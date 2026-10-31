<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\Handler\Pvp\SiegeHandler;
use Hanjiang\MasterData\BeautyAdvanced;
use Hanjiang\MasterData\BeautyChip;
use Hanjiang\MasterData\BeautyDialogue;
use Hanjiang\MasterData\BeautyLootBox;
use Hanjiang\MasterData\BeautyPamper;
use Hanjiang\MasterData\BeautySkill;
use Hanjiang\MasterData\BeautyTemp;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 铜雀台（移植旧版 models/beauty_model.php，路径相对旧版 s1/application/）
 *
 * 配置（MasterData）：美人模板 BeautyTemp、进阶 BeautyAdvanced、碎片 BeautyChip、对话 BeautyDialogue、
 * 抢夺宝箱 BeautyLootBox、互动奖励 BeautyPamper、技能 BeautySkill。
 * 玩家数据同旧版表：beauty_own_log（拥有的美人）、beauty_own_chip（碎片）、beauty_info（免战 / 抢夺次数）、
 * beauty_loot_list（抢夺对手列表）、beauty_loot_log（抢夺日志）、beauty_active_log（互动次数）、
 * beauty_buyloot_log / beauty_buyrose_log（购买日志）、beauty_speical_reward_log（特殊技能领取日志）。
 *
 * 与旧版的差别（多 Worker 安全）：
 * - beauty_own_log (uid, beauty_id)、beauty_own_chip (uid, beauty_id, chip_id)、beauty_active_log uid 加了唯一键，
 *   初始化行用 INSERT IGNORE；等级 / 品级 / 碎片 / 互动次数用一条 UPDATE 原子增减（旧版读出后写回绝对值）。
 * - 碎片减到 0 以下时按 0 写（旧版无符号列，非严格模式截到 0）。
 */
final class Beauty
{
    /** 每天免费抢夺次数 */
    public const int LOOT_FREE_TIMES = 10;
    /** 每天可以互动的次数 */
    public const int ACTIVE_TIMES = 5;
    /** 互动问候冷却时间 */
    public const int GREET_TIME = 0;
    /** 免战持续时间 */
    public const int BATTLE_HOUR = 3600;
    /** 每天的免战次数 */
    public const int BATTLE_NUM = 5;
    /** 每天购买抢夺次数 */
    public const int VIP_TIMES = 10;
    /** 升级持续时间 */
    public const int LEVELUP_HOUR = 3600;

    /** 抢夺战斗锁（_lott_key{uid}，500 秒） */
    private const string LOTT_KEY = '_lott_key';
    private const int LOTT_TIME = 500;
    /** 接口防刷锁（{锁名}{uid}，30 秒） */
    private const int LOCK_TIME = 30;

    /** beauty_own_log 旧版列 */
    private const array OWN_COLUMNS = [
        'id', 'uid', 'beauty_id', 'beauty_name', 'beauty_level', 'skill1', 'skill2', 'skill3', 'quality', 'dateline', 'open_status',
        'up_status', 'up_dateline', 'effect_time', 'effect_end_time', 'ratio', 'ratio_time', 'is_play', 'wakeup_num', 'wakeup_dateline',
    ];
    /** beauty_own_chip 旧版列 */
    private const array CHIP_COLUMNS = ['id', 'uid', 'beauty_id', 'chip', 'chip_id', 'chip_name', 'dateline', 'disable'];
    /** beauty_info 旧版列 */
    private const array INFO_COLUMNS = ['id', 'uid', 'vip', 'times', 'cd_time', 'buy_time', 'free_times', 'vip_times', 'reflush_dateline', 'dateline'];
    /** beauty_loot_log 旧版列 */
    private const array LOOT_LOG_COLUMNS = [
        'id', 'uid', 'nickname', 'to_uid', 'to_name', 'chip_id', 'beauty_id', 'reward_id', 'chip_status', 'battle_status', 'report_id', 'dateline',
    ];

    // ---------------------------------------------------------------------------------------------
    // 配置
    // ---------------------------------------------------------------------------------------------

    /**
     * 美人对话（get_dialogue）
     *
     * @return list<array<string, mixed>>
     */
    public static function dialogue(int $bid, int $type): array
    {
        $return = [];
        foreach (BeautyDialogue::ROWS as $row) {
            if ($row['beauty_id'] === $bid && $row['type'] === $type) {
                $return[] = $row;
            }
        }
        return $return;
    }

    /**
     * 美人模板（get_beauty_info($id)）；不存在为空数组
     *
     * @return array<string, mixed>
     */
    public static function template(int $id): array
    {
        return BeautyTemp::ROWS[$id] ?? [];
    }

    /**
     * 全部美人模板（get_beauty_info()，按 id）
     *
     * @return list<array<string, mixed>>
     */
    public static function templates(): array
    {
        return array_values(BeautyTemp::ROWS);
    }

    /**
     * 技能表（get_beauty_skill：$ids 为数组时按属性 IN；否则属性等于 $ids，$type 1 时属性 <= $ids）
     *
     * @param int|list<int> $ids
     * @return list<array<string, mixed>>
     */
    public static function skill(int $bid, int|array $ids, int $level = 0, int $type = 0): array
    {
        $return = [];
        foreach (BeautySkill::ROWS as $row) {
            if ($row['beauty_id'] !== $bid || $row['skill_level'] !== $level) {
                continue;
            }
            $match = is_array($ids)
                ? in_array($row['skill_property'], $ids, true)
                : ($type === 1 ? $row['skill_property'] <= $ids : $row['skill_property'] === $ids);
            if ($match) {
                $return[] = $row;
            }
        }
        return $return;
    }

    /**
     * 一条技能（get_beauty_skill_info：属性 4 5 6 7 12 的值带「%」）；查不到为空数组
     *
     * @return array<string, mixed>
     */
    public static function skillInfo(int $bid, int $property, int $level = 0): array
    {
        $row = self::skill($bid, $property, $level)[0] ?? [];
        if ($row !== [] && in_array($row['skill_property'], [4, 5, 6, 7, 12], true)) {
            $row['skill_value'] = $row['skill_value'] . '%';
        }
        return $row;
    }

    /**
     * 某美人某等级的全部技能（get_beauty_skill_level，按 id）
     *
     * @return list<array<string, mixed>>
     */
    public static function skillLevel(int $bid, int $level): array
    {
        $return = [];
        foreach (BeautySkill::ROWS as $row) {
            if ($row['beauty_id'] === $bid && $row['skill_level'] === $level) {
                $return[] = $row;
            }
        }
        return $return;
    }

    /**
     * 某美人若干等级的技能（get_beauty_skill_by_level，按 id）
     *
     * @param list<int> $levels
     * @return list<array<string, mixed>>
     */
    public static function skillByLevel(int $bid, array $levels): array
    {
        $return = [];
        foreach (BeautySkill::ROWS as $row) {
            if ($row['beauty_id'] === $bid && in_array($row['skill_level'], $levels, true)) {
                $return[] = $row;
            }
        }
        return $return;
    }

    /**
     * 按技能属性与等级取值（get_value_by_type）；查不到为空数组
     *
     * @return array<string, mixed>
     */
    public static function valueByType(int $level, int $property, int $bid): array
    {
        foreach (BeautySkill::ROWS as $row) {
            if ($row['skill_level'] === $level && $row['skill_property'] === $property && $row['beauty_id'] === $bid) {
                return $row;
            }
        }
        return [];
    }

    /**
     * 美人碎片模板（get_beauty_chip_info：$chipId 为 0 时该美人的全部碎片，否则一条；查不到为空数组）
     *
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    public static function chipInfo(int $bid, int $chipId = 0): array
    {
        $return = [];
        foreach (BeautyChip::ROWS as $row) {
            if ($row['beauty_id'] !== $bid) {
                continue;
            }
            if ($chipId > 0) {
                if ($row['chip_id'] === $chipId) {
                    return $row;
                }
                continue;
            }
            $return[] = $row;
        }
        return $return;
    }

    /**
     * 进阶表（get_advanced_info()：按 level 索引，同一等级后面的行覆盖前面的）
     *
     * @return array<int, array<string, mixed>>
     */
    public static function advancedInfo(): array
    {
        $return = [];
        foreach (BeautyAdvanced::ROWS as $row) {
            $return[$row['level']] = $row;
        }
        return $return;
    }

    /**
     * 抢夺宝箱（get_loot_reward）
     *
     * @return list<array<string, mixed>>
     */
    public static function lootReward(): array
    {
        return array_values(BeautyLootBox::ROWS);
    }

    /**
     * 互动奖励（get_active_info）
     *
     * @return list<array<string, mixed>>
     */
    public static function activeInfo(int $type): array
    {
        $return = [];
        foreach (BeautyPamper::ROWS as $row) {
            if ($row['pamper_type'] === $type) {
                $return[] = $row;
            }
        }
        return $return;
    }

    /**
     * 好感度标识（get_happy_ratio）：0-20 伤感 / 21-40 冷淡 / 41-60 平静 / 61-80 高兴 / 81-100 欢愉；不在 0 ~ 100 为空数组
     *
     * @return array{quality?: int, skill?: int}
     */
    public static function happyRatio(int|float $ratio): array
    {
        if ($ratio >= 0 && $ratio <= 20) {
            return ['quality' => 0, 'skill' => 25];
        } elseif ($ratio >= 21 && $ratio <= 40) {
            return ['quality' => 1, 'skill' => 50];
        } elseif ($ratio >= 41 && $ratio <= 60) {
            return ['quality' => 2, 'skill' => 75];
        } elseif ($ratio >= 61 && $ratio <= 80) {
            return ['quality' => 3, 'skill' => 100];
        } elseif ($ratio >= 81 && $ratio <= 100) {
            return ['quality' => 4, 'skill' => 125];
        }
        return [];
    }

    /**
     * 进阶或升级按钮（get_up_status）：等级是 20 的倍数为 2 进阶（含 0 与 80，旧版「80 满级」分支走不到），否则 1 升级；
     * lock 为已解锁技能数（0-20 1 个，21-60 2 个，61-80 3 个，其他 0）
     *
     * @return array{up_status: int, lock: int}
     */
    public static function upStatus(int $level): array
    {
        $return = ['up_status' => $level % 20 === 0 ? 2 : 1, 'lock' => 0];
        if ($level >= 0 && $level <= 20) {
            $return['lock'] = 1;
        } elseif ($level >= 21 && $level <= 60) {
            $return['lock'] = 2;
        } elseif ($level >= 61 && $level <= 80) {
            $return['lock'] = 3;
        }
        return $return;
    }

    // ---------------------------------------------------------------------------------------------
    // 拥有的美人 beauty_own_log
    // ---------------------------------------------------------------------------------------------

    /**
     * 玩家拥有的某个美人（get_beauty_own_log($beauty_id, $uid)）；没有为空数组
     *
     * @return array<string, mixed>
     */
    public static function own(int $uid, int $bid): array
    {
        $row = DB::table('beauty_own_log')->select(...self::OWN_COLUMNS)->where('uid', $uid)->where('beauty_id', $bid)
            ->whereNull('deleted_at')->first();
        return $row === null ? [] : self::castOwn($row);
    }

    /**
     * 玩家拥有的全部美人（get_beauty_own_log(0, $uid)，按 beauty_id）
     *
     * @return list<array<string, mixed>>
     */
    public static function ownAll(int $uid): array
    {
        $rows = DB::table('beauty_own_log')->select(...self::OWN_COLUMNS)->where('uid', $uid)->whereNull('deleted_at')
            ->orderBy('beauty_id')->get();
        return array_map(self::castOwn(...), $rows);
    }

    /**
     * 激活中的美人数（get_beauty_online_status）
     */
    public static function onlineCount(int $uid): int
    {
        return DB::table('beauty_own_log')->where('uid', $uid)->where('open_status', 1)->whereNull('deleted_at')->count();
    }

    /**
     * 激活新美人（add_beauty_own_log：1 级、品级 1、激活）
     *
     * @param array<string, mixed> $info 美人模板
     */
    public static function addOwn(int $uid, int $bid, array $info, int $now): void
    {
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_beauty_own_log`
             (`uid`, `beauty_id`, `beauty_level`, `beauty_name`, `quality`, `skill1`, `skill2`, `skill3`, `dateline`, `open_status`, `up_status`, `ratio`, `wakeup_num`, `wakeup_dateline`)
             VALUES (:uid, :bid, 1, :name, 1, :s1, :s2, :s3, :now, 1, 0, 0, 0, 0)',
            ['uid' => $uid, 'bid' => $bid, 'name' => $info['name'], 's1' => $info['skill1'], 's2' => $info['skill2'], 's3' => $info['skill3'], 'now' => $now]
        );
    }

    /**
     * 初始化美人（init_beauty_own_log：1 级、品级 1、休息；同一数组里 quality 先写 0 后写 1，以后者为准）
     *
     * @return array<string, mixed> 插入的数据（同旧版返回 $data）
     */
    public static function initOwn(int $uid, int $bid, int $now): array
    {
        $info = self::template($bid);
        $data = [
            'uid' => $uid,
            'beauty_id' => $bid,
            'beauty_name' => $info['name'] ?? '',
            'beauty_level' => 1,
            'quality' => 1,
            'skill1' => $info['skill1'] ?? 0,
            'skill2' => $info['skill2'] ?? 0,
            'skill3' => $info['skill3'] ?? 0,
            'dateline' => $now,
            'open_status' => 0,
            'up_status' => 0,
            'ratio' => 0,
            'ratio_time' => 0,
        ];
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_beauty_own_log`
             (`uid`, `beauty_id`, `beauty_name`, `beauty_level`, `quality`, `skill1`, `skill2`, `skill3`, `dateline`, `open_status`, `up_status`, `ratio`, `ratio_time`, `wakeup_num`, `wakeup_dateline`)
             VALUES (:uid, :bid, :name, 1, 1, :s1, :s2, :s3, :now, 0, 0, 0, 0, 0, 0)',
            ['uid' => $uid, 'bid' => $bid, 'name' => $data['beauty_name'], 's1' => $data['skill1'], 's2' => $data['skill2'], 's3' => $data['skill3'], 'now' => $now]
        );
        return $data;
    }

    /**
     * 改 beauty_own_log 的若干列
     *
     * @param array<string, int> $data
     */
    public static function updateOwn(int $uid, int $bid, array $data): int
    {
        return DB::table('beauty_own_log')->where('uid', $uid)->where('beauty_id', $bid)->whereNull('deleted_at')->update($data);
    }

    /**
     * 增加好感度（update_beauty_happy_info：先按每小时减 1 衰减当前好感度再加 $num，最多 100；衰减到 0 以下时直接为 $num）
     *
     * @return array<string, int> ratio / ratio_time（写入成功另带 status 1）
     */
    public static function updateHappy(int $uid, int $bid, int $num, int $now): array
    {
        $result = self::own($uid, $bid);
        if ($result === []) {
            $result = self::initOwn($uid, $bid, $now);
        }
        $lastTime = $now - (int)$result['ratio_time'];
        $lastTime = $lastTime > 0 ? $lastTime : 0;
        // 当前好感度的值
        $ratio = (int)$result['ratio'] - (int)ceil($lastTime / 3600) + 1;
        if ($ratio <= 0) {
            $afterRatio = $num;
        } else {
            $afterRatio = $ratio + $num;
            if ($afterRatio >= 100) {
                $afterRatio = 100;
            }
        }
        $return = ['ratio' => $afterRatio, 'ratio_time' => $now];
        if (self::updateOwn($uid, $bid, $return) > 0) {
            $return['status'] = 1;
        }
        return $return;
    }

    /**
     * 激活 / 休息（update_beauty_active：1 激活；0 休息并清技能生效时间；2 只清技能生效时间）
     *
     * @return array{status: int}
     */
    public static function updateActive(int $uid, int $bid, int $type = 1): array
    {
        $data = match ($type) {
            1 => ['open_status' => 1],
            0 => ['open_status' => 0, 'effect_time' => 0, 'effect_end_time' => 0],
            2 => ['effect_time' => 0, 'effect_end_time' => 0],
            default => [],
        };
        if ($data === []) {
            return ['status' => 0];
        }
        return ['status' => self::updateOwn($uid, $bid, $data) > 0 ? 1 : 0];
    }

    /**
     * 升级时间和状态（update_level_status：1 开始升级（up_dateline 为一小时后）；0 清除；2 清除并标记播放升级动画）
     *
     * @return array<string, int> 写入成功时为写入的数据，否则为空数组
     */
    public static function updateLevelStatus(int $uid, int $bid, int $type, int $now): array
    {
        $data = ['up_status' => 1, 'up_dateline' => $now + self::LEVELUP_HOUR];
        if ($type === 0) {
            $data = ['up_status' => 0, 'up_dateline' => 0, 'is_play' => 0];
        } elseif ($type === 2) {
            $data = ['up_status' => 0, 'up_dateline' => 0, 'is_play' => 2];
        }
        return self::updateOwn($uid, $bid, $data) > 0 ? $data : [];
    }

    /**
     * 等级 + 1（update_beauty_level）
     */
    public static function updateLevel(int $uid, int $bid): int
    {
        return DB::table('beauty_own_log')->where('uid', $uid)->where('beauty_id', $bid)->whereNull('deleted_at')->increment('beauty_level', 1);
    }

    /**
     * 品级 + 1（update_beauty_quality）
     */
    public static function updateQuality(int $uid, int $bid): int
    {
        return DB::table('beauty_own_log')->where('uid', $uid)->where('beauty_id', $bid)->whereNull('deleted_at')->increment('quality', 1);
    }

    /**
     * 激活信息（update_beauty_wakeupinfo：$type > 0 时 wakeup_num + 1、wakeup_dateline 记当前时间；否则两者清 0）
     */
    public static function updateWakeupInfo(int $uid, int $bid, int $type, int $now): int
    {
        if ($type > 0) {
            return DB::execute(
                'UPDATE `zc_sanguo_beauty_own_log` SET `wakeup_num` = `wakeup_num` + 1, `wakeup_dateline` = :now
                 WHERE `uid` = :uid AND `beauty_id` = :bid AND `deleted_at` IS NULL',
                ['now' => $now, 'uid' => $uid, 'bid' => $bid]
            );
        }
        return self::updateOwn($uid, $bid, ['wakeup_num' => 0, 'wakeup_dateline' => 0]);
    }

    /**
     * 技能生效结束时间延长（update_beauty_info($numval, 'effect_end_time', $bid)：已过期时从当前时间算起并记 effect_time）
     *
     * @return array<string, int> 写入成功时为写入的数据，否则为空数组
     */
    public static function extendEffect(int $uid, int $bid, int $numval, int $now): array
    {
        $info = self::own($uid, $bid);
        $end = (int)($info['effect_end_time'] ?? 0);
        $data = [];
        if ($now > $end) {
            $end = $now;
            $data['effect_time'] = $now;
        }
        $data['effect_end_time'] = $end + $numval;
        return self::updateOwn($uid, $bid, $data) > 0 ? $data : [];
    }

    // ---------------------------------------------------------------------------------------------
    // 碎片 beauty_own_chip
    // ---------------------------------------------------------------------------------------------

    /**
     * 玩家某美人的全部碎片（get_beauty_chip($beauty_id, 0, $uid)，按插入顺序）
     *
     * @return list<array<string, mixed>>
     */
    public static function chips(int $uid, int $bid): array
    {
        $rows = DB::table('beauty_own_chip')->select(...self::CHIP_COLUMNS)->where('beauty_id', $bid)->where('uid', $uid)
            ->whereNull('deleted_at')->orderBy('id')->get();
        return array_map(self::castChip(...), $rows);
    }

    /**
     * 玩家某美人的某个碎片（get_beauty_chip($beauty_id, $chip_id, $uid)）；没有为空数组
     *
     * @return array<string, mixed>
     */
    public static function chip(int $uid, int $bid, int $chipId): array
    {
        $row = DB::table('beauty_own_chip')->select(...self::CHIP_COLUMNS)->where('beauty_id', $bid)->where('uid', $uid)
            ->where('chip_id', $chipId)->whereNull('deleted_at')->first();
        return $row === null ? [] : self::castChip($row);
    }

    /**
     * 建美人碎片（add_beauty_chip_info：没有拥有记录时先初始化美人；没有碎片时按模板插入全部碎片，数量 0）
     */
    public static function addChipInfo(int $uid, int $bid, int $now): void
    {
        if (self::own($uid, $bid) === []) {
            self::initOwn($uid, $bid, $now);
        }
        if (self::chips($uid, $bid) !== []) {
            return;
        }
        foreach (self::chipInfo($bid) as $val) {
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_beauty_own_chip` (`uid`, `beauty_id`, `chip`, `chip_id`, `chip_name`, `dateline`, `disable`)
                 VALUES (:uid, :bid, 0, :chip_id, :name, :now, 0)',
                ['uid' => $uid, 'bid' => $bid, 'chip_id' => $val['chip_id'], 'name' => $val['chip_name'], 'now' => $now]
            );
        }
    }

    /**
     * 碎片数量增减（update_chip_num：没有该碎片时先建碎片；减到 0 以下按 0）
     *
     * @return array{status: int}
     */
    public static function updateChipNum(int $uid, int $bid, int $chipId, int $num, int $now): array
    {
        if (self::chip($uid, $bid, $chipId) === []) {
            self::addChipInfo($uid, $bid, $now);
        }
        $affected = DB::execute(
            'UPDATE `zc_sanguo_beauty_own_chip` SET `chip` = GREATEST(CAST(`chip` AS SIGNED) + :num, 0)
             WHERE `uid` = :uid AND `beauty_id` = :bid AND `chip_id` = :chip_id AND `deleted_at` IS NULL',
            ['num' => $num, 'uid' => $uid, 'bid' => $bid, 'chip_id' => $chipId]
        );
        return ['status' => $affected > 0 ? 1 : 0];
    }

    /**
     * 碎片减 1（被抢夺），只在还有碎片时减；返回是否减成
     */
    public static function takeChip(int $uid, int $bid, int $chipId): bool
    {
        return DB::execute(
            'UPDATE `zc_sanguo_beauty_own_chip` SET `chip` = `chip` - 1
             WHERE `uid` = :uid AND `beauty_id` = :bid AND `chip_id` = :chip_id AND `chip` > 0 AND `deleted_at` IS NULL',
            ['uid' => $uid, 'bid' => $bid, 'chip_id' => $chipId]
        ) > 0;
    }

    /**
     * 每个碎片减 1（update_beauty_chip：碎片转化）
     */
    public static function subAllChips(int $uid, int $bid): void
    {
        DB::execute(
            'UPDATE `zc_sanguo_beauty_own_chip` SET `chip` = GREATEST(CAST(`chip` AS SIGNED) - 1, 0)
             WHERE `uid` = :uid AND `beauty_id` = :bid AND `deleted_at` IS NULL',
            ['uid' => $uid, 'bid' => $bid]
        );
    }

    /**
     * 可抢夺的碎片持有者（get_loot_num_info：该碎片 chip > 0、disable = 0、不是自己，前 $limit 条）
     *
     * @return list<array<string, mixed>>
     */
    public static function lootCandidates(int $bid, int $chipId, int $limit, int $uid): array
    {
        $rows = DB::table('beauty_own_chip')->select(...self::CHIP_COLUMNS)->where('beauty_id', $bid)->where('chip_id', $chipId)
            ->where('chip', '>', 0)->where('disable', 0)->where('uid', '!=', $uid)->whereNull('deleted_at')
            ->orderBy('id')->limit($limit)->get();
        return array_map(self::castChip(...), $rows);
    }

    // ---------------------------------------------------------------------------------------------
    // 玩家信息 beauty_info（免战 / 抢夺次数）
    // ---------------------------------------------------------------------------------------------

    /**
     * 玩家信息（get_beauty_user_info）；没有为空数组
     *
     * 免战购买时间不是今天时把免战次数重置为 5、购买时间记当前时间，但返回的仍是重置前读出的行（旧版如此）。
     *
     * @return array<string, mixed>
     */
    public static function userInfo(int $uid, int $now): array
    {
        $info = self::infoRow($uid);
        if ($info === null) {
            return [];
        }
        if (date('ymd', $info['buy_time']) !== date('ymd', $now) && $info['buy_time'] > 0) {
            DB::table('beauty_info')->where('uid', $uid)->whereNull('deleted_at')->update(['times' => self::BATTLE_NUM, 'buy_time' => $now]);
        }
        return $info;
    }

    /**
     * 初始化玩家信息（init_battle_user_info）
     */
    public static function initUserInfo(int $uid, int $vip, int $now): void
    {
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_beauty_info` (`uid`, `vip`, `times`, `cd_time`, `free_times`, `vip_times`, `reflush_dateline`, `dateline`)
             VALUES (:uid, :vip, :times, 0, :free, :vip_times, 0, :now)',
            ['uid' => $uid, 'vip' => $vip, 'times' => self::BATTLE_NUM, 'free' => self::LOOT_FREE_TIMES, 'vip_times' => self::VIP_TIMES, 'now' => $now]
        );
    }

    /**
     * 改玩家信息（modify_beauty_user_info / update_loot_info）
     *
     * @param array<string, int> $data
     */
    public static function updateUserInfo(int $uid, array $data): int
    {
        return DB::table('beauty_info')->where('uid', $uid)->whereNull('deleted_at')->update($data);
    }

    /**
     * 抢夺次数原子减 1（loot_action 的 free_times - 1）；次数不够不扣
     */
    public static function useFreeTimes(int $uid): bool
    {
        return DB::execute(
            'UPDATE `zc_sanguo_beauty_info` SET `free_times` = `free_times` - 1 WHERE `uid` = :uid AND `free_times` > 0 AND `deleted_at` IS NULL',
            ['uid' => $uid]
        ) > 0;
    }

    /**
     * 购买抢夺次数（buy_loot_times：free_times + 1、vip_times - 1）；剩余购买次数不够不改
     */
    public static function buyLootTimes(int $uid): bool
    {
        return DB::execute(
            'UPDATE `zc_sanguo_beauty_info` SET `free_times` = `free_times` + 1, `vip_times` = `vip_times` - 1
             WHERE `uid` = :uid AND `vip_times` > 0 AND `deleted_at` IS NULL',
            ['uid' => $uid]
        ) > 0;
    }

    /**
     * 免战（update_beauty_user_info：次数 - 1，免战结束时间在未过期时累加一小时，否则从当前时间算起）
     *
     * @return array<string, int> 写入成功时为写入的数据，否则为空数组
     */
    public static function peace(int $uid, int $vip, int $now): array
    {
        $battleInfo = self::userInfo($uid, $now);
        if ($battleInfo !== []) {
            $times = (int)$battleInfo['times'];
        } else {
            self::initUserInfo($uid, $vip, $now);
            $times = self::BATTLE_NUM;
            $battleInfo = self::userInfo($uid, $now);
        }
        // 当前免战生效时间点
        $addTime = (int)($battleInfo['cd_time'] ?? 0);
        // 免战时间累加
        if ($addTime > 0) {
            if ($now >= $addTime) {
                $addTime = $now;
            }
        } else {
            $addTime = $now;
        }
        $data = [
            'times' => max(0, $times - 1),
            'cd_time' => $addTime + self::BATTLE_HOUR,
            'buy_time' => $now,
        ];
        return self::updateUserInfo($uid, $data) > 0 ? $data : [];
    }

    /**
     * 抢夺界面玩家信息（get_loot_info：没有时初始化）
     *
     * @return array<string, mixed>
     */
    public static function lootInfo(int $uid, int $vip, int $now): array
    {
        $row = self::infoRow($uid);
        if ($row !== null) {
            return $row;
        }
        self::initUserInfo($uid, $vip, $now);
        return self::userInfo($uid, $now);
    }

    /**
     * 是否在免战中（get_loot_info_list：cd_time >= 当前时间的行）
     */
    public static function inPeace(int $uid, int $now): bool
    {
        return DB::table('beauty_info')->where('uid', $uid)->where('cd_time', '>=', $now)->whereNull('deleted_at')->exists();
    }

    /**
     * 每天重置抢夺次数（update_loot_times：dateline 不是今天时免费次数重置为 10（双倍活动 20）、购买次数 10）
     */
    public static function updateLootTimes(int $uid, bool $isDouble, int $vip, int $now): void
    {
        $free = self::LOOT_FREE_TIMES;
        if ($isDouble) {
            $free = min($free * 2, 20);
        }
        $info = self::lootInfo($uid, $vip, $now);
        if ($info !== [] && date('ymd', (int)$info['dateline']) !== date('ymd', $now)) {
            // 条件里带上读到的 dateline，多 Worker 同时首请求只重置一次
            DB::table('beauty_info')->where('uid', $uid)->where('dateline', (int)$info['dateline'])->whereNull('deleted_at')->update([
                'free_times' => $free,
                'vip_times' => self::VIP_TIMES,
                'dateline' => $now,
            ]);
        }
    }

    // ---------------------------------------------------------------------------------------------
    // 抢夺列表 / 日志
    // ---------------------------------------------------------------------------------------------

    /**
     * 玩家的抢夺对手列表（get_loot_list）；没有为空数组
     *
     * @return array<string, mixed>
     */
    public static function lootList(int $uid, int $bid, int $chipId): array
    {
        return DB::table('beauty_loot_list')->select('uid', 'beauty_id', 'chip_id', 'list', 'dateline')->where('uid', $uid)
            ->where('beauty_id', $bid)->where('chip_id', $chipId)->whereNull('deleted_at')->first() ?? [];
    }

    /**
     * 保存抢夺对手列表（add_loot_list）
     *
     * @param list<array<string, mixed>> $list
     */
    public static function addLootList(int $uid, int $bid, int $chipId, array $list, int $now): void
    {
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_beauty_loot_list` (`uid`, `beauty_id`, `chip_id`, `list`, `dateline`) VALUES (:uid, :bid, :chip_id, :list, :now)',
            ['uid' => $uid, 'bid' => $bid, 'chip_id' => $chipId, 'list' => json_encode($list, JSON_UNESCAPED_UNICODE), 'now' => $now]
        );
    }

    /**
     * 更新抢夺对手列表（update_loot_list；没有记录时不插入，同旧版）
     *
     * @param list<array<string, mixed>> $list
     */
    public static function updateLootList(int $uid, int $bid, int $chipId, array $list, int $now): int
    {
        return DB::table('beauty_loot_list')->where('uid', $uid)->where('beauty_id', $bid)->where('chip_id', $chipId)->whereNull('deleted_at')
            ->update(['list' => json_encode($list, JSON_UNESCAPED_UNICODE), 'dateline' => $now]);
    }

    /**
     * 抢夺日志（get_loot_log：抢到碎片且战斗非平局、自己是抢夺方或被抢方，按时间倒序 50 条）
     *
     * @return list<array<string, mixed>>
     */
    public static function lootLog(int $uid): array
    {
        $rows = DB::table('beauty_loot_log')->select(...self::LOOT_LOG_COLUMNS)->where('chip_status', 1)->where('battle_status', '!=', 0)
            ->where(static function ($q) use ($uid): void {
                $q->where('uid', $uid)->orWhere('to_uid', $uid);
            })
            ->whereNull('deleted_at')->orderBy('dateline', 'DESC')->orderBy('id', 'DESC')->limit(50)->get();
        return array_map(self::castLootLog(...), $rows);
    }

    /**
     * 碎片被抢信息（get_loot_chip_info：每个碎片取一条被抢走的日志）
     *
     * @param list<int> $chipIds
     * @return list<array<string, mixed>>
     */
    public static function lootChipInfo(int $uid, int $bid, array $chipIds): array
    {
        $return = [];
        foreach ($chipIds as $chipId) {
            $row = DB::table('beauty_loot_log')->select(...self::LOOT_LOG_COLUMNS)->where('to_uid', $uid)->where('beauty_id', $bid)
                ->where('chip_id', $chipId)->where('chip_status', 1)->whereNull('deleted_at')->orderBy('id')->first();
            if ($row !== null) {
                $return[] = self::castLootLog($row);
            }
        }
        return $return;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function addLootLog(array $data): void
    {
        DB::table('beauty_loot_log')->insert($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function addBuyRoseLog(array $data): void
    {
        DB::table('beauty_buyrose_log')->insert($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function addBuyLootLog(array $data): void
    {
        DB::table('beauty_buyloot_log')->insert($data);
    }

    // ---------------------------------------------------------------------------------------------
    // 互动 beauty_active_log
    // ---------------------------------------------------------------------------------------------

    /**
     * 互动次数（get_active_times：没有时插入；问候时间不是今天时三种次数重置）
     *
     * 每天次数 5，所在国家占领的都城效果为 6（铜雀台）时加上效果值（siege_model->get_city_effect，有副作用同旧版）。
     *
     * @param array<string, mixed> $member 当前玩家
     * @return array<string, int>
     */
    public static function activeTimes(array $member, int $now): array
    {
        $uid = (int)$member['uid'];
        $active = self::ACTIVE_TIMES;
        // 取都城占领加成
        $effect = SiegeHandler::getCityEffect($member, $now);
        if ($effect['status'] === 1 && ($effect['type'] ?? 0) === 6) {
            $active += (int)$effect['value'];
        }

        $info = DB::table('beauty_active_log')->select('uid', 'greeting', 'g_dateline', 'close', 'love')->where('uid', $uid)
            ->whereNull('deleted_at')->first();
        if ($info === null) {
            // 为空添加数据
            $data = ['uid' => $uid, 'greeting' => $active, 'g_dateline' => $now, 'close' => $active, 'love' => $active];
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_beauty_active_log` (`uid`, `greeting`, `g_dateline`, `close`, `love`) VALUES (:uid, :a1, :now, :a2, :a3)',
                ['uid' => $uid, 'a1' => $active, 'now' => $now, 'a2' => $active, 'a3' => $active]
            );
            return $data;
        }
        $info = array_map('intval', $info);
        if (date('ymd', $info['g_dateline'] - self::GREET_TIME) !== date('ymd', $now) && $info['g_dateline'] > 0) {
            $data = ['greeting' => $active, 'g_dateline' => $now, 'close' => $active, 'love' => $active];
            // 条件里带上读到的问候时间，多 Worker 同时首请求只重置一次
            $affected = DB::table('beauty_active_log')->where('uid', $uid)->where('g_dateline', $info['g_dateline'])->whereNull('deleted_at')
                ->update($data);
            return $affected > 0 ? $data : $info;
        }
        return $info;
    }

    /**
     * 互动次数减 1（update_active_log：1 问候（问候时间记为当前 + 冷却）/ 2 亲近 / 3 宠幸）；次数不够不扣
     */
    public static function useActive(int $uid, int $type, int $now): bool
    {
        $sql = match ($type) {
            1 => 'UPDATE `zc_sanguo_beauty_active_log` SET `greeting` = `greeting` - 1, `g_dateline` = :t WHERE `uid` = :uid AND `greeting` > 0 AND `deleted_at` IS NULL',
            2 => 'UPDATE `zc_sanguo_beauty_active_log` SET `close` = `close` - 1 WHERE `uid` = :uid AND `close` > 0 AND `deleted_at` IS NULL',
            3 => 'UPDATE `zc_sanguo_beauty_active_log` SET `love` = `love` - 1 WHERE `uid` = :uid AND `love` > 0 AND `deleted_at` IS NULL',
            default => null,
        };
        if ($sql === null) {
            return false;
        }
        $params = ['uid' => $uid];
        if ($type === 1) {
            $params['t'] = $now + self::GREET_TIME;
        }
        return DB::execute($sql, $params) > 0;
    }

    // ---------------------------------------------------------------------------------------------
    // 特殊技能领取日志
    // ---------------------------------------------------------------------------------------------

    /**
     * 本次激活最近一次领取（get_special_reward_log）；没有为空数组
     *
     * @return array<string, int>
     */
    public static function specialRewardLog(int $uid, int $bid, int $wakeupNum): array
    {
        $row = DB::table('beauty_speical_reward_log')->select('uid', 'beauty_id', 'type', 'num', 'wakeup_num', 'dateline')
            ->where('uid', $uid)->where('beauty_id', $bid)->where('wakeup_num', $wakeupNum)->whereNull('deleted_at')
            ->orderBy('dateline', 'DESC')->orderBy('id', 'DESC')->first();
        return $row === null ? [] : array_map('intval', $row);
    }

    /**
     * @param array<string, int> $data
     */
    public static function addSpecialRewardLog(array $data): void
    {
        DB::table('beauty_speical_reward_log')->insert($data);
    }

    // ---------------------------------------------------------------------------------------------
    // 战斗加成
    // ---------------------------------------------------------------------------------------------

    /**
     * 美人加成（get_beauty_addition）
     *
     * 激活中且在技能生效期内的美人，按好感度（每小时衰减 1）对应的技能效果百分比计入其已解锁技能的属性值，最后各项取整。
     * 技能属性：1 武力 2 智力 3 体力 4 命中 5 精准 6 闪避 7 识破 8 物攻 9 物防 10 策攻 11 策防 12 暴击伤害%（13 以上为产出，不计入）。
     * 旧版逐级累计的写法照搬（21 ~ 60 级时第二个技能取「等级 - 20」的值并且计了两次）。
     *
     * @return array<string, int>
     */
    public static function addition(int $uid, int $now): array
    {
        $return = [
            'wuli' => 0, 'zhili' => 0, 'tili' => 0, 'attack' => 0, 'defense' => 0, 'pattack' => 0, 'pdefense' => 0,
            'blood' => 0, 'dodge' => 0, 'harm' => 0, 'penetrate' => 0, 'power' => 0, 'mingzhong' => 0, 'jingzhun' => 0,
        ];
        $sum = $return;
        $info = [];
        // 技能生效的美人（激活状态中 + 时间段在技能生效期）
        foreach (self::ownAll($uid) as $val) {
            if ($val['open_status'] === 1 && $now >= $val['effect_time'] && $now <= $val['effect_end_time']) {
                $ratio = $val['ratio'] - (int)ceil(($now - $val['ratio_time']) / 3600) + 1;
                if ($ratio <= 0) {
                    $ratio = 0;
                }
                // 好感度超过 100（刚互动的同一秒）时旧版取不到技能效果，按 0
                $val['add'] = (self::happyRatio($ratio)['skill'] ?? 0) / 100;
                $info[] = $val;
            }
        }

        $field = [1 => 'wuli', 2 => 'zhili', 3 => 'tili', 4 => 'mingzhong', 5 => 'jingzhun', 6 => 'dodge', 7 => 'penetrate',
            8 => 'attack', 9 => 'defense', 10 => 'pattack', 11 => 'pdefense', 12 => 'harm'];
        foreach ($info as $val) {
            // 计算美人等级对应的技能等级
            $level = $val['beauty_level'];
            $levelArray = match (self::upStatus($level)['lock']) {
                1 => [$level],
                2 => [$level, $level - 20],
                3 => [$level, $level - 20, $level - 60],
                default => [],
            };
            foreach ($levelArray as $key => $value) {
                $result = self::skillLevel($val['beauty_id'], $value);
                // 开始处理是否解锁逻辑
                if ($level <= 20) {
                    // 剔除最后两个技能
                    unset($result[1], $result[2]);
                } elseif ($level <= 60) {
                    if (isset($result[1]) && $result[1]['skill_property'] <= 11) {
                        // 计算第二个技能效果
                        $result[1] = self::valueByType($level - 20, $result[1]['skill_property'], $val['beauty_id']);
                    }
                    // 剔除最后一个技能
                    unset($result[2]);
                }
                // 处理累计加成的逻辑
                if ($key === 1) {
                    unset($result[0]);
                } elseif ($key === 2) {
                    unset($result[0], $result[1]);
                }
                foreach ($result as $v) {
                    if ($v === []) {
                        continue;
                    }
                    $name = $field[$v['skill_property']] ?? null;
                    if ($name !== null) {
                        $sum[$name] += $v['skill_value'] * $val['add'];
                    }
                }
            }
        }
        // 对技能进行向下取整（blood / power 旧版没有加成，保持 0）
        foreach ($field as $name) {
            $return[$name] = (int)$sum[$name];
        }
        return $return;
    }

    // ---------------------------------------------------------------------------------------------
    // 锁
    // ---------------------------------------------------------------------------------------------

    /**
     * 接口防刷锁（get_lock + add_lock：{锁名}{uid}，30 秒；旧版先查再写，这里用 SET NX）；已被锁返回 false
     */
    public static function lock(string $key, int $uid): bool
    {
        $redis = Redis::instance();
        return $redis !== null && (bool)$redis->set($key . $uid, '1', ['nx', 'ex' => self::LOCK_TIME]);
    }

    public static function unlock(string $key, int $uid): void
    {
        Redis::del($key . $uid);
    }

    /**
     * 抢夺战斗锁（get_lott_lock + add_lott_lock：双方 _lott_key{uid} 500 秒）；任一方已在战斗中返回 false
     */
    public static function lottLock(int $uid, int $touid): bool
    {
        $redis = Redis::instance();
        if ($redis === null || !$redis->set(self::LOTT_KEY . $uid, '1', ['nx', 'ex' => self::LOTT_TIME])) {
            return false;
        }
        if ($touid !== $uid && !$redis->set(self::LOTT_KEY . $touid, '1', ['nx', 'ex' => self::LOTT_TIME])) {
            Redis::del(self::LOTT_KEY . $uid);
            return false;
        }
        return true;
    }

    /**
     * 解锁抢夺（un_lott_lock）
     */
    public static function unLottLock(int $uid, int $touid): void
    {
        Redis::del(self::LOTT_KEY . $touid);
        Redis::del(self::LOTT_KEY . $uid);
    }

    // ---------------------------------------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    private static function infoRow(int $uid): ?array
    {
        $row = DB::table('beauty_info')->select(...self::INFO_COLUMNS)->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function castOwn(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($key !== 'beauty_name') {
                $row[$key] = (int)$value;
            }
        }
        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function castChip(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($key !== 'chip_name') {
                $row[$key] = (int)$value;
            }
        }
        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function castLootLog(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($key !== 'nickname' && $key !== 'to_name') {
                $row[$key] = (int)$value;
            }
        }
        return $row;
    }
}
