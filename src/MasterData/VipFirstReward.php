<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 首充奖励表（旧版 zc_sanguo_vip_first_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class VipFirstReward
{
    public const string TABLE = 'zc_sanguo_vip_first_reward';

    /**
     * 按主键 id 索引，共 3 行。字段：
     *   - id (int) 奖励
     *   - get_type (int) 奖励类型
     *   - get_value (int) 奖励参数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'get_type' => 1, 'get_value' => 13],
        2 => ['id' => 2, 'get_type' => 2, 'get_value' => 52],
        3 => ['id' => 3, 'get_type' => 3, 'get_value' => 100000],
    ];
}
