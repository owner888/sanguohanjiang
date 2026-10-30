<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 宝石强化表（旧版 zc_sanguo_gem_levelup）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class GemLevelup
{
    public const string TABLE = 'zc_sanguo_gem_levelup';

    /**
     * 按主键 level 索引，共 25 行。字段：
     *   - level (tinyint) 宝石等级
     *   - need_chip (int) 所需碎片
     */
    public const array ROWS = [
        1 => ['level' => 1, 'need_chip' => 100],
        2 => ['level' => 2, 'need_chip' => 170],
        3 => ['level' => 3, 'need_chip' => 289],
        4 => ['level' => 4, 'need_chip' => 492],
        5 => ['level' => 5, 'need_chip' => 837],
        6 => ['level' => 6, 'need_chip' => 1423],
        7 => ['level' => 7, 'need_chip' => 2420],
        8 => ['level' => 8, 'need_chip' => 4114],
        9 => ['level' => 9, 'need_chip' => 6994],
        10 => ['level' => 10, 'need_chip' => 11890],
        11 => ['level' => 11, 'need_chip' => 20213],
        12 => ['level' => 12, 'need_chip' => 34363],
        13 => ['level' => 13, 'need_chip' => 58418],
        14 => ['level' => 14, 'need_chip' => 99311],
        15 => ['level' => 15, 'need_chip' => 168829],
        16 => ['level' => 16, 'need_chip' => 287010],
        17 => ['level' => 17, 'need_chip' => 487917],
        18 => ['level' => 18, 'need_chip' => 829459],
        19 => ['level' => 19, 'need_chip' => 1410081],
        20 => ['level' => 20, 'need_chip' => 2397138],
        21 => ['level' => 21, 'need_chip' => 5821518],
        22 => ['level' => 22, 'need_chip' => 2704182],
        23 => ['level' => 23, 'need_chip' => 3326143],
        24 => ['level' => 24, 'need_chip' => 4091155],
        25 => ['level' => 25, 'need_chip' => 5032120],
    ];
}
