<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 国家庆典身份奖励数据（旧版 zc_sanguo_act_celebration_member）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActCelebrationMember
{
    public const string TABLE = 'zc_sanguo_act_celebration_member';

    /**
     * 按主键 id 索引，共 8 行。字段：
     *   - id (int) ID
     *   - name (varchar) 身份名称
     *   - gold (float) 充值元宝
     *   - type1 (int) 奖励1类型
     *   - num1 (int) 奖励1数量
     *   - value1 (int) 奖励1参数
     *   - type2 (int) 奖励2类型
     *   - num2 (int) 奖励2数量
     *   - value2 (int) 奖励2参数
     *   - type3 (int) 奖励3类型
     *   - num3 (int) 奖励3数量
     *   - value3 (int) 奖励3参数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '佃户', 'gold' => 0.0, 'type1' => 0, 'num1' => 0, 'value1' => 0, 'type2' => 0, 'num2' => 0, 'value2' => 0, 'type3' => 0, 'num3' => 0, 'value3' => 0],
        2 => ['id' => 2, 'name' => '小农', 'gold' => 300.0, 'type1' => 10, 'num1' => 100, 'value1' => 0, 'type2' => 4, 'num2' => 50, 'value2' => 0, 'type3' => 2, 'num3' => 5000000, 'value3' => 0],
        3 => ['id' => 3, 'name' => '中农', 'gold' => 1980.0, 'type1' => 10, 'num1' => 150, 'value1' => 0, 'type2' => 4, 'num2' => 80, 'value2' => 0, 'type3' => 7, 'num3' => 6000, 'value3' => 0],
        4 => ['id' => 4, 'name' => '富农', 'gold' => 6480.0, 'type1' => 10, 'num1' => 200, 'value1' => 0, 'type2' => 4, 'num2' => 100, 'value2' => 0, 'type3' => 7, 'num3' => 10000, 'value3' => 0],
        5 => ['id' => 5, 'name' => '商人', 'gold' => 18000.0, 'type1' => 10, 'num1' => 300, 'value1' => 0, 'type2' => 4, 'num2' => 150, 'value2' => 0, 'type3' => 7, 'num3' => 15000, 'value3' => 0],
        6 => ['id' => 6, 'name' => '地主', 'gold' => 50000.0, 'type1' => 10, 'num1' => 500, 'value1' => 0, 'type2' => 4, 'num2' => 300, 'value2' => 0, 'type3' => 7, 'num3' => 30000, 'value3' => 0],
        7 => ['id' => 7, 'name' => '员外', 'gold' => 150000.0, 'type1' => 10, 'num1' => 800, 'value1' => 0, 'type2' => 4, 'num2' => 600, 'value2' => 0, 'type3' => 7, 'num3' => 60000, 'value3' => 0],
        8 => ['id' => 8, 'name' => '王侯', 'gold' => 500000.0, 'type1' => 26, 'num1' => 104, 'value1' => 1, 'type2' => 4, 'num2' => 1000, 'value2' => 0, 'type3' => 7, 'num3' => 100000, 'value3' => 0],
    ];
}
