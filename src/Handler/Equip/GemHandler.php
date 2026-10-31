<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Equip;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\GeneralHandler;
use Hanjiang\MasterData\Gem;
use Hanjiang\Model\Member;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnMatrix;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 宝石（移植旧版 controllers/gem.php + models/gem_model.php）
 *
 * 客户端（public/static/js/view/gemworld.js）调用的 11 个方法：index / get_gem_by_id / adron / pick_off / level_up /
 * gem_turn / get_gem_info / get_gem_by_page / mergegem_index / get_20gem_by_page / merge_gem。
 * 旧版 expansion（格子扩充）客户端没有调用，未移植。
 *
 * 存储见 Model\OwnGem：own_gem 每颗一行，member.gem 宝石碎片，member.gemuse 宝石格子占用（未镶嵌的宝石个数）。
 * 主公 140 级开启（未开启返回 status 2；mergegem_index / merge_gem 返回 -1，get_gem_by_page / get_20gem_by_page 不检查）。
 *
 * 与旧版的差别：
 * - 旧版 equipment_model->get_info 不限 uid，这里只查本人的装备；
 * - 未镶嵌宝石列表在旧版 ORDER BY level DESC, type ASC 之后再按 id 升序，使同级同类型的顺序固定；
 * - 修改数据的方法在 Member::locked（玩家行锁）里执行，多 Worker 下同一玩家的操作串行；
 * - 数值字段输出为整数（旧版 MySQL 驱动输出字符串）。
 */
final class GemHandler
{
    /** 开启等级（旧版 $_level） */
    public const int OPEN_LEVEL = 140;

    /**
     * 初试界面: c=gem&m=index
     *
     * 状态码：2 功能未开启；-1 获取阵法信息失败；1 成功。
     * pack 为第一页未镶嵌宝石；eid_list 为上阵将领（按转生、等级降序）的装备，第一件装备带 1 ~ 8 号位的宝石；
     * gem 为第一件装备上的第一颗宝石（没有则取背包第一颗，都没有为 0），带下一级信息。
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0];
        $member = self::member($uid);

        // 判断功能是否开启
        if (!self::isOpen($member)) {
            $return['status'] = 2;
            return $return;
        }

        // 获取上阵武将的装备
        $matrix = OwnMatrix::own($uid, OwnMatrix::activeId($uid));
        if ($matrix === null) {
            // 获取阵法信息失败
            $return['status'] = -1;
            return $return;
        }

        $gidArr = [];
        for ($i = 1; $i <= 9; $i++) {
            if (($matrix['position' . $i] ?? 0) > 0) {
                $gidArr[] = (int)$matrix['position' . $i];
            }
        }

        // 获取将领信息
        $eidArr = [];
        $generalName = [];
        if ($gidArr !== []) {
            $generalList = GeneralHandler::ownLegacyList($uid, $gidArr);
            // 武将排序（转生降序、等级降序）
            usort($generalList, static fn (array $a, array $b): int => [$b['turn'], $b['level']] <=> [$a['turn'], $a['level']]);
            // 取道具id
            foreach ($generalList as $val) {
                for ($i = 1; $i <= 4; $i++) {
                    if ($val['eid' . $i] > 0) {
                        $eidArr[] = $val['eid' . $i];
                        $generalName[$val['eid' . $i]] = $val['name'];
                    }
                }
            }
        }

        // 得到道具的详细信息
        $eidList = [];
        if ($eidArr !== []) {
            $list = self::equipInfo($uid, $eidArr);
            foreach ($eidArr as $val) {
                if (isset($list[$val])) {
                    $eidList[$val] = $list[$val];
                }
            }
        }

        // 组装道具所属武将
        $eid = 0;
        $equipList = [];
        foreach ($eidList as $val) {
            $equipList[$val['id']] = self::equipFields($val);
            $equipList[$val['id']]['generalname'] = $generalName[$val['id']];
            if ($eid === 0) {
                $eid = $val['id'];
            }
        }

        // 第一个宝石的信息（第一件道具上的第一颗）
        $oneGem = [];
        if ($eid > 0) {
            $gemInfo = OwnGem::byEid($uid, $eid);
            for ($i = 1; $i <= 8; $i++) {
                if (isset($gemInfo[$i])) {
                    $equipList[$eid][$i] = OwnGem::legacyRow($gemInfo[$i]);
                    if ($oneGem === []) {
                        $oneGem = OwnGem::legacyRow($gemInfo[$i]);
                    }
                } else {
                    $equipList[$eid][$i] = 0;
                }
            }
        }

        // 获得第一页未佩戴的宝石
        $gem = OwnGem::unequipList($uid, 1);
        $newGem = [];
        foreach ($gem as $key => $val) {
            foreach (['id', 'name', 'type', 'level', 'power', 'quality'] as $field) {
                $newGem[$key][$field] = $val[$field];
            }
        }

        // 第一个宝石的信息
        if ($oneGem === [] && $gem !== []) {
            $oneGem = $gem[0];
        }

        // 如果第一个宝石的等级小于20级才获取下个等级的信息
        if ($oneGem !== []) {
            $oneGem = self::withNext($oneGem);
        }

        // 成功返回
        $return['status'] = 1;
        $return['pack'] = [
            'chip' => (int)$member['gem'],
            'page' => 1,
            'num' => (int)$member['gemuse'],
            'allpage' => self::allpage((int)$member['gemuse'], OwnGem::LIMIT),
            'list' => $newGem,
        ];
        $return['gem'] = $oneGem !== [] ? $oneGem : 0;
        $return['eid_list'] = array_values($equipList);
        return $return;
    }

    /**
     * 查看单个道具的信息: c=gem&m=get_gem_by_id&id=装备id&gemid=宝石id
     *
     * 状态码：2 功能未开启；-1 装备 id 不合法或不存在；-2 宝石不存在；1 成功。
     * equip 为装备与 1 ~ 8 号位的宝石；gem 为装备上第一颗宝石，装备上没有宝石时取 gemid 那颗（gemid 为 0 时为空数组）。
     */
    public static function get_gem_by_id(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0];

        // 判断功能是否开启
        if (!self::isOpen(self::member($uid))) {
            $return['status'] = 2;
            return $return;
        }

        $id = (int)$ctx->input('id', 0);
        $gemid = (int)$ctx->input('gemid', 0);

        // 判断id是否合法
        if (!$id) {
            $return['status'] = -1;
            return $return;
        }

        // 获取道具信息
        $info = self::equipInfo($uid, [$id])[$id] ?? null;
        if ($info === null) {
            $return['status'] = -1;
            return $return;
        }

        $equip = self::equipFields($info);
        $oneGem = [];
        // 获取该道具上已佩戴的宝石信息
        $gemList = OwnGem::byEid($uid, $info['id']);
        for ($i = 1; $i <= 8; $i++) {
            if (isset($gemList[$i])) {
                $equip[$i] = OwnGem::legacyRow($gemList[$i]);
                if ($oneGem === []) {
                    $oneGem = OwnGem::legacyRow($gemList[$i]);
                }
            } else {
                $equip[$i] = 0;
            }
        }

        if ($oneGem !== []) {
            $oneGem = self::withNext($oneGem);
        } elseif ($gemid > 0) {
            // 获取宝石信息
            $oneGem = OwnGem::gemById($uid, $gemid);
            if ($oneGem === []) {
                $return['status'] = -2;
                return $return;
            }
            $oneGem = self::withNext($oneGem);
        }

        // 成功返回
        $return['status'] = 1;
        $return['equip'] = $equip;
        $return['gem'] = $oneGem;
        return $return;
    }

    /**
     * 佩戴: c=gem&m=adron&id=宝石id&eid=装备id&site=佩戴位置（1~8）
     *
     * 状态码：2 功能未开启；-1 位置参数不正确；-2 装备不存在；-3 宝石不存在；-4 宝石已佩戴在别的装备上；
     * -5 位置没有变化；-6 装备类型不能镶嵌；-7 宝石类型和装备类型不符；1 成功。
     * 目标位置已有宝石时：当前宝石已在该装备上则两颗调换位置，否则把原来那颗卸回背包；
     * 目标位置为空且当前宝石未佩戴时，宝石格子占用 -1。
     */
    public static function adron(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        $eid = (int)$ctx->input('eid', 0);
        $site = (int)$ctx->input('site', 0);

        return Member::locked($uid, static function () use ($uid, $id, $eid, $site): array {
            $return = ['status' => 0];

            // 判断功能是否开启
            if (!self::isOpen(self::member($uid))) {
                $return['status'] = 2;
                return $return;
            }

            // 判断位置参数是否正确
            if ($site > 8 || $site < 1) {
                $return['status'] = -1;
                return $return;
            }

            // 获取道具信息
            $equip = self::equipInfo($uid, [$eid])[$eid] ?? null;
            if ($equip === null) {
                $return['status'] = -2;
                return $return;
            }

            // 获取宝石信息
            $gem = OwnGem::gemById($uid, $id);
            if ($gem === []) {
                $return['status'] = -3;
                return $return;
            }

            // 判断该宝石是否佩戴
            if ($gem['eid'] > 0 && $gem['eid'] !== $eid) {
                $return['status'] = -4;
                return $return;
            }

            // 判断位置信息(位置有无变化)
            if ($gem['eid'] === $eid && $gem['site'] === $site) {
                $return['status'] = -5;
                return $return;
            }

            // 获取装备需要佩戴的宝石类型
            $equiptype = OwnGem::adromType((int)$equip['type']);
            if (!$equiptype) {
                $return['status'] = -6;
                return $return;
            }

            // 判断宝石类型和装备类型是否相符
            if ($gem['type'] !== $equiptype) {
                $return['status'] = -7;
                return $return;
            }

            // 获取要佩戴到的位置有没有宝石
            $siteGem = OwnGem::byEid($uid, $eid, $site);
            if ($siteGem !== []) {
                if ($gem['site'] > 0) {
                    // 调换位置
                    OwnGem::adron($uid, (int)$siteGem['id'], $eid, $gem['site']);
                } else {
                    // 卸下之前的宝石
                    OwnGem::adron($uid, (int)$siteGem['id'], 0, 0);
                }
            } elseif ($gem['eid'] === 0) {
                // 如果宝石的位置没有佩戴的宝石，并且当前的宝石是没有佩戴过的，就需要减掉包裹占用个数
                OwnGem::subGemuse($uid);
            }

            // 把当前的宝石替换到要佩戴的位置
            OwnGem::adron($uid, $id, $eid, $site);

            $gemuse = (int)self::member($uid)['gemuse'];
            // 成功返回
            $return['status'] = 1;
            $return['allpage'] = self::allpage($gemuse, OwnGem::LIMIT);
            $return['list'] = OwnGem::unequipList($uid, 1);
            $return['num'] = $gemuse;
            $return['page'] = 1;
            return $return;
        });
    }

    /**
     * 卸下: c=gem&m=pick_off&id=宝石id
     *
     * 状态码：2 功能未开启；-1 宝石不存在；-2 宝石未佩戴；1 成功（宝石格子占用 +1）。
     * 旧版 id 为 0 时先输出 -1 却没有 return（随后宝石查不到再输出一次 -1），这里直接返回 -1。
     */
    public static function pick_off(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);

        return Member::locked($uid, static function () use ($uid, $id): array {
            $return = ['status' => 0];

            // 判断功能是否开启
            if (!self::isOpen(self::member($uid))) {
                $return['status'] = 2;
                return $return;
            }

            // 判断id是否合法
            if (!$id) {
                $return['status'] = -1;
                return $return;
            }
            // 获取宝石信息
            $gem = OwnGem::gemById($uid, $id);
            if ($gem === []) {
                $return['status'] = -1;
                return $return;
            }
            // 判断宝石是否已佩戴
            if ($gem['eid'] === 0) {
                $return['status'] = -2;
                return $return;
            }

            // 执行卸下
            if (OwnGem::adron($uid, $id, 0, 0)) {
                // 增加包裹格子占用
                OwnGem::addGemuse($uid);
            }

            $gemuse = (int)self::member($uid)['gemuse'];
            // 成功返回
            $return['status'] = 1;
            $return['allpage'] = self::allpage($gemuse, OwnGem::LIMIT);
            $return['list'] = OwnGem::unequipList($uid, 1);
            $return['num'] = $gemuse;
            $return['page'] = 1;
            return $return;
        });
    }

    /**
     * 宝石升级: c=gem&m=level_up&id=宝石id
     *
     * 状态码：2 功能未开启；-1 宝石不存在；-2 等级已满（20 级或 25 级）；-3 没有升级价格；-4 获取宝石模板失败；
     * -5 碎片不足；-6 升级失败（旧版此时碎片已扣除）；1 成功。
     * 升到 level 级扣 GemLevelup[level].need_chip × 类型系数（2、4 类型 0.75）向上取整的宝石碎片，power 取模板 level{N}。
     */
    public static function level_up(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);

        return Member::locked($uid, static function () use ($uid, $id): array {
            $return = ['status' => 0];

            // 判断功能是否开启
            if (!self::isOpen(self::member($uid))) {
                $return['status'] = 2;
                return $return;
            }

            if (!$id) {
                $return['status'] = -1;
                return $return;
            }

            // 查询宝石信息
            $gem = OwnGem::gemById($uid, $id);
            if ($gem === []) {
                $return['status'] = -1;
                return $return;
            }

            // 判断宝石等级是否已满
            if ($gem['level'] === 20 || $gem['level'] >= 25) {
                $return['status'] = -2;
                return $return;
            }

            // 获取升级信息
            $level = $gem['level'] + 1;
            $info = OwnGem::levelupPrice($level, $gem['type']);
            if ($info === []) {
                $return['status'] = -3;
                return $return;
            }

            // 获取宝石模版信息
            $temp = Gem::ROWS[$gem['gemid']] ?? null;
            if ($temp === null || !isset($temp['level' . $level])) {
                $return['status'] = -4;
                return $return;
            }

            // 判断碎片是否足够，扣除碎片
            if (OwnGem::subChip($uid, $info['need_chip'], 'gem', 'level_up')['status'] !== 1) {
                $return['status'] = -5;
                return $return;
            }

            // 执行升级
            $result = OwnGem::updateGemPower($uid, $id, $level, (int)$temp['level' . $level]);
            if ($result['status'] !== 1) {
                $return['status'] = -6;
                return $return;
            }

            // 成功返回
            $return['status'] = 1;
            $return['power'] = (int)$temp['level' . $level];
            $return['quality'] = $level === 25 ? 6 : (int)ceil($level / 4);
            $return['level'] = $level;

            // 下个等级的属性
            if ($level < 20 || ($level > 20 && $level < 25)) {
                $level++;
                $nextNeed = OwnGem::levelupPrice($level, $gem['type']);
                $return['need_chip'] = $nextNeed['need_chip'] ?? null;
                $return['next_power'] = isset($temp['level' . $level]) ? (int)$temp['level' . $level] : null;
                $return['ismax'] = 0;
            } else {
                $return['ismax'] = 1;
            }

            $return['all_chip'] = OwnGem::chip($uid);
            return $return;
        });
    }

    /**
     * 宝石转碎片: c=gem&m=gem_turn&id=宝石id&confirm=1
     *
     * 状态码：2 功能未开启；-1 宝石不存在；-2 删除失败；1 成功。
     * 可得碎片 = 1 ~ 当前等级的升级碎片总和（× 类型系数，向上取整）+ 50；confirm 不为 1 时只返回 chip 不转换；
     * confirm 为 1 时删除宝石、宝石格子占用 -1（旧版镶嵌着的宝石也照样 -1）、加碎片。
     */
    public static function gem_turn(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        $confirm = (int)$ctx->input('confirm', 0);

        return Member::locked($uid, static function () use ($uid, $id, $confirm): array {
            $return = ['status' => 0];

            // 判断功能是否开启
            if (!self::isOpen(self::member($uid))) {
                $return['status'] = 2;
                return $return;
            }

            if (!$id) {
                $return['status'] = -1;
                return $return;
            }

            // 获取宝石信息
            $gem = OwnGem::gemById($uid, $id);
            if ($gem === []) {
                $return['status'] = -1;
                return $return;
            }

            // 获取宝石能转换多少碎片，要转换的碎片需要加上50初试价格
            $chip = OwnGem::levelupAllPrice($gem['level'], $gem['type']);
            $chip += 50;

            if ($confirm === 1) {
                // 执行删除
                if (!OwnGem::delGem($uid, $id)) {
                    $return['status'] = -2;
                    return $return;
                }

                // 减少包裹格子占用
                OwnGem::subGemuse($uid);

                // 添加碎片
                OwnGem::addChip($uid, $chip, 'gem', 'gem_turn');
            }

            $member = self::member($uid);
            // 成功返回
            $return['status'] = 1;
            $return['chip'] = $chip;
            $return['all_chip'] = (int)$member['gem'];
            $return['allpage'] = self::allpage((int)$member['gemuse'], OwnGem::LIMIT);
            $return['list'] = OwnGem::unequipList($uid, 1);
            $return['num'] = (int)$member['gemuse'];
            $return['page'] = 1;
            return $return;
        });
    }

    /**
     * 查看单个宝石信息: c=gem&m=get_gem_info&id=宝石id
     *
     * 状态码：2 功能未开启；-1 宝石不存在；1 成功（gem 带下一级信息）。
     */
    public static function get_gem_info(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0];

        // 判断功能是否开启
        if (!self::isOpen(self::member($uid))) {
            $return['status'] = 2;
            return $return;
        }

        $id = (int)$ctx->input('id', 0);
        // 查看宝石信息
        $gem = OwnGem::gemById($uid, $id);
        if ($gem === []) {
            $return['status'] = -1;
            return $return;
        }

        // 成功返回
        $return['status'] = 1;
        $return['gem'] = self::withNext($gem);
        return $return;
    }

    /**
     * 翻页查看宝石信息: c=gem&m=get_gem_by_page&p=页码
     *
     * 状态码：-1 页码超过总页数；1 成功。旧版不检查功能是否开启。每页 12 个。
     */
    public static function get_gem_by_page(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0];
        $page = (int)$ctx->input('p', 0);
        $page = $page > 0 ? $page : 1;
        $member = self::member($uid);
        $allpage = self::allpage((int)$member['gemuse'], OwnGem::LIMIT);
        if ($page > $allpage) {
            $return['status'] = -1;
            return $return;
        }
        // 按分页获取宝石信息
        $return['status'] = 1;
        $return['list'] = OwnGem::unequipList($uid, $page);
        $return['page'] = $page;
        $return['allpage'] = $allpage;
        $return['chip'] = (int)$member['gem'];
        $return['num'] = (int)$member['gemuse'];
        return $return;
    }

    /**
     * 宝石合成首页: c=gem&m=mergegem_index
     *
     * 状态码：-1 玩家等级不足；-3 获取新宝石模板失败（旧版 msg 取未定义的 merge_error4，为 false）；1 成功。
     * temp 为可合成的新宝石（Gem 6 ~ 10，21 级属性 level21，quality 6）；list 为第一页未镶嵌宝石（每页 9 个）；
     * gem 为 list 第一颗（带下一级信息，没有则为空数组）。
     */
    public static function mergegem_index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0, 'msg' => '未知错误'];
        $member = self::member($uid);

        if (!self::isOpen($member)) {
            // 玩家等级不足
            $return['status'] = -1;
            $return['msg'] = '玩家等级不足';
            return $return;
        }

        // 获取未装备的宝石信息
        $ungem = OwnGem::unequipList($uid, 1, 1);

        // 获取新宝石数据
        $newgem = OwnGem::newgemTemp();
        if ($newgem === []) {
            // 获取模板信息失败
            $return['status'] = -3;
            $return['msg'] = false;
            return $return;
        }

        $oneGem = [];
        // 第一个宝石的信息
        if ($ungem !== []) {
            $oneGem = self::withNext($ungem[0]);
        }

        // 成功返回
        $return['status'] = 1;
        $return['msg'] = '成功';
        $return['temp'] = $newgem;
        $return['chip'] = (int)$member['gem'];
        $return['num'] = (int)$member['gemuse'];
        $return['allpage'] = self::allpage((int)$member['gemuse'], OwnGem::MERGE_LIMIT);
        $return['page'] = 1;
        $return['gem'] = $oneGem;
        $return['list'] = $ungem;
        return $return;
    }

    /**
     * 20级宝石分页接口: c=gem&m=get_20gem_by_page&page=页码
     *
     * 状态码：-1 已经是最后一页；1 成功。旧版不检查功能是否开启；每页 9 个（旧版取的是全部未镶嵌宝石，并不只有 20 级）。
     */
    public static function get_20gem_by_page(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0];
        $page = (int)$ctx->input('page', 0);
        $page = $page > 0 ? $page : 1;
        $member = self::member($uid);
        $allpage = self::allpage((int)$member['gemuse'], OwnGem::MERGE_LIMIT);

        if ($page > $allpage) {
            $return['status'] = -1;
            $return['msg'] = '已经是最后一页';
            return $return;
        }
        // 按分页获取宝石信息
        $return['status'] = 1;
        $return['list'] = OwnGem::unequipList($uid, $page, 1);
        $return['page'] = $page;
        $return['allpage'] = $allpage;
        $return['chip'] = (int)$member['gem'];
        $return['num'] = (int)$member['gemuse'];
        $return['msg'] = '成功';
        return $return;
    }

    /**
     * 合成宝石: c=gem&m=merge_gem&gemid1=宝石id&gemid2=宝石id
     *
     * 状态码：-1 玩家等级不足；-2 操作太快（Redis 锁 gem_merge_gem_{uid} 存在，旧版此时删掉锁再返回）；-3 参数错误；
     * -4 两颗不都是本人未删除的 20 级宝石；-5 两颗类型不一样；-6 获取新宝石模板失败；-7 删除失败；-8 合成失败；1 成功。
     * 两颗同类型 20 级宝石 → 一颗该类型的新宝石（Gem 6 ~ 10，21 级），写 own_gem_mergelog。
     * 旧版不改宝石格子占用（删两颗、加一颗，gemuse 不变）；new_gem 依次为 status / id / name / type / quality / power / level。
     */
    public static function merge_gem(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gemid1 = (int)$ctx->input('gemid1', 0);
        $gemid2 = (int)$ctx->input('gemid2', 0);

        return Member::locked($uid, static function () use ($uid, $gemid1, $gemid2): array {
            $return = ['status' => 0, 'msg' => '未知错误'];
            if (!self::isOpen(self::member($uid))) {
                // 玩家等级不足
                $return['status'] = -1;
                $return['msg'] = '玩家等级不足';
                return $return;
            }

            // 加锁
            $lock = 'gem_merge_gem_' . $uid;
            if ((int)Redis::get($lock) > 0) {
                $return['status'] = -2;
                Redis::del($lock);
                $return['msg'] = '操作太快';
                return $return;
            }
            Redis::set($lock, 1, 30);

            // 接收参数
            if ($gemid1 <= 0 || $gemid2 <= 0 || $gemid1 === $gemid2) {
                $return['status'] = -3;
                $return['msg'] = '参数错误';
                Redis::del($lock);
                return $return;
            }

            // 获取宝石信息
            $gems = OwnGem::gemByIdArr($uid, [$gemid1, $gemid2]);
            if (count($gems) !== 2) {
                // 放入宝石的有误
                $return['status'] = -4;
                $return['msg'] = '宝石未达到合成条件';
                Redis::del($lock);
                return $return;
            }

            if ($gems[0]['type'] !== $gems[1]['type']) {
                // 合成宝石类型不一样
                $return['status'] = -5;
                $return['msg'] = '合成宝石类型不一样';
                Redis::del($lock);
                return $return;
            }

            // 根据合成的类型取新宝石
            $data = OwnGem::newgemTemp($gems[0]['type']);
            if ($data === []) {
                // 获取新宝石模板失败
                $return['status'] = -6;
                $return['msg'] = '获取新宝石模板失败';
                Redis::del($lock);
                return $return;
            }
            // 新宝石ID
            $id = $data[0]['id'];

            // 删除合成的宝石
            foreach ($gems as $val) {
                if (OwnGem::delGem($uid, $val['id']) !== 1) {
                    // 删除失败
                    $return['status'] = -7;
                    $return['msg'] = '删除失败';
                    Redis::del($lock);
                    return $return;
                }
            }

            // 增加合成后的新宝石
            $newGem = OwnGem::addNewGem($uid, $id, 21);
            if ($newGem['status'] !== 1) {
                $return['status'] = -8;
                $return['msg'] = '合成失败';
                Redis::del($lock);
                return $return;
            }

            // 写入合成日志
            OwnGem::addMergeLog($uid, $gemid1, $gemid2, $newGem['id']);

            $member = self::member($uid);
            // 返回数据
            $return['status'] = 1;
            $return['msg'] = '成功';
            $return['new_gem'] = $newGem;
            $return['allpage'] = self::allpage((int)$member['gemuse'], OwnGem::MERGE_LIMIT);
            $return['num'] = (int)$member['gemuse'];
            $return['chip'] = (int)$member['gem'];
            // 旧版 mergegem_index(1) 只取 list：第一页未镶嵌宝石（每页 9 个）
            $return['list'] = OwnGem::unequipList($uid, 1, 1);
            Redis::del($lock);
            return $return;
        });
    }

    /**
     * 带下一级信息（旧版各方法里同一段：等级小于 20 或在 21 ~ 24 之间时给 next_power / need_chip，ismax 0；否则 ismax 1）
     *
     * @param array<string, mixed> $gem
     * @return array<string, mixed>
     */
    private static function withNext(array $gem): array
    {
        $level = (int)$gem['level'];
        if ($level < 20 || ($level > 20 && $level < 25)) {
            // 获取下个等级的属性
            $temp = Gem::ROWS[$gem['gemid']] ?? [];
            $gem['next_power'] = isset($temp['level' . ($level + 1)]) ? (int)$temp['level' . ($level + 1)] : null;
            // 获取下个等级升级所需碎片
            $gem['need_chip'] = OwnGem::levelupPrice($level + 1, (int)$gem['type'])['need_chip'] ?? null;
            $gem['ismax'] = 0;
        } else {
            $gem['ismax'] = 1;
        }
        return $gem;
    }

    /**
     * 本人的装备（旧版 equipment_model->get_info：未删除，含碎片行；旧版不限 uid）
     *
     * @param list<int> $ids
     * @return array<int, array<string, mixed>> 装备 id => 行
     */
    private static function equipInfo(int $uid, array $ids): array
    {
        $ids = array_values(array_filter($ids, static fn (int $v): bool => $v > 0));
        if ($ids === []) {
            return [];
        }
        $return = [];
        foreach (DB::table('equipment')->where('uid', $uid)->whereIn('id', $ids)->whereNull('deleted_at')->get() as $row) {
            $return[(int)$row['id']] = $row;
        }
        return $return;
    }

    /**
     * 界面用的装备字段（旧版 id / name / type / quality / level / powerup / equipid）
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function equipFields(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'type' => (int)$row['type'],
            'quality' => (int)$row['quality'],
            'level' => (int)$row['level'],
            'powerup' => (int)$row['powerup'],
            'equipid' => (int)$row['equipid'],
        ];
    }

    /**
     * 宝石格子总页数（旧版：gemuse 为 0 时 1 页，否则 ceil(gemuse / 每页个数)）
     */
    private static function allpage(int $gemuse, int $limit): int
    {
        return $gemuse ? (int)ceil($gemuse / $limit) : 1;
    }

    /**
     * 功能是否开启（主公等级不低于 140）
     *
     * @param array<string, mixed> $member
     */
    private static function isOpen(array $member): bool
    {
        return (int)($member['level'] ?? 0) >= self::OPEN_LEVEL;
    }

    /**
     * @return array<string, mixed>
     */
    private static function member(int $uid): array
    {
        return DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
    }
}
