<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class WarHandler
{
    /**
     * 皇城国战大厅: c=war&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $myCountry = (int)($member['country'] ?? 0);
        $points = (int)(Redis::get('war_points:' . $uid) ?: 200);

        return [
            'status' => 1,
            'title' => '魏蜀吴·皇城跨服封神国战',
            'season' => '第一赛季·问鼎中原',
            'my_points' => $points,
            'my_country' => $myCountry,
            'leaderboard' => [
                ['rank' => 1, 'country' => '魏国', 'score' => 125000],
                ['rank' => 2, 'country' => '蜀国', 'score' => 118000],
                ['rank' => 3, 'country' => '吴国', 'score' => 102000],
            ],
            'message' => 'ok',
        ];
    }

    /**
     * 报名出征皇城国战: c=war&m=entry
     */
    public static function entry(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $myCountry = (int)($member['country'] ?? 0);

        if ($myCountry <= 0) {
            return ['status' => 0, 'message' => '未加入魏、蜀、吴任意国家，无法参与皇城国战'];
        }

        $addPoints = 50;
        $curPoints = (int)(Redis::get('war_points:' . $uid) ?: 200) + $addPoints;
        Redis::set('war_points:' . $uid, (string)$curPoints, 86400 * 30);

        MemberHandler::addCurrency($uid, silver: 10000, reputation: 100);

        return [
            'status' => 1,
            'points' => $curPoints,
            'silver' => 10000,
            'message' => "皇城出征大捷！击退敌军先锋，斩获军功战绩点 +{$addPoints}、赏赐银两 10000！",
        ];
    }

    /**
     * 军功功勋商城: c=war&m=point_shop
     */
    public static function point_shop(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $points = (int)(Redis::get('war_points:' . $uid) ?: 200);

        return [
            'status' => 1,
            'points' => $points,
            'goods' => [
                1 => ['id' => 1, 'name' => '封神将魂令 x50', 'cost_points' => 100],
                2 => ['id' => 2, 'name' => '百炼龙纹精铁 x10', 'cost_points' => 150],
                3 => ['id' => 3, 'name' => '天命暗金宝匣 x1', 'cost_points' => 300],
            ],
            'message' => 'ok',
        ];
    }
}
