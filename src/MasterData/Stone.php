<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 珍惜宝石表（旧版 zc_sanguo_stone）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Stone
{
    public const string TABLE = 'zc_sanguo_stone';

    /**
     * 按主键 id 索引，共 8 行。字段：
     *   - id (tinyint) 珍惜宝石id
     *   - name (varchar) 名称
     *   - level (tinyint) 品级（2绿 3蓝 4金 5紫）
     *   - value (mediumint) 出售价格
     *   - rate (tinyint) 出现概率
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '猫眼石', 'level' => 2, 'value' => 10000, 'rate' => 100],
        2 => ['id' => 2, 'name' => '绿松石', 'level' => 3, 'value' => 70000, 'rate' => 25],
        3 => ['id' => 3, 'name' => '火龙玉', 'level' => 3, 'value' => 100000, 'rate' => 50],
        4 => ['id' => 4, 'name' => '乾坤玉', 'level' => 3, 'value' => 150000, 'rate' => 25],
        5 => ['id' => 5, 'name' => '和氏璧', 'level' => 4, 'value' => 600000, 'rate' => 25],
        6 => ['id' => 6, 'name' => '太皇石', 'level' => 4, 'value' => 800000, 'rate' => 50],
        7 => ['id' => 7, 'name' => '仙王玉', 'level' => 4, 'value' => 1000000, 'rate' => 25],
        8 => ['id' => 8, 'name' => '神眼石', 'level' => 5, 'value' => 2000000, 'rate' => 100],
    ];
}
