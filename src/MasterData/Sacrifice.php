<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * sacrifice（旧版 zc_sanguo_sacrifice）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Sacrifice
{
    public const string TABLE = 'zc_sanguo_sacrifice';

    /**
     * 按主键 id 索引，共 3 行。字段：
     *   - id (int)
     *   - type (char) 宝石名称
     *   - multiply1 (tinyint) 1倍
     *   - multiply2 (tinyint) 2倍
     *   - multiply4 (tinyint) 4倍
     *   - multiply8 (tinyint) 8倍
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => '蓝宝石', 'multiply1' => 75, 'multiply2' => 15, 'multiply4' => 5, 'multiply8' => 5],
        2 => ['id' => 2, 'type' => '金宝石', 'multiply1' => 85, 'multiply2' => 10, 'multiply4' => 4, 'multiply8' => 1],
        3 => ['id' => 3, 'type' => '紫宝石', 'multiply1' => 92, 'multiply2' => 5, 'multiply4' => 2, 'multiply8' => 1],
    ];
}
