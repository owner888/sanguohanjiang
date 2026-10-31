<?php

declare(strict_types=1);

namespace Hanjiang\Cron;

use Hanjiang\Handler\Activity\NewyearbossHandler;

/**
 * 春节年兽计划任务（移植旧版 controllers/cron.php:get_newyearboss_reward「计划任务发放春节年兽排行奖励」，参数 type 1 伤害 /
 * 2 鼓舞 / 3 闪避）
 *
 * 由 server.php 在 HTTP Worker #0 上每 INTERVAL 秒调用，三种排行各执行一次。旧版代码包里没有 crontab：任务只在活动结束后
 * 10 小时内发放（endtime + 36000），按小时执行即可在窗口内补发；已领取（newyear_boss_reward_log type 2）的玩家不会重复发放。
 */
final class NewyearbossCron
{
    /** 执行间隔（秒） */
    public const int INTERVAL = 3600;

    /**
     * @return array<int, int> [排行类型 => NewyearbossHandler::settleRankReward 的返回值]
     */
    public static function run(int $now): array
    {
        $return = [];
        foreach ([1, 2, 3] as $type) {
            $return[$type] = NewyearbossHandler::settleRankReward($type, $now);
        }
        return $return;
    }
}
