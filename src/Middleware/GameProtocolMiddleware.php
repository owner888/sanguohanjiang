<?php

declare(strict_types=1);

namespace Hanjiang\Middleware;

use OmniPHP\Http\Context;
use OmniPHP\Logger;

/**
 * 游戏协议分发中间件 (带智能平滑降级与 Logger Warning 跟踪)
 */
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

        // 1. 控制器或动作未实现时的智能降级与告警追踪
        if (!isset($this->handlers[$c]) || !method_exists($this->handlers[$c], $m)) {
            $uid = (int)($ctx->input('uid', $ctx->input('token_uid', 0)));
            
            // 采用 OmniPHP 框架原生 Logger 记录告警日志，精准捕获玩家触发的待移植接口
            Logger::warning("[Protocol Fallback] 命中未覆盖接口: c={$c}&m={$m}", [
                'c' => $c,
                'm' => $m,
                'uid' => $uid,
                'params' => $ctx->all(),
            ]);

            // 返回向后兼容平滑信封，杜绝前端任何白屏或卡加载遮罩
            return [
                'status' => 1,
                'is_fallback' => 1,
                'c' => $c,
                'm' => $m,
                'message' => "【{$c}.{$m}】玩法正在紧锣密鼓筹备中，敬请期待！",
                'msg' => "【{$c}.{$m}】玩法正在紧锣密鼓筹备中，敬请期待！",
                'list' => [],
                'info' => [],
                'data' => [],
            ];
        }

        $handlerClass = $this->handlers[$c];

        $ctx->set('protocol_c', $c);
        $ctx->set('protocol_m', $m);
        $ctx->set('handler_target', [$handlerClass, $m]);

        return $next($ctx);
    }
}
