<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Context;

final class MemberHandler
{
    /**
     * 玩家主信息接口: c=member&m=index
     */
    public static function index(Context $ctx): array
    {
        $now = time();

        return [
            'status' => 1,
            'uid' => 10001,
            'username' => 'dev_player',
            'nickname' => '天命主公',
            'sex' => 1,
            'roleid' => 1,
            'country' => 1, // 1魏 2蜀 3吴
            'Level' => 1,
            'silver' => 88888,   // 银两
            'gold' => 9999,      // 元宝
            'reputation' => 100, // 声望
            'act' => 100,        // 军令/体力
            'maxact' => 100,
            'vip' => 1,
            'exp' => 0,
            'maxexp' => 100,
            'bagnum' => 20,
            'attackcd' => [
                'status' => 0,
                'cd' => 0,
            ],
            'levy' => [
                'times' => 10,
                'cd' => 0,
                'status' => 0,
            ],
            'practice' => [
                'timers' => 0,
            ],
            'rank' => 1,
            'newresult' => 0,
            'gift' => 0,
            'race' => 0,
            'race_dateline' => 0,
            'new_role_reward' => 0,
            'opennewyearboss' => 0,
            'timestamp' => $now,
        ];
    }

    /**
     * 选角/创角: c=member&m=select_role
     */
    public static function select_role(Context $ctx): array
    {
        $roleId = (int)($ctx->input('roleid', 1));
        $nickname = trim((string)$ctx->input('nickname', '主公'));

        return [
            'status' => 1,
            'message' => '角色创建成功',
            'roleid' => $roleId,
            'nickname' => $nickname,
            'Level' => 1,
        ];
    }
}
