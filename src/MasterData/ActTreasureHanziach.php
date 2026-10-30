<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 寻宝集字成就表（旧版 zc_sanguo_act_treasure_hanziach）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActTreasureHanziach
{
    public const string TABLE = 'zc_sanguo_act_treasure_hanziach';

    /**
     * 按主键 id 索引，共 14 行。字段：
     *   - id (int) 自增ID
     *   - desc (varchar) 描述
     *   - num (int) 所需次数
     *   - type1 (int) 奖励类型1
     *   - value1 (int) 奖励系数1
     *   - type2 (int) 奖励类型2
     *   - value2 (int) 奖励系数2
     *   - type3 (int) 奖励类型3
     *   - value3 (int) 奖励系数3
     */
    public const array ROWS = [
        1 => ['id' => 1, 'desc' => '集齐1次“悍将三国，谁与争锋”', 'num' => 1, 'type1' => 2, 'value1' => 3000000, 'type2' => 4, 'value2' => 200, 'type3' => 15, 'value3' => 30000],
        2 => ['id' => 2, 'desc' => '集齐3次“悍将三国，谁与争锋”', 'num' => 3, 'type1' => 2, 'value1' => 4000000, 'type2' => 4, 'value2' => 300, 'type3' => 15, 'value3' => 50000],
        3 => ['id' => 3, 'desc' => '集齐5次“悍将三国，谁与争锋”', 'num' => 5, 'type1' => 2, 'value1' => 5000000, 'type2' => 4, 'value2' => 400, 'type3' => 15, 'value3' => 70000],
        4 => ['id' => 4, 'desc' => '集齐7次“悍将三国，谁与争锋”', 'num' => 7, 'type1' => 2, 'value1' => 6000000, 'type2' => 4, 'value2' => 600, 'type3' => 15, 'value3' => 100000],
        5 => ['id' => 5, 'desc' => '集齐10次“悍将三国，谁与争锋”', 'num' => 10, 'type1' => 2, 'value1' => 7000000, 'type2' => 4, 'value2' => 1000, 'type3' => 15, 'value3' => 150000],
        6 => ['id' => 6, 'desc' => '集齐20次“悍将三国，谁与争锋”', 'num' => 20, 'type1' => 2, 'value1' => 8000000, 'type2' => 4, 'value2' => 1200, 'type3' => 16, 'value3' => 10000],
        7 => ['id' => 7, 'desc' => '集齐35次“悍将三国，谁与争锋”', 'num' => 35, 'type1' => 2, 'value1' => 10000000, 'type2' => 4, 'value2' => 1500, 'type3' => 16, 'value3' => 20000],
        8 => ['id' => 8, 'desc' => '集齐50次“悍将三国，谁与争锋”', 'num' => 50, 'type1' => 2, 'value1' => 12000000, 'type2' => 4, 'value2' => 2000, 'type3' => 16, 'value3' => 40000],
        9 => ['id' => 9, 'desc' => '集齐75次“悍将三国，谁与争锋”', 'num' => 75, 'type1' => 2, 'value1' => 15000000, 'type2' => 4, 'value2' => 3000, 'type3' => 17, 'value3' => 5000],
        10 => ['id' => 10, 'desc' => '集齐100次“悍将三国，谁与争锋”', 'num' => 100, 'type1' => 23, 'value1' => 10, 'type2' => 26, 'value2' => 5, 'type3' => 17, 'value3' => 10000],
        11 => ['id' => 11, 'desc' => '集齐200次“悍将三国，谁与争锋”', 'num' => 200, 'type1' => 2, 'value1' => 15000000, 'type2' => 4, 'value2' => 3000, 'type3' => 0, 'value3' => 0],
        12 => ['id' => 12, 'desc' => '集齐300次“悍将三国，谁与争锋”', 'num' => 300, 'type1' => 2, 'value1' => 15000000, 'type2' => 4, 'value2' => 3000, 'type3' => 0, 'value3' => 0],
        13 => ['id' => 13, 'desc' => '集齐400次“悍将三国，谁与争锋”', 'num' => 400, 'type1' => 2, 'value1' => 20000000, 'type2' => 4, 'value2' => 4000, 'type3' => 17, 'value3' => 1000000],
        14 => ['id' => 14, 'desc' => '集齐500次“悍将三国，谁与争锋”', 'num' => 500, 'type1' => 2, 'value1' => 20000000, 'type2' => 4, 'value2' => 5000, 'type3' => 17, 'value3' => 1000000],
    ];
}
