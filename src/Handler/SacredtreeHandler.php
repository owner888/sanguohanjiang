<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class SacredtreeHandler
{
    /**
     * 摇钱神树主界面: c=sacredtree&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $treeState = Redis::getJson('user_sacred_tree:' . $uid) ?: [
            'level' => 1,
            'watered_times' => 0,
        ];

        $level = max(1, (int)$treeState['level']);
        $watered = (int)$treeState['watered_times'];

        return [
            'status' => 1,
            'tree_name' => '聚宝通天摇钱神树',
            'level' => $level,
            'watered_times' => $watered,
            'need_water' => $level * 5,
            'current_yield' => $level * 10000 . '银两 + ' . ($level * 10) . '元宝',
            'cost_silver' => 3000,
            'message' => 'ok',
        ];
    }

    /**
     * 摇钱神树浇水: c=sacredtree&m=watering
     */
    public static function watering(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $costSilver = 3000;

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver)) {
            return ['status' => -1, 'message' => "银两不足，给神树浇水需要 {$costSilver} 银两"];
        }

        $treeState = Redis::getJson('user_sacred_tree:' . $uid) ?: [
            'level' => 1,
            'watered_times' => 0,
        ];

        $curLevel = max(1, (int)$treeState['level']);
        $newWatered = (int)$treeState['watered_times'] + 1;
        $needWater = $curLevel * 5;

        $leveledUp = false;
        if ($newWatered >= $needWater) {
            $newWatered = 0;
            $curLevel++;
            $leveledUp = true;
        }

        $treeState['level'] = $curLevel;
        $treeState['watered_times'] = $newWatered;
        Redis::setJson('user_sacred_tree:' . $uid, $treeState, 86400 * 30);

        // 摇下果实掉落银两和元宝
        $rewardSilver = $curLevel * 3000;
        $rewardGold = $leveledUp ? 20 : 5;

        MemberHandler::addCurrency($uid, silver: $rewardSilver, gold: $rewardGold);

        if ($leveledUp) {
            $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
            $pName = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "瑞气千条！主公【{$pName}】精心灌溉聚宝神树，神树突破至第 {$curLevel} 阶，金叶漫天！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'level' => $curLevel,
            'watered_times' => $newWatered,
            'leveled_up' => $leveledUp ? 1 : 0,
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'message' => "浇水成功！神树抖落金叶玉果，斩获银两 {$rewardSilver}、元宝 {$rewardGold}！" . ($leveledUp ? " 神树晋升至第 {$curLevel} 阶！" : ''),
        ];
    }
}
