<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\WorldBoss;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 每日精彩活动汇总、开放状态与气泡红点处理器 (DDD Activity Domain)
 */
final class ActivityHandler
{
    /**
     * 每日活动主界面: c=activity&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $level = max(1, (int)($member['level'] ?? 1));

        // 连续登录奖励提示气泡
        $signKey = 'dailysign:' . $uid;
        $signData = Redis::getJson($signKey);
        $todayStr = date('Ymd');
        $loginedPopo = (is_array($signData) && ($signData['last_date'] ?? '') === $todayStr) ? 0 : 1;

        // 计算多人副本的真实活动时段状态 (1:进行中, 2:时间未到, 3:今日已结束)
        $isAlwaysOpen = (bool)(getenv('DEV_ACTIVITY_ALL_OPEN') ?: false);

        $eliteStart1 = '12:00';
        $eliteEnd1   = '14:00';
        $eliteStart2 = '19:00';
        $eliteEnd2   = '21:00';
        $eliteStatus = $isAlwaysOpen ? 1 : self::calculateTimeStatus($eliteStart1, $eliteEnd1, $eliteStart2, $eliteEnd2);

        $activities = [
            'lottery' => ['status' => 1, 'starttime' => '', 'endtime' => '', 'openlevel' => 0],
            'treasure' => ['status' => 1, 'starttime' => '', 'endtime' => '', 'openlevel' => 36],
            'drink' => ['status' => 1, 'starttime' => '', 'endtime' => '', 'openlevel' => 0],
            // 世界 BOSS（旧版 activity.php:worldboss_init）
            'boss' => WorldBoss::activityInit($uid, BossClock::now()),
            'dailytask' => ['status' => 1, 'starttime' => '', 'endtime' => '', 'openlevel' => 41],
            'invitation' => ['status' => 1, 'starttime' => '', 'endtime' => '', 'openlevel' => 50],
            'dailytrade' => ['status' => 1, 'starttime' => '', 'endtime' => '', 'openlevel' => 61],
            'tower' => ['status' => 1, 'starttime' => '', 'endtime' => '', 'openlevel' => 51],
            'generaltask' => ['status' => 1, 'starttime' => '', 'endtime' => '', 'openlevel' => 71],
            'logined' => ['popo' => $loginedPopo],
            'elite' => [
                'status' => $eliteStatus,
                'starttime' => $eliteStart1,
                'endtime' => $eliteEnd1,
                'starttime2' => $eliteStart2,
                'endtime2' => $eliteEnd2,
            ],
        ];

        return [
            'status' => 1,
            'list' => $activities,
            'message' => 'ok',
        ];
    }

    /**
     * 根据当前时间计算活动状态 (1: 进行中, 2: 时间未到, 3: 今日已结束)
     */
    public static function calculateTimeStatus(
        string $start1,
        string $end1,
        string $start2 = '',
        string $end2 = ''
    ): int {
        $now = date('H:i');

        // 第一时段进行中
        if ($now >= $start1 && $now < $end1) {
            return 1;
        }

        // 第二时段进行中
        if ($start2 !== '' && $end2 !== '' && $now >= $start2 && $now < $end2) {
            return 1;
        }

        // 第二时段之后：今日已全部结束
        if ($start2 !== '' && $end2 !== '') {
            if ($now >= $end2) {
                return 3;
            }
            if ($now >= $end1 && $now < $start2) {
                return 2; // 两场之间：未到第二场
            }
        } else {
            if ($now >= $end1) {
                return 3;
            }
        }

        // 第一场之前：时间未到
        return 2;
    }
}
