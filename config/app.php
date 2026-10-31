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
    // 多人副本实时服务器地址（旧版 application/config/config.php 的 socket_server_url，c=elite&m=get_server 原样返回，
    // 客户端 elitepop.js 拿它 io.connect）。新版实时组队就在上面的 ws 网关（Hanjiang\WebSocket\EliteRoom）：
    // 留空时 socket.io.min.js 适配层改连 HJ_CONFIG.wsUrl（页面同主机 + ws.public_port）；ws 网关在别的域名 / 端口时
    // 配成该地址（http(s):// 或 ws(s):// 均可，适配层会转换）
    'socket_server_url' => getenv('SOCKET_SERVER_URL') ?: '',
    // 开发者测试充值（c=pay&m=test_info / test_grant，客户端设置弹窗「测试充值」）：仅环境变量 PAY_TEST=1 时开启，生产环境绝不能开启
    'pay_test' => getenv('PAY_TEST') === '1',
    'assets' => [
        'version'    => getenv('ASSET_VERSION') ?: '20261102_1',
        'static_url' => getenv('STATIC_URL') ?: 'static/',
        'cdn_url'    => getenv('CDN_URL') ?: 'static/',
        'api_url'    => getenv('API_URL') ?: 'index.php',
    ],
];
