<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\MountLevel;
use Hanjiang\MasterData\MountTemp;
use OmniPHP\Database\DB;

/**
 * 坐骑与坐骑碎片
 *
 * 移植旧版 models/mount_model.php 的 add_mount / get_info_by_id / disable_mount_by_id / adorn / take_down_all /
 * get_mount_by_gid / _add_mountchip_log / get_mount_by_page / get_info_by_page / add_exp / check_levelup / add_exp_log /
 * update_general_mount。
 *
 * 存储同旧版：own_mount 每匹坐骑一行（mid 为 MasterData MountTemp 模板，level 从 0 起，属性按 MountLevel 系数算好存入）；
 * 坐骑碎片也存在 own_mount，chip > 0 的行是碎片行（每种坐骑一行，满 10 片自动合成坐骑）。碎片获得在 mountchip_log
 * 记一条，删除坐骑在 mount_info_log 记 type 2。旧版 disable 改为软删除 deleted_at。
 *
 * 并发：碎片的读改写在 Member::locked 的玩家行锁下进行；整匹坐骑是单条批量插入。
 */
final class OwnMount
{
    /** 合成一匹坐骑需要的碎片数（旧版 add_mount 的 $full_num） */
    public const int CHIP_FULL = 10;

    /**
     * 添加坐骑或坐骑碎片（移植 add_mount）
     *
     * $type 为 1 时加 $num 片碎片：已有碎片行就累加，满 10 片按旧版合成；没有碎片行时 $num 不足 10 片插一行碎片，
     * 够 10 片则插 floor($num / 10) 匹坐骑，余数大于 0 再插一行碎片。$type 为 0 时插 $num 匹坐骑。
     * 照旧版保留的怪处：已有碎片行累加后恰好 10 片时，碎片行本身清零变成一匹坐骑（不再另插）；超过 10 片时先按
     * floor(总片数 / 10) 另插坐骑，余数为 0 的碎片行同样变成一匹坐骑，因此会多出一匹。
     *
     * @return array{status: int, info?: array<string, mixed>} status 1 成功，-1 模板不存在，-2 等级表缺 0 级，0 失败；
     *         插入整匹坐骑时 info 为坐骑数据
     */
    public static function addMount(int $uid, int $id, int $type = 0, int $num = 1, string $class = '', string $method = '', ?int $now = null): array
    {
        $temp = MountTemp::ROWS[$id] ?? null;
        if ($temp === null) {
            return ['status' => -1];
        }
        if ($num <= 0) {
            return ['status' => 0];
        }
        $now ??= time();
        if ($type !== 1) {
            return self::insertMounts($uid, $temp, array_fill(0, $num, 0), $now);
        }

        return Member::locked($uid, static function () use ($uid, $id, $num, $temp, $class, $method, $now): array {
            $return = ['status' => 0];
            // 坐骑碎片信息
            $mount = self::chipRow($uid, $id);
            if ($mount !== null) {
                $chip = (int)$mount['chip'] + $num;
                $status = 0;
                if ($chip > self::CHIP_FULL) {
                    // 碎片已超出，添加坐骑，碎片个数减去一匹坐骑需要个数
                    $status = intdiv($chip, self::CHIP_FULL);
                    $chip -= self::CHIP_FULL * $status;
                } elseif ($chip === self::CHIP_FULL) {
                    // 碎片刚好，直接转坐骑
                    $chip = 0;
                }
                $affected = DB::table('own_mount')->where('id', (int)$mount['id'])->where('uid', $uid)->whereNull('deleted_at')
                    ->update(['chip' => $chip, 'dateline' => $now]);
                if ($affected > 0) {
                    $return['status'] = 1;
                }
                if ($status > 0) {
                    self::insertMounts($uid, $temp, array_fill(0, $status, 0), $now);
                }
                self::chipLog($uid, $temp, $num, $chip, $class, $method, $now);
                return $return;
            }

            if ($num >= self::CHIP_FULL) {
                $status = intdiv($num, self::CHIP_FULL);
                $balance = $num - self::CHIP_FULL * $status;
                $chips = array_fill(0, $status, 0);
                if ($balance > 0) {
                    $chips[] = $balance;
                }
            } else {
                $balance = $num;
                $chips = [$num];
            }
            self::chipLog($uid, $temp, $num, $balance, $class, $method, $now);
            return self::insertMounts($uid, $temp, $chips, $now);
        });
    }

    /**
     * 按 id 取玩家的坐骑（移植 get_info_by_id）
     *
     * $type 为 1 时 $mid 是坐骑模板 id、只取碎片行（chip > 0）；否则 $mid 是 own_mount.id。
     * $mid 为数组时返回 [id => 行]，单个时返回一行，取不到返回空数组。
     *
     * @param int|list<int> $mid
     * @return array<int|string, mixed>
     */
    public static function infoById(int $uid, int|array $mid, int $type = 0): array
    {
        $ids = is_array($mid) ? $mid : [$mid];
        if ($ids === []) {
            return [];
        }
        $query = DB::table('own_mount')->where('uid', $uid)->whereNull('deleted_at');
        if ($type === 1) {
            $query->where('chip', '>', 0)->whereIn('mid', $ids);
        } else {
            $query->whereIn('id', $ids);
        }
        if (!is_array($mid)) {
            return $query->first() ?? [];
        }
        $return = [];
        foreach ($query->get() as $row) {
            $return[(int)$row['id']] = $row;
        }
        return $return;
    }

    /**
     * 将领身上的坐骑（移植 get_mount_by_gid）
     *
     * @return list<array<string, mixed>>
     */
    public static function byGid(int $uid, int $gid): array
    {
        return DB::table('own_mount')->where('gid', $gid)->where('uid', $uid)->whereNull('deleted_at')->get();
    }

    /**
     * 删除坐骑（移植 disable_mount_by_id：被吃经验等），每匹在 mount_info_log 记 type 2
     *
     * @param int|list<int> $id own_mount.id
     * @return int 1 成功，0 一匹都没删到
     */
    public static function disable(int $uid, int|array $id, string $class = '', string $method = '', ?int $now = null): int
    {
        $ids = is_array($id) ? $id : [$id];
        if ($ids === []) {
            return 0;
        }
        $now ??= time();
        $rows = self::infoById($uid, $ids);
        $affected = DB::table('own_mount')->where('uid', $uid)->whereIn('id', $ids)->whereNull('deleted_at')
            ->update(['deleted_at' => date('Y-m-d H:i:s', $now)]);
        if ($affected <= 0) {
            return 0;
        }
        $info = Member::logInfo($uid) ?? ['level' => 0, 'vip' => 0];
        foreach ($rows as $row) {
            DB::table('mount_info_log')->insert([
                'uid' => $uid,
                'vip' => $info['vip'],
                'level' => $info['level'],
                'type' => 2,
                'mid' => (int)$row['mid'],
                'mname' => $row['name'],
                'mlevel' => (int)$row['level'],
                'quality' => (int)$row['quality'],
                'exp' => 0,
                'class' => Member::logText($class, 20),
                'method' => Member::logText($method, 20),
                'argument' => '',
                'dateline' => $now,
            ]);
        }
        return 1;
    }

    /**
     * 修改坐骑的佩戴将领（移植 adorn；$gid 为 0 即卸下）
     */
    public static function adorn(int $uid, int $id, int $gid = 0): int
    {
        return DB::table('own_mount')->where('uid', $uid)->where('id', $id)->whereNull('deleted_at')->update(['gid' => $gid]) > 0 ? 1 : 0;
    }

    /**
     * 卸下将领身上所有坐骑（移植 take_down_all：清将领的 mid0 ~ mid4，再把带这位将领的坐骑 gid 清零）
     */
    public static function takeDownAll(int $uid, int $gid): void
    {
        DB::table('general')->where('uid', $uid)->where('id', $gid)->whereNull('deleted_at')->update([
            'mid0' => 0, 'mid1' => 0, 'mid2' => 0, 'mid3' => 0, 'mid4' => 0,
        ]);
        // 旧版只按 gid 清（将领 id 全局唯一），这里加上 uid 条件
        DB::table('own_mount')->where('uid', $uid)->where('gid', $gid)->whereNull('deleted_at')->update(['gid' => 0]);
    }

    /** 坐骑最高等级（旧版 mount_model->_max_level） */
    public const int MAX_LEVEL = 20;
    /** 坐骑列表每页个数（旧版 mount_model->_limit） */
    public const int LIMIT = 8;

    /**
     * 输出用的坐骑行（旧版 SELECT * FROM own_mount 的字段，disable 恒为 0）
     *
     * @param array<string, mixed> $row own_mount 行
     * @return array<string, mixed>
     */
    public static function legacyRow(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'uid' => (int)$row['uid'],
            'gid' => (int)$row['gid'],
            'mid' => (int)$row['mid'],
            'gender' => (int)$row['gender'],
            'name' => (string)$row['name'],
            'quality' => (int)$row['quality'],
            'level' => (int)$row['level'],
            'exp' => (int)$row['exp'],
            'levelup_exp' => (int)$row['levelup_exp'],
            'attack' => (int)$row['attack'],
            'wufang' => (int)$row['wufang'],
            'cefang' => (int)$row['cefang'],
            'hp' => (int)$row['hp'],
            'toexp' => (int)$row['toexp'],
            'disable' => 0,
            'chip' => (int)$row['chip'],
            'dateline' => (int)$row['dateline'],
        ];
    }

    /**
     * 分页查询未装配的坐骑（移植 get_mount_by_page：gid = 0，可排除一个 id；每页 8 个，
     * 按 mid DESC, quality DESC, gender ASC, level DESC, id ASC 排序；$islast 为 1 时页码超出就取最后一页）
     *
     * 与旧版的差别：页码小于 1 时按 1（旧版负数页码会拼出负 offset 的 SQL 报错）。
     *
     * @return array{page: int, allpage: int, list: list<array<string, mixed>>}
     */
    public static function mountByPage(int $uid, int $page, int $id = 0, int $islast = 0): array
    {
        $query = static function () use ($uid, $id) {
            $q = DB::table('own_mount')->where('uid', $uid)->where('gid', 0)->whereNull('deleted_at');
            if ($id > 0) {
                $q->where('id', '!=', $id);
            }
            return $q;
        };
        // 得到总页数
        $allpage = (int)ceil($query()->count() / self::LIMIT);
        $allpage = $allpage > 0 ? $allpage : 1;
        if ($islast === 1 && $page > $allpage) {
            $page = $allpage;
        }
        $page = $page < 1 ? 1 : $page;

        $list = [];
        $rows = $query()->orderBy('mid', 'DESC')->orderBy('quality', 'DESC')->orderBy('gender', 'ASC')
            ->orderBy('level', 'DESC')->orderBy('id', 'ASC')->limit(self::LIMIT)->offset(($page - 1) * self::LIMIT)->get();
        foreach ($rows as $row) {
            $list[] = self::legacyRow($row);
        }
        return ['page' => $page, 'allpage' => $allpage, 'list' => $list];
    }

    /**
     * 某页第一个未装配的坐骑（移植 get_info_by_page：按 quality DESC, gender ASC, level DESC, id ASC，取第 ($page - 1) × 8 个）
     *
     * @return array<string, mixed> 取不到为空数组
     */
    public static function infoByPage(int $uid, int $page): array
    {
        $page = $page < 1 ? 1 : $page;
        $row = DB::table('own_mount')->where('uid', $uid)->where('gid', 0)->whereNull('deleted_at')
            ->orderBy('quality', 'DESC')->orderBy('gender', 'ASC')->orderBy('level', 'DESC')->orderBy('id', 'ASC')
            ->limit(1)->offset(($page - 1) * self::LIMIT)->first();
        return $row === null ? [] : self::legacyRow($row);
    }

    /**
     * 添加经验（移植 add_exp + check_levelup）
     *
     * 逐级扣 MountLevel[level].exp 升级，最多到 20 级（到顶时 exp 停在 19 级的经验槽，溢出的经验 subexp 不计入 toexp）；
     * toexp（被吃时折合的经验）加上实际吸收的经验；升级后按新等级系数重算 attack / wufang / cefang / hp。
     * 旧版不检查坐骑是否存在（取不到按 0 级 0 经验算，UPDATE 不命中）。
     *
     * @return array<string, mixed> 旧版 $data：exp / levelup_exp / level / toexp，升级时再加 attack / wufang / cefang / hp
     */
    public static function addExp(int $uid, int $id, int $addExp): array
    {
        // 查询坐骑信息
        $info = self::infoById($uid, $id);
        $level = (int)($info['level'] ?? 0);
        $result = self::checkLevelup((int)($info['exp'] ?? 0) + $addExp, $level, self::MAX_LEVEL);

        $data = [
            'exp' => $result['exp'] ?? 0,
            'levelup_exp' => $result['levelup_exp'] ?? 0,
            'level' => $result['level'] ?? 0,
            'toexp' => (int)((int)($info['toexp'] ?? 0) + ($addExp - ($result['subexp'] ?? 0))),
        ];

        // 检查是否有升级
        if (($result['level'] ?? 0) > $level) {
            // 查询该坐骑的模版信息，重新计算属性
            $temp = MountTemp::ROWS[(int)($info['mid'] ?? 0)] ?? null;
            if ($temp !== null) {
                $coefficient = (float)$result['coefficient'];
                $data['attack'] = $temp['attack'] + (int)($temp['attack_up'] * $coefficient);
                $data['wufang'] = $temp['wufang'] + (int)($temp['wufang_up'] * $coefficient);
                $data['cefang'] = $temp['cefang'] + (int)($temp['cefang_up'] * $coefficient);
                $data['hp'] = $temp['hp'] + (int)($temp['hp_up'] * $coefficient);
            }
        }

        DB::table('own_mount')->where('uid', $uid)->where('id', $id)->whereNull('deleted_at')->update($data);
        return $data;
    }

    /**
     * 坐骑获得经验记录（移植 add_exp_log）
     */
    public static function addExpLog(int $uid, int $mid, string $midStr, int $add, int $now, int $level, ?int $time = null): void
    {
        DB::table('mount_exp_log')->insert([
            'uid' => $uid,
            'mid' => $mid,
            'exp_mid' => Member::logText($midStr, 200),
            'add_exp' => $add,
            'now_exp' => $now,
            'now_level' => $level,
            'dateline' => $time ?? time(),
        ]);
    }

    /**
     * 修改将领佩戴的坐骑（移植 update_general_mount：$data 为 mid0 ~ mid4 => 坐骑 id）
     *
     * @param array<string, int> $data
     * @return int 1 成功，0 没有改动
     */
    public static function updateGeneralMount(int $uid, int $gid, array $data): int
    {
        $data = array_intersect_key($data, array_flip(['mid0', 'mid1', 'mid2', 'mid3', 'mid4']));
        if ($data === []) {
            return 0;
        }
        return DB::table('general')->where('id', $gid)->where('uid', $uid)->whereNull('deleted_at')->update($data) > 0 ? 1 : 0;
    }

    /**
     * 检查是否升级（移植 check_levelup，递归）；已满级返回空数组
     *
     * @return array{level?: int, subexp?: int, exp?: int, levelup_exp?: int, coefficient?: float}
     */
    private static function checkLevelup(int $exp, int $level, int $maxLevel): array
    {
        if ($level >= $maxLevel) {
            return [];
        }
        // 获取升级需要经验
        $result = MountLevel::ROWS[$level] ?? ['exp' => 0, 'coefficient' => 0.0];
        $levelupExp = (int)$result['exp'];
        if ($exp >= $levelupExp) {
            $level++;
            if ($level === $maxLevel) {
                $result = MountLevel::ROWS[$level] ?? ['exp' => 0, 'coefficient' => 0.0];
                return [
                    'subexp' => $exp - $levelupExp,
                    'level' => $level,
                    'exp' => $levelupExp,
                    'levelup_exp' => (int)$result['exp'],
                    'coefficient' => (float)$result['coefficient'],
                ];
            }
            return self::checkLevelup($exp - $levelupExp, $level, $maxLevel);
        }
        return [
            'level' => $level,
            'subexp' => 0,
            'exp' => $exp,
            'coefficient' => (float)$result['coefficient'],
            'levelup_exp' => $levelupExp,
        ];
    }

    /**
     * 玩家某种坐骑的碎片行（get_info_by_id($id, $uid, 1)）
     *
     * @return array<string, mixed>|null
     */
    private static function chipRow(int $uid, int $mid): ?array
    {
        $row = self::infoById($uid, $mid, 1);
        return $row === [] ? null : $row;
    }

    /**
     * 插入坐骑行（add_mount 的插入部分）：$chips 每项一行，0 为整匹坐骑，大于 0 为碎片行
     *
     * @param array<string, mixed> $temp MountTemp 行
     * @param list<int> $chips
     * @return array{status: int, info?: array<string, mixed>}
     */
    private static function insertMounts(int $uid, array $temp, array $chips, int $now): array
    {
        // 查询等级加成和升级经验信息
        $levelInfo = MountLevel::ROWS[0] ?? null;
        if ($levelInfo === null) {
            return ['status' => -2];
        }
        $coefficient = (float)$levelInfo['coefficient'];
        $data = [
            'uid' => $uid,
            'gid' => 0,
            'mid' => $temp['id'],
            'name' => $temp['name'],
            'quality' => $temp['quality'],
            'level' => 0,
            'exp' => 0,
            'levelup_exp' => $levelInfo['exp'],
            'attack' => $temp['attack'] + (int)($temp['attack_up'] * $coefficient),
            'wufang' => $temp['wufang'] + (int)($temp['wufang_up'] * $coefficient),
            'cefang' => $temp['cefang'] + (int)($temp['cefang_up'] * $coefficient),
            'hp' => $temp['hp'] + (int)($temp['hp_up'] * $coefficient),
            'toexp' => $temp['exp'],
            'chip' => 0,
            'dateline' => $now,
        ];
        $rows = [];
        foreach ($chips as $chip) {
            $rows[] = array_merge($data, ['chip' => $chip]);
        }
        if (DB::table('own_mount')->insertBatch($rows) <= 0) {
            return ['status' => 0];
        }
        return ['status' => 1, 'info' => $data];
    }

    /**
     * mountchip_log 一条（移植 _add_mountchip_log；argument 恒为空串）
     *
     * @param array<string, mixed> $temp
     */
    private static function chipLog(int $uid, array $temp, int $num, int $balance, string $class, string $method, int $now): void
    {
        DB::table('mountchip_log')->insert([
            'uid' => $uid,
            'type' => 1,
            'mname' => $temp['name'],
            'mid' => $temp['id'],
            'quality' => $temp['quality'],
            'num' => $num,
            'blance' => $balance,
            'class' => Member::logText($class, 50),
            'method' => Member::logText($method, 50),
            'argument' => '',
            'dateline' => $now,
        ]);
    }
}
