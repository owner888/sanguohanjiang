<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 爱心成就奖励表（旧版 zc_sanguo_act_moon_ach_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActMoonAchReward
{
    public const string TABLE = 'zc_sanguo_act_moon_ach_reward';

    /**
     * 按主键 id 索引，共 14 行。字段：
     *   - id (mediumint) 自增id
     *   - achlove (mediumint) 达成次数
     *   - gettype1 (mediumint) 获得类型1
     *   - getvalue1 (mediumint) 获得类型1
     *   - gettype2 (mediumint) 获得类型2
     *   - getvalue2 (mediumint) 获得数量2
     *   - gettype3 (mediumint) 获得类型3
     *   - getvalue3 (mediumint) 获得数量3
     */
    public const array ROWS = [
        1 => ['id' => 1, 'achlove' => 10, 'gettype1' => 4, 'getvalue1' => 50, 'gettype2' => 10, 'getvalue2' => 50, 'gettype3' => 7, 'getvalue3' => 5000],
        2 => ['id' => 2, 'achlove' => 50, 'gettype1' => 4, 'getvalue1' => 100, 'gettype2' => 10, 'getvalue2' => 100, 'gettype3' => 7, 'getvalue3' => 10000],
        3 => ['id' => 3, 'achlove' => 150, 'gettype1' => 4, 'getvalue1' => 200, 'gettype2' => 10, 'getvalue2' => 300, 'gettype3' => 7, 'getvalue3' => 20000],
        4 => ['id' => 4, 'achlove' => 300, 'gettype1' => 4, 'getvalue1' => 300, 'gettype2' => 10, 'getvalue2' => 400, 'gettype3' => 7, 'getvalue3' => 30000],
        5 => ['id' => 5, 'achlove' => 500, 'gettype1' => 4, 'getvalue1' => 350, 'gettype2' => 10, 'getvalue2' => 500, 'gettype3' => 7, 'getvalue3' => 35000],
        6 => ['id' => 6, 'achlove' => 700, 'gettype1' => 4, 'getvalue1' => 400, 'gettype2' => 3, 'getvalue2' => 600, 'gettype3' => 7, 'getvalue3' => 40000],
        7 => ['id' => 7, 'achlove' => 1000, 'gettype1' => 4, 'getvalue1' => 500, 'gettype2' => 3, 'getvalue2' => 1000, 'gettype3' => 7, 'getvalue3' => 50000],
        8 => ['id' => 8, 'achlove' => 1800, 'gettype1' => 4, 'getvalue1' => 1000, 'gettype2' => 3, 'getvalue2' => 2000, 'gettype3' => 7, 'getvalue3' => 100000],
        9 => ['id' => 9, 'achlove' => 3000, 'gettype1' => 4, 'getvalue1' => 1500, 'gettype2' => 3, 'getvalue2' => 3000, 'gettype3' => 7, 'getvalue3' => 150000],
        10 => ['id' => 10, 'achlove' => 5000, 'gettype1' => 4, 'getvalue1' => 2500, 'gettype2' => 3, 'getvalue2' => 5000, 'gettype3' => 7, 'getvalue3' => 180000],
        11 => ['id' => 11, 'achlove' => 7000, 'gettype1' => 4, 'getvalue1' => 3000, 'gettype2' => 3, 'getvalue2' => 6000, 'gettype3' => 7, 'getvalue3' => 200000],
        12 => ['id' => 12, 'achlove' => 10000, 'gettype1' => 4, 'getvalue1' => 4000, 'gettype2' => 3, 'getvalue2' => 8000, 'gettype3' => 7, 'getvalue3' => 300000],
        13 => ['id' => 13, 'achlove' => 15000, 'gettype1' => 4, 'getvalue1' => 5000, 'gettype2' => 3, 'getvalue2' => 10000, 'gettype3' => 7, 'getvalue3' => 350000],
        14 => ['id' => 14, 'achlove' => 20000, 'gettype1' => 4, 'getvalue1' => 6000, 'gettype2' => 3, 'getvalue2' => 12000, 'gettype3' => 7, 'getvalue3' => 400000],
    ];
}
