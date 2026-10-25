<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

/**
 * 青梅煮酒论英雄、痛饮杜康佳酿与军令体力犒赏处理器 (DDD Activity Domain)
 */
final class DrinkHandler
{
    /**
     * 3 大传世名酿配置字典
     * type: 1 银两, 2 元宝
     */
    public const array WINES = [
        1 => ['id' => 1, 'name' => '陈年竹叶青', 'type' => 1, 'cost_silver' => 3000, 'cost_gold' => 0, 'act' => 20, 'silver' => 8000, 'soul' => 20],
        2 => ['id' => 2, 'name' => '百年状元红', 'type' => 2, 'cost_silver' => 0, 'cost_gold' => 20, 'act' => 50, 'silver' => 25000, 'soul' => 60],
        3 => ['id' => 3, 'name' => '极品千年杜康', 'type' => 2, 'cost_silver' => 0, 'cost_gold' => 50, 'act' => 120, 'silver' => 60000, 'soul' => 150],
    ];

    /**
     * 煮酒台主界面: c=drink&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $level = max(1, (int)($member['level'] ?? 1));

        $todayStr = date('Ymd');
        $lastDrink = (string)(Redis::get("user_drink_date:{$uid}") ?? '');
        $status = ($lastDrink === $todayStr) ? 2 : 1; // 1: 今日可饮, 2: 今日已饮

        // 格式化 3 种酒品 (1 号酒消耗银两随等级递增)
        $list = [];
        foreach (self::WINES as $id => $wine) {
            $price = ($wine['type'] === 1) ? $level * 100 : (int)$wine['cost_gold'];
            $list[] = [
                'id' => $id,
                'name' => $wine['name'],
                'type' => (int)$wine['type'],
                'price' => $price,
                'act' => (int)$wine['act'],
            ];
        }

        $logs = Redis::getJson('global_drink_logs') ?: [
            ['nickname' => '常山赵子龙', 'type' => 3, 'act' => 120],
            ['nickname' => '关云长', 'type' => 2, 'act' => 50],
            ['nickname' => '燕人张翼德', 'type' => 1, 'act' => 20],
        ];

        return [
            'status' => $status,
            'list' => $list,
            'log' => array_values($logs),
            // 向下兼容保留原字段
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
        $wineId = (int)($ctx->input('type', $ctx->input('id', 1)));

        if (!isset(self::WINES[$wineId])) {
            return ['status' => -3, 'message' => '美酒品级不存在'];
        }

        $todayStr = date('Ymd');
        $lastDrink = (string)(Redis::get("user_drink_date:{$uid}") ?? '');
        if ($lastDrink === $todayStr) {
            return ['status' => -2, 'message' => '今日对酒次数已用尽，请明日再来'];
        }

        $cfg = self::WINES[$wineId];
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $level = max(1, (int)($member['level'] ?? 1));

        $costSilver = ($cfg['type'] === 1) ? $level * 100 : 0;
        $costGold = ($cfg['type'] === 2) ? (int)$cfg['cost_gold'] : 0;

        if ($costGold > 0) {
            if ((int)($member['gold'] ?? 0) < $costGold) {
                return ['status' => 3, 'message' => '元宝不足'];
            }
            MemberHandler::deductCurrency($uid, gold: $costGold);
        }

        if ($costSilver > 0) {
            if ((int)($member['silver'] ?? 0) < $costSilver) {
                return ['status' => 2, 'message' => '银两不足'];
            }
            MemberHandler::deductCurrency($uid, silver: $costSilver);
        }

        // 发放军令体力
        $rewardAct = (int)$cfg['act'];
        MemberHandler::addCurrency($uid, act: $rewardAct);

        // 记录今日对酒完成
        Redis::set("user_drink_date:{$uid}", $todayStr, 86400 * 2);
        Redis::del('player:' . $uid);

        $freshMember = DB::table('member')->where('uid', $uid)->first();
        $nickname = (string)($freshMember['nickname'] ?? ('主公' . $uid));

        // 记录全服对酒滚屏日志
        $logs = Redis::getJson('global_drink_logs') ?: [];
        array_unshift($logs, [
            'nickname' => $nickname,
            'type' => $wineId,
            'act' => $rewardAct,
        ]);
        $logs = array_slice($logs, 0, 10);
        Redis::setJson('global_drink_logs', $logs, 86400 * 7);

        if ($wineId === 3) {
            Broadcaster::broadcast('marquee', [
                'content' => "对酒当歌！主公【{$nickname}】青梅煮酒痛饮【千年杜康】，天赐豪气，斩获 {$rewardAct} 点军令大增益！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'reward' => [
                'silver' => (int)($freshMember['silver'] ?? 0),
                'gold' => (int)($freshMember['gold'] ?? 0),
                'act' => (int)($freshMember['act'] ?? 0),
            ],
            'log' => [
                'nickname' => $nickname,
                'type' => $wineId,
                'act' => $rewardAct,
            ],
            // 向下兼容保留原字段
            'wine_name' => $cfg['name'],
            'silver' => (int)$cfg['silver'],
            'soul' => (int)$cfg['soul'],
            'message' => "对酒当歌，人生几何！痛饮【{$cfg['name']}】，结交天下豪杰，获得军令 {$rewardAct} 点！",
        ];
    }
}
