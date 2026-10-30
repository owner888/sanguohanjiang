<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 春节送福倍数表（旧版 zc_sanguo_act_newyear_multiple）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActNewyearMultiple
{
    public const string TABLE = 'zc_sanguo_act_newyear_multiple';

    /**
     * 按主键 id 索引，共 4 行。字段：
     *   - id (int) 自增ID
     *   - fortune (int) 福气值
     *   - num (int) 奖励倍数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'fortune' => 0, 'num' => 1],
        2 => ['id' => 2, 'fortune' => 80, 'num' => 2],
        3 => ['id' => 3, 'fortune' => 410, 'num' => 3],
        4 => ['id' => 4, 'fortune' => 1110, 'num' => 5],
    ];
}
