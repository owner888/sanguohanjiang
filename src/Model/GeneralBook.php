<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\Book;
use Hanjiang\MasterData\BookAchievement;
use Hanjiang\MasterData\BookAddition;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 名将谱 / 名将成就（移植旧版 models/general_model.php 的图鉴、成就与加成部分）
 *
 * 存储同旧版：
 * - general.addtype / addvalue：获得图鉴里的将领时从 Book 复制的单将加成（General::createFromTemplate）；
 * - own_achievement：拥有的成就（uid + bid 唯一），quality 1 ~ 6；
 * - own_addition：每个玩家一行，单将加成与成就加成的合计（战斗 _addition_arr、将领界面 _get_achievement_info 读取，主公 81 级起）；
 * - own_achievement_log：成就升级记录；
 * - Redis general_book_flag_{uid}（86400 秒）：当天已在打开名将谱时重算过加成。
 * 配置：图鉴 Book，成就 BookAchievement（gid1 ~ gid5 为所需将领模板），成就属性 BookAddition（按 id + quality）。
 *
 * 不移植：图鉴总数缓存 general_book_count（直接数 Book）、clear_book_flag（无调用方）。
 */
final class GeneralBook
{
    /** 加成类型 → own_addition 字段（旧版 $_type） */
    public const array TYPE = [
        1 => 'wuli',        // 武力
        2 => 'zhili',       // 智力
        3 => 'tili',        // 体力
        4 => 'attack',      // 物攻
        5 => 'defense',     // 物防
        6 => 'pattack',     // 策攻
        7 => 'pdefense',    // 策防
        8 => 'blood',       // 生命
        9 => 'dodge',       // 闪避率
        10 => 'crit',       // 暴击率
        11 => 'penetrate',  // 识破率
        12 => 'power',      // 初始士气
        13 => 'mingzhong',  // 命中
        14 => 'jingzhun',   // 精准
        15 => 'kcrit',      // 抗暴击率
    ];

    /** own_addition 的旧版字段（get_own_addition 的输出，去掉无用的 asdasda） */
    public const array ADDITION_FIELDS = [
        'uid', 'wuli', 'zhili', 'tili', 'attack', 'defense', 'pattack', 'pdefense', 'blood', 'dodge', 'crit', 'penetrate',
        'power', 'mingzhong', 'jingzhun', 'kcrit',
    ];

    /** 当天已重算标记（旧版 _book_flag） */
    private const string BOOK_FLAG = 'general_book_flag_';

    /**
     * 拥有的星级 >= $star 的将领（移植 get_own_by_star：[模板 id => 将领行]）
     *
     * @return array<int, array<string, mixed>>
     */
    public static function ownByStar(int $uid, int $star = 1): array
    {
        $rows = DB::table('general')->where('uid', $uid)->where('star', '>=', $star)->whereNull('deleted_at')->orderBy('id')->get();
        $return = [];
        foreach ($rows as $row) {
            $return[(int)$row['generalid']] = [
                'id' => (int)$row['id'],
                'generalid' => (int)$row['generalid'],
                'addtype' => (int)$row['addtype'],
                'addvalue' => (int)$row['addvalue'],
            ];
        }
        return $return;
    }

    /**
     * 拥有的成就（移植 get_own_achievement_list：[bid => 行]）
     *
     * @return array<int, array{uid: int, bid: int, quality: int}>
     */
    public static function ownAchievementList(int $uid): array
    {
        $rows = DB::table('own_achievement')->select('uid', 'bid', 'quality')->where('uid', $uid)->whereNull('deleted_at')->orderBy('id')->get();
        $return = [];
        foreach ($rows as $row) {
            $return[(int)$row['bid']] = array_map('intval', $row);
        }
        return $return;
    }

    /**
     * 拥有的某个成就（移植 get_own_achievement_info(单个)）；没有返回 null
     *
     * @return array{uid: int, bid: int, quality: int}|null
     */
    public static function ownAchievement(int $uid, int $bid): ?array
    {
        $row = DB::table('own_achievement')->select('uid', 'bid', 'quality')->where('uid', $uid)->where('bid', $bid)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * 成就升一级（移植 update_own_achievement；加上「当前品级 = 新品级 - 1」条件，并发重复升级只写一次）
     *
     * @return int 1 成功，0 失败
     */
    public static function updateOwnAchievement(int $uid, int $bid, int $quality): int
    {
        $affected = DB::table('own_achievement')->where('uid', $uid)->where('bid', $bid)->where('quality', $quality - 1)->whereNull('deleted_at')
            ->update(['quality' => $quality]);
        return $affected > 0 ? 1 : 0;
    }

    /**
     * 成就模板列表（移植 get_achievement_list：$sort = 1 时 ORDER BY id, sort；id 为主键，两种顺序相同）
     *
     * @return list<array<string, mixed>>
     */
    public static function achievementList(): array
    {
        $rows = BookAchievement::ROWS;
        ksort($rows);
        return array_values($rows);
    }

    /**
     * 成就属性（移植 get_achievement_addition_list：ORDER BY id, quality）
     *
     * @return list<array<string, mixed>>
     */
    public static function achievementAdditionList(): array
    {
        $rows = BookAddition::ROWS;
        usort($rows, static fn (array $a, array $b): int => [$a['id'], $a['quality']] <=> [$b['id'], $b['quality']]);
        return $rows;
    }

    /**
     * 某些成就的属性（移植 get_achievement_addition_info：WHERE id IN (...)，表顺序即 id、quality 顺序）
     *
     * @param int|list<int> $id
     * @return list<array<string, mixed>>
     */
    public static function achievementAdditionInfo(int|array $id): array
    {
        $ids = is_array($id) ? $id : [$id];
        $return = [];
        foreach (self::achievementAdditionList() as $row) {
            if (in_array($row['id'], $ids, true)) {
                $return[] = $row;
            }
        }
        return $return;
    }

    /**
     * 检查达成的成就（移植 check_achievement：成就所需将领模板都拥有即达成；[成就 id => 模板行]）
     *
     * @param list<int> $gidArr 拥有的将领模板 id
     * @return array{status: int, complete: array<int, array<string, mixed>>}
     */
    public static function checkAchievement(array $gidArr): array
    {
        $return = ['status' => 0, 'complete' => []];
        foreach (BookAchievement::ROWS as $value) {
            $group = [];
            for ($i = 1; $i < 6; $i++) {
                if ($value['gid' . $i] > 0) {
                    $group[] = $value['gid' . $i];
                }
            }
            if (array_diff($group, $gidArr) === []) {
                // 达成成就
                $return['complete'][$value['id']] = $value;
                $return['status'] = 1;
            }
        }
        return $return;
    }

    /**
     * 当前将领与成就的加成来源（移植 get_addition）
     *
     * 拥有的星级 >= 1 的将领为 general；达成而尚未拥有的成就按品级 1 建档；
     * achieve 为拥有的成就各自当前品级的属性（只取本次达成的成就的属性，拥有但已不达成的成就属性为空，不计加成）。
     *
     * @param array<int, array<string, int>> $achievement 拥有的成就（ownAchievementList）
     * @return array{general: array<int, array<string, mixed>>, achieve: list<array<string, mixed>>}
     */
    public static function getAddition(int $uid, array $achievement): array
    {
        $return = ['general' => [], 'achieve' => []];
        $list = self::ownByStar($uid, 1);
        $return['general'] = $list;

        $gidArr = array_keys($list);
        // 检查是否达成成就组合
        $result = self::checkAchievement($gidArr);
        $achieve = [];
        if ($result['status'] === 1) {
            // 达成的所有成就
            $complete = array_keys($result['complete']);
            // 本次达成的成就
            $idArr = array_diff($complete, array_keys($achievement));
            if ($idArr !== []) {
                $achievement += self::addAchievementInfo($uid, array_values($idArr));
            }

            $addition = self::achievementAdditionInfo($complete);
            foreach ($achievement as $value) {
                foreach ($addition as $val) {
                    if ($val['id'] === $value['bid'] && $val['quality'] === $value['quality']) {
                        $value += $val;
                    }
                }
                $achieve[] = self::achieveFields($value);
            }
        }
        $return['achieve'] = $achieve;
        return $return;
    }

    /**
     * 名将属性加成合计（移植 achievement_addition：单将 addtype / addvalue + 成就 addtype1 ~ 3 / addvalue1 ~ 3）
     *
     * @param array<int, array<string, mixed>> $general
     * @param list<array<string, mixed>> $achievement
     * @return array<string, int>
     */
    public static function achievementAddition(array $general, array $achievement = []): array
    {
        $addition = array_fill_keys(array_values(self::TYPE), 0);
        foreach ($general as $value) {
            if (isset(self::TYPE[$value['addtype']])) {
                $addition[self::TYPE[$value['addtype']]] += (int)$value['addvalue'];
            }
        }
        foreach ($achievement as $value) {
            for ($i = 1; $i < 4; $i++) {
                if (($value['addvalue' . $i] ?? 0) > 0 && isset(self::TYPE[$value['addtype' . $i]])) {
                    $addition[self::TYPE[$value['addtype' . $i]]] += (int)$value['addvalue' . $i];
                }
            }
        }
        return $addition;
    }

    /**
     * 重算并保存名将谱加成（get_own_achievement_list → get_addition → achievement_addition → add / update_own_addition；
     * general_book index、add_general 主公 80 级以上时同样的步骤）
     *
     * @return array<string, int>
     */
    public static function refresh(int $uid): array
    {
        $achievement = self::ownAchievementList($uid);
        $addition = self::getAddition($uid, $achievement);
        $add = self::achievementAddition($addition['general'], $addition['achieve']);
        self::saveAddition($uid, $add);
        return $add;
    }

    /**
     * 保存名将谱加成（add_own_addition / update_own_addition：每人一行，按 uid 唯一键写入）
     *
     * @param array<string, int> $add
     */
    public static function saveAddition(int $uid, array $add): void
    {
        $columns = array_values(self::TYPE);
        $params = ['uid' => $uid];
        $sets = [];
        foreach ($columns as $column) {
            $params[$column] = max(0, (int)$add[$column]);
            $sets[] = "`{$column}` = VALUES(`{$column}`)";
        }
        DB::execute(
            'INSERT INTO `zc_sanguo_own_addition` (`uid`, `' . implode('`, `', $columns) . '`) VALUES (:uid, :' . implode(', :', $columns) . ')
             ON DUPLICATE KEY UPDATE ' . implode(', ', $sets),
            $params
        );
    }

    /**
     * 玩家的名将谱加成（移植 get_own_addition）；没有记录返回空数组
     *
     * @return array<string, int>
     */
    public static function ownAddition(int $uid): array
    {
        $row = DB::table('own_addition')->select(...self::ADDITION_FIELDS)->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? [] : array_map('intval', $row);
    }

    /**
     * 成就升级记录（移植 add_achievement_log）
     */
    public static function addAchievementLog(int $uid, int $bid, int $quality1, int $quality2, int $type, int $jade, int $now): void
    {
        DB::table('own_achievement_log')->insert([
            'uid' => $uid,
            'bid' => $bid,
            'quality1' => $quality1,
            'quality2' => $quality2,
            'type' => $type,
            'jade' => $jade,
            'dateline' => $now,
        ]);
    }

    /**
     * 名将图鉴分页（移植 get_book_list：ORDER BY sort ASC；页码超出时取最后一页，每页默认 9 个）
     *
     * @return array<string, mixed> status 1 成功，-1 没有图鉴
     */
    public static function bookList(int $page, int $perpage): array
    {
        $return = ['status' => 0];
        $page = $page > 0 ? $page : 1;
        $perpage = $perpage > 0 ? $perpage : 9;
        $totals = count(Book::ROWS);
        if ($totals === 0) {
            $return['status'] = -1;
            return $return;
        }
        $pages = (int)ceil($totals / $perpage);
        $page = min($page, $pages);
        $rows = array_values(Book::ROWS);
        usort($rows, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);
        $return['list'] = array_slice($rows, ($page - 1) * $perpage, $perpage);
        $return['pages'] = $pages;
        $return['page'] = $page;
        $return['perpage'] = $perpage;
        $return['count'] = $totals;
        $return['status'] = 1;
        return $return;
    }

    /**
     * 当天是否已重算过加成（移植 get_book_flag）
     */
    public static function bookFlag(int $uid): bool
    {
        return Redis::exists(self::BOOK_FLAG . $uid);
    }

    /**
     * 标记当天已重算（移植 set_book_flag：86400 秒）
     */
    public static function setBookFlag(int $uid): void
    {
        Redis::set(self::BOOK_FLAG . $uid, 1, 86400);
    }

    /**
     * 新达成的成就按品级 1 建档（移植 add_achievement_info：[bid => 行]；并发重复建档时已有的行不变）
     *
     * @param list<int> $idArr
     * @return array<int, array{uid: int, bid: int, quality: int}>
     */
    private static function addAchievementInfo(int $uid, array $idArr): array
    {
        $data = [];
        foreach ($idArr as $value) {
            $data[$value] = ['uid' => $uid, 'bid' => $value, 'quality' => 1];
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_own_achievement` (`uid`, `bid`, `quality`) VALUES (:uid, :bid, 1)',
                ['uid' => $uid, 'bid' => $value]
            );
        }
        return $data;
    }

    /**
     * 成就加成计算用的字段（get_addition / levelup 的 $field；拥有但没合并到属性的成就，这些字段为 null）
     *
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    public static function achieveFields(array $value): array
    {
        $result = [];
        foreach (['id', 'name', 'addtype1', 'addvalue1', 'addtype2', 'addvalue2', 'addtype3', 'addvalue3'] as $v) {
            $result[$v] = $value[$v] ?? null;
        }
        return $result;
    }
}
