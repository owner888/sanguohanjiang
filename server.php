<?php

declare(strict_types=1);

/**
 * 悍将三国服务入口（OmniPHP 框架入口栈，与 smart-book 同构）
 *
 *   php server.php start        # 前台运行
 *   php server.php start -d     # 守护进程
 *   php server.php restart -d   # 重启（改路由 / .env 必须 restart，reload 不生效）
 *   php server.php stop
 *   php server.php status
 *
 * pid、Workerman 日志、守护模式下的 stdout / PHP 报错都在 runtime/ 下（见 Application::workerman）。
 */

require_once __DIR__ . '/bootstrap.php';

use Hanjiang\Cron\CompeteCron;
use Hanjiang\Cron\CountryCron;
use Hanjiang\Cron\PassGeneralCron;
use Hanjiang\Cron\SiegeCron;
use Hanjiang\Cron\WorldBossCron;
use Hanjiang\Model\FlowLog;
use Hanjiang\Support\Connections;
use Hanjiang\WebSocket\ChatPush;
use Hanjiang\WebSocket\EliteRoom;
use Hanjiang\WebSocket\WebSocketHandler;
use OmniPHP\Application;
use OmniPHP\Config;
use OmniPHP\Http\Kernel;
use Workerman\Timer;
use Workerman\Worker;

// event loop 切到 Fiber（Revolt）+ runtime/hanjiang.pid、runtime/logs/hanjiang.log、runtime/logs/hanjiang_stdout.log
// 必须在任何 Worker 实例化之前调用；fork 之后子进程继承
Application::workerman('hanjiang');

// 路由在 master 里注册一次，fork 后子进程继承
require_once __DIR__ . '/routes.php';

// 数据库迁移不自动执行：OmniPHP MigrationRunner 只认 database/migrations/*.php（框架 Migration 类），
// 本项目的迁移是手写 .sql，按 README「数据库迁移」一节手动执行。这里也不调用 DatabaseBootstrap::init()——
// 它只在 master 里连一次再关掉，对本项目没有用处。

// -------------------------------------------------------------
// 1. HTTP 游戏业务网关
// -------------------------------------------------------------
$httpWorker = new Worker(Config::get('app.http.listen'));
$httpWorker->count = Config::get('app.http.count');
$httpWorker->name = 'Hanjiang-Http-Gateway';

// 【架构铁律】：长连接与子进程 Fork 隔离纪律
// 严禁在 Master 主进程提前建立连接，必须在每个 Worker 启动事件中独立创建连接，彻底杜绝并发 Socket 句柄竞争！
$httpWorker->onWorkerStart = static function (Worker $worker): void {
    Connections::init();

    // 全服每日计划任务只在 #0 号 Worker 上跑，避免多进程重复执行
    // （Fiber 事件循环下每次定时器回调都在独立 Fiber 里执行，FlowLog 的当前路由按 Fiber 隔离）
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
        // 计划任务里的货币流水 class / method 记为旧版计划任务控制器与方法（见 FlowLog::route）
        $cron = static function (string $class, string $method, callable $job): callable {
            return static function () use ($class, $method, $job): void {
                FlowLog::route($class, $method);
                try {
                    $job();
                } finally {
                    FlowLog::route('', '');
                }
            };
        };
        $daily('00:00', $cron('cron_country', 'clear_contribute', static function (): void {
            CountryCron::clearContribute();
        }));
        // 世界 BOSS：自动发放未领取的奖励并清空世界 BOSS 键（cron_acttask::worldboss）
        $daily(WorldBossCron::RUN_AT, $cron('cron_acttask', 'worldboss', static function (): void {
            WorldBossCron::settle(time());
        }));
        // 过关斩将：每日自动领取宝箱并重置进度、活动结束后自动兑换兑奖券与领取成就（cron_acttask::pass_general）
        $daily(PassGeneralCron::RUN_AT, $cron('cron_acttask', 'pass_general', static function (): void {
            PassGeneralCron::run(time());
        }));
        // 国战：每分钟检查是否到了某城的开战时间，算出整场战斗（cron.php:siege_battle）；每周六 00:00 清除参战状态
        Timer::add(SiegeCron::BATTLE_INTERVAL, $cron('cron', 'siege_battle', static function (): void {
            SiegeCron::battle(time());
        }));
        $daily(SiegeCron::CLEAR_AT, $cron('cron', 'clear_warstatus', static function (): void {
            SiegeCron::clearWarstatus(time());
        }));
        // 群雄逐鹿：活动结束后 10 小时内自动补领排名奖励（cron.php:get_compete_reward）
        Timer::add(CompeteCron::INTERVAL, $cron('cron', 'get_compete_reward', static function (): void {
            CompeteCron::run(time());
        }));
    }
};

$httpWorker->onMessage = [Kernel::class, 'handle'];

// -------------------------------------------------------------
// 2. 原生全双工 WebSocket 网关
// -------------------------------------------------------------
$wsListen = (string)Config::get('app.ws.listen', '');
if ($wsListen !== '') {
    $wsWorker = new Worker($wsListen);
    $wsWorker->count = Config::get('app.ws.count', 1);
    $wsWorker->name = 'Hanjiang-WebSocket-Gateway';

    $wsWorker->onWorkerStart = static function (Worker $worker): void {
        Connections::init();
        WebSocketHandler::init();
        // 多人副本组队：清理本进程上次运行残留的队伍；多进程时启动跨进程投递
        EliteRoom::init($worker->id, $worker->count);
        // 聊天室实时推送：取 HTTP 进程写入的推送通知，推给本进程上的连接
        ChatPush::init($worker->id);
    };

    $wsWorker->onWebSocketConnect = [WebSocketHandler::class, 'onWebSocketConnect'];
    $wsWorker->onClose = [WebSocketHandler::class, 'onClose'];
    $wsWorker->onMessage = [WebSocketHandler::class, 'onMessage'];
    // 对齐 smart-book 规范：注册协议级 ping (Opcode 0x9) 回调，自动回 pong 帧并刷新连接活跃时间
    $wsWorker->onWebSocketPing = [WebSocketHandler::class, 'onWebSocketPing'];
}

echo 'Starting ' . Config::get('app.name') . ' on ' . Config::get('app.http.listen') . ' and ' . $wsListen . "...\n";

Worker::runAll();
