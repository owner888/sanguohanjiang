<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Member;
use Hanjiang\Model\Pay;
use Hanjiang\Model\Rebate;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 大熊跳楼返利（移植旧版 controllers/rebate.php + models/rebate_model.php，路径相对旧版 s1/application/）
 *
 * 活动时间取 ActivityTime jump_rebate（旧版数据 starttime / endtime 均为 0，活动未开启）。活动期间的充值由充值核心
 * Model\Pay::complete 记入 jump_rebate_order；本期累计充值达到 300 元宝的那天起 30 天内，每天可领 100 元宝
 * （活动结束后仍可领完）。存储见 Model\Rebate。
 *
 * 客户端调用（bearpop.js）：index、get_reward，全部移植。
 * 并发：领取以 jump_rebate.getdateline 条件更新（今天还没领过才写入）占领取资格，同一天并发领取只发一次。
 * 旧版计划任务 cron::put_jump_rebate_reward（一次性补发，写死 rebate_version = 1）为运维用，不移植。
 */
final class RebateHandler
{
    /** 总共可领取多少天（$_all_day） */
    private const int ALL_DAY = 30;

    /** 每日可领取元宝（$_get_gold） */
    private const int GET_GOLD = 100;

    /**
     * 活动信息: c=rebate&m=index
     *
     * get：1 可领取、2 今日已领取、0 不可领取（充值未满 300 元宝）；gold 为本期充值元宝，day 为剩余可领天数，
     * surplus 为剩余可领元宝，cd 为活动剩余秒数，type 为活动类型（0 普通、1 春节）。
     */
    public static function index(Context $ctx): array
    {
        /**
         * -2 获取活动信息失败
         * -1 活动已结束
         *  1 成功
         */
        $return = ['status' => 0, 'get' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::initActivity($uid, $now);
        if ($act === null) {
            return $return;
        }

        // 判断活动是否结束
        if (!$act['activity'] && $now > $act['endtime']) {
            $return['status'] = -1;
            return $return;
        }

        // 查询用户活动信息（首次进入时建档）
        $member = Rebate::member($uid, $act['version']);

        // 获取用户充值金额
        $amount = Rebate::payMoney($uid, $act['version']);

        // 返回领取状态（1 可领取，2 已领取，0 不可领取）
        $day = $act['day'];
        if ($amount * 10 >= Rebate::GOLD) {
            if (date('ymd', $member['getdateline']) === date('ymd', $now)) {
                $return['get'] = 2;
                $day -= 1;
            } else {
                $return['get'] = 1;
            }
        }

        // 成功返回
        $return['status'] = 1;
        $return['gold'] = $amount * 10;
        $return['day'] = $day;
        $return['surplus'] = $day * self::GET_GOLD;
        $return['cd'] = $act['cd'];
        $return['type'] = $act['type'];
        return $return;
    }

    /**
     * 领取今日返利元宝: c=rebate&m=get_reward
     */
    public static function get_reward(Context $ctx): array
    {
        /**
         * -6 修改领取状态失败（同一天并发领取的后到者）
         * -5 今日已领取
         * -4 返利元宝已领完
         * -3 充值未满 300 元宝
         * -2 获取活动信息失败
         * -1 活动已结束
         *  1 成功
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::initActivity($uid, $now);
        if ($act === null) {
            return $return;
        }

        // 判断活动是否结束
        if (!$act['activity'] && $now > $act['endtime']) {
            $return['status'] = -1;
            return $return;
        }

        // 查询用户活动信息
        $member = Rebate::member($uid, $act['version']);

        // 获取用户充值金额；判断充值元宝是否满额
        $amount = Rebate::payMoney($uid, $act['version']);
        if ($amount * 10 < Rebate::GOLD) {
            $return['status'] = -3;
            return $return;
        }

        // 判断返利元宝是否已经领完了
        if (!$act['day']) {
            $return['status'] = -4;
            return $return;
        }

        // 判断今日是否已经领取过奖励了
        if (date('ymd', $member['getdateline']) === date('ymd', $now)) {
            $return['status'] = -5;
            return $return;
        }

        // 修改领取状态
        if (!Rebate::claim($uid, $act['version'], $now)) {
            $return['status'] = -6;
            return $return;
        }
        // 添加奖励元宝
        Member::addCurrency($uid, gold: self::GET_GOLD);
        Redis::del('player:' . $uid);

        // 成功返回
        $return['status'] = 1;
        $return['add'] = self::GET_GOLD;
        $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
        $return['day'] = $act['day'] - 1;
        $return['type'] = $act['type'];
        $return['surplus'] = $return['day'] * self::GET_GOLD;
        return $return;
    }

    /**
     * 初始化活动信息（_init_activity）
     *
     * 活动开启时取活动期号 / 类型 / 剩余秒数；按 member_field.rebate_dateline（本期充值达标的时间）算出领取截止时间与
     * 剩余可领天数。上一期返利还没领完时新一期视为未开启；活动未开启时期号取玩家记录的 rebate_version。
     *
     * @return array{activity: int, version: int, type: int, cd: int, endtime: int, day: int}|null 玩家不存在时为 null
     */
    private static function initActivity(int $uid, int $now): ?array
    {
        $field = DB::table('member_field')->select('rebate_dateline', 'rebate_version')
            ->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($field === null) {
            return null;
        }
        $rebateDateline = (int)$field['rebate_dateline'];
        $rebateVersion = (int)$field['rebate_version'];

        $activity = 0;
        $version = 0;
        $type = 0;
        $cd = 0;
        $info = Pay::activity('jump_rebate', $now);
        if ($info !== null) {
            $activity = 1;
            $version = (int)$info['version'];
            $type = (int)$info['type'];
            $cd = max(0, (int)$info['endtime'] - $now);
        }
        // 算出今天该领第几天的奖励
        $endtime = 0;
        $day = 0;
        if ($rebateDateline > 0) {
            $starttime = (int)strtotime(date('y-m-d', $rebateDateline));
            $endtime = $starttime + self::ALL_DAY * 86400;
            $diff = (int)strtotime(date('y-m-d', $now)) - $starttime;
            $passed = $diff <= 0 ? 0 : intdiv($diff, 86400);
            $day = self::ALL_DAY - $passed;
        }

        if ($activity === 1 && $now < $endtime && $version !== $rebateVersion) {
            // 上次活动奖励还未结束，本次活动又开始了
            $activity = 0;
            $cd = 0;
        }
        // 活动已结束，版本号取用户信息中存储的版本号
        if (!$activity) {
            $version = $rebateVersion;
        }
        // 活动开启，但版本号改变，把用户领取的天数置零
        if ($activity && $rebateVersion !== $version) {
            $day = 0;
        }

        return ['activity' => $activity, 'version' => $version, 'type' => $type, 'cd' => $cd, 'endtime' => $endtime, 'day' => $day];
    }
}
