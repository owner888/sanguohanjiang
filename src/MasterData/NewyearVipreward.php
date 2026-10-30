<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 春节回馈奖励表（旧版 zc_sanguo_newyear_vipreward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class NewyearVipreward
{
    public const string TABLE = 'zc_sanguo_newyear_vipreward';

    /**
     * 按主键 id 索引，共 13 行。字段：
     *   - id (int) 奖励id
     *   - vip (tinyint) 需要VIP等级
     *   - type (tinyint) 奖励类型
     *   - value (int) 奖励参数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'vip' => 1, 'type' => 2, 'value' => 50],
        2 => ['id' => 2, 'vip' => 2, 'type' => 2, 'value' => 100],
        3 => ['id' => 3, 'vip' => 3, 'type' => 2, 'value' => 150],
        4 => ['id' => 4, 'vip' => 4, 'type' => 1, 'value' => 200],
        5 => ['id' => 5, 'vip' => 5, 'type' => 1, 'value' => 250],
        6 => ['id' => 6, 'vip' => 6, 'type' => 1, 'value' => 300],
        7 => ['id' => 7, 'vip' => 7, 'type' => 3, 'value' => 300],
        8 => ['id' => 8, 'vip' => 8, 'type' => 3, 'value' => 500],
        9 => ['id' => 9, 'vip' => 9, 'type' => 3, 'value' => 800],
        10 => ['id' => 10, 'vip' => 10, 'type' => 3, 'value' => 1000],
        11 => ['id' => 11, 'vip' => 11, 'type' => 3, 'value' => 2000],
        12 => ['id' => 12, 'vip' => 12, 'type' => 3, 'value' => 4000],
        13 => ['id' => 13, 'vip' => 13, 'type' => 3, 'value' => 8000],
    ];
}
