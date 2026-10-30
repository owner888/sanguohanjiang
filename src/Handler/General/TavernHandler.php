<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 酒馆名将招募、商贾会客贸易与宝石兑换处理器 (DDD General Domain)
 */
final class TavernHandler
{
    /**
     * 五大商贾席位配置
     * gettype: 1 蓝宝石, 2 黄宝石, 3 紫宝石
     */
    public const array TRADERS = [
        1 => ['id' => 1, 'name' => '走方游医', 'description' => '初涉江湖的小商贾，产出蓝宝石', 'cost' => 4000, 'trading' => 20, 'gettype' => 1, 'getvalue' => 1],
        2 => ['id' => 2, 'name' => '西域商贩', 'description' => '运送西域香料的商人，产出蓝宝石/黄宝石', 'cost' => 6000, 'trading' => 40, 'gettype' => 2, 'getvalue' => 1],
        3 => ['id' => 3, 'name' => '豪门贾客', 'description' => '坐贾富室，产出黄宝石', 'cost' => 9000, 'trading' => 60, 'gettype' => 2, 'getvalue' => 2],
        4 => ['id' => 4, 'name' => '异域巨商', 'description' => '往来大秦波斯的巨商，产出黄宝石/紫宝石', 'cost' => 14000, 'trading' => 100, 'gettype' => 3, 'getvalue' => 1],
        5 => ['id' => 5, 'name' => '富甲天下', 'description' => '天下巨贾巨富，必出紫宝石', 'cost' => 20000, 'trading' => 200, 'gettype' => 3, 'getvalue' => 2],
    ];

    /**
     * 酒馆常驻名将池配置
     *
     * @var array<int, array<string, mixed>>
     */
    public const array HERO_POOL = [
        10 => [
            'id' => 10,
            'name' => '张飞',
            'image' => 54,
            'quality' => 4,
            'cost_silver' => 5000,
            'wuli' => 99,
            'zhili' => 45,
            'hp' => 1960,
            'attack' => 380,
            'defense' => 130,
            'skill' => '万夫莫敌',
        ],
        11 => [
            'id' => 11,
            'name' => '关羽',
            'image' => 58,
            'quality' => 5,
            'cost_silver' => 10000,
            'wuli' => 98,
            'zhili' => 82,
            'hp' => 2050,
            'attack' => 410,
            'defense' => 150,
            'skill' => '春秋十字斩',
        ],
        12 => [
            'id' => 12,
            'name' => '诸葛亮',
            'image' => 76,
            'quality' => 5,
            'cost_silver' => 20000,
            'wuli' => 55,
            'zhili' => 100,
            'hp' => 1680,
            'attack' => 400,
            'defense' => 155,
            'skill' => '卧龙天火',
        ],
        13 => [
            'id' => 13,
            'name' => '赵云',
            'image' => 25,
            'quality' => 5,
            'cost_silver' => 15000,
            'wuli' => 96,
            'zhili' => 78,
            'hp' => 1920,
            'attack' => 395,
            'defense' => 145,
            'skill' => '龙胆穿心',
        ],
    ];

    /**
     * 酒馆主界面 / 会客贸易主界面: c=tavern&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        // 1. 武将招募列表
        $ownedGenerals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->get();
        $ownedMap = [];
        foreach ($ownedGenerals as $g) {
            $ownedMap[(int)$g['generalid']] = true;
        }

        $list = [];
        $idx = 1;
        foreach (self::HERO_POOL as $hero) {
            $gid = (int)$hero['id'];
            $list[$idx] = array_merge($hero, [
                'is_recruited' => isset($ownedMap[$gid]) ? 1 : 0,
            ]);
            $idx++;
        }

        // 2. 商贾会客数据 (来自 Redis 缓存)
        $traderData = self::getUserTraderData($uid);

        $siteInfo = [];
        foreach (self::TRADERS as $tid => $trader) {
            $siteInfo[(string)$tid] = [
                'id' => $tid,
                'name' => $trader['name'],
                'description' => $trader['description'],
                'cost' => (int)$trader['cost'],
                'trading' => (int)$trader['trading'],
            ];
        }

        $curSite = max(1, min(5, (int)($traderData['site'] ?? 1)));

        return [
            'status' => 1,
            'list' => $list,
            'jade' => (int)($traderData['get1'] ?? 0) + (int)($traderData['get2'] ?? 0) + (int)($traderData['get3'] ?? 0),
            // 会客贸易核心字段
            'get1' => (int)($traderData['get1'] ?? 0),
            'get2' => (int)($traderData['get2'] ?? 0),
            'get3' => (int)($traderData['get3'] ?? 0),
            'site' => $curSite,
            'site1' => 1,
            'site2' => (int)($traderData['site2'] ?? 0),
            'site3' => (int)($traderData['site3'] ?? 0),
            'site4' => (int)($traderData['site4'] ?? 0),
            'site5' => (int)($traderData['site5'] ?? 0),
            'site_info' => $siteInfo,
            'isfree' => ($traderData['last_date'] !== date('Ymd')) ? 1 : 0,
            'silver' => (int)($member['silver'] ?? 0),
            'gold' => (int)($member['gold'] ?? 0),
            'message' => 'ok',
        ];
    }

    /**
     * 可招募武将列表: c=tavern&m=get_list
     */
    public static function get_list(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $traderData = self::getUserTraderData($uid);

        $ownedGenerals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->get();
        $ownedMap = [];
        foreach ($ownedGenerals as $g) {
            $ownedMap[(int)$g['generalid']] = true;
        }

        $barList = [];
        $idx = 1;
        foreach (self::HERO_POOL as $gid => $hero) {
            $isOwn = isset($ownedMap[$gid]) ? 1 : 0;
            $star = match ((int)$hero['quality']) {
                5 => 5,
                4 => 4,
                default => 3,
            };
            $vocation = ($gid === 12) ? 2 : 1; // 诸葛亮为谋士(2)，关张赵为武将(1)

            $barList[$idx] = [
                'generalid' => $gid,
                'name' => (string)$hero['name'],
                'paytype' => min(3, max(1, (int)$hero['quality'] - 2)), // 1 蓝宝石, 2 黄宝石, 3 紫宝石
                'price' => (int)$hero['quality'] * 5,
                'isown' => $isOwn,
                'open' => 1,
                'need_reputation' => 0,
                'info' => [
                    'id' => $gid,
                    'name' => (string)$hero['name'],
                    'type' => $vocation,
                    'image' => 'hero_' . $gid,
                    'wuli' => (int)$hero['wuli'],
                    'zhili' => (int)$hero['zhili'],
                    'tili' => 85,
                    'star' => $star,
                    'talent' => [
                        'name' => '神威',
                        'description' => "天生神力，基础攻击力大幅提升 {$hero['attack']} 点",
                    ],
                    'skill' => [
                        'name' => (string)$hero['skill'],
                        'description' => "释放绝技【{$hero['skill']}】，对敌军造成致命轰击！",
                    ],
                ],
            ];
            $idx++;
        }

        $jade = [
            1 => (int)($traderData['get1'] ?? 15),
            2 => (int)($traderData['get2'] ?? 8),
            3 => (int)($traderData['get3'] ?? 3),
        ];

        return [
            'status' => 1,
            'page' => 1,
            'perpage' => 100,
            'totals' => count($barList),
            'jade' => $jade,
            'reputation' => (int)($member['reputation'] ?? 0),
            'list' => $barList,
            'message' => 'ok',
        ];
    }

    /**
     * 招募将领: c=tavern&m=buy
     */
    public static function buy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('id', $ctx->input('generalid', 10)));

        if (!isset(self::HERO_POOL[$gid])) {
            return [
                'status' => 0,
                'message' => '未找到该武将招募信息',
            ];
        }

        $heroConfig = self::HERO_POOL[$gid];
        $costSilver = (int)$heroConfig['cost_silver'];

        // 检查是否已经拥有
        $exist = DB::table('general')
            ->where('uid', $uid)
            ->where('generalid', $gid)
            ->whereNull('deleted_at')
            ->first();

        if ($exist !== null) {
            return [
                'status' => 4,
                'message' => '您已经招募过该武将',
            ];
        }

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($member === null || (int)$member['silver'] < $costSilver) {
            return [
                'status' => 2,
                'message' => '银两不足，无法招募该武将',
            ];
        }

        // 扣除银两
        $newSilver = (int)$member['silver'] - $costSilver;
        DB::table('member')->where('uid', $uid)->update(['silver' => $newSilver]);

        // 插入武将
        DB::table('general')->insert([
            'uid' => $uid,
            'generalid' => $gid,
            'name' => $heroConfig['name'],
            'level' => 1,
            'exp' => 0,
            'pos' => 0,
            'matrix_id' => 1,
            'status' => 1,
        ]);

        Redis::del('player:' . $uid);

        $star = match ((int)$heroConfig['quality']) {
            5 => 5,
            4 => 4,
            default => 3,
        };
        $vocation = ($gid === 12) ? 2 : 1;
        $payType = min(3, max(1, (int)$heroConfig['quality'] - 2));

        $traderData = self::getUserTraderData($uid);
        $remainJade = (int)($traderData['get' . $payType] ?? 10);

        return [
            'status' => 1,
            'type' => $payType,
            'price' => $costSilver,
            'jade' => $remainJade,
            'general' => [
                'generalid' => $gid,
                'name' => (string)$heroConfig['name'],
                'type' => $vocation,
                'image' => 'hero_' . $gid,
                'star' => $star,
                'wuli' => (int)$heroConfig['wuli'],
                'zhili' => (int)$heroConfig['zhili'],
                'tili' => 85,
                'talent' => [
                    'name' => '神威',
                    'description' => "天生神力，基础攻击力大幅提升 {$heroConfig['attack']} 点",
                ],
                'skill' => [
                    'name' => (string)$heroConfig['skill'],
                    'description' => "释放绝技【{$heroConfig['skill']}】，对敌军造成致命轰击！",
                ],
            ],
            // 向下兼容保留原字段
            'gid' => $gid,
            'name' => $heroConfig['name'],
            'cost_silver' => $costSilver,
            'remain_silver' => $newSilver,
            'message' => "招募成功！【{$heroConfig['name']}】已加入您的麾下！",
        ];
    }

    /**
     * 会客贸易: c=tavern&m=trade
     */
    public static function trade(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $type = (int)($ctx->input('type', 0)); // 0: 银两贸易, 1: 元宝贸易
        $site = (int)($ctx->input('s', 1));
        if ($site < 1 || $site > 5) {
            $site = 1;
        }

        $traderCfg = self::TRADERS[$site] ?? self::TRADERS[1];
        $traderData = self::getUserTraderData($uid);

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $isFree = ($type === 0 && $traderData['last_date'] !== date('Ymd'));

        if ($type === 1) {
            $costGold = (int)$traderCfg['trading'];
            if ((int)($member['gold'] ?? 0) < $costGold) {
                return ['status' => -1, 'message' => '元宝不足'];
            }
            MemberHandler::deductCurrency($uid, gold: $costGold);
        } else {
            if (!$isFree) {
                $costSilver = (int)$traderCfg['cost'];
                if ((int)($member['silver'] ?? 0) < $costSilver) {
                    return ['status' => -2, 'message' => '银两不足'];
                }
                MemberHandler::deductCurrency($uid, silver: $costSilver);
            }
        }

        // 产出宝石
        $gemType = (int)$traderCfg['gettype'];
        $gemVal = (int)$traderCfg['getvalue'];
        $traderData['get' . $gemType] = (int)($traderData['get' . $gemType] ?? 0) + $gemVal;

        // 计算下一席位 (概率晋级或重置回 1 号)
        $upRate = match ($site) {
            1 => 60,
            2 => 50,
            3 => 40,
            4 => 30,
            default => 0,
        };

        if (mt_rand(1, 100) <= $upRate && $site < 5) {
            $nextSite = $site + 1;
            $traderData['site' . $nextSite] = 1;
        } else {
            $nextSite = 1;
            for ($s = 2; $s <= 5; $s++) {
                $traderData['site' . $s] = 0;
            }
        }
        $traderData['site'] = $nextSite;
        $traderData['last_date'] = date('Ymd');

        Redis::setJson("user_trader:{$uid}", $traderData, 86400 * 30);
        Redis::del('player:' . $uid);

        $freshMember = DB::table('member')->where('uid', $uid)->first();
        $nextCfg = self::TRADERS[$nextSite] ?? self::TRADERS[1];

        return [
            'status' => 1,
            'site' => $nextSite,
            'id' => $nextSite,
            'addtype' => $gemType,
            'addnum' => $gemVal,
            'number' => (int)$traderData['get' . $gemType],
            'cost' => $nextCfg['cost'],
            'trading' => $nextCfg['trading'],
            'silver' => (int)($freshMember['silver'] ?? 0),
            'gold' => (int)($freshMember['gold'] ?? 0),
            'site1' => 1,
            'site2' => (int)($traderData['site2'] ?? 0),
            'site3' => (int)($traderData['site3'] ?? 0),
            'site4' => (int)($traderData['site4'] ?? 0),
            'site5' => (int)($traderData['site5'] ?? 0),
            'isfree' => 0,
            'get1' => $traderData['get1'],
            'get2' => $traderData['get2'],
            'get3' => $traderData['get3'],
            'message' => '贸易完成',
        ];
    }

    /**
     * 批量贸易: c=tavern&m=trade_batch
     */
    public static function trade_batch(Context $ctx): array
    {
        return self::trade($ctx);
    }

    /**
     * 获取玩家商贾会客记录
     *
     * @return array<string, mixed>
     */
    private static function getUserTraderData(int $uid): array
    {
        $key = "user_trader:{$uid}";
        $data = Redis::getJson($key);

        if (!is_array($data)) {
            $data = [
                'site' => 1,
                'site1' => 1,
                'site2' => 0,
                'site3' => 0,
                'site4' => 0,
                'site5' => 0,
                'get1' => 0,
                'get2' => 0,
                'get3' => 0,
                'last_date' => '',
            ];
            Redis::setJson($key, $data, 86400 * 30);
        }

        return $data;
    }
}
