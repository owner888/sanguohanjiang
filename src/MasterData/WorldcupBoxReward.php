<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 我爱世界杯宝箱奖励表（旧版 zc_sanguo_worldcup_box_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class WorldcupBoxReward
{
    public const string TABLE = 'zc_sanguo_worldcup_box_reward';

    /**
     * 按主键 id 索引，共 16 行。字段：
     *   - id (int)
     *   - number (int)
     *   - type1 (int)
     *   - value1 (int)
     *   - type2 (int)
     *   - value2 (int)
     *   - type3 (int)
     *   - value3 (int)
     */
    public const array ROWS = [
        1 => ['id' => 1, 'number' => 1380, 'type1' => 1, 'value1' => 500000, 'type2' => 7, 'value2' => 1000, 'type3' => 10, 'value3' => 10],
        2 => ['id' => 2, 'number' => 2880, 'type1' => 1, 'value1' => 1000000, 'type2' => 7, 'value2' => 2000, 'type3' => 10, 'value3' => 30],
        3 => ['id' => 3, 'number' => 5880, 'type1' => 1, 'value1' => 2000000, 'type2' => 7, 'value2' => 3000, 'type3' => 10, 'value3' => 50],
        4 => ['id' => 4, 'number' => 11880, 'type1' => 1, 'value1' => 3000000, 'type2' => 7, 'value2' => 4000, 'type3' => 10, 'value3' => 80],
        5 => ['id' => 5, 'number' => 25880, 'type1' => 1, 'value1' => 5000000, 'type2' => 7, 'value2' => 5000, 'type3' => 10, 'value3' => 120],
        6 => ['id' => 6, 'number' => 40880, 'type1' => 1, 'value1' => 8000000, 'type2' => 7, 'value2' => 8000, 'type3' => 10, 'value3' => 150],
        7 => ['id' => 7, 'number' => 65880, 'type1' => 1, 'value1' => 10000000, 'type2' => 7, 'value2' => 10000, 'type3' => 10, 'value3' => 180],
        8 => ['id' => 8, 'number' => 93880, 'type1' => 6, 'value1' => 5000000, 'type2' => 7, 'value2' => 15000, 'type3' => 10, 'value3' => 250],
        9 => ['id' => 9, 'number' => 128880, 'type1' => 6, 'value1' => 10000000, 'type2' => 7, 'value2' => 20000, 'type3' => 10, 'value3' => 300],
        10 => ['id' => 10, 'number' => 258880, 'type1' => 8, 'value1' => 59, 'type2' => 8, 'value2' => 68, 'type3' => 10, 'value3' => 450],
        11 => ['id' => 11, 'number' => 558880, 'type1' => 8, 'value1' => 71, 'type2' => 7, 'value2' => 50000, 'type3' => 10, 'value3' => 600],
        12 => ['id' => 12, 'number' => 1088880, 'type1' => 9, 'value1' => 150000, 'type2' => 7, 'value2' => 150000, 'type3' => 10, 'value3' => 1000],
        13 => ['id' => 13, 'number' => 2088880, 'type1' => 9, 'value1' => 300000, 'type2' => 7, 'value2' => 300000, 'type3' => 10, 'value3' => 2000],
        14 => ['id' => 14, 'number' => 2588880, 'type1' => 9, 'value1' => 600000, 'type2' => 7, 'value2' => 500000, 'type3' => 5, 'value3' => 10000],
        15 => ['id' => 15, 'number' => 2888880, 'type1' => 9, 'value1' => 800000, 'type2' => 7, 'value2' => 500000, 'type3' => 5, 'value3' => 20000],
        16 => ['id' => 16, 'number' => 3288880, 'type1' => 5, 'value1' => 30000, 'type2' => 8, 'value2' => 73, 'type3' => 0, 'value3' => 0],
    ];
}
