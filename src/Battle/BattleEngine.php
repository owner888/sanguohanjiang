<?php

declare(strict_types=1);

namespace Hanjiang\Battle;

/**
 * 确定性九宫格回合制战斗推演引擎
 * 移植与现代化重构自原版 battle_model.php
 */
final class BattleEngine
{
    /** @var array<int, Hero> */
    private array $leftTeam = [];
    /** @var array<int, Hero> */
    private array $rightTeam = [];

    /** @var list<array<string, mixed>> */
    private array $combatLog = [];

    public function __construct(
        array $leftHeroes,
        array $rightHeroes,
        public readonly int $maxRounds = 30
    ) {
        foreach ($leftHeroes as $h) {
            $this->leftTeam[$h->pos->value] = $h;
        }
        foreach ($rightHeroes as $h) {
            $this->rightTeam[$h->pos->value] = $h;
        }
    }

    /**
     * 执行整场推演，输出可直接发送给客户端渲染的回放帧与胜负结果
     *
     * @return array{win: bool, rounds: int, logs: list<array<string, mixed>>}
     */
    public function simulate(): array
    {
        $round = 1;
        while ($round <= $this->maxRounds) {
            if (!$this->isTeamAlive($this->leftTeam) || !$this->isTeamAlive($this->rightTeam)) {
                break;
            }

            $this->simulateRound($round);
            $round++;
        }

        $leftWin = $this->isTeamAlive($this->leftTeam) && !$this->isTeamAlive($this->rightTeam);

        // 原版经典战斗评级算法 (_battle_assess)
        $leftInitialHp = array_sum(array_map(fn($h) => $h->maxHp, $this->leftTeam));
        $leftRemainHp = array_sum(array_map(fn($h) => max(0, $h->currentHp), $this->leftTeam));
        $rightInitialHp = array_sum(array_map(fn($h) => $h->maxHp, $this->rightTeam));
        $rightRemainHp = array_sum(array_map(fn($h) => max(0, $h->currentHp), $this->rightTeam));

        if ($leftWin) {
            $ratio = $leftRemainHp / max(1, $leftInitialHp);
            $assess = match (true) {
                $ratio >= 0.7 => 3, // 三星完胜
                $ratio >= 0.4 => 2, // 二星大胜
                default       => 1, // 一星小胜
            };
        } else {
            $ratio = $rightRemainHp / max(1, $rightInitialHp);
            $assess = match (true) {
                $ratio >= 0.7 => -3, // 完败
                $ratio >= 0.4 => -2, // 大败
                default       => -1, // 惜败
            };
        }

        return [
            'win' => $leftWin,
            'rounds' => $round - 1,
            'assess' => $assess,
            'logs' => $this->combatLog,
        ];
    }

    private function simulateRound(int $round): void
    {
        // 收集所有存活英雄按出手速度降序行动
        /** @var list<array{side: 'left'|'right', hero: Hero}> $actors */
        $actors = [];
        foreach ($this->leftTeam as $h) {
            if ($h->isAlive()) {
                $actors[] = ['side' => 'left', 'hero' => $h];
            }
        }
        foreach ($this->rightTeam as $h) {
            if ($h->isAlive()) {
                $actors[] = ['side' => 'right', 'hero' => $h];
            }
        }

        usort($actors, static fn($a, $b) => $b['hero']->speed <=> $a['hero']->speed);

        foreach ($actors as $actor) {
            if (!$actor['hero']->isAlive()) {
                continue;
            }

            $attacker = $actor['hero'];
            $target = $actor['side'] === 'left'
                ? $this->findTarget($this->rightTeam, $attacker->pos)
                : $this->findTarget($this->leftTeam, $attacker->pos);

            if ($target === null) {
                break;
            }

            // 基础伤害计算公式: max(1, 攻击 * 攻击系数 - 防御 * 防御系数)
            $rawDamage = max(10, (int)($attacker->attack * 1.2 - $target->defense * 0.8));
            $isCrit = ($attacker->anger >= $attacker->maxAnger);
            if ($isCrit) {
                $rawDamage = (int)($rawDamage * 1.5);
                $attacker->anger = 0;
            } else {
                $attacker->anger = min($attacker->maxAnger, $attacker->anger + 35);
            }

            $taken = $target->takeDamage($rawDamage);

            $this->combatLog[] = [
                'round' => $round,
                'side' => $actor['side'],
                'attacker_pos' => $attacker->pos->value,
                'target_pos' => $target->pos->value,
                'damage' => $taken,
                'is_crit' => $isCrit,
                'target_hp' => $target->currentHp,
            ];
        }
    }

    /**
     * @param array<int, Hero> $team
     */
    private function isTeamAlive(array $team): bool
    {
        foreach ($team as $h) {
            if ($h->isAlive()) {
                return true;
            }
        }
        return false;
    }

    /**
     * 原版九宫格寻敌规则: 优先前排对位，若对位无人顺延寻找
     *
     * @param array<int, Hero> $defenders
     */
    private function findTarget(array $defenders, Position $attackerPos): ?Hero
    {
        // 简单优先规则：从 1 到 9 寻找第一个存活单位
        foreach ($defenders as $h) {
            if ($h->isAlive()) {
                return $h;
            }
        }
        return null;
    }
}
