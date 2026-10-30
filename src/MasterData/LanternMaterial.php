<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 元宵节材料表（旧版 zc_sanguo_lantern_material）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class LanternMaterial
{
    public const string TABLE = 'zc_sanguo_lantern_material';

    /**
     * 按主键 ID 索引，共 10 行。字段：
     *   - ID (int)
     *   - name (varchar) 材料名称
     *   - price (int) 价格
     */
    public const array ROWS = [
        1 => ['ID' => 1, 'name' => '糯米', 'price' => 10],
        2 => ['ID' => 2, 'name' => '芝麻', 'price' => 10],
        3 => ['ID' => 3, 'name' => '白糖', 'price' => 10],
        4 => ['ID' => 4, 'name' => '黑米', 'price' => 20],
        5 => ['ID' => 5, 'name' => '花生', 'price' => 20],
        6 => ['ID' => 6, 'name' => '紫薯', 'price' => 30],
        7 => ['ID' => 7, 'name' => '红枣', 'price' => 30],
        8 => ['ID' => 8, 'name' => '火腿', 'price' => 40],
        9 => ['ID' => 9, 'name' => '抹茶', 'price' => 40],
        10 => ['ID' => 10, 'name' => '酒酿', 'price' => 40],
    ];
}
