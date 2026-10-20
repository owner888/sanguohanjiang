<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Context;
use Hanjiang\Database\DB;

final class ArenaHandler
{
    /**
     * 演武榜列表: c=arena&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $field = DB::table('member_field')->where('uid', $uid)->first();

        return [
            'status' => 1,
            'rank' => (int)($field['rank'] ?? 1),
            'left_times' => 15, // 每日剩余挑战次数
            'cd' => 0,
            'list' => [
                1 => ['rank' => 1, 'uid' => 10002, 'name' => '诸葛孔明', 'level' => 10, 'head' => 1],
                2 => ['rank' => 2, 'uid' => 10003, 'name' => '常山赵子龙', 'level' => 9, 'head' => 1],
                3 => ['rank' => 3, 'uid' => 10004, 'name' => '燕人张翼德', 'level' => 8, 'head' => 2],
            ],
            'message' => 'ok',
        ];
    }

    /**
     * 发起挑战: c=arena&m=action
     */
    public static function action(Context $ctx): array
    {
        return [
            'status' => 1,
            'win' => 1,
            'rank' => 1,
            'reputation' => 20,
            'silver' => 300,
            'message' => '挑战大获全胜！',
        ];
    }

    /**
     * 领取演武排名奖励: c=arena&m=get_reward
     */
    public static function get_reward(Context $ctx): array
    {
        return [
            'status' => 1,
            'reputation' => 50,
            'silver' => 2000,
            'message' => '领取成功',
        ];
    }

    /**
     * 清理冷却: c=arena&m=clear_cd
     */
    public static function clear_cd(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => '冷却已清除',
        ];
    }
}
