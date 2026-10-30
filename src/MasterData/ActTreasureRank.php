<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 寻宝次数排行奖励表（旧版 zc_sanguo_act_treasure_rank）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActTreasureRank
{
    public const string TABLE = 'zc_sanguo_act_treasure_rank';

    /**
     * 按主键 rank 索引，共 30 行。字段：
     *   - rank (int) 排行ID
     *   - type (int) 奖励类型
     *   - value (int) 奖励系数
     */
    public const array ROWS = [
        1 => ['rank' => 1, 'type' => 4, 'value' => 80000],
        2 => ['rank' => 2, 'type' => 4, 'value' => 76000],
        3 => ['rank' => 3, 'type' => 4, 'value' => 74000],
        4 => ['rank' => 4, 'type' => 4, 'value' => 70000],
        5 => ['rank' => 5, 'type' => 4, 'value' => 68000],
        6 => ['rank' => 6, 'type' => 4, 'value' => 66000],
        7 => ['rank' => 7, 'type' => 4, 'value' => 64000],
        8 => ['rank' => 8, 'type' => 4, 'value' => 62000],
        9 => ['rank' => 9, 'type' => 4, 'value' => 60000],
        10 => ['rank' => 10, 'type' => 4, 'value' => 58000],
        11 => ['rank' => 11, 'type' => 4, 'value' => 56000],
        12 => ['rank' => 12, 'type' => 4, 'value' => 54000],
        13 => ['rank' => 13, 'type' => 4, 'value' => 52000],
        14 => ['rank' => 14, 'type' => 4, 'value' => 50000],
        15 => ['rank' => 15, 'type' => 4, 'value' => 48000],
        16 => ['rank' => 16, 'type' => 4, 'value' => 46000],
        17 => ['rank' => 17, 'type' => 4, 'value' => 44000],
        18 => ['rank' => 18, 'type' => 4, 'value' => 42000],
        19 => ['rank' => 19, 'type' => 4, 'value' => 40000],
        20 => ['rank' => 20, 'type' => 4, 'value' => 38000],
        21 => ['rank' => 21, 'type' => 4, 'value' => 36000],
        22 => ['rank' => 22, 'type' => 4, 'value' => 34000],
        23 => ['rank' => 23, 'type' => 4, 'value' => 32000],
        24 => ['rank' => 24, 'type' => 4, 'value' => 30000],
        25 => ['rank' => 25, 'type' => 4, 'value' => 28000],
        26 => ['rank' => 26, 'type' => 4, 'value' => 26000],
        27 => ['rank' => 27, 'type' => 4, 'value' => 24000],
        28 => ['rank' => 28, 'type' => 4, 'value' => 22000],
        29 => ['rank' => 29, 'type' => 4, 'value' => 20000],
        30 => ['rank' => 30, 'type' => 4, 'value' => 18000],
    ];
}
