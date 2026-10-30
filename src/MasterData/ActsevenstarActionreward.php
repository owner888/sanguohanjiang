<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 点灯奖励表（旧版 zc_sanguo_actsevenstar_actionreward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActsevenstarActionreward
{
    public const string TABLE = 'zc_sanguo_actsevenstar_actionreward';

    /**
     * 按主键 id 索引，共 98 行。字段：
     *   - id (int) 自增ID
     *   - type (int) 类型
     *   - value (int) 值
     *   - num (int) 数量
     *   - lid (int) 灯位置
     *   - rate (int) 权重
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => 5, 'value' => 0, 'num' => 200, 'lid' => 1, 'rate' => 19],
        2 => ['id' => 2, 'type' => 9, 'value' => 0, 'num' => 3000, 'lid' => 1, 'rate' => 19],
        3 => ['id' => 3, 'type' => 13, 'value' => 0, 'num' => 3, 'lid' => 1, 'rate' => 19],
        4 => ['id' => 4, 'type' => 29, 'value' => 21, 'num' => 1, 'lid' => 1, 'rate' => 8],
        5 => ['id' => 5, 'type' => 29, 'value' => 22, 'num' => 1, 'lid' => 1, 'rate' => 4],
        6 => ['id' => 6, 'type' => 29, 'value' => 23, 'num' => 1, 'lid' => 1, 'rate' => 4],
        7 => ['id' => 7, 'type' => 29, 'value' => 43, 'num' => 1, 'lid' => 1, 'rate' => 8],
        8 => ['id' => 8, 'type' => 29, 'value' => 30, 'num' => 1, 'lid' => 1, 'rate' => 0],
        9 => ['id' => 9, 'type' => 29, 'value' => 31, 'num' => 1, 'lid' => 1, 'rate' => 0],
        10 => ['id' => 10, 'type' => 29, 'value' => 35, 'num' => 1, 'lid' => 1, 'rate' => 0],
        11 => ['id' => 11, 'type' => 29, 'value' => 39, 'num' => 1, 'lid' => 1, 'rate' => 0],
        12 => ['id' => 12, 'type' => 29, 'value' => 45, 'num' => 1, 'lid' => 1, 'rate' => 0],
        13 => ['id' => 13, 'type' => 30, 'value' => 0, 'num' => 1, 'lid' => 1, 'rate' => 0],
        14 => ['id' => 14, 'type' => 10, 'value' => 0, 'num' => 20, 'lid' => 1, 'rate' => 18],
        15 => ['id' => 15, 'type' => 5, 'value' => 0, 'num' => 400, 'lid' => 2, 'rate' => 19],
        16 => ['id' => 16, 'type' => 9, 'value' => 0, 'num' => 6000, 'lid' => 2, 'rate' => 19],
        17 => ['id' => 17, 'type' => 13, 'value' => 0, 'num' => 5, 'lid' => 2, 'rate' => 19],
        18 => ['id' => 18, 'type' => 29, 'value' => 21, 'num' => 1, 'lid' => 2, 'rate' => 8],
        19 => ['id' => 19, 'type' => 29, 'value' => 22, 'num' => 1, 'lid' => 2, 'rate' => 4],
        20 => ['id' => 20, 'type' => 29, 'value' => 23, 'num' => 1, 'lid' => 2, 'rate' => 4],
        21 => ['id' => 21, 'type' => 29, 'value' => 43, 'num' => 1, 'lid' => 2, 'rate' => 8],
        22 => ['id' => 22, 'type' => 29, 'value' => 30, 'num' => 1, 'lid' => 2, 'rate' => 0],
        23 => ['id' => 23, 'type' => 29, 'value' => 31, 'num' => 1, 'lid' => 2, 'rate' => 0],
        24 => ['id' => 24, 'type' => 29, 'value' => 35, 'num' => 1, 'lid' => 2, 'rate' => 0],
        25 => ['id' => 25, 'type' => 29, 'value' => 39, 'num' => 1, 'lid' => 2, 'rate' => 0],
        26 => ['id' => 26, 'type' => 29, 'value' => 45, 'num' => 1, 'lid' => 2, 'rate' => 0],
        27 => ['id' => 27, 'type' => 30, 'value' => 0, 'num' => 1, 'lid' => 2, 'rate' => 1],
        28 => ['id' => 28, 'type' => 10, 'value' => 0, 'num' => 40, 'lid' => 2, 'rate' => 18],
        29 => ['id' => 29, 'type' => 5, 'value' => 0, 'num' => 600, 'lid' => 3, 'rate' => 19],
        30 => ['id' => 30, 'type' => 9, 'value' => 0, 'num' => 9000, 'lid' => 3, 'rate' => 19],
        31 => ['id' => 31, 'type' => 13, 'value' => 0, 'num' => 8, 'lid' => 3, 'rate' => 19],
        32 => ['id' => 32, 'type' => 29, 'value' => 21, 'num' => 1, 'lid' => 3, 'rate' => 8],
        33 => ['id' => 33, 'type' => 29, 'value' => 22, 'num' => 1, 'lid' => 3, 'rate' => 4],
        34 => ['id' => 34, 'type' => 29, 'value' => 23, 'num' => 1, 'lid' => 3, 'rate' => 4],
        35 => ['id' => 35, 'type' => 29, 'value' => 43, 'num' => 1, 'lid' => 3, 'rate' => 8],
        36 => ['id' => 36, 'type' => 29, 'value' => 30, 'num' => 1, 'lid' => 3, 'rate' => 0],
        37 => ['id' => 37, 'type' => 29, 'value' => 31, 'num' => 1, 'lid' => 3, 'rate' => 0],
        38 => ['id' => 38, 'type' => 29, 'value' => 35, 'num' => 1, 'lid' => 3, 'rate' => 0],
        39 => ['id' => 39, 'type' => 29, 'value' => 39, 'num' => 1, 'lid' => 3, 'rate' => 0],
        40 => ['id' => 40, 'type' => 29, 'value' => 45, 'num' => 1, 'lid' => 3, 'rate' => 0],
        41 => ['id' => 41, 'type' => 30, 'value' => 0, 'num' => 1, 'lid' => 3, 'rate' => 2],
        42 => ['id' => 42, 'type' => 10, 'value' => 0, 'num' => 60, 'lid' => 3, 'rate' => 18],
        43 => ['id' => 43, 'type' => 5, 'value' => 0, 'num' => 800, 'lid' => 4, 'rate' => 19],
        44 => ['id' => 44, 'type' => 9, 'value' => 0, 'num' => 12000, 'lid' => 4, 'rate' => 19],
        45 => ['id' => 45, 'type' => 13, 'value' => 0, 'num' => 10, 'lid' => 4, 'rate' => 19],
        46 => ['id' => 46, 'type' => 29, 'value' => 21, 'num' => 1, 'lid' => 4, 'rate' => 8],
        47 => ['id' => 47, 'type' => 29, 'value' => 22, 'num' => 1, 'lid' => 4, 'rate' => 4],
        48 => ['id' => 48, 'type' => 29, 'value' => 23, 'num' => 1, 'lid' => 4, 'rate' => 4],
        49 => ['id' => 49, 'type' => 29, 'value' => 43, 'num' => 1, 'lid' => 4, 'rate' => 8],
        50 => ['id' => 50, 'type' => 29, 'value' => 30, 'num' => 1, 'lid' => 4, 'rate' => 0],
        51 => ['id' => 51, 'type' => 29, 'value' => 31, 'num' => 1, 'lid' => 4, 'rate' => 0],
        52 => ['id' => 52, 'type' => 29, 'value' => 35, 'num' => 1, 'lid' => 4, 'rate' => 0],
        53 => ['id' => 53, 'type' => 29, 'value' => 39, 'num' => 1, 'lid' => 4, 'rate' => 0],
        54 => ['id' => 54, 'type' => 29, 'value' => 45, 'num' => 1, 'lid' => 4, 'rate' => 0],
        55 => ['id' => 55, 'type' => 30, 'value' => 0, 'num' => 1, 'lid' => 4, 'rate' => 3],
        56 => ['id' => 56, 'type' => 10, 'value' => 0, 'num' => 80, 'lid' => 4, 'rate' => 18],
        57 => ['id' => 57, 'type' => 5, 'value' => 0, 'num' => 1000, 'lid' => 5, 'rate' => 19],
        58 => ['id' => 58, 'type' => 9, 'value' => 0, 'num' => 15000, 'lid' => 5, 'rate' => 19],
        59 => ['id' => 59, 'type' => 13, 'value' => 0, 'num' => 0, 'lid' => 5, 'rate' => 0],
        60 => ['id' => 60, 'type' => 29, 'value' => 21, 'num' => 1, 'lid' => 5, 'rate' => 8],
        61 => ['id' => 61, 'type' => 29, 'value' => 22, 'num' => 1, 'lid' => 5, 'rate' => 4],
        62 => ['id' => 62, 'type' => 29, 'value' => 23, 'num' => 1, 'lid' => 5, 'rate' => 4],
        63 => ['id' => 63, 'type' => 29, 'value' => 43, 'num' => 1, 'lid' => 5, 'rate' => 8],
        64 => ['id' => 64, 'type' => 29, 'value' => 30, 'num' => 1, 'lid' => 5, 'rate' => 4],
        65 => ['id' => 65, 'type' => 29, 'value' => 31, 'num' => 1, 'lid' => 5, 'rate' => 4],
        66 => ['id' => 66, 'type' => 29, 'value' => 35, 'num' => 1, 'lid' => 5, 'rate' => 2],
        67 => ['id' => 67, 'type' => 29, 'value' => 39, 'num' => 1, 'lid' => 5, 'rate' => 2],
        68 => ['id' => 68, 'type' => 29, 'value' => 45, 'num' => 1, 'lid' => 5, 'rate' => 4],
        69 => ['id' => 69, 'type' => 30, 'value' => 0, 'num' => 1, 'lid' => 5, 'rate' => 3],
        70 => ['id' => 70, 'type' => 10, 'value' => 0, 'num' => 100, 'lid' => 5, 'rate' => 18],
        71 => ['id' => 71, 'type' => 5, 'value' => 0, 'num' => 1200, 'lid' => 6, 'rate' => 19],
        72 => ['id' => 72, 'type' => 9, 'value' => 0, 'num' => 18000, 'lid' => 6, 'rate' => 19],
        73 => ['id' => 73, 'type' => 13, 'value' => 0, 'num' => 0, 'lid' => 6, 'rate' => 0],
        74 => ['id' => 74, 'type' => 29, 'value' => 21, 'num' => 1, 'lid' => 6, 'rate' => 8],
        75 => ['id' => 75, 'type' => 29, 'value' => 22, 'num' => 1, 'lid' => 6, 'rate' => 4],
        76 => ['id' => 76, 'type' => 29, 'value' => 23, 'num' => 1, 'lid' => 6, 'rate' => 4],
        77 => ['id' => 77, 'type' => 29, 'value' => 43, 'num' => 1, 'lid' => 6, 'rate' => 8],
        78 => ['id' => 78, 'type' => 29, 'value' => 30, 'num' => 1, 'lid' => 6, 'rate' => 4],
        79 => ['id' => 79, 'type' => 29, 'value' => 31, 'num' => 1, 'lid' => 6, 'rate' => 4],
        80 => ['id' => 80, 'type' => 29, 'value' => 35, 'num' => 1, 'lid' => 6, 'rate' => 2],
        81 => ['id' => 81, 'type' => 29, 'value' => 39, 'num' => 1, 'lid' => 6, 'rate' => 2],
        82 => ['id' => 82, 'type' => 29, 'value' => 45, 'num' => 1, 'lid' => 6, 'rate' => 4],
        83 => ['id' => 83, 'type' => 30, 'value' => 0, 'num' => 1, 'lid' => 6, 'rate' => 4],
        84 => ['id' => 84, 'type' => 10, 'value' => 0, 'num' => 120, 'lid' => 6, 'rate' => 18],
        85 => ['id' => 85, 'type' => 5, 'value' => 0, 'num' => 1500, 'lid' => 7, 'rate' => 19],
        86 => ['id' => 86, 'type' => 9, 'value' => 0, 'num' => 22000, 'lid' => 7, 'rate' => 19],
        87 => ['id' => 87, 'type' => 13, 'value' => 0, 'num' => 0, 'lid' => 7, 'rate' => 0],
        88 => ['id' => 88, 'type' => 29, 'value' => 21, 'num' => 1, 'lid' => 7, 'rate' => 8],
        89 => ['id' => 89, 'type' => 29, 'value' => 22, 'num' => 1, 'lid' => 7, 'rate' => 4],
        90 => ['id' => 90, 'type' => 29, 'value' => 23, 'num' => 1, 'lid' => 7, 'rate' => 4],
        91 => ['id' => 91, 'type' => 29, 'value' => 43, 'num' => 1, 'lid' => 7, 'rate' => 8],
        92 => ['id' => 92, 'type' => 29, 'value' => 30, 'num' => 1, 'lid' => 7, 'rate' => 4],
        93 => ['id' => 93, 'type' => 29, 'value' => 31, 'num' => 1, 'lid' => 7, 'rate' => 4],
        94 => ['id' => 94, 'type' => 29, 'value' => 35, 'num' => 1, 'lid' => 7, 'rate' => 2],
        95 => ['id' => 95, 'type' => 29, 'value' => 39, 'num' => 1, 'lid' => 7, 'rate' => 2],
        96 => ['id' => 96, 'type' => 29, 'value' => 45, 'num' => 1, 'lid' => 7, 'rate' => 4],
        97 => ['id' => 97, 'type' => 30, 'value' => 0, 'num' => 1, 'lid' => 7, 'rate' => 5],
        98 => ['id' => 98, 'type' => 10, 'value' => 0, 'num' => 140, 'lid' => 7, 'rate' => 18],
    ];
}
