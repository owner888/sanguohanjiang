<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 打造材料表（旧版 zc_sanguo_materials_temp）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class MaterialsTemp
{
    public const string TABLE = 'zc_sanguo_materials_temp';

    /**
     * 按主键 id 索引，共 104 行。字段：
     *   - id (int) 自增ID
     *   - materials_id (int) 材料ID
     *   - name (varchar) 材料名称
     *   - quality (int) 材料品级 1紫色 2红色 3橙色 4无等级 5卷轴
     *   - chip (int) 是否卷轴碎片 1碎片 0卷轴
     *   - sales (int) 售价
     *   - desc (varchar) 装备描述
     */
    public const array ROWS = [
        1 => ['id' => 1, 'materials_id' => 1, 'name' => '钨钢', 'quality' => 5, 'chip' => 0, 'sales' => 5000, 'desc' => '1'],
        2 => ['id' => 2, 'materials_id' => 2, 'name' => '百年松木', 'quality' => 5, 'chip' => 0, 'sales' => 5000, 'desc' => '1'],
        3 => ['id' => 3, 'materials_id' => 3, 'name' => '赤铜矿', 'quality' => 5, 'chip' => 0, 'sales' => 5000, 'desc' => '1'],
        4 => ['id' => 4, 'materials_id' => 4, 'name' => '紫竹棉', 'quality' => 5, 'chip' => 0, 'sales' => 5000, 'desc' => '1'],
        5 => ['id' => 5, 'materials_id' => 5, 'name' => '灵纹布', 'quality' => 5, 'chip' => 0, 'sales' => 5000, 'desc' => '1'],
        6 => ['id' => 6, 'materials_id' => 6, 'name' => '西域丝绸', 'quality' => 5, 'chip' => 0, 'sales' => 5000, 'desc' => '1'],
        7 => ['id' => 7, 'materials_id' => 7, 'name' => '厚皮', 'quality' => 5, 'chip' => 0, 'sales' => 5000, 'desc' => '1'],
        8 => ['id' => 8, 'materials_id' => 8, 'name' => '黑羽毛', 'quality' => 5, 'chip' => 0, 'sales' => 5000, 'desc' => '1'],
        9 => ['id' => 9, 'materials_id' => 9, 'name' => '元晶石', 'quality' => 5, 'chip' => 0, 'sales' => 5000, 'desc' => '蕴藏着神秘的力量，是装备锻造和神铸的必备材料。'],
        10 => ['id' => 10, 'materials_id' => 10, 'name' => '海蓝水晶', 'quality' => 5, 'chip' => 0, 'sales' => 5000, 'desc' => '1'],
        11 => ['id' => 11, 'materials_id' => 11, 'name' => '灵玉', 'quality' => 5, 'chip' => 0, 'sales' => 5000, 'desc' => '1'],
        12 => ['id' => 12, 'materials_id' => 12, 'name' => '天机符咒', 'quality' => 5, 'chip' => 0, 'sales' => 5000, 'desc' => '符咒中似乎暗藏玄机，是锻造谋士装备的必备材料。'],
        13 => ['id' => 13, 'materials_id' => 13, 'name' => '精炼钢', 'quality' => 6, 'chip' => 0, 'sales' => 10000, 'desc' => '经过名匠三天三夜熔炼而成的钢材，可用于锻造猛将武器和防具。'],
        14 => ['id' => 14, 'materials_id' => 14, 'name' => '千年铁木', 'quality' => 6, 'chip' => 0, 'sales' => 10000, 'desc' => '取自千年铁树，木质坚硬如铁，可用于锻造谋士武器和兵符。'],
        15 => ['id' => 15, 'materials_id' => 15, 'name' => '赤金矿', 'quality' => 6, 'chip' => 0, 'sales' => 10000, 'desc' => '纯度极高的金矿，可用于锻造猛将武器和兵符。'],
        16 => ['id' => 16, 'materials_id' => 16, 'name' => '轻尘棉', 'quality' => 6, 'chip' => 0, 'sales' => 10000, 'desc' => '1'],
        17 => ['id' => 17, 'materials_id' => 17, 'name' => '飘风丝绸', 'quality' => 6, 'chip' => 0, 'sales' => 10000, 'desc' => '1'],
        18 => ['id' => 18, 'materials_id' => 18, 'name' => '神纹布', 'quality' => 6, 'chip' => 0, 'sales' => 10000, 'desc' => '天然纯手工！可用于锻造谋士铠甲和披风。'],
        19 => ['id' => 19, 'materials_id' => 19, 'name' => '兽王皮', 'quality' => 6, 'chip' => 0, 'sales' => 10000, 'desc' => '百兽之王的皮毛，可用于锻造猛将铠甲和披风。'],
        20 => ['id' => 20, 'materials_id' => 20, 'name' => '紫雀羽', 'quality' => 6, 'chip' => 0, 'sales' => 10000, 'desc' => '紫雀身上的羽毛，十分罕见，可用于锻造谋士武器和披风。'],
        21 => ['id' => 21, 'materials_id' => 21, 'name' => '红宝石', 'quality' => 6, 'chip' => 0, 'sales' => 10000, 'desc' => '通体红色的宝石，鲜艳、柔和而明亮，可用于锻造兵符、猛将披风和谋士铠甲。'],
        22 => ['id' => 22, 'materials_id' => 22, 'name' => '狂云水晶', 'quality' => 6, 'chip' => 0, 'sales' => 10000, 'desc' => '相传产自白云之巅，散发着一种狂厉之气，可用于锻造高级武器和装备。'],
        23 => ['id' => 23, 'materials_id' => 23, 'name' => '温螭玉', 'quality' => 6, 'chip' => 0, 'sales' => 10000, 'desc' => '玉乃石之美者，可温人心，是锻造兵符和防具的材料之一。'],
        24 => ['id' => 24, 'materials_id' => 24, 'name' => '钟仙符咒', 'quality' => 6, 'chip' => 0, 'sales' => 10000, 'desc' => '1'],
        25 => ['id' => 25, 'materials_id' => 25, 'name' => '陨星钢', 'quality' => 7, 'chip' => 0, 'sales' => 10000, 'desc' => '由来自天界的矿石熔炼而成，可用于锻造猛将橙色武器和防具。'],
        26 => ['id' => 26, 'materials_id' => 26, 'name' => '九转神木', 'quality' => 7, 'chip' => 0, 'sales' => 30000, 'desc' => '取自经过九世轮回的神树，可用于锻造橙色谋士武器和防具。'],
        27 => ['id' => 27, 'materials_id' => 27, 'name' => '灵铁矿', 'quality' => 7, 'chip' => 0, 'sales' => 30000, 'desc' => '灵力孕育而成的矿石，可用于锻造橙色猛将武器和兵符。'],
        28 => ['id' => 28, 'materials_id' => 28, 'name' => '蛮荒棉', 'quality' => 7, 'chip' => 0, 'sales' => 30000, 'desc' => '1'],
        29 => ['id' => 29, 'materials_id' => 29, 'name' => '混沌布', 'quality' => 7, 'chip' => 0, 'sales' => 30000, 'desc' => '1'],
        30 => ['id' => 30, 'materials_id' => 30, 'name' => '龙纹布', 'quality' => 7, 'chip' => 0, 'sales' => 30000, 'desc' => '相传是帝王所用之布，可用于锻造谋士橙色铠甲和披风。'],
        31 => ['id' => 31, 'materials_id' => 31, 'name' => '魔灵皮', 'quality' => 7, 'chip' => 0, 'sales' => 30000, 'desc' => '蕴含着魔灵之力，可用于锻造猛将橙色铠甲和披风。'],
        32 => ['id' => 32, 'materials_id' => 32, 'name' => '凤翎', 'quality' => 7, 'chip' => 0, 'sales' => 30000, 'desc' => '凤凰身上掉落的羽毛，极为罕见，可用于锻造谋士橙色武器和披风。'],
        33 => ['id' => 33, 'materials_id' => 33, 'name' => '橙宝石', 'quality' => 7, 'chip' => 0, 'sales' => 30000, 'desc' => '通体橙色的宝石，内有流光闪动，可用于锻造橙色兵符、猛将披风和谋士铠甲。'],
        34 => ['id' => 34, 'materials_id' => 34, 'name' => '湛蓝水晶', 'quality' => 7, 'chip' => 0, 'sales' => 30000, 'desc' => '1'],
        35 => ['id' => 35, 'materials_id' => 35, 'name' => '女娲玉', 'quality' => 7, 'chip' => 0, 'sales' => 30000, 'desc' => '女娲补天所剩的玉石，具有起死回生的力量，可用于锻造橙色兵符。'],
        36 => ['id' => 36, 'materials_id' => 36, 'name' => '钟馗符咒', 'quality' => 7, 'chip' => 0, 'sales' => 30000, 'desc' => '1'],
        37 => ['id' => 37, 'materials_id' => 37, 'name' => '麒麟血', 'quality' => 5, 'chip' => 0, 'sales' => 5000, 'desc' => '1'],
        38 => ['id' => 38, 'materials_id' => 38, 'name' => '青龙麟', 'quality' => 6, 'chip' => 0, 'sales' => 10000, 'desc' => '1'],
        39 => ['id' => 39, 'materials_id' => 39, 'name' => '圣兽心', 'quality' => 7, 'chip' => 0, 'sales' => 30000, 'desc' => '凝聚了远古圣兽千年的修行，锻造橙色武器和兵符必备的材料。'],
        40 => ['id' => 40, 'materials_id' => 40, 'name' => '冥牙', 'quality' => 5, 'chip' => 0, 'sales' => 5000, 'desc' => '魔物的巨大獠牙，锋利异常，是锻造猛将装备的必备材料。'],
        41 => ['id' => 41, 'materials_id' => 41, 'name' => '极星石', 'quality' => 5, 'chip' => 0, 'sales' => 5000, 'desc' => '1'],
        42 => ['id' => 42, 'materials_id' => 42, 'name' => '纯阳玉', 'quality' => 6, 'chip' => 0, 'sales' => 10000, 'desc' => '1'],
        43 => ['id' => 43, 'materials_id' => 43, 'name' => '地煞珠', 'quality' => 6, 'chip' => 0, 'sales' => 10000, 'desc' => '汲取天地之煞气所形成的珠子，是装备锻造和神铸的材料之一。'],
        44 => ['id' => 44, 'materials_id' => 44, 'name' => '月火石', 'quality' => 7, 'chip' => 0, 'sales' => 30000, 'desc' => '1'],
        45 => ['id' => 45, 'materials_id' => 45, 'name' => '星冰核', 'quality' => 7, 'chip' => 0, 'sales' => 30000, 'desc' => '潜藏着巨大的星蕴之力，是装备锻造和神铸的材料之一。'],
        46 => ['id' => 46, 'materials_id' => 46, 'name' => '漩涡精华', 'quality' => 5, 'chip' => 0, 'sales' => 5000, 'desc' => '1'],
        47 => ['id' => 47, 'materials_id' => 47, 'name' => '幻影精华', 'quality' => 6, 'chip' => 0, 'sales' => 10000, 'desc' => '1'],
        48 => ['id' => 48, 'materials_id' => 48, 'name' => '奇异精华', 'quality' => 7, 'chip' => 0, 'sales' => 30000, 'desc' => '1'],
        49 => ['id' => 49, 'materials_id' => 49, 'name' => '奔雷戟', 'quality' => 5, 'chip' => 1, 'sales' => 148, 'desc' => '大漠苍狼，团结、凶残'],
        50 => ['id' => 50, 'materials_id' => 50, 'name' => '奔雷甲', 'quality' => 5, 'chip' => 1, 'sales' => 149, 'desc' => '大漠苍狼，团结、凶残'],
        51 => ['id' => 51, 'materials_id' => 51, 'name' => '奔雷披风', 'quality' => 5, 'chip' => 1, 'sales' => 150, 'desc' => '大漠苍狼，团结、凶残'],
        52 => ['id' => 52, 'materials_id' => 52, 'name' => '奔雷符', 'quality' => 5, 'chip' => 1, 'sales' => 151, 'desc' => '大漠苍狼，团结、凶残'],
        53 => ['id' => 53, 'materials_id' => 53, 'name' => '七星羽扇', 'quality' => 5, 'chip' => 1, 'sales' => 152, 'desc' => '大漠苍狼，团结、凶残'],
        54 => ['id' => 54, 'materials_id' => 54, 'name' => '七星衣', 'quality' => 5, 'chip' => 1, 'sales' => 153, 'desc' => '大漠苍狼，团结、凶残'],
        55 => ['id' => 55, 'materials_id' => 55, 'name' => '七星披肩', 'quality' => 5, 'chip' => 1, 'sales' => 154, 'desc' => '大漠苍狼，团结、凶残'],
        56 => ['id' => 56, 'materials_id' => 56, 'name' => '七星算盘', 'quality' => 5, 'chip' => 1, 'sales' => 155, 'desc' => '大漠苍狼，团结、凶残'],
        57 => ['id' => 57, 'materials_id' => 57, 'name' => '青龙枪', 'quality' => 5, 'chip' => 1, 'sales' => 156, 'desc' => '大漠苍狼，团结、凶残'],
        58 => ['id' => 58, 'materials_id' => 58, 'name' => '青龙甲', 'quality' => 5, 'chip' => 1, 'sales' => 157, 'desc' => '大漠苍狼，团结、凶残'],
        59 => ['id' => 59, 'materials_id' => 59, 'name' => '青龙披风', 'quality' => 5, 'chip' => 1, 'sales' => 158, 'desc' => '大漠苍狼，团结、凶残'],
        60 => ['id' => 60, 'materials_id' => 60, 'name' => '青龙印', 'quality' => 5, 'chip' => 1, 'sales' => 159, 'desc' => '大漠苍狼，团结、凶残'],
        61 => ['id' => 61, 'materials_id' => 61, 'name' => '朱雀琴', 'quality' => 5, 'chip' => 1, 'sales' => 160, 'desc' => '大漠苍狼，团结、凶残'],
        62 => ['id' => 62, 'materials_id' => 62, 'name' => '朱雀衣', 'quality' => 5, 'chip' => 1, 'sales' => 161, 'desc' => '大漠苍狼，团结、凶残'],
        63 => ['id' => 63, 'materials_id' => 63, 'name' => '朱雀披肩', 'quality' => 5, 'chip' => 1, 'sales' => 162, 'desc' => '大漠苍狼，团结、凶残'],
        64 => ['id' => 64, 'materials_id' => 64, 'name' => '朱雀印', 'quality' => 5, 'chip' => 1, 'sales' => 163, 'desc' => '大漠苍狼，团结、凶残'],
        65 => ['id' => 65, 'materials_id' => 65, 'name' => '白虎刀', 'quality' => 5, 'chip' => 1, 'sales' => 164, 'desc' => '大漠苍狼，团结、凶残'],
        66 => ['id' => 66, 'materials_id' => 66, 'name' => '白虎甲', 'quality' => 5, 'chip' => 1, 'sales' => 165, 'desc' => '大漠苍狼，团结、凶残'],
        67 => ['id' => 67, 'materials_id' => 67, 'name' => '白虎披风', 'quality' => 5, 'chip' => 1, 'sales' => 166, 'desc' => '大漠苍狼，团结、凶残'],
        68 => ['id' => 68, 'materials_id' => 68, 'name' => '白虎印', 'quality' => 5, 'chip' => 1, 'sales' => 167, 'desc' => '大漠苍狼，团结、凶残'],
        69 => ['id' => 69, 'materials_id' => 69, 'name' => '玄武杖', 'quality' => 5, 'chip' => 1, 'sales' => 168, 'desc' => '大漠苍狼，团结、凶残'],
        70 => ['id' => 70, 'materials_id' => 70, 'name' => '玄武衣', 'quality' => 5, 'chip' => 1, 'sales' => 169, 'desc' => '大漠苍狼，团结、凶残'],
        71 => ['id' => 71, 'materials_id' => 71, 'name' => '玄武披风', 'quality' => 5, 'chip' => 1, 'sales' => 170, 'desc' => '大漠苍狼，团结、凶残'],
        72 => ['id' => 72, 'materials_id' => 72, 'name' => '玄武印', 'quality' => 5, 'chip' => 1, 'sales' => 171, 'desc' => '大漠苍狼，团结、凶残'],
        73 => ['id' => 73, 'materials_id' => 73, 'name' => '四圣剑', 'quality' => 5, 'chip' => 1, 'sales' => 172, 'desc' => '大漠苍狼，团结、凶残'],
        74 => ['id' => 74, 'materials_id' => 74, 'name' => '四圣甲', 'quality' => 5, 'chip' => 1, 'sales' => 173, 'desc' => '大漠苍狼，团结、凶残'],
        75 => ['id' => 75, 'materials_id' => 75, 'name' => '四圣披风', 'quality' => 5, 'chip' => 1, 'sales' => 174, 'desc' => '大漠苍狼，团结、凶残'],
        76 => ['id' => 76, 'materials_id' => 76, 'name' => '四圣武印', 'quality' => 5, 'chip' => 1, 'sales' => 175, 'desc' => '大漠苍狼，团结、凶残'],
        77 => ['id' => 77, 'materials_id' => 77, 'name' => '四圣符', 'quality' => 5, 'chip' => 1, 'sales' => 176, 'desc' => '大漠苍狼，团结、凶残'],
        78 => ['id' => 78, 'materials_id' => 78, 'name' => '四圣衣', 'quality' => 5, 'chip' => 1, 'sales' => 177, 'desc' => '大漠苍狼，团结、凶残'],
        79 => ['id' => 79, 'materials_id' => 79, 'name' => '四圣披肩', 'quality' => 5, 'chip' => 1, 'sales' => 178, 'desc' => '大漠苍狼，团结、凶残'],
        80 => ['id' => 80, 'materials_id' => 80, 'name' => '四圣智印', 'quality' => 5, 'chip' => 1, 'sales' => 179, 'desc' => '大漠苍狼，团结、凶残'],
        81 => ['id' => 81, 'materials_id' => 81, 'name' => '斩仙刀', 'quality' => 5, 'chip' => 1, 'sales' => 180, 'desc' => '大漠苍狼，团结、凶残'],
        82 => ['id' => 82, 'materials_id' => 82, 'name' => '斩仙甲', 'quality' => 5, 'chip' => 1, 'sales' => 181, 'desc' => '大漠苍狼，团结、凶残'],
        83 => ['id' => 83, 'materials_id' => 83, 'name' => '斩仙披风', 'quality' => 5, 'chip' => 1, 'sales' => 182, 'desc' => '大漠苍狼，团结、凶残'],
        84 => ['id' => 84, 'materials_id' => 84, 'name' => '斩仙链', 'quality' => 5, 'chip' => 1, 'sales' => 183, 'desc' => '大漠苍狼，团结、凶残'],
        85 => ['id' => 85, 'materials_id' => 85, 'name' => '诛邪剑', 'quality' => 5, 'chip' => 1, 'sales' => 184, 'desc' => '大漠苍狼，团结、凶残'],
        86 => ['id' => 86, 'materials_id' => 86, 'name' => '诛邪衣', 'quality' => 5, 'chip' => 1, 'sales' => 185, 'desc' => '大漠苍狼，团结、凶残'],
        87 => ['id' => 87, 'materials_id' => 87, 'name' => '诛邪袍', 'quality' => 5, 'chip' => 1, 'sales' => 186, 'desc' => '大漠苍狼，团结、凶残'],
        88 => ['id' => 88, 'materials_id' => 88, 'name' => '诛邪印', 'quality' => 5, 'chip' => 1, 'sales' => 187, 'desc' => '大漠苍狼，团结、凶残'],
        89 => ['id' => 89, 'materials_id' => 89, 'name' => '裂天枪', 'quality' => 5, 'chip' => 1, 'sales' => 188, 'desc' => '大漠苍狼，团结、凶残'],
        90 => ['id' => 90, 'materials_id' => 90, 'name' => '裂天甲', 'quality' => 5, 'chip' => 1, 'sales' => 189, 'desc' => '大漠苍狼，团结、凶残'],
        91 => ['id' => 91, 'materials_id' => 91, 'name' => '裂天披风', 'quality' => 5, 'chip' => 1, 'sales' => 190, 'desc' => '大漠苍狼，团结、凶残'],
        92 => ['id' => 92, 'materials_id' => 92, 'name' => '裂天轮', 'quality' => 5, 'chip' => 1, 'sales' => 191, 'desc' => '大漠苍狼，团结、凶残'],
        93 => ['id' => 93, 'materials_id' => 93, 'name' => '卧龙扇', 'quality' => 5, 'chip' => 1, 'sales' => 192, 'desc' => '大漠苍狼，团结、凶残'],
        94 => ['id' => 94, 'materials_id' => 94, 'name' => '卧龙衣', 'quality' => 5, 'chip' => 1, 'sales' => 193, 'desc' => '大漠苍狼，团结、凶残'],
        95 => ['id' => 95, 'materials_id' => 95, 'name' => '卧龙袍', 'quality' => 5, 'chip' => 1, 'sales' => 194, 'desc' => '大漠苍狼，团结、凶残'],
        96 => ['id' => 96, 'materials_id' => 96, 'name' => '卧龙吟', 'quality' => 5, 'chip' => 1, 'sales' => 195, 'desc' => '大漠苍狼，团结、凶残'],
        97 => ['id' => 97, 'materials_id' => 97, 'name' => '破军戟', 'quality' => 5, 'chip' => 1, 'sales' => 196, 'desc' => '大漠苍狼，团结、凶残'],
        98 => ['id' => 98, 'materials_id' => 98, 'name' => '破军甲', 'quality' => 5, 'chip' => 1, 'sales' => 197, 'desc' => '大漠苍狼，团结、凶残'],
        99 => ['id' => 99, 'materials_id' => 99, 'name' => '破军披风', 'quality' => 5, 'chip' => 1, 'sales' => 198, 'desc' => '大漠苍狼，团结、凶残'],
        100 => ['id' => 100, 'materials_id' => 100, 'name' => '破军印', 'quality' => 5, 'chip' => 1, 'sales' => 199, 'desc' => '大漠苍狼，团结、凶残'],
        101 => ['id' => 101, 'materials_id' => 101, 'name' => '贪狼杖', 'quality' => 5, 'chip' => 1, 'sales' => 200, 'desc' => '大漠苍狼，团结、凶残'],
        102 => ['id' => 102, 'materials_id' => 102, 'name' => '贪狼衣', 'quality' => 5, 'chip' => 1, 'sales' => 201, 'desc' => '大漠苍狼，团结、凶残'],
        103 => ['id' => 103, 'materials_id' => 103, 'name' => '贪狼袍', 'quality' => 5, 'chip' => 1, 'sales' => 202, 'desc' => '大漠苍狼，团结、凶残'],
        104 => ['id' => 104, 'materials_id' => 104, 'name' => '贪狼印', 'quality' => 5, 'chip' => 1, 'sales' => 203, 'desc' => '大漠苍狼，团结、凶残'],
    ];
}
