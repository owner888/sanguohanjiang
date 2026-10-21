<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Http\Context;
use OmniPHP\Database\DB;
use OmniPHP\Cache\Redis;

final class CityHandler
{
    /**
     * 主城主信息: c=city&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $field = DB::table('member_field')->where('uid', $uid)->first() ?? [];
        $member = DB::table('member')->where('uid', $uid)->first() ?? [];

        $levyTimes = (int)($field['levytimes'] ?? 50);

        return [
            'status' => 1,
            'times' => $levyTimes,
            'levytimes' => $levyTimes,
            'gold' => 2, // 强征基础金币消耗
            'silver' => (int)($member['silver'] ?? 0),
            'user_gold' => (int)($member['gold'] ?? 0),
            'cd' => [
                'status' => 0,
                'cd' => 0,
            ],
            'buildings' => [
                ['id' => 1, 'name' => '官府', 'level' => (int)($member['level'] ?? 1)],
                ['id' => 2, 'name' => '民居', 'level' => (int)($member['level'] ?? 1)],
                ['id' => 3, 'name' => '铁匠铺', 'level' => (int)($member['level'] ?? 1)],
                ['id' => 4, 'name' => '校场', 'level' => (int)($member['level'] ?? 1)],
            ],
            'message' => 'ok',
        ];
    }

    /**
     * 普通征收: c=city&m=impose
     */
    public static function impose(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $field = DB::table('member_field')->where('uid', $uid)->first();
        $member = DB::table('member')->where('uid', $uid)->first();

        $remainTimes = (int)($field['levytimes'] ?? 50);
        if ($remainTimes <= 0) {
            return [
                'status' => -1,
                'message' => '今日征收次数已用尽',
            ];
        }

        $level = max(1, (int)($member['level'] ?? 1));
        // 基础征收银两 = 等级 * 500
        $baseSilver = $level * 500;

        // 随机暴击 (10% 概率触发 2 倍大丰收暴击)
        $isCrit = (mt_rand(1, 100) <= 15);
        $multiplier = $isCrit ? 2 : 1;
        $earnedSilver = $baseSilver * $multiplier;

        $newRemainTimes = $remainTimes - 1;
        $newTotalSilver = (int)($member['silver'] ?? 0) + $earnedSilver;

        DB::table('member_field')->where('uid', $uid)->update([
            'levytimes' => $newRemainTimes,
            'levydateline' => time(),
        ]);

        DB::table('member')->where('uid', $uid)->update([
            'silver' => $newTotalSilver,
        ]);

        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'silver' => $earnedSilver,
            'all_silver' => $newTotalSilver,
            'levytimes' => $newRemainTimes,
            'crit' => $isCrit ? 1 : 0,
            'multiplier' => $multiplier,
            'cd' => 0,
            'message' => $isCrit ? "天降祥瑞！触发 {$multiplier} 倍丰收，获得 {$earnedSilver} 银两！" : "征收成功，获得 {$earnedSilver} 银两",
        ];
    }

    /**
     * 强征 (消耗元宝/金币): c=city&m=impress
     */
    public static function impress(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $costGold = 2;

        $curGold = (int)($member['gold'] ?? 0);
        if ($curGold < $costGold) {
            return [
                'status' => -1,
                'message' => '元宝不足，强征需要 ' . $costGold . ' 元宝',
            ];
        }

        $level = max(1, (int)($member['level'] ?? 1));
        $earnedSilver = $level * 1000; // 强征获得双倍银两

        $newGold = $curGold - $costGold;
        $newTotalSilver = (int)($member['silver'] ?? 0) + $earnedSilver;

        DB::table('member')->where('uid', $uid)->update([
            'gold' => $newGold,
            'silver' => $newTotalSilver,
        ]);

        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'silver' => $earnedSilver,
            'all_silver' => $newTotalSilver,
            'all_gold' => $newGold,
            'gold_cost' => $costGold,
            'message' => "强征成功，消耗 {$costGold} 元宝，获得 {$earnedSilver} 银两",
        ];
    }

    /**
     * 清理征收冷却: c=city&m=clear_cd
     */
    public static function clear_cd(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => '征收冷却已重置',
        ];
    }
}
