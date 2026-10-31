<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Member;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 国庆节「天降财神」红包（移植旧版 controllers/act_national_day.php + models/national_day_model.php，计划任务
 * cron_acttask.php:get_nationalday）
 *
 * 活动时间与版本取 ActivityTime 的 national_day（旧版数据从未开放）。活动期间玩家每花一笔元宝，元宝流水（Model\FlowLog 的
 * gold_log type 2，即旧版 member_model->_gold_log）另记一条 national_day_gold_log；第二天起可以打开前一天及以前的红包，
 * 红包为当天消耗元宝的 10%（四舍五入），每天一个（national_day_log）。活动结束后计划任务把没领的红包自动发放并发信件
 * （NationalDayCron）。图标在活动结束后多挂一天：界面显示的结束时间为 endtime - 86401。
 * 客户端 nationaldaypop.js 调用 index、open_red_packets（ymd）。当前时间取 BossClock。
 *
 * 照旧版：index 的红包列表从开始那天排到「结束时间 - 86401」那天；没有消耗的天 status 2、gold 0、没有 title。
 * 与旧版的差别：领取锁改为 Redis SET NX（旧版 get + setex 30 秒）；national_day_log 加 (uid, version, ymd) 唯一键，
 * 领取记录写入成功才加元宝（旧版先加元宝后记录，并发可重复领）。
 */
final class ActNationalDayHandler
{
    public const string ACTIVITY = 'national_day';

    /** 领取锁（_actnational_day_key，30 秒） */
    private const string LOCK_KEY = 'national_dat_get_reward';
    private const int LOCK_TTL = 30;

    /** national_day_lang.php */
    private const array LANG = [
        'ymd_error' => '参数错误',
        'not_open' => '活动未开启',
        'fast_error' => '操作太快了',
        'prohibit_recipients' => '禁止领取',
        'has_received' => '已经领取过了',
        'no_red_packets' => '没有该红包',
        'tips' => '%s月%s日红包',
        'introduction' => '活动期间（%s月%s日-%s月%s日)每日消耗元宝的10%%，隔天将作为红包返还',
        // cron_lang.php（cron_national_day 定义了两次，后一次生效）
        'cron_national_day_info' => '亲爱的主公，您在天降财神中的红包忘记领取啦，',
        'cron_national_day' => '现已自动领取帮您领取%s元宝，请查收！',
        'message_system_title' => '系统通知',
    ];

    /**
     * 获取信息: c=act_national_day&m=index
     *
     * 1 成功；-1 活动未开启
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::init($now);
        // 如果未开启弹出提示
        if ($act === null || $uid <= 0) {
            return ['status' => -1, 'msg' => self::LANG['not_open']];
        }
        $endTime = (int)strtotime(date('Y-m-d', $now));
        // 获取消耗元宝记录
        $loglist = self::goldLog($uid, $act['version'], $act['start'], $endTime);
        // 获取玩家领取红包记录
        $ids = self::receivedYmd($uid, $act['version']);
        $list = [];
        // 组装成每天该玩家总共消耗了多少
        foreach ($loglist as $v) {
            $ymd = date('Ymd', $v['dateline']);
            $list[$ymd]['gold'] = ($list[$ymd]['gold'] ?? 0) + $v['gold'];
            // 1 已经领取；0 未领取
            $list[$ymd]['status'] = in_array((int)$ymd, $ids, true) ? 1 : 0;
        }
        // 得到每个红包的 gold
        $starttime = $act['start'];
        $newlist = [];
        for ($i = date('Ymd', $starttime); $i <= date('Ymd', $act['end']); $i = date('Ymd', $starttime)) {
            if (!isset($list[$i])) {
                // 如果不存在给空
                $oneinfo = ['gold' => 0, 'status' => 2];
            } else {
                $oneinfo = $list[$i];
                $oneinfo['gold'] = (int)round($oneinfo['gold'] * 0.1);
                if ($oneinfo['gold'] <= 0) {
                    $oneinfo['status'] = 2;
                }
                $oneinfo['title'] = sprintf(self::LANG['tips'], (int)date('m', $starttime), (int)date('d', $starttime));
            }
            $oneinfo['ymd'] = date('Ymd', $starttime);
            $newlist[] = $oneinfo;
            $starttime += 86400;
        }
        return [
            'status' => 1,
            'list' => $newlist,
            'cd_time' => $act['cd'],
            'introduction' => sprintf(
                self::LANG['introduction'],
                (int)date('m', $act['start']),
                (int)date('d', $act['start']),
                (int)date('m', $act['end']),
                (int)date('d', $act['end'])
            ),
        ];
    }

    /**
     * 打开红包: c=act_national_day&m=open_red_packets（ymd）
     *
     * 1 成功；-2 参数错误；-3 禁止领取（今天及以后、活动开始前）；-4 已经领取过了；-5 没有该红包；-6 操作太快了。
     * 照旧版：领取不判断活动是否开启（未开启时版本号为 0，查不到消耗记录回 -5）。
     */
    public static function open_red_packets(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        if ($uid <= 0 || !(bool)Redis::instance()->set(self::LOCK_KEY . $uid, 1, ['nx', 'ex' => self::LOCK_TTL])) {
            // 操作太快了
            return ['status' => -6, 'msg' => self::LANG['fast_error']];
        }
        try {
            $act = self::init($now);
            $version = $act['version'] ?? 0;
            $start = $act['start'] ?? 0;
            $ymd = trim((string)$ctx->input('ymd', ''));
            // 参数不能为空
            if ($ymd === '') {
                return ['status' => -2, 'msg' => self::LANG['ymd_error']];
            }
            if (date('Ymd', $now) <= $ymd || $ymd < date('Ymd', $start)) {
                return ['status' => -3, 'msg' => self::LANG['prohibit_recipients']];
            }
            // 判断是否已经有领取记录
            if (in_array((int)$ymd, self::receivedYmd($uid, $version), true)) {
                return ['status' => -4, 'msg' => self::LANG['has_received']];
            }
            // 获取当天的消耗元宝记录（strtotime 解析不了的 ymd 查不到记录）
            $startTime = strtotime($ymd . ' 00:00:00');
            $endTime = strtotime($ymd . ' 23:59:59');
            $loglist = $startTime === false || $endTime === false ? [] : self::goldLog($uid, $version, $startTime, $endTime);
            // 累加所有的钱
            $totalGold = (int)round(array_sum(array_column($loglist, 'gold')) * 0.1);
            if ($loglist === [] || $totalGold <= 0) {
                return ['status' => -5, 'msg' => self::LANG['no_red_packets']];
            }
            // 添加日志（唯一键，并发重复领取时写不进），写入成功才加元宝
            $added = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_national_day_log` (`uid`, `ymd`, `version`, `gold`, `dateline`) VALUES (:uid, :ymd, :version, :gold, :dateline)',
                ['uid' => $uid, 'ymd' => (int)$ymd, 'version' => $version, 'gold' => $totalGold, 'dateline' => $now]
            );
            if ($added <= 0) {
                return ['status' => -4, 'msg' => self::LANG['has_received']];
            }
            // 加金币
            Member::addCurrency($uid, gold: $totalGold);
            return ['gold' => (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold') ?? 0), 'status' => 1];
        } finally {
            Redis::del(self::LOCK_KEY . $uid);
        }
    }

    /**
     * 活动时间（_init）：activity_status 开启时返回版本、开始时间、显示用结束时间（endtime - 86401）与剩余 cd；否则 null
     *
     * @return array{version: int, start: int, end: int, cd: int}|null
     */
    private static function init(int $now): ?array
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null) {
            return null;
        }
        // 活动结束后图标要多挂一天，所以活动会提前一天结束
        $end = (int)$info['endtime'] - 86401;
        return [
            'version' => (int)$info['version'],
            'start' => (int)$info['starttime'],
            'end' => $end,
            'cd' => max(0, $end - $now),
        ];
    }

    /**
     * 元宝消耗记录（get_gold_log：dateline 在 [start, end) 内，按时间升序）
     *
     * @return list<array{gold: int, dateline: int}>
     */
    public static function goldLog(int $uid, int $version, int $start, int $end): array
    {
        $rows = DB::table('national_day_gold_log')->select('gold', 'dateline')->where('version', $version)->where('uid', $uid)
            ->where('dateline', '>=', $start)->where('dateline', '<', $end)->whereNull('deleted_at')->orderBy('dateline')->orderBy('id')->get();
        return array_map(static fn (array $r): array => ['gold' => (int)$r['gold'], 'dateline' => (int)$r['dateline']], $rows);
    }

    /**
     * 已领取红包的日期（get 的 ymd 列）
     *
     * @return list<int>
     */
    private static function receivedYmd(int $uid, int $version): array
    {
        return array_map('intval', array_column(
            DB::table('national_day_log')->select('ymd')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->get(),
            'ymd'
        ));
    }

    /**
     * 计划任务：活动结束后自动领取没领的红包（cron_acttask.php:get_nationalday）
     *
     * 只在活动结束后 10 小时内执行（endtime < 当前 <= endtime + 36000）。统计 [starttime, endtime - 86401) 内的元宝消耗，
     * 按玩家、日期把没有领取记录的天写进 national_day_log，再把这些消耗合计的 10%（四舍五入）加给玩家并发信件。
     * 照旧版：领取记录的 gold 记的是当天的原始消耗（红包按消耗合计算，不是每天各自四舍五入）。
     * 与旧版的差别：旧版每条消耗流水各写一条领取记录；这里每人每天一条（national_day_log 唯一键），记当天消耗合计，
     * 只有写入成功的天才计入红包（与玩家手动领取并发时不重复发）。
     *
     * @return int -1 无活动；-2 活动未结束；-3 结束超过 10 小时；1 已执行
     */
    public static function cronReward(int $now): int
    {
        $activity = ActivityClock::byName(self::ACTIVITY);
        if ($activity === null) {
            return -1;
        }
        // 判断活动是否结束
        if ($activity['endtime'] > $now) {
            return -2;
        }
        // 判断是否可发放奖励（活动已结束，并且在活动结束后 10 小时内）
        if ($activity['endtime'] + 36000 < $now) {
            return -3;
        }
        $version = (int)$activity['version'];
        $end = (int)$activity['endtime'] - 86401;
        // 获得消耗元宝记录
        $rows = DB::table('national_day_gold_log')->select('uid', 'gold', 'dateline')->where('version', $version)
            ->where('dateline', '>=', (int)$activity['starttime'])->where('dateline', '<', $end)->whereNull('deleted_at')
            ->orderBy('dateline')->orderBy('id')->get();
        // 获取玩家领取记录
        $rewardLog = [];
        foreach (DB::table('national_day_log')->select('uid', 'ymd')->where('version', $version)->whereNull('deleted_at')->get() as $val) {
            $rewardLog[$val['uid'] . '-' . $val['ymd']] = true;
        }
        $days = [];
        foreach ($rows as $li) {
            $key = $li['uid'] . '-' . date('Ymd', (int)$li['dateline']);
            if (!isset($rewardLog[$key])) {
                $days[(int)$li['uid']][date('Ymd', (int)$li['dateline'])] = ($days[(int)$li['uid']][date('Ymd', (int)$li['dateline'])] ?? 0) + (int)$li['gold'];
            }
        }
        foreach ($days as $uid => $list) {
            $gold = 0;
            foreach ($list as $ymd => $dayGold) {
                $added = DB::execute(
                    'INSERT IGNORE INTO `zc_sanguo_national_day_log` (`uid`, `ymd`, `version`, `gold`, `dateline`) VALUES (:uid, :ymd, :version, :gold, :dateline)',
                    ['uid' => $uid, 'ymd' => (int)$ymd, 'version' => $version, 'gold' => $dayGold, 'dateline' => $now]
                );
                if ($added > 0) {
                    $gold += $dayGold;
                }
            }
            // 得到每个红包的 gold
            $gold = (int)round($gold * 0.1);
            if ($gold > 0) {
                // 加金币
                Member::addCurrency($uid, gold: $gold);
                // 发信
                MessageHandler::sendSystemMessage([$uid], 1, self::LANG['message_system_title'], self::LANG['cron_national_day_info'] . sprintf(self::LANG['cron_national_day'], $gold));
            }
        }
        return 1;
    }

    /**
     * 国庆节活动期间的元宝消耗另记一条（member_model->_gold_log 的 type 2 分支：activity_status('national_day') 开启、
     * 且 starttime <= 当前 <= endtime 时，把 gold_log 的同一行加上 version 写进 national_day_gold_log）
     *
     * @param array<string, int|string> $row gold_log 的一行（uid / level / type / gold / balance / class / method / argument / dateline / vip）
     */
    public static function addGoldLog(array $row, int $now): void
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null) {
            return;
        }
        $row['version'] = (int)$info['version'];
        DB::table('national_day_gold_log')->insert($row);
    }
}
