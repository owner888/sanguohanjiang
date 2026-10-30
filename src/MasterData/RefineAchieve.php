<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 装备精炼成就表（旧版 zc_sanguo_refine_achieve）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class RefineAchieve
{
    public const string TABLE = 'zc_sanguo_refine_achieve';

    /**
     * 按主键 id 索引，共 7 行。字段：
     *   - id (int) 主键ID
     *   - level (int) 精炼等级
     *   - name (varchar) 成就名称
     *   - add_type1 (int) 增益类型1
     *   - add_type_val1 (int) 增益类型值1
     *   - add_type2 (int) 增益类型2
     *   - add_type_val2 (int) 增益类型值2
     *   - add_type3 (int) 增益类型3
     *   - add_type_val3 (int) 增益类型值3
     *   - add_type4 (int) 增益类型4
     *   - add_type_val4 (int) 增益类型值4
     */
    public const array ROWS = [
        1 => ['id' => 1, 'level' => 1, 'name' => '普通', 'add_type1' => 1, 'add_type_val1' => 10, 'add_type2' => 2, 'add_type_val2' => 10, 'add_type3' => 3, 'add_type_val3' => 500, 'add_type4' => 4, 'add_type_val4' => 500],
        2 => ['id' => 2, 'level' => 10, 'name' => '加强', 'add_type1' => 1, 'add_type_val1' => 20, 'add_type2' => 2, 'add_type_val2' => 20, 'add_type3' => 3, 'add_type_val3' => 1000, 'add_type4' => 4, 'add_type_val4' => 1000],
        3 => ['id' => 3, 'level' => 20, 'name' => '高级', 'add_type1' => 1, 'add_type_val1' => 35, 'add_type2' => 2, 'add_type_val2' => 35, 'add_type3' => 3, 'add_type_val3' => 1800, 'add_type4' => 4, 'add_type_val4' => 1800],
        4 => ['id' => 4, 'level' => 30, 'name' => '白金', 'add_type1' => 1, 'add_type_val1' => 50, 'add_type2' => 2, 'add_type_val2' => 50, 'add_type3' => 3, 'add_type_val3' => 3100, 'add_type4' => 4, 'add_type_val4' => 3100],
        5 => ['id' => 5, 'level' => 40, 'name' => '至尊', 'add_type1' => 1, 'add_type_val1' => 75, 'add_type2' => 2, 'add_type_val2' => 75, 'add_type3' => 3, 'add_type_val3' => 5100, 'add_type4' => 4, 'add_type_val4' => 5100],
        6 => ['id' => 6, 'level' => 50, 'name' => '神话', 'add_type1' => 1, 'add_type_val1' => 110, 'add_type2' => 2, 'add_type_val2' => 110, 'add_type3' => 3, 'add_type_val3' => 7900, 'add_type4' => 4, 'add_type_val4' => 7900],
        7 => ['id' => 7, 'level' => 60, 'name' => '传奇', 'add_type1' => 1, 'add_type_val1' => 200, 'add_type2' => 2, 'add_type_val2' => 200, 'add_type3' => 3, 'add_type_val3' => 11600, 'add_type4' => 4, 'add_type_val4' => 11600],
    ];
}
