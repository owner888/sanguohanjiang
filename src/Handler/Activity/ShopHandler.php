<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Http\Context;
use OmniPHP\Database\DB;
use OmniPHP\Cache\Redis;

final class ShopHandler
{
    /**
     * 商城列表: c=shop&m=index
     */
    public static function index(Context $ctx): array
    {
        return [
            'status' => 1,
            'list' => [
                1 => ['id' => 1, 'name' => '初级军令包', 'price' => 50, 'coin' => 0],
                2 => ['id' => 2, 'name' => '百炼强化石', 'price' => 100, 'coin' => 0],
            ],
            'message' => 'ok',
        ];
    }

    /**
     * 购买军令体力: c=shop&m=buy_act
     */
    public static function buy_act(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        if ($member !== null) {
            DB::table('member')->where('uid', $uid)->update([
                'act' => (int)$member['act'] + 50,
            ]);
            Redis::del('player:' . $uid);
        }

        return [
            'status' => 1,
            'act' => ($member ? (int)$member['act'] + 50 : 150),
            'message' => '购买军令成功，军令增加 50 点',
        ];
    }

    /**
     * 普通购买: c=shop&m=buy
     */
    public static function buy(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => '购买成功',
        ];
    }
}
