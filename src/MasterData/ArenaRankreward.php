<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 演武榜积分排行奖励表（旧版 zc_sanguo_arena_rankreward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ArenaRankreward
{
    public const string TABLE = 'zc_sanguo_arena_rankreward';

    /**
     * 按主键 id 索引，共 13 行。字段：
     *   - id (int) 奖励ID
     *   - rank (int) 排行
     *   - intnum (int) 奖励积分
     */
    public const array ROWS = [
        1 => ['id' => 1, 'rank' => 1, 'intnum' => 2000],
        2 => ['id' => 2, 'rank' => 2, 'intnum' => 1800],
        3 => ['id' => 3, 'rank' => 3, 'intnum' => 1600],
        4 => ['id' => 4, 'rank' => 4, 'intnum' => 1400],
        5 => ['id' => 5, 'rank' => 11, 'intnum' => 1200],
        6 => ['id' => 6, 'rank' => 21, 'intnum' => 1000],
        7 => ['id' => 7, 'rank' => 31, 'intnum' => 800],
        8 => ['id' => 8, 'rank' => 51, 'intnum' => 600],
        9 => ['id' => 9, 'rank' => 76, 'intnum' => 300],
        10 => ['id' => 10, 'rank' => 101, 'intnum' => 200],
        11 => ['id' => 11, 'rank' => 201, 'intnum' => 140],
        12 => ['id' => 12, 'rank' => 501, 'intnum' => 100],
        13 => ['id' => 13, 'rank' => 1001, 'intnum' => 60],
    ];
}
