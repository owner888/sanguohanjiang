<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\LegacyStats;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\Handler\HandlerRegistry;
use Hanjiang\Http\Router;
use Hanjiang\MasterData\AdvancedInfo;
use Hanjiang\MasterData\Book;
use Hanjiang\MasterData\BookAddition;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\RefineAchieve;
use Hanjiang\MasterData\RefineMaster;
use Hanjiang\MasterData\Talent;
use Hanjiang\Middleware\AuthMiddleware;
use Hanjiang\Middleware\CorsMiddleware;
use Hanjiang\Middleware\DailyCronMiddleware;
use Hanjiang\Middleware\GameProtocolMiddleware;
use Hanjiang\Middleware\ResponseEnvelopeMiddleware;
use Hanjiang\Middleware\ThrottleMiddleware;
use Hanjiang\Model\Advanced;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\General;
use Hanjiang\Model\GeneralBook;
use Hanjiang\Model\HeroTalent;
use Hanjiang\Model\Refine;
use Hanjiang\Model\TraderLog;
use Hanjiang\Tests\Support\ScriptedRandomEngine;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use PHPUnit\Framework\TestCase;
use Random\Randomizer;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * 养成第三批：名将进阶（c=advanced）、装备精炼（c=refine）、名将谱 / 名将成就（c=general_book），
 * 以及进阶 / 名将成就加成在将领界面、组队与推演中的计入
 */
final class Growth3Test extends TestCase
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

    protected function tearDown(): void
    {
        BossClock::freeze(null);
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
            unset($res['msg']);
        }
        return $res;
    }

    /**
     * 新游客：返回 uid、鉴权参数与出征的初始武将 id（模板 1 廖化，猛将）
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

    private static function freeleap(int $uid): int
    {
        return (int)DB::table('member_field')->where('uid', $uid)->value('freeleap');
    }

    /**
     * 直接放一件装备（可指定精炼等级与穿戴将领），返回 equipment.id
     *
     * @param array<string, mixed> $override
     */
    private static function giveEquip(int $uid, int $equipid, array $override = []): int
    {
        $tpl = Equip::ROWS[$equipid];
        return (int)DB::table('equipment')->insert(array_merge([
            'uid' => $uid, 'equipid' => $equipid, 'name' => $tpl['name'], 'type' => $tpl['type'], 'quality' => $tpl['quality'],
            'needlevel' => $tpl['needlevel'], 'level' => 0, 'power' => 100, 'powerup' => 300, 'saleprice' => 0, 'generalid' => 0,
        ], $override));
    }

    private static function hammer(int $id): int
    {
        return (int)DB::table('equipment')->where('id', $id)->value('hammer');
    }

    // ---------------------------------------------------------------------------------------------
    // 名将进阶
    // ---------------------------------------------------------------------------------------------

    public function testAdvancedIndexGateAndSkillInfo(): void
    {
        $p = $this->player(['level' => 99]);
        foreach (['index', 'get_skill_info', 'get_general_info', 'level_up', 'start_advanced', 'advanced_inherit', 'get_advanced_inherit_info'] as $m) {
            $this->assertSame(2, $this->request($p['auth'], 'advanced', $m, ['gid' => $p['gid'], 't' => 1])['status'], $m);
        }
        $this->assertSame(0, DB::table('advanced_log')->where('uid', $p['uid'])->count());

        DB::table('member')->where('uid', $p['uid'])->update(['level' => 100]);
        $res = $this->request($p['auth'], 'advanced', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertSame([['id' => $p['gid'], 'level' => 1, 'name' => '廖化', 'image' => 1, 'quality' => 1, 'type' => 1]], $res['list']);
        // 第一次打开时建档：品级 1、技能 0 级，名称取各类型第一行
        $row = Advanced::generalSkill($p['gid'], $p['uid']);
        $this->assertSame([1, '好战', '坚强', '睿智', '强壮'], [$row['quality'], $row['skill1_name'], $row['skill2_name'], $row['skill3_name'], $row['skill4_name']]);
        $this->assertSame(1, DB::table('advanced_log')->where('uid', $p['uid'])->count());
        $this->assertSame(
            ['level' => 0, 'name' => '好战', 'value' => '0%', 'add_name' => '物攻天赋', 'quality' => 1, 'is_full' => 0, 'type' => 1,
                'next_value' => '2%', 'next_use' => 100, 'next_level' => 1],
            $res['skill_info']
        );
        $this->assertSame(AdvancedInfo::ROWS[2] + ['general_level' => 100], $res['quality_info']);
        $this->assertSame(0, $res['level_up']);
        // 进阶传承 CD（没用过为 0）、资源（140 级以下不返回声望）
        $this->assertSame(0, $res['cd']);
        $this->assertSame(['freeleap' => 10, 'soul' => 100, 'jade2' => 0, 'jade3' => 0], $res['resource']);
        $this->assertSame(['skill1_level' => 0, 'skill1_name' => '好战', 'skill1_quality' => 1], array_intersect_key($res['general'], array_flip(['skill1_level', 'skill1_name', 'skill1_quality'])));

        // 单个技能信息
        $info = $this->request($p['auth'], 'advanced', 'get_skill_info', ['gid' => $p['gid'], 't' => 2])['info'];
        $this->assertSame(['level' => 0, 'name' => '坚强', 'value' => '0%', 'add_name' => '物防天赋', 'quality' => 1, 'is_full' => 0, 'type' => 2,
            'next_value' => '2%', 'next_use' => 50, 'next_level' => 1], $info);
        $this->assertSame(-1, $this->request($p['auth'], 'advanced', 'get_skill_info', ['gid' => $p['gid'], 't' => 5])['status']);
        $this->assertSame(-1, $this->request($p['auth'], 'advanced', 'get_skill_info', ['gid' => 999999, 't' => 1])['status']);
        $this->assertSame(-1, $this->request($p['auth'], 'advanced', 'get_general_info', ['gid' => 999999])['status']);

        // 140 级起返回声望；VIP 11 的传承 CD 15 天
        DB::table('member')->where('uid', $p['uid'])->update(['level' => 140, 'vip' => 11]);
        BossClock::freeze(2_000_000_000);
        DB::table('member_field')->where('uid', $p['uid'])->update(['advanced_time' => 2_000_000_000 - 86400]);
        $res = $this->request($p['auth'], 'advanced', 'index');
        $this->assertSame(100, $res['resource']['reputation']);
        $this->assertSame(14 * 86400, $res['cd']);
    }

    public function testAdvancedLevelUpStartAdvancedAndInherit(): void
    {
        $p = $this->player(['level' => 100]);
        [$uid, $gid] = [$p['uid'], $p['gid']];
        DB::table('member_field')->where('uid', $uid)->update(['freeleap' => 100000]);

        // 技能 1 连升 3 级：100 + 200 + 300 张突飞卡
        foreach ([1 => '2%', 2 => '4%', 3 => '6%'] as $level => $value) {
            $res = $this->request($p['auth'], 'advanced', 'level_up', ['gid' => $gid, 't' => 1]);
            $this->assertSame(1, $res['status']);
            $this->assertSame(['level' => $level, 'name' => '好战', 'value' => $value, 'type' => 1, 'quality' => 1, 'is_full' => 0, 'add_name' => '物攻天赋'],
                array_slice($res['skill'], 0, 7));
            $this->assertSame([], $res['general']);
        }
        $this->assertSame(['8%', 400, 4], [$res['skill']['next_value'], $res['skill']['next_use'], $res['skill']['next_level']]);
        $this->assertSame(100000 - 600, $res['freeleap']);
        $this->assertSame(100000 - 600, self::freeleap($uid));
        // 品级 1 时技能最高 3 级
        $this->assertSame(-8, $this->request($p['auth'], 'advanced', 'level_up', ['gid' => $gid, 't' => 1])['status']);
        $this->assertSame(3, Advanced::generalSkill($gid, $uid)['skill1_level']);
        $this->assertSame(0.06, Advanced::generalSkill($gid, $uid)['skill1_value']);
        // 突飞卡不足不扣
        DB::table('member_field')->where('uid', $uid)->update(['freeleap' => 49]);
        $this->assertSame(-6, $this->request($p['auth'], 'advanced', 'level_up', ['gid' => $gid, 't' => 2])['status']);
        $this->assertSame(49, self::freeleap($uid));
        DB::table('member_field')->where('uid', $uid)->update(['freeleap' => 100000]);
        $this->assertSame(-2, $this->request($p['auth'], 'advanced', 'level_up', ['gid' => 999999, 't' => 2])['status']);

        // 生命技能升级时重新计算将领信息（血量含生命技能加成，与将领界面一致）
        $before = $this->request($p['auth'], 'general', 'get_info', ['gid' => $gid])['info']['hp'];
        $res = $this->request($p['auth'], 'advanced', 'level_up', ['gid' => $gid, 't' => 4]);
        $this->assertSame(1, $res['status']);
        $after = $this->request($p['auth'], 'general', 'get_info', ['gid' => $gid])['info']['hp'];
        $this->assertGreaterThan($before, $after);
        $this->assertSame($after, $res['general']['hp']);
        $this->assertSame(0, $res['level_up']);

        // 四个技能都到 3 级、将领 100 级 → 可进阶
        foreach ([2, 2, 2, 3, 3, 3, 4, 4] as $t) {
            $res = $this->request($p['auth'], 'advanced', 'level_up', ['gid' => $gid, 't' => $t]);
            $this->assertSame(1, $res['status']);
        }
        $this->assertSame(0, $res['level_up']);
        $this->assertSame(-6, $this->request($p['auth'], 'advanced', 'start_advanced', ['gid' => $gid])['status']);
        DB::table('general')->where('id', $gid)->update(['level' => 100]);
        $this->assertSame(1, $this->request($p['auth'], 'advanced', 'get_general_info', ['gid' => $gid])['level_up']);

        // 进阶传承：被传承将领品级不高于受益将领
        $gid2 = (int)General::createFromTemplate($uid, 74);
        $this->assertSame(-6, $this->request($p['auth'], 'advanced', 'advanced_inherit', ['gid1' => $gid, 'gid2' => $gid2])['status']);
        $this->assertSame(-5, $this->request($p['auth'], 'advanced', 'advanced_inherit', ['gid1' => $gid2, 'gid2' => $gid])['status']);
        $this->assertSame(-3, $this->request($p['auth'], 'advanced', 'advanced_inherit', ['gid1' => $gid, 'gid2' => $gid])['status']);

        // 进阶 2 品：黄宝石 1800、紫宝石 1800、原魂 90000（声望 0），按顺序检查
        $this->assertSame(-8, $this->request($p['auth'], 'advanced', 'start_advanced', ['gid' => $gid])['status']);
        TraderLog::addJade($uid, 2, 1800);
        $this->assertSame(-9, $this->request($p['auth'], 'advanced', 'start_advanced', ['gid' => $gid])['status']);
        TraderLog::addJade($uid, 3, 2000);
        $this->assertSame(-10, $this->request($p['auth'], 'advanced', 'start_advanced', ['gid' => $gid])['status']);
        DB::table('member')->where('uid', $uid)->update(['soul' => 90005]);
        $res = $this->request($p['auth'], 'advanced', 'start_advanced', ['gid' => $gid]);
        $this->assertSame(1, $res['status']);
        $this->assertSame(['freeleap' => self::freeleap($uid), 'soul' => 5, 'jade2' => 0, 'jade3' => 200], $res['resource']);
        $this->assertSame([5, 0, 200], [$this->memberValue($uid, 'soul'), TraderLog::jade($uid, 2), TraderLog::jade($uid, 3)]);
        $this->assertSame(2, $res['general']['quality']);
        $this->assertSame(AdvancedInfo::ROWS[3] + ['general_level' => 120], $res['quality_info']);
        $this->assertSame(0, $res['level_up']);
        $row = Advanced::generalSkill($gid, $uid);
        $this->assertSame([2, 30, 30, 30, 37500], [$row['quality'], $row['wuli'], $row['zhili'], $row['tili'], $row['hp']]);
        $this->assertSame(2, (int)DB::table('general')->where('id', $gid)->value('quality'));
        // 进阶后的属性：wuli 含进阶武力（加在 wuli 上，不是 wuliup）
        $tpl = DB::table('general')->where('id', $gid)->first();
        $this->assertSame((int)$tpl['wuli'] + 30, $res['general']['wuli']);
        $this->assertSame($this->request($p['auth'], 'general', 'get_info', ['gid' => $gid])['info']['hp'], $res['general']['hp']);
        // 品级 2 后技能可升到 6 级
        $this->assertSame(1, $this->request($p['auth'], 'advanced', 'level_up', ['gid' => $gid, 't' => 1])['status']);

        // 可传承数据
        $res = $this->request($p['auth'], 'advanced', 'get_advanced_inherit_info', ['gid' => $gid]);
        $this->assertSame(1, $res['status']);
        $this->assertSame(['8%', '攻击天赋', '6%', '物防天赋', '6%', '策防天赋', '6%', '生命天赋'], [
            $res['advanced']['skill1_value'], $res['advanced']['skill1_addname'], $res['advanced']['skill2_value'], $res['advanced']['skill2_addname'],
            $res['advanced']['skill3_value'], $res['advanced']['skill3_addname'], $res['advanced']['skill4_value'], $res['advanced']['skill4_addname'],
        ]);
        $this->assertSame(-2, $this->request($p['auth'], 'advanced', 'get_advanced_inherit_info', ['gid' => 999999])['status']);

        // 进阶传承：主将的进阶整体移到新将身上，主将清空；CD 30 天（VIP 0）
        BossClock::freeze(2_000_000_000);
        $res = $this->request($p['auth'], 'advanced', 'advanced_inherit', ['gid1' => $gid, 'gid2' => $gid2]);
        $this->assertSame(1, $res['status']);
        $this->assertSame(30 * 86400, $res['cd']);
        $this->assertSame(1, $res['general']['quality']);
        $this->assertSame(0, $res['general']['skill1_level']);
        $this->assertCount(1, $res['list']);
        $moved = Advanced::generalSkill($gid2, $uid);
        $this->assertSame([2, 4, 3, 3, 3, 30, 37500], [$moved['quality'], $moved['skill1_level'], $moved['skill2_level'], $moved['skill3_level'], $moved['skill4_level'], $moved['wuli'], $moved['hp']]);
        $cleared = Advanced::generalSkill($gid, $uid);
        $this->assertSame([1, 0, 0, 0.0], [$cleared['quality'], $cleared['skill1_level'], $cleared['wuli'], $cleared['skill4_value']]);
        $this->assertSame([1, 2], [(int)DB::table('general')->where('id', $gid)->value('quality'), (int)DB::table('general')->where('id', $gid2)->value('quality')]);
        $this->assertSame(2_000_000_000, Advanced::dateline($uid));
        $log = DB::table('advanced_inherit_log')->where('uid', $uid)->first();
        $this->assertSame([$gid, 2, 4, $gid2, 1, 0], [(int)$log['gid1'], (int)$log['g1_quality'], (int)$log['g1_level1'], (int)$log['gid2'], (int)$log['g2_quality'], (int)$log['g2_level1']]);
        // CD 中
        $this->assertSame(-2, $this->request($p['auth'], 'advanced', 'advanced_inherit', ['gid1' => $gid2, 'gid2' => $gid])['status']);
        $this->assertSame(30 * 86400, $this->request($p['auth'], 'advanced', 'index')['cd']);
    }

    public function testAdvancedBonusesInTeamGeneralInfoAndEngine(): void
    {
        $p = $this->player(['level' => 99]);
        [$uid, $gid] = [$p['uid'], $p['gid']];
        DB::table('advanced_log')->insert([
            'uid' => $uid, 'gid' => $gid, 'quality' => 3, 'skill1_level' => 6, 'skill2_level' => 6, 'skill3_level' => 6, 'skill4_level' => 6,
            'skill1_value' => 0.12, 'skill2_value' => 0.1, 'skill3_value' => 0.1, 'skill4_value' => 0.5,
            'wuli' => 80, 'zhili' => 81, 'tili' => 82, 'hp' => 100000,
        ]);
        // 99 级：不计进阶
        $base = TeamBuilder::forMember($uid)['team'];
        $pos = (int)array_key_first($base);
        $this->assertArrayNotHasKey('advanced', $base[$pos]);
        $baseInfo = $this->request($p['auth'], 'general', 'get_info', ['gid' => $gid])['info'];

        DB::table('member')->where('uid', $uid)->update(['level' => 100]);
        $unit = TeamBuilder::forMember($uid)['team'][$pos];
        $this->assertSame(0.5, $unit['advanced']['skill4_value']);
        $this->assertSame([80, 81, 82, 100000], [
            $unit['wuli'] - $base[$pos]['wuli'], $unit['zhili'] - $base[$pos]['zhili'], $unit['tili'] - $base[$pos]['tili'], $unit['all_blood'] - $base[$pos]['all_blood'],
        ]);

        // 将领界面：三围加到 wuli / zhili / tili，血量加到 hp，生命技能加到天赋血量系数上
        $info = $this->request($p['auth'], 'general', 'get_info', ['gid' => $gid])['info'];
        $this->assertSame([$baseInfo['wuli'] + 80, $baseInfo['zhili'] + 81, $baseInfo['tili'] + 82], [$info['wuli'], $info['zhili'], $info['tili']]);
        $row = DB::table('general')->where('id', $gid)->first();
        // 主公 91 级起的生命天赋（组队时已按旧版建档）
        $heroTalentHp = (int)(HeroTalent::byType($uid, 5)['power'] ?? 0);
        $this->assertSame(LegacyStats::infoHp(
            LegacyStats::baseHp((int)$row['level'], 0), 0, 0, $heroTalentHp, (int)$row['tili'] + 82, (int)$row['tiliup'],
            (float)Talent::ROWS[(int)$row['talentid']]['blood'], advancedBlood: 0.5, flatAdd: 100000,
        ), $info['hp']);
        // 名将进阶界面的血量与将领界面一致
        $this->assertSame($info['hp'], $this->request($p['auth'], 'advanced', 'get_general_info', ['gid' => $gid])['general']['hp']);

        // 推演：攻击天赋系数 + 技能 1，防御天赋系数 + 技能 2 / 3，总血量的天赋血量系数 + 技能 4
        $atk = ['type' => 1, 'attack' => 300, 'talentid' => 1];
        $def = ['type' => 1, 'talentid' => 1, 'defense1' => 100, 'defense2' => 100];
        $this->assertSame(200, self::firstAction($atk, $def, [99, 99])['hurt'][0]['blood']);
        $this->assertSame(350, self::firstAction($atk + ['advanced' => self::adv(['skill1_value' => 0.5])], $def, [99, 99])['hurt'][0]['blood']);
        $this->assertSame(150, self::firstAction($atk, $def + ['advanced' => self::adv(['skill2_value' => 0.5])], [99, 99])['hurt'][0]['blood']);
        // 谋士打策防：取技能 3
        $this->assertSame(150, self::firstAction(['type' => 2] + $atk, $def + ['advanced' => self::adv(['skill3_value' => 0.5])], [99, 99])['hurt'][0]['blood']);
        $engine = new BattleEngine(rng: new Randomizer(new ScriptedRandomEngine([])));
        $player = ['tili' => 100, 'talentid' => 1, 'all_blood' => 1000, 'allblood' => 0, 'blood' => 0, 'advanced' => self::adv(['skill4_value' => 0.5])];
        $res = $engine->battle([2 => self::unit($player)], [2 => self::unit([])]);
        $this->assertSame((int)(1000 * 1.2 * 1.5), $res['leftteam'][2]['allblood']);
    }

    // ---------------------------------------------------------------------------------------------
    // 装备精炼
    // ---------------------------------------------------------------------------------------------

    public function testRefineIndexMasterRefreshAndGates(): void
    {
        $p = $this->player(['level' => 159]);
        $uid = $p['uid'];
        foreach (['index', 'item_index', 'item_selected', 'master_refresh', 'preview'] as $m) {
            $res = $this->request($p['auth'], 'refine', $m, ['id' => 1, 'id1' => 1, 'id2' => 2]);
            $this->assertSame([-1, '抱歉，您的等级不足！'], [$res['status'], $res['msg']], $m);
        }
        $this->assertSame(0, DB::table('refine_master_log')->where('uid', $uid)->count());
        // 精炼的等级不足旧版不解锁：30 秒内再精炼是「太频繁」
        $this->assertSame(-1, $this->request($p['auth'], 'refine', 'refinning', ['id' => 1])['status']);
        DB::table('member')->where('uid', $uid)->update(['level' => 160]);
        $this->assertSame(5, $this->request($p['auth'], 'refine', 'refinning', ['id' => 1])['status']);
        Refine::unlock(Refine::REFINE_LOCK, $uid);

        // 首次进入：随机一位非橙 / 红大师；13:00 时离 20:00 还有 7 小时
        $now = (int)strtotime('2033-05-18 13:00:00');
        BossClock::freeze($now);
        $res = $this->request($p['auth'], 'refine', 'index');
        $this->assertSame(1, $res['status']);
        $log = Refine::masterLog($uid, $now);
        $this->assertSame([$now, $log['masterid']], [$log['dateline'], $res['master']['id']]);
        $this->assertNotContains(RefineMaster::ROWS[$log['masterid']]['grade'], [5, 6]);
        $this->assertSame(7 * 3600, $res['cd']);
        $this->assertSame(100, $res['price']);
        $this->assertSame(['jade' => 0, 'refinestone' => 0, 'colorstone' => 0, 'bloodstone' => 0], $res['resource']);
        $master = RefineMaster::ROWS[$log['masterid']];
        $this->assertSame($master['add_type'] === 2 ? $master['succ_rate'] + $master['add_value'] : $master['succ_rate'], $res['master']['current']);

        // 过了 20:00：上次刷新在 20:00 之前 → 自动刷新（不花元宝），倒计时到次日 04:00
        BossClock::freeze($now + 8 * 3600);
        $res = $this->request($p['auth'], 'refine', 'index');
        $this->assertSame(7 * 3600, $res['cd']);
        $log2 = Refine::masterLog($uid, $now);
        $this->assertSame($now + 8 * 3600, $log2['dateline']);
        $this->assertNotSame($log['masterid'], $log2['masterid']);
        $this->assertSame($log2['masterid'], $res['master']['id']);

        // 花元宝刷新：不会刷到当前大师
        DB::table('member')->where('uid', $uid)->update(['gold' => 99]);
        $this->assertSame(-3, $this->request($p['auth'], 'refine', 'master_refresh', ['zero' => 0])['status']);
        DB::table('member')->where('uid', $uid)->update(['gold' => 150]);
        $res = $this->request($p['auth'], 'refine', 'master_refresh', ['zero' => 0]);
        $this->assertSame(1, $res['status']);
        $this->assertSame([0, 50, 50], [$res['refresh'], $res['all_gold'], $this->memberValue($uid, 'gold')]);
        $this->assertNotSame($log2['masterid'], $res['list']['id']);
        $this->assertSame($res['list']['id'], Refine::masterLog($uid, $now)['masterid']);
        // 倒计时到 0 的免费刷新
        $res = $this->request($p['auth'], 'refine', 'master_refresh', ['zero' => 1]);
        $this->assertSame([1, 1, 50], [$res['status'], $res['refresh'], $this->memberValue($uid, 'gold')]);
        $this->assertSame(-2, $this->request($p['auth'], 'refine', 'master_refresh', ['zero' => 2])['status']);
        // 防刷锁
        Refine::lock(Refine::REFRESH_LOCK, $uid);
        $this->assertSame([2, '请稍后再试'], array_values(array_intersect_key($this->request($p['auth'], 'refine', 'master_refresh', ['zero' => 1]), ['status' => 0, 'msg' => 0])));
        Refine::unlock(Refine::REFRESH_LOCK, $uid);
    }

    public function testRefineItemListSelectedAndRefinning(): void
    {
        $p = $this->player(['level' => 160]);
        [$uid, $gid] = [$p['uid'], $p['gid']];
        $this->assertSame(-2, $this->request($p['auth'], 'refine', 'item_index')['status']);

        $weapon = self::giveEquip($uid, 1, ['generalid' => $gid]);
        $armor = self::giveEquip($uid, 2, ['hammer' => 5]);
        self::giveEquip($uid, 1, ['chip' => 3]);
        $other = $this->player(['level' => 160]);
        $foreign = self::giveEquip($other['uid'], 1);

        $res = $this->request($p['auth'], 'refine', 'item_index');
        $this->assertSame(1, $res['status']);
        // 穿戴的在前；碎片不列出
        $this->assertSame([$weapon, $armor], array_column($res['list'], 'id'));
        $this->assertSame(['id' => $weapon, 'gid' => $gid, 'type' => 1, 'level' => 0, 'equipid' => 1, 'quality' => Equip::ROWS[1]['quality'],
            'hammer' => 0, 'name' => Equip::ROWS[1]['name'], 'generalname' => '廖化', 'hammer_quality' => 0], $res['list'][0]);
        $this->assertSame(1, $res['list'][1]['hammer_quality']);

        // 选择装备：强化值 200，下一级精炼 20%
        $res = $this->request($p['auth'], 'refine', 'item_selected', ['id' => $weapon]);
        $this->assertSame(1, $res['status']);
        $promote1 = Refine::promote(1);
        $this->assertSame([0, ['jade' => $promote1['jade'], 'refinestone' => $promote1['refinestone'], 'colorstone' => 0, 'bloodstone' => 0], 0, 20, 40, 0, 0, 200, 300, 0, 0, 0], [
            $res['list']['refine'], $res['list']['cost_next'], $res['list']['percent'], $res['list']['percent_next'], $res['list']['refine_next'],
            $res['list']['strength_ach'], $res['list']['strength_ach_next'], $res['list']['powerups'], $res['list']['all'],
            $res['list']['now_mode'], $res['list']['ismax'], $res['list']['hammer_quality'],
        ]);
        $this->assertSame($gid, $res['list']['gid']);
        // 5 级 40%：精炼 80，下一级 45% → +10
        $res = $this->request($p['auth'], 'refine', 'item_selected', ['id' => $armor])['list'];
        $this->assertSame([80, 40, Refine::promote(6)['percent'], (int)round(200 * Refine::promote(6)['percent'] / 100 - 80), 380], [$res['refine'], $res['percent'], $res['percent_next'], $res['refine_next'], $res['all']]);
        $this->assertSame(-3, $this->request($p['auth'], 'refine', 'item_selected', ['id' => $foreign])['status']);
        $this->assertSame(-2, $this->request($p['auth'], 'refine', 'item_selected', ['id' => 0])['status']);

        // 精炼：材料不足
        Refine::masterLog($uid, time());
        $this->assertSame(-6, $this->request($p['auth'], 'refine', 'refinning', ['id' => $weapon])['status']);
        DB::table('member')->where('uid', $uid)->update(['jade' => $promote1['jade']]);
        $this->assertSame(-7, $this->request($p['auth'], 'refine', 'refinning', ['id' => $weapon])['status']);
        $this->assertSame(-4, $this->request($p['auth'], 'refine', 'refinning', ['id' => $foreign])['status']);
        $this->assertSame(-2, $this->request($p['auth'], 'refine', 'refinning', ['id' => 0])['status']);

        // 欧冶子（成功率 70 + 30）：必成，不连升
        DB::table('member')->where('uid', $uid)->update(['refinestone' => $promote1['refinestone'] + 7]);
        DB::table('refine_master_log')->where('uid', $uid)->update(['masterid' => 12]);
        $res = $this->request($p['auth'], 'refine', 'refinning', ['id' => $weapon]);
        $this->assertSame([1, 0], [$res['status'], $res['hit']]);
        $this->assertSame(['jade' => 0, 'refinestone' => 7, 'colorstone' => 0, 'bloodstone' => 0], $res['resource']);
        $this->assertSame([1, 40, 340, 1], [$res['list']['hammer'], $res['list']['refine'], $res['list']['all'], $res['list']['hammer_quality']]);
        $this->assertSame(1, self::hammer($weapon));
        $log = DB::table('refine_log')->where('uid', $uid)->first();
        $this->assertSame([$weapon, 12, 0, 1], [(int)$log['eid'], (int)$log['mid'], (int)$log['now_level'], (int)$log['new_level']]);
        $this->assertSame(0, (int)DB::table('jade_log')->where('uid', $uid)->where('type', 2)->where('jade', '<>', $promote1['jade'])->count());

        // 祖冲之（成功率 20，add_type 1 暴击 5%）：失败照样扣材料；成功且暴击时连升 2 级
        DB::table('refine_master_log')->where('uid', $uid)->update(['masterid' => 1]);
        $promote2 = Refine::promote(2);
        $give = static fn () => DB::table('member')->where('uid', $uid)->update(['jade' => $promote2['jade'], 'refinestone' => $promote2['refinestone']]);
        $give();
        $seed = self::seed(static fn (): bool => mt_rand(1, 100) > 20);
        mt_srand($seed);
        $res = $this->request($p['auth'], 'refine', 'refinning', ['id' => $weapon]);
        $this->assertSame([4, 0, 0, 0], [$res['status'], $res['hit'], $res['resource']['jade'], $res['resource']['refinestone']]);
        $this->assertArrayNotHasKey('list', $res);
        $this->assertSame(1, self::hammer($weapon));
        $give();
        $seed = self::seed(static fn (): bool => mt_rand(1, 100) <= 20 && mt_rand(1, 100) <= 5);
        mt_srand($seed);
        $res = $this->request($p['auth'], 'refine', 'refinning', ['id' => $weapon]);
        $this->assertSame([1, 1, 3], [$res['status'], $res['hit'], $res['list']['hammer']]);
        $this->assertSame(3, self::hammer($weapon));
        // 8 级（晋升前）不连升
        DB::table('equipment')->where('id', $weapon)->update(['hammer' => 8]);
        $promote9 = Refine::promote(9);
        DB::table('member')->where('uid', $uid)->update(['jade' => $promote9['jade'], 'refinestone' => $promote9['refinestone'], 'colorstone' => $promote9['colorstone'], 'bloodstone' => $promote9['bloodstone']]);
        mt_srand(self::seed(static fn (): bool => mt_rand(1, 100) <= 20));
        $res = $this->request($p['auth'], 'refine', 'refinning', ['id' => $weapon]);
        $this->assertSame([1, 0, 9, 1], [$res['status'], $res['hit'], $res['list']['hammer'], $res['list']['now_mode']]);
        $this->assertNull(Redis::get(Refine::REFINE_LOCK . $uid));
    }

    public function testRefineInheritPreviewAndAchievement(): void
    {
        $p = $this->player(['level' => 160]);
        [$uid, $gid] = [$p['uid'], $p['gid']];
        $a = self::giveEquip($uid, 1, ['hammer' => 5]);
        $b = self::giveEquip($uid, 1, ['hammer' => 2, 'powerup' => 333]);
        $zero = self::giveEquip($uid, 1);
        $foreign = self::giveEquip($this->player()['uid'], 1, ['hammer' => 1]);

        // 预览：右边按左边的精炼等级算（33.3% 之类的小数照旧版输出）
        $res = $this->request($p['auth'], 'refine', 'preview', ['id1' => $a, 'id2' => $b]);
        $this->assertSame(1, $res['status']);
        $this->assertSame(['refine' => 300, 'level' => 0, 'type' => 0, 'powerups' => 200, 'all' => 300, 'power' => 100, 'hammer_quality' => 0], $res['left']);
        $this->assertSame(['refine' => 133.2, 'type' => 1, 'level' => 5, 'powerups' => 233, 'all' => 466.2, 'power' => 100, 'hammer_quality' => 1], $res['right']);
        $this->assertSame(-4, $this->request($p['auth'], 'refine', 'preview', ['id1' => $b, 'id2' => $a])['status']);
        $this->assertSame(-5, $this->request($p['auth'], 'refine', 'inherit', ['id1' => $zero, 'id2' => $b])['status']);
        $this->assertSame(-4, $this->request($p['auth'], 'refine', 'inherit', ['id1' => $b, 'id2' => $a])['status']);
        $this->assertSame(-3, $this->request($p['auth'], 'refine', 'inherit', ['id1' => $foreign, 'id2' => $b])['status']);
        $this->assertSame(-2, $this->request($p['auth'], 'refine', 'inherit', ['id1' => $a])['status']);

        $res = $this->request($p['auth'], 'refine', 'inherit', ['id1' => $a, 'id2' => $b]);
        $this->assertSame(['status' => 1, 'left' => ['hammer' => 0, 'hammer_quality' => 0], 'right' => ['hammer' => 5, 'hammer_quality' => 1]], $res);
        $this->assertSame([0, 5], [self::hammer($a), self::hammer($b)]);
        $this->assertSame(1, DB::table('refine_inherit_log')->where('uid', $uid)->where('id1', $a)->where('id2', $b)->count());
        Refine::lock(Refine::INHERIT_LOCK, $uid);
        $this->assertSame(2, $this->request($p['auth'], 'refine', 'inherit', ['id1' => $b, 'id2' => $a])['status']);
        Refine::unlock(Refine::INHERIT_LOCK, $uid);

        // 精炼成就（旧版不判断开放等级）：4 件装备最低精炼 10 级 → 成就 2（累加成就 1、2 的加成）
        DB::table('member')->where('uid', $uid)->update(['level' => 30]);
        $res = $this->request($p['auth'], 'refine', 'achieve_index');
        $this->assertSame(1, $res['status']);
        $this->assertSame([0, []], [$res['general']['achid'], $res['general']['achInfo']]);
        $this->assertSame(array_values(RefineAchieve::ROWS), $res['achList']);
        foreach ([[1, 12], [2, 10], [5, 30], [7, 11]] as [$equipid, $hammer]) {
            self::giveEquip($uid, $equipid, ['generalid' => $gid, 'hammer' => $hammer]);
        }
        $res = $this->request($p['auth'], 'refine', 'get_achieve_info', ['gid' => $gid]);
        $this->assertSame(1, $res['status']);
        $r1 = RefineAchieve::ROWS[1];
        $r2 = RefineAchieve::ROWS[2];
        $this->assertSame(2, $res['general']['achid']);
        $this->assertSame([
            'id' => 2, 'level' => 10,
            'add_type1' => 1, 'add_type_val1' => $r1['add_type_val1'] + $r2['add_type_val1'],
            'add_type2' => 2, 'add_type_val2' => $r1['add_type_val2'] + $r2['add_type_val2'],
            'add_type3' => 3, 'add_type_val3' => $r1['add_type_val3'] + $r2['add_type_val3'],
            'add_type4' => 4, 'add_type_val4' => $r1['add_type_val4'] + $r2['add_type_val4'],
        ], $res['general']['achInfo']);
        $this->assertSame(['hammer' => 12, 'hammer_quality' => Refine::promote(12)['type']], array_intersect_key($res['general']['eid1'], ['hammer' => 0, 'hammer_quality' => 0]));
        $this->assertSame([7, 11], [$res['general']['eid4']['equipid'], $res['general']['eid4']['hammer']]);
        $this->assertSame(-3, $this->request($p['auth'], 'refine', 'get_achieve_info', ['gid' => 999999])['status']);
    }

    // ---------------------------------------------------------------------------------------------
    // 名将谱 / 名将成就
    // ---------------------------------------------------------------------------------------------

    public function testGeneralBookIndexListsAndLevelup(): void
    {
        $p = $this->player(['level' => 80]);
        $uid = $p['uid'];
        foreach (['index', 'get_book_list', 'get_achievement_list', 'levelup'] as $m) {
            $this->assertSame(-1, $this->request($p['auth'], 'general_book', $m, ['id' => 1])['status'], $m);
        }
        // 80 级获得武将不重算；81 级起获得武将时重算（邓艾 + 姜维 → 达成「宿命之敌」）
        General::createFromTemplate($uid, 74);
        $this->assertSame([], GeneralBook::ownAddition($uid));
        DB::table('member')->where('uid', $uid)->update(['level' => 81]);
        General::createFromTemplate($uid, 72);
        $this->assertSame(['uid' => $uid, 'bid' => 1, 'quality' => 1], GeneralBook::ownAchievement($uid, 1));
        $q1 = BookAddition::ROWS[0];
        $this->assertSame([1, 4, 4, 240], [$q1['addtype1'], $q1['addvalue1'], $q1['addtype2'], $q1['addvalue2']]);
        $addition = GeneralBook::ownAddition($uid);
        // 单将加成：邓艾武力 4、姜维武力 8；成就 1 品：武力 4、物攻 240
        $this->assertSame([Book::ROWS[74]['addvalue'] + Book::ROWS[72]['addvalue'] + 4, 240], [$addition['wuli'], $addition['attack']]);

        // 打开名将谱：当天第一次重算加成并记标志
        DB::table('own_addition')->where('uid', $uid)->update(['wuli' => 0]);
        $res = $this->request($p['auth'], 'general_book', 'index', ['perpage' => 18]);
        $this->assertSame(1, $res['status']);
        $this->assertSame(['uid' => $uid, 'wuli' => 16, 'attack' => 240], $res['addition']);
        $this->assertSame([1, 18, 9, 156, 0, 0], [$res['page'], $res['perpage'], $res['pages'], $res['count'], $res['_happy_open'], $res['_happy_cd']]);
        $this->assertCount(18, $res['book']);
        $sorted = array_values(Book::ROWS);
        usort($sorted, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);
        $this->assertSame(array_column(array_slice($sorted, 0, 18), 'id'), array_column($res['book'], 'id'));
        $page = intdiv((int)array_search(74, array_column($sorted, 'id'), true), 9) + 1;
        $book = $this->request($p['auth'], 'general_book', 'get_book_list', ['page' => $page])['book'];
        $deng = array_values(array_filter($book, static fn (array $b): bool => $b['id'] === 74))[0];
        $this->assertSame(['id' => 74, 'name' => '邓艾', 'star' => 8, 'addtype' => 1, 'addvalue' => 4, 'sort' => 35, 'isown' => 1, 'image' => $deng['info']['image'],
            'info' => ['id' => 74, 'name' => '邓艾', 'image' => $deng['info']['image']]], $deng);
        // 同一天再打开不重算
        DB::table('own_addition')->where('uid', $uid)->update(['wuli' => 1]);
        $this->assertSame(1, $this->request($p['auth'], 'general_book', 'index')['addition']['wuli']);
        // 百分比加成类型带 %
        $percent = array_values(array_filter(Book::ROWS, static fn (array $b): bool => in_array($b['addtype'], [9, 10, 11, 13, 14, 15], true)));
        if ($percent !== []) {
            $page = intdiv(array_search($percent[0]['id'], array_column($sorted, 'id'), true), 9) + 1;
            $book = $this->request($p['auth'], 'general_book', 'get_book_list', ['page' => $page])['book'];
            $hit = array_values(array_filter($book, static fn (array $b): bool => $b['id'] === $percent[0]['id']))[0];
            $this->assertSame($percent[0]['addvalue'] . '%', $hit['addvalue']);
        }
        // 页码超出取最后一页
        $res = $this->request($p['auth'], 'general_book', 'get_book_list', ['page' => 100]);
        $this->assertSame([18, 9, 156, 3], [$res['page'], $res['perpage'], $res['count'], count($res['book'])]);

        // 成就列表：已拥有的排最前
        TraderLog::addJade($uid, 1, 7);
        $res = $this->request($p['auth'], 'general_book', 'get_achievement_list');
        $this->assertSame(1, $res['status']);
        $this->assertSame(['1' => 7, '2' => 0, '3' => 0], $res['jade']);
        $first = $res['achievement'][0];
        $this->assertSame([1, 1, 1, 0, 3], [$first['id'], $first['complete'], $first['quality'], $first['ismax'], $first['paytype']]);
        $this->assertSame(['quality' => 1, 'addtype1' => 1, 'addtype2' => 4, 'addtype3' => 0, 'addvalue1' => 4, 'addvalue2' => 240, 'addvalue3' => 0], $first['addition']);
        $this->assertSame([250, 500], [$first['levelup']['now']['number'], $first['levelup']['next']['number']]);
        $this->assertCount(6, $first['preview']);
        $this->assertSame(['id' => 74, 'name' => '邓艾', 'image' => $first['gid1']['image'], 'isown' => 1], $first['gid1']);
        $this->assertCount(279, $res['achievement']);
        $this->assertSame([0, 1, []], [$res['achievement'][1]['complete'], $res['achievement'][1]['quality'], $res['achievement'][1]['levelup']]);

        // 升级：1 品升 2 品花紫宝石 250
        $this->assertSame(-2, $this->request($p['auth'], 'general_book', 'levelup', ['id' => 2])['status']);
        $this->assertSame(-5, $this->request($p['auth'], 'general_book', 'levelup', ['id' => 1])['status']);
        TraderLog::addJade($uid, 3, 260);
        $res = $this->request($p['auth'], 'general_book', 'levelup', ['id' => 1]);
        $this->assertSame(1, $res['status']);
        $this->assertSame(['3' => 10], $res['jade']);
        $this->assertSame(['wuli' => 12 + 10, 'attack' => 600], $res['addition']);
        $this->assertSame([0, 2, 3], [$res['ismax'], $res['levelup']['quality'], $res['next']['quality']]);
        $this->assertSame(2, GeneralBook::ownAchievement($uid, 1)['quality']);
        $this->assertSame(['wuli' => 22, 'attack' => 600], array_intersect_key(GeneralBook::ownAddition($uid), ['wuli' => 0, 'attack' => 0]));
        $log = DB::table('own_achievement_log')->where('uid', $uid)->first();
        $this->assertSame([1, 1, 2, 3, 250], [(int)$log['bid'], (int)$log['quality1'], (int)$log['quality2'], (int)$log['type'], (int)$log['jade']]);
        // 满级
        DB::table('own_achievement')->where('uid', $uid)->update(['quality' => 6]);
        $this->assertSame(2, $this->request($p['auth'], 'general_book', 'levelup', ['id' => 1])['status']);
        DB::table('own_achievement')->where('uid', $uid)->update(['quality' => 5]);
        TraderLog::addJade($uid, 3, 1500);
        $res = $this->request($p['auth'], 'general_book', 'levelup', ['id' => 1]);
        $this->assertSame([1, 1, []], [$res['status'], $res['ismax'], $res['next']]);
    }

    public function testAchievementBonusesInTeamGeneralInfoAndEngine(): void
    {
        $p = $this->player(['level' => 80]);
        [$uid, $gid] = [$p['uid'], $p['gid']];
        GeneralBook::saveAddition($uid, [
            'wuli' => 7, 'zhili' => 8, 'tili' => 3, 'attack' => 40, 'defense' => 11, 'pattack' => 50, 'pdefense' => 12, 'blood' => 500,
            'dodge' => 2, 'crit' => 3, 'penetrate' => 4, 'power' => 9, 'mingzhong' => 4, 'jingzhun' => 6, 'kcrit' => 2,
        ]);
        $base = TeamBuilder::forMember($uid)['team'];
        $pos = (int)array_key_first($base);
        $this->assertArrayNotHasKey('addition_arr', $base[$pos]);
        $baseInfo = $this->request($p['auth'], 'general', 'get_info', ['gid' => $gid])['info'];

        // 81 级：名将成就加成计入组队与将领界面
        DB::table('member')->where('uid', $uid)->update(['level' => 81]);
        $unit = TeamBuilder::forMember($uid)['team'][$pos];
        $this->assertSame(GeneralBook::ownAddition($uid), $unit['addition_arr']);
        $this->assertSame([7, 8, 3, 9, 2, 11, 12, 500, 40, 4], [
            $unit['wuli'] - $base[$pos]['wuli'], $unit['zhili'] - $base[$pos]['zhili'], $unit['tili'] - $base[$pos]['tili'], $unit['power'] - $base[$pos]['power'],
            $unit['kcrit'] - $base[$pos]['kcrit'], $unit['defense1'] - $base[$pos]['defense1'], $unit['defense2'] - $base[$pos]['defense2'],
            $unit['all_blood'] - $base[$pos]['all_blood'], $unit['attack'] - $base[$pos]['attack'], $unit['mingzhong'] - $base[$pos]['mingzhong'],
        ]);
        $info = $this->request($p['auth'], 'general', 'get_info', ['gid' => $gid])['info'];
        $this->assertSame([$baseInfo['wuliup'] + 7, $baseInfo['zhiliup'] + 8, $baseInfo['tiliup'] + 3], [$info['wuliup'], $info['zhiliup'], $info['tiliup']]);
        $row = DB::table('general')->where('id', $gid)->first();
        $this->assertSame(LegacyStats::infoHp(
            LegacyStats::baseHp((int)$row['level'], 0), 0, 0, 0, (int)$row['tili'], (int)$row['tiliup'] + 3,
            (float)Talent::ROWS[(int)$row['talentid']]['blood'], flatAdd: 500,
        ), $info['hp']);

        // 推演：暴击率 / 闪避率 / 识破率（只对有体力字段的玩家单位）
        $atk = ['type' => 1, 'attack' => 300, 'talentid' => 1];
        $def = ['type' => 1, 'talentid' => 1, 'defense1' => 0];
        $log = self::firstAction($atk + ['tili' => 0, 'addition_arr' => ['crit' => 100]], $def, [99, 99]);
        $this->assertSame([1, 450], [$log['hit'], $log['hurt'][0]['blood']]);
        $log = self::firstAction($atk + ['addition_arr' => ['crit' => 100]], $def, [99, 99]);
        $this->assertSame(0, $log['hit']);
        $this->assertSame(-1, self::firstAction($atk, $def + ['tili' => 0, 'addition_arr' => ['dodge' => 100]], [99, 99])['attacktype']);
        $this->assertSame(0, self::firstAction($atk, $def + ['tili' => 0, 'addition_arr' => ['dodge' => 99]], [99, 99])['attacktype']);
        $this->assertSame(-2, self::firstAction(['type' => 2] + $atk, $def + ['tili' => 0, 'addition_arr' => ['penetrate' => 100]], [99, 99])['attacktype']);
    }

    /**
     * 单位上的进阶行（BattleEngine 读取的四个技能加成，未给的为 0）
     *
     * @param array<string, float> $values
     * @return array<string, float>
     */
    private static function adv(array $values): array
    {
        return $values + ['skill1_value' => 0.0, 'skill2_value' => 0.0, 'skill3_value' => 0.0, 'skill4_value' => 0.0];
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

    /**
     * 找一个 mt_srand 种子，使接下来的 mt_rand 序列满足 $want
     *
     * @param callable(): bool $want
     */
    private static function seed(callable $want): int
    {
        for ($seed = 1; $seed < 100000; $seed++) {
            mt_srand($seed);
            if ($want()) {
                return $seed;
            }
        }
        self::fail('找不到满足条件的种子');
    }
}
