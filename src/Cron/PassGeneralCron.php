<?php

declare(strict_types=1);

namespace Hanjiang\Cron;

use Hanjiang\MasterData\PassgeneralAchievement;
use Hanjiang\Model\Member;
use Hanjiang\Model\PassGeneral;
use OmniPHP\Database\DB;

/**
 * 过关斩将计划任务（移植旧版 controllers/cron_acttask.php:pass_general「c=cron_acttask&m=pass_general&diy=4」）
 *
 * 由 server.php 在 HTTP Worker #0 上每日 RUN_AT 调用（旧版注释「每天零点重置」）。活动时间取 MasterData ActivityTime
 * （pass_general 已于 2016-12-31 00:00 结束），现在三项都直接返回。
 *
 * 1. update_pass_general_info：活动期间每天把未领取的宝箱奖励自动发放并发信，再重置全部玩家的进度与免费次数；
 * 2. get_pass_general_num_reward：活动结束后 10 小时内，把全部玩家的兑奖券按每张 20000 银币自动兑换并发信；
 * 3. get_passsgeneral_achieve_reward：活动结束后 10 小时内，自动领取全部玩家未领取的成就奖励并发信。
 */
final class PassGeneralCron
{
    /** 物品流水的执行类与方法（旧版计划任务入口 c=cron_acttask&m=pass_general） */
    private const string LOG_CLASS = 'cron_acttask';
    private const string LOG_METHOD = 'pass_general';

    /** 每日执行时间（时:分，Asia/Shanghai） */
    public const string RUN_AT = '00:00';

    /** 活动开始 5 小时内不刷新次数 */
    private const int FIRST_DAY = 18000;

    /** 活动结束后的自动兑换 / 领奖时限（旧版 36000 秒，注释写「一个小时内」） */
    private const int END_WINDOW = 36000;

    /** 每张兑奖券兑换的银币 */
    private const int LOTTERY_SILVER = 20000;

    public static function run(int $now): void
    {
        $activity = PassGeneral::activity();
        if ($activity === null || $now - $activity['starttime'] < self::FIRST_DAY) {
            // 如果当前时间距离活动开始时间小于5小时 就不刷新次数 后面的程序也不执行
            return;
        }
        // 每天零点重置：过关奖励--每日清理过关斩将信息
        self::updatePassGeneralInfo($activity, $now);
        // 活动结束后重置：过关奖励--自动兑换玩家手中兑奖券奖励
        self::getPassGeneralNumReward($activity, $now);
        // 过关斩将--自动领取成就奖励
        self::getPassgeneralAchieveReward($activity, $now);
    }

    /**
     * 每日清理过关斩将信息（update_pass_general_info）
     *
     * 旧版按循环计数发放 $new_reward[$i - 1]，只有第一个玩家拿到奖励；开发者要求修正为每人都发放（见 PassGeneral::autoBoxReward）。
     *
     * @param array<string, mixed> $activity
     * @return int 自动领取的人数
     */
    public static function updatePassGeneralInfo(array $activity, int $now): int
    {
        // 判断活动是否结束 / 是否开始
        if ($activity['endtime'] < $now || $activity['starttime'] > $now) {
            return 0;
        }
        $version = $activity['version'];
        $userData = DB::table('passgeneral')->where('version', $version)->whereNull('deleted_at')->get();
        if ($userData === []) {
            return 0;
        }
        $i = 0;
        // 如果有未领取的奖励 自动发放给玩家
        foreach ($userData as $val) {
            $val = array_map('intval', $val);
            if ($val['is_get'] !== 2) {
                continue;
            }
            // 先原子抢占（与玩家手动领取 / 请求时的每日清理并发时只发一次）
            if (!PassGeneral::claimBox($val)) {
                continue;
            }
            $i++;
            PassGeneral::autoBoxReward($val, $version, $now, self::LOG_CLASS, self::LOG_METHOD);
        }
        // 零点初始化全部玩家（旧版按读到的 uid 列表，即本版本全部玩家；不更新刷新时间）
        PassGeneral::resetVersion($version);
        return $i;
    }

    /**
     * 过关奖励--自动兑换玩家手中兑奖券奖励（get_pass_general_num_reward）
     *
     * @param array<string, mixed> $activity
     * @return int 兑换的人数
     */
    public static function getPassGeneralNumReward(array $activity, int $now): int
    {
        // 判断是否可发放奖励（活动已结束，并且在活动结束后 10 小时内）
        if ($activity['endtime'] > $now || $activity['endtime'] + self::END_WINDOW < $now) {
            return 0;
        }
        $count = 0;
        // 更新玩家兑奖券（全部 act_kemari_int > 0 的玩家，含蹴鞠活动的兑奖券）
        $rows = DB::table('member_integral')->select('uid', 'act_kemari_int')->where('act_kemari_int', '>', 0)->whereNull('deleted_at')->get();
        foreach ($rows as $data) {
            $uid = (int)$data['uid'];
            $lottery = (int)$data['act_kemari_int'];
            // 更新兑换券个数（按读到的数量比较交换，与玩家兑奖并发时不重复发放）
            $affected = DB::table('member_integral')->where('uid', $uid)->where('act_kemari_int', $lottery)
                ->whereNull('deleted_at')->update(['act_kemari_int' => 0]);
            if ($affected <= 0 || $uid === 0) {
                continue;
            }
            // 发放奖励
            $num = $lottery * self::LOTTERY_SILVER;
            Member::addCurrency($uid, silver: $num);
            $re = PassGeneral::LANG_CRON_SILVER . $num . PassGeneral::LANG_CRON_GE;
            $message = PassGeneral::LANG_CRON_PASSGENERALFINISH_GET1 . PassGeneral::LANG_CRON_PASSGENERALFINISH_GET2 . $re . PassGeneral::LANG_CRON_CHECK;
            PassGeneral::sendSystemMessage($uid, $message);
            $count++;
        }
        return $count;
    }

    /**
     * 过关斩将--自动发放成就奖励（get_passsgeneral_achieve_reward）
     *
     * @param array<string, mixed> $activity
     * @return int 发放的人数
     */
    public static function getPassgeneralAchieveReward(array $activity, int $now): int
    {
        // 判断是否可发放奖励（活动已结束，并且在活动结束后 10 小时内）
        if ($activity['endtime'] > $now || $activity['endtime'] + self::END_WINDOW < $now) {
            return 0;
        }
        $version = $activity['version'];
        // 获取所有玩家通关信息
        $allLog = DB::table('passgeneral')->select('uid', 'succ_num')->where('version', $version)->whereNull('deleted_at')->get();
        // 把未领取的用户和奖励列表筛选出来（成就日志唯一键：已领取或与玩家手动领取并发时不再发放）
        $reward = [];
        foreach ($allLog as $num) {
            $uid = (int)$num['uid'];
            foreach (PassgeneralAchievement::ROWS as $ach) {
                if ((int)$num['succ_num'] >= $ach['num'] && PassGeneral::addAchievementLog($uid, $version, $ach['id'], $now) === 1) {
                    // 可领取的奖励（同类型累加）
                    for ($i = 1; $i <= 3; $i++) {
                        $reward[$uid][$ach['type' . $i]] = ($reward[$uid][$ach['type' . $i]] ?? 0) + $ach['value' . $i];
                    }
                }
            }
        }
        // 发送奖励
        foreach ($reward as $uid => $val) {
            $message = PassGeneral::LANG_CRON_PASSGENERAL;
            foreach ($val as $type => $num) {
                $message .= PassGeneral::getRewardItem($type, $num, $uid, $now, self::LOG_CLASS, self::LOG_METHOD);
            }
            $message .= PassGeneral::LANG_CRON_CHECK;
            PassGeneral::sendSystemMessage($uid, $message);
        }
        return count($reward);
    }
}
