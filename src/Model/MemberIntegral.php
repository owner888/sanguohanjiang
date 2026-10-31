<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Database\DB;

/**
 * 玩家积分记录（移植旧版 member_model->get_memint_info / change_memint / add_memint_log）
 *
 * 存储同旧版 member_integral：每个玩家一行（唯一键 uid），首次读取时插入默认行（各积分字段 0）。
 * change 的每次写入在 integral_log 记一条（type 1 增加 / 2 消耗，balance 为变化后的值，class / method 为调用的控制器与方法）。
 *
 * 与旧版的差别（多 Worker 安全）：旧版读出后写回绝对值；这里用一条 UPDATE 原子增减并取回新值，
 * 消耗时余额不足不扣、返回空数组（旧版各调用方都先检查余额再扣，正常流程结果相同）。
 */
final class MemberIntegral
{
    /** 旧版 get_memint_info 首次插入的默认字段 */
    public const array DEFAULT = [
        'act_kemari_int' => 0,
        'act_arena_int' => 0,
        'beauty_rose' => 0,
        'beauty_diamond' => 0,
        'general' => 0,
        'candy' => 0,
        'hammer' => 0,
        'medal' => 0,
        'god_soul' => 0,
    ];

    /** change 允许的积分列 */
    private const array COLUMNS = ['act_kemari_int', 'act_arena_int', 'beauty_rose', 'beauty_diamond', 'general', 'candy', 'hammer', 'medal', 'god_soul'];

    /**
     * 玩家积分信息（get_memint_info：没有时插入默认行）
     *
     * @return array<string, mixed>
     */
    public static function get(int $uid): array
    {
        $row = DB::table('member_integral')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row !== null) {
            return $row;
        }
        $data = ['uid' => $uid] + self::DEFAULT;
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_member_integral` (`uid`, `act_kemari_int`, `act_arena_int`, `beauty_rose`, `beauty_diamond`, `general`, `candy`, `hammer`, `medal`, `god_soul`)
             VALUES (:uid, 0, 0, 0, 0, 0, 0, 0, 0, 0)',
            ['uid' => $uid]
        );
        return DB::table('member_integral')->where('uid', $uid)->whereNull('deleted_at')->first() ?? $data;
    }

    /**
     * 积分变化（change_memint：type 1 增加、2 消耗；写入成功才记日志）
     *
     * @return array<string, int> [$arg => 变化后的值]；没有写入（消耗时余额不足）为空数组
     */
    public static function change(int $uid, int $num, string $arg, int $type, string $class, string $method, ?int $now = null): array
    {
        if (!in_array($arg, self::COLUMNS, true) || ($type !== 1 && $type !== 2)) {
            return [];
        }
        self::get($uid);
        if ($type === 1) {
            $affected = DB::execute(
                "UPDATE `zc_sanguo_member_integral` SET `{$arg}` = LAST_INSERT_ID(`{$arg}` + :num) WHERE `uid` = :uid AND `deleted_at` IS NULL",
                ['num' => $num, 'uid' => $uid]
            );
        } else {
            $affected = DB::execute(
                "UPDATE `zc_sanguo_member_integral` SET `{$arg}` = LAST_INSERT_ID(`{$arg}` - :num)
                 WHERE `uid` = :uid AND `deleted_at` IS NULL AND `{$arg}` >= :need",
                ['num' => $num, 'uid' => $uid, 'need' => $num]
            );
        }
        if ($affected <= 0) {
            return [];
        }
        $balance = (int)DB::query('SELECT LAST_INSERT_ID() AS `v`')[0]['v'];
        DB::table('integral_log')->insert([
            'uid' => $uid,
            'type' => $type,
            'intnum' => $num,
            'balance' => $balance,
            'class' => $class,
            'method' => $method,
            'argument' => $arg,
            'dateline' => $now ?? time(),
        ]);
        return [$arg => $balance];
    }
}
