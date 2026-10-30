<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 四方来朝奖励表（旧版 zc_sanguo_flop_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class FlopReward
{
    public const string TABLE = 'zc_sanguo_flop_reward';

    /**
     * 按主键 id 索引，共 6 行。字段：
     *   - id (tinyint)
     *   - name (varchar) 名称
     *   - num (int) 数量
     *   - rate (tinyint) 出现概率
     *   - multiply1 (tinyint) 正常权重
     *   - multiply2 (tinyint) 2倍权重
     *   - multiply4 (tinyint) 4倍权重
     *   - multiply8 (tinyint) 8倍权重
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '元宝', 'num' => 10, 'rate' => 10, 'multiply1' => 85, 'multiply2' => 5, 'multiply4' => 1, 'multiply8' => 1],
        2 => ['id' => 2, 'name' => '银币', 'num' => 500, 'rate' => 50, 'multiply1' => 85, 'multiply2' => 5, 'multiply4' => 1, 'multiply8' => 1],
        3 => ['id' => 3, 'name' => '军令', 'num' => 1, 'rate' => 50, 'multiply1' => 85, 'multiply2' => 5, 'multiply4' => 1, 'multiply8' => 1],
        4 => ['id' => 4, 'name' => '初级突飞卡', 'num' => 1, 'rate' => 10, 'multiply1' => 85, 'multiply2' => 5, 'multiply4' => 1, 'multiply8' => 1],
        5 => ['id' => 5, 'name' => '中级突飞卡', 'num' => 1, 'rate' => 5, 'multiply1' => 85, 'multiply2' => 5, 'multiply4' => 1, 'multiply8' => 1],
        6 => ['id' => 6, 'name' => '高级突飞卡', 'num' => 1, 'rate' => 1, 'multiply1' => 85, 'multiply2' => 5, 'multiply4' => 1, 'multiply8' => 1],
    ];
}
