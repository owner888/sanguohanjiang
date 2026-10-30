<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 过关斩将过人奖励表（旧版 zc_sanguo_passgeneral_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class PassgeneralReward
{
    public const string TABLE = 'zc_sanguo_passgeneral_reward';

    /**
     * 按主键 id 索引，共 65 行。字段：
     *   - id (int) ID
     *   - type (int) 类型
     *   - value (int) 参数
     *   - num (int) 层数
     *   - is_succ (int) 是否过人奖励
     *   - rate (float) 权重
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => 3, 'value' => 20, 'num' => 1, 'is_succ' => 0, 'rate' => 30.0],
        2 => ['id' => 2, 'type' => 10, 'value' => 10, 'num' => 1, 'is_succ' => 0, 'rate' => 30.0],
        3 => ['id' => 3, 'type' => 27, 'value' => 1, 'num' => 1, 'is_succ' => 0, 'rate' => 40.0],
        4 => ['id' => 4, 'type' => 5, 'value' => 400, 'num' => 1, 'is_succ' => 0, 'rate' => 30.0],
        5 => ['id' => 5, 'type' => 9, 'value' => 1000, 'num' => 1, 'is_succ' => 0, 'rate' => 30.0],
        6 => ['id' => 6, 'type' => 26, 'value' => 92, 'num' => 1, 'is_succ' => 1, 'rate' => 0.0001],
        7 => ['id' => 7, 'type' => 26, 'value' => 106, 'num' => 1, 'is_succ' => 1, 'rate' => 0.0001],
        8 => ['id' => 8, 'type' => 26, 'value' => 91, 'num' => 1, 'is_succ' => 1, 'rate' => 0.0001],
        9 => ['id' => 9, 'type' => 26, 'value' => 111, 'num' => 1, 'is_succ' => 1, 'rate' => 0.0001],
        10 => ['id' => 10, 'type' => 3, 'value' => 60, 'num' => 1, 'is_succ' => 1, 'rate' => 30.0],
        11 => ['id' => 11, 'type' => 10, 'value' => 20, 'num' => 1, 'is_succ' => 1, 'rate' => 30.0],
        12 => ['id' => 12, 'type' => 27, 'value' => 2, 'num' => 1, 'is_succ' => 1, 'rate' => 40.0],
        13 => ['id' => 13, 'type' => 5, 'value' => 600, 'num' => 1, 'is_succ' => 1, 'rate' => 30.0],
        14 => ['id' => 14, 'type' => 9, 'value' => 2000, 'num' => 1, 'is_succ' => 1, 'rate' => 30.0],
        15 => ['id' => 15, 'type' => 3, 'value' => 30, 'num' => 2, 'is_succ' => 0, 'rate' => 30.0],
        16 => ['id' => 16, 'type' => 10, 'value' => 15, 'num' => 2, 'is_succ' => 0, 'rate' => 30.0],
        17 => ['id' => 17, 'type' => 27, 'value' => 2, 'num' => 2, 'is_succ' => 0, 'rate' => 40.0],
        18 => ['id' => 18, 'type' => 5, 'value' => 600, 'num' => 2, 'is_succ' => 0, 'rate' => 30.0],
        19 => ['id' => 19, 'type' => 4, 'value' => 10, 'num' => 2, 'is_succ' => 0, 'rate' => 10.0],
        20 => ['id' => 20, 'type' => 26, 'value' => 129, 'num' => 2, 'is_succ' => 1, 'rate' => 0.0001],
        21 => ['id' => 21, 'type' => 26, 'value' => 130, 'num' => 2, 'is_succ' => 1, 'rate' => 0.0001],
        22 => ['id' => 22, 'type' => 26, 'value' => 131, 'num' => 2, 'is_succ' => 1, 'rate' => 0.0001],
        23 => ['id' => 23, 'type' => 3, 'value' => 60, 'num' => 2, 'is_succ' => 1, 'rate' => 30.0],
        24 => ['id' => 24, 'type' => 10, 'value' => 30, 'num' => 2, 'is_succ' => 1, 'rate' => 30.0],
        25 => ['id' => 25, 'type' => 27, 'value' => 3, 'num' => 2, 'is_succ' => 1, 'rate' => 40.0],
        26 => ['id' => 26, 'type' => 5, 'value' => 800, 'num' => 2, 'is_succ' => 1, 'rate' => 30.0],
        27 => ['id' => 27, 'type' => 4, 'value' => 15, 'num' => 2, 'is_succ' => 1, 'rate' => 10.0],
        28 => ['id' => 28, 'type' => 10, 'value' => 20, 'num' => 3, 'is_succ' => 0, 'rate' => 30.0],
        29 => ['id' => 29, 'type' => 27, 'value' => 3, 'num' => 3, 'is_succ' => 0, 'rate' => 40.0],
        30 => ['id' => 30, 'type' => 5, 'value' => 800, 'num' => 3, 'is_succ' => 0, 'rate' => 30.0],
        31 => ['id' => 31, 'type' => 4, 'value' => 15, 'num' => 3, 'is_succ' => 0, 'rate' => 10.0],
        32 => ['id' => 32, 'type' => 14, 'value' => 5000, 'num' => 3, 'is_succ' => 0, 'rate' => 10.0],
        33 => ['id' => 33, 'type' => 15, 'value' => 20, 'num' => 3, 'is_succ' => 0, 'rate' => 8.0],
        34 => ['id' => 34, 'type' => 16, 'value' => 5, 'num' => 3, 'is_succ' => 0, 'rate' => 5.0],
        35 => ['id' => 35, 'type' => 26, 'value' => 51, 'num' => 3, 'is_succ' => 1, 'rate' => 0.0001],
        36 => ['id' => 36, 'type' => 26, 'value' => 107, 'num' => 3, 'is_succ' => 1, 'rate' => 0.0001],
        37 => ['id' => 37, 'type' => 26, 'value' => 110, 'num' => 3, 'is_succ' => 1, 'rate' => 0.0001],
        38 => ['id' => 38, 'type' => 10, 'value' => 40, 'num' => 3, 'is_succ' => 1, 'rate' => 30.0],
        39 => ['id' => 39, 'type' => 27, 'value' => 4, 'num' => 3, 'is_succ' => 1, 'rate' => 40.0],
        40 => ['id' => 40, 'type' => 5, 'value' => 1000, 'num' => 3, 'is_succ' => 1, 'rate' => 30.0],
        41 => ['id' => 41, 'type' => 4, 'value' => 20, 'num' => 3, 'is_succ' => 1, 'rate' => 10.0],
        42 => ['id' => 42, 'type' => 14, 'value' => 10000, 'num' => 3, 'is_succ' => 1, 'rate' => 10.0],
        43 => ['id' => 43, 'type' => 10, 'value' => 25, 'num' => 4, 'is_succ' => 0, 'rate' => 30.0],
        44 => ['id' => 44, 'type' => 27, 'value' => 4, 'num' => 4, 'is_succ' => 0, 'rate' => 40.0],
        45 => ['id' => 45, 'type' => 5, 'value' => 1000, 'num' => 4, 'is_succ' => 0, 'rate' => 30.0],
        46 => ['id' => 46, 'type' => 4, 'value' => 20, 'num' => 4, 'is_succ' => 0, 'rate' => 10.0],
        47 => ['id' => 47, 'type' => 7, 'value' => 2000, 'num' => 4, 'is_succ' => 0, 'rate' => 10.0],
        48 => ['id' => 48, 'type' => 17, 'value' => 1, 'num' => 4, 'is_succ' => 0, 'rate' => 0.1],
        49 => ['id' => 49, 'type' => 18, 'value' => 1, 'num' => 4, 'is_succ' => 0, 'rate' => 0.1],
        50 => ['id' => 50, 'type' => 19, 'value' => 1, 'num' => 4, 'is_succ' => 0, 'rate' => 0.1],
        51 => ['id' => 51, 'type' => 20, 'value' => 1, 'num' => 4, 'is_succ' => 0, 'rate' => 0.1],
        52 => ['id' => 52, 'type' => 21, 'value' => 1, 'num' => 4, 'is_succ' => 0, 'rate' => 0.1],
        53 => ['id' => 53, 'type' => 22, 'value' => 1, 'num' => 4, 'is_succ' => 0, 'rate' => 0.1],
        54 => ['id' => 54, 'type' => 10, 'value' => 50, 'num' => 4, 'is_succ' => 1, 'rate' => 50.0],
        55 => ['id' => 55, 'type' => 27, 'value' => 5, 'num' => 4, 'is_succ' => 1, 'rate' => 40.0],
        56 => ['id' => 56, 'type' => 5, 'value' => 1200, 'num' => 4, 'is_succ' => 1, 'rate' => 60.0],
        57 => ['id' => 57, 'type' => 4, 'value' => 25, 'num' => 4, 'is_succ' => 1, 'rate' => 40.0],
        58 => ['id' => 58, 'type' => 7, 'value' => 4000, 'num' => 4, 'is_succ' => 1, 'rate' => 40.0],
        59 => ['id' => 59, 'type' => 17, 'value' => 1, 'num' => 4, 'is_succ' => 1, 'rate' => 0.1],
        60 => ['id' => 60, 'type' => 18, 'value' => 1, 'num' => 4, 'is_succ' => 1, 'rate' => 0.1],
        61 => ['id' => 61, 'type' => 19, 'value' => 1, 'num' => 4, 'is_succ' => 1, 'rate' => 0.1],
        62 => ['id' => 62, 'type' => 20, 'value' => 1, 'num' => 4, 'is_succ' => 1, 'rate' => 0.1],
        63 => ['id' => 63, 'type' => 21, 'value' => 1, 'num' => 4, 'is_succ' => 1, 'rate' => 0.1],
        64 => ['id' => 64, 'type' => 22, 'value' => 1, 'num' => 4, 'is_succ' => 1, 'rate' => 0.1],
        65 => ['id' => 65, 'type' => 26, 'value' => 47, 'num' => 4, 'is_succ' => 1, 'rate' => 0.01],
    ];
}
