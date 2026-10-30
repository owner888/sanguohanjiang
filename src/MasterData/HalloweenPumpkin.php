<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 万圣节砸南瓜（旧版 zc_sanguo_halloween_pumpkin）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class HalloweenPumpkin
{
    public const string TABLE = 'zc_sanguo_halloween_pumpkin';

    /**
     * 按主键 id 索引，共 38 行。字段：
     *   - id (int) 自增ID
     *   - type (int) 奖励类型
     *   - value (int) 奖励数量或者装备精华id
     *   - value1 (int) 奖励数量2(装备或者精华数量)
     *   - rate (int) 权重
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => 2, 'value' => 150000, 'value1' => 0, 'rate' => 15],
        2 => ['id' => 2, 'type' => 2, 'value' => 200000, 'value1' => 0, 'rate' => 20],
        3 => ['id' => 3, 'type' => 2, 'value' => 220000, 'value1' => 0, 'rate' => 10],
        4 => ['id' => 4, 'type' => 10, 'value' => 6, 'value1' => 0, 'rate' => 20],
        5 => ['id' => 5, 'type' => 10, 'value' => 7, 'value1' => 0, 'rate' => 10],
        6 => ['id' => 6, 'type' => 10, 'value' => 8, 'value1' => 0, 'rate' => 5],
        7 => ['id' => 7, 'type' => 4, 'value' => 6, 'value1' => 0, 'rate' => 10],
        8 => ['id' => 8, 'type' => 4, 'value' => 7, 'value1' => 0, 'rate' => 5],
        9 => ['id' => 9, 'type' => 4, 'value' => 8, 'value1' => 0, 'rate' => 1],
        10 => ['id' => 10, 'type' => 7, 'value' => 200, 'value1' => 0, 'rate' => 20],
        11 => ['id' => 11, 'type' => 7, 'value' => 300, 'value1' => 0, 'rate' => 10],
        12 => ['id' => 12, 'type' => 7, 'value' => 400, 'value1' => 0, 'rate' => 10],
        13 => ['id' => 13, 'type' => 7, 'value' => 500, 'value1' => 0, 'rate' => 5],
        14 => ['id' => 14, 'type' => 5, 'value' => 400, 'value1' => 0, 'rate' => 15],
        15 => ['id' => 15, 'type' => 5, 'value' => 600, 'value1' => 0, 'rate' => 10],
        16 => ['id' => 16, 'type' => 3, 'value' => 10, 'value1' => 0, 'rate' => 15],
        17 => ['id' => 17, 'type' => 3, 'value' => 14, 'value1' => 0, 'rate' => 10],
        18 => ['id' => 18, 'type' => 3, 'value' => 16, 'value1' => 0, 'rate' => 5],
        19 => ['id' => 19, 'type' => 14, 'value' => 500, 'value1' => 0, 'rate' => 10],
        20 => ['id' => 20, 'type' => 14, 'value' => 1000, 'value1' => 0, 'rate' => 5],
        21 => ['id' => 21, 'type' => 15, 'value' => 40, 'value1' => 0, 'rate' => 10],
        22 => ['id' => 22, 'type' => 15, 'value' => 60, 'value1' => 0, 'rate' => 5],
        23 => ['id' => 23, 'type' => 16, 'value' => 10, 'value1' => 0, 'rate' => 5],
        24 => ['id' => 24, 'type' => 27, 'value' => 6, 'value1' => 0, 'rate' => 15],
        25 => ['id' => 25, 'type' => 27, 'value' => 8, 'value1' => 0, 'rate' => 10],
        26 => ['id' => 26, 'type' => 27, 'value' => 10, 'value1' => 0, 'rate' => 5],
        27 => ['id' => 27, 'type' => 28, 'value' => 2, 'value1' => 0, 'rate' => 10],
        28 => ['id' => 28, 'type' => 28, 'value' => 4, 'value1' => 0, 'rate' => 5],
        29 => ['id' => 29, 'type' => 28, 'value' => 6, 'value1' => 0, 'rate' => 5],
        30 => ['id' => 30, 'type' => 30, 'value' => 1, 'value1' => 0, 'rate' => 30],
        31 => ['id' => 31, 'type' => 30, 'value' => 2, 'value1' => 0, 'rate' => 30],
        32 => ['id' => 32, 'type' => 30, 'value' => 3, 'value1' => 0, 'rate' => 25],
        33 => ['id' => 33, 'type' => 30, 'value' => 4, 'value1' => 0, 'rate' => 15],
        34 => ['id' => 34, 'type' => 8, 'value' => 72, 'value1' => 1, 'rate' => 0],
        35 => ['id' => 35, 'type' => 8, 'value' => 73, 'value1' => 1, 'rate' => 0],
        36 => ['id' => 36, 'type' => 8, 'value' => 74, 'value1' => 1, 'rate' => 0],
        37 => ['id' => 37, 'type' => 8, 'value' => 75, 'value1' => 1, 'rate' => 0],
        38 => ['id' => 38, 'type' => 8, 'value' => 76, 'value1' => 1, 'rate' => 0],
    ];
}
