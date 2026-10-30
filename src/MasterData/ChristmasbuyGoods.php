<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 团购商品表（旧版 zc_sanguo_christmasbuy_goods）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ChristmasbuyGoods
{
    public const string TABLE = 'zc_sanguo_christmasbuy_goods';

    /**
     * 按主键 id 索引，共 31 行。字段：
     *   - id (int) ID
     *   - name (varchar) 商品名称
     *   - type (int) 商品类型
     *   - value (int) 参数
     *   - price (float) 商品价格
     *   - gnum (int) 商品数量
     *   - num (int) 购买数量
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '黄宝石', 'type' => 2, 'value' => 0, 'price' => 480.0, 'gnum' => 100, 'num' => 80],
        2 => ['id' => 2, 'name' => '原魂', 'type' => 5, 'value' => 0, 'price' => 800.0, 'gnum' => 5000, 'num' => 80],
        3 => ['id' => 3, 'name' => '声望', 'type' => 8, 'value' => 0, 'price' => 800.0, 'gnum' => 5000, 'num' => 80],
        4 => ['id' => 4, 'name' => '玫瑰', 'type' => 12, 'value' => 0, 'price' => 1600.0, 'gnum' => 50, 'num' => 80],
        5 => ['id' => 5, 'name' => '钻石', 'type' => 13, 'value' => 0, 'price' => 1600.0, 'gnum' => 20, 'num' => 80],
        6 => ['id' => 6, 'name' => '黄宝石', 'type' => 2, 'value' => 0, 'price' => 4800.0, 'gnum' => 1000, 'num' => 80],
        7 => ['id' => 7, 'name' => '原魂', 'type' => 5, 'value' => 0, 'price' => 8000.0, 'gnum' => 50000, 'num' => 80],
        8 => ['id' => 8, 'name' => '声望', 'type' => 8, 'value' => 0, 'price' => 8000.0, 'gnum' => 50000, 'num' => 80],
        9 => ['id' => 9, 'name' => '太皇真龙剑', 'type' => 15, 'value' => 59, 'price' => 14000.0, 'gnum' => 1, 'num' => 80],
        10 => ['id' => 10, 'name' => '天地人古经', 'type' => 15, 'value' => 68, 'price' => 14000.0, 'gnum' => 1, 'num' => 80],
        11 => ['id' => 11, 'name' => '太极阴阳甲', 'type' => 15, 'value' => 62, 'price' => 8800.0, 'gnum' => 1, 'num' => 80],
        12 => ['id' => 12, 'name' => '九龙金丝袍', 'type' => 15, 'value' => 65, 'price' => 8800.0, 'gnum' => 1, 'num' => 80],
        13 => ['id' => 13, 'name' => '温螭玉', 'type' => 14, 'value' => 23, 'price' => 1200.0, 'gnum' => 5, 'num' => 80],
        14 => ['id' => 14, 'name' => '地煞珠', 'type' => 14, 'value' => 43, 'price' => 3200.0, 'gnum' => 5, 'num' => 80],
        15 => ['id' => 15, 'name' => '紫雀羽', 'type' => 14, 'value' => 20, 'price' => 1200.0, 'gnum' => 5, 'num' => 80],
        16 => ['id' => 16, 'name' => '精炼钢', 'type' => 14, 'value' => 13, 'price' => 1200.0, 'gnum' => 5, 'num' => 80],
        17 => ['id' => 17, 'name' => '圣兽心', 'type' => 14, 'value' => 39, 'price' => 2400.0, 'gnum' => 3, 'num' => 80],
        18 => ['id' => 18, 'name' => '女娲玉', 'type' => 14, 'value' => 35, 'price' => 2400.0, 'gnum' => 3, 'num' => 80],
        19 => ['id' => 19, 'name' => '灵铁矿', 'type' => 14, 'value' => 27, 'price' => 1900.0, 'gnum' => 3, 'num' => 80],
        20 => ['id' => 20, 'name' => '九转神木', 'type' => 14, 'value' => 26, 'price' => 900.0, 'gnum' => 3, 'num' => 80],
        21 => ['id' => 21, 'name' => '龙纹布', 'type' => 14, 'value' => 30, 'price' => 2400.0, 'gnum' => 5, 'num' => 80],
        22 => ['id' => 22, 'name' => '魔灵皮', 'type' => 14, 'value' => 31, 'price' => 2400.0, 'gnum' => 5, 'num' => 80],
        23 => ['id' => 23, 'name' => '紫宝石', 'type' => 3, 'value' => 0, 'price' => 4800.0, 'gnum' => 1000, 'num' => 80],
        24 => ['id' => 24, 'name' => '元晶石', 'type' => 14, 'value' => 9, 'price' => 800.0, 'gnum' => 5, 'num' => 80],
        25 => ['id' => 25, 'name' => '狂云水晶', 'type' => 14, 'value' => 22, 'price' => 1200.0, 'gnum' => 5, 'num' => 80],
        26 => ['id' => 26, 'name' => '冥牙', 'type' => 14, 'value' => 40, 'price' => 800.0, 'gnum' => 5, 'num' => 80],
        27 => ['id' => 27, 'name' => '天机符咒', 'type' => 14, 'value' => 12, 'price' => 800.0, 'gnum' => 5, 'num' => 80],
        28 => ['id' => 28, 'name' => '星冰核', 'type' => 14, 'value' => 45, 'price' => 3200.0, 'gnum' => 5, 'num' => 80],
        29 => ['id' => 29, 'name' => '红宝石', 'type' => 14, 'value' => 21, 'price' => 2400.0, 'gnum' => 5, 'num' => 80],
        30 => ['id' => 30, 'name' => '人王', 'type' => 15, 'value' => 204, 'price' => 5000.0, 'gnum' => 1, 'num' => 10],
        31 => ['id' => 31, 'name' => '魅影', 'type' => 15, 'value' => 214, 'price' => 5000.0, 'gnum' => 1, 'num' => 10],
    ];
}
