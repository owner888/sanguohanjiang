<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
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

        // 世界BOSS提示气泡与状态 (12:30-13:00, 20:30-21:00)
        $curTimeStr = date('Hi');
        $bossStatus = 1;
        $bossPopo = 0;

        // 精英副本活动时间 (11:00-13:00, 18:00-20:00)
        $eliteStatus = 1;

        $activities = [
            'lottery' => ['status' => 1, 'starttime' => '', 'endtime' => '', 'openlevel' => 0],
            'treasure' => ['status' => 1, 'starttime' => '', 'endtime' => '', 'openlevel' => 36],
            'drink' => ['status' => 1, 'starttime' => '', 'endtime' => '', 'openlevel' => 0],
            'boss' => [
                'status' => $bossStatus,
                'starttime' => '12:30',
                'endtime' => '13:00',
                'starttime2' => '20:30',
                'endtime2' => '21:00',
                'openlevel' => 46,
                'popo' => $bossPopo,
            ],
            'dailytask' => ['status' => 1, 'starttime' => '', 'endtime' => '', 'openlevel' => 41],
            'invitation' => ['status' => 1, 'starttime' => '', 'endtime' => '', 'openlevel' => 50],
            'dailytrade' => ['status' => 1, 'starttime' => '', 'endtime' => '', 'openlevel' => 61],
            'tower' => ['status' => 1, 'starttime' => '', 'endtime' => '', 'openlevel' => 51],
            'generaltask' => ['status' => 1, 'starttime' => '', 'endtime' => '', 'openlevel' => 71],
            'logined' => ['popo' => $loginedPopo],
            'elite' => [
                'status' => $eliteStatus,
                'starttime' => '11:00',
                'endtime' => '13:00',
                'starttime2' => '18:00',
                'endtime2' => '20:00',
            ],
        ];

        return [
            'status' => 1,
            'list' => $activities,
            'message' => 'ok',
        ];
    }
}
