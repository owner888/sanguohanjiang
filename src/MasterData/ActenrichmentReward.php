<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 貔貅聚财奖励表（旧版 zc_sanguo_actenrichment_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActenrichmentReward
{
    public const string TABLE = 'zc_sanguo_actenrichment_reward';

    /**
     * 按主键 id 索引，共 3 行。字段：
     *   - id (int) 自增ID
     *   - gold (int) 充值额度
     *   - day1_type (int) 第一天奖励类型
     *   - day1_value (int) 第一天奖励数量
     *   - day2_type (int) 第二天奖励类型
     *   - day2_value (int) 第二天奖励数量
     *   - day3_type (int) 第三天奖励类型
     *   - day3_value (int) 第三天奖励数量
     *   - day4_type (int) 第四天奖励类型
     *   - day4_value (int) 第四天奖励数量
     *   - day5_type (int) 第五天奖励类型
     *   - day5_value (int) 第五天奖励数量
     *   - day6_type (int) 第六天奖励类型
     *   - day6_value (int) 第六天奖励数量
     *   - day7_type (int) 第七天奖励类型
     *   - day7_value (int) 第七天奖励数量或者id
     *   - day7_value1 (int) 第七天奖励数量或者id
     */
    public const array ROWS = [
        1 => ['id' => 1, 'gold' => 10000, 'day1_type' => 1, 'day1_value' => 50, 'day2_type' => 1, 'day2_value' => 100, 'day3_type' => 1, 'day3_value' => 150, 'day4_type' => 1, 'day4_value' => 200, 'day5_type' => 1, 'day5_value' => 250, 'day6_type' => 1, 'day6_value' => 250, 'day7_type' => 2, 'day7_value' => 4, 'day7_value1' => 11],
        2 => ['id' => 2, 'gold' => 32800, 'day1_type' => 1, 'day1_value' => 240, 'day2_type' => 1, 'day2_value' => 340, 'day3_type' => 1, 'day3_value' => 450, 'day4_type' => 1, 'day4_value' => 500, 'day5_type' => 1, 'day5_value' => 750, 'day6_type' => 1, 'day6_value' => 1000, 'day7_type' => 2, 'day7_value' => 5, 'day7_value1' => 13],
        3 => ['id' => 3, 'gold' => 64800, 'day1_type' => 1, 'day1_value' => 740, 'day2_type' => 1, 'day2_value' => 840, 'day3_type' => 1, 'day3_value' => 950, 'day4_type' => 1, 'day4_value' => 1000, 'day5_type' => 1, 'day5_value' => 1250, 'day6_type' => 1, 'day6_value' => 1700, 'day7_type' => 2, 'day7_value' => 65, 'day7_value1' => 1],
    ];
}
