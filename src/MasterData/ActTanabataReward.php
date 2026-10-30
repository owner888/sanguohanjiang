<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 七夕活动充值奖励表（旧版 zc_sanguo_act_tanabata_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActTanabataReward
{
    public const string TABLE = 'zc_sanguo_act_tanabata_reward';

    /**
     * 按主键 id 索引，共 8 行。字段：
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
        1 => ['id' => 1, 'gold' => 600000, 'type1' => 2, 'num1' => 10000000, 'value1' => 0, 'type2' => 3, 'num2' => 100000, 'value2' => 0, 'type3' => 6, 'num3' => 100000, 'value3' => 0, 'type4' => 4, 'num4' => 100000, 'value4' => 0, 'type5' => 7, 'num5' => 100000, 'value5' => 0],
        2 => ['id' => 2, 'gold' => 1000000, 'type1' => 2, 'num1' => 12000000, 'value1' => 0, 'type2' => 3, 'num2' => 200000, 'value2' => 0, 'type3' => 6, 'num3' => 200000, 'value3' => 0, 'type4' => 4, 'num4' => 200000, 'value4' => 0, 'type5' => 7, 'num5' => 200000, 'value5' => 0],
        3 => ['id' => 3, 'gold' => 2000000, 'type1' => 2, 'num1' => 15000000, 'value1' => 0, 'type2' => 3, 'num2' => 300000, 'value2' => 0, 'type3' => 6, 'num3' => 300000, 'value3' => 0, 'type4' => 4, 'num4' => 300000, 'value4' => 0, 'type5' => 7, 'num5' => 300000, 'value5' => 0],
        4 => ['id' => 4, 'gold' => 3000000, 'type1' => 2, 'num1' => 20000000, 'value1' => 0, 'type2' => 3, 'num2' => 400000, 'value2' => 0, 'type3' => 6, 'num3' => 400000, 'value3' => 0, 'type4' => 4, 'num4' => 400000, 'value4' => 0, 'type5' => 7, 'num5' => 400000, 'value5' => 0],
        5 => ['id' => 5, 'gold' => 4000000, 'type1' => 2, 'num1' => 40000000, 'value1' => 0, 'type2' => 3, 'num2' => 500000, 'value2' => 0, 'type3' => 6, 'num3' => 500000, 'value3' => 0, 'type4' => 4, 'num4' => 500000, 'value4' => 0, 'type5' => 7, 'num5' => 500000, 'value5' => 0],
        6 => ['id' => 6, 'gold' => 5000000, 'type1' => 2, 'num1' => 60000000, 'value1' => 0, 'type2' => 3, 'num2' => 600000, 'value2' => 0, 'type3' => 6, 'num3' => 600000, 'value3' => 0, 'type4' => 4, 'num4' => 600000, 'value4' => 0, 'type5' => 7, 'num5' => 600000, 'value5' => 0],
        7 => ['id' => 7, 'gold' => 6000000, 'type1' => 2, 'num1' => 100000000, 'value1' => 0, 'type2' => 3, 'num2' => 700000, 'value2' => 0, 'type3' => 6, 'num3' => 700000, 'value3' => 0, 'type4' => 4, 'num4' => 700000, 'value4' => 0, 'type5' => 7, 'num5' => 700000, 'value5' => 0],
        8 => ['id' => 8, 'gold' => 10000000, 'type1' => 2, 'num1' => 200000000, 'value1' => 0, 'type2' => 3, 'num2' => 1000000, 'value2' => 0, 'type3' => 6, 'num3' => 1000000, 'value3' => 0, 'type4' => 4, 'num4' => 1000000, 'value4' => 0, 'type5' => 7, 'num5' => 1000000, 'value5' => 0],
    ];
}
