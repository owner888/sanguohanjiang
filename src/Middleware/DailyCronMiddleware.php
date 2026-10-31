<?php

declare(strict_types=1);

namespace Hanjiang\Middleware;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Model\Member;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\Http\Middleware;

/**
 * 计划任务触发中间件（移植自旧版 ZC_Controller::_init_cron：_cron_today 每日计划任务、_cron_time 七点发放征收次数）
 *
 * 已登录玩家（带 token）当天首次请求时执行 Member::dailyCron；每天 7 点以后的首次请求执行 Member::levyCron。
 * 旧版先执行每日计划任务、再执行征收计划任务，征收计划任务看的是请求开始时读到的 member_field.dateline，这里同样先取出再执行。
 * Redis 记录当日 / 当个「7 点日」已执行，避免每个请求都打一次库；真正的执行权由两者各自的条件更新抢占。
 */
final class DailyCronMiddleware implements Middleware
{
    /** 征收次数发放时刻（7 点） */
    private const int LEVY_CRON_SEC = 3600 * 7;

    public function handle(Context $ctx, callable $next): mixed
    {
        if (trim((string)$ctx->input('token', '')) !== '') {
            $uid = MemberHandler::resolveUid($ctx);
            $now = time();
            $today = date('ymd', $now);
            $levyDay = date('Ymd', $now - self::LEVY_CRON_SEC);
            $dailyKey = "daily_cron:{$uid}";
            $levyKey = "levy_cron:{$uid}";
            $runDaily = $uid > 0 && Redis::get($dailyKey) !== $today;
            $runLevy = $uid > 0 && Redis::get($levyKey) !== $levyDay;
            if ($runDaily || $runLevy) {
                $fieldDateline = (int)(DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->value('dateline') ?? 0);
                if ($runDaily) {
                    Member::dailyCron($uid, $now);
                    Redis::set($dailyKey, $today, 86400 * 2);
                }
                if ($runLevy) {
                    Member::levyCron($uid, $now, $fieldDateline);
                    Redis::set($levyKey, $levyDay, 86400 * 2);
                }
            }
        }

        return $next($ctx);
    }
}
