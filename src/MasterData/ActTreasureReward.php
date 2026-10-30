<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 踏青寻宝-转盘奖励表（旧版 zc_sanguo_act_treasure_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActTreasureReward
{
    public const string TABLE = 'zc_sanguo_act_treasure_reward';

    /**
     * 按主键 id 索引，共 29 行。字段：
     *   - id (int) 主键ID
     *   - name (varchar) 奖品名称
     *   - type (int) 奖品类型
     *   - num (int) 奖品数量
     *   - p_type (int) 价值类型
     *   - get_rate (float) 刷新权重
     *   - site_rate (float) 获得权重
     *   - image (int) 图片大小标识
     *   - desc (varchar) 奖品描述
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '悍', 'type' => 19, 'num' => 1, 'p_type' => 1, 'get_rate' => 50.0, 'site_rate' => 60.0, 'image' => 0, 'desc' => '悍'],
        2 => ['id' => 2, 'name' => '将', 'type' => 19, 'num' => 2, 'p_type' => 1, 'get_rate' => 50.0, 'site_rate' => 60.0, 'image' => 0, 'desc' => '将'],
        3 => ['id' => 3, 'name' => '三', 'type' => 19, 'num' => 3, 'p_type' => 1, 'get_rate' => 50.0, 'site_rate' => 60.0, 'image' => 0, 'desc' => '三'],
        4 => ['id' => 4, 'name' => '国', 'type' => 19, 'num' => 4, 'p_type' => 1, 'get_rate' => 50.0, 'site_rate' => 60.0, 'image' => 0, 'desc' => '国'],
        5 => ['id' => 5, 'name' => '谁', 'type' => 19, 'num' => 5, 'p_type' => 1, 'get_rate' => 50.0, 'site_rate' => 60.0, 'image' => 0, 'desc' => '谁'],
        6 => ['id' => 6, 'name' => '与', 'type' => 19, 'num' => 6, 'p_type' => 1, 'get_rate' => 50.0, 'site_rate' => 60.0, 'image' => 0, 'desc' => '与'],
        7 => ['id' => 7, 'name' => '争', 'type' => 19, 'num' => 7, 'p_type' => 1, 'get_rate' => 50.0, 'site_rate' => 60.0, 'image' => 0, 'desc' => '争'],
        8 => ['id' => 8, 'name' => '锋', 'type' => 19, 'num' => 8, 'p_type' => 1, 'get_rate' => 50.0, 'site_rate' => 60.0, 'image' => 0, 'desc' => '锋'],
        9 => ['id' => 9, 'name' => '盘龙雌雄剑', 'type' => 8, 'num' => 72, 'p_type' => 3, 'get_rate' => 5.0, 'site_rate' => 1.0, 'image' => 0, 'desc' => '盘龙雌雄剑'],
        10 => ['id' => 10, 'name' => '朱雀羽扇', 'type' => 8, 'num' => 73, 'p_type' => 3, 'get_rate' => 5.0, 'site_rate' => 1.0, 'image' => 0, 'desc' => '朱雀羽扇'],
        11 => ['id' => 11, 'name' => '赤赦飞龙甲', 'type' => 8, 'num' => 74, 'p_type' => 3, 'get_rate' => 5.0, 'site_rate' => 3.0, 'image' => 0, 'desc' => '赤赦飞龙甲'],
        12 => ['id' => 12, 'name' => '山隐虎王袍', 'type' => 8, 'num' => 75, 'p_type' => 3, 'get_rate' => 5.0, 'site_rate' => 3.0, 'image' => 0, 'desc' => '山隐虎王袍'],
        13 => ['id' => 13, 'name' => '声望', 'type' => 7, 'num' => 600, 'p_type' => 1, 'get_rate' => 100.0, 'site_rate' => 600.0, 'image' => 1, 'desc' => '声望'],
        14 => ['id' => 14, 'name' => '紫宝石', 'type' => 4, 'num' => 10, 'p_type' => 1, 'get_rate' => 100.0, 'site_rate' => 600.0, 'image' => 1, 'desc' => '紫宝石'],
        15 => ['id' => 15, 'name' => '黄宝石', 'type' => 3, 'num' => 30, 'p_type' => 1, 'get_rate' => 100.0, 'site_rate' => 600.0, 'image' => 1, 'desc' => '黄宝石'],
        16 => ['id' => 16, 'name' => '五彩石', 'type' => 16, 'num' => 50, 'p_type' => 1, 'get_rate' => 100.0, 'site_rate' => 600.0, 'image' => 1, 'desc' => '精炼石'],
        17 => ['id' => 17, 'name' => '宝石碎片', 'type' => 9, 'num' => 15000, 'p_type' => 1, 'get_rate' => 100.0, 'site_rate' => 600.0, 'image' => 1, 'desc' => '宝石碎片'],
        18 => ['id' => 18, 'name' => '上古血玉', 'type' => 17, 'num' => 8, 'p_type' => 1, 'get_rate' => 100.0, 'site_rate' => 600.0, 'image' => 1, 'desc' => '上古血玉'],
        19 => ['id' => 19, 'name' => '突飞卡', 'type' => 10, 'num' => 10, 'p_type' => 1, 'get_rate' => 100.0, 'site_rate' => 600.0, 'image' => 1, 'desc' => '突飞卡'],
        20 => ['id' => 20, 'name' => '银币', 'type' => 2, 'num' => 900000, 'p_type' => 1, 'get_rate' => 100.0, 'site_rate' => 600.0, 'image' => 1, 'desc' => '银币'],
        21 => ['id' => 21, 'name' => '声望', 'type' => 7, 'num' => 1000, 'p_type' => 2, 'get_rate' => 5.0, 'site_rate' => 600.0, 'image' => 1, 'desc' => '声望'],
        22 => ['id' => 22, 'name' => '紫宝石', 'type' => 4, 'num' => 15, 'p_type' => 2, 'get_rate' => 5.0, 'site_rate' => 600.0, 'image' => 1, 'desc' => '紫宝石'],
        23 => ['id' => 23, 'name' => '黄宝石', 'type' => 3, 'num' => 50, 'p_type' => 2, 'get_rate' => 5.0, 'site_rate' => 600.0, 'image' => 1, 'desc' => '黄宝石'],
        24 => ['id' => 24, 'name' => '五彩石', 'type' => 16, 'num' => 80, 'p_type' => 2, 'get_rate' => 5.0, 'site_rate' => 600.0, 'image' => 1, 'desc' => '五彩石'],
        25 => ['id' => 25, 'name' => '宝石碎片', 'type' => 9, 'num' => 20000, 'p_type' => 2, 'get_rate' => 5.0, 'site_rate' => 600.0, 'image' => 1, 'desc' => '宝石碎片'],
        26 => ['id' => 26, 'name' => '上古血玉', 'type' => 17, 'num' => 15, 'p_type' => 2, 'get_rate' => 5.0, 'site_rate' => 600.0, 'image' => 1, 'desc' => '上古血玉'],
        27 => ['id' => 27, 'name' => '突飞卡', 'type' => 10, 'num' => 15, 'p_type' => 2, 'get_rate' => 5.0, 'site_rate' => 600.0, 'image' => 1, 'desc' => '突飞卡'],
        28 => ['id' => 28, 'name' => '问号格', 'type' => 18, 'num' => 0, 'p_type' => 2, 'get_rate' => 80.0, 'site_rate' => 600.0, 'image' => 0, 'desc' => '问号格'],
        29 => ['id' => 29, 'name' => '双倍格', 'type' => 20, 'num' => 0, 'p_type' => 4, 'get_rate' => 0.0, 'site_rate' => 600.0, 'image' => 0, 'desc' => '双倍格'],
    ];
}
