<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 宝石模版表（旧版 zc_sanguo_gem）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Gem
{
    public const string TABLE = 'zc_sanguo_gem';

    /**
     * 按主键 id 索引，共 10 行。字段：
     *   - id (tinyint) 模版id
     *   - name (varchar) 宝石名称
     *   - type (tinyint) 加成属性（1物理攻击2物理防御3策略攻击4策略防御）
     *   - level0 (int) 等级0属性
     *   - level1 (int) 等级1属性
     *   - level2 (int) 等级2属性
     *   - level3 (int) 等级3属性
     *   - level4 (int) 等级4属性
     *   - level5 (int) 等级5属性
     *   - level6 (int) 等级6属性
     *   - level7 (int) 等级7属性
     *   - level8 (int) 等级8属性
     *   - level9 (int) 等级9属性
     *   - level10 (int) 等级10属性
     *   - level11 (int) 等级11属性
     *   - level12 (int) 等级12属性
     *   - level13 (int) 等级13属性
     *   - level14 (int) 等级14属性
     *   - level15 (int) 等级15属性
     *   - level16 (int) 等级16属性
     *   - level17 (int) 等级17属性
     *   - level18 (int) 等级18属性
     *   - level19 (int) 等级19属性
     *   - level20 (int) 等级20属性
     *   - level21 (int) 等级21属性
     *   - level22 (int) 等级22属性
     *   - level23 (int) 等级23属性
     *   - level24 (int) 等级24属性
     *   - level25 (int) 等级25属性
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '青龙石', 'type' => 1, 'level0' => 100, 'level1' => 122, 'level2' => 149, 'level3' => 182, 'level4' => 223, 'level5' => 273, 'level6' => 334, 'level7' => 408, 'level8' => 498, 'level9' => 608, 'level10' => 742, 'level11' => 906, 'level12' => 1106, 'level13' => 1350, 'level14' => 1647, 'level15' => 2010, 'level16' => 2453, 'level17' => 2993, 'level18' => 3652, 'level19' => 4456, 'level20' => 5437, 'level21' => 0, 'level22' => 0, 'level23' => 0, 'level24' => 0, 'level25' => 0],
        2 => ['id' => 2, 'name' => '玄武石', 'type' => 2, 'level0' => 75, 'level1' => 92, 'level2' => 112, 'level3' => 137, 'level4' => 167, 'level5' => 205, 'level6' => 251, 'level7' => 306, 'level8' => 374, 'level9' => 456, 'level10' => 557, 'level11' => 680, 'level12' => 830, 'level13' => 1013, 'level14' => 1235, 'level15' => 1508, 'level16' => 1840, 'level17' => 2245, 'level18' => 2739, 'level19' => 3342, 'level20' => 4078, 'level21' => 0, 'level22' => 0, 'level23' => 0, 'level24' => 0, 'level25' => 0],
        3 => ['id' => 3, 'name' => '朱雀石', 'type' => 3, 'level0' => 100, 'level1' => 122, 'level2' => 149, 'level3' => 182, 'level4' => 223, 'level5' => 273, 'level6' => 334, 'level7' => 408, 'level8' => 498, 'level9' => 608, 'level10' => 742, 'level11' => 906, 'level12' => 1106, 'level13' => 1350, 'level14' => 1647, 'level15' => 2010, 'level16' => 2453, 'level17' => 2993, 'level18' => 3652, 'level19' => 4456, 'level20' => 5437, 'level21' => 0, 'level22' => 0, 'level23' => 0, 'level24' => 0, 'level25' => 0],
        4 => ['id' => 4, 'name' => '白虎石', 'type' => 4, 'level0' => 75, 'level1' => 92, 'level2' => 112, 'level3' => 137, 'level4' => 167, 'level5' => 205, 'level6' => 251, 'level7' => 306, 'level8' => 374, 'level9' => 456, 'level10' => 557, 'level11' => 680, 'level12' => 830, 'level13' => 1013, 'level14' => 1235, 'level15' => 1508, 'level16' => 1840, 'level17' => 2245, 'level18' => 2739, 'level19' => 3342, 'level20' => 4078, 'level21' => 0, 'level22' => 0, 'level23' => 0, 'level24' => 0, 'level25' => 0],
        5 => ['id' => 5, 'name' => '黄麟石', 'type' => 5, 'level0' => 260, 'level1' => 318, 'level2' => 388, 'level3' => 474, 'level4' => 579, 'level5' => 707, 'level6' => 863, 'level7' => 1053, 'level8' => 1285, 'level9' => 1568, 'level10' => 1914, 'level11' => 2336, 'level12' => 2850, 'level13' => 3478, 'level14' => 4244, 'level15' => 5178, 'level16' => 6318, 'level17' => 7709, 'level18' => 9405, 'level19' => 11475, 'level20' => 14000, 'level21' => 0, 'level22' => 0, 'level23' => 0, 'level24' => 0, 'level25' => 0],
        6 => ['id' => 6, 'name' => '苍龙石', 'type' => 1, 'level0' => 0, 'level1' => 0, 'level2' => 0, 'level3' => 0, 'level4' => 0, 'level5' => 0, 'level6' => 0, 'level7' => 0, 'level8' => 0, 'level9' => 0, 'level10' => 0, 'level11' => 0, 'level12' => 0, 'level13' => 0, 'level14' => 0, 'level15' => 0, 'level16' => 0, 'level17' => 0, 'level18' => 0, 'level19' => 0, 'level20' => 0, 'level21' => 8155, 'level22' => 9949, 'level23' => 12138, 'level24' => 14809, 'level25' => 18067],
        7 => ['id' => 7, 'name' => '玄冥石', 'type' => 2, 'level0' => 0, 'level1' => 0, 'level2' => 0, 'level3' => 0, 'level4' => 0, 'level5' => 0, 'level6' => 0, 'level7' => 0, 'level8' => 0, 'level9' => 0, 'level10' => 0, 'level11' => 0, 'level12' => 0, 'level13' => 0, 'level14' => 0, 'level15' => 0, 'level16' => 0, 'level17' => 0, 'level18' => 0, 'level19' => 0, 'level20' => 0, 'level21' => 6117, 'level22' => 7462, 'level23' => 9104, 'level24' => 11107, 'level25' => 13551],
        8 => ['id' => 8, 'name' => '长离石', 'type' => 3, 'level0' => 0, 'level1' => 0, 'level2' => 0, 'level3' => 0, 'level4' => 0, 'level5' => 0, 'level6' => 0, 'level7' => 0, 'level8' => 0, 'level9' => 0, 'level10' => 0, 'level11' => 0, 'level12' => 0, 'level13' => 0, 'level14' => 0, 'level15' => 0, 'level16' => 0, 'level17' => 0, 'level18' => 0, 'level19' => 0, 'level20' => 0, 'level21' => 8155, 'level22' => 9949, 'level23' => 12138, 'level24' => 14809, 'level25' => 18067],
        9 => ['id' => 9, 'name' => '百兽石', 'type' => 4, 'level0' => 0, 'level1' => 0, 'level2' => 0, 'level3' => 0, 'level4' => 0, 'level5' => 0, 'level6' => 0, 'level7' => 0, 'level8' => 0, 'level9' => 0, 'level10' => 0, 'level11' => 0, 'level12' => 0, 'level13' => 0, 'level14' => 0, 'level15' => 0, 'level16' => 0, 'level17' => 0, 'level18' => 0, 'level19' => 0, 'level20' => 0, 'level21' => 6117, 'level22' => 7462, 'level23' => 9104, 'level24' => 11107, 'level25' => 13551],
        10 => ['id' => 10, 'name' => '天命石', 'type' => 5, 'level0' => 0, 'level1' => 0, 'level2' => 0, 'level3' => 0, 'level4' => 0, 'level5' => 0, 'level6' => 0, 'level7' => 0, 'level8' => 0, 'level9' => 0, 'level10' => 0, 'level11' => 0, 'level12' => 0, 'level13' => 0, 'level14' => 0, 'level15' => 0, 'level16' => 0, 'level17' => 0, 'level18' => 0, 'level19' => 0, 'level20' => 0, 'level21' => 21000, 'level22' => 25620, 'level23' => 31256, 'level24' => 38132, 'level25' => 46522],
    ];
}
