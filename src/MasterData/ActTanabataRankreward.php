<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 七夕活动充值排行奖励表（旧版 zc_sanguo_act_tanabata_rankreward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActTanabataRankreward
{
    public const string TABLE = 'zc_sanguo_act_tanabata_rankreward';

    /**
     * 按主键 rank 索引，共 20 行。字段：
     *   - rank (int) 排行
     *   - type (int) 奖励类型
     *   - num (int) 奖励数量
     *   - value (int) 奖励参数
     */
    public const array ROWS = [
        1 => ['rank' => 1, 'type' => 4, 'num' => 2000, 'value' => 0],
        2 => ['rank' => 2, 'type' => 4, 'num' => 1600, 'value' => 0],
        3 => ['rank' => 3, 'type' => 4, 'num' => 1400, 'value' => 0],
        4 => ['rank' => 4, 'type' => 4, 'num' => 1200, 'value' => 0],
        5 => ['rank' => 5, 'type' => 4, 'num' => 1100, 'value' => 0],
        6 => ['rank' => 6, 'type' => 4, 'num' => 1000, 'value' => 0],
        7 => ['rank' => 7, 'type' => 4, 'num' => 900, 'value' => 0],
        8 => ['rank' => 8, 'type' => 4, 'num' => 800, 'value' => 0],
        9 => ['rank' => 9, 'type' => 4, 'num' => 700, 'value' => 0],
        10 => ['rank' => 10, 'type' => 4, 'num' => 600, 'value' => 0],
        11 => ['rank' => 11, 'type' => 4, 'num' => 500, 'value' => 0],
        12 => ['rank' => 12, 'type' => 4, 'num' => 400, 'value' => 0],
        13 => ['rank' => 13, 'type' => 4, 'num' => 320, 'value' => 0],
        14 => ['rank' => 14, 'type' => 4, 'num' => 260, 'value' => 0],
        15 => ['rank' => 15, 'type' => 4, 'num' => 200, 'value' => 0],
        16 => ['rank' => 16, 'type' => 4, 'num' => 140, 'value' => 0],
        17 => ['rank' => 17, 'type' => 4, 'num' => 100, 'value' => 0],
        18 => ['rank' => 18, 'type' => 4, 'num' => 60, 'value' => 0],
        19 => ['rank' => 19, 'type' => 4, 'num' => 40, 'value' => 0],
        20 => ['rank' => 20, 'type' => 4, 'num' => 20, 'value' => 0],
    ];
}
