<?php

declare(strict_types=1);

namespace Hanjiang\Enum;

/**
 * 战斗评级与胜负状态枚举
 */
enum BattleAssess: int
{
    case Defeat = -1;       // 战败
    case Victory = 1;      // 普通胜利
    case HardVictory = 2;  // 险胜
    case Perfect = 3;      // 大胜 / 完胜

    public function isWin(): bool
    {
        return $this->value > 0;
    }

    public function label(): string
    {
        return match ($this) {
            self::Defeat => '战败',
            self::Victory => '小胜',
            self::HardVictory => '险胜',
            self::Perfect => '完胜',
        };
    }
}
