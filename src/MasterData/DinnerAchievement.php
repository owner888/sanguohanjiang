<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 年夜饭成就表（旧版 zc_sanguo_dinner_achievement）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class DinnerAchievement
{
    public const string TABLE = 'zc_sanguo_dinner_achievement';

    /**
     * 按主键 id 索引，共 8 行。字段：
     *   - id (tinyint) 成就id
     *   - title (varchar) 成就名称
     *   - type (tinyint) 成就类型（1参与人数，2朱果数量，3人参数量，4熊掌数量，5同国人数）
     *   - value (int) 成就参数
     *   - get_type (tinyint) 1银币，2元宝，3蓝宝石，4金宝石，5紫宝石
     *   - get_value (mediumint) 成就奖励参数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'title' => '百人盛宴', 'type' => 1, 'value' => 100, 'get_type' => 1, 'get_value' => 100000],
        2 => ['id' => 2, 'title' => '百人盛宴', 'type' => 1, 'value' => 200, 'get_type' => 1, 'get_value' => 200000],
        3 => ['id' => 3, 'title' => '百人盛宴', 'type' => 1, 'value' => 300, 'get_type' => 1, 'get_value' => 300000],
        4 => ['id' => 4, 'title' => '百人盛宴', 'type' => 1, 'value' => 500, 'get_type' => 1, 'get_value' => 500000],
        5 => ['id' => 5, 'title' => '天朝上邦', 'type' => 5, 'value' => 30, 'get_type' => 2, 'get_value' => 30],
        6 => ['id' => 6, 'title' => '丰盛佳肴', 'type' => 4, 'value' => 30, 'get_type' => 3, 'get_value' => 100],
        7 => ['id' => 7, 'title' => '丰盛佳肴', 'type' => 3, 'value' => 20, 'get_type' => 4, 'get_value' => 30],
        8 => ['id' => 8, 'title' => '丰盛佳肴', 'type' => 2, 'value' => 10, 'get_type' => 5, 'get_value' => 10],
    ];
}
