<?php

declare(strict_types=1);

namespace Hanjiang\Middleware;

use OmniPHP\Http\Context;
use Workerman\Protocols\Http\Response as WorkermanResponse;

final class CorsMiddleware implements MiddlewareInterface
{
    public function handle(Context $ctx, callable $next): mixed
    {
        // OPTIONS 预检请求直接拦截返回 204
        if ($ctx->method() === 'OPTIONS') {
            return new WorkermanResponse(204, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => '*',
            ], '');
        }

        $response = $next($ctx);

        if ($response instanceof WorkermanResponse) {
            $response->withHeaders([
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => '*',
            ]);
        }

        return $response;
    }
}
