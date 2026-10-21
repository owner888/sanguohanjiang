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
        $this->assertArrayHasKey('buildings', $res);
        $oldTimes = (int)$res['times'];

        // 1. 普通征收
        $impose = $this->request('city', 'impose');
        $this->assertSame(1, $impose['status']);
        $this->assertGreaterThan(0, $impose['silver']);
        $this->assertSame($oldTimes - 1, $impose['levytimes']);

        // 2. 元宝强征
        $impress = $this->request('city', 'impress');
        $this->assertSame(1, $impress['status']);
        $this->assertGreaterThan(0, $impress['silver']);
        $this->assertSame(2, $impress['gold_cost']);

        // 3. 清除 CD
        $clearCd = $this->request('city', 'clear_cd');
        $this->assertSame(1, $clearCd['status']);
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
        $this->assertGreaterThan(0, $info['info']['attack']);
        $this->assertGreaterThan(0, $info['info']['defense']);
    }

    public function testMatrixSystem(): void
    {
        $res = $this->request('matrix', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertSame(1, $res['now_mid']);
        $this->assertNotEmpty($res['list']);
        $this->assertArrayHasKey('generals', $res);

        // 获取阵法详情
        $info = $this->request('matrix', 'get_info', ['mid' => 1]);
        $this->assertSame(1, $info['status']);
        $this->assertSame('鱼鳞阵', $info['info']['name']);

        // 阵法升级
        $levelUp = $this->request('matrix', 'levelup', ['mid' => 1]);
        $this->assertSame(1, $levelUp['status']);
        $this->assertSame(2, $levelUp['level']);
        $this->assertGreaterThan(0, $levelUp['cost_silver']);

        // 切换阵法至鹤翼阵 (mid: 3)
        $use = $this->request('matrix', 'use_matrix', ['mid' => 3]);
        $this->assertSame(1, $use['status']);
        $this->assertSame(3, $use['now_mid']);

        // 切换回鱼鳞阵 (mid: 1)
        $this->request('matrix', 'use_matrix', ['mid' => 1]);
    }

    public function testPackSystem(): void
    {
        $res = $this->request('pack', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertArrayHasKey('list', $res);
        $this->assertNotEmpty($res['list']);
    }

    public function testEquipmentEquipAndUnequipAttributes(): void
    {
        // 1. 获取当前武将初始属性
        $genList = $this->request('general', 'index');
        $this->assertSame(1, $genList['status']);
        $mainGeneral = $genList['list'][1];
        $gid = (int)$mainGeneral['id'];
        $baseAttack = (int)$mainGeneral['attack'];
        $baseDefense = (int)$mainGeneral['defense'];

        // 2. 获取背包中的青铜剑 (type 1) 与 青铜甲 (type 2)
        $pack = $this->request('pack', 'index');
        $weapon = null;
        $armor = null;
        foreach ($pack['list'] as $item) {
            if ($item['type'] === 1 && $weapon === null) {
                $weapon = $item;
            } elseif ($item['type'] === 2 && $armor === null) {
                $armor = $item;
            }
        }
        $this->assertNotNull($weapon, '背包中必须存在初始武器');
        $this->assertNotNull($armor, '背包中必须存在初始防具');

        // 3. 穿戴武器 (青铜剑): 验证攻击力加成
        $equipWeapon = $this->request('general', 'equip', ['gid' => $gid, 'eid' => $weapon['id']]);
        $this->assertSame(1, $equipWeapon['status']);
        $this->assertGreaterThan($baseAttack, $equipWeapon['stats']['attack']);

        // 4. 穿戴防具 (青铜甲): 验证防御力加成
        $equipArmor = $this->request('general', 'equip', ['gid' => $gid, 'eid' => $armor['id']]);
        $this->assertSame(1, $equipArmor['status']);
        $this->assertGreaterThan($baseDefense, $equipArmor['stats']['defense']);

        // 5. 验证已穿戴装备尝试出售被拦截
        $saleBlocked = $this->request('pack', 'sale', ['id' => $weapon['id']]);
        $this->assertSame(-2, $saleBlocked['status']);

        // 6. 卸下武器: 验证攻击力回退
        $unequip = $this->request('general', 'unequip', ['gid' => $gid, 'eid' => $weapon['id']]);
        $this->assertSame(1, $unequip['status']);
        $this->assertSame($baseAttack, $unequip['stats']['attack']);

        // 重新穿回武器，便于后续强化和战斗验证
        $this->request('general', 'equip', ['gid' => $gid, 'eid' => $weapon['id']]);
    }

    public function testPackEquipmentSale(): void
    {
        // 1. 获取当前武将与装备
        $genList = $this->request('general', 'index');
        $gid = (int)$genList['list'][1]['id'];

        // 2. 找到防具并卸下
        $pack = $this->request('pack', 'index');
        $armorId = 0;
        foreach ($pack['list'] as $item) {
            if ($item['type'] === 2 && $item['is_equipped'] === 1) {
                $armorId = (int)$item['id'];
                break;
            }
        }

        if ($armorId > 0) {
            $this->request('general', 'unequip', ['gid' => $gid, 'eid' => $armorId]);
        } else {
            // 从背包中挑一件未穿戴装备
            foreach ($pack['list'] as $item) {
                if ($item['is_equipped'] === 0) {
                    $armorId = (int)$item['id'];
                    break;
                }
            }
        }

        $this->assertGreaterThan(0, $armorId);

        // 3. 出售未穿戴装备
        $sale = $this->request('pack', 'sale', ['id' => $armorId]);
        $this->assertSame(1, $sale['status']);
        $this->assertGreaterThan(0, $sale['sale_price']);
        $this->assertArrayHasKey('silver', $sale);

        // 4. 再次查询背包，确认已删除
        $newPack = $this->request('pack', 'index');
        foreach ($newPack['list'] as $item) {
            $this->assertNotSame($armorId, (int)$item['id'], '已出售装备不应继续出现在背包中');
        }
    }

    public function testStrengthenSystem(): void
    {
        $res = $this->request('strengthen', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertSame(100, $res['rate']);
        $this->assertNotEmpty($res['list']);

        $firstEquip = $res['list'][1];
        $eid = (int)$firstEquip['id'];
        $oldLevel = (int)$firstEquip['level'];

        // 查询单件装备强化预览
        $info = $this->request('strengthen', 'strengthen_info', ['id' => $eid]);
        $this->assertSame(1, $info['status']);
        $this->assertArrayHasKey('cost_silver', $info['info']);

        // 执行铁匠铺装备强化
        $start = $this->request('strengthen', 'strengthen_start', ['id' => $eid]);
        $this->assertSame(1, $start['status']);
        $this->assertSame($oldLevel + 1, $start['level']);
        $this->assertGreaterThan(0, $start['cost_silver']);
        $this->assertArrayHasKey('silver', $start);
    }

    public function testTavernSystem(): void
    {
        $res = $this->request('tavern', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        // 招募名将张飞 (id: 10, 花费 5000 银两)
        $buy = $this->request('tavern', 'buy', ['id' => 10]);
        $this->assertSame(1, $buy['status']);
        $this->assertSame('张飞', $buy['name']);
        $this->assertSame(5000, $buy['cost_silver']);

        // 再次尝试招募张飞，触发唯一性拦截 (status = 4 已拥有该武将)
        $duplicateBuy = $this->request('tavern', 'buy', ['id' => 10]);
        $this->assertSame(4, $duplicateBuy['status']);

        // 验证武将列表中已包含新招募的张飞
        $generals = $this->request('general', 'index');
        $hasZhangFei = false;
        foreach ($generals['list'] as $g) {
            if ($g['name'] === '张飞') {
                $hasZhangFei = true;
                break;
            }
        }
        $this->assertTrue($hasZhangFei, '武将列表中应包含成功招募的张飞');
    }

    public function testPracticeSystem(): void
    {
        $res = $this->request('practice', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertSame(6, $res['maxnum']);
        $this->assertSame(2, $res['opennum']);

        // 获取张飞的 ID
        $generals = $this->request('general', 'index');
        $zfId = 0;
        foreach ($generals['list'] as $g) {
            if ($g['name'] === '张飞') {
                $zfId = (int)$g['id'];
                break;
            }
        }
        $this->assertGreaterThan(0, $zfId);

        // 1. 将张飞加入 1 号训练位
        $start = $this->request('practice', 'practice_start', ['pid' => 1, 'gid' => $zfId]);
        $this->assertSame(1, $start['status']);

        // 2. 尝试向 1 号训练位重复添加武将，应被拦截
        $conflictSlot = $this->request('practice', 'practice_start', ['pid' => 1, 'gid' => $zfId]);
        $this->assertSame(6, $conflictSlot['status']); // 已在训练中

        // 3. 停止 1 号训练位训练，完成挂机经验结算
        $stop = $this->request('practice', 'practice_stop', ['pid' => 1]);
        $this->assertSame(1, $stop['status']);
        $this->assertGreaterThanOrEqual(1, $stop['earned_exp']);

        // 4. 对张飞执行突飞猛进
        $leap = $this->request('practice', 'go_leap', ['gid' => $zfId, 'num' => 1]);
        $this->assertSame(1, $leap['status']);
        $this->assertSame(500, $leap['add_exp']);
        $this->assertGreaterThanOrEqual(2, $leap['level']); // 500 经验足以升到 2 级以上
    }

    public function testMultiHeroFormationAndBattleAction(): void
    {
        // 1. 获取主公与张飞的将领 ID
        $generals = $this->request('general', 'index');
        $mainId = 0;
        $zfId = 0;
        foreach ($generals['list'] as $g) {
            if ($g['name'] === '张飞') {
                $zfId = (int)$g['id'];
            } else {
                $mainId = (int)$g['id'];
            }
        }
        $this->assertGreaterThan(0, $mainId);
        $this->assertGreaterThan(0, $zfId);

        // 2. 验证防重复上阵拦截
        $duplicateFormation = "{$zfId},{$mainId},{$zfId},0,0,0,0,0,0";
        $conflict = $this->request('matrix', 'update_matrix', ['list' => $duplicateFormation]);
        $this->assertSame(-4, $conflict['status']);

        // 3. 将张飞布阵到 1 号位 (前排上)，主公在 2 号位 (前排中)
        $setZf = $this->request('matrix', 'update_matrix', ['pos' => 1, 'gid' => $zfId]);
        $this->assertSame(1, $setZf['status']);
        $this->assertSame($zfId, $setZf['positions'][1]);

        // 4. 验证武将表出征状态已实时同步
        $updatedGenerals = $this->request('general', 'index');
        $activeCount = 0;
        foreach ($updatedGenerals['list'] as $g) {
            if ((int)$g['goout'] === 1) {
                $activeCount++;
            }
        }
        $this->assertGreaterThanOrEqual(2, $activeCount, '当前应至少有两位武将处于出战状态');

        // 5. 发起推图战斗，验证多武将协同作战与阵法加成生效
        $battle = $this->request('map', 'action', ['l' => 1, 's' => 1, 'id' => 1]);
        $this->assertSame(1, $battle['status']);
        $this->assertSame(1, $battle['info']['win']);
        $this->assertArrayHasKey('battle', $battle);

        // 验证我方阵容中同时包含了 1 号位 (张飞) 和 2 号位 (主公)
        $leftTeam = $battle['battle']['leftteam'];
        $this->assertArrayHasKey(1, $leftTeam, '战场上必须存在 1 号位英雄 (张飞)');
        $this->assertArrayHasKey(2, $leftTeam, '战场上必须存在 2 号位英雄 (主公)');
        $this->assertSame('张飞', $leftTeam[1]['name']);

        // 验证战斗动作日志中有不同英雄的出手记录
        $process = $battle['battle']['process'];
        $this->assertNotEmpty($process);
        $attackors = array_column($process, 'attackor');
        $this->assertContains(1, $attackors, '张飞在战斗过程中应当有实际出手动作');
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
        $this->assertGreaterThan(0, $res['left_times']);
        $oldRank = $res['rank'];

        // 挑战 10002 号天梯对手
        $battle = $this->request('arena', 'action', ['target_uid' => 10002]);
        $this->assertSame(1, $battle['status']);
        $this->assertSame(1, $battle['win']);
        $this->assertLessThanOrEqual($oldRank, $battle['rank']);
        $this->assertSame($res['left_times'] - 1, $battle['left_times']);
        $this->assertGreaterThan(0, $battle['reputation']);

        // 领取每日演武排名俸禄
        $reward = $this->request('arena', 'get_reward');
        $this->assertSame(1, $reward['status']);
        $this->assertGreaterThan(0, $reward['silver']);
        $this->assertGreaterThan(0, $reward['reputation']);

        // 再次尝试同日领取被拦截
        $dupReward = $this->request('arena', 'get_reward');
        $this->assertSame(0, $dupReward['status']);
    }

    public function testWebSocketAndBroadcasterSystem(): void
    {
        // 1. 发送跑马灯大喇叭广播
        \Hanjiang\WebSocket\Broadcaster::notifyMarquee('全服通告：三国争霸正式开启！');

        // 2. 检查 Redis 广播历史
        $history = \Hanjiang\Database\Redis::getJson('broadcast_history');
        $this->assertIsArray($history);
        $this->assertNotEmpty($history);
        $lastBroadcast = end($history);
        $this->assertSame('marquee', $lastBroadcast['type']);
        $this->assertSame('全服通告：三国争霸正式开启！', $lastBroadcast['data']['content']);

        // 3. 通过聊天拉取跑马灯历史
        $chat = $this->request('chat', 'read');
        $this->assertSame(1, $chat['status']);
        $this->assertArrayHasKey('marquee', $chat);
        $this->assertNotEmpty($chat['marquee']);

        // 4. 广播自定义全服事件
        $sentCount = \Hanjiang\WebSocket\Broadcaster::broadcast('system_alert', ['info' => '军令已补满']);
        $this->assertGreaterThanOrEqual(0, $sentCount);

        // 5. 对齐 smart-book 标准: 验证 RFC 6455 协议级 ping (0x9) 与应用级 ping/pong 双轨机制
        $pingData = "protocol_ping_payload";
        $mockConn = $this->createMock(\Workerman\Connection\TcpConnection::class);
        $mockConn->expects($this->once())
            ->method('send')
            ->with($pingData);

        \Hanjiang\WebSocket\WebSocketHandler::onWebSocketPing($mockConn, $pingData);
        $this->assertGreaterThan(0, $mockConn->lastActiveTime ?? 0, '必须刷新连接最后活跃时间戳');

        // 验证应用层 JSON Ping: 兼容不支持原生控制帧的 H5/小程序客户端
        $mockAppConn = $this->createMock(\Workerman\Connection\TcpConnection::class);
        $mockAppConn->expects($this->once())
            ->method('send')
            ->with($this->callback(function (string $json): bool {
                $arr = json_decode($json, true);
                return is_array($arr) && ($arr['action'] ?? null) === 'pong';
            }));

        \Hanjiang\WebSocket\WebSocketHandler::onMessage($mockAppConn, (string)json_encode(['action' => 'ping']));

        // 6. 对齐 smart-book 标准: 验证超时无心跳僵尸连接回收清理 (sweep)
        $zombieConn = $this->createMock(\Workerman\Connection\TcpConnection::class);
        $zombieConn->id = 88888;
        $zombieConn->expects($this->once())->method('close');

        \Hanjiang\WebSocket\Broadcaster::register($zombieConn, 9999);
        $zombieConn->lastActiveTime = time() - 100; // 模拟 100 秒前最后活跃

        $swept = \Hanjiang\WebSocket\Broadcaster::sweep(60);
        $this->assertGreaterThanOrEqual(1, $swept, '超时僵尸连接应被自动识别并清理');

        // 7. 验证前端 WebSocket 客户端脚本就绪
        $clientScript = dirname(__DIR__, 2) . '/public/static/js/network/ws_client.js';
        $this->assertFileExists($clientScript);
        $content = (string)file_get_contents($clientScript);
        $this->assertStringContainsString('startHeartbeat', $content);
        $this->assertStringContainsString('scheduleReconnect', $content);
    }
}
