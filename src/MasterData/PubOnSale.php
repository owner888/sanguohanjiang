<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 武将打折表（旧版 zc_sanguo_pub_on_sale）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class PubOnSale
{
    public const string TABLE = 'zc_sanguo_pub_on_sale';

    /**
     * 按主键 generalid 索引，共 33 行。字段：
     *   - generalid (smallint) 将领id
     *   - paytype (tinyint) 宝石类型(1蓝;2黄;3紫)
     *   - price (int) 数量
     *   - position (tinyint) 位置
     *   - version (tinyint) 版本
     */
    public const array ROWS = [
        33 => ['generalid' => 33, 'paytype' => 3, 'price' => 1280, 'position' => 2, 'version' => 1],
        34 => ['generalid' => 34, 'paytype' => 3, 'price' => 960, 'position' => 1, 'version' => 1],
        35 => ['generalid' => 35, 'paytype' => 3, 'price' => 640, 'position' => 3, 'version' => 1],
        38 => ['generalid' => 38, 'paytype' => 3, 'price' => 1520, 'position' => 2, 'version' => 5],
        49 => ['generalid' => 49, 'paytype' => 3, 'price' => 2240, 'position' => 2, 'version' => 7],
        50 => ['generalid' => 50, 'paytype' => 3, 'price' => 2240, 'position' => 2, 'version' => 6],
        52 => ['generalid' => 52, 'paytype' => 3, 'price' => 1440, 'position' => 2, 'version' => 3],
        53 => ['generalid' => 53, 'paytype' => 3, 'price' => 1120, 'position' => 2, 'version' => 2],
        54 => ['generalid' => 54, 'paytype' => 3, 'price' => 1520, 'position' => 3, 'version' => 4],
        55 => ['generalid' => 55, 'paytype' => 3, 'price' => 1200, 'position' => 1, 'version' => 3],
        56 => ['generalid' => 56, 'paytype' => 3, 'price' => 560, 'position' => 1, 'version' => 2],
        57 => ['generalid' => 57, 'paytype' => 3, 'price' => 1600, 'position' => 2, 'version' => 4],
        58 => ['generalid' => 58, 'paytype' => 3, 'price' => 600, 'position' => 3, 'version' => 3],
        60 => ['generalid' => 60, 'paytype' => 3, 'price' => 1920, 'position' => 2, 'version' => 8],
        61 => ['generalid' => 61, 'paytype' => 3, 'price' => 1440, 'position' => 3, 'version' => 6],
        62 => ['generalid' => 62, 'paytype' => 2, 'price' => 800, 'position' => 3, 'version' => 2],
        63 => ['generalid' => 63, 'paytype' => 3, 'price' => 800, 'position' => 1, 'version' => 5],
        64 => ['generalid' => 64, 'paytype' => 3, 'price' => 640, 'position' => 3, 'version' => 10],
        65 => ['generalid' => 65, 'paytype' => 3, 'price' => 1200, 'position' => 1, 'version' => 6],
        66 => ['generalid' => 66, 'paytype' => 2, 'price' => 1600, 'position' => 3, 'version' => 5],
        68 => ['generalid' => 68, 'paytype' => 2, 'price' => 1520, 'position' => 2, 'version' => 9],
        72 => ['generalid' => 72, 'paytype' => 3, 'price' => 2240, 'position' => 2, 'version' => 10],
        73 => ['generalid' => 73, 'paytype' => 3, 'price' => 1560, 'position' => 1, 'version' => 4],
        74 => ['generalid' => 74, 'paytype' => 2, 'price' => 2240, 'position' => 1, 'version' => 8],
        75 => ['generalid' => 75, 'paytype' => 2, 'price' => 1920, 'position' => 1, 'version' => 7],
        76 => ['generalid' => 76, 'paytype' => 3, 'price' => 2400, 'position' => 2, 'version' => 11],
        77 => ['generalid' => 77, 'paytype' => 2, 'price' => 2000, 'position' => 3, 'version' => 8],
        78 => ['generalid' => 78, 'paytype' => 3, 'price' => 1200, 'position' => 3, 'version' => 7],
        84 => ['generalid' => 84, 'paytype' => 2, 'price' => 1520, 'position' => 1, 'version' => 9],
        85 => ['generalid' => 85, 'paytype' => 2, 'price' => 1520, 'position' => 3, 'version' => 9],
        86 => ['generalid' => 86, 'paytype' => 2, 'price' => 2800, 'position' => 1, 'version' => 10],
        87 => ['generalid' => 87, 'paytype' => 3, 'price' => 2160, 'position' => 1, 'version' => 11],
        88 => ['generalid' => 88, 'paytype' => 3, 'price' => 1240, 'position' => 3, 'version' => 11],
    ];
}
