<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 注册前3日奖励（旧版 zc_sanguo_newrolereward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Newrolereward
{
    public const string TABLE = 'zc_sanguo_newrolereward';

    /**
     * 按主键 id 索引，共 3 行。字段：
     *   - id (tinyint) 天数
     *   - type1 (tinyint) 奖励1类型（1银币，2元宝，3蓝宝石，4蓝色突飞卡）
     *   - value1 (mediumint) 奖励1参数
     *   - type2 (tinyint) 奖励2类型（1银币，2元宝，3蓝宝石，4蓝色突飞卡）
     *   - value2 (mediumint) 奖励2参数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type1' => 1, 'value1' => 80000, 'type2' => 2, 'value2' => 30],
        2 => ['id' => 2, 'type1' => 3, 'value1' => 100, 'type2' => 2, 'value2' => 30],
        3 => ['id' => 3, 'type1' => 4, 'value1' => 20, 'type2' => 2, 'value2' => 40],
    ];
}
