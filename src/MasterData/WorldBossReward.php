<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 世界boss奖励数据表（旧版 zc_sanguo_world_boss_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class WorldBossReward
{
    public const string TABLE = 'zc_sanguo_world_boss_reward';

    /**
     * 按主键 id 索引，共 11 行。字段：
     *   - id (int) 名次id
     *   - rank (int) 排名区间
     *   - silver (int) 奖励银币系数
     *   - gettype (tinyint) 奖励宝石类型
     *   - getvalue (int) 奖励宝石参数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'rank' => 1, 'silver' => 10000, 'gettype' => 3, 'getvalue' => 20],
        2 => ['id' => 2, 'rank' => 2, 'silver' => 8000, 'gettype' => 3, 'getvalue' => 15],
        3 => ['id' => 3, 'rank' => 3, 'silver' => 6000, 'gettype' => 3, 'getvalue' => 10],
        4 => ['id' => 4, 'rank' => 4, 'silver' => 4000, 'gettype' => 3, 'getvalue' => 8],
        5 => ['id' => 5, 'rank' => 6, 'silver' => 3500, 'gettype' => 3, 'getvalue' => 5],
        6 => ['id' => 6, 'rank' => 11, 'silver' => 3000, 'gettype' => 2, 'getvalue' => 20],
        7 => ['id' => 7, 'rank' => 16, 'silver' => 2500, 'gettype' => 2, 'getvalue' => 15],
        8 => ['id' => 8, 'rank' => 21, 'silver' => 2000, 'gettype' => 2, 'getvalue' => 10],
        9 => ['id' => 9, 'rank' => 31, 'silver' => 1500, 'gettype' => 2, 'getvalue' => 5],
        10 => ['id' => 10, 'rank' => 41, 'silver' => 1000, 'gettype' => 2, 'getvalue' => 5],
        11 => ['id' => 11, 'rank' => 51, 'silver' => 500, 'gettype' => 2, 'getvalue' => 5],
    ];
}
