<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 暴打名将宝箱奖励表（旧版 zc_sanguo_beatengod_box_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class BeatengodBoxReward
{
    public const string TABLE = 'zc_sanguo_beatengod_box_reward';

    /**
     * 按主键 id 索引，共 16 行。字段：
     *   - id (tinyint) 奖励id
     *   - number (mediumint) 达成碎片数
     *   - type1 (tinyint) 奖励物品1类型(1银币，2军令，3突飞卡，4黄宝石，5紫宝石，6玉石，7原魂，8红装-整件)
     *   - value1 (mediumint) 奖励物品1参数
     *   - type2 (tinyint) 奖励物品2类型
     *   - value2 (mediumint) 奖励物品2参数
     *   - type3 (tinyint) 奖励物品3类型
     *   - value3 (mediumint) 奖励物品3参数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'number' => 1380, 'type1' => 3, 'value1' => 20, 'type2' => 7, 'value2' => 1200, 'type3' => 5, 'value3' => 30],
        2 => ['id' => 2, 'number' => 2880, 'type1' => 3, 'value1' => 30, 'type2' => 7, 'value2' => 2400, 'type3' => 5, 'value3' => 60],
        3 => ['id' => 3, 'number' => 5880, 'type1' => 3, 'value1' => 50, 'type2' => 7, 'value2' => 3600, 'type3' => 5, 'value3' => 120],
        4 => ['id' => 4, 'number' => 11880, 'type1' => 3, 'value1' => 70, 'type2' => 7, 'value2' => 4800, 'type3' => 5, 'value3' => 240],
        5 => ['id' => 5, 'number' => 25880, 'type1' => 3, 'value1' => 110, 'type2' => 7, 'value2' => 6000, 'type3' => 5, 'value3' => 300],
        6 => ['id' => 6, 'number' => 40880, 'type1' => 3, 'value1' => 170, 'type2' => 7, 'value2' => 9600, 'type3' => 5, 'value3' => 360],
        7 => ['id' => 7, 'number' => 65880, 'type1' => 3, 'value1' => 210, 'type2' => 7, 'value2' => 12000, 'type3' => 5, 'value3' => 420],
        8 => ['id' => 8, 'number' => 93880, 'type1' => 3, 'value1' => 300, 'type2' => 7, 'value2' => 16000, 'type3' => 5, 'value3' => 600],
        9 => ['id' => 9, 'number' => 128880, 'type1' => 8, 'value1' => 59, 'type2' => 8, 'value2' => 68, 'type3' => 5, 'value3' => 960],
        10 => ['id' => 10, 'number' => 258880, 'type1' => 8, 'value1' => 71, 'type2' => 7, 'value2' => 24000, 'type3' => 5, 'value3' => 1200],
        11 => ['id' => 11, 'number' => 558880, 'type1' => 8, 'value1' => 76, 'type2' => 7, 'value2' => 60000, 'type3' => 5, 'value3' => 1800],
        12 => ['id' => 12, 'number' => 1088880, 'type1' => 9, 'value1' => 200000, 'type2' => 7, 'value2' => 180000, 'type3' => 5, 'value3' => 3600],
        13 => ['id' => 13, 'number' => 2088880, 'type1' => 9, 'value1' => 400000, 'type2' => 7, 'value2' => 360000, 'type3' => 5, 'value3' => 7200],
        14 => ['id' => 14, 'number' => 2588880, 'type1' => 9, 'value1' => 600000, 'type2' => 7, 'value2' => 500000, 'type3' => 5, 'value3' => 10000],
        15 => ['id' => 15, 'number' => 2888880, 'type1' => 9, 'value1' => 800000, 'type2' => 7, 'value2' => 500000, 'type3' => 5, 'value3' => 20000],
        16 => ['id' => 16, 'number' => 3288880, 'type1' => 8, 'value1' => 72, 'type2' => 0, 'value2' => 0, 'type3' => 5, 'value3' => 30000],
    ];
}
