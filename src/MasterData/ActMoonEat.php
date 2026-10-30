<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 吃月饼奖励表（旧版 zc_sanguo_act_moon_eat）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActMoonEat
{
    public const string TABLE = 'zc_sanguo_act_moon_eat';

    /**
     * 按主键 id 索引，共 53 行。字段：
     *   - id (int) 自增id
     *   - type (mediumint) 排名
     *   - params (mediumint) 参数值
     *   - rate (mediumint) 获得权重
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => 10, 'params' => 2, 'rate' => 10],
        2 => ['id' => 2, 'type' => 10, 'params' => 3, 'rate' => 10],
        3 => ['id' => 3, 'type' => 10, 'params' => 4, 'rate' => 10],
        4 => ['id' => 4, 'type' => 10, 'params' => 5, 'rate' => 5],
        5 => ['id' => 5, 'type' => 10, 'params' => 6, 'rate' => 5],
        6 => ['id' => 6, 'type' => 4, 'params' => 1, 'rate' => 15],
        7 => ['id' => 7, 'type' => 4, 'params' => 2, 'rate' => 30],
        8 => ['id' => 8, 'type' => 4, 'params' => 3, 'rate' => 5],
        9 => ['id' => 9, 'type' => 4, 'params' => 4, 'rate' => 5],
        10 => ['id' => 10, 'type' => 4, 'params' => 5, 'rate' => 1],
        11 => ['id' => 11, 'type' => 4, 'params' => 6, 'rate' => 1],
        12 => ['id' => 12, 'type' => 7, 'params' => 100, 'rate' => 15],
        13 => ['id' => 13, 'type' => 7, 'params' => 200, 'rate' => 30],
        14 => ['id' => 14, 'type' => 7, 'params' => 300, 'rate' => 5],
        15 => ['id' => 15, 'type' => 7, 'params' => 400, 'rate' => 5],
        16 => ['id' => 16, 'type' => 7, 'params' => 500, 'rate' => 1],
        17 => ['id' => 17, 'type' => 7, 'params' => 600, 'rate' => 1],
        18 => ['id' => 18, 'type' => 5, 'params' => 100, 'rate' => 5],
        19 => ['id' => 19, 'type' => 5, 'params' => 150, 'rate' => 15],
        20 => ['id' => 20, 'type' => 5, 'params' => 200, 'rate' => 10],
        21 => ['id' => 21, 'type' => 5, 'params' => 250, 'rate' => 5],
        22 => ['id' => 22, 'type' => 27, 'params' => 1, 'rate' => 5],
        23 => ['id' => 23, 'type' => 27, 'params' => 2, 'rate' => 5],
        24 => ['id' => 24, 'type' => 27, 'params' => 3, 'rate' => 5],
        25 => ['id' => 25, 'type' => 28, 'params' => 1, 'rate' => 5],
        26 => ['id' => 26, 'type' => 28, 'params' => 2, 'rate' => 1],
        27 => ['id' => 27, 'type' => 28, 'params' => 3, 'rate' => 1],
        28 => ['id' => 28, 'type' => 3, 'params' => 5, 'rate' => 5],
        29 => ['id' => 29, 'type' => 3, 'params' => 6, 'rate' => 5],
        30 => ['id' => 30, 'type' => 3, 'params' => 7, 'rate' => 10],
        31 => ['id' => 31, 'type' => 3, 'params' => 8, 'rate' => 10],
        32 => ['id' => 32, 'type' => 3, 'params' => 9, 'rate' => 10],
        33 => ['id' => 33, 'type' => 3, 'params' => 10, 'rate' => 5],
        34 => ['id' => 34, 'type' => 14, 'params' => 500, 'rate' => 5],
        35 => ['id' => 35, 'type' => 14, 'params' => 1000, 'rate' => 10],
        36 => ['id' => 36, 'type' => 14, 'params' => 1500, 'rate' => 10],
        37 => ['id' => 37, 'type' => 14, 'params' => 2000, 'rate' => 5],
        38 => ['id' => 38, 'type' => 14, 'params' => 3000, 'rate' => 1],
        39 => ['id' => 39, 'type' => 15, 'params' => 5, 'rate' => 5],
        40 => ['id' => 40, 'type' => 15, 'params' => 8, 'rate' => 10],
        41 => ['id' => 41, 'type' => 15, 'params' => 10, 'rate' => 10],
        42 => ['id' => 42, 'type' => 15, 'params' => 12, 'rate' => 5],
        43 => ['id' => 43, 'type' => 15, 'params' => 15, 'rate' => 1],
        44 => ['id' => 44, 'type' => 16, 'params' => 1, 'rate' => 10],
        45 => ['id' => 45, 'type' => 16, 'params' => 2, 'rate' => 5],
        46 => ['id' => 46, 'type' => 16, 'params' => 3, 'rate' => 3],
        47 => ['id' => 47, 'type' => 16, 'params' => 4, 'rate' => 2],
        48 => ['id' => 48, 'type' => 16, 'params' => 5, 'rate' => 1],
        49 => ['id' => 49, 'type' => 6, 'params' => 20000, 'rate' => 10],
        50 => ['id' => 50, 'type' => 6, 'params' => 30000, 'rate' => 10],
        51 => ['id' => 51, 'type' => 6, 'params' => 40000, 'rate' => 10],
        52 => ['id' => 52, 'type' => 6, 'params' => 50000, 'rate' => 5],
        53 => ['id' => 53, 'type' => 6, 'params' => 60000, 'rate' => 1],
    ];
}
