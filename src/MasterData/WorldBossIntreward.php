<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 世界boss积分数据表（旧版 zc_sanguo_world_boss_intreward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class WorldBossIntreward
{
    public const string TABLE = 'zc_sanguo_world_boss_intreward';

    /**
     * 按主键 id 索引，共 13 行。字段：
     *   - id (int) 名次id
     *   - rank (int) 排名区间
     *   - getvalue (int) 演武榜积分
     */
    public const array ROWS = [
        1 => ['id' => 1, 'rank' => 1, 'getvalue' => 1000],
        2 => ['id' => 2, 'rank' => 2, 'getvalue' => 900],
        3 => ['id' => 3, 'rank' => 3, 'getvalue' => 800],
        4 => ['id' => 4, 'rank' => 4, 'getvalue' => 700],
        5 => ['id' => 5, 'rank' => 11, 'getvalue' => 600],
        6 => ['id' => 6, 'rank' => 21, 'getvalue' => 500],
        7 => ['id' => 7, 'rank' => 31, 'getvalue' => 400],
        8 => ['id' => 8, 'rank' => 51, 'getvalue' => 300],
        9 => ['id' => 9, 'rank' => 76, 'getvalue' => 200],
        10 => ['id' => 10, 'rank' => 101, 'getvalue' => 100],
        11 => ['id' => 11, 'rank' => 201, 'getvalue' => 70],
        12 => ['id' => 12, 'rank' => 501, 'getvalue' => 50],
        13 => ['id' => 13, 'rank' => 1001, 'getvalue' => 30],
    ];
}
