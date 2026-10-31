<?php

declare(strict_types=1);

namespace Hanjiang\Cron;

use Hanjiang\Handler\Activity\MessageHandler;
use Hanjiang\Model\Flop;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberActivity;
use OmniPHP\Database\DB;

/**
 * 四方来朝计划任务（移植旧版 controllers/cron.php:push_flop_reward「领取四方来朝剩余奖励」/ get_flop_reward 的
 * _get_flop_reward / clear_flop_times「清除四方来朝次数」）
 *
 * 由 server.php 在 HTTP Worker #0 上每 INTERVAL 秒调用 run()。旧版代码包里没有 crontab：
 * - push：只在活动结束后 10 小时内执行（endtime + 36000），当天已生成过记录（flop_cron_log 按日期 version）不再重复；
 *   按每小时执行即可在窗口内发放。
 * - clear：旧版只判断活动未开启，执行时间不明；为了不在发放前清掉次数，这里在发放窗口结束之后才执行。
 */
final class FlopCron
{
    /** 执行间隔（秒） */
    public const int INTERVAL = 3600;

    /** 活动结束后可发放奖励的时长（秒） */
    private const int PUSH_WINDOW = 36000;

    /** 计划任务里抽奖的类型权重（push_flop_reward 写死，各 10） */
    private const array RATE = [1 => 10, 2 => 10, 3 => 10, 4 => 10, 5 => 10, 6 => 10];

    /** 类型 → flop_cron_log 的列 */
    private const array REWARD = [1 => 'gold', 2 => 'silver', 3 => 'act', 4 => 'leap1', 5 => 'leap2', 6 => 'leap3'];

    private const string LANG_MESSAGE_SYSTEM_TITLE = '系统通知';
    private const int MESSAGE_TYPE_SYSTEM = 1;
    private const string LANG_GET_FLOP_REWARD = '朝贡活动已结束，您剩余%s次抽奖机会系統已自动帮您领取。您获得：%s。';
    private const array LANG_BASE = [
        'gold' => '元宝', 'silver' => '银币', 'act' => '军令', 'leap1' => '初级突飞卡', 'leap2' => '中级突飞卡', 'leap3' => '高级突飞卡',
    ];

    public static function run(int $now): void
    {
        self::pushReward($now);
        $activity = Flop::activity();
        if ($activity !== null && $now > $activity['endtime'] + self::PUSH_WINDOW) {
            self::clearFlopTimes($now);
        }
    }

    /**
     * 领取四方来朝剩余奖励（push_flop_reward，不带 limit）
     *
     * @return int -1 无活动；-2 活动未结束；-3 结束超过 10 小时或今天已执行；2 没有玩家有剩余次数；1 已发放
     */
    public static function pushReward(int $now): int
    {
        // 旧版 get_activity_by_start：按名字取活动（starttime 不晚于现在）
        $activity = Flop::activity();
        if ($activity === null || $activity['starttime'] > $now) {
            return -1;
        }
        // 判断活动是否结束
        if ($activity['endtime'] > $now) {
            return -2;
        }
        // 判断是否可发放奖励（活动已结束，并且在活动结束后十个小时内）
        if ($activity['endtime'] + self::PUSH_WINDOW < $now) {
            return -3;
        }
        $ymd = (int)date('Ymd', $now);
        if (DB::table('flop_cron_log')->where('version', $ymd)->whereNull('deleted_at')->value('uid') !== null) {
            // 请勿重复执行
            return -3;
        }
        // 获取有剩余次数的玩家
        $result = DB::table('member_activity')->select('uid', 'floptimes')->where('floptimes', '>', 0)->whereNull('deleted_at')->get();
        if ($result === []) {
            // 没有玩家有剩余次数
            return 2;
        }
        $info = [];
        for ($i = 1; $i <= 6; $i++) {
            $get = Flop::rewardInfo(1, $i);
            $info[$i] = ['name' => $get['name'], 'num' => $get['num'] * $get['multiply']];
        }
        $timesArr = [];
        foreach ($result as $value) {
            $timesArr[(int)$value['uid']] = (int)$value['floptimes'];
        }
        $members = DB::table('member')->select('uid', 'level')->whereIn('uid', array_keys($timesArr))->whereNull('deleted_at')->get();
        foreach ($members as $value) {
            $uid = (int)$value['uid'];
            $times = $timesArr[$uid];
            $log = ['version' => $ymd, 'uid' => $uid, 'times' => $times, 'gold' => 0, 'silver' => 0, 'act' => 0,
                'leap1' => 0, 'leap2' => 0, 'leap3' => 0, 'dateline' => 0];
            for ($i = 1; $i <= $times; $i++) {
                $type = self::roll(self::RATE);
                if ($type === 2) {
                    // 银币乘系数
                    $log[self::REWARD[$type]] += $info[$type]['num'] * (int)$value['level'];
                } else {
                    $log[self::REWARD[$type]] += $info[$type]['num'];
                }
            }
            // 增加朝贡剩余次数记录（主键 uid + version，重复执行不会重复插入）
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_flop_cron_log` (`uid`, `version`, `times`, `gold`, `silver`, `act`, `leap1`, `leap2`, `leap3`, `dateline`)
                 VALUES (:uid, :version, :times, :gold, :silver, :act, :leap1, :leap2, :leap3, :dateline)',
                $log
            );
        }
        self::sendReward($ymd, $now);
        return 1;
    }

    /**
     * 发放（_get_flop_reward）：逐条记发放时间、清剩余次数、加资源并发信。以 dateline 0 → now 的条件更新抢占每一条，多次执行不重复发放
     */
    private static function sendReward(int $ymd, int $now): void
    {
        $cronLog = DB::table('flop_cron_log')->where('version', $ymd)->where('dateline', 0)->whereNull('deleted_at')->get();
        foreach ($cronLog as $value) {
            $uid = (int)$value['uid'];
            $claimed = DB::table('flop_cron_log')->where('version', $ymd)->where('uid', $uid)->where('dateline', 0)->whereNull('deleted_at')
                ->update(['dateline' => $now]);
            if ($claimed === 0) {
                continue;
            }
            MemberActivity::update($uid, ['floptimes' => 0]);
            // 加资源（旧版把 $this->member['vip'] 设为 10 忽略军令上限；add_act(1) 本就不受上限限制）
            $addReward = [];
            foreach (['gold', 'silver', 'act'] as $column) {
                if ((int)$value[$column] > 0) {
                    $addReward[] = self::LANG_BASE[$column] . 'x' . $value[$column];
                }
            }
            Member::addCurrency($uid, silver: (int)$value['silver'], gold: (int)$value['gold'], act: (int)$value['act']);
            $freeleap = 0;
            foreach (['leap1' => 1, 'leap2' => 2, 'leap3' => 4] as $column => $ratio) {
                if ((int)$value[$column] > 0) {
                    $addReward[] = self::LANG_BASE[$column] . 'x' . $value[$column];
                    $freeleap += (int)$value[$column] * $ratio;
                }
            }
            if ($freeleap > 0) {
                FreeLeap::add($uid, $freeleap, $now);
            }
            MessageHandler::sendSystemMessage([$uid], self::MESSAGE_TYPE_SYSTEM, self::LANG_MESSAGE_SYSTEM_TITLE,
                sprintf(self::LANG_GET_FLOP_REWARD, $value['times'], implode(' ', $addReward)));
        }
    }

    /**
     * 清除四方来朝次数（clear_flop_times：活动未开启时，把有剩余次数的玩家的翻牌字段全部归零）
     */
    public static function clearFlopTimes(int $now): int
    {
        if (Flop::isOpen($now)) {
            return 0;
        }
        return DB::table('member_activity')->where('floptimes', '>', 0)->whereNull('deleted_at')->update([
            'floptimes' => 0, 'flopact' => 0, 'flopgold' => 0, 'flopleap' => 0,
            'flopacttimes' => 0, 'flopgoldtimes' => 0, 'flopleaptimes' => 0,
        ]) > 0 ? 1 : 0;
    }

    /**
     * 按权重抽类型（cron.php 的 _roll：从 1 数到 count）
     *
     * @param array<int, int> $rate
     */
    private static function roll(array $rate): int
    {
        $return = 0;
        $rand = mt_rand(1, (int)array_sum($rate));
        $cursor = 0;
        for ($j = 1, $size = count($rate); $j <= $size; $j++) {
            if (isset($rate[$j])) {
                $cursor += $rate[$j];
                $return = $j;
                if ($cursor >= $rand) {
                    break;
                }
            }
        }
        return $return;
    }
}
