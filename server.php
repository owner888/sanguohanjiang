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

use Hanjiang\Cron\BeatengodCron;
use Hanjiang\Cron\BusinessCron;
use Hanjiang\Cron\CompeteCron;
use Hanjiang\Cron\CountryCron;
use Hanjiang\Cron\ExerciseCron;
use Hanjiang\Cron\Fest3Cron;
use Hanjiang\Cron\FestivalCron;
use Hanjiang\Cron\FlopCron;
use Hanjiang\Cron\GodCron;
use Hanjiang\Cron\GvgCron;
use Hanjiang\Cron\NewgiftCron;
use Hanjiang\Cron\NewyearbossCron;
use Hanjiang\Cron\PassGeneralCron;
use Hanjiang\Cron\SiegeCron;
use Hanjiang\Cron\TreasuremapCron;
use Hanjiang\Cron\WarCron;
use Hanjiang\Cron\WorldArenaCron;
use Hanjiang\Cron\WorldBossCron;
use Hanjiang\Model\FlowLog;
use Hanjiang\Support\Connections;
use Hanjiang\Support\Migrations;
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

// 数据库自动迁移（与 smart-book 的 DatabaseBootstrap::init() 同一时机）：start / restart 时在 master 里
// 跑完 database/migrations/*.php 中待执行的迁移（空库时第一个迁移导入 database/schema.sql），然后关闭连接再 fork；
// 子进程照旧在 onWorkerStart 里 Connections::init()。stop / status / reload 等命令不碰数据库。
// 迁移失败直接退出，不带着没迁移完的库启动。不直接调 DatabaseBootstrap::init() 的原因见 Hanjiang\Support\Migrations。
if (in_array($argv[1] ?? '', ['start', 'restart'], true)) {
    try {
        $migrated = Migrations::migrateOnStart();
    } catch (Throwable $e) {
        fwrite(STDERR, '数据库迁移失败: ' . $e->getMessage() . "\n");
        exit(1);
    }
    echo $migrated === []
        ? "数据库迁移：没有待执行的迁移\n"
        : '数据库迁移：执行了 ' . count($migrated) . ' 个（' . implode('、', $migrated) . "）\n";
}

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
        // 整军演武：每日 7 点清排行（cron::clear_exercise）；活动结束后 3 天内自动开剩余礼包（cron::sale_gift）
        $daily(ExerciseCron::CLEAR_AT, $cron('cron', 'clear_exercise', static function (): void {
            ExerciseCron::clearRank();
        }));
        Timer::add(ExerciseCron::SALE_INTERVAL, $cron('cron', 'sale_gift', static function (): void {
            ExerciseCron::saleGift(time());
        }));
        // 每日通商：今天起 3 天没有任务的日期按旧版 business_model->builder 生成（旧版由后台生成，数据只到 2017-10-11）
        $business = $cron('business', 'builder', static function (): void {
            BusinessCron::run(time());
        });
        $business();
        Timer::add(BusinessCron::INTERVAL, $business);
        // 四方来朝：活动结束后 10 小时内自动领取剩余翻牌次数（cron::push_flop_reward），之后清除翻牌次数（cron::clear_flop_times）
        Timer::add(FlopCron::INTERVAL, $cron('cron', 'push_flop_reward', static function (): void {
            FlopCron::run(time());
        }));
        // 暴打名将：活动结束后 10 小时内自动发放未领取的排行奖励与宝箱奖励（cron::get_beatengod_reward / get_beatengod_box_reward）
        Timer::add(BeatengodCron::INTERVAL, $cron('cron', 'get_beatengod_reward', static function (): void {
            BeatengodCron::rank(time());
        }));
        Timer::add(BeatengodCron::INTERVAL, $cron('cron', 'get_beatengod_box_reward', static function (): void {
            BeatengodCron::box(time());
        }));
        // 春节年兽：活动结束后 10 小时内补发未领取的伤害 / 鼓舞 / 闪避排行奖励（cron::get_newyearboss_reward）
        Timer::add(NewyearbossCron::INTERVAL, $cron('cron', 'get_newyearboss_reward', static function (): void {
            NewyearbossCron::run(time());
        }));
        // 节日活动第二批：活动结束后补发未领取的奖励（七夕 / 国家庆典 / 中秋 / 国庆 / 万圣节 / 圣诞团购 / 清明 / 充值返利）
        foreach (FestivalCron::JOBS as [$cronClass, $cronMethod, $cronJob]) {
            Timer::add(FestivalCron::INTERVAL, $cron($cronClass, $cronMethod, static function () use ($cronJob): void {
                $cronJob(time());
            }));
        }
        // 节日活动第三批：每天零点的寻宝 / 充值送豪礼与累计登录 / 蹴鞠与世界杯任务，半小时一次的招募战友统计，
        // 活动结束后的领工资、貔貅聚财、七星灯补发
        foreach (Fest3Cron::DAILY as [$cronClass, $cronMethod, $cronJob]) {
            $daily(Fest3Cron::RUN_AT, $cron($cronClass, $cronMethod, static function () use ($cronJob): void {
                $cronJob(time());
            }));
        }
        [$cronClass, $cronMethod, $cronJob] = Fest3Cron::STAT;
        Timer::add(Fest3Cron::STAT_INTERVAL, $cron($cronClass, $cronMethod, static function () use ($cronJob): void {
            $cronJob(time());
        }));
        foreach (Fest3Cron::JOBS as [$cronClass, $cronMethod, $cronJob]) {
            Timer::add(Fest3Cron::INTERVAL, $cron($cronClass, $cronMethod, static function () use ($cronJob): void {
                $cronJob(time());
            }));
        }
        // 藏宝图：每日 7 点（跟征收一起）出售昨天以前获得、未组队的藏宝图（cron::sale_map）
        $daily(TreasuremapCron::RUN_AT, $cron('cron', 'sale_map', static function (): void {
            TreasuremapCron::run(time());
        }));
        // 新服大礼包：30 天活动结束后自动领取可领取的礼包并发信件（cron::get_newgift_reward）
        $daily(NewgiftCron::RUN_AT, $cron('cron', 'get_newgift_reward', static function (): void {
            NewgiftCron::run(time());
        }));
        // 跨服演武：0 点后记本服昨日前三（cron::cache_top_rank）并重置跨服排行榜（battle cron::reset_rank_list）；
        // 每 10 分钟同步榜上玩家的神树等级（battle cron::reset_member_tree）
        $daily(WorldArenaCron::RUN_AT, $cron('cron', 'cache_top_rank', static function (): void {
            WorldArenaCron::daily(time());
        }));
        Timer::add(WorldArenaCron::TREE_INTERVAL, $cron('cron', 'reset_member_tree', static function (): void {
            WorldArenaCron::tree(time());
        }));
        // 战神殿：报名结束后生成对阵，战斗阶段每局开打前 1 分钟算出全部战斗（battle god::check_pk）
        Timer::add(GodCron::INTERVAL, $cron('god', 'check_pk', static function (): void {
            GodCron::run(time());
        }));
        // 武斗会：报名结束后同步、每天开打生成对阵、每局开打前 1 分钟算出战斗、每天打完排名晋级（服务器端赛程，推定）
        Timer::add(WarCron::INTERVAL, $cron('war', 'check_pk', static function (): void {
            WarCron::run(time());
        }));
        // 群服争霸：生成参赛服务器 / 对阵 / 关注、开战前取参赛玩家并同步、每轮锁定后算出团战（battle gvg::run_pk）
        Timer::add(GvgCron::PK_INTERVAL, $cron('gvg', 'run_pk', static function (): void {
            GvgCron::runPk(time());
        }));
        // 群服争霸：押注奖励与晋级奖励（cron_gvg::bet_reward / push_member_reward）
        Timer::add(GvgCron::REWARD_INTERVAL, $cron('cron_gvg', 'bet_reward', static function (): void {
            GvgCron::betReward(time());
        }));
        Timer::add(GvgCron::REWARD_INTERVAL, $cron('cron_gvg', 'push_member_reward', static function (): void {
            GvgCron::pushMemberReward(time());
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
