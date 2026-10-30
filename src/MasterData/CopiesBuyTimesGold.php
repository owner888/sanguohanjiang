<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 副本购买次数元宝花费数据表（旧版 zc_sanguo_copies_buy_times_gold）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class CopiesBuyTimesGold
{
    public const string TABLE = 'zc_sanguo_copies_buy_times_gold';

    /**
     * 按主键 id 索引，共 100 行。字段：
     *   - id (int) 自增ID
     *   - times (int) 购买次数
     *   - gold (int) 花费元宝
     */
    public const array ROWS = [
        1 => ['id' => 1, 'times' => 1, 'gold' => 2],
        2 => ['id' => 2, 'times' => 2, 'gold' => 4],
        3 => ['id' => 3, 'times' => 3, 'gold' => 6],
        4 => ['id' => 4, 'times' => 4, 'gold' => 8],
        5 => ['id' => 5, 'times' => 5, 'gold' => 10],
        6 => ['id' => 6, 'times' => 6, 'gold' => 11],
        7 => ['id' => 7, 'times' => 7, 'gold' => 12],
        8 => ['id' => 8, 'times' => 8, 'gold' => 13],
        9 => ['id' => 9, 'times' => 9, 'gold' => 14],
        10 => ['id' => 10, 'times' => 10, 'gold' => 15],
        11 => ['id' => 11, 'times' => 11, 'gold' => 16],
        12 => ['id' => 12, 'times' => 12, 'gold' => 17],
        13 => ['id' => 13, 'times' => 13, 'gold' => 18],
        14 => ['id' => 14, 'times' => 14, 'gold' => 19],
        15 => ['id' => 15, 'times' => 15, 'gold' => 20],
        16 => ['id' => 16, 'times' => 16, 'gold' => 21],
        17 => ['id' => 17, 'times' => 17, 'gold' => 22],
        18 => ['id' => 18, 'times' => 18, 'gold' => 23],
        19 => ['id' => 19, 'times' => 19, 'gold' => 24],
        20 => ['id' => 20, 'times' => 20, 'gold' => 25],
        21 => ['id' => 21, 'times' => 21, 'gold' => 26],
        22 => ['id' => 22, 'times' => 22, 'gold' => 27],
        23 => ['id' => 23, 'times' => 23, 'gold' => 28],
        24 => ['id' => 24, 'times' => 24, 'gold' => 29],
        25 => ['id' => 25, 'times' => 25, 'gold' => 30],
        26 => ['id' => 26, 'times' => 26, 'gold' => 31],
        27 => ['id' => 27, 'times' => 27, 'gold' => 32],
        28 => ['id' => 28, 'times' => 28, 'gold' => 33],
        29 => ['id' => 29, 'times' => 29, 'gold' => 34],
        30 => ['id' => 30, 'times' => 30, 'gold' => 35],
        31 => ['id' => 31, 'times' => 31, 'gold' => 36],
        32 => ['id' => 32, 'times' => 32, 'gold' => 37],
        33 => ['id' => 33, 'times' => 33, 'gold' => 38],
        34 => ['id' => 34, 'times' => 34, 'gold' => 39],
        35 => ['id' => 35, 'times' => 35, 'gold' => 40],
        36 => ['id' => 36, 'times' => 36, 'gold' => 41],
        37 => ['id' => 37, 'times' => 37, 'gold' => 42],
        38 => ['id' => 38, 'times' => 38, 'gold' => 43],
        39 => ['id' => 39, 'times' => 39, 'gold' => 44],
        40 => ['id' => 40, 'times' => 40, 'gold' => 45],
        41 => ['id' => 41, 'times' => 41, 'gold' => 46],
        42 => ['id' => 42, 'times' => 42, 'gold' => 47],
        43 => ['id' => 43, 'times' => 43, 'gold' => 48],
        44 => ['id' => 44, 'times' => 44, 'gold' => 49],
        45 => ['id' => 45, 'times' => 45, 'gold' => 50],
        46 => ['id' => 46, 'times' => 46, 'gold' => 50],
        47 => ['id' => 47, 'times' => 47, 'gold' => 50],
        48 => ['id' => 48, 'times' => 48, 'gold' => 50],
        49 => ['id' => 49, 'times' => 49, 'gold' => 50],
        50 => ['id' => 50, 'times' => 50, 'gold' => 50],
        51 => ['id' => 51, 'times' => 51, 'gold' => 50],
        52 => ['id' => 52, 'times' => 52, 'gold' => 50],
        53 => ['id' => 53, 'times' => 53, 'gold' => 50],
        54 => ['id' => 54, 'times' => 54, 'gold' => 50],
        55 => ['id' => 55, 'times' => 55, 'gold' => 50],
        56 => ['id' => 56, 'times' => 56, 'gold' => 50],
        57 => ['id' => 57, 'times' => 57, 'gold' => 50],
        58 => ['id' => 58, 'times' => 58, 'gold' => 50],
        59 => ['id' => 59, 'times' => 59, 'gold' => 50],
        60 => ['id' => 60, 'times' => 60, 'gold' => 50],
        61 => ['id' => 61, 'times' => 61, 'gold' => 50],
        62 => ['id' => 62, 'times' => 62, 'gold' => 50],
        63 => ['id' => 63, 'times' => 63, 'gold' => 50],
        64 => ['id' => 64, 'times' => 64, 'gold' => 50],
        65 => ['id' => 65, 'times' => 65, 'gold' => 50],
        66 => ['id' => 66, 'times' => 66, 'gold' => 50],
        67 => ['id' => 67, 'times' => 67, 'gold' => 50],
        68 => ['id' => 68, 'times' => 68, 'gold' => 50],
        69 => ['id' => 69, 'times' => 69, 'gold' => 50],
        70 => ['id' => 70, 'times' => 70, 'gold' => 50],
        71 => ['id' => 71, 'times' => 71, 'gold' => 50],
        72 => ['id' => 72, 'times' => 72, 'gold' => 50],
        73 => ['id' => 73, 'times' => 73, 'gold' => 50],
        74 => ['id' => 74, 'times' => 74, 'gold' => 50],
        75 => ['id' => 75, 'times' => 75, 'gold' => 50],
        76 => ['id' => 76, 'times' => 76, 'gold' => 50],
        77 => ['id' => 77, 'times' => 77, 'gold' => 50],
        78 => ['id' => 78, 'times' => 78, 'gold' => 50],
        79 => ['id' => 79, 'times' => 79, 'gold' => 50],
        80 => ['id' => 80, 'times' => 80, 'gold' => 50],
        81 => ['id' => 81, 'times' => 81, 'gold' => 50],
        82 => ['id' => 82, 'times' => 82, 'gold' => 50],
        83 => ['id' => 83, 'times' => 83, 'gold' => 50],
        84 => ['id' => 84, 'times' => 84, 'gold' => 50],
        85 => ['id' => 85, 'times' => 85, 'gold' => 50],
        86 => ['id' => 86, 'times' => 86, 'gold' => 50],
        87 => ['id' => 87, 'times' => 87, 'gold' => 50],
        88 => ['id' => 88, 'times' => 88, 'gold' => 50],
        89 => ['id' => 89, 'times' => 89, 'gold' => 50],
        90 => ['id' => 90, 'times' => 90, 'gold' => 50],
        91 => ['id' => 91, 'times' => 91, 'gold' => 50],
        92 => ['id' => 92, 'times' => 92, 'gold' => 50],
        93 => ['id' => 93, 'times' => 93, 'gold' => 50],
        94 => ['id' => 94, 'times' => 94, 'gold' => 50],
        95 => ['id' => 95, 'times' => 95, 'gold' => 50],
        96 => ['id' => 96, 'times' => 96, 'gold' => 50],
        97 => ['id' => 97, 'times' => 97, 'gold' => 50],
        98 => ['id' => 98, 'times' => 98, 'gold' => 50],
        99 => ['id' => 99, 'times' => 99, 'gold' => 50],
        100 => ['id' => 100, 'times' => 100, 'gold' => 50],
    ];
}
