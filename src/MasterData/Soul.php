<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 将魂模板表（旧版 zc_sanguo_soul）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Soul
{
    public const string TABLE = 'zc_sanguo_soul';

    /**
     * 按主键 id 索引，共 66 行。字段：
     *   - id (int)
     *   - name (varchar) 名称
     *   - type (tinyint) 类型
     *   - quality (tinyint) 品质
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '怒之战魂', 'type' => 1, 'quality' => 1],
        2 => ['id' => 2, 'name' => '绝望战魂', 'type' => 1, 'quality' => 2],
        3 => ['id' => 3, 'name' => '寒冰战魂', 'type' => 1, 'quality' => 3],
        4 => ['id' => 4, 'name' => '无畏战魂', 'type' => 1, 'quality' => 4],
        5 => ['id' => 5, 'name' => '古神战魂', 'type' => 1, 'quality' => 5],
        6 => ['id' => 6, 'name' => '骁勇战魂', 'type' => 2, 'quality' => 1],
        7 => ['id' => 7, 'name' => '果敢战魂', 'type' => 2, 'quality' => 2],
        8 => ['id' => 8, 'name' => '常胜战魂', 'type' => 2, 'quality' => 3],
        9 => ['id' => 9, 'name' => '无双战魂', 'type' => 2, 'quality' => 4],
        10 => ['id' => 10, 'name' => '无敌战魂', 'type' => 2, 'quality' => 5],
        11 => ['id' => 11, 'name' => '睿智之魄', 'type' => 3, 'quality' => 1],
        12 => ['id' => 12, 'name' => '洞察之魄', 'type' => 3, 'quality' => 2],
        13 => ['id' => 13, 'name' => '诡诈之魄', 'type' => 3, 'quality' => 3],
        14 => ['id' => 14, 'name' => '深谋之魄', 'type' => 3, 'quality' => 4],
        15 => ['id' => 15, 'name' => '神鬼之魄', 'type' => 3, 'quality' => 5],
        16 => ['id' => 16, 'name' => '稳固战魂', 'type' => 4, 'quality' => 1],
        17 => ['id' => 17, 'name' => '坚韧战魂', 'type' => 4, 'quality' => 2],
        18 => ['id' => 18, 'name' => '厚重战魂', 'type' => 4, 'quality' => 3],
        19 => ['id' => 19, 'name' => '磐石战魂', 'type' => 4, 'quality' => 4],
        20 => ['id' => 20, 'name' => '不死战魂', 'type' => 4, 'quality' => 5],
        21 => ['id' => 21, 'name' => '灵动战魂', 'type' => 5, 'quality' => 1],
        22 => ['id' => 22, 'name' => '迅捷战魂', 'type' => 5, 'quality' => 2],
        23 => ['id' => 23, 'name' => '急速战魂', 'type' => 5, 'quality' => 3],
        24 => ['id' => 24, 'name' => '飘渺战魂', 'type' => 5, 'quality' => 4],
        25 => ['id' => 25, 'name' => '飞天战魂', 'type' => 5, 'quality' => 5],
        26 => ['id' => 26, 'name' => '谋臣之魄', 'type' => 6, 'quality' => 1],
        27 => ['id' => 27, 'name' => '名士之魄', 'type' => 6, 'quality' => 2],
        28 => ['id' => 28, 'name' => '大贤之魄', 'type' => 6, 'quality' => 3],
        29 => ['id' => 29, 'name' => '诸子之魄', 'type' => 6, 'quality' => 4],
        30 => ['id' => 30, 'name' => '圣人之魄', 'type' => 6, 'quality' => 5],
        31 => ['id' => 31, 'name' => '狂躁战魂', 'type' => 7, 'quality' => 1],
        32 => ['id' => 32, 'name' => '残忍战魂', 'type' => 7, 'quality' => 2],
        33 => ['id' => 33, 'name' => '兽血战魂', 'type' => 7, 'quality' => 3],
        34 => ['id' => 34, 'name' => '残暴战魂', 'type' => 7, 'quality' => 4],
        35 => ['id' => 35, 'name' => '血魔战魂', 'type' => 7, 'quality' => 5],
        36 => ['id' => 36, 'name' => '斗士战魂', 'type' => 8, 'quality' => 1],
        37 => ['id' => 37, 'name' => '虎贲战魂', 'type' => 8, 'quality' => 2],
        38 => ['id' => 38, 'name' => '霸者战魂', 'type' => 8, 'quality' => 3],
        39 => ['id' => 39, 'name' => '破天战魂', 'type' => 8, 'quality' => 4],
        40 => ['id' => 40, 'name' => '诛神战魂', 'type' => 8, 'quality' => 5],
        41 => ['id' => 41, 'name' => '斗神战魂', 'type' => 1, 'quality' => 6],
        42 => ['id' => 42, 'name' => '无我战魂', 'type' => 2, 'quality' => 6],
        43 => ['id' => 43, 'name' => '太初战魂', 'type' => 3, 'quality' => 6],
        44 => ['id' => 44, 'name' => '涅槃战魂', 'type' => 4, 'quality' => 6],
        45 => ['id' => 45, 'name' => '追风战魂', 'type' => 5, 'quality' => 6],
        46 => ['id' => 46, 'name' => '金仙战魂', 'type' => 6, 'quality' => 6],
        47 => ['id' => 47, 'name' => '战魔战魂', 'type' => 7, 'quality' => 6],
        48 => ['id' => 48, 'name' => '诛仙战魂', 'type' => 8, 'quality' => 6],
        49 => ['id' => 49, 'name' => '拂柳战魂', 'type' => 9, 'quality' => 1],
        50 => ['id' => 50, 'name' => '飞花战魂', 'type' => 9, 'quality' => 2],
        51 => ['id' => 51, 'name' => '飞星战魂', 'type' => 9, 'quality' => 3],
        52 => ['id' => 52, 'name' => '流星战魂', 'type' => 9, 'quality' => 4],
        53 => ['id' => 53, 'name' => '追日战魂', 'type' => 9, 'quality' => 5],
        54 => ['id' => 54, 'name' => '射天战魂', 'type' => 9, 'quality' => 6],
        55 => ['id' => 55, 'name' => '破风战魂', 'type' => 10, 'quality' => 1],
        56 => ['id' => 56, 'name' => '破水战魂', 'type' => 10, 'quality' => 2],
        57 => ['id' => 57, 'name' => '破山战魂', 'type' => 10, 'quality' => 3],
        58 => ['id' => 58, 'name' => '破军战魂', 'type' => 10, 'quality' => 4],
        59 => ['id' => 59, 'name' => '破魔战魂', 'type' => 10, 'quality' => 5],
        60 => ['id' => 60, 'name' => '破神战魂', 'type' => 10, 'quality' => 6],
        61 => ['id' => 61, 'name' => '陷阵战魂', 'type' => 11, 'quality' => 1],
        62 => ['id' => 62, 'name' => '绝尘战魂', 'type' => 11, 'quality' => 2],
        63 => ['id' => 63, 'name' => '贪狼战魂', 'type' => 11, 'quality' => 3],
        64 => ['id' => 64, 'name' => '斩仙战魂', 'type' => 11, 'quality' => 4],
        65 => ['id' => 65, 'name' => '诛魔战魂', 'type' => 11, 'quality' => 5],
        66 => ['id' => 66, 'name' => '战神战魂', 'type' => 11, 'quality' => 6],
    ];
}
