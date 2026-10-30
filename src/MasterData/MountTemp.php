<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 坐骑模版表（旧版 zc_sanguo_mount_temp）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class MountTemp
{
    public const string TABLE = 'zc_sanguo_mount_temp';

    /**
     * 按主键 id 索引，共 30 行。字段：
     *   - id (int) 坐骑id
     *   - name (varchar) 坐骑名称
     *   - quality (tinyint) 坐骑品级（1白 2绿 3蓝 4金 5紫 6红 7橙）
     *   - attack (int) 初始攻击
     *   - wufang (int) 初始物理防御
     *   - cefang (int) 初始策略防御
     *   - hp (int) 初始生命值
     *   - attack_up (int) 攻击成长
     *   - wufang_up (int) 物理防御成长
     *   - cefang_up (int) 策略防御成长
     *   - hp_up (int) 生命值成长
     *   - exp (int) 折合经验
     *   - type (tinyint) 特殊属性(1所有攻击力加成)
     *   - level (int) all=坐骑等级*level
     *   - value (int) 初始属性加成值
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '越影', 'quality' => 1, 'attack' => 750, 'wufang' => 375, 'cefang' => 375, 'hp' => 1875, 'attack_up' => 150, 'wufang_up' => 75, 'cefang_up' => 75, 'hp_up' => 375, 'exp' => 50, 'type' => 0, 'level' => 0, 'value' => 0],
        2 => ['id' => 2, 'name' => '逾辉', 'quality' => 1, 'attack' => 800, 'wufang' => 400, 'cefang' => 400, 'hp' => 2000, 'attack_up' => 150, 'wufang_up' => 75, 'cefang_up' => 75, 'hp_up' => 375, 'exp' => 75, 'type' => 0, 'level' => 0, 'value' => 0],
        3 => ['id' => 3, 'name' => '绝地', 'quality' => 2, 'attack' => 1000, 'wufang' => 500, 'cefang' => 500, 'hp' => 2500, 'attack_up' => 200, 'wufang_up' => 100, 'cefang_up' => 100, 'hp_up' => 500, 'exp' => 100, 'type' => 0, 'level' => 0, 'value' => 0],
        4 => ['id' => 4, 'name' => '超光', 'quality' => 2, 'attack' => 1100, 'wufang' => 550, 'cefang' => 550, 'hp' => 2750, 'attack_up' => 200, 'wufang_up' => 100, 'cefang_up' => 100, 'hp_up' => 500, 'exp' => 150, 'type' => 0, 'level' => 0, 'value' => 0],
        5 => ['id' => 5, 'name' => '奔宵', 'quality' => 3, 'attack' => 1250, 'wufang' => 625, 'cefang' => 625, 'hp' => 3125, 'attack_up' => 250, 'wufang_up' => 125, 'cefang_up' => 125, 'hp_up' => 625, 'exp' => 200, 'type' => 0, 'level' => 0, 'value' => 0],
        6 => ['id' => 6, 'name' => '腾雾', 'quality' => 3, 'attack' => 1400, 'wufang' => 700, 'cefang' => 700, 'hp' => 3500, 'attack_up' => 250, 'wufang_up' => 125, 'cefang_up' => 125, 'hp_up' => 625, 'exp' => 250, 'type' => 0, 'level' => 0, 'value' => 0],
        7 => ['id' => 7, 'name' => '追电', 'quality' => 4, 'attack' => 1500, 'wufang' => 750, 'cefang' => 750, 'hp' => 3750, 'attack_up' => 300, 'wufang_up' => 150, 'cefang_up' => 150, 'hp_up' => 750, 'exp' => 400, 'type' => 0, 'level' => 0, 'value' => 0],
        8 => ['id' => 8, 'name' => '飞翩', 'quality' => 4, 'attack' => 1600, 'wufang' => 800, 'cefang' => 800, 'hp' => 4000, 'attack_up' => 300, 'wufang_up' => 150, 'cefang_up' => 150, 'hp_up' => 750, 'exp' => 450, 'type' => 0, 'level' => 0, 'value' => 0],
        9 => ['id' => 9, 'name' => '铜爵', 'quality' => 4, 'attack' => 1750, 'wufang' => 875, 'cefang' => 875, 'hp' => 4375, 'attack_up' => 300, 'wufang_up' => 150, 'cefang_up' => 150, 'hp_up' => 750, 'exp' => 600, 'type' => 0, 'level' => 0, 'value' => 0],
        10 => ['id' => 10, 'name' => '蹑景', 'quality' => 4, 'attack' => 1800, 'wufang' => 900, 'cefang' => 900, 'hp' => 4500, 'attack_up' => 300, 'wufang_up' => 150, 'cefang_up' => 150, 'hp_up' => 750, 'exp' => 650, 'type' => 0, 'level' => 0, 'value' => 0],
        11 => ['id' => 11, 'name' => '乌骓', 'quality' => 5, 'attack' => 2000, 'wufang' => 1000, 'cefang' => 1000, 'hp' => 5000, 'attack_up' => 400, 'wufang_up' => 200, 'cefang_up' => 200, 'hp_up' => 1000, 'exp' => 800, 'type' => 0, 'level' => 0, 'value' => 0],
        12 => ['id' => 12, 'name' => '的卢', 'quality' => 5, 'attack' => 2100, 'wufang' => 1050, 'cefang' => 1050, 'hp' => 5250, 'attack_up' => 400, 'wufang_up' => 200, 'cefang_up' => 200, 'hp_up' => 1000, 'exp' => 850, 'type' => 0, 'level' => 0, 'value' => 0],
        13 => ['id' => 13, 'name' => '赤兔', 'quality' => 5, 'attack' => 2250, 'wufang' => 1125, 'cefang' => 1125, 'hp' => 5625, 'attack_up' => 400, 'wufang_up' => 200, 'cefang_up' => 200, 'hp_up' => 1000, 'exp' => 1200, 'type' => 0, 'level' => 0, 'value' => 0],
        14 => ['id' => 14, 'name' => '腾雾', 'quality' => 5, 'attack' => 2350, 'wufang' => 1175, 'cefang' => 1175, 'hp' => 5875, 'attack_up' => 400, 'wufang_up' => 200, 'cefang_up' => 200, 'hp_up' => 1000, 'exp' => 1300, 'type' => 0, 'level' => 0, 'value' => 0],
        15 => ['id' => 15, 'name' => '象龙', 'quality' => 6, 'attack' => 2500, 'wufang' => 1250, 'cefang' => 1250, 'hp' => 6250, 'attack_up' => 500, 'wufang_up' => 250, 'cefang_up' => 250, 'hp_up' => 1250, 'exp' => 1600, 'type' => 0, 'level' => 0, 'value' => 0],
        16 => ['id' => 16, 'name' => '照夜玉狮子', 'quality' => 6, 'attack' => 2700, 'wufang' => 1350, 'cefang' => 1350, 'hp' => 6750, 'attack_up' => 500, 'wufang_up' => 250, 'cefang_up' => 250, 'hp_up' => 1250, 'exp' => 1800, 'type' => 0, 'level' => 0, 'value' => 0],
        17 => ['id' => 17, 'name' => '猛虎', 'quality' => 6, 'attack' => 3200, 'wufang' => 1600, 'cefang' => 1600, 'hp' => 8000, 'attack_up' => 500, 'wufang_up' => 250, 'cefang_up' => 250, 'hp_up' => 1250, 'exp' => 2200, 'type' => 0, 'level' => 0, 'value' => 0],
        18 => ['id' => 18, 'name' => '乌云踏雪', 'quality' => 6, 'attack' => 3000, 'wufang' => 1500, 'cefang' => 1500, 'hp' => 7500, 'attack_up' => 500, 'wufang_up' => 250, 'cefang_up' => 250, 'hp_up' => 1250, 'exp' => 2000, 'type' => 0, 'level' => 0, 'value' => 0],
        19 => ['id' => 19, 'name' => '血狮', 'quality' => 6, 'attack' => 3800, 'wufang' => 1900, 'cefang' => 1900, 'hp' => 9500, 'attack_up' => 500, 'wufang_up' => 250, 'cefang_up' => 250, 'hp_up' => 1250, 'exp' => 2800, 'type' => 0, 'level' => 0, 'value' => 0],
        20 => ['id' => 20, 'name' => '狭翼', 'quality' => 6, 'attack' => 3500, 'wufang' => 1750, 'cefang' => 1750, 'hp' => 8750, 'attack_up' => 500, 'wufang_up' => 250, 'cefang_up' => 250, 'hp_up' => 1250, 'exp' => 2500, 'type' => 0, 'level' => 0, 'value' => 0],
        21 => ['id' => 21, 'name' => '麒麟', 'quality' => 7, 'attack' => 6000, 'wufang' => 3000, 'cefang' => 3000, 'hp' => 15000, 'attack_up' => 700, 'wufang_up' => 350, 'cefang_up' => 350, 'hp_up' => 1750, 'exp' => 10000, 'type' => 0, 'level' => 0, 'value' => 0],
        22 => ['id' => 22, 'name' => '白龙马', 'quality' => 7, 'attack' => 5000, 'wufang' => 2500, 'cefang' => 2500, 'hp' => 12500, 'attack_up' => 700, 'wufang_up' => 350, 'cefang_up' => 350, 'hp_up' => 1750, 'exp' => 10000, 'type' => 0, 'level' => 0, 'value' => 0],
        100 => ['id' => 100, 'name' => '年兽', 'quality' => 7, 'attack' => 10000, 'wufang' => 5000, 'cefang' => 5000, 'hp' => 25000, 'attack_up' => 700, 'wufang_up' => 350, 'cefang_up' => 350, 'hp_up' => 1750, 'exp' => 20000, 'type' => 0, 'level' => 0, 'value' => 0],
        101 => ['id' => 101, 'name' => '经验银马', 'quality' => 1, 'attack' => 50, 'wufang' => 50, 'cefang' => 50, 'hp' => 50, 'attack_up' => 50, 'wufang_up' => 50, 'cefang_up' => 50, 'hp_up' => 50, 'exp' => 1000, 'type' => 0, 'level' => 0, 'value' => 0],
        102 => ['id' => 102, 'name' => '经验金马', 'quality' => 4, 'attack' => 100, 'wufang' => 100, 'cefang' => 100, 'hp' => 100, 'attack_up' => 100, 'wufang_up' => 100, 'cefang_up' => 100, 'hp_up' => 100, 'exp' => 2000, 'type' => 0, 'level' => 0, 'value' => 0],
        103 => ['id' => 103, 'name' => '比翼鸟', 'quality' => 7, 'attack' => 11000, 'wufang' => 5500, 'cefang' => 5500, 'hp' => 15000, 'attack_up' => 750, 'wufang_up' => 400, 'cefang_up' => 400, 'hp_up' => 1800, 'exp' => 20000, 'type' => 1, 'level' => 1, 'value' => 5],
        104 => ['id' => 104, 'name' => '天禄', 'quality' => 7, 'attack' => 10000, 'wufang' => 6000, 'cefang' => 6000, 'hp' => 25000, 'attack_up' => 700, 'wufang_up' => 450, 'cefang_up' => 450, 'hp_up' => 1800, 'exp' => 20000, 'type' => 2, 'level' => 1, 'value' => 5],
        105 => ['id' => 105, 'name' => '龙雀', 'quality' => 7, 'attack' => 12000, 'wufang' => 5000, 'cefang' => 5000, 'hp' => 15500, 'attack_up' => 780, 'wufang_up' => 400, 'cefang_up' => 400, 'hp_up' => 1800, 'exp' => 20000, 'type' => 1, 'level' => 1, 'value' => 5],
        106 => ['id' => 106, 'name' => '魔法扫帚', 'quality' => 7, 'attack' => 11000, 'wufang' => 5500, 'cefang' => 5500, 'hp' => 16000, 'attack_up' => 750, 'wufang_up' => 400, 'cefang_up' => 400, 'hp_up' => 1800, 'exp' => 10000, 'type' => 0, 'level' => 0, 'value' => 0],
        107 => ['id' => 107, 'name' => '风蹄', 'quality' => 7, 'attack' => 10000, 'wufang' => 5800, 'cefang' => 5800, 'hp' => 20000, 'attack_up' => 700, 'wufang_up' => 450, 'cefang_up' => 450, 'hp_up' => 1800, 'exp' => 20000, 'type' => 3, 'level' => 1, 'value' => 20],
    ];
}
