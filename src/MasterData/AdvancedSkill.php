<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 名将进阶小技能数据表（旧版 zc_sanguo_advanced_skill）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class AdvancedSkill
{
    public const string TABLE = 'zc_sanguo_advanced_skill';

    /**
     * 按主键 id 索引，共 100 行。字段：
     *   - id (int) id
     *   - name (varchar) 技能名称
     *   - level (int) 技能等级
     *   - use (int) 消耗突飞卡
     *   - type (int) 技能加成类型（1策攻物攻，2物防，3策防，4生命）
     *   - value (double) 加成参数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '好战', 'level' => 1, 'use' => 100, 'type' => 1, 'value' => 0.02],
        2 => ['id' => 2, 'name' => '好战', 'level' => 2, 'use' => 200, 'type' => 1, 'value' => 0.04],
        3 => ['id' => 3, 'name' => '好战', 'level' => 3, 'use' => 300, 'type' => 1, 'value' => 0.06],
        4 => ['id' => 4, 'name' => '好战', 'level' => 4, 'use' => 400, 'type' => 1, 'value' => 0.08],
        5 => ['id' => 5, 'name' => '好战', 'level' => 5, 'use' => 500, 'type' => 1, 'value' => 0.1],
        6 => ['id' => 6, 'name' => '好战', 'level' => 6, 'use' => 600, 'type' => 1, 'value' => 0.12],
        7 => ['id' => 7, 'name' => '好战', 'level' => 7, 'use' => 700, 'type' => 1, 'value' => 0.14],
        8 => ['id' => 8, 'name' => '好战', 'level' => 8, 'use' => 800, 'type' => 1, 'value' => 0.16],
        9 => ['id' => 9, 'name' => '好战', 'level' => 9, 'use' => 900, 'type' => 1, 'value' => 0.18],
        10 => ['id' => 10, 'name' => '好战', 'level' => 10, 'use' => 1000, 'type' => 1, 'value' => 0.22],
        11 => ['id' => 11, 'name' => '好战', 'level' => 11, 'use' => 1100, 'type' => 1, 'value' => 0.26],
        12 => ['id' => 12, 'name' => '好战', 'level' => 12, 'use' => 1200, 'type' => 1, 'value' => 0.3],
        13 => ['id' => 13, 'name' => '好战', 'level' => 13, 'use' => 1300, 'type' => 1, 'value' => 0.36],
        14 => ['id' => 14, 'name' => '好战', 'level' => 14, 'use' => 1400, 'type' => 1, 'value' => 0.42],
        15 => ['id' => 15, 'name' => '好战', 'level' => 15, 'use' => 1500, 'type' => 1, 'value' => 0.5],
        16 => ['id' => 16, 'name' => '好战', 'level' => 16, 'use' => 2000, 'type' => 1, 'value' => 0.58],
        17 => ['id' => 17, 'name' => '好战', 'level' => 17, 'use' => 2500, 'type' => 1, 'value' => 0.66],
        18 => ['id' => 18, 'name' => '好战', 'level' => 18, 'use' => 3000, 'type' => 1, 'value' => 0.76],
        19 => ['id' => 19, 'name' => '好战', 'level' => 19, 'use' => 3500, 'type' => 1, 'value' => 0.86],
        20 => ['id' => 20, 'name' => '好战', 'level' => 20, 'use' => 5000, 'type' => 1, 'value' => 1.0],
        21 => ['id' => 21, 'name' => '坚强', 'level' => 1, 'use' => 50, 'type' => 2, 'value' => 0.02],
        22 => ['id' => 22, 'name' => '坚强', 'level' => 2, 'use' => 100, 'type' => 2, 'value' => 0.04],
        23 => ['id' => 23, 'name' => '坚强', 'level' => 3, 'use' => 150, 'type' => 2, 'value' => 0.06],
        24 => ['id' => 24, 'name' => '坚强', 'level' => 4, 'use' => 200, 'type' => 2, 'value' => 0.08],
        25 => ['id' => 25, 'name' => '坚强', 'level' => 5, 'use' => 250, 'type' => 2, 'value' => 0.1],
        26 => ['id' => 26, 'name' => '坚强', 'level' => 6, 'use' => 300, 'type' => 2, 'value' => 0.12],
        27 => ['id' => 27, 'name' => '坚强', 'level' => 7, 'use' => 350, 'type' => 2, 'value' => 0.14],
        28 => ['id' => 28, 'name' => '坚强', 'level' => 8, 'use' => 400, 'type' => 2, 'value' => 0.16],
        29 => ['id' => 29, 'name' => '坚强', 'level' => 9, 'use' => 450, 'type' => 2, 'value' => 0.18],
        30 => ['id' => 30, 'name' => '坚强', 'level' => 10, 'use' => 500, 'type' => 2, 'value' => 0.22],
        31 => ['id' => 31, 'name' => '坚强', 'level' => 11, 'use' => 550, 'type' => 2, 'value' => 0.26],
        32 => ['id' => 32, 'name' => '坚强', 'level' => 12, 'use' => 600, 'type' => 2, 'value' => 0.3],
        33 => ['id' => 33, 'name' => '坚强', 'level' => 13, 'use' => 650, 'type' => 2, 'value' => 0.36],
        34 => ['id' => 34, 'name' => '坚强', 'level' => 14, 'use' => 700, 'type' => 2, 'value' => 0.42],
        35 => ['id' => 35, 'name' => '坚强', 'level' => 15, 'use' => 750, 'type' => 2, 'value' => 0.5],
        36 => ['id' => 36, 'name' => '坚强', 'level' => 16, 'use' => 1000, 'type' => 2, 'value' => 0.58],
        37 => ['id' => 37, 'name' => '坚强', 'level' => 17, 'use' => 1250, 'type' => 2, 'value' => 0.66],
        38 => ['id' => 38, 'name' => '坚强', 'level' => 18, 'use' => 1500, 'type' => 2, 'value' => 0.76],
        39 => ['id' => 39, 'name' => '坚强', 'level' => 19, 'use' => 1750, 'type' => 2, 'value' => 0.86],
        40 => ['id' => 40, 'name' => '坚强', 'level' => 20, 'use' => 2500, 'type' => 2, 'value' => 1.0],
        41 => ['id' => 41, 'name' => '睿智', 'level' => 1, 'use' => 50, 'type' => 3, 'value' => 0.02],
        42 => ['id' => 42, 'name' => '睿智', 'level' => 2, 'use' => 100, 'type' => 3, 'value' => 0.04],
        43 => ['id' => 43, 'name' => '睿智', 'level' => 3, 'use' => 150, 'type' => 3, 'value' => 0.06],
        44 => ['id' => 44, 'name' => '睿智', 'level' => 4, 'use' => 200, 'type' => 3, 'value' => 0.08],
        45 => ['id' => 45, 'name' => '睿智', 'level' => 5, 'use' => 250, 'type' => 3, 'value' => 0.1],
        46 => ['id' => 46, 'name' => '睿智', 'level' => 6, 'use' => 300, 'type' => 3, 'value' => 0.12],
        47 => ['id' => 47, 'name' => '睿智', 'level' => 7, 'use' => 350, 'type' => 3, 'value' => 0.14],
        48 => ['id' => 48, 'name' => '睿智', 'level' => 8, 'use' => 400, 'type' => 3, 'value' => 0.16],
        49 => ['id' => 49, 'name' => '睿智', 'level' => 9, 'use' => 450, 'type' => 3, 'value' => 0.18],
        50 => ['id' => 50, 'name' => '睿智', 'level' => 10, 'use' => 500, 'type' => 3, 'value' => 0.22],
        51 => ['id' => 51, 'name' => '睿智', 'level' => 11, 'use' => 550, 'type' => 3, 'value' => 0.26],
        52 => ['id' => 52, 'name' => '睿智', 'level' => 12, 'use' => 600, 'type' => 3, 'value' => 0.3],
        53 => ['id' => 53, 'name' => '睿智', 'level' => 13, 'use' => 650, 'type' => 3, 'value' => 0.36],
        54 => ['id' => 54, 'name' => '睿智', 'level' => 14, 'use' => 700, 'type' => 3, 'value' => 0.42],
        55 => ['id' => 55, 'name' => '睿智', 'level' => 15, 'use' => 750, 'type' => 3, 'value' => 0.5],
        56 => ['id' => 56, 'name' => '睿智', 'level' => 16, 'use' => 1000, 'type' => 3, 'value' => 0.58],
        57 => ['id' => 57, 'name' => '睿智', 'level' => 17, 'use' => 1250, 'type' => 3, 'value' => 0.66],
        58 => ['id' => 58, 'name' => '睿智', 'level' => 18, 'use' => 1500, 'type' => 3, 'value' => 0.76],
        59 => ['id' => 59, 'name' => '睿智', 'level' => 19, 'use' => 1750, 'type' => 3, 'value' => 0.86],
        60 => ['id' => 60, 'name' => '睿智', 'level' => 20, 'use' => 2500, 'type' => 3, 'value' => 1.0],
        61 => ['id' => 61, 'name' => '强壮', 'level' => 1, 'use' => 100, 'type' => 4, 'value' => 0.02],
        62 => ['id' => 62, 'name' => '强壮', 'level' => 2, 'use' => 200, 'type' => 4, 'value' => 0.04],
        63 => ['id' => 63, 'name' => '强壮', 'level' => 3, 'use' => 300, 'type' => 4, 'value' => 0.06],
        64 => ['id' => 64, 'name' => '强壮', 'level' => 4, 'use' => 400, 'type' => 4, 'value' => 0.08],
        65 => ['id' => 65, 'name' => '强壮', 'level' => 5, 'use' => 500, 'type' => 4, 'value' => 0.1],
        66 => ['id' => 66, 'name' => '强壮', 'level' => 6, 'use' => 600, 'type' => 4, 'value' => 0.12],
        67 => ['id' => 67, 'name' => '强壮', 'level' => 7, 'use' => 700, 'type' => 4, 'value' => 0.14],
        68 => ['id' => 68, 'name' => '强壮', 'level' => 8, 'use' => 800, 'type' => 4, 'value' => 0.16],
        69 => ['id' => 69, 'name' => '强壮', 'level' => 9, 'use' => 900, 'type' => 4, 'value' => 0.18],
        70 => ['id' => 70, 'name' => '强壮', 'level' => 10, 'use' => 1000, 'type' => 4, 'value' => 0.22],
        71 => ['id' => 71, 'name' => '强壮', 'level' => 11, 'use' => 1100, 'type' => 4, 'value' => 0.26],
        72 => ['id' => 72, 'name' => '强壮', 'level' => 12, 'use' => 1200, 'type' => 4, 'value' => 0.3],
        73 => ['id' => 73, 'name' => '强壮', 'level' => 13, 'use' => 1300, 'type' => 4, 'value' => 0.36],
        74 => ['id' => 74, 'name' => '强壮', 'level' => 14, 'use' => 1400, 'type' => 4, 'value' => 0.42],
        75 => ['id' => 75, 'name' => '强壮', 'level' => 15, 'use' => 1500, 'type' => 4, 'value' => 0.5],
        76 => ['id' => 76, 'name' => '强壮', 'level' => 16, 'use' => 2000, 'type' => 4, 'value' => 0.58],
        77 => ['id' => 77, 'name' => '强壮', 'level' => 17, 'use' => 2500, 'type' => 4, 'value' => 0.66],
        78 => ['id' => 78, 'name' => '强壮', 'level' => 18, 'use' => 3000, 'type' => 4, 'value' => 0.76],
        79 => ['id' => 79, 'name' => '强壮', 'level' => 19, 'use' => 3500, 'type' => 4, 'value' => 0.86],
        80 => ['id' => 80, 'name' => '强壮', 'level' => 20, 'use' => 5000, 'type' => 4, 'value' => 1.0],
        81 => ['id' => 81, 'name' => '好战', 'level' => 21, 'use' => 6000, 'type' => 1, 'value' => 1.2],
        82 => ['id' => 82, 'name' => '好战', 'level' => 22, 'use' => 7000, 'type' => 1, 'value' => 1.4],
        83 => ['id' => 83, 'name' => '好战', 'level' => 23, 'use' => 8000, 'type' => 1, 'value' => 1.6],
        84 => ['id' => 84, 'name' => '好战', 'level' => 24, 'use' => 9000, 'type' => 1, 'value' => 1.8],
        85 => ['id' => 85, 'name' => '好战', 'level' => 25, 'use' => 10000, 'type' => 1, 'value' => 2.0],
        86 => ['id' => 86, 'name' => '坚强', 'level' => 21, 'use' => 3000, 'type' => 2, 'value' => 1.2],
        87 => ['id' => 87, 'name' => '坚强', 'level' => 22, 'use' => 3500, 'type' => 2, 'value' => 1.4],
        88 => ['id' => 88, 'name' => '坚强', 'level' => 23, 'use' => 4000, 'type' => 2, 'value' => 1.6],
        89 => ['id' => 89, 'name' => '坚强', 'level' => 24, 'use' => 4500, 'type' => 2, 'value' => 1.8],
        90 => ['id' => 90, 'name' => '坚强', 'level' => 25, 'use' => 5000, 'type' => 2, 'value' => 2.0],
        91 => ['id' => 91, 'name' => '睿智', 'level' => 21, 'use' => 3000, 'type' => 3, 'value' => 1.2],
        92 => ['id' => 92, 'name' => '睿智', 'level' => 22, 'use' => 3500, 'type' => 3, 'value' => 1.4],
        93 => ['id' => 93, 'name' => '睿智', 'level' => 23, 'use' => 4000, 'type' => 3, 'value' => 1.6],
        94 => ['id' => 94, 'name' => '睿智', 'level' => 24, 'use' => 4500, 'type' => 3, 'value' => 1.8],
        95 => ['id' => 95, 'name' => '睿智', 'level' => 25, 'use' => 5000, 'type' => 3, 'value' => 2.0],
        96 => ['id' => 96, 'name' => '强壮', 'level' => 21, 'use' => 6000, 'type' => 4, 'value' => 1.2],
        97 => ['id' => 97, 'name' => '强壮', 'level' => 22, 'use' => 7000, 'type' => 4, 'value' => 1.4],
        98 => ['id' => 98, 'name' => '强壮', 'level' => 23, 'use' => 8000, 'type' => 4, 'value' => 1.6],
        99 => ['id' => 99, 'name' => '强壮', 'level' => 24, 'use' => 9000, 'type' => 4, 'value' => 1.8],
        100 => ['id' => 100, 'name' => '强壮', 'level' => 25, 'use' => 10000, 'type' => 4, 'value' => 2.0],
    ];
}
