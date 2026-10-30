<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 商品信息表（旧版 zc_sanguo_shop）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Shop
{
    public const string TABLE = 'zc_sanguo_shop';

    /**
     * 按主键 position 索引，共 12 行。字段：
     *   - position (tinyint)
     *   - equipid (mediumint) 装备ID
     *   - type (tinyint) 商品类型（1 金币、2 银币）
     *   - price (mediumint) 价格
     *   - maxtimes (int) 每日限购次数
     */
    public const array ROWS = [
        1 => ['position' => 1, 'equipid' => 7, 'type' => 1, 'price' => 50, 'maxtimes' => 50],
        2 => ['position' => 2, 'equipid' => 9, 'type' => 1, 'price' => 200, 'maxtimes' => 30],
        3 => ['position' => 3, 'equipid' => 10, 'type' => 1, 'price' => 300, 'maxtimes' => 30],
        4 => ['position' => 4, 'equipid' => 11, 'type' => 1, 'price' => 700, 'maxtimes' => 10],
        5 => ['position' => 5, 'equipid' => 12, 'type' => 1, 'price' => 1000, 'maxtimes' => 10],
        6 => ['position' => 6, 'equipid' => 13, 'type' => 1, 'price' => 2500, 'maxtimes' => 8],
        7 => ['position' => 7, 'equipid' => 14, 'type' => 1, 'price' => 3000, 'maxtimes' => 8],
        8 => ['position' => 8, 'equipid' => 15, 'type' => 1, 'price' => 5000, 'maxtimes' => 5],
        9 => ['position' => 9, 'equipid' => 53, 'type' => 1, 'price' => 6000, 'maxtimes' => 4],
        10 => ['position' => 10, 'equipid' => 54, 'type' => 1, 'price' => 7500, 'maxtimes' => 3],
        11 => ['position' => 11, 'equipid' => 55, 'type' => 1, 'price' => 9000, 'maxtimes' => 2],
        12 => ['position' => 12, 'equipid' => 56, 'type' => 1, 'price' => 20000, 'maxtimes' => 10],
    ];
}
