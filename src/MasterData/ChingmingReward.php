<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 祭祖普通奖励表（旧版 zc_sanguo_chingming_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ChingmingReward
{
    public const string TABLE = 'zc_sanguo_chingming_reward';

    /**
     * 按主键 id 索引，共 50 行。字段：
     *   - id (int)
     *   - type (int)
     *   - value (int)
     *   - rate (double) 出现权重
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => 1, 'value' => 1, 'rate' => 10.0],
        2 => ['id' => 2, 'type' => 1, 'value' => 2, 'rate' => 20.0],
        3 => ['id' => 3, 'type' => 1, 'value' => 3, 'rate' => 30.0],
        4 => ['id' => 4, 'type' => 1, 'value' => 4, 'rate' => 50.0],
        5 => ['id' => 5, 'type' => 1, 'value' => 5, 'rate' => 30.0],
        6 => ['id' => 6, 'type' => 1, 'value' => 6, 'rate' => 20.0],
        7 => ['id' => 7, 'type' => 1, 'value' => 7, 'rate' => 10.0],
        8 => ['id' => 8, 'type' => 1, 'value' => 8, 'rate' => 5.0],
        9 => ['id' => 9, 'type' => 2, 'value' => 1, 'rate' => 50.0],
        10 => ['id' => 10, 'type' => 2, 'value' => 2, 'rate' => 10.0],
        11 => ['id' => 11, 'type' => 2, 'value' => 3, 'rate' => 5.0],
        12 => ['id' => 12, 'type' => 6, 'value' => 600, 'rate' => 30.0],
        13 => ['id' => 13, 'type' => 6, 'value' => 1000, 'rate' => 30.0],
        14 => ['id' => 14, 'type' => 6, 'value' => 1400, 'rate' => 30.0],
        15 => ['id' => 15, 'type' => 6, 'value' => 1800, 'rate' => 30.0],
        16 => ['id' => 16, 'type' => 6, 'value' => 2200, 'rate' => 30.0],
        17 => ['id' => 17, 'type' => 6, 'value' => 2600, 'rate' => 30.0],
        18 => ['id' => 18, 'type' => 5, 'value' => 2, 'rate' => 20.0],
        19 => ['id' => 19, 'type' => 5, 'value' => 3, 'rate' => 20.0],
        20 => ['id' => 20, 'type' => 5, 'value' => 4, 'rate' => 20.0],
        21 => ['id' => 21, 'type' => 5, 'value' => 5, 'rate' => 20.0],
        22 => ['id' => 22, 'type' => 5, 'value' => 6, 'rate' => 20.0],
        23 => ['id' => 23, 'type' => 5, 'value' => 7, 'rate' => 20.0],
        24 => ['id' => 24, 'type' => 5, 'value' => 8, 'rate' => 20.0],
        25 => ['id' => 25, 'type' => 5, 'value' => 9, 'rate' => 20.0],
        26 => ['id' => 26, 'type' => 5, 'value' => 10, 'rate' => 20.0],
        27 => ['id' => 27, 'type' => 7, 'value' => 3, 'rate' => 20.0],
        28 => ['id' => 28, 'type' => 7, 'value' => 5, 'rate' => 20.0],
        29 => ['id' => 29, 'type' => 7, 'value' => 7, 'rate' => 20.0],
        30 => ['id' => 30, 'type' => 7, 'value' => 9, 'rate' => 20.0],
        31 => ['id' => 31, 'type' => 7, 'value' => 11, 'rate' => 20.0],
        32 => ['id' => 32, 'type' => 7, 'value' => 13, 'rate' => 20.0],
        33 => ['id' => 33, 'type' => 7, 'value' => 15, 'rate' => 20.0],
        34 => ['id' => 34, 'type' => 3, 'value' => 4000, 'rate' => 5.0],
        35 => ['id' => 35, 'type' => 3, 'value' => 8000, 'rate' => 30.0],
        36 => ['id' => 36, 'type' => 3, 'value' => 12000, 'rate' => 50.0],
        37 => ['id' => 37, 'type' => 3, 'value' => 16000, 'rate' => 20.0],
        38 => ['id' => 38, 'type' => 3, 'value' => 20000, 'rate' => 10.0],
        39 => ['id' => 39, 'type' => 3, 'value' => 24000, 'rate' => 10.0],
        40 => ['id' => 40, 'type' => 3, 'value' => 28000, 'rate' => 5.0],
        41 => ['id' => 41, 'type' => 3, 'value' => 32000, 'rate' => 5.0],
        42 => ['id' => 42, 'type' => 3, 'value' => 36000, 'rate' => 5.0],
        43 => ['id' => 43, 'type' => 3, 'value' => 40000, 'rate' => 5.0],
        44 => ['id' => 44, 'type' => 4, 'value' => 80, 'rate' => 20.0],
        45 => ['id' => 45, 'type' => 4, 'value' => 100, 'rate' => 20.0],
        46 => ['id' => 46, 'type' => 4, 'value' => 120, 'rate' => 20.0],
        47 => ['id' => 47, 'type' => 4, 'value' => 140, 'rate' => 20.0],
        48 => ['id' => 48, 'type' => 4, 'value' => 160, 'rate' => 20.0],
        49 => ['id' => 49, 'type' => 4, 'value' => 180, 'rate' => 20.0],
        50 => ['id' => 50, 'type' => 4, 'value' => 200, 'rate' => 20.0],
    ];
}
