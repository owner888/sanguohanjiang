<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 坐骑等级表（旧版 zc_sanguo_mount_level）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class MountLevel
{
    public const string TABLE = 'zc_sanguo_mount_level';

    /**
     * 按主键 level 索引，共 21 行。字段：
     *   - level (int) 等级
     *   - coefficient (float) 等级增强系数
     *   - exp (int) 坐骑经验槽
     */
    public const array ROWS = [
        0 => ['level' => 0, 'coefficient' => 1.0, 'exp' => 100],
        1 => ['level' => 1, 'coefficient' => 1.35, 'exp' => 180],
        2 => ['level' => 2, 'coefficient' => 1.823, 'exp' => 324],
        3 => ['level' => 3, 'coefficient' => 2.462, 'exp' => 584],
        4 => ['level' => 4, 'coefficient' => 3.324, 'exp' => 1052],
        5 => ['level' => 5, 'coefficient' => 4.488, 'exp' => 1894],
        6 => ['level' => 6, 'coefficient' => 6.059, 'exp' => 3410],
        7 => ['level' => 7, 'coefficient' => 8.18, 'exp' => 6138],
        8 => ['level' => 8, 'coefficient' => 11.043, 'exp' => 11049],
        9 => ['level' => 9, 'coefficient' => 14.909, 'exp' => 19889],
        10 => ['level' => 10, 'coefficient' => 20.128, 'exp' => 35801],
        11 => ['level' => 11, 'coefficient' => 27.173, 'exp' => 64442],
        12 => ['level' => 12, 'coefficient' => 36.684, 'exp' => 115996],
        13 => ['level' => 13, 'coefficient' => 49.524, 'exp' => 208793],
        14 => ['level' => 14, 'coefficient' => 66.858, 'exp' => 375828],
        15 => ['level' => 15, 'coefficient' => 90.259, 'exp' => 676491],
        16 => ['level' => 16, 'coefficient' => 121.85, 'exp' => 1217684],
        17 => ['level' => 17, 'coefficient' => 164.498, 'exp' => 2191832],
        18 => ['level' => 18, 'coefficient' => 222.073, 'exp' => 3945298],
        19 => ['level' => 19, 'coefficient' => 299.799, 'exp' => 7101537],
        20 => ['level' => 20, 'coefficient' => 404.729, 'exp' => 0],
    ];
}
