<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 团购打折表（旧版 zc_sanguo_christmasbuy_discount）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ChristmasbuyDiscount
{
    public const string TABLE = 'zc_sanguo_christmasbuy_discount';

    /**
     * 按主键 id 索引，共 6 行。字段：
     *   - id (int) ID
     *   - price (float) 价格
     *   - discount (float) 折扣
     */
    public const array ROWS = [
        1 => ['id' => 1, 'price' => 1000.0, 'discount' => 9.5],
        2 => ['id' => 2, 'price' => 5000.0, 'discount' => 9.0],
        3 => ['id' => 3, 'price' => 8000.0, 'discount' => 8.5],
        4 => ['id' => 4, 'price' => 10000.0, 'discount' => 8.0],
        5 => ['id' => 5, 'price' => 20000.0, 'discount' => 7.5],
        6 => ['id' => 6, 'price' => 30000.0, 'discount' => 7.0],
    ];
}
