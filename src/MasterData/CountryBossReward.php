<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 国家boss奖励数据表（旧版 zc_sanguo_country_boss_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class CountryBossReward
{
    public const string TABLE = 'zc_sanguo_country_boss_reward';

    /**
     * 按主键 rank 索引，共 50 行。字段：
     *   - rank (tinyint) 排名
     *   - win_item_id (smallint) 胜利碎片id
     *   - win_item_num (int) 胜利碎片个数
     *   - win_jade_type (tinyint) 胜利宝石类型
     *   - win_jade_num (int) 胜利宝石个数
     *   - lose_item_id (smallint) 失败碎片id
     *   - lose_item_num (tinyint) 失败碎片个数
     *   - lose_jade_type (tinyint) 失败宝石类型
     *   - lose_jade_num (int) 失败宝石个数
     */
    public const array ROWS = [
        1 => ['rank' => 1, 'win_item_id' => 57, 'win_item_num' => 2, 'win_jade_type' => 3, 'win_jade_num' => 40, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 20],
        2 => ['rank' => 2, 'win_item_id' => 60, 'win_item_num' => 2, 'win_jade_type' => 3, 'win_jade_num' => 35, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 15],
        3 => ['rank' => 3, 'win_item_id' => 63, 'win_item_num' => 2, 'win_jade_type' => 3, 'win_jade_num' => 30, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 10],
        4 => ['rank' => 4, 'win_item_id' => 60, 'win_item_num' => 1, 'win_jade_type' => 3, 'win_jade_num' => 25, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 10],
        5 => ['rank' => 5, 'win_item_id' => 63, 'win_item_num' => 1, 'win_jade_type' => 3, 'win_jade_num' => 15, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 10],
        6 => ['rank' => 6, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 10, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 9],
        7 => ['rank' => 7, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 9, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 9],
        8 => ['rank' => 8, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 8, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 8],
        9 => ['rank' => 9, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 8, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 8],
        10 => ['rank' => 10, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 8, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 8],
        11 => ['rank' => 11, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 7, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 7],
        12 => ['rank' => 12, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 7, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 7],
        13 => ['rank' => 13, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 7, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 7],
        14 => ['rank' => 14, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 7, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 7],
        15 => ['rank' => 15, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 7, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 7],
        16 => ['rank' => 16, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 7, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 7],
        17 => ['rank' => 17, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 7, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 7],
        18 => ['rank' => 18, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 7, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 7],
        19 => ['rank' => 19, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 7, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 7],
        20 => ['rank' => 20, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 7, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 7],
        21 => ['rank' => 21, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 6, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 6],
        22 => ['rank' => 22, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 6, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 6],
        23 => ['rank' => 23, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 6, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 6],
        24 => ['rank' => 24, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 6, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 6],
        25 => ['rank' => 25, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 6, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 6],
        26 => ['rank' => 26, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 6, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 6],
        27 => ['rank' => 27, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 6, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 6],
        28 => ['rank' => 28, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 6, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 6],
        29 => ['rank' => 29, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 6, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 6],
        30 => ['rank' => 30, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 6, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 6],
        31 => ['rank' => 31, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
        32 => ['rank' => 32, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
        33 => ['rank' => 33, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
        34 => ['rank' => 34, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
        35 => ['rank' => 35, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
        36 => ['rank' => 36, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
        37 => ['rank' => 37, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
        38 => ['rank' => 38, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
        39 => ['rank' => 39, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
        40 => ['rank' => 40, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
        41 => ['rank' => 41, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
        42 => ['rank' => 42, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
        43 => ['rank' => 43, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
        44 => ['rank' => 44, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
        45 => ['rank' => 45, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
        46 => ['rank' => 46, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
        47 => ['rank' => 47, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
        48 => ['rank' => 48, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
        49 => ['rank' => 49, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
        50 => ['rank' => 50, 'win_item_id' => 0, 'win_item_num' => 0, 'win_jade_type' => 3, 'win_jade_num' => 5, 'lose_item_id' => 0, 'lose_item_num' => 0, 'lose_jade_type' => 2, 'lose_jade_num' => 5],
    ];
}
