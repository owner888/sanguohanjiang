<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * jadegamble_fb_reward（旧版 zc_sanguo_jadegamble_fb_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class JadegambleFbReward
{
    public const string TABLE = 'zc_sanguo_jadegamble_fb_reward';

    /**
     * 按主键 id 索引，共 78 行。字段：
     *   - id (int)
     *   - jadetype (int)
     *   - type (int)
     *   - value (int)
     *   - multiple (int)
     *   - is_success (int)
     *   - getrate (int)
     */
    public const array ROWS = [
        1 => ['id' => 1, 'jadetype' => 3, 'type' => 5, 'value' => 75, 'multiple' => 8, 'is_success' => 0, 'getrate' => 30],
        2 => ['id' => 2, 'jadetype' => 3, 'type' => 6, 'value' => 1500, 'multiple' => 8, 'is_success' => 0, 'getrate' => 30],
        3 => ['id' => 3, 'jadetype' => 3, 'type' => 10, 'value' => 5, 'multiple' => 8, 'is_success' => 0, 'getrate' => 40],
        4 => ['id' => 4, 'jadetype' => 3, 'type' => 5, 'value' => 100, 'multiple' => 8, 'is_success' => 1, 'getrate' => 30],
        5 => ['id' => 5, 'jadetype' => 3, 'type' => 6, 'value' => 2500, 'multiple' => 8, 'is_success' => 1, 'getrate' => 30],
        6 => ['id' => 6, 'jadetype' => 3, 'type' => 10, 'value' => 6, 'multiple' => 8, 'is_success' => 1, 'getrate' => 40],
        7 => ['id' => 7, 'jadetype' => 3, 'type' => 5, 'value' => 100, 'multiple' => 32, 'is_success' => 0, 'getrate' => 30],
        8 => ['id' => 8, 'jadetype' => 3, 'type' => 6, 'value' => 2500, 'multiple' => 32, 'is_success' => 0, 'getrate' => 30],
        9 => ['id' => 9, 'jadetype' => 3, 'type' => 9, 'value' => 1000, 'multiple' => 32, 'is_success' => 0, 'getrate' => 20],
        10 => ['id' => 10, 'jadetype' => 3, 'type' => 10, 'value' => 5, 'multiple' => 32, 'is_success' => 0, 'getrate' => 20],
        11 => ['id' => 11, 'jadetype' => 3, 'type' => 5, 'value' => 150, 'multiple' => 32, 'is_success' => 1, 'getrate' => 30],
        12 => ['id' => 12, 'jadetype' => 3, 'type' => 6, 'value' => 4000, 'multiple' => 32, 'is_success' => 1, 'getrate' => 30],
        13 => ['id' => 13, 'jadetype' => 3, 'type' => 9, 'value' => 2000, 'multiple' => 32, 'is_success' => 1, 'getrate' => 20],
        14 => ['id' => 14, 'jadetype' => 3, 'type' => 10, 'value' => 10, 'multiple' => 32, 'is_success' => 1, 'getrate' => 20],
        15 => ['id' => 15, 'jadetype' => 3, 'type' => 9, 'value' => 2000, 'multiple' => 256, 'is_success' => 0, 'getrate' => 40],
        16 => ['id' => 16, 'jadetype' => 3, 'type' => 14, 'value' => 4000, 'multiple' => 256, 'is_success' => 0, 'getrate' => 30],
        17 => ['id' => 17, 'jadetype' => 3, 'type' => 15, 'value' => 20, 'multiple' => 256, 'is_success' => 0, 'getrate' => 30],
        18 => ['id' => 18, 'jadetype' => 3, 'type' => 9, 'value' => 3000, 'multiple' => 256, 'is_success' => 1, 'getrate' => 40],
        19 => ['id' => 19, 'jadetype' => 3, 'type' => 14, 'value' => 7000, 'multiple' => 256, 'is_success' => 1, 'getrate' => 30],
        20 => ['id' => 20, 'jadetype' => 3, 'type' => 15, 'value' => 30, 'multiple' => 256, 'is_success' => 1, 'getrate' => 30],
        21 => ['id' => 21, 'jadetype' => 3, 'type' => 7, 'value' => 2000, 'multiple' => 1024, 'is_success' => 0, 'getrate' => 40],
        22 => ['id' => 22, 'jadetype' => 3, 'type' => 14, 'value' => 5000, 'multiple' => 1024, 'is_success' => 0, 'getrate' => 30],
        23 => ['id' => 23, 'jadetype' => 3, 'type' => 15, 'value' => 30, 'multiple' => 1024, 'is_success' => 0, 'getrate' => 30],
        24 => ['id' => 24, 'jadetype' => 3, 'type' => 7, 'value' => 4000, 'multiple' => 1024, 'is_success' => 1, 'getrate' => 40],
        25 => ['id' => 25, 'jadetype' => 3, 'type' => 14, 'value' => 10000, 'multiple' => 1024, 'is_success' => 1, 'getrate' => 30],
        26 => ['id' => 26, 'jadetype' => 3, 'type' => 15, 'value' => 50, 'multiple' => 1024, 'is_success' => 1, 'getrate' => 30],
        27 => ['id' => 27, 'jadetype' => 2, 'type' => 5, 'value' => 20, 'multiple' => 8, 'is_success' => 0, 'getrate' => 30],
        28 => ['id' => 28, 'jadetype' => 2, 'type' => 2, 'value' => 10000, 'multiple' => 8, 'is_success' => 0, 'getrate' => 30],
        29 => ['id' => 29, 'jadetype' => 2, 'type' => 10, 'value' => 3, 'multiple' => 8, 'is_success' => 0, 'getrate' => 40],
        30 => ['id' => 30, 'jadetype' => 2, 'type' => 5, 'value' => 30, 'multiple' => 8, 'is_success' => 1, 'getrate' => 30],
        31 => ['id' => 31, 'jadetype' => 2, 'type' => 2, 'value' => 30000, 'multiple' => 8, 'is_success' => 1, 'getrate' => 30],
        32 => ['id' => 32, 'jadetype' => 2, 'type' => 10, 'value' => 4, 'multiple' => 8, 'is_success' => 1, 'getrate' => 40],
        33 => ['id' => 33, 'jadetype' => 2, 'type' => 5, 'value' => 30, 'multiple' => 32, 'is_success' => 0, 'getrate' => 30],
        34 => ['id' => 34, 'jadetype' => 2, 'type' => 6, 'value' => 2000, 'multiple' => 32, 'is_success' => 0, 'getrate' => 30],
        35 => ['id' => 35, 'jadetype' => 2, 'type' => 2, 'value' => 30000, 'multiple' => 32, 'is_success' => 0, 'getrate' => 20],
        36 => ['id' => 36, 'jadetype' => 2, 'type' => 10, 'value' => 5, 'multiple' => 32, 'is_success' => 0, 'getrate' => 20],
        37 => ['id' => 37, 'jadetype' => 2, 'type' => 5, 'value' => 50, 'multiple' => 32, 'is_success' => 1, 'getrate' => 30],
        38 => ['id' => 38, 'jadetype' => 2, 'type' => 6, 'value' => 3000, 'multiple' => 32, 'is_success' => 1, 'getrate' => 30],
        39 => ['id' => 39, 'jadetype' => 2, 'type' => 2, 'value' => 50000, 'multiple' => 32, 'is_success' => 1, 'getrate' => 20],
        40 => ['id' => 40, 'jadetype' => 2, 'type' => 10, 'value' => 6, 'multiple' => 32, 'is_success' => 1, 'getrate' => 20],
        41 => ['id' => 41, 'jadetype' => 2, 'type' => 5, 'value' => 50, 'multiple' => 256, 'is_success' => 0, 'getrate' => 40],
        42 => ['id' => 42, 'jadetype' => 2, 'type' => 6, 'value' => 4000, 'multiple' => 256, 'is_success' => 0, 'getrate' => 30],
        43 => ['id' => 43, 'jadetype' => 2, 'type' => 10, 'value' => 10, 'multiple' => 256, 'is_success' => 0, 'getrate' => 30],
        44 => ['id' => 44, 'jadetype' => 2, 'type' => 5, 'value' => 80, 'multiple' => 256, 'is_success' => 1, 'getrate' => 40],
        45 => ['id' => 45, 'jadetype' => 2, 'type' => 6, 'value' => 5000, 'multiple' => 256, 'is_success' => 1, 'getrate' => 30],
        46 => ['id' => 46, 'jadetype' => 2, 'type' => 10, 'value' => 15, 'multiple' => 256, 'is_success' => 1, 'getrate' => 30],
        47 => ['id' => 47, 'jadetype' => 2, 'type' => 4, 'value' => 15, 'multiple' => 1024, 'is_success' => 0, 'getrate' => 40],
        48 => ['id' => 48, 'jadetype' => 2, 'type' => 5, 'value' => 100, 'multiple' => 1024, 'is_success' => 0, 'getrate' => 30],
        49 => ['id' => 49, 'jadetype' => 2, 'type' => 10, 'value' => 20, 'multiple' => 1024, 'is_success' => 0, 'getrate' => 30],
        50 => ['id' => 50, 'jadetype' => 2, 'type' => 4, 'value' => 30, 'multiple' => 1024, 'is_success' => 1, 'getrate' => 40],
        51 => ['id' => 51, 'jadetype' => 2, 'type' => 5, 'value' => 150, 'multiple' => 1024, 'is_success' => 1, 'getrate' => 30],
        52 => ['id' => 52, 'jadetype' => 2, 'type' => 10, 'value' => 30, 'multiple' => 1024, 'is_success' => 1, 'getrate' => 30],
        53 => ['id' => 53, 'jadetype' => 1, 'type' => 13, 'value' => 2, 'multiple' => 8, 'is_success' => 0, 'getrate' => 30],
        54 => ['id' => 54, 'jadetype' => 1, 'type' => 2, 'value' => 5000, 'multiple' => 8, 'is_success' => 0, 'getrate' => 30],
        55 => ['id' => 55, 'jadetype' => 1, 'type' => 10, 'value' => 1, 'multiple' => 8, 'is_success' => 0, 'getrate' => 40],
        56 => ['id' => 56, 'jadetype' => 1, 'type' => 13, 'value' => 4, 'multiple' => 8, 'is_success' => 1, 'getrate' => 30],
        57 => ['id' => 57, 'jadetype' => 1, 'type' => 2, 'value' => 8000, 'multiple' => 8, 'is_success' => 1, 'getrate' => 30],
        58 => ['id' => 58, 'jadetype' => 1, 'type' => 10, 'value' => 2, 'multiple' => 8, 'is_success' => 1, 'getrate' => 40],
        59 => ['id' => 59, 'jadetype' => 1, 'type' => 13, 'value' => 4, 'multiple' => 32, 'is_success' => 0, 'getrate' => 30],
        60 => ['id' => 60, 'jadetype' => 1, 'type' => 5, 'value' => 10, 'multiple' => 32, 'is_success' => 0, 'getrate' => 30],
        61 => ['id' => 61, 'jadetype' => 1, 'type' => 2, 'value' => 10000, 'multiple' => 32, 'is_success' => 0, 'getrate' => 20],
        62 => ['id' => 62, 'jadetype' => 1, 'type' => 10, 'value' => 3, 'multiple' => 32, 'is_success' => 0, 'getrate' => 20],
        63 => ['id' => 63, 'jadetype' => 1, 'type' => 13, 'value' => 6, 'multiple' => 32, 'is_success' => 1, 'getrate' => 30],
        64 => ['id' => 64, 'jadetype' => 1, 'type' => 5, 'value' => 20, 'multiple' => 32, 'is_success' => 1, 'getrate' => 30],
        65 => ['id' => 65, 'jadetype' => 1, 'type' => 2, 'value' => 20000, 'multiple' => 32, 'is_success' => 1, 'getrate' => 20],
        66 => ['id' => 66, 'jadetype' => 1, 'type' => 10, 'value' => 5, 'multiple' => 32, 'is_success' => 1, 'getrate' => 20],
        67 => ['id' => 67, 'jadetype' => 1, 'type' => 3, 'value' => 10, 'multiple' => 256, 'is_success' => 0, 'getrate' => 40],
        68 => ['id' => 68, 'jadetype' => 1, 'type' => 2, 'value' => 20000, 'multiple' => 256, 'is_success' => 0, 'getrate' => 30],
        69 => ['id' => 69, 'jadetype' => 1, 'type' => 10, 'value' => 10, 'multiple' => 256, 'is_success' => 0, 'getrate' => 30],
        70 => ['id' => 70, 'jadetype' => 1, 'type' => 3, 'value' => 15, 'multiple' => 256, 'is_success' => 1, 'getrate' => 40],
        71 => ['id' => 71, 'jadetype' => 1, 'type' => 2, 'value' => 40000, 'multiple' => 256, 'is_success' => 1, 'getrate' => 30],
        72 => ['id' => 72, 'jadetype' => 1, 'type' => 10, 'value' => 15, 'multiple' => 256, 'is_success' => 1, 'getrate' => 30],
        73 => ['id' => 73, 'jadetype' => 1, 'type' => 3, 'value' => 15, 'multiple' => 1024, 'is_success' => 0, 'getrate' => 40],
        74 => ['id' => 74, 'jadetype' => 1, 'type' => 2, 'value' => 50000, 'multiple' => 1024, 'is_success' => 0, 'getrate' => 30],
        75 => ['id' => 75, 'jadetype' => 1, 'type' => 10, 'value' => 15, 'multiple' => 1024, 'is_success' => 0, 'getrate' => 30],
        76 => ['id' => 76, 'jadetype' => 1, 'type' => 3, 'value' => 30, 'multiple' => 1024, 'is_success' => 1, 'getrate' => 40],
        77 => ['id' => 77, 'jadetype' => 1, 'type' => 2, 'value' => 100000, 'multiple' => 1024, 'is_success' => 1, 'getrate' => 30],
        78 => ['id' => 78, 'jadetype' => 1, 'type' => 10, 'value' => 20, 'multiple' => 1024, 'is_success' => 1, 'getrate' => 30],
    ];
}
