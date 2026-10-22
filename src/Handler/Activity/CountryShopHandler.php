<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\Pvp\CountryHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class CountryShopHandler
{
    public const array GOODS = [
        1 => ['id' => 1, 'name' => '神品·青龙偃月刀图纸', 'cost_rep' => 500, 'cost_silver' => 20000, 'desc' => '用于在铸造工坊打造绝世神兵'],
        2 => ['id' => 2, 'name' => '神品·连环铠图纸', 'cost_rep' => 400, 'cost_silver' => 15000, 'desc' => '用于打造极品防具'],
        3 => ['id' => 3, 'name' => '万年洗髓古令 x5', 'cost_rep' => 200, 'cost_silver' => 10000, 'desc' => '用于武将洗髓突破'],
        4 => ['id' => 4, 'name' => '天命星魂晶石 x50', 'cost_rep' => 150, 'cost_silver' => 5000, 'desc' => '用于将魂命盘突破'],
    ];

    /**
     * 国家特产商城主界面: c=countryshop&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $rep = (int)($member['reputation'] ?? 0);
        $country = (int)($member['country'] ?? 0);

        return [
            'status' => 1,
            'my_reputation' => $rep,
            'country_name' => CountryHandler::COUNTRIES[$country]['name'] ?? '在野游侠',
            'list' => array_values(self::GOODS),
            'message' => 'ok',
        ];
    }

    /**
     * 兑换国家特产珍宝: c=countryshop&m=buy
     */
    public static function buy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $goodsId = (int)($ctx->input('id', $ctx->input('goods_id', 1)));

        if (!isset(self::GOODS[$goodsId])) {
            return ['status' => 0, 'message' => '商品不存在'];
        }

        $cfg = self::GOODS[$goodsId];
        $costRep = (int)$cfg['cost_rep'];
        $costSilver = (int)$cfg['cost_silver'];

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $curRep = (int)($member['reputation'] ?? 0);

        if ($curRep < $costRep) {
            return ['status' => -1, 'message' => "国家声望不足，兑换需要 {$costRep} 声望"];
        }

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
            return ['status' => -2, 'message' => "银两不足，兑换需要 {$costSilver} 银两"];
        }

        // 扣除声望
        $newRep = $curRep - $costRep;
        DB::table('member')->where('uid', $uid)->update(['reputation' => $newRep]);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'goods_name' => $cfg['name'],
            'reputation' => $newRep,
            'message' => "兑换成功！耗费声望 {$costRep}、银两 {$costSilver}，成功购得【{$cfg['name']}】！",
        ];
    }
}
