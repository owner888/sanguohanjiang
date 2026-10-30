<?php

declare(strict_types=1);

namespace Hanjiang\Cron;

use Hanjiang\Handler\Pvp\SiegeHandler;
use OmniPHP\Database\DB;

/**
 * 国战计划任务（移植旧版 controllers/cron.php:siege_battle「计划任务调用国战」与 clear_warstatus「每周定时清除国家成员的历史参战状态」）
 *
 * 由 server.php 在 HTTP Worker #0 上调用。旧版代码包里没有 crontab，执行时间按旧版逻辑推定：
 * - siege_battle 只在周六 18 ~ 21 点、各城开战前 3 分钟起的 10 分钟内计算该城战斗，每分钟执行（BATTLE_INTERVAL）；
 * - clear_warstatus 每周一次。成员参战状态（warstatus）决定能否进上次国战的战场、领奖励，下一次国战在周六开放报名，
 *   所以取每周六 00:00（CLEAR_DAY / CLEAR_AT）清除，留足一周的领奖时间。
 */
final class SiegeCron
{
    /** 国战计算执行间隔（秒） */
    public const int BATTLE_INTERVAL = 60;

    /** 清除参战状态：每周几（date('w')）的几点 */
    public const int CLEAR_DAY = 6;
    public const string CLEAR_AT = '00:00';

    /**
     * 国战计算（cron.php:siege_battle）
     *
     * @return int 1 本次算出了一座城的战斗；0 没有需要计算的城
     */
    public static function battle(int $now): int
    {
        return SiegeHandler::cronBattle($now);
    }

    /**
     * 每日 CLEAR_AT 调用：周六清除全部国家成员的参战状态（cron.php:clear_warstatus）
     *
     * @return int 清除的行数；不是周六时为 0
     */
    public static function clearWarstatus(int $now): int
    {
        if ((int)date('w', $now) !== self::CLEAR_DAY) {
            return 0;
        }
        // siege_model->clear_warstatus（不放在 SiegeHandler：Handler 的无参公开静态方法会被 c=siege&m=… 直接调用）
        return DB::table('member_country')->whereNull('deleted_at')->update(['city' => 0, 'warstatus' => 0]);
    }
}
