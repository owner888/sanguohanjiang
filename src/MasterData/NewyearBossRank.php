<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 春节年兽活动排名奖励表（旧版 zc_sanguo_newyear_boss_rank）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class NewyearBossRank
{
    public const string TABLE = 'zc_sanguo_newyear_boss_rank';

    /**
     * 按主键 id 索引，共 13 行。字段：
     *   - id (int) id
     *   - rank (int) 排名区间
     *   - tab1 (tinyint) 伤害榜奖励类型（1元宝 2银币 3黄宝石 4紫宝石 5原魂 6水晶 7声望 8装备 9魔石碎片 10突飞 11坐骑 12蓝宝石 13武将）
     *   - value1 (int) 奖励参数
     *   - tab2 (tinyint) 鼓舞榜奖励类型（1元宝 2银币 3黄宝石 4紫宝石 5原魂 6水晶 7声望 8装备 9魔石碎片 10突飞 11坐骑 12蓝宝石 13武将）
     *   - value2 (int) 奖励参数
     *   - tab3 (tinyint) 闪避榜奖励类型（1元宝 2银币 3黄宝石 4紫宝石 5原魂 6水晶 7声望 8装备 9魔石碎片 10突飞 11坐骑 12蓝宝石 13武将）
     *   - value3 (int) 奖励参数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'rank' => 1, 'tab1' => 5, 'value1' => 24000, 'tab2' => 4, 'value2' => 300, 'tab3' => 9, 'value3' => 100000],
        2 => ['id' => 2, 'rank' => 2, 'tab1' => 5, 'value1' => 20000, 'tab2' => 4, 'value2' => 250, 'tab3' => 9, 'value3' => 90000],
        3 => ['id' => 3, 'rank' => 3, 'tab1' => 5, 'value1' => 16000, 'tab2' => 4, 'value2' => 200, 'tab3' => 9, 'value3' => 80000],
        4 => ['id' => 4, 'rank' => 4, 'tab1' => 5, 'value1' => 8000, 'tab2' => 4, 'value2' => 100, 'tab3' => 9, 'value3' => 70000],
        5 => ['id' => 5, 'rank' => 11, 'tab1' => 5, 'value1' => 6000, 'tab2' => 4, 'value2' => 75, 'tab3' => 9, 'value3' => 50000],
        6 => ['id' => 6, 'rank' => 21, 'tab1' => 5, 'value1' => 4000, 'tab2' => 4, 'value2' => 50, 'tab3' => 9, 'value3' => 30000],
        7 => ['id' => 7, 'rank' => 31, 'tab1' => 5, 'value1' => 2000, 'tab2' => 4, 'value2' => 25, 'tab3' => 9, 'value3' => 10000],
        8 => ['id' => 8, 'rank' => 51, 'tab1' => 5, 'value1' => 1000, 'tab2' => 3, 'value2' => 50, 'tab3' => 9, 'value3' => 5000],
        9 => ['id' => 9, 'rank' => 76, 'tab1' => 5, 'value1' => 800, 'tab2' => 3, 'value2' => 40, 'tab3' => 9, 'value3' => 2500],
        10 => ['id' => 10, 'rank' => 101, 'tab1' => 5, 'value1' => 600, 'tab2' => 3, 'value2' => 30, 'tab3' => 9, 'value3' => 1500],
        11 => ['id' => 11, 'rank' => 201, 'tab1' => 5, 'value1' => 400, 'tab2' => 3, 'value2' => 20, 'tab3' => 9, 'value3' => 1000],
        12 => ['id' => 12, 'rank' => 501, 'tab1' => 5, 'value1' => 300, 'tab2' => 3, 'value2' => 15, 'tab3' => 9, 'value3' => 500],
        13 => ['id' => 13, 'rank' => 1001, 'tab1' => 5, 'value1' => 200, 'tab2' => 3, 'value2' => 10, 'tab3' => 9, 'value3' => 250],
    ];
}
