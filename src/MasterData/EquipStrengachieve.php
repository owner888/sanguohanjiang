<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 装备强化成就表（旧版 zc_sanguo_equip_strengachieve）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class EquipStrengachieve
{
    public const string TABLE = 'zc_sanguo_equip_strengachieve';

    /**
     * 按主键 id 索引，共 9 行。字段：
     *   - id (int) 主键ID
     *   - level (int) 强化等级
     *   - name (varchar) 成就名称
     *   - type1 (int) 加成装备类型1
     *   - add_type1 (int) 装备加成属性1
     *   - add_per1 (float) 装备加成百分比1
     *   - item1 (int) 消耗物资ID1
     *   - item_num1 (int) 消耗物资数量1
     *   - type2 (int) 加成装备类型2
     *   - add_type2 (int) 装备加成属性2
     *   - add_per2 (float) 装备加成百分比2
     *   - item2 (int) 消耗物资ID2
     *   - item_num2 (int) 消耗物资数量2
     *   - type3 (int) 加成装备类型3
     *   - add_type3 (int) 装备加成属性3
     *   - add_per3 (float) 装备加成百分比3
     *   - item3 (int) 消耗物资ID3
     *   - item_num3 (int) 消耗物资数量3
     *   - type4 (int) 加成装备类型4
     *   - add_type4 (int) 装备加成属性4
     *   - add_per4 (float) 装备加成百分比4
     *   - item4 (int) 消耗物资ID4
     *   - item_num4 (int) 消耗物资数量4
     *   - type5 (int) 加成装备类型5
     *   - add_type5 (int) 装备加成属性5
     *   - add_per5 (float) 装备加成百分比5
     *   - item5 (int) 消耗物资ID5
     *   - item_num5 (int) 消耗物资数量5
     */
    public const array ROWS = [
        1 => ['id' => 1, 'level' => 40, 'name' => '强化至40级', 'type1' => 1, 'add_type1' => 1, 'add_per1' => 5.0, 'item1' => 1, 'item_num1' => 2, 'type2' => 2, 'add_type2' => 1, 'add_per2' => 5.0, 'item2' => 1, 'item_num2' => 2, 'type3' => 3, 'add_type3' => 2, 'add_per3' => 5.0, 'item3' => 7, 'item_num3' => 2, 'type4' => 4, 'add_type4' => 2, 'add_per4' => 5.0, 'item4' => 7, 'item_num4' => 2, 'type5' => 5, 'add_type5' => 3, 'add_per5' => 5.0, 'item5' => 13, 'item_num5' => 2],
        2 => ['id' => 2, 'level' => 60, 'name' => '强化至60级', 'type1' => 1, 'add_type1' => 1, 'add_per1' => 10.0, 'item1' => 1, 'item_num1' => 3, 'type2' => 2, 'add_type2' => 1, 'add_per2' => 10.0, 'item2' => 1, 'item_num2' => 3, 'type3' => 3, 'add_type3' => 2, 'add_per3' => 10.0, 'item3' => 7, 'item_num3' => 3, 'type4' => 4, 'add_type4' => 2, 'add_per4' => 10.0, 'item4' => 7, 'item_num4' => 3, 'type5' => 5, 'add_type5' => 3, 'add_per5' => 10.0, 'item5' => 13, 'item_num5' => 3],
        3 => ['id' => 3, 'level' => 80, 'name' => '强化至80级', 'type1' => 1, 'add_type1' => 1, 'add_per1' => 15.0, 'item1' => 1, 'item_num1' => 4, 'type2' => 2, 'add_type2' => 1, 'add_per2' => 15.0, 'item2' => 1, 'item_num2' => 4, 'type3' => 3, 'add_type3' => 2, 'add_per3' => 15.0, 'item3' => 7, 'item_num3' => 4, 'type4' => 4, 'add_type4' => 2, 'add_per4' => 15.0, 'item4' => 7, 'item_num4' => 4, 'type5' => 5, 'add_type5' => 3, 'add_per5' => 15.0, 'item5' => 13, 'item_num5' => 4],
        4 => ['id' => 4, 'level' => 100, 'name' => '强化至100级', 'type1' => 1, 'add_type1' => 1, 'add_per1' => 20.0, 'item1' => 1, 'item_num1' => 5, 'type2' => 2, 'add_type2' => 1, 'add_per2' => 20.0, 'item2' => 1, 'item_num2' => 5, 'type3' => 3, 'add_type3' => 2, 'add_per3' => 20.0, 'item3' => 7, 'item_num3' => 5, 'type4' => 4, 'add_type4' => 2, 'add_per4' => 20.0, 'item4' => 7, 'item_num4' => 5, 'type5' => 5, 'add_type5' => 3, 'add_per5' => 20.0, 'item5' => 13, 'item_num5' => 5],
        5 => ['id' => 5, 'level' => 120, 'name' => '强化至120级', 'type1' => 1, 'add_type1' => 1, 'add_per1' => 25.0, 'item1' => 1, 'item_num1' => 10, 'type2' => 2, 'add_type2' => 1, 'add_per2' => 25.0, 'item2' => 1, 'item_num2' => 10, 'type3' => 3, 'add_type3' => 2, 'add_per3' => 25.0, 'item3' => 7, 'item_num3' => 10, 'type4' => 4, 'add_type4' => 2, 'add_per4' => 25.0, 'item4' => 7, 'item_num4' => 10, 'type5' => 5, 'add_type5' => 3, 'add_per5' => 25.0, 'item5' => 13, 'item_num5' => 10],
        6 => ['id' => 6, 'level' => 140, 'name' => '强化至140级', 'type1' => 1, 'add_type1' => 1, 'add_per1' => 30.0, 'item1' => 1, 'item_num1' => 15, 'type2' => 2, 'add_type2' => 1, 'add_per2' => 30.0, 'item2' => 1, 'item_num2' => 15, 'type3' => 3, 'add_type3' => 2, 'add_per3' => 30.0, 'item3' => 7, 'item_num3' => 15, 'type4' => 4, 'add_type4' => 2, 'add_per4' => 30.0, 'item4' => 7, 'item_num4' => 15, 'type5' => 5, 'add_type5' => 3, 'add_per5' => 30.0, 'item5' => 13, 'item_num5' => 15],
        7 => ['id' => 7, 'level' => 160, 'name' => '强化至160级', 'type1' => 1, 'add_type1' => 1, 'add_per1' => 35.0, 'item1' => 2, 'item_num1' => 10, 'type2' => 2, 'add_type2' => 1, 'add_per2' => 35.0, 'item2' => 2, 'item_num2' => 10, 'type3' => 3, 'add_type3' => 2, 'add_per3' => 35.0, 'item3' => 8, 'item_num3' => 10, 'type4' => 4, 'add_type4' => 2, 'add_per4' => 35.0, 'item4' => 8, 'item_num4' => 10, 'type5' => 5, 'add_type5' => 3, 'add_per5' => 35.0, 'item5' => 14, 'item_num5' => 10],
        8 => ['id' => 8, 'level' => 180, 'name' => '强化至180级', 'type1' => 1, 'add_type1' => 1, 'add_per1' => 40.0, 'item1' => 2, 'item_num1' => 15, 'type2' => 2, 'add_type2' => 1, 'add_per2' => 40.0, 'item2' => 2, 'item_num2' => 15, 'type3' => 3, 'add_type3' => 2, 'add_per3' => 40.0, 'item3' => 8, 'item_num3' => 15, 'type4' => 4, 'add_type4' => 2, 'add_per4' => 40.0, 'item4' => 8, 'item_num4' => 15, 'type5' => 5, 'add_type5' => 3, 'add_per5' => 40.0, 'item5' => 14, 'item_num5' => 15],
        9 => ['id' => 9, 'level' => 200, 'name' => '强化至200级', 'type1' => 1, 'add_type1' => 1, 'add_per1' => 45.0, 'item1' => 2, 'item_num1' => 20, 'type2' => 2, 'add_type2' => 1, 'add_per2' => 45.0, 'item2' => 2, 'item_num2' => 20, 'type3' => 3, 'add_type3' => 2, 'add_per3' => 45.0, 'item3' => 8, 'item_num3' => 20, 'type4' => 4, 'add_type4' => 2, 'add_per4' => 45.0, 'item4' => 8, 'item_num4' => 20, 'type5' => 5, 'add_type5' => 3, 'add_per5' => 45.0, 'item5' => 14, 'item_num5' => 20],
    ];
}
