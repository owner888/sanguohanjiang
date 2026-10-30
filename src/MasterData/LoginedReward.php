<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 持续登录奖励表（旧版 zc_sanguo_logined_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class LoginedReward
{
    public const string TABLE = 'zc_sanguo_logined_reward';

    /**
     * 按主键 id 索引，共 15 行。字段：
     *   - id (int) 登录天数
     *   - giftname (varchar) 礼包名称
     *   - gettype1 (int) 参数类型1
     *   - getvalue1 (int) 参数1
     *   - gettype2 (int) 参数类型2
     *   - getvalue2 (int) 参数2
     *   - gettype3 (int) 参数类型3
     *   - getvalue3 (int) 参数3
     *   - gettype4 (int) 参数类型4
     *   - getvalue4 (int) 参数4
     */
    public const array ROWS = [
        1 => ['id' => 1, 'giftname' => '连续登录1天', 'gettype1' => 1, 'getvalue1' => 100000, 'gettype2' => 3, 'getvalue2' => 50, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        2 => ['id' => 2, 'giftname' => '连续登录2天', 'gettype1' => 1, 'getvalue1' => 120000, 'gettype2' => 3, 'getvalue2' => 52, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        3 => ['id' => 3, 'giftname' => '连续登录3天', 'gettype1' => 1, 'getvalue1' => 140000, 'gettype2' => 3, 'getvalue2' => 54, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        4 => ['id' => 4, 'giftname' => '连续登录4天', 'gettype1' => 1, 'getvalue1' => 160000, 'gettype2' => 3, 'getvalue2' => 56, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        5 => ['id' => 5, 'giftname' => '连续登录5天', 'gettype1' => 1, 'getvalue1' => 180000, 'gettype2' => 3, 'getvalue2' => 58, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        6 => ['id' => 6, 'giftname' => '连续登录6天', 'gettype1' => 1, 'getvalue1' => 200000, 'gettype2' => 3, 'getvalue2' => 60, 'gettype3' => 2, 'getvalue3' => 30, 'gettype4' => 0, 'getvalue4' => 0],
        7 => ['id' => 7, 'giftname' => '连续登录7天', 'gettype1' => 1, 'getvalue1' => 220000, 'gettype2' => 3, 'getvalue2' => 62, 'gettype3' => 2, 'getvalue3' => 32, 'gettype4' => 0, 'getvalue4' => 0],
        8 => ['id' => 8, 'giftname' => '连续登录8天', 'gettype1' => 1, 'getvalue1' => 240000, 'gettype2' => 3, 'getvalue2' => 64, 'gettype3' => 2, 'getvalue3' => 34, 'gettype4' => 0, 'getvalue4' => 0],
        9 => ['id' => 9, 'giftname' => '连续登录9天', 'gettype1' => 1, 'getvalue1' => 260000, 'gettype2' => 3, 'getvalue2' => 66, 'gettype3' => 2, 'getvalue3' => 36, 'gettype4' => 0, 'getvalue4' => 0],
        10 => ['id' => 10, 'giftname' => '连续登录10天', 'gettype1' => 1, 'getvalue1' => 280000, 'gettype2' => 3, 'getvalue2' => 68, 'gettype3' => 2, 'getvalue3' => 38, 'gettype4' => 0, 'getvalue4' => 0],
        11 => ['id' => 11, 'giftname' => '连续登录11天', 'gettype1' => 1, 'getvalue1' => 300000, 'gettype2' => 3, 'getvalue2' => 64, 'gettype3' => 2, 'getvalue3' => 34, 'gettype4' => 4, 'getvalue4' => 30],
        12 => ['id' => 12, 'giftname' => '连续登录12天', 'gettype1' => 1, 'getvalue1' => 330000, 'gettype2' => 3, 'getvalue2' => 66, 'gettype3' => 2, 'getvalue3' => 36, 'gettype4' => 4, 'getvalue4' => 32],
        13 => ['id' => 13, 'giftname' => '连续登录13天', 'gettype1' => 1, 'getvalue1' => 360000, 'gettype2' => 3, 'getvalue2' => 68, 'gettype3' => 2, 'getvalue3' => 38, 'gettype4' => 4, 'getvalue4' => 34],
        14 => ['id' => 14, 'giftname' => '连续登录14天', 'gettype1' => 1, 'getvalue1' => 390000, 'gettype2' => 3, 'getvalue2' => 68, 'gettype3' => 2, 'getvalue3' => 38, 'gettype4' => 4, 'getvalue4' => 34],
        15 => ['id' => 15, 'giftname' => '连续登录15天', 'gettype1' => 1, 'getvalue1' => 450000, 'gettype2' => 3, 'getvalue2' => 68, 'gettype3' => 2, 'getvalue3' => 8, 'gettype4' => 5, 'getvalue4' => 30],
    ];
}
