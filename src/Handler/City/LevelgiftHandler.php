<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;
use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class LevelgiftHandler
{
    public const array GIFTS = [
        5  => ['level' => 5, 'name' => '5级成长礼包', 'silver' => 10000, 'gold' => 20, 'act' => 20],
        10 => ['level' => 10, 'name' => '10级建功礼包', 'silver' => 20000, 'gold' => 50, 'act' => 30],
        20 => ['level' => 20, 'name' => '20级拜将礼包', 'silver' => 50000, 'gold' => 100, 'act' => 50],
        30 => ['level' => 30, 'name' => '30级封侯礼包', 'silver' => 100000, 'gold' => 200, 'act' => 80],
        40 => ['level' => 40, 'name' => '40级称霸礼包', 'silver' => 200000, 'gold' => 300, 'act' => 100],
        50 => ['level' => 50, 'name' => '50级问鼎礼包', 'silver' => 500000, 'gold' => 500, 'act' => 150],
    ];

    /**
     * 等级礼包主界面: c=levelgift&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $myLvl = max(1, (int)($member['level'] ?? 1));

        $claimed = Redis::getJson('level_gifts:' . $uid) ?: [];

        $list = [];
        foreach (self::GIFTS as $lvl => $g) {
            $isClaimed = in_array($lvl, $claimed, true);
            $canClaim = ($myLvl >= $lvl && !$isClaimed);

            $list[$lvl] = array_merge($g, [
                'status' => $isClaimed ? 2 : ($canClaim ? 1 : 0),
            ]);
        }

        return [
            'status' => 1,
            'my_level' => $myLvl,
            'list' => array_values($list),
            'message' => 'ok',
        ];
    }

    /**
     * 领取等级成长礼包: c=levelgift&m=get_reward
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $targetLvl = (int)($ctx->input('level', 5));

        if (!isset(self::GIFTS[$targetLvl])) {
            return ['status' => 0, 'message' => '等级礼包不存在'];
        }

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $myLvl = max(1, (int)($member['level'] ?? 1));

        if ($myLvl < $targetLvl) {
            return ['status' => 0, 'message' => "当前等级尚未达到 {$targetLvl} 级，无法领取"];
        }

        $claimed = Redis::getJson('level_gifts:' . $uid) ?: [];
        if (in_array($targetLvl, $claimed, true)) {
            return ['status' => 0, 'message' => "{$targetLvl} 级成长礼包已经领取过了"];
        }

        $g = self::GIFTS[$targetLvl];
        $rewardSilver = (int)$g['silver'];
        $rewardGold = (int)$g['gold'];
        $rewardAct = (int)$g['act'];

        MemberHandler::addCurrency($uid, silver: $rewardSilver, gold: $rewardGold, act: $rewardAct);

        $claimed[] = $targetLvl;
        Redis::setJson('level_gifts:' . $uid, $claimed, 86400 * 365);

        return [
            'status' => 1,
            'level' => $targetLvl,
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'act' => $rewardAct,
            'message' => "成功领取【{$g['name']}】！斩获银两 {$rewardSilver}、元宝 {$rewardGold}、军令 {$rewardAct}！",
        ];
    }
}
