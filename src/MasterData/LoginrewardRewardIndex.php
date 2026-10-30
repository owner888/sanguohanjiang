<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 登录返礼奖励系数表（旧版 zc_sanguo_loginreward_reward_index）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class LoginrewardRewardIndex
{
    public const string TABLE = 'zc_sanguo_loginreward_reward_index';

    /**
     * 按主键 id 索引，共 1 行。字段：
     *   - id (int) 自增ID
     *   - type1 (int) 奖励1类型
     *   - num1 (int) 奖励1数量
     *   - value1 (int) 奖励1参数
     *   - type2 (int) 奖励2类型
     *   - num2 (int) 奖励2数量
     *   - value2 (int) 奖励2参数
     *   - type3 (int) 奖励3类型
     *   - num3 (int) 奖励3数量
     *   - value3 (int) 奖励3参数
     *   - type4 (int) 奖励4类型
     *   - num4 (int) 奖励4数量
     *   - value4 (int) 奖励4参数
     *   - type5 (int) 奖励5类型
     *   - num5 (int) 奖励5数量
     *   - value5 (int) 奖励5参数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type1' => 2, 'num1' => 100000, 'value1' => 0, 'type2' => 10, 'num2' => 3, 'value2' => 0, 'type3' => 11, 'num3' => 10, 'value3' => 0, 'type4' => 3, 'num4' => 5, 'value4' => 0, 'type5' => 4, 'num5' => 2, 'value5' => 0],
    ];
}
