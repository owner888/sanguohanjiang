<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\Pvp\ArenaHandler;
use Hanjiang\Handler\Pvp\CountryHandler;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\DailyTask;
use Hanjiang\Model\Edifice;
use Hanjiang\Model\MainQuestTask;
use Hanjiang\Model\Member;
use Hanjiang\Model\Message;
use Hanjiang\Model\Pay;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 银矿（移植旧版 controllers/mine.php + models/mine_model.php）
 *
 * 客户端（mineworld.js / miscpop.js）调用：index(p) / caikuang(p, id, t) / zhanling(p, id, t) / get_silver(s) / give_up / send_to_chat。
 * 主公 20 级开放。矿区按主公等级每 10 级一段（mine.level 是占矿时的主公等级），每段 5 页：第 1 页 1 个 1 型 + 8 个 2 型，
 * 第 2 ~ 5 页各 12 个 3 / 4 / 5 / 5 型（mine_num）；矿型系数 1 ~ 5 → 6 / 5 / 4 / 3 / 2（mine_series）。
 * 每 6 分钟收益 = 系数 × 等级段 × 12.5，占满 4 小时可收获（total_silver = 40 次）；放弃 / 被抢时按已占时间算银币。
 * 每人同时只能占一个矿；开采 / 抢夺各消耗 1 军令；抢夺按旧版 battle_pk 打矿主（ArenaHandler::battlePk），胜利后接管该矿
 * （按对方已占时间掠夺银币，重新计时）并给矿主发信件战报（type 3）。member_field.onmine 记占矿时间（主城 onmine 气泡）。
 * 开采 / 抢夺都计日常任务 3（主公 41 级以上）与主线任务 7。打开时记主城建筑首次打开（edifice 2）。
 * 双倍活动取 ActivityTime 的 double_mine（旧版数据未开启）。
 *
 * 与旧版的差别（多 Worker 安全）：接口在 Member::locked（玩家行锁）里执行；mine 表加唯一约束（每人一个、同一段同页同位置
 * 一个），并发开采同一个矿时后到的失败（旧版会插出两行）；抢夺以「矿主仍是对方」条件更新，没抢到（被别人先抢）时不给银币；
 * 收获 / 放弃先软删除自己的矿再加银币（旧版放弃先加银币后删除）。旧版开采不校验客户端传的矿型 t，与矿区配置不符时按矿已被
 * 占领（-2）处理（旧版会按传入的矿型记收益）。
 * 依赖未移植：计划任务 cron::push_mine_list / mine（矿区可收获的 iOS 推送，需要设备 token 与推送服务）。
 */
final class MineHandler
{
    /** 总共占领矿区时间（旧版 $total_time） */
    private const int TOTAL_TIME = 14400;
    /** 累加银币间隔时间（旧版 $time） */
    private const int TIME = 360;
    /** 矿战开放等级 */
    private const int LEVEL = 20;
    /** 矿区系数（mine_model->$_ratio） */
    private const float RATIO = 12.5;
    /** 矿型系数（mine_series） */
    private const array SERIES = [1 => 6, 2 => 5, 3 => 4, 4 => 3, 5 => 2];
    /** 每页的矿型与个数（mine_num） */
    private const array NUM = [
        1 => [1 => 1, 2 => 8],
        2 => [3 => 12],
        3 => [4 => 12],
        4 => [5 => 12],
        5 => [5 => 12],
    ];
    /** 双倍奖励活动（ActivityTime.name） */
    private const string DOUBLE_ACTIVITY = 'double_mine';
    /** 信件类型：矿战 */
    private const int MESSAGE_TYPE = 3;
    /** 战报分享到聊天室的元宝花费（旧版 send_to_chat 的 $price） */
    private const int SHARE_PRICE = 2;
    /** 语言包 */
    private const string LANG_COUNTRY_NULL = '无';
    private const string LANG_MESSAGE_MINE_WIN = '攻打了您的矿，被您击败了。';
    private const string LANG_MESSAGE_MINE_LOSE = '攻打了您的矿，掠夺了你银币 %s';

    /**
     * 获得用户记录: c=mine&m=index&p=页码
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        // 页数
        $page = (int)$ctx->input('p', 0);
        $member = self::member($uid);
        if ($member === null || (int)$member['level'] < self::LEVEL) {
            // 等级不足,未开放
            $return['status'] = -1;
            return $return;
        }
        $now = time();
        $double = self::isDouble();
        // 取用户记录
        $log = self::ownMine($uid);
        // 默认记录等级
        $level = self::bracket((int)$member['level']);
        $return['level'] = $level . '~' . ($level + 9);
        if ($log !== null && !$page) {
            // 按记录查询位置，初试页面根据此页数显示
            $return['status'] = 2;
            $page = $log['page'];
        }
        if ($log !== null) {
            // 有用户记录，根据用户等级查询
            $level = self::bracket($log['level']);
        }
        if (!$page) {
            $page = 1;
        }
        // 根据页码和等级查询所有信息
        $list = self::listByPage($level, $page);
        if ($list === null) {
            // 页码错误
            $return['status'] = -2;
            return $return;
        }
        // 取该页角色
        $roleList = self::minesByPage($page, $level);
        if ($roleList !== []) {
            // 获取我的好友列表
            $friendList = FriendHandler::friendList($uid);
            $users = [];
            foreach (DB::table('member')->select('uid', 'nickname', 'gender', 'level', 'country')
                ->whereIn('uid', array_column($roleList, 'uid'))->whereNull('deleted_at')->get() as $row) {
                $users[(int)$row['uid']] = $row;
            }
            $countryList = [];
            $countryIds = array_values(array_unique(array_map(static fn(array $v): int => (int)$v['country'], $users)));
            if ($countryIds !== []) {
                foreach (DB::table('country')->select('id', 'name')->whereIn('id', $countryIds)->whereNull('deleted_at')->get() as $row) {
                    $countryList[(int)$row['id']] = (string)$row['name'];
                }
            }
            // 把对应位置的用户信息放到矿石列表中
            foreach ($list as $key => $val) {
                // 双倍活动期间，奖励翻倍
                if ($double) {
                    $list[$key]['get_silver'] *= 2;
                    $list[$key]['silver'] *= 2;
                }
                $role = $roleList[$val['id']] ?? null;
                if ($role === null) {
                    continue;
                }
                $user = $users[$role['uid']] ?? null;
                $time = self::TOTAL_TIME - ($now - $role['dateline']);
                // 该矿占领者用户信息
                $list[$key]['nickname'] = $user['nickname'] ?? null;
                $list[$key]['gender'] = isset($user['gender']) ? (int)$user['gender'] : null;
                $list[$key]['level'] = isset($user['level']) ? (int)$user['level'] : null;
                $list[$key]['uid'] = isset($user['uid']) ? (int)$user['uid'] : null;
                $country = (int)($user['country'] ?? 0);
                $list[$key]['country'] = $country > 0 ? ($countryList[$country] ?? null) : self::LANG_COUNTRY_NULL;
                $list[$key]['status'] = $role['uid'] === $uid ? 2 : 1;
                // 累计占领时间
                $list[$key]['time'] = $time < 0 ? 0 : $time;
                // 累计收益
                if ($list[$key]['time'] === 0) {
                    $list[$key]['get_silver'] = (int)ceil($list[$key]['silver']);
                } else {
                    $list[$key]['get_silver'] = (int)ceil($list[$key]['get_silver'] * floor((self::TOTAL_TIME - $list[$key]['time']) / self::TIME));
                }
                if ($list[$key]['get_silver'] < 0) {
                    $list[$key]['get_silver'] = 0;
                }
                // 判断该用户是不是自己的好友
                $list[$key]['friend'] = in_array($role['uid'], $friendList, true) ? 1 : 0;
            }
        }
        if ($page === 1) {
            // 第 1 页的 1 型矿放到中间（第 5 个）
            $first = [array_shift($list)];
            array_splice($list, 4, 0, $first);
        }
        // 首次打开操作记录 (类型2)
        Edifice::addLog($uid, Edifice::MINE);

        $return['status'] = 1;
        $return['page'] = $page + 1 > 5 ? 0 : $page + 1;
        $return['log'] = $log ?? [];
        $return['list'] = array_values($list);
        $return['dateline'] = $now;
        return $return;
    }

    /**
     * 收获: c=mine&m=get_silver&s=矿的位置
     */
    public static function get_silver(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $site = (int)$ctx->input('s', 0);
        return Member::locked($uid, static function () use ($uid, $site): array {
            $return = [];
            $now = time();
            $member = self::member($uid);
            // 取自己占领的矿信息
            $log = self::ownMine($uid);
            if ($log === null || $member === null) {
                // 还未占领一个矿，不能收获
                $return['status'] = -1;
                return $return;
            }
            // 判断矿的拥有者
            if ($site !== $log['site'] || $log['uid'] !== $uid) {
                // 这个矿不属于我，不能收获
                $return['status'] = -2;
                return $return;
            }
            // 判断是否可收获
            if ($now - $log['dateline'] < self::TOTAL_TIME) {
                // 没有到收获时间
                $return['status'] = -3;
                return $return;
            }
            // 获取需要获得的银币
            $get = self::calc($log['level'], $log['type'], true);
            $addSilver = max(0, $get['total_silver']);
            // 修改状态
            if (self::giveUp($uid, $log['id'], $now) !== 1) {
                // 修改失败
                $return['status'] = -4;
                return $return;
            }
            // 修改矿区气泡收获时间
            self::updateMineDateline($uid, $now, true);
            // 双倍活动期间，奖励翻倍
            if (self::isDouble()) {
                $addSilver *= 2;
            }
            // 加银币
            Member::addCurrency($uid, silver: $addSilver);
            // 页面需要刷新
            $status = 1;
            if (intdiv($log['level'], 10) < intdiv((int)$member['level'], 10)) {
                $status = 2;
            }
            // 成功返回
            $return['status'] = $status;
            $return['silver'] = $addSilver;
            $return['all_silver'] = (int)DB::table('member')->where('uid', $uid)->value('silver');
            return $return;
        });
    }

    /**
     * 占领（抢夺）: c=mine&m=zhanling&p=页码&id=位置&t=矿型
     */
    public static function zhanling(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $page = (int)$ctx->input('p', 0);
        $site = (int)$ctx->input('id', 0);
        return Member::locked($uid, static function () use ($uid, $page, $site): array {
            $return = ['status' => 0];
            $now = time();
            // 对页数和位置做限制，防止占错位置
            if (!self::validSite($page, $site)) {
                $return['status'] = -2;
                return $return;
            }
            $member = self::member($uid);
            // 判断军令消耗
            if ($member === null || (int)$member['act'] < 1) {
                $return['status'] = -4;
                return $return;
            }
            // 判断自己是否有已占领的矿
            if (self::ownMine($uid) !== null) {
                // 已经占领了一个矿，不能再开采其他的矿了
                $return['status'] = -1;
                return $return;
            }
            // 查询位置信息
            $level = self::bracket((int)$member['level']);
            $mineLog = self::minesByPage($page, $level)[$site] ?? null;
            // 判断是否可抢夺
            if ($mineLog === null) {
                $return['status'] = -2;
                return $return;
            }
            $touid = $mineLog['uid'];
            // 抢夺 调用算法
            $battle = ArenaHandler::battlePk($uid, $touid);
            $assess = $battle['info']['assess'] ?? null;

            $return['info'] = [];
            if ($assess !== null && $assess >= 1) {
                // 胜利 修改矿区状态（以矿主仍是对方为条件接管，类型不变，重新计时）
                $updated = DB::table('mine')->where('id', $mineLog['id'])->where('uid', $touid)->whereNull('deleted_at')
                    ->update(['uid' => $uid, 'level' => (int)$member['level'], 'dateline' => $now]);
                $return['info']['dateline'] = self::TOTAL_TIME;
                $getSilver = 0;
                if ($updated > 0) {
                    // 增加银币 调用算法取可掠夺银币
                    $silver = self::calc($mineLog['level'], $mineLog['type'], true);
                    $time = min($now - $mineLog['dateline'], self::TOTAL_TIME);
                    $getSilver = max(0, (int)($silver['silver'] * floor($time / self::TIME)));
                    // 双倍活动期间，奖励翻倍
                    if (self::isDouble()) {
                        $getSilver *= 2;
                    }
                    Member::addCurrency($uid, silver: $getSilver);
                }
                $return['info']['get_silver'] = $getSilver;
                // 发信
                $pmid = self::sendMessage($member, $touid, sprintf(self::LANG_MESSAGE_MINE_LOSE, $getSilver));
                // 修改占领时间
                self::updateMineDateline($uid, $now);
                self::updateMineDateline($touid, $now, true);
            } else {
                // 失败
                $pmid = self::sendMessage($member, $touid, self::LANG_MESSAGE_MINE_WIN);
            }

            // 查询对方职业
            $toMember = self::member($touid);
            // 消耗军令
            Member::deductCurrency($uid, act: 1);
            // 按国家id查询国家信息
            $return['info']['country'] = self::countryName((int)$member['country']);
            $after = self::member($uid);
            $return['status'] = 1;
            $return['info']['win'] = $assess;
            $return['info']['act'] = (int)$after['act'];
            $return['info']['silver'] = (int)$after['silver'];
            $return['info']['gender'] = $toMember !== null ? (int)$toMember['gender'] : null;
            $return['info']['name'] = $toMember !== null ? (string)$toMember['nickname'] : null;
            $return['battle'] = $battle['info'] ?? [];
            $return['battle']['left_role'] = [
                'name' => (string)$member['nickname'],
                'level' => (int)$member['level'],
                'gender' => (int)$member['gender'],
            ];
            $return['battle']['right_role'] = [
                'name' => $return['info']['name'],
                'level' => $toMember !== null ? (int)$toMember['level'] : null,
                'gender' => $return['info']['gender'],
            ];
            // 记录信件战报
            Message::recordPkReport($pmid, [
                'from' => (string)$member['nickname'],
                'fromlevel' => (int)$member['level'],
                'to' => $return['battle']['right_role']['name'],
                'tolevel' => $return['battle']['right_role']['level'],
                'report' => $battle['info'] ?? null,
            ]);
            $return['info']['report'] = $pmid;
            $return['share'] = ['id' => $pmid, 'type' => 'mine'];
            self::taskAction($uid, (int)$member['level']);
            return $return;
        });
    }

    /**
     * 开采: c=mine&m=caikuang&p=页码&id=位置&t=矿型
     */
    public static function caikuang(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $page = (int)$ctx->input('p', 0);
        $site = (int)$ctx->input('id', 0);
        $type = (int)$ctx->input('t', 0);
        return Member::locked($uid, static function () use ($uid, $page, $site, $type): array {
            $return = ['status' => 0];
            $now = time();
            // 对页数和位置做限制，防止占错位置
            if (!self::validSite($page, $site)) {
                $return['status'] = -2;
                return $return;
            }
            $member = self::member($uid);
            if ($member === null) {
                return $return;
            }
            // 等级
            $level = self::bracket((int)$member['level']);
            // 判断军令
            if ((int)$member['act'] < 1) {
                $return['status'] = -3;
                return $return;
            }
            // 取矿区信息
            if (self::ownMine($uid) !== null) {
                // 已经占领了一个矿，不能再开采其他的矿了
                $return['status'] = -1;
                return $return;
            }
            // 查询该矿是否已经被别人抢占了（矿型与矿区配置不符时同样按不可开采处理）
            if (isset(self::minesByPage($page, $level)[$site]) || self::siteType($page, $site) !== $type) {
                // 该矿已经被人占领了
                $return['status'] = -2;
                return $return;
            }
            // 开采（唯一约束：并发开采同一个矿时后到的插入失败）
            $inserted = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_mine` (`uid`, `page`, `site`, `type`, `level`, `dateline`) VALUES (:uid, :page, :site, :type, :level, :now)',
                ['uid' => $uid, 'page' => $page, 'site' => $site, 'type' => $type, 'level' => (int)$member['level'], 'now' => $now]
            );
            if ($inserted > 0) {
                // 按国家id查询国家信息
                $return['country'] = self::countryName((int)$member['country']);
                // 成功
                $return['status'] = 1;
                $return['dateline'] = self::TOTAL_TIME;
                // 消耗军令
                Member::deductCurrency($uid, act: 1);
                $return['act'] = (int)DB::table('member')->where('uid', $uid)->value('act');
                self::updateMineDateline($uid, $now);
            }
            // 日常任务、主线任务（旧版开采没成功也计）
            self::taskAction($uid, (int)$member['level']);
            return $return;
        });
    }

    /**
     * 放弃: c=mine&m=give_up
     */
    public static function give_up(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return Member::locked($uid, static function () use ($uid): array {
            $return = [];
            $now = time();
            // 查询矿的信息
            $log = self::ownMine($uid);
            $member = self::member($uid);
            // 判断符不符合放弃条件
            if ($log === null || $member === null) {
                // 没有用户信息，无法放弃
                $return['status'] = -1;
                return $return;
            }
            $time = $now - $log['dateline'];
            if ($time >= self::TOTAL_TIME) {
                // 该矿可以收获了，不能放弃
                $return['status'] = -2;
                return $return;
            }
            // 取可收获的银币
            $silver = self::calc($log['level'], $log['type'], true);
            $return['silver'] = max(0, (int)($silver['silver'] * floor($time / self::TIME)));
            // 双倍奖励活动期间，奖励翻倍
            if (self::isDouble()) {
                $return['silver'] *= 2;
            }
            // 执行放弃操作（先删矿，删成功再加银币）
            if (self::giveUp($uid, $log['id'], $now) !== 1) {
                $return['status'] = 0;
                return $return;
            }
            // 增加银币
            Member::addCurrency($uid, silver: $return['silver']);
            $return['all_silver'] = (int)DB::table('member')->where('uid', $uid)->value('silver');
            // 放弃成功
            $return['status'] = 1;
            if (intdiv($log['level'], 10) < intdiv((int)$member['level'], 10)) {
                $return['status'] = 2;
            }
            self::updateMineDateline($uid, $now, true);
            return $return;
        });
    }

    /**
     * 分享矿区战报: c=mine&m=send_to_chat（touid 对方 uid，report 信件 id，win 战斗评级；移植旧版 controllers/mine.php，扣 2 元宝）
     *
     * status：-1 参数不完整；-2 元宝不足；-3 对方不存在；1 成功（all_gold）
     */
    public static function send_to_chat(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $touid = (int)$ctx->input('touid', 0);
        $report = (int)$ctx->input('report', 0);
        $win = (int)$ctx->input('win', 0);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        // 判断参数是否完整
        if ($touid === 0 || $report === 0 || $member === null) {
            return ['status' => -1];
        }
        // 判断金币是否足够
        if ((int)$member['gold'] < self::SHARE_PRICE) {
            return ['status' => -2];
        }
        // 取用户信息
        $toUser = DB::table('member')->where('uid', $touid)->whereNull('deleted_at')->first();
        if ($toUser === null) {
            return ['status' => -3];
        }
        // 扣除金币
        $allGold = Chat::chargeShare($uid, self::SHARE_PRICE);
        if ($allGold === null) {
            return ['status' => -2];
        }
        $sender = Chat::sender($uid);
        $message = Chat::getMessageStr(27, [
            'to' => ['uid' => $touid, 'nickname' => $toUser['nickname']],
            'id' => $report,
            'win' => Chat::winWord($win),
        ], $sender);
        Chat::sendToAll($message, Chat::TYPE_REPORT, $report, 0, $sender);
        return ['status' => 1, 'all_gold' => $allGold];
    }

    /**
     * 对页数和位置做限制（第 1 页 9 个、其余 12 个）
     */
    private static function validSite(int $page, int $site): bool
    {
        return !(!$page || !$site || $page > 5 || $page < 0 || $site < 0 || ($page === 1 && $site > 9) || ($page > 1 && $site > 12));
    }

    /**
     * 矿区配置里该位置的矿型
     */
    private static function siteType(int $page, int $site): int
    {
        $j = 0;
        foreach (self::NUM[$page] ?? [] as $type => $num) {
            if ($site > $j && $site <= $j + $num) {
                return $type;
            }
            $j += $num;
        }
        return 0;
    }

    /**
     * 根据页码查询所有信息（get_list_buy_page：页码超过 5 为 null）
     *
     * @return list<array<string, int>>|null
     */
    private static function listByPage(int $level, int $page): ?array
    {
        if (!isset(self::NUM[$page])) {
            return null;
        }
        $list = [];
        $j = 0;
        foreach (self::NUM[$page] as $type => $num) {
            $calc = self::calc($level, self::SERIES[$type]);
            for ($i = $j; $i < $j + $num; $i++) {
                $list[$i] = [
                    'id' => $i + 1,
                    'page' => $page,
                    'type' => $type,
                    'status' => 0,
                    'silver' => $calc['total_silver'],
                    'get_silver' => $calc['silver'],
                ];
            }
            $j = $i;
        }
        return $list;
    }

    /**
     * 收益算法（calc：$byType 为 true 时 $series 是矿型，先换算成系数）
     *
     * @return array{silver: int, total_silver: int}
     */
    private static function calc(int $level, int $series, bool $byType = false): array
    {
        $level = self::bracket($level);
        if ($byType) {
            $series = self::SERIES[$series] ?? 0;
        }
        $silver = $series * $level * self::RATIO;
        $totalSilver = $silver * (self::TOTAL_TIME / self::TIME);
        return ['silver' => (int)floor($silver), 'total_silver' => (int)floor($totalSilver)];
    }

    private static function bracket(int $level): int
    {
        return intdiv($level, 10) * 10;
    }

    /**
     * 自己占领的矿（get_info()）
     *
     * @return array{id: int, uid: int, page: int, site: int, type: int, level: int, dateline: int}|null
     */
    private static function ownMine(int $uid): ?array
    {
        $row = DB::table('mine')->select('id', 'uid', 'page', 'site', 'type', 'level', 'dateline')->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * 某等级段某页的矿（get_info(0, page / level)），按位置索引
     *
     * @return array<int, array{id: int, uid: int, page: int, site: int, type: int, level: int, dateline: int}>
     */
    private static function minesByPage(int $page, int $level): array
    {
        $rows = DB::table('mine')->select('id', 'uid', 'page', 'site', 'type', 'level', 'dateline')
            ->where('page', $page)->where('level', '>=', $level)->where('level', '<', $level + 10)->whereNull('deleted_at')->get();
        $list = [];
        foreach ($rows as $row) {
            $row = array_map('intval', $row);
            $list[$row['site']] = $row;
        }
        return $list;
    }

    /**
     * 放弃 / 收获（give_up：删除自己的矿，旧版整行删除，新版软删除）
     */
    private static function giveUp(int $uid, int $id, int $now): int
    {
        $affected = DB::table('mine')->where('id', $id)->where('uid', $uid)->whereNull('deleted_at')
            ->update(['deleted_at' => date('Y-m-d H:i:s', $now)]);
        return $affected > 0 ? 1 : 0;
    }

    /**
     * 修改最后占领时间（update_mine_dateline：$harvest 为 true 时清 0）
     */
    private static function updateMineDateline(int $uid, int $now, bool $harvest = false): void
    {
        DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['onmine' => $harvest ? 0 : $now]);
    }

    /**
     * 日常任务(3是矿战对应的类型) 与 主线任务 7（占领或抢夺一次银矿，无论胜败）
     */
    private static function taskAction(int $uid, int $level): void
    {
        if ($level >= DailyTask::OPEN_LEVEL) {
            DailyTask::action($uid, DailyTask::TYPE_MINE);
        }
        MainQuestTask::action($uid, 7, 1);
    }

    private static function isDouble(): bool
    {
        return Pay::activity(self::DOUBLE_ACTIVITY, BossClock::now()) !== null;
    }

    private static function countryName(int $country): ?string
    {
        if ($country <= 0) {
            return self::LANG_COUNTRY_NULL;
        }
        $info = CountryHandler::countryInfo($country);
        return $info === null ? null : (string)$info['name'];
    }

    /**
     * 给矿主发矿战信件（message_model->send_message type 3）
     *
     * @param array<string, mixed> $member
     */
    private static function sendMessage(array $member, int $touid, string $text): int
    {
        $message = '<em uid="' . (int)$member['uid'] . '">' . $member['nickname'] . '</em>' . $text;
        return MessageHandler::sendSystemMessage([$touid], self::MESSAGE_TYPE, (string)$member['nickname'], $message, 0, (int)$member['uid']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return DB::table('member')->select('uid', 'nickname', 'gender', 'level', 'vip', 'act', 'silver', 'gold', 'country')
            ->where('uid', $uid)->whereNull('deleted_at')->first();
    }
}
