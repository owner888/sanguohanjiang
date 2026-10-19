<?php

declare(strict_types=1);

namespace Hanjiang\Middleware;

use Hanjiang\Context;

final class GameProtocolMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string, class-string> $handlers 映射字典: ['ios' => IosHandler::class, ...]
     */
    public function __construct(
        private readonly array $handlers
    ) {
    }

    public function handle(Context $ctx, callable $next): mixed
    {
        $c = strtolower(trim((string)$ctx->input('c', 'index')));
        $m = trim((string)$ctx->input('m', 'index'));

        if (!isset($this->handlers[$c])) {
            return [
                'status' => 0,
                'message' => "接口未实现: {$c}.{$m}",
                'msg' => "接口未实现: {$c}.{$m}",
            ];
        }

        $handlerClass = $this->handlers[$c];
        if (!method_exists($handlerClass, $m)) {
            return [
                'status' => 0,
                'message' => "接口方法未实现: {$c}.{$m}",
                'msg' => "接口方法未实现: {$c}.{$m}",
            ];
        }

        $ctx->set('protocol_c', $c);
        $ctx->set('protocol_m', $m);
        $ctx->set('handler_target', [$handlerClass, $m]);

        return $next($ctx);
    }
}
