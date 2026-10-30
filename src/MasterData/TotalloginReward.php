<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 累计登录奖励表（旧版 zc_sanguo_totallogin_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class TotalloginReward
{
    public const string TABLE = 'zc_sanguo_totallogin_reward';

    /**
     * 按主键 id 索引，共 1 行。字段：
     *   - id (int) 累计登录天数
     *   - type (int) 奖励类型
     *   - value (int) 奖励值
     *   - num (int) 奖励数量
     *   - total_login_bg (int) 背景图显示
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => 12, 'value' => 1, 'num' => 1, 'total_login_bg' => 1],
    ];
}
