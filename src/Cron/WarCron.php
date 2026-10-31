<?php

declare(strict_types=1);

namespace Hanjiang\Cron;

use Hanjiang\Cross\WarServer;

/**
 * 武斗会计划任务（服务器端的赛程推进，见 Cross\WarServer::checkPk；旧版 war 服务器的控制器与 crontab 都不在旧版代码包里）
 *
 * 由 server.php 在 HTTP Worker #0 上每 INTERVAL 秒执行（推定每分钟，同战神殿）：报名结束后同步全部报名玩家；
 * 每天开打时生成对阵，每局开打前 1 分钟算出战斗并生成下一轮；预赛到复赛每天打完排名晋级；决赛打完更新四强。
 */
final class WarCron
{
    /** 执行间隔（秒，推定） */
    public const int INTERVAL = 60;

    public static function run(int $now): int
    {
        return WarServer::checkPk($now);
    }
}
