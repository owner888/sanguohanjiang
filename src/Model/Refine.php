<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\Battle\LegacyStats;
use Hanjiang\MasterData\RefineAchieve;
use Hanjiang\MasterData\RefineMaster;
use Hanjiang\MasterData\RefinePromote;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 装备精炼（移植旧版 models/refine_model.php）
 *
 * 存储同旧版：装备的精炼等级为 equipment.hammer（0 ~ 99）；refine_master_log 每个玩家一行，为当前的精炼大师与最后刷新时间；
 * refine_log / refine_inherit_log 为精炼 / 继承日志。配置：大师 RefineMaster，精炼等级 RefinePromote（按 level，同 level 取第一行），
 * 精炼成就 RefineAchieve。
 *
 * 防刷锁同旧版：Redis 键 {refine_lock_ / inherit_lock_ / refresh_lock_}{uid}，30 秒过期；
 * 旧版先 get 再 setex（两个并发请求可能都拿到锁），这里用 SET NX 原子加锁。
 */
final class Refine
{
    /** 精炼锁 */
    public const string REFINE_LOCK = 'refine_lock_';
    /** 继承锁 */
    public const string INHERIT_LOCK = 'inherit_lock_';
    /** 刷新大师锁 */
    public const string REFRESH_LOCK = 'refresh_lock_';
    /** 锁的有效期（秒） */
    private const int LOCK_TIME = 30;

    /**
     * 精炼大师（移植 get_master_info($id)：按 id 一行）；不存在返回 null
     *
     * @return array<string, mixed>|null
     */
    public static function masterInfo(int $id): ?array
    {
        return RefineMaster::ROWS[$id] ?? null;
    }

    /**
     * 全部精炼大师（移植 get_master_info()：多行，下标从 0 开始，按 id 顺序）
     *
     * @return list<array<string, mixed>>
     */
    public static function masterList(): array
    {
        return array_values(RefineMaster::ROWS);
    }

    /**
     * 玩家的精炼大师（移植 get_master_log）
     *
     * 没有记录时按 get_rate 权重随机一位（剔除 5 / 6 品的橙、红大师）插入，dateline 为当前时间。
     * 并发首次进入时只插入一行（uid 唯一），插入失败读回已有的那一行。
     *
     * @return array{uid: int, masterid: int, dateline: int}
     */
    public static function masterLog(int $uid, int $now): array
    {
        $row = self::findMasterLog($uid);
        if ($row !== null) {
            return $row;
        }
        $list = self::masterList();
        $rate = 0;
        foreach ($list as $key => $val) {
            // 总分母
            $rate += $val['get_rate'] * 100;
            // 剔除橙色和红色
            if ($val['grade'] === 6 || $val['grade'] === 5) {
                unset($list[$key]);
                $rate -= $val['get_rate'] * 100;
            }
        }
        $masterid = self::pick($list, $rate)['id'];
        $info = ['uid' => $uid, 'masterid' => $masterid, 'dateline' => $now];
        $affected = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_refine_master_log` (`uid`, `masterid`, `dateline`) VALUES (:uid, :masterid, :dateline)',
            $info
        );
        return $affected > 0 ? $info : (self::findMasterLog($uid) ?? $info);
    }

    /**
     * 按权重抽一位大师（get_master_log / master_refresh 共用的写法：mt_rand(1, 总权重)，游标累加 get_rate × 100）
     *
     * @param array<int, array<string, mixed>> $list
     * @return array<string, mixed>
     */
    public static function pick(array $list, int $rate): array
    {
        $rand = mt_rand(1, $rate);
        $cursor = 0;
        foreach ($list as $val) {
            $cursor += $val['get_rate'] * 100;
            if ($rand <= $cursor) {
                return $val;
            }
        }
        // 权重合计等于 $rate，不会走到这里
        return end($list);
    }

    /**
     * 更新玩家的精炼大师与刷新时间（移植 update_master_log）
     */
    public static function updateMasterLog(int $uid, int $masterid, int $now): void
    {
        DB::table('refine_master_log')->where('uid', $uid)->whereNull('deleted_at')->update(['dateline' => $now, 'masterid' => $masterid]);
    }

    /**
     * 精炼等级信息（移植 get_promote_info(单个等级)：0 或不存在返回 null）
     *
     * @return array<string, int>|null
     */
    public static function promote(int $level): ?array
    {
        return LegacyStats::refinePromote($level);
    }

    /**
     * 多个精炼等级的信息（移植 get_promote_info(数组)：WHERE level IN (...)，按表顺序返回全部匹配行）
     *
     * @param list<int> $levels
     * @return list<array<string, int>>
     */
    public static function promoteList(array $levels): array
    {
        $return = [];
        foreach (RefinePromote::ROWS as $row) {
            if (in_array($row['level'], $levels, true)) {
                $return[] = $row;
            }
        }
        return $return;
    }

    /**
     * 精炼日志（移植 add_refine_log）
     */
    public static function addRefineLog(int $uid, int $eid, int $mid, int $nowLevel, int $newLevel, int $now): void
    {
        DB::table('refine_log')->insert([
            'eid' => $eid,
            'mid' => $mid,
            'uid' => $uid,
            'dateline' => $now,
            'now_level' => $nowLevel,
            'new_level' => $newLevel,
        ]);
    }

    /**
     * 继承日志（移植 add_inherit_log）
     */
    public static function addInheritLog(int $uid, int $id1, int $id2, int $now): void
    {
        DB::table('refine_inherit_log')->insert(['uid' => $uid, 'id1' => $id1, 'id2' => $id2, 'dateline' => $now]);
    }

    /**
     * 精炼成就表（移植 get_refine_ach()：全部行，下标从 0 开始）
     *
     * @return list<array<string, mixed>>
     */
    public static function achieveList(): array
    {
        return array_values(RefineAchieve::ROWS);
    }

    /**
     * 本人的装备（旧版 equipment_model->get_info：单个 id 返回一行，数组返回 [id => 行]，字段为旧版 own_equip）
     *
     * 旧版 get_info 不按 uid 过滤（可以精炼 / 继承别人的装备）；新版只查本人未删除的装备，别人的装备按「不存在」处理。
     *
     * @param int|list<int> $ids
     * @return array<string, mixed>|array<int, array<string, mixed>>|null 单个 id 不存在为 null；数组都不存在为空数组
     */
    public static function ownEquip(int $uid, int|array $ids): ?array
    {
        $list = is_array($ids) ? $ids : [$ids];
        if ($list === []) {
            return [];
        }
        $rows = DB::table('equipment')->where('uid', $uid)->whereIn('id', $list)->whereNull('deleted_at')->orderBy('id')->get();
        if (!is_array($ids)) {
            return $rows === [] ? null : self::legacyEquip($rows[0]);
        }
        $return = [];
        foreach ($rows as $row) {
            $return[(int)$row['id']] = self::legacyEquip($row);
        }
        return $return;
    }

    /**
     * 新版 equipment 行 → 旧版 own_equip 字段
     *
     * gid 即 generalid；glevel（旧版装备列表的武将等级缓存，没有维护）与 disable 新版不存，按 0；dateline 取入库时间。
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function legacyEquip(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'uid' => (int)$row['uid'],
            'equipid' => (int)$row['equipid'],
            'gid' => (int)$row['generalid'],
            'glevel' => 0,
            'name' => (string)$row['name'],
            'type' => (int)$row['type'],
            'quality' => (int)$row['quality'],
            'needlevel' => (int)$row['needlevel'],
            'level' => (int)$row['level'],
            'power' => (int)$row['power'],
            'powerup' => (int)$row['powerup'],
            'saleprice' => (int)$row['saleprice'],
            'chip' => (int)$row['chip'],
            'number' => (int)$row['number'],
            'disable' => 0,
            'sale_site' => (int)$row['sale_site'],
            'dateline' => (int)strtotime((string)$row['created_at']),
            'hammer' => (int)$row['hammer'],
            'aid' => (string)$row['aid'],
            'forgeid' => (int)$row['forgeid'],
            'newquality' => (int)$row['newquality'],
            'oldequipid' => (int)$row['oldequipid'],
            'newattribute' => (string)$row['newattribute'],
        ];
    }

    /**
     * 写装备精炼等级（移植 equipment_model->refine / update_own_info 的 hammer）
     */
    public static function setHammer(int $uid, int $id, int $hammer): void
    {
        DB::table('equipment')->where('id', $id)->where('uid', $uid)->whereNull('deleted_at')->update(['hammer' => $hammer]);
    }

    /**
     * 装备精炼加成、强化成就加成、精炼成就（旧版 models/refine_model.php:general_refine_add；将领界面 / 名将进阶界面共用）
     *
     * eid1~eid4 由装备 id 替换成装备对象（字段同旧版 item_refine_add），没有装备为 0；
     * 4 件装备都有精炼等级时按最低等级的 refine_promote.type 设 achID，并按 refine_achieve 累加 wuliup / zhiliup。
     *
     * @param array<string, mixed> $general
     * @return array<string, mixed>|null
     */
    public static function generalRefineAdd(int $uid, array $general): ?array
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
     * 加锁（移植 add_lock + get_lock：已被锁返回 false）
     */
    public static function lock(string $key, int $uid): bool
    {
        $redis = Redis::instance();
        return $redis !== null && (bool)$redis->set($key . $uid, '1', ['nx', 'ex' => self::LOCK_TIME]);
    }

    /**
     * 解锁（移植 unlock）
     */
    public static function unlock(string $key, int $uid): void
    {
        Redis::del($key . $uid);
    }

    /**
     * @return array{uid: int, masterid: int, dateline: int}|null
     */
    private static function findMasterLog(int $uid): ?array
    {
        $row = DB::table('refine_master_log')->select('uid', 'masterid', 'dateline')->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }
}
