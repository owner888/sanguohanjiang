<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('Asia/Shanghai');

use OmniPHP\Database\DB;
use OmniPHP\Cache\Redis;
use Hanjiang\Handler\Pvp\ArenaHandler;
use Hanjiang\Handler\Activity\ChatHandler;
use Hanjiang\Handler\City\CityHandler;
use Hanjiang\Handler\General\GeneralHandler;
use Hanjiang\Handler\Auth\IosHandler;
use Hanjiang\Handler\Auth\LoginHandler;
use Hanjiang\Handler\Activity\MainquestHandler;
use Hanjiang\Handler\Campaign\MapHandler;
use Hanjiang\Handler\Campaign\MatrixHandler;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\Equip\PackHandler;
use Hanjiang\Handler\General\PracticeHandler;
use Hanjiang\Handler\Auth\RegisterHandler;
use Hanjiang\Handler\Activity\ShopHandler;
use Hanjiang\Handler\Equip\StrengthenHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\Handler\Campaign\TowerHandler;
use Hanjiang\Handler\Campaign\PassGeneralHandler;
use Hanjiang\Handler\General\SoulHandler;
use Hanjiang\Handler\Pvp\CountryHandler;
use Hanjiang\Handler\General\CultivateHandler;
use Hanjiang\Handler\City\DailySignHandler;
use Hanjiang\Handler\Activity\FriendHandler;
use Hanjiang\Handler\Activity\MessageHandler;
use Hanjiang\Handler\Activity\MineHandler;
use Hanjiang\Handler\City\VipHandler;
use Hanjiang\Handler\Activity\BeautyHandler;
use Hanjiang\Handler\Activity\MountHandler;
use Hanjiang\Handler\Equip\GemHandler;
use Hanjiang\Handler\Pvp\HeavenHandler;
use Hanjiang\Handler\Pvp\WorldBossHandler;
use Hanjiang\Handler\Pvp\SiegeHandler;
use Hanjiang\Handler\Pvp\GvgHandler;
use Hanjiang\Handler\General\GeneralInheritHandler;
use Hanjiang\Handler\Activity\BlackmarketHandler;
use Hanjiang\Handler\Activity\FirstRechargeHandler;
use Hanjiang\Handler\Activity\OverseasTradeHandler;
use Hanjiang\Handler\Activity\StudfarmHandler;
use Hanjiang\Handler\Activity\JadegambleHandler;
use Hanjiang\Handler\Activity\TreasuremapHandler;
use Hanjiang\Handler\Activity\WorkshopHandler;
use Hanjiang\Handler\Campaign\SanctumHandler;
use Hanjiang\Handler\Pvp\CompeteHandler;
use Hanjiang\Handler\Pvp\CountrySacrificeHandler;
use Hanjiang\Handler\Activity\ExpostulationHandler;
use Hanjiang\Handler\Activity\BanquetHandler;
use Hanjiang\Handler\Activity\DrinkHandler;
use Hanjiang\Handler\Activity\DiceHandler;
use Hanjiang\Handler\Activity\SacredtreeHandler;
use Hanjiang\Handler\Activity\FlopHandler;
use Hanjiang\Handler\Activity\ChallengeHandler;
use Hanjiang\Handler\Activity\EliteHandler;
use Hanjiang\Handler\General\MusterHandler;
use Hanjiang\Handler\Activity\BusinessHandler;
use Hanjiang\Handler\Activity\GamblingHandler;
use Hanjiang\Handler\Activity\TaskHandler;
use Hanjiang\Handler\General\TalentHandler;
use Hanjiang\Handler\General\AdvancedHandler;
use Hanjiang\Handler\Equip\RefineHandler;
use Hanjiang\Handler\General\StarHandler;
use Hanjiang\Handler\Pvp\WarCollegeHandler;
use Hanjiang\Handler\Activity\LotteryHandler;
use Hanjiang\Handler\Activity\GeneraltaskHandler;
use Hanjiang\Handler\Activity\GeneralBookHandler;
use Hanjiang\Handler\Campaign\CopiesHandler;
use Hanjiang\Handler\Pvp\IslandHandler;
use Hanjiang\Handler\Pvp\HerothroneHandler;
use Hanjiang\Handler\Pvp\BeatengodHandler;
use Hanjiang\Handler\Pvp\WarHandler;
use Hanjiang\Handler\Pvp\CountryMineHandler;
use Hanjiang\Handler\Pvp\CountryBossHandler;
use Hanjiang\Handler\Activity\HiteggHandler;
use Hanjiang\Handler\City\LevelgiftHandler;
use Hanjiang\Handler\Activity\CountryShopHandler;
use Hanjiang\Handler\Equip\EquipForgeHandler;
use Hanjiang\Handler\City\TotalLoginHandler;
use Hanjiang\Handler\Campaign\EpicHandler;
use Hanjiang\Handler\City\ActivationCodeHandler;
use Hanjiang\Handler\Activity\ExerciseHandler;
use Hanjiang\Handler\Activity\InformationHandler;
use Hanjiang\Handler\Activity\InvitationHandler;
use Hanjiang\Handler\Auth\UserHandler;
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
