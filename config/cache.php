<?php

declare(strict_types=1);

/**
 * 缓存配置（Redis 连接；对齐 smart-book 的 config/cache.php）
 *
 * 优先级：系统环境变量 > .env 文件 > 默认值。由 Hanjiang\Support\Connections::init() 传给 OmniPHP\Cache\Redis::init()。
 */

return [
    'redis' => [
        'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('REDIS_PORT') ?: 6379),
        'password' => getenv('REDIS_PASSWORD') ?: null,
        'database' => (int)(getenv('REDIS_DB') ?: 0),
        'prefix' => 'hj:',
        'timeout' => 2.5,
    ],
];
