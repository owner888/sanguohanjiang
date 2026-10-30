<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 东瀛城池数据表（旧版 zc_sanguo_japan_city）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class JapanCity
{
    public const string TABLE = 'zc_sanguo_japan_city';

    /**
     * 按主键 city_id 索引，共 4 行。字段：
     *   - city_id (int) 城市ID
     *   - city_name (varchar) 城池名字
     *   - ex_military (int) 重置消耗军令数量
     *   - get_credit (int) 奖励：战功
     *   - get_devote (int) 奖励：贡献
     *   - pass_credit (int) 通关奖励：战功
     *   - credit_in_seven (int) 7日内通关奖励：战功
     */
    public const array ROWS = [
        1 => ['city_id' => 1, 'city_name' => '春日山城', 'ex_military' => 50, 'get_credit' => 10, 'get_devote' => 10, 'pass_credit' => 200, 'credit_in_seven' => 200],
        2 => ['city_id' => 2, 'city_name' => '踯躅崎馆', 'ex_military' => 50, 'get_credit' => 10, 'get_devote' => 10, 'pass_credit' => 300, 'credit_in_seven' => 300],
        3 => ['city_id' => 3, 'city_name' => '冈崎城', 'ex_military' => 50, 'get_credit' => 10, 'get_devote' => 10, 'pass_credit' => 400, 'credit_in_seven' => 400],
        4 => ['city_id' => 4, 'city_name' => '岐阜城', 'ex_military' => 50, 'get_credit' => 10, 'get_devote' => 10, 'pass_credit' => 500, 'credit_in_seven' => 500],
    ];
}
