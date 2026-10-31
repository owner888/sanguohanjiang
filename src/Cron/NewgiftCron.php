<?php

declare(strict_types=1);

namespace Hanjiang\Cron;

use Hanjiang\Handler\City\NewgiftHandler;

/**
 * 新服大礼包计划任务（移植旧版 controllers/cron.php:get_newgift_reward「自动发放新服礼包的奖励」）
 *
 * 由 server.php 在 HTTP Worker #0 上每天 RUN_AT 执行。旧版代码包里没有 crontab：任务给 30 天活动已结束、还有可领取礼包的玩家
 * 自动领取，按每日执行即可（每个玩家的活动结束时间不同，结束后的下一次执行发放）；定在 00:10，在每日计划任务之后。
 */
final class NewgiftCron
{
    /** 执行时间（时:分） */
    public const string RUN_AT = '00:10';

    /**
     * @return int 发放的玩家数
     */
    public static function run(int $now): int
    {
        return NewgiftHandler::autoReward($now);
    }
}
