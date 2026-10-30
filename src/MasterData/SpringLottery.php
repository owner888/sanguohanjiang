<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 抽奖表（旧版 zc_sanguo_spring_lottery）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class SpringLottery
{
    public const string TABLE = 'zc_sanguo_spring_lottery';

    /**
     * 按主键 id 索引，共 26 行。字段：
     *   - id (int) 奖品id
     *   - group (int) 对应转盘类型
     *   - gettype (int) 奖品类型
     *   - getvalue (int) 参数1
     *   - getvalue1 (int) 参数2
     *   - getrate (float) 权重
     */
    public const array ROWS = [
        1 => ['id' => 1, 'group' => 1, 'gettype' => 8, 'getvalue' => 56, 'getvalue1' => 0, 'getrate' => 0.4],
        2 => ['id' => 2, 'group' => 1, 'gettype' => 4, 'getvalue' => 10, 'getvalue1' => 0, 'getrate' => 25.0],
        3 => ['id' => 3, 'group' => 1, 'gettype' => 4, 'getvalue' => 30, 'getvalue1' => 0, 'getrate' => 15.0],
        4 => ['id' => 4, 'group' => 1, 'gettype' => 5, 'getvalue' => 1500, 'getvalue1' => 0, 'getrate' => 25.0],
        5 => ['id' => 5, 'group' => 1, 'gettype' => 10, 'getvalue' => 30, 'getvalue1' => 0, 'getrate' => 25.0],
        6 => ['id' => 6, 'group' => 1, 'gettype' => 17, 'getvalue' => 60, 'getvalue1' => 0, 'getrate' => 15.0],
        7 => ['id' => 7, 'group' => 1, 'gettype' => 19, 'getvalue' => 22, 'getvalue1' => 3, 'getrate' => 5.0],
        8 => ['id' => 8, 'group' => 1, 'gettype' => 15, 'getvalue' => 20000, 'getvalue1' => 0, 'getrate' => 15.0],
        9 => ['id' => 9, 'group' => 1, 'gettype' => 17, 'getvalue' => 120, 'getvalue1' => 0, 'getrate' => 5.0],
        10 => ['id' => 10, 'group' => 1, 'gettype' => 16, 'getvalue' => 400, 'getvalue1' => 0, 'getrate' => 20.0],
        11 => ['id' => 11, 'group' => 1, 'gettype' => 16, 'getvalue' => 800, 'getvalue1' => 0, 'getrate' => 10.0],
        12 => ['id' => 12, 'group' => 1, 'gettype' => 8, 'getvalue' => 74, 'getvalue1' => 0, 'getrate' => 0.2],
        13 => ['id' => 13, 'group' => 1, 'gettype' => 8, 'getvalue' => 75, 'getvalue1' => 0, 'getrate' => 0.2],
        14 => ['id' => 14, 'group' => 2, 'gettype' => 4, 'getvalue' => 50, 'getvalue1' => 0, 'getrate' => 25.0],
        15 => ['id' => 15, 'group' => 2, 'gettype' => 5, 'getvalue' => 3000, 'getvalue1' => 0, 'getrate' => 25.0],
        16 => ['id' => 16, 'group' => 2, 'gettype' => 19, 'getvalue' => 45, 'getvalue1' => 3, 'getrate' => 8.0],
        17 => ['id' => 17, 'group' => 2, 'gettype' => 18, 'getvalue' => 7, 'getvalue1' => 1, 'getrate' => 3.0],
        18 => ['id' => 18, 'group' => 2, 'gettype' => 7, 'getvalue' => 2500, 'getvalue1' => 0, 'getrate' => 25.0],
        19 => ['id' => 19, 'group' => 2, 'gettype' => 18, 'getvalue' => 13, 'getvalue1' => 1, 'getrate' => 3.0],
        20 => ['id' => 20, 'group' => 2, 'gettype' => 16, 'getvalue' => 600, 'getvalue1' => 0, 'getrate' => 20.0],
        21 => ['id' => 21, 'group' => 2, 'gettype' => 16, 'getvalue' => 1000, 'getvalue1' => 0, 'getrate' => 10.0],
        22 => ['id' => 22, 'group' => 2, 'gettype' => 17, 'getvalue' => 150, 'getvalue1' => 0, 'getrate' => 25.0],
        23 => ['id' => 23, 'group' => 2, 'gettype' => 17, 'getvalue' => 300, 'getvalue1' => 0, 'getrate' => 10.0],
        24 => ['id' => 24, 'group' => 2, 'gettype' => 18, 'getvalue' => 1, 'getvalue1' => 1, 'getrate' => 3.0],
        25 => ['id' => 25, 'group' => 2, 'gettype' => 8, 'getvalue' => 72, 'getvalue1' => 0, 'getrate' => 0.1],
        26 => ['id' => 26, 'group' => 2, 'gettype' => 8, 'getvalue' => 73, 'getvalue1' => 0, 'getrate' => 0.1],
    ];
}
