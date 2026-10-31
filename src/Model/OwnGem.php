<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\Gem;
use Hanjiang\MasterData\GemLevelup;
use OmniPHP\Database\DB;

/**
 * 宝石、宝石碎片与宝石格子占用
 *
 * 移植旧版 models/gem_model.php 的 add_gem / get_gem_by_eid / get_gem_by_id / del_gem / adron / pack_off_by_eid /
 * get_unequip_gem_list / get_levelup_price / get_levelup_all_price / update_gem_power / get_adrom_type / get_newgem_temp /
 * get_gem_by_id_arr / add_newgem / add_gem_mergelog，
 * 与 models/member_model.php 的 add_gem / sub_gem（宝石碎片，_add_gem_log）/ add_gemuse / sub_gemuse。
 *
 * 存储同旧版：
 * - own_gem 每颗宝石一行（gemid 为 MasterData Gem 模板，level 0 ~ 25，power 取模板 level{N}；eid / site 为镶嵌的装备与
 *   位置 1 ~ 8，未镶嵌为 0）；旧版 disable 改为软删除 deleted_at；
 * - member.gem 宝石碎片数，每次增减在 gem_log 记一条（type 1 获得 / 2 消耗，balance 为变动后的余额）；
 * - member.gemuse 宝石格子占用数（旧版的宝石包裹不设上限，sanctum 的上限判断已注释掉）。
 *
 * 宝石增删是单行插入 / 条件 UPDATE；碎片与格子占用是单条原子 UPDATE（Member::incrementColumn 等），多 Worker 安全。
 * 与旧版的差别：碎片数量 <= 0 时不写库直接成功；旧版 add_gem 只能发给当前玩家（$this->member），这里显式传 uid。
 */
final class OwnGem
{
    /**
     * 添加宝石（移植 gem_model->add_gem）
     *
     * @return array{status: int, name?: string, type?: int, power?: int} status 1 成功，-1 模板不存在，0 失败；
     *         power 照旧版取模板 level0（不论 $level）
     */
    public static function addGem(int $uid, int $id, int $level = 0, int $num = 1, ?int $now = null): array
    {
        $info = Gem::ROWS[$id] ?? null;
        if ($info === null) {
            return ['status' => -1];
        }
        if ($num <= 0) {
            return ['status' => 0];
        }
        $now ??= time();
        $rows = [];
        for ($i = 0; $i < $num; $i++) {
            $rows[] = [
                'uid' => $uid,
                'gemid' => $info['id'],
                'name' => $info['name'],
                'type' => $info['type'],
                'level' => $level,
                'power' => (int)($info['level' . $level] ?? 0),
                'eid' => 0,
                'dateline' => $now,
            ];
        }
        if (DB::table('own_gem')->insertBatch($rows) <= 0) {
            return ['status' => 0];
        }
        return ['status' => 1, 'name' => $info['name'], 'type' => $info['type'], 'power' => $info['level0']];
    }

    /**
     * 单颗宝石（旧版 get_gem_by_id）；不存在返回 null
     *
     * @return array<string, mixed>|null
     */
    public static function find(int $uid, int $id): ?array
    {
        return DB::table('own_gem')->where('uid', $uid)->where('id', $id)->whereNull('deleted_at')->first();
    }

    /**
     * 按装备 id 取已镶嵌的宝石（移植 get_gem_by_eid）
     *
     * quality 按旧版：level 0 为 1，25 为 6，其余 ceil(level / 4)。
     * - $eid 为单个 id 且 $site > 0：返回该位置的一颗（旧版此分支 level 25 时把 quality 写错了变量，结果里没有 quality）；
     * - $eid 为单个 id：返回 [site => 宝石]；
     * - $eid 为数组：返回宝石列表（$site 不起作用）。
     *
     * @param int|list<int> $eid
     * @return array<int|string, mixed>
     */
    public static function byEid(int $uid, int|array $eid, int $site = 0): array
    {
        $isId = !is_array($eid);
        $ids = $isId ? [$eid] : $eid;
        if (!$isId) {
            $site = 0;
        }
        if ($ids === []) {
            return [];
        }
        $query = DB::table('own_gem')->where('uid', $uid)->whereIn('eid', $ids)->whereNull('deleted_at');
        if ($site > 0) {
            $query->where('site', $site);
            $row = $query->first();
            if ($row === null) {
                return [];
            }
            if ((int)$row['level'] === 0) {
                $row['quality'] = 1;
            } elseif ((int)$row['level'] !== 25) {
                $row['quality'] = (int)ceil((int)$row['level'] / 4);
            }
            return $row;
        }
        $result = [];
        foreach ($query->get() as $key => $val) {
            $val['quality'] = self::quality((int)$val['level']);
            $result[$key] = $val;
        }
        if (!$isId) {
            return $result;
        }
        $return = [];
        foreach ($result as $val) {
            $return[(int)$val['site']] = $val;
        }
        return $return;
    }

    /**
     * 删除宝石（移植 del_gem：旧版 disable 写时间戳，这里软删除）
     *
     * @return int 1 成功，0 宝石不存在或已删除
     */
    public static function delGem(int $uid, int $id): int
    {
        $affected = DB::table('own_gem')->where('uid', $uid)->where('id', $id)->whereNull('deleted_at')
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);
        return $affected > 0 ? 1 : 0;
    }

    /**
     * 镶嵌到装备上（移植 adron）
     *
     * @return int 1 成功，0 失败
     */
    public static function adron(int $uid, int $id, int $eid, int $site): int
    {
        $affected = DB::table('own_gem')->where('uid', $uid)->where('id', $id)->whereNull('deleted_at')
            ->update(['eid' => $eid, 'site' => $site]);
        return $affected > 0 ? 1 : 0;
    }

    /**
     * 卸下某件装备上的全部宝石（移植 pack_off_by_eid；调用方随后按返回个数 addGemuse）
     *
     * @return int 卸下的宝石个数
     */
    public static function packOffByEid(int $uid, int $eid): int
    {
        return DB::table('own_gem')->where('uid', $uid)->where('eid', $eid)->whereNull('deleted_at')
            ->update(['eid' => 0, 'site' => 0]);
    }

    /**
     * 当前宝石碎片数（member.gem）；玩家不存在为 0
     */
    public static function chip(int $uid): int
    {
        return (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gem') ?? 0);
    }

    /**
     * 增加宝石碎片（移植 member_model->add_gem）
     *
     * @return array{status: int, gem: int} status 1 成功，-1 玩家不存在；gem 为增加后的余额
     */
    public static function addChip(int $uid, int $gem, string $class = '', string $method = '', ?int $now = null): array
    {
        if ($gem <= 0) {
            return Member::logInfo($uid) === null ? ['status' => -1, 'gem' => 0] : ['status' => 1, 'gem' => self::chip($uid)];
        }
        $balance = Member::incrementColumn($uid, 'gem', $gem);
        if ($balance === null) {
            return ['status' => -1, 'gem' => 0];
        }
        self::chipLog($uid, 1, $gem, $balance, $class, $method, $now);
        return ['status' => 1, 'gem' => $balance];
    }

    /**
     * 消耗宝石碎片（移植 member_model->sub_gem）
     *
     * @return array{status: int, gem: int} status 1 成功，-1 碎片不足，-2 玩家不存在；gem 为扣除后的余额
     */
    public static function subChip(int $uid, int $gem, string $class = '', string $method = '', ?int $now = null): array
    {
        if ($gem <= 0) {
            return Member::logInfo($uid) === null ? ['status' => -2, 'gem' => 0] : ['status' => 1, 'gem' => self::chip($uid)];
        }
        $balance = Member::decrementColumn($uid, 'gem', $gem);
        if ($balance === null) {
            return Member::logInfo($uid) === null
                ? ['status' => -2, 'gem' => 0]
                : ['status' => -1, 'gem' => self::chip($uid)];
        }
        self::chipLog($uid, 2, $gem, $balance, $class, $method, $now);
        return ['status' => 1, 'gem' => $balance];
    }

    /**
     * 增加宝石格子占用（移植 add_gemuse：旧版 $add 不大于 1 时按 1 算，所以传 0 也会加 1）
     *
     * @return int 1 成功，0 失败（玩家不存在）
     */
    public static function addGemuse(int $uid, int $add = 1): int
    {
        return Member::incrementColumn($uid, 'gemuse', $add > 1 ? $add : 1) === null ? 0 : 1;
    }

    /**
     * 减少宝石格子占用（移植 sub_gemuse：$sub 不大于 1 时按 1 算；旧版不检查下限，无符号列截到 0）
     *
     * @return int 1 成功，0 失败（玩家不存在）
     */
    public static function subGemuse(int $uid, int $sub = 1): int
    {
        return Member::decrementColumnFloor($uid, 'gemuse', $sub > 1 ? $sub : 1) === null ? 0 : 1;
    }

    /**
     * 宝石品质（旧版 get_unequip_gem_list / get_gem_by_eid：level 0 为 1，25 为 6，其余 ceil(level / 4)）
     */
    public static function quality(int $level): int
    {
        return match (true) {
            $level === 0 => 1,
            $level === 25 => 6,
            default => (int)ceil($level / 4),
        };
    }

    /** 宝石背包每页个数（旧版 gem_model->_limit） */
    public const int LIMIT = 12;
    /** 宝石合成界面每页个数（旧版 gem_model->_mergelimit） */
    public const int MERGE_LIMIT = 9;

    /**
     * 输出用的宝石行（旧版 SELECT * FROM own_gem 的字段，disable 恒为 0；带 quality 时保留）
     *
     * @param array<string, mixed> $row own_gem 行
     * @return array<string, mixed>
     */
    public static function legacyRow(array $row): array
    {
        $return = [
            'id' => (int)$row['id'],
            'uid' => (int)$row['uid'],
            'gemid' => (int)$row['gemid'],
            'name' => (string)$row['name'],
            'type' => (int)$row['type'],
            'level' => (int)$row['level'],
            'power' => (int)$row['power'],
            'eid' => (int)$row['eid'],
            'site' => (int)$row['site'],
            'disable' => 0,
            'dateline' => (int)$row['dateline'],
        ];
        if (array_key_exists('quality', $row)) {
            $return['quality'] = $row['quality'];
        }
        return $return;
    }

    /**
     * 单颗宝石（移植 get_gem_by_id：带 quality，level 0 为 1、25 为 6、其余 ceil(level / 4)）；不存在返回空数组
     *
     * @return array<string, mixed>
     */
    public static function gemById(int $uid, int $id): array
    {
        $row = self::find($uid, $id);
        if ($row === null) {
            return [];
        }
        $row['quality'] = self::quality((int)$row['level']);
        return self::legacyRow($row);
    }

    /**
     * 未镶嵌的宝石一页（移植 get_unequip_gem_list：$merge 为 1 时每页 9 个，否则 12 个；按等级降序、类型升序）
     *
     * @return list<array<string, mixed>>
     */
    public static function unequipList(int $uid, int $page, int $merge = 0): array
    {
        $page = $page > 0 ? $page : 1;
        $limit = $merge ? self::MERGE_LIMIT : self::LIMIT;
        $rows = DB::table('own_gem')->where('uid', $uid)->where('eid', 0)->whereNull('deleted_at')
            ->orderBy('level', 'DESC')->orderBy('type', 'ASC')->orderBy('id', 'ASC')
            ->limit($limit)->offset(($page - 1) * $limit)->get();
        $return = [];
        foreach ($rows as $row) {
            $row['quality'] = self::quality((int)$row['level']);
            $return[] = self::legacyRow($row);
        }
        return $return;
    }

    /**
     * 多颗 20 级宝石（移植 get_gem_by_id_arr：只取 level = 20，quality = ceil(level / 4)）
     *
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    public static function gemByIdArr(int $uid, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $return = [];
        foreach (DB::table('own_gem')->where('uid', $uid)->whereIn('id', $ids)->where('level', 20)->whereNull('deleted_at')->get() as $row) {
            $row['quality'] = (int)ceil((int)$row['level'] / 4);
            $return[] = self::legacyRow($row);
        }
        return $return;
    }

    /**
     * 修改宝石等级与属性（移植 update_gem_power：等级 / 属性 / id 有一个为 0 返回 -1）
     *
     * @return array{status: int}
     */
    public static function updateGemPower(int $uid, int $id, int $level, int $power): array
    {
        if (!$level || !$power || !$id) {
            return ['status' => -1];
        }
        $affected = DB::table('own_gem')->where('uid', $uid)->where('id', $id)->whereNull('deleted_at')
            ->update(['level' => $level, 'power' => $power]);
        return ['status' => $affected > 0 ? 1 : 0];
    }

    /**
     * 升到 $level 级所需碎片（移植 get_levelup_price：gem_levelup.need_chip × 类型系数后向上取整）；没有该等级返回空数组
     *
     * @return array{level?: int, need_chip?: int}
     */
    public static function levelupPrice(int $level, int $type): array
    {
        $row = GemLevelup::ROWS[$level] ?? null;
        if ($row === null) {
            return [];
        }
        return ['level' => $row['level'], 'need_chip' => (int)ceil($row['need_chip'] * self::levelupRatio($type))];
    }

    /**
     * 升到 $level 级为止的碎片总和（移植 get_levelup_all_price：各级 need_chip × 类型系数累加后向上取整）
     */
    public static function levelupAllPrice(int $level, int $type): int
    {
        $total = 0;
        foreach (GemLevelup::ROWS as $row) {
            if ($row['level'] <= $level) {
                $total += $row['need_chip'] * self::levelupRatio($type);
            }
        }
        return (int)ceil($total);
    }

    /**
     * 合成用的新宝石模板（移植 get_newgem_temp：Gem 6 ~ 10，可按类型筛选，quality 恒为 6；只取 id / name / type / level21）
     *
     * @return list<array<string, mixed>>
     */
    public static function newgemTemp(int $type = 0): array
    {
        $return = [];
        foreach ([6, 7, 8, 9, 10] as $id) {
            $row = Gem::ROWS[$id] ?? null;
            if ($row === null || ($type !== 0 && $row['type'] !== $type)) {
                continue;
            }
            $return[] = ['id' => $row['id'], 'name' => $row['name'], 'type' => $row['type'], 'level21' => $row['level21'], 'quality' => 6];
        }
        return $return;
    }

    /**
     * 添加宝石（合成用，移植 add_newgem：返回新宝石 id 与 quality / power / level）
     *
     * @return array<string, mixed> status 1 成功，-1 模板不存在，0 失败
     */
    public static function addNewGem(int $uid, int $id, int $level = 0, ?int $now = null): array
    {
        $return = ['status' => 0];
        $info = Gem::ROWS[$id] ?? null;
        if ($info === null) {
            $return['status'] = -1;
            return $return;
        }
        $newId = (int)DB::table('own_gem')->insert([
            'uid' => $uid,
            'gemid' => $info['id'],
            'name' => $info['name'],
            'type' => $info['type'],
            'level' => $level,
            'power' => (int)($info['level' . $level] ?? 0),
            'eid' => 0,
            'dateline' => $now ?? time(),
        ]);
        if ($newId > 0) {
            $return['id'] = $newId;
            $return['status'] = 1;
            $return['name'] = $info['name'];
            $return['type'] = $info['type'];
            $return['quality'] = (int)ceil($level / 4);
            $return['power'] = (int)($info['level' . $level] ?? 0);
            $return['level'] = $level;
        }
        return $return;
    }

    /**
     * 宝石合成日志（移植 add_gem_mergelog）
     */
    public static function addMergeLog(int $uid, int $gemid1, int $gemid2, int $newGemid, ?int $now = null): int
    {
        return DB::table('own_gem_mergelog')->insert([
            'gemid1' => $gemid1,
            'gemid2' => $gemid2,
            'new_gemid' => $newGemid,
            'uid' => $uid,
            'dateline' => $now ?? time(),
        ]) ? 1 : 0;
    }

    /**
     * 装备可镶嵌的宝石类型（移植 get_adrom_type：装备类型 1 → 1、2 → 3、3 → 2、4 → 4、5 → 5，其余 0）
     */
    public static function adromType(int $equipType): int
    {
        return [1 => 1, 2 => 3, 3 => 2, 4 => 4, 5 => 5][$equipType] ?? 0;
    }

    /**
     * 升级碎片的类型系数（移植 get_levelup_ratio）
     */
    private static function levelupRatio(int $type): float
    {
        return [1 => 1, 2 => 0.75, 3 => 1, 4 => 0.75, 5 => 1][$type] ?? 1;
    }

    /**
     * gem_log 一条（移植 _add_gem_log；argument 恒为空串）
     */
    private static function chipLog(int $uid, int $type, int $gem, int $balance, string $class, string $method, ?int $now): void
    {
        $info = Member::logInfo($uid) ?? ['level' => 0, 'vip' => 0];
        DB::table('gem_log')->insert([
            'uid' => $uid,
            'level' => $info['level'],
            'type' => $type,
            'gem' => $gem,
            'balance' => $balance,
            'vip' => $info['vip'],
            'class' => Member::logText($class, 20),
            'method' => Member::logText($method, 20),
            'argument' => '',
            'dateline' => $now ?? time(),
        ]);
    }
}
