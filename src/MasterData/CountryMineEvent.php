<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 国家矿触发事件表（旧版 zc_sanguo_country_mine_event）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class CountryMineEvent
{
    public const string TABLE = 'zc_sanguo_country_mine_event';

    /**
     * 按主键 id 索引，共 10 行。字段：
     *   - id (int) 事件id
     *   - name (varchar) 事件名称
     *   - type (int) 收益类型（1紫宝石，2玉石产量倍数，3元宝）
     *   - value (double) 参数（1紫宝石数量，2玉石产量系数，3元宝个数）
     *   - rate (int) 出现该事件权重
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '大丰收-小', 'type' => 2, 'value' => 1.2, 'rate' => 40],
        2 => ['id' => 2, 'name' => '大丰收-中', 'type' => 2, 'value' => 1.4, 'rate' => 25],
        3 => ['id' => 3, 'name' => '大丰收-大', 'type' => 2, 'value' => 1.6, 'rate' => 10],
        4 => ['id' => 4, 'name' => '大丰收-超', 'type' => 2, 'value' => 1.8, 'rate' => 10],
        5 => ['id' => 5, 'name' => '大丰收-神', 'type' => 2, 'value' => 2.0, 'rate' => 5],
        6 => ['id' => 6, 'name' => '石中藏金-小', 'type' => 3, 'value' => 10.0, 'rate' => 30],
        7 => ['id' => 7, 'name' => '石中藏金-大', 'type' => 3, 'value' => 20.0, 'rate' => 15],
        8 => ['id' => 8, 'name' => '偶得奇石', 'type' => 1, 'value' => 1.0, 'rate' => 30],
        9 => ['id' => 9, 'name' => '珍宝现世', 'type' => 1, 'value' => 2.0, 'rate' => 10],
        10 => ['id' => 10, 'name' => '神石天赐', 'type' => 1, 'value' => 3.0, 'rate' => 5],
    ];
}
