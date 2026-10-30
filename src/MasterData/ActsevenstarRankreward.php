<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 七星灯排名奖励表（旧版 zc_sanguo_actsevenstar_rankreward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActsevenstarRankreward
{
    public const string TABLE = 'zc_sanguo_actsevenstar_rankreward';

    /**
     * 按主键 rank 索引，共 20 行。字段：
     *   - rank (int) 自增ID
     *   - type1 (int) 奖励类型1
     *   - value1 (int) 奖励值1
     *   - num1 (int) 奖励数量1
     *   - type2 (int) 奖励类型2
     *   - value2 (int) 奖励值2
     *   - num2 (int) 奖励数量2
     */
    public const array ROWS = [
        1 => ['rank' => 1, 'type1' => 28, 'value1' => 0, 'num1' => 120, 'type2' => 32, 'value2' => 10, 'num2' => 3],
        2 => ['rank' => 2, 'type1' => 28, 'value1' => 0, 'num1' => 100, 'type2' => 26, 'value2' => 102, 'num2' => 30],
        3 => ['rank' => 3, 'type1' => 28, 'value1' => 0, 'num1' => 80, 'type2' => 26, 'value2' => 101, 'num2' => 30],
        4 => ['rank' => 4, 'type1' => 27, 'value1' => 0, 'num1' => 220, 'type2' => 10, 'value2' => 0, 'num2' => 800],
        5 => ['rank' => 5, 'type1' => 27, 'value1' => 0, 'num1' => 190, 'type2' => 10, 'value2' => 0, 'num2' => 700],
        6 => ['rank' => 6, 'type1' => 27, 'value1' => 0, 'num1' => 160, 'type2' => 10, 'value2' => 0, 'num2' => 600],
        7 => ['rank' => 7, 'type1' => 27, 'value1' => 0, 'num1' => 130, 'type2' => 10, 'value2' => 0, 'num2' => 500],
        8 => ['rank' => 8, 'type1' => 27, 'value1' => 0, 'num1' => 120, 'type2' => 10, 'value2' => 0, 'num2' => 400],
        9 => ['rank' => 9, 'type1' => 27, 'value1' => 0, 'num1' => 110, 'type2' => 10, 'value2' => 0, 'num2' => 300],
        10 => ['rank' => 10, 'type1' => 27, 'value1' => 0, 'num1' => 100, 'type2' => 10, 'value2' => 0, 'num2' => 200],
        11 => ['rank' => 11, 'type1' => 27, 'value1' => 0, 'num1' => 90, 'type2' => 9, 'value2' => 0, 'num2' => 150000],
        12 => ['rank' => 12, 'type1' => 27, 'value1' => 0, 'num1' => 80, 'type2' => 9, 'value2' => 0, 'num2' => 120000],
        13 => ['rank' => 13, 'type1' => 27, 'value1' => 0, 'num1' => 70, 'type2' => 9, 'value2' => 0, 'num2' => 100000],
        14 => ['rank' => 14, 'type1' => 27, 'value1' => 0, 'num1' => 60, 'type2' => 9, 'value2' => 0, 'num2' => 90000],
        15 => ['rank' => 15, 'type1' => 27, 'value1' => 0, 'num1' => 50, 'type2' => 9, 'value2' => 0, 'num2' => 80000],
        16 => ['rank' => 16, 'type1' => 27, 'value1' => 0, 'num1' => 40, 'type2' => 9, 'value2' => 0, 'num2' => 70000],
        17 => ['rank' => 17, 'type1' => 27, 'value1' => 0, 'num1' => 30, 'type2' => 9, 'value2' => 0, 'num2' => 60000],
        18 => ['rank' => 18, 'type1' => 27, 'value1' => 0, 'num1' => 20, 'type2' => 9, 'value2' => 0, 'num2' => 50000],
        19 => ['rank' => 19, 'type1' => 27, 'value1' => 0, 'num1' => 10, 'type2' => 9, 'value2' => 0, 'num2' => 40000],
        20 => ['rank' => 20, 'type1' => 27, 'value1' => 0, 'num1' => 5, 'type2' => 9, 'value2' => 0, 'num2' => 30000],
    ];
}
