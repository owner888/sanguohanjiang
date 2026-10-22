<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Handler\City\MemberHandler;

use Hanjiang\Model\General;
use Hanjiang\Model\Equipment;
use OmniPHP\Http\Context;
use OmniPHP\Database\DB;

final class GeneralHandler
{
    /**
     * 计算单件装备提供的属性加成 (委托至 Equipment 领域模型)
     *
     * @param array<string, mixed> $equip
     * @return array{attack: int, defense: int, hp: int}
     */
    public static function calculateEquipBonus(array $equip): array
    {
        return (new Equipment($equip))->calculateBonus();
    }

    /**
     * 计算武将的综合战斗属性 (委托至 General 领域模型)
     *
     * @param array<string, mixed> $general
     * @param list<array<string, mixed>> $equippedItems
     * @return array<string, mixed>
     */
    public static function calculateGeneralStats(array $general, array $equippedItems = []): array
    {
        return (new General($general))->calculateStats($equippedItems);
    }

    /**
     * 原版经典武将升级经验公式 (80 * 等级^2)
     */
    public static function getLevelUpExp(int $level): int
    {
        return General::getLevelUpExp($level);
    }

    /**
     * 将领列表: c=general&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $nickname = $member['nickname'] ?? '天命主公';

        // 查询已有武将
        $generals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->get();
        if (empty($generals)) {
            // 初始化初始主公将领
            DB::table('general')->insert([
                'uid' => $uid,
                'generalid' => 1,
                'name' => $nickname,
                'level' => 1,
                'exp' => 0,
                'pos' => 2,
                'matrix_id' => 1,
                'status' => 1,
            ]);
            $generals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->get();
        }

        // 查询该玩家所有已穿戴装备 (过滤软删除)
        $allEquips = DB::table('equipment')->where('uid', $uid)->whereNull('deleted_at')->get();
        $equipsByGeneral = [];
        foreach ($allEquips as $eq) {
            $gid = (int)$eq['generalid'];
            if ($gid > 0) {
                $equipsByGeneral[$gid][] = $eq;
            }
        }

        $list = [];
        $idx = 1;
        foreach ($generals as $g) {
            $gid = (int)$g['id'];
            $equipped = $equipsByGeneral[$gid] ?? [];
            $stats = self::calculateGeneralStats($g, $equipped);

            // 格式化 4 个槽位的已装配装备
            $equipSlot = [];
            foreach ($equipped as $eq) {
                $type = (int)$eq['type'];
                $equipSlot[$type] = [
                    'id' => (int)$eq['id'],
                    'equipid' => (int)$eq['equipid'],
                    'name' => $eq['name'],
                    'type' => $type,
                    'level' => (int)$eq['level'],
                    'quality' => (int)$eq['quality'],
                    'bonus' => self::calculateEquipBonus($eq),
                ];
            }

            $list[$idx] = [
                'id' => $gid,
                'uid' => $uid,
                'generalid' => (int)$g['generalid'],
                'name' => $g['name'],
                'level' => $stats['level'],
                'exp' => (int)$g['exp'],
                'levelupexp' => self::getLevelUpExp($stats['level']),
                'wuli' => 85,
                'zhili' => 70,
                'tili' => 90,
                'hp' => $stats['hp'],
                'allblood' => $stats['allblood'],
                'attack' => $stats['attack'],
                'defense' => $stats['defense'],
                'speed' => $stats['speed'],
                'skillid' => 1,
                'talentid' => 1,
                'pos' => (int)$g['pos'],
                'goout' => (int)$g['pos'] > 0 ? 1 : 0,
                'equip' => $equipSlot,
                'stats' => $stats,
            ];
            $idx++;
        }

        $availableEquipments = [];
        foreach ($allEquips as $eq) {
            $bonus = self::calculateEquipBonus($eq);
            $availableEquipments[] = array_merge($eq, [
                'id' => (int)$eq['id'],
                'equipid' => (int)$eq['equipid'],
                'name' => $eq['name'],
                'type' => (int)$eq['type'],
                'level' => (int)$eq['level'],
                'quality' => (int)$eq['quality'],
                'gid' => (int)$eq['generalid'],
                'powerup' => $bonus['attack'] ?: ($bonus['defense'] ?: $bonus['hp']),
                'bonus' => $bonus,
            ]);
        }

        return [
            'status' => 1,
            'list' => $list,
            'equipments' => $availableEquipments,
            'message' => 'ok',
        ];
    }

    /**
     * 将领详情: c=general&m=get_info
     */
    public static function get_info(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)($ctx->input('gid', 1));
        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();

        if ($general === null) {
            // 若指定 gid 不存在，回退到该用户拥有的首位武将
            $general = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->first();
        }

        if ($general === null) {
            return [
                'status' => -1,
                'message' => '武将不存在',
            ];
        }

        $realGid = (int)$general['id'];
        $equipped = DB::table('equipment')->where('uid', $uid)->where('generalid', $realGid)->whereNull('deleted_at')->get();
        $stats = self::calculateGeneralStats($general, $equipped);

        $equipSlots = [];
        foreach ($equipped as $eq) {
            $type = (int)$eq['type'];
            $equipSlots[$type] = [
                'id' => (int)$eq['id'],
                'name' => $eq['name'],
                'type' => $type,
                'level' => (int)$eq['level'],
                'bonus' => self::calculateEquipBonus($eq),
            ];
        }

        return [
            'status' => 1,
            'info' => [
                'id' => $realGid,
                'name' => $general['name'],
                'level' => $stats['level'],
                'hp' => $stats['hp'],
                'allblood' => $stats['allblood'],
                'attack' => $stats['attack'],
                'defense' => $stats['defense'],
                'speed' => $stats['speed'],
                'exp' => (int)$general['exp'],
                'levelupexp' => self::getLevelUpExp($stats['level']),
                'equip' => $equipSlots,
                'stats' => $stats,
            ],
            'equipments' => $equipSlots,
        ];
    }

    /**
     * 穿装备: c=general&m=equip
     */
    public static function equip(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)$ctx->input('gid', 0);
        $eid = (int)$ctx->input('eid', 0);

        if ($gid <= 0 || $eid <= 0) {
            return [
                'status' => 0,
                'message' => '参数错误',
            ];
        }

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->first();
        if ($general === null) {
            return [
                'status' => -1,
                'message' => '武将不存在',
            ];
        }

        $equip = DB::table('equipment')->where('uid', $uid)->where('id', $eid)->first();
        if ($equip === null) {
            return [
                'status' => -4,
                'message' => '装备不存在',
            ];
        }

        // 检查武将该槽位是否已有穿戴中的同类型装备，有则自动卸下
        $equipType = (int)$equip['type'];
        $alreadyEquipped = DB::table('equipment')
            ->where('uid', $uid)
            ->where('generalid', $gid)
            ->where('type', $equipType)
            ->get();

        foreach ($alreadyEquipped as $old) {
            DB::table('equipment')->where('id', (int)$old['id'])->update(['generalid' => 0]);
        }

        // 装配新装备
        DB::table('equipment')->where('id', $eid)->update(['generalid' => $gid]);

        // 获取最新穿戴属性
        $allEquipped = DB::table('equipment')->where('uid', $uid)->where('generalid', $gid)->get();
        $stats = self::calculateGeneralStats($general, $allEquipped);

        return [
            'status' => 1,
            'message' => '穿戴成功',
            'gid' => $gid,
            'eid' => $eid,
            'stats' => $stats,
        ];
    }

    /**
     * 卸装备: c=general&m=unequip
     */
    public static function unequip(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)$ctx->input('gid', 0);
        $eid = (int)$ctx->input('eid', 0);

        if ($gid <= 0 || $eid <= 0) {
            return [
                'status' => 0,
                'message' => '参数错误',
            ];
        }

        $equip = DB::table('equipment')->where('uid', $uid)->where('id', $eid)->first();
        if ($equip === null) {
            return [
                'status' => -1,
                'message' => '装备不存在',
            ];
        }

        if ((int)$equip['generalid'] !== $gid) {
            return [
                'status' => -3,
                'message' => '该装备未穿戴在指定武将身上',
            ];
        }

        // 卸下装备
        DB::table('equipment')->where('id', $eid)->update(['generalid' => 0]);

        $general = DB::table('general')->where('uid', $uid)->where('id', $gid)->first();
        $allEquipped = DB::table('equipment')->where('uid', $uid)->where('generalid', $gid)->get();
        $stats = $general ? self::calculateGeneralStats($general, $allEquipped) : [];

        return [
            'status' => 1,
            'message' => '卸下成功',
            'gid' => $gid,
            'eid' => $eid,
            'stats' => $stats,
        ];
    }
}
