<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 春节送福奖励表（旧版 zc_sanguo_act_newyear_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActNewyearReward
{
    public const string TABLE = 'zc_sanguo_act_newyear_reward';

    /**
     * 按主键 id 索引，共 19 行。字段：
     *   - id (int) 自增ID
     *   - type (int) 奖励类型
     *   - min_num (int) 最小数量
     *   - max_num (int) 最大数量
     *   - value (int) 奖励倍数
     *   - rate (int) 权重
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => 15, 'min_num' => 9, 'max_num' => 11, 'value' => 1, 'rate' => 31],
        2 => ['id' => 2, 'type' => 4, 'min_num' => 5, 'max_num' => 6, 'value' => 1, 'rate' => 48],
        3 => ['id' => 3, 'type' => 8, 'min_num' => 1000, 'max_num' => 1200, 'value' => 1, 'rate' => 13],
        4 => ['id' => 4, 'type' => 5, 'min_num' => 260, 'max_num' => 320, 'value' => 1, 'rate' => 10],
        5 => ['id' => 5, 'type' => 14, 'min_num' => 5, 'max_num' => 6, 'value' => 1, 'rate' => 12],
        6 => ['id' => 6, 'type' => 15, 'min_num' => 9, 'max_num' => 11, 'value' => 2, 'rate' => 31],
        7 => ['id' => 7, 'type' => 4, 'min_num' => 5, 'max_num' => 6, 'value' => 2, 'rate' => 48],
        8 => ['id' => 8, 'type' => 8, 'min_num' => 1000, 'max_num' => 1200, 'value' => 2, 'rate' => 13],
        9 => ['id' => 9, 'type' => 5, 'min_num' => 260, 'max_num' => 320, 'value' => 2, 'rate' => 13],
        10 => ['id' => 10, 'type' => 14, 'min_num' => 5, 'max_num' => 6, 'value' => 2, 'rate' => 9],
        11 => ['id' => 11, 'type' => 15, 'min_num' => 9, 'max_num' => 11, 'value' => 3, 'rate' => 31],
        12 => ['id' => 12, 'type' => 4, 'min_num' => 5, 'max_num' => 6, 'value' => 3, 'rate' => 48],
        13 => ['id' => 13, 'type' => 8, 'min_num' => 1000, 'max_num' => 1200, 'value' => 3, 'rate' => 13],
        14 => ['id' => 14, 'type' => 5, 'min_num' => 260, 'max_num' => 320, 'value' => 3, 'rate' => 13],
        15 => ['id' => 15, 'type' => 14, 'min_num' => 5, 'max_num' => 6, 'value' => 3, 'rate' => 9],
        16 => ['id' => 16, 'type' => 4, 'min_num' => 5, 'max_num' => 6, 'value' => 5, 'rate' => 35],
        17 => ['id' => 17, 'type' => 8, 'min_num' => 1000, 'max_num' => 1200, 'value' => 5, 'rate' => 15],
        18 => ['id' => 18, 'type' => 5, 'min_num' => 260, 'max_num' => 320, 'value' => 5, 'rate' => 15],
        19 => ['id' => 19, 'type' => 14, 'min_num' => 5, 'max_num' => 6, 'value' => 5, 'rate' => 15],
    ];
}
