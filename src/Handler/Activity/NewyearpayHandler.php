<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\PayReward;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberIntegral;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\TraderLog;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 春节充值活动（移植旧版 controllers/newyearpay.php + models/christmas_model.php 的 reward_list / get_pay_log / get_reward_log /
 * add_reward_log）
 *
 * 活动时间取 ActivityTime 的 newyearpay（旧版数据 starttime = endtime = 0，从未开放；版本号为活动版本 + 1000，type 1 为春节样式）。
 * 活动期间每笔充值由 Model\Pay 记一条 pay_reward_order（add_christmas_log，同时按 10% / type 1 为 20% 返利元宝）；
 * 累计充值 × 10 达到 MasterData PayReward 的额度即可领该档奖励，每期每档一次（pay_reward_log）。
 * 客户端 newyearpaypop.js 调用 pay_reward、get_reward（id）。当前时间取 BossClock（生产恒为 time()）。
 *
 * 照旧版：get_reward 不判断活动是否开启（未开启时版本号为 0，查不到充值记录回 -3）。
 * 与旧版的差别：旧版奖励不存在 / 没有充值记录两处把 $return 写成 $retuen，输出的是未定义变量（null）；这里按注释回 -2 / -3。
 * pay_reward_log 加 (uid, version, rewardid) 唯一键，领取记录写入成功才发奖（旧版先查后写，并发可重复领取）。
 */
final class NewyearpayHandler
{
    public const string ACTIVITY = 'newyearpay';

    /** 活动版本号偏移（旧版 version + 1000，与圣诞充值 christmaspay 的记录区分） */
    public const int VERSION_OFFSET = 1000;

    /**
     * 春节充值: c=newyearpay&m=pay_reward
     *
     * -1 活动未开启
     */
    public static function pay_reward(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $activity = ActivityClock::open(self::ACTIVITY, $now);
        if ($activity === null || $uid <= 0) {
            $return['status'] = -1;
            return $return;
        }
        $version = (int)$activity['version'] + self::VERSION_OFFSET;
        // 取奖励列表
        $list = self::rewardList();
        // 取自己的充值记录
        $amount = self::payAmount($uid, $version);
        // 取自己的领奖记录
        $getLog = self::rewardLog($uid, $version);
        foreach ($list as $key => $val) {
            if ($val['amount'] <= $amount) {
                // 可领取；已领取为 2
                $list[$key]['status'] = isset($getLog[$val['id']]) ? 2 : 1;
            } else {
                // 额度不够
                $list[$key]['status'] = 0;
            }
        }
        $return['status'] = 1;
        $return['now_pay'] = $amount;
        $return['endtime'] = $activity['endtime'] - $now;
        $return['list'] = $list;
        $return['type'] = (int)$activity['type'];
        return $return;
    }

    /**
     * 春节奖励领取: c=newyearpay&m=get_reward（id 奖励 id）
     *
     * -1 奖励已领取
     * -2 奖励信息不存在
     * -3 没有活动充值记录
     * -4 充值金额不足，无法领取该奖励
     * -5 参数不完整
     */
    public static function get_reward(Context $ctx): array
    {
        $return = [];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $id = (int)$ctx->input('id', 0);
        if ($id === 0 || $uid <= 0) {
            $return['status'] = -5;
            return $return;
        }
        $activity = ActivityClock::open(self::ACTIVITY, $now);
        $version = $activity !== null ? (int)$activity['version'] + self::VERSION_OFFSET : 0;
        // 按id获取该奖励的领取状态
        if (isset(self::rewardLog($uid, $version)[$id])) {
            $return['status'] = -1;
            return $return;
        }
        // 获取奖励信息
        $info = PayReward::ROWS[$id] ?? null;
        if ($info === null) {
            $return['status'] = -2;
            return $return;
        }
        // 取自己的充值信息
        if (!DB::table('pay_reward_order')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->exists()) {
            $return['status'] = -3;
            return $return;
        }
        // 判断是否可领取该奖励
        if (self::payAmount($uid, $version) < $info['amount']) {
            $return['status'] = -4;
            return $return;
        }
        // 添加领取记录（唯一键：同一档并发只写入一次）
        $inserted = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_pay_reward_log` (`uid`, `rewardid`, `version`, `dateline`) VALUES (:uid, :rid, :version, :now)',
            ['uid' => $uid, 'rid' => $id, 'version' => $version, 'now' => $now]
        );
        if ($inserted === 0) {
            $return['status'] = -1;
            return $return;
        }
        // 添加奖励
        $value = (int)$info['getvalue'];
        switch ((int)$info['gettype']) {
            case 1: // 银币
                Member::addCurrency($uid, silver: $value);
                break;
            case 2: // 元宝
                Member::addCurrency($uid, gold: $value);
                break;
            case 3: // 蓝宝石
                TraderLog::addJade($uid, 1, $value, class: 'newyearpay', method: 'get_reward', now: $now);
                break;
            case 4: // 金宝石
                TraderLog::addJade($uid, 2, $value, class: 'newyearpay', method: 'get_reward', now: $now);
                break;
            case 5: // 紫宝石
                TraderLog::addJade($uid, 3, $value, class: 'newyearpay', method: 'get_reward', now: $now);
                break;
            case 6: // 玉石
                MemberJade::add($uid, $value, 'newyearpay', 'get_reward', $now);
                break;
            case 7: // 元魂
                OwnSoul::addSoul($uid, $value, 0, 'newyearpay', 'get_reward', $now);
                break;
            case 8: // 突飞卡
                FreeLeap::add($uid, $value, $now);
                break;
            case 9: // 声望
                Member::addReputation($uid, $value);
                break;
            case 10: // 魔石碎片
                OwnGem::addChip($uid, $value, 'newyearpay', 'get_reward', $now);
                break;
            case 11: // 兑奖券（member_integral.act_kemari_int）
                MemberIntegral::change($uid, $value, 'act_kemari_int', 1, 'newyearpay', 'get_reward', $now);
                break;
        }
        $after = DB::table('member')->select('gold', 'silver')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        // 成功返回
        $return['status'] = 1;
        $return['all_gold'] = (int)($after['gold'] ?? 0);
        $return['all_silver'] = (int)($after['silver'] ?? 0);
        $return['gettype'] = (int)$info['gettype'];
        $return['getvalue'] = $value;
        return $return;
    }

    /**
     * 计划任务：充值返利活动结束后发放未领取的礼包奖励（cron.php:push_pay_reward，读 christmas_model 的充值与领取记录）
     *
     * 只在活动结束后 10 小时内执行（endtime <= 当前 <= endtime + 36000），版本号为活动版本 + 1000。累计充值 × 10 达到额度且
     * 没领过的档次写领取记录，同类奖励合并发放，每人一封信件（类型 1 银币 2 元宝 3 蓝宝石 4 黄宝石 5 紫宝石 6 玉石 7 原魂
     * 8 突飞卡 9 声望；10 / 11 计划任务不发，同旧版）。
     * 与旧版的差别：旧版先批量写领取记录再发；这里逐条写（唯一键），写入成功的档次才计入，重复执行不重复发。
     *
     * @return int -1 无活动；-2 活动未结束；-3 结束超过 10 小时；-4 没有充值用户；-5 奖励表为空；-6 没有未领取的用户；1 已发放
     */
    public static function cronPushPayReward(int $now): int
    {
        $activity = ActivityClock::byName(self::ACTIVITY);
        if ($activity === null) {
            return -1;
        }
        if ($activity['endtime'] > $now) {
            return -2;
        }
        if ($activity['endtime'] + 36000 < $now) {
            return -3;
        }
        // 活动版本号（在数据库中是加了 1000 的）
        $version = (int)$activity['version'] + self::VERSION_OFFSET;
        // 查询活动充值用户列表
        $result = DB::query(
            'SELECT `uid`, SUM(`money`) AS `amount` FROM `zc_sanguo_pay_reward_order` WHERE `version` = :version AND `deleted_at` IS NULL GROUP BY `uid` ORDER BY MIN(`id`)',
            ['version' => $version]
        );
        if ($result === []) {
            return -4;
        }
        if (PayReward::ROWS === []) {
            return -5;
        }
        $putReward = [];
        foreach ($result as $val) {
            $uid = (int)$val['uid'];
            foreach (PayReward::ROWS as $small) {
                if ((int)$val['amount'] * 10 < $small['amount']) {
                    continue;
                }
                $inserted = DB::execute(
                    'INSERT IGNORE INTO `zc_sanguo_pay_reward_log` (`uid`, `rewardid`, `version`, `dateline`) VALUES (:uid, :rid, :version, :now)',
                    ['uid' => $uid, 'rid' => $small['id'], 'version' => $version, 'now' => $now]
                );
                if ($inserted > 0) {
                    $putReward[$uid][(int)$small['gettype']] = ($putReward[$uid][(int)$small['gettype']] ?? 0) + (int)$small['getvalue'];
                }
            }
        }
        if ($putReward === []) {
            return -6;
        }
        $names = [1 => '%s银币，', 2 => '%s元宝，', 3 => '%s个蓝宝石，', 4 => '%s个黄宝石，', 5 => '%s个紫宝石，', 6 => '%s玉石，', 7 => '%s原魂，', 8 => '%s张突飞卡，', 9 => '%s声望，'];
        foreach ($putReward as $uid => $val) {
            $message = '充值返利活动已结束，您未领取的充值礼包，系统已自动为您领取，共获得了：';
            foreach ($val as $type => $value) {
                if (!isset($names[$type])) {
                    continue;
                }
                $message .= sprintf($names[$type], $value);
                match ($type) {
                    1 => Member::addCurrency($uid, silver: $value),
                    2 => Member::addCurrency($uid, gold: $value),
                    3 => TraderLog::addJade($uid, 1, $value, class: 'cron', method: 'push_pay_reward', now: $now),
                    4 => TraderLog::addJade($uid, 2, $value, class: 'cron', method: 'push_pay_reward', now: $now),
                    5 => TraderLog::addJade($uid, 3, $value, class: 'cron', method: 'push_pay_reward', now: $now),
                    6 => MemberJade::add($uid, $value, 'cron', 'push_pay_reward', $now),
                    7 => OwnSoul::addSoul($uid, $value, 0, 'cron', 'push_pay_reward', $now),
                    8 => FreeLeap::add($uid, $value, $now),
                    9 => Member::addReputation($uid, $value),
                };
            }
            $message .= '请查收！';
            MessageHandler::sendSystemMessage([$uid], 1, '系统通知', $message);
        }
        return 1;
    }

    /**
     * 充值奖励列表（reward_list）
     *
     * @return list<array<string, int>>
     */
    private static function rewardList(): array
    {
        return array_values(PayReward::ROWS);
    }

    /**
     * 活动期间累计充值 × 10（get_pay_log 的 money 合计 × 10）
     */
    private static function payAmount(int $uid, int $version): int
    {
        $amount = 0;
        foreach (DB::table('pay_reward_order')->select('money')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->get() as $val) {
            $amount += (int)$val['money'];
        }
        return $amount * 10;
    }

    /**
     * 自己的奖励领取记录（get_reward_log）：[rewardid => 行]
     *
     * @return array<int, array<string, mixed>>
     */
    private static function rewardLog(int $uid, int $version): array
    {
        $return = [];
        foreach (DB::table('pay_reward_log')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->get() as $val) {
            $return[(int)$val['rewardid']] = $val;
        }
        return $return;
    }
}
