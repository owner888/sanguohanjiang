<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 国师授艺数据表（旧版 zc_sanguo_country_learn）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class CountryLearn
{
    public const string TABLE = 'zc_sanguo_country_learn';

    /**
     * 按主键 id 索引，共 3 行。字段：
     *   - id (tinyint)
     *   - exp (mediumint) 经验奖励
     *   - gold (int) 花费元宝
     */
    public const array ROWS = [
        1 => ['id' => 1, 'exp' => 1125, 'gold' => 10],
        2 => ['id' => 2, 'exp' => 6750, 'gold' => 100],
        3 => ['id' => 3, 'exp' => 22500, 'gold' => 600],
    ];
}
