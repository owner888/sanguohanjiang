<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Campaign;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\GeneralHandler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Hero;
use Hanjiang\Battle\Position;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class TowerHandler
{
    /**
     * 获取玩家当前试炼塔进度
     *
     * @return array{floor: int, sweep_times: int}
     */
    public static function getTowerProgress(int $uid): array
    {
        $cached = Redis::getJson('tower_progress:' . $uid);
        if (is_array($cached)) {
            return $cached;
        }

        $progress = [
            'floor' => 1,
            'max_floor' => 1,
            'sweep_times' => 3,
        ];
        Redis::setJson('tower_progress:' . $uid, $progress, 86400 * 30);
        return $progress;
    }

    /**
     * 试炼塔主界面: c=tower&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $progress = self::getTowerProgress($uid);
        $floor = (int)($progress['floor'] ?? 1);

        $bossName = match (true) {
            $floor % 10 === 0 => '上古青龙神兽',
            $floor % 5 === 0  => '烈焰麒麟幻影',
            default           => '试炼守关神兽·第' . $floor . '层',
        };

        return [
            'status' => 1,
            'floor' => $floor,
            'max_floor' => (int)($progress['max_floor'] ?? $floor),
            'boss_name' => $bossName,
            'sweep_times' => (int)($progress['sweep_times'] ?? 3),
            'rewards_preview' => [
                'silver' => $floor * 300,
                'soul' => $floor * 10,
            ],
            'message' => 'ok',
        ];
    }

    /**
     * 发起爬塔挑战: c=tower&m=action
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $progress = self::getTowerProgress($uid);
        $floor = max(1, (int)($progress['floor'] ?? 1));

        // 1. 构建我方出战队伍 (多武将 + 装备 + 阵法加成)
        $matrix = DB::table('matrix')->where('uid', $uid)->first();
        $matrixMid = (int)($matrix['mid'] ?? 1);
        $matrixLevel = max(1, (int)($matrix['level'] ?? 1));
        $matrixAddition = 1.0 + 0.05 + ($matrixLevel - 1) * 0.01;

        $generals = DB::table('general')->where('uid', $uid)->where('pos', '>', 0)->get();
        if (empty($generals)) {
            $generals = DB::table('general')->where('uid', $uid)->get();
        }

        $leftHeroes = [];
        foreach ($generals as $g) {
            $gid = (int)$g['id'];
            $posInt = (int)$g['pos'] > 0 ? (int)$g['pos'] : 2;
            $pos = Position::tryFrom($posInt) ?? Position::FrontMiddle;

            $equipped = DB::table('equipment')->where('uid', $uid)->where('generalid', $gid)->get();
            $stats = GeneralHandler::calculateGeneralStats($g, $equipped);

            $finalAtk = ($matrixMid === 1) ? (int)round($stats['attack'] * $matrixAddition) : $stats['attack'];
            $finalDef = ($matrixMid === 3) ? (int)round($stats['defense'] * $matrixAddition) : $stats['defense'];

            $heroName = $g['name'] ?: ($member['nickname'] ?? '天命主公');
            $leftHeroes[] = new Hero($gid, $heroName, $pos, $stats['hp'], $stats['hp'], $finalAtk, $finalDef, $stats['speed']);
        }

        if (empty($leftHeroes)) {
            $leftHeroes[] = new Hero(1, '天命主公', Position::FrontMiddle, 2000, 2000, 380, 150, 150);
        }

        // 2. 构建守关敌将
        $bossHp = 1000 + $floor * 250;
        $bossAtk = 200 + $floor * 35;
        $bossDef = 80 + $floor * 15;
        $bossName = ($floor % 5 === 0) ? '镇塔神将·第' . $floor . '重' : '守塔凶兽·第' . $floor . '层';

        $rightHeroes = [
            new Hero(9000 + $floor, $bossName, Position::FrontMiddle, $bossHp, $bossHp, $bossAtk, $bossDef, 120),
        ];

        // 3. 确定性纯内存九宫格推演
        $engine = new BattleEngine($leftHeroes, $rightHeroes);
        $simResult = $engine->simulate();
        $isWin = $simResult['win'];

        if (!$isWin) {
            return [
                'status' => 1,
                'win' => -1,
                'floor' => $floor,
                'rounds' => $simResult['rounds'],
                'message' => '挑战失败，请强化装备、调整阵型后再来挑战！',
            ];
        }

        // 4. 战胜：层数推进与奖励结算
        $newFloor = $floor + 1;
        $maxFloor = max((int)($progress['max_floor'] ?? $floor), $newFloor);
        $progress['floor'] = $newFloor;
        $progress['max_floor'] = $maxFloor;
        Redis::setJson('tower_progress:' . $uid, $progress, 86400 * 30);

        $rewardSilver = $floor * 300;
        $rewardSoul = $floor * 10;

        if ($member !== null) {
            DB::table('member')->where('uid', $uid)->update([
                'silver' => (int)($member['silver'] ?? 0) + $rewardSilver,
            ]);
            Redis::del('player:' . $uid);
        }

        // 整 5 层全服跑马灯广播
        if ($floor % 5 === 0) {
            $nickname = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "威震乾坤！恭喜主公【{$nickname}】成功突破试炼塔第【{$floor}】层！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'win' => 1,
            'cleared_floor' => $floor,
            'next_floor' => $newFloor,
            'silver' => $rewardSilver,
            'soul' => $rewardSoul,
            'rounds' => $simResult['rounds'],
            'message' => "试炼塔第 {$floor} 层大获全胜！获得银两 {$rewardSilver}，将魂 {$rewardSoul}！",
        ];
    }

    /**
     * 扫荡试炼塔: c=tower&m=sweep
     */
    public static function sweep(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $progress = self::getTowerProgress($uid);
        $sweepTimes = (int)($progress['sweep_times'] ?? 3);

        if ($sweepTimes <= 0) {
            return [
                'status' => 0,
                'message' => '今日扫荡次数已用尽',
            ];
        }

        $maxFloor = (int)($progress['max_floor'] ?? 1);
        if ($maxFloor <= 1) {
            return [
                'status' => -1,
                'message' => '当前未通关任何层数，无法扫荡',
            ];
        }

        $clearedLayers = $maxFloor - 1;
        $rewardSilver = $clearedLayers * 200;

        $progress['sweep_times'] = $sweepTimes - 1;
        Redis::setJson('tower_progress:' . $uid, $progress, 86400 * 30);

        $member = DB::table('member')->where('uid', $uid)->first();
        if ($member !== null) {
            DB::table('member')->where('uid', $uid)->update([
                'silver' => (int)($member['silver'] ?? 0) + $rewardSilver,
            ]);
            Redis::del('player:' . $uid);
        }

        return [
            'status' => 1,
            'cleared_layers' => $clearedLayers,
            'silver' => $rewardSilver,
            'left_sweep_times' => $progress['sweep_times'],
            'message' => "扫荡完成！瞬间扫荡 {$clearedLayers} 层，获得银两 {$rewardSilver}！",
        ];
    }
}
