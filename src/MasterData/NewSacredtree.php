<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 神树模版表（旧版 zc_sanguo_new_sacredtree）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class NewSacredtree
{
    public const string TABLE = 'zc_sanguo_new_sacredtree';

    /**
     * 按主键 id 索引，共 7 行。字段：
     *   - id (int) 神树id
     *   - name (varchar) 神树名称
     *   - exp (int) 经验值
     *   - buff (tinyint) 血量buff
     *   - get1_type (tinyint) 奖励1类型
     *   - get1_value1 (int) 奖励1参数1
     *   - get1_value2 (int) 奖励1参数2
     *   - get2_type (tinyint) 奖励2类型
     *   - get2_value1 (int) 奖励2参数1
     *   - get2_value2 (int) 奖励2参数2
     *   - get3_type (tinyint) 奖励3类型
     *   - get3_value1 (int) 奖励3参数1
     *   - get3_value2 (int) 奖励3参数2
     *   - get4_type (tinyint) 奖励4类型
     *   - get4_value1 (int) 奖励4参数1
     *   - get4_value2 (int) 奖励4参数2
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '初级灵树', 'exp' => 0, 'buff' => 0, 'get1_type' => 0, 'get1_value1' => 0, 'get1_value2' => 0, 'get2_type' => 0, 'get2_value1' => 0, 'get2_value2' => 0, 'get3_type' => 0, 'get3_value1' => 0, 'get3_value2' => 0, 'get4_type' => 0, 'get4_value1' => 0, 'get4_value2' => 0],
        2 => ['id' => 2, 'name' => '流光宝树', 'exp' => 800, 'buff' => 5, 'get1_type' => 1, 'get1_value1' => 500, 'get1_value2' => 0, 'get2_type' => 2, 'get2_value1' => 50, 'get2_value2' => 0, 'get3_type' => 3, 'get3_value1' => 5000000, 'get3_value2' => 0, 'get4_type' => 0, 'get4_value1' => 0, 'get4_value2' => 0],
        3 => ['id' => 3, 'name' => '南海妙树', 'exp' => 2000, 'buff' => 10, 'get1_type' => 9, 'get1_value1' => 1, 'get1_value2' => 0, 'get2_type' => 2, 'get2_value1' => 100, 'get2_value2' => 0, 'get3_type' => 4, 'get3_value1' => 500, 'get3_value2' => 0, 'get4_type' => 0, 'get4_value1' => 0, 'get4_value2' => 0],
        4 => ['id' => 4, 'name' => '青帝圣树', 'exp' => 5000, 'buff' => 15, 'get1_type' => 5, 'get1_value1' => 500, 'get1_value2' => 0, 'get2_type' => 2, 'get2_value1' => 200, 'get2_value2' => 0, 'get3_type' => 6, 'get3_value1' => 10000, 'get3_value2' => 0, 'get4_type' => 0, 'get4_value1' => 0, 'get4_value2' => 0],
        5 => ['id' => 5, 'name' => '九天仙树', 'exp' => 20000, 'buff' => 20, 'get1_type' => 12, 'get1_value1' => 15, 'get1_value2' => 3, 'get2_type' => 13, 'get2_value1' => 15, 'get2_value2' => 3, 'get3_type' => 14, 'get3_value1' => 15, 'get3_value2' => 3, 'get4_type' => 15, 'get4_value1' => 15, 'get4_value2' => 3],
        6 => ['id' => 6, 'name' => '大荒神树', 'exp' => 50000, 'buff' => 25, 'get1_type' => 17, 'get1_value1' => 74, 'get1_value2' => 1, 'get2_type' => 17, 'get2_value1' => 75, 'get2_value2' => 1, 'get3_type' => 0, 'get3_value1' => 0, 'get3_value2' => 0, 'get4_type' => 0, 'get4_value1' => 0, 'get4_value2' => 0],
        7 => ['id' => 7, 'name' => '混沌古树', 'exp' => 100000, 'buff' => 30, 'get1_type' => 17, 'get1_value1' => 76, 'get1_value2' => 1, 'get2_type' => 16, 'get2_value1' => 500000, 'get2_value2' => 0, 'get3_type' => 0, 'get3_value1' => 0, 'get3_value2' => 0, 'get4_type' => 0, 'get4_value1' => 0, 'get4_value2' => 0],
    ];
}
