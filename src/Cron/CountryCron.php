<?php

declare(strict_types=1);

namespace Hanjiang\Cron;

use OmniPHP\Database\DB;

/**
 * 国家相关全服计划任务（移植自旧版 controllers/cron_country.php）
 *
 * 由 server.php 在 HTTP Worker #0 上按每日 00:00 定时调用。
 */
final class CountryCron
{
    /**
     * 清空所有玩家今日贡献（cron_country::clear_contribute → country_model->clear_contribute）
     */
    public static function clearContribute(): int
    {
        return DB::table('member_country')->whereNull('deleted_at')->update(['contribute' => 0]);
    }
}
