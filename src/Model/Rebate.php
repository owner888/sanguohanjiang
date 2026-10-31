<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Database\DB;

/**
 * 大熊跳楼返利（移植旧版 models/rebate_model.php）
 *
 * 存储同旧版：jump_rebate（每人每期一行，getdateline 为上次领取返利时间）、jump_rebate_order（活动期间的充值记录）；
 * 本期累计充值达到 300 元宝的时间与期号记在 member_field.rebate_dateline / rebate_version，此后 30 天每天可领 100 元宝。
 */
final class Rebate
{
    /** 充值满 300 元宝可返利（$_gold） */
    public const int GOLD = 300;

    /**
     * 获取用户活动信息（get_member_info：首次进入时插入 getdateline = 0 的记录；唯一键 uid + version，并发只插入一行）
     *
     * @return array{uid: int, version: int, getdateline: int}
     */
    public static function member(int $uid, int $version): array
    {
        $row = DB::table('jump_rebate')->select('uid', 'version', 'getdateline')
            ->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first();
        if ($row !== null) {
            return array_map('intval', $row);
        }
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_jump_rebate` (`uid`, `version`, `getdateline`) VALUES (:uid, :version, 0)',
            ['uid' => $uid, 'version' => $version]
        );
        return ['uid' => $uid, 'version' => $version, 'getdateline' => 0];
    }

    /**
     * 修改领取时间（update_member_info(['getdateline' => now])）
     *
     * 多 Worker 安全：只在今天还没领过时写入（同一天并发领取只有一个成功），成功返回 1。
     */
    public static function claim(int $uid, int $version, int $now): int
    {
        return DB::execute(
            'UPDATE `zc_sanguo_jump_rebate` SET `getdateline` = :now
             WHERE `uid` = :uid AND `version` = :version AND `getdateline` < :today AND `deleted_at` IS NULL',
            ['now' => $now, 'uid' => $uid, 'version' => $version, 'today' => strtotime(date('Y-m-d', $now))]
        ) > 0 ? 1 : 0;
    }

    /**
     * 记录活动期间的充值（add_rebate_order）：本期累计充值首次达到 300 元宝时记下达标时间与期号
     *
     * @param int $amount 充值金额（人民币）
     */
    public static function addOrder(int $uid, int $level, int $amount, string $orderid, int $version, int $now): void
    {
        // 查询目前充值金额
        $paid = self::payMoney($uid, $version);
        DB::table('jump_rebate_order')->insert([
            'uid' => $uid,
            'level' => $level,
            'money' => $amount,
            'version' => $version,
            'orderid' => $orderid,
            'class' => 'pay',
            'method' => 'complete',
            'dateline' => $now,
        ]);
        // 判断是否达到返利额度
        if ($paid * 10 < self::GOLD && ($paid + $amount) * 10 >= self::GOLD) {
            DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update([
                'rebate_dateline' => $now,
                'rebate_version' => $version,
            ]);
        }
    }

    /**
     * 查询本期累计充值金额（get_user_pay_money：SUM(money)，没有记录时旧版为 null，参与运算按 0）
     *
     * 旧版取到的是字符串，乘 10 后整数金额为 int；这里整数金额同样回 int。
     */
    public static function payMoney(int $uid, int $version): int|float
    {
        $rows = DB::query(
            'SELECT SUM(`money`) AS `amount` FROM `zc_sanguo_jump_rebate_order` WHERE `uid` = :uid AND `version` = :version AND `deleted_at` IS NULL',
            ['uid' => $uid, 'version' => $version]
        );
        $amount = (float)($rows[0]['amount'] ?? 0);
        return floor($amount) === $amount ? (int)$amount : $amount;
    }
}
