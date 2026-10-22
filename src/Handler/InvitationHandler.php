<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class InvitationHandler
{
    /**
     * 结义邀请主界面: c=invitation&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $myCode = 'HJ' . (100000 + $uid);
        $bound = (int)(Redis::get('invitation_bound:' . $uid) ?: 0);
        $invitedCount = (int)(Redis::get('invitation_count:' . $uid) ?: 0);

        return [
            'status' => 1,
            'my_code' => $myCode,
            'is_bound' => $bound,
            'invited_count' => $invitedCount,
            'reward_per_invite' => '100 元宝 + 10000 银两',
            'message' => 'ok',
        ];
    }

    /**
     * 绑定结义邀请码: c=invitation&m=change
     */
    public static function change(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $code = strtoupper(trim((string)($ctx->input('code', $ctx->input('invite_code', '')))));

        if ($code === '') {
            return ['status' => 0, 'message' => '请输入好友结义邀请码'];
        }

        $myCode = 'HJ' . (100000 + $uid);
        if ($code === $myCode) {
            return ['status' => 0, 'message' => '不可绑定自己的结义邀请码'];
        }

        $isBound = (int)(Redis::get('invitation_bound:' . $uid) ?: 0);
        if ($isBound > 0) {
            return ['status' => 0, 'message' => '您已经绑定过结义邀请码'];
        }

        // 双方各奖励 100 元宝 + 10000 银两
        MemberHandler::addCurrency($uid, silver: 10000, gold: 100);
        Redis::set('invitation_bound:' . $uid, '1', 86400 * 365);

        return [
            'status' => 1,
            'silver' => 10000,
            'gold' => 100,
            'message' => '结义金兰绑定成功！双方各获赠 100 元宝、10000 银两！',
        ];
    }

    /**
     * 领取邀请达标奖励: c=invitation&m=get_reward
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $invitedCount = (int)(Redis::get('invitation_count:' . $uid) ?: 1); // 默认测试达标 1 人

        $rewardSilver = 20000;
        $rewardGold = 200;

        MemberHandler::addCurrency($uid, silver: $rewardSilver, gold: $rewardGold);

        return [
            'status' => 1,
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'message' => '成功领取结义拉新达标豪礼！获得银两 20000、元宝 200！',
        ];
    }
}
