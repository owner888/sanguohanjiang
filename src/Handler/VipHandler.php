<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class VipHandler
{
    /**
     * VIP 规则与特权定义
     *
     * @var array<int, array<string, mixed>>
     */
    public const array VIP_TIERS = [
        1 => ['level' => 1, 'need_exp' => 0, 'salary_silver' => 3000, 'salary_gold' => 10, 'salary_act' => 10, 'gift_silver' => 10000, 'gift_gold' => 50],
        2 => ['level' => 2, 'need_exp' => 100, 'salary_silver' => 5000, 'salary_gold' => 20, 'salary_act' => 15, 'gift_silver' => 20000, 'gift_gold' => 100],
        3 => ['level' => 3, 'need_exp' => 300, 'salary_silver' => 8000, 'salary_gold' => 30, 'salary_act' => 20, 'gift_silver' => 30000, 'gift_gold' => 200],
        4 => ['level' => 4, 'need_exp' => 500, 'salary_silver' => 12000, 'salary_gold' => 50, 'salary_act' => 25, 'gift_silver' => 50000, 'gift_gold' => 300],
        5 => ['level' => 5, 'need_exp' => 1000, 'salary_silver' => 20000, 'salary_gold' => 100, 'salary_act' => 30, 'gift_silver' => 100000, 'gift_gold' => 500],
    ];

    /**
     * VIP 主界面: c=vip&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $vipLevel = max(1, (int)($member['vip'] ?? 1));

        $tier = self::VIP_TIERS[$vipLevel] ?? self::VIP_TIERS[1];
        $nextTier = self::VIP_TIERS[$vipLevel + 1] ?? null;

        $now = time();
        $salaryClaimed = false;
        $lastSalary = Redis::get('vip_salary:' . $uid);
        if ($lastSalary !== null && date('Ymd', (int)$lastSalary) === date('Ymd', $now)) {
            $salaryClaimed = true;
        }

        $claimedGifts = Redis::getJson('vip_gifts:' . $uid) ?: [];

        return [
            'status' => 1,
            'level' => $vipLevel,
            'exp' => $vipLevel * 100,
            'next_level' => $nextTier ? $vipLevel + 1 : -1,
            'top_exp' => $nextTier ? (int)$nextTier['need_exp'] : $vipLevel * 100,
            'salary_claimed' => $salaryClaimed ? 1 : 0,
            'claimed_gifts' => $claimedGifts,
            'privileges' => [
                'bag_slots' => 20 + $vipLevel * 5,
                'levy_crit_rate' => 15 + $vipLevel * 5,
            ],
            'message' => 'ok',
        ];
    }

    /**
     * 领取每日 VIP 俸禄: c=vip&m=get_vip_reward
     */
    public static function get_vip_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $vipLevel = max(1, (int)($member['vip'] ?? 1));

        $now = time();
        $lastSalary = Redis::get('vip_salary:' . $uid);
        if ($lastSalary !== null && date('Ymd', (int)$lastSalary) === date('Ymd', $now)) {
            return [
                'status' => 0,
                'message' => '今日 VIP 俸禄已领取，请明日再来',
            ];
        }

        $tier = self::VIP_TIERS[$vipLevel] ?? self::VIP_TIERS[1];
        $addSilver = (int)$tier['salary_silver'];
        $addGold = (int)$tier['salary_gold'];
        $addAct = (int)$tier['salary_act'];

        $newSilver = (int)($member['silver'] ?? 0) + $addSilver;
        $newGold = (int)($member['gold'] ?? 0) + $addGold;
        $newAct = min(200, (int)($member['act'] ?? 0) + $addAct);

        DB::table('member')->where('uid', $uid)->update([
            'silver' => $newSilver,
            'gold' => $newGold,
            'act' => $newAct,
        ]);

        Redis::set('vip_salary:' . $uid, (string)$now, 86400 * 2);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'silver' => $addSilver,
            'gold' => $addGold,
            'act' => $addAct,
            'message' => "成功领取 VIP{$vipLevel} 每日俸禄！获得银两 {$addSilver}、元宝 {$addGold}、军令 {$addAct}！",
        ];
    }

    /**
     * 领取 VIP 等级专属晋升大礼包: c=vip&m=get_gift
     */
    public static function get_gift(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $targetVip = (int)($ctx->input('level', $ctx->input('vip_level', 1)));

        $member = DB::table('member')->where('uid', $uid)->first();
        $myVip = max(1, (int)($member['vip'] ?? 1));

        if ($targetVip > $myVip) {
            return [
                'status' => -1,
                'message' => "尚未达成 VIP{$targetVip}，无法领取该礼包",
            ];
        }

        $claimed = Redis::getJson('vip_gifts:' . $uid) ?: [];
        if (in_array($targetVip, $claimed, true)) {
            return [
                'status' => 0,
                'message' => "VIP{$targetVip} 特权晋升礼包已领取过",
            ];
        }

        $tier = self::VIP_TIERS[$targetVip] ?? self::VIP_TIERS[1];
        $addSilver = (int)$tier['gift_silver'];
        $addGold = (int)$tier['gift_gold'];

        $newSilver = (int)($member['silver'] ?? 0) + $addSilver;
        $newGold = (int)($member['gold'] ?? 0) + $addGold;

        DB::table('member')->where('uid', $uid)->update([
            'silver' => $newSilver,
            'gold' => $newGold,
        ]);

        $claimed[] = $targetVip;
        Redis::setJson('vip_gifts:' . $uid, $claimed, 86400 * 365);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'level' => $targetVip,
            'silver' => $addSilver,
            'gold' => $addGold,
            'message' => "成功领取 VIP{$targetVip} 专属晋级礼包，获得银两 {$addSilver}、元宝 {$addGold}！",
        ];
    }
}
