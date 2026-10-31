<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class BanquetHandler
{
    /**
     * 宴会规格
     */
    public const array BANQUET_TYPES = [
        1 => ['id' => 1, 'name' => '主公家宴', 'cost_silver' => 10000, 'cost_gold' => 0, 'act' => 30, 'rep' => 100],
        2 => ['id' => 2, 'name' => '诸侯豪宴', 'cost_silver' => 0, 'cost_gold' => 50, 'act' => 60, 'rep' => 300],
        3 => ['id' => 3, 'name' => '万寿琼浆盛宴', 'cost_silver' => 0, 'cost_gold' => 100, 'act' => 120, 'rep' => 800],
    ];

    /**
     * 夜宴大厅: c=banquet&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $teams = Redis::getJson('banquet_teams') ?: [];

        return [
            'status' => 1,
            'types' => array_values(self::BANQUET_TYPES),
            'tables' => array_values($teams),
            'message' => 'ok',
        ];
    }

    /**
     * 举办夜宴开席: c=banquet&m=create_team 或 m=action
     */
    public static function create_team(Context $ctx): array
    {
        return self::action($ctx);
    }

    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $type = (int)($ctx->input('id', $ctx->input('type', 1)));

        if (!isset(self::BANQUET_TYPES[$type])) {
            return ['status' => 0, 'message' => '宴席规格不存在'];
        }

        $cfg = self::BANQUET_TYPES[$type];
        $costSilver = (int)$cfg['cost_silver'];
        $costGold = (int)$cfg['cost_gold'];

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver, gold: $costGold)) {
            return [
                'status' => -1,
                'message' => ($costGold > 0) ? "元宝不足，开办此宴需要 {$costGold} 元宝" : "银两不足，开办此宴需要 {$costSilver} 银两",
            ];
        }

        // 发放军令与声望
        MemberHandler::addCurrency($uid, act: (int)$cfg['act'], reputation: (int)$cfg['rep']);


        return [
            'status' => 1,
            'name' => $cfg['name'],
            'add_act' => $cfg['act'],
            'add_reputation' => $cfg['rep'],
            'message' => "【{$cfg['name']}】开席大吉！宾主尽欢，获赠军令 {$cfg['act']} 点、声望 {$cfg['rep']} 点！",
        ];
    }
}
