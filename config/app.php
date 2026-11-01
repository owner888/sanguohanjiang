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
    // 本服的服务器 id（旧版 application/config/config.php 的 serverid）。旧版的 uc（用户中心）与 battle（战斗服务器）是独立服务，
    // 新版作为本进程内的模块（Hanjiang\Cross），表在同一个库里，跨服数据按这个 id 分区；多服部署见 README「跨服」
    'server_id' => (int)(getenv('SERVER_ID') ?: 1),
    // 分享战报的展示页地址（旧版 report 服务器，c=report&m=save 返回的 link 为「该地址 + 分享编号」）。展示页不在旧版代码包里；
    // 未配置时战报照样存进 uc_report，但 save 按旧版连不上 report 服务器回 -2（分享失败），见 docs/LEGACY_GAP.md
    'report_url' => getenv('REPORT_URL') ?: '',
    // 多人副本实时服务器地址（旧版 application/config/config.php 的 socket_server_url，c=elite&m=get_server 原样返回，
    // 客户端 elitepop.js 拿它 io.connect）。新版实时组队就在上面的 ws 网关（Hanjiang\WebSocket\EliteRoom）：
    // 留空时 socket.io.min.js 适配层改连 HJ_CONFIG.wsUrl（页面同主机 + ws.public_port）；ws 网关在别的域名 / 端口时
    // 配成该地址（http(s):// 或 ws(s):// 均可，适配层会转换）
    'socket_server_url' => getenv('SOCKET_SERVER_URL') ?: '',
    // 开发者测试充值（c=pay&m=test_info / test_grant，客户端设置弹窗「测试充值」）：仅环境变量 PAY_TEST=1 时开启，生产环境绝不能开启
    'pay_test' => getenv('PAY_TEST') === '1',
    // 开发者活动时间开关（Hanjiang\Model\ActivityClock）：逗号分隔的活动名或 all，这些活动从今天 0 点开放到「启动后
    // activity_open_days 天」，用于开发环境玩旧版已结束的运营活动。默认空 = 旧版活动表原样；生产环境绝不能开启
    'activity_open' => getenv('ACTIVITY_OPEN') ?: '',
    'activity_open_days' => in_array(getenv('ACTIVITY_OPEN_DAYS'), [false, ''], true) ? 30 : (int)getenv('ACTIVITY_OPEN_DAYS'),
    'assets' => [
        'version'    => getenv('ASSET_VERSION') ?: '20261102_2',
        'static_url' => getenv('STATIC_URL') ?: 'static/',
        'cdn_url'    => getenv('CDN_URL') ?: 'static/',
        'api_url'    => getenv('API_URL') ?: 'index.php',
    ],
];
