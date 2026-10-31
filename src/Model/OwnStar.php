<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\StarGrid;
use Hanjiang\MasterData\StarRefine;
use OmniPHP\Database\DB;

/**
 * 主公星魄 / 命格（移植旧版 models/star_model.php）
 *
 * 存储同旧版：
 * - own_star 每个玩家一行：star1 / star2 / star3 为幽蓝 / 铭黄 / 紫阳星魄，lastsite 为最后选的炼化炉，lastlevel 为最后升级的命格；
 * - star_tree 每个玩家每个命格一行（uid + starid 唯一），level 为命格等级；
 * - star_log 星魄流水（type 1 获得 / 2 扣除，升级命格时记命格 id 与等级）；
 * - own_star_addition 每个玩家一行：命格累计的属性加成（战斗、将领界面使用）。
 * 配置：命格 StarGrid（按 id + level 一行），炼化炉 StarRefine。
 *
 * 并发：读改写由调用方在 Member::locked 玩家行锁内执行；加成累加是单条原子 UPDATE。
 */
final class OwnStar
{
    /** 旧版 own_star 的字段 */
    private const array STAR_FIELDS = ['uid', 'star1', 'star2', 'star3', 'dateline', 'lastsite', 'lastlevel'];

    /** 旧版 own_star_addition 的字段（get_star_addition 的输出顺序） */
    public const array ADDITION_FIELDS = [
        'uid', 'wuli', 'zhili', 'tili', 'attack', 'defense', 'pattack', 'pdefense', 'blood', 'dodge', 'crit', 'penetrate',
        'harm', 'power', 'mingzhong', 'jingzhun', 'kangyun', 'kcrit',
    ];

    /**
     * 新建玩家星魄（移植 init_star：三种星魄 0，lastsite 1，lastlevel 1）
     *
     * @return array<string, int> 成功返回写入的数据，失败空数组
     */
    public static function init(int $uid, int $now): array
    {
        $data = ['uid' => $uid, 'star1' => 0, 'star2' => 0, 'star3' => 0, 'dateline' => $now, 'lastsite' => 1, 'lastlevel' => 1];
        $affected = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_own_star` (`uid`, `star1`, `star2`, `star3`, `dateline`, `lastsite`, `lastlevel`)
             VALUES (:uid, 0, 0, 0, :dateline, 1, 1)',
            ['uid' => $uid, 'dateline' => $now]
        );
        return $affected > 0 ? $data : [];
    }

    /**
     * 玩家星魄（移植 get_own_star）；没有返回空数组
     *
     * @return array<string, int>
     */
    public static function get(int $uid): array
    {
        $row = DB::table('own_star')->select(...self::STAR_FIELDS)->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? [] : array_map('intval', $row);
    }

    /**
     * 更新玩家星魄（移植 update_own_star：star{类型} 写成给定余额；$lastsite / $starid 非 0 时同时记最后的炼化炉 / 命格）
     *
     * @param array<int|string, int> $startype 星魄类型（1 ~ 3）
     * @param array<int|string, int> $blance 与 $startype 同键的余额
     * @return int 1 有改动，0 没有（同旧版 affected_rows）
     */
    public static function update(int $uid, array $startype, array $blance, int $lastsite = 0, int $starid = 0): int
    {
        $data = [];
        foreach ($startype as $key => $val) {
            $data['star' . $val] = max(0, $blance[$key]);
        }
        if ($lastsite) {
            $data['lastsite'] = $lastsite;
        }
        if ($starid) {
            $data['lastlevel'] = $starid;
        }
        if ($data === []) {
            return 0;
        }
        $affected = DB::table('own_star')->where('uid', $uid)->whereNull('deleted_at')->update($data);
        return $affected > 0 ? 1 : 0;
    }

    /**
     * 星魄流水（移植 add_star_log；升级命格时 $grid 为命格行，记 treeid / level）
     *
     * @param array<string, mixed> $grid
     */
    public static function log(int $uid, int $startype, int $type, int $star, int $balance, string $class, string $method, int $now, array $grid = []): void
    {
        $data = [
            'uid' => $uid,
            'startype' => $startype,
            'type' => $type,
            'star' => $star,
            'balance' => max(0, $balance),
            'class' => Member::logText($class, 50),
            'method' => Member::logText($method, 50),
            'argument' => '',
            'dateline' => $now,
        ];
        if ($grid !== []) {
            $data['treeid'] = (int)$grid['id'];
            $data['level'] = (int)$grid['level'];
        }
        DB::table('star_log')->insert($data);
    }

    /**
     * 新建命格（移植 init_star_tree）
     *
     * @return array<int, array<string, int>> 成功返回 [1 => 写入的数据]（旧版同），失败空数组
     */
    public static function initTree(int $uid, int $starid, int $level): array
    {
        $affected = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_star_tree` (`uid`, `starid`, `level`) VALUES (:uid, :starid, :level)',
            ['uid' => $uid, 'starid' => $starid, 'level' => $level]
        );
        return $affected > 0 ? [1 => ['uid' => $uid, 'starid' => $starid, 'level' => $level]] : [];
    }

    /**
     * 命格升级（移植 update_star_tree：当前等级不超过 $maxLevel 时写成 $level）
     *
     * 多加了「当前等级为 $level - 1」的条件，同一命格并发升级只有一次生效。
     *
     * @return int 1 成功，0 失败
     */
    public static function updateTree(int $uid, int $starid, int $level, int $maxLevel): int
    {
        $affected = DB::table('star_tree')->where('uid', $uid)->where('starid', $starid)->where('level', '<=', $maxLevel)
            ->where('level', $level - 1)->whereNull('deleted_at')->update(['level' => $level]);
        return $affected > 0 ? 1 : 0;
    }

    /**
     * 玩家的命格（移植 get_star_tree）
     *
     * 不指定命格时返回 [starid => 行]；指定命格（或等级）时返回列表。两个条件分别是 IN，旧版前置命格判断即「任一前置命格处于任一要求等级」。
     *
     * @param list<int> $starid
     * @param list<int> $level
     * @return array<int, array<string, int>>
     */
    public static function tree(int $uid, array $starid = [], array $level = []): array
    {
        $query = DB::table('star_tree')->select('uid', 'starid', 'level')->where('uid', $uid)->whereNull('deleted_at');
        if ($starid !== []) {
            $query->whereIn('starid', $starid);
        }
        if ($level !== []) {
            $query->whereIn('level', $level);
        }
        $rows = array_map(static fn (array $row): array => array_map('intval', $row), $query->orderBy('id')->get());
        if ($starid !== []) {
            return $rows;
        }
        $return = [];
        foreach ($rows as $val) {
            $return[$val['starid']] = $val;
        }
        return $return;
    }

    /**
     * 命格配置（移植 get_star_grid：按页 / 命格 id / 等级过滤，按 id、level、line 升序，键从 0 开始）
     *
     * @param list<int> $id
     * @param list<int> $level
     * @return list<array<string, mixed>>
     */
    public static function grid(int $page = 0, array $id = [], array $level = []): array
    {
        $rows = [];
        foreach (StarGrid::ROWS as $row) {
            if ($page && (int)$row['page'] !== $page) {
                continue;
            }
            if ($id !== [] && !in_array((int)$row['id'], $id, true)) {
                continue;
            }
            if ($level !== [] && !in_array((int)$row['level'], $level, true)) {
                continue;
            }
            $rows[] = $row;
        }
        usort($rows, static fn (array $a, array $b): int =>
            [(int)$a['id'], (int)$a['level'], (int)$a['line']] <=> [(int)$b['id'], (int)$b['level'], (int)$b['line']]);
        return $rows;
    }

    /**
     * 升级某命格后开启的 1 级命格（移植 get_star_open_list：任一前置命格为 $prepos1 且要求等级为 $prepos11；旧版无排序，按主键 gid）
     *
     * @return list<array<string, int>>
     */
    public static function openList(int $prepos1, int $prepos11): array
    {
        $return = [];
        foreach (StarGrid::ROWS as $row) {
            if ((int)$row['level'] !== 1) {
                continue;
            }
            if (((int)$row['prepos1'] === $prepos1 && (int)$row['prepos11'] === $prepos11)
                || ((int)$row['prepos2'] === $prepos1 && (int)$row['prepos22'] === $prepos11)
                || ((int)$row['prepos3'] === $prepos1 && (int)$row['prepos33'] === $prepos11)) {
                $item = [];
                foreach (['id', 'line', 'prepos1', 'prepos11', 'prepos2', 'prepos22', 'prepos3', 'prepos33'] as $field) {
                    $item[$field] = (int)$row[$field];
                }
                $return[] = $item;
            }
        }
        return $return;
    }

    /**
     * 炼化炉配置（移植 get_star_refine：按炉 id / 提炼类型过滤，按 id、refine_type 升序）
     *
     * @return list<array<string, mixed>>
     */
    public static function refine(int $id = 0, int $refineType = 0): array
    {
        $rows = [];
        foreach (StarRefine::ROWS as $row) {
            if ($id && (int)$row['id'] !== $id) {
                continue;
            }
            if ($refineType && (int)$row['refine_type'] !== $refineType) {
                continue;
            }
            $rows[] = $row;
        }
        usort($rows, static fn (array $a, array $b): int =>
            [(int)$a['id'], (int)$a['refine_type']] <=> [(int)$b['id'], (int)$b['refine_type']]);
        return $rows;
    }

    /**
     * 命格加成（移植 get_star_addition：没有记录时插入全 0 的一行并返回）
     *
     * @return array<string, int>
     */
    public static function addition(int $uid): array
    {
        $row = DB::table('own_star_addition')->select(...self::ADDITION_FIELDS)->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row !== null) {
            return array_map('intval', $row);
        }
        $return = array_fill_keys(self::ADDITION_FIELDS, 0);
        $return['uid'] = $uid;
        DB::execute('INSERT IGNORE INTO `zc_sanguo_own_star_addition` (`uid`) VALUES (:uid)', ['uid' => $uid]);
        return $return;
    }

    /**
     * 命格加成累加（star 控制器 add_addition_info + update_star_addition）
     *
     * 旧版先读出整行、加上命格的 addvalue 再整行写回；这里写成单条原子累加，结果相同。
     * addtype：1 武力、2 智力、3 体力、4 血量、5 物攻、6 物防、7 策攻、8 策防、9 暴击率、10 暴击伤害、11 精准、12 命中、
     * 13 闪避、14 识破、15 士气、16 抗晕、17 抗暴击率、18 武力 / 智力 / 体力。其他类型不改动。
     */
    public static function addAddition(int $uid, int $addtype, int $addvalue): void
    {
        $columns = match ($addtype) {
            1 => ['wuli'],
            2 => ['zhili'],
            3 => ['tili'],
            4 => ['blood'],
            5 => ['attack'],
            6 => ['defense'],
            7 => ['pattack'],
            8 => ['pdefense'],
            9 => ['crit'],
            10 => ['harm'],
            11 => ['jingzhun'],
            12 => ['mingzhong'],
            13 => ['dodge'],
            14 => ['penetrate'],
            15 => ['power'],
            16 => ['kangyun'],
            17 => ['kcrit'],
            18 => ['wuli', 'zhili', 'tili'],
            default => [],
        };
        if ($columns === []) {
            return;
        }
        // 原生预处理不允许重复的命名占位符，每列各用一个
        $sets = [];
        $bindings = ['uid' => $uid];
        foreach ($columns as $i => $column) {
            $sets[] = "`{$column}` = `{$column}` + :v{$i}";
            $bindings['v' . $i] = $addvalue;
        }
        DB::execute(
            'UPDATE `zc_sanguo_own_star_addition` SET ' . implode(', ', $sets) . ' WHERE `uid` = :uid AND `deleted_at` IS NULL',
            $bindings
        );
    }
}
