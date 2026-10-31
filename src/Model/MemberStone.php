<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Database\DB;

/**
 * 精炼石 / 五彩石 / 上古血玉（移植旧版 models/member_model.php 的 add_stone / sub_stone / _stone_log）
 *
 * 存储同旧版：member.refinestone / colorstone / bloodstone 三列（类型 1 / 2 / 3）；每次增减在 stone_log 记一条
 * （stone_type 石头类型，type 1 增加 / 2 减少，stone 为操作数，balance 为变动后的余额）。
 * 旧版 own_stone 表与这三种石头无关（那是赌石玩法 gambling_model 的原石切割记录），不在这里。
 *
 * 增减都是单条原子 UPDATE（Member::incrementColumn / decrementColumn）。与旧版的差别同 MemberJade：
 * 数量为 0 时直接成功、不记流水；流水只在写库成功时记。
 */
final class MemberStone
{
    /** 精炼石 */
    public const int REFINE = 1;
    /** 五彩石 */
    public const int COLOR = 2;
    /** 上古血玉 */
    public const int BLOOD = 3;

    /**
     * 某种石头的当前数量；类型不对或玩家不存在为 0
     */
    public static function get(int $uid, int $type = self::REFINE): int
    {
        $column = self::column($type);
        if ($column === null) {
            return 0;
        }
        return (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value($column) ?? 0);
    }

    /**
     * 三种石头的当前数量（旧版 $this->member 的 refinestone / colorstone / bloodstone）；玩家不存在全为 0
     *
     * @return array{refinestone: int, colorstone: int, bloodstone: int}
     */
    public static function all(int $uid): array
    {
        $row = DB::table('member')->select('refinestone', 'colorstone', 'bloodstone')
            ->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        return [
            'refinestone' => (int)($row['refinestone'] ?? 0),
            'colorstone' => (int)($row['colorstone'] ?? 0),
            'bloodstone' => (int)($row['bloodstone'] ?? 0),
        ];
    }

    /**
     * 增加石头（移植 add_stone）
     *
     * @param int $type 1 精炼石、2 五彩石、3 上古血玉
     * @return array{status: int, stone: int} status 1 成功，0 类型不对，-1 玩家不存在；stone 为增加后的余额
     */
    public static function add(int $uid, int $stone, int $type = self::REFINE, string $class = '', string $method = '', ?int $now = null): array
    {
        $column = self::column($type);
        if ($column === null) {
            return ['status' => 0, 'stone' => 0];
        }
        if ($stone <= 0) {
            return Member::logInfo($uid) === null ? ['status' => -1, 'stone' => 0] : ['status' => 1, 'stone' => self::get($uid, $type)];
        }
        $balance = Member::incrementColumn($uid, $column, $stone);
        if ($balance === null) {
            return ['status' => -1, 'stone' => 0];
        }
        self::log($uid, $type, 1, $stone, $balance, $class, $method, $now);
        return ['status' => 1, 'stone' => $balance];
    }

    /**
     * 消耗石头（移植 sub_stone）
     *
     * @param int $type 1 精炼石、2 五彩石、3 上古血玉
     * @return array{status: int, stone: int} status 1 成功，0 类型不对，-1 数量不足，-2 玩家不存在；stone 为扣除后的余额
     */
    public static function sub(int $uid, int $stone, int $type = self::REFINE, string $class = '', string $method = '', ?int $now = null): array
    {
        $column = self::column($type);
        if ($column === null) {
            return ['status' => 0, 'stone' => 0];
        }
        if ($stone <= 0) {
            return Member::logInfo($uid) === null ? ['status' => -2, 'stone' => 0] : ['status' => 1, 'stone' => self::get($uid, $type)];
        }
        $balance = Member::decrementColumn($uid, $column, $stone);
        if ($balance === null) {
            return Member::logInfo($uid) === null
                ? ['status' => -2, 'stone' => 0]
                : ['status' => -1, 'stone' => self::get($uid, $type)];
        }
        self::log($uid, $type, 2, $stone, $balance, $class, $method, $now);
        return ['status' => 1, 'stone' => $balance];
    }

    private static function column(int $type): ?string
    {
        return match ($type) {
            self::REFINE => 'refinestone',
            self::COLOR => 'colorstone',
            self::BLOOD => 'bloodstone',
            default => null,
        };
    }

    /**
     * stone_log 一条（移植 _stone_log；argument 恒为空串）
     */
    private static function log(int $uid, int $stoneType, int $type, int $stone, int $balance, string $class, string $method, ?int $now): void
    {
        $info = Member::logInfo($uid) ?? ['level' => 0, 'vip' => 0];
        DB::table('stone_log')->insert([
            'uid' => $uid,
            'level' => $info['level'],
            'stone_type' => $stoneType,
            'type' => $type,
            'stone' => $stone,
            'balance' => $balance,
            'vip' => $info['vip'],
            'class' => Member::logText($class, 60),
            'method' => Member::logText($method, 60),
            'argument' => '',
            'dateline' => $now ?? time(),
        ]);
    }
}
