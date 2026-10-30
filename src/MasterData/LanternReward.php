<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * lantern_reward（旧版 zc_sanguo_lantern_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class LanternReward
{
    public const string TABLE = 'zc_sanguo_lantern_reward';

    /**
     * 按主键 lid 索引，共 4 行。字段：
     *   - lid (int)
     *   - lname (varchar) 元宵名称
     *   - id_1 (int) 所需材料ID
     *   - name_1 (varchar) 材料1名称
     *   - price_1 (int) 材料1价格
     *   - t1 (int) 数量
     *   - id_2 (int) 所需材料ID
     *   - name_2 (varchar) 材料2名称
     *   - price_2 (int) 材料2价格
     *   - t2 (int) 数量
     *   - id_3 (int) 所需材料ID
     *   - name_3 (varchar) 材料3名称
     *   - price_3 (int) 材料3价格
     *   - t3 (int) 数量
     *   - reward1 (int) 奖励类型1
     *   - num1 (int) 数量1
     *   - reward2 (int) 奖励类型2
     *   - num2 (int) 数量2
     *   - reward3 (int) 奖励类型3
     *   - num3 (int) 数量3
     */
    public const array ROWS = [
        1 => ['lid' => 1, 'lname' => '芝麻元宵', 'id_1' => 1, 'name_1' => '糯米', 'price_1' => 10, 't1' => 50, 'id_2' => 2, 'name_2' => '芝麻', 'price_2' => 12, 't2' => 50, 'id_3' => 3, 'name_3' => '白糖', 'price_3' => 10, 't3' => 50, 'reward1' => 4, 'num1' => 8000, 'reward2' => 31, 'num2' => 20, 'reward3' => 32, 'num3' => 12],
        2 => ['lid' => 2, 'lname' => '花生元宵', 'id_1' => 3, 'name_1' => '白糖', 'price_1' => 10, 't1' => 50, 'id_2' => 4, 'name_2' => '黑米', 'price_2' => 25, 't2' => 50, 'id_3' => 5, 'name_3' => '花生', 'price_3' => 25, 't3' => 50, 'reward1' => 6, 'num1' => 10, 'reward2' => 31, 'num2' => 25, 'reward3' => 32, 'num3' => 15],
        3 => ['lid' => 3, 'lname' => '紫玉红枣元宵', 'id_1' => 3, 'name_1' => '白糖', 'price_1' => 10, 't1' => 50, 'id_2' => 6, 'name_2' => '紫薯', 'price_2' => 35, 't2' => 50, 'id_3' => 7, 'name_3' => '红枣', 'price_3' => 35, 't3' => 50, 'reward1' => 3, 'num1' => 20, 'reward2' => 31, 'num2' => 30, 'reward3' => 32, 'num3' => 18],
        4 => ['lid' => 4, 'lname' => '翡翠火腿元宵', 'id_1' => 8, 'name_1' => '火腿', 'price_1' => 40, 't1' => 50, 'id_2' => 9, 'name_2' => '抹茶', 'price_2' => 40, 't2' => 50, 'id_3' => 10, 'name_3' => '酒酿', 'price_3' => 40, 't3' => 50, 'reward1' => 7, 'num1' => 5, 'reward2' => 31, 'num2' => 35, 'reward3' => 32, 'num3' => 20],
    ];
}
