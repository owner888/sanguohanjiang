<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 万圣节糖果（旧版 zc_sanguo_halloween_candy）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class HalloweenCandy
{
    public const string TABLE = 'zc_sanguo_halloween_candy';

    /**
     * 按主键 id 索引，共 23 行。字段：
     *   - id (int) 自增ID
     *   - type (int) 奖励类型
     *   - value (int) 奖励数量或者装备精华id
     *   - value1 (int) 奖励数量2(装备或者精华数量)
     *   - rate (int) 权重
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => 10, 'value' => 4, 'value1' => 0, 'rate' => 20],
        2 => ['id' => 2, 'type' => 10, 'value' => 6, 'value1' => 0, 'rate' => 5],
        3 => ['id' => 3, 'type' => 10, 'value' => 8, 'value1' => 0, 'rate' => 5],
        4 => ['id' => 4, 'type' => 4, 'value' => 2, 'value1' => 0, 'rate' => 10],
        5 => ['id' => 5, 'type' => 4, 'value' => 4, 'value1' => 0, 'rate' => 5],
        6 => ['id' => 6, 'type' => 4, 'value' => 6, 'value1' => 0, 'rate' => 1],
        7 => ['id' => 7, 'type' => 7, 'value' => 80, 'value1' => 0, 'rate' => 20],
        8 => ['id' => 8, 'type' => 7, 'value' => 90, 'value1' => 0, 'rate' => 10],
        9 => ['id' => 9, 'type' => 7, 'value' => 100, 'value1' => 0, 'rate' => 5],
        10 => ['id' => 10, 'type' => 5, 'value' => 30, 'value1' => 0, 'rate' => 15],
        11 => ['id' => 11, 'type' => 5, 'value' => 50, 'value1' => 0, 'rate' => 10],
        12 => ['id' => 12, 'type' => 3, 'value' => 4, 'value1' => 0, 'rate' => 10],
        13 => ['id' => 13, 'type' => 3, 'value' => 6, 'value1' => 0, 'rate' => 5],
        14 => ['id' => 14, 'type' => 3, 'value' => 8, 'value1' => 0, 'rate' => 5],
        15 => ['id' => 15, 'type' => 11, 'value' => 8, 'value1' => 0, 'rate' => 20],
        16 => ['id' => 16, 'type' => 11, 'value' => 10, 'value1' => 0, 'rate' => 10],
        17 => ['id' => 17, 'type' => 11, 'value' => 12, 'value1' => 0, 'rate' => 5],
        18 => ['id' => 18, 'type' => 6, 'value' => 3000, 'value1' => 0, 'rate' => 10],
        19 => ['id' => 19, 'type' => 6, 'value' => 3100, 'value1' => 0, 'rate' => 10],
        20 => ['id' => 20, 'type' => 6, 'value' => 3200, 'value1' => 0, 'rate' => 5],
        21 => ['id' => 21, 'type' => 29, 'value' => 1, 'value1' => 0, 'rate' => 50],
        22 => ['id' => 22, 'type' => 29, 'value' => 2, 'value1' => 0, 'rate' => 20],
        23 => ['id' => 23, 'type' => 29, 'value' => 3, 'value1' => 0, 'rate' => 10],
    ];
}
