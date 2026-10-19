<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Context;

final class RegisterHandler
{
    /**
     * 游客快速试玩账号创建: c=register&m=guest
     */
    public static function guest(Context $ctx): array
    {
        $serverBasePort = 18100;
        $host = $ctx->request->host();
        $hostname = explode(':', $host)[0];

        $serverList = [
            [
                'id' => 1,
                'name' => '1区-桃园结义',
                'addr' => "http://{$hostname}:{$serverBasePort}/index.php",
                'servergroup' => 1,
                'newserver' => 0,
                'status' => 1,
                'message' => '',
                'selected' => 1,
            ],
        ];

        return [
            'status' => 1,
            'uid' => 10001,
            'message' => '试玩账号创建成功',
            'serverlist' => $serverList,
        ];
    }

    /**
     * 正式用户注册: c=register&m=reg
     */
    public static function reg(Context $ctx): array
    {
        $username = trim((string)$ctx->input('u', ''));
        $password = trim((string)$ctx->input('p', ''));

        if ($username === '' || $password === '') {
            return [
                'status' => -4,
                'message' => '用户名或密码不能为空',
            ];
        }

        $serverBasePort = 18100;
        $host = $ctx->request->host();
        $hostname = explode(':', $host)[0];

        $serverList = [
            [
                'id' => 1,
                'name' => '1区-桃园结义',
                'addr' => "http://{$hostname}:{$serverBasePort}/index.php",
                'servergroup' => 1,
                'newserver' => 0,
                'status' => 1,
                'message' => '',
                'selected' => 1,
            ],
        ];

        return [
            'status' => 1,
            'uid' => 10001,
            'message' => '注册成功',
            'serverlist' => $serverList,
        ];
    }
}
