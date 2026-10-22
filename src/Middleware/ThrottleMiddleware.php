<?php

declare(strict_types=1);

namespace Hanjiang\Middleware;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Http\Context;

/**
 * 核心关键操作原子防连击与防并发重入中间件
 *
 * 针对抽卡、强化、商城购买、摇骰等核心资产操作，通过 Redis 原子互斥锁，
 * 彻底防止多 Worker 并行时外挂脚本或连点导致的重入竞争与恶意刷单。
 */
final class ThrottleMiddleware implements MiddlewareInterface
{
    /** @var list<string> 需要防重复连击的核心高频关键操作 (controller.method) */
    private const array PROTECTED_ACTIONS = [
        'tavern.action',
        'strengthen.action',
        'shop.buy',
        'dice.action',
        'dailysign.action',
        'hitegg.action',
        'gem.action',
        'blackmarket.buy',
    ];

    public function handle(Context $ctx, callable $next): mixed
    {
        $c = strtolower((string)$ctx->query('c', $ctx->post('c', '')));
        $m = strtolower((string)$ctx->query('m', $ctx->post('m', '')));
        $actionKey = "{$c}.{$m}";

        if (!in_array($actionKey, self::PROTECTED_ACTIONS, true)) {
            return $next($ctx);
        }

        $uid = MemberHandler::resolveUid($ctx);

        if ($uid <= 0) {
            return $next($ctx);
        }

        // 尝试获取 1 秒原子防重放互斥锁 (EX = 1 秒自动过期兜底)
        $lockKey = "hj:lock:req:{$uid}:{$actionKey}";
        if (Redis::get($lockKey) !== null) {
            return [
                'status' => -1,
                'message' => '操作过于频繁，请稍候再试',
                'msg' => '操作过于频繁，请稍候再试',
            ];
        }

        Redis::set($lockKey, '1', 1);

        try {
            return $next($ctx);
        } finally {
            // 请求完成即时释放锁，保证正常连贯操作不被多余阻塞
            Redis::del($lockKey);
        }
    }
}
