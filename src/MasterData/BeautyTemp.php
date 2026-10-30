<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 美人模板表（旧版 zc_sanguo_beauty_temp）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class BeautyTemp
{
    public const string TABLE = 'zc_sanguo_beauty_temp';

    /**
     * 按主键 id 索引，共 7 行。字段：
     *   - id (int) 序列id
     *   - name (varchar) 美人名称
     *   - skill1 (int) 技能1
     *   - skill2 (int) 技能2
     *   - skill3 (int) 技能3
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '祝融', 'skill1' => 1, 'skill2' => 17, 'skill3' => 7],
        2 => ['id' => 2, 'name' => '甄姬', 'skill1' => 2, 'skill2' => 18, 'skill3' => 6],
        3 => ['id' => 3, 'name' => '孙尚香', 'skill1' => 3, 'skill2' => 19, 'skill3' => 12],
        4 => ['id' => 4, 'name' => '大乔', 'skill1' => 13, 'skill2' => 9, 'skill3' => 5],
        5 => ['id' => 5, 'name' => '小乔', 'skill1' => 14, 'skill2' => 11, 'skill3' => 4],
        6 => ['id' => 6, 'name' => '蔡文姬', 'skill1' => 15, 'skill2' => 8, 'skill3' => 1],
        7 => ['id' => 7, 'name' => '貂蝉', 'skill1' => 16, 'skill2' => 10, 'skill3' => 2],
    ];
}
