<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * japan_rank（旧版 zc_sanguo_japan_rank）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class JapanRank
{
    public const string TABLE = 'zc_sanguo_japan_rank';

    /**
     * 按主键 rank 索引，共 50 行。字段：
     *   - rank (int) 伤害排名
     *   - credit (bigint) 奖励：战功
     */
    public const array ROWS = [
        1 => ['rank' => 1, 'credit' => 100],
        2 => ['rank' => 2, 'credit' => 85],
        3 => ['rank' => 3, 'credit' => 70],
        4 => ['rank' => 4, 'credit' => 60],
        5 => ['rank' => 5, 'credit' => 50],
        6 => ['rank' => 6, 'credit' => 40],
        7 => ['rank' => 7, 'credit' => 40],
        8 => ['rank' => 8, 'credit' => 40],
        9 => ['rank' => 9, 'credit' => 40],
        10 => ['rank' => 10, 'credit' => 40],
        11 => ['rank' => 11, 'credit' => 35],
        12 => ['rank' => 12, 'credit' => 35],
        13 => ['rank' => 13, 'credit' => 35],
        14 => ['rank' => 14, 'credit' => 35],
        15 => ['rank' => 15, 'credit' => 35],
        16 => ['rank' => 16, 'credit' => 30],
        17 => ['rank' => 17, 'credit' => 30],
        18 => ['rank' => 18, 'credit' => 30],
        19 => ['rank' => 19, 'credit' => 30],
        20 => ['rank' => 20, 'credit' => 30],
        21 => ['rank' => 21, 'credit' => 30],
        22 => ['rank' => 22, 'credit' => 30],
        23 => ['rank' => 23, 'credit' => 30],
        24 => ['rank' => 24, 'credit' => 30],
        25 => ['rank' => 25, 'credit' => 30],
        26 => ['rank' => 26, 'credit' => 25],
        27 => ['rank' => 27, 'credit' => 25],
        28 => ['rank' => 28, 'credit' => 25],
        29 => ['rank' => 29, 'credit' => 25],
        30 => ['rank' => 30, 'credit' => 25],
        31 => ['rank' => 31, 'credit' => 25],
        32 => ['rank' => 32, 'credit' => 25],
        33 => ['rank' => 33, 'credit' => 25],
        34 => ['rank' => 34, 'credit' => 25],
        35 => ['rank' => 35, 'credit' => 25],
        36 => ['rank' => 36, 'credit' => 20],
        37 => ['rank' => 37, 'credit' => 20],
        38 => ['rank' => 38, 'credit' => 20],
        39 => ['rank' => 39, 'credit' => 20],
        40 => ['rank' => 40, 'credit' => 20],
        41 => ['rank' => 41, 'credit' => 20],
        42 => ['rank' => 42, 'credit' => 20],
        43 => ['rank' => 43, 'credit' => 20],
        44 => ['rank' => 44, 'credit' => 20],
        45 => ['rank' => 45, 'credit' => 20],
        46 => ['rank' => 46, 'credit' => 20],
        47 => ['rank' => 47, 'credit' => 20],
        48 => ['rank' => 48, 'credit' => 20],
        49 => ['rank' => 49, 'credit' => 20],
        50 => ['rank' => 50, 'credit' => 20],
    ];
}
