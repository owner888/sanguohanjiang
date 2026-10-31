<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('Asia/Shanghai');

use Hanjiang\Cron\CompeteCron;
use Hanjiang\Cron\CountryCron;
use Hanjiang\Cron\PassGeneralCron;
use Hanjiang\Cron\SiegeCron;
use Hanjiang\Cron\WorldBossCron;
use Hanjiang\Handler\HandlerRegistry;
use Hanjiang\Http\Router;
use Hanjiang\Middleware\AuthMiddleware;
use Hanjiang\Middleware\CorsMiddleware;
use Hanjiang\Middleware\DailyCronMiddleware;
use Hanjiang\Middleware\GameProtocolMiddleware;
use Hanjiang\Middleware\ResponseEnvelopeMiddleware;
use Hanjiang\Middleware\ThrottleMiddleware;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Timer;
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
$router->use(new AuthMiddleware());
$router->use(new ThrottleMiddleware());
$router->use(new DailyCronMiddleware());
// 自动挂载 7 大领域子目录下的全部 Handler 映射 (驼峰与下划线双向兼容)
$router->use(new GameProtocolMiddleware(HandlerRegistry::all()));

// 【架构铁律】：长连接与子进程 Fork 隔离纪律
// 严禁在 Master 主进程提前建立连接，必须在每个 Worker 启动事件中独立创建连接池，彻底杜绝并发 Socket 句柄竞争！
$httpWorker->onWorkerStart = static function (Worker $worker) use ($dbConfig): void {
    DB::init($dbConfig['mysql']);
    Redis::init($dbConfig['redis']);

    // 全服每日计划任务只在 #0 号 Worker 上跑，避免多进程重复执行
    if ($worker->id === 0) {
        // 每天 $hm（时:分）执行一次 $job
        $daily = static function (string $hm, callable $job): void {
            $next = strtotime('today ' . $hm);
            if ($next <= time()) {
                $next = strtotime('tomorrow ' . $hm);
            }
            Timer::add($next - time(), static function () use ($job): void {
                $job();
                Timer::add(86400, $job);
            }, [], false);
        };
        $daily('00:00', static function (): void {
            CountryCron::clearContribute();
        });
        // 世界 BOSS：自动发放未领取的奖励并清空世界 BOSS 键（cron_acttask::worldboss）
        $daily(WorldBossCron::RUN_AT, static function (): void {
            WorldBossCron::settle(time());
        });
        // 过关斩将：每日自动领取宝箱并重置进度、活动结束后自动兑换兑奖券与领取成就（cron_acttask::pass_general）
        $daily(PassGeneralCron::RUN_AT, static function (): void {
            PassGeneralCron::run(time());
        });
        // 国战：每分钟检查是否到了某城的开战时间，算出整场战斗（cron.php:siege_battle）；每周六 00:00 清除参战状态
        Timer::add(SiegeCron::BATTLE_INTERVAL, static function (): void {
            SiegeCron::battle(time());
        });
        $daily(SiegeCron::CLEAR_AT, static function (): void {
            SiegeCron::clearWarstatus(time());
        });
        // 群雄逐鹿：活动结束后 10 小时内自动补领排名奖励（cron.php:get_compete_reward）
        Timer::add(CompeteCron::INTERVAL, static function (): void {
            CompeteCron::run(time());
        });
    }
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
        // 多人副本组队：清理本进程上次运行残留的队伍；多进程时启动跨进程投递
        \Hanjiang\WebSocket\EliteRoom::init($worker->id, $worker->count);
        // 聊天室实时推送：取 HTTP 进程写入的推送通知，推给本进程上的连接
        \Hanjiang\WebSocket\ChatPush::init($worker->id);
    };

    $wsWorker->onWebSocketConnect = [Hanjiang\WebSocket\WebSocketHandler::class, 'onWebSocketConnect'];
    $wsWorker->onClose = [Hanjiang\WebSocket\WebSocketHandler::class, 'onClose'];
    $wsWorker->onMessage = [Hanjiang\WebSocket\WebSocketHandler::class, 'onMessage'];
    // 对齐 smart-book 规范：注册协议级 ping (Opcode 0x9) 回调，自动回 pong 帧并刷新连接活跃时间
    $wsWorker->onWebSocketPing = [Hanjiang\WebSocket\WebSocketHandler::class, 'onWebSocketPing'];
}

echo "Starting {$config['name']} on {$config['http']['listen']} and {$config['ws']['listen']}...\n";

Worker::runAll();
