<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('Asia/Shanghai');

use OmniPHP\Database\DB;
use OmniPHP\Cache\Redis;
use Hanjiang\Handler\ArenaHandler;
use Hanjiang\Handler\ChatHandler;
use Hanjiang\Handler\CityHandler;
use Hanjiang\Handler\GeneralHandler;
use Hanjiang\Handler\IosHandler;
use Hanjiang\Handler\LoginHandler;
use Hanjiang\Handler\MainquestHandler;
use Hanjiang\Handler\MapHandler;
use Hanjiang\Handler\MatrixHandler;
use Hanjiang\Handler\MemberHandler;
use Hanjiang\Handler\PackHandler;
use Hanjiang\Handler\PracticeHandler;
use Hanjiang\Handler\RegisterHandler;
use Hanjiang\Handler\ShopHandler;
use Hanjiang\Handler\StrengthenHandler;
use Hanjiang\Handler\TavernHandler;
use Hanjiang\Handler\TowerHandler;
use Hanjiang\Handler\PassGeneralHandler;
use Hanjiang\Handler\SoulHandler;
use Hanjiang\Handler\CountryHandler;
use Hanjiang\Handler\CultivateHandler;
use Hanjiang\Handler\DailySignHandler;
use Hanjiang\Handler\FriendHandler;
use Hanjiang\Handler\MessageHandler;
use Hanjiang\Handler\MineHandler;
use Hanjiang\Handler\VipHandler;
use Hanjiang\Handler\BeautyHandler;
use Hanjiang\Handler\MountHandler;
use Hanjiang\Handler\GemHandler;
use Hanjiang\Handler\HeavenHandler;
use Hanjiang\Handler\WorldBossHandler;
use Hanjiang\Handler\SiegeHandler;
use Hanjiang\Handler\GvgHandler;
use Hanjiang\Handler\GeneralInheritHandler;
use Hanjiang\Handler\BlackmarketHandler;
use Hanjiang\Handler\FirstRechargeHandler;
use Hanjiang\Handler\OverseasTradeHandler;
use Hanjiang\Handler\StudfarmHandler;
use Hanjiang\Handler\JadegambleHandler;
use Hanjiang\Handler\TreasuremapHandler;
use Hanjiang\Handler\WorkshopHandler;
use Hanjiang\Handler\SanctumHandler;
use Hanjiang\Handler\CompeteHandler;
use Hanjiang\Handler\CountrySacrificeHandler;
use Hanjiang\Handler\ExpostulationHandler;
use Hanjiang\Handler\BanquetHandler;
use Hanjiang\Handler\DrinkHandler;
use Hanjiang\Handler\DiceHandler;
use Hanjiang\Handler\SacredtreeHandler;
use Hanjiang\Handler\FlopHandler;
use Hanjiang\Handler\ChallengeHandler;
use Hanjiang\Handler\EliteHandler;
use Hanjiang\Handler\MusterHandler;
use Hanjiang\Handler\BusinessHandler;
use Hanjiang\Handler\GamblingHandler;
use Hanjiang\Handler\TaskHandler;
use Hanjiang\Handler\TalentHandler;
use Hanjiang\Handler\AdvancedHandler;
use Hanjiang\Handler\RefineHandler;
use Hanjiang\Handler\StarHandler;
use Hanjiang\Handler\WarCollegeHandler;
use Hanjiang\Handler\LotteryHandler;
use Hanjiang\Handler\GeneraltaskHandler;
use Hanjiang\Handler\GeneralBookHandler;
use Hanjiang\Handler\CopiesHandler;
use Hanjiang\Handler\IslandHandler;
use Hanjiang\Handler\HerothroneHandler;
use Hanjiang\Handler\BeatengodHandler;
use Hanjiang\Handler\WarHandler;
use Hanjiang\Handler\CountryMineHandler;
use Hanjiang\Handler\CountryBossHandler;
use Hanjiang\Handler\HiteggHandler;
use Hanjiang\Handler\LevelgiftHandler;
use Hanjiang\Handler\CountryShopHandler;
use Hanjiang\Handler\EquipForgeHandler;
use Hanjiang\Handler\TotalLoginHandler;
use Hanjiang\Handler\EpicHandler;
use Hanjiang\Handler\ActivationCodeHandler;
use Hanjiang\Handler\ExerciseHandler;
use Hanjiang\Handler\InformationHandler;
use Hanjiang\Handler\InvitationHandler;
use Hanjiang\Handler\UserHandler;
use Hanjiang\Http\Router;
use Hanjiang\Middleware\CorsMiddleware;
use Hanjiang\Middleware\GameProtocolMiddleware;
use Hanjiang\Middleware\ResponseEnvelopeMiddleware;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Worker;

$config = require __DIR__ . '/config/app.php';
$dbConfig = require __DIR__ . '/config/database.php';

// 初始化数据库与 Redis
DB::init($dbConfig['mysql']);
Redis::init($dbConfig['redis']);

$httpWorker = new Worker($config['http']['listen']);
$httpWorker->count = $config['http']['count'];
$httpWorker->name = 'Hanjiang-Http-Gateway';

$router = new Router($config['http']['public_path']);

// 1. 注册中间件流水线 (严格遵循 smart-book 架构)
$router->use(new CorsMiddleware());
$router->use(new ResponseEnvelopeMiddleware());
$router->use(new GameProtocolMiddleware([
    'ios' => IosHandler::class,
    'login' => LoginHandler::class,
    'user' => UserHandler::class,
    'register' => RegisterHandler::class,
    'member' => MemberHandler::class,
    'city' => CityHandler::class,
    'map' => MapHandler::class,
    'general' => GeneralHandler::class,
    'matrix' => MatrixHandler::class,
    'pack' => PackHandler::class,
    'strengthen' => StrengthenHandler::class,
    'tavern' => TavernHandler::class,
    'practice' => PracticeHandler::class,
    'chat' => ChatHandler::class,
    'mainquest' => MainquestHandler::class,
    'shop' => ShopHandler::class,
    'arena' => ArenaHandler::class,
    'tower' => TowerHandler::class,
    'pass_general' => PassGeneralHandler::class,
    'soul' => SoulHandler::class,
    'country' => CountryHandler::class,
    'cultivate' => CultivateHandler::class,
    'dailysign' => DailySignHandler::class,
    'vip' => VipHandler::class,
    'message' => MessageHandler::class,
    'friend' => FriendHandler::class,
    'mine' => MineHandler::class,
    'beauty' => BeautyHandler::class,
    'mount' => MountHandler::class,
    'gem' => GemHandler::class,
    'heaven' => HeavenHandler::class,
    'worldboss' => WorldBossHandler::class,
    'siege' => SiegeHandler::class,
    'gvg' => GvgHandler::class,
    'general_inherit' => GeneralInheritHandler::class,
    'blackmarket' => BlackmarketHandler::class,
    'first_recharge' => FirstRechargeHandler::class,
    'overseastrade' => OverseasTradeHandler::class,
    'studfarm' => StudfarmHandler::class,
    'jadegamble' => JadegambleHandler::class,
    'treasuremap' => TreasuremapHandler::class,
    'workshop' => WorkshopHandler::class,
    'sanctum' => SanctumHandler::class,
    'compete' => CompeteHandler::class,
    'countrysacrifice' => CountrySacrificeHandler::class,
    'expostulation' => ExpostulationHandler::class,
    'banquet' => BanquetHandler::class,
    'drink' => DrinkHandler::class,
    'dice' => DiceHandler::class,
    'sacredtree' => SacredtreeHandler::class,
    'flop' => FlopHandler::class,
    'challenge' => ChallengeHandler::class,
    'elite' => EliteHandler::class,
    'muster' => MusterHandler::class,
    'business' => BusinessHandler::class,
    'gambling' => GamblingHandler::class,
    'task' => TaskHandler::class,
    'talent' => TalentHandler::class,
    'advanced' => AdvancedHandler::class,
    'refine' => RefineHandler::class,
    'star' => StarHandler::class,
    'war_college' => WarCollegeHandler::class,
    'lottery' => LotteryHandler::class,
    'generaltask' => GeneraltaskHandler::class,
    'general_book' => GeneralBookHandler::class,
    'copies' => CopiesHandler::class,
    'island' => IslandHandler::class,
    'herothrone' => HerothroneHandler::class,
    'beatengod' => BeatengodHandler::class,
    'war' => WarHandler::class,
    'countrymine' => CountryMineHandler::class,
    'countryboss' => CountryBossHandler::class,
    'hitegg' => HiteggHandler::class,
    'levelgift' => LevelgiftHandler::class,
    'countryshop' => CountryShopHandler::class,
    'equip_forge' => EquipForgeHandler::class,
    'totallogin' => TotalLoginHandler::class,
    'epic' => EpicHandler::class,
    'activationcode' => ActivationCodeHandler::class,
    'exercise' => ExerciseHandler::class,
    'information' => InformationHandler::class,
    'invitation' => InvitationHandler::class,
]));

$httpWorker->onMessage = static function (TcpConnection $connection, Request $request) use ($router): void {
    $router->dispatch($connection, $request);
};

// 2. 挂载原生全双工 WebSocket 服务
if (!empty($config['ws']['listen'])) {
    $wsWorker = new Worker($config['ws']['listen']);
    $wsWorker->count = $config['ws']['count'] ?? 1;
    $wsWorker->name = 'Hanjiang-WebSocket-Gateway';

    $wsWorker->onWorkerStart = static function (): void {
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
