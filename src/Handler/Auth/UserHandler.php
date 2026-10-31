<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Auth;

use OmniPHP\Http\Context;
use OmniPHP\Cache\Redis;
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
                'name' => self::SERVER_NAME,
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
     * 根据临时 Token 获取用户数据: c=user&m=get_info_by_temptoken&temptoken=（原 uc 服 controllers/user.php:get_info_by_temptoken）
     *
     * H5 网页版从官网登录后带 temptoken 进入游戏，客户端（base.js）用返回的 token / uid / username / servername 直接进游戏；
     * username 为空串表示试玩用户。旧版 uc 的 user_model 不在旧版代码包里（temptoken 由官网经 create_temptoken 生成，
     * 并按 IP / UA 校验），新版单服的登录接口把会话 token 同时作为 temptoken 返回（LoginHandler），这里按会话 token 查玩家：
     * temptoken 为空或无效时回 0（客户端回到登录界面）。servername 取服务器列表里本服的名字。
     */
    public static function get_info_by_temptoken(Context $ctx): array
    {
        $return = ['status' => 0];
        $temptoken = trim((string)$ctx->input('temptoken', ''));
        if ($temptoken === '') {
            return $return;
        }
        $uid = (int)(Redis::get('token:' . $temptoken) ?? 0);
        $user = $uid > 0 ? DB::table('user')->select('uid', 'username', 'is_guest')->where('uid', $uid)->whereNull('deleted_at')->first() : null;
        if ($user === null) {
            return $return;
        }
        return [
            'status' => 1,
            'uid' => $uid,
            // 试玩用户的用户名为空串（旧版 uc 试玩账号没有用户名）
            'username' => (int)$user['is_guest'] === 1 ? '' : (string)$user['username'],
            'token' => $temptoken,
            'servername' => self::SERVER_NAME,
        ];
    }

    /** 服务器列表里本服的名字（同 login 返回的 serverlist） */
    private const string SERVER_NAME = '1区-桃园结义';
}
