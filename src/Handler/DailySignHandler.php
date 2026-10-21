<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class DailySignHandler
{
    /**
     * 签到主界面: c=dailysign&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $now = time();

        $signKey = 'dailysign:' . $uid;
        $signData = Redis::getJson($signKey) ?: ['total' => 0, 'last_date' => ''];
        $todayStr = date('Ymd', $now);
        $signedToday = ($signData['last_date'] === $todayStr) ? 1 : 0;

        $level = max(1, (int)($member['level'] ?? 1));

        $list = [];
        for ($i = 1; $i <= 7; $i++) {
            $isClaimed = ($i <= (int)$signData['total']) ? 1 : 0;
            $isOpen = ($i === (int)$signData['total'] + 1 && !$signedToday) ? 1 : 0;

            $list[] = [
                'day' => $i,
                'silver' => $i * 1000 * $level,
                'gold' => ($i % 3 === 0) ? 20 : 10,
                'act' => 10,
                'get' => $isClaimed,
                'open' => $isOpen,
            ];
        }

        return [
            'status' => 1,
            'signed_today' => $signedToday,
            'total_days' => (int)$signData['total'],
            'list' => $list,
            'date' => date('m.d'),
            'message' => 'ok',
        ];
    }

    /**
     * 执行每日签到: c=dailysign&m=sign
     */
    public static function sign(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $now = time();

        $signKey = 'dailysign:' . $uid;
        $signData = Redis::getJson($signKey) ?: ['total' => 0, 'last_date' => ''];
        $todayStr = date('Ymd', $now);

        if ($signData['last_date'] === $todayStr) {
            return [
                'status' => -1,
                'message' => '今天已签到，请明天再来',
            ];
        }

        $newTotal = (int)$signData['total'] + 1;
        $level = max(1, (int)($member['level'] ?? 1));

        $rewardSilver = $newTotal * 1000 * $level;
        $rewardGold = ($newTotal % 3 === 0) ? 20 : 10;
        $rewardAct = 10;

        $newSilver = (int)($member['silver'] ?? 0) + $rewardSilver;
        $newGold = (int)($member['gold'] ?? 0) + $rewardGold;
        $newAct = min(200, (int)($member['act'] ?? 0) + $rewardAct);

        DB::table('member')->where('uid', $uid)->update([
            'silver' => $newSilver,
            'gold' => $newGold,
            'act' => $newAct,
        ]);

        Redis::setJson($signKey, [
            'total' => $newTotal,
            'last_date' => $todayStr,
        ], 86400 * 30);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'day' => $newTotal,
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'act' => $rewardAct,
            'all_silver' => $newSilver,
            'all_gold' => $newGold,
            'message' => "今日签到成功！获得银两 {$rewardSilver}、元宝 {$rewardGold}、军令 {$rewardAct}！",
        ];
    }
}
