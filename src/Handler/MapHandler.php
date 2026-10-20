<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Hero;
use Hanjiang\Battle\Position;
use Hanjiang\Context;
use Hanjiang\Database\DB;
use Hanjiang\Database\Redis;
use Hanjiang\Enum\BattleAssess;
use Hanjiang\Enum\Faction;
use Hanjiang\Enum\FactionStatus;

final class MapHandler
{
    /**
     * 世界十三州大地图主信息: c=map&m=index
     * 采用强类型 Faction / FactionStatus 领域枚举，同时输出语义化 factions 列表与向下兼容键
     */
    public static function index(Context $ctx): array
    {
        $factions = [];
        $res = [
            'status' => 1,
            'open' => 1,
            'enter_world' => 1,
            'next_status' => 0,
            'first_enter' => 0,
        ];

        // 默认开启前 10 个主流战役势力
        foreach (array_slice(Faction::cases(), 0, 10) as $faction) {
            $item = [
                'id' => $faction->value,
                'code' => $faction->code(),
                'name' => $faction->label(),
                'status' => FactionStatus::Active->value,
                'status_name' => FactionStatus::Active->label(),
                'pass_status' => 0,
                'rewardnum' => 0,
            ];

            $factions[] = $item;
            // 保持对老版数字键的向后兼容支持
            $res[(string)$faction->value] = $item;
        }

        // 语义化标准输出
        $res['factions'] = $factions;

        return $res;
    }

    /**
     * 战役势力场景关卡列表: c=map&m=get_scene_list
     */
    public static function get_scene_list(Context $ctx): array
    {
        $level = (int)($ctx->input('l', 1));
        if ($level <= 0) {
            $level = 1;
        }

        // 黄巾势力包含 4 个关卡 stage: 1大兴山 2青州 3广宗 4巨鹿
        $stages = [
            1 => ['stage' => 1, 'name' => '大兴山', 'status' => FactionStatus::Active->value],
            2 => ['stage' => 2, 'name' => '青州', 'status' => FactionStatus::Locked->value],
            3 => ['stage' => 3, 'name' => '广宗', 'status' => FactionStatus::Locked->value],
            4 => ['stage' => 4, 'name' => '巨鹿', 'status' => FactionStatus::Locked->value],
        ];

        $list = [];
        $stageItems = [];
        foreach ($stages as $stgId => $stg) {
            $item = [
                'id' => $stgId,
                'stage' => $stg['stage'],
                'name' => $stg['name'],
                'openstatus' => 1,
                'status' => $stg['status'],
            ];
            $list[(string)$stgId] = $item;
            $stageItems[] = $item;
        }

        return [
            'status' => 1,
            'popo' => 0,
            'list' => $list,
            'stages' => $stageItems, // 语义化关卡列表
        ];
    }

    /**
     * 关卡内怪物据点列表: c=map&m=get_mission_list
     */
    public static function get_mission_list(Context $ctx): array
    {
        $level = (int)($ctx->input('l', 1));
        $stage = (int)($ctx->input('s', 1));

        // 关卡据点敌人 (包含精准屏幕坐标与类型)
        $enemies = [
            1 => ['name' => '黄巾前哨', 'type' => 1, 'head' => 1, 'color' => 1, 'x' => 180, 'y' => 440],
            2 => ['name' => '黄巾精兵', 'type' => 1, 'head' => 2, 'color' => 1, 'x' => 320, 'y' => 360],
            3 => ['name' => '程远志',   'type' => 2, 'head' => 3, 'color' => 2, 'x' => 460, 'y' => 290],
            4 => ['name' => '邓茂',     'type' => 2, 'head' => 4, 'color' => 2, 'x' => 600, 'y' => 210],
            5 => ['name' => '张梁',     'type' => 3, 'head' => 5, 'color' => 3, 'x' => 740, 'y' => 140],
        ];

        $list = [];
        $missionItems = [];
        foreach ($enemies as $mid => $cfg) {
            $item = [
                'id' => $mid,
                'missionid' => $mid,
                'name' => $cfg['name'],
                'head' => $cfg['head'],
                'color' => $cfg['color'],
                'type' => $cfg['type'],
                'x' => $cfg['x'],
                'y' => $cfg['y'],
                'openstatus' => 1,
                'status' => 0,
                'maxtimes' => 5,
                'coin' => 0,
                'power' => 100 * $mid,
            ];
            $list[(string)$mid] = $item;
            $missionItems[] = $item;
        }

        return [
            'status' => 1,
            'popo' => 0,
            'next_status' => 0,
            'list' => $list,
            'missions' => $missionItems, // 语义化据点列表
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
            'assess' => $simResult['win'] ? BattleAssess::Perfect->value : BattleAssess::Defeat->value,
            'leftbuff' => ['mingzhong' => 0, 'jingzhun' => 0, 'kangyun' => 0],
            'rightbuff' => ['mingzhong' => 0, 'jingzhun' => 0, 'kangyun' => 0],
        ];

        // 5. 战斗胜利升级与经验结算
        $levelUp = 0;
        if ($member !== null) {
            $newExp = (int)$member['exp'] + 100;
            $curLevel = (int)$member['level'];
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
