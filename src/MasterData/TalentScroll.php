<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 天赋卷轴模版表（旧版 zc_sanguo_talent_scroll）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class TalentScroll
{
    public const string TABLE = 'zc_sanguo_talent_scroll';

    /**
     * 按主键 id 索引，共 35 行。字段：
     *   - id (smallint) 卷轴id
     *   - type (tinyint) 类型(1物攻,2物防，3策攻，4策防，5生命)
     *   - quality (tinyint) 品级（白，绿，蓝，金，紫）
     *   - price (int) 售价
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => 1, 'quality' => 1, 'price' => 1000],
        2 => ['id' => 2, 'type' => 2, 'quality' => 1, 'price' => 1000],
        3 => ['id' => 3, 'type' => 3, 'quality' => 1, 'price' => 1000],
        4 => ['id' => 4, 'type' => 4, 'quality' => 1, 'price' => 1000],
        5 => ['id' => 5, 'type' => 5, 'quality' => 1, 'price' => 1000],
        6 => ['id' => 6, 'type' => 1, 'quality' => 2, 'price' => 2000],
        7 => ['id' => 7, 'type' => 2, 'quality' => 2, 'price' => 2000],
        8 => ['id' => 8, 'type' => 3, 'quality' => 2, 'price' => 2000],
        9 => ['id' => 9, 'type' => 4, 'quality' => 2, 'price' => 2000],
        10 => ['id' => 10, 'type' => 5, 'quality' => 2, 'price' => 2000],
        11 => ['id' => 11, 'type' => 1, 'quality' => 3, 'price' => 6000],
        12 => ['id' => 12, 'type' => 2, 'quality' => 3, 'price' => 6000],
        13 => ['id' => 13, 'type' => 3, 'quality' => 3, 'price' => 6000],
        14 => ['id' => 14, 'type' => 4, 'quality' => 3, 'price' => 6000],
        15 => ['id' => 15, 'type' => 5, 'quality' => 3, 'price' => 6000],
        16 => ['id' => 16, 'type' => 1, 'quality' => 4, 'price' => 20000],
        17 => ['id' => 17, 'type' => 2, 'quality' => 4, 'price' => 20000],
        18 => ['id' => 18, 'type' => 3, 'quality' => 4, 'price' => 20000],
        19 => ['id' => 19, 'type' => 4, 'quality' => 4, 'price' => 20000],
        20 => ['id' => 20, 'type' => 5, 'quality' => 4, 'price' => 20000],
        21 => ['id' => 21, 'type' => 1, 'quality' => 5, 'price' => 50000],
        22 => ['id' => 22, 'type' => 2, 'quality' => 5, 'price' => 50000],
        23 => ['id' => 23, 'type' => 3, 'quality' => 5, 'price' => 50000],
        24 => ['id' => 24, 'type' => 4, 'quality' => 5, 'price' => 50000],
        25 => ['id' => 25, 'type' => 5, 'quality' => 5, 'price' => 50000],
        26 => ['id' => 26, 'type' => 1, 'quality' => 6, 'price' => 100000],
        27 => ['id' => 27, 'type' => 2, 'quality' => 6, 'price' => 100000],
        28 => ['id' => 28, 'type' => 3, 'quality' => 6, 'price' => 100000],
        29 => ['id' => 29, 'type' => 4, 'quality' => 6, 'price' => 100000],
        30 => ['id' => 30, 'type' => 5, 'quality' => 6, 'price' => 100000],
        31 => ['id' => 31, 'type' => 1, 'quality' => 7, 'price' => 240000],
        32 => ['id' => 32, 'type' => 2, 'quality' => 7, 'price' => 240000],
        33 => ['id' => 33, 'type' => 3, 'quality' => 7, 'price' => 240000],
        34 => ['id' => 34, 'type' => 4, 'quality' => 7, 'price' => 240000],
        35 => ['id' => 35, 'type' => 5, 'quality' => 7, 'price' => 240000],
    ];
}
