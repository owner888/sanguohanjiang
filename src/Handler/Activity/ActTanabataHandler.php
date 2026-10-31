<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\ActTanabataRankreward;
use Hanjiang\MasterData\ActTanabataReward;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FestReward;
use Hanjiang\Model\Member;
use Hanjiang\Model\OwnMount;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 七夕「土豪的爱」充值活动（移植旧版 controllers/act_tanabata.php + models/acttanabata_model.php，计划任务
 * cron_acttask.php:acttanabata_reward，充值记账 pay.php:pay_act_init 的 add_tanabata_pay_order）
 *
 * 活动时间与版本取 ActivityTime 的 acttanabata（旧版数据 2016-12-31 已结束）。活动期间（图标多挂一天，充值记账到结束前一天）
 * 每笔充值由 Model\Pay 记一条 act_tanabata_order（元宝数）；全服充值合计达到 MasterData ActTanabataReward 的档次后，
 * 活动期间充过值的玩家都可以领该档奖励（每档一次，act_tanabata_reward_log）；充值排行前 20 名（ActTanabataRankreward）
 * 在「结束时间 - 86401」之后可以领排行奖励（act_tanabata_rankreward_log）。活动结束后 10 小时内计划任务把没领的
 * 排行奖励与档次奖励自动发放并发信件（cronReward）。
 * 客户端 valentinepop.js 调用 index、reward（id）、rank_reward。当前时间取 BossClock。
 *
 * 奖励类型（get_reward）：1 元宝 2 银币 3 黄宝石 4 紫宝石 5 原魂 6 玉石 7 声望 8 装备 9 宝石碎片 10 突飞卡 11 蓝宝石
 * 12 武将 13 军令 14 精炼石 15 五彩石 16 上古血石 17 ~ 25 装备精华 26 坐骑；8 / 12 / 17 ~ 26 的 num 为 id、value 为数量，
 * 列表里换成 info + 交换 num / value。
 *
 * 照旧版：rank_reward 不看活动是否开启的判断结果（未开启时 cd 为 0、版本为 0，查不到排行回 5）；排行按充值合计降序、
 * 时间升序（旧版 GROUP BY 后按任意一行的 dateline，这里取最早一笔）。
 * 与旧版的差别：两个领取锁改为 Redis SET NX（旧版 get + setex 30 秒）；领取记录表加唯一键，记录写入成功才发奖
 * （旧版先查后写，并发可重复领）。
 */
final class ActTanabataHandler
{
    public const string ACTIVITY = 'acttanabata';

    /** 领取充值档次 lock_key（_re_key） */
    private const string RE_KEY = 'tana_reward_';
    /** 领取排行奖励 lock_key（_rank_re_key） */
    private const string RANK_RE_KEY = 'tana_rank_re_';
    private const int LOCK_TTL = 30;

    /** 排行总数（acttanabata_model->_rank_num） */
    private const int RANK_NUM = 20;

    /** 首页展示的比翼鸟坐骑（type 26，坐骑 id 103） */
    private const int MOUNT_ID = 103;

    /** acttanabata_lang.php + cron_lang.php */
    private const array LANG = [
        'tanabata_error_open' => '活动未开启',
        'tanabata_error_reward' => '奖励表错误',
        'tanabata_error_rewarded' => '已经领取该档次的奖励',
        'tanabata_error_rankreward' => '排行奖励错误',
        'tanabata_error_rankrewarded' => '已经领取排行奖励',
        'tanabata_error_notpay' => '未充值，不能领取奖励',
        'tanabata_error_param' => '传递参数错误',
        'tanabata_error_fast' => '请求不要太频繁',
        'tanabata_error_rank_time' => '活动未结束，不能领取排行奖励',
        'tanabata_error_notrankreward' => '您不在排行中，不能领取排行奖励',
        'tanabata_error_rankrewardlog' => '记录领取排行奖励日志错误',
        'tanabata_error_rewardlog' => '记录领取奖励日志错误',
        'tanabata_error_rewarderror' => '全服充值不足，不能领取该档次奖励',
        'cron_tanabata_end' => '亲爱的玩家！土豪的爱活动已结束，您还未领取的%s奖励已自动帮您领取，',
        'cron_tanabata_rank' => '排名',
        'cron_tanabata_ranknum' => '排名%s位',
        'message_system_title' => '系统通知',
    ];

    /**
     * 首页: c=act_tanabata&m=index
     *
     * 1 成功；-1 活动未开启；-2 奖励表错误；-3 排行奖励表错误
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::init($now);
        // 条件判断（奖励列表 re_list 和排行奖励信息 rankre_list）
        $judge = self::preJudge($act);
        if ($judge !== null || $uid <= 0) {
            return $judge ?? ['status' => -1, 'msg' => self::LANG['tanabata_error_open']];
        }
        $version = $act['version'];
        $return = ['status' => 0];
        // 获取总的充值元宝数量
        $return['all_gold'] = self::payCount($version);
        // 获取当前玩家的领取奖励日志信息
        $rewardLog = self::rewardLog($uid, $version);
        // 获取当前玩家的领取的排行奖励的日志信息
        $rankrewardLog = self::rankrewardLog($uid, $version);
        // 获取当前玩家充值元宝总数
        $myGold = self::payCount($version, $uid);
        // 处理奖励列表
        $return['list'] = [];
        foreach (ActTanabataReward::ROWS as $val) {
            // 是否领取该档次的奖励 0，未领取；1，可领取；2，已领取
            $val['is_re'] = 0;
            if ($return['all_gold'] >= $val['gold']) {
                if ($myGold > 0) {
                    $val['is_re'] = 1;
                }
                if (isset($rewardLog[$val['id']])) {
                    $val['is_re'] = 2;
                }
            }
            for ($i = 1; $i <= 5; $i++) {
                $val = self::showItem($val, (string)$i);
            }
            $return['list'][] = $val;
        }
        // 玩家排行列表
        $return['rank_list'] = $return['my_rank'] = [];
        foreach (self::rankList($version) as $key => $rankInfo) {
            $rank = $key + 1;
            $reward = ActTanabataRankreward::ROWS[$rank] ?? null;
            if ($reward === null) {
                continue;
            }
            $reward['uid'] = $rankInfo['uid'];
            $reward['nickname'] = $rankInfo['nickname'];
            $reward['total_gold'] = $rankInfo['total_gold'];
            $reward = self::showItem($reward, '');
            $return['rank_list'][] = $reward;
            // 当前玩家排行奖励
            if ($rankInfo['uid'] === $uid) {
                $return['my_rank'] = $reward;
                // 排行奖励状态 0，不能领取；1，可以领取；2，已领取
                $return['my_rank']['is_reward'] = $rankrewardLog !== null ? 2 : ($act['rank_cd'] <= 0 ? 1 : 0);
            }
        }
        // 活动剩余时间
        $return['cd_time'] = $act['rank_cd'];
        // 比翼鸟信息
        $return['mount'] = FestReward::mountInfo(self::MOUNT_ID, false);
        $return['status'] = 1;
        return $return;
    }

    /**
     * 领取充值档次奖励: c=act_tanabata&m=reward（id 档次 id）
     *
     * 1 成功；2 请求频繁（锁）；3 未充值；4 已经领取该档次；5 记录领取日志错误；6 全服充值不足；-1 活动未开启；
     * -2 奖励表错误 / 没有该档次；-3 排行奖励表错误；-4 参数错误
     */
    public static function reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        // 防刷接口处理
        if ($uid <= 0 || !self::lock(self::RE_KEY . $uid)) {
            return ['status' => 2, 'msg' => self::LANG['tanabata_error_fast']];
        }
        try {
            // 接收参数
            $id = (int)$ctx->input('id', 0);
            if ($id <= 0) {
                return ['status' => -4, 'msg' => self::LANG['tanabata_error_param']];
            }
            $act = self::init($now);
            $judge = self::preJudge($act);
            if ($judge !== null) {
                return $judge;
            }
            $version = $act['version'];
            // 判断是否存在该档次的奖励信息
            $reward = ActTanabataReward::ROWS[$id] ?? null;
            if ($reward === null) {
                return ['status' => -2, 'msg' => self::LANG['tanabata_error_reward']];
            }
            // 查看玩家是否有充值记录
            if (self::payCount($version, $uid) <= 0) {
                return ['status' => 3, 'msg' => self::LANG['tanabata_error_notpay']];
            }
            // 获取当前玩家的领取奖励日志信息
            if (isset(self::rewardLog($uid, $version)[$id])) {
                return ['status' => 4, 'msg' => self::LANG['tanabata_error_rewarded']];
            }
            // 获取总的充值元宝数量
            if (self::payCount($version) < $reward['gold']) {
                return ['status' => 6, 'msg' => self::LANG['tanabata_error_rewarderror']];
            }
            // 记录领取奖励记录（唯一键，写入成功才发奖）
            if (!self::addRewardLog($uid, $version, $id, $now)) {
                return ['status' => 5, 'msg' => self::LANG['tanabata_error_rewardlog']];
            }
            // 当前奖励信息
            $return = ['status' => 0, 'reward' => $reward];
            for ($i = 1; $i <= 5; $i++) {
                $type = (int)($reward['type' . $i] ?? 0);
                if ($type > 0) {
                    self::grant($uid, ['type' => $type, 'num' => (int)$reward['num' . $i], 'value' => (int)$reward['value' . $i]], $now, 'reward');
                }
            }
            $member = DB::table('member')->select('gold', 'silver')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
            // 当前玩家银币和元宝数
            $return['all_gold'] = (int)($member['gold'] ?? 0);
            $return['all_silver'] = (int)($member['silver'] ?? 0);
            $return['status'] = 1;
            return $return;
        } finally {
            Redis::del(self::RE_KEY . $uid);
        }
    }

    /**
     * 领取排行奖励: c=act_tanabata&m=rank_reward
     *
     * 1 成功；2 请求频繁（锁）；3 活动未结束；4 已经领取排行奖励；5 不在排行中；6 记录排行奖励日志错误
     */
    public static function rank_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        // 防刷接口处理
        if ($uid <= 0 || !self::lock(self::RANK_RE_KEY . $uid)) {
            return ['status' => 2, 'msg' => self::LANG['tanabata_error_fast']];
        }
        try {
            // 照旧版：条件判断的结果不用（未开启时 cd 为 0、版本为 0）
            $act = self::init($now) ?? ['version' => 0, 'rank_cd' => 0];
            $version = $act['version'];
            // 判断是否到达领取的时间
            if ($act['rank_cd'] > 0) {
                return ['status' => 3, 'msg' => self::LANG['tanabata_error_rank_time']];
            }
            // 获取当前玩家的领取的排行奖励的日志信息
            if (self::rankrewardLog($uid, $version) !== null) {
                return ['status' => 4, 'msg' => self::LANG['tanabata_error_rankrewarded']];
            }
            // 获取排行列表
            $rank = 0;
            $myReward = null;
            foreach (self::rankList($version) as $key => $rankInfo) {
                if ($rankInfo['uid'] === $uid) {
                    $rank = $key + 1;
                    $myReward = ActTanabataRankreward::ROWS[$rank] ?? null;
                    break;
                }
            }
            // 判断玩家是否在排行中
            if ($myReward === null) {
                return ['status' => 5, 'msg' => self::LANG['tanabata_error_notrankreward']];
            }
            // 记录领取排行奖励日志
            if (!self::addRankrewardLog($uid, $version, $rank, $now)) {
                return ['status' => 6, 'msg' => self::LANG['tanabata_error_rankrewardlog']];
            }
            // 向玩家发送奖励
            self::grant($uid, ['type' => $myReward['type'], 'num' => $myReward['num'], 'value' => $myReward['value']], $now, 'rank_reward');
            $member = DB::table('member')->select('gold', 'silver')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
            return ['status' => 1, 'all_gold' => (int)($member['gold'] ?? 0), 'all_silver' => (int)($member['silver'] ?? 0)];
        } finally {
            Redis::del(self::RANK_RE_KEY . $uid);
        }
    }

    /**
     * 充值记账（pay.php:pay_act_init → add_tanabata_pay_order）：活动开启且距结束超过一天（图标多挂一天）时记一笔
     */
    public static function addPayOrder(int $uid, string $nickname, int $level, int $gold, string $orderId, string $class, string $method, int $now): void
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null || (int)$info['endtime'] - 86400 <= $now) {
            return;
        }
        DB::table('act_tanabata_order')->insert([
            'uid' => $uid,
            'nickname' => Member::logText($nickname, 50),
            'level' => $level,
            'gold' => $gold,
            'version' => (int)$info['version'],
            'orderid' => $orderId,
            'class' => Member::logText($class, 20),
            'method' => Member::logText($method, 20),
            'dateline' => $now,
        ]);
    }

    /**
     * 计划任务：活动结束后发放排行奖励与充值档次奖励（cron_acttask.php:acttanabata_reward）
     *
     * 只在活动结束后 10 小时内执行（endtime <= 当前 <= endtime + 36000）。先发排行奖励（没领的写领取记录、发奖、发信件），
     * 再发档次奖励：全服充值达到的档次，给每个充过值的玩家补发没领的档次，同类资源合并到一封信件。
     * 照旧版：合并档次奖励时装备 / 武将 / 精华（8 / 12 / 17 ~ 25）存成列表，只有坐骑 26 逐个发放，其余列表旧版传给发奖函数时
     * 取不到类型，不发也不写进信件（奖励表里没有这些类型）。
     *
     * @return int -1 无活动；-2 活动未结束；-3 结束超过 10 小时；1 已执行
     */
    public static function cronReward(int $now): int
    {
        $activity = ActivityClock::byName(self::ACTIVITY);
        if ($activity === null) {
            return -1;
        }
        // 判断活动是否结束
        if ($activity['endtime'] > $now) {
            return -2;
        }
        // 判断是否可发放奖励（活动已结束，并且在活动结束后 10 小时内）
        if ($activity['endtime'] + 36000 < $now) {
            return -3;
        }
        $version = (int)$activity['version'];
        self::cronRankReward($version, $now);
        self::cronTierReward($version, $now);
        return 1;
    }

    /**
     * 发放充值排行奖励（action_tanabata_rank_reward）
     */
    private static function cronRankReward(int $version, int $now): void
    {
        if (ActTanabataRankreward::ROWS === []) {
            return;
        }
        foreach (self::rankList($version) as $key => $val) {
            $rank = $key + 1;
            $reward = ActTanabataRankreward::ROWS[$rank] ?? null;
            // 添加领取排行奖励日志（已领过的写不进）
            if ($reward === null || !self::addRankrewardLog($val['uid'], $version, $rank, $now)) {
                continue;
            }
            $msg = sprintf(self::LANG['cron_tanabata_end'], self::LANG['cron_tanabata_rank']);
            $msg .= sprintf(self::LANG['cron_tanabata_ranknum'], $rank);
            $msg .= FestReward::LANG_CRON['cron_get_reward'];
            $msg .= FestReward::cronGrant($val['uid'], $reward, false, 'cron_acttask', 'acttanabata_reward', $now);
            $msg .= FestReward::LANG_CRON['cron_check'];
            MessageHandler::sendSystemMessage([$val['uid']], 1, self::LANG['message_system_title'], $msg);
        }
    }

    /**
     * 发放充值档次奖励（action_tanabata_reward）
     */
    private static function cronTierReward(int $version, int $now): void
    {
        // 获取总的充值元宝数量，得到可以领取奖励的档次 ID
        $allGold = self::payCount($version);
        $reIds = [];
        foreach (ActTanabataReward::ROWS as $id => $val) {
            if ($allGold >= $val['gold']) {
                $reIds[] = $id;
            }
        }
        if ($reIds === []) {
            return;
        }
        // 获取所有充值玩家
        $users = array_map(
            static fn (array $r): int => (int)$r['uid'],
            DB::query('SELECT `uid` FROM `zc_sanguo_act_tanabata_order` WHERE `version` = :version AND `deleted_at` IS NULL GROUP BY `uid` ORDER BY MIN(`id`)', ['version' => $version])
        );
        foreach ($users as $uid) {
            $addReInfo = [];
            foreach ($reIds as $reId) {
                // 记录领取奖励记录（已领过的写不进，跳过）
                if (!self::addRewardLog($uid, $version, $reId, $now)) {
                    continue;
                }
                $reInfo = ActTanabataReward::ROWS[$reId];
                for ($i = 1; $i <= 5; $i++) {
                    $type = (int)($reInfo['type' . $i] ?? 0);
                    if ($type <= 0) {
                        continue;
                    }
                    $info = ['type' => $type, 'num' => (int)$reInfo['num' . $i], 'value' => (int)$reInfo['value' . $i]];
                    if (FestReward::isItemType($type)) {
                        $addReInfo[$type][] = $info;
                    } elseif (isset($addReInfo[$type])) {
                        $addReInfo[$type]['num'] += $info['num'];
                    } else {
                        $addReInfo[$type] = $info;
                    }
                }
            }
            if ($addReInfo === []) {
                continue;
            }
            $msg = sprintf(self::LANG['cron_tanabata_end'], '') . FestReward::LANG_CRON['cron_get_reward'];
            foreach ($addReInfo as $type => $reward) {
                if ($type === 26) {
                    foreach ($reward as $v) {
                        $msg .= FestReward::cronGrant($uid, $v, false, 'cron_acttask', 'acttanabata_reward', $now);
                    }
                } elseif (!FestReward::isItemType($type)) {
                    $msg .= FestReward::cronGrant($uid, $reward, false, 'cron_acttask', 'acttanabata_reward', $now);
                }
            }
            $msg .= FestReward::LANG_CRON['cron_check'];
            MessageHandler::sendSystemMessage([$uid], 1, self::LANG['message_system_title'], $msg);
        }
    }

    /**
     * 向玩家发奖（get_reward，$is_get = 0）
     *
     * @param array{type: int, num: int, value: int} $info
     * @return array<string, mixed> 装备 / 武将 / 精华 / 坐骑的 info
     */
    private static function grant(int $uid, array $info, int $now, string $method): array
    {
        $type = $info['type'];
        $num = $info['num'];
        $id = 0;
        if (FestReward::isItemType($type)) {
            $id = $info['num'];
            $num = $info['value'];
        }
        switch ($type) {
            case 1: // 元宝
                Member::addCurrency($uid, gold: $num);
                return [];
            case 8: // 装备
                $item = FestReward::equipInfo($id);
                if ($item === null) {
                    return [];
                }
                Equipment::addFromTemplate($uid, $id);
                return ['info' => $item];
            case 12: // 武将（奖励表里没有用到）
                $general = FestReward::grantGeneral($uid, $id, 'act_tanabata', $method, $now);
                return ['info' => $general['general'] ?? []];
            case 13: // 军令（add_act(1, value)）
                Member::addCurrency($uid, act: $info['value']);
                return [];
            case 17: case 18: case 19: case 20: case 21: case 22: case 23: case 24: case 25: // 装备精华
                $ess = FestReward::essenceInfo($id);
                if ($ess !== null) {
                    Equipment::addTowerItem($uid, $id, $num);
                }
                return ['info' => ($ess ?? []) + ['num' => $num]];
            case 26: // 坐骑
                OwnMount::addMount($uid, $id, 0, $num, 'act_tanabata', $method, $now);
                return ['info' => FestReward::mountInfo($id, false)];
        }
        // 七夕没有玫瑰 / 钻石（27 / 28）
        if ($type < 27) {
            FestReward::grantBasic($uid, $type, $num, 'act_tanabata', $method, $now);
        }
        return [];
    }

    /**
     * 列表里的装备 / 武将 / 精华 / 坐骑：加 info{$suffix}（get_reward($info, 1)['info']）并交换 num / value
     *
     * @param array<string, mixed> $val
     * @return array<string, mixed>
     */
    private static function showItem(array $val, string $suffix): array
    {
        $type = (int)($val['type' . $suffix] ?? 0);
        if ($type <= 0 || !FestReward::isItemType($type)) {
            return $val;
        }
        $id = (int)$val['num' . $suffix];
        $num = (int)$val['value' . $suffix];
        $val['info' . $suffix] = match (true) {
            $type === 8 => FestReward::equipInfo($id),
            $type === 12 => FestReward::generalInfo($id),
            $type === 26 => FestReward::mountInfo($id, false),
            default => (FestReward::essenceInfo($id) ?? []) + ['num' => $num],
        };
        $val['num' . $suffix] = $num;
        $val['value' . $suffix] = $id;
        return $val;
    }

    /**
     * 活动时间（_init）：activity_status 开启时返回版本与排行领奖 cd（「endtime - 86401」- 当前，最小 0）；
     * starttime <= 当前 < endtime 才算开启（open），否则 null
     *
     * @return array{version: int, rank_cd: int, open: bool}|null
     */
    private static function init(int $now): ?array
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null) {
            return null;
        }
        // 活动结束后图标要多挂一天，所以活动会提前一天结束
        $end = (int)$info['endtime'] - 86401;
        return [
            'version' => (int)$info['version'],
            'rank_cd' => max(0, $end - $now),
            'open' => $now >= (int)$info['starttime'] && $now < (int)$info['endtime'],
        ];
    }

    /**
     * 条件判断（pre_judge）：活动未开启 -1、奖励表错误 -2、排行奖励表错误 -3；通过返回 null
     *
     * @param array{version: int, rank_cd: int, open: bool}|null $act
     * @return array<string, mixed>|null
     */
    private static function preJudge(?array $act): ?array
    {
        if ($act === null || !$act['open']) {
            return ['status' => -1, 'msg' => self::LANG['tanabata_error_open']];
        }
        if (ActTanabataReward::ROWS === []) {
            return ['status' => -2, 'msg' => self::LANG['tanabata_error_reward']];
        }
        if (ActTanabataRankreward::ROWS === []) {
            return ['status' => -3, 'msg' => self::LANG['tanabata_error_rankreward']];
        }
        return null;
    }

    private static function lock(string $key): bool
    {
        return (bool)Redis::instance()->set($key, 1, ['nx', 'ex' => self::LOCK_TTL]);
    }

    /**
     * 充值元宝合计（get_pay_count：$uid 为 0 时全服）
     */
    private static function payCount(int $version, int $uid = 0): int
    {
        $query = DB::table('act_tanabata_order')->where('version', $version)->whereNull('deleted_at');
        if ($uid > 0) {
            $query->where('uid', $uid);
        }
        return (int)$query->sum('gold');
    }

    /**
     * 充值排行（get_rank_list：按玩家合计降序，同额按时间升序，前 20 名）
     *
     * @return list<array{uid: int, nickname: string, total_gold: int}>
     */
    private static function rankList(int $version): array
    {
        $rows = DB::query(
            'SELECT `uid`, MAX(`nickname`) AS `nickname`, SUM(`gold`) AS `total_gold` FROM `zc_sanguo_act_tanabata_order`
             WHERE `version` = :version AND `deleted_at` IS NULL GROUP BY `uid` ORDER BY `total_gold` DESC, MIN(`dateline`) ASC, MIN(`id`) ASC
             LIMIT ' . self::RANK_NUM,
            ['version' => $version]
        );
        return array_map(static fn (array $r): array => [
            'uid' => (int)$r['uid'],
            'nickname' => (string)$r['nickname'],
            'total_gold' => (int)$r['total_gold'],
        ], $rows);
    }

    /**
     * 玩家领取档次奖励的记录（get_reward_log，按 re_id 索引）
     *
     * @return array<int, true>
     */
    private static function rewardLog(int $uid, int $version): array
    {
        $return = [];
        foreach (DB::table('act_tanabata_reward_log')->select('re_id')->where('version', $version)->where('uid', $uid)->whereNull('deleted_at')->get() as $row) {
            $return[(int)$row['re_id']] = true;
        }
        return $return;
    }

    private static function addRewardLog(int $uid, int $version, int $id, int $now): bool
    {
        return DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_act_tanabata_reward_log` (`uid`, `version`, `re_id`, `dateline`) VALUES (:uid, :version, :id, :now)',
            ['uid' => $uid, 'version' => $version, 'id' => $id, 'now' => $now]
        ) > 0;
    }

    /**
     * 玩家领取排行奖励的记录（get_rankreward_log）；没有为 null
     */
    private static function rankrewardLog(int $uid, int $version): ?int
    {
        $rank = DB::table('act_tanabata_rankreward_log')->where('version', $version)->where('uid', $uid)->whereNull('deleted_at')->value('rank');
        return $rank === null ? null : (int)$rank;
    }

    private static function addRankrewardLog(int $uid, int $version, int $rank, int $now): bool
    {
        return DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_act_tanabata_rankreward_log` (`uid`, `version`, `rank`, `dateline`) VALUES (:uid, :version, :rank, :now)',
            ['uid' => $uid, 'version' => $version, 'rank' => $rank, 'now' => $now]
        ) > 0;
    }
}
