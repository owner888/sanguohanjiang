<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 祭祖排名奖励表（旧版 zc_sanguo_chingming_rank_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ChingmingRankReward
{
    public const string TABLE = 'zc_sanguo_chingming_rank_reward';

    /**
     * 按主键 rank 索引，共 30 行。字段：
     *   - rank (int)
     *   - value (int)
     */
    public const array ROWS = [
        1 => ['rank' => 1, 'value' => 200000],
        2 => ['rank' => 2, 'value' => 150000],
        3 => ['rank' => 3, 'value' => 125000],
        4 => ['rank' => 4, 'value' => 100000],
        5 => ['rank' => 5, 'value' => 75000],
        6 => ['rank' => 6, 'value' => 70000],
        7 => ['rank' => 7, 'value' => 65000],
        8 => ['rank' => 8, 'value' => 60000],
        9 => ['rank' => 9, 'value' => 55000],
        10 => ['rank' => 10, 'value' => 50000],
        11 => ['rank' => 11, 'value' => 47500],
        12 => ['rank' => 12, 'value' => 45000],
        13 => ['rank' => 13, 'value' => 42500],
        14 => ['rank' => 14, 'value' => 37500],
        15 => ['rank' => 15, 'value' => 32500],
        16 => ['rank' => 16, 'value' => 30000],
        17 => ['rank' => 17, 'value' => 27500],
        18 => ['rank' => 18, 'value' => 25000],
        19 => ['rank' => 19, 'value' => 20000],
        20 => ['rank' => 20, 'value' => 15000],
        21 => ['rank' => 21, 'value' => 12500],
        22 => ['rank' => 22, 'value' => 12000],
        23 => ['rank' => 23, 'value' => 9000],
        24 => ['rank' => 24, 'value' => 8000],
        25 => ['rank' => 25, 'value' => 7500],
        26 => ['rank' => 26, 'value' => 7000],
        27 => ['rank' => 27, 'value' => 6500],
        28 => ['rank' => 28, 'value' => 6000],
        29 => ['rank' => 29, 'value' => 5000],
        30 => ['rank' => 30, 'value' => 4500],
    ];
}
