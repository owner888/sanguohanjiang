<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 万圣狂欢、捣蛋南瓜、魔法糖果与万圣兑换处理器 (DDD Activity Domain)
 */
final class ActHalloweenHandler
{
    /**
     * 万圣节主界面: c=act_halloween&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $todayStr = date('Ymd');
        $cacheKey = "halloween_data:{$uid}:{$todayStr}";

        $data = Redis::getJson($cacheKey);
        if (!is_array($data) || !isset($data['list'])) {
            $list = [];
            for ($i = 0; $i < 9; $i++) {
                $list[$i] = [
                    'id' => $i + 1,
                    'status' => 0, // 0: 完好未砸, 1: 已砸开
                ];
            }
            $data = [
                'candy' => 5,
                'hammer' => 3,
                'list' => $list,
            ];
            Redis::setJson($cacheKey, $data, 86400 * 2);
        }

        $allCrushed = 1;
        foreach ($data['list'] as $p) {
            if ($p['status'] === 0) {
                $allCrushed = 0;
                break;
            }
        }

        return [
            'status' => 1,
            'candy' => (int)($data['candy'] ?? 5),
            'hammer' => (int)($data['hammer'] ?? 3),
            'list' => $data['list'],
            'buy_hammer_price' => 35,
            'all_price' => 300,
            'all_button_status' => $allCrushed,
            'date' => date('m.d') . '~' . date('m.d', strtotime('+7 days')),
            'message' => 'ok',
        ];
    }

    /**
     * 砸单个南瓜: c=act_halloween&m=action_pumpkin
     */
    public static function action_pumpkin(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)($ctx->input('id', 1));
        $todayStr = date('Ymd');
        $cacheKey = "halloween_data:{$uid}:{$todayStr}";

        $data = Redis::getJson($cacheKey) ?: [];
        $hammer = (int)($data['hammer'] ?? 0);

        if ($hammer <= 0) {
            return ['status' => -3, 'msg' => '南瓜神锤不足，请先购买锤子'];
        }

        $data['hammer'] = $hammer - 1;
        foreach ($data['list'] as &$p) {
            if ($p['id'] === $id) {
                $p['status'] = 1;
            }
        }
        Redis::setJson($cacheKey, $data, 86400 * 2);

        $silverReward = 50000;
        MemberHandler::addCurrency($uid, silver: $silverReward);

        return [
            'status' => 1,
            'id' => $id,
            'hammer' => $data['hammer'],
            'silver' => $silverReward,
            'reward' => [
                'type' => 1,
                'value' => $silverReward,
                'name' => "银两 x{$silverReward}",
            ],
            'message' => "万圣惊奇！砸开南瓜获得银两 {$silverReward}！",
        ];
    }

    /**
     * 购买南瓜锤: c=act_halloween&m=buy_hammer
     */
    public static function buy_hammer(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $cost = 35;

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ((int)($member['gold'] ?? 0) < $cost) {
            return ['status' => -4, 'msg' => '元宝不足'];
        }

        MemberHandler::deductCurrency($uid, gold: $cost);

        $todayStr = date('Ymd');
        $cacheKey = "halloween_data:{$uid}:{$todayStr}";
        $data = Redis::getJson($cacheKey) ?: [];
        $data['hammer'] = (int)($data['hammer'] ?? 0) + 1;
        Redis::setJson($cacheKey, $data, 86400 * 2);

        $freshMember = DB::table('member')->where('uid', $uid)->first();

        return [
            'status' => 1,
            'hammer' => $data['hammer'],
            'gold' => (int)($freshMember['gold'] ?? 0),
            'message' => '成功购买南瓜神锤 x1！',
        ];
    }

    /**
     * 品尝万圣糖果: c=act_halloween&m=action_candy
     */
    public static function action_candy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $todayStr = date('Ymd');
        $cacheKey = "halloween_data:{$uid}:{$todayStr}";
        $data = Redis::getJson($cacheKey) ?: [];

        $candy = (int)($data['candy'] ?? 0);
        if ($candy <= 0) {
            return ['status' => -4, 'msg' => '万圣糖果不足'];
        }

        $data['candy'] = $candy - 1;
        Redis::setJson($cacheKey, $data, 86400 * 2);

        $soulReward = 50;
        MemberHandler::addCurrency($uid, soul: $soulReward);

        return [
            'status' => 1,
            'candy' => $data['candy'],
            'soul' => $soulReward,
            'message' => "万圣不给糖就捣蛋！品尝糖果获得将魂 {$soulReward}！",
        ];
    }
}
