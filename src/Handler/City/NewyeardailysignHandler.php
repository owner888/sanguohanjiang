<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;

use Hanjiang\Cross\Server;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Member;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 新春签到（感恩签到；移植旧版 controllers/newyeardailysign.php + models/dailysign_model.php 的 newyeardailysign 部分）
 *
 * 活动时间与版本取 ActivityTime 的 newyeardailysign（旧版数据 starttime = endtime = 0，从未开放）。签到期为活动结束前 3 天
 * （endtime - 3 天 ~ endtime），每天签到一次得 100 元宝（dailysign_model->reward['newyeardailysign']，记 dailysign_log，
 * class 为 newyeardailysign）；宝箱每期领一次 500 元宝（treasurebox_log）。客户端 ganenpop.js 调用 index、sign、open_box。
 * 当前时间取 BossClock（生产恒为 time()）。
 *
 * 照旧版：open_box 不要求签满（活动期间随时可开）；index 的 list 按「签到期天数」列出，今天以前没签的 get 为 2。
 * 与旧版的差别：签到 / 开宝箱以唯一键（dailysign_log 的 uid + class + version + day、treasurebox_log 的 uid + version）
 * 写入成功才发奖（旧版先查后写，并发可重复领取）。
 */
final class NewyeardailysignHandler
{
    public const string ACTIVITY = 'newyeardailysign';

    /** 签到类型（旧版 $this->class） */
    private const string CLASS_NAME = 'newyeardailysign';

    /** 签到奖励（dailysign_model->reward['newyeardailysign']） */
    private const int REWARD_GOLD = 100;

    /** 宝箱奖励（dailysign_model->_boxreward） */
    private const int BOX_REWARD = 500;

    /** 签到期：活动结束前 3 天 */
    private const int SIGN_DAYS = 3;

    /** 旧版写死的说明文字（serverid > 3000 为繁体服） */
    private const string DESC = '长久以来，玩家们的大力支持才让悍将走到今天。在此佳节到来之际，我们虔诚感恩的同时献上大礼包一份，希望能带给您好的心情。同时我们郑重承诺，悍将将成为一款持续数年以上的大作，我们会用快速的更新，长久的服务来让您的每一分花费物有所值。';
    private const string DESC_TW = '長久以來，玩家們的大力支持才讓悍將走到今天。在此佳節到來之際，我們虔誠感恩的同時獻上大禮包一份，希望能帶給您好的心情。同時我們鄭重承諾，悍將將成為一款持續數年以上的大作，我們會用快速的更新，長久的服務來讓您的每一分花費物有所值。';

    /**
     * 初试信息: c=newyeardailysign&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $activity = ActivityClock::open(self::ACTIVITY, $now);
        // 判断活动是否开启
        if ($activity === null || $uid <= 0) {
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
        // 算活动总共几天
        $times = max(self::SIGN_DAYS, 1);
        $list = [];
        for ($i = 1; $i <= $times; $i++) {
            $list[$i - 1] = [
                'gold' => self::REWARD_GOLD,
                'silver' => 0,
                'act' => 0,
                'get' => 0,
                'open' => 0,
            ];
            if (isset($userLog[$i])) {
                $list[$i - 1]['get'] = 1;
            } elseif ($i < $day) {
                $list[$i - 1]['get'] = 2;
            }
            if ($day === $i) {
                $list[$i - 1]['open'] = 1;
            }
        }
        // 宝箱领取记录
        $return['box_log'] = self::boxLog($uid, $version) ? 1 : 0;
        $return['status'] = 1;
        $return['time'] = date('n.d', $activity['starttime']) . '~' . date('n.d', $activity['endtime']);
        $return['list'] = $list;
        $return['desc'] = Server::id() > 3000 ? self::DESC_TW : self::DESC;
        return $return;
    }

    /**
     * 签到: c=newyeardailysign&m=sign
     *
     * -1 活动未开启（或还没到签到期）
     * -2 今天已签到
     *  1 成功（add_gold / all_gold 等）
     */
    public static function sign(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $activity = ActivityClock::open(self::ACTIVITY, $now);
        if ($activity === null || $uid <= 0) {
            $return['status'] = -1;
            return $return;
        }
        $day = self::day($activity, $now);
        if ($day <= 0) {
            $return['status'] = -1;
            return $return;
        }
        $return = ['status' => 0, 'add_gold' => 0, 'add_silver' => 0, 'add_act' => 0, 'all_silver' => 0, 'all_gold' => 0, 'all_act' => 0];
        $inserted = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_dailysign_log` (`uid`, `day`, `class`, `version`) VALUES (:uid, :day, :class, :version)',
            ['uid' => $uid, 'day' => $day, 'class' => self::CLASS_NAME, 'version' => (int)$activity['version']]
        );
        if ($inserted === 0) {
            // 今天已签到
            return ['status' => -2];
        }
        $return['status'] = 1;
        // 添加奖励
        $return['add_gold'] = self::REWARD_GOLD;
        Member::addCurrency($uid, gold: self::REWARD_GOLD);
        $member = DB::table('member')->select('act', 'gold', 'silver')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        $return['all_act'] = (int)($member['act'] ?? 0);
        $return['all_gold'] = (int)($member['gold'] ?? 0);
        $return['all_silver'] = (int)($member['silver'] ?? 0);
        return $return;
    }

    /**
     * 宝箱奖励领取: c=newyeardailysign&m=open_box
     *
     * -1 活动未开启
     * -2 宝箱已开启
     *  1 成功（add_gold / all_gold）
     */
    public static function open_box(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $activity = ActivityClock::open(self::ACTIVITY, $now);
        if ($activity === null || $uid <= 0) {
            $return['status'] = -1;
            return $return;
        }
        // 未领取，添加领取记录（主键 uid + version：已领取过时写不进去）
        $inserted = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_treasurebox_log` (`uid`, `version`, `dateline`) VALUES (:uid, :version, :now)',
            ['uid' => $uid, 'version' => (int)$activity['version'], 'now' => $now]
        );
        if ($inserted === 0) {
            // 宝箱已开启
            $return['status'] = -2;
            return $return;
        }
        // 添加奖励
        Member::addCurrency($uid, gold: self::BOX_REWARD);
        // 成功返回
        $return['status'] = 1;
        $return['add_gold'] = self::BOX_REWARD;
        $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold');
        return $return;
    }

    /**
     * 今天是签到期的第几天（签到开始那天为第 1 天，开始以前为 0）
     *
     * @param array<string, int|string> $activity
     */
    private static function day(array $activity, int $now): int
    {
        $starttime = (int)$activity['endtime'] - 86400 * self::SIGN_DAYS;
        $diffTime = strtotime(date('Y-m-d', $now)) - strtotime(date('Y-m-d', $starttime));
        if ($diffTime < 0) {
            return 0;
        }
        return intdiv($diffTime, 86400) + 1;
    }

    private static function boxLog(int $uid, int $version): bool
    {
        return DB::table('treasurebox_log')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->exists();
    }
}
