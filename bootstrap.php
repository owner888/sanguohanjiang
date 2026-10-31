<?php

declare(strict_types=1);

/**
 * 统一初始化文件：所有入口（server.php、tools/*.php 命令行工具、PHPUnit、tests/Support 子进程）第一行 require 本文件
 *
 * 通用部分（BASE_PATH / CONFIG_PATH / RUNTIME_PATH 常量、读 .env、时区、LOG_LEVEL、CrashHandler 全局兜底、
 * ArchitectureGuard 架构巡检）都在 OmniPHP\Application::boot() 里，这里不再重复实现。
 * 本文件不建立任何数据库 / Redis 连接：连接只能在各进程自己的启动点建立（见 Hanjiang\Support\Connections）。
 */

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    fwrite(STDERR, "依赖未安装，请先运行: composer install\n");
    exit(1);
}

require_once __DIR__ . '/vendor/autoload.php';

// 时区：boot() 取 APP_TIMEZONE（环境变量或 .env），没配时沿用当前默认时区；
// 游戏的日结、活动时段都按北京时间，所以先把默认时区设为 Asia/Shanghai
date_default_timezone_set('Asia/Shanghai');

\OmniPHP\Application::boot(__DIR__);
