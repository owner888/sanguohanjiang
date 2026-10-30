<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 国家神殿商品表（旧版 zc_sanguo_country_shop）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class CountryShop
{
    public const string TABLE = 'zc_sanguo_country_shop';

    /**
     * 按主键 id 索引，共 25 行。字段：
     *   - id (int) 商品id
     *   - tab (tinyint) 所在tab(1卷轴，2宝石)
     *   - type (tinyint) 商品类型（1物攻，2物防，3策攻，4策防，5生命卷轴，6青龙，7玄武，8朱雀，9玄武）
     *   - value1 (int) 卷轴品级，宝石等级
     *   - value2 (int) 数量
     *   - sale_type (tinyint) 购买货币类型（1元宝，2神器碎片）
     *   - price (int) 货币价格
     */
    public const array ROWS = [
        1 => ['id' => 1, 'tab' => 1, 'type' => 1, 'value1' => 4, 'value2' => 5, 'sale_type' => 1, 'price' => 75],
        2 => ['id' => 2, 'tab' => 1, 'type' => 2, 'value1' => 4, 'value2' => 5, 'sale_type' => 1, 'price' => 75],
        3 => ['id' => 3, 'tab' => 1, 'type' => 3, 'value1' => 4, 'value2' => 5, 'sale_type' => 1, 'price' => 75],
        4 => ['id' => 4, 'tab' => 1, 'type' => 4, 'value1' => 4, 'value2' => 5, 'sale_type' => 1, 'price' => 75],
        5 => ['id' => 5, 'tab' => 1, 'type' => 5, 'value1' => 4, 'value2' => 5, 'sale_type' => 1, 'price' => 75],
        6 => ['id' => 6, 'tab' => 1, 'type' => 1, 'value1' => 5, 'value2' => 5, 'sale_type' => 1, 'price' => 150],
        7 => ['id' => 7, 'tab' => 1, 'type' => 2, 'value1' => 5, 'value2' => 5, 'sale_type' => 1, 'price' => 150],
        8 => ['id' => 8, 'tab' => 1, 'type' => 3, 'value1' => 5, 'value2' => 5, 'sale_type' => 1, 'price' => 150],
        9 => ['id' => 9, 'tab' => 1, 'type' => 4, 'value1' => 5, 'value2' => 5, 'sale_type' => 1, 'price' => 150],
        10 => ['id' => 10, 'tab' => 1, 'type' => 5, 'value1' => 5, 'value2' => 5, 'sale_type' => 1, 'price' => 150],
        11 => ['id' => 11, 'tab' => 1, 'type' => 1, 'value1' => 6, 'value2' => 5, 'sale_type' => 1, 'price' => 300],
        12 => ['id' => 12, 'tab' => 1, 'type' => 2, 'value1' => 6, 'value2' => 5, 'sale_type' => 1, 'price' => 300],
        13 => ['id' => 13, 'tab' => 1, 'type' => 3, 'value1' => 6, 'value2' => 5, 'sale_type' => 1, 'price' => 300],
        14 => ['id' => 14, 'tab' => 1, 'type' => 4, 'value1' => 6, 'value2' => 5, 'sale_type' => 1, 'price' => 300],
        15 => ['id' => 15, 'tab' => 1, 'type' => 5, 'value1' => 6, 'value2' => 5, 'sale_type' => 1, 'price' => 300],
        16 => ['id' => 16, 'tab' => 2, 'type' => 6, 'value1' => 8, 'value2' => 1, 'sale_type' => 2, 'price' => 1000],
        17 => ['id' => 17, 'tab' => 2, 'type' => 7, 'value1' => 8, 'value2' => 1, 'sale_type' => 2, 'price' => 750],
        18 => ['id' => 18, 'tab' => 2, 'type' => 8, 'value1' => 8, 'value2' => 1, 'sale_type' => 2, 'price' => 1000],
        19 => ['id' => 19, 'tab' => 2, 'type' => 9, 'value1' => 8, 'value2' => 1, 'sale_type' => 2, 'price' => 750],
        20 => ['id' => 20, 'tab' => 2, 'type' => 10, 'value1' => 8, 'value2' => 1, 'sale_type' => 2, 'price' => 1000],
        21 => ['id' => 21, 'tab' => 1, 'type' => 1, 'value1' => 7, 'value2' => 5, 'sale_type' => 1, 'price' => 600],
        22 => ['id' => 22, 'tab' => 1, 'type' => 2, 'value1' => 7, 'value2' => 5, 'sale_type' => 1, 'price' => 600],
        23 => ['id' => 23, 'tab' => 1, 'type' => 3, 'value1' => 7, 'value2' => 5, 'sale_type' => 1, 'price' => 600],
        24 => ['id' => 24, 'tab' => 1, 'type' => 4, 'value1' => 7, 'value2' => 5, 'sale_type' => 1, 'price' => 600],
        25 => ['id' => 25, 'tab' => 1, 'type' => 5, 'value1' => 7, 'value2' => 5, 'sale_type' => 1, 'price' => 600],
    ];
}
