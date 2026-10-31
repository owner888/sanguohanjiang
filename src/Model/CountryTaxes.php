<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Database\DB;

/**
 * 国家税金（移植旧版 models/member_country_taxes_model.php）
 *
 * 每个玩家一行 member_country_taxes：第一次读取（getinfo）时按旧版以国家总贡献 allcontribute × 0.1 建档（流水 type 1）。
 * 国家贡献增加时按新增今日贡献 × 0.1 累加（country_model->update_member_info 的 pretreatment → change_taxes('+')，流水 type 2）；
 * 税金商城购买时扣除（change_taxes('-')，流水 type 3）。写库前取整：增加按 floor，建档按 MySQL 写入 int 列的四舍五入。
 * 流水 country_taxes_shop_log 的 taxes 记的是本次变化量（不是余额），同旧版。
 *
 * 与旧版的差别（多 Worker 安全）：旧版读出后写回绝对值；这里增加用一条 UPDATE 原子累加，扣除用 taxes >= 价格的条件 UPDATE
 * （不足时不扣、返回 null，旧版调用方先检查余额再扣，正常流程结果相同）；建档用唯一键 + INSERT IGNORE 只建一次。
 */
final class CountryTaxes
{
    public const int LOG_INIT = 1;
    public const int LOG_ADD = 2;
    public const int LOG_SUB = 3;

    /**
     * 玩家税金信息（getinfo：没有时建档；附带国家总贡献 contribute 与将军令 general）
     *
     * @return array{status: int, info: array<string, int>}
     */
    public static function getinfo(int $uid, int $country, ?int $now = null): array
    {
        $row = self::row($uid);
        if ($row === null) {
            self::init($uid, $country, $now ?? time());
            $row = self::row($uid);
        }
        $return = ['status' => $row === null ? -1 : 1, 'info' => $row ?? []];
        // 拿出贡献
        $memberCountry = DB::table('member_country')->select('allcontribute')->where('uid', $uid)->whereNull('deleted_at')->first();
        $return['info']['contribute'] = $memberCountry !== null ? (int)$memberCountry['allcontribute'] : 0;
        // 得到将军令
        $return['info']['general'] = (int)(MemberIntegral::get($uid)['general'] ?? 0);
        return $return;
    }

    /**
     * 增加税金（change_taxes('+')）
     *
     * @return int 增加后的税金
     */
    public static function add(int $uid, int $country, float $value, ?int $now = null): int
    {
        $now ??= time();
        self::getinfo($uid, $country, $now);
        self::log($uid, $country, self::LOG_ADD, $value, $now);
        DB::execute(
            'UPDATE `zc_sanguo_member_country_taxes` SET `taxes` = FLOOR(`taxes` + :value) WHERE `uid` = :uid AND `deleted_at` IS NULL',
            ['value' => $value, 'uid' => $uid]
        );
        return (int)(self::row($uid)['taxes'] ?? 0);
    }

    /**
     * 扣除税金（change_taxes('-')）
     *
     * @return int|null 扣除后的税金；税金不足为 null（不扣、不记流水）
     */
    public static function sub(int $uid, int $country, int $value, ?int $now = null): ?int
    {
        $now ??= time();
        self::getinfo($uid, $country, $now);
        $affected = DB::execute(
            'UPDATE `zc_sanguo_member_country_taxes` SET `taxes` = `taxes` - :value
             WHERE `uid` = :uid AND `taxes` >= :need AND `deleted_at` IS NULL',
            ['value' => $value, 'uid' => $uid, 'need' => $value]
        );
        if ($affected === 0) {
            return null;
        }
        self::log($uid, $country, self::LOG_SUB, $value, $now);
        return (int)(self::row($uid)['taxes'] ?? 0);
    }

    /**
     * @return array<string, int>|null
     */
    private static function row(int $uid): ?array
    {
        $row = DB::table('member_country_taxes')->select('id', 'uid', 'country_id', 'taxes', 'dateline')
            ->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * 建档（_init：税金 = 国家总贡献 × 0.1，记流水 type 1）
     */
    private static function init(int $uid, int $country, int $now): void
    {
        $allcontribute = DB::table('member_country')->where('uid', $uid)->whereNull('deleted_at')->value('allcontribute');
        $taxes = $allcontribute !== null ? (int)$allcontribute * 0.1 : 0;
        $inserted = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_member_country_taxes` (`uid`, `country_id`, `taxes`, `dateline`) VALUES (:uid, :country, :taxes, :now)',
            ['uid' => $uid, 'country' => $country, 'taxes' => (int)round($taxes), 'now' => $now]
        );
        if ($inserted > 0) {
            self::log($uid, $country, self::LOG_INIT, $taxes, $now);
        }
    }

    /**
     * 税金流水（save_log：taxes 为本次变化量，写入 int 列四舍五入）
     */
    private static function log(int $uid, int $country, int $type, float|int $taxes, int $now): void
    {
        DB::table('country_taxes_shop_log')->insert([
            'uid' => $uid,
            'country_id' => $country,
            'taxes' => (int)round($taxes),
            'type' => $type,
            'dateline' => $now,
        ]);
    }
}
