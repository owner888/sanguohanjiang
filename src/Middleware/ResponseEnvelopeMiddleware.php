<?php

declare(strict_types=1);

namespace Hanjiang\Middleware;

use Hanjiang\Context;
use Workerman\Protocols\Http\Response as WorkermanResponse;

final class ResponseEnvelopeMiddleware implements MiddlewareInterface
{
    public function handle(Context $ctx, callable $next): mixed
    {
        $result = $next($ctx);

        if ($result instanceof WorkermanResponse) {
            return $result;
        }

        if (is_array($result)) {
            // 自动补齐原版客户端基础协议字段
            if (!isset($result['status'])) {
                $result['status'] = 1;
            }
            if (!isset($result['message']) && !isset($result['msg'])) {
                $result['message'] = 'ok';
                $result['msg'] = 'ok';
            } elseif (isset($result['message']) && !isset($result['msg'])) {
                $result['msg'] = $result['message'];
            } elseif (isset($result['msg']) && !isset($result['message'])) {
                $result['message'] = $result['msg'];
            }

            return new WorkermanResponse(
                200,
                [
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
                json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }

        return new WorkermanResponse(200, ['Content-Type' => 'text/plain; charset=utf-8'], (string)$result);
    }
}
