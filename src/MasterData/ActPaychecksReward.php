<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 周年庆-招募战友奖励表（旧版 zc_sanguo_act_paychecks_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActPaychecksReward
{
    public const string TABLE = 'zc_sanguo_act_paychecks_reward';

    /**
     * 按主键 id 索引，共 5 行。字段：
     *   - id (tinyint) id
     *   - desc (varchar) 条件描述
     *   - count (tinyint) 需要邀请的人数
     *   - type (tinyint) 奖励类型(1黄宝石，2紫宝石，3元宝)
     *   - number (int) 奖励数量
     */
    public const array ROWS = [
        1 => ['id' => 1, 'desc' => '邀请1个好友到达80级', 'count' => 1, 'type' => 3, 'number' => 200],
        2 => ['id' => 2, 'desc' => '邀请5个好友到达80级', 'count' => 5, 'type' => 3, 'number' => 500],
        3 => ['id' => 3, 'desc' => '邀请10个好友到达80级', 'count' => 10, 'type' => 1, 'number' => 400],
        4 => ['id' => 4, 'desc' => '邀请15个好友到达80级', 'count' => 15, 'type' => 1, 'number' => 600],
        5 => ['id' => 5, 'desc' => '邀请20个好友到达80级', 'count' => 20, 'type' => 2, 'number' => 1000],
    ];
}
