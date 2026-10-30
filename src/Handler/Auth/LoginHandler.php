<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Auth;

use OmniPHP\Http\Context;
use OmniPHP\Database\DB;
use OmniPHP\Cache\Redis;

final class LoginHandler
{
    /**
     * 单服试玩登录: c=login&m=guest
     */
    public static function guest(Context $ctx): array
    {
        $uid = (int)($ctx->input('uid', 0));
        $guestKey = trim((string)$ctx->input('guestkey', ''));
        if ($uid <= 0 || $guestKey === '') {
            // 参数缺失
            return ['status' => -4];
        }

        // 旧版靠设备 mac 校验游客身份，H5 端 mac 恒为 00:00:00:00:00:00 形同虚设；
        // 开发者要求改为注册时下发的游客密钥
        $user = DB::table('user')->where('uid', $uid)->where('is_guest', 1)->whereNull('deleted_at')->first();
        if ($user === null || $user['guest_key'] === '' || !hash_equals((string)$user['guest_key'], hash('sha256', $guestKey))) {
            // 登录验证失败
            return ['status' => -1, 'message' => '登录验证失败'];
        }

        $token = 'tk_guest_' . $uid . '_' . bin2hex(random_bytes(8));
        // 将 Token 写入 Redis 缓存 (双向兼容纯字符串与结构化 JSON)，有效期 7 天
        Redis::set('token:' . $token, (string)$uid, 86400 * 7);
        Redis::setJson('token_session:' . $token, ['uid' => $uid, 'time' => time()], 86400 * 7);
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

        // 写入 Redis 缓存 (双向兼容纯字符串与结构化 JSON)
        Redis::set('token:' . $token, (string)$uid, 86400 * 7);
        Redis::setJson('token_session:' . $token, ['uid' => $uid, 'time' => time()], 86400 * 7);
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
