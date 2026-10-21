<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Hero;
use Hanjiang\Battle\Position;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class HeavenHandler
{
    public const array CONSTELLATIONS = [
        1 => ['id' => 1, 'name' => '角木蛟·先锋星宿', 'hp' => 1800, 'attack' => 300, 'defense' => 120, 'reward_silver' => 1500],
        2 => ['id' => 2, 'name' => '亢金龙·青龙星宿', 'hp' => 2400, 'attack' => 360, 'defense' => 150, 'reward_silver' => 2500],
        3 => ['id' => 3, 'name' => '氐土貉·北斗星宿', 'hp' => 3000, 'attack' => 420, 'defense' => 180, 'reward_silver' => 4000],
        4 => ['id' => 4, 'name' => '房日兔·紫微星宿', 'hp' => 4000, 'attack' => 500, 'defense' => 220, 'reward_silver' => 6000],
    ];

    /**
     * 天命观星主界面: c=heaven&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $starLevel = (int)(Redis::get('heaven_star:' . $uid) ?: 1);

        $list = [];
        foreach (self::CONSTELLATIONS as $id => $c) {
            $list[$id] = array_merge($c, [
                'status' => ($id < $starLevel) ? 2 : (($id === $starLevel) ? 1 : 0),
            ]);
        }

        return [
            'status' => 1,
            'current_star' => $starLevel,
            'stars' => $list,
            'message' => 'ok',
        ];
    }

    /**
     * 挑战星宿天神: c=heaven&m=pk
     */
    public static function pk(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $starId = (int)($ctx->input('id', $ctx->input('star_id', 1)));
        $member = DB::table('member')->where('uid', $uid)->first();

        $curStar = (int)(Redis::get('heaven_star:' . $uid) ?: 1);
        if ($starId <= 0) {
            $starId = $curStar;
        }

        if (!isset(self::CONSTELLATIONS[$starId])) {
            return ['status' => 0, 'message' => '星宿不存在'];
        }

        $cfg = self::CONSTELLATIONS[$starId];

        // 1. 构建我方出战队伍 (多武将 + 装备 + 阵法)
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
            $leftHeroes[] = new Hero($gid, $g['name'], $pos, $stats['hp'], $stats['hp'], $stats['attack'], $stats['defense'], $stats['speed']);
        }
        if (empty($leftHeroes)) {
            $leftHeroes[] = new Hero(1, '天命主公', Position::FrontMiddle, 2000, 2000, 380, 150, 150);
        }

        // 2. 构建星宿守护天神
        $rightHeroes = [
            new Hero(7000 + $cfg['id'], $cfg['name'], Position::FrontMiddle, $cfg['hp'], $cfg['hp'], $cfg['attack'], $cfg['defense'], 130),
        ];

        // 3. 纯内存九宫格推演
        $engine = new BattleEngine($leftHeroes, $rightHeroes);
        $simResult = $engine->simulate();
        $isWin = $simResult['win'];

        if (!$isWin) {
            return [
                'status' => 1,
                'win' => -1,
                'star_id' => $starId,
                'rounds' => $simResult['rounds'],
                'message' => "观星挑战【{$cfg['name']}】失败，星宿神力浩瀚，请择日再战！",
            ];
        }

        // 战胜：推进天命星宿进度并给银两
        $nextStar = $starId + 1;
        Redis::set('heaven_star:' . $uid, (string)$nextStar, 86400 * 30);

        $rewardSilver = (int)$cfg['reward_silver'];
        DB::table('member')->where('uid', $uid)->update([
            'silver' => (int)($member['silver'] ?? 0) + $rewardSilver,
        ]);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'win' => 1,
            'star_id' => $starId,
            'star_name' => $cfg['name'],
            'silver' => $rewardSilver,
            'next_star' => $nextStar,
            'rounds' => $simResult['rounds'],
            'message' => "天降吉兆！成功突破星宿【{$cfg['name']}】，获得天命银两 {$rewardSilver}！",
        ];
    }

    /**
     * 一键观星扫荡: c=heaven&m=mop_up
     */
    public static function mop_up(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $curStar = (int)(Redis::get('heaven_star:' . $uid) ?: 1);

        if ($curStar <= 1) {
            return ['status' => 0, 'message' => '尚未点亮任何星宿，无法扫荡'];
        }

        $cleared = $curStar - 1;
        $rewardSilver = $cleared * 1500;

        $member = DB::table('member')->where('uid', $uid)->first();
        DB::table('member')->where('uid', $uid)->update([
            'silver' => (int)($member['silver'] ?? 0) + $rewardSilver,
        ]);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'cleared_stars' => $cleared,
            'silver' => $rewardSilver,
            'message' => "观星扫荡完成！获得天命银两 {$rewardSilver}！",
        ];
    }
}
