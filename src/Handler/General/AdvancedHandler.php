<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class AdvancedHandler
{
    public const array QUALITIES = [
        1 => ['quality' => 1, 'name' => '普通白将', 'color' => '#ffffff', 'cost_silver' => 10000, 'cost_gold' => 0],
        2 => ['quality' => 2, 'name' => '骁勇绿将', 'color' => '#00ff00', 'cost_silver' => 20000, 'cost_gold' => 0],
        3 => ['quality' => 3, 'name' => '绝世蓝将', 'color' => '#00bfff', 'cost_silver' => 40000, 'cost_gold' => 20],
        4 => ['quality' => 4, 'name' => '霸道紫将', 'color' => '#9932cc', 'cost_silver' => 80000, 'cost_gold' => 50],
        5 => ['quality' => 5, 'name' => '无双橙将', 'color' => '#ff8c00', 'cost_silver' => 150000, 'cost_gold' => 100],
        6 => ['quality' => 6, 'name' => '天命暗金神将', 'color' => '#ffd700', 'cost_silver' => 300000, 'cost_gold' => 200],
    ];

    /**
     * 武将进阶主界面: c=advanced&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('gid', 1));

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();
        if ($general === null) {
            $first = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->first();
            $gid = (int)($first['id'] ?? 1);
            $general = $first;
        }

        $curQuality = (int)($general['quality'] ?? 2);
        $nextTier = self::QUALITIES[$curQuality + 1] ?? null;

        return [
            'status' => 1,
            'gid' => $gid,
            'name' => $general['name'] ?? '武将',
            'quality' => $curQuality,
            'quality_name' => self::QUALITIES[$curQuality]['name'] ?? '绝世名将',
            'next_quality' => $nextTier ? $curQuality + 1 : -1,
            'next_name' => $nextTier['name'] ?? '至尊巅峰',
            'cost_silver' => $nextTier['cost_silver'] ?? 0,
            'cost_gold' => $nextTier['cost_gold'] ?? 0,
            'can_advance' => $nextTier ? 1 : 0,
            'message' => 'ok',
        ];
    }

    /**
     * 执行武将品质进阶飞升: c=advanced&m=start_advanced
     */
    public static function start_advanced(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('gid', 1));

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();
        if ($general === null) {
            return ['status' => -1, 'message' => '武将不存在'];
        }

        $curQuality = (int)($general['quality'] ?? 2);
        if ($curQuality >= 6) {
            return ['status' => 0, 'message' => '武将已达至高【天命暗金神将】品质，无法再进阶'];
        }

        $nextTier = self::QUALITIES[$curQuality + 1];
        $costSilver = (int)$nextTier['cost_silver'];
        $costGold = (int)$nextTier['cost_gold'];

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver, gold: $costGold)) {
            return [
                'status' => -2,
                'message' => ($costGold > 0) ? "元宝不足，飞升需要 {$costGold} 元宝" : "银两不足，飞升需要 {$costSilver} 银两",
            ];
        }

        $newQuality = $curQuality + 1;

        // 升级武将基础三围洗髓属性作为进阶奖励
        $newWuli = (int)($general['wuliup'] ?? 0) + 15;
        $newZhili = (int)($general['zhiliup'] ?? 0) + 15;
        $newTili = (int)($general['tiliup'] ?? 0) + 30;

        DB::table('general')->where('id', $gid)->update([
            'wuliup' => $newWuli,
            'zhiliup' => $newZhili,
            'tiliup' => $newTili,
        ]);

        Redis::del('player:' . $uid);

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $pName = $member['nickname'] ?? ('主公' . $uid);

        // 高阶进阶全服大喇叭
        if ($newQuality >= 4) {
            Broadcaster::broadcast('marquee', [
                'content' => "天地异象！主公【{$pName}】麾下名将【{$general['name']}】参透大道，羽化飞升至【{$nextTier['name']}】！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'gid' => $gid,
            'new_quality' => $newQuality,
            'quality_name' => $nextTier['name'],
            'message' => "飞升成功！【{$general['name']}】成功晋阶为【{$nextTier['name']}】，全军属性暴涨！",
        ];
    }
}
