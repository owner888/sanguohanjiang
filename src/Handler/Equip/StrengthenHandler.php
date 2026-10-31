<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Equip;

use Hanjiang\Battle\LegacyStats;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Equip as EquipTemplate;
use Hanjiang\MasterData\EquipEssence;
use Hanjiang\MasterData\EquipStrengachieve;
use Hanjiang\Model\DailyTask;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\Expostulation;
use Hanjiang\Model\Member;
use Hanjiang\Model\MainQuestTask;
use Hanjiang\Model\StatPay;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 铁匠铺强化（移植旧版 controllers/strengthen.php 的 index / strengthen_info / strengthen_start / clear_cd）
 *
 * 强化冷却：旧版 vip_model::cd_status() 固定返回 1（免 CD），所以 strengthen_info 的 cd 恒为
 * {status: 0, cd: 0}、strengthen_start 的 cd 恒为 0，cd_model::update_strengthen 从不执行，
 * member_field.qiangstatus / qiangdateline 只会被 VIP 充值清 cd 写成 0，按同样结果输出。
 * 依赖未移植：双倍强化活动 activity_status('double_strengthen')（开启时成功率 100%）。
 *
 * 强化成就（achievement / achieve_unlock / revert_achieve，客户端 strongachievepop.js）：MasterData EquipStrengachieve 每级成就按
 * 装备类型消耗装备精华（equipment type 100 行）解锁，必须按顺序解锁，已解锁的成就 id 逗号拼接记在 equipment.aid；还原时清空 aid、
 * 返还全部精华并记 revert_achieve_log。照旧版保留的写法：achievement 不传 id 时取第一件装备；还原返还精华按 item{装备类型}
 * 取（解锁按成就表里与装备类型相同的那一列）；revert_achieve 的 Redis 锁 strengthen_revert_achieve_{uid} 已存在时删锁回 -1。
 * 与旧版的差别：achieve_unlock / revert_achieve 在 Member::locked（玩家行锁）里执行，精华用条件更新扣减（旧版按读到的数量写回）；
 * 多个 aid 按 id 查表（同 Battle\TeamBuilder）；旧版 achievement 核对将领 eid 与装备不符时清 gid，新版穿戴关系只存在
 * equipment.generalid，无需核对。
 */
final class StrengthenHandler
{
    /** VIP 达到该等级时强化成功可触发暴击（+2 级） */
    private const int CRIT_VIP = 5;

    /**
     * 装备强化主界面: c=strengthen&m=index（旧版 controllers/strengthen.php:index）
     *
     * list 为全部类型 1~5 的非碎片装备（含已穿戴），排序同旧版 get_own_list(needlevel)：
     * gid DESC, type ASC, quality DESC, hammer DESC, level DESC, needlevel ASC；info 为第一件的 strengthen_info。
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0, 'list' => [], 'info' => []];

        $rows = DB::table('equipment')
            ->where('uid', $uid)
            ->where('chip', 0)
            ->whereIn('type', [1, 2, 3, 4, 5])
            ->whereNull('deleted_at')
            ->orderBy('generalid', 'DESC')
            ->orderBy('type', 'ASC')
            ->orderBy('quality', 'DESC')
            ->orderBy('hammer', 'DESC')
            ->orderBy('level', 'DESC')
            ->orderBy('needlevel', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();
        if ($rows === []) {
            $return['status'] = -1;
            return $return;
        }

        $gids = [];
        foreach ($rows as $val) {
            if ((int)$val['generalid'] > 0) {
                $gids[] = (int)$val['generalid'];
            }
        }
        $generals = [];
        if ($gids !== []) {
            foreach (DB::table('general')->where('uid', $uid)->whereIn('id', array_values(array_unique($gids)))->whereNull('deleted_at')->get() as $g) {
                $generals[(int)$g['id']] = $g;
            }
        }

        $list = [];
        $num = 1;
        foreach ($rows as $val) {
            $gid = (int)$val['generalid'];
            $promote = LegacyStats::refinePromote((int)$val['hammer']);
            $info = [
                'id' => (int)$val['id'],
                'gid' => $gid,
                'type' => (int)$val['type'],
                'level' => (int)$val['level'],
                'equipid' => (int)$val['equipid'],
                'quality' => (int)$val['quality'],
                'hammer' => (int)$val['hammer'],
                'hammer_quality' => $promote === null ? 0 : (int)$promote['type'],
            ];
            // 旧版还会核对将领 eid{site} 是否就是本装备、不符时清零 gid；新版穿戴关系只存在 equipment.generalid，无需核对
            if ($gid > 0 && isset($generals[$gid])) {
                $info['generalname'] = (string)$generals[$gid]['name'];
                $info['generalid'] = (int)$generals[$gid]['generalid'];
            }
            $list[$num] = $info;
            $num++;
        }

        $return['status'] = 1;
        $return['list'] = $list;
        $return['info'] = self::strengthenInfo($uid, (int)$list[1]['id']);
        return $return;
    }

    /**
     * 获取单件强化信息: c=strengthen&m=strengthen_info（旧版 controllers/strengthen.php:strengthen_info）
     */
    public static function strengthen_info(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return self::strengthenInfo($uid, (int)$ctx->input('id', 0));
    }

    /**
     * 执行强化: c=strengthen&m=strengthen_start（旧版 controllers/strengthen.php:strengthen_start）
     *
     * 状态码：-2 装备不存在，-3 强化等级已达主公等级，-4 银币不足，-5 元宝不足（ratetype=1 补满概率），-6 强化失败（仍扣银币）
     * 成功率取 equip_level[下一级].rate；ratetype=1 时花 equip_level.gold 元宝必定成功。
     * VIP 5 及以上、当前强化等级 > 0 时按 equip_level.powerup 概率暴击，多升 1 级（不超过主公等级）。
     */
    public static function strengthen_start(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $itemid = (int)$ctx->input('id', 0);
        $ratetype = (int)$ctx->input('ratetype', 0);
        $return = ['status' => 0, 'cd' => 0, 'gold' => 0, 'rate' => 0, 'power' => 0, 'silver' => 0];

        // 旧版 get_info 不按 uid 过滤（他人装备会扣银币但不改装备）；这里只认本人装备。
        // 卷轴等非装备（type > 5）旧版按 0 系数照常「强化」，客户端列表里不会出现，这里同样按装备不存在处理。
        $item = DB::table('equipment')->where('uid', $uid)->where('id', $itemid)->whereNull('deleted_at')->first();
        if ($item === null || LegacyStats::itemSite((int)$item['type']) === null) {
            $return['status'] = -2;
            return $return;
        }

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $memberLevel = (int)($member['level'] ?? 0);
        $level = (int)$item['level'];
        if ($level >= $memberLevel) {
            $return['status'] = -3;
            return $return;
        }

        $type = (int)$item['type'];
        $quality = (int)$item['quality'];
        $power = (int)$item['power'];
        $rule = LegacyStats::strengthenRule($type, $quality, $power, $level);
        if ((int)($member['silver'] ?? 0) < $rule['silver']) {
            $return['status'] = -4;
            return $return;
        }

        if ($ratetype === 1) {
            // 使用元宝补满概率
            if ((int)($member['gold'] ?? 0) < $rule['gold']) {
                $return['status'] = -5;
                return $return;
            }
            if ($rule['gold'] > 0 && !MemberHandler::deductCurrency($uid, gold: $rule['gold'])) {
                $return['status'] = -5;
                return $return;
            }
            // 消费统计记录
            StatPay::record($uid, 'qianghua_rate', $rule['gold']);
        } elseif (mt_rand(1, 100) > $rule['rate']) {
            // 强化失败：照扣银币
            MemberHandler::deductCurrency($uid, silver: $rule['silver']);
            $after = DB::table('member')->where('uid', $uid)->first();
            $return['status'] = -6;
            $return['all_silver'] = (int)($after['silver'] ?? 0);
            $return['all_gold'] = (int)($after['gold'] ?? 0);
            $return['cd'] = 0;
            // 日常任务(8是强化对应的类型)
            if ($memberLevel >= DailyTask::OPEN_LEVEL) {
                DailyTask::action($uid, DailyTask::TYPE_STRENGTHEN);
            }
            // 帮派任务(谏言：1是强化对应的类型)
            self::expostulation($uid);
            return $return;
        }

        if (!MemberHandler::deductCurrency($uid, silver: $rule['silver'])) {
            $return['status'] = -4;
            return $return;
        }

        $baoji = 0;
        $newLevel = $level + 1;
        if ($level > 0 && (int)($member['vip'] ?? 0) >= self::CRIT_VIP) {
            if (mt_rand(1, 100) <= (int)($rule['baoji'] ?? 0) && $level + 2 <= $memberLevel) {
                // 强化暴击：按下一级再取一次规则，powerup 取 +2 级的值
                $baoji = 1;
                $rule = LegacyStats::strengthenRule($type, $quality, $power, $level + 1);
                $newLevel++;
            }
        }

        // 主线任务 2任意装备强化到X级
        MainQuestTask::action($uid, 2, $newLevel);

        // 强化后售价（旧版 strengthen.php:saleprice）：模板售价 + 累计强化花费 * 0.6，
        // 浮点值直接写入 INT 列由 MySQL 四舍五入（与 equipment_model::get_saleprice 的 ceil 不同）
        $data = ['level' => $newLevel, 'powerup' => $rule['power']];
        $tpl = EquipTemplate::ROWS[(int)$item['equipid']] ?? null;
        if ($tpl !== null) {
            $saleprice = $tpl['saleprice'] + LegacyStats::strengthenTotalCost($type, $quality, $newLevel) * 0.6;
            if ($saleprice) {
                $data['saleprice'] = (int)round($saleprice);
            }
        }
        DB::table('equipment')->where('uid', $uid)->where('id', $itemid)->update($data);

        $newrule = LegacyStats::strengthenRule($type, $quality, $power, $newLevel);
        $display = LegacyStats::displayPowerup($power, $rule['power'], $type, (int)$item['hammer'], (string)$item['aid']);
        $after = DB::table('member')->where('uid', $uid)->first();

        $return = $newrule;
        $return['all_gold'] = (int)($after['gold'] ?? 0);
        $return['all_silver'] = (int)($after['silver'] ?? 0);
        $return['newlevel'] = $newLevel;
        $return['newpower'] = $display['powerup'];
        $return['baoji'] = $baoji;
        $return['status'] = 1;
        $return['cd'] = 0;
        // 日常任务(8是强化对应的类型)
        if ($memberLevel >= DailyTask::OPEN_LEVEL) {
            DailyTask::action($uid, DailyTask::TYPE_STRENGTHEN);
        }
        // 帮派任务(谏言：1是强化对应的类型)
        self::expostulation($uid);
        return $return;
    }

    /**
     * 帮派谏言任务 1（强化装备，有国家时 expostulation_model->action(1)）
     */
    private static function expostulation(int $uid): void
    {
        $country = (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('country');
        if ($country > 0) {
            Expostulation::action($country, 1);
        }
    }

    /**
     * 清理强化冷却: c=strengthen&m=clear_cd（旧版 controllers/strengthen.php:clear_cd）
     *
     * 旧版按 cd_model::get_strengthen 的剩余秒数收 ceil(cd / 300) 元宝；因 cd_status() 恒为 1，
     * 强化从不累加 CD，剩余秒数恒为 0，旧版总是返回 -1（不需要清除）。
     */
    public static function clear_cd(Context $ctx): array
    {
        return [
            'gold' => 0,
            'status' => -1,
        ];
    }

    /**
     * 单件强化信息（旧版 controllers/strengthen.php:strengthen_info）
     *
     * @return array<string, mixed>
     */
    private static function strengthenInfo(int $uid, int $itemid): array
    {
        $return = ['status' => 0, 'info' => [], 'rule' => [], 'cd' => 0];

        $item = DB::table('equipment')->where('uid', $uid)->where('id', $itemid)->whereNull('deleted_at')->first();
        if ($item === null) {
            $return['status'] = -1;
            return $return;
        }

        $type = (int)$item['type'];
        $info = [
            'id' => (int)$item['id'],
            'gid' => (int)$item['generalid'],
            'name' => (string)$item['name'],
            'type' => $type,
            'quality' => (int)$item['quality'],
            'needlevel' => (int)$item['needlevel'],
            'power' => (int)$item['power'],
            'powerup' => (int)$item['powerup'],
            'level' => (int)$item['level'],
            'chip' => (int)$item['chip'],
            'equipid' => (int)$item['equipid'],
            'hammer' => (int)$item['hammer'],
            'aid' => (string)$item['aid'],
        ];
        if ($info['gid'] > 0) {
            $general = DB::table('general')->where('uid', $uid)->where('id', $info['gid'])->whereNull('deleted_at')->first();
            $info['gidname'] = $general['name'] ?? null;
        }
        $display = LegacyStats::displayPowerup($info['power'], $info['powerup'], $type, $info['hammer'], $info['aid']);
        $info['powerup'] = $display['powerup'];
        $info['hammer_quality'] = $display['hammer_quality'];

        $return['status'] = 1;
        $return['rule'] = LegacyStats::strengthenRule($type, $info['quality'], $info['power'], $info['level']);
        $return['info'] = $info;
        // 旧版 cd_status() 恒为 1：免 CD
        $return['cd'] = ['status' => 0, 'cd' => 0];
        return $return;
    }

    /** 旧版语言包（heaven_lang.php strengthen_error*、strengthen_lang.php revert_error*） */
    private const array ACH_LANG = [
        'strengthen_error1' => '强化等级成就信息错误',
        'strengthen_error2' => '装备精华信息错误',
        'strengthen_error3' => '参数错误',
        'strengthen_error4' => '玩家装备不存在',
        'strengthen_error5' => '玩家装备此成就已经解锁',
        'strengthen_error6' => '精华材料数量不足',
        'strengthen_error7' => '装备成就解锁必须按照顺序解锁',
        'revert_error2' => '参数错误',
        'revert_error3' => '玩家装备不存在',
        'revert_error4' => '此装备没有强化成就',
        'revert_error5' => '获取装备强化信息错误',
        'revert_error6' => '获取精华信息失败',
        'revert_error7' => '还原装备强化成就失败',
    ];

    /**
     * 强化成就列表: c=strengthen&m=achievement&id=
     */
    public static function achievement(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        $data = self::achievementData($uid, $id);
        $return = ['status' => 1, 'itemList' => $id > 0 ? [] : $data['itemList'], 'itemInfo' => $data['itemInfo'], 'achieveList' => $data['achieveList']];
        return $return;
    }

    /**
     * 强化成就解锁: c=strengthen&m=achieve_unlock&id=（装备 id）&aid=（成就 id）
     */
    public static function achieve_unlock(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        $aid = (int)$ctx->input('aid', 0);
        return Member::locked($uid, static function () use ($uid, $id, $aid): array {
            /**
             * -2 装备精华信息错误
             * -1 强化等级成就信息错误
             *  0 请求失败
             *  1 请求成功
             *  2 参数错误
             *  3 玩家装备不存在
             *  4 玩家装备此成就已经解锁
             *  5 精华材料数量不足
             *  6 装备成就解锁必须按照顺利解锁
             */
            $return = ['status' => 0];
            if ($id <= 0 || $aid <= 0) {
                return self::achFail($return, 2, 'strengthen_error3');
            }
            $base = EquipStrengachieve::ROWS[$aid] ?? null;
            if ($base === null) {
                return self::achFail($return, -1, 'strengthen_error1');
            }
            $equip = DB::table('equipment')->select('id', 'type', 'aid')->where('uid', $uid)->where('id', $id)->whereNull('deleted_at')->first();
            if ($equip === null) {
                return self::achFail($return, 3, 'strengthen_error4');
            }
            $achArr = [];
            if ((string)$equip['aid'] !== '') {
                $achArr = explode(',', (string)$equip['aid']);
                if (in_array((string)$aid, $achArr, true)) {
                    return self::achFail($return, 4, 'strengthen_error5');
                }
                // 必须按照顺序解锁
                $sorted = $achArr;
                rsort($sorted);
                if ($aid - (int)$sorted[0] !== 1) {
                    return self::achFail($return, 6, 'strengthen_error7');
                }
            } elseif ($aid !== 1) {
                return self::achFail($return, 6, 'strengthen_error7');
            }
            // 此装备成就加成信息（成就表里与装备类型相同的那一列）
            $typeId = 0;
            for ($i = 1; $i <= 5; $i++) {
                if ((int)$equip['type'] === $base['type' . $i]) {
                    $typeId = $i;
                }
            }
            $essId = $base['item' . $typeId] ?? 0;
            $essNum = $base['item_num' . $typeId] ?? 0;
            $essence = EquipEssence::ROWS[$essId] ?? null;
            if ($essence === null) {
                return self::achFail($return, -2, 'strengthen_error2');
            }
            $material = Equipment::towerItem($uid, $essId);
            $lastNum = (int)($material['number'] ?? 0) - $essNum;
            if ($material === [] || $lastNum < 0 || Equipment::subTowerItem($uid, $essId, $essNum) !== 1) {
                // 精华材料数量不足
                $return = self::achFail($return, 5, 'strengthen_error6');
                $return['essInfo'] = ['quality' => $essence['quality'], 'name' => $essence['name']];
                return $return;
            }
            $return['status'] = 1;
            $return['m_num'] = $lastNum;
            // 更新装备成就
            $achArr[] = (string)$aid;
            DB::table('equipment')->where('uid', $uid)->where('id', $id)->whereNull('deleted_at')
                ->update(['aid' => count($achArr) <= 1 ? (string)$aid : implode(',', $achArr)]);
            return $return;
        });
    }

    /**
     * 还原强化成就: c=strengthen&m=revert_achieve&id=
     */
    public static function revert_achieve(Context $ctx): array
    {
        /**
         *  0 请求失败
         *  1 请求成功
         * -1 参数错误
         * -2 玩家装备不存在
         * -3 该装备没有强化成就
         * -4 装备强化信息错误
         * -5 获取精华失败
         * -6 清除装备强化成就失败
         */
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        $lock = 'strengthen_revert_achieve_' . $uid;
        if (!Redis::instance()->set($lock, '1', ['nx', 'ex' => 30])) {
            Redis::del($lock);
            return self::achFail(['status' => 0], -1, 'revert_error2');
        }
        try {
            return Member::locked($uid, static function () use ($uid, $id): array {
                $return = ['status' => 0];
                if ($id <= 0) {
                    return self::achFail($return, -1, 'revert_error2');
                }
                $equip = DB::table('equipment')->select('id', 'type', 'aid')->where('uid', $uid)->where('id', $id)->whereNull('deleted_at')->first();
                if ($equip === null) {
                    return self::achFail($return, -2, 'revert_error3');
                }
                $aid = (string)$equip['aid'];
                if ($aid === '') {
                    return self::achFail($return, -3, 'revert_error4');
                }
                $ids = array_map('intval', explode(',', $aid));
                sort($ids);
                $rows = array_values(array_filter(array_map(static fn (int $v): ?array => EquipStrengachieve::ROWS[$v] ?? null, array_unique($ids))));
                if ($rows === []) {
                    return self::achFail($return, -4, 'revert_error5');
                }
                // 按装备类型返还精华（旧版取 item{装备类型} 列）
                $type = (int)$equip['type'];
                $add = [];
                foreach ($rows as $val) {
                    $item = $val['item' . $type] ?? 0;
                    $add[$item] = ($add[$item] ?? 0) + ($val['item_num' . $type] ?? 0);
                }
                // 清除装备的强化成就
                $affected = DB::table('equipment')->where('uid', $uid)->where('id', $id)->whereNull('deleted_at')->update(['aid' => '']);
                if ($affected <= 0) {
                    return self::achFail($return, -6, 'revert_error7');
                }
                // 返回消耗的精华
                $now = time();
                $turn = [];
                foreach ($add as $key => $val) {
                    $essence = EquipEssence::ROWS[$key] ?? null;
                    if ($essence === null) {
                        continue;
                    }
                    $essence['value'] = $val;
                    Equipment::addTowerItem($uid, $key, $val);
                    // 添加还原强化成就日志
                    DB::table('revert_achieve_log')->insert([
                        'uid' => $uid,
                        'equipid' => $id,
                        'aid' => $aid,
                        'type' => $key,
                        'num' => $val,
                        'dateline' => $now,
                    ]);
                    $turn[] = $essence;
                }
                // 返回强化成就列表
                $data = self::achievementData($uid, $id);
                $return['achieveList'] = $data['achieveList'];
                $return['itemInfo'] = $data['itemInfo'];
                $return['jinghua'] = $turn;
                $return['status'] = 1;
                return $return;
            });
        } finally {
            Redis::del($lock);
        }
    }

    /**
     * 强化成就数据（achievement($type)）：装备列表（id / aid / quality / level / equipid / type / gid，已穿戴的带将领名）、
     * 选中的装备（不传 id 取第一件）与该装备类型的各级成就（精华需求、已拥有精华数、是否解锁）
     *
     * @return array{itemList: list<array<string, mixed>>, itemInfo: array<string, mixed>, achieveList: list<array<string, mixed>>}
     */
    private static function achievementData(int $uid, int $id): array
    {
        $rows = DB::table('equipment')->select('id', 'aid', 'quality', 'level', 'equipid', 'type', 'generalid')
            ->where('uid', $uid)->where('chip', 0)->whereIn('type', [1, 2, 3, 4, 5])->whereNull('deleted_at')
            ->orderBy('generalid', 'DESC')->orderBy('type', 'ASC')->orderBy('quality', 'DESC')->orderBy('hammer', 'DESC')
            ->orderBy('level', 'DESC')->orderBy('needlevel', 'ASC')->orderBy('id', 'ASC')->get();
        $list = [];
        $gids = [];
        foreach ($rows as $row) {
            $item = [
                'id' => (int)$row['id'],
                'aid' => (string)$row['aid'],
                'quality' => (int)$row['quality'],
                'level' => (int)$row['level'],
                'equipid' => (int)$row['equipid'],
                'type' => (int)$row['type'],
                'gid' => (int)$row['generalid'],
            ];
            $list[] = $item;
            $gids[] = $item['gid'];
        }
        $names = [];
        $gids = array_values(array_unique(array_filter($gids)));
        if ($gids !== []) {
            foreach (DB::table('general')->select('id', 'name')->where('uid', $uid)->whereIn('id', $gids)->whereNull('deleted_at')->get() as $g) {
                $names[(int)$g['id']] = (string)$g['name'];
            }
        }
        foreach ($list as $key => $val) {
            if (isset($names[$val['gid']])) {
                $list[$key]['generalname'] = $names[$val['gid']];
            }
        }
        $itemInfo = [];
        $achieveList = [];
        if ($list !== []) {
            if ($id > 0) {
                foreach ($list as $own) {
                    if ($own['id'] === $id) {
                        $itemInfo = $own;
                    }
                }
            } else {
                $itemInfo = $list[0];
            }
            $achIds = isset($itemInfo['aid']) && $itemInfo['aid'] !== '' ? explode(',', $itemInfo['aid']) : [];
            $typeId = 0;
            $essIds = [];
            foreach (EquipStrengachieve::ROWS as $value) {
                for ($i = 1; $i <= 5; $i++) {
                    if (isset($itemInfo['type']) && $itemInfo['type'] === $value['type' . $i]) {
                        $typeId = $i;
                    }
                }
                $ess = EquipEssence::ROWS[$value['item' . $typeId] ?? 0] ?? [];
                $ess['num'] = $value['item_num' . $typeId] ?? null;
                $ess['all_num'] = 0;
                unset($ess['desc'], $ess['price']);
                $essIds[] = $ess['id'] ?? 0;
                $achieveList[] = [
                    'id' => $value['id'],
                    'level' => $value['level'],
                    'add_type' => $value['add_type' . $typeId] ?? null,
                    'add_per' => $value['add_per' . $typeId] ?? null,
                    'item' => $ess,
                    'lock' => in_array((string)$value['id'], $achIds, true) ? 1 : 0,
                ];
            }
            // 玩家当前身上材料数量
            $owned = [];
            foreach (Equipment::towerItem($uid, array_values(array_unique($essIds)), 2) as $row) {
                $owned[(int)$row['equipid'] - 100] = (int)$row['number'];
            }
            foreach ($achieveList as $key => $achieve) {
                $essId = $achieve['item']['id'] ?? 0;
                if (($owned[$essId] ?? 0) > 0) {
                    $achieveList[$key]['item']['all_num'] = $owned[$essId];
                }
            }
        }
        return ['itemList' => $list, 'itemInfo' => $itemInfo, 'achieveList' => $achieveList];
    }

    /**
     * @param array<string, mixed> $return
     * @return array<string, mixed>
     */
    private static function achFail(array $return, int $status, string $lang): array
    {
        $return['status'] = $status;
        $return['msg'] = self::ACH_LANG[$lang];
        return $return;
    }
}
