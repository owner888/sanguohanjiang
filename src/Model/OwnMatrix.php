<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\Matrix;
use Hanjiang\MasterData\MatrixLevel;
use OmniPHP\Database\DB;

/**
 * 拥有阵法（移植旧版 models/matrix_model.php 的 own_matrix 读写部分）
 *
 * 存储同旧版：matrix 表每个阵法一行（主键 uid + mid，position1~9 为 general.id，level 为该阵法等级），
 * 启用的阵法记在 member_field.matrixid。阵法模板取 MasterData Matrix，升级价格取 MasterData MatrixLevel。
 * 这里是阵法站位的唯一真相源：出征名单是 general.goout，战斗读启用的阵法（Battle\TeamBuilder）。
 */
final class OwnMatrix
{
    /**
     * 旧版 update_matrix / add_matrix 允许的阵法 id：range(1, 11)
     */
    public const int MAX_MID = 11;

    /**
     * 新账号初始阵法（旧版 controllers/login.php：init_matrix(1, uid, 2, gid)；member_model 注册时 matrixid = 1）
     */
    public const int INITIAL_MID = 1;
    public const int INITIAL_POSITION = 2;

    /**
     * 旧版 own_matrix 的列（输出时不带三个时间列）
     */
    private const array COLUMNS = [
        'uid', 'mid', 'position1', 'position2', 'position3', 'position4', 'position5',
        'position6', 'position7', 'position8', 'position9', 'level',
    ];

    /**
     * 启用的阵法 id（旧版 member_field.matrixid；没有扩展行时按 0）
     */
    public static function activeId(int $uid): int
    {
        return (int)(DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->value('matrixid') ?? 0);
    }

    /**
     * 拥有的阵法（get_own_by_id / get_own_info 单个 id），查不到返回 null
     *
     * @return array<string, int>|null
     */
    public static function own(int $uid, int $mid): ?array
    {
        $row = DB::table('matrix')->where('uid', $uid)->where('mid', $mid)->whereNull('deleted_at')->first();
        return $row === null ? null : self::legacyRow($row);
    }

    /**
     * 拥有的阵法列表（get_own_list），按 mid 升序（旧版 SQL 无 ORDER BY，即主键 uid + mid 顺序）
     *
     * @return list<array<string, int>>
     */
    public static function ownList(int $uid): array
    {
        $rows = DB::table('matrix')->where('uid', $uid)->whereNull('deleted_at')->orderBy('mid')->get();
        return array_map(self::legacyRow(...), $rows);
    }

    /**
     * 初始化阵法（init_matrix）：阵法等级 1，$position 号位放 $gid
     */
    public static function init(int $uid, int $mid, int $position, int $gid): void
    {
        DB::table('matrix')->insert([
            'uid' => $uid,
            'mid' => $mid,
            'position' . $position => $gid,
            'level' => 1,
        ]);
    }

    /**
     * 添加阵法（add_matrix）：只写 uid / mid，等级为列默认值 0（旧版同）
     *
     * @return int 1 成功；-1 阵法不存在；-2 阵法已存在
     */
    public static function add(int $uid, int $mid): int
    {
        if ($mid < 1 || $mid > self::MAX_MID) {
            return -1;
        }
        if (DB::table('matrix')->where('uid', $uid)->where('mid', $mid)->exists()) {
            return -2;
        }
        DB::table('matrix')->insert(['uid' => $uid, 'mid' => $mid]);
        return 1;
    }

    /**
     * 阵法规则（get_rule_by_id 的单个 id 分支）：开放位置与加成
     *
     * info.position1~9：0 开放、-1 关闭；addition：type = 模板 addtype，percent = 阵法等级 × intval(模板 addition)。
     * 模板不存在返回 null（旧版 -1）。
     *
     * @return array{info: array<string, int>, addition: array{type: int, percent: int}}|null
     */
    public static function rule(int $mid, int $level): ?array
    {
        $tpl = Matrix::ROWS[$mid] ?? null;
        if ($tpl === null) {
            return null;
        }

        $field = [];
        if ((int)$tpl['opentype'] === 1) {
            $field = [$tpl['position1'], $tpl['position2']];
            if ($level < $tpl['openlevel3']) {
                // 只开放前两个位置
            } elseif ($level < $tpl['openlevel4']) {
                $field[] = $tpl['position3'];
            } elseif ($level < $tpl['openlevel5']) {
                $field[] = $tpl['position3'];
                $field[] = $tpl['position4'];
            } else {
                $field[] = $tpl['position3'];
                $field[] = $tpl['position4'];
                $field[] = $tpl['position5'];
            }
        } elseif ((int)$tpl['opentype'] === 2) {
            for ($i = 1; $i < 10; $i++) {
                if ($tpl['position' . $i] > 0) {
                    $field[] = $tpl['position' . $i];
                }
            }
        }

        $info = [
            'mid' => (int)$tpl['id'],
            'ratio' => (int)$tpl['ratio'],
            'opentype' => (int)$tpl['opentype'],
            'openlevel' => (int)$tpl['openlevel'],
        ];
        for ($i = 1; $i < 10; $i++) {
            $info['position' . $i] = in_array($i, $field, true) ? 0 : -1;
        }

        return [
            'info' => $info,
            'addition' => ['type' => (int)$tpl['addtype'], 'percent' => self::addition($level, (int)$tpl['addition'])],
        ];
    }

    /**
     * 阵法等级加成（get_addition）
     */
    public static function addition(int $level, int $addition): int
    {
        return $level * $addition;
    }

    /**
     * 阵法升级价格（get_levelup_price）：MatrixLevel 没有该等级时为 0
     */
    public static function levelupPrice(int $level): int
    {
        return (int)(MatrixLevel::ROWS[$level]['price'] ?? 0);
    }

    /**
     * 阵法最高等级（get_max_level）：主公 5 级起每 5 级 +1，最高 100
     */
    public static function maxLevel(int $memberLevel): int
    {
        $max = 1;
        if ($memberLevel >= 5) {
            $max = intdiv($memberLevel, 5);
        }
        return $max > 100 ? 100 : $max;
    }

    /**
     * 升级到某阵法等级所需的主公等级（get_levelup_rule）
     */
    public static function levelupRule(int $level): int
    {
        return $level * 5;
    }

    /**
     * 已达开启条件的阵法模板（get_open_level）：opentype 1 按主公等级、2 按 VIP 等级；按模板 id 索引
     *
     * @return array<int, array<string, mixed>>
     */
    public static function openTemplates(int $memberLevel, int $vip): array
    {
        $return = [];
        foreach (Matrix::ROWS as $id => $tpl) {
            if (((int)$tpl['opentype'] === 1 && (int)$tpl['openlevel'] <= $memberLevel)
                || ((int)$tpl['opentype'] === 2 && (int)$tpl['openlevel'] <= $vip)) {
                $return[$id] = $tpl;
            }
        }
        return $return;
    }

    /**
     * 模板阵法列表（get_matrix_list）：ORDER BY opentype DESC, openlevel DESC（同序按 id）
     *
     * @return list<array<string, mixed>>
     */
    public static function templateList(): array
    {
        $rows = array_values(Matrix::ROWS);
        usort($rows, static fn (array $a, array $b): int =>
            [(int)$b['opentype'], (int)$b['openlevel'], (int)$a['id']] <=> [(int)$a['opentype'], (int)$a['openlevel'], (int)$b['id']]);
        return $rows;
    }

    /**
     * 将领上阵信息（get_matrix_by_gid）：每个含该将领的阵法一项 [uid, mid, positionN => gid]，空数组则无需下阵
     *
     * @return array<int, array<string, int>>
     */
    public static function byGid(int $uid, int $gid): array
    {
        $list = [];
        foreach (self::ownList($uid) as $key => $value) {
            for ($i = 1; $i < 10; $i++) {
                if ($value['position' . $i] === $gid) {
                    $list[$key]['uid'] = $value['uid'];
                    $list[$key]['mid'] = $value['mid'];
                    $list[$key]['position' . $i] = $value['position' . $i];
                }
            }
        }
        return $list;
    }

    /**
     * 下阵（matrix_model::go_rest）：按 mid 批量更新站位
     *
     * @param array<int, array<string, int>> $position 每项含 mid 与要写入的 positionN
     * @return int 1 成功；-1 下阵失败（没有行被更新）
     */
    public static function goRest(int $uid, array $position): int
    {
        $affected = 0;
        foreach ($position as $row) {
            $mid = (int)$row['mid'];
            unset($row['mid']);
            if ($row === []) {
                continue;
            }
            $affected += DB::table('matrix')->where('uid', $uid)->where('mid', $mid)->whereNull('deleted_at')->update($row);
        }
        return $affected > 0 ? 1 : -1;
    }

    /**
     * @param array<string, mixed> $row matrix 表行
     * @return array<string, int>
     */
    private static function legacyRow(array $row): array
    {
        $return = [];
        foreach (self::COLUMNS as $column) {
            $return[$column] = (int)$row[$column];
        }
        return $return;
    }
}
