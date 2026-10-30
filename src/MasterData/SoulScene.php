<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 将魂星路场景表（旧版 zc_sanguo_soul_scene）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class SoulScene
{
    public const string TABLE = 'zc_sanguo_soul_scene';

    /**
     * 按主键 id 索引，共 7 行。字段：
     *   - id (tinyint)
     *   - name (varchar) 场景名称
     *   - openmethod (tinyint) 开启方法（1 推图、2 主公等级）
     *   - opencondition (int) 开启条件（场景ID 或 主公等级）
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '北斗七星', 'openmethod' => 1, 'opencondition' => 0],
        2 => ['id' => 2, 'name' => '紫薇星域', 'openmethod' => 1, 'opencondition' => 1],
        3 => ['id' => 3, 'name' => '勾陈古星', 'openmethod' => 1, 'opencondition' => 2],
        4 => ['id' => 4, 'name' => '太初星域', 'openmethod' => 1, 'opencondition' => 3],
        5 => ['id' => 5, 'name' => '天龙祖星', 'openmethod' => 1, 'opencondition' => 4],
        6 => ['id' => 6, 'name' => '凌虚古星', 'openmethod' => 1, 'opencondition' => 5],
        7 => ['id' => 7, 'name' => '太古星宿', 'openmethod' => 1, 'opencondition' => 6],
    ];
}
