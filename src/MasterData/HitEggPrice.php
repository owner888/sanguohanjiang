<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 砸金蛋价格表（旧版 zc_sanguo_hit_egg_price）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class HitEggPrice
{
    public const string TABLE = 'zc_sanguo_hit_egg_price';

    /**
     * 按主键 id 索引，共 3 行。字段：
     *   - id (int) 金蛋id
     *   - price (mediumint) 砸蛋价格（元宝）
     *   - description (varchar) 描述
     */
    public const array ROWS = [
        1 => ['id' => 1, 'price' => 100, 'description' => '可开出5-6星名将，蓝宝石、黄宝石、紫宝石、突飞卡等。'],
        2 => ['id' => 2, 'price' => 300, 'description' => '可开出7-8星名将，大额黄宝石、紫宝石、突飞卡等。'],
        3 => ['id' => 3, 'price' => 600, 'description' => '可开出8-9星名将，超额紫宝石、突飞卡等。'],
    ];
}
