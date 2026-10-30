<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 名将进阶数据表（旧版 zc_sanguo_advanced_info）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class AdvancedInfo
{
    public const string TABLE = 'zc_sanguo_advanced_info';

    /**
     * 按主键 id 索引，共 6 行。字段：
     *   - id (int) id
     *   - skill_level (int) 需要技能等级
     *   - use_type1 (tinyint) 消耗类型1（1黄宝石，2紫宝石，3原魂，4声望）
     *   - use_value1 (int) 消耗参数1
     *   - use_type2 (tinyint) 消耗类型2
     *   - use_value2 (int) 消耗参数2
     *   - use_type3 (tinyint) 消耗类型3
     *   - use_value3 (int) 消耗参数3
     *   - use_type4 (tinyint) 消耗类型4
     *   - use_value4 (int) 消耗参数4
     *   - wuli (int) 加成初始武力
     *   - zhili (int) 加成初始智力
     *   - tili (int) 加成初始体力
     *   - hp (int) 加成初始生命
     */
    public const array ROWS = [
        2 => ['id' => 2, 'skill_level' => 3, 'use_type1' => 1, 'use_value1' => 1800, 'use_type2' => 2, 'use_value2' => 1800, 'use_type3' => 3, 'use_value3' => 90000, 'use_type4' => 4, 'use_value4' => 0, 'wuli' => 30, 'zhili' => 30, 'tili' => 30, 'hp' => 37500],
        3 => ['id' => 3, 'skill_level' => 6, 'use_type1' => 1, 'use_value1' => 4000, 'use_type2' => 2, 'use_value2' => 4000, 'use_type3' => 3, 'use_value3' => 200000, 'use_type4' => 4, 'use_value4' => 0, 'wuli' => 50, 'zhili' => 50, 'tili' => 50, 'hp' => 62500],
        4 => ['id' => 4, 'skill_level' => 9, 'use_type1' => 1, 'use_value1' => 8000, 'use_type2' => 2, 'use_value2' => 8000, 'use_type3' => 3, 'use_value3' => 400000, 'use_type4' => 4, 'use_value4' => 400000, 'wuli' => 80, 'zhili' => 80, 'tili' => 80, 'hp' => 100000],
        5 => ['id' => 5, 'skill_level' => 12, 'use_type1' => 1, 'use_value1' => 12000, 'use_type2' => 2, 'use_value2' => 12000, 'use_type3' => 3, 'use_value3' => 600000, 'use_type4' => 4, 'use_value4' => 600000, 'wuli' => 120, 'zhili' => 120, 'tili' => 120, 'hp' => 150000],
        6 => ['id' => 6, 'skill_level' => 15, 'use_type1' => 1, 'use_value1' => 20000, 'use_type2' => 2, 'use_value2' => 20000, 'use_type3' => 3, 'use_value3' => 1000000, 'use_type4' => 4, 'use_value4' => 1000000, 'wuli' => 200, 'zhili' => 200, 'tili' => 200, 'hp' => 250000],
        7 => ['id' => 7, 'skill_level' => 20, 'use_type1' => 1, 'use_value1' => 30000, 'use_type2' => 2, 'use_value2' => 30000, 'use_type3' => 3, 'use_value3' => 1500000, 'use_type4' => 4, 'use_value4' => 1500000, 'wuli' => 300, 'zhili' => 300, 'tili' => 300, 'hp' => 500000],
    ];
}
