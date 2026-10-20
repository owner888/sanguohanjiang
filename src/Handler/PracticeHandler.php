<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Context;
use Hanjiang\Database\DB;

final class PracticeHandler
{
    /**
     * 校场训练主界面: c=practice&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $practices = DB::table('practice')->where('uid', $uid)->get();

        return [
            'status' => 1,
            'maxnum' => 6,
            'opennum' => 2,
            'list' => !empty($practices) ? $practices : [
                1 => ['squence' => 1, 'generalid' => 1, 'level' => 1, 'type' => 1],
                2 => ['squence' => 2, 'generalid' => 0, 'level' => 1, 'type' => 1],
            ],
            'message' => 'ok',
        ];
    }

    /**
     * 开始训练: c=practice&m=practice_start
     */
    public static function practice_start(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => '训练开始',
        ];
    }

    /**
     * 停止训练: c=practice&m=practice_stop
     */
    public static function practice_stop(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => '训练已停止',
        ];
    }

    /**
     * 突飞猛进: c=practice&m=go_leap
     */
    public static function go_leap(Context $ctx): array
    {
        return [
            'status' => 1,
            'add_exp' => 500,
            'message' => '突飞猛进成功，获得 500 点武将经验',
        ];
    }
}
