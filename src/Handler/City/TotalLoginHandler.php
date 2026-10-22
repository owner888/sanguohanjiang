<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;
use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class TotalLoginHandler
{
    public const array REWARDS = [
        1 => ['day' => 1, 'silver' => 10000, 'gold' => 50, 'act' => 30],
        2 => ['day' => 2, 'silver' => 20000, 'gold' => 100, 'act' => 50],
        3 => ['day' => 3, 'silver' => 30000, 'gold' => 150, 'act' => 50],
        7 => ['day' => 7, 'silver' => 100000, 'gold' => 300, 'act' => 100],
        14 => ['day' => 14, 'silver' => 200000, 'gold' => 500, 'act' => 150],
    ];

    /**
     * 累登大厅主界面: c=totallogin&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $totalDays = (int)(Redis::get('totallogin_days:' . $uid) ?: 7); // 默认测试或体验环境累登7天
        $claimed = Redis::getJson('totallogin_claimed:' . $uid) ?: [];

        $list = [];
        foreach (self::REWARDS as $day => $r) {
            $isClaimed = in_array($day, $claimed, true);
            $canClaim = ($totalDays >= $day && !$isClaimed);

            $list[$day] = array_merge($r, [
                'status' => $isClaimed ? 2 : ($canClaim ? 1 : 0),
            ]);
        }

        return [
            'status' => 1,
            'total_login_days' => $totalDays,
            'list' => array_values($list),
            'message' => 'ok',
        ];
    }

    /**
     * 领取累登豪礼: c=totallogin&m=action
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $day = (int)($ctx->input('day', $ctx->input('id', 1)));

        if (!isset(self::REWARDS[$day])) {
            return ['status' => 0, 'message' => '奖励天数档位不存在'];
        }

        $totalDays = (int)(Redis::get('totallogin_days:' . $uid) ?: 7);
        if ($totalDays < $day) {
            return ['status' => 0, 'message' => "累计登录未达 {$day} 天，无法领取"];
        }

        $claimed = Redis::getJson('totallogin_claimed:' . $uid) ?: [];
        if (in_array($day, $claimed, true)) {
            return ['status' => 0, 'message' => "第 {$day} 天累登奖励已领取"];
        }

        $r = self::REWARDS[$day];
        $rewardSilver = (int)$r['silver'];
        $rewardGold = (int)$r['gold'];
        $rewardAct = (int)$r['act'];

        MemberHandler::addCurrency($uid, silver: $rewardSilver, gold: $rewardGold, act: $rewardAct);

        $claimed[] = $day;
        Redis::setJson('totallogin_claimed:' . $uid, $claimed, 86400 * 365);

        return [
            'status' => 1,
            'day' => $day,
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'act' => $rewardAct,
            'message' => "成功领取累登第 {$day} 天豪礼！获得银两 {$rewardSilver}、元宝 {$rewardGold}、军令 {$rewardAct}！",
        ];
    }
}
