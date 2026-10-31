<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\AdvancedInfo;
use Hanjiang\MasterData\AdvancedSkill;
use OmniPHP\Database\DB;

/**
 * 名将进阶（移植旧版 models/advanced_model.php 的客户端用到的方法）
 *
 * 存储同旧版：advanced_log 每个将领一行（uid + gid 唯一）：quality 品级（1 ~ 7），skill1 ~ skill4 为四个被动技能
 * （1 物攻 / 策攻、2 物防、3 策防、4 生命）的等级 / 名称 / 加成（加到战斗的天赋系数上），wuli / zhili / tili / hp 为进阶累计的属性；
 * advanced_inherit_log 为进阶传承记录；member_field.advanced_time 为上次进阶传承时间。
 * 配置：进阶 AdvancedInfo（按品级 id），被动技能 AdvancedSkill（按 type + level）。
 *
 * 不移植：sync_skill_info（计划任务 cron::update_advanced_log，改配置后同步已有技能加成，运维用）、
 * get_inherit_log / recover_inherit（后台恢复进阶传承，客户端不调用）。
 */
final class Advanced
{
    /** advanced_log 的旧版字段（get_general_skill 的输出） */
    private const array FIELDS = [
        'uid', 'gid', 'quality',
        'skill1_level', 'skill2_level', 'skill3_level', 'skill4_level',
        'skill1_name', 'skill2_name', 'skill3_name', 'skill4_name',
        'skill1_value', 'skill2_value', 'skill3_value', 'skill4_value',
        'wuli', 'zhili', 'tili', 'hp',
    ];

    /**
     * 将领的进阶信息（移植 get_general_skill：没有记录时按品级 1、技能 0 级插入一行；技能名称取 AdvancedSkill 各类型第一行）
     *
     * 旧版不检查将领是否属于该玩家（按 uid + gid 建档）；这里同样不检查，调用方先校验将领。
     *
     * @return array<string, mixed>
     */
    public static function generalSkill(int $gid, int $uid): array
    {
        $row = self::find($gid, $uid);
        if ($row !== null) {
            return $row;
        }
        $names = self::skillNames();
        $info = [
            'uid' => $uid, 'gid' => $gid, 'quality' => 1,
            'skill1_level' => 0, 'skill2_level' => 0, 'skill3_level' => 0, 'skill4_level' => 0,
            'skill1_name' => $names[1] ?? '', 'skill2_name' => $names[2] ?? '', 'skill3_name' => $names[3] ?? '', 'skill4_name' => $names[4] ?? '',
            'skill1_value' => 0, 'skill2_value' => 0, 'skill3_value' => 0, 'skill4_value' => 0,
            'wuli' => 0, 'zhili' => 0, 'tili' => 0, 'hp' => 0,
        ];
        // 并发首次进入时只插入一行（uid + gid 唯一），插入失败说明另一请求刚建档，读回那一行
        $affected = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_advanced_log` (`uid`, `gid`, `quality`, `skill1_name`, `skill2_name`, `skill3_name`, `skill4_name`)
             VALUES (:uid, :gid, 1, :n1, :n2, :n3, :n4)',
            ['uid' => $uid, 'gid' => $gid, 'n1' => $info['skill1_name'], 'n2' => $info['skill2_name'], 'n3' => $info['skill3_name'], 'n4' => $info['skill4_name']]
        );
        return $affected > 0 ? $info : (self::find($gid, $uid) ?? $info);
    }

    /**
     * 多个将领的进阶信息（移植 get_advanced_by_gid，[gid => 行]；没有记录的将领不在结果里，不建档）
     *
     * 旧版只按 gid 查（将领 id 全服唯一）；新版同样全服唯一，另加 uid 条件结果相同。
     *
     * @param list<int> $gidArr
     * @return array<int, array<string, mixed>>
     */
    public static function byGids(int $uid, array $gidArr): array
    {
        if ($gidArr === []) {
            return [];
        }
        $rows = DB::table('advanced_log')->select(...self::FIELDS)
            ->where('uid', $uid)->whereIn('gid', $gidArr)->whereNull('deleted_at')->get();
        $return = [];
        foreach ($rows as $row) {
            $row = self::cast($row);
            $return[$row['gid']] = $row;
        }
        return $return;
    }

    /**
     * 进阶配置（移植 get_advanced_info）；不存在返回 null
     *
     * @return array<string, int>|null
     */
    public static function advancedInfo(int $quality): ?array
    {
        return AdvancedInfo::ROWS[$quality] ?? null;
    }

    /**
     * 被动技能某一级的升级信息（移植 get_skill_info：type + level 的第一行）；不存在返回 null
     *
     * @return array<string, mixed>|null
     */
    public static function skillInfo(int $type, int $level): ?array
    {
        foreach (AdvancedSkill::ROWS as $row) {
            if ($row['type'] === $type && $row['level'] === $level) {
                return $row;
            }
        }
        return null;
    }

    /**
     * 技能升级（移植 update_skill_info：只改该技能的等级与加成）
     *
     * 旧版直接写入新等级；这里加上「当前等级 = 新等级 - 1」的条件，并发重复升级只成功一次（另一次返回 0，同旧版的失败）。
     *
     * @return int 1 成功，0 失败
     */
    public static function updateSkill(int $uid, int $gid, int $type, int $level, float $value): int
    {
        $affected = DB::table('advanced_log')
            ->where('uid', $uid)->where('gid', $gid)->where('skill' . $type . '_level', $level - 1)->whereNull('deleted_at')
            ->update(['skill' . $type . '_level' => $level, 'skill' . $type . '_value' => $value]);
        return $affected > 0 ? 1 : 0;
    }

    /**
     * 进阶（移植 update_advanced_info：写入品级与累计属性，并把 general.quality 改为新品级）
     *
     * 旧版直接写入；这里加上「当前品级 = 新品级 - 1」的条件，并发重复进阶只写一次。
     *
     * @param array{quality: int, wuli: int, zhili: int, tili: int, hp: int} $info
     * @return int 1 成功，0 失败
     */
    public static function updateAdvanced(int $uid, int $gid, array $info): int
    {
        $affected = DB::table('advanced_log')
            ->where('uid', $uid)->where('gid', $gid)->where('quality', $info['quality'] - 1)->whereNull('deleted_at')
            ->update([
                'quality' => $info['quality'],
                'wuli' => $info['wuli'],
                'zhili' => $info['zhili'],
                'tili' => $info['tili'],
                'hp' => $info['hp'],
            ]);
        if ($affected === 0) {
            return 0;
        }
        DB::table('general')->where('uid', $uid)->where('id', $gid)->update(['quality' => $info['quality']]);
        return 1;
    }

    /**
     * 整体写入将领进阶数据（移植 update_advanced_all_info：$info 为空时清成品级 1、技能 0 级、属性 0；同步 general.quality）
     *
     * @param array<string, mixed> $info
     * @return int 1 有改动，0 没有
     */
    public static function updateAll(array $info, int $gid, int $uid): int
    {
        if ($info === []) {
            $data = ['quality' => 1, 'wuli' => 0, 'zhili' => 0, 'tili' => 0, 'hp' => 0];
            for ($i = 1; $i <= 4; $i++) {
                $data['skill' . $i . '_level'] = 0;
                $data['skill' . $i . '_value'] = 0;
            }
        } else {
            $data = [
                'quality' => $info['quality'],
                'wuli' => $info['wuli'],
                'zhili' => $info['zhili'],
                'tili' => $info['tili'],
                'hp' => $info['hp'],
            ];
            for ($i = 1; $i <= 4; $i++) {
                $data['skill' . $i . '_level'] = $info['skill' . $i . '_level'];
                $data['skill' . $i . '_value'] = $info['skill' . $i . '_value'];
            }
        }
        $affected = DB::table('advanced_log')->where('gid', $gid)->where('uid', $uid)->whereNull('deleted_at')->update($data);
        if ($affected === 0) {
            return 0;
        }
        DB::table('general')->where('uid', $uid)->where('id', $gid)->update(['quality' => $data['quality']]);
        return 1;
    }

    /**
     * 进阶传承 CD 起点（移植 update_advanced_dateline：member_field.advanced_time）
     */
    public static function updateDateline(int $uid, int $now): void
    {
        DB::table('member_field')->where('uid', $uid)->update(['advanced_time' => $now]);
    }

    /**
     * 上次进阶传承时间（旧版 $this->member_field['advanced_time']）
     */
    public static function dateline(int $uid): int
    {
        return (int)(DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->value('advanced_time') ?? 0);
    }

    /**
     * 进阶传承记录（移植 add_inherit_log：两个将领传承前的进阶数据）
     *
     * @param array<string, mixed> $gid1 被传承将领的进阶行
     * @param array<string, mixed> $gid2 受益将领的进阶行
     */
    public static function addInheritLog(int $uid, array $gid1, array $gid2, int $now): void
    {
        $data = ['uid' => $uid, 'dateline' => $now];
        foreach ([1 => $gid1, 2 => $gid2] as $i => $info) {
            $data['gid' . $i] = $info['gid'];
            $data['g' . $i . '_quality'] = $info['quality'];
            $data['g' . $i . '_wuli'] = $info['wuli'];
            $data['g' . $i . '_zhili'] = $info['zhili'];
            $data['g' . $i . '_tili'] = $info['tili'];
            $data['g' . $i . '_hp'] = $info['hp'];
            for ($j = 1; $j <= 4; $j++) {
                $data['g' . $i . '_level' . $j] = $info['skill' . $j . '_level'];
                $data['g' . $i . '_value' . $j] = $info['skill' . $j . '_value'];
            }
        }
        DB::table('advanced_inherit_log')->insert($data);
    }

    /**
     * 被动技能品级（get_skill_info / level_up 的写法）：0 级为 1；18 级以下 ceil(等级 / 3)，19 级起 floor(等级 / 3)，上限 $max
     */
    public static function skillQuality(int $level, int $max): int
    {
        if ($level <= 0) {
            return 1;
        }
        return (int)min($level > 18 ? floor($level / 3) : ceil($level / 3), $max);
    }

    /**
     * 技能名称（移植 get_skill_name：SELECT type, name ... GROUP BY type，取各类型第一行）
     *
     * @return array<int, string>
     */
    private static function skillNames(): array
    {
        $list = [];
        foreach (AdvancedSkill::ROWS as $row) {
            $list[$row['type']] ??= $row['name'];
        }
        return $list;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function find(int $gid, int $uid): ?array
    {
        $row = DB::table('advanced_log')->select(...self::FIELDS)
            ->where('gid', $gid)->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : self::cast($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function cast(array $row): array
    {
        foreach (self::FIELDS as $field) {
            $row[$field] = match (true) {
                str_ends_with($field, '_name') => (string)$row[$field],
                str_ends_with($field, '_value') => (float)$row[$field],
                default => (int)$row[$field],
            };
        }
        return $row;
    }
}
