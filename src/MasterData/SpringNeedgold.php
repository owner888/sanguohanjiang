<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 记录每次抽奖所需元宝（旧版 zc_sanguo_spring_needgold）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class SpringNeedgold
{
    public const string TABLE = 'zc_sanguo_spring_needgold';

    /**
     * 按主键 id 索引，共 16 行。字段：
     *   - id (int) 抽奖次数
     *   - gold (int) 所需元宝
     */
    public const array ROWS = [
        1 => ['id' => 1, 'gold' => 50],
        2 => ['id' => 2, 'gold' => 60],
        3 => ['id' => 3, 'gold' => 70],
        4 => ['id' => 4, 'gold' => 80],
        5 => ['id' => 5, 'gold' => 90],
        6 => ['id' => 6, 'gold' => 100],
        7 => ['id' => 7, 'gold' => 110],
        8 => ['id' => 8, 'gold' => 120],
        9 => ['id' => 9, 'gold' => 130],
        10 => ['id' => 10, 'gold' => 140],
        11 => ['id' => 11, 'gold' => 150],
        12 => ['id' => 12, 'gold' => 160],
        13 => ['id' => 13, 'gold' => 170],
        14 => ['id' => 14, 'gold' => 180],
        15 => ['id' => 15, 'gold' => 190],
        16 => ['id' => 16, 'gold' => 200],
    ];
}
