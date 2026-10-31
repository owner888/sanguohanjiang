<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;

use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Member;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 节日签到（移植旧版 controllers/dailysign.php + models/dailysign_model.php 的 dailysign 部分）
 *
 * 活动时间与版本取 ActivityTime 的 dailysign（旧版数据中未开启，index 回 -1）。活动期间每天可签到一次（dailysign_log 记第几天），
 * 奖励写死在旧版模型里：军令 5、银币 1000 × 主公等级（add_act(1) 不受军令上限限制）。界面日期照旧版写死「9.30-10.7」。
 * 新春签到（newyeardailysign，同一个模型的 class 区分）是节日活动，不在本类。客户端 dailysignpop.js 调用 index、sign。
 * 当前时间取 BossClock（生产恒为 time()）。
 *
 * 与旧版的差别：旧版 sign 不判断活动是否开启，未开启时版本号未定义、SQL 出错；这里回 -1（客户端把 -1 以外都当签到成功）。
 * dailysign_log 加 (uid, class, version, day) 唯一键，并发签到只成功一次。
 */
final class DailySignHandler
{
    public const string ACTIVITY = 'dailysign';

    /** 签到类型（旧版 $this->class） */
    private const string CLASS_NAME = 'dailysign';

    /** 签到奖励（dailysign_model->reward['dailysign']：银币按主公等级倍数） */
    private const int REWARD_ACT = 5;
    private const int REWARD_SILVER = 1000;

    /** 节日签到开放日期（旧版写死） */
    private const string DATE = '9.30-10.7';

    /**
     * 初试信息: c=dailysign&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $activity = self::openActivity($now);
        $member = $uid > 0 ? DB::table('member')->select('level')->where('uid', $uid)->whereNull('deleted_at')->first() : null;
        // 判断活动是否开启
        if ($activity === null || $member === null) {
            $return['status'] = -1;
            return $return;
        }
        $version = (int)$activity['version'];
        $day = self::day($activity, $now);
        // 取用户信息
        $userLog = [];
        foreach (DB::table('dailysign_log')->select('day')->where('uid', $uid)->where('class', self::CLASS_NAME)->where('version', $version)
            ->whereNull('deleted_at')->get() as $row) {
            $userLog[(int)$row['day']] = true;
        }
        $times = ($activity['endtime'] - $activity['starttime']) / 86400 + 1;
        $list = [];
        for ($i = 1; $i <= $times; $i++) {
            $list[$i - 1] = [
                'gold' => 0,
                'silver' => self::REWARD_SILVER * (int)$member['level'],
                'act' => self::REWARD_ACT,
                'get' => isset($userLog[$i]) ? 1 : 0,
                'open' => $day === $i ? 1 : 0,
            ];
        }
        $return['status'] = 1;
        $return['list'] = $list;
        // 节日签到开放日期
        $return['date'] = self::DATE;
        return $return;
    }

    /**
     * 签到: c=dailysign&m=sign
     */
    public static function sign(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $activity = self::openActivity($now);
        if ($activity === null || $uid <= 0) {
            // 旧版活动未开启时 SQL 出错，这里按「今天已签到」返回
            $return['status'] = -1;
            return $return;
        }
        $version = (int)$activity['version'];
        $day = self::day($activity, $now);
        $return = ['status' => 0, 'add_gold' => 0, 'add_silver' => 0, 'add_act' => 0, 'all_silver' => 0, 'all_gold' => 0, 'all_act' => 0];
        $inserted = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_dailysign_log` (`uid`, `day`, `class`, `version`) VALUES (:uid, :day, :class, :version)',
            ['uid' => $uid, 'day' => $day, 'class' => self::CLASS_NAME, 'version' => $version]
        );
        if ($inserted === 0) {
            // 今天已签到
            return ['status' => -1];
        }
        $return['status'] = 1;
        // 添加奖励
        $level = (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level');
        $return['add_act'] = self::REWARD_ACT;
        $return['add_silver'] = self::REWARD_SILVER * $level;
        Member::addCurrency($uid, silver: $return['add_silver'], act: $return['add_act']);
        $member = DB::table('member')->select('act', 'gold', 'silver')->where('uid', $uid)->first();
        $return['all_act'] = (int)$member['act'];
        $return['all_gold'] = (int)$member['gold'];
        $return['all_silver'] = (int)$member['silver'];
        return $return;
    }

    /**
     * 今天是活动的第几天（活动开始那天为第 1 天）
     *
     * @param array<string, int|string> $activity
     */
    private static function day(array $activity, int $now): int
    {
        $diff = strtotime(date('Y-m-d', $now)) - strtotime(date('Y-m-d', (int)$activity['starttime']));
        return $diff > 0 ? (int)($diff / 86400 + 1) : 1;
    }

    /**
     * @return array<string, int|string>|null
     */
    private static function openActivity(int $now): ?array
    {
        return ActivityClock::open(self::ACTIVITY, $now);
    }
}
