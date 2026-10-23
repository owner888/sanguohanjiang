<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * VIP 每日工资俸禄与尊享特权奖励处理器 (DDD City Domain)
 */
final class VipwageHandler
{
    /**
     * VIP 1~10 级每日工资配置字典
     */
    public const array WAGE_CONFIG = [
        1 => ['vip' => 1, 'type1' => 1, 'get1' => 100, 'type2' => 2, 'get2' => 5, 'type3' => 3, 'get3' => 5],
        2 => ['vip' => 2, 'type1' => 1, 'get1' => 150, 'type2' => 2, 'get2' => 10, 'type3' => 3, 'get3' => 10],
        3 => ['vip' => 3, 'type1' => 1, 'get1' => 200, 'type2' => 2, 'get2' => 15, 'type3' => 7, 'get3' => 50],
        4 => ['vip' => 4, 'type1' => 1, 'get1' => 250, 'type2' => 2, 'get2' => 20, 'type3' => 7, 'get3' => 100],
        5 => ['vip' => 5, 'type1' => 1, 'get1' => 300, 'type2' => 2, 'get2' => 30, 'type3' => 7, 'get3' => 150],
        6 => ['vip' => 6, 'type1' => 1, 'get1' => 400, 'type2' => 2, 'get2' => 40, 'type3' => 7, 'get3' => 200],
        7 => ['vip' => 7, 'type1' => 1, 'get1' => 500, 'type2' => 2, 'get2' => 50, 'type3' => 8, 'get3' => 50],
        8 => ['vip' => 8, 'type1' => 1, 'get1' => 600, 'type2' => 2, 'get2' => 60, 'type3' => 8, 'get3' => 100],
        9 => ['vip' => 9, 'type1' => 1, 'get1' => 800, 'type2' => 2, 'get2' => 80, 'type3' => 8, 'get3' => 150],
        10 => ['vip' => 10, 'type1' => 1, 'get1' => 1000, 'type2' => 2, 'get2' => 100, 'type3' => 8, 'get3' => 200],
    ];

    /**
     * VIP 工资主界面: c=vipwage&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $vip = max(0, min(10, (int)($member['vip'] ?? 1)));
        $level = max(1, (int)($member['level'] ?? 1));

        // 格式化 1~10 级 VIP 工资列表 (银两根据等级动态计算)
        $list = [];
        $myReward = [];

        foreach (self::WAGE_CONFIG as $tier => $cfg) {
            $item = $cfg;
            $item['get1'] = $cfg['get1'] * $level;
            $list[] = $item;

            if ($tier === $vip) {
                $myReward = $item;
            }
        }

        // 校验今日是否已领
        $claimDate = Redis::get("vipwage_date:{$uid}");
        $alreadyClaimed = ($claimDate === date('Ymd'));
        $getStatus = ($vip <= 0) ? 0 : ($alreadyClaimed ? 2 : 1);

        return [
            'status' => 1,
            'list' => $list,
            'my_reward' => $myReward,
            'get' => $getStatus,
            'message' => 'ok',
        ];
    }

    /**
     * 领取 VIP 每日工资: c=vipwage&m=get_vip_wage
     */
    public static function get_vip_wage(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $vip = (int)($member['vip'] ?? 0);

        if ($vip <= 0) {
            return ['status' => -1, 'message' => 'VIP 等级不足，暂无法领取工资'];
        }

        $todayStr = date('Ymd');
        $claimDate = Redis::get("vipwage_date:{$uid}");
        if ($claimDate === $todayStr) {
            return ['status' => -3, 'message' => '今日 VIP 工资已领取，明日再来吧'];
        }

        $level = max(1, (int)($member['level'] ?? 1));
        $cfg = self::WAGE_CONFIG[$vip] ?? self::WAGE_CONFIG[1];
        $earnedSilver = $cfg['get1'] * $level;
        $earnedLeap = (int)$cfg['get2'];
        $extraReward = (int)$cfg['get3'];

        // 发放银两
        MemberHandler::addCurrency($uid, silver: $earnedSilver);

        // 发放突飞卡到 member_field
        $field = DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($field !== null) {
            $curFreeLeap = (int)($field['freeleap'] ?? 0);
            DB::table('member_field')->where('uid', $uid)->update([
                'freeleap' => $curFreeLeap + $earnedLeap,
            ]);
        }

        // 发放军令 / 将魂原魂
        if ($cfg['type3'] === 3) {
            MemberHandler::addCurrency($uid, act: $extraReward);
        } elseif ($cfg['type3'] === 7) {
            $curSoul = (int)($member['soul'] ?? 0);
            DB::table('member')->where('uid', $uid)->update(['soul' => $curSoul + $extraReward]);
        }

        Redis::set("vipwage_date:{$uid}", $todayStr, 86400 * 2);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'message' => "VIP 工资领取成功，获得 {$earnedSilver} 银两及专属特权物资！",
        ];
    }
}
