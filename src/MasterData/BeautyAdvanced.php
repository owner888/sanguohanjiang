<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 美人进阶表（旧版 zc_sanguo_beauty_advanced）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class BeautyAdvanced
{
    public const string TABLE = 'zc_sanguo_beauty_advanced';

    /**
     * 无主键，按原顺序 list，共 12 行。字段：
     *   - id (int)
     *   - level (int) 进阶条件
     *   - type1 (int) 消耗类型1
     *   - value1 (int) 参数1
     *   - type2 (int) 消耗类型2
     *   - value2 (int) 参数2
     *   - type3 (int) 消耗类型3
     *   - value3 (int) 参数3
     *   - type4 (int) 消耗类型4
     *   - value4 (int) 参数4
     *   - type5 (int) 消耗类型5
     *   - value5 (int) 参数5
     *   - type6 (int) 消耗类型6
     *   - value6 (int) 参数6
     */
    public const array ROWS = [
        ['id' => 1, 'level' => 20, 'type1' => 1, 'value1' => 5000, 'type2' => 2, 'value2' => 1500, 'type3' => 3, 'value3' => 0, 'type4' => 4, 'value4' => 0, 'type5' => 5, 'value5' => 1000, 'type6' => 6, 'value6' => 0],
        ['id' => 1, 'level' => 40, 'type1' => 1, 'value1' => 15000, 'type2' => 2, 'value2' => 8000, 'type3' => 3, 'value3' => 500000, 'type4' => 4, 'value4' => 200000, 'type5' => 5, 'value5' => 5000, 'type6' => 6, 'value6' => 1000],
        ['id' => 1, 'level' => 60, 'type1' => 1, 'value1' => 30000, 'type2' => 2, 'value2' => 20000, 'type3' => 3, 'value3' => 2000000, 'type4' => 4, 'value4' => 500000, 'type5' => 5, 'value5' => 10000, 'type6' => 6, 'value6' => 2000],
        ['id' => 1, 'level' => 20, 'type1' => 1, 'value1' => 10000, 'type2' => 2, 'value2' => 5000, 'type3' => 3, 'value3' => 100000, 'type4' => 4, 'value4' => 0, 'type5' => 5, 'value5' => 1000, 'type6' => 6, 'value6' => 0],
        ['id' => 2, 'level' => 40, 'type1' => 1, 'value1' => 30000, 'type2' => 2, 'value2' => 15000, 'type3' => 3, 'value3' => 500000, 'type4' => 4, 'value4' => 500000, 'type5' => 5, 'value5' => 5000, 'type6' => 6, 'value6' => 1000],
        ['id' => 3, 'level' => 60, 'type1' => 1, 'value1' => 60000, 'type2' => 2, 'value2' => 30000, 'type3' => 3, 'value3' => 1500000, 'type4' => 4, 'value4' => 1000000, 'type5' => 5, 'value5' => 10000, 'type6' => 6, 'value6' => 2000],
        ['id' => 1, 'level' => 20, 'type1' => 1, 'value1' => 10000, 'type2' => 2, 'value2' => 5000, 'type3' => 3, 'value3' => 100000, 'type4' => 4, 'value4' => 0, 'type5' => 5, 'value5' => 1000, 'type6' => 6, 'value6' => 100],
        ['id' => 2, 'level' => 40, 'type1' => 1, 'value1' => 30000, 'type2' => 2, 'value2' => 15000, 'type3' => 3, 'value3' => 500000, 'type4' => 4, 'value4' => 500000, 'type5' => 5, 'value5' => 5000, 'type6' => 6, 'value6' => 1000],
        ['id' => 3, 'level' => 60, 'type1' => 1, 'value1' => 60000, 'type2' => 2, 'value2' => 30000, 'type3' => 3, 'value3' => 1500000, 'type4' => 4, 'value4' => 1000000, 'type5' => 5, 'value5' => 10000, 'type6' => 6, 'value6' => 2000],
        ['id' => 1, 'level' => 20, 'type1' => 1, 'value1' => 10000, 'type2' => 2, 'value2' => 5000, 'type3' => 3, 'value3' => 100000, 'type4' => 4, 'value4' => 0, 'type5' => 5, 'value5' => 2000, 'type6' => 6, 'value6' => 100],
        ['id' => 2, 'level' => 40, 'type1' => 1, 'value1' => 30000, 'type2' => 2, 'value2' => 15000, 'type3' => 3, 'value3' => 500000, 'type4' => 4, 'value4' => 500000, 'type5' => 5, 'value5' => 8000, 'type6' => 6, 'value6' => 2000],
        ['id' => 3, 'level' => 60, 'type1' => 1, 'value1' => 60000, 'type2' => 2, 'value2' => 30000, 'type3' => 3, 'value3' => 1500000, 'type4' => 4, 'value4' => 1000000, 'type5' => 5, 'value5' => 20000, 'type6' => 6, 'value6' => 5000],
    ];
}
