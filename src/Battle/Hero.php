<?php

declare(strict_types=1);

namespace Hanjiang\Battle;

final class Hero
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly Position $pos,
        public int $currentHp,
        public readonly int $maxHp,
        public readonly int $attack,
        public readonly int $defense,
        public readonly int $speed,
        public int $anger = 0,
        public readonly int $maxAnger = 100,
    ) {
    }

    public function isAlive(): bool
    {
        return $this->currentHp > 0;
    }

    public function takeDamage(int $damage): int
    {
        $actual = min($this->currentHp, max(1, $damage));
        $this->currentHp -= $actual;
        // 受击增加怒气
        $this->anger = min($this->maxAnger, $this->anger + 25);
        return $actual;
    }
}
