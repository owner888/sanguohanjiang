<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 兑奖券兑换奖励表（旧版 zc_sanguo_actkemari_exchange_lottery）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActkemariExchangeLottery
{
    public const string TABLE = 'zc_sanguo_actkemari_exchange_lottery';

    /**
     * 按主键 id 索引，共 36 行。字段：
     *   - id (int) 奖品ID
     *   - type (int) 奖品类型
     *   - value (int) 奖品数量
     *   - value1 (int) 装备精华数量
     *   - lottery_num (int) 消耗奖券
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => 2, 'value' => 2000000, 'value1' => 0, 'lottery_num' => 10],
        2 => ['id' => 2, 'type' => 11, 'value' => 150, 'value1' => 0, 'lottery_num' => 10],
        3 => ['id' => 3, 'type' => 3, 'value' => 75, 'value1' => 0, 'lottery_num' => 10],
        4 => ['id' => 4, 'type' => 28, 'value' => 23, 'value1' => 1, 'lottery_num' => 18],
        5 => ['id' => 5, 'type' => 28, 'value' => 43, 'value1' => 1, 'lottery_num' => 18],
        6 => ['id' => 6, 'type' => 28, 'value' => 20, 'value1' => 1, 'lottery_num' => 18],
        7 => ['id' => 7, 'type' => 28, 'value' => 13, 'value1' => 1, 'lottery_num' => 18],
        8 => ['id' => 8, 'type' => 28, 'value' => 30, 'value1' => 1, 'lottery_num' => 28],
        9 => ['id' => 9, 'type' => 28, 'value' => 31, 'value1' => 1, 'lottery_num' => 28],
        10 => ['id' => 10, 'type' => 28, 'value' => 27, 'value1' => 1, 'lottery_num' => 33],
        11 => ['id' => 11, 'type' => 28, 'value' => 26, 'value1' => 1, 'lottery_num' => 33],
        12 => ['id' => 12, 'type' => 28, 'value' => 39, 'value1' => 1, 'lottery_num' => 38],
        13 => ['id' => 13, 'type' => 28, 'value' => 35, 'value1' => 1, 'lottery_num' => 38],
        14 => ['id' => 14, 'type' => 4, 'value' => 100, 'value1' => 0, 'lottery_num' => 50],
        15 => ['id' => 15, 'type' => 10, 'value' => 150, 'value1' => 0, 'lottery_num' => 50],
        16 => ['id' => 16, 'type' => 5, 'value' => 30000, 'value1' => 0, 'lottery_num' => 50],
        17 => ['id' => 17, 'type' => 4, 'value' => 500, 'value1' => 0, 'lottery_num' => 200],
        18 => ['id' => 18, 'type' => 10, 'value' => 750, 'value1' => 0, 'lottery_num' => 200],
        19 => ['id' => 19, 'type' => 7, 'value' => 60000, 'value1' => 0, 'lottery_num' => 200],
        20 => ['id' => 20, 'type' => 4, 'value' => 1700, 'value1' => 0, 'lottery_num' => 600],
        21 => ['id' => 21, 'type' => 10, 'value' => 2500, 'value1' => 0, 'lottery_num' => 600],
        22 => ['id' => 22, 'type' => 7, 'value' => 200000, 'value1' => 0, 'lottery_num' => 600],
        23 => ['id' => 23, 'type' => 8, 'value' => 62, 'value1' => 0, 'lottery_num' => 700],
        24 => ['id' => 24, 'type' => 8, 'value' => 65, 'value1' => 0, 'lottery_num' => 700],
        25 => ['id' => 25, 'type' => 8, 'value' => 59, 'value1' => 0, 'lottery_num' => 1000],
        26 => ['id' => 26, 'type' => 8, 'value' => 68, 'value1' => 0, 'lottery_num' => 1000],
        27 => ['id' => 27, 'type' => 8, 'value' => 78, 'value1' => 0, 'lottery_num' => 1000],
        28 => ['id' => 28, 'type' => 8, 'value' => 79, 'value1' => 0, 'lottery_num' => 1000],
        29 => ['id' => 29, 'type' => 8, 'value' => 77, 'value1' => 0, 'lottery_num' => 1500],
        30 => ['id' => 30, 'type' => 8, 'value' => 80, 'value1' => 0, 'lottery_num' => 1500],
        31 => ['id' => 31, 'type' => 8, 'value' => 71, 'value1' => 0, 'lottery_num' => 2000],
        32 => ['id' => 32, 'type' => 8, 'value' => 74, 'value1' => 0, 'lottery_num' => 2500],
        33 => ['id' => 33, 'type' => 8, 'value' => 75, 'value1' => 0, 'lottery_num' => 2500],
        34 => ['id' => 34, 'type' => 8, 'value' => 72, 'value1' => 0, 'lottery_num' => 3500],
        35 => ['id' => 35, 'type' => 8, 'value' => 73, 'value1' => 0, 'lottery_num' => 3500],
        36 => ['id' => 36, 'type' => 8, 'value' => 76, 'value1' => 0, 'lottery_num' => 4000],
    ];
}
