<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Model\MainQuestTask;
use Hanjiang\Model\Member;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\StatPay;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 将魂（移植旧版 controllers/soul.php + models/soul_model.php，路径相对旧版 s1/application/）
 *
 * 客户端调用（soulworld.js）：index、get_soul_info、get_general_info、levelup、convert、equip、unequip、
 * get_unlock_price、unlock、get_pack_info、merge_index、merge_action。旧版全部方法前端都有调用。
 *
 * 存储见 Model\OwnSoul：own_soul（将魂）、own_general_soul（将领将魂槽 site1~site8）、own_soul_mergelog（合成日志）、
 * member.soul（原魂，流水 usesoul_log）/ soulpack（包裹格数）/ souluse（包裹占用）。
 * 将魂槽按将领转生次数解锁（OwnSoul::unlockSite）；属性 / 升级花费取 MasterData SoulType，合成的新将魂取 Soul 模板。
 * 战斗加成见 TeamBuilder / BattleEngine（转生 > 0 的将领），将领界面加成见 GeneralHandler（武力 / 智力 / 生命将魂）。
 *
 * 开放：主公等级大于 50（构造函数 _open）。
 * 并发：会改数据的接口（levelup / convert / equip / unequip / unlock / merge_action）在 Member::locked 玩家行锁内执行。
 * 旧版 merge_action 的防刷锁 soul_merge_{uid} 只查不加（add_lock 从未调用），等于没有锁；这里由玩家行锁保证并发安全，
 * 不移植这个 Redis 键（-3 操作频繁不会出现，同旧版）。
 * 升级后计主线任务 16（将魂等级提升至 X 级，MainQuestTask）；背包解锁的元宝流水由 Member::deductCurrency 记，
 * 并记付费点统计 unlock_pack（StatPay）。
 */
final class SoulHandler
{
    /** 开放等级：主公等级大于 50 */
    private const int OPEN_LEVEL = 50;

    /** 将领字段（index 的 battle_field 与 get_general_info 的 general_field，顺序各按旧版） */
    private const array BATTLE_FIELD = ['id', 'quality', 'name', 'level', 'image', 'type', 'star', 'turn'];
    private const array GENERAL_FIELD = ['id', 'name', 'quality', 'level', 'image', 'type', 'star', 'turn'];

    /** 旧版语言包 olderror（merge_index / merge_action 的 msg） */
    private const array LANG = [
        'soul_pack_error' => '获取背包信息失败',
        'soul_param_error' => '参数传递错误',
        'soul_notfast_error' => '操作不要频繁',
        'soul_level_error' => '等级不足',
        'soul_info_error' => '获取将魂信息失败',
        'soul_quality_error' => '将魂品级必须为紫色',
        'soul_maxlevel_error' => '将魂等级必须满级',
        'soul_newinfo_error' => '新合成将魂基础信息错误',
        'soul_del_error' => '删除或卸载武将将魂信息失败',
        'soul_nomerge_error' => '将魂品级达到紫色品级以上，不能合成',
        'soul_notsame_error' => '合成的两个将魂ID不能相同',
    ];

    /**
     * 初始化: c=soul&m=index
     *
     * 出征将领列表（第一个将领带 site1~site8 槽位信息）、未装备将魂第 1 页、默认选中的将魂（第一个将领第一个有将魂的槽位，
     * 没有时取包裹第一个）及其升级信息；并按包裹里的将魂个数修正 member.souluse。
     */
    public static function index(Context $ctx): array
    {
        /**
         * -5 添加将领将魂槽失败
         * -4 获取升级信息失败
         * -3 获取背包信息失败
         * -2 没有将领出征
         * -1 玩家等级不足
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0, 'general' => [], 'pack' => [], 'soul' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            // 玩家等级不足
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];

        // 获取出征将领列表
        $battle = GeneralHandler::battleList($uid);
        if ($battle === []) {
            // 没有将领出征
            $return['status'] = -2;
            return $return;
        }
        $totals = count($battle);
        $battleArr = [];
        foreach ($battle as $key => $value) {
            foreach (self::BATTLE_FIELD as $v) {
                $battleArr[$key][$v] = $value[$v];
            }
        }

        $first = reset($battleArr);
        $gid = (int)$first['id'];
        $turn = (int)$first['turn'];

        // 获取将领拥有的将魂
        $site = self::generalSites($uid, $gid);
        if ($site === null) {
            // 添加将领失败
            $return['status'] = -5;
            return $return;
        }
        $unlockSite = OwnSoul::unlockSite($turn);
        $equip = self::equippedSouls($site);

        $siteNum = 0;
        foreach ($battleArr as $key => $value) {
            if ($value['id'] !== $gid) {
                continue;
            }
            foreach (self::siteInfo($site, $unlockSite, $equip) as $i => $info) {
                $battleArr[$key]['site' . $i] = $info;
                if ($siteNum === 0 && isset($info['info']['id'])) {
                    $siteNum = $i;
                }
            }
        }

        // 获取未装备将魂列表
        $unequip = OwnSoul::unequipList($uid);

        $soul = null;
        $level = $type = $quality = 0;
        if ($siteNum > 0) {
            foreach ($equip as $value) {
                if ($value['id'] === $site['site' . $siteNum]) {
                    $level = $value['level'] + 1;
                    $type = $value['type'];
                    $quality = $value['quality'];
                    $soul = $value;
                }
            }
            $from = $siteNum;
        } elseif (isset($unequip['list'][0])) {
            $level = $unequip['list'][0]['level'] + 1;
            $type = $unequip['list'][0]['type'];
            $quality = $unequip['list'][0]['quality'];
            $soul = $unequip['list'][0];
            $from = 9;
        }

        $levelup = ['info' => []];
        if ($soul !== null) {
            $soul['from'] = $from;
            // 获取升级信息
            $levelup = self::levelupRule($level, $type, $quality);
            if ($levelup['status'] !== 1) {
                // 获取升级信息失败
                $return['status'] = -4;
                return $return;
            }
            // 如果将魂品级是紫色，并且达到满级时，出现合成按钮（随后被升级信息里的 merge 覆盖，同旧版）
            $soul['merge'] = 0;
            if ($soul['quality'] === 5 && $levelup['info']['ismax'] === 1) {
                $soul['merge'] = 1;
            }
        }

        // 修复背包占用格数
        $souluse = (int)$member['souluse'];
        if ($unequip['use'] > $souluse) {
            OwnSoul::addSouluse($uid, $unequip['use'] - $souluse);
        }
        if ($unequip['use'] < $souluse) {
            OwnSoul::subSouluse($uid, $souluse - $unequip['use']);
        }

        $return['general_totals'] = $totals;
        $return['general'] = $battleArr;
        $return['pack'] = $unequip;
        $return['soul'] = $soul !== null ? array_merge($soul, $levelup['info']) : [];
        $return['status'] = 1;
        return $return;
    }

    /**
     * 获取将魂信息: c=soul&m=get_soul_info（sid 拥有将魂id）
     */
    public static function get_soul_info(Context $ctx): array
    {
        /**
         * -3 获取升级规则失败
         * -2 获取将魂信息失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];

        $sid = (int)$ctx->input('sid', 0);
        $soul = OwnSoul::byId($sid);
        if ($soul === [] || $soul['uid'] !== $uid) {
            // 获取将魂信息失败
            $return['status'] = -2;
            return $return;
        }

        $levelup = self::levelupRule($soul['level'] + 1, $soul['type'], $soul['quality']);
        if ($levelup['status'] !== 1) {
            // 获取升级规则信息
            $return['status'] = -3;
            return $return;
        }

        // 如果将魂品级是紫色，并且达到满级时，出现合成按钮
        $soul['merge'] = 0;
        if ($soul['quality'] === 5 && $levelup['info']['ismax'] === 1) {
            $soul['merge'] = 1;
        }
        $return['status'] = 1;
        $return['info'] = array_merge($soul, $levelup['info']);
        return $return;
    }

    /**
     * 获取将领拥有将魂信息: c=soul&m=get_general_info（gid 将领id，sid 将领没有将魂时默认选中的将魂）
     */
    public static function get_general_info(Context $ctx): array
    {
        /**
         * -5 添加将领将魂槽失败
         * -4 获取将魂信息失败
         * -3 获取升级信息失败
         * -2 获取将领信息失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0, 'general' => [], 'soul' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];

        $gid = (int)$ctx->input('gid', 0);
        $sid = (int)$ctx->input('sid', 0);

        $general = GeneralHandler::ownLegacyList($uid, $gid)[0] ?? null;
        if ($general === null) {
            // 获取将领信息失败
            $return['status'] = -2;
            return $return;
        }
        $generalArr = [];
        foreach (self::GENERAL_FIELD as $value) {
            $generalArr[$value] = $general[$value];
        }

        // 获取将领拥有的将魂
        $site = self::generalSites($uid, (int)$general['id']);
        if ($site === null) {
            // 添加将领失败
            $return['status'] = -5;
            return $return;
        }
        $unlockSite = OwnSoul::unlockSite((int)$general['turn']);
        $equip = self::equippedSouls($site);

        $soul = [];
        foreach (self::siteInfo($site, $unlockSite, $equip) as $i => $info) {
            $generalArr['site' . $i] = $info;
            if ($soul === [] && isset($info['info']['id'])) {
                $soul = $info['info'];
                $soul['from'] = $i;
            }
        }

        if ($soul === [] && $sid > 0) {
            $soul = OwnSoul::byId($sid);
            if ($soul === [] || $soul['uid'] !== $uid) {
                $return['status'] = -4;
                return $return;
            }
        }

        $levelup = ['info' => []];
        if ($soul !== []) {
            $levelup = self::levelupRule($soul['level'] + 1, $soul['type'], $soul['quality']);
            if ($levelup['status'] !== 1) {
                // 获取升级信息失败
                $return['status'] = -3;
                return $return;
            }
        }
        $return['status'] = 1;
        $return['general'] = $generalArr;
        $return['soul'] = $soul !== [] ? array_merge($soul, $levelup['info']) : [];
        return $return;
    }

    /**
     * 升级将魂: c=soul&m=levelup（sid 拥有将魂id）
     */
    public static function levelup(Context $ctx): array
    {
        /**
         * -6 获取下个等级信息失败
         * -5 扣除原魂失败
         * -4 升级失败
         * -3 获取升级规则失败
         * -2 获取将魂信息失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         *  2 等级已满
         *  3 原魂不足
         */
        $uid = MemberHandler::resolveUid($ctx);
        $sid = (int)$ctx->input('sid', 0);
        return Member::locked($uid, static function () use ($uid, $sid): array {
            $return = ['status' => 0];
            $member = self::member($uid);
            if ($member === null) {
                return $return;
            }
            if (!self::isOpen($member)) {
                $return['status'] = -1;
                return $return;
            }

            $soul = OwnSoul::byId($sid);
            if ($soul === [] || $soul['uid'] !== $uid) {
                // 获取将魂信息失败
                $return['status'] = -2;
                return $return;
            }

            $level = $soul['level'] + 1;
            $levelup = OwnSoul::levelupRule($level, $soul['type'], $soul['quality']);
            if ($levelup['status'] !== 1) {
                // 2 等级已满，其余为获取升级规则失败
                $return['status'] = $levelup['status'] === 2 ? 2 : -3;
                return $return;
            }

            if ((int)$member['soul'] < $levelup['info']['price']) {
                // 原魂不足
                $return['status'] = 3;
                return $return;
            }

            if (OwnSoul::levelup($uid, $sid, $level, $levelup['info']['addition']) !== 1) {
                // 升级失败
                $return['status'] = -4;
                return $return;
            }

            if (OwnSoul::subSoul($uid, $levelup['info']['price'], 'soul', 'levelup')['status'] !== 1) {
                // 扣除原魂失败
                $return['status'] = -5;
                return $return;
            }

            $next = self::levelupRule($level + 1, $soul['type'], $soul['quality']);
            if ($next['status'] !== 1) {
                // 获取下个等级信息失败
                $return['status'] = -6;
                return $return;
            }

            $return['status'] = 1;
            $return['info'] = [
                'now' => $levelup['info'],
                'next' => $next['info'],
                'base_soul' => self::memberValue($uid, 'soul'),
            ];
            // 主线任务 16将魂等级提升至X级
            MainQuestTask::action($uid, 16, $level);
            return $return;
        });
    }

    /**
     * 转换将魂为原魂: c=soul&m=convert（sid 拥有将魂id，confirm 1 确认转换，否则只返回可得原魂；merge 合成界面）
     */
    public static function convert(Context $ctx): array
    {
        /**
         * -6 删除将魂失败
         * -5 获取背包信息失败
         * -4 添加原魂失败
         * -3 转换将魂失败
         * -2 获取将魂信息失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         */
        $uid = MemberHandler::resolveUid($ctx);
        $sid = (int)$ctx->input('sid', 0);
        $confirm = (int)$ctx->input('confirm', 0);
        $merge = (int)$ctx->input('merge', 0);
        return Member::locked($uid, static function () use ($uid, $sid, $confirm, $merge): array {
            $return = ['status' => 0];
            $member = self::member($uid);
            if ($member === null) {
                return $return;
            }
            if (!self::isOpen($member)) {
                $return['status'] = -1;
                return $return;
            }

            $soul = OwnSoul::byId($sid);
            if ($soul === [] || $soul['uid'] !== $uid) {
                // 获取将魂信息失败
                $return['status'] = -2;
                return $return;
            }

            $num = OwnSoul::convertNum($soul['level'], $soul['quality']);
            if ($num < 0) {
                // 转换将魂失败
                $return['status'] = -3;
                return $return;
            }

            if ($confirm === 1) {
                // 删除将魂
                if (OwnSoul::delSoul($uid, $sid) !== 1) {
                    $return['status'] = -6;
                    return $return;
                }
                // 添加原魂并扣除背包占用
                if (OwnSoul::addSoul($uid, $num, 1, 'soul', 'convert')['status'] !== 1) {
                    // 添加原魂失败
                    $return['status'] = -4;
                    return $return;
                }

                if ($soul['gid'] > 0) {
                    // 清空将领身上的将魂。旧版找不到槽位时拼出不存在的列 site 而报错退出；这里跳过（库里数据不一致时才会出现）
                    $general = OwnSoul::generalSoul($soul['gid']);
                    for ($i = 1; $i < 9; $i++) {
                        if (($general['site' . $i] ?? 0) === $sid) {
                            OwnSoul::updateGeneral($uid, $soul['gid'], ['site' . $i => 0]);
                            // 旧版装备中的将魂不占包裹，这里照旧版再减一次占用（index 会按包裹实数修正）
                            OwnSoul::subSouluse($uid);
                            break;
                        }
                    }
                }

                $return['pack'] = $merge > 0 ? self::mergeList($uid, 0) : OwnSoul::unequipList($uid);
            }

            $return['status'] = 1;
            $return['num'] = $num;
            $return['base_soul'] = self::memberValue($uid, 'soul');
            return $return;
        });
    }

    /**
     * 装备将魂: c=soul&m=equip（gid 将领id，sid 拥有将魂id，site 槽位 1~8）
     */
    public static function equip(Context $ctx): array
    {
        /**
         * -5 获取背包信息失败
         * -4 装备失败（含旧版模型返回的「位置未改变」4 与「将魂不是自己的」5：旧版控制器只映射 -2 / 2 / 3，其余都落到 -4）
         * -3 位置未开启
         * -2 获取将领信息失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         *  2 其他将领已装备
         *  3 同种类型只能装备一件
         *  4 位置未改变（旧版模型从不返回 -2，客户端收不到）
         */
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)$ctx->input('gid', 0);
        $sid = (int)$ctx->input('sid', 0);
        $site = (int)$ctx->input('site', 0);
        return Member::locked($uid, static function () use ($uid, $gid, $sid, $site): array {
            $return = ['status' => 0];
            $member = self::member($uid);
            if ($member === null) {
                return $return;
            }
            if (!self::isOpen($member)) {
                $return['status'] = -1;
                return $return;
            }

            $general = GeneralHandler::ownLegacyList($uid, $gid)[0] ?? null;
            if ($general === null) {
                // 获取将领信息失败
                $return['status'] = -2;
                return $return;
            }

            $maxsite = OwnSoul::unlockSite((int)$general['turn']);
            if ($site > $maxsite) {
                // 位置未开启
                $return['status'] = -3;
                return $return;
            }
            if ($site < 1) {
                // 旧版槽位小于 1 时先改了将魂的 gid，再写不存在的列 site{n} 报错退出；这里不写库，按装备失败返回
                $return['status'] = -4;
                return $return;
            }

            $equip = OwnSoul::equip($uid, $gid, $sid, $site);
            if ($equip['status'] !== 1) {
                $return['status'] = match ($equip['status']) {
                    -2 => 4,
                    2 => 2,
                    3 => 3,
                    default => -4,
                };
                return $return;
            }

            if ($equip['type'] === 4) {
                OwnSoul::subSouluse($uid);
            }

            $return['status'] = 1;
            // 旧版 sub_souluse 不更新内存里的玩家信息：返回的是操作前的占用数
            $return['souluse'] = (int)$member['souluse'];
            $return['pack'] = OwnSoul::unequipList($uid);
            return $return;
        });
    }

    /**
     * 卸下将魂: c=soul&m=unequip（gid 将领id，sid 拥有将魂id）
     */
    public static function unequip(Context $ctx): array
    {
        /**
         * -3 获取背包信息失败
         * -2 卸下失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         *  2 包裹已满
         */
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)$ctx->input('gid', 0);
        $sid = (int)$ctx->input('sid', 0);
        return Member::locked($uid, static function () use ($uid, $gid, $sid): array {
            $return = ['status' => 0];
            $member = self::member($uid);
            if ($member === null) {
                return $return;
            }
            if (!self::isOpen($member)) {
                // 等级不足
                $return['status'] = -1;
                return $return;
            }

            $use = (int)$member['souluse'];
            if ($use >= (int)$member['soulpack']) {
                // 包裹已满
                $return['status'] = 2;
                return $return;
            }

            if (OwnSoul::unequip($uid, $gid, $sid) !== 1) {
                $return['status'] = -2;
                return $return;
            }
            OwnSoul::addSouluse($uid);

            $return['status'] = 1;
            // 旧版 add_souluse 不更新内存里的玩家信息：返回的是操作前的占用数
            $return['souluse'] = $use;
            $return['pack'] = OwnSoul::unequipList($uid);
            return $return;
        });
    }

    /**
     * 获取背包解锁价格: c=soul&m=get_unlock_price（pos 解锁到第几格，含前 4 格）
     */
    public static function get_unlock_price(Context $ctx): array
    {
        /**
         * -2 价格获取失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0, 'price' => 0, 'number' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $pos = (int)$ctx->input('pos', 0);
        if (!self::isOpen($member)) {
            $return['status'] = -1;
            return $return;
        }

        $start = (int)$member['soulpack'] - 3;
        $end = $pos - 4;
        // 旧版 $end < $start 时设 -2 后没有 return，继续算价格为 0，再次得到 -2
        $gold = OwnSoul::packPrice($start, $end);
        if ($gold <= 0) {
            // 解锁价格获取失败
            $return['status'] = -2;
            return $return;
        }

        $return['status'] = 1;
        $return['price'] = $gold;
        $return['number'] = $end - $start + 1;
        return $return;
    }

    /**
     * 解锁背包: c=soul&m=unlock（pos 解锁到第几格，含前 4 格）
     */
    public static function unlock(Context $ctx): array
    {
        /**
         * -4 解锁失败
         * -3 扣金币失败
         * -2 价格获取失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         *  2 金币不足
         */
        $uid = MemberHandler::resolveUid($ctx);
        $pos = (int)$ctx->input('pos', 0);
        return Member::locked($uid, static function () use ($uid, $pos): array {
            $return = ['status' => 0];
            $member = self::member($uid);
            if ($member === null) {
                return $return;
            }
            if (!self::isOpen($member)) {
                $return['status'] = -1;
                return $return;
            }

            $start = (int)$member['soulpack'] - 3;
            $end = $pos - 4;
            // 旧版 $end < $start 时设 -2 后没有 return，继续算价格为 0，再次得到 -2
            $gold = OwnSoul::packPrice($start, $end);
            if ($gold <= 0) {
                // 解锁价格获取失败
                $return['status'] = -2;
                return $return;
            }
            // 元宝不足
            if ((int)$member['gold'] < $gold) {
                $return['status'] = 2;
                return $return;
            }
            if (!Member::deductCurrency($uid, gold: $gold)) {
                // 扣金币失败
                $return['status'] = -3;
                return $return;
            }
            $number = $end - $start + 1;
            if (OwnSoul::addSoulpack($uid, $number) !== 1) {
                // 解锁失败
                $return['status'] = -4;
                return $return;
            }
            // 消费统计记录
            StatPay::record($uid, 'unlock_pack', $gold);

            $soulpack = self::memberValue($uid, 'soulpack');
            $return['status'] = 1;
            $return['gold'] = self::memberValue($uid, 'gold');
            $return['pack'] = $soulpack;
            $return['pages'] = (int)ceil($soulpack / 12);
            return $return;
        });
    }

    /**
     * 未装备列表: c=soul&m=get_pack_info（page 页码，perpage 每页个数，type > 0 只列紫色将魂）
     */
    public static function get_pack_info(Context $ctx): array
    {
        /**
         * -2 获取背包信息失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        if (!self::isOpen($member)) {
            $return['status'] = -1;
            return $return;
        }

        $page = (int)$ctx->input('page', 0);
        $perpage = (int)$ctx->input('perpage', 0);
        $type = (int)$ctx->input('type', 0);

        $return['info'] = OwnSoul::unequipList((int)$member['uid'], $page, $perpage, $type > 0 ? 5 : 0);
        $return['status'] = 1;
        return $return;
    }

    /**
     * 将魂合成页面: c=soul&m=merge_index（page 页码）
     *
     * 列出满级的紫色将魂（旧版不检查开放等级）。
     */
    public static function merge_index(Context $ctx): array
    {
        /**
         * -2 获取升级信息失败
         * -1 获取背包信息失败
         *  0 失败
         *  1 成功
         */
        $uid = MemberHandler::resolveUid($ctx);
        $page = (int)$ctx->input('page', 0);
        return ['status' => 1, 'pack' => self::mergeList($uid, $page > 0 ? $page : 1)];
    }

    /**
     * 将魂合成: c=soul&m=merge_action（sid1 / sid2 两个满级紫色将魂，page 合成后返回的合成列表页码）
     *
     * 合成为 16 级红色将魂：两个将魂同类型时类型不变，否则在 1 ~ 8 中随机；两个将魂软删除（不减包裹占用，同旧版），
     * 新将魂进包裹（占用 + 1）。
     */
    public static function merge_action(Context $ctx): array
    {
        /**
         * -10 两个将魂的ID不能相同
         * -9 将魂品级达到紫色品级以上，不能合成 / 删除或卸载武将将魂信息失败
         * -8 新合成将魂合成规则信息失败
         * -7 新合成将魂基础信息错误
         * -6 将魂等级必须满级
         * -5 将魂品级必须为紫色
         * -4 获取将魂信息失败
         * -3 操作不要频繁（旧版锁只查不加，不会出现）
         * -2 参数传递错误
         * -1 等级不足
         *  0 失败
         *  1 成功
         */
        $uid = MemberHandler::resolveUid($ctx);
        $sid1 = (int)$ctx->input('sid1', 0);
        $sid2 = (int)$ctx->input('sid2', 0);
        $page = (int)$ctx->input('page', 0);
        return Member::locked($uid, static function () use ($uid, $sid1, $sid2, $page): array {
            $return = ['status' => 0];
            $member = self::member($uid);
            if ($member === null) {
                return $return;
            }
            // 等级不足
            if (!self::isOpen($member)) {
                return self::fail($return, -1, 'soul_level_error');
            }
            if ($sid1 <= 0 && $sid2 <= 0) {
                return self::fail($return, -2, 'soul_param_error');
            }
            if ($sid1 === $sid2) {
                // 两个将魂的ID不能相同
                return self::fail($return, -10, 'soul_notsame_error');
            }

            // 获取将魂信息（按 id 顺序）
            $souls = OwnSoul::byId([$sid1, $sid2]);
            if ($souls === []) {
                return self::fail($return, -4, 'soul_info_error');
            }
            for ($i = 0; $i <= 1; $i++) {
                if (!isset($souls[$i]['uid']) || $souls[$i]['uid'] !== $uid) {
                    // 获取将魂信息失败
                    return self::fail($return, -4, 'soul_info_error');
                }
                if ($souls[$i]['quality'] === 0) {
                    return self::fail($return, -4, 'soul_info_error');
                }
                if ($souls[$i]['quality'] < 5) {
                    // 将魂品级必须为紫色
                    return self::fail($return, -5, 'soul_quality_error');
                }
                if ($souls[$i]['quality'] > 5) {
                    // 将魂品级达到紫色品级以上，不能合成
                    return self::fail($return, -9, 'soul_nomerge_error');
                }
                // 获取升级信息：必须满级（旧版取不到升级规则时 ismax 未定义，同样按未满级）
                $souls[$i]['level'] += 1;
                $levelup = self::levelupRule($souls[$i]['level'], $souls[$i]['type'], $souls[$i]['quality']);
                if (($levelup['info']['ismax'] ?? 0) !== 1) {
                    return self::fail($return, -6, 'soul_maxlevel_error');
                }
            }

            // 根据 2 个将魂类型确定新将魂的类型：同类型不变；不同类型，随机一个类型
            $newLevel = 16;
            $newQuality = $souls[0]['quality'] + 1;
            $newType = $souls[0]['type'] === $souls[1]['type'] ? $souls[0]['type'] : mt_rand(1, 8);
            $baseSoul = OwnSoul::baseInfoByType($newType, $newQuality);
            if ($baseSoul === []) {
                // 新合成将魂基础信息错误
                return self::fail($return, -7, 'soul_newinfo_error');
            }

            // 处理合成的 2 个将魂：在武将身上的只清 gid（旧版不清将领的槽位），然后删除
            $now = time();
            foreach ($souls as $soulVal) {
                if (OwnSoul::updateSoul($uid, $soulVal['id'], ['gid' => 0, 'deleted_at' => date('Y-m-d H:i:s', $now)]) !== 1) {
                    // 删除或卸载武将将魂信息失败
                    return self::fail($return, -9, 'soul_del_error');
                }
            }

            // 增加合成后的新将魂
            $newSoul = OwnSoul::addHeroSoul($uid, (int)$baseSoul['id'], $newLevel);
            if ($newSoul === []) {
                // 新合成将魂合成规则信息失败
                return self::fail($return, -8, 'soul_newinfo_error');
            }
            // 写入合成日志
            OwnSoul::addMergeLog($uid, $sid1, $sid2, (int)$baseSoul['id'], $now);

            $return['status'] = 1;
            $return['new_soul'] = $newSoul;
            $return['pack'] = self::mergeList($uid, $page > 0 ? $page : 1);
            return $return;
        });
    }

    /**
     * 将领的将魂槽；没有时新建（add_general_soul），失败返回 null
     *
     * @return array<string, int>|null
     */
    public static function generalSites(int $uid, int $gid): ?array
    {
        $site = OwnSoul::generalSoul($gid);
        if ($site !== []) {
            return $site;
        }
        if (OwnSoul::addGeneralSoul($gid, $uid) !== 1) {
            return null;
        }
        $site = [];
        for ($i = 1; $i < 9; $i++) {
            $site['site' . $i] = 0;
        }
        return $site;
    }

    /**
     * 槽位上的将魂（get_soul_by_id，输出格式）
     *
     * @param array<string, int> $site
     * @return list<array<string, int|string>>
     */
    public static function equippedSouls(array $site): array
    {
        $soularr = [];
        for ($i = 1; $i < 9; $i++) {
            if ($site['site' . $i] > 0) {
                $soularr[$i] = $site['site' . $i];
            }
        }
        return $soularr === [] ? [] : OwnSoul::byId(array_values($soularr));
    }

    /**
     * site1~site8 的槽位信息：已解锁 {islock 0, info}；未解锁 {islock 1, unlock_level}；
     * 未解锁但装着将魂 {islock 2, unlock_level, info}（转生次数下降后的旧数据）
     *
     * @param array<string, int> $site
     * @param list<array<string, int|string>> $equip
     * @return array<int, array<string, mixed>>
     */
    public static function siteInfo(array $site, int $unlockSite, array $equip): array
    {
        $return = [];
        for ($i = 1; $i < 9; $i++) {
            $return[$i] = ['islock' => 0, 'info' => []];
            if ($i > $unlockSite) {
                // 获取位置解锁等级
                $return[$i] = ['islock' => 1, 'unlock_level' => OwnSoul::unlockLevel($i)];
            }
            if ($site['site' . $i] > 0) {
                foreach ($equip as $value) {
                    if ($value['id'] === $site['site' . $i]) {
                        $return[$i]['islock'] = $unlockSite < $i ? 2 : 0;
                        $return[$i]['info'] = $value;
                    }
                }
            }
        }
        return $return;
    }

    /**
     * 升级信息（移植控制器 _get_levelup_rule）
     *
     * info：merge（满级的紫色将魂为 1，可合成）、ismax（1 满级）、levelup（未满级时为下一级的 addition / price / level）。
     * status 1 成功，-1 获取升级信息失败。
     *
     * @return array{status: int, info: array<string, mixed>}
     */
    private static function levelupRule(int $level, int $type, int $quality): array
    {
        $return = ['status' => 0, 'info' => []];
        $levelup = OwnSoul::levelupRule($level, $type, $quality);
        $return['info']['merge'] = 0;
        if ($levelup['status'] === 1) {
            // 未满级
            $return['info']['ismax'] = 0;
            $return['info']['levelup'] = $levelup['info'];
        } elseif ($levelup['status'] === 2) {
            // 等级已满
            $return['info']['ismax'] = 1;
            $return['info']['levelup'] = [];
            if ($level > 15 && $quality === 5) {
                $return['info']['merge'] = 1;
            }
        } else {
            // 获取升级信息失败
            $return['status'] = -1;
            return $return;
        }
        $return['status'] = 1;
        return $return;
    }

    /**
     * 合成列表（merge_index 的数据；convert 的 merge 分支同样过滤）：紫色未装备将魂中去掉未满级的，按 id 升序
     *
     * 旧版 sort() 比较的是整行，各行字段相同时第一个字段 id 决定顺序。
     *
     * @return array<string, mixed>
     */
    private static function mergeList(int $uid, int $page): array
    {
        $unequip = OwnSoul::unequipList($uid, $page, 0, 5);
        foreach ($unequip['list'] as $key => $value) {
            $levelup = self::levelupRule($value['level'] + 1, $value['type'], $value['quality']);
            // 过滤没有达到满级的将魂
            if ((($levelup['info']['ismax'] ?? 0) !== 1 && $value['quality'] === 5) || $value['quality'] === 6) {
                unset($unequip['list'][$key]);
            }
        }
        usort($unequip['list'], static fn (array $a, array $b): int => $a['id'] <=> $b['id']);
        return $unequip;
    }

    /**
     * 失败返回（merge_action 带旧版语言包 msg）
     *
     * @param array<string, mixed> $return
     * @return array<string, mixed>
     */
    private static function fail(array $return, int $status, string $msg): array
    {
        $return['status'] = $status;
        $return['msg'] = self::LANG[$msg];
        return $return;
    }

    /**
     * 主公等级大于 50 开放（构造函数 _open）
     *
     * @param array<string, mixed> $member
     */
    private static function isOpen(array $member): bool
    {
        return (int)$member['level'] > self::OPEN_LEVEL;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        return DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
    }

    private static function memberValue(int $uid, string $column): int
    {
        return (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value($column) ?? 0);
    }
}
