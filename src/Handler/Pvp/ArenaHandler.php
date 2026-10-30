<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\City\MemberHandler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Hero;
use Hanjiang\Battle\Position;
use Hanjiang\Battle\TeamBuilder;
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

        $userPoints = (int)(Redis::get("arena_points:{$uid}") ?: 100);
        $logData = [
            'rank' => $myRank,
            'times' => max(0, (int)$myArena['times']),
            'cd' => 0,
            'act_arena_int' => $userPoints,
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

        // 1. 我方队伍（旧版 arena 控制器走 battle_model->battle_pk：init_member_team_info，非 PVE）
        $member = DB::table('member')->where('uid', $uid)->first();
        $left = TeamBuilder::forMember($uid);
        $leftHeroes = array_values(TeamBuilder::heroes($left['team'], $left['matrix_type'], $left['matrix_coe']));

        // 2. 对手防守队伍（init_member_team_info($touid, 1)：防守方，不是本人，不触发 6 星特效）
        // 旧版 battle_pk 的状态只看防守方：防守方 -1（没有阵法）/ -3（武将信息错误）时失败；
        // 任一侧 -2（阵法里没有武将）、以及进攻方的 -1 / -3 都按空队伍继续战斗（空队伍直接判负），旧版如此
        $targetMember = DB::table('member')->where('uid', $targetUid)->whereNull('deleted_at')->first();
        if ($targetMember !== null) {
            $right = TeamBuilder::forMember($targetUid, defense: true, self: false);
            if ($right['status'] === -1 || $right['status'] === -3) {
                // 本玩法尚未按旧版 arena 控制器移植，直接回传 battle_pk 的失败状态
                return ['status' => $right['status'], 'message' => '对手阵容信息错误'];
            }
            $rightHeroes = array_values(TeamBuilder::heroes($right['team'], $right['matrix_type'], $right['matrix_coe']));
        } elseif (isset(self::NPC_DEFENDERS[$targetUid])) {
            // 依赖未移植：天梯预设 NPC 是新版自定的（旧版演武榜对手都是玩家）
            $npc = self::NPC_DEFENDERS[$targetUid];
            $rightHeroes = [Hero::unported($targetUid, $npc['name'], Position::FrontMiddle, (int)$npc['hp'], (int)$npc['attack'], (int)$npc['defense'])];
        } else {
            // 依赖未移植：对手不存在时的守将是新版自定的（旧版 arena 控制器在对手不在榜上时提前返回错误）
            $rightHeroes = [Hero::unported($targetUid, '演武守将', Position::FrontMiddle, 1600, 320, 120)];
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
        $totalSilver = (int)($member['silver'] ?? 0);
        if ($member !== null) {
            $totalSilver += $rewardSilver;
            DB::table('member')->where('uid', $uid)->update([
                'silver' => $totalSilver,
                'reputation' => (int)$member['reputation'] + $rewardReputation,
            ]);
            Redis::del('player:' . $uid);
        }

        $newReward = [
            'intnum' => 50,
            'silver' => 10000,
            'reputation' => 100,
        ];

        return [
            'status' => 1,
            'rank' => $rank,
            'reputation' => $rewardReputation,
            'silver' => $rewardSilver,
            'all_silver' => $totalSilver,
            'new_reward' => $newReward,
            'message' => '成功领取今日演武排名俸禄',
        ];
    }

    /**
     * 购买演武挑战次数: c=arena&m=buy_pk_num
     */
    public static function buy_pk_num(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $type = (int)($ctx->input('t', 0)); // 0: 查价格, 1: 确认购买

        $todayStr = date('Ymd');
        $boughtKey = "arena_buy_times:{$uid}:{$todayStr}";
        $boughtTimes = (int)(Redis::get($boughtKey) ?? 0);
        $price = min(100, 20 + $boughtTimes * 10);

        if ($type === 0) {
            return [
                'status' => 1,
                'price' => $price,
                'message' => 'ok',
            ];
        }

        // type == 1: 购买挑战次数
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ((int)($member['gold'] ?? 0) < $price) {
            return [
                'status' => -1,
                'message' => '元宝不足，无法购买挑战次数',
            ];
        }

        if (!MemberHandler::deductCurrency($uid, gold: $price)) {
            return [
                'status' => -1,
                'message' => '元宝扣除失败',
            ];
        }

        // 挑战次数 + 1
        $myArena = self::getOrInitArena($uid);
        $newTimes = (int)($myArena['times'] ?? 0) + 1;
        DB::table('arena')->where('uid', $uid)->update([
            'times' => $newTimes,
        ]);

        Redis::set($boughtKey, (string)($boughtTimes + 1), 86400 * 2);
        Redis::del('player:' . $uid);

        $freshMember = DB::table('member')->where('uid', $uid)->first();
        $nextPrice = min(100, 20 + ($boughtTimes + 1) * 10);

        return [
            'status' => 1,
            'gold' => (int)($freshMember['gold'] ?? 0),
            'times' => $newTimes,
            'price' => $nextPrice,
            'message' => '演武挑战次数购买成功！',
        ];
    }

    /**
     * 演武积分商城兑换配置 (type: 1 积分兑换, 2 限量商品)
     * re_type 对应客户端 showMessage: 2 银两, 13 军令, 5 原魂, 7 声望, 10 突飞卡, 14 精炼石, 8 装备
     */
    public const array SHOP_GOODS = [
        // 积分兑换 (type: 1)
        [
            'id' => 1,
            'type' => 1,
            're_type' => 2, // 2: 银币
            're_value1' => 100000,
            'use_int' => 50,
            'num' => 10,
        ],
        [
            'id' => 2,
            'type' => 1,
            're_type' => 13, // 13: 军令
            're_value1' => 50,
            'use_int' => 30,
            'num' => 5,
        ],
        [
            'id' => 3,
            'type' => 1,
            're_type' => 5, // 5: 原魂/将魂
            're_value1' => 150,
            'use_int' => 60,
            'num' => 5,
        ],
        [
            'id' => 4,
            'type' => 1,
            're_type' => 7, // 7: 声望
            're_value1' => 500,
            'use_int' => 80,
            'num' => 5,
        ],
        [
            'id' => 5,
            'type' => 1,
            're_type' => 10, // 10: 突飞卡
            're_value1' => 30,
            'use_int' => 40,
            'num' => 10,
        ],
        [
            'id' => 6,
            'type' => 1,
            're_type' => 14, // 14: 精炼石
            're_value1' => 20,
            'use_int' => 50,
            'num' => 10,
        ],
        // 限量商品 (type: 2)
        [
            'id' => 7,
            'type' => 2,
            're_type' => 8, // 8: 装备
            're_value1' => 1,
            'use_int' => 150,
            'num' => 1,
            'info' => [
                'id' => 31,
                'name' => '破军兵符',
                'quality' => 3,
                'image' => 31,
            ],
        ],
        [
            'id' => 8,
            'type' => 2,
            're_type' => 8,
            're_value1' => 1,
            'use_int' => 250,
            'num' => 1,
            'info' => [
                'id' => 32,
                'name' => '神射兵符',
                'quality' => 3,
                'image' => 32,
            ],
        ],
        [
            'id' => 9,
            'type' => 2,
            're_type' => 8,
            're_value1' => 1,
            'use_int' => 400,
            'num' => 1,
            'info' => [
                'id' => 33,
                'name' => '重甲兵符',
                'quality' => 4,
                'image' => 33,
            ],
        ],
    ];

    /**
     * 演武积分商城主界面: c=arena&m=shop_index
     */
    public static function shop_index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $userPoints = (int)(Redis::get("arena_points:{$uid}") ?: 100);

        $todayStr = date('Ymd');
        $list = [];
        foreach (self::SHOP_GOODS as $item) {
            $id = (int)$item['id'];
            $exchKey = "arena_exch:{$uid}:{$todayStr}:{$id}";
            $exchanged = (int)(Redis::get($exchKey) ?? 0);
            $maxNum = (int)$item['num'];
            $remain = max(0, $maxNum - $exchanged);
            $canExch = ($remain > 0 && $userPoints >= (int)$item['use_int']) ? 1 : 0;

            $entry = array_merge($item, [
                'is_exch' => $canExch,
                'exch_num' => $remain,
            ]);
            $list[] = $entry;
        }

        return [
            'status' => 1,
            'act_arena_int' => $userPoints,
            'list' => $list,
            'message' => 'ok',
        ];
    }

    /**
     * 演武积分商城兑换: c=arena&m=shop_exchange
     */
    public static function shop_exchange(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)($ctx->input('id', 1));

        $target = null;
        foreach (self::SHOP_GOODS as $item) {
            if ($item['id'] === $id) {
                $target = $item;
                break;
            }
        }

        // 依赖未移植：旧版积分兑换取 MasterData ArenaExchange（re_type 8 发 re_value1 号装备）；
        // 新版写死的兵符兑换品不在 MasterData Equip 中，没有可对应的模板，不予兑换（不扣积分）。
        if ($target === null || (int)$target['re_type'] === 8) {
            return ['status' => -3, 'msg' => '商品不存在'];
        }

        $userPoints = (int)(Redis::get("arena_points:{$uid}") ?: 100);
        $cost = (int)$target['use_int'];
        if ($userPoints < $cost) {
            return ['status' => 4, 'msg' => '积分不足，无法兑换该道具'];
        }

        $todayStr = date('Ymd');
        $exchKey = "arena_exch:{$uid}:{$todayStr}:{$id}";
        $exchanged = (int)(Redis::get($exchKey) ?? 0);
        if ($exchanged >= (int)$target['num']) {
            return ['status' => 3, 'msg' => '今日兑换次数已达上限'];
        }

        // 扣除积分
        $newPoints = $userPoints - $cost;
        Redis::set("arena_points:{$uid}", (string)$newPoints, 86400 * 30);
        Redis::set($exchKey, (string)($exchanged + 1), 86400 * 2);

        // 发放资产
        $reType = (int)$target['re_type'];
        $val = (int)$target['re_value1'];
        $rewardName = '丰厚物资';

        match ($reType) {
            2 => (function() use ($uid, $val, &$rewardName) {
                MemberHandler::addCurrency($uid, silver: $val);
                $rewardName = "银两 x{$val}";
            })(),
            13 => (function() use ($uid, $val, &$rewardName) {
                MemberHandler::addCurrency($uid, act: $val);
                $rewardName = "军令 x{$val}";
            })(),
            5 => (function() use ($uid, $val, &$rewardName) {
                MemberHandler::addCurrency($uid, soul: $val);
                $rewardName = "将魂 x{$val}";
            })(),
            7 => (function() use ($uid, $val, &$rewardName) {
                MemberHandler::addCurrency($uid, reputation: $val);
                $rewardName = "声望 x{$val}";
            })(),
            10 => (function() use ($uid, $val, &$rewardName) {
                MemberHandler::addCurrency($uid, soul: $val * 5);
                $rewardName = "突飞卡 x{$val}";
            })(),
            14 => (function() use ($uid, $val, &$rewardName) {
                MemberHandler::addCurrency($uid, silver: $val * 1000);
                $rewardName = "精炼石 x{$val}";
            })(),
            default => null,
        };

        // 获取刷新后的商品列表
        $freshList = [];
        $rewardItem = null;
        foreach (self::SHOP_GOODS as $item) {
            $gId = (int)$item['id'];
            $curExch = (int)(Redis::get("arena_exch:{$uid}:{$todayStr}:{$gId}") ?? 0);
            $maxNum = (int)$item['num'];
            $remain = max(0, $maxNum - $curExch);
            $canExch = ($remain > 0 && $newPoints >= (int)$item['use_int']) ? 1 : 0;

            $entry = array_merge($item, [
                'is_exch' => $canExch,
                'exch_num' => $remain,
            ]);
            $freshList[] = $entry;
            if ($gId === $id) {
                $rewardItem = $entry;
            }
        }

        if ($rewardItem === null) {
            $rewardItem = $target;
        }

        return [
            'status' => 1,
            'act_arena_int' => $newPoints,
            'reward' => $rewardItem,
            'reward_name' => $rewardName,
            'list' => $freshList,
            'msg' => "恭喜！成功兑换获得【{$rewardName}】！",
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
