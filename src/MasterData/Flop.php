<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 四方来朝数据表（旧版 zc_sanguo_flop）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Flop
{
    public const string TABLE = 'zc_sanguo_flop';

    /**
     * 按主键 id 索引，共 28 行。字段：
     *   - id (tinyint)
     *   - gold (int) 消耗元宝
     *   - act (int) 消耗军令
     *   - leap (int) 消耗突飞卡
     */
    public const array ROWS = [
        1 => ['id' => 1, 'gold' => 10, 'act' => 5, 'leap' => 2],
        2 => ['id' => 2, 'gold' => 30, 'act' => 7, 'leap' => 4],
        3 => ['id' => 3, 'gold' => 50, 'act' => 9, 'leap' => 6],
        4 => ['id' => 4, 'gold' => 70, 'act' => 11, 'leap' => 8],
        5 => ['id' => 5, 'gold' => 90, 'act' => 13, 'leap' => 10],
        6 => ['id' => 6, 'gold' => 110, 'act' => 15, 'leap' => 12],
        7 => ['id' => 7, 'gold' => 130, 'act' => 17, 'leap' => 14],
        8 => ['id' => 8, 'gold' => 150, 'act' => 19, 'leap' => 16],
        9 => ['id' => 9, 'gold' => 170, 'act' => 21, 'leap' => 18],
        10 => ['id' => 10, 'gold' => 190, 'act' => 23, 'leap' => 20],
        11 => ['id' => 11, 'gold' => 210, 'act' => 25, 'leap' => 22],
        12 => ['id' => 12, 'gold' => 230, 'act' => 27, 'leap' => 24],
        13 => ['id' => 13, 'gold' => 250, 'act' => 29, 'leap' => 26],
        14 => ['id' => 14, 'gold' => 270, 'act' => 31, 'leap' => 28],
        15 => ['id' => 15, 'gold' => 290, 'act' => 33, 'leap' => 30],
        16 => ['id' => 16, 'gold' => 310, 'act' => 35, 'leap' => 32],
        17 => ['id' => 17, 'gold' => 330, 'act' => 37, 'leap' => 34],
        18 => ['id' => 18, 'gold' => 350, 'act' => 39, 'leap' => 36],
        19 => ['id' => 19, 'gold' => 370, 'act' => 41, 'leap' => 38],
        20 => ['id' => 20, 'gold' => 390, 'act' => 43, 'leap' => 40],
        21 => ['id' => 21, 'gold' => 410, 'act' => 45, 'leap' => 42],
        22 => ['id' => 22, 'gold' => 430, 'act' => 47, 'leap' => 44],
        23 => ['id' => 23, 'gold' => 450, 'act' => 49, 'leap' => 46],
        24 => ['id' => 24, 'gold' => 470, 'act' => 50, 'leap' => 48],
        25 => ['id' => 25, 'gold' => 490, 'act' => 50, 'leap' => 50],
        26 => ['id' => 26, 'gold' => 500, 'act' => 50, 'leap' => 52],
        27 => ['id' => 27, 'gold' => 500, 'act' => 50, 'leap' => 54],
        28 => ['id' => 28, 'gold' => 500, 'act' => 50, 'leap' => 56],
    ];
}
