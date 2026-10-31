<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\Pvp\CountryHandler;
use Hanjiang\MasterData\CountryTaxesShop;
use Hanjiang\Model\CountryTaxes;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberIntegral;
use Hanjiang\Model\SendReward;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 国家税金商城（移植旧版 controllers/country_taxes_shop.php + models/country_taxes_shop_model.php、
 * member_country_taxes_model.php、daytime_sys_model.php；奖励见 Model\SendReward）
 *
 * 客户端（countrymallpop.js）调用：index / buy(id)。商品取 MasterData CountryTaxesShop（type 奖励类型、value 数量、
 * num 每日限购（0 不限）、taxes 税金价格、openlevel 国家等级），按 openlevel、type 倒序、value、id 排序。
 * 税金见 Model\CountryTaxes（国家贡献 × 0.1 累计）。国家 30 级开放列表（没有国家 2、不足 30 级 3、没有商品 4）。
 * 每日限购次数是整个国家共用的（旧版 daytime_sys_model 的 uid 为 country_{国家id}，每天第一次读取时清空）：
 * 新版存 Redis 哈希 country_taxes_times_{国家id}_{Ymd}（商品 id => 今日国家已购次数），HINCRBY 原子累加，超过上限时减回。
 *
 * 与旧版的差别：buy 在 Member::locked（玩家行锁）里执行，税金按条件 UPDATE 扣除（见 Model\CountryTaxes）。
 */
final class CountryTaxesShopHandler
{
    /** 国家税金商城开放等级（country_taxes_shop_model->get_shop_list） */
    private const int OPEN_LEVEL = 30;
    /** 国家每日购买次数键（旧版 day_time 表 uid = country_{国家id} 的 _country_taxes_times） */
    private const string TIMES_KEY = 'country_taxes_times_';

    /** 语言包（country_taxes_shop_lang） */
    private const array LANG = [
        'id_not' => 'id 不合法',
        'not_country' => '未加入任何国家',
        'country_level_reached' => '国家等级不足30级',
        'taxes_qnadequate' => '税金不足',
        'times_error' => '购买次数不足',
        'not_exist' => '不存在该物品',
        'not_data' => '没有数据',
    ];

    /**
     * 税金商城列表: c=country_taxes_shop&m=index
     *
     * 1 成功；2 未加入任何国家；3 国家等级未达到；4 商城列表为空（策划没配表）
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = self::member($uid);
        $country = (int)($member['country'] ?? 0);
        $now = time();
        $list = self::getlist($country, $now);
        // 获取用户税金数据
        $info = CountryTaxes::getinfo($uid, $country, $now);
        $return = [];
        $return['status'] = $list['status'];
        $return['shop_list'] = $list;
        $return['member_info'] = $info;
        if (isset($list['msg'])) {
            $return['msg'] = $list['msg'];
        }
        return $return;
    }

    /**
     * 购买商品: c=country_taxes_shop&m=buy&id=商品id
     *
     * -1 id 参数不合法；-2 税金不足；-3 购买次数超出上限；-4 国家等级未达到；1 成功；2 此商品不存在 / 未加入任何国家
     */
    public static function buy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $rawId = $ctx->input('id', '');
        return Member::locked($uid, static function () use ($uid, $rawId): array {
            $return = [];
            $now = time();
            // 如果不合法 进行提示
            if ($rawId === '' || $rawId === null) {
                $return['status'] = -1;
                $return['msg'] = self::LANG['id_not'];
                return $return;
            }
            // 拿到商品的信息
            $info = CountryTaxesShop::ROWS[(int)$rawId] ?? null;
            if ($info === null) {
                $return['status'] = 2;
                $return['msg'] = self::LANG['not_exist'];
                return $return;
            }
            $info['limit'] = $info['num'];
            $member = self::member($uid);
            // 判断是否加入国家
            if ($member === null || (int)$member['country'] === 0) {
                $return['status'] = 2;
                $return['msg'] = self::LANG['not_country'];
                return $return;
            }
            $country = (int)$member['country'];
            // 判断国家等级是否达到
            $countryInfo = CountryHandler::countryInfo($country);
            if ((int)($countryInfo['level'] ?? 0) < $info['openlevel']) {
                $return['status'] = -4;
                $return['msg'] = self::LANG['country_level_reached'];
                return $return;
            }
            // 判断用户的税金够不够
            $memberInfo = CountryTaxes::getinfo($uid, $country, $now);
            if ((int)($memberInfo['info']['taxes'] ?? 0) < $info['taxes']) {
                $return['status'] = -2;
                $return['msg'] = self::LANG['taxes_qnadequate'];
                return $return;
            }
            // 国家今日购买次数：先原子 + 1，超过上限再减回（旧版先读后写）
            $key = self::timesKey($country, $now);
            $times = (int)Redis::hIncrBy($key, (string)$info['id'], 1);
            Redis::expire($key, 86400 * 2);
            if ($info['limit'] !== 0 && $times > $info['limit']) {
                Redis::hIncrBy($key, (string)$info['id'], -1);
                $return['status'] = -3;
                $return['msg'] = self::LANG['times_error'];
                return $return;
            }
            // 购买（country_taxes_shop_model->buy：扣税金、发奖励、取将军令）
            $taxes = CountryTaxes::sub($uid, $country, $info['taxes'], $now);
            if ($taxes === null) {
                Redis::hIncrBy($key, (string)$info['id'], -1);
                $return['status'] = -2;
                $return['msg'] = self::LANG['taxes_qnadequate'];
                return $return;
            }
            $return['taxes'] = $taxes;
            $return['reward_info'] = SendReward::send($uid, $info['type'], $info['value'], 'country_taxes_shop', 'buy', $now);
            $return['general'] = (int)(MemberIntegral::get($uid)['general'] ?? 0);
            if ($info['num'] !== 0) {
                $info['times'] = $times;
                $info['num'] -= $times;
                $return['item'] = $info;
            }
            $return['status'] = 1;
            return $return;
        });
    }

    /**
     * 商城列表 + 国家今日已购次数（getlist / country_taxes_shop_model->get_shop_list）
     *
     * @return array<string, mixed>
     */
    private static function getlist(int $country, int $now): array
    {
        // 未加入任何国家时 退出
        if ($country === 0) {
            return ['msg' => self::LANG['not_country'], 'status' => 2];
        }
        // 得到当前国家等级
        $level = (int)(CountryHandler::countryInfo($country)['level'] ?? 0);
        if ($level < self::OPEN_LEVEL) {
            return ['msg' => self::LANG['country_level_reached'], 'status' => 3];
        }
        $rows = array_values(CountryTaxesShop::ROWS);
        if ($rows === []) {
            return ['msg' => self::LANG['not_data'], 'status' => 4];
        }
        // order by openlevel asc, type desc, value asc, id asc
        usort($rows, static fn(array $a, array $b): int => [$a['openlevel'], $b['type'], $a['value'], $a['id']] <=> [$b['openlevel'], $a['type'], $b['value'], $b['id']]);
        $times = self::times($country, $now);
        $list = [];
        foreach ($rows as $k => $v) {
            $v['is_open'] = $v['openlevel'] <= $level ? 1 : 0;
            $v['limit'] = $v['num'];
            $bought = $v['num'] === 0 ? 0 : (int)($times[$v['id']] ?? 0);
            $v['times'] = $bought;
            $v['num'] = $v['num'] - $bought;
            $list[$k] = $v;
        }
        return ['status' => 1, 'list' => $list];
    }

    /**
     * 国家今日已购次数（商品 id => 次数）
     *
     * @return array<int, int>
     */
    private static function times(int $country, int $now): array
    {
        $raw = Redis::hGetAll(self::timesKey($country, $now));
        $times = [];
        foreach (is_array($raw) ? $raw : [] as $id => $num) {
            $times[(int)$id] = (int)$num;
        }
        return $times;
    }

    private static function timesKey(int $country, int $now): string
    {
        return self::TIMES_KEY . $country . '_' . date('Ymd', $now);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return DB::table('member')->select('uid', 'nickname', 'level', 'country')->where('uid', $uid)->whereNull('deleted_at')->first();
    }
}
