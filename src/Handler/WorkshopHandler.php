<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class WorkshopHandler
{
    /**
     * 获取玩家军械工坊状态
     *
     * @return array{level: int, start_time: int}
     */
    public static function getWorkshop(int $uid): array
    {
        $cached = Redis::getJson('user_workshop:' . $uid);
        if (is_array($cached)) {
            return $cached;
        }

        $init = [
            'level' => 1,
            'start_time' => time(),
        ];
        Redis::setJson('user_workshop:' . $uid, $init, 86400 * 30);
        return $init;
    }

    /**
     * 军械工坊主界面: c=workshop&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $ws = self::getWorkshop($uid);
        $level = max(1, (int)$ws['level']);
        $startTime = (int)$ws['start_time'];

        $elapsed = max(0, time() - $startTime);
        $rate = $level * 15; // 每秒 15 银两军械产出
        $maxCapacity = $level * 50000;
        $accumulated = min($maxCapacity, $elapsed * $rate);

        return [
            'status' => 1,
            'level' => $level,
            'rate_desc' => ($rate * 60) . '银两/分',
            'current_production' => $accumulated,
            'max_capacity' => $maxCapacity,
            'upgrade_cost' => $level * 8000,
            'message' => 'ok',
        ];
    }

    /**
     * 收取军械银两: c=workshop&m=get_reward
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $ws = self::getWorkshop($uid);
        $level = max(1, (int)$ws['level']);
        $startTime = (int)$ws['start_time'];

        $elapsed = max(1, time() - $startTime);
        $rate = $level * 15;
        $maxCapacity = $level * 50000;
        $earnedSilver = min($maxCapacity, $elapsed * $rate);

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $newSilver = (int)($member['silver'] ?? 0) + $earnedSilver;

        DB::table('member')->where('uid', $uid)->update(['silver' => $newSilver]);

        // 重置生产计时
        $ws['start_time'] = time();
        Redis::setJson('user_workshop:' . $uid, $ws, 86400 * 30);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'silver' => $earnedSilver,
            'all_silver' => $newSilver,
            'message' => "军械收缴完成！军械库精铁破甲箭与攻城机甲售得银两 {$earnedSilver}！",
        ];
    }

    /**
     * 升级军械工坊: c=workshop&m=levelup
     */
    public static function levelup(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $ws = self::getWorkshop($uid);
        $level = max(1, (int)$ws['level']);
        $costSilver = $level * 8000;

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
            return ['status' => -1, 'message' => "银两不足，扩建工坊流水线需要 {$costSilver} 银两"];
        }

        $newLevel = $level + 1;
        $ws['level'] = $newLevel;
        Redis::setJson('user_workshop:' . $uid, $ws, 86400 * 30);

        return [
            'status' => 1,
            'level' => $newLevel,
            'message' => "军械工坊流水线扩建成功！提升至 {$newLevel} 级，军工产出速率成倍飙升！",
        ];
    }
}
