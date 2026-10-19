<?php

declare(strict_types=1);

namespace Hanjiang\Battle;

/**
 * 九宫格战斗站位定义 (1~9号位)
 * 前排: 1, 2, 3
 * 中排: 4, 5, 6
 * 后排: 7, 8, 9
 */
enum Position: int
{
    case FrontTop = 1;
    case FrontMiddle = 2;
    case FrontBottom = 3;
    case MidTop = 4;
    case MidMiddle = 5;
    case MidBottom = 6;
    case BackTop = 7;
    case BackMiddle = 8;
    case BackBottom = 9;

    public function isFrontRow(): bool
    {
        return $this->value <= 3;
    }

    public function isMidRow(): bool
    {
        return $this->value >= 4 && $this->value <= 6;
    }

    public function isBackRow(): bool
    {
        return $this->value >= 7;
    }
}
