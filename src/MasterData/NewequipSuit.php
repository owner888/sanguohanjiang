<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 套装属性数据表（旧版 zc_sanguo_newequip_suit）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class NewequipSuit
{
    public const string TABLE = 'zc_sanguo_newequip_suit';

    /**
     * 按主键 id 索引，共 16 行。字段：
     *   - id (mediumint) 分解唯一id
     *   - name (varchar) 套装名称
     *   - eid1 (mediumint) 装备id1
     *   - eid2 (mediumint) 装备id2
     *   - eid3 (mediumint) 装备id3
     *   - eid4 (mediumint) 装备id4
     *   - suit2_1 (mediumint) 二件套属性1
     *   - params2_1 (mediumint) 二件套参数1
     *   - suit2_2 (mediumint) 二件套属性2
     *   - params2_2 (mediumint) 二件套参数2
     *   - suit3_1 (mediumint) 三件套
     *   - params3_1 (mediumint) 三件套属性
     *   - suit3_2 (mediumint) 三件套
     *   - params3_2 (mediumint) 三件套属性
     *   - suit4_1 (mediumint) 四件套
     *   - params4_1 (mediumint) 四件套属性
     *   - suit4_2 (mediumint) 四件套
     *   - params4_2 (mediumint) 四件套属性
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '奔雷套装', 'eid1' => 81, 'eid2' => 82, 'eid3' => 83, 'eid4' => 84, 'suit2_1' => 6, 'params2_1' => 500, 'suit2_2' => 8, 'params2_2' => 500, 'suit3_1' => 1, 'params3_1' => 20, 'suit3_2' => 5, 'params3_2' => 800, 'suit4_1' => 18, 'params4_1' => 20, 'suit4_2' => 24, 'params4_2' => 25],
        2 => ['id' => 2, 'name' => '七星套装', 'eid1' => 85, 'eid2' => 86, 'eid3' => 87, 'eid4' => 88, 'suit2_1' => 6, 'params2_1' => 500, 'suit2_2' => 8, 'params2_2' => 500, 'suit3_1' => 2, 'params3_1' => 20, 'suit3_2' => 7, 'params3_2' => 800, 'suit4_1' => 18, 'params4_1' => 20, 'suit4_2' => 24, 'params4_2' => 25],
        3 => ['id' => 3, 'name' => '白虎套装', 'eid1' => 97, 'eid2' => 98, 'eid3' => 99, 'eid4' => 100, 'suit2_1' => 3, 'params2_1' => 30, 'suit2_2' => 15, 'params2_2' => 10, 'suit3_1' => 1, 'params3_1' => 30, 'suit3_2' => 8, 'params3_2' => 2000, 'suit4_1' => 18, 'params4_1' => 40, 'suit4_2' => 23, 'params4_2' => 25],
        4 => ['id' => 4, 'name' => '玄武套装', 'eid1' => 101, 'eid2' => 102, 'eid3' => 103, 'eid4' => 104, 'suit2_1' => 3, 'params2_1' => 30, 'suit2_2' => 15, 'params2_2' => 10, 'suit3_1' => 2, 'params3_1' => 30, 'suit3_2' => 6, 'params3_2' => 2000, 'suit4_1' => 18, 'params4_1' => 40, 'suit4_2' => 23, 'params4_2' => 25],
        5 => ['id' => 5, 'name' => '青龙套装', 'eid1' => 89, 'eid2' => 90, 'eid3' => 91, 'eid4' => 92, 'suit2_1' => 5, 'params2_1' => 2000, 'suit2_2' => 15, 'params2_2' => 15, 'suit3_1' => 1, 'params3_1' => 60, 'suit3_2' => 10, 'params3_2' => 15, 'suit4_1' => 18, 'params4_1' => 60, 'suit4_2' => 19, 'params4_2' => 25],
        6 => ['id' => 6, 'name' => '朱雀套装', 'eid1' => 93, 'eid2' => 94, 'eid3' => 95, 'eid4' => 96, 'suit2_1' => 7, 'params2_1' => 2000, 'suit2_2' => 15, 'params2_2' => 15, 'suit3_1' => 2, 'params3_1' => 60, 'suit3_2' => 10, 'params3_2' => 15, 'suit4_1' => 18, 'params4_1' => 60, 'suit4_2' => 19, 'params4_2' => 25],
        7 => ['id' => 7, 'name' => '四圣套装（猛）', 'eid1' => 105, 'eid2' => 106, 'eid3' => 107, 'eid4' => 108, 'suit2_1' => 3, 'params2_1' => 80, 'suit2_2' => 4, 'params2_2' => 15, 'suit3_1' => 1, 'params3_1' => 80, 'suit3_2' => 10, 'params3_2' => 20, 'suit4_1' => 18, 'params4_1' => 80, 'suit4_2' => 23, 'params4_2' => 50],
        8 => ['id' => 8, 'name' => '四圣套装（谋）', 'eid1' => 109, 'eid2' => 110, 'eid3' => 111, 'eid4' => 112, 'suit2_1' => 3, 'params2_1' => 80, 'suit2_2' => 4, 'params2_2' => 15, 'suit3_1' => 2, 'params3_1' => 80, 'suit3_2' => 10, 'params3_2' => 20, 'suit4_1' => 18, 'params4_1' => 80, 'suit4_2' => 23, 'params4_2' => 50],
        9 => ['id' => 9, 'name' => '斩仙套装', 'eid1' => 113, 'eid2' => 114, 'eid3' => 115, 'eid4' => 116, 'suit2_1' => 3, 'params2_1' => 100, 'suit2_2' => 4, 'params2_2' => 20, 'suit3_1' => 1, 'params3_1' => 100, 'suit3_2' => 14, 'params3_2' => 25, 'suit4_1' => 18, 'params4_1' => 100, 'suit4_2' => 25, 'params4_2' => 100],
        10 => ['id' => 10, 'name' => '诛邪套装', 'eid1' => 117, 'eid2' => 118, 'eid3' => 119, 'eid4' => 120, 'suit2_1' => 3, 'params2_1' => 100, 'suit2_2' => 4, 'params2_2' => 20, 'suit3_1' => 2, 'params3_1' => 100, 'suit3_2' => 13, 'params3_2' => 25, 'suit4_1' => 18, 'params4_1' => 100, 'suit4_2' => 25, 'params4_2' => 100],
        11 => ['id' => 11, 'name' => '裂天套装', 'eid1' => 121, 'eid2' => 122, 'eid3' => 123, 'eid4' => 124, 'suit2_1' => 3, 'params2_1' => 100, 'suit2_2' => 16, 'params2_2' => 20, 'suit3_1' => 1, 'params3_1' => 100, 'suit3_2' => 4, 'params3_2' => 25, 'suit4_1' => 18, 'params4_1' => 100, 'suit4_2' => 26, 'params4_2' => 25],
        12 => ['id' => 12, 'name' => '卧龙套装', 'eid1' => 125, 'eid2' => 126, 'eid3' => 127, 'eid4' => 128, 'suit2_1' => 3, 'params2_1' => 100, 'suit2_2' => 16, 'params2_2' => 20, 'suit3_1' => 2, 'params3_1' => 100, 'suit3_2' => 4, 'params3_2' => 25, 'suit4_1' => 18, 'params4_1' => 100, 'suit4_2' => 26, 'params4_2' => 25],
        13 => ['id' => 13, 'name' => '破军套装', 'eid1' => 129, 'eid2' => 130, 'eid3' => 131, 'eid4' => 132, 'suit2_1' => 3, 'params2_1' => 100, 'suit2_2' => 10, 'params2_2' => 20, 'suit3_1' => 1, 'params3_1' => 100, 'suit3_2' => 19, 'params3_2' => 25, 'suit4_1' => 18, 'params4_1' => 100, 'suit4_2' => 20, 'params4_2' => 30],
        14 => ['id' => 14, 'name' => '贪狼套装', 'eid1' => 133, 'eid2' => 134, 'eid3' => 135, 'eid4' => 136, 'suit2_1' => 3, 'params2_1' => 100, 'suit2_2' => 10, 'params2_2' => 20, 'suit3_1' => 2, 'params3_1' => 100, 'suit3_2' => 19, 'params3_2' => 25, 'suit4_1' => 18, 'params4_1' => 100, 'suit4_2' => 20, 'params4_2' => 30],
        15 => ['id' => 15, 'name' => '黄泉碧落', 'eid1' => 200, 'eid2' => 201, 'eid3' => 202, 'eid4' => 203, 'suit2_1' => 3, 'params2_1' => 100, 'suit2_2' => 4, 'params2_2' => 30, 'suit3_1' => 1, 'params3_1' => 150, 'suit3_2' => 19, 'params3_2' => 40, 'suit4_1' => 18, 'params4_1' => 200, 'suit4_2' => 20, 'params4_2' => 50],
        16 => ['id' => 16, 'name' => '十死九生', 'eid1' => 210, 'eid2' => 211, 'eid3' => 212, 'eid4' => 213, 'suit2_1' => 3, 'params2_1' => 100, 'suit2_2' => 4, 'params2_2' => 30, 'suit3_1' => 2, 'params3_1' => 150, 'suit3_2' => 19, 'params3_2' => 40, 'suit4_1' => 18, 'params4_1' => 200, 'suit4_2' => 20, 'params4_2' => 50],
    ];
}
