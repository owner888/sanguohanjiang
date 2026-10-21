<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Http\Context;
use OmniPHP\Database\DB;
use OmniPHP\Cache\Redis;

final class StrengthenHandler
{
    /**
     * 装备强化主界面: c=strengthen&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $equips = DB::table('equipment')->where('uid', $uid)->get();

        if (empty($equips)) {
            // 自动补齐初始装备
            $now = time();
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
                'created_at' => $now,
            ]);
            $equips = DB::table('equipment')->where('uid', $uid)->get();
        }

        // 查询武将字典
        $generals = DB::table('general')->where('uid', $uid)->get();
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

            $list[$idx] = [
                'id' => $eid,
                'equipid' => (int)$item['equipid'],
                'name' => $item['name'],
                'type' => (int)$item['type'],
                'quality' => (int)$item['quality'],
                'level' => $curLevel,
                'gid' => $gid,
                'generalname' => $generalMap[$gid] ?? '',
                'cost_silver' => $costSilver,
                'bonus' => $curBonus,
                'next_bonus' => $nextBonus,
            ];
            $idx++;
        }

        return [
            'status' => 1,
            'rate' => 100, // 强化成功率 100%
            'cd' => 0,
            'list' => $list,
            'info' => $list[1] ?? [],
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

        if ((int)$member['silver'] < $costSilver) {
            return [
                'status' => -4,
                'message' => '银两不足，强化需要 ' . $costSilver . ' 银两',
            ];
        }

        $newLevel = $curLevel + 1;
        $remainSilver = (int)$member['silver'] - $costSilver;

        // 扣除银两并升级装备
        DB::table('member')->where('uid', $uid)->update(['silver' => $remainSilver]);
        DB::table('equipment')->where('id', $eid)->update(['level' => $newLevel]);

        Redis::del('player:' . $uid);

        $nickname = $member['nickname'] ?? ('主公' . $uid);
        \OmniPHP\WebSocket\Broadcaster::broadcast('marquee', [
            'content' => "神工现世！主公【{$nickname}】成功将【{$equip['name']}】强化至 +{$newLevel}！",
            'type' => 'system_broadcast',
        ]);

        $newBonus = GeneralHandler::calculateEquipBonus(array_merge($equip, ['level' => $newLevel]));

        return [
            'status' => 1,
            'message' => '强化成功',
            'id' => $eid,
            'level' => $newLevel,
            'cost_silver' => $costSilver,
            'silver' => $remainSilver,
            'gold' => 0,
            'rate' => 100,
            'power' => $newBonus['attack'] ?: ($newBonus['defense'] ?: $newBonus['hp']),
            'bonus' => $newBonus,
            'cd' => 0,
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

        $curLevel = max(1, (int)$equip['level']);
        $costSilver = $curLevel * 150;

        return [
            'status' => 1,
            'info' => [
                'id' => $eid,
                'name' => $equip['name'],
                'level' => $curLevel,
                'cost_silver' => $costSilver,
                'rate' => 100,
                'bonus' => GeneralHandler::calculateEquipBonus($equip),
                'next_bonus' => GeneralHandler::calculateEquipBonus(array_merge($equip, ['level' => $curLevel + 1])),
            ],
        ];
    }

    /**
     * 清理强化冷却: c=strengthen&m=clear_cd
     */
    public static function clear_cd(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => 'CD已清除',
        ];
    }
}
