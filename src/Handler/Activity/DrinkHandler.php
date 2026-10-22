<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class DrinkHandler
{
    public const array WINES = [
        1 => ['id' => 1, 'name' => '陈年竹叶青', 'cost_silver' => 3000, 'cost_gold' => 0, 'silver' => 8000, 'soul' => 20],
        2 => ['id' => 2, 'name' => '百年状元红', 'cost_silver' => 10000, 'cost_gold' => 0, 'silver' => 25000, 'soul' => 60],
        3 => ['id' => 3, 'name' => '极品千年杜康', 'cost_silver' => 0, 'cost_gold' => 30, 'silver' => 60000, 'soul' => 150],
    ];

    /**
     * 煮酒台主界面: c=drink&m=index
     */
    public static function index(Context $ctx): array
    {
        return [
            'status' => 1,
            'table_title' => '青梅煮酒论英雄',
            'wines' => array_values(self::WINES),
            'message' => 'ok',
        ];
    }

    /**
     * 举杯对饮: c=drink&m=go_drink
     */
    public static function go_drink(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $wineId = (int)($ctx->input('id', $ctx->input('type', 1)));

        if (!isset(self::WINES[$wineId])) {
            return ['status' => 0, 'message' => '美酒品级不存在'];
        }

        $cfg = self::WINES[$wineId];
        $costSilver = (int)$cfg['cost_silver'];
        $costGold = (int)$cfg['cost_gold'];

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver, gold: $costGold)) {
            return [
                'status' => -1,
                'message' => ($costGold > 0) ? "元宝不足，畅饮此酒需要 {$costGold} 元宝" : "银两不足，畅饮此酒需要 {$costSilver} 银两",
            ];
        }

        $rewardSilver = (int)$cfg['silver'];
        $rewardSoul = (int)$cfg['soul'];

        MemberHandler::addCurrency($uid, silver: $rewardSilver, soul: $rewardSoul);

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($wineId === 3) {
            $pName = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "天下英雄唯使君与操耳！主公【{$pName}】青梅煮酒畅饮【千年杜康】，天降将魂祥瑞！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'wine_name' => $cfg['name'],
            'silver' => $rewardSilver,
            'soul' => $rewardSoul,
            'message' => "对酒当歌，人生几何！畅饮【{$cfg['name']}】，结交英雄，获赠银两 {$rewardSilver}、将魂 {$rewardSoul}！",
        ];
    }
}
