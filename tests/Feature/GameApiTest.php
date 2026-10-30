<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\Model\Member;
use Hanjiang\Model\General;
use Hanjiang\Model\Equipment;
use OmniPHP\Http\Context;
use OmniPHP\Database\DB;
use OmniPHP\Cache\Redis;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\HandlerRegistry;
use Hanjiang\Http\Router;
use Hanjiang\Cron\CountryCron;
use Hanjiang\Middleware\AuthMiddleware;
use Hanjiang\Middleware\CorsMiddleware;
use Hanjiang\Middleware\DailyCronMiddleware;
use Hanjiang\Middleware\GameProtocolMiddleware;
use Hanjiang\Middleware\ResponseEnvelopeMiddleware;
use Hanjiang\Middleware\ThrottleMiddleware;
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
        $router->use(new AuthMiddleware());
        $router->use(new ThrottleMiddleware());
        $router->use(new DailyCronMiddleware());
        // 自动发现并挂载全量 7 大领域子目录下的全部 Handler 映射 (驼峰与下划线双向兼容)
        $router->use(new GameProtocolMiddleware(HandlerRegistry::all()));

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

        $login = $this->request('login', 'guest', ['uid' => self::$uid, 'guestkey' => $reg['guestkey']]);
        $this->assertSame(1, $login['status']);
        $this->assertNotEmpty($login['token']);
        self::$token = (string)$login['token'];

        // 游客密钥必填且必须匹配；不再有「不传 uid 登录最新游客」
        $this->assertSame(-4, $this->request('login', 'guest', ['token' => '', 'uid' => self::$uid])['status']);
        $this->assertSame(-1, $this->request('login', 'guest', ['token' => '', 'uid' => self::$uid, 'guestkey' => 'wrong'])['status']);
        $this->assertSame(-4, $this->request('login', 'guest', ['token' => '', 'uid' => '', 'guestkey' => $reg['guestkey']])['status']);
    }

    public function testMemberProfileAndRoleSelection(): void
    {
        $member = $this->request('member', 'index');
        $this->assertSame(1, $member['status']);
        $this->assertSame(self::$uid, (int)$member['uid']);
        $this->assertArrayHasKey('daily_task', $member);
        $this->assertSame(0, $member['daily_task']['status']);

        // 断言经验与军令契约对齐客户端 player.info 期望
        $this->assertArrayHasKey('levelupexp', $member);
        $this->assertArrayHasKey('act_limit', $member);
        $this->assertGreaterThan(0, $member['levelupexp']);
        $this->assertGreaterThan(0, $member['act_limit']);
        $this->assertSame($member['levelupexp'], $member['maxexp']);
        $this->assertSame($member['act_limit'], $member['maxact']);

        $role = $this->request('member', 'select_role', ['sex' => 1, 'name' => '常山赵子龙']);
        $this->assertSame(1, $role['status']);

        $updated = $this->request('member', 'index');
        $this->assertSame('常山赵子龙', $updated['nickname']);
        $this->assertSame(1, (int)$updated['gender']);
        $this->assertSame(80, (int)$updated['levelupexp']);
        $this->assertSame(60, (int)$updated['act_limit']);
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

        // 验证势力地图底部【下一势力】按钮状态 (在第 9 势力曹操势力时，下一势力第 10 势力刘备势力处于开放状态，next_status 必须为 1)
        $caocaoScenes = $this->request('map', 'get_scene_list', ['l' => 9]);
        $this->assertSame(1, $caocaoScenes['status']);
        $this->assertSame(1, $caocaoScenes['next_status'], '曹操势力向右切换到刘备势力必须畅通可点击');

        $missions = $this->request('map', 'get_mission_list', ['l' => 1, 's' => 1]);
        $this->assertSame(1, $missions['status']);
        $this->assertArrayHasKey('list', $missions);

        $detail = $this->request('map', 'mission', ['l' => 1, 's' => 1, 'id' => 1]);
        $this->assertSame(1, $detail['status']);
        $this->assertArrayHasKey('info', $detail);
        $this->assertSame('黄巾前哨', $detail['info']['name']);
        $this->assertArrayHasKey('image', $detail['info']);

        // 通关奖励查询与领取测试 (c=map&m=get_newreward_index / get_newreward)
        $rewards = $this->request('map', 'get_newreward_index', ['levelid' => 1]);
        $this->assertSame(1, $rewards['status']);
        $this->assertNotEmpty($rewards['list']);
        $firstReward = $rewards['list'][0];
        $this->assertArrayHasKey('mname', $firstReward);
        $this->assertArrayHasKey('re_type', $firstReward);
        $this->assertArrayHasKey('is_get', $firstReward);

        // 领取第 1 个通关奖励
        $claim = $this->request('map', 'get_newreward', ['id' => $firstReward['id']]);
        $this->assertSame(1, $claim['status']);
        $this->assertArrayHasKey('reward', $claim);
        $this->assertSame(2, $claim['reward']['is_get']); // 变为已领取状态

        // 战役势力通关大礼领取测试 (c=map&m=get_newresult)
        $shiliReward = $this->request('map', 'get_newresult', ['id' => 1]);
        $this->assertSame(1, $shiliReward['status']);
        $this->assertArrayHasKey('item', $shiliReward);
        $this->assertArrayHasKey('name', $shiliReward['item']);
        $this->assertSame(1, $shiliReward['gettype']);
    }

    public function testBattleSimulationAction(): void
    {
        $res = $this->request('map', 'action', ['l' => 1, 's' => 1, 'id' => 1]);
        $this->assertSame(1, $res['status']);
        $this->assertSame(1, $res['info']['win']);
        $this->assertArrayHasKey('battle', $res);
        $this->assertSame('黄巾前哨', $res['battle']['rightteam'][2]['name']);
        $this->assertCount(2, $res['battle']['rightteam'], '据点 1 应当包含 2 名守军对抗');
        $this->assertArrayHasKey(2, $res['battle']['rightteam']);
        $this->assertArrayHasKey(3, $res['battle']['rightteam']);
        $this->assertSame(3, $res['battle']['assess']);
        $this->assertNotEmpty($res['battle']['process']);

        // 验证关底大 Boss 战役 (id: 5)：拥有 5 人满编军团对决
        $bossRes = $this->request('map', 'action', ['l' => 1, 's' => 1, 'id' => 5]);
        $this->assertSame(1, $bossRes['status']);
        $this->assertCount(5, $bossRes['battle']['rightteam'], '关底决战应当拥有 5 人满编敌军军团');
        $this->assertArrayHasKey(5, $bossRes['battle']['rightteam'], '5号中排统帅位必须为关底大Boss');
        $this->assertSame('黄巾渠帅·张梁', $bossRes['battle']['rightteam'][5]['name']);

        // 验证重刷已打通的据点时，玩家全局最高进度单向锁定不回滚
        $memBefore = \OmniPHP\Database\DB::table('member')->where('uid', self::$uid)->first();
        $oldLvl = (int)$memBefore['missionlevel'];
        $oldStg = (int)$memBefore['missionstage'];
        $oldMis = (int)$memBefore['missionid'];

        // 再次打一次 1-1-1 旧关卡
        $repeat = $this->request('map', 'action', ['l' => 1, 's' => 1, 'id' => 1]);
        $this->assertSame(1, $repeat['status']);

        $memAfter = \OmniPHP\Database\DB::table('member')->where('uid', self::$uid)->first();
        $this->assertGreaterThanOrEqual($oldLvl, (int)$memAfter['missionlevel'], '重刷旧关卡绝不可导致势力等级倒退');
        $this->assertGreaterThanOrEqual($oldStg, (int)$memAfter['missionstage'], '重刷旧关卡绝不可导致大关场景倒退');
        $this->assertGreaterThanOrEqual($oldMis, (int)$memAfter['missionid'], '重刷旧关卡绝不可导致据点编号倒退');

        // 验证主公升级时的奖励包与功能开启预告生成结构 (16级解锁装备强化)
        $funcData = \Hanjiang\Handler\Campaign\MapHandler::getLevelFuncData(16, 50000);
        $this->assertArrayHasKey('levelReward', $funcData);
        $this->assertSame(80000, $funcData['levelReward']['1']);
        $this->assertArrayHasKey('levelFunc', $funcData);
        $this->assertSame(1, $funcData['levelFunc']['func_open']);
        $this->assertSame('强化', $funcData['levelFunc']['func_name']);
        $this->assertSame('strong', $funcData['levelFunc']['func_mark']);
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
        $this->assertArrayHasKey('info', $levelUp);
        $this->assertSame(2, $levelUp['info']['level']);
        $this->assertArrayHasKey('percent', $levelUp['info']);
        $this->assertArrayHasKey('next', $levelUp['info']);

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
        $this->assertArrayHasKey('info', $res);
        $this->assertArrayHasKey('info', $res['info']);
        $this->assertGreaterThan(0, $res['info']['info']['quality']);
        $this->assertArrayHasKey('rule', $res['info']);
        $this->assertArrayHasKey('cd', $res['info']);

        $firstEquip = $res['list'][1];
        $eid = (int)$firstEquip['id'];
        $oldLevel = (int)$firstEquip['level'];

        // 契约断言：必须具备 hammer 与 hammer_quality (防客户端显示 undefined)，未穿戴时不暴露 generalname
        $this->assertArrayHasKey('hammer', $firstEquip);
        $this->assertArrayHasKey('hammer_quality', $firstEquip);
        $this->assertSame(0, $firstEquip['hammer']);
        $this->assertSame(0, $firstEquip['hammer_quality']);
        if ((int)$firstEquip['gid'] === 0) {
            $this->assertArrayNotHasKey('generalname', $firstEquip, '未穿戴装备严禁输出 generalname 键，防客户端多余渲染');
        }

        // 查询单件装备强化预览
        $info = $this->request('strengthen', 'strengthen_info', ['id' => $eid]);
        $this->assertSame(1, $info['status']);
        $this->assertArrayHasKey('cost_silver', $info['info']);
        $this->assertArrayHasKey('hammer', $info['info']);

        // 执行铁匠铺装备强化
        $start = $this->request('strengthen', 'strengthen_start', ['id' => $eid]);
        $this->assertSame(1, $start['status']);
        $this->assertSame($oldLevel + 1, $start['level']);
        $this->assertSame($oldLevel + 1, $start['newlevel']);
        $this->assertGreaterThan(0, $start['newpower']);
        $this->assertGreaterThan(0, $start['cost_silver']);
        $this->assertArrayHasKey('silver', $start);
        $this->assertArrayHasKey('all_silver', $start);
    }

    public function testTavernSystem(): void
    {
        $res = $this->request('tavern', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        // 测试酒馆名将招募列表 get_list
        $bar = $this->request('tavern', 'get_list');
        $this->assertSame(1, $bar['status']);
        $this->assertGreaterThan(0, $bar['totals']);
        $this->assertArrayHasKey('jade', $bar);
        $this->assertArrayHasKey('info', $bar['list'][1]);
        $this->assertNotEmpty($bar['list'][1]['info']['name']);

        // 招募名将张飞 (id: 10, 花费 5000 银两)
        $buy = $this->request('tavern', 'buy', ['id' => 10]);
        $this->assertSame(1, $buy['status']);
        $this->assertSame('张飞', $buy['name']);
        $this->assertSame(5000, $buy['cost_silver']);
        $this->assertArrayHasKey('general', $buy);
        $this->assertSame(10, $buy['general']['generalid']);
        $this->assertArrayHasKey('jade', $buy);

        // 测试商贾会客贸易
        $trade = $this->request('tavern', 'trade', ['s' => 1, 'type' => 0]);
        $this->assertSame(1, $trade['status']);
        $this->assertArrayHasKey('site', $trade);
        $this->assertArrayHasKey('addtype', $trade);

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
        $this->assertGreaterThanOrEqual(6, $res['maxnum']);
        $this->assertSame(2, $res['opennum']);
        $this->assertNotEmpty($res['list'], '武将列表不能为空');
        $this->assertNotEmpty($res['place'], '训练位列表不能为空');
        $this->assertNotEmpty($res['rule'], '训练规则不能为空');
        $this->assertArrayHasKey('1', $res['rule'], '训练规则必须包含 1 号规则');
        $this->assertArrayHasKey('hour', $res['rule']['1']);
        $this->assertArrayHasKey('freetimes', $res);
        $this->assertArrayHasKey('leap', $res);
        $this->assertArrayHasKey('mop', $res);

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
        $this->assertGreaterThanOrEqual(500, $leap['add_exp']);
        $this->assertGreaterThanOrEqual(2, $leap['level']); // 经验足以升到 2 级以上

        // 5. 训练位升级与解锁价格查询
        $lvPrice = $this->request('practice', 'get_levelup_price', ['pid' => 1]);
        $this->assertSame(1, $lvPrice['status']);
        $this->assertSame(2, $lvPrice['info']['level']);

        $unlockPrice = $this->request('practice', 'get_unlock_price', ['squence' => 3]);
        $this->assertSame(1, $unlockPrice['status']);
        $this->assertSame(50, $unlockPrice['gold']);
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
        $this->assertArrayHasKey('list', $send, '聊天发送成功必须包含 list 供前端 chatpop.js 立即渲染');
        $this->assertSame(0, $send['list'][0]['type']);
        $this->assertNotEmpty($send['list'][0]['nickname']);

        // 测试拉取全量聊天频道记录
        $index = $this->request('chat', 'index');
        $this->assertSame(1, $index['status']);
        $this->assertNotEmpty($index['list']);

        // 测试增量读取接口 c=chat&m=read (由于刚发完并初始化过，此时增量列表为空，避免客户端 DOM 重复追加)
        $read = $this->request('chat', 'read');
        $this->assertSame(1, $read['status']);
        $this->assertIsArray($read['list']);
        $this->assertEmpty($read['list'], '增量轮询在没有新消息时必须为空数组，杜绝客户端重复追加');

        // 测试探活接口 c=index&m=index
        $rootIndex = $this->request('index', 'index');
        $this->assertSame(1, $rootIndex['status']);

        // 清理单测写入的测试聊天消息，绝不污染真实在线玩家屏幕
        $all = Redis::getJson('chat_messages') ?: [];
        $cleaned = array_values(array_filter($all, static fn(array $m): bool => ($m['message'] ?? '') !== '测试三国聊天'));
        Redis::setJson('chat_messages', $cleaned, 86400);
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
        $this->assertArrayHasKey('info', $res);
        $this->assertArrayHasKey('info', $res['info']);
        $this->assertArrayHasKey('totals', $res);
        $this->assertArrayHasKey('log_list', $res);

        $detail = $this->request('shop', 'get_info', ['id' => 31]);
        $this->assertSame(1, $detail['status']);
        $this->assertArrayHasKey('info', $detail);

        $buyItem = $this->request('shop', 'buy', ['id' => 31]);
        $this->assertSame(1, $buyItem['status']);

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

        // 购买演武挑战次数测试 (c=arena&m=buy_pk_num)
        $price = $this->request('arena', 'buy_pk_num', ['t' => 0]);
        $this->assertSame(1, $price['status']);
        $this->assertGreaterThan(0, $price['price']);

        $buy = $this->request('arena', 'buy_pk_num', ['t' => 1]);
        $this->assertSame(1, $buy['status']);
        $this->assertGreaterThan(0, $buy['times']);

        // 测试演武积分商城主页
        $arenaShop = $this->request('arena', 'shop_index');
        $this->assertSame(1, $arenaShop['status']);
        $this->assertArrayHasKey('act_arena_int', $arenaShop);
        $this->assertNotEmpty($arenaShop['list']);

        // 测试演武积分兑换 (银两包 id: 1)
        $exch = $this->request('arena', 'shop_exchange', ['id' => 1]);
        $this->assertSame(1, $exch['status']);
        $this->assertArrayHasKey('act_arena_int', $exch);
        $this->assertArrayHasKey('reward', $exch);
        $this->assertSame(2, $exch['reward']['re_type']); // 2 为银币奖励
        $this->assertNotEmpty($exch['list']);
    }

    public function testWebSocketAndBroadcasterSystem(): void
    {
        // 1. 发送跑马灯大喇叭广播
        \Hanjiang\WebSocket\WebSocketHandler::handleMarquee(\OmniPHP\WebSocket\Context::mock(['content' => '全服通告：三国争霸正式开启！']));

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

        // 未 auth 的连接不能发聊天（不再回退到请求里的 uid）
        $anonCtx = \OmniPHP\WebSocket\Context::mock(['action' => 'chat', 'message' => '冒名', 'uid' => self::$uid]);
        \Hanjiang\WebSocket\WebSocketHandler::dispatch($anonCtx);
        $this->assertStringContainsString('auth_fail', $anonCtx->sentMessages[0]);

        // 验证 Context 错误响应机制
        $errCtx = \OmniPHP\WebSocket\Context::mock(['action' => 'chat', 'message' => ''], uid: self::$uid);
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
        $this->assertArrayHasKey('pack', $res);
        $this->assertArrayHasKey('pages', $res['pack']);
        $this->assertArrayHasKey('general', $res);
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
        $before = DB::table('member')->where('uid', self::$uid)->first();
        $this->assertNotNull($before);

        // 0. 等级不足 (_open: 等级 > 25) 时建国被拦截
        DB::table('member')->where('uid', self::$uid)->update(['level' => 20]);
        $this->assertSame(4, $this->request('country', 'create', ['name' => 'X', 'confirm' => 1])['status']);

        // 国家系统开放 (> 25)、国库捐献 (>= 50)、国师授艺 (>= 30)
        DB::table('member')->where('uid', self::$uid)->update(['level' => 60, 'gold' => 100000, 'silver' => 10000000]);
        Redis::del('player:' . self::$uid);

        // 1. 建国价格
        $price = $this->request('country', 'create', ['confirm' => 0]);
        $this->assertSame(1, $price['status']);
        $this->assertSame(500, $price['price']);

        // 2. 名称校验
        $this->assertSame(-1, $this->request('country', 'create', ['name' => '', 'confirm' => 1])['status']);
        $this->assertSame(-2, $this->request('country', 'create', ['name' => '一二三四五六七八', 'confirm' => 1])['status']);

        // 3. 建国：扣 500 元宝，成为君主
        $name = '国' . substr((string)hrtime(true), -6);
        $create = $this->request('country', 'create', ['name' => $name, 'confirm' => 1]);
        $this->assertSame(1, $create['status']);
        $countryId = (int)$create['country']['id'];
        $this->assertGreaterThan(0, $countryId);
        $this->assertSame($name, $create['country']['name']);
        $this->assertSame(100000 - 500, $create['gold']);
        $this->assertSame(1, $create['info']['job']);
        $this->assertSame('君主', $create['list'][0]['job_name']);
        $this->assertSame($countryId, (int)DB::table('member')->where('uid', self::$uid)->first()['country']);

        // 4. 已有国家不可再建
        $again = $this->request('country', 'create', ['name' => $name . 'b', 'confirm' => 1]);
        $this->assertSame(2, $again['status']);
        $this->assertSame($countryId, $again['country']);

        // 5. 成员列表
        $memberList = $this->request('country', 'get_member_list');
        $this->assertSame(1, $memberList['status']);
        $this->assertSame($name, $memberList['country']['name']);
        $this->assertSame('', $memberList['country']['notice']);
        $this->assertSame(1, $memberList['info']['job']);
        $this->assertSame(0, $memberList['info']['make_way']);
        $this->assertCount(1, $memberList['list']);
        $this->assertSame(1000, (int)$memberList['list'][0]['allcontribute']);
        $this->assertSame('君主', $memberList['list'][0]['job_name']);
        $this->assertSame(0, (int)$memberList['audit']);

        // 6. 国家排行 (page=0 返回我的国家所在页)
        $rank = $this->request('country', 'get_rank');
        $this->assertSame(1, $rank['status']);
        $this->assertSame($countryId, (int)$rank['country']['id']);
        $this->assertArrayHasKey('notice', $rank['country']);
        $this->assertSame(5, $rank['perpage']);

        // 7. 国家信息 / 搜索
        $info = $this->request('country', 'get_country_info', ['id' => $countryId]);
        $this->assertSame(1, $info['status']);
        $this->assertSame($name, $info['country']['name']);
        $search = $this->request('country', 'search', ['name' => $name]);
        $this->assertSame(1, $search['status']);
        $this->assertSame($countryId, (int)$search['list'][0]['id']);
        $this->assertSame(-2, $this->request('country', 'search', ['name' => '不存在之国'])['status']);

        // 8. 第二位玩家申请 / 取消申请
        $reg = $this->request('register', 'guest', ['token' => '', 'uid' => '']);
        $this->assertSame(1, $reg['status']);
        $uid2 = (int)$reg['uid'];
        $login2 = $this->request('login', 'guest', ['token' => '', 'uid' => $uid2, 'guestkey' => $reg['guestkey']]);
        $token2 = (string)$login2['token'];
        $as2 = ['token' => $token2, 'uid' => $uid2];
        DB::table('member')->where('uid', $uid2)->update(['level' => 30]);
        Redis::del('player:' . $uid2);

        $apply = $this->request('country', 'apply', $as2 + ['id' => $countryId]);
        $this->assertSame(1, $apply['status']);
        $this->assertSame(-3, $this->request('country', 'apply', $as2 + ['id' => $countryId])['status']);
        $this->assertSame(-2, $this->request('country', 'apply', $as2 + ['id' => 999999])['status']);

        $applyList = $this->request('country', 'get_apply_list', $as2);
        $this->assertSame(1, $applyList['status']);
        $this->assertCount(1, $applyList['list']);
        $this->assertSame($countryId, (int)$applyList['country']['id']);

        $this->assertSame(1, (int)$this->request('country', 'get_member_list')['audit']);
        $audits = $this->request('country', 'get_audit_list');
        $this->assertSame(1, $audits['status']);
        $this->assertSame($uid2, (int)$audits['list'][0]['uid']);

        $this->assertSame(1, $this->request('country', 'del_apply', $as2 + ['id' => $countryId])['status']);
        $this->assertSame(-1, $this->request('country', 'del_apply', $as2 + ['id' => $countryId])['status']);
        $this->assertSame(0, (int)$this->request('country', 'get_member_list')['audit']);
        // 软删除后可以再次申请同一国家
        $this->assertSame(1, $this->request('country', 'apply', $as2 + ['id' => $countryId])['status']);

        // 9. 审核通过（国战/国家boss时段冻结）
        $audit = $this->request('country', 'audit', ['uid' => $uid2, 'type' => 1]);
        if (in_array($audit['status'], [4, 5], true)) {
            $this->assertSame(1, $this->request('country', 'ignore_all')['status']);
        } else {
            $this->assertSame(1, $audit['status']);
            $this->assertSame(0, (int)$audit['audit']);
            $this->assertSame(2, $audit['country']['member']);
            $this->assertSame($countryId, (int)DB::table('member')->where('uid', $uid2)->first()['country']);
            $list2 = $this->request('country', 'get_member_list', $as2);
            $this->assertSame(1, $list2['status']);
            $this->assertSame(2, $list2['info']['job']);
            $this->assertCount(2, $list2['list']);
            $this->assertSame(1, $this->request('country', 'get_member_list')['info']['make_way']);
            // 君主为第一位，贡献更高的不可踢
            $kickInfo = $this->request('country', 'kick_out_info', ['token' => $token2, 'uid' => self::$uid]);
            $this->assertSame(-3, $kickInfo['status']);
            $mw = $this->request('country', 'make_way_info');
            $this->assertSame(1, $mw['status']);
            $this->assertSame($uid2, (int)$mw['info']['uid']);
        }

        // 10. 发布公告
        $this->assertSame(-5, $this->request('country', 'publish_notice', ['country' => $countryId, 'content' => '加群qq'])['status']);
        $this->assertSame(-4, $this->request('country', 'publish_notice', ['country' => $countryId + 1, 'content' => '团结'])['status']);
        $notice = $this->request('country', 'publish_notice', ['country' => $countryId, 'content' => '团结一心']);
        $this->assertSame(1, $notice['status']);
        $this->assertSame('团结一心', $notice['notice']);
        $this->assertSame(3, $this->request('country', 'publish_notice', ['country' => $countryId, 'content' => '团结一心'])['status']);
        $this->assertSame('团结一心', $this->request('country', 'get_country_info', ['id' => $countryId])['country']['notice']);

        // 11. 国库
        $storage = $this->request('country', 'storage');
        $this->assertSame(1, $storage['status']);
        $this->assertSame(0, $storage['islock']);
        $this->assertSame(0, $storage['get']);
        $this->assertSame(500 * 60, $storage['donate']['silver']);
        $this->assertSame(10, $storage['donate']['gold']);
        $this->assertSame(10, $storage['donate']['contribute']);
        // 1 级国家俸禄：银币 550*等级、军令 5、将魂 80，乘君主系数 2.0；60 级无玉石
        $this->assertSame((int)round(550 * 60 * 2.0), $storage['salary']['silver']);
        $this->assertSame(10, $storage['salary']['act']);
        $this->assertSame(160, $storage['salary']['soul']);
        $this->assertArrayNotHasKey('jade', $storage['salary']);
        $this->assertSame('君主', $storage['info']['job_name']);

        // 12. 捐献 (银币 / 元宝 / 参数错误)
        $silverBefore = (int)DB::table('member')->where('uid', self::$uid)->first()['silver'];
        $donate = $this->request('country', 'donate', ['type' => 1]);
        $this->assertSame(1, $donate['status']);
        $this->assertSame($silverBefore - 500 * 60, $donate['silver']);
        $this->assertSame(550 * 60, $donate['donate']['silver']);
        $this->assertSame(1, $donate['country']['level']);
        $this->assertSame(10, $donate['country']['exp']);
        $this->assertSame(80, $donate['country']['levelupexp']);
        $donateGold = $this->request('country', 'donate', ['type' => 2]);
        $this->assertSame(1, $donateGold['status']);
        $this->assertSame(100000 - 500 - 10, $donateGold['gold']);
        $this->assertSame(11, $donateGold['donate']['gold']);
        $this->assertSame(20, $donateGold['country']['exp']);
        $this->assertSame(-5, $this->request('country', 'donate', ['type' => 9])['status']);
        $mc = DB::table('member_country')->where('uid', self::$uid)->first();
        $this->assertSame(1020, (int)$mc['allcontribute']);
        $this->assertSame(1, (int)$mc['silvertimes']);
        $this->assertSame(1, (int)$mc['goldtimes']);
        $this->assertSame(2, DB::table('country_donate_log')->where('uid', self::$uid)->count());

        // 13. 俸禄
        $actBefore = (int)DB::table('member')->where('uid', self::$uid)->first()['act'];
        $salary = $this->request('country', 'get_salary');
        $this->assertSame(1, $salary['status']);
        $this->assertSame($actBefore + 10, $salary['act']);
        $this->assertSame(2, $this->request('country', 'get_salary')['status']);
        $this->assertSame(1, $this->request('country', 'storage')['get']);

        // 14. 国师授艺：1 级国家没有学艺次数
        $learnIndex = $this->request('country', 'learn_index');
        $this->assertSame(1, $learnIndex['status']);
        $this->assertSame(1, $learnIndex['level']);
        $this->assertSame(0, $learnIndex['all_times']);
        $this->assertSame(0, $learnIndex['times']);
        $this->assertCount(3, $learnIndex['list']);
        $this->assertSame(1125 * 60, $learnIndex['list'][0]['exp']);
        $this->assertSame(4, $this->request('country', 'learn', ['type' => 1])['status']);
        $this->assertSame(-4, $this->request('country', 'learn', ['type' => 9])['status']);

        // 15. 君主无法升官；国家战报
        $this->assertSame(2, $this->request('country', 'get_usurp_info')['status']);
        $this->assertSame(1, $this->request('country', 'fight_notice', ['type' => 1])['status']);

        // 恢复等级与货币，保留国家成员身份
        DB::table('member')->where('uid', self::$uid)->update([
            'level' => $before['level'],
            'gold' => $before['gold'],
            'silver' => $before['silver'],
        ]);
        Redis::del('player:' . self::$uid);
    }

    public function testCultivateSystem(): void
    {
        // 1. 获取主公武将 ID
        $generals = $this->request('general', 'index');
        $mainGid = (int)$generals['list'][1]['id'];

        // 2. 查看洗髓面板
        $res = $this->request('cultivate', 'index', ['gid' => $mainGid]);
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);
        $this->assertNotEmpty($res['rule']);
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

        // 5. VIP 每日工资测试 (c=vipwage&m=index / get_vip_wage)
        $wage = $this->request('vipwage', 'index');
        $this->assertSame(1, $wage['status']);
        $this->assertNotEmpty($wage['list']);
        $this->assertNotEmpty($wage['my_reward']);
        $this->assertArrayHasKey('get1', $wage['my_reward']);

        $claimWage = $this->request('vipwage', 'get_vip_wage');
        $this->assertSame(1, $claimWage['status']);

        // 6. VIP 回馈狂欢礼包测试 (c=vipreward&m=index / get_reward)
        $repay = $this->request('vipreward', 'index');
        $this->assertSame(1, $repay['status']);
        $this->assertNotEmpty($repay['list']);

        $claimRepay = $this->request('vipreward', 'get_reward', ['id' => 1]);
        $this->assertSame(1, $claimRepay['status']);
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
        $this->assertArrayHasKey('now_number', $res);
        $this->assertArrayHasKey('all_number', $res);
        $this->assertArrayHasKey('online', $res);
        $this->assertArrayHasKey('nickname', $res['list'][0]);
        $this->assertArrayHasKey('country', $res['list'][0]);
        $this->assertArrayHasKey('job', $res['list'][0]);

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
        $this->assertNotEmpty($res['list']);
        $this->assertArrayHasKey('level', $res);
        $this->assertSame(1, $res['list'][0]['page']);

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
        $this->assertArrayHasKey('beauty_info', $res);
        $this->assertArrayHasKey('skill', $res['beauty_info']);

        // 测试获取指定美人信息
        $info = $this->request('beauty', 'get_beauty_info', ['beauty_id' => 2]);
        $this->assertSame(1, $info['status']);
        $this->assertArrayHasKey('beauty_info', $info);
        $this->assertSame(2, $info['beauty_info']['info']['beauty_id']);

        // 2. 赠送鲜花给貂蝉 (id: 1)
        $flower = $this->request('beauty', 'give_flower', ['id' => 1]);
        $this->assertSame(1, $flower['status']);
        $this->assertSame(1, $flower['bid']);
        $this->assertGreaterThan(0, $flower['intimacy']);

        // 3. 赏赐珠宝美玉
        $gift = $this->request('beauty', 'gift', ['id' => 1]);
        $this->assertSame(1, $gift['status']);
        $this->assertSame(1, $gift['bid']);
        $this->assertArrayHasKey('happy', $gift);
        $this->assertGreaterThan(0, $gift['happy']['rose']);
        $this->assertGreaterThanOrEqual(1, $gift['level']);

        // 4. 购买玫瑰花测试
        $buyRose = $this->request('beauty', 'buy_rose');
        $this->assertSame(1, $buyRose['status']);
        $this->assertGreaterThan(0, $buyRose['rose']);

        // 5. 查看美人互动主界面
        $actIdx = $this->request('beauty', 'active_index', ['id' => 1]);
        $this->assertSame(1, $actIdx['status']);
        $this->assertGreaterThan(0, $actIdx['greeting']);
        $this->assertGreaterThan(0, $actIdx['spend_greet']);

        // 6. 执行美人互动 (问候)
        $actAction = $this->request('beauty', 'active_action', ['id' => 1, 'type' => 1]);
        $this->assertSame(1, $actAction['status']);
        $this->assertArrayHasKey('happy', $actAction);
        $this->assertGreaterThan(0, $actAction['happy']['rose']);
        $this->assertArrayHasKey('special_skill', $actAction);
        $this->assertArrayHasKey('reward', $actAction);
        $this->assertSame(2, $actAction['lastnum']);

        // 7. 铜雀台开启免战
        $peace = $this->request('beauty', 'peace');
        $this->assertSame(1, $peace['status']);
        $this->assertSame(7200, $peace['cd_time']);
        $this->assertGreaterThan(0, $peace['times']);
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
        $res = $this->request('act_thanksgiving', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertSame(1, $res['is_fallback']);
        $this->assertSame('act_thanksgiving', $res['c']);
        $this->assertSame('index', $res['m']);
        $this->assertStringContainsString('筹备中', $res['message']);
        $this->assertIsArray($res['list']);
        $this->assertIsArray($res['info']);
    }

    public function testHandlerStatelessArchitectureLaw(): void
    {
        // 架构铁律 1 深度反射自检: 杜绝任何 Handler 拥有实例属性 ($this->xxx)，彻底杜绝长常驻内存中的跨请求会话污染
        $handlerFiles = glob(dirname(__DIR__, 2) . '/src/Handler/*/*Handler.php');
        $this->assertNotEmpty($handlerFiles);

        foreach ($handlerFiles as $file) {
            $subDir = basename(dirname($file));
            $className = 'Hanjiang\\Handler\\' . $subDir . '\\' . basename($file, '.php');
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
        $ok = \Hanjiang\Handler\City\MemberHandler::deductCurrency(self::$uid, silver: 100, gold: 5);
        $this->assertTrue($ok);

        // 2. 验证超额扣减原子行锁拦截 (余额不足时必须原子回滚并拒绝，绝不允许扣成负数或超卖)
        $failed = \Hanjiang\Handler\City\MemberHandler::deductCurrency(self::$uid, silver: 999999999);
        $this->assertFalse($failed, '当银两不足时原子扣减必须返回 false');

        // 3. 验证原子增加货币
        \Hanjiang\Handler\City\MemberHandler::addCurrency(self::$uid, silver: 5000);
        $member = \OmniPHP\Database\DB::table('member')->where('uid', self::$uid)->first();
        $this->assertGreaterThan(5000, (int)$member['silver']);
    }

    public function testSiegeSystem(): void
    {
        // 1. 查看名城列表
        $res = $this->request('siege', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertCount(6, $res['list']);

        // 2. 攻打并占领濮阳城 (id: 6)
        $action = $this->request('siege', 'action', ['city_id' => 6]);
        $this->assertSame(1, $action['status']);
        $this->assertSame(1, $action['win']);
        $this->assertSame('中原·濮阳', $action['city_name']);

        // 3. 领取已占领城池的每日岁贡
        $reward = $this->request('siege', 'get_reward');
        $this->assertSame(1, $reward['status']);
        $this->assertGreaterThan(0, $reward['silver']);
        $this->assertGreaterThan(0, $reward['gold']);
    }

    public function testGvgSystem(): void
    {
        // 1. 查看跨服 GVG 对阵树
        $res = $this->request('gvg', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['teams']);

        // 2. 押注竞猜天命无双军团夺冠
        $bet = $this->request('gvg', 'betting', ['team_id' => 1, 'amount' => 5000]);
        $this->assertSame(1, $bet['status']);
        $this->assertSame(5000, $bet['bet_amount']);

        // 3. 重复押注拦截
        $dup = $this->request('gvg', 'betting', ['team_id' => 2, 'amount' => 5000]);
        $this->assertSame(0, $dup['status']);
    }

    public function testMasterDataMatchesLegacyDump(): void
    {
        // 旧版配置表生成的常量数组：抽查场景与怪物原值
        $this->assertCount(94, \Hanjiang\MasterData\Scene::ROWS);
        $this->assertSame('曲阳', \Hanjiang\MasterData\Scene::ROWS[1]['name']);
        $this->assertSame('长枪兵', \Hanjiang\MasterData\Monster::ROWS[1]['name']);
        $this->assertSame(20, \Hanjiang\MasterData\Monster::ROWS[1]['blood']);
    }

    public function testGeneralInheritSystem(): void
    {
        // 1. 获取主公与张飞将领 ID
        $generals = $this->request('general', 'index');
        $this->assertGreaterThanOrEqual(2, count($generals['list']));
        $g1 = (int)$generals['list'][1]['id'];
        $g2 = (int)$generals['list'][2]['id'];

        // 2. 查看传承面板
        $res = $this->request('general_inherit', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);
        $this->assertArrayHasKey('rule', $res);
        $this->assertArrayHasKey(1, $res['rule']);
        $this->assertSame('普通继承', $res['rule'][1]['name']);
        $this->assertArrayHasKey('free_inherit', $res);

        // 测试获取武将可传承经验与规则
        $inheritInfo = $this->request('general_inherit', 'get_inherit_info', ['gid' => $g1]);
        $this->assertSame(1, $inheritInfo['status']);
        $this->assertArrayHasKey('rule', $inheritInfo);
        $this->assertArrayHasKey(1, $inheritInfo['rule']);
        $this->assertArrayHasKey('allexp', $inheritInfo['rule'][1]);

        // 3. 预览至尊完璧传承属性 (type: 4 为 100% 完美传承)
        $preview = $this->request('general_inherit', 'preview', ['from_gid' => $g1, 'to_gid' => $g2, 'type' => 4]);
        $this->assertSame(1, $preview['status']);
        $this->assertEquals(1.0, $preview['inherit_rate']);

        // 4. 执行传承
        $inherit = $this->request('general_inherit', 'go_inherit', ['from_gid' => $g1, 'to_gid' => $g2, 'type' => 1]);
        $this->assertSame(1, $inherit['status']);
        $this->assertSame($g2, $inherit['to_gid']);
    }

    public function testBlackmarketSystem(): void
    {
        // 1. 查看黑市货架
        $res = $this->request('blackmarket', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        // 2. 锁定商品 1
        $lock = $this->request('blackmarket', 'lock', ['id' => 1]);
        $this->assertSame(1, $lock['status']);
        $this->assertSame(1, $lock['locked']);

        // 3. 购买商品 1
        $buy = $this->request('blackmarket', 'buy', ['id' => 1]);
        $this->assertSame(1, $buy['status']);
        $this->assertSame(1, $buy['id']);

        // 4. 刷新货架
        $refresh = $this->request('blackmarket', 'refresh');
        $this->assertSame(1, $refresh['status']);
        $this->assertNotEmpty($refresh['list']);
    }

    public function testFirstRechargeSystem(): void
    {
        // 1. 查看首充面板
        $res = $this->request('first_recharge', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertArrayHasKey('reward_preview', $res);

        // 2. 领取首充豪礼
        $reward = $this->request('first_recharge', 'get_reward');
        $this->assertSame(1, $reward['status']);
        $this->assertSame('赵云', $reward['general']);
        $this->assertSame('龙胆亮银枪', $reward['weapon']);
        $this->assertSame(2, $reward['vip']);

        // 3. 再次领取被拦截
        $dup = $this->request('first_recharge', 'get_reward');
        $this->assertSame(0, $dup['status']);
    }

    public function testOverseasTradeSystem(): void
    {
        // 1. 查看商贸主界面
        $res = $this->request('overseastrade', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['fleets']);
        $this->assertArrayHasKey('info', $res);
        $this->assertArrayHasKey('times', $res['info']);
        $this->assertArrayHasKey('rob', $res);
        $this->assertArrayHasKey('team', $res);

        // 2. 查看国家贸易组队大厅 (验证 page 与 allpage 避免 NaN 翻页)
        $teams = $this->request('overseastrade', 'get_list_by_country', ['p' => 1]);
        $this->assertSame(1, $teams['status']);
        $this->assertSame(1, $teams['page']);
        $this->assertSame(5, $teams['allpage']);
        $this->assertArrayHasKey('list', $teams);

        // 3. 派遣快行小帆船启航出海 (fleet: 1)
        $go = $this->request('overseastrade', 'goout', ['fleet_id' => 1]);
        $this->assertSame(1, $go['status']);
        $this->assertSame(1, $go['fleet_id']);

        // 4. 验证商贸刷新接口 (验证 team.allpage)
        $refresh = $this->request('overseastrade', 'refresh', ['p' => 1]);
        $this->assertSame(1, $refresh['status']);
        $this->assertArrayHasKey('team', $refresh);
        $this->assertSame(1, $refresh['team']['allpage']);
    }

    public function testStudfarmSystem(): void
    {
        // 1. 查看伯乐马场
        $res = $this->request('studfarm', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        // 2. 执行相马
        $horse = $this->request('studfarm', 'action', ['id' => 1]);
        $this->assertSame(1, $horse['status']);
        $this->assertArrayHasKey('mount_name', $horse);
    }

    public function testJadegambleSystem(): void
    {
        // 1. 查看赌石坊
        $res = $this->request('jadegamble', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        // 2. 选购原石
        $mine = $this->request('jadegamble', 'mining', ['id' => 1]);
        $this->assertSame(1, $mine['status']);

        // 3. 解石一刀切
        $cut = $this->request('jadegamble', 'gambling');
        $this->assertSame(1, $cut['status']);
        $this->assertGreaterThan(0, $cut['final_value']);

        // 4. 收取翡翠玉石
        $harvest = $this->request('jadegamble', 'harvest');
        $this->assertSame(1, $harvest['status']);
        $this->assertGreaterThan(0, $harvest['silver']);
    }

    public function testTreasuremapSystem(): void
    {
        // 1. 查看藏宝图
        $res = $this->request('treasuremap', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertGreaterThanOrEqual(1, $res['star']);
        $this->assertArrayHasKey('list', $res);
        $this->assertArrayHasKey('map', $res);
        $this->assertArrayHasKey('team', $res);

        // 2. 寻龙分金刷新宝图
        $ref = $this->request('treasuremap', 'refresh');
        $this->assertSame(1, $ref['status']);

        // 3. 创建寻宝队伍 (c=treasuremap&m=create_team)
        $team = $this->request('treasuremap', 'create_team');
        $this->assertSame(1, $team['status']);
        $this->assertNotEmpty($team['team']);

        // 4. 退出/解散队伍 (c=treasuremap&m=exit_team)
        $exit = $this->request('treasuremap', 'exit_team');
        $this->assertSame(1, $exit['status']);

        // 5. 发掘挖宝
        $sale = $this->request('treasuremap', 'sale');
        $this->assertSame(1, $sale['status']);
        $this->assertGreaterThan(0, $sale['silver']);
    }

    public function testWorkshopSystem(): void
    {
        // 1. 查看军械工坊
        $res = $this->request('workshop', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertGreaterThanOrEqual(1, $res['level']);

        // 2. 收取军械折现
        $reward = $this->request('workshop', 'get_reward');
        $this->assertSame(1, $reward['status']);

        // 3. 升级军械工坊
        $up = $this->request('workshop', 'levelup');
        $this->assertSame(1, $up['status']);
        $this->assertSame(2, $up['level']);
    }

    public function testSanctumSystem(): void
    {
        // 1. 查看四象圣域
        $res = $this->request('sanctum', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertCount(4, $res['list']);

        // 2. 挑战青龙圣域 (realm: 1)
        $action = $this->request('sanctum', 'action', ['id' => 1]);
        $this->assertSame(1, $action['status']);
        $this->assertSame(1, $action['win']);
        $this->assertGreaterThan(0, $action['silver']);
        $this->assertGreaterThan(0, $action['gold']);
    }

    public function testCompeteSystem(): void
    {
        // 1. 查看跨服论剑比武
        $res = $this->request('compete', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['opponents']);

        // 2. 发起比武单挑 (对手 1)
        $pk = $this->request('compete', 'action', ['id' => 1]);
        $this->assertSame(1, $pk['status']);
        $this->assertSame(1, $pk['win']);
        $this->assertSame(50, $pk['add_score']);

        // 3. 领取每日比武段位俸禄
        $reward = $this->request('compete', 'get_reward');
        $this->assertSame(1, $reward['status']);
        $this->assertGreaterThan(0, $reward['silver']);
    }

    /**
     * @return array{uid: int, auth: array{token: string, uid: int}}
     */
    private function newGuestPlayer(): array
    {
        $reg = $this->request('register', 'guest', ['token' => '', 'uid' => '']);
        $this->assertSame(1, $reg['status']);
        $uid = (int)$reg['uid'];
        $login = $this->request('login', 'guest', ['token' => '', 'uid' => $uid, 'guestkey' => $reg['guestkey']]);
        return ['uid' => $uid, 'auth' => ['token' => (string)$login['token'], 'uid' => $uid]];
    }

    public function testAuthRejectsForgedIdentity(): void
    {
        // 无 token / 伪造 token / 只带 uid：受保护接口一律 403，公开接口照常
        foreach ([['token' => '', 'uid' => self::$uid], ['token' => 'tk_guest_' . self::$uid . '_forged', 'uid' => self::$uid]] as $forged) {
            $ctx = Context::mock(array_merge(['c' => 'member', 'm' => 'index'], $forged));
            $res = self::$router->handleContext($ctx);
            $this->assertInstanceOf(WorkermanResponse::class, $res);
            $this->assertSame('403', (string)$res->rawBody());
        }
        $this->assertSame(1, $this->request('ios', 'update_world_common', ['token' => '', 'uid' => ''])['status']);
        $this->assertSame(0, MemberHandler::resolveUid(Context::mock(['uid' => self::$uid])));
    }

    public function testCountryMakeWayRequiresKing(): void
    {
        $p = $this->newGuestPlayer();
        $countryId = (int)DB::table('country')->insertGetId(['name' => '禅' . substr((string)hrtime(true), -6), 'member' => 2, 'uid' => 1, 'nickname' => 'king']);
        DB::table('member')->where('uid', $p['uid'])->update(['level' => 30, 'country' => $countryId]);
        DB::table('member_country')->insert(['uid' => $p['uid'], 'nickname' => 'p', 'country' => $countryId, 'job' => 2]);
        Redis::del('player:' . $p['uid']);

        // 非君主调用禅让被拦截，职位不变
        $this->assertSame(-7, $this->request('country', 'make_way', $p['auth'])['status']);
        $this->assertSame(2, (int)DB::table('member_country')->where('uid', $p['uid'])->first()['job']);
    }

    public function testDailyCronResetsCountryCounters(): void
    {
        $p = $this->newGuestPlayer();
        $uid = $p['uid'];
        if (DB::table('member_field')->where('uid', $uid)->first() === null) {
            DB::table('member_field')->insert(['uid' => $uid]);
        }
        DB::table('member_country')->insert([
            'uid' => $uid, 'nickname' => 'p', 'country' => 1,
            'goldtimes' => 5, 'silvertimes' => 3, 'learntimes' => 2, 'contribute' => 100,
        ]);
        DB::table('member_field')->where('uid', $uid)->update(['dateline' => strtotime('yesterday')]);
        Redis::del("daily_cron:{$uid}");

        // 当天首次请求：清空捐献/学艺次数，今日贡献留给全服 0 点任务
        $this->request('member', 'index', $p['auth']);
        $row = DB::table('member_country')->where('uid', $uid)->first();
        $this->assertSame([0, 0, 0, 100], [(int)$row['goldtimes'], (int)$row['silvertimes'], (int)$row['learntimes'], (int)$row['contribute']]);
        $this->assertGreaterThanOrEqual(strtotime('today'), (int)DB::table('member_field')->where('uid', $uid)->first()['dateline']);

        // 同一天再次请求不重复执行
        DB::table('member_country')->where('uid', $uid)->update(['goldtimes' => 4]);
        Redis::del("daily_cron:{$uid}");
        $this->request('member', 'index', $p['auth']);
        $this->assertSame(4, (int)DB::table('member_country')->where('uid', $uid)->first()['goldtimes']);

        // 全服 0 点任务清空今日贡献
        CountryCron::clearContribute();
        $this->assertSame(0, (int)DB::table('member_country')->where('uid', $uid)->first()['contribute']);
    }

    public function testCountrySacrificeSystem(): void
    {
        // 1. 查看宗庙祈福
        $res = $this->request('countrysacrifice', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertCount(3, $res['list']);

        // 2. 执行白马平祭 (type: 1)
        $action = $this->request('countrysacrifice', 'action', ['type' => 1]);
        $this->assertSame(1, $action['status']);
        $this->assertGreaterThan(0, $action['add_reputation']);
    }

    public function testExpostulationSystem(): void
    {
        // 1. 查看朝堂谏言奏折
        $res = $this->request('expostulation', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        // 2. 御笔批阅采纳屯田兴农疏 (id: 1)
        $adopt = $this->request('expostulation', 'adopt', ['id' => 1]);
        $this->assertSame(1, $adopt['status']);
        $this->assertGreaterThan(0, $adopt['silver']);
        $this->assertGreaterThan(0, $adopt['merit']);

        // 3. 重复采纳同一奏折拦截
        $dup = $this->request('expostulation', 'adopt', ['id' => 1]);
        $this->assertSame(0, $dup['status']);
    }

    public function testBanquetSystem(): void
    {
        // 1. 查看夜宴大厅
        $res = $this->request('banquet', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['types']);

        // 2. 举办主公家宴 (type: 1)
        $action = $this->request('banquet', 'action', ['type' => 1]);
        $this->assertSame(1, $action['status']);
        $this->assertSame('主公家宴', $action['name']);
        $this->assertGreaterThan(0, $action['add_act']);
    }

    public function testDrinkSystem(): void
    {
        // 1. 查看青梅煮酒
        $res = $this->request('drink', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);
        $this->assertNotEmpty($res['log']);
        $this->assertNotEmpty($res['wines']);

        // 2. 畅饮陈年竹叶青 (type: 1)
        $drink = $this->request('drink', 'go_drink', ['type' => 1]);
        $this->assertSame(1, $drink['status']);
        $this->assertSame('陈年竹叶青', $drink['wine_name']);
        $this->assertGreaterThan(0, $drink['silver']);
        $this->assertArrayHasKey('reward', $drink);
        $this->assertArrayHasKey('act', $drink['reward']);
    }

    public function testDiceSystem(): void
    {
        // 1. 查看神骰主界面
        $res = $this->request('dice', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertArrayHasKey('member', $res);
        $this->assertArrayHasKey('last_week', $res);
        $this->assertArrayHasKey('list', $res);
        $this->assertArrayHasKey('points', $res['member']);
        $this->assertGreaterThanOrEqual(1, $res['member']['times']);

        // 2. 投掷骰子
        $shake = $this->request('dice', 'shake_dice');
        $this->assertSame(1, $shake['status']);
        $this->assertArrayHasKey('member', $shake);
        $this->assertArrayHasKey('dices', $shake);
        $this->assertCount(3, $shake['dices']);
        $this->assertGreaterThan(0, $shake['silver']);
    }

    public function testSacredtreeSystem(): void
    {
        // 1. 查看聚宝神树
        $res = $this->request('sacredtree', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertGreaterThanOrEqual(1, $res['level']);

        // 2. 给神树浇水摇钱
        $water = $this->request('sacredtree', 'watering');
        $this->assertSame(1, $water['status']);
        $this->assertGreaterThan(0, $water['silver']);
    }

    public function testFlopSystem(): void
    {
        // 1. 查看翻牌界面
        $res = $this->request('flop', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertCount(9, $res['cards']);

        // 2. 翻开第 1 张卡牌
        $turn = $this->request('flop', 'turnover', ['id' => 1]);
        $this->assertSame(1, $turn['status']);
        $this->assertGreaterThan(0, $turn['silver']);

        // 3. 重复翻同一张卡牌被拦截
        $dup = $this->request('flop', 'turnover', ['id' => 1]);
        $this->assertSame(0, $dup['status']);
    }

    public function testChallengeSystem(): void
    {
        // 1. 查看擂台
        $res = $this->request('challenge', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['masters']);

        // 2. 挑战擂主 1
        $pk = $this->request('challenge', 'action', ['id' => 1]);
        $this->assertSame(1, $pk['status']);
        $this->assertSame(1, $pk['win']);
        $this->assertGreaterThan(0, $pk['silver']);
    }

    public function testEliteSystem(): void
    {
        // 1. 获取多人副本 WebSocket 实时服务地址 (c=elite&m=get_server)
        $srv = $this->request('elite', 'get_server');
        $this->assertSame(1, $srv['status']);
        $this->assertNotEmpty($srv['url']);

        // 2. 查看精英副本
        $res = $this->request('elite', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['maps']);

        // 3. 挑战精英大兴山
        $battle = $this->request('elite', 'action', ['id' => 1]);
        $this->assertSame(1, $battle['status']);
        $this->assertSame(1, $battle['win']);
        $this->assertGreaterThan(0, $battle['silver']);
    }

    public function testMusterSystem(): void
    {
        // 1. 查看点将台名册
        $res = $this->request('muster', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);
        $this->assertNotEmpty($res['first']);
        $this->assertArrayHasKey('skill', $res['first']);
        $this->assertArrayHasKey('talent', $res['first']);
        $this->assertArrayHasKey('name', $res['first']['skill']);

        // 2. 查看单名将领详情 (c=muster&m=get_general_info)
        $firstGid = (int)$res['first']['id'];
        $info = $this->request('muster', 'get_general_info', ['gid' => $firstGid]);
        $this->assertSame(1, $info['status']);
        $this->assertSame($firstGid, $info['info']['id']);

        // 挑选一个未出征的后备武将进行退役
        $freeGid = 0;
        foreach ($res['list'] as $g) {
            if ($g['pos'] === 0 && $g['id'] > 1) {
                $freeGid = (int)$g['id'];
                break;
            }
        }

        if ($freeGid > 0) {
            // 3. 遣散该武将退役 (软删除并领安家费)
            $sale = $this->request('muster', 'sale_general', ['id' => $freeGid]);
            $this->assertSame(1, $sale['status']);
            $this->assertGreaterThan(0, $sale['severance_pay']);
        }
    }

    public function testActivitySystem(): void
    {
        $res = $this->request('activity', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertArrayHasKey('list', $res);
        $this->assertArrayHasKey('logined', $res['list']);
        $this->assertArrayHasKey('boss', $res['list']);
        $this->assertArrayHasKey('popo', $res['list']['logined']);
        $this->assertArrayHasKey('popo', $res['list']['boss']);
    }

    public function testLoginedSystem(): void
    {
        $res = $this->request('logined', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertGreaterThanOrEqual(1, $res['day']);
        $this->assertNotEmpty($res['list']);
        $this->assertArrayHasKey('id', $res['list'][0]);
        $this->assertArrayHasKey('type', $res['list'][0]);

        $claim = $this->request('logined', 'get_reward', ['id' => 1]);
        $this->assertSame(1, $claim['status']);
        $this->assertGreaterThan(0, $claim['all_silver']);
    }

    public function testBusinessSystem(): void
    {
        // 1. 查看四海通商
        $res = $this->request('business', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['cities']);

        // 2. 启程通商洛阳商市 (city: 1)
        $go = $this->request('business', 'go_business', ['id' => 1]);
        $this->assertSame(1, $go['status']);
        $this->assertSame('中原·洛阳商市', $go['city_name']);
    }

    public function testGamblingSystem(): void
    {
        // 1. 查看聚宝博彩盆
        $res = $this->request('gambling', 'index');
        $this->assertSame(1, $res['status']);

        // 2. 投注原石
        $buy = $this->request('gambling', 'buy');
        $this->assertSame(1, $buy['status']);

        // 3. 抛光雕琢
        $cut = $this->request('gambling', 'cut');
        $this->assertSame(1, $cut['status']);
        $this->assertGreaterThan(0, $cut['final_value']);

        // 4. 典当折现
        $sell = $this->request('gambling', 'sell');
        $this->assertSame(1, $sell['status']);
        $this->assertGreaterThan(0, $sell['silver']);
    }

    public function testTaskSystem(): void
    {
        // 1. 查看悬赏大厅看板
        $res = $this->request('task', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);
        $this->assertArrayHasKey('taskid1', $res);
        $this->assertArrayHasKey('number', $res);
        $this->assertArrayHasKey('free_times', $res);

        // 2. 接取悬赏任务 1
        $accept = $this->request('task', 'accept_task', ['id' => 1]);
        $this->assertSame(1, $accept['status']);

        // 3. 交付悬赏领赏
        $reward = $this->request('task', 'get_reward', ['id' => 1]);
        $this->assertSame(1, $reward['status']);
        $this->assertGreaterThan(0, $reward['silver']);
    }

    public function testTalentSystem(): void
    {
        // 1. 获取主公武将 ID
        $generals = $this->request('general', 'index');
        $mainGid = (int)$generals['list'][1]['id'];

        // 2. 查看天赋树
        $res = $this->request('talent', 'index', ['gid' => $mainGid]);
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        // 3. 升级猛攻天赋 (id: 1)
        $up = $this->request('talent', 'levelup', ['gid' => $mainGid, 'id' => 1]);
        $this->assertSame(1, $up['status']);
        $this->assertSame(2, $up['level']);
    }

    public function testAdvancedSystem(): void
    {
        // 1. 获取主公武将 ID
        $generals = $this->request('general', 'index');
        $mainGid = (int)$generals['list'][1]['id'];

        // 2. 查看进阶面板
        $res = $this->request('advanced', 'index', ['gid' => $mainGid]);
        $this->assertSame(1, $res['status']);
        $this->assertSame(1, $res['can_advance']);

        // 3. 执行品质进阶飞升
        $adv = $this->request('advanced', 'start_advanced', ['gid' => $mainGid]);
        $this->assertSame(1, $adv['status']);
        $this->assertGreaterThan(1, $adv['new_quality']);
    }

    public function testRefineSystem(): void
    {
        // 1. 查看装备精炼
        $res = $this->request('refine', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertArrayHasKey('current_refine', $res);
        $eid = (int)$res['eid'];

        // 2. 执行精炼淬火
        $refine = $this->request('refine', 'refinning', ['id' => $eid]);
        $this->assertSame(1, $refine['status']);
        $this->assertArrayHasKey('refine', $refine);
    }

    public function testStarSystem(): void
    {
        // 1. 获取主公武将 ID
        $generals = $this->request('general', 'index');
        $mainGid = (int)$generals['list'][1]['id'];

        // 2. 查看星级
        $res = $this->request('star', 'index', ['gid' => $mainGid]);
        $this->assertSame(1, $res['status']);
        $this->assertGreaterThanOrEqual(1, $res['star']);

        // 3. 升星突破
        $up = $this->request('star', 'level_up', ['gid' => $mainGid]);
        $this->assertSame(1, $up['status']);
        $this->assertGreaterThan(1, $up['new_star']);
    }

    public function testWarCollegeSystem(): void
    {
        // 1. 查看战争学院
        $res = $this->request('war_college', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertCount(4, $res['techs']);

        // 2. 研发步兵玄甲圆阵科技 (id: 1)
        $up = $this->request('war_college', 'level_up', ['id' => 1]);
        $this->assertSame(1, $up['status']);
        $this->assertSame(2, $up['level']);
    }

    public function testLotterySystem(): void
    {
        // 1. 查看大转盘
        $res = $this->request('lottery', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertCount(8, $res['list']);
        $this->assertArrayHasKey('log', $res);
        $this->assertArrayHasKey('info', $res['log']);
        $this->assertArrayHasKey('total_num', $res['log']['info']);

        // 2. 旋转转盘
        $roll = $this->request('lottery', 'action');
        $this->assertSame(1, $roll['status']);
        $this->assertArrayHasKey('info', $roll);
        $this->assertArrayHasKey('id', $roll['info']);
        $this->assertArrayHasKey('gettype', $roll['info']);
        $this->assertArrayHasKey('prize_name', $roll);
    }

    public function testGeneraltaskSystem(): void
    {
        // 1. 获取有效武将 ID
        $generals = $this->request('general', 'index');
        $gid = (int)$generals['list'][1]['id'];

        // 2. 查看远征
        $res = $this->request('generaltask', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        // 3. 派遣武将出征 (task: 1)
        $action = $this->request('generaltask', 'action', ['task_id' => 1, 'gid' => $gid]);
        $this->assertSame(1, $action['status']);
        $this->assertSame('古栈道斥候侦察', $action['task_name']);
    }

    public function testGeneralBookSystem(): void
    {
        // 1. 查看名将谱
        $res = $this->request('general_book', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        // 2. 点亮桃园结义羁绊 (combo: 1)
        $up = $this->request('general_book', 'levelup', ['id' => 1]);
        $this->assertSame(1, $up['status']);
        $this->assertSame('桃园结义', $up['combo_name']);
    }

    public function testCopiesSystem(): void
    {
        // 1. 查看平乱副本
        $res = $this->request('copies', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        // 2. 挑战十常侍之乱 (copy: 1)
        $battle = $this->request('copies', 'pk', ['id' => 1]);
        $this->assertSame(1, $battle['status']);
        $this->assertSame(1, $battle['win']);
        $this->assertGreaterThan(0, $battle['silver']);

        // 3. 一键平乱扫荡
        $sweep = $this->request('copies', 'mop_up');
        $this->assertSame(1, $sweep['status']);
        $this->assertGreaterThan(0, $sweep['silver']);
    }

    public function testIslandSystem(): void
    {
        // 1. 查看金银岛
        $res = $this->request('island', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertCount(3, $res['islands']);

        // 2. 挑战蓬莱仙境岛 (island: 1)
        $battle = $this->request('island', 'pk', ['id' => 1]);
        $this->assertSame(1, $battle['status']);
        $this->assertSame(1, $battle['win']);
        $this->assertGreaterThan(0, $battle['silver']);
    }

    public function testHerothroneSystem(): void
    {
        // 1. 查看英雄王座
        $res = $this->request('herothrone', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertCount(3, $res['thrones']);

        // 2. 冲击青铜铁血王座 (throne: 1)
        $action = $this->request('herothrone', 'action', ['id' => 1]);
        $this->assertSame(1, $action['status']);
        $this->assertSame(1, $action['win']);
        $this->assertGreaterThan(0, $action['silver']);
    }

    public function testBeatengodSystem(): void
    {
        // 1. 查看战神试炼
        $res = $this->request('beatengod', 'index');
        $this->assertSame(1, $res['status']);

        // 2. 敲击战神金身
        $beaten = $this->request('beatengod', 'beaten');
        $this->assertSame(1, $beaten['status']);
        $this->assertGreaterThan(0, $beaten['silver']);
    }

    public function testWarSystem(): void
    {
        // 1. 查看皇城国战大厅
        $res = $this->request('war', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['leaderboard']);

        // 2. 报名皇城国战出征
        $entry = $this->request('war', 'entry');
        $this->assertSame(1, $entry['status']);
        $this->assertGreaterThan(0, $entry['silver']);

        // 3. 查看积分功勋商城
        $shop = $this->request('war', 'point_shop');
        $this->assertSame(1, $shop['status']);
        $this->assertArrayHasKey('point', $shop);
        $this->assertNotEmpty($shop['list']);
        $this->assertNotEmpty($shop['goods']);

        // 4. 积分兑换青铜宝匣
        $buyBox = $this->request('war', 'point_buy', ['id' => 1]);
        $this->assertSame(1, $buyBox['status']);
        $this->assertArrayHasKey('point', $buyBox);
        $this->assertArrayHasKey('silver', $buyBox);
    }

    public function testCountryMineSystem(): void
    {
        // 1. 查看国家矿山
        $res = $this->request('countrymine', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['mines']);

        // 2. 占领官渡铜矿
        $claim = $this->request('countrymine', 'caikuang', ['id' => 1]);
        $this->assertSame(1, $claim['status']);
        $this->assertSame(1, $claim['mine_id']);

        // 3. 掠夺敌国矿车
        $rob = $this->request('countrymine', 'rob', ['id' => 2]);
        $this->assertSame(1, $rob['status']);
        $this->assertSame(1, $rob['win']);
        $this->assertGreaterThan(0, $rob['rob_silver']);

        // 4. 收取开采银饷
        $harvest = $this->request('countrymine', 'get_reward');
        $this->assertSame(1, $harvest['status']);
        $this->assertGreaterThan(0, $harvest['silver']);
    }

    public function testCountryBossSystem(): void
    {
        // 1. 查看护国神兽
        $res = $this->request('countryboss', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['boss_name']);

        // 2. 鼓舞军威
        $buff = $this->request('countryboss', 'powerup');
        $this->assertSame(1, $buff['status']);
        $this->assertGreaterThan(0, $buff['buff']);

        // 3. 挑战护国神兽
        $battle = $this->request('countryboss', 'battle');
        $this->assertSame(1, $battle['status']);
        $this->assertGreaterThan(0, $battle['damage']);

        // 4. 领取护国神兽宝箱
        $reward = $this->request('countryboss', 'reward');
        $this->assertSame(1, $reward['status']);
        $this->assertGreaterThan(0, $reward['silver']);
    }

    public function testHiteggSystem(): void
    {
        // 1. 查看砸金蛋
        $res = $this->request('hitegg', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertCount(3, $res['eggs']);

        // 2. 砸碎如意金蛋
        $hit = $this->request('hitegg', 'hit_egg', ['id' => 1]);
        $this->assertSame(1, $hit['status']);
        $this->assertGreaterThan(0, $hit['silver']);
        $this->assertGreaterThan(0, $hit['gold']);
    }

    public function testLevelgiftSystem(): void
    {
        // 1. 查看等级礼包
        $res = $this->request('levelgift', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        // 2. 领取 5 级成长礼包 (如果当前等级达到 5 级)
        $gift = $this->request('levelgift', 'get_reward', ['level' => 5]);
        $this->assertIsArray($gift);
    }

    public function testCountryShopSystem(): void
    {
        // 1. 查看国家特产商城
        $res = $this->request('countryshop', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        // 2. 购买青龙偃月刀图纸
        $buy = $this->request('countryshop', 'buy', ['id' => 1]);
        $this->assertSame(1, $buy['status']);
        $this->assertStringContainsString('青龙偃月刀', $buy['goods_name']);

        // 3. 国家税金商城测试 (c=country_taxes_shop&m=index / buy)
        $taxesShop = $this->request('country_taxes_shop', 'index');
        $this->assertSame(1, $taxesShop['status']);
        $this->assertNotEmpty($taxesShop['shop_list']['list']);
        $this->assertArrayHasKey('member_info', $taxesShop);

        $buyTaxes = $this->request('country_taxes_shop', 'buy', ['id' => 1]);
        $this->assertSame(1, $buyTaxes['status']);
    }

    public function testEquipForgeSystem(): void
    {
        // 1. 查看铸造工坊
        $res = $this->request('equip_forge', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['recipes']);

        // 2. 锻造神装青龙偃月刀
        $forge = $this->request('equip_forge', 'fabrication', ['id' => 1]);
        $this->assertSame(1, $forge['status']);
        $this->assertStringContainsString('青龙偃月刀', $forge['equip_name']);

        // 3. 熔炼一件未穿戴装备
        $pack = $this->request('pack', 'index');
        $freeEid = 0;
        foreach ($pack['list'] as $item) {
            if ($item['is_equipped'] === 0) {
                $freeEid = (int)$item['id'];
                break;
            }
        }
        if ($freeEid > 0) {
            $resolve = $this->request('equip_forge', 'resolve', ['id' => $freeEid]);
            $this->assertSame(1, $resolve['status']);
            $this->assertGreaterThan(0, $resolve['get_iron']);
        }
    }

    public function testTotalLoginSystem(): void
    {
        // 1. 查看累登面板
        $res = $this->request('totallogin', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);

        // 2. 领取第 1 天累登豪礼
        $reward = $this->request('totallogin', 'action', ['day' => 1]);
        $this->assertSame(1, $reward['status']);
        $this->assertGreaterThan(0, $reward['silver']);
    }

    public function testEpicSystem(): void
    {
        // 1. 查看史诗战纪
        $res = $this->request('epic', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertCount(3, $res['list']);

        // 2. 挑战史诗白马之战
        $battle = $this->request('epic', 'force', ['id' => 1]);
        $this->assertSame(1, $battle['status']);
        $this->assertSame(1, $battle['win']);
        $this->assertGreaterThan(0, $battle['silver']);
    }

    public function testActivationCodeSystem(): void
    {
        // 1. 查看激活码主界面
        $res = $this->request('activationcode', 'index');
        $this->assertSame(1, $res['status']);

        // 2. 兑换官方豪礼码 VIP888
        $reward = $this->request('activationcode', 'get_reward', ['code' => 'VIP888']);
        $this->assertSame(1, $reward['status']);
        $this->assertSame(88888, $reward['silver']);
        $this->assertSame(200, $reward['gold']);

        // 3. 重复兑换拦截
        $dup = $this->request('activationcode', 'get_reward', ['code' => 'VIP888']);
        $this->assertSame(-2, $dup['status']);
    }

    public function testExerciseSystem(): void
    {
        // 1. 查看整军演武
        $res = $this->request('exercise', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertGreaterThanOrEqual(1, $res['gift_count']);

        // 2. 拆开整军礼包
        $open = $this->request('exercise', 'open_gift');
        $this->assertSame(1, $open['status']);
        $this->assertGreaterThan(0, $open['silver']);
        $this->assertGreaterThan(0, $open['act']);
    }

    public function testInformationSystem(): void
    {
        // 1. 查看主公总战力总览
        $res = $this->request('information', 'get_info');
        $this->assertSame(1, $res['status']);
        $this->assertGreaterThan(0, $res['total_power']);
        $this->assertGreaterThanOrEqual(1, $res['general_count']);

        // 2. 查看个人成就勋章
        $ach = $this->request('information', 'get_own_achievement');
        $this->assertSame(1, $ach['status']);
        $this->assertNotEmpty($ach['list']);
    }

    public function testInvitationSystem(): void
    {
        // 1. 查看结义邀请
        $res = $this->request('invitation', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['my_code']);

        // 2. 绑定好友结义码
        $bind = $this->request('invitation', 'change', ['code' => 'HJ888888']);
        $this->assertSame(1, $bind['status']);
        $this->assertSame(100, $bind['gold']);

        // 3. 领取邀请达标奖励
        $reward = $this->request('invitation', 'get_reward');
        $this->assertSame(1, $reward['status']);
        $this->assertGreaterThan(0, $reward['silver']);
    }

    public function testLightweightThinModelsArchitecture(): void
    {
        // 1. 验证 Member 模型自动类型转换 (Casting) 与领域方法
        $member = Member::findByUid(self::$uid);
        $this->assertNotNull($member);
        $this->assertIsInt($member->level);
        $this->assertIsInt($member->silver);
        $this->assertIsInt($member->gold);
        $this->assertIsInt($member->uid);
        $this->assertSame(self::$uid, $member->uid);

        // 2. 验证 General 模型自动过滤软删除与领域属性计算
        $generals = General::query()->where('uid', self::$uid)->get();
        $this->assertNotEmpty($generals);
        $firstGen = General::fromArray($generals[0]);
        $this->assertIsInt($firstGen->level);

        $stats = $firstGen->calculateStats();
        $this->assertArrayHasKey('attack', $stats);
        $this->assertArrayHasKey('defense', $stats);
        $this->assertArrayHasKey('hp', $stats);

        // 3. 验证 Equipment 模型属性推算
        $equips = Equipment::query()->where('uid', self::$uid)->get();
        $this->assertNotEmpty($equips);
        $firstEq = Equipment::fromArray($equips[0]);
        $bonus = $firstEq->calculateBonus();
        $this->assertIsArray($bonus);
        $this->assertGreaterThan(0, $firstEq->getSite());
    }

    public function testThrottleProtectionMiddleware(): void
    {
        // 1. 正常操作应畅通无阻
        $res = $this->request('tavern', 'index');
        $this->assertSame(1, $res['status']);

        // 2. 模拟并发重入锁已存在时的防重放拦截
        $lockKey = "hj:lock:req:" . self::$uid . ":tavern.action";
        Redis::set($lockKey, '1', 5);

        $blocked = $this->request('tavern', 'action');
        $this->assertSame(-1, $blocked['status']);
        $this->assertStringContainsString('过于频繁', (string)$blocked['message']);

        // 3. 锁释放后恢复正常
        Redis::del($lockKey);
    }

    public function testHalloweenSystem(): void
    {
        // 1. 查看万圣活动主页
        $res = $this->request('act_halloween', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertNotEmpty($res['list']);
        $this->assertGreaterThan(0, $res['candy']);
        $this->assertGreaterThan(0, $res['hammer']);

        // 2. 砸开 1 号南瓜
        $smash = $this->request('act_halloween', 'action_pumpkin', ['id' => 1]);
        $this->assertSame(1, $smash['status']);
        $this->assertSame(1, $smash['id']);

        // 3. 品尝万圣魔法糖果
        $candy = $this->request('act_halloween', 'action_candy');
        $this->assertSame(1, $candy['status']);
        $this->assertGreaterThan(0, $candy['soul']);
    }

    public function testWorldArenaSystem(): void
    {
        // 1. 查看跨服远征主界面
        $res = $this->request('worldarena', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertArrayHasKey('top', $res);
        $this->assertNotEmpty($res['top']);
        $this->assertArrayHasKey('reward', $res);

        // 2. 领取跨服排行奖励 (验证 add 与 exploit)
        $rankReward = $this->request('worldarena', 'get_rank_reward');
        $this->assertSame(1, $rankReward['status']);
        $this->assertGreaterThan(0, $rankReward['add']);
        $this->assertGreaterThan(0, $rankReward['exploit']);

        // 3. 查看跨服功勋商店 (验证 list 与 exploit)
        $shop = $this->request('worldarena', 'shop');
        $this->assertSame(1, $shop['status']);
        $this->assertNotEmpty($shop['list']);
        $this->assertArrayHasKey('exploit', $shop);

        // 4. 领取跨服押注奖励 (验证 bet.rank.before)
        $betReward = $this->request('worldarena', 'get_bet_reward');
        $this->assertSame(1, $betReward['status']);
        $this->assertArrayHasKey('bet', $betReward);
        $this->assertArrayHasKey('rank', $betReward['bet']);
        $this->assertSame(0, $betReward['bet']['rank']['before']);

        // 5. 进入跨服备战
        $enter = $this->request('worldarena', 'enter');
        $this->assertSame(1, $enter['status']);
    }
}
