<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 东瀛怪物模板表（旧版 zc_sanguo_japan_monster）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class JapanMonster
{
    public const string TABLE = 'zc_sanguo_japan_monster';

    /**
     * 按主键 id 索引，共 31 行。字段：
     *   - id (int) 怪物ID
     *   - name (varchar) 怪物名称
     *   - image (int) 美术图ID
     *   - level (int) 怪物等级
     *   - blood (int) 基础HP
     *   - wuli (int) 武力
     *   - zhili (int) 智力
     *   - type (int) 职业
     *   - attack (int) 怪物攻击
     *   - pdefense (int) 物理防御
     *   - mdefense (int) 策略防御
     *   - talentid (int) 天赋ID
     *   - skillid (int) 技能ID
     *   - power (int) 初始士气
     *   - mingzhong (int) 命中
     *   - kangyun (int) 抗眩晕
     *   - extra_recover_mp (int) 额外回气
     *   - attack_ratio (double) 受到伤害的系数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '鬼小岛', 'image' => 488, 'level' => 130, 'blood' => 100000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 10790, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 140, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        2 => ['id' => 2, 'name' => '力士', 'image' => 496, 'level' => 130, 'blood' => 200000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 10790, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 140, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        3 => ['id' => 3, 'name' => '柿崎景家', 'image' => 491, 'level' => 135, 'blood' => 300000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 13156, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 140, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        4 => ['id' => 4, 'name' => '冲阵骑兵', 'image' => 483, 'level' => 135, 'blood' => 400000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 13156, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 140, 'kangyun' => 85, 'extra_recover_mp' => 75, 'attack_ratio' => 0.1],
        5 => ['id' => 5, 'name' => '直江兼续', 'image' => 502, 'level' => 140, 'blood' => 500000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 2, 'attack' => 15876, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 140, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        6 => ['id' => 6, 'name' => '阴阳师', 'image' => 498, 'level' => 140, 'blood' => 600000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 2, 'attack' => 15876, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 140, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        7 => ['id' => 7, 'name' => '上杉谦信', 'image' => 494, 'level' => 150, 'blood' => 700000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 26400, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 140, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        8 => ['id' => 8, 'name' => '阴阳师', 'image' => 498, 'level' => 150, 'blood' => 800000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 2, 'attack' => 26400, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 140, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        9 => ['id' => 9, 'name' => '山县昌景', 'image' => 493, 'level' => 160, 'blood' => 900000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 35200, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 160, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        10 => ['id' => 10, 'name' => '赤备骑兵', 'image' => 483, 'level' => 160, 'blood' => 1000000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 35200, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 160, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        11 => ['id' => 11, 'name' => '高坂昌信', 'image' => 487, 'level' => 165, 'blood' => 1100000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 40260, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 160, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        12 => ['id' => 12, 'name' => '铁炮兵', 'image' => 495, 'level' => 165, 'blood' => 1200000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 40260, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 160, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        13 => ['id' => 13, 'name' => '真田幸村', 'image' => 500, 'level' => 170, 'blood' => 1300000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 56100, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 160, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        14 => ['id' => 14, 'name' => '影武突击兵', 'image' => 496, 'level' => 170, 'blood' => 1400000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 56100, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 160, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        15 => ['id' => 15, 'name' => '武田信玄', 'image' => 497, 'level' => 180, 'blood' => 1500000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 90270, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 160, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        16 => ['id' => 16, 'name' => '影武者', 'image' => 496, 'level' => 180, 'blood' => 1600000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 90270, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 160, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        17 => ['id' => 17, 'name' => '服部半藏', 'image' => 486, 'level' => 190, 'blood' => 1700000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 194256, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 180, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        18 => ['id' => 18, 'name' => '伊贺忍者', 'image' => 492, 'level' => 190, 'blood' => 1800000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 194256, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 180, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        19 => ['id' => 19, 'name' => '酒井忠次', 'image' => 489, 'level' => 195, 'blood' => 1900000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 280800, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 180, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        20 => ['id' => 20, 'name' => '玄武重步', 'image' => 484, 'level' => 195, 'blood' => 2000000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 280800, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 180, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        21 => ['id' => 21, 'name' => '本多忠胜', 'image' => 481, 'level' => 200, 'blood' => 2100000000, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 360000, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 180, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        22 => ['id' => 22, 'name' => '雉刀兵', 'image' => 484, 'level' => 200, 'blood' => 2147483647, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 360000, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 180, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        23 => ['id' => 23, 'name' => '德川家康', 'image' => 485, 'level' => 210, 'blood' => 2147483647, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 664520, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 180, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        24 => ['id' => 24, 'name' => '纪州流忍者', 'image' => 492, 'level' => 210, 'blood' => 2147483647, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 664520, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 180, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        25 => ['id' => 25, 'name' => '柴田胜家', 'image' => 482, 'level' => 220, 'blood' => 2147483647, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 838148, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 200, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        26 => ['id' => 26, 'name' => '阴阳师', 'image' => 498, 'level' => 220, 'blood' => 2147483647, 'wuli' => 100000, 'zhili' => 100000, 'type' => 2, 'attack' => 838148, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 200, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        27 => ['id' => 27, 'name' => '明智光秀', 'image' => 490, 'level' => 225, 'blood' => 2147483647, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 1034879, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 200, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        28 => ['id' => 28, 'name' => '战忍', 'image' => 492, 'level' => 225, 'blood' => 2147483647, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 1034879, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 200, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        29 => ['id' => 29, 'name' => '羽柴秀吉', 'image' => 499, 'level' => 230, 'blood' => 2147483647, 'wuli' => 100000, 'zhili' => 100000, 'type' => 2, 'attack' => 1190085, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 200, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        30 => ['id' => 30, 'name' => '精英足轻', 'image' => 496, 'level' => 230, 'blood' => 2147483647, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 1190085, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 200, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
        31 => ['id' => 31, 'name' => '织田信长', 'image' => 501, 'level' => 240, 'blood' => 2147483647, 'wuli' => 100000, 'zhili' => 100000, 'type' => 1, 'attack' => 1316016, 'pdefense' => 3000, 'mdefense' => 3000, 'talentid' => 1, 'skillid' => 1, 'power' => 0, 'mingzhong' => 200, 'kangyun' => 85, 'extra_recover_mp' => 25, 'attack_ratio' => 0.1],
    ];
}
