<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 抢夺宝箱数据表（旧版 zc_sanguo_beauty_loot_box）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class BeautyLootBox
{
    public const string TABLE = 'zc_sanguo_beauty_loot_box';

    /**
     * 按主键 id 索引，共 63 行。字段：
     *   - id (int) 序列id
     *   - name (varchar) 名称
     *   - type (int) 奖励类型
     *   - value (int) 奖励数量
     *   - value1 (int) 奖励数量
     *   - rate (float) 权重
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '祝融碎片一', 'type' => 30, 'value' => 1, 'value1' => 1, 'rate' => 1.0],
        2 => ['id' => 2, 'name' => '祝融碎片二', 'type' => 30, 'value' => 1, 'value1' => 2, 'rate' => 1.0],
        3 => ['id' => 3, 'name' => '祝融碎片三', 'type' => 30, 'value' => 1, 'value1' => 3, 'rate' => 1.0],
        4 => ['id' => 4, 'name' => '祝融碎片四', 'type' => 30, 'value' => 1, 'value1' => 4, 'rate' => 1.0],
        5 => ['id' => 5, 'name' => '祝融碎片五', 'type' => 30, 'value' => 1, 'value1' => 5, 'rate' => 1.0],
        6 => ['id' => 6, 'name' => '祝融碎片六', 'type' => 30, 'value' => 1, 'value1' => 6, 'rate' => 1.0],
        7 => ['id' => 7, 'name' => '甄姬碎片一', 'type' => 30, 'value' => 2, 'value1' => 1, 'rate' => 1.0],
        8 => ['id' => 8, 'name' => '甄姬碎片二', 'type' => 30, 'value' => 2, 'value1' => 2, 'rate' => 1.0],
        9 => ['id' => 9, 'name' => '甄姬碎片三', 'type' => 30, 'value' => 2, 'value1' => 3, 'rate' => 1.0],
        10 => ['id' => 10, 'name' => '甄姬碎片四', 'type' => 30, 'value' => 2, 'value1' => 4, 'rate' => 1.0],
        11 => ['id' => 11, 'name' => '甄姬碎片五', 'type' => 30, 'value' => 2, 'value1' => 5, 'rate' => 1.0],
        12 => ['id' => 12, 'name' => '甄姬碎片六', 'type' => 30, 'value' => 2, 'value1' => 6, 'rate' => 1.0],
        13 => ['id' => 13, 'name' => '孙尚香碎片一', 'type' => 30, 'value' => 3, 'value1' => 1, 'rate' => 1.0],
        14 => ['id' => 14, 'name' => '孙尚香碎片二', 'type' => 30, 'value' => 3, 'value1' => 2, 'rate' => 1.0],
        15 => ['id' => 15, 'name' => '孙尚香碎片三', 'type' => 30, 'value' => 3, 'value1' => 3, 'rate' => 1.0],
        16 => ['id' => 16, 'name' => '孙尚香碎片四', 'type' => 30, 'value' => 3, 'value1' => 4, 'rate' => 1.0],
        17 => ['id' => 17, 'name' => '孙尚香碎片五', 'type' => 30, 'value' => 3, 'value1' => 5, 'rate' => 1.0],
        18 => ['id' => 18, 'name' => '孙尚香碎片六', 'type' => 30, 'value' => 3, 'value1' => 6, 'rate' => 1.0],
        19 => ['id' => 19, 'name' => '大乔碎片一', 'type' => 30, 'value' => 4, 'value1' => 1, 'rate' => 1.0],
        20 => ['id' => 20, 'name' => '大乔碎片二', 'type' => 30, 'value' => 4, 'value1' => 2, 'rate' => 1.0],
        21 => ['id' => 21, 'name' => '大乔碎片三', 'type' => 30, 'value' => 4, 'value1' => 3, 'rate' => 1.0],
        22 => ['id' => 22, 'name' => '大乔碎片四', 'type' => 30, 'value' => 4, 'value1' => 4, 'rate' => 1.0],
        23 => ['id' => 23, 'name' => '大乔碎片五', 'type' => 30, 'value' => 4, 'value1' => 5, 'rate' => 1.0],
        24 => ['id' => 24, 'name' => '大乔碎片六', 'type' => 30, 'value' => 4, 'value1' => 6, 'rate' => 1.0],
        25 => ['id' => 25, 'name' => '小乔碎片一', 'type' => 30, 'value' => 5, 'value1' => 1, 'rate' => 1.0],
        26 => ['id' => 26, 'name' => '小乔碎片二', 'type' => 30, 'value' => 5, 'value1' => 2, 'rate' => 1.0],
        27 => ['id' => 27, 'name' => '小乔碎片三', 'type' => 30, 'value' => 5, 'value1' => 3, 'rate' => 1.0],
        28 => ['id' => 28, 'name' => '小乔碎片四', 'type' => 30, 'value' => 5, 'value1' => 4, 'rate' => 1.0],
        29 => ['id' => 29, 'name' => '小乔碎片五', 'type' => 30, 'value' => 5, 'value1' => 5, 'rate' => 1.0],
        30 => ['id' => 30, 'name' => '小乔碎片六', 'type' => 30, 'value' => 5, 'value1' => 6, 'rate' => 1.0],
        31 => ['id' => 31, 'name' => '蔡文姬碎片一', 'type' => 30, 'value' => 6, 'value1' => 1, 'rate' => 1.0],
        32 => ['id' => 32, 'name' => '蔡文姬碎片二', 'type' => 30, 'value' => 6, 'value1' => 2, 'rate' => 1.0],
        33 => ['id' => 33, 'name' => '蔡文姬碎片三', 'type' => 30, 'value' => 6, 'value1' => 3, 'rate' => 1.0],
        34 => ['id' => 34, 'name' => '蔡文姬碎片四', 'type' => 30, 'value' => 6, 'value1' => 4, 'rate' => 1.0],
        35 => ['id' => 35, 'name' => '蔡文姬碎片五', 'type' => 30, 'value' => 6, 'value1' => 5, 'rate' => 1.0],
        36 => ['id' => 36, 'name' => '蔡文姬碎片六', 'type' => 30, 'value' => 6, 'value1' => 6, 'rate' => 1.0],
        37 => ['id' => 37, 'name' => '貂蝉碎片一', 'type' => 30, 'value' => 7, 'value1' => 1, 'rate' => 1.0],
        38 => ['id' => 38, 'name' => '貂蝉碎片二', 'type' => 30, 'value' => 7, 'value1' => 2, 'rate' => 1.0],
        39 => ['id' => 39, 'name' => '貂蝉碎片三', 'type' => 30, 'value' => 7, 'value1' => 3, 'rate' => 1.0],
        40 => ['id' => 40, 'name' => '貂蝉碎片四', 'type' => 30, 'value' => 7, 'value1' => 4, 'rate' => 1.0],
        41 => ['id' => 41, 'name' => '貂蝉碎片五', 'type' => 30, 'value' => 7, 'value1' => 5, 'rate' => 1.0],
        42 => ['id' => 42, 'name' => '貂蝉碎片六', 'type' => 30, 'value' => 7, 'value1' => 6, 'rate' => 1.0],
        43 => ['id' => 43, 'name' => '银币', 'type' => 2, 'value' => 500, 'value1' => 0, 'rate' => 40.0],
        44 => ['id' => 44, 'name' => '黄宝石', 'type' => 3, 'value' => 4, 'value1' => 0, 'rate' => 15.0],
        45 => ['id' => 45, 'name' => '黄宝石', 'type' => 3, 'value' => 8, 'value1' => 0, 'rate' => 10.0],
        46 => ['id' => 46, 'name' => '黄宝石', 'type' => 3, 'value' => 12, 'value1' => 0, 'rate' => 3.0],
        47 => ['id' => 47, 'name' => '紫宝石', 'type' => 4, 'value' => 2, 'value1' => 0, 'rate' => 10.0],
        48 => ['id' => 48, 'name' => '紫宝石', 'type' => 4, 'value' => 5, 'value1' => 0, 'rate' => 5.0],
        49 => ['id' => 49, 'name' => '紫宝石', 'type' => 4, 'value' => 8, 'value1' => 0, 'rate' => 2.0],
        50 => ['id' => 50, 'name' => '原魂', 'type' => 5, 'value' => 10, 'value1' => 0, 'rate' => 20.0],
        51 => ['id' => 51, 'name' => '原魂', 'type' => 5, 'value' => 15, 'value1' => 0, 'rate' => 15.0],
        52 => ['id' => 52, 'name' => '原魂', 'type' => 5, 'value' => 20, 'value1' => 0, 'rate' => 10.0],
        53 => ['id' => 53, 'name' => '玫瑰花', 'type' => 31, 'value' => 1, 'value1' => 0, 'rate' => 10.0],
        54 => ['id' => 54, 'name' => '玫瑰花', 'type' => 31, 'value' => 2, 'value1' => 0, 'rate' => 6.0],
        55 => ['id' => 55, 'name' => '玫瑰花', 'type' => 31, 'value' => 3, 'value1' => 0, 'rate' => 3.0],
        56 => ['id' => 56, 'name' => '声望', 'type' => 7, 'value' => 20, 'value1' => 0, 'rate' => 10.0],
        57 => ['id' => 57, 'name' => '声望', 'type' => 7, 'value' => 60, 'value1' => 0, 'rate' => 5.0],
        58 => ['id' => 58, 'name' => '声望', 'type' => 7, 'value' => 100, 'value1' => 0, 'rate' => 3.0],
        59 => ['id' => 59, 'name' => '钻石', 'type' => 32, 'value' => 1, 'value1' => 0, 'rate' => 5.0],
        60 => ['id' => 60, 'name' => '钻石', 'type' => 32, 'value' => 2, 'value1' => 0, 'rate' => 2.0],
        61 => ['id' => 61, 'name' => '钻石', 'type' => 32, 'value' => 3, 'value1' => 0, 'rate' => 1.0],
        62 => ['id' => 62, 'name' => '玉石', 'type' => 6, 'value' => 50000, 'value1' => 0, 'rate' => 5.0],
        63 => ['id' => 63, 'name' => '突飞卡', 'type' => 10, 'value' => 20, 'value1' => 0, 'rate' => 10.0],
    ];
}
