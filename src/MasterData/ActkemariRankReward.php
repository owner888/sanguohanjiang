<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 蹴鞠大赛-排行奖励表（旧版 zc_sanguo_actkemari_rank_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActkemariRankReward
{
    public const string TABLE = 'zc_sanguo_actkemari_rank_reward';

    /**
     * 按主键 rank 索引，共 20 行。字段：
     *   - rank (int) 排行ID
     *   - type (int) 奖品类型
     *   - value (int) 奖励系数
     */
    public const array ROWS = [
        1 => ['rank' => 1, 'type' => 4, 'value' => 500],
        2 => ['rank' => 2, 'type' => 4, 'value' => 400],
        3 => ['rank' => 3, 'type' => 4, 'value' => 350],
        4 => ['rank' => 4, 'type' => 4, 'value' => 300],
        5 => ['rank' => 5, 'type' => 4, 'value' => 250],
        6 => ['rank' => 6, 'type' => 4, 'value' => 200],
        7 => ['rank' => 7, 'type' => 4, 'value' => 150],
        8 => ['rank' => 8, 'type' => 4, 'value' => 140],
        9 => ['rank' => 9, 'type' => 4, 'value' => 130],
        10 => ['rank' => 10, 'type' => 4, 'value' => 120],
        11 => ['rank' => 11, 'type' => 4, 'value' => 110],
        12 => ['rank' => 12, 'type' => 4, 'value' => 100],
        13 => ['rank' => 13, 'type' => 4, 'value' => 90],
        14 => ['rank' => 14, 'type' => 4, 'value' => 80],
        15 => ['rank' => 15, 'type' => 4, 'value' => 70],
        16 => ['rank' => 16, 'type' => 4, 'value' => 60],
        17 => ['rank' => 17, 'type' => 4, 'value' => 50],
        18 => ['rank' => 18, 'type' => 4, 'value' => 40],
        19 => ['rank' => 19, 'type' => 4, 'value' => 30],
        20 => ['rank' => 20, 'type' => 4, 'value' => 20],
    ];
}
