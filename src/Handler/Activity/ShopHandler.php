<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\Shop;
use Hanjiang\Model\Equipment;
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
     * 商城商品（旧版 controllers/shop.php：商品取 MasterData Shop，展示字段取 MasterData Equip 模板）
     * 按 position 排序；id 即装备模板 id（旧版 shop_model::get_item_info 按 equipid 查）。
     *
     * @return array<int, array<string, mixed>>
     */
    public static function shopItems(): array
    {
        $items = [];
        foreach (Shop::ROWS as $pos => $row) {
            $tpl = Equip::ROWS[$row['equipid']];
            $items[$pos] = [
                'id' => $tpl['id'],
                'equipid' => $tpl['id'],
                'name' => $tpl['name'],
                'type' => $tpl['type'],
                'quality' => $tpl['quality'],
                'shoptype' => $row['type'], // 1 元宝, 2 银两
                'price' => $row['price'],
                'maxtimes' => $row['maxtimes'],
                'needlevel' => $tpl['needlevel'],
                'need_viplevel' => self::needVipLevel($tpl['quality'], $tpl['id']),
                'power' => $tpl['power'],
                'description' => $tpl['description'],
                'position' => $row['position'],
            ];
        }
        ksort($items);
        return $items;
    }

    /**
     * 购买所需 VIP 等级（旧版 controllers/shop.php:get_need_vip_level）
     */
    private static function needVipLevel(int $quality, int $id): int
    {
        return match (true) {
            $quality === 2 => 1,
            $quality === 3 => 2,
            $quality === 4 => 3,
            $quality === 5 && $id === 15 => 4,
            $quality === 5 && $id === 53 => 5,
            $quality === 5 && $id === 54 => 6,
            $quality === 5 && $id === 55 => 7,
            $quality === 5 && $id === 56 => 8,
            default => 0,
        };
    }

    /**
     * 商城列表: c=shop&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);

        $nowList = [];
        $totals = 0;
        foreach (self::shopItems() as $pos => $item) {
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
        $firstItem = self::shopItems()[1];
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
        $itemId = (int)($ctx->input('id', 0));

        $target = null;
        foreach (self::shopItems() as $item) {
            if ($item['id'] === $itemId) {
                $target = $item;
                break;
            }
        }

        if ($target === null) {
            $target = self::shopItems()[1];
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
        $itemId = (int)($ctx->input('id', 0));

        $target = null;
        foreach (self::shopItems() as $item) {
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

        // 发货：旧版 equipment_model::add_item(模板)，入库走唯一入口
        Equipment::addFromTemplate($uid, $itemId);

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
