<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Auth;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 修改密码（原 uc 服 controllers/password.php:set_pwd）
 *
 * 客户端（settingspop.js）调用 set_pwd&oldpwd=&newpwd=：新密码至少 6 位（-1）、只能用字母数字与 ~!@#$%^&*（6 ~ 16 位，-2），
 * 原始密码不正确 -3，用户不存在 -4，成功 1（msg 为旧版提示文字）。
 * 旧版 uc 按参数 user / type（1 按 uid、2 按帐号）找用户，password_model 不在旧版代码包里；新版单服的帐号在本服 user 表，
 * 只允许改登录 token 对应的本人帐号（客户端传的 uid 不采信）。试玩帐号（没有正式密码）按原始密码不正确处理。
 * 不移植：找回密码 / 手机绑定等短信接口（find / send / setpw / send_bind / bind / findpwbyauthcode / bind_phone_by_pwd /
 * send_change_mobile_code / change_mobile，客户端不调用，依赖短信网关）。
 */
final class PasswordHandler
{
    /**
     * 修改密码: c=password&m=set_pwd
     */
    public static function set_pwd(Context $ctx): array
    {
        $return = ['status' => 0];
        $oldpwd = (string)$ctx->input('oldpwd', '');
        $newpwd = (string)$ctx->input('newpwd', '');
        if (strlen($newpwd) < 6) {
            // 修改密码失败，密码太短
            $return['msg'] = '格式不正确: 密码至少6位';
            $return['status'] = -1;
            return $return;
        }
        if (!preg_match('/^[\w~!@#$%^&*]{6,16}$/i', $newpwd)) {
            $return['msg'] = '格式不正确：密码只能使用字母、数字和下划线';
            $return['status'] = -2;
            return $return;
        }
        $uid = MemberHandler::resolveUid($ctx);
        $user = DB::table('user')->select('uid', 'password', 'is_guest')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($user === null) {
            $return['status'] = -4;
            $return['msg'] = '用户不存在';
            return $return;
        }
        $old = sha1($oldpwd);
        if ((int)$user['is_guest'] === 1 || !hash_equals((string)$user['password'], $old)) {
            $return['status'] = -3;
            $return['msg'] = '原始密码不正确';
            return $return;
        }
        $affected = DB::table('user')->where('uid', $uid)->where('password', $old)->whereNull('deleted_at')->update(['password' => sha1($newpwd)]);
        $return['status'] = $affected > 0 || $oldpwd === $newpwd ? 1 : 0;
        $return['msg'] = '密码修改成功，请保管好你的新密码！';
        return $return;
    }
}
