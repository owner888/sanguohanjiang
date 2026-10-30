<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 副本购买次数数据表（旧版 zc_sanguo_copies_vip_buy_times）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class CopiesVipBuyTimes
{
    public const string TABLE = 'zc_sanguo_copies_vip_buy_times';

    /**
     * 按主键 id 索引，共 14 行。字段：
     *   - id (int) 自增ID
     *   - vip (int) vip等级
     *   - times (int) 购买次数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'vip' => 0, 'times' => 1],
        2 => ['id' => 2, 'vip' => 1, 'times' => 5],
        3 => ['id' => 3, 'vip' => 2, 'times' => 5],
        4 => ['id' => 4, 'vip' => 3, 'times' => 5],
        5 => ['id' => 5, 'vip' => 4, 'times' => 10],
        6 => ['id' => 6, 'vip' => 5, 'times' => 10],
        7 => ['id' => 7, 'vip' => 6, 'times' => 15],
        8 => ['id' => 8, 'vip' => 7, 'times' => 20],
        9 => ['id' => 9, 'vip' => 8, 'times' => 25],
        10 => ['id' => 10, 'vip' => 9, 'times' => 30],
        11 => ['id' => 11, 'vip' => 10, 'times' => 35],
        12 => ['id' => 12, 'vip' => 11, 'times' => 40],
        13 => ['id' => 13, 'vip' => 12, 'times' => 45],
        14 => ['id' => 14, 'vip' => 13, 'times' => 60],
    ];
}
