<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * vip工资表（旧版 zc_sanguo_vip_wage）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class VipWage
{
    public const string TABLE = 'zc_sanguo_vip_wage';

    /**
     * 按主键 vip 索引，共 20 行。字段：
     *   - vip (tinyint) vip等级
     *   - type1 (tinyint) 工资类型1（1银，2突，3军，4蓝，5金，6紫，7原魂，8玉石）
     *   - get1 (int) 参数1
     *   - type2 (tinyint) 工资类型2（1银，2突，3军，4蓝，5金，6紫，7原魂，8玉石）
     *   - get2 (int) 参数2
     *   - type3 (tinyint) 工资类型3（1银，2突，3军，4蓝，5金，6紫，7原魂，8玉石）
     *   - get3 (int) 参数3
     */
    public const array ROWS = [
        1 => ['vip' => 1, 'type1' => 1, 'get1' => 1000, 'type2' => 3, 'get2' => 5, 'type3' => 2, 'get3' => 3],
        2 => ['vip' => 2, 'type1' => 1, 'get1' => 1500, 'type2' => 3, 'get2' => 10, 'type3' => 2, 'get3' => 6],
        3 => ['vip' => 3, 'type1' => 1, 'get1' => 2000, 'type2' => 3, 'get2' => 15, 'type3' => 2, 'get3' => 9],
        4 => ['vip' => 4, 'type1' => 1, 'get1' => 2500, 'type2' => 3, 'get2' => 20, 'type3' => 2, 'get3' => 12],
        5 => ['vip' => 5, 'type1' => 1, 'get1' => 3000, 'type2' => 3, 'get2' => 25, 'type3' => 2, 'get3' => 15],
        6 => ['vip' => 6, 'type1' => 7, 'get1' => 1000, 'type2' => 6, 'get2' => 5, 'type3' => 2, 'get3' => 18],
        7 => ['vip' => 7, 'type1' => 7, 'get1' => 1500, 'type2' => 6, 'get2' => 8, 'type3' => 2, 'get3' => 21],
        8 => ['vip' => 8, 'type1' => 7, 'get1' => 2000, 'type2' => 6, 'get2' => 11, 'type3' => 2, 'get3' => 24],
        9 => ['vip' => 9, 'type1' => 7, 'get1' => 2500, 'type2' => 6, 'get2' => 14, 'type3' => 2, 'get3' => 27],
        10 => ['vip' => 10, 'type1' => 7, 'get1' => 30000, 'type2' => 6, 'get2' => 100, 'type3' => 2, 'get3' => 300],
        11 => ['vip' => 11, 'type1' => 7, 'get1' => 40000, 'type2' => 6, 'get2' => 150, 'type3' => 9, 'get3' => 20000],
        12 => ['vip' => 12, 'type1' => 7, 'get1' => 50000, 'type2' => 6, 'get2' => 200, 'type3' => 9, 'get3' => 35000],
        13 => ['vip' => 13, 'type1' => 7, 'get1' => 60000, 'type2' => 6, 'get2' => 250, 'type3' => 9, 'get3' => 50000],
        14 => ['vip' => 14, 'type1' => 7, 'get1' => 70000, 'type2' => 6, 'get2' => 300, 'type3' => 9, 'get3' => 70000],
        15 => ['vip' => 15, 'type1' => 7, 'get1' => 80000, 'type2' => 6, 'get2' => 350, 'type3' => 9, 'get3' => 80000],
        16 => ['vip' => 16, 'type1' => 7, 'get1' => 90000, 'type2' => 6, 'get2' => 400, 'type3' => 9, 'get3' => 90000],
        17 => ['vip' => 17, 'type1' => 7, 'get1' => 10000, 'type2' => 6, 'get2' => 450, 'type3' => 9, 'get3' => 100000],
        18 => ['vip' => 18, 'type1' => 7, 'get1' => 15000, 'type2' => 6, 'get2' => 500, 'type3' => 9, 'get3' => 150000],
        19 => ['vip' => 19, 'type1' => 7, 'get1' => 20000, 'type2' => 6, 'get2' => 1000, 'type3' => 9, 'get3' => 200000],
        20 => ['vip' => 20, 'type1' => 7, 'get1' => 40000, 'type2' => 6, 'get2' => 5000, 'type3' => 9, 'get3' => 400000],
    ];
}
