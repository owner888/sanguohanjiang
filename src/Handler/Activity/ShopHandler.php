<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\Price;
use Hanjiang\MasterData\Shop;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\Member;
use Hanjiang\Model\StatPay;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 兵符商城与军令采买（移植旧版 controllers/shop.php + models/shop_model.php）
 *
 * 客户端调用：index / get_info / buy（shoppop.js）、buy_act（base.js / pacifypop.js）。
 * 商品取 MasterData Shop（position / equipid / type 1 元宝、2 银币 / price / maxtimes），展示字段取 MasterData Equip 模板。
 * 限购次数是全服每件商品每天 maxtimes 次（Redis shop_item_{装备id}{ymd}，旧版键不带 uid）；每次购买后玩家有 60 秒购买 CD
 * （shop_cd_{uid}）；今日全服最近 5 条购买记录 shop_buy_log_{ymd}。军令采买价格取 MasterData Price.act（第 50 个起 50 元宝），
 * 今日购买次数记 member_field.actnumber / actdateline。
 *
 * 与旧版的差别（多 Worker 安全）：buy / buy_act 在 Member::locked（玩家行锁）里执行；全服限购次数用 INCR 原子累加
 * （超过上限时减回）；先扣货币再发装备（旧版先发装备后扣，扣款不会失败）；购买记录改为 Redis 列表 LPUSH + LTRIM。
 */
final class ShopHandler
{
    /** 每次增加 cd 时间（旧版 $cd_time） */
    private const int CD_TIME = 60;
    /** 限购次数键（旧版 $_shop_item，后接装备 id 与 ymd） */
    private const string ITEM_KEY = 'shop_item_';
    /** 购买 cd 键（旧版 $_shop_cd） */
    private const string CD_KEY = 'shop_cd_';
    /** 购买记录键（旧版 $buy_log，后接 ymd） */
    private const string LOG_KEY = 'shop_buy_log_';
    /** 军令第 50 个起的价格（shop_model->get_act_price） */
    private const int ACT_MAX_PRICE = 50;

    /**
     * 取商店列表: c=shop&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0, 'list' => []];
        $uid = MemberHandler::resolveUid($ctx);
        $now = time();
        // 未装备的装备列表（get_list：按 equipid 索引，顺序同表主键 position）
        $list = [];
        foreach (Shop::ROWS as $row) {
            $list[$row['equipid']] = $row;
        }
        if ($list === []) {
            // 没有找到商品列表
            $return['status'] = -1;
            return $return;
        }
        $nowlist = [];
        $totals = 0;
        foreach ($list as $equipid => $row) {
            $tpl = Equip::ROWS[$equipid] ?? null;
            if ($tpl === null) {
                // 没有找到装备模版数据
                $return['status'] = -2;
                return $return;
            }
            $info = [
                'name' => $tpl['name'],
                'type' => $tpl['type'],
                'quality' => $tpl['quality'],
                'need_viplevel' => self::needVipLevel($tpl['quality'], $tpl['id']),
                // 组合商品条件
                'id' => $row['equipid'],
                'position' => $row['position'],
            ];
            $nowlist[$row['position']] = $info;
            ++$totals;
        }
        ksort($nowlist);
        $first = reset($list);
        $return['info'] = self::info($uid, (int)$first['equipid'], $now);
        $return['status'] = 1;
        $return['list'] = $nowlist;
        $return['log_list'] = self::shopLog($now);
        $return['totals'] = $totals;
        return $return;
    }

    /**
     * 单件装备信息: c=shop&m=get_info
     */
    public static function get_info(Context $ctx): array
    {
        return self::info(MemberHandler::resolveUid($ctx), (int)$ctx->input('id', 0), time());
    }

    /**
     * 购买装备: c=shop&m=buy
     */
    public static function buy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $itemid = (int)$ctx->input('id', 0);
        return Member::locked($uid, static function () use ($uid, $itemid): array {
            $return = ['status' => 0];
            $now = time();
            // 商品基本信息
            $shop = self::shopItem($itemid);
            if ($shop === null) {
                // 装备不存在
                $return['status'] = -1;
                return $return;
            }
            // 取装备模版信息
            $tpl = Equip::ROWS[$itemid] ?? null;
            if ($tpl === null) {
                $return['status'] = -2;
                return $return;
            }
            $member = DB::table('member')->select('uid', 'nickname', 'vip', 'gold', 'silver')->where('uid', $uid)->whereNull('deleted_at')->first();
            if ($member === null) {
                return $return;
            }
            // 判断vip购买等级
            if ((int)$member['vip'] < self::needVipLevel($tpl['quality'], $itemid)) {
                // 需要vip等级不足
                $return['status'] = -8;
                return $return;
            }
            // 判断购买cd是否清除
            $cd = Redis::get(self::CD_KEY . $uid);
            if (is_array($cd) && (int)$cd['dateline'] - $now > 0) {
                // 购买cd未清除
                $return['status'] = -3;
                return $return;
            }
            // 检查金币
            if ($shop['type'] === 1 && (int)$member['gold'] < $shop['price']) {
                $return['status'] = -4;
                return $return;
            }
            // 检查银币
            if ($shop['type'] === 2 && (int)$member['silver'] < $shop['price']) {
                $return['status'] = -5;
                return $return;
            }
            // 全服今日限购次数：先原子 + 1，超过上限再减回（旧版先读后写）
            $key = self::itemKey($itemid, $now);
            $times = (int)Redis::incr($key);
            Redis::expire($key, 86400);
            $maxtimes = max(0, $shop['maxtimes'] - ($times - 1));
            // 检查限购次数
            if ($maxtimes <= 0) {
                Redis::decr($key);
                $return['status'] = -6;
                return $return;
            }
            $return['maxtimes'] = $maxtimes - 1;

            // 减少金币 / 银币
            $paid = $shop['type'] === 1
                ? Member::deductCurrency($uid, gold: $shop['price'])
                : Member::deductCurrency($uid, silver: $shop['price']);
            if (!$paid) {
                Redis::decr($key);
                $return['status'] = $shop['type'] === 1 ? -4 : -5;
                return $return;
            }
            // 发货
            if (Equipment::addFromTemplate($uid, $itemid) === []) {
                $return['status'] = -7;
                return $return;
            }
            $return['status'] = 1;
            $after = DB::table('member')->select('gold', 'silver')->where('uid', $uid)->first();
            if ($shop['type'] === 1) {
                $return['gold'] = (int)$after['gold'];
                // 消费统计记录
                StatPay::record($uid, 'buy_bingfu', $shop['price']);
            } else {
                $return['silver'] = (int)$after['silver'];
            }
            // 添加购买记录
            self::addShopLog((string)$tpl['name'], (int)$tpl['quality'], $member, $now);
            // 存cd
            Redis::set(self::CD_KEY . $uid, ['dateline' => $now + self::CD_TIME], self::CD_TIME);

            $return['cd'] = self::CD_TIME;
            return $return;
        });
    }

    /**
     * 购买军令: c=shop&m=buy_act&t=1（不传 t 只返回下一个军令的价格）
     */
    public static function buy_act(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $type = (int)$ctx->input('t', 0);
        return Member::locked($uid, static function () use ($uid, $type): array {
            $return = [];
            $now = time();
            $field = DB::table('member_field')->select('actnumber', 'actdateline')->where('uid', $uid)->whereNull('deleted_at')->first();
            if ($field === null) {
                $return['status'] = 0;
                return $return;
            }
            if (date('ymd', (int)$field['actdateline']) === date('ymd', $now)) {
                $number = (int)$field['actnumber'];
            } else {
                // 修改数据库时间（update_field_act($field, 1)：今日购买次数归零）
                $number = 0;
                DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['actnumber' => 0, 'actdateline' => $now]);
            }
            // 取军令价格
            $price = self::actPrice($number + 1);
            $return['price'] = $price;
            if ($type === 1) {
                // 减少金币
                if (!Member::deductCurrency($uid, gold: $price)) {
                    $return['status'] = -1;
                    return $return;
                }
                $return['gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
                // 添加军令
                Member::addCurrency($uid, act: 1);
                $return['act'] = (int)DB::table('member')->where('uid', $uid)->value('act');
                // 修改军令购买记录（update_field_act($field)：旧版用本次请求开始时读到的 actnumber + 1，
                // 隔天第一次购买时写回的是昨天的次数 + 1，照搬）
                DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')
                    ->update(['actnumber' => (int)$field['actnumber'] + 1, 'actdateline' => $now]);
                $return['status'] = 1;
                // 下个军令的价格
                $return['price'] = self::actPrice($number + 2);
                // 消费统计记录
                StatPay::record($uid, 'buy_junling', $price);
            }
            return $return;
        });
    }

    /**
     * 单件装备信息（旧版 get_info($itemid)：index 内部调用与接口共用）
     *
     * @return array<string, mixed>
     */
    private static function info(int $uid, int $itemid, int $now): array
    {
        $return = ['status' => 0, 'info' => []];
        // 商品信息
        $shop = self::shopItem($itemid);
        if ($shop === null) {
            $return['status'] = -1;
            return $return;
        }
        // 装备模版信息
        $tpl = Equip::ROWS[$itemid] ?? null;
        if ($tpl === null) {
            $return['status'] = -2;
            return $return;
        }
        $info = $shop;
        $info['shoptype'] = $shop['type'];
        // 模板字段覆盖同名商品字段（type 变成装备类型，商品类型在 shoptype）
        foreach (['name', 'quality', 'type', 'needlevel', 'power', 'description'] as $field) {
            $info[$field] = $tpl[$field];
        }
        // 取剩余购买次数
        $times = (int)(Redis::get(self::itemKey($itemid, $now)) ?? 0);
        $info['nowmaxtimes'] = max(0, $shop['maxtimes'] - $times);
        $time = 0;
        $cd = Redis::get(self::CD_KEY . $uid);
        if (is_array($cd) && (int)$cd['dateline'] - $now > 0) {
            $time = (int)$cd['dateline'] - $now;
        }
        $return['status'] = 1;
        $return['info'] = $info;
        // vip等级购买限制
        $return['need_viplevel'] = self::needVipLevel((int)$info['quality'], $itemid);
        $return['cd'] = $time;
        return $return;
    }

    /**
     * 商品行（shop_model->get_item_info：按 equipid 查）
     *
     * @return array{position: int, equipid: int, type: int, price: int, maxtimes: int}|null
     */
    private static function shopItem(int $equipid): ?array
    {
        foreach (Shop::ROWS as $row) {
            if ($row['equipid'] === $equipid) {
                return $row;
            }
        }
        return null;
    }

    private static function itemKey(int $itemid, int $now): string
    {
        return self::ITEM_KEY . $itemid . date('ymd', $now);
    }

    /**
     * 读取今日购买记录（get_shop_log）
     *
     * @return list<array<string, mixed>>
     */
    private static function shopLog(int $now): array
    {
        $list = Redis::lRange(self::LOG_KEY . date('ymd', $now), 0, 4);
        return array_values(array_filter(array_map(static fn(string $v): mixed => json_decode($v, true), is_array($list) ? $list : []), 'is_array'));
    }

    /**
     * 添加购买记录（add_shop_log：新记录在最前，最多 5 条，保留 1 天）
     *
     * @param array<string, mixed> $member
     */
    private static function addShopLog(string $itemname, int $quality, array $member, int $now): void
    {
        $key = self::LOG_KEY . date('ymd', $now);
        Redis::lPush($key, json_encode([
            'itemname' => $itemname,
            'quality' => $quality,
            'username' => (string)$member['nickname'],
            'uid' => (int)$member['uid'],
            'dateline' => date('H:i:s', $now),
        ], JSON_UNESCAPED_UNICODE));
        Redis::lTrim($key, 0, 4);
        Redis::expire($key, 86400);
    }

    /**
     * 根据购买数量获取军令价格（shop_model->get_act_price）
     */
    private static function actPrice(int $num): int
    {
        if ($num >= 50) {
            // 购买个数大于等于50个，价格都是50金币
            return self::ACT_MAX_PRICE;
        }
        return (int)(Price::ROWS[$num]['act'] ?? 0);
    }

    /**
     * 查询vip购买限制（get_need_vip_level）
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
}
