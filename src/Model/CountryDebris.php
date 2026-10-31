<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Database\DB;

/**
 * 神器碎片（移植旧版 models/country_model.php 的 add_debris / sub_debris / _add_debris_log）
 *
 * 存储同旧版：member_country.debris（神器碎片数量，该列此前已建、尚无读写）；每次增减在 debris_log 记一条
 * （type 1 增加 / 2 减少，add_reduce 为操作数，debris 为变动后的数量）。旧版扣除不检查余额，不足时扣到 0。
 *
 * 增减都是单条原子 UPDATE（LAST_INSERT_ID 取回新值）。与旧版的差别：数量 <= 0 不写库；流水只在写库成功时记
 * （旧版 add_debris 在没有国家记录时也记流水）。
 */
final class CountryDebris
{
    /**
     * 当前神器碎片数；没有国家记录为 0
     */
    public static function get(int $uid): int
    {
        return (int)(DB::table('member_country')->where('uid', $uid)->whereNull('deleted_at')->value('debris') ?? 0);
    }

    /**
     * 添加神器碎片（移植 add_debris）
     *
     * @return int 增加后的碎片数；没有国家记录（旧版写库失败）返回 0
     */
    public static function add(int $uid, int $num, string $class = '', string $method = '', ?int $now = null): int
    {
        if ($num <= 0) {
            return self::get($uid);
        }
        $affected = DB::execute(
            'UPDATE `zc_sanguo_member_country` SET `debris` = LAST_INSERT_ID(`debris` + :num) WHERE `uid` = :uid AND `deleted_at` IS NULL',
            ['num' => $num, 'uid' => $uid]
        );
        if ($affected === 0) {
            return 0;
        }
        $debris = (int)DB::query('SELECT LAST_INSERT_ID() AS `v`')[0]['v'];
        self::log($uid, 1, $num, $debris, $class, $method, $now);
        return $debris;
    }

    /**
     * 扣除神器碎片（移植 sub_debris：不足时扣到 0）
     *
     * @return int 扣除后的碎片数（旧版同样在扣到 0 或失败时返回 0）
     */
    public static function sub(int $uid, int $num, string $class = '', string $method = '', ?int $now = null): int
    {
        if ($num <= 0) {
            return self::get($uid);
        }
        $affected = DB::execute(
            'UPDATE `zc_sanguo_member_country` SET `debris` = LAST_INSERT_ID(IF(`debris` > :num, `debris` - :num2, 0))
             WHERE `uid` = :uid AND `deleted_at` IS NULL',
            ['num' => $num, 'num2' => $num, 'uid' => $uid]
        );
        if ($affected === 0) {
            // 没有国家记录，或本来就是 0
            return 0;
        }
        $debris = (int)DB::query('SELECT LAST_INSERT_ID() AS `v`')[0]['v'];
        self::log($uid, 2, $num, $debris, $class, $method, $now);
        return $debris;
    }

    /**
     * debris_log 一条（移植 _add_debris_log）
     */
    private static function log(int $uid, int $type, int $num, int $debris, string $class, string $method, ?int $now): void
    {
        DB::table('debris_log')->insert([
            'type' => $type,
            'add_reduce' => $num,
            'debris' => $debris,
            'uid' => $uid,
            'class' => Member::logText($class, 20),
            'method' => Member::logText($method, 20),
            'dateline' => $now ?? time(),
        ]);
    }
}
