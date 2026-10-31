<?php

declare(strict_types=1);

namespace Hanjiang\Battle;

use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Talent;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * 九宫格战斗推演（逐段移植旧版 models/battle_model.php，路径相对旧版 s1/application/）
 *
 * - battle          ← battle()：单位初始化（总血量、命中 / 抗晕 / 抗暴、buff）、process、评级、返回结构
 * - process         ← process()：左右交替出手，250 回合上限
 * - processAttack   ← process_attack()：按站位顺序取本回合未出手的武将，眩晕解除 / 士气 >= 100 发动技能（消耗 100）
 * - processEffect   ← process_effect()：目标选择、暴击、闪避 / 识破、技能效果、士气、阵亡、战斗记录
 * - getPosition     ← _get_position()；getToPosition ← _get_to_position()
 * - processPosition ← process_position()：第一目标
 * - processHurt     ← process_hurt()：伤害公式
 * - battleAssess    ← _battle_assess()
 * - buffReturn / buffQuality ← buff_return() / buff_quality()
 *
 * 一个实例只推演一场（旧版模型实例同样在一次请求里只打一场），不跨请求共享。
 * 例外：团战 BattleRush（battle_rush）在一次请求里用同一个旧版模型实例连打多场，各场之间保留旧版不重置的实例状态
 * （_left_all_blood / _right_all_blood 累加、天神守护 / 降低识破 / 伤害加成等），并在每场前按旧版 resume_matrix 改阵法，
 * 所以 BattleRush 复用同一个实例并调用 setMatrix。
 *
 * 随机数：旧版用 mt_rand / rand / array_rand（PHP 7.1+ 三者同为 MT19937）。这里统一走注入的 Randomizer：
 * 默认 new Randomizer(new Mt19937())（随机种子），getInt / pickArrayKeys 与 mt_rand / array_rand 在同种子下逐个相同；
 * 测试注入固定种子或脚本化的 Engine。
 *
 * 依赖未移植（旧版对应数组在新版恒为空，相关分支不移植）：
 * 将魂 _general_soul、名将成就 _addition_arr、美人 _beauty_arr、星魄 _star_arr、国家学院 _warcollege_arr、
 * 锻造套装 _suit_arr 与单位上的 suitbuff（回气 / 偷气 / 清气 / 反伤 / 最终增伤 / 暴击伤害 / 血量伤害倍增）、
 * 名将进阶 _advanced_arr（天赋攻防系数加成）、神树 _tree_info（buff 里的 tree）。
 * 坐骑的特殊属性（血量% / 抗晕 / 最终免伤 / 吸血，旧版也记在 _suit_arr 与 suitbuff）与 buff 里的 mount 由 TeamBuilder 记在单位的
 * suit / suitbuff / mountinfo 上，这里照旧版计算。
 *
 * 旧版没有分支的技能按旧版照搬：effect 30 / 31（技能 655、656、668、669）只按 ratio 计伤害，攻击类型 16（655）没有受击位。
 */
final class BattleEngine
{
    /** 回合数上限（process） */
    private const int MAX_ROUND = 250;
    /** 世界 boss 命中率（抗闪避）/ 精准度（抗识破） */
    private const int BOSS_HIT_RATIO = 150;
    private const int BOSS_PRECISION_RATIO = 150;
    /** 国家 boss 命中率（抗闪避）/ 精准度（抗识破） */
    private const int COUNTRYBOSS_HIT_RATIO = 100;
    private const int COUNTRYBOSS_PRECISION_RATIO = 100;

    private readonly Randomizer $rng;

    /** @var array{left: array<int, array<string, mixed>>, right: array<int, array<string, mixed>>} */
    private array $team = ['left' => [], 'right' => []];

    /** @var array{left: array{type: int, coe: float}, right: array{type: int, coe: float}} 阵法 type / coe */
    private array $matrix;

    /** @var array{left: array<string, mixed>, right: array<string, mixed>} */
    private array $buff = ['left' => [], 'right' => []];

    private int $round = 0;
    private int $ko = 0;
    /** 当前攻击方 / 防御方（旧版 _attack / _defense 指向的队伍，_to_side） */
    private string $launch = 'left';
    private string $toSide = 'right';
    /** @var array<string, mixed> */
    private array $attackLog = [];
    /** @var list<array<string, mixed>> */
    private array $attackLogArr = [];
    private int $leftAllBlood = 0;
    private int $rightAllBlood = 0;

    /** @var array<string, array<int, int>> 天神守护（effect 18）：side => [站位 => 回合] */
    private array $shouhu = [];
    /** @var array{left: array<int, array{round: int, num: int|float}>, right: array<int, array{round: int, num: int|float}>} 降低识破（effect 19） */
    private array $jianshipo = ['left' => [], 'right' => []];
    /** @var array<string, array{round: int, num: int|float}> 伤害加成（effect 20） */
    private array $jiashanghai = [];
    /** 当前防御位置，用于查找天神守护 / 空城状态 */
    private int $defensePos = 0;
    /** @var array{left: array<int|string, array<string, mixed>>, right: array<int|string, array<string, mixed>>} 空城（effect 26） */
    private array $kongcheng = ['left' => [], 'right' => []];
    /** 无理由伤害翻倍（effect 27） */
    private int $doubleCoe = 0;
    /** @var array{left: array<string, array<string, int|float>>, right: array<string, array<string, int|float>>} 己方增益（effect 28 抗暴） */
    private array $selfBuff = ['left' => [], 'right' => []];
    /** @var array{left: array<string, array<string, int|float>>, right: array<string, array<string, int|float>>} 敌方减益（effect 29 降低命中精准） */
    private array $sideDebuff = ['left' => [], 'right' => []];

    /**
     * @param int $leftMatrixType  旧版 _left_matrix_type（init_member_team_info 防守方为 0 时写入左侧）
     * @param int|float $attackCoe 旧版 _attack_coe（世界 / 国家 boss 战的鼓舞系数，双方伤害都乘）
     * @param int $bossBattle      旧版 _boss_battle：0 普通，1 世界 boss，2 国家 boss
     */
    public function __construct(
        int $leftMatrixType = 0,
        float $leftMatrixCoe = 0.0,
        int $rightMatrixType = 0,
        float $rightMatrixCoe = 0.0,
        private readonly int|float $attackCoe = 1,
        private readonly int $bossBattle = 0,
        ?Randomizer $rng = null,
    ) {
        $this->matrix = [
            'left' => ['type' => $leftMatrixType, 'coe' => $leftMatrixCoe],
            'right' => ['type' => $rightMatrixType, 'coe' => $rightMatrixCoe],
        ];
        $this->rng = $rng ?? new Randomizer(new Mt19937());
    }

    /**
     * 改某一侧的阵法（旧版直接改 _left_matrix_type / _left_matrix_coe 等实例属性：init_member_team_info、resume_matrix）
     *
     * @param 'left'|'right' $side
     */
    public function setMatrix(string $side, int $type, float $coe): void
    {
        $this->matrix[$side] = ['type' => $type, 'coe' => $coe];
    }

    /**
     * 战斗（移植 battle()）
     *
     * @param array<int, array<string, mixed>> $teamLeft  TeamBuilder 输出的旧版队伍（站位 => 单位）
     * @param array<int, array<string, mixed>> $teamRight
     * @return array{leftteam: array<int, array<string, mixed>>, rightteam: array<int, array<string, mixed>>, process: list<array<string, mixed>>, assess: int, leftresult: array<int, array<string, mixed>>, rightresult: array<int, array<string, mixed>>, leftbuff: array<string, mixed>, rightbuff: array<string, mixed>}
     */
    public function battle(array $teamLeft, array $teamRight): array
    {
        $this->team['left'] = $this->initTeam($teamLeft, 'left');
        $this->team['right'] = $this->initTeam($teamRight, 'right');

        $return = [];
        $return['leftteam'] = TeamBuilder::teamReturnArr($this->team['left']);
        $return['rightteam'] = TeamBuilder::teamReturnArr($this->team['right']);

        if ($return['leftteam'] === []) {
            $return['process'] = [];
            $return['assess'] = -3;
        } elseif ($return['rightteam'] === []) {
            $return['process'] = [];
            $return['assess'] = 3;
        } else {
            $this->attackLogArr = [];
            $this->process();
            $return['process'] = $this->attackLogArr;
            $return['assess'] = $this->battleAssess();
        }
        $return['leftresult'] = TeamBuilder::teamReturnArr($this->team['left'], true);
        $return['rightresult'] = TeamBuilder::teamReturnArr($this->team['right'], true);
        $return['leftbuff'] = self::buffReturn($this->buff['left']);
        $return['rightbuff'] = self::buffReturn($this->buff['right']);
        return $return;
    }

    /**
     * battle() 开头的单位初始化（左右两侧同一套；旧版右侧的星魄 / 套装 / 将魂抗晕只对玩家将领（有 tili）生效，星魄 / 将魂依赖未移植）
     *
     * @param array<int, array<string, mixed>> $team
     * @return array<int, array<string, mixed>>
     */
    private function initTeam(array $team, string $side): array
    {
        $buff = ['mingzhong' => 0, 'jingzhun' => 0, 'kangyun' => 0, 'tree' => 0, 'star' => 0, 'kcrit' => 0, 'mount' => []];
        foreach ($team as $key => $value) {
            // 眩晕状态
            $team[$key]['faint'] = 0;
            // 血量：没有总血量时按 _get_general_all_blood 计算（怪物在 battle_mission 已按天赋血量系数算好）
            if (!isset($team[$key]['allblood']) || $team[$key]['allblood'] == 0) {
                $team[$key]['allblood'] = self::generalAllBlood($value);
            }
            if (!isset($team[$key]['blood']) || $team[$key]['blood'] == 0) {
                $team[$key]['blood'] = $team[$key]['allblood'];
            }
            // 命中：单位附加值 + 天赋命中；猛将取最高值记 mingzhong，谋士记 jingzhun
            $mingzhong = ($value['mingzhong'] ?? 0) + self::talent($value['talentid'], 'mingzhong');
            if ((int)$value['type'] === 1 && $buff['mingzhong'] < $mingzhong) {
                $buff['mingzhong'] = $mingzhong;
            }
            if ((int)$value['type'] === 2 && $buff['jingzhun'] < $mingzhong) {
                $buff['jingzhun'] = $mingzhong;
            }
            // 依赖未移植：神树 buff tree
            // 坐骑（旧版 _mount_info[uuid]：玩家本队品级最高的坐骑，TeamBuilder 记在各单位的 mountinfo）
            if (isset($value['mountinfo'])) {
                $buff['mount'] = ['id' => $value['mountinfo']['mid'], 'name' => $value['mountinfo']['name'], 'quality' => $value['mountinfo']['quality']];
            }
            // 抗晕：单位附加值 + 天赋抗晕 + 套装 / 坐骑抗晕（旧版 _suit_arr[ugid]['kangyun']，记在单位的 suit 上）
            // 依赖未移植：星魄 / 将魂(12) 抗晕
            $kangyun = ($value['kangyun'] ?? 0) + self::talent($value['talentid'], 'kangyun') + ($value['suit']['kangyun'] ?? 0);
            if ($buff['kangyun'] < $kangyun) {
                $buff['kangyun'] = $kangyun;
            }
            // 战斗特效标识
            $team[$key]['star'] = $value['star'] ?? 0;
            $team[$key]['mingzhong'] = $mingzhong;
            $team[$key]['kangyun'] = $kangyun;
            // 抗暴击率
            $team[$key]['kcrit'] = $value['kcrit'] ?? 0;
            if ($side === 'left') {
                $this->leftAllBlood += (int)$team[$key]['allblood'];
            } else {
                $this->rightAllBlood += (int)$team[$key]['allblood'];
            }
        }
        $this->buff[$side] = $buff;
        return $team;
    }

    /**
     * 套装 / 坐骑最终免伤：玩家将领（有 tili）带 suit.lastjianhurt 时伤害 × (100 - lastjianhurt) / 100 后取整
     * （旧版 _suit_arr[ugid]['lastjianhurt']）
     *
     * @param array<string, mixed> $defender
     */
    private static function lastJianHurt(array $defender, int|float $hit): int|float
    {
        if (isset($defender['tili'], $defender['suit']['lastjianhurt'])) {
            return (int)($hit * (100 - $defender['suit']['lastjianhurt']) / 100);
        }
        return $hit;
    }

    /**
     * 战斗总血量（移植 _get_general_all_blood）
     *
     * @param array<string, mixed> $general
     */
    private static function generalAllBlood(array $general): int
    {
        // 依赖未移植：名将进阶血量加成 skill4_value（主公 100 级）
        $advancedBlood = 0.0;
        // ptblood = 套装 / 坐骑血量%（旧版 _suit_arr[ugid]['ptblood']，记在单位的 suit 上）
        // 依赖未移植：将魂生命(4)、神树 buff
        $ptblood = (float)($general['suit']['ptblood'] ?? 0);
        return LegacyStats::battleAllBlood(
            (float)($general['all_blood'] ?? 0),
            (int)($general['tili'] ?? 0),
            (float)self::talent($general['talentid'], 'blood'),
            $advancedBlood,
            $ptblood,
        );
    }

    /**
     * @param array<string, mixed> $buff
     * @return array{mingzhong: int, jingzhun: int, kangyun: int, tree: mixed, kcrit: mixed, mount: mixed}
     */
    private static function buffReturn(array $buff): array
    {
        return [
            'mingzhong' => self::buffQuality($buff['mingzhong']),
            'jingzhun' => self::buffQuality($buff['jingzhun']),
            'kangyun' => self::buffQuality($buff['kangyun']),
            'tree' => $buff['tree'],
            'kcrit' => $buff['kcrit'],
            'mount' => $buff['mount'],
        ];
    }

    private static function buffQuality(int|float $buff): int
    {
        return match (true) {
            $buff == 0 => 0,
            $buff <= 20 => 2,
            $buff <= 40 => 3,
            $buff <= 60 => 4,
            $buff <= 80 => 5,
            default => 6,
        };
    }

    /**
     * 回合数控制（移植 process()）
     */
    private function process(): void
    {
        $this->ko = $this->round = 0;
        while ($this->ko === 0) {
            $this->round += 1;

            // 左侧攻击
            $this->processAttack(true);
            // 一方 KO 结束战斗，否则右侧继续攻击
            if ($this->ko) {
                break;
            }

            $this->processAttack(false);
            if ($this->round >= self::MAX_ROUND) {
                break;
            }
        }
    }

    /**
     * 攻击记录的回合数：ceil(_round / 5)
     */
    private function logRound(): int
    {
        return (int)ceil($this->round / 5);
    }

    /**
     * 移植 process_attack()
     *
     * @param bool $type true 左方攻击；false 右方攻击
     */
    private function processAttack(bool $type): void
    {
        $this->attackLog = [
            'round' => $this->logRound(),  // 回合数
            'launch' => '',                // 攻击阵营
            'attackor' => 0,               // 攻击位
            'attackpower' => 0,            // 攻击者士气
            'skill' => 0,                  // 0 普通攻击；发动技能时为技能效果 effect
            'hit' => 0,                    // 是否暴击
            'attacktype' => 0,             // 0 普通攻击、-1 闪避、-2 识破、-3 解除眩晕、-4 二次攻击
            'beattackors' => [],           // 受击位
            'hurt' => [],                  // 攻击结果
        ];

        if ($type) {
            $this->launch = 'left';
            $this->toSide = 'right';
        } else {
            $this->launch = 'right';
            $this->toSide = 'left';
        }
        $this->attackLog['launch'] = $this->launch;
        if ($this->team['left'] === []) {
            // 失败
            $this->ko = 2;
            return;
        }
        foreach ($this->team[$this->launch] as $position => $general) {
            if ($general['round'] >= $this->round) {
                // 跳过当前回合已攻击过的武将
                continue;
            }
            $this->team[$this->launch][$position]['round'] += 5;

            $this->attackLog['attackor'] = $position;

            if ($general['faint'] == 1) {
                // 解除眩晕
                $this->attackLog['attacktype'] = -3;
                $this->attackLog['hit'] = 0;
                $this->attackLog['beattackors'][] = $position;
                $this->attackLog['hurt'][] = ['pos' => $position, 'blood' => 0, 'power' => $general['power'], 'self' => 1, 'faint' => 0];
                $this->attackLogArr[] = $this->attackLog;
                $this->team[$this->launch][$position]['faint'] = 0;
            } elseif ($general['power'] >= 100) {
                // 士气可以发动技能攻击
                $this->attackLog['skill'] = 1;
                $this->team[$this->launch][$position]['power'] -= 100;
                // 技能不存在时旧版取到的 type 为 null，按普通攻击处理（skill 仍记 1）
                $this->ko = $this->processEffect($general, (int)(self::skill($general['skillid'])['type'] ?? 0));
            } else {
                $this->attackLog['skill'] = 0;
                $this->ko = $this->processEffect($general);
            }
            break;
        }
    }

    /**
     * 攻击效果（移植 process_effect()）
     *
     * @param array<string, mixed> $attack 出手时的攻击方单位（士气扣除前的副本）
     */
    private function processEffect(array $attack, int $skillType = 0): int
    {
        $round = $this->logRound();
        $L = $this->launch;
        $T = $this->toSide;
        // 攻击位
        $attackPos = $this->attackLog['attackor'];

        $skill = null;
        if ($skillType) {
            // 技能攻击取技能详情
            $skill = self::skill($attack['skillid']);
            $this->attackLog['skill'] = (string)$skill['effect'];
        }

        // 天赋暴击率
        $this->attackLog['hit'] = 0;
        $critRate = self::talent($attack['talentid'], 'crit');
        // 依赖未移植：玩家的将魂(7) / 名将成就 / 星魄 / 套装暴击率加成

        // 选择被攻击武将
        $suitposition = $position = $positionOrigin = $this->processPosition(array_keys($this->team[$T]));
        $defense = $this->team[$T][$position];

        // 攻击者的被动技能
        $this->doubleCoe = 0;
        $ownSkill = self::skill($attack['skillid']);
        // 神吕蒙（effect 27）：防御方血量低于 paramete% 时无理由释放技能，伤害倍增
        if ($ownSkill !== null && (int)$ownSkill['effect'] === 27) {
            if ($defense['blood'] < ($defense['allblood'] * $ownSkill['paramete'] / 100)) {
                if ($skillType >= 1) {
                    // 正常释放技能：释放技能消耗的士气补回来
                    $this->team[$L][$attackPos]['power'] += 100;
                } else {
                    // 强制释放技能
                    $skill = $ownSkill;
                    $this->attackLog['skill'] = (string)$skill['effect'];
                    $skillType = (int)$skill['type'];
                }
                $this->doubleCoe = 1;
            }
        }

        // 刺杀：第一目标为后排武将
        if ($skillType === 5) {
            $p = ($position - 1) % 3;
            foreach ([$p + 7, $p + 4, $p + 1] as $v) {
                if (isset($this->team[$T][$v])) {
                    $position = $v;
                    $defense = $this->team[$T][$position];
                    break;
                }
            }
        }

        // 记录第一目标
        $this->attackLog['beattackors'][] = $position;

        // 防御方己方增益：抗暴击（effect 28）
        if (isset($this->selfBuff[$T]['kcrit']) && $this->selfBuff[$T]['kcrit']['round'] >= $round - 1) {
            $defense['kcrit'] = ($defense['kcrit'] ?? 0) + $this->selfBuff[$T]['kcrit']['pkcrit'];
        }

        // 暴击与抗暴击
        $rand = $this->rand(1, 100);
        if (isset($defense['kcrit'])) {
            $critRate -= $defense['kcrit'];
        }
        if ($critRate >= $rand) {
            $this->attackLog['hit'] = 1;
        }

        // 没有眩晕状态：计算闪避（猛将）/ 识破（谋士）
        if ($defense['faint'] == 0) {
            $rand = $this->rand(1, 100);
            $this->attackLog['attacktype'] = 0;
            if ((int)$attack['type'] === 1) {
                $dodgeRate = self::talent($defense['talentid'], 'dodge');
                // 依赖未移植：玩家的将魂(5) / 名将成就 / 美人 / 星魄 / 国家学院闪避率加成

                // 降低攻击方的精准命中（effect 29）
                if (isset($this->sideDebuff[$L]['jingzhun']) && $this->sideDebuff[$L]['jingzhun']['round'] >= $round - 1) {
                    $attack['mingzhong'] -= $this->sideDebuff[$L]['jingzhun']['pmingzhong'];
                }

                if ($this->bossBattle === 1) {
                    $dodgeRate -= self::BOSS_HIT_RATIO;
                } elseif ($this->bossBattle === 2) {
                    $dodgeRate -= self::COUNTRYBOSS_HIT_RATIO;
                } elseif (isset($attack['mingzhong'])) {
                    $dodgeRate -= $attack['mingzhong'];
                }

                if ($dodgeRate >= $rand) {
                    // 闪避
                    $this->attackLog['attacktype'] = -1;
                    $this->attackLog['hit'] = 0;
                }
            } elseif ((int)$attack['type'] === 2) {
                $penetrateRate = self::talent($defense['talentid'], 'penetrate');
                // 玩家（有体力字段）
                if (isset($defense['tili'])) {
                    // 依赖未移植：玩家的将魂(6) / 名将成就 / 美人 / 星魄 / 国家学院 / 套装识破率加成

                    // 降低攻击方的精准命中（effect 29；旧版此处把命中改写为识破率的折算值）
                    if (isset($this->sideDebuff[$L]['jingzhun']) && $this->sideDebuff[$L]['jingzhun']['round'] >= $round - 1) {
                        $attack['mingzhong'] = ($penetrateRate * (100 - $this->sideDebuff[$L]['jingzhun']['pmingzhong']) / 100);
                    }
                }
                if ($this->bossBattle === 1) {
                    $penetrateRate -= self::BOSS_PRECISION_RATIO;
                } elseif ($this->bossBattle === 2) {
                    $penetrateRate -= self::COUNTRYBOSS_PRECISION_RATIO;
                } elseif (isset($attack['mingzhong'])) {
                    $penetrateRate -= $attack['mingzhong'];
                }
                // 降低识破（effect 19）
                if (isset($this->jianshipo[$T][$position]) && $this->jianshipo[$T][$position]['round'] >= $round - 1) {
                    $penetrateRate -= $this->jianshipo[$T][$position]['num'];
                }

                if ($penetrateRate >= $rand) {
                    // 识破：第一目标转换为攻击方武将
                    $this->attackLog['attacktype'] = -2;
                    $this->attackLog['hit'] = 0;
                    $position = $attackPos;
                }
            }
        }

        // 防御武将位置
        $defensePosArr = [];
        $attackPosArr = [];
        $self = 0;  // 技能效果对象为己方
        switch ($skillType) {
            case 0:  // 未使用技能
            case 1:  // 单体攻击
                $defensePosArr = [$position];
                break;
            case 2:  // 横排攻击 1、4、7
                $p = ($position - 1) % 3;
                $defensePosArr = [$p + 1, $p + 4, $p + 7];
                break;
            case 3:  // 竖排攻击 1、2、3
                $p = intdiv($position - 1, 3);
                for ($i = 1; $i <= 3; $i++) {
                    $defensePosArr[] = 3 * $p + $i;
                }
                break;
            case 4:  // 全体攻击
                $defensePosArr = [1, 2, 3, 4, 5, 6, 7, 8, 9];
                break;
            case 5:  // 刺杀
                $defensePosArr[] = $position;
                break;
            case 6:  // 上下左右
                $defensePosArr = match ($position) {
                    1 => [1, 2, 4],
                    2 => [1, 2, 3, 5],
                    3 => [2, 3, 6],
                    4 => [1, 4, 5, 7],
                    5 => [2, 4, 5, 6, 8],
                    6 => [3, 5, 6, 9],
                    7 => [4, 7, 8],
                    8 => [5, 7, 8, 9],
                    9 => [6, 8, 9],
                    default => [],
                };
                break;
            case 7:  // 己方全体
                $self = 1;
                $attackPosArr = $this->getPosition($attack['skillid'], 5);
                break;
            case 8:  // 己方单体
                $self = 1;
                $attackPosArr = $this->getPosition($attack['skillid'], 1);
                break;
            case 9:  // 己方三人
                $self = 1;
                $attackPosArr = $this->getPosition($attack['skillid'], 3);
                break;
            case 10:  // 随机敌方 1 人
                $defensePosArr = $this->getToPosition(1);
                break;
            case 11:  // 随机敌方 2 人
                $defensePosArr = $this->getToPosition(2);
                break;
            case 12:  // 随机敌方 3 人
                $defensePosArr = $this->getToPosition(3);
                break;
            case 13:  // 根据攻击者血量判断人数：每减少 10% 血，随机增加攻击 1 人
                $hp = $this->team[$L][$attackPos]['blood'];
                $allhp = $this->team[$L][$attackPos]['allblood'];
                if ($hp < ($allhp * 0.6)) {
                    $defensePosArr = $this->getToPosition(5);
                } elseif ($hp < ($allhp * 0.7)) {
                    $defensePosArr = $this->getToPosition(4);
                } elseif ($hp < ($allhp * 0.8)) {
                    $defensePosArr = $this->getToPosition(3);
                } elseif ($hp < ($allhp * 0.9)) {
                    $defensePosArr = $this->getToPosition(2);
                } else {
                    $defensePosArr = $this->getToPosition(1);
                }
                break;
            case 14:  // 目标纵列以及目标后排纵列
                $p = intdiv($position - 1, 3);
                for ($i = 1; $i <= 6; $i++) {
                    $positionSite = 3 * $p + $i;
                    if ($positionSite > 9) {
                        break;
                    }
                    $defensePosArr[] = $positionSite;
                }
                break;
            case 15:  // 目标横排以及目标纵列
                $p = ($position - 1) % 3;
                $defensePosArr = [$p + 1, $p + 4, $p + 7];
                $p = intdiv($position - 1, 3);
                for ($i = 1; $i <= 3; $i++) {
                    $defensePosArr[] = 3 * $p + $i;
                }
                $defensePosArr = array_unique($defensePosArr);
                sort($defensePosArr);
                break;
            // 其他类型（如 16）旧版没有分支：没有受击位
        }
        if ($self === 1) {
            // 己方增益不闪避、不识破
            $this->attackLog['attacktype'] = 0;
            // 加怒气不暴击
            if ((int)$skill['effect'] === 7) {
                $this->attackLog['hit'] = 0;
            }
            $defensePosArr = [$positionOrigin];
        } elseif ($this->attackLog['attacktype'] === -2) {
            // 被识破之后防御方变成自己
            $defensePosArr = [$position];
        }

        $hurtPosArr = [];   // 受击位置数组
        $attackLog2 = [];   // 二次攻击记录
        $doubleHit = 0;     // 是否触发连击
        $skillUp = 0;       // 攻击附带增益开关，防止群攻重复计算增益
        $allHit = 0;        // 总伤害（神曹操 effect 24）
        $secondHit = 0;
        $defensePower = 0;
        $defenseBefore = [];
        // 套装 / 坐骑吸血：旧版 $defense_before = $this->_defense（本次攻击前的防御方），$xhit 累计吸血
        $suitDefenseBefore = $this->team[$T];
        $xhit = 0;
        if ($skillType !== 0 && (int)$skill['effect'] === 24) {
            $defenseBefore = $this->team[$T];
        }
        // 神诸葛空城：只循环处理一次的结束信息
        $endtimes = 0;
        $endrighttimes = 0;
        $openSelf = 0;
        $kongchengSide = 0;

        $trueSite = $T === 'left' ? 'right' : 'left';

        foreach ($defensePosArr as $pos) {
            $this->defensePos = $pos;
            $additionEffect = [];  // 附加效果
            $hit = $power = $faint = $isOpen = 0;

            // 被攻击方（或攻击方）存在空城数据
            if ($this->kongcheng[$T] !== [] || $this->kongcheng[$trueSite] !== []) {
                if ($this->kongcheng[$T] !== []) {
                    $iswnfs = &$this->kongcheng[$T];
                } else {
                    $iswnfs = &$this->kongcheng[$trueSite];
                }
                // 识破特殊处理
                $topos = $pos;
                if ($this->attackLog['attacktype'] === -2) {
                    $topos = $suitposition;
                }
                if (isset($iswnfs['isself' . $attackPos])) {
                    // 自己是自己的防御方
                    if ($iswnfs['isself' . $attackPos]['endsite'] <= 0) {
                        $kongchengSide = $iswnfs['isself' . $attackPos]['actpos'];
                        if ($iswnfs['isself' . $attackPos]['round'] + 1 <= $round) {
                            // 空城效果结束
                            $endrighttimes = $kongchengSide;
                            $iswnfs['isself' . $attackPos]['endsite'] = 1;
                        }
                    }
                }
                if (isset($iswnfs[$topos])) {
                    // 被正常攻击触发了空城（非识破和反伤）
                    if ($iswnfs[$topos]['endsite'] <= 0) {
                        $kongchengSide = $iswnfs[$topos]['actpos'];
                        $end = false;
                        if ($iswnfs[$topos]['round'] + 1 === $round) {
                            // 当前攻击位大于或者等于触发空城的攻击位时结束
                            $end = $attackPos >= $kongchengSide;
                        } elseif ($iswnfs[$topos]['round'] + 1 < $round) {
                            $end = true;
                        }
                        if ($end) {
                            $endtimes = $kongchengSide;
                            if ($this->attackLog['attacktype'] === -2) {
                                $endtimes = $topos;
                                $openSelf = 1;
                            }
                            if (isset($iswnfs[$topos]['fanshang'])) {
                                $endtimes = $suitposition;
                                $openSelf = 1;
                            }
                            $iswnfs[$topos]['endsite'] = 1;
                        }
                    }
                }
                unset($iswnfs);
            }

            if ($this->attackLog['attacktype'] === 0) {
                // 正常攻击
                if (isset($this->team[$T][$pos])) {
                    if ($self === 0) {
                        // 攻击对方
                        if ($pos === $position && $skillType === 0) {
                            // 士气：攻击方、第一目标各涨 25
                            $this->team[$L][$attackPos]['power'] += 25;
                            $this->team[$T][$pos]['power'] += 25;
                        }
                        $hurtPosArr[] = $pos;

                        $hit = $this->processHurt($attack, $this->team[$T][$pos], $this->attackLog['hit']);
                        // 依赖未移植：防御方套装反伤（suitbuff.fanshang）
                        // 套装 / 坐骑最终免伤（lastjianhurt）
                        $hit = self::lastJianHurt($this->team[$T][$pos], $hit);

                        $newBlood = $this->team[$T][$pos]['blood'] - $hit;

                        $isLjian = true;

                        // 防御方神诸葛空城计（被动技能，只触发一次）
                        if (isset($this->team[$T][$pos]['tili'])
                            && (int)(self::skill($this->team[$T][$pos]['skillid'])['effect'] ?? 0) === 26
                            && $newBlood <= 0
                            && !isset($this->kongcheng[$T][$pos])
                            && !isset($this->kongcheng[$T]['isself' . $pos])
                        ) {
                            $this->kongcheng[$T][$pos] = [
                                'endsite' => 0,
                                'paramete' => self::skill($this->team[$T][$pos]['skillid'])['paramete'],
                                'actpos' => $attackPos,
                                'round' => $round,
                            ];
                            // 恢复血量到 10%
                            $isOpen = $newBlood = (int)($this->team[$T][$pos]['allblood'] * 10 / 100);
                            $isLjian = false;
                        }

                        if ($newBlood <= 0) {
                            // 击杀
                            $hit = $this->team[$T][$pos]['blood'];
                            // 记录死前的士气，偷气用到
                            $defensePower = $this->team[$T][$pos]['power'];
                            // 阵亡将领移出队伍
                            unset($this->team[$T][$pos]);
                        } else {
                            // 伤害
                            $this->team[$T][$pos]['blood'] = $newBlood;
                            $power = $this->team[$T][$pos]['power'];
                            $faint = $this->team[$T][$pos]['faint'];
                            if ($skillType !== 0) {
                                // 技能效果
                                $rand = $this->rand(1, 100);
                                $effect = (int)$skill['effect'];
                                if ($effect === 2) {
                                    // 眩晕
                                    $yunCoe = $skill['paramete'];
                                    if (isset($this->team[$T][$pos]['kangyun'])) {
                                        $yunCoe -= $this->team[$T][$pos]['kangyun'];
                                    }
                                    if ($this->bossBattle === 0 && $yunCoe >= $rand) {
                                        $this->team[$T][$pos]['faint'] = 1;
                                        $faint = 1;
                                    }
                                } elseif ($effect === 6) {
                                    // 消士气
                                    $this->team[$T][$pos]['power'] -= $skill['paramete'];
                                    if ($this->team[$T][$pos]['power'] < 0) {
                                        $this->team[$T][$pos]['power'] = 0;
                                    }
                                    $power = $this->team[$T][$pos]['power'];
                                } elseif ($effect === 5 || $effect === 25) {
                                    // 连击
                                    if ($doubleHit === 1 || ($doubleHit === 0 && $skill['paramete'] >= $rand)) {
                                        $doubleHit = 1;
                                        $secondHit = $hit;
                                        if ($attackLog2 === []) {
                                            $attackLog2['attackor'] = $this->attackLog['attackor'];
                                            $attackLog2['beattackors'] = $this->attackLog['beattackors'];
                                            $attackLog2['launch'] = $this->attackLog['launch'];
                                            $attackLog2['round'] = $this->attackLog['round'];
                                            $attackLog2['skill'] = 0;
                                            $attackLog2['attackpower'] = $this->team[$L][$attackPos]['power'];
                                            $attackLog2['attacktype'] = -4;  // 二次攻击

                                            $rand = $this->rand(1, 100);
                                            $critRate = self::talent($attack['talentid'], 'crit');
                                            // 玩家（有体力字段）
                                            if (isset($attack['tili'])) {
                                                // 依赖未移植：将魂(7) / 名将成就 / 星魄 / 套装暴击率加成
                                                // 左方右方暴击和抗暴击
                                                if (isset($defense['kcrit'])) {
                                                    $critRate -= $defense['kcrit'];
                                                }
                                            }

                                            if ($critRate >= $rand) {
                                                // 暴击
                                                $attackLog2['hit'] = 1;
                                                if ($this->attackLog['hit'] === 0) {
                                                    // 依赖未移植：将魂(8) / 美人 / 星魄 / 套装暴击伤害加成
                                                    $secondHit = (int)($hit * 1.5);
                                                }
                                            } elseif ($this->attackLog['hit'] === 1) {
                                                $secondHit = (int)($hit * 2 / 3);
                                                $attackLog2['hit'] = 0;
                                            }
                                        }

                                        $newBlood = $this->team[$T][$pos]['blood'] - $secondHit;
                                        // 防御方神诸葛空城计
                                        if (isset($this->team[$T][$pos]['tili'])
                                            && (int)(self::skill($this->team[$T][$pos]['skillid'])['effect'] ?? 0) === 26
                                            && $newBlood <= 0
                                            && !isset($this->kongcheng[$T][$pos])
                                            && !isset($this->kongcheng[$T]['isself' . $pos])
                                        ) {
                                            $this->kongcheng[$T][$pos] = [
                                                'endsite' => 0,
                                                'paramete' => self::skill($this->team[$T][$pos]['skillid'])['paramete'],
                                                'actpos' => $attackPos,
                                                'round' => $round,
                                            ];
                                            // 恢复血量到 10%
                                            $isOpen = $newBlood = (int)($this->team[$T][$pos]['allblood'] * 10 / 100);
                                        }
                                        if ($newBlood <= 0) {
                                            // 击杀
                                            $secondHit = $this->team[$T][$pos]['blood'];
                                            unset($this->team[$T][$pos]);
                                        } else {
                                            $this->team[$T][$pos]['blood'] = $newBlood;
                                        }

                                        $tmpl = ['pos' => $pos, 'blood' => $secondHit, 'power' => $power, 'self' => 0, 'faint' => $faint];
                                        if ($isOpen > 0 && $isLjian) {
                                            $tmpl['kongcheng'] = $isOpen;
                                            $isOpen = 0;
                                        }
                                        // 记录连击
                                        $attackLog2['hurt'][] = $tmpl;
                                    } else {
                                        // 触发失败
                                        $doubleHit = -1;
                                    }
                                } elseif ($effect === 19) {
                                    // 降低对方识破
                                    $this->jianshipo[$T][$pos] = ['round' => $round, 'num' => $skill['paramete']];
                                } elseif ($effect === 20) {
                                    // 增加己方攻击
                                    $this->jiashanghai[$L] = ['round' => $round, 'num' => $skill['paramete']];
                                } elseif ($effect === 28) {
                                    // 提升抗暴击率
                                    $this->selfBuff[$L]['kcrit'] = ['round' => $round, 'pkcrit' => $skill['paramete']];
                                } elseif ($effect === 29) {
                                    // 降低敌方命中精准
                                    $this->sideDebuff[$T]['jingzhun'] = ['round' => $round, 'pmingzhong' => $skill['paramete']];
                                }
                            }
                        }

                        // 套装 / 坐骑吸血（suitbuff.xixue）：群攻各目标的吸血累加，打到最后一个目标时一次加给攻击方
                        if (($this->team[$L][$attackPos]['suitbuff']['xixue'] ?? 0) > 0) {
                            $xhit += $hit * $this->team[$L][$attackPos]['suitbuff']['xixue'] / 100;
                            $defenseLastSite = 0;
                            foreach ($suitDefenseBefore as $key => $val) {
                                if ($key > $defenseLastSite && in_array($key, $defensePosArr)) {
                                    $defenseLastSite = $key;
                                }
                            }
                            if ($pos === $defenseLastSite) {
                                if ($xhit + $this->team[$L][$attackPos]['blood'] >= $this->team[$L][$attackPos]['allblood']) {
                                    // 加血不溢出处理
                                    $xhit = $this->team[$L][$attackPos]['allblood'] - $this->team[$L][$attackPos]['blood'];
                                }
                                $this->team[$L][$attackPos]['blood'] += (int)$xhit;
                                $this->attackLog['hurt'][] = [
                                    'xixue' => 1,
                                    'pos' => $attackPos,
                                    'blood' => (int)$xhit,
                                    'power' => 0,
                                    'self' => 1,
                                    'faint' => $faint,
                                ];
                            }
                        }
                        // 依赖未移植：套装回气（retpower）、偷气（thipower）

                        if ($skillType !== 0) {
                            $effect = (int)$skill['effect'];
                            if ($effect === 3 || $effect === 15) {
                                // 吸血
                                $blood = (int)($skill['paramete'] / 100 * $hit);
                                if ($blood + $this->team[$L][$attackPos]['blood'] >= $this->team[$L][$attackPos]['allblood']) {
                                    // 加血不溢出
                                    $blood = $this->team[$L][$attackPos]['allblood'] - $this->team[$L][$attackPos]['blood'];
                                }
                                $this->team[$L][$attackPos]['blood'] += $blood;
                                $additionEffect = [
                                    'pos' => $attackPos,
                                    'blood' => $blood,
                                    'power' => $this->team[$L][$attackPos]['power'],
                                    'self' => 1,
                                    'faint' => $faint,
                                ];
                            } elseif (in_array($effect, [8, 17, 23, 27, 28], true)) {
                                // 吸气（23 神孙权、27 神吕蒙吸 200 气，28 吸 100 气）
                                if ($this->bossBattle === 0) {
                                    $paramete = $skill['paramete'];
                                    if ($effect === 27) {
                                        $paramete = 200;
                                    } elseif ($effect === 28) {
                                        $paramete = 100;
                                    }
                                    if (isset($this->team[$T][$pos])) {
                                        // 对方没死
                                        $addPower = $this->team[$T][$pos]['power'] >= $paramete ? $paramete : $this->team[$T][$pos]['power'];

                                        $this->team[$T][$pos]['power'] -= $addPower;
                                        $power = $this->team[$T][$pos]['power'];
                                        if ($effect === 17) {
                                            $yunCoe = $skill['paramete'];
                                            if (isset($this->team[$T][$pos]['kangyun'])) {
                                                $yunCoe -= $this->team[$T][$pos]['kangyun'];
                                            }
                                            if ($yunCoe >= $this->rand(1, 100)) {
                                                $this->team[$T][$pos]['faint'] = 1;
                                                $faint = 1;
                                            }
                                        }
                                        if ($effect === 23 || $effect === 27) {
                                            // 100% 眩晕
                                            $yunCoe = 100;
                                            if (isset($this->team[$T][$pos]['kangyun'])) {
                                                $yunCoe -= $this->team[$T][$pos]['kangyun'];
                                            }
                                            if ($yunCoe >= $this->rand(1, 100)) {
                                                $this->team[$T][$pos]['faint'] = 1;
                                                $faint = 1;
                                            }
                                        }
                                    } else {
                                        // 击杀
                                        $addPower = $defensePower >= $paramete ? $paramete : $defensePower;
                                        $power = $defensePower - $addPower;
                                    }

                                    $this->team[$L][$attackPos]['power'] += $addPower;
                                    $additionEffect = [
                                        'pos' => $attackPos,
                                        'blood' => $this->team[$L][$attackPos]['blood'],
                                        'power' => $this->team[$L][$attackPos]['power'],
                                        'self' => 1,
                                        'faint' => $faint,
                                    ];
                                }
                            } elseif ($effect === 14 || $effect === 16) {
                                // 吸血加气
                                if ($skillUp === 0) {
                                    $this->team[$L][$attackPos]['power'] += 100;
                                    $skillUp = 1;
                                }

                                $blood = (int)($skill['paramete'] / 100 * $hit);
                                if ($blood + $this->team[$L][$attackPos]['blood'] >= $this->team[$L][$attackPos]['allblood']) {
                                    // 加血不溢出
                                    $blood = $this->team[$L][$attackPos]['allblood'] - $this->team[$L][$attackPos]['blood'];
                                }
                                $this->team[$L][$attackPos]['blood'] += $blood;
                                $additionEffect = [
                                    'pos' => $attackPos,
                                    'blood' => $blood,
                                    'power' => $this->team[$L][$attackPos]['power'],
                                    'self' => 1,
                                    'faint' => $faint,
                                ];
                            } elseif ($skillUp === 0) {
                                // 攻击附带增益技能
                                if ($effect === 9) {
                                    // 攻击附带单体加气（除自己）
                                    $skillUp = 1;
                                    foreach ($this->getPosition($attack['skillid'], 1) as $value) {
                                        $this->team[$L][$value]['power'] += $skill['paramete'];
                                        $this->attackLog['hurt'][] = [
                                            'pos' => $value,
                                            'blood' => $this->team[$L][$value]['blood'],
                                            'power' => $this->team[$L][$value]['power'],
                                            'self' => 1,
                                            'faint' => $faint,
                                        ];
                                    }
                                } elseif ($effect === 10) {
                                    // 攻击附带全体加气（除自己）
                                    $skillUp = 1;
                                    foreach ($this->getPosition($attack['skillid'], 4) as $value) {
                                        $this->team[$L][$value]['power'] += $skill['paramete'];
                                        $this->attackLog['hurt'][] = [
                                            'pos' => $value,
                                            'blood' => $this->team[$L][$value]['blood'],
                                            'power' => $this->team[$L][$value]['power'],
                                            'self' => 1,
                                            'faint' => $faint,
                                        ];
                                    }
                                } elseif ($effect === 11) {
                                    // 攻击附带自己加气
                                    $skillUp = 1;
                                    $this->team[$L][$attackPos]['power'] += $skill['paramete'];
                                    $this->attackLog['hurt'][] = [
                                        'pos' => $attackPos,
                                        'blood' => $this->team[$L][$attackPos]['blood'],
                                        'power' => $this->team[$L][$attackPos]['power'],
                                        'self' => 1,
                                        'faint' => $faint,
                                    ];
                                } elseif (in_array($effect, [12, 18, 19, 20, 21, 22, 25, 26, 29], true)) {
                                    // 攻击附带自己加气：21 / 29 加 150，22 / 26 加 200，其余加 100
                                    $skillUp = 1;
                                    if ($effect === 21 || $effect === 29) {
                                        $this->team[$L][$attackPos]['power'] += 150;
                                    } elseif ($effect === 22 || $effect === 26) {
                                        $this->team[$L][$attackPos]['power'] += 200;
                                    } else {
                                        $this->team[$L][$attackPos]['power'] += 100;
                                    }
                                    $this->attackLog['hurt'][] = [
                                        'pos' => $attackPos,
                                        'blood' => $this->team[$L][$attackPos]['blood'],
                                        'power' => $this->team[$L][$attackPos]['power'],
                                        'self' => 1,
                                        'faint' => $faint,
                                    ];

                                    if ($effect === 18) {
                                        // 天神守护
                                        $this->shouhu[$L][$attackPos] = $round;
                                    }
                                } elseif ($effect === 24) {
                                    // 神曹操：打完最后一个目标后给自己加 100 气、按总伤害 paramete% 回血
                                    $allHit += $hit;
                                    $addPower = 100;
                                    // 敌方活着的人占的最后一个位置
                                    $defenseLastSite = 0;
                                    foreach ($defenseBefore as $key => $val) {
                                        if ($key > $defenseLastSite && in_array($key, $defensePosArr, true)) {
                                            $defenseLastSite = $key;
                                        }
                                    }
                                    if ($pos === $defenseLastSite) {
                                        $this->team[$L][$attackPos]['power'] += $addPower;
                                        $addBlood = (int)($allHit * $skill['paramete'] / 100);
                                        if ($this->team[$L][$attackPos]['blood'] + $addBlood >= $this->team[$L][$attackPos]['allblood']) {
                                            // 加血不溢出
                                            $addBlood = $this->team[$L][$attackPos]['allblood'] - $this->team[$L][$attackPos]['blood'];
                                        }
                                        $this->team[$L][$attackPos]['blood'] += $addBlood;
                                        $this->attackLog['hurt'][] = [
                                            'pos' => $attackPos,
                                            'blood' => $addBlood,
                                            'power' => $this->team[$L][$attackPos]['power'],
                                            'self' => 1,
                                            'faint' => $faint,
                                        ];
                                    }
                                }
                            }
                        }

                        // 依赖未移植：套装清气（suitbuff.clearpower）、套装反伤结算（openfanshang）
                    } else {
                        // 依赖未移植：套装回气（suitbuff.retpower）
                        // 己方增益
                        $effect = (int)$skill['effect'];
                        if ($effect === 4) {
                            // 加血：治疗量按对第一目标的伤害公式计算（旧版 intval 的第二个参数 1 落在 intval 上，process_hurt 仍按伤害计算）
                            $addBlood = $this->processHurt($attack, $this->team[$T][$pos], $this->attackLog['hit']);
                            foreach ($attackPosArr as $value) {
                                if (isset($this->team[$L][$value])) {
                                    $shijiBlood = $addBlood;
                                    if ($this->team[$L][$value]['blood'] + $addBlood >= $this->team[$L][$value]['allblood']) {
                                        // 加血不溢出
                                        $shijiBlood = $this->team[$L][$value]['allblood'] - $this->team[$L][$value]['blood'];
                                    }
                                    $this->team[$L][$value]['blood'] += $shijiBlood;
                                    $this->attackLog['hurt'][] = [
                                        'pos' => $value,
                                        'blood' => $shijiBlood,
                                        'power' => $this->team[$L][$value]['power'],
                                        'self' => $self,
                                        'faint' => $faint,
                                    ];
                                }
                            }
                        } elseif ($effect === 7) {
                            // 加士气
                            foreach ($attackPosArr as $value) {
                                $this->team[$L][$value]['power'] += $skill['paramete'];
                                $this->attackLog['hurt'][] = [
                                    'pos' => $value,
                                    'blood' => $this->team[$L][$value]['blood'],
                                    'power' => $this->team[$L][$value]['power'],
                                    'self' => $self,
                                    'faint' => $faint,
                                ];
                            }
                        }
                    }
                }
            } elseif ($this->attackLog['attacktype'] === -1) {
                // 闪避
                if (isset($this->team[$T][$pos])) {
                    $hurtPosArr[] = $pos;
                    $power = $this->team[$T][$pos]['power'];
                }
            } elseif ($this->attackLog['attacktype'] === -2) {
                // 识破：防御方（第一目标）按自身属性反击攻击方，伤害 25%
                if (isset($this->team[$L][$pos])) {
                    $hurtPosArr[] = $pos;
                    $hit = $this->processHurt($defense, $this->team[$L][$pos], 0);
                    if ($hit) {
                        $hit = $hit * 0.25;
                    }
                    // 套装 / 坐骑最终免伤（lastjianhurt）
                    $hit = self::lastJianHurt($this->team[$L][$pos], $hit);
                    $hit = (int)$hit;

                    $newBlood = $this->team[$L][$pos]['blood'] - $hit;

                    // 攻击方神诸葛空城计（触发者和防御者都是自己）
                    if (isset($this->team[$L][$pos]['tili'])
                        && (int)(self::skill($this->team[$L][$pos]['skillid'])['effect'] ?? 0) === 26
                        && $newBlood <= 0
                        && !isset($this->kongcheng[$trueSite]['isself' . $pos])
                        && !isset($this->kongcheng[$trueSite][$pos])
                    ) {
                        $this->kongcheng[$trueSite]['isself' . $pos] = [
                            'endsite' => 0,
                            'paramete' => self::skill($this->team[$L][$pos]['skillid'])['paramete'],
                            'actpos' => $pos,
                            'round' => $round,
                        ];
                        // 恢复血量到 10%
                        $newBlood = (int)($this->team[$L][$pos]['allblood'] * 10 / 100);
                        $this->attackLog['hurt'][] = ['pos' => $pos, 'blood' => 0, 'self' => 1, 'faint' => $faint, 'kongcheng' => $newBlood];
                    }
                    if ($newBlood <= 0) {
                        $hit = $this->team[$L][$pos]['blood'];
                        unset($this->team[$L][$pos]);
                    } else {
                        $this->team[$L][$pos]['blood'] = $newBlood;
                        $power = $this->team[$L][$pos]['power'];
                    }
                }
            }

            // 记录攻击方士气
            $this->attackLog['attackpower'] = isset($this->team[$L][$attackPos]) ? $this->team[$L][$attackPos]['power'] : 0;
            if ($endrighttimes > 0) {
                $this->attackLog['hurt'][] = [
                    'pos' => $endrighttimes,
                    'blood' => 0,
                    'power' => 0,
                    'self' => 1,
                    'faint' => $faint,
                    'endkongcheng' => 1,
                ];
            }
            // 战斗记录
            if (in_array($pos, $hurtPosArr, true)) {
                $tmpl = [
                    'pos' => $pos,
                    'blood' => $hit,
                    'power' => $power,
                    'self' => $self,
                    'faint' => $faint,
                ];
                // 神诸葛技能
                if ($isOpen > 0) {
                    $tmpl['kongcheng'] = $isOpen;
                }
                if ($endtimes > 0 && $openSelf > 0) {
                    $this->attackLog['hurt'][] = [
                        'pos' => $endtimes,
                        'blood' => 0,
                        'power' => 0,
                        'self' => 0,
                        'faint' => $faint,
                        'endkongcheng' => 1,
                    ];
                } elseif ($endtimes > 0 && !isset($tmpl['kongcheng'])) {
                    $tmpl['endkongcheng'] = 1;
                }
                $this->attackLog['hurt'][] = $tmpl;
            }
            if ($additionEffect !== []) {
                if ($this->doubleCoe > 0) {
                    $additionEffect['noy'] = 1;  // 不扣士气
                }
                $this->attackLog['hurt'][] = $additionEffect;
            }
        }

        // 记录战斗结果
        $this->attackLogArr[] = $this->attackLog;
        // 记录连击
        if ($attackLog2 !== []) {
            $this->attackLogArr[] = $attackLog2;
        }
        // 胜负判定
        if ($this->team['right'] === []) {
            return 1;
        }
        if ($this->team['left'] === []) {
            return 2;
        }
        return 0;
    }

    /**
     * 攻击方士气或血量最少的一人或多人的位置（移植 _get_position）
     *
     * @return list<int>
     */
    private function getPosition(mixed $id, int $num = 5): array
    {
        $return = [];
        $team = $this->team[$this->launch];
        $effect = (int)(self::skill($id)['effect'] ?? 0);

        // 加气时只给自己之外的人加
        $isPowerUp = in_array($effect, [7, 9, 10], true) ? 1 : 0;

        foreach ($team as $key => $value) {
            if ($isPowerUp && $key === $this->attackLog['attackor']) {
                continue;
            }
            if (count($return) < $num) {
                $return[] = $key;
            } else {
                // 旧版逐个比较并替换，被替换下来的单位继续和后面的位置比较
                foreach ($return as $k => $v) {
                    if ($effect === 4 && $value['blood'] / $value['allblood'] < $team[$v]['blood'] / $team[$v]['allblood']) {
                        // 加血
                        $value = $team[$v];
                        $return[$k] = $key;
                    } elseif ($isPowerUp && $value['power'] < $team[$v]['power']) {
                        // 加气
                        $value = $team[$v];
                        $return[$k] = $key;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * 随机选择敌方受击目标（移植 _get_to_position：array_rand，按站位顺序返回）
     *
     * @return list<int>
     */
    private function getToPosition(int $num = 5): array
    {
        $team = $this->team[$this->toSide];
        $count = count($team);
        $num = $num > $count ? $count : $num;
        return $this->rng->pickArrayKeys($team, $num);
    }

    /**
     * 攻击第一目标位置（移植 process_position）：先找攻击位同一横排（1/4/7、2/5/8、3/6/9）由前到后的第一个，
     * 没有再按旧版顺序找其他横排
     *
     * @param list<int> $positionArr
     */
    private function processPosition(array $positionArr): int
    {
        $attackPos = $this->attackLog['attackor'];
        $return = 0;

        $posArr = [
            0 => [3, 6, 9],
            1 => [1, 4, 7],
            2 => [2, 5, 8],
        ];

        $i = 0;
        while (!$return) {
            $key = $attackPos % 3;
            if ($key === 1) {
                if ($i === 1) {
                    $key = 2;
                } elseif ($i === 2) {
                    $key = 0;
                }
            } elseif ($key === 2) {
                if ($i === 1) {
                    $key = 1;
                } elseif ($i === 2) {
                    $key = 0;
                }
            } else {
                if ($i === 1) {
                    $key = 2;
                } elseif ($i === 2) {
                    $key = 1;
                }
            }
            foreach ($posArr[$key] as $value) {
                if (in_array($value, $positionArr, true)) {
                    $return = $value;
                    break;
                }
            }
            $i++;
        }

        return $return;
    }

    /**
     * 伤害计算公式（移植 process_hurt）
     *
     * @param array<string, mixed> $attack
     * @param array<string, mixed> $defense
     * @param int $type 旧版治疗传 1 时不计防御；旧版唯一的治疗调用实际没有传入（见 processEffect effect 4）
     */
    private function processHurt(array $attack, array $defense, int $hit = 1, int $type = 0): int
    {
        $round = $this->logRound();
        // 武力 / 智力差系数：猛将比武力、谋士（及其他类型）比智力，相差 200 为 1.5 倍；区间 [0.5, 1.5]
        if ((int)$attack['type'] === 1) {
            $typeCoe = 1 + ($attack['wuli'] - $defense['wuli']) / 400;
        } else {
            $typeCoe = 1 + ($attack['zhili'] - $defense['zhili']) / 400;
        }
        if ($typeCoe >= 1.5) {
            $typeCoe = 1.5;
        } elseif ($typeCoe < 0.5) {
            $typeCoe = 0.5;
        }

        // 天赋系数
        // 依赖未移植：名将进阶 skill1_value / skill2_value / skill3_value 加到天赋系数上
        $attackTalentCoe = $defenseTalentCoe = 1;
        if ($attack['talentid']) {
            // 猛将取物攻系数，其他取策攻系数
            $attackTalentCoe = self::talent($attack['talentid'], (int)$attack['type'] === 1 ? 'pattack' : 'sattack');
        }

        // 防御值：旧版只在防御方有天赋时才赋值（猛将打物防 defense1、其他打策防 defense2），
        // 没有天赋时取到未赋值的下标，按 0 参与运算
        $defenseValue = 0;
        if ($defense['talentid']) {
            if ((int)$attack['type'] === 1) {
                $defenseTalentCoe = self::talent($defense['talentid'], 'pdefense');
                $defenseValue = $defense['defense1'];
            } else {
                $defenseTalentCoe = self::talent($defense['talentid'], 'sdefense');
                $defenseValue = $defense['defense2'];
            }
        }

        if ($type > 0) {
            // 治疗公式不计算防御
            $defenseValue = 0;
        }

        // 技能系数
        $skillCoe = 1;
        if ($attack['skillid']) {
            $skill = self::skill($attack['skillid']);
            $effect = (int)($skill['effect'] ?? 0);
            $ratio = $skill['ratio'] ?? 0;
            if ($this->attackLog['skill'] >= 1 && $ratio != 0) {
                // 可以释放技能并且有技能参数
                if (in_array($effect, [12, 13, 21, 22], true)) {
                    // 血量每减少 10% 增加 paramete
                    $bloodCoe = (int)(($attack['allblood'] - $attack['blood']) / $attack['allblood'] * 10);
                    $skillCoe = $ratio + $skill['paramete'] * $bloodCoe;
                } elseif (in_array($effect, [15, 24, 26], true)) {
                    // 24 神曹操、26 神诸葛：血量每减少 1% 伤害增加 1%
                    $bloodCoe = (int)(($attack['allblood'] - $attack['blood']) / $attack['allblood'] * 100) / 100;
                    $skillCoe = $ratio * (1 + $bloodCoe);
                } elseif ($effect === 16) {
                    // 血量低于 20% 伤害加倍
                    $skillCoe = $ratio;
                    if ($attack['blood'] < ($attack['allblood'] * 0.2)) {
                        $skillCoe *= 2;
                    }
                } elseif ($effect === 23) {
                    // 神孙权：血量低于 30% 伤害加倍
                    $skillCoe = $ratio;
                    if ($attack['blood'] < ($attack['allblood'] * 0.3)) {
                        $skillCoe *= 2;
                    }
                } elseif ($this->doubleCoe > 0) {
                    // 神吕蒙无理由伤害翻倍；世界 / 国家 boss 战改为 +5
                    $skillCoe = $ratio;
                    if ($this->bossBattle === 1 || $this->bossBattle === 2) {
                        $skillCoe += 5;
                    } else {
                        $skillCoe *= 2;
                    }
                } else {
                    $skillCoe = $ratio;
                }
            } else {
                // 普通攻击的技能加成
                if (in_array($effect, [15, 24, 26], true)) {
                    // 血量减少 1% 伤害增加 1%
                    $bloodCoe = (int)(($attack['allblood'] - $attack['blood']) / $attack['allblood'] * 100) / 100;
                    $skillCoe += $bloodCoe;
                } elseif ($effect === 16) {
                    // 血量低于 20% 普通攻击加倍
                    if ($attack['blood'] < ($attack['allblood'] * 0.2)) {
                        $skillCoe *= 2;
                    }
                } elseif ($effect === 23) {
                    // 血量低于 30% 普通攻击加倍
                    if ($attack['blood'] < ($attack['allblood'] * 0.3)) {
                        $skillCoe *= 2;
                    }
                }
            }
        }

        // 依赖未移植：套装血量伤害倍增（jianuphurt）

        // 基础战斗公式
        $hurt = $typeCoe * ($attack['attack'] * $attackTalentCoe * $this->attackCoe - $defenseValue * $defenseTalentCoe) * $skillCoe;

        // 阵法加成：识破时攻守阵法反转
        $hurtAdd = $hurtSub = 0;
        $attackSide = $this->launch;
        if ($this->attackLog['attacktype'] === -2) {
            $attackSide = $attackSide === 'left' ? 'right' : 'left';
        }
        $defenseSide = $attackSide === 'left' ? 'right' : 'left';
        $atkMatrix = $this->matrix[$attackSide];
        $defMatrix = $this->matrix[$defenseSide];
        if ((int)$attack['type'] === 1) {
            // 物理攻击：攻方阵法 1 / 6 / 7 加攻，守方阵法 2 / 5 / 7 减伤
            if (in_array($atkMatrix['type'], [1, 6, 7], true)) {
                $hurtAdd = $hurt * $atkMatrix['coe'];
            }
            if (in_array($defMatrix['type'], [2, 5, 7], true)) {
                $hurtSub = $hurt * $defMatrix['coe'];
            }
        } else {
            // 策略攻击：攻方阵法 3 / 6 / 7 加攻，守方阵法 4 / 5 / 7 减伤
            if (in_array($atkMatrix['type'], [3, 6, 7], true)) {
                $hurtAdd = $hurt * $atkMatrix['coe'];
            }
            if (in_array($defMatrix['type'], [4, 5, 7], true)) {
                $hurtSub = $hurt * $defMatrix['coe'];
            }
        }

        $hurt = $hurt + $hurtAdd - $hurtSub;

        // 最小伤害值
        if ($hurt <= 1) {
            $hurt = $this->rand(1, 10);
        }

        // 暴击 1.5 倍
        if ($hit) {
            // 依赖未移植：将魂(8) / 美人 / 星魄 / 套装暴击伤害加成
            $hurt = $hurt * 1.5;
        }

        // 依赖未移植：套装最终伤害加成（lasthurt）

        // 天神守护（effect 18）：受击位减半
        $pos = $this->defensePos;
        if (isset($this->shouhu[$defenseSide][$pos]) && $this->shouhu[$defenseSide][$pos] >= $round - 1) {
            $hurt *= 0.5;
        }

        // 防御方触发空城计（effect 26）
        if (isset($this->kongcheng[$defenseSide][$pos]) && $this->kongcheng[$defenseSide][$pos]['endsite'] <= 0) {
            $hurt *= (100 - $this->kongcheng[$defenseSide][$pos]['paramete']) / 100;
        } elseif (isset($this->kongcheng[$defenseSide]['isself' . $pos]) && $this->kongcheng[$defenseSide]['isself' . $pos]['endsite'] <= 0) {
            $hurt *= (100 - $this->kongcheng[$defenseSide]['isself' . $pos]['paramete']) / 100;
        }

        // 增加伤害（effect 20）
        if (isset($this->jiashanghai[$this->launch]) && $this->jiashanghai[$this->launch]['round'] >= $round - 1) {
            $hurt = $hurt * (1 + (0.01 * $this->jiashanghai[$this->launch]['num']));
        }

        return (int)$hurt;
    }

    /**
     * 战斗评级（移植 _battle_assess）：胜 3 / 2 / 1，负 -3 / -2 / -1，250 回合未分胜负为 0
     */
    private function battleAssess(): int
    {
        $blood = 0;
        $return = 0;

        if ($this->ko === 1) {
            foreach ($this->team['left'] as $value) {
                $blood += $value['blood'];
            }
            $per = (int)(100 * $blood / $this->leftAllBlood);
            if ($per >= 70) {
                $return = 3;
            } elseif ($per > 40) {
                $return = 2;
            } else {
                $return = 1;
            }
        } elseif ($this->ko === 2) {
            foreach ($this->team['right'] as $value) {
                $blood += $value['blood'];
            }
            $per = (int)(100 * $blood / $this->rightAllBlood);
            if ($per >= 70) {
                $return = -3;
            } elseif ($per > 40) {
                $return = -2;
            } else {
                $return = -1;
            }
        }
        return $return;
    }

    private function rand(int $min, int $max): int
    {
        return $this->rng->getInt($min, $max);
    }

    /**
     * 天赋字段；天赋不存在时旧版取到 null，按 0 参与运算
     */
    private static function talent(mixed $id, string $field): int|float
    {
        return Talent::ROWS[(int)$id][$field] ?? 0;
    }

    /**
     * 技能行；paramete / ratio 为整数值时按整数返回（旧版取自库里的字符串，参与运算后同为整数）
     *
     * @return array<string, mixed>|null
     */
    private static function skill(mixed $id): ?array
    {
        $row = Skill::ROWS[(int)$id] ?? null;
        if ($row === null) {
            return null;
        }
        foreach (['paramete', 'ratio'] as $field) {
            $v = $row[$field];
            if (is_float($v) && $v === floor($v)) {
                $row[$field] = (int)$v;
            }
        }
        return $row;
    }
}
