<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Hanjiang\Handler\IosHandler;
use Hanjiang\Handler\MemberHandler;
use Hanjiang\Handler\RegisterHandler;
use Hanjiang\Handler\UserHandler;
use Hanjiang\Http\Router;
use Hanjiang\Middleware\CorsMiddleware;
use Hanjiang\Middleware\GameProtocolMiddleware;
use Hanjiang\Middleware\ResponseEnvelopeMiddleware;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Worker;

$config = require __DIR__ . '/config/app.php';

$httpWorker = new Worker($config['http']['listen']);
$httpWorker->count = $config['http']['count'];
$httpWorker->name = 'Hanjiang-Http-Gateway';

$router = new Router($config['http']['public_path']);

// 1. 注册中间件流水线 (严格遵循 smart-book 架构)
$router->use(new CorsMiddleware());
$router->use(new ResponseEnvelopeMiddleware());
$router->use(new GameProtocolMiddleware([
    'ios' => IosHandler::class,
    'user' => UserHandler::class,
    'register' => RegisterHandler::class,
    'member' => MemberHandler::class,
]));

$httpWorker->onMessage = static function (TcpConnection $connection, Request $request) use ($router): void {
    $router->dispatch($connection, $request);
};

echo "Starting {$config['name']} on {$config['http']['listen']}...\n";

Worker::runAll();
