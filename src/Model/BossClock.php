<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use LogicException;

/**
 * 世界 BOSS / 国家 BOSS 用的当前时间（旧版控制器 / 模型里的 $this->timestamp）
 *
 * 生产环境恒为 time()。只有 PHPUnit 进程（vendor/bin/phpunit 定义了 PHPUNIT_COMPOSER_INSTALL）可以固定时间，
 * 用于测试开放时段；其他进程调用 freeze() 直接抛异常，HTTP 请求也没有任何入口能改它。
 */
final class BossClock
{
    private static ?int $frozen = null;

    public static function now(): int
    {
        return self::$frozen ?? time();
    }

    /**
     * 测试用：固定当前时间（null 恢复 time()）
     */
    public static function freeze(?int $timestamp): void
    {
        if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
            throw new LogicException('BossClock::freeze 只供 PHPUnit 测试使用');
        }
        self::$frozen = $timestamp;
    }
}
