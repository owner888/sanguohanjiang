<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * japan_reward（旧版 zc_sanguo_japan_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class JapanReward
{
    public const string TABLE = 'zc_sanguo_japan_reward';

    /**
     * 按主键 reward_id 索引，共 32 行。字段：
     *   - reward_id (int) 奖品ID
     *   - reward_type (int) 奖品类型
     *   - param1 (bigint) 参数1
     *   - param2 (bigint) 参数2
     *   - ex_credit (bigint) 消耗战功
     *   - buy_type (int) 可兑换类型
     *   - buy_time (int) 可兑换次数
     */
    public const array ROWS = [
        1 => ['reward_id' => 1, 'reward_type' => 1, 'param1' => 10, 'param2' => 0, 'ex_credit' => 100, 'buy_type' => 1, 'buy_time' => 20],
        2 => ['reward_id' => 2, 'reward_type' => 2, 'param1' => 10, 'param2' => 0, 'ex_credit' => 100, 'buy_type' => 1, 'buy_time' => 20],
        3 => ['reward_id' => 3, 'reward_type' => 3, 'param1' => 10, 'param2' => 0, 'ex_credit' => 100, 'buy_type' => 1, 'buy_time' => 20],
        4 => ['reward_id' => 4, 'reward_type' => 4, 'param1' => 10, 'param2' => 0, 'ex_credit' => 100, 'buy_type' => 1, 'buy_time' => 20],
        5 => ['reward_id' => 5, 'reward_type' => 5, 'param1' => 10, 'param2' => 0, 'ex_credit' => 100, 'buy_type' => 1, 'buy_time' => 20],
        6 => ['reward_id' => 6, 'reward_type' => 6, 'param1' => 10, 'param2' => 0, 'ex_credit' => 100, 'buy_type' => 1, 'buy_time' => 20],
        7 => ['reward_id' => 7, 'reward_type' => 7, 'param1' => 10, 'param2' => 0, 'ex_credit' => 200, 'buy_type' => 1, 'buy_time' => 20],
        8 => ['reward_id' => 8, 'reward_type' => 8, 'param1' => 78, 'param2' => 0, 'ex_credit' => 2000, 'buy_type' => 1, 'buy_time' => 5],
        9 => ['reward_id' => 9, 'reward_type' => 9, 'param1' => 10, 'param2' => 0, 'ex_credit' => 2000, 'buy_type' => 1, 'buy_time' => 5],
        10 => ['reward_id' => 10, 'reward_type' => 10, 'param1' => 10, 'param2' => 0, 'ex_credit' => 2000, 'buy_type' => 1, 'buy_time' => 5],
        11 => ['reward_id' => 11, 'reward_type' => 11, 'param1' => 10, 'param2' => 0, 'ex_credit' => 2000, 'buy_type' => 1, 'buy_time' => 5],
        12 => ['reward_id' => 12, 'reward_type' => 12, 'param1' => 167, 'param2' => 0, 'ex_credit' => 2000, 'buy_type' => 1, 'buy_time' => 5],
        13 => ['reward_id' => 13, 'reward_type' => 13, 'param1' => 10, 'param2' => 0, 'ex_credit' => 2000, 'buy_type' => 1, 'buy_time' => 5],
        14 => ['reward_id' => 14, 'reward_type' => 14, 'param1' => 10, 'param2' => 0, 'ex_credit' => 2000, 'buy_type' => 1, 'buy_time' => 5],
        15 => ['reward_id' => 15, 'reward_type' => 15, 'param1' => 10, 'param2' => 0, 'ex_credit' => 2000, 'buy_type' => 1, 'buy_time' => 5],
        16 => ['reward_id' => 16, 'reward_type' => 16, 'param1' => 10, 'param2' => 0, 'ex_credit' => 2000, 'buy_type' => 1, 'buy_time' => 5],
        17 => ['reward_id' => 17, 'reward_type' => 17, 'param1' => 1, 'param2' => 10, 'ex_credit' => 2000, 'buy_type' => 1, 'buy_time' => 5],
        18 => ['reward_id' => 18, 'reward_type' => 17, 'param1' => 2, 'param2' => 10, 'ex_credit' => 400, 'buy_type' => 2, 'buy_time' => 1],
        19 => ['reward_id' => 19, 'reward_type' => 17, 'param1' => 3, 'param2' => 10, 'ex_credit' => 400, 'buy_type' => 2, 'buy_time' => 1],
        20 => ['reward_id' => 20, 'reward_type' => 17, 'param1' => 4, 'param2' => 10, 'ex_credit' => 600, 'buy_type' => 2, 'buy_time' => 1],
        21 => ['reward_id' => 21, 'reward_type' => 17, 'param1' => 5, 'param2' => 10, 'ex_credit' => 600, 'buy_type' => 2, 'buy_time' => 1],
        22 => ['reward_id' => 22, 'reward_type' => 17, 'param1' => 6, 'param2' => 10, 'ex_credit' => 1200, 'buy_type' => 2, 'buy_time' => 1],
        23 => ['reward_id' => 23, 'reward_type' => 17, 'param1' => 7, 'param2' => 10, 'ex_credit' => 1200, 'buy_type' => 2, 'buy_time' => 1],
        24 => ['reward_id' => 24, 'reward_type' => 17, 'param1' => 8, 'param2' => 10, 'ex_credit' => 1200, 'buy_type' => 2, 'buy_time' => 1],
        25 => ['reward_id' => 25, 'reward_type' => 17, 'param1' => 9, 'param2' => 10, 'ex_credit' => 1200, 'buy_type' => 2, 'buy_time' => 1],
        26 => ['reward_id' => 26, 'reward_type' => 26, 'param1' => 106, 'param2' => 1, 'ex_credit' => 1200, 'buy_type' => 2, 'buy_time' => 1],
        27 => ['reward_id' => 27, 'reward_type' => 27, 'param1' => 10, 'param2' => 1, 'ex_credit' => 1200, 'buy_type' => 2, 'buy_time' => 1],
        28 => ['reward_id' => 28, 'reward_type' => 28, 'param1' => 10, 'param2' => 0, 'ex_credit' => 2000, 'buy_type' => 2, 'buy_time' => 1],
        29 => ['reward_id' => 29, 'reward_type' => 29, 'param1' => 43, 'param2' => 10, 'ex_credit' => 2000, 'buy_type' => 2, 'buy_time' => 1],
        30 => ['reward_id' => 30, 'reward_type' => 30, 'param1' => 10, 'param2' => 0, 'ex_credit' => 4000, 'buy_type' => 2, 'buy_time' => 1],
        31 => ['reward_id' => 31, 'reward_type' => 31, 'param1' => 10, 'param2' => 0, 'ex_credit' => 4000, 'buy_type' => 2, 'buy_time' => 1],
        32 => ['reward_id' => 32, 'reward_type' => 32, 'param1' => 10, 'param2' => 0, 'ex_credit' => 4000, 'buy_type' => 2, 'buy_time' => 1],
    ];
}
