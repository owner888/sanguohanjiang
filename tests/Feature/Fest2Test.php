<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\Cron\FestivalCron;
use Hanjiang\Handler\Activity\ActCelebrationHandler;
use Hanjiang\Handler\Activity\ActHalloweenHandler;
use Hanjiang\Handler\Activity\ActMoonHandler;
use Hanjiang\Handler\Activity\ActNationalDayHandler;
use Hanjiang\Handler\Activity\ActTanabataHandler;
use Hanjiang\Handler\Activity\ChingmingHandler;
use Hanjiang\Handler\Activity\ChristmasbuyHandler;
use Hanjiang\MasterData\ActTanabataRankreward;
use Hanjiang\MasterData\ChristmasgiftReward;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberActivity;
use Hanjiang\Model\MemberIntegral;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\Pay;
use Hanjiang\Model\TraderLog;
use Hanjiang\Model\WorldBoss;
use Hanjiang\Tests\Support\Gateway;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use PHPUnit\Framework\TestCase;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * 节日活动第二批：周年庆（c=anniversary）、国庆红包（c=act_national_day 与元宝流水记账）、圣诞礼包（c=christmasgift）、
 * 七夕（c=act_tanabata 与充值记账）、国家庆典（c=act_celebration、充值记账、退出国家自动领奖）、清明祭祖（c=chingming 与主城
 * VIP 同步）、圣诞团购（c=christmasbuy）、万圣节（c=act_halloween）、中秋送月饼（c=act_moon 与充值送月饼），以及活动结束后的
 * 计划任务（FestivalCron）按旧版流程测试。
 *
 * 旧版数据中这些活动均已结束或从未开放：用开发者开关 ACTIVITY_OPEN 开启（窗口从今天 0 点到 30 天后），时间用 BossClock 固定；
 * 随机奖励固定 mt_rand 种子（tearDown 重置）。
 */
final class Fest2Test extends TestCase
{
    /** 本批活动的玩家记录表（每个测试从空表开始：活动版本号固定，重复运行时排行、全服合计会带上上次的数据） */
    private const array TABLES = [
        'anniversary_log', 'national_day_log', 'national_day_gold_log', 'christmasgift_log', 'act_tanabata_order',
        'act_tanabata_reward_log', 'act_tanabata_rankreward_log', 'act_celebration_order', 'act_celebration_memberreward_log',
        'act_celebration_reward_log', 'act_celebration_reward_own_log', 'chingming_log', 'chingming_achievement_log',
        'christmasbuy_log', 'christmasbuy_info', 'christmasbuy_gift_log', 'halloween', 'act_moon', 'act_moon_ach_log',
        'act_moon_givefriend_log', 'act_moon_order',
    ];

    protected function setUp(): void
    {
        foreach (self::TABLES as $table) {
            DB::execute('DELETE FROM `zc_sanguo_' . $table . '`');
        }
        WorldBoss::deleteByPattern('christmasgift_*');
        WorldBoss::deleteByPattern('car_key_*');
        WorldBoss::deleteByPattern('moon_cron_list*');
        WorldBoss::deleteByPattern('mn_lk_*');
    }

    protected function tearDown(): void
    {
        putenv('ACTIVITY_OPEN');
        putenv('ACTIVITY_OPEN_DAYS');
        ActivityClock::reset();
        BossClock::freeze(null);
        mt_srand();
        WorldBoss::deleteByPattern('christmasgift_*');
        WorldBoss::deleteByPattern('car_key_*');
        WorldBoss::deleteByPattern('moon_cron_list*');
        WorldBoss::deleteByPattern('mn_lk_*');
    }

    private static function open(string $names): void
    {
        putenv('ACTIVITY_OPEN=' . $names);
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
            $this->assertIsArray($json, "{$controller}.{$method}");
            $res = $json;
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
        $member += ['nickname' => 'f2_' . $uid];
        DB::table('member')->where('uid', $uid)->update($member);
        Redis::del('player:' . $uid);
        return ['uid' => $uid, 'auth' => ['token' => (string)$login['token'], 'uid' => $uid]];
    }

    private static function memberValue(int $uid, string $column): int
    {
        return (int)DB::table('member')->where('uid', $uid)->value($column);
    }

    private static function messages(int $uid, string $like = ''): int
    {
        $query = DB::table('pm')->where('touid', $uid)->whereNull('deleted_at');
        if ($like !== '') {
            $query->where('message', 'like', '%' . $like . '%');
        }
        return $query->count();
    }

    private static function today(string $time = '12:00:00'): int
    {
        return (int)strtotime(date('Y-m-d') . ' ' . $time);
    }

    // ---------------------------------------------------------------------------------------------
    // 周年庆
    // ---------------------------------------------------------------------------------------------

    public function testAnniversaryDailyRewardCostAndLock(): void
    {
        $p = $this->player(['gold' => 200, 'silver' => 0, 'soul' => 0]);
        $uid = $p['uid'];
        BossClock::freeze(self::today());
        $this->assertSame(['status' => 2], $this->request($p['auth'], 'anniversary', 'index'));
        $this->assertSame(['status' => 2], $this->request($p['auth'], 'anniversary', 'get_reward'));

        self::open('anniversary');
        $index = $this->request($p['auth'], 'anniversary', 'index');
        $this->assertSame([1, 0, 0, 300, 0], [$index['status'], $index['cd'], $index['is_get'], $index['price'], $index['last_day']]);
        $this->assertSame(['type' => 1, 'value' => 5000000], $index['list']['1']);
        $this->assertCount(4, $index['list']);
        // 元宝不足
        $this->assertSame(['status' => -3], $this->request($p['auth'], 'anniversary', 'get_reward'));
        DB::table('member')->where('uid', $uid)->update(['gold' => 1000]);
        $leap = FreeLeap::get($uid);
        $purple = TraderLog::jade($uid, 3);
        $get = $this->request($p['auth'], 'anniversary', 'get_reward');
        $this->assertSame([1, 700, 5000000], [$get['status'], $get['all_gold'], $get['all_silver']]);
        $this->assertSame(self::today('00:00:00') + 86400 - self::today(), $get['cd']);
        $this->assertSame([$leap + 50, $purple + 50, 5000], [FreeLeap::get($uid), TraderLog::jade($uid, 3), self::memberValue($uid, 'soul')]);
        $this->assertSame(1, DB::table('anniversary_action_log')->where('uid', $uid)->count());
        // 今天已领
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'anniversary', 'get_reward'));
        $this->assertSame(1, $this->request($p['auth'], 'anniversary', 'index')['is_get']);
        // 领取锁
        Redis::set('anniversary_lock_' . $uid, 1, 60);
        $this->assertSame(['status' => -5], $this->request($p['auth'], 'anniversary', 'get_reward'));
        Redis::del('anniversary_lock_' . $uid);
        // 第二天可以再领
        BossClock::freeze(self::today() + 86400);
        $this->assertSame(1, $this->request($p['auth'], 'anniversary', 'get_reward')['status']);
        $this->assertSame(400, self::memberValue($uid, 'gold'));
    }

    // ---------------------------------------------------------------------------------------------
    // 国庆红包
    // ---------------------------------------------------------------------------------------------

    public function testNationalDayGoldLogRedPacketAndCron(): void
    {
        $p = $this->player(['gold' => 10000]);
        $uid = $p['uid'];
        // 活动未开启：花元宝不记国庆流水
        Member::deductCurrency($uid, gold: 100);
        $this->assertFalse(DB::table('national_day_gold_log')->where('uid', $uid)->exists());
        BossClock::freeze(self::today());
        $this->assertSame(-1, $this->request($p['auth'], 'act_national_day', 'index')['status']);

        self::open('national_day');
        $act = ActivityClock::byName('national_day');
        Member::deductCurrency($uid, gold: 1234);
        $row = DB::table('national_day_gold_log')->where('uid', $uid)->first();
        $this->assertSame([(int)$act['version'], 2, 1234, 10000 - 100 - 1234], [(int)$row['version'], (int)$row['type'], (int)$row['gold'], (int)$row['balance']]);
        // 加元宝不记
        Member::addCurrency($uid, gold: 10);
        $this->assertSame(1, DB::table('national_day_gold_log')->where('uid', $uid)->count());

        // 今天的红包明天才能领
        $today = date('Ymd');
        $index = $this->request($p['auth'], 'act_national_day', 'index');
        $this->assertSame([1, 0, 2, $today], [$index['status'], $index['list'][0]['gold'], $index['list'][0]['status'], $index['list'][0]['ymd']]);
        $this->assertSame(['status' => -3, 'msg' => '禁止领取'], $this->request($p['auth'], 'act_national_day', 'open_red_packets', ['ymd' => $today]));
        BossClock::freeze(self::today() + 86400);
        $index = $this->request($p['auth'], 'act_national_day', 'index');
        $this->assertSame([123, 0, (int)date('m') . '月' . (int)date('d') . '日红包'], [$index['list'][0]['gold'], $index['list'][0]['status'], $index['list'][0]['title']]);
        $this->assertSame(['status' => -2, 'msg' => '参数错误'], $this->request($p['auth'], 'act_national_day', 'open_red_packets'));
        $this->assertSame(-3, $this->request($p['auth'], 'act_national_day', 'open_red_packets', ['ymd' => date('Ymd', self::today() - 86400)])['status']);
        $gold = self::memberValue($uid, 'gold');
        $open = $this->request($p['auth'], 'act_national_day', 'open_red_packets', ['ymd' => $today]);
        $this->assertSame(['gold' => $gold + 123, 'status' => 1], $open);
        $this->assertSame(['status' => -4, 'msg' => '已经领取过了'], $this->request($p['auth'], 'act_national_day', 'open_red_packets', ['ymd' => $today]));
        $this->assertSame(1, $this->request($p['auth'], 'act_national_day', 'index')['list'][0]['status']);
        // 操作锁
        Redis::set('national_dat_get_reward' . $uid, 1, 60);
        $this->assertSame(-6, $this->request($p['auth'], 'act_national_day', 'open_red_packets', ['ymd' => $today])['status']);
        Redis::del('national_dat_get_reward' . $uid);

        // 计划任务：活动结束后 10 小时内补发没领的红包（只统计到 endtime - 86401）
        $q = $this->player(['gold' => 0]);
        $start = (int)$act['starttime'];
        foreach ([[$q['uid'], 300, $start + 3600], [$q['uid'], 255, $start + 7200], [$q['uid'], 999, (int)$act['endtime'] - 3600]] as [$u, $g, $t]) {
            DB::table('national_day_gold_log')->insert(['uid' => $u, 'version' => (int)$act['version'], 'level' => 1, 'type' => 2, 'gold' => $g, 'balance' => 0, 'dateline' => $t, 'vip' => 0]);
        }
        $this->assertSame(-2, ActNationalDayHandler::cronReward((int)$act['endtime'] - 1));
        $this->assertSame(1, ActNationalDayHandler::cronReward((int)$act['endtime'] + 100));
        $this->assertSame(56, self::memberValue($q['uid'], 'gold'));
        $this->assertSame(1, self::messages($q['uid']));
        $this->assertSame([1, 555], [DB::table('national_day_log')->where('uid', $q['uid'])->count(), (int)DB::table('national_day_log')->where('uid', $q['uid'])->value('gold')]);
        // 已领过的不补发，重复执行不重复发
        $this->assertSame($gold + 123, self::memberValue($uid, 'gold'));
        ActNationalDayHandler::cronReward((int)$act['endtime'] + 200);
        $this->assertSame([56, 1], [self::memberValue($q['uid'], 'gold'), self::messages($q['uid'])]);
        $this->assertSame(-3, ActNationalDayHandler::cronReward((int)$act['endtime'] + 36001));
    }

    // ---------------------------------------------------------------------------------------------
    // 圣诞礼包
    // ---------------------------------------------------------------------------------------------

    public function testChristmasgiftBuyVipAndGlobalLimit(): void
    {
        $p = $this->player(['gold' => 100, 'silver' => 0, 'soul' => 0, 'vip' => 0]);
        $uid = $p['uid'];
        BossClock::freeze(self::today());
        $this->assertSame(['status' => 2], $this->request($p['auth'], 'christmasgift', 'index'));

        self::open('christmasgift');
        $index = $this->request($p['auth'], 'christmasgift', 'index');
        $this->assertSame([1, count(ChristmasgiftReward::ROWS), 1], [$index['status'], count($index['list']), $index['type']]);
        // 限售 200 的坐骑礼包：今日剩余 200；坐骑换成模板（0 级加成），星魄换成 id / 数量；VIP 不够显示灰色
        $gift8 = $index['list'][7];
        $this->assertSame([200, 1, 0, 0], [$gift8['buytimes'], $gift8['is_buy'], $gift8['is_get'], $gift8['is_open']]);
        $this->assertSame([21, 0], [$gift8['getvalue1']['id'], $gift8['getvalue1']['level']]);
        $this->assertSame(['id' => 1, 'num' => 50000], $index['list'][10]['getvalue2']);
        $this->assertSame([0, 1], [$index['list'][0]['buytimes'], $index['list'][0]['is_open']]);

        $this->assertSame(['status' => -1], $this->request($p['auth'], 'christmasgift', 'get_reward'));
        $this->assertSame(['status' => -8], $this->request($p['auth'], 'christmasgift', 'get_reward', ['id' => 3]));
        $this->assertSame(['status' => -5], $this->request($p['auth'], 'christmasgift', 'get_reward', ['id' => 1]));
        DB::table('member')->where('uid', $uid)->update(['gold' => 100000, 'vip' => 4]);
        $leap = FreeLeap::get($uid);
        $purple = TraderLog::jade($uid, 3);
        $get = $this->request($p['auth'], 'christmasgift', 'get_reward', ['id' => 1]);
        $this->assertSame([1, 100000 - 250, 5000000], [$get['status'], $get['all_gold'], $get['all_silver']]);
        $this->assertSame([$leap + 100, 10000, $purple + 100], [FreeLeap::get($uid), self::memberValue($uid, 'soul'), TraderLog::jade($uid, 3)]);
        $this->assertSame(1, $get['list'][0]['is_get']);
        $this->assertSame([1, 1], [(int)DB::table('christmasgift_log')->where('uid', $uid)->value('itemid'), (int)DB::table('christmasgift_action_log')->where('uid', $uid)->value('type')]);
        $this->assertSame(['status' => -3], $this->request($p['auth'], 'christmasgift', 'get_reward', ['id' => 1]));

        // 全服限售：卖完回 -4；买到时计数加 1
        $key = 'christmasgift_buytimes_' . date('ymd') . '_8';
        Redis::set($key, 200, 86400);
        $this->assertSame(['status' => -4], $this->request($p['auth'], 'christmasgift', 'get_reward', ['id' => 8]));
        Redis::set($key, 5, 86400);
        $mounts = DB::table('own_mount')->where('uid', $uid)->count();
        $get = $this->request($p['auth'], 'christmasgift', 'get_reward', ['id' => 8]);
        $this->assertSame([1, 194], [$get['status'], $get['list'][7]['buytimes']]);
        $this->assertSame($mounts + 4, DB::table('own_mount')->where('uid', $uid)->count());
        $this->assertSame(6, (int)Redis::get($key));
        // 星魄礼包（VIP 5）
        DB::table('member')->where('uid', $uid)->update(['vip' => 5, 'gold' => 100000]);
        $this->assertSame(1, $this->request($p['auth'], 'christmasgift', 'get_reward', ['id' => 11])['status']);
        $this->assertSame([50000, 15000, 10000], array_map('intval', array_values(DB::table('own_star')->select('star1', 'star2', 'star3')->where('uid', $uid)->first())));
        // 购买锁
        Redis::set('christmasgift_lock_' . $uid, 1, 60);
        $this->assertSame(['status' => -7], $this->request($p['auth'], 'christmasgift', 'get_reward', ['id' => 2]));
    }

    // ---------------------------------------------------------------------------------------------
    // 七夕
    // ---------------------------------------------------------------------------------------------

    public function testTanabataPayOrderTierRankAndCron(): void
    {
        $p = $this->player(['silver' => 0, 'reputation' => 0]);
        $uid = $p['uid'];
        $other = $this->player();
        $this->assertSame(['status' => -1, 'msg' => '活动未开启'], $this->request($p['auth'], 'act_tanabata', 'index'));
        // 未开启时充值不记
        Pay::grantManual($uid, 2);
        $this->assertFalse(DB::table('act_tanabata_order')->exists());

        self::open('acttanabata');
        $act = ActivityClock::byName('acttanabata');
        BossClock::freeze(self::today());
        Pay::grantManual($uid, 2);
        $order = DB::table('act_tanabata_order')->where('uid', $uid)->first();
        $this->assertSame([300, (int)$act['version'], 'pay', 'manual'], [(int)$order['gold'], (int)$order['version'], $order['class'], $order['method']]);
        DB::table('act_tanabata_order')->insert(['uid' => $other['uid'], 'nickname' => 'big', 'level' => 1, 'gold' => 700000, 'version' => (int)$act['version'], 'dateline' => self::today()]);

        $index = $this->request($p['auth'], 'act_tanabata', 'index');
        $this->assertSame([1, 700300, 8], [$index['status'], $index['all_gold'], count($index['list'])]);
        $this->assertSame([1, 0], [$index['list'][0]['is_re'], $index['list'][1]['is_re']]);
        $this->assertSame([$other['uid'], $uid], array_column($index['rank_list'], 'uid'));
        $this->assertSame([2, 300, 0], [$index['my_rank']['rank'], $index['my_rank']['total_gold'], $index['my_rank']['is_reward']]);
        $this->assertSame([103, 0], [$index['mount']['id'], $index['mount']['level']]);
        $this->assertSame((int)$act['endtime'] - 86401 - self::today(), $index['cd_time']);

        $this->assertSame(-4, $this->request($p['auth'], 'act_tanabata', 'reward')['status']);
        $this->assertSame(-2, $this->request($p['auth'], 'act_tanabata', 'reward', ['id' => 99])['status']);
        $this->assertSame(6, $this->request($p['auth'], 'act_tanabata', 'reward', ['id' => 2])['status']);
        $nonPayer = $this->player();
        $this->assertSame(3, $this->request($nonPayer['auth'], 'act_tanabata', 'reward', ['id' => 1])['status']);
        $jade = MemberJade::get($uid);
        $reward = $this->request($p['auth'], 'act_tanabata', 'reward', ['id' => 1]);
        $this->assertSame([1, 10000000, 1], [$reward['status'], $reward['all_silver'], $reward['reward']['id']]);
        $this->assertSame([$jade + 100000, 100000], [MemberJade::get($uid), self::memberValue($uid, 'reputation')]);
        $this->assertSame(4, $this->request($p['auth'], 'act_tanabata', 'reward', ['id' => 1])['status']);
        // 操作锁
        Redis::set('tana_reward_' . $uid, 1, 60);
        $this->assertSame(2, $this->request($p['auth'], 'act_tanabata', 'reward', ['id' => 1])['status']);
        Redis::del('tana_reward_' . $uid);

        // 排行奖励：结束时间 - 86401 之后才能领
        $this->assertSame(3, $this->request($p['auth'], 'act_tanabata', 'rank_reward')['status']);
        BossClock::freeze((int)$act['endtime'] - 86401 + 10);
        $this->assertSame(1, $this->request($p['auth'], 'act_tanabata', 'index')['my_rank']['is_reward']);
        $purple = TraderLog::jade($uid, 3);
        $this->assertSame(1, $this->request($p['auth'], 'act_tanabata', 'rank_reward')['status']);
        $this->assertSame($purple + ActTanabataRankreward::ROWS[2]['num'], TraderLog::jade($uid, 3));
        $this->assertSame(4, $this->request($p['auth'], 'act_tanabata', 'rank_reward')['status']);
        $this->assertSame(5, $this->request($nonPayer['auth'], 'act_tanabata', 'rank_reward')['status']);

        // 计划任务：结束后补发没领的排行奖励与档次奖励
        $this->assertSame(-2, ActTanabataHandler::cronReward((int)$act['endtime'] - 1));
        $otherPurple = TraderLog::jade($other['uid'], 3);
        $otherSilver = self::memberValue($other['uid'], 'silver');
        $this->assertSame(1, ActTanabataHandler::cronReward((int)$act['endtime'] + 100));
        $this->assertSame($otherPurple + 2000 + 100000, TraderLog::jade($other['uid'], 3));
        $this->assertSame($otherSilver + 10000000, self::memberValue($other['uid'], 'silver'));
        $this->assertSame(2, self::messages($other['uid'], '土豪的爱'));
        $this->assertSame(0, self::messages($uid, '土豪的爱'), '已领过的不补发');
        ActTanabataHandler::cronReward((int)$act['endtime'] + 200);
        $this->assertSame(2, self::messages($other['uid'], '土豪的爱'));
    }

    // ---------------------------------------------------------------------------------------------
    // 国家庆典
    // ---------------------------------------------------------------------------------------------

    public function testCelebrationIdentityCountryLeaveAndCron(): void
    {
        $p = $this->player(['country' => 7, 'silver' => 0, 'reputation' => 0]);
        $q = $this->player(['country' => 7, 'silver' => 0]);
        $r = $this->player(['country' => 0]);
        $uid = $p['uid'];
        BossClock::freeze(self::today());
        $this->assertSame(-1, $this->request($p['auth'], 'act_celebration', 'index')['status']);

        self::open('national_celebration');
        $act = ActivityClock::byName('national_celebration');
        $version = (int)$act['version'];
        $this->assertSame(['status' => -4, 'msg' => '您没有国家，无法参加该活动。'], $this->request($r['auth'], 'act_celebration', 'index'));
        Pay::grantManual($uid, 4);
        $this->assertSame(1980, (int)DB::table('act_celebration_order')->where('uid', $uid)->value('gold'));
        DB::table('act_celebration_order')->insert(['uid' => $q['uid'], 'nickname' => 'q', 'level' => 1, 'gold' => 300, 'version' => $version, 'dateline' => self::today()]);
        DB::table('act_celebration_order')->insert(['uid' => $r['uid'], 'nickname' => 'r', 'level' => 1, 'gold' => 90000, 'version' => $version, 'dateline' => self::today()]);

        $index = $this->request($p['auth'], 'act_celebration', 'index');
        $this->assertSame([1, 2280, 1980, '中农', 2], [$index['status'], $index['all_gold'], $index['my_gold'], $index['name'], $index['quality']]);
        $this->assertSame([[$uid, 1980, '中农', 2], [$q['uid'], 300, '小农', 1]], array_map(static fn (array $v): array => [$v['uid'], $v['gold'], $v['name'], $v['quality']], $index['country_list']));
        $this->assertSame([7, 0], [count($index['member_list']), $index['list'][0]['is_re']]);
        $this->assertSame([1, 1, 0], [$index['member_list'][0]['is_re'], $index['member_list'][1]['is_re'], $index['member_list'][2]['is_re']]);
        // 王侯的坐骑奖励换成 info 并交换 num / value
        $this->assertSame([104, 1, 104], [$index['member_list'][6]['info1']['id'], $index['member_list'][6]['num1'], $index['member_list'][6]['value1']]);
        $this->assertSame(104, $index['mount']['id']);

        $this->assertSame(3, $this->request($p['auth'], 'act_celebration', 'get_my_reward', ['reward_id' => 1])['status']);
        $this->assertSame(5, $this->request($p['auth'], 'act_celebration', 'get_my_reward', ['reward_id' => 4])['status']);
        $this->assertSame(-4, $this->request($r['auth'], 'act_celebration', 'get_my_reward', ['reward_id' => 2])['status']);
        $leap = FreeLeap::get($uid);
        $get = $this->request($p['auth'], 'act_celebration', 'get_my_reward', ['reward_id' => 2]);
        $this->assertSame([1, 5000000], [$get['status'], $get['all_silver']]);
        $this->assertSame(['type1' => 10, 'num1' => 100, 'value1' => 0, 'type2' => 4, 'num2' => 50, 'value2' => 0, 'type3' => 2, 'num3' => 5000000, 'value3' => 0], $get['reward_info']);
        $this->assertSame($leap + 100, FreeLeap::get($uid));
        $this->assertSame(4, $this->request($p['auth'], 'act_celebration', 'get_my_reward', ['reward_id' => 2])['status']);
        $this->assertSame(2, $this->request($p['auth'], 'act_celebration', 'index')['member_list'][0]['is_re']);

        // 退出国家：自动领取未领的身份奖励并发信件
        ActCelebrationHandler::leaveCountryReward($q['uid'], self::today());
        $this->assertSame([5000000, 1], [self::memberValue($q['uid'], 'silver'), self::messages($q['uid'])]);
        ActCelebrationHandler::leaveCountryReward($q['uid'], self::today());
        $this->assertSame(1, self::messages($q['uid']));

        // 计划任务：结束前一天起补发身份奖励与国家充值档次奖励（本国合计达到 4 万，充值满 300 的成员可得）
        DB::table('act_celebration_order')->insert(['uid' => $uid, 'nickname' => 'p', 'level' => 1, 'gold' => 38000, 'version' => $version, 'dateline' => self::today()]);
        $this->assertSame(-2, ActCelebrationHandler::cronReward((int)$act['endtime'] - 86401));
        $qGold = self::memberValue($q['uid'], 'gold');
        $pRep = self::memberValue($uid, 'reputation');
        $this->assertSame(1, ActCelebrationHandler::cronReward((int)$act['endtime'] - 3600));
        // p：没领的身份 3 ~ 5（中农、富农、商人）合并发放 + 国家第 1 档；q：国家第 1 档
        $this->assertSame($pRep + 6000 + 10000 + 15000, self::memberValue($uid, 'reputation'));
        $this->assertSame($qGold + 200, self::memberValue($q['uid'], 'gold'));
        $this->assertSame([2, 2], [self::messages($uid, '国家庆典'), self::messages($q['uid'], '国家庆典')]);
        $this->assertTrue(DB::table('act_celebration_reward_log')->where('version', $version)->where('country_id', 7)->where('reward_id', 1)->exists());
        $this->assertSame(0, self::messages($r['uid']), '没有国家不发');
        ActCelebrationHandler::cronReward((int)$act['endtime']);
        $this->assertSame([2, 2], [self::messages($uid, '国家庆典'), self::messages($q['uid'], '国家庆典')]);
        $this->assertSame(2, $this->request($p['auth'], 'act_celebration', 'index')['list'][0]['is_re']);
    }

    // ---------------------------------------------------------------------------------------------
    // 清明祭祖
    // ---------------------------------------------------------------------------------------------

    public function testChingmingActionAchievementRankAndCron(): void
    {
        $p = $this->player(['gold' => 5000, 'level' => 40, 'vip' => 0, 'soul' => 0]);
        $uid = $p['uid'];
        BossClock::freeze(self::today());
        $this->assertSame(['status' => -1, 'vip' => 2], $this->request($p['auth'], 'chingming', 'index'));

        self::open('chingming');
        $act = ActivityClock::byName('chingming');
        $index = $this->request($p['auth'], 'chingming', 'index');
        $this->assertSame([1, 2, 0, 100, 30, 3000, 0], [$index['status'], $index['vip'], $index['times'], $index['next_times'], $index['price'], $index['hundred'], $index['type']]);
        $this->assertSame((int)$act['endtime'] - 86400 - self::today(), $index['cd']);

        // 百次祭祖
        mt_srand(1);
        $action = $this->request($p['auth'], 'chingming', 'action', ['type' => 1]);
        $this->assertSame([1, 100, 0, 100, 0, 2000], [$action['status'], $action['num'], $action['free'], $action['times'], $action['next_times'], $action['all_gold']]);
        foreach ($action['reward'] as $reward) {
            $this->assertContains($reward['type'], [1, 2, 3, 4, 5, 6, 7]);
        }
        // 下一次盛大祭祖免费、奖励 10 倍、次数不加
        $action = $this->request($p['auth'], 'chingming', 'action');
        $this->assertSame([1, 1, 1, 100, 100, 2000], [$action['status'], $action['num'], $action['free'], $action['times'], $action['next_times'], $action['all_gold']]);
        $this->assertSame(0, $action['reward'][0]['value'] % 10);
        $this->assertSame(1, $this->request($p['auth'], 'chingming', 'action')['status']);
        $this->assertSame(-4, $this->request($p['auth'], 'chingming', 'action', ['type' => 1])['status']);
        DB::table('member')->where('uid', $uid)->update(['gold' => 10]);
        $this->assertSame(-5, $this->request($p['auth'], 'chingming', 'action')['status']);
        $this->assertSame(1, DB::table('chingming_action_log')->where('uid', $uid)->where('gold', 0)->count());

        // 成就
        $list = $this->request($p['auth'], 'chingming', 'get_achievement_list');
        $this->assertSame([1, 2, 1, 0], [$list['status'], $list['vip'], $list['list'][0]['open'], $list['list'][1]['open']]);
        $this->assertSame([\Hanjiang\MasterData\Equip::ROWS[62]['name'], \Hanjiang\MasterData\Equip::ROWS[62]['quality']], [$list['list'][4]['value2'], $list['list'][4]['value2_quality']]);
        $this->assertSame(-3, $this->request($p['auth'], 'chingming', 'get_achievement_reward')['status']);
        $this->assertSame(-4, $this->request($p['auth'], 'chingming', 'get_achievement_reward', ['id' => 2])['status']);
        $soul = self::memberValue($uid, 'soul');
        $get = $this->request($p['auth'], 'chingming', 'get_achievement_reward', ['id' => 1]);
        $this->assertSame([1, 0], [$get['status'], $get['type']]);
        $this->assertSame($soul + 5000, self::memberValue($uid, 'soul'));
        $this->assertSame(-5, $this->request($p['auth'], 'chingming', 'get_achievement_reward', ['id' => 1])['status']);

        // 排行要求 VIP 2：主城同步 VIP
        $this->assertSame([], $this->request($p['auth'], 'chingming', 'get_rank_list')['list']);
        DB::table('member')->where('uid', $uid)->update(['vip' => 2]);
        Redis::del('player:' . $uid);
        $this->request($p['auth'], 'member', 'index');
        $this->assertSame(2, (int)DB::table('chingming_log')->where('uid', $uid)->value('vip'));
        $rank = $this->request($p['auth'], 'chingming', 'get_rank_list');
        $this->assertSame([1, 0, 0], [$rank['status'], $rank['vip'], $rank['isget']]);
        $this->assertSame(['uid' => $uid, 'nickname' => 'f2_' . $uid, 'vip' => 2, 'times' => 101, 'rank' => 1, 'type' => 5, 'value' => 200000], $rank['list'][0]);
        $this->assertSame(-2, $this->request($p['auth'], 'chingming', 'get_rank_reward')['status']);
        BossClock::freeze((int)$act['endtime'] - 86400 + 10);
        $this->assertSame(-2, $this->request($p['auth'], 'chingming', 'action')['status']);
        $this->assertSame(1, $this->request($p['auth'], 'chingming', 'get_rank_list')['isget']);
        $soul = self::memberValue($uid, 'soul');
        $get = $this->request($p['auth'], 'chingming', 'get_rank_reward');
        $this->assertSame([1, 5, 200000], [$get['status'], $get['reward']['type'], $get['reward']['value']]);
        $this->assertSame($soul + 200000, self::memberValue($uid, 'soul'));
        $this->assertSame(-5, $this->request($p['auth'], 'chingming', 'get_rank_reward')['status']);

        // 计划任务：补发排行与成就奖励（两件红装逐件发放）
        $q = $this->player(['vip' => 2]);
        DB::table('chingming_log')->insert(['uid' => $q['uid'], 'version' => (int)$act['version'], 'nickname' => 'q', 'vip' => 2, 'times' => 1000, 'free_times' => 0, 'get_rank' => 0, 'dateline' => self::today()]);
        $this->assertSame(-2, ChingmingHandler::cronRankReward((int)$act['endtime'] - 1));
        $soul = self::memberValue($q['uid'], 'soul');
        $this->assertSame(1, ChingmingHandler::cronRankReward((int)$act['endtime'] + 100));
        $this->assertSame([$soul + 200000, 1], [self::memberValue($q['uid'], 'soul'), (int)DB::table('chingming_log')->where('uid', $q['uid'])->value('get_rank')]);
        $this->assertSame(1, ChingmingHandler::cronAchievementReward((int)$act['endtime'] + 100));
        $this->assertSame(5, DB::table('chingming_achievement_log')->where('uid', $q['uid'])->count());
        $this->assertSame([1, 1], [DB::table('equipment')->where('uid', $q['uid'])->where('equipid', 62)->count(), DB::table('equipment')->where('uid', $q['uid'])->where('equipid', 65)->count()]);
        $this->assertSame(2, self::messages($q['uid']));
        // p 已领过成就 1，补发 2 ~ … 没达到（101 次）：只补没领的且达到的（无）
        $this->assertSame(0, self::messages($uid));
        $this->assertSame(-6, ChingmingHandler::cronAchievementReward((int)$act['endtime'] + 200));
        ChingmingHandler::cronRankReward((int)$act['endtime'] + 200);
        $this->assertSame(2, self::messages($q['uid']));
    }

    // ---------------------------------------------------------------------------------------------
    // 圣诞团购
    // ---------------------------------------------------------------------------------------------

    public function testChristmasbuyCartDiscountBuyRebateAndCron(): void
    {
        $p = $this->player(['gold' => 500, 'silver' => 0]);
        $uid = $p['uid'];
        BossClock::freeze(self::today());
        $this->assertSame(-1, $this->request($p['auth'], 'christmasbuy', 'index')['status']);

        self::open('christmasbuy');
        $act = ActivityClock::byName('christmasbuy');
        $index = $this->request($p['auth'], 'christmasbuy', 'index');
        $this->assertSame([1, [], ['price' => 0, 'discount' => 0, 'gold' => 0]], [$index['status'], $index['carinfo'], $index['car_array']]);
        $this->assertSame(['id' => 1, 'name' => '黄宝石', 'type' => 2, 'price' => 480, 'gnum' => 100, 'num' => 80], $index['list'][0]);
        // 锻造材料商品带材料信息
        $material = array_values(array_filter($index['list'], static fn (array $v): bool => $v['type'] === 14))[0];
        $this->assertArrayHasKey('info', $material);
        $this->assertArrayNotHasKey('value', $material);

        $this->assertSame(-2, $this->request($p['auth'], 'christmasbuy', 'add_good')['status']);
        $this->assertSame(-3, $this->request($p['auth'], 'christmasbuy', 'add_good', ['id' => 999])['status']);
        $this->assertSame(-4, $this->request($p['auth'], 'christmasbuy', 'del_car_good', ['id' => 1])['status']);
        for ($i = 0; $i < 3; $i++) {
            $add = $this->request($p['auth'], 'christmasbuy', 'add_good', ['id' => 1]);
        }
        $this->assertSame([1, ['1' => 3], 77], [$add['status'], $add['car_info'], $add['list'][0]['num']]);
        $this->assertSame([1440, 0.95, 1368], [$add['car_array']['price'], round($add['car_array']['discount'], 2), (int)$add['car_array']['gold']]);
        $del = $this->request($p['auth'], 'christmasbuy', 'del_car_good', ['id' => 1]);
        $this->assertSame([['1' => 2], 78], [$del['car_info'], $del['list'][0]['num']]);
        // 元宝不足；结算：960 不到 1000，不打折
        $this->assertSame(-4, $this->request($p['auth'], 'christmasbuy', 'buy_good')['status']);
        DB::table('member')->where('uid', $uid)->update(['gold' => 5000]);
        $yellow = TraderLog::jade($uid, 2);
        $buy = $this->request($p['auth'], 'christmasbuy', 'buy_good');
        $this->assertSame([1, [], 5000 - 960, 78], [$buy['status'], $buy['car_info'], $buy['gold'], $buy['list'][0]['num']]);
        $this->assertSame($yellow + 200, TraderLog::jade($uid, 2));
        $this->assertSame([960, 2], [(int)DB::table('christmasbuy_info')->where('uid', $uid)->value('gold'), (int)DB::table('christmasbuy_log')->where('uid', $uid)->value('goods_num')]);
        $this->assertSame(-3, $this->request($p['auth'], 'christmasbuy', 'buy_good')['status']);
        // 每天限购：今天已买满
        DB::table('christmasbuy_log')->insert(['uid' => $uid, 'version' => (int)$act['version'], 'goods_id' => 1, 'goods_num' => 78, 'dateline' => self::today()]);
        $this->assertSame(-4, $this->request($p['auth'], 'christmasbuy', 'add_good', ['id' => 1])['status']);

        // 返利
        $rebate = $this->request($p['auth'], 'christmasbuy', 'rebate_reward_index');
        $this->assertSame([1, 960, 8, 0, 4000], [$rebate['status'], $rebate['allgold'], count($rebate['list']), $rebate['list'][0]['button_status'], $rebate['list'][0]['limit']]);
        $this->assertSame(['type' => 1, 'num' => 200000], $rebate['list'][0]['reward_info'][0]);
        $this->assertSame(-4, $this->request($p['auth'], 'christmasbuy', 'rebate_reward', ['id' => 1])['status']);
        $this->assertSame(-3, $this->request($p['auth'], 'christmasbuy', 'rebate_reward', ['id' => 99])['status']);
        DB::table('christmasbuy_info')->insert(['uid' => $uid, 'version' => (int)$act['version'], 'gold' => 3040, 'dateline' => self::today()]);
        $silver = self::memberValue($uid, 'silver');
        $get = $this->request($p['auth'], 'christmasbuy', 'rebate_reward', ['id' => 1]);
        $this->assertSame([1, 4000, $silver + 200000], [$get['status'], $get['allgold'], $get['silver']]);
        $this->assertSame(-5, $this->request($p['auth'], 'christmasbuy', 'rebate_reward', ['id' => 1])['status']);
        $this->assertSame(2, $this->request($p['auth'], 'christmasbuy', 'rebate_reward_index')['list'][0]['button_status']);
        // 玩法介绍
        $intro = $this->request($p['auth'], 'christmasbuy', 'play_introduce');
        $this->assertSame([1, ['price' => 1000, 'discount' => 9.5]], [$intro['status'], $intro['info'][0]]);
        // 操作锁
        Redis::set('_buy_good_lock_key' . $uid, 1, 60);
        $this->assertSame(-5, $this->request($p['auth'], 'christmasbuy', 'buy_good')['status']);

        // 计划任务：补发没领的返利（同类合并）
        $q = $this->player(['silver' => 0]);
        DB::table('christmasbuy_info')->insert(['uid' => $q['uid'], 'version' => (int)$act['version'], 'gold' => 20000, 'dateline' => self::today()]);
        $this->assertSame(-2, ChristmasbuyHandler::cronReward((int)$act['endtime'] - 1));
        $purple = TraderLog::jade($q['uid'], 3);
        $this->assertSame(1, ChristmasbuyHandler::cronReward((int)$act['endtime'] + 100));
        $this->assertSame([800000, $purple + 120, 1], [self::memberValue($q['uid'], 'silver'), TraderLog::jade($q['uid'], 3), self::messages($q['uid'])]);
        $this->assertSame(0, self::messages($uid));
        ChristmasbuyHandler::cronReward((int)$act['endtime'] + 200);
        $this->assertSame([800000, 1], [self::memberValue($q['uid'], 'silver'), self::messages($q['uid'])]);
    }

    // ---------------------------------------------------------------------------------------------
    // 万圣节
    // ---------------------------------------------------------------------------------------------

    public function testHalloweenCandyPumpkinMedalAndCron(): void
    {
        $p = $this->player(['gold' => 0, 'silver' => 0]);
        $uid = $p['uid'];
        BossClock::freeze(self::today());
        $this->assertSame(['status' => -1, 'msg' => '活动未开启'], $this->request($p['auth'], 'act_halloween', 'index'));

        self::open('halloween');
        $act = ActivityClock::byName('halloween');
        $index = $this->request($p['auth'], 'act_halloween', 'index');
        $this->assertSame([1, 0, 0, 35, 300, 1, 9], [$index['status'], $index['candy'], $index['hammer'], $index['buy_hammer_price'], $index['all_price'], $index['all_button_status'], count($index['list'])]);
        $this->assertSame(['id' => 1, 'status' => 0], $index['list'][0]);
        $this->assertSame(date('m.d', (int)$act['starttime']) . '~' . date('m.d', (int)$act['endtime']), $index['date']);

        // 吃糖果
        $this->assertSame(-4, $this->request($p['auth'], 'act_halloween', 'action_candy')['status']);
        MemberIntegral::change($uid, 2, 'candy', 1, 'test', 'test');
        mt_srand(1);
        $candy = $this->request($p['auth'], 'act_halloween', 'action_candy');
        $this->assertSame([1, 1], [$candy['status'], $candy['candy']]);
        $this->assertContains($candy['reward']['type'], [3, 4, 5, 6, 7, 10, 11, 29]);
        $this->assertSame(1, (int)MemberIntegral::get($uid)['candy']);
        $this->assertSame($candy['reward']['type'], (int)DB::table('halloween_candy_log')->where('uid', $uid)->value('type'));

        // 买锤子、砸南瓜
        $this->assertSame(-3, $this->request($p['auth'], 'act_halloween', 'buy_hammer')['status']);
        DB::table('member')->where('uid', $uid)->update(['gold' => 1000]);
        $hammer = (int)MemberIntegral::get($uid)['hammer'];
        $buy = $this->request($p['auth'], 'act_halloween', 'buy_hammer');
        $this->assertSame([1, $hammer + 1, 965], [$buy['status'], $buy['hammer'], $buy['gold']]);
        $this->assertSame(-3, $this->request($p['auth'], 'act_halloween', 'action_pumpkin', ['id' => 10])['status']);
        $smash = $this->request($p['auth'], 'act_halloween', 'action_pumpkin', ['id' => 1]);
        $this->assertSame([1, $hammer, 0, 0], [$smash['status'], $smash['hammer'], $smash['reset'], $smash['all_button_status']]);
        $this->assertSame(-5, $this->request($p['auth'], 'act_halloween', 'action_pumpkin', ['id' => 1])['status']);
        if ($hammer === 0) {
            $this->assertSame(-6, $this->request($p['auth'], 'act_halloween', 'action_pumpkin', ['id' => 2])['status']);
        }
        $this->assertSame([0, 1], [$this->request($p['auth'], 'act_halloween', 'index')['all_button_status'], $this->request($p['auth'], 'act_halloween', 'index')['list'][0]['status']]);
        $this->assertSame(-4, $this->request($p['auth'], 'act_halloween', 'action_all_pumpkin')['status']);
        // 砸完 9 个重置
        MemberIntegral::change($uid, 8, 'hammer', 1, 'test', 'test');
        for ($i = 2; $i <= 9; $i++) {
            $smash = $this->request($p['auth'], 'act_halloween', 'action_pumpkin', ['id' => $i]);
            $this->assertSame(1, $smash['status']);
        }
        $this->assertSame([1, 1], [$smash['reset'], $smash['all_button_status']]);
        $this->assertSame(1, (int)DB::table('halloween')->where('uid', $uid)->value('times'));
        $this->assertSame(9, DB::table('halloween_pumpkin_log')->where('uid', $uid)->count());

        // 砸九次（300 元宝）
        DB::table('member')->where('uid', $uid)->update(['gold' => 100]);
        $this->assertSame(-6, $this->request($p['auth'], 'act_halloween', 'action_all_pumpkin')['status']);
        DB::table('member')->where('uid', $uid)->update(['gold' => 1000]);
        $all = $this->request($p['auth'], 'act_halloween', 'action_all_pumpkin');
        $this->assertSame([1, 700], [$all['status'], $all['gold']]);
        $this->assertNotEmpty($all['reward']);
        $this->assertSame([2, 9], [(int)DB::table('halloween')->where('uid', $uid)->value('times'), DB::table('halloween_log')->where('uid', $uid)->where('times', 1)->count()]);

        // 奖章商城
        $medal = $this->request($p['auth'], 'act_halloween', 'medal_index');
        $this->assertSame([1, (int)MemberIntegral::get($uid)['medal']], [$medal['status'], $medal['medal']]);
        $mountItem = array_values(array_filter($medal['list'], static fn (array $v): bool => $v['type'] === 26))[0];
        $this->assertSame([1, 0], [$mountItem['value'], $mountItem['info']['level']]);
        $this->assertArrayNotHasKey('value1', $mountItem);
        $soulItem = array_values(array_filter($medal['list'], static fn (array $v): bool => $v['type'] === 31))[0];
        $this->assertSame(\Hanjiang\MasterData\Soul::ROWS[65]['name'], $soulItem['info']['name']);
        $this->assertSame(-3, $this->request($p['auth'], 'act_halloween', 'action_medal')['status']);
        DB::table('member_integral')->where('uid', $uid)->update(['medal' => 0]);
        $this->assertSame(-5, $this->request($p['auth'], 'act_halloween', 'action_medal', ['id' => 1])['status']);
        DB::table('member_integral')->where('uid', $uid)->update(['medal' => 25]);
        $silver = self::memberValue($uid, 'silver');
        $get = $this->request($p['auth'], 'act_halloween', 'action_medal', ['id' => 1]);
        $this->assertSame([1, 15, $silver + 2400000, ['type' => 2, 'value' => 2400000]], [$get['status'], $get['medal'], $get['silver'], $get['reward']]);
        $this->assertSame(1, DB::table('halloween_medal2_log')->where('uid', $uid)->where('reward_id', 1)->count());
        $this->assertSame(-6, $this->request($p['auth'], 'act_halloween', 'action_medal', ['id' => 999])['status']);

        // 计划任务：结束后 1 小时内奖章按 2 万银币兑换
        $this->assertSame(-2, ActHalloweenHandler::cronReward((int)$act['endtime'] - 1));
        $silver = self::memberValue($uid, 'silver');
        $this->assertSame(1, ActHalloweenHandler::cronReward((int)$act['endtime'] + 100));
        $this->assertSame([$silver + 300000, 0, 1], [self::memberValue($uid, 'silver'), (int)MemberIntegral::get($uid)['medal'], self::messages($uid)]);
        $this->assertSame(15, (int)DB::table('halloween_medal_log')->where('uid', $uid)->value('medal'));
        ActHalloweenHandler::cronReward((int)$act['endtime'] + 200);
        $this->assertSame(1, self::messages($uid));
        $this->assertSame(-3, ActHalloweenHandler::cronReward((int)$act['endtime'] + 3601));
    }

    // ---------------------------------------------------------------------------------------------
    // 中秋送月饼
    // ---------------------------------------------------------------------------------------------

    public function testMoonGiveEatBuyAchievementPayAndCron(): void
    {
        $p = $this->player(['level' => 60, 'vip' => 2, 'gold' => 1000, 'reputation' => 0]);
        $f = $this->player(['level' => 60, 'vip' => 0, 'gold' => 0]);
        $low = $this->player(['level' => 30]);
        $uid = $p['uid'];
        BossClock::freeze(self::today());
        $this->assertSame(['status' => -1, 'msg' => '活动未开启！'], $this->request($p['auth'], 'act_moon', 'index'));

        self::open('moon');
        $act = ActivityClock::byName('moon');
        $version = (int)$act['version'];
        $this->assertSame(['status' => -1, 'msg' => '活动50级开启！'], $this->request($low['auth'], 'act_moon', 'index'));
        $index = $this->request($p['auth'], 'act_moon', 'index');
        $this->assertSame([1, 1, 1, 0, 50, 2, 50], [$index['status'], $index['mine_moon'], $index['giftimg'], $index['achimg'], $index['buymoon'], $index['vip'], $index['moon_price']]);
        $this->assertSame(date('m', (int)$act['starttime']) . '月' . date('d', (int)$act['starttime']) . '日-' . date('m', (int)$act['endtime']) . '月' . date('d', (int)$act['endtime']) . '日', $index['timestr']);
        $this->assertCount(3, $index['buy_list']);
        // 玉兔每天第一次送 10 个小月饼
        $this->assertSame([10, 10], [(int)DB::table('act_moon')->where('uid', $uid)->value('friend_moon'), (int)DB::table('act_moon')->where('uid', $uid)->value('friend_all')]);
        $this->request($p['auth'], 'act_moon', 'index');
        $this->assertSame(10, (int)DB::table('act_moon')->where('uid', $uid)->value('friend_moon'));
        $this->assertSame(1, DB::table('act_moon_givefriend_log')->where('frienduid', $uid)->where('type', 1)->count());

        // 购买月饼（VIP 2 每期 50 个）
        $this->assertSame(['status' => -1, 'msg' => '达到VIP1可购买10个月饼！'], $this->request($f['auth'], 'act_moon', 'buy_moon', ['id' => 1]));
        $this->assertSame(-3, $this->request($p['auth'], 'act_moon', 'buy_moon', ['id' => 9])['status']);
        $buy = $this->request($p['auth'], 'act_moon', 'buy_moon', ['id' => 2]);
        $this->assertSame([1, 11, 40, 500], [$buy['status'], $buy['mine_moon'], $buy['buymoon'], $buy['gold']]);

        // 送月饼
        $this->assertSame(-3, $this->request($p['auth'], 'act_moon', 'give_moon', ['fuid' => $f['uid'], 'num' => 5])['status']);
        $this->assertSame(-4, $this->request($p['auth'], 'act_moon', 'give_moon', ['fuid' => $uid, 'num' => 1])['status']);
        mt_srand(1);
        $give = $this->request($p['auth'], 'act_moon', 'give_moon', ['fuid' => $f['uid'], 'num' => 10]);
        $this->assertSame([1, 10, 1, 1, 0, 0], [$give['status'], $give['love'], $give['achimg'], $give['mine_moon'], $give['spent_gold'], $give['buymoon']]);
        $this->assertSame([['uid' => $uid, 'nickname' => 'f2_' . $uid, 'love' => 10]], $give['rank_list']);
        $this->assertNotEmpty($give['reward_list']);
        $friend = DB::table('act_moon')->where('uid', $f['uid'])->first();
        // 照旧版：好友建档按送的人的 VIP 送 1 个月饼
        $this->assertSame([1, 10, 10], [(int)$friend['mine_moon'], (int)$friend['friend_moon'], (int)$friend['friend_all']]);
        // 月饼不够：type 0 回 -11，type 1 自动购买（9 个 450 元宝）
        $this->assertSame(-11, $this->request($p['auth'], 'act_moon', 'give_moon', ['fuid' => $f['uid'], 'num' => 10])['status']);
        $give = $this->request($p['auth'], 'act_moon', 'give_moon', ['fuid' => $f['uid'], 'num' => 10, 'type' => 1]);
        $this->assertSame([1, 20, 0, 450, 50, 31], [$give['status'], $give['love'], $give['mine_moon'], $give['spent_gold'], $give['gold'], $give['buymoon']]);
        $this->assertSame(-9, $this->request($p['auth'], 'act_moon', 'give_moon', ['fuid' => $f['uid'], 'num' => 10, 'type' => 1])['status']);
        // 好友被锁住（正在吃月饼）
        Redis::set('mn_lk_' . $f['uid'], $f['uid'], 10);
        $this->assertSame(-2, $this->request($p['auth'], 'act_moon', 'give_moon', ['fuid' => $f['uid'], 'num' => 1])['status']);
        Redis::del('mn_lk_' . $f['uid']);

        // 好友收到的月饼、吃月饼
        $info = $this->request($f['auth'], 'act_moon', 'get_give_info');
        $this->assertSame([1, 20, 20, 2], [$info['status'], $info['friend_moon'], $info['friend_all'], count($info['msg_list'])]);
        $this->assertSame([2, $uid, 'f2_' . $uid], [$info['msg_list'][0]['type'], $info['msg_list'][0]['fuid'], $info['msg_list'][0]['nickname']]);
        $this->assertStringEndsWith('f2_' . $uid . '送给你了10个小月饼', $info['msg_list'][0]['msg_str']);
        $this->assertSame(-5, $this->request($f['auth'], 'act_moon', 'eat_moon', ['num' => 50])['status']);
        $eat = $this->request($f['auth'], 'act_moon', 'eat_moon', ['num' => 10]);
        $this->assertSame([1, 10], [$eat['status'], $eat['friend_moon']]);
        $this->assertSame(count($eat['reward_list']), DB::table('act_moon_giveeat_log')->where('uid', $f['uid'])->where('type', 1)->count());

        // 好友列表：有活动记录的带爱心
        DB::table('friend')->insert(['uid' => $uid, 'fuid' => $f['uid'], 'status' => 1, 'dateline' => self::today()]);
        $this->assertSame([['fuid' => $f['uid'], 'love' => 0, 'nickname' => 'f2_' . $f['uid']]], $this->request($p['auth'], 'act_moon', 'get_friend_list')['friend_list']);

        // 爱心成就
        $ach = $this->request($p['auth'], 'act_moon', 'get_ach_list');
        $this->assertSame([1, 20, 1, 0], [$ach['status'], $ach['love'], $ach['ach_list'][0]['reward'], $ach['ach_list'][1]['reward']]);
        $this->assertSame(-7, $this->request($p['auth'], 'act_moon', 'get_reward', ['id' => 2])['status']);
        $purple = TraderLog::jade($uid, 3);
        $this->assertSame(1, $this->request($p['auth'], 'act_moon', 'get_reward', ['id' => 1])['status']);
        $this->assertSame([$purple + 50, 5000], [TraderLog::jade($uid, 3), self::memberValue($uid, 'reputation') - (int)DB::table('act_moon_giveeat_log')->where('uid', $uid)->where('rewardtype', 7)->sum('rewardid')]);
        $this->assertSame(-5, $this->request($p['auth'], 'act_moon', 'get_reward', ['id' => 1])['status']);
        $this->assertSame(-4, $this->request($p['auth'], 'act_moon', 'get_reward', ['id' => 99])['status']);

        // 充值送月饼：每满 30 元 1 个，VIP 0 首充另送 1 个，送了月饼发信件
        $mine = (int)DB::table('act_moon')->where('uid', $f['uid'])->value('mine_moon');
        Pay::grantManual($f['uid'], 4);
        $this->assertSame($mine + 6 + 1, (int)DB::table('act_moon')->where('uid', $f['uid'])->value('mine_moon'));
        $this->assertSame(198, (int)DB::table('act_moon_order')->where('uid', $f['uid'])->value('money'));
        $this->assertSame(1, DB::table('pm')->where('touid', $f['uid'])->where('message', 'like', '恭喜您在充值送月饼活动中获得6个月饼%')->count());

        // 计划任务：结束后剩余月饼兑换银币
        $this->assertSame(-1, ActMoonHandler::cronReward((int)$act['endtime'] - 1));
        $fSilver = self::memberValue($f['uid'], 'silver');
        $friend = DB::table('act_moon')->where('uid', $f['uid'])->first();
        $this->assertSame(1, ActMoonHandler::cronReward((int)$act['endtime'] + 100));
        $this->assertSame($fSilver + (int)$friend['mine_moon'] * 50000 + (int)$friend['friend_moon'] * 10000, self::memberValue($f['uid'], 'silver'));
        $messages = self::messages($f['uid']);
        ActMoonHandler::cronReward((int)$act['endtime'] + 200);
        $this->assertSame($messages, self::messages($f['uid']));
    }

    // ---------------------------------------------------------------------------------------------
    // 主城图标、开发者开关、计划任务汇总、中秋吃月饼的每日重置
    // ---------------------------------------------------------------------------------------------

    public function testMemberFlagsOpenableCronSummaryAndMooncakeDailyReset(): void
    {
        $p = $this->player();
        $names = ['christmasbuy', 'christmasgift', 'halloween', 'moon', 'national_day', 'acttanabata', 'national_celebration', 'anniversary', 'chingming'];
        foreach ($names as $name) {
            $this->assertContains($name, ActivityClock::OPENABLE);
        }
        self::open('all');
        $flags = $this->request($p['auth'], 'member', 'index');
        $flags = $flags['member'] ?? $flags;
        foreach ($names as $name) {
            $this->assertSame(1, $flags[$name], $name);
        }
        $this->assertSame([0, 0], [$flags['christmas'], $flags['mooncake']]);
        // 活动都没结束：计划任务都不发放
        $result = FestivalCron::run(time());
        $this->assertSame(['acttanabata_reward' => -2, 'actcelebration_reward' => -2, 'clear_moon' => -1, 'get_nationalday' => -2, 'get_helloween_reward' => -2,
            'get_christmasbuy' => -2, 'get_chingming_reward' => -2, 'get_chingming_achievement_reward' => -2, 'push_pay_reward' => -2], $result);

        // 中秋吃月饼：建档 12 次，每日计划任务重置为 12
        MemberActivity::ensure($p['uid']);
        $this->assertSame(12, (int)DB::table('member_activity')->where('uid', $p['uid'])->value('freemooncake'));
        DB::table('member_activity')->where('uid', $p['uid'])->update(['freemooncake' => 3]);
        DB::table('member_field')->where('uid', $p['uid'])->update(['dateline' => 0]);
        Member::dailyCron($p['uid'], time());
        $this->assertSame(12, (int)DB::table('member_activity')->where('uid', $p['uid'])->value('freemooncake'));
    }
}
