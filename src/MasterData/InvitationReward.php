<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 邀请奖励表（旧版 zc_sanguo_invitation_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class InvitationReward
{
    public const string TABLE = 'zc_sanguo_invitation_reward';

    /**
     * 按主键 id 索引，共 4 行。字段：
     *   - id (tinyint) id
     *   - count (int) 需要邀请的人数
     *   - type (tinyint) 奖励类型(1黄宝石，2紫宝石，3元宝)
     *   - number (int) 奖励数量
     */
    public const array ROWS = [
        1 => ['id' => 1, 'count' => 1, 'type' => 3, 'number' => 100],
        2 => ['id' => 2, 'count' => 5, 'type' => 3, 'number' => 500],
        3 => ['id' => 3, 'count' => 10, 'type' => 1, 'number' => 200],
        4 => ['id' => 4, 'count' => 20, 'type' => 2, 'number' => 100],
    ];
}
