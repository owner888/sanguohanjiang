<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 祭神排名奖励表（旧版 zc_sanguo_newsacrifices_rank_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class NewsacrificesRankReward
{
    public const string TABLE = 'zc_sanguo_newsacrifices_rank_reward';

    /**
     * 按主键 rank 索引，共 30 行。字段：
     *   - rank (int)
     *   - type (tinyint) 奖励类型（1紫宝石，2黄宝石）
     *   - value (int)
     */
    public const array ROWS = [
        1 => ['rank' => 1, 'type' => 1, 'value' => 1000],
        2 => ['rank' => 2, 'type' => 1, 'value' => 800],
        3 => ['rank' => 3, 'type' => 1, 'value' => 700],
        4 => ['rank' => 4, 'type' => 1, 'value' => 600],
        5 => ['rank' => 5, 'type' => 1, 'value' => 500],
        6 => ['rank' => 6, 'type' => 1, 'value' => 400],
        7 => ['rank' => 7, 'type' => 1, 'value' => 300],
        8 => ['rank' => 8, 'type' => 1, 'value' => 200],
        9 => ['rank' => 9, 'type' => 1, 'value' => 150],
        10 => ['rank' => 10, 'type' => 1, 'value' => 100],
        11 => ['rank' => 11, 'type' => 1, 'value' => 90],
        12 => ['rank' => 12, 'type' => 1, 'value' => 80],
        13 => ['rank' => 13, 'type' => 1, 'value' => 70],
        14 => ['rank' => 14, 'type' => 1, 'value' => 60],
        15 => ['rank' => 15, 'type' => 1, 'value' => 50],
        16 => ['rank' => 16, 'type' => 1, 'value' => 45],
        17 => ['rank' => 17, 'type' => 1, 'value' => 40],
        18 => ['rank' => 18, 'type' => 1, 'value' => 35],
        19 => ['rank' => 19, 'type' => 1, 'value' => 30],
        20 => ['rank' => 20, 'type' => 1, 'value' => 25],
        21 => ['rank' => 21, 'type' => 1, 'value' => 20],
        22 => ['rank' => 22, 'type' => 1, 'value' => 20],
        23 => ['rank' => 23, 'type' => 1, 'value' => 20],
        24 => ['rank' => 24, 'type' => 1, 'value' => 20],
        25 => ['rank' => 25, 'type' => 1, 'value' => 20],
        26 => ['rank' => 26, 'type' => 1, 'value' => 10],
        27 => ['rank' => 27, 'type' => 1, 'value' => 10],
        28 => ['rank' => 28, 'type' => 1, 'value' => 10],
        29 => ['rank' => 29, 'type' => 1, 'value' => 10],
        30 => ['rank' => 30, 'type' => 1, 'value' => 10],
    ];
}
