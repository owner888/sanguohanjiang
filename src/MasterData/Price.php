<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 价格汇总表（旧版 zc_sanguo_price）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Price
{
    public const string TABLE = 'zc_sanguo_price';

    /**
     * 按主键 id 索引，共 50 行。字段：
     *   - id (tinyint) 次数
     *   - act (smallint) 军令价格
     *   - leap (smallint) 突飞价格
     *   - storm (smallint) 强攻价格
     *   - levy (smallint) 强征价格
     *   - jade1 (int) 蓝宝石祭祀花费元宝
     *   - jade2 (int) 黄宝石祭祀花费元宝
     *   - jade3 (int) 紫宝石祭祀花费元宝
     *   - pack (int) 将魂背包格子开启价格
     *   - elite (int) 多人副本购买次数价格
     */
    public const array ROWS = [
        1 => ['id' => 1, 'act' => 50, 'leap' => 1, 'storm' => 2, 'levy' => 50, 'jade1' => 10, 'jade2' => 10, 'jade3' => 10, 'pack' => 5, 'elite' => 2],
        2 => ['id' => 2, 'act' => 50, 'leap' => 2, 'storm' => 4, 'levy' => 50, 'jade1' => 20, 'jade2' => 20, 'jade3' => 20, 'pack' => 10, 'elite' => 4],
        3 => ['id' => 3, 'act' => 50, 'leap' => 3, 'storm' => 6, 'levy' => 50, 'jade1' => 30, 'jade2' => 30, 'jade3' => 30, 'pack' => 15, 'elite' => 6],
        4 => ['id' => 4, 'act' => 50, 'leap' => 4, 'storm' => 8, 'levy' => 50, 'jade1' => 40, 'jade2' => 40, 'jade3' => 40, 'pack' => 20, 'elite' => 8],
        5 => ['id' => 5, 'act' => 50, 'leap' => 5, 'storm' => 10, 'levy' => 50, 'jade1' => 50, 'jade2' => 50, 'jade3' => 50, 'pack' => 25, 'elite' => 10],
        6 => ['id' => 6, 'act' => 50, 'leap' => 6, 'storm' => 12, 'levy' => 50, 'jade1' => 60, 'jade2' => 60, 'jade3' => 60, 'pack' => 30, 'elite' => 12],
        7 => ['id' => 7, 'act' => 50, 'leap' => 7, 'storm' => 14, 'levy' => 50, 'jade1' => 70, 'jade2' => 70, 'jade3' => 70, 'pack' => 35, 'elite' => 14],
        8 => ['id' => 8, 'act' => 50, 'leap' => 8, 'storm' => 16, 'levy' => 50, 'jade1' => 80, 'jade2' => 80, 'jade3' => 80, 'pack' => 40, 'elite' => 16],
        9 => ['id' => 9, 'act' => 50, 'leap' => 9, 'storm' => 18, 'levy' => 50, 'jade1' => 90, 'jade2' => 90, 'jade3' => 90, 'pack' => 45, 'elite' => 18],
        10 => ['id' => 10, 'act' => 50, 'leap' => 10, 'storm' => 20, 'levy' => 50, 'jade1' => 100, 'jade2' => 100, 'jade3' => 100, 'pack' => 50, 'elite' => 20],
        11 => ['id' => 11, 'act' => 50, 'leap' => 11, 'storm' => 22, 'levy' => 50, 'jade1' => 110, 'jade2' => 110, 'jade3' => 110, 'pack' => 50, 'elite' => 22],
        12 => ['id' => 12, 'act' => 50, 'leap' => 12, 'storm' => 24, 'levy' => 50, 'jade1' => 120, 'jade2' => 120, 'jade3' => 120, 'pack' => 50, 'elite' => 24],
        13 => ['id' => 13, 'act' => 50, 'leap' => 13, 'storm' => 26, 'levy' => 50, 'jade1' => 130, 'jade2' => 130, 'jade3' => 130, 'pack' => 50, 'elite' => 26],
        14 => ['id' => 14, 'act' => 50, 'leap' => 14, 'storm' => 28, 'levy' => 50, 'jade1' => 140, 'jade2' => 140, 'jade3' => 140, 'pack' => 50, 'elite' => 28],
        15 => ['id' => 15, 'act' => 50, 'leap' => 15, 'storm' => 30, 'levy' => 50, 'jade1' => 150, 'jade2' => 150, 'jade3' => 150, 'pack' => 50, 'elite' => 30],
        16 => ['id' => 16, 'act' => 50, 'leap' => 16, 'storm' => 32, 'levy' => 50, 'jade1' => 160, 'jade2' => 160, 'jade3' => 160, 'pack' => 50, 'elite' => 32],
        17 => ['id' => 17, 'act' => 50, 'leap' => 17, 'storm' => 34, 'levy' => 50, 'jade1' => 170, 'jade2' => 170, 'jade3' => 170, 'pack' => 50, 'elite' => 34],
        18 => ['id' => 18, 'act' => 50, 'leap' => 18, 'storm' => 36, 'levy' => 50, 'jade1' => 180, 'jade2' => 180, 'jade3' => 180, 'pack' => 50, 'elite' => 36],
        19 => ['id' => 19, 'act' => 50, 'leap' => 19, 'storm' => 38, 'levy' => 50, 'jade1' => 190, 'jade2' => 190, 'jade3' => 190, 'pack' => 50, 'elite' => 38],
        20 => ['id' => 20, 'act' => 50, 'leap' => 20, 'storm' => 40, 'levy' => 50, 'jade1' => 200, 'jade2' => 200, 'jade3' => 200, 'pack' => 50, 'elite' => 40],
        21 => ['id' => 21, 'act' => 50, 'leap' => 21, 'storm' => 42, 'levy' => 50, 'jade1' => 210, 'jade2' => 210, 'jade3' => 210, 'pack' => 50, 'elite' => 42],
        22 => ['id' => 22, 'act' => 50, 'leap' => 22, 'storm' => 44, 'levy' => 50, 'jade1' => 220, 'jade2' => 220, 'jade3' => 220, 'pack' => 50, 'elite' => 44],
        23 => ['id' => 23, 'act' => 50, 'leap' => 23, 'storm' => 46, 'levy' => 50, 'jade1' => 230, 'jade2' => 230, 'jade3' => 230, 'pack' => 50, 'elite' => 46],
        24 => ['id' => 24, 'act' => 50, 'leap' => 24, 'storm' => 48, 'levy' => 50, 'jade1' => 240, 'jade2' => 240, 'jade3' => 240, 'pack' => 50, 'elite' => 48],
        25 => ['id' => 25, 'act' => 50, 'leap' => 25, 'storm' => 50, 'levy' => 50, 'jade1' => 250, 'jade2' => 250, 'jade3' => 250, 'pack' => 50, 'elite' => 50],
        26 => ['id' => 26, 'act' => 50, 'leap' => 26, 'storm' => 50, 'levy' => 50, 'jade1' => 260, 'jade2' => 260, 'jade3' => 260, 'pack' => 50, 'elite' => 50],
        27 => ['id' => 27, 'act' => 50, 'leap' => 27, 'storm' => 50, 'levy' => 50, 'jade1' => 270, 'jade2' => 270, 'jade3' => 270, 'pack' => 50, 'elite' => 50],
        28 => ['id' => 28, 'act' => 50, 'leap' => 28, 'storm' => 50, 'levy' => 50, 'jade1' => 280, 'jade2' => 280, 'jade3' => 280, 'pack' => 50, 'elite' => 50],
        29 => ['id' => 29, 'act' => 50, 'leap' => 29, 'storm' => 50, 'levy' => 50, 'jade1' => 290, 'jade2' => 290, 'jade3' => 290, 'pack' => 50, 'elite' => 50],
        30 => ['id' => 30, 'act' => 50, 'leap' => 30, 'storm' => 50, 'levy' => 50, 'jade1' => 300, 'jade2' => 300, 'jade3' => 300, 'pack' => 50, 'elite' => 50],
        31 => ['id' => 31, 'act' => 50, 'leap' => 31, 'storm' => 50, 'levy' => 50, 'jade1' => 310, 'jade2' => 310, 'jade3' => 310, 'pack' => 50, 'elite' => 50],
        32 => ['id' => 32, 'act' => 50, 'leap' => 32, 'storm' => 50, 'levy' => 50, 'jade1' => 320, 'jade2' => 320, 'jade3' => 320, 'pack' => 50, 'elite' => 50],
        33 => ['id' => 33, 'act' => 50, 'leap' => 33, 'storm' => 50, 'levy' => 50, 'jade1' => 330, 'jade2' => 330, 'jade3' => 330, 'pack' => 50, 'elite' => 50],
        34 => ['id' => 34, 'act' => 50, 'leap' => 34, 'storm' => 50, 'levy' => 50, 'jade1' => 340, 'jade2' => 340, 'jade3' => 340, 'pack' => 50, 'elite' => 50],
        35 => ['id' => 35, 'act' => 50, 'leap' => 35, 'storm' => 50, 'levy' => 50, 'jade1' => 350, 'jade2' => 350, 'jade3' => 350, 'pack' => 50, 'elite' => 50],
        36 => ['id' => 36, 'act' => 50, 'leap' => 36, 'storm' => 50, 'levy' => 50, 'jade1' => 360, 'jade2' => 360, 'jade3' => 360, 'pack' => 50, 'elite' => 50],
        37 => ['id' => 37, 'act' => 50, 'leap' => 37, 'storm' => 50, 'levy' => 50, 'jade1' => 370, 'jade2' => 370, 'jade3' => 370, 'pack' => 50, 'elite' => 50],
        38 => ['id' => 38, 'act' => 50, 'leap' => 38, 'storm' => 50, 'levy' => 50, 'jade1' => 380, 'jade2' => 380, 'jade3' => 380, 'pack' => 50, 'elite' => 50],
        39 => ['id' => 39, 'act' => 50, 'leap' => 39, 'storm' => 50, 'levy' => 50, 'jade1' => 390, 'jade2' => 390, 'jade3' => 390, 'pack' => 50, 'elite' => 50],
        40 => ['id' => 40, 'act' => 50, 'leap' => 40, 'storm' => 50, 'levy' => 50, 'jade1' => 400, 'jade2' => 400, 'jade3' => 400, 'pack' => 50, 'elite' => 50],
        41 => ['id' => 41, 'act' => 50, 'leap' => 41, 'storm' => 50, 'levy' => 50, 'jade1' => 410, 'jade2' => 410, 'jade3' => 410, 'pack' => 50, 'elite' => 50],
        42 => ['id' => 42, 'act' => 50, 'leap' => 42, 'storm' => 50, 'levy' => 50, 'jade1' => 420, 'jade2' => 420, 'jade3' => 420, 'pack' => 50, 'elite' => 50],
        43 => ['id' => 43, 'act' => 50, 'leap' => 43, 'storm' => 50, 'levy' => 50, 'jade1' => 430, 'jade2' => 430, 'jade3' => 430, 'pack' => 50, 'elite' => 50],
        44 => ['id' => 44, 'act' => 50, 'leap' => 44, 'storm' => 50, 'levy' => 50, 'jade1' => 440, 'jade2' => 440, 'jade3' => 440, 'pack' => 50, 'elite' => 50],
        45 => ['id' => 45, 'act' => 50, 'leap' => 45, 'storm' => 50, 'levy' => 50, 'jade1' => 450, 'jade2' => 450, 'jade3' => 450, 'pack' => 50, 'elite' => 50],
        46 => ['id' => 46, 'act' => 50, 'leap' => 46, 'storm' => 50, 'levy' => 50, 'jade1' => 460, 'jade2' => 460, 'jade3' => 460, 'pack' => 50, 'elite' => 50],
        47 => ['id' => 47, 'act' => 50, 'leap' => 47, 'storm' => 50, 'levy' => 50, 'jade1' => 470, 'jade2' => 470, 'jade3' => 470, 'pack' => 50, 'elite' => 50],
        48 => ['id' => 48, 'act' => 50, 'leap' => 48, 'storm' => 50, 'levy' => 50, 'jade1' => 480, 'jade2' => 480, 'jade3' => 480, 'pack' => 50, 'elite' => 50],
        49 => ['id' => 49, 'act' => 50, 'leap' => 49, 'storm' => 50, 'levy' => 50, 'jade1' => 490, 'jade2' => 490, 'jade3' => 490, 'pack' => 50, 'elite' => 50],
        50 => ['id' => 50, 'act' => 50, 'leap' => 50, 'storm' => 50, 'levy' => 50, 'jade1' => 500, 'jade2' => 500, 'jade3' => 500, 'pack' => 50, 'elite' => 50],
    ];
}
