<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\HalloweenCandy;
use Hanjiang\MasterData\HalloweenPumpkin;
use Hanjiang\MasterData\HalloweenShop;
use Hanjiang\MasterData\Soul;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FestReward;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberIntegral;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\PubLog;
use Hanjiang\Model\TraderLog;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 万圣节（移植旧版 controllers/act_halloween.php + models/acthalloween_model.php，计划任务 cron_acttask.php:get_helloween_reward）
 *
 * 活动时间与版本取 ActivityTime 的 halloween（旧版数据 2016-12-31 已结束）。糖果 / 锤子 / 奖章存 member_integral 的
 * candy / hammer / medal（Model\MemberIntegral）。吃糖果按 MasterData HalloweenCandy 抽一个奖励；锤子（35 元宝一个）砸南瓜
 * 按 HalloweenPumpkin 抽一个奖励，9 个南瓜都砸开后重置（halloween 表 id_1 ~ id_9、轮数 times）；9 个都没砸时可花 300 元宝
 * 一次砸九个（同类奖励合并）。奖章在奖章商城（HalloweenShop）兑换奖励。活动结束后 1 小时内计划任务把玩家剩余的奖章按每个
 * 2 万银币自动兑换并发信件（cronReward）。
 * 客户端 halloweenpop.js 调用 index、action_candy、medal_index、action_medal（id）、action_pumpkin（id）、action_all_pumpkin、
 * buy_hammer。当前时间取 BossClock。
 *
 * 奖励类型（get_reward）：同七夕的 1 ~ 28（8 / 12 / 17 ~ 26 的 value 为 id、value1 为数量，0 按 1），另有 29 锤子 30 奖章 31 将魂
 * （value 为将魂 id，按 1 级发放）。返回 type / value（物品类的 value 为数量）与 info。
 * 照旧版：元宝（类型 1）没有 break，会同时加同样数量的银币；奖品随机（rand_arr）按 rate × 10 划分区间，区间下界本身不中，
 * 九连砸时同一种物品（value1 为 0）只发 1 个；奖章商城列表里 value1 为 0 的物品把 value 显示成 1；九连砸元宝不足回 -6。
 * 糖果的来源（旧版聊天室发言 add_send_message_log）未移植：聊天室不移植。
 * 与旧版的差别：各操作锁改为 Redis SET NX（旧版 get + setex 30 秒）；糖果 / 锤子 / 奖章用条件更新扣减，扣成功才发奖；
 * 南瓜状态用条件更新（id_N = 0），并发同一个南瓜只砸开一次；九连砸在玩家行锁事务里扣元宝、发奖、重置；随机没抽中奖励时
 * （旧版会把空奖励当成奖品、照扣糖果 / 锤子）回「获取奖励信息失败」不扣；武将奖励（奖章商城里没有）展示时不再按已拥有发紫宝石。
 */
final class ActHalloweenHandler
{
    public const string ACTIVITY = 'halloween';

    /** 购买锤子价格 */
    private const int BUY_HAMMER_PRICE = 35;
    /** 砸九次的价格 */
    private const int ALL_PRICE = 300;
    private const int ALL_TIMES = 9;

    /** 操作锁（旧版 key 后接 uid，30 秒） */
    private const string OPEN_CANDY_KEY = '_open_candy_keys';
    private const string BUY_HAMMER_KEY = '_buy_hammer_keys';
    private const string ACTION_HAMMER_KEY = '_action_hammer_keys';
    private const string ACTION_HAMMER_NINE_KEY = '_action_hammer_nine_keys';
    private const string ACTION_MEDAL_KEY = 'action_medal_keys';
    private const int LOCK_TTL = 30;

    /** 计划任务：每个奖章兑换的银币 */
    private const int MEDAL_SILVER = 20000;

    /** acthalloween_lang.php + cron_lang.php */
    private const array LANG = [
        'activity_not_open' => '活动未开启',
        'info_error' => '获取个人信息失败',
        'fast_error' => '操作太快了',
        'candy_error' => '糖果不足',
        'candy_reward_error' => '获取奖励信息失败',
        'gold_error' => '元宝不足',
        'param_error' => '参数错误',
        'halloween_error' => '该南瓜已开启',
        'hammer_error' => '锤子不足',
        'medal_error' => '奖章不足',
        'cron_halloweenfinish_get1' => '万圣节活动已结束，您未兑换的奖章，系统已自动兑换，',
        'cron_halloweenfinish_get2' => '兑换',
        'cron_silver' => '银币',
        'cron_ge' => '个，',
        'cron_check' => '请查收!',
        'message_system_title' => '系统通知',
    ];

    /**
     * 首页: c=act_halloween&m=index
     *
     * 1 成功；-1 活动未开启；-2 获取信息失败
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::init($now);
        if ($act === null || $uid <= 0) {
            return ['status' => -1, 'msg' => self::LANG['activity_not_open']];
        }
        $user = MemberIntegral::get($uid);
        // 获取个人信息
        $info = self::halloweenInfo($uid, $act['version']);
        $list = [];
        $num = 0;
        for ($i = 0; $i <= 8; $i++) {
            $list[$i] = ['id' => $i + 1, 'status' => $info['id_' . ($i + 1)]];
            if ($list[$i]['status'] === 0) {
                $num++;
            }
        }
        return [
            'status' => 1,
            'candy' => (int)$user['candy'],
            'hammer' => (int)$user['hammer'],
            'list' => $list,
            'buy_hammer_price' => self::BUY_HAMMER_PRICE,
            'all_price' => self::ALL_PRICE,
            // 砸九次的状态
            'all_button_status' => $num === self::ALL_TIMES ? 1 : 0,
            'date' => $act['date'],
        ];
    }

    /**
     * 吃糖果: c=act_halloween&m=action_candy
     *
     * 1 成功；-1 活动未开启；-2 操作太快了；-3 获取个人信息失败；-4 糖果不足；-5 获取奖励信息失败
     */
    public static function action_candy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        if ($uid <= 0 || !self::lock(self::OPEN_CANDY_KEY . $uid)) {
            return ['status' => -2, 'msg' => self::LANG['fast_error']];
        }
        try {
            $act = self::init($now);
            if ($act === null) {
                return ['status' => -1, 'msg' => self::LANG['activity_not_open']];
            }
            $candy = (int)MemberIntegral::get($uid)['candy'];
            if ($candy <= 0) {
                return ['status' => -4, 'msg' => self::LANG['candy_error']];
            }
            // 随机获取奖励
            $picked = self::randArr(array_values(HalloweenCandy::ROWS), 1);
            if ($picked === []) {
                return ['status' => -5, 'msg' => self::LANG['candy_reward_error']];
            }
            // 减糖果（余额不足时不扣）
            if (MemberIntegral::change($uid, 1, 'candy', 2, 'act_halloween', 'action_candy', $now) === []) {
                return ['status' => -4, 'msg' => self::LANG['candy_error']];
            }
            // 发奖
            $rewardInfo = self::getReward($uid, $picked[0], 0, 'action_candy', $now);
            // 添加日志信息
            DB::table('halloween_candy_log')->insert([
                'uid' => $uid,
                'version' => $act['version'],
                'type' => $rewardInfo['type'],
                'value' => $rewardInfo['value'],
                'dateline' => $now,
            ]);
            return ['status' => 1, 'candy' => $candy - 1, 'reward' => $rewardInfo];
        } finally {
            Redis::del(self::OPEN_CANDY_KEY . $uid);
        }
    }

    /**
     * 兑换奖励列表: c=act_halloween&m=medal_index
     *
     * 1 成功；-1 活动未开启；-2 获取奖励信息失败。is_exch：1 奖章够，0 不够或武将已拥有
     */
    public static function medal_index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::init($now);
        if ($act === null || $uid <= 0) {
            return ['status' => -1, 'msg' => self::LANG['activity_not_open']];
        }
        if (HalloweenShop::ROWS === []) {
            return ['status' => -2, 'msg' => self::LANG['candy_reward_error']];
        }
        // 获取我的代币
        $medal = (int)MemberIntegral::get($uid)['medal'];
        $list = [];
        foreach (HalloweenShop::ROWS as $value) {
            $value['is_exch'] = $medal >= $value['medal'] ? 1 : 0;
            if (in_array($value['type'], [8, 12, 26, 31], true) || ($value['type'] >= 17 && $value['type'] <= 25)) {
                $myInfo = self::getReward($uid, $value, 1, 'medal_index', $now);
                if (!empty($myInfo['info'])) {
                    $value['type'] = $myInfo['type'];
                    if ($value['value1'] <= 0) {
                        $value['value'] = 1;
                    }
                    $value['info'] = $myInfo['info'];
                    if (($myInfo['info']['is_exist'] ?? 0) > 0) {
                        $value['is_exch'] = 0;
                    }
                }
            }
            unset($value['value1']);
            $list[] = $value;
        }
        return ['status' => 1, 'list' => $list, 'medal' => $medal];
    }

    /**
     * 奖章兑换: c=act_halloween&m=action_medal（id 商城 id）
     *
     * 1 成功；-1 活动未开启；-2 操作太快了；-3 参数错误；-4 获取个人信息失败；-5 奖章不足；-6 获取奖励信息失败（含武将已拥有）
     */
    public static function action_medal(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        if ($uid <= 0 || !self::lock(self::ACTION_MEDAL_KEY . $uid)) {
            return ['status' => -2, 'msg' => self::LANG['fast_error']];
        }
        try {
            $act = self::init($now);
            if ($act === null) {
                return ['status' => -1, 'msg' => self::LANG['activity_not_open']];
            }
            $id = (int)$ctx->input('id', 0);
            if ($id <= 0) {
                return ['status' => -3, 'msg' => self::LANG['param_error']];
            }
            // 获取代币（奖章）
            $medal = (int)MemberIntegral::get($uid)['medal'];
            if ($medal <= 0) {
                return ['status' => -5, 'msg' => self::LANG['medal_error']];
            }
            $reward = HalloweenShop::ROWS[$id] ?? null;
            if ($reward === null) {
                return ['status' => -6, 'msg' => self::LANG['candy_reward_error']];
            }
            // 如果已经有该武将则不能被招募
            if ($reward['type'] === 12 && General::ownsTemplate($uid, $reward['value'])) {
                return ['status' => -6, 'msg' => self::LANG['candy_reward_error']];
            }
            if ($medal < $reward['medal']) {
                return ['status' => -5, 'msg' => self::LANG['medal_error']];
            }
            // 减奖章（余额不足时不扣）
            if (MemberIntegral::change($uid, $reward['medal'], 'medal', 2, 'act_halloween', 'action_medal', $now) === []) {
                return ['status' => -5, 'msg' => self::LANG['medal_error']];
            }
            // 发奖
            $rewardInfo = self::getReward($uid, $reward, 0, 'action_medal', $now);
            // 添加 log
            DB::table('halloween_medal2_log')->insert(['version' => $act['version'], 'uid' => $uid, 'reward_id' => $id, 'dateline' => $now]);
            $member = self::balances($uid);
            return [
                'status' => 1,
                'medal' => $medal - $reward['medal'],
                'gold' => $member['gold'],
                'silver' => $member['silver'],
                'act' => $member['act'],
                'reward' => $rewardInfo,
            ];
        } finally {
            Redis::del(self::ACTION_MEDAL_KEY . $uid);
        }
    }

    /**
     * 砸南瓜: c=act_halloween&m=action_pumpkin（id 南瓜 1 ~ 9）
     *
     * 1 成功（9 个都砸开时重置，reset 1）；-1 活动未开启；-2 操作太快了；-3 参数错误；-5 该南瓜已开启；-6 锤子不足；
     * -7 获取奖励信息失败
     */
    public static function action_pumpkin(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        if ($uid <= 0 || !self::lock(self::ACTION_HAMMER_KEY . $uid)) {
            return ['status' => -2, 'msg' => self::LANG['fast_error']];
        }
        try {
            $act = self::init($now);
            if ($act === null) {
                return ['status' => -1, 'msg' => self::LANG['activity_not_open']];
            }
            $version = $act['version'];
            $halloween = self::halloweenInfo($uid, $version);
            $id = (int)$ctx->input('id', 0);
            if ($id <= 0 || $id > self::ALL_TIMES) {
                return ['status' => -3, 'msg' => self::LANG['param_error']];
            }
            if ($halloween['id_' . $id] === 1) {
                return ['status' => -5, 'msg' => self::LANG['halloween_error']];
            }
            // 获取锤子
            $hammer = (int)MemberIntegral::get($uid)['hammer'];
            if ($hammer <= 0) {
                return ['status' => -6, 'msg' => self::LANG['hammer_error']];
            }
            // 随机获取奖励
            $picked = self::randArr(array_values(HalloweenPumpkin::ROWS), 1);
            if ($picked === []) {
                return ['status' => -7, 'msg' => self::LANG['candy_reward_error']];
            }
            // 修改南瓜状态（条件更新，并发只砸开一次）
            $opened = DB::table('halloween')->where('uid', $uid)->where('version', $version)->where('id_' . $id, 0)->whereNull('deleted_at')
                ->update(['id_' . $id => 1]);
            if ($opened <= 0) {
                return ['status' => -5, 'msg' => self::LANG['halloween_error']];
            }
            // 减锤子
            if (MemberIntegral::change($uid, 1, 'hammer', 2, 'act_halloween', 'action_pumpkin', $now) === []) {
                DB::table('halloween')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->update(['id_' . $id => 0]);
                return ['status' => -6, 'msg' => self::LANG['hammer_error']];
            }
            // 发奖
            $rewardInfo = self::getReward($uid, $picked[0], 0, 'action_pumpkin', $now);
            // 添加砸南瓜日志与获取奖励日志
            DB::table('halloween_log')->insert(['uid' => $uid, 'version' => $version, 'times' => $halloween['times'], 'ids' => $id, 'dateline' => $now]);
            DB::table('halloween_pumpkin_log')->insert([
                'uid' => $uid,
                'version' => $version,
                'times' => $halloween['times'],
                'type' => $rewardInfo['type'],
                'value' => $rewardInfo['value'],
                'ids' => $id,
                'dateline' => $now,
            ]);
            // 重新获取信息，判断 9 个南瓜状态是否重置
            $resetInfo = self::halloweenInfo($uid, $version);
            $num = 0;
            for ($i = 1; $i <= 9; $i++) {
                if ($resetInfo['id_' . $i] === 1) {
                    $num++;
                }
            }
            $reset = 0;
            if ($num === self::ALL_TIMES) {
                self::resetHalloween($uid, $version, $halloween['times'] + 1);
                $reset = 1;
            }
            return [
                'status' => 1,
                'all_button_status' => $reset,
                'hammer' => $hammer - 1,
                'reset' => $reset,
                'reward' => $rewardInfo,
            ];
        } finally {
            Redis::del(self::ACTION_HAMMER_KEY . $uid);
        }
    }

    /**
     * 砸九次（消耗元宝）: c=act_halloween&m=action_all_pumpkin
     *
     * 1 成功；-1 活动未开启；-2 操作太快了；-3 获取个人信息失败；-4 已经有南瓜开启；-6 元宝不足；-7 获取奖励信息失败
     */
    public static function action_all_pumpkin(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        if ($uid <= 0 || !self::lock(self::ACTION_HAMMER_NINE_KEY . $uid)) {
            return ['status' => -2, 'msg' => self::LANG['fast_error']];
        }
        try {
            $act = self::init($now);
            if ($act === null) {
                return ['status' => -1, 'msg' => self::LANG['activity_not_open']];
            }
            return Member::locked($uid, static fn (): array => self::doAllPumpkin($uid, $act['version'], $now));
        } finally {
            Redis::del(self::ACTION_HAMMER_NINE_KEY . $uid);
        }
    }

    /**
     * 砸九次（在玩家行锁事务里执行）
     *
     * @return array<string, mixed>
     */
    private static function doAllPumpkin(int $uid, int $version, int $now): array
    {
        $halloween = self::halloweenInfo($uid, $version);
        for ($i = 1; $i <= self::ALL_TIMES; $i++) {
            if ($halloween['id_' . $i] === 1) {
                return ['status' => -4, 'msg' => self::LANG['halloween_error']];
            }
        }
        $gold = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold') ?? 0);
        if ($gold < self::ALL_PRICE) {
            return ['status' => -6, 'msg' => self::LANG['gold_error']];
        }
        // 随机获取奖励
        $picked = self::randArr(array_values(HalloweenPumpkin::ROWS), self::ALL_TIMES);
        if ($picked === []) {
            return ['status' => -7, 'msg' => self::LANG['candy_reward_error']];
        }
        // 如果有相同的奖励则累加（物品按类型 + id 合并，数量累加 value1）
        $tmpArray = [];
        foreach ($picked as $row) {
            if (!in_array($row['type'], [8, 12, 26, 17, 18, 19, 20, 21, 22, 23, 24, 25, 31], true)) {
                if (array_key_exists($row['type'], $tmpArray)) {
                    $tmpArray[$row['type']]['value'] += $row['value'];
                } else {
                    $tmpArray[$row['type']] = $row;
                }
            } else {
                $key = $row['type'] . $row['value'];
                if (array_key_exists($key, $tmpArray)) {
                    $tmpArray[$key]['num'] += $row['value1'];
                } else {
                    $tmpArray[$key] = $row;
                    $tmpArray[$key]['num'] = $row['value1'];
                }
            }
        }
        foreach ($tmpArray as $key => $val) {
            if (($val['num'] ?? 0) > 0) {
                $tmpArray[$key]['value1'] = $val['num'];
            }
        }
        // 减元宝
        if (!Member::deductCurrency($uid, gold: self::ALL_PRICE)) {
            return ['status' => -6, 'msg' => self::LANG['gold_error']];
        }
        // 发奖
        $rewardInfo = [];
        foreach ($tmpArray as $tmp) {
            $rewardInfo[] = self::getReward($uid, $tmp, 0, 'action_all_pumpkin', $now);
        }
        // 添加日志（九个南瓜各一条）与砸南瓜获取奖励日志
        $logs = [];
        for ($i = 1; $i <= 9; $i++) {
            $logs[] = ['uid' => $uid, 'version' => $version, 'times' => $halloween['times'], 'ids' => $i, 'dateline' => $now];
        }
        DB::table('halloween_log')->insertBatch($logs);
        $pumpkinLogs = [];
        foreach ($rewardInfo as $key => $val) {
            $pumpkinLogs[] = [
                'uid' => $uid,
                'version' => $version,
                'times' => $halloween['times'],
                'type' => $val['type'],
                'value' => $val['value'],
                'ids' => $key + 1,
                'dateline' => $now,
            ];
        }
        DB::table('halloween_pumpkin_log')->insertBatch($pumpkinLogs);
        // 修改次数、南瓜状态
        self::resetHalloween($uid, $version, $halloween['times'] + 1);
        return [
            'status' => 1,
            'gold' => (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold') ?? 0),
            'reward' => $rewardInfo,
        ];
    }

    /**
     * 买锤子: c=act_halloween&m=buy_hammer（35 元宝一个）
     *
     * 1 成功；-1 活动未开启；-2 操作太快了；-3 元宝不足
     */
    public static function buy_hammer(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        if ($uid <= 0 || !self::lock(self::BUY_HAMMER_KEY . $uid)) {
            return ['status' => -2, 'msg' => self::LANG['fast_error']];
        }
        try {
            $act = self::init($now);
            if ($act === null) {
                return ['status' => -1, 'msg' => self::LANG['activity_not_open']];
            }
            // 减金币（元宝不足时不扣）
            if (!Member::deductCurrency($uid, gold: self::BUY_HAMMER_PRICE)) {
                return ['status' => -3, 'msg' => self::LANG['gold_error']];
            }
            // 加锤子数量
            $hammer = MemberIntegral::change($uid, 1, 'hammer', 1, 'act_halloween', 'buy_hammer', $now)['hammer'] ?? 0;
            return [
                'status' => 1,
                'hammer' => $hammer,
                'gold' => (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold') ?? 0),
            ];
        } finally {
            Redis::del(self::BUY_HAMMER_KEY . $uid);
        }
    }

    /**
     * 计划任务：活动结束后自动把玩家手中的奖章兑换成银币（cron_acttask.php:get_helloween_reward）
     *
     * 只在活动结束后 1 小时内执行（endtime <= 当前 <= endtime + 3600）。奖章大于 0 的玩家：奖章清零、记 halloween_medal_log、
     * 每个奖章 2 万银币、发信件。
     * 与旧版的差别：旧版批量清零后发奖；这里逐个玩家用条件更新（奖章等于读到的值时才清零）清零成功才发，重复执行不会重复发。
     *
     * @return int -1 无活动；-2 活动未结束；-3 结束超过 1 小时；1 已执行
     */
    public static function cronReward(int $now): int
    {
        $activity = ActivityClock::byName(self::ACTIVITY);
        if ($activity === null) {
            return -1;
        }
        if ($activity['endtime'] > $now) {
            return -2;
        }
        if ($activity['endtime'] + 3600 < $now) {
            return -3;
        }
        $version = (int)$activity['version'];
        foreach (DB::table('member_integral')->select('uid', 'medal')->where('medal', '>', 0)->whereNull('deleted_at')->get() as $val) {
            $uid = (int)$val['uid'];
            $medal = (int)$val['medal'];
            $cleared = DB::table('member_integral')->where('uid', $uid)->where('medal', $medal)->whereNull('deleted_at')->update(['medal' => 0]);
            if ($cleared <= 0) {
                continue;
            }
            $num = $medal * self::MEDAL_SILVER;
            DB::table('halloween_medal_log')->insert(['uid' => $uid, 'version' => $version, 'medal' => $medal, 'silver' => $num, 'dateline' => $now]);
            Member::addCurrency($uid, silver: $num);
            $message = self::LANG['cron_halloweenfinish_get1'] . self::LANG['cron_halloweenfinish_get2']
                . self::LANG['cron_silver'] . $num . self::LANG['cron_ge'] . self::LANG['cron_check'];
            MessageHandler::sendSystemMessage([$uid], 1, self::LANG['message_system_title'], $message);
        }
        return 1;
    }

    /**
     * 获取奖励信息或添加玩家奖励（get_reward；$isGet 0 添加奖励，1 只取信息）
     *
     * @param array<string, mixed> $rewardInfo 奖励行：type、value、value1
     * @return array<string, mixed> type、value（物品类为数量）、物品类的 info
     */
    private static function getReward(int $uid, array $rewardInfo, int $isGet, string $method, int $now): array
    {
        $type = (int)$rewardInfo['type'];
        $num = (int)$rewardInfo['value'];
        $return = ['type' => $type, 'value' => $num];
        $id = 0;
        // 装备、坐骑单独处理
        if (FestReward::isItemType($type)) {
            $id = $num;
            $num = (int)($rewardInfo['value1'] ?? 0) > 0 ? (int)$rewardInfo['value1'] : 1;
            $return['value'] = $num;
        }
        switch ($type) {
            case 1: // 元宝（旧版没有 break，继续执行银币分支）
                Member::addCurrency($uid, gold: $num, silver: $num);
                break;
            case 8: // 装备
                $item = FestReward::equipInfo($id);
                if ($item !== null) {
                    if ($isGet === 0) {
                        Equipment::addFromTemplate($uid, $id, $num);
                    }
                    $return['info'] = $item;
                }
                break;
            case 12: // 武将
                $return['info'] = FestReward::generalInfo($id);
                if (General::ownsTemplate($uid, $id)) {
                    $return['info']['is_exist'] = 1;
                    $jade = FestReward::generalJade($id);
                    if ($isGet === 0 && $jade !== null) {
                        TraderLog::addJade($uid, 3, (int)$jade, class: 'act_halloween', method: $method, now: $now);
                    }
                } elseif ($isGet === 0 && General::addGeneral($uid, $id) !== null) {
                    // 添加酒馆日志(武将id, 拥有状态, 获取方式)
                    PubLog::add($uid, $id, 1, 11, $now);
                }
                break;
            case 13: // 军令（add_act(1, value)）
                Member::addCurrency($uid, act: (int)$rewardInfo['value']);
                break;
            case 17: case 18: case 19: case 20: case 21: case 22: case 23: case 24: case 25: // 装备精华
                $ess = FestReward::essenceInfo($id);
                if ($ess !== null) {
                    if ($isGet === 0) {
                        Equipment::addTowerItem($uid, $id, $num);
                    }
                    $return['info'] = $ess;
                }
                break;
            case 26: // 坐骑
                if ($isGet === 0) {
                    OwnMount::addMount($uid, $id, 0, $num, 'act_halloween', $method, $now);
                }
                $return['info'] = FestReward::mountInfo($id, true);
                break;
            case 29: // 锤子
                MemberIntegral::change($uid, $num, 'hammer', 1, 'act_halloween', $method, $now);
                break;
            case 30: // 奖章
                MemberIntegral::change($uid, $num, 'medal', 1, 'act_halloween', $method, $now);
                break;
            case 31: // 将魂（按 1 级发放；展示 value1 级的属性）
                if ($isGet === 0) {
                    OwnSoul::addHeroSoul($uid, (int)$rewardInfo['value'], 1);
                }
                $soul = Soul::ROWS[(int)$rewardInfo['value']] ?? null;
                if ($soul !== null) {
                    $attribute = OwnSoul::levelupRule((int)($rewardInfo['value1'] ?? 0), (int)$soul['type'], (int)$soul['quality']);
                    if (isset($attribute['info'])) {
                        $return['info'] = $attribute['info'] + [
                            'name' => $soul['name'],
                            'quality' => $soul['quality'],
                            'type' => $soul['type'],
                            'image' => $soul['type'] . $soul['quality'],
                            'power' => $attribute['info']['addition'],
                        ];
                    }
                }
                break;
            default:
                FestReward::grantBasic($uid, $type, $num, 'act_halloween', $method, $now);
                break;
        }
        return $return;
    }

    /**
     * 奖品随机计算（rand_arr）：按 id 排序后 rate × 10 划分区间 [k + 1, rate × 10 + k]，尝试「奖品个数」次，
     * 每次 mt_rand(1, 总 rate × 10) 落在某个区间（不含下界）就选中，选够 $num 个为止
     *
     * @param list<array<string, int>> $arr
     * @return list<array<string, int>>
     */
    private static function randArr(array $arr, int $num): array
    {
        usort($arr, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);
        $k = 0;
        $total = 0;
        foreach ($arr as $key => $value) {
            $arr[$key]['rate_in'] = [$k + 1, $value['rate'] * 10 + $k];
            $k += $value['rate'] * 10;
            $total += $value['rate'];
        }
        $return = [];
        $count = count($arr);
        for ($i = 1; $i <= $count; $i++) {
            $randNum = mt_rand(1, $total * 10);
            foreach ($arr as $val) {
                if ($randNum > $val['rate_in'][0] && $randNum <= $val['rate_in'][1]) {
                    // 达到需求数量自动退出
                    if (count($return) >= $num) {
                        break;
                    }
                    $return[] = $val;
                }
            }
        }
        return $return;
    }

    /**
     * 活动时间（_init）：activity_status 开启且 starttime <= 当前 < endtime 时返回版本与日期（m.d~m.d）；否则 null
     *
     * @return array{version: int, date: string}|null
     */
    private static function init(int $now): ?array
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null || $now >= (int)$info['endtime']) {
            return null;
        }
        return ['version' => (int)$info['version'], 'date' => date('m.d', (int)$info['starttime']) . '~' . date('m.d', (int)$info['endtime'])];
    }

    /**
     * 玩家南瓜信息（get_halloween_info：没有时初始化；唯一键 uid + version，并发只插一行）
     *
     * @return array<string, int>
     */
    private static function halloweenInfo(int $uid, int $version): array
    {
        $row = DB::table('halloween')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first();
        if ($row === null) {
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_halloween` (`uid`, `version`, `times`, `id_1`, `id_2`, `id_3`, `id_4`, `id_5`, `id_6`, `id_7`, `id_8`, `id_9`)
                 VALUES (:uid, :version, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)',
                ['uid' => $uid, 'version' => $version]
            );
            $row = DB::table('halloween')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first() ?? [];
        }
        $return = ['times' => (int)($row['times'] ?? 0)];
        for ($i = 1; $i <= 9; $i++) {
            $return['id_' . $i] = (int)($row['id_' . $i] ?? 0);
        }
        return $return;
    }

    /**
     * 重置南瓜（reset_halloween：轮数写成 $times，九个南瓜都未开启）
     */
    private static function resetHalloween(int $uid, int $version, int $times): void
    {
        $data = ['times' => $times];
        for ($i = 1; $i <= 9; $i++) {
            $data['id_' . $i] = 0;
        }
        DB::table('halloween')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->update($data);
    }

    private static function lock(string $key): bool
    {
        return (bool)Redis::instance()->set($key, 1, ['nx', 'ex' => self::LOCK_TTL]);
    }

    /**
     * @return array{gold: int, silver: int, act: int}
     */
    private static function balances(int $uid): array
    {
        $row = DB::table('member')->select('gold', 'silver', 'act')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        return ['gold' => (int)($row['gold'] ?? 0), 'silver' => (int)($row['silver'] ?? 0), 'act' => (int)($row['act'] ?? 0)];
    }
}
