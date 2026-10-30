<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 国家矿模版表（旧版 zc_sanguo_country_mine_ratio）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class CountryMineRatio
{
    public const string TABLE = 'zc_sanguo_country_mine_ratio';

    /**
     * 按主键 id 索引，共 5 行。字段：
     *   - id (int) 模版id
     *   - times (double) 玉石产量系数
     *   - rate (int) 触发事件概率
     */
    public const array ROWS = [
        1 => ['id' => 1, 'times' => 6.0, 'rate' => 50],
        2 => ['id' => 2, 'times' => 4.0, 'rate' => 40],
        3 => ['id' => 3, 'times' => 2.5, 'rate' => 30],
        4 => ['id' => 4, 'times' => 1.5, 'rate' => 20],
        5 => ['id' => 5, 'times' => 1.0, 'rate' => 10],
    ];
}
