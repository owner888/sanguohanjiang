<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;
use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class BeatengodHandler
{
    /**
     * 战神试炼主界面: c=beatengod&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $beatenTimes = (int)(Redis::get('beatengod_times:' . $uid) ?: 0);
        $boxClaimed = (int)(Redis::get('beatengod_box:' . $uid) ?: 0);

        return [
            'status' => 1,
            'title' => '真武战神·金光试炼',
            'beaten_times' => $beatenTimes,
            'box_claimed' => $boxClaimed,
            'can_open_box' => ($beatenTimes >= 10 && $boxClaimed === 0) ? 1 : 0,
            'cost_silver' => 3000,
            'message' => 'ok',
        ];
    }

    /**
     * 敲击战神金身试炼: c=beatengod&m=beaten
     */
    public static function beaten(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $costSilver = 3000;

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
            return ['status' => -1, 'message' => "银两不足，参拜敲击战神金身需要 {$costSilver} 银两"];
        }

        $times = (int)(Redis::get('beatengod_times:' . $uid) ?: 0) + 1;
        Redis::set('beatengod_times:' . $uid, (string)$times, 86400 * 7);

        // 随机掉落
        $silver = mt_rand(5000, 15000);
        $soul = mt_rand(10, 30);

        MemberHandler::addCurrency($uid, silver: $silver, soul: $soul);

        return [
            'status' => 1,
            'beaten_times' => $times,
            'silver' => $silver,
            'soul' => $soul,
            'message' => "金光大盛！战神金身赐福：获得银两 {$silver}、将魂 {$soul}！",
        ];
    }

    /**
     * 领取试炼金身大宝箱: c=beatengod&m=get_box_reward
     */
    public static function get_box_reward(Context $ctx): array
    {
        // 依赖未移植：旧版宝箱紫装以上 chat_model->send_message_to_horn(chat_beatengod_get_quality) 小喇叭（随旧版 beatengod 移植）
        $uid = MemberHandler::resolveUid($ctx);
        $times = (int)(Redis::get('beatengod_times:' . $uid) ?: 0);
        $boxClaimed = (int)(Redis::get('beatengod_box:' . $uid) ?: 0);

        if ($times < 10) {
            return ['status' => 0, 'message' => "敲击试炼进度尚未达标 ({$times}/10)，无法开启战神宝箱"];
        }

        if ($boxClaimed > 0) {
            return ['status' => 0, 'message' => '战神试炼宝箱已领取'];
        }

        $rewardSilver = 50000;
        $rewardGold = 50;

        MemberHandler::addCurrency($uid, silver: $rewardSilver, gold: $rewardGold);
        Redis::set('beatengod_box:' . $uid, '1', 86400 * 7);

        return [
            'status' => 1,
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'message' => "战神显圣！开启试炼宝箱获得银两 {$rewardSilver}、元宝 {$rewardGold}！",
        ];
    }
}
