<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\Battle\TeamBuilder;
use Hanjiang\Cron\GvgCron;
use Hanjiang\Cron\WarCron;
use Hanjiang\Cross\GvgServer;
use Hanjiang\Cross\Server;
use Hanjiang\Cross\WarServer;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\MemberJade;
use Hanjiang\Tests\Support\Gateway;
use OmniPHP\Cache\Redis;
use OmniPHP\Config;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use PHPUnit\Framework\TestCase;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * 跨服第二批：武斗会（c=war，服务器端按 s1 调用方、客户端与表结构还原的 Cross\WarServer）、群服争霸（c=gvg，
 * battle gvg 控制器 Cross\GvgServer 与 s1 cron_gvg），按旧版的流程测试。
 *
 * 两个玩法都按自然月计时，测试用 BossClock 固定时间；相关的全局表每个测试先清空。群服争霸的奖励计划任务读活动表 gvg，
 * 用 ACTIVITY_OPEN 打开（窗口从今天算起），所以群服争霸用下个月的日期。
 */
final class Cross2Test extends TestCase
{
    private int $starttime = 0;

    protected function setUp(): void
    {
        foreach (['arena', 'war_reward_log', 'war_shop_log', 'gvg_bet', 'gvg_reward_log', 'gvg_round',
            'battle_war_entry', 'battle_war_member', 'battle_war_member_info', 'battle_war_palace', 'battle_war_report', 'battle_war_round',
            'battle_war_support', 'battle_gvg_entry', 'battle_gvg_follow', 'battle_gvg_member', 'battle_gvg_report', 'battle_gvg_round',
            'battle_gvg_server', 'uc_report'] as $table) {
            DB::execute('TRUNCATE TABLE `zc_sanguo_' . $table . '`');
        }
        foreach (array_merge(Redis::keys('*war_*'), Redis::keys('*gvg_*'), Redis::keys('*get_share_info_*'), Redis::keys('*chat_recode_*'),
            Redis::keys('*point_buy_lock_*'), Redis::keys('*arena_top_30*')) as $key) {
            Redis::del(preg_replace('/^hj:/', '', $key));
        }
        Redis::del('bet_reward', 'push_member_reward', 'chat_message_0');
        // 开服时间：一年前（群服争霸当月新开的服不参加）
        $this->starttime = (int)DB::table('uc_server')->where('id', 1)->value('starttime');
        DB::table('uc_server')->where('id', 1)->update(['starttime' => (int)strtotime('-1 year')]);
    }

    protected function tearDown(): void
    {
        DB::table('uc_server')->where('id', 1)->update(['starttime' => $this->starttime]);
        BossClock::freeze(null);
        Config::set('app.report_url', '');
        Config::set('app.server_id', 1);
        putenv('ACTIVITY_OPEN');
        putenv('ACTIVITY_OPEN_DAYS');
        ActivityClock::reset();
    }

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function request(array $auth, string $controller, string $method, array $params = []): array
    {
        $ctx = Context::mock(array_merge(['c' => $controller, 'm' => $method], $auth, $params));
        $res = Gateway::handle($ctx);
        if ($res instanceof WorkermanResponse) {
            $json = json_decode((string)$res->rawBody(), true);
            $res = is_array($json) ? $json : (string)$res->rawBody();
        }
        $this->assertIsArray($res, "{$controller}.{$method}");
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
    private function player(array $member = []): array
    {
        $reg = $this->request(['token' => '', 'uid' => ''], 'register', 'guest');
        $uid = (int)$reg['uid'];
        $login = $this->request(['token' => '', 'uid' => $uid], 'login', 'guest', ['guestkey' => $reg['guestkey']]);
        $member += ['nickname' => 'c2_' . $uid];
        DB::table('member')->where('uid', $uid)->update($member);
        Redis::del('player:' . $uid);
        return ['uid' => $uid, 'auth' => ['token' => (string)$login['token'], 'uid' => $uid]];
    }

    private static function at(string $time): int
    {
        $ts = (int)strtotime($time);
        BossClock::freeze($ts);
        return $ts;
    }

    /**
     * @param array<string, mixed> $auth
     * @return array<string, mixed>
     */
    private function memberIndex(array $auth): array
    {
        $res = $this->request($auth, 'member', 'index');
        return $res['member'] ?? $res;
    }

    // ---------------------------------------------------------------------------------------------
    // 武斗会
    // ---------------------------------------------------------------------------------------------

    public function testWarEntryDaysFinalPalaceAndShare(): void
    {
        $serverid = Server::id();
        $players = [];
        for ($i = 0; $i < 6; $i++) {
            $players[] = $this->player(['level' => 90, 'gold' => 10]);
        }
        $viewer = $this->player(['level' => 70, 'gold' => 10]);
        $low79 = $this->player(['level' => 79]);
        $p0 = $players[0];

        // 19 日以前：活动未开启
        $now = self::at('2026-11-10 12:00:00');
        $index = $this->request($viewer['auth'], 'war', 'index');
        $this->assertSame([1, 1, (int)strtotime('2026-11-19') - $now + 3, 80, 0, 0, 0, ['is_entry' => 0]],
            [$index['status'], $index['stage'], $index['cd'], $index['limit_level'], $index['day'], $index['palace'], $index['is_entry'], $index['info']]);
        $this->assertSame(['status' => -1], $this->request($p0['auth'], 'war', 'entry'), '报名未开启');
        $this->assertSame(['status' => 2], $this->request($low79['auth'], 'war', 'entry'), '80 级以下');
        $this->assertSame([0, 0], [$this->memberIndex($p0['auth'])['war'], $this->memberIndex($p0['auth'])['war_entry']], '20 日以前没有图标');

        // 报名阶段
        self::at('2026-11-20 12:00:00');
        $index = $this->request($p0['auth'], 'war', 'index');
        $this->assertSame([2, ['list' => [], 'is_entry' => 0]], [$index['stage'], $index['info']]);
        $this->assertSame([1, 1], [$this->memberIndex($p0['auth'])['war'], $this->memberIndex($p0['auth'])['war_entry']]);
        $this->assertSame([1, 0], [$this->memberIndex($viewer['auth'])['war'], $this->memberIndex($viewer['auth'])['war_entry']], '报名等级 80');
        $entry = $this->request($p0['auth'], 'war', 'entry');
        $this->assertSame(['status' => 1, 'list' => [['nickname' => 'c2_' . $p0['uid']]]], $entry);
        $this->assertSame(['status' => -5], $this->request($p0['auth'], 'war', 'entry'));
        $this->assertSame(0, $this->memberIndex($p0['auth'])['war_entry'], '已报名');
        foreach (array_slice($players, 1) as $p) {
            $this->assertSame(1, $this->request($p['auth'], 'war', 'entry')['status']);
        }
        $index = $this->request($p0['auth'], 'war', 'index');
        $this->assertSame([4, 1], [count($index['info']['list']), $index['is_entry']]);
        $this->assertSame(-3, $this->request($p0['auth'], 'war', 'sync_battle_info')['status'], '报名未结束');
        $this->assertSame(['status' => -6], $this->request($low79['auth'], 'war', 'sync_battle_info'));

        // 18 点报名结束：计划任务同步全部报名玩家
        $t = self::at('2026-11-26 18:00:20');
        $this->assertSame(0, WarCron::run($t));
        $this->assertSame(6, DB::table('battle_war_member')->count());
        $this->assertSame([90], array_values(array_unique(array_map('intval', array_column(DB::table('battle_war_entry')->get(), 'level')))));
        $index = $this->request($viewer['auth'], 'war', 'index');
        $this->assertSame([5, (int)strtotime('2026-11-26 19:00:00') - $t + 3], [$index['stage'], $index['cd']]);
        $this->assertSame(-2, $this->request($p0['auth'], 'war', 'sync_battle_info')['status'], '计划任务刚同步过');
        self::at('2026-11-26 18:01:00');
        $this->assertSame(['status' => 1, 'info' => []], $this->request($p0['auth'], 'war', 'sync_battle_info'));
        $this->assertSame(-2, $this->request($p0['auth'], 'war', 'sync_battle_info')['status'], '5 秒内');
        $this->assertSame(['status' => -1, 'info' => []], $this->request($this->player(['level' => 90])['auth'], 'war', 'sync_battle_info'), '未报名');

        // 预赛第一轮：6 人配成 3 场；观战玩家随机关注一场并支持一方
        $t = self::at('2026-11-26 19:00:30');
        $this->assertSame(0, WarCron::run($t));
        $this->assertSame(3, DB::table('battle_war_round')->where('day', 1)->where('round', 1)->count());
        $this->assertSame(2, $this->memberIndex($p0['auth'])['war_entry'], '开战');
        $index = $this->request($viewer['auth'], 'war', 'index');
        $this->assertSame([3, 1, 0, 1, 1, 1, 570 + 3], [$index['stage'], $index['day'], $index['is_entry'], $index['info']['round'], $index['info']['stage'], $index['info']['mode'], $index['cd']]);
        $this->assertSame(['id' => 1, 'name' => '预赛', 'integral' => 35, 'support' => 3], $index['info']['reward']);
        $this->assertSame('【官方1服】', $index['info']['play']['left']['server']);
        $roundid = $index['info']['roundid'];
        $this->assertSame(['status' => 1], $this->request($viewer['auth'], 'war', 'support', ['side' => 1]));
        $this->assertSame(['status' => -1], $this->request($viewer['auth'], 'war', 'support', ['side' => 1]), '已经支持过');
        $this->assertSame(-1, $this->request($viewer['auth'], 'war', 'index')['is_entry']);
        $support = $this->request($viewer['auth'], 'war', 'get_support', ['id' => $roundid]);
        $this->assertSame(1, $support['status']);
        $this->assertGreaterThan(0, $support['left']);
        $mine = $this->request($p0['auth'], 'war', 'index');
        $this->assertSame([1, 128], [$mine['is_entry'], $mine['info']['max_rank']]);
        $this->assertGreaterThan(0, $mine['info']['rank']);
        $this->assertContains($p0['uid'], [$mine['info']['play']['left']['uid'], $mine['info']['play']['right']['uid']]);
        self::at('2026-11-26 19:05:31');
        $other = $this->player(['level' => 70]);
        $this->assertSame(['status' => -1], $this->request($other['auth'], 'war', 'support', ['side' => 1]), '开打前 5 分钟截止');

        // 预赛到复赛：每天 5 轮，每轮开打前 1 分钟算出战斗并配下一轮
        foreach (['2026-11-26', '2026-11-27', '2026-11-28'] as $day) {
            WarCron::run(self::at($day . ' 19:00:30'));
            foreach (['19:09:30', '19:19:30', '19:29:30', '19:39:30', '19:49:30'] as $hm) {
                $t = self::at($day . ' ' . $hm);
                $this->assertSame(3, WarCron::run($t), $day . ' ' . $hm);
                $this->assertSame(0, WarCron::run($t + 20), '每轮只算一次');
            }
        }
        $this->assertSame(45, DB::table('battle_war_round')->where('day', '<=', 3)->count());
        $this->assertSame(0, DB::table('battle_war_round')->where('day', '<=', 3)->where('win', 0)->count());
        $this->assertSame(30, (int)DB::table('battle_war_entry')->sum('win1'), '预赛与初赛的胜场');
        $this->assertSame(15, (int)DB::table('battle_war_entry')->sum('win2'), '复赛的胜场');
        $this->assertSame(15 * 35 + 15 * 60 + 15 * 90, (int)DB::table('battle_war_entry')->sum('integral'));
        $this->assertSame(0, DB::table('battle_war_entry')->where('goout', '>', 0)->count(), '6 人都晋级');
        $viewerPoint = (int)DB::table('battle_war_round')->where('id', $roundid)->value('win') === 1 ? 3 : 0;
        $this->assertSame($viewerPoint, WarServer::getPoint($viewer['uid'], $serverid), '支持对了得 3 积分');

        // 当日战斗结束：晋级名单与下次开战倒计时
        $t = self::at('2026-11-28 21:00:00');
        $index = $this->request($p0['auth'], 'war', 'index');
        $this->assertSame([6, 4, 3, 1, 6, (int)strtotime('2026-11-29 19:00:00') - $t + 3],
            [$index['stage'], $index['day'], $index['info']['pre_day'], $index['info']['isup'], count($index['info']['list']), $index['cd']]);
        $this->assertGreaterThan(0, $index['info']['myrank']);
        $this->assertSame([1, '【官方1服】'], [$index['info']['list'][0]['rank'], $index['info']['list'][0]['server']]);
        $this->assertSame(0, $this->request($viewer['auth'], 'war', 'index')['info']['myrank']);

        // 决赛：6 人 → 4 场（2 场轮空），三局两胜
        WarCron::run(self::at('2026-11-29 19:00:30'));
        $this->assertSame([4, 4, 4, 4], array_map('intval', array_column(DB::table('battle_war_round')->where('day', 4)->get(), 'round')));
        $index = $this->request($viewer['auth'], 'war', 'index');
        $this->assertSame([3, 4, 2, 0], [$index['stage'], $index['day'], $index['info']['mode'], $index['info']['rank']]);
        $this->assertSame(['四强赛', 1700, 200], [$index['info']['reward']['name'], $index['info']['reward']['integral'], $index['info']['reward']['support']], '决赛按轮次给积分');
        foreach (['19:09:30', '19:19:30', '19:29:30', '19:39:30', '19:49:30', '19:59:30', '20:09:30', '20:19:30', '20:29:30'] as $hm) {
            $t = self::at('2026-11-29 ' . $hm);
            WarCron::run($t);
            $this->assertSame(0, WarCron::run($t + 20), $hm . ' 每局只算一次');
        }
        $this->assertSame([4, 2, 1], array_values(array_unique(array_map('intval', array_column(DB::table('battle_war_round')->where('day', 4)->orderBy('id')->get(), 'round')))));
        $this->assertSame(0, DB::table('battle_war_round')->where('day', 4)->where('win', 0)->count());
        $final = DB::table('battle_war_round')->where('day', 4)->where('round', 1)->first();
        $champion = (int)$final['uid' . $final['win']];
        $this->assertSame([1, 2, 2, 4, 4], array_map('intval', array_column(DB::table('battle_war_entry')->where('goout', '>', 0)->orderBy('goout')->get(), 'goout')));
        $this->assertSame($champion, (int)DB::table('battle_war_entry')->where('goout', 0)->value('uid'));
        $palace = DB::table('battle_war_palace')->orderBy('site')->get();
        $this->assertSame([1, 2, 3, 4], array_map('intval', array_column($palace, 'site')));
        $this->assertSame($champion, (int)$palace[0]['uid']);
        $finalStage = (int)DB::table('battle_war_report')->where('id', $final['id'])->max('stage');
        $report = WarServer::reportInfo((int)$final['id'], $finalStage);
        $this->assertSame([2, 1, 4, 3000], [$report['report']['report']['up'], $report['report']['report']['round'], $report['report']['report']['day'], $report['report']['integral']]);
        foreach (['leftteam', 'rightteam', 'process', 'assess', 'leftresult', 'rightresult'] as $key) {
            $this->assertArrayHasKey($key, $report['report']['report']);
        }
        $this->assertNotEmpty($report['report']['report']['process']);
        $this->assertGreaterThanOrEqual(3000, WarServer::getPoint($champion, $serverid), '冠军决赛 3000 积分');

        // 活动结束：武神榜、斗士堂、历程、回放、分享、奖励
        self::at('2026-11-29 20:31:00');
        $index = $this->request($viewer['auth'], 'war', 'index');
        $this->assertSame([4, 0, 1], [$index['stage'], $index['cd'], $index['palace']]);
        $this->assertSame(['第一名', '第二名', '四强', '四强', '八强', '八强'], array_column($index['info']['list'], 'round'));
        $rank = $this->request($viewer['auth'], 'war', 'get_rank_list');
        $this->assertSame([1, $champion, '第一名', '【官方1服】'], [$rank['status'], $rank['list'][0]['uid'], $rank['list'][0]['round'], $rank['list'][0]['server']]);
        $palaceList = $this->request($viewer['auth'], 'war', 'get_palace_list', ['all' => 0]);
        $this->assertSame([1, 4, $champion, '【官方1服】'], [$palaceList['status'], count($palaceList['list']), $palaceList['list'][0]['uid'], $palaceList['list'][0]['server']]);
        $all = $this->request($viewer['auth'], 'war', 'get_palace_list', ['all' => 1]);
        $this->assertSame([1, 1, 4], [$all['status'], count($all['list']), count($all['list'][0])]);
        $cp = array_values(array_filter($players, static fn (array $p): bool => $p['uid'] === $champion))[0];
        $this->assertSame(['status' => -4], $this->request($viewer['auth'], 'war', 'get_report_list', ['uid' => $champion]), '80 级以下不能看历程');
        $looker = $players[0]['uid'] === $champion ? $players[1] : $players[0];
        $history = $this->request($looker['auth'], 'war', 'get_report_list', ['uid' => $champion, 'serverid' => $serverid]);
        $this->assertSame([1, '第一名', '【官方1服】', 90], [$history['status'], $history['info']['rank'], $history['info']['server'], $history['info']['level']]);
        $rounds = array_unique(array_column($history['list'], 'round'));
        foreach (['预赛', '初赛', '复赛', '决赛'] as $name) {
            $this->assertContains($name, $rounds);
        }
        $this->assertContains('第五轮', array_column($history['list'], 'turn'));
        $this->assertSame(['status' => -4], $this->request($low79['auth'], 'war', 'get_report_list', ['uid' => $champion]));
        $this->assertSame(['status' => -3], $this->request($looker['auth'], 'war', 'get_report_list', ['uid' => $viewer['uid'], 'serverid' => $serverid]));
        $this->assertSame(1, $this->request($cp['auth'], 'war', 'get_report_list', ['uid' => $champion])['status'], '本人取本服');
        $ri = $this->request($viewer['auth'], 'war', 'get_report_info', ['id' => $final['id'], 'stage' => $finalStage]);
        $this->assertSame([1, 3000, ['id' => $final['id'] . '_' . $finalStage, 'type' => 'war']], [$ri['status'], $ri['info']['integral'], $ri['share']]);
        $this->assertArrayHasKey('left_role', $ri['battle']);
        $this->assertSame(['status' => -1, 'share' => ['id' => '0_0', 'type' => 'war']], $this->request($viewer['auth'], 'war', 'get_report_info'));
        $share = $this->request($viewer['auth'], 'war', 'share_report', ['id' => $final['id'], 'stage' => $finalStage]);
        $this->assertSame(['status' => 1, 'all_gold' => 8], $share);
        $this->assertStringContainsString('在武斗会决赛中战胜', implode("\n", array_column($this->request($viewer['auth'], 'chat', 'index')['list'], 'message')));
        $this->assertSame(['status' => 1, 'all_gold' => 6], $this->request($viewer['auth'], 'war', 'share_report', ['id' => $final['id'], 'stage' => $finalStage]), '5 分钟内不再广播');
        $this->assertSame(['status' => -2], $this->request($viewer['auth'], 'war', 'share_report', ['id' => 999, 'stage' => 1]));
        $this->assertSame(['status' => -3], $this->request($viewer['auth'], 'war', 'get_reward_list', ['type' => 1]), '没有物品奖励');
        $this->assertSame(['status' => -2], $this->request($viewer['auth'], 'war', 'get_reward', ['type' => 1, 'id' => 1]));
        self::at('2026-11-25 12:00:00');
        $this->assertSame(['status' => -2], $this->request($viewer['auth'], 'war', 'get_reward_list'), '活动未结束');

        // 下个月初还是本期（第 4 天可能跨到下月），6 日起为下一期
        self::at('2026-12-03 12:00:00');
        $this->assertSame(4, $this->request($viewer['auth'], 'war', 'index')['stage']);
        self::at('2026-12-06 12:00:00');
        $this->assertSame([1, 1], [$this->request($viewer['auth'], 'war', 'index')['stage'], $this->request($viewer['auth'], 'war', 'index')['palace']]);
    }

    public function testWarPointShop(): void
    {
        $p = $this->player(['level' => 90, 'silver' => 0]);
        $serverid = Server::id();
        $shop = $this->request($p['auth'], 'war', 'point_shop');
        $this->assertSame([1, 0, 12], [$shop['status'], $shop['point'], count($shop['list'])]);
        // 第 3 个宝箱第 5 项为精华：数量换成参数 2
        $this->assertSame([5, 5, 1], [$shop['list'][2]['getvalue5'], $shop['list'][2]['info']['essence5']['num'], $shop['list'][2]['info']['essence5']['id']]);
        $this->assertSame(72, $shop['list'][0]['info']['equip4']['id']);
        $this->assertSame(100, $shop['list'][3]['info']['mount4']['id']);

        $this->assertSame(['status' => 2], $this->request($p['auth'], 'war', 'point_buy', ['id' => 12]), '积分不足');
        DB::table('battle_war_member_info')->insert(['serverid' => $serverid, 'uid' => $p['uid'], 'integral' => 1500]);
        $jade = MemberJade::get($p['uid']);
        $leap = FreeLeap::get($p['uid']);
        $buy = $this->request($p['auth'], 'war', 'point_buy', ['id' => 12]);
        $this->assertSame([1, 500, 5000000, 5000000, '普通宝箱'], [$buy['status'], $buy['point'], $buy['silver'], $buy['allsilver'], $buy['list']['name']]);
        $this->assertSame([$jade + 2000000, $leap + 200], [MemberJade::get($p['uid']), FreeLeap::get($p['uid'])]);
        $this->assertSame(500, WarServer::getPoint($p['uid'], $serverid));
        $this->assertSame([[12, $p['uid']]], array_map(static fn (array $r): array => [(int)$r['wid'], (int)$r['uid']], DB::table('war_shop_log')->get()));
        $this->assertSame(['status' => 2], $this->request($p['auth'], 'war', 'point_buy', ['id' => 12]));
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'war', 'point_buy', ['id' => 999]));
        // 购买锁被占：回 3 并解锁
        Redis::set('point_buy_lock_' . $p['uid'], 1, 30);
        $this->assertSame(['status' => 3], $this->request($p['auth'], 'war', 'point_buy', ['id' => 12]));
        $this->assertFalse(Redis::exists('point_buy_lock_' . $p['uid']));
        $this->assertSame(500, $this->request($p['auth'], 'war', 'point_shop')['point']);
    }

    // ---------------------------------------------------------------------------------------------
    // 群服争霸
    // ---------------------------------------------------------------------------------------------

    public function testGvgSingleServerBetAndRewards(): void
    {
        $ym = date('Y-m-', (int)strtotime('first day of next month'));
        $serverid = Server::id();
        $players = [];
        for ($i = 1; $i <= 3; $i++) {
            $players[$i] = $this->player(['level' => 80]);
            DB::table('arena')->insert(['uid' => $players[$i]['uid'], 'username' => 'a' . $players[$i]['uid'], 'rank' => $i]);
        }
        $viewer = $this->player(['level' => 60, 'gold' => 0]);
        $low = $this->player(['level' => 59]);

        // 15 日 23 点以前：未开启
        self::at($ym . '14 12:00:00');
        $this->assertSame(['status' => -3], $this->request($viewer['auth'], 'gvg', 'index'));
        $this->assertSame(0, $this->memberIndex($viewer['auth'])['gvg']);

        // 15 日：计划任务生成参赛服务器、对阵（单服：1 场，右方轮空）与关注
        $t = self::at($ym . '15 10:00:00');
        $this->assertSame(0, GvgCron::runPk($t));
        GvgCron::runPk($t + 60);
        $this->assertSame([[$serverid, 0]], array_map(static fn (array $r): array => [(int)$r['serverid'], (int)$r['goout']], DB::table('battle_gvg_server')->get()));
        $this->assertSame([[1, 1, $serverid, 0]], array_map(static fn (array $r): array => [(int)$r['round'], (int)$r['roundid'], (int)$r['serverid1'], (int)$r['serverid2']], DB::table('battle_gvg_round')->get()));
        $this->assertSame(1, DB::table('battle_gvg_follow')->count());
        $this->assertSame(0, GvgCron::runPk((int)strtotime($ym . '14 12:00:00')), '计划任务时间外不执行');

        // 16 日：图标、选手确认倒计时（本服演武榜前 30）
        $t = self::at($ym . '16 12:00:00');
        $this->assertSame(1, $this->memberIndex($viewer['auth'])['gvg']);
        $this->assertSame(['status' => -1], $this->request($low['auth'], 'gvg', 'index'));
        $index = $this->request($viewer['auth'], 'gvg', 'index');
        $this->assertSame([1, 1, (int)strtotime($ym . '18 19:00:00') - $t + 5], [$index['status'], $index['stage'], $index['cd']]);
        $play = $index['info']['play'];
        $this->assertSame(['官方1服', '', '决赛', 60, ['player' => [], 'server' => []]], [$play['left_name'], $play['right_name'], $play['round_name'], $play['level_limit'], $play['reward']]);
        $this->assertSame([3, 2, 1], array_column($index['info']['team']['left'], 'rank'), '旧版按排名降序');
        $this->assertSame(['nickname' => 'a' . $players[3]['uid'], 'level' => 80], array_intersect_key($index['info']['team']['left'][0], ['nickname' => 1, 'level' => 1]));
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], array_keys($index['info']['schedule']));
        $this->assertSame('官方1服', $index['info']['schedule'][1]['server1']);
        $this->assertSame(['status' => -1], $this->request($players[1]['auth'], 'gvg', 'sync_info'), '还不是参赛玩家');

        // 18 日 19 点：取参赛玩家并同步
        $t = self::at($ym . '18 19:01:00');
        GvgCron::runPk($t);
        $this->assertSame(3, DB::table('battle_gvg_entry')->count());
        $this->assertSame(3, DB::table('battle_gvg_member')->count());
        $index = $this->request($players[1]['auth'], 'gvg', 'index');
        $this->assertSame(2, $index['stage']);
        $this->assertSame(array_map(static fn (array $p): int => $p['uid'], array_values($players)), array_column($index['info']['team']['left'], 'uid'), '参赛玩家按演武排名升序');
        $this->assertSame(['status' => -1], $this->request($players[1]['auth'], 'gvg', 'sync_info'), '10 秒内（战斗服务器回 2）');
        self::at($ym . '18 19:01:11');
        $this->assertSame(['status' => 1], $this->request($players[1]['auth'], 'gvg', 'sync_info'));
        $this->assertSame(['status' => -2], $this->request($players[1]['auth'], 'gvg', 'sync_info'), '5 秒同步锁');

        // 战斗中（第一轮 19:15 开打，19:10 锁定）：参赛者可同步，其他人可押注
        self::at($ym . '18 19:05:00');
        $index = $this->request($players[1]['auth'], 'gvg', 'index');
        $this->assertSame([3, 1, 1, 0], [$index['stage'], $index['info']['play']['is_player'], $index['info']['play']['sync'], $index['info']['play']['bet']]);
        $index = $this->request($viewer['auth'], 'gvg', 'index');
        $this->assertSame([3, 0, 0, 1], [$index['stage'], $index['info']['play']['is_player'], $index['info']['play']['sync'], $index['info']['play']['bet']]);
        $this->assertSame(['status' => -2], $this->request($players[2]['auth'], 'gvg', 'betting', ['round' => 1, 'roundid' => 1, 'toserverid' => $serverid]), '参赛者不能押注');
        $bet = $this->request($viewer['auth'], 'gvg', 'betting', ['round' => 1, 'roundid' => 1, 'toserverid' => $serverid]);
        $this->assertSame(1, $bet['status']);
        $this->assertGreaterThan(0, $bet['bet1']);
        $this->assertSame(['status' => -1], $this->request($viewer['auth'], 'gvg', 'betting', ['round' => 1, 'roundid' => 1, 'toserverid' => $serverid]), '已押注');
        $getBet = $this->request($viewer['auth'], 'gvg', 'get_bet', ['round' => 1, 'roundid' => 1]);
        $this->assertSame([1, $serverid, 0], [$getBet['status'], $getBet['info']['serverid1'], $getBet['info']['serverid2']]);
        $this->assertGreaterThan(0, $getBet['info']['bet1']);
        $this->assertSame([2, $serverid], [$this->request($viewer['auth'], 'gvg', 'index')['info']['play']['bet'], $this->request($viewer['auth'], 'gvg', 'index')['info']['play']['bet_server']]);
        $this->assertSame(['status' => -1], $this->request($viewer['auth'], 'gvg', 'get_bet', ['round' => 9, 'roundid' => 9]));

        // 锁定后算出本轮：右方轮空，左方胜
        $t = self::at($ym . '18 19:11:00');
        $this->assertSame(1, GvgCron::runPk($t));
        $this->assertSame(1, (int)DB::table('battle_gvg_round')->value('win'));
        $this->assertSame(['status' => -2], $this->request($viewer['auth'], 'gvg', 'get_report', ['round' => 1, 'roundid' => 1]), '轮空没有战报');
        $this->assertSame(['status' => -1], $this->request($viewer['auth'], 'gvg', 'get_report', ['round' => 0, 'roundid' => 1]));

        // 战斗结束：押注奖励与晋级奖励（活动表 gvg 开启时）
        $t = self::at($ym . '18 19:16:00');
        $this->assertSame(-1, GvgCron::betReward($t), '活动表 gvg 旧版从未开启');
        putenv('ACTIVITY_OPEN=gvg');
        putenv('ACTIVITY_OPEN_DAYS=90');
        ActivityClock::reset();
        $this->assertSame(1, GvgCron::betReward($t));
        $this->assertSame(2, (int)DB::table('member')->where('uid', $viewer['uid'])->value('gold'), '押注奖励 2 元宝');
        $this->assertSame(-2, GvgCron::betReward($t + 60), '10 分钟锁');
        $this->assertGreaterThan(0, GvgCron::pushMemberReward($t));
        $logs = DB::table('gvg_reward_log')->where('type', 1)->orderBy('uid')->get();
        $this->assertSame(array_map(static fn (array $p): int => $p['uid'], array_values($players)), array_map('intval', array_column($logs, 'uid')));
        $this->assertSame(0, DB::table('gvg_reward_log')->where('dateline', 0)->count());
        $this->assertSame(2, (int)DB::table('gvg_reward_log')->where('uid', $viewer['uid'])->value('type'), '其他 60 级以上玩家为全服奖励');
        $this->assertNull(DB::table('gvg_reward_log')->where('uid', $low['uid'])->value('type'), '60 级以下没有');
        $this->assertSame(1, (int)DB::table('gvg_round')->where('round', 1)->value('islock'));
        $count = DB::table('gvg_reward_log')->count();
        GvgCron::pushMemberReward($t + 400);
        $this->assertSame($count, DB::table('gvg_reward_log')->count(), '只生成一次');
        $messages = array_column(DB::table('pm')->where('touid', $players[1]['uid'])->get(), 'message');
        $this->assertContains('您所在的服务器在群服争霸中获得冠军，您获得奖励。', $messages, '旧版数据错列，奖励为空，照样发信件');

        // 结果页
        self::at($ym . '18 19:20:00');
        $index = $this->request($viewer['auth'], 'gvg', 'index');
        $this->assertSame([1, 4], [$index['status'], $index['stage']]);
        $this->assertSame(['rank' => '第一名', 'server' => '官方1服'], $index['match_result'][1]);
        $this->assertSame(['rank' => '第二名', 'server' => ''], $index['match_result'][2]);
        $this->assertSame([['round' => 1, 'roundid' => 1, 'serverid1' => '官方1服', 'serverid2' => '', 'win' => 1]], $index['schedule']);
    }

    public function testGvgTeamBattleReportSwapAndShare(): void
    {
        $ym = date('Y-m-', (int)strtotime('first day of next month'));
        $phase = (int)str_replace('-', '', rtrim($ym, '-'));
        $local = [$this->player(['level' => 80]), $this->player(['level' => 80])];
        $remote = [$this->player(['level' => 80]), $this->player(['level' => 80])];
        DB::table('battle_gvg_server')->insert(['phase' => $phase, 'serverid' => 1]);
        DB::table('battle_gvg_server')->insert(['phase' => $phase, 'serverid' => 2]);
        DB::table('battle_gvg_round')->insert(['phase' => $phase, 'round' => 1, 'roundid' => 1, 'serverid1' => 2, 'serverid2' => 1]);
        foreach ([1 => $local, 2 => $remote] as $sid => $list) {
            foreach ($list as $k => $p) {
                DB::table('battle_gvg_entry')->insert(['phase' => $phase, 'uid' => $p['uid'], 'serverid' => $sid, 'nickname' => 'g' . $p['uid'], 'level' => 80, 'rank' => $k + 1]);
                DB::table('battle_gvg_member')->insert(['uid' => $p['uid'], 'serverid' => $sid, 'data' => (string)json_encode(TeamBuilder::forMember($p['uid'], defense: true, self: false))]);
            }
        }
        $t = self::at($ym . '18 19:11:00');
        $this->assertSame(1, GvgServer::runPk($t));
        $row = DB::table('battle_gvg_round')->first();
        $win = (int)$row['win'];
        $this->assertContains($win, [1, 2]);
        $this->assertSame((int)$row['round'], (int)DB::table('battle_gvg_server')->where('serverid', $win === 1 ? 1 : 2)->value('goout'), '败方出局');

        // 服务器 id 小的先手：战报左方为本服（1）
        $res = $this->request($local[0]['auth'], 'gvg', 'get_report', ['round' => 1, 'roundid' => 1]);
        $this->assertSame([1, '官方1服', '官方2服', ['id' => '1_1', 'type' => 'gvg']], [$res['status'], $res['report']['left_name'], $res['report']['right_name'], $res['share']]);
        $this->assertSame(array_map(static fn (array $p): int => $p['uid'], $local), array_column($res['report']['left_team'], 'uid'));
        foreach (['win', 'battle_ready', 'battle_log', 'general_arr', 'kill_rank'] as $key) {
            $this->assertArrayHasKey($key, $res['report']['battle']);
        }
        $this->assertNotEmpty($res['report']['battle']['battle_log']);
        $reportWin = (int)$res['report']['battle']['win'];

        // 本服是战报守方时左右交换（以 2 服的身份看）
        Redis::del('gvg_report_1_1');
        Config::set('app.server_id', 2);
        $swapped = $this->request($local[0]['auth'], 'gvg', 'get_report', ['round' => 1, 'roundid' => 1]);
        $this->assertSame(['官方2服', '官方1服', 3 - $reportWin], [$swapped['report']['left_name'], $swapped['report']['right_name'], $swapped['report']['battle']['win']]);
        $this->assertSame(array_map(static fn (array $p): int => $p['uid'], $remote), array_column($swapped['report']['left_team'], 'uid'));
        Config::set('app.server_id', 1);

        // 分享到外部
        Redis::del('gvg_report_1_1');
        Config::set('app.report_url', 'https://share.example/r?id=');
        $save = $this->request($local[0]['auth'], 'report', 'save', ['type' => 'gvg', 'id' => '1_1']);
        $this->assertSame(1, $save['status']);
        $stored = json_decode((string)DB::table('uc_report')->where('type', 'gvg')->value('report'), true);
        $this->assertSame(['【官方1服】', '【官方2服】'], [$stored['report']['left_name'], $stored['report']['right_name']]);
    }
}
