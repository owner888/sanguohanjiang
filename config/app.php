<?php

declare(strict_types=1);

return [
    'name' => '悍将三国 (Modern Workerman Edition)',
    'env' => getenv('APP_ENV') ?: 'dev',
    'debug' => (bool)(getenv('APP_DEBUG') ?: true),
    'http' => [
        'listen' => getenv('HTTP_LISTEN') ?: 'http://0.0.0.0:18100',
        'count' => (int)(getenv('HTTP_WORKERS') ?: 4),
        'public_path' => dirname(__DIR__) . '/public',
    ],
    'ws' => [
        'listen' => getenv('WS_LISTEN') ?: 'websocket://0.0.0.0:18101',
        'count' => 1,
        'public_port' => (int)(getenv('WS_PUBLIC_PORT') ?: 18101),
    ],
    'server_base_port' => (int)(getenv('SERVER_BASE_PORT') ?: 18100),
    'assets' => [
        'version'    => getenv('ASSET_VERSION') ?: '20261030_2',
        'static_url' => getenv('STATIC_URL') ?: 'static/',
        'cdn_url'    => getenv('CDN_URL') ?: 'static/',
        'api_url'    => getenv('API_URL') ?: 'index.php',
    ],
];
