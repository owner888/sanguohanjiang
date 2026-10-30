<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 充值奖励表（旧版 zc_sanguo_pay_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class PayReward
{
    public const string TABLE = 'zc_sanguo_pay_reward';

    /**
     * 按主键 id 索引，共 15 行。字段：
     *   - id (tinyint) 奖励id
     *   - amount (int) 充值额度
     *   - gettype (tinyint) 奖励类型（1银币，2元宝，3蓝宝石，4金宝石，5紫宝石，6玉石，7元魂）
     *   - getvalue (int) 奖励参数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'amount' => 500, 'gettype' => 4, 'getvalue' => 50],
        2 => ['id' => 2, 'amount' => 2500, 'gettype' => 4, 'getvalue' => 100],
        3 => ['id' => 3, 'amount' => 5000, 'gettype' => 4, 'getvalue' => 200],
        4 => ['id' => 4, 'amount' => 10000, 'gettype' => 5, 'getvalue' => 100],
        5 => ['id' => 5, 'amount' => 25000, 'gettype' => 5, 'getvalue' => 150],
        6 => ['id' => 6, 'amount' => 35000, 'gettype' => 5, 'getvalue' => 200],
        7 => ['id' => 7, 'amount' => 50000, 'gettype' => 5, 'getvalue' => 250],
        8 => ['id' => 8, 'amount' => 100000, 'gettype' => 5, 'getvalue' => 350],
        9 => ['id' => 9, 'amount' => 250000, 'gettype' => 5, 'getvalue' => 550],
        10 => ['id' => 10, 'amount' => 350000, 'gettype' => 5, 'getvalue' => 800],
        11 => ['id' => 11, 'amount' => 500000, 'gettype' => 5, 'getvalue' => 1100],
        12 => ['id' => 12, 'amount' => 1000000, 'gettype' => 9, 'getvalue' => 250000],
        13 => ['id' => 13, 'amount' => 2500000, 'gettype' => 9, 'getvalue' => 300000],
        14 => ['id' => 14, 'amount' => 3500000, 'gettype' => 9, 'getvalue' => 350000],
        15 => ['id' => 15, 'amount' => 5000000, 'gettype' => 5, 'getvalue' => 5500],
    ];
}
