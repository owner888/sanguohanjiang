<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 至尊组队表（旧版 zc_sanguo_elite_price）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ElitePrice
{
    public const string TABLE = 'zc_sanguo_elite_price';

    /**
     * 按主键 id 索引，共 1 行。字段：
     *   - id (tinyint) id
     *   - caption (smallint) 至尊组队队长加成
     *   - player (smallint) 至尊组队队友加成
     *   - gold (smallint) 至尊组队价格
     */
    public const array ROWS = [
        1 => ['id' => 1, 'caption' => 300, 'player' => 200, 'gold' => 50],
    ];
}
