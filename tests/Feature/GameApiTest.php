<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\Context;
use Hanjiang\Database\DB;
use Hanjiang\Database\Redis;
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
use Hanjiang\Handler\UserHandler;
use Hanjiang\Http\Router;
use Hanjiang\Middleware\CorsMiddleware;
use Hanjiang\Middleware\GameProtocolMiddleware;
use Hanjiang\Middleware\ResponseEnvelopeMiddleware;
use PHPUnit\Framework\TestCase;
use Workerman\Protocols\Http\Response as WorkermanResponse;

final class GameApiTest extends TestCase
{
    private static Router $router;
    private static ?string $token = null;
    private static int $uid = 0;

    public static function setUpBeforeClass(): void
    {
        $dbConfig = require dirname(__DIR__, 2) . '/config/database.php';
        DB::init($dbConfig['mysql']);
        Redis::init($dbConfig['redis']);

        $router = new Router(dirname(__DIR__, 2) . '/public');
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
        ]));

        self::$router = $router;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function request(string $controller, string $method, array $params = []): array
    {
        $inputs = array_merge([
            'c' => $controller,
            'm' => $method,
            'token' => self::$token ?? '',
            'uid' => self::$uid > 0 ? self::$uid : '',
        ], $params);

        $ctx = Context::mock($inputs);
        $res = self::$router->handleContext($ctx);

        if ($res instanceof WorkermanResponse) {
            $body = (string)$res->rawBody();
            $json = json_decode($body, true);
            $this->assertIsArray($json, "Response from {$controller}.{$method} is not valid JSON: {$body}");
            return $json;
        }

        if (is_array($res)) {
            return $res;
        }

        $this->fail("Unexpected response type from {$controller}.{$method}");
    }

    public function testIosHandshake(): void
    {
        $res = $this->request('ios', 'update_world_common');
        $this->assertSame(1, $res['status']);
        $this->assertArrayHasKey('version', $res);
    }

    public function testGuestRegistrationAndLogin(): void
    {
        $reg = $this->request('register', 'guest');
        $this->assertSame(1, $reg['status']);
        $this->assertArrayHasKey('uid', $reg);
        self::$uid = (int)$reg['uid'];
        $this->assertGreaterThan(0, self::$uid);

        $login = $this->request('login', 'guest', ['uid' => self::$uid]);
        $this->assertSame(1, $login['status']);
        $this->assertNotEmpty($login['token']);
        self::$token = (string)$login['token'];
    }

    public function testMemberProfileAndRoleSelection(): void
    {
        $member = $this->request('member', 'index');
        $this->assertSame(1, $member['status']);
        $this->assertSame(self::$uid, (int)$member['uid']);
        $this->assertArrayHasKey('daily_task', $member);
        $this->assertSame(0, $member['daily_task']['status']);

        $role = $this->request('member', 'select_role', ['sex' => 1, 'name' => '常山赵子龙']);
        $this->assertSame(1, $role['status']);

        $updated = $this->request('member', 'index');
        $this->assertSame('常山赵子龙', $updated['nickname']);
        $this->assertSame(1, (int)$updated['gender']);
    }

    public function testCitySystem(): void
    {
        $res = $this->request('city', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertArrayHasKey('times', $res);
    }

    public function testMapAndCampaignSystem(): void
    {
        $map = $this->request('map', 'index');
        $this->assertSame(1, $map['status']);
        $this->assertArrayHasKey('factions', $map);
        $this->assertNotEmpty($map['factions']);

        $scenes = $this->request('map', 'get_scene_list', ['l' => 1]);
        $this->assertSame(1, $scenes['status']);
        $this->assertArrayHasKey('list', $scenes);

        $missions = $this->request('map', 'get_mission_list', ['l' => 1, 's' => 1]);
        $this->assertSame(1, $missions['status']);
        $this->assertArrayHasKey('list', $missions);

        $detail = $this->request('map', 'mission', ['l' => 1, 's' => 1, 'id' => 1]);
        $this->assertSame(1, $detail['status']);
        $this->assertArrayHasKey('info', $detail);
        $this->assertSame('黄巾前哨', $detail['info']['name']);
        $this->assertArrayHasKey('image', $detail['info']);
    }

    public function testBattleSimulationAction(): void
    {
        $res = $this->request('map', 'action', ['l' => 1, 's' => 1, 'id' => 1]);
        $this->assertSame(1, $res['status']);
        $this->assertSame(1, $res['info']['win']);
        $this->assertArrayHasKey('battle', $res);
        $this->assertSame(3, $res['battle']['assess']);
        $this->assertNotEmpty($res['battle']['process']);
    }

    public function testGeneralSystem(): void
    {
        $res = $this->request('general', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        $info = $this->request('general', 'get_info', ['gid' => 1]);
        $this->assertSame(1, $info['status']);
    }

    public function testMatrixSystem(): void
    {
        $res = $this->request('matrix', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertSame(1, $res['now_mid']);
    }

    public function testPackSystem(): void
    {
        $res = $this->request('pack', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertArrayHasKey('list', $res);
    }

    public function testStrengthenSystem(): void
    {
        $res = $this->request('strengthen', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertSame(100, $res['rate']);
    }

    public function testTavernSystem(): void
    {
        $res = $this->request('tavern', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);
    }

    public function testPracticeSystem(): void
    {
        $res = $this->request('practice', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertSame(6, $res['maxnum']);
    }

    public function testChatSystem(): void
    {
        $send = $this->request('chat', 'send', ['message' => '测试三国聊天']);
        $this->assertSame(1, $send['status']);

        $read = $this->request('chat', 'read');
        $this->assertSame(1, $read['status']);
        $this->assertNotEmpty($read['list']);
    }

    public function testMainquestSystem(): void
    {
        $res = $this->request('mainquest', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertSame('初出茅庐', $res['task']['name']);
    }

    public function testShopSystem(): void
    {
        $res = $this->request('shop', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        $buyAct = $this->request('shop', 'buy_act');
        $this->assertSame(1, $buyAct['status']);
    }

    public function testArenaSystem(): void
    {
        $res = $this->request('arena', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        $battle = $this->request('arena', 'action', ['target_uid' => 10002]);
        $this->assertSame(1, $battle['status']);
        $this->assertSame(1, $battle['win']);
    }
}
