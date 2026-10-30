<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 七星灯点灯花费表（旧版 zc_sanguo_actsevenstar_actionprice）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActsevenstarActionprice
{
    public const string TABLE = 'zc_sanguo_actsevenstar_actionprice';

    /**
     * 按主键 id 索引，共 7 行。字段：
     *   - id (int) 自增ID
     *   - get3 (int) 花费紫宝石数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'get3' => 214],
        2 => ['id' => 2, 'get3' => 214],
        3 => ['id' => 3, 'get3' => 214],
        4 => ['id' => 4, 'get3' => 214],
        5 => ['id' => 5, 'get3' => 214],
        6 => ['id' => 6, 'get3' => 214],
        7 => ['id' => 7, 'get3' => 214],
    ];
}
