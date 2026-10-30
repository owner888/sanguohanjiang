<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 每日对酒数据表（旧版 zc_sanguo_drink）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Drink
{
    public const string TABLE = 'zc_sanguo_drink';

    /**
     * 按主键 id 索引，共 4 行。字段：
     *   - id (tinyint) id
     *   - name (char) 酒名
     *   - type (tinyint) 价格类型
     *   - price (mediumint) 价格
     *   - act (int) 增加军令个数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '梨花白', 'type' => 1, 'price' => 100, 'act' => 5],
        2 => ['id' => 2, 'name' => '花雕', 'type' => 2, 'price' => 50, 'act' => 10],
        3 => ['id' => 3, 'name' => '竹叶青', 'type' => 2, 'price' => 100, 'act' => 20],
        4 => ['id' => 4, 'name' => '状元红', 'type' => 2, 'price' => 200, 'act' => 40],
    ];
}
