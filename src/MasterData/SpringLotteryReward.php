<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 春节抽奖奖励表（旧版 zc_sanguo_spring_lottery_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class SpringLotteryReward
{
    public const string TABLE = 'zc_sanguo_spring_lottery_reward';

    /**
     * 按主键 id 索引，共 20 行。字段：
     *   - id (int) 活动排名ID
     *   - type (tinyint) 奖励类型
     *   - reward (int) 奖励声望数量
     *   - reward1 (int) 参数2
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => 7, 'reward' => 200000, 'reward1' => 0],
        2 => ['id' => 2, 'type' => 7, 'reward' => 150000, 'reward1' => 0],
        3 => ['id' => 3, 'type' => 7, 'reward' => 100000, 'reward1' => 0],
        4 => ['id' => 4, 'type' => 7, 'reward' => 90000, 'reward1' => 0],
        5 => ['id' => 5, 'type' => 7, 'reward' => 80000, 'reward1' => 0],
        6 => ['id' => 6, 'type' => 7, 'reward' => 70000, 'reward1' => 0],
        7 => ['id' => 7, 'type' => 7, 'reward' => 60000, 'reward1' => 0],
        8 => ['id' => 8, 'type' => 7, 'reward' => 50000, 'reward1' => 0],
        9 => ['id' => 9, 'type' => 7, 'reward' => 40000, 'reward1' => 0],
        10 => ['id' => 10, 'type' => 7, 'reward' => 30000, 'reward1' => 0],
        11 => ['id' => 11, 'type' => 7, 'reward' => 28000, 'reward1' => 0],
        12 => ['id' => 12, 'type' => 7, 'reward' => 26000, 'reward1' => 0],
        13 => ['id' => 13, 'type' => 7, 'reward' => 24000, 'reward1' => 0],
        14 => ['id' => 14, 'type' => 7, 'reward' => 22000, 'reward1' => 0],
        15 => ['id' => 15, 'type' => 7, 'reward' => 20000, 'reward1' => 0],
        16 => ['id' => 16, 'type' => 7, 'reward' => 18000, 'reward1' => 0],
        17 => ['id' => 17, 'type' => 7, 'reward' => 16000, 'reward1' => 0],
        18 => ['id' => 18, 'type' => 7, 'reward' => 14000, 'reward1' => 0],
        19 => ['id' => 19, 'type' => 7, 'reward' => 12000, 'reward1' => 0],
        20 => ['id' => 20, 'type' => 7, 'reward' => 10000, 'reward1' => 0],
    ];
}
