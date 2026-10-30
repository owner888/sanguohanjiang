<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 四象圣域宝石每日领取数据表（旧版 zc_sanguo_sanctum_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class SanctumReward
{
    public const string TABLE = 'zc_sanguo_sanctum_reward';

    /**
     * 按主键 id 索引，共 2 行。字段：
     *   - id (int) 四象圣域每日奖励倍数id
     *   - sub (int) 每日奖励倍数消耗
     *   - ratio (int) 每日奖励倍率
     */
    public const array ROWS = [
        1 => ['id' => 1, 'sub' => 200, 'ratio' => 200],
        2 => ['id' => 2, 'sub' => 800, 'ratio' => 500],
    ];
}
