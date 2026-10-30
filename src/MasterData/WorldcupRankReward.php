<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 我爱世界杯排名奖励表（旧版 zc_sanguo_worldcup_rank_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class WorldcupRankReward
{
    public const string TABLE = 'zc_sanguo_worldcup_rank_reward';

    /**
     * 按主键 rank 索引，共 30 行。字段：
     *   - rank (int) 排名ID
     *   - type (int) 奖励类型
     *   - value (int) 奖励值
     */
    public const array ROWS = [
        1 => ['rank' => 1, 'type' => 3, 'value' => 300],
        2 => ['rank' => 2, 'type' => 3, 'value' => 200],
        3 => ['rank' => 3, 'type' => 3, 'value' => 150],
        4 => ['rank' => 4, 'type' => 3, 'value' => 125],
        5 => ['rank' => 5, 'type' => 3, 'value' => 100],
        6 => ['rank' => 6, 'type' => 3, 'value' => 85],
        7 => ['rank' => 7, 'type' => 3, 'value' => 80],
        8 => ['rank' => 8, 'type' => 3, 'value' => 75],
        9 => ['rank' => 9, 'type' => 3, 'value' => 70],
        10 => ['rank' => 10, 'type' => 3, 'value' => 65],
        11 => ['rank' => 11, 'type' => 3, 'value' => 60],
        12 => ['rank' => 12, 'type' => 3, 'value' => 55],
        13 => ['rank' => 13, 'type' => 3, 'value' => 50],
        14 => ['rank' => 14, 'type' => 3, 'value' => 45],
        15 => ['rank' => 15, 'type' => 3, 'value' => 40],
        16 => ['rank' => 16, 'type' => 2, 'value' => 100],
        17 => ['rank' => 17, 'type' => 2, 'value' => 80],
        18 => ['rank' => 18, 'type' => 2, 'value' => 70],
        19 => ['rank' => 19, 'type' => 2, 'value' => 60],
        20 => ['rank' => 20, 'type' => 2, 'value' => 50],
        21 => ['rank' => 21, 'type' => 2, 'value' => 45],
        22 => ['rank' => 22, 'type' => 2, 'value' => 40],
        23 => ['rank' => 23, 'type' => 2, 'value' => 35],
        24 => ['rank' => 24, 'type' => 2, 'value' => 30],
        25 => ['rank' => 25, 'type' => 2, 'value' => 25],
        26 => ['rank' => 26, 'type' => 2, 'value' => 20],
        27 => ['rank' => 27, 'type' => 2, 'value' => 15],
        28 => ['rank' => 28, 'type' => 2, 'value' => 10],
        29 => ['rank' => 29, 'type' => 2, 'value' => 10],
        30 => ['rank' => 30, 'type' => 2, 'value' => 10],
    ];
}
