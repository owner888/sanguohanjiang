<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

/**
 * 领域处理器自动发现注册中心 (Auto Handler Registry)
 *
 * 自动递归扫描 7 大 DDD 领域子目录下的全部 Handler 类，
 * 智能构建驼峰与下划线双向兼容的控制器映射字典，彻底消除手动维护上百行映射表的样板代码。
 */
final class HandlerRegistry
{
    /** @var array<string, class-string>|null */
    private static ?array $cachedMap = null;

    /**
     * 获取全量注册的控制器映射表
     *
     * @return array<string, class-string>
     */
    public static function all(): array
    {
        if (self::$cachedMap !== null) {
            return self::$cachedMap;
        }

        $map = [];
        $files = glob(__DIR__ . '/*/*Handler.php');

        foreach ($files as $file) {
            $subDir = basename(dirname($file));
            $className = basename($file, '.php');
            /** @var class-string $fullClass */
            $fullClass = __NAMESPACE__ . "\\{$subDir}\\{$className}";

            $rawName = (string)preg_replace('/Handler$/', '', $className);

            // 1. 全小写别名 (如 general, countrymine, tavern)
            $lowerAlias = strtolower($rawName);
            $map[$lowerAlias] = $fullClass;

            // 2. 蛇形下划线别名 (如 pass_general, general_inherit, equip_forge)
            $snakeAlias = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $rawName));
            $map[$snakeAlias] = $fullClass;
        }

        self::$cachedMap = $map;
        return $map;
    }

    /**
     * 重置缓存 (供测试使用)
     */
    public static function clear(): void
    {
        self::$cachedMap = null;
    }
}
