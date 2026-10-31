<?php

declare(strict_types=1);

namespace Hanjiang\Cron;

use Hanjiang\Handler\Activity\TreasuremapHandler;

/**
 * 藏宝图计划任务（移植旧版 controllers/cron.php:sale_map「出售藏宝图，跟征收一起更新」）
 *
 * 由 server.php 在 HTTP Worker #0 上每天 RUN_AT 执行。旧版代码包里没有 crontab，注释写明跟征收一起更新：征收计划任务在每日 7 点
 * （Member::levyCron），所以定在 07:00。
 */
final class TreasuremapCron
{
    /** 执行时间（时:分） */
    public const string RUN_AT = '07:00';

    /**
     * @return int 卖出的玩家数
     */
    public static function run(int $now): int
    {
        return TreasuremapHandler::saleMap($now);
    }
}
