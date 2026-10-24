<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 国家税金商城与军饷兑换处理器 (DDD Activity Domain)
 */
final class CountryTaxesShopHandler
{
    /**
     * 国家税金商城商品配置
     * type: 1 银两, 2 突飞卡, 3 军令, 4 宝石, 7 将魂, 8 玉石
     */
    public const array GOODS = [
        1 => ['id' => 1, 'type' => 1, 'value' => 50000, 'taxes' => 100, 'openlevel' => 1, 'num' => 10],
        2 => ['id' => 2, 'type' => 2, 'value' => 20, 'taxes' => 200, 'openlevel' => 1, 'num' => 10],
        3 => ['id' => 3, 'type' => 3, 'value' => 15, 'taxes' => 300, 'openlevel' => 1, 'num' => 5],
        4 => ['id' => 4, 'type' => 4, 'value' => 5, 'taxes' => 500, 'openlevel' => 2, 'num' => 5],
        5 => ['id' => 5, 'type' => 7, 'value' => 100, 'taxes' => 800, 'openlevel' => 3, 'num' => 5],
        6 => ['id' => 6, 'type' => 8, 'value' => 50, 'taxes' => 1000, 'openlevel' => 4, 'num' => 3],
    ];

    /**
     * 国家税金商城主界面: c=country_taxes_shop&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $countryId = (int)($member['country'] ?? 1);
        $countryLevel = ($countryId > 0) ? 5 : 1;

        $taxesData = self::getUserTaxesData($uid);
        $todayStr = date('Ymd');

        $goodsList = [];
        foreach (self::GOODS as $id => $item) {
            $boughtTimes = (int)(Redis::get("taxes_buy:{$uid}:{$id}:{$todayStr}") ?? 0);
            $remainNum = max(0, (int)$item['num'] - $boughtTimes);
            $isOpen = ($countryLevel >= (int)$item['openlevel']) ? 1 : 0;

            $goodsList[] = [
                'id' => $id,
                'type' => (int)$item['type'],
                'value' => (int)$item['value'],
                'taxes' => (int)$item['taxes'],
                'openlevel' => (int)$item['openlevel'],
                'is_open' => $isOpen,
                'limit' => (int)$item['num'],
                'num' => $remainNum,
                'times' => $boughtTimes,
            ];
        }

        return [
            'status' => 1,
            'shop_list' => [
                'status' => 1,
                'list' => $goodsList,
            ],
            'member_info' => [
                'info' => [
                    'taxes' => (int)($taxesData['taxes'] ?? 5000),
                    'contribute' => (int)($taxesData['contribute'] ?? 2000),
                    'general' => (int)($taxesData['general'] ?? 10),
                ],
            ],
            'msg' => 'ok',
        ];
    }

    /**
     * 购买税金商城商品: c=country_taxes_shop&m=buy
     */
    public static function buy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)($ctx->input('id', 0));

        if (!isset(self::GOODS[$id])) {
            return ['status' => 2, 'msg' => '商品不存在'];
        }

        $item = self::GOODS[$id];
        $costTaxes = (int)$item['taxes'];
        $taxesData = self::getUserTaxesData($uid);

        if ((int)($taxesData['taxes'] ?? 0) < $costTaxes) {
            return ['status' => -2, 'msg' => '国家税金不足，无法兑换'];
        }

        $todayStr = date('Ymd');
        $boughtTimes = (int)(Redis::get("taxes_buy:{$uid}:{$id}:{$todayStr}") ?? 0);
        if ($boughtTimes >= (int)$item['num']) {
            return ['status' => -3, 'msg' => '今日兑换次数已达上限'];
        }

        // 扣除税金并累加购买次数
        $taxesData['taxes'] = (int)$taxesData['taxes'] - $costTaxes;
        Redis::setJson("user_taxes:{$uid}", $taxesData, 86400 * 30);
        Redis::set("taxes_buy:{$uid}:{$id}:{$todayStr}", (string)($boughtTimes + 1), 86400 * 2);

        // 发放对应资源
        $type = (int)$item['type'];
        $val = (int)$item['value'];
        if ($type === 1) {
            MemberHandler::addCurrency($uid, silver: $val);
        } elseif ($type === 2) {
            $field = DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->first();
            if ($field !== null) {
                DB::table('member_field')->where('uid', $uid)->update([
                    'freeleap' => (int)($field['freeleap'] ?? 0) + $val,
                ]);
            }
        } elseif ($type === 3) {
            MemberHandler::addCurrency($uid, act: $val);
        } elseif ($type === 7) {
            $member = DB::table('member')->where('uid', $uid)->first();
            DB::table('member')->where('uid', $uid)->update([
                'soul' => (int)($member['soul'] ?? 0) + $val,
            ]);
        }

        Redis::del('player:' . $uid);

        $remainNum = (int)$item['num'] - ($boughtTimes + 1);

        return [
            'status' => 1,
            'taxes' => $taxesData['taxes'],
            'general' => $taxesData['general'],
            'item' => array_merge($item, [
                'times' => $boughtTimes + 1,
                'num' => max(0, $remainNum),
            ]),
            'msg' => '兑换成功！',
        ];
    }

    /**
     * 获取玩家国家税金与军饷资产
     *
     * @return array<string, int>
     */
    private static function getUserTaxesData(int $uid): array
    {
        $key = "user_taxes:{$uid}";
        $data = Redis::getJson($key);

        if (!is_array($data)) {
            $data = [
                'taxes' => 5000,
                'contribute' => 2000,
                'general' => 10,
            ];
            Redis::setJson($key, $data, 86400 * 30);
        }

        return $data;
    }
}
