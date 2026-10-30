<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 新服大礼包（旧版 zc_sanguo_newgift_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class NewgiftReward
{
    public const string TABLE = 'zc_sanguo_newgift_reward';

    /**
     * 按主键 id 索引，共 31 行。字段：
     *   - id (int) 领取天数
     *   - giftname (varchar) 礼包名称
     *   - gettype1 (int) 奖励类型1
     *   - getvalue1 (int) 参数1
     *   - gettype2 (int) 奖励类型2
     *   - getvalue2 (int) 参数2
     *   - gettype3 (int) 奖励类型3
     *   - getvalue3 (int) 参数3
     *   - gettype4 (int) 奖励类型4
     *   - getvalue4 (int) 参数4
     */
    public const array ROWS = [
        1 => ['id' => 1, 'giftname' => '第1天登陸', 'gettype1' => 1, 'getvalue1' => 1000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 100, 'gettype4' => 6, 'getvalue4' => 100],
        2 => ['id' => 2, 'giftname' => '第2天登陸', 'gettype1' => 1, 'getvalue1' => 2000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 200, 'gettype4' => 6, 'getvalue4' => 200],
        3 => ['id' => 3, 'giftname' => '第3天登陸', 'gettype1' => 1, 'getvalue1' => 3000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 300, 'gettype4' => 6, 'getvalue4' => 300],
        4 => ['id' => 4, 'giftname' => '第4天登陸', 'gettype1' => 1, 'getvalue1' => 4000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 400, 'gettype4' => 6, 'getvalue4' => 400],
        5 => ['id' => 5, 'giftname' => '第5天登陸', 'gettype1' => 1, 'getvalue1' => 5000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 500, 'gettype4' => 6, 'getvalue4' => 500],
        6 => ['id' => 6, 'giftname' => '第6天登陸', 'gettype1' => 1, 'getvalue1' => 6000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 600, 'gettype4' => 6, 'getvalue4' => 600],
        7 => ['id' => 7, 'giftname' => '第7天登陸', 'gettype1' => 1, 'getvalue1' => 7000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 700, 'gettype4' => 6, 'getvalue4' => 700],
        8 => ['id' => 8, 'giftname' => '第8天登陸', 'gettype1' => 1, 'getvalue1' => 8000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 800, 'gettype4' => 6, 'getvalue4' => 800],
        9 => ['id' => 9, 'giftname' => '第9天登陸', 'gettype1' => 1, 'getvalue1' => 9000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 900, 'gettype4' => 6, 'getvalue4' => 900],
        10 => ['id' => 10, 'giftname' => '第10天登陸', 'gettype1' => 1, 'getvalue1' => 10000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 1000, 'gettype4' => 6, 'getvalue4' => 1000],
        11 => ['id' => 11, 'giftname' => '第11天登陸', 'gettype1' => 1, 'getvalue1' => 11000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 1100, 'gettype4' => 6, 'getvalue4' => 1100],
        12 => ['id' => 12, 'giftname' => '第12天登陸', 'gettype1' => 1, 'getvalue1' => 12000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 1200, 'gettype4' => 6, 'getvalue4' => 1200],
        13 => ['id' => 13, 'giftname' => '第13天登陸', 'gettype1' => 1, 'getvalue1' => 13000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 1300, 'gettype4' => 6, 'getvalue4' => 1300],
        14 => ['id' => 14, 'giftname' => '第14天登陸', 'gettype1' => 1, 'getvalue1' => 14000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 1400, 'gettype4' => 6, 'getvalue4' => 1400],
        15 => ['id' => 15, 'giftname' => '第15天登陸', 'gettype1' => 1, 'getvalue1' => 15000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 15000, 'gettype4' => 6, 'getvalue4' => 15000],
        16 => ['id' => 16, 'giftname' => '第16天登陸', 'gettype1' => 1, 'getvalue1' => 16000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 1600, 'gettype4' => 6, 'getvalue4' => 1600],
        17 => ['id' => 17, 'giftname' => '第17天登陸', 'gettype1' => 1, 'getvalue1' => 17000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 1700, 'gettype4' => 6, 'getvalue4' => 1700],
        18 => ['id' => 18, 'giftname' => '第18天登陸', 'gettype1' => 1, 'getvalue1' => 18000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 1800, 'gettype4' => 6, 'getvalue4' => 1800],
        19 => ['id' => 19, 'giftname' => '第19天登陸', 'gettype1' => 1, 'getvalue1' => 19000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 1900, 'gettype4' => 6, 'getvalue4' => 1900],
        20 => ['id' => 20, 'giftname' => '第20天登陸', 'gettype1' => 1, 'getvalue1' => 20000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 2000, 'gettype4' => 6, 'getvalue4' => 2000],
        21 => ['id' => 21, 'giftname' => '第21天登陸', 'gettype1' => 1, 'getvalue1' => 21000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 2100, 'gettype4' => 6, 'getvalue4' => 2100],
        22 => ['id' => 22, 'giftname' => '第22天登陸', 'gettype1' => 1, 'getvalue1' => 22000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 2200, 'gettype4' => 6, 'getvalue4' => 2200],
        23 => ['id' => 23, 'giftname' => '第23天登陸', 'gettype1' => 1, 'getvalue1' => 23000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 2300, 'gettype4' => 6, 'getvalue4' => 2300],
        24 => ['id' => 24, 'giftname' => '第24天登陸', 'gettype1' => 1, 'getvalue1' => 24000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 2400, 'gettype4' => 6, 'getvalue4' => 2400],
        25 => ['id' => 25, 'giftname' => '第25天登陸', 'gettype1' => 1, 'getvalue1' => 25000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 2500, 'gettype4' => 6, 'getvalue4' => 2500],
        26 => ['id' => 26, 'giftname' => '第26天登陸', 'gettype1' => 1, 'getvalue1' => 26000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 2600, 'gettype4' => 6, 'getvalue4' => 2600],
        27 => ['id' => 27, 'giftname' => '第27天登陸', 'gettype1' => 1, 'getvalue1' => 27000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 2700, 'gettype4' => 6, 'getvalue4' => 2700],
        28 => ['id' => 28, 'giftname' => '第28天登陸', 'gettype1' => 1, 'getvalue1' => 28000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 2800, 'gettype4' => 6, 'getvalue4' => 2800],
        29 => ['id' => 29, 'giftname' => '第29天登陸', 'gettype1' => 1, 'getvalue1' => 23000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 2900, 'gettype4' => 6, 'getvalue4' => 2900],
        30 => ['id' => 30, 'giftname' => '第30天登陸', 'gettype1' => 1, 'getvalue1' => 30000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 30000, 'gettype4' => 6, 'getvalue4' => 30000],
        31 => ['id' => 31, 'giftname' => '第31天登陸', 'gettype1' => 1, 'getvalue1' => 30000000, 'gettype2' => 7, 'getvalue2' => 188888, 'gettype3' => 5, 'getvalue3' => 30000, 'gettype4' => 6, 'getvalue4' => 30000],
    ];
}
