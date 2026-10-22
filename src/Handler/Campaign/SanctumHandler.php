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

final class SanctumHandler
{
    public const array REALMS = [
        1 => ['id' => 1, 'name' => '青龙东方圣域', 'boss' => '青龙守护神尊', 'hp' => 2000, 'attack' => 280, 'defense' => 120, 'reward_silver' => 15000, 'reward_gold' => 30],
        2 => ['id' => 2, 'name' => '白虎西方圣域', 'boss' => '白虎破军狂尊', 'hp' => 3000, 'attack' => 350, 'defense' => 150, 'reward_silver' => 25000, 'reward_gold' => 50],
        3 => ['id' => 3, 'name' => '朱雀南方圣域', 'boss' => '朱雀焚天炎尊', 'hp' => 4500, 'attack' => 420, 'defense' => 180, 'reward_silver' => 40000, 'reward_gold' => 70],
        4 => ['id' => 4, 'name' => '玄武北方圣域', 'boss' => '玄武极寒冥尊', 'hp' => 6000, 'attack' => 500, 'defense' => 220, 'reward_silver' => 60000, 'reward_gold' => 100],
    ];

    /**
     * 四象圣域主界面: c=sanctum&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $cleared = (int)(Redis::get('sanctum_cleared:' . $uid) ?: 0);

        $list = [];
        foreach (self::REALMS as $id => $r) {
            $list[$id] = array_merge($r, [
                'status' => ($id <= $cleared) ? 2 : (($id === $cleared + 1) ? 1 : 0),
            ]);
        }

        return [
            'status' => 1,
            'cleared_realms' => $cleared,
            'list' => $list,
            'message' => 'ok',
        ];
    }

    /**
     * 发起圣域神尊决战: c=sanctum&m=action
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $realmId = (int)($ctx->input('id', $ctx->input('realm_id', 1)));

        if (!isset(self::REALMS[$realmId])) {
            return ['status' => 0, 'message' => '圣域不存在'];
        }

        $cfg = self::REALMS[$realmId];
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

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

            $heroName = $g['name'] ?: ($member['nickname'] ?? '天命主公');
            $leftHeroes[] = new Hero($gid, $heroName, $pos, $stats['hp'], $stats['hp'], $finalAtk, $finalDef, $stats['speed']);
        }
        if (empty($leftHeroes)) {
            $leftHeroes[] = new Hero(1, '天命主公', Position::FrontMiddle, 2000, 2000, 380, 150, 150);
        }

        // 2. 圣域神尊
        $rightHeroes = [
            new Hero(5000 + $cfg['id'], $cfg['boss'], Position::FrontMiddle, (int)$cfg['hp'], (int)$cfg['hp'], (int)$cfg['attack'], (int)$cfg['defense'], 140),
        ];

        // 3. 推演
        $engine = new BattleEngine($leftHeroes, $rightHeroes);
        $simResult = $engine->simulate();
        $isWin = $simResult['win'];

        if (!$isWin) {
            return [
                'status' => 1,
                'win' => -1,
                'realm_id' => $realmId,
                'rounds' => $simResult['rounds'],
                'message' => "挑战【{$cfg['boss']}】失败，圣尊神威浩瀚，请强化阵型再战！",
            ];
        }

        // 4. 战胜：记录通关进度并给奖
        $cleared = (int)(Redis::get('sanctum_cleared:' . $uid) ?: 0);
        if ($realmId > $cleared) {
            Redis::set('sanctum_cleared:' . $uid, (string)$realmId, 86400 * 30);
        }

        $rewardSilver = (int)$cfg['reward_silver'];
        $rewardGold = (int)$cfg['reward_gold'];

        $newSilver = (int)($member['silver'] ?? 0) + $rewardSilver;
        $newGold = (int)($member['gold'] ?? 0) + $rewardGold;

        DB::table('member')->where('uid', $uid)->update([
            'silver' => $newSilver,
            'gold' => $newGold,
        ]);
        Redis::del('player:' . $uid);

        $nickname = $member['nickname'] ?? ('主公' . $uid);
        Broadcaster::broadcast('marquee', [
            'content' => "圣域破晓！主公【{$nickname}】力克四象圣尊【{$cfg['boss']}】，扬我国威！",
            'type' => 'system_broadcast',
        ]);

        return [
            'status' => 1,
            'win' => 1,
            'realm_id' => $realmId,
            'boss_name' => $cfg['boss'],
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'rounds' => $simResult['rounds'],
            'message' => "大捷！成功突破【{$cfg['name']}】，击溃【{$cfg['boss']}】！获得银两 {$rewardSilver}、元宝 {$rewardGold}！",
        ];
    }

    /**
     * 领取圣域通关总大奖: c=sanctum&m=get_reward
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $cleared = (int)(Redis::get('sanctum_cleared:' . $uid) ?: 0);

        if ($cleared < 4) {
            return ['status' => 0, 'message' => "当前已突破 {$cleared}/4 大圣域，需通关全部四象圣域方可申领终极神礼！"];
        }

        return [
            'status' => 1,
            'message' => '四象圣域圆满大成，上古圣兽精魄已悉数归位！',
        ];
    }
}
