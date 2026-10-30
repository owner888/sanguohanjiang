<?php

declare(strict_types=1);

namespace Hanjiang\Handler\General;

use Hanjiang\Battle\LegacyStats;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\RefineAchieve;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Talent;
use Hanjiang\Model\General;
use OmniPHP\Http\Context;
use OmniPHP\Database\DB;

/**
 * 将领界面（移植旧版 controllers/general.php 的客户端接口 index / get_info / equip / unequip）
 *
 * 旧版 own_general.eid1~eid4 新版不存储，由 equipment.generalid + LegacyStats::itemSite 派生；
 * 出征名单为 general.goout（同旧版 own_general.goout，由 c=muster 的 go_battle / go_rest 维护）。
 *
 * 依赖未移植（界面血量 / 三围加成，均按 0 计入 LegacyStats::infoHp 与 wuliup/zhiliup/tiliup）：
 * 兵符宝石 gem_model->get_gem_by_eid、主公天赋 _hero_talent_hp（主公 91 级以上）、名将进阶 _get_advanced_info（100 级）、
 * 坐骑 _get_mount_info（120 级）、名将成就 _get_achievement_info（81 级）、美人 _get_beauty_info（60 级）、
 * 主公星魄 _get_star_info（80 级）、国家学院 _get_war_college_info（国家 30 级）、套装 _get_siut_info、
 * 将魂 _get_soul_info、神树活动 _get_sacredtree_info（index 的 tree 恒为 0）。
 */
final class GeneralHandler
{
    /**
     * 旧版 get_info / _get_info 输出的将领字段（info_field），之后再追加 hp
     */
    private const array INFO_FIELD = [
        'id', 'uid', 'quality', 'name', 'level', 'image', 'type', 'wuli', 'zhili', 'tili', 'wuliup', 'zhiliup', 'tiliup',
        'star', 'turn', 'goout', 'skill', 'talent', 'eid1', 'eid2', 'eid3', 'eid4', 'mid0', 'mid1', 'mid2', 'mid3', 'mid4',
        'achID', 'achList',
    ];

    /**
     * 原版经典武将升级经验公式 (80 * 等级^2)
     */
    public static function getLevelUpExp(int $level): int
    {
        return General::getLevelUpExp($level);
    }

    /**
     * 将领列表: c=general&m=index（旧版 controllers/general.php:index）
     *
     * list 为出征将领（get_battle_list），只有 list[1] 补齐技能、天赋、装备与血量，其余为原始将领行；
     * equipments 为 list[1] 可装配的未穿戴装备（get_unequip_list）。
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0, 'list' => [], 'equipments' => []];

        $list = self::battleList($uid);
        if ($list === []) {
            // 没有出征将领
            $return['status'] = 2;
            return $return;
        }

        $first = self::enrich($uid, $list[1]);
        if ($first === null) {
            // 获取装备失败
            $return['status'] = -1;
            return $return;
        }
        $list[1] = $first;

        $return['list'] = $list;
        // 依赖未移植：神树活动（sacredtree 活动开启时为玩家神树 treeid，并按 buff 加血）
        $return['tree'] = 0;
        $return['equipments'] = self::unequipList($uid, (int)$first['level'], (int)$first['type']);
        $return['status'] = 1;
        return $return;
    }

    /**
     * 将领详情: c=general&m=get_info（旧版 controllers/general.php:get_info）
     */
    public static function get_info(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $return = ['status' => 0, 'info' => [], 'equipments' => []];

        $gid = (int)$ctx->input('gid', 0);
        if ($gid <= 0) {
            // 参数错误
            $return['status'] = -1;
            return $return;
        }

        // check_battle：不存在或未出征都返回 2
        $row = self::ownGeneral($uid, $gid);
        if ($row === null || (int)$row['goout'] === 0) {
            $return['status'] = 2;
            return $return;
        }

        $general = self::enrich($uid, self::legacyRow($row, self::equippedIds($uid, $gid)));
        if ($general === null) {
            // 装备不存在
            $return['status'] = -3;
            return $return;
        }

        $return['info'] = self::infoFields($general);
        $return['equipments'] = self::unequipList($uid, (int)$general['level'], (int)$general['type']);
        $return['status'] = 1;
        return $return;
    }

    /**
     * 穿装备: c=general&m=equip（旧版 controllers/general.php:equip + models/equipment_model.php:equip）
     *
     * 状态码：-5 武器类型与将领职业不符，-4 装备不存在，-3 装配失败（已被穿戴 / 碎片 / 非装备），-2 等级不足，-1 将领不存在
     */
    public static function equip(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)$ctx->input('gid', 0);
        $eid = (int)$ctx->input('eid', 0);

        $row = self::ownGeneral($uid, $gid);
        if ($row === null) {
            // 旧版此处只设 -1 不输出（直接 return），这里照旧版状态码输出
            return ['status' => -1];
        }

        // 旧版 get_info 不按 uid 过滤，随后的 update 带 uid，他人装备最终 affected_rows 为 0 → 0 → -4；这里直接按不存在处理
        $item = DB::table('equipment')->where('uid', $uid)->where('id', $eid)->whereNull('deleted_at')->first();
        if ($item === null) {
            return ['status' => -4];
        }

        $type = (int)$item['type'];
        if ($type < 3 && (int)$row['type'] !== $type) {
            return ['status' => -5];
        }
        if ((int)$item['generalid'] > 0 || (int)$item['chip'] > 0 || $type > 5) {
            return ['status' => -3];
        }
        if ((int)$item['needlevel'] > (int)$row['level']) {
            return ['status' => -2];
        }

        // 该装配位置已有装备则先卸下（旧版 equipment_model::equip → unequip）
        $site = (int)LegacyStats::itemSite($type);
        $eids = self::equippedIds($uid, $gid);
        if ($eids[$site] !== 0) {
            DB::table('equipment')->where('uid', $uid)->where('id', $eids[$site])->update(['generalid' => 0]);
        }

        $affected = DB::table('equipment')->where('uid', $uid)->where('id', $eid)->whereNull('deleted_at')->update(['generalid' => $gid]);
        if ($affected <= 0) {
            return ['status' => -4];
        }

        $eids[$site] = $eid;
        return self::reloadInfo($uid, self::legacyRow($row, $eids));
    }

    /**
     * 卸装备: c=general&m=unequip（旧版 controllers/general.php:unequip + models/equipment_model.php:unequip）
     *
     * 状态码：-3 装备不属于该武将，-2 将领不存在，-1 卸下失败
     */
    public static function unequip(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $gid = (int)$ctx->input('gid', 0);
        $eid = (int)$ctx->input('eid', 0);

        $row = self::ownGeneral($uid, $gid);
        if ($row === null) {
            return ['status' => -2];
        }

        $eids = self::equippedIds($uid, $gid);
        $site = 0;
        for ($i = 1; $i <= 4; $i++) {
            if ($eids[$i] === $eid && $eid > 0) {
                $site = $i;
            }
        }
        if ($site === 0) {
            return ['status' => -3];
        }

        $affected = DB::table('equipment')->where('uid', $uid)->where('id', $eid)->where('generalid', $gid)->update(['generalid' => 0]);
        if ($affected <= 0) {
            return ['status' => -1];
        }

        $eids[$site] = 0;
        return self::reloadInfo($uid, self::legacyRow($row, $eids));
    }

    /**
     * 出征将领列表（旧版 models/general_model.php:get_battle_list），键从 1 开始
     *
     * 旧版 index 按主公等级传 type 1/2，但 get_battle_list 用 in_array($type, [0, 1]) 校验，2 会被改回 1，
     * 所以实际总是按转生降序、等级降序排列；同序时按 id（旧版 SQL 无 ORDER BY，即主键顺序）。
     *
     * @return array<int, array<string, mixed>>
     */
    public static function battleList(int $uid): array
    {
        $rows = DB::table('general')->where('uid', $uid)->where('goout', 1)->whereNull('deleted_at')->get();
        if ($rows === []) {
            return [];
        }
        usort($rows, static fn (array $a, array $b): int =>
            [(int)$b['turn'], (int)$b['level'], (int)$a['id']] <=> [(int)$a['turn'], (int)$a['level'], (int)$b['id']]);

        $eidsByGeneral = self::equippedIdsByGeneral($uid);
        $list = [];
        foreach ($rows as $i => $row) {
            $gid = (int)$row['id'];
            $list[$i + 1] = self::legacyRow($row, $eidsByGeneral[$gid] ?? [1 => 0, 2 => 0, 3 => 0, 4 => 0]);
        }
        return $list;
    }

    /**
     * 旧版界面详情（get_info 的 info 字段 + hp），供其他界面复用（如 information）
     *
     * @param array<string, mixed> $row 新版 general 表行
     * @return array<string, mixed>|null
     */
    public static function legacyInfo(int $uid, array $row): ?array
    {
        $general = self::enrich($uid, self::legacyRow($row, self::equippedIds($uid, (int)$row['id'])));
        return $general === null ? null : self::infoFields($general);
    }

    /**
     * 技能信息（旧版 controllers/general.php:get_skill_by_id）：id / name / description，不存在时三项为 null
     *
     * @return array{id: mixed, name: mixed, description: mixed}
     */
    public static function skillById(int $skillid): array
    {
        $skill = Skill::ROWS[$skillid] ?? [];
        return [
            'id' => $skill['id'] ?? null,
            'name' => $skill['name'] ?? null,
            'description' => $skill['description'] ?? null,
        ];
    }

    /**
     * 天赋信息（旧版 controllers/general.php:get_talent_by_id）：id / name / blood / description，不存在时各项为 null
     *
     * @return array{id: mixed, name: mixed, blood: mixed, description: mixed}
     */
    public static function talentById(int $talentid): array
    {
        $talent = Talent::ROWS[$talentid] ?? [];
        return [
            'id' => $talent['id'] ?? null,
            'name' => $talent['name'] ?? null,
            'blood' => $talent['blood'] ?? null,
            'description' => $talent['description'] ?? null,
        ];
    }

    /**
     * 装配 / 卸下后的返回（旧版 controllers/general.php:_get_info）
     *
     * @param array<string, mixed> $general 旧版 own_general 形状的行
     * @return array<string, mixed>
     */
    private static function reloadInfo(int $uid, array $general): array
    {
        $return = ['status' => 0, 'info' => [], 'equipments' => []];
        $enriched = self::enrich($uid, $general);
        if ($enriched === null) {
            $return['status'] = -4;
            return $return;
        }
        $return['info'] = self::infoFields($enriched);
        $return['equipments'] = self::unequipList($uid, (int)$enriched['level'], (int)$enriched['type']);
        $return['status'] = 1;
        return $return;
    }

    /**
     * 补齐技能、天赋、装备（含精炼 / 强化成就加成）、精炼成就与界面血量
     * （旧版 index / get_info / _get_info 共用的流程；三处的差别只在依赖未移植的加成上）
     *
     * @param array<string, mixed> $general 旧版 own_general 形状的行
     * @return array<string, mixed>|null 装备查询失败（旧版 general_refine_add 返回 -4）时为 null
     */
    private static function enrich(int $uid, array $general): ?array
    {
        $baseHp = LegacyStats::baseHp((int)$general['level'], (int)$general['turn']);

        $general['skill'] = self::skillById((int)$general['skillid']);
        $talent = self::talentById((int)$general['talentid']);
        $talentBlood = (float)($talent['blood'] ?? 0);
        unset($talent['blood']);
        $general['talent'] = $talent;

        $general = self::generalRefineAdd($uid, $general);
        if ($general === null) {
            return null;
        }

        // 兵符加血：eid4 powerup（已含精炼 / 强化成就）；兵符宝石依赖未移植
        $eid4Powerup = is_array($general['eid4']) ? (int)$general['eid4']['powerup'] : 0;

        $general['hp'] = LegacyStats::infoHp(
            $baseHp,
            $eid4Powerup,
            0,
            0,
            (int)$general['tili'],
            (int)$general['tiliup'],
            $talentBlood,
        );
        return $general;
    }

    /**
     * 装备精炼加成、强化成就加成、精炼成就（旧版 models/refine_model.php:general_refine_add）
     *
     * eid1~eid4 由装备 id 替换成装备对象（字段同旧版 item_refine_add），没有装备为 0；
     * 4 件装备都有精炼等级时按最低等级的 refine_promote.type 设 achID，并按 refine_achieve 累加 wuliup / zhiliup。
     *
     * @param array<string, mixed> $general
     * @return array<string, mixed>|null
     */
    private static function generalRefineAdd(int $uid, array $general): ?array
    {
        $general['achID'] = 0;
        $general['achList'] = [];

        $eidarr = [];
        for ($i = 1; $i <= 4; $i++) {
            if ((int)$general['eid' . $i] !== 0) {
                $eidarr[] = (int)$general['eid' . $i];
            }
        }
        if ($eidarr === []) {
            return $general;
        }

        $rows = DB::table('equipment')->where('uid', $uid)->whereIn('id', $eidarr)->whereNull('deleted_at')->get();
        if ($rows === []) {
            return null;
        }
        $equipArr = [];
        foreach ($rows as $item) {
            $equipArr[(int)$item['id']] = self::refineEquipFields($item);
        }

        $harmarr = [];
        for ($i = 1; $i <= 4; $i++) {
            $id = (int)$general['eid' . $i];
            if (isset($equipArr[$id])) {
                $general['eid' . $i] = $equipArr[$id];
                $harmarr[$id] = $equipArr[$id]['hammer'];
            } else {
                // 旧版此处 repair_equipment 把将领的 eid 清零；新版 eid 由 equipment 派生，不会出现
                $general['eid' . $i] = 0;
            }
        }

        // 精炼成就：4 件装备都有精炼等级
        if (!in_array(0, $harmarr, true) && count($harmarr) >= 4) {
            sort($harmarr);
            $refine = LegacyStats::refinePromote((int)$harmarr[0]);
            // 旧版 refine_promote 不存在时返回 -1，调用方未处理（输出空 info）；这里不加精炼成就
            if ($refine !== null) {
                $refineType = (int)$refine['type'];
                $general['achID'] = $refineType;
                $wuliup = 0;
                $zhiliup = 0;
                foreach (RefineAchieve::ROWS as $achadd) {
                    if ($refineType >= (int)$achadd['id']) {
                        $wuliup += (int)$achadd['add_type_val1'];
                        $zhiliup += (int)$achadd['add_type_val2'];
                    }
                }
                if ($refineType > 0) {
                    $general['wuliup'] = (int)$general['wuliup'] + $wuliup;
                    $general['zhiliup'] = (int)$general['zhiliup'] + $zhiliup;
                }
            }
        }

        return $general;
    }

    /**
     * 将领身上装备的输出字段（旧版 models/refine_model.php:item_refine_add 的 equip_field）
     *
     * @param array<string, mixed> $item equipment 表行
     * @return array<string, mixed>
     */
    private static function refineEquipFields(array $item): array
    {
        $display = LegacyStats::displayPowerup(
            (int)$item['power'],
            (int)$item['powerup'],
            (int)$item['type'],
            (int)$item['hammer'],
            (string)$item['aid'],
        );
        return [
            'id' => (int)$item['id'],
            'equipid' => (int)$item['equipid'],
            'name' => (string)$item['name'],
            'type' => (int)$item['type'],
            'quality' => (int)$item['quality'],
            'needlevel' => (int)$item['needlevel'],
            'power' => (int)$item['power'],
            'powerup' => $display['powerup'],
            'level' => (int)$item['level'],
            'saleprice' => (int)$item['saleprice'],
            'hammer' => (int)$item['hammer'],
            'aid' => (string)$item['aid'],
            'hammer_quality' => $display['hammer_quality'],
            'number' => (int)$item['number'],
            'newquality' => (int)$item['newquality'],
        ];
    }

    /**
     * 未穿戴的可装配列表（旧版 controllers/general.php:get_unequip_list），键从 1 开始
     *
     * 条件：未穿戴、非碎片、类型为将领职业武器或 3/4/5；needlevel 高于将领等级的 available = 0。
     * 排序同旧版 get_own_list(power, needlevel)：type ASC, quality DESC, hammer DESC, level DESC, powerup DESC, needlevel ASC。
     *
     * @return array<int, array<string, mixed>>
     */
    private static function unequipList(int $uid, int $level, int $equiptype): array
    {
        $rows = DB::table('equipment')
            ->where('uid', $uid)
            ->where('generalid', 0)
            ->where('chip', 0)
            ->whereIn('type', [$equiptype, 3, 4, 5])
            ->whereNull('deleted_at')
            ->orderBy('type', 'ASC')
            ->orderBy('quality', 'DESC')
            ->orderBy('hammer', 'DESC')
            ->orderBy('level', 'DESC')
            ->orderBy('powerup', 'DESC')
            ->orderBy('needlevel', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();

        $result = [];
        $i = 0;
        foreach ($rows as $v) {
            $i++;
            $type = (int)$v['type'];
            $display = LegacyStats::displayPowerup((int)$v['power'], (int)$v['powerup'], $type, (int)$v['hammer'], (string)$v['aid']);
            $result[$i] = [
                'id' => (int)$v['id'],
                'equipid' => (int)$v['equipid'],
                'type' => $type,
                'name' => (string)$v['name'],
                'quality' => (int)$v['quality'],
                'needlevel' => (int)$v['needlevel'],
                'power' => (int)$v['power'],
                'powerup' => $display['powerup'],
                'level' => (int)$v['level'],
                'saleprice' => (int)$v['saleprice'],
                'hammer' => (int)$v['hammer'],
                'aid' => (string)$v['aid'],
                'newquality' => (int)$v['newquality'],
                'available' => (int)$v['needlevel'] > $level ? 0 : 1,
                'etype' => match ($type) {
                    1, 2 => 1,
                    3 => 2,
                    4 => 3,
                    default => 4,
                },
                'hammer_quality' => $display['hammer_quality'],
            ];
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $general
     * @return array<string, mixed>
     */
    private static function infoFields(array $general): array
    {
        $info = [];
        foreach (self::INFO_FIELD as $field) {
            $info[$field] = $general[$field];
        }
        $info['hp'] = $general['hp'];
        return $info;
    }

    /**
     * 旧版 get_own_by_id：本人、未删除的将领
     *
     * @return array<string, mixed>|null
     */
    private static function ownGeneral(int $uid, int $gid): ?array
    {
        if ($gid <= 0) {
            return null;
        }
        return DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->first();
    }

    /**
     * 新版 general 行 → 旧版 own_general 字段（eid1~eid4 由装备派生，disable 恒 0）
     *
     * @param array<string, mixed> $row
     * @param array<int, int> $eids 装配位置 1~4 => 装备 id（0 为空）
     * @return array<string, mixed>
     */
    private static function legacyRow(array $row, array $eids): array
    {
        return [
            'id' => (int)$row['id'],
            'uid' => (int)$row['uid'],
            'generalid' => (int)$row['generalid'],
            'name' => (string)$row['name'],
            'level' => (int)$row['level'],
            'exp' => (int)$row['exp'],
            'levelupexp' => (int)$row['levelupexp'],
            'image' => (int)$row['image'],
            'type' => (int)$row['type'],
            'wuli' => (int)$row['wuli'],
            'zhili' => (int)$row['zhili'],
            'tili' => (int)$row['tili'],
            'wuliup' => (int)$row['wuliup'],
            'zhiliup' => (int)$row['zhiliup'],
            'tiliup' => (int)$row['tiliup'],
            'talentid' => (int)$row['talentid'],
            'skillid' => (int)$row['skillid'],
            'star' => (int)$row['star'],
            'eid1' => $eids[1] ?? 0,
            'eid2' => $eids[2] ?? 0,
            'eid3' => $eids[3] ?? 0,
            'eid4' => $eids[4] ?? 0,
            'mid0' => (int)$row['mid0'],
            'mid1' => (int)$row['mid1'],
            'mid2' => (int)$row['mid2'],
            'mid3' => (int)$row['mid3'],
            'mid4' => (int)$row['mid4'],
            'addtype' => (int)$row['addtype'],
            'addvalue' => (int)$row['addvalue'],
            'goout' => (int)$row['goout'],
            'turn' => (int)$row['turn'],
            'disable' => 0,
            'quality' => (int)$row['quality'],
        ];
    }

    /**
     * 将领各装配位置上的装备 id
     *
     * @return array<int, int>
     */
    private static function equippedIds(int $uid, int $gid): array
    {
        return self::equippedIdsByGeneral($uid, $gid)[$gid] ?? [1 => 0, 2 => 0, 3 => 0, 4 => 0];
    }

    /**
     * @return array<int, array<int, int>> 将领 id => [装配位置 => 装备 id]
     */
    private static function equippedIdsByGeneral(int $uid, ?int $gid = null): array
    {
        $query = DB::table('equipment')->where('uid', $uid)->whereNull('deleted_at');
        $query = $gid === null ? $query->where('generalid', '>', 0) : $query->where('generalid', $gid);

        $result = [];
        foreach ($query->get() as $item) {
            $site = LegacyStats::itemSite((int)$item['type']);
            if ($site === null) {
                continue;
            }
            $owner = (int)$item['generalid'];
            $result[$owner] ??= [1 => 0, 2 => 0, 3 => 0, 4 => 0];
            $result[$owner][$site] = (int)$item['id'];
        }
        return $result;
    }
}
