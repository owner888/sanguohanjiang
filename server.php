<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Hanjiang\Controller\IosController;
use Hanjiang\Controller\MemberController;
use Hanjiang\Controller\RegisterController;
use Hanjiang\Controller\UserController;
use Hanjiang\Http\Router;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Worker;

$config = require __DIR__ . '/config/app.php';

$httpWorker = new Worker($config['http']['listen']);
$httpWorker->count = $config['http']['count'];
$httpWorker->name = 'Hanjiang-Http-Gateway';

$router = new Router($config['http']['public_path']);

// 注册业务控制器
$router->register('ios', new IosController());
$router->register('user', new UserController());
$router->register('register', new RegisterController());
$router->register('member', new MemberController());

$httpWorker->onMessage = static function (TcpConnection $connection, Request $request) use ($router): void {
    $router->dispatch($connection, $request);
};

echo "Starting {$config['name']} on {$config['http']['listen']}...\n";

Worker::runAll();
