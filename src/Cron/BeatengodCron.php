<?php

declare(strict_types=1);

namespace Hanjiang\Cron;

use Hanjiang\Handler\Pvp\BeatengodHandler;

/**
 * 暴打名将计划任务（移植旧版 controllers/cron.php:get_beatengod_reward「发放暴打名将排行奖励」与
 * get_beatengod_box_reward「自动领取暴打名将宝箱奖励」）
 *
 * 由 server.php 在 HTTP Worker #0 上每 INTERVAL 秒调用。旧版代码包里没有 crontab：两个任务都只在活动结束后 10 小时内执行
 * （endtime + 36000），按小时执行即可在窗口内补发；已领取（get_rank > 0 / 已有宝箱领取记录）的不会重复发放。
 */
final class BeatengodCron
{
    /** 执行间隔（秒） */
    public const int INTERVAL = 3600;

    /**
     * 未领取的排行奖励（cron::get_beatengod_reward），返回值见 BeatengodHandler::settleRankReward
     */
    public static function rank(int $now): int
    {
        return BeatengodHandler::settleRankReward($now);
    }

    /**
     * 未领取的宝箱奖励（cron::get_beatengod_box_reward），返回值见 BeatengodHandler::settleBoxReward
     */
    public static function box(int $now): int
    {
        return BeatengodHandler::settleBoxReward($now);
    }
}
