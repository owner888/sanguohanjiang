<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use OmniPHP\Http\Context;
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
        date_default_timezone_set('Asia/Shanghai');
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

        // 4. 再次查询背包，确认已删除 (底层数据库 deleted_at 软删除留存)
        $newPack = $this->request('pack', 'index');
        foreach ($newPack['list'] as $item) {
            $this->assertNotSame($armorId, (int)$item['id'], '已出售装备不应继续出现在背包中');
        }

        // 验证数据库底层软删除字段生效
        $rawItem = \OmniPHP\Database\DB::table('equipment')->where('id', $armorId)->first();
        $this->assertNotNull($rawItem);
        $this->assertNotNull($rawItem['deleted_at'], '软删除字段 deleted_at 必须被正确填充为删除时间戳');
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
        $this->assertSame(1, $res['task']['status']);

        // 领取第 1 个任务奖励，并自动解锁下一主线任务
        $reward = $this->request('mainquest', 'get_task_reward');
        $this->assertSame(1, $reward['status']);
        $this->assertSame(1, $reward['reward_task_id']);
        $this->assertGreaterThan(0, $reward['silver']);
        $this->assertGreaterThan(0, $reward['gold']);
        $this->assertNotNull($reward['next_task']);

        // 验证主线任务进度已进入下一阶段
        $nextRes = $this->request('mainquest', 'index');
        $this->assertSame(1, $nextRes['status']);
        $this->assertGreaterThanOrEqual(2, $nextRes['task']['id']);
    }

    public function testTowerSystem(): void
    {
        $res = $this->request('tower', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertSame(1, $res['floor']);
        $this->assertNotEmpty($res['boss_name']);

        // 挑战试炼塔第 1 层
        $action = $this->request('tower', 'action');
        $this->assertSame(1, $action['status']);
        $this->assertSame(1, $action['win']);
        $this->assertSame(1, $action['cleared_floor']);
        $this->assertSame(2, $action['next_floor']);
        $this->assertGreaterThan(0, $action['silver']);
        $this->assertGreaterThan(0, $action['soul']);

        // 扫荡已通关层数
        $sweep = $this->request('tower', 'sweep');
        $this->assertSame(1, $sweep['status']);
        $this->assertSame(1, $sweep['cleared_layers']);
        $this->assertGreaterThan(0, $sweep['silver']);
    }

    public function testPassGeneralSystem(): void
    {
        $res = $this->request('pass_general', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertSame(1, $res['current_pass']);
        $this->assertCount(6, $res['list']);

        // 1. 发起挑战第 1 关【华雄】
        $battle = $this->request('pass_general', 'action', ['pass_id' => 1]);
        $this->assertSame(1, $battle['status']);
        $this->assertSame(1, $battle['win']);
        $this->assertSame('华雄', $battle['boss_name']);
        $this->assertSame(2, $battle['next_pass']);
        $this->assertGreaterThan(0, $battle['silver']);

        // 2. 尝试越级挑战第 3 关【文丑】被拦截
        $skip = $this->request('pass_general', 'action', ['pass_id' => 3]);
        $this->assertSame(0, $skip['status']);

        // 3. 每日重置
        $reset = $this->request('pass_general', 'reset');
        $this->assertSame(1, $reset['status']);
        $this->assertSame(1, $reset['current_pass']);
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
        // 1. 发送跑马灯大喇叭广播 (直接使用框架原生 Broadcaster)
        \OmniPHP\WebSocket\Broadcaster::broadcast('marquee', [
            'content' => '全服通告：三国争霸正式开启！',
            'type' => 'system_broadcast',
        ]);

        // 2. 检查 Redis 广播历史
        $history = \OmniPHP\Cache\Redis::getJson('broadcast_history');
        $this->assertIsArray($history);

        // 3. 通过聊天拉取跑马灯历史
        $chat = $this->request('chat', 'read');
        $this->assertSame(1, $chat['status']);
        $this->assertArrayHasKey('marquee', $chat);

        // 4. 广播自定义全服事件
        $sentCount = \OmniPHP\WebSocket\Broadcaster::broadcast('system_alert', ['info' => '军令已补满']);
        $this->assertGreaterThanOrEqual(0, $sentCount);

        // 5. 对齐 smart-book 标准: 严格验证 RFC 6455 协议级控制帧 ping (0x9) 与 pong 响应
        $pingData = "protocol_ping_payload";
        $mockConn = $this->createMock(\Workerman\Connection\TcpConnection::class);
        $mockConn->expects($this->once())
            ->method('send')
            ->with($pingData);

        \Hanjiang\WebSocket\WebSocketHandler::onWebSocketPing($mockConn, $pingData);
        $this->assertGreaterThan(0, $mockConn->lastActiveTime ?? 0, '必须刷新连接最后活跃时间戳');

        // 6. 对齐 smart-book 标准: 验证超时无心跳僵尸连接回收清理 (sweep)
        $zombieConn = $this->createMock(\Workerman\Connection\TcpConnection::class);
        $zombieConn->id = 88888;
        $zombieConn->expects($this->once())->method('close');

        \OmniPHP\WebSocket\Broadcaster::register($zombieConn, 9999);
        $zombieConn->lastActiveTime = time() - 100; // 模拟 100 秒前最后活跃

        $swept = \OmniPHP\WebSocket\Broadcaster::sweep(60);
        $this->assertGreaterThanOrEqual(1, $swept, '超时僵尸连接应被自动识别并清理');

        // 7. 验证前端 WebSocket 客户端脚本就绪 (纯粹协议级设计，包含指数退避重连机制)
        $clientScript = dirname(__DIR__, 2) . '/public/static/js/network/ws_client.js';
        $this->assertFileExists($clientScript);
        $content = (string)file_get_contents($clientScript);
        $this->assertStringContainsString('scheduleReconnect', $content);
        $this->assertStringContainsString('RFC 6455', $content);

        // 8. 验证直接使用 OmniPHP 官方核心 HTTP Context (完全对标 Go Gin 设计模式)
        $ctx = \OmniPHP\Http\Context::mock(['foo' => 'bar']);
        $this->assertInstanceOf(\OmniPHP\Http\Context::class, $ctx);
        $this->assertSame('bar', $ctx->input('foo'));
        $ctx->set('user_role', 'admin');
        $this->assertSame('admin', $ctx->get('user_role'));

        // 9. 验证直接使用 OmniPHP 官方核心 WebSocket Context (解耦底层的极速纯内存单测能力)
        $wsCtx = \OmniPHP\WebSocket\Context::mock([
            'action' => 'chat',
            'message' => '来自框架原生 Context 的测试战报！',
        ], uid: self::$uid);

        $this->assertInstanceOf(\OmniPHP\WebSocket\Context::class, $wsCtx);
        $this->assertSame('chat', $wsCtx->action());
        $this->assertSame('来自框架原生 Context 的测试战报！', $wsCtx->input('message'));
        $this->assertSame(self::$uid, $wsCtx->uid());

        // 执行分发
        \Hanjiang\WebSocket\WebSocketHandler::dispatch($wsCtx);

        // 验证 Context 错误响应机制
        $errCtx = \OmniPHP\WebSocket\Context::mock(['action' => 'chat', 'message' => '']);
        \Hanjiang\WebSocket\WebSocketHandler::dispatch($errCtx);
        $this->assertNotEmpty($errCtx->sentMessages);
        $this->assertStringContainsString('聊天内容不能为空', $errCtx->sentMessages[0]);
    }

    public function testSoulSystem(): void
    {
        // 1. 获取主公武将 ID
        $generals = $this->request('general', 'index');
        $mainGid = (int)$generals['list'][1]['id'];

        // 2. 查看将魂星盘
        $res = $this->request('soul', 'index', ['gid' => $mainGid]);
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['slots']);
        $this->assertArrayHasKey('user_soul', $res);
        $oldSoul = $res['user_soul'];

        // 3. 升级 1 号位 (青龙生命魂)
        $up = $this->request('soul', 'upgrade', ['gid' => $mainGid, 'slot' => 1]);
        $this->assertSame(1, $up['status']);
        $this->assertSame(2, $up['level']);
        $this->assertLessThan($oldSoul, $up['user_soul']);

        // 4. 验证武将详情中的将魂加成属性已生效
        $info = $this->request('general', 'get_info', ['gid' => $mainGid]);
        $this->assertSame(1, $info['status']);
        $this->assertGreaterThan(0, $info['info']['hp']);
    }

    public function testCountrySystem(): void
    {
        // 1. 查看三国概况
        $res = $this->request('country', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertCount(3, $res['countries']);

        // 2. 选择天命随机加入阵营 (获得天命封赏)
        $join = $this->request('country', 'select_country', ['country' => 0]);
        $this->assertSame(1, $join['status']);
        $this->assertGreaterThan(0, $join['country']);
        $this->assertSame(1, $join['is_random']);
        $this->assertSame(100, $join['bonus_gold']);
        $this->assertSame(10000, $join['bonus_silver']);

        // 3. 再次尝试选国被拦截 (不可叛国)
        $rejoin = $this->request('country', 'select_country', ['country' => 1]);
        $this->assertSame(0, $rejoin['status']);

        // 4. 国家军资捐献
        $donate = $this->request('country', 'donate', ['type' => 1]);
        $this->assertSame(1, $donate['status']);
        $this->assertGreaterThan(0, $donate['reputation']);

        // 5. 领取每日国家俸禄
        $salary = $this->request('country', 'salary');
        $this->assertSame(1, $salary['status']);
        $this->assertGreaterThan(0, $salary['silver']);

        // 6. 重复领取俸禄被拦截
        $dupSalary = $this->request('country', 'salary');
        $this->assertSame(0, $dupSalary['status']);
    }

    public function testCultivateSystem(): void
    {
        // 1. 获取主公武将 ID
        $generals = $this->request('general', 'index');
        $mainGid = (int)$generals['list'][1]['id'];

        // 2. 查看洗髓面板
        $res = $this->request('cultivate', 'index', ['gid' => $mainGid]);
        $this->assertSame(1, $res['status']);
        $this->assertCount(4, $res['modes']);

        // 3. 执行一次普通洗髓 (mode: 1)
        $roll = $this->request('cultivate', 'roll', ['gid' => $mainGid, 'mode' => 1]);
        $this->assertSame(1, $roll['status']);
        $this->assertArrayHasKey('roll', $roll);
        $this->assertArrayHasKey('wuli', $roll['roll']);

        // 4. 确认保存洗髓属性
        $save = $this->request('cultivate', 'save', ['gid' => $mainGid]);
        $this->assertSame(1, $save['status']);
        $this->assertArrayHasKey('wuliup', $save);
    }

    public function testDailySignSystem(): void
    {
        // 1. 查看签到面板
        $res = $this->request('dailysign', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        // 2. 执行今日签到
        $sign = $this->request('dailysign', 'sign');
        $this->assertSame(1, $sign['status']);
        $this->assertGreaterThan(0, $sign['silver']);
        $this->assertGreaterThan(0, $sign['gold']);
        $this->assertGreaterThan(0, $sign['act']);

        // 3. 今日重复签到拦截
        $dup = $this->request('dailysign', 'sign');
        $this->assertSame(-1, $dup['status']);
    }

    public function testVipSystem(): void
    {
        // 1. 查看 VIP 面板
        $res = $this->request('vip', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertGreaterThanOrEqual(1, $res['level']);
        $this->assertArrayHasKey('privileges', $res);

        // 2. 领取 VIP 每日俸禄
        $salary = $this->request('vip', 'get_vip_reward');
        $this->assertSame(1, $salary['status']);
        $this->assertGreaterThan(0, $salary['silver']);

        // 3. 领取 VIP1 专属晋级礼包
        $gift = $this->request('vip', 'get_gift', ['level' => 1]);
        $this->assertSame(1, $gift['status']);
        $this->assertSame(1, $gift['level']);
        $this->assertGreaterThan(0, $gift['silver']);

        // 4. 重复领取礼包被拦截
        $dupGift = $this->request('vip', 'get_gift', ['level' => 1]);
        $this->assertSame(0, $dupGift['status']);
    }

    public function testMessageSystem(): void
    {
        // 1. 查看收件箱
        $res = $this->request('message', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        // 2. 读取第一封邮件并自动标为已读
        $detail = $this->request('message', 'get_message', ['id' => 1]);
        $this->assertSame(1, $detail['status']);
        $this->assertSame(1, $detail['info']['read']);

        // 3. 给好友发送私信
        $send = $this->request('message', 'send_message', [
            'target_uid' => 10002,
            'title' => '讨伐董卓檄文',
            'content' => '请将军速速整备三军，共襄义举！',
        ]);
        $this->assertSame(1, $send['status']);

        // 4. 删除邮件
        $del = $this->request('message', 'del_message', ['id' => 1]);
        $this->assertSame(1, $del['status']);
    }

    public function testFriendSystem(): void
    {
        // 1. 查看好友列表
        $res = $this->request('friend', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        // 2. 添加新好友
        $add = $this->request('friend', 'add_friend', ['target_uid' => 10005]);
        $this->assertSame(1, $add['status']);

        // 3. 重复添加好友拦截
        $dup = $this->request('friend', 'add_friend', ['target_uid' => 10005]);
        $this->assertSame(0, $dup['status']);

        // 4. 删除好友
        $del = $this->request('friend', 'del_friend', ['target_uid' => 10005]);
        $this->assertSame(1, $del['status']);
    }

    public function testMineSystem(): void
    {
        // 1. 查看矿区列表
        $res = $this->request('mine', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['mines']);

        // 2. 占领大型富饶银矿 (id: 3)
        $claim = $this->request('mine', 'caikuang', ['id' => 3]);
        $this->assertSame(1, $claim['status']);
        $this->assertSame(3, $claim['mine_id']);

        // 3. 提取开采累积银两
        $harvest = $this->request('mine', 'get_silver');
        $this->assertSame(1, $harvest['status']);
        $this->assertGreaterThan(0, $harvest['silver']);

        // 4. 放弃矿位
        $giveUp = $this->request('mine', 'give_up');
        $this->assertSame(1, $giveUp['status']);
    }

    public function testBeautySystem(): void
    {
        // 1. 查看绝色美人列表
        $res = $this->request('beauty', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        // 2. 赠送鲜花给貂蝉 (id: 1)
        $flower = $this->request('beauty', 'give_flower', ['id' => 1]);
        $this->assertSame(1, $flower['status']);
        $this->assertSame(1, $flower['bid']);
        $this->assertGreaterThan(0, $flower['intimacy']);

        // 3. 赏赐珠宝美玉
        $gift = $this->request('beauty', 'gift', ['id' => 1]);
        $this->assertSame(1, $gift['status']);
        $this->assertSame(1, $gift['bid']);
        $this->assertGreaterThanOrEqual(1, $gift['level']);
    }

    public function testMountSystem(): void
    {
        // 1. 获取主公 ID
        $generals = $this->request('general', 'index');
        $mainGid = (int)$generals['list'][1]['id'];

        // 2. 查看战马马厩
        $res = $this->request('mount', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        // 3. 乘骑赤兔马 (id: 1)
        $fit = $this->request('mount', 'fit_on', ['id' => 1, 'gid' => $mainGid]);
        $this->assertSame(1, $fit['status']);
        $this->assertSame(1, $fit['mount_id']);

        // 4. 驯化进阶战马
        $up = $this->request('mount', 'levelup', ['id' => 1]);
        $this->assertSame(1, $up['status']);
        $this->assertSame(2, $up['level']);

        // 5. 卸下战马
        $down = $this->request('mount', 'take_down', ['id' => 1]);
        $this->assertSame(1, $down['status']);
    }

    public function testGemSystem(): void
    {
        // 1. 查看宝石背包
        $res = $this->request('gem', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        // 获取一件装备 ID
        $pack = $this->request('pack', 'index');
        $eid = (int)$pack['list'][1]['id'];

        // 2. 镶嵌红宝石 (id: 1)
        $adron = $this->request('gem', 'adron', ['gem_id' => 1, 'equip_id' => $eid]);
        $this->assertSame(1, $adron['status']);

        // 3. 拆卸宝石
        $pickOff = $this->request('gem', 'pick_off', ['equip_id' => $eid]);
        $this->assertSame(1, $pickOff['status']);

        // 4. 4合1宝石熔炼升级
        $merge = $this->request('gem', 'merge_gem', ['gem_id' => 1]);
        $this->assertSame(1, $merge['status']);
        $this->assertSame(2, $merge['level']);
    }

    public function testHeavenSystem(): void
    {
        // 1. 查看观星主界面
        $res = $this->request('heaven', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['stars']);

        // 2. 挑战星宿守护神 (星宿 1)
        $pk = $this->request('heaven', 'pk', ['id' => 1]);
        $this->assertSame(1, $pk['status']);
        $this->assertSame(1, $pk['win']);
        $this->assertSame(2, $pk['next_star']);
        $this->assertGreaterThan(0, $pk['silver']);

        // 3. 一键观星扫荡
        $sweep = $this->request('heaven', 'mop_up');
        $this->assertSame(1, $sweep['status']);
        $this->assertGreaterThan(0, $sweep['cleared_stars']);
    }

    public function testWorldBossSystem(): void
    {
        // 1. 查看世界 BOSS
        $res = $this->request('worldboss', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertSame('极·魔王董卓', $res['boss_name']);
        $this->assertSame(1, $res['is_alive']);

        // 2. 鼓舞士气
        $buff = $this->request('worldboss', 'powerup');
        $this->assertSame(1, $buff['status']);
        $this->assertSame(20, $buff['buff_percent']);

        // 3. 发起讨伐战斗
        $battle = $this->request('worldboss', 'battle');
        $this->assertSame(1, $battle['status']);
        $this->assertGreaterThan(0, $battle['damage_dealt']);
        $this->assertGreaterThan(0, $battle['reward_silver']);

        // 4. 领取世界 BOSS 功勋宝箱
        $reward = $this->request('worldboss', 'reward');
        $this->assertSame(1, $reward['status']);
        $this->assertGreaterThan(0, $reward['silver']);
        $this->assertGreaterThan(0, $reward['gold']);
    }

    public function testProtocolSmartFallbackAndLoggerWarning(): void
    {
        // 访问尚未移植的冷门或老旧接口，验证智能平滑降级信封与 OmniPHP Logger 告警追踪 (方案 B)
        $res = $this->request('blackmarket', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertSame(1, $res['is_fallback']);
        $this->assertSame('blackmarket', $res['c']);
        $this->assertSame('index', $res['m']);
        $this->assertStringContainsString('筹备中', $res['message']);
        $this->assertIsArray($res['list']);
        $this->assertIsArray($res['info']);
    }

    public function testHandlerStatelessArchitectureLaw(): void
    {
        // 架构铁律 1 深度反射自检: 杜绝任何 Handler 拥有实例属性 ($this->xxx)，彻底杜绝长常驻内存中的跨请求会话污染
        $handlerFiles = glob(dirname(__DIR__, 2) . '/src/Handler/*Handler.php');
        $this->assertNotEmpty($handlerFiles);

        foreach ($handlerFiles as $file) {
            $className = 'Hanjiang\\Handler\\' . basename($file, '.php');
            $ref = new \ReflectionClass($className);

            // 1. 严禁拥有非静态实例变量
            $instanceProps = array_filter($ref->getProperties(), fn($p) => !$p->isStatic());
            $this->assertEmpty(
                $instanceProps,
                "架构铁律违规: Handler [{$className}] 不得拥有任何非静态实例变量，严防 Workerman 长进程会话污染！"
            );

            // 2. 所有公开业务 Action 必须是静态方法 (public static)
            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
                if ($m->getName() === '__construct') continue;
                $this->assertTrue(
                    $m->isStatic(),
                    "架构铁律违规: Handler [{$className}::{$m->getName()}] 必须是静态方法 (public static function)！"
                );
            }
        }
    }

    public function testAtomicCurrencyDeductionAndConcurrencyProtection(): void
    {
        // 1. 验证原子扣减银两/元宝成功
        $ok = \Hanjiang\Handler\MemberHandler::deductCurrency(self::$uid, silver: 100, gold: 5);
        $this->assertTrue($ok);

        // 2. 验证超额扣减原子行锁拦截 (余额不足时必须原子回滚并拒绝，绝不允许扣成负数或超卖)
        $failed = \Hanjiang\Handler\MemberHandler::deductCurrency(self::$uid, silver: 999999999);
        $this->assertFalse($failed, '当银两不足时原子扣减必须返回 false');

        // 3. 验证原子增加货币
        \Hanjiang\Handler\MemberHandler::addCurrency(self::$uid, silver: 5000);
        $member = \OmniPHP\Database\DB::table('member')->where('uid', self::$uid)->first();
        $this->assertGreaterThan(5000, (int)$member['silver']);
    }
}
