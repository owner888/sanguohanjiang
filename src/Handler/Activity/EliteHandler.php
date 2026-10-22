<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\GeneralHandler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Hero;
use Hanjiang\Battle\Position;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

final class EliteHandler
{
    public const array ELITE_MAPS = [
        1 => ['id' => 1, 'name' => '精英·大兴山断桥', 'hp' => 2200, 'attack' => 320, 'defense' => 120, 'silver' => 10000, 'drop' => '百炼强化玄铁 x2'],
        2 => ['id' => 2, 'name' => '精英·宛城火海营', 'hp' => 3200, 'attack' => 380, 'defense' => 150, 'silver' => 18000, 'drop' => '赤炎红宝石 x1'],
        3 => ['id' => 3, 'name' => '精英·虎牢三英关', 'hp' => 4500, 'attack' => 450, 'defense' => 180, 'silver' => 30000, 'drop' => '极品龙纹兵符 x1'],
    ];

    /**
     * 精英副本主界面: c=elite&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $times = (int)(Redis::get('elite_times:' . $uid) ?? 5);

        return [
            'status' => 1,
            'left_times' => $times,
            'maps' => array_values(self::ELITE_MAPS),
            'message' => 'ok',
        ];
    }

    /**
     * 发起精英战役: c=elite&m=action
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $mapId = (int)($ctx->input('id', $ctx->input('map_id', 1)));
        $times = (int)(Redis::get('elite_times:' . $uid) ?? 5);

        if ($times <= 0) {
            return ['status' => -1, 'message' => '今日精英副本挑战次数已用尽'];
        }

        if (!isset(self::ELITE_MAPS[$mapId])) {
            return ['status' => 0, 'message' => '精英关卡不存在'];
        }

        $cfg = self::ELITE_MAPS[$mapId];

        // 1. 构建我方出战英雄
        $generals = DB::table('general')->where('uid', $uid)->where('pos', '>', 0)->whereNull('deleted_at')->get();
        if (empty($generals)) {
            $generals = DB::table('general')->where('uid', $uid)->whereNull('deleted_at')->get();
        }

        $matrix = DB::table('matrix')->where('uid', $uid)->whereNull('deleted_at')->first();
        $matrixMid = (int)($matrix['mid'] ?? 1);
        $matrixLevel = max(1, (int)($matrix['level'] ?? 1));
        $matrixAddition = 1.0 + 0.05 + ($matrixLevel - 1) * 0.01;

        $leftHeroes = [];
        foreach ($generals as $g) {
            $gid = (int)$g['id'];
            $posInt = (int)$g['pos'] > 0 ? (int)$g['pos'] : 2;
            $pos = Position::tryFrom($posInt) ?? Position::FrontMiddle;
            $equipped = DB::table('equipment')->where('uid', $uid)->where('generalid', $gid)->whereNull('deleted_at')->get();
            $stats = GeneralHandler::calculateGeneralStats($g, $equipped);
            $finalAtk = ($matrixMid === 1) ? (int)round($stats['attack'] * $matrixAddition) : $stats['attack'];
            $finalDef = ($matrixMid === 3) ? (int)round($stats['defense'] * $matrixAddition) : $stats['defense'];
            $leftHeroes[] = new Hero($gid, $g['name'], $pos, $stats['hp'], $stats['hp'], $finalAtk, $finalDef, $stats['speed']);
        }
        if (empty($leftHeroes)) {
            $leftHeroes[] = new Hero(1, '天命主公', Position::FrontMiddle, 2000, 2000, 380, 150, 150);
        }

        // 2. 精英据点守军
        $rightHeroes = [
            new Hero(2000 + $cfg['id'], $cfg['name'], Position::FrontMiddle, $cfg['hp'], $cfg['hp'], $cfg['attack'], $cfg['defense'], 125),
        ];

        // 3. 推演
        $engine = new BattleEngine($leftHeroes, $rightHeroes);
        $simResult = $engine->simulate();
        $isWin = $simResult['win'];

        Redis::set('elite_times:' . $uid, (string)($times - 1), 86400);

        if (!$isWin) {
            return [
                'status' => 1,
                'win' => -1,
                'rounds' => $simResult['rounds'],
                'message' => "精英战役惜败于【{$cfg['name']}】，请强化装备再来挑战！",
            ];
        }

        // 发放银两与战利品
        MemberHandler::addCurrency($uid, silver: (int)$cfg['silver']);

        return [
            'status' => 1,
            'win' => 1,
            'map_name' => $cfg['name'],
            'silver' => $cfg['silver'],
            'drop' => $cfg['drop'],
            'rounds' => $simResult['rounds'],
            'message' => "精英战役大捷！击破【{$cfg['name']}】，斩获银两 {$cfg['silver']} 与珍稀战利品【{$cfg['drop']}】！",
        ];
    }
}
