<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 天下史诗数据表（旧版 zc_sanguo_epic）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Epic
{
    public const string TABLE = 'zc_sanguo_epic';

    /**
     * 按主键 id 索引，共 9 行。字段：
     *   - id (smallint) 序列id
     *   - reward (int) 奖励声望
     *   - price (int) 强行领取需要花费
     */
    public const array ROWS = [
        1 => ['id' => 1, 'reward' => 500, 'price' => 250],
        2 => ['id' => 2, 'reward' => 1000, 'price' => 500],
        3 => ['id' => 3, 'reward' => 1500, 'price' => 750],
        4 => ['id' => 4, 'reward' => 2500, 'price' => 1250],
        5 => ['id' => 5, 'reward' => 3500, 'price' => 1750],
        6 => ['id' => 6, 'reward' => 5000, 'price' => 2500],
        7 => ['id' => 7, 'reward' => 10000, 'price' => 5000],
        8 => ['id' => 8, 'reward' => 25000, 'price' => 12500],
        9 => ['id' => 9, 'reward' => 50000, 'price' => 25000],
    ];
}
