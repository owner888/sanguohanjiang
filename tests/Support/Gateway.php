<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Support;

use OmniPHP\Http\Context;
use OmniPHP\Http\Router;

/**
 * 测试用 HTTP 入口：把 Context::mock() 构造的请求交给线上同一个 OmniPHP Router（routes.php 注册的中间件管道）
 */
final class Gateway
{
    public static function handle(Context $ctx): mixed
    {
        return Router::dispatch($ctx->connection(), $ctx->request());
    }
}
