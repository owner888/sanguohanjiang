<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Context;

final class LoginHandler
{
    /**
     * 单服试玩登录: c=login&m=guest
     */
    public static function guest(Context $ctx): array
    {
        $uid = (int)($ctx->input('uid', 10001));
        if ($uid <= 0) {
            $uid = 10001;
        }

        $token = 'tk_guest_' . $uid . '_' . bin2hex(random_bytes(8));

        return [
            'status' => 1,
            'token' => $token,
            'vip' => 1,
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

        $uid = 10001;
        $token = 'tk_user_' . $uid . '_' . bin2hex(random_bytes(8));

        return [
            'status' => 1,
            'token' => $token,
            'vip' => 1,
            'uid' => $uid,
            'temptoken' => $token,
            'message' => '登录成功',
            'msg' => '登录成功',
        ];
    }
}
