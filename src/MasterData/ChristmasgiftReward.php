<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 圣诞礼物数据表（旧版 zc_sanguo_christmasgift_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ChristmasgiftReward
{
    public const string TABLE = 'zc_sanguo_christmasgift_reward';

    /**
     * 按主键 id 索引，共 15 行。字段：
     *   - id (int) 礼包id
     *   - num (int) 限量数量
     *   - name (varchar) 礼包名称
     *   - gettype1 (tinyint) 礼包1类型
     *   - getvalue1 (int) 礼包1奖励参数
     *   - getnum1 (int) 装备精华数量
     *   - gettype2 (tinyint) 礼包2类型
     *   - getvalue2 (int) 礼包2奖励参数
     *   - getnum2 (int) 装备精华数量
     *   - gettype3 (tinyint) 礼包3类型
     *   - getvalue3 (int) 礼包3奖励参数
     *   - getnum3 (int) 装备精华数量
     *   - gettype4 (tinyint) 礼包4类型
     *   - getvalue4 (int) 礼包4奖励参数
     *   - getnum4 (int) 装备精华数量
     *   - price (int) 花费元宝
     *   - vip (tinyint) vip限制
     */
    public const array ROWS = [
        1 => ['id' => 1, 'num' => 0, 'name' => '感恩团购大礼包（回馈新老玩家）', 'gettype1' => 1, 'getvalue1' => 5000000, 'getnum1' => 0, 'gettype2' => 2, 'getvalue2' => 100, 'getnum2' => 0, 'gettype3' => 3, 'getvalue3' => 10000, 'getnum3' => 0, 'gettype4' => 4, 'getvalue4' => 100, 'getnum4' => 0, 'price' => 250, 'vip' => 0],
        2 => ['id' => 2, 'num' => 0, 'name' => '黄宝石团购大礼包', 'gettype1' => 1, 'getvalue1' => 5000000, 'getnum1' => 0, 'gettype2' => 2, 'getvalue2' => 300, 'getnum2' => 0, 'gettype3' => 3, 'getvalue3' => 10000, 'getnum3' => 0, 'gettype4' => 5, 'getvalue4' => 1000, 'getnum4' => 0, 'price' => 2000, 'vip' => 0],
        3 => ['id' => 3, 'num' => 0, 'name' => '紫宝石团购大礼包', 'gettype1' => 1, 'getvalue1' => 5000000, 'getnum1' => 0, 'gettype2' => 2, 'getvalue2' => 500, 'getnum2' => 0, 'gettype3' => 3, 'getvalue3' => 10000, 'getnum3' => 0, 'gettype4' => 4, 'getvalue4' => 500, 'getnum4' => 0, 'price' => 4000, 'vip' => 2],
        4 => ['id' => 4, 'num' => 0, 'name' => '原魂团购大礼包', 'gettype1' => 1, 'getvalue1' => 5000000, 'getnum1' => 0, 'gettype2' => 2, 'getvalue2' => 500, 'getnum2' => 0, 'gettype3' => 3, 'getvalue3' => 100000, 'getnum3' => 0, 'gettype4' => 4, 'getvalue4' => 100, 'getnum4' => 0, 'price' => 8000, 'vip' => 2],
        5 => ['id' => 5, 'num' => 0, 'name' => '突飞卡团购大礼包', 'gettype1' => 1, 'getvalue1' => 5000000, 'getnum1' => 0, 'gettype2' => 2, 'getvalue2' => 1200, 'getnum2' => 0, 'gettype3' => 3, 'getvalue3' => 10000, 'getnum3' => 0, 'gettype4' => 4, 'getvalue4' => 100, 'getnum4' => 0, 'price' => 8000, 'vip' => 4],
        6 => ['id' => 6, 'num' => 0, 'name' => '钻石团购大礼包', 'gettype1' => 1, 'getvalue1' => 10000000, 'getnum1' => 0, 'gettype2' => 2, 'getvalue2' => 1500, 'getnum2' => 0, 'gettype3' => 14, 'getvalue3' => 300, 'getnum3' => 0, 'gettype4' => 15, 'getvalue4' => 100, 'getnum4' => 0, 'price' => 18888, 'vip' => 4],
        7 => ['id' => 7, 'num' => 0, 'name' => '声望团购大礼包', 'gettype1' => 1, 'getvalue1' => 5000000, 'getnum1' => 0, 'gettype2' => 2, 'getvalue2' => 100, 'getnum2' => 0, 'gettype3' => 3, 'getvalue3' => 10000, 'getnum3' => 0, 'gettype4' => 8, 'getvalue4' => 200000, 'getnum4' => 0, 'price' => 20000, 'vip' => 4],
        8 => ['id' => 8, 'num' => 200, 'name' => '至尊坐骑大礼包（限售200）', 'gettype1' => 7, 'getvalue1' => 21, 'getnum1' => 1, 'gettype2' => 7, 'getvalue2' => 21, 'getnum2' => 1, 'gettype3' => 7, 'getvalue3' => 19, 'getnum3' => 1, 'gettype4' => 7, 'getvalue4' => 19, 'getnum4' => 1, 'price' => 40000, 'vip' => 4],
        9 => ['id' => 9, 'num' => 100, 'name' => '精炼团购大礼包（限售100）', 'gettype1' => 9, 'getvalue1' => 2000000, 'getnum1' => 0, 'gettype2' => 10, 'getvalue2' => 500000, 'getnum2' => 0, 'gettype3' => 11, 'getvalue3' => 50000, 'getnum3' => 0, 'gettype4' => 12, 'getvalue4' => 10000, 'getnum4' => 0, 'price' => 60000, 'vip' => 4],
        10 => ['id' => 10, 'num' => 100, 'name' => '至尊豪华大礼包（限售100）', 'gettype1' => 8, 'getvalue1' => 200000, 'getnum1' => 0, 'gettype2' => 3, 'getvalue2' => 200000, 'getnum2' => 0, 'gettype3' => 5, 'getvalue3' => 4000, 'getnum3' => 0, 'gettype4' => 4, 'getvalue4' => 4000, 'getnum4' => 0, 'price' => 80000, 'vip' => 5],
        11 => ['id' => 11, 'num' => 100, 'name' => '星魄团购大礼包（限售100）', 'gettype1' => 9, 'getvalue1' => 5000000, 'getnum1' => 0, 'gettype2' => 18, 'getvalue2' => 1, 'getnum2' => 50000, 'gettype3' => 18, 'getvalue3' => 2, 'getnum3' => 15000, 'gettype4' => 18, 'getvalue4' => 3, 'getnum4' => 10000, 'price' => 87500, 'vip' => 5],
        12 => ['id' => 12, 'num' => 100, 'name' => '风蹄坐骑大礼包（限售100）', 'gettype1' => 7, 'getvalue1' => 107, 'getnum1' => 1, 'gettype2' => 7, 'getvalue2' => 100, 'getnum2' => 1, 'gettype3' => 7, 'getvalue3' => 101, 'getnum3' => 30, 'gettype4' => 7, 'getvalue4' => 102, 'getnum4' => 30, 'price' => 98000, 'vip' => 5],
        13 => ['id' => 13, 'num' => 100, 'name' => '龙雀坐骑大礼包（限售100）', 'gettype1' => 7, 'getvalue1' => 105, 'getnum1' => 1, 'gettype2' => 7, 'getvalue2' => 100, 'getnum2' => 1, 'gettype3' => 7, 'getvalue3' => 101, 'getnum3' => 30, 'gettype4' => 7, 'getvalue4' => 102, 'getnum4' => 30, 'price' => 120000, 'vip' => 5],
        14 => ['id' => 14, 'num' => 100, 'name' => '杀神大礼包（限售100）', 'gettype1' => 4, 'getvalue1' => 5000, 'getnum1' => 0, 'gettype2' => 3, 'getvalue2' => 200000, 'getnum2' => 0, 'gettype3' => 5, 'getvalue3' => 3000, 'getnum3' => 0, 'gettype4' => 2, 'getvalue4' => 10000, 'getnum4' => 0, 'price' => 125000, 'vip' => 0],
        15 => ['id' => 15, 'num' => 100, 'name' => '超神大礼包(限售100）', 'gettype1' => 4, 'getvalue1' => 5000, 'getnum1' => 0, 'gettype2' => 5, 'getvalue2' => 5000, 'getnum2' => 0, 'gettype3' => 9, 'getvalue3' => 10000000, 'getnum3' => 0, 'gettype4' => 10, 'getvalue4' => 1000000, 'getnum4' => 0, 'price' => 150000, 'vip' => 0],
    ];
}
