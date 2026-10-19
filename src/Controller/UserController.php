<?php

declare(strict_types=1);

namespace Hanjiang\Controller;

use Hanjiang\Http\Response;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response as WorkermanResponse;

final class UserController
{
    /**
     * 用户账号登录: c=user&m=login
     */
    public function login(Request $request): WorkermanResponse
    {
        $username = trim((string)($request->get('u') ?? $request->post('u') ?? ''));
        $password = trim((string)($request->get('p') ?? $request->post('p') ?? ''));

        if ($username === '' || $password === '') {
            return Response::error('用户名或密码不能为空', -3);
        }

        $serverBasePort = 18100;
        $host = $request->host();
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

        return Response::json([
            'status' => 1,
            'message' => '登录成功',
            'msg' => '登录成功',
            'uid' => $uid,
            'member' => [
                'uid' => $uid,
                'username' => $username,
                'nickname' => $username,
                'channel' => 106,
            ],
            'serverlist' => $serverList,
        ]);
    }

    /**
     * 通过临时 token 获取用户信息: c=user&m=get_info_by_temptoken
     */
    public function get_info_by_temptoken(Request $request): WorkermanResponse
    {
        return Response::success([
            'uid' => 10001,
            'username' => 'dev_player',
            'token' => 'mock_token_' . time(),
        ]);
    }
}
