<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 国家税金商城（旧版 zc_sanguo_country_taxes_shop）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class CountryTaxesShop
{
    public const string TABLE = 'zc_sanguo_country_taxes_shop';

    /**
     * 按主键 id 索引，共 22 行。字段：
     *   - id (int)
     *   - type (int) 类型
     *   - value (int) 值
     *   - num (int) 数量
     *   - taxes (int) 税金
     *   - openlevel (int) 开放等级
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => 32, 'value' => 1, 'num' => 0, 'taxes' => 5, 'openlevel' => 30],
        2 => ['id' => 2, 'type' => 11, 'value' => 60, 'num' => 15, 'taxes' => 20, 'openlevel' => 30],
        3 => ['id' => 3, 'type' => 3, 'value' => 30, 'num' => 15, 'taxes' => 40, 'openlevel' => 35],
        4 => ['id' => 4, 'type' => 4, 'value' => 10, 'num' => 15, 'taxes' => 50, 'openlevel' => 40],
        5 => ['id' => 5, 'type' => 5, 'value' => 800, 'num' => 15, 'taxes' => 40, 'openlevel' => 30],
        6 => ['id' => 6, 'type' => 6, 'value' => 25000, 'num' => 15, 'taxes' => 25, 'openlevel' => 35],
        7 => ['id' => 7, 'type' => 7, 'value' => 2000, 'num' => 10, 'taxes' => 80, 'openlevel' => 50],
        8 => ['id' => 8, 'type' => 9, 'value' => 10000, 'num' => 10, 'taxes' => 100, 'openlevel' => 35],
        9 => ['id' => 9, 'type' => 15, 'value' => 200, 'num' => 5, 'taxes' => 120, 'openlevel' => 40],
        10 => ['id' => 10, 'type' => 16, 'value' => 50, 'num' => 5, 'taxes' => 150, 'openlevel' => 50],
        11 => ['id' => 11, 'type' => 27, 'value' => 1, 'num' => 15, 'taxes' => 5, 'openlevel' => 30],
        12 => ['id' => 12, 'type' => 28, 'value' => 1, 'num' => 10, 'taxes' => 30, 'openlevel' => 35],
        13 => ['id' => 13, 'type' => 11, 'value' => 120, 'num' => 5, 'taxes' => 40, 'openlevel' => 35],
        14 => ['id' => 14, 'type' => 3, 'value' => 60, 'num' => 5, 'taxes' => 80, 'openlevel' => 35],
        15 => ['id' => 15, 'type' => 4, 'value' => 20, 'num' => 5, 'taxes' => 95, 'openlevel' => 40],
        16 => ['id' => 16, 'type' => 5, 'value' => 1600, 'num' => 5, 'taxes' => 80, 'openlevel' => 35],
        17 => ['id' => 17, 'type' => 6, 'value' => 50000, 'num' => 5, 'taxes' => 50, 'openlevel' => 35],
        18 => ['id' => 18, 'type' => 7, 'value' => 4000, 'num' => 5, 'taxes' => 150, 'openlevel' => 40],
        19 => ['id' => 19, 'type' => 9, 'value' => 20000, 'num' => 5, 'taxes' => 200, 'openlevel' => 55],
        20 => ['id' => 20, 'type' => 15, 'value' => 400, 'num' => 5, 'taxes' => 240, 'openlevel' => 40],
        21 => ['id' => 21, 'type' => 16, 'value' => 100, 'num' => 5, 'taxes' => 290, 'openlevel' => 45],
        22 => ['id' => 22, 'type' => 27, 'value' => 5, 'num' => 5, 'taxes' => 25, 'openlevel' => 35],
    ];
}
