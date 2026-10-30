<?php

declare(strict_types=1);

namespace Hanjiang\Cron;

use Hanjiang\Handler\Pvp\CompeteHandler;

/**
 * 群雄逐鹿计划任务（移植旧版 controllers/cron.php:get_compete_reward「计划任务发放群雄逐鹿排行奖励」）
 *
 * 由 server.php 在 HTTP Worker #0 上每 INTERVAL 秒调用。旧版代码包里没有 crontab：任务只在活动结束后 10 小时内发放
 * （endtime + 36000），按小时执行即可在窗口内补发；已领取（get_rank > 0）的玩家不会重复发放。
 */
final class CompeteCron
{
    /** 执行间隔（秒） */
    public const int INTERVAL = 3600;

    /**
     * @return int 同 CompeteHandler::settleRewards（1 已发放；-1 无活动；-2 活动未结束；-3 结束超过 10 小时；-4 没有可发放的用户）
     */
    public static function run(int $now): int
    {
        return CompeteHandler::settleRewards($now);
    }
}
