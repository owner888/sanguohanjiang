<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 老兵召回活动奖励表（旧版 zc_sanguo_act_veteran_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActVeteranReward
{
    public const string TABLE = 'zc_sanguo_act_veteran_reward';

    /**
     * 按主键 id 索引，共 1 行。字段：
     *   - id (int) 自增ID
     *   - level (int) 等级限制
     *   - nologin_stime (int) 未登陆开始时间
     *   - nologin_etime (int) 未登陆结束时间
     *   - type1 (int) 奖励类型1
     *   - value1 (int) 奖励值1
     *   - type2 (int) 奖励类型2
     *   - value2 (int) 奖励值2
     *   - type3 (int) 奖励类型3
     *   - value3 (int) 奖励值3
     */
    public const array ROWS = [
        1 => ['id' => 1, 'level' => 80, 'nologin_stime' => 20140714, 'nologin_etime' => 20140913, 'type1' => 16, 'value1' => 144, 'type2' => 16, 'value2' => 152, 'type3' => 17, 'value3' => 1],
    ];
}
