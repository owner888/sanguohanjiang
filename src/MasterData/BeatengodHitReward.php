<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 暴打名将常规物品奖励表（旧版 zc_sanguo_beatengod_hit_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class BeatengodHitReward
{
    public const string TABLE = 'zc_sanguo_beatengod_hit_reward';

    /**
     * 按主键 id 索引，共 42 行。字段：
     *   - id (tinyint) 物品id
     *   - type (tinyint) 物品类型（1银，2军，3突，4黄宝石，5紫宝石，6玉石，7原魂）
     *   - value (mediumint) 参数
     *   - hit_type (tinyint) 所属锤子（1木锤，2金锤）
     *   - rate (float) 出现权重
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => 1, 'value' => 500, 'hit_type' => 1, 'rate' => 50.0],
        2 => ['id' => 2, 'type' => 1, 'value' => 700, 'hit_type' => 1, 'rate' => 50.0],
        3 => ['id' => 3, 'type' => 1, 'value' => 900, 'hit_type' => 1, 'rate' => 50.0],
        4 => ['id' => 4, 'type' => 1, 'value' => 1100, 'hit_type' => 1, 'rate' => 50.0],
        5 => ['id' => 5, 'type' => 1, 'value' => 1300, 'hit_type' => 1, 'rate' => 50.0],
        6 => ['id' => 6, 'type' => 1, 'value' => 1500, 'hit_type' => 1, 'rate' => 50.0],
        7 => ['id' => 7, 'type' => 2, 'value' => 3, 'hit_type' => 1, 'rate' => 50.0],
        8 => ['id' => 8, 'type' => 2, 'value' => 4, 'hit_type' => 1, 'rate' => 50.0],
        9 => ['id' => 9, 'type' => 2, 'value' => 5, 'hit_type' => 1, 'rate' => 50.0],
        10 => ['id' => 10, 'type' => 2, 'value' => 6, 'hit_type' => 1, 'rate' => 50.0],
        11 => ['id' => 11, 'type' => 2, 'value' => 7, 'hit_type' => 1, 'rate' => 50.0],
        12 => ['id' => 12, 'type' => 2, 'value' => 8, 'hit_type' => 1, 'rate' => 50.0],
        13 => ['id' => 13, 'type' => 3, 'value' => 2, 'hit_type' => 1, 'rate' => 50.0],
        14 => ['id' => 14, 'type' => 3, 'value' => 3, 'hit_type' => 1, 'rate' => 50.0],
        15 => ['id' => 15, 'type' => 3, 'value' => 4, 'hit_type' => 1, 'rate' => 50.0],
        16 => ['id' => 16, 'type' => 3, 'value' => 5, 'hit_type' => 1, 'rate' => 50.0],
        17 => ['id' => 17, 'type' => 3, 'value' => 6, 'hit_type' => 1, 'rate' => 50.0],
        18 => ['id' => 18, 'type' => 3, 'value' => 7, 'hit_type' => 1, 'rate' => 50.0],
        19 => ['id' => 19, 'type' => 4, 'value' => 3, 'hit_type' => 1, 'rate' => 50.0],
        20 => ['id' => 20, 'type' => 4, 'value' => 4, 'hit_type' => 1, 'rate' => 50.0],
        21 => ['id' => 21, 'type' => 4, 'value' => 5, 'hit_type' => 1, 'rate' => 50.0],
        22 => ['id' => 22, 'type' => 4, 'value' => 6, 'hit_type' => 1, 'rate' => 50.0],
        23 => ['id' => 23, 'type' => 4, 'value' => 7, 'hit_type' => 1, 'rate' => 50.0],
        24 => ['id' => 24, 'type' => 4, 'value' => 8, 'hit_type' => 1, 'rate' => 50.0],
        25 => ['id' => 25, 'type' => 5, 'value' => 3, 'hit_type' => 2, 'rate' => 50.0],
        26 => ['id' => 26, 'type' => 5, 'value' => 4, 'hit_type' => 2, 'rate' => 50.0],
        27 => ['id' => 27, 'type' => 5, 'value' => 5, 'hit_type' => 2, 'rate' => 50.0],
        28 => ['id' => 28, 'type' => 5, 'value' => 6, 'hit_type' => 2, 'rate' => 50.0],
        29 => ['id' => 29, 'type' => 5, 'value' => 7, 'hit_type' => 2, 'rate' => 50.0],
        30 => ['id' => 30, 'type' => 5, 'value' => 8, 'hit_type' => 2, 'rate' => 50.0],
        31 => ['id' => 31, 'type' => 6, 'value' => 40000, 'hit_type' => 2, 'rate' => 50.0],
        32 => ['id' => 32, 'type' => 6, 'value' => 60000, 'hit_type' => 2, 'rate' => 50.0],
        33 => ['id' => 33, 'type' => 6, 'value' => 80000, 'hit_type' => 2, 'rate' => 50.0],
        34 => ['id' => 34, 'type' => 6, 'value' => 100000, 'hit_type' => 2, 'rate' => 50.0],
        35 => ['id' => 35, 'type' => 6, 'value' => 120000, 'hit_type' => 2, 'rate' => 50.0],
        36 => ['id' => 36, 'type' => 6, 'value' => 160000, 'hit_type' => 2, 'rate' => 50.0],
        37 => ['id' => 37, 'type' => 7, 'value' => 300, 'hit_type' => 2, 'rate' => 50.0],
        38 => ['id' => 38, 'type' => 7, 'value' => 350, 'hit_type' => 2, 'rate' => 50.0],
        39 => ['id' => 39, 'type' => 7, 'value' => 400, 'hit_type' => 2, 'rate' => 50.0],
        40 => ['id' => 40, 'type' => 7, 'value' => 450, 'hit_type' => 2, 'rate' => 50.0],
        41 => ['id' => 41, 'type' => 7, 'value' => 500, 'hit_type' => 2, 'rate' => 50.0],
        42 => ['id' => 42, 'type' => 7, 'value' => 600, 'hit_type' => 2, 'rate' => 50.0],
    ];
}
