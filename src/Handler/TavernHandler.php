<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Context;
use Hanjiang\Database\DB;

final class TavernHandler
{
    /**
     * 酒馆主界面: c=tavern&m=index
     */
    public static function index(Context $ctx): array
    {
        return [
            'status' => 1,
            'list' => [
                1 => ['id' => 10, 'name' => '张飞', 'quality' => 3, 'cost_silver' => 5000],
                2 => ['id' => 11, 'name' => '关羽', 'quality' => 4, 'cost_silver' => 10000],
                3 => ['id' => 12, 'name' => '诸葛亮', 'quality' => 5, 'cost_silver' => 20000],
            ],
            'message' => 'ok',
        ];
    }

    /**
     * 可招募武将列表: c=tavern&m=get_list
     */
    public static function get_list(Context $ctx): array
    {
        return self::index($ctx);
    }

    /**
     * 招募将领: c=tavern&m=buy
     */
    public static function buy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('id', 10));

        DB::table('general')->insert([
            'uid' => $uid,
            'generalid' => $gid,
            'name' => '招募武将' . $gid,
            'level' => 1,
            'exp' => 0,
            'pos' => 0,
            'matrix_id' => 1,
            'status' => 1,
            'created_at' => time(),
        ]);

        return [
            'status' => 1,
            'message' => '招募成功',
        ];
    }

    /**
     * 会客商人买卖: c=tavern&m=trade
     */
    public static function trade(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => '交易成功',
        ];
    }
}
