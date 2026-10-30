<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 踏青寻宝-汉字兑换奖励表（旧版 zc_sanguo_act_treasure_hanzireward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActTreasureHanzireward
{
    public const string TABLE = 'zc_sanguo_act_treasure_hanzireward';

    /**
     * 按主键 id 索引，共 8 行。字段：
     *   - id (int) 主键ID
     *   - name (varchar) 兑换奖项名称
     *   - desc (varchar) 兑换奖项描述
     *   - level (int) 奖品等级
     *   - type1 (int) 奖励类型1
     *   - value1 (int) 奖励值1
     *   - type2 (int) 奖励类型2
     *   - value2 (int) 奖励值2
     *   - type3 (int) 奖励类型3
     *   - value3 (int) 奖励值3
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '特等奖', 'desc' => '集齐：悍将三国，谁与争锋', 'level' => 0, 'type1' => 1, 'value1' => 300, 'type2' => 4, 'value2' => 200, 'type3' => 5, 'value3' => 10000],
        2 => ['id' => 2, 'name' => '二等奖', 'desc' => '集齐：悍将三国', 'level' => 1, 'type1' => 1, 'value1' => 150, 'type2' => 4, 'value2' => 50, 'type3' => 5, 'value3' => 2000],
        3 => ['id' => 3, 'name' => '二等奖', 'desc' => '集齐：谁与争锋', 'level' => 1, 'type1' => 1, 'value1' => 150, 'type2' => 4, 'value2' => 50, 'type3' => 5, 'value3' => 2000],
        4 => ['id' => 4, 'name' => '三等奖', 'desc' => '集齐：悍将', 'level' => 2, 'type1' => 1, 'value1' => 60, 'type2' => 4, 'value2' => 10, 'type3' => 0, 'value3' => 0],
        5 => ['id' => 5, 'name' => '三等奖', 'desc' => '集齐：三国', 'level' => 2, 'type1' => 1, 'value1' => 60, 'type2' => 4, 'value2' => 10, 'type3' => 0, 'value3' => 0],
        6 => ['id' => 6, 'name' => '三等奖', 'desc' => '集齐：谁与', 'level' => 2, 'type1' => 1, 'value1' => 60, 'type2' => 4, 'value2' => 10, 'type3' => 0, 'value3' => 0],
        7 => ['id' => 7, 'name' => '三等奖', 'desc' => '集齐：争锋', 'level' => 2, 'type1' => 1, 'value1' => 60, 'type2' => 4, 'value2' => 10, 'type3' => 0, 'value3' => 0],
        8 => ['id' => 8, 'name' => '参与奖', 'desc' => '每晚12点每个汉字自动兑换30元宝', 'level' => 3, 'type1' => 1, 'value1' => 30, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0],
    ];
}
