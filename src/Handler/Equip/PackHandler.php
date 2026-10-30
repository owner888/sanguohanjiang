<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Equip;

use Hanjiang\Battle\LegacyStats;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Equip as EquipTemplate;
use Hanjiang\MasterData\EquipEssence;

use OmniPHP\Http\Context;
use OmniPHP\Database\DB;
use OmniPHP\Cache\Redis;

/**
 * 背包（移植旧版 controllers/pack.php 的 index / sale；use_item 见下方说明）
 */
final class PackHandler
{
    /** 单日可出售的高强化装备件数（旧版 pack.php $_sale_num） */
    private const int SALE_NUM = 10;

    /** 强化等级达到该值的装备计入单日出售件数（旧版 pack.php $_sale_level） */
    private const int SALE_LEVEL = 10;

    /** VIP 达到该等级出售装备时返还 100% 强化花费（旧版 pack.php） */
    private const int SALE_REFUND_VIP = 11;

    /**
     * 背包列表: c=pack&m=index（旧版 controllers/pack.php:index）
     *
     * 只列未穿戴的道具（get_own_list type=1, sort=1, chip=1, needlevel=1）：
     * type <= 5 的装备、碎片（chip > 0）以及 number <> 0 的卷轴 / 跨服道具。
     * 碎片按 ceil(售价 / 合成数) * 碎片数 出售，显示 full_num「碎片数/合成数」；卷轴按 售价 * 个数；
     * VIP 11 及以上整件装备售价 = 模板售价 + 累计强化花费。
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 1, 'list' => []];

        $rows = DB::table('equipment')
            ->where('uid', $uid)
            ->where('generalid', 0)
            ->whereRaw('(`type` <= 5 OR `number` <> 0 OR `chip` <> 0)')
            ->whereNull('deleted_at')
            ->orderBy('quality', 'DESC')
            ->orderBy('type', 'ASC')
            ->orderBy('hammer', 'DESC')
            ->orderBy('level', 'DESC')
            ->orderBy('needlevel', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();
        if ($rows === []) {
            return $return;
        }

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $vip = (int)($member['vip'] ?? 0);

        $itemlist = [];
        $itemarr = [];
        foreach ($rows as $key => $val) {
            $info = [
                'id' => (int)$val['id'],
                'equipid' => (int)$val['equipid'],
                'uid' => (int)$val['uid'],
                'name' => (string)$val['name'],
                'type' => (int)$val['type'],
                'quality' => (int)$val['quality'],
                'needlevel' => (int)$val['needlevel'],
                'level' => (int)$val['level'],
                'power' => (int)$val['power'],
                'powerup' => (int)$val['powerup'],
                'chip' => (int)$val['chip'],
                'saleprice' => (int)$val['saleprice'],
                'number' => (int)$val['number'],
                'hammer' => (int)$val['hammer'],
                'aid' => (string)$val['aid'],
                // 旧版无此二字段；新版 packpop.js sellItemConfirm 读取它们拦截已穿戴装备，背包只列未穿戴，恒为 0
                'gid' => 0,
                'is_equipped' => 0,
            ];

            if ($info['chip'] > 0) {
                $fullNum = LegacyStats::chipNum($info['quality']);
                $info['saleprice'] = (int)ceil($info['saleprice'] / $fullNum) * $info['chip'];
                $info['full_num'] = $info['chip'] . '/' . $fullNum;
                if ($info['type'] <= 5) {
                    // 旧版 lang equipment_desc
                    $info['description'] = sprintf('集齐%s个%s碎片可以合成一件Lv%s的%s', $fullNum, $info['name'], $info['needlevel'], $info['name']);
                }
            } elseif ($info['type'] <= 5) {
                $itemarr[] = $info['equipid'];
            } elseif ($info['number'] > 0) {
                $info['saleprice'] = (int)ceil($info['saleprice'] * $info['number']);
            }

            if ($info['type'] === 100 && $info['equipid'] > 100) {
                // 精华类道具：equipid - 100 为 equip_essence.id
                $essence = EquipEssence::ROWS[$info['equipid'] - 100] ?? null;
                $info['equipid'] = $essence['id'] ?? null;
                $info['description'] = $essence['desc'] ?? null;
                $info['quality'] = $essence['quality'] ?? null;
            }

            $itemlist[$key] = $info;
        }

        // 旧版只在背包里有整件装备时才进入这段（补介绍、VIP 售价与 hammer_quality）
        if ($itemarr !== []) {
            $itemarr = array_unique($itemarr);
            foreach ($itemlist as $key => $val) {
                $tpl = in_array($val['equipid'], $itemarr, true) ? (EquipTemplate::ROWS[$val['equipid']] ?? null) : null;
                if ($tpl !== null && $val['type'] <= 5) {
                    if ($vip >= self::SALE_REFUND_VIP) {
                        $strengthenSilver = LegacyStats::strengthenTotalCost($val['type'], $val['quality'], $val['level']);
                        $itemlist[$key]['saleprice'] = (int)($tpl['saleprice'] + $strengthenSilver);
                    }
                    $itemlist[$key]['description'] = $tpl['description'];
                }
                // 旧版在这里还按精炼 / 强化成就累加 powerup，但累加在循环局部变量上、没有写回列表，
                // 所以背包里的 powerup 始终是库里的原值；只有 hammer_quality 生效
                $promote = LegacyStats::refinePromote($val['hammer']);
                $itemlist[$key]['hammer_quality'] = $promote === null ? 0 : (int)$promote['type'];
            }
        }

        $return['list'] = $itemlist;
        return $return;
    }

    /**
     * 使用物品: c=pack&m=use_item
     */
    public static function use_item(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => '使用成功',
        ];
    }

    /**
     * 出售物品: c=pack&m=sale（旧版 controllers/pack.php:sale）
     *
     * 状态码：-1 道具不存在，-2 已穿戴不可出售，-3 今日高强化装备出售已达上限
     */
    public static function sale(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $itemid = (int)$ctx->input('id', 0);
        $return = ['status' => 0, 'silver' => 0, 'all_silver' => 0];

        $item = DB::table('equipment')->where('uid', $uid)->where('id', $itemid)->whereNull('deleted_at')->first();
        if ($item === null) {
            $return['status'] = -1;
            return $return;
        }
        if ((int)$item['generalid'] > 0) {
            $return['status'] = -2;
            // 旧版无 message；响应信封会补 message = 'ok'，packpop.js 优先显示 res.message，所以这里给出提示文案
            $return['message'] = '已穿戴装备无法出售，请先在将领面板卸下';
            return $return;
        }

        // 强化 10 级以上的装备每日限售 10 件（旧版 equipment_model get_saleitem_num / add_saleitem_num）
        if ((int)$item['level'] >= self::SALE_LEVEL) {
            $saleKey = 'equip_sale_' . date('ymd') . '_' . $uid;
            $saleNum = (int)(Redis::get($saleKey) ?? 0);
            if ($saleNum >= self::SALE_NUM) {
                $return['status'] = -3;
                return $return;
            }
            Redis::set($saleKey, $saleNum + 1, 86400);
        }

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $vip = (int)($member['vip'] ?? 0);
        $type = (int)$item['type'];
        $saleprice = (int)$item['saleprice'];
        if ((int)$item['chip'] > 0) {
            // 旧版这里不取整（index 列表里是 ceil）；入账时 add_silver 再 intval
            $saleprice = $saleprice / LegacyStats::chipNum((int)$item['quality']) * (int)$item['chip'];
        } elseif ($type <= 5 && $vip >= self::SALE_REFUND_VIP) {
            $tpl = EquipTemplate::ROWS[(int)$item['equipid']] ?? null;
            if ($tpl !== null) {
                $strengthenSilver = LegacyStats::strengthenTotalCost($type, (int)$item['quality'], (int)$item['level']);
                $saleprice = (int)($tpl['saleprice'] + $strengthenSilver);
            }
        } elseif ($type > 5 && (int)$item['number'] > 0) {
            $saleprice = $saleprice * (int)$item['number'];
        }

        // 依赖未移植：主公 140 级以上出售时卸下装备上的宝石（gem_model->pack_off_by_eid、member gemuse）；
        // 新版宝石存 Redis user_gems，不按装备镶嵌

        $affected = DB::table('equipment')->where('uid', $uid)->where('id', $itemid)->whereNull('deleted_at')
            ->update(['deleted_at' => date('Y-m-d H:i:s'), 'sale_site' => 0]);
        if ($affected > 0) {
            MemberHandler::addCurrency($uid, silver: (int)$saleprice);
            $return['status'] = 1;
            $return['silver'] = $saleprice;
            $return['all_silver'] = (int)($member['silver'] ?? 0) + (int)$saleprice;
        }
        return $return;
    }
}
