<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 抽奖信息（旧版 zc_sanguo_lottery）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Lottery
{
    public const string TABLE = 'zc_sanguo_lottery';

    /**
     * 按主键 id 索引，共 32 行。字段：
     *   - id (tinyint) 奖励id
     *   - gettype (tinyint) 奖励类型（1 银币、2 金币、3 军令、4 将领）
     *   - getvalue (int) 奖励物品id或数量
     *   - getrate (float) 获得物品概率
     *   - group (tinyint) 奖励分组
     */
    public const array ROWS = [
        1 => ['id' => 1, 'gettype' => 6, 'getvalue' => 1, 'getrate' => 30.0, 'group' => 1],
        2 => ['id' => 2, 'gettype' => 6, 'getvalue' => 2, 'getrate' => 30.0, 'group' => 1],
        3 => ['id' => 3, 'gettype' => 6, 'getvalue' => 3, 'getrate' => 25.0, 'group' => 1],
        4 => ['id' => 4, 'gettype' => 6, 'getvalue' => 5, 'getrate' => 10.0, 'group' => 1],
        5 => ['id' => 5, 'gettype' => 6, 'getvalue' => 10, 'getrate' => 4.0, 'group' => 1],
        6 => ['id' => 6, 'gettype' => 6, 'getvalue' => 50, 'getrate' => 0.4, 'group' => 1],
        7 => ['id' => 7, 'gettype' => 6, 'getvalue' => 100, 'getrate' => 0.2, 'group' => 1],
        8 => ['id' => 8, 'gettype' => 7, 'getvalue' => 56, 'getrate' => 0.1, 'group' => 1],
        9 => ['id' => 9, 'gettype' => 1, 'getvalue' => 300, 'getrate' => 30.0, 'group' => 2],
        10 => ['id' => 10, 'gettype' => 1, 'getvalue' => 600, 'getrate' => 30.0, 'group' => 2],
        11 => ['id' => 11, 'gettype' => 1, 'getvalue' => 900, 'getrate' => 20.0, 'group' => 2],
        12 => ['id' => 12, 'gettype' => 1, 'getvalue' => 1200, 'getrate' => 10.0, 'group' => 2],
        13 => ['id' => 13, 'gettype' => 1, 'getvalue' => 1500, 'getrate' => 5.0, 'group' => 2],
        14 => ['id' => 14, 'gettype' => 1, 'getvalue' => 1800, 'getrate' => 3.0, 'group' => 2],
        15 => ['id' => 15, 'gettype' => 1, 'getvalue' => 2100, 'getrate' => 2.0, 'group' => 2],
        16 => ['id' => 16, 'gettype' => 7, 'getvalue' => 54, 'getrate' => 0.1, 'group' => 2],
        17 => ['id' => 17, 'gettype' => 2, 'getvalue' => 3, 'getrate' => 30.0, 'group' => 3],
        18 => ['id' => 18, 'gettype' => 2, 'getvalue' => 4, 'getrate' => 30.0, 'group' => 3],
        19 => ['id' => 19, 'gettype' => 2, 'getvalue' => 5, 'getrate' => 20.0, 'group' => 3],
        20 => ['id' => 20, 'gettype' => 2, 'getvalue' => 8, 'getrate' => 10.0, 'group' => 3],
        21 => ['id' => 21, 'gettype' => 2, 'getvalue' => 10, 'getrate' => 5.0, 'group' => 3],
        22 => ['id' => 22, 'gettype' => 2, 'getvalue' => 50, 'getrate' => 2.0, 'group' => 3],
        23 => ['id' => 23, 'gettype' => 2, 'getvalue' => 100, 'getrate' => 1.0, 'group' => 3],
        24 => ['id' => 24, 'gettype' => 2, 'getvalue' => 500, 'getrate' => 0.2, 'group' => 3],
        25 => ['id' => 25, 'gettype' => 8, 'getvalue' => 3, 'getrate' => 30.0, 'group' => 4],
        26 => ['id' => 26, 'gettype' => 8, 'getvalue' => 4, 'getrate' => 30.0, 'group' => 4],
        27 => ['id' => 27, 'gettype' => 8, 'getvalue' => 5, 'getrate' => 20.0, 'group' => 4],
        28 => ['id' => 28, 'gettype' => 8, 'getvalue' => 8, 'getrate' => 10.0, 'group' => 4],
        29 => ['id' => 29, 'gettype' => 8, 'getvalue' => 10, 'getrate' => 5.0, 'group' => 4],
        30 => ['id' => 30, 'gettype' => 8, 'getvalue' => 50, 'getrate' => 2.0, 'group' => 4],
        31 => ['id' => 31, 'gettype' => 8, 'getvalue' => 100, 'getrate' => 1.0, 'group' => 4],
        32 => ['id' => 32, 'gettype' => 8, 'getvalue' => 500, 'getrate' => 0.2, 'group' => 4],
    ];
}
