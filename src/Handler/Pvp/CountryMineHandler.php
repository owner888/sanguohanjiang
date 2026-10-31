<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\Activity\MessageHandler;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\CountryMineEvent;
use Hanjiang\MasterData\CountryMineRatio;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 国家矿脉（移植旧版 controllers/countrymine.php + models/countrymine_model.php，路径相对旧版 s1/application/）
 *
 * 主公 91 级且加入国家（member.country = 国家 id）后开放。矿区共 10 页（MINE_NUM），每个位置的矿类型对应
 * MasterData CountryMineRatio（玉石产量系数 times、触发事件概率 rate）；每人同时只能占一个矿，每次占领 15 分钟，
 * 每日 3 次开采、3 次抢夺（玩家首次进入 / 跨天时在 zc_sanguo_country_mine 重置）。收获 / 抢夺可按概率触发
 * MasterData CountryMineEvent 事件（1 紫宝石、2 玉石倍数、3 元宝）。抢夺对手是占矿的其他国家玩家（battle_pk）。
 *
 * 客户端调用（countrymineworld.js / miscpop.js）：index、get_countrymine_info、caikuang、rob、get_reward、give_up、
 * send_to_chat。
 *
 * 依赖未移植：
 * - 好友（friend_model->get_list / get_friend_by_id，新版好友为自定数据），friend 恒为 0；
 * - message_model->record_pk_report 信件战报；日常任务 task_model->task_action(13)（主公 41 级以上）；
 * - 聊天室战报分享（send_to_chat，见该方法）；元宝流水。
 */
final class CountryMineHandler
{
    /** 矿战开放等级 */
    private const int OPEN_LEVEL = 91;

    /** 玉石流水的执行类（旧版 $this->class） */
    private const string LOG_CLASS = 'countrymine';

    /** 最大 / 最小的翻页数 */
    private const int MAX_PAGE = 10;
    private const int MIN_PAGE = 1;

    /** 总的占领时间（秒） */
    private const int TOTAL_TIME = 900;

    /** 间隔更新收益时间（秒） */
    private const int TIME = 60;

    /** 每日免费占矿 / 抢夺次数 */
    private const int TIMES = 3;
    private const int ROB_TIMES = 3;

    /** 矿区锁（秒） */
    private const string LOCK_KEY = 'countrymine_lock_';
    private const int LOCK_TTL = 600;

    /** 国家矿区信件（旧版 send_message type 11，发件人为抢夺者本人） */
    private const int MESSAGE_TYPE = 11;

    /** 分享到聊天室的元宝花费 */
    private const int SHARE_PRICE = 2;

    /** 旧版 zc_sanguo_country_mine 的字段 */
    private const array MINE_COLUMNS = ['uid', 'times', 'robtimes', 'page', 'site', 'type', 'level', 'jointime', 'dateline'];

    /**
     * 每页的矿：矿类型（CountryMineRatio.id） => 个数（旧版 mine_num，位置按页内顺序从 1 编号）
     */
    private const array MINE_NUM = [
        1 => [1 => 1, 2 => 8],
        2 => [3 => 12],
        3 => [4 => 12],
        4 => [5 => 12],
        5 => [5 => 12],
        6 => [5 => 12],
        7 => [5 => 12],
        8 => [5 => 12],
        9 => [5 => 12],
        10 => [5 => 12],
    ];

    private const string LANG_COUNTRY_NULL = '无';
    private const string LANG_MESSAGE_COUNTRYMINE_WIN = '攻打了您的国家矿，被您击败了。';
    private const string LANG_MESSAGE_COUNTRYMINE_LOSE = '攻打了您的国家矿，掠夺了你的玉石%s';

    /**
     * 获得矿区页面: c=countrymine&m=index（p 页码，不传按自己占的矿所在页）
     *
     * status：2 等级不足；3 未加入国家；-1 页码有误；1 成功
     */
    public static function index(Context $ctx): array
    {
        $now = BossClock::now();
        $return = ['status' => 0];
        // 页数
        $page = (int)$ctx->input('p', 0);
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || !self::isOpen($member)) {
            // 等级不足,未开放
            $return['status'] = 2;
            return $return;
        }
        if ((int)$member['country'] <= 0) {
            $return['status'] = 3;
            return $return;
        }
        $uid = (int)$member['uid'];

        // 取用户记录
        $log = self::getInfo($uid, $now);
        if ($page === 0 && $log !== null) {
            // 按记录查询位置，初试页面根据此页数显示
            $page = (int)$log['page'];
        }
        if ($page === 0) {
            $page = 1;
        }

        // 根据页码查询所有信息（旧版负页码取不到矿区配置、返回空列表的成功结果，这里按页码有误处理）
        $list = self::getListByPage($page, (int)$member['level']);
        if ($list === null) {
            $return['status'] = -1;
            return $return;
        }

        // 取该页角色
        $roleList = self::getInfoBySite($page);
        if ($roleList !== []) {
            $uidarr = array_map(static fn(array $val): int => $val['uid'], $roleList);
            // 按uid数组查询角色信息
            $userinfo = [];
            foreach (DB::table('member')->select('uid', 'nickname', 'gender', 'level', 'country')->whereIn('uid', $uidarr)->whereNull('deleted_at')->get() as $row) {
                $userinfo[(int)$row['uid']] = $row;
            }
            // 按国家id查询国家名称
            $countryList = self::countryNames(array_map(static fn(array $row): int => (int)$row['country'], $userinfo));

            // 把对应位置的用户信息放到矿石列表中
            foreach ($list as $key => $val) {
                $role = $roleList[$val['id']] ?? null;
                if ($role === null) {
                    $list[$key]['get_jade'] = 0;
                    continue;
                }
                $user = $userinfo[$role['uid']] ?? null;
                $time = self::TOTAL_TIME - ($now - $role['jointime']);
                // 该矿占领者用户信息（等级为占矿时的等级）
                $list[$key]['nickname'] = $user !== null ? (string)$user['nickname'] : null;
                $list[$key]['gender'] = $user !== null ? (int)$user['gender'] : null;
                $list[$key]['level'] = $role['level'];
                $list[$key]['uid'] = $user !== null ? (int)$user['uid'] : null;
                $userCountry = $user !== null ? (int)$user['country'] : 0;
                $list[$key]['country'] = $userCountry > 0 ? ($countryList[$userCountry] ?? null) : self::LANG_COUNTRY_NULL;
                $list[$key]['country_name'] = mb_substr((string)$list[$key]['country'], 0, 1);
                $list[$key]['status'] = $role['uid'] === $uid ? 2 : 1;
                // 累计占领时间
                $list[$key]['time'] = max(0, $time);
                // 根据用户计算玉石数量
                $calc = self::calc($role['level'], $val['series']);
                // 总收益
                $list[$key]['jade'] = (int)ceil($calc['total_reward']);
                // 累计收益
                if ($list[$key]['time'] === 0) {
                    $list[$key]['get_jade'] = (int)ceil($calc['total_reward']);
                } else {
                    $list[$key]['get_jade'] = (int)ceil($calc['reward'] * floor((self::TOTAL_TIME - $list[$key]['time']) / self::TIME));
                }
                if ($list[$key]['get_jade'] < 0) {
                    $list[$key]['get_jade'] = 0;
                }
                // 依赖未移植：好友（friend_model->get_list），friend 恒为 0
                $list[$key]['friend'] = 0;
            }
        } else {
            foreach ($list as $key => $val) {
                $list[$key]['get_jade'] = 0;
            }
        }

        if ($page === 1) {
            // 第 1 页第一个矿（唯一的 1 类矿）挪到第 5 个位置显示
            $first = [array_shift($list)];
            array_splice($list, 4, 0, $first);
        }
        $return['status'] = 1;
        $return['page'] = $page + 1 > self::MAX_PAGE ? 0 : $page + 1;
        $return['log'] = $log ?? [];
        $return['list'] = $list;
        $return['dateline'] = $now;
        return $return;
    }

    /**
     * 查询矿区信息: c=countrymine&m=get_countrymine_info（p 页码，id 位置，t 矿类型）
     *
     * status：2 等级不足；3 未加入国家；1 成功
     */
    public static function get_countrymine_info(Context $ctx): array
    {
        $now = BossClock::now();
        $return = ['status' => 0];
        $page = (int)$ctx->input('p', 0);
        $site = (int)$ctx->input('id', 0);
        $type = (int)$ctx->input('t', 0);
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || !self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }
        if ((int)$member['country'] <= 0) {
            $return['status'] = 3;
            return $return;
        }
        $uid = (int)$member['uid'];

        $info = [];
        // 查询要抢夺矿的信息（旧版不传位置时按整页查询，取不到占领者字段；这里按空矿处理）
        $mineLog = $site > 0 ? self::getMineBySite($page, $site) : null;
        if ($mineLog === null) {
            // 查询空矿的信息
            $calc = self::calc((int)$member['level'], $type, true);
            $info['status'] = 0;
            $info['get_jade'] = 0;
            $info['jade'] = $calc['total_reward'];
            $info['time'] = 0;
            $info['friend'] = 0;
            $info['country'] = 0;
            $info['uid'] = 0;
            $info['nickname'] = 0;
        } else {
            // 该矿占领者用户信息
            $user = self::member($mineLog['uid']);
            $info['uid'] = $mineLog['uid'];
            $info['nickname'] = $user !== null ? (string)$user['nickname'] : '';
            // 查询国家名称
            $info['country'] = self::LANG_COUNTRY_NULL;
            if ($user !== null && (int)$user['country'] > 0) {
                $country = CountryHandler::countryInfo((int)$user['country']);
                if ($country !== null) {
                    $info['country'] = (string)$country['name'];
                }
            }
            $info['country_name'] = mb_substr($info['country'], 0, 1);
            $info['status'] = $mineLog['uid'] === $uid ? 2 : 1;
            // 累计占领时间
            $time = self::TOTAL_TIME - ($now - $mineLog['jointime']);
            $info['time'] = max(0, $time);
            // 根据用户计算玉石数量
            $calc = self::calc($mineLog['level'], $mineLog['type'], true);
            // 总收益
            $info['jade'] = (int)ceil($calc['total_reward']);
            // 累计收益
            if ($info['time'] === 0) {
                $info['get_jade'] = (int)ceil($calc['total_reward']);
            } else {
                $info['get_jade'] = (int)ceil($calc['reward'] * floor((self::TOTAL_TIME - $info['time']) / self::TIME));
            }
            if ($info['get_jade'] < 0) {
                $info['get_jade'] = 0;
            }
            // 依赖未移植：好友（friend_model->get_friend_by_id），friend 恒为 0
            $info['friend'] = 0;
        }

        $return['status'] = 1;
        $return['info'] = $info;
        return $return;
    }

    /**
     * 收获: c=countrymine&m=get_reward
     *
     * status：2 等级不足；3 未加入国家；-1 没有矿区记录；-2 没有占领矿；-3 未到收获时间；1 成功
     */
    public static function get_reward(Context $ctx): array
    {
        $now = BossClock::now();
        $return = [];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || !self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }
        if ((int)$member['country'] <= 0) {
            $return['status'] = 3;
            return $return;
        }
        $uid = (int)$member['uid'];

        // 取矿的信息
        $log = self::getInfo($uid, $now);
        if ($log === null) {
            $return['status'] = -1;
            return $return;
        }
        // 判断自己是否占领了一个矿
        if (!($log['page'] > 0 && $log['site'] > 0)) {
            $return['status'] = -2;
            return $return;
        }
        // 判断是否到了收获的时间
        if ($now - $log['jointime'] < self::TOTAL_TIME) {
            $return['status'] = -3;
            return $return;
        }

        // 执行收矿操作（只收读到的这个矿：多 Worker 并发收获 / 被抢只有一个成功，没收到按没有占领矿处理）
        if (!self::clearMine($uid, $log, $now)) {
            $return['status'] = -2;
            return $return;
        }

        // 计算可收获的玉石数量
        $calc = self::calc($log['level'], $log['type'], true);

        // 触发随机事件
        $event = self::emptyEvent();
        if (mt_rand(1, 100) <= $calc['rate']) {
            $event = self::triggerEvent($uid, $calc['total_reward'], 'get_reward');
            $calc['total_reward'] = $event['jade'];
        }

        // 加玉石
        if ($calc['total_reward'] < 0) {
            $calc['total_reward'] = 0;
        }
        $allJade = MemberJade::add($uid, (int)$calc['total_reward'], self::LOG_CLASS, 'get_reward', $now)['jade'];

        self::updateMineDateline($uid, $now, true);

        $after = self::member($uid) ?? $member;
        $return['status'] = 1;
        $return['event_status'] = $event['status'];
        $return['event_name'] = $event['name'];
        $return['event_type'] = $event['type'];
        $return['event_value'] = $event['value'];
        $return['event_add'] = $event['add'];
        $return['jade'] = $calc['total_reward'];
        $return['all_jade'] = $allJade;
        $return['all_gold'] = (int)$after['gold'];
        return $return;
    }

    /**
     * 抢矿: c=countrymine&m=rob（p 页码，id 位置，t 矿类型）
     *
     * status：-7 该矿正在操作中；2 等级不足；3 未加入国家；-1 没有矿区记录；-2 已占领一个矿；-3 抢夺次数不足；
     * -4 该矿没有被占领（或位置 / 类型与矿区配置不符）；-5 同国家的矿；-6 扣除次数失败；1 成功
     */
    public static function rob(Context $ctx): array
    {
        $now = BossClock::now();
        $return = ['status' => 0];
        // 传点击的页码和site
        $page = (int)$ctx->input('p', 0);
        $site = (int)$ctx->input('id', 0);
        $type = (int)$ctx->input('t', 0);

        if (!self::addLock($page, $site)) {
            // 有人正在操作中
            $return['status'] = -7;
            return $return;
        }
        try {
            return self::doRob(MemberHandler::resolveUid($ctx), $page, $site, $type, $now);
        } finally {
            self::unlock($page, $site);
        }
    }

    /**
     * 开采: c=countrymine&m=caikuang（p 页码，id 位置，t 矿类型）
     *
     * status：-5 页码错误 / 该矿正在操作中（或位置 / 类型与矿区配置不符）；2 等级不足；3 未加入国家；-1 没有矿区记录；
     * -2 已占领一个矿；-3 开采次数不足；-4 该矿已被占领；0 开采失败；1 成功
     */
    public static function caikuang(Context $ctx): array
    {
        $now = BossClock::now();
        $return = ['status' => 0];
        // 需要矿区的页码 / 位置 / 矿的类型
        $page = (int)$ctx->input('p', 0);
        $site = (int)$ctx->input('id', 0);
        $type = (int)$ctx->input('t', 0);

        if ($page > self::MAX_PAGE || $page < self::MIN_PAGE) {
            // 操作错误
            $return['status'] = -5;
            return $return;
        }
        // 旧版不校验位置与类型，会写入页面上不存在的矿；这里不符时按操作错误处理
        if (self::siteType($page, $site) !== $type) {
            $return['status'] = -5;
            return $return;
        }
        if (!self::addLock($page, $site)) {
            // 有人正在操作中
            $return['status'] = -5;
            return $return;
        }
        try {
            $member = self::member(MemberHandler::resolveUid($ctx));
            if ($member === null || !self::isOpen($member)) {
                $return['status'] = 2;
                return $return;
            }
            if ((int)$member['country'] <= 0) {
                $return['status'] = 3;
                return $return;
            }
            $uid = (int)$member['uid'];
            // 取矿区信息
            $log = self::getInfo($uid, $now);
            if ($log === null) {
                $return['status'] = -1;
                return $return;
            }
            // 判断自己是否已经占领一个矿区
            if ($log['page'] > 0 && $log['site'] > 0) {
                $return['status'] = -2;
                return $return;
            }
            // 判断次数是否足够
            if ($log['times'] <= 0) {
                $return['status'] = -3;
                return $return;
            }
            // 查询该矿是否已经被别人抢占了
            if (self::getMineBySite($page, $site) !== null) {
                $return['status'] = -4;
                return $return;
            }

            // 开采并消耗次数（一条 UPDATE：仍未占矿且有次数时才成功，多 Worker 并发开采不会占两个矿）
            $affected = DB::execute(
                'UPDATE `zc_sanguo_country_mine` SET `page` = :page, `site` = :site, `type` = :type, `level` = :level,
                 `jointime` = :now, `times` = `times` - 1
                 WHERE `uid` = :uid AND `page` = 0 AND `site` = 0 AND `times` > 0 AND `deleted_at` IS NULL',
                ['page' => $page, 'site' => $site, 'type' => $type, 'level' => (int)$member['level'], 'now' => $now, 'uid' => $uid]
            );
            if ($affected > 0) {
                // 按国家id查询国家信息
                $return['country'] = self::countryName((int)$member['country']);
                $return['country_name'] = mb_substr((string)$return['country'], 0, 1);
                $return['status'] = 1;
                $return['dateline'] = self::TOTAL_TIME;
                $return['times'] = $log['times'] - 1;
                self::updateMineDateline($uid, $now);
            }
            return $return;
        } finally {
            self::unlock($page, $site);
        }
    }

    /**
     * 放弃: c=countrymine&m=give_up
     *
     * status：2 等级不足；3 未加入国家；-1 没有矿区记录；-2 没有占领矿；-3 已可收获不能放弃；0 放弃失败；1 成功
     */
    public static function give_up(Context $ctx): array
    {
        $now = BossClock::now();
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || !self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }
        if ((int)$member['country'] <= 0) {
            $return['status'] = 3;
            return $return;
        }
        $uid = (int)$member['uid'];
        // 查询矿的信息
        $log = self::getInfo($uid, $now);
        if ($log === null) {
            $return['status'] = -1;
            return $return;
        }
        // 判断是否占领了一个矿区
        if ($log['page'] === 0 && $log['site'] === 0) {
            $return['status'] = -2;
            return $return;
        }
        $time = $now - $log['jointime'];
        if ($time >= self::TOTAL_TIME) {
            // 该矿可以收获了，不能放弃
            $return['status'] = -3;
            return $return;
        }
        // 取可收获的玉石
        $reward = self::calc($log['level'], $log['type'], true);
        $return['jade'] = (int)($reward['reward'] * floor($time / self::TIME));
        if ($return['jade'] < 0) {
            $return['jade'] = 0;
        }
        $return['all_jade'] = MemberJade::get($uid);
        // 执行放弃操作（只放弃读到的这个矿）；旧版先加玉石再放弃，这里放弃成功才加，并发收获 / 被抢时不重复发
        if (self::clearMine($uid, $log, $now)) {
            // 增加玉石
            $return['all_jade'] = MemberJade::add($uid, $return['jade'], self::LOG_CLASS, 'give_up', $now)['jade'];
            // 放弃成功
            $return['status'] = 1;
            self::updateMineDateline($uid, $now, true);
        }
        return $return;
    }

    /**
     * 分享战报: c=countrymine&m=send_to_chat（touid 对方 uid，report 信件 id，win 战斗评级）
     *
     * status：-1 参数不完整；-2 元宝不足；-3 对方不存在；1 成功
     */
    public static function send_to_chat(Context $ctx): array
    {
        $touid = (int)$ctx->input('touid', 0);
        $report = (int)$ctx->input('report', 0);
        $member = self::member(MemberHandler::resolveUid($ctx));
        // 判断参数是否完整
        if ($touid === 0 || $report === 0 || $member === null) {
            return ['status' => -1];
        }
        // 取用户信息
        if (self::member($touid) === null) {
            return ['status' => -3];
        }
        // 依赖未移植：chat_model->get_message_str(26, to / id / win) + send_message_to_all(…, 3, report) 聊天室战报广播。
        // 旧版在这里先校验元宝(-2)、扣 SHARE_PRICE 再广播；聊天室未移植前广播发不出去，
        // 开发者要求暂不扣元宝，返回 0（失败），客户端提示「暂时无法分享战报」。聊天室移植后恢复旧版流程。
        return ['status' => 0];
    }

    /**
     * @return array<string, mixed>
     */
    private static function doRob(int $selfUid, int $page, int $site, int $type, int $now): array
    {
        $return = ['status' => 0];
        $member = self::member($selfUid);
        if ($member === null || !self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }
        if ((int)$member['country'] <= 0) {
            $return['status'] = 3;
            return $return;
        }
        $uid = (int)$member['uid'];
        // 获取自己的信息
        $log = self::getInfo($uid, $now);
        if ($log === null) {
            $return['status'] = -1;
            return $return;
        }
        // 判断是否占领了矿
        if ($log['page'] > 0 && $log['site'] > 0) {
            $return['status'] = -2;
            return $return;
        }
        // 判断次数是否足够
        if ($log['robtimes'] <= 0) {
            $return['status'] = -3;
            return $return;
        }
        // 查询要抢夺矿的信息（旧版不校验矿类型，会按传入的类型占领；与矿区配置不符时按该矿不可抢夺处理）
        $mineLog = $site > 0 && self::siteType($page, $site) === $type ? self::getMineBySite($page, $site) : null;
        if ($mineLog === null) {
            $return['status'] = -4;
            return $return;
        }
        $touid = $mineLog['uid'];
        // 查询对方国家信息
        $toCountry = DB::table('member_country')->where('uid', $touid)->whereNull('deleted_at')->first();
        if ($toCountry !== null && (int)$toCountry['country'] === (int)$member['country']) {
            $return['status'] = -5;
            return $return;
        }

        // 抢夺 调用算法
        $battle = ArenaHandler::battlePk($uid, $touid);
        $assess = $battle['info']['assess'] ?? null;

        // 扣除次数
        $affected = DB::table('country_mine')->where('uid', $uid)->where('robtimes', '>', 0)->whereNull('deleted_at')
            ->decrement('robtimes', 1);
        if ($affected <= 0) {
            $return['status'] = -6;
            return $return;
        }

        $event = self::emptyEvent();
        $getJade = 0;
        if ($assess !== null && $assess >= 1) {
            // 胜利 把对方的占领状态去掉（只清读到的这个矿）
            self::clearMine($touid, $mineLog, $now);
            // 自己占领该矿区
            DB::table('country_mine')->where('uid', $uid)->whereNull('deleted_at')->update([
                'page' => $page,
                'site' => $site,
                'type' => $type,
                'level' => (int)$member['level'],
                'jointime' => $now,
            ]);
            // 获得可以抢到的玉石数量
            $reward = self::calc($mineLog['level'], $mineLog['type'], true);
            $time = min($now - $mineLog['jointime'], self::TOTAL_TIME);
            $getJade = (int)($reward['reward'] * floor($time / self::TIME));
            if ($getJade < 0) {
                $getJade = 0;
            }

            // 触发随机事件
            if ($getJade > 0 && mt_rand(1, 100) <= $reward['rate']) {
                $event = self::triggerEvent($uid, $getJade, 'rob');
                $getJade = (int)$event['jade'];
            }

            // 发信
            $pmid = self::sendMessage($member, $touid, sprintf(self::LANG_MESSAGE_COUNTRYMINE_LOSE, $getJade));

            MemberJade::add($uid, $getJade, self::LOG_CLASS, 'rob', $now);

            self::updateMineDateline($touid, $now, true);
            self::updateMineDateline($uid, $now);
            // 记录国家战报 - 抢矿日志
            self::addFightNoticeLog($member, $touid, 1, $now);
        } else {
            // 失败
            $pmid = self::sendMessage($member, $touid, self::LANG_MESSAGE_COUNTRYMINE_WIN);
        }

        $info = [];
        // 按国家id查询国家信息
        $info['country'] = self::countryName((int)$member['country']);
        $info['country_name'] = mb_substr((string)$info['country'], 0, 1);

        // 查询对方职业
        $toMember = self::member($touid);

        // 依赖未移植：日常任务 task_model->task_action(13)（国家矿区抢夺，主公 41 级以上）

        $after = self::member($uid) ?? $member;
        $return['status'] = 1;
        $info['win'] = $assess;
        $info['robtimes'] = $log['robtimes'] - 1;
        $info['jade'] = $getJade;
        $info['all_jade'] = MemberJade::get($uid);
        $info['gender'] = $toMember !== null ? (int)$toMember['gender'] : null;
        $info['name'] = $toMember !== null ? (string)$toMember['nickname'] : null;
        $info['all_gold'] = (int)$after['gold'];
        $return['battle'] = $battle['info'] ?? [];
        $return['battle']['left_role'] = [
            'name' => (string)$member['nickname'],
            'level' => (int)$member['level'],
            'gender' => (int)$member['gender'],
        ];
        $return['battle']['right_role'] = [
            'name' => $info['name'],
            'level' => $toMember !== null ? (int)$toMember['level'] : null,
            'gender' => $info['gender'],
        ];

        // 依赖未移植：message_model->record_pk_report 信件战报（from / fromlevel / to / tolevel / report 落库供信件回放）

        $info['report'] = $pmid;
        $info['event_status'] = $event['status'];
        $info['event_name'] = $event['name'];
        $info['event_type'] = $event['type'];
        $info['event_value'] = $event['value'];
        $info['event_add'] = $event['add'];
        $return['info'] = $info;
        $return['share'] = [
            'id' => $pmid,
            'type' => 'countrymine',
        ];
        return $return;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
    }

    /**
     * _open：主公等级 >= 91
     *
     * @param array<string, mixed> $member
     */
    private static function isOpen(array $member): bool
    {
        return (int)$member['level'] >= self::OPEN_LEVEL;
    }

    /**
     * 查询自己的矿区信息（countrymine_model->get_info：跨天重置每日开采 / 抢夺次数，没有记录时建档）
     *
     * 跨天重置以 dateline 条件原子更新；另一个 Worker 已重置时直接重读（旧版此时返回失败）。
     *
     * @return array<string, int>|null
     */
    private static function getInfo(int $uid, int $now): ?array
    {
        $today = (int)strtotime(date('Y-m-d', $now));
        $row = self::mineRow($uid);
        if ($row !== null) {
            if (date('ymd', $row['dateline']) !== date('ymd', $now)) {
                // 修改每日次数
                DB::execute(
                    'UPDATE `zc_sanguo_country_mine` SET `dateline` = :now, `times` = :times, `robtimes` = :robtimes
                     WHERE `uid` = :uid AND `deleted_at` IS NULL AND (`dateline` < :today OR `dateline` >= :tomorrow)',
                    ['now' => $now, 'times' => self::TIMES, 'robtimes' => self::ROB_TIMES, 'uid' => $uid, 'today' => $today, 'tomorrow' => $today + 86400]
                );
                $row = self::mineRow($uid);
            }
            return $row;
        }
        // 添加一条数据
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_country_mine` (`uid`, `times`, `robtimes`, `page`, `site`, `type`, `level`, `jointime`, `dateline`)
             VALUES (:uid, :times, :robtimes, 0, 0, 0, 0, 0, :now)',
            ['uid' => $uid, 'times' => self::TIMES, 'robtimes' => self::ROB_TIMES, 'now' => $now]
        );
        return self::mineRow($uid);
    }

    /**
     * @return array<string, int>|null
     */
    private static function mineRow(int $uid): ?array
    {
        $row = DB::table('country_mine')->select(...self::MINE_COLUMNS)->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * 该页被占领的矿（countrymine_model->get_info_by_site($page)），按位置索引
     *
     * @return array<int, array<string, int>>
     */
    private static function getInfoBySite(int $page): array
    {
        $return = [];
        foreach (DB::table('country_mine')->select(...self::MINE_COLUMNS)->where('page', $page)->whereNull('deleted_at')->get() as $row) {
            $row = array_map('intval', $row);
            $return[$row['site']] = $row;
        }
        return $return;
    }

    /**
     * 某个位置的占领记录（countrymine_model->get_info_by_site($page, $site)）
     *
     * @return array<string, int>|null
     */
    private static function getMineBySite(int $page, int $site): ?array
    {
        $row = DB::table('country_mine')->select(...self::MINE_COLUMNS)->where('page', $page)->where('site', $site)
            ->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * 清除占领状态（update_mine() 不带参数：page / site / type / level 归 0、jointime 记为当前时间）
     *
     * 只清除读到的那个矿（同一页同一位置同一占矿时间），已被收获 / 放弃 / 抢走时返回 false。
     *
     * @param array<string, int> $log
     */
    private static function clearMine(int $uid, array $log, int $now): bool
    {
        $affected = DB::table('country_mine')->where('uid', $uid)->where('page', $log['page'])->where('site', $log['site'])
            ->where('jointime', $log['jointime'])->whereNull('deleted_at')
            ->update(['page' => 0, 'site' => 0, 'type' => 0, 'level' => 0, 'jointime' => $now]);
        return $affected > 0;
    }

    /**
     * 该页某位置的矿类型（旧版 get_list_buy_page 的编号规则），位置不存在为 null
     */
    private static function siteType(int $page, int $site): ?int
    {
        $i = 0;
        foreach (self::MINE_NUM[$page] ?? [] as $type => $num) {
            $i += $num;
            if ($site >= 1 && $site <= $i) {
                return $type;
            }
        }
        return null;
    }

    /**
     * 根据页码查询该页所有矿（countrymine_model->get_list_buy_page），按当前主公等级计算收益
     *
     * @return list<array<string, mixed>>|null 页码有误为 null
     */
    private static function getListByPage(int $page, int $level): ?array
    {
        if (!isset(self::MINE_NUM[$page])) {
            return null;
        }
        $list = [];
        $j = 0;
        foreach (self::MINE_NUM[$page] as $type => $num) {
            $series = CountryMineRatio::ROWS[$type]['times'] ?? null;
            $calc = self::calc($level, $series);
            for ($i = $j; $i < $j + $num; $i++) {
                $list[$i] = [
                    'id' => $i + 1,
                    'page' => $page,
                    'type' => $type,
                    'status' => 0,
                    'series' => self::num($series),
                    'jade' => $calc['total_reward'],
                    'get_jade' => $calc['reward'],
                ];
            }
            $j = $i;
        }
        return $list;
    }

    /**
     * 收益算法（countrymine_model->calc）：总收益 = 等级 × 120 × 矿系数，每分钟收益 = 总收益 / 15
     *
     * @param int|float|null $series 矿的系数；$byType 为 true 时为矿的类型（CountryMineRatio.id），并返回触发事件概率
     * @return array{reward: int, total_reward: int, rate: int}
     */
    private static function calc(int $level, int|float|null $series, bool $byType = false): array
    {
        $return = ['reward' => 0, 'total_reward' => 0, 'rate' => 0];
        if ($byType) {
            $row = CountryMineRatio::ROWS[(int)$series] ?? null;
            $series = $row['times'] ?? null;
            $return['rate'] = $row['rate'] ?? 0;
        }
        $totalReward = $level * 120 * (float)$series;
        $reward = $totalReward / (self::TOTAL_TIME / self::TIME);
        $return['reward'] = (int)floor($reward);
        $return['total_reward'] = (int)floor($totalReward);
        return $return;
    }

    /**
     * @return array{status: int, name: string, type: int, value: int|float, add: int, jade: int}
     */
    private static function emptyEvent(): array
    {
        return ['status' => 0, 'name' => '', 'type' => 0, 'value' => 0, 'add' => 0, 'jade' => 0];
    }

    /**
     * 触发随机事件（按 CountryMineEvent.rate 权重抽一个）：1 紫宝石、2 玉石产量倍数、3 元宝
     *
     * @return array{status: int, name: string, type: int, value: int|float, add: int, jade: int} jade 为事件后的玉石数
     */
    private static function triggerEvent(int $uid, int $jade, string $method): array
    {
        $event = self::emptyEvent();
        $event['jade'] = $jade;
        $list = array_values(CountryMineEvent::ROWS);
        if ($list === []) {
            return $event;
        }
        $totalRate = 0;
        foreach ($list as $val) {
            $totalRate += $val['rate'];
        }
        $eventKey = 0;
        $cursor = 0;
        $rands = mt_rand(1, $totalRate);
        foreach ($list as $key => $val) {
            $cursor += $val['rate'];
            if ($rands <= $cursor) {
                $eventKey = $key;
                break;
            }
        }
        $row = $list[$eventKey];
        if ($row['type'] === 1) {
            // 添加紫宝石
            TavernHandler::addJade($uid, 3, (int)$row['value'], self::LOG_CLASS, $method);
        } elseif ($row['type'] === 2) {
            // 获得的玉石加倍
            $total = (int)($jade * $row['value']);
            $event['add'] = $total - $jade;
            $event['jade'] = $total;
        } elseif ($row['type'] === 3) {
            // 添加元宝
            Member::addCurrency($uid, gold: (int)$row['value']);
        }
        $event['status'] = 1;
        $event['name'] = $row['name'];
        $event['type'] = $row['type'];
        $event['value'] = self::num($row['type'] === 2 ? $row['value'] * 100 : $row['value']);
        return $event;
    }

    /**
     * 配置里的小数按旧版输出：整数值输出为整数（旧版库里取出的是字符串，json 里不带小数点）
     */
    private static function num(int|float|null $value): int|float|null
    {
        if (is_float($value) && abs($value - round($value)) < 1e-9) {
            return (int)round($value);
        }
        return $value;
    }

    /**
     * 修改占矿到期时间（update_mine_dateline：member_field.oncountrymine，主城据此提示可收获；收获 / 放弃 / 被抢时清 0）
     */
    private static function updateMineDateline(int $uid, int $now, bool $clear = false): void
    {
        DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')
            ->update(['oncountrymine' => $clear ? 0 : $now + self::TOTAL_TIME]);
        Redis::del('player:' . $uid);
    }

    /**
     * 矿区加锁（countrymine_model->get_lock / add_lock，600 秒）：原子加锁，已有锁时失败
     */
    private static function addLock(int $page, int $site): bool
    {
        $redis = Redis::instance();
        return $redis !== null && (bool)$redis->set(self::LOCK_KEY . $page . '_' . $site, '1', ['nx', 'ex' => self::LOCK_TTL]);
    }

    private static function unlock(int $page, int $site): void
    {
        Redis::del(self::LOCK_KEY . $page . '_' . $site);
    }

    /**
     * 国家名称（未加入国家或国家不存在时为「无」）
     */
    private static function countryName(int $country): ?string
    {
        if ($country <= 0) {
            return self::LANG_COUNTRY_NULL;
        }
        $info = CountryHandler::countryInfo($country);
        return $info !== null ? (string)$info['name'] : null;
    }

    /**
     * @param list<int> $ids
     * @return array<int, string>
     */
    private static function countryNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $return = [];
        foreach (DB::table('country')->select('id', 'name')->whereIn('id', $ids)->whereNull('deleted_at')->get() as $row) {
            $return[(int)$row['id']] = (string)$row['name'];
        }
        return $return;
    }

    /**
     * 国家矿区信件（发件人为抢夺者本人）
     *
     * @param array<string, mixed> $member
     */
    private static function sendMessage(array $member, int $touid, string $text): int
    {
        $message = '<em uid="' . (int)$member['uid'] . '">' . $member['nickname'] . '</em>' . $text;
        return MessageHandler::sendSystemMessage([$touid], self::MESSAGE_TYPE, (string)$member['nickname'], $message, 0, (int)$member['uid']);
    }

    /**
     * 记录国家战报 - 抢矿日志（countryfight_model->add_fight_notice_log($touid, $win)，type 0 国家抢矿）
     *
     * @param array<string, mixed> $member 抢夺者
     */
    private static function addFightNoticeLog(array $member, int $touid, int $win, int $now): void
    {
        // 被抢玩家的国家信息（member_country + country）
        $toMemberCountry = DB::table('member_country')->where('uid', $touid)->whereNull('deleted_at')->first();
        $toCountry = $toMemberCountry !== null ? CountryHandler::countryInfo((int)$toMemberCountry['country']) : null;
        // 抢夺者国家信息
        $myMemberCountry = DB::table('member_country')->where('uid', (int)$member['uid'])->whereNull('deleted_at')->first();
        $myCountry = $myMemberCountry !== null ? CountryHandler::countryInfo((int)$myMemberCountry['country']) : null;
        DB::table('country_fight_notice')->insert([
            'type' => 0,
            'attack_uid' => (int)$member['uid'],
            'attack_nickname' => (string)$member['nickname'],
            'attack_country' => (int)$member['country'],
            'attack_country_name' => (string)($myCountry['name'] ?? ''),
            'win' => $win,
            'dateline' => $now,
            'member_country' => (int)($toCountry['id'] ?? 0),
            'member_country_name' => (string)($toCountry['name'] ?? ''),
            'member1' => $touid,
            'member1_name' => (string)($toMemberCountry['nickname'] ?? ''),
            'member2' => 0,
            'member2_name' => '',
        ]);
    }
}
