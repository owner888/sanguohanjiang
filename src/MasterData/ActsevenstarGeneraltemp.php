<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 神将出售模板表（旧版 zc_sanguo_actsevenstar_generaltemp）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActsevenstarGeneraltemp
{
    public const string TABLE = 'zc_sanguo_actsevenstar_generaltemp';

    /**
     * 按主键 id 索引，共 2 行。字段：
     *   - id (int) 自增ID
     *   - gid (int) 武将ID
     *   - price (int) 武将售价
     */
    public const array ROWS = [
        1 => ['id' => 1, 'gid' => 167, 'price' => 135],
        2 => ['id' => 2, 'gid' => 168, 'price' => 135],
    ];
}
