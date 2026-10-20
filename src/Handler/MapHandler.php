<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Hero;
use Hanjiang\Battle\Position;
use Hanjiang\Context;
use Hanjiang\Database\DB;
use Hanjiang\Database\Redis;

final class MapHandler
{
    /**
     * 世界十三州大地图主信息: c=map&m=index
     * 前端 mapworld.js 循环遍历 0..99，直接读取 res[i]，必须以势力 ID 为键
     */
    public static function index(Context $ctx): array
    {
        $res = [
            'status' => 1,
            'open' => 1,
            'enter_world' => 1,
            'next_status' => 0,
            'first_enter' => 0,
        ];

        // 默认开放前 10 个三国主流势力 (黄巾、袁绍、公孙瓒、董卓、马腾、刘表、袁术、吕布、曹操、刘备)
        for ($i = 1; $i <= 10; $i++) {
            $res[(string)$i] = [
                'id' => $i,
                'status' => 1, // 1 为开启显示
                'pass_status' => $i === 1 ? 0 : 0, // 1 为已通关全部关卡
                'rewardnum' => 0,
            ];
        }

        return $res;
    }

    /**
     * 战役势力场景关卡列表: c=map&m=get_scene_list
     * 前端读取 res.list，按 stage 渲染关卡城池 (大兴山、青州城等)
     */
    public static function get_scene_list(Context $ctx): array
    {
        $level = (int)($ctx->input('l', 1));
        if ($level <= 0) {
            $level = 1;
        }

        // 黄巾势力包含 4 个关卡 stage: 1大兴山 2青州 3广宗 4巨鹿
        $stages = [
            1 => ['stage' => 1, 'name' => '大兴山', 'status' => 0],
            2 => ['stage' => 2, 'name' => '青州', 'status' => 0],
            3 => ['stage' => 3, 'name' => '广宗', 'status' => 0],
            4 => ['stage' => 4, 'name' => '巨鹿', 'status' => 0],
        ];

        $list = [];
        foreach ($stages as $stgId => $stg) {
            $list[(string)$stgId] = [
                'id' => $stgId,
                'stage' => $stg['stage'],
                'openstatus' => 1,
                'status' => $stg['status'],
            ];
        }

        return [
            'status' => 1,
            'popo' => 0,
            'list' => $list,
        ];
    }

    /**
     * 关卡内怪物据点列表: c=map&m=get_mission_list
     * 前端读取 res.list，渲染每个敌将 (点击敌将弹出布阵与进攻窗口)
     */
    public static function get_mission_list(Context $ctx): array
    {
        $level = (int)($ctx->input('l', 1));
        $stage = (int)($ctx->input('s', 1));

        $enemies = [
            1 => ['name' => '黄巾前哨', 'head' => 1, 'color' => 1],
            2 => ['name' => '黄巾精兵', 'head' => 2, 'color' => 1],
            3 => ['name' => '程远志', 'head' => 3, 'color' => 2],
            4 => ['name' => '邓茂', 'head' => 4, 'color' => 2],
            5 => ['name' => '张梁', 'head' => 5, 'color' => 3],
        ];

        $list = [];
        foreach ($enemies as $mid => $cfg) {
            $list[(string)$mid] = [
                'missionid' => $mid,
                'name' => $cfg['name'],
                'head' => $cfg['head'],
                'color' => $cfg['color'],
                'openstatus' => 1,
                'status' => 0,
                'maxtimes' => 5,
                'coin' => 0,
                'power' => 100 * $mid,
            ];
        }

        return [
            'status' => 1,
            'popo' => 0,
            'next_status' => 0,
            'list' => $list,
        ];
    }

    /**
     * 发起推图战斗: c=map&m=action
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $level = (int)($ctx->input('l', 1));
        $stage = (int)($ctx->input('s', 1));
        $missionId = (int)($ctx->input('id', 1));

        $member = DB::table('member')->where('uid', $uid)->first();
        $nickname = $member['nickname'] ?? '天命主公';

        // 1. 构建我方出战英雄
        $leftHeroes = [
            new Hero(1, $nickname, Position::FrontMiddle, 1500, 1500, 320, 120, 150),
        ];

        // 2. 构建敌方据点守军
        $enemyHp = 800 + $missionId * 150;
        $enemyAtk = 180 + $missionId * 30;
        $rightHeroes = [
            new Hero(100 + $missionId, '黄巾守将', Position::FrontMiddle, $enemyHp, $enemyHp, $enemyAtk, 80, 100),
        ];

        // 3. 调用确定性纯内存九宫格推演引擎
        $engine = new BattleEngine($leftHeroes, $rightHeroes);
        $simResult = $engine->simulate();

        // 4. 组装符合原版 battleworld.js 的战斗回放结构
        $battleReport = [
            'leftteam' => [
                2 => [
                    'id' => 1,
                    'name' => $nickname,
                    'head' => (int)($member['gender'] ?? 1),
                    'position' => 2,
                    'blood' => 1500,
                    'allblood' => 1500,
                    'power' => 50,
                ],
            ],
            'rightteam' => [
                2 => [
                    'id' => 100 + $missionId,
                    'name' => '黄巾守将',
                    'head' => 3,
                    'position' => 2,
                    'blood' => $enemyHp,
                    'allblood' => $enemyHp,
                    'power' => 0,
                ],
            ],
            'process' => self::buildBattleProcess($simResult['logs']),
            'assess' => $simResult['win'] ? 3 : -1, // 3: 大胜
            'leftbuff' => ['mingzhong' => 0, 'jingzhun' => 0, 'kangyun' => 0],
            'rightbuff' => ['mingzhong' => 0, 'jingzhun' => 0, 'kangyun' => 0],
        ];

        // 5. 战斗胜利升级与经验结算
        $levelUp = 0;
        if ($member !== null) {
            $newExp = (int)$member['exp'] + 100;
            $curLevel = (int)$member['level'];
            // 经验达到升级
            if ($newExp >= (int)$member['maxexp']) {
                $curLevel++;
                $newExp = 0;
                $levelUp = 1;
            }

            DB::table('member')->where('uid', $uid)->update([
                'act' => max(0, (int)$member['act'] - 1),
                'silver' => (int)$member['silver'] + 500,
                'exp' => $newExp,
                'level' => $curLevel,
                'updated_at' => time(),
            ]);
            Redis::del('player:' . $uid);
        }

        return [
            'status' => 1,
            'info' => [
                'win' => $simResult['win'] ? 1 : 0,
                'exp' => 100,
                'gexp' => 150,
                'silver' => 500,
                'background' => 1,
                'openstatus' => 1,
                'itemname' => '青铜铠甲',
                'levelup' => $levelUp,
                'report' => 0,
            ],
            'battle' => $battleReport,
        ];
    }

    /**
     * @param list<array<string, mixed>> $logs
     * @return list<array<string, mixed>>
     */
    private static function buildBattleProcess(array $logs): array
    {
        $process = [];
        foreach ($logs as $log) {
            $process[] = [
                'attackside' => $log['side'] === 'left' ? 1 : 2,
                'attackposition' => $log['attacker_pos'],
                'targetside' => $log['side'] === 'left' ? 2 : 1,
                'targetposition' => $log['target_pos'],
                'damage' => $log['damage'],
                'crit' => $log['is_crit'] ? 1 : 0,
                'blood' => $log['target_hp'],
                'skill' => 0,
                'dodge' => 0,
                'block' => 0,
            ];
        }
        return $process;
    }
}
