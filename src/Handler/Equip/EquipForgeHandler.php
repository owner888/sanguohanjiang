<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Equip;

use Hanjiang\Battle\LegacyStats;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\CopiesReward;
use Hanjiang\MasterData\Equip as EquipTemplate;
use Hanjiang\MasterData\EquipEssence;
use Hanjiang\MasterData\EquipStrengachieve;
use Hanjiang\MasterData\MaterialsTemp;
use Hanjiang\MasterData\NewequipConsume;
use Hanjiang\MasterData\NewequipQuality;
use Hanjiang\MasterData\NewequipResolve;
use Hanjiang\MasterData\NewequipSuit;
use Hanjiang\MasterData\RefinePromote;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\MemberStone;
use Hanjiang\Model\OwnGem;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 装备打造（移植旧版 controllers/newequip/equip_forge.php + models/newequip/equip_forge_model.php）
 *
 * 客户端（forgepop.js / resolvepop.js / miscpop.js）调用：index / get_fabrication_info / sell / fabrication / resolve_index /
 * resolve / get_suit_attr / get_equip_attr。主公 110 级开放。
 * 打造：紫装以上（type 1 ~ 5、quality 5 ~ 7、非碎片）按 MasterData NewequipConsume（旧装备 → 新装备，消耗 own_materials 的
 * 卷轴 / 材料）锻造：typeup 1 为紫装升红装（属性直接取新装备模板、强化等级清零并返还强化花费），0 / 2 按 NewequipQuality 的
 * 权重随机品阶与属性（同一件重铸属性只升不降，到品阶上限时升一阶；品阶与属性都满时回 2）；记 newequip_log。打造出橙装、或
 * 红装品阶达到优秀以上（重铸时品阶提升）发小喇叭（chat_msg）。
 * 分解：红装 / 橙装按 NewequipResolve 返还普通材料（打造消耗 × fabrratio，随机 ordinary 种）与神铸材料（按权重，× castach，
 * 神铸过的另加 retratio），并按 80% 售价返还银币、返还精炼累计消耗（RefinePromote 的玉石 / 精炼石 / 五彩石 / 上古血玉）与
 * 强化成就消耗的装备精华；主公 140 级以上先卸下装备上的宝石。材料出售按 MaterialsTemp.sales。套装属性取 NewequipSuit。
 * 精炼（hammer）与强化成就（aid）的加成计算同旧版。sell / fabrication / resolve 同旧版用 Redis 锁 forge_lk_{方法}{uid}
 * （10 秒，锁已存在时回 -2「请求太快」）。
 *
 * 照旧版保留的写法：index / resolve_index 的 page 是 SQL 偏移量（LIMIT 200 OFFSET page-1）、材料列表 LIMIT 48 OFFSET page-1；
 * index 里背包有紫装以上但都不能打造时回 -3；打造信息里的属性下限在遍历中被同装备那一行改写后沿用到之后的行；
 * resolve 的普通材料按打造消耗表随机取 ordinary 种（不足时全取）。
 * 与旧版的差别：装备只查本人的（旧版 get_info 不限 uid）；写操作在 Member::locked（玩家行锁）里执行；材料扣除用条件更新
 * （数量不足时回 -8，旧版按读到的余额写回）；强化成就多个 aid 按 id 查表（同 Battle\TeamBuilder 的处理）；分解的装备软删除。
 * 不移植：sell / get_suit_attr 的 platform = android 原生客户端文本处理。
 */
final class EquipForgeHandler
{
    /** 功能开启等级 */
    private const int OPEN_LEVEL = 110;
    /** 锁 key 前缀（forge_lk_{方法}{uid}） */
    private const string LOCK_KEY = 'forge_lk_';
    /** 锁时长（秒） */
    private const int LOCK_TIME = 10;
    /** 主公达到该等级分解时先卸下装备上的宝石 */
    private const int GEM_PACK_OFF_LEVEL = 140;
    /** VIP 达到该等级时装备售价按模板售价 + 累计强化花费计算 */
    private const int SALE_REFUND_VIP = 11;
    /** 套装属性中按百分比显示的属性 id */
    private const array PERCENT_SUIT = [9, 10, 11, 12, 13, 14, 16, 17, 19, 20, 21, 22, 26, 27];

    /** 旧版语言包 newequip/equip_forge_lang.php */
    private const array LANG = [
        'params_is_null' => '参数不能为空',
        'materials_is_null' => '没有得到材料信息',
        'undefined_error' => '未知错误',
        'function_not_open' => '功能未开启',
        'forge_data_is_error' => '基础数据错误',
        'materials_insufficient' => '卷轴不足',
        'materials_insufficient1' => '材料不足',
        'attr_max_value' => '品级和属性值均已经最高',
        'materials_insufficient3' => '资源不足',
        'materials_sub' => '扣除卷轴失败',
        'sub_equip_is_error' => '扣除装备失败',
        'unequip_is_error' => '卸下装备失败',
        'equip_is_null' => '装备不存在',
        'chat_msg' => '恭喜【%s】打造出绝世神兵-%s(%s)',
        'reslove_is_not_null' => '没有可获得的材料',
        'suit_not_null' => '该装备没有套装',
        'resolve_data_is_null' => '没有可分解的装备(红色品质以上装备)',
    ];

    /** 列表查询的装备字段（$equip_field） */
    private const array EQUIP_FIELDS = [
        'id', 'equipid', 'gid', 'name', 'type', 'quality', 'needlevel', 'level', 'power', 'powerup', 'saleprice', 'hammer', 'aid',
        'newquality', 'forgeid', 'oldequipid',
    ];

    /**
     * 打造主页: c=equip_forge&m=index&page=
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return self::fail($return, -1, 'function_not_open');
        }
        $uid = $member['uid'];
        $page = (int)$ctx->input('page', 0);
        if ($page > 0) {
            $page--;
        }
        // 材料信息
        $materialArr = [];
        foreach (self::ownMaterials($uid, $page) as $vx) {
            if ($vx['num'] === 0) {
                continue;
            }
            $materialArr[$vx['materials_id']] = $vx;
        }
        // 用户装备表
        $equipList = self::equipmentList($uid, $page, false);
        if ($equipList !== []) {
            $equiparr = [];
            foreach ($equipList as $val) {
                if ($val['newquality'] > 0) {
                    // 装备锻造过，那么就获取旧的装备id
                    $equiparr[] = $val['oldequipid'];
                }
                $equiparr[] = $val['equipid'];
            }
            $generalNames = self::generalNames($uid, array_column($equipList, 'gid'));
            // 查询玩家所有装备对应的所有消耗信息
            $consumearr = [];
            foreach (NewequipConsume::ROWS as $consume) {
                if (in_array($consume['equipid'], $equiparr, true)) {
                    $consumearr[] = $consume['equipid'];
                }
            }
            if ($consumearr === []) {
                // 基础数据错误
                return self::fail($return, -3, 'forge_data_is_error');
            }
            foreach ($equipList as $key => $val) {
                $trueEquipid = $val['newquality'] > 0 ? $val['oldequipid'] : $val['equipid'];
                if (!in_array($trueEquipid, $consumearr, true)) {
                    unset($equipList[$key]);
                    continue;
                }
                $item = EquipTemplate::ROWS[$val['equipid']] ?? ['saleprice' => 0, 'description' => null];
                $saleprice = $val['saleprice'];
                // 计算银币折扣
                if ($member['vip'] >= self::SALE_REFUND_VIP) {
                    $saleprice = (int)($item['saleprice'] + LegacyStats::strengthenTotalCost($val['type'], $val['quality'], $val['level']));
                }
                $equipList[$key]['saleprice'] = $saleprice - $item['saleprice'];
                $equipList[$key]['gname'] = $generalNames[$val['gid']] ?? '';
                [$power, , , $hammerQuality] = self::allPower($val);
                $equipList[$key]['hammer_quality'] = $hammerQuality;
                $equipList[$key]['allpower'] = round($power);
                $equipList[$key]['desc'] = $item['description'];
                unset($equipList[$key]['aid'], $equipList[$key]['forgeid'], $equipList[$key]['oldequipid']);
            }
        }
        // 材料模板信息
        foreach ($materialArr as $mid => $mat) {
            $temp = self::materialTemp($mid);
            $materialArr[$mid]['desc'] = $temp['desc'] ?? null;
            $materialArr[$mid]['sales'] = $temp['sales'] ?? null;
            unset($materialArr[$mid]['uid'], $materialArr[$mid]['dateline']);
        }
        $return['status'] = 1;
        $return['equiplist'] = array_values($equipList);
        $return['materiallist'] = array_values($materialArr);
        return $return;
    }

    /**
     * 查询新装备信息（可打造成的装备与消耗）: c=equip_forge&m=get_fabrication_info&id=
     */
    public static function get_fabrication_info(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return self::fail($return, -1, 'function_not_open');
        }
        $id = (int)$ctx->input('id', 0);
        if ($id <= 0) {
            return self::fail($return, -2, 'params_is_null');
        }
        $uid = $member['uid'];
        $ownEquip = self::equipInfo($uid, $id);
        if ($ownEquip === null) {
            return self::fail($return, -3, 'forge_data_is_error');
        }
        $materialArr = [];
        foreach (self::ownMaterials($uid) as $vx) {
            $materialArr[$vx['materials_id']] = $vx;
        }
        $oldEquipid = $ownEquip['newquality'] > 0 ? $ownEquip['oldequipid'] : $ownEquip['equipid'];
        // 查询装备对应的新装备消耗信息
        $consumeInfo = array_values(array_filter(NewequipConsume::ROWS, static fn (array $r): bool => $r['equipid'] === $oldEquipid));
        if ($consumeInfo === []) {
            return self::fail($return, -3, 'forge_data_is_error');
        }
        $midarr = [];
        foreach ($consumeInfo as $k => $consume) {
            $consumearr = [];
            for ($ii = 1; $ii < 7; $ii++) {
                $mid = $consume['type' . $ii];
                if ($mid > 0) {
                    $consumearr[] = [
                        'id' => $ii,
                        'type' => $mid,
                        'params' => $consume['params' . $ii],
                        // 构建玩家对应的材料数量
                        'ownnum' => $materialArr[$mid]['num'] ?? 0,
                    ];
                    if ($consume['params' . $ii] > 0) {
                        $midarr[$mid] = $mid;
                    }
                }
                unset($consumeInfo[$k]['type' . $ii], $consumeInfo[$k]['params' . $ii]);
            }
            $consumeInfo[$k]['consumearr'] = $consumearr;
        }
        // 批量材料模板信息
        $matinfoarr = [];
        foreach ($midarr as $mid) {
            $temp = self::materialTemp($mid);
            if ($temp !== null) {
                $matinfoarr[$mid] = $temp;
            }
        }
        if ($matinfoarr === []) {
            return self::fail($return, -2, 'forge_data_is_error');
        }
        // 当前品阶及以上的品阶信息
        $qualityArr = [];
        foreach (self::qualityList($ownEquip['newquality']) as $val) {
            if ($val['qualityid'] !== 0) {
                $qualityArr[$val['qualityid']] = $val;
            }
        }
        if ($qualityArr === []) {
            return self::fail($return, -2, 'forge_data_is_error');
        }
        $guidelist = self::guideList($uid);
        $refineInfo = LegacyStats::refinePromote($ownEquip['hammer']);
        if ($ownEquip['hammer'] > 0 && $refineInfo === null) {
            return self::fail($return, -2, 'forge_data_is_error');
        }
        $achInfo = self::strengAchieves($ownEquip['aid']);
        if ($ownEquip['aid'] !== '' && $achInfo === []) {
            return self::fail($return, -2, 'forge_data_is_error');
        }
        $minPercent = reset($qualityArr)['attributedown'];
        $maxPercent = end($qualityArr)['attributeup'];
        foreach ($consumeInfo as $k => $vxl) {
            foreach ($vxl['consumearr'] as $ck => $val) {
                $mid = $val['type'];
                $consumeInfo[$k]['consumearr'][$ck]['quality'] = $matinfoarr[$mid]['quality'] ?? null;
                $consumeInfo[$k]['consumearr'][$ck]['mname'] = $matinfoarr[$mid]['name'] ?? null;
                $consumeInfo[$k]['consumearr'][$ck]['mdesc'] = $matinfoarr[$mid]['desc'] ?? null;
                $consumeInfo[$k]['consumearr'][$ck]['guide'] = $guidelist[$mid] ?? [];
            }
            $newTemp = EquipTemplate::ROWS[$vxl['newequipid']] ?? null;
            if ($newTemp === null) {
                return self::fail($return, -2, 'forge_data_is_error');
            }
            // 新的装备是当前装备时按当前品阶取下限
            if ($vxl['newequipid'] === $ownEquip['equipid']) {
                $minPercent = $qualityArr[$ownEquip['newquality']]['attributedown'] ?? $minPercent;
            }
            // 构建新的装备属性信息
            $maxAttr = floor($maxPercent * $newTemp['power'] / 100);
            $minAttr = floor($minPercent * $newTemp['power'] / 100);
            if ($minAttr < $ownEquip['power'] && $vxl['newequipid'] === $ownEquip['equipid']) {
                $minAttr = $ownEquip['power'];
            }
            $consumeInfo[$k]['newpowerdown'] = $minAttr;
            $consumeInfo[$k]['newpowerup'] = $maxAttr;
            $powerup = $ownEquip['powerup'] - $ownEquip['power'];
            $sumup = $maxAttr + $powerup;
            $sumdown = $minAttr + $powerup;
            // 计算精炼和强化成就
            if ($refineInfo !== null) {
                $sumup += $powerup * $refineInfo['percent'] / 100;
                $sumdown += $powerup * $refineInfo['percent'] / 100;
            }
            if ($achInfo !== []) {
                $streng = end($achInfo);
                $add = $streng['add_per' . $ownEquip['type']] ?? 0;
                $sumup += $sumup * $add / 100;
                $sumdown += $sumdown * $add / 100;
            }
            $consumeInfo[$k]['sumnewpowerdown'] = round($sumdown);
            $consumeInfo[$k]['sumnewpowerup'] = round($sumup);
            unset($newTemp['id'], $newTemp['description'], $newTemp['saleprice']);
            $consumeInfo[$k]['newequipinfo'] = $newTemp;
            unset($consumeInfo[$k]['id'], $consumeInfo[$k]['equipid'], $consumeInfo[$k]['fabrratio'], $consumeInfo[$k]['castach'], $consumeInfo[$k]['retratio']);
        }
        $return['newequiplist'] = $consumeInfo;
        $return['status'] = 1;
        return $return;
    }

    /**
     * 卖出材料: c=equip_forge&m=sell&id=（拥有的材料行 id）&num=
     */
    public static function sell(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        $num = (int)$ctx->input('num', 0);
        return self::guarded('sell', $uid, static function () use ($uid, $id, $num): array {
            $return = ['status' => 0];
            if ($id <= 0 || $num <= 0) {
                return self::fail($return, -3, 'params_is_null');
            }
            $material = DB::table('own_materials')->select('id', 'uid', 'materials_id', 'name', 'quality', 'chip', 'num', 'dateline')
                ->where('uid', $uid)->where('id', $id)->whereNull('deleted_at')->first();
            if ($material === null) {
                return self::fail($return, -4, 'forge_data_is_error');
            }
            if ((int)$material['num'] < $num) {
                // 相关材料不足（卷轴 / 材料）
                return self::fail($return, -5, (int)$material['quality'] !== 5 ? 'materials_insufficient1' : 'materials_insufficient');
            }
            $temp = self::materialTemp((int)$material['materials_id']);
            if ($temp === null) {
                return self::fail($return, -6, 'materials_is_null');
            }
            $balance = (int)$material['num'] - $num;
            $addSilver = $num * $temp['sales'];
            // 扣除卷轴
            if (!self::subMaterial($uid, (int)$material['materials_id'], $num)) {
                return self::fail($return, -7, 'materials_sub');
            }
            // 添加银币
            Member::addCurrency($uid, silver: $addSilver);
            self::materialsLog($uid, [[
                'type' => 0,
                'materials_id' => (int)$material['materials_id'],
                'name' => (string)$material['name'],
                'quality' => (int)$material['quality'],
                'num' => $num,
                'now_num' => $balance,
            ]], 'sell');
            $return['status'] = 1;
            $return['silver'] = (int)DB::table('member')->where('uid', $uid)->value('silver');
            return $return;
        });
    }

    /**
     * 装备锻造: c=equip_forge&m=fabrication&id=（装备 id）&newequipid=
     */
    public static function fabrication(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $newequipid = (int)$ctx->input('newequipid', 0);
        $id = (int)$ctx->input('id', 0);
        return self::guarded('fabrication', $uid, static fn (): array => self::fabricate($uid, $id, $newequipid, time()));
    }

    /**
     * 分解首页: c=equip_forge&m=resolve_index&page=
     */
    public static function resolve_index(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return self::fail($return, -1, 'function_not_open');
        }
        $uid = $member['uid'];
        $page = (int)$ctx->input('page', 0);
        if ($page > 0) {
            $page--;
        }
        // 用户红装 / 橙装列表
        $equipList = self::equipmentList($uid, $page, true);
        if ($equipList === []) {
            // 没有可以分解的装备
            return self::fail($return, -2, 'resolve_data_is_null');
        }
        $generalNames = self::generalNames($uid, array_column($equipList, 'gid'));
        // 批量查询新装备的合成信息（newequipid IN 装备模板 AND equipid IN 旧装备），一条都没有时为基础数据错误
        $equipids = array_column($equipList, 'equipid');
        $depletes = array_column($equipList, 'oldequipid');
        $hasConsume = false;
        foreach (NewequipConsume::ROWS as $row) {
            if (in_array($row['newequipid'], $equipids, true) && in_array($row['equipid'], $depletes, true)) {
                $hasConsume = true;
                break;
            }
        }
        if (!$hasConsume) {
            return self::fail($return, -2, 'undefined_error');
        }
        // 分解数据
        $resolveArr = [];
        foreach (NewequipResolve::ROWS as $val) {
            $resolveArr[$val['qualityid']] = $val;
        }
        $list = [];
        foreach ($equipList as $val) {
            $consume = self::consume($val['equipid'], $val['oldequipid']);
            if ($consume === null) {
                // 装备没有可分解的东西（旧版 unset($val) 只断开引用，这件装备仍按原样留在列表里）
                $list[] = $val;
                continue;
            }
            // 计算银币折扣
            if ($member['vip'] >= self::SALE_REFUND_VIP) {
                $item = EquipTemplate::ROWS[$val['equipid']] ?? ['saleprice' => 0];
                $val['saleprice'] = (int)($item['saleprice'] + LegacyStats::strengthenTotalCost($val['type'], $val['quality'], $val['level']));
            }
            $val['saleprice'] = (int)($val['saleprice'] * 0.8);
            $val['gname'] = $generalNames[$val['gid']] ?? '';
            [$power, , , $hammerQuality] = self::allPower($val);
            $val['hammer_quality'] = $hammerQuality;
            $val['allpower'] = round($power);
            $resolve = $resolveArr[$val['quality']] ?? null;
            if ($resolve !== null) {
                for ($ii = 1; $ii < 3; $ii++) {
                    if ($resolve['godcast' . $ii] > 0) {
                        $god = self::materialTemp($resolve['godcast' . $ii]);
                        $retnum = mt_rand($resolve['gcdown' . $ii], $resolve['gcup' . $ii]);
                        // 锻造分解加成系数（神铸装备额外增加掉落数量）
                        $addstg = $consume['castach'] + ($val['forgeid'] > 0 ? $consume['retratio'] : 0);
                        $val['getgod'][] = [
                            'godcast' => $resolve['godcast' . $ii],
                            'gcrate' => $resolve['gcrate' . $ii],
                            'name' => $god['name'] ?? null,
                            'quality' => $god['quality'] ?? null,
                            'num' => floor($addstg * $retnum),
                        ];
                    }
                }
            }
            unset($val['aid'], $val['forgeid'], $val['oldequipid']);
            $list[] = $val;
        }
        $return['status'] = 1;
        $return['equiplist'] = $list;
        return $return;
    }

    /**
     * 装备分解: c=equip_forge&m=resolve&id=
     */
    public static function resolve(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        return self::guarded('resolve', $uid, static fn (): array => self::doResolve($uid, $id, time()));
    }

    /**
     * 套装属性预览: c=equip_forge&m=get_suit_attr&npid=
     */
    public static function get_suit_attr(Context $ctx): array
    {
        $return = ['status' => 0];
        if (self::member(MemberHandler::resolveUid($ctx)) === null) {
            return self::fail($return, -1, 'function_not_open');
        }
        $npid = (int)$ctx->input('npid', 0);
        if ($npid <= 0) {
            return self::fail($return, -1, 'params_is_null');
        }
        $suit = self::suitAttr($npid);
        if ($suit === null) {
            return self::fail($return, -3, 'suit_not_null');
        }
        $return['status'] = 1;
        $return['suit_attr'] = self::sortSuitarr($suit);
        return $return;
    }

    /**
     * 查询新装备激活属性: c=equip_forge&m=get_equip_attr&id=
     */
    public static function get_equip_attr(Context $ctx): array
    {
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return self::fail($return, -1, 'function_not_open');
        }
        $id = (int)$ctx->input('id', 0);
        if ($id <= 0) {
            return self::fail($return, -1, 'params_is_null');
        }
        $uid = $member['uid'];
        $equip = self::equipInfo($uid, $id);
        if ($equip === null || $equip['newquality'] <= 0) {
            // 装备不存在或装备未锻造过
            return self::fail($return, -2, 'forge_data_is_error');
        }
        $general = null;
        $eidx = [];
        if ($equip['gid'] > 0) {
            $general = DB::table('general')->select('id', 'name')->where('uid', $uid)->where('id', $equip['gid'])->whereNull('deleted_at')->first();
            if ($general === null) {
                return self::fail($return, -2, 'forge_data_is_error');
            }
            // 武将身上各装配位置的装备模板 id
            foreach (DB::table('equipment')->select('equipid', 'type')->where('uid', $uid)->where('generalid', $equip['gid'])
                ->whereNull('deleted_at')->get() as $row) {
                $site = LegacyStats::itemSite((int)$row['type']);
                if ($site !== null) {
                    $eidx[$site] = (int)$row['equipid'];
                }
            }
        }
        [$powerup, $refineadd, $stgadd, $hammerQuality] = self::allPower($equip);
        $equip['hammer_quality'] = $hammerQuality;
        $equip['powerup'] = (int)($equip['powerup'] - $equip['power']);
        $equip['refineadd'] = (int)$refineadd;
        $equip['stgadd'] = (int)$stgadd;
        $equip['allpower'] = round($powerup);
        // 获取套装属性表数据
        $suit = self::suitAttr($equip['equipid']);
        if ($suit === null) {
            return self::fail($return, -2, 'forge_data_is_error');
        }
        $equip['desc'] = EquipTemplate::ROWS[$equip['equipid']]['description'] ?? null;
        $equip['gname'] = $general !== null ? (string)$general['name'] : '';
        // 开启了几件套属性
        $equip['open'] = 0;
        $suitEids = [$suit['eid1'], $suit['eid2'], $suit['eid3'], $suit['eid4']];
        for ($ii = 1; $ii < 5; $ii++) {
            $suit['open' . $ii] = 0;
            if (($eidx[$ii] ?? 0) > 0 && in_array($eidx[$ii], $suitEids, true)) {
                $suit['open' . $ii] = 1;
                $equip['open']++;
            }
        }
        $equip['suitarr'] = self::sortSuitarr($suit);
        unset($equip['glevel'], $equip['uid'], $equip['chip'], $equip['number'], $equip['disable'], $equip['sale_site'], $equip['dateline'], $equip['aid']);
        $return['status'] = 1;
        $return['equip_info'] = $equip;
        return $return;
    }

    /**
     * 锻造（在玩家行锁里）
     *
     * @return array<string, mixed>
     */
    private static function fabricate(int $uid, int $id, int $newequipid, int $now): array
    {
        $return = ['status' => 0];
        if ($newequipid <= 0 || $id <= 0) {
            return self::fail($return, -3, 'params_is_null');
        }
        $own = self::equipInfo($uid, $id);
        if ($own === null) {
            return self::fail($return, -5, 'forge_data_is_error');
        }
        $oldEquipid = $own['newquality'] > 0 ? $own['oldequipid'] : $own['equipid'];
        $consume = self::consume($newequipid, $oldEquipid);
        if ($consume === null) {
            return self::fail($return, -4, 'forge_data_is_error');
        }
        $newequipid = $consume['newequipid'];
        $newTemp = EquipTemplate::ROWS[$newequipid] ?? null;
        $consumeTemp = EquipTemplate::ROWS[$consume['equipid']] ?? null;
        $oldTemp = EquipTemplate::ROWS[$oldEquipid] ?? null;
        if ($newTemp === null || $consumeTemp === null || $oldTemp === null) {
            return self::fail($return, -6, 'forge_data_is_error');
        }
        // 获取锻造装备相应消耗资源
        $consumeNum = [];
        for ($ii = 1; $ii < 7; $ii++) {
            if ($consume['type' . $ii] > 0) {
                $consumeNum[$consume['type' . $ii]] = $consume['params' . $ii];
            }
        }
        // 在玩家行锁事务里锁住这些材料行，之后的扣除不会被其他请求插入
        $params = ['uid' => $uid];
        foreach (array_keys($consumeNum) as $i => $mid) {
            $params['m' . $i] = $mid;
        }
        $materialList = $consumeNum === [] ? [] : DB::query(
            'SELECT `materials_id`, `name`, `quality`, `num` FROM `zc_sanguo_own_materials`
             WHERE `uid` = :uid AND `materials_id` IN (:' . implode(', :', array_slice(array_keys($params), 1)) . ') AND `deleted_at` IS NULL FOR UPDATE',
            $params
        );
        if ($materialList === [] || count($materialList) !== count($consumeNum)) {
            return self::fail($return, -7, 'materials_insufficient3');
        }
        $balanceArr = [];
        foreach ($materialList as $val) {
            $mid = (int)$val['materials_id'];
            if ($consumeNum[$mid] > (int)$val['num']) {
                return self::fail($return, -8, 'materials_insufficient3');
            }
            $balanceArr[$mid] = ['name' => (string)$val['name'], 'quality' => (int)$val['quality'], 'upnum' => (int)$val['num'] - $consumeNum[$mid]];
        }
        // 当前装备的品级信息（> 0 表示重新锻造）
        $newquality = $own['newquality'];
        $qualityInfo = self::qualityList($newquality);
        if ($qualityInfo === []) {
            return self::fail($return, -9, 'forge_data_is_error');
        }
        $allRate = 0;
        $minRate = 10000;
        foreach ($qualityInfo as $val) {
            $allRate += $val['rate'];
            if ($val['rate'] < $minRate) {
                $minRate = $val['rate'];
            }
        }
        $randNum = mt_rand($minRate, max($minRate, $allRate));
        $newqualityInfo = [];
        $newequipAttr = 0;
        $nowRate = 0;
        // 是否是升级装备（1 紫装到红装）
        $type = $consume['typeup'];
        foreach ($qualityInfo as $key => $val) {
            if ($type === 1) {
                // 从紫装升级到红装，不计算升级锻造品级属性
                $newqualityInfo = $val;
                $newequipAttr = $newTemp['power'];
                break;
            }
            $nowRate += $val['rate'];
            // 重铸为不同装备且当前品级与遍历到的品级相同时保留品级
            if (($newquality > 0 && $newquality === $val['qualityid'] && $own['equipid'] !== $newequipid) || $randNum <= $nowRate) {
                $newqualityInfo = $val;
                $newpower = $newTemp['power'];
                $attributedown = $val['attributedown'];
                $attributeup = $val['attributeup'];
                $newattributeup = floor($attributeup * $newpower / 100);
                $upattr = floor($newattributeup * $val['upattr'] / 100);
                // 同一件重铸成相同装备：当前品级的属性已经最高，直接升级到下一个品级
                if ($upattr <= $own['power'] && in_array($type, [0, 2], true) && $own['newquality'] === $newqualityInfo['qualityid'] && $own['equipid'] === $newequipid) {
                    $qualityid = count($qualityInfo) > $key + 1 ? $key + 1 : count($qualityInfo);
                    $newqualityInfo = $qualityInfo[$qualityid] ?? $newqualityInfo;
                    $attributeup = $newqualityInfo['attributeup'];
                    $attributedown = $newqualityInfo['attributedown'];
                    $newattributeup = floor($attributeup * $newpower / 100);
                }
                // 计算属性下限
                $newattributedown = floor($attributedown * $newpower / 100);
                $newattributedown = $newattributedown <= $own['power'] && $own['equipid'] === $newequipid ? $own['power'] : $newattributedown;
                $endq = end($qualityInfo);
                // 最高品级且已经升级满了
                if ($newattributeup <= $own['power'] && $endq['qualityid'] === $own['newquality'] && $own['equipid'] === $newequipid) {
                    return self::fail($return, 2, 'attr_max_value');
                }
                if ($newattributedown >= $newattributeup) {
                    // 基础数据错误
                    return self::fail($return, -3, 'forge_data_is_error');
                }
                $newattributedown++;
                $newequipAttr = mt_rand((int)$newattributedown, (int)$newattributeup);
                break;
            }
        }
        $newattr = $newequipAttr;
        if ($type === 0) {
            $newattr += $own['powerup'] - $own['power'];
        }
        // 计算精炼加成系数，计算强化成就加成系数
        if ($own['hammer'] > 0) {
            $refine = LegacyStats::refinePromote($own['hammer']);
            if ($refine === null) {
                return self::fail($return, -2, 'forge_data_is_error');
            }
            $newattr += ($own['powerup'] - $own['power']) * $refine['percent'] / 100;
        }
        if ($own['aid'] !== '') {
            $achInfo = self::strengAchieves($own['aid']);
            if ($achInfo === []) {
                return self::fail($return, -2, 'forge_data_is_error');
            }
            $streng = end($achInfo);
            $newattr += $newattr * ($streng['add_per' . $own['type']] ?? 0) / 100;
        }
        $saleprice = 0;
        $retsilver = 0;
        $stglevel = 0;
        // 重置 powerup 属性
        $rmpowerup = $own['powerup'] - $own['power'] + $newequipAttr;
        if (in_array($type, [1, 2], true)) {
            // 升级时才重置新装备属性为模板属性
            if ($type === 1) {
                $newequipAttr = $newTemp['power'];
                $stglevel = 1;
            }
            $saleprice = $newTemp['saleprice'];
            // 强化等级大于 0 级则返还强化花费
            if ($newquality === 0 && $own['level'] > 0) {
                $retsilver = LegacyStats::strengthenTotalCost($own['type'], $own['quality'], $own['level']);
                if (self::vip($uid) < self::SALE_REFUND_VIP) {
                    $retsilver = $own['saleprice'] - $oldTemp['saleprice'];
                }
                $stglevel = 1;
            }
            $rmpowerup = $newequipAttr;
        } else {
            // 重新计算出售价格
            $saleprice = $own['saleprice'] - $consumeTemp['saleprice'] + $newTemp['saleprice'];
        }
        // 扣除相应资源
        $logs = [];
        foreach ($balanceArr as $mid => $val) {
            self::subMaterial($uid, $mid, $consumeNum[$mid]);
            $logs[] = ['type' => 0, 'materials_id' => $mid, 'name' => $val['name'], 'quality' => $val['quality'], 'num' => $consumeNum[$mid], 'now_num' => $val['upnum']];
        }
        self::materialsLog($uid, $logs, 'fabrication');
        // 紫装升红装不计算品级
        if ($type === 1) {
            $newqualityInfo['qualityid'] = 0;
        }
        $logOldEquipid = $oldEquipid;
        if ($type === 1) {
            $oldEquipid = 0;
        }
        // 更新属性（update_equip_attr：$power、$newquality、$oldequipid、$saleprice、$rmpowerup 为 0 时不改该列）
        $data = [];
        if ($newequipAttr) {
            $data += [
                'power' => $newequipAttr,
                'equipid' => $newequipid,
                'name' => $newTemp['name'],
                'needlevel' => $newTemp['needlevel'],
                'quality' => $newTemp['quality'],
            ];
        }
        if ($newqualityInfo['qualityid']) {
            $data['newquality'] = $newqualityInfo['qualityid'];
        }
        if ($oldEquipid) {
            $data['oldequipid'] = $oldEquipid;
        }
        if ($saleprice) {
            $data['saleprice'] = $saleprice;
        }
        if ($rmpowerup) {
            $data['powerup'] = (int)$rmpowerup;
        }
        if ($stglevel > 0) {
            $data['level'] = 0;
        }
        if ($data !== []) {
            DB::table('equipment')->where('id', $id)->where('uid', $uid)->whereNull('deleted_at')->update($data);
        }
        // 打造装备广播：锻造出橙装、锻造红装品级良好以上；重铸时品级提升才广播
        $horn = false;
        if ($newquality === 0 || $newqualityInfo['qualityid'] > $newquality) {
            if ($own['quality'] === 7) {
                $horn = true;
            } elseif ($own['quality'] === 6 && $newqualityInfo['qualityid'] >= 3) {
                $horn = true;
            }
        }
        if ($horn) {
            $sender = Chat::sender($uid);
            $name = Chat::quality($newTemp['quality'], (string)$newTemp['name']);
            $qualityx = Chat::quality($newqualityInfo['qualityid'] + 1, (string)$newqualityInfo['name']);
            Chat::sendToHorn(sprintf(self::LANG['chat_msg'], $sender['nickname'] ?? '', $name, $qualityx), $sender);
        }
        DB::table('newequip_log')->insert([
            'uid' => $uid,
            'ownid' => $id,
            'before_attr' => $own['power'],
            'after_attr' => $newequipAttr,
            'before_quality' => $newquality,
            'after_quality' => $newqualityInfo['qualityid'],
            'before_equipid' => $logOldEquipid,
            'after_equipid' => $newequipid,
            'dateline' => $now,
            'type' => $type,
        ]);
        // 返还升级银币
        if ($retsilver > 0) {
            Member::addCurrency($uid, silver: $retsilver);
        }
        $info = $newqualityInfo;
        $info['attr'] = round($newequipAttr);
        $info['newattr'] = round($newattr);
        $info['needlevel'] = $newTemp['needlevel'];
        $info['type'] = $newTemp['type'];
        $info['quality'] = $newTemp['quality'];
        $info['id'] = $newTemp['id'];
        $info['name'] = $newTemp['name'];
        unset($info['attributedown'], $info['attributeup'], $info['rate'], $info['upattr']);
        $return['status'] = 1;
        $return['ownid'] = $own['id'];
        $return['newquality_info'] = $info;
        $return['retsilver'] = $retsilver;
        $return['allsilver'] = (int)DB::table('member')->where('uid', $uid)->value('silver');
        return $return;
    }

    /**
     * 分解（在玩家行锁里）
     *
     * @return array<string, mixed>
     */
    private static function doResolve(int $uid, int $id, int $now): array
    {
        $return = ['status' => 0];
        if ($id <= 0) {
            return self::fail($return, -1, 'params_is_null');
        }
        $equip = self::equipInfo($uid, $id);
        if ($equip === null) {
            return self::fail($return, -2, 'equip_is_null');
        }
        $consume = self::consume($equip['equipid'], $equip['oldequipid']);
        if ($consume === null) {
            return self::fail($return, -3, 'forge_data_is_error');
        }
        $godcost = null;
        foreach (NewequipResolve::ROWS as $row) {
            if ($row['qualityid'] === $equip['quality']) {
                $godcost = $row;
                break;
            }
        }
        if ($godcost === null) {
            return self::fail($return, -3, 'forge_data_is_error');
        }
        // 普通材料：从打造消耗里随机取 ordinary 种，数量 × fabrratio
        $consumeArr = [];
        for ($ii = 1; $ii < 7; $ii++) {
            if ($consume['type' . $ii] > 0) {
                $consumeArr[] = ['type' => $consume['type' . $ii], 'num' => $consume['params' . $ii]];
            }
        }
        $resolveList = [];
        $godcastList = [];
        $materialIds = [];
        for ($ii = 1; $ii <= $godcost['ordinary']; $ii++) {
            shuffle($consumeArr);
            $result = array_pop($consumeArr);
            if ($result === null) {
                break;
            }
            $resolveList[$result['type']] = (int)floor($result['num'] * $consume['fabrratio']);
            $materialIds[] = $result['type'];
        }
        // 神铸材料掉落
        for ($ii = 1; $ii <= 2; $ii++) {
            $godcast = $godcost['godcast' . $ii];
            if ($godcast > 0) {
                $randNum = mt_rand(1, 100);
                if ($godcost['gcrate' . $ii] >= $randNum) {
                    $num = mt_rand($godcost['gcdown' . $ii], $godcost['gcup' . $ii]);
                    $addstg = $consume['castach'] + ($equip['forgeid'] > 0 ? $consume['retratio'] : 0);
                    $num = (int)floor($addstg * $num);
                    if (isset($resolveList[$godcast])) {
                        $num += $resolveList[$godcast];
                    }
                    $godcastList[$godcast] = $num;
                    $materialIds[] = $godcast;
                }
            }
        }
        $member = self::member($uid, false);
        // 卸下宝石
        if ($member !== null && $member['level'] >= self::GEM_PACK_OFF_LEVEL) {
            $packNum = OwnGem::packOffByEid($uid, $id);
            if ($packNum > 0) {
                OwnGem::addGemuse($uid, $packNum);
            }
        }
        // 把装备从武将身上卸下
        if ($equip['gid'] > 0) {
            $affected = DB::table('equipment')->where('uid', $uid)->where('id', $id)->whereNull('deleted_at')->update(['generalid' => 0]);
            if ($affected <= 0) {
                return self::fail($return, -7, 'unequip_is_error');
            }
        }
        $silver = $equip['saleprice'];
        // 累加精炼材料
        $jade = $refinestone = $colorstone = $bloodstone = 0;
        if ($equip['hammer'] > 0) {
            foreach (RefinePromote::ROWS as $row) {
                if ($row['level'] <= $equip['hammer']) {
                    $jade += (int)$row['jade'];
                    $refinestone += (int)$row['refinestone'];
                    $colorstone += (int)$row['colorstone'];
                    $bloodstone += (int)$row['bloodstone'];
                }
            }
        }
        // 强化成就消耗的精华
        $essenceArr = [];
        if ($equip['aid'] !== '') {
            $history = self::strengAchieves($equip['aid']);
            if ($history === []) {
                return self::fail($return, -4, 'forge_data_is_error');
            }
            foreach ($history as $val) {
                $item = $val['item' . $equip['type']] ?? 0;
                $essenceArr[$item] = ($essenceArr[$item] ?? 0) + ($val['item_num' . $equip['type']] ?? 0);
            }
        }
        if ($materialIds === []) {
            return self::fail($return, -9, 'reslove_is_not_null');
        }
        $retResoure = [
            'silver' => 0,
            'jade' => 0,
            'colorstone' => 0,
            'bloodstone' => 0,
            'refinestone' => 0,
            'essence' => [],
            'materials' => [],
        ];
        $getMaterials = [];
        $temps = [];
        foreach (MaterialsTemp::ROWS as $row) {
            if (in_array($row['materials_id'], $materialIds, true)) {
                $val = $row;
                unset($val['id'], $val['chip']);
                $temps[$row['materials_id']] = $val;
                if (isset($godcastList[$row['materials_id']])) {
                    $val['num'] = $godcastList[$row['materials_id']];
                    $getMaterials[] = $val;
                } else {
                    $val['num'] = $resolveList[$row['materials_id']];
                    $retResoure['materials'][] = $val;
                }
            }
        }
        if ($temps === []) {
            return self::fail($return, -8, 'forge_data_is_error');
        }
        $resolveList = $godcastList + $resolveList;
        // 扣除装备（软删除）
        $affected = DB::table('equipment')->where('uid', $uid)->where('id', $id)->whereNull('deleted_at')
            ->update(['deleted_at' => date('Y-m-d H:i:s'), 'sale_site' => 0]);
        if ($affected <= 0) {
            return self::fail($return, -8, 'sub_equip_is_error');
        }
        // 添加材料
        $logs = [];
        foreach ($resolveList as $mid => $num) {
            $temp = $temps[$mid] ?? ['name' => '', 'quality' => 0];
            DB::execute(
                'INSERT INTO `zc_sanguo_own_materials` (`uid`, `materials_id`, `name`, `quality`, `chip`, `num`, `dateline`)
                 VALUES (:uid, :mid, :name, :quality, 0, :num, :now)
                 ON DUPLICATE KEY UPDATE `num` = `num` + :num2',
                ['uid' => $uid, 'mid' => $mid, 'name' => $temp['name'], 'quality' => $temp['quality'], 'num' => $num, 'now' => $now, 'num2' => $num]
            );
            $nowNum = (int)DB::table('own_materials')->where('uid', $uid)->where('materials_id', $mid)->value('num');
            $logs[] = ['type' => 1, 'materials_id' => $mid, 'name' => $temp['name'], 'quality' => $temp['quality'], 'num' => $num, 'now_num' => $nowNum];
        }
        // 添加银币（80%）
        if ($silver > 0) {
            if ($member !== null && $member['vip'] >= self::SALE_REFUND_VIP) {
                $item = EquipTemplate::ROWS[$equip['equipid']] ?? ['saleprice' => 0];
                $silver = (int)($item['saleprice'] + LegacyStats::strengthenTotalCost($equip['type'], $equip['quality'], $equip['level']));
            }
            $silver = (int)($silver * 0.8);
            Member::addCurrency($uid, silver: $silver);
            $retResoure['silver'] = $silver;
            $retResoure['allsilver'] = (int)DB::table('member')->where('uid', $uid)->value('silver');
        }
        // 返还精炼材料：玉石、五彩石、上古血玉、精炼石
        if ($jade > 0) {
            MemberJade::add($uid, $jade, 'equip_forge', 'resolve', $now);
            $retResoure['jade'] = $jade;
        }
        if ($colorstone > 0) {
            MemberStone::add($uid, $colorstone, MemberStone::COLOR, 'equip_forge', 'resolve', $now);
            $retResoure['colorstone'] = $colorstone;
        }
        if ($bloodstone > 0) {
            MemberStone::add($uid, $bloodstone, MemberStone::BLOOD, 'equip_forge', 'resolve', $now);
            $retResoure['bloodstone'] = $bloodstone;
        }
        if ($refinestone > 0) {
            MemberStone::add($uid, $refinestone, MemberStone::REFINE, 'equip_forge', 'resolve', $now);
            $retResoure['refinestone'] = $refinestone;
        }
        // 返还强化成就材料
        foreach ($essenceArr as $key => $val) {
            $essence = EquipEssence::ROWS[$key] ?? null;
            if ($essence === null) {
                continue;
            }
            $essence['value'] = $val;
            Equipment::addTowerItem($uid, $key, $val);
            $retResoure['essence'][] = $essence;
        }
        // 添加分解log
        self::materialsLog($uid, $logs, 'resolve');
        $return['get_materials'] = $getMaterials;
        $return['ret_resoure'] = $retResoure;
        $return['status'] = 1;
        return $return;
    }

    /**
     * sell / fabrication / resolve：Redis 锁（锁已存在时回 -2）+ 开放等级检查 + 玩家行锁
     *
     * @param callable(): array<string, mixed> $callback
     * @return array<string, mixed>
     */
    private static function guarded(string $method, int $uid, callable $callback): array
    {
        $return = ['status' => 0];
        if (self::member($uid) === null) {
            return self::fail($return, -1, 'function_not_open');
        }
        $lock = self::LOCK_KEY . $method . $uid;
        if (!Redis::instance()->set($lock, '1', ['nx', 'ex' => self::LOCK_TIME])) {
            // 请求太快，提示未知错误（旧版这次请求结束时也会删锁）
            Redis::del($lock);
            return self::fail($return, -2, 'undefined_error');
        }
        try {
            return Member::locked($uid, $callback);
        } finally {
            Redis::del($lock);
        }
    }

    /**
     * @param array<string, mixed> $return
     * @return array<string, mixed>
     */
    private static function fail(array $return, int $status, string $lang): array
    {
        $return['status'] = $status;
        $return['msg'] = self::LANG[$lang];
        return $return;
    }

    /**
     * 玩家等级 / VIP；$open 为 true 时未到开放等级返回 null
     *
     * @return array{uid: int, level: int, vip: int}|null
     */
    private static function member(int $uid, bool $open = true): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        $row = DB::table('member')->select('uid', 'level', 'vip')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row === null || ($open && (int)$row['level'] < self::OPEN_LEVEL)) {
            return null;
        }
        return ['uid' => (int)$row['uid'], 'level' => (int)$row['level'], 'vip' => (int)$row['vip']];
    }

    private static function vip(int $uid): int
    {
        return (int)DB::table('member')->where('uid', $uid)->value('vip');
    }

    /**
     * 拥有的装备（equipment_model->get_info 单件），按旧版 own_equip 字段输出；不存在为 null
     *
     * @return array<string, mixed>|null
     */
    private static function equipInfo(int $uid, int $id): ?array
    {
        $row = DB::table('equipment')->where('uid', $uid)->where('id', $id)->whereNull('deleted_at')->first();
        if ($row === null) {
            return null;
        }
        return [
            'id' => (int)$row['id'],
            'uid' => (int)$row['uid'],
            'equipid' => (int)$row['equipid'],
            'gid' => (int)$row['generalid'],
            'glevel' => 0,
            'name' => (string)$row['name'],
            'type' => (int)$row['type'],
            'quality' => (int)$row['quality'],
            'needlevel' => (int)$row['needlevel'],
            'level' => (int)$row['level'],
            'power' => (int)$row['power'],
            'powerup' => (int)$row['powerup'],
            'saleprice' => (int)$row['saleprice'],
            'chip' => (int)$row['chip'],
            'number' => (int)$row['number'],
            'disable' => 0,
            'sale_site' => (int)$row['sale_site'],
            'dateline' => 0,
            'hammer' => (int)$row['hammer'],
            'aid' => (string)$row['aid'],
            'forgeid' => (int)$row['forgeid'],
            'newquality' => (int)$row['newquality'],
            'oldequipid' => (int)$row['oldequipid'],
            'newattribute' => (string)$row['newattribute'],
        ];
    }

    /**
     * 用户紫装以上的装备列表（get_equipment_list：$resolve 时只取红 / 橙；LIMIT 200 OFFSET $page）
     *
     * @return list<array<string, mixed>>
     */
    private static function equipmentList(int $uid, int $page, bool $resolve): array
    {
        $rows = DB::table('equipment')->where('uid', $uid)->where('chip', 0)->whereIn('type', [1, 2, 3, 4, 5])
            ->whereIn('quality', $resolve ? [6, 7] : [5, 6, 7])->whereNull('deleted_at')
            ->orderBy('generalid', 'DESC')->orderBy('quality', 'DESC')->orderBy('type', 'ASC')->orderBy('hammer', 'DESC')
            ->orderBy('newquality', 'DESC')->orderBy('level', 'DESC')->orderBy('id', 'DESC')
            ->limit(200)->offset(max(0, $page))->get();
        $list = [];
        foreach ($rows as $row) {
            $item = self::equipInfo($uid, (int)$row['id']);
            if ($item === null) {
                continue;
            }
            $equip = [];
            foreach (self::EQUIP_FIELDS as $field) {
                $equip[$field] = $item[$field];
            }
            $list[] = $equip;
        }
        return $list;
    }

    /**
     * 拥有的材料（get_own_material：$page >= 0 时 LIMIT 48 OFFSET $page）
     *
     * @return list<array<string, mixed>>
     */
    private static function ownMaterials(int $uid, int $page = -1): array
    {
        $query = DB::table('own_materials')->select('id', 'uid', 'materials_id', 'name', 'quality', 'chip', 'num', 'dateline')
            ->where('uid', $uid)->whereNull('deleted_at')->orderBy('id', 'ASC');
        if ($page >= 0) {
            $query->limit(48)->offset($page);
        }
        $list = [];
        foreach ($query->get() as $row) {
            foreach ($row as $key => $value) {
                $row[$key] = $key === 'name' ? (string)$value : (int)$value;
            }
            $list[] = $row;
        }
        return $list;
    }

    /**
     * 扣材料（条件更新，数量不足返回 false）
     */
    private static function subMaterial(int $uid, int $mid, int $num): bool
    {
        return DB::execute(
            'UPDATE `zc_sanguo_own_materials` SET `num` = `num` - :num WHERE `uid` = :uid AND `materials_id` = :mid AND `num` >= :need AND `deleted_at` IS NULL',
            ['num' => $num, 'uid' => $uid, 'mid' => $mid, 'need' => $num]
        ) > 0;
    }

    /**
     * 材料变动日志（insert_materials_log：class equip_forge）
     *
     * @param list<array<string, mixed>> $logs
     */
    private static function materialsLog(int $uid, array $logs, string $method): void
    {
        $now = time();
        foreach ($logs as $log) {
            DB::table('own_materials_log')->insert($log + [
                'uid' => $uid,
                'chip' => 0,
                'dateline' => $now,
                'class' => 'equip_forge',
                'method' => $method,
            ]);
        }
    }

    /**
     * 材料模板（materials_temp 按 materials_id）
     *
     * @return array<string, mixed>|null
     */
    private static function materialTemp(int $mid): ?array
    {
        foreach (MaterialsTemp::ROWS as $row) {
            if ($row['materials_id'] === $mid) {
                return $row;
            }
        }
        return null;
    }

    /**
     * 合成消耗（get_newequip_consume($newequipid, $equipid) 单条）
     *
     * @return array<string, mixed>|null
     */
    private static function consume(int $newequipid, int $equipid): ?array
    {
        foreach (NewequipConsume::ROWS as $row) {
            if ($row['newequipid'] === $newequipid && $row['equipid'] === $equipid) {
                return $row;
            }
        }
        return null;
    }

    /**
     * 品阶表中 qualityid >= $quality 的行（get_newequip_quality，下标从 0 开始）
     *
     * @return list<array<string, mixed>>
     */
    private static function qualityList(int $quality): array
    {
        return array_values(array_filter(NewequipQuality::ROWS, static fn (array $r): bool => $r['qualityid'] >= $quality));
    }

    /**
     * 强化成就行（get_streng_achieve(aid)：按 id 升序）
     *
     * @return array<int, array<string, mixed>>
     */
    private static function strengAchieves(string $aid): array
    {
        if ($aid === '') {
            return [];
        }
        $ids = array_map('intval', explode(',', $aid));
        sort($ids);
        $return = [];
        foreach ($ids as $id) {
            if (isset(EquipStrengachieve::ROWS[$id])) {
                $return[$id] = EquipStrengachieve::ROWS[$id];
            }
        }
        return $return;
    }

    /**
     * 精炼 + 强化成就后的总属性（列表与激活属性用：精炼加 (powerup - power) × percent%，强化成就取 aid 最后一个）
     *
     * @param array<string, mixed> $equip
     * @return array{0: float|int, 1: float|int, 2: float|int, 3: int} [总属性, 精炼加成, 强化成就加成, 精炼品质]
     */
    private static function allPower(array $equip): array
    {
        $power = $equip['powerup'];
        $refineadd = 0;
        $hammerQuality = 0;
        if ($equip['hammer'] > 0) {
            $refine = LegacyStats::refinePromote($equip['hammer']);
            $refineadd = ($equip['powerup'] - $equip['power']) * ($refine['percent'] ?? 0) / 100;
            $power += $refineadd;
            $hammerQuality = (int)($refine['type'] ?? 0);
        }
        $stgadd = 0;
        if ($equip['aid'] !== '') {
            $ids = explode(',', $equip['aid']);
            $streng = EquipStrengachieve::ROWS[(int)end($ids)] ?? null;
            $stgadd = $power * ($streng['add_per' . $equip['type']] ?? 0) / 100;
            $power += $stgadd;
        }
        return [$power, $refineadd, $stgadd, $hammerQuality];
    }

    /**
     * 武将名字（general_model->get_own_by_id(gid 数组)：id => name）
     *
     * @param list<int> $gids
     * @return array<int, string>
     */
    private static function generalNames(int $uid, array $gids): array
    {
        $gids = array_values(array_unique(array_filter($gids)));
        if ($gids === []) {
            return [];
        }
        $return = [];
        foreach (DB::table('general')->select('id', 'name')->where('uid', $uid)->whereIn('id', $gids)->whereNull('deleted_at')->get() as $row) {
            $return[(int)$row['id']] = (string)$row['name'];
        }
        return $return;
    }

    /**
     * 材料的副本获取引导（copies_reward 掉落 type 3 的材料 => 副本 / 难度 / 是否开放）
     *
     * 难度 1 恒开放；其余难度要求该副本该难度打过、且上一难度「第 N 个怪物」打过（N 为上一难度打过的怪物数，旧版写法）。
     *
     * @return array<int, list<array{copies_id: int, diff_id: int, open: int}>>
     */
    private static function guideList(int $uid): array
    {
        $beat = [];
        foreach (DB::table('copies_beat')->select('copies_id', 'diff_id', 'monster_id', 'status')->where('uid', $uid)->where('status', 1)
            ->whereNull('deleted_at')->get() as $row) {
            $beat[(int)$row['copies_id']][(int)$row['diff_id']][(int)$row['monster_id']] = (int)$row['status'];
        }
        $guidelist = [];
        $unique = [];
        foreach (CopiesReward::ROWS as $val) {
            for ($ii = 1; $ii < 6; $ii++) {
                if ($val['type' . $ii] !== 3) {
                    continue;
                }
                $materid = $val['value' . $ii];
                $open = 0;
                if ($val['diff_id'] === 1) {
                    $open = 1;
                } elseif (isset($beat[$val['copies_id']][$val['diff_id']])) {
                    $prev = $beat[$val['copies_id']][$val['diff_id'] - 1] ?? [];
                    $all = count($prev);
                    if (isset($prev[$all - 1])) {
                        $open = $prev[$all - 1];
                    }
                }
                if (!isset($unique[$materid][$val['copies_id']][$val['diff_id']])) {
                    $unique[$materid][$val['copies_id']][$val['diff_id']] = 1;
                    $guidelist[$materid][] = ['copies_id' => $val['copies_id'], 'diff_id' => $val['diff_id'], 'open' => $open];
                }
            }
        }
        return $guidelist;
    }

    /**
     * 套装属性（get_suit_attr：任一装备 id 等于 $equipid 的第一行）
     *
     * @return array<string, mixed>|null
     */
    private static function suitAttr(int $equipid): ?array
    {
        foreach (NewequipSuit::ROWS as $row) {
            if (in_array($equipid, [$row['eid1'], $row['eid2'], $row['eid3'], $row['eid4']], true)) {
                return $row;
            }
        }
        return null;
    }

    /**
     * 套装属性显示（_sort_suitarr：百分比属性的参数加 %；name1 ~ 4 为四件装备名，quality 为最后一件的品质）
     *
     * @param array<string, mixed> $suit
     * @return array<string, mixed>
     */
    private static function sortSuitarr(array $suit): array
    {
        for ($ii = 2; $ii < 5; $ii++) {
            for ($jj = 1; $jj < 3; $jj++) {
                $suitid = $suit['suit' . $ii . '_' . $jj];
                if ($suitid > 0 && (in_array($suitid, self::PERCENT_SUIT, true) || $suitid === 4)) {
                    $suit['params' . $ii . '_' . $jj] = $suit['params' . $ii . '_' . $jj] . '%';
                }
            }
        }
        for ($ii = 1; $ii < 5; $ii++) {
            $temp = EquipTemplate::ROWS[$suit['eid' . $ii]] ?? null;
            $suit['name' . $ii] = $temp['name'] ?? null;
            $suit['quality'] = $temp['quality'] ?? null;
        }
        return $suit;
    }
}

