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

final class PassGeneralHandler
{
    /**
     * 六大守关武将配置表
     *
     * @var array<int, array<string, mixed>>
     */
    public const array STAGES = [
        1 => ['id' => 1, 'name' => '华雄', 'hp' => 1500, 'attack' => 260, 'defense' => 100, 'speed' => 120, 'silver' => 800, 'gold' => 0, 'box' => '青铜宝箱'],
        2 => ['id' => 2, 'name' => '颜良', 'hp' => 2000, 'attack' => 300, 'defense' => 120, 'speed' => 125, 'silver' => 1200, 'gold' => 0, 'box' => '青铜宝箱'],
        3 => ['id' => 3, 'name' => '文丑', 'hp' => 2500, 'attack' => 350, 'defense' => 140, 'speed' => 130, 'silver' => 1600, 'gold' => 0, 'box' => '白银宝箱'],
        4 => ['id' => 4, 'name' => '庞德', 'hp' => 3000, 'attack' => 400, 'defense' => 160, 'speed' => 135, 'silver' => 2000, 'gold' => 10, 'box' => '白银宝箱'],
        5 => ['id' => 5, 'name' => '典韦', 'hp' => 3600, 'attack' => 460, 'defense' => 180, 'speed' => 140, 'silver' => 2500, 'gold' => 20, 'box' => '黄金宝箱'],
        6 => ['id' => 6, 'name' => '吕布', 'hp' => 5000, 'attack' => 550, 'defense' => 220, 'speed' => 160, 'silver' => 5000, 'gold' => 50, 'box' => '神品无双宝箱'],
    ];

    /**
     * 获取玩家过关斩将当前进度
     */
    public static function getProgress(int $uid): int
    {
        $cached = Redis::get('pass_general_stage:' . $uid);
        return ($cached !== null && is_numeric($cached)) ? max(1, (int)$cached) : 1;
    }

    /**
     * 过关斩将主界面: c=pass_general&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $currentPass = self::getProgress($uid);

        $list = [];
        foreach (self::STAGES as $id => $st) {
            $list[$id] = array_merge($st, [
                'status' => match (true) {
                    $id < $currentPass  => 2, // 已通关斩杀
                    $id === $currentPass => 1, // 当前正可挑战
                    default              => 0, // 未解锁
                },
            ]);
        }

        return [
            'status' => 1,
            'current_pass' => $currentPass,
            'total_stages' => count(self::STAGES),
            'list' => $list,
            'message' => 'ok',
        ];
    }

    /**
     * 发起过关斩将挑战: c=pass_general&m=action
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $targetPass = (int)($ctx->input('pass_id', $ctx->input('id', 0)));

        $currentPass = self::getProgress($uid);
        if ($targetPass <= 0) {
            $targetPass = $currentPass;
        }

        if ($targetPass !== $currentPass) {
            return [
                'status' => 0,
                'message' => ($targetPass < $currentPass) ? '该关卡已被通关斩杀' : '前置关卡尚未突破，无法越级挑战',
            ];
        }

        if (!isset(self::STAGES[$targetPass])) {
            return [
                'status' => 0,
                'message' => '关卡不存在或已通关全部关卡',
            ];
        }

        $bossConfig = self::STAGES[$targetPass];

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

        // 2. 构建守关大将
        $rightHeroes = [
            new Hero(
                8000 + $bossConfig['id'],
                $bossConfig['name'],
                Position::FrontMiddle,
                (int)$bossConfig['hp'],
                (int)$bossConfig['hp'],
                (int)$bossConfig['attack'],
                (int)$bossConfig['defense'],
                (int)$bossConfig['speed']
            ),
        ];

        // 3. 确定性纯内存九宫格推演
        $engine = new BattleEngine($leftHeroes, $rightHeroes);
        $simResult = $engine->simulate();
        $isWin = $simResult['win'];

        if (!$isWin) {
            return [
                'status' => 1,
                'win' => -1,
                'pass_id' => $targetPass,
                'rounds' => $simResult['rounds'],
                'message' => "挑战守将【{$bossConfig['name']}】惜败，请调整武将站位再来挑战！",
            ];
        }

        // 4. 战胜：推进关卡进度与发奖
        $nextPass = $targetPass + 1;
        Redis::set('pass_general_stage:' . $uid, (string)$nextPass, 86400 * 7);

        $rewardSilver = (int)$bossConfig['silver'];
        $rewardGold = (int)$bossConfig['gold'];

        if ($member !== null) {
            DB::table('member')->where('uid', $uid)->update([
                'silver' => (int)($member['silver'] ?? 0) + $rewardSilver,
                'gold' => (int)($member['gold'] ?? 0) + $rewardGold,
            ]);
            Redis::del('player:' . $uid);
        }

        // 斩杀第 6 关吕布通关全服大喇叭
        if ($targetPass === 6) {
            $nickname = $member['nickname'] ?? ('主公' . $uid);
            Broadcaster::broadcast('marquee', [
                'content' => "天下无双！主公【{$nickname}】势如破竹，于过关斩将中一举斩杀神将【吕布】，通关全境！",
                'type' => 'system_broadcast',
            ]);
        }

        return [
            'status' => 1,
            'win' => 1,
            'cleared_pass' => $targetPass,
            'boss_name' => $bossConfig['name'],
            'next_pass' => $nextPass,
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'box' => $bossConfig['box'],
            'rounds' => $simResult['rounds'],
            'message' => "成功斩杀守关大将【{$bossConfig['name']}】！开启【{$bossConfig['box']}】，获得银两 {$rewardSilver}" . ($rewardGold > 0 ? "、元宝 {$rewardGold}" : '') . "！",
        ];
    }

    /**
     * 重置过关斩将: c=pass_general&m=reset
     */
    public static function reset(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        Redis::set('pass_general_stage:' . $uid, '1', 86400 * 7);

        return [
            'status' => 1,
            'current_pass' => 1,
            'message' => '过关斩将已重置回第一关',
        ];
    }
}
