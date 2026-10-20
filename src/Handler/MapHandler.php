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
     * 世界地图主信息: c=map&m=index
     */
    public static function index(Context $ctx): array
    {
        return [
            'status' => 1,
            'open' => 1,
            'enter_world' => 1,
            'next_status' => 0,
            'first_enter' => 0,
            'list' => [
                [
                    'id' => 1,
                    'name' => '黄巾起义',
                    'status' => 1,
                    'rewardnum' => 0,
                ],
                [
                    'id' => 2,
                    'name' => '董卓乱政',
                    'status' => 0,
                    'rewardnum' => 0,
                ],
            ],
        ];
    }

    /**
     * 势力场景列表: c=map&m=get_scene_list
     */
    public static function get_scene_list(Context $ctx): array
    {
        $level = (int)($ctx->input('l', 1));

        return [
            'status' => 1,
            'popo' => 0,
            'info' => [
                [
                    'stage' => 1,
                    'name' => '大兴山',
                    'status' => 1,
                    'openstatus' => 1,
                ],
                [
                    'stage' => 2,
                    'name' => '青州城',
                    'status' => 0,
                    'openstatus' => 0,
                ],
            ],
        ];
    }

    /**
     * 场景据点敌人列表: c=map&m=get_mission_list
     */
    public static function get_mission_list(Context $ctx): array
    {
        $level = (int)($ctx->input('l', 1));
        $stage = (int)($ctx->input('s', 1));

        // 默认第一关怪物配置 (黄巾前哨)
        $enemies = [
            1 => ['name' => '黄巾前哨', 'head' => 1, 'color' => 1],
            2 => ['name' => '黄巾精兵', 'head' => 2, 'color' => 1],
            3 => ['name' => '程远志', 'head' => 3, 'color' => 2],
            4 => ['name' => '邓茂', 'head' => 4, 'color' => 2],
            5 => ['name' => '张梁', 'head' => 5, 'color' => 3],
        ];

        $list = [];
        foreach ($enemies as $mid => $cfg) {
            $list[$mid] = [
                'missionid' => $mid,
                'name' => $cfg['name'],
                'head' => $cfg['head'],
                'color' => $cfg['color'],
                'openstatus' => 1,
                'status' => $mid === 1 ? 0 : 0,
                'maxtimes' => 1,
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
            new Hero(100 + $missionId, '黄巾敌将', Position::FrontMiddle, $enemyHp, $enemyHp, $enemyAtk, 80, 100),
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
                    'name' => '黄巾敌将',
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

        // 5. 扣减 1 点军令体力，发放战后结算银两和经验
        if ($member !== null && $member['act'] > 0) {
            DB::table('member')->where('uid', $uid)->update([
                'act' => max(0, (int)$member['act'] - 1),
                'silver' => (int)$member['silver'] + 500,
                'exp' => (int)$member['exp'] + 100,
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
                'itemname' => '青铜剑',
                'levelup' => 0,
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
