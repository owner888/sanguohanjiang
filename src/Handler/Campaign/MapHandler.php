<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Campaign;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Equip as EquipTemplate;
use Hanjiang\MasterData\Map as MapTemplate;
use Hanjiang\MasterData\Mission as MissionTemplate;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\Member;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\TeamBuilder;
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
     * 采用强类型 Faction / FactionStatus 领域枚举，依据玩家实际历史推图进度真实计算开启与通关状态
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->first();
        $curLevel = max(1, (int)($member['missionlevel'] ?? 1));

        $factions = [];
        $res = [
            'status' => 1,
            'open' => 1,
            'enter_world' => $curLevel,
            'next_status' => 0,
            'first_enter' => 0,
        ];

        foreach (Faction::cases() as $faction) {
            $fid = $faction->value;

            if ($fid < $curLevel) {
                // 已彻底通关的旧势力
                $status = 1;
                $passStatus = 1;
            } elseif ($fid === $curLevel) {
                // 当前正在攻坚的前线势力
                $status = 1;
                $passStatus = 0;
            } else {
                // 尚未攻克解锁的未来势力
                $status = 0;
                $passStatus = 0;
            }

            $item = [
                'id' => $fid,
                'code' => $faction->code(),
                'name' => $faction->label(),
                'status' => $status,
                'status_name' => ($status === 1) ? FactionStatus::Active->label() : FactionStatus::Locked->label(),
                'pass_status' => $passStatus,
                'rewardnum' => 0,
            ];

            $factions[] = $item;
            $res[(string)$fid] = $item;
        }

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

        // 战役关卡场景 stage: 1~4 (按势力 level 严格匹配史实关卡)
        $stageNamesByLevel = [
            1 => [1 => '曲阳', 2 => '广宗', 3 => '巨鹿', 4 => '濮阳'],
            2 => [1 => '乌巢', 2 => '官渡', 3 => '壶关', 4 => '邺城'],
            3 => [1 => '蓟县', 2 => '界桥', 3 => '北平', 4 => '代郡'],
            4 => [1 => '虎牢关', 2 => '洛阳', 3 => '函谷关', 4 => '郿坞'],
        ];

        $defaultNames = $stageNamesByLevel[$level] ?? [1 => '前哨营', 2 => '先锋寨', 3 => '要塞关', 4 => '主城'];
        $stages = [];
        for ($s = 1; $s <= 4; $s++) {
            $stages[$s] = ['stage' => $s, 'name' => $defaultNames[$s] ?? "关卡{$s}"];
        }

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
        $uid = MemberHandler::resolveUid($ctx);
        $level = (int)($ctx->input('l', 1));
        $stage = (int)($ctx->input('s', 1));
        $id = (int)($ctx->input('id', 1));

        $enemyCfg = self::getEnemyConfig($level, $stage, $id);
        $enemyName = (string)$enemyCfg['name'];
        $type = (int)$enemyCfg['type'];

        // 检查该关卡是否首杀 (zc_sanguo_map 记录的 mission < 当前据点 id 则为首杀)
        $existMap = DB::table('map')->where('uid', $uid)->where('level', $level)->where('stage', $stage)->first();
        $isFirst = ($existMap === null || (int)$existMap['mission'] < $id) ? 1 : -1;

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
                'first' => $isFirst,
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

        // 关卡行（旧版 map_model->get_mission_by_id：zc_sanguo_mission 按 missionlevel / missionstage / missionid 查，查不到返回 -6）
        // 依赖未移植：get_mission_by_id 的地图记录 / 场景开启 / 关卡开启校验（-3 / -4 / -5），以及攻击 CD、强攻元宝
        $missionRow = self::missionRow($level, $stage, $missionId);
        if ($missionRow === null) {
            return ['status' => -6];
        }

        // 1. 我方队伍（旧版 battle_model->battle_mission：PVE，init_member_team_info）
        $left = TeamBuilder::forMember($uid, pve: true);
        $teamError = false;
        // 2. 守军（init_monster_team_info，Monster 表）
        $right = TeamBuilder::forMonsters($missionRow, TeamBuilder::MONSTER_MISSION);

        if ($left['status'] === -1 || $left['status'] === -3) {
            // 旧版 battle_mission 返回 -1（没有阵法）/ -3（武将信息错误）：map 控制器把 assess 记 0，按未胜利处理，battle 为空
            $battleReport = ['assess' => 0];
            $simResult = ['win' => false];
            $teamError = true;
        } else {
            // -2（阵法里没有武将）旧版按空队伍继续战斗：空队伍直接判大败（assess -3），没有战斗过程
            $leftHeroes = TeamBuilder::heroes($left['team'], $left['matrix_type'], $left['matrix_coe']);
            $rightHeroes = TeamBuilder::heroes($right['team']);

            // 3. 调用确定性纯内存九宫格推演引擎
            $engine = new BattleEngine(array_values($leftHeroes), array_values($rightHeroes));
            $simResult = $engine->simulate();

            // 4. 组装战斗回放（旧版 battle()：leftteam / rightteam / leftresult / rightresult 均为 team_return_arr）
            // 依赖未移植：process 战斗过程格式（P5 移植 process_attack）；leftbuff / rightbuff（buff_return）
            $battleReport = [
                'leftteam' => TeamBuilder::teamReturnArr($left['team']),
                'rightteam' => TeamBuilder::teamReturnArr($right['team']),
                'process' => self::buildBattleProcess($simResult['logs']),
                'assess' => (int)$simResult['assess'],
                'leftresult' => TeamBuilder::teamReturnArr($left['team'], true, $leftHeroes),
                'rightresult' => TeamBuilder::teamReturnArr($right['team'], true, $rightHeroes),
                'leftbuff' => ['mingzhong' => 0, 'jingzhun' => 0, 'kangyun' => 0],
                'rightbuff' => ['mingzhong' => 0, 'jingzhun' => 0, 'kangyun' => 0],
            ];
        }
        // 依赖未移植：经验结算仍取新版自定的 getEnemyConfig 等级公式（旧版取 Mission 行的 firstkillexp / exp / gfirstkillexp / gexp）
        $enemyCfg = self::getEnemyConfig($level, $stage, $missionId);

        // 5. 战斗胜利关卡推进、升级与经验结算
        $isWin = $simResult['win'];
        $isLastMission = ($missionId >= 5); // 5号Boss是本关最终守关将领
        $nextStage = $stage;
        $nextMissionId = $missionId;
        $openstatus = 0;
        $levelUp = 0;

        // 根据是否首通据点决定经验结算 (首杀使用 firstkillexp/gfirstkillexp，非首杀使用普通经验)
        $existMapBefore = DB::table('map')->where('uid', $uid)->where('level', $level)->where('stage', $stage)->first();
        $isFirstKill = ($existMapBefore === null || (int)$existMapBefore['mission'] < $missionId);

        $gainExp = 0;
        $gainGexp = 0;
        // 关卡掉落（旧版 controllers/map.php:action「处理概率掉落」），胜利后结算
        $drop = ['get_number' => 0, 'gettype' => 0, 'getvalue' => 0, 'itemid' => 0, 'itemname' => ''];

        if ($isWin) {
            $enemyLvl = (int)($enemyCfg['level'] ?? 1);
            if ($isFirstKill) {
                $gainExp = 150 + $enemyLvl * 20;
                $gainGexp = 200 + $enemyLvl * 25;
            } else {
                $gainExp = 100 + $enemyLvl * 10;
                $gainGexp = 150 + $enemyLvl * 15;
            }
        }

        if ($member !== null) {
            $curLevel = max(1, (int)$member['level']);
            $newExp = (int)$member['exp'] + $gainExp;
            $reqExp = Member::getLevelUpExp($curLevel);

            while ($newExp >= $reqExp && $curLevel < 120) {
                $newExp -= $reqExp;
                $curLevel++;
                $levelUp = 1;
                $reqExp = Member::getLevelUpExp($curLevel);
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
                    'exp' => $newExp,
                    'maxexp' => $reqExp,
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
                }

                $drop = self::missionDrop($uid, $curLevel, $level, $stage, $missionId);
            } else {
                DB::table('member')->where('uid', $uid)->update([
                    'act' => max(0, (int)$member['act'] - 1),
                ]);
            }
            Redis::del('player:' . $uid);
        }

        $resultInfo = [
            'win' => $teamError ? 0 : ($isWin ? 1 : -1),
            'exp' => $gainExp,
            'gexp' => $gainGexp,
            'get_number' => $drop['get_number'],
            'gettype' => $drop['gettype'],
            'getvalue' => $drop['getvalue'],
            'itemid' => $drop['itemid'],
            'background' => 1,
            'openstatus' => $openstatus,
            'get_level' => $level, // 势力通关标记
            'missionlevel' => $nextLevel,
            'missionstage' => $nextStage,
            'missionid' => $nextMissionId,
            'itemname' => $drop['itemname'],
            'levelup' => $levelUp,
            'levelexp' => $newExp ?? 0,
            'levelupexp' => $reqExp ?? 80,
            'report' => 0,
        ];

        // 旧版只在掉落装备 / 卷轴时才有 itemtype、quality
        foreach (['itemtype', 'quality'] as $key) {
            if (isset($drop[$key])) {
                $resultInfo[$key] = $drop[$key];
            }
        }

        // 若主公升级，生成升级银两奖励与功能预告/功能开启数据包，驱动客户端弹出【主公升级】与【功能开启】弹窗
        if ($levelUp === 1) {
            $levelData = self::getLevelFuncData($curLevel, (int)($member['silver'] ?? 0));
            $resultInfo['levelReward'] = $levelData['levelReward'];
            $resultInfo['levelFunc'] = $levelData['levelFunc'];
        }

        return [
            'status' => 1,
            'info' => $resultInfo,
            'battle' => $battleReport,
        ];
    }

    /**
     * 胜利后的关卡概率掉落（移植旧版 controllers/map.php:action「处理概率掉落 / 添加道具」）
     *
     * 关卡取 MasterData Mission（missionlevel / missionstage / missionid，旧版 map_model::get_mission_by_id 同表）；
     * 每次攻击 mt_rand(1, 100) <= getrate 计 1 件，get_number > 0 时按 gettype 发放：
     * 1 装备（Equipment::addFromTemplate，模板取 MasterData Equip），3 银币 getvalue * 主公等级 * 件数，4 元宝 getvalue * 件数。
     * 依赖未移植：2 装备碎片（Equipment 碎片分支）、5 / 6 天赋卷轴及碎片（talent_scroll）；这三类不发放也不回报。
     * 旧版双倍掉落活动只在 first == 0 时翻倍，而 action 里 first 只会是 1 或 -1，从不生效，这里不移植。
     * 新版 action 只支持单次攻击（旧版 times 刷图未移植），攻击次数按 1。
     *
     * @return array<string, mixed>
     */
    private static function missionDrop(int $uid, int $memberLevel, int $level, int $stage, int $missionId): array
    {
        $drop = ['get_number' => 0, 'gettype' => 0, 'getvalue' => 0, 'itemid' => 0, 'itemname' => ''];

        $mission = self::missionRow($level, $stage, $missionId);
        if ($mission === null) {
            return $drop;
        }

        $number = 1;
        $getNumber = 0;
        for ($i = 0; $i < $number; $i++) {
            if (mt_rand(1, 100) <= (int)$mission['getrate']) {
                $getNumber++;
            }
        }
        if ($getNumber <= 0) {
            return $drop;
        }

        $gettype = (int)$mission['gettype'];
        $getvalue = (int)$mission['getvalue'];
        switch ($gettype) {
            case 1:
                $tpl = EquipTemplate::ROWS[$getvalue] ?? null;
                if ($tpl === null) {
                    break;
                }
                Equipment::addFromTemplate($uid, $getvalue, $getNumber);
                $drop['get_number'] = $getNumber;
                $drop['gettype'] = $gettype;
                // itemtype：1 装备、2 卷轴
                $drop['itemtype'] = 1;
                $drop['itemid'] = (int)$tpl['id'];
                $drop['quality'] = (int)$tpl['quality'];
                $drop['itemname'] = (string)$tpl['name'];
                break;
            case 3:
                $silver = $getvalue * $memberLevel * $getNumber;
                MemberHandler::addCurrency($uid, silver: $silver);
                $drop['get_number'] = $getNumber;
                $drop['gettype'] = $gettype;
                $drop['getvalue'] = $silver;
                break;
            case 4:
                $gold = $getvalue * $getNumber;
                MemberHandler::addCurrency($uid, gold: $gold);
                $drop['get_number'] = $getNumber;
                $drop['gettype'] = $gettype;
                $drop['getvalue'] = $gold;
                break;
        }
        return $drop;
    }

    /**
     * 生成主公升级奖励与下一阶段功能开启预告
     *
     * @return array{levelReward: array<string, int>, levelFunc: array<string, mixed>}
     */
    public static function getLevelFuncData(int $newLevel, int $allSilver): array
    {
        $silverReward = $newLevel * 5000;

        // 功能开启等级里程碑配置
        $milestones = [
            11 => ['mark' => 'practice', 'name' => '校场训练', 'info' => '开启校场训练位，快速提升武将经验'],
            16 => ['mark' => 'strong', 'name' => '强化', 'info' => '开启装备强化，可大幅提高装备效果'],
            21 => ['mark' => 'levy', 'name' => '征税', 'info' => '开启主城征税，源源不断获得大笔银饷'],
            26 => ['mark' => 'mine', 'name' => '演武榜', 'info' => '开启演武榜与银矿争夺，与天下豪杰切磋'],
            31 => ['mark' => 'bar', 'name' => '酒馆', 'info' => '开启酒馆名将招募，招募三国名将'],
            41 => ['mark' => 'foster', 'name' => '培养', 'info' => '开启武将洗髓培养，激发全军无限潜能'],
            51 => ['mark' => 'country', 'name' => '国家', 'info' => '开启国家系统与海外商贸，效力魏蜀吴'],
            60 => ['mark' => 'beauty', 'name' => '铜雀台', 'info' => '开启铜雀台佳人系统，红颜相伴全军增益'],
            70 => ['mark' => 'mount', 'name' => '坐骑', 'info' => '开启坐骑马厩系统，乘骑三国绝世良驹'],
            80 => ['mark' => 'gem', 'name' => '宝石', 'info' => '开启宝石镶嵌系统，属性神石战力激增'],
        ];

        // 查找当前等级是否正好解锁新功能
        $openData = ['mark' => '', 'name' => '', 'info' => ''];
        $isOpen = 0;
        if (isset($milestones[$newLevel])) {
            $isOpen = 1;
            $openData = $milestones[$newLevel];
        }

        // 查找下一个即将解锁的新功能
        $nextFunc = ['mark' => 'strong', 'name' => '强化', 'level' => 16, 'info' => '16级 开启装备强化功能'];
        foreach ($milestones as $lvl => $cfg) {
            if ($lvl > $newLevel) {
                $nextFunc = [
                    'mark' => $cfg['mark'],
                    'name' => $cfg['name'],
                    'level' => $lvl,
                    'info' => "{$lvl}级 开启{$cfg['name']}功能",
                ];
                break;
            }
        }

        $levelFunc = [
            'all_silver' => $allSilver,
            'func_mark' => $isOpen ? $openData['mark'] : $nextFunc['mark'],
            'func_name' => $isOpen ? $openData['name'] : $nextFunc['name'],
            'func_level' => $isOpen ? $newLevel : $nextFunc['level'],
            'func_info' => $isOpen ? $openData['info'] : $nextFunc['info'],
            'func_open' => $isOpen,
            'imageid' => 0,
            'open' => $openData,
        ];

        return [
            'levelReward' => ['1' => $silverReward],
            'levelFunc' => $levelFunc,
        ];
    }

    /**
     * 关卡行（旧版 map_model->get_mission_by_id 的查表部分：zc_sanguo_mission 按 missionlevel / missionstage / missionid）
     *
     * @return array<string, mixed>|null
     */
    private static function missionRow(int $level, int $stage, int $missionId): ?array
    {
        foreach (MissionTemplate::ROWS as $row) {
            if ((int)$row['missionlevel'] === $level && (int)$row['missionstage'] === $stage && (int)$row['missionid'] === $missionId) {
                return $row;
            }
        }
        return null;
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

        // 势力通关奖励取 MasterData Map（旧版 controllers/map.php:get_newresult）
        $mapRow = MapTemplate::ROWS[$levelId] ?? null;
        if ($mapRow === null || (int)$mapRow['rewardtype'] <= 0 || (int)$mapRow['reward'] <= 0) {
            return ['status' => -4, 'message' => '该势力没有通关奖励'];
        }
        if ((int)$mapRow['rewardtype'] !== 1) {
            // 依赖未移植：rewardtype 2 天赋卷轴 / 3 声望 / 4 坐骑
            return ['status' => -4, 'message' => '该势力通关奖励尚未开放'];
        }
        $itemTpl = EquipTemplate::ROWS[(int)$mapRow['reward']];
        Equipment::addFromTemplate($uid, (int)$itemTpl['id']);
        $targetReward = [
            'gettype' => 1,
            'rewardnum' => (int)$mapRow['rewardnum'],
            'item' => $itemTpl,
        ];

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
