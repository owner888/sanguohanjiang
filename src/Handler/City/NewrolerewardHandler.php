<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;

use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\Newrolereward;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\Member;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use PDOException;

/**
 * 注册前 3 日奖励（移植旧版 controllers/newrolereward.php + models/newrolereward_model.php）
 *
 * 客户端（newbiegiftpop.js）调用：index / get_reward。从首次进入游戏（member_field.start_time）当天起 3 天内开放，第 N 天
 * 领 MasterData Newrolereward 第 N 行（1 银币、2 元宝、3 蓝宝石、4 蓝色突飞卡），记 newrolereward_log（每天一条）。
 * index 的 open：0 未开启、1 可领取、2 已领取、3 过期未领取；endtime 为未到的天数的开放倒计时。
 * 与旧版的差别：newrolereward_log 以 uid + day 为唯一键，并发重复领取被挡下时回 -4（旧版同样是插入失败的状态）。
 */
final class NewrolerewardHandler
{
    /** 活动时长（天） */
    private const int END_DAYS = 3;

    /**
     * 获得奖励信息: c=newrolereward&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = time();
        $startTime = self::startTime($uid);
        $day = self::day($startTime, $now);
        if ($day === 0) {
            // 活动已结束
            $return['status'] = -1;
            return $return;
        }
        $log = self::rewardLog($uid);
        $list = [];
        foreach (Newrolereward::ROWS as $val) {
            if ($val['id'] < $day) {
                $val['open'] = isset($log[$val['id']]) ? 2 : 3;
            } elseif ($val['id'] === $day) {
                $val['open'] = isset($log[$val['id']]) ? 2 : 1;
            } else {
                $val['open'] = 0;
            }
            // 领取奖励倒计时（过期或当天不用倒计时）
            $val['endtime'] = $val['id'] <= $day ? 0 : strtotime(date('y-m-d', $startTime)) + ($val['id'] - 1) * 86400 - $now;
            $list[] = $val;
        }
        $return['status'] = 1;
        $return['list'] = $list;
        return $return;
    }

    /**
     * 领取奖励: c=newrolereward&m=get_reward
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return Member::locked($uid, static function () use ($uid): array {
            $return = ['status' => 0];
            $now = time();
            $day = self::day(self::startTime($uid), $now);
            if ($day === 0) {
                $return['status'] = -1;
                return $return;
            }
            $reward = Newrolereward::ROWS[$day] ?? null;
            if ($reward === null) {
                $return['status'] = -2;
                return $return;
            }
            if (isset(self::rewardLog($uid)[$day])) {
                // 已领取
                $return['status'] = -3;
                return $return;
            }
            try {
                DB::table('newrolereward_log')->insert(['uid' => $uid, 'day' => $day, 'dateline' => $now]);
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                $return['status'] = -4;
                return $return;
            }
            for ($i = 1; $i <= 2; $i++) {
                $value = $reward['value' . $i];
                match ($reward['type' . $i]) {
                    1 => Member::addCurrency($uid, silver: $value),
                    2 => Member::addCurrency($uid, gold: $value),
                    3 => TavernHandler::addJade($uid, 1, $value, 'newrolereward', 'get_reward'),
                    4 => FreeLeap::add($uid, $value, $now),
                    default => null,
                };
            }
            $after = DB::table('member')->select('silver', 'gold')->where('uid', $uid)->first();
            $return['status'] = 1;
            $return['reward'] = $reward;
            $return['all_silver'] = (int)$after['silver'];
            $return['all_gold'] = (int)$after['gold'];
            return $return;
        });
    }

    private static function startTime(int $uid): int
    {
        return (int)(DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->value('start_time') ?? 0);
    }

    /**
     * 今日是第几天（_init：首次进入当天为第 1 天，3 天后为 0 表示活动已结束）
     */
    private static function day(int $startTime, int $now): int
    {
        $diff = strtotime(date('Y-m-d', $now)) - strtotime(date('Y-m-d', $startTime));
        if (86400 * self::END_DAYS - $diff > 0) {
            return intdiv($diff, 86400) + 1;
        }
        return 0;
    }

    /**
     * 奖励领取记录（get_reward_log：day => 行）
     *
     * @return array<int, bool>
     */
    private static function rewardLog(int $uid): array
    {
        $return = [];
        foreach (DB::table('newrolereward_log')->select('day')->where('uid', $uid)->whereNull('deleted_at')->get() as $row) {
            $return[(int)$row['day']] = true;
        }
        return $return;
    }
}
