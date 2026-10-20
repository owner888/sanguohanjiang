<?php

declare(strict_types=1);

namespace Hanjiang\Enum;

/**
 * 势力与关卡状态枚举
 */
enum FactionStatus: int
{
    case Locked = 0;    // 未解锁
    case Active = 1;    // 攻略中 / 已开启
    case Cleared = 2;   // 已全通关

    public function label(): string
    {
        return match ($this) {
            self::Locked => '未开启',
            self::Active => '已开启',
            self::Cleared => '已通关',
        };
    }

    public function isAvailable(): bool
    {
        return $this !== self::Locked;
    }
}
