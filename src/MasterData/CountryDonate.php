<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 国库捐献数据表（旧版 zc_sanguo_country_donate）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class CountryDonate
{
    public const string TABLE = 'zc_sanguo_country_donate';

    /**
     * 按主键 id 索引，共 41 行。字段：
     *   - id (tinyint) 类型(1银币;2金币)
     *   - silver (int) 银币系数
     *   - gold (int) 元宝数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'silver' => 500, 'gold' => 10],
        2 => ['id' => 2, 'silver' => 550, 'gold' => 11],
        3 => ['id' => 3, 'silver' => 600, 'gold' => 12],
        4 => ['id' => 4, 'silver' => 650, 'gold' => 13],
        5 => ['id' => 5, 'silver' => 700, 'gold' => 14],
        6 => ['id' => 6, 'silver' => 750, 'gold' => 15],
        7 => ['id' => 7, 'silver' => 800, 'gold' => 16],
        8 => ['id' => 8, 'silver' => 850, 'gold' => 17],
        9 => ['id' => 9, 'silver' => 900, 'gold' => 18],
        10 => ['id' => 10, 'silver' => 950, 'gold' => 19],
        11 => ['id' => 11, 'silver' => 1000, 'gold' => 20],
        12 => ['id' => 12, 'silver' => 1050, 'gold' => 21],
        13 => ['id' => 13, 'silver' => 1100, 'gold' => 22],
        14 => ['id' => 14, 'silver' => 1150, 'gold' => 23],
        15 => ['id' => 15, 'silver' => 1200, 'gold' => 24],
        16 => ['id' => 16, 'silver' => 1250, 'gold' => 25],
        17 => ['id' => 17, 'silver' => 1300, 'gold' => 26],
        18 => ['id' => 18, 'silver' => 1350, 'gold' => 27],
        19 => ['id' => 19, 'silver' => 1400, 'gold' => 28],
        20 => ['id' => 20, 'silver' => 1450, 'gold' => 29],
        21 => ['id' => 21, 'silver' => 1500, 'gold' => 30],
        22 => ['id' => 22, 'silver' => 1550, 'gold' => 31],
        23 => ['id' => 23, 'silver' => 1600, 'gold' => 32],
        24 => ['id' => 24, 'silver' => 1650, 'gold' => 33],
        25 => ['id' => 25, 'silver' => 1700, 'gold' => 34],
        26 => ['id' => 26, 'silver' => 1750, 'gold' => 35],
        27 => ['id' => 27, 'silver' => 1800, 'gold' => 36],
        28 => ['id' => 28, 'silver' => 1850, 'gold' => 37],
        29 => ['id' => 29, 'silver' => 1900, 'gold' => 38],
        30 => ['id' => 30, 'silver' => 1950, 'gold' => 39],
        31 => ['id' => 31, 'silver' => 2000, 'gold' => 40],
        32 => ['id' => 32, 'silver' => 2050, 'gold' => 41],
        33 => ['id' => 33, 'silver' => 2100, 'gold' => 42],
        34 => ['id' => 34, 'silver' => 2150, 'gold' => 43],
        35 => ['id' => 35, 'silver' => 2200, 'gold' => 44],
        36 => ['id' => 36, 'silver' => 2250, 'gold' => 45],
        37 => ['id' => 37, 'silver' => 2300, 'gold' => 46],
        38 => ['id' => 38, 'silver' => 2350, 'gold' => 47],
        39 => ['id' => 39, 'silver' => 2400, 'gold' => 48],
        40 => ['id' => 40, 'silver' => 2450, 'gold' => 49],
        41 => ['id' => 41, 'silver' => 2500, 'gold' => 50],
    ];
}
