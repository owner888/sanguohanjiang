<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 团购礼包表（旧版 zc_sanguo_christmasbuy_gift）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ChristmasbuyGift
{
    public const string TABLE = 'zc_sanguo_christmasbuy_gift';

    /**
     * 按主键 id 索引，共 8 行。字段：
     *   - id (int) ID
     *   - limit (int) 花费额度
     *   - type1 (int) 奖励类型1
     *   - num1 (int) 数量1（装备或坐骑id）
     *   - value1 (int) 参数1（装备或坐骑数量）
     *   - type2 (int) 奖励类型2
     *   - num2 (int) 数量2（装备或坐骑id）
     *   - value2 (int) 参数2（装备或坐骑数量）
     *   - type3 (int) 奖励类型3
     *   - num3 (int) 数量3（装备或坐骑id）
     *   - value3 (int) 参数3（装备或坐骑数量）
     */
    public const array ROWS = [
        1 => ['id' => 1, 'limit' => 4000, 'type1' => 1, 'num1' => 200000, 'value1' => 0, 'type2' => 3, 'num2' => 20, 'value2' => 0, 'type3' => 4, 'num3' => 50, 'value3' => 0],
        2 => ['id' => 2, 'limit' => 20000, 'type1' => 1, 'num1' => 600000, 'value1' => 0, 'type2' => 3, 'num2' => 100, 'value2' => 0, 'type3' => 4, 'num3' => 100, 'value3' => 0],
        3 => ['id' => 3, 'limit' => 40000, 'type1' => 5, 'num1' => 8000, 'value1' => 0, 'type2' => 3, 'num2' => 300, 'value2' => 0, 'type3' => 4, 'num3' => 300, 'value3' => 0],
        4 => ['id' => 4, 'limit' => 80000, 'type1' => 5, 'num1' => 10000, 'value1' => 0, 'type2' => 3, 'num2' => 600, 'value2' => 0, 'type3' => 4, 'num3' => 600, 'value3' => 0],
        5 => ['id' => 5, 'limit' => 120000, 'type1' => 5, 'num1' => 30000, 'value1' => 0, 'type2' => 3, 'num2' => 700, 'value2' => 0, 'type3' => 8, 'num3' => 80000, 'value3' => 0],
        6 => ['id' => 6, 'limit' => 200000, 'type1' => 5, 'num1' => 50000, 'value1' => 0, 'type2' => 3, 'num2' => 1000, 'value2' => 0, 'type3' => 8, 'num3' => 100000, 'value3' => 0],
        7 => ['id' => 7, 'limit' => 400000, 'type1' => 5, 'num1' => 80000, 'value1' => 0, 'type2' => 3, 'num2' => 2000, 'value2' => 0, 'type3' => 8, 'num3' => 200000, 'value3' => 0],
        8 => ['id' => 8, 'limit' => 800000, 'type1' => 5, 'num1' => 100000, 'value1' => 0, 'type2' => 3, 'num2' => 4000, 'value2' => 0, 'type3' => 8, 'num3' => 400000, 'value3' => 0],
    ];
}
