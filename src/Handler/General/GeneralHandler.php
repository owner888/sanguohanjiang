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
        if (empty($allEquips)) {
            // 初始化新手入门装备放入背包 (generalid=0)
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
            $allEquips = DB::table('equipment')->where('uid', $uid)->whereNull('deleted_at')->get();
        }
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
            $list[(string)$idx] = self::formatHeroData($g, $equipped, $uid);
            $idx++;
        }

        $availableEquipments = [];
        $eqIdx = 1;
        foreach ($allEquips as $eq) {
            $bonus = self::calculateEquipBonus($eq);
            $item = array_merge($eq, [
                'id' => (int)$eq['id'],
                'equipid' => (int)$eq['equipid'],
                'etype' => (int)$eq['type'],
                'name' => $eq['name'],
                'type' => (int)$eq['type'],
                'level' => (int)$eq['level'],
                'quality' => (int)$eq['quality'],
                'gid' => (int)$eq['generalid'],
                'needlevel' => 1,
                'available' => 1,
                'hammer' => 0,
                'hammer_quality' => 0,
                'powerup' => $bonus['attack'] ?: ($bonus['defense'] ?: $bonus['hp']),
                'bonus' => $bonus,
            ]);
            $availableEquipments[(string)$eqIdx] = $item;
            $eqIdx++;
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
        $allEquips = DB::table('equipment')->where('uid', $uid)->whereNull('deleted_at')->get();
        $equipped = [];
        $unequipped = [];
        $eqIdx = 1;
        foreach ($allEquips as $eq) {
            if ((int)$eq['generalid'] === $realGid) {
                $equipped[] = $eq;
            } elseif ((int)$eq['generalid'] === 0) {
                $bonus = self::calculateEquipBonus($eq);
                $unequipped[(string)$eqIdx] = array_merge($eq, [
                    'id' => (int)$eq['id'],
                    'equipid' => (int)$eq['equipid'],
                    'etype' => (int)$eq['type'],
                    'name' => $eq['name'],
                    'type' => (int)$eq['type'],
                    'level' => (int)$eq['level'],
                    'quality' => (int)$eq['quality'],
                    'gid' => 0,
                    'needlevel' => 1,
                    'available' => 1,
                    'hammer' => 0,
                    'hammer_quality' => 0,
                    'powerup' => $bonus['attack'] ?: ($bonus['defense'] ?: $bonus['hp']),
                    'bonus' => $bonus,
                ]);
                $eqIdx++;
            }
        }

        $heroInfo = self::formatHeroData($general, $equipped, $uid);

        return [
            'status' => 1,
            'info' => $heroInfo,
            'equipments' => $unequipped,
        ];
    }

    /**
     * 格式化武将完整协议数据 (包含 heropop.js 依赖的 talent, skill, eid1~eid4 等)
     *
     * @param array<string, mixed> $g
     * @param list<array<string, mixed>> $equipped
     * @return array<string, mixed>
     */
    public static function formatHeroData(array $g, array $equipped, int $uid): array
    {
        $gid = (int)$g['id'];
        $stats = self::calculateGeneralStats($g, $equipped);
        $level = $stats['level'];

        $equipSlot = [];
        $eids = [1 => '0', 2 => '0', 3 => '0', 4 => '0'];

        foreach ($equipped as $eq) {
            $type = (int)$eq['type'];
            $eqItem = [
                'id' => (int)$eq['id'],
                'equipid' => (int)$eq['equipid'],
                'name' => $eq['name'],
                'type' => $type,
                'level' => (int)$eq['level'],
                'quality' => (int)$eq['quality'],
                'hammer' => 0,
                'hammer_quality' => 0,
                'bonus' => self::calculateEquipBonus($eq),
            ];
            $equipSlot[$type] = $eqItem;
            if ($type >= 1 && $type <= 4) {
                $eids[$type] = $eqItem;
            }
        }

        return [
            'id' => $gid,
            'uid' => $uid,
            'generalid' => (int)($g['generalid'] ?? 1),
            'name' => $g['name'],
            'level' => $level,
            'exp' => (int)$g['exp'],
            'levelupexp' => self::getLevelUpExp($level),
            'image' => (int)($g['image'] ?? $g['generalid'] ?? 1),
            'quality' => (int)($g['quality'] ?? 3),
            'type' => (int)($g['type'] ?? 1),
            'turn' => (int)($g['turn'] ?? 0),
            'isturn' => ($level >= 100) ? 1 : 0,
            'star' => (int)($g['star'] ?? 1),
            'achID' => 0,
            'achList' => [],
            'pos' => (int)$g['pos'],
            'goout' => (int)$g['pos'] > 0 ? 1 : 0,
            'wuli' => 85,
            'wuliup' => (int)($g['wuliup'] ?? 0),
            'zhili' => 70,
            'zhiliup' => (int)($g['zhiliup'] ?? 0),
            'tili' => 90,
            'tiliup' => (int)($g['tiliup'] ?? 0),
            'hp' => $stats['hp'],
            'allblood' => $stats['allblood'],
            'attack' => $stats['attack'],
            'defense' => $stats['defense'],
            'speed' => $stats['speed'],
            'skillid' => (int)($g['skillid'] ?? 1),
            'talentid' => (int)($g['talentid'] ?? 1),
            'talent' => self::getGeneralTalent($g),
            'skill' => self::getGeneralSkill($g),
            'eid1' => $eids[1],
            'eid2' => $eids[2],
            'eid3' => $eids[3],
            'eid4' => $eids[4],
            'mid0' => 0,
            'mid1' => 0,
            'mid2' => 0,
            'mid3' => 0,
            'mid4' => 0,
            'equip' => $equipSlot,
            'stats' => $stats,
        ];
    }

    /**
     * @param array<string, mixed> $g
     * @return array{id: int, name: string, blood: int, description: string}
     */
    public static function getGeneralTalent(array $g): array
    {
        $talents = [
            1 => ['id' => 1, 'name' => '坚强', 'blood' => 1, 'description' => '受到直接物理伤害降低5%'],
            2 => ['id' => 2, 'name' => '勇猛', 'blood' => 1, 'description' => '普通物理攻击伤害提升10%'],
            3 => ['id' => 3, 'name' => '神速', 'blood' => 1, 'description' => '出手先攻速度提升15点'],
            4 => ['id' => 4, 'name' => '智囊', 'blood' => 1, 'description' => '策略攻击防御综合提升10%'],
            5 => ['id' => 5, 'name' => '破军', 'blood' => 1, 'description' => '无双暴击概率提升10%'],
            6 => ['id' => 6, 'name' => '百战', 'blood' => 1, 'description' => '气血生命值上限提升15%'],
        ];

        $name = (string)($g['name'] ?? '');
        $id = match ($name) {
            '张飞' => 2,
            '赵云' => 3,
            '关羽' => 5,
            '诸葛亮' => 4,
            default => (int)($g['talentid'] ?? 1),
        };

        return $talents[$id] ?? $talents[1];
    }

    /**
     * @param array<string, mixed> $g
     * @return array{id: int, name: string, description: string}
     */
    public static function getGeneralSkill(array $g): array
    {
        $skills = [
            1 => ['id' => 1, 'name' => '会心一击', 'description' => '积攒怒气释放强力一击，造成150%战法伤害'],
            2 => ['id' => 2, 'name' => '万夫莫敌', 'description' => '咆哮怒吼横扫千军，对横排敌方造成160%群体震慑'],
            3 => ['id' => 3, 'name' => '龙胆穿心', 'description' => '白马银枪直刺中路，必定暴击并破除敌军格挡'],
            4 => ['id' => 4, 'name' => '青龙偃月', 'description' => '神刀劈砍降下雷霆万钧，重创敌将并提升自身韧性'],
            5 => ['id' => 5, 'name' => '卧龙天火', 'description' => '借东风降下神火天降，对敌军全阵型进行法术覆盖'],
        ];

        $name = (string)($g['name'] ?? '');
        $id = match ($name) {
            '张飞' => 2,
            '赵云' => 3,
            '关羽' => 4,
            '诸葛亮' => 5,
            default => (int)($g['skillid'] ?? 1),
        };

        return $skills[$id] ?? $skills[1];
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

        // 获取最新穿戴属性与背包装备
        $allEquips = DB::table('equipment')->where('uid', $uid)->whereNull('deleted_at')->get();
        $equipped = [];
        $unequipped = [];
        $eqIdx = 1;
        foreach ($allEquips as $item) {
            if ((int)$item['generalid'] === $gid) {
                $equipped[] = $item;
            } elseif ((int)$item['generalid'] === 0) {
                $bonus = self::calculateEquipBonus($item);
                $unequipped[(string)$eqIdx] = array_merge($item, [
                    'id' => (int)$item['id'],
                    'equipid' => (int)$item['equipid'],
                    'etype' => (int)$item['type'],
                    'name' => $item['name'],
                    'type' => (int)$item['type'],
                    'level' => (int)$item['level'],
                    'quality' => (int)$item['quality'],
                    'gid' => 0,
                    'needlevel' => 1,
                    'available' => 1,
                    'hammer' => 0,
                    'hammer_quality' => 0,
                    'powerup' => $bonus['attack'] ?: ($bonus['defense'] ?: $bonus['hp']),
                    'bonus' => $bonus,
                ]);
                $eqIdx++;
            }
        }

        $stats = self::calculateGeneralStats($general, $equipped);
        $heroData = self::formatHeroData($general, $equipped, $uid);

        return [
            'status' => 1,
            'message' => '穿戴成功',
            'gid' => $gid,
            'eid' => $eid,
            'stats' => $stats,
            'info' => $heroData,
            'equipments' => $unequipped,
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
        $allEquips = DB::table('equipment')->where('uid', $uid)->whereNull('deleted_at')->get();
        $equipped = [];
        $unequipped = [];
        $eqIdx = 1;
        foreach ($allEquips as $item) {
            if ((int)$item['generalid'] === $gid) {
                $equipped[] = $item;
            } elseif ((int)$item['generalid'] === 0) {
                $bonus = self::calculateEquipBonus($item);
                $unequipped[(string)$eqIdx] = array_merge($item, [
                    'id' => (int)$item['id'],
                    'equipid' => (int)$item['equipid'],
                    'etype' => (int)$item['type'],
                    'name' => $item['name'],
                    'type' => (int)$item['type'],
                    'level' => (int)$item['level'],
                    'quality' => (int)$item['quality'],
                    'gid' => 0,
                    'needlevel' => 1,
                    'available' => 1,
                    'hammer' => 0,
                    'hammer_quality' => 0,
                    'powerup' => $bonus['attack'] ?: ($bonus['defense'] ?: $bonus['hp']),
                    'bonus' => $bonus,
                ]);
                $eqIdx++;
            }
        }

        $stats = $general ? self::calculateGeneralStats($general, $equipped) : [];
        $heroData = $general ? self::formatHeroData($general, $equipped, $uid) : [];

        return [
            'status' => 1,
            'message' => '卸下成功',
            'gid' => $gid,
            'eid' => $eid,
            'stats' => $stats,
            'info' => $heroData,
            'equipments' => $unequipped,
        ];
    }
}
