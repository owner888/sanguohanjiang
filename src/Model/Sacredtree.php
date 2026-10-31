<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\ActivityTime;
use Hanjiang\MasterData\NewSacredtree;
use Hanjiang\MasterData\Sacredtree as SacredtreeTemp;
use Hanjiang\MasterData\SacredtreeRate;
use OmniPHP\Database\DB;

/**
 * 运营活动：神树（移植旧版 models/sacredtree_model.php）
 *
 * 活动时间与版本取 ActivityTime 的 sacredtree（旧版数据 2016-12-31 已结束）；活动类型 type 1（春节）用 MasterData NewSacredtree，
 * 否则 Sacredtree（7 级）。玩家神树按活动版本一行（sacredtree_log），每天第一次查询时免费浇水次数重置为 1。
 * 活动期间神树的血量 buff 计入战斗（TeamBuilder 记在单位的 tree 上，BattleEngine 加到 ptblood，左右 buff 的 tree 为神树等级），
 * 好友 / 演武榜 / 群雄逐鹿 / 聊天显示神树等级（treeByUid）。
 * 当前时间取 BossClock（生产恒为 time()）。
 */
final class Sacredtree
{
    public const string ACTIVITY = 'sacredtree';

    /** 神树最高等级 */
    public const int MAX_LEVEL = 7;

    /**
     * @return array{id: int, name: string, starttime: int, endtime: int, version: int, type: int}|null
     */
    public static function activity(): ?array
    {
        foreach (ActivityTime::ROWS as $row) {
            if ($row['name'] === self::ACTIVITY) {
                return $row;
            }
        }
        return null;
    }

    public static function isOpen(int $now): bool
    {
        $info = self::activity();
        return $info !== null && $info['starttime'] <= $now && $info['endtime'] >= $now;
    }

    /**
     * 活动开启时的版本号；未开启为 null（旧版各控制器 _activity / _version）
     */
    public static function openVersion(int $now): ?int
    {
        return self::isOpen($now) ? (int)self::activity()['version'] : null;
    }

    /**
     * 神树列表（get_tree_list）
     *
     * @return list<array<string, mixed>>
     */
    public static function treeList(int $type = 0): array
    {
        return array_values($type === 1 ? NewSacredtree::ROWS : SacredtreeTemp::ROWS);
    }

    /**
     * 一棵树（get_tree_by_id）；不存在为空数组
     *
     * @return array<string, mixed>
     */
    public static function tree(int $id, int $type = 0): array
    {
        return ($type === 1 ? NewSacredtree::ROWS : SacredtreeTemp::ROWS)[$id] ?? [];
    }

    /**
     * 玩家神树（get_member_info：没有记录时建档 1 级、升级经验取第 2 棵树；不是今天更新的免费次数重置为 1）
     *
     * @return array{uid: int, version: int, treeid: int, buff: int, exp: int, levelup_exp: int, time: int, dateline: int}
     */
    public static function memberInfo(int $uid, int $version, int $type, int $now): array
    {
        $row = self::row($uid, $version);
        if ($row !== null) {
            if (date('ymd', $row['dateline']) !== date('ymd', $now)) {
                $data = ['time' => 1, 'dateline' => $now];
                DB::table('sacredtree_log')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->update($data);
                $row = array_merge($row, $data);
            }
            return $row;
        }
        // 查询第二棵树的信息
        $tree = self::tree(2, $type);
        $data = [
            'uid' => $uid,
            'version' => $version,
            'treeid' => 1,
            'buff' => 0,
            'exp' => 0,
            'levelup_exp' => (int)($tree['exp'] ?? 0),
            'time' => 1,
            'dateline' => $now,
        ];
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_sacredtree_log` (`uid`, `version`, `treeid`, `buff`, `exp`, `levelup_exp`, `time`, `dateline`)
             VALUES (:uid, :version, :treeid, :buff, :exp, :levelup_exp, :time, :dateline)',
            $data
        );
        return self::row($uid, $version) ?? $data;
    }

    /**
     * 添加经验（add_exp，照旧版递归：每升一级递归一次，每一层都写一次库；只有递归层（$level > 0）写 buff，
     * 最外层最后写入等级 / 经验，所以库里的 buff 是本次升的第一级的 buff）
     *
     * @return array{level: int, exp: int, levelup_exp: int}
     */
    public static function addExp(int $uid, int $version, int $addExp, int $level, int $myExp, int $activityType, int $now): array
    {
        $return = ['level' => 1, 'exp' => 0, 'levelup_exp' => 0];
        $buff = 0;
        if ($level > 0) {
            $nextLevel = $level + 1;
            // 升级后的buff
            $buff = (int)(self::tree($level, $activityType)['buff'] ?? 0);
        } else {
            // 查询当前用户所属等级
            $member = self::memberInfo($uid, $version, $activityType, $now);
            $level = $member['treeid'];
            $nextLevel = $member['treeid'] + 1;
            $myExp = $member['exp'];
        }
        $nextLevel = $nextLevel >= self::MAX_LEVEL ? self::MAX_LEVEL : $nextLevel;
        // 获取当前升级所需经验
        $levelupExp = (int)(self::tree($nextLevel, $activityType)['exp'] ?? 0);
        // 当前经验
        $nowExp = $myExp + $addExp;
        if ($nowExp < $levelupExp) {
            // 未升级
            $return['exp'] = $level >= self::MAX_LEVEL ? $levelupExp : $nowExp;
            $return['levelup_exp'] = $levelupExp;
            $return['level'] = $level;
        } elseif ($level >= self::MAX_LEVEL) {
            $return['exp'] = $nowExp;
            $return['levelup_exp'] = $levelupExp;
            $return['level'] = $level;
        } else {
            // 升级
            $result = self::addExp($uid, $version, $nowExp - $levelupExp, $nextLevel, 0, $activityType, $now);
            $return['exp'] = $result['exp'];
            $return['levelup_exp'] = $result['levelup_exp'];
            $return['level'] = $result['level'];
        }
        $data = ['treeid' => $return['level'], 'exp' => $return['exp'], 'levelup_exp' => $return['levelup_exp']];
        if ($buff > 0) {
            $data['buff'] = $buff;
        }
        DB::table('sacredtree_log')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->update($data);
        return $return;
    }

    /**
     * 本次浇水的暴击倍数（get_rate：按 SacredtreeRate 第一行的 one / two / four / eight 权重）
     */
    public static function rate(): int
    {
        $result = array_values(SacredtreeRate::ROWS)[0] ?? null;
        if ($result === null) {
            return 1;
        }
        $weights = ['one' => 1, 'two' => 2, 'four' => 4, 'eight' => 8];
        $total = 0;
        foreach ($weights as $key => $multiple) {
            $total += (int)$result[$key];
        }
        $rands = mt_rand(1, $total);
        $nowRate = 0;
        foreach ($weights as $key => $multiple) {
            $nowRate += (int)$result[$key];
            if ($rands <= $nowRate) {
                return $multiple;
            }
        }
        return 1;
    }

    /**
     * 多个玩家的神树（get_tree_by_uid 传数组）：uid => 行
     *
     * @param list<int> $uids
     * @return array<int, array<string, int>>
     */
    public static function treeByUid(int $version, array $uids): array
    {
        if ($uids === []) {
            return [];
        }
        $return = [];
        $rows = DB::table('sacredtree_log')->select(...self::COLUMNS)->whereIn('uid', $uids)->where('version', $version)->whereNull('deleted_at')->get();
        foreach ($rows as $row) {
            $return[(int)$row['uid']] = array_map('intval', $row);
        }
        return $return;
    }

    /**
     * 活动开启时玩家的神树（战斗 / 聊天用；活动未开启或没有记录为 null）
     *
     * @return array<string, int>|null
     */
    public static function openTree(int $uid, int $now): ?array
    {
        $version = self::openVersion($now);
        return $version === null ? null : (self::treeByUid($version, [$uid])[$uid] ?? null);
    }

    private const array COLUMNS = ['uid', 'version', 'treeid', 'buff', 'exp', 'levelup_exp', 'time', 'dateline'];

    /**
     * @return array{uid: int, version: int, treeid: int, buff: int, exp: int, levelup_exp: int, time: int, dateline: int}|null
     */
    private static function row(int $uid, int $version): ?array
    {
        $row = DB::table('sacredtree_log')->select(...self::COLUMNS)->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }
}
