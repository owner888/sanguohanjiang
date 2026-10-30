<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 等级奖励和开启功能表（旧版 zc_sanguo_level_func）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class LevelFunc
{
    public const string TABLE = 'zc_sanguo_level_func';

    /**
     * 按主键 id 索引，共 13 行。字段：
     *   - id (int) 主键ID
     *   - level (int) 等级
     *   - func_name (varchar) 开启功能名称
     *   - func_info (varchar) 开启功能描述
     *   - func_mark (varchar) 开始功能标识
     */
    public const array ROWS = [
        1 => ['id' => 1, 'level' => 12, 'func_name' => '布阵', 'func_info' => '开启布阵功能，可调整武将策略和强化上阵武将属性', 'func_mark' => 'team'],
        2 => ['id' => 2, 'level' => 16, 'func_name' => '强化', 'func_info' => '开启装备强化，可大幅提高装备效果', 'func_mark' => 'strong'],
        3 => ['id' => 3, 'level' => 20, 'func_name' => '砸金蛋', 'func_info' => '砸金蛋可以获得大量资源和高级武将', 'func_mark' => 'egg'],
        4 => ['id' => 4, 'level' => 30, 'func_name' => '蔡文姬', 'func_info' => '赠送7星武将——蔡文姬', 'func_mark' => 'avatar'],
        5 => ['id' => 5, 'level' => 40, 'func_name' => '培养', 'func_info' => '开启武将培养，可提高武将三围属性', 'func_mark' => 'foster'],
        6 => ['id' => 6, 'level' => 51, 'func_name' => '将魂', 'func_info' => '开启将魂功能，可提升转生武将三维属性与隐藏属性', 'func_mark' => 'soul'],
        7 => ['id' => 7, 'level' => 61, 'func_name' => '每日通商', 'func_info' => '开启每日通商，可获得海量银币', 'func_mark' => 'dailytrade'],
        8 => ['id' => 8, 'level' => 71, 'func_name' => '神将之门', 'func_info' => '开启神将之门，可获得海量原魂', 'func_mark' => 'generaltask'],
        9 => ['id' => 9, 'level' => 81, 'func_name' => '名将成就', 'func_info' => '开启名将成就，可大幅度提高战力', 'func_mark' => 'heromanual'],
        10 => ['id' => 10, 'level' => 91, 'func_name' => '天赋', 'func_info' => '开启主公天赋，可提升全体武将基础属性', 'func_mark' => 'talent'],
        11 => ['id' => 11, 'level' => 100, 'func_name' => '进阶', 'func_info' => '开启武将进阶，可大幅强化武将各项属性', 'func_mark' => 'advanced'],
        12 => ['id' => 12, 'level' => 120, 'func_name' => '坐骑', 'func_info' => '开启坐骑功能，可极大提升武将基础属性', 'func_mark' => 'mount'],
        13 => ['id' => 13, 'level' => 140, 'func_name' => '宝石', 'func_info' => '开启宝石功能，可极大强化装备基础属性', 'func_mark' => 'gem'],
    ];
}
