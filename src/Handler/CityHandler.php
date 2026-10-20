<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Context;
use Hanjiang\Database\DB;

final class CityHandler
{
    /**
     * 主城主信息: c=city&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $field = DB::table('member_field')->where('uid', $uid)->first() ?? [];

        return [
            'status' => 1,
            'times' => (int)($field['levytimes'] ?? 50),
            'gold' => 2, // 强征元宝消耗
            'cd' => [
                'status' => 0,
                'cd' => 0,
            ],
            'message' => 'ok',
        ];
    }
}
