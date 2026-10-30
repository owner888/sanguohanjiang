<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 天下史诗残卷数据表（旧版 zc_sanguo_epic_chip）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class EpicChip
{
    public const string TABLE = 'zc_sanguo_epic_chip';

    /**
     * 按主键 id 索引，共 45 行。字段：
     *   - id (smallint) 残卷id
     *   - epicid (smallint) 所属史诗id
     *   - rate (double) 出现权重
     */
    public const array ROWS = [
        1 => ['id' => 1, 'epicid' => 1, 'rate' => 100.0],
        2 => ['id' => 2, 'epicid' => 1, 'rate' => 100.0],
        3 => ['id' => 3, 'epicid' => 1, 'rate' => 100.0],
        4 => ['id' => 4, 'epicid' => 1, 'rate' => 100.0],
        5 => ['id' => 5, 'epicid' => 1, 'rate' => 100.0],
        6 => ['id' => 6, 'epicid' => 2, 'rate' => 60.0],
        7 => ['id' => 7, 'epicid' => 2, 'rate' => 60.0],
        8 => ['id' => 8, 'epicid' => 2, 'rate' => 60.0],
        9 => ['id' => 9, 'epicid' => 2, 'rate' => 60.0],
        10 => ['id' => 10, 'epicid' => 2, 'rate' => 60.0],
        11 => ['id' => 11, 'epicid' => 3, 'rate' => 40.0],
        12 => ['id' => 12, 'epicid' => 3, 'rate' => 40.0],
        13 => ['id' => 13, 'epicid' => 3, 'rate' => 40.0],
        14 => ['id' => 14, 'epicid' => 3, 'rate' => 40.0],
        15 => ['id' => 15, 'epicid' => 3, 'rate' => 40.0],
        16 => ['id' => 16, 'epicid' => 4, 'rate' => 25.0],
        17 => ['id' => 17, 'epicid' => 4, 'rate' => 25.0],
        18 => ['id' => 18, 'epicid' => 4, 'rate' => 25.0],
        19 => ['id' => 19, 'epicid' => 4, 'rate' => 25.0],
        20 => ['id' => 20, 'epicid' => 4, 'rate' => 25.0],
        21 => ['id' => 21, 'epicid' => 5, 'rate' => 20.0],
        22 => ['id' => 22, 'epicid' => 5, 'rate' => 20.0],
        23 => ['id' => 23, 'epicid' => 5, 'rate' => 20.0],
        24 => ['id' => 24, 'epicid' => 5, 'rate' => 20.0],
        25 => ['id' => 25, 'epicid' => 5, 'rate' => 20.0],
        26 => ['id' => 26, 'epicid' => 6, 'rate' => 15.0],
        27 => ['id' => 27, 'epicid' => 6, 'rate' => 15.0],
        28 => ['id' => 28, 'epicid' => 6, 'rate' => 15.0],
        29 => ['id' => 29, 'epicid' => 6, 'rate' => 15.0],
        30 => ['id' => 30, 'epicid' => 6, 'rate' => 15.0],
        31 => ['id' => 31, 'epicid' => 7, 'rate' => 10.0],
        32 => ['id' => 32, 'epicid' => 7, 'rate' => 10.0],
        33 => ['id' => 33, 'epicid' => 7, 'rate' => 10.0],
        34 => ['id' => 34, 'epicid' => 7, 'rate' => 10.0],
        35 => ['id' => 35, 'epicid' => 7, 'rate' => 10.0],
        36 => ['id' => 36, 'epicid' => 8, 'rate' => 5.0],
        37 => ['id' => 37, 'epicid' => 8, 'rate' => 5.0],
        38 => ['id' => 38, 'epicid' => 8, 'rate' => 5.0],
        39 => ['id' => 39, 'epicid' => 8, 'rate' => 5.0],
        40 => ['id' => 40, 'epicid' => 8, 'rate' => 5.0],
        41 => ['id' => 41, 'epicid' => 9, 'rate' => 3.0],
        42 => ['id' => 42, 'epicid' => 9, 'rate' => 3.0],
        43 => ['id' => 43, 'epicid' => 9, 'rate' => 3.0],
        44 => ['id' => 44, 'epicid' => 9, 'rate' => 3.0],
        45 => ['id' => 45, 'epicid' => 9, 'rate' => 3.0],
    ];
}
