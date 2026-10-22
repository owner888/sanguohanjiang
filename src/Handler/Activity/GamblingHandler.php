<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;
use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class GamblingHandler
{
    /**
     * 聚宝博彩主界面: c=gambling&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $state = Redis::getJson('user_gamble:' . $uid) ?: [
            'item_id' => 0,
            'is_cut' => 0,
            'value' => 0,
        ];

        return [
            'status' => 1,
            'bowl_title' => '招财聚宝博彩盆',
            'current' => $state,
            'message' => 'ok',
        ];
    }

    /**
     * 投注原宝: c=gambling&m=buy
     */
    public static function buy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $costSilver = 8000;

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
            return ['status' => -1, 'message' => "银两不足，投注聚宝盆需要 {$costSilver} 银两"];
        }

        $newItem = [
            'item_id' => 1,
            'name' => '聚宝通灵原石',
            'is_cut' => 0,
            'value' => 10000,
        ];
        Redis::setJson('user_gamble:' . $uid, $newItem, 86400);

        return [
            'status' => 1,
            'item' => $newItem,
            'message' => '成功投入聚宝盆！快快下刀抛光解石！',
        ];
    }

    /**
     * 抛光雕琢: c=gambling&m=cut
     */
    public static function cut(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $state = Redis::getJson('user_gamble:' . $uid);

        if (!is_array($state) || empty($state['item_id'])) {
            return ['status' => 0, 'message' => '聚宝盆中空无一物，请先投注原石'];
        }

        $rand = mt_rand(1, 100);
        $multiplier = match (true) {
            $rand <= 15 => 3.0,
            $rand <= 50 => 1.8,
            default     => 1.2,
        };

        $finalVal = (int)round((int)$state['value'] * $multiplier);
        $state['is_cut'] = 1;
        $state['value'] = $finalVal;
        Redis::setJson('user_gamble:' . $uid, $state, 86400);

        if ($multiplier >= 3.0) {
            $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
            $pName = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "财星高照！主公【{$pName}】聚宝博彩开出 3 倍聚宝天赐大奖，估价翻腾！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'multiplier' => $multiplier,
            'final_value' => $finalVal,
            'message' => "精雕细琢！原石灵光显现，暴涨至 {$finalVal} 银两价值！",
        ];
    }

    /**
     * 典当折现: c=gambling&m=sell
     */
    public static function sell(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $state = Redis::getJson('user_gamble:' . $uid);

        if (!is_array($state) || empty($state['value'])) {
            return ['status' => 0, 'message' => '没有可兑现的宝物'];
        }

        $silver = (int)$state['value'];
        MemberHandler::addCurrency($uid, silver: $silver);

        Redis::del('user_gamble:' . $uid);

        return [
            'status' => 1,
            'silver' => $silver,
            'message' => "宝物典当成功！获得银两 {$silver}！",
        ];
    }
}
