<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 国家庆典活动充值奖励表（旧版 zc_sanguo_act_celebration_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActCelebrationReward
{
    public const string TABLE = 'zc_sanguo_act_celebration_reward';

    /**
     * 按主键 id 索引，共 10 行。字段：
     *   - id (int) 自增ID
     *   - gold (int) 充值元宝
     *   - type1 (int) 奖励1类型
     *   - num1 (int) 奖励1数量
     *   - value1 (int) 奖励1参数
     *   - type2 (int) 奖励2类型
     *   - num2 (int) 奖励2数量
     *   - value2 (int) 奖励2参数
     *   - type3 (int) 奖励3类型
     *   - num3 (int) 奖励3数量
     *   - value3 (int) 奖励3参数
     *   - type4 (int) 奖励4类型
     *   - num4 (int) 奖励4数量
     *   - value4 (int) 奖励4参数
     *   - type5 (int) 奖励5类型
     *   - num5 (int) 奖励5数量
     *   - value5 (int) 奖励5参数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'gold' => 40000, 'type1' => 2, 'num1' => 2000000, 'value1' => 0, 'type2' => 1, 'num2' => 200, 'value2' => 0, 'type3' => 10, 'num3' => 200, 'value3' => 0, 'type4' => 0, 'num4' => 0, 'value4' => 0, 'type5' => 0, 'num5' => 0, 'value5' => 0],
        2 => ['id' => 2, 'gold' => 120000, 'type1' => 2, 'num1' => 3000000, 'value1' => 0, 'type2' => 1, 'num2' => 250, 'value2' => 0, 'type3' => 10, 'num3' => 250, 'value3' => 0, 'type4' => 4, 'num4' => 100, 'value4' => 0, 'type5' => 0, 'num5' => 0, 'value5' => 0],
        3 => ['id' => 3, 'gold' => 200000, 'type1' => 2, 'num1' => 4000000, 'value1' => 0, 'type2' => 1, 'num2' => 280, 'value2' => 0, 'type3' => 10, 'num3' => 280, 'value3' => 0, 'type4' => 4, 'num4' => 120, 'value4' => 0, 'type5' => 7, 'num5' => 15000, 'value5' => 0],
        4 => ['id' => 4, 'gold' => 320000, 'type1' => 2, 'num1' => 6000000, 'value1' => 0, 'type2' => 1, 'num2' => 300, 'value2' => 0, 'type3' => 10, 'num3' => 300, 'value3' => 0, 'type4' => 4, 'num4' => 150, 'value4' => 0, 'type5' => 28, 'num5' => 50, 'value5' => 0],
        5 => ['id' => 5, 'gold' => 600000, 'type1' => 2, 'num1' => 8000000, 'value1' => 0, 'type2' => 1, 'num2' => 350, 'value2' => 0, 'type3' => 10, 'num3' => 350, 'value3' => 0, 'type4' => 4, 'num4' => 180, 'value4' => 0, 'type5' => 7, 'num5' => 20000, 'value5' => 0],
        6 => ['id' => 6, 'gold' => 800000, 'type1' => 2, 'num1' => 10000000, 'value1' => 0, 'type2' => 1, 'num2' => 400, 'value2' => 0, 'type3' => 10, 'num3' => 400, 'value3' => 0, 'type4' => 4, 'num4' => 200, 'value4' => 0, 'type5' => 27, 'num5' => 150, 'value5' => 0],
        7 => ['id' => 7, 'gold' => 1600000, 'type1' => 2, 'num1' => 15000000, 'value1' => 0, 'type2' => 1, 'num2' => 450, 'value2' => 0, 'type3' => 10, 'num3' => 450, 'value3' => 0, 'type4' => 4, 'num4' => 250, 'value4' => 0, 'type5' => 7, 'num5' => 30000, 'value5' => 0],
        8 => ['id' => 8, 'gold' => 2400000, 'type1' => 2, 'num1' => 20000000, 'value1' => 0, 'type2' => 1, 'num2' => 500, 'value2' => 0, 'type3' => 10, 'num3' => 500, 'value3' => 0, 'type4' => 4, 'num4' => 300, 'value4' => 0, 'type5' => 28, 'num5' => 100, 'value5' => 0],
        9 => ['id' => 9, 'gold' => 4000000, 'type1' => 2, 'num1' => 25000000, 'value1' => 0, 'type2' => 1, 'num2' => 600, 'value2' => 0, 'type3' => 10, 'num3' => 600, 'value3' => 0, 'type4' => 4, 'num4' => 350, 'value4' => 0, 'type5' => 7, 'num5' => 50000, 'value5' => 0],
        10 => ['id' => 10, 'gold' => 5000000, 'type1' => 2, 'num1' => 30000000, 'value1' => 0, 'type2' => 1, 'num2' => 1000, 'value2' => 0, 'type3' => 10, 'num3' => 800, 'value3' => 0, 'type4' => 4, 'num4' => 500, 'value4' => 0, 'type5' => 7, 'num5' => 80000, 'value5' => 0],
    ];
}
