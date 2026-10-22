<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;
use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class ExerciseHandler
{
    /**
     * 整军经武演习主界面: c=exercise&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $giftCount = (int)(Redis::get('exercise_gifts:' . $uid) ?? 3); // 默认赠送 3 个整军礼包

        return [
            'status' => 1,
            'title' => '整军经武·全军大演习',
            'gift_count' => $giftCount,
            'exercise_rank' => 1,
            'message' => 'ok',
        ];
    }

    /**
     * 开启整军礼包: c=exercise&m=open_gift
     */
    public static function open_gift(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $giftCount = (int)(Redis::get('exercise_gifts:' . $uid) ?? 3);

        if ($giftCount <= 0) {
            return ['status' => 0, 'message' => '暂无待开启的整军礼包'];
        }

        $newCount = $giftCount - 1;
        Redis::set('exercise_gifts:' . $uid, (string)$newCount, 86400 * 30);

        $rewardSilver = 15000;
        $rewardAct = 25;

        MemberHandler::addCurrency($uid, silver: $rewardSilver, act: $rewardAct);

        return [
            'status' => 1,
            'left_gifts' => $newCount,
            'silver' => $rewardSilver,
            'act' => $rewardAct,
            'message' => "成功开启整军演武礼包！获得犒赏银饷 {$rewardSilver}、行军令 {$rewardAct}！",
        ];
    }
}
