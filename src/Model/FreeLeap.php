<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Database\DB;

/**
 * 突飞卡（免费突飞次数 member_field.freeleap；移植旧版 models/tavern_model.php 的 add_freeleap / sub_freeleap / _leap_log）
 *
 * 每次增减在 leap_log 记一条（type 1 获得 / 2 花费，leaptimes 操作数，alltimes 变动后的剩余张数，class / method 取当前请求）。
 * 来源：酒馆突飞卡（tavern tab 3）、神将之门奖励、VIP 礼包 / 工资、首充、过关斩将等；消耗：校场突飞 / 突飞扫荡、名将进阶技能。
 *
 * 与旧版的差别：增减用一条 UPDATE 原子完成（旧版按请求开始时读到的张数读改写，并发会丢）。
 * 依赖未移植：sub_freeleap 的翻牌活动计数（四方来朝 flop，member_activity.flopleap*；旧版数据中 2016-12-31 已结束）。
 */
final class FreeLeap
{
    /**
     * 增加突飞卡（add_freeleap）
     *
     * @return int 1 成功；0 失败（玩家扩展表不存在或数量 <= 0）
     */
    public static function add(int $uid, int $num, ?int $now = null): int
    {
        if ($num <= 0) {
            return 0;
        }
        $affected = DB::execute(
            'UPDATE `zc_sanguo_member_field` SET `freeleap` = LAST_INSERT_ID(`freeleap` + :num) WHERE `uid` = :uid AND `deleted_at` IS NULL',
            ['num' => $num, 'uid' => $uid]
        );
        if ($affected === 0) {
            return 0;
        }
        $balance = (int)DB::query('SELECT LAST_INSERT_ID() AS `v`')[0]['v'];
        self::log($uid, 1, $num, $balance, $now);
        return 1;
    }

    /**
     * 消耗突飞卡（sub_freeleap）
     *
     * @return int 1 成功；-1 张数不足；0 失败
     */
    public static function sub(int $uid, int $num = 1, ?int $now = null): int
    {
        $affected = DB::execute(
            'UPDATE `zc_sanguo_member_field` SET `freeleap` = LAST_INSERT_ID(`freeleap` - :num)
             WHERE `uid` = :uid AND `deleted_at` IS NULL AND `freeleap` >= :need',
            ['num' => $num, 'uid' => $uid, 'need' => $num]
        );
        if ($affected === 0) {
            $left = DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->value('freeleap');
            return $left !== null && (int)$left < $num ? -1 : 0;
        }
        $balance = (int)DB::query('SELECT LAST_INSERT_ID() AS `v`')[0]['v'];
        // 依赖未移植：四方来朝（翻牌）活动计数 flopleap
        self::log($uid, 2, $num, $balance, $now);
        return 1;
    }

    /**
     * 剩余突飞卡
     */
    public static function get(int $uid): int
    {
        return (int)(DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->value('freeleap') ?? 0);
    }

    /**
     * 突飞卡流水（_leap_log；argument 恒为空串）
     */
    private static function log(int $uid, int $type, int $num, int $alltimes, ?int $now): void
    {
        $info = Member::logInfo($uid) ?? ['level' => 0, 'vip' => 0];
        [$class, $method] = FlowLog::current();
        DB::table('leap_log')->insert([
            'uid' => $uid,
            'level' => $info['level'],
            'vip' => $info['vip'],
            'type' => $type,
            'leaptimes' => $num,
            'alltimes' => max(0, $alltimes),
            'class' => Member::logText($class, 20),
            'method' => Member::logText($method, 20),
            'argument' => '',
            'dateline' => $now ?? time(),
        ]);
    }
}
