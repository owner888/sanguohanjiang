<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\ActivityTime;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\EquipEssence;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\LoginrewardReward;
use Hanjiang\MasterData\LoginrewardRewardIndex;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\MasterData\Pub;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Talent;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberIntegral;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\MemberStone;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\PubLog;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 登陆返礼活动（移植旧版 controllers/loginreward.php + models/loginreward_model.php）
 *
 * 活动时间与版本取 ActivityTime 的 login_reward（旧版数据中未开启，接口回 -1）。总登录天数按每日计划任务日志
 * member_log（Member::dailyCron 写入）统计：旧版取活动开始时间以前（dateline <= starttime）的不同日期数 + 1，
 * 没有记录时注册时间不晚于活动开始才算 1 天，照旧版保留。奖励：总奖励（type 1，MasterData LoginrewardRewardIndex，数量 × 总天数）
 * 与达成天数奖励（type 2，MasterData LoginrewardReward），领取记 loginreward_reward_log（唯一键 uid + type + reward_id，
 * 照旧版不含版本号）。客户端 loginrewardpop.js 调用 index、action_reward（type、reward_id）。
 * 当前时间取 BossClock（生产恒为 time()）。
 *
 * 旧版照搬的问题：奖励类型 1（元宝）漏了 break，同时按同样数量加银币；类型 13（军令）按 value 而不是 num 加；
 * index 里装备 / 武将 / 坐骑 / 精华的 info 取不到（旧版 is_get = 1 时不查询，记 null）。
 * 计划任务 cron_send_loginreward / cron_acttask 的登录返礼补发是运维接口（口令 / 写死时间），不移植。
 * 与旧版的差别：领奖锁改为 Redis SET NX（旧版 get + setex）。
 */
final class LoginrewardHandler
{
    public const string ACTIVITY = 'login_reward';

    private const string LOCK_KEY = '_loginreward_lock';
    private const int LOCK_TTL = 30;

    /** 有展示信息的奖励类型（装备 8、武将 12、坐骑 26、精华 17 ~ 25） */
    private const array ITEM_TYPES = [8, 12, 26];

    private const array LANG = [
        'active_not_open' => '活动未开启！',
        'loginreward_reward_index_error' => '登录返礼奖励系数表错误',
        'loginreward_reward_error' => '获取登录返礼奖励表错误',
        'get_reward_error' => '该奖励已经领取',
        'get_post_error' => '参数错误',
        'reward_error' => '获取奖励信息失败',
        'day_error' => '登陆天数不足',
        'add_log_error' => '插入领取日志表失败',
        'fast_error' => '操作太快了',
    ];

    /**
     * 初始化: c=loginreward&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         * -3 获取累计登陆档次表错误
         * -2 累计登陆天数奖励表错误
         * -1 活动未开启
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $activity = self::openActivity(BossClock::now());
        $member = $uid > 0 ? DB::table('member')->select('uid', 'jointime')->where('uid', $uid)->whereNull('deleted_at')->first() : null;
        if ($activity === null || $member === null) {
            // 活动未开启
            $return['status'] = -1;
            $return['msg'] = self::LANG['active_not_open'];
            return $return;
        }
        // 获取登录返礼奖励系数表
        $rewardIndex = array_values(LoginrewardRewardIndex::ROWS);
        if ($rewardIndex === []) {
            $return['status'] = -2;
            $return['msg'] = self::LANG['loginreward_reward_index_error'];
            return $return;
        }
        // 获取登录返礼奖励表
        $reward = array_values(LoginrewardReward::ROWS);
        if ($reward === []) {
            $return['status'] = -3;
            $return['msg'] = self::LANG['loginreward_reward_error'];
            return $return;
        }
        // 首次登陆时间
        $return['join_time'] = date('Y-m-d', (int)$member['jointime']);
        // 总登录天数
        $totalCount = self::totalCount($uid, (int)$member['jointime'], (int)$activity['starttime']);
        $version = (int)$activity['version'];
        // 解析奖励数据
        $indexReward = self::rateReward($uid, $version, $rewardIndex, 1, $totalCount, 1);
        $return['day'] = $totalCount;
        $return['reward_index'] = $indexReward[0];
        $return['reward'] = self::rateReward($uid, $version, $reward, 2, $totalCount, 1);
        $return['status'] = 1;
        $return['time'] = date('Y.m.d', (int)$activity['starttime']) . '~' . date('Y.m.d', (int)$activity['endtime']);
        return $return;
    }

    /**
     * 领取奖励: c=loginreward&m=action_reward（type 1 总奖励 / 2 达成天数奖励，reward_id 奖励 id）
     */
    public static function action_reward(Context $ctx): array
    {
        /**
         * -7 插入领取日志表失败
         * -6 已经领取奖励
         * -5 操作太快了
         * -4 登陆天数不足
         * -3 获取奖励信息失败
         * -2 参数错误
         * -1 活动未开启
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        if ($uid <= 0) {
            return $return;
        }
        // 防止刷接口
        $redis = Redis::instance();
        if ($redis === null || !(bool)$redis->set(self::LOCK_KEY . $uid, '1', ['nx', 'ex' => self::LOCK_TTL])) {
            $return['status'] = -5;
            $return['msg'] = self::LANG['fast_error'];
            return $return;
        }
        try {
            return self::actionReward($uid, (int)$ctx->input('type', 0), (int)$ctx->input('reward_id', 0), $return);
        } finally {
            $redis->del(self::LOCK_KEY . $uid);
        }
    }

    /**
     * @param array<string, mixed> $return
     * @return array<string, mixed>
     */
    private static function actionReward(int $uid, int $type, int $rewardId, array $return): array
    {
        $now = BossClock::now();
        $activity = self::openActivity($now);
        if ($activity === null) {
            $return['status'] = -1;
            $return['msg'] = self::LANG['active_not_open'];
            return $return;
        }
        if (!in_array($type, [1, 2], true) || $rewardId <= 0) {
            $return['status'] = -2;
            $return['msg'] = self::LANG['get_post_error'];
            return $return;
        }
        $row = $type === 1 ? (LoginrewardRewardIndex::ROWS[$rewardId] ?? null) : (LoginrewardReward::ROWS[$rewardId] ?? null);
        if ($row === null) {
            $return['status'] = -3;
            $return['msg'] = self::LANG['reward_error'];
            return $return;
        }
        $rewardInfo = [$row];
        $member = DB::table('member')->select('jointime')->where('uid', $uid)->whereNull('deleted_at')->first();
        // 总登录天数
        $totalCount = self::totalCount($uid, (int)($member['jointime'] ?? 0), (int)$activity['starttime']);
        if ($totalCount <= 0 || ($type === 2 && $totalCount < (int)$row['day'])) {
            $return['status'] = -4;
            $return['msg'] = self::LANG['day_error'];
            return $return;
        }
        $version = (int)$activity['version'];
        // 判断是否领取了奖励
        if (self::rewardLogged($uid, $version, $type, $rewardId)) {
            $return['status'] = -6;
            $return['msg'] = self::LANG['get_reward_error'];
            return $return;
        }
        // 添加log（唯一键 uid + type + reward_id，照旧版不含版本号：换版本后同一奖励记不上，回 -7）
        $added = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_loginreward_reward_log` (`uid`, `version`, `type`, `reward_id`, `dateline`) VALUES (:uid, :version, :type, :rid, :now)',
            ['uid' => $uid, 'version' => $version, 'type' => $type, 'rid' => $rewardId, 'now' => $now]
        );
        if ($added === 0) {
            $return['status'] = -7;
            $return['msg'] = self::LANG['add_log_error'];
            return $return;
        }
        // 发送奖励
        $reward = self::rateReward($uid, $version, $rewardInfo, $type, $totalCount);
        unset($reward[0]['is_re']);
        $balances = DB::table('member')->select('silver', 'gold')->where('uid', $uid)->first();
        $return['status'] = 1;
        $return['reward'] = $reward[0];
        $return['silver'] = (int)$balances['silver'];
        $return['gold'] = (int)$balances['gold'];
        return $return;
    }

    /**
     * 奖励信息处理（rate_reward：$isGet 为 0 时发放奖励，否则只标领取状态 is_re：0 未达成、1 可领取、2 已领取）
     *
     * @param list<array<string, int>> $reward
     * @return list<array<string, mixed>>
     */
    private static function rateReward(int $uid, int $version, array $reward, int $type, int $day, int $isGet = 0): array
    {
        $list = [];
        foreach ($reward as $val) {
            if ($type === 1) {
                for ($i = 1; $i <= 5; $i++) {
                    if ((int)($val['type' . $i] ?? 0) <= 0) {
                        continue;
                    }
                    $t = (int)$val['type' . $i];
                    $val['num' . $i] = (int)$val['num' . $i] * $day;
                    $rewardInfo = ['type' => $t, 'num' => $val['num' . $i], 'value' => (int)$val['value' . $i]];
                    $reInfo = $isGet === 0 ? self::getReward($uid, $rewardInfo) : [];
                    if (self::isItem($t)) {
                        $val['info' . $i] = $reInfo['info'] ?? null;
                        unset($val['value' . $i]);
                    }
                }
            } elseif ((int)($val['type'] ?? 0) > 0) {
                $t = (int)$val['type'];
                $rewardInfo = ['type' => $t, 'num' => (int)$val['num'], 'value' => (int)$val['value']];
                $reInfo = $isGet === 0 ? self::getReward($uid, $rewardInfo) : [];
                if (self::isItem($t)) {
                    $val['info'] = $reInfo['info'] ?? null;
                    unset($val['value']);
                }
            }
            if ($isGet > 0) {
                $val['is_re'] = 0;
                if ($type === 1 && $day > 0) {
                    $val['is_re'] = 1;
                }
                if ($type === 2 && $day >= (int)$val['day']) {
                    $val['is_re'] = 1;
                }
                if (self::rewardLogged($uid, $version, $type, (int)$val['id'])) {
                    $val['is_re'] = 2;
                }
            }
            $list[] = $val;
        }
        return $list;
    }

    /**
     * 添加玩家奖励（get_reward，$is_get = 0）
     *
     * 奖励类型 1 元宝（漏 break，同时加同样数量的银币）2 银币 3 黄宝石 4 紫宝石 5 原魂 6 玉石 7 声望 8 装备 9 宝石碎片 10 突飞卡
     * 11 蓝宝石 12 武将 13 军令（按 value）14 精炼石 15 五彩石 16 上古血玉 17 ~ 25 装备精华 26 坐骑 27 玫瑰 28 钻石；
     * 装备 / 武将 / 精华 / 坐骑的 num 是 id，数量取 value
     *
     * @param array{type: int, num: int, value: int} $rewardInfo
     * @return array<string, mixed>
     */
    private static function getReward(int $uid, array $rewardInfo): array
    {
        $return = [];
        $type = $rewardInfo['type'];
        $num = $rewardInfo['num'];
        $id = 0;
        if (self::isItem($type)) {
            $id = $rewardInfo['num'];
            $num = $rewardInfo['value'];
        }
        switch ($type) {
            case 1: // 元宝（旧版漏了 break，继续执行银币）
                Member::addCurrency($uid, gold: $num);
                // no break
            case 2: // 银币
                Member::addCurrency($uid, silver: $num);
                break;
            case 3: // 黄宝石
                TavernHandler::addJade($uid, 2, $num);
                break;
            case 4: // 紫宝石
                TavernHandler::addJade($uid, 3, $num);
                break;
            case 5: // 原魂
                OwnSoul::addSoul($uid, $num);
                break;
            case 6: // 玉石
                MemberJade::add($uid, $num);
                break;
            case 7: // 声望
                Member::addReputation($uid, $num);
                break;
            case 8: // 装备
                $item = Equip::ROWS[$id] ?? null;
                if ($item !== null) {
                    Equipment::addFromTemplate($uid, $id);
                    $return['info'] = $item;
                }
                break;
            case 9: // 宝石碎片
                OwnGem::addChip($uid, $num);
                break;
            case 10: // 突飞卡
                FreeLeap::add($uid, $num);
                break;
            case 11: // 蓝宝石
                TavernHandler::addJade($uid, 1, $num);
                break;
            case 12: // 武将
                $owned = DB::table('general')->where('uid', $uid)->where('generalid', $id)->whereNull('deleted_at')->first();
                if ($owned !== null) {
                    // 已拥有：按酒馆紫宝石价格的 8 折补紫宝石
                    $pub = Pub::ROWS[$id] ?? null;
                    if ($pub !== null) {
                        TavernHandler::addJade($uid, 3, (int)((int)$pub['price'] * 0.8));
                    }
                    $return['info'] = ['status' => 1, 'info' => $owned];
                } else {
                    $general = General::addGeneral($uid, $id);
                    if ($general !== null) {
                        $return['info'] = $general;
                        // 添加酒馆日志(武将id, 拥有状态, 获取方式)
                        PubLog::add($uid, $id, 1, 11);
                    }
                }
                $talentid = (int)(GeneralTemplate::ROWS[$id]['talentid'] ?? 0);
                if (isset($return['info']) && $talentid > 0) {
                    $skillid = (int)GeneralTemplate::ROWS[$id]['skillid'];
                    $return['info']['talent'] = ['name' => Talent::ROWS[$talentid]['name'] ?? null, 'description' => Talent::ROWS[$talentid]['description'] ?? null];
                    $return['info']['skill'] = ['name' => Skill::ROWS[$skillid]['name'] ?? null, 'description' => Skill::ROWS[$skillid]['description'] ?? null];
                }
                break;
            case 13: // 军令（旧版按 value 加）
                Member::addCurrency($uid, act: $rewardInfo['value']);
                break;
            case 14: // 精炼石
            case 15: // 五彩石
            case 16: // 上古血石
                MemberStone::add($uid, $num, $type - 13);
                break;
            case 17: case 18: case 19: case 20: case 21: case 22: case 23: case 24: case 25: // 装备精华（id 为精华 id）
                $ess = EquipEssence::ROWS[$id] ?? null;
                if ($ess !== null) {
                    Equipment::addTowerItem($uid, $id, $num);
                    unset($ess['desc']);
                }
                $return['info'] = $ess;
                if ($ess !== null) {
                    $return['info']['num'] = $num;
                }
                break;
            case 26: // 坐骑
                $mount = MountTemp::ROWS[$id] ?? [];
                OwnMount::addMount($uid, $id, 0, $num);
                // 坐骑特殊属性
                if ((int)($mount['type'] ?? 0) > 0 && (int)($mount['value'] ?? 0) > 0) {
                    $mount['special_type'] = $mount['type'];
                    $mount['special_value'] = $mount['value'];
                }
                $return['info'] = $mount;
                break;
            case 27: // 玫瑰花
                MemberIntegral::change($uid, $num, 'beauty_rose', 1, 'loginreward', 'action_reward');
                break;
            case 28: // 钻石
                MemberIntegral::change($uid, $num, 'beauty_diamond', 1, 'loginreward', 'action_reward');
                break;
        }
        return $return;
    }

    /**
     * 总登录天数（get_user_login_info：活动开始时间以前 member_log 的不同日期数 + 1；没有记录时注册不晚于活动开始算 1 天）
     */
    private static function totalCount(int $uid, int $jointime, int $start): int
    {
        $count = (int)(DB::query(
            'SELECT COUNT(DISTINCT `ymd`) AS `n` FROM `zc_sanguo_member_log` WHERE `uid` = :uid AND `dateline` <= :start AND `deleted_at` IS NULL',
            ['uid' => $uid, 'start' => $start]
        )[0]['n'] ?? 0);
        if ($count === 0) {
            // 注册时间是否小于活动开始时间（玩家注册的第一天 member_log 没有记录，所以总天数要加 1）
            return $jointime <= $start ? 1 : 0;
        }
        return $count + 1;
    }

    private static function rewardLogged(int $uid, int $version, int $type, int $id): bool
    {
        return DB::table('loginreward_reward_log')->where('uid', $uid)->where('version', $version)->where('type', $type)
            ->where('reward_id', $id)->whereNull('deleted_at')->value('id') !== null;
    }

    private static function isItem(int $type): bool
    {
        return in_array($type, self::ITEM_TYPES, true) || ($type >= 17 && $type <= 25);
    }

    /**
     * @return array<string, int|string>|null
     */
    private static function openActivity(int $now): ?array
    {
        foreach (ActivityTime::ROWS as $row) {
            if ($row['name'] === self::ACTIVITY) {
                return $row['starttime'] <= $now && $row['endtime'] >= $now ? $row : null;
            }
        }
        return null;
    }
}
