<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\GeneralHandler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Hero;
use Hanjiang\Battle\Position;
use OmniPHP\Http\Context;
use OmniPHP\Database\DB;
use OmniPHP\Cache\Redis;

final class ArenaHandler
{
    /**
     * 天梯榜首预设镜像对手
     *
     * @var array<int, array<string, mixed>>
     */
    public const array NPC_DEFENDERS = [
        10001 => ['name' => '诸葛孔明', 'level' => 15, 'hp' => 2200, 'attack' => 380, 'defense' => 160, 'speed' => 150],
        10002 => ['name' => '常山赵子龙', 'level' => 8, 'hp' => 1200, 'attack' => 240, 'defense' => 100, 'speed' => 130],
        10003 => ['name' => '燕人张翼德', 'level' => 6, 'hp' => 1000, 'attack' => 210, 'defense' => 90, 'speed' => 120],
        10004 => ['name' => '守榜斥候', 'level' => 4, 'hp' => 800, 'attack' => 180, 'defense' => 70, 'speed' => 110],
        10005 => ['name' => '演武假人', 'level' => 1, 'hp' => 600, 'attack' => 140, 'defense' => 60, 'speed' => 100],
    ];

    /**
     * 获取或初始化用户的竞技场数据
     *
     * @return array<string, mixed>
     */
    public static function getOrInitArena(int $uid): array
    {
        $arena = DB::table('arena')->where('uid', $uid)->first();
        if ($arena !== null) {
            // 每日跨天重置挑战次数
            $now = time();
            $lastTime = (int)$arena['dateline'];
            if ($lastTime > 0 && date('Ymd', $lastTime) !== date('Ymd', $now)) {
                DB::table('arena')->where('uid', $uid)->update([
                    'times' => 15,
                    'dateline' => $now,
                ]);
                $arena['times'] = 15;
            }
            return $arena;
        }

        // 初始化守榜 NPC 镜像
        $totalArena = DB::table('arena')->count();
        if ($totalArena === 0) {
            $rankNum = 1;
            foreach (self::NPC_DEFENDERS as $npcUid => $npc) {
                DB::table('arena')->insert([
                    'uid' => $npcUid,
                    'username' => $npc['name'],
                    'rank' => $rankNum,
                    'times' => 15,
                    'dateline' => time(),
                    'get_time' => 0,
                ]);
                $rankNum++;
            }
        }

        // 查询天梯最大排名
        $maxRankRow = DB::query("SELECT MAX(`rank`) as max_rank FROM `zc_sanguo_arena`");
        $maxRank = (int)($maxRankRow[0]['max_rank'] ?? 0);
        $myRank = $maxRank + 1;

        $member = DB::table('member')->where('uid', $uid)->first();
        $name = $member['nickname'] ?? '天命主公';

        DB::table('arena')->insert([
            'uid' => $uid,
            'username' => $name,
            'rank' => $myRank,
            'times' => 15,
            'dateline' => time(),
            'get_time' => 0,
        ]);

        DB::table('member_field')->where('uid', $uid)->update(['rank' => $myRank]);

        return DB::table('arena')->where('uid', $uid)->first() ?? [];
    }

    /**
     * 演武榜列表: c=arena&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $myArena = self::getOrInitArena($uid);
        $myRank = (int)$myArena['rank'];

        // 获取排在自己前面的 3~5 位可挑战对手
        $opponents = DB::query(
            "SELECT `uid`, `username` as `name`, `rank` FROM `zc_sanguo_arena` WHERE `rank` < :rank ORDER BY `rank` DESC LIMIT 4",
            ['rank' => $myRank]
        );

        if (empty($opponents)) {
            // 如果自己已经是第一名，显示后一位对手
            $opponents = DB::query(
                "SELECT `uid`, `username` as `name`, `rank` FROM `zc_sanguo_arena` WHERE `rank` > :rank ORDER BY `rank` ASC LIMIT 4",
                ['rank' => $myRank]
            );
        }

        // 格式化对手列表 (1-based: 1..5)
        $list = [];
        $idx = 1;
        foreach ($opponents as $op) {
            $opUid = (int)$op['uid'];
            $level = 10;
            if (isset(self::NPC_DEFENDERS[$opUid])) {
                $level = (int)self::NPC_DEFENDERS[$opUid]['level'];
            } else {
                $m = DB::table('member')->where('uid', $opUid)->first();
                if ($m !== null) {
                    $level = (int)$m['level'];
                }
            }

            $list[(string)$idx] = [
                'rank' => (int)$op['rank'],
                'uid' => $opUid,
                'username' => (string)$op['name'],
                'name' => (string)$op['name'],
                'level' => $level,
                'gender' => ($idx % 2 === 0) ? 2 : 1,
                'status' => 0,
            ];
            $idx++;
        }

        // 默认保底兜底
        if (empty($list)) {
            $list['1'] = ['rank' => 1, 'uid' => 10001, 'username' => '诸葛孔明', 'name' => '诸葛孔明', 'level' => 25, 'gender' => 1, 'status' => 0];
        }

        // 查询天梯榜首前五名霸主
        $topFive = DB::query("SELECT `uid`, `username`, `rank` FROM `zc_sanguo_arena` ORDER BY `rank` ASC LIMIT 5");
        $topList = [];
        $tIdx = 1;
        foreach ($topFive as $top) {
            $tUid = (int)$top['uid'];
            $level = 20;
            if (isset(self::NPC_DEFENDERS[$tUid])) {
                $level = (int)self::NPC_DEFENDERS[$tUid]['level'];
            } else {
                $m = DB::table('member')->where('uid', $tUid)->first();
                if ($m !== null) {
                    $level = (int)$m['level'];
                }
            }
            $topList[(string)$tIdx] = [
                'uid' => $tUid,
                'rank' => (int)$top['rank'],
                'username' => (string)$top['username'],
                'level' => $level,
                'tree' => 0,
            ];
            $tIdx++;
        }

        $logData = [
            'rank' => $myRank,
            'times' => max(0, (int)$myArena['times']),
            'cd' => 0,
            'act_arena_int' => 100,
        ];

        $endTime = max(0, strtotime('tomorrow') - time());

        $rewardData = [
            'intnum' => 50,
            'silver' => 10000,
            'reputation' => 100,
        ];

        return [
            'status' => 1,
            'rank' => $myRank,
            'left_times' => max(0, (int)$myArena['times']),
            'cd' => 0,
            'log' => $logData,
            'list' => $list,
            'top_list' => $topList,
            'reward' => $rewardData,
            'end_time' => $endTime,
            'message' => 'ok',
        ];
    }

    /**
     * 发起挑战: c=arena&m=action
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $targetUid = (int)($ctx->input('target_uid', $ctx->input('t', 10002)));

        $myArena = self::getOrInitArena($uid);
        if ((int)$myArena['times'] <= 0) {
            return [
                'status' => -1,
                'message' => '今日演武挑战次数已用尽',
            ];
        }

        $myRank = (int)$myArena['rank'];
        $targetArena = DB::table('arena')->where('uid', $targetUid)->first();
        $targetRank = $targetArena ? (int)$targetArena['rank'] : 1;

        // 1. 构建我方出战队伍 (多武将 + 装备 + 阵法加成)
        $member = DB::table('member')->where('uid', $uid)->first();
        $generals = DB::table('general')->where('uid', $uid)->where('pos', '>', 0)->get();
        if (empty($generals)) {
            $generals = DB::table('general')->where('uid', $uid)->get();
        }

        $matrix = DB::table('matrix')->where('uid', $uid)->first();
        $matrixMid = (int)($matrix['mid'] ?? 1);
        $matrixLevel = max(1, (int)($matrix['level'] ?? 1));
        $matrixAddition = 1.0 + 0.05 + ($matrixLevel - 1) * 0.01;

        $leftHeroes = [];
        foreach ($generals as $g) {
            $gid = (int)$g['id'];
            $posInt = (int)$g['pos'] > 0 ? (int)$g['pos'] : 2;
            $pos = Position::tryFrom($posInt) ?? Position::FrontMiddle;

            $equipped = DB::table('equipment')->where('uid', $uid)->where('generalid', $gid)->get();
            $stats = GeneralHandler::calculateGeneralStats($g, $equipped);

            $finalAtk = ($matrixMid === 1) ? (int)round($stats['attack'] * $matrixAddition) : $stats['attack'];
            $finalDef = ($matrixMid === 3) ? (int)round($stats['defense'] * $matrixAddition) : $stats['defense'];

            $heroName = $g['name'] ?: ($member['nickname'] ?? '主将');
            $leftHeroes[] = new Hero($gid, $heroName, $pos, $stats['hp'], $stats['hp'], $finalAtk, $finalDef, $stats['speed']);
        }

        if (empty($leftHeroes)) {
            $leftHeroes[] = new Hero(1, '天命主公', Position::FrontMiddle, 2000, 2000, 380, 150, 150);
        }

        // 2. 构建对手防守队伍
        $rightHeroes = [];
        $targetGenerals = DB::table('general')->where('uid', $targetUid)->where('pos', '>', 0)->get();
        if (!empty($targetGenerals)) {
            foreach ($targetGenerals as $tg) {
                $tgId = (int)$tg['id'];
                $tgPosInt = (int)$tg['pos'] > 0 ? (int)$tg['pos'] : 2;
                $tgPos = Position::tryFrom($tgPosInt) ?? Position::FrontMiddle;
                $tgEquipped = DB::table('equipment')->where('uid', $targetUid)->where('generalid', $tgId)->get();
                $tgStats = GeneralHandler::calculateGeneralStats($tg, $tgEquipped);
                $rightHeroes[] = new Hero($tgId, $tg['name'], $tgPos, $tgStats['hp'], $tgStats['hp'], $tgStats['attack'], $tgStats['defense'], $tgStats['speed']);
            }
        } elseif (isset(self::NPC_DEFENDERS[$targetUid])) {
            $npc = self::NPC_DEFENDERS[$targetUid];
            $rightHeroes[] = new Hero($targetUid, $npc['name'], Position::FrontMiddle, (int)$npc['hp'], (int)$npc['hp'], (int)$npc['attack'], (int)$npc['defense'], (int)$npc['speed']);
        } else {
            $rightHeroes[] = new Hero($targetUid, '演武守将', Position::FrontMiddle, 1600, 1600, 320, 120, 140);
        }

        // 3. 确定性纯内存推演
        $engine = new BattleEngine($leftHeroes, $rightHeroes);
        $simResult = $engine->simulate();
        $isWin = $simResult['win'];

        // 4. 结算：扣除挑战次数
        $remainTimes = max(0, (int)$myArena['times'] - 1);
        DB::table('arena')->where('uid', $uid)->update([
            'times' => $remainTimes,
            'dateline' => time(),
        ]);

        $finalRank = $myRank;
        $rewardReputation = 10;
        $rewardSilver = 200;

        // 若挑战成功且对手排名高于自己，进行天梯名次互换 (Rank Swap)
        if ($isWin && $targetRank < $myRank && $targetRank > 0) {
            DB::table('arena')->where('uid', $uid)->update(['rank' => $targetRank]);
            DB::table('arena')->where('uid', $targetUid)->update(['rank' => $myRank]);

            DB::table('member_field')->where('uid', $uid)->update(['rank' => $targetRank]);
            DB::table('member_field')->where('uid', $targetUid)->update(['rank' => $myRank]);

            $finalRank = $targetRank;
            $rewardReputation = 30;
            $rewardSilver = 800;
        }

        // 发放声望与银两
        if ($member !== null) {
            DB::table('member')->where('uid', $uid)->update([
                'silver' => (int)$member['silver'] + $rewardSilver,
                'reputation' => (int)$member['reputation'] + $rewardReputation,
            ]);
            Redis::del('player:' . $uid);
        }

        return [
            'status' => 1,
            'win' => $isWin ? 1 : -1,
            'rank' => $finalRank,
            'old_rank' => $myRank,
            'left_times' => $remainTimes,
            'reputation' => $rewardReputation,
            'silver' => $rewardSilver,
            'rounds' => $simResult['rounds'],
            'message' => $isWin ? '演武挑战大获全胜，排名提升！' : '惜败于对手，再接再厉！',
        ];
    }

    /**
     * 领取演武排名每日俸禄: c=arena&m=get_reward
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $myArena = self::getOrInitArena($uid);
        $now = time();

        $lastGet = (int)($myArena['get_time'] ?? 0);
        if ($lastGet > 0 && date('Ymd', $lastGet) === date('Ymd', $now)) {
            return [
                'status' => 0,
                'message' => '今日演武俸禄已领取，请明日再来',
            ];
        }

        $rank = max(1, (int)$myArena['rank']);
        // 排名越高奖励越丰厚
        $rewardReputation = max(20, 200 - $rank * 10);
        $rewardSilver = max(1000, 10000 - $rank * 500);

        DB::table('arena')->where('uid', $uid)->update([
            'get_time' => $now,
        ]);

        $member = DB::table('member')->where('uid', $uid)->first();
        if ($member !== null) {
            DB::table('member')->where('uid', $uid)->update([
                'silver' => (int)$member['silver'] + $rewardSilver,
                'reputation' => (int)$member['reputation'] + $rewardReputation,
            ]);
            Redis::del('player:' . $uid);
        }

        return [
            'status' => 1,
            'rank' => $rank,
            'reputation' => $rewardReputation,
            'silver' => $rewardSilver,
            'message' => '成功领取今日演武排名俸禄',
        ];
    }

    /**
     * 清理冷却: c=arena&m=clear_cd
     */
    public static function clear_cd(Context $ctx): array
    {
        return [
            'status' => 1,
            'message' => '冷却已清除',
        ];
    }
}
