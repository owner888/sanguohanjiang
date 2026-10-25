<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

/**
 * 装备商城、军令采买与全服消费记录处理器 (DDD Activity Domain)
 */
final class ShopHandler
{
    /**
     * 商城珍宝道具字典
     */
    public const array SHOP_ITEMS = [
        1 => [
            'id' => 101,
            'name' => '百炼青钢剑',
            'type' => 1,
            'quality' => 3,
            'shoptype' => 1, // 1 元宝, 2 银两
            'price' => 50,
            'maxtimes' => 10,
            'needlevel' => 1,
            'need_viplevel' => 0,
            'power' => 120,
            'description' => '百炼精钢锻造的神兵利刃，削铁如泥，大幅增强攻击力',
            'position' => 1,
        ],
        2 => [
            'id' => 102,
            'name' => '天命校尉兵符',
            'type' => 4,
            'quality' => 3,
            'shoptype' => 1,
            'price' => 80,
            'maxtimes' => 10,
            'needlevel' => 1,
            'need_viplevel' => 0,
            'power' => 420,
            'description' => '受命于天的汉室校尉兵符，蕴含雄厚气血，大幅增加生命上限',
            'position' => 2,
        ],
        3 => [
            'id' => 103,
            'name' => '玄铁明光铠',
            'type' => 2,
            'quality' => 3,
            'shoptype' => 1,
            'price' => 60,
            'maxtimes' => 10,
            'needlevel' => 1,
            'need_viplevel' => 0,
            'power' => 80,
            'description' => '日月生辉的明光重铠，刀枪不入，固若金汤',
            'position' => 3,
        ],
        4 => [
            'id' => 104,
            'name' => '西极烈风骢',
            'type' => 3,
            'quality' => 3,
            'shoptype' => 1,
            'price' => 120,
            'maxtimes' => 5,
            'needlevel' => 20,
            'need_viplevel' => 1,
            'power' => 200,
            'description' => '产自大宛的西域良驹，迅疾如风，神勇非凡',
            'position' => 4,
        ],
        5 => [
            'id' => 105,
            'name' => '诸葛兵法残卷',
            'type' => 4,
            'quality' => 4,
            'shoptype' => 1,
            'price' => 200,
            'maxtimes' => 5,
            'needlevel' => 30,
            'need_viplevel' => 2,
            'power' => 600,
            'description' => '卧龙先生亲手书写的奇门兵法，内藏排兵布阵之玄机',
            'position' => 5,
        ],
        6 => [
            'id' => 106,
            'name' => '太公六韬兵书',
            'type' => 4,
            'quality' => 5,
            'shoptype' => 1,
            'price' => 500,
            'maxtimes' => 3,
            'needlevel' => 40,
            'need_viplevel' => 4,
            'power' => 1200,
            'description' => '姜太公辅佐周室的不世天书，神鬼莫测，天道乾坤',
            'position' => 6,
        ],
    ];

    /**
     * 商城列表: c=shop&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);

        $nowList = [];
        $totals = 0;
        foreach (self::SHOP_ITEMS as $pos => $item) {
            $nowList[$pos] = [
                'id' => (int)$item['id'],
                'name' => (string)$item['name'],
                'type' => (int)$item['type'],
                'quality' => (int)$item['quality'],
                'need_viplevel' => (int)$item['need_viplevel'],
                'position' => $pos,
                'price' => (int)$item['price'],
                'coin' => 0,
            ];
            $totals++;
        }

        // 默认第一件商品的详细信息
        $firstItem = self::SHOP_ITEMS[1];
        $info = [
            'info' => array_merge($firstItem, [
                'nowmaxtimes' => (int)$firstItem['maxtimes'],
            ]),
            'need_viplevel' => (int)$firstItem['need_viplevel'],
            'cd' => 0,
        ];

        $logs = Redis::getJson('global_shop_logs') ?: [
            [
                'uid' => 8888,
                'username' => '常山赵子龙',
                'itemname' => '天命校尉兵符',
                'quality' => 3,
                'dateline' => '刚刚',
            ],
            [
                'uid' => 8889,
                'username' => '关云长',
                'itemname' => '百炼青钢剑',
                'quality' => 3,
                'dateline' => '2分钟前',
            ],
        ];

        return [
            'status' => 1,
            'totals' => $totals,
            'list' => $nowList,
            'info' => $info,
            'log_list' => array_values($logs),
            'message' => 'ok',
        ];
    }

    /**
     * 查询单件商品详情: c=shop&m=get_info
     */
    public static function get_info(Context $ctx): array
    {
        $itemId = (int)($ctx->input('id', 101));

        $target = null;
        foreach (self::SHOP_ITEMS as $item) {
            if ($item['id'] === $itemId) {
                $target = $item;
                break;
            }
        }

        if ($target === null) {
            $target = self::SHOP_ITEMS[1];
        }

        return [
            'status' => 1,
            'info' => array_merge($target, [
                'nowmaxtimes' => (int)$target['maxtimes'],
            ]),
            'need_viplevel' => (int)$target['need_viplevel'],
            'cd' => 0,
            'message' => 'ok',
        ];
    }

    /**
     * 购买装备/道具: c=shop&m=buy
     */
    public static function buy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $itemId = (int)($ctx->input('id', 101));

        $target = null;
        foreach (self::SHOP_ITEMS as $item) {
            if ($item['id'] === $itemId) {
                $target = $item;
                break;
            }
        }

        if ($target === null) {
            return ['status' => -1, 'message' => '商品不存在'];
        }

        $price = (int)$target['price'];
        $shopType = (int)$target['shoptype']; // 1 元宝, 2 银两

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($shopType === 1) {
            if ((int)($member['gold'] ?? 0) < $price) {
                return ['status' => -4, 'message' => '元宝不足'];
            }
            MemberHandler::deductCurrency($uid, gold: $price);
        } else {
            if ((int)($member['silver'] ?? 0) < $price) {
                return ['status' => -5, 'message' => '银两不足'];
            }
            MemberHandler::deductCurrency($uid, silver: $price);
        }

        // 发放装备到装备背包
        DB::table('equipment')->insert([
            'uid' => $uid,
            'equipid' => $itemId,
            'name' => $target['name'],
            'type' => $target['type'],
            'level' => 1,
            'quality' => $target['quality'],
            'generalid' => 0,
            'number' => 1,
            'saleprice' => 50 * $target['quality'],
        ]);

        Redis::del('player:' . $uid);
        $freshMember = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $nickname = (string)($freshMember['nickname'] ?? ('主公' . $uid));

        // 记录全服购买日志
        $logs = Redis::getJson('global_shop_logs') ?: [];
        array_unshift($logs, [
            'uid' => $uid,
            'username' => $nickname,
            'itemname' => $target['name'],
            'quality' => $target['quality'],
            'dateline' => '刚刚',
        ]);
        $logs = array_slice($logs, 0, 10);
        Redis::setJson('global_shop_logs', $logs, 86400 * 7);

        return [
            'status' => 1,
            'maxtimes' => 9,
            'gold' => (int)($freshMember['gold'] ?? 0),
            'silver' => (int)($freshMember['silver'] ?? 0),
            'cd' => 0,
            'message' => "成功购买【{$target['name']}】！装备已放入包裹！",
        ];
    }

    /**
     * 购买军令体力: c=shop&m=buy_act
     */
    public static function buy_act(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $costGold = 10;

        if ((int)($member['gold'] ?? 0) < $costGold) {
            return ['status' => -4, 'message' => '元宝不足'];
        }

        MemberHandler::deductCurrency($uid, gold: $costGold);
        MemberHandler::addCurrency($uid, act: 50);

        $freshMember = DB::table('member')->where('uid', $uid)->first();

        return [
            'status' => 1,
            'act' => (int)($freshMember['act'] ?? 150),
            'gold' => (int)($freshMember['gold'] ?? 0),
            'message' => '购买军令成功，军令增加 50 点',
        ];
    }
}
