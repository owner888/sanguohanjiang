<?php

declare(strict_types=1);

namespace Hanjiang\Battle;

use Random\Randomizer;
use RuntimeException;

/**
 * 团战（逐段移植旧版 models/battle_model.php，路径相对旧版 s1/application/）
 *
 * - run                ← battle_rush()：三路对战，后备人员补位，连胜下阵，最后场上剩余人员对位决胜
 * - initRushMonsterInfo ← init_rush_monster_info()；initRushMemberInfo ← init_rush_member_info()
 * - rushResultToBattle ← rush_result_to_battle()
 * - saveMatrix / resumeMatrix ← save_matrix() / resume_matrix()
 * - rushTeamReturn     ← rush_team_return()；rushReport ← rush_report()
 *
 * 只移植 PVE 两种（$type 1 国战 PVE 怪物 country_monster、$type 2 多人副本怪物 elite_monster，右侧为怪物）；
 * 依赖未移植：$type 0（PVP，右侧为玩家、双方阵法）/ $type 3（EVP，左侧为怪物），调用时抛异常。
 *
 * 旧版整场团战共用一个 battle_model 实例：阵法（_left_matrix_* / _right_matrix_*）是实例属性，
 * init_member_team_info 写左侧、resume_matrix 按场改写，没被改写的一侧沿用上一场的值；单场 battle() 也不重置
 * 总血量累加等状态。这里同样整场共用一个 BattleEngine，并在每场前把当前阵法状态 setMatrix 进去。
 */
final class BattleRush
{
    /** 团战最大连胜次数（_max_win_limit；多人副本 $type 2 为 10） */
    private int $maxWinLimit = 6;

    /** @var array{left: array{type: int, coe: float}, right: array{type: int, coe: float}} 旧版 _left_matrix_* / _right_matrix_* */
    private array $matrix = [
        'left' => ['type' => 0, 'coe' => 0.0],
        'right' => ['type' => 0, 'coe' => 0.0],
    ];

    private readonly BattleEngine $engine;

    /**
     * @param int $selfUid 当前请求的玩家（旧版 $this->member['uid']）：init_member_team_info 的 uid 为 0 时取本人、6 星战斗特效只对本人生效
     */
    public function __construct(private readonly int $selfUid, ?Randomizer $rng = null)
    {
        $this->engine = new BattleEngine(rng: $rng);
    }

    /**
     * 团战（移植 battle_rush）
     *
     * @param list<int|string> $atkUidArr 攻击方玩家 uid（按出场顺序）
     * @param list<array<string, mixed>> $defUidArr PVE：防守方关卡行（id + position1~9）
     * @param int $type 1 国战 PVE；2 多人副本
     * @return array{win: int, battle_ready: array<int, array<string, mixed>>, battle_log: list<array<int, array<string, mixed>>>, general_arr: array<int|string, list<int>>, kill_rank: array{left: array<int|string, int>, right: array<int|string, int>}}
     */
    public function run(array $atkUidArr, array $defUidArr, int $type): array
    {
        if ($type !== 1 && $type !== 2) {
            throw new RuntimeException("依赖未移植：battle_rush type {$type}（只移植了 PVE 1 / 2）");
        }

        // 处理PVE输入值
        if ($type === 2) {
            $this->maxWinLimit = 10;
        }
        // 防御方阵法数组：遍历输入参数，整理数据结构，将防御方怪物id填充到 $defUidArr
        $defMatrixArr = $tempDefUidArr = [];
        foreach ($defUidArr as $value) {
            $defMatrixArr[$value['id']] = $value;
            $tempDefUidArr[] = $value['id'];
        }
        $defUidArr = $tempDefUidArr;
        unset($tempDefUidArr);

        // ********************************* 初始化战场数据 **********************************
        $battlefield = $battleReady = $returnGeneralArr = [];
        $killRank = ['left' => [], 'right' => []];
        for ($i = 1; $i <= 3; $i++) {
            $battlefield[$i]['left'] = $battlefield[$i]['right'] = [];
            // 攻击队员上场
            $attackUid = array_shift($atkUidArr);
            if ($attackUid != null) {
                $this->initRushMemberInfo($battlefield[$i], 'left', $attackUid);

                if ($type === 2) {
                    // 多人副本返回参与战斗的将领id
                    $returnGeneralArr[$attackUid] = [];
                    foreach ($battlefield[$i]['left']['team'] as $value) {
                        $returnGeneralArr[$attackUid][] = (int)$value['id'];
                    }
                }
                // 获取战斗准备返回值
                $battleReady[$i]['left'] = self::rushTeamReturn($battlefield[$i]['left']['team']);
                $battleReady[$i]['left']['uid'] = $attackUid;
            }
            // 防御队员上场
            $defenseUid = array_shift($defUidArr);
            if ($defenseUid != null) {
                $this->initRushMonsterInfo($battlefield[$i], 'right', $defenseUid, $defMatrixArr[$defenseUid], $type);

                $battleReady[$i]['right'] = self::rushTeamReturn($battlefield[$i]['right']['team']);
                $battleReady[$i]['right']['uid'] = $defenseUid;
            }
        }
        // 战斗记录
        $battleLog = [];
        // 一场战斗结果 第N组 => 1胜 2负 3平
        $pkResult = [1 => 0, 2 => 0, 3 => 0];
        // 各组战斗状态 第N组 => 0 可以战斗、1 左方胜出、2 右方胜出、3同归于尽
        $pkReady = [1 => 0, 2 => 0, 3 => 0];
        $lastWin = 0;

        // ********************************* 第一阶段 有后备人员 **********************************
        do {
            $report = [];
            // 无法继续pk的组数
            $pkBreak = 0;
            foreach ($battlefield as $key => $value) {
                // 判断交战双方是否有人
                if (empty($value['right'])) {
                    $pkReady[$key] = 1;
                    if (empty($value['left'])) {
                        $pkReady[$key] = 3;
                    }
                    $pkBreak++;
                    continue;
                } elseif (empty($value['left'])) {
                    $pkReady[$key] = 2;
                    $pkBreak++;
                    continue;
                }

                if ($pkResult[$key] == 1) {
                    // 攻方胜利，守方先手。PVE：右方怪物没有阵法参数，左方玩家的阵法恢复到右侧
                    $this->resumeMatrix('right', $battlefield[$key]['left']);
                    $result = $this->battle($value['right']['team'], $value['left']['team']);
                    $this->rushResultToBattle($battlefield[$key], $result, true);
                    $theReport = self::rushReport($result, true);
                    // 右方先手时，战斗结果反转（按左方先手返回）
                    $theReport['assess'] = 0 - (int)$theReport['assess'];
                } else {
                    // 平局 或 守方胜利 攻方先手
                    $this->resumeMatrix('left', $battlefield[$key]['left']);
                    $result = $this->battle($value['left']['team'], $value['right']['team']);
                    $this->rushResultToBattle($battlefield[$key], $result);
                    $theReport = self::rushReport($result);
                }

                // 填充 uid 和 血量
                self::fillReport($theReport, $battlefield[$key]);
                unset($result);

                // 清理战场
                if ($theReport['assess'] > 0) {
                    // 攻击方胜利
                    $pkResult[$key] = 1;
                    $battlefield[$key]['right'] = [];
                    $battlefield[$key]['left']['win']++;
                    if ($battlefield[$key]['left']['win']) {
                        // 攻防杀敌榜
                        $killRank['left'][$battlefield[$key]['left']['uid']] = $battlefield[$key]['left']['win'];
                    }
                    if ($battlefield[$key]['left']['win'] >= $this->maxWinLimit) {
                        // 连胜下阵
                        $killRank['left'][$battlefield[$key]['left']['uid']] = $battlefield[$key]['left']['win'];
                        $battlefield[$key]['left'] = [];
                        $theReport['left_win'] = $this->maxWinLimit;
                        // 记录平局
                        $pkResult[$key] = 3;
                    }
                } elseif ($theReport['assess'] < 0) {
                    // 防御方胜利
                    $pkResult[$key] = 2;
                    $battlefield[$key]['left'] = [];
                    $battlefield[$key]['right']['win']++;
                    if ($battlefield[$key]['right']['win']) {
                        // 守方杀敌榜
                        $killRank['right'][$battlefield[$key]['right']['uid']] = $battlefield[$key]['right']['win'];
                    }
                    if ($battlefield[$key]['right']['win'] >= $this->maxWinLimit) {
                        $killRank['right'][$battlefield[$key]['right']['uid']] = $battlefield[$key]['right']['win'];
                        $battlefield[$key]['right'] = [];
                        $theReport['right_win'] = $this->maxWinLimit;
                        $pkResult[$key] = 3;
                    }
                } else {
                    // 平局
                    $pkResult[$key] = 3;
                    $battlefield[$key]['left'] = $battlefield[$key]['right'] = [];
                }

                // 战报返回连胜数
                if (!isset($theReport['left_win'])) {
                    $theReport['left_win'] = empty($battlefield[$key]['left']) ? 0 : $battlefield[$key]['left']['win'];
                }
                if (!isset($theReport['right_win'])) {
                    $theReport['right_win'] = empty($battlefield[$key]['right']) ? 0 : $battlefield[$key]['right']['win'];
                }

                // 单场战报加入到战场战报
                $report[$key] = $theReport;
            }
            // 战场战报合并到返回的战报中
            if (!empty($report)) {
                $battleLog[] = $report;
            }

            // 补人
            foreach ($battlefield as $key => $value) {
                // 左方没人且后备人员不为空时补人
                if (empty($value['left']) && !empty($atkUidArr)) {
                    $attackUid = array_shift($atkUidArr);
                    if ($attackUid != null) {
                        $this->initRushMemberInfo($battlefield[$key], 'left', $attackUid);
                    }
                } elseif ($pkReady[$key] == 0) {
                    $pkReady[$key] = 2;
                }
                // 右方没人且后备人员不为空时补人
                if (empty($value['right']) && !empty($defUidArr)) {
                    $defenseUid = array_shift($defUidArr);
                    if ($defenseUid != null) {
                        $this->initRushMonsterInfo($battlefield[$key], 'right', $defenseUid, $defMatrixArr[$defenseUid], $type);
                    }
                } elseif ($pkReady[$key] == 0) {
                    $pkReady[$key] = 1;
                }
            }
            // 场上各组都有人的情况下 停止战斗的组少于3组 循环第一阶段处理
        } while (count($battlefield) == 3 && $pkBreak < 3);

        // ********************************* 第二阶段 只剩场上人员 **********************************
        // 整场战斗结果 1 攻击方胜利 2 防御方胜利 3 平局算防御方胜利
        $win = 0;
        do {
            $leftWin = $rightWin = 0;
            $report = [];
            foreach ($pkReady as $key => $value) {
                if ($value == 1) {
                    // 左方有人，记录所在组
                    $leftWin = $key;
                } elseif ($value == 2) {
                    // 右方有人，记录所在组
                    $rightWin = $key;
                }
            }

            if ($leftWin && $rightWin) {
                // 双方都有人胜出，进攻方与防守方对位，继续战斗
                $battlefield[$rightWin]['left'] = $battlefield[$leftWin]['left'];
                $battlefield[$leftWin]['left'] = [];
                $pkReady[$leftWin] = 3;
                $pkReady[$rightWin] = 0;

                // 攻方先手：恢复左方阵型
                $this->resumeMatrix('left', $battlefield[$rightWin]['left']);
                $result = $this->battle($battlefield[$rightWin]['left']['team'], $battlefield[$rightWin]['right']['team']);
                $this->rushResultToBattle($battlefield[$rightWin], $result);
                $theReport = self::rushReport($result);

                self::fillReport($theReport, $battlefield[$rightWin]);

                // 清理战场
                if ($theReport['assess'] > 0) {
                    // 攻击方胜利
                    $pkReady[$rightWin] = 1;
                    $battlefield[$rightWin]['right'] = [];
                    $battlefield[$rightWin]['left']['win']++;
                    if ($battlefield[$rightWin]['left']['win']) {
                        $killRank['left'][$battlefield[$rightWin]['left']['uid']] = $battlefield[$rightWin]['left']['win'];
                    }
                    if ($battlefield[$rightWin]['left']['win'] >= $this->maxWinLimit) {
                        $killRank['left'][$battlefield[$rightWin]['left']['uid']] = $battlefield[$rightWin]['left']['win'];
                        $battlefield[$rightWin]['left'] = [];
                        $theReport['left_win'] = $this->maxWinLimit;
                        // 记录平局 和 最后一场战斗胜利方
                        $lastWin = 1;
                        $pkReady[$rightWin] = 3;
                    }
                } elseif ($theReport['assess'] < 0) {
                    // 防御方胜利
                    $pkReady[$rightWin] = 2;
                    $battlefield[$rightWin]['left'] = [];
                    $battlefield[$rightWin]['right']['win']++;
                    if ($battlefield[$rightWin]['right']['win']) {
                        $killRank['right'][$battlefield[$rightWin]['right']['uid']] = $battlefield[$rightWin]['right']['win'];
                    }
                    if ($battlefield[$rightWin]['right']['win'] >= $this->maxWinLimit) {
                        $killRank['right'][$battlefield[$rightWin]['right']['uid']] = $battlefield[$rightWin]['right']['win'];
                        $battlefield[$rightWin]['right'] = [];
                        $theReport['right_win'] = $this->maxWinLimit;
                        $lastWin = 2;
                        $pkReady[$rightWin] = 3;
                    }
                } else {
                    // 平局
                    $lastWin = 0;
                    $pkReady[$rightWin] = 3;
                    $battlefield[$rightWin]['left'] = $battlefield[$rightWin]['right'] = [];
                }

                // 战报返回连胜数
                if (!isset($theReport['left_win'])) {
                    $theReport['left_win'] = empty($battlefield[$rightWin]['left']) ? 0 : $battlefield[$rightWin]['left']['win'];
                }
                if (!isset($theReport['right_win'])) {
                    $theReport['right_win'] = empty($battlefield[$rightWin]['right']) ? 0 : $battlefield[$rightWin]['right']['win'];
                }

                // 清空战斗组
                $report[$leftWin] = ['status' => 1];
                $report[$rightWin] = $theReport;

                // 记录战报
                $battleLog[] = $report;
            }
            // 战斗结果判定
            if (!in_array(2, $pkReady) && in_array(1, $pkReady)) {
                // 攻击方胜利
                $win = 1;
            } elseif (!in_array(1, $pkReady) && in_array(2, $pkReady)) {
                // 防御方胜利
                $win = 2;
            } elseif (!in_array(1, $pkReady) && !in_array(2, $pkReady)) {
                // 左右都没人 平局按最后一局判定胜负；最后一局平局算防御方胜利
                $win = $lastWin == 1 ? 1 : 2;
            }
        } while (!$win);

        arsort($killRank['left']);
        arsort($killRank['right']);

        return [
            'win' => $win,
            'battle_ready' => $battleReady,
            'battle_log' => $battleLog,
            'general_arr' => $returnGeneralArr,
            'kill_rank' => $killRank,
        ];
    }

    /**
     * 单场战斗：把当前阵法状态带进共用的 BattleEngine
     *
     * @param array<int, array<string, mixed>> $teamLeft
     * @param array<int, array<string, mixed>> $teamRight
     * @return array<string, mixed>
     */
    private function battle(array $teamLeft, array $teamRight): array
    {
        $this->engine->setMatrix('left', $this->matrix['left']['type'], $this->matrix['left']['coe']);
        $this->engine->setMatrix('right', $this->matrix['right']['type'], $this->matrix['right']['coe']);
        return $this->engine->battle($teamLeft, $teamRight);
    }

    /**
     * 初始化团战怪物信息（移植 init_rush_monster_info）
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $matrixInfo
     */
    private function initRushMonsterInfo(array &$data, string $side, mixed $id, array $matrixInfo, int $type): void
    {
        $monsterType = match ($type) {
            1 => TeamBuilder::MONSTER_COUNTRY,
            2 => TeamBuilder::MONSTER_ELITE,
        };
        // 旧版直接调 init_monster_team_info，不经 battle_mission，怪物血量不乘天赋血量系数
        $result = TeamBuilder::forMonsters($matrixInfo, $monsterType, missionBlood: false);
        // 连胜纪录清零
        $data[$side]['win'] = 0;
        $data[$side]['uid'] = $id;
        // 保存队伍信息
        $data[$side]['team'] = $result['team'];
    }

    /**
     * 初始化团战玩家信息（移植 init_rush_member_info → init_member_team_info($uid, 0)）
     *
     * init_member_team_info 在没有阵法（-1）时提前返回、不改阵法属性；其余情况把阵法写到左侧（$defense 0）。
     *
     * @param array<string, mixed> $data
     */
    private function initRushMemberInfo(array &$data, string $side, mixed $uid): void
    {
        // 旧版 init_member_team_info：uid 为 0 时取当前玩家
        $memberUid = (int)$uid === 0 ? $this->selfUid : (int)$uid;
        $result = TeamBuilder::forMember($memberUid, defense: false, pve: true, self: $memberUid === $this->selfUid);
        if ($result['status'] !== -1) {
            $this->matrix['left'] = ['type' => (int)$result['matrix_type'], 'coe' => (float)$result['matrix_coe']];
        }
        // 连胜纪录清零
        $data[$side]['win'] = 0;
        $data[$side]['uid'] = $uid;
        $data[$side]['allblood'] = 0;
        // 保存守方阵法属性 和 队伍信息
        $this->saveMatrix($side, $data[$side]);
        $data[$side]['team'] = $result['team'];
    }

    /**
     * 按战斗结果修改战场信息（移植 rush_result_to_battle）
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $result
     */
    private function rushResultToBattle(array &$data, array $result, bool $first = false): void
    {
        $leftteam = $result['leftteam'];
        $rightteam = $result['rightteam'];
        $leftresult = $result['leftresult'];
        $rightresult = $result['rightresult'];
        if ($first) {
            // 守方先手 交换结果
            $leftteam = $result['rightteam'];
            $rightteam = $result['leftteam'];
            $leftresult = $result['rightresult'];
            $rightresult = $result['leftresult'];
        }
        $data['left']['blood'] = $data['right']['blood'] = 0;
        foreach (['left' => [$leftteam, $leftresult], 'right' => [$rightteam, $rightresult]] as $side => [$team, $sideResult]) {
            $newGeneral = 0;
            foreach (array_keys($data[$side]['team']) as $k) {
                $generalAllblood = $team[$k]['all_blood'] ?? $team[$k]['allblood'];

                if (!isset($data[$side]['allblood']) || $data[$side]['allblood'] <= 0) {
                    $data[$side]['allblood'] = $generalAllblood;
                    $newGeneral = 1;
                } elseif ($newGeneral) {
                    $data[$side]['allblood'] += $generalAllblood;
                }
                if ($data[$side]['win']) {
                    // 旧版取本场前的剩余血量；没有 blood 的单位按 0（旧版读到 null）
                    $data[$side]['blood'] += $data[$side]['team'][$k]['blood'] ?? 0;
                } else {
                    $data[$side]['blood'] = $data[$side]['allblood'];
                }
                if (!isset($sideResult[$k])) {
                    // 去掉阵亡的武将
                    unset($data[$side]['team'][$k]);
                } else {
                    // 保存剩余血量
                    $data[$side]['team'][$k]['blood'] = $sideResult[$k]['blood'];
                }
            }
        }
    }

    /**
     * 保存阵型信息（移植 save_matrix）
     *
     * @param array<string, mixed> $data
     */
    private function saveMatrix(string $side, array &$data): void
    {
        $data['matrix_type'] = $this->matrix[$side]['type'];
        $data['matrix_coe'] = $this->matrix[$side]['coe'];
    }

    /**
     * 恢复阵型信息（移植 resume_matrix）
     *
     * @param array<string, mixed> $data
     */
    private function resumeMatrix(string $side, array $data): void
    {
        if (!empty($data)) {
            $this->matrix[$side] = ['type' => $data['matrix_type'], 'coe' => $data['matrix_coe']];
        }
    }

    /**
     * 战报填充 uid 和 血量
     *
     * @param array<string, mixed> $theReport
     * @param array<string, mixed> $field
     */
    private static function fillReport(array &$theReport, array $field): void
    {
        foreach (['left', 'right'] as $side) {
            $theReport[$side]['uid'] = $field[$side]['uid'];
            $theReport[$side]['blood'] = $field[$side]['blood'];
            $theReport[$side]['allblood'] = $field[$side]['allblood'];
        }
    }

    /**
     * 获得队伍阵型（移植 rush_team_return）
     *
     * @param array<int, array<string, mixed>> $team
     * @return array{matrix: array<int, mixed>, allblood: int}
     */
    private static function rushTeamReturn(array $team): array
    {
        $return = ['matrix' => [], 'allblood' => 0];
        foreach ($team as $k => $v) {
            $return['matrix'][$k] = $v['image'];
        }
        return $return;
    }

    /**
     * rush战报处理（移植 rush_report）：双方阵型 + 每回合阵亡位置与伤血，超过 3 回合合并成 3 个回合
     *
     * @param array<string, mixed> $report BattleEngine::battle 的返回值
     * @return array<string, mixed>
     */
    private static function rushReport(array $report, bool $isDefense = false): array
    {
        $return = [];
        foreach ($report as $key => $value) {
            if (!in_array($key, ['leftteam', 'rightteam'], true)) {
                if ($key === 'assess') {
                    $return[$key] = $value;
                }
                continue;
            }
            // 左右双方阵型
            $side = substr($key, 0, -4);
            if ($isDefense) {
                // 守方先手
                $side = $side === 'left' ? 'right' : 'left';
            }
            $return[$side] = ['matrix' => []];
            foreach ($value as $k => $v) {
                $return[$side]['matrix'][$k] = $v['image'];
            }
        }
        $key = 'process';
        $return['process'] = [];
        // 攻击过程
        $round = 1;
        $dead = ['left' => [], 'right' => []];
        $hitArray = ['left' => 0, 'right' => 0];
        foreach ($report['process'] as $v) {
            // 每回合阵亡数组
            if ($round != $v['round']) {
                $return[$key][$round] = self::roundReport($dead, $hitArray, $isDefense);
                $round = $v['round'];
                $dead = ['left' => [], 'right' => []];
                $hitArray = ['left' => 0, 'right' => 0];
            }

            // 处理返回的key
            if ($v['launch'] === 'left') {
                $attackKey = 'left';
                $defenseKey = 'right';
            } else {
                $attackKey = 'right';
                $defenseKey = 'left';
            }
            $attackTeamKey = $attackKey . 'team';
            $defenseTeamKey = $defenseKey . 'team';
            // 按回合计算伤血数
            foreach ($v['hurt'] ?? [] as $hit) {
                if ($hit['blood'] <= 0) {
                    continue;
                }

                $thePos = &$report[$attackTeamKey][$hit['pos']];
                if (in_array($v['skill'] ?? null, [3, 4, 14]) && $hit['self']) {
                    // 使用加血
                    $hitArray[$attackKey] -= $hit['blood'];
                    if (isset($thePos['allblood'])) {
                        $thePos['allblood'] += $hit['blood'];
                    }
                    if (isset($thePos['blood'])) {
                        $thePos['blood'] += $hit['blood'];
                    }
                } elseif (($v['attacktype'] ?? null) == -2) {
                    // 识破：将领减血
                    if (isset($thePos['blood']) && $thePos['blood'] < $thePos['allblood']) {
                        $thePos['allblood'] = $thePos['blood'];
                    }
                    // 将领伤亡
                    if (!isset($thePos['allblood']) || $thePos['allblood'] <= $hit['blood']) {
                        $dead[$attackKey][] = $hit['pos'];
                        $hitArray[$attackKey] += $thePos['allblood'] ?? 0;
                    } else {
                        $thePos['allblood'] -= $hit['blood'];
                        $hitArray[$attackKey] += $hit['blood'];
                    }
                } else {
                    // 攻击 将领减血
                    unset($thePos);
                    $thePos = &$report[$defenseTeamKey][$hit['pos']];
                    if ($hit['self'] == 1) {
                        // 自身增益不计算伤害
                        unset($thePos);
                        continue;
                    }
                    if (isset($thePos['blood']) && $thePos['blood'] < $thePos['allblood']) {
                        $thePos['allblood'] = $thePos['blood'];
                    }
                    // 将领伤亡
                    if (!isset($thePos['allblood']) || $thePos['allblood'] <= $hit['blood']) {
                        $dead[$defenseKey][] = $hit['pos'];
                        $hitArray[$defenseKey] += $thePos['allblood'] ?? 0;
                    } else {
                        $thePos['allblood'] -= $hit['blood'];
                        $hitArray[$defenseKey] += $hit['blood'];
                    }
                }
                unset($thePos);
            }
        }
        // 最后一回合阵亡记录
        $return[$key][$round] = self::roundReport($dead, $hitArray, $isDefense);

        // 多回合合并战报
        if ($round > 3) {
            // 计算几回合合并成一个回合
            $times = (int)ceil($round / 3);
            foreach ($return['process'] as $key => $value) {
                // 重新计算回合数
                $newRound = (int)ceil($key / $times);
                if ($key == $newRound) {
                    // 原战报第一回合跳过
                    continue;
                } elseif (!isset($return['process'][$newRound])) {
                    // 初始化新战报回合
                    $return['process'][$newRound] = ['dead_left' => [], 'dead_right' => [], 'hit_left' => 0, 'hit_right' => 0];
                }
                foreach ($value as $k => $v) {
                    // 将原战报内容合并到新战报中
                    if ($k === 'dead_left' || $k === 'dead_right') {
                        foreach ($v as $generalid) {
                            $return['process'][$newRound][$k][] = $generalid;
                        }
                    }
                    if ($k === 'hit_left' || $k === 'hit_right') {
                        $return['process'][$newRound][$k] += $v;
                    }
                }
                unset($return['process'][$key]);
            }
        }
        return $return;
    }

    /**
     * 一个回合的阵亡位置与伤血（守方先手时左右互换）
     *
     * @param array{left: list<mixed>, right: list<mixed>} $dead
     * @param array{left: int|float, right: int|float} $hitArray
     * @return array{dead_left: list<mixed>, dead_right: list<mixed>, hit_left: int|float, hit_right: int|float}
     */
    private static function roundReport(array $dead, array $hitArray, bool $isDefense): array
    {
        return [
            'dead_left' => $isDefense ? $dead['right'] : $dead['left'],
            'dead_right' => $isDefense ? $dead['left'] : $dead['right'],
            'hit_left' => $isDefense ? $hitArray['right'] : $hitArray['left'],
            'hit_right' => $isDefense ? $hitArray['left'] : $hitArray['right'],
        ];
    }
}
