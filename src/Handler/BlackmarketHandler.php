<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class BlackmarketHandler
{
    /**
     * 黑市固定商品配置池
     */
    public const array GOODS_POOL = [
        1 => ['id' => 1, 'name' => '万年洗髓仙丹 x10', 'cost_type' => 'silver', 'price' => 10000, 'type' => 1, 'desc' => '用于武将洗髓突破'],
        2 => ['id' => 2, 'name' => '天命星魂晶石 x50', 'cost_type' => 'silver', 'price' => 15000, 'type' => 2, 'desc' => '用于星宿命格升级'],
        3 => ['id' => 3, 'name' => '百炼玄铁神兵券 x1', 'cost_type' => 'gold', 'price' => 30, 'type' => 3, 'desc' => '可在铁匠铺打造稀有神兵'],
        4 => ['id' => 4, 'name' => '极品璀璨宝石袋 x1', 'cost_type' => 'gold', 'price' => 50, 'type' => 4, 'desc' => '随机开出高阶红蓝绿宝石'],
    ];

    /**
     * 获取玩家黑市货架状态
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getMarketGoods(int $uid): array
    {
        $cached = Redis::getJson('user_blackmarket:' . $uid);
        if (is_array($cached)) {
            return $cached;
        }

        $goods = [];
        foreach (self::GOODS_POOL as $id => $item) {
            $goods[$id] = array_merge($item, [
                'locked' => 0,
                'bought' => 0,
            ]);
        }
        Redis::setJson('user_blackmarket:' . $uid, $goods, 86400);
        return $goods;
    }

    /**
     * 黑市主界面: c=blackmarket&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $goods = self::getMarketGoods($uid);

        return [
            'status' => 1,
            'list' => array_values($goods),
            'refresh_cost' => 2000,
            'message' => 'ok',
        ];
    }

    /**
     * 购买黑市珍宝: c=blackmarket&m=buy
     */
    public static function buy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $goodsId = (int)($ctx->input('id', 1));

        $goods = self::getMarketGoods($uid);
        if (!isset($goods[$goodsId])) {
            return ['status' => 0, 'message' => '商品不存在'];
        }

        $item = $goods[$goodsId];
        if (!empty($item['bought'])) {
            return ['status' => 0, 'message' => '该绝版商品已被抢购，请刷新货架'];
        }

        $costSilver = ($item['cost_type'] === 'silver') ? (int)$item['price'] : 0;
        $costGold = ($item['cost_type'] === 'gold') ? (int)$item['price'] : 0;

        // 原子安全扣费
        if (!MemberHandler::deductCurrency($uid, silver: $costSilver, gold: $costGold)) {
            return [
                'status' => -1,
                'message' => ($costGold > 0) ? "元宝不足，购买需要 {$costGold} 元宝" : "银两不足，购买需要 {$costSilver} 银两",
            ];
        }

        $goods[$goodsId]['bought'] = 1;
        Redis::setJson('user_blackmarket:' . $uid, $goods, 86400);

        // 如果是高阶黄金道具，全服广播
        if ($costGold >= 50) {
            $member = DB::table('member')->where('uid', $uid)->first();
            $pName = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "西域传宝！主公【{$pName}】于神秘黑市淘得稀世珍宝【{$item['name']}】，引各路诸侯艳羡！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'id' => $goodsId,
            'item_name' => $item['name'],
            'message' => "成功抢购【{$item['name']}】！",
        ];
    }

    /**
     * 锁定/解锁商品: c=blackmarket&m=lock
     */
    public static function lock(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $goodsId = (int)($ctx->input('id', 1));

        $goods = self::getMarketGoods($uid);
        if (!isset($goods[$goodsId])) {
            return ['status' => 0, 'message' => '商品不存在'];
        }

        $newLocked = empty($goods[$goodsId]['locked']) ? 1 : 0;
        $goods[$goodsId]['locked'] = $newLocked;
        Redis::setJson('user_blackmarket:' . $uid, $goods, 86400);

        return [
            'status' => 1,
            'id' => $goodsId,
            'locked' => $newLocked,
            'message' => $newLocked ? '商品已锁定，刷新时将受保护保留' : '商品已解除锁定',
        ];
    }

    /**
     * 刷新黑市珍宝货架: c=blackmarket&m=refresh
     */
    public static function refresh(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $costSilver = 2000;

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
            return ['status' => -1, 'message' => "银两不足，刷新货架需要 {$costSilver} 银两"];
        }

        $goods = self::getMarketGoods($uid);
        foreach ($goods as $id => $item) {
            // 未锁定的商品重置为可购买状态并随机轮换
            if (empty($item['locked'])) {
                $goods[$id]['bought'] = 0;
            }
        }
        Redis::setJson('user_blackmarket:' . $uid, $goods, 86400);

        return [
            'status' => 1,
            'list' => array_values($goods),
            'message' => '黑市货架刷新完成，西域奇珍已重新上架！',
        ];
    }
}
