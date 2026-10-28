<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\City\MemberHandler;

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
     * 积分商城六大功勋宝匣
     */
    public const array POINT_CHESTS = [
        1 => [
            'id' => 1,
            'name' => '汉室青铜宝匣',
            'type' => 1,
            'integral' => 100,
            'desc' => '朝廷犒赏三军的青铜宝匣，内藏大量军资银两与基础将魂',
            'gettype1' => 1, 'getvalue1' => 50000,
            'gettype2' => 4, 'getvalue2' => 50,
            'gettype3' => 6, 'getvalue3' => 100,
            'gettype4' => 0, 'getvalue4' => 0,
            'gettype5' => 0, 'getvalue5' => 0,
            'info' => [],
        ],
        2 => [
            'id' => 2,
            'name' => '西凉白银宝匣',
            'type' => 2,
            'integral' => 200,
            'desc' => '西凉进贡的白银宝匣，开启可获丰厚银两与名将原魂',
            'gettype1' => 1, 'getvalue1' => 100000,
            'gettype2' => 4, 'getvalue2' => 100,
            'gettype3' => 6, 'getvalue3' => 200,
            'gettype4' => 0, 'getvalue4' => 0,
            'gettype5' => 0, 'getvalue5' => 0,
            'info' => [],
        ],
        3 => [
            'id' => 3,
            'name' => '玄武黄金宝匣',
            'type' => 3,
            'integral' => 500,
            'desc' => '玄武神殿秘藏的黄金宝匣，内藏珍贵黄宝石与大笔军饷',
            'gettype1' => 1, 'getvalue1' => 250000,
            'gettype2' => 2, 'getvalue2' => 10,
            'gettype3' => 4, 'getvalue3' => 250,
            'gettype4' => 6, 'getvalue4' => 500,
            'gettype5' => 0, 'getvalue5' => 0,
            'info' => [],
        ],
        4 => [
            'id' => 4,
            'name' => '朱雀翡翠宝匣',
            'type' => 4,
            'integral' => 1000,
            'desc' => '朱雀圣灵庇佑的翡翠宝匣，内含极品紫宝石与海量将魂',
            'gettype1' => 1, 'getvalue1' => 500000,
            'gettype2' => 3, 'getvalue2' => 10,
            'gettype3' => 4, 'getvalue3' => 600,
            'gettype4' => 6, 'getvalue4' => 1000,
            'gettype5' => 0, 'getvalue5' => 0,
            'info' => [],
        ],
        5 => [
            'id' => 5,
            'name' => '青龙神火宝匣',
            'type' => 4,
            'integral' => 2000,
            'desc' => '青龙降世神火宝匣，内藏海量稀有宝石、神魄原魂与雄厚声望',
            'gettype1' => 1, 'getvalue1' => 1000000,
            'gettype2' => 3, 'getvalue2' => 25,
            'gettype3' => 4, 'getvalue3' => 1500,
            'gettype4' => 6, 'getvalue4' => 2500,
            'gettype5' => 0, 'getvalue5' => 0,
            'info' => [],
        ],
        6 => [
            'id' => 6,
            'name' => '白虎天罡宝匣',
            'type' => 5,
            'integral' => 5000,
            'desc' => '天罡绝顶白虎宝匣，镇国至宝，开启立获天量军资祥瑞',
            'gettype1' => 1, 'getvalue1' => 3000000,
            'gettype2' => 3, 'getvalue2' => 60,
            'gettype3' => 4, 'getvalue3' => 5000,
            'gettype4' => 6, 'getvalue4' => 8000,
            'gettype5' => 0, 'getvalue5' => 0,
            'info' => [],
        ],
    ];

    /**
     * 军功功勋商城: c=war&m=point_shop
     */
    public static function point_shop(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $points = (int)(Redis::get('war_points:' . $uid) ?: 888);

        $list = array_values(self::POINT_CHESTS);

        return [
            'status' => 1,
            'point' => $points,
            'points' => $points,
            'list' => $list,
            'goods' => [
                1 => ['id' => 1, 'name' => '封神将魂令 x50', 'cost_points' => 100],
                2 => ['id' => 2, 'name' => '百炼龙纹精铁 x10', 'cost_points' => 150],
                3 => ['id' => 3, 'name' => '天命暗金宝匣 x1', 'cost_points' => 300],
            ],
            'message' => 'ok',
        ];
    }

    /**
     * 积分兑换宝箱: c=war&m=point_buy
     */
    public static function point_buy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)($ctx->input('id', 1));

        $chest = self::POINT_CHESTS[$id] ?? self::POINT_CHESTS[1];
        $costPoint = (int)$chest['integral'];

        $points = (int)(Redis::get('war_points:' . $uid) ?: 888);
        if ($points < $costPoint) {
            return [
                'status' => 2,
                'message' => '积分不足，无法兑换该宝箱',
            ];
        }

        $newPoints = $points - $costPoint;
        Redis::set('war_points:' . $uid, (string)$newPoints, 86400 * 30);

        // 奖励结算
        $rewardSilver = (int)($chest['getvalue1'] ?? 50000);
        $rewardSoul = (int)($chest['getvalue2'] ?? 50);
        $rewardRep = (int)($chest['getvalue3'] ?? 100);

        MemberHandler::addCurrency($uid, silver: $rewardSilver, soul: $rewardSoul, reputation: $rewardRep);

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $allSilver = (int)($member['silver'] ?? 0);

        return [
            'status' => 1,
            'list' => $chest,
            'point' => $newPoints,
            'silver' => $rewardSilver,
            'allsilver' => $allSilver,
            'all_silver' => $allSilver,
            'message' => "成功花费 {$costPoint} 积分兑换【{$chest['name']}】！获得丰厚物资馈赠！",
        ];
    }
}
