<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class StarHandler
{
    /**
     * 武将升星主界面: c=star&m=index
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

        $star = (int)(Redis::get("general_star:{$uid}:{$gid}") ?: 1);

        return [
            'status' => 1,
            'gid' => $gid,
            'name' => $general['name'] ?? '武将',
            'star' => $star,
            'max_star' => 7,
            'cost_silver' => $star * 10000,
            'cost_soul' => $star * 20,
            'message' => 'ok',
        ];
    }

    /**
     * 执行武将升星突破: c=star&m=level_up
     */
    public static function level_up(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('gid', 1));

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();
        if ($general === null) {
            return ['status' => -1, 'message' => '武将不存在'];
        }

        $star = (int)(Redis::get("general_star:{$uid}:{$gid}") ?: 1);
        if ($star >= 7) {
            return ['status' => 0, 'message' => '该武将已达七星北斗无双至高星级！'];
        }

        $costSilver = $star * 10000;
        $costSoul = $star * 20;

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ((int)($member['soul'] ?? 0) < $costSoul) {
            return ['status' => -2, 'message' => "将魂点数不足，升星需要 {$costSoul} 点将魂"];
        }

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
            return ['status' => -3, 'message' => "银两不足，升星需要 {$costSilver} 银两"];
        }

        // 扣除将魂
        DB::table('member')->where('uid', $uid)->update([
            'soul' => (int)$member['soul'] - $costSoul,
        ]);

        $newStar = $star + 1;
        Redis::set("general_star:{$uid}:{$gid}", (string)$newStar, 86400 * 30);
        Redis::del('player:' . $uid);

        // 待按旧版重写：旧版武将没有逐将升星，wuliup/zhiliup/tiliup 只属于培养（cultivate），这里不写三围

        return [
            'status' => 1,
            'gid' => $gid,
            'new_star' => $newStar,
            'message' => "星魂觉醒！【{$general['name']}】成功突破升星至 {$newStar} 星！",
        ];
    }
}
