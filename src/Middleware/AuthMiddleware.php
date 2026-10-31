<?php

declare(strict_types=1);

namespace Hanjiang\Middleware;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Http\Context;
use OmniPHP\Http\Middleware;

/**
 * 登录态校验中间件（移植自旧版 ZC_Controller 的 token 验证）
 *
 * 除登录前即需调用的接口外，token 无效一律返回纯文本 403，
 * 客户端 base.js 的 http() 据此清空 token 并回到登录页。
 */
final class AuthMiddleware implements Middleware
{
    /** 无需登录的控制器：旧版 s1 的 login，以及原 UC 服的注册 / 账号 / 版本握手接口 */
    private const array PUBLIC_CONTROLLERS = ['index', 'ios', 'login', 'register', 'user'];

    public function handle(Context $ctx, callable $next): mixed
    {
        $c = strtolower(trim((string)$ctx->input('c', 'index')));
        if (in_array($c, self::PUBLIC_CONTROLLERS, true) || MemberHandler::resolveUid($ctx) > 0) {
            return $next($ctx);
        }

        return '403';
    }
}
