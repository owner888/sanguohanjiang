<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 将魂每日奖励领取数据表（旧版 zc_sanguo_soul_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class SoulReward
{
    public const string TABLE = 'zc_sanguo_soul_reward';

    /**
     * 按主键 id 索引，共 2 行。字段：
     *   - id (int)
     *   - gold (int) 消耗元宝
     *   - ratio (int) 倍率
     */
    public const array ROWS = [
        1 => ['id' => 1, 'gold' => 30, 'ratio' => 200],
        2 => ['id' => 2, 'gold' => 100, 'ratio' => 500],
    ];
}
