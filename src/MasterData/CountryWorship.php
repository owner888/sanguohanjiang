<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 国家祭祀表（旧版 zc_sanguo_country_worship）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class CountryWorship
{
    public const string TABLE = 'zc_sanguo_country_worship';

    /**
     * 按主键 id 索引，共 3 行。字段：
     *   - id (tinyint) 索引id
     *   - type (tinyint) 消耗类型
     *   - price (smallint) 消耗参数
     *   - get_value (mediumint) 获得奖励参数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => 1, 'price' => 500, 'get_value' => 40],
        2 => ['id' => 2, 'type' => 2, 'price' => 30, 'get_value' => 120],
        3 => ['id' => 3, 'type' => 2, 'price' => 100, 'get_value' => 400],
    ];
}
