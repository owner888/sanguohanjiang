<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 威震天下数据表（旧版 zc_sanguo_smash）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Smash
{
    public const string TABLE = 'zc_sanguo_smash';

    /**
     * 按主键 id 索引，共 3 行。字段：
     *   - id (tinyint) 序列id
     *   - name (varchar) 名称
     *   - sub (int) 花费元宝
     *   - get (int) 获得声望
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '剿灭黄巾', 'sub' => 100, 'get' => 1000],
        2 => ['id' => 2, 'name' => '拥立天子', 'sub' => 400, 'get' => 3000],
        3 => ['id' => 3, 'name' => '一统天下', 'sub' => 1000, 'get' => 10000],
    ];
}
