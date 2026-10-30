<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * city_addition（旧版 zc_sanguo_city_addition）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class CityAddition
{
    public const string TABLE = 'zc_sanguo_city_addition';

    /**
     * 按主键 id 索引，共 21 行。字段：
     *   - id (bigint) 流水号
     *   - addition (int) 加成系数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'addition' => 200],
        2 => ['id' => 2, 'addition' => 205],
        3 => ['id' => 3, 'addition' => 210],
        4 => ['id' => 4, 'addition' => 215],
        5 => ['id' => 5, 'addition' => 220],
        6 => ['id' => 6, 'addition' => 225],
        7 => ['id' => 7, 'addition' => 230],
        8 => ['id' => 8, 'addition' => 235],
        9 => ['id' => 9, 'addition' => 240],
        10 => ['id' => 10, 'addition' => 245],
        11 => ['id' => 11, 'addition' => 250],
        12 => ['id' => 12, 'addition' => 255],
        13 => ['id' => 13, 'addition' => 260],
        14 => ['id' => 14, 'addition' => 265],
        15 => ['id' => 15, 'addition' => 270],
        16 => ['id' => 16, 'addition' => 275],
        17 => ['id' => 17, 'addition' => 280],
        18 => ['id' => 18, 'addition' => 285],
        19 => ['id' => 19, 'addition' => 290],
        20 => ['id' => 20, 'addition' => 295],
        21 => ['id' => 21, 'addition' => 300],
    ];
}
