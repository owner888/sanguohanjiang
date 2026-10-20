<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Context;
use Hanjiang\Database\DB;

final class PackHandler
{
    /**
     * 背包列表: c=pack&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $items = DB::table('equipment')->where('uid', $uid)->get();

        return [
            'status' => 1,
            'maxnum' => (int)($member['bagnum'] ?? 20),
            'list' => !empty($items) ? $items : [
                1 => [
                    'id' => 1,
                    'name' => '青铜剑',
                    'type' => 1, // 武器
                    'level' => 1,
                    'quality' => 1,
                    'saleprice' => 50,
                    'number' => 1,
                ],
                2 => [
                    'id' => 2,
                    'name' => '青铜甲',
                    'type' => 2, // 衣服
                    'level' => 1,
                    'quality' => 1,
                    'saleprice' => 50,
                    'number' => 1,
                ],
            ],
            'message' => 'ok',
        ];
    }

    /**
     * 使用物品: c=pack&m=use_item
     */
    public static function use_item(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => '使用成功',
        ];
    }

    /**
     * 出售物品: c=pack&m=sale
     */
    public static function sale(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => '出售成功',
        ];
    }
}
