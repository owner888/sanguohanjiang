<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 等级奖励表（旧版 zc_sanguo_levelgift_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class LevelgiftReward
{
    public const string TABLE = 'zc_sanguo_levelgift_reward';

    /**
     * 按主键 level 索引，共 17 行。字段：
     *   - level (int) 领取等级
     *   - giftname (varchar) 礼包名称
     *   - gettype1 (int) 奖励类型1
     *   - getvalue1 (int) 参数1
     *   - getvalue12 (int) 参数2
     *   - gettype2 (int) 奖励类型2
     *   - getvalue2 (int) 参数1
     *   - getvalue22 (int) 参数2
     *   - gettype3 (int) 奖励类型3
     *   - getvalue3 (int) 参数1
     *   - getvalue32 (int) 参数2
     *   - gettype4 (int) 奖励类型4
     *   - getvalue4 (int) 参数1
     *   - getvalue42 (int) 参数2
     */
    public const array ROWS = [
        16 => ['level' => 16, 'giftname' => '主公等级16级', 'gettype1' => 8, 'getvalue1' => 50000, 'getvalue12' => 0, 'gettype2' => 1, 'getvalue2' => 300000, 'getvalue22' => 0, 'gettype3' => 2, 'getvalue3' => 50, 'getvalue32' => 0, 'gettype4' => 0, 'getvalue4' => 0, 'getvalue42' => 0],
        30 => ['level' => 30, 'giftname' => '主公等级30级', 'gettype1' => 10, 'getvalue1' => 13, 'getvalue12' => 0, 'gettype2' => 3, 'getvalue2' => 38, 'getvalue22' => 0, 'gettype3' => 3, 'getvalue3' => 39, 'getvalue32' => 0, 'gettype4' => 3, 'getvalue4' => 40, 'getvalue42' => 0],
        35 => ['level' => 35, 'giftname' => '主公等级35级', 'gettype1' => 8, 'getvalue1' => 88888, 'getvalue12' => 0, 'gettype2' => 1, 'getvalue2' => 500000, 'getvalue22' => 0, 'gettype3' => 2, 'getvalue3' => 100, 'getvalue32' => 0, 'gettype4' => 4, 'getvalue4' => 30, 'getvalue42' => 0],
        40 => ['level' => 40, 'giftname' => '主公等级40级', 'gettype1' => 8, 'getvalue1' => 50000, 'getvalue12' => 0, 'gettype2' => 1, 'getvalue2' => 600000, 'getvalue22' => 0, 'gettype3' => 2, 'getvalue3' => 200, 'getvalue32' => 0, 'gettype4' => 4, 'getvalue4' => 40, 'getvalue42' => 0],
        45 => ['level' => 45, 'giftname' => '主公等级45级', 'gettype1' => 8, 'getvalue1' => 50000, 'getvalue12' => 0, 'gettype2' => 1, 'getvalue2' => 700000, 'getvalue22' => 0, 'gettype3' => 2, 'getvalue3' => 250, 'getvalue32' => 0, 'gettype4' => 4, 'getvalue4' => 45, 'getvalue42' => 0],
        50 => ['level' => 50, 'giftname' => '主公等级50级', 'gettype1' => 8, 'getvalue1' => 50000, 'getvalue12' => 0, 'gettype2' => 1, 'getvalue2' => 800000, 'getvalue22' => 0, 'gettype3' => 2, 'getvalue3' => 300, 'getvalue32' => 0, 'gettype4' => 4, 'getvalue4' => 50, 'getvalue42' => 0],
        55 => ['level' => 55, 'giftname' => '主公等级55级', 'gettype1' => 9, 'getvalue1' => 3, 'getvalue12' => 6, 'gettype2' => 9, 'getvalue2' => 8, 'getvalue22' => 6, 'gettype3' => 9, 'getvalue3' => 13, 'getvalue32' => 6, 'gettype4' => 9, 'getvalue4' => 18, 'getvalue42' => 6],
        60 => ['level' => 60, 'giftname' => '主公等级60级', 'gettype1' => 3, 'getvalue1' => 13, 'getvalue12' => 0, 'gettype2' => 1, 'getvalue2' => 900000, 'getvalue22' => 0, 'gettype3' => 0, 'getvalue3' => 0, 'getvalue32' => 0, 'gettype4' => 0, 'getvalue4' => 0, 'getvalue42' => 0],
        65 => ['level' => 65, 'giftname' => '主公等级65级', 'gettype1' => 8, 'getvalue1' => 188888, 'getvalue12' => 0, 'gettype2' => 1, 'getvalue2' => 1000000, 'getvalue22' => 0, 'gettype3' => 2, 'getvalue3' => 400, 'getvalue32' => 0, 'gettype4' => 4, 'getvalue4' => 60, 'getvalue42' => 0],
        70 => ['level' => 70, 'giftname' => '主公等级70级', 'gettype1' => 3, 'getvalue1' => 49, 'getvalue12' => 0, 'gettype2' => 1, 'getvalue2' => 11000000, 'getvalue22' => 0, 'gettype3' => 0, 'getvalue3' => 0, 'getvalue32' => 0, 'gettype4' => 0, 'getvalue4' => 0, 'getvalue42' => 0],
        80 => ['level' => 80, 'giftname' => '主公等级80级', 'gettype1' => 8, 'getvalue1' => 50000, 'getvalue12' => 0, 'gettype2' => 1, 'getvalue2' => 12000000, 'getvalue22' => 0, 'gettype3' => 2, 'getvalue3' => 450, 'getvalue32' => 0, 'gettype4' => 4, 'getvalue4' => 65, 'getvalue42' => 0],
        90 => ['level' => 90, 'giftname' => '主公等级90级', 'gettype1' => 8, 'getvalue1' => 288888, 'getvalue12' => 0, 'gettype2' => 1, 'getvalue2' => 13000000, 'getvalue22' => 0, 'gettype3' => 7, 'getvalue3' => 500000, 'getvalue32' => 0, 'gettype4' => 5, 'getvalue4' => 500, 'getvalue42' => 0],
        120 => ['level' => 120, 'giftname' => '主公等级120级', 'gettype1' => 8, 'getvalue1' => 388888, 'getvalue12' => 0, 'gettype2' => 1, 'getvalue2' => 15000000, 'getvalue22' => 0, 'gettype3' => 7, 'getvalue3' => 600000, 'getvalue32' => 0, 'gettype4' => 5, 'getvalue4' => 600, 'getvalue42' => 0],
        150 => ['level' => 150, 'giftname' => '主公等级150级', 'gettype1' => 8, 'getvalue1' => 488888, 'getvalue12' => 0, 'gettype2' => 1, 'getvalue2' => 15000000, 'getvalue22' => 0, 'gettype3' => 7, 'getvalue3' => 600000, 'getvalue32' => 0, 'gettype4' => 5, 'getvalue4' => 600, 'getvalue42' => 0],
        180 => ['level' => 180, 'giftname' => '主公等级180级', 'gettype1' => 8, 'getvalue1' => 588888, 'getvalue12' => 0, 'gettype2' => 1, 'getvalue2' => 15000000, 'getvalue22' => 0, 'gettype3' => 7, 'getvalue3' => 600000, 'getvalue32' => 0, 'gettype4' => 5, 'getvalue4' => 600, 'getvalue42' => 0],
        220 => ['level' => 220, 'giftname' => '主公等级220级', 'gettype1' => 8, 'getvalue1' => 688888, 'getvalue12' => 0, 'gettype2' => 1, 'getvalue2' => 15000000, 'getvalue22' => 0, 'gettype3' => 7, 'getvalue3' => 600000, 'getvalue32' => 0, 'gettype4' => 5, 'getvalue4' => 600, 'getvalue42' => 0],
        239 => ['level' => 239, 'giftname' => '主公等级237级', 'gettype1' => 8, 'getvalue1' => 888888, 'getvalue12' => 0, 'gettype2' => 1, 'getvalue2' => 15000000, 'getvalue22' => 0, 'gettype3' => 7, 'getvalue3' => 600000, 'getvalue32' => 0, 'gettype4' => 5, 'getvalue4' => 600, 'getvalue42' => 0],
    ];
}
