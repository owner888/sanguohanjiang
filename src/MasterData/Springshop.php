<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 春节商城(活动)表（旧版 zc_sanguo_springshop）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Springshop
{
    public const string TABLE = 'zc_sanguo_springshop';

    /**
     * 按主键 id 索引，共 13 行。字段：
     *   - id (int) 礼包ID
     *   - number (int) 每天限购的数量
     *   - name (varchar) 礼包名称
     *   - type1 (int) 奖品类型1
     *   - type1_num (int) 奖品类型1数量
     *   - type2 (int) 奖品类型2
     *   - type2_num (int) 奖品类型2数量
     *   - type3 (int) 奖品类型3
     *   - type3_num (int) 奖品类型3数量
     *   - type4 (int) 奖品类型4
     *   - type4_num (int) 奖品类型4数量
     *   - buytype (tinyint) 购买礼包所需要的类型(1:元宝、2:紫宝石)
     *   - price (int) 购买礼包所需要的价格
     *   - buytype1 (tinyint) 类型
     *   - price1 (int) 数量
     *   - discount (int) 折扣
     */
    public const array ROWS = [
        1 => ['id' => 1, 'number' => 20, 'name' => '曹操', 'type1' => 13, 'type1_num' => 71, 'type2' => 0, 'type2_num' => 0, 'type3' => 0, 'type3_num' => 0, 'type4' => 0, 'type4_num' => 0, 'buytype' => 2, 'price' => 5500, 'buytype1' => 3, 'price1' => 1000000, 'discount' => 80],
        2 => ['id' => 2, 'number' => 20, 'name' => '宁令哥', 'type1' => 13, 'type1_num' => 127, 'type2' => 0, 'type2_num' => 0, 'type3' => 0, 'type3_num' => 0, 'type4' => 0, 'type4_num' => 0, 'buytype' => 2, 'price' => 6000, 'buytype1' => 3, 'price1' => 600000, 'discount' => 80],
        3 => ['id' => 3, 'number' => 20, 'name' => '神甘宁', 'type1' => 13, 'type1_num' => 105, 'type2' => 0, 'type2_num' => 0, 'type3' => 0, 'type3_num' => 0, 'type4' => 0, 'type4_num' => 0, 'buytype' => 2, 'price' => 9000, 'buytype1' => 3, 'price1' => 900000, 'discount' => 80],
        4 => ['id' => 4, 'number' => 20, 'name' => '神曹植', 'type1' => 13, 'type1_num' => 138, 'type2' => 0, 'type2_num' => 0, 'type3' => 0, 'type3_num' => 0, 'type4' => 0, 'type4_num' => 0, 'buytype' => 2, 'price' => 10000, 'buytype1' => 3, 'price1' => 1000000, 'discount' => 80],
        5 => ['id' => 5, 'number' => 20, 'name' => '神许褚', 'type1' => 13, 'type1_num' => 115, 'type2' => 0, 'type2_num' => 0, 'type3' => 0, 'type3_num' => 0, 'type4' => 0, 'type4_num' => 0, 'buytype' => 2, 'price' => 20000, 'buytype1' => 3, 'price1' => 2000000, 'discount' => 80],
        6 => ['id' => 6, 'number' => 20, 'name' => '诸葛瑾', 'type1' => 13, 'type1_num' => 150, 'type2' => 0, 'type2_num' => 0, 'type3' => 0, 'type3_num' => 0, 'type4' => 0, 'type4_num' => 0, 'buytype' => 2, 'price' => 20000, 'buytype1' => 3, 'price1' => 2000000, 'discount' => 80],
        7 => ['id' => 7, 'number' => 20, 'name' => '神周瑜', 'type1' => 13, 'type1_num' => 118, 'type2' => 0, 'type2_num' => 0, 'type3' => 0, 'type3_num' => 0, 'type4' => 0, 'type4_num' => 0, 'buytype' => 2, 'price' => 30000, 'buytype1' => 3, 'price1' => 3000000, 'discount' => 80],
        8 => ['id' => 8, 'number' => 20, 'name' => '萧峰', 'type1' => 13, 'type1_num' => 122, 'type2' => 0, 'type2_num' => 0, 'type3' => 0, 'type3_num' => 0, 'type4' => 0, 'type4_num' => 0, 'buytype' => 2, 'price' => 35000, 'buytype1' => 3, 'price1' => 3500000, 'discount' => 80],
        9 => ['id' => 9, 'number' => 20, 'name' => '神貂蝉', 'type1' => 13, 'type1_num' => 131, 'type2' => 0, 'type2_num' => 0, 'type3' => 0, 'type3_num' => 0, 'type4' => 0, 'type4_num' => 0, 'buytype' => 2, 'price' => 37000, 'buytype1' => 3, 'price1' => 3700000, 'discount' => 80],
        10 => ['id' => 10, 'number' => 20, 'name' => '成吉思汗', 'type1' => 13, 'type1_num' => 125, 'type2' => 0, 'type2_num' => 0, 'type3' => 0, 'type3_num' => 0, 'type4' => 0, 'type4_num' => 0, 'buytype' => 2, 'price' => 45000, 'buytype1' => 3, 'price1' => 4500000, 'discount' => 80],
        11 => ['id' => 11, 'number' => 20, 'name' => '神孙权', 'type1' => 13, 'type1_num' => 156, 'type2' => 0, 'type2_num' => 0, 'type3' => 0, 'type3_num' => 0, 'type4' => 0, 'type4_num' => 0, 'buytype' => 2, 'price' => 60000, 'buytype1' => 3, 'price1' => 6000000, 'discount' => 80],
        12 => ['id' => 12, 'number' => 20, 'name' => '神吕蒙', 'type1' => 13, 'type1_num' => 168, 'type2' => 0, 'type2_num' => 0, 'type3' => 0, 'type3_num' => 0, 'type4' => 0, 'type4_num' => 0, 'buytype' => 2, 'price' => 75000, 'buytype1' => 3, 'price1' => 8000000, 'discount' => 80],
        13 => ['id' => 13, 'number' => 20, 'name' => '神诸葛亮', 'type1' => 13, 'type1_num' => 167, 'type2' => 0, 'type2_num' => 0, 'type3' => 0, 'type3_num' => 0, 'type4' => 0, 'type4_num' => 0, 'buytype' => 2, 'price' => 75000, 'buytype1' => 3, 'price1' => 8000000, 'discount' => 80],
    ];
}
