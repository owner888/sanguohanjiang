<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

/**
 * 兵符商城、军令采买与全服消费记录处理器 (DDD Activity Domain)
 */
final class ShopHandler
{
    /**
     * 六大绝品兵符字典 (type: 5 兵符/通用, 增加生命属性)
     */
    public const array SHOP_ITEMS = [
        1 => [
            'id' => 31,
            'equipid' => 31,
            'name' => '破军兵符',
            'type' => 5, // 5: 通用职业，生命属性
            'quality' => 3,
            'shoptype' => 1, // 1 元宝, 2 银两
            'price' => 50,
            'maxtimes' => 10,
            'needlevel' => 1,
            'need_viplevel' => 0,
            'power' => 500,
            'description' => '汉室精铸破军兵符，军威浩荡，大幅增强全军生命上限',
            'position' => 1,
        ],
        2 => [
            'id' => 32,
            'equipid' => 32,
            'name' => '神射兵符',
            'type' => 5,
            'quality' => 3,
            'shoptype' => 1,
            'price' => 80,
            'maxtimes' => 10,
            'needlevel' => 10,
            'need_viplevel' => 0,
            'power' => 800,
            'description' => '百步穿杨神射兵符，固本培元，大幅增强全军生命上限',
            'position' => 2,
        ],
        3 => [
            'id' => 33,
            'equipid' => 33,
            'name' => '重甲兵符',
            'type' => 5,
            'quality' => 3,
            'shoptype' => 1,
            'price' => 120,
            'maxtimes' => 10,
            'needlevel' => 20,
            'need_viplevel' => 0,
            'power' => 1200,
            'description' => '坚如磐石重甲兵符，金戈铁马，大幅增强全军生命上限',
            'position' => 3,
        ],
        4 => [
            'id' => 34,
            'equipid' => 34,
            'name' => '疾风兵符',
            'type' => 5,
            'quality' => 4,
            'shoptype' => 1,
            'price' => 200,
            'maxtimes' => 5,
            'needlevel' => 30,
            'need_viplevel' => 1,
            'power' => 2000,
            'description' => '御风逐电疾风兵符，身轻如燕，大幅增强全军生命上限',
            'position' => 4,
        ],
        5 => [
            'id' => 35,
            'equipid' => 35,
            'name' => '神算兵符',
            'type' => 5,
            'quality' => 4,
            'shoptype' => 1,
            'price' => 350,
            'maxtimes' => 5,
            'needlevel' => 40,
            'need_viplevel' => 2,
            'power' => 3500,
            'description' => '料敌先机神算兵符，运筹帷幄，大幅增强全军生命上限',
            'position' => 5,
        ],
        6 => [
            'id' => 36,
            'equipid' => 36,
            'name' => '鬼谋兵符',
            'type' => 5,
            'quality' => 5,
            'shoptype' => 1,
            'price' => 600,
            'maxtimes' => 3,
            'needlevel' => 50,
            'need_viplevel' => 4,
            'power' => 6000,
            'description' => '神鬼莫测天罡兵符，通天彻地，极大幅度增强全军生命上限',
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
                'equipid' => (int)$item['id'],
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

        // 默认选中的第一件商品
        $firstItem = self::SHOP_ITEMS[1];
        $info = [
            'info' => array_merge($firstItem, [
                'equipid' => (int)$firstItem['id'],
                'nowmaxtimes' => (int)$firstItem['maxtimes'],
            ]),
            'need_viplevel' => (int)$firstItem['need_viplevel'],
            'cd' => 0,
        ];

        $logs = Redis::getJson('global_shop_logs') ?: [
            [
                'uid' => 8888,
                'username' => '常山赵子龙',
                'itemname' => '神射兵符',
                'quality' => 3,
                'dateline' => '刚刚',
            ],
            [
                'uid' => 8889,
                'username' => '关云长',
                'itemname' => '破军兵符',
                'quality' => 3,
                'dateline' => '2分钟前',
            ],
        ];

        return [
            'status' => 1,
            'totals' => $totals,
            'list' => $nowList,
            'info' => $info,
            'log_list' => array_slice(array_values($logs), 0, 4), // 限制最多 4 条避免溢出
            'message' => 'ok',
        ];
    }

    /**
     * 查询单件商品详情: c=shop&m=get_info
     */
    public static function get_info(Context $ctx): array
    {
        $itemId = (int)($ctx->input('id', 31));

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
                'equipid' => (int)$target['id'],
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
        $itemId = (int)($ctx->input('id', 31));

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

        // 发放兵符到装备背包
        DB::table('equipment')->insert([
            'uid' => $uid,
            'equipid' => $itemId,
            'name' => $target['name'],
            'type' => 4, // 兵符装备类型 4
            'level' => 1,
            'quality' => $target['quality'],
            'generalid' => 0,
            'number' => 1,
            'saleprice' => 100 * $target['quality'],
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
        $logs = array_slice($logs, 0, 4);
        Redis::setJson('global_shop_logs', $logs, 86400 * 7);

        return [
            'status' => 1,
            'maxtimes' => 9,
            'gold' => (int)($freshMember['gold'] ?? 0),
            'silver' => (int)($freshMember['silver'] ?? 0),
            'cd' => 0,
            'message' => "成功购买【{$target['name']}】！兵符已收入包裹！",
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
