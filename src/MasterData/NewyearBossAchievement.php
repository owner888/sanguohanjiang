<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 春节年兽成就奖励表（旧版 zc_sanguo_newyear_boss_achievement）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class NewyearBossAchievement
{
    public const string TABLE = 'zc_sanguo_newyear_boss_achievement';

    /**
     * 按主键 id 索引，共 38 行。字段：
     *   - id (int) 成就ID
     *   - type (tinyint) 成就类型（1伤害，2鼓舞，3闪避）
     *   - name (varchar) 成就名称
     *   - value (bigint) 成就参数
     *   - gettype (tinyint) 奖品类型（1元宝 2银币 3黄宝石 4紫宝石 5原魂 6水晶 7声望 8装备 9魔石碎片 10突飞 11坐骑 12蓝宝石 13武将）
     *   - getvalue (int) 奖励参数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => 1, 'name' => '单日伤害100万', 'value' => 1000000, 'gettype' => 3, 'getvalue' => 10],
        2 => ['id' => 2, 'type' => 1, 'name' => '单日伤害500万', 'value' => 5000000, 'gettype' => 3, 'getvalue' => 15],
        3 => ['id' => 3, 'type' => 1, 'name' => '单日伤害1000万', 'value' => 10000000, 'gettype' => 3, 'getvalue' => 20],
        4 => ['id' => 4, 'type' => 1, 'name' => '单日伤害5000万', 'value' => 50000000, 'gettype' => 3, 'getvalue' => 25],
        5 => ['id' => 5, 'type' => 1, 'name' => '单日伤害1亿', 'value' => 100000000, 'gettype' => 3, 'getvalue' => 30],
        6 => ['id' => 6, 'type' => 1, 'name' => '单日伤害5亿', 'value' => 500000000, 'gettype' => 4, 'getvalue' => 30],
        7 => ['id' => 7, 'type' => 1, 'name' => '单日伤害10亿', 'value' => 1000000000, 'gettype' => 4, 'getvalue' => 40],
        8 => ['id' => 8, 'type' => 1, 'name' => '单日伤害25亿', 'value' => 2500000000, 'gettype' => 4, 'getvalue' => 50],
        9 => ['id' => 9, 'type' => 1, 'name' => '单日伤害50亿', 'value' => 5000000000, 'gettype' => 4, 'getvalue' => 70],
        10 => ['id' => 10, 'type' => 1, 'name' => '单日伤害100亿', 'value' => 10000000000, 'gettype' => 4, 'getvalue' => 100],
        11 => ['id' => 11, 'type' => 2, 'name' => '单日元宝鼓舞1次', 'value' => 1, 'gettype' => 5, 'getvalue' => 200],
        12 => ['id' => 12, 'type' => 2, 'name' => '单日元宝鼓舞2次', 'value' => 2, 'gettype' => 5, 'getvalue' => 300],
        13 => ['id' => 13, 'type' => 2, 'name' => '单日元宝鼓舞4次', 'value' => 4, 'gettype' => 5, 'getvalue' => 400],
        14 => ['id' => 14, 'type' => 2, 'name' => '单日元宝鼓舞6次', 'value' => 6, 'gettype' => 5, 'getvalue' => 500],
        15 => ['id' => 15, 'type' => 2, 'name' => '单日元宝鼓舞8次', 'value' => 8, 'gettype' => 5, 'getvalue' => 600],
        16 => ['id' => 16, 'type' => 2, 'name' => '单日元宝鼓舞10次', 'value' => 10, 'gettype' => 5, 'getvalue' => 700],
        17 => ['id' => 17, 'type' => 2, 'name' => '单日元宝鼓舞15次', 'value' => 15, 'gettype' => 5, 'getvalue' => 800],
        18 => ['id' => 18, 'type' => 2, 'name' => '单日元宝鼓舞20次', 'value' => 20, 'gettype' => 5, 'getvalue' => 1000],
        19 => ['id' => 19, 'type' => 2, 'name' => '单日元宝鼓舞25次', 'value' => 25, 'gettype' => 5, 'getvalue' => 1500],
        20 => ['id' => 20, 'type' => 2, 'name' => '单日元宝鼓舞30次', 'value' => 30, 'gettype' => 5, 'getvalue' => 2000],
        21 => ['id' => 21, 'type' => 3, 'name' => '单日闪避3次', 'value' => 3, 'gettype' => 10, 'getvalue' => 5],
        22 => ['id' => 22, 'type' => 3, 'name' => '单日闪避5次', 'value' => 5, 'gettype' => 10, 'getvalue' => 10],
        23 => ['id' => 23, 'type' => 3, 'name' => '单日闪避10次', 'value' => 10, 'gettype' => 10, 'getvalue' => 15],
        24 => ['id' => 24, 'type' => 3, 'name' => '单日闪避15次', 'value' => 15, 'gettype' => 10, 'getvalue' => 20],
        25 => ['id' => 25, 'type' => 3, 'name' => '单日闪避20次', 'value' => 20, 'gettype' => 10, 'getvalue' => 25],
        26 => ['id' => 26, 'type' => 3, 'name' => '单日闪避25次', 'value' => 25, 'gettype' => 10, 'getvalue' => 30],
        27 => ['id' => 27, 'type' => 3, 'name' => '单日闪避30次', 'value' => 30, 'gettype' => 10, 'getvalue' => 40],
        28 => ['id' => 28, 'type' => 3, 'name' => '单日闪避50次', 'value' => 50, 'gettype' => 10, 'getvalue' => 50],
        29 => ['id' => 29, 'type' => 3, 'name' => '单日闪避70次', 'value' => 70, 'gettype' => 10, 'getvalue' => 60],
        30 => ['id' => 30, 'type' => 3, 'name' => '单日闪避100次', 'value' => 100, 'gettype' => 10, 'getvalue' => 70],
        31 => ['id' => 31, 'type' => 4, 'name' => '单日伤害榜前200名', 'value' => 200, 'gettype' => 9, 'getvalue' => 1000],
        32 => ['id' => 32, 'type' => 4, 'name' => '单日伤害榜前100名', 'value' => 100, 'gettype' => 9, 'getvalue' => 1500],
        33 => ['id' => 33, 'type' => 4, 'name' => '单日伤害榜前70名', 'value' => 70, 'gettype' => 9, 'getvalue' => 2000],
        34 => ['id' => 34, 'type' => 4, 'name' => '单日伤害榜前50名', 'value' => 50, 'gettype' => 9, 'getvalue' => 2500],
        35 => ['id' => 35, 'type' => 4, 'name' => '单日伤害榜前30名', 'value' => 30, 'gettype' => 9, 'getvalue' => 3000],
        36 => ['id' => 36, 'type' => 4, 'name' => '单日伤害榜前10名', 'value' => 10, 'gettype' => 9, 'getvalue' => 4000],
        37 => ['id' => 37, 'type' => 4, 'name' => '单日伤害榜前5名', 'value' => 5, 'gettype' => 9, 'getvalue' => 5000],
        38 => ['id' => 38, 'type' => 4, 'name' => '单日伤害榜第1名', 'value' => 1, 'gettype' => 9, 'getvalue' => 6000],
    ];
}
