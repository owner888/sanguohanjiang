<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 充值送豪礼奖励表（旧版 zc_sanguo_thirty_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ThirtyReward
{
    public const string TABLE = 'zc_sanguo_thirty_reward';

    /**
     * 按主键 id 索引，共 7 行。字段：
     *   - id (int) 天数
     *   - gold (int) 充值元宝
     *   - type1 (int) 奖励类型1
     *   - value1 (int) 奖励数量1
     *   - num1 (int) 奖励数量1
     *   - type2 (int) 奖励类型2
     *   - value2 (int) 奖励数量2
     *   - num2 (int) 奖励数量2
     *   - type3 (int) 奖励类型3
     *   - value3 (int) 奖励数量3
     *   - num3 (int) 奖励数量3
     *   - type4 (int) 奖励类型4
     *   - value4 (int) 奖励数量4
     *   - num4 (int) 奖励数量4
     *   - type5 (int) 奖励类型5
     *   - value5 (int) 奖励数量5
     *   - num5 (int) 奖励数量5
     */
    public const array ROWS = [
        1 => ['id' => 1, 'gold' => 300, 'type1' => 2, 'value1' => 3000000, 'num1' => 0, 'type2' => 10, 'value2' => 100, 'num2' => 0, 'type3' => 15, 'value3' => 151, 'num3' => 10, 'type4' => 0, 'value4' => 0, 'num4' => 0, 'type5' => 0, 'value5' => 0, 'num5' => 0],
        2 => ['id' => 2, 'gold' => 300, 'type1' => 2, 'value1' => 3000000, 'num1' => 0, 'type2' => 10, 'value2' => 100, 'num2' => 0, 'type3' => 15, 'value3' => 151, 'num3' => 10, 'type4' => 0, 'value4' => 0, 'num4' => 0, 'type5' => 0, 'value5' => 0, 'num5' => 0],
        3 => ['id' => 3, 'gold' => 300, 'type1' => 2, 'value1' => 3000000, 'num1' => 0, 'type2' => 10, 'value2' => 100, 'num2' => 0, 'type3' => 15, 'value3' => 151, 'num3' => 10, 'type4' => 0, 'value4' => 0, 'num4' => 0, 'type5' => 0, 'value5' => 0, 'num5' => 0],
        4 => ['id' => 4, 'gold' => 300, 'type1' => 2, 'value1' => 3000000, 'num1' => 0, 'type2' => 10, 'value2' => 100, 'num2' => 0, 'type3' => 15, 'value3' => 151, 'num3' => 10, 'type4' => 0, 'value4' => 0, 'num4' => 0, 'type5' => 0, 'value5' => 0, 'num5' => 0],
        5 => ['id' => 5, 'gold' => 300, 'type1' => 2, 'value1' => 3000000, 'num1' => 0, 'type2' => 10, 'value2' => 100, 'num2' => 0, 'type3' => 15, 'value3' => 151, 'num3' => 10, 'type4' => 0, 'value4' => 0, 'num4' => 0, 'type5' => 0, 'value5' => 0, 'num5' => 0],
        6 => ['id' => 6, 'gold' => 300, 'type1' => 2, 'value1' => 3000000, 'num1' => 0, 'type2' => 10, 'value2' => 100, 'num2' => 0, 'type3' => 15, 'value3' => 151, 'num3' => 10, 'type4' => 0, 'value4' => 0, 'num4' => 0, 'type5' => 0, 'value5' => 0, 'num5' => 0],
        7 => ['id' => 7, 'gold' => 300, 'type1' => 2, 'value1' => 3000000, 'num1' => 0, 'type2' => 10, 'value2' => 100, 'num2' => 0, 'type3' => 15, 'value3' => 151, 'num3' => 10, 'type4' => 0, 'value4' => 0, 'num4' => 0, 'type5' => 0, 'value5' => 0, 'num5' => 0],
    ];
}
