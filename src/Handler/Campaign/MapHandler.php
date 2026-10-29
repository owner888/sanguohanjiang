<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Campaign;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\GeneralHandler;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\Hero;
use Hanjiang\Battle\Position;
use OmniPHP\Http\Context;
use OmniPHP\Database\DB;
use OmniPHP\Cache\Redis;
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
        $uid = MemberHandler::resolveUid($ctx);
        $level = (int)($ctx->input('l', 1));
        if ($level <= 0) {
            $level = 1;
        }

        $member = DB::table('member')->where('uid', $uid)->first();
        $curLevel = max(1, (int)($member['missionlevel'] ?? 1));
        $curStage = max(1, (int)($member['missionstage'] ?? 1));

        // 战役关卡场景 stage: 1~4
        $stages = [
            1 => ['stage' => 1, 'name' => '大兴山'],
            2 => ['stage' => 2, 'name' => '青州'],
            3 => ['stage' => 3, 'name' => '广宗'],
            4 => ['stage' => 4, 'name' => '巨鹿'],
        ];

        $list = [];
        $stageItems = [];
        foreach ($stages as $stgId => $stg) {
            if ($level < $curLevel) {
                // 已彻底通关的旧势力：全部关卡全开全过
                $isOpen = true;
                $isCleared = true;
            } elseif ($level === $curLevel) {
                $isOpen = ($stgId <= $curStage);
                $isCleared = ($stgId < $curStage);
            } else {
                $isOpen = false;
                $isCleared = false;
            }

            $item = [
                'id' => $stgId,
                'stage' => $stg['stage'],
                'name' => $stg['name'],
                'openstatus' => $isOpen ? 1 : 0,
                'status' => $isCleared ? 1 : 0,
            ];
            $list[(string)$stgId] = $item;
            $stageItems[] = $item;
        }

        $nextLevel = $level + 1;
        $maxOpenedLevel = max(10, $curLevel);
        $hasNext = ($nextLevel <= $maxOpenedLevel && $nextLevel <= 16);

        return [
            'status' => 1,
            'popo' => 0,
            'next_status' => $hasNext ? 1 : 0,
            'list' => $list,
            'stages' => $stageItems, // 语义化关卡列表
        ];
    }

    /**
     * 获取战役关卡据点敌人配置 (按势力 level、场景 stage、据点 missionId 全局精准映射)
     */
    public static function getEnemyConfig(int $level, int $stage, int $missionId): array
    {
        $roster = [
            // 势力 1：黄巾之乱
            1 => [
                1 => [ // 大兴山
                    1 => ['name' => '黄巾前哨', 'type' => 1, 'head' => 1, 'color' => 1, 'image' => '1', 'star' => 1, 'x' => 180, 'y' => 440],
                    2 => ['name' => '黄巾精兵', 'type' => 1, 'head' => 2, 'color' => 1, 'image' => '2', 'star' => 1, 'x' => 320, 'y' => 360],
                    3 => ['name' => '程远志',   'type' => 2, 'head' => 3, 'color' => 2, 'image' => '10', 'star' => 2, 'x' => 460, 'y' => 290],
                    4 => ['name' => '邓茂',     'type' => 2, 'head' => 4, 'color' => 2, 'image' => '11', 'star' => 2, 'x' => 600, 'y' => 210],
                    5 => ['name' => '黄巾渠帅·张梁', 'type' => 3, 'head' => 5, 'color' => 3, 'image' => '12', 'star' => 3, 'x' => 740, 'y' => 140],
                ],
                2 => [ // 青州
                    1 => ['name' => '青州前哨', 'type' => 1, 'head' => 1, 'color' => 1, 'image' => '1', 'star' => 1, 'x' => 180, 'y' => 440],
                    2 => ['name' => '黄巾精锐', 'type' => 1, 'head' => 2, 'color' => 1, 'image' => '2', 'star' => 1, 'x' => 320, 'y' => 360],
                    3 => ['name' => '波才',     'type' => 2, 'head' => 3, 'color' => 2, 'image' => '13', 'star' => 2, 'x' => 460, 'y' => 290],
                    4 => ['name' => '彭脱',     'type' => 2, 'head' => 4, 'color' => 2, 'image' => '14', 'star' => 2, 'x' => 600, 'y' => 210],
                    5 => ['name' => '地公将军·张宝', 'type' => 3, 'head' => 5, 'color' => 3, 'image' => '15', 'star' => 3, 'x' => 740, 'y' => 140],
                ],
                3 => [ // 广宗
                    1 => ['name' => '广宗前锋', 'type' => 1, 'head' => 1, 'color' => 1, 'image' => '1', 'star' => 1, 'x' => 180, 'y' => 440],
                    2 => ['name' => '黄巾死士', 'type' => 1, 'head' => 2, 'color' => 1, 'image' => '2', 'star' => 1, 'x' => 320, 'y' => 360],
                    3 => ['name' => '管亥',     'type' => 2, 'head' => 3, 'color' => 2, 'image' => '16', 'star' => 2, 'x' => 460, 'y' => 290],
                    4 => ['name' => '严政',     'type' => 2, 'head' => 4, 'color' => 2, 'image' => '17', 'star' => 2, 'x' => 600, 'y' => 210],
                    5 => ['name' => '人公将军·张梁', 'type' => 3, 'head' => 5, 'color' => 3, 'image' => '18', 'star' => 4, 'x' => 740, 'y' => 140],
                ],
                4 => [ // 巨鹿
                    1 => ['name' => '巨鹿亲卫', 'type' => 1, 'head' => 1, 'color' => 1, 'image' => '1', 'star' => 1, 'x' => 180, 'y' => 440],
                    2 => ['name' => '太平道尊', 'type' => 1, 'head' => 2, 'color' => 1, 'image' => '2', 'star' => 2, 'x' => 320, 'y' => 360],
                    3 => ['name' => '高升',     'type' => 2, 'head' => 3, 'color' => 2, 'image' => '19', 'star' => 2, 'x' => 460, 'y' => 290],
                    4 => ['name' => '韩忠',     'type' => 2, 'head' => 4, 'color' => 2, 'image' => '20', 'star' => 2, 'x' => 600, 'y' => 210],
                    5 => ['name' => '天公将军·张角', 'type' => 3, 'head' => 5, 'color' => 3, 'image' => '21', 'star' => 5, 'x' => 740, 'y' => 140],
                ],
            ],
            // 势力 2：董卓乱政
            2 => [
                1 => [ // 汜水关
                    1 => ['name' => '西凉铁骑', 'type' => 1, 'head' => 1, 'color' => 1, 'image' => '1', 'star' => 1, 'x' => 180, 'y' => 440],
                    2 => ['name' => '飞熊精兵', 'type' => 1, 'head' => 2, 'color' => 1, 'image' => '2', 'star' => 2, 'x' => 320, 'y' => 360],
                    3 => ['name' => '胡轸',     'type' => 2, 'head' => 3, 'color' => 2, 'image' => '22', 'star' => 2, 'x' => 460, 'y' => 290],
                    4 => ['name' => '樊稠',     'type' => 2, 'head' => 4, 'color' => 2, 'image' => '23', 'star' => 3, 'x' => 600, 'y' => 210],
                    5 => ['name' => '关西都督·华雄', 'type' => 3, 'head' => 5, 'color' => 3, 'image' => '24', 'star' => 4, 'x' => 740, 'y' => 140],
                ],
                2 => [ // 虎牢关
                    1 => ['name' => '并州狼骑', 'type' => 1, 'head' => 1, 'color' => 1, 'image' => '1', 'star' => 2, 'x' => 180, 'y' => 440],
                    2 => ['name' => '虎牢锐卒', 'type' => 1, 'head' => 2, 'color' => 1, 'image' => '2', 'star' => 2, 'x' => 320, 'y' => 360],
                    3 => ['name' => '张济',     'type' => 2, 'head' => 3, 'color' => 2, 'image' => '25', 'star' => 3, 'x' => 460, 'y' => 290],
                    4 => ['name' => '高顺',     'type' => 2, 'head' => 4, 'color' => 2, 'image' => '26', 'star' => 4, 'x' => 600, 'y' => 210],
                    5 => ['name' => '飞将·吕布', 'type' => 3, 'head' => 5, 'color' => 3, 'image' => '27', 'star' => 5, 'x' => 740, 'y' => 140],
                ],
                3 => [ // 洛阳
                    1 => ['name' => '西凉残部', 'type' => 1, 'head' => 1, 'color' => 1, 'image' => '1', 'star' => 2, 'x' => 180, 'y' => 440],
                    2 => ['name' => '禁军死士', 'type' => 1, 'head' => 2, 'color' => 1, 'image' => '2', 'star' => 2, 'x' => 320, 'y' => 360],
                    3 => ['name' => '郭汜',     'type' => 2, 'head' => 3, 'color' => 2, 'image' => '28', 'star' => 3, 'x' => 460, 'y' => 290],
                    4 => ['name' => '李傕',     'type' => 2, 'head' => 4, 'color' => 2, 'image' => '29', 'star' => 3, 'x' => 600, 'y' => 210],
                    5 => ['name' => '毒士·李儒', 'type' => 3, 'head' => 5, 'color' => 3, 'image' => '30', 'star' => 4, 'x' => 740, 'y' => 140],
                ],
                4 => [ // 郿坞
                    1 => ['name' => '郿坞精锐', 'type' => 1, 'head' => 1, 'color' => 1, 'image' => '1', 'star' => 2, 'x' => 180, 'y' => 440],
                    2 => ['name' => '相府卫队', 'type' => 1, 'head' => 2, 'color' => 1, 'image' => '2', 'star' => 3, 'x' => 320, 'y' => 360],
                    3 => ['name' => '董旻',     'type' => 2, 'head' => 3, 'color' => 2, 'image' => '31', 'star' => 3, 'x' => 460, 'y' => 290],
                    4 => ['name' => '牛辅',     'type' => 2, 'head' => 4, 'color' => 2, 'image' => '32', 'star' => 4, 'x' => 600, 'y' => 210],
                    5 => ['name' => '太师·董卓', 'type' => 3, 'head' => 5, 'color' => 3, 'image' => '33', 'star' => 5, 'x' => 740, 'y' => 140],
                ],
            ],
            // 势力 3：群雄割据
            3 => [
                1 => [ // 界桥
                    1 => ['name' => '幽州步骑', 'type' => 1, 'head' => 1, 'color' => 1, 'image' => '1', 'star' => 2, 'x' => 180, 'y' => 440],
                    2 => ['name' => '白马义从', 'type' => 1, 'head' => 2, 'color' => 1, 'image' => '2', 'star' => 2, 'x' => 320, 'y' => 360],
                    3 => ['name' => '严纲',     'type' => 2, 'head' => 3, 'color' => 2, 'image' => '34', 'star' => 3, 'x' => 460, 'y' => 290],
                    4 => ['name' => '田楷',     'type' => 2, 'head' => 4, 'color' => 2, 'image' => '35', 'star' => 3, 'x' => 600, 'y' => 210],
                    5 => ['name' => '白马将军·公孙瓒', 'type' => 3, 'head' => 5, 'color' => 3, 'image' => '36', 'star' => 4, 'x' => 740, 'y' => 140],
                ],
                2 => [ // 官渡
                    1 => ['name' => '大戟精兵', 'type' => 1, 'head' => 1, 'color' => 1, 'image' => '1', 'star' => 2, 'x' => 180, 'y' => 440],
                    2 => ['name' => '河北强弩', 'type' => 1, 'head' => 2, 'color' => 1, 'image' => '2', 'star' => 3, 'x' => 320, 'y' => 360],
                    3 => ['name' => '颜良',     'type' => 2, 'head' => 3, 'color' => 2, 'image' => '37', 'star' => 4, 'x' => 460, 'y' => 290],
                    4 => ['name' => '文丑',     'type' => 2, 'head' => 4, 'color' => 2, 'image' => '38', 'star' => 4, 'x' => 600, 'y' => 210],
                    5 => ['name' => '河北霸主·袁绍', 'type' => 3, 'head' => 5, 'color' => 3, 'image' => '39', 'star' => 5, 'x' => 740, 'y' => 140],
                ],
            ],
        ];

        $item = $roster[$level][$stage][$missionId] ?? null;
        if ($item === null) {
            $titles = [1 => '前锋营', 2 => '精锐卫', 3 => '统领', 4 => '主将', 5 => '大帅'];
            $title = $titles[$missionId] ?? "守军{$missionId}";
            $item = [
                'name' => "第{$level}章·{$title}",
                'type' => ($missionId >= 4 ? 3 : ($missionId >= 3 ? 2 : 1)),
                'head' => $missionId,
                'color' => min(3, max(1, $missionId - 1)),
                'image' => (string)(20 + $missionId),
                'star' => min(5, max(1, $missionId)),
                'x' => 180 + ($missionId - 1) * 140,
                'y' => 440 - ($missionId - 1) * 75,
            ];
        }

        // 精准统一等级与三维数值
        $baseLevel = ($level - 1) * 20 + ($stage - 1) * 5 + $missionId * 2;
        $type = (int)($item['type'] ?? 1);

        if ($type === 3) { // 关卡终极大Boss
            $hp = 1800 + $baseLevel * 100;
            $atk = 220 + $baseLevel * 18;
            $def = 100 + $baseLevel * 10;
        } elseif ($type === 2) { // 关卡精英守将
            $hp = 1200 + $baseLevel * 80;
            $atk = 180 + $baseLevel * 15;
            $def = 80 + $baseLevel * 8;
        } else { // 前哨小怪
            $hp = 800 + $baseLevel * 60;
            $atk = 140 + $baseLevel * 12;
            $def = 60 + $baseLevel * 6;
        }

        $power = (int)round($hp * 0.15 + $atk * 2.0 + $def * 1.5);

        return array_merge($item, [
            'level' => $baseLevel,
            'hp' => $hp,
            'atk' => $atk,
            'def' => $def,
            'power' => $power,
        ]);
    }

    /**
     * 关卡内怪物据点列表: c=map&m=get_mission_list
     */
    public static function get_mission_list(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $level = (int)($ctx->input('l', 1));
        $stage = (int)($ctx->input('s', 1));

        $member = DB::table('member')->where('uid', $uid)->first();
        $curLevel = max(1, (int)($member['missionlevel'] ?? 1));
        $curStage = max(1, (int)($member['missionstage'] ?? 1));
        $curMissionId = max(1, (int)($member['missionid'] ?? 1));

        $list = [];
        $missionItems = [];
        for ($mid = 1; $mid <= 5; $mid++) {
            $cfg = self::getEnemyConfig($level, $stage, $mid);

            // 计算据点解锁与通关状态 (结合 level, stage, mid 全维度单向防回退)
            if ($level < $curLevel || ($level === $curLevel && $stage < $curStage)) {
                // 已打通的旧关卡：全部据点已击败并开放自由扫荡
                $openstatus = 1;
                $status = 1;
            } elseif ($level === $curLevel && $stage === $curStage) {
                if ($mid < $curMissionId) {
                    $openstatus = 1;
                    $status = 1; // 已打通
                } elseif ($mid === $curMissionId) {
                    $openstatus = 1;
                    $status = 0; // 当前挑战前线据点
                } else {
                    $openstatus = 0; // 未解锁
                    $status = 0;
                }
            } else {
                $openstatus = 0;
                $status = 0;
            }

            $item = [
                'id' => $mid,
                'missionid' => $mid,
                'name' => $cfg['name'],
                'head' => $cfg['head'],
                'color' => $cfg['color'],
                'type' => $cfg['type'],
                'x' => $cfg['x'],
                'y' => $cfg['y'],
                'openstatus' => $openstatus,
                'status' => $status,
                'maxtimes' => 5,
                'coin' => 0,
                'power' => $cfg['power'],
            ];
            $list[(string)$mid] = $item;
            $missionItems[] = $item;
        }

        $hasNext = ($level < $curLevel) || ($level === $curLevel && $curStage > $stage);

        return [
            'status' => 1,
            'popo' => 0,
            'next_status' => $hasNext ? 1 : 0,
            'list' => $list,
            'missions' => $missionItems, // 语义化据点列表
        ];
    }

    /**
     * 据点敌人详细信息与出战确认: c=map&m=mission
     */
    public static function mission(Context $ctx): array
    {
        $level = (int)($ctx->input('l', 1));
        $stage = (int)($ctx->input('s', 1));
        $id = (int)($ctx->input('id', 1));

        $enemyCfg = self::getEnemyConfig($level, $stage, $id);
        $enemyName = (string)$enemyCfg['name'];
        $type = (int)$enemyCfg['type'];

        return [
            'status' => 1,
            'info' => [
                'id' => $id,
                'missionlevel' => $level,
                'missionstage' => $stage,
                'missionid' => $id,
                'level' => $enemyCfg['level'],
                'power' => $enemyCfg['power'],
                'name' => $enemyName,
                'type' => $type,
                'image' => (int)($enemyCfg['image'] ?? 0),
                'recruitrate' => 0,
                'generalid' => 0,
                'background' => 1,
                'first' => 1,
                'firstkillexp' => 150 + $enemyCfg['level'] * 20,
                'exp' => 100 + $enemyCfg['level'] * 10,
                'gfirstkillexp' => 200 + $enemyCfg['level'] * 25,
                'gexp' => 150 + $enemyCfg['level'] * 15,
                'maxtimes' => 5,
                'nowmaxtimes' => 5,
                'coin' => 0,
                'gettype' => 1,
                'getrate' => 100,
                'getvalue' => 1,
                'add_type' => 1,
                'five_sub' => 0,
                'ten_sub' => 0,
                'fifty_sub' => 0,
                'hundred_sub' => 0,
            ],
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

        // 1. 构建我方出战英雄 (支持多武将战位、装备穿戴加成与阵法加成)
        $matrix = DB::table('matrix')->where('uid', $uid)->first();
        $matrixMid = (int)($matrix['mid'] ?? 1);
        $matrixLevel = max(1, (int)($matrix['level'] ?? 1));
        $matrixAddition = 1.0 + 0.05 + ($matrixLevel - 1) * 0.01;

        $generals = DB::table('general')->where('uid', $uid)->where('pos', '>', 0)->get();
        if (empty($generals)) {
            $generals = DB::table('general')->where('uid', $uid)->get();
        }

        $leftHeroes = [];
        $leftTeamData = [];
        $leftHeroImage = (string)($member['gender'] ?? 1);

        foreach ($generals as $g) {
            $gid = (int)$g['id'];
            $posInt = (int)$g['pos'] > 0 ? (int)$g['pos'] : 2;
            $pos = Position::tryFrom($posInt) ?? Position::FrontMiddle;

            $equipped = DB::table('equipment')->where('uid', $uid)->where('generalid', $gid)->get();
            $stats = GeneralHandler::calculateGeneralStats($g, $equipped);

            // 应用阵法加成
            $finalAtk = ($matrixMid === 1) ? (int)round($stats['attack'] * $matrixAddition) : $stats['attack'];
            $finalDef = ($matrixMid === 3) ? (int)round($stats['defense'] * $matrixAddition) : $stats['defense'];

            $heroName = $g['name'] ?: $nickname;
            $leftHeroes[] = new Hero($gid, $heroName, $pos, $stats['hp'], $stats['hp'], $finalAtk, $finalDef, $stats['speed']);

            $leftTeamData[$posInt] = [
                'heroid' => (string)$gid,
                'name' => $heroName,
                'herotype' => '1',
                'level' => (string)$stats['level'],
                'allblood' => $stats['hp'],
                'blood' => $stats['hp'],
                'power' => 50,
                'image' => (int)$g['generalid'] === 1 ? $leftHeroImage : (string)((int)$g['generalid'] % 20 + 10),
                'star' => 1,
                'talent' => '坚强',
                'skill' => '会心一击',
            ];
        }

        if (empty($leftHeroes)) {
            $leftHeroes[] = new Hero(1, $nickname, Position::FrontMiddle, 1500, 1500, 320, 120, 150);
            $leftTeamData[2] = [
                'heroid' => '1',
                'name' => $nickname,
                'herotype' => '1',
                'level' => (string)($member['level'] ?? 1),
                'allblood' => 1500,
                'blood' => 1500,
                'power' => 50,
                'image' => $leftHeroImage,
                'star' => 1,
                'talent' => '坚强',
                'skill' => '会心一击',
            ];
        }

        // 2. 构建敌方据点守军
        $enemyCfg = self::getEnemyConfig($level, $stage, $missionId);
        $enemyHeroName = (string)$enemyCfg['name'];
        $enemyHeroImage = (string)($enemyCfg['image'] ?? '27');
        $enemyStar = (int)($enemyCfg['star'] ?? 1);

        $enemyHp = 800 + $missionId * 150 + ($level - 1) * 300;
        $enemyAtk = 180 + $missionId * 30 + ($level - 1) * 50;
        $rightHeroes = [
            new Hero(100 + $missionId, $enemyHeroName, Position::FrontMiddle, $enemyHp, $enemyHp, $enemyAtk, 80, 100),
        ];

        // 3. 调用确定性纯内存九宫格推演引擎
        $engine = new BattleEngine($leftHeroes, $rightHeroes);
        $simResult = $engine->simulate();

        // 4. 组装符合原版 battleworld.js 的战斗回放结构
        $rightTeamData = [
            2 => [
                'heroid' => (string)(100 + $missionId),
                'name' => $enemyHeroName,
                'herotype' => (string)($enemyCfg['type'] ?? 1),
                'level' => (string)max(1, $missionId * 2),
                'allblood' => $enemyHp,
                'blood' => $enemyHp,
                'power' => 0,
                'image' => $enemyHeroImage,
                'star' => $enemyStar,
                'talent' => '坚强',
                'skill' => '会心一击',
            ],
        ];

        $battleReport = [
            'leftteam' => $leftTeamData,
            'rightteam' => $rightTeamData,
            'leftresult' => $simResult['win'] ? $leftTeamData : [],
            'rightresult' => $simResult['win'] ? [] : $rightTeamData,
            'process' => self::buildBattleProcess($simResult['logs']),
            'assess' => (int)($simResult['assess'] ?? ($simResult['win'] ? 3 : -3)),
            'leftbuff' => ['mingzhong' => 0, 'jingzhun' => 0, 'kangyun' => 0],
            'rightbuff' => ['mingzhong' => 0, 'jingzhun' => 0, 'kangyun' => 0],
        ];

        // 5. 战斗胜利关卡推进、升级与经验结算
        $levelUp = 0;
        $isWin = $simResult['win'];
        $isLastMission = ($missionId >= 5); // 5号Boss是本关最终守关将领
        $nextStage = $stage;
        $nextMissionId = $missionId;
        $openstatus = 0;

        if ($member !== null) {
            $newExp = (int)$member['exp'] + 100;
            $curLevel = (int)$member['level'];
            if ($newExp >= (int)$member['maxexp']) {
                $curLevel++;
                $newExp = 0;
                $levelUp = 1;
            }

            $curMemberLevel = max(1, (int)($member['missionlevel'] ?? 1));
            $curMemberStage = max(1, (int)($member['missionstage'] ?? 1));
            $curMemberMission = max(1, (int)($member['missionid'] ?? 1));
            $maxStageForLevel = 4; // 每个势力共有 4 个大关卡场景

            $nextLevel = $curMemberLevel;
            $nextStage = $curMemberStage;
            $nextMissionId = $curMemberMission;
            $nextSite = 3;
            $openstatus = 0;

            if ($isWin) {
                // 只有当玩家挑战的是“当前最高前线据点”时，才单向推进全局历史最高进度！
                $isFrontier = ($level === $curMemberLevel && $stage === $curMemberStage && $missionId === $curMemberMission);

                if ($isFrontier) {
                    if ($isLastMission) {
                        if ($stage >= $maxStageForLevel) {
                            // 打败势力最终Boss，整势力彻底通关！开启下一新势力
                            $openstatus = 2;
                            $nextLevel = $curMemberLevel + 1;
                            $nextStage = 1;
                            $nextMissionId = 1;
                            $nextSite = 1; // 回到世界大地图展现新势力开启
                        } else {
                            // 打败守关Boss，当前小关卡通关！开启下一大关场景
                            $openstatus = 1;
                            $nextStage = $curMemberStage + 1;
                            $nextMissionId = 1;
                            $nextSite = 2; // 回到势力场景地图展现新开启关卡
                        }
                    } else {
                        // 普通据点通关，推进到下一个据点
                        $openstatus = 0;
                        $nextStage = $curMemberStage;
                        $nextMissionId = $missionId + 1;
                        $nextSite = 3; // 保持在关卡小地图现场
                    }
                } else {
                    // 回头重刷旧关卡：保持最高历史进度绝不倒退回滚！
                    $openstatus = 0;
                    $nextSite = 3;
                }

                // 推进玩家进度
                DB::table('member')->where('uid', $uid)->update([
                    'act' => max(0, (int)$member['act'] - 1),
                    'silver' => (int)$member['silver'] + 500,
                    'exp' => $newExp,
                    'level' => $curLevel,
                    'missionsite' => $nextSite,
                    'missionlevel' => $nextLevel,
                    'missionstage' => $nextStage,
                    'missionid' => $nextMissionId,
                ]);

                // 记录关卡进度到 zc_sanguo_map 表
                $existMap = DB::table('map')->where('uid', $uid)->where('level', $level)->where('stage', $stage)->first();
                if ($existMap !== null) {
                    DB::table('map')->where('uid', $uid)->where('level', $level)->where('stage', $stage)->update([
                        'mission' => max((int)$existMap['mission'], $missionId),
                        'dateline' => time(),
                    ]);
                } else {
                    DB::table('map')->insert([
                        'uid' => $uid,
                        'level' => $level,
                        'stage' => $stage,
                        'mission' => $missionId,
                        'dateline' => time(),
                    ]);
                    // 首通据点奖励入库
                    $dropEquipId = match ($missionId) {
                        1 => 101,
                        2 => 102,
                        3 => 103,
                        default => 0,
                    };
                    $dropEquipName = match ($missionId) {
                        1 => '青铜剑',
                        2 => '皮甲',
                        3 => '行军披风',
                        default => '',
                    };
                    $dropEquipType = match ($missionId) {
                        1 => 1,
                        2 => 2,
                        3 => 3,
                        default => 0,
                    };
                    if ($dropEquipId > 0) {
                        DB::table('equipment')->insert([
                            'uid' => $uid,
                            'equipid' => $dropEquipId,
                            'name' => $dropEquipName,
                            'type' => $dropEquipType,
                            'quality' => 1,
                            'level' => 1,
                            'generalid' => 0,
                            'number' => 1,
                            'saleprice' => 50,
                        ]);
                    }
                }
            } else {
                DB::table('member')->where('uid', $uid)->update([
                    'act' => max(0, (int)$member['act'] - 1),
                ]);
            }
            Redis::del('player:' . $uid);
        }

        return [
            'status' => 1,
            'info' => [
                'win' => $isWin ? 1 : -1,
                'exp' => 100,
                'gexp' => 150,
                'silver' => 500,
                'gettype' => 3,
                'getvalue' => 500,
                'background' => 1,
                'openstatus' => $openstatus,
                'get_level' => $level, // 势力通关标记
                'missionlevel' => $nextLevel,
                'missionstage' => $nextStage,
                'missionid' => $nextMissionId,
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
        foreach ($logs as $index => $log) {
            $isLeft = ($log['side'] === 'left');
            $process[] = [
                'round' => (int)($index / 2) + 1,
                'launch' => $isLeft ? 'left' : 'right',
                'attackor' => (int)$log['attacker_pos'],
                'attackpower' => 25,
                'skill' => 0,
                'hit' => $log['is_crit'] ? 1 : 0,
                'attacktype' => 0,
                'beattackors' => [(int)$log['target_pos']],
                'hurt' => [
                    [
                        'pos' => (int)$log['target_pos'],
                        'blood' => (int)$log['damage'],
                        'power' => 25,
                        'self' => 0,
                        'faint' => 0,
                    ],
                ],
            ];
        }
        return $process;
    }

    /**
     * 获取战役势力通关奖励列表: c=map&m=get_newreward_index
     */
    public static function get_newreward_index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $levelId = (int)($ctx->input('levelid', 1));
        if ($levelId <= 0) {
            $levelId = 1;
        }

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $curLevel = max(1, (int)($member['missionlevel'] ?? 1));
        $curStage = max(1, (int)($member['missionstage'] ?? 1));

        // 查询奖励配置
        $rewardConfigs = DB::table('mission_newreward')
            ->where('levelid', $levelId)
            ->whereNull('deleted_at')
            ->orderBy('missid', 'asc')
            ->get();

        if (empty($rewardConfigs)) {
            // 若该势力暂未配置，回退到 1 号势力配置
            $rewardConfigs = DB::table('mission_newreward')
                ->where('levelid', 1)
                ->whereNull('deleted_at')
                ->orderBy('missid', 'asc')
                ->get();
        }

        // 查询该玩家在该势力下的已领取记录
        $claimedLogs = DB::table('mission_newreward_log')
            ->where('uid', $uid)
            ->where('levelid', $levelId)
            ->whereNull('deleted_at')
            ->get();

        $claimedMap = [];
        foreach ($claimedLogs as $log) {
            $claimedMap[(int)$log['missid']] = true;
        }

        $isLevelCleared = ($curLevel > $levelId);

        $list = [];
        foreach ($rewardConfigs as $cfg) {
            $cfgId = (int)$cfg['id'];
            $missId = (int)$cfg['missid'];
            $reType = (int)$cfg['re_type'];

            // 是否已领取: 0不能领取, 1可领取, 2已领取
            if (isset($claimedMap[$missId])) {
                $isGet = 2;
            } elseif ($isLevelCleared || $curStage >= $missId) {
                $isGet = 1;
            } else {
                $isGet = 0;
            }

            $item = [
                'id' => $cfgId,
                'levelid' => $levelId,
                'missid' => $missId,
                'mname' => (string)$cfg['mname'],
                're_type' => $reType,
                're_name' => (string)$cfg['re_name'],
                're_num' => (int)$cfg['re_num'],
                're_value' => (int)$cfg['re_value'],
                'is_get' => $isGet,
                'info' => [
                    'id' => $cfgId,
                    'image' => (int)$cfg['re_value'] ?: 1,
                    'name' => (string)$cfg['re_name'],
                    'quality' => 3,
                ],
                'general' => [
                    'id' => (int)$cfg['re_value'] ?: 1,
                    'name' => (string)$cfg['re_name'],
                    'image' => (int)$cfg['re_value'] ?: 1,
                    'quality' => 3,
                ],
            ];

            $list[] = $item;
        }

        return [
            'status' => 1,
            'list' => $list,
            'message' => 'ok',
        ];
    }

    /**
     * 领取通关奖励: c=map&m=get_newreward
     */
    public static function get_newreward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)($ctx->input('id', 0));

        if ($id <= 0) {
            return ['status' => -1, 'msg' => '参数错误'];
        }

        $lockKey = "lock:newreward:{$uid}:{$id}";
        if (Redis::get($lockKey) !== null) {
            return ['status' => -3, 'msg' => '操作太频繁了，请稍候'];
        }
        Redis::set($lockKey, '1', 2);

        $reward = DB::table('mission_newreward')->where('id', $id)->whereNull('deleted_at')->first();
        if ($reward === null) {
            return ['status' => -2, 'msg' => '奖励信息不存在'];
        }

        $levelId = (int)$reward['levelid'];
        $missId = (int)$reward['missid'];

        // 验证是否已领取
        $already = DB::table('mission_newreward_log')
            ->where('uid', $uid)
            ->where('levelid', $levelId)
            ->where('missid', $missId)
            ->whereNull('deleted_at')
            ->first();

        if ($already !== null) {
            return ['status' => 3, 'msg' => '已经领取过该奖励'];
        }

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($member === null) {
            return ['status' => 0, 'msg' => '玩家数据不存在'];
        }

        // 发放奖励
        $reType = (int)$reward['re_type'];
        $reNum = (int)$reward['re_num'];
        $newGold = (int)$member['gold'];
        $newSilver = (int)$member['silver'];

        switch ($reType) {
            case 1: // 元宝
                $newGold += $reNum;
                DB::table('member')->where('uid', $uid)->update(['gold' => $newGold]);
                break;
            case 2: // 银两
                $newSilver += $reNum;
                DB::table('member')->where('uid', $uid)->update(['silver' => $newSilver]);
                break;
            case 5: // 原魂/将魂
                $newSoul = (int)($member['soul'] ?? 0) + $reNum;
                DB::table('member')->where('uid', $uid)->update(['soul' => $newSoul]);
                break;
            default:
                $newSilver += $reNum;
                DB::table('member')->where('uid', $uid)->update(['silver' => $newSilver]);
                break;
        }

        // 写入领取记录 (并发唯一键安全捕获)
        try {
            DB::table('mission_newreward_log')->insert([
                'uid' => $uid,
                'levelid' => $levelId,
                'missid' => $missId,
                'dateline' => time(),
            ]);
        } catch (\Throwable $e) {
            return ['status' => 3, 'msg' => '已经领取过该奖励'];
        }

        Redis::del('player:' . $uid);

        // 检查该势力下是否还有未领取的奖励
        $allRewards = DB::table('mission_newreward')
            ->where('levelid', $levelId)
            ->whereNull('deleted_at')
            ->get();

        $allLogs = DB::table('mission_newreward_log')
            ->where('uid', $uid)
            ->where('levelid', $levelId)
            ->whereNull('deleted_at')
            ->count();

        $hasRemaining = count($allRewards) > $allLogs;

        $rewardData = [
            'id' => $id,
            'levelid' => $levelId,
            'missid' => $missId,
            'mname' => $reward['mname'],
            're_type' => $reType,
            're_name' => $reward['re_name'],
            're_num' => $reNum,
            'all_gold' => $newGold,
            'is_get' => 2,
        ];

        return [
            'status' => 1,
            'popo' => $hasRemaining ? 1 : 0,
            'reward' => $rewardData,
            'gold' => $newGold,
            'silver' => $newSilver,
            'msg' => '领取成功',
        ];
    }

    /**
     * 领取战役整势力通关大奖: c=map&m=get_newresult
     */
    public static function get_newresult(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $levelId = (int)($ctx->input('id', $ctx->input('levelid', 1)));
        if ($levelId <= 0) {
            $levelId = 1;
        }

        $lockKey = "lock:shili_reward:{$uid}:{$levelId}";
        if (Redis::get($lockKey) !== null) {
            return ['status' => -3, 'message' => '操作太频繁了，请稍候'];
        }
        Redis::set($lockKey, '1', 2);

        $claimKey = "user_shili_claimed:{$uid}:{$levelId}";
        if (Redis::get($claimKey) !== null) {
            return ['status' => -3, 'message' => '该势力通关大奖已领取过'];
        }

        // 根据战役势力配置对应通关神兵大礼
        $rewards = [
            1 => [
                'gettype' => 1,
                'rewardnum' => 1,
                'item' => [
                    'id' => 101,
                    'equipid' => 101,
                    'name' => '百炼青钢剑',
                    'quality' => 3,
                    'type' => 1,
                    'description' => '通关黄巾之乱获得的精炼名兵，削铁如泥！',
                ],
            ],
            2 => [
                'gettype' => 1,
                'rewardnum' => 1,
                'item' => [
                    'id' => 102,
                    'equipid' => 102,
                    'name' => '西凉锁子甲',
                    'quality' => 3,
                    'type' => 2,
                    'description' => '通关董卓之乱缴获的坚固战甲，刀枪不入！',
                ],
            ],
            3 => [
                'gettype' => 1,
                'rewardnum' => 1,
                'item' => [
                    'id' => 103,
                    'equipid' => 103,
                    'name' => '修罗疾风披风',
                    'quality' => 4,
                    'type' => 3,
                    'description' => '决战虎牢关大捷封赏的宝贵披风！',
                ],
            ],
        ];

        $targetReward = $rewards[$levelId] ?? [
            'gettype' => 1,
            'rewardnum' => 1,
            'item' => [
                'id' => 104,
                'equipid' => 104,
                'name' => '天命校尉兵符',
                'quality' => 3,
                'type' => 4,
                'description' => '扫平诸侯叛军封赏的精锐兵符！',
            ],
        ];

        // 装备真实入库
        if ((int)$targetReward['gettype'] === 1 && isset($targetReward['item']['name'])) {
            $itemCfg = $targetReward['item'];
            DB::table('equipment')->insert([
                'uid' => $uid,
                'equipid' => (int)($itemCfg['equipid'] ?? 101),
                'name' => (string)$itemCfg['name'],
                'type' => (int)($itemCfg['type'] ?? 1),
                'quality' => (int)($itemCfg['quality'] ?? 3),
                'level' => 1,
                'generalid' => 0,
                'number' => 1,
                'saleprice' => 500,
            ]);
        }

        // 标记已领取
        Redis::set($claimKey, '1', 86400 * 365);
        DB::table('member_field')->where('uid', $uid)->update(['newresult' => 0]);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'gettype' => $targetReward['gettype'],
            'item' => $targetReward['item'],
            'rewardnum' => $targetReward['rewardnum'],
            'message' => '通关大礼领取成功',
        ];
    }
}
