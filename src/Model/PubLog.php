<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Database\DB;

/**
 * 酒馆将领获得日志（移植旧版 models/tavern_model.php 的 pub_log 部分：get_pub_log_by_id / check_own / add_pub_log /
 * _get_vip_money）
 *
 * 每人每个将领模板一行：own 0 已击败可招募、1 已招募；type 获取方式（1 酒馆、2 征战、3 神将……）；money 为当时的充值金额。
 * 目前只有主线推图（c=map）读写；新版 c=tavern 仍是自定玩法，没有接入。
 * 与旧版的差别：加唯一键 (uid, generalid)，没有记录时 INSERT IGNORE（旧版先查再插，并发会插出两行）。
 */
final class PubLog
{
    /**
     * 玩家某将领的招募日志（get_pub_log_by_id），没有为空数组
     *
     * @return array<string, mixed>
     */
    public static function byGeneral(int $uid, int $generalid): array
    {
        $row = DB::table('pub_log')->select('id', 'uid', 'generalid', 'own', 'dateline', 'nickname', 'level', 'money', 'type')
            ->where('uid', $uid)->where('generalid', $generalid)->whereNull('deleted_at')->first();
        if ($row === null) {
            return [];
        }
        foreach ($row as $key => $value) {
            $row[$key] = $key === 'nickname' ? (string)$value : (int)$value;
        }
        return $row;
    }

    /**
     * 记录将领获得（add_pub_log）
     *
     * @return int 1 记录成功；2 已拥有该武将（不改）；-1 own 不是 0 / 1；-5 玩家不存在；0 没有修改
     */
    public static function add(int $uid, int $generalid, int $own = 1, int $type = 1, ?int $now = null): int
    {
        $member = DB::table('member')->select('uid', 'level', 'nickname')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($member === null) {
            // 获取用户信息失败
            return -5;
        }
        if (!in_array($own, [0, 1], true)) {
            return -1;
        }
        $now ??= time();
        $log = self::byGeneral($uid, $generalid);
        if ($log !== [] && $log['own'] === 1) {
            // 已拥有该武将
            return 2;
        }
        $data = [
            'own' => $own,
            'nickname' => (string)$member['nickname'],
            'level' => (int)$member['level'],
            'type' => $type,
            'money' => (int)(Vip::log($uid)['money'] ?? 0),
            'dateline' => $now,
        ];
        if ($log === []) {
            // 未击败,没有记录
            $affected = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_pub_log` (`uid`, `generalid`, `own`, `dateline`, `nickname`, `level`, `money`, `type`)
                 VALUES (:uid, :generalid, :own, :dateline, :nickname, :level, :money, :type)',
                ['uid' => $uid, 'generalid' => $generalid] + $data
            );
            return $affected > 0 ? 1 : 0;
        }
        // 已击败,有记录
        $affected = DB::table('pub_log')->where('uid', $uid)->where('generalid', $generalid)->whereNull('deleted_at')->update($data);
        return $affected > 0 ? 1 : 0;
    }
}
