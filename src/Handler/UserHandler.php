<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Context;

final class UserHandler
{
    /**
     * 账号密码登录: c=user&m=login
     */
    public static function login(Context $ctx): array
    {
        $username = trim((string)$ctx->input('u', ''));
        $password = trim((string)$ctx->input('p', ''));

        if ($username === '' || $password === '') {
            return [
                'status' => -3,
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

        $uid = 10001;

        return [
            'status' => 1,
            'message' => '登录成功',
            'uid' => $uid,
            'member' => [
                'uid' => $uid,
                'username' => $username,
                'nickname' => $username,
                'channel' => 106,
            ],
            'serverlist' => $serverList,
        ];
    }

    /**
     * 根据临时 Token 获取用户数据: c=user&m=get_info_by_temptoken
     */
    public static function get_info_by_temptoken(Context $ctx): array
    {
        return [
            'status' => 1,
            'uid' => 10001,
            'username' => 'dev_player',
            'token' => 'mock_token_' . time(),
        ];
    }
}
