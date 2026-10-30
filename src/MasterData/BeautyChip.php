<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 美人碎片表（旧版 zc_sanguo_beauty_chip）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class BeautyChip
{
    public const string TABLE = 'zc_sanguo_beauty_chip';

    /**
     * 按主键 id 索引，共 42 行。字段：
     *   - id (int) 序列id
     *   - beauty_id (int) 美人id
     *   - chip_name (varchar) 碎片名称
     *   - chip_id (tinyint) 碎片唯一id
     */
    public const array ROWS = [
        1 => ['id' => 1, 'beauty_id' => 1, 'chip_name' => '祝融碎片一', 'chip_id' => 1],
        2 => ['id' => 2, 'beauty_id' => 1, 'chip_name' => '祝融碎片二', 'chip_id' => 2],
        3 => ['id' => 3, 'beauty_id' => 1, 'chip_name' => '祝融碎片三', 'chip_id' => 3],
        4 => ['id' => 4, 'beauty_id' => 1, 'chip_name' => '祝融碎片四', 'chip_id' => 4],
        5 => ['id' => 5, 'beauty_id' => 1, 'chip_name' => '祝融碎片五', 'chip_id' => 5],
        6 => ['id' => 6, 'beauty_id' => 1, 'chip_name' => '祝融碎片六', 'chip_id' => 6],
        7 => ['id' => 7, 'beauty_id' => 2, 'chip_name' => '甄姬碎片一', 'chip_id' => 1],
        8 => ['id' => 8, 'beauty_id' => 2, 'chip_name' => '甄姬碎片二', 'chip_id' => 2],
        9 => ['id' => 9, 'beauty_id' => 2, 'chip_name' => '甄姬碎片三', 'chip_id' => 3],
        10 => ['id' => 10, 'beauty_id' => 2, 'chip_name' => '甄姬碎片四', 'chip_id' => 4],
        11 => ['id' => 11, 'beauty_id' => 2, 'chip_name' => '甄姬碎片五', 'chip_id' => 5],
        12 => ['id' => 12, 'beauty_id' => 2, 'chip_name' => '甄姬碎片六', 'chip_id' => 6],
        13 => ['id' => 13, 'beauty_id' => 3, 'chip_name' => '孙尚香碎片一', 'chip_id' => 1],
        14 => ['id' => 14, 'beauty_id' => 3, 'chip_name' => '孙尚香碎片二', 'chip_id' => 2],
        15 => ['id' => 15, 'beauty_id' => 3, 'chip_name' => '孙尚香碎片三', 'chip_id' => 3],
        16 => ['id' => 16, 'beauty_id' => 3, 'chip_name' => '孙尚香碎片四', 'chip_id' => 4],
        17 => ['id' => 17, 'beauty_id' => 3, 'chip_name' => '孙尚香碎片五', 'chip_id' => 5],
        18 => ['id' => 18, 'beauty_id' => 3, 'chip_name' => '孙尚香碎片六', 'chip_id' => 6],
        19 => ['id' => 19, 'beauty_id' => 4, 'chip_name' => '大乔碎片一', 'chip_id' => 1],
        20 => ['id' => 20, 'beauty_id' => 4, 'chip_name' => '大乔碎片二', 'chip_id' => 2],
        21 => ['id' => 21, 'beauty_id' => 4, 'chip_name' => '大乔碎片三', 'chip_id' => 3],
        22 => ['id' => 22, 'beauty_id' => 4, 'chip_name' => '大乔碎片四', 'chip_id' => 4],
        23 => ['id' => 23, 'beauty_id' => 4, 'chip_name' => '大乔碎片五', 'chip_id' => 5],
        24 => ['id' => 24, 'beauty_id' => 4, 'chip_name' => '大乔碎片六', 'chip_id' => 6],
        25 => ['id' => 25, 'beauty_id' => 5, 'chip_name' => '小乔碎片一', 'chip_id' => 1],
        26 => ['id' => 26, 'beauty_id' => 5, 'chip_name' => '小乔碎片二', 'chip_id' => 2],
        27 => ['id' => 27, 'beauty_id' => 5, 'chip_name' => '小乔碎片三', 'chip_id' => 3],
        28 => ['id' => 28, 'beauty_id' => 5, 'chip_name' => '小乔碎片四', 'chip_id' => 4],
        29 => ['id' => 29, 'beauty_id' => 5, 'chip_name' => '小乔碎片五', 'chip_id' => 5],
        30 => ['id' => 30, 'beauty_id' => 5, 'chip_name' => '小乔碎片六', 'chip_id' => 6],
        31 => ['id' => 31, 'beauty_id' => 6, 'chip_name' => '蔡文姬碎片一', 'chip_id' => 1],
        32 => ['id' => 32, 'beauty_id' => 6, 'chip_name' => '蔡文姬碎片二', 'chip_id' => 2],
        33 => ['id' => 33, 'beauty_id' => 6, 'chip_name' => '蔡文姬碎片三', 'chip_id' => 3],
        34 => ['id' => 34, 'beauty_id' => 6, 'chip_name' => '蔡文姬碎片四', 'chip_id' => 4],
        35 => ['id' => 35, 'beauty_id' => 6, 'chip_name' => '蔡文姬碎片五', 'chip_id' => 5],
        36 => ['id' => 36, 'beauty_id' => 6, 'chip_name' => '蔡文姬碎片六', 'chip_id' => 6],
        37 => ['id' => 37, 'beauty_id' => 7, 'chip_name' => '貂蝉碎片一', 'chip_id' => 1],
        38 => ['id' => 38, 'beauty_id' => 7, 'chip_name' => '貂蝉碎片二', 'chip_id' => 2],
        39 => ['id' => 39, 'beauty_id' => 7, 'chip_name' => '貂蝉碎片三', 'chip_id' => 3],
        40 => ['id' => 40, 'beauty_id' => 7, 'chip_name' => '貂蝉碎片四', 'chip_id' => 4],
        41 => ['id' => 41, 'beauty_id' => 7, 'chip_name' => '貂蝉碎片五', 'chip_id' => 5],
        42 => ['id' => 42, 'beauty_id' => 7, 'chip_name' => '貂蝉碎片六', 'chip_id' => 6],
    ];
}
