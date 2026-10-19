<?php

declare(strict_types=1);

namespace Hanjiang\Controller;

use Hanjiang\Http\Response;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response as WorkermanResponse;

final class RegisterController
{
    /**
     * 游客一键试玩注册: c=register&m=guest
     */
    public function guest(Request $request): WorkermanResponse
    {
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
                'status' => 1, // 1新服，2火爆
                'message' => '',
                'selected' => 1,
            ],
        ];

        return Response::json([
            'status' => 1,
            'uid' => 10001,
            'message' => '试玩账号创建成功',
            'msg' => '试玩账号创建成功',
            'serverlist' => $serverList,
        ]);
    }

    /**
     * 账号注册: c=register&m=reg
     */
    public function reg(Request $request): WorkermanResponse
    {
        $username = trim((string)($request->get('u') ?? $request->post('u') ?? ''));
        $password = trim((string)($request->get('p') ?? $request->post('p') ?? ''));

        if ($username === '' || $password === '') {
            return Response::error('用户名或密码不能为空', -4);
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

        return Response::json([
            'status' => 1,
            'uid' => 10001,
            'message' => '注册成功',
            'msg' => '注册成功',
            'serverlist' => $serverList,
        ]);
    }
}
