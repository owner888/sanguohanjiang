<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class SoulHandler
{
    /**
     * 将魂孔位类型字典
     *
     * @var array<int, array<string, mixed>>
     */
    public const array SLOTS = [
        1 => ['id' => 1, 'name' => '天命青龙·生命魂', 'type' => 'hp', 'base' => 200, 'unit' => '点生命'],
        2 => ['id' => 2, 'name' => '霸道白虎·物攻魂', 'type' => 'attack', 'base' => 40, 'unit' => '点攻击'],
        3 => ['id' => 3, 'name' => '玄天玄武·物防魂', 'type' => 'defense', 'base' => 25, 'unit' => '点防御'],
        4 => ['id' => 4, 'name' => '涅槃朱雀·暴击魂', 'type' => 'crit', 'base' => 1, 'unit' => '%暴击率'],
        5 => ['id' => 5, 'name' => '破军勾陈·破甲魂', 'type' => 'attack', 'base' => 35, 'unit' => '点穿甲攻击'],
        6 => ['id' => 6, 'name' => '金刚腾蛇·免伤魂', 'type' => 'defense', 'base' => 20, 'unit' => '点免伤防御'],
        7 => ['id' => 7, 'name' => '鹰眼太阴·精准魂', 'type' => 'hit', 'base' => 1, 'unit' => '%命中率'],
        8 => ['id' => 8, 'name' => '幻影太阳·闪避魂', 'type' => 'dodge', 'base' => 1, 'unit' => '%闪避率'],
    ];

    /**
     * 获取或初始化武将的将魂盘
     *
     * @return array<string, mixed>
     */
    public static function getOrInitGeneralSoul(int $uid, int $gid): array
    {
        $soul = DB::table('general_soul')->where('uid', $uid)->where('gid', $gid)->first();
        if ($soul !== null) {
            return $soul;
        }

        $now = time();
        DB::table('general_soul')->insert([
            'uid' => $uid,
            'gid' => $gid,
            'slot1' => 1, // 初始激活生命魂 1 级
            'slot2' => 0,
            'slot3' => 0,
            'slot4' => 0,
            'slot5' => 0,
            'slot6' => 0,
            'slot7' => 0,
            'slot8' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('general_soul')->where('uid', $uid)->where('gid', $gid)->first() ?? [];
    }

    /**
     * 计算将魂额外提供的属性加成
     *
     * @return array{hp: int, attack: int, defense: int}
     */
    public static function calculateSoulBonus(int $uid, int $gid): array
    {
        $soul = DB::table('general_soul')->where('uid', $uid)->where('gid', $gid)->first();
        if ($soul === null) {
            return ['hp' => 0, 'attack' => 0, 'defense' => 0];
        }

        $addHp = (int)$soul['slot1'] * 200;
        $addAtk = ((int)$soul['slot2'] * 40) + ((int)$soul['slot5'] * 35);
        $addDef = ((int)$soul['slot3'] * 25) + ((int)$soul['slot6'] * 20);

        return [
            'hp' => $addHp,
            'attack' => $addAtk,
            'defense' => $addDef,
        ];
    }

    /**
     * 将魂星盘主界面: c=soul&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('gid', 0));

        if ($gid <= 0) {
            $firstGen = DB::table('general')->where('uid', $uid)->first();
            $gid = (int)($firstGen['id'] ?? 1);
        }

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->first();
        if ($general === null) {
            return [
                'status' => -1,
                'message' => '武将不存在',
            ];
        }

        $member = DB::table('member')->where('uid', $uid)->first();
        $userSoul = (int)($member['soul'] ?? 100);

        $soulRecord = self::getOrInitGeneralSoul($uid, $gid);

        $slots = [];
        for ($i = 1; $i <= 8; $i++) {
            $cfg = self::SLOTS[$i];
            $curLevel = (int)($soulRecord['slot' . $i] ?? 0);
            $cost = max(50, $curLevel * 50);

            $slots[$i] = [
                'slot' => $i,
                'name' => $cfg['name'],
                'type' => $cfg['type'],
                'level' => $curLevel,
                'cost' => $cost,
                'current_value' => $curLevel * $cfg['base'] . $cfg['unit'],
                'next_value' => ($curLevel + 1) * $cfg['base'] . $cfg['unit'],
            ];
        }

        return [
            'status' => 1,
            'gid' => $gid,
            'general_name' => $general['name'],
            'user_soul' => $userSoul,
            'slots' => $slots,
            'message' => 'ok',
        ];
    }

    /**
     * 将魂命盘孔位升级/激活: c=soul&m=upgrade
     */
    public static function upgrade(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('gid', 1));
        $slot = (int)($ctx->input('slot', 1));

        if ($slot < 1 || $slot > 8) {
            return [
                'status' => 0,
                'message' => '无效的将魂孔位编号',
            ];
        }

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->first();
        if ($general === null) {
            return [
                'status' => -1,
                'message' => '武将不存在',
            ];
        }

        $soulRecord = self::getOrInitGeneralSoul($uid, $gid);
        $curLevel = (int)($soulRecord['slot' . $slot] ?? 0);

        if ($curLevel >= 10) {
            return [
                'status' => 0,
                'message' => '该将魂孔位已达到最高等级 10 级',
            ];
        }

        $cost = max(50, $curLevel * 50);
        $member = DB::table('member')->where('uid', $uid)->first();
        $userSoul = (int)($member['soul'] ?? 0);

        if ($userSoul < $cost) {
            return [
                'status' => -2,
                'message' => "将魂点数不足，升级需要 {$cost} 点将魂（可通过试炼塔通关获取）",
            ];
        }

        $newLevel = $curLevel + 1;
        $remainSoul = $userSoul - $cost;

        // 扣除将魂点数并提升孔位等级
        DB::table('member')->where('uid', $uid)->update(['soul' => $remainSoul]);
        DB::table('general_soul')->where('id', (int)$soulRecord['id'])->update([
            'slot' . $slot => $newLevel,
            'updated_at' => time(),
        ]);

        Redis::del('player:' . $uid);

        $cfg = self::SLOTS[$slot];

        return [
            'status' => 1,
            'gid' => $gid,
            'slot' => $slot,
            'slot_name' => $cfg['name'],
            'level' => $newLevel,
            'cost' => $cost,
            'user_soul' => $remainSoul,
            'bonus' => $newLevel * $cfg['base'] . $cfg['unit'],
            'message' => "将魂【{$cfg['name']}】成功突破至 {$newLevel} 级！",
        ];
    }
}
