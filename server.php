<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('Asia/Shanghai');

use Hanjiang\Handler\HandlerRegistry;
use Hanjiang\Http\Router;
use Hanjiang\Middleware\CorsMiddleware;
use Hanjiang\Middleware\GameProtocolMiddleware;
use Hanjiang\Middleware\ResponseEnvelopeMiddleware;
use Hanjiang\Middleware\ThrottleMiddleware;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Worker;

$config = require __DIR__ . '/config/app.php';
$dbConfig = require __DIR__ . '/config/database.php';

// -------------------------------------------------------------
// 1. HTTP 游戏业务网关
// -------------------------------------------------------------
$httpWorker = new Worker($config['http']['listen']);
$httpWorker->count = $config['http']['count'];
$httpWorker->name = 'Hanjiang-Http-Gateway';

$router = new Router($config['http']['public_path']);
$router->use(new CorsMiddleware());
$router->use(new ResponseEnvelopeMiddleware());
$router->use(new ThrottleMiddleware());
// 自动挂载 7 大领域子目录下的全部 Handler 映射 (驼峰与下划线双向兼容)
$router->use(new GameProtocolMiddleware(HandlerRegistry::all()));

// 【架构铁律】：长连接与子进程 Fork 隔离纪律
// 严禁在 Master 主进程提前建立连接，必须在每个 Worker 启动事件中独立创建连接池，彻底杜绝并发 Socket 句柄竞争！
$httpWorker->onWorkerStart = static function (Worker $worker) use ($dbConfig): void {
    DB::init($dbConfig['mysql']);
    Redis::init($dbConfig['redis']);
};

$httpWorker->onMessage = static function (TcpConnection $connection, Request $request) use ($router): void {
    $router->dispatch($connection, $request);
};

// -------------------------------------------------------------
// 2. 原生全双工 WebSocket 网关
// -------------------------------------------------------------
if (!empty($config['ws']['listen'])) {
    $wsWorker = new Worker($config['ws']['listen']);
    $wsWorker->count = $config['ws']['count'] ?? 1;
    $wsWorker->name = 'Hanjiang-WebSocket-Gateway';

    $wsWorker->onWorkerStart = static function (Worker $worker) use ($dbConfig): void {
        DB::init($dbConfig['mysql']);
        Redis::init($dbConfig['redis']);
        \Hanjiang\WebSocket\WebSocketHandler::init();
    };

    $wsWorker->onConnect = [Hanjiang\WebSocket\WebSocketHandler::class, 'onConnect'];
    $wsWorker->onClose = [Hanjiang\WebSocket\WebSocketHandler::class, 'onClose'];
    $wsWorker->onMessage = [Hanjiang\WebSocket\WebSocketHandler::class, 'onMessage'];
    // 对齐 smart-book 规范：注册协议级 ping (Opcode 0x9) 回调，自动回 pong 帧并刷新连接活跃时间
    $wsWorker->onWebSocketPing = [Hanjiang\WebSocket\WebSocketHandler::class, 'onWebSocketPing'];
}

echo "Starting {$config['name']} on {$config['http']['listen']} and {$config['ws']['listen']}...\n";

Worker::runAll();
