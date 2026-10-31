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

    public function testRegistrationInitialState(): void
    {
        // 旧版 login.php：init_general(1, uid) + init_matrix(1, uid, 2, gid)，不送初始装备
        $generals = DB::table('general')->where('uid', self::$uid)->whereNull('deleted_at')->get();
        $this->assertCount(1, $generals);
        $g = $generals[0];
        $tpl = \Hanjiang\MasterData\General::ROWS[1];
        $this->assertSame('廖化', $tpl['name']);
        $this->assertSame(1, (int)$g['generalid']);
        foreach (['name', 'image', 'type', 'wuli', 'zhili', 'tili', 'talentid', 'skillid', 'star'] as $col) {
            $this->assertEquals($tpl[$col], $g[$col], "初始武将 {$col} 必须取自模板");
        }
        $this->assertSame(1, (int)$g['level']);
        $this->assertSame(0, (int)$g['exp']);
        $this->assertSame(0, (int)$g['levelupexp'], 'init_general 的 levelupexp 为 0');
        $this->assertSame(1, (int)$g['goout']);
        $this->assertArrayNotHasKey('pos', $g, '站位只存在阵法表');
        $this->assertArrayNotHasKey('matrix_id', $g);
        $this->assertSame(0, (int)$g['wuliup'] + (int)$g['zhiliup'] + (int)$g['tiliup']);

        $matrix = DB::table('matrix')->where('uid', self::$uid)->whereNull('deleted_at')->get();
        $this->assertCount(1, $matrix);
        $this->assertSame(1, (int)$matrix[0]['mid']);
        $this->assertSame(1, (int)$matrix[0]['level']);
        $this->assertSame((int)$g['id'], (int)$matrix[0]['position2']);
        // 旧版 member_model 注册：默认启用阵法 1
        $this->assertSame(1, (int)DB::table('member_field')->where('uid', self::$uid)->value('matrixid'));
        foreach ([1, 3, 4, 5, 6, 7, 8, 9] as $p) {
            $this->assertSame(0, (int)$matrix[0]['position' . $p]);
        }

        $this->assertSame([], DB::table('equipment')->where('uid', self::$uid)->whereNull('deleted_at')->get(), '注册不送装备');
        // 旧版 member_model->insert_member：将魂包裹 4 格，其余物品计数为 0
        $member = DB::table('member')->where('uid', self::$uid)->first();
        $this->assertSame([4, 0, 0, 0, 0, 0, 0, 0], array_map('intval', [
            $member['soulpack'], $member['souluse'], $member['jade'], $member['gem'], $member['gemuse'],
            $member['refinestone'], $member['colorstone'], $member['bloodstone'],
        ]));
        $this->assertSame([], $this->request('pack', 'index')['list'], '背包为空时不自动补装备');
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
        // 新玩家 VIP 0（旧版 VIP 只由充值累计金额决定），军令上限 50
        $this->assertSame(0, (int)$updated['vip']);
        $this->assertSame(50, (int)$updated['act_limit']);
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
        // 旧版 MasterData Map 1（黄巾势力）通关奖励：装备 6 狮心锁环甲
        $this->assertSame(6, $shiliReward['item']['id']);
        $this->assertSame('狮心锁环甲', $shiliReward['item']['name']);
    }

    /**
     * 物品存储类奖励的发放点（旧版调用 add_talent_scroll / add_item 碎片分支 / add_mount / add_jade 等处）
     */
    public function testItemRewardSitesGrantStorage(): void
    {
        $this->withFreshPlayer(function (int $uid): void {
            // 势力通关奖励：Map 6 为天赋卷轴（add_talent_scroll(reward, 0, rewardnum)），Map 17 为坐骑（add_mount(reward, 0, 0, rewardnum)）
            $map6 = \Hanjiang\MasterData\Map::ROWS[6];
            $res = $this->request('map', 'get_newresult', ['id' => 6]);
            $this->assertSame([1, 2, $map6['rewardnum']], [$res['status'], $res['gettype'], $res['rewardnum']]);
            $scroll = \Hanjiang\MasterData\TalentScroll::ROWS[$map6['reward']];
            $this->assertSame($scroll['type'], $res['item']['id'], '旧版 info.id 改成卷轴类型');
            $this->assertSame($map6['rewardnum'], (int)\Hanjiang\Model\Equipment::talentScroll($uid, $scroll['type'], $scroll['quality'])['number']);
            $this->assertSame(['map', 'get_newresult'], array_values(DB::table('talent_scroll_log')->select('class', 'method')->where('uid', $uid)->first()));
            $map17 = \Hanjiang\MasterData\Map::ROWS[17];
            $res = $this->request('map', 'get_newresult', ['id' => 17]);
            $this->assertSame([1, 4, $map17['reward']], [$res['status'], $res['gettype'], $res['info']['mid']]);
            $this->assertSame($map17['rewardnum'], DB::table('own_mount')->where('uid', $uid)->where('mid', $map17['reward'])->count());
            $this->assertSame(-3, $this->request('map', 'get_newresult', ['id' => 17])['status'], '已领取');

            // 关卡掉落：gettype 2 装备碎片（add_item 碎片分支）、5 天赋卷轴、6 卷轴碎片，件数即数量
            $drop = new \ReflectionMethod(\Hanjiang\Handler\Campaign\MapHandler::class, 'missionDrop');
            $m43 = \Hanjiang\MasterData\Mission::ROWS[43];
            $this->assertSame(2, $m43['gettype']);
            do {
                $res = $drop->invoke(null, $uid, 10, $m43['missionlevel'], $m43['missionstage'], $m43['missionid']);
            } while ($res['get_number'] === 0);
            $equip = \Hanjiang\MasterData\Equip::ROWS[$m43['getvalue']];
            $this->assertSame([2, $equip['name'] . '碎片'], [$res['gettype'], $res['itemname']]);
            $this->assertSame(1, (int)DB::table('equipment')->where('uid', $uid)->where('equipid', $equip['id'])->where('chip', '>', 0)->value('chip'));
            $m212 = \Hanjiang\MasterData\Mission::ROWS[212];
            $this->assertSame([5, 100], [$m212['gettype'], $m212['getrate']]);
            $scroll = \Hanjiang\MasterData\TalentScroll::ROWS[$m212['getvalue']];
            $had = (int)(\Hanjiang\Model\Equipment::talentScroll($uid, $scroll['type'], $scroll['quality'])['number'] ?? 0);
            $res = $drop->invoke(null, $uid, 10, $m212['missionlevel'], $m212['missionstage'], $m212['missionid']);
            $this->assertSame([5, 2, $scroll['type'], \Hanjiang\Model\Equipment::scrollName($scroll['type'])], [$res['gettype'], $res['itemtype'], $res['itemid'], $res['itemname']]);
            $this->assertSame($had + 1, (int)\Hanjiang\Model\Equipment::talentScroll($uid, $scroll['type'], $scroll['quality'])['number']);

            // 平乱副本奖励类型 2 装备碎片（CopiesReward 里没有，直接调发奖分支）：add_item($info, 1, $num)
            $copies = new \ReflectionMethod(\Hanjiang\Handler\Campaign\CopiesHandler::class, 'reward');
            $res = $copies->invoke(null, $uid, ['type' => 2, 'value' => 19, 'num' => 3], 0, 0, time(), 'pk');
            $this->assertSame(19, $res['id']);
            $this->assertSame(3, (int)DB::table('equipment')->where('uid', $uid)->where('equipid', 19)->where('chip', '>', 0)->value('chip'));
            $this->assertSame(['copies', 'pk'], array_values(DB::table('itemchip_log')->select('class', 'method')->where('uid', $uid)->where('itemid', 19)->first()));

            // 国家俸禄：主公 91 级以上照旧版发玉石（add_salary 的 jade），返回当前玉石
            DB::table('member')->where('uid', $uid)->update(['level' => 91]);
            Redis::del('player:' . $uid);
            $this->assertSame(1, $this->request('country', 'create', ['name' => '玉' . substr((string)hrtime(true), -5), 'confirm' => 1])['status']);
            $salary = $this->request('country', 'get_salary');
            $this->assertSame(1, $salary['status']);
            $this->assertGreaterThan(0, $salary['jade']);
            $this->assertSame($salary['jade'], \Hanjiang\Model\MemberJade::get($uid));
            $this->assertSame(['country', 'get_salary'], array_values(DB::table('jade_log')->select('class', 'method')->where('uid', $uid)->first()));

            // 将领界面血量：兵符上的宝石（get_gem_by_eid）与坐骑（主公 120 级起，主战 100%、备战 ceil(× 0.3)）
            $gid = (int)$this->request('general', 'index')['list'][1]['id'];
            DB::table('general')->where('id', $gid)->update(['level' => 10]);
            [$fu] = Equipment::addFromTemplate($uid, 7);
            $this->assertSame(1, $this->request('general', 'equip', ['gid' => $gid, 'eid' => $fu])['status']);
            $hp = fn (): int => $this->request('general', 'get_info', ['gid' => $gid])['info']['hp'];
            $before = $hp();
            \Hanjiang\Model\OwnGem::addGem($uid, 5, 0, 1);
            $gemId = (int)DB::table('own_gem')->where('uid', $uid)->value('id');
            \Hanjiang\Model\OwnGem::adron($uid, $gemId, $fu, 1);
            $row = DB::table('general')->where('id', $gid)->first();
            $talentBlood = (float)\Hanjiang\MasterData\Talent::ROWS[(int)$row['talentid']]['blood'];
            $coef = (1 + ((int)$row['tili'] + (int)$row['tiliup']) * 0.002) * $talentBlood;
            $base = \Hanjiang\Battle\LegacyStats::baseHp((int)$row['level'], (int)$row['turn']) + \Hanjiang\MasterData\Equip::ROWS[7]['power'];
            $this->assertSame((int)($base * $coef), $before);
            $gemPower = \Hanjiang\MasterData\Gem::ROWS[5]['level0'];
            $this->assertSame((int)(($base + $gemPower) * $coef), $hp());
            \Hanjiang\Model\OwnMount::addMount($uid, 1);
            \Hanjiang\Model\OwnMount::addMount($uid, 2);
            $mounts = DB::table('own_mount')->where('uid', $uid)->whereIn('mid', [1, 2])->where('chip', 0)->orderBy('id')->get();
            DB::table('general')->where('id', $gid)->update(['mid0' => (int)$mounts[0]['id'], 'mid1' => (int)$mounts[1]['id']]);
            $this->assertSame((int)(($base + $gemPower) * $coef), $hp(), '主公 120 级以下不计坐骑');
            DB::table('member')->where('uid', $uid)->update(['level' => 120]);
            $mountHp = (int)$mounts[0]['hp'] + ceil((int)$mounts[1]['hp'] * 0.3);
            $this->assertSame((int)(($base + $gemPower + $mountHp) * $coef), $hp());
        });
    }

    public function testBattleSimulationAction(): void
    {
        $res = $this->request('map', 'action', ['l' => 1, 's' => 1, 'id' => 1]);
        $this->assertSame(1, $res['status']);
        // 旧版 info.win 即战斗评级 assess：一击击杀长枪兵，我方满血完胜
        $this->assertSame(3, $res['info']['win']);
        $this->assertArrayHasKey('battle', $res);
        // 守军取旧版 Mission 1（1-1-1）的 position5 = Monster 1 长枪兵：blood 20 × 平庸 1.0
        $monster = \Hanjiang\MasterData\Monster::ROWS[1];
        $this->assertSame([5], array_keys($res['battle']['rightteam']));
        $this->assertSame([
            'heroid' => '1',
            'name' => '长枪兵',
            'herotype' => '1',
            'level' => '1',
            'allblood' => 20,
            'power' => 0,
            'image' => $monster['image'],
            'star' => 0,
            'talent' => '平庸',
            'skill' => '会心一击',
            'quality' => '1',
        ], $res['battle']['rightteam'][5]);
        // 我方取 matrix 站位，字段为旧版 team_return_arr
        $matrix = DB::table('matrix')->where('uid', self::$uid)->whereNull('deleted_at')->first();
        foreach ($res['battle']['leftteam'] as $pos => $unit) {
            $this->assertSame((string)$matrix['position' . $pos], $unit['heroid']);
            $this->assertSame(['heroid', 'name', 'herotype', 'level', 'allblood', 'power', 'image', 'star', 'talent', 'skill', 'quality'], array_keys($unit));
        }
        $this->assertSame(3, $res['battle']['assess']);
        $this->assertNotEmpty($res['battle']['process']);
        // 旧版 battle() 返回结构 + map 控制器附带的双方信息
        $this->assertSame(['leftteam', 'rightteam', 'process', 'assess', 'leftresult', 'rightresult', 'leftbuff', 'rightbuff', 'left_role', 'right_role'], array_keys($res['battle']));
        $this->assertSame(['name' => '长枪兵', 'level' => 1, 'type' => 1], $res['battle']['right_role']);
        $this->assertSame([], $res['battle']['rightresult'], '阵亡单位移出队伍');

        // 1-1-5：Mission 5 只有 position5 = Monster 5 邓茂
        $bossRes = $this->request('map', 'action', ['l' => 1, 's' => 1, 'id' => 5]);
        $this->assertSame(1, $bossRes['status']);
        $this->assertCount(1, $bossRes['battle']['rightteam']);
        $this->assertSame('邓茂', $bossRes['battle']['rightteam'][5]['name']);

        // 旧版 get_mission_by_id 查不到关卡返回 -6
        $this->assertSame(-6, $this->request('map', 'action', ['l' => 1, 's' => 1, 'id' => 99])['status']);

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

    public function testMissionEquipmentDrop(): void
    {
        $this->withFreshPlayer(function (int $uid): void {
            $silver = (int)DB::table('member')->where('uid', $uid)->first()['silver'];

            // 1-1-1 长枪兵：MasterData Mission 1 无掉落
            $plain = $this->request('map', 'action', ['l' => 1, 's' => 1, 'id' => 1]);
            $this->assertGreaterThanOrEqual(1, $plain['info']['win']);
            $this->assertSame(0, $plain['info']['gettype']);
            $this->assertSame(0, $plain['info']['get_number']);
            $this->assertSame('', $plain['info']['itemname']);
            $this->assertArrayNotHasKey('silver', $plain['info'], '胜利不再送新版自定的 500 银币');
            $this->assertSame($silver, (int)DB::table('member')->where('uid', $uid)->first()['silver']);

            // 1-1-2 裴元绍：Mission 2 gettype 1（装备）getvalue 1（赤铜刀）getrate 100
            $before = count(DB::table('equipment')->where('uid', $uid)->where('equipid', 1)->whereNull('deleted_at')->get());
            $res = $this->request('map', 'action', ['l' => 1, 's' => 1, 'id' => 2]);
            $this->assertGreaterThanOrEqual(1, $res['info']['win']);
            $tpl = \Hanjiang\MasterData\Equip::ROWS[1];
            $this->assertSame(1, $res['info']['get_number']);
            $this->assertSame(1, $res['info']['gettype']);
            $this->assertSame(0, $res['info']['getvalue']);
            $this->assertSame(1, $res['info']['itemtype']);
            $this->assertSame($tpl['id'], $res['info']['itemid']);
            $this->assertSame($tpl['quality'], $res['info']['quality']);
            $this->assertSame($tpl['name'], $res['info']['itemname']);
            $items = DB::table('equipment')->where('uid', $uid)->where('equipid', 1)->whereNull('deleted_at')->get();
            $this->assertCount($before + 1, $items);
            $new = end($items);
            $this->assertSame($tpl['power'], (int)$new['powerup']);
            $this->assertSame(0, (int)$new['generalid']);

            // 重刷同一关照样按 getrate 掉落（旧版不区分首杀）
            $this->assertSame(1, $this->request('map', 'action', ['l' => 1, 's' => 1, 'id' => 2])['info']['gettype']);
        });
    }

    public function testMapActionWithoutFormationFollowsLegacy(): void
    {
        $this->withFreshPlayer(function (int $uid): void {
            $before = DB::table('member')->where('uid', $uid)->first();

            // 阵法里没有武将：旧版 battle_mission 把 -2 当空队伍继续战斗，空队伍直接判大败 assess -3，没有战斗过程，不再造「天命主公」
            $positions = [];
            for ($i = 1; $i <= 9; $i++) {
                $positions['position' . $i] = 0;
            }
            DB::table('matrix')->where('uid', $uid)->update($positions);
            $res = $this->request('map', 'action', ['l' => 1, 's' => 1, 'id' => 1]);
            $this->assertSame(1, $res['status']);
            $this->assertSame(-3, $res['info']['win']);
            $this->assertSame([], $res['battle']['leftteam']);
            $this->assertSame([], $res['battle']['process']);
            $this->assertSame(-3, $res['battle']['assess']);
            $this->assertSame('长枪兵', $res['battle']['rightteam'][5]['name']);
            $this->assertSame(0, $res['info']['exp']);
            $after = DB::table('member')->where('uid', $uid)->first();
            $this->assertSame((int)$before['missionid'], (int)$after['missionid']);

            // 启用的阵法行查不到：旧版 empty(get_own_by_id) 不成立，按空站位继续（同上，大败）
            DB::table('matrix')->where('uid', $uid)->update(['deleted_at' => date('Y-m-d H:i:s')]);
            $res = $this->request('map', 'action', ['l' => 1, 's' => 1, 'id' => 1]);
            $this->assertSame(-3, $res['info']['win']);
            $this->assertSame(-3, $res['battle']['assess']);

            // 没有阵法（member_field.matrixid = 0）：旧版 battle_mission 返回 -1，map 控制器把 assess 记 0（win 0），battle 只有 assess 与双方信息
            DB::table('member_field')->where('uid', $uid)->update(['matrixid' => 0]);
            $res = $this->request('map', 'action', ['l' => 1, 's' => 1, 'id' => 1]);
            $this->assertSame(1, $res['status']);
            $this->assertSame(0, $res['info']['win']);
            $this->assertSame(['assess', 'left_role', 'right_role'], array_keys($res['battle']));
            $this->assertSame(0, $res['battle']['assess']);
        });
    }

    /**
     * 临时切换到一个新注册的游客账号执行 $fn（新号：1 级、VIP 1、银币 88888、元宝 9999、廖化在 1 号阵 2 号位）
     */
    private function withFreshPlayer(callable $fn): void
    {
        [$oldToken, $oldUid] = [self::$token, self::$uid];
        self::$token = null;
        self::$uid = 0;
        try {
            $reg = $this->request('register', 'guest');
            self::$uid = (int)$reg['uid'];
            $login = $this->request('login', 'guest', ['uid' => self::$uid, 'guestkey' => $reg['guestkey']]);
            self::$token = (string)$login['token'];
            $fn(self::$uid);
        } finally {
            self::$token = $oldToken;
            self::$uid = $oldUid;
        }
    }

    /**
     * 临时把当前账号阵法里的武将提到 60 级执行 $fn，结束后恢复等级
     *
     * 这些玩法的守军仍是新版自定的数值（依赖未移植），而我方已按旧版公式组队：
     * 低级武将打不过自定守军，测试奖励流程时需要一支够强的阵容。
     */
    private function withStrongFormation(callable $fn): void
    {
        $matrix = DB::table('matrix')->where('uid', self::$uid)->whereNull('deleted_at')->first();
        $gids = [];
        for ($i = 1; $i <= 9; $i++) {
            if ((int)$matrix['position' . $i] > 0) {
                $gids[] = (int)$matrix['position' . $i];
            }
        }
        $levels = [];
        foreach (DB::table('general')->whereIn('id', $gids)->get() as $g) {
            $levels[(int)$g['id']] = (int)$g['level'];
        }
        DB::table('general')->whereIn('id', $gids)->update(['level' => 60]);
        try {
            $fn();
        } finally {
            foreach ($levels as $id => $level) {
                DB::table('general')->where('id', $id)->update(['level' => $level]);
            }
        }
    }

    public function testGeneralSystem(): void
    {
        // 旧版 general.php:index 只列出征将领（goout = 1），键从 1 开始
        $res = $this->request('general', 'index');
        $this->assertSame(1, $res['status']);
        $goout = DB::table('general')->where('uid', self::$uid)->where('goout', 1)->whereNull('deleted_at')->get();
        $this->assertSame(range(1, count($goout)), array_keys($res['list']));
        $this->assertSame(0, $res['tree']);
        $gid = (int)$res['list'][1]['id'];
        $row = DB::table('general')->where('id', $gid)->first();

        $info = $this->request('general', 'get_info', ['gid' => $gid]);
        $this->assertSame(1, $info['status']);
        $this->assertSame(
            ['id', 'uid', 'quality', 'name', 'level', 'image', 'type', 'wuli', 'zhili', 'tili', 'wuliup', 'zhiliup', 'tiliup',
                'star', 'turn', 'goout', 'skill', 'talent', 'eid1', 'eid2', 'eid3', 'eid4', 'mid0', 'mid1', 'mid2', 'mid3', 'mid4',
                'achID', 'achList', 'hp'],
            array_keys($info['info']),
            'get_info.info 必须正好是旧版 info_field + hp',
        );
        $this->assertSame((int)$row['star'], $info['info']['star']);
        $this->assertSame(1, $info['info']['goout']);

        // 技能 / 天赋取自 MasterData（旧版 get_skill_by_id / get_talent_by_id），天赋不带 blood
        $skill = \Hanjiang\MasterData\Skill::ROWS[(int)$row['skillid']];
        $talent = \Hanjiang\MasterData\Talent::ROWS[(int)$row['talentid']];
        $this->assertSame(['id' => $skill['id'], 'name' => $skill['name'], 'description' => $skill['description']], $info['info']['skill']);
        $this->assertSame(['id' => $talent['id'], 'name' => $talent['name'], 'description' => $talent['description']], $info['info']['talent']);

        // 没穿兵符：hp = intval((80 + 50 * (等级-1) * 转生系数) * (1 + (体力+体力培养) * 0.002) * 天赋血量系数)
        $baseHp = 80 + 50 * ((int)$row['level'] - 1) * \Hanjiang\Battle\LegacyStats::turnCoefficient((int)$row['turn']);
        $this->assertSame(0, $info['info']['eid4']);
        $this->assertSame(
            (int)($baseHp * (1 + ((int)$row['tili'] + (int)$row['tiliup']) * 0.002) * $talent['blood']),
            $info['info']['hp'],
        );

        // 参数错误 -1；不存在或未出征 2
        $this->assertSame(-1, $this->request('general', 'get_info', ['gid' => 0])['status']);
        $this->assertSame(2, $this->request('general', 'get_info', ['gid' => 999999999])['status']);
        $rest = General::createFromTemplate(self::$uid, 2);
        $this->assertSame(2, $this->request('general', 'get_info', ['gid' => $rest])['status']);
        $this->assertNotContains($rest, array_column($this->request('general', 'index')['list'], 'id'), '未出征将领不在 index 列表');
        DB::table('general')->where('id', $rest)->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    public function testMatrixSystem(): void
    {
        $this->withFreshPlayer(function (int $uid): void {
            $mainId = (int)DB::table('general')->where('uid', $uid)->value('id');

            // index：1 级主公只拥有 1 号锥形阵（init_matrix），启用的阵法输出整行（关闭位置为 -1）与加成
            $res = $this->legacyRequest('matrix', 'index');
            $this->assertSame(['status', 'matrix', 'general'], array_keys($res));
            $this->assertSame(1, $res['status']);
            $this->assertSame([1], array_keys($res['matrix']));
            $open = $res['matrix'][1];
            $this->assertSame(
                ['mid', 'position1', 'position2', 'position3', 'position4', 'position5', 'position6', 'position7', 'position8', 'position9', 'level', 'addition', 'open', 'vip', 'sort'],
                array_keys($open),
            );
            // 锥形阵 1 级只开放模板 position1 / position2 = 2、4 号位
            $this->assertSame([-1, $mainId, -1, 0, -1, -1, -1, -1, -1], array_map(fn (int $i) => $open['position' . $i], range(1, 9)));
            // 加成：type = addtype 1，percent = 1 级 × 1；next = MatrixLevel[2].price × ratio 1
            $this->assertSame(['type' => 1, 'percent' => 1, 'next' => \Hanjiang\MasterData\MatrixLevel::ROWS[2]['price']], $open['addition']);
            $this->assertSame([1, 0, 1], [$open['open'], $open['vip'], $open['sort']]);
            $this->assertSame([1 => ['id' => $mainId, 'goout' => 1, 'quality' => 1, 'name' => '廖化', 'image' => 1, 'level' => 1]], $res['general']);

            // 主公 10 级、VIP 7：补齐 2 号锋矢阵（主公 10 级）与 9 号壁垒阵（VIP 7），新阵法等级 0；VIP 阵法排在前面
            DB::table('member')->where('uid', $uid)->update(['level' => 10, 'vip' => 7]);
            $res = $this->legacyRequest('matrix', 'index');
            $this->assertSame([1, 2, 9], array_keys($res['matrix']));
            $this->assertSame(['mid' => 2, 'open' => 0, 'vip' => 0, 'sort' => 3], $res['matrix'][2]);
            $this->assertSame(['mid' => 9, 'open' => 0, 'vip' => 7, 'sort' => 1], $res['matrix'][9]);
            $this->assertSame(2, $res['matrix'][1]['sort']);
            $this->assertSame(0, (int)DB::table('matrix')->where('uid', $uid)->where('mid', 2)->value('level'));

            // get_info：mid 0 取启用的阵法；未拥有 -1；VIP 阵法输出 vip 等级
            $info = $this->legacyRequest('matrix', 'get_info', ['mid' => 0]);
            $this->assertSame(1, $info['status']);
            $this->assertSame([$uid, 1, -1, $mainId], [$info['matrix']['uid'], $info['matrix']['mid'], $info['matrix']['position1'], $info['matrix']['position2']]);
            $this->assertSame(-1, $this->legacyRequest('matrix', 'get_info', ['mid' => 5])['status']);
            $vipInfo = $this->legacyRequest('matrix', 'get_info', ['mid' => 9]);
            $this->assertSame(7, $vipInfo['matrix']['vip']);
            // 壁垒阵 0 级：percent 0，next = MatrixLevel[1].price × ratio 2
            $this->assertSame(['type' => 5, 'percent' => 0, 'next' => \Hanjiang\MasterData\MatrixLevel::ROWS[1]['price'] * 2], $vipInfo['matrix']['addition']);

            // update_matrix 状态码
            $this->assertSame(-1, $this->legacyRequest('matrix', 'update_matrix', ['list' => '0,0,0', 'mid' => 1])['status']);
            $this->assertSame(-2, $this->legacyRequest('matrix', 'update_matrix', ['list' => "0,{$mainId},0,0,0,0,0,0,0", 'mid' => 12])['status'], '阵法 id 错误');
            $this->assertSame(-2, $this->legacyRequest('matrix', 'update_matrix', ['list' => "0,{$mainId},0,0,0,0,0,0,0", 'mid' => 5])['status'], '未拥有');
            $this->assertSame(-2, $this->legacyRequest('matrix', 'update_matrix', ['list' => "-1,{$mainId},-1,0,-1,-1,-1,-1,-1", 'mid' => 1])['status'], '站位没有变化');
            $empty = $this->legacyRequest('matrix', 'update_matrix', ['list' => '0,0,0,0,0,0,0,0,0', 'mid' => 1]);
            $this->assertSame(['status' => 1, 'tip' => 1], $empty, '阵法上没有将领仍然保存，tip = 1');
            $this->assertSame(1, $this->legacyRequest('matrix', 'update_matrix', ['list' => "0,{$mainId},0,0,0,0,0,0,0", 'mid' => 1])['status']);
            // 关闭的位置写 0：主公放在 1 号位（锥形阵 1 级关闭）不会上阵
            $this->assertSame(1, $this->legacyRequest('matrix', 'update_matrix', ['list' => "{$mainId},0,0,0,0,0,0,0,0", 'mid' => 1])['status']);
            $this->assertSame([0, 0], array_map('intval', array_values(DB::table('matrix')->where('uid', $uid)->where('mid', 1)->select('position1', 'position2')->first())));
            $this->assertSame(1, $this->legacyRequest('matrix', 'update_matrix', ['list' => "0,{$mainId},0,0,0,0,0,0,0", 'mid' => 1])['status']);

            // levelup：花费 intval(MatrixLevel[新等级].price × ratio) 元宝；阵法等级上限 = 主公等级 / 5
            $gold = (int)DB::table('member')->where('uid', $uid)->value('gold');
            $up = $this->legacyRequest('matrix', 'levelup', ['mid' => 1]);
            $this->assertSame(1, $up['status']);
            $cost = \Hanjiang\MasterData\MatrixLevel::ROWS[2]['price'] * \Hanjiang\MasterData\Matrix::ROWS[1]['ratio'];
            $this->assertSame([
                'gold' => $gold - $cost,
                'level' => 2,
                'type' => 1,
                'position' => 0,
                'percent' => 2,
                'next' => \Hanjiang\MasterData\MatrixLevel::ROWS[3]['price'],
            ], $up['info']);
            $this->assertSame($gold - $cost, (int)DB::table('member')->where('uid', $uid)->value('gold'));
            $this->assertSame(2, (int)DB::table('matrix')->where('uid', $uid)->where('mid', 1)->value('level'));
            // 3 级需要主公 15 级
            $this->assertSame(['info' => ['matrix_level' => 3, 'master_level' => 15], 'status' => 3], $this->legacyRequest('matrix', 'levelup', ['mid' => 1]));
            // 升到 3 级开放模板 position3 = 6 号位
            DB::table('member')->where('uid', $uid)->update(['level' => 15]);
            $this->assertSame(6, $this->legacyRequest('matrix', 'levelup', ['mid' => 1])['info']['position']);
            // 壁垒阵 ratio 2：0 → 1 级花 MatrixLevel[1].price × 2；元宝不足 4
            DB::table('member')->where('uid', $uid)->update(['gold' => 1]);
            $this->assertSame(4, $this->legacyRequest('matrix', 'levelup', ['mid' => 9])['status']);
            $this->assertSame(-1, $this->legacyRequest('matrix', 'levelup', ['mid' => 5])['status']);
            DB::table('matrix')->where('uid', $uid)->where('mid', 1)->update(['level' => 100]);
            DB::table('member')->where('uid', $uid)->update(['level' => 500]);
            $this->assertSame(2, $this->legacyRequest('matrix', 'levelup', ['mid' => 1])['status'], '阵法满级');
        });
    }

    public function testUseMatrixSwitchesActiveMatrixForBattle(): void
    {
        $this->withFreshPlayer(function (int $uid): void {
            $mainId = (int)DB::table('general')->where('uid', $uid)->value('id');
            DB::table('member')->where('uid', $uid)->update(['level' => 10]);
            $this->legacyRequest('matrix', 'index');

            // 参数错误 2；未拥有 -2；阵法上没有将领 3
            $this->assertSame(2, $this->legacyRequest('matrix', 'use_matrix', ['mid' => 0])['status']);
            $this->assertSame(-2, $this->legacyRequest('matrix', 'use_matrix', ['mid' => 5])['status']);
            $this->assertSame(3, $this->legacyRequest('matrix', 'use_matrix', ['mid' => 2])['status']);
            // 已是启用的阵法：旧版 affected_rows 为 0，启用失败 -1
            $this->assertSame(-1, $this->legacyRequest('matrix', 'use_matrix', ['mid' => 1])['status']);

            // 2 号锋矢阵 0 级开放模板 position1 / position2 = 1、2 号位：主公放 1 号位后启用
            $this->assertSame(1, $this->legacyRequest('matrix', 'update_matrix', ['list' => "{$mainId},0,0,0,0,0,0,0,0", 'mid' => 2])['status']);
            $this->assertSame(['status' => 1], $this->legacyRequest('matrix', 'use_matrix', ['mid' => 2]));
            $this->assertSame(2, (int)DB::table('member_field')->where('uid', $uid)->value('matrixid'));

            // 战斗读启用的阵法：主公在 1 号位，阵法类型取锋矢阵模板，0 级加成 0
            $built = \Hanjiang\Battle\TeamBuilder::forMember($uid, pve: true);
            $this->assertSame(1, $built['status']);
            $this->assertSame([1], array_keys($built['team']));
            $this->assertSame(\Hanjiang\MasterData\Matrix::ROWS[2]['addtype'], $built['matrix_type']);
            $this->assertEqualsWithDelta(0.0, $built['matrix_coe'], 1e-9);
            $battle = $this->legacyRequest('map', 'action', ['l' => 1, 's' => 1, 'id' => 1]);
            $this->assertSame([1], array_keys($battle['battle']['leftteam']));

            $res = $this->legacyRequest('matrix', 'index');
            $this->assertSame([0, 1], [$res['matrix'][1]['open'], $res['matrix'][2]['open']]);
            $this->assertSame(['mid' => 1, 'open' => 0, 'vip' => 0, 'sort' => 1], $res['matrix'][1]);

            // 切回 1 号阵：战斗回到 2 号位
            $this->assertSame(1, $this->legacyRequest('matrix', 'use_matrix', ['mid' => 1])['status']);
            $this->assertSame([2], array_keys(\Hanjiang\Battle\TeamBuilder::forMember($uid)['team']));
        });
    }

    public function testMusterGoBattleAndGoRest(): void
    {
        $this->withFreshPlayer(function (int $uid): void {
            $mainId = (int)DB::table('general')->where('uid', $uid)->value('id');
            $zhou = (int)General::createFromTemplate($uid, 2);
            $zhang = (int)General::createFromTemplate($uid, 3);

            // index：按 goout DESC, star DESC, level DESC；list 键从 1 开始，只有旧版 list_field
            $index = $this->legacyRequest('muster', 'index', ['page' => 1, 'perpage' => 999]);
            $this->assertSame(['status', 'page', 'list', 'first'], array_keys($index), 'VIP 7 以下不输出 sale_cd');
            $this->assertSame([1, 2, 3], array_keys($index['list']));
            $this->assertSame(['id', 'quality', 'name', 'image', 'type', 'goout'], array_keys($index['list'][1]));
            $this->assertSame([$mainId, 1], [$index['list'][1]['id'], $index['list'][1]['goout']]);
            $this->assertSame($zhang, $index['list'][2]['id'], '张梁 3 星排在周仓 2 星前');
            $this->assertSame(
                ['id', 'quality', 'image', 'type', 'star', 'name', 'turn', 'level', 'wuli', 'zhili', 'tili', 'zhiliup', 'tiliup', 'wuliup', 'skill', 'talent', 'goout'],
                array_keys($index['first']),
            );
            $info = $this->legacyRequest('muster', 'get_general_info', ['gid' => $zhou]);
            $this->assertSame(['id', 'image', 'quality'], array_slice(array_keys($info['info']), 0, 3));
            $this->assertSame(0, $info['info']['goout']);
            $this->assertSame(-1, $this->legacyRequest('muster', 'get_general_info', ['gid' => 999999999])['status']);

            // go_battle：-1 不存在；1 出征；2 已在出征队列；只改 goout，不动阵法
            $this->assertSame(['status' => -1], $this->legacyRequest('muster', 'go_battle', ['gid' => 999999999]));
            $this->assertSame(['status' => 1], $this->legacyRequest('muster', 'go_battle', ['gid' => $zhou]));
            $this->assertSame(['status' => 2], $this->legacyRequest('muster', 'go_battle', ['gid' => $zhou]));
            $this->assertSame(1, (int)DB::table('general')->where('id', $zhou)->value('goout'));
            $this->assertSame(0, (int)DB::table('matrix')->where('uid', $uid)->where('mid', 1)->value('position4'));

            // 周仓上 1 号阵 4 号位、2 号阵 2 号位，穿一件装备、骑坐骑、在训练位上
            DB::table('member')->where('uid', $uid)->update(['level' => 10]);
            $this->legacyRequest('matrix', 'index');
            $this->assertSame(1, $this->legacyRequest('matrix', 'update_matrix', ['list' => "0,{$mainId},0,{$zhou},0,0,0,0,0", 'mid' => 1])['status']);
            $this->assertSame(1, $this->legacyRequest('matrix', 'update_matrix', ['list' => "0,{$zhou},0,0,0,0,0,0,0", 'mid' => 2])['status']);
            [$eid] = Equipment::addFromTemplate($uid, 1);
            DB::table('equipment')->where('id', $eid)->update(['generalid' => $zhou]);
            DB::table('general')->where('id', $zhou)->update(['mid0' => 7]);
            $this->legacyRequest('practice', 'index');
            $slot = DB::table('practice')->where('uid', $uid)->where('squence', 1)->first();
            $end = time() + 3600;
            DB::table('practice')->where('id', $slot['id'])->update(['generalid' => $zhou, 'type' => 1, 'starttime' => time(), 'endtime' => $end]);
            DB::table('member_field')->where('uid', $uid)->update(['practicenow' => 1, 'practicetime' => $end]);

            // 训练中：不确认 2；confrim = 1 终止训练后休息
            $this->assertSame(['status' => 2, 'tip' => 0], $this->legacyRequest('muster', 'go_rest', ['gid' => $zhou]));
            $this->assertSame(['status' => 1, 'tip' => 0], $this->legacyRequest('muster', 'go_rest', ['gid' => $zhou, 'confrim' => 1]));
            $this->assertSame(0, (int)DB::table('practice')->where('id', $slot['id'])->value('generalid'));
            $field = DB::table('member_field')->where('uid', $uid)->first();
            $this->assertSame([0, 0], [(int)$field['practicenow'], (int)$field['practicetime']]);
            // 从所有阵法撤下、卸下装备与坐骑、goout = 0
            $this->assertSame(0, (int)DB::table('matrix')->where('uid', $uid)->where('mid', 1)->value('position4'));
            $this->assertSame(0, (int)DB::table('matrix')->where('uid', $uid)->where('mid', 2)->value('position2'));
            $this->assertSame($mainId, (int)DB::table('matrix')->where('uid', $uid)->where('mid', 1)->value('position2'));
            $this->assertSame(0, (int)DB::table('equipment')->where('id', $eid)->value('generalid'));
            $row = DB::table('general')->where('id', $zhou)->first();
            $this->assertSame([0, 0], [(int)$row['goout'], (int)$row['mid0']]);

            // 只剩 1 人出征：最后一个将领 4（先于其他检查）
            $this->assertSame(['status' => 4, 'tip' => 0], $this->legacyRequest('muster', 'go_rest', ['gid' => $mainId]));
            $this->assertSame(['status' => 4, 'tip' => 0], $this->legacyRequest('muster', 'go_rest', ['gid' => $zhou]));

            // 主公是启用阵法上唯一的将领：照样撤下，tip = 1；已休息 3
            $this->assertSame(1, $this->legacyRequest('muster', 'go_battle', ['gid' => $zhang])['status']);
            $this->assertSame(['status' => 1, 'tip' => 1], $this->legacyRequest('muster', 'go_rest', ['gid' => $mainId]));
            $this->assertSame(0, (int)DB::table('matrix')->where('uid', $uid)->where('mid', 1)->value('position2'));
            $this->assertSame(1, $this->legacyRequest('muster', 'go_battle', ['gid' => $zhou])['status']);
            $this->assertSame(['status' => 3, 'tip' => 0], $this->legacyRequest('muster', 'go_rest', ['gid' => $mainId]));
            $this->assertSame(['status' => -3, 'tip' => 0], $this->legacyRequest('muster', 'go_rest', ['gid' => 999999999]));

            // 出征名单上限 8 人（battle_count >= 8 先于其他检查）
            foreach ([4, 5, 6, 7, 8, 9] as $tpl) {
                $gid = (int)General::createFromTemplate($uid, $tpl);
                $this->assertSame(['status' => 1], $this->legacyRequest('muster', 'go_battle', ['gid' => $gid]));
            }
            $this->assertSame(8, (int)DB::table('general')->where('uid', $uid)->where('goout', 1)->count());
            $this->assertSame(['status' => 3], $this->legacyRequest('muster', 'go_battle', ['gid' => $mainId]));
            $this->assertSame(['status' => 3], $this->legacyRequest('muster', 'go_battle', ['gid' => $zhou]), '已出征也先返回队列已满');
        });
    }

    /**
     * 请求并去掉 ResponseEnvelopeMiddleware 补的 message / msg，便于与旧版返回整体比对
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function legacyRequest(string $controller, string $method, array $params = []): array
    {
        $res = $this->request($controller, $method, $params);
        unset($res['message'], $res['msg']);
        return $res;
    }

    public function testPackSystem(): void
    {
        $this->withFreshPlayer(function (int $uid): void {
            $empty = $this->request('pack', 'index');
            $this->assertSame(1, $empty['status']);
            $this->assertSame([], $empty['list'], '背包为空时 status 1、空列表');

            [$knife] = Equipment::addFromTemplate($uid, 1);    // 赤铜刀 售价 220
            [$armor] = Equipment::addFromTemplate($uid, 2);    // 赤铜甲 售价 100
            DB::table('equipment')->where('id', $armor)->update(['hammer' => 1, 'level' => 3, 'powerup' => 23]);
            [$chip] = Equipment::addFromTemplate($uid, 20);    // 帝王铠 紫 5 → 10 片合成
            DB::table('equipment')->where('id', $chip)->update(['chip' => 3, 'saleprice' => 1001]);
            $scroll = (int)DB::table('equipment')->insert(['uid' => $uid, 'equipid' => 1, 'name' => '物攻天赋卷轴', 'type' => 6, 'quality' => 1, 'number' => 3, 'saleprice' => 1000]);
            $empty = (int)DB::table('equipment')->insert(['uid' => $uid, 'equipid' => 2, 'name' => '空卷轴', 'type' => 6, 'quality' => 1, 'number' => 0]);
            $gid = (int)$this->request('general', 'index')['list'][1]['id'];
            [$worn] = Equipment::addFromTemplate($uid, 1);
            $this->assertSame(1, $this->request('general', 'equip', ['gid' => $gid, 'eid' => $worn])['status']);

            $res = $this->request('pack', 'index');
            $this->assertSame(1, $res['status']);
            $this->assertArrayNotHasKey('maxnum', $res);
            $list = array_column($res['list'], null, 'id');
            $this->assertArrayNotHasKey($worn, $list, '背包只列未穿戴');
            $this->assertArrayNotHasKey($empty, $list, 'type > 5 且 number = 0 的不列');
            // 排序 quality DESC, type ASC, ...
            $this->assertSame([$chip, $knife, $armor, $scroll], array_column($res['list'], 'id'));

            $this->assertSame(220, $list[$knife]['saleprice']);
            $this->assertSame(\Hanjiang\MasterData\Equip::ROWS[1]['description'], $list[$knife]['description']);
            $this->assertSame(0, $list[$knife]['is_equipped']);
            // 背包不做精炼加成（旧版累加在局部变量上），只给 hammer_quality
            $this->assertSame(23, $list[$armor]['powerup']);
            $this->assertSame(\Hanjiang\MasterData\RefinePromote::ROWS[0]['type'], $list[$armor]['hammer_quality']);
            // 碎片：ceil(1001 / 10) * 3，full_num「3/10」
            $this->assertSame(101 * 3, $list[$chip]['saleprice']);
            $this->assertSame('3/10', $list[$chip]['full_num']);
            // 卷轴：售价 * 个数
            $this->assertSame(3000, $list[$scroll]['saleprice']);

            // VIP 11 及以上整件装备售价 = 模板售价 + 累计强化花费
            DB::table('member')->where('uid', $uid)->update(['vip' => 11]);
            $vipList = array_column($this->request('pack', 'index')['list'], null, 'id');
            $this->assertSame(100 + \Hanjiang\Battle\LegacyStats::strengthenTotalCost(3, 1, 3), $vipList[$armor]['saleprice']);
            DB::table('member')->where('uid', $uid)->update(['vip' => 1]);
        });
    }

    public function testGeneralInfoHpAndEquipmentDisplay(): void
    {
        $this->withFreshPlayer(function (int $uid): void {
            $gid = (int)$this->request('general', 'index')['list'][1]['id'];
            $row = DB::table('general')->where('id', $gid)->first();

            // 帝皇印（兵符 type 5、紫 5、初始 5000）强化 10 级 + 精炼 1 级 + 强化成就 1
            [$seal] = Equipment::addFromTemplate($uid, 54);
            DB::table('equipment')->where('id', $seal)->update([
                'level' => 10,
                'powerup' => \Hanjiang\Battle\LegacyStats::powerup(5000, 10, 5, 5),
                'hammer' => 1,
                'aid' => '1',
            ]);
            [$armor] = Equipment::addFromTemplate($uid, 2);

            $res = $this->request('general', 'equip', ['gid' => $gid, 'eid' => $seal]);
            $this->assertSame(1, $res['status']);
            $this->assertSame(['status', 'info', 'equipments'], array_values(array_diff(array_keys($res), ['message', 'msg'])), '信封外的字段同旧版');

            // 显示能力：5840 + round(840 * 20%) = 6008，再 + round(6008 * 强化成就1 add_per5 5%) = 6308；锤子类型取 refine_promote.type
            $eid4 = $res['info']['eid4'];
            $this->assertSame(
                ['id', 'equipid', 'name', 'type', 'quality', 'needlevel', 'power', 'powerup', 'level', 'saleprice', 'hammer', 'aid', 'hammer_quality', 'number', 'newquality'],
                array_keys($eid4),
            );
            $this->assertSame(5840 + 168 + 300, $eid4['powerup']);
            $this->assertSame(\Hanjiang\MasterData\RefinePromote::ROWS[0]['type'], $eid4['hammer_quality']);

            // 血量 = intval((成长血量 + 兵符显示能力) * (1 + (体力+体力培养) * 0.002) * 天赋血量系数)
            $blood = \Hanjiang\MasterData\Talent::ROWS[(int)$row['talentid']]['blood'];
            $expectedHp = (int)((80 + 6308) * (1 + ((int)$row['tili'] + (int)$row['tiliup']) * 0.002) * $blood);
            $this->assertSame($expectedHp, $res['info']['hp']);
            $this->assertSame($expectedHp, $this->request('general', 'get_info', ['gid' => $gid])['info']['hp']);

            // 未穿戴列表（get_unequip_list）字段与 etype
            $list = $res['equipments'];
            $this->assertSame([1], array_keys($list));
            $this->assertSame(
                ['id', 'equipid', 'type', 'name', 'quality', 'needlevel', 'power', 'powerup', 'level', 'saleprice', 'hammer', 'aid', 'newquality', 'available', 'etype', 'hammer_quality'],
                array_keys($list[1]),
            );
            $this->assertSame($armor, $list[1]['id']);
            $this->assertSame(2, $list[1]['etype']);
            $this->assertSame(1, $list[1]['available']);

            // needlevel 高于将领等级的装备照样列出，available = 0；谋士武器不在猛将的列表里
            [$blade] = Equipment::addFromTemplate($uid, 3);
            [$fan] = Equipment::addFromTemplate($uid, 23);
            $ids = array_column($this->request('general', 'get_info', ['gid' => $gid])['equipments'], 'available', 'id');
            $this->assertSame(0, $ids[$blade]);
            $this->assertArrayNotHasKey($fan, $ids);

            // 4 件都有精炼等级时触发精炼成就：achID = 最低精炼等级的 refine_promote.type，武力/智力培养加 refine_achieve
            foreach ([1, 2, 21] as $tplId) {
                [$eq] = Equipment::addFromTemplate($uid, $tplId);
                DB::table('equipment')->where('id', $eq)->update(['hammer' => 1]);
                $this->assertSame(1, $this->request('general', 'equip', ['gid' => $gid, 'eid' => $eq])['status']);
            }
            $full = $this->request('general', 'get_info', ['gid' => $gid])['info'];
            $achType = \Hanjiang\MasterData\RefinePromote::ROWS[0]['type'];
            $this->assertSame($achType, $full['achID']);
            $this->assertSame((int)$row['wuliup'] + \Hanjiang\MasterData\RefineAchieve::ROWS[1]['add_type_val1'], $full['wuliup']);
            $this->assertSame((int)$row['zhiliup'] + \Hanjiang\MasterData\RefineAchieve::ROWS[1]['add_type_val2'], $full['zhiliup']);
        });
    }

    public function testEquipValidationsAndUnequip(): void
    {
        $this->withFreshPlayer(function (int $uid): void {
            $gid = (int)$this->request('general', 'index')['list'][1]['id'];
            [$knife] = Equipment::addFromTemplate($uid, 1);   // 赤铜刀 猛将武器 1 级
            [$blade] = Equipment::addFromTemplate($uid, 3);   // 劈山刀 7 级
            [$fan] = Equipment::addFromTemplate($uid, 23);    // 七星琉璃扇 谋士武器
            [$blood] = Equipment::addFromTemplate($uid, 52);  // 血祭 猛将武器 1 级
            [$chip] = Equipment::addFromTemplate($uid, 2);
            DB::table('equipment')->where('id', $chip)->update(['chip' => 1]);
            $scroll = (int)DB::table('equipment')->insert(['uid' => $uid, 'equipid' => 1, 'name' => '卷轴', 'type' => 6, 'quality' => 1]);
            [$gone] = Equipment::addFromTemplate($uid, 2);
            DB::table('equipment')->where('id', $gone)->update(['deleted_at' => date('Y-m-d H:i:s')]);
            [$others] = Equipment::addFromTemplate(990003, 1);
            $zhoucang = General::createFromTemplate($uid, 2);
            [$worn] = Equipment::addFromTemplate($uid, 2);
            DB::table('equipment')->where('id', $worn)->update(['generalid' => $zhoucang]);

            // 旧版 equipment_model::equip 的校验顺序与 general.php:equip 的状态码映射
            $this->assertSame(-1, $this->request('general', 'equip', ['gid' => 999999999, 'eid' => $knife])['status']);
            $this->assertSame(-4, $this->request('general', 'equip', ['gid' => $gid, 'eid' => 999999999])['status']);
            $this->assertSame(-4, $this->request('general', 'equip', ['gid' => $gid, 'eid' => $others])['status'], '他人装备');
            $this->assertSame(-4, $this->request('general', 'equip', ['gid' => $gid, 'eid' => $gone])['status'], '已删除装备');
            $this->assertSame(-5, $this->request('general', 'equip', ['gid' => $gid, 'eid' => $fan])['status'], '猛将不能装谋士武器');
            $this->assertSame(-3, $this->request('general', 'equip', ['gid' => $gid, 'eid' => $chip])['status'], '碎片');
            $this->assertSame(-3, $this->request('general', 'equip', ['gid' => $gid, 'eid' => $scroll])['status'], 'type > 5');
            $this->assertSame(-3, $this->request('general', 'equip', ['gid' => $gid, 'eid' => $worn])['status'], '已穿在别的将领身上');
            $this->assertSame(-2, $this->request('general', 'equip', ['gid' => $gid, 'eid' => $blade])['status'], '装配等级不足');
            $this->assertSame(0, (int)DB::table('equipment')->where('id', $blade)->first()['generalid']);

            // 同一装配位置先卸下旧装备
            $this->assertSame(1, $this->request('general', 'equip', ['gid' => $gid, 'eid' => $knife])['status']);
            $res = $this->request('general', 'equip', ['gid' => $gid, 'eid' => $blood]);
            $this->assertSame(1, $res['status']);
            $this->assertSame($blood, $res['info']['eid1']['id']);
            $this->assertSame(0, (int)DB::table('equipment')->where('id', $knife)->first()['generalid']);
            $this->assertContains($knife, array_column($res['equipments'], 'id'));

            // 卸下：-2 将领不存在，-3 装备不在该将领身上，成功后 eid1 = 0
            $this->assertSame(-2, $this->request('general', 'unequip', ['gid' => 999999999, 'eid' => $blood])['status']);
            $this->assertSame(-3, $this->request('general', 'unequip', ['gid' => $gid, 'eid' => $knife])['status']);
            $this->assertSame(-3, $this->request('general', 'unequip', ['gid' => $gid, 'eid' => $worn])['status']);
            $un = $this->request('general', 'unequip', ['gid' => $gid, 'eid' => $blood]);
            $this->assertSame(1, $un['status']);
            $this->assertSame(0, $un['info']['eid1']);
            $this->assertSame(0, (int)DB::table('equipment')->where('id', $blood)->first()['generalid']);

            DB::table('equipment')->where('uid', 990003)->delete();
        });
    }

    public function testPackEquipmentSale(): void
    {
        $this->withFreshPlayer(function (int $uid): void {
            $silver = (int)DB::table('member')->where('uid', $uid)->first()['silver'];
            $gid = (int)$this->request('general', 'index')['list'][1]['id'];
            [$worn] = Equipment::addFromTemplate($uid, 1);
            $this->request('general', 'equip', ['gid' => $gid, 'eid' => $worn]);
            [$armor] = Equipment::addFromTemplate($uid, 2);
            [$chip] = Equipment::addFromTemplate($uid, 20);
            DB::table('equipment')->where('id', $chip)->update(['chip' => 3, 'saleprice' => 1001]);

            $this->assertSame(-1, $this->request('pack', 'sale', ['id' => 999999999])['status']);
            $this->assertSame(-2, $this->request('pack', 'sale', ['id' => $worn])['status']);

            $sale = $this->request('pack', 'sale', ['id' => $armor]);
            $this->assertSame(1, $sale['status']);
            $this->assertSame(100, $sale['silver']);
            $this->assertSame($silver + 100, $sale['all_silver']);
            $raw = DB::table('equipment')->where('id', $armor)->first();
            $this->assertNotNull($raw['deleted_at'], '出售为软删除');
            $this->assertSame(0, (int)$raw['sale_site']);
            $this->assertNotContains($armor, array_column($this->request('pack', 'index')['list'], 'id'));
            $this->assertSame(-1, $this->request('pack', 'sale', ['id' => $armor])['status'], '不能重复出售');

            // 碎片出售：1001 / 10 * 3 = 300.3（旧版不取整），入账 intval
            $chipSale = $this->request('pack', 'sale', ['id' => $chip]);
            $this->assertSame(1, $chipSale['status']);
            $this->assertEqualsWithDelta(300.3, $chipSale['silver'], 0.0001);
            $this->assertSame($silver + 100 + 300, (int)DB::table('member')->where('uid', $uid)->first()['silver']);

            // 强化 10 级以上的装备每日限售 10 件
            [$strong] = Equipment::addFromTemplate($uid, 1);
            DB::table('equipment')->where('id', $strong)->update(['level' => 10]);
            Redis::set('equip_sale_' . date('ymd') . '_' . $uid, 10, 86400);
            $this->assertSame(-3, $this->request('pack', 'sale', ['id' => $strong])['status']);
            Redis::set('equip_sale_' . date('ymd') . '_' . $uid, 9, 86400);
            $this->assertSame(1, $this->request('pack', 'sale', ['id' => $strong])['status']);
            $this->assertSame(10, Redis::get('equip_sale_' . date('ymd') . '_' . $uid));
            $this->assertSame(0, (int)DB::table('member')->where('uid', $uid)->value('gemuse'), '140 级以下出售不卸宝石');

            // 主公 140 级以上：出售前卸下装备上的宝石，卸下个数加到宝石格子占用（旧版 add_gemuse(0) 也加 1）
            DB::table('member')->where('uid', $uid)->update(['level' => 140]);
            [$gemmed, $plain] = Equipment::addFromTemplate($uid, 2, 2);
            \Hanjiang\Model\OwnGem::addGem($uid, 1, 0, 2);
            $gemIds = array_map('intval', array_column(DB::table('own_gem')->where('uid', $uid)->whereNull('deleted_at')->get(), 'id'));
            \Hanjiang\Model\OwnGem::adron($uid, $gemIds[0], $gemmed, 1);
            \Hanjiang\Model\OwnGem::adron($uid, $gemIds[1], $gemmed, 2);
            $this->assertSame(1, $this->request('pack', 'sale', ['id' => $gemmed])['status']);
            $this->assertSame([], \Hanjiang\Model\OwnGem::byEid($uid, $gemmed));
            $this->assertSame(2, (int)DB::table('member')->where('uid', $uid)->value('gemuse'));
            $this->assertSame(1, $this->request('pack', 'sale', ['id' => $plain])['status']);
            $this->assertSame(3, (int)DB::table('member')->where('uid', $uid)->value('gemuse'), '没有宝石也加 1（旧版 add_gemuse 下限 1）');
        });
    }

    public function testStrengthenSystem(): void
    {
        $this->withFreshPlayer(function (int $uid): void {
            $this->assertSame(-1, $this->request('strengthen', 'index')['status'], '没有装备');
            DB::table('member')->where('uid', $uid)->update(['level' => 20, 'silver' => 100000]);
            [$knife] = Equipment::addFromTemplate($uid, 1); // 赤铜刀：猛将武器 白 1，初始 10，售价 220

            $res = $this->request('strengthen', 'index');
            $this->assertSame(1, $res['status']);
            $this->assertSame(['status', 'list', 'info'], array_values(array_diff(array_keys($res), ['message', 'msg'])), '信封外的字段同旧版');
            $this->assertSame(['id', 'gid', 'type', 'level', 'equipid', 'quality', 'hammer', 'hammer_quality'], array_keys($res['list'][1]));
            $info = $res['info'];
            $this->assertSame(1, $info['status']);
            $this->assertSame(['status' => 0, 'cd' => 0], $info['cd']);
            // 下一级规则：ceil(2.2 * 1 * equip_level[1].silver 30) = 66，成功率 100，强化后 10 + 1 * 12 = 22，暴击率 15
            $this->assertSame(['silver' => 66, 'gold' => 0, 'rate' => 100, 'power' => 22, 'baoji' => 15], $info['rule']);

            $start = $this->request('strengthen', 'strengthen_start', ['id' => $knife]);
            $this->assertSame(1, $start['status']);
            $this->assertSame(1, $start['newlevel']);
            $this->assertSame(22, $start['newpower']);
            $this->assertSame(0, $start['baoji']);
            $this->assertSame(0, $start['cd']);
            // 返回下一级规则：ceil(2.2 * 120) = 264，强化后 10 + 2 * 12 = 34
            $this->assertSame(264, $start['silver']);
            $this->assertSame(34, $start['power']);
            $this->assertSame(100000 - 66, $start['all_silver']);
            $row = DB::table('equipment')->where('id', $knife)->first();
            $this->assertSame(1, (int)$row['level']);
            $this->assertSame(22, (int)$row['powerup']);
            // 售价 = 模板 220 + round(2.2 * 30) * 0.6 = 259.6，写入 INT 列四舍五入
            $this->assertSame(260, (int)$row['saleprice']);

            // VIP 5 暴击（当前强化等级 > 0 才可能），ratetype = 1 用元宝补满概率时不掷成功率骰
            DB::table('member')->where('uid', $uid)->update(['vip' => 5]);
            $seed = 1;
            while (true) {
                mt_srand($seed);
                if (mt_rand(1, 100) <= 15) {
                    break;
                }
                $seed++;
            }
            mt_srand($seed);
            $crit = $this->request('strengthen', 'strengthen_start', ['id' => $knife, 'ratetype' => 1]);
            $this->assertSame(1, $crit['status']);
            $this->assertSame(1, $crit['baoji']);
            $this->assertSame(3, $crit['newlevel']);
            $this->assertSame(10 + 3 * 12, (int)DB::table('equipment')->where('id', $knife)->first()['powerup']);

            // 成功率 < 100 的等级（equip_level[11].rate = 98）失败：-6，照扣银币，等级不变
            DB::table('member')->where('uid', $uid)->update(['vip' => 1]);
            DB::table('equipment')->where('id', $knife)->update(['level' => 10]);
            $seed = 1;
            while (true) {
                mt_srand($seed);
                if (mt_rand(1, 100) > 98) {
                    break;
                }
                $seed++;
            }
            $before = (int)DB::table('member')->where('uid', $uid)->first()['silver'];
            mt_srand($seed);
            $fail = $this->request('strengthen', 'strengthen_start', ['id' => $knife]);
            $this->assertSame(-6, $fail['status']);
            $this->assertSame($before - (int)ceil(2.2 * 3630), $fail['all_silver']);
            $this->assertSame(10, (int)DB::table('equipment')->where('id', $knife)->first()['level']);

            // -3 强化等级已达主公等级，-4 银币不足，-2 装备不存在
            DB::table('equipment')->where('id', $knife)->update(['level' => 20]);
            $this->assertSame(-3, $this->request('strengthen', 'strengthen_start', ['id' => $knife])['status']);
            DB::table('equipment')->where('id', $knife)->update(['level' => 5]);
            DB::table('member')->where('uid', $uid)->update(['silver' => 0]);
            $this->assertSame(-4, $this->request('strengthen', 'strengthen_start', ['id' => $knife])['status']);
            $this->assertSame(-2, $this->request('strengthen', 'strengthen_start', ['id' => 999999999])['status']);
            $this->assertSame(-1, $this->request('strengthen', 'strengthen_info', ['id' => 999999999])['status']);

            // 旧版免 CD：clear_cd 总是「不需要清除」
            $this->assertSame(-1, $this->request('strengthen', 'clear_cd', ['type' => 1])['status']);
        });
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
        // 池 id 10 的「张飞」入库为 MasterData General 52（张飞，形象 54）
        $this->assertSame(52, $buy['general']['generalid']);
        $zhangfei = DB::table('general')->where('uid', self::$uid)->where('generalid', 52)->whereNull('deleted_at')->first();
        $this->assertNotNull($zhangfei);
        $this->assertSame('张飞', $zhangfei['name']);
        $this->assertSame(123, (int)$zhangfei['wuli']);
        $this->assertSame(80, (int)$zhangfei['levelupexp']);
        $this->assertArrayHasKey('jade', $buy);

        // 测试商贾会客贸易
        $trade = $this->request('tavern', 'trade', ['s' => 1, 'type' => 0]);
        $this->assertSame(1, $trade['status']);
        $this->assertArrayHasKey('site', $trade);
        $this->assertArrayHasKey('addtype', $trade);
        // 会客记录与宝石在 trader_log，宝石获得记 tavernjade_log；当天已交易不再免费
        $trader = \Hanjiang\Model\TraderLog::get(self::$uid);
        $this->assertNotNull($trader);
        $this->assertSame($trade['number'], $trader['get' . $trade['addtype']]);
        $this->assertSame($trade['site'], $trader['site']);
        $this->assertSame(date('Ymd'), date('Ymd', $trader['dateline']));
        $this->assertSame(0, $this->request('tavern', 'index')['isfree']);
        $log = DB::table('tavernjade_log')->where('uid', self::$uid)->orderBy('id', 'DESC')->first();
        $this->assertSame([1, $trade['addtype'], $trade['addnum'], $trade['number'], 'tavern', 'trade'], [(int)$log['type'], (int)$log['jadetype'], (int)$log['jade'], (int)$log['balance'], $log['class'], $log['method']]);

        // 再次尝试招募张飞，触发唯一性拦截 (status = 4 已拥有该武将)
        $duplicateBuy = $this->request('tavern', 'buy', ['id' => 10]);
        $this->assertSame(4, $duplicateBuy['status']);

        // 新招募的张飞未出征（旧版 add_general goout = 0），不在 general.index 的出征列表里
        $generals = $this->request('general', 'index');
        $this->assertNotContains('张飞', array_column($generals['list'], 'name'));
        $this->assertSame(2, $this->request('general', 'get_info', ['gid' => (int)$zhangfei['id']])['status']);
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

        // 获取张飞的 ID（未出征，不在 general.index 列表里）
        $zfId = (int)DB::table('general')->where('uid', self::$uid)->where('generalid', 52)->whereNull('deleted_at')->value('id');
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
        // 1. 获取主公（初始武将廖化）与张飞的将领 ID
        $mainId = (int)DB::table('general')->where('uid', self::$uid)->where('generalid', General::INITIAL_GENERALID)->whereNull('deleted_at')->value('id');
        $zfId = (int)DB::table('general')->where('uid', self::$uid)->where('generalid', 52)->whereNull('deleted_at')->value('id');
        $this->assertGreaterThan(0, $mainId);
        $this->assertGreaterThan(0, $zfId);

        // 2. 验证防重复上阵拦截
        $duplicateFormation = "{$zfId},{$mainId},{$zfId},0,0,0,0,0,0";
        $conflict = $this->request('matrix', 'update_matrix', ['list' => $duplicateFormation, 'mid' => 1]);
        $this->assertSame(-4, $conflict['status']);

        // 3. 张飞未出征（旧版 add_general goout = 0）不能上阵；出征后布到 4 号位（1 号锥形阵 1 级只开放 2、4 号位），主公在 2 号位
        $formation = "0,{$mainId},0,{$zfId},0,0,0,0,0";
        $this->assertSame(3, $this->request('matrix', 'update_matrix', ['list' => $formation, 'mid' => 1])['status']);
        $this->assertSame(1, $this->request('muster', 'go_battle', ['gid' => $zfId])['status']);
        $setZf = $this->request('matrix', 'update_matrix', ['list' => $formation, 'mid' => 1]);
        $this->assertSame(['status' => 1, 'tip' => 0], array_intersect_key($setZf, ['status' => 0, 'tip' => 0]));
        $this->assertSame($zfId, (int)DB::table('matrix')->where('uid', self::$uid)->where('mid', 1)->value('position4'));

        // 4. 出征名单（general.index）里有两位武将
        $updatedGenerals = $this->request('general', 'index');
        $activeCount = 0;
        foreach ($updatedGenerals['list'] as $g) {
            if ((int)$g['goout'] === 1) {
                $activeCount++;
            }
        }
        $this->assertGreaterThanOrEqual(2, $activeCount, '当前应至少有两位武将处于出征状态');

        // 5. 发起推图战斗，我方按启用的阵法组队
        $battle = $this->request('map', 'action', ['l' => 1, 's' => 1, 'id' => 1]);
        $this->assertSame(1, $battle['status']);
        $this->assertGreaterThanOrEqual(1, $battle['info']['win']);
        $this->assertArrayHasKey('battle', $battle);

        $leftTeam = $battle['battle']['leftteam'];
        $this->assertArrayHasKey(2, $leftTeam, '战场上必须存在 2 号位英雄 (主公)');
        $this->assertArrayHasKey(4, $leftTeam, '战场上必须存在 4 号位英雄 (张飞)');
        $this->assertSame('张飞', $leftTeam[4]['name']);

        $process = $battle['battle']['process'];
        $this->assertNotEmpty($process);
        $leftAttackors = array_column(array_filter($process, fn (array $p): bool => $p['launch'] === 'left'), 'attackor');
        $this->assertNotEmpty($leftAttackors);
        $this->assertSame([], array_diff($leftAttackors, [2, 4]), '我方出手只来自阵法上的 2、4 号位');
        $this->assertSame((string)$zfId, $leftTeam[4]['heroid']);
    }

    public function testChatSystem(): void
    {
        $this->chatReset();
        $s = $this->chatPlayer();
        $r = $this->chatPlayer();
        $this->chatOnline($s);
        $this->chatOnline($r);

        // 50 级以下不能发言（旧版 status 6，msg 为 chat_send_need_level）
        DB::table('member')->where('uid', $s['uid'])->update(['level' => 49]);
        $res = $this->chatSend($s, '你好');
        $this->assertSame([6, '您必须达到等级50级才能在世界频道发言！'], [$res['status'], $res['msg']]);
        DB::table('member')->where('uid', $s['uid'])->update(['level' => 50]);
        // 内容为空 / 超过 200 字节（htmlspecialchars 之后按字节算）
        $this->assertSame([2, '消息不能为空！'], [$this->chatSend($s, '')['status'], $this->chatSend($s, '')['msg']]);
        $this->assertSame([3, '消息太长！'], [$this->chatSend($s, str_repeat('汉', 67))['status'], $this->chatSend($s, str_repeat('汉', 67))['msg']]);

        // 世界频道：进公共队列与每个在线玩家的私人队列；发信人的 list 即其 read 结果（含自己这条）
        $res = $this->chatSend($s, 'a<b>&');
        $this->assertSame(1, $res['status']);
        $nickname = (string)DB::table('member')->where('uid', $s['uid'])->value('nickname');
        $own = $res['list'][0];
        $this->assertSame(['uid' => $s['uid'], 'type' => 0, 'nickname' => $nickname, 'message' => 'a&lt;b&gt;&amp;', 'id' => 0, 'rank' => 0, 'tree' => 0, 'system' => 0], array_diff_key($own, ['time' => 1]));
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $own['time']);
        $this->assertSame([[0, 'a&lt;b&gt;&amp;', 0, $s['uid']]], $this->chatMessages($r));
        $this->assertSame([], $this->chatMessages($r), 'read 弹出后队列为空');
        $index = $this->request('chat', 'index', $r['auth']);
        $this->assertSame([1, 'a&lt;b&gt;&amp;'], [$index['status'], end($index['list'])['message']]);

        // 3 秒内再发：发送太快
        $this->assertSame([4, '发送太快！'], [$this->chatSend($s, '再来', [], false)['status'], $this->chatSend($s, '再来', [], false)['msg']]);
        // 与上次内容相同（去掉符号后比较）：仅自己可见
        $res = $this->chatSend($s, 'a<b>&');
        $this->assertSame([1, 0], [$res['status'], $res['list'][0]['type']]);
        $this->assertSame([], $this->chatMessages($r));
        // 含聊天屏蔽字（大小写不敏感、先去掉符号）：仅自己可见
        $res = $this->chatSend($s, '来淘-宝买');
        $this->assertSame(1, count($res['list']));
        $this->assertSame([], $this->chatMessages($r));
        $this->assertSame([], $this->chatMessages($s));
        // 带双引号的消息正常发出（旧版 &quot; 含屏蔽字 q 被当违规，已修正）；原文含 q 仍按屏蔽处理
        $this->chatSend($s, '他说"你好"');
        $this->assertSame([[0, '他说&quot;你好&quot;', 0, $s['uid']]], $this->chatMessages($r));
        $res = $this->chatSend($s, '"加我qq"');
        $this->assertSame(1, count($res['list']));
        $this->assertSame([], $this->chatMessages($r));
        // 后台屏蔽词替换成 ***
        Redis::set('badword', ['坏蛋'], 600);
        $this->chatSend($s, '你这个坏蛋');
        $this->assertSame([[0, '你这个***', 0, $s['uid']]], $this->chatMessages($r));
        Redis::del('badword');

        // 禁言（member.status 1 / 2）：世界频道只发给自己；禁言到期自动解除
        DB::table('member')->where('uid', $s['uid'])->update(['status' => 1, 'statustime' => time() + 600]);
        $res = $this->chatSend($s, '禁言中');
        $this->assertSame([1, '禁言中'], [count($res['list']), $res['list'][0]['message']]);
        $this->assertSame([], $this->chatMessages($r));
        DB::table('member')->where('uid', $s['uid'])->update(['status' => 1, 'statustime' => time() - 1]);
        $this->chatSend($s, '解禁了');
        $this->assertSame([[0, '解禁了', 0, $s['uid']]], $this->chatMessages($r));
        $this->assertSame([0, 0], array_map(intval(...), array_values(DB::table('member')->select('status', 'statustime')->where('uid', $s['uid'])->first())));

        // 私聊：只进对方私人队列（type 1），自己的 list 不含这条
        $x = $this->chatPlayer();
        $this->chatOnline($x);
        $res = $this->chatSend($s, '悄悄话', ['uid' => $r['uid']]);
        $this->assertSame([1, []], [$res['status'], $res['list']]);
        $this->assertSame([[1, '悄悄话', 0, $s['uid']]], $this->chatMessages($r));
        $this->assertSame([], $this->chatMessages($x));
        // 私聊对象不在线：消息丢弃（同旧版）
        $offline = $this->chatPlayer();
        $this->chatSend($s, '不在线', ['uid' => $offline['uid']]);
        $this->assertSame([], $this->chatMessages($offline));

        // 国家频道：未加入国家 status 5；同国成员与国家队列可见，别国不可见
        $res = $this->chatSend($s, '国家', ['country' => 1]);
        $this->assertSame([5, '未加入国家，无法发送！'], [$res['status'], $res['msg']]);
        $this->chatJoinCountry($s, 9001);
        $this->chatJoinCountry($r, 9001);
        $this->chatJoinCountry($x, 9002);
        $res = $this->chatSend($s, '本国集合', ['country' => 1]);
        $this->assertSame([6, '本国集合'], [$res['list'][0]['type'], $res['list'][0]['message']]);
        $this->assertSame([[6, '本国集合', 0, $s['uid']]], $this->chatMessages($r));
        $this->assertSame([], $this->chatMessages($x));
        $this->assertSame('本国集合', $this->request('chat', 'index', $r['auth'])['list'][0]['message'], '国家队列排在公共队列前面');
        $this->assertNotContains('本国集合', array_column($this->request('chat', 'index', $x['auth'])['list'], 'message'));

        // 恢复后第一次读取（first = 1）：不取消息
        $this->chatSend($s, '第一次');
        $this->assertSame([1, []], [$this->request('chat', 'read', $r['auth'] + ['first' => 1])['status'], $this->request('chat', 'read', $r['auth'] + ['first' => 1])['list']]);
        $this->assertSame(['第一次'], array_column($this->chatMessages($r), 1));
        $this->chatMessages($x);

        // 按昵称查 uid
        $this->assertSame(['status' => 1, 'uid' => $s['uid']], array_intersect_key($this->request('chat', 'getuid', $r['auth'] + ['name' => $nickname]), ['status' => 1, 'uid' => 1]));
        $this->assertSame(2, $this->request('chat', 'getuid', $r['auth'] + ['name' => '没有这个人'])['status']);

        // 屏蔽某人 24 小时：返回去掉此人消息的 index 列表，之后 read 也过滤；重复屏蔽 -1
        $res = $this->request('chat', 'ban_uid', $r['auth'] + ['touid' => $s['uid']]);
        $this->assertSame(1, $res['status']);
        $this->assertNotContains($s['uid'], array_column(array_filter($res['list'], static fn(array $m): bool => $m['type'] !== 6), 'uid'));
        $this->assertSame(-1, $this->request('chat', 'ban_uid', $r['auth'] + ['touid' => $s['uid']])['status']);
        $this->chatSend($s, '被屏蔽了');
        $this->assertSame([], $this->chatMessages($r));
        $this->assertSame(['被屏蔽了'], array_column($this->chatMessages($x), 1));

        // 公共队列最多 30 条、国家队列最多 10 条
        for ($i = 0; $i < 35; $i++) {
            \Hanjiang\Model\Chat::sendToAll('系统' . $i, \Hanjiang\Model\Chat::TYPE_SYSTEM);
            \Hanjiang\Model\Chat::sendToCountry('国家' . $i, [], \Hanjiang\Model\Chat::TYPE_COUNTRY, 1, 9001);
        }
        $list = $this->request('chat', 'index', $r['auth'])['list'];
        $this->assertCount(40, $list);
        $this->assertSame(['国家25', '国家34', '系统5', '系统34'], [$list[0]['message'], $list[9]['message'], $list[10]['message'], $list[39]['message']]);
        // 喇叭（type 2）不进公共队列，只进在线玩家的私人队列
        \Hanjiang\Model\Chat::sendToHorn('喇叭');
        $this->assertNotContains('喇叭', array_column($this->request('chat', 'index', $r['auth'])['list'], 'message'));
        $this->assertContains([2, '喇叭', 0, 0], $this->chatMessages($x));

        // 探活接口 c=index&m=index
        $this->assertSame(1, $this->request('index', 'index')['status']);
    }

    /**
     * 系统消息模板（get_message_str）：参数顺序、默认发信人、各战报类型的 {类型,[查看战报],id}
     */
    public function testChatMessageTemplates(): void
    {
        $sender = ['uid' => 7, 'nickname' => '张三', 'country' => 0, 'rank' => 0];
        $to = ['uid' => 8, 'nickname' => '李四'];
        $C = \Hanjiang\Model\Chat::class;
        $this->assertSame('{1,张三 ,7} 击败了 {1,李四 ,8} 升至演武榜 {10,第1名}  {2,[查看战报],9}', $C::getMessageStr(2, ['to' => $to, 'rank' => 1, 'id' => 9], $sender));
        $this->assertSame('{1,王五 ,3} 将“刑天”最后击杀，奖励额外增加50%', $C::getMessageStr(12, ['from' => ['uid' => 3, 'nickname' => '王五']], $sender));
        $this->assertSame('{1,王五 ,3} 将“蚩尤”最后击杀，奖励额外增加50%', $C::getMessageStr(17, ['from' => ['uid' => 3, 'nickname' => '王五']], null));
        $this->assertSame('恭喜【{1,张三 ,7}】在西域马场选中名马-<em class="q6">赤兔</em>，真乃当世雄主', $C::getMessageStr(28, ['rebate' => '赤兔', 'mount' => 6], $sender));
        $this->assertSame('{9,魏}击败了{9,蜀}，攻占【{9,洛阳}】{14,[查看战报],3}', $C::getMessageStr(20, ['siege1' => '魏', 'siege2' => '蜀', 'siege3' => '洛阳', 'id' => 3], null));
        $this->assertSame('{9,蜀}被{9,魏}击败了，【{9,洛阳}】被攻占{14,[查看战报],3}', $C::getMessageStr(19, ['siege1' => '蜀', 'siege2' => '魏', 'siege3' => '洛阳', 'id' => 3], null));
        $this->assertSame('{9,魏}击败了{9,蜀}，成功守住【{9,洛阳}】{14,[查看战报],3}', $C::getMessageStr(18, ['siege1' => '魏', 'siege2' => '蜀', 'siege3' => '洛阳', 'id' => 3], null));
        $this->assertSame('{9,魏}被{9,蜀}击败了，【{9,洛阳}】攻占失败。{14,[查看战报],3}', $C::getMessageStr(21, ['siege1' => '魏', 'siege2' => '蜀', 'siege3' => '洛阳', 'id' => 3], null));
        $this->assertSame('恭喜【{1,张三 ,7}】在过关斩将中获得七星武将-{9,关羽}', $C::getMessageStr(45, ['star_general' => '七', 'general' => '关羽'], $sender));
        $this->assertSame('恭喜【{1,张三 ,7}】在过关斩将中获得八星神将-{9,吕布}', $C::getMessageStr(46, ['star_general' => '八', 'general' => '吕布'], $sender));
        $this->assertSame('恭喜【{1,张三 ,7}】在天命转盘获得九星武将-{9,赵云}', $C::getMessageStr(35, ['star_general' => '九', 'general' => '赵云'], $sender));
        $this->assertSame('恭喜【{1,张三 ,7}】在首充双倍返利中获得装备-<em class="q5">青釭剑</em>', $C::getMessageStr(33, ['springequipment' => '<em class="q5">青釭剑</em>'], $sender));
        $this->assertSame('{1,张三 ,7} 在铜雀台中大展雄风，战胜了{1,李四 ,8} {49,[查看战报],4}', $C::getMessageStr(49, ['to' => $to, 'id' => 4, 'win' => 1], $sender));
        $this->assertSame('{1,张三 ,7} 和 {1,李四 ,8} 在铜雀台中战成 打成平局 {49,[查看战报],4}', $C::getMessageStr(51, ['to' => $to, 'id' => 4, 'win' => '打成平局'], $sender));
        $this->assertSame('{1,张三 ,7} 在矿区抢夺 {1,李四 ,8} 的矿中 失败  {2,[查看战报],4}', $C::getMessageStr(27, ['to' => $to, 'id' => 4, 'win' => '失败'], $sender));
        $this->assertSame('恭喜 {1,张三 ,7} 在幸运大转盘抽到了 {5,100元宝}', $C::getMessageStr(6, ['gold' => 100], $sender));
        $this->assertSame('玩家 {1,张三 ,7} 达成 x3 幸运通商，获得银币x3 。', $C::getMessageStr(13, ['times' => 'x3'], $sender));
        // 计划任务里没有发信人
        $this->assertSame('恭喜 {1, ,} 成功招募到武将 {9,张飞}', $C::getMessageStr(10, ['general' => '张飞'], null));
        // 未定义 / 预留的类型
        $this->assertSame('', $C::getMessageStr(1, [], $sender));
        $this->assertSame('', $C::getMessageStr(99, [], $sender));
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

    /**
     * 将魂星路玩家：主公 51 级（旧版 level > 50 开放）
     *
     * @return array{uid: int, auth: array<string, mixed>}
     */
    /**
     * c=tower 请求，去掉响应信封补的 message / msg（旧版 api_return 只输出控制器给的字段）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function towerRequest(string $method, array $params = []): array
    {
        $res = $this->request('tower', $method, $params);
        unset($res['message'], $res['msg']);
        return $res;
    }

    private function towerPlayer(int $level = 51): array
    {
        $p = $this->newGuestPlayer();
        DB::table('member')->where('uid', $p['uid'])->update(['level' => $level]);
        return $p;
    }

    /**
     * 阵法里的武将提到远超守军的等级：旧版战斗有随机（闪避 / 暴击），这里让胜负不依赖随机数
     */
    private function towerOverpower(int $uid): void
    {
        DB::table('general')->where('uid', $uid)->update(['level' => 20000]);
    }

    /**
     * @param array<string, int> $data
     */
    private function towerMap(int $uid, array $data): void
    {
        DB::table('soul_open_map')->where('uid', $uid)->update($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function towerActivity(int $uid): array
    {
        return DB::table('member_activity')->where('uid', $uid)->first();
    }

    private function towerMember(int $uid, string $column): int
    {
        return (int)DB::table('member')->where('uid', $uid)->value($column);
    }

    public function testTowerSceneAndMissionLists(): void
    {
        // 旧版 level > 50 开放
        $low = $this->towerPlayer(50);
        foreach (['get_scene_list', 'get_mission_list', 'get_mission_info', 'get_mopup_price', 'pk', 'get_price', 'buy', 'reward_info', 'get_reward'] as $m) {
            $this->assertSame(-1, $this->towerRequest($m, $low['auth'] + ['s' => 1, 'id' => 1, 'type' => 3])['status'], $m);
        }
        $this->assertNull(DB::table('soul_open_map')->where('uid', $low['uid'])->first());
        // 新版自定的接口已删除（前端只调用旧版接口）
        foreach (['index', 'action', 'sweep', 'getTowerProgress'] as $m) {
            $this->assertFalse(method_exists(\Hanjiang\Handler\Campaign\TowerHandler::class, $m), $m);
        }

        $p = $this->towerPlayer();
        $uid = $p['uid'];
        $scene = $this->towerRequest('get_scene_list', $p['auth']);
        $this->assertSame(1, $scene['status']);
        $this->assertCount(7, $scene['scene']);
        $this->assertSame(['id' => 1, 'name' => '北斗七星', 'openstatus' => 1, 'killed' => 0], $scene['scene'][0]);
        $this->assertSame(['id' => 2, 'name' => '紫薇星域', 'openstatus' => 0, 'killed' => 0], $scene['scene'][1]);
        // get_open_map 首次建档；get_scene_list 不建活动记录（update_activity_info 没有记录时不插入）
        $map = DB::table('soul_open_map')->where('uid', $uid)->first();
        $this->assertSame([1, 0, 0], [(int)$map['level'], (int)$map['stage'], (int)$map['mission']]);
        $this->assertNull(DB::table('member_activity')->where('uid', $uid)->first());

        $list = $this->towerRequest('get_mission_list', $p['auth'] + ['s' => 1]);
        $this->assertSame(1, $list['status']);
        $this->assertSame(['status', 'now', 'list', 'times', 'get_reward'], array_keys($list));
        $this->assertCount(25, $list['list']);
        $this->assertSame(['id', 'level', 'name', 'type', 'image', 'openstatus', 'killed', 'firstkill'], array_keys($list['list'][0]));
        $m1 = \Hanjiang\MasterData\SoulMission::ROWS[1];
        $this->assertSame([1, $m1['level'], $m1['name'], $m1['type'], $m1['image'], 1, 0, 1], array_values($list['list'][0]));
        $this->assertSame([0, 0, 1], [$list['list'][1]['openstatus'], $list['list'][1]['killed'], $list['list'][1]['firstkill']]);
        $this->assertSame(['id' => 1, 'soul' => $m1['soul'], 'first_soul_num' => $m1['first_soul_num'], 'firstkill' => 1, 'hero_soul' => []], $list['now']);
        // 首次读活动信息按旧版初始值建档：免费次数 12
        $this->assertSame(12, $list['times']);
        $this->assertSame(0, $list['get_reward']);
        $act = $this->towerActivity($uid);
        $this->assertSame([1, 1, 0, 12, 0, 0], [(int)$act['towerlevel'], (int)$act['towerstage'], (int)$act['towermission'], (int)$act['freetower'], (int)$act['towertimes'], (int)$act['souldate']]);

        // 场景 2 未开启；场景 0 没有关卡
        $this->assertSame(-4, $this->towerRequest('get_mission_list', $p['auth'] + ['s' => 2])['status']);
        $this->assertSame(-5, $this->towerRequest('get_mission_list', $p['auth'] + ['s' => 0])['status']);

        $info = $this->towerRequest('get_mission_info', $p['auth'] + ['id' => 1]);
        $this->assertSame(['status' => 1, 'mission' => ['id' => 1, 'soul' => $m1['soul'], 'first_soul_num' => $m1['first_soul_num'], 'firstkill' => 1, 'hero_soul' => []], 'mop_up' => 0], $info);
        $this->assertSame(1, (int)$this->towerActivity($uid)['towermission']);
        $this->assertSame(-5, $this->towerRequest('get_mission_info', $p['auth'] + ['id' => 2])['status'], '关卡未开启');
        $this->assertSame(-4, $this->towerRequest('get_mission_info', $p['auth'] + ['id' => 26])['status'], '场景未开启');
        $this->assertSame(-2, $this->towerRequest('get_mission_info', $p['auth'] + ['id' => 999])['status']);

        // 第 3 关掉落将魂（soulid 1、getrate 100）：模板 + 图片 type.quality + 0 级属性
        $this->towerMap($uid, ['mission' => 2]);
        $soul = \Hanjiang\MasterData\Soul::ROWS[1];
        $heroSoul = $soul + ['image' => (int)($soul['type'] . $soul['quality']), 'addition' => (int)\Hanjiang\MasterData\SoulType::ROWS[$soul['type']]['lv0']];
        $info = $this->towerRequest('get_mission_info', $p['auth'] + ['id' => 3]);
        $this->assertSame($heroSoul, $info['mission']['hero_soul']);
        $list = $this->towerRequest('get_mission_list', $p['auth'] + ['s' => 1]);
        $this->assertSame(3, $list['now']['id'], '当前关卡为已过关卡 + 1');
        $this->assertSame($heroSoul, $list['now']['hero_soul']);
        $this->assertSame([1, 1, 1, 0], [$list['list'][1]['killed'], $list['list'][2]['openstatus'], $list['list'][2]['firstkill'], $list['list'][2]['killed']]);
    }

    public function testTowerPkLoseAndGuards(): void
    {
        $p = $this->towerPlayer();
        $uid = $p['uid'];
        $nickname = (string)DB::table('member')->where('uid', $uid)->value('nickname');
        $soulBefore = $this->towerMember($uid, 'soul');

        // 阵法里没有武将：旧版 battle_mission 按空队伍继续战斗，直接大败（assess -3），不消耗随机数
        $this->emptyFormation($uid);
        $res = $this->towerRequest('pk', $p['auth'] + ['id' => 1]);
        $this->assertSame(1, $res['status']);
        $this->assertSame(['status', 'info', 'clear', 'battle'], array_keys($res));
        $this->assertSame(['id' => 1, 'background' => 0, 'report' => 0, 'times' => 11, 'win' => -3, 'reward_soul' => 0, 'hero_soul' => [], 'soul' => $soulBefore], $res['info']);
        $this->assertSame(0, $res['clear']);
        $this->assertSame(['leftteam', 'rightteam', 'process', 'assess', 'leftresult', 'rightresult', 'leftbuff', 'rightbuff', 'left_role', 'right_role'], array_keys($res['battle']));
        $this->assertSame([], $res['battle']['leftteam']);
        $this->assertSame([], $res['battle']['process']);
        $this->assertSame(-3, $res['battle']['assess']);
        // 守军取 soul_mission 的 position1~9 → soul_monster
        $this->assertSame(['1', '3', '5', '7', '9'], array_map('strval', array_keys($res['battle']['rightteam'])));
        $this->assertSame((string)\Hanjiang\MasterData\SoulMission::ROWS[1]['position1'], $res['battle']['rightteam']['1']['heroid']);
        $this->assertSame(['name' => $nickname, 'level' => 51], $res['battle']['left_role']);
        $this->assertSame(['name' => '北斗力士', 'level' => 51], $res['battle']['right_role']);
        $this->assertSame(11, (int)$this->towerActivity($uid)['freetower'], '战败也扣 1 次（先用免费次数）');
        $this->assertSame(0, (int)DB::table('soul_open_map')->where('uid', $uid)->value('mission'));
        $this->assertSame(0, DB::table('soul_log')->where('uid', $uid)->count());

        // 没有阵法：旧版 battle_mission 返回 -1 → pk -7，不扣次数
        DB::table('member_field')->where('uid', $uid)->update(['matrixid' => 0]);
        $this->assertSame(-7, $this->towerRequest('pk', $p['auth'] + ['id' => 1])['status']);
        $this->assertSame(11, (int)$this->towerActivity($uid)['freetower']);
        DB::table('member_field')->where('uid', $uid)->update(['matrixid' => 1]);

        $this->assertSame(-6, $this->towerRequest('pk', $p['auth'] + ['id' => 2])['status'], '关卡未开启');
        $this->assertSame(-5, $this->towerRequest('pk', $p['auth'] + ['id' => 26])['status'], '场景未开启');
        $this->assertSame(-3, $this->towerRequest('pk', $p['auth'] + ['id' => 999])['status']);

        // 免费次数用完后扣购买次数；都用完返回 3
        DB::table('member_activity')->where('uid', $uid)->update(['freetower' => 0, 'towertimes' => 1]);
        $res = $this->towerRequest('pk', $p['auth'] + ['id' => 1]);
        $this->assertSame(0, $res['info']['times']);
        $this->assertSame([0, 0], [(int)$this->towerActivity($uid)['freetower'], (int)$this->towerActivity($uid)['towertimes']]);
        $this->assertSame(3, $this->towerRequest('pk', $p['auth'] + ['id' => 1])['status']);
    }

    public function testTowerPkWinFirstKillReportAndClear(): void
    {
        DB::execute('DELETE FROM `zc_sanguo_soul_log`');
        DB::execute('DELETE FROM `zc_sanguo_soul_report`');
        $p = $this->towerPlayer();
        $uid = $p['uid'];
        $nickname = (string)DB::table('member')->where('uid', $uid)->value('nickname');
        $this->towerOverpower($uid);
        $m1 = \Hanjiang\MasterData\SoulMission::ROWS[1];
        $soulBefore = $this->towerMember($uid, 'soul');

        // 真实战斗：首杀
        $res = $this->towerRequest('pk', $p['auth'] + ['id' => 1]);
        $this->assertSame(1, $res['status']);
        $battle = $res['battle'];
        $this->assertNotEmpty($battle['leftteam']);
        $this->assertNotEmpty($battle['rightteam']);
        $this->assertNotEmpty($battle['process']);
        $this->assertGreaterThanOrEqual(1, $battle['assess']);
        $this->assertSame(['heroid', 'name', 'herotype', 'level', 'allblood', 'power', 'image', 'star', 'talent', 'skill', 'quality'], array_keys(reset($battle['rightteam'])));
        $this->assertSame(['mingzhong', 'jingzhun', 'kangyun', 'tree', 'kcrit', 'mount'], array_keys($battle['leftbuff']));
        $info = $res['info'];
        $this->assertSame(['first_soul_num', 'firstkill', 'id', 'background', 'report', 'times', 'win', 'reward_soul', 'hero_soul', 'soul'], array_keys($info));
        $this->assertSame([$m1['first_soul_num'], 1, 1, 11, $battle['assess'], $m1['soul'], []], [$info['first_soul_num'], $info['firstkill'], $info['id'], $info['times'], $info['win'], $info['reward_soul'], $info['hero_soul']]);
        // 首杀只发首杀原魂
        $this->assertSame($soulBefore + $m1['first_soul_num'], $info['soul']);
        $this->assertSame($info['soul'], $this->towerMember($uid, 'soul'));
        $this->assertSame(0, $res['clear']);
        $this->assertSame(1, (int)DB::table('soul_open_map')->where('uid', $uid)->value('mission'));
        $log = DB::table('soul_log')->where('uid', $uid)->first();
        $this->assertSame($info['report'], (int)$log['id'], '战报 id 即首杀记录 id');
        $this->assertSame([$nickname, 1, 1, 1, 51, 0, 1], [$log['nickname'], (int)$log['missionlevel'], (int)$log['missionstage'], (int)$log['missionid'], (int)$log['level'], (int)$log['tree'], (int)$log['first_reward']]);

        // 战报回放：与 pk 返回的 battle 相同
        $report = $this->towerRequest('get_report_info', $p['auth'] + ['id' => $info['report']]);
        $this->assertSame(1, $report['status']);
        $this->assertEquals($battle, $report['battle']);
        $this->assertSame(['win' => $battle['assess'], 'background' => 0], $report['info']);
        $this->assertSame(['status' => 2, 'battle' => [], 'info' => []], $this->towerRequest('get_report_info', $p['auth'] + ['id' => 999999]));
        // 攻略列表：最近 7 条首杀记录，按记录 id 索引
        $list = $this->towerRequest('get_report_list', $p['auth'] + ['id' => 1]);
        $this->assertSame(1, $list['status']);
        $this->assertSame([(string)$info['report'] => ['id' => $info['report'], 'uid' => $uid, 'level' => 51, 'nickname' => $nickname, 'tree' => 0]], $list['list']);
        $this->assertSame(['status' => 2, 'list' => []], $this->towerRequest('get_report_list', $p['auth'] + ['id' => 175]));
        // 已首杀的关卡在列表里 firstkill 为 0
        $mlist = $this->towerRequest('get_mission_list', $p['auth'] + ['s' => 1]);
        $this->assertSame([1, 0], [$mlist['list'][0]['killed'], $mlist['list'][0]['firstkill']]);

        // 再打一次：普通原魂，不再记战报
        $soulBefore = $this->towerMember($uid, 'soul');
        $res = $this->towerRequest('pk', $p['auth'] + ['id' => 1]);
        $this->assertGreaterThanOrEqual(1, $res['info']['win']);
        $this->assertArrayNotHasKey('firstkill', $res['info']);
        $this->assertSame([0, $m1['soul'], $soulBefore + $m1['soul']], [$res['info']['report'], $res['info']['reward_soul'], $res['info']['soul']]);
        $this->assertSame(1, DB::table('soul_log')->where('uid', $uid)->count());

        // 打败场景最后一关：clear 1，开启下一场景
        $this->towerMap($uid, ['mission' => 24]);
        $res = $this->towerRequest('pk', $p['auth'] + ['id' => 25]);
        $this->assertGreaterThanOrEqual(1, $res['info']['win']);
        $this->assertSame(1, $res['clear']);
        $map = DB::table('soul_open_map')->where('uid', $uid)->first();
        $this->assertSame([1, 25], [(int)$map['stage'], (int)$map['mission']]);
        $scene = $this->towerRequest('get_scene_list', $p['auth']);
        $this->assertSame([1, 1], [$scene['scene'][0]['openstatus'], $scene['scene'][0]['killed']]);
        $this->assertSame([1, 0], [$scene['scene'][1]['openstatus'], $scene['scene'][1]['killed']]);
        $this->assertSame(1, $this->towerRequest('get_mission_list', $p['auth'] + ['s' => 2])['status']);

        // 旧版老玩家：有过关记录但未领首杀奖励（first_reward 0），胜利时补发首杀并标记
        DB::table('soul_log')->insert(['uid' => $uid, 'nickname' => $nickname, 'missionlevel' => 1, 'missionstage' => 1, 'missionid' => 2, 'level' => 51, 'first_reward' => 0]);
        $soulBefore = $this->towerMember($uid, 'soul');
        $res = $this->towerRequest('pk', $p['auth'] + ['id' => 2]);
        $this->assertSame(1, $res['info']['firstkill']);
        $this->assertSame(0, $res['info']['report']);
        $this->assertSame($soulBefore + \Hanjiang\MasterData\SoulMission::ROWS[2]['first_soul_num'], $res['info']['soul']);
        $this->assertSame(1, (int)DB::table('soul_log')->where('uid', $uid)->where('missionid', 2)->value('first_reward'));
    }

    public function testTowerMopUpAndPrices(): void
    {
        $p = $this->towerPlayer();
        $uid = $p['uid'];
        $act = static fn (int $n): int => (int)\Hanjiang\MasterData\Price::ROWS[min(50, $n)]['act'];
        $sum = static function (int $start, int $times) use ($act): int {
            $total = 0;
            for ($i = 1; $i <= $times; $i++) {
                $total += $act($start + $i);
            }
            return $total;
        };
        $this->towerRequest('get_mission_list', $p['auth'] + ['s' => 1]);
        $this->towerMap($uid, ['mission' => 3]);
        foreach ([1, 3] as $mid) {
            DB::table('soul_log')->insert(['uid' => $uid, 'missionlevel' => 1, 'missionstage' => 1, 'missionid' => $mid, 'level' => 51, 'first_reward' => 1]);
        }

        // 已首杀的已过关卡可以扫荡；get_mission_info 按「今日购买次数 + 1」起算价格
        $info = $this->towerRequest('get_mission_info', $p['auth'] + ['id' => 1]);
        $this->assertSame(1, $info['mop_up']);
        $this->assertSame([0, 0, $sum(1, 38), $sum(1, 88)], [$info['mission']['five_sub'], $info['mission']['ten_sub'], $info['mission']['fifty_sub'], $info['mission']['hundred_sub']]);
        // 已过关但未首杀（没有记录）不显示扫荡
        $this->assertSame(0, $this->towerRequest('get_mission_info', $p['auth'] + ['id' => 2])['mop_up']);

        $price = $this->towerRequest('get_mopup_price', $p['auth'] + ['id' => 1]);
        $this->assertSame(['status' => 1, 'info' => ['five_sub' => 0, 'ten_sub' => 0, 'fifty_sub' => $sum(0, 38), 'hundred_sub' => $sum(0, 88)]], $price);
        $this->assertSame(-5, $this->towerRequest('get_mopup_price', $p['auth'] + ['id' => 4])['status'], '未击败的关卡无法扫荡');
        $this->assertSame(-2, $this->towerRequest('get_mopup_price', $p['auth'] + ['id' => 999])['status']);

        $this->assertSame(-7, $this->towerRequest('mop_up', $p['auth'] + ['id' => 1, 'times' => 7])['status']);
        $this->assertSame(-6, $this->towerRequest('mop_up', $p['auth'] + ['id' => 4, 'times' => 5])['status']);

        // 免费次数够：不花元宝
        $gold = $this->towerMember($uid, 'gold');
        $soul = $this->towerMember($uid, 'soul');
        $m1 = \Hanjiang\MasterData\SoulMission::ROWS[1];
        $res = $this->towerRequest('mop_up', $p['auth'] + ['id' => 1, 'times' => 10]);
        $this->assertSame(1, $res['status']);
        $this->assertSame(['status', 'info', 'times', 'gold'], array_keys($res));
        $this->assertSame(['five_sub' => $sum(0, 3), 'ten_sub' => $sum(0, 8), 'fifty_sub' => $sum(0, 48), 'hundred_sub' => $sum(0, 98), 'soul' => $m1['soul'] * 10, 'hero_soul' => []], $res['info']);
        $this->assertSame([2, $gold], [$res['times'], $res['gold']]);
        $this->assertSame($soul + $m1['soul'] * 10, $this->towerMember($uid, 'soul'));
        $this->assertSame([2, 0], [(int)$this->towerActivity($uid)['freetower'], (int)$this->towerActivity($uid)['towertimes']]);

        // 次数不足：差额按今日购买次数逐次计价，计入 soultimes
        $res = $this->towerRequest('mop_up', $p['auth'] + ['id' => 1, 'times' => 5]);
        $this->assertSame(1, $res['status']);
        $this->assertSame([0, $gold - $sum(0, 3)], [$res['times'], $res['gold']]);
        $this->assertSame($sum(3, 5), $res['info']['five_sub']);
        $this->assertSame(3, (int)DB::table('member_field')->where('uid', $uid)->value('soultimes'));
        $this->assertSame([0, 0], [(int)$this->towerActivity($uid)['freetower'], (int)$this->towerActivity($uid)['towertimes']]);

        // 元宝不足
        DB::table('member')->where('uid', $uid)->update(['gold' => 0]);
        $this->assertSame(3, $this->towerRequest('mop_up', $p['auth'] + ['id' => 1, 'times' => 5])['status']);
        $this->assertSame(3, (int)DB::table('member_field')->where('uid', $uid)->value('soultimes'));

        // 掉落将魂的关卡：掉落的将魂发进 own_soul（add_hero_soul），包裹占用加个数
        DB::table('member_activity')->where('uid', $uid)->update(['freetower' => 5]);
        $mission = \Hanjiang\MasterData\SoulMission::ROWS[3];
        $this->assertGreaterThan(0, $mission['soulid']);
        $res = $this->towerRequest('mop_up', $p['auth'] + ['id' => 3, 'times' => 5]);
        $this->assertSame(1, $res['status']);
        $soul = \Hanjiang\MasterData\Soul::ROWS[$mission['soulid']];
        $num = $res['info']['hero_soul']['num'] ?? 0;
        if ($num > 0) {
            $this->assertSame(['name' => $soul['name'], 'quality' => $soul['quality'], 'level' => 0, 'num' => $num], $res['info']['hero_soul']);
        } else {
            $this->assertSame([], $res['info']['hero_soul']);
        }
        $this->assertSame($num, DB::table('own_soul')->where('uid', $uid)->whereNull('deleted_at')->count());
        $this->assertSame($num, (int)DB::table('member')->where('uid', $uid)->value('souluse'));

        // 旧版老玩家未领首杀的关卡不能扫荡
        DB::table('soul_log')->where('uid', $uid)->where('missionid', 3)->update(['first_reward' => 0]);
        $this->assertSame(-13, $this->towerRequest('mop_up', $p['auth'] + ['id' => 3, 'times' => 5])['status']);
        DB::table('soul_log')->where('uid', $uid)->where('missionid', 3)->update(['first_reward' => 1]);

        // 将魂包裹满（soulpack <= souluse，旧版 _isfull）：掉落将魂的关卡返回 2，不掉将魂的关卡照常
        DB::table('member')->where('uid', $uid)->update(['soulpack' => 4, 'souluse' => 4]);
        DB::table('member_activity')->where('uid', $uid)->update(['freetower' => 5]);
        $this->assertSame(2, $this->towerRequest('mop_up', $p['auth'] + ['id' => 3, 'times' => 5])['status']);
        $this->assertSame(2, $this->towerRequest('pk', $p['auth'] + ['id' => 3])['status']);
    }

    public function testTowerBuyTimes(): void
    {
        $p = $this->towerPlayer();
        $uid = $p['uid'];
        $price = \Hanjiang\MasterData\Price::ROWS;
        $this->assertSame(['status' => 1, 'price' => (int)$price[1]['act']], $this->towerRequest('get_price', $p['auth']));

        DB::table('member')->where('uid', $uid)->update(['gold' => 1000]);
        $res = $this->towerRequest('buy', $p['auth']);
        $this->assertSame(['status' => 1, 'times' => 13, 'gold' => 1000 - (int)$price[1]['act'], 'next' => (int)$price[2]['act']], $res);
        $this->assertSame([12, 1], [(int)$this->towerActivity($uid)['freetower'], (int)$this->towerActivity($uid)['towertimes']]);
        $this->assertSame(1, (int)DB::table('member_field')->where('uid', $uid)->value('soultimes'));
        $this->assertSame((int)$price[2]['act'], $this->towerRequest('get_price', $p['auth'])['price']);

        // 50 次起固定 50 元宝
        DB::table('member_field')->where('uid', $uid)->update(['soultimes' => 60]);
        $this->assertSame(50, $this->towerRequest('get_price', $p['auth'])['price']);

        // 元宝不足
        DB::table('member')->where('uid', $uid)->update(['gold' => 49]);
        $this->assertSame(['status' => 2], $this->towerRequest('buy', $p['auth']));
        $this->assertSame(1, (int)$this->towerActivity($uid)['towertimes']);
    }

    public function testTowerDailyReward(): void
    {
        $p = $this->towerPlayer();
        $uid = $p['uid'];
        $this->assertSame(['status' => 3], $this->towerRequest('reward_info', $p['auth']), '未击败关卡');
        $this->assertSame(['status' => 3], $this->towerRequest('get_reward', $p['auth'] + ['type' => 3]));

        // 奖励按已过的最大关卡，info 显示下一场景 / 下一关
        $this->towerMap($uid, ['stage' => 1, 'mission' => 26]);
        $m26 = \Hanjiang\MasterData\SoulMission::ROWS[26];
        $rewards = \Hanjiang\MasterData\SoulReward::ROWS;
        $info = $this->towerRequest('reward_info', $p['auth']);
        $this->assertSame([
            'status' => 1,
            'effect' => 0,
            'info' => ['stage' => \Hanjiang\MasterData\SoulScene::ROWS[2]['name'], 'mission' => 27],
            'base_reward' => $m26['reward'],
            'normal_reward' => ['gold' => $rewards[1]['gold'], 'multiple' => intdiv($rewards[1]['ratio'], 100)],
            'best_reward' => ['gold' => $rewards[2]['gold'], 'multiple' => intdiv($rewards[2]['ratio'], 100)],
        ], $info);

        $this->assertSame(-3, $this->towerRequest('get_reward', $p['auth'] + ['type' => 0])['status']);
        DB::table('member')->where('uid', $uid)->update(['gold' => $rewards[2]['gold'] - 1]);
        $this->assertSame(4, $this->towerRequest('get_reward', $p['auth'] + ['type' => 2])['status'], '元宝不足');

        // 元宝加倍
        DB::table('member')->where('uid', $uid)->update(['gold' => 1000]);
        $soul = $this->towerMember($uid, 'soul');
        $res = $this->towerRequest('get_reward', $p['auth'] + ['type' => 1]);
        $gain = (int)($rewards[1]['ratio'] / 100 * $m26['reward']);
        $this->assertSame(['status' => 1, 'effect' => 0, 'gold' => 1000 - $rewards[1]['gold'], 'soul' => $soul + $gain], $res);
        $this->assertGreaterThanOrEqual(strtotime('today'), (int)$this->towerActivity($uid)['souldate']);
        $this->assertSame(['status' => 2], $this->towerRequest('reward_info', $p['auth']), '今日已领取');
        $this->assertSame(['status' => 2], $this->towerRequest('get_reward', $p['auth'] + ['type' => 3]));
        $this->assertSame(1, $this->towerRequest('get_mission_list', $p['auth'] + ['s' => 1])['get_reward']);

        // 次日不加倍领取
        DB::table('member_activity')->where('uid', $uid)->update(['souldate' => strtotime('yesterday')]);
        $soul = $this->towerMember($uid, 'soul');
        $res = $this->towerRequest('get_reward', $p['auth'] + ['type' => 3]);
        $this->assertSame([1, 1000 - $rewards[1]['gold'], $soul + $m26['reward']], [$res['status'], $res['gold'], $res['soul']]);
    }

    public function testTowerDailyCronResets(): void
    {
        $p = $this->towerPlayer();
        $uid = $p['uid'];
        $this->towerRequest('get_mission_list', $p['auth'] + ['s' => 1]);
        DB::table('member_activity')->where('uid', $uid)->update(['freetower' => 0, 'towertimes' => 3]);
        DB::table('member_field')->where('uid', $uid)->update(['soultimes' => 7, 'dateline' => strtotime('yesterday')]);
        Redis::del("daily_cron:{$uid}");

        // 旧版 daily_cron：soultimes 归零、freetower 重置为 10，购买的次数保留
        $this->assertSame(13, $this->towerRequest('get_mission_list', $p['auth'] + ['s' => 1])['times']);
        $this->assertSame([10, 3], [(int)$this->towerActivity($uid)['freetower'], (int)$this->towerActivity($uid)['towertimes']]);
        $this->assertSame(0, (int)DB::table('member_field')->where('uid', $uid)->value('soultimes'));

        // 没有活动记录的玩家不建档
        $q = $this->towerPlayer();
        DB::table('member_field')->where('uid', $q['uid'])->update(['dateline' => strtotime('yesterday')]);
        Redis::del("daily_cron:{$q['uid']}");
        $this->request('member', 'index', $q['auth']);
        $this->assertNull(DB::table('member_activity')->where('uid', $q['uid'])->first());
    }

    public function testTowerSendToChat(): void
    {
        $p = $this->towerPlayer();
        $w = $this->newGuestPlayer();
        $this->chatOnline($w);
        $this->assertSame(['status' => -1], $this->towerRequest('send_to_chat', $p['auth'] + ['id' => 1]));
        $this->assertSame(['status' => -2], $this->towerRequest('send_to_chat', $p['auth'] + ['id' => 999, 'report' => 5]));
        // 元宝不足
        DB::table('member')->where('uid', $p['uid'])->update(['gold' => 1]);
        $this->assertSame(['status' => -2], $this->towerRequest('send_to_chat', $p['auth'] + ['id' => 1, 'report' => 5]));
        // 旧版扣 2 元宝，全服发将魂战报（type 5）
        DB::table('member')->where('uid', $p['uid'])->update(['gold' => 10]);
        $this->assertSame(['status' => 1, 'all_gold' => 8], $this->towerRequest('send_to_chat', $p['auth'] + ['id' => 1, 'report' => 5, 'win' => 3]));
        $this->assertSame(8, $this->towerMember($p['uid'], 'gold'));
        $name = \Hanjiang\MasterData\SoulMission::ROWS[1]['name'];
        $this->assertSame([[5, $this->chatFrom($p) . ' 在将魂星路击败了 {9,' . $name . '} {4,[查看战报],5}', 5, $p['uid']]], $this->chatMessages($w));
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

        // 旧版商城 1 号位：MasterData Shop → 装备 7 青玉兵符，元宝 50
        $this->assertSame(7, $res['list'][1]['id']);
        $this->assertSame('青玉兵符', $res['list'][1]['name']);
        $this->assertSame(50, $res['list'][1]['price']);

        $detail = $this->request('shop', 'get_info', ['id' => 7]);
        $this->assertSame(1, $detail['status']);
        $this->assertArrayHasKey('info', $detail);

        $before = count(DB::table('equipment')->where('uid', self::$uid)->where('equipid', 7)->whereNull('deleted_at')->get());
        $buyItem = $this->request('shop', 'buy', ['id' => 7]);
        $this->assertSame(1, $buyItem['status']);
        $bought = DB::table('equipment')->where('uid', self::$uid)->where('equipid', 7)->whereNull('deleted_at')->get();
        $this->assertCount($before + 1, $bought);
        $this->assertSame('青玉兵符', $bought[0]['name']);
        $this->assertSame(5, (int)$bought[0]['type']);
        $this->assertSame(100, (int)$bought[0]['power']);

        $this->assertSame(-1, $this->request('shop', 'buy', ['id' => 31])['status'], '非旧版商城商品不可购买');

        $buyAct = $this->request('shop', 'buy_act');
        $this->assertSame(1, $buyAct['status']);
    }

    /**
     * 演武榜测试用：清空排行榜（各用例自建玩家与名次），返回按顺序入榜的 26 级新玩家
     *
     * @return list<array{uid: int, auth: array{token: string, uid: int}}>
     */
    private function freshArenaPlayers(int $count): array
    {
        foreach (['arena', 'arena_log', 'arena_paylog', 'arena_exchange_log'] as $table) {
            DB::execute('DELETE FROM `zc_sanguo_' . $table . '`');
        }
        Redis::del('arena_topuser');
        $players = [];
        for ($i = 0; $i < $count; $i++) {
            $p = $this->newGuestPlayer();
            DB::table('member')->where('uid', $p['uid'])->update(['level' => 26]);
            $res = $this->request('arena', 'index', $p['auth']);
            $this->assertSame($i + 1, $res['log']['rank'], '按入榜顺序排在最后一名之后');
            $players[] = $p;
        }
        return $players;
    }

    private function emptyFormation(int $uid): void
    {
        $positions = [];
        for ($i = 1; $i <= 9; $i++) {
            $positions['position' . $i] = 0;
        }
        DB::table('matrix')->where('uid', $uid)->update($positions);
    }

    /**
     * @return array<string, mixed>
     */
    private function arenaRow(int $uid): array
    {
        return DB::table('arena')->where('uid', $uid)->whereNull('deleted_at')->first();
    }

    public function testArenaIndexAndLadderEntry(): void
    {
        [$a] = $this->freshArenaPlayers(1);
        $now = time();

        // 旧版 26 级开放
        $low = $this->newGuestPlayer();
        $this->assertSame(['status' => -1, 'list' => []], array_intersect_key($this->request('arena', 'index', $low['auth']), ['status' => 1, 'list' => 1]));
        $this->assertNull(DB::table('arena')->where('uid', $low['uid'])->first());
        $this->assertSame(0, (int)DB::table('member_field')->where('uid', $low['uid'])->value('rank'), '未入榜 member_field.rank 为 0');

        // 第二个玩家入榜：最后一名 + 1，首次次数 10、领奖时间为入榜时间（当天显示「明日可领取」status 1）
        $b = $this->newGuestPlayer();
        DB::table('member')->where('uid', $b['uid'])->update(['level' => 26, 'gender' => 2]);
        $res = $this->request('arena', 'index', $b['auth']);
        $this->assertSame(1, $res['status']);
        $this->assertSame(['uid', 'username', 'rank', 'times', 'total_rank', 'dateline', 'get_time', 'cd', 'act_arena_int'], array_keys($res['log']));
        $this->assertSame(2, $res['log']['rank']);
        $this->assertSame(2, $res['log']['total_rank']);
        $this->assertSame(10, $res['log']['times']);
        $this->assertSame(0, $res['log']['cd']);
        $this->assertSame(0, $res['log']['act_arena_int']);
        $this->assertSame(2, (int)DB::table('member_field')->where('uid', $b['uid'])->value('rank'));
        // 名次 2 → ArenaRankreward 第 2 档
        $this->assertSame(['intnum' => \Hanjiang\MasterData\ArenaRankreward::ROWS[2]['intnum']], $res['reward']);
        $this->assertEqualsWithDelta(strtotime(date('Y-m-d', $now)) + 86400 - $now, $res['end_time'], 2, '距明日 0 点的秒数');
        // 名次 ≤ 5 取 1 ~ 5 名中存在的玩家，键从 1 开始；旧版 status 恒为 0
        $this->assertSame([1, 2], array_keys($res['list']));
        $this->assertSame(['id', 'uid', 'username', 'rank', 'status', 'gender', 'level'], array_keys($res['list'][1]));
        $this->assertSame([$a['uid'], 1, 0, 26], [$res['list'][1]['uid'], $res['list'][1]['rank'], $res['list'][1]['status'], $res['list'][1]['level']]);
        $this->assertSame([$b['uid'], 2, 2], [$res['list'][2]['uid'], $res['list'][2]['rank'], $res['list'][2]['gender']]);
        $this->assertSame([1 => ['uid' => $a['uid'], 'username' => $res['list'][1]['username'], 'tree' => 0], 2 => ['uid' => $b['uid'], 'username' => $res['list'][2]['username'], 'tree' => 0]], $res['top_list']);
        // 没有 NPC 守榜
        $this->assertSame(2, DB::table('arena')->count());

        // 同一天再进不重置次数；跨天（dateline 为昨天）重置为 15
        DB::table('arena')->where('uid', $b['uid'])->update(['times' => 3]);
        $this->assertSame(3, $this->request('arena', 'index', $b['auth'])['log']['times']);
        DB::table('arena')->where('uid', $b['uid'])->update(['dateline' => strtotime('yesterday')]);
        $this->assertSame(15, $this->request('arena', 'index', $b['auth'])['log']['times']);
        $this->assertSame(15, (int)$this->arenaRow($b['uid'])['times']);

        // 排名表
        $list = $this->request('arena', 'get_reward_list', $b['auth']);
        $this->assertSame(1, $list['status']);
        $this->assertSame(array_values(\Hanjiang\MasterData\ArenaRankreward::ROWS), $list['list']);

        // 旧版 cd_time 为 0：从不需要清除 cd
        $this->assertSame(-1, $this->request('arena', 'clear_cd', $b['auth'] + ['t' => 1])['status']);
    }

    public function testArenaEntersLadderOnLevel26(): void
    {
        $this->freshArenaPlayers(0);
        $p = $this->newGuestPlayer();
        DB::table('member')->where('uid', $p['uid'])->update(['level' => 25, 'exp' => Member::getLevelUpExp(25) - 1]);
        $res = $this->request('map', 'action', $p['auth'] + ['l' => 1, 's' => 1, 'id' => 1]);
        $this->assertGreaterThanOrEqual(1, $res['info']['win']);
        $this->assertSame(1, $res['info']['levelup']);
        // 旧版 member_model 升到 26 级即入榜
        $row = $this->arenaRow($p['uid']);
        $this->assertSame([1, 10, 1], [(int)$row['rank'], (int)$row['times'], (int)$row['total_rank']]);
        $this->assertSame(1, (int)DB::table('member_field')->where('uid', $p['uid'])->value('rank'));
    }

    public function testArenaChallengeHigherRankSwapsRanks(): void
    {
        [$b, $c, $d, $a] = $this->freshArenaPlayers(4);

        // B 阵法里没有武将：旧版 battle_pk 按空队伍继续战斗，A 不消耗随机数直接完胜（assess 3）
        $this->emptyFormation($b['uid']);
        $this->chatOnline($c);
        $res = $this->request('arena', 'action', $a['auth'] + ['t' => $b['uid']]);
        $this->assertSame(1, $res['status']);
        $info = $res['info'];
        $this->assertSame(3, $info['win']);
        $this->assertSame(1, $info['rank']);
        $this->assertSame(1, $info['total_rank'], '历史最高排名更新为 1');
        $this->assertSame(5, $info['win_times'], '旧版取历史最高排名（4）+ 1');
        $this->assertSame(1, $info['reward_status'], '当天已领奖（入榜即记领奖时间），显示新名次奖励');
        $this->assertSame(['status' => 1, 'reward' => ['intnum' => \Hanjiang\MasterData\ArenaRankreward::ROWS[1]['intnum']]], $info['reward']);
        $this->assertSame(9, $info['times']);
        $this->assertSame(0, $info['cd']);
        $this->assertSame([1, 2, 3, 4], array_column($info['list'], 'rank'));
        $this->assertSame([$a['uid'], $c['uid'], $d['uid'], $b['uid']], array_column($info['list'], 'uid'));
        $this->assertSame([$a['uid'], $c['uid'], $d['uid'], $b['uid']], array_column($info['top_list'], 'uid'), '前 5 名缓存已刷新');
        $this->assertGreaterThan(0, $info['report']);
        $this->assertSame(['id' => $info['report'], 'type' => 'arena'], $res['share']);
        // 战报（客户端 xGame.loadBattle 读 res.battle）
        $this->assertSame(['leftteam', 'rightteam', 'process', 'assess', 'leftresult', 'rightresult', 'leftbuff', 'rightbuff', 'left_role', 'right_role'], array_keys($res['battle']));
        $this->assertNotEmpty($res['battle']['leftteam']);
        $this->assertSame([], $res['battle']['rightteam']);
        $this->assertSame(3, $res['battle']['assess']);
        $this->assertSame(['name', 'level', 'gender'], array_keys($res['battle']['right_role']));

        $rowA = $this->arenaRow($a['uid']);
        $rowB = $this->arenaRow($b['uid']);
        $this->assertSame([1, 1, 0, 9], [(int)$rowA['rank'], (int)$rowA['total_rank'], (int)$rowA['win'], (int)$rowA['times']]);
        $this->assertSame([4, 1, 0], [(int)$rowB['rank'], (int)$rowB['total_rank'], (int)$rowB['win']], '防守方历史最高排名不变');
        $this->assertSame(1, (int)DB::table('member_field')->where('uid', $a['uid'])->value('rank'));
        $this->assertSame(4, (int)DB::table('member_field')->where('uid', $b['uid'])->value('rank'));
        $logs = DB::table('arena_log')->select('uid', 'type', 'win', 'rank', 'userid')->orderBy('id')->get();
        $this->assertEquals([
            ['uid' => $a['uid'], 'type' => 1, 'win' => 1, 'rank' => 1, 'userid' => $b['uid']],
            ['uid' => $b['uid'], 'type' => 2, 'win' => 2, 'rank' => 4, 'userid' => $a['uid']],
        ], $logs);
        // 演武信件（type 4，发件人为挑战者）
        $mail = Redis::getJson('user_messages:' . $b['uid']);
        $last = end($mail);
        $this->assertSame([4, $a['uid'], $info['report']], [$last['type'], $last['fromuid'], $last['id']]);
        $this->assertStringEndsWith('在演武榜将您击败，排名跌落至第4名', $last['message']);
        // 登上第 1 名：全服战报（get_message_str 2，send_message_to_report：type 3，id 为信件战报）
        $bName = (string)DB::table('arena')->where('uid', $b['uid'])->value('username');
        $this->assertSame([[3, $this->chatFrom($a) . ' 击败了 {1,' . $bName . ' ,' . $b['uid'] . '} 升至演武榜 {10,第1名}  {2,[查看战报],' . $info['report'] . '}', $info['report'], $a['uid']]], $this->chatMessages($c));

        // 排名比我低的不能打：C（2）打 B（4）
        $this->assertSame(-3, $this->request('arena', 'action', $c['auth'] + ['t' => $b['uid']])['status']);
        // 不在可挑战名单
        $outsider = $this->newGuestPlayer();
        $this->assertSame(-4, $this->request('arena', 'action', $c['auth'] + ['t' => $outsider['uid']])['status']);

        // D（3）阵法里没有武将打 C（2）：空队伍直接大败，名次不变、不记 log
        $this->emptyFormation($d['uid']);
        $lose = $this->request('arena', 'action', $d['auth'] + ['t' => $c['uid']]);
        $this->assertSame(1, $lose['status']);
        $this->assertSame(-3, $lose['info']['win']);
        $this->assertSame(3, $lose['info']['rank']);
        $this->assertSame(0, $lose['info']['win_times']);
        $this->assertSame([], $lose['info']['reward']);
        $this->assertSame([], $lose['battle']['leftteam']);
        $this->assertSame(3, (int)$this->arenaRow($d['uid'])['rank']);
        $this->assertSame(2, (int)$this->arenaRow($c['uid'])['rank']);
        $this->assertSame(2, DB::table('arena_log')->count());
        $mail = Redis::getJson('user_messages:' . $c['uid']);
        $this->assertStringEndsWith('在演武榜向您挑战，被您打的落花流水。', end($mail)['message']);

        // 双方都有阵容的正常战斗：战报带完整过程；胜（assess ≥ 1）则互换名次，否则不变
        $real = $this->request('arena', 'action', $c['auth'] + ['t' => $a['uid']]);
        $this->assertSame(1, $real['status']);
        $this->assertNotEmpty($real['battle']['leftteam']);
        $this->assertNotEmpty($real['battle']['rightteam']);
        $this->assertNotEmpty($real['battle']['process']);
        $won = $real['battle']['assess'] >= 1;
        $this->assertSame($real['battle']['assess'], $real['info']['win']);
        $this->assertSame($won ? 1 : 2, (int)$this->arenaRow($c['uid'])['rank']);
        $this->assertSame($won ? 2 : 1, (int)$this->arenaRow($a['uid'])['rank']);
        $this->assertSame($won ? 1 : 2, $real['info']['rank']);
    }

    public function testArenaChallengeGuards(): void
    {
        [$b, $a] = $this->freshArenaPlayers(2);

        // 对方正在被挑战（action 锁）
        Redis::set('action_lock_' . $b['uid'], 1, 30);
        $this->assertSame(-2, $this->request('arena', 'action', $a['auth'] + ['t' => $b['uid']])['status']);
        Redis::del('action_lock_' . $b['uid']);

        // 对方正在 pk 中（战斗锁），请求结束后 action 锁已释放
        Redis::set('arena_battle_' . $b['uid'], 1, 500);
        $this->assertSame(-5, $this->request('arena', 'action', $a['auth'] + ['t' => $b['uid']])['status']);
        Redis::del('arena_battle_' . $b['uid']);
        $this->assertNull(Redis::get('action_lock_' . $a['uid']));

        // 挑战次数用完
        DB::table('arena')->where('uid', $a['uid'])->update(['times' => 0]);
        $this->assertSame(-1, $this->request('arena', 'action', $a['auth'] + ['t' => $b['uid']])['status']);
        $this->assertSame(0, (int)$this->arenaRow($a['uid'])['times']);
    }

    public function testArenaBuyPkNum(): void
    {
        [$a] = $this->freshArenaPlayers(1);
        $price = \Hanjiang\MasterData\Price::ROWS[1]['act'];
        $gold = (int)DB::table('member')->where('uid', $a['uid'])->value('gold');

        // 只查价格（第 1 次购买取 Price 第 1 行军令价格）
        $this->assertSame(['status' => 1, 'price' => $price], array_intersect_key($this->request('arena', 'buy_pk_num', $a['auth'] + ['t' => 0]), ['status' => 1, 'price' => 1]));
        $this->assertSame($gold, (int)DB::table('member')->where('uid', $a['uid'])->value('gold'));

        $buy = $this->request('arena', 'buy_pk_num', $a['auth'] + ['t' => 1]);
        $this->assertSame(1, $buy['status']);
        $this->assertSame($gold - $price, $buy['gold']);
        $this->assertSame(11, $buy['times']);
        $this->assertSame(\Hanjiang\MasterData\Price::ROWS[2]['act'], $buy['price']);
        $this->assertSame($gold - $price, (int)DB::table('member')->where('uid', $a['uid'])->value('gold'));
        $pay = DB::table('arena_paylog')->where('uid', $a['uid'])->first();
        $this->assertSame([1, 1], [(int)$pay['times'], (int)$pay['total_times']]);

        // 昨天买过：今日次数从 0 算，累计次数继续加
        DB::table('arena_paylog')->where('uid', $a['uid'])->update(['dateline' => strtotime('yesterday')]);
        $this->assertSame(12, $this->request('arena', 'buy_pk_num', $a['auth'] + ['t' => 1])['times']);
        $pay = DB::table('arena_paylog')->where('uid', $a['uid'])->first();
        $this->assertSame([1, 2], [(int)$pay['times'], (int)$pay['total_times']]);

        // 元宝不足
        DB::table('member')->where('uid', $a['uid'])->update(['gold' => $price - 1]);
        $this->assertSame(-1, $this->request('arena', 'buy_pk_num', $a['auth'] + ['t' => 1])['status']);
        $this->assertSame(12, (int)$this->arenaRow($a['uid'])['times']);
    }

    public function testArenaRankRewardTiming(): void
    {
        [$a] = $this->freshArenaPlayers(1);

        // 入榜当天已记领奖时间：显示当前名次奖励，没有可领取的
        $this->assertSame(1, $this->request('arena', 'index', $a['auth'])['status']);
        $this->assertSame(-1, $this->request('arena', 'get_reward', $a['auth'])['status']);

        // 昨天领过、昨天最后一条挑战记录名次 11：今天按 11 名发（ArenaRankreward rank 11 档）
        DB::table('arena')->where('uid', $a['uid'])->update(['get_time' => strtotime('yesterday')]);
        DB::table('arena_log')->insert(['uid' => $a['uid'], 'type' => 1, 'win' => 1, 'rank' => 11, 'userid' => 0, 'dateline' => strtotime('yesterday') + 3600]);
        DB::table('arena_log')->insert(['uid' => $a['uid'], 'type' => 2, 'win' => 2, 'rank' => 30, 'userid' => 0, 'dateline' => strtotime('yesterday') + 60]);
        $intnum = \Hanjiang\MasterData\ArenaRankreward::ROWS[5]['intnum'];
        $res = $this->request('arena', 'index', $a['auth']);
        $this->assertSame(2, $res['status']);
        $this->assertSame(['intnum' => $intnum], $res['reward']);

        $get = $this->request('arena', 'get_reward', $a['auth']);
        $this->assertSame(1, $get['status']);
        $this->assertSame($intnum, $get['reward']['intnum']);
        $this->assertSame($intnum, $get['act_arena_int']);
        $this->assertSame(['intnum' => \Hanjiang\MasterData\ArenaRankreward::ROWS[1]['intnum']], $get['new_reward'], '新奖励按当前名次 1');
        $this->assertGreaterThanOrEqual(strtotime('today'), (int)$this->arenaRow($a['uid'])['get_time']);
        $this->assertSame($intnum, (int)DB::table('member_integral')->where('uid', $a['uid'])->value('act_arena_int'));
        $log = DB::table('integral_log')->where('uid', $a['uid'])->first();
        $this->assertSame([1, $intnum, $intnum, 'act_arena_int', 'arena', 'get_reward'], [(int)$log['type'], (int)$log['intnum'], (int)$log['balance'], $log['argument'], $log['class'], $log['method']]);

        // 同日不能重复领；再进演武榜显示明日奖励
        $this->assertSame(-1, $this->request('arena', 'get_reward', $a['auth'])['status']);
        $again = $this->request('arena', 'index', $a['auth']);
        $this->assertSame(1, $again['status']);
        $this->assertSame($intnum, $again['log']['act_arena_int']);
    }

    public function testArenaShopExchange(): void
    {
        [$a] = $this->freshArenaPlayers(1);
        $uid = $a['uid'];
        $rows = \Hanjiang\MasterData\ArenaExchange::ROWS;

        $low = $this->newGuestPlayer();
        $this->assertSame(-1, $this->request('arena', 'shop_index', $low['auth'])['status']);
        $this->assertSame(-1, $this->request('arena', 'shop_exchange', $low['auth'] + ['id' => 1])['status']);

        DB::table('member_integral')->where('uid', $uid)->update(['act_arena_int' => 1000]);
        $shop = $this->request('arena', 'shop_index', $a['auth']);
        $this->assertSame(1, $shop['status']);
        $this->assertSame(1000, $shop['act_arena_int']);
        $this->assertCount(count($rows), $shop['list']);
        $byId = array_column($shop['list'], null, 'id');
        $this->assertSame([1, $rows[1]['num']], [$byId[1]['is_exch'], $byId[1]['exch_num']]);
        $this->assertSame([0, $rows[8]['num']], [$byId[8]['is_exch'], $byId[8]['exch_num']], '积分不足不可兑换');
        $equip = \Hanjiang\MasterData\Equip::ROWS[$rows[15]['re_value1']];
        $this->assertSame(['id' => $equip['id'], 'image' => $equip['id'], 'name' => $equip['name'], 'quality' => $equip['quality'], 'type' => $equip['type'], 'needlevel' => $equip['needlevel'], 'power' => $equip['power']], $byId[15]['info']);
        $general = \Hanjiang\MasterData\General::ROWS[$rows[32]['re_value1']];
        $this->assertSame($general['name'], $byId[32]['info']['name']);
        $this->assertEquals(\Hanjiang\MasterData\Skill::ROWS[$general['skillid']], $byId[32]['info']['skill'], 'JSON 中 0.0 解码为 0');
        $this->assertArrayNotHasKey('is_exist', $byId[32]['info'], '旧版未拥有时没有 is_exist');
        $this->assertSame(\Hanjiang\MasterData\Gem::ROWS[1]['name'], $byId[27]['info']['gem_name']);
        $this->assertSame(2, $byId[27]['info']['gem_quality']);
        $soul = \Hanjiang\MasterData\Soul::ROWS[$rows[19]['re_value1']];
        $this->assertSame([$soul['name'], $soul['type'] . $soul['quality'], 6], [$byId[19]['info']['name'], $byId[19]['info']['image'], $byId[19]['info']['level']]);
        $this->assertArrayNotHasKey('info', $byId[1]);

        // 参数 / 商品校验
        $this->assertSame(-2, $this->request('arena', 'shop_exchange', $a['auth'] + ['id' => 0])['status']);
        $this->assertSame(-3, $this->request('arena', 'shop_exchange', $a['auth'] + ['id' => 9999])['status']);
        // 积分不足（将魂 re_type 27 要 1200 积分）不扣积分
        $this->assertSame(3, $this->request('arena', 'shop_exchange', $a['auth'] + ['id' => 19])['status']);
        $this->assertSame(1000, (int)DB::table('member_integral')->where('uid', $uid)->value('act_arena_int'));
        // 防刷锁
        Redis::set('arena_exchange_' . $uid, 1, 30);
        $this->assertSame(2, $this->request('arena', 'shop_exchange', $a['auth'] + ['id' => 1])['status']);
        Redis::del('arena_exchange_' . $uid);

        // 银币（re_type 2）
        $silver = (int)DB::table('member')->where('uid', $uid)->value('silver');
        $res = $this->request('arena', 'shop_exchange', $a['auth'] + ['id' => 1]);
        $this->assertSame(1, $res['status']);
        $this->assertSame(1000 - $rows[1]['use_int'], $res['act_arena_int']);
        $this->assertSame([1, 2, $rows[1]['re_value1'], $rows[1]['num'] - 1], [$res['reward']['id'], $res['reward']['re_type'], $res['reward']['re_value1'], $res['reward']['exch_num']]);
        $this->assertSame($silver + $rows[1]['re_value1'], (int)DB::table('member')->where('uid', $uid)->value('silver'));
        $this->assertSame(1, DB::table('arena_exchange_log')->where('uid', $uid)->where('pid', 1)->where('ptype', 1)->count());
        $log = DB::table('integral_log')->where('uid', $uid)->where('type', 2)->first();
        $this->assertSame([$rows[1]['use_int'], 1000 - $rows[1]['use_int'], 'shop_exchange'], [(int)$log['intnum'], (int)$log['balance'], $log['method']]);

        // 装备（re_type 8，限量 1 次）
        $points = 1000 - $rows[1]['use_int'];
        $res = $this->request('arena', 'shop_exchange', $a['auth'] + ['id' => 15]);
        $this->assertSame(1, $res['status']);
        $this->assertSame($equip['name'], $res['reward']['info']['name']);
        $this->assertSame(0, $res['reward']['exch_num']);
        $this->assertSame(1, DB::table('equipment')->where('uid', $uid)->where('equipid', $equip['id'])->whereNull('deleted_at')->count());
        $this->assertSame(3, $this->request('arena', 'shop_exchange', $a['auth'] + ['id' => 15])['status'], '限量已兑完');
        $points -= $rows[15]['use_int'];

        // 武将（re_type 12）：积分不足 3；已招募 4；兑换后 is_exist 1
        $this->assertSame(3, $this->request('arena', 'shop_exchange', $a['auth'] + ['id' => 32])['status']);
        DB::table('member_integral')->where('uid', $uid)->update(['act_arena_int' => 100000]);
        General::createFromTemplate($uid, $rows[35]['re_value1']);
        $this->assertSame(4, $this->request('arena', 'shop_exchange', $a['auth'] + ['id' => 35])['status']);
        $res = $this->request('arena', 'shop_exchange', $a['auth'] + ['id' => 32]);
        $this->assertSame(1, $res['status']);
        $this->assertSame(1, $res['reward']['info']['is_exist']);
        $this->assertSame(1, DB::table('general')->where('uid', $uid)->where('generalid', $general['id'])->whereNull('deleted_at')->count());
        $this->assertSame(100000 - $rows[32]['use_int'], $res['act_arena_int']);

        // 旧版 get_add_reward 的玩家存储类奖励：照旧版发放并记流水（类 arena、方法 shop_exchange）
        $before = DB::table('member')->where('uid', $uid)->first();
        foreach ([5, 6, 19, 27, 31] as $id) {
            $this->assertSame(1, $this->request('arena', 'shop_exchange', $a['auth'] + ['id' => $id])['status'], "商品 {$id}");
        }
        $after = DB::table('member')->where('uid', $uid)->first();
        // 5 玉石、6 宝石碎片
        $this->assertSame((int)$before['jade'] + $rows[5]['re_value1'], (int)$after['jade']);
        $this->assertSame((int)$before['gem'] + $rows[6]['re_value1'], (int)$after['gem']);
        $this->assertSame(['arena', 'shop_exchange'], array_values(DB::table('jade_log')->select('class', 'method')->where('uid', $uid)->first()));
        // 19 将魂（re_value1 模板、re_value2 等级）：own_soul + 包裹占用
        $soulRow = DB::table('own_soul')->where('uid', $uid)->whereNull('deleted_at')->first();
        $this->assertSame([$soul['name'], $rows[19]['re_value2']], [$soulRow['name'], (int)$soulRow['level']]);
        $this->assertSame((int)$before['souluse'] + 1, (int)$after['souluse']);
        // 27 宝石（re_value2 等级）：own_gem + 宝石格子占用
        $gemRow = DB::table('own_gem')->where('uid', $uid)->whereNull('deleted_at')->first();
        $this->assertSame([$rows[27]['re_value1'], $rows[27]['re_value2'], \Hanjiang\MasterData\Gem::ROWS[1]['level5']], [(int)$gemRow['gemid'], (int)$gemRow['level'], (int)$gemRow['power']]);
        $this->assertSame((int)$before['gemuse'] + 1, (int)$after['gemuse']);
        // 31 坐骑
        $this->assertSame(1, DB::table('own_mount')->where('uid', $uid)->where('mid', $rows[31]['re_value1'])->where('chip', 0)->count());
    }

    public function testArenaSendToChat(): void
    {
        [$b, $a] = $this->freshArenaPlayers(2);
        $this->chatOnline($b);
        $this->assertSame(-1, $this->request('arena', 'send_to_chat', $a['auth'] + ['touid' => $b['uid']])['status']);
        $this->assertSame(-3, $this->request('arena', 'send_to_chat', $a['auth'] + ['touid' => 999999999, 'report' => 5])['status']);
        DB::table('member')->where('uid', $a['uid'])->update(['gold' => 1]);
        $this->assertSame(-2, $this->request('arena', 'send_to_chat', $a['auth'] + ['touid' => $b['uid'], 'report' => 5])['status']);
        // 旧版扣 2 元宝，全服发切磋战报（get_message_str 3，type 3）
        DB::table('member')->where('uid', $a['uid'])->update(['gold' => 10]);
        $res = $this->request('arena', 'send_to_chat', $a['auth'] + ['touid' => $b['uid'], 'report' => 5, 'win' => 3]);
        $this->assertSame([1, 8], [$res['status'], $res['all_gold']]);
        $this->assertSame(8, (int)DB::table('member')->where('uid', $a['uid'])->value('gold'));
        $this->assertSame([[3, $this->chatFrom($a) . ' 在与 ' . $this->chatFrom($b) . ' 比武中 胜利  {2,[查看战报],5}', 5, $a['uid']]], $this->chatMessages($b));
        // 平局 / 战败
        $this->request('arena', 'send_to_chat', $a['auth'] + ['touid' => $b['uid'], 'report' => 6, 'win' => 0]);
        $this->request('arena', 'send_to_chat', $a['auth'] + ['touid' => $b['uid'], 'report' => 7, 'win' => -2]);
        $this->assertSame([
            $this->chatFrom($a) . ' 在与 ' . $this->chatFrom($b) . ' 比武中 打成平局  {2,[查看战报],6}',
            $this->chatFrom($a) . ' 在与 ' . $this->chatFrom($b) . ' 比武中 失败  {2,[查看战报],7}',
        ], array_column($this->chatMessages($b), 1));
        $this->assertSame(4, (int)DB::table('member')->where('uid', $a['uid'])->value('gold'));
    }

    /**
     * 新补的分享战报接口：c=map / mine / beauty 的 send_to_chat（旧版扣 2 元宝，全服广播）
     */
    public function testMapMineBeautySendToChat(): void
    {
        $p = $this->chatPlayer();
        $q = $this->chatPlayer();
        $w = $this->chatPlayer();
        $this->chatOnline($w);
        $from = $this->chatFrom($p);
        $to = $this->chatFrom($q);

        // 过图战报（type 4）：l / s / id 定位 Mission 关卡
        $this->assertSame(-1, $this->request('map', 'send_to_chat', $p['auth'] + ['l' => 1, 's' => 1, 'id' => 1])['status']);
        $this->assertSame(-2, $this->request('map', 'send_to_chat', $p['auth'] + ['l' => 99, 's' => 1, 'id' => 1, 'report' => 5])['status']);
        DB::table('member')->where('uid', $p['uid'])->update(['gold' => 1]);
        $this->assertSame(-2, $this->request('map', 'send_to_chat', $p['auth'] + ['l' => 1, 's' => 1, 'id' => 1, 'report' => 5])['status']);
        DB::table('member')->where('uid', $p['uid'])->update(['gold' => 100]);
        $res = $this->request('map', 'send_to_chat', $p['auth'] + ['l' => 1, 's' => 1, 'id' => 1, 'report' => 5]);
        $this->assertSame([1, 98], [$res['status'], $res['all_gold']]);
        $mission = null;
        foreach (\Hanjiang\MasterData\Mission::ROWS as $row) {
            if ((int)$row['missionlevel'] === 1 && (int)$row['missionstage'] === 1 && (int)$row['missionid'] === 1) {
                $mission = $row['name'];
            }
        }
        $this->assertSame([[4, $from . ' 击败了 {9,' . $mission . '} {3,[查看战报],5}', 5, $p['uid']]], $this->chatMessages($w));

        // 矿区战报（get_message_str 27，type 3）
        $this->assertSame(-1, $this->request('mine', 'send_to_chat', $p['auth'] + ['touid' => $q['uid']])['status']);
        $this->assertSame(-3, $this->request('mine', 'send_to_chat', $p['auth'] + ['touid' => 999999999, 'report' => 6])['status']);
        $res = $this->request('mine', 'send_to_chat', $p['auth'] + ['touid' => $q['uid'], 'report' => 6, 'win' => -1]);
        $this->assertSame([1, 96], [$res['status'], $res['all_gold']]);
        $this->assertSame([[3, $from . ' 在矿区抢夺 ' . $to . ' 的矿中 失败  {2,[查看战报],6}', 6, $p['uid']]], $this->chatMessages($w));

        // 群雄逐鹿切磋战报（get_message_str 3，type 3）
        $this->assertSame(-1, $this->request('compete', 'send_to_chat', $p['auth'] + ['report' => 6])['status']);
        $res = $this->request('compete', 'send_to_chat', $p['auth'] + ['touid' => $q['uid'], 'report' => 10, 'win' => 2]);
        $this->assertSame([1, 94], [$res['status'], $res['all_gold']]);
        $this->assertSame([[3, $from . ' 在与 ' . $to . ' 比武中 胜利  {2,[查看战报],10}', 10, $p['uid']]], $this->chatMessages($w));

        // 铜雀台战报（胜 49 / 平 51 / 败 50，消息 type 49）
        $this->assertSame(['status' => -1, 'msg' => '参数错误'], array_intersect_key($this->request('beauty', 'send_to_chat', $p['auth'] + ['touid' => $q['uid']]), ['status' => 1, 'msg' => 1]));
        $this->assertSame(['status' => -3, 'msg' => '获取用户信息失败'], array_intersect_key($this->request('beauty', 'send_to_chat', $p['auth'] + ['touid' => 999999999, 'report' => 7]), ['status' => 1, 'msg' => 1]));
        foreach ([1 => 7, 0 => 8, -1 => 9] as $win => $report) {
            $this->assertSame(1, $this->request('beauty', 'send_to_chat', $p['auth'] + ['touid' => $q['uid'], 'report' => $report, 'win' => $win])['status']);
        }
        $this->assertSame([
            [49, $from . ' 在铜雀台中大展雄风，战胜了' . $to . ' {49,[查看战报],7}', 7, $p['uid']],
            [49, $from . ' 和 ' . $to . ' 在铜雀台中战成 打成平局 {49,[查看战报],8}', 8, $p['uid']],
            [49, $from . ' 妄图抢劫' . $to . ' 的美人，被狠狠教训了一顿 {49,[查看战报],9}', 9, $p['uid']],
        ], $this->chatMessages($w));
        $this->assertSame(88, (int)DB::table('member')->where('uid', $p['uid'])->value('gold'));
        DB::table('member')->where('uid', $p['uid'])->update(['gold' => 0]);
        $this->assertSame(['status' => -2, 'msg' => '金币不足'], array_intersect_key($this->request('beauty', 'send_to_chat', $p['auth'] + ['touid' => $q['uid'], 'report' => 7]), ['status' => 1, 'msg' => 1]));
    }

    public function testWebSocketAndBroadcasterSystem(): void
    {
        // 1. 广播自定义全服事件
        $sentCount = \OmniPHP\WebSocket\Broadcaster::broadcast('system_alert', ['info' => '军令已补满']);
        $this->assertGreaterThanOrEqual(0, $sentCount);

        // 2. 对齐 smart-book 标准: 严格验证 RFC 6455 协议级控制帧 ping (0x9) 与 pong 响应
        $pingData = "protocol_ping_payload";
        $mockConn = $this->createMock(\Workerman\Connection\TcpConnection::class);
        $mockConn->expects($this->once())
            ->method('send')
            ->with($pingData);

        \Hanjiang\WebSocket\WebSocketHandler::onWebSocketPing($mockConn, $pingData);
        $this->assertGreaterThan(0, $mockConn->lastActiveTime ?? 0, '必须刷新连接最后活跃时间戳');

        // 3. 对齐 smart-book 标准: 验证超时无心跳僵尸连接回收清理 (sweep)
        $zombieConn = $this->createMock(\Workerman\Connection\TcpConnection::class);
        $zombieConn->id = 88888;
        $zombieConn->expects($this->once())->method('close');

        \OmniPHP\WebSocket\Broadcaster::register($zombieConn, 9999);
        $zombieConn->lastActiveTime = time() - 100; // 模拟 100 秒前最后活跃

        $swept = \OmniPHP\WebSocket\Broadcaster::sweep(60);
        $this->assertGreaterThanOrEqual(1, $swept, '超时僵尸连接应被自动识别并清理');

        // 4. 前端 WebSocket 客户端脚本就绪 (纯粹协议级设计，包含指数退避重连机制)
        $clientScript = dirname(__DIR__, 2) . '/public/static/js/network/ws_client.js';
        $this->assertFileExists($clientScript);
        $content = (string)file_get_contents($clientScript);
        $this->assertStringContainsString('scheduleReconnect', $content);
        $this->assertStringContainsString('RFC 6455', $content);

        // 5. 直接使用 OmniPHP 官方核心 HTTP Context (完全对标 Go Gin 设计模式)
        $ctx = \OmniPHP\Http\Context::mock(['foo' => 'bar']);
        $this->assertInstanceOf(\OmniPHP\Http\Context::class, $ctx);
        $this->assertSame('bar', $ctx->input('foo'));
        $ctx->set('user_role', 'admin');
        $this->assertSame('admin', $ctx->get('user_role'));

        // 6. OmniPHP 官方核心 WebSocket Context (解耦底层的极速纯内存单测能力)
        $wsCtx = \OmniPHP\WebSocket\Context::mock(['action' => 'ping'], uid: self::$uid);
        $this->assertSame('ping', $wsCtx->action());
        $this->assertSame(self::$uid, $wsCtx->uid());
        \Hanjiang\WebSocket\WebSocketHandler::dispatch($wsCtx);
        $this->assertStringContainsString('"pong"', $wsCtx->sentMessages[0]);

        // 未 auth 的连接不能发业务事件；聊天不再走 ws（旧版发言是 HTTP c=chat&m=send）
        $anonCtx = \OmniPHP\WebSocket\Context::mock(['action' => 'chat', 'message' => '冒名', 'uid' => self::$uid]);
        \Hanjiang\WebSocket\WebSocketHandler::dispatch($anonCtx);
        $this->assertStringContainsString('auth_fail', $anonCtx->sentMessages[0]);
        $errCtx = \OmniPHP\WebSocket\Context::mock(['action' => 'chat', 'message' => 'x'], uid: 1);
        \Hanjiang\WebSocket\WebSocketHandler::dispatch($errCtx);
        $this->assertStringContainsString('未知的事件类型: chat', $errCtx->sentMessages[0]);
    }

    /**
     * 聊天室实时推送：GameWS 鉴权后由 ws 进程弹出私人队列推送（载荷同 c=chat&m=read），HTTP 发言 / 系统消息都推
     */
    public function testChatWebSocketPush(): void
    {
        $this->chatReset();
        \Hanjiang\WebSocket\ChatPush::reset(0);
        $s = $this->chatPlayer();
        $a = $this->chatPlayer();
        $b = $this->chatPlayer();
        $this->chatOnline($s);

        // 鉴权失败不登记
        $bad = $this->chatWsAuth(['token' => 'nope']);
        $this->assertSame(['auth_fail'], array_column($this->chatFrames($bad), 0));
        // 鉴权前积压在私人队列里的消息（玩家在线时收到的）鉴权后立即推送
        $this->chatOnline($a);
        $this->chatSend($s, '鉴权前');
        $inboxA = $this->chatWsAuth($a['auth']);
        $frames = $this->chatFrames($inboxA);
        $this->assertSame(['auth_success', 'chat_message'], array_column($frames, 0));
        $this->assertSame(1, $frames[1][1]['status']);
        $this->assertSame(['鉴权前'], array_column($frames[1][1]['list'], 'message'));
        // 鉴权后玩家算在线（不再轮询也会收到全服消息）
        $inboxB = $this->chatWsAuth($b['auth']);
        $this->assertSame(['auth_success'], array_column($this->chatFrames($inboxB), 0));
        $this->assertSame(0, Redis::get('chat_ws_' . $b['uid']), '记下所在 ws 进程 0');

        // HTTP 发言 → 收件人所在 ws 进程的通知集合 → drain 推送，与 read 的结果完全相同
        $res = $this->chatSend($s, '大家好');
        \Hanjiang\WebSocket\ChatPush::drain();
        foreach ([$inboxA, $inboxB] as $inbox) {
            $frames = $this->chatFrames($inbox);
            $this->assertSame('chat_message', $frames[0][0]);
            $this->assertSame(1, $frames[0][1]['status']);
            $this->assertCount(1, $frames[0][1]['list']);
            $this->assertSame(array_diff_key($res['list'][0], ['time' => 1]), array_diff_key($frames[0][1]['list'][0], ['time' => 1]));
        }
        // 已推送的消息不会再被 read 取到（队列弹出，只送达一次）
        $this->assertSame([], $this->chatMessages($a));
        // 没有新消息时不推
        \Hanjiang\WebSocket\ChatPush::drain();
        $this->assertSame([], $this->chatFrames($inboxA));

        // 系统消息 / 喇叭 / 私聊同样推送
        \Hanjiang\Model\Chat::sendToHorn('喇叭来了');
        $this->chatSend($s, '只给你', ['uid' => $a['uid']]);
        \Hanjiang\WebSocket\ChatPush::drain();
        $this->assertSame([[2, '喇叭来了'], [1, '只给你']], array_map(static fn(array $m): array => [$m['type'], $m['message']], $this->chatFrames($inboxA)[0][1]['list']));
        $this->assertSame([[2, '喇叭来了']], array_map(static fn(array $m): array => [$m['type'], $m['message']], $this->chatFrames($inboxB)[0][1]['list']));

        // 推送同样过滤屏蔽的人
        $this->request('chat', 'ban_uid', $b['auth'] + ['touid' => $s['uid']]);
        $this->chatSend($s, '屏蔽测试');
        \Hanjiang\WebSocket\ChatPush::drain();
        $this->assertSame(['屏蔽测试'], array_column($this->chatFrames($inboxA)[0][1]['list'], 'message'));
        $this->assertSame([], $this->chatFrames($inboxB));

        // 多 ws 进程：玩家在进程 1 上时通知进程 1 的集合，进程 0 不推、不弹出他的队列
        \Hanjiang\Model\Chat::markWs($b['uid'], 'b', 1);
        \Hanjiang\Model\Chat::sendToAll('跨进程', \Hanjiang\Model\Chat::TYPE_SYSTEM);
        \Hanjiang\WebSocket\ChatPush::drain();
        $this->assertSame(['跨进程'], array_column($this->chatFrames($inboxA)[0][1]['list'], 'message'));
        $this->assertSame([], $this->chatFrames($inboxB));
        $this->assertSame([$b['uid']], array_map(intval(...), Redis::sMembers('chat_push_1')));
        // 进程 1 取通知并推送
        \Hanjiang\WebSocket\ChatPush::reset(1);
        $inboxB1 = $this->chatWsAuth($b['auth']);
        $this->assertSame(['auth_success', 'chat_message'], array_column($this->chatFrames($inboxB1), 0));
        \Hanjiang\WebSocket\ChatPush::drain();
        $this->assertSame([], Redis::sMembers('chat_push_1'));

        // 断线：去掉进程标记，之后的消息留在私人队列，客户端改回轮询 read 取走
        $conn = $this->createStub(\Workerman\Connection\TcpConnection::class);
        $conn->id = 777;
        $live = new \OmniPHP\WebSocket\Context($conn, (string)json_encode(['action' => 'auth', 'token' => $a['auth']['token']]));
        $frames = [];
        $conn->method('send')->willReturnCallback(static function (string $frame) use (&$frames): bool {
            $frames[] = json_decode($frame, true)['type'];
            return true;
        });
        \Hanjiang\WebSocket\WebSocketHandler::dispatch($live);
        $this->assertSame(['auth_success'], $frames);
        $this->chatSend($s, '真连接');
        \Hanjiang\WebSocket\ChatPush::drain();
        $this->assertSame(['auth_success', 'chat_message'], $frames);
        \Hanjiang\WebSocket\WebSocketHandler::onClose($conn);
        $this->assertNull(Redis::get('chat_ws_' . $a['uid']));
        $this->chatSend($s, '断线后');
        \Hanjiang\WebSocket\ChatPush::drain();
        $this->assertSame(['auth_success', 'chat_message'], $frames);
        $this->assertSame(['断线后'], array_column($this->chatMessages($a), 1));
        \Hanjiang\WebSocket\ChatPush::reset(0);
    }

    public function testCountrySystem(): void
    {
        $before = DB::table('member')->where('uid', self::$uid)->first();
        $this->assertNotNull($before);

        // 0. 等级不足 (_open: 等级 > 25) 时建国被拦截
        DB::table('member')->where('uid', self::$uid)->update(['level' => 20]);
        $this->assertSame(4, $this->request('country', 'create', ['name' => 'X', 'confirm' => 1])['status']);

        // 国家系统开放 (> 25)、国库捐献 (>= 50)、国师授艺 (>= 30)；VIP 1（旧版 VIP 0 入国 7 天内贡献不能超过 100）
        DB::table('member')->where('uid', self::$uid)->update(['level' => 60, 'gold' => 100000, 'silver' => 10000000, 'vip' => 1]);
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

    public function testMountSystem(): void
    {
        // 旧版 controllers/mount.php：主公 120 级开启，坐骑写 own_mount，将领身上的坐骑写 general.mid0 ~ mid4
        $this->withFreshPlayer(function (int $uid): void {
            $this->assertSame(2, $this->request('mount', 'index')['status'], '120 级以下未开启');
            DB::table('member')->where('uid', $uid)->update(['level' => 120]);
            $gid = (int)DB::table('general')->where('uid', $uid)->value('id');

            // 没有坐骑：ginfo 五个位置都是 0，mlist 为空
            $index = $this->request('mount', 'index');
            $this->assertSame(1, $index['status']);
            $this->assertSame([1, 1], [$index['page'], $index['allpage']]);
            $this->assertSame($gid, $index['general'][1]['id'], 'general 键从 1 起');
            $this->assertSame([0, 0, 0, 0, 0, $gid, 0], [$index['ginfo']['mid0'], $index['ginfo']['mid1'], $index['ginfo']['mid2'],
                $index['ginfo']['mid3'], $index['ginfo']['mid4'], $index['ginfo']['id'], $index['ginfo']['attack']]);
            $this->assertSame([], $index['mlist']);

            $add = static function (int $mid) use ($uid): int {
                \Hanjiang\Model\OwnMount::addMount($uid, $mid);
                return (int)DB::table('own_mount')->where('uid', $uid)->where('mid', $mid)->where('chip', 0)->orderBy('id', 'DESC')->value('id');
            };
            $yueying = $add(1);     // 越影：攻击 750 + intval(150 × 1.0) = 900
            $yuhui = $add(2);       // 逾辉：攻击 800 + 150 = 950
            $juedi = $add(3);       // 绝地
            $biyi = $add(103);      // 比翼鸟：特殊类型 1（value 5 + level 1 × 坐骑等级）
            $longque = $add(105);   // 龙雀：特殊类型 1
            $silver = $add(101);    // 经验银马：toexp 1000
            \Hanjiang\Model\OwnMount::addMount($uid, 4, 1, 3);
            $chipRow = (int)DB::table('own_mount')->where('uid', $uid)->where('mid', 4)->value('id');

            // 未装配列表：每页 8 个，按 mid DESC… 排序后经验马（type 1）排最前；碎片行带 full_num
            $page = $this->request('mount', 'get_mount_by_page', ['page' => 1]);
            $this->assertSame(1, $page['status']);
            $this->assertSame([$silver, $longque, $biyi, $chipRow, $juedi, $yuhui, $yueying], array_column($page['list'], 'id'));
            $this->assertSame([1, 0, 0, 0, 0, 0, 0], array_column($page['list'], 'type'));
            $this->assertSame('3/10', $page['list'][3]['full_num']);
            $this->assertSame([1, 5], [$page['list'][2]['special_type'], $page['list'][2]['special_value']]);
            $this->assertSame(1000, $page['list'][0]['toexp']);
            $this->assertSame($longque, $page['info']['id'], 'info 为排序前（mid DESC）的第一匹');
            $this->assertNotContains($yueying, array_column($this->request('mount', 'get_mount_by_page', ['page' => 1, 'mid' => $yueying])['list'], 'id'));

            // 装上：参数 / 将领 / 坐骑 / 碎片
            $this->assertSame(-1, $this->request('mount', 'fit_on', ['gid' => $gid, 'mid' => $yueying, 'site' => 5])['status']);
            $this->assertSame(-2, $this->request('mount', 'fit_on', ['gid' => 999999999, 'mid' => $yueying, 'site' => 0])['status']);
            $this->assertSame(-3, $this->request('mount', 'fit_on', ['gid' => $gid, 'mid' => 999999999, 'site' => 0])['status']);
            $this->assertSame(-7, $this->request('mount', 'fit_on', ['gid' => $gid, 'mid' => $chipRow, 'site' => 0])['status']);

            $attackOf = static fn (): int => (int)array_values(\Hanjiang\Battle\TeamBuilder::forMember($uid)['team'])[0]['attack'];
            $base = $attackOf();
            $fit = $this->request('mount', 'fit_on', ['gid' => $gid, 'mid' => $yueying, 'site' => 0, 'page' => 1]);
            $this->assertSame(1, $fit['status']);
            $this->assertSame($yueying, $fit['info']['mid0']['id']);
            $this->assertSame($yueying, $fit['minfo']['id']);
            $this->assertSame(900, $fit['info']['attack']);
            $this->assertNotContains($yueying, array_column($fit['mlist'], 'id'));
            $this->assertSame($gid, (int)DB::table('own_mount')->where('id', $yueying)->value('gid'));
            $this->assertSame($yueying, (int)DB::table('general')->where('id', $gid)->value('mid0'));
            $this->assertSame($base + 900, $attackOf(), 'TeamBuilder 按 mid0 全额计入');

            // 备战位：ceil(× 0.3)
            $fit = $this->request('mount', 'fit_on', ['gid' => $gid, 'mid' => $yuhui, 'site' => 1, 'page' => 1]);
            $this->assertSame(900 + 285, $fit['info']['attack']);
            $this->assertSame($base + 900 + 285, $attackOf(), 'TeamBuilder 按 mid1 计入 ceil(950 × 0.3)');

            // 换下：mid1 换成未装配的绝地，逾辉卸回
            $this->assertSame(1, $this->request('mount', 'fit_on', ['gid' => $gid, 'mid' => $juedi, 'site' => 1])['status']);
            $this->assertSame(0, (int)DB::table('own_mount')->where('id', $yuhui)->value('gid'));
            // 互换：越影（mid0）放到 mid1，绝地换到 mid0
            $this->assertSame(1, $this->request('mount', 'fit_on', ['gid' => $gid, 'mid' => $yueying, 'site' => 1])['status']);
            $row = DB::table('general')->where('id', $gid)->first();
            $this->assertSame([$juedi, $yueying, 0, 0, 0], [(int)$row['mid0'], (int)$row['mid1'], (int)$row['mid2'], (int)$row['mid3'], (int)$row['mid4']]);

            // 同种特殊类型（1）不能同时佩戴
            $this->assertSame(1, $this->request('mount', 'fit_on', ['gid' => $gid, 'mid' => $biyi, 'site' => 2])['status']);
            $this->assertSame(-8, $this->request('mount', 'fit_on', ['gid' => $gid, 'mid' => $longque, 'site' => 3])['status']);
            // 已被其他将领佩戴
            $copy = DB::table('general')->where('id', $gid)->first();
            unset($copy['id'], $copy['created_at'], $copy['updated_at'], $copy['deleted_at']);
            $other = (int)DB::table('general')->insert(array_merge($copy, ['goout' => 0, 'mid0' => 0, 'mid1' => 0, 'mid2' => 0, 'mid3' => 0, 'mid4' => 0]));
            $this->assertSame(-6, $this->request('mount', 'fit_on', ['gid' => $other, 'mid' => $biyi, 'site' => 0])['status']);

            // 查询将领坐骑：mid 为 0 取第一匹；mid 不为 0 时才给 mlist
            $info = $this->request('mount', 'get_info_by_id', ['gid' => $gid, 'page' => 1]);
            $this->assertSame([1, $juedi, [], 1], [$info['status'], $info['minfo']['id'], $info['mlist'], $info['allpage']]);
            $info = $this->request('mount', 'get_info_by_id', ['gid' => $gid, 'mid' => $yueying, 'page' => 1]);
            $this->assertSame($yueying, $info['minfo']['id']);
            $this->assertNotEmpty($info['mlist']);
            $this->assertSame(-1, $this->request('mount', 'get_info_by_id', ['gid' => 999999999])['status']);
            // 将领身上没有坐骑时取第 page 页第一匹未装配的整行（按 quality DESC…）
            $info = $this->request('mount', 'get_info_by_id', ['gid' => $other, 'page' => 1]);
            $this->assertSame($longque, $info['minfo']['id']);
            $this->assertArrayHasKey('toexp', $info['minfo']);

            // 卸下：将领该位置清零，坐骑 gid 清零
            $down = $this->request('mount', 'take_down', ['mid' => $biyi, 'page' => 1]);
            $this->assertSame(1, $down['status']);
            $this->assertSame(0, $down['info']['mid2']);
            $this->assertSame(0, (int)DB::table('general')->where('id', $gid)->value('mid2'));
            $this->assertSame(0, (int)DB::table('own_mount')->where('id', $biyi)->value('gid'));
            $this->assertSame(-1, $this->request('mount', 'take_down', ['mid' => 999999999])['status']);

            // 升级界面：经验马 -6；列表不含自己
            $this->assertSame(-6, $this->request('mount', 'get_level_info', ['id' => $silver])['status']);
            $this->assertSame(-1, $this->request('mount', 'get_level_info', ['id' => 999999999])['status']);
            $lv = $this->request('mount', 'get_level_info', ['id' => $yuhui]);
            $this->assertSame(1, $lv['status']);
            $this->assertNotContains($yuhui, array_column($lv['list'], 'id'));
            $this->assertSame([$yuhui, 0, 100], [$lv['info']['id'], $lv['info']['level'], $lv['info']['levelup_exp']]);

            // 升级：碎片 -7、已佩戴 -2、经验马 -6、满级 -4
            $this->assertSame(-7, $this->request('mount', 'levelup', ['id' => $yuhui, 'list' => (string)$chipRow])['status']);
            $this->assertSame(-2, $this->request('mount', 'levelup', ['id' => $yuhui, 'list' => (string)$juedi])['status']);
            $this->assertSame(-6, $this->request('mount', 'levelup', ['id' => $silver, 'list' => (string)$longque])['status']);
            $this->assertSame(-3, $this->request('mount', 'levelup', ['id' => 999999999, 'list' => (string)$longque])['status']);

            // 逾辉吃经验银马（toexp 1000）：0 级 100 → 1 级 180 → 2 级 324 → 3 级，剩 396；系数 2.462
            $up = $this->request('mount', 'levelup', ['id' => $yuhui, 'list' => (string)$silver]);
            $this->assertSame(1, $up['status']);
            $this->assertSame([3, 396, 584, 75 + 1000], [$up['info']['level'], $up['info']['exp'], $up['info']['levelup_exp'], $up['info']['toexp']]);
            $this->assertSame(800 + (int)(150 * 2.462), $up['info']['attack']);
            $this->assertSame(['level' => 3, 'exp' => 396, 'attack' => 1169, 'deleted' => true], [
                'level' => (int)DB::table('own_mount')->where('id', $yuhui)->value('level'),
                'exp' => (int)DB::table('own_mount')->where('id', $yuhui)->value('exp'),
                'attack' => (int)DB::table('own_mount')->where('id', $yuhui)->value('attack'),
                'deleted' => DB::table('own_mount')->where('id', $silver)->value('deleted_at') !== null,
            ]);
            $log = DB::table('mount_exp_log')->where('uid', $uid)->first();
            $this->assertSame([$yuhui, (string)$silver, 1000, 396, 3], [(int)$log['mid'], $log['exp_mid'], (int)$log['add_exp'], (int)$log['now_exp'], (int)$log['now_level']]);
            $info = DB::table('mount_info_log')->where('uid', $uid)->first();
            $this->assertSame([2, 101, 'mount', 'levelup'], [(int)$info['type'], (int)$info['mid'], $info['class'], $info['method']]);

            // 到 20 级封顶：溢出的经验不计入 toexp，exp 停在 19 级经验槽
            DB::table('own_mount')->where('id', $yuhui)->update(['level' => 19, 'exp' => 0, 'toexp' => 100]);
            $gold = $add(102);
            $need = \Hanjiang\MasterData\MountLevel::ROWS[19]['exp'];
            DB::table('own_mount')->where('id', $gold)->update(['toexp' => $need + 500]);
            $up = $this->request('mount', 'levelup', ['id' => $yuhui, 'list' => (string)$gold]);
            $this->assertSame([20, $need, 0, 100 + $need, 1], [$up['info']['level'], $up['info']['exp'], $up['info']['levelup_exp'], $up['info']['toexp'], $up['info']['is_full']]);
            $this->assertSame(-4, $this->request('mount', 'levelup', ['id' => $yuhui, 'list' => (string)$longque])['status']);
        });
    }

    public function testGemSystem(): void
    {
        // 旧版 controllers/gem.php：主公 140 级开启，宝石在 own_gem，碎片 member.gem，格子占用 member.gemuse
        $this->withFreshPlayer(function (int $uid): void {
            $this->assertSame(2, $this->request('gem', 'index')['status'], '140 级以下未开启');
            $this->assertSame(-1, $this->request('gem', 'mergegem_index')['status']);
            $this->assertSame(1, $this->request('gem', 'get_gem_by_page')['status'], '翻页不检查开启');
            DB::table('member')->where('uid', $uid)->update(['level' => 140]);

            // 阵上将领穿赤铜刀（类型 1 → 镶 1 类宝石）、赤铜甲（类型 3 → 镶 2 类宝石）
            $gid = (int)DB::table('general')->where('uid', $uid)->value('id');
            $weapon = Equipment::addFromTemplate($uid, 1)[0];
            $armor = Equipment::addFromTemplate($uid, 2)[0];
            DB::table('equipment')->whereIn('id', [$weapon, $armor])->update(['generalid' => $gid]);
            \Hanjiang\Model\OwnGem::addGem($uid, 1, 0, 2);   // 青龙石（类型 1）× 2
            \Hanjiang\Model\OwnGem::addGem($uid, 2, 1);      // 玄武石（类型 2）1 级
            DB::table('member')->where('uid', $uid)->update(['gemuse' => 3, 'gem' => 0]);
            $gems = array_map('intval', DB::table('own_gem')->where('uid', $uid)->orderBy('id')->pluck('id'));
            [$qing1, $qing2, $xuan] = $gems;

            // 背包按 level DESC, type ASC：玄武 1 级在前；gem 为第一颗并带下一级信息（2 类系数 0.75：ceil(170 × 0.75) = 128）
            $index = $this->request('gem', 'index');
            $this->assertSame(1, $index['status']);
            $this->assertSame([0, 1, 3, 1], [$index['pack']['chip'], $index['pack']['page'], $index['pack']['num'], $index['pack']['allpage']]);
            $this->assertSame([$xuan, $qing1, $qing2], array_column($index['pack']['list'], 'id'));
            $this->assertSame(['id', 'name', 'type', 'level', 'power', 'quality'], array_keys($index['pack']['list'][0]));
            $this->assertSame([$xuan, 112, 128, 0], [$index['gem']['id'], $index['gem']['next_power'], $index['gem']['need_chip'], $index['gem']['ismax']]);
            $this->assertSame([$weapon, $armor], array_column($index['eid_list'], 'id'));
            $this->assertSame((string)DB::table('general')->where('id', $gid)->value('name'), $index['eid_list'][0]['generalname']);
            $this->assertSame(0, $index['eid_list'][0][1], '第一件装备带 1 ~ 8 号位');

            // 镶嵌：位置 / 装备 / 宝石 / 类型
            $this->assertSame(-1, $this->request('gem', 'adron', ['id' => $qing1, 'eid' => $weapon, 'site' => 9])['status']);
            $this->assertSame(-2, $this->request('gem', 'adron', ['id' => $qing1, 'eid' => 999999999, 'site' => 1])['status']);
            $this->assertSame(-3, $this->request('gem', 'adron', ['id' => 999999999, 'eid' => $weapon, 'site' => 1])['status']);
            $this->assertSame(-7, $this->request('gem', 'adron', ['id' => $xuan, 'eid' => $weapon, 'site' => 1])['status']);
            $adron = $this->request('gem', 'adron', ['id' => $qing1, 'eid' => $weapon, 'site' => 1]);
            $this->assertSame([1, 2, [$xuan, $qing2]], [$adron['status'], $adron['num'], array_column($adron['list'], 'id')]);
            $this->assertSame(-5, $this->request('gem', 'adron', ['id' => $qing1, 'eid' => $weapon, 'site' => 1])['status']);
            $this->assertSame(-4, $this->request('gem', 'adron', ['id' => $qing1, 'eid' => $armor, 'site' => 1])['status']);
            // 位置已有宝石、当前宝石未镶嵌：原来那颗卸回背包，占用不变
            $this->assertSame(2, $this->request('gem', 'adron', ['id' => $qing2, 'eid' => $weapon, 'site' => 1])['num']);
            $this->assertSame([0, $weapon], [(int)DB::table('own_gem')->where('id', $qing1)->value('eid'), (int)DB::table('own_gem')->where('id', $qing2)->value('eid')]);
            // 镶到 2 号位，再把 2 号位那颗挪到 1 号位：互换
            $this->assertSame(1, $this->request('gem', 'adron', ['id' => $qing1, 'eid' => $weapon, 'site' => 2])['num']);
            $this->assertSame(1, $this->request('gem', 'adron', ['id' => $qing1, 'eid' => $weapon, 'site' => 1])['status']);
            $this->assertSame([1, 2], [(int)DB::table('own_gem')->where('id', $qing1)->value('site'), (int)DB::table('own_gem')->where('id', $qing2)->value('site')]);

            // 装备详情：1 ~ 8 号位与第一颗宝石；装备上没有宝石时取 gemid
            $byId = $this->request('gem', 'get_gem_by_id', ['id' => $weapon]);
            $this->assertSame([1, $qing1, $qing2, 0, $qing1], [$byId['status'], $byId['equip'][1]['id'], $byId['equip'][2]['id'], $byId['equip'][3], $byId['gem']['id']]);
            $byId = $this->request('gem', 'get_gem_by_id', ['id' => $armor, 'gemid' => $xuan]);
            $this->assertSame($xuan, $byId['gem']['id']);
            $this->assertSame(-2, $this->request('gem', 'get_gem_by_id', ['id' => $armor, 'gemid' => 999999999])['status']);
            $this->assertSame(-1, $this->request('gem', 'get_gem_by_id', ['id' => 0])['status']);

            // TeamBuilder 按装备上的宝石 power 计入（青龙石 0 级 100 × 2）
            $attack = (int)array_values(\Hanjiang\Battle\TeamBuilder::forMember($uid)['team'])[0]['attack'];

            // 卸下：占用 +1
            $this->assertSame(-2, $this->request('gem', 'pick_off', ['id' => $xuan])['status']);
            $this->assertSame(-1, $this->request('gem', 'pick_off', ['id' => 0])['status']);
            $off = $this->request('gem', 'pick_off', ['id' => $qing2]);
            $this->assertSame([1, 2], [$off['status'], $off['num']]);
            $this->assertSame($attack - 100, (int)array_values(\Hanjiang\Battle\TeamBuilder::forMember($uid)['team'])[0]['attack']);

            // 升级：碎片不足 -5；够了扣 ceil(100 × 1) 碎片，power 取 level1
            $this->assertSame(-5, $this->request('gem', 'level_up', ['id' => $qing1])['status']);
            DB::table('member')->where('uid', $uid)->update(['gem' => 1000]);
            $up = $this->request('gem', 'level_up', ['id' => $qing1]);
            $this->assertSame([1, 122, 1, 1, 170, 149, 0, 900], [$up['status'], $up['power'], $up['quality'], $up['level'], $up['need_chip'], $up['next_power'], $up['ismax'], $up['all_chip']]);
            $this->assertSame([1, 122], [(int)DB::table('own_gem')->where('id', $qing1)->value('level'), (int)DB::table('own_gem')->where('id', $qing1)->value('power')]);
            $log = DB::table('gem_log')->where('uid', $uid)->orderBy('id', 'DESC')->first();
            $this->assertSame([2, 100, 900, 'gem', 'level_up'], [(int)$log['type'], (int)$log['gem'], (int)$log['balance'], $log['class'], $log['method']]);
            DB::table('own_gem')->where('id', $qing2)->update(['level' => 20]);
            $this->assertSame(-2, $this->request('gem', 'level_up', ['id' => $qing2])['status'], '20 级不能再升');
            $info = $this->request('gem', 'get_gem_info', ['id' => $qing2]);
            $this->assertSame([1, 1, 5], [$info['status'], $info['gem']['ismax'], $info['gem']['quality']]);
            $this->assertSame(-1, $this->request('gem', 'get_gem_info', ['id' => 999999999])['status']);

            // 转碎片：1 级 2 类 = ceil(100 × 0.75) + 50 = 125；不确认只报价
            $turn = $this->request('gem', 'gem_turn', ['id' => $xuan]);
            $this->assertSame([1, 125, 900, 2], [$turn['status'], $turn['chip'], $turn['all_chip'], $turn['num']]);
            $turn = $this->request('gem', 'gem_turn', ['id' => $xuan, 'confirm' => 1]);
            $this->assertSame([1, 125, 1025, 1], [$turn['status'], $turn['chip'], $turn['all_chip'], $turn['num']]);
            $this->assertNotNull(DB::table('own_gem')->where('id', $xuan)->value('deleted_at'));
            $this->assertSame(-1, $this->request('gem', 'gem_turn', ['id' => $xuan])['status']);

            // 翻页
            $this->assertSame(-1, $this->request('gem', 'get_gem_by_page', ['p' => 2])['status']);
            $this->assertSame([$qing2], array_column($this->request('gem', 'get_gem_by_page', ['p' => 1])['list'], 'id'));

            // 合成：两颗同类型 20 级 → 一颗 21 级新宝石（Gem 6 ~ 10），记 own_gem_mergelog，不改 gemuse
            \Hanjiang\Model\OwnGem::addGem($uid, 1, 20);
            \Hanjiang\Model\OwnGem::addGem($uid, 2, 20);
            \Hanjiang\Model\OwnGem::addGem($uid, 1, 19);
            DB::table('member')->where('uid', $uid)->update(['gemuse' => 4]);
            [$qing3, $xuan20, $qing19] = array_map('intval', DB::table('own_gem')->where('uid', $uid)->where('id', '>', $xuan)->orderBy('id')->pluck('id'));

            $merge = $this->request('gem', 'mergegem_index');
            $this->assertSame([1, '成功', [6, 7, 8, 9, 10], 1, 1], [$merge['status'], $merge['msg'], array_column($merge['temp'], 'id'), $merge['allpage'], $merge['page']]);
            $this->assertSame([6], array_values(array_unique(array_column($merge['temp'], 'quality'))));
            $this->assertSame([$qing2, $qing3, $xuan20, $qing19], array_column($merge['list'], 'id'));
            $this->assertSame(1, $merge['gem']['ismax']);
            $this->assertSame(-1, $this->request('gem', 'get_20gem_by_page', ['page' => 2])['status']);
            $this->assertSame(1, $this->request('gem', 'get_20gem_by_page', ['page' => 1])['status']);

            $this->assertSame(-3, $this->request('gem', 'merge_gem', ['gemid1' => $qing2, 'gemid2' => $qing2])['status']);
            $this->assertSame(-4, $this->request('gem', 'merge_gem', ['gemid1' => $qing2, 'gemid2' => $qing19])['status']);
            $this->assertSame(-5, $this->request('gem', 'merge_gem', ['gemid1' => $qing2, 'gemid2' => $xuan20])['status']);
            // 锁还在：返回 -2 并删掉锁
            Redis::set('gem_merge_gem_' . $uid, 1, 30);
            $this->assertSame(-2, $this->request('gem', 'merge_gem', ['gemid1' => $qing2, 'gemid2' => $qing3])['status']);
            $this->assertNull(Redis::get('gem_merge_gem_' . $uid));

            $done = $this->request('gem', 'merge_gem', ['gemid1' => $qing2, 'gemid2' => $qing3]);
            $this->assertSame(1, $done['status']);
            $newId = $done['new_gem']['id'];
            $this->assertSame(['status' => 1, 'id' => $newId, 'name' => '苍龙石', 'type' => 1, 'quality' => 6,
                'power' => \Hanjiang\MasterData\Gem::ROWS[6]['level21'], 'level' => 21], $done['new_gem']);
            $this->assertSame(4, $done['num'], '旧版合成不改 gemuse');
            $this->assertSame([$newId, $xuan20, $qing19], array_column($done['list'], 'id'));
            $log = DB::table('own_gem_mergelog')->where('uid', $uid)->first();
            $this->assertSame([$qing2, $qing3, $newId], [(int)$log['gemid1'], (int)$log['gemid2'], (int)$log['new_gemid']]);
            $this->assertSame(2, DB::table('own_gem')->whereIn('id', [$qing2, $qing3])->whereNotNull('deleted_at')->count());
            $this->assertNull(Redis::get('gem_merge_gem_' . $uid));
            // 21 级可继续升到 25 级
            $this->assertSame(0, $this->request('gem', 'get_gem_info', ['id' => $newId])['gem']['ismax']);
        });
    }

    /**
     * 世界 / 国家 BOSS 测试的共同准备：清空两类 BOSS 的 Redis 键，固定 BossClock
     */
    private function bossAt(string $datetime): int
    {
        $ts = (int)strtotime($datetime);
        \Hanjiang\Model\BossClock::freeze($ts);
        return $ts;
    }

    private function bossReset(): void
    {
        \Hanjiang\Model\BossClock::freeze(null);
        \Hanjiang\Model\WorldBoss::deleteByPattern('worldboss_*');
        \Hanjiang\Model\WorldBoss::deleteByPattern('countryboss_*');
        \Hanjiang\Model\WorldBoss::deleteByPattern('log_*_countryboss_*');
    }

    /**
     * @return array{uid: int, auth: array<string, mixed>}
     */
    private function bossPlayer(int $level, int $country = 0): array
    {
        $p = $this->newGuestPlayer();
        DB::table('member')->where('uid', $p['uid'])->update(['level' => $level, 'country' => $country, 'nickname' => 'boss' . $p['uid']]);
        Redis::del('player:' . $p['uid']);
        return $p;
    }

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function bossRequest(string $c, string $m, array $auth, array $params = []): array
    {
        $res = $this->request($c, $m, array_merge($auth, $params));
        unset($res['message'], $res['msg']);
        return $res;
    }

    /**
     * 战报里左方对 BOSS 的伤害（旧版控制器：左方出手记录里 self = 0 的 hurt.blood 之和）
     *
     * @param array<string, mixed> $battle
     */
    private function bossHitOf(array $battle): int
    {
        $hit = 0;
        foreach ($battle['process'] as $value) {
            if ($value['launch'] === 'left') {
                foreach ($value['hurt'] as $v) {
                    if (isset($v['self']) && $v['self'] == 0) {
                        $hit += $v['blood'];
                    }
                }
            }
        }
        return $hit;
    }

    public function testWorldBossWindowFollowsLegacySchedule(): void
    {
        $this->bossReset();
        try {
            $low = $this->bossPlayer(45);
            $p = $this->bossPlayer(46);

            // 46 级开放
            $this->bossAt('2026-10-30 12:05:00');
            $this->assertSame(6, $this->bossRequest('worldboss', 'index', $low['auth'])['status']);

            // 12:00 前未开启，战斗返回 5
            $this->bossAt('2026-10-30 11:59:59');
            $this->assertSame(3, $this->bossRequest('worldboss', 'index', $p['auth'])['status']);
            $this->assertSame(['status' => 5, 'info' => [], 'battle' => []], $this->bossRequest('worldboss', 'battle', $p['auth']));

            // 12:00 开战：准备数据
            $this->bossAt('2026-10-30 12:00:00');
            $res = $this->bossRequest('worldboss', 'index', $p['auth']);
            $this->assertSame([
                'status' => 2,
                'countdown' => 900,
                'powerup' => 100,
                'bossinfo' => ['name' => '刑天', 'blood' => '100.00', 'image' => 'worldboss1'],
                'reward' => [],
                'top' => [],
                'cd' => 0,
                'upcost' => ['gold' => 10, 'silver' => 46 * 500],
                'cdcost' => 10,
                'my' => ['rank' => 0, 'hit' => 0],
            ], $res);

            // 12:15 结束（900 秒整算结束），未参加：20:15 之前算「未开启」
            $this->bossAt('2026-10-30 12:15:00');
            $this->assertSame(3, $this->bossRequest('worldboss', 'index', $p['auth'])['status']);
            $this->assertSame(5, $this->bossRequest('worldboss', 'battle', $p['auth'])['status']);

            // 20:00 场：没参加过 12:00 场的玩家切到 20:00 场
            $this->bossAt('2026-10-30 20:14:00');
            $res = $this->bossRequest('worldboss', 'index', $p['auth']);
            $this->assertSame(2, $res['status']);
            $this->assertSame(60, $res['countdown']);

            // 20:16 以后：活动已结束
            $this->bossAt('2026-10-30 20:16:00');
            $this->assertSame(4, $this->bossRequest('worldboss', 'index', $p['auth'])['status']);

            // 活动列表的 boss 项（activity.php:worldboss_init）与主城开启标识（member.php:worldboss_init）
            $this->bossAt('2026-10-30 12:05:00');
            $act = $this->bossRequest('activity', 'index', $p['auth']);
            $this->assertSame(['status' => 1, 'starttime' => '12:00', 'endtime' => '12:15', 'openlevel' => 46, 'starttime2' => '20:00', 'endtime2' => '20:15', 'popo' => 0], $act['list']['boss']);
            $member = $this->bossRequest('member', 'index', $p['auth']);
            $this->assertSame([1, 0], [$member['openboss'], $member['openbossreward']]);
            $this->assertSame(0, $this->bossRequest('member', 'index', $low['auth'])['openboss']);
            $this->bossAt('2026-10-30 12:16:00');
            $this->assertSame(0, $this->bossRequest('member', 'index', $p['auth'])['openboss']);
        } finally {
            $this->bossReset();
        }
    }

    public function testWorldBossBattleRecordsHitRankAndCd(): void
    {
        $this->bossReset();
        try {
            $p = $this->bossPlayer(60);
            $uid = $p['uid'];
            $this->bossAt('2026-10-30 12:00:10');
            $gold = $this->towerMember($uid, 'gold');

            $res = $this->bossRequest('worldboss', 'battle', $p['auth']);
            $this->assertSame(1, $res['status']);
            $battle = $res['battle'];
            $this->assertSame($battle['assess'], $res['info']['win']);
            $this->assertSame(['leftteam', 'rightteam', 'process', 'assess', 'leftresult', 'rightresult', 'leftbuff', 'rightbuff'], array_keys($battle));
            // 守方：刑天固定 5 号位，血条按百分比、herotype 0（旧版控制器改写）
            $this->assertSame([5], array_keys($battle['rightteam']));
            $boss = $battle['rightteam'][5];
            $this->assertSame(['刑天', 'worldboss1', '100.00', 0], [$boss['name'], $boss['image'], $boss['allblood'], $boss['herotype']]);
            $this->assertSame('1', $boss['heroid']);
            $this->assertSame(100, $battle['rightresult'][5]['allblood']);
            $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $battle['rightresult'][5]['blood']);
            $hit = $this->bossHitOf($battle);
            $this->assertGreaterThan(0, $hit);
            $this->assertSame($hit, \Hanjiang\Model\WorldBoss::myHit(0, $uid));

            // 1 秒战斗锁：cd 61
            $this->assertSame(['status' => 2, 'info' => ['cd' => 61], 'battle' => []], $this->bossRequest('worldboss', 'battle', $p['auth']));
            Redis::del('worldboss_hit_lock_' . $uid);
            // 复活 CD：距上次攻击 30 秒 → 还要 60 - 30 + 1 秒
            Redis::expire('worldboss_my_hit_' . $uid, \Hanjiang\Model\WorldBoss::REDIS_EXPIRE - 30);
            $res = $this->bossRequest('worldboss', 'battle', $p['auth']);
            $this->assertSame(2, $res['status']);
            $this->assertContains($res['info']['cd'], [30, 31]);
            $index = $this->bossRequest('worldboss', 'index', $p['auth']);
            $this->assertContains($index['cd'], [30, 31]);
            $this->assertSame(['rank' => 1, 'hit' => $hit], $index['my']);
            $this->assertSame((string)$uid, $index['top'][0]['uid']);
            $this->assertSame((string)$hit, $index['top'][0]['hit']);
            $this->assertSame('boss' . $uid, $index['top'][0]['nickname']);
            $this->assertSame(['uid', 'hit', 'rank', 'level', 'gender', 'nickname'], array_keys($index['top'][0]));
            // 血条按剩余时间自然减少：890 / 900 ≈ 98.89%
            $this->assertSame('98.89', $index['bossinfo']['blood']);

            // 立即复活：扣 10 元宝，伤害累加
            Redis::del('worldboss_hit_lock_' . $uid);
            $res = $this->bossRequest('worldboss', 'battle', $p['auth'], ['now' => 1]);
            $this->assertSame(1, $res['status']);
            $this->assertSame($gold - 10, $res['info']['all_gold']);
            $this->assertSame($gold - 10, $this->towerMember($uid, 'gold'));
            $this->assertSame($hit + $this->bossHitOf($res['battle']), \Hanjiang\Model\WorldBoss::myHit(0, $uid));

            // 元宝不足
            Redis::del('worldboss_hit_lock_' . $uid);
            DB::table('member')->where('uid', $uid)->update(['gold' => 5]);
            $this->assertSame(3, $this->bossRequest('worldboss', 'battle', $p['auth'], ['now' => 1])['status']);
            $this->assertSame(5, $this->towerMember($uid, 'gold'));

            // 排行：按累计伤害降序，排名同 index.my.rank
            $q = $this->bossPlayer(60);
            \Hanjiang\Model\WorldBoss::updateMyHit(0, $q['uid'], \Hanjiang\Model\WorldBoss::myHit(0, $uid) + 1, 800);
            $index = $this->bossRequest('worldboss', 'index', $p['auth']);
            $this->assertSame(2, $index['my']['rank']);
            $this->assertSame([(string)$q['uid'], (string)$uid], array_column($index['top'], 'uid'));
            $this->assertSame([1, 2], array_column($index['top'], 'rank'));
        } finally {
            $this->bossReset();
        }
    }

    public function testWorldBossPowerupCostAndCap(): void
    {
        $this->bossReset();
        try {
            $p = $this->bossPlayer(50);
            $uid = $p['uid'];
            $this->bossAt('2026-10-30 12:01:00');
            DB::table('member')->where('uid', $uid)->update(['silver' => 100000, 'gold' => 100]);

            // 银币鼓舞：主公等级 × 500
            $res = $this->bossRequest('worldboss', 'powerup', $p['auth'], ['gold' => 0]);
            $this->assertSame(['status' => 1, 'powerup' => 110, 'all_silver' => 100000 - 25000], $res);
            // 元宝鼓舞：10 元宝
            $res = $this->bossRequest('worldboss', 'powerup', $p['auth'], ['gold' => 1]);
            $this->assertSame(['status' => 1, 'powerup' => 120, 'all_gold' => 90], $res);
            $this->assertSame(120, $this->bossRequest('worldboss', 'index', $p['auth'])['powerup']);

            // 银币不足 / 元宝不足：不加成
            DB::table('member')->where('uid', $uid)->update(['silver' => 100, 'gold' => 5]);
            $this->assertSame(['status' => 3, 'powerup' => 0], $this->bossRequest('worldboss', 'powerup', $p['auth'], ['gold' => 0]));
            $this->assertSame(['status' => 2, 'powerup' => 0], $this->bossRequest('worldboss', 'powerup', $p['auth'], ['gold' => 1]));
            $this->assertSame(120, \Hanjiang\Model\WorldBoss::powerUp(0, $uid));

            // 上限 200：不扣费
            DB::table('member')->where('uid', $uid)->update(['gold' => 1000]);
            for ($i = 130; $i <= 200; $i += 10) {
                $this->assertSame($i, $this->bossRequest('worldboss', 'powerup', $p['auth'], ['gold' => 1])['powerup']);
            }
            $this->assertSame(['status' => 4, 'powerup' => 200], $this->bossRequest('worldboss', 'powerup', $p['auth'], ['gold' => 1]));
            $this->assertSame(1000 - 80, $this->towerMember($uid, 'gold'));
            // 加成系数 = powerup / 100（战斗双方伤害都乘，见 BattleEngineTest）
            $this->assertSame(200, $this->bossRequest('worldboss', 'index', $p['auth'])['powerup']);
        } finally {
            $this->bossReset();
        }
    }

    public function testWorldBossRewardAfterEndAndOncePerDay(): void
    {
        $this->bossReset();
        try {
            $p = $this->bossPlayer(60);
            $q = $this->bossPlayer(60);
            $uid = $p['uid'];
            $this->bossAt('2026-10-30 12:03:00');
            $this->assertSame(1, $this->bossRequest('worldboss', 'battle', $p['auth'])['status']);
            $hit = \Hanjiang\Model\WorldBoss::myHit(0, $uid);
            // 活动进行中不能领奖
            $this->assertSame(['status' => 2, 'reward' => []], $this->bossRequest('worldboss', 'reward', $p['auth']));
            // 进行中 20 点场的玩家不受影响：q 没打，排行只有 p
            $this->assertSame(['status' => 2, 'reward' => []], $this->bossRequest('worldboss', 'reward', $q['auth']));

            // 结束后：伤害榜 + 奖励（第 1 名 1000 积分，最后一击 × 1.5）
            $w = $this->chatPlayer();
            $this->chatOnline($w);
            $this->bossAt('2026-10-30 12:16:00');
            $res = $this->bossRequest('worldboss', 'index', $p['auth']);
            $this->assertSame(1, $res['status']);
            // 最后一击全服系统公告（get_message_str 12），只发一次
            $this->assertSame([[7, '{1,boss' . $uid . ' ,' . $uid . '} 将“刑天”最后击杀，奖励额外增加50%', 0, $uid]], $this->chatMessages($w));
            $this->bossRequest('worldboss', 'index', $q['auth']);
            $this->assertSame([], $this->chatMessages($w));
            $this->assertSame(['intnum' => 1500, 'lasthit' => 1, 'rank' => 1, 'hit' => $hit], $res['reward']);
            $this->assertSame([(string)$uid], array_column($res['top'], 'uid'));
            $this->assertSame(1, $this->bossRequest('member', 'index', $p['auth'])['openbossreward']);
            $this->assertSame(1, $this->bossRequest('activity', 'index', $p['auth'])['list']['boss']['popo']);

            $before = (int)(DB::table('member_integral')->where('uid', $uid)->value('act_arena_int') ?? 0);
            $this->assertSame(['status' => 1, 'reward' => ['intnum' => 1500]], $this->bossRequest('worldboss', 'reward', $p['auth']));
            $this->assertSame($before + 1500, (int)DB::table('member_integral')->where('uid', $uid)->value('act_arena_int'));
            $log = DB::table('integral_log')->where('uid', $uid)->orderBy('id', 'desc')->first();
            $this->assertSame([1, 1500, $before + 1500, 'worldboss', 'reward', 'act_arena_int'], [(int)$log['type'], (int)$log['intnum'], (int)$log['balance'], $log['class'], $log['method'], $log['argument']]);
            // 不能重复领取；之后显示「今日已参加」
            $this->assertSame(['status' => 2, 'reward' => []], $this->bossRequest('worldboss', 'reward', $p['auth']));
            $this->assertSame(5, $this->bossRequest('worldboss', 'index', $p['auth'])['status']);

            // 每人每天一场：12:00 场参加过的晚上仍按 12:00 场（已参加），没参加的进 20:00 场
            $this->bossAt('2026-10-30 20:05:00');
            $this->assertSame(5, $this->bossRequest('worldboss', 'index', $p['auth'])['status']);
            $this->assertSame(5, $this->bossRequest('worldboss', 'battle', $p['auth'])['status']);
            $this->assertSame(0, $this->bossRequest('member', 'index', $p['auth'])['openboss']);
            $this->assertSame(2, $this->bossRequest('worldboss', 'index', $q['auth'])['status']);
        } finally {
            $this->bossReset();
        }
    }

    public function testWorldBossCronAutoClaimsAndClears(): void
    {
        $this->bossReset();
        try {
            $a = $this->bossPlayer(60);
            $b = $this->bossPlayer(60);
            $c = $this->bossPlayer(60);
            // 20:00 场：c 已领奖（伤害记录已删），a 最后一击
            \Hanjiang\Model\WorldBoss::updateMyHit(1, $c['uid'], 900, 300);
            \Hanjiang\Model\WorldBoss::updateMyHit(1, $b['uid'], 500, 300);
            \Hanjiang\Model\WorldBoss::updateMyHit(1, $a['uid'], 800, 200);
            $this->assertTrue(\Hanjiang\Model\WorldBoss::delMyHit(1, $c['uid']));
            $intBefore = static fn (int $uid): int => (int)(DB::table('member_integral')->where('uid', $uid)->value('act_arena_int') ?? 0);
            [$ia, $ib, $ic] = [$intBefore($a['uid']), $intBefore($b['uid']), $intBefore($c['uid'])];

            // 次日 00:30：12:00 场没有排行，取 20:00 场；排名 c 1、a 2、b 3
            $now = (int)strtotime('2026-10-31 00:30:00');
            $this->assertSame(2, \Hanjiang\Cron\WorldBossCron::settle($now));
            $this->assertSame($ia + (int)(900 * 1.5), $intBefore($a['uid']));
            $this->assertSame($ib + 800, $intBefore($b['uid']));
            $this->assertSame($ic, $intBefore($c['uid']));
            $log = DB::table('integral_log')->where('uid', $a['uid'])->orderBy('id', 'desc')->first();
            $this->assertSame(['cron_acttask', 'worldboss', (int)(900 * 1.5)], [$log['class'], $log['method'], (int)$log['intnum']]);
            $mail = Redis::getJson('user_messages:' . $b['uid']);
            $last = end($mail);
            $this->assertSame([1, '系统通知', '亲爱的玩家！您上次参加世界BOSS获得积分800，系统已经帮您自动领取！'], [$last['type'], $last['fromname'], $last['message']]);
            // 清空全部世界 BOSS 键
            $this->assertSame([], Redis::keys('worldboss_*'));

            // 两场都没有排行：不发奖、不清空
            Redis::set('worldboss_played_' . $a['uid'], 1, 600);
            $this->assertSame(0, \Hanjiang\Cron\WorldBossCron::settle($now));
            $this->assertSame(1, Redis::get('worldboss_played_' . $a['uid']));

            // 12:00 场有排行时优先发 12:00 场，然后清空（含 20:00 场）
            \Hanjiang\Model\WorldBoss::updateMyHit(0, $a['uid'], 10, 100);
            \Hanjiang\Model\WorldBoss::updateMyHit(1, $b['uid'], 10, 100);
            $this->assertSame(1, \Hanjiang\Cron\WorldBossCron::settle($now));
            $this->assertSame(0, \Hanjiang\Model\WorldBoss::myHit(1, $b['uid']));
        } finally {
            $this->bossReset();
        }
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

        // private 方法与带其他参数的 public static 辅助方法不可路由，按未实现接口降级
        $this->assertSame(1, $this->request('pay', 'testEnabled')['is_fallback'] ?? 0);
        $this->assertSame(1, $this->request('copies', 'doPk')['is_fallback'] ?? 0);
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
        $this->withFreshPlayer(function (int $uid): void {
            // member_field.first_recharge 默认 1；强制引导期间（guidestop = 0）有图标时为 3
            $this->assertSame(3, $this->request('member', 'index')['first_recharge']);

            $res = $this->request('first_recharge', 'index');
            $this->assertSame(1, $res['status']);
            $this->assertSame([1, 2, 3, 4, 5, 6], array_column($res['list'], 'id'), '按 gold 降序');
            $this->assertSame([0, 0, 0, 0, 0, 0], array_column($res['list'], 'status'));
            $this->assertSame('首充6480元宝，赠送<em class="red">6480</em>元宝(额度<em class="red">100%</em>)，可开启一次<em class="red">天命转盘</em>', $res['list'][0]['name']);
            $this->assertSame('首充300元宝，赠送<em class="red">300</em>元宝(额度<em class="red">100%</em>)', $res['list'][4]['name']);
            $android = $this->request('first_recharge', 'index', ['platform' => 'android']);
            $this->assertSame('首充300元宝，赠送[color=ffff0000]300[/color]元宝(额度[color=ffff0000]100%[/color])', $android['list'][4]['name']);

            // 没有充值记录
            $this->assertSame(2, $this->request('first_recharge', 'get_reward', ['id' => 5])['status']);
            $this->assertSame(-2, $this->request('first_recharge', 'get_reward', ['id' => 0])['status']);
            $this->assertSame(3, $this->request('first_recharge', 'action')['status']);

            // 充值 30 元 → 首充 300 元宝（旧版 vip_model::first_recharge），图标状态 2
            $this->assertTrue(\Hanjiang\Model\FirstRechargeLog::recordRecharge($uid, 30));
            $this->assertFalse(\Hanjiang\Model\FirstRechargeLog::recordRecharge($uid, 648), '只记第一次');
            $this->assertSame(2, (int)DB::table('member_field')->where('uid', $uid)->first()['first_recharge']);
            $this->assertSame([0, 0, 0, 0, 1, 0], array_column($this->request('first_recharge', 'index')['list'], 'status'));

            $this->assertSame(5, $this->request('first_recharge', 'get_reward', ['id' => 6])['status'], '档位错误');
            Redis::set('first_reward_lock_' . $uid, 1, 30);
            $this->assertSame(6, $this->request('first_recharge', 'get_reward', ['id' => 5])['status'], '操作太频繁');
            Redis::del('first_reward_lock_' . $uid);

            // 档位 5：元宝 300、银币 800000、突飞卡 100、黄宝石 100（MasterData FirstRecharge 5）
            $member = DB::table('member')->where('uid', $uid)->first();
            $field = DB::table('member_field')->where('uid', $uid)->first();
            $jade = \Hanjiang\Model\TraderLog::get($uid);
            $reward = $this->request('first_recharge', 'get_reward', ['id' => 5]);
            $this->assertSame(1, $reward['status']);
            $this->assertSame([
                'gold' => 300,
                'all_gold' => (int)$member['gold'] + 300,
                'silver' => 800000,
                'all_silver' => (int)$member['silver'] + 800000,
                'jadetype' => 2,
                'jadenum' => 100,
            ], $reward['info']);
            $after = DB::table('member')->where('uid', $uid)->first();
            $this->assertSame((int)$member['gold'] + 300, (int)$after['gold']);
            $this->assertSame((int)$member['silver'] + 800000, (int)$after['silver']);
            $this->assertSame((int)$field['freeleap'] + 100, (int)DB::table('member_field')->where('uid', $uid)->first()['freeleap']);
            $this->assertSame((int)($jade['get2'] ?? 0) + 100, \Hanjiang\Model\TraderLog::jade($uid, 2));
            $this->assertSame([0, 0, 0, 0, 2, 0], array_column($reward['list'], 'status'));
            $this->assertSame(5, (int)DB::table('first_recharge_log')->where('uid', $uid)->first()['status']);
            // 不是最高档：图标不再显示
            $this->assertSame(0, (int)DB::table('member_field')->where('uid', $uid)->first()['first_recharge']);
            $this->assertSame(3, $this->request('first_recharge', 'get_reward', ['id' => 5])['status'], '已领取');
            $this->assertSame([], DB::table('general')->where('uid', $uid)->where('generalid', 6)->get(), '不再送赵云');

            // 天命转盘：首充未达最高档不能抽
            $lottery = $this->request('first_recharge', 'lottery');
            $this->assertSame(1, $lottery['status']);
            $this->assertSame(0, $lottery['lottery']);
            $this->assertCount(12, $lottery['list']);
            $tpl = \Hanjiang\MasterData\General::ROWS[7];
            $this->assertSame($tpl['name'], $lottery['list'][0]['name']);
            $this->assertSame(\Hanjiang\MasterData\Talent::ROWS[$tpl['talentid']]['name'], $lottery['list'][0]['talent']);
            $this->assertSame(\Hanjiang\MasterData\Skill::ROWS[$tpl['skillid']]['description'], $lottery['list'][0]['skill_desc']);
            $this->assertSame(4, $this->request('first_recharge', 'action')['status']);

            // 首充达到最高档后可抽一次；抽中的格由 mt_rand(1, 总权重) 落点决定
            DB::table('first_recharge_log')->where('uid', $uid)->update(['gold' => 6480]);
            $this->assertSame(1, $this->request('first_recharge', 'lottery')['lottery']);
            mt_srand(20261031);
            $expected = \Hanjiang\Handler\Activity\FirstRechargeHandler::lotteryRow(mt_rand(1, \Hanjiang\Handler\Activity\FirstRechargeHandler::lotteryTotalRate()));
            $w = $this->chatPlayer();
            $this->chatOnline($w);
            mt_srand(20261031);
            $draw = $this->request('first_recharge', 'action');
            $this->assertSame(1, $draw['status']);
            $this->assertSame(1, $draw['info']['gettype']);
            $this->assertSame($expected['getvalue'], $draw['info']['id']);
            // 7 星以上武将：小喇叭（general_firstrecharge_get）+ 聊天室系统消息（get_message_str 35）
            $gen = \Hanjiang\MasterData\General::ROWS[$expected['getvalue']];
            $nick = (string)DB::table('member')->where('uid', $uid)->value('nickname');
            $num = \Hanjiang\Model\Chat::starWord($gen['star']);
            $this->assertSame($num === null ? [] : [
                [2, '恭喜玩家【' . $nick . '】在天命转盘中获得' . $num . '星武将-' . $gen['name'], 0, $uid],
                [7, '恭喜【{1,' . $nick . ' ,' . $uid . '}】在天命转盘获得' . $num . '星武将-{9,' . $gen['name'] . '}', 0, $uid],
            ], $this->chatMessages($w));
            $this->assertNotNull(DB::table('general')->where('uid', $uid)->where('generalid', $expected['getvalue'])->whereNull('deleted_at')->first());
            $this->assertSame(1, (int)DB::table('first_recharge_log')->where('uid', $uid)->first()['lottery']);
            $this->assertSame(5, $this->request('first_recharge', 'action')['status'], '只能抽一次');

            // 抽到已拥有的武将：按酒馆紫宝石价格 * 0.8 返还紫宝石
            DB::table('first_recharge_log')->where('uid', $uid)->update(['lottery' => 0]);
            $purple = \Hanjiang\Model\TraderLog::jade($uid, 3);
            mt_srand(20261031);
            $again = $this->request('first_recharge', 'action');
            $this->assertSame(1, $again['status']);
            $refund = (int)(\Hanjiang\MasterData\Pub::ROWS[$expected['getvalue']]['price'] * 0.8);
            $this->assertSame(3, $again['info']['jadetype']);
            $this->assertEquals($refund, $again['info']['jadenum']);
            $this->assertSame($purple + $refund, \Hanjiang\Model\TraderLog::jade($uid, 3));
        });
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
        // 旧版 controllers/studfarm.php：三档取 MountRatio，花黄 / 紫宝石（trader_log.get2 / get3），首次必得最高品级
        $this->withFreshPlayer(function (int $uid): void {
            $this->assertSame(2, $this->request('studfarm', 'index')['status'], '120 级以下未开启');
            DB::table('member')->where('uid', $uid)->update(['level' => 120]);

            $index = $this->request('studfarm', 'index');
            $this->assertSame([1, 0, 0, 0], [$index['status'], $index['jade1'], $index['jade2'], $index['jade3']]);
            $this->assertSame([1, 2, 3], array_column($index['list'], 'id'));
            $this->assertSame([1, 1, 1, 1, 0, 0, 0], array_map(static fn (int $i): int => $index['list'][0]['rate' . $i], range(1, 7)), '权重大于 0 显示为 1');
            $this->assertSame(500, $index['list'][0]['price']);

            $this->assertSame(-1, $this->request('studfarm', 'action', ['id' => 9, 'new' => 1])['status']);
            $this->assertSame(-3, $this->request('studfarm', 'action', ['id' => 1])['status']);
            $this->assertSame(-2, $this->request('studfarm', 'action', ['id' => 1, 'new' => 1])['status'], '黄宝石不足');

            \Hanjiang\Model\TraderLog::addJade($uid, 2, 1200);
            \Hanjiang\Model\TraderLog::addJade($uid, 3, 100);
            $this->assertSame(-2, $this->request('studfarm', 'action', ['id' => 3, 'new' => 1])['status'], '紫宝石不足');

            // 首次十里挑一：必得 4 品（金）坐骑（模板 7 ~ 10），记 mount_log.first1
            $first = $this->request('studfarm', 'action', ['id' => 1, 'new' => 1]);
            $this->assertSame([1, 1, 700, 100], [$first['status'], $first['first'], $first['jade2'], $first['jade3']]);
            $this->assertSame(4, $first['info']['quality']);
            $this->assertContains($first['info']['mid'], [7, 8, 9, 10]);
            $this->assertSame(1, DB::table('own_mount')->where('uid', $uid)->where('mid', $first['info']['mid'])->whereNull('deleted_at')->count());
            $log = DB::table('mount_log')->where('uid', $uid)->first();
            $this->assertGreaterThan(0, (int)$log['first1']);
            $this->assertSame([0, 0], [(int)$log['first2'], (int)$log['first3']]);
            $jadeLog = DB::table('tavernjade_log')->where('uid', $uid)->where('type', 2)->first();
            $this->assertSame([2, 500, 700, 'studfarm', 'action'], [(int)$jadeLog['jadetype'], (int)$jadeLog['jade'], (int)$jadeLog['balance'], $jadeLog['class'], $jadeLog['method']]);

            // 之后按 rate1 ~ rate7 权重抽品级，再在该品级模板（id < 100）里等概率抽
            mt_srand(20261030);
            $rate = mt_rand(1, 100);
            $quality = $rate <= 20 ? 1 : ($rate <= 50 ? 2 : ($rate <= 80 ? 3 : 4));
            $pool = array_values(array_filter(\Hanjiang\MasterData\MountTemp::ROWS, static fn (array $t): bool => $t['quality'] === $quality && $t['id'] < 100));
            $expect = $pool[mt_rand(1, count($pool)) - 1]['id'];
            mt_srand(20261030);
            $again = $this->request('studfarm', 'action', ['id' => 1, 'new' => 1]);
            mt_srand();
            $this->assertSame([1, 0, 200], [$again['status'], $again['first'], $again['jade2']]);
            $this->assertSame([$expect, $quality], [$again['info']['mid'], $again['info']['quality']]);
            $this->assertSame(-2, $this->request('studfarm', 'action', ['id' => 1, 'new' => 1])['status']);

            // 首次万里挑一必得 6 品：5 品以上发聊天室系统消息（get_message_str 28）与小喇叭（chat_mount_get）
            $w = $this->chatPlayer();
            $this->chatOnline($w);
            \Hanjiang\Model\TraderLog::addJade($uid, 3, 100000);
            $top = $this->request('studfarm', 'action', ['id' => 3, 'new' => 1]);
            $this->assertSame([1, 6], [$top['status'], $top['info']['quality']]);
            $nick = (string)DB::table('member')->where('uid', $uid)->value('nickname');
            $mount = '<em class="q6">' . \Hanjiang\MasterData\MountTemp::ROWS[$top['info']['mid']]['name'] . '</em>';
            $this->assertSame([
                [7, '恭喜【{1,' . $nick . ' ,' . $uid . '}】在西域马场选中名马-' . $mount . '，真乃当世雄主', 0, $uid],
                [2, '恭喜【' . $nick . '】在西域马场选中名马-' . $mount . '，真乃当世雄主', 0, $uid],
            ], $this->chatMessages($w));
        });
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

    /**
     * @return array{uid: int, auth: array{token: string, uid: int}}
     */
    /**
     * 聊天室：清空全部聊天 Redis 键（在线标记、队列、屏蔽、发言间隔）
     */
    private function chatReset(): void
    {
        $prefix = (string)Redis::instance()->getOption(\Redis::OPT_PREFIX);
        foreach (Redis::keys('chat_*') as $key) {
            Redis::del(substr($key, strlen($prefix)));
        }
    }

    /**
     * 聊天室：50 级可发言的新玩家
     *
     * @return array{uid: int, auth: array{token: string, uid: int}}
     */
    private function chatPlayer(): array
    {
        $p = $this->newGuestPlayer();
        DB::table('member')->where('uid', $p['uid'])->update(['level' => 50]);
        return $p;
    }

    /**
     * 聊天室发言（c=chat&m=send）；默认世界频道（不带 uid），默认先清掉 3 秒发言间隔
     *
     * @param array{uid: int, auth: array<string, mixed>} $p
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function chatSend(array $p, string $message, array $extra = [], bool $clearDelay = true): array
    {
        if ($clearDelay) {
            Redis::del('chat_lock_' . $p['uid']);
        }
        return $this->request('chat', 'send', array_merge($p['auth'], ['uid' => '', 'message' => $message], $extra));
    }

    /**
     * 聊天室：把玩家加入国家（member.country + member_country 成员表；用不存在的国家 id，不影响军团用例）
     *
     * @param array{uid: int} $p
     */
    private function chatJoinCountry(array $p, int $country): void
    {
        DB::table('member')->where('uid', $p['uid'])->update(['country' => $country]);
        DB::table('member_country')->insert(['uid' => $p['uid'], 'country' => $country]);
    }

    /**
     * GameWS 鉴权（auth），返回该连接的收件箱（之后推给他的帧都追加到这里）
     *
     * @param array<string, mixed> $auth
     */
    private function chatWsAuth(array $auth): \OmniPHP\WebSocket\Context
    {
        $ctx = \OmniPHP\WebSocket\Context::mock(['action' => 'auth', 'token' => $auth['token'] ?? '']);
        \Hanjiang\WebSocket\WebSocketHandler::dispatch($ctx);
        return $ctx;
    }

    /**
     * 取出并清空收到的帧：[[事件名, 载荷], ...]
     *
     * @return list<array{0: string, 1: mixed}>
     */
    private function chatFrames(\OmniPHP\WebSocket\Context $inbox): array
    {
        $frames = array_map(static function (string $raw): array {
            $frame = json_decode($raw, true);
            return [$frame['type'], $frame['data']];
        }, $inbox->sentMessages);
        $inbox->sentMessages = [];
        return $frames;
    }

    /**
     * 聊天室：玩家上线（同旧版轮询 c=chat&m=read 刷新在线标记 chat_online_{uid}），并取走积压的消息
     *
     * @param array{uid: int, auth: array<string, mixed>} $p
     */
    private function chatOnline(array $p): void
    {
        $this->assertSame(1, $this->request('chat', 'read', $p['auth'])['status']);
    }

    /**
     * 聊天室：取走玩家私人队列里的消息（c=chat&m=read），返回 [type, message, id, uid] 列表
     *
     * @param array{uid: int, auth: array<string, mixed>} $p
     * @return list<array{0: int, 1: string, 2: mixed, 3: int}>
     */
    private function chatMessages(array $p): array
    {
        $res = $this->request('chat', 'read', $p['auth']);
        $this->assertSame(1, $res['status']);
        return array_map(static fn(array $m): array => [$m['type'], $m['message'], $m['id'], $m['uid']], $res['list']);
    }

    /**
     * 聊天室消息里的玩家标签（get_message_str 的 from / to：{1,昵称 ,uid}）
     *
     * @param array{uid: int} $p
     */
    private function chatFrom(array $p): string
    {
        return '{1,' . DB::table('member')->where('uid', $p['uid'])->value('nickname') . ' ,' . $p['uid'] . '}';
    }

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


    /**
     * @param array<string, mixed> $params
     */
    private function eliteRequest(string $method, array $params = []): array
    {
        $res = $this->request('elite', $method, $params);
        unset($res['message'], $res['msg']);
        return $res;
    }

    /**
     * 多人副本开战核心 EliteHandler::fight（旧版实时服务器以 HTTP 调 c=elite&m=action 的参数：token 为调用者，
     * fid / teamid / topteam / leader / members）；新版 HTTP action 不开放，只由 EliteRoom 的 startfight 调用
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function eliteFight(array $params): array
    {
        $self = MemberHandler::resolveUid(Context::mock(['token' => $params['token'] ?? '']));
        return \Hanjiang\Handler\Activity\EliteHandler::fight(
            $self,
            (int)($params['fid'] ?? 0),
            (int)($params['teamid'] ?? 0),
            (int)($params['topteam'] ?? 0),
            (int)($params['leader'] ?? 0),
            (string)($params['members'] ?? ''),
        );
    }

    /**
     * 旧版直接 json_encode 非数组返回值的接口（join_team / get_country_info）：返回响应体原文
     *
     * @param array<string, mixed> $params
     */
    private function eliteRawRequest(string $method, array $params): string
    {
        $res = self::$router->handleContext(Context::mock(array_merge(['c' => 'elite', 'm' => $method], $params)));
        $this->assertInstanceOf(WorkermanResponse::class, $res);
        return (string)$res->rawBody();
    }

    /**
     * 主线通关到某关（新版 zc_sanguo_map：该关所在势力 / 场景一行，mission 为已通关据点）
     */
    private function eliteMissionProgress(int $uid, int $missionId): void
    {
        $m = \Hanjiang\MasterData\Mission::ROWS[$missionId];
        DB::table('map')->where('uid', $uid)->delete();
        DB::table('map')->insert(['uid' => $uid, 'level' => $m['missionlevel'], 'stage' => $m['missionstage'], 'mission' => $m['missionid']]);
    }

    /**
     * 多人副本玩家：军团 1 已开启（主线打通第 50 关，即军团 1 的最后一关），已建活动记录
     *
     * @return array{uid: int, auth: array<string, mixed>}
     */
    private function elitePlayer(bool $overpower = true): array
    {
        $p = $this->newGuestPlayer();
        $this->eliteMissionProgress($p['uid'], 50);
        if ($overpower) {
            // 主公与武将同为 20000 级：胜负不依赖随机数，且武将等级不超过主公（add_exp 才会加经验）
            DB::table('member')->where('uid', $p['uid'])->update(['level' => 20000]);
            DB::table('general')->where('uid', $p['uid'])->update(['level' => 20000, 'goout' => 1]);
        } else {
            $this->emptyFormation($p['uid']);
        }
        \Hanjiang\Model\MemberActivity::ensure($p['uid']);
        return $p;
    }

    /**
     * @return array<string, int>
     */
    private function eliteActivity(int $uid): array
    {
        $row = DB::table('member_activity')->select('elitetimes', 'elitesurplustimes', 'elitebuytimes', 'elitebuydateline')->where('uid', $uid)->first();
        return array_map('intval', $row);
    }

    public function testEliteIndexListsLegacyCities(): void
    {
        $p = $this->newGuestPlayer();
        $res = $this->eliteRequest('index', $p['auth']);
        $this->assertSame(1, $res['status']);
        $this->assertSame(['status', 'list'], array_keys($res));
        $cities = \Hanjiang\MasterData\EliteCity::ROWS;
        $this->assertCount(count($cities), $res['list']);
        $equip = \Hanjiang\MasterData\Equip::ROWS;

        // 军团 1：装备碎片（掉率 80 → getrate 2），首杀送整件装备
        $c1 = $cities[1];
        $this->assertSame($c1 + [
            'getrate' => 2,
            'itemname' => $equip[$c1['get_value']]['name'] . '碎片',
            'quality' => $equip[$c1['get_value']]['quality'],
            'firstitemname' => $equip[$c1['first_value']]['name'],
            'firstquality' => $equip[$c1['first_value']]['quality'],
        ], $res['list'][0]);

        // 天赋卷轴：卷轴类型名 + 「天赋卷轴」；掉率 100 按旧版落到 else 分支，getrate 1
        $scroll = \Hanjiang\MasterData\TalentScroll::ROWS[$cities[18]['get_value']];
        $names = [1 => '物攻', 2 => '物防', 3 => '策攻', 4 => '策防', 5 => '生命'];
        $c18 = $res['list'][17];
        $this->assertSame([18, 1, $names[$scroll['type']] . '天赋卷轴', $scroll['quality']], [$c18['id'], $c18['getrate'], $c18['itemname'], $c18['quality']]);
        $this->assertSame($names[$scroll['type']] . '天赋卷轴', $c18['firstitemname']);
        // 天赋卷轴碎片
        $c26 = $res['list'][25];
        $scroll26 = \Hanjiang\MasterData\TalentScroll::ROWS[$cities[26]['get_value']];
        $this->assertSame($names[$scroll26['type']] . '天赋卷轴碎片', $c26['itemname']);
        // 掉率 <= 50 为 getrate 1
        $this->assertSame([40, 1], [$res['list'][3]['get_rate'], $res['list'][3]['getrate']]);
    }

    public function testEliteMemberInfoOpenCitiesFirstKillAndTimes(): void
    {
        $p = $this->newGuestPlayer();
        $uid = $p['uid'];
        $this->assertNull(DB::table('member_activity')->where('uid', $uid)->first());

        // 没有主线进度：全部军团未开启、全部首杀；首次读活动信息按旧版建档（多人副本 10 次、将魂星路 12 次）
        $res = $this->eliteRequest('get_member_info', $p['auth']);
        $this->assertSame(['status', 'countryname', 'supper_group', 'number', 'list'], array_keys($res));
        $this->assertSame(1, $res['status']);
        $this->assertSame(0, $res['countryname']);
        $this->assertSame(\Hanjiang\MasterData\ElitePrice::ROWS[1], $res['supper_group']);
        $this->assertSame(10, $res['number']);
        $this->assertCount(83, $res['list']);
        $this->assertSame(['open' => 0, 'firstkill' => 1], $res['list'][1]);
        $act = DB::table('member_activity')->where('uid', $uid)->first();
        $this->assertSame([10, 0, 0, 0, 12, 1], [(int)$act['elitetimes'], (int)$act['elitesurplustimes'], (int)$act['elitebuytimes'], (int)$act['elitebuydateline'], (int)$act['freetower'], (int)$act['towerlevel']]);

        // 主线打到第 49 关：军团 1 还有第 50 关没过，不开启
        $this->eliteMissionProgress($uid, 49);
        $this->assertSame(0, $this->eliteRequest('get_member_info', $p['auth'])['list'][1]['open']);
        // 打通第 50 关：军团 1 开启，军团 2 未开启
        $this->eliteMissionProgress($uid, 50);
        $res = $this->eliteRequest('get_member_info', $p['auth']);
        $this->assertSame([1, 0], [$res['list'][1]['open'], $res['list'][2]['open']]);

        // 已首杀的军团 firstkill 0；次数 = 免费 + 购买剩余
        DB::table('elite_mission_log')->insert(['missionid' => 1, 'uid' => $uid, 'dateline' => time(), 'first_reward' => 1]);
        DB::table('member_activity')->where('uid', $uid)->update(['elitetimes' => 3, 'elitesurplustimes' => 2]);
        $res = $this->eliteRequest('get_member_info', $p['auth']);
        $this->assertSame(['open' => 1, 'firstkill' => 0], $res['list'][1]);
        $this->assertSame(['open' => 0, 'firstkill' => 1], $res['list'][2]);
        $this->assertSame(5, $res['number']);

        // 加入国家返回国家名称
        $name = '精' . substr((string)hrtime(true), -6);
        $countryId = (int)DB::table('country')->insertGetId(['name' => $name, 'member' => 1, 'uid' => $uid, 'nickname' => 'k']);
        DB::table('member')->where('uid', $uid)->update(['country' => $countryId]);
        $this->assertSame($name, $this->eliteRequest('get_member_info', $p['auth'])['countryname']);
    }

    public function testEliteJoinTeamAndCountryInfoRawOutput(): void
    {
        $p = $this->newGuestPlayer();
        // 没有国家：get_country_info 输出空数组；join_team 只有国家参数缺失时（旧版 null == false）为 1
        $this->assertSame('[]', $this->eliteRawRequest('get_country_info', $p['auth']));
        $this->assertSame('0', $this->eliteRawRequest('join_team', $p['auth'] + ['country' => 3]));
        $this->assertSame('1', $this->eliteRawRequest('join_team', $p['auth']));

        $countryId = (int)DB::table('country')->insertGetId(['name' => '团' . substr((string)hrtime(true), -6), 'member' => 1, 'uid' => $p['uid'], 'nickname' => 'k']);
        DB::table('member')->where('uid', $p['uid'])->update(['country' => $countryId]);
        $this->assertSame('1', $this->eliteRawRequest('join_team', $p['auth'] + ['country' => (string)$countryId]));
        $this->assertSame('0', $this->eliteRawRequest('join_team', $p['auth'] + ['country' => $countryId + 1]));
        $info = json_decode($this->eliteRawRequest('get_country_info', $p['auth']), true);
        $this->assertSame($countryId, (int)$info['id']);
        $this->assertArrayNotHasKey('status', $info);
    }

    public function testEliteGetServerOpenHours(): void
    {
        $at = static fn (string $hm): int => (int)strtotime('2026-10-30 ' . $hm . ':00');
        foreach (['11:59' => false, '12:00' => true, '14:00' => true, '14:01' => false, '18:59' => false, '19:00' => true, '21:00' => true, '21:01' => false] as $hm => $open) {
            $this->assertSame($open, \Hanjiang\Handler\Activity\EliteHandler::isOpen($at($hm)), $hm);
        }

        $p = $this->newGuestPlayer();
        $now = (int)date('Hi');
        $res = $this->eliteRequest('get_server', $p['auth']);
        if (($now >= 1200 && $now <= 1400) || ($now >= 1900 && $now <= 2100)) {
            $config = require dirname(__DIR__, 2) . '/config/app.php';
            $this->assertSame(['status' => 1, 'url' => $config['socket_server_url']], $res);
        } else {
            $this->assertSame(['status' => -1], $res);
        }
    }

    public function testEliteBuyNumber(): void
    {
        $p = $this->newGuestPlayer();
        $uid = $p['uid'];
        $price = static fn (int $n): int => (int)\Hanjiang\MasterData\Price::ROWS[min(50, $n)]['elite'];

        // 只查价格：首次建档
        $this->assertSame(['status' => 1, 'price' => $price(1), 'times' => 10], $this->eliteRequest('buy_number', $p['auth'] + ['t' => 0]));

        DB::table('member')->where('uid', $uid)->update(['gold' => 100]);
        $res = $this->eliteRequest('buy_number', $p['auth'] + ['t' => 1]);
        $this->assertSame(['status' => 1, 'price' => $price(2), 'times' => 11, 'all_gold' => 100 - $price(1)], $res);
        $act = $this->eliteActivity($uid);
        $this->assertSame([10, 1, 1], [$act['elitetimes'], $act['elitesurplustimes'], $act['elitebuytimes']]);
        $this->assertGreaterThanOrEqual(strtotime('today'), $act['elitebuydateline']);
        $this->assertSame($price(2), $this->eliteRequest('buy_number', $p['auth'] + ['t' => 0])['price']);

        // 跨天：今日购买次数从 0 算起（不归零字段，按最后购买时间判断）
        DB::table('member_activity')->where('uid', $uid)->update(['elitebuytimes' => 7, 'elitebuydateline' => strtotime('yesterday')]);
        $this->assertSame($price(1), $this->eliteRequest('buy_number', $p['auth'] + ['t' => 0])['price']);
        // 第 50 次起固定按第 50 次
        DB::table('member_activity')->where('uid', $uid)->update(['elitebuytimes' => 60, 'elitebuydateline' => time()]);
        $this->assertSame($price(50), $this->eliteRequest('buy_number', $p['auth'] + ['t' => 0])['price']);

        // 元宝不足：-1，带价格与次数，不改次数
        DB::table('member')->where('uid', $uid)->update(['gold' => $price(50) - 1]);
        $this->assertSame(['status' => -1, 'price' => $price(50), 'times' => 11], $this->eliteRequest('buy_number', $p['auth'] + ['t' => 1]));
        $this->assertSame([1, 60], [$this->eliteActivity($uid)['elitesurplustimes'], $this->eliteActivity($uid)['elitebuytimes']]);
        $this->assertSame($price(50) - 1, (int)DB::table('member')->where('uid', $uid)->value('gold'));
    }

    public function testEliteSendInvite(): void
    {
        $p = $this->newGuestPlayer();
        // 旧版参数缺失返回 1
        $this->assertSame(['status' => 1], $this->eliteRequest('sendinvite', $p['auth'] + ['fid' => 1]));
        $this->assertSame(['status' => -2], $this->eliteRequest('sendinvite', $p['auth'] + ['fid' => 99, 'teamid' => 5]));
        // 军团未开启
        $this->assertSame(['status' => -2], $this->eliteRequest('sendinvite', $p['auth'] + ['fid' => 1, 'teamid' => 5]));
        // 全服系统消息：组队邀请（get_message_str 11，前端 {12,fid|teamid|countryid} 解析为「加入队伍」）
        $w = $this->newGuestPlayer();
        $this->chatOnline($w);
        $this->eliteMissionProgress($p['uid'], 50);
        $this->assertSame(['status' => 1], $this->eliteRequest('sendinvite', $p['auth'] + ['fid' => 1, 'teamid' => 5, 'countryid' => 3]));
        $city = \Hanjiang\MasterData\EliteCity::ROWS[1]['name'];
        $this->assertSame([[7, $this->chatFrom($p) . '准备攻打{9,' . $city . '}，天下英雄们速来协助 {12,1|5|3}', 0, $p['uid']]], $this->chatMessages($w));
    }

    public function testEliteActionGuards(): void
    {
        $a = $this->elitePlayer();
        $b = $this->elitePlayer();
        $members = "{$a['uid']},{$b['uid']}";
        $base = $a['auth'] + ['fid' => 1, 'teamid' => 3, 'leader' => $a['uid'], 'members' => $members];

        $this->assertSame(['status' => -1, 'info' => []], $this->eliteFight(['members' => ''] + $base));
        $this->assertSame(['status' => -1, 'info' => []], $this->eliteFight(['leader' => 0] + $base));
        // 自己不能和自己组队
        $this->assertSame(['status' => 0, 'info' => []], $this->eliteFight(['members' => "{$a['uid']},{$a['uid']}"] + $base));

        // 次数不足（含没有活动记录的玩家）返回该玩家 uid
        DB::table('member_activity')->where('uid', $b['uid'])->update(['elitetimes' => 0, 'elitesurplustimes' => 0]);
        $this->assertSame(['status' => -2, 'info' => [], 'uid' => (string)$b['uid']], $this->eliteFight($base));
        $c = $this->newGuestPlayer();
        $this->assertSame(['status' => -2, 'info' => [], 'uid' => (string)$c['uid']], $this->eliteFight(['members' => "{$a['uid']},{$c['uid']}"] + $base));
        DB::table('member_activity')->where('uid', $b['uid'])->update(['elitesurplustimes' => 1]);

        // 任一队员没开启该军团
        $this->assertSame(['status' => -4, 'info' => []], $this->eliteFight(['fid' => 2] + $base));
        $this->eliteMissionProgress($b['uid'], 49);
        $this->assertSame(['status' => -4, 'info' => []], $this->eliteFight($base));
        $this->eliteMissionProgress($b['uid'], 50);

        // 至尊组队：队长 VIP 8 以上、元宝够
        DB::table('member')->where('uid', $a['uid'])->update(['vip' => 7, 'gold' => 1000]);
        $this->assertSame(['status' => -7, 'info' => []], $this->eliteFight(['topteam' => 1] + $base));
        DB::table('member')->where('uid', $a['uid'])->update(['vip' => 8, 'gold' => \Hanjiang\MasterData\ElitePrice::ROWS[1]['gold'] - 1]);
        $this->assertSame(['status' => -6, 'info' => []], $this->eliteFight(['topteam' => 1] + $base));

        // 以上都不扣次数、不记战斗记录
        $this->assertSame([10, 0], [$this->eliteActivity($a['uid'])['elitetimes'], $this->eliteActivity($b['uid'])['elitetimes']]);
        $this->assertSame(0, DB::table('elite_log')->where('leader', $a['uid'])->count());

        // 同一队长 10 秒内不能再次开战
        $this->assertSame(1, $this->eliteFight($base)['status']);
        $this->assertSame([9, 0, 0], [$this->eliteActivity($a['uid'])['elitetimes'], $this->eliteActivity($b['uid'])['elitetimes'], $this->eliteActivity($b['uid'])['elitesurplustimes']]);
        DB::table('member_activity')->where('uid', $b['uid'])->update(['elitetimes' => 1]);
        $this->assertSame(['status' => -8, 'info' => []], $this->eliteFight($base));
        DB::table('elite_log')->where('leader', $a['uid'])->update(['dateline' => time() - 11]);
        $this->assertSame(1, $this->eliteFight($base)['status']);
    }

    public function testEliteActionLoseStructure(): void
    {
        // 阵法里没有武将：旧版 init_member_team_info 返回 -2、空队伍上场，每场直接大败（不消耗随机数），结果确定
        $a = $this->elitePlayer(false);
        $b = $this->elitePlayer(false);
        $c = $this->elitePlayer(false);
        DB::table('member_activity')->where('uid', $c['uid'])->update(['elitetimes' => 0, 'elitesurplustimes' => 2]);
        $members = "{$a['uid']},{$b['uid']},{$c['uid']}";
        $res = $this->eliteFight($a['auth'] + ['fid' => 1, 'teamid' => 9, 'leader' => $a['uid'], 'members' => $members]);

        $this->assertSame(['status', 'info', 'battle'], array_keys($res));
        $this->assertSame(1, $res['status']);
        // 战败：每人只有剩余次数（先扣免费次数，没有免费次数扣购买剩余）
        $this->assertSame([(string)$a['uid'] => ['number' => 9], (string)$b['uid'] => ['number' => 9], (string)$c['uid'] => ['number' => 1], 'win' => 2, 'teamid' => 9], $res['info']);
        $this->assertSame([9, 0], [$this->eliteActivity($a['uid'])['elitetimes'], $this->eliteActivity($a['uid'])['elitesurplustimes']]);
        $this->assertSame([0, 1], [$this->eliteActivity($c['uid'])['elitetimes'], $this->eliteActivity($c['uid'])['elitesurplustimes']]);

        $battle = $res['battle'];
        $this->assertSame(['win', 'battle_ready', 'battle_log', 'kill_rank', 'attack_name', 'defense_name'], array_keys($battle));
        $this->assertSame(2, $battle['win']);
        // 守军：军团 1 的关卡按 id 顺序出场（45、46、47 首发，48~50 后备）
        $missions = array_values(array_filter(\Hanjiang\MasterData\EliteMission::ROWS, static fn (array $m): bool => $m['city'] === 1));
        $this->assertSame([45, 46, 47, 48, 49, 50], array_column($missions, 'id'));
        $this->assertSame(array_combine(array_map('strval', array_column($missions, 'id')), array_column($missions, 'name')), $battle['defense_name']);
        $nick = static fn (int $uid): string => (string)DB::table('member')->where('uid', $uid)->value('nickname');
        $this->assertSame([(string)$a['uid'] => $nick($a['uid']), (string)$b['uid'] => $nick($b['uid']), (string)$c['uid'] => $nick($c['uid'])], $battle['attack_name']);

        $monsters = \Hanjiang\MasterData\EliteMonster::ROWS;
        $images = static function (array $mission) use ($monsters): array {
            $matrix = [];
            for ($i = 1; $i <= 9; $i++) {
                if ($mission['position' . $i] > 0) {
                    $matrix[(string)$i] = $monsters[$mission['position' . $i]]['image'];
                }
            }
            return $matrix;
        };
        // 团战怪物血量不乘天赋血量系数（旧版 battle_rush 不经 battle_mission）
        $blood = static function (array $mission) use ($monsters): int {
            $sum = 0;
            for ($i = 1; $i <= 9; $i++) {
                if ($mission['position' . $i] > 0) {
                    $sum += $monsters[$mission['position' . $i]]['blood'];
                }
            }
            return $sum;
        };
        $uids = [1 => $a['uid'], 2 => $b['uid'], 3 => $c['uid']];
        $ready = [];
        foreach ($uids as $line => $uid) {
            $ready[(string)$line] = [
                'left' => ['matrix' => [], 'allblood' => 0, 'uid' => (string)$uid],
                'right' => ['matrix' => $images($missions[$line - 1]), 'allblood' => 0, 'uid' => $missions[$line - 1]['id']],
            ];
        }
        $this->assertSame($ready, $battle['battle_ready']);

        // 一轮：三路空队伍各自大败；守方连胜 1 场，后备守军不上场
        $this->assertCount(1, $battle['battle_log']);
        $round = [];
        foreach ($uids as $line => $uid) {
            $m = $missions[$line - 1];
            $round[(string)$line] = [
                'left' => ['matrix' => [], 'uid' => (string)$uid, 'blood' => 0, 'allblood' => 0],
                'right' => ['matrix' => $images($m), 'uid' => $m['id'], 'blood' => $blood($m), 'allblood' => $blood($m)],
                'assess' => -3,
                'process' => ['1' => ['dead_left' => [], 'dead_right' => [], 'hit_left' => 0, 'hit_right' => 0]],
                'left_win' => 0,
                'right_win' => 1,
            ];
        }
        $this->assertSame($round, $battle['battle_log'][0]);
        $this->assertSame(['left' => [], 'right' => ['45' => 1, '46' => 1, '47' => 1]], $battle['kill_rank']);

        // 战斗记录：队员原样、奖励为空、times 按旧版 int 列为 0
        $log = DB::table('elite_log')->where('leader', $a['uid'])->first();
        $this->assertSame([1, $members, 0, '[]', 0], [(int)$log['fid'], $log['member'], (int)$log['topteam'], $log['reward'], (int)$log['times']]);
        $this->assertSame(0, DB::table('elite_mission_log')->where('uid', $a['uid'])->count());
    }

    public function testEliteActionWinFirstKillAndRepeatRewards(): void
    {
        $a = $this->elitePlayer();
        $b = $this->elitePlayer();
        $c = $this->elitePlayer();
        $city = \Hanjiang\MasterData\EliteCity::ROWS[1];
        $item = \Hanjiang\MasterData\Equip::ROWS[$city['first_value']];
        $members = "{$a['uid']},{$b['uid']},{$c['uid']}";
        $params = $a['auth'] + ['fid' => 1, 'teamid' => 4, 'leader' => $a['uid'], 'members' => $members];
        $exp = static fn (int $uid): int => (int)DB::table('general')->where('uid', $uid)->sum('exp');
        $expBefore = [$a['uid'] => $exp($a['uid']), $b['uid'] => $exp($b['uid']), $c['uid'] => $exp($c['uid'])];

        // 首杀：首杀经验 + 首杀奖励（军团 1 为整件装备）
        $res = $this->eliteFight($params);
        $this->assertSame(1, $res['status']);
        $this->assertSame(1, $res['battle']['win']);
        $this->assertSame(['win' => 1, 'teamid' => 4], array_slice($res['info'], 3, null, true));
        // 团战：三路首发各胜 1 场，后备守军（48~50）补位后守方先手，再各胜 1 场；左方连胜榜 2 场
        $battle = $res['battle'];
        $this->assertCount(2, $battle['battle_log']);
        $this->assertSame([45, 46, 47], array_map(static fn (array $r): int => $r['right']['uid'], array_values($battle['battle_log'][0])));
        $this->assertSame([48, 49, 50], array_map(static fn (array $r): int => $r['right']['uid'], array_values($battle['battle_log'][1])));
        foreach ($battle['battle_log'] as $n => $round) {
            foreach ($round as $line => $report) {
                // 守方先手的一场（第 2 轮）旧版 rush_report 先写 right 再写 left
                $this->assertSame($n === 0 ? ['left', 'right', 'assess', 'process', 'left_win', 'right_win'] : ['right', 'left', 'assess', 'process', 'left_win', 'right_win'], array_keys($report));
                $this->assertGreaterThanOrEqual(1, $report['assess']);
                $this->assertSame([$n + 1, 0], [$report['left_win'], $report['right_win']]);
                $this->assertSame((string)[1 => $a['uid'], 2 => $b['uid'], 3 => $c['uid']][$line], $report['left']['uid']);
                $this->assertNotEmpty($report['left']['matrix']);
                $this->assertLessThanOrEqual(3, count($report['process']), '超过 3 回合合并为 3 个回合');
            }
        }
        $this->assertSame([(string)$a['uid'] => 2, (string)$b['uid'] => 2, (string)$c['uid'] => 2], $battle['kill_rank']['left']);
        $this->assertSame([], $battle['kill_rank']['right']);
        foreach ([$a, $b, $c] as $p) {
            $uid = $p['uid'];
            $this->assertSame([
                'exp' => $city['exp'],
                'first_exp' => $city['first_exp'],
                'itemid' => 0,
                'firstkill' => 1,
                'firstitemtype' => 1,
                'firstitemid' => $item['id'],
                'firstquality' => $item['quality'],
                'firstitemname' => $item['name'],
                'number' => 9,
            ], $res['info'][(string)$uid]);
            $this->assertSame(1, DB::table('equipment')->where('uid', $uid)->where('equipid', $item['id'])->count());
            $log = DB::table('elite_mission_log')->where('uid', $uid)->first();
            $this->assertSame([1, 1], [(int)$log['missionid'], (int)$log['first_reward']]);
            // 每个上阵武将加首杀经验
            $generals = DB::table('general')->where('uid', $uid)->count();
            $this->assertSame($expBefore[$uid] + $generals * $city['first_exp'], $exp($uid));
            $this->assertSame(9, $this->eliteActivity($uid)['elitetimes']);
        }
        $log = DB::table('elite_log')->where('leader', $a['uid'])->first();
        // reward 为奖励 json（旧版 varchar(255) 超长截断）
        $reward = json_encode([$a['uid'] => array_diff_key($res['info'][(string)$a['uid']], ['number' => 1]), $b['uid'] => array_diff_key($res['info'][(string)$b['uid']], ['number' => 1]), $c['uid'] => array_diff_key($res['info'][(string)$c['uid']], ['number' => 1])]);
        $this->assertGreaterThan(255, strlen($reward));
        $this->assertSame(substr($reward, 0, 255), $log['reward']);

        // 再打：普通经验 + 每人按掉率掷点（mt_rand(1, 100) <= get_rate），军团 1 掉装备碎片（add_item 碎片分支，1 片）
        DB::table('elite_log')->where('leader', $a['uid'])->update(['dateline' => time() - 11]);
        mt_srand(20261031);
        $rolls = [mt_rand(1, 100), mt_rand(1, 100), mt_rand(1, 100)];
        mt_srand(20261031);
        $expBefore = [$a['uid'] => $exp($a['uid']), $b['uid'] => $exp($b['uid']), $c['uid'] => $exp($c['uid'])];
        $res = $this->eliteFight($params);
        $this->assertSame(1, $res['battle']['win']);
        foreach ([$a, $b, $c] as $i => $p) {
            $uid = $p['uid'];
            $expected = ['exp' => $city['exp'], 'first_exp' => $city['first_exp'], 'itemid' => 0];
            if ($rolls[$i] <= $city['get_rate']) {
                $chip = \Hanjiang\MasterData\Equip::ROWS[$city['get_value']];
                $expected = ['exp' => $city['exp'], 'first_exp' => $city['first_exp'], 'itemid' => $chip['id'], 'itemtype' => 1, 'quality' => $chip['quality'], 'itemname' => $chip['name'] . '碎片'];
            }
            $this->assertSame($expected + ['number' => 8], $res['info'][(string)$uid], "roll {$rolls[$i]}");
            $this->assertSame(1, DB::table('equipment')->where('uid', $uid)->where('equipid', $item['id'])->where('chip', 0)->count());
            if ($rolls[$i] <= $city['get_rate']) {
                $chipRow = DB::table('equipment')->where('uid', $uid)->where('equipid', $city['get_value'])->where('chip', '>', 0)->first();
                $this->assertSame(1, (int)$chipRow['chip'], '碎片 1 片');
                $this->assertSame(['elite', 'action'], array_values(DB::table('itemchip_log')->select('class', 'method')->where('uid', $uid)->first()));
            }
            $generals = DB::table('general')->where('uid', $uid)->count();
            $this->assertSame($expBefore[$uid] + $generals * $city['exp'], $exp($uid));
        }
        $this->assertSame(1, DB::table('elite_mission_log')->where('uid', $a['uid'])->count());

        // 至尊组队：队长 300%、队员 200% 经验（PHP 5 json 14 位有效数字），扣队长元宝
        $top = \Hanjiang\MasterData\ElitePrice::ROWS[1];
        DB::table('member')->where('uid', $a['uid'])->update(['vip' => 8, 'gold' => 100]);
        DB::table('elite_log')->where('leader', $a['uid'])->update(['dateline' => time() - 11]);
        $res = $this->eliteFight(['topteam' => 1] + $params);
        $this->assertSame(1, $res['battle']['win']);
        $this->assertSame([$city['exp'] * 3, $city['first_exp'] * 3], [$res['info'][(string)$a['uid']]['exp'], $res['info'][(string)$a['uid']]['first_exp']]);
        $this->assertSame([$city['exp'] * 2, $city['first_exp'] * 2], [$res['info'][(string)$b['uid']]['exp'], $res['info'][(string)$b['uid']]['first_exp']]);
        $this->assertSame(100 - $top['gold'], (int)DB::table('member')->where('uid', $a['uid'])->value('gold'));
        $this->assertSame(1, (int)DB::table('elite_log')->where('leader', $a['uid'])->where('topteam', 1)->count());
    }

    /**
     * 多人副本实时组队：elitepop.js 经 socket.io.min.js 适配层发 {action, data}
     */
    private function eliteWs(string $action, mixed $data = '', ?int $uid = null): \OmniPHP\WebSocket\Context
    {
        $ctx = \OmniPHP\WebSocket\Context::mock(['action' => $action, 'data' => $data, 'time' => 0], uid: $uid);
        \Hanjiang\WebSocket\WebSocketHandler::dispatch($ctx);
        return $ctx;
    }

    /**
     * 登录实时组队（开放时间内），返回该玩家的会话收件箱（之后推给他的帧都追加到这里）
     *
     * @param array{uid: int, auth: array{token: string, uid: int}} $p
     */
    private function eliteWsLogin(array $p): \OmniPHP\WebSocket\Context
    {
        \Hanjiang\WebSocket\EliteRoom::useClock(static fn (): int => (int)strtotime('2026-10-30 12:30:00'));
        $inbox = $this->eliteWs('login', ['uid' => $p['uid'], 'token' => $p['auth']['token'], 'serverid' => 1]);
        $this->assertSame([['loginstatus', 1]], $this->eliteFrames($inbox));
        return $inbox;
    }

    /**
     * 清掉多人副本实时组队的 Redis 状态（各用例都看军团 1 的队伍列表，避免互相影响）
     */
    private function eliteRoomReset(): void
    {
        $prefix = (string)Redis::instance()->getOption(\Redis::OPT_PREFIX);
        foreach (Redis::keys('elite:*') as $key) {
            Redis::del(substr($key, strlen($prefix)));
        }
    }

    /**
     * 取出并清空收到的帧：[[事件名, 载荷], ...]
     *
     * @return list<array{0: string, 1: mixed}>
     */
    private function eliteFrames(\OmniPHP\WebSocket\Context $inbox): array
    {
        $frames = [];
        foreach ($inbox->sentMessages as $raw) {
            $frame = json_decode($raw, true);
            $frames[] = [$frame['type'], $frame['data']];
        }
        $inbox->sentMessages = [];
        return $frames;
    }

    /**
     * @return list<string>
     */
    private function eliteFrameTypes(\OmniPHP\WebSocket\Context $inbox): array
    {
        return array_column($this->eliteFrames($inbox), 0);
    }

    public function testEliteRoomLoginAndIndex(): void
    {
        $this->eliteRoomReset();
        $p = $this->elitePlayer();
        $at = static fn (string $hm): \Closure => static fn (): int => (int)strtotime('2026-10-30 ' . $hm . ':00');
        $login = fn (array $data): array => $this->eliteFrames($this->eliteWs('login', $data));

        // 缺 token -1；token 无效 / 与 uid 不符 0（只认 token，不回退到 uid）；不在开放时间 -3
        \Hanjiang\WebSocket\EliteRoom::useClock($at('12:30'));
        $this->assertSame([['loginstatus', -1]], $login(['uid' => $p['uid'], 'serverid' => 1]));
        $this->assertSame([['loginstatus', 0]], $login(['uid' => $p['uid'], 'token' => 'tk_forged', 'serverid' => 1]));
        $this->assertSame([['loginstatus', 0]], $login(['uid' => $p['uid'] + 1, 'token' => $p['auth']['token'], 'serverid' => 1]));
        foreach (['11:59' => -3, '14:01' => -3, '18:59' => -3, '21:01' => -3] as $hm => $code) {
            \Hanjiang\WebSocket\EliteRoom::useClock($at($hm));
            $this->assertSame([['loginstatus', $code]], $login(['uid' => $p['uid'], 'token' => $p['auth']['token']]), $hm);
        }
        \Hanjiang\WebSocket\EliteRoom::useClock($at('19:00'));
        $this->assertSame([['loginstatus', 1]], $login(['uid' => $p['uid'], 'token' => $p['auth']['token']]));

        // 没登录的连接发组队事件：loginstatus 0（客户端提示失去登录状态）
        $q = $this->newGuestPlayer();
        foreach (['eliteindex', 'teamlist', 'createTeam', 'startfight'] as $action) {
            $this->assertSame([['loginstatus', 0]], $this->eliteFrames($this->eliteWs($action, ['id' => 1], $q['uid'])), $action);
        }

        // eliteindex：军团列表（fid / name / open / firstkill）、今日次数、至尊组队价格
        $inbox = $this->eliteWsLogin($p);
        $this->eliteWs('eliteindex', '', $p['uid']);
        [[$type, $index]] = $this->eliteFrames($inbox);
        $this->assertSame('eliteindex', $type);
        $this->assertSame(['list', 'number', 'supper_group', 'countryname'], array_keys($index));
        $this->assertCount(count(\Hanjiang\MasterData\EliteCity::ROWS), $index['list']);
        $this->assertSame(['fid' => 1, 'name' => \Hanjiang\MasterData\EliteCity::ROWS[1]['name'], 'open' => 1, 'firstkill' => 1], $index['list'][0]);
        $this->assertSame(0, $index['list'][1]['open']);
        $this->assertSame(10, $index['number']);
        $this->assertSame(\Hanjiang\MasterData\ElitePrice::ROWS[1], $index['supper_group']);
        \Hanjiang\WebSocket\EliteRoom::disconnect($p['uid']);
    }

    public function testEliteRoomTeamRules(): void
    {
        $this->eliteRoomReset();
        [$a, $b, $c, $d] = [$this->elitePlayer(), $this->elitePlayer(), $this->elitePlayer(), $this->elitePlayer()];
        $locked = $this->newGuestPlayer();
        [$ia, $ib, $ic, $id, $il] = array_map(fn (array $p): \OmniPHP\WebSocket\Context => $this->eliteWsLogin($p), [$a, $b, $c, $d, $locked]);

        // teamlist：军团奖励行 + id / firstkill / list；军团不存在 -1，未开启 -2
        $this->eliteWs('teamlist', ['id' => 1], $a['uid']);
        [[$type, $list]] = $this->eliteFrames($ia);
        $this->assertSame('teamlist', $type);
        $city = array_column(\Hanjiang\Handler\Activity\EliteHandler::rewardList(), null, 'id')[1];
        $this->assertSame($city + ['firstkill' => 1, 'list' => []], $list);
        $this->eliteWs('teamlist', ['id' => 999], $locked['uid']);
        $this->eliteWs('teamlist', ['id' => 1], $locked['uid']);
        $this->assertSame([['teamlist_error', -1], ['teamlist_error', -2]], $this->eliteFrames($il));
        $this->eliteWs('teamlist', ['id' => 1], $b['uid']);
        $this->eliteFrames($ib);

        // 创建：createteamdone + getteamdone；正在看军团 1 的玩家收到 teamlist，没在看的（c）收不到
        $this->eliteWs('createTeam', ['id' => 1, 'type' => 1, 'topteam' => false], $a['uid']);
        $frames = $this->eliteFrames($ia);
        $this->assertSame(['createteamdone', 'getteamdone', 'teamlist'], array_column($frames, 0));
        $teamId = $frames[0][1]['teamid'];
        $nick = static fn (int $uid): string => (string)DB::table('member')->where('uid', $uid)->value('nickname');
        $this->assertSame([
            'teamid' => $teamId, 'fid' => 1, 'name' => $nick($a['uid']), 'leader' => $a['uid'], 'topteam' => 0,
            'countryid' => 0, 'countryname' => '', 'invited' => 0, 'num' => 1,
            'list' => [['uid' => $a['uid'], 'nickname' => $nick($a['uid']), 'level' => 20000, 'order' => 1, 'leader' => true]],
        ], $frames[1][1]);
        $summary = ['teamid' => $teamId, 'name' => $nick($a['uid']), 'num' => 1, 'countryid' => 0, 'countryname' => '', 'topteam' => 0];
        $this->assertSame([$summary], $frames[2][1]['list']);
        $this->assertSame([['teamlist', $city + ['firstkill' => 1, 'list' => [$summary]]]], $this->eliteFrames($ib));
        $this->assertSame([], $this->eliteFrames($ic));

        // 创建失败：已在队伍 -1，国家队伍没国家 -2，军团未开启 -3
        $this->eliteWs('createTeam', ['id' => 1, 'type' => 1], $a['uid']);
        $this->eliteWs('createTeam', ['id' => 1, 'type' => 2], $b['uid']);
        $this->eliteWs('createTeam', ['id' => 2, 'type' => 1], $b['uid']);
        $this->assertSame([['createteam_error', -1]], $this->eliteFrames($ia));
        $this->assertSame([['createteam_error', -2], ['createteam_error', -3]], $this->eliteFrames($ib));

        // 加入：军团未开启 -1，队伍不存在 -2；成功后全队 getteamdone，按空位排第 2
        $this->eliteWs('jointeam', ['id' => 1, 'teamid' => $teamId], $locked['uid']);
        $this->eliteWs('jointeam', ['id' => 1, 'teamid' => $teamId + 1000], $b['uid']);
        $this->assertSame([['jointeam_error', -1]], $this->eliteFrames($il));
        $this->assertSame([['jointeam_error', -2]], $this->eliteFrames($ib));
        $this->eliteWs('jointeam', ['id' => 1, 'teamid' => $teamId], $b['uid']);
        $this->assertSame(['jointeamdone', 'getteamdone', 'teamlist'], $this->eliteFrameTypes($ib));
        [[, $detail], [, $list]] = $this->eliteFrames($ia);
        $this->assertSame([2, [1, 2]], [$detail['num'], array_column($detail['list'], 'order')]);
        $this->assertSame(2, $list['list'][0]['num']);
        $this->eliteWs('jointeam', ['id' => 1, 'teamid' => $teamId], $b['uid']);
        $this->assertSame([['jointeam_error', 0]], $this->eliteFrames($ib));
        $this->eliteWs('jointeam', ['id' => 1, 'teamid' => $teamId], $c['uid']);
        $this->eliteWs('jointeam', ['id' => 1, 'teamid' => $teamId], $d['uid']);
        $this->assertSame([['jointeam_error', -3]], $this->eliteFrames($id));
        $this->eliteFrames($ia);
        $this->eliteFrames($ib);
        $this->eliteFrames($ic);

        // 调位置：只有队长 -3；from 位置的人换到 to
        $this->eliteWs('changeorder', ['id' => 1, 'teamid' => $teamId, 'from' => 2, 'to' => 3], $b['uid']);
        $this->assertSame([['changeorder_error', -3]], $this->eliteFrames($ib));
        $this->eliteWs('changeorder', ['id' => 1, 'teamid' => $teamId, 'from' => 2, 'to' => 3], $a['uid']);
        [[$type, $detail]] = $this->eliteFrames($ic);
        $this->assertSame('getteamdone', $type);
        $this->assertSame([$a['uid'] => 1, $b['uid'] => 3, $c['uid'] => 2], array_column($detail['list'], 'order', 'uid'));

        // 踢人：不是队长 -3，对方不在队伍 -4；被踢者 beenfire，队长 firememberdone
        $this->eliteWs('firemember', ['id' => 1, 'teamid' => $teamId, 'uid' => $c['uid']], $b['uid']);
        $this->assertSame([['firemember_error', -3]], array_slice($this->eliteFrames($ib), -1));
        $this->eliteWs('firemember', ['id' => 1, 'teamid' => $teamId, 'uid' => $d['uid']], $a['uid']);
        $this->assertSame([['firemember_error', -4]], array_slice($this->eliteFrames($ia), -1));
        $this->eliteWs('firemember', ['id' => 1, 'teamid' => $teamId, 'uid' => $c['uid']], $a['uid']);
        // c 加入时已在看军团 1，被踢后仍会收到列表更新
        $this->assertSame(['beenfire', 'teamlist'], $this->eliteFrameTypes($ic));
        $this->assertSame(['firememberdone', 'getteamdone', 'teamlist'], $this->eliteFrameTypes($ia));
        [[$type, $detail]] = $this->eliteFrames($ib);
        $this->assertSame(['getteamdone', 2], [$type, $detail['num']]);

        // 退出：队长不能退 -3，队伍不存在 -2，军团不对 -1，不在该队伍 0
        $this->eliteWs('exitteam', ['id' => 1, 'teamid' => $teamId], $a['uid']);
        $this->assertSame([['exitteam_error', -3]], $this->eliteFrames($ia));
        $this->eliteWs('exitteam', ['id' => 1, 'teamid' => $teamId + 1000], $b['uid']);
        $this->eliteWs('exitteam', ['id' => 2, 'teamid' => $teamId], $b['uid']);
        $this->assertSame([['exitteam_error', -2], ['exitteam_error', -1]], $this->eliteFrames($ib));
        $this->eliteWs('exitteam', ['id' => 1, 'teamid' => $teamId], $c['uid']);
        $this->assertSame([['exitteam_error', 0]], $this->eliteFrames($ic));
        // 解散：只有队长 -3
        $this->eliteWs('fireteam', ['id' => 1, 'teamid' => $teamId], $b['uid']);
        $this->assertSame([['fireteam_error', -3]], $this->eliteFrames($ib));
        $this->eliteWs('exitteam', ['id' => 1, 'teamid' => $teamId], $b['uid']);
        $this->assertSame(['exitteamdone', 'teamlist'], $this->eliteFrameTypes($ib));
        [[, $detail]] = $this->eliteFrames($ia);
        $this->assertSame(1, $detail['num']);

        // 邀请标记
        $this->eliteWs('teaminvited', ['id' => 1, 'teamid' => $teamId], $a['uid']);
        $this->assertSame(1, $this->eliteFrames($ia)[0][1]['invited']);

        // 解散：队长 fireteamdone，队员 teambeenfire，列表清空
        $this->eliteWs('jointeam', ['id' => 1, 'teamid' => $teamId], $b['uid']);
        $this->eliteFrames($ia);
        $this->eliteFrames($ib);
        $this->eliteWs('fireteam', ['id' => 1, 'teamid' => $teamId], $a['uid']);
        $this->assertSame([['fireteamdone', ['teamid' => $teamId]], ['teamlist', $city + ['firstkill' => 1, 'list' => []]]], $this->eliteFrames($ia));
        $this->assertSame(['teambeenfire', 'teamlist'], $this->eliteFrameTypes($ib));

        // 国家队伍：只有同国家可加入（没有国家 -4，国家不符 -5）
        $country = static fn (string $name, int $uid): int => (int)DB::table('country')->insertGetId(['name' => $name . substr((string)hrtime(true), -6), 'member' => 1, 'uid' => $uid, 'nickname' => 'k']);
        $ca = $country('组', $a['uid']);
        $cb = $country('队', $b['uid']);
        DB::table('member')->where('uid', $a['uid'])->update(['country' => $ca]);
        $this->eliteWs('createTeam', ['id' => 1, 'type' => 2, 'topteam' => true], $a['uid']);
        $frames = $this->eliteFrames($ia);
        $teamId = $frames[0][1]['teamid'];
        $this->assertSame([$ca, (string)DB::table('country')->where('id', $ca)->value('name'), 1], [$frames[1][1]['countryid'], $frames[1][1]['countryname'], $frames[1][1]['topteam']]);
        $this->eliteWs('jointeam', ['id' => 1, 'teamid' => $teamId], $b['uid']);
        DB::table('member')->where('uid', $b['uid'])->update(['country' => $cb]);
        $this->eliteWs('jointeam', ['id' => 1, 'teamid' => $teamId], $b['uid']);
        $this->assertSame([['jointeam_error', -4], ['jointeam_error', -5]], array_values(array_filter($this->eliteFrames($ib), static fn (array $f): bool => $f[0] === 'jointeam_error')));
        DB::table('member')->where('uid', $b['uid'])->update(['country' => $ca]);
        $this->eliteWs('jointeam', ['id' => 1, 'teamid' => $teamId], $b['uid']);
        $this->assertSame('jointeamdone', $this->eliteFrames($ib)[0][0]);

        foreach ([$a, $b, $c, $d, $locked] as $p) {
            \Hanjiang\WebSocket\EliteRoom::disconnect($p['uid']);
        }
    }

    public function testEliteRoomStartFight(): void
    {
        $this->eliteRoomReset();
        // 阵法为空：每场直接大败，结果确定（同 testEliteActionLoseStructure）
        [$a, $b, $c] = [$this->elitePlayer(false), $this->elitePlayer(false), $this->elitePlayer(false)];
        [$ia, $ib, $ic] = array_map(fn (array $p): \OmniPHP\WebSocket\Context => $this->eliteWsLogin($p), [$a, $b, $c]);
        $this->eliteWs('teamlist', ['id' => 1], $c['uid']);
        $create = function (array $leader, \OmniPHP\WebSocket\Context $inbox, array $members, bool $topteam = false): int {
            $this->eliteWs('createTeam', ['id' => 1, 'type' => 1, 'topteam' => $topteam], $leader['uid']);
            $teamId = $this->eliteFrames($inbox)[0][1]['teamid'];
            foreach ($members as $m) {
                $this->eliteWs('jointeam', ['id' => 1, 'teamid' => $teamId], $m['uid']);
            }
            return $teamId;
        };

        // 不在队伍 0；人数不够 -3；不是队长 -2
        $this->eliteWs('startfight', 1, $a['uid']);
        $this->assertSame([['startfight_error', 0]], $this->eliteFrames($ia));
        $teamId = $create($a, $ia, []);
        $this->eliteWs('startfight', 1, $a['uid']);
        $this->assertSame([['startfight_error', -3]], array_slice($this->eliteFrames($ia), -1));
        $this->eliteWs('jointeam', ['id' => 1, 'teamid' => $teamId], $b['uid']);
        $this->eliteWs('changeorder', ['id' => 1, 'teamid' => $teamId, 'from' => 1, 'to' => 2], $a['uid']);
        $this->eliteFrames($ib);
        $this->eliteWs('startfight', 1, $b['uid']);
        $this->assertSame([['startfight_error', -2]], $this->eliteFrames($ib));
        $this->eliteFrames($ia);
        $this->eliteFrames($ic);

        // 开战：全队先 readyfight，再收到同一份 enterfight（fight 的返回值，出场顺序按位置：b 第 1、a 第 2）
        $this->eliteWs('startfight', 1, $a['uid']);
        $fa = $this->eliteFrames($ia);
        $fb = $this->eliteFrames($ib);
        $this->assertSame(['readyfight', 'enterfight', 'teamlist'], array_column($fa, 0));
        $this->assertSame(['readyfight', 'enterfight', 'teamlist'], array_column($fb, 0));
        $enter = $fa[1][1];
        $this->assertSame($enter, $fb[1][1]);
        $this->assertSame(1, $enter['status']);
        $this->assertSame([$b['uid'] => ['number' => 9], $a['uid'] => ['number' => 9], 'win' => 2, 'teamid' => $teamId], $enter['info']);
        $this->assertSame([(string)$b['uid'], (string)$a['uid']], [$enter['battle']['battle_ready']['1']['left']['uid'], $enter['battle']['battle_ready']['2']['left']['uid']]);
        $this->assertSame("{$b['uid']},{$a['uid']}", DB::table('elite_log')->where('leader', $a['uid'])->value('member'));
        // 开战成功后队伍清除，正在看军团的玩家列表更新
        $this->assertSame([], $fa[2][1]['list']);
        $this->assertSame([['teamlist', []]], array_map(static fn (array $f): array => [$f[0], $f[1]['list']], $this->eliteFrames($ic)));
        $this->eliteWs('startfight', 1, $a['uid']);
        $this->assertSame([['startfight_error', 0]], $this->eliteFrames($ia));

        // 10 秒内再开战 → startfight_error -5，队员 readyfight_error；队伍保留
        $teamId = $create($a, $ia, [$b]);
        $this->eliteFrames($ia);
        $this->eliteFrames($ib);
        $this->eliteWs('startfight', 1, $a['uid']);
        $this->assertSame([['readyfight', ['teamid' => $teamId]], ['startfight_error', -5]], $this->eliteFrames($ia));
        $this->assertSame([['readyfight', ['teamid' => $teamId]], ['readyfight_error', -5]], $this->eliteFrames($ib));
        DB::table('elite_log')->where('leader', $a['uid'])->update(['dateline' => time() - 11]);

        // 次数不足 → 全队 enterfight {status -2, uid}，队伍保留
        DB::table('member_activity')->where('uid', $b['uid'])->update(['elitetimes' => 0, 'elitesurplustimes' => 0]);
        $this->eliteWs('startfight', 1, $a['uid']);
        $expect = [['readyfight', ['teamid' => $teamId]], ['enterfight', ['status' => -2, 'info' => [], 'uid' => (string)$b['uid']]]];
        $this->assertSame($expect, $this->eliteFrames($ia));
        $this->assertSame($expect, $this->eliteFrames($ib));
        $this->eliteWs('fireteam', ['id' => 1, 'teamid' => $teamId], $a['uid']);
        DB::table('member_activity')->where('uid', $b['uid'])->update(['elitetimes' => 1]);
        $this->eliteFrames($ia);
        $this->eliteFrames($ib);

        // 至尊组队：队长不到 VIP8 → -7；元宝不足 → -4
        $teamId = $create($a, $ia, [$b], true);
        $this->eliteFrames($ib);
        DB::table('member')->where('uid', $a['uid'])->update(['vip' => 7, 'gold' => 100000]);
        $this->eliteWs('startfight', 1, $a['uid']);
        DB::table('member')->where('uid', $a['uid'])->update(['vip' => 8, 'gold' => \Hanjiang\MasterData\ElitePrice::ROWS[1]['gold'] - 1]);
        $this->eliteWs('startfight', 1, $a['uid']);
        $this->assertSame([['startfight_error', -7], ['startfight_error', -4]], array_values(array_filter($this->eliteFrames($ia), static fn (array $f): bool => $f[0] === 'startfight_error')));
        $this->assertSame([['readyfight_error', -7], ['readyfight_error', -4]], array_values(array_filter($this->eliteFrames($ib), static fn (array $f): bool => $f[0] === 'readyfight_error')));
        // 以上失败都不扣次数
        $this->assertSame([9, 1], [$this->eliteActivity($a['uid'])['elitetimes'], $this->eliteActivity($b['uid'])['elitetimes']]);

        foreach ([$a, $b, $c] as $p) {
            \Hanjiang\WebSocket\EliteRoom::disconnect($p['uid']);
        }
    }

    public function testEliteRoomDisconnectAndRelogin(): void
    {
        $this->eliteRoomReset();
        [$a, $b, $c] = [$this->elitePlayer(), $this->elitePlayer(), $this->elitePlayer()];
        [$ia, $ib, $ic] = array_map(fn (array $p): \OmniPHP\WebSocket\Context => $this->eliteWsLogin($p), [$a, $b, $c]);
        $this->eliteWs('teamlist', ['id' => 1], $c['uid']);
        $this->eliteWs('createTeam', ['id' => 1, 'type' => 1], $a['uid']);
        $teamId = $this->eliteFrames($ia)[0][1]['teamid'];
        $this->eliteWs('jointeam', ['id' => 1, 'teamid' => $teamId], $b['uid']);
        $this->eliteFrames($ia);
        $this->eliteFrames($ib);
        $this->eliteFrames($ic);

        // 队员断线：从队伍移除，队长 getteamdone，观众 teamlist
        \Hanjiang\WebSocket\EliteRoom::disconnect($b['uid']);
        [[$type, $detail]] = $this->eliteFrames($ia);
        $this->assertSame(['getteamdone', 1], [$type, $detail['num']]);
        $this->assertSame(1, $this->eliteFrames($ic)[0][1]['list'][0]['num']);
        $this->assertSame([['loginstatus', 0]], $this->eliteFrames($this->eliteWs('jointeam', ['id' => 1, 'teamid' => $teamId], $b['uid'])));

        // 重复登录：旧会话作废（旧队伍按离开处理），新会话可正常加入
        $ib = $this->eliteWsLogin($b);
        $this->eliteWs('jointeam', ['id' => 1, 'teamid' => $teamId], $b['uid']);
        $this->assertSame('jointeamdone', $this->eliteFrames($ib)[0][0]);
        $this->eliteFrames($ia);
        $old = $ib;
        $ib = $this->eliteWsLogin($b);
        $this->assertSame([], $this->eliteFrames($old));
        [[$type, $detail]] = $this->eliteFrames($ia);
        $this->assertSame(['getteamdone', 1], [$type, $detail['num']]);
        $this->eliteWs('jointeam', ['id' => 1, 'teamid' => $teamId], $b['uid']);
        $this->eliteFrames($ia);
        $this->eliteFrames($ib);
        $this->eliteFrames($ic);

        // 队长断线：队伍解散，队员 teambeenfire，观众列表清空
        \Hanjiang\WebSocket\EliteRoom::disconnect($a['uid']);
        $this->assertSame([['teambeenfire', ['teamid' => $teamId]]], array_slice($this->eliteFrames($ib), 0, 1));
        $this->assertSame([], $this->eliteFrames($ic)[0][1]['list']);
        $this->assertNull(Redis::get('elite:team:' . $teamId));
        $this->assertNull(Redis::get('elite:player:' . $b['uid']));
        // 断线的观众不再收到 teamlist
        \Hanjiang\WebSocket\EliteRoom::disconnect($c['uid']);
        $this->eliteWs('createTeam', ['id' => 1, 'type' => 1], $b['uid']);
        $this->assertSame([], $this->eliteFrames($ic));
        $this->assertArrayNotHasKey($c['uid'], Redis::get('elite:viewers:1'));
        \Hanjiang\WebSocket\EliteRoom::disconnect($b['uid']);
        $this->assertNull(Redis::get('elite:player:' . $b['uid']));
    }

    public function testEliteHttpActionIsInternalOnly(): void
    {
        $a = $this->elitePlayer();
        $b = $this->elitePlayer();
        $res = $this->eliteRequest('action', $a['auth'] + ['fid' => 1, 'teamid' => 3, 'leader' => $a['uid'], 'members' => "{$a['uid']},{$b['uid']}"]);
        $this->assertSame(['status' => 0, 'info' => []], $res);
        $this->assertSame(0, DB::table('elite_log')->where('leader', $a['uid'])->count());
        $this->assertSame([10, 10], [$this->eliteActivity($a['uid'])['elitetimes'], $this->eliteActivity($b['uid'])['elitetimes']]);
    }

    public function testEliteDailyCronResetsTimes(): void
    {
        $p = $this->elitePlayer();
        $uid = $p['uid'];
        DB::table('member_activity')->where('uid', $uid)->update(['elitetimes' => 0, 'elitesurplustimes' => 3, 'freetower' => 0]);
        DB::table('member_field')->where('uid', $uid)->update(['dateline' => strtotime('yesterday')]);
        Redis::del("daily_cron:{$uid}");

        // 旧版 daily_cron：elitetimes 重置为 10，购买的剩余次数保留
        $this->assertSame(13, $this->eliteRequest('get_member_info', $p['auth'])['number']);
        $this->assertSame([10, 3], [$this->eliteActivity($uid)['elitetimes'], $this->eliteActivity($uid)['elitesurplustimes']]);
        $this->assertSame(10, (int)DB::table('member_activity')->where('uid', $uid)->value('freetower'));

        // 新版自定的接口与 Redis 次数已删除
        foreach (['getTowerProgress', 'ELITE_MAPS'] as $name) {
            $this->assertFalse(defined(\Hanjiang\Handler\Activity\EliteHandler::class . '::' . $name) || method_exists(\Hanjiang\Handler\Activity\EliteHandler::class, $name), $name);
        }
        $this->assertNull(Redis::get('elite_times:' . $uid));
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
            if ($g['goout'] === 0 && $g['id'] > 1) {
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

    /**
     * c=copies 请求：去掉响应信封补的 message（以及信封补的 msg = ok；旧版 api_return 只输出控制器给的字段）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function copiesRequest(string $method, array $params = []): array
    {
        $res = $this->request('copies', $method, $params + ['d' => 'newequip']);
        if (($res['msg'] ?? null) === 'ok' && ($res['message'] ?? null) === 'ok') {
            unset($res['msg']);
        }
        unset($res['message']);
        return $res;
    }

    /**
     * @return array{uid: int, auth: array<string, mixed>}
     */
    private function copiesPlayer(int $level = 110): array
    {
        $p = $this->newGuestPlayer();
        // 新账号注册即 VIP1；副本测试从 VIP0（每只怪每天可买 1 次）开始
        DB::table('member')->where('uid', $p['uid'])->update(['level' => $level, 'vip' => 0]);
        return $p;
    }

    /**
     * @return array<string, mixed>
     */
    private function copiesBeat(int $uid, int $copiesId, int $diffId, int $monsterId): array
    {
        return DB::table('copies_beat')->where('uid', $uid)->where('copies_id', $copiesId)
            ->where('diff_id', $diffId)->where('monster_id', $monsterId)->first();
    }

    /**
     * @param array<string, int> $data
     */
    private function copiesSetBeat(int $uid, int $copiesId, int $diffId, array $monsterIds, array $data): void
    {
        DB::table('copies_beat')->where('uid', $uid)->where('copies_id', $copiesId)
            ->where('diff_id', $diffId)->whereIn('monster_id', $monsterIds)->update($data);
    }

    /**
     * 旧版 copies_model->get_price_by_times：从第 start + 1 次起连续 times 次的价格之和
     */
    private function copiesPrice(int $start, int $times): int
    {
        $list = [];
        foreach (\Hanjiang\MasterData\CopiesBuyTimesGold::ROWS as $row) {
            $list[$row['times']] = $row['gold'];
        }
        $sub = 0;
        for ($i = 0; $i < $times; $i++) {
            $start = min(100, $start + 1);
            $sub += $list[$start];
        }
        return $sub;
    }

    /**
     * 第一个让 mt_rand(0, 100) 依次落在 $wants（true 掉落：rate >= roll）的种子
     *
     * @param list<bool> $wants
     */
    private function copiesSeed(float $rate, array $wants): int
    {
        for ($seed = 1; $seed < 100000; $seed++) {
            mt_srand($seed);
            $ok = true;
            foreach ($wants as $want) {
                if (($rate >= mt_rand(0, 100)) !== $want) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return $seed;
            }
        }
        $this->fail('no seed');
    }

    /**
     * 材料奖励项（旧版 get_reward：materials_temp 整行 + rate_info / type / value）
     *
     * @return array<string, mixed>
     */
    private function copiesMaterialReward(int $materialsId, int $num, ?string $rateInfo): array
    {
        $row = \Hanjiang\Model\OwnMaterials::template($materialsId);
        $this->assertNotNull($row);
        if ($rateInfo !== null) {
            $row['rate_info'] = $rateInfo;
        }
        $row['type'] = 3;
        $row['value'] = $num;
        return $row;
    }

    public function testCopiesIndexAndMonsterList(): void
    {
        // 旧版 110 级开启（语言包写 65 级，照代码）
        $low = $this->copiesPlayer(109);
        foreach (['index', 'get_monster_list', 'get_monster_info', 'pk', 'get_mopup_price', 'mop_up', 'get_report_list', 'get_report_info', 'send_to_chat', 'buy_times'] as $m) {
            $res = $this->copiesRequest($m, $low['auth'] + ['id' => 1, 'diff_id' => 1, 'monster_id' => 1, 'times' => 1, 'report' => 1]);
            $this->assertSame(-1, $res['status'], $m);
            $this->assertSame('副本65级开启。', $res['msg'], $m);
        }

        $p = $this->copiesPlayer();
        $uid = $p['uid'];
        $member = DB::table('member')->where('uid', $uid)->first();
        $this->assertSame(['status' => 1, 'list' => [
            ['id' => 1, 'name' => '天公张角', 'succ' => 0],
            ['id' => 2, 'name' => '魔王董卓', 'succ' => 0],
            ['id' => 3, 'name' => '战神吕布', 'succ' => 0],
        ]], $this->copiesRequest('index', $p['auth']));
        $this->assertSame(0, DB::table('copies_beat')->where('uid', $uid)->count(), 'index 不建档');

        $this->assertSame(['status' => -2, 'msg' => '传参错误。'], $this->copiesRequest('get_monster_list', $p['auth'] + ['id' => 0]));
        $this->assertSame(['status' => -3, 'msg' => '获取副本难度信息失败。'], $this->copiesRequest('get_monster_list', $p['auth'] + ['id' => 4]));

        // 首次打开副本：每只怪建一行（免费 5 次，VIP0 可购买 1 次）
        $list = $this->copiesRequest('get_monster_list', $p['auth'] + ['id' => 1]);
        $this->assertSame(['status', 'list', 'info'], array_keys($list));
        $this->assertSame(['act' => (int)$member['act'], 'gold' => (int)$member['gold']], $list['info']);
        $this->assertSame([1, 2, 3], array_column($list['list'], 'diff_id'));
        $this->assertSame([1, 0, 0], array_column($list['list'], 'status'));
        $row1 = \Hanjiang\MasterData\CopiesInfo::ROWS[1];
        $this->assertSame([
            'copies_id' => 1, 'diff_id' => 1, 'monster_id' => 1, 'name' => $row1['name'], 'pass_type' => $row1['pass_type'],
            'killed' => 0, 'openstatus' => 1, 'x' => $row1['x'], 'y' => $row1['y'],
        ], $list['list'][0]['list'][0]);
        $this->assertSame([1, 0, 0, 0, 0, 0, 0, 0, 0, 0], array_column($list['list'][0]['list'], 'openstatus'));
        $this->assertCount(10, $list['list'][1]['list']);
        $beats = DB::table('copies_beat')->where('uid', $uid)->get();
        $this->assertCount(30, $beats);
        foreach ($beats as $b) {
            $this->assertSame([0, 1, 0, 5, 1], [(int)$b['vip'], (int)$b['copies_id'], (int)$b['status'], (int)$b['free_times'], (int)$b['vip_times']]);
            $this->assertEqualsWithDelta(time(), (int)$b['dateline'], 5);
        }

        // 简单难度前 3 只已击败：第 4 只开启；普通难度仍未解锁
        $this->copiesSetBeat($uid, 1, 1, [1, 2, 3], ['status' => 1]);
        $list = $this->copiesRequest('get_monster_list', $p['auth'] + ['id' => 1]);
        $this->assertSame([1, 1, 1, 0, 0, 0, 0, 0, 0, 0], array_column($list['list'][0]['list'], 'killed'));
        $this->assertSame([1, 1, 1, 1, 0, 0, 0, 0, 0, 0], array_column($list['list'][0]['list'], 'openstatus'));
        // 简单难度最后一只击败后普通难度解锁
        $this->copiesSetBeat($uid, 1, 1, range(4, 10), ['status' => 1]);
        $list = $this->copiesRequest('get_monster_list', $p['auth'] + ['id' => 1]);
        $this->assertSame([1, 1, 0], array_column($list['list'], 'status'));
        $this->assertSame([1, 0, 0, 0, 0, 0, 0, 0, 0, 0], array_column($list['list'][1]['list'], 'openstatus'));
        $this->assertSame(0, $this->copiesRequest('index', $p['auth'])['list'][0]['succ']);
        // 三个难度的最后一只都击败：首页显示通关
        $this->copiesSetBeat($uid, 1, 2, [10], ['status' => 1]);
        $this->copiesSetBeat($uid, 1, 3, [10], ['status' => 1]);
        $this->assertSame([1, 0, 0], array_column($this->copiesRequest('index', $p['auth'])['list'], 'succ'));
    }

    public function testCopiesMonsterInfo(): void
    {
        $p = $this->copiesPlayer();
        $uid = $p['uid'];
        $this->copiesRequest('get_monster_list', $p['auth'] + ['id' => 1]);

        $this->assertSame(['status' => -2, 'msg' => '传参错误。'], $this->copiesRequest('get_monster_info', $p['auth'] + ['id' => 1, 'diff_id' => 1]));
        $this->assertSame(['status' => -3, 'msg' => '当前副本难度尚未解锁'], $this->copiesRequest('get_monster_info', $p['auth'] + ['id' => 1, 'diff_id' => 2, 'monster_id' => 1]));
        // 旧版代码：-4 获取怪物信息失败（提示 diff_error），-5 当前怪物尚未解锁
        $this->assertSame(['status' => -4, 'msg' => '当前副本难度尚未解锁'], $this->copiesRequest('get_monster_info', $p['auth'] + ['id' => 1, 'diff_id' => 1, 'monster_id' => 99]));
        $this->assertSame(['status' => -5, 'msg' => '当前怪物尚未解锁'], $this->copiesRequest('get_monster_info', $p['auth'] + ['id' => 1, 'diff_id' => 1, 'monster_id' => 2]));

        // 未首杀：首杀声望；首杀奖励类型 0 时没有 first_reward；掉落只展示不发放
        $info = $this->copiesRequest('get_monster_info', $p['auth'] + ['id' => 1, 'diff_id' => 1, 'monster_id' => 1]);
        $row1 = \Hanjiang\MasterData\CopiesInfo::ROWS[1];
        $reward1 = \Hanjiang\MasterData\CopiesReward::ROWS[1];
        $this->assertSame(['status' => 1, 'info' => [
            'mop_up' => 0,
            'reputation' => $reward1['first_reputation'],
            'reward' => [$this->copiesMaterialReward($reward1['value4'], $reward1['num4'], '小概率')],
            'first_kill' => 1,
            'free_times' => 5,
            'vip_times' => 1,
            'name' => $row1['name'],
            'image' => $row1['image'],
            'monster_level' => $row1['level'],
            'pass_type' => $row1['pass_type'],
            'price' => 0,
        ]], $info);
        $this->assertSame(0, DB::table('own_materials')->where('uid', $uid)->count());

        // 已首杀：正常声望，first_reward 为空数组；免费次数用完时给出下一次购买价格
        DB::table('copies_beat_log')->insert(['uid' => $uid, 'nickname' => 'x', 'level' => 110, 'copies_id' => 1, 'diff_id' => 1, 'monster_id' => 1, 'status' => 1, 'dateline' => time()]);
        $this->copiesSetBeat($uid, 1, 1, [1], ['status' => 1, 'free_times' => 0]);
        $info = $this->copiesRequest('get_monster_info', $p['auth'] + ['id' => 1, 'diff_id' => 1, 'monster_id' => 1])['info'];
        $this->assertSame(['mop_up', 'reputation', 'first_reward', 'reward', 'first_kill', 'free_times', 'vip_times', 'name', 'image', 'monster_level', 'pass_type', 'price'], array_keys($info));
        $this->assertSame([1, $reward1['reputation'], [], 0, 0, 1], [$info['mop_up'], $info['reputation'], $info['first_reward'], $info['first_kill'], $info['free_times'], $info['vip_times']]);
        $this->assertSame(\Hanjiang\MasterData\CopiesBuyTimesGold::ROWS[1]['gold'], $info['price'], '已购 0 次：第 1 次的价格');
        $this->copiesSetBeat($uid, 1, 1, [1], ['vip_times' => 0]);
        $this->assertSame(\Hanjiang\MasterData\CopiesBuyTimesGold::ROWS[2]['gold'], $this->copiesRequest('get_monster_info', $p['auth'] + ['id' => 1, 'diff_id' => 1, 'monster_id' => 1])['info']['price']);

        // 首杀奖励为装备（普通难度第 10 只：Equip 65）：装备模板整行，type 改为奖励类型，首杀必得
        $this->copiesSetBeat($uid, 1, 1, range(1, 10), ['status' => 1]);
        $this->copiesRequest('get_monster_list', $p['auth'] + ['id' => 1]);
        $this->copiesSetBeat($uid, 1, 2, range(1, 9), ['status' => 1]);
        $reward = null;
        foreach (\Hanjiang\MasterData\CopiesReward::ROWS as $r) {
            if ([$r['copies_id'], $r['diff_id'], $r['monster_id']] === [1, 2, 10]) {
                $reward = $r;
            }
        }
        $this->assertSame([1, 65, 1], [$reward['first_type'], $reward['first_value'], $reward['first_num']]);
        $info = $this->copiesRequest('get_monster_info', $p['auth'] + ['id' => 1, 'diff_id' => 2, 'monster_id' => 10])['info'];
        $equip = \Hanjiang\MasterData\Equip::ROWS[65];
        $equip['type'] = 1;
        $equip['value'] = 1;
        $equip['rate_info'] = '首杀必得';
        $this->assertSame($equip, $info['first_reward']);
        $this->assertSame($reward['first_reputation'], $info['reputation']);
        $this->assertSame(0, DB::table('equipment')->where('uid', $uid)->count(), '查看不发奖');
    }

    public function testCopiesPkLoseAndGuards(): void
    {
        $p = $this->copiesPlayer();
        $uid = $p['uid'];
        $member = DB::table('member')->where('uid', $uid)->first();
        $this->copiesRequest('get_monster_list', $p['auth'] + ['id' => 1]);
        $q = ['id' => 1, 'diff_id' => 1, 'monster_id' => 1];

        // 阵法里没有武将：旧版 battle_mission 按空队伍继续战斗，直接大败；战败只扣军令，不扣次数
        $this->emptyFormation($uid);
        $res = $this->copiesRequest('pk', $p['auth'] + $q);
        $this->assertSame(['status', 'battle', 'reward_info', 'info'], array_keys($res));
        $this->assertSame(1, $res['status']);
        $this->assertSame(['leftteam', 'rightteam', 'process', 'assess', 'leftresult', 'rightresult', 'leftbuff', 'rightbuff', 'left_role', 'right_role'], array_keys($res['battle']));
        $this->assertSame(-3, $res['battle']['assess']);
        // 守军取该怪物行的 position1~9 → copies_info 的行
        $row1 = \Hanjiang\MasterData\CopiesInfo::ROWS[1];
        $positions = [];
        for ($i = 1; $i <= 9; $i++) {
            if ($row1['position' . $i] > 0) {
                $positions[] = (string)$i;
            }
        }
        $this->assertSame($positions, array_map('strval', array_keys($res['battle']['rightteam'])));
        $this->assertSame((string)$row1['position3'], $res['battle']['rightteam']['3']['heroid']);
        $this->assertSame(['name' => $member['nickname'], 'level' => 110, 'gender' => (int)$member['gender']], $res['battle']['left_role']);
        $this->assertSame(['name' => $row1['name'], 'level' => $row1['level']], $res['battle']['right_role']);
        $this->assertSame([], $res['reward_info']);
        $this->assertSame(['win' => -3, 'act' => (int)$member['act'] - 1, 'gold' => (int)$member['gold'], 'report' => 0], $res['info']);
        $this->assertSame((int)$member['act'] - 1, (int)DB::table('member')->where('uid', $uid)->value('act'));
        $beat = $this->copiesBeat($uid, 1, 1, 1);
        $this->assertSame([0, 5], [(int)$beat['status'], (int)$beat['free_times']]);
        $this->assertSame(0, DB::table('copies_beat_log')->where('uid', $uid)->count());
        $this->assertNull(Redis::get('copies_pk_lock' . $uid), '请求结束解锁');

        // 加锁中
        Redis::set('copies_pk_lock' . $uid, 1, 30);
        $this->assertSame(['status' => -8, 'msg' => '操作太快了。'], $this->copiesRequest('pk', $p['auth'] + $q));
        Redis::del('copies_pk_lock' . $uid);

        // 没有阵法：旧版 battle_mission 返回 -1 → pk -7，不扣军令
        $act = (int)DB::table('member')->where('uid', $uid)->value('act');
        DB::table('member_field')->where('uid', $uid)->update(['matrixid' => 0]);
        $this->assertSame(['status' => -7, 'msg' => '获取战斗信息失败。'], $this->copiesRequest('pk', $p['auth'] + $q));
        $this->assertSame($act, (int)DB::table('member')->where('uid', $uid)->value('act'));
        DB::table('member_field')->where('uid', $uid)->update(['matrixid' => 1]);

        $this->assertSame(['status' => -2, 'msg' => '传参错误。'], $this->copiesRequest('pk', $p['auth'] + ['id' => 1, 'diff_id' => 1]));
        $this->assertSame(['status' => -3, 'msg' => '当前副本难度尚未解锁'], $this->copiesRequest('pk', $p['auth'] + ['id' => 1, 'diff_id' => 2, 'monster_id' => 1]));
        $this->assertSame(['status' => -4, 'msg' => '获取怪物信息失败。'], $this->copiesRequest('pk', $p['auth'] + ['id' => 1, 'diff_id' => 1, 'monster_id' => 99]));
        $this->assertSame(['status' => -5, 'msg' => '当前怪物尚未解锁'], $this->copiesRequest('pk', $p['auth'] + ['id' => 1, 'diff_id' => 1, 'monster_id' => 2]));

        DB::table('member')->where('uid', $uid)->update(['act' => 0]);
        $this->assertSame(['status' => -7, 'msg' => '军令不足。'], $this->copiesRequest('pk', $p['auth'] + $q));
        DB::table('member')->where('uid', $uid)->update(['act' => 50]);
        $this->copiesSetBeat($uid, 1, 1, [1], ['free_times' => 0]);
        $this->assertSame(['status' => -6, 'msg' => '攻击次数不足。'], $this->copiesRequest('pk', $p['auth'] + $q));
        $this->assertSame(50, (int)DB::table('member')->where('uid', $uid)->value('act'));
    }

    public function testCopiesPkWinFirstKillRewardsAndReport(): void
    {
        DB::execute('DELETE FROM `zc_sanguo_copies_beat_log`');
        DB::execute('DELETE FROM `zc_sanguo_copies_report`');
        $p = $this->copiesPlayer();
        $uid = $p['uid'];
        $member = DB::table('member')->where('uid', $uid)->first();
        $this->towerOverpower($uid);
        $this->copiesRequest('get_monster_list', $p['auth'] + ['id' => 1]);
        $q = ['id' => 1, 'diff_id' => 1, 'monster_id' => 1];
        $reward1 = \Hanjiang\MasterData\CopiesReward::ROWS[1];
        $this->assertSame([3, 12, 1, 20.0], [$reward1['type4'], $reward1['value4'], $reward1['num4'], $reward1['rate4']]);

        // 真实战斗：首杀，掉落命中（rate 20 >= rand(0, 100)）
        mt_srand($this->copiesSeed(20.0, [true]));
        $res = $this->copiesRequest('pk', $p['auth'] + $q);
        $this->assertSame(['status', 'battle', 'reward_info', 'info'], array_keys($res));
        $battle = $res['battle'];
        $this->assertNotEmpty($battle['leftteam']);
        $this->assertNotEmpty($battle['process']);
        $this->assertGreaterThanOrEqual(1, $battle['assess']);
        $this->assertSame(['heroid', 'name', 'herotype', 'level', 'allblood', 'power', 'image', 'star', 'talent', 'skill', 'quality'], array_keys(reset($battle['rightteam'])));
        $this->assertSame(['mingzhong', 'jingzhun', 'kangyun', 'tree', 'kcrit', 'mount'], array_keys($battle['leftbuff']));
        $this->assertSame([
            'mop_up' => 1,
            'reputation' => $reward1['first_reputation'],
            'reward' => [$this->copiesMaterialReward(12, 1, '小概率')],
            'first_kill' => 1,
        ], $res['reward_info']);
        $log = DB::table('copies_beat_log')->where('uid', $uid)->first();
        $this->assertSame(['win' => $battle['assess'], 'act' => (int)$member['act'] - 1, 'gold' => (int)$member['gold'], 'report' => (int)$log['id']], $res['info']);
        $this->assertSame([$member['nickname'], 110, 1, 1, 1, $battle['assess']], [$log['nickname'], (int)$log['level'], (int)$log['copies_id'], (int)$log['diff_id'], (int)$log['monster_id'], (int)$log['status']]);
        $this->assertSame((int)$member['reputation'] + $reward1['first_reputation'], (int)DB::table('member')->where('uid', $uid)->value('reputation'));
        $beat = $this->copiesBeat($uid, 1, 1, 1);
        $this->assertSame([1, 4], [(int)$beat['status'], (int)$beat['free_times']]);
        // 锻造材料入库 + 日志
        $own = DB::table('own_materials')->where('uid', $uid)->get();
        $this->assertCount(1, $own);
        $tpl = \Hanjiang\Model\OwnMaterials::template(12);
        $this->assertSame([12, $tpl['name'], $tpl['quality'], $tpl['chip'], 1], [(int)$own[0]['materials_id'], $own[0]['name'], (int)$own[0]['quality'], (int)$own[0]['chip'], (int)$own[0]['num']]);
        $mlog = DB::table('own_materials_log')->where('uid', $uid)->get();
        $this->assertCount(1, $mlog);
        $this->assertSame([1, 12, 1, 1, '', ''], [(int)$mlog[0]['type'], (int)$mlog[0]['materials_id'], (int)$mlog[0]['num'], (int)$mlog[0]['now_num'], $mlog[0]['class'], $mlog[0]['method']]);

        // 战报回放：与 pk 返回的 battle 相同（report 列存 base64(gzcompress(json))）
        $report = $this->copiesRequest('get_report_info', $p['auth'] + ['id' => $res['info']['report']]);
        $this->assertSame(['status', 'battle', 'info'], array_keys($report));
        $expected = $battle;
        $expected['left_role'] = ['name' => $member['nickname'], 'level' => 110];
        $this->assertEquals($expected, $report['battle']);
        $this->assertSame(['win' => $battle['assess']], $report['info']);
        $raw = (string)DB::table('copies_report')->where('id', $res['info']['report'])->value('report');
        $this->assertSame($battle['assess'], json_decode((string)gzuncompress((string)base64_decode($raw)), true)['assess']);
        $this->assertSame(['status' => -3, 'msg' => '攻略不存在'], $this->copiesRequest('get_report_info', $p['auth'] + ['id' => 999999]));
        // 攻略列表：该怪物最近 7 条首杀记录（全服）
        $list = $this->copiesRequest('get_report_list', $p['auth'] + $q);
        $this->assertSame(['status' => 1, 'list' => [[
            'id' => (int)$log['id'], 'uid' => $uid, 'nickname' => $member['nickname'], 'level' => 110,
            'copies_id' => 1, 'diff_id' => 1, 'monster_id' => 1, 'status' => $battle['assess'], 'dateline' => (int)$log['dateline'],
        ]]], $list);
        $this->assertSame(['status' => 1, 'list' => []], $this->copiesRequest('get_report_list', $p['auth'] + ['id' => 1, 'diff_id' => 1, 'monster_id' => 2]));
        $this->assertSame(['status' => -2, 'list' => [], 'msg' => '传参错误。'], $this->copiesRequest('get_report_list', $p['auth'] + ['id' => 1]));

        // 再打一次：正常声望，first_reward 为空数组，不再记战报；掉落未命中
        $rep = (int)DB::table('member')->where('uid', $uid)->value('reputation');
        mt_srand($this->copiesSeed(20.0, [false]));
        $res = $this->copiesRequest('pk', $p['auth'] + $q);
        $this->assertGreaterThanOrEqual(1, $res['info']['win']);
        $this->assertSame(['mop_up' => 1, 'reputation' => $reward1['reputation'], 'first_reward' => [], 'reward' => [], 'first_kill' => 0], $res['reward_info']);
        $this->assertSame(0, $res['info']['report']);
        $this->assertSame($rep + $reward1['reputation'], (int)DB::table('member')->where('uid', $uid)->value('reputation'));
        $this->assertSame(1, DB::table('copies_beat_log')->where('uid', $uid)->count());
        $this->assertSame(3, (int)$this->copiesBeat($uid, 1, 1, 1)['free_times']);
        // 掉落命中：材料累加
        mt_srand($this->copiesSeed(20.0, [true]));
        $res = $this->copiesRequest('pk', $p['auth'] + $q);
        $this->assertSame([$this->copiesMaterialReward(12, 1, '小概率')], $res['reward_info']['reward']);
        $this->assertSame(2, (int)DB::table('own_materials')->where('uid', $uid)->value('num'));
        $this->assertSame([1, 2], array_map('intval', array_column(DB::table('own_materials_log')->where('uid', $uid)->orderBy('id')->get(), 'now_num')));

        // 难度最后一只首次击败：first_succ；首杀奖励装备（Equip 65）入背包
        $this->copiesSetBeat($uid, 1, 1, range(1, 10), ['status' => 1]);
        $this->copiesRequest('get_monster_list', $p['auth'] + ['id' => 1]);
        $this->copiesSetBeat($uid, 1, 2, range(1, 9), ['status' => 1]);
        $res = $this->copiesRequest('pk', $p['auth'] + ['id' => 1, 'diff_id' => 2, 'monster_id' => 10]);
        $this->assertSame(['status', 'first_succ', 'battle', 'reward_info', 'info'], array_keys($res));
        $this->assertGreaterThanOrEqual(1, $res['info']['win']);
        $this->assertSame(1, $res['first_succ']);
        $equip = \Hanjiang\MasterData\Equip::ROWS[65];
        $equip['type'] = 1;
        $equip['value'] = 1;
        $equip['rate_info'] = '首杀必得';
        $this->assertSame($equip, $res['reward_info']['first_reward']);
        $this->assertSame([65], array_map('intval', array_column(DB::table('equipment')->where('uid', $uid)->get(), 'equipid')));
        $this->assertGreaterThan(0, $res['info']['report']);
        // 再次击败已通关的最后一只不再有 first_succ
        $res = $this->copiesRequest('pk', $p['auth'] + ['id' => 1, 'diff_id' => 2, 'monster_id' => 10]);
        $this->assertArrayNotHasKey('first_succ', $res);
        $this->assertSame(1, DB::table('equipment')->where('uid', $uid)->count());
    }

    public function testCopiesMopUpPriceAndFlow(): void
    {
        $p = $this->copiesPlayer();
        $uid = $p['uid'];
        $this->copiesRequest('get_monster_list', $p['auth'] + ['id' => 1]);
        $q = ['id' => 1, 'diff_id' => 1, 'monster_id' => 1];
        DB::table('member')->where('uid', $uid)->update(['act' => 50, 'gold' => 1000]);

        $this->assertSame(['status' => -2, 'msg' => '传参错误。'], $this->copiesRequest('get_mopup_price', $p['auth'] + ['id' => 1]));
        $this->assertSame(['status' => -3, 'msg' => '获取怪物信息失败。'], $this->copiesRequest('get_mopup_price', $p['auth'] + ['id' => 1, 'diff_id' => 1, 'monster_id' => 99]));
        $this->assertSame(['status' => -5, 'msg' => '无法扫荡'], $this->copiesRequest('get_mopup_price', $p['auth'] + $q));
        $this->assertSame(['status' => -5, 'msg' => '无法扫荡'], $this->copiesRequest('mop_up', $p['auth'] + $q + ['times' => 1]));

        $this->copiesSetBeat($uid, 1, 1, [1], ['status' => 1]);
        // 免费 5 次、VIP0 可购买 1 次：1 / 5 次免费，10 次需买 5 次
        $this->assertSame(['status' => 1, 'info' => ['one_sub' => 0, 'five_sub' => 0, 'ten_sub' => $this->copiesPrice(0, 5)], 'vip_times' => 1], $this->copiesRequest('get_mopup_price', $p['auth'] + $q));

        $this->assertSame(['status' => -3, 'msg' => '传参错误。'], $this->copiesRequest('mop_up', $p['auth'] + ['id' => 1, 'times' => 1]));
        $this->assertSame(['status' => -4, 'msg' => '次数错误'], $this->copiesRequest('mop_up', $p['auth'] + $q + ['times' => 3]));
        Redis::set('_mop_up_lock' . $uid, 1, 30);
        $this->assertSame(['status' => -2, 'msg' => '操作太快了。'], $this->copiesRequest('mop_up', $p['auth'] + $q + ['times' => 1]));
        Redis::del('_mop_up_lock' . $uid);
        DB::table('member')->where('uid', $uid)->update(['act' => 4]);
        $this->assertSame(['status' => -11, 'msg' => '军令不足。'], $this->copiesRequest('mop_up', $p['auth'] + $q + ['times' => 5]));
        DB::table('member')->where('uid', $uid)->update(['act' => 50]);

        // 扫荡 5 次（免费次数足够）：每次照常掷点发奖，相同材料合并；扣 5 军令、5 次免费次数
        $wants = [true, false, true, true, false];
        mt_srand($this->copiesSeed(20.0, $wants));
        $rep = (int)DB::table('member')->where('uid', $uid)->value('reputation');
        $res = $this->copiesRequest('mop_up', $p['auth'] + $q + ['times' => 5]);
        $reward1 = \Hanjiang\MasterData\CopiesReward::ROWS[1];
        $item = $this->copiesMaterialReward(12, 3, '小概率');
        $item['type_1'] = 12;
        $this->assertSame(['status' => 1, 'reputation' => 5 * $reward1['first_reputation'], 'reward' => [$item], 'info' => [
            'one_sub' => $this->copiesPrice(0, 1), 'five_sub' => $this->copiesPrice(0, 5), 'ten_sub' => $this->copiesPrice(0, 10),
            'act' => 45, 'gold' => 1000, 'vip_times' => 1, 'free_times' => 0,
        ]], $res, '没有首杀记录（只改了 status）时每次都按首杀声望');
        $this->assertSame($rep + 5 * $reward1['first_reputation'], (int)DB::table('member')->where('uid', $uid)->value('reputation'));
        $this->assertSame(3, (int)DB::table('own_materials')->where('uid', $uid)->value('num'));
        $this->assertSame(3, DB::table('own_materials_log')->where('uid', $uid)->count());
        $beat = $this->copiesBeat($uid, 1, 1, 1);
        $this->assertSame([1, 0, 1], [(int)$beat['status'], (int)$beat['free_times'], (int)$beat['vip_times']]);
        $this->assertSame(45, (int)DB::table('member')->where('uid', $uid)->value('act'));

        // 免费次数用完：扫荡 5 次需买 5 次，但 VIP0 只剩 1 次可购买 → -4，不扣元宝
        DB::table('copies_beat_log')->insert(['uid' => $uid, 'nickname' => 'x', 'level' => 110, 'copies_id' => 1, 'diff_id' => 1, 'monster_id' => 1, 'status' => 1, 'dateline' => time()]);
        $this->assertSame(['status' => -4, 'msg' => '次数不足'], $this->copiesRequest('mop_up', $p['auth'] + $q + ['times' => 5]));
        DB::table('member')->where('uid', $uid)->update(['gold' => 1]);
        $this->assertSame(['status' => -10, 'msg' => '元宝不足'], $this->copiesRequest('mop_up', $p['auth'] + $q + ['times' => 1]));
        DB::table('member')->where('uid', $uid)->update(['gold' => 1000]);
        // 扫荡 1 次：花元宝买 1 次，可购买次数 -1，免费次数不动
        mt_srand($this->copiesSeed(20.0, [false]));
        $res = $this->copiesRequest('mop_up', $p['auth'] + $q + ['times' => 1]);
        $price = $this->copiesPrice(0, 1);
        $this->assertSame(['status' => 1, 'reputation' => $reward1['reputation'], 'reward' => [], 'info' => [
            'one_sub' => $this->copiesPrice(1, 1), 'five_sub' => $this->copiesPrice(1, 5), 'ten_sub' => $this->copiesPrice(1, 10),
            'act' => 44, 'gold' => 1000 - $price, 'vip_times' => 0, 'free_times' => 0,
        ]], $res);
        $this->assertSame(1000 - $price, (int)DB::table('member')->where('uid', $uid)->value('gold'));
        $beat = $this->copiesBeat($uid, 1, 1, 1);
        $this->assertSame([0, 0], [(int)$beat['free_times'], (int)$beat['vip_times']]);
        $this->assertSame(['status' => 1, 'info' => ['one_sub' => $this->copiesPrice(1, 1), 'five_sub' => $this->copiesPrice(1, 5), 'ten_sub' => $this->copiesPrice(1, 10)], 'vip_times' => 0], $this->copiesRequest('get_mopup_price', $p['auth'] + $q));
    }

    public function testCopiesBuyTimesVipLimits(): void
    {
        $p = $this->copiesPlayer();
        $uid = $p['uid'];
        $this->copiesRequest('get_monster_list', $p['auth'] + ['id' => 1]);
        $q = ['id' => 1, 'diff_id' => 1, 'monster_id' => 1];
        DB::table('member')->where('uid', $uid)->update(['gold' => 100]);
        $gold = static fn (int $times): int => \Hanjiang\MasterData\CopiesBuyTimesGold::ROWS[$times]['gold'];

        $this->assertSame(['status' => -2, 'msg' => '传参错误。'], $this->copiesRequest('buy_times', $p['auth'] + ['id' => 1]));
        $this->assertSame(['status' => -3, 'msg' => '获取过关信息失败。'], $this->copiesRequest('buy_times', $p['auth'] + ['id' => 1, 'diff_id' => 1, 'monster_id' => 99]));
        Redis::set('copies_buy_free_times_lock' . $uid, 1, 30);
        $this->assertSame(['status' => 2, 'msg' => '操作太快了。'], $this->copiesRequest('buy_times', $p['auth'] + $q));
        Redis::del('copies_buy_free_times_lock' . $uid);

        // VIP0 每只怪每天可买 1 次：第 1 次价格，返回下一次价格
        $this->assertSame(['status' => 1, 'price' => $gold(2), 'free_times' => 6, 'vip_times' => 0, 'gold' => 100 - $gold(1)], $this->copiesRequest('buy_times', $p['auth'] + $q));
        $beat = $this->copiesBeat($uid, 1, 1, 1);
        $this->assertSame([6, 0], [(int)$beat['free_times'], (int)$beat['vip_times']]);
        $this->assertSame(100 - $gold(1), (int)DB::table('member')->where('uid', $uid)->value('gold'));
        $this->assertSame(['status' => -4, 'msg' => '可购买次数不足。'], $this->copiesRequest('buy_times', $p['auth'] + $q));

        // VIP 等级变化：剩余可购买次数改为新等级的次数（VIP4 10 次），已买次数从 0 算起
        $vip4 = 0;
        foreach (\Hanjiang\MasterData\CopiesVipBuyTimes::ROWS as $row) {
            if ($row['vip'] === 4) {
                $vip4 = $row['times'];
            }
        }
        $this->assertSame(10, $vip4);
        DB::table('member')->where('uid', $uid)->update(['vip' => 4, 'gold' => 100]);
        $this->assertSame(['status' => 1, 'price' => $gold(2), 'free_times' => 7, 'vip_times' => 9, 'gold' => 100 - $gold(1)], $this->copiesRequest('buy_times', $p['auth'] + $q));
        $this->assertSame(['status' => 1, 'price' => $gold(3), 'free_times' => 8, 'vip_times' => 8, 'gold' => 100 - $gold(1) - $gold(2)], $this->copiesRequest('buy_times', $p['auth'] + $q));
        $beat = $this->copiesBeat($uid, 1, 1, 1);
        $this->assertSame([4, 8, 8], [(int)$beat['vip'], (int)$beat['free_times'], (int)$beat['vip_times']]);
        // 别的怪物各自计数
        $this->assertSame(1, (int)$this->copiesBeat($uid, 1, 1, 2)['vip_times'], '未访问的怪物还没按新 VIP 重置');

        DB::table('member')->where('uid', $uid)->update(['gold' => 0]);
        $this->assertSame(['status' => -5, 'msg' => '元宝不足'], $this->copiesRequest('buy_times', $p['auth'] + $q));
        // 没有配置的 VIP 等级：可购买次数 0
        DB::table('member')->where('uid', $uid)->update(['vip' => 14, 'gold' => 100]);
        $this->assertSame(['status' => -4, 'msg' => '可购买次数不足。'], $this->copiesRequest('buy_times', $p['auth'] + $q));
        $this->assertSame(0, (int)$this->copiesBeat($uid, 1, 1, 1)['vip_times']);
    }

    public function testCopiesDailyResetAndSendToChat(): void
    {
        $p = $this->copiesPlayer();
        $uid = $p['uid'];
        $this->copiesRequest('get_monster_list', $p['auth'] + ['id' => 1]);
        $yesterday = strtotime('-1 day');
        $this->copiesSetBeat($uid, 1, 1, [1], ['status' => 1, 'free_times' => 0, 'vip_times' => 0, 'dateline' => $yesterday]);

        // 怪物列表不重置（get_all_beat_info 原样返回）
        $this->copiesRequest('get_monster_list', $p['auth'] + ['id' => 1]);
        $this->assertSame([0, 0, $yesterday], [(int)$this->copiesBeat($uid, 1, 1, 1)['free_times'], (int)$this->copiesBeat($uid, 1, 1, 1)['vip_times'], (int)$this->copiesBeat($uid, 1, 1, 1)['dateline']]);
        // 扫荡价格读的也是未重置的记录
        $this->assertSame(0, $this->copiesRequest('get_mopup_price', $p['auth'] + ['id' => 1, 'diff_id' => 1, 'monster_id' => 1])['vip_times']);
        // 跨天后首次读取该怪物（get_beat_info）：免费次数 5、可购买次数按 VIP 重置，击败状态保留
        $info = $this->copiesRequest('get_monster_info', $p['auth'] + ['id' => 1, 'diff_id' => 1, 'monster_id' => 1])['info'];
        $this->assertSame([5, 1, 1], [$info['free_times'], $info['vip_times'], $info['mop_up']]);
        $beat = $this->copiesBeat($uid, 1, 1, 1);
        $this->assertSame([1, 5, 1], [(int)$beat['status'], (int)$beat['free_times'], (int)$beat['vip_times']]);
        $this->assertEqualsWithDelta(time(), (int)$beat['dateline'], 5);
        $this->assertSame(0, \Hanjiang\MasterData\ActivityTime::ROWS[74]['endtime'], 'double_copies 活动未开启：免费次数不翻倍');

        // 战报分享：参数 / 怪物 / 元宝校验照旧版，扣 2 元宝，全服发平乱战报（type 53，id 为「战报id|副本id」）
        $w = $this->newGuestPlayer();
        $this->chatOnline($w);
        $this->assertSame(['status' => -2, 'msg' => '传参错误。'], $this->copiesRequest('send_to_chat', $p['auth'] + ['id' => 1, 'diff_id' => 1, 'monster_id' => 1]));
        $this->assertSame(['status' => -3, 'msg' => '获取怪物信息失败。'], $this->copiesRequest('send_to_chat', $p['auth'] + ['report' => 1, 'id' => 1, 'diff_id' => 1, 'monster_id' => 99]));
        DB::table('member')->where('uid', $uid)->update(['gold' => 1]);
        $this->assertSame(['status' => -4, 'msg' => '元宝不足'], $this->copiesRequest('send_to_chat', $p['auth'] + ['report' => 1, 'id' => 1, 'diff_id' => 1, 'monster_id' => 1]));
        DB::table('member')->where('uid', $uid)->update(['gold' => 5]);
        $this->assertSame(['status' => 1, 'all_gold' => 3], $this->copiesRequest('send_to_chat', $p['auth'] + ['report' => 1, 'id' => 1, 'diff_id' => 2, 'monster_id' => 1]));
        $this->assertSame(3, (int)DB::table('member')->where('uid', $uid)->value('gold'));
        $monster = null;
        foreach (\Hanjiang\MasterData\CopiesInfo::ROWS as $row) {
            if ($row['copies_id'] === 1 && $row['diff_id'] === 2 && $row['monster_id'] === 1) {
                $monster = $row['name'];
            }
        }
        $this->assertSame([[53, $this->chatFrom($p) . '击败了' . $monster . '(普通) {53,[查看战报],1|1}', '1', $uid]], $this->chatMessages($w));
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

    /**
     * 国家（玩家军团）：等级 $level
     */
    private function bossCountry(int $level): int
    {
        return (int)DB::table('country')->insertGetId(['name' => '神' . substr((string)hrtime(true), -6), 'level' => $level, 'member' => 1, 'uid' => 1, 'nickname' => 'k']);
    }

    public function testCountryBossWindowAndOpenConditions(): void
    {
        $this->bossReset();
        try {
            $country = $this->bossCountry(40);
            $low = $this->bossCountry(39);
            $p = $this->bossPlayer(50, $country);

            $this->bossAt('2026-10-30 20:31:00');
            // 主公 50 级
            $res = $this->bossRequest('countryboss', 'index', $this->bossPlayer(49, $country)['auth']);
            $this->assertSame([6, 50], [$res['status'], $res['member_level']]);
            // 国家 40 级；未加入国家（country 0）旧版同样落到 8
            $res = $this->bossRequest('countryboss', 'index', $this->bossPlayer(50, $low)['auth']);
            $this->assertSame([8, 40], [$res['status'], $res['country_level']]);
            $this->assertSame(8, $this->bossRequest('countryboss', 'index', $this->bossPlayer(50)['auth'])['status']);

            // 周四：未开启，返回开启时间
            $this->bossAt('2026-10-29 20:31:00');
            $res = $this->bossRequest('countryboss', 'index', $p['auth']);
            $this->assertSame([3, '周五 20:30', [], 0, 100], [$res['status'], $res['starttime'], $res['top'], $res['level'], $res['add']]);
            $this->assertSame(5, $this->bossRequest('countryboss', 'battle', $p['auth'])['status']);

            // 周五 20:30 ~ 20:45
            $this->bossAt('2026-10-30 20:29:59');
            $this->assertSame(3, $this->bossRequest('countryboss', 'index', $p['auth'])['status']);
            $this->bossAt('2026-10-30 20:30:05');
            $res = $this->bossRequest('countryboss', 'index', $p['auth']);
            $this->assertSame([
                'status' => 2,
                'countdown' => 895,
                'powerup' => 100,
                'bossinfo' => ['name' => '蚩尤', 'blood' => '100.00', 'image' => 'countryboss'],
                'reward' => [],
                'top' => [],
                'cd' => 0,
                'upcost' => ['gold' => 20, 'silver' => 50 * 1000],
                'cdcost' => 20,
                'my' => ['rank' => 0, 'hit' => 0],
                'level' => 0,
                'add' => 100,
            ], $res);
            $this->bossAt('2026-10-30 20:45:00');
            $this->assertSame(4, $this->bossRequest('countryboss', 'index', $p['auth'])['status']);
            $this->assertSame(5, $this->bossRequest('countryboss', 'battle', $p['auth'])['status']);
        } finally {
            $this->bossReset();
        }
    }

    public function testCountryBossBattleKillRewardAndGuildIsolation(): void
    {
        $this->bossReset();
        try {
            $country = $this->bossCountry(40);
            $other = $this->bossCountry(45);
            $a = $this->bossPlayer(60, $country);
            $b = $this->bossPlayer(60, $country);
            $c = $this->bossPlayer(60, $other);
            $allblood = 2000000000;

            $this->bossAt('2026-10-30 20:31:00');
            $res = $this->bossRequest('countryboss', 'battle', $a['auth']);
            $this->assertSame(1, $res['status']);
            $battle = $res['battle'];
            $this->assertSame($battle['assess'], $res['info']['win']);
            $boss = $battle['rightteam'][5];
            $this->assertSame(['2', '蚩尤', 'countryboss', '0', $allblood, $allblood, 0], [$boss['heroid'], $boss['name'], $boss['image'], $boss['level'], $boss['allblood'], $boss['blood'], $boss['herotype']]);
            $this->assertSame($allblood, $battle['rightresult'][5]['allblood']);
            $this->assertSame(['name' => 'boss' . $a['uid'], 'level' => 60, 'gender' => 0], $battle['left_role']);
            $this->assertSame(['name' => '蚩尤', 'level' => 0, 'type' => 1], $battle['right_role']);
            $hitA = $this->bossHitOf($battle);
            $this->assertGreaterThan(0, $hitA);
            $this->assertSame($hitA, \Hanjiang\Model\CountryBoss::myHit($country, $a['uid']));
            // 剩余血量 = 总血量 - 伤害
            $this->assertSame($allblood - $hitA, \Hanjiang\Model\CountryBoss::bossInfo($country, \Hanjiang\Model\BossClock::now())['blood']);

            // 每个国家各自的 BOSS 与排行
            $res = $this->bossRequest('countryboss', 'index', $c['auth']);
            $this->assertSame(['100.00', []], [$res['bossinfo']['blood'], $res['top']]);
            $res = $this->bossRequest('countryboss', 'index', $a['auth']);
            $this->assertSame([(string)$a['uid']], array_column($res['top'], 'uid'));
            $this->assertSame(['rank' => 1, 'hit' => $hitA], $res['my']);

            // 复活：有 CD 时扣 20 元宝
            Redis::del('countryboss_hit_lock_' . $a['uid']);
            Redis::expire('countryboss_' . $country . '_my_hit_' . $a['uid'], \Hanjiang\Model\CountryBoss::MY_HIT_EXPIRE - 10);
            $res = $this->bossRequest('countryboss', 'battle', $a['auth']);
            $this->assertSame(2, $res['status']);
            $this->assertContains($res['info']['cd'], [50, 51]);
            Redis::del('countryboss_hit_lock_' . $a['uid']);
            $gold = $this->towerMember($a['uid'], 'gold');
            $res = $this->bossRequest('countryboss', 'battle', $a['auth'], ['now' => 1]);
            $this->assertSame(1, $res['status']);
            $this->assertSame($gold - 20, $res['info']['all_gold']);
            $hitA += $this->bossHitOf($res['battle']);

            // 活动中、BOSS 未死不能领奖
            $this->assertSame(-1, $this->bossRequest('countryboss', 'reward', $a['auth'])['status']);

            // 击杀：剩 1 血时 b 出手
            Redis::hSet('countryboss_' . $country . '_boss_info', 'blood', 1);
            $res = $this->bossRequest('countryboss', 'battle', $b['auth']);
            $this->assertSame(1, $res['status']);
            $this->assertGreaterThanOrEqual(1, $res['info']['win']);
            $hitB = $this->bossHitOf($res['battle']);
            $this->assertSame(1, DB::table('country_boss_log')->where('country', $country)->where('ymd', 20261030)->count());
            // BOSS 已死：不能再打，index 显示领奖（胜利）
            Redis::del('countryboss_hit_lock_' . $a['uid']);
            $this->assertSame(4, $this->bossRequest('countryboss', 'battle', $a['auth'])['status']);
            $this->chatJoinCountry($a, $country);
            $this->chatJoinCountry($b, $country);
            $this->chatJoinCountry($c, $other);
            $this->chatOnline($b);
            $this->chatOnline($c);
            $res = $this->bossRequest('countryboss', 'index', $a['auth']);
            $this->assertSame([1, 1], [$res['status'], $res['win']]);
            // 最后一击国家聊天公告（get_message_str 17，type 6 系统），只发给本国成员、只发一次
            $notice = '{1,boss' . $b['uid'] . ' ,' . $b['uid'] . '} 将“蚩尤”最后击杀，奖励额外增加50%';
            $read = $this->request('chat', 'read', $b['auth'])['list'];
            $this->assertSame([[6, 1, $notice, $a['uid']]], array_map(static fn (array $m): array => [$m['type'], $m['system'], $m['message'], $m['uid']], $read));
            $this->assertSame([], $this->chatMessages($c));
            $this->assertSame($notice, $this->request('chat', 'index', $b['auth'])['list'][0]['message'], '进本国国家队列');
            $this->bossRequest('countryboss', 'index', $b['auth']);
            $this->assertSame([], $this->chatMessages($b));
            $this->assertSame(0, $this->bossRequest('countryboss', 'index', $c['auth'])['level']);

            // 领奖：按国家内排名取胜利奖励，最后一击（b）宝石 × 1.5
            $rankA = \Hanjiang\Model\CountryBoss::myRank($country, $a['uid']);
            $rankB = \Hanjiang\Model\CountryBoss::myRank($country, $b['uid']);
            $this->assertSame([1, 2], [min($rankA, $rankB), max($rankA, $rankB)]);
            $jade = static fn (int $uid): int => \Hanjiang\Model\TraderLog::jade($uid, 3);
            $jadeB = $jade($b['uid']);
            $res = $this->bossRequest('countryboss', 'reward', $b['auth']);
            $row = \Hanjiang\MasterData\CountryBossReward::ROWS[$rankB];
            $item = \Hanjiang\MasterData\Equip::ROWS[$row['win_item_id']];
            $this->assertSame([
                'status' => 1,
                'reward' => [
                    'jadetype' => 3,
                    'jadenum' => (int)ceil($row['win_jade_num'] * 1.5),
                    'itemid' => $item['id'],
                    'itemnum' => $row['win_item_num'],
                    'quality' => $item['quality'],
                    'itemname' => $item['name'] . '碎片',
                    'lasthit' => 1,
                    'hit' => $hitB,
                    'win' => 1,
                    'jadename' => '紫宝石',
                    'rank' => $rankB,
                ],
            ], $res);
            $this->assertSame($jadeB + (int)ceil($row['win_jade_num'] * 1.5), $jade($b['uid']));
            // 装备碎片照旧版 add_item 碎片分支发放（满 chipNum(品质) 片合成整件），记 itemchip_log
            $full = \Hanjiang\Battle\LegacyStats::chipNum($item['quality']);
            $chips = DB::table('equipment')->where('uid', $b['uid'])->where('equipid', $item['id'])->whereNull('deleted_at')->get();
            $this->assertSame($row['win_item_num'], array_sum(array_map(static fn (array $r): int => (int)$r['chip'] === 0 ? $full : (int)$r['chip'], $chips)));
            $this->assertSame([$row['win_item_num'], 'countryboss', 'reward'], [(int)($l = DB::table('itemchip_log')->where('uid', $b['uid'])->first())['num'], $l['class'], $l['method']]);
            $this->assertSame(2, $this->bossRequest('countryboss', 'reward', $b['auth'])['status']);
            $res = $this->bossRequest('countryboss', 'reward', $a['auth']);
            $this->assertSame([1, 0, $rankA, \Hanjiang\MasterData\CountryBossReward::ROWS[$rankA]['win_jade_num']], [$res['status'], $res['reward']['lasthit'], $res['reward']['rank'], $res['reward']['jadenum']]);
            // 另一个国家的 BOSS 没死、活动未结束：-1；活动结束后没打过：2
            $this->assertSame(-1, $this->bossRequest('countryboss', 'reward', $c['auth'])['status']);
            $this->bossAt('2026-10-30 20:46:00');
            $this->assertSame(2, $this->bossRequest('countryboss', 'reward', $c['auth'])['status']);

            // 下周五：BOSS 数据已过期重新建档，往周击杀 1 次 → 血量 × 1.03、等级 1
            $this->bossReset();
            $this->bossAt('2026-11-06 20:30:30');
            $res = $this->bossRequest('countryboss', 'index', $a['auth']);
            $this->assertSame([2, 1, 100], [$res['status'], $res['level'], $res['add']]);
            $info = \Hanjiang\Model\CountryBoss::bossInfo($country, \Hanjiang\Model\BossClock::now());
            $this->assertSame([(int)ceil($allblood * 1.03), 1], [$info['allblood'], $info['level']]);
        } finally {
            $this->bossReset();
        }
    }

    public function testCountryBossPowerupCost(): void
    {
        $this->bossReset();
        try {
            $p = $this->bossPlayer(55, $this->bossCountry(40));
            $uid = $p['uid'];
            DB::table('member')->where('uid', $uid)->update(['silver' => 200000, 'gold' => 30]);
            $this->bossAt('2026-10-30 20:32:00');
            $this->assertSame(['status' => 1, 'powerup' => 110, 'all_silver' => 200000 - 55000], $this->bossRequest('countryboss', 'powerup', $p['auth'], ['gold' => 0]));
            $this->assertSame(['status' => 1, 'powerup' => 120, 'all_gold' => 10], $this->bossRequest('countryboss', 'powerup', $p['auth'], ['gold' => 1]));
            // 元宝不足：旧版仍返回 all_gold
            $this->assertSame(['status' => 2, 'powerup' => 0, 'all_gold' => 10], $this->bossRequest('countryboss', 'powerup', $p['auth'], ['gold' => 1]));
            Redis::set('countryboss_' . $this->towerMember($uid, 'country') . '_power_up_' . $uid, 200, 600);
            $this->assertSame(['status' => 4, 'powerup' => 200], $this->bossRequest('countryboss', 'powerup', $p['auth'], ['gold' => 0]));
            $this->assertSame(200000 - 55000, $this->towerMember($uid, 'silver'));
        } finally {
            $this->bossReset();
        }
    }

    public function testBossStateIsAtomicAcrossProcesses(): void
    {
        $this->bossReset();
        try {
            $country = $this->bossCountry(40);
            $p = $this->bossPlayer(60, $country);
            $uid = $p['uid'];
            $allblood = \Hanjiang\Model\CountryBoss::bossInfo($country, (int)strtotime('2026-10-30 20:31:00'))['allblood'];
            \Hanjiang\Model\WorldBoss::bossInfo();

            // 两个进程同时对同一只 BOSS、同一个玩家各记 50 次伤害
            $procs = [];
            foreach ([1, 2] as $i) {
                $procs[] = $this->bossWorker(['hit', $country, $uid, 7, 50]);
            }
            foreach ($procs as $proc) {
                $this->assertStringEndsWith('done', $this->bossWorkerWait($proc));
            }
            $this->assertSame(700, \Hanjiang\Model\CountryBoss::myHit($country, $uid));
            $this->assertSame(1, \Hanjiang\Model\CountryBoss::myRank($country, $uid));
            $this->assertSame((string)700, \Hanjiang\Model\CountryBoss::topRank($country)[0]['hit']);
            $this->assertSame($allblood - 700, (int)Redis::hGet('countryboss_' . $country . '_boss_info', 'blood'));
            $this->assertSame(700, \Hanjiang\Model\WorldBoss::myHit(1, $uid));
            $this->assertSame('700', \Hanjiang\Model\WorldBoss::rankList(1)[0]['hit']);

            // 两个进程同时领奖：只有一个删到伤害记录（只发一次奖）
            $procs = [$this->bossWorker(['claim', $uid]), $this->bossWorker(['claim', $uid])];
            $claimed = array_map(fn ($proc): string => substr($this->bossWorkerWait($proc), -1), $procs);
            sort($claimed);
            $this->assertSame(['0', '1'], $claimed);
            $this->assertSame(0, \Hanjiang\Model\WorldBoss::myHit(1, $uid));
        } finally {
            $this->bossReset();
        }
    }

    /**
     * 起一个 tests/Support/boss_worker.php 进程
     *
     * @param list<int|string> $args
     * @return array{0: resource, 1: array<int, resource>}
     */
    private function bossWorker(array $args): array
    {
        $cmd = array_merge([PHP_BINARY, dirname(__DIR__) . '/Support/boss_worker.php'], array_map('strval', $args));
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($proc);
        return [$proc, $pipes];
    }

    /**
     * @param array{0: resource, 1: array<int, resource>} $worker
     */
    private function bossWorkerWait(array $worker): string
    {
        [$proc, $pipes] = $worker;
        $out = trim((string)stream_get_contents($pipes[1]));
        $err = trim((string)stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($proc), $err);
        return $out;
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
        // 1. 查看主公资料；新版自定的 total_power 旧版没有对应字段（客户端也不读），已删除
        $res = $this->request('information', 'get_info');
        $this->assertSame(1, $res['status']);
        $this->assertArrayNotHasKey('total_power', $res);
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

        // 3. 验证 Equipment 模型装配位置（战斗数值不再在模型里推算，见 Battle\TeamBuilder）
        $equips = Equipment::query()->where('uid', self::$uid)->get();
        $this->assertNotEmpty($equips);
        $firstEq = Equipment::fromArray($equips[0]);
        $this->assertGreaterThan(0, $firstEq->getSite());
    }

    public function testGeneralCreationCopiesTemplate(): void
    {
        $uid = 990001;
        DB::table('general')->where('uid', $uid)->delete();

        // 旧版 add_general：模板字段复制成实列，1 级，levelupexp = 80，不出征
        $id = General::createFromTemplate($uid, 2);
        $this->assertNotNull($id);
        $row = DB::table('general')->where('id', $id)->first();
        $tpl = \Hanjiang\MasterData\General::ROWS[2];
        foreach (['name', 'image', 'type', 'wuli', 'zhili', 'tili', 'talentid', 'skillid', 'star'] as $col) {
            $this->assertEquals($tpl[$col], $row[$col], "createFromTemplate {$col}");
        }
        $this->assertSame(2, (int)$row['generalid']);
        $this->assertSame(1, (int)$row['level']);
        $this->assertSame(80, (int)$row['levelupexp']);
        $this->assertSame(0, (int)$row['goout']);
        $this->assertSame(0, (int)$row['turn']);
        $this->assertSame(1, (int)$row['quality']);

        // 名将图鉴中有的武将写 addtype / addvalue（Book 6 赵云：1 / 4）；type != 1 时 30 级
        $zhaoyun = General::createFromTemplate($uid, 6, 2);
        $this->assertNotNull($zhaoyun);
        $row = DB::table('general')->where('id', $zhaoyun)->first();
        $this->assertSame(\Hanjiang\MasterData\Book::ROWS[6]['addtype'], (int)$row['addtype']);
        $this->assertSame(\Hanjiang\MasterData\Book::ROWS[6]['addvalue'], (int)$row['addvalue']);
        $this->assertSame(30, (int)$row['level']);
        $this->assertSame(80, (int)$row['levelupexp']);

        // 已拥有同模板（旧版 -3）与模板不存在（旧版 -1）都不入库
        $this->assertNull(General::createFromTemplate($uid, 2));
        $this->assertNull(General::createFromTemplate($uid, 999999));
        $this->assertCount(2, DB::table('general')->where('uid', $uid)->get());

        DB::table('general')->where('uid', $uid)->delete();
    }

    public function testEquipmentCreationAndSlotGuard(): void
    {
        $uid = 990002;
        DB::table('equipment')->where('uid', $uid)->delete();

        // 旧版 add_item：模板字段复制，powerup = power，强化 0 级，未穿戴
        $ids = Equipment::addFromTemplate($uid, 3, 2);
        $this->assertCount(2, $ids);
        $tpl = \Hanjiang\MasterData\Equip::ROWS[3];
        foreach ($ids as $id) {
            $row = DB::table('equipment')->where('id', $id)->first();
            foreach (['name', 'type', 'quality', 'needlevel', 'power', 'saleprice'] as $col) {
                $this->assertEquals($tpl[$col], $row[$col], "addFromTemplate {$col}");
            }
            $this->assertSame($tpl['power'], (int)$row['powerup']);
            $this->assertSame(0, (int)$row['level']);
            $this->assertSame(0, (int)$row['generalid']);
            $this->assertNull($row['site']);
            $this->assertSame(0, (int)$row['hammer']);
            $this->assertSame('', $row['aid']);
        }
        $this->assertSame([], Equipment::addFromTemplate($uid, 999999));

        // 同一武将同一装配位置只能有一件：猛将武器(1)与谋士武器(2)同在 1 号位
        [$a, $b] = $ids;
        $mou = Equipment::addFromTemplate($uid, 35)[0]; // 军师之扇 type 2
        DB::table('equipment')->where('id', $a)->update(['generalid' => 777]);
        $this->assertSame(1, (int)DB::table('equipment')->where('id', $a)->first()['site']);
        foreach ([$b, $mou] as $dup) {
            try {
                DB::table('equipment')->where('id', $dup)->update(['generalid' => 777]);
                $this->fail('同一装配位置不应允许第二件装备');
            } catch (\PDOException $e) {
                $this->assertSame('23000', $e->getCode());
            }
        }
        // 软删除后释放装配位置
        DB::table('equipment')->where('id', $a)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        DB::table('equipment')->where('id', $mou)->update(['generalid' => 777]);
        $this->assertSame(1, (int)DB::table('equipment')->where('id', $mou)->first()['site']);

        DB::table('equipment')->where('uid', $uid)->delete();
    }

    public function testEquipSwapsWithinLegacySite(): void
    {
        $this->withFreshPlayer(function (int $uid): void {
            $gid = (int)$this->request('general', 'index')['list'][1]['id'];
            [$knife] = Equipment::addFromTemplate($uid, 1);  // 赤铜刀 type 1
            [$blade] = Equipment::addFromTemplate($uid, 52); // 血祭 type 1
            [$armor] = Equipment::addFromTemplate($uid, 2);  // 赤铜甲 type 3

            $this->assertSame(1, $this->request('general', 'equip', ['gid' => $gid, 'eid' => $knife])['status']);
            $res = $this->request('general', 'equip', ['gid' => $gid, 'eid' => $blade]);
            $this->assertSame(1, $res['status']);
            $this->assertSame(0, (int)DB::table('equipment')->where('id', $knife)->first()['generalid'], '同位置旧装备应被卸下');
            $this->assertSame($blade, (int)$res['info']['eid1']['id']);

            // eid1~eid4 按旧版 get_item_site 派生：铠甲(type 3) 在 eid2
            $res = $this->request('general', 'equip', ['gid' => $gid, 'eid' => $armor]);
            $this->assertSame(1, $res['status']);
            $this->assertSame($armor, (int)$res['info']['eid2']['id']);
        });
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

    // ---------------------------------------------------------------
    // 切磋（旧版 controllers/challenge.php）
    // ---------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function messagesOf(int $uid): array
    {
        return Redis::getJson('user_messages:' . $uid) ?: [];
    }

    public function testChallengeGuardsAndDeterministicResults(): void
    {
        $a = $this->newGuestPlayer();
        $b = $this->newGuestPlayer();
        $silver = (int)DB::table('member')->where('uid', $a['uid'])->value('silver');

        // -1 没传对方 uid / 是自己；-2 对方不存在
        $strip = static function (array $res): array {
            unset($res['message'], $res['msg']);
            return $res;
        };
        $this->assertSame(['status' => -1], $strip($this->request('challenge', 'action', ['token' => $a['auth']['token'], 'uid' => 0])));
        $this->assertSame(['status' => -1], $strip($this->request('challenge', 'action', $a['auth'])));
        $this->assertSame(['status' => -2], $strip($this->request('challenge', 'action', ['token' => $a['auth']['token'], 'uid' => 999999999])));

        // 我方远强于对方：对方阵法没有武将（空队伍直接判 3 完胜），对方信件为「惨遭失败」
        $this->emptyFormation($b['uid']);
        $res = $this->request('challenge', 'action', ['token' => $a['auth']['token'], 'uid' => $b['uid']]);
        $this->assertSame(1, $res['status']);
        $this->assertSame(3, $res['info']['win']);
        $this->assertSame(3, $res['battle']['assess']);
        $this->assertSame([], $res['battle']['rightteam']);
        $self = DB::table('member')->where('uid', $a['uid'])->first();
        $to = DB::table('member')->where('uid', $b['uid'])->first();
        $this->assertSame(['name' => $self['nickname'], 'level' => (int)$self['level'], 'gender' => (int)$self['gender']], $res['battle']['left_role']);
        $this->assertSame(['name' => $to['nickname'], 'level' => (int)$to['level'], 'gender' => (int)$to['gender']], $res['battle']['right_role']);
        $this->assertSame((int)$to['gender'], $res['info']['gender']);
        $this->assertGreaterThan(0, $res['info']['report']);
        $this->assertSame(['id' => $res['info']['report'], 'type' => 'challenge'], $res['share']);
        $msg = array_values(array_filter($this->messagesOf($b['uid']), static fn(array $m): bool => (int)$m['id'] === $res['info']['report']));
        $this->assertCount(1, $msg);
        $this->assertSame(4, $msg[0]['type']);
        $this->assertSame($a['uid'], $msg[0]['fromuid']);
        $this->assertSame('<em uid="' . $a['uid'] . '">' . $self['nickname'] . '</em>跟您切磋了一把，你惨遭失败。', $msg[0]['message']);
        // 不消耗、不奖励
        $this->assertSame($silver, (int)DB::table('member')->where('uid', $a['uid'])->value('silver'));

        // 对方没有阵法（右侧 -1）：旧版不战斗，win 为 null，battle 只有双方信息，信件按平局
        DB::table('member_field')->where('uid', $b['uid'])->update(['matrixid' => 0]);
        $res = $this->request('challenge', 'action', ['token' => $a['auth']['token'], 'uid' => $b['uid']]);
        $this->assertSame(1, $res['status']);
        $this->assertNull($res['info']['win']);
        $this->assertSame(['left_role', 'right_role'], array_keys($res['battle']));
        $last = $this->messagesOf($b['uid']);
        $this->assertStringEndsWith('跟您切磋了一把，双方实力相当，本次平局。', end($last)['message']);

        // 我方阵法没有武将（左侧 -2 按空队伍）：判 -3 大败，对方信件为「大获全胜」
        DB::table('member_field')->where('uid', $b['uid'])->update(['matrixid' => 1]);
        $this->emptyFormation($a['uid']);
        DB::table('matrix')->where('uid', $b['uid'])->update(['position2' => (int)DB::table('general')->where('uid', $b['uid'])->value('id')]);
        $res = $this->request('challenge', 'action', ['token' => $a['auth']['token'], 'uid' => $b['uid']]);
        $this->assertSame(-3, $res['info']['win']);
        $last = $this->messagesOf($b['uid']);
        $this->assertStringEndsWith('跟您切磋了一把，你大获全胜。', end($last)['message']);
    }

    public function testChallengeRealBattleReport(): void
    {
        $a = $this->newGuestPlayer();
        $b = $this->newGuestPlayer();
        $res = $this->request('challenge', 'action', ['token' => $a['auth']['token'], 'uid' => $b['uid']]);
        $this->assertSame(1, $res['status']);
        // 战报结构同演武榜（battle_model->battle_pk）
        foreach (['leftteam', 'rightteam', 'process', 'assess', 'leftresult', 'rightresult', 'leftbuff', 'rightbuff', 'left_role', 'right_role'] as $key) {
            $this->assertArrayHasKey($key, $res['battle']);
        }
        $this->assertNotEmpty($res['battle']['leftteam']);
        $this->assertNotEmpty($res['battle']['rightteam']);
        $this->assertNotEmpty($res['battle']['process']);
        $this->assertSame($res['battle']['assess'], $res['info']['win']);
        $this->assertContains($res['info']['win'], [-3, -2, -1, 0, 1, 2, 3]);
    }

    // ---------------------------------------------------------------
    // 天下史诗（旧版 controllers/epic.php + models/epic_model.php）
    // ---------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function epicRequest(array $p, string $method, array $params = []): array
    {
        $res = $this->request('epic', $method, $p['auth'] + $params);
        unset($res['message'], $res['msg']);
        return $res;
    }

    /**
     * @return array<string, mixed>
     */
    private function epicRow(int $uid): array
    {
        return DB::table('epic_log')->where('uid', $uid)->first();
    }

    /**
     * 找一个让 refresh 的下一次 mt_rand(0, 1390) 落在 (min, max] 的种子（残卷 id 按权重累加的区间）
     */
    private function epicSeed(int $min, int $max): int
    {
        for ($seed = 1; ; $seed++) {
            mt_srand($seed);
            $n = mt_rand(0, 1390);
            if ($n > $min && $n <= $max) {
                return $seed;
            }
        }
    }

    public function testEpicIndexSelectAndBuy(): void
    {
        // 主公 150 级开放
        $low = $this->newGuestPlayer();
        DB::table('member')->where('uid', $low['uid'])->update(['level' => 149]);
        foreach (['index', 'select_epic', 'refresh', 'force', 'buy_lock_times', 'buy_refresh_times'] as $m) {
            $this->assertSame(-1, $this->epicRequest($low, $m, ['id' => 1])['status'], $m);
        }
        $this->assertNull(DB::table('epic_log')->where('uid', $low['uid'])->first());

        $p = $this->newGuestPlayer();
        DB::table('member')->where('uid', $p['uid'])->update(['level' => 150, 'vip' => 1, 'gold' => 1000]);
        $res = $this->epicRequest($p, 'index');
        $this->assertSame(1, $res['status']);
        $this->assertSame(0, $res['get_price']);
        $this->assertSame(10, $res['times']);
        $this->assertSame(10, $res['refreshprice']);
        $this->assertSame(5, $res['lockprice']);
        $this->assertSame(array_values(\Hanjiang\MasterData\Epic::ROWS), $res['list']);
        $this->assertSame([
            'uid' => $p['uid'], 'refreshtimes' => 10, 'locktimes' => 5, 'epicid' => 0,
            'site1' => 0, 'site2' => 0, 'site3' => 0, 'site4' => 0, 'site5' => 0, 'lock_site' => '',
            'site1_lock' => 0, 'site2_lock' => 0, 'site3_lock' => 0, 'site4_lock' => 0, 'site5_lock' => 0,
        ], $res['member']);

        // 未选择史诗
        $this->assertSame(-3, $this->epicRequest($p, 'refresh')['status']);
        $this->assertSame(['status' => -3], $this->epicRequest($p, 'force'));
        // 选择背景图
        $this->assertSame(['status' => -2], $this->epicRequest($p, 'select_epic', ['id' => 0]));
        $this->assertSame(['status' => -5], $this->epicRequest($p, 'select_epic', ['id' => 99]));
        $this->assertSame(['status' => 1, 'epicid' => 2], $this->epicRequest($p, 'select_epic', ['id' => 2]));
        $this->assertSame(['status' => -4], $this->epicRequest($p, 'select_epic', ['id' => 1]));
        $this->assertSame(500, $this->epicRequest($p, 'index')['get_price']);

        // 购买刷新次数：10 元宝 + 1 次
        $buy = $this->epicRequest($p, 'buy_refresh_times');
        $this->assertSame(['status' => 1, 'refreshtimes' => 11, 'all_gold' => 990], $buy);
        $this->assertSame(1, $this->epicRow($p['uid'])['buy_refreshtimes']);
        // 购买锁定次数：t = 1 只返回价格
        $this->assertSame(['status' => 1, 'price' => 15, 'buy_times' => 3, 'locktimes' => 5, 'all_gold' => 990], $this->epicRequest($p, 'buy_lock_times', ['t' => 1, 'num' => 3]));
        $this->assertSame(['status' => 1, 'price' => 15, 'buy_times' => 3, 'locktimes' => 8, 'all_gold' => 975], $this->epicRequest($p, 'buy_lock_times', ['num' => 3]));
        $this->assertSame(['status' => 1, 'price' => 5, 'buy_times' => 1, 'locktimes' => 9, 'all_gold' => 970], $this->epicRequest($p, 'buy_lock_times'));
        DB::table('member')->where('uid', $p['uid'])->update(['gold' => 9]);
        $this->assertSame(['status' => -3], $this->epicRequest($p, 'buy_refresh_times'));
        $this->assertSame(['status' => -3], $this->epicRequest($p, 'buy_lock_times', ['num' => 2]));
        $this->assertSame(9, (int)DB::table('member')->where('uid', $p['uid'])->value('gold'));

        // 强行领取：史诗 2 花 500 元宝得 1000 声望，拼图清空
        $this->assertSame(['status' => -5], $this->epicRequest($p, 'force'));
        DB::table('member')->where('uid', $p['uid'])->update(['gold' => 600, 'reputation' => 0]);
        DB::table('epic_log')->where('uid', $p['uid'])->update(['site1' => 6, 'lock_site' => '1']);
        $force = $this->epicRequest($p, 'force');
        $this->assertSame(1, $force['status']);
        $this->assertSame(1000, $force['add']);
        $this->assertSame(1000, $force['reputation']);
        $this->assertSame(100, $force['all_gold']);
        $this->assertSame([
            'uid' => $p['uid'], 'refreshtimes' => 11, 'locktimes' => 9, 'epicid' => 0,
            'site1' => 0, 'site2' => 0, 'site3' => 0, 'site4' => 0, 'site5' => 0, 'lock_site' => '',
        ], $force['member']);
        $this->assertSame(0, $this->epicRow($p['uid'])['epicid']);

        // 每日重置：刷新 / 锁定次数按 VIP 重置（VIP 13：55 / 50），购买的次数保留
        DB::table('epic_log')->where('uid', $p['uid'])->update(['dateline' => time() - 86400, 'refreshtimes' => 0, 'locktimes' => 0]);
        DB::table('member')->where('uid', $p['uid'])->update(['vip' => 13]);
        $member = $this->epicRequest($p, 'index')['member'];
        $this->assertSame(55 + 1, $member['refreshtimes']);
        $this->assertSame(50 + 4, $member['locktimes']);
    }

    public function testEpicRefreshCompletesAndDeductsTimes(): void
    {
        $p = $this->newGuestPlayer();
        DB::table('member')->where('uid', $p['uid'])->update(['level' => 150, 'vip' => 1, 'reputation' => 0]);
        $this->epicRequest($p, 'index');
        $this->epicRequest($p, 'select_epic', ['id' => 1]);

        // 5 个位置全部锁定 / 锁定次数不足 / 刷新次数不足
        $this->assertSame(-4, $this->epicRequest($p, 'refresh', ['l' => '1,2,3,4,5'])['status']);
        DB::table('epic_log')->where('uid', $p['uid'])->update(['locktimes' => 1]);
        $res = $this->epicRequest($p, 'refresh', ['l' => '1,2']);
        $this->assertSame(['status' => -6, 'complete' => 0, 'add_reputation' => 0, 'all_reputation' => 0], $res);
        DB::table('epic_log')->where('uid', $p['uid'])->update(['refreshtimes' => 0]);
        $this->assertSame(-5, $this->epicRequest($p, 'refresh')['status']);

        // 没有锁定：5 个位置都重新掷（残卷 1 的区间是 [0, 100]），扣 1 次免费刷新
        DB::table('epic_log')->where('uid', $p['uid'])->update(['refreshtimes' => 10, 'locktimes' => 5]);
        $seed = $this->epicSeed(-1, 100);
        mt_srand($seed);
        $res = $this->epicRequest($p, 'refresh');
        $this->assertSame(1, $res['status']);
        $this->assertSame(0, $res['complete']);
        $this->assertSame(1, $res['member']['site1']);
        $this->assertSame(2, $res['member']['site1_lock'], '位置 1 正好是史诗 1 的第 1 张残卷，提示可锁定');
        $this->assertSame(9, $res['member']['refreshtimes']);
        $this->assertSame(5, $res['member']['locktimes']);
        $row = $this->epicRow($p['uid']);
        $this->assertSame(9, $row['refreshtimes']);
        for ($i = 1; $i <= 5; $i++) {
            $this->assertSame($res['member']['site' . $i], $row['site' . $i]);
        }

        // 锁定 1~4（已经是残卷 1~4），只掷位置 5：落在残卷 5 的区间 (400, 500] 即完成，得 500 声望并清空
        DB::table('epic_log')->where('uid', $p['uid'])->update(['site1' => 1, 'site2' => 2, 'site3' => 3, 'site4' => 4, 'site5' => 0, 'locktimes' => 2, 'buy_locktimes' => 3]);
        mt_srand($this->epicSeed(400, 500));
        $res = $this->epicRequest($p, 'refresh', ['l' => '1,2,3,4']);
        $this->assertSame(1, $res['status']);
        $this->assertSame(1, $res['complete']);
        $this->assertSame(500, $res['add_reputation']);
        $this->assertSame(500, $res['all_reputation']);
        $this->assertSame(0, $res['member']['epicid']);
        $this->assertSame('', $res['member']['lock_site']);
        // 锁定次数先扣免费（2）再扣购买（3 - 2）
        $this->assertSame(0 + 1, $res['member']['locktimes']);
        $row = $this->epicRow($p['uid']);
        $this->assertSame([0, 1, 0, 0, ''], [$row['locktimes'], $row['buy_locktimes'], $row['epicid'], $row['site1'], $row['lock_site']]);
        $this->assertSame(500, (int)DB::table('member')->where('uid', $p['uid'])->value('reputation'));

        // 免费刷新次数用完后扣购买的次数；没完成时记录锁定位置
        $this->epicRequest($p, 'select_epic', ['id' => 9]);
        DB::table('epic_log')->where('uid', $p['uid'])->update(['refreshtimes' => 0, 'buy_refreshtimes' => 2, 'locktimes' => 5, 'buy_locktimes' => 0]);
        $res = $this->epicRequest($p, 'refresh', ['l' => '2']);
        $this->assertSame(1, $res['status']);
        $this->assertSame(1, $res['member']['site2_lock']);
        $row = $this->epicRow($p['uid']);
        $this->assertSame([0, 1, 4, '2'], [$row['refreshtimes'], $row['buy_refreshtimes'], $row['locktimes'], $row['lock_site']]);
    }

    // ---------------------------------------------------------------
    // 过关斩将（旧版 controllers/pass_general.php + models/passgeneral_model.php + cron_acttask::pass_general）
    // ---------------------------------------------------------------

    /** 活动期间（activity_time pass_general：0 ~ 2016-12-31 00:00） */
    private function passOpenAt(string $datetime = '2016-06-01 12:00:00'): int
    {
        $ts = (int)strtotime($datetime);
        \Hanjiang\Model\BossClock::freeze($ts);
        return $ts;
    }

    /**
     * @return array<string, mixed>
     */
    private function passRequest(array $p, string $method, array $params = []): array
    {
        // 响应信封补的 message（以及成功时的 msg = ok）去掉；失败时的 msg 是旧版语言包文案，保留
        $res = $this->request('pass_general', $method, $p['auth'] + $params);
        unset($res['message']);
        if (($res['msg'] ?? null) === 'ok') {
            unset($res['msg']);
        }
        return $res;
    }

    /**
     * @return array<string, int>
     */
    private function passRow(int $uid): array
    {
        return array_map('intval', DB::table('passgeneral')->where('uid', $uid)->where('version', 34)->first());
    }

    /**
     * @return array{uid: int, auth: array{token: string, uid: int}}
     */
    private function passPlayer(int $gold = 100000): array
    {
        $p = $this->newGuestPlayer();
        DB::table('member')->where('uid', $p['uid'])->update(['gold' => $gold, 'silver' => 0, 'reputation' => 0]);
        foreach (['_passgeneral_action_lock', '_passgeneral_lottery_lock', '_passgeneral_acieve_action_lock', '_passgeneral_reward_action_lock'] as $key) {
            Redis::del($key . $p['uid']);
        }
        return $p;
    }

    private function passLottery(int $uid): int
    {
        return (int)DB::table('member_integral')->where('uid', $uid)->value('act_kemari_int');
    }

    private function passJade(int $uid, int $type): int
    {
        return \Hanjiang\Model\TraderLog::jade($uid, $type);
    }

    /**
     * 找一个让过人掷点 mt_rand(0, 100) 满足 $want 的种子
     */
    private function passSeed(callable $want): int
    {
        for ($seed = 1; ; $seed++) {
            mt_srand($seed);
            if ($want(mt_rand(0, 100))) {
                return $seed;
            }
        }
    }

    public function testPassGeneralClosedInProduction(): void
    {
        // 活动 2016-12-31 结束：现在一律 -1（action 旧版不解锁，30 秒内再请求返回 2「操作太快了」）
        $p = $this->passPlayer();
        foreach (['index', 'achievement_reward', 'reward_info', 'lottery_index', 'action_achievement_reward', 'action_reward', 'action_lottery'] as $m) {
            $this->assertSame(['status' => -1, 'msg' => '活动未开启！'], $this->passRequest($p, $m, ['id' => 1, 'aid' => 1]), $m);
        }
        $this->assertSame(['status' => -1, 'msg' => '活动未开启！'], $this->passRequest($p, 'action', ['id' => 1, 'type' => 1]));
        $this->assertSame(['status' => 2, 'msg' => '操作太快了！'], $this->passRequest($p, 'action', ['id' => 1, 'type' => 1]));
        $this->assertNull(DB::table('passgeneral')->where('uid', $p['uid'])->first());
        $this->assertSame(0, \Hanjiang\Cron\PassGeneralCron::updatePassGeneralInfo(\Hanjiang\Model\PassGeneral::activity(), time()));
    }

    public function testPassGeneralIndexAndAction(): void
    {
        $now = $this->passOpenAt();
        try {
            $p = $this->passPlayer();
            $res = $this->passRequest($p, 'index');
            $this->assertSame(1, $res['status']);
            $this->assertSame(1, $res['pass_id']);
            $this->assertSame('01月01日-12月31日', $res['time']);
            $this->assertSame(0, $res['achieve_popo']);
            $this->assertCount(11, $res['pass_info']);
            $this->assertSame(['id' => 1, 'name' => \Hanjiang\MasterData\General::ROWS[106]['name'], 'image' => \Hanjiang\MasterData\General::ROWS[106]['image'], 'show_status' => 2], $res['pass_info'][0]);
            $this->assertSame(3, $res['pass_info'][10]['show_status']);
            $this->assertSame([
                ['reward_status' => 1, 'box_id' => 1], ['reward_status' => 1, 'box_id' => 2],
                ['reward_status' => 1, 'box_id' => 3], ['reward_status' => 1, 'box_id' => 4],
            ], $res['reward_info']);
            // 3 次免费过人；强行突破没有免费次数也没有 CD（旧版 cd = 0 - 当前时间）
            $this->assertSame([
                ['id' => 1, 'cd' => 0, 'price' => 0, 'times' => 3],
                ['id' => 2, 'cd' => -$now, 'price' => 250, 'times' => 0],
            ], $res['price_list']);
            $row = $this->passRow($p['uid']);
            $this->assertSame([0, 3, 1, 0, 0, 1], [$row['num'], $row['free_times1'], $row['pass_id'], $row['is_pass'], $row['succ_num'], $row['is_get']]);

            // 参数 / 进度校验
            $this->assertSame(['status' => -2, 'msg' => '传参类型错误'], $this->passRequest($p, 'action', ['id' => 0, 'type' => 1]));
            $this->assertSame(['status' => -2, 'msg' => '传参类型错误'], $this->passRequest($p, 'action', ['id' => 1, 'type' => 3]));
            $this->assertSame(['status' => 4, 'msg' => '您不能挑战该球员'], $this->passRequest($p, 'action', ['id' => 2, 'type' => 1]));
            // 防刷锁
            Redis::set('_passgeneral_action_lock' . $p['uid'], 1, 30);
            $this->assertSame(['status' => 2, 'msg' => '操作太快了！'], $this->passRequest($p, 'action', ['id' => 1, 'type' => 1]));
            Redis::del('_passgeneral_action_lock' . $p['uid']);

            // 免费过人失败（掷点 > 80）：不扣元宝，退回本层第一名守将，免费次数 - 1，30 分钟 CD
            mt_srand($this->passSeed(static fn(int $n): bool => $n > 80));
            $res = $this->passRequest($p, 'action', ['id' => 1, 'type' => 1]);
            $this->assertSame(1, $res['status']);
            $this->assertSame(0, $res['is_succ']);
            $this->assertSame(1, $res['pass_id']);
            $this->assertSame(100000, $res['all_gold']);
            $this->assertSame(['id' => 1, 'cd' => 1800, 'price' => 125, 'times' => 2], $res['price_list'][0]);
            $this->assertContains($res['reward']['type'], array_column(array_filter(\Hanjiang\MasterData\PassgeneralReward::ROWS, static fn(array $r): bool => $r['num'] === 1 && $r['is_succ'] === 0), 'type'));
            $this->assertArrayNotHasKey('rate', $res['reward']);
            $log = DB::table('passgeneral_log')->where('uid', $p['uid'])->get();
            $this->assertCount(1, $log);
            $this->assertSame([34, 1, 1, 106, 0, $res['reward']['type'], $res['reward']['value'], $now], [(int)$log[0]['version'], (int)$log[0]['num'], (int)$log[0]['num_id'], (int)$log[0]['g_id'], (int)$log[0]['is_succ'], (int)$log[0]['type'], (int)$log[0]['value'], (int)$log[0]['dateline']]);
            $row = $this->passRow($p['uid']);
            $this->assertSame([2, $now + 1800, 1, 0], [$row['free_times1'], $row['normal_time1'], $row['pass_id'], $row['is_pass']]);

            // CD 中普通过人扣 price1（125）；成功进到下一名守将
            mt_srand($this->passSeed(static fn(int $n): bool => $n <= 80));
            $res = $this->passRequest($p, 'action', ['id' => 1, 'type' => 1]);
            $this->assertSame(1, $res['is_succ']);
            $this->assertSame(2, $res['pass_id']);
            $this->assertSame(100000 - 125, $res['all_gold']);
            $this->assertSame(2, $this->passRow($p['uid'])['free_times1'], 'CD 中不消耗免费次数');
            $this->assertSame([1, 2, 3], array_column(array_slice($res['pass_info'], 0, 3), 'show_status'));

            // 强行突破（price2，必定成功）：本层最后一名守将突破后宝箱可领取，未领取前不能继续
            DB::table('passgeneral')->where('uid', $p['uid'])->update(['pass_id' => 4, 'is_pass' => 3]);
            $res = $this->passRequest($p, 'action', ['id' => 4, 'type' => 2]);
            $this->assertSame(1, $res['is_succ']);
            $this->assertSame(5, $res['pass_id']);
            $this->assertSame(100000 - 125 - 400, $res['all_gold']);
            $this->assertSame(2, $res['reward_info'][0]['reward_status']);
            $this->assertSame(['status' => 4, 'msg' => '请先领取您的宝箱奖励'], $this->passRequest($p, 'action', ['id' => 5, 'type' => 2]));

            // 元宝不足
            DB::table('passgeneral')->where('uid', $p['uid'])->update(['is_get' => 1]);
            DB::table('member')->where('uid', $p['uid'])->update(['gold' => 299]);
            $this->assertSame(['status' => 3, 'msg' => '元宝不足'], $this->passRequest($p, 'action', ['id' => 5, 'type' => 2]));
            $this->assertSame(['status' => 4, 'msg' => '已经突破过该球员'], $this->passRequest($p, 'action', ['id' => 4, 'type' => 2]));

            // 突破最后一名守将：pass_id 12、通关次数 + 1、第 4 层宝箱可领取、成就可领取
            DB::table('member')->where('uid', $p['uid'])->update(['gold' => 100000]);
            DB::table('passgeneral')->where('uid', $p['uid'])->update(['num' => 3, 'pass_id' => 11, 'is_pass' => 10]);
            $res = $this->passRequest($p, 'action', ['id' => 11, 'type' => 2]);
            $this->assertSame(12, $res['pass_id']);
            $this->assertSame(1, $res['achieve_popo']);
            $this->assertSame([1], array_values(array_unique(array_column($res['pass_info'], 'show_status'))));
            $this->assertSame(['id' => 1, 'cd' => 1800, 'price' => 125, 'times' => 2], $res['price_list'][0], '全部突破后按第 1 名守将的价格');
            $row = $this->passRow($p['uid']);
            $this->assertSame([12, 11, 1, 2], [$row['pass_id'], $row['is_pass'], $row['succ_num'], $row['is_get']]);
        } finally {
            \Hanjiang\Model\BossClock::freeze(null);
        }
    }

    public function testPassGeneralActionRewardGrantsByType(): void
    {
        $this->passOpenAt();
        try {
            $p = $this->passPlayer();
            $this->passRequest($p, 'index');
            // 强行突破第 1 名守将（必定成功），按奖励类型核对发放
            for ($round = 0; $round < 8; $round++) {
                DB::table('passgeneral')->where('uid', $p['uid'])->update(['pass_id' => 1, 'is_pass' => 0, 'is_get' => 1, 'num' => 0]);
                $before = [
                    'lottery' => $this->passLottery($p['uid']),
                    'yellow' => $this->passJade($p['uid'], 2),
                    'soul' => (int)DB::table('member')->where('uid', $p['uid'])->value('soul'),
                    'freeleap' => (int)DB::table('member_field')->where('uid', $p['uid'])->value('freeleap'),
                    'gem' => \Hanjiang\Model\OwnGem::chip($p['uid']),
                ];
                $res = $this->passRequest($p, 'action', ['id' => 1, 'type' => 2]);
                $this->assertSame(1, $res['status']);
                $reward = $res['reward'];
                $value = $reward['value'];
                match ($reward['type']) {
                    27 => $this->assertSame($before['lottery'] + $value, $this->passLottery($p['uid'])),
                    3 => $this->assertSame($before['yellow'] + $value, $this->passJade($p['uid'], 2)),
                    5 => $this->assertSame($before['soul'] + $value, (int)DB::table('member')->where('uid', $p['uid'])->value('soul')),
                    10 => $this->assertSame($before['freeleap'] + $value, (int)DB::table('member_field')->where('uid', $p['uid'])->value('freeleap')),
                    9 => $this->assertSame([$before['gem'] + $value, 'pass_general', 'action'], [\Hanjiang\Model\OwnGem::chip($p['uid']), ($g = DB::table('gem_log')->where('uid', $p['uid'])->orderBy('id', 'DESC')->first())['class'], $g['method']], '宝石碎片 member.gem'),
                    26 => $this->assertSame($value, $reward['general']['generalid']),
                };
                if ($reward['type'] === 27) {
                    $log = DB::table('integral_log')->where('uid', $p['uid'])->orderBy('id', 'DESC')->first();
                    $this->assertSame([1, $value, $this->passLottery($p['uid']), 'pass_general', 'action', 'act_kemari_int'], [(int)$log['type'], (int)$log['intnum'], (int)$log['balance'], $log['class'], $log['method'], $log['argument']]);
                }
            }
        } finally {
            \Hanjiang\Model\BossClock::freeze(null);
        }
    }

    /**
     * 过关斩将奖励的广播：紫装以上装备小喇叭；7 星以上武将小喇叭 + 聊天室 45，神将 46
     */
    public function testPassGeneralRewardBroadcasts(): void
    {
        $p = $this->passPlayer();
        $member = DB::table('member')->where('uid', $p['uid'])->first();
        $nick = (string)$member['nickname'];
        $w = $this->chatPlayer();
        $this->chatOnline($w);
        $add = new \ReflectionMethod(\Hanjiang\Handler\Campaign\PassGeneralHandler::class, 'addReward');
        $equip = \Hanjiang\MasterData\Equip::ROWS[15];
        $add->invoke(null, $member, ['type' => 8, 'id' => 15, 'value' => 1], 'action', time());
        $add->invoke(null, $member, ['type' => 8, 'id' => 1, 'value' => 1], 'action', time());
        $add->invoke(null, $member, ['type' => 12, 'id' => 6, 'value' => 1], 'action', time());
        $this->assertSame([
            [2, '恭喜【' . $nick . '】在过关斩将中获得装备-<em class="q5">' . $equip['name'] . '</em>', 0, $p['uid']],
            [2, '恭喜玩家【' . $nick . '】在过关斩将中获得八星武将-赵云', 0, $p['uid']],
            [7, '恭喜【{1,' . $nick . ' ,' . $p['uid'] . '}】在过关斩将中获得八星武将-{9,赵云}', 0, $p['uid']],
        ], $this->chatMessages($w));
        DB::table('general')->where('uid', $p['uid'])->where('generalid', 6)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $add->invoke(null, $member, ['type' => 26, 'id' => 6, 'value' => 1], 'action', time());
        $this->assertSame([
            [2, '恭喜玩家【' . $nick . '】在过关斩将中获得八星神将-赵云', 0, $p['uid']],
            [7, '恭喜【{1,' . $nick . ' ,' . $p['uid'] . '}】在过关斩将中获得八星神将-{9,赵云}', 0, $p['uid']],
        ], $this->chatMessages($w));
    }

    public function testPassGeneralRandArrIntervals(): void
    {
        // 区间 [k + 1, rate × 10 + k]，随机数须大于下限（下限本身落空）；最多掷 count 次，凑够 1 个即停
        $rows = [['id' => 2, 'rate' => 1.0], ['id' => 1, 'rate' => 1.0]];
        $pick = static function (int $n): int {
            // 排序后 id 1 在前：id 1 为 [1, 10]、id 2 为 [11, 20]
            return match (true) {
                $n > 1 && $n <= 10 => 1,
                $n > 11 && $n <= 20 => 2,
                default => 0,
            };
        };
        $seen = [];
        for ($seed = 1; $seed <= 300; $seed++) {
            mt_srand($seed);
            $n = mt_rand(1, 10);
            mt_srand($seed);
            $one = \Hanjiang\Model\PassGeneral::randArr([$rows[0]], 1.0, 1)['info'];
            $this->assertSame($n > 1 ? [2] : [], array_column($one, 'id'), "seed {$seed}");

            mt_srand($seed);
            $want = $pick(mt_rand(1, 20));
            if ($want === 0) {
                $want = $pick(mt_rand(1, 20));
            }
            mt_srand($seed);
            $two = \Hanjiang\Model\PassGeneral::randArr($rows, 2.0, 1)['info'];
            $this->assertSame($want === 0 ? [] : [$want], array_column($two, 'id'), "seed {$seed}");
            $seen[$want] = true;
        }
        $this->assertArrayHasKey(0, $seen, '两次都落在下限上时没有奖励');
        $this->assertArrayHasKey(1, $seen);
        $this->assertArrayHasKey(2, $seen);
    }

    public function testPassGeneralBoxRewards(): void
    {
        $now = $this->passOpenAt();
        try {
            $p = $this->passPlayer();
            $this->passRequest($p, 'index');

            // 宝箱预览：第 1 层固定 3 件（兑奖券 10、紫宝石 50、声望 5000）
            $preview = $this->passRequest($p, 'reward_info', ['id' => 1]);
            $this->assertSame(1, $preview['status']);
            $this->assertSame('第1层奖励', $preview['info']);
            $this->assertSame(1, $preview['reward_status']);
            $this->assertSame([
                ['type' => 27, 'value' => 10, 'name' => '兑奖券'],
                ['type' => 4, 'value' => 50, 'name' => '紫宝石'],
                ['type' => 7, 'value' => 5000, 'name' => '声望'],
            ], $preview['reward']);
            $this->assertSame(['status' => 2, 'msg' => '传参类型错误'], $this->passRequest($p, 'reward_info', ['id' => 0]));
            $this->assertSame(['status' => -2, 'msg' => '获取宝箱奖励失败'], $this->passRequest($p, 'reward_info', ['id' => 5]));
            // 超级奖励：同类型只列第一次出现的，武将按星级从高到低排在最后
            $super = $this->passRequest($p, 'reward_info', ['id' => 4]);
            $this->assertSame('超级奖励', $super['info']);
            $types = array_column($super['reward'], 'type');
            $this->assertSame([4, 10, 7, 27, 20, 17, 21, 18, 22, 19], array_slice($types, 0, 10));
            $generals = array_slice($super['reward'], 10 + 9);
            $stars = array_column($generals, 'star');
            $sorted = $stars;
            rsort($sorted);
            $this->assertSame($sorted, $stars);
            $this->assertArrayHasKey('talent', $generals[0]['general']);

            // 未达成 / 层数不对 / 已领取
            $this->assertSame(['status' => 2, 'msg' => '不能领取该宝箱奖励'], $this->passRequest($p, 'action_reward', ['id' => 1]));
            DB::table('passgeneral')->where('uid', $p['uid'])->update(['is_get' => 2, 'pass_id' => 5, 'is_pass' => 4]);
            $this->assertSame(['status' => 2, 'msg' => '不能领取该宝箱奖励'], $this->passRequest($p, 'action_reward', ['id' => 2]));
            $this->assertSame(['status' => -3, 'msg' => '传参类型错误'], $this->passRequest($p, 'action_reward', ['id' => 0]));
            $this->assertSame(2, $this->passRequest($p, 'reward_info', ['id' => 1])['reward_status']);

            $lottery = $this->passLottery($p['uid']);
            $purple = $this->passJade($p['uid'], 3);
            $res = $this->passRequest($p, 'action_reward', ['id' => 1]);
            $this->assertSame(1, $res['status']);
            $this->assertSame(5, $res['pass_id']);
            $this->assertSame(0, $res['is_full']);
            $this->assertSame([
                ['type' => 27, 'value' => 10, 'name' => '兑奖券'],
                ['type' => 4, 'value' => 50, 'name' => '紫宝石'],
                ['type' => 7, 'value' => 5000, 'name' => '声望'],
            ], $res['reward']);
            $this->assertSame([3, 1, 1, 1], array_column($res['reward_info'], 'reward_status'));
            $this->assertSame($lottery + 10, $this->passLottery($p['uid']));
            $this->assertSame($purple + 50, $this->passJade($p['uid'], 3));
            $this->assertSame(5000, (int)DB::table('member')->where('uid', $p['uid'])->value('reputation'));
            $row = $this->passRow($p['uid']);
            $this->assertSame([1, 1, 5], [$row['num'], $row['is_get'], $row['pass_id']]);
            // 旧版日志 value1 记的是 type1
            $log = DB::table('passgeneral_box_log')->where('uid', $p['uid'])->get();
            $this->assertCount(1, $log);
            $this->assertSame([0, 27, 27, 4, 50, 7, 5000, $now], [(int)$log[0]['num'], (int)$log[0]['type1'], (int)$log[0]['value1'], (int)$log[0]['type2'], (int)$log[0]['value2'], (int)$log[0]['type3'], (int)$log[0]['value3'], (int)$log[0]['dateline']]);
            $this->assertSame(['status' => 2, 'msg' => '不能领取该宝箱奖励'], $this->passRequest($p, 'action_reward', ['id' => 1]));
            $this->assertSame(3, $this->passRequest($p, 'reward_info', ['id' => 1])['reward_status']);

            // 第 4 层超级大奖：加权抽一行，领完回到第 1 名守将
            DB::table('passgeneral')->where('uid', $p['uid'])->update(['num' => 3, 'is_get' => 2, 'pass_id' => 12, 'is_pass' => 11]);
            $res = $this->passRequest($p, 'action_reward', ['id' => 4]);
            $this->assertSame(1, $res['status']);
            $this->assertSame(1, $res['pass_id']);
            $this->assertLessThanOrEqual(1, count($res['reward']));
            $row = $this->passRow($p['uid']);
            $this->assertSame([0, 1, 0, 1], [$row['num'], $row['pass_id'], $row['is_pass'], $row['is_get']]);
        } finally {
            \Hanjiang\Model\BossClock::freeze(null);
        }
    }

    public function testPassGeneralAchievements(): void
    {
        $now = $this->passOpenAt();
        try {
            $p = $this->passPlayer();
            $this->passRequest($p, 'index');
            DB::table('passgeneral')->where('uid', $p['uid'])->update(['succ_num' => 1]);

            $res = $this->passRequest($p, 'achievement_reward');
            $this->assertSame(1, $res['status']);
            $this->assertSame(1, $res['num']);
            $this->assertSame(array_column(\Hanjiang\MasterData\PassgeneralAchievement::ROWS, 'num'), $res['num_list']);
            $this->assertSame(['id' => 1, 'desc' => '突破所有人1次', 'open' => 1, 'type1' => 27, 'value1' => 20, 'type2' => 4, 'value2' => 100, 'type3' => 2, 'value3' => 5000000], $res['list'][0]);
            $this->assertSame(0, $res['list'][1]['open']);

            $this->assertSame(['status' => -3, 'msg' => '传参类型错误'], $this->passRequest($p, 'action_achievement_reward', ['aid' => 0]));
            $this->assertSame(['status' => -2, 'msg' => '获取奖券基础表信息失败'], $this->passRequest($p, 'action_achievement_reward', ['aid' => 99]));
            $this->assertSame(['status' => 2, 'msg' => '成就未达成'], $this->passRequest($p, 'action_achievement_reward', ['aid' => 2]));

            $lottery = $this->passLottery($p['uid']);
            $purple = $this->passJade($p['uid'], 3);
            $res = $this->passRequest($p, 'action_achievement_reward', ['aid' => 1]);
            $this->assertSame(['achieve_popo' => 0, 'status' => 1, 'all_gold' => 100000, 'all_silver' => 5000000], $res);
            $this->assertSame($lottery + 20, $this->passLottery($p['uid']));
            $this->assertSame($purple + 100, $this->passJade($p['uid'], 3));
            $this->assertSame(['status' => 3, 'msg' => '已经领取成就奖励'], $this->passRequest($p, 'action_achievement_reward', ['aid' => 1]));
            $this->assertSame(2, $this->passRequest($p, 'achievement_reward')['list'][0]['open']);
            $this->assertSame(1, DB::table('passgeneral_achievement_log')->where('uid', $p['uid'])->count());
            $log = DB::table('integral_log')->where('uid', $p['uid'])->orderBy('id', 'DESC')->first();
            $this->assertSame([1, 20, 'action_achievement_reward', $now], [(int)$log['type'], (int)$log['intnum'], $log['method'], (int)$log['dateline']]);
        } finally {
            \Hanjiang\Model\BossClock::freeze(null);
        }
    }

    public function testPassGeneralLotteryExchange(): void
    {
        $now = $this->passOpenAt();
        try {
            $p = $this->passPlayer();
            $res = $this->passRequest($p, 'lottery_index');
            $this->assertSame(1, $res['status']);
            $this->assertSame(0, $res['lottery_num']);
            $this->assertCount(count(\Hanjiang\MasterData\ActkemariExchangeLottery::ROWS), $res['list']);
            $this->assertSame(['type' => 2, 'value' => 2000000, 'lottery_num' => 10, 'id' => 1, 'name' => '银币', 'is_exch' => 0], $res['list'][0]);
            // 打造材料：value 为数量（value1），info 为材料模板
            $this->assertSame(1, $res['list'][3]['value']);
            $this->assertSame(23, $res['list'][3]['info']['materials_id']);
            $this->assertSame(62, $res['list'][22]['info']['id']);

            $this->assertSame(['status' => -3, 'msg' => '获取奖券基础表信息失败'], $this->passRequest($p, 'action_lottery', ['id' => 999]));
            $this->assertSame(['status' => 3, 'msg' => '兑奖券不足'], $this->passRequest($p, 'action_lottery', ['id' => 1]));

            DB::table('member_integral')->where('uid', $p['uid'])->update(['act_kemari_int' => 30]);
            $res = $this->passRequest($p, 'action_lottery', ['id' => 1]);
            $this->assertSame(1, $res['status']);
            $this->assertSame(20, $res['lottery_num']);
            $this->assertSame(['id' => 1, 'type' => 2, 'value' => 2000000, 'value1' => 0, 'lottery_num' => 10, 'is_exch' => 1], $res['reward']);
            $this->assertSame(1, $res['list'][0]['is_exch']);
            $this->assertSame(2000000, (int)DB::table('member')->where('uid', $p['uid'])->value('silver'));
            $log = DB::table('integral_log')->where('uid', $p['uid'])->orderBy('id', 'DESC')->first();
            $this->assertSame([2, 10, 20, 'action_lottery'], [(int)$log['type'], (int)$log['intnum'], (int)$log['balance'], $log['method']]);
            $ex = DB::table('passgeneral_exchange_log')->where('uid', $p['uid'])->first();
            $this->assertSame([34, 2, 2000000, 10, $now], [(int)$ex['version'], (int)$ex['type'], (int)$ex['value'], (int)$ex['lottery_num'], (int)$ex['dateline']]);

            // 打造材料：发到 own_materials
            $res = $this->passRequest($p, 'action_lottery', ['id' => 4]);
            $this->assertSame(1, $res['status']);
            $this->assertSame(2, $res['lottery_num']);
            $this->assertSame(1, $res['reward']['value']);
            $this->assertSame(23, $res['reward']['id']);
            $this->assertSame(23, $res['reward']['equipmairt']['materials_id']);
            $this->assertSame(1, (int)DB::table('own_materials')->where('uid', $p['uid'])->where('materials_id', 23)->value('num'));

            // 参数错误：旧版解错了锁，30 秒内再兑换返回 2
            $this->assertSame(['status' => -2, 'msg' => '传参类型错误'], $this->passRequest($p, 'action_lottery', ['id' => 0]));
            $this->assertSame(['status' => 2, 'msg' => '操作太快了！'], $this->passRequest($p, 'action_lottery', ['id' => 1]));
        } finally {
            \Hanjiang\Model\BossClock::freeze(null);
        }
    }

    public function testPassGeneralDailyResetAndCron(): void
    {
        $now = $this->passOpenAt();
        try {
            // 请求时的每日清理：刷新时间不是今天，未领取的宝箱自动发放并发信，再重置进度与免费次数
            $p = $this->passPlayer();
            $this->passRequest($p, 'index');
            DB::table('passgeneral')->where('uid', $p['uid'])->update([
                'refresh_time' => $now - 86400, 'is_get' => 2, 'num' => 0, 'pass_id' => 5, 'is_pass' => 4, 'free_times1' => 0, 'normal_time1' => $now + 600, 'succ_num' => 3,
            ]);
            $lottery = $this->passLottery($p['uid']);
            $res = $this->passRequest($p, 'index');
            $this->assertSame(1, $res['pass_id']);
            $row = $this->passRow($p['uid']);
            $this->assertSame([0, 1, 0, 1, 3, 0, $now, 3], [$row['num'], $row['pass_id'], $row['is_pass'], $row['is_get'], $row['free_times1'], $row['normal_time1'], $row['refresh_time'], $row['succ_num']]);
            $this->assertSame($lottery + 10, $this->passLottery($p['uid']));
            $this->assertSame(5000, (int)DB::table('member')->where('uid', $p['uid'])->value('reputation'));
            $messages = $this->messagesOf($p['uid']);
            $this->assertSame('亲爱的玩家！您今天未领取的过关斩将奖励，系统已自动领取，共获得：兑奖券10个，紫宝石50个，声望5000个，请查收!', end($messages)['message']);
            $this->assertSame(1, end($messages)['type']);
            // 同一天再请求不再清理
            DB::table('passgeneral')->where('uid', $p['uid'])->update(['pass_id' => 2, 'is_pass' => 1]);
            $this->passRequest($p, 'index');
            $this->assertSame(2, $this->passRow($p['uid'])['pass_id']);

            // 计划任务：活动开始 5 小时内不执行
            DB::execute('DELETE FROM `zc_sanguo_passgeneral` WHERE `uid` <> :uid', ['uid' => $p['uid']]);
            $q = $this->passPlayer();
            $this->passRequest($q, 'index');
            DB::table('passgeneral')->whereIn('uid', [$p['uid'], $q['uid']])->update(['is_get' => 2, 'num' => 0, 'pass_id' => 5]);
            \Hanjiang\Cron\PassGeneralCron::run(17999);
            $this->assertSame(2, $this->passRow($q['uid'])['is_get']);

            // 每个待领取的玩家都自动领到本层奖励（旧版只有第一个玩家拿到，已修正）；全部重置
            $repP = (int)DB::table('member')->where('uid', $p['uid'])->value('reputation');
            $repQ = (int)DB::table('member')->where('uid', $q['uid'])->value('reputation');
            $activity = \Hanjiang\Model\PassGeneral::activity();
            $this->assertSame(2, \Hanjiang\Cron\PassGeneralCron::updatePassGeneralInfo($activity, $now));
            $this->assertSame($repP + 5000, (int)DB::table('member')->where('uid', $p['uid'])->value('reputation'));
            $this->assertSame($repQ + 5000, (int)DB::table('member')->where('uid', $q['uid'])->value('reputation'));
            $qMessages = $this->messagesOf($q['uid']);
            $this->assertSame('亲爱的玩家！您今天未领取的过关斩将奖励，系统已自动领取，共获得：兑奖券10个，紫宝石50个，声望5000个，请查收!', end($qMessages)['message']);
            $qLog = DB::table('passgeneral_box_log')->where('uid', $q['uid'])->get();
            $this->assertCount(1, $qLog);
            $this->assertSame([27, 0, 4, 7], [(int)$qLog[0]['type1'], (int)$qLog[0]['value1'], (int)$qLog[0]['type2'], (int)$qLog[0]['type3']]);
            foreach ([$p['uid'], $q['uid']] as $uid) {
                $row = $this->passRow($uid);
                $this->assertSame([0, 1, 1, 3], [$row['num'], $row['pass_id'], $row['is_get'], $row['free_times1']]);
            }
            $this->assertSame(0, \Hanjiang\Cron\PassGeneralCron::updatePassGeneralInfo($activity, $activity['endtime'] + 1), '活动结束后不再清理');

            // 活动结束后 10 小时内：兑奖券按 20000 银币兑换、自动领取未领的成就
            $end = $activity['endtime'];
            DB::table('member_integral')->where('act_kemari_int', '>', 0)->update(['act_kemari_int' => 0]);
            \Hanjiang\Model\PassGeneral::lotteryInfo($q['uid']);
            DB::table('member_integral')->where('uid', $q['uid'])->update(['act_kemari_int' => 7]);
            DB::table('member')->where('uid', $q['uid'])->update(['silver' => 0]);
            DB::table('passgeneral')->where('uid', $q['uid'])->update(['succ_num' => 3]);
            DB::table('passgeneral')->where('uid', $p['uid'])->update(['succ_num' => 0]);
            $this->assertSame(0, \Hanjiang\Cron\PassGeneralCron::getPassGeneralNumReward($activity, $end - 1));
            $this->assertSame(0, \Hanjiang\Cron\PassGeneralCron::getPassGeneralNumReward($activity, $end + 36001));
            $this->assertSame(1, \Hanjiang\Cron\PassGeneralCron::getPassGeneralNumReward($activity, $end + 3600));
            $this->assertSame(0, $this->passLottery($q['uid']));
            $this->assertSame(140000, (int)DB::table('member')->where('uid', $q['uid'])->value('silver'));
            $qMessages = $this->messagesOf($q['uid']);
            $this->assertSame('过关斩将活动已结束，您未兑换的兑奖券，系统已自动兑换，兑换银币140000个，请查收!', end($qMessages)['message']);

            // 成就 1（1 次）、2（3 次）自动领取：同类型累加（兑奖券 50、紫宝石 300、银币 13000000）
            $purple = $this->passJade($q['uid'], 3);
            $this->assertSame(1, \Hanjiang\Cron\PassGeneralCron::getPassgeneralAchieveReward($activity, $end + 3600));
            $this->assertSame(50, $this->passLottery($q['uid']));
            $this->assertSame($purple + 300, $this->passJade($q['uid'], 3));
            $this->assertSame(140000 + 13000000, (int)DB::table('member')->where('uid', $q['uid'])->value('silver'));
            $this->assertSame(2, DB::table('passgeneral_achievement_log')->where('uid', $q['uid'])->count());
            $qMessages = $this->messagesOf($q['uid']);
            $this->assertSame('亲爱的玩家！过关斩将活动已结束，您未领取的成就奖励已自动帮您领取，共获得：兑奖券50个，紫宝石300个，银币13000000个，请查收!', end($qMessages)['message']);
            $this->assertSame(0, \Hanjiang\Cron\PassGeneralCron::getPassgeneralAchieveReward($activity, $end + 3600), '已领取的不再发放');
        } finally {
            \Hanjiang\Model\BossClock::freeze(null);
        }
    }

    // ---------------------------------------------------------------
    // 批次 batch2：四象圣域 / 通天塔 / 英雄王座 / 金银岛（按旧版移植）
    // ---------------------------------------------------------------

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function b2Request(string $controller, string $method, array $params = []): array
    {
        $res = $this->request($controller, $method, $params);
        unset($res['message']);
        if (($res['msg'] ?? null) === 'ok') {
            unset($res['msg']);
        }
        return $res;
    }

    /**
     * @param array<string, mixed> $member
     * @return array{uid: int, auth: array<string, mixed>}
     */
    private function b2Player(int $level, array $member = []): array
    {
        $p = $this->newGuestPlayer();
        DB::table('member')->where('uid', $p['uid'])->update(['level' => $level] + $member);
        return $p;
    }

    /**
     * 阵法里的武将提到远超守军的等级（四象圣域 / 英雄王座高层守军血量近千万），让胜负不依赖随机数
     */
    private function b2Overpower(int $uid): void
    {
        DB::table('general')->where('uid', $uid)->update(['level' => 2000000]);
    }

    private function b2Member(int $uid, string $column): int
    {
        return (int)DB::table('member')->where('uid', $uid)->value($column);
    }

    /**
     * @return array<string, int>
     */
    private function b2Row(string $table, int $uid, array $columns): array
    {
        $row = DB::table($table)->where('uid', $uid)->first();
        $this->assertNotNull($row, $table);
        $return = [];
        foreach ($columns as $column) {
            $return[$column] = (int)$row[$column];
        }
        return $return;
    }

    /**
     * @return array<string, mixed>
     */
    private static function sanctumMission(int $id): array
    {
        foreach (\Hanjiang\MasterData\SanctumMission::ROWS as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }
        throw new \RuntimeException("SanctumMission {$id}");
    }

    public function testSanctumIndexSelectMapAndMissionInfo(): void
    {
        // 旧版等级小于 140 不开放
        $low = $this->b2Player(139);
        foreach (['index', 'select_map', 'get_mission_info', 'buy_times', 'get_mission_report_list', 'get_reward'] as $m) {
            $this->assertSame(2, $this->b2Request('sanctum', $m, $low['auth'] + ['id' => 1])['status'], $m);
        }
        $this->assertSame(2, $this->b2Request('sanctum', 'action', $low['auth'] + ['id' => 1])['status']);

        $p = $this->b2Player(140);
        $uid = $p['uid'];
        $index = $this->b2Request('sanctum', 'index', $p['auth']);
        $this->assertSame(['status' => 1, 'list' => [1 => ['pass' => 0], 2 => ['pass' => 0], 3 => ['pass' => 0], 4 => ['pass' => 0], 5 => ['pass' => 0]]], $index);
        $this->assertNull(DB::table('sanctum_open_map')->where('uid', $uid)->first(), 'index 不建档');

        // 不传圣域时取 sanctum_log 记录的上次位置（首次建档 1 圣域 1 层）
        $map = $this->b2Request('sanctum', 'select_map', $p['auth']);
        $this->assertSame(1, $map['status']);
        $this->assertSame(['status', 'list', 'times', 'gem', 'stage', 'all_stage', 'name', 'price'], array_keys($map));
        $this->assertCount(10, $map['list']);
        $m1 = self::sanctumMission(1);
        $this->assertSame(['id' => 1, 'name' => $m1['name'], 'type' => 1, 'city' => 1, 'stage' => 1, 'x' => $m1['x'], 'y' => $m1['y'], 'image' => 0, 'open' => 1, 'missionid' => 1, 'firstkill' => 1], $map['list'][0]);
        $this->assertSame([3, 10], [$map['list'][9]['open'], $map['list'][9]['missionid']]);
        $this->assertSame([10, 0, 1, 1, '青龙圣域一重天', 20], [$map['times'], $map['gem'], $map['stage'], $map['all_stage'], $map['name'], $map['price']]);
        $this->assertSame(['times' => 10, 'buy_times' => 0, 'missionlevel' => 1, 'missionstage' => 1, 'missionid' => 1], $this->b2Row('sanctum_log', $uid, ['times', 'buy_times', 'missionlevel', 'missionstage', 'missionid']));
        $this->assertSame(['city' => 1, 'stage' => 1, 'missionid' => 0], $this->b2Row('sanctum_open_map', $uid, ['city', 'stage', 'missionid']));

        // 未开启的层 -3；没有关卡的圣域不建档，按旧版返回 -4
        $this->assertSame(['status' => -3], $this->b2Request('sanctum', 'select_map', $p['auth'] + ['l' => 1, 's' => 2]));
        $this->assertSame(['status' => -4], $this->b2Request('sanctum', 'select_map', $p['auth'] + ['l' => 9]));
        $this->assertSame(1, DB::table('sanctum_open_map')->where('uid', $uid)->count());
        // 第 2 圣域首次进入建档：missionid 为该圣域第一关 id - 1
        $map2 = $this->b2Request('sanctum', 'select_map', $p['auth'] + ['l' => 2]);
        $this->assertSame([51, 1, '玄武圣域一重天'], [$map2['list'][0]['id'], $map2['list'][0]['open'], $map2['name']]);
        $this->assertSame(50, (int)DB::table('sanctum_open_map')->where('uid', $uid)->where('city', 2)->value('missionid'));

        $info = $this->b2Request('sanctum', 'get_mission_info', $p['auth'] + ['id' => 1]);
        $this->assertSame(['status' => 1, 'mop_up' => 0, 'mission' => [
            'city' => 1, 'stage' => 1, 'type' => 1, 'chip_num' => 4, 'background' => 0, 'id' => 1, 'image' => 0, 'level' => $m1['level'], 'name' => $m1['name'],
            'gem' => 0, 'gem_level' => 0, 'first_chip_num' => 80, 'attack' => 1, 'gem_name' => '', 'gem_value' => 0, 'gem_type' => 0, 'gem_quality' => 0,
            'five_sub' => 0, 'ten_sub' => 0, 'fifty_sub' => 800, 'hundred_sub' => 1800, 'getrate' => '0', 'firstkill' => 1,
        ]], $info);
        $this->assertSame(0, $this->b2Request('sanctum', 'get_mission_info', $p['auth'] + ['id' => 2])['mission']['attack']);
        // 宝石关：Gem 模板名称 / 0 级属性 / 类型，掉率 40% → getrate '1'
        $gem = \Hanjiang\MasterData\Gem::ROWS[1];
        $info = $this->b2Request('sanctum', 'get_mission_info', $p['auth'] + ['id' => 10]);
        $this->assertSame([1, $gem['name'], $gem['level0'], $gem['type'], 1, '1'], [$info['mission']['gem'], $info['mission']['gem_name'], $info['mission']['gem_value'], $info['mission']['gem_type'], $info['mission']['gem_quality'], $info['mission']['getrate']]);
        $this->assertSame(['status' => -1], $this->b2Request('sanctum', 'get_mission_info', $p['auth']));
        $this->assertSame(['status' => -3], $this->b2Request('sanctum', 'get_mission_info', $p['auth'] + ['id' => 999]));

        // 跨天重置剩余次数为 10，购买的次数保留
        DB::table('sanctum_log')->where('uid', $uid)->update(['times' => 1, 'buy_times' => 3, 'dateline' => strtotime('yesterday')]);
        $this->assertSame(13, $this->b2Request('sanctum', 'select_map', $p['auth'])['times']);
        $this->assertSame(['times' => 10, 'buy_times' => 3], $this->b2Row('sanctum_log', $uid, ['times', 'buy_times']));
    }

    public function testSanctumActionLoseAndGuards(): void
    {
        $p = $this->b2Player(140);
        $uid = $p['uid'];
        $nickname = (string)DB::table('member')->where('uid', $uid)->value('nickname');
        $this->emptyFormation($uid);

        // 阵法里没有武将：空队伍直接大败，照样扣 1 次
        $res = $this->b2Request('sanctum', 'action', $p['auth'] + ['id' => 1]);
        $this->assertSame(1, $res['status']);
        $this->assertSame(['status', 'info', 'battle', 'mission'], array_keys($res));
        $this->assertSame([
            'win' => -3, 'gem' => 0, 'chip' => 0, 'gem_name' => '', 'gem_type' => 0, 'gem_power' => 0, 'gem_num' => 0, 'gem_quality' => 0, 'complete' => 0, 'stage' => 1,
            'five_sub' => 0, 'ten_sub' => 20, 'fifty_sub' => 820, 'hundred_sub' => 1820, 'report' => 0, 'times' => 9, 'all_chip' => 0, 'all_gold' => 9999,
        ], $res['info']);
        $this->assertSame(['firstkill' => 0], $res['mission']);
        $this->assertSame(['leftteam', 'rightteam', 'process', 'assess', 'leftresult', 'rightresult', 'leftbuff', 'rightbuff', 'left_role', 'right_role'], array_keys($res['battle']));
        $m1 = self::sanctumMission(1);
        // 守军取 sanctum_mission 的 position1~9 → sanctum_monster
        $this->assertSame(['1', '3', '8'], array_map('strval', array_keys($res['battle']['rightteam'])));
        $this->assertSame(['name' => $nickname, 'level' => 140, 'gender' => 0], $res['battle']['left_role']);
        $this->assertSame(['name' => $m1['name'], 'level' => $m1['level'], 'type' => 1], $res['battle']['right_role']);
        $this->assertSame(['times' => 9, 'buy_times' => 0], $this->b2Row('sanctum_log', $uid, ['times', 'buy_times']));
        $this->assertSame(0, DB::table('sanctum_mission_log')->where('uid', $uid)->count());

        // 没有阵法：旧版控制器不看战斗状态，战报为空、按失败处理，照样扣次数
        DB::table('member_field')->where('uid', $uid)->update(['matrixid' => 0]);
        $res = $this->b2Request('sanctum', 'action', $p['auth'] + ['id' => 1]);
        $this->assertSame([1, null, 8], [$res['status'], $res['info']['win'], $res['info']['times']]);
        $this->assertSame(['left_role', 'right_role'], array_keys($res['battle']));
        DB::table('member_field')->where('uid', $uid)->update(['matrixid' => 1]);

        $this->assertSame(-4, $this->b2Request('sanctum', 'action', $p['auth'] + ['id' => 2])['status'], '关卡未开启');
        $this->assertSame(-2, $this->b2Request('sanctum', 'action', $p['auth'] + ['id' => 999])['status']);
        $this->assertSame(-5, $this->b2Request('sanctum', 'action', $p['auth'] + ['id' => 1, 'num' => 5])['status'], '未通过不能扫荡');
        $this->assertSame(0, $this->b2Request('sanctum', 'action', $p['auth'] + ['id' => 1, 'num' => -3])['status'], '负数次数');
        DB::table('sanctum_log')->where('uid', $uid)->update(['times' => 0, 'buy_times' => 0]);
        $this->assertSame(-6, $this->b2Request('sanctum', 'action', $p['auth'] + ['id' => 1])['status'], '次数不足');
        // 有购买次数时先扣免费次数（为 0），再扣购买次数
        DB::table('sanctum_log')->where('uid', $uid)->update(['buy_times' => 2]);
        $this->assertSame(1, $this->b2Request('sanctum', 'action', $p['auth'] + ['id' => 1])['info']['times']);
        $this->assertSame(['times' => 0, 'buy_times' => 1], $this->b2Row('sanctum_log', $uid, ['times', 'buy_times']));
    }

    public function testSanctumActionWinFirstKillReportAndComplete(): void
    {
        DB::execute('DELETE FROM `zc_sanguo_sanctum_mission_log`');
        DB::execute('DELETE FROM `zc_sanguo_sanctum_report`');
        $p = $this->b2Player(140);
        $uid = $p['uid'];
        $nickname = (string)DB::table('member')->where('uid', $uid)->value('nickname');
        $this->b2Overpower($uid);
        $m1 = self::sanctumMission(1);

        // 真实战斗：首通，记战报，首杀碎片发进 member.gem（add_gem）
        $res = $this->b2Request('sanctum', 'action', $p['auth'] + ['id' => 1]);
        $this->assertSame(1, $res['status']);
        $battle = $res['battle'];
        $this->assertNotEmpty($battle['leftteam']);
        $this->assertNotEmpty($battle['process']);
        $this->assertGreaterThanOrEqual(1, $battle['assess']);
        $this->assertSame(['heroid', 'name', 'herotype', 'level', 'allblood', 'power', 'image', 'star', 'talent', 'skill', 'quality'], array_keys(reset($battle['rightteam'])));
        $info = $res['info'];
        $this->assertSame([$battle['assess'], 1, 80, 0, 1, 0, 9], [$info['win'], $info['firstkill'], $info['first_chip_num'], $info['chip'], $info['stage'], $info['complete'], $info['times']]);
        $this->assertSame(['firstkill' => 1], $res['mission']);
        $this->assertSame([80, 80], [$info['all_chip'], \Hanjiang\Model\OwnGem::chip($uid)]);
        $this->assertSame(['sanctum', 'action', 80], [($g = DB::table('gem_log')->where('uid', $uid)->first())['class'], $g['method'], (int)$g['gem']]);
        $this->assertSame(80, $this->b2Request('sanctum', 'select_map', $p['auth'] + ['l' => 1])['gem']);
        $this->assertSame(['stage' => 1, 'missionid' => 1], $this->b2Row('sanctum_open_map', $uid, ['stage', 'missionid']));
        $log = DB::table('sanctum_mission_log')->where('uid', $uid)->first();
        $this->assertSame($info['report'], (int)$log['id'], '战报 id 即通过记录 id');
        $this->assertSame([1, $nickname, 140, 0, 1], [(int)$log['missionid'], $log['nickname'], (int)$log['level'], (int)$log['tree'], (int)$log['first_reward']]);

        // 战报回放与攻略列表
        $report = $this->b2Request('sanctum', 'get_report_info', $p['auth'] + ['id' => $info['report']]);
        $this->assertSame(1, $report['status']);
        $expected = $battle;
        $expected['left_role'] = ['name' => $nickname, 'level' => 140];
        $expected['right_role'] = ['name' => $m1['name'], 'level' => $m1['level']];
        $this->assertEquals($expected, $report['battle']);
        $this->assertSame(['win' => $battle['assess'], 'background' => 0], $report['info']);
        $this->assertSame(['status' => 2], $this->b2Request('sanctum', 'get_report_info', $p['auth'] + ['id' => 999999]));
        $list = $this->b2Request('sanctum', 'get_mission_report_list', $p['auth'] + ['id' => 1]);
        $this->assertSame('第1关', $list['missionid']);
        $this->assertSame([['id' => $info['report'], 'missionid' => 1, 'uid' => $uid, 'nickname' => $nickname, 'level' => 140, 'dateline' => (int)$log['dateline'], 'tree' => 0, 'first_reward' => 1]], $list['list']);
        $this->assertSame(['status' => 1, 'missionid' => '第3关', 'list' => []], $this->b2Request('sanctum', 'get_mission_report_list', $p['auth'] + ['id' => 53]));

        // 再打一次：普通碎片，不再记战报
        $res = $this->b2Request('sanctum', 'action', $p['auth'] + ['id' => 1]);
        $this->assertSame([0, 4, 0, 84], [$res['info']['firstkill'], $res['info']['chip'], $res['info']['report'], $res['info']['all_chip']]);
        $this->assertSame(['firstkill' => 0], $res['mission']);
        $this->assertSame(1, DB::table('sanctum_mission_log')->where('uid', $uid)->count());
        $this->assertSame(1, $this->b2Request('sanctum', 'get_mission_info', $p['auth'] + ['id' => 1])['mop_up']);

        // 打过本层最后一关进入下一层
        DB::table('sanctum_open_map')->where('uid', $uid)->update(['missionid' => 9]);
        $res = $this->b2Request('sanctum', 'action', $p['auth'] + ['id' => 10]);
        $this->assertSame([2, 0], [$res['info']['stage'], $res['info']['complete']]);
        $this->assertSame(['stage' => 2, 'missionid' => 10], $this->b2Row('sanctum_open_map', $uid, ['stage', 'missionid']));
        if ($res['info']['gem'] > 0) {
            // 宝石掉落按掷点发进 own_gem（add_gem），宝石格子占用加颗数（add_gemuse）
            $this->assertSame([1, \Hanjiang\MasterData\Gem::ROWS[1]['name'], 1, 1], [$res['info']['gem'], $res['info']['gem_name'], $res['info']['gem_num'], $res['info']['gem_quality']]);
            $this->assertSame(1, DB::table('own_gem')->where('uid', $uid)->where('gemid', 1)->whereNull('deleted_at')->count());
            $this->assertSame(1, (int)DB::table('member')->where('uid', $uid)->value('gemuse'));
        } else {
            $this->assertSame(0, DB::table('own_gem')->where('uid', $uid)->count());
        }

        // 打过圣域最后一关：complete，index 显示通关
        DB::table('sanctum_open_map')->where('uid', $uid)->update(['stage' => 5, 'missionid' => 49]);
        $res = $this->b2Request('sanctum', 'action', $p['auth'] + ['id' => 50]);
        $this->assertSame([5, 1], [$res['info']['stage'], $res['info']['complete']]);
        $this->assertSame([1 => ['pass' => 1], 2 => ['pass' => 0], 3 => ['pass' => 0], 4 => ['pass' => 0], 5 => ['pass' => 0]], $this->b2Request('sanctum', 'index', $p['auth'])['list']);
        // 最高关卡 250 没有下一关，按 _max_id 判通关
        $this->b2Request('sanctum', 'select_map', $p['auth'] + ['l' => 5]);
        DB::table('sanctum_open_map')->where('uid', $uid)->where('city', 5)->update(['stage' => 5, 'missionid' => 249]);
        $this->assertSame(1, $this->b2Request('sanctum', 'action', $p['auth'] + ['id' => 250])['info']['complete']);

        // 旧版老玩家：有通过记录但未领首杀（first_reward 0），胜利时补发并标记
        DB::table('sanctum_mission_log')->insert(['missionid' => 2, 'uid' => $uid, 'nickname' => $nickname, 'level' => 140, 'first_reward' => 0]);
        DB::table('sanctum_open_map')->where('uid', $uid)->where('city', 1)->update(['stage' => 1, 'missionid' => 5]);
        $res = $this->b2Request('sanctum', 'action', $p['auth'] + ['id' => 2]);
        $this->assertSame([1, self::sanctumMission(2)['first_chip_num'], 0], [$res['info']['firstkill'], $res['info']['first_chip_num'], $res['info']['report']]);
        $this->assertSame(1, (int)DB::table('sanctum_mission_log')->where('uid', $uid)->where('missionid', 2)->value('first_reward'));
    }

    public function testSanctumMopUpAndBuyTimes(): void
    {
        $p = $this->b2Player(140);
        $uid = $p['uid'];
        $this->b2Request('sanctum', 'select_map', $p['auth']);
        DB::table('sanctum_open_map')->where('uid', $uid)->update(['missionid' => 3]);
        DB::table('sanctum_mission_log')->insert(['missionid' => 1, 'uid' => $uid, 'level' => 140, 'first_reward' => 1]);

        // 扫荡：默认胜利，不返回战斗过程，碎片 = chip_num × 次数
        $gold = $this->b2Member($uid, 'gold');
        $res = $this->b2Request('sanctum', 'action', $p['auth'] + ['id' => 1, 'num' => 5]);
        $this->assertSame(['status', 'info', 'mission'], array_keys($res));
        $this->assertSame([1, 20, 0, 5, $gold, 0, 100, 900, 1900], [$res['info']['win'], $res['info']['chip'], $res['info']['firstkill'], $res['info']['times'], $res['info']['all_gold'], $res['info']['five_sub'], $res['info']['ten_sub'], $res['info']['fifty_sub'], $res['info']['hundred_sub']]);
        $this->assertSame(['times' => 5, 'buy_times' => 0], $this->b2Row('sanctum_log', $uid, ['times', 'buy_times']));

        // 次数不足：差额每次 20 元宝
        $res = $this->b2Request('sanctum', 'action', $p['auth'] + ['id' => 1, 'num' => 12]);
        $this->assertSame([1, 48, 0, $gold - 140], [$res['info']['win'], $res['info']['chip'], $res['info']['times'], $res['info']['all_gold']]);
        $this->assertSame($gold - 140, $this->b2Member($uid, 'gold'));
        DB::table('member')->where('uid', $uid)->update(['gold' => 99]);
        $this->assertSame(-8, $this->b2Request('sanctum', 'action', $p['auth'] + ['id' => 1, 'num' => 5])['status'], '元宝不足');
        $this->assertSame(99, $this->b2Member($uid, 'gold'));

        // 旧版扣次数的写法：免费次数不够时免费次数清零、购买次数按「购买次数 - 本次次数」计
        DB::table('sanctum_log')->where('uid', $uid)->update(['times' => 3, 'buy_times' => 5]);
        $this->b2Request('sanctum', 'action', $p['auth'] + ['id' => 1, 'num' => 5]);
        $this->assertSame(['times' => 0, 'buy_times' => 0], $this->b2Row('sanctum_log', $uid, ['times', 'buy_times']));

        // 未领首杀的关卡不能扫荡
        DB::table('sanctum_mission_log')->where('uid', $uid)->update(['first_reward' => 0]);
        DB::table('sanctum_log')->where('uid', $uid)->update(['times' => 10]);
        $this->assertSame(-24, $this->b2Request('sanctum', 'action', $p['auth'] + ['id' => 1, 'num' => 5])['status']);

        // 购买次数：type 0 只查价格
        DB::table('member')->where('uid', $uid)->update(['gold' => 1000]);
        $this->assertSame(['status' => 1, 'price' => 20, 'all_gold' => 1000, 'times' => 10], $this->b2Request('sanctum', 'buy_times', $p['auth']));
        $this->assertSame(['status' => 1, 'price' => 40, 'all_gold' => 960, 'times' => 12], $this->b2Request('sanctum', 'buy_times', $p['auth'] + ['type' => 1, 'num' => 2]));
        $this->assertSame(['times' => 10, 'buy_times' => 2], $this->b2Row('sanctum_log', $uid, ['times', 'buy_times']));
        DB::table('member')->where('uid', $uid)->update(['gold' => 19]);
        $this->assertSame(['status' => -2], $this->b2Request('sanctum', 'buy_times', $p['auth'] + ['type' => 1]));
        $this->assertSame(['status' => 0], $this->b2Request('sanctum', 'buy_times', $p['auth'] + ['type' => 1, 'num' => -5]));
        $this->assertSame(19, $this->b2Member($uid, 'gold'));
    }

    public function testSanctumDailyRewardAndSendToChat(): void
    {
        $p = $this->b2Player(140);
        $uid = $p['uid'];
        $this->assertSame(['status' => -1], $this->b2Request('sanctum', 'get_reward', $p['auth']), '没有圣域记录');
        $this->b2Request('sanctum', 'select_map', $p['auth']);
        $this->assertSame(['status' => -4], $this->b2Request('sanctum', 'get_reward', $p['auth']), '还未通过一关');

        // 按各圣域里（相对本圣域）最靠后的已过关卡发奖
        DB::table('sanctum_open_map')->where('uid', $uid)->update(['missionid' => 10]);
        $this->b2Request('sanctum', 'select_map', $p['auth'] + ['l' => 2]);
        DB::table('sanctum_open_map')->where('uid', $uid)->where('city', 2)->update(['missionid' => 55]);
        $ratio = \Hanjiang\MasterData\SanctumReward::ROWS;
        $view = $this->b2Request('sanctum', 'get_reward', $p['auth']);
        $this->assertSame([
            'status' => 1, 'effect' => 0, 'gem' => 300, 'all_gold' => 9999, 'all_gem' => 0,
            'mission' => ['stage' => '一重天', 'id' => 10],
            'normal_reward' => ['gold' => $ratio[1]['sub'], 'multiple' => intdiv($ratio[1]['ratio'], 100)],
            'best_reward' => ['gold' => $ratio[2]['sub'], 'multiple' => intdiv($ratio[2]['ratio'], 100)],
        ], $view);
        $this->assertSame(0, (int)DB::table('member_activity')->where('uid', $uid)->value('sanctumdate'), '查看不算领取');

        $this->assertSame(-6, $this->b2Request('sanctum', 'get_reward', $p['auth'] + ['type' => 1, 'multiple' => 3])['status']);
        DB::table('member')->where('uid', $uid)->update(['gold' => $ratio[2]['sub'] - 1]);
        $this->assertSame(-7, $this->b2Request('sanctum', 'get_reward', $p['auth'] + ['type' => 1, 'multiple' => 2])['status']);
        DB::table('member')->where('uid', $uid)->update(['gold' => 1000]);
        $res = $this->b2Request('sanctum', 'get_reward', $p['auth'] + ['type' => 1, 'multiple' => 2]);
        $this->assertSame([1, (int)(300 * $ratio[2]['ratio'] / 100), 1000 - $ratio[2]['sub']], [$res['status'], $res['gem'], $res['all_gold']]);
        $this->assertGreaterThanOrEqual(strtotime('today'), (int)DB::table('member_activity')->where('uid', $uid)->value('sanctumdate'));
        $this->assertSame(['status' => -3], $this->b2Request('sanctum', 'get_reward', $p['auth'] + ['type' => 1]), '今日已领取');

        // 次日不翻倍领取
        DB::table('member_activity')->where('uid', $uid)->update(['sanctumdate' => strtotime('yesterday')]);
        $res = $this->b2Request('sanctum', 'get_reward', $p['auth'] + ['type' => 1]);
        $this->assertSame([1, 300, 1000 - $ratio[2]['sub']], [$res['status'], $res['gem'], $res['all_gold']]);
        // 每日奖励的碎片发进 member.gem，all_gem 为当前碎片数
        $doubled = (int)(300 * $ratio[2]['ratio'] / 100);
        $this->assertSame([$doubled + 300, $doubled + 300], [$res['all_gem'], \Hanjiang\Model\OwnGem::chip($uid)]);
        $this->assertSame([[$doubled, 'get_reward'], [300, 'get_reward']], array_map(static fn (array $r): array => [(int)$r['gem'], $r['method']], DB::table('gem_log')->where('uid', $uid)->orderBy('id')->get()));

        // 战报分享：扣 2 元宝，全服发四象圣域战报（get_message_str 25，消息 type 11，战报 id 为「战报id|圣域」）
        $w = $this->newGuestPlayer();
        $this->chatOnline($w);
        $this->assertSame(['status' => -1], $this->b2Request('sanctum', 'send_to_chat', $p['auth'] + ['id' => 1]));
        $this->assertSame(['status' => -2], $this->b2Request('sanctum', 'send_to_chat', $p['auth'] + ['id' => 999, 'report' => 5]));
        $gold = 1000 - $ratio[2]['sub'];
        $this->assertSame(['status' => 1, 'all_gold' => $gold - 2], $this->b2Request('sanctum', 'send_to_chat', $p['auth'] + ['id' => 1, 'report' => 5]));
        $this->assertSame($gold - 2, $this->b2Member($uid, 'gold'));
        $mission = array_column(\Hanjiang\MasterData\SanctumMission::ROWS, null, 'id')[1];
        $this->assertSame([[11, $this->chatFrom($p) . ' 在四象圣域击败了 {9,' . $mission['name'] . '} {16,[查看战报],5|' . $mission['city'] . '}', 5, $uid]], $this->chatMessages($w));
    }

    public function testHeavenIndexAndPkLoseGuards(): void
    {
        $low = $this->b2Player(159);
        foreach (['index', 'get_mopup_price', 'pk', 'buy', 'reward_info', 'get_reward'] as $m) {
            $res = $this->b2Request('heaven', $m, $low['auth'] + ['id' => 1]);
            $this->assertSame([-1, '抱歉，您的等级不足！'], [$res['status'], $res['msg']], $m);
        }
        $this->assertSame(['status' => 1, 'price' => 20], $this->b2Request('heaven', 'get_price', $low['auth']));

        $p = $this->b2Player(160);
        $uid = $p['uid'];
        $nickname = (string)DB::table('member')->where('uid', $uid)->value('nickname');
        $index = $this->b2Request('heaven', 'index', $p['auth']);
        $this->assertSame(['status', 'index', 'resource', 'list', 'times', 'get_reward'], array_keys($index));
        $this->assertCount(200, $index['list']);
        $h1 = \Hanjiang\MasterData\HeavenMission::ROWS[1];
        $this->assertSame([
            'id' => 1, 'level' => 160, 'name' => $h1['name'], 'type' => 1, 'image' => $h1['image'], 'refinestone' => 12, 'colorstone' => 0, 'color_getrate' => 0,
            'bloodstone' => 0, 'blood_getrate' => 0, 'equip' => 1, 'equip_value' => 1, 'background' => $h1['background'], 'first_type' => 1, 'first_value' => 600,
            'openstatus' => 1, 'killed' => 0, 'mop_up' => 0, 'essence' => ['id' => 1, 'quality' => 1, 'num' => 1], 'firstkill' => 1,
        ], $index['list'][0]);
        $this->assertSame([0, 0, 0], [$index['list'][1]['openstatus'], $index['list'][9]['essence']['id'], $index['index']]);
        // 首次读活动信息按旧版初始值建档：heaventimes 10、freeheaven 0；旧版 hevanedate 拼写错误，get_reward 恒为 0
        $this->assertSame([10, 0, ['refinestone' => 0, 'colorstone' => 0, 'bloodstone' => 0]], [$index['times'], $index['get_reward'], $index['resource']]);
        $this->assertSame(['heavenlevel' => 1, 'heavenmission' => 0, 'heaventimes' => 10, 'freeheaven' => 0], $this->b2Row('member_activity', $uid, ['heavenlevel', 'heavenmission', 'heaventimes', 'freeheaven']));
        $this->assertSame(['level' => 1, 'stage' => 0, 'mission' => 0], $this->b2Row('heaven_open_map', $uid, ['level', 'stage', 'mission']));

        // 阵法里没有武将：大败，扣 1 次（没有免费次数时扣购买的次数）
        $this->emptyFormation($uid);
        $res = $this->b2Request('heaven', 'pk', $p['auth'] + ['id' => 1]);
        $this->assertSame(['status', 'info', 'clear', 'battle'], array_keys($res));
        $this->assertSame(['firstkill' => 1, 'id' => 1, 'report' => 0, 'times' => 9, 'win' => -3, 'refinestone' => 0, 'bloodstone' => 0, 'colorstone' => 0, 'essence' => ['id' => 0, 'quality' => 0, 'num' => 0]], $res['info']);
        $this->assertEqualsCanonicalizing([1, 2, 3, 7, 9], array_map('intval', array_keys($res['battle']['rightteam'])));
        $this->assertSame(['name' => $nickname, 'level' => 160], $res['battle']['left_role']);
        $this->assertSame(['name' => $h1['name'], 'level' => 160], $res['battle']['right_role']);
        $this->assertSame(['heaventimes' => 9, 'freeheaven' => 0], $this->b2Row('member_activity', $uid, ['heaventimes', 'freeheaven']));

        $this->assertSame(-6, $this->b2Request('heaven', 'pk', $p['auth'] + ['id' => 3])['status'], '关卡未开启');
        $this->assertSame(-4, $this->b2Request('heaven', 'pk', $p['auth'] + ['id' => 999])['status']);
        DB::table('member_field')->where('uid', $uid)->update(['matrixid' => 0]);
        $this->assertSame(['status' => -7, 'msg' => '获取战斗信息失败'], $this->b2Request('heaven', 'pk', $p['auth'] + ['id' => 1]));
        $this->assertSame(9, $this->b2Row('member_activity', $uid, ['heaventimes'])['heaventimes'], '战斗失败不扣次数');
        DB::table('member_activity')->where('uid', $uid)->update(['heaventimes' => 0]);
        $this->assertSame(['status' => -3, 'msg' => '次数不足'], $this->b2Request('heaven', 'pk', $p['auth'] + ['id' => 1]));
    }

    public function testHeavenPkWinFirstKillAndReports(): void
    {
        DB::execute('DELETE FROM `zc_sanguo_heaven_log`');
        DB::execute('DELETE FROM `zc_sanguo_heaven_report`');
        $p = $this->b2Player(160);
        $uid = $p['uid'];
        $nickname = (string)DB::table('member')->where('uid', $uid)->value('nickname');
        $this->b2Overpower($uid);
        $this->b2Request('heaven', 'index', $p['auth']);
        DB::table('member_activity')->where('uid', $uid)->update(['freeheaven' => 2]);

        // 真实战斗：首杀奖励（石头依赖未移植，只回报）、记战报、开启下一关
        $res = $this->b2Request('heaven', 'pk', $p['auth'] + ['id' => 1]);
        $this->assertSame(1, $res['status']);
        $battle = $res['battle'];
        $this->assertNotEmpty($battle['process']);
        $this->assertGreaterThanOrEqual(1, $battle['assess']);
        $info = $res['info'];
        $this->assertSame(['firstkill', 'first_type', 'first_value', 'id', 'report', 'times', 'win', 'refinestone', 'bloodstone', 'colorstone', 'essence'], array_keys($info));
        $this->assertSame([1, 1, 600, 11, 0], [$info['firstkill'], $info['first_type'], $info['first_value'], $info['times'], $info['refinestone']]);
        $this->assertSame(['freeheaven' => 1, 'heaventimes' => 10], $this->b2Row('member_activity', $uid, ['freeheaven', 'heaventimes']), '先扣免费次数');
        $this->assertSame(1, $this->b2Row('heaven_open_map', $uid, ['mission'])['mission']);
        $log = DB::table('heaven_log')->where('uid', $uid)->first();
        $this->assertSame($info['report'], (int)$log['id']);
        $this->assertSame([$nickname, 1, 1, 160, 1], [$log['nickname'], (int)$log['missionlevel'], (int)$log['missionid'], (int)$log['level'], (int)$log['first_reward']]);

        $report = $this->b2Request('heaven', 'get_report_info', $p['auth'] + ['id' => $info['report']]);
        $this->assertSame(['status', 'battle', 'win'], array_keys($report));
        $this->assertEquals($battle, $report['battle']);
        $this->assertSame($battle['assess'], $report['win']);
        $this->assertSame(['status' => 2, 'battle' => [], 'msg' => '攻略不存在'], $this->b2Request('heaven', 'get_report_info', $p['auth'] + ['id' => 999999]));
        $list = $this->b2Request('heaven', 'get_report_list', $p['auth'] + ['id' => 1]);
        $this->assertSame([$info['report'] => ['id' => $info['report'], 'uid' => $uid, 'level' => 160, 'nickname' => $nickname, 'tree' => 0]], $list['list']);
        $this->assertSame(['status' => 2, 'list' => [], 'msg' => '攻略不存在'], $this->b2Request('heaven', 'get_report_list', $p['auth'] + ['id' => 2]));

        // 列表：已首杀的已过关卡可扫荡；当前关卡为第 2 关
        $index = $this->b2Request('heaven', 'index', $p['auth']);
        $this->assertSame([1, 1, 1, 0], [$index['list'][0]['killed'], $index['list'][0]['mop_up'], $index['list'][0]['openstatus'], $index['list'][0]['firstkill']]);
        $this->assertSame([1, 0, 1], [$index['list'][1]['openstatus'], $index['list'][1]['killed'], $index['index']]);

        // 再打一次：非首杀，精炼石 100% 掉落
        $res = $this->b2Request('heaven', 'pk', $p['auth'] + ['id' => 1]);
        $this->assertSame([0, 0, 12], [$res['info']['firstkill'], $res['info']['report'], $res['info']['refinestone']]);
        $this->assertArrayNotHasKey('first_type', $res['info']);
        $this->assertSame(1, DB::table('heaven_log')->where('uid', $uid)->count());

        // 旧版老玩家：有记录但未领首杀，胜利时补发首杀并标记
        DB::table('heaven_log')->insert(['uid' => $uid, 'nickname' => $nickname, 'missionlevel' => 1, 'missionid' => 2, 'level' => 160, 'first_reward' => 0]);
        $res = $this->b2Request('heaven', 'pk', $p['auth'] + ['id' => 2]);
        $this->assertSame([1, 1, 700, 0], [$res['info']['firstkill'], $res['info']['first_type'], $res['info']['first_value'], $res['info']['report']]);
        $this->assertSame(1, (int)DB::table('heaven_log')->where('uid', $uid)->where('missionid', 2)->value('first_reward'));
        $this->assertSame(2, $this->b2Row('heaven_open_map', $uid, ['mission'])['mission']);

        $this->assertSame(['status' => -1, 'msg' => '传递类型错误'], $this->b2Request('heaven', 'send_to_chat', $p['auth'] + ['id' => 1]));
        $this->assertSame(['status' => -3, 'msg' => '获取关卡列表失败'], $this->b2Request('heaven', 'send_to_chat', $p['auth'] + ['id' => 999, 'report' => 5]));
        // 战报分享：扣 2 元宝，全服发通天塔战报（type 38）
        $w = $this->newGuestPlayer();
        $this->chatOnline($w);
        DB::table('member')->where('uid', $uid)->update(['gold' => 1]);
        $this->assertSame(['status' => -2, 'msg' => '元宝不足'], $this->b2Request('heaven', 'send_to_chat', $p['auth'] + ['id' => 1, 'report' => 5]));
        DB::table('member')->where('uid', $uid)->update(['gold' => 2]);
        $this->assertSame(['status' => 1, 'all_gold' => 0], $this->b2Request('heaven', 'send_to_chat', $p['auth'] + ['id' => 1, 'report' => 5]));
        $name = \Hanjiang\MasterData\HeavenMission::ROWS[1]['name'];
        $this->assertSame([[38, $this->chatFrom($p) . ' 在通天塔击败了 {9,' . $name . '} {17,[查看战报],5}', 5, $uid]], $this->chatMessages($w));
    }

    public function testHeavenMopUpBuyAndDailyReward(): void
    {
        $p = $this->b2Player(160);
        $uid = $p['uid'];
        $this->b2Request('heaven', 'index', $p['auth']);
        DB::table('heaven_open_map')->where('uid', $uid)->update(['mission' => 10]);
        DB::table('heaven_log')->insert(['uid' => $uid, 'missionlevel' => 1, 'missionid' => 1, 'level' => 160, 'first_reward' => 1]);

        $this->assertSame(['status' => 1, 'info' => ['five_sub' => 0, 'ten_sub' => 0, 'fifty_sub' => 800, 'hundred_sub' => 1800]], $this->b2Request('heaven', 'get_mopup_price', $p['auth'] + ['id' => 1]));
        $this->assertSame(-4, $this->b2Request('heaven', 'get_mopup_price', $p['auth'] + ['id' => 11])['status'], '未击败的关卡无法扫荡');
        $this->assertSame(-2, $this->b2Request('heaven', 'get_mopup_price', $p['auth'] + ['id' => 999])['status']);
        $this->assertSame(['status' => -7, 'resource' => [], 'msg' => '传递参数失败'], $this->b2Request('heaven', 'mop_up', $p['auth'] + ['id' => 1, 'times' => 7]));
        $this->assertSame(-5, $this->b2Request('heaven', 'mop_up', $p['auth'] + ['id' => 11, 'times' => 5])['status']);

        // 次数够：不花元宝，精炼石 = refinestone × 次数
        $gold = $this->b2Member($uid, 'gold');
        $res = $this->b2Request('heaven', 'mop_up', $p['auth'] + ['id' => 1, 'times' => 5]);
        $this->assertSame(['status', 'resource', 'info', 'times', 'gold'], array_keys($res));
        $this->assertSame(['five_sub', 'ten_sub', 'fifty_sub', 'hundred_sub', 'essence', 'refinestone', 'colorstone', 'bloodstone'], array_keys($res['info']));
        $this->assertSame([0, 100, 900, 1900, 60, 0, 0], [$res['info']['five_sub'], $res['info']['ten_sub'], $res['info']['fifty_sub'], $res['info']['hundred_sub'], $res['info']['refinestone'], $res['info']['colorstone'], $res['info']['bloodstone']]);
        $this->assertSame([5, $gold], [$res['times'], $res['gold']]);
        $this->assertSame(['freeheaven' => 0, 'heaventimes' => 5], $this->b2Row('member_activity', $uid, ['freeheaven', 'heaventimes']));
        // 精炼石发进 member.refinestone（add_stone 类型 1），resource 为当前三种石头
        $this->assertSame(\Hanjiang\Model\MemberStone::all($uid), $res['resource']);
        $this->assertGreaterThanOrEqual(60, $res['resource']['refinestone']);
        $this->assertSame(['heaven', 'mop_up', 60], [($l = DB::table('stone_log')->where('uid', $uid)->where('method', 'mop_up')->first())['class'], $l['method'], (int)$l['stone']]);

        // 次数不足：差额每次 20 元宝，补买的次数累加到 member_field.heaventimes
        $res = $this->b2Request('heaven', 'mop_up', $p['auth'] + ['id' => 1, 'times' => 10]);
        $this->assertSame([0, $gold - 100], [$res['times'], $res['gold']]);
        $this->assertSame(5, (int)DB::table('member_field')->where('uid', $uid)->value('heaventimes'));
        $this->assertSame(['freeheaven' => 0, 'heaventimes' => 0], $this->b2Row('member_activity', $uid, ['freeheaven', 'heaventimes']));
        DB::table('member')->where('uid', $uid)->update(['gold' => 99]);
        $this->assertSame(['status' => -8, 'resource' => [], 'msg' => '元宝不足'], $this->b2Request('heaven', 'mop_up', $p['auth'] + ['id' => 1, 'times' => 5]));
        DB::table('heaven_log')->where('uid', $uid)->update(['first_reward' => 0]);
        $this->assertSame(-24, $this->b2Request('heaven', 'mop_up', $p['auth'] + ['id' => 1, 'times' => 5])['status'], '首杀阶段不能扫荡');

        // 购买次数：每次 20 元宝
        DB::table('member')->where('uid', $uid)->update(['gold' => 1000]);
        $this->assertSame(['status' => 1, 'times' => 1, 'gold' => 980], $this->b2Request('heaven', 'buy', $p['auth']));
        $this->assertSame(1, $this->b2Row('member_activity', $uid, ['heaventimes'])['heaventimes']);
        DB::table('member')->where('uid', $uid)->update(['gold' => 19]);
        $this->assertSame(['status' => -2, 'msg' => '元宝不足'], $this->b2Request('heaven', 'buy', $p['auth']));
        $this->assertSame(1, $this->b2Row('member_activity', $uid, ['heaventimes'])['heaventimes']);

        // 每日奖励：按已过的最大关卡；上古血玉取本关 boss（第 10 关 5 个），五彩石取此前最近的五彩石关（没有为 0）
        $q = $this->b2Player(160);
        $this->assertSame(['status' => 3, 'msg' => '关卡未开启'], $this->b2Request('heaven', 'reward_info', $q['auth']), '未击败关卡');
        DB::table('heaven_open_map')->where('uid', $q['uid'])->update(['mission' => 10]);
        $h10 = \Hanjiang\MasterData\HeavenMission::ROWS[10];
        $this->assertSame([
            'status' => 1, 'effect' => 0, 'base_reward' => 90,
            'info' => ['name' => $h10['name'], 'mission' => 10],
            'two_reward' => ['gold' => 100, 'refine_reward' => 210, 'color_reward' => 0],
            'four_reward' => ['gold' => 200, 'refine_reward' => 330, 'color_reward' => 0, 'blood_reward' => 15],
            'eight_reward' => ['gold' => 400, 'refine_reward' => 570, 'color_reward' => 0, 'blood_reward' => 30],
        ], $this->b2Request('heaven', 'reward_info', $q['auth']));
        $this->assertSame(['status' => -3, 'msg' => '传递类型错误'], $this->b2Request('heaven', 'get_reward', $q['auth'] + ['type' => 4]));
        DB::table('member')->where('uid', $q['uid'])->update(['gold' => 199]);
        $this->assertSame(['status' => -6, 'effect' => 0, 'msg' => '元宝不足'], $this->b2Request('heaven', 'get_reward', $q['auth'] + ['type' => 2]));
        DB::table('member')->where('uid', $q['uid'])->update(['gold' => 500]);
        $this->assertSame([
            'status' => 1, 'effect' => 0, 'reward' => ['refinestone' => 330, 'colorstone' => 0, 'bloodstone' => 15], 'gold' => 300,
            'resource' => ['refinestone' => 330, 'colorstone' => 0, 'bloodstone' => 15],
        ], $this->b2Request('heaven', 'get_reward', $q['auth'] + ['type' => 2]));
        $this->assertSame([[1, 330], [3, 15]], array_map(static fn (array $r): array => [(int)$r['stone_type'], (int)$r['stone']], DB::table('stone_log')->where('uid', $q['uid'])->where('method', 'get_reward')->orderBy('id')->get()));
        $this->assertSame(['status' => 2, 'msg' => '今日已领取过奖励'], $this->b2Request('heaven', 'reward_info', $q['auth']));
        $this->assertSame(['status' => 2, 'msg' => '今日已领取过奖励'], $this->b2Request('heaven', 'get_reward', $q['auth']));
        DB::table('member_activity')->where('uid', $q['uid'])->update(['heavendate' => strtotime('yesterday')]);
        $this->assertSame(['refinestone' => 90, 'colorstone' => 0, 'bloodstone' => 0], $this->b2Request('heaven', 'get_reward', $q['auth'])['reward']);
        $this->assertSame(300, $this->b2Member($q['uid'], 'gold'));
    }

    public function testBatch2DailyCronResets(): void
    {
        $p = $this->b2Player(160);
        $uid = $p['uid'];
        $this->b2Request('heaven', 'index', $p['auth']);
        DB::table('member_activity')->where('uid', $uid)->update(['freeheaven' => 0, 'heaventimes' => 3, 'islandtimes' => 4]);
        DB::table('member_field')->where('uid', $uid)->update(['heaventimes' => 6, 'dateline' => strtotime('yesterday')]);
        Redis::del("daily_cron:{$uid}");

        // 旧版 daily_cron：通天塔免费次数重置为 10、金银岛今日挑战次数归零；购买的次数与扫荡补买次数保留
        $this->assertSame(13, $this->b2Request('heaven', 'index', $p['auth'])['times']);
        $this->assertSame(['freeheaven' => 10, 'heaventimes' => 3, 'islandtimes' => 0], $this->b2Row('member_activity', $uid, ['freeheaven', 'heaventimes', 'islandtimes']));
        $this->assertSame(6, (int)DB::table('member_field')->where('uid', $uid)->value('heaventimes'));
    }

    public function testHerothroneIndexStartAndRewards(): void
    {
        $low = $this->b2Player(100);
        foreach (['index', 'go_back', 'start', 'action', 'buy_times', 'end_battle'] as $m) {
            $this->assertSame(2, $this->b2Request('herothrone', $m, $low['auth'])['status'], $m);
        }

        $p = $this->b2Player(101);
        $uid = $p['uid'];
        $this->assertSame(['status' => -1], $this->b2Request('herothrone', 'go_back', $p['auth']), 'go_back 不建档');
        $this->assertSame(['status' => -1], $this->b2Request('herothrone', 'start', $p['auth']));
        $res = $this->b2Request('herothrone', 'index', $p['auth']);
        $this->assertSame(['status' => 1, 'info' => ['uid' => $uid, 'times' => 2, 'buytimes' => 0, 'laststage' => 0, 'status' => 0], 'buy' => 0, 'price' => 1000, 'list' => []], $res);
        $this->assertSame(['status' => 1, 'buy' => 0, 'info' => $res['info'], 'price' => 1000, 'list' => []], $this->b2Request('herothrone', 'go_back', $p['auth']));

        // 新开一局：扣 1 次免费次数，从第 1 关开始
        $silver = $this->b2Member($uid, 'silver');
        $start = $this->b2Request('herothrone', 'start', $p['auth']);
        $this->assertSame(['status', 'buy', 'mission', 'reward', 'price', 'info', 'get_reward', 'all_silver'], array_keys($start));
        $this->assertSame([['id' => 1, 'image' => 81, 'open' => 1], ['id' => 2, 'image' => 82, 'open' => 0]], array_slice($start['mission'], 0, 2));
        $this->assertCount(5, $start['mission']);
        $this->assertCount(30, $start['reward']);
        $this->assertSame(['id' => 10, 'gettype1' => 2, 'gettype2' => 1, 'gettype3' => 0, 'getvalue1' => 20000, 'getvalue2' => 50000, 'getvalue3' => 0, 'getnum1' => 0, 'getnum2' => 0, 'getnum3' => 0], $start['reward'][0]);
        $equip = \Hanjiang\MasterData\Equip::ROWS[60];
        $this->assertSame(['id' => 60, 'name' => $equip['name'] . '碎片', 'type' => $equip['type'], 'quality' => $equip['quality']], $start['reward'][4]['getvalue3']);
        $mount = \Hanjiang\MasterData\MountTemp::ROWS[101];
        $this->assertSame(['id' => 101, 'name' => $mount['name'] . '碎片', 'quality' => $mount['quality']], $start['reward'][15]['getvalue3']);
        $this->assertSame(2, $start['reward'][15]['getnum3']);
        $this->assertSame([1, 0, 1, 0, 50, 0], [$start['info']['times'], $start['info']['stage'], $start['info']['status'], $start['info']['laststage'], $start['info']['price'], $start['info']['buytimes']]);
        $this->assertSame([[], $silver], [$start['get_reward'], $start['all_silver']]);
        $this->assertSame(['times' => 1, 'stage' => 0, 'status' => 1], $this->b2Row('herothrone_log', $uid, ['times', 'stage', 'status']));
        // 已开局：index 直接进开始界面，再 start 不再扣次数
        $this->assertSame(['status' => 3, 'price' => 1000], $this->b2Request('herothrone', 'index', $p['auth']));
        $this->assertSame(1, $this->b2Request('herothrone', 'start', $p['auth'])['info']['times']);

        // 重新开局从历史最高所在十层的前十层开始，并发放之前的宝箱奖励（声望排在最前，装备碎片按 getsort 排在后面）
        DB::table('herothrone_log')->where('uid', $uid)->update(['status' => 0, 'stage' => 0, 'laststage' => 155]);
        $silver = $this->b2Member($uid, 'silver');
        $reputation = $this->b2Member($uid, 'reputation');
        $soul = $this->b2Member($uid, 'soul');
        $start = $this->b2Request('herothrone', 'start', $p['auth']);
        $this->assertSame(140, $start['info']['stage']);
        $this->assertSame([141, 1], [$start['mission'][0]['id'], $start['mission'][0]['open']]);
        $this->assertSame(140, $this->b2Row('herothrone_log', $uid, ['stage'])['stage']);
        $sum = [1 => 0, 2 => 0, 7 => 0, 8 => 0];
        $chips = [];
        foreach (\Hanjiang\MasterData\HerothroneMission::ROWS as $row) {
            if ($row['id'] % 10 !== 0 || $row['id'] > 141) {
                continue;
            }
            for ($i = 1; $i <= 3; $i++) {
                if (isset($sum[$row['gettype' . $i]])) {
                    $sum[$row['gettype' . $i]] += $row['getvalue' . $i];
                } elseif ($row['gettype' . $i] === 4) {
                    $chips[] = $row['getvalue' . $i];
                }
            }
        }
        $types = array_column($start['get_reward'], 'gettype');
        $this->assertSame(7, $types[0]);
        $this->assertSame(['gettype' => 7, 'getvalue' => $sum[7], 'getsort' => 0], $start['get_reward'][0]);
        $this->assertSame([2, 1], array_slice($types, 1, 2));
        $this->assertSame(array_fill(0, count($chips), 4), array_slice($types, 3));
        $first = \Hanjiang\MasterData\Equip::ROWS[$chips[0]];
        $this->assertSame(['gettype' => 4, 'getvalue' => ['itemtype' => 1, 'itemid' => $chips[0], 'quality' => $first['quality'], 'itemname' => $first['name'] . '碎片'], 'getsort' => 4], $start['get_reward'][3]);
        $this->assertSame($silver + $sum[1], $start['all_silver']);
        $this->assertSame([$silver + $sum[1], $reputation + $sum[7], $soul + $sum[8]], [$this->b2Member($uid, 'silver'), $this->b2Member($uid, 'reputation'), $this->b2Member($uid, 'soul')]);
    }

    public function testHerothroneActionBuyTimesAndEndBattle(): void
    {
        $p = $this->b2Player(101);
        $uid = $p['uid'];
        $this->b2Request('herothrone', 'index', $p['auth']);
        $this->assertSame(['status' => -2, 'info' => ['reward' => [], 'win' => 0, 'view' => 0]], $this->b2Request('herothrone', 'action', $p['auth']), '还未开始');
        $this->assertSame(['status' => -2], $this->b2Request('herothrone', 'end_battle', $p['auth']));

        // 战败（VIP 不足 10）：直接结束本局
        $this->b2Request('herothrone', 'start', $p['auth']);
        $this->emptyFormation($uid);
        $res = $this->b2Request('herothrone', 'action', $p['auth']);
        $this->assertSame(['status', 'info', 'battle'], array_keys($res));
        $this->assertSame(['reward' => [], 'win' => -3, 'view' => 0, 'all_gold' => 9999, 'all_silver' => $this->b2Member($uid, 'silver')], $res['info']);
        $monster = \Hanjiang\MasterData\HerothroneMonster::ROWS[1];
        // 守方取 5 号位守将
        $this->assertSame(['name', 'level', 'type'], array_keys($res['battle']['right_role']));
        $this->assertSame($monster['name'], $res['battle']['right_role']['name']);
        $this->assertSame(['name', 'level', 'gender'], array_keys($res['battle']['left_role']));
        $this->assertSame(['stage' => 0, 'status' => 0], $this->b2Row('herothrone_log', $uid, ['stage', 'status']));

        // VIP 10 战败：状态改为失败，可花 50 元宝继续
        DB::table('member')->where('uid', $uid)->update(['vip' => 10]);
        $this->b2Request('herothrone', 'start', $p['auth']);
        $res = $this->b2Request('herothrone', 'action', $p['auth']);
        $this->assertSame([-3, 50, 1], [$res['info']['win'], $res['info']['price'], $res['info']['view']]);
        $this->assertSame(2, $this->b2Row('herothrone_log', $uid, ['status'])['status']);
        $this->assertSame(['status' => 3, 'price' => 1000], $this->b2Request('herothrone', 'index', $p['auth']));
        $res = $this->b2Request('herothrone', 'action', $p['auth']);
        $this->assertSame([1, 9999 - 50], [$res['status'], $res['info']['all_gold']]);
        DB::table('member')->where('uid', $uid)->update(['gold' => 49]);
        $this->assertSame(-3, $this->b2Request('herothrone', 'action', $p['auth'])['status'], '元宝不足');
        DB::table('member')->where('uid', $uid)->update(['vip' => 9]);
        $this->assertSame(-4, $this->b2Request('herothrone', 'action', $p['auth'])['status'], '先结束上一局');

        // 结束本局
        $this->assertSame(['status' => 1], $this->b2Request('herothrone', 'end_battle', $p['auth']));
        $this->assertSame(['stage' => 0, 'status' => 0], $this->b2Row('herothrone_log', $uid, ['stage', 'status']));

        // 真实战斗胜利：进度 + 1，刷新历史最高；第 10 关奖励玉石（add_jade）与银币
        $q = $this->b2Player(101);
        $quid = $q['uid'];
        $this->b2Overpower($quid);
        $this->b2Request('herothrone', 'index', $q['auth']);
        $this->b2Request('herothrone', 'start', $q['auth']);
        $res = $this->b2Request('herothrone', 'action', $q['auth']);
        $this->assertGreaterThanOrEqual(1, $res['battle']['assess']);
        $this->assertNotEmpty($res['battle']['process']);
        $this->assertSame([[], 0], [$res['info']['reward'], $res['info']['view']]);
        $this->assertSame(['stage' => 1, 'status' => 1, 'laststage' => 1], $this->b2Row('herothrone_log', $quid, ['stage', 'status', 'laststage']));
        DB::table('herothrone_log')->where('uid', $quid)->update(['stage' => 9]);
        $silver = $this->b2Member($quid, 'silver');
        $res = $this->b2Request('herothrone', 'action', $q['auth']);
        $this->assertSame([['gettype' => 2, 'getvalue' => 20000], ['gettype' => 1, 'getvalue' => 50000]], $res['info']['reward']);
        $this->assertSame([$silver + 50000, $silver + 50000], [$res['info']['all_silver'], $this->b2Member($quid, 'silver')]);
        $this->assertSame(20000, \Hanjiang\Model\MemberJade::get($quid));
        $this->assertSame(['herothrone', 'action'], array_values(DB::table('jade_log')->select('class', 'method')->where('uid', $quid)->first()));
        $this->assertSame(['stage' => 10, 'laststage' => 10], $this->b2Row('herothrone_log', $quid, ['stage', 'laststage']));
        // 打过最高层：本局结束
        DB::table('herothrone_log')->where('uid', $quid)->update(['stage' => 299]);
        $res = $this->b2Request('herothrone', 'action', $q['auth']);
        $this->assertSame(0, $res['info']['view']);
        $this->assertSame(['stage' => 0, 'status' => 0, 'laststage' => 300], $this->b2Row('herothrone_log', $quid, ['stage', 'status', 'laststage']));

        // 购买次数：VIP 9 每日 1 次、每次 1000 元宝
        $this->assertSame(['status' => -2], $this->b2Request('herothrone', 'buy_times', $q['auth']), 'VIP 不足');
        DB::table('member')->where('uid', $quid)->update(['vip' => 9, 'gold' => 999]);
        $this->assertSame(['status' => -3], $this->b2Request('herothrone', 'buy_times', $q['auth']));
        DB::table('member')->where('uid', $quid)->update(['gold' => 2500]);
        $this->assertSame(['status' => 1, 'times' => 1, 'buytimes' => 1, 'all_gold' => 1500], $this->b2Request('herothrone', 'buy_times', $q['auth']));
        $this->assertSame(['status' => -2], $this->b2Request('herothrone', 'buy_times', $q['auth']), '今日已达上限');
        $this->assertSame(1, $this->b2Row('herothrone_log', $quid, ['today_buytimes'])['today_buytimes']);

        // 跨天：index 重置免费次数与今日购买次数，购买的次数保留
        DB::table('herothrone_log')->where('uid', $quid)->update(['times' => 0, 'dateline' => strtotime('yesterday')]);
        $res = $this->b2Request('herothrone', 'index', $q['auth']);
        $this->assertSame([2, 1, 1], [$res['info']['times'], $res['info']['buytimes'], $res['buy']]);
        $this->assertSame(['times' => 2, 'buytimes' => 1, 'today_buytimes' => 0, 'today_buydate' => 0], $this->b2Row('herothrone_log', $quid, ['times', 'buytimes', 'today_buytimes', 'today_buydate']));
        // 免费次数用完后用购买的次数开局；都用完不能开局
        DB::table('herothrone_log')->where('uid', $quid)->update(['times' => 0]);
        $this->b2Request('herothrone', 'start', $q['auth']);
        $this->assertSame(['times' => 0, 'buytimes' => 0], $this->b2Row('herothrone_log', $quid, ['times', 'buytimes']));
        $this->b2Request('herothrone', 'end_battle', $q['auth']);
        $this->assertSame(['status' => -2], $this->b2Request('herothrone', 'start', $q['auth']));
    }

    public function testIslandIndexMissionAndPk(): void
    {
        $low = $this->b2Player(35);
        $this->assertSame(['status' => -1, 'list' => []], $this->b2Request('island', 'index', $low['auth']));

        $p = $this->b2Player(40);
        $uid = $p['uid'];
        $nickname = (string)DB::table('member')->where('uid', $uid)->value('nickname');
        $index = $this->b2Request('island', 'index', $p['auth']);
        $this->assertSame(1, $index['status']);
        $this->assertCount(5, $index['list']);
        $i6 = \Hanjiang\MasterData\Island::ROWS[6];
        $this->assertSame(['id' => 6, 'name' => $i6['name'], 'x' => $i6['x'], 'y' => $i6['y'], 'background' => $i6['background'], 'killed' => 0, 'openstatus' => 1], $index['list'][0]);
        $this->assertSame([10, 0], [$index['list'][4]['id'], $index['list'][1]['openstatus']]);
        $this->assertSame(['islandid' => 6, 'islandtimes' => 0], $this->b2Row('member_activity', $uid, ['islandid', 'islandtimes']));

        $this->assertSame(['status' => 1, 'info' => ['id' => 6, 'monsterlevel' => 41, 'name' => $i6['name'], 'image' => $i6['image'], 'silver' => 41000, 'gold' => 3, 'act' => 1]], $this->b2Request('island', 'get_mission', $p['auth'] + ['id' => 6]));
        $this->assertSame(['status' => -2, 'info' => []], $this->b2Request('island', 'get_mission', $p['auth'] + ['id' => 999]));
        // 等级组不符：返回 4，并把进度改到本等级组第一关
        DB::table('member_activity')->where('uid', $uid)->update(['islandid' => 8]);
        $this->assertSame(['status' => 4, 'info' => []], $this->b2Request('island', 'get_mission', $p['auth'] + ['id' => 1]));
        $this->assertSame(6, $this->b2Row('member_activity', $uid, ['islandid'])['islandid']);

        // 战败：扣军令（今日挑战次数 + 1），不加挑战次数
        $this->emptyFormation($uid);
        $act = $this->b2Member($uid, 'act');
        $res = $this->b2Request('island', 'pk', $p['auth'] + ['id' => 6]);
        $this->assertSame(['status', 'info', 'battle'], array_keys($res));
        $this->assertSame(['id' => 6, 'background' => 0, 'report' => 0, 'act' => $act - 1, 'win' => -3, 'reward_gold' => 0, 'reward_silver' => 0, 'gold' => 9999, 'silver' => $this->b2Member($uid, 'silver'), 'cd' => 0], $res['info']);
        $this->assertSame(['name' => $nickname, 'level' => 40], $res['battle']['left_role']);
        $this->assertSame(['name' => $i6['name'], 'level' => 40], $res['battle']['right_role']);
        $this->assertEqualsCanonicalizing([1, 3, 5, 7, 9], array_map('intval', array_keys($res['battle']['rightteam'])));
        $this->assertSame(0, $this->b2Row('member_activity', $uid, ['islandtimes'])['islandtimes']);

        $this->assertSame(2, $this->b2Request('island', 'pk', $p['auth'] + ['id' => 7])['status'], '未开启');
        $this->assertSame(3, $this->b2Request('island', 'pk', $p['auth'] + ['id' => 1])['status'], '等级组不符');
        DB::table('member_field')->where('uid', $uid)->update(['matrixid' => 0]);
        $this->assertSame(-4, $this->b2Request('island', 'pk', $p['auth'] + ['id' => 6])['status']);
        DB::table('member_field')->where('uid', $uid)->update(['matrixid' => 1]);
        DB::table('member')->where('uid', $uid)->update(['act' => 0]);
        $this->assertSame(4, $this->b2Request('island', 'pk', $p['auth'] + ['id' => 6])['status'], '军令不足');
        // 战斗 CD（旧版 cd_model->get_attack）
        DB::table('member')->where('uid', $uid)->update(['act' => 100]);
        DB::table('member_field')->where('uid', $uid)->update(['attackstatus' => 1, 'attackdateline' => time() + 60]);
        $this->assertSame(-1, $this->b2Request('island', 'pk', $p['auth'] + ['id' => 6])['status']);
        DB::table('member_field')->where('uid', $uid)->update(['attackstatus' => 0, 'attackdateline' => time() + 1801]);
        $this->assertSame(-1, $this->b2Request('island', 'pk', $p['auth'] + ['id' => 6])['status']);
        DB::table('member_field')->where('uid', $uid)->update(['attackdateline' => time() + 1700]);
        $this->assertSame(1, $this->b2Request('island', 'pk', $p['auth'] + ['id' => 6])['status']);
    }

    public function testIslandPkWinReportsAndProgress(): void
    {
        DB::execute('DELETE FROM `zc_sanguo_island_log`');
        DB::execute('DELETE FROM `zc_sanguo_island_report`');
        $p = $this->b2Player(40);
        $uid = $p['uid'];
        $nickname = (string)DB::table('member')->where('uid', $uid)->value('nickname');
        $this->b2Overpower($uid);
        $this->b2Request('island', 'index', $p['auth']);

        // 真实战斗：首胜开启下一关、银两必得、元宝按 50% 掉落、记战报
        $silver = $this->b2Member($uid, 'silver');
        $gold = $this->b2Member($uid, 'gold');
        $act = $this->b2Member($uid, 'act');
        $res = $this->b2Request('island', 'pk', $p['auth'] + ['id' => 6]);
        $battle = $res['battle'];
        $this->assertGreaterThanOrEqual(1, $battle['assess']);
        $this->assertNotEmpty($battle['process']);
        $info = $res['info'];
        $this->assertContains($info['reward_gold'], [0, 3]);
        $this->assertSame([41000, $silver + 41000, $gold + $info['reward_gold'], $act - 1], [$info['reward_silver'], $info['silver'], $info['gold'], $info['act']]);
        $this->assertSame(['islandid' => 7, 'islandtimes' => 1], $this->b2Row('member_activity', $uid, ['islandid', 'islandtimes']));
        $log = DB::table('island_log')->where('uid', $uid)->first();
        $this->assertSame($info['report'], (int)$log['id']);
        $this->assertSame([6, $nickname, 40, 0], [(int)$log['islandid'], $log['nickname'], (int)$log['level'], (int)$log['tree']]);

        $report = $this->b2Request('island', 'get_report_info', $p['auth'] + ['id' => $info['report']]);
        $this->assertSame(['status', 'battle', 'info'], array_keys($report));
        $expected = $battle;
        $expected['left_role'] = ['name' => $nickname, 'level' => 40];
        $expected['right_role'] = ['name' => \Hanjiang\MasterData\Island::ROWS[6]['name'], 'level' => 41];
        $this->assertEquals($expected, $report['battle']);
        $this->assertSame(['win' => $battle['assess']], $report['info']);
        $this->assertSame(['status' => 2, 'battle' => [], 'info' => []], $this->b2Request('island', 'get_report_info', $p['auth'] + ['id' => 999999]));
        $this->assertSame(['status' => -1, 'battle' => [], 'info' => []], $this->b2Request('island', 'get_report_info', $p['auth']));
        $list = $this->b2Request('island', 'get_report_list', $p['auth'] + ['id' => 6]);
        $this->assertSame([['id' => $info['report'], 'islandid' => 6, 'uid' => $uid, 'nickname' => $nickname, 'level' => 40, 'dateline' => (int)$log['dateline'], 'tree' => 0]], $list['list']);
        $this->assertSame(['status' => 2, 'list' => []], $this->b2Request('island', 'get_report_list', $p['auth'] + ['id' => 7]));

        // 再打已过的关卡：军令按今日挑战次数 + 1 递增，不再记战报，进度不变
        $res = $this->b2Request('island', 'pk', $p['auth'] + ['id' => 6]);
        $this->assertSame([0, $act - 3], [$res['info']['report'], $res['info']['act']]);
        $this->assertSame(['islandid' => 7, 'islandtimes' => 2], $this->b2Row('member_activity', $uid, ['islandid', 'islandtimes']));
        $this->assertSame(['status' => 1, 'info' => ['id' => 7, 'monsterlevel' => 43, 'name' => \Hanjiang\MasterData\Island::ROWS[7]['name'], 'image' => \Hanjiang\MasterData\Island::ROWS[7]['image'], 'silver' => 43000, 'gold' => 3, 'act' => 3]], $this->b2Request('island', 'get_mission', $p['auth'] + ['id' => 7]));
        $index = $this->b2Request('island', 'index', $p['auth']);
        $this->assertSame([1, 1, 0, 1], [$index['list'][0]['killed'], $index['list'][1]['openstatus'], $index['list'][1]['killed'], $index['status']]);

        // 跨天进入：回到本等级组第一关
        DB::table('member_activity')->where('uid', $uid)->update(['islanddate' => strtotime('yesterday')]);
        $this->b2Request('island', 'index', $p['auth']);
        $this->assertSame(6, $this->b2Row('member_activity', $uid, ['islandid'])['islandid']);
        // 升级到新等级组：进度改到新等级组第一关
        DB::table('member')->where('uid', $uid)->update(['level' => 50]);
        $index = $this->b2Request('island', 'index', $p['auth']);
        $this->assertSame([11, 1], [$index['list'][0]['id'], $index['list'][0]['openstatus']]);
        $this->assertSame(11, $this->b2Row('member_activity', $uid, ['islandid'])['islandid']);

        // 战报分享：扣 2 元宝，全服发金银洞战报（type 8）
        $w = $this->newGuestPlayer();
        $this->chatOnline($w);
        $gold = $this->b2Member($uid, 'gold');
        $this->assertSame(['status' => -1], $this->b2Request('island', 'send_to_chat', $p['auth'] + ['id' => 6]));
        $this->assertSame(['status' => -2], $this->b2Request('island', 'send_to_chat', $p['auth'] + ['id' => 999, 'report' => 5]));
        $this->assertSame(['status' => 1, 'all_gold' => $gold - 2], $this->b2Request('island', 'send_to_chat', $p['auth'] + ['id' => 6, 'report' => 5]));
        $this->assertSame($gold - 2, $this->b2Member($uid, 'gold'));
        $name = \Hanjiang\MasterData\Island::ROWS[6]['name'];
        $this->assertSame([[8, $this->chatFrom($p) . ' 在金银洞击败了 {9,' . $name . '} {11,[查看战报],5}', 5, $uid]], $this->chatMessages($w));
    }

    // ---------------------------------------------------------------
    // 批次 batch3：群雄逐鹿 / 国家矿脉 / 国战（按旧版移植）
    // ---------------------------------------------------------------

    /**
     * @param array<string, mixed> $member
     * @return array{uid: int, auth: array<string, mixed>}
     */
    private function b3Player(int $level, array $member = []): array
    {
        $p = $this->newGuestPlayer();
        DB::table('member')->where('uid', $p['uid'])->update(['level' => $level, 'nickname' => 'p' . $p['uid']] + $member);
        Redis::del('player:' . $p['uid']);
        return $p;
    }

    /**
     * 国家（玩家军团）
     */
    private function b3Country(int $level): int
    {
        return (int)DB::table('country')->insertGetId(['name' => '国' . substr((string)hrtime(true), -6), 'level' => $level, 'member' => 1, 'uid' => 0, 'nickname' => '']);
    }

    /**
     * 让玩家加入国家（member.country + member_country，加入时间为 0，不受当天加入的限制）
     *
     * @param array{uid: int} $p
     */
    private function b3Join(array $p, int $country, int $job): void
    {
        DB::table('member')->where('uid', $p['uid'])->update(['country' => $country]);
        DB::table('member_country')->insert(['uid' => $p['uid'], 'nickname' => 'p' . $p['uid'], 'country' => $country, 'job' => $job, 'joindateline' => 0]);
        Redis::del('player:' . $p['uid']);
    }

    private function b3At(string $datetime): int
    {
        $ts = (int)strtotime($datetime);
        \Hanjiang\Model\BossClock::freeze($ts);
        return $ts;
    }

    private function competeReset(): void
    {
        DB::execute('DELETE FROM `zc_sanguo_compete`');
        DB::execute('DELETE FROM `zc_sanguo_compete_action_log`');
        Redis::del('compete_topuser');
    }

    /**
     * 群雄逐鹿活动行（ActivityTime：compete）
     *
     * @return array<string, int|string>
     */
    private static function competeActivity(): array
    {
        foreach (\Hanjiang\MasterData\ActivityTime::ROWS as $row) {
            if ($row['name'] === 'compete') {
                return $row;
            }
        }
        throw new \RuntimeException('compete');
    }

    public function testCompeteClosedInProduction(): void
    {
        $this->competeReset();
        $p = $this->b3Player(30);
        foreach (['index', 'get_reward', 'get_reward_list', 'buy_pk_num', 'action'] as $m) {
            $this->assertSame(-1, $this->b2Request('compete', $m, $p['auth'])['status'], $m);
        }
        $this->assertSame(0, DB::table('compete')->count(), '活动未开启时不入榜');
    }

    public function testCompeteLadderChallengeAndRewards(): void
    {
        $this->competeReset();
        $end = (int)self::competeActivity()['endtime'];
        try {
            $now = $this->b3At(date('Y-m-d H:i:s', $end - 86400 * 5));
            $low = $this->b3Player(25);
            foreach (['index', 'get_reward', 'get_reward_list', 'buy_pk_num', 'action'] as $m) {
                $this->assertSame(2, $this->b2Request('compete', $m, $low['auth'])['status'], $m);
            }

            // 首次打开以「最后一名 + 1」入榜，挑战次数 100
            [$a, $b, $c, $d] = [$this->b3Player(26), $this->b3Player(26), $this->b3Player(26), $this->b3Player(26)];
            foreach ([$a, $b, $c, $d] as $i => $p) {
                $res = $this->b2Request('compete', 'index', $p['auth']);
                $this->assertSame(1, $res['status']);
                $this->assertSame($i + 1, $res['log']['rank']);
            }
            $this->assertSame(['uid' => $d['uid'], 'nickname' => 'p' . $d['uid'], 'rank' => 4, 'times' => 100, 'dateline' => $now], $res['log'], '首次插入返回插入的数据');
            $this->assertSame($end - 86400 - $now, $res['cd']);
            $this->assertSame(0, $res['is_get']);
            $this->assertSame(0, $res['type']);
            $reward4 = \Hanjiang\MasterData\CompeteReward::ROWS[4];
            $this->assertSame(\Hanjiang\MasterData\MountTemp::ROWS[$reward4['value1']], $res['reward']['value1'], '坐骑换成坐骑模板');
            $this->assertSame([3, $reward4['value2'], 5, $reward4['value3'], 2, $reward4['value4'], '4~11'], [$res['reward']['type2'], $res['reward']['value2'], $res['reward']['type3'], $res['reward']['value3'], $res['reward']['type4'], $res['reward']['value4'], $res['reward']['rank_str']]);
            $this->assertSame([1, 2, 3], array_keys($res['top_list']));
            $this->assertSame(['uid' => $a['uid'], 'nickname' => 'p' . $a['uid'], 'tree' => 0], $res['top_list'][1]);
            // 可挑战名单：名次 1 ~ 5 + 自己，比自己靠前的 status 1
            $this->assertSame([1, 2, 3, 4], array_column($res['list'], 'rank'));
            $this->assertSame([1, 1, 1, 0], array_column($res['list'], 'status'));
            $this->assertSame(['uid', 'nickname', 'rank', 'get_rank', 'status', 'gender', 'level'], array_keys($res['list'][1]));

            // 奖励列表：10 档，坐骑换成模板
            $list = $this->b2Request('compete', 'get_reward_list', $a['auth']);
            $this->assertSame(1, $list['status']);
            $this->assertSame([1, 2, 3, '4~11', '11~21', '21~31', '31~51', '51~76', '76~101', '101~200'], array_column($list['list'], 'rank_str'));
            $this->assertSame(\Hanjiang\MasterData\MountTemp::ROWS[\Hanjiang\MasterData\CompeteReward::ROWS[1]['value1']], $list['list'][0]['value1']);

            // 购买次数：2 元宝 + 1 次
            $this->assertSame(['status' => 1, 'price' => 2], $this->b2Request('compete', 'buy_pk_num', $d['auth'] + ['t' => 0]));
            $gold = $this->b2Member($d['uid'], 'gold');
            $buy = $this->b2Request('compete', 'buy_pk_num', $d['auth'] + ['t' => 1]);
            $this->assertSame(['status' => 1, 'price' => 2, 'gold' => $gold - 2, 'times' => 101], $buy);
            $this->assertSame($gold - 2, $this->b2Member($d['uid'], 'gold'));

            // 挑战未结束不能领奖
            $this->assertSame(-2, $this->b2Request('compete', 'get_reward', $a['auth'])['status']);

            // 守门：不在名单 / 打比自己低的 / 对方正在 pk / 次数用完
            $outsider = $this->b3Player(26);
            $this->assertSame(-4, $this->b2Request('compete', 'action', $c['auth'] + ['t' => $outsider['uid']])['status']);
            $this->assertSame(-7, $this->b2Request('compete', 'action', $b['auth'] + ['t' => $c['uid']])['status']);
            Redis::set('compete_battle_' . $a['uid'], 1, 500);
            $this->assertSame(-5, $this->b2Request('compete', 'action', $c['auth'] + ['t' => $a['uid']])['status']);
            Redis::del('compete_battle_' . $a['uid']);
            DB::table('compete')->where('uid', $b['uid'])->update(['times' => 0]);
            $this->assertSame(-3, $this->b2Request('compete', 'action', $b['auth'] + ['t' => $a['uid']])['status']);

            // D（4）挑战阵法里没有武将的 A（1）：空队伍直接完胜（assess 3），互换名次
            $this->emptyFormation($a['uid']);
            $win = $this->b2Request('compete', 'action', $d['auth'] + ['t' => $a['uid']]);
            $this->assertSame(1, $win['status']);
            $info = $win['info'];
            $this->assertSame(3, $info['win']);
            $this->assertSame(1, $info['rank']);
            $this->assertSame(1, $info['reward_status']);
            $this->assertSame(\Hanjiang\MasterData\MountTemp::ROWS[\Hanjiang\MasterData\CompeteReward::ROWS[1]['value1']], $info['reward']['value1']);
            $this->assertSame(100, $info['times']);
            $this->assertSame([1, 2, 3, 4], array_column($info['list'], 'rank'));
            $this->assertSame([$d['uid'], $b['uid'], $c['uid'], $a['uid']], array_column($info['list'], 'uid'));
            $this->assertSame([$d['uid'], $b['uid'], $c['uid']], array_column($info['top_list'], 'uid'), '前 3 名缓存已刷新');
            $this->assertSame(['id' => $info['report'], 'type' => 'compete'], $win['share']);
            $this->assertSame(['leftteam', 'rightteam', 'process', 'assess', 'leftresult', 'rightresult', 'leftbuff', 'rightbuff', 'left_role', 'right_role'], array_keys($win['battle']));
            $this->assertSame([], $win['battle']['rightteam']);
            $this->assertSame([4, 1], [(int)DB::table('compete')->where('uid', $a['uid'])->value('rank'), (int)DB::table('compete')->where('uid', $d['uid'])->value('rank')]);
            $mail = $this->messagesOf($a['uid']);
            $last = end($mail);
            $this->assertSame([4, $d['uid'], $info['report']], [$last['type'], $last['fromuid'], $last['id']]);
            $this->assertStringEndsWith('在群雄逐鹿将您击败，排名跌落至第4名。', $last['message']);
            $this->assertSame(1, DB::table('compete_action_log')->where('uid', $d['uid'])->count());

            // A（4）阵法为空挑战 C（3）：直接大败，名次不变
            $lose = $this->b2Request('compete', 'action', $a['auth'] + ['t' => $c['uid']]);
            $this->assertSame([-3, 4, 0, []], [$lose['info']['win'], $lose['info']['rank'], $lose['info']['reward_status'], $lose['info']['reward']]);
            $this->assertSame(99, $lose['info']['times']);
            $mail = $this->messagesOf($c['uid']);
            $this->assertStringEndsWith('在群雄逐鹿向您挑战，被您打的落花流水。', end($mail)['message']);

            // 双方都有阵容的正常战斗
            $real = $this->b2Request('compete', 'action', $c['auth'] + ['t' => $b['uid']]);
            $this->assertSame(1, $real['status']);
            $this->assertNotEmpty($real['battle']['leftteam']);
            $this->assertNotEmpty($real['battle']['rightteam']);
            $this->assertNotEmpty($real['battle']['process']);
            $won = $real['battle']['assess'] >= 1;
            $this->assertSame($won ? 2 : 3, (int)DB::table('compete')->where('uid', $c['uid'])->value('rank'));

            // 挑战结束（活动最后一天）：不能挑战 / 买次数，可以领奖
            $this->b3At(date('Y-m-d H:i:s', $end - 3600));
            $this->assertSame(-2, $this->b2Request('compete', 'action', $c['auth'] + ['t' => $d['uid']])['status']);
            $this->assertSame(-2, $this->b2Request('compete', 'buy_pk_num', $c['auth'] + ['t' => 1])['status']);
            $silver = $this->b2Member($d['uid'], 'silver');
            $soul = $this->b2Member($d['uid'], 'soul');
            $reward = $this->b2Request('compete', 'get_reward', $d['auth']);
            $row1 = \Hanjiang\MasterData\CompeteReward::ROWS[1];
            $this->assertSame(1, $reward['status']);
            // 坐骑照旧版 add_mount 发进 own_mount，value 换成 add_mount 返回的坐骑数据
            $mountRow = DB::table('own_mount')->where('uid', $d['uid'])->where('mid', $row1['value1'])->whereNull('deleted_at')->first();
            $this->assertNotNull($mountRow);
            $this->assertSame([$d['uid'], (int)$row1['value1'], \Hanjiang\MasterData\MountTemp::ROWS[$row1['value1']]['name'], (int)$mountRow['attack']], [$reward['reward']['value1']['uid'], $reward['reward']['value1']['mid'], $reward['reward']['value1']['name'], $reward['reward']['value1']['attack']]);
            $this->assertSame($silver + $row1['value4'], $this->b2Member($d['uid'], 'silver'));
            $this->assertSame($soul + $row1['value3'], $this->b2Member($d['uid'], 'soul'));
            $this->assertSame($silver + $row1['value4'], $reward['all_silver']);
            $this->assertSame($row1['value2'], \Hanjiang\Model\TraderLog::jade($d['uid'], 3), '紫宝石');
            $this->assertSame(1, (int)DB::table('compete')->where('uid', $d['uid'])->value('get_rank'));
            $this->assertSame(-5, $this->b2Request('compete', 'get_reward', $d['auth'])['status']);
            $this->assertSame(1, $this->b2Request('compete', 'index', $d['auth'])['is_get']);
            $this->b2Request('compete', 'index', $outsider['auth']);
            DB::table('compete')->where('uid', $outsider['uid'])->update(['rank' => 201]);
            $this->assertSame(-4, $this->b2Request('compete', 'get_reward', $outsider['auth'])['status']);
        } finally {
            \Hanjiang\Model\BossClock::freeze(null);
        }
    }

    public function testCompeteCronSettlesUnclaimedRewards(): void
    {
        $this->competeReset();
        $end = (int)self::competeActivity()['endtime'];
        $p = $this->b3Player(26);
        $q = $this->b3Player(26);
        DB::table('compete')->insert(['uid' => $p['uid'], 'nickname' => 'x', 'rank' => 12, 'times' => 0, 'get_rank' => 0, 'dateline' => 0]);
        DB::table('compete')->insert(['uid' => $q['uid'], 'nickname' => 'y', 'rank' => 2, 'times' => 0, 'get_rank' => 2, 'dateline' => 0]);
        $this->assertSame(-2, \Hanjiang\Cron\CompeteCron::run($end - 1), '活动未结束');
        $this->assertSame(-3, \Hanjiang\Cron\CompeteCron::run($end + 36001), '结束超过 10 小时');
        $silver = $this->b2Member($p['uid'], 'silver');
        $qSilver = $this->b2Member($q['uid'], 'silver');
        $this->assertSame(1, \Hanjiang\Cron\CompeteCron::run($end + 3600));
        $this->assertSame($silver + \Hanjiang\MasterData\CompeteReward::ROWS[5]['value4'], $this->b2Member($p['uid'], 'silver'), '12 名取 11 ~ 21 档');
        $this->assertSame(12, (int)DB::table('compete')->where('uid', $p['uid'])->value('get_rank'));
        $this->assertSame($qSilver, $this->b2Member($q['uid'], 'silver'), '已领取的不重复发放');
        $this->assertSame(-4, \Hanjiang\Cron\CompeteCron::run($end + 3600), '没有可发放的用户');
    }

    /**
     * 国家矿脉玩家：91 级并加入国家
     *
     * @return array{uid: int, auth: array<string, mixed>}
     */
    private function minePlayer(int $country): array
    {
        $p = $this->b3Player(91);
        $this->b3Join($p, $country, 51);
        return $p;
    }

    public function testCountryMineIndexCaikuangAndGiveUp(): void
    {
        DB::execute('DELETE FROM `zc_sanguo_country_mine`');
        try {
            $now = $this->b3At('2026-10-27 10:00:00');
            $low = $this->b3Player(90);
            $noCountry = $this->b3Player(91);
            foreach (['index', 'get_countrymine_info', 'get_reward', 'give_up', 'caikuang', 'rob'] as $m) {
                $params = in_array($m, ['caikuang', 'rob'], true) ? ['p' => 2, 'id' => 1, 't' => 3] : [];
                $this->assertSame(2, $this->b2Request('countrymine', $m, $low['auth'] + $params)['status'], $m);
                $this->assertSame(3, $this->b2Request('countrymine', $m, $noCountry['auth'] + $params)['status'], $m);
            }

            $country = $this->b3Country(5);
            $a = $this->minePlayer($country);
            $res = $this->b2Request('countrymine', 'index', $a['auth']);
            $this->assertSame(1, $res['status']);
            $this->assertSame(2, $res['page']);
            $this->assertSame(['uid' => $a['uid'], 'times' => 3, 'robtimes' => 3, 'page' => 0, 'site' => 0, 'type' => 0, 'level' => 0, 'jointime' => 0, 'dateline' => $now], $res['log']);
            $this->assertSame($now, $res['dateline']);
            // 第 1 页：1 个 1 类矿（挪到第 5 个显示）+ 8 个 2 类矿
            $this->assertSame([2, 3, 4, 5, 1, 6, 7, 8, 9], array_column($res['list'], 'id'));
            $this->assertSame(['id' => 1, 'page' => 1, 'type' => 1, 'status' => 0, 'series' => 6, 'jade' => 65520, 'get_jade' => 0], $res['list'][4], '没有人占矿的页面累计收益为 0');
            $this->assertSame(-1, $this->b2Request('countrymine', 'index', $a['auth'] + ['p' => 11])['status']);
            $this->assertSame(0, $this->b2Request('countrymine', 'index', $a['auth'] + ['p' => 10])['page']);

            // 开采：页码 / 位置 / 类型与配置不符时 -5
            $this->assertSame(-5, $this->b2Request('countrymine', 'caikuang', $a['auth'] + ['p' => 11, 'id' => 1, 't' => 5])['status']);
            $this->assertSame(-5, $this->b2Request('countrymine', 'caikuang', $a['auth'] + ['p' => 1, 'id' => 1, 't' => 2])['status']);
            $this->assertSame(-5, $this->b2Request('countrymine', 'caikuang', $a['auth'] + ['p' => 1, 'id' => 10, 't' => 2])['status']);
            Redis::set('countrymine_lock_1_1', 1, 600);
            $this->assertSame(-5, $this->b2Request('countrymine', 'caikuang', $a['auth'] + ['p' => 1, 'id' => 1, 't' => 1])['status']);
            Redis::del('countrymine_lock_1_1');
            $dig = $this->b2Request('countrymine', 'caikuang', $a['auth'] + ['p' => 1, 'id' => 1, 't' => 1]);
            $countryName = (string)DB::table('country')->where('id', $country)->value('name');
            $this->assertSame(['status' => 1, 'country' => $countryName, 'country_name' => mb_substr($countryName, 0, 1), 'dateline' => 900, 'times' => 2], $dig);
            $this->assertNull(Redis::get('countrymine_lock_1_1'), '操作完成后解锁');
            $this->assertSame($now + 900, (int)DB::table('member_field')->where('uid', $a['uid'])->value('oncountrymine'));
            $this->assertSame(-2, $this->b2Request('countrymine', 'caikuang', $a['auth'] + ['p' => 1, 'id' => 2, 't' => 2])['status'], '已占领一个矿');
            $b = $this->minePlayer($country);
            $this->assertSame(-4, $this->b2Request('countrymine', 'caikuang', $b['auth'] + ['p' => 1, 'id' => 1, 't' => 1])['status'], '该矿已被占领');

            // 5 分钟后：矿区页面与矿区信息
            $this->b3At('2026-10-27 10:05:30');
            $page = $this->b2Request('countrymine', 'index', $a['auth']);
            $mine = $page['list'][4];
            $this->assertSame([1, 2, 'p' . $a['uid'], 91, $a['uid'], $countryName, 570, 65520, 4368 * 5, 0], [$mine['id'], $mine['status'], $mine['nickname'], $mine['level'], $mine['uid'], $mine['country'], $mine['time'], $mine['jade'], $mine['get_jade'], $mine['friend']]);
            $info = $this->b2Request('countrymine', 'get_countrymine_info', $b['auth'] + ['p' => 1, 'id' => 1, 't' => 1]);
            $this->assertSame(['uid' => $a['uid'], 'nickname' => 'p' . $a['uid'], 'country' => $countryName, 'country_name' => mb_substr($countryName, 0, 1), 'status' => 1, 'time' => 570, 'jade' => 65520, 'get_jade' => 21840, 'friend' => 0], $info['info']);
            $empty = $this->b2Request('countrymine', 'get_countrymine_info', $b['auth'] + ['p' => 2, 'id' => 3, 't' => 3]);
            $this->assertSame(['status' => 0, 'get_jade' => 0, 'jade' => 27300, 'time' => 0, 'friend' => 0, 'country' => 0, 'uid' => 0, 'nickname' => 0], $empty['info']);

            // 未到收获时间不能收获；放弃得到已累积的玉石（5 分钟 × 每分钟 4368）
            $this->assertSame(-3, $this->b2Request('countrymine', 'get_reward', $a['auth'])['status']);
            $give = $this->b2Request('countrymine', 'give_up', $a['auth']);
            // 玉石发进 member.jade（add_jade），all_jade 为加后的余额
            $this->assertSame(['status' => 1, 'jade' => 21840, 'all_jade' => 21840], $give);
            $this->assertSame(21840, \Hanjiang\Model\MemberJade::get($a['uid']));
            $this->assertSame(['countrymine', 'give_up', 21840, 21840], [($l = DB::table('jade_log')->where('uid', $a['uid'])->first())['class'], $l['method'], (int)$l['jade'], (int)$l['balance']]);
            $this->assertSame(0, (int)DB::table('member_field')->where('uid', $a['uid'])->value('oncountrymine'));
            $this->assertSame(-2, $this->b2Request('countrymine', 'give_up', $a['auth'])['status']);
            $this->assertSame(-2, $this->b2Request('countrymine', 'get_reward', $a['auth'])['status']);

            // 次数用完；次日重置 3 / 3
            DB::table('country_mine')->where('uid', $a['uid'])->update(['times' => 0]);
            $this->assertSame(-3, $this->b2Request('countrymine', 'caikuang', $a['auth'] + ['p' => 1, 'id' => 1, 't' => 1])['status']);
            $this->b3At('2026-10-28 00:00:01');
            $this->assertSame([3, 3], [$this->b2Request('countrymine', 'index', $a['auth'])['log']['times'], $this->b2Request('countrymine', 'index', $a['auth'])['log']['robtimes']]);
        } finally {
            \Hanjiang\Model\BossClock::freeze(null);
        }
    }

    /**
     * 第一个让 mt_rand(1, 100) 依次落在要求区间的种子
     *
     * @param list<array{int, int}> $ranges 每次 mt_rand 的 [min, max] 与期望区间 [lo, hi]
     */
    private function mineSeed(array $ranges): int
    {
        for ($seed = 1; $seed < 200000; $seed++) {
            mt_srand($seed);
            $ok = true;
            foreach ($ranges as [$min, $max, $lo, $hi]) {
                $roll = mt_rand($min, $max);
                if ($roll < $lo || $roll > $hi) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return $seed;
            }
        }
        $this->fail('no seed');
    }

    public function testCountryMineHarvestEventsRobAndFightNotice(): void
    {
        DB::execute('DELETE FROM `zc_sanguo_country_mine`');
        try {
            $now = $this->b3At('2026-10-27 12:00:00');
            $x = $this->b3Country(5);
            $y = $this->b3Country(5);
            $a = $this->minePlayer($x);
            $b = $this->minePlayer($y);
            $a2 = $this->minePlayer($x);

            // 收获：占满 15 分钟，不触发事件（第一次掷点大于 1 类矿的事件概率 50）
            $this->assertSame(1, $this->b2Request('countrymine', 'caikuang', $a['auth'] + ['p' => 1, 'id' => 1, 't' => 1])['status']);
            $this->b3At('2026-10-27 12:15:00');
            $this->assertSame(1, $this->b2Request('member', 'index', $a['auth'])['oncountrymine'], '主城提示国家矿可收获');
            mt_srand($this->mineSeed([[1, 100, 51, 100]]));
            $gold = $this->b2Member($a['uid'], 'gold');
            $harvest = $this->b2Request('countrymine', 'get_reward', $a['auth']);
            $this->assertSame(['status' => 1, 'event_status' => 0, 'event_name' => '', 'event_type' => 0, 'event_value' => 0, 'event_add' => 0, 'jade' => 65520, 'all_jade' => 65520, 'all_gold' => $gold], $harvest);
            $this->assertSame([0, 0], array_map('intval', array_values(DB::table('country_mine')->select('page', 'site')->where('uid', $a['uid'])->first())));
            $this->assertSame(0, $this->b2Request('member', 'index', $a['auth'])['oncountrymine']);

            // 收获触发事件「石中藏金-小」（权重 91 ~ 120）：加 10 元宝
            $this->assertSame(1, $this->b2Request('countrymine', 'caikuang', $a['auth'] + ['p' => 1, 'id' => 2, 't' => 2])['status']);
            $this->b3At('2026-10-27 12:30:00');
            mt_srand($this->mineSeed([[1, 100, 1, 40], [1, 180, 91, 120]]));
            $gold = $this->b2Member($a['uid'], 'gold');
            $harvest = $this->b2Request('countrymine', 'get_reward', $a['auth']);
            $this->assertSame([1, '石中藏金-小', 3, 10, 0, 91 * 120 * 4], [$harvest['event_status'], $harvest['event_name'], $harvest['event_type'], $harvest['event_value'], $harvest['event_add'], $harvest['jade']]);
            $this->assertSame($gold + 10, $this->b2Member($a['uid'], 'gold'));

            // 抢夺：同国家不能抢；A 的矿被 B（他国）抢
            $this->assertSame(1, $this->b2Request('countrymine', 'caikuang', $a['auth'] + ['p' => 2, 'id' => 1, 't' => 3])['status']);
            $this->assertSame(-5, $this->b2Request('countrymine', 'rob', $a2['auth'] + ['p' => 2, 'id' => 1, 't' => 3])['status']);
            $this->assertSame(-4, $this->b2Request('countrymine', 'rob', $b['auth'] + ['p' => 2, 'id' => 2, 't' => 3])['status'], '空矿不能抢');
            $this->assertSame(-4, $this->b2Request('countrymine', 'rob', $b['auth'] + ['p' => 2, 'id' => 1, 't' => 5])['status'], '类型与矿区配置不符');
            Redis::set('countrymine_lock_2_1', 1, 600);
            $this->assertSame(-7, $this->b2Request('countrymine', 'rob', $b['auth'] + ['p' => 2, 'id' => 1, 't' => 3])['status']);
            Redis::del('countrymine_lock_2_1');

            // 10 分钟后 B 抢夺阵法为空的 A：完胜，A 被清出，B 占矿，得到 10 分钟累积的玉石；掷点不触发事件
            $this->b3At('2026-10-27 12:40:00');
            $this->emptyFormation($a['uid']);
            mt_srand($this->mineSeed([[1, 100, 31, 100]]));
            $rob = $this->b2Request('countrymine', 'rob', $b['auth'] + ['p' => 2, 'id' => 1, 't' => 3]);
            $this->assertSame(1, $rob['status']);
            $perMinute = intdiv(91 * 120 * 25 / 10, 15);
            $this->assertSame([3, 2, $perMinute * 10, \Hanjiang\Model\MemberJade::get($b['uid']), 0, 'p' . $a['uid']], [$rob['info']['win'], $rob['info']['robtimes'], $rob['info']['jade'], $rob['info']['all_jade'], $rob['info']['event_status'], $rob['info']['name']]);
            $this->assertSame($perMinute * 10, (int)DB::table('jade_log')->where('uid', $b['uid'])->where('method', 'rob')->value('jade'));
            $this->assertSame(['id' => $rob['info']['report'], 'type' => 'countrymine'], $rob['share']);
            $this->assertSame(['name', 'level', 'gender'], array_keys($rob['battle']['right_role']));
            $this->assertSame([0, 0], array_map('intval', array_values(DB::table('country_mine')->select('page', 'site')->where('uid', $a['uid'])->first())));
            $this->assertSame([2, 1, 3, 91, $now + 2400], array_map('intval', array_values(DB::table('country_mine')->select('page', 'site', 'type', 'level', 'jointime')->where('uid', $b['uid'])->first())));
            $this->assertSame(0, (int)DB::table('member_field')->where('uid', $a['uid'])->value('oncountrymine'));
            $this->assertSame($now + 2400 + 900, (int)DB::table('member_field')->where('uid', $b['uid'])->value('oncountrymine'));
            $mail = $this->messagesOf($a['uid']);
            $last = end($mail);
            $this->assertSame([11, $b['uid'], $rob['info']['report']], [$last['type'], $last['fromuid'], $last['id']]);
            $this->assertStringEndsWith('攻打了您的国家矿，掠夺了你的玉石' . ($perMinute * 10), $last['message']);
            $notice = DB::table('country_fight_notice')->where('attack_uid', $b['uid'])->first();
            $this->assertSame([0, $a['uid'], 'p' . $a['uid'], $x, $y, 1], [(int)$notice['type'], (int)$notice['member1'], $notice['member1_name'], (int)$notice['member_country'], (int)$notice['attack_country'], (int)$notice['win']]);
            // 国家战报：B 的国家是荣誉（type 1），A 的国家是屈辱（type 0）
            $honor = $this->b2Request('country', 'fight_notice', $b['auth'] + ['type' => 1]);
            $this->assertSame(1, $honor['total']);
            $this->assertSame(['type' => 0, 'attack' => $b['uid'], 'attack_nickname' => 'p' . $b['uid'], 'member_country' => $x, 'member_country_name' => (string)DB::table('country')->where('id', $x)->value('name'), 'member1' => $a['uid'], 'member1_name' => 'p' . $a['uid'], 'dateline' => date('m-d H:i:s', $now + 2400)], $honor['log'][0]);
            $this->assertSame(1, $this->b2Request('country', 'fight_notice', $a['auth'] + ['type' => 0])['total']);
            $this->assertSame(-2, $this->b2Request('countrymine', 'rob', $b['auth'] + ['p' => 2, 'id' => 2, 't' => 3])['status'], '已占领一个矿不能再抢');

            // A2（同国）阵法为空去抢 B 的矿以外：A 阵法为空抢 B 的矿，直接大败
            $lose = $this->b2Request('countrymine', 'rob', $a['auth'] + ['p' => 2, 'id' => 1, 't' => 3]);
            $this->assertSame([1, -3, 2, 0], [$lose['status'], $lose['info']['win'], $lose['info']['robtimes'], $lose['info']['jade']]);
            $mail = $this->messagesOf($b['uid']);
            $this->assertStringEndsWith('攻打了您的国家矿，被您击败了。', end($mail)['message']);
            DB::table('country_mine')->where('uid', $a['uid'])->update(['robtimes' => 0]);
            $this->assertSame(-3, $this->b2Request('countrymine', 'rob', $a['auth'] + ['p' => 2, 'id' => 1, 't' => 3])['status']);

            // 双方都有阵容的正常抢夺
            $real = $this->b2Request('countrymine', 'rob', $a2['auth'] + ['p' => 2, 'id' => 1, 't' => 3]);
            $this->assertSame(1, $real['status']);
            $this->assertNotEmpty($real['battle']['leftteam']);
            $this->assertNotEmpty($real['battle']['rightteam']);
            $this->assertSame($real['battle']['assess'] >= 1 ? $a2['uid'] : $b['uid'], (int)DB::table('country_mine')->where('page', 2)->where('site', 1)->value('uid'));

            // 战报分享：扣 2 元宝，全服发国家矿区战报（get_message_str 26，消息 type 3）
            $this->chatOnline($a);
            $gold = $this->b2Member($b['uid'], 'gold');
            $this->assertSame(['status' => 1, 'all_gold' => $gold - 2], $this->b2Request('countrymine', 'send_to_chat', $b['auth'] + ['touid' => $a['uid'], 'report' => 5, 'win' => 1]));
            $this->assertSame([[3, $this->chatFrom($b) . ' 在国家矿区抢夺 ' . $this->chatFrom($a) . ' 的矿中 胜利  {2,[查看战报],5}', 5, $b['uid']]], $this->chatMessages($a));
            $this->assertSame(['status' => -1], $this->b2Request('countrymine', 'send_to_chat', $b['auth'] + ['touid' => 0, 'report' => 5]));
        } finally {
            \Hanjiang\Model\BossClock::freeze(null);
        }
    }

    /**
     * 国战准备：清空城池 / 战报 / 国战 Redis，并让 13 座都城全部开放（全服主线进度到第 14 个势力）
     */
    private function siegeReset(): void
    {
        foreach (['country_city', 'country_city_report', 'country_siege_log'] as $table) {
            DB::execute('DELETE FROM `zc_sanguo_' . $table . '`');
        }
        foreach (Redis::keys('siege_*') as $key) {
            $prefix = (string)Redis::instance()->getOption(\Redis::OPT_PREFIX);
            Redis::del(str_starts_with($key, $prefix) ? substr($key, strlen($prefix)) : $key);
        }
        DB::table('map')->where('uid', 0)->delete();
        DB::table('map')->insert(['uid' => 0, 'level' => 14, 'stage' => 1, 'mission' => 1]);
    }

    private function siegeCleanup(): void
    {
        \Hanjiang\Model\BossClock::freeze(null);
        DB::table('map')->where('uid', 0)->delete();
    }

    public function testSiegeIndexCapitalInfoAndOccupy(): void
    {
        $this->siegeReset();
        try {
            $this->b3At('2026-10-26 12:00:00');
            $none = $this->b3Player(30);
            $this->assertSame(['status' => -1], $this->b2Request('siege', 'index', $none['auth']));
            $this->assertSame(['status' => -1], $this->b2Request('siege', 'capital_info', $none['auth'] + ['id' => 1]));

            $x = $this->b3Country(5);
            $y = $this->b3Country(3);
            $kingX = $this->b3Player(60, ['vip' => 7, 'gold' => 5000]);
            $this->b3Join($kingX, $x, 1);
            $memberX = $this->b3Player(60);
            $this->b3Join($memberX, $x, 30);
            $officerX = $this->b3Player(60);
            $this->b3Join($officerX, $x, 2);
            $kingY = $this->b3Player(60, ['vip' => 7, 'gold' => 5000]);
            $this->b3Join($kingY, $y, 1);

            // 退国 7 天内
            $left = $this->b3Player(30);
            $this->b3Join($left, $x, 51);
            DB::table('member_country')->where('uid', $left['uid'])->update(['leavedateline' => (int)strtotime('2026-10-25 12:00:00')]);
            $this->assertSame(['status' => 3, 'cd' => 6 * 86400], $this->b2Request('siege', 'index', $left['auth']));

            $index = $this->b2Request('siege', 'index', $kingX['auth']);
            $this->assertSame(1, $index['status']);
            $this->assertCount(13, $index['list']);
            $this->assertSame(['id' => 1, 'name' => '濮阳', 'time' => 1830, 'type' => 1, 'value' => 3, 'team' => '张角', 'open' => 1, 'size' => 0, 'occupy' => 0, 'occupy_name' => ''], $index['list'][0]);
            $this->assertSame([0, 0, 1, 0, 0, 1, 1, 1, 1, 0, 1, 0, 1], array_column($index['list'], 'size'));

            $info = $this->b2Request('siege', 'capital_info', $kingX['auth'] + ['id' => 1]);
            $this->assertSame(['status' => 1, 'info' => [
                'id' => 1, 'name' => '濮阳', 'time' => '周六18:30分', 'team' => '张角', 'open' => 1, 'str' => '全国成员免费征收次数+3',
                'castellanid' => 0, 'castellanname' => '张角', 'generalname' => '张角', 'challengercid' => 0, 'challengername' => '张角',
                'status' => 0, 'force' => 0,
            ], 'price' => 1000], $info);
            $this->assertSame('全国通天塔每日领取500%', $this->b2Request('siege', 'capital_info', $kingX['auth'] + ['id' => 13])['info']['str']);
            $this->assertSame(['status' => -2], $this->b2Request('siege', 'capital_info', $kingX['auth'] + ['id' => 14]));

            // 占领（请战）守门
            $this->assertSame(-2, $this->b2Request('siege', 'occupy', $memberX['auth'] + ['id' => 1])['status'], '官职不够');
            $this->assertSame(-5, $this->b2Request('siege', 'occupy', $kingX['auth'] + ['id' => 14])['status']);
            // 全服主线进度只到第 1 个势力（其他用例的进度暂时软删除）：都城未开放
            $others = array_column(DB::table('map')->select('id')->where('uid', '<>', 0)->whereNull('deleted_at')->get(), 'id');
            DB::table('map')->whereIn('id', $others !== [] ? $others : [0])->update(['deleted_at' => date('Y-m-d H:i:s')]);
            DB::table('map')->where('uid', 0)->update(['level' => 1]);
            try {
                $this->assertSame(0, $this->b2Request('siege', 'index', $kingX['auth'])['list'][0]['open']);
                $this->assertSame(-6, $this->b2Request('siege', 'occupy', $kingX['auth'] + ['id' => 1])['status'], '都城未开放');
            } finally {
                DB::table('map')->whereIn('id', $others !== [] ? $others : [0])->update(['deleted_at' => null]);
                DB::table('map')->where('uid', 0)->update(['level' => 14]);
            }
            $this->b3At('2026-10-30 18:00:00');
            $this->assertSame(-3, $this->b2Request('siege', 'occupy', $kingX['auth'] + ['id' => 1])['status'], '周五 18 点起不能占领');
            $this->b3At('2026-10-26 12:00:00');

            // X 请战 NPC 城濮阳
            $ok = $this->b2Request('siege', 'occupy', $kingX['auth'] + ['id' => 1]);
            $this->assertSame(['status' => 1], $ok);
            $city = DB::table('country_city')->where('maplevel', 1)->first();
            $this->assertSame([$x, 0, 0], [(int)$city['challengercid'], (int)$city['cid'], (int)$city['lock']]);
            $this->assertSame([1, 2], [(int)DB::table('country')->where('id', $x)->value('city'), (int)DB::table('country')->where('id', $x)->value('city_status')]);
            $mail = $this->messagesOf($memberX['uid']);
            $this->assertSame([7, 1000, '国家通知'], [end($mail)['type'], end($mail)['fromuid'], end($mail)['fromname']]);
            $this->assertStringEndsWith('成功对濮阳发起了攻占,请各位做好作战准备，请在周六18时30分准备应战！', end($mail)['message']);
            $this->assertSame(-4, $this->b2Request('siege', 'occupy', $kingX['auth'] + ['id' => 1])['status']);
            $this->assertSame(2, $this->b2Request('siege', 'capital_info', $kingX['auth'] + ['id' => 1])['status'], '本国已请战');
            $this->assertSame([2, ''], [$this->b2Request('siege', 'index', $kingX['auth'])['list'][0]['occupy'], $this->b2Request('siege', 'index', $kingX['auth'])['list'][0]['occupy_name']]);

            // 主城国战提示：国家已请战，等待开战（敌方为 NPC）
            $war = $this->b2Request('member', 'index', $kingX['auth']);
            $this->assertSame([0, 0, '17:30', '张角军团', 0], [$war['country_war'], $war['is_saturday'], $war['country_date'], $war['enemy_name'], $war['country_lock']]);

            // Y（3 级）普通攻占：等级不高于挑战方 X（5 级）失败
            $low = $this->b2Request('siege', 'occupy', $kingY['auth'] + ['id' => 1]);
            $this->assertSame(-8, $low['status']);
            $this->assertStringContainsString('国家等级低于当前挑战方', $low['msg']);
            // Y 升到 6 级：取代 X 的挑战资格，X 成员收到信件
            DB::table('country')->where('id', $y)->update(['level' => 6]);
            $take = $this->b2Request('siege', 'occupy', $kingY['auth'] + ['id' => 1]);
            $this->assertSame(1, $take['status']);
            $this->assertStringContainsString('国家等级超过', $take['msg']);
            $this->assertSame($y, (int)DB::table('country_city')->where('maplevel', 1)->value('challengercid'));
            $this->assertSame([0, 0], [(int)DB::table('country')->where('id', $x)->value('city'), (int)DB::table('country')->where('id', $x)->value('city_status')]);
            $mail = $this->messagesOf($memberX['uid']);
            $this->assertStringContainsString('自动变为进攻方', end($mail)['message']);

            // X 请战邺城后改打下邳：先返回 3 询问，确认后放弃邺城
            $this->assertSame(1, $this->b2Request('siege', 'occupy', $kingX['auth'] + ['id' => 2])['status']);
            $ask = $this->b2Request('siege', 'occupy', $kingX['auth'] + ['id' => 8]);
            $this->assertSame(3, $ask['status']);
            $this->assertStringStartsWith('您当前已经申请打城池【邺城】，确认攻打【下邳】', $ask['msg']);
            $this->assertSame(1, $this->b2Request('siege', 'occupy', $kingX['auth'] + ['id' => 8, 'sure' => 1])['status']);
            $this->assertSame(0, (int)DB::table('country_city')->where('maplevel', 2)->value('challengercid'));
            $this->assertSame([8, 2], [(int)DB::table('country')->where('id', $x)->value('city'), (int)DB::table('country')->where('id', $x)->value('city_status')]);

            // 至尊攻城：非君主 / vip 不足 / 元宝不足
            $this->assertSame(-11, $this->b2Request('siege', 'occupy', $officerX['auth'] + ['id' => 1, 'force' => 1, 'sure' => 1])['status']);
            DB::table('member')->where('uid', $kingX['uid'])->update(['vip' => 6]);
            $this->assertSame(['status' => -8, 'msg' => 'vip等级达到7级才可使用至尊攻城！'], $this->b2Request('siege', 'occupy', $kingX['auth'] + ['id' => 1, 'force' => 1, 'sure' => 1]));
            DB::table('member')->where('uid', $kingX['uid'])->update(['vip' => 7, 'gold' => 999]);
            $this->assertSame(-13, $this->b2Request('siege', 'occupy', $kingX['auth'] + ['id' => 1, 'force' => 1, 'sure' => 1])['status']);
            DB::table('member')->where('uid', $kingX['uid'])->update(['gold' => 5000]);

            // 至尊攻城失败（X 君主阵法为空）：扣 1000 元宝，返回战报，城池不变
            $this->emptyFormation($kingX['uid']);
            $fail = $this->b2Request('siege', 'occupy', $kingX['auth'] + ['id' => 1, 'force' => 1, 'sure' => 1]);
            $this->assertSame([1, -3], [$fail['status'], $fail['info']['win']]);
            $this->assertSame(['p' . $kingY['uid'], 60], [$fail['battle']['right_role']['name'], $fail['battle']['right_role']['level']]);
            $this->assertSame(4000, $this->b2Member($kingX['uid'], 'gold'));
            $this->assertSame($y, (int)DB::table('country_city')->where('maplevel', 1)->value('challengercid'));

            // 至尊攻城成功（Y 君主阵法为空）：取代 Y 的挑战资格并锁定城池；X 放弃下邳
            $this->emptyFormation($kingY['uid']);
            $this->b2Overpower($kingX['uid']);
            DB::table('matrix')->where('uid', $kingX['uid'])->update(['position5' => (int)DB::table('general')->where('uid', $kingX['uid'])->value('id')]);
            $w = $this->chatPlayer();
            $this->chatOnline($w);
            $this->chatOnline($kingY);
            $force = $this->b2Request('siege', 'occupy', $kingX['auth'] + ['id' => 1, 'force' => 1, 'sure' => 1]);
            $this->assertSame(1, $force['status']);
            $this->assertSame(3, $force['info']['win']);
            // 聊天室：两条至尊攻城小喇叭（全服），并进相关三国成员的国家聊天室（type 6 系统）
            $horns = $this->chatMessages($w);
            $this->assertSame([2, 2], array_column($horns, 0));
            $this->assertStringContainsString('发起至尊攻城成功', $horns[0][1]);
            $this->assertStringContainsString('获得挑战资格', $horns[1][1]);
            $this->assertSame([[2, $horns[0][1]], [2, $horns[1][1]], [6, $horns[1][1]], [6, $horns[0][1]]], array_map(static fn (array $m): array => [$m[0], $m[1]], $this->chatMessages($kingY)));
            $this->assertSame(3000, $this->b2Member($kingX['uid'], 'gold'));
            $this->assertSame([$x, 1], [(int)DB::table('country_city')->where('maplevel', 1)->value('challengercid'), (int)DB::table('country_city')->where('maplevel', 1)->value('lock')]);
            $this->assertSame(0, (int)DB::table('country_city')->where('maplevel', 8)->value('challengercid'));
            $mail = $this->messagesOf($kingY['uid']);
            $this->assertStringContainsString('发起至尊攻城成功', end($mail)['message']);
            $this->assertSame(1, $this->b2Request('siege', 'capital_info', $kingX['auth'] + ['id' => 1])['info']['force']);
            // 锁定后：Y 普通攻占不能取代；X 不能改打别的城
            DB::table('country')->where('id', $y)->update(['level' => 9]);
            $this->assertStringContainsString('已被', $this->b2Request('siege', 'occupy', $kingY['auth'] + ['id' => 1])['msg']);
            $this->assertSame(-10, $this->b2Request('siege', 'occupy', $kingX['auth'] + ['id' => 3])['status']);
        } finally {
            $this->siegeCleanup();
        }
    }

    public function testSiegeBattleFlowRewardsAndCityEffect(): void
    {
        $this->siegeReset();
        try {
            $x = $this->b3Country(5);
            $players = [];
            for ($job = 1; $job <= 5; $job++) {
                $p = $this->b3Player(60);
                $this->b3Join($p, $x, $job);
                $this->b2Overpower($p['uid']);
                $players[] = $p;
            }
            $king = $players[0];
            DB::table('country_city')->insert(['maplevel' => 1, 'challengercid' => $x, 'challengername' => 'x', 'challengerlevel' => 5, 'challengerdateline' => 0]);
            DB::table('country')->where('id', $x)->update(['city' => 1, 'city_status' => 2]);

            // 周六 17:20：报名尚未开始
            $this->b3At('2026-10-31 17:20:00');
            $early = $this->b2Request('siege', 'battle_prepare', $king['auth']);
            $this->assertSame(['status' => -5, 'msg' => '国战报名将于周六17:30开启,请准时参加'], $early);
            $this->b3At('2026-10-30 17:40:00');
            $this->assertSame(['status' => -2], $this->b2Request('siege', 'battle_prepare', $king['auth']), '不是周六');
            $this->assertSame(['status' => -2], $this->b2Request('siege', 'join_battle', $king['auth']));

            // 周六 17:40：报名
            $now = $this->b3At('2026-10-31 17:40:00');
            $prep = $this->b2Request('siege', 'battle_prepare', $king['auth']);
            $this->assertSame(1, $prep['status']);
            $this->assertSame([1, 0, 0, '濮阳', '张角军团', 0, 28, 1], [$prep['type'], $prep['in_battle'], $prep['attack_num'], $prep['city_name'], $prep['defense_name'], $prep['defense_level'], $prep['defense_num'], $prep['npc']]);
            $this->assertSame(['uid' => 2, 'nickname' => '裴元绍', 'level' => 30], $prep['defense'][0]);
            $this->assertSame(50 * 60 + 10, $prep['dateline']);
            $war = $this->b2Request('member', 'index', $king['auth']);
            $this->assertSame([1, 1], [$war['country_war'], $war['is_saturday']]);
            foreach ($players as $i => $p) {
                $join = $this->b2Request('siege', 'join_battle', $p['auth']);
                $this->assertSame(1, $join['status']);
                $this->assertSame(2, $join['type']);
                $this->assertSame($i + 1, $join['attack_num']);
            }
            $this->assertSame(array_reverse(array_column($players, 'uid')), array_column($join['attack'], 'uid'), '按官职倒序');
            $this->assertSame([1, 1], array_map('intval', array_values(DB::table('member_country')->select('city', 'warstatus')->where('uid', $king['uid'])->first())));
            $this->assertSame(-6, $this->b2Request('siege', 'join_battle', $king['auth'])['status']);
            $this->assertSame(1, $this->b2Request('siege', 'battle_prepare', $king['auth'])['in_battle']);
            $this->b3At('2026-10-31 18:28:00');
            $late = $this->b3Player(60);
            $this->b3Join($late, $x, 51);
            $this->assertSame(-5, $this->b2Request('siege', 'join_battle', $late['auth'])['status'], '开战前 3 分钟停止报名');

            // 开战前进入战场：-5
            $this->assertSame(['status' => -5], $this->b2Request('siege', 'action', $king['auth']));

            // 计划任务 18:27 起算出战斗：5 名玩家各连胜 6 场打完 28 名 NPC 守将，攻方胜，X 占领濮阳
            $this->assertSame(0, \Hanjiang\Cron\SiegeCron::battle((int)strtotime('2026-10-31 18:26:00')));
            $at = (int)strtotime('2026-10-31 18:27:00');
            $w = $this->chatPlayer();
            $this->chatOnline($w);
            $this->chatOnline($players[1]);
            $this->assertSame(1, \Hanjiang\Cron\SiegeCron::battle($at));
            // 聊天室：全服系统公告；攻方国家成员收到国家战报 20（计划任务里没有发信人，旧版把国家id 错位传进 tree）
            $xName = (string)DB::table('country')->where('id', $x)->value('name');
            $win = '【' . $xName . '】击败了【张角军团】 ，成功占领【濮阳】。';
            $this->assertSame([[7, $win, 0, 0]], $this->chatMessages($w));
            $read = $this->request('chat', 'read', $players[1]['auth'])['list'];
            $this->assertSame([[7, 0, 0, $win], [6, 1, $x, '{9,' . $xName . '}击败了{9,张角军团}，攻占【{9,濮阳}】{14,[查看战报],1}']], array_map(static fn (array $m): array => [$m['type'], $m['system'], $m['tree'], $m['message']], $read));
            $this->assertSame(0, \Hanjiang\Cron\SiegeCron::battle($at + 60), '已有战报不重复计算');
            $city = DB::table('country_city')->where('maplevel', 1)->first();
            $this->assertSame([$x, 0, $at, 0], [(int)$city['cid'], (int)$city['challengercid'], (int)$city['dateline'], (int)$city['lock']]);
            $this->assertSame([1, 1], [(int)DB::table('country')->where('id', $x)->value('city'), (int)DB::table('country')->where('id', $x)->value('city_status')]);
            $this->assertSame(1, DB::table('country_city_report')->count());
            $log = DB::table('country_siege_log')->first();
            $this->assertSame([1, '濮阳', 1, $x, 0, '张角军团', 0], [(int)$log['city'], $log['city_name'], (int)$log['win_type'], (int)$log['attack_country'], (int)$log['country'], $log['country_name'], (int)$log['zhizun']]);
            $this->assertNull(Redis::get('siege_1_attack'), '报名列表已清空');

            // 进入战场播放战报
            $this->b3At('2026-10-31 18:31:00');
            $act = $this->b2Request('siege', 'action', $players[2]['auth']);
            $this->assertSame(1, $act['status']);
            $this->assertSame(1, $act['info']['win']);
            $this->assertSame(['win', 'battle_ready', 'battle_log', 'general_arr', 'kill_rank'], array_keys($act['battle']));
            $this->assertSame(1, $act['battle']['win']);
            $this->assertSame([5, 28, 1, '濮阳'], [$act['attack_num'], $act['defense_num'], $act['npc'], $act['city_name']]);
            $this->assertSame(['id' => (int)DB::table('country_city_report')->value('id'), 'type' => 'siege'], $act['share']);
            $this->assertSame(array_sum($act['battle']['kill_rank']['left']), 28, '攻方杀敌数合计为 28');
            $report = $this->b2Request('siege', 'get_report', $king['auth'] + ['id' => 1]);
            $this->assertSame([1, $act['battle'], '濮阳', 5], [$report['status'], $report['battle'], $report['city_name'], $report['attack_num']]);
            $this->assertSame(['status' => -1], $this->b2Request('siege', 'get_report', $king['auth'] + ['id' => 2]));

            // 领奖：国家 5 级俸禄 × 官职系数，占领成功 3 倍；先看杀敌榜
            $rule = \Hanjiang\MasterData\CountryLevel::ROWS[5];
            $ratio = \Hanjiang\MasterData\CountryJob::ROWS[1]['ratio'];
            $view = $this->b2Request('siege', 'get_reward', $king['auth']);
            $this->assertSame(1, $view['status']);
            $this->assertSame(['level' => 5, 'silver' => (int)round(60 * $rule['silver'] * $ratio) * 3, 'act' => (int)round($rule['act'] * $ratio) * 3, 'soul' => (int)round($rule['soul'] * $ratio) * 3, 'jade' => 0], $view['rule']);
            $this->assertCount(5, $view['list']);
            $this->assertSame(['nickname', 'num', 'uid'], array_keys($view['list'][0]));
            $silver = $this->b2Member($king['uid'], 'silver');
            $get = $this->b2Request('siege', 'get_reward', $king['auth'] + ['type' => 1]);
            $this->assertSame($silver + $view['rule']['silver'], $get['all_silver']);
            $this->assertSame([0, 0], array_map('intval', array_values(DB::table('member_country')->select('city', 'warstatus')->where('uid', $king['uid'])->first())));
            $this->assertSame(['status' => -2], $this->b2Request('siege', 'get_reward', $king['auth'] + ['type' => 1]));
            // 91 级以上俸禄含玉石（add_jade，大于 0 才加）
            DB::table('member_country')->where('uid', $king['uid'])->update(['warstatus' => 1]);
            DB::table('member')->where('uid', $king['uid'])->update(['level' => 91]);
            $jade = (int)round(91 * $rule['jade'] * $ratio) * 3;
            $this->assertGreaterThan(0, $jade);
            $this->assertSame(1, $this->b2Request('siege', 'get_reward', $king['auth'] + ['type' => 1])['status']);
            $this->assertSame($jade, \Hanjiang\Model\MemberJade::get($king['uid']));
            $this->assertSame(['siege', 'get_reward'], array_values(DB::table('jade_log')->select('class', 'method')->where('uid', $king['uid'])->first()));

            // 国战日志：周六 21:00 以后显示本周
            $this->assertSame([], $this->b2Request('siege', 'siege_notice', $king['auth'])['log']);
            $this->b3At('2026-10-31 21:30:00');
            $this->assertCount(1, $this->b2Request('siege', 'siege_notice', $king['auth'])['log']);
            $this->b3At('2026-11-02 10:00:00');
            $this->assertCount(1, $this->b2Request('siege', 'siege_notice', $king['auth'])['log'], '下周显示上周');
            $this->assertSame(['status' => 2, 'log' => []], $this->b2Request('siege', 'siege_notice', $this->b3Player(25)['auth']));

            // 每周六 00:00 清除参战状态
            $this->assertSame(0, \Hanjiang\Cron\SiegeCron::clearWarstatus((int)strtotime('2026-11-06 00:00:00')));
            $this->assertSame(1, (int)DB::table('member_country')->where('uid', $players[1]['uid'])->value('warstatus'));
            $this->assertGreaterThan(0, \Hanjiang\Cron\SiegeCron::clearWarstatus((int)strtotime('2026-11-07 00:00:00')));
            $this->assertSame(0, (int)DB::table('member_country')->where('uid', $players[1]['uid'])->value('warstatus'));

            // 都城效果：濮阳为征收 +3，每天 7 点后首次调用加一次
            $now = $this->b3At('2026-11-02 10:00:00');
            $kingRow = DB::table('member')->where('uid', $king['uid'])->first();
            $levy = (int)DB::table('member_field')->where('uid', $king['uid'])->value('levytimes');
            $effect = \Hanjiang\Handler\Pvp\SiegeHandler::getCityEffect($kingRow, $now);
            $this->assertSame(['status' => 1, 'times' => $levy + 3, 'type' => 1, 'value' => 3], $effect);
            $this->assertSame(['status' => 1, 'times' => 0, 'type' => 1, 'value' => 3], \Hanjiang\Handler\Pvp\SiegeHandler::getCityEffect($kingRow, $now));
            $this->assertSame($levy + 3, (int)DB::table('member_field')->where('uid', $king['uid'])->value('levytimes'));
            DB::table('member_country')->where('uid', $king['uid'])->update(['joindateline' => $now]);
            $this->assertSame(-5, \Hanjiang\Handler\Pvp\SiegeHandler::getCityEffect($kingRow, $now)['status'], '当天加入国家');
            $outsider = DB::table('member')->where('uid', $this->b3Player(60)['uid'])->first();
            $this->assertSame(-1, \Hanjiang\Handler\Pvp\SiegeHandler::getCityEffect($outsider, $now)['status']);
            // 将魂星路（都城效果 type 4，寿春）：占领后每日奖励加成
            DB::table('country_city')->where('maplevel', 1)->update(['maplevel' => 7]);
            DB::table('member_country')->where('uid', $king['uid'])->update(['joindateline' => 0]);
            $this->assertSame(['status' => 1, 'times' => 0, 'type' => 4, 'value' => 100], \Hanjiang\Handler\Pvp\SiegeHandler::getCityEffect($kingRow, $now));

            // 国家解散时清除国战状态
            $this->assertSame(1, \Hanjiang\Handler\Pvp\SiegeHandler::clearBattleStatus($x));
            $this->assertSame(0, (int)DB::table('country_city')->where('maplevel', 7)->value('cid'));
        } finally {
            $this->siegeCleanup();
        }
    }

    public function testSiegeNpcLosesAndMonsterSiegeAndPvpRush(): void
    {
        $this->siegeReset();
        try {
            // 单人进攻 NPC 城：连胜 6 场后下阵，NPC 守城成功，挑战资格清除
            $x = $this->b3Country(5);
            $p = $this->b3Player(60);
            $this->b3Join($p, $x, 1);
            $this->b2Overpower($p['uid']);
            DB::table('country_city')->insert(['maplevel' => 2, 'challengercid' => $x, 'challengername' => 'x', 'challengerlevel' => 5, 'challengerdateline' => 0]);
            DB::table('country')->where('id', $x)->update(['city' => 2, 'city_status' => 2]);
            $this->b3At('2026-10-31 18:00:00');
            $this->assertSame(1, $this->b2Request('siege', 'join_battle', $p['auth'])['status']);
            // 邺城 18:40 开战：玩家在国战期间进入战场时由本次请求算出战斗
            $this->b3At('2026-10-31 18:41:00');
            $act = $this->b2Request('siege', 'action', $p['auth']);
            $this->assertSame([1, 2, 2], [$act['status'], $act['info']['win'], $act['battle']['win']]);
            $this->assertSame([$p['uid'] => 6], $act['battle']['kill_rank']['left']);
            $this->assertSame([0, 0], [(int)DB::table('country_city')->where('maplevel', 2)->value('challengercid'), (int)DB::table('country')->where('id', $x)->value('city_status')]);
            $this->assertSame(0, (int)DB::table('country_siege_log')->where('city', 2)->value('win_type'));
            $this->assertSame($act['battle'], $this->b2Request('siege', 'action', $p['auth'])['battle'], '再次进入读同一份战报');
            // 战报 6 天内仍可回放；之后没有战报
            $this->b3At('2026-11-03 12:00:00');
            $this->assertSame(1, $this->b2Request('siege', 'action', $p['auth'])['status']);
            Redis::del('siege_2_battle');
            $this->assertSame(1, $this->b2Request('siege', 'action', $p['auth'])['status'], 'Redis 战报丢失时读数据库');
            $this->b3At('2026-11-07 12:00:00');
            Redis::del('siege_2_battle');
            $this->assertSame(['status' => -2], $this->b2Request('siege', 'action', $p['auth']));

            // 怪物攻城（团战类型 3）：Y 占领北平且没有挑战国，NPC 守将进攻，Y 单人守城连胜 6 场后下阵，城池回到 NPC
            $y = $this->b3Country(4);
            $d = $this->b3Player(60);
            $this->b3Join($d, $y, 1);
            $this->b2Overpower($d['uid']);
            DB::table('country_city')->insert(['maplevel' => 3, 'cid' => $y, 'name' => 'y', 'level' => 4]);
            DB::table('country')->where('id', $y)->update(['city' => 3, 'city_status' => 1]);
            $this->b3At('2026-11-07 18:00:00');
            $prep = $this->b2Request('siege', 'battle_prepare', $d['auth']);
            $this->assertSame([1, 2, '公孙瓒军团', 0], [$prep['status'], $prep['type'], $prep['attack_name'], $prep['attack_level']]);
            $this->assertSame(1, $this->b2Request('siege', 'join_battle', $d['auth'])['status']);
            $this->assertSame(1, \Hanjiang\Cron\SiegeCron::battle((int)strtotime('2026-11-07 18:47:00')));
            $data = Redis::get('siege_3_battle');
            $this->assertSame([3, 1, 1], [$data['npc'], $data['att_type'], $data['battle']['win']]);
            $this->assertSame([$d['uid'] => 6], $data['battle']['kill_rank']['right']);
            $this->assertSame([0, 0], [(int)DB::table('country_city')->where('maplevel', 3)->value('cid'), (int)DB::table('country')->where('id', $y)->value('city_status')]);

            // 玩家对玩家（团战类型 0）：Z 挑战 Y 占领的长安，双方各两人，正常战斗
            $z = $this->b3Country(4);
            DB::table('country_city')->insert(['maplevel' => 4, 'cid' => $y, 'name' => 'y', 'level' => 4, 'challengercid' => $z, 'challengername' => 'z', 'challengerlevel' => 4]);
            DB::table('country')->where('id', $y)->update(['city' => 4, 'city_status' => 3]);
            DB::table('country')->where('id', $z)->update(['city' => 4, 'city_status' => 2]);
            DB::table('general')->where('uid', $d['uid'])->update(['level' => 60]);
            $d2 = $this->b3Player(60);
            $this->b3Join($d2, $y, 2);
            $a1 = $this->b3Player(60);
            $this->b3Join($a1, $z, 1);
            $a2 = $this->b3Player(60);
            $this->b3Join($a2, $z, 2);
            DB::table('member_country')->whereIn('uid', [$d['uid'], $d2['uid']])->update(['warstatus' => 0, 'city' => 0]);
            foreach ([$d, $d2, $a1, $a2] as $q) {
                $this->assertSame(1, $this->b2Request('siege', 'join_battle', $q['auth'])['status']);
            }
            $this->b3At('2026-11-07 19:01:00');
            $act = $this->b2Request('siege', 'action', $a1['auth']);
            $this->assertSame(1, $act['status']);
            $this->assertSame([0, 2, 2], [$act['npc'], $act['attack_num'], $act['defense_num']]);
            $this->assertSame([$a2['uid'], $a1['uid']], array_column($act['attack'], 'uid'));
            $this->assertNotEmpty($act['battle']['battle_log']);
            $win = $act['battle']['win'];
            $this->assertSame($win === 1 ? 1 : 2, $act['info']['win']);
            $this->assertSame($win === 1 ? 2 : 1, $this->b2Request('siege', 'action', $d['auth'])['info']['win'], '守方看到相反的结果');
            $this->assertSame($win === 1 ? $z : $y, (int)DB::table('country_city')->where('maplevel', 4)->value('cid'));
        } finally {
            $this->siegeCleanup();
        }
    }
}
