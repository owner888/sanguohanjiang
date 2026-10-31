<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\Pvp\CountryHandler;
use Hanjiang\MasterData\CountryShop;
use Hanjiang\MasterData\Gem;
use Hanjiang\MasterData\TalentScroll;
use Hanjiang\Model\CountryDebris;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\Member;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\Refine;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 国家神殿 / 国家商店（移植旧版 controllers/countryshop.php + models/countryshop_model.php）
 *
 * 客户端（countrytreasurepop.js）调用：index(tab) / buy(id) / red_item_list / sale_red_item(id)。
 * 国家等级 50 级开启（没有国家 status 2，国家不足 50 级 status 3）。商品取 MasterData CountryShop：tab 1 天赋卷轴
 * （type 1 ~ 5，value1 卷轴品级）、tab 2 宝石（type 6 ~ 10 → 宝石模板 1 ~ 5，value1 宝石等级），value2 为数量；
 * sale_type 1 花元宝、2 花神器碎片（member_country.debris，见 Model\CountryDebris）。
 * 红装回收：未穿戴的红色（quality 6）武器 / 防具 / 饰品（type <= 5）按 100 × 碎片数（整件按 20 片）× 部位系数换神器碎片，
 * 主公 140 级以上回收前卸下镶嵌的宝石。
 *
 * 与旧版的差别：buy / sale_red_item 在 Member::locked（玩家行锁）里执行（旧版先读后扣，并发可超扣碎片 / 重复回收）。
 */
final class CountryShopHandler
{
    /** 国家商店开启等级（旧版 $_level，判断写死 50） */
    private const int OPEN_LEVEL = 50;
    /** 1个红装碎片的价格（旧版 $_sale_num） */
    private const int SALE_NUM = 100;
    /** 主公卸下宝石的等级 */
    private const int GEM_LEVEL = 140;
    /** 商品类型 6 ~ 10 对应的宝石模板 id */
    private const array GEM_ID = [6 => 1, 7 => 2, 8 => 3, 9 => 4, 10 => 5];
    /** 红装出售时的碎片系数（_get_coefficient_type） */
    private const array COEFFICIENT = [1 => 2, 2 => 2, 3 => 1, 4 => 1, 5 => 2];

    /**
     * 查询商品列表: c=countryshop&m=index&tab=1|2
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        $gate = self::gate($member);
        if ($gate !== 0) {
            $return['status'] = $gate;
            return $return;
        }
        // 1卷轴神殿，2宝石神殿
        $tab = (int)$ctx->input('tab', 0);
        $tab = $tab > 0 ? $tab : 1;
        $tab = $tab > 2 ? 2 : $tab;

        // 查询商品列表
        $list = array_values(array_filter(CountryShop::ROWS, static fn(array $v): bool => $v['tab'] === $tab));
        if ($list === []) {
            $return['status'] = -1;
            return $return;
        }
        foreach ($list as $key => $val) {
            if ($val['type'] <= 5) {
                // 获取卷轴名称
                $list[$key]['type'] = 1;
                $list[$key]['itemname'] = Equipment::scrollName($val['type']);
                $list[$key]['itemtype'] = $val['type'];
                $list[$key]['itemlevel'] = $val['value1'];
                $list[$key]['quality'] = $val['value1'];
            } else {
                // 获取宝石名称
                $gem = Gem::ROWS[self::GEM_ID[$val['type']] ?? 0] ?? null;
                $list[$key]['type'] = 2;
                $list[$key]['itemname'] = $gem['name'] ?? null;
                $list[$key]['itemtype'] = $gem['type'] ?? null;
                $list[$key]['itemlevel'] = $val['value1'];
                $list[$key]['quality'] = (int)ceil($val['value1'] / 5);
            }
            $list[$key]['number'] = $val['value2'];
            // 删除不必要字段
            unset($list[$key]['value1'], $list[$key]['value2']);
        }

        // 成功返回
        $return['status'] = 1;
        $return['list'] = $list;
        $return['debris'] = CountryDebris::get((int)$member['uid']);
        return $return;
    }

    /**
     * 购买商品: c=countryshop&m=buy&id=商品id
     */
    public static function buy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        return Member::locked($uid, static function () use ($uid, $id): array {
            $return = ['status' => 0];
            $now = time();
            $member = self::member($uid);
            $gate = self::gate($member);
            if ($gate !== 0) {
                $return['status'] = $gate;
                return $return;
            }
            // 要购买的道具id
            if (!$id) {
                $return['status'] = -1;
                return $return;
            }
            // 查询道具信息
            $item = CountryShop::ROWS[$id] ?? null;
            if ($item === null) {
                $return['status'] = -2;
                return $return;
            }
            // 查询自己的国家信息
            $allDebris = CountryDebris::get($uid);
            if ($item['sale_type'] === 1) {
                // 元宝
                if ((int)$member['gold'] < $item['price'] || !Member::deductCurrency($uid, gold: $item['price'])) {
                    $return['status'] = -3;
                    return $return;
                }
            } else {
                // 神器碎片
                if ($allDebris < $item['price']) {
                    $return['status'] = -4;
                    return $return;
                }
                // 扣除神器碎片
                CountryDebris::sub($uid, $item['price'], 'countryshop', 'buy', $now);
                $allDebris -= $item['price'];
            }

            // 添加道具
            $name = '';
            $num = $item['value2'];
            $itemtype = 0;
            if ($item['type'] <= 5) {
                // 查询卷轴id（get_scroll_by_type）后添加卷轴
                $scrollId = self::scrollId($item['type'], $item['value1']);
                $result = Equipment::addTalentScroll($uid, $scrollId, 0, $item['value2'], 'countryshop', 'buy', $now);
                $name = $result['info']['name'] ?? null;
                $quality = $item['value1'];
                $type = 1;
            } else {
                // 添加宝石
                $gemId = self::GEM_ID[$item['type']];
                $result = OwnGem::addGem($uid, $gemId, $item['value1'], $item['value2'], $now);
                // 修改宝石格子占用数
                OwnGem::addGemuse($uid, $item['value2']);
                $name = $result['name'] ?? null;
                $quality = (int)ceil($item['value1'] / 4);
                $type = 2;
                $itemtype = $gemId;
            }

            // 成功返回
            $return['status'] = 1;
            $return['type'] = $type;
            $return['name'] = $name;
            $return['itemtype'] = $itemtype;
            $return['quality'] = $quality;
            $return['level'] = $item['value1'];
            $return['num'] = $num;
            $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
            $return['all_debris'] = $allDebris;
            return $return;
        });
    }

    /**
     * 查询可出售的红装: c=countryshop&m=red_item_list
     */
    public static function red_item_list(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        $gate = self::gate($member);
        if ($gate !== 0) {
            $return['status'] = $gate;
            return $return;
        }
        // get_red_item：未穿戴的红色武器 / 防具 / 饰品，碎片少的在前
        $items = DB::table('equipment')->where('uid', (int)$member['uid'])->whereNull('deleted_at')
            ->where('type', '<=', 5)->where('quality', 6)->where('generalid', 0)
            ->orderBy('chip', 'ASC')->orderBy('type', 'ASC')->get();
        $list = [];
        foreach ($items as $key => $val) {
            foreach (['id', 'equipid', 'name', 'type', 'quality', 'level', 'chip', 'hammer'] as $field) {
                $list[$key][$field] = is_numeric($val[$field]) ? (int)$val[$field] : $val[$field];
            }
            // 计算售价
            $saleprice = self::salePrice((int)$val['chip'], (int)$val['type']);
            $list[$key]['full_num'] = (int)$val['chip'] > 0 ? $val['chip'] . '/20' : '';
            $list[$key]['saleprice'] = $saleprice;
            // 精炼等级加成（锤子类型）
            $hammer = (int)$val['hammer'];
            $list[$key]['hammer_quality'] = 0;
            if ($hammer > 0) {
                foreach (Refine::promoteList([$hammer]) as $refine) {
                    $list[$key]['hammer_quality'] = (int)$refine['type'];
                }
            }
        }

        // 成功返回
        $return['status'] = 1;
        $return['list'] = $list;
        return $return;
    }

    /**
     * 出售红装: c=countryshop&m=sale_red_item&id=装备id
     */
    public static function sale_red_item(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        return Member::locked($uid, static function () use ($uid, $id): array {
            $return = ['status' => 0];
            $now = time();
            $member = self::member($uid);
            $gate = self::gate($member);
            if ($gate !== 0) {
                $return['status'] = $gate;
                return $return;
            }
            // 要删除的红装道具id
            if (!$id) {
                $return['status'] = -1;
                return $return;
            }
            // 查询道具信息
            $item = DB::table('equipment')->where('id', $id)->whereNull('deleted_at')->first();
            if ($item === null || (int)$item['uid'] !== $uid) {
                $return['status'] = -2;
                return $return;
            }
            // 判断是否是可出售的装备
            if ((int)$item['type'] > 5 || (int)$item['quality'] !== 6 || (int)$item['generalid'] > 0) {
                $return['status'] = -3;
                return $return;
            }
            // 装备出售价格
            $saleprice = self::salePrice((int)$item['chip'], (int)$item['type']);

            // 宝石卸下
            if ((int)$member['level'] >= self::GEM_LEVEL) {
                // 返回卸下的宝石个数，修改宝石格子占用数
                $packNum = OwnGem::packOffByEid($uid, $id);
                OwnGem::addGemuse($uid, $packNum);
            }

            // 移除装备（sale_item($id, 0, 1)：出售位置 1 红装回收）
            $result = DB::table('equipment')->where('id', $id)->where('uid', $uid)->whereNull('deleted_at')
                ->update(['deleted_at' => date('Y-m-d H:i:s', $now), 'sale_site' => 1]);
            if ($result > 0) {
                $return['status'] = 1;
                // 添加神器碎片
                $debris = CountryDebris::add($uid, $saleprice, 'countryshop', 'sale_red_item', $now);
                $return['add_debris'] = $saleprice;
                $return['all_debris'] = $debris;
            }
            return $return;
        });
    }

    /**
     * 国家与国家等级判断：没有国家 2，国家不足 50 级 3，通过 0
     *
     * @param array<string, mixed>|null $member
     */
    private static function gate(?array $member): int
    {
        if ($member === null || (int)$member['country'] === 0) {
            return 2;
        }
        $country = CountryHandler::countryInfo((int)$member['country']);
        if ((int)($country['level'] ?? 0) < self::OPEN_LEVEL) {
            return 3;
        }
        return 0;
    }

    /**
     * 红装售价：碎片 100 × 片数（整件按 20 片）× 部位系数
     */
    private static function salePrice(int $chip, int $type): int
    {
        $saleprice = $chip > 0 ? self::SALE_NUM * $chip : self::SALE_NUM * 20;
        return (self::COEFFICIENT[$type] ?? 1) * $saleprice;
    }

    /**
     * 天赋卷轴模板 id（equipment_model->get_scroll_by_type：按类型与品级查 talent_scroll）
     */
    private static function scrollId(int $type, int $quality): int
    {
        foreach (TalentScroll::ROWS as $row) {
            if ((int)$row['type'] === $type && (int)$row['quality'] === $quality) {
                return (int)$row['id'];
            }
        }
        return 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return DB::table('member')->select('uid', 'nickname', 'level', 'vip', 'gold', 'country')->where('uid', $uid)->whereNull('deleted_at')->first();
    }
}
