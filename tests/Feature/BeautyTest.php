<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\Handler\HandlerRegistry;
use Hanjiang\Http\Router;
use Hanjiang\MasterData\BeautyDialogue;
use Hanjiang\MasterData\BeautySkill;
use Hanjiang\Middleware\AuthMiddleware;
use Hanjiang\Middleware\CorsMiddleware;
use Hanjiang\Middleware\DailyCronMiddleware;
use Hanjiang\Middleware\GameProtocolMiddleware;
use Hanjiang\Middleware\ResponseEnvelopeMiddleware;
use Hanjiang\Middleware\ThrottleMiddleware;
use Hanjiang\Model\Beauty;
use Hanjiang\Model\MemberIntegral;
use Hanjiang\Model\TraderLog;
use Hanjiang\Tests\Support\ScriptedRandomEngine;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use PHPUnit\Framework\TestCase;
use Random\Randomizer;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * 养成第二批：铜雀台美人（c=beauty，旧版 controllers/beauty.php + models/beauty_model.php），
 * 他人美人信息（c=information&m=get_all_beauty_info），以及美人加成在将领界面 / 组队 / 推演中的计入
 */
final class BeautyTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    private static function own(int $uid, int $bid): array
    {
        return Beauty::own($uid, $bid);
    }

    /**
     * @return list<int>
     */
    private static function chipCounts(int $uid, int $bid): array
    {
        return array_column(Beauty::chips($uid, $bid), 'chip');
    }

    /**
     * @return list<string>
     */
    private static function says(int $bid, int $type): array
    {
        return array_column(Beauty::dialogue($bid, $type), 'content');
    }

    private static function skillValue(int $bid, int $property, int $level): int
    {
        foreach (BeautySkill::ROWS as $row) {
            if ($row['beauty_id'] === $bid && $row['skill_property'] === $property && $row['skill_level'] === $level) {
                return $row['skill_value'];
            }
        }
        self::fail("no skill {$bid}/{$property}/{$level}");
    }

    private static function clearLocks(int $uid): void
    {
        foreach (['_level_up_lock', '_reflush_lock', '_lott_key'] as $key) {
            Redis::del($key . $uid);
        }
    }

    // ---------------------------------------------------------------------------------------------
    // 主页 / 激活 / 休息
    // ---------------------------------------------------------------------------------------------

    public function testIndexWakeupRestAndGates(): void
    {
        $p = $this->player(['level' => 59, 'gold' => 1000]);
        $uid = $p['uid'];
        foreach (['index', 'wakeup_beauty', 'rest_beauty', 'level_up', 'cancel_levelup', 'active_index', 'gift', 'give_flower', 'conversion_chip', 'action_special_reward', 'loot_action'] as $m) {
            $this->assertSame(['status' => -1, 'msg' => '等级不足'], $this->request($p['auth'], 'beauty', $m, ['beauty_id' => 1, 'type' => 1]), $m);
        }
        $this->assertSame(['status' => -1, 'msg' => '等级不足'], $this->request($p['auth'], 'beauty', 'get_beauty_info', ['beauty_id' => 1]));

        DB::table('member')->where('uid', $uid)->update(['level' => 60]);
        // 没有激活的美人：显示 1 号美人（祝融）的模板信息
        $res = $this->request($p['auth'], 'beauty', 'index');
        $this->assertSame(1, $res['status']);
        $this->assertCount(7, $res['beauty_info']['active']);
        $this->assertSame(['beauty_id' => 1, 'name' => '祝融', 'active' => 0, 'quality' => 1], $res['beauty_info']['active'][0]);
        $this->assertSame([
            'beauty_id' => 1, 'beauty_quality' => 1, 'beauty_level' => 1, 'beauty_active' => 0, 'skill_cd' => 0, 'is_active' => 1,
            'battle_cd' => 0, 'up_status' => 1, 'sub_rose' => 5, 'max_num' => 1, 'buy_rose' => 20, 'up_cd' => 0, 'up_succ' => 0, 'conversion_status' => 0,
        ], $res['beauty_info']['info']);
        // 技能：祝融 1 武力 / 17 宝石碎片 / 7 识破（带 %），1 级时只解锁第一个
        $skill = $res['beauty_info']['skill'];
        $this->assertSame(['id' => 1, 'name' => '勇武', 'content' => '武力增加', 'value' => 10, 'lock' => 1, 'next_value' => 13], $skill[0]);
        $this->assertSame([0, 21], [$skill[1]['lock'], $skill[1]['lock_msg']]);
        $this->assertSame([7, '20%', 0, 61], [$skill[2]['id'], $skill[2]['value'], $skill[2]['lock'], $skill[2]['lock_msg']]);
        $this->assertCount(6, $res['beauty_info']['chip']);
        $this->assertSame(['id' => 1, 'beauty_id' => 1, 'chip_name' => '祝融碎片一', 'chip_id' => 1, 'own' => 0, 'chip' => 0], $res['beauty_info']['chip'][0]);
        $this->assertSame(['beauty_id' => 1, 'ratio' => 0, 'skill' => 25, 'quality' => 0, 'rose' => 0, 'diamond' => 0], $res['beauty_info']['happy']);
        $this->assertSame(['gold' => 50, 'times' => 5], $res['beauty_info']['battle']);
        $this->assertSame(['id' => 17, 'level' => 1, 'num' => 0, 'allnum' => 0, 'lock' => 0, 'lock_msg' => 21, 'skill_name' => '流光溢彩'], $res['beauty_info']['special_skill']);
        $this->assertContains($res['say'], self::says(1, 1));

        // 私语 / 详情参数
        $say = $this->request($p['auth'], 'beauty', 'get_say_info', ['beauty_id' => 3]);
        $this->assertSame(1, $say['status']);
        $this->assertContains($say['say'], self::says(3, 1));
        $this->assertSame(-1, $this->request($p['auth'], 'beauty', 'get_say_info')['status']);
        $this->assertSame(['status' => -2, 'msg' => '参数错误'], $this->request($p['auth'], 'beauty', 'get_beauty_info'));
        $this->assertSame(-2, $this->request($p['auth'], 'beauty', 'get_beauty_info', ['beauty_id' => 99])['status']);

        // 激活：1 号美人（建拥有记录与 6 个碎片），60 级只能激活一个
        $this->assertSame(['status' => -3, 'msg' => '获取美人模板表失败'], $this->request($p['auth'], 'beauty', 'wakeup_beauty', ['beauty_id' => 99]));
        $wake = $this->request($p['auth'], 'beauty', 'wakeup_beauty', ['beauty_id' => 1]);
        $this->assertSame([1, 1000, 1], [$wake['status'], $wake['all_gold'], $wake['wakeup_num']]);
        $this->assertContains($wake['say'], self::says(1, 2));
        $own = self::own($uid, 1);
        $this->assertSame(['祝融', 1, 1, 1, 17, 7, 1, 0], [$own['beauty_name'], $own['beauty_level'], $own['quality'], $own['skill1'], $own['skill2'], $own['skill3'], $own['open_status'], $own['up_status']]);
        $this->assertSame([0, 0, 0, 0, 0, 0], self::chipCounts($uid, 1));
        $this->assertSame(['status' => -8, 'msg' => '美人已经激活'], $this->request($p['auth'], 'beauty', 'wakeup_beauty', ['beauty_id' => 1]));
        $this->assertSame(['status' => -4, 'msg' => '激活美人已达上限'], $this->request($p['auth'], 'beauty', 'wakeup_beauty', ['beauty_id' => 2]));
        // 90 级可以激活第二个
        DB::table('member')->where('uid', $uid)->update(['level' => 90]);
        $this->assertSame(2, $this->request($p['auth'], 'beauty', 'wakeup_beauty', ['beauty_id' => 2])['wakeup_num']);

        // 主页默认显示激活中 id 最小的美人
        $res = $this->request($p['auth'], 'beauty', 'index');
        $this->assertSame([1, 1, 2], [$res['beauty_info']['info']['beauty_id'], $res['beauty_info']['info']['beauty_active'], $res['beauty_info']['info']['max_num']]);
        $this->assertSame([1, 1, 0], array_column(array_slice($res['beauty_info']['active'], 0, 3), 'active'));
        $this->assertSame(['chip' => 0, 'chip_name' => '祝融碎片一', 'own' => 0], array_intersect_key($res['beauty_info']['chip'][0], ['own' => 1, 'chip' => 1, 'chip_name' => 1]));
        $info2 = $this->request($p['auth'], 'beauty', 'get_beauty_info', ['beauty_id' => 2]);
        $this->assertSame([1, 2, 1], [$info2['status'], $info2['beauty_info']['info']['beauty_id'], $info2['beauty_info']['info']['beauty_active']]);

        // 休息：没有此美人 -3、休息后再休息 -4
        $this->assertSame(['status' => -3, 'msg' => '用户id参数失败'], $this->request($p['auth'], 'beauty', 'rest_beauty', ['beauty_id' => 5]));
        $rest = $this->request($p['auth'], 'beauty', 'rest_beauty', ['beauty_id' => 2]);
        $this->assertSame([1, 1], [$rest['status'], $rest['wakeup_num']]);
        $this->assertSame(['id' => 18, 'level' => 1, 'num' => 0, 'allnum' => 0, 'lock' => 0, 'lock_msg' => 21, 'skill_name' => '威震天下'], $rest['special_skill']);
        $this->assertSame(0, self::own($uid, 2)['open_status']);
        $this->assertSame(['status' => -4, 'msg' => '美人正在休息中'], $this->request($p['auth'], 'beauty', 'rest_beauty', ['beauty_id' => 2]));
        // 休息过的美人重新激活：只改状态
        $this->assertSame(1, $this->request($p['auth'], 'beauty', 'wakeup_beauty', ['beauty_id' => 2])['status']);
        $this->assertSame(1, DB::table('beauty_own_log')->where('uid', $uid)->where('beauty_id', 2)->count());

        // 他人信息：激活中的美人与已解锁技能
        $q = $this->player();
        $all = $this->request($q['auth'], 'information', 'get_all_beauty_info', ['uid' => $uid]);
        $this->assertSame(1, $all['status']);
        $this->assertSame([
            ['beauty_id' => 1, 'beauty_name' => '祝融', 'beauty_level' => 1, 'skill_info' => [['skill_name' => '勇武', 'skill_content' => '武力增加', 'skill_value' => 10]]],
            ['beauty_id' => 2, 'beauty_name' => '甄姬', 'beauty_level' => 1, 'skill_info' => [['skill_name' => self::skillName(2, 2, 1), 'skill_content' => '智力增加', 'skill_value' => self::skillValue(2, 2, 1)]]],
        ], $all['list']);
        $this->assertSame(['status' => 1, 'list' => []], $this->request($p['auth'], 'information', 'get_all_beauty_info', ['uid' => $q['uid']]));
    }

    private static function skillName(int $bid, int $property, int $level): string
    {
        foreach (BeautySkill::ROWS as $row) {
            if ($row['beauty_id'] === $bid && $row['skill_property'] === $property && $row['skill_level'] === $level) {
                return $row['skill_name'];
            }
        }
        return '';
    }

    // ---------------------------------------------------------------------------------------------
    // 互动 / 赠礼 / 送花 / 特殊技能 / 免战
    // ---------------------------------------------------------------------------------------------

    public function testActiveGiftFlowerAndPeace(): void
    {
        $p = $this->player(['level' => 60, 'gold' => 2000, 'silver' => 150000, 'vip' => 0]);
        $uid = $p['uid'];
        $this->request($p['auth'], 'beauty', 'wakeup_beauty', ['beauty_id' => 1]);

        // 互动主页：每天 5 次，宠幸要 vip 5
        $idx = $this->request($p['auth'], 'beauty', 'active_index');
        $this->assertSame([
            'uid' => $uid, 'greeting' => 5, 'g_dateline' => 0, 'close' => 5, 'love' => 5, 'three' => 0,
            'spend_greet' => 100000, 'spend_close' => 100, 'spend_love' => 500, 'love_vip' => 5, 'status' => 1,
        ], $idx);
        $this->assertSame(['status' => -1, 'msg' => 'VIP等级不足'], $this->request($p['auth'], 'beauty', 'active_action', ['beauty_id' => 1, 'type' => 3]));
        $this->assertSame(['status' => -2, 'msg' => '参数错误'], $this->request($p['auth'], 'beauty', 'active_action', ['beauty_id' => 1, 'type' => 4]));

        // 问候：10 万银币，好感度 8，三个奖池各一份奖励
        $act = $this->request($p['auth'], 'beauty', 'active_action', ['beauty_id' => 1, 'type' => 1]);
        $this->assertSame(1, $act['status']);
        $this->assertSame([4, 0, 50000], [$act['lastnum'], $act['cd_time'], $this->memberValue($uid, 'silver') - $this->silverGain($act['reward'])]);
        $this->assertCount(3, $act['reward']);
        $this->assertSame(30, $act['reward'][0]['type']);
        $this->assertSame(['quality' => 0, 'skill' => 25, 'ratio' => 8], array_diff_key($act['happy'], ['rose' => 1]));
        $this->assertContains($act['say'], self::says(1, 5));
        $chipReward = $act['reward'][0];
        $this->assertSame($chipReward['num'], Beauty::chip($uid, $chipReward['beauty_id'], $chipReward['chip_id'])['chip']);
        $this->assertSame(4, (int)DB::table('beauty_active_log')->where('uid', $uid)->value('greeting'));
        // 银币不足
        DB::table('member')->where('uid', $uid)->update(['silver' => 10]);
        $this->assertSame(['status' => -4, 'msg' => '银币不足'], $this->request($p['auth'], 'beauty', 'active_action', ['beauty_id' => 1, 'type' => 1]));
        // 跨天：问候时间不是今天时三种次数重置
        DB::table('beauty_active_log')->where('uid', $uid)->update(['greeting' => 0, 'g_dateline' => time() - 86400 * 2]);
        $this->assertSame(5, $this->request($p['auth'], 'beauty', 'active_index')['greeting']);

        // 赠礼：500 元宝 +50 好感度；钻石不足 -6
        $gift = $this->request($p['auth'], 'beauty', 'gift', ['beauty_id' => 1, 'type' => 1]);
        $this->assertSame([1, 1500 - $this->goldGain($act['reward'])], [$gift['status'], $gift['gold'] - $this->goldGain($act['reward'])]);
        // 同一秒内衰减算出 +1（旧版公式 ratio - ceil(0 / 3600) + 1）：8 + 1 + 50
        $this->assertSame(59, self::own($uid, 1)['ratio']);
        $this->assertSame(['quality' => 2, 'skill' => 75, 'ratio' => 59], array_diff_key($gift['happy'], ['rose' => 1]));
        $this->assertContains($gift['say'], self::says(1, 8));
        $this->assertSame(['status' => -6, 'msg' => '钻石不足'], $this->request($p['auth'], 'beauty', 'gift', ['beauty_id' => 1, 'type' => 3]));
        MemberIntegral::change($uid, 30, 'beauty_diamond', 1, 'test', 'test');
        $gift3 = $this->request($p['auth'], 'beauty', 'gift', ['beauty_id' => 1, 'type' => 3]);
        $this->assertSame([1, 12 + $this->diamondGain($act['reward'])], [$gift3['status'], $gift3['diamond_num']]);
        $this->assertSame(100, self::own($uid, 1)['ratio']);
        // 好感度已满（没有衰减时 >= 100）
        $this->assertSame(['status' => -5, 'msg' => '美人好感度已满'], $this->request($p['auth'], 'beauty', 'gift', ['beauty_id' => 1, 'type' => 1]));
        // 没拥有的美人赠礼会建休息中的记录
        $this->assertSame(1, $this->request($p['auth'], 'beauty', 'gift', ['beauty_id' => 4, 'type' => 1])['status']);
        $this->assertSame([0, 1, 50], [self::own($uid, 4)['open_status'], self::own($uid, 4)['quality'], self::own($uid, 4)['ratio']]);

        // 买玫瑰花 / 送花（技能生效 24 小时）
        $rose = (int)MemberIntegral::get($uid)['beauty_rose'];
        $buy = $this->request($p['auth'], 'beauty', 'buy_rose');
        $this->assertSame([1, $rose + 1], [$buy['status'], $buy['rose']]);
        $this->assertSame(1, DB::table('beauty_buyrose_log')->where('uid', $uid)->count());
        DB::table('member_integral')->where('uid', $uid)->update(['beauty_rose' => 4]);
        $this->assertSame(['status' => 2, 'msg' => '玫瑰花不足'], $this->request($p['auth'], 'beauty', 'give_flower', ['beauty_id' => 1]));
        $this->assertSame(['status' => 4, 'msg' => '美人未激活'], $this->request($p['auth'], 'beauty', 'give_flower', ['beauty_id' => 4]));
        DB::table('member_integral')->where('uid', $uid)->update(['beauty_rose' => 12]);
        $flower = $this->request($p['auth'], 'beauty', 'give_flower', ['beauty_id' => 1]);
        $this->assertSame([1, 86400, 7], [$flower['status'], $flower['skill_cd'], $flower['rose']]);
        $own = self::own($uid, 1);
        $this->assertSame([86400, 1], [$own['effect_end_time'] - $own['effect_time'], $own['wakeup_num']]);
        // 再送一次：在原结束时间上累加，不再增加 wakeup_num
        $this->assertSame(86400 * 2, $this->request($p['auth'], 'beauty', 'give_flower', ['beauty_id' => 1])['skill_cd']);
        $this->assertSame(1, self::own($uid, 1)['wakeup_num']);

        // 免战：50 元宝一小时，每天 5 次
        $before = $this->memberValue($uid, 'gold');
        $peace = $this->request($p['auth'], 'beauty', 'peace');
        $this->assertSame([1, 3600, 4, $before - 50], [$peace['status'], $peace['cd_time'], $peace['times'], $peace['all_gold']]);
        $this->assertSame([1, 7200, 3], array_values(array_intersect_key($this->request($p['auth'], 'beauty', 'peace'), ['status' => 1, 'cd_time' => 1, 'times' => 1])));
        DB::table('beauty_info')->where('uid', $uid)->update(['times' => 0]);
        $this->assertSame(['status' => -3, 'msg' => '购买免战次数不足'], $this->request($p['auth'], 'beauty', 'peace'));
        DB::table('member')->where('uid', $uid)->update(['gold' => 10]);
        DB::table('beauty_info')->where('uid', $uid)->update(['times' => 2]);
        $this->assertSame(['status' => -2, 'msg' => '元宝不足'], $this->request($p['auth'], 'beauty', 'peace'));
        $this->assertContains($this->request($p['auth'], 'beauty', 'index')['beauty_info']['info']['battle_cd'], [7198, 7199, 7200]);
    }

    /**
     * @param list<array<string, mixed>> $reward
     */
    private function silverGain(array $reward): int
    {
        $sum = 0;
        foreach ($reward as $r) {
            $sum += $r['type'] === 2 ? $r['num'] : 0;
        }
        return $sum;
    }

    /**
     * @param list<array<string, mixed>> $reward
     */
    private function goldGain(array $reward): int
    {
        return 0;
    }

    /**
     * @param list<array<string, mixed>> $reward
     */
    private function diamondGain(array $reward): int
    {
        $sum = 0;
        foreach ($reward as $r) {
            $sum += $r['type'] === 32 ? $r['num'] : 0;
        }
        return $sum;
    }

    public function testSpecialSkillRewardAndAutoCollect(): void
    {
        $p = $this->player(['level' => 60, 'gold' => 1000]);
        $uid = $p['uid'];
        // 大乔：技能 1 为 13 银币产出（1 级开放），24 小时 50000
        $this->request($p['auth'], 'beauty', 'wakeup_beauty', ['beauty_id' => 4]);
        $this->assertSame(['status' => -4, 'msg' => '技能未生效'], $this->request($p['auth'], 'beauty', 'action_special_reward', ['beauty_id' => 4]));
        $now = time();
        // 好感度 50（技能效果 75%），生效 1 小时
        Beauty::updateOwn($uid, 4, ['ratio' => 50, 'ratio_time' => $now, 'effect_time' => $now - 3600, 'effect_end_time' => $now + 82800, 'wakeup_num' => 1]);
        $silver = $this->memberValue($uid, 'silver');
        $res = $this->request($p['auth'], 'beauty', 'action_special_reward', ['beauty_id' => 4]);
        $this->assertSame(1, $res['status']);
        $this->assertContains($res['num'], [1562, 1563]);
        $this->assertSame($silver + $res['num'], $this->memberValue($uid, 'silver'));
        $log = DB::table('beauty_speical_reward_log')->where('uid', $uid)->first();
        $this->assertSame([4, 13, $res['num'], 1], [(int)$log['beauty_id'], (int)$log['type'], (int)$log['num'], (int)$log['wakeup_num']]);
        $this->assertSame(['status' => -5, 'msg' => '当前没有可领取的奖励'], $this->request($p['auth'], 'beauty', 'action_special_reward', ['beauty_id' => 4]));

        // 技能已到期：进入主页时按到期时的好感度自动发放剩余产出（从上次领取算到结束时间），发信并清空生效时间
        DB::table('beauty_speical_reward_log')->where('uid', $uid)->update(['dateline' => $now - 7200]);
        Beauty::updateOwn($uid, 4, ['effect_time' => $now - 86400, 'effect_end_time' => $now - 3600, 'ratio_time' => $now - 3600]);
        $silver = $this->memberValue($uid, 'silver');
        $index = $this->request($p['auth'], 'beauty', 'index');
        $expect = (int)ceil(50000 / 86400 * 3600 * 0.75);
        $this->assertSame(0, $index['beauty_info']['special_skill']['num']);
        $this->assertSame($silver + $expect, $this->memberValue($uid, 'silver'));
        $this->assertSame([0, 0], [self::own($uid, 4)['effect_time'], self::own($uid, 4)['effect_end_time']]);
        $this->assertSame(2, DB::table('beauty_speical_reward_log')->where('uid', $uid)->count());
        $messages = Redis::getJson('user_messages:' . $uid) ?: [];
        $this->assertSame('美人大乔的的技能已经失效，期间您一共获得银币' . $expect . ', 请您笑纳', end($messages)['message']);
        // 再进一次不重复发放
        $this->request($p['auth'], 'beauty', 'index');
        $this->assertSame($silver + $expect, $this->memberValue($uid, 'silver'));
    }

    // ---------------------------------------------------------------------------------------------
    // 升级 / 进阶 / 转化
    // ---------------------------------------------------------------------------------------------

    public function testLevelUpAdvanceAndConversion(): void
    {
        $p = $this->player(['level' => 60, 'gold' => 1000]);
        $uid = $p['uid'];
        $this->request($p['auth'], 'beauty', 'wakeup_beauty', ['beauty_id' => 1]);

        // 碎片不足：-4（防刷锁不解锁，同旧版）
        $res = $this->request($p['auth'], 'beauty', 'level_up', ['beauty_id' => 1, 'type' => 1]);
        $this->assertSame([-4, '集齐六种碎片方可开始升级'], [$res['status'], $res['msg']]);
        $this->assertCount(6, $res['chip']);
        $this->assertSame(['status' => -8, 'msg' => '操作太快了！'], $this->request($p['auth'], 'beauty', 'level_up', ['beauty_id' => 1, 'type' => 1]));
        self::clearLocks($uid);

        // 集齐碎片开始升级：美人转为休息，一小时倒计时
        DB::table('beauty_own_chip')->where('uid', $uid)->where('beauty_id', 1)->update(['chip' => 2]);
        $res = $this->request($p['auth'], 'beauty', 'level_up', ['beauty_id' => 1, 'type' => 1]);
        $this->assertSame([1, 3600, 0], [$res['status'], $res['cd'], $res['wakeup_num']]);
        $this->assertArrayHasKey('special_skill', $res);
        $own = self::own($uid, 1);
        $this->assertSame([0, 1], [$own['open_status'], $own['up_status']]);
        $this->assertSame(['status' => -4, 'msg' => '升级过程中不能进行操作'], $this->request($p['auth'], 'beauty', 'wakeup_beauty', ['beauty_id' => 1]));
        $this->assertSame(['status' => -4, 'msg' => '升级过程中不能进行操作'], $this->request($p['auth'], 'beauty', 'gift', ['beauty_id' => 1, 'type' => 1]));
        // 倒计时中再请求：只返回 cd
        $res = $this->request($p['auth'], 'beauty', 'level_up', ['beauty_id' => 1, 'type' => 2]);
        $this->assertSame(1, $res['status']);
        $this->assertGreaterThan(3590, $res['cd']);
        $this->assertArrayNotHasKey('special_skill', $res);
        // 取消升级
        $this->assertSame(['status' => 1], $this->request($p['auth'], 'beauty', 'cancel_levelup', ['beauty_id' => 1]));
        $this->assertSame(['status' => -4, 'msg' => '美人不在升级状态'], $this->request($p['auth'], 'beauty', 'cancel_levelup', ['beauty_id' => 1]));

        // 重新开始并让倒计时结束：碎片各减 1，等级 + 1，返回主页信息，cd -1
        $this->request($p['auth'], 'beauty', 'level_up', ['beauty_id' => 1, 'type' => 1]);
        Beauty::updateOwn($uid, 1, ['up_dateline' => time() - 1]);
        $res = $this->request($p['auth'], 'beauty', 'level_up', ['beauty_id' => 1, 'type' => 2]);
        $this->assertSame([1, -1, 2], [$res['status'], $res['cd'], $res['beauty_info']['info']['beauty_level']]);
        $this->assertSame([1, 1, 1, 1, 1, 1], self::chipCounts($uid, 1));
        $this->assertSame([2, 0, 0], [self::own($uid, 1)['beauty_level'], self::own($uid, 1)['up_status'], self::own($uid, 1)['up_dateline']]);

        // 主页完成到时间的升级（up_succ 1）
        $this->request($p['auth'], 'beauty', 'level_up', ['beauty_id' => 1, 'type' => 1]);
        Beauty::updateOwn($uid, 1, ['up_dateline' => time() - 1]);
        $index = $this->request($p['auth'], 'beauty', 'get_beauty_info', ['beauty_id' => 1]);
        $this->assertSame([3, 1, 0], [$index['beauty_info']['info']['beauty_level'], $index['beauty_info']['info']['up_succ'], $index['beauty_info']['info']['up_cd']]);
        $this->assertSame([0, 0, 0, 0, 0, 0], self::chipCounts($uid, 1));
        // 动画已播放
        $this->assertSame(1, $this->request($p['auth'], 'beauty', 'level_up_status', ['beauty_id' => 1])['status']);
        $this->assertSame(1, self::own($uid, 1)['is_play']);

        // 进阶（20 级）：level_up 不可用，消耗按进阶表 level 20 的最后一行
        Beauty::updateOwn($uid, 1, ['beauty_level' => 20]);
        $this->assertSame(['status' => -5, 'msg' => '此美人不可升级'], $this->request($p['auth'], 'beauty', 'level_up', ['beauty_id' => 1, 'type' => 1]));
        $adv = $this->request($p['auth'], 'beauty', 'advance_index', ['beauty_id' => 1]);
        $this->assertSame(1, $adv['status']);
        $this->assertSame(['level' => 20, 'type1' => 1, 'value1' => 10000, 'type5' => 5, 'value5' => 2000, 'value6' => 100], array_intersect_key($adv['list'], ['level' => 1, 'type1' => 1, 'value1' => 1, 'type5' => 1, 'value5' => 1, 'value6' => 1]));
        $this->assertSame([
            ['name' => '武力增加', 'value' => self::skillValue(1, 1, 21) - self::skillValue(1, 1, 20)],
            ['name' => '解锁技能：流光溢彩', 'value' => 0],
        ], $adv['add_info']);
        $this->assertSame(['type1' => 1, 'value1' => 0, 'type2' => 2, 'value2' => 0, 'type3' => 3, 'value3' => $this->memberValue($uid, 'soul'),
            'type4' => 4, 'value4' => $this->memberValue($uid, 'reputation'), 'type5' => 5, 'value5' => 0, 'type6' => 6, 'value6' => 0], $adv['info']);
        $this->assertSame(['status' => -4, 'msg' => '获取美人失败'], $this->request($p['auth'], 'beauty', 'advance_index', ['beauty_id' => 5]));

        $this->assertSame(['status' => 2, 'msg' => '黄宝石不足'], $this->request($p['auth'], 'beauty', 'advance_action', ['beauty_id' => 1]));
        TraderLog::addJade($uid, 2, 10000);
        TraderLog::addJade($uid, 3, 5000);
        DB::table('member')->where('uid', $uid)->update(['soul' => 100005, 'reputation' => 7]);
        $this->assertSame(['status' => 6, 'msg' => '玫瑰花不足'], $this->request($p['auth'], 'beauty', 'advance_action', ['beauty_id' => 1]));
        DB::table('member_integral')->where('uid', $uid)->update(['beauty_rose' => 2001, 'beauty_diamond' => 100]);
        // 进阶等级可转化碎片：6 个碎片各减 1，好感度 + 8，按宠幸奖励表抽奖
        $this->assertSame(['status' => -5, 'msg' => '美人碎片不足'], $this->request($p['auth'], 'beauty', 'conversion_chip', ['beauty_id' => 1]));
        DB::table('beauty_own_chip')->where('uid', $uid)->where('beauty_id', 1)->update(['chip' => 1]);
        $conv = $this->request($p['auth'], 'beauty', 'conversion_chip', ['beauty_id' => 1]);
        $this->assertSame(1, $conv['status']);
        $this->assertCount(3, $conv['reward']);
        $this->assertSame(1, $conv['reward'][0]['beauty_id']);
        $chipsAfter = self::chipCounts($uid, 1);
        $this->assertSame(1, array_sum($chipsAfter));
        $this->assertSame($chipsAfter, array_column($conv['chip'], 'chip'));

        Beauty::updateOwn($uid, 1, ['open_status' => 1]);
        // 资源扣除记日志后算出的值可能因转化奖励变化，按进阶前读出的数值核对
        $rose = (int)MemberIntegral::get($uid)['beauty_rose'];
        $diamond = (int)MemberIntegral::get($uid)['beauty_diamond'];
        $yellow = TraderLog::jade($uid, 2);
        $purple = TraderLog::jade($uid, 3);
        $soul = $this->memberValue($uid, 'soul');
        $res = $this->request($p['auth'], 'beauty', 'advance_action', ['beauty_id' => 1]);
        $this->assertSame(1, $res['status']);
        $this->assertSame([21, 2, 0], [self::own($uid, 1)['beauty_level'], self::own($uid, 1)['quality'], self::own($uid, 1)['open_status']]);
        $this->assertSame([0, 21, 0], [$res['wakeup_num'], $res['beauty_info']['info']['beauty_level'], $res['beauty_info']['info']['beauty_active']]);
        $this->assertSame(
            [$yellow - 10000, $purple - 5000, $soul - 100000, $rose - 2000, $diamond - 100],
            [TraderLog::jade($uid, 2), TraderLog::jade($uid, 3), $this->memberValue($uid, 'soul'), (int)MemberIntegral::get($uid)['beauty_rose'], (int)MemberIntegral::get($uid)['beauty_diamond']]
        );
        // 21 级：第二个技能解锁，特殊技能（探宝）开放
        $this->assertSame([1, 1], [$res['beauty_info']['skill'][1]['lock'], $res['beauty_info']['special_skill']['lock']]);
        $this->assertSame(['status' => -5, 'msg' => '此美人不可进阶'], $this->request($p['auth'], 'beauty', 'advance_action', ['beauty_id' => 1]));
        // 80 级满级显示 3，进阶表没有 80 级：返回 advance_return 的 -6
        Beauty::updateOwn($uid, 1, ['beauty_level' => 80]);
        $this->assertSame(3, $this->request($p['auth'], 'beauty', 'get_beauty_info', ['beauty_id' => 1])['beauty_info']['info']['up_status']);
        $this->assertSame(['status' => -6, 'msg' => '获取美人进阶表失败'], $this->request($p['auth'], 'beauty', 'advance_action', ['beauty_id' => 1]));
        $this->assertSame(80, self::own($uid, 1)['beauty_level']);
    }

    // ---------------------------------------------------------------------------------------------
    // 掠夺
    // ---------------------------------------------------------------------------------------------

    public function testLootFlow(): void
    {
        // 抢夺方 80 级；被抢方 101 级（高 21 级：成功率 90%），阵法清空（空队伍，抢夺方必胜）
        $a = $this->player(['level' => 80, 'gold' => 1000]);
        $b = $this->player(['level' => 101]);
        $c = $this->player(['level' => 59]);
        [$ua, $ub] = [$a['uid'], $b['uid']];
        foreach ([$b, $c] as $x) {
            $this->request($x['auth'], 'beauty', 'gift', ['beauty_id' => 1, 'type' => 1]);
            Beauty::addChipInfo($x['uid'], 1, time());
        }
        $this->request($b['auth'], 'beauty', 'wakeup_beauty', ['beauty_id' => 1]);
        Beauty::addChipInfo($ub, 1, time());
        DB::table('beauty_own_chip')->where('uid', $ub)->where('beauty_id', 1)->whereIn('chip_id', [1, 3])->update(['chip' => 1]);
        DB::table('beauty_own_chip')->where('uid', $c['uid'])->where('beauty_id', 1)->update(['chip' => 1]);
        // 其他用例留下的同一碎片持有者清掉，对手列表只剩 B（C 不到 60 级被剔除）
        DB::table('beauty_own_chip')->where('beauty_id', 1)->whereNotIn('uid', [$ub, $c['uid']])->update(['chip' => 0]);
        DB::table('matrix')->where('uid', $ub)->update(array_fill_keys(array_map(static fn (int $i): string => 'position' . $i, range(1, 9)), 0));

        // 抢夺主页：60 级以下的持有者不出现；对手列表保存起来
        $this->assertSame(['status' => -4, 'msg' => '参数错误'], $this->request($a['auth'], 'beauty', 'loot_index', ['chip_id' => 1]));
        $this->assertSame(['status' => -7, 'msg' => '获取美人模板表失败'], $this->request($a['auth'], 'beauty', 'loot_index', ['beauty_id' => 99, 'chip_id' => 1]));
        $idx = $this->request($a['auth'], 'beauty', 'loot_index', ['beauty_id' => 1, 'chip_id' => 1]);
        $this->assertSame(1, $idx['status']);
        $listUids = array_column($idx['loot_list'], 'uid');
        $this->assertContains($ub, $listUids);
        $this->assertNotContains($c['uid'], $listUids);
        $mine = $idx['loot_list'][array_search($ub, $listUids, true)];
        $this->assertSame(['rate_info' => 6, 'level' => 101, 'chip_id' => 1, 'beauty_id' => 1, 'uid' => $ub], array_intersect_key($mine, ['rate_info' => 1, 'level' => 1, 'chip_id' => 1, 'beauty_id' => 1, 'uid' => 1]));
        $this->assertSame([10, 10, 50], [$idx['free_times'], $idx['vip_times'], $idx['price']]);
        $this->assertSame([], $idx['loot_log']);
        $this->assertSame(1, DB::table('beauty_loot_list')->where('uid', $ua)->count());

        // 刷新对手：冷却 1 秒
        $re = $this->request($a['auth'], 'beauty', 'loot_reflush', ['beauty_id' => 1, 'chip_id' => 1]);
        $this->assertSame([1, 1], [$re['status'], $re['cd']]);
        $this->assertSame(['status' => -4, 'msg' => '刷新对手冷却中'], $this->request($a['auth'], 'beauty', 'loot_reflush', ['beauty_id' => 1, 'chip_id' => 1]));

        // 门槛
        $this->assertSame(['status' => -3, 'msg' => '美人id参数失败'], $this->request($a['auth'], 'beauty', 'loot_action', ['beauty_id' => 9, 'chip_id' => 1, 't' => $ub]));
        $this->assertSame(['status' => -11, 'msg' => '对方等级不足！'], $this->request($a['auth'], 'beauty', 'loot_action', ['beauty_id' => 1, 'chip_id' => 1, 't' => $c['uid']]));
        Redis::set('_lott_key' . $ub, '1');
        $this->assertSame(['status' => -9, 'msg' => '正在战斗中'], $this->request($a['auth'], 'beauty', 'loot_action', ['beauty_id' => 1, 'chip_id' => 1, 't' => $ub]));
        Redis::del('_lott_key' . $ub);
        $this->assertSame(['status' => -13, 'msg' => '对方碎片不足'], $this->request($a['auth'], 'beauty', 'loot_action', ['beauty_id' => 1, 'chip_id' => 2, 't' => $ub]));
        // 对方免战
        $this->assertSame(1, $this->request($b['auth'], 'beauty', 'peace')['status']);
        $this->assertSame(['status' => -7, 'msg' => '对方正在免战中'], $this->request($a['auth'], 'beauty', 'loot_action', ['beauty_id' => 1, 'chip_id' => 1, 't' => $ub]));
        Beauty::updateUserInfo($ub, ['cd_time' => 0]);
        // 被抢方正在升级：碎片被抢光时升级失败
        Beauty::updateOwn($ub, 1, ['up_status' => 1, 'up_dateline' => time() + 3000]);

        // 抢夺（空队伍对手必胜；成功率 90% 的随机按种子重放，直到抢到）
        $gold = $this->memberValue($ua, 'gold');
        $res = null;
        for ($seed = 1; $seed <= 50; $seed++) {
            self::clearLocks($ua);
            mt_srand($seed);
            $res = $this->request($a['auth'], 'beauty', 'loot_action', ['beauty_id' => 1, 'chip_id' => 1, 't' => $ub]);
            $this->assertSame(1, $res['status']);
            if ($res['b_info']['chip_status'] === 1) {
                break;
            }
            $this->assertSame(1, self::chipCounts($ub, 1)[0]);
        }
        $this->assertSame(['chip_status' => 1, 'chip_id' => 1, 'beauty_id' => 1, 'chip_name' => '祝融碎片一'], $res['b_info']);
        $this->assertSame(3, $res['info']['win']);
        $this->assertSame(['gender', 'win', 'touid', 'report'], array_keys($res['info']));
        $this->assertSame(['leftteam', 'rightteam', 'process', 'assess', 'leftresult', 'rightresult', 'leftbuff', 'rightbuff', 'left_role', 'right_role'], array_keys($res['battle']));
        $this->assertSame([], $res['battle']['rightteam']);
        $this->assertSame(['name' => DB::table('member')->where('uid', $ub)->value('nickname'), 'level' => 101, 'gender' => (int)DB::table('member')->where('uid', $ub)->value('gender')], $res['battle']['right_role']);
        $this->assertSame(['id' => $res['info']['report'], 'type' => 'beauty'], $res['share']);
        $this->assertSame(1, self::chipCounts($ua, 1)[0]);
        $this->assertSame(0, self::chipCounts($ub, 1)[0]);
        $this->assertSame(1, $res['chip'][0]['own']);
        // 被抢方的升级失败并收到系统信件；另有一封掠夺信件（类型 13）
        $this->assertSame([0, 0], [self::own($ub, 1)['up_status'], self::own($ub, 1)['up_dateline']]);
        $mails = Redis::getJson('user_messages:' . $ub) ?: [];
        $types = array_column($mails, 'type');
        $this->assertContains(13, $types);
        $this->assertContains('您的【祝融碎片一】已经被【' . DB::table('member')->where('uid', $ua)->value('nickname') . '】抢夺,升级失败。', array_column($mails, 'message'));
        // 免费次数、抢夺日志、宝箱奖励
        $tries = $seed;
        $this->assertSame(10 - $tries, $res['free_times']);
        $this->assertSame($tries, DB::table('beauty_loot_log')->where('uid', $ua)->count());
        $this->assertSame([['battle_status' => 1, 'to_name' => DB::table('member')->where('uid', $ub)->value('nickname'), 'to_uid' => $ub, 'beauty_id' => 1, 'chip_name' => '祝融碎片一', 'chip_id' => 1]],
            array_map(static fn (array $l): array => array_diff_key($l, ['report_id' => 1, 'dateline' => 1]), $res['loot_log']));
        $this->assertArrayHasKey('type', $res['reward_info']);
        // 被抢方看到的是对方抢自己（battle_status 2）
        $bIdx = $this->request($b['auth'], 'beauty', 'loot_index', ['beauty_id' => 1, 'chip_id' => 1]);
        $this->assertSame([1, 2, $ua], [$bIdx['status'], $bIdx['loot_log'][0]['battle_status'], $bIdx['loot_log'][0]['to_uid']]);
        // 已经拥有该碎片 / 对方碎片已被抢光
        self::clearLocks($ua);
        $this->assertSame(['status' => -8, 'msg' => '已经拥有该碎片'], $this->request($a['auth'], 'beauty', 'loot_index', ['beauty_id' => 1, 'chip_id' => 1]));
        $this->assertSame(['status' => -13, 'msg' => '对方碎片不足'], $this->request($a['auth'], 'beauty', 'loot_action', ['beauty_id' => 1, 'chip_id' => 1, 't' => $ub]));

        // 购买抢夺次数：50 元宝一次
        $before = Beauty::userInfo($ua, time());
        $buy = $this->request($a['auth'], 'beauty', 'buy_loot_times');
        $this->assertSame([1, $before['free_times'] + 1, 9, $gold - 50], [$buy['status'], $buy['free_times'], $buy['vip_times'], $buy['gold']]);
        $this->assertSame(1, DB::table('beauty_buyloot_log')->where('uid', $ua)->count());
        Beauty::updateUserInfo($ua, ['vip_times' => 0]);
        $this->assertSame(['status' => -2, 'msg' => '剩余购买抢夺次数不足'], $this->request($a['auth'], 'beauty', 'buy_loot_times'));
        // 每天重置：免费 10 次、购买 10 次
        Beauty::updateUserInfo($ua, ['free_times' => 0, 'dateline' => time() - 86400 * 2]);
        $idx3 = $this->request($a['auth'], 'beauty', 'loot_index', ['beauty_id' => 1, 'chip_id' => 3]);
        $this->assertSame([1, 10, 10], [$idx3['status'], $idx3['free_times'], $idx3['vip_times']]);
        Beauty::updateUserInfo($ua, ['free_times' => 0]);
        self::clearLocks($ua);
        $this->assertSame(['status' => -10, 'msg' => '免费抢夺次数不足'], $this->request($a['auth'], 'beauty', 'loot_action', ['beauty_id' => 1, 'chip_id' => 3, 't' => $ub]));

        // 战报回放依赖信件战报存储，未移植：没有指定的战报
        $this->assertSame(['status' => -1, 'battle' => [], 'info' => []], $this->request($a['auth'], 'beauty', 'report_detail', ['id' => $res['info']['report']]));
    }

    public function testLootRealBattleAndLevelUpCompletedForTarget(): void
    {
        $a = $this->player(['level' => 70, 'gold' => 100]);
        $b = $this->player(['level' => 70]);
        [$ua, $ub] = [$a['uid'], $b['uid']];
        $this->request($b['auth'], 'beauty', 'wakeup_beauty', ['beauty_id' => 2]);
        DB::table('beauty_own_chip')->where('uid', $ub)->where('beauty_id', 2)->update(['chip' => 3]);
        // 抢夺主页（初始化抢夺次数；对手列表里有 B）
        $this->assertSame(1, $this->request($a['auth'], 'beauty', 'loot_index', ['beauty_id' => 2, 'chip_id' => 4])['status']);
        // 对方升级已到时间：抢夺前替对方完成升级（is_play 2），碎片各减 1
        Beauty::updateOwn($ub, 2, ['up_status' => 1, 'up_dateline' => time() - 5]);

        mt_srand(7);
        $res = $this->request($a['auth'], 'beauty', 'loot_action', ['beauty_id' => 2, 'chip_id' => 4, 't' => $ub]);
        $this->assertSame(1, $res['status']);
        $this->assertSame([2, 0, 0, 2], [self::own($ub, 2)['beauty_level'], self::own($ub, 2)['up_status'], self::own($ub, 2)['up_dateline'], self::own($ub, 2)['is_play']]);
        // 一场真实战斗（双方都有初始武将）
        $this->assertNotEmpty($res['battle']['leftteam']);
        $this->assertNotEmpty($res['battle']['rightteam']);
        $this->assertNotEmpty($res['battle']['process']);
        $this->assertContains($res['info']['win'], [-3, -2, -1, 1, 2, 3]);
        $won = $res['info']['win'] >= 1 && $res['b_info']['chip_status'] === 1;
        $this->assertSame($won ? [1, 1] : [0, 2], [self::chipCounts($ua, 2)[3] ?? 0, self::chipCounts($ub, 2)[3]]);
        $log = DB::table('beauty_loot_log')->where('uid', $ua)->first();
        $this->assertSame([$ub, 2, 4, $res['info']['win'] >= 1 ? 1 : 0, $res['b_info']['chip_status']], [(int)$log['to_uid'], (int)$log['beauty_id'], (int)$log['chip_id'], (int)$log['battle_status'], (int)$log['chip_status']]);
        $this->assertSame(9, $res['free_times']);
        // 被抢方主页播放升级动画
        $this->assertSame(1, $this->request($b['auth'], 'beauty', 'get_beauty_info', ['beauty_id' => 2])['beauty_info']['info']['up_succ']);
    }

    // ---------------------------------------------------------------------------------------------
    // 加成
    // ---------------------------------------------------------------------------------------------

    public function testAdditionInGeneralInfoAndTeam(): void
    {
        $p = $this->player(['level' => 60]);
        [$uid, $gid] = [$p['uid'], $p['gid']];
        $baseInfo = $this->request($p['auth'], 'general', 'get_info', ['gid' => $gid])['info'];
        $baseTeam = TeamBuilder::forMember($uid)['team'];
        $pos = (int)array_key_first($baseTeam);
        $this->assertSame(array_fill_keys(['wuli', 'zhili', 'tili', 'attack', 'defense', 'pattack', 'pdefense', 'blood', 'dodge', 'harm', 'penetrate', 'power', 'mingzhong', 'jingzhun'], 0), $baseTeam[$pos]['beauty_arr']);

        $now = time();
        // 祝融 10 级（技能 1 武力）、大乔 30 级（技能 1 银币不计、技能 2 物防按 10 级计两次）、孙尚香 65 级（体力 65、技能 2 不计、技能 3 暴击伤害 5 级）
        // 好感度 70（效果 100%）；貂蝉休息中不计
        $rows = [1 => 10, 4 => 30, 3 => 65, 7 => 40];
        foreach ($rows as $bid => $level) {
            Beauty::initOwn($uid, $bid, $now);
            Beauty::updateOwn($uid, $bid, ['beauty_level' => $level, 'open_status' => $bid === 7 ? 0 : 1, 'effect_time' => $now - 10, 'effect_end_time' => $now + 1000, 'ratio' => 70, 'ratio_time' => $now - 1]);
        }
        $add = Beauty::addition($uid, $now);
        $expect = array_fill_keys(['wuli', 'zhili', 'tili', 'attack', 'defense', 'pattack', 'pdefense', 'blood', 'dodge', 'harm', 'penetrate', 'power', 'mingzhong', 'jingzhun'], 0);
        $expect['wuli'] = self::skillValue(1, 1, 10);
        $expect['defense'] = self::skillValue(4, 9, 10) * 2;
        $expect['tili'] = self::skillValue(3, 3, 65);
        $expect['harm'] = self::skillValue(3, 12, 5);
        $this->assertSame($expect, $add);
        // 好感度 30（效果 50%）
        Beauty::updateOwn($uid, 1, ['ratio' => 30]);
        $this->assertSame((int)(self::skillValue(1, 1, 10) * 0.5), Beauty::addition($uid, $now)['wuli']);
        Beauty::updateOwn($uid, 1, ['ratio' => 70]);

        // 将领界面：三围加到 wuliup / zhiliup / tiliup
        $info = $this->request($p['auth'], 'general', 'get_info', ['gid' => $gid])['info'];
        $this->assertSame([$baseInfo['wuliup'] + $expect['wuli'], $baseInfo['zhiliup'], $baseInfo['tiliup'] + $expect['tili']], [$info['wuliup'], $info['zhiliup'], $info['tiliup']]);
        // 组队：三围、物防加到单位上，beauty_arr 供推演读取
        $unit = TeamBuilder::forMember($uid)['team'][$pos];
        $this->assertSame($add, $unit['beauty_arr']);
        $this->assertSame([$expect['wuli'], $expect['tili'], $expect['defense']], [$unit['wuli'] - $baseTeam[$pos]['wuli'], $unit['tili'] - $baseTeam[$pos]['tili'], $unit['defense1'] - $baseTeam[$pos]['defense1']]);
        // 59 级不计
        DB::table('member')->where('uid', $uid)->update(['level' => 59]);
        $this->assertArrayNotHasKey('beauty_arr', TeamBuilder::forMember($uid)['team'][$pos]);
        $info59 = $this->request($p['auth'], 'general', 'get_info', ['gid' => $gid])['info'];
        $this->assertSame($baseInfo['wuliup'], $info59['wuliup']);
    }

    public function testEngineBeautyBonuses(): void
    {
        $atk = ['type' => 1, 'attack' => 300, 'talentid' => 1];
        $def = ['type' => 1, 'talentid' => 1, 'defense1' => 0];
        $arr = array_fill_keys(['wuli', 'zhili', 'tili', 'attack', 'defense', 'pattack', 'pdefense', 'blood', 'dodge', 'harm', 'penetrate', 'power', 'mingzhong', 'jingzhun'], 0);

        // 暴击伤害：300 × 1.5 + 300 × 美人 20% = 510
        $log = self::firstAction($atk + ['tili' => 0, 'soul' => [7 => 100], 'beauty_arr' => ['harm' => 20] + $arr], $def, [99, 99]);
        $this->assertSame([1, 510], [$log['hit'], $log['hurt'][0]['blood']]);
        // 闪避：防守方美人闪避 100 → 闪避；没有体力字段不计
        $log = self::firstAction($atk, $def + ['tili' => 0, 'beauty_arr' => ['dodge' => 100] + $arr], [99, 99]);
        $this->assertSame(-1, $log['attacktype']);
        $log = self::firstAction($atk, $def + ['beauty_arr' => ['dodge' => 100] + $arr], [99, 99]);
        $this->assertSame(0, $log['attacktype']);
        // 识破：谋士攻击时防守方美人识破
        $log = self::firstAction(['type' => 2, 'attack' => 300, 'talentid' => 1], $def + ['tili' => 0, 'beauty_arr' => ['penetrate' => 100] + $arr], [99, 99]);
        $this->assertSame(-2, $log['attacktype']);
    }

    /**
     * @param array<string, mixed> $attack
     * @param array<string, mixed> $defense
     * @param list<int> $offsets
     * @return array<string, mixed>
     */
    private static function firstAction(array $attack, array $defense, array $offsets): array
    {
        $engine = new BattleEngine(rng: new Randomizer(new ScriptedRandomEngine($offsets)));
        $unit = static fn (array $o): array => array_replace(TeamBuilder::unportedUnit(1, '单位', 100000, 0, 0), $o);
        return $engine->battle([2 => $unit($attack)], [2 => $unit($defense)])['process'][0];
    }
}
