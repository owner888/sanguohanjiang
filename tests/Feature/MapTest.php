<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\Map as MapTemplate;
use Hanjiang\MasterData\Mission;
use Hanjiang\MasterData\MissionNewreward;
use Hanjiang\MasterData\Monster;
use Hanjiang\MasterData\TalentScroll;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\MainQuestTask;
use Hanjiang\Model\OpenMap;
use Hanjiang\Tests\Support\Gateway;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use PHPUnit\Framework\TestCase;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * 主线推图（c=map）：旧版 controllers/map.php + models/map_model.php
 */
final class MapTest extends TestCase
{
    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function request(array $auth, string $method, array $params = [], string $controller = 'map'): array
    {
        $ctx = Context::mock(array_merge(['c' => $controller, 'm' => $method], $auth, $params));
        $res = Gateway::handle($ctx);
        if ($res instanceof WorkermanResponse) {
            $json = json_decode((string)$res->rawBody(), true);
            $this->assertIsArray($json, "map.{$method}: " . $res->rawBody());
            $res = $json;
        }
        $this->assertIsArray($res);
        unset($res['message']);
        if (($res['msg'] ?? null) === 'ok') {
            unset($res['msg']);
        }
        return $res;
    }

    /**
     * 新游客（已按旧版注册开启势力 1）
     *
     * @param array<string, mixed> $member
     * @return array{uid: int, auth: array<string, mixed>}
     */
    private function player(array $member = []): array
    {
        $reg = $this->request(['token' => '', 'uid' => ''], 'guest', [], 'register');
        $uid = (int)$reg['uid'];
        $login = $this->request(['token' => '', 'uid' => $uid], 'guest', ['guestkey' => $reg['guestkey']], 'login');
        $member += ['nickname' => 'map_' . $uid];
        DB::table('member')->where('uid', $uid)->update($member);
        Redis::del('player:' . $uid);
        return ['uid' => $uid, 'auth' => ['token' => (string)$login['token'], 'uid' => $uid]];
    }

    /**
     * 出战武将练到必胜（主线前几关）
     */
    private static function strong(int $uid): void
    {
        DB::table('general')->where('uid', $uid)->update(['level' => 120, 'wuli' => 400, 'zhili' => 400, 'tili' => 400]);
    }

    /**
     * 直接设置开启记录（open_map 势力一行）
     */
    private static function progress(int $uid, int $level, int $stage, int $mission, int $newresult = 0): void
    {
        DB::execute(
            'INSERT INTO `zc_sanguo_open_map` (`uid`, `level`, `stage`, `mission`, `newresult`) VALUES (:uid, :level, :stage, :mission, :newresult)
             ON DUPLICATE KEY UPDATE `stage` = VALUES(`stage`), `mission` = VALUES(`mission`), `newresult` = VALUES(`newresult`)',
            ['uid' => $uid, 'level' => $level, 'stage' => $stage, 'mission' => $mission, 'newresult' => $newresult]
        );
    }

    private static function missionId(int $level, int $stage, int $id): int
    {
        return (int)OpenMap::mission($level, $stage, $id)['id'];
    }

    /**
     * 玩家聊天室里收到的小喇叭（c=chat&m=read 的 type 2；第一次调用即进入聊天室）
     *
     * @param array{auth: array<string, mixed>} $p
     * @return list<string>
     */
    private function horns(array $p): array
    {
        $res = $this->request($p['auth'], 'read', [], 'chat');
        return array_values(array_map(static fn (array $m): string => (string)$m['message'], array_filter($res['list'] ?? [], static fn (array $m): bool => (int)$m['type'] === 2)));
    }

    private static function memberValue(int $uid, string $column): int
    {
        return (int)DB::table('member')->where('uid', $uid)->value($column);
    }

    /**
     * 弹窗（get_mission_list / mission）的守将名称、等级、首杀经验与实际战斗的守军、结算经验一致（旧版都取 Mission 行）
     */
    public function testPopupNameLevelAndExpMatchBattle(): void
    {
        $p = $this->player();
        self::strong($p['uid']);
        // 1-1-1 ~ 1-1-4 已打通，攻击 1-1-5
        self::progress($p['uid'], 1, 1, 4);
        $row = Mission::ROWS[5];
        $this->assertSame([1, 1, 5, '邓茂', 5], [$row['missionlevel'], $row['missionstage'], $row['missionid'], $row['name'], $row['level']]);

        $list = $this->request($p['auth'], 'get_mission_list', ['l' => 1, 's' => 1]);
        $this->assertSame([1, 0, 0], [$list['status'], $list['next_status'], $list['popo']]);
        $this->assertSame(range(1, 10), array_keys($list['list']));
        $this->assertSame(['id' => 5, 'missionlevel' => 1, 'missionstage' => 1, 'missionid' => 5, 'level' => 5, 'type' => 2, 'generalid' => 0, 'x' => $row['x'], 'y' => $row['y'], 'background' => 1, 'maxtimes' => 5, 'name' => '邓茂', 'status' => 0, 'openstatus' => 1], $list['list'][5]);

        $popup = $this->request($p['auth'], 'mission', ['l' => 1, 's' => 1, 'id' => 5]);
        $this->assertSame(1, $popup['status']);
        $this->assertSame(['邓茂', 5, 1, 2200, 2200], [$popup['info']['name'], $popup['info']['level'], $popup['info']['first'], $popup['info']['firstkillexp'], $popup['info']['gfirstkillexp']]);

        $battle = $this->request($p['auth'], 'action', ['l' => 1, 's' => 1, 'id' => 5]);
        $this->assertSame(1, $battle['status']);
        $this->assertGreaterThanOrEqual(1, $battle['info']['win']);
        // 守将即弹窗里的关卡（新手引导预制战报也按本次结算覆盖 right_role 与经验）
        $this->assertSame(['name' => $popup['info']['name'], 'level' => $popup['info']['level'], 'type' => $popup['info']['type']], $battle['battle']['right_role']);
        $this->assertSame([$popup['info']['name'], $popup['info']['firstkillexp'], $popup['info']['gfirstkillexp']], [$battle['info']['name'], $battle['info']['exp'], $battle['info']['gexp']]);
        // 1 级 + 2200 经验：升到 4 级（80 + 320 + 720），剩 1080；每级加 2 军令
        $this->assertSame([4, 1080, 1280], [self::memberValue($p['uid'], 'level'), self::memberValue($p['uid'], 'exp'), self::memberValue($p['uid'], 'maxexp')]);
        $this->assertSame([1, 1080, 1280], [$battle['info']['levelup'], $battle['info']['levelexp'], $battle['info']['levelupexp']]);

        // 非首杀弹窗与再次攻击：普通经验 exp × 次数
        $popup = $this->request($p['auth'], 'mission', ['l' => 1, 's' => 1, 'id' => 5]);
        $this->assertSame([-1, $row['exp']], [$popup['info']['first'], $popup['info']['exp']]);
        $again = $this->request($p['auth'], 'action', ['l' => 1, 's' => 1, 'id' => 5]);
        $this->assertSame(['邓茂', 5], [$again['battle']['right_role']['name'], $again['battle']['right_role']['level']]);
        $this->assertSame([$popup['info']['exp'], $popup['info']['gexp'], -1], [$again['info']['exp'], $again['info']['gexp'], $again['info']['first']]);
        // 守军就是 Mission 5 的 position5 → Monster 5
        $this->assertSame([5], array_keys($again['battle']['rightteam']));
        $this->assertSame(Monster::ROWS[5]['name'], $again['battle']['rightteam'][5]['name']);
    }

    public function testIndexSceneAndMissionListsFollowOpenMap(): void
    {
        $p = $this->player(['missionsite' => 3]);
        $uid = $p['uid'];
        $this->assertSame([['level' => 1, 'stage' => 1, 'mission' => 0, 'newresult' => 0]], array_map(
            static fn (array $r): array => ['level' => $r['level'], 'stage' => $r['stage'], 'mission' => $r['mission'], 'newresult' => $r['newresult']],
            OpenMap::rows($uid)
        ), '注册时开启势力 1');

        $index = $this->request($p['auth'], 'index');
        $this->assertSame([0, 0, 1], [$index['first'], $index['next_status'], $index['enter_world']]);
        foreach (range(1, 14) as $id) {
            $this->assertSame($id === 1 ? 1 : 0, $index[$id]['status']);
            $this->assertSame(MapTemplate::ROWS[$id]['name'], $index[$id]['name']);
        }
        $this->assertArrayNotHasKey('15', $index, '第二张地图未开启时只给第一张');
        $this->assertSame([1, 1], [self::memberValue($uid, 'missionsite'), self::memberValue($uid, 'missionworld')]);

        $scenes = $this->request($p['auth'], 'get_scene_list', ['l' => 1]);
        $this->assertSame([1, 0, 0], [$scenes['status'], $scenes['next_status'], $scenes['popo']]);
        $this->assertSame([1, 2, 3, 4], array_keys($scenes['list']));
        $this->assertSame([1, 0, 0, 0], array_column($scenes['list'], 'openstatus'));
        $this->assertSame([0, 0, 0, 0], array_column($scenes['list'], 'status'));
        $this->assertSame([2, 1], [self::memberValue($uid, 'missionsite'), self::memberValue($uid, 'missionlevel')]);
        // 势力 2 未开启（前置势力未通关）
        $this->assertSame(['status' => -1, 'popo' => 0], $this->request($p['auth'], 'get_scene_list', ['l' => 2]));

        $missions = $this->request($p['auth'], 'get_mission_list', ['l' => 1, 's' => 1]);
        $this->assertSame([1, 0, 0, 0, 0, 0, 0, 0, 0, 0], array_column($missions['list'], 'openstatus'), '默认开启已通过的下一关');
        $this->assertSame([3, 1, 1], [self::memberValue($uid, 'missionsite'), self::memberValue($uid, 'missionlevel'), self::memberValue($uid, 'missionstage')]);
        $this->assertSame(-2, $this->request($p['auth'], 'get_mission_list', ['l' => 1, 's' => 2])['status'], '场景未开启');

        // 打到 1-2-3：场景 1 全部通过，场景 2 开到第 4 关
        self::progress($uid, 1, 2, 3);
        $scenes = $this->request($p['auth'], 'get_scene_list', ['l' => 1]);
        $this->assertSame([1, 1, 0, 0], array_column($scenes['list'], 'openstatus'));
        $this->assertSame([1, 0, 0, 0], array_column($scenes['list'], 'status'));
        $this->assertSame(1, $scenes['popo'], '已通过 1-1-10，势力 1 第一个宝箱可领');
        $s1 = $this->request($p['auth'], 'get_mission_list', ['l' => 1, 's' => 1]);
        $this->assertSame([1, array_fill(0, 10, 1), array_fill(0, 10, 1)], [$s1['next_status'], array_column($s1['list'], 'openstatus'), array_column($s1['list'], 'status')]);
        $s2 = $this->request($p['auth'], 'get_mission_list', ['l' => 1, 's' => 2]);
        $this->assertSame([1, 1, 1, 1, 0, 0, 0, 0, 0, 0], array_column($s2['list'], 'openstatus'));
        $this->assertSame([1, 1, 1, 0, 0, 0, 0, 0, 0, 0], array_column($s2['list'], 'status'));
        // 不传势力 / 场景时取 member 的位置
        $this->assertSame(array_keys($s2['list']), array_keys($this->request($p['auth'], 'get_mission_list')['list']));

        // 新手引导改推图位置（c=member&m=updatemissionsite）
        $this->assertSame(['status' => 1], $this->request($p['auth'], 'updatemissionsite', ['site' => 2], 'member'));
        $this->assertSame(2, self::memberValue($uid, 'missionsite'));
        $this->assertSame(2, $this->request($p['auth'], 'index', [], 'member')['missionsite']);
    }

    public function testMissionPopupTimesPricesAndDrops(): void
    {
        $p = $this->player(['vip' => 3, 'level' => 10]);
        $uid = $p['uid'];
        self::progress($uid, 1, 1, 2);
        // 1-1-2 裴元绍：首杀前
        $info = $this->request($p['auth'], 'mission', ['l' => 1, 's' => 1, 'id' => 2]);
        $equip = Equip::ROWS[1];
        $this->assertSame(1, $info['status']);
        $this->assertSame([1, 5, 5, $equip['name'], $equip['quality'], '0', 0], [
            $info['info']['first'], $info['info']['maxtimes'], $info['info']['nowmaxtimes'], $info['info']['getitemname'], $info['info']['quality'], $info['info']['getrate'], $info['mop_up'],
        ]);
        $this->assertArrayNotHasKey('five_sub', $info['info']);

        // 首杀过、今天没打过：刷 5 次免费，10 次 = 强攻 1~5 次（2+4+6+8+10），50 / 100 次
        DB::table('mission_log')->insert(['uid' => $uid, 'missionlevel' => 1, 'missionstage' => 1, 'missionid' => 2, 'times' => 1, 'dateline' => time() - 86400 * 2]);
        $info = $this->request($p['auth'], 'mission', ['l' => 1, 's' => 1, 'id' => 2]);
        $this->assertSame([-1, 5, 0, 30, OpenMap::priceByTimes(0, 45), OpenMap::priceByTimes(0, 95), 1], [
            $info['info']['first'], $info['info']['nowmaxtimes'], $info['info']['five_sub'], $info['info']['ten_sub'], $info['info']['fifty_sub'], $info['info']['hundred_sub'], $info['mop_up'],
        ]);
        // 主公比关卡高 5 级以上：非首杀主公经验为 0
        $this->assertSame(0, $info['info']['exp']);
        // 今天普通次数用完、已强攻 3 次：强攻一次第 4 次的价格
        DB::table('mission_log')->where('uid', $uid)->update(['times' => 5, 'strom' => 3, 'dateline' => time()]);
        $info = $this->request($p['auth'], 'mission', ['l' => 1, 's' => 1, 'id' => 2]);
        $this->assertSame([0, 8, 2 * (4 + 5 + 6 + 7 + 8)], [$info['info']['nowmaxtimes'], $info['info']['coin'], $info['info']['five_sub']]);

        // 天赋卷轴碎片关卡（gettype 6）显示卷轴名 + 碎片；不判断关卡是否开启，但势力未开启照样拒绝
        $m = null;
        foreach (Mission::ROWS as $row) {
            if ($row['gettype'] === 6) {
                $m = $row;
                break;
            }
        }
        $this->assertNotNull($m);
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'mission', ['l' => $m['missionlevel'], 's' => $m['missionstage'], 'id' => $m['missionid']]));
        self::progress($uid, $m['missionlevel'], $m['missionstage'], 0);
        $info = $this->request($p['auth'], 'mission', ['l' => $m['missionlevel'], 's' => $m['missionstage'], 'id' => $m['missionid']]);
        $scroll = TalentScroll::ROWS[$m['getvalue']];
        $this->assertSame([Equipment::scrollName($scroll['type']) . '碎片', $scroll['quality']], [$info['info']['getitemname'], $info['info']['quality']]);
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'mission', ['l' => 99, 's' => 1, 'id' => 1]));
    }

    public function testActionGatesSweepAndStrongAttackCosts(): void
    {
        $p = $this->player(['level' => 16, 'gold' => 1000, 'vip' => 2]);
        $uid = $p['uid'];
        self::strong($uid);
        $this->assertSame(-5, $this->request($p['auth'], 'action', ['l' => 1, 's' => 1, 'id' => 3])['status'], '关卡未开启');
        $this->assertSame(-4, $this->request($p['auth'], 'action', ['l' => 1, 's' => 2, 'id' => 1])['status'], '场景未开启');
        $this->assertSame(-3, $this->request($p['auth'], 'action', ['l' => 2, 's' => 1, 'id' => 1])['status'], '势力未开启');
        $this->assertSame(-8, $this->request($p['auth'], 'action', ['l' => 1, 's' => 1, 'id' => 1, 'times' => 5])['status'], '首杀不能扫荡');
        DB::table('member')->where('uid', $uid)->update(['act' => 0]);
        $this->assertSame(-1, $this->request($p['auth'], 'action', ['l' => 1, 's' => 1, 'id' => 1])['status']);
        DB::table('member')->where('uid', $uid)->update(['act' => 200]);

        // 1-1-2 首杀：消耗 1 军令，记首杀、主线任务 1 的「击败关卡」
        self::progress($uid, 1, 1, 1);
        $first = $this->request($p['auth'], 'action', ['l' => 1, 's' => 1, 'id' => 2]);
        $this->assertSame([1, 1], [$first['status'], $first['info']['first']]);
        $this->assertSame(199, self::memberValue($uid, 'act'));
        $this->assertSame(1, MainQuestTask::taskInfo($uid, 1, 2));
        $this->assertSame(0, MainQuestTask::taskInfo($uid, 1, 3));
        $this->assertSame(2, OpenMap::rows($uid)[0]['mission']);
        $this->assertSame(-9, $this->request($p['auth'], 'action', ['l' => 1, 's' => 1, 'id' => 2, 'times' => 5])['status'], 'VIP 3 才能扫荡');

        // 扫荡 5 次：首杀那次也算今天的普通次数（add_mission_log 的 times 1），剩 4 次免费、第 5 次强攻（Price 1 = 2 元宝）；
        // 扣 5 军令，强制胜利、不返回 battle，掉落按次数掷（getrate 100）
        DB::table('member')->where('uid', $uid)->update(['vip' => 3]);
        $gold = self::memberValue($uid, 'gold');
        $chips = count(DB::table('equipment')->where('uid', $uid)->where('equipid', 1)->get());
        $sweep = $this->request($p['auth'], 'action', ['l' => 1, 's' => 1, 'id' => 2, 'times' => 5]);
        $this->assertSame(1, $sweep['status']);
        $this->assertArrayNotHasKey('battle', $sweep);
        $this->assertSame([1, 5, 1, OpenMap::priceByTimes(1, 5), OpenMap::priceByTimes(1, 10), OpenMap::priceByTimes(1, 50), OpenMap::priceByTimes(1, 100)], [
            $sweep['info']['win'], $sweep['info']['get_number'], $sweep['info']['gettype'], $sweep['info']['five_sub'], $sweep['info']['ten_sub'], $sweep['info']['fifty_sub'], $sweep['info']['hundred_sub'],
        ]);
        $this->assertSame(40, $sweep['info']['five_sub']);
        $this->assertSame([Mission::ROWS[2]['gexp'] * 5, 0], [$sweep['info']['gexp'], $sweep['info']['exp']], '主公高 5 级以上不得主公经验');
        $this->assertSame($chips + 5, count(DB::table('equipment')->where('uid', $uid)->where('equipid', 1)->get()));
        $this->assertSame([194, $gold - 2], [self::memberValue($uid, 'act'), self::memberValue($uid, 'gold')]);
        $log = DB::table('mission_log')->where('uid', $uid)->where('missionid', 2)->first();
        $this->assertSame([5, 1], [(int)$log['times'], (int)$log['strom']]);

        // 再扫 5 次：全是强攻（第 2 ~ 6 次：4+6+8+10+12 = 40 元宝），记付费点 qianggong
        $before = (int)(DB::table('stat_pay')->where('name', 'qianggong')->value('gold') ?? 0);
        $sweep = $this->request($p['auth'], 'action', ['l' => 1, 's' => 1, 'id' => 2, 'times' => 5]);
        $this->assertSame([1, OpenMap::priceByTimes(6, 5)], [$sweep['status'], $sweep['info']['five_sub']]);
        $this->assertSame($gold - 42, self::memberValue($uid, 'gold'));
        $log = DB::table('mission_log')->where('uid', $uid)->where('missionid', 2)->first();
        $this->assertSame([5, 6], [(int)$log['times'], (int)$log['strom']]);
        $this->assertSame($before + 40, (int)DB::table('stat_pay')->where('name', 'qianggong')->value('gold'));
        $this->assertSame(['map', 'action'], array_values(DB::table('gold_log')->select('class', 'method')->where('uid', $uid)->orderBy('id', 'DESC')->first()));
        // 元宝不够强攻
        DB::table('member')->where('uid', $uid)->update(['gold' => 1]);
        $this->assertSame(['status' => -7], $this->request($p['auth'], 'action', ['l' => 1, 's' => 1, 'id' => 2]));
        // 已开启的前面场景不判断关卡号，查不到关卡为 -6
        self::progress($uid, 1, 2, 0);
        $this->assertSame(['status' => -6], $this->request($p['auth'], 'action', ['l' => 1, 's' => 1, 'id' => 99]));
    }

    public function testFirstKillOpensSceneReportsBroadcastAndRecruit(): void
    {
        $p = $this->player();
        $uid = $p['uid'];
        self::strong($uid);
        // 1-2-10 张梁：场景最后一关，招募率 1000‰ 必招张梁
        self::progress($uid, 1, 2, 9);
        // 先进聊天室（在线才收得到小喇叭）
        $this->horns($p);
        DB::table('mission_log')->where('missionlevel', 1)->where('missionstage', 2)->where('missionid', 10)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $res = $this->request($p['auth'], 'action', ['l' => 1, 's' => 2, 'id' => 10]);
        $this->assertSame(1, $res['status']);
        $this->assertGreaterThanOrEqual(1, $res['info']['win']);
        $this->assertSame([1, 1, 3, 0], [$res['info']['openstatus'], $res['info']['missionlevel'], $res['info']['missionstage'], $res['info']['get_level']]);
        $this->assertSame(['stage' => 3, 'mission' => 0], array_intersect_key(OpenMap::rows($uid)[0], ['stage' => 0, 'mission' => 0]));
        // 招募张梁（generalid 3），酒馆日志 own 1 type 2
        $this->assertSame([1, 3, '张梁'], [$res['info']['generalstatus'], $res['info']['generalinfo']['generalid'], $res['info']['generalinfo']['name']]);
        $this->assertNotNull(DB::table('general')->where('uid', $uid)->where('generalid', 3)->first());
        $pub = DB::table('pub_log')->where('uid', $uid)->where('generalid', 3)->first();
        $this->assertSame([1, 2], [(int)$pub['own'], (int)$pub['type']]);
        // 首杀记录、战报、分享
        $log = DB::table('mission_log')->where('uid', $uid)->where('missionstage', 2)->where('missionid', 10)->first();
        $this->assertSame((int)$log['id'], $res['info']['report']);
        $this->assertSame(['id' => (int)$log['id'], 'type' => 'map'], $res['share']);
        // 本服首杀最终守将：小喇叭
        $this->assertContains('恭喜玩家【map_' . $uid . '】本服首度击杀-张梁', $this->horns($p));

        // 攻略：全服最近 7 条首杀；单条战报回放
        $report = $this->request($p['auth'], 'report', ['l' => 1, 's' => 2, 'id' => 10]);
        $this->assertSame(1, $report['status']);
        $this->assertSame(['id' => (int)$log['id'], 'uid' => $uid, 'level' => (int)$log['level'], 'nickname' => 'map_' . $uid, 'tree' => 0], $report['list'][(int)$log['id']]);
        $this->assertSame(['status' => -2, 'list' => []], $this->request($p['auth'], 'report', ['l' => 1, 's' => 2]));
        $detail = $this->request($p['auth'], 'report_detail', ['id' => $log['id']]);
        $this->assertSame([1, $res['info']['win'], 1], [$detail['status'], $detail['info']['win'], $detail['info']['background']]);
        $this->assertSame(['name' => '张梁', 'level' => Mission::ROWS[20]['level']], $detail['battle']['right_role']);
        $this->assertSame($res['battle']['process'], $detail['battle']['process']);
        $this->assertSame(['status' => -1, 'battle' => [], 'info' => []], $this->request($p['auth'], 'report_detail', ['id' => 999999999]));

        // 分享战报到聊天室：扣 2 元宝
        $gold = self::memberValue($uid, 'gold');
        $this->assertSame(['status' => 1, 'all_gold' => $gold - 2], $this->request($p['auth'], 'send_to_chat', ['l' => 1, 's' => 2, 'id' => 10, 'report' => $log['id']]));
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'send_to_chat', ['l' => 1, 's' => 4, 'id' => 1, 'report' => $log['id']]), '场景未开启');

        // 第二个玩家再打：不再本服首杀广播；已拥有张梁时不再入库
        $q = $this->player();
        self::strong($q['uid']);
        self::progress($q['uid'], 1, 2, 9);
        $this->horns($q);
        $res = $this->request($q['auth'], 'action', ['l' => 1, 's' => 2, 'id' => 10]);
        $this->assertSame(1, $res['info']['openstatus']);
        $this->assertNotContains('恭喜玩家【map_' . $q['uid'] . '】本服首度击杀-张梁', $this->horns($q));
    }

    public function testFactionClearOpensNextFactionAndNewresult(): void
    {
        $p = $this->player();
        $uid = $p['uid'];
        self::strong($uid);
        // 1-4-10 张角：势力 1 最后一关
        self::progress($uid, 1, 4, 9);
        $res = $this->request($p['auth'], 'action', ['l' => 1, 's' => 4, 'id' => 10]);
        $this->assertGreaterThanOrEqual(1, $res['info']['win']);
        $this->assertSame([2, 2, 1, 1], [$res['info']['openstatus'], $res['info']['missionlevel'], $res['info']['missionstage'], $res['info']['get_level']]);
        $this->assertSame([[1, 4, 10, 0], [2, 1, 0, 0]], array_map(static fn (array $r): array => [$r['level'], $r['stage'], $r['mission'], $r['newresult']], OpenMap::rows($uid)));
        $this->assertSame(1, (int)DB::table('member_field')->where('uid', $uid)->value('newresult'));
        $this->assertSame(1, $this->request($p['auth'], 'get_scene_list', ['l' => 2])['status']);

        // 势力通关奖励：只能领 member_field.newresult 记的势力
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'get_newresult', ['id' => 2]));
        $res = $this->request($p['auth'], 'get_newresult', ['id' => 1]);
        $equip = Equip::ROWS[MapTemplate::ROWS[1]['reward']];
        $this->assertSame([1, 1, 1, $equip['id'], '狮心锁环甲'], [$res['status'], $res['gettype'], $res['rewardnum'], $res['item']['id'], $res['item']['name']]);
        $this->assertSame(0, (int)DB::table('member_field')->where('uid', $uid)->value('newresult'));
        $this->assertSame(1, OpenMap::rows($uid)[0]['newresult']);
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'get_newresult', ['id' => 1]), '已领取（newresult 已清 0）');
        $index = $this->request($p['auth'], 'index');
        $this->assertSame([1, 1, 0], [$index[1]['pass_status'], $index[2]['status'], $index[2]['pass_status']]);

        // 天赋卷轴 / 坐骑奖励（势力 6 / 17）
        foreach ([6 => 2, 17 => 4] as $level => $type) {
            self::progress($uid, $level, 1, 0);
            DB::table('member_field')->where('uid', $uid)->update(['newresult' => $level]);
            $res = $this->request($p['auth'], 'get_newresult', ['id' => $level]);
            $this->assertSame([1, $type, MapTemplate::ROWS[$level]['rewardnum']], [$res['status'], $res['gettype'], $res['rewardnum']]);
        }
        $scroll = TalentScroll::ROWS[MapTemplate::ROWS[6]['reward']];
        $this->assertSame(MapTemplate::ROWS[6]['rewardnum'], (int)Equipment::talentScroll($uid, $scroll['type'], $scroll['quality'])['number']);
        $this->assertSame(1, DB::table('own_mount')->where('uid', $uid)->where('mid', MapTemplate::ROWS[17]['reward'])->count());
        // 领奖锁
        Redis::set('get_map_reward_' . $uid, 1, 600);
        $this->assertSame(['status' => -3], $this->request($p['auth'], 'get_newresult', ['id' => 6]));
        Redis::del('get_map_reward_' . $uid);
    }

    public function testNewbieFirstKillUsesScriptedReport(): void
    {
        $p = $this->player();
        $uid = $p['uid'];
        self::progress($uid, 1, 1, 1);
        $res = $this->request($p['auth'], 'action', ['l' => 1, 's' => 1, 'id' => 2]);
        $this->assertSame(1, $res['status']);
        // 预制战报：张飞 / 关羽 / 刘备 / 廖化 对黄巾兵，share 为旧版写死的 8513
        $this->assertSame(['张飞', '关羽', '刘备', '廖化'], array_values(array_column($res['battle']['leftteam'], 'name')));
        $this->assertSame(['id' => 8513, 'type' => 'map'], $res['share']);
        $this->assertSame(['name' => '裴元绍', 'level' => 2, 'type' => 1], $res['battle']['right_role']);
        $this->assertSame(['name' => 'map_' . $uid, 'level' => self::memberValue($uid, 'level'), 'gender' => 0], $res['battle']['left_role']);
        $this->assertSame($res['info']['win'], $res['battle']['assess']);
        $this->assertSame([1, '裴元绍', 1], [$res['info']['first'], $res['info']['name'], $res['info']['add_type']]);
        $this->assertSame([], $res['battle']['rightbuff']);
        // 实际战斗的首杀记录照常写入（report 为真实记录 id）
        $log = DB::table('mission_log')->where('uid', $uid)->where('missionid', 2)->first();
        $this->assertSame($res['info']['win'] >= 1 ? (int)$log['id'] : 0, $res['info']['report']);

        // 1-1-1 没有预制战报：真实战斗结构
        $q = $this->player();
        $real = $this->request($q['auth'], 'action', ['l' => 1, 's' => 1, 'id' => 1]);
        $this->assertSame(['leftteam', 'rightteam', 'process', 'assess', 'leftresult', 'rightresult', 'leftbuff', 'rightbuff', 'left_role', 'right_role'], array_keys($real['battle']));
        $this->assertSame(['name' => '长枪兵', 'level' => 1, 'type' => 1], $real['battle']['right_role']);
        $this->assertSame(['win', 'exp', 'gexp', 'background', 'openstatus', 'gettype', 'getvalue', 'itemid', 'itemname', 'levelup', 'add_type', 'levelupexp', 'image', 'generalstatus', 'get_level', 'report', 'generalinfo'], array_slice(array_keys($real['info']), 0, 17));
        $this->assertSame([0, 0], [$real['info']['cd'], (int)DB::table('member_field')->where('uid', $q['uid'])->value('attackdateline')], '不加攻击 CD');
    }

    public function testNewrewardChestsAndRace(): void
    {
        $p = $this->player();
        $uid = $p['uid'];
        // 关卡宝箱：势力 1 第一个宝箱在第 10 关
        $rows = array_values(array_filter(MissionNewreward::ROWS, static fn (array $r): bool => $r['levelid'] === 1));
        $index = $this->request($p['auth'], 'get_newreward_index', ['levelid' => 1]);
        $this->assertSame(1, $index['status']);
        $this->assertSame(array_column($rows, 'id'), array_column($index['list'], 'id'));
        $this->assertSame([0, 0, 0, 0], array_column($index['list'], 'is_get'));
        $this->assertSame(['status' => 2, 'msg' => '未通过该关卡'], $this->request($p['auth'], 'get_newreward', ['id' => $rows[0]['id']]));

        self::progress($uid, 1, 2, 0);
        $index = $this->request($p['auth'], 'get_newreward_index', ['levelid' => 1]);
        $this->assertSame([1, 0, 0, 0], array_column($index['list'], 'is_get'));
        // 装备宝箱带展示信息
        $equipBox = array_values(array_filter($index['list'], static fn (array $r): bool => $r['re_type'] === 8))[0];
        $this->assertSame(Equip::ROWS[$equipBox['re_value']]['name'], $equipBox['info']['name']);

        $silver = self::memberValue($uid, 'silver');
        $claim = $this->request($p['auth'], 'get_newreward', ['id' => $rows[0]['id']]);
        $this->assertSame([1, 0, 2, $silver + $rows[0]['re_num']], [$claim['status'], $claim['popo'], $claim['reward']['is_get'], $claim['silver']]);
        $this->assertSame(['status' => 3, 'msg' => '已经领取该关卡宝箱奖励'], $this->request($p['auth'], 'get_newreward', ['id' => $rows[0]['id']]));
        $this->assertSame(['status' => -1, 'msg' => '传递参数错误'], $this->request($p['auth'], 'get_newreward', ['id' => 0]));
        $this->assertSame(['status' => -2, 'msg' => '势力关卡奖励信息错误'], $this->request($p['auth'], 'get_newreward', ['id' => 99999]));
        Redis::set('get_map_reward_' . $uid, 1, 600);
        $this->assertSame(['status' => -3, 'msg' => '操作太频繁了'], $this->request($p['auth'], 'get_newreward', ['id' => $rows[1]['id']]));
        Redis::del('get_map_reward_' . $uid);

        // 闯关竞速：注册 3 天内，过了第 10 关可领 20 元宝
        $list = $this->request($p['auth'], 'get_reward_list');
        $this->assertSame(1, $list['status']);
        $this->assertSame([10, 0, Mission::ROWS[10]['image']], [$list['list'][0]['missionid'], $list['list'][0]['open_status'], $list['list'][0]['image']]);
        $this->assertGreaterThan(259000, $list['race_dateline']);
        $gold = self::memberValue($uid, 'gold');
        $res = $this->request($p['auth'], 'get_mission_reward', ['id' => 10]);
        $this->assertSame(['popo' => 0, 'status' => 1, 'gold' => $gold + 20], $res);
        $this->assertSame(-3, $this->request($p['auth'], 'get_mission_reward', ['id' => 10])['status']);
        $this->assertSame(-2, $this->request($p['auth'], 'get_mission_reward', ['id' => 20])['status']);
        $this->assertSame(1, DB::table('map_action_log')->where('uid', $uid)->count());
        $list = $this->request($p['auth'], 'get_reward_list');
        $this->assertSame([1, 2], [$list['list'][0]['open_status'], $list['list'][count($list['list']) - 1]['open_status']], '按 open_status 排序');
        DB::table('member_field')->where('uid', $uid)->update(['start_time' => time() - 259201]);
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'get_reward_list'));
    }

    public function testClearCd(): void
    {
        $p = $this->player(['level' => 30]);
        $uid = $p['uid'];
        $this->assertSame(['gold' => 0, 'status' => -1], $this->request($p['auth'], 'clear_cd', ['type' => 0]));
        DB::table('member_field')->where('uid', $uid)->update(['attackstatus' => 1, 'attackdateline' => time() + 600]);
        $this->assertSame(['gold' => 4, 'status' => 0], $this->request($p['auth'], 'clear_cd', ['type' => 0]));
        $gold = self::memberValue($uid, 'gold');
        $this->assertSame(['gold' => 4, 'status' => 1, 'all_gold' => $gold - 4], $this->request($p['auth'], 'clear_cd', ['type' => 1]));
        $this->assertSame(0, (int)DB::table('member_field')->where('uid', $uid)->value('attackstatus'));
        $this->assertSame(['gold' => 0, 'status' => -1], $this->request($p['auth'], 'clear_cd', ['type' => 1]));
    }

    public function testProgressReadersUseOpenMap(): void
    {
        $p = $this->player();
        $uid = $p['uid'];
        // 没有记录（未注册的 uid）为 0；注册后 1-1-0 按第 1 关
        $this->assertSame(['status' => 0, 'id' => 0], OpenMap::missionMax(-1));
        $this->assertSame(['status' => 1, 'id' => 1], OpenMap::missionMax($uid));
        self::progress($uid, 2, 3, 4);
        $this->assertSame(self::missionId(2, 3, 4), OpenMap::missionMax($uid)['id']);
        // 关卡未开启时（记录超出关卡表）取最后一关
        self::progress($uid, 17, 9, 9);
        $last = max(array_keys(Mission::ROWS));
        $this->assertSame($last, OpenMap::missionMax($uid)['id']);
        // 多人副本军团开启按 get_mission_max
        $this->assertTrue(\Hanjiang\Handler\Activity\EliteHandler::cityOpen($uid, 1));
        // 主线任务「击败关卡」按 mission_log
        $this->assertSame(0, MainQuestTask::taskInfo($uid, 1, 1));
        DB::table('mission_log')->insert(['uid' => $uid, 'missionlevel' => 1, 'missionstage' => 1, 'missionid' => 1]);
        $this->assertSame(1, MainQuestTask::taskInfo($uid, 1, 1));
    }
}
