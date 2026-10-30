<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 过关斩将每层宝箱奖励表（旧版 zc_sanguo_passgeneral_box_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class PassgeneralBoxReward
{
    public const string TABLE = 'zc_sanguo_passgeneral_box_reward';

    /**
     * 按主键 id 索引，共 55 行。字段：
     *   - id (int) ID
     *   - num (int) 突破层数
     *   - type1 (int) 奖励物品1类型
     *   - value1 (int) 参数
     *   - type2 (int) 奖励物品2类型
     *   - value2 (int) 参数
     *   - type3 (int) 奖励物品3类型
     *   - value3 (int) 参数
     *   - rate (float) 权重
     */
    public const array ROWS = [
        1 => ['id' => 1, 'num' => 1, 'type1' => 27, 'value1' => 10, 'type2' => 4, 'value2' => 50, 'type3' => 7, 'value3' => 5000, 'rate' => 0.0],
        2 => ['id' => 2, 'num' => 2, 'type1' => 27, 'value1' => 20, 'type2' => 4, 'value2' => 100, 'type3' => 7, 'value3' => 10000, 'rate' => 0.0],
        3 => ['id' => 3, 'num' => 3, 'type1' => 27, 'value1' => 30, 'type2' => 4, 'value2' => 150, 'type3' => 7, 'value3' => 15000, 'rate' => 0.0],
        4 => ['id' => 4, 'num' => 4, 'type1' => 4, 'value1' => 100, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 50.0],
        5 => ['id' => 5, 'num' => 4, 'type1' => 4, 'value1' => 120, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 40.0],
        6 => ['id' => 6, 'num' => 4, 'type1' => 4, 'value1' => 140, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 30.0],
        7 => ['id' => 7, 'num' => 4, 'type1' => 4, 'value1' => 160, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 20.0],
        8 => ['id' => 8, 'num' => 4, 'type1' => 4, 'value1' => 200, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 10.0],
        9 => ['id' => 9, 'num' => 4, 'type1' => 10, 'value1' => 200, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 50.0],
        10 => ['id' => 10, 'num' => 4, 'type1' => 10, 'value1' => 220, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 40.0],
        11 => ['id' => 11, 'num' => 4, 'type1' => 10, 'value1' => 240, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 30.0],
        12 => ['id' => 12, 'num' => 4, 'type1' => 10, 'value1' => 260, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 20.0],
        13 => ['id' => 13, 'num' => 4, 'type1' => 10, 'value1' => 300, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 10.0],
        14 => ['id' => 14, 'num' => 4, 'type1' => 7, 'value1' => 30000, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 50.0],
        15 => ['id' => 15, 'num' => 4, 'type1' => 7, 'value1' => 40000, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 40.0],
        16 => ['id' => 16, 'num' => 4, 'type1' => 7, 'value1' => 50000, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 30.0],
        17 => ['id' => 17, 'num' => 4, 'type1' => 7, 'value1' => 60000, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 20.0],
        18 => ['id' => 18, 'num' => 4, 'type1' => 7, 'value1' => 80000, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 10.0],
        19 => ['id' => 19, 'num' => 4, 'type1' => 27, 'value1' => 40, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 50.0],
        20 => ['id' => 20, 'num' => 4, 'type1' => 27, 'value1' => 50, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 30.0],
        21 => ['id' => 21, 'num' => 4, 'type1' => 27, 'value1' => 60, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 10.0],
        22 => ['id' => 22, 'num' => 4, 'type1' => 20, 'value1' => 1, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 5.0],
        23 => ['id' => 23, 'num' => 4, 'type1' => 17, 'value1' => 1, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 5.0],
        24 => ['id' => 24, 'num' => 4, 'type1' => 21, 'value1' => 1, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 5.0],
        25 => ['id' => 25, 'num' => 4, 'type1' => 18, 'value1' => 1, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 5.0],
        26 => ['id' => 26, 'num' => 4, 'type1' => 22, 'value1' => 1, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 5.0],
        27 => ['id' => 27, 'num' => 4, 'type1' => 19, 'value1' => 1, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 5.0],
        28 => ['id' => 28, 'num' => 4, 'type1' => 26, 'value1' => 101, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.01],
        29 => ['id' => 29, 'num' => 4, 'type1' => 26, 'value1' => 51, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.01],
        30 => ['id' => 30, 'num' => 4, 'type1' => 26, 'value1' => 71, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.01],
        31 => ['id' => 31, 'num' => 4, 'type1' => 26, 'value1' => 127, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.01],
        32 => ['id' => 32, 'num' => 4, 'type1' => 26, 'value1' => 89, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.01],
        33 => ['id' => 33, 'num' => 4, 'type1' => 26, 'value1' => 120, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.01],
        34 => ['id' => 34, 'num' => 4, 'type1' => 26, 'value1' => 133, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.01],
        35 => ['id' => 35, 'num' => 4, 'type1' => 26, 'value1' => 105, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.01],
        36 => ['id' => 36, 'num' => 4, 'type1' => 26, 'value1' => 113, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.01],
        38 => ['id' => 38, 'num' => 4, 'type1' => 26, 'value1' => 91, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.01],
        39 => ['id' => 39, 'num' => 4, 'type1' => 8, 'value1' => 62, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 3.0],
        40 => ['id' => 40, 'num' => 4, 'type1' => 8, 'value1' => 65, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 3.0],
        41 => ['id' => 41, 'num' => 4, 'type1' => 8, 'value1' => 59, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 3.0],
        42 => ['id' => 42, 'num' => 4, 'type1' => 8, 'value1' => 68, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 3.0],
        43 => ['id' => 43, 'num' => 4, 'type1' => 8, 'value1' => 74, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.1],
        44 => ['id' => 44, 'num' => 4, 'type1' => 8, 'value1' => 75, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.1],
        45 => ['id' => 45, 'num' => 4, 'type1' => 8, 'value1' => 72, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.1],
        46 => ['id' => 46, 'num' => 4, 'type1' => 8, 'value1' => 73, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.1],
        47 => ['id' => 47, 'num' => 4, 'type1' => 8, 'value1' => 76, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.1],
        48 => ['id' => 48, 'num' => 4, 'type1' => 26, 'value1' => 92, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.001],
        49 => ['id' => 49, 'num' => 4, 'type1' => 26, 'value1' => 106, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.001],
        51 => ['id' => 51, 'num' => 4, 'type1' => 26, 'value1' => 111, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.001],
        52 => ['id' => 52, 'num' => 4, 'type1' => 26, 'value1' => 129, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.001],
        53 => ['id' => 53, 'num' => 4, 'type1' => 26, 'value1' => 130, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.001],
        54 => ['id' => 54, 'num' => 4, 'type1' => 26, 'value1' => 131, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.001],
        56 => ['id' => 56, 'num' => 4, 'type1' => 26, 'value1' => 107, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.001],
        57 => ['id' => 57, 'num' => 4, 'type1' => 26, 'value1' => 110, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.001],
        58 => ['id' => 58, 'num' => 4, 'type1' => 26, 'value1' => 47, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0, 'rate' => 0.001],
    ];
}
