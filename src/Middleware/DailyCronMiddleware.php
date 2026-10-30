<?php

declare(strict_types=1);

namespace Hanjiang\Middleware;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Model\Member;
use OmniPHP\Cache\Redis;
use OmniPHP\Http\Context;

/**
 * 每日计划任务触发中间件（移植自旧版 ZC_Controller::_cron_today）
 *
 * 已登录玩家（带 token）当天首次请求时执行 Member::dailyCron。
 * Redis 记录当日已执行，避免每个请求都打一次 member_field。
 */
final class DailyCronMiddleware implements MiddlewareInterface
{
    public function handle(Context $ctx, callable $next): mixed
    {
        if (trim((string)$ctx->input('token', '')) !== '') {
            $uid = MemberHandler::resolveUid($ctx);
            $now = time();
            $today = date('ymd', $now);
            $key = "daily_cron:{$uid}";
            if ($uid > 0 && Redis::get($key) !== $today) {
                Member::dailyCron($uid, $now);
                Redis::set($key, $today, 86400 * 2);
            }
        }

        return $next($ctx);
    }
}
