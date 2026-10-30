<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 祭神成就表（旧版 zc_sanguo_newsacrifices_achievement）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class NewsacrificesAchievement
{
    public const string TABLE = 'zc_sanguo_newsacrifices_achievement';

    /**
     * 按主键 id 索引，共 13 行。字段：
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
        1 => ['id' => 1, 'times' => 100, 'type1' => 5, 'value1' => 100, 'type2' => 2, 'value2' => 100, 'type3' => 4, 'value3' => 3000],
        2 => ['id' => 2, 'times' => 200, 'type1' => 5, 'value1' => 200, 'type2' => 2, 'value2' => 150, 'type3' => 4, 'value3' => 5000],
        3 => ['id' => 3, 'times' => 500, 'type1' => 5, 'value1' => 300, 'type2' => 2, 'value2' => 300, 'type3' => 4, 'value3' => 8000],
        4 => ['id' => 4, 'times' => 700, 'type1' => 5, 'value1' => 400, 'type2' => 2, 'value2' => 400, 'type3' => 4, 'value3' => 9000],
        5 => ['id' => 5, 'times' => 1000, 'type1' => 5, 'value1' => 500, 'type2' => 2, 'value2' => 500, 'type3' => 4, 'value3' => 10000],
        6 => ['id' => 6, 'times' => 2000, 'type1' => 5, 'value1' => 800, 'type2' => 2, 'value2' => 800, 'type3' => 4, 'value3' => 12000],
        7 => ['id' => 7, 'times' => 3000, 'type1' => 5, 'value1' => 1000, 'type2' => 2, 'value2' => 900, 'type3' => 4, 'value3' => 14000],
        8 => ['id' => 8, 'times' => 4000, 'type1' => 5, 'value1' => 1200, 'type2' => 2, 'value2' => 1000, 'type3' => 4, 'value3' => 16000],
        9 => ['id' => 9, 'times' => 5000, 'type1' => 5, 'value1' => 1500, 'type2' => 2, 'value2' => 1300, 'type3' => 4, 'value3' => 18000],
        10 => ['id' => 10, 'times' => 7000, 'type1' => 5, 'value1' => 2100, 'type2' => 2, 'value2' => 1500, 'type3' => 4, 'value3' => 21000],
        11 => ['id' => 11, 'times' => 10000, 'type1' => 5, 'value1' => 3000, 'type2' => 2, 'value2' => 2000, 'type3' => 4, 'value3' => 30000],
        12 => ['id' => 12, 'times' => 15000, 'type1' => 5, 'value1' => 4500, 'type2' => 8, 'value2' => 72, 'type3' => 8, 'value3' => 73],
        13 => ['id' => 13, 'times' => 20000, 'type1' => 5, 'value1' => 6000, 'type2' => 2, 'value2' => 4000, 'type3' => 8, 'value3' => 76],
    ];
}
