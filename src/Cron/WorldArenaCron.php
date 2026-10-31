<?php

declare(strict_types=1);

namespace Hanjiang\Cron;

use Hanjiang\Cross\WorldArenaServer;
use Hanjiang\Model\WorldArena;

/**
 * 跨服演武计划任务（移植旧版 s1 cron.php:cache_top_rank、battle cron.php:reset_rank_list / reset_member_tree）
 *
 * 由 server.php 在 HTTP Worker #0 上执行。旧版代码包里没有 crontab，执行时间按注释推定：
 * - 每天 RUN_AT（0 点后，在跨服远征 0 点后 10 分钟的更新锁内）：先记本服演武榜昨日前三（cache_top_rank：缓存 + world_top_log，
 *   清押注缓存——旧版 clear_bet_info 用 get 取通配键，什么也不删，照搬为不处理），再由战斗服务器记录昨日排名、
 *   按各服前三更新跨服排行榜（reset_rank_list）；
 * - 每 TREE_INTERVAL 秒（旧版「每天 00:10 后执行 10 分钟一次」）：更新榜上玩家的神树等级（reset_member_tree），0 点到 0:10 不执行。
 */
final class WorldArenaCron
{
    /** 每日执行时间（时:分，推定） */
    public const string RUN_AT = '00:01';

    /** 神树同步间隔（秒） */
    public const int TREE_INTERVAL = 600;

    /**
     * 每日：本服前三 + 跨服排行榜重置
     */
    public static function daily(int $now): void
    {
        WorldArena::cacheTopRank((int)date('Ymd', $now));
        WorldArenaServer::resetRankList($now);
    }

    /**
     * 神树同步（0:10 以前不执行）
     */
    public static function tree(int $now): int
    {
        if ($now - (int)strtotime(date('Y-m-d', $now)) < 600) {
            return 0;
        }
        return WorldArenaServer::resetMemberTree($now);
    }
}
