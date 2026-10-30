<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Equip;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\GeneralHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class StrengthenHandler
{
    /**
     * 装备强化主界面: c=strengthen&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $equips = DB::table('equipment')->where('uid', $uid)->whereNull('deleted_at')->get();

        if (empty($equips)) {
            // 自动补齐初始装备
            DB::table('equipment')->insert([
                'uid' => $uid,
                'equipid' => 101,
                'name' => '青铜剑',
                'type' => 1,
                'quality' => 1,
                'level' => 1,
                'generalid' => 0,
                'number' => 1,
                'saleprice' => 50,
            ]);
            $equips = DB::table('equipment')->where('uid', $uid)->whereNull('deleted_at')->get();
        }

        // 查询武将字典
        $generals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->get();
        $generalMap = [];
        foreach ($generals as $g) {
            $generalMap[(int)$g['id']] = $g['name'];
        }

        $list = [];
        $idx = 1;
        foreach ($equips as $item) {
            $eid = (int)$item['id'];
            $gid = (int)$item['generalid'];
            $curLevel = max(1, (int)$item['level']);
            $costSilver = $curLevel * 150;

            $curBonus = GeneralHandler::calculateEquipBonus($item);
            $nextBonus = GeneralHandler::calculateEquipBonus(array_merge($item, ['level' => $curLevel + 1]));

            $itemData = [
                'id' => $eid,
                'equipid' => (int)$item['equipid'],
                'name' => $item['name'],
                'type' => (int)$item['type'],
                'quality' => (int)$item['quality'],
                'level' => $curLevel,
                'gid' => $gid,
                'hammer' => 0,
                'hammer_quality' => 0,
                'cost_silver' => $costSilver,
                'bonus' => $curBonus,
                'next_bonus' => $nextBonus,
            ];

            // 仅当装备穿在武将身上时才下发武将名称，未穿戴时不设该键
            if ($gid > 0 && isset($generalMap[$gid])) {
                $itemData['generalname'] = $generalMap[$gid];
                $itemData['generalid'] = $gid;
            }

            $list[$idx] = $itemData;
            $idx++;
        }

        $firstItem = $list[1] ?? [];
        $powerup = ($firstItem['bonus']['attack'] ?? 0) ?: (($firstItem['bonus']['defense'] ?? 0) ?: ($firstItem['bonus']['hp'] ?? 50));
        $firstDetail = [
            'id' => $firstItem['id'] ?? 1,
            'equipid' => $firstItem['equipid'] ?? 101,
            'name' => $firstItem['name'] ?? '青铜剑',
            'type' => $firstItem['type'] ?? 1,
            'quality' => $firstItem['quality'] ?? 1,
            'needlevel' => 1,
            'level' => $firstItem['level'] ?? 1,
            'power' => 50,
            'powerup' => $powerup,
            'gid' => $firstItem['gid'] ?? 0,
            'gidname' => $firstItem['generalname'] ?? '',
            'hammer' => 0,
            'hammer_quality' => 0,
        ];

        $infoObj = [
            'info' => $firstDetail,
            'rule' => [
                'rate' => 100,
                'silver' => $firstItem['cost_silver'] ?? 150,
                'gold' => 0,
            ],
            'cd' => [
                'status' => 0,
                'cd' => 0,
            ],
            // 向下兼容
            'cost_silver' => $firstItem['cost_silver'] ?? 150,
        ];

        return [
            'status' => 1,
            'rate' => 100, // 强化成功率 100%
            'cd' => 0,
            'list' => $list,
            'info' => $infoObj,
            'message' => 'ok',
        ];
    }

    /**
     * 执行强化: c=strengthen&m=strengthen_start
     */
    public static function strengthen_start(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $eid = (int)($ctx->input('id', $ctx->input('eid', 0)));

        if ($eid <= 0) {
            return [
                'status' => 0,
                'message' => '请选择需要强化的装备',
            ];
        }

        $equip = DB::table('equipment')->where('uid', $uid)->where('id', $eid)->first();
        if ($equip === null) {
            return [
                'status' => -2,
                'message' => '装备不存在',
            ];
        }

        $member = DB::table('member')->where('uid', $uid)->first();
        $curLevel = max(1, (int)$equip['level']);
        $costSilver = $curLevel * 150;

        // 原子安全扣减银两
        if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
            return [
                'status' => -4,
                'message' => '银两不足，强化需要 ' . $costSilver . ' 银两',
            ];
        }

        $newLevel = $curLevel + 1;
        $remainSilver = max(0, (int)$member['silver'] - $costSilver);

        DB::table('equipment')->where('id', $eid)->update(['level' => $newLevel]);
        Redis::del('player:' . $uid);

        $newBonus = GeneralHandler::calculateEquipBonus(array_merge($equip, ['level' => $newLevel]));
        $newPower = $newBonus['attack'] ?: ($newBonus['defense'] ?: $newBonus['hp']);

        return [
            'status' => 1,
            'message' => '强化成功',
            'level' => $newLevel,
            'newlevel' => $newLevel,
            'cost_silver' => $costSilver,
            'silver' => $costSilver,
            'all_silver' => $remainSilver,
            'gold' => 0,
            'all_gold' => (int)($member['gold'] ?? 0),
            'rate' => 100,
            'power' => $newPower,
            'newpower' => $newPower,
            'bonus' => $newBonus,
            'cd' => 0,
            'baoji' => 1,
        ];
    }

    /**
     * 获取单件强化信息: c=strengthen&m=strengthen_info
     */
    public static function strengthen_info(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $eid = (int)($ctx->input('id', $ctx->input('eid', 0)));

        $equip = DB::table('equipment')->where('uid', $uid)->where('id', $eid)->first();
        if ($equip === null) {
            return [
                'status' => 0,
                'message' => '装备不存在',
            ];
        }

        $gid = (int)$equip['generalid'];
        $gidName = '';
        if ($gid > 0) {
            $gen = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();
            $gidName = $gen['name'] ?? '';
        }

        $curLevel = max(1, (int)$equip['level']);
        $costSilver = $curLevel * 150;
        $curBonus = GeneralHandler::calculateEquipBonus($equip);
        $powerup = ($curBonus['attack'] ?? 0) ?: (($curBonus['defense'] ?? 0) ?: ($curBonus['hp'] ?? 50));

        $detail = [
            'id' => $eid,
            'equipid' => (int)$equip['equipid'],
            'name' => $equip['name'],
            'type' => (int)$equip['type'],
            'quality' => (int)$equip['quality'],
            'needlevel' => 1,
            'level' => $curLevel,
            'power' => 50,
            'powerup' => $powerup,
            'gid' => $gid,
            'gidname' => $gidName,
            'hammer' => 0,
            'hammer_quality' => 0,
            'cost_silver' => $costSilver,
            'rate' => 100,
            'bonus' => $curBonus,
            'next_bonus' => GeneralHandler::calculateEquipBonus(array_merge($equip, ['level' => $curLevel + 1])),
        ];

        return [
            'status' => 1,
            'info' => $detail,
            'rule' => [
                'rate' => 100,
                'silver' => $costSilver,
                'gold' => 0,
            ],
            'cd' => [
                'status' => 0,
                'cd' => 0,
            ],
            'message' => 'ok',
        ];
    }

    /**
     * 清理强化冷却: c=strengthen&m=clear_cd
     */
    public static function clear_cd(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => '冷却已清除',
        ];
    }
}
