<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 国家骰子大奖表（旧版 zc_sanguo_dice_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class DiceReward
{
    public const string TABLE = 'zc_sanguo_dice_reward';

    /**
     * 按主键 id 索引，共 2 行。字段：
     *   - id (tinyint) 序列id
     *   - points (int) 点数
     *   - reward (int) 奖励元宝
     */
    public const array ROWS = [
        1 => ['id' => 1, 'points' => 600, 'reward' => 300],
        2 => ['id' => 2, 'points' => 400, 'reward' => 50],
    ];
}
