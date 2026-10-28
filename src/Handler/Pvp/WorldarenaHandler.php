<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 跨服远征演武、群英巅峰赛与天梯押注处理器 (DDD PvP Domain)
 */
final class WorldarenaHandler
{
    /**
     * 跨服远征功勋商城商品字典 (type 1~8 对应客户端前端图标与属性)
     */
    public const array SHOP_ITEMS = [
        [
            'id' => 1,
            'name' => '万两军饷',
            'type' => 1, // 银两
            'add1' => 10000,
            'add2' => 50000,
            'description' => '跨服远征专属犒劳军资，开启可获天量银两',
            'exploit' => 100,
        ],
        [
            'id' => 2,
            'name' => '深邃蓝宝石',
            'type' => 2, // 蓝宝石
            'add1' => 5,
            'add2' => 20,
            'description' => '深海采撷的蓝宝石，可用于酒馆名将招募',
            'exploit' => 150,
        ],
        [
            'id' => 3,
            'name' => '耀眼黄宝石',
            'type' => 3, // 黄宝石
            'add1' => 3,
            'add2' => 15,
            'description' => '大漠地心凝结的黄宝石，可招募顶级名将',
            'exploit' => 250,
        ],
        [
            'id' => 4,
            'name' => '极品紫宝石',
            'type' => 4, // 紫宝石
            'add1' => 2,
            'add2' => 10,
            'description' => '天地精气汇聚的紫宝石，可招募绝世神将',
            'exploit' => 400,
        ],
        [
            'id' => 5,
            'name' => '远征军令包',
            'type' => 5, // 军令
            'add1' => 50,
            'add2' => 200,
            'description' => '跨服出征战令，使用立即增加 50 点军令体力',
            'exploit' => 80,
        ],
        [
            'id' => 6,
            'name' => '神速突飞令',
            'type' => 6, // 突飞卡
            'add1' => 20,
            'add2' => 100,
            'description' => '校场操练奇书，使武将瞬间突飞猛进大量经验',
            'exploit' => 120,
        ],
        [
            'id' => 7,
            'name' => '天工精炼石',
            'type' => 7, // 精炼石
            'add1' => 15,
            'add2' => 60,
            'description' => '锻造装备洗练神石，赋予装备高阶神属性',
            'exploit' => 180,
        ],
        [
            'id' => 8,
            'name' => '乾坤强化符',
            'type' => 8, // 强化石
            'add1' => 10,
            'add2' => 50,
            'description' => '铁匠铺淬炼宝符，提升装备强化成功率',
            'exploit' => 200,
        ],
    ];

    /**
     * 跨服远征主界面: c=worldarena&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);

        // 跨服昨日巅峰前三
        $topThree = [
            [
                'rank' => 1,
                'uid' => 8888,
                'serverid' => 1,
                'nickname' => '常山赵子龙',
                'tree' => 0,
            ],
            [
                'rank' => 2,
                'uid' => 8889,
                'serverid' => 1,
                'nickname' => '关云长',
                'tree' => 0,
            ],
            [
                'rank' => 3,
                'uid' => 8890,
                'serverid' => 1,
                'nickname' => '诸葛孔明',
                'tree' => 0,
            ],
        ];

        // 押注状态
        $betInfo = [
            'bet_status' => 0,
            'times' => 0,
            'support' => 0,
            'silver' => 0,
            'cd' => 0,
            'nickname' => '',
            'rank' => [
                'before' => 0,
                'after' => 0,
            ],
        ];

        // 排名功勋奖励
        $rewardInfo = [
            'rank' => 1,
            'exploit' => 500,
            'cd' => 0,
        ];

        return [
            'status' => 1,
            'top' => $topThree,
            'bet' => $betInfo,
            'reward' => $rewardInfo,
            'message' => 'ok',
        ];
    }

    /**
     * 领取跨服排行功勋奖励: c=worldarena&m=get_rank_reward
     */
    public static function get_rank_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $rewardExploit = 500;

        $userExploit = (int)(Redis::get("worldarena_exploit:{$uid}") ?: 1000);
        $newExploit = $userExploit + $rewardExploit;
        Redis::set("worldarena_exploit:{$uid}", (string)$newExploit, 86400 * 30);

        return [
            'status' => 1,
            'add' => $rewardExploit,
            'exploit' => $newExploit,
            'cd' => 86400,
            'message' => "领取成功，获得功勋 {$rewardExploit}！",
        ];
    }

    /**
     * 跨服功勋商店主页: c=worldarena&m=shop
     */
    public static function shop(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $userExploit = (int)(Redis::get("worldarena_exploit:{$uid}") ?: 1000);

        $logs = [
            [
                'sid' => 1,
                'serverid' => 1,
                'groupname' => '1服·龙腾万里',
                'name' => '常山赵子龙',
                'item_name' => '万两军饷',
                'dateline' => '刚刚',
            ],
        ];

        return [
            'status' => 1,
            'list' => self::SHOP_ITEMS,
            'log' => $logs,
            'exploit' => $userExploit,
            'message' => 'ok',
        ];
    }

    /**
     * 跨服功勋商店购买: c=worldarena&m=buy
     */
    public static function buy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)($ctx->input('id', 1));

        $target = null;
        foreach (self::SHOP_ITEMS as $item) {
            if ($item['id'] === $id) {
                $target = $item;
                break;
            }
        }

        if ($target === null) {
            return ['status' => -1, 'message' => '商品不存在'];
        }

        $cost = (int)$target['exploit'];
        $userExploit = (int)(Redis::get("worldarena_exploit:{$uid}") ?: 1000);

        if ($userExploit < $cost) {
            return ['status' => -2, 'message' => '功勋不足'];
        }

        $newExploit = $userExploit - $cost;
        Redis::set("worldarena_exploit:{$uid}", (string)$newExploit, 86400 * 30);

        if ($target['type'] === 1) {
            MemberHandler::addCurrency($uid, silver: 100000);
        } elseif ($target['type'] === 5) {
            MemberHandler::addCurrency($uid, act: 50);
        }

        return [
            'status' => 1,
            'exploit' => $newExploit,
            'message' => "成功花费 {$cost} 功勋兑换【{$target['name']}】！",
        ];
    }

    /**
     * 跨服争霸备战信息: c=worldarena&m=enter
     */
    public static function enter(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => '进入跨服争霸备战区',
        ];
    }
}
