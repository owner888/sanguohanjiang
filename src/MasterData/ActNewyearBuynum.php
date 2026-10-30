<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 春节送福次数购买表（旧版 zc_sanguo_act_newyear_buynum）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActNewyearBuynum
{
    public const string TABLE = 'zc_sanguo_act_newyear_buynum';

    /**
     * 按主键 id 索引，共 81 行。字段：
     *   - id (int) 自增ID
     *   - buynum (int) 购买次数
     *   - gold (int) 购买消费元宝
     */
    public const array ROWS = [
        1 => ['id' => 1, 'buynum' => 1, 'gold' => 10],
        2 => ['id' => 2, 'buynum' => 2, 'gold' => 11],
        3 => ['id' => 3, 'buynum' => 3, 'gold' => 12],
        4 => ['id' => 4, 'buynum' => 4, 'gold' => 13],
        5 => ['id' => 5, 'buynum' => 5, 'gold' => 14],
        6 => ['id' => 6, 'buynum' => 6, 'gold' => 15],
        7 => ['id' => 7, 'buynum' => 7, 'gold' => 16],
        8 => ['id' => 8, 'buynum' => 8, 'gold' => 17],
        9 => ['id' => 9, 'buynum' => 9, 'gold' => 18],
        10 => ['id' => 10, 'buynum' => 10, 'gold' => 19],
        11 => ['id' => 11, 'buynum' => 11, 'gold' => 20],
        12 => ['id' => 12, 'buynum' => 12, 'gold' => 21],
        13 => ['id' => 13, 'buynum' => 13, 'gold' => 22],
        14 => ['id' => 14, 'buynum' => 14, 'gold' => 23],
        15 => ['id' => 15, 'buynum' => 15, 'gold' => 24],
        16 => ['id' => 16, 'buynum' => 16, 'gold' => 25],
        17 => ['id' => 17, 'buynum' => 17, 'gold' => 26],
        18 => ['id' => 18, 'buynum' => 18, 'gold' => 27],
        19 => ['id' => 19, 'buynum' => 19, 'gold' => 28],
        20 => ['id' => 20, 'buynum' => 20, 'gold' => 29],
        21 => ['id' => 21, 'buynum' => 21, 'gold' => 30],
        22 => ['id' => 22, 'buynum' => 22, 'gold' => 31],
        23 => ['id' => 23, 'buynum' => 23, 'gold' => 32],
        24 => ['id' => 24, 'buynum' => 24, 'gold' => 33],
        25 => ['id' => 25, 'buynum' => 25, 'gold' => 34],
        26 => ['id' => 26, 'buynum' => 26, 'gold' => 35],
        27 => ['id' => 27, 'buynum' => 27, 'gold' => 36],
        28 => ['id' => 28, 'buynum' => 28, 'gold' => 37],
        29 => ['id' => 29, 'buynum' => 29, 'gold' => 38],
        30 => ['id' => 30, 'buynum' => 30, 'gold' => 39],
        31 => ['id' => 31, 'buynum' => 31, 'gold' => 40],
        32 => ['id' => 32, 'buynum' => 32, 'gold' => 41],
        33 => ['id' => 33, 'buynum' => 33, 'gold' => 42],
        34 => ['id' => 34, 'buynum' => 34, 'gold' => 43],
        35 => ['id' => 35, 'buynum' => 35, 'gold' => 44],
        36 => ['id' => 36, 'buynum' => 36, 'gold' => 45],
        37 => ['id' => 37, 'buynum' => 37, 'gold' => 46],
        38 => ['id' => 38, 'buynum' => 38, 'gold' => 47],
        39 => ['id' => 39, 'buynum' => 39, 'gold' => 48],
        40 => ['id' => 40, 'buynum' => 40, 'gold' => 49],
        41 => ['id' => 41, 'buynum' => 41, 'gold' => 50],
        42 => ['id' => 42, 'buynum' => 42, 'gold' => 51],
        43 => ['id' => 43, 'buynum' => 43, 'gold' => 52],
        44 => ['id' => 44, 'buynum' => 44, 'gold' => 53],
        45 => ['id' => 45, 'buynum' => 45, 'gold' => 54],
        46 => ['id' => 46, 'buynum' => 46, 'gold' => 55],
        47 => ['id' => 47, 'buynum' => 47, 'gold' => 56],
        48 => ['id' => 48, 'buynum' => 48, 'gold' => 57],
        49 => ['id' => 49, 'buynum' => 49, 'gold' => 58],
        50 => ['id' => 50, 'buynum' => 50, 'gold' => 59],
        51 => ['id' => 51, 'buynum' => 51, 'gold' => 60],
        52 => ['id' => 52, 'buynum' => 52, 'gold' => 61],
        53 => ['id' => 53, 'buynum' => 53, 'gold' => 62],
        54 => ['id' => 54, 'buynum' => 54, 'gold' => 63],
        55 => ['id' => 55, 'buynum' => 55, 'gold' => 64],
        56 => ['id' => 56, 'buynum' => 56, 'gold' => 65],
        57 => ['id' => 57, 'buynum' => 57, 'gold' => 66],
        58 => ['id' => 58, 'buynum' => 58, 'gold' => 67],
        59 => ['id' => 59, 'buynum' => 59, 'gold' => 68],
        60 => ['id' => 60, 'buynum' => 60, 'gold' => 69],
        61 => ['id' => 61, 'buynum' => 61, 'gold' => 70],
        62 => ['id' => 62, 'buynum' => 62, 'gold' => 71],
        63 => ['id' => 63, 'buynum' => 63, 'gold' => 72],
        64 => ['id' => 64, 'buynum' => 64, 'gold' => 73],
        65 => ['id' => 65, 'buynum' => 65, 'gold' => 74],
        66 => ['id' => 66, 'buynum' => 66, 'gold' => 75],
        67 => ['id' => 67, 'buynum' => 67, 'gold' => 76],
        68 => ['id' => 68, 'buynum' => 68, 'gold' => 77],
        69 => ['id' => 69, 'buynum' => 69, 'gold' => 78],
        70 => ['id' => 70, 'buynum' => 70, 'gold' => 79],
        71 => ['id' => 71, 'buynum' => 71, 'gold' => 80],
        72 => ['id' => 72, 'buynum' => 72, 'gold' => 81],
        73 => ['id' => 73, 'buynum' => 73, 'gold' => 82],
        74 => ['id' => 74, 'buynum' => 74, 'gold' => 83],
        75 => ['id' => 75, 'buynum' => 75, 'gold' => 84],
        76 => ['id' => 76, 'buynum' => 76, 'gold' => 85],
        77 => ['id' => 77, 'buynum' => 77, 'gold' => 86],
        78 => ['id' => 78, 'buynum' => 78, 'gold' => 87],
        79 => ['id' => 79, 'buynum' => 79, 'gold' => 88],
        80 => ['id' => 80, 'buynum' => 80, 'gold' => 89],
        81 => ['id' => 81, 'buynum' => 81, 'gold' => 90],
    ];
}
