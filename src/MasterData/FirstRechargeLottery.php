<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 首充天命转盘奖励表（旧版 zc_sanguo_first_recharge_lottery）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class FirstRechargeLottery
{
    public const string TABLE = 'zc_sanguo_first_recharge_lottery';

    /**
     * 按主键 id 索引，共 12 行。字段：
     *   - id (int) 主键ID
     *   - gettype (tinyint) 奖励类型
     *   - getvalue (int) 奖励数值
     *   - getrate (float) 奖励权重
     */
    public const array ROWS = [
        1 => ['id' => 1, 'gettype' => 1, 'getvalue' => 7, 'getrate' => 50.0],
        2 => ['id' => 2, 'gettype' => 1, 'getvalue' => 8, 'getrate' => 50.0],
        3 => ['id' => 3, 'gettype' => 1, 'getvalue' => 21, 'getrate' => 50.0],
        4 => ['id' => 4, 'gettype' => 1, 'getvalue' => 6, 'getrate' => 45.0],
        5 => ['id' => 5, 'gettype' => 1, 'getvalue' => 63, 'getrate' => 40.0],
        6 => ['id' => 6, 'gettype' => 1, 'getvalue' => 34, 'getrate' => 40.0],
        7 => ['id' => 7, 'gettype' => 1, 'getvalue' => 33, 'getrate' => 30.0],
        8 => ['id' => 8, 'gettype' => 1, 'getvalue' => 52, 'getrate' => 20.0],
        9 => ['id' => 9, 'gettype' => 1, 'getvalue' => 54, 'getrate' => 20.0],
        10 => ['id' => 10, 'gettype' => 1, 'getvalue' => 57, 'getrate' => 15.0],
        11 => ['id' => 11, 'gettype' => 1, 'getvalue' => 60, 'getrate' => 10.0],
        12 => ['id' => 12, 'gettype' => 1, 'getvalue' => 50, 'getrate' => 5.0],
    ];
}
