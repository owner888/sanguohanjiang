<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Auth;

use OmniPHP\Http\Context;
use OmniPHP\Database\DB;

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

        $user = DB::table('user')->where('username', $username)->first();
        if ($user === null) {
            return [
                'status' => -1,
                'message' => '用户不存在',
            ];
        }

        if ($user['password'] !== sha1($password)) {
            return [
                'status' => -2,
                'message' => '密码错误，请重新输入',
            ];
        }

        $uid = (int)$user['uid'];
        $member = DB::table('member')->where('uid', $uid)->first();

        $serverBasePort = 18100;
        $host = $ctx->host();
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
            'message' => '登录成功',
            'uid' => $uid,
            'member' => [
                'uid' => $uid,
                'username' => $username,
                'nickname' => $member['nickname'] ?? $username,
                'channel' => (int)$user['channel'],
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
