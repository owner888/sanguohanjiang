<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 演武榜奖品兑换表（旧版 zc_sanguo_arena_exchange）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ArenaExchange
{
    public const string TABLE = 'zc_sanguo_arena_exchange';

    /**
     * 按主键 id 索引，共 38 行。字段：
     *   - id (int) 自增ID
     *   - re_type (int) 奖品类型
     *   - re_value1 (int) 奖品参数1
     *   - re_value2 (int) 奖品参数2
     *   - use_int (int) 消耗积分
     *   - type (int) 兑换类型：1，每日刷新；2，每日不刷新
     *   - num (int) 可兑换次数：0，无限；大于0，可兑换次数；
     */
    public const array ROWS = [
        1 => ['id' => 1, 're_type' => 2, 're_value1' => 250000, 're_value2' => 0, 'use_int' => 100, 'type' => 1, 'num' => 20],
        2 => ['id' => 2, 're_type' => 11, 're_value1' => 30, 're_value2' => 0, 'use_int' => 100, 'type' => 1, 'num' => 20],
        3 => ['id' => 3, 're_type' => 3, 're_value1' => 15, 're_value2' => 0, 'use_int' => 100, 'type' => 1, 'num' => 20],
        4 => ['id' => 4, 're_type' => 5, 're_value1' => 50, 're_value2' => 0, 'use_int' => 100, 'type' => 1, 'num' => 20],
        5 => ['id' => 5, 're_type' => 6, 're_value1' => 2500, 're_value2' => 0, 'use_int' => 100, 'type' => 1, 'num' => 20],
        6 => ['id' => 6, 're_type' => 9, 're_value1' => 250, 're_value2' => 0, 'use_int' => 100, 'type' => 1, 'num' => 20],
        7 => ['id' => 7, 're_type' => 4, 're_value1' => 5, 're_value2' => 0, 'use_int' => 200, 'type' => 1, 'num' => 20],
        8 => ['id' => 8, 're_type' => 2, 're_value1' => 5000000, 're_value2' => 0, 'use_int' => 2000, 'type' => 1, 'num' => 5],
        9 => ['id' => 9, 're_type' => 11, 're_value1' => 600, 're_value2' => 0, 'use_int' => 2000, 'type' => 1, 'num' => 5],
        10 => ['id' => 10, 're_type' => 3, 're_value1' => 300, 're_value2' => 0, 'use_int' => 2000, 'type' => 1, 'num' => 5],
        11 => ['id' => 11, 're_type' => 4, 're_value1' => 50, 're_value2' => 0, 'use_int' => 2000, 'type' => 1, 'num' => 5],
        12 => ['id' => 12, 're_type' => 5, 're_value1' => 1000, 're_value2' => 0, 'use_int' => 2000, 'type' => 1, 'num' => 5],
        13 => ['id' => 13, 're_type' => 6, 're_value1' => 50000, 're_value2' => 0, 'use_int' => 2000, 'type' => 1, 'num' => 5],
        14 => ['id' => 14, 're_type' => 9, 're_value1' => 5000, 're_value2' => 0, 'use_int' => 2000, 'type' => 1, 'num' => 5],
        15 => ['id' => 15, 're_type' => 8, 're_value1' => 45, 're_value2' => 0, 'use_int' => 400, 'type' => 2, 'num' => 1],
        16 => ['id' => 16, 're_type' => 8, 're_value1' => 46, 're_value2' => 0, 'use_int' => 400, 'type' => 2, 'num' => 1],
        17 => ['id' => 17, 're_type' => 8, 're_value1' => 27, 're_value2' => 0, 'use_int' => 600, 'type' => 2, 'num' => 1],
        18 => ['id' => 18, 're_type' => 8, 're_value1' => 44, 're_value2' => 0, 'use_int' => 600, 'type' => 2, 'num' => 1],
        19 => ['id' => 19, 're_type' => 27, 're_value1' => 23, 're_value2' => 6, 'use_int' => 1200, 'type' => 2, 'num' => 1],
        20 => ['id' => 20, 're_type' => 27, 're_value1' => 28, 're_value2' => 6, 'use_int' => 1200, 'type' => 2, 'num' => 1],
        21 => ['id' => 21, 're_type' => 27, 're_value1' => 33, 're_value2' => 6, 'use_int' => 1200, 'type' => 2, 'num' => 1],
        22 => ['id' => 22, 're_type' => 27, 're_value1' => 38, 're_value2' => 6, 'use_int' => 1200, 'type' => 2, 'num' => 1],
        23 => ['id' => 23, 're_type' => 8, 're_value1' => 47, 're_value2' => 0, 'use_int' => 1200, 'type' => 2, 'num' => 1],
        24 => ['id' => 24, 're_type' => 8, 're_value1' => 48, 're_value2' => 0, 'use_int' => 1200, 'type' => 2, 'num' => 1],
        25 => ['id' => 25, 're_type' => 8, 're_value1' => 49, 're_value2' => 0, 'use_int' => 2000, 'type' => 2, 'num' => 1],
        26 => ['id' => 26, 're_type' => 8, 're_value1' => 50, 're_value2' => 0, 'use_int' => 2000, 'type' => 2, 'num' => 1],
        27 => ['id' => 27, 're_type' => 28, 're_value1' => 1, 're_value2' => 5, 'use_int' => 4000, 'type' => 2, 'num' => 1],
        28 => ['id' => 28, 're_type' => 28, 're_value1' => 2, 're_value2' => 5, 'use_int' => 4000, 'type' => 2, 'num' => 1],
        29 => ['id' => 29, 're_type' => 28, 're_value1' => 3, 're_value2' => 5, 'use_int' => 4000, 'type' => 2, 'num' => 1],
        30 => ['id' => 30, 're_type' => 28, 're_value1' => 4, 're_value2' => 5, 'use_int' => 4000, 'type' => 2, 'num' => 1],
        31 => ['id' => 31, 're_type' => 26, 're_value1' => 6, 're_value2' => 0, 'use_int' => 12000, 'type' => 2, 'num' => 1],
        32 => ['id' => 32, 're_type' => 12, 're_value1' => 30, 're_value2' => 0, 'use_int' => 12000, 'type' => 2, 'num' => 1],
        33 => ['id' => 33, 're_type' => 4, 're_value1' => 500, 're_value2' => 0, 'use_int' => 20000, 'type' => 2, 'num' => 1],
        34 => ['id' => 34, 're_type' => 26, 're_value1' => 10, 're_value2' => 0, 'use_int' => 24000, 'type' => 2, 'num' => 1],
        35 => ['id' => 35, 're_type' => 12, 're_value1' => 68, 're_value2' => 0, 'use_int' => 24000, 'type' => 2, 'num' => 1],
        36 => ['id' => 36, 're_type' => 26, 're_value1' => 14, 're_value2' => 0, 'use_int' => 36000, 'type' => 2, 'num' => 1],
        37 => ['id' => 37, 're_type' => 12, 're_value1' => 83, 're_value2' => 0, 'use_int' => 36000, 'type' => 2, 'num' => 1],
        38 => ['id' => 38, 're_type' => 7, 're_value1' => 200000, 're_value2' => 0, 'use_int' => 40000, 'type' => 2, 'num' => 1],
    ];
}
