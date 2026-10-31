<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\Member;
use Hanjiang\Model\TraderLog;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 周年庆（移植旧版 controllers/anniversary.php + models/anniversary_model.php）
 *
 * 活动时间与版本取 ActivityTime 的 anniversary（旧版数据 2016-12-31 已结束，接口回 2）。活动期间每天可花 300 元宝领一份
 * 写死的奖励（get_reward_list：银币 500 万、突飞卡 50、原魂 5000、紫宝石 50），每天一次（anniversary_log 按第几天记），
 * 每次领取记活动人次 anniversary_action_log。客户端 anniversarypop.js 调用 index、get_reward。当前时间取 BossClock。
 *
 * 与旧版的差别：领取锁改为 Redis SET NX（旧版 get + setex 600 秒，有并发窗口）；领取在玩家行锁事务里执行，anniversary_log
 * 加 (uid, version, day) 唯一键，记录写入成功才扣元宝发奖（旧版先查后写，并发可重复领）。
 */
final class AnniversaryHandler
{
    public const string ACTIVITY = 'anniversary';

    /** 领取奖励价格（$_price） */
    private const int PRICE = 300;

    /** 用户领取奖励锁（_lock_key，600 秒） */
    private const string LOCK_KEY = 'anniversary_lock_';
    private const int LOCK_TTL = 600;

    /** 周年庆奖励列表（get_reward_list 写死；type：1 银币，2 突飞卡，3 原魂，4 紫宝石） */
    private const array REWARD = [
        1 => ['type' => 1, 'value' => 5000000],
        2 => ['type' => 2, 'value' => 50],
        3 => ['type' => 3, 'value' => 5000],
        4 => ['type' => 4, 'value' => 50],
    ];

    /**
     * 初始化接口: c=anniversary&m=index
     *
     * 1 成功；2 活动已结束；-1 获取奖励信息失败
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $activity = ActivityClock::open(self::ACTIVITY, $now);
        // 判断活动是否结束
        if ($activity === null || $uid <= 0) {
            $return['status'] = 2;
            return $return;
        }
        // 获取用户今日是否已经领取过奖励
        $isGet = self::rewardLog($uid, (int)$activity['version'], self::day($activity, $now));
        // 距离下次领取时间
        $cd = $isGet ? self::nextDay($now) - $now : 0;
        // 成功返回
        $return['status'] = 1;
        $return['list'] = self::REWARD;
        $return['cd'] = $cd;
        $return['time'] = date('n.d', $activity['starttime']) . '~' . date('n.d', $activity['endtime'] - 100);
        $return['last_day'] = self::lastDay($activity, $now);
        $return['is_get'] = $isGet;
        $return['price'] = self::PRICE;
        return $return;
    }

    /**
     * 领取奖励: c=anniversary&m=get_reward
     *
     * 1 成功；2 活动已结束；-1 获取奖励信息失败；-2 今日已领取；-3 元宝不足；-4 添加领取记录失败；-5 领取操作未结束（锁）
     */
    public static function get_reward(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        // 判断是否领取操作结束
        if ($uid <= 0 || !(bool)Redis::instance()->set(self::LOCK_KEY . $uid, 1, ['nx', 'ex' => self::LOCK_TTL])) {
            $return['status'] = -5;
            return $return;
        }
        try {
            $activity = ActivityClock::open(self::ACTIVITY, $now);
            // 判断活动是否结束
            if ($activity === null) {
                $return['status'] = 2;
                return $return;
            }
            $version = (int)$activity['version'];
            $day = self::day($activity, $now);
            $status = Member::locked($uid, static function () use ($uid, $version, $day, $now): int {
                // 获取用户今日是否已经领取过奖励
                if (self::rewardLog($uid, $version, $day)) {
                    return -2;
                }
                // 判断元宝是否足够
                $gold = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold') ?? 0);
                if ($gold < self::PRICE) {
                    return -3;
                }
                // 添加奖励领取记录（唯一键，并发重复领取时写不进）
                $added = DB::execute(
                    'INSERT IGNORE INTO `zc_sanguo_anniversary_log` (`uid`, `day`, `version`, `dateline`) VALUES (:uid, :day, :version, :dateline)',
                    ['uid' => $uid, 'day' => $day, 'version' => $version, 'dateline' => $now]
                );
                if ($added <= 0) {
                    return -4;
                }
                // 扣除元宝
                Member::deductCurrency($uid, gold: self::PRICE);
                // 添加奖励（type：1 银币，2 突飞卡，3 原魂，4 紫宝石）
                foreach (self::REWARD as $val) {
                    match ($val['type']) {
                        1 => Member::addCurrency($uid, silver: $val['value']),
                        2 => FreeLeap::add($uid, $val['value'], $now),
                        3 => Member::addCurrency($uid, soul: $val['value']),
                        4 => TraderLog::addJade($uid, 3, $val['value'], class: 'anniversary', method: 'get_reward', now: $now),
                    };
                }
                // 记录活动人次
                $level = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level') ?? 0);
                DB::table('anniversary_action_log')->insert(['uid' => $uid, 'gold' => self::PRICE, 'level' => $level, 'dateline' => $now]);
                return 1;
            });
            if ($status !== 1) {
                $return['status'] = $status;
                return $return;
            }
            $member = DB::table('member')->select('gold', 'silver', 'act')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
            // 成功返回
            $return['status'] = 1;
            $return['all_gold'] = (int)($member['gold'] ?? 0);
            $return['all_silver'] = (int)($member['silver'] ?? 0);
            $return['all_act'] = (int)($member['act'] ?? 0);
            $return['cd'] = self::nextDay($now) - $now;
            $return['last_day'] = self::lastDay($activity, $now);
            return $return;
        } finally {
            // 解锁
            Redis::del(self::LOCK_KEY . $uid);
        }
    }

    /**
     * 活动进行天数（ceil((当前 - 开始时间) / 86400)）
     *
     * @param array<string, int|string> $activity
     */
    private static function day(array $activity, int $now): int
    {
        return (int)ceil(($now - (int)$activity['starttime']) / 86400);
    }

    /**
     * 是否是最后一天（距结束不足 86400 秒）
     *
     * @param array<string, int|string> $activity
     */
    private static function lastDay(array $activity, int $now): int
    {
        return (int)$activity['endtime'] - $now < 86400 ? 1 : 0;
    }

    /**
     * 明天 0 点（strtotime(date('y-m-d')) + 86400）
     */
    private static function nextDay(int $now): int
    {
        return (int)strtotime(date('y-m-d', $now)) + 86400;
    }

    /**
     * 根据天数查询当天是否领取过奖励（get_reward_log）
     */
    private static function rewardLog(int $uid, int $version, int $day): int
    {
        return DB::table('anniversary_log')->where('version', $version)->where('uid', $uid)->where('day', $day)
            ->whereNull('deleted_at')->exists() ? 1 : 0;
    }
}
