<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 日常任务表（旧版 zc_sanguo_task）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Task
{
    public const string TABLE = 'zc_sanguo_task';

    /**
     * 按主键 id 索引，共 128 行。字段：
     *   - id (int) 任务id
     *   - title (varchar) 任务标题
     *   - description (varchar) 任务介绍
     *   - type (tinyint) 任务条件类型
     *   - value (tinyint) 任务条件参数
     *   - star (tinyint) 任务星级
     *   - silver (int) 任务奖励银币系数（等级*系数）
     *   - equip_type (tinyint) 任务奖励装备类型（1整件2碎片）
     *   - equip_id (mediumint) 装备id
     *   - equip_num (int) 装备/碎片数量
     *   - open_level (int) 功能开启等级
     */
    public const array ROWS = [
        1 => ['id' => 1, 'title' => '武将突飞', 'description' => '单日对任意武将突飞1次', 'type' => 1, 'value' => 1, 'star' => 1, 'silver' => 500, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        2 => ['id' => 2, 'title' => '银币征收', 'description' => '单日征收或强征1次', 'type' => 2, 'value' => 1, 'star' => 1, 'silver' => 500, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        3 => ['id' => 3, 'title' => '银矿抢占', 'description' => '单日抢夺或占领银矿1次', 'type' => 3, 'value' => 1, 'star' => 1, 'silver' => 500, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        4 => ['id' => 4, 'title' => '每日通商', 'description' => '单日完成通商1次', 'type' => 4, 'value' => 1, 'star' => 1, 'silver' => 500, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        5 => ['id' => 5, 'title' => '元宝消费', 'description' => '单日任意元宝消费1次', 'type' => 5, 'value' => 1, 'star' => 1, 'silver' => 500, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        6 => ['id' => 6, 'title' => '关卡挑战', 'description' => '单日挑战任意关卡敌军1次', 'type' => 6, 'value' => 1, 'star' => 1, 'silver' => 500, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        7 => ['id' => 7, 'title' => '属性培养', 'description' => '单日完成属性培养1次', 'type' => 7, 'value' => 1, 'star' => 1, 'silver' => 500, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        8 => ['id' => 8, 'title' => '装备升级', 'description' => '单日完成装备升级1次', 'type' => 8, 'value' => 1, 'star' => 1, 'silver' => 500, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        9 => ['id' => 9, 'title' => '金银守卫', 'description' => '单日金银洞副本挑战1次', 'type' => 9, 'value' => 1, 'star' => 1, 'silver' => 500, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        10 => ['id' => 10, 'title' => '演武竞技', 'description' => '单日演武场竞技1次', 'type' => 10, 'value' => 1, 'star' => 1, 'silver' => 500, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        11 => ['id' => 11, 'title' => '宝石贸易', 'description' => '单日贸易中心贸易1次', 'type' => 11, 'value' => 1, 'star' => 1, 'silver' => 500, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        12 => ['id' => 12, 'title' => '武将突飞', 'description' => '单日对任意武将突飞3次', 'type' => 1, 'value' => 3, 'star' => 2, 'silver' => 700, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        13 => ['id' => 13, 'title' => '银币征收', 'description' => '单日征收或强征3次', 'type' => 2, 'value' => 3, 'star' => 2, 'silver' => 700, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        14 => ['id' => 14, 'title' => '银矿抢占', 'description' => '单日抢夺或占领银矿3次', 'type' => 3, 'value' => 3, 'star' => 2, 'silver' => 700, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        15 => ['id' => 15, 'title' => '每日通商', 'description' => '单日完成通商3次', 'type' => 4, 'value' => 3, 'star' => 2, 'silver' => 700, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        16 => ['id' => 16, 'title' => '元宝消费', 'description' => '单日任意元宝消费3次', 'type' => 5, 'value' => 3, 'star' => 2, 'silver' => 700, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        17 => ['id' => 17, 'title' => '关卡挑战', 'description' => '单日挑战任意关卡敌军3次', 'type' => 6, 'value' => 3, 'star' => 2, 'silver' => 700, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        18 => ['id' => 18, 'title' => '属性培养', 'description' => '单日完成属性培养3次', 'type' => 7, 'value' => 3, 'star' => 2, 'silver' => 700, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        19 => ['id' => 19, 'title' => '装备升级', 'description' => '单日完成装备升级3次', 'type' => 8, 'value' => 3, 'star' => 2, 'silver' => 700, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        20 => ['id' => 20, 'title' => '金银守卫', 'description' => '单日金银洞副本挑战3次', 'type' => 9, 'value' => 3, 'star' => 2, 'silver' => 700, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        21 => ['id' => 21, 'title' => '演武竞技', 'description' => '单日演武场竞技3次', 'type' => 10, 'value' => 3, 'star' => 2, 'silver' => 700, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        22 => ['id' => 22, 'title' => '宝石贸易', 'description' => '单日贸易中心贸易3次', 'type' => 11, 'value' => 3, 'star' => 2, 'silver' => 700, 'equip_type' => 0, 'equip_id' => 0, 'equip_num' => 0, 'open_level' => 0],
        23 => ['id' => 23, 'title' => '武将突飞', 'description' => '单日对任意武将突飞5次', 'type' => 1, 'value' => 5, 'star' => 3, 'silver' => 900, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 1, 'open_level' => 0],
        24 => ['id' => 24, 'title' => '银币征收', 'description' => '单日征收或强征5次', 'type' => 2, 'value' => 5, 'star' => 3, 'silver' => 900, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 1, 'open_level' => 0],
        25 => ['id' => 25, 'title' => '银矿抢占', 'description' => '单日抢夺或占领银矿5次', 'type' => 3, 'value' => 5, 'star' => 3, 'silver' => 900, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 1, 'open_level' => 0],
        26 => ['id' => 26, 'title' => '每日通商', 'description' => '单日完成通商5次', 'type' => 4, 'value' => 5, 'star' => 3, 'silver' => 900, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 1, 'open_level' => 0],
        27 => ['id' => 27, 'title' => '元宝消费', 'description' => '单日任意元宝消费5次', 'type' => 5, 'value' => 5, 'star' => 3, 'silver' => 900, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 1, 'open_level' => 0],
        28 => ['id' => 28, 'title' => '关卡挑战', 'description' => '单日挑战任意关卡敌军5次', 'type' => 6, 'value' => 5, 'star' => 3, 'silver' => 900, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 1, 'open_level' => 0],
        29 => ['id' => 29, 'title' => '属性培养', 'description' => '单日完成属性培养5次', 'type' => 7, 'value' => 5, 'star' => 3, 'silver' => 900, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 1, 'open_level' => 0],
        30 => ['id' => 30, 'title' => '装备升级', 'description' => '单日完成装备升级5次', 'type' => 8, 'value' => 5, 'star' => 3, 'silver' => 900, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 1, 'open_level' => 0],
        31 => ['id' => 31, 'title' => '金银守卫', 'description' => '单日金银洞副本挑战5次', 'type' => 9, 'value' => 5, 'star' => 3, 'silver' => 900, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 1, 'open_level' => 0],
        32 => ['id' => 32, 'title' => '演武竞技', 'description' => '单日演武场竞技5次', 'type' => 10, 'value' => 5, 'star' => 3, 'silver' => 900, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 1, 'open_level' => 0],
        33 => ['id' => 33, 'title' => '宝石贸易', 'description' => '单日贸易中心贸易5次', 'type' => 11, 'value' => 5, 'star' => 3, 'silver' => 900, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 1, 'open_level' => 0],
        34 => ['id' => 34, 'title' => '武将突飞', 'description' => '单日对任意武将突飞7次', 'type' => 1, 'value' => 7, 'star' => 4, 'silver' => 1100, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 1, 'open_level' => 0],
        35 => ['id' => 35, 'title' => '银币征收', 'description' => '单日征收或强征7次', 'type' => 2, 'value' => 7, 'star' => 4, 'silver' => 1100, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 1, 'open_level' => 0],
        36 => ['id' => 36, 'title' => '银矿抢占', 'description' => '单日抢夺或占领银矿7次', 'type' => 3, 'value' => 7, 'star' => 4, 'silver' => 1100, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 1, 'open_level' => 0],
        37 => ['id' => 37, 'title' => '每日通商', 'description' => '单日完成通商7次', 'type' => 4, 'value' => 7, 'star' => 4, 'silver' => 1100, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 1, 'open_level' => 0],
        38 => ['id' => 38, 'title' => '元宝消费', 'description' => '单日任意元宝消费7次', 'type' => 5, 'value' => 7, 'star' => 4, 'silver' => 1100, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 1, 'open_level' => 0],
        39 => ['id' => 39, 'title' => '关卡挑战', 'description' => '单日挑战任意关卡敌军7次', 'type' => 6, 'value' => 7, 'star' => 4, 'silver' => 1100, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 1, 'open_level' => 0],
        40 => ['id' => 40, 'title' => '属性培养', 'description' => '单日完成属性培养7次', 'type' => 7, 'value' => 7, 'star' => 4, 'silver' => 1100, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 1, 'open_level' => 0],
        41 => ['id' => 41, 'title' => '装备升级', 'description' => '单日完成装备升级7次', 'type' => 8, 'value' => 7, 'star' => 4, 'silver' => 1100, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 1, 'open_level' => 0],
        42 => ['id' => 42, 'title' => '金银守卫', 'description' => '单日金银洞副本挑战7次', 'type' => 9, 'value' => 7, 'star' => 4, 'silver' => 1100, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 1, 'open_level' => 0],
        43 => ['id' => 43, 'title' => '演武竞技', 'description' => '单日演武场竞技7次', 'type' => 10, 'value' => 7, 'star' => 4, 'silver' => 1100, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 1, 'open_level' => 0],
        44 => ['id' => 44, 'title' => '宝石贸易', 'description' => '单日贸易中心贸易7次', 'type' => 11, 'value' => 7, 'star' => 4, 'silver' => 1100, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 1, 'open_level' => 0],
        45 => ['id' => 45, 'title' => '武将突飞', 'description' => '单日对任意武将突飞9次', 'type' => 1, 'value' => 9, 'star' => 5, 'silver' => 1300, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 2, 'open_level' => 0],
        46 => ['id' => 46, 'title' => '银币征收', 'description' => '单日征收或强征9次', 'type' => 2, 'value' => 9, 'star' => 5, 'silver' => 1300, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 2, 'open_level' => 0],
        47 => ['id' => 47, 'title' => '银矿抢占', 'description' => '单日抢夺或占领银矿9次', 'type' => 3, 'value' => 9, 'star' => 5, 'silver' => 1300, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 2, 'open_level' => 0],
        48 => ['id' => 48, 'title' => '每日通商', 'description' => '单日完成通商9次', 'type' => 4, 'value' => 9, 'star' => 5, 'silver' => 1300, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 2, 'open_level' => 0],
        49 => ['id' => 49, 'title' => '元宝消费', 'description' => '单日任意元宝消费9次', 'type' => 5, 'value' => 9, 'star' => 5, 'silver' => 1300, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 2, 'open_level' => 0],
        50 => ['id' => 50, 'title' => '关卡挑战', 'description' => '单日挑战任意关卡敌军9次', 'type' => 6, 'value' => 9, 'star' => 5, 'silver' => 1300, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 2, 'open_level' => 0],
        51 => ['id' => 51, 'title' => '属性培养', 'description' => '单日完成属性培养9次', 'type' => 7, 'value' => 9, 'star' => 5, 'silver' => 1300, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 2, 'open_level' => 0],
        52 => ['id' => 52, 'title' => '装备升级', 'description' => '单日完成装备升级9次', 'type' => 8, 'value' => 9, 'star' => 5, 'silver' => 1300, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 2, 'open_level' => 0],
        53 => ['id' => 53, 'title' => '金银守卫', 'description' => '单日金银洞副本挑战9次', 'type' => 9, 'value' => 9, 'star' => 5, 'silver' => 1300, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 2, 'open_level' => 0],
        54 => ['id' => 54, 'title' => '演武竞技', 'description' => '单日演武场竞技9次', 'type' => 10, 'value' => 9, 'star' => 5, 'silver' => 1300, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 2, 'open_level' => 0],
        55 => ['id' => 55, 'title' => '宝石贸易', 'description' => '单日贸易中心贸易9次', 'type' => 11, 'value' => 9, 'star' => 5, 'silver' => 1300, 'equip_type' => 2, 'equip_id' => 17, 'equip_num' => 2, 'open_level' => 0],
        56 => ['id' => 56, 'title' => '武将突飞', 'description' => '单日对任意武将突飞12次', 'type' => 1, 'value' => 12, 'star' => 6, 'silver' => 1500, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 2, 'open_level' => 0],
        57 => ['id' => 57, 'title' => '银币征收', 'description' => '单日征收或强征12次', 'type' => 2, 'value' => 12, 'star' => 6, 'silver' => 1500, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 2, 'open_level' => 0],
        58 => ['id' => 58, 'title' => '银矿抢占', 'description' => '单日抢夺或占领银矿12次', 'type' => 3, 'value' => 12, 'star' => 6, 'silver' => 1500, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 2, 'open_level' => 0],
        59 => ['id' => 59, 'title' => '每日通商', 'description' => '单日完成通商12次', 'type' => 4, 'value' => 12, 'star' => 6, 'silver' => 1500, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 2, 'open_level' => 0],
        60 => ['id' => 60, 'title' => '元宝消费', 'description' => '单日任意元宝消费12次', 'type' => 5, 'value' => 12, 'star' => 6, 'silver' => 1500, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 2, 'open_level' => 0],
        61 => ['id' => 61, 'title' => '关卡挑战', 'description' => '单日挑战任意关卡敌军12次', 'type' => 6, 'value' => 12, 'star' => 6, 'silver' => 1500, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 2, 'open_level' => 0],
        62 => ['id' => 62, 'title' => '属性培养', 'description' => '单日完成属性培养12次', 'type' => 7, 'value' => 12, 'star' => 6, 'silver' => 1500, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 2, 'open_level' => 0],
        63 => ['id' => 63, 'title' => '装备升级', 'description' => '单日完成装备升级12次', 'type' => 8, 'value' => 12, 'star' => 6, 'silver' => 1500, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 2, 'open_level' => 0],
        64 => ['id' => 64, 'title' => '金银守卫', 'description' => '单日金银洞副本挑战12次', 'type' => 9, 'value' => 12, 'star' => 6, 'silver' => 1500, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 2, 'open_level' => 0],
        65 => ['id' => 65, 'title' => '演武竞技', 'description' => '单日演武场竞技12次', 'type' => 10, 'value' => 12, 'star' => 6, 'silver' => 1500, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 2, 'open_level' => 0],
        66 => ['id' => 66, 'title' => '宝石贸易', 'description' => '单日贸易中心贸易12次', 'type' => 11, 'value' => 12, 'star' => 6, 'silver' => 1500, 'equip_type' => 2, 'equip_id' => 27, 'equip_num' => 2, 'open_level' => 0],
        67 => ['id' => 67, 'title' => '武将突飞', 'description' => '单日对任意武将突飞15次', 'type' => 1, 'value' => 15, 'star' => 7, 'silver' => 2000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 1, 'open_level' => 0],
        68 => ['id' => 68, 'title' => '银币征收', 'description' => '单日征收或强征15次', 'type' => 2, 'value' => 15, 'star' => 7, 'silver' => 2000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 1, 'open_level' => 0],
        69 => ['id' => 69, 'title' => '银矿抢占', 'description' => '单日抢夺或占领银矿15次', 'type' => 3, 'value' => 15, 'star' => 7, 'silver' => 2000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 1, 'open_level' => 0],
        70 => ['id' => 70, 'title' => '每日通商', 'description' => '单日完成通商15次', 'type' => 4, 'value' => 15, 'star' => 7, 'silver' => 2000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 1, 'open_level' => 0],
        71 => ['id' => 71, 'title' => '元宝消费', 'description' => '单日任意元宝消费15次', 'type' => 5, 'value' => 15, 'star' => 7, 'silver' => 2000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 1, 'open_level' => 0],
        72 => ['id' => 72, 'title' => '关卡挑战', 'description' => '单日挑战任意关卡敌军15次', 'type' => 6, 'value' => 15, 'star' => 7, 'silver' => 2000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 1, 'open_level' => 0],
        73 => ['id' => 73, 'title' => '属性培养', 'description' => '单日完成属性培养15次', 'type' => 7, 'value' => 15, 'star' => 7, 'silver' => 2000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 1, 'open_level' => 0],
        74 => ['id' => 74, 'title' => '装备升级', 'description' => '单日完成装备升级15次', 'type' => 8, 'value' => 15, 'star' => 7, 'silver' => 2000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 1, 'open_level' => 0],
        75 => ['id' => 75, 'title' => '金银守卫', 'description' => '单日金银洞副本挑战15次', 'type' => 9, 'value' => 15, 'star' => 7, 'silver' => 2000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 1, 'open_level' => 0],
        76 => ['id' => 76, 'title' => '演武竞技', 'description' => '单日演武场竞技15次', 'type' => 10, 'value' => 15, 'star' => 7, 'silver' => 2000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 1, 'open_level' => 0],
        77 => ['id' => 77, 'title' => '宝石贸易', 'description' => '单日贸易中心贸易15次', 'type' => 11, 'value' => 15, 'star' => 7, 'silver' => 2000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 1, 'open_level' => 0],
        78 => ['id' => 78, 'title' => '武将突飞', 'description' => '单日对任意武将突飞18次', 'type' => 1, 'value' => 18, 'star' => 8, 'silver' => 2500, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 2, 'open_level' => 0],
        79 => ['id' => 79, 'title' => '银币征收', 'description' => '单日征收或强征18次', 'type' => 2, 'value' => 18, 'star' => 8, 'silver' => 2500, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 2, 'open_level' => 0],
        80 => ['id' => 80, 'title' => '银矿抢占', 'description' => '单日抢夺或占领银矿18次', 'type' => 3, 'value' => 18, 'star' => 8, 'silver' => 2500, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 2, 'open_level' => 0],
        81 => ['id' => 81, 'title' => '每日通商', 'description' => '单日完成通商18次', 'type' => 4, 'value' => 18, 'star' => 8, 'silver' => 2500, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 2, 'open_level' => 0],
        82 => ['id' => 82, 'title' => '元宝消费', 'description' => '单日任意元宝消费18次', 'type' => 5, 'value' => 18, 'star' => 8, 'silver' => 2500, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 2, 'open_level' => 0],
        83 => ['id' => 83, 'title' => '关卡挑战', 'description' => '单日挑战任意关卡敌军18次', 'type' => 6, 'value' => 18, 'star' => 8, 'silver' => 2500, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 2, 'open_level' => 0],
        84 => ['id' => 84, 'title' => '属性培养', 'description' => '单日完成属性培养18次', 'type' => 7, 'value' => 18, 'star' => 8, 'silver' => 2500, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 2, 'open_level' => 0],
        85 => ['id' => 85, 'title' => '装备升级', 'description' => '单日完成装备升级18次', 'type' => 8, 'value' => 18, 'star' => 8, 'silver' => 2500, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 2, 'open_level' => 0],
        86 => ['id' => 86, 'title' => '金银守卫', 'description' => '单日金银洞副本挑战18次', 'type' => 9, 'value' => 18, 'star' => 8, 'silver' => 2500, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 2, 'open_level' => 0],
        87 => ['id' => 87, 'title' => '演武竞技', 'description' => '单日演武场竞技18次', 'type' => 10, 'value' => 18, 'star' => 8, 'silver' => 2500, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 2, 'open_level' => 0],
        88 => ['id' => 88, 'title' => '宝石贸易', 'description' => '单日贸易中心贸易18次', 'type' => 11, 'value' => 18, 'star' => 8, 'silver' => 2500, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 2, 'open_level' => 0],
        89 => ['id' => 89, 'title' => '武将突飞', 'description' => '单日对任意武将突飞21次', 'type' => 1, 'value' => 21, 'star' => 9, 'silver' => 3000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 3, 'open_level' => 0],
        90 => ['id' => 90, 'title' => '银币征收', 'description' => '单日征收或强征21次', 'type' => 2, 'value' => 21, 'star' => 9, 'silver' => 3000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 3, 'open_level' => 0],
        91 => ['id' => 91, 'title' => '银矿抢占', 'description' => '单日抢夺或占领银矿21次', 'type' => 3, 'value' => 21, 'star' => 9, 'silver' => 3000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 3, 'open_level' => 0],
        92 => ['id' => 92, 'title' => '每日通商', 'description' => '单日完成通商21次', 'type' => 4, 'value' => 21, 'star' => 9, 'silver' => 3000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 3, 'open_level' => 0],
        93 => ['id' => 93, 'title' => '元宝消费', 'description' => '单日任意元宝消费21次', 'type' => 5, 'value' => 21, 'star' => 9, 'silver' => 3000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 3, 'open_level' => 0],
        94 => ['id' => 94, 'title' => '关卡挑战', 'description' => '单日挑战任意关卡敌军21次', 'type' => 6, 'value' => 21, 'star' => 9, 'silver' => 3000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 3, 'open_level' => 0],
        95 => ['id' => 95, 'title' => '属性培养', 'description' => '单日完成属性培养21次', 'type' => 7, 'value' => 21, 'star' => 9, 'silver' => 3000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 3, 'open_level' => 0],
        96 => ['id' => 96, 'title' => '装备升级', 'description' => '单日完成装备升级21次', 'type' => 8, 'value' => 21, 'star' => 9, 'silver' => 3000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 3, 'open_level' => 0],
        97 => ['id' => 97, 'title' => '金银守卫', 'description' => '单日金银洞副本挑战21次', 'type' => 9, 'value' => 21, 'star' => 9, 'silver' => 3000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 3, 'open_level' => 0],
        98 => ['id' => 98, 'title' => '演武竞技', 'description' => '单日演武场竞技21次', 'type' => 10, 'value' => 21, 'star' => 9, 'silver' => 3000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 3, 'open_level' => 0],
        99 => ['id' => 99, 'title' => '宝石贸易', 'description' => '单日贸易中心贸易21次', 'type' => 11, 'value' => 21, 'star' => 9, 'silver' => 3000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 3, 'open_level' => 0],
        100 => ['id' => 100, 'title' => '武将突飞', 'description' => '单日对任意武将突飞25次', 'type' => 1, 'value' => 25, 'star' => 10, 'silver' => 4000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 5, 'open_level' => 0],
        101 => ['id' => 101, 'title' => '银币征收', 'description' => '单日征收或强征25次', 'type' => 2, 'value' => 25, 'star' => 10, 'silver' => 4000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 5, 'open_level' => 0],
        102 => ['id' => 102, 'title' => '银矿抢占', 'description' => '单日抢夺或占领银矿25次', 'type' => 3, 'value' => 25, 'star' => 10, 'silver' => 4000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 5, 'open_level' => 0],
        103 => ['id' => 103, 'title' => '每日通商', 'description' => '单日完成通商25次', 'type' => 4, 'value' => 25, 'star' => 10, 'silver' => 4000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 5, 'open_level' => 0],
        104 => ['id' => 104, 'title' => '元宝消费', 'description' => '单日任意元宝消费25次', 'type' => 5, 'value' => 25, 'star' => 10, 'silver' => 4000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 5, 'open_level' => 0],
        105 => ['id' => 105, 'title' => '关卡挑战', 'description' => '单日挑战任意关卡敌军25次', 'type' => 6, 'value' => 25, 'star' => 10, 'silver' => 4000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 5, 'open_level' => 0],
        106 => ['id' => 106, 'title' => '属性培养', 'description' => '单日完成属性培养25次', 'type' => 7, 'value' => 25, 'star' => 10, 'silver' => 4000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 5, 'open_level' => 0],
        107 => ['id' => 107, 'title' => '装备升级', 'description' => '单日完成装备升级25次', 'type' => 8, 'value' => 25, 'star' => 10, 'silver' => 4000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 5, 'open_level' => 0],
        108 => ['id' => 108, 'title' => '金银守卫', 'description' => '单日金银洞副本挑战25次', 'type' => 9, 'value' => 25, 'star' => 10, 'silver' => 4000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 5, 'open_level' => 0],
        109 => ['id' => 109, 'title' => '演武竞技', 'description' => '单日演武场竞技25次', 'type' => 10, 'value' => 25, 'star' => 10, 'silver' => 4000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 5, 'open_level' => 0],
        110 => ['id' => 110, 'title' => '宝石贸易', 'description' => '单日贸易中心贸易25次', 'type' => 11, 'value' => 25, 'star' => 10, 'silver' => 4000, 'equip_type' => 2, 'equip_id' => 50, 'equip_num' => 5, 'open_level' => 0],
        201 => ['id' => 201, 'title' => '世界BOSS', 'description' => '单日击杀1次世界BOSS', 'type' => 12, 'value' => 1, 'star' => 5, 'silver' => 1300, 'equip_type' => 3, 'equip_id' => 5, 'equip_num' => 0, 'open_level' => 0],
        202 => ['id' => 202, 'title' => '国家矿脉', 'description' => '单日抢夺1次国家矿脉', 'type' => 13, 'value' => 1, 'star' => 5, 'silver' => 1300, 'equip_type' => 4, 'equip_id' => 50000, 'equip_num' => 0, 'open_level' => 91],
        203 => ['id' => 203, 'title' => '天下史诗', 'description' => '单日刷新10次天下史诗', 'type' => 14, 'value' => 10, 'star' => 5, 'silver' => 1300, 'equip_type' => 5, 'equip_id' => 500, 'equip_num' => 0, 'open_level' => 150],
        204 => ['id' => 204, 'title' => '世界BOSS', 'description' => '单日击杀1次世界BOSS', 'type' => 12, 'value' => 1, 'star' => 6, 'silver' => 1500, 'equip_type' => 3, 'equip_id' => 6, 'equip_num' => 0, 'open_level' => 0],
        205 => ['id' => 205, 'title' => '国家矿脉', 'description' => '单日抢夺1次国家矿脉', 'type' => 13, 'value' => 1, 'star' => 6, 'silver' => 1500, 'equip_type' => 4, 'equip_id' => 60000, 'equip_num' => 0, 'open_level' => 91],
        206 => ['id' => 206, 'title' => '天下史诗', 'description' => '单日刷新10次天下史诗', 'type' => 14, 'value' => 10, 'star' => 6, 'silver' => 1500, 'equip_type' => 5, 'equip_id' => 600, 'equip_num' => 0, 'open_level' => 150],
        207 => ['id' => 207, 'title' => '世界BOSS', 'description' => '单日击杀1次世界BOSS', 'type' => 12, 'value' => 1, 'star' => 7, 'silver' => 2000, 'equip_type' => 3, 'equip_id' => 7, 'equip_num' => 0, 'open_level' => 0],
        208 => ['id' => 208, 'title' => '国家矿脉', 'description' => '单日抢夺1次国家矿脉', 'type' => 13, 'value' => 1, 'star' => 7, 'silver' => 2000, 'equip_type' => 4, 'equip_id' => 70000, 'equip_num' => 0, 'open_level' => 91],
        209 => ['id' => 209, 'title' => '天下史诗', 'description' => '单日刷新10次天下史诗', 'type' => 14, 'value' => 10, 'star' => 7, 'silver' => 2000, 'equip_type' => 5, 'equip_id' => 700, 'equip_num' => 0, 'open_level' => 150],
        210 => ['id' => 210, 'title' => '世界BOSS', 'description' => '单日击杀1次世界BOSS', 'type' => 12, 'value' => 1, 'star' => 8, 'silver' => 2500, 'equip_type' => 3, 'equip_id' => 8, 'equip_num' => 0, 'open_level' => 0],
        211 => ['id' => 211, 'title' => '国家矿脉', 'description' => '单日抢夺1次国家矿脉', 'type' => 13, 'value' => 1, 'star' => 8, 'silver' => 2500, 'equip_type' => 4, 'equip_id' => 80000, 'equip_num' => 0, 'open_level' => 91],
        212 => ['id' => 212, 'title' => '天下史诗', 'description' => '单日刷新10次天下史诗', 'type' => 14, 'value' => 10, 'star' => 8, 'silver' => 2500, 'equip_type' => 5, 'equip_id' => 800, 'equip_num' => 0, 'open_level' => 150],
        213 => ['id' => 213, 'title' => '世界BOSS', 'description' => '单日击杀1次世界BOSS', 'type' => 12, 'value' => 1, 'star' => 9, 'silver' => 3000, 'equip_type' => 3, 'equip_id' => 9, 'equip_num' => 0, 'open_level' => 0],
        214 => ['id' => 214, 'title' => '国家矿脉', 'description' => '单日抢夺1次国家矿脉', 'type' => 13, 'value' => 1, 'star' => 9, 'silver' => 3000, 'equip_type' => 4, 'equip_id' => 90000, 'equip_num' => 0, 'open_level' => 91],
        215 => ['id' => 215, 'title' => '天下史诗', 'description' => '单日刷新10次天下史诗', 'type' => 14, 'value' => 10, 'star' => 9, 'silver' => 3000, 'equip_type' => 5, 'equip_id' => 900, 'equip_num' => 0, 'open_level' => 150],
        216 => ['id' => 216, 'title' => '世界BOSS', 'description' => '单日击杀1次世界BOSS', 'type' => 12, 'value' => 1, 'star' => 10, 'silver' => 4000, 'equip_type' => 3, 'equip_id' => 10, 'equip_num' => 0, 'open_level' => 0],
        217 => ['id' => 217, 'title' => '国家矿脉', 'description' => '单日抢夺1次国家矿脉', 'type' => 13, 'value' => 1, 'star' => 10, 'silver' => 4000, 'equip_type' => 4, 'equip_id' => 100000, 'equip_num' => 0, 'open_level' => 91],
        218 => ['id' => 218, 'title' => '天下史诗', 'description' => '单日刷新10次天下史诗', 'type' => 14, 'value' => 10, 'star' => 10, 'silver' => 4000, 'equip_type' => 5, 'equip_id' => 1000, 'equip_num' => 0, 'open_level' => 150],
    ];
}
