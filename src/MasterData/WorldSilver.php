<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 跨服远征银币奖励表（旧版 zc_sanguo_world_silver）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class WorldSilver
{
    public const string TABLE = 'zc_sanguo_world_silver';

    /**
     * 按主键 changed 索引，共 51 行。字段：
     *   - changed (smallint) 排名变化(从0开始)
     *   - reward (smallint) 排行榜玩家奖励银币系数
     *   - bet (smallint) 押注者奖励银币系数
     */
    public const array ROWS = [
        0 => ['changed' => 0, 'reward' => 1000, 'bet' => 500],
        1 => ['changed' => 1, 'reward' => 1100, 'bet' => 550],
        2 => ['changed' => 2, 'reward' => 1200, 'bet' => 600],
        3 => ['changed' => 3, 'reward' => 1300, 'bet' => 650],
        4 => ['changed' => 4, 'reward' => 1400, 'bet' => 700],
        5 => ['changed' => 5, 'reward' => 1500, 'bet' => 750],
        6 => ['changed' => 6, 'reward' => 1600, 'bet' => 800],
        7 => ['changed' => 7, 'reward' => 1700, 'bet' => 850],
        8 => ['changed' => 8, 'reward' => 1800, 'bet' => 900],
        9 => ['changed' => 9, 'reward' => 1900, 'bet' => 950],
        10 => ['changed' => 10, 'reward' => 2000, 'bet' => 1000],
        11 => ['changed' => 11, 'reward' => 2100, 'bet' => 1050],
        12 => ['changed' => 12, 'reward' => 2200, 'bet' => 1100],
        13 => ['changed' => 13, 'reward' => 2300, 'bet' => 1150],
        14 => ['changed' => 14, 'reward' => 2400, 'bet' => 1200],
        15 => ['changed' => 15, 'reward' => 2500, 'bet' => 1250],
        16 => ['changed' => 16, 'reward' => 2600, 'bet' => 1300],
        17 => ['changed' => 17, 'reward' => 2700, 'bet' => 1350],
        18 => ['changed' => 18, 'reward' => 2800, 'bet' => 1400],
        19 => ['changed' => 19, 'reward' => 2900, 'bet' => 1450],
        20 => ['changed' => 20, 'reward' => 3000, 'bet' => 1500],
        21 => ['changed' => 21, 'reward' => 3100, 'bet' => 1550],
        22 => ['changed' => 22, 'reward' => 3200, 'bet' => 1600],
        23 => ['changed' => 23, 'reward' => 3300, 'bet' => 1650],
        24 => ['changed' => 24, 'reward' => 3400, 'bet' => 1700],
        25 => ['changed' => 25, 'reward' => 3500, 'bet' => 1750],
        26 => ['changed' => 26, 'reward' => 3600, 'bet' => 1800],
        27 => ['changed' => 27, 'reward' => 3700, 'bet' => 1850],
        28 => ['changed' => 28, 'reward' => 3800, 'bet' => 1900],
        29 => ['changed' => 29, 'reward' => 3900, 'bet' => 1950],
        30 => ['changed' => 30, 'reward' => 4000, 'bet' => 2000],
        31 => ['changed' => 31, 'reward' => 4100, 'bet' => 2050],
        32 => ['changed' => 32, 'reward' => 4200, 'bet' => 2100],
        33 => ['changed' => 33, 'reward' => 4300, 'bet' => 2150],
        34 => ['changed' => 34, 'reward' => 4400, 'bet' => 2200],
        35 => ['changed' => 35, 'reward' => 4500, 'bet' => 2250],
        36 => ['changed' => 36, 'reward' => 4600, 'bet' => 2300],
        37 => ['changed' => 37, 'reward' => 4700, 'bet' => 2350],
        38 => ['changed' => 38, 'reward' => 4800, 'bet' => 2400],
        39 => ['changed' => 39, 'reward' => 4900, 'bet' => 2450],
        40 => ['changed' => 40, 'reward' => 5000, 'bet' => 2500],
        41 => ['changed' => 41, 'reward' => 5100, 'bet' => 2550],
        42 => ['changed' => 42, 'reward' => 5200, 'bet' => 2600],
        43 => ['changed' => 43, 'reward' => 5300, 'bet' => 2650],
        44 => ['changed' => 44, 'reward' => 5400, 'bet' => 2700],
        45 => ['changed' => 45, 'reward' => 5500, 'bet' => 2750],
        46 => ['changed' => 46, 'reward' => 5600, 'bet' => 2800],
        47 => ['changed' => 47, 'reward' => 5700, 'bet' => 2850],
        48 => ['changed' => 48, 'reward' => 5800, 'bet' => 2900],
        49 => ['changed' => 49, 'reward' => 5900, 'bet' => 2950],
        50 => ['changed' => 50, 'reward' => 6000, 'bet' => 3000],
    ];
}
