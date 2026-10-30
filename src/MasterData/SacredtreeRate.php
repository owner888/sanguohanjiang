<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 神树经验暴击表（旧版 zc_sanguo_sacredtree_rate）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class SacredtreeRate
{
    public const string TABLE = 'zc_sanguo_sacredtree_rate';

    /**
     * 按主键 id 索引，共 1 行。字段：
     *   - id (int) 暴击id
     *   - one (smallint) 1倍正常权重
     *   - two (tinyint) 2倍暴击权重
     *   - four (smallint) 4倍暴击权重
     *   - eight (smallint) 8被暴击权重
     */
    public const array ROWS = [
        1 => ['id' => 1, 'one' => 80, 'two' => 15, 'four' => 3, 'eight' => 2],
    ];
}
