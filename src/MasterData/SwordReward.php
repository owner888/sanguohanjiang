<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 铸剑奖励表（旧版 zc_sanguo_sword_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class SwordReward
{
    public const string TABLE = 'zc_sanguo_sword_reward';

    /**
     * 按主键 id 索引，共 11 行。字段：
     *   - id (int) 铸剑等级
     *   - finish_num (int) 完成数量
     *   - type (int) 类型
     *   - num (int) 数量
     */
    public const array ROWS = [
        1 => ['id' => 1, 'finish_num' => 100, 'type' => 27, 'num' => 5],
        2 => ['id' => 2, 'finish_num' => 150, 'type' => 27, 'num' => 10],
        3 => ['id' => 3, 'finish_num' => 200, 'type' => 28, 'num' => 5],
        4 => ['id' => 4, 'finish_num' => 250, 'type' => 27, 'num' => 15],
        5 => ['id' => 5, 'finish_num' => 300, 'type' => 27, 'num' => 20],
        6 => ['id' => 6, 'finish_num' => 400, 'type' => 28, 'num' => 10],
        7 => ['id' => 7, 'finish_num' => 500, 'type' => 27, 'num' => 50],
        8 => ['id' => 8, 'finish_num' => 600, 'type' => 28, 'num' => 40],
        9 => ['id' => 9, 'finish_num' => 700, 'type' => 27, 'num' => 120],
        10 => ['id' => 10, 'finish_num' => 800, 'type' => 28, 'num' => 60],
        11 => ['id' => 11, 'finish_num' => 0, 'type' => 0, 'num' => 0],
    ];
}
