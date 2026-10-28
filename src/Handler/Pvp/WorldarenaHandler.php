<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 跨服远征演武、群英巅峰赛与天梯押注处理器 (DDD PvP Domain)
 */
final class WorldarenaHandler
{
    /**
     * 跨服远征主界面: c=worldarena&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        // 跨服昨日巅峰前三
        $topThree = [
            [
                'rank' => 1,
                'uid' => 8888,
                'serverid' => 1,
                'nickname' => '常山赵子龙',
                'tree' => 0,
            ],
            [
                'rank' => 2,
                'uid' => 8889,
                'serverid' => 1,
                'nickname' => '关云长',
                'tree' => 0,
            ],
            [
                'rank' => 3,
                'uid' => 8890,
                'serverid' => 1,
                'nickname' => '诸葛孔明',
                'tree' => 0,
            ],
        ];

        // 押注状态
        $betInfo = [
            'bet_status' => 0,
            'times' => 0,
            'support' => 0,
            'silver' => 0,
            'cd' => 0,
            'nickname' => '',
            'rank' => [
                'before' => 0,
                'after' => 0,
            ],
        ];

        // 排名功勋奖励
        $rewardInfo = [
            'rank' => 1,
            'exploit' => 500,
            'cd' => 0,
        ];

        return [
            'status' => 1,
            'top' => $topThree,
            'bet' => $betInfo,
            'reward' => $rewardInfo,
            'message' => 'ok',
        ];
    }

    /**
     * 跨服争霸备战信息: c=worldarena&m=enter
     */
    public static function enter(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => '进入跨服争霸备战区',
        ];
    }
}
