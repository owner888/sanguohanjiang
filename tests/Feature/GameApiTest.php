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
        // 旧版 MasterData Map 1（黄巾势力）通关奖励：装备 6 狮心锁环甲
        $this->assertSame(6, $shiliReward['item']['id']);
        $this->assertSame('狮心锁环甲', $shiliReward['item']['name']);
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
        $this->withStrongFormation(function (): void {
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
        });
    }

    public function testPassGeneralSystem(): void
    {
        $this->withStrongFormation(function (): void {
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
        });
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
        // 依赖未移植：将魂（re_type 27）无法发放，不扣积分
        $this->assertSame(-3, $this->request('arena', 'shop_exchange', $a['auth'] + ['id' => 19])['status']);
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
    }

    public function testArenaSendToChat(): void
    {
        [$b, $a] = $this->freshArenaPlayers(2);
        $this->assertSame(-1, $this->request('arena', 'send_to_chat', $a['auth'] + ['touid' => $b['uid']])['status']);
        $this->assertSame(-3, $this->request('arena', 'send_to_chat', $a['auth'] + ['touid' => 999999999, 'report' => 5])['status']);
        DB::table('member')->where('uid', $a['uid'])->update(['gold' => 1]);
        $this->assertSame(-2, $this->request('arena', 'send_to_chat', $a['auth'] + ['touid' => $b['uid'], 'report' => 5])['status']);
        DB::table('member')->where('uid', $a['uid'])->update(['gold' => 10]);
        $res = $this->request('arena', 'send_to_chat', $a['auth'] + ['touid' => $b['uid'], 'report' => 5, 'win' => 3]);
        $this->assertSame(1, $res['status']);
        $this->assertSame(8, $res['all_gold']);
        $this->assertSame(8, (int)DB::table('member')->where('uid', $a['uid'])->value('gold'));
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
        $this->withStrongFormation(function (): void {
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
        });
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
        $this->withStrongFormation(function (): void {
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
        });
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
            $jade = Redis::getJson('user_trader:' . $uid);
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
            $this->assertSame((int)($jade['get2'] ?? 0) + 100, Redis::getJson('user_trader:' . $uid)['get2']);
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
            mt_srand(20261031);
            $draw = $this->request('first_recharge', 'action');
            $this->assertSame(1, $draw['status']);
            $this->assertSame(1, $draw['info']['gettype']);
            $this->assertSame($expected['getvalue'], $draw['info']['id']);
            $this->assertNotNull(DB::table('general')->where('uid', $uid)->where('generalid', $expected['getvalue'])->whereNull('deleted_at')->first());
            $this->assertSame(1, (int)DB::table('first_recharge_log')->where('uid', $uid)->first()['lottery']);
            $this->assertSame(5, $this->request('first_recharge', 'action')['status'], '只能抽一次');

            // 抽到已拥有的武将：按酒馆紫宝石价格 * 0.8 返还紫宝石
            DB::table('first_recharge_log')->where('uid', $uid)->update(['lottery' => 0]);
            $purple = (int)(Redis::getJson('user_trader:' . $uid)['get3'] ?? 0);
            mt_srand(20261031);
            $again = $this->request('first_recharge', 'action');
            $this->assertSame(1, $again['status']);
            $refund = (int)(\Hanjiang\MasterData\Pub::ROWS[$expected['getvalue']]['price'] * 0.8);
            $this->assertSame(3, $again['info']['jadetype']);
            $this->assertEquals($refund, $again['info']['jadenum']);
            $this->assertSame($purple + $refund, Redis::getJson('user_trader:' . $uid)['get3']);
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
        $this->withStrongFormation(function (): void {
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
        });
    }

    public function testCompeteSystem(): void
    {
        $this->withStrongFormation(function (): void {
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
        });
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
        $this->withStrongFormation(function (): void {
            // 1. 查看擂台
            $res = $this->request('challenge', 'index');
            $this->assertSame(1, $res['status']);
            $this->assertNotEmpty($res['masters']);

            // 2. 挑战擂主 1
            $pk = $this->request('challenge', 'action', ['id' => 1]);
            $this->assertSame(1, $pk['status']);
            $this->assertSame(1, $pk['win']);
            $this->assertGreaterThan(0, $pk['silver']);
        });
    }

    public function testEliteSystem(): void
    {
        $this->withStrongFormation(function (): void {
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
        });
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
        $this->withStrongFormation(function (): void {
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
        });
    }

    public function testIslandSystem(): void
    {
        $this->withStrongFormation(function (): void {
            // 1. 查看金银岛
            $res = $this->request('island', 'index');
            $this->assertSame(1, $res['status']);
            $this->assertCount(3, $res['islands']);

            // 2. 挑战蓬莱仙境岛 (island: 1)
            $battle = $this->request('island', 'pk', ['id' => 1]);
            $this->assertSame(1, $battle['status']);
            $this->assertSame(1, $battle['win']);
            $this->assertGreaterThan(0, $battle['silver']);
        });
    }

    public function testHerothroneSystem(): void
    {
        $this->withStrongFormation(function (): void {
            // 1. 查看英雄王座
            $res = $this->request('herothrone', 'index');
            $this->assertSame(1, $res['status']);
            $this->assertCount(3, $res['thrones']);

            // 2. 冲击青铜铁血王座 (throne: 1)
            $action = $this->request('herothrone', 'action', ['id' => 1]);
            $this->assertSame(1, $action['status']);
            $this->assertSame(1, $action['win']);
            $this->assertGreaterThan(0, $action['silver']);
        });
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
        $this->withStrongFormation(function (): void {
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
        });
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
        $this->withStrongFormation(function (): void {
            // 1. 查看史诗战纪
            $res = $this->request('epic', 'index');
            $this->assertSame(1, $res['status']);
            $this->assertCount(3, $res['list']);

            // 2. 挑战史诗白马之战
            $battle = $this->request('epic', 'force', ['id' => 1]);
            $this->assertSame(1, $battle['status']);
            $this->assertSame(1, $battle['win']);
            $this->assertGreaterThan(0, $battle['silver']);
        });
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
}
