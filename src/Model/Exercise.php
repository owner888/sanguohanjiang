<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\Exercise as ExerciseRule;
use Hanjiang\MasterData\ExerciseAddition;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 运营活动：整军演武（移植旧版 models/exercise_model.php，与 city.php 的 _update_exercise、member_model 的演武礼包读写）
 *
 * 活动开启（ActivityTime 的 exercise）期间，征收 / 强征按「今日征收总次数」（levyall + imposetimes）进 Redis 有序集合
 * exercise_rank（前 40 名，旧版键名），排名前 30 的征收银币按 MasterData ExerciseAddition 的百分比加成；
 * 征收总次数到 5 / 10 / … / 50 / 100 次时得礼包（member_activity.exercise_gift），礼包按 MasterData Exercise 开出银币或军令。
 * 每日 7 点征收计划任务把 levyall / imposetimes 归零，排行由 Cron\ExerciseCron 每日 7 点清空（cron::clear_exercise）。
 * 当前时间取 BossClock（生产恒为 time()）。
 */
final class Exercise
{
    public const string ACTIVITY = 'exercise';

    /** 排行榜（旧版 _list_key） */
    public const string RANK_KEY = 'exercise_rank';

    /** 征收锁（旧版 _lock_key：征收后 0.5 秒内不能再征收） */
    private const string LOCK_KEY = 'exercise_lock_';

    /** 键过期时间（旧版 _ttl） */
    private const int TTL = 864000;

    /** 征收次数 => 获得礼包数（get_gift） */
    public const array GIFT_RULE = [5 => 5, 10 => 10, 15 => 15, 20 => 20, 25 => 25, 30 => 30, 35 => 35, 40 => 40, 45 => 45, 50 => 50, 100 => 225];

    /** 2013-05-25 11:00 ~ 2013-05-26 11:00 的特殊奖励（旧版写死，加成 300%） */
    private const int SPECIAL_START = 1369436400;
    private const int SPECIAL_END = 1369522800;

    /**
     * @return array{id: int, name: string, starttime: int, endtime: int, version: int, type: int}|null
     */
    public static function activity(): ?array
    {
        return ActivityClock::byName(self::ACTIVITY);
    }

    public static function isOpen(int $now): bool
    {
        $info = self::activity();
        return $info !== null && $info['starttime'] <= $now && $info['endtime'] >= $now;
    }

    public static function specialAddition(int $now): bool
    {
        return $now >= self::SPECIAL_START && $now <= self::SPECIAL_END;
    }

    /**
     * 排行榜（get_rank_list：按征收次数从高到低的 uid）
     *
     * @return list<int>
     */
    public static function rankList(int $start = 0, int $stop = 29): array
    {
        $list = Redis::zRevRange(self::RANK_KEY, $start, $stop);
        return is_array($list) ? array_map('intval', $list) : [];
    }

    /**
     * 当前的征收次数（get_levytimes）；不在榜上为 false（旧版 zscore 的返回值）
     */
    public static function levytimes(int $uid): int|false
    {
        $score = Redis::zScore(self::RANK_KEY, (string)$uid);
        return $score === false || $score === null ? false : (int)$score;
    }

    /**
     * 记录征收次数（update_rank：榜上超过 40 人且第 40 名高于本次次数时不上榜）
     */
    public static function updateRank(int $levytimes, int $uid): int
    {
        $size = (int)Redis::zCard(self::RANK_KEY);
        if ($size > 39) {
            $rank = Redis::zRevRange(self::RANK_KEY, 39, 39, true);
            foreach ((array)$rank as $value) {
                if ($value > $levytimes) {
                    return -1;
                }
            }
        }
        Redis::zAdd(self::RANK_KEY, $levytimes, (string)$uid);
        return 1;
    }

    public static function clearRank(): void
    {
        Redis::del(self::RANK_KEY);
    }

    /**
     * 排名对应的加成（get_addition：取不到时 0，排名 30 以后 100）
     */
    public static function addition(int $rank): int
    {
        $return = $rank > 30 ? 100 : 0;
        if (isset(ExerciseAddition::ROWS[$rank])) {
            $return = (int)ExerciseAddition::ROWS[$rank]['addition'];
        }
        return $return;
    }

    /**
     * 征收总次数对应的礼包数（get_gift）
     */
    public static function gift(int $levytimes): int
    {
        return self::GIFT_RULE[$levytimes] ?? 0;
    }

    /**
     * 今日征收次数排行（get_exercise_rank：今天 7 点以后执行过征收计划任务、征收过的玩家，取前 30）
     *
     * @return list<array{uid: int, times: int}>
     */
    public static function exerciseRank(int $now): array
    {
        $cronSec = 3600 * 7;
        $dateline = strtotime(date('Y-m-d', $now)) + $cronSec;
        if ($now < $dateline) {
            $dateline -= 86400;
        }
        $rows = DB::query(
            'SELECT `uid`, (`levyall` + `imposetimes`) AS `times` FROM `zc_sanguo_member_field`
             WHERE `exercisetimes` > :dateline AND (`levyall` > 0 OR `imposetimes` > 0) AND `deleted_at` IS NULL
             ORDER BY `times` DESC LIMIT 30',
            ['dateline' => $dateline]
        );
        return array_map(static fn (array $r): array => ['uid' => (int)$r['uid'], 'times' => (int)$r['times']], $rows);
    }

    /**
     * 征收后加锁（set_lock：记录当前微秒时间）
     */
    public static function setLock(int $uid): void
    {
        Redis::set(self::LOCK_KEY . $uid, microtime(true), self::TTL);
    }

    /**
     * 是否在上次征收后 0.5 秒内（is_lock）
     */
    public static function isLock(int $uid): bool
    {
        $redisTime = (float)(Redis::get(self::LOCK_KEY . $uid) ?? 0);
        return $redisTime > 0 && microtime(true) - $redisTime < 0.5;
    }

    /**
     * 礼包奖励（reward：按 rate 抽礼包类型（MasterData Exercise 第 1 / 2 行），再按 multiply1 / 2 / 4 / 8 权重抽倍数；
     * 银币 = 参数 × 主公等级 × 倍数，军令 = 参数 × 倍数）
     *
     * @return array{type: int, hit: int, num: int}
     */
    public static function reward(int $level): array
    {
        $list = array_values(ExerciseRule::ROWS);
        // _get_type
        $typeRate = [1 => (int)$list[0]['rate'], 2 => (int)$list[1]['rate']];
        $roll = mt_rand(1, (int)array_sum($typeRate));
        $cursor = 0;
        $key = 0;
        for ($i = 1, $j = 0; $i <= 2; $j++, $i++) {
            $key = $j;
            $cursor += $typeRate[$i];
            if ($cursor >= $roll) {
                break;
            }
        }
        $rule = $list[$key];

        $rulearr = [];
        for ($i = 1; $i <= 8; $i++) {
            if (isset($rule['multiply' . $i])) {
                $rulearr[$i] = (int)$rule['multiply' . $i];
            }
        }
        $roll = mt_rand(1, (int)array_sum($rulearr));
        $cursor = 0;
        $hit = 0;
        for ($i = 1; $i <= 8; $i++) {
            if (isset($rulearr[$i])) {
                $hit = $i;
                $cursor += $rulearr[$i];
            }
            if ($cursor >= $roll) {
                break;
            }
        }
        $return = ['type' => (int)$rule['id'], 'hit' => $hit];
        if ((int)$rule['id'] === 1) {
            // 奖励银币 = 参数 * 主公等级
            $return['num'] = (int)$rule['num'] * $level * $hit;
        } else {
            // 奖励军令
            $return['num'] = (int)$rule['num'] * $hit;
        }
        return $return;
    }

    /**
     * 更新演武礼包（update_exercise_gift：礼包数与获得时的主公等级）
     */
    public static function updateGift(int $uid, int $num, int $level): int
    {
        return DB::table('member_activity')->where('uid', $uid)->whereNull('deleted_at')
            ->update(['exercise_gift' => $num, 'exercise_level' => $level]) > 0 ? 1 : 0;
    }
}
