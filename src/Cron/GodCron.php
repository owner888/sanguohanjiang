<?php

declare(strict_types=1);

namespace Hanjiang\Cron;

use Hanjiang\Cross\GodServer;

/**
 * 战神殿计划任务（移植旧版 battle controllers/god.php:check_pk）
 *
 * 由 server.php 在 HTTP Worker #0 上每 INTERVAL 秒执行（旧版代码包里没有 crontab：check_pk 按「开打前 60 秒内」判断，
 * 推定为每分钟执行）。报名结束后 1 分钟内同步报名玩家并生成第一轮对阵；战斗阶段每轮（每局）开打前 1 分钟算出全部战斗。
 */
final class GodCron
{
    /** 执行间隔（秒，推定） */
    public const int INTERVAL = 60;

    public static function run(int $now): int
    {
        return GodServer::checkPk($now);
    }
}
