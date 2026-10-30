<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 万圣节商城（旧版 zc_sanguo_halloween_shop）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class HalloweenShop
{
    public const string TABLE = 'zc_sanguo_halloween_shop';

    /**
     * 按主键 id 索引，共 30 行。字段：
     *   - id (int) 自增ID
     *   - type (int) 奖励类型
     *   - value (int) 奖励数量或者装备精华id
     *   - value1 (int) 奖励数量2(装备或者精华数量)
     *   - medal (int) 消耗代币
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => 2, 'value' => 2400000, 'value1' => 0, 'medal' => 10],
        2 => ['id' => 2, 'type' => 11, 'value' => 180, 'value1' => 0, 'medal' => 10],
        3 => ['id' => 3, 'type' => 3, 'value' => 90, 'value1' => 0, 'medal' => 10],
        4 => ['id' => 4, 'type' => 4, 'value' => 30, 'value1' => 0, 'medal' => 10],
        5 => ['id' => 5, 'type' => 5, 'value' => 1200, 'value1' => 0, 'medal' => 10],
        6 => ['id' => 6, 'type' => 6, 'value' => 240000, 'value1' => 0, 'medal' => 10],
        7 => ['id' => 7, 'type' => 7, 'value' => 3600, 'value1' => 0, 'medal' => 10],
        8 => ['id' => 8, 'type' => 9, 'value' => 24000, 'value1' => 0, 'medal' => 10],
        9 => ['id' => 9, 'type' => 10, 'value' => 36, 'value1' => 0, 'medal' => 10],
        10 => ['id' => 10, 'type' => 14, 'value' => 18000, 'value1' => 0, 'medal' => 10],
        11 => ['id' => 11, 'type' => 15, 'value' => 180, 'value1' => 0, 'medal' => 10],
        12 => ['id' => 12, 'type' => 16, 'value' => 55, 'value1' => 0, 'medal' => 10],
        13 => ['id' => 13, 'type' => 11, 'value' => 2160, 'value1' => 0, 'medal' => 100],
        14 => ['id' => 14, 'type' => 3, 'value' => 1080, 'value1' => 0, 'medal' => 100],
        15 => ['id' => 15, 'type' => 4, 'value' => 360, 'value1' => 0, 'medal' => 100],
        16 => ['id' => 16, 'type' => 5, 'value' => 14400, 'value1' => 0, 'medal' => 100],
        17 => ['id' => 17, 'type' => 6, 'value' => 2880000, 'value1' => 0, 'medal' => 100],
        18 => ['id' => 18, 'type' => 7, 'value' => 43200, 'value1' => 0, 'medal' => 100],
        19 => ['id' => 19, 'type' => 9, 'value' => 288000, 'value1' => 0, 'medal' => 100],
        20 => ['id' => 20, 'type' => 10, 'value' => 432, 'value1' => 0, 'medal' => 100],
        21 => ['id' => 21, 'type' => 14, 'value' => 216000, 'value1' => 0, 'medal' => 100],
        22 => ['id' => 22, 'type' => 15, 'value' => 2160, 'value1' => 0, 'medal' => 100],
        23 => ['id' => 23, 'type' => 16, 'value' => 660, 'value1' => 0, 'medal' => 100],
        24 => ['id' => 24, 'type' => 8, 'value' => 72, 'value1' => 0, 'medal' => 2000],
        25 => ['id' => 25, 'type' => 8, 'value' => 73, 'value1' => 0, 'medal' => 2000],
        26 => ['id' => 26, 'type' => 8, 'value' => 74, 'value1' => 0, 'medal' => 1500],
        27 => ['id' => 27, 'type' => 8, 'value' => 75, 'value1' => 0, 'medal' => 1500],
        28 => ['id' => 28, 'type' => 8, 'value' => 76, 'value1' => 0, 'medal' => 2500],
        29 => ['id' => 29, 'type' => 26, 'value' => 106, 'value1' => 0, 'medal' => 1500],
        30 => ['id' => 30, 'type' => 31, 'value' => 65, 'value1' => 0, 'medal' => 500],
    ];
}
