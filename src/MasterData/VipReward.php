<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * vip等级奖励表（旧版 zc_sanguo_vip_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class VipReward
{
    public const string TABLE = 'zc_sanguo_vip_reward';

    /**
     * 按主键 vip_level 索引，共 20 行。字段：
     *   - vip_level (tinyint) vip等级
     *   - get_type1 (tinyint) 奖励类型（1装备，2武将，3银币，4金翡翠，5紫翡翠，6蓝翡翠,，7玉石，8战魂，9突飞卡）
     *   - get_value1 (int) 奖励参数
     *   - get_type2 (tinyint) 奖励类型
     *   - get_value2 (int) 奖励参数
     *   - get_type3 (tinyint) 奖励类型
     *   - get_value3 (int) 奖励参数
     *   - get_type4 (tinyint) 奖励类型
     *   - get_value4 (int) 奖励参数
     */
    public const array ROWS = [
        1 => ['vip_level' => 1, 'get_type1' => 5, 'get_value1' => 1000, 'get_type2' => 6, 'get_value2' => 5000, 'get_type3' => 9, 'get_value3' => 1000, 'get_type4' => 0, 'get_value4' => 0],
        2 => ['vip_level' => 2, 'get_type1' => 5, 'get_value1' => 1500, 'get_type2' => 0, 'get_value2' => 5000, 'get_type3' => 0, 'get_value3' => 0, 'get_type4' => 0, 'get_value4' => 0],
        3 => ['vip_level' => 3, 'get_type1' => 5, 'get_value1' => 3000, 'get_type2' => 0, 'get_value2' => 0, 'get_type3' => 0, 'get_value3' => 0, 'get_type4' => 0, 'get_value4' => 0],
        4 => ['vip_level' => 4, 'get_type1' => 1, 'get_value1' => 204, 'get_type2' => 1, 'get_value2' => 214, 'get_type3' => 0, 'get_value3' => 0, 'get_type4' => 0, 'get_value4' => 0],
        5 => ['vip_level' => 5, 'get_type1' => 2, 'get_value1' => 20, 'get_type2' => 0, 'get_value2' => 0, 'get_type3' => 0, 'get_value3' => 0, 'get_type4' => 0, 'get_value4' => 0],
        6 => ['vip_level' => 6, 'get_type1' => 1, 'get_value1' => 74, 'get_type2' => 1, 'get_value2' => 75, 'get_type3' => 0, 'get_value3' => 0, 'get_type4' => 0, 'get_value4' => 0],
        7 => ['vip_level' => 7, 'get_type1' => 5, 'get_value1' => 4000, 'get_type2' => 0, 'get_value2' => 0, 'get_type3' => 0, 'get_value3' => 0, 'get_type4' => 0, 'get_value4' => 0],
        8 => ['vip_level' => 8, 'get_type1' => 5, 'get_value1' => 5000, 'get_type2' => 0, 'get_value2' => 0, 'get_type3' => 0, 'get_value3' => 0, 'get_type4' => 0, 'get_value4' => 0],
        9 => ['vip_level' => 9, 'get_type1' => 2, 'get_value1' => 69, 'get_type2' => 0, 'get_value2' => 0, 'get_type3' => 0, 'get_value3' => 0, 'get_type4' => 0, 'get_value4' => 0],
        10 => ['vip_level' => 10, 'get_type1' => 2, 'get_value1' => 122, 'get_type2' => 6, 'get_value2' => 10000, 'get_type3' => 5, 'get_value3' => 10000, 'get_type4' => 0, 'get_value4' => 0],
        11 => ['vip_level' => 11, 'get_type1' => 2, 'get_value1' => 130, 'get_type2' => 0, 'get_value2' => 20000, 'get_type3' => 5, 'get_value3' => 20000, 'get_type4' => 0, 'get_value4' => 0],
        12 => ['vip_level' => 12, 'get_type1' => 2, 'get_value1' => 70, 'get_type2' => 0, 'get_value2' => 30000, 'get_type3' => 5, 'get_value3' => 30000, 'get_type4' => 0, 'get_value4' => 0],
        13 => ['vip_level' => 13, 'get_type1' => 2, 'get_value1' => 167, 'get_type2' => 0, 'get_value2' => 50000, 'get_type3' => 5, 'get_value3' => 50000, 'get_type4' => 0, 'get_value4' => 0],
        14 => ['vip_level' => 14, 'get_type1' => 2, 'get_value1' => 168, 'get_type2' => 2, 'get_value2' => 181, 'get_type3' => 5, 'get_value3' => 100000, 'get_type4' => 6, 'get_value4' => 100000],
        15 => ['vip_level' => 15, 'get_type1' => 2, 'get_value1' => 185, 'get_type2' => 2, 'get_value2' => 186, 'get_type3' => 5, 'get_value3' => 200000, 'get_type4' => 6, 'get_value4' => 200000],
        16 => ['vip_level' => 16, 'get_type1' => 2, 'get_value1' => 187, 'get_type2' => 2, 'get_value2' => 189, 'get_type3' => 5, 'get_value3' => 500000, 'get_type4' => 6, 'get_value4' => 500000],
        17 => ['vip_level' => 17, 'get_type1' => 2, 'get_value1' => 195, 'get_type2' => 1, 'get_value2' => 92, 'get_type3' => 5, 'get_value3' => 1000000, 'get_type4' => 6, 'get_value4' => 1000000],
        18 => ['vip_level' => 18, 'get_type1' => 2, 'get_value1' => 196, 'get_type2' => 1, 'get_value2' => 91, 'get_type3' => 5, 'get_value3' => 2000000, 'get_type4' => 6, 'get_value4' => 2000000],
        19 => ['vip_level' => 19, 'get_type1' => 2, 'get_value1' => 194, 'get_type2' => 1, 'get_value2' => 90, 'get_type3' => 5, 'get_value3' => 5000000, 'get_type4' => 6, 'get_value4' => 5000000],
        20 => ['vip_level' => 20, 'get_type1' => 2, 'get_value1' => 191, 'get_type2' => 1, 'get_value2' => 89, 'get_type3' => 5, 'get_value3' => 9999999, 'get_type4' => 6, 'get_value4' => 9999999],
    ];
}
