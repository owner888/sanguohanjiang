<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 整军演武征收状态榜（旧版 zc_sanguo_exercise_addition）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ExerciseAddition
{
    public const string TABLE = 'zc_sanguo_exercise_addition';

    /**
     * 按主键 id 索引，共 30 行。字段：
     *   - id (tinyint) 排名
     *   - addition (smallint) 收益（百分比）
     */
    public const array ROWS = [
        1 => ['id' => 1, 'addition' => 300],
        2 => ['id' => 2, 'addition' => 290],
        3 => ['id' => 3, 'addition' => 280],
        4 => ['id' => 4, 'addition' => 270],
        5 => ['id' => 5, 'addition' => 260],
        6 => ['id' => 6, 'addition' => 250],
        7 => ['id' => 7, 'addition' => 240],
        8 => ['id' => 8, 'addition' => 230],
        9 => ['id' => 9, 'addition' => 220],
        10 => ['id' => 10, 'addition' => 210],
        11 => ['id' => 11, 'addition' => 200],
        12 => ['id' => 12, 'addition' => 195],
        13 => ['id' => 13, 'addition' => 190],
        14 => ['id' => 14, 'addition' => 185],
        15 => ['id' => 15, 'addition' => 180],
        16 => ['id' => 16, 'addition' => 175],
        17 => ['id' => 17, 'addition' => 170],
        18 => ['id' => 18, 'addition' => 165],
        19 => ['id' => 19, 'addition' => 160],
        20 => ['id' => 20, 'addition' => 155],
        21 => ['id' => 21, 'addition' => 150],
        22 => ['id' => 22, 'addition' => 145],
        23 => ['id' => 23, 'addition' => 140],
        24 => ['id' => 24, 'addition' => 135],
        25 => ['id' => 25, 'addition' => 130],
        26 => ['id' => 26, 'addition' => 125],
        27 => ['id' => 27, 'addition' => 120],
        28 => ['id' => 28, 'addition' => 115],
        29 => ['id' => 29, 'addition' => 110],
        30 => ['id' => 30, 'addition' => 105],
    ];
}
