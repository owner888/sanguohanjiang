<?php

declare(strict_types=1);

/**
 * PHPUnit 启动：与 server.php 同一套初始化（OmniPHP Application::boot）+ 同一份 routes.php，
 * 再为本测试进程建立 MySQL / Redis 连接（测试进程就是最终进程，不存在 fork 共享 fd 的问题）
 */

require dirname(__DIR__) . '/bootstrap.php';
require BASE_PATH . '/routes.php';

\Hanjiang\Support\Connections::init();
