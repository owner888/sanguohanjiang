<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 连续登录大礼包（移植旧版 models/logined_model.php）
 *
 * logined_log：每个玩家一行，连续登录天数（每日计划任务 update_day：昨天登录过 + 1，否则重置为 1）；
 * logined_list：第 1 ~ 15 天礼包的状态（1 可领取、2 未达成、3 已领取），状态变化后写缓存 {Ymd}logined_{uid}（旧版键名，
 * 主城 / 每日活动的连续登录气泡读它）。奖励在 MasterData LoginedReward。
 */
final class Logined
{
    /** 最大登录天数，大于 15 则一直领取第十五天的奖励 */
    public const int MAX_DAY = 15;

    /** 状态缓存键（旧版 date("Ymd").'logined_'.uid） */
    private const string CACHE_KEY = 'logined_';

    /** 领奖锁（旧版 logined_lock_） */
    public const string LOCK_KEY = 'logined_lock_';

    private const array LIST_COLUMNS = ['id', 'uid', 'day', 'dateline', 'status'];

    /**
     * 更新连续登录次数（update_day）
     */
    public static function updateDay(int $uid, int $now): void
    {
        $info = DB::table('logined_log')->select('day', 'dateline')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($info === null) {
            // 第一次登陆添加一个数据
            DB::execute('INSERT IGNORE INTO `zc_sanguo_logined_log` (`uid`, `day`, `dateline`) VALUES (:uid, 1, :now)', ['uid' => $uid, 'now' => $now]);
            return;
        }
        $time1 = strtotime(date('y-m-d', (int)$info['dateline']));
        $time2 = strtotime(date('y-m-d', $now));
        if ($time2 === $time1) {
            return;
        }
        // 取2个时间之间的相差天数：相差 1 天为连续登录，否则重置
        $day = ($time2 - $time1) / 86400 == 1 ? (int)$info['day'] + 1 : 1;
        DB::table('logined_log')->where('uid', $uid)->where('dateline', (int)$info['dateline'])->whereNull('deleted_at')
            ->update(['day' => $day, 'dateline' => $now]);
    }

    /**
     * 连续登录天数记录（get_reward_log(0)）；没有为 null
     *
     * @return array{day: int, dateline: int}|null
     */
    public static function loginLog(int $uid): ?array
    {
        $row = DB::table('logined_log')->select('day', 'dateline')->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * 各天礼包状态（get_reward_log(1)），按记录顺序
     *
     * @return list<array{id: int, uid: int, day: int, dateline: int, status: int}>
     */
    public static function rewardLog(int $uid): array
    {
        $rows = DB::table('logined_list')->select(...self::LIST_COLUMNS)->where('uid', $uid)->whereNull('deleted_at')->orderBy('id')->get();
        return array_map(static fn (array $r): array => array_map('intval', $r), $rows);
    }

    /**
     * 第 $day 天及以前的状态（get_day_log）
     *
     * @return list<array{id: int, uid: int, day: int, dateline: int, status: int}>
     */
    public static function dayLog(int $uid, int $day): array
    {
        $rows = DB::table('logined_list')->select(...self::LIST_COLUMNS)->where('uid', $uid)->where('day', '<=', $day)->whereNull('deleted_at')
            ->orderBy('id')->get();
        return array_map(static fn (array $r): array => array_map('intval', $r), $rows);
    }

    /**
     * 某一天的状态（get_logined_log_by_day）；没有为 null
     *
     * @return array{id: int, uid: int, day: int, dateline: int, status: int}|null
     */
    public static function byDay(int $uid, int $day): ?array
    {
        $row = DB::table('logined_list')->select(...self::LIST_COLUMNS)->where('uid', $uid)->where('day', $day)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * 添加某天的礼包记录（add_reward_log：type 1 可领取，其他未达成；(uid, day) 已有时不插）
     */
    public static function addRewardLog(int $uid, int $day, int $type, int $now): int
    {
        return DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_logined_list` (`uid`, `day`, `dateline`, `status`) VALUES (:uid, :day, :now, :status)',
            ['uid' => $uid, 'day' => $day, 'now' => $now, 'status' => $type === 1 ? 1 : 2]
        ) > 0 ? 1 : 0;
    }

    /**
     * 更新某天的礼包状态（update_reward_log：type 1 可领取、2 未达成、其他已领取；写成功后刷新状态缓存）
     */
    public static function updateRewardLog(int $uid, int $day, int $type, int $now): int
    {
        $status = match ($type) {
            1 => 1,
            2 => 2,
            default => 3,
        };
        $affected = DB::table('logined_list')->where('uid', $uid)->where('day', $day)->whereNull('deleted_at')
            ->update(['dateline' => $now, 'status' => $status]);
        if ($affected > 0) {
            self::writeCache($uid, $now);
            return 1;
        }
        return 0;
    }

    /**
     * 领取：可领取（1）的那一天改为已领取（3），条件更新只成功一次
     */
    public static function claim(int $uid, int $day, int $now): bool
    {
        $affected = DB::table('logined_list')->where('uid', $uid)->where('day', $day)->where('status', 1)->whereNull('deleted_at')
            ->update(['dateline' => $now, 'status' => 3]);
        if ($affected > 0) {
            self::writeCache($uid, $now);
            return true;
        }
        return false;
    }

    /**
     * 状态缓存（get_logined_cache：当天的缓存没有时按库建）
     *
     * @return list<array<string, int>>
     */
    public static function cache(int $uid, int $now): array
    {
        $result = Redis::get(self::cacheKey($uid, $now));
        if (!is_array($result)) {
            $result = self::writeCache($uid, $now);
        }
        return $result;
    }

    /**
     * 最后一次领奖时间（get_time_by_uid）；没有领过为 null
     */
    public static function lastRewardTime(int $uid): ?int
    {
        $value = DB::table('logined_list')->where('uid', $uid)->where('status', 3)->whereNull('deleted_at')->orderBy('dateline', 'DESC')->value('dateline');
        return $value === null ? null : (int)$value;
    }

    /**
     * 连续登录气泡（member.php _logined / activity.php：有可领取的礼包，或今天还没领过奖时显示）
     *
     * @param list<array<string, int>> $list 礼包状态（主城读缓存，每日活动读库）
     */
    public static function popo(int $uid, array $list, int $now): int
    {
        $popo = 1;
        foreach ($list as $val) {
            if ((int)$val['status'] !== 1) {
                $popo = 0;
            } else {
                $popo = 1;
                break;
            }
        }
        $dateline = self::lastRewardTime($uid);
        if ($dateline !== null && date('Ymd', $now) !== date('Ymd', $dateline)) {
            $popo = 1;
        }
        return $popo;
    }

    /**
     * @return list<array<string, int>>
     */
    private static function writeCache(int $uid, int $now): array
    {
        $result = self::rewardLog($uid);
        Redis::set(self::cacheKey($uid, $now), $result, 86400);
        return $result;
    }

    private static function cacheKey(int $uid, int $now): string
    {
        return date('Ymd', $now) . self::CACHE_KEY . $uid;
    }
}
