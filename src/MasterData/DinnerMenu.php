<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 年夜饭菜肴表（旧版 zc_sanguo_dinner_menu）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class DinnerMenu
{
    public const string TABLE = 'zc_sanguo_dinner_menu';

    /**
     * 按主键 id 索引，共 5 行。字段：
     *   - id (tinyint) 菜肴id
     *   - name (varchar) 菜肴名称
     *   - sub (int) 菜肴花费
     *   - get (int) 菜肴奖励
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '烧鸡', 'sub' => 10, 'get' => 5],
        2 => ['id' => 2, 'name' => '猪头', 'sub' => 30, 'get' => 12],
        3 => ['id' => 3, 'name' => '熊掌', 'sub' => 100, 'get' => 33],
        4 => ['id' => 4, 'name' => '人参', 'sub' => 300, 'get' => 85],
        5 => ['id' => 5, 'name' => '朱果', 'sub' => 1000, 'get' => 250],
    ];
}
