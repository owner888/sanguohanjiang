<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 登录返礼奖励表（旧版 zc_sanguo_loginreward_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class LoginrewardReward
{
    public const string TABLE = 'zc_sanguo_loginreward_reward';

    /**
     * 按主键 id 索引，共 10 行。字段：
     *   - id (int) 自增ID
     *   - day (int) 登陆天数
     *   - type (int) 奖励类型
     *   - num (int) 奖励1数量
     *   - value (int) 奖励参数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'day' => 1, 'type' => 3, 'num' => 100, 'value' => 0],
        2 => ['id' => 2, 'day' => 15, 'type' => 3, 'num' => 200, 'value' => 0],
        3 => ['id' => 3, 'day' => 30, 'type' => 3, 'num' => 400, 'value' => 0],
        4 => ['id' => 4, 'day' => 60, 'type' => 4, 'num' => 100, 'value' => 0],
        5 => ['id' => 5, 'day' => 100, 'type' => 4, 'num' => 200, 'value' => 0],
        6 => ['id' => 6, 'day' => 150, 'type' => 4, 'num' => 400, 'value' => 0],
        7 => ['id' => 7, 'day' => 220, 'type' => 4, 'num' => 600, 'value' => 0],
        8 => ['id' => 8, 'day' => 350, 'type' => 4, 'num' => 1000, 'value' => 0],
        9 => ['id' => 9, 'day' => 470, 'type' => 4, 'num' => 1500, 'value' => 0],
        10 => ['id' => 10, 'day' => 550, 'type' => 4, 'num' => 2000, 'value' => 0],
    ];
}
