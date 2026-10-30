<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * gvg_reward（旧版 zc_sanguo_gvg_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class GvgReward
{
    public const string TABLE = 'zc_sanguo_gvg_reward';

    /**
     * 按主键 id 索引，共 15 行。字段：
     *   - id (int)
     *   - name (varchar)
     *   - type1 (int) 参赛奖励类型1
     *   - number1 (int) 参赛奖励1
     *   - type2 (int) 参赛奖励类型2
     *   - number2 (int) 参赛奖励2
     *   - type3 (int) 参赛奖励类型3
     *   - number3 (int) 参赛奖励3
     *   - type4 (int) 服务器奖励类型
     *   - number4 (int) 服务器奖励
     *   - bet (int) 押注奖励（元宝）
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '1', 'type1' => 0, 'number1' => 5, 'type2' => 80000, 'number2' => 9, 'type3' => 21, 'number3' => 8, 'type4' => 100000, 'number4' => 40, 'bet' => 2],
        2 => ['id' => 2, 'name' => '2', 'type1' => 0, 'number1' => 5, 'type2' => 40000, 'number2' => 9, 'type3' => 22, 'number3' => 8, 'type4' => 80000, 'number4' => 30, 'bet' => 2],
        3 => ['id' => 3, 'name' => '3', 'type1' => 0, 'number1' => 10, 'type2' => 179, 'number2' => 9, 'type3' => 22, 'number3' => 8, 'type4' => 70000, 'number4' => 25, 'bet' => 2],
        4 => ['id' => 4, 'name' => '4', 'type1' => 0, 'number1' => 8, 'type2' => 60000, 'number2' => 6, 'type3' => 77, 'number3' => 6, 'type4' => 80, 'number4' => 20, 'bet' => 2],
        5 => ['id' => 5, 'name' => '5', 'type1' => 0, 'number1' => 10, 'type2' => 178, 'number2' => 6, 'type3' => 78, 'number3' => 6, 'type4' => 79, 'number4' => 15, 'bet' => 2],
        6 => ['id' => 6, 'name' => '6', 'type1' => 0, 'number1' => 5, 'type2' => 30000, 'number2' => 1, 'type3' => 2000, 'number3' => 3, 'type4' => 500, 'number4' => 10, 'bet' => 2],
        7 => ['id' => 7, 'name' => '7', 'type1' => 0, 'number1' => 5, 'type2' => 15000, 'number2' => 1, 'type3' => 1000, 'number3' => 3, 'type4' => 250, 'number4' => 10, 'bet' => 2],
        8 => ['id' => 8, 'name' => '8', 'type1' => 0, 'number1' => 10, 'type2' => 177, 'number2' => 1, 'type3' => 600, 'number3' => 3, 'type4' => 150, 'number4' => 5, 'bet' => 2],
        9 => ['id' => 9, 'name' => '9', 'type1' => 0, 'number1' => 5, 'type2' => 10000, 'number2' => 1, 'type3' => 400, 'number3' => 3, 'type4' => 100, 'number4' => 5, 'bet' => 2],
        10 => ['id' => 10, 'name' => '10', 'type1' => 0, 'number1' => 5, 'type2' => 5000, 'number2' => 1, 'type3' => 200, 'number3' => 3, 'type4' => 50, 'number4' => 5, 'bet' => 2],
        11 => ['id' => 11, 'name' => '11', 'type1' => 0, 'number1' => 5, 'type2' => 5000, 'number2' => 1, 'type3' => 200, 'number3' => 3, 'type4' => 50, 'number4' => 5, 'bet' => 2],
        12 => ['id' => 12, 'name' => '12', 'type1' => 0, 'number1' => 5, 'type2' => 5000, 'number2' => 1, 'type3' => 200, 'number3' => 3, 'type4' => 50, 'number4' => 5, 'bet' => 2],
        13 => ['id' => 13, 'name' => '13', 'type1' => 0, 'number1' => 5, 'type2' => 5000, 'number2' => 1, 'type3' => 200, 'number3' => 3, 'type4' => 50, 'number4' => 5, 'bet' => 2],
        14 => ['id' => 14, 'name' => '14', 'type1' => 0, 'number1' => 5, 'type2' => 5000, 'number2' => 1, 'type3' => 200, 'number3' => 3, 'type4' => 50, 'number4' => 5, 'bet' => 2],
        15 => ['id' => 15, 'name' => '15', 'type1' => 0, 'number1' => 5, 'type2' => 5000, 'number2' => 1, 'type3' => 200, 'number3' => 3, 'type4' => 50, 'number4' => 5, 'bet' => 2],
    ];
}
