<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Context;
use Hanjiang\Database\DB;
use Hanjiang\Database\Redis;

final class LoginHandler
{
    /**
     * 单服试玩登录: c=login&m=guest
     */
    public static function guest(Context $ctx): array
    {
        $uid = (int)($ctx->input('uid', 0));
        if ($uid <= 0) {
            $user = DB::table('user')->where('is_guest', 1)->orderBy('uid', 'DESC')->first();
            $uid = $user !== null ? (int)$user['uid'] : 10001;
        }

        $token = 'tk_guest_' . $uid . '_' . bin2hex(random_bytes(8));
        // 将 Token 写入 Redis 缓存，有效期 7 天
        Redis::set('token:' . $token, (string)$uid, 86400 * 7);
        Redis::set('user_token:' . $uid, $token, 86400 * 7);

        $member = DB::table('member')->where('uid', $uid)->first();

        return [
            'status' => 1,
            'token' => $token,
            'vip' => (int)($member['vip'] ?? 1),
            'uid' => $uid,
            'temptoken' => $token,
            'message' => '登录成功',
            'msg' => '登录成功',
        ];
    }

    /**
     * 单服账号密码登录: c=login&m=user
     */
    public static function user(Context $ctx): array
    {
        $username = trim((string)$ctx->input('u', ''));
        $password = trim((string)$ctx->input('p', ''));

        $user = DB::table('user')->where('username', $username)->first();
        if ($user === null || $user['password'] !== sha1($password)) {
            return [
                'status' => -1,
                'message' => '账号或密码错误',
            ];
        }

        $uid = (int)$user['uid'];
        $token = 'tk_user_' . $uid . '_' . bin2hex(random_bytes(8));

        // 写入 Redis 缓存
        Redis::set('token:' . $token, (string)$uid, 86400 * 7);
        Redis::set('user_token:' . $uid, $token, 86400 * 7);

        $member = DB::table('member')->where('uid', $uid)->first();

        return [
            'status' => 1,
            'token' => $token,
            'vip' => (int)($member['vip'] ?? 1),
            'uid' => $uid,
            'temptoken' => $token,
            'message' => '登录成功',
            'msg' => '登录成功',
        ];
    }
}
