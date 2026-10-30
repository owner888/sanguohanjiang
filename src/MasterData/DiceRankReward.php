<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 国家骰子排名奖励记录表（旧版 zc_sanguo_dice_rank_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class DiceRankReward
{
    public const string TABLE = 'zc_sanguo_dice_rank_reward';

    /**
     * 按主键 rank 索引，共 50 行。字段：
     *   - rank (tinyint) 排名
     *   - reward (smallint) 排名奖励元宝
     */
    public const array ROWS = [
        1 => ['rank' => 1, 'reward' => 100],
        2 => ['rank' => 2, 'reward' => 90],
        3 => ['rank' => 3, 'reward' => 80],
        4 => ['rank' => 4, 'reward' => 75],
        5 => ['rank' => 5, 'reward' => 70],
        6 => ['rank' => 6, 'reward' => 65],
        7 => ['rank' => 7, 'reward' => 60],
        8 => ['rank' => 8, 'reward' => 55],
        9 => ['rank' => 9, 'reward' => 50],
        10 => ['rank' => 10, 'reward' => 45],
        11 => ['rank' => 11, 'reward' => 40],
        12 => ['rank' => 12, 'reward' => 35],
        13 => ['rank' => 13, 'reward' => 30],
        14 => ['rank' => 14, 'reward' => 30],
        15 => ['rank' => 15, 'reward' => 30],
        16 => ['rank' => 16, 'reward' => 30],
        17 => ['rank' => 17, 'reward' => 30],
        18 => ['rank' => 18, 'reward' => 30],
        19 => ['rank' => 19, 'reward' => 30],
        20 => ['rank' => 20, 'reward' => 30],
        21 => ['rank' => 21, 'reward' => 25],
        22 => ['rank' => 22, 'reward' => 25],
        23 => ['rank' => 23, 'reward' => 25],
        24 => ['rank' => 24, 'reward' => 25],
        25 => ['rank' => 25, 'reward' => 25],
        26 => ['rank' => 26, 'reward' => 25],
        27 => ['rank' => 27, 'reward' => 25],
        28 => ['rank' => 28, 'reward' => 25],
        29 => ['rank' => 29, 'reward' => 25],
        30 => ['rank' => 30, 'reward' => 25],
        31 => ['rank' => 31, 'reward' => 20],
        32 => ['rank' => 32, 'reward' => 20],
        33 => ['rank' => 33, 'reward' => 20],
        34 => ['rank' => 34, 'reward' => 20],
        35 => ['rank' => 35, 'reward' => 20],
        36 => ['rank' => 36, 'reward' => 15],
        37 => ['rank' => 37, 'reward' => 15],
        38 => ['rank' => 38, 'reward' => 15],
        39 => ['rank' => 39, 'reward' => 15],
        40 => ['rank' => 40, 'reward' => 15],
        41 => ['rank' => 41, 'reward' => 10],
        42 => ['rank' => 42, 'reward' => 10],
        43 => ['rank' => 43, 'reward' => 10],
        44 => ['rank' => 44, 'reward' => 10],
        45 => ['rank' => 45, 'reward' => 10],
        46 => ['rank' => 46, 'reward' => 10],
        47 => ['rank' => 47, 'reward' => 10],
        48 => ['rank' => 48, 'reward' => 10],
        49 => ['rank' => 49, 'reward' => 10],
        50 => ['rank' => 50, 'reward' => 10],
    ];
}
