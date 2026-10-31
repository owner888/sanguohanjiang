<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Equip;

use Hanjiang\Battle\LegacyStats;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Equip as EquipTemplate;
use Hanjiang\Model\DailyTask;
use Hanjiang\Model\Expostulation;
use Hanjiang\Model\MainQuestTask;
use Hanjiang\Model\StatPay;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 铁匠铺强化（移植旧版 controllers/strengthen.php 的 index / strengthen_info / strengthen_start / clear_cd）
 *
 * 强化冷却：旧版 vip_model::cd_status() 固定返回 1（免 CD），所以 strengthen_info 的 cd 恒为
 * {status: 0, cd: 0}、strengthen_start 的 cd 恒为 0，cd_model::update_strengthen 从不执行，
 * member_field.qiangdateline 不会增长；新版不存这两列，按同样结果输出。
 * 依赖未移植：双倍强化活动 activity_status('double_strengthen')（开启时成功率 100%）。
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
}
