<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 等级礼包（旧版 zc_sanguo_level_gift）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class LevelGift
{
    public const string TABLE = 'zc_sanguo_level_gift';

    /**
     * 按主键 id 索引，共 93 行。字段：
     *   - id (int) 礼包id
     *   - title (varchar) 任务简介
     *   - description (varchar) 新功能预知
     *   - cond_type (tinyint) 完成条件类型
     *   - cond_value (int) 条件参数
     *   - type (tinyint) 奖励类型(1 金币; 2 银币; 3 军令; 4 蓝翡翠; 5 金翡翠; 6 紫翡翠)
     *   - num (int) 奖励参数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'title' => '击败“周仓”', 'description' => '16级功能：征收、装备强化', 'cond_type' => 1, 'cond_value' => 10, 'type' => 2, 'num' => 5000],
        2 => ['id' => 2, 'title' => '击败“张梁”', 'description' => '21级功能：属性培养', 'cond_type' => 1, 'cond_value' => 20, 'type' => 2, 'num' => 10000],
        3 => ['id' => 3, 'title' => '击败“杜远”', 'description' => '21级功能：属性培养', 'cond_type' => 1, 'cond_value' => 24, 'type' => 2, 'num' => 15000],
        4 => ['id' => 4, 'title' => '击败“彭脱”', 'description' => '21级功能：属性培养', 'cond_type' => 1, 'cond_value' => 27, 'type' => 2, 'num' => 20000],
        5 => ['id' => 5, 'title' => '击败“张宝”', 'description' => '26级功能：国家、演武、银矿区、藏宝图', 'cond_type' => 1, 'cond_value' => 30, 'type' => 1, 'num' => 10],
        6 => ['id' => 6, 'title' => '击败“马元义”', 'description' => '26级功能：国家、演武、银矿区、藏宝图', 'cond_type' => 1, 'cond_value' => 34, 'type' => 2, 'num' => 20000],
        7 => ['id' => 7, 'title' => '击败“张牛角”', 'description' => '26级功能：国家、演武、银矿区、藏宝图', 'cond_type' => 1, 'cond_value' => 37, 'type' => 2, 'num' => 20000],
        8 => ['id' => 8, 'title' => '击败“张燕”', 'description' => '26级功能：国家、演武、银矿区、藏宝图', 'cond_type' => 1, 'cond_value' => 38, 'type' => 1, 'num' => 10],
        9 => ['id' => 9, 'title' => '击败“管亥”', 'description' => '26级功能：国家、演武、银矿区、藏宝图', 'cond_type' => 1, 'cond_value' => 39, 'type' => 1, 'num' => 10],
        10 => ['id' => 10, 'title' => '击败“张角”', 'description' => '31级功能：酒馆、贸易中心、团队副本', 'cond_type' => 1, 'cond_value' => 40, 'type' => 1, 'num' => 20],
        11 => ['id' => 11, 'title' => '击败“乌巢文官”', 'description' => '31级功能：酒馆、贸易中心、团队副本', 'cond_type' => 1, 'cond_value' => 44, 'type' => 2, 'num' => 20000],
        12 => ['id' => 12, 'title' => '击败“吕威璜”', 'description' => '31级功能：酒馆、贸易中心、团队副本', 'cond_type' => 1, 'cond_value' => 47, 'type' => 2, 'num' => 20000],
        13 => ['id' => 13, 'title' => '击败“颜良”', 'description' => '36级功能：日常活动之金银洞', 'cond_type' => 1, 'cond_value' => 50, 'type' => 1, 'num' => 20],
        14 => ['id' => 14, 'title' => '击败“幽州悍卫”', 'description' => '36级功能：日常活动之金银洞', 'cond_type' => 1, 'cond_value' => 54, 'type' => 2, 'num' => 20000],
        15 => ['id' => 15, 'title' => '击败“马延”', 'description' => '36级功能：日常活动之金银洞', 'cond_type' => 1, 'cond_value' => 57, 'type' => 2, 'num' => 20000],
        16 => ['id' => 16, 'title' => '击败“文丑”', 'description' => '41级功能：每日对酒、日常任务', 'cond_type' => 1, 'cond_value' => 60, 'type' => 1, 'num' => 20],
        17 => ['id' => 17, 'title' => '击败“冀州锐士”', 'description' => '41级功能：每日对酒、日常任务', 'cond_type' => 1, 'cond_value' => 64, 'type' => 3, 'num' => 5],
        18 => ['id' => 18, 'title' => '击败“吕翔”', 'description' => '41级功能：每日对酒、日常任务', 'cond_type' => 1, 'cond_value' => 67, 'type' => 3, 'num' => 5],
        19 => ['id' => 19, 'title' => '击败“田丰”', 'description' => '46级功能：日常活动之世界BOSS', 'cond_type' => 1, 'cond_value' => 70, 'type' => 1, 'num' => 20],
        20 => ['id' => 20, 'title' => '击败“逢纪”', 'description' => '46级功能：日常活动之世界BOSS', 'cond_type' => 1, 'cond_value' => 74, 'type' => 2, 'num' => 20000],
        21 => ['id' => 21, 'title' => '击败“袁尚”', 'description' => '46级功能：日常活动之世界BOSS', 'cond_type' => 1, 'cond_value' => 77, 'type' => 2, 'num' => 20000],
        22 => ['id' => 22, 'title' => '击败“袁绍”', 'description' => '51级功能：将魂星路、将魂、海外贸易', 'cond_type' => 1, 'cond_value' => 80, 'type' => 1, 'num' => 30],
        23 => ['id' => 23, 'title' => '击败“卑衍”', 'description' => '51级功能：将魂星路、将魂、海外贸易', 'cond_type' => 1, 'cond_value' => 84, 'type' => 2, 'num' => 20000],
        24 => ['id' => 24, 'title' => '击败“公孙渊”', 'description' => '51级功能：将魂星路、将魂、海外贸易', 'cond_type' => 1, 'cond_value' => 87, 'type' => 2, 'num' => 20000],
        25 => ['id' => 25, 'title' => '击败“公孙度”', 'description' => '61级功能：每日通商、跨服远征', 'cond_type' => 1, 'cond_value' => 90, 'type' => 1, 'num' => 30],
        26 => ['id' => 26, 'title' => '击败“乌恒骠骑”', 'description' => '61级功能：每日通商、跨服远征', 'cond_type' => 1, 'cond_value' => 94, 'type' => 2, 'num' => 20000],
        27 => ['id' => 27, 'title' => '击败“王门”', 'description' => '61级功能：每日通商、跨服远征', 'cond_type' => 1, 'cond_value' => 97, 'type' => 2, 'num' => 20000],
        28 => ['id' => 28, 'title' => '击败“田予”', 'description' => '61级功能：每日通商、跨服远征', 'cond_type' => 1, 'cond_value' => 100, 'type' => 1, 'num' => 30],
        29 => ['id' => 29, 'title' => '击败“白马精锐”', 'description' => '61级功能：每日通商、跨服远征', 'cond_type' => 1, 'cond_value' => 104, 'type' => 2, 'num' => 20000],
        30 => ['id' => 30, 'title' => '击败“公孙范”', 'description' => '61级功能：每日通商、跨服远征', 'cond_type' => 1, 'cond_value' => 107, 'type' => 2, 'num' => 20000],
        31 => ['id' => 31, 'title' => '击败“公孙瓒”', 'description' => '61级功能：每日通商、跨服远征', 'cond_type' => 1, 'cond_value' => 110, 'type' => 1, 'num' => 30],
        32 => ['id' => 32, 'title' => '击败“华雄”', 'description' => '71级功能：神将之门', 'cond_type' => 1, 'cond_value' => 120, 'type' => 4, 'num' => 15],
        33 => ['id' => 33, 'title' => '击败“貂蝉”', 'description' => '71级功能：神将之门', 'cond_type' => 1, 'cond_value' => 130, 'type' => 4, 'num' => 15],
        34 => ['id' => 34, 'title' => '击败“张绣”', 'description' => '71级功能：神将之门', 'cond_type' => 1, 'cond_value' => 137, 'type' => 4, 'num' => 15],
        35 => ['id' => 35, 'title' => '击败“李儒”', 'description' => '91级功能：名将成就、主公天赋', 'cond_type' => 1, 'cond_value' => 147, 'type' => 4, 'num' => 15],
        36 => ['id' => 36, 'title' => '击败“徐荣”', 'description' => '91级功能：名将成就、主公天赋', 'cond_type' => 1, 'cond_value' => 157, 'type' => 4, 'num' => 15],
        37 => ['id' => 37, 'title' => '击败“董卓”', 'description' => '91级功能：名将成就、主公天赋', 'cond_type' => 1, 'cond_value' => 167, 'type' => 4, 'num' => 20],
        38 => ['id' => 38, 'title' => '击败“马岱”', 'description' => '91级功能：名将成就、主公天赋', 'cond_type' => 1, 'cond_value' => 177, 'type' => 5, 'num' => 20],
        39 => ['id' => 39, 'title' => '击败“庞德”', 'description' => '91级功能：名将成就、主公天赋', 'cond_type' => 1, 'cond_value' => 187, 'type' => 5, 'num' => 20],
        40 => ['id' => 40, 'title' => '击败“韩遂”', 'description' => '91级功能：名将成就、主公天赋', 'cond_type' => 1, 'cond_value' => 197, 'type' => 5, 'num' => 20],
        41 => ['id' => 41, 'title' => '击败“马腾”', 'description' => '91级功能：名将成就、主公天赋', 'cond_type' => 1, 'cond_value' => 207, 'type' => 5, 'num' => 20],
        42 => ['id' => 42, 'title' => '击败“文聘”', 'description' => '101级功能：副本英雄王座', 'cond_type' => 1, 'cond_value' => 217, 'type' => 3, 'num' => 50],
        43 => ['id' => 43, 'title' => '击败“蒯越”', 'description' => '101级功能：副本英雄王座', 'cond_type' => 1, 'cond_value' => 227, 'type' => 3, 'num' => 50],
        44 => ['id' => 44, 'title' => '击败“黄忠”', 'description' => '101级功能：副本英雄王座', 'cond_type' => 1, 'cond_value' => 237, 'type' => 3, 'num' => 50],
        45 => ['id' => 45, 'title' => '击败“刘表”', 'description' => '101级功能：副本英雄王座', 'cond_type' => 1, 'cond_value' => 247, 'type' => 3, 'num' => 50],
        46 => ['id' => 46, 'title' => '击败“纪灵”', 'description' => '101级功能：副本英雄王座', 'cond_type' => 1, 'cond_value' => 257, 'type' => 3, 'num' => 50],
        47 => ['id' => 47, 'title' => '击败“孔融”', 'description' => '101级功能：副本英雄王座', 'cond_type' => 1, 'cond_value' => 267, 'type' => 3, 'num' => 50],
        48 => ['id' => 48, 'title' => '击败“孙坚”', 'description' => '101级功能：副本英雄王座', 'cond_type' => 1, 'cond_value' => 277, 'type' => 3, 'num' => 50],
        49 => ['id' => 49, 'title' => '击败“袁术”', 'description' => '101级功能：副本英雄王座', 'cond_type' => 1, 'cond_value' => 287, 'type' => 3, 'num' => 50],
        50 => ['id' => 50, 'title' => '击败“张辽”', 'description' => '101级功能：副本英雄王座', 'cond_type' => 1, 'cond_value' => 297, 'type' => 6, 'num' => 20],
        51 => ['id' => 51, 'title' => '击败“陈宫”', 'description' => '101级功能：副本英雄王座', 'cond_type' => 1, 'cond_value' => 307, 'type' => 6, 'num' => 20],
        52 => ['id' => 52, 'title' => '击败“高顺”', 'description' => '101级功能：副本英雄王座', 'cond_type' => 1, 'cond_value' => 317, 'type' => 6, 'num' => 20],
        53 => ['id' => 53, 'title' => '击败“吕布”', 'description' => '101级功能：副本英雄王座', 'cond_type' => 1, 'cond_value' => 327, 'type' => 6, 'num' => 20],
        54 => ['id' => 54, 'title' => '击败“贾诩”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 337, 'type' => 1, 'num' => 500],
        55 => ['id' => 55, 'title' => '击败“荀彧”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 347, 'type' => 1, 'num' => 500],
        56 => ['id' => 56, 'title' => '击败“典韦”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 357, 'type' => 1, 'num' => 500],
        57 => ['id' => 57, 'title' => '击败“夏侯惇”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 367, 'type' => 1, 'num' => 500],
        58 => ['id' => 58, 'title' => '击败“曹仁”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 377, 'type' => 1, 'num' => 500],
        59 => ['id' => 59, 'title' => '击败“司马炎”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 387, 'type' => 1, 'num' => 500],
        60 => ['id' => 60, 'title' => '击败“曹操”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 397, 'type' => 1, 'num' => 1000],
        61 => ['id' => 61, 'title' => '击败“张鲁”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 407, 'type' => 1, 'num' => 1000],
        62 => ['id' => 62, 'title' => '击败“刘璋”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 417, 'type' => 1, 'num' => 1000],
        63 => ['id' => 63, 'title' => '击败“姜维”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 427, 'type' => 1, 'num' => 1000],
        64 => ['id' => 64, 'title' => '击败“关羽”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 437, 'type' => 1, 'num' => 1000],
        65 => ['id' => 65, 'title' => '击败“张飞”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 447, 'type' => 1, 'num' => 1000],
        66 => ['id' => 66, 'title' => '击败“诸葛亮”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 457, 'type' => 1, 'num' => 1000],
        67 => ['id' => 67, 'title' => '击败“刘备”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 467, 'type' => 1, 'num' => 2000],
        68 => ['id' => 68, 'title' => '击败“甘宁”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 477, 'type' => 6, 'num' => 50],
        69 => ['id' => 69, 'title' => '击败“张昭”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 487, 'type' => 6, 'num' => 50],
        70 => ['id' => 70, 'title' => '击败“陆逊”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 497, 'type' => 6, 'num' => 50],
        71 => ['id' => 71, 'title' => '击败“周瑜”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 507, 'type' => 6, 'num' => 50],
        72 => ['id' => 72, 'title' => '击败“孙坚”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 517, 'type' => 6, 'num' => 50],
        73 => ['id' => 73, 'title' => '击败“孙策”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 527, 'type' => 6, 'num' => 50],
        74 => ['id' => 74, 'title' => '击败“孙权”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 537, 'type' => 6, 'num' => 100],
        75 => ['id' => 75, 'title' => '击败“带来洞主”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 547, 'type' => 6, 'num' => 100],
        76 => ['id' => 76, 'title' => '击败“朵思大王”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 557, 'type' => 6, 'num' => 100],
        77 => ['id' => 77, 'title' => '击败“木鹿大王”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 567, 'type' => 6, 'num' => 100],
        78 => ['id' => 78, 'title' => '击败“祝融夫人”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 577, 'type' => 6, 'num' => 100],
        79 => ['id' => 79, 'title' => '击败“兀突骨”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 587, 'type' => 6, 'num' => 100],
        80 => ['id' => 80, 'title' => '击败“孟获”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 597, 'type' => 6, 'num' => 200],
        81 => ['id' => 81, 'title' => '击败“匈奴大酋长”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 607, 'type' => 1, 'num' => 5000],
        82 => ['id' => 82, 'title' => '击败“匈奴左贤王”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 617, 'type' => 1, 'num' => 5000],
        83 => ['id' => 83, 'title' => '击败“匈奴右贤王”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 627, 'type' => 1, 'num' => 5000],
        84 => ['id' => 84, 'title' => '击败“匈奴大单于”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 637, 'type' => 1, 'num' => 5000],
        85 => ['id' => 85, 'title' => '击败“匈奴大单于”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 647, 'type' => 1, 'num' => 5000],
        86 => ['id' => 86, 'title' => '击败“匈奴大汗”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 657, 'type' => 1, 'num' => 5000],
        87 => ['id' => 87, 'title' => '击败“匈奴大汗”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 667, 'type' => 1, 'num' => 5000],
        88 => ['id' => 88, 'title' => '击败“大帝阿提拉”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 677, 'type' => 1, 'num' => 10000],
        89 => ['id' => 89, 'title' => '击败“曹操”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 687, 'type' => 1, 'num' => 5000],
        90 => ['id' => 90, 'title' => '击败“袁绍”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 697, 'type' => 1, 'num' => 5000],
        91 => ['id' => 91, 'title' => '击败“马超”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 707, 'type' => 1, 'num' => 5000],
        92 => ['id' => 92, 'title' => '击败“陆逊”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 717, 'type' => 1, 'num' => 5000],
        93 => ['id' => 93, 'title' => '击败“吕布”', 'description' => '150级功能：威震天下、天下史诗', 'cond_type' => 1, 'cond_value' => 727, 'type' => 1, 'num' => 10000],
    ];
}
