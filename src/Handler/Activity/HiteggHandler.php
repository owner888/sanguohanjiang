<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class HiteggHandler
{
    public const array EGGS = [
        1 => ['id' => 1, 'name' => '吉祥如意金蛋', 'cost_silver' => 5000, 'cost_gold' => 0, 'silver' => 12000, 'gold' => 10],
        2 => ['id' => 2, 'name' => '富贵荣华银蛋', 'cost_silver' => 15000, 'cost_gold' => 0, 'silver' => 35000, 'gold' => 20],
        3 => ['id' => 3, 'name' => '至尊天下无双彩蛋', 'cost_silver' => 0, 'cost_gold' => 50, 'silver' => 88888, 'gold' => 100],
    ];

    /**
     * 砸金蛋主界面: c=hitegg&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $times = (int)(Redis::get('hitegg_times:' . $uid) ?? 5);

        return [
            'status' => 1,
            'left_hammers' => $times,
            'eggs' => array_values(self::EGGS),
            'message' => 'ok',
        ];
    }

    /**
     * 挥锤砸金蛋: c=hitegg&m=hit_egg
     */
    public static function hit_egg(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $eggId = (int)($ctx->input('id', $ctx->input('egg_id', 1)));

        if (!isset(self::EGGS[$eggId])) {
            return ['status' => 0, 'message' => '金蛋不存在'];
        }

        $cfg = self::EGGS[$eggId];
        $costSilver = (int)$cfg['cost_silver'];
        $costGold = (int)$cfg['cost_gold'];

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver, gold: $costGold)) {
            return [
                'status' => -1,
                'message' => ($costGold > 0) ? "元宝不足，砸碎彩蛋需要 {$costGold} 元宝" : "银两不足，砸碎金蛋需要 {$costSilver} 银两",
            ];
        }

        $rewardSilver = (int)$cfg['silver'];
        $rewardGold = (int)$cfg['gold'];

        MemberHandler::addCurrency($uid, silver: $rewardSilver, gold: $rewardGold);

        if ($eggId === 3 || $rewardGold >= 50) {
            $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
            $pName = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "金花四溅！主公【{$pName}】一锤砸开【{$cfg['name']}】，金光耀目，独揽 {$rewardGold} 元宝大暴击！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'egg_name' => $cfg['name'],
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'message' => "金蛋碎裂，祥瑞降临！斩获银两 {$rewardSilver}" . ($rewardGold > 0 ? "、元宝 {$rewardGold}" : '') . "！",
        ];
    }
}
