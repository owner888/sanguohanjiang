<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 祭祖成就表（旧版 zc_sanguo_chingming_achievement）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ChingmingAchievement
{
    public const string TABLE = 'zc_sanguo_chingming_achievement';

    /**
     * 按主键 id 索引，共 21 行。字段：
     *   - id (int)
     *   - times (int)
     *   - type1 (int)
     *   - value1 (int)
     *   - type2 (int)
     *   - value2 (int)
     *   - type3 (int)
     *   - value3 (int)
     */
    public const array ROWS = [
        1 => ['id' => 1, 'times' => 100, 'type1' => 5, 'value1' => 200, 'type2' => 2, 'value2' => 150, 'type3' => 4, 'value3' => 5000],
        2 => ['id' => 2, 'times' => 200, 'type1' => 5, 'value1' => 300, 'type2' => 2, 'value2' => 300, 'type3' => 4, 'value3' => 8000],
        3 => ['id' => 3, 'times' => 300, 'type1' => 5, 'value1' => 400, 'type2' => 2, 'value2' => 400, 'type3' => 4, 'value3' => 9000],
        4 => ['id' => 4, 'times' => 500, 'type1' => 5, 'value1' => 500, 'type2' => 2, 'value2' => 800, 'type3' => 4, 'value3' => 12000],
        5 => ['id' => 5, 'times' => 1000, 'type1' => 5, 'value1' => 800, 'type2' => 8, 'value2' => 62, 'type3' => 8, 'value3' => 65],
        6 => ['id' => 6, 'times' => 1200, 'type1' => 5, 'value1' => 1000, 'type2' => 2, 'value2' => 900, 'type3' => 4, 'value3' => 14000],
        7 => ['id' => 7, 'times' => 1400, 'type1' => 5, 'value1' => 1200, 'type2' => 2, 'value2' => 1000, 'type3' => 4, 'value3' => 16000],
        8 => ['id' => 8, 'times' => 1600, 'type1' => 5, 'value1' => 1500, 'type2' => 2, 'value2' => 1300, 'type3' => 4, 'value3' => 18000],
        9 => ['id' => 9, 'times' => 1800, 'type1' => 5, 'value1' => 2100, 'type2' => 2, 'value2' => 1500, 'type3' => 4, 'value3' => 21000],
        10 => ['id' => 10, 'times' => 2000, 'type1' => 5, 'value1' => 3000, 'type2' => 2, 'value2' => 2000, 'type3' => 4, 'value3' => 30000],
        11 => ['id' => 11, 'times' => 6000, 'type1' => 5, 'value1' => 4500, 'type2' => 8, 'value2' => 72, 'type3' => 8, 'value3' => 73],
        12 => ['id' => 12, 'times' => 9000, 'type1' => 5, 'value1' => 6000, 'type2' => 2, 'value2' => 4000, 'type3' => 8, 'value3' => 76],
        13 => ['id' => 13, 'times' => 12000, 'type1' => 5, 'value1' => 8000, 'type2' => 2, 'value2' => 5000, 'type3' => 0, 'value3' => 0],
        14 => ['id' => 14, 'times' => 15000, 'type1' => 5, 'value1' => 10000, 'type2' => 2, 'value2' => 6000, 'type3' => 0, 'value3' => 0],
        15 => ['id' => 15, 'times' => 18000, 'type1' => 5, 'value1' => 12000, 'type2' => 2, 'value2' => 7000, 'type3' => 0, 'value3' => 0],
        16 => ['id' => 16, 'times' => 21000, 'type1' => 5, 'value1' => 15000, 'type2' => 2, 'value2' => 8000, 'type3' => 0, 'value3' => 0],
        17 => ['id' => 17, 'times' => 24000, 'type1' => 2, 'value1' => 8000, 'type2' => 8, 'value2' => 214, 'type3' => 0, 'value3' => 0],
        18 => ['id' => 18, 'times' => 27000, 'type1' => 2, 'value1' => 8000, 'type2' => 8, 'value2' => 204, 'type3' => 0, 'value3' => 0],
        19 => ['id' => 19, 'times' => 30000, 'type1' => 2, 'value1' => 8000, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0],
        20 => ['id' => 20, 'times' => 40000, 'type1' => 2, 'value1' => 12000, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0],
        21 => ['id' => 21, 'times' => 50000, 'type1' => 2, 'value1' => 15000, 'type2' => 5, 'value2' => 20000, 'type3' => 0, 'value3' => 0],
    ];
}
