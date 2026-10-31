<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\LegacyStats;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\Handler\HandlerRegistry;
use Hanjiang\Http\Router;
use Hanjiang\MasterData\HeroTalentStar;
use Hanjiang\MasterData\Mission;
use Hanjiang\MasterData\SoulType;
use Hanjiang\MasterData\Talent;
use Hanjiang\Middleware\AuthMiddleware;
use Hanjiang\Middleware\CorsMiddleware;
use Hanjiang\Middleware\DailyCronMiddleware;
use Hanjiang\Middleware\GameProtocolMiddleware;
use Hanjiang\Middleware\ResponseEnvelopeMiddleware;
use Hanjiang\Middleware\ThrottleMiddleware;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\General;
use Hanjiang\Model\HeroTalent;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\OwnStar;
use Hanjiang\Model\TraderLog;
use Hanjiang\Tests\Support\ScriptedRandomEngine;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use PHPUnit\Framework\TestCase;
use Random\Randomizer;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * 养成第一批：将魂（c=soul）、主公星魄 / 命格（c=star）、主公天赋（c=talent），以及三者在将领界面与战斗中的加成
 *
 * 每个用例注册一个新游客（旧版初始武将已出征、在阵法里），按需改主公等级后走旧版接口流程。
 */
final class GrowthTest extends TestCase
{
    private static Router $router;

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
        $router->use(new GameProtocolMiddleware(HandlerRegistry::all()));
        self::$router = $router;
    }

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function request(array $auth, string $controller, string $method, array $params = []): array
    {
        $ctx = Context::mock(array_merge(['c' => $controller, 'm' => $method], $auth, $params));
        $res = self::$router->handleContext($ctx);
        if ($res instanceof WorkermanResponse) {
            $json = json_decode((string)$res->rawBody(), true);
            $this->assertIsArray($json, "{$controller}.{$method}: " . $res->rawBody());
            $res = $json;
        }
        $this->assertIsArray($res);
        unset($res['message']);
        if (($res['msg'] ?? null) === 'ok') {
            // 信封补的默认提示
            unset($res['msg']);
        }
        return $res;
    }

    /**
     * 新游客：返回 uid、鉴权参数与出征的初始武将 id
     *
     * @param array<string, int> $member
     * @return array{uid: int, auth: array<string, mixed>, gid: int}
     */
    private function player(array $member = []): array
    {
        $reg = $this->request(['token' => '', 'uid' => ''], 'register', 'guest');
        $uid = (int)$reg['uid'];
        $login = $this->request(['token' => '', 'uid' => $uid], 'login', 'guest', ['guestkey' => $reg['guestkey']]);
        if ($member !== []) {
            DB::table('member')->where('uid', $uid)->update($member);
        }
        $gid = (int)DB::table('general')->where('uid', $uid)->where('goout', 1)->whereNull('deleted_at')->orderBy('id')->value('id');
        return ['uid' => $uid, 'auth' => ['token' => (string)$login['token'], 'uid' => $uid], 'gid' => $gid];
    }

    private function memberValue(int $uid, string $column): int
    {
        return (int)DB::table('member')->where('uid', $uid)->value($column);
    }

    private static function soulTemplate(int $type, int $quality): int
    {
        return (int)OwnSoul::baseInfoByType($type, $quality)['id'];
    }

    /**
     * 包裹里加一个将魂，返回 own_soul.id
     */
    private static function giveSoul(int $uid, int $type, int $quality, int $level = 0): int
    {
        return (int)OwnSoul::addHeroSoul($uid, self::soulTemplate($type, $quality), $level)['id'];
    }

    /**
     * @return array<string, int>
     */
    private static function sites(int $gid): array
    {
        return OwnSoul::generalSoul($gid);
    }

    // ---------------------------------------------------------------------------------------------
    // 将魂
    // ---------------------------------------------------------------------------------------------

    public function testSoulIndexGateSlotsAndPackRepair(): void
    {
        $p = $this->player(['level' => 50]);
        foreach (['index', 'get_soul_info', 'get_general_info', 'levelup', 'convert', 'equip', 'unequip', 'get_unlock_price', 'unlock', 'get_pack_info'] as $m) {
            $this->assertSame(-1, $this->request($p['auth'], 'soul', $m)['status'], $m);
        }
        $this->assertSame(['status' => -1, 'msg' => '等级不足'], $this->request($p['auth'], 'soul', 'merge_action', ['sid1' => 1, 'sid2' => 2]));

        // 51 级开放；包裹占用按包裹里的将魂个数修正（旧版 index「修复背包占用格数」）
        DB::table('member')->where('uid', $p['uid'])->update(['level' => 51, 'souluse' => 3]);
        $res = $this->request($p['auth'], 'soul', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertSame(1, $res['general_totals']);
        $first = $res['general'][1];
        $this->assertSame(['id', 'quality', 'name', 'level', 'image', 'type', 'star', 'turn', 'site1', 'site2', 'site3', 'site4', 'site5', 'site6', 'site7', 'site8'], array_keys($first));
        $this->assertSame($p['gid'], $first['id']);
        // 未转生：8 个槽位都未解锁，解锁所需转生次数 1 1 1 2 4 6 8 10
        $this->assertSame([1, 1, 1, 2, 4, 6, 8, 10], array_map(static fn (int $i): int => $first['site' . $i]['unlock_level'], range(1, 8)));
        $this->assertSame(['islock' => 1, 'unlock_level' => 1], $first['site1']);
        $this->assertSame(['list' => [], 'page' => 1, 'pages' => 1, 'last' => 1, 'base_soul' => 100, 'use' => 0, 'all' => 4], $res['pack']);
        $this->assertSame([], $res['soul']);
        $this->assertSame(0, $this->memberValue($p['uid'], 'souluse'));
        // 首次打开为将领建空槽（旧版 add_general_soul）
        $this->assertSame(['gid' => $p['gid'], 'uid' => $p['uid'], 'site1' => 0, 'site2' => 0, 'site3' => 0, 'site4' => 0, 'site5' => 0, 'site6' => 0, 'site7' => 0, 'site8' => 0], self::sites($p['gid']));

        // 没有出征将领：-2
        DB::table('general')->where('uid', $p['uid'])->update(['goout' => 0]);
        $this->assertSame(-2, $this->request($p['auth'], 'soul', 'index')['status']);
    }

    public function testSoulLevelupConvertAndInfo(): void
    {
        $p = $this->player(['level' => 51]);
        $uid = $p['uid'];
        // 武力将魂（type 2，不带 %）紫色 0 级
        $sid = self::giveSoul($uid, 2, 5);
        $this->assertSame(1, $this->memberValue($uid, 'souluse'));

        // index：将领没有将魂时默认选中包裹第一个（from 9）
        $res = $this->request($p['auth'], 'soul', 'index');
        $lv = SoulType::ROWS[2];
        $this->assertSame([
            'id' => $sid, 'gid' => 0, 'name' => '无敌战魂', 'image' => 25, 'type' => 2, 'quality' => 5, 'level' => 0, 'power' => $lv['lv0'], 'sort' => 1,
            'from' => 9, 'merge' => 0, 'ismax' => 0, 'levelup' => ['addition' => $lv['lv1'], 'price' => 10, 'level' => 1],
        ], $res['soul']);
        $this->assertSame(1, $res['pack']['use']);

        // 升级：花 10 原魂
        $up = $this->request($p['auth'], 'soul', 'levelup', ['sid' => $sid]);
        $this->assertSame([
            'status' => 1,
            'info' => [
                'now' => ['addition' => $lv['lv1'], 'price' => 10, 'level' => 1],
                'next' => ['merge' => 0, 'ismax' => 0, 'levelup' => ['addition' => $lv['lv2'], 'price' => 20, 'level' => 2]],
                'base_soul' => 90,
            ],
        ], $up);
        $row = DB::table('own_soul')->where('id', $sid)->first();
        $this->assertSame([1, $lv['lv1']], [(int)$row['level'], (int)$row['power']]);
        $log = DB::table('usesoul_log')->where('uid', $uid)->first();
        $this->assertSame([2, 10, 90, 'soul', 'levelup'], [(int)$log['type'], (int)$log['reputation'], (int)$log['balance'], $log['class'], $log['method']]);

        // 原魂不足：3
        DB::table('member')->where('uid', $uid)->update(['soul' => 19]);
        $this->assertSame(['status' => 3], $this->request($p['auth'], 'soul', 'levelup', ['sid' => $sid]));
        // 紫色满级 15：2；get_soul_info 出现合成按钮
        DB::table('own_soul')->where('id', $sid)->update(['level' => 15, 'power' => $lv['lv15']]);
        $this->assertSame(['status' => 2], $this->request($p['auth'], 'soul', 'levelup', ['sid' => $sid]));
        $info = $this->request($p['auth'], 'soul', 'get_soul_info', ['sid' => $sid]);
        $this->assertSame(['id' => $sid, 'uid' => $uid, 'gid' => 0, 'name' => '无敌战魂', 'image' => 25, 'type' => 2, 'quality' => 5, 'level' => 15, 'power' => $lv['lv15'], 'merge' => 1, 'ismax' => 1, 'levelup' => []], $info['info']);

        // 别人的 / 不存在的将魂：-2
        $other = $this->player(['level' => 51]);
        $this->assertSame(['status' => -2], $this->request($other['auth'], 'soul', 'get_soul_info', ['sid' => $sid]));
        $this->assertSame(['status' => -2], $this->request($other['auth'], 'soul', 'convert', ['sid' => $sid, 'confirm' => 1]));

        // 转换：品质 + 1 ~ 15 级升级花费 = 5 + 10 × (2^15 - 1)
        $num = 5 + 10 * (2 ** 15 - 1);
        $this->assertSame(['status' => 1, 'num' => $num, 'base_soul' => 19], $this->request($p['auth'], 'soul', 'convert', ['sid' => $sid]));
        $this->assertNotNull(DB::table('own_soul')->where('id', $sid)->whereNull('deleted_at')->first(), '不确认只返回可得原魂');
        $conv = $this->request($p['auth'], 'soul', 'convert', ['sid' => $sid, 'confirm' => 1]);
        $this->assertSame(1, $conv['status']);
        $this->assertSame($num, $conv['num']);
        $this->assertSame(19 + $num, $conv['base_soul']);
        $this->assertSame(['list' => [], 'page' => 1, 'pages' => 1, 'last' => 1, 'base_soul' => 19 + $num, 'use' => 0, 'all' => 4], $conv['pack']);
        $this->assertNull(DB::table('own_soul')->where('id', $sid)->whereNull('deleted_at')->first());
        $this->assertSame(0, $this->memberValue($uid, 'souluse'));
        $log = DB::table('usesoul_log')->where('uid', $uid)->orderBy('id', 'DESC')->first();
        $this->assertSame([1, $num, 19 + $num, 'convert'], [(int)$log['type'], (int)$log['reputation'], (int)$log['balance'], $log['method']]);
    }

    public function testSoulEquipUnequipAndGeneralInfo(): void
    {
        $p = $this->player(['level' => 51]);
        [$uid, $gid] = [$p['uid'], $p['gid']];
        $a = self::giveSoul($uid, 2, 1);
        $b = self::giveSoul($uid, 2, 2);
        $c = self::giveSoul($uid, 3, 1);
        $this->assertSame(3, $this->memberValue($uid, 'souluse'));

        // 将领还没有将魂槽（没打开过界面）：旧版模型 -1 → 控制器 -4
        DB::table('general')->where('id', $gid)->update(['turn' => 1]);
        $this->assertSame(['status' => -4], $this->request($p['auth'], 'soul', 'equip', ['gid' => $gid, 'sid' => $a, 'site' => 1]));
        $this->request($p['auth'], 'soul', 'get_general_info', ['gid' => $gid]);

        // 转生 1 次解锁 3 个槽位
        $this->assertSame(['status' => -3], $this->request($p['auth'], 'soul', 'equip', ['gid' => $gid, 'sid' => $a, 'site' => 4]));
        $this->assertSame(['status' => -4], $this->request($p['auth'], 'soul', 'equip', ['gid' => $gid, 'sid' => $a, 'site' => 0]));
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'soul', 'equip', ['gid' => 999999999, 'sid' => $a, 'site' => 1]));

        // 新装配（type 4）：包裹占用 - 1；返回的 souluse 是操作前的值（同旧版）
        $res = $this->request($p['auth'], 'soul', 'equip', ['gid' => $gid, 'sid' => $a, 'site' => 1]);
        $this->assertSame([1, 3, 2], [$res['status'], $res['souluse'], $res['pack']['use']]);
        $this->assertSame(2, $this->memberValue($uid, 'souluse'));
        $this->assertSame($gid, (int)DB::table('own_soul')->where('id', $a)->value('gid'));
        // 位置未改变：旧版模型返回 4，控制器只映射 -2 / 2 / 3，落到 -4
        $this->assertSame(['status' => -4], $this->request($p['auth'], 'soul', 'equip', ['gid' => $gid, 'sid' => $a, 'site' => 1]));
        // 同种类型只能装备一件
        $this->assertSame(['status' => 3], $this->request($p['auth'], 'soul', 'equip', ['gid' => $gid, 'sid' => $b, 'site' => 2]));
        $this->assertSame(1, $this->request($p['auth'], 'soul', 'equip', ['gid' => $gid, 'sid' => $c, 'site' => 2])['status']);
        $this->assertSame(1, $this->memberValue($uid, 'souluse'));
        // 互换槽位（type 1）/ 换到空槽位（type 2）不改包裹占用
        $this->request($p['auth'], 'soul', 'equip', ['gid' => $gid, 'sid' => $a, 'site' => 2]);
        $this->assertSame([$c, $a, 0], array_values(array_slice(self::sites($gid), 2, 3)));
        $this->request($p['auth'], 'soul', 'equip', ['gid' => $gid, 'sid' => $a, 'site' => 3]);
        $this->assertSame([$c, 0, $a], array_values(array_slice(self::sites($gid), 2, 3)));
        // 覆盖已有将魂（type 3）：被换下的回包裹，占用不变（同旧版）
        $this->request($p['auth'], 'soul', 'equip', ['gid' => $gid, 'sid' => $b, 'site' => 3]);
        $this->assertSame([$c, 0, $b], array_values(array_slice(self::sites($gid), 2, 3)));
        $this->assertSame([0, $gid], [(int)DB::table('own_soul')->where('id', $a)->value('gid'), (int)DB::table('own_soul')->where('id', $b)->value('gid')]);
        $this->assertSame(1, $this->memberValue($uid, 'souluse'));

        // 其他将领已装备：2
        $gid2 = (int)General::createFromTemplate($uid, 2);
        DB::table('general')->where('id', $gid2)->update(['turn' => 1]);
        $this->request($p['auth'], 'soul', 'get_general_info', ['gid' => $gid2]);
        $this->assertSame(['status' => 2], $this->request($p['auth'], 'soul', 'equip', ['gid' => $gid2, 'sid' => $b, 'site' => 1]));

        // get_general_info：第一个有将魂的槽位为默认选中（from）；转生降回 0 后已装将魂的槽位 islock 2
        $info = $this->request($p['auth'], 'soul', 'get_general_info', ['gid' => $gid]);
        $this->assertSame(['id', 'name', 'quality', 'level', 'image', 'type', 'star', 'turn'], array_slice(array_keys($info['general']), 0, 8));
        $this->assertSame(['islock' => 0, 'info' => []], $info['general']['site2']);
        $this->assertSame($c, $info['general']['site1']['info']['id']);
        $this->assertSame(['islock' => 1, 'unlock_level' => 2], $info['general']['site4']);
        $this->assertSame([$c, 1, 0, 0], [$info['soul']['id'], $info['soul']['from'], $info['soul']['merge'], $info['soul']['ismax']]);
        DB::table('general')->where('id', $gid)->update(['turn' => 0]);
        $info = $this->request($p['auth'], 'soul', 'get_general_info', ['gid' => $gid]);
        $this->assertSame([2, 1, $b], [$info['general']['site3']['islock'], $info['general']['site3']['unlock_level'], $info['general']['site3']['info']['id']]);
        DB::table('general')->where('id', $gid)->update(['turn' => 1]);
        // 将领没有将魂时取传入的 sid；不是自己的 -4
        $this->assertSame($a, $this->request($p['auth'], 'soul', 'get_general_info', ['gid' => $gid2, 'sid' => $a])['soul']['id']);
        $other = $this->player(['level' => 51]);
        $this->assertSame(-4, $this->request($p['auth'], 'soul', 'get_general_info', ['gid' => $gid2, 'sid' => self::giveSoul($other['uid'], 1, 1)])['status']);

        // 卸下：包裹占用 + 1，返回操作前的占用
        $res = $this->request($p['auth'], 'soul', 'unequip', ['gid' => $gid, 'sid' => $b]);
        $this->assertSame([1, 1, 2], [$res['status'], $res['souluse'], $res['pack']['use']]);
        $this->assertSame(2, $this->memberValue($uid, 'souluse'));
        $this->assertSame(0, self::sites($gid)['site3']);
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'soul', 'unequip', ['gid' => $gid, 'sid' => $b]));
        // 包裹已满：2
        DB::table('member')->where('uid', $uid)->update(['souluse' => 4]);
        $this->assertSame(['status' => 2], $this->request($p['auth'], 'soul', 'unequip', ['gid' => $gid, 'sid' => $c]));
    }

    public function testSoulPackUnlockAndPaging(): void
    {
        $p = $this->player(['level' => 51, 'gold' => 100]);
        // 包裹 4 格：解锁到第 6 格 = 第 1、2 格价格 5 + 10
        $this->assertSame(['status' => 1, 'price' => 15, 'number' => 2], $this->request($p['auth'], 'soul', 'get_unlock_price', ['pos' => 6]));
        $this->assertSame(['status' => -2, 'price' => 0, 'number' => 0], $this->request($p['auth'], 'soul', 'get_unlock_price', ['pos' => 4]));
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'soul', 'unlock', ['pos' => 4]));
        $this->assertSame(['status' => 1, 'gold' => 85, 'pack' => 6, 'pages' => 1], $this->request($p['auth'], 'soul', 'unlock', ['pos' => 6]));
        // 第 3 ~ 15 格：15 + 20 + 25 + 30 + 35 + 40 + 45 + 50 × 6 = 510 > 85
        $this->assertSame(510, $this->request($p['auth'], 'soul', 'get_unlock_price', ['pos' => 19])['price']);
        $this->assertSame(['status' => 2], $this->request($p['auth'], 'soul', 'unlock', ['pos' => 19]));

        // 未装备列表：品质、等级、id 倒序；页码超过总页数取最后一页；type > 0 只列紫色
        $ids = [];
        foreach ([[1, 0], [5, 3], [3, 2], [5, 1]] as [$quality, $level]) {
            $ids[] = self::giveSoul($p['uid'], 1, $quality, $level);
        }
        $page = $this->request($p['auth'], 'soul', 'get_pack_info', ['page' => 9, 'perpage' => 3])['info'];
        $this->assertSame([2, 2, 1, 4, 6], [$page['page'], $page['pages'], $page['last'], $page['use'], $page['all']]);
        $this->assertSame([$ids[0]], array_column($page['list'], 'id'));
        $this->assertSame([$ids[1], $ids[3], $ids[2]], array_column($this->request($p['auth'], 'soul', 'get_pack_info', ['page' => 1, 'perpage' => 3])['info']['list'], 'id'));
        $purple = $this->request($p['auth'], 'soul', 'get_pack_info', ['type' => 1])['info'];
        $this->assertSame([[$ids[1], $ids[3]], 2], [array_column($purple['list'], 'id'), $purple['use']]);
        $this->assertSame('1%', OwnSoul::byId(self::giveSoul($p['uid'], 7, 1))['power'], '类型 > 3 的属性带 %');
    }

    public function testSoulMerge(): void
    {
        $p = $this->player(['level' => 51]);
        $uid = $p['uid'];
        $s1 = self::giveSoul($uid, 4, 5, 15);
        $s2 = self::giveSoul($uid, 4, 5, 15);
        $notMax = self::giveSoul($uid, 4, 5, 14);
        $blue = self::giveSoul($uid, 4, 4, 13);
        $red = self::giveSoul($uid, 4, 6, 20);

        // 合成列表只有满级紫色，按 id 升序
        $this->assertSame([$s1, $s2], array_column($this->request($p['auth'], 'soul', 'merge_index')['pack']['list'], 'id'));

        $fail = fn (int $a, int $b): array => $this->request($p['auth'], 'soul', 'merge_action', ['sid1' => $a, 'sid2' => $b]);
        $this->assertSame(['status' => -2, 'msg' => '参数传递错误'], $fail(0, 0));
        $this->assertSame(['status' => -10, 'msg' => '合成的两个将魂ID不能相同'], $fail($s1, $s1));
        $this->assertSame(['status' => -4, 'msg' => '获取将魂信息失败'], $fail($s1, 999999999));
        $this->assertSame(['status' => -5, 'msg' => '将魂品级必须为紫色'], $fail($s1, $blue));
        $this->assertSame(['status' => -9, 'msg' => '将魂品级达到紫色品级以上，不能合成'], $fail($s1, $red));
        $this->assertSame(['status' => -6, 'msg' => '将魂等级必须满级'], $fail($s1, $notMax));

        // 同类型合成：类型不变，16 级红色
        $souluse = $this->memberValue($uid, 'souluse');
        $res = $fail($s1, $s2);
        $this->assertSame(1, $res['status']);
        $tpl = self::soulTemplate(4, 6);
        $newId = $res['new_soul']['id'];
        $this->assertSame(['name' => '涅槃战魂', 'type' => 4, 'quality' => 6, 'image' => 46, 'level' => 16, 'power' => SoulType::ROWS[4]['lv16'] . '%', 'id' => $newId, 'num' => 1], $res['new_soul']);
        $this->assertSame([], $res['pack']['list']);
        $this->assertSame(2, DB::table('own_soul')->whereIn('id', [$s1, $s2])->whereNotNull('deleted_at')->count());
        // 合成的两个将魂不减包裹占用，新将魂 + 1（同旧版，index 会修正）
        $this->assertSame($souluse + 1, $this->memberValue($uid, 'souluse'));
        $log = DB::table('own_soul_mergelog')->where('uid', $uid)->first();
        $this->assertSame([$s1, $s2, $tpl], [(int)$log['sid1'], (int)$log['sid2'], (int)$log['new_sid']]);

        // 不同类型：类型在 1 ~ 8 中随机
        $x = self::giveSoul($uid, 1, 5, 15);
        $y = self::giveSoul($uid, 10, 5, 15);
        $res = $fail($x, $y);
        $this->assertSame([1, 6, 16], [$res['status'], $res['new_soul']['quality'], $res['new_soul']['level']]);
        $this->assertContains($res['new_soul']['type'], range(1, 8));
    }

    // ---------------------------------------------------------------------------------------------
    // 主公星魄 / 命格
    // ---------------------------------------------------------------------------------------------

    public function testStarIndexAndLevelUp(): void
    {
        $p = $this->player(['level' => 79]);
        $uid = $p['uid'];
        foreach (['index', 'level_up', 'refining_index', 'refining'] as $m) {
            $this->assertSame(['status' => -1, 'msg' => '功能未开启！'], $this->request($p['auth'], 'star', $m, ['starid' => 1, 'id' => 1, 'refine_type' => 1]), $m);
        }
        DB::table('member')->where('uid', $uid)->update(['level' => 80]);
        // 没有星魄记录时提炼首页 -2（旧版只有 index 建档）
        $this->assertSame(['status' => -2, 'msg' => '基础数据错误！'], $this->request($p['auth'], 'star', 'refining_index'));

        $res = $this->request($p['auth'], 'star', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertSame([0, 0, 0], [$res['all_star1'], $res['all_star2'], $res['all_star3']]);
        $this->assertSame(array_fill_keys(OwnStar::ADDITION_FIELDS, 0), array_replace($res['addition'], ['uid' => 0]));
        $this->assertSame([1, 2, 3], array_keys($res['list']));
        $this->assertSame(range(1, 10), array_keys($res['list'][1]));
        $this->assertSame([
            'id' => 1, 'name' => '踏雪', 'desc' => '物理攻击+0(下一级+200)', 'level' => 0, 'max_level' => 5, 'line' => 1, 'prepos_level' => 80, 'open_status' => 1,
            'cost' => ['type1' => 1, 'value1' => 200, 'value2' => 0, 'value3' => 0],
        ], $res['list'][1][1]);
        $this->assertSame(0, $res['list'][1][4]['open_status'], '前置命格未满级');
        $this->assertSame('武力，智力，体力+20', $res['list'][1][10]['desc']);
        // 默认选中 lastlevel 1
        $this->assertSame($res['list'][1][1], $res['def_cost']);
        $this->assertSame(['uid' => $uid, 'star1' => 0, 'star2' => 0, 'star3' => 0, 'lastsite' => 1, 'lastlevel' => 1], array_diff_key(OwnStar::get($uid), ['dateline' => 0]));
        $this->assertSame([1 => ['uid' => $uid, 'starid' => 1, 'level' => 0]], OwnStar::tree($uid));

        // 星魄不足
        $this->assertSame(['status' => -6, 'msg' => '幽蓝星魄不足'], $this->request($p['auth'], 'star', 'level_up', ['starid' => 1]));
        DB::table('own_star')->where('uid', $uid)->update(['star1' => 1000]);
        $up = $this->request($p['auth'], 'star', 'level_up', ['starid' => 1]);
        $this->assertSame([
            'status' => 1,
            'next_cost' => [
                'id' => 1, 'name' => '踏雪', 'desc' => '物理攻击+200(下一级+400)', 'level' => 2, 'max_level' => 5, 'line' => 1, 'prepos_level' => 82, 'open_status' => 1,
                'cost' => ['type1' => 1, 'value1' => 400, 'type2' => 0, 'value2' => 0, 'type3' => 0, 'value3' => 0],
            ],
            'open_star_list' => [],
            'star1' => 800, 'star2' => 0, 'star3' => 0,
        ], $up);
        $this->assertSame(1, OwnStar::tree($uid, [1])[0]['level']);
        $this->assertSame(200, OwnStar::addition($uid)['attack']);
        $log = DB::table('star_log')->where('uid', $uid)->first();
        $this->assertSame([1, 2, 200, 800, 'star', 'level_up', 1, 1], [(int)$log['startype'], (int)$log['type'], (int)$log['star'], (int)$log['balance'], $log['class'], $log['method'], (int)$log['treeid'], (int)$log['level']]);

        // 2 级要求主公 82 级
        $this->assertSame(['status' => -5, 'msg' => '主公等级无法开启此命格'], $this->request($p['auth'], 'star', 'level_up', ['starid' => 1]));
        // 前置命格（1 号 5 级）未满足
        $this->assertSame(['status' => -4, 'msg' => '命格未开启'], $this->request($p['auth'], 'star', 'level_up', ['starid' => 4]));
        // 配置里没有的命格：-3，不留命格行
        $this->assertSame(['status' => -3, 'msg' => '获取命格信息失败！'], $this->request($p['auth'], 'star', 'level_up', ['starid' => 999]));
        $this->assertSame([], OwnStar::tree($uid, [999]));
        $this->assertSame(['status' => 0], $this->request($p['auth'], 'star', 'level_up', ['starid' => 0]));
        // 满级
        DB::table('star_tree')->where('uid', $uid)->where('starid', 1)->update(['level' => 5]);
        $this->assertSame(['status' => -4, 'msg' => '命格已经满级！'], $this->request($p['auth'], 'star', 'level_up', ['starid' => 1]));
        // 上一次请求未结束（方法锁）：-1
        Redis::instance()->set('sr_lk_level_up' . $uid, (string)$uid, ['ex' => 10]);
        $this->assertSame(['status' => -1, 'msg' => '功能未开启！'], $this->request($p['auth'], 'star', 'level_up', ['starid' => 2]));
        Redis::del('sr_lk_level_up' . $uid);

        // 首页：满级命格显示 5 级描述、没有消耗；最后升级的命格为默认选中
        $res = $this->request($p['auth'], 'star', 'index');
        $this->assertSame(['物理攻击+1000', 5, []], [$res['list'][1][1]['desc'], $res['list'][1][1]['level'], $res['list'][1][1]['cost']]);
        $this->assertSame($res['list'][1][1], $res['def_cost']);
        $this->assertSame(1, $res['list'][1][4]['open_status'], '1 号命格满级后 4 号开启');
    }

    public function testStarRefining(): void
    {
        $p = $this->player(['level' => 80, 'gold' => 500]);
        $uid = $p['uid'];
        $this->request($p['auth'], 'star', 'index');

        $res = $this->request($p['auth'], 'star', 'refining_index');
        $this->assertSame(1, $res['status']);
        $this->assertSame([1, 2, 3], array_keys($res['refine']));
        $this->assertSame(['id' => 1, 'desc' => '普普通通的炉子，可以提炼出幽蓝星魄', 'name' => '青铜炉', 'refine_type1', 'refine_type2', 'refine_type3'], array_merge(array_slice($res['refine'][1], 0, 3), array_keys(array_slice($res['refine'][1], 3))));
        $this->assertSame(['type1' => 1, 'value1' => 50000, 'type2' => 2, 'value2' => 50, 'type3' => 0, 'value3' => 0, 'gettype' => 1, 'getvalue' => 100], $res['refine'][1]['refine_type1']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 0, 1], [$res['jade1'], $res['jade2'], $res['jade3'], $res['jade'], $res['star1'], $res['star2'], $res['star3'], $res['lastsite']]);

        $this->assertSame(['status' => -2, 'msg' => '非法操作！'], $this->request($p['auth'], 'star', 'refining', ['id' => 1]));
        $this->assertSame(['status' => -3, 'msg' => '基础数据错误！'], $this->request($p['auth'], 'star', 'refining', ['id' => 9, 'refine_type' => 1]));
        $this->assertSame(['status' => -4, 'msg' => '玉石不足！'], $this->request($p['auth'], 'star', 'refining', ['id' => 1, 'refine_type' => 1]));
        MemberJade::add($uid, 60000);
        $this->assertSame(['status' => -5, 'msg' => '蓝宝石不足！'], $this->request($p['auth'], 'star', 'refining', ['id' => 1, 'refine_type' => 1]));
        TraderLog::addJade($uid, 1, 60);

        $this->assertSame(
            ['status' => 1, 'star' => 100, 'gettype' => 1, 'getstar' => 100, 'jade1' => 10, 'jade2' => 0, 'jade3' => 0, 'jade' => 10000, 'gold' => 500],
            $this->request($p['auth'], 'star', 'refining', ['id' => 1, 'refine_type' => 1])
        );
        $this->assertSame([100, 1], [OwnStar::get($uid)['star1'], OwnStar::get($uid)['lastsite']]);
        $log = DB::table('star_log')->where('uid', $uid)->first();
        $this->assertSame([1, 1, 100, 100, 'refining'], [(int)$log['startype'], (int)$log['type'], (int)$log['star'], (int)$log['balance'], $log['method']]);

        // 盘龙炉高级提炼：玉石 400000、黄宝石 200、元宝 200 → 铭黄星魄 600
        MemberJade::add($uid, 400000);
        TraderLog::addJade($uid, 2, 200);
        $res = $this->request($p['auth'], 'star', 'refining', ['id' => 2, 'refine_type' => 2]);
        $this->assertSame([1, 600, 2, 300, 10000, 0], [$res['status'], $res['star'], $res['gettype'], $res['gold'], $res['jade'], $res['jade2']]);
        $this->assertSame(2, OwnStar::get($uid)['lastsite']);
        $this->assertSame(['status' => -8, 'msg' => '元宝不足！'], (function () use ($p, $uid): array {
            MemberJade::add($uid, 400000);
            TraderLog::addJade($uid, 2, 200);
            DB::table('member')->where('uid', $uid)->update(['gold' => 199]);
            return $this->request($p['auth'], 'star', 'refining', ['id' => 2, 'refine_type' => 2]);
        })());
    }

    // ---------------------------------------------------------------------------------------------
    // 主公天赋
    // ---------------------------------------------------------------------------------------------

    public function testTalentIndexLevelupAndInfo(): void
    {
        $p = $this->player(['level' => 90, 'vip' => 3, 'gold' => 1000]);
        $uid = $p['uid'];
        foreach (['index', 'levelup', 'get_info'] as $m) {
            $this->assertSame(-1, $this->request($p['auth'], 'talent', $m, ['type' => 1])['status'], $m);
        }
        DB::table('member')->where('uid', $uid)->update(['level' => 91]);
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'talent', 'get_info', ['type' => 1]), '没打开过天赋界面时还没有记录');

        $res = $this->request($p['auth'], 'talent', 'index');
        $this->assertSame([
            'status' => 1,
            'mode' => 2,
            'starup' => ['star' => 1, 'power' => 120, 'jade' => HeroTalentStar::ROWS[1]['jade'], 'part' => 5, 'quality' => 1, 'success' => 15, 'type' => 1],
            'list' => [
                ['uid' => $uid, 'type' => 1, 'quality' => 1, 'star' => 0, 'power' => 100],
                ['uid' => $uid, 'type' => 2, 'quality' => 1, 'star' => 0, 'power' => 100],
                ['uid' => $uid, 'type' => 3, 'quality' => 1, 'star' => 0, 'power' => 100],
                ['uid' => $uid, 'type' => 4, 'quality' => 1, 'star' => 0, 'power' => 100],
                ['uid' => $uid, 'type' => 5, 'quality' => 1, 'star' => 0, 'power' => 300],
            ],
            'ismax' => 0,
            'scroll' => ['type' => 1, 'quality' => 1, 'num' => 0],
            'jade' => 0,
        ], $res);

        $this->assertSame(['status' => 3, 'info' => [], 'failed' => 0], $this->request($p['auth'], 'talent', 'levelup', ['type' => 1]));
        MemberJade::add($uid, 20000);
        $this->assertSame(['status' => 4, 'info' => [], 'failed' => 0], $this->request($p['auth'], 'talent', 'levelup', ['type' => 1]));
        Equipment::addTalentScroll($uid, 1, 0, 12);

        // 元宝必成（VIP 9 以下不暴击）：扣元宝 15、玉石、卷轴 5
        $up = $this->request($p['auth'], 'talent', 'levelup', ['type' => 1, 'success' => 1]);
        $this->assertSame([
            'status' => 1,
            'info' => ['star' => 1, 'quality' => 1, 'power' => 120, 'type' => 1],
            'failed' => 0,
            'hit' => 0,
            'mode' => 2,
            'ismax' => 0,
            'starup' => ['star' => 2, 'power' => 140, 'jade' => HeroTalentStar::ROWS[2]['jade'], 'part' => 5, 'quality' => 1, 'success' => 15, 'type' => 1],
            'scroll' => ['type' => 1, 'quality' => 1, 'num' => 7],
            'jade' => 20000 - HeroTalentStar::ROWS[1]['jade'],
            'gold' => 985,
        ], $up);
        $this->assertSame(['uid' => $uid, 'type' => 1, 'quality' => 1, 'star' => 1, 'power' => 120], HeroTalent::byType($uid, 1));

        $info = $this->request($p['auth'], 'talent', 'get_info', ['type' => 1]);
        $this->assertSame([1, 2, 0, ['type' => 1, 'quality' => 1, 'num' => 7]], [$info['status'], $info['mode'], $info['ismax'], $info['scroll']]);
        $this->assertSame(['uid' => $uid, 'type' => 1, 'quality' => 1, 'star' => 1, 'power' => 120], $info['info']);

        // 星级 20 的下一星为顿悟：VIP 6 以下不能元宝必成；starup 不带 success
        DB::table('own_hero_talent')->where('uid', $uid)->where('type', 1)->update(['star' => 20, 'power' => 500]);
        $info = $this->request($p['auth'], 'talent', 'get_info', ['type' => 1]);
        $this->assertSame([1, ['star' => 21, 'power' => 100 + 21 * 30, 'jade' => 635250, 'part' => 50, 'quality' => 2, 'protect' => 40, 'type' => 1]], [$info['mode'], $info['starup']]);
        MemberJade::add($uid, 700000);
        Equipment::addTalentScroll($uid, 6, 0, 60);
        $this->assertSame(['status' => 5, 'info' => [], 'failed' => 0], $this->request($p['auth'], 'talent', 'levelup', ['type' => 1, 'success' => 1]));
        DB::table('member')->where('uid', $uid)->update(['vip' => 6]);
        $up = $this->request($p['auth'], 'talent', 'levelup', ['type' => 1, 'success' => 1]);
        $this->assertSame([1, ['star' => 21, 'quality' => 2, 'power' => 730, 'type' => 1], 0], [$up['status'], $up['info'], $up['failed']]);
        $this->assertSame(585, $this->memberValue($uid, 'gold'));

        // 满星
        DB::table('own_hero_talent')->where('uid', $uid)->where('type', 2)->update(['star' => 300]);
        $this->assertSame(['status' => 2, 'info' => [], 'failed' => 0], $this->request($p['auth'], 'talent', 'levelup', ['type' => 2]));
        $info = $this->request($p['auth'], 'talent', 'get_info', ['type' => 2]);
        $this->assertSame([1, 1, []], [$info['status'], $info['ismax'], $info['scroll']]);
        $this->assertArrayNotHasKey('starup', $info);
    }

    public function testTalentRollsFollowLegacy(): void
    {
        // 升星失败：星级不变，品级为目标品级
        $rule = HeroTalent::rule(5, 1)['rule'];
        // 旧版 _roll([100 - rate, rate])：掷出 1 ~ 100 - rate 为失败
        $seed = $this->seedFor(static fn (): bool => mt_rand(1, 100) <= 100 - $rule['rate']);
        mt_srand($seed);
        $this->assertSame(['status' => -1, 'gold' => 0, 'info' => ['star' => 5, 'quality' => 1]], HeroTalent::starup($rule, 0, 0, 3));

        // 顿悟失败：按 sub1 ~ sub4 掉 1 ~ 4 星（旧版 _roll 遍历不到 sub5，第 5 档落到 4），品级 - 1
        $insight = HeroTalent::rule(20, 1)['rule'];
        $seed = $this->seedFor(static fn (): bool => mt_rand(1, 100) <= 100 - $insight['rate'] && mt_rand(1, 100) > 80);
        mt_srand($seed);
        $this->assertSame(['status' => -1, 'gold' => 0, 'info' => ['star' => 17, 'quality' => 1]], HeroTalent::insight($insight, 0, 0, 0, 3));
        // 保护：失败不掉星
        $seed = $this->seedFor(static fn (): bool => mt_rand(1, 100) <= 100 - $insight['rate']);
        mt_srand($seed);
        $this->assertSame(['status' => -1, 'gold' => 40, 'info' => ['star' => 20, 'quality' => 1]], HeroTalent::insight($insight, 1, 0, 1000, 3));
        $this->assertSame(['status' => 3, 'gold' => 0], HeroTalent::insight($insight, 1, 0, 39, 3));
    }

    /**
     * 找一个让 $check 成立的 mt_rand 种子
     */
    private function seedFor(callable $check): int
    {
        for ($seed = 1; $seed < 10000; $seed++) {
            mt_srand($seed);
            if ($check()) {
                return $seed;
            }
        }
        $this->fail('找不到种子');
    }

    // ---------------------------------------------------------------------------------------------
    // 将领界面与战斗加成
    // ---------------------------------------------------------------------------------------------

    public function testBonusesInGeneralInfoAndTeam(): void
    {
        $p = $this->player(['level' => 70]);
        [$uid, $gid] = [$p['uid'], $p['gid']];
        DB::table('general')->where('id', $gid)->update(['turn' => 1]);
        $baseInfo = $this->request($p['auth'], 'general', 'get_info', ['gid' => $gid])['info'];
        $baseTeam = TeamBuilder::forMember($uid)['team'];
        $pos = (int)array_key_first($baseTeam);
        $this->assertArrayNotHasKey('soul', $baseTeam[$pos]);
        $this->assertArrayNotHasKey('star_arr', $baseTeam[$pos]);

        // 装武力 / 生命 / 命中 / 士气将魂（转生 1 次 3 个槽位，命中换士气需另一个槽：先装 3 个）
        $this->request($p['auth'], 'soul', 'get_general_info', ['gid' => $gid]);
        $wuli = self::giveSoul($uid, 2, 3, 5);
        $life = self::giveSoul($uid, 4, 1, 0);
        $hit = self::giveSoul($uid, 9, 2, 3);
        foreach ([$wuli, $life, $hit] as $i => $sid) {
            $this->assertSame(1, $this->request($p['auth'], 'soul', 'equip', ['gid' => $gid, 'sid' => $sid, 'site' => $i + 1])['status']);
        }
        $pWuli = SoulType::ROWS[2]['lv5'];
        $pLife = SoulType::ROWS[4]['lv0'];
        $pHit = SoulType::ROWS[9]['lv3'];

        $team = TeamBuilder::forMember($uid)['team'];
        $unit = $team[$pos];
        $this->assertSame([2 => $pWuli, 4 => $pLife, 9 => $pHit], $unit['soul']);
        $this->assertSame($baseTeam[$pos]['wuli'] + $pWuli, $unit['wuli']);
        $isWarrior = (int)$unit['type'] === 1;
        $this->assertSame($isWarrior ? $pHit : 0, $unit['mingzhong']);

        // 将领界面：武力将魂加到 wuliup，生命将魂按百分比乘到血量上
        $info = $this->request($p['auth'], 'general', 'get_info', ['gid' => $gid])['info'];
        $this->assertSame($baseInfo['wuliup'] + $pWuli, $info['wuliup']);
        $row = DB::table('general')->where('id', $gid)->first();
        $talentBlood = (float)Talent::ROWS[(int)$row['talentid']]['blood'];
        $expectHp = static fn (int $heroTalent, int $tiliAdd, int $flat, float $mult): int => LegacyStats::infoHp(
            LegacyStats::baseHp((int)$row['level'], 1), 0, 0, $heroTalent, (int)$row['tili'], (int)$row['tiliup'] + $tiliAdd, $talentBlood, flatAdd: $flat, percentMultiplier: $mult,
        );
        $this->assertSame($expectHp(0, 0, 0, 1.0), $baseInfo['hp']);
        $this->assertSame($expectHp(0, 0, 0, 1 + $pLife / 100), $info['hp']);

        // 主公 91 级：星魄（命格加成）与主公天赋（第一次组队时建 5 行）
        DB::table('member')->where('uid', $uid)->update(['level' => 91]);
        OwnStar::addition($uid);
        DB::table('own_star_addition')->where('uid', $uid)->update([
            'wuli' => 7, 'zhili' => 8, 'tili' => 3, 'attack' => 40, 'defense' => 11, 'pattack' => 50, 'pdefense' => 12, 'blood' => 500,
            'dodge' => 2, 'crit' => 3, 'penetrate' => 4, 'harm' => 5, 'power' => 9, 'mingzhong' => 4, 'jingzhun' => 6, 'kangyun' => 1, 'kcrit' => 2,
        ]);
        $team91 = TeamBuilder::forMember($uid)['team'][$pos];
        $this->assertCount(5, HeroTalent::list($uid));
        $this->assertSame(OwnStar::addition($uid), $team91['star_arr']);
        $this->assertSame(
            [7, 8, 3, 9, 2, 11 + 100, 12 + 100, 500 + 300, ($isWarrior ? 40 : 50) + 100, $isWarrior ? 4 : 6],
            [
                $team91['wuli'] - $unit['wuli'], $team91['zhili'] - $unit['zhili'], $team91['tili'] - $unit['tili'], $team91['power'] - $unit['power'],
                $team91['kcrit'] - $unit['kcrit'], $team91['defense1'] - $unit['defense1'], $team91['defense2'] - $unit['defense2'],
                $team91['all_blood'] - $unit['all_blood'], $team91['attack'] - $unit['attack'], $team91['mingzhong'] - $unit['mingzhong'],
            ]
        );
        $info91 = $this->request($p['auth'], 'general', 'get_info', ['gid' => $gid])['info'];
        $this->assertSame([$info['wuliup'] + 7, $info['zhiliup'] + 8, $info['tiliup'] + 3], [$info91['wuliup'], $info91['zhiliup'], $info91['tiliup']]);
        $this->assertSame($expectHp(300, 3, 500, 1 + $pLife / 100), $info91['hp']);

        // 一场真实战斗：我方战斗血量计入将魂生命百分比，战报结构同旧版
        $mission = Mission::ROWS[array_key_first(Mission::ROWS)];
        $right = TeamBuilder::forMonsters($mission)['team'];
        $result = (new BattleEngine(rng: new Randomizer(new \Random\Engine\Mt19937(3))))->battle([$pos => $team91], $right);
        $this->assertSame(['leftteam', 'rightteam', 'process', 'assess', 'leftresult', 'rightresult', 'leftbuff', 'rightbuff'], array_keys($result));
        $expectBlood = LegacyStats::battleAllBlood((float)$team91['all_blood'], (int)$team91['tili'], (float)Talent::ROWS[(int)$team91['talentid']]['blood'], 0.0, (float)$pLife);
        $this->assertSame($expectBlood, $result['leftteam'][$pos]['allblood']);
        $this->assertNotEmpty($result['process']);
        $this->assertContains($result['assess'], [-3, -2, -1, 1, 2, 3]);
        // 星魄抗晕计入 leftbuff（buff 输出为旧版 buff_quality 档位）
        $this->assertSame(self::buffQuality((int)Talent::ROWS[(int)$team91['talentid']]['kangyun'] + 1), $result['leftbuff']['kangyun']);
    }

    /**
     * 旧版 buff_quality：0 → 0，≤ 20 → 2，≤ 40 → 3，≤ 60 → 4，≤ 80 → 5，其余 6
     */
    private static function buffQuality(int $buff): int
    {
        return match (true) {
            $buff === 0 => 0,
            $buff <= 20 => 2,
            $buff <= 40 => 3,
            $buff <= 60 => 4,
            $buff <= 80 => 5,
            default => 6,
        };
    }

    /**
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private static function unit(array $override): array
    {
        return array_replace(TeamBuilder::unportedUnit(1, '单位', 100000, 0, 0), $override);
    }

    /**
     * @param list<int> $offsets
     * @return array<string, mixed>
     */
    private static function firstAction(array $attack, array $defense, array $offsets): array
    {
        $engine = new BattleEngine(rng: new Randomizer(new ScriptedRandomEngine($offsets)));
        return $engine->battle([2 => self::unit($attack)], [2 => self::unit($defense)])['process'][0];
    }

    public function testEngineSoulAndStarBonuses(): void
    {
        $atk = ['type' => 1, 'attack' => 300, 'talentid' => 1];
        $def = ['type' => 1, 'talentid' => 1, 'defense1' => 0];
        $bonus = ['tili' => 0, 'soul' => [7 => 100, 8 => 20], 'star_arr' => ['crit' => 0, 'harm' => 10, 'dodge' => 0, 'penetrate' => 0, 'kangyun' => 0]];

        // 暴击率：将魂(7) 100 → 必暴；暴击伤害 = 300 × 1.5 + 300 × 星魄 10% + intval(300 × 将魂 20%) = 540
        $log = self::firstAction($atk + $bonus, $def, [99, 99]);
        $this->assertSame([1, 540], [$log['hit'], $log['hurt'][0]['blood']]);
        // 没有体力字段（非玩家）不计将魂 / 星魄
        $bonusNoTili = $bonus;
        unset($bonusNoTili['tili']);
        $log = self::firstAction($atk + $bonusNoTili, $def, [99, 99]);
        $this->assertSame([0, 300], [$log['hit'], $log['hurt'][0]['blood']]);
        // 星魄暴击率
        $log = self::firstAction($atk + ['tili' => 0, 'star_arr' => ['crit' => 100, 'harm' => 0]], $def, [99, 99]);
        $this->assertSame([1, 450], [$log['hit'], $log['hurt'][0]['blood']]);

        // 闪避：防守方将魂(5) + 星魄闪避 ≥ 100 → 闪避（攻击类型 -1）
        $log = self::firstAction($atk, $def + ['tili' => 0, 'soul' => [5 => 60], 'star_arr' => ['dodge' => 40]], [99, 99]);
        $this->assertSame(-1, $log['attacktype']);
        $log = self::firstAction($atk, $def + ['tili' => 0, 'soul' => [5 => 60], 'star_arr' => ['dodge' => 39]], [99, 99]);
        $this->assertSame(0, $log['attacktype']);
        // 识破：谋士攻击时防守方将魂(6) + 星魄识破
        $log = self::firstAction(['type' => 2, 'attack' => 300, 'talentid' => 1], $def + ['tili' => 0, 'soul' => [6 => 50], 'star_arr' => ['penetrate' => 50]], [99, 99]);
        $this->assertSame(-2, $log['attacktype']);

        // 战斗血量：将魂生命(4) 按百分比；抗晕：星魄 + 将魂(12)；右侧只对有体力字段的单位
        $engine = new BattleEngine(rng: new Randomizer(new ScriptedRandomEngine([])));
        $player = ['tili' => 100, 'talentid' => 1, 'all_blood' => 1000, 'allblood' => 0, 'blood' => 0, 'soul' => [4 => 10, 12 => 30], 'star_arr' => ['kangyun' => 15]];
        $res = $engine->battle([2 => self::unit($player)], [2 => self::unit($player), 3 => self::unit(['soul' => [12 => 50], 'star_arr' => ['kangyun' => 50]])]);
        $this->assertSame((int)((int)(1000 * 1.2 * 1.0) * 110 / 100), $res['leftteam'][2]['allblood']);
        // 30 + 15 = 45 → 档位 4；右侧没有体力字段的单位不加（否则 100 → 档位 6）
        $this->assertSame([4, 4], [$res['leftbuff']['kangyun'], $res['rightbuff']['kangyun']]);
    }
}
