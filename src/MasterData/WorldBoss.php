<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * world_boss（旧版 zc_sanguo_world_boss）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class WorldBoss
{
    public const string TABLE = 'zc_sanguo_world_boss';

    /**
     * 按主键 id 索引，共 3 行。字段：
     *   - id (tinyint)
     *   - name (varchar) boss名称
     *   - level (tinyint) boss等级
     *   - image (varchar) 资源名
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '刑天', 'level' => 50, 'image' => 'worldboss1'],
        2 => ['id' => 2, 'name' => '蚩尤', 'level' => 50, 'image' => 'countryboss'],
        3 => ['id' => 3, 'name' => '年兽', 'level' => 37, 'image' => 'newyearboss'],
    ];
}
