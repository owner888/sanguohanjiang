<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 逐鹿名次奖励表（旧版 zc_sanguo_compete_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class CompeteReward
{
    public const string TABLE = 'zc_sanguo_compete_reward';

    /**
     * 按主键 id 索引，共 10 行。字段：
     *   - id (int) 排名ID
     *   - type1 (tinyint) 奖励类型1（1元宝 2银币 3紫宝石 4突飞卡 5原魂 6玉石 7装备--装备ID，默认只给1个 8坐骑--坐骑模板ID，默认只给1个）
     *   - value1 (int) 奖励参数1
     *   - type2 (tinyint) 奖励类型2
     *   - value2 (int) 奖励参数2
     *   - type3 (tinyint) 奖励类型3
     *   - value3 (int) 奖励参数3
     *   - type4 (tinyint) 奖励类型4
     *   - value4 (int) 奖励参数4
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type1' => 8, 'value1' => 21, 'type2' => 3, 'value2' => 1000, 'type3' => 5, 'value3' => 50000, 'type4' => 2, 'value4' => 50000000],
        2 => ['id' => 2, 'type1' => 8, 'value1' => 22, 'type2' => 3, 'value2' => 800, 'type3' => 5, 'value3' => 40000, 'type4' => 2, 'value4' => 40000000],
        3 => ['id' => 3, 'type1' => 8, 'value1' => 22, 'type2' => 3, 'value2' => 700, 'type3' => 5, 'value3' => 35000, 'type4' => 2, 'value4' => 35000000],
        4 => ['id' => 4, 'type1' => 8, 'value1' => 19, 'type2' => 3, 'value2' => 600, 'type3' => 5, 'value3' => 30000, 'type4' => 2, 'value4' => 30000000],
        5 => ['id' => 5, 'type1' => 8, 'value1' => 20, 'type2' => 3, 'value2' => 500, 'type3' => 5, 'value3' => 25000, 'type4' => 2, 'value4' => 25000000],
        6 => ['id' => 6, 'type1' => 8, 'value1' => 17, 'type2' => 3, 'value2' => 400, 'type3' => 5, 'value3' => 20000, 'type4' => 2, 'value4' => 20000000],
        7 => ['id' => 7, 'type1' => 8, 'value1' => 18, 'type2' => 3, 'value2' => 300, 'type3' => 5, 'value3' => 15000, 'type4' => 2, 'value4' => 15000000],
        8 => ['id' => 8, 'type1' => 8, 'value1' => 14, 'type2' => 3, 'value2' => 200, 'type3' => 5, 'value3' => 10000, 'type4' => 2, 'value4' => 10000000],
        9 => ['id' => 9, 'type1' => 8, 'value1' => 12, 'type2' => 3, 'value2' => 100, 'type3' => 5, 'value3' => 5000, 'type4' => 2, 'value4' => 5000000],
        10 => ['id' => 10, 'type1' => 8, 'value1' => 10, 'type2' => 3, 'value2' => 50, 'type3' => 5, 'value3' => 2500, 'type4' => 2, 'value4' => 2500000],
    ];
}
