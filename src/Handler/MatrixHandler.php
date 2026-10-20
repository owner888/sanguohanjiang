<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Context;
use Hanjiang\Database\DB;

final class MatrixHandler
{
    /**
     * 阵法主界面: c=matrix&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $matrix = DB::table('matrix')->where('uid', $uid)->first();

        return [
            'status' => 1,
            'now_mid' => (int)($matrix['mid'] ?? 1),
            'list' => [
                1 => [
                    'id' => 1,
                    'name' => '鱼鳞阵',
                    'level' => (int)($matrix['level'] ?? 1),
                    'type' => 1, // 物攻
                    'addition' => 1.05,
                    'open' => 1,
                    'position' => [
                        1 => (int)($matrix['position1'] ?? 0),
                        2 => (int)($matrix['position2'] ?? 1),
                        3 => (int)($matrix['position3'] ?? 0),
                        4 => (int)($matrix['position4'] ?? 0),
                        5 => (int)($matrix['position5'] ?? 0),
                        6 => (int)($matrix['position6'] ?? 0),
                        7 => (int)($matrix['position7'] ?? 0),
                        8 => (int)($matrix['position8'] ?? 0),
                        9 => (int)($matrix['position9'] ?? 0),
                    ],
                ],
            ],
        ];
    }

    /**
     * 调整阵法站位: c=matrix&m=update_matrix
     */
    public static function update_matrix(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mid = (int)($ctx->input('mid', 1));
        $pos = (int)($ctx->input('position', 2));
        $gid = (int)($ctx->input('gid', 0));

        $col = 'position' . $pos;
        DB::table('matrix')->where('uid', $uid)->where('mid', $mid)->update([
            $col => $gid,
        ]);

        return [
            'status' => 1,
            'message' => '布阵成功',
        ];
    }

    /**
     * 获取阵法信息: c=matrix&m=get_info
     */
    public static function get_info(Context $ctx): array
    {
        return [
            'status' => 1,
            'info' => [
                'mid' => 1,
                'name' => '鱼鳞阵',
                'level' => 1,
                'effect' => '提升全军普通攻击力 5%',
            ],
        ];
    }

    /**
     * 启用指定阵法: c=matrix&m=use_matrix
     */
    public static function use_matrix(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mid = (int)($ctx->input('mid', 1));

        DB::table('matrix')->where('uid', $uid)->update([
            'mid' => $mid,
        ]);

        return [
            'status' => 1,
            'message' => '阵法切换成功',
        ];
    }

    /**
     * 升级阵法: c=matrix&m=levelup
     */
    public static function levelup(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => '阵法升级成功',
        ];
    }
}
