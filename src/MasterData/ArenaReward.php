<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 奖励参照表（旧版 zc_sanguo_arena_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ArenaReward
{
    public const string TABLE = 'zc_sanguo_arena_reward';

    /**
     * 按主键 id 索引，共 13 行。字段：
     *   - id (mediumint) 奖励id
     *   - rank (mediumint) 将领最高等级
     *   - silver (float) 奖励银币系数
     *   - gettype (tinyint) 获得翡翠类型
     *   - getnum (int) 奖励翡翠个数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'rank' => 1, 'silver' => 20000.0, 'gettype' => 3, 'getnum' => 50],
        2 => ['id' => 2, 'rank' => 2, 'silver' => 15000.0, 'gettype' => 3, 'getnum' => 40],
        3 => ['id' => 3, 'rank' => 3, 'silver' => 12000.0, 'gettype' => 3, 'getnum' => 35],
        4 => ['id' => 4, 'rank' => 4, 'silver' => 10000.0, 'gettype' => 3, 'getnum' => 30],
        5 => ['id' => 5, 'rank' => 11, 'silver' => 8000.0, 'gettype' => 3, 'getnum' => 25],
        6 => ['id' => 6, 'rank' => 21, 'silver' => 7000.0, 'gettype' => 3, 'getnum' => 20],
        7 => ['id' => 7, 'rank' => 31, 'silver' => 6000.0, 'gettype' => 3, 'getnum' => 15],
        8 => ['id' => 8, 'rank' => 51, 'silver' => 5000.0, 'gettype' => 3, 'getnum' => 10],
        9 => ['id' => 9, 'rank' => 76, 'silver' => 4000.0, 'gettype' => 3, 'getnum' => 5],
        10 => ['id' => 10, 'rank' => 101, 'silver' => 3000.0, 'gettype' => 2, 'getnum' => 15],
        11 => ['id' => 11, 'rank' => 201, 'silver' => 2000.0, 'gettype' => 2, 'getnum' => 10],
        12 => ['id' => 12, 'rank' => 501, 'silver' => 1000.0, 'gettype' => 2, 'getnum' => 8],
        13 => ['id' => 13, 'rank' => 1001, 'silver' => 500.0, 'gettype' => 2, 'getnum' => 5],
    ];
}
