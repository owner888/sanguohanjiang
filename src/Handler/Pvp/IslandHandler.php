<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;
use Hanjiang\Handler\General\GeneralHandler;
use Hanjiang\Handler\City\MemberHandler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Hero;
use Hanjiang\Battle\Position;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class IslandHandler
{
    public const array ISLANDS = [
        1 => ['id' => 1, 'name' => '蓬莱仙境银矿岛', 'boss' => '蓬莱护岛神蛟', 'hp' => 2500, 'attack' => 350, 'defense' => 130, 'silver' => 20000, 'gold' => 10],
        2 => ['id' => 2, 'name' => '瀛洲极品赤金岛', 'boss' => '瀛洲双头火雕', 'hp' => 3800, 'attack' => 420, 'defense' => 160, 'silver' => 35000, 'gold' => 20],
        3 => ['id' => 3, 'name' => '方丈远古秘境岛', 'boss' => '方丈通天神龟', 'hp' => 5200, 'attack' => 500, 'defense' => 200, 'silver' => 60000, 'gold' => 50],
    ];

    /**
     * 金银岛主界面: c=island&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $times = (int)(Redis::get('island_times:' . $uid) ?? 5);

        return [
            'status' => 1,
            'left_times' => $times,
            'islands' => array_values(self::ISLANDS),
            'message' => 'ok',
        ];
    }

    /**
     * 登陆挑战金银岛: c=island&m=pk
     */
    public static function pk(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $islandId = (int)($ctx->input('id', $ctx->input('island_id', 1)));
        $times = (int)(Redis::get('island_times:' . $uid) ?? 5);

        if ($times <= 0) {
            return ['status' => -1, 'message' => '今日金银岛探索次数已用尽'];
        }

        if (!isset(self::ISLANDS[$islandId])) {
            return ['status' => 0, 'message' => '海岛不存在'];
        }

        $cfg = self::ISLANDS[$islandId];
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        // 1. 我方出战队伍
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

        // 2. 护岛神兽
        $rightHeroes = [
            new Hero(6500 + $cfg['id'], $cfg['boss'], Position::FrontMiddle, $cfg['hp'], $cfg['hp'], $cfg['attack'], $cfg['defense'], 125),
        ];

        // 3. 推演
        $engine = new BattleEngine($leftHeroes, $rightHeroes);
        $simResult = $engine->simulate();
        $isWin = $simResult['win'];

        Redis::set('island_times:' . $uid, (string)($times - 1), 86400);

        if (!$isWin) {
            return [
                'status' => 1,
                'win' => -1,
                'rounds' => $simResult['rounds'],
                'message' => "探索海岛遭遇重创，未能击退【{$cfg['boss']}】！",
            ];
        }

        // 胜利发奖
        $rewardSilver = (int)$cfg['silver'];
        $rewardGold = (int)$cfg['gold'];
        MemberHandler::addCurrency($uid, silver: $rewardSilver, gold: $rewardGold);

        if ($islandId === 3) {
            $pName = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "海内伏波！主公【{$pName}】扬帆远航，成功征服【{$cfg['name']}】，夺取海外龙脉宝藏！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'win' => 1,
            'island_name' => $cfg['name'],
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'rounds' => $simResult['rounds'],
            'message' => "海外探险大获全胜！征服【{$cfg['name']}】，斩获银两 {$rewardSilver}、元宝 {$rewardGold}！",
        ];
    }
}
