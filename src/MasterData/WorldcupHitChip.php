<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 我爱世界杯常规碎片奖励表（旧版 zc_sanguo_worldcup_hit_chip）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class WorldcupHitChip
{
    public const string TABLE = 'zc_sanguo_worldcup_hit_chip';

    /**
     * 按主键 id 索引，共 2 行。字段：
     *   - id (int)
     *   - price (int)
     *   - number2 (int)
     *   - number3 (int)
     *   - number4 (int)
     *   - number8 (int)
     *   - number9 (int)
     *   - number10 (int)
     *   - number11 (int)
     *   - number12 (int)
     *   - number13 (int)
     *   - number14 (int)
     *   - number15 (int)
     *   - number16 (int)
     */
    public const array ROWS = [
        1 => ['id' => 1, 'price' => 100, 'number2' => 50, 'number3' => 50, 'number4' => 50, 'number8' => 0, 'number9' => 0, 'number10' => 0, 'number11' => 0, 'number12' => 0, 'number13' => 0, 'number14' => 0, 'number15' => 0, 'number16' => 0],
        2 => ['id' => 2, 'price' => 400, 'number2' => 0, 'number3' => 0, 'number4' => 0, 'number8' => 50, 'number9' => 50, 'number10' => 50, 'number11' => 50, 'number12' => 50, 'number13' => 50, 'number14' => 50, 'number15' => 50, 'number16' => 50],
    ];
}
