<?php

declare(strict_types=1);

namespace Hanjiang\Battle;

/**
 * 原版核心游戏数值静态主仓库 (收敛自 545+ 表核心字典与 ini.js)
 */
final class GameMasterData
{
    /**
     * 原版 16 大历史战役章节全景表
     */
    public const array CHAPTERS = [
        1 => ['id' => 1, 'name' => '大兴山之战', 'desc' => '刘关张桃园结义，初战黄巾叛军', 'missions_count' => 10],
        2 => ['id' => 2, 'name' => '宛城之战', 'desc' => '张绣夜袭曹营，猛将典韦战死', 'missions_count' => 10],
        3 => ['id' => 3, 'name' => '虎牢关之战', 'desc' => '十八路诸侯讨董卓，三英战吕布', 'missions_count' => 10],
        4 => ['id' => 4, 'name' => '徐州争夺战', 'desc' => '陶谦三让徐州，吕布夜袭下邳', 'missions_count' => 10],
        5 => ['id' => 5, 'name' => '白马之战', 'desc' => '关羽解白马之围，斩颜良诛文丑', 'missions_count' => 10],
        6 => ['id' => 6, 'name' => '官渡之战', 'desc' => '曹操以弱胜强，火烧乌巢破袁绍', 'missions_count' => 10],
        7 => ['id' => 7, 'name' => '博望坡之战', 'desc' => '诸葛亮初出茅庐第一功，火烧博望', 'missions_count' => 10],
        8 => ['id' => 8, 'name' => '长坂坡之战', 'desc' => '赵子龙单骑救主，张翼德大闹长坂桥', 'missions_count' => 10],
        9 => ['id' => 9, 'name' => '赤壁之战', 'desc' => '孙刘联军火烧赤壁，百万曹军灰飞烟灭', 'missions_count' => 10],
        10 => ['id' => 10, 'name' => '合肥之战', 'desc' => '张辽威震逍遥津，八百破十万', 'missions_count' => 10],
        11 => ['id' => 11, 'name' => '定军山之战', 'desc' => '老将黄忠刀斩夏侯渊，平定汉中', 'missions_count' => 10],
        12 => ['id' => 12, 'name' => '水淹七军', 'desc' => '关羽威震华夏，水淹于禁庞德', 'missions_count' => 10],
        13 => ['id' => 13, 'name' => '夷陵之战', 'desc' => '陆逊火烧连营七百里，刘备托孤白帝城', 'missions_count' => 10],
        14 => ['id' => 14, 'name' => '七擒孟获', 'desc' => '诸葛亮平定南中，攻心为上', 'missions_count' => 10],
        15 => ['id' => 15, 'name' => '六出祁山', 'desc' => '空城计智退司马懿，星落五丈原', 'missions_count' => 10],
        16 => ['id' => 16, 'name' => '三国一统', 'desc' => '西晋灭吴，天下归一', 'missions_count' => 10],
    ];

    /**
     * 原版神兵利器名剑宝甲全品级对照表
     */
    public const array ARTIFACTS = [
        101 => ['id' => 101, 'name' => '青龙偃月刀', 'type' => 1, 'quality' => 5, 'base_power' => 120, 'desc' => '关羽佩刀，冷艳锯'],
        102 => ['id' => 102, 'name' => '丈八蛇矛', 'type' => 1, 'quality' => 5, 'base_power' => 115, 'desc' => '张飞佩矛，万夫莫敌'],
        103 => ['id' => 103, 'name' => '倚天剑', 'type' => 1, 'quality' => 5, 'base_power' => 110, 'desc' => '曹操随身佩剑，削铁如泥'],
        104 => ['id' => 104, 'name' => '方天画戟', 'type' => 1, 'quality' => 6, 'base_power' => 150, 'desc' => '吕布绝世神兵，无双乱舞'],
        201 => ['id' => 201, 'name' => '兽面吞头连环铠', 'type' => 2, 'quality' => 5, 'base_power' => 90, 'desc' => '重金玄铁重甲，坚不可摧'],
        202 => ['id' => 202, 'name' => '八卦紫绶仙衣', 'type' => 2, 'quality' => 5, 'base_power' => 85, 'desc' => '诸葛孔明策防道袍'],
        301 => ['id' => 301, 'name' => '锦襕红锦披风', 'type' => 3, 'quality' => 4, 'base_power' => 70, 'desc' => '金丝雀羽披风，御寒护体'],
        401 => ['id' => 401, 'name' => '传国玉玺虎符', 'type' => 4, 'quality' => 6, 'base_power' => 500, 'desc' => '奉天承运，号令天下诸侯'],
    ];

    /**
     * 获取战役章节详情
     *
     * @return array<string, mixed>|null
     */
    public static function getChapter(int $id): ?array
    {
        return self::CHAPTERS[$id] ?? null;
    }
}
