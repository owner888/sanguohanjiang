<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\Cron\NewyearbossCron;
use Hanjiang\MasterData\ActNewyearTask;
use Hanjiang\MasterData\SpringLottery;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\ActNewyear;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\General;
use Hanjiang\Model\NewyearBoss;
use Hanjiang\Model\Pay;
use Hanjiang\Model\PubLog;
use Hanjiang\Model\TraderLog;
use Hanjiang\Model\WorldBoss;
use Hanjiang\Tests\Support\Gateway;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use PHPUnit\Framework\TestCase;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * 节日活动第一批（春节系列）：春节送福任务计数（ActNewyear 与各玩法的挂钩、主城刷新任务）、新春签到（c=newyeardailysign）、
 * 春节充值（c=newyearpay 与 Model\Pay 的充值记账）、武将商城（c=springshop）、转盘（c=springlottery）、年兽（c=newyearboss
 * 与 NewyearbossCron）按旧版的流程测试。
 *
 * 旧版数据中这些活动均已结束或从未开放：用开发者开关 ACTIVITY_OPEN 开启（窗口从今天 0 点到 30 天后），时间用 BossClock 固定。
 */
final class Fest1Test extends TestCase
{
    protected function setUp(): void
    {
        WorldBoss::deleteByPattern('newyearboss_*');
        WorldBoss::deleteByPattern('spring_*');
    }

    protected function tearDown(): void
    {
        putenv('ACTIVITY_OPEN');
        putenv('ACTIVITY_OPEN_DAYS');
        ActivityClock::reset();
        BossClock::freeze(null);
        mt_srand();
        WorldBoss::deleteByPattern('newyearboss_*');
        WorldBoss::deleteByPattern('spring_*');
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
     * 新游客：返回 uid 与鉴权参数
     *
     * @param array<string, mixed> $member
     * @return array{uid: int, auth: array<string, mixed>}
     */
    private function player(array $member = []): array
    {
        $reg = $this->request(['token' => '', 'uid' => ''], 'register', 'guest');
        $uid = (int)$reg['uid'];
        $login = $this->request(['token' => '', 'uid' => $uid], 'login', 'guest', ['guestkey' => $reg['guestkey']]);
        $member += ['nickname' => 'f1_' . $uid];
        DB::table('member')->where('uid', $uid)->update($member);
        Redis::del('player:' . $uid);
        return ['uid' => $uid, 'auth' => ['token' => (string)$login['token'], 'uid' => $uid]];
    }

    private static function memberValue(int $uid, string $column): int
    {
        return (int)DB::table('member')->where('uid', $uid)->value($column);
    }

    // ---------------------------------------------------------------------------------------------
    // 春节送福（任务计数）
    // ---------------------------------------------------------------------------------------------

    public function testActNewyearTaskCountersAndDailyRefresh(): void
    {
        $p = $this->player(['level' => 30, 'gold' => 1000]);
        $uid = $p['uid'];
        $now = (int)strtotime(date('Y-m-d') . ' 12:00:00');
        BossClock::freeze($now);

        // 活动未开启：各玩法不计数
        $this->assertSame(0, ActNewyear::task($uid, 25, $now));
        $this->assertFalse(DB::table('act_newyear_task_log')->where('uid', $uid)->exists());

        self::open('act_newyear');
        $version = (int)ActivityClock::byName('act_newyear')['version'];
        // 主城：活动开启时带版本号；第一次建档（refresh_time 为当前时间）不刷新
        $flags = $this->request($p['auth'], 'member', 'index');
        $this->assertSame(1, $flags['act_newyear']);
        $this->assertSame($version, $flags['act_newyear_version']);
        $info = DB::table('act_newyear_info')->where('uid', $uid)->first();
        $this->assertSame([0, 0, 0], [(int)$info['num'], (int)$info['buy_num'], (int)$info['fortune']]);
        $this->assertFalse(DB::table('act_newyear_task_log')->where('uid', $uid)->exists());

        // 第一次计数时导入当天的任务列表：类型 2 的 3 个全部 + 类型 1 随机 5 个
        $this->assertSame(1, ActNewyear::task($uid, 25, $now));
        $tids = array_map('intval', array_column(DB::table('act_newyear_task_log')->where('uid', $uid)->get(), 'tid'));
        $this->assertCount(8, $tids);
        foreach ([17, 19, 25] as $tid) {
            $this->assertContains($tid, $tids);
        }
        // VIP 工资 t_num 1：已满不再加
        $this->assertSame(0, ActNewyear::task($uid, 25, $now));
        $this->assertSame(1, (int)DB::table('act_newyear_task_log')->where('uid', $uid)->where('tid', 25)->value('have_num'));
        // 当天列表里没有的任务不计
        $missing = array_values(array_diff(array_column(ActNewyearTask::ROWS, 't_type'), $tids))[0];
        $this->assertSame(0, ActNewyear::task($uid, $missing, $now));

        // 每日对酒：type 2（花雕）记任务 21（今天的列表里放一行 21）
        DB::table('act_newyear_task_log')->where('uid', $uid)->where('tid', 21)->delete();
        DB::table('act_newyear_task_log')->insert(['uid' => $uid, 'version' => $version, 'ymd' => (int)date('Ymd', $now), 'tid' => 21]);
        $this->assertSame(1, $this->request($p['auth'], 'drink', 'go_drink', ['type' => 2])['status']);
        $this->assertSame(1, (int)DB::table('act_newyear_task_log')->where('uid', $uid)->where('tid', 21)->value('have_num'));

        // 第二天第一次进主城：清零翻牌 / 购买 / 福气，导入当天的任务列表
        $rows = DB::table('act_newyear_task_log')->where('uid', $uid)->count();
        DB::table('act_newyear_info')->where('uid', $uid)->update(['num' => 3, 'buy_num' => 2, 'fortune' => 5, 'refresh_time' => $now - 86400]);
        $this->request($p['auth'], 'member', 'index');
        $info = DB::table('act_newyear_info')->where('uid', $uid)->first();
        $this->assertSame([0, 0, 0], [(int)$info['num'], (int)$info['buy_num'], (int)$info['fortune']]);
        $this->assertSame(date('Ymd', $now), date('Ymd', (int)$info['refresh_time']));
        $this->assertSame($rows + 8, DB::table('act_newyear_task_log')->where('uid', $uid)->count());
        // 今天已刷新过：不再导入
        $this->assertSame(0, ActNewyear::autoInitTask($version, $uid, $now));
    }

    // ---------------------------------------------------------------------------------------------
    // 新春签到
    // ---------------------------------------------------------------------------------------------

    public function testNewyearDailySignLastThreeDaysAndBox(): void
    {
        $p = $this->player(['gold' => 0]);
        $uid = $p['uid'];
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'newyeardailysign', 'index'));
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'newyeardailysign', 'sign'));

        self::open('newyeardailysign');
        $act = ActivityClock::byName('newyeardailysign');
        // 签到期为结束前 3 天：今天还没到，签到回 -1
        $index = $this->request($p['auth'], 'newyeardailysign', 'index');
        $this->assertSame(1, $index['status']);
        $this->assertCount(3, $index['list']);
        $this->assertSame(['gold' => 100, 'silver' => 0, 'act' => 0, 'get' => 0, 'open' => 0], $index['list'][0]);
        $this->assertSame(date('n.d', $act['starttime']) . '~' . date('n.d', $act['endtime']), $index['time']);
        $this->assertSame(0, $index['box_log']);
        $this->assertStringStartsWith('长久以来', $index['desc']);
        $this->assertSame(-1, $this->request($p['auth'], 'newyeardailysign', 'sign')['status']);

        // 签到期第 2 天：第 1 天未签为 2，今天可签
        BossClock::freeze((int)strtotime(date('Y-m-d', (int)$act['endtime'] - 3 * 86400) . ' 12:00:00') + 86400);
        $index = $this->request($p['auth'], 'newyeardailysign', 'index');
        $this->assertSame([2, 0], [$index['list'][0]['get'], $index['list'][0]['open']]);
        $this->assertSame([0, 1], [$index['list'][1]['get'], $index['list'][1]['open']]);
        $res = $this->request($p['auth'], 'newyeardailysign', 'sign');
        $this->assertSame(['status' => 1, 'add_gold' => 100, 'add_silver' => 0, 'add_act' => 0], array_intersect_key($res, ['status' => 0, 'add_gold' => 0, 'add_silver' => 0, 'add_act' => 0]));
        $this->assertSame(100, $res['all_gold']);
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'newyeardailysign', 'sign'));
        $this->assertSame(1, $this->request($p['auth'], 'newyeardailysign', 'index')['list'][1]['get']);
        $this->assertSame(1, DB::table('dailysign_log')->where('uid', $uid)->where('class', 'newyeardailysign')->where('day', 2)->count());

        // 宝箱：每期一次 500 元宝
        $this->assertSame(['status' => 1, 'add_gold' => 500, 'all_gold' => 600], $this->request($p['auth'], 'newyeardailysign', 'open_box'));
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'newyeardailysign', 'open_box'));
        $this->assertSame(1, $this->request($p['auth'], 'newyeardailysign', 'index')['box_log']);
    }

    // ---------------------------------------------------------------------------------------------
    // 春节充值
    // ---------------------------------------------------------------------------------------------

    public function testNewyearPayRecordsOrdersAndRewards(): void
    {
        $p = $this->player();
        $uid = $p['uid'];
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'newyearpay', 'pay_reward'));
        $this->assertSame(['status' => -5], $this->request($p['auth'], 'newyearpay', 'get_reward'));
        // 未开启时版本为 0，查不到充值记录（旧版写成 $retuen 输出 null，这里回 -3）
        $this->assertSame(['status' => -3], $this->request($p['auth'], 'newyearpay', 'get_reward', ['id' => 1]));

        self::open('newyearpay');
        $act = ActivityClock::byName('newyearpay');
        $version = (int)$act['version'] + 1000;
        // 活动期间充值：返利 20%（type 1），记一条 pay_reward_order
        $gold = self::memberValue($uid, 'gold');
        $res = Pay::grantManual($uid, 3);
        $this->assertSame(1, $res['status']);
        $this->assertSame(680 + 136, $res['gold']);
        $order = DB::table('pay_reward_order')->where('uid', $uid)->first();
        $this->assertSame([68, $version, 'pay', 'manual'], [(int)$order['money'], (int)$order['version'], $order['class'], $order['method']]);
        $this->assertGreaterThanOrEqual($gold + 816, self::memberValue($uid, 'gold'));

        $res = $this->request($p['auth'], 'newyearpay', 'pay_reward');
        $this->assertSame(1, $res['status']);
        $this->assertSame(680, $res['now_pay']);
        $this->assertSame(1, $res['type']);
        $this->assertSame([1, 0], [$res['list'][0]['status'], $res['list'][1]['status']]);

        // 第 1 档（500）：金宝石 50
        $jade = TraderLog::jade($uid, 2);
        $res = $this->request($p['auth'], 'newyearpay', 'get_reward', ['id' => 1]);
        $this->assertSame([1, 4, 50], [$res['status'], $res['gettype'], $res['getvalue']]);
        $this->assertSame($jade + 50, TraderLog::jade($uid, 2));
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'newyearpay', 'get_reward', ['id' => 1]));
        $this->assertSame(['status' => -4], $this->request($p['auth'], 'newyearpay', 'get_reward', ['id' => 2]));
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'newyearpay', 'get_reward', ['id' => 99]));
        $this->assertSame(2, $this->request($p['auth'], 'newyearpay', 'pay_reward')['list'][0]['status']);
    }

    // ---------------------------------------------------------------------------------------------
    // 武将商城
    // ---------------------------------------------------------------------------------------------

    public function testSpringShopBuyGeneralWithPurpleJadeAndLimit(): void
    {
        $p = $this->player(['level' => 40, 'reputation' => 0]);
        $uid = $p['uid'];
        $this->assertSame(2, $this->request($p['auth'], 'springshop', 'index')['status']);

        self::open('springshop');
        BossClock::freeze((int)strtotime(date('Y-m-d') . ' 12:00:00'));
        $index = $this->request($p['auth'], 'springshop', 'index');
        $this->assertSame(1, $index['status']);
        $this->assertCount(13, $index['list']);
        $gift = $index['list'][0];
        $this->assertSame([1, 20, 4400, 800000], [$gift['is_buy'], $gift['buytimes'], $gift['price_discount'], $gift['price1_discount']]);
        $this->assertSame(71, $gift['general']['id']);
        $this->assertArrayHasKey('name', $gift['general']['talent']);
        $this->assertSame(0, $index['get3']);

        // 紫宝石 / 声望不足
        $this->assertSame(-5, $this->request($p['auth'], 'springshop', 'buy', ['id' => 1])['status']);
        TraderLog::addJade($uid, 3, 5000);
        $this->assertSame(-6, $this->request($p['auth'], 'springshop', 'buy', ['id' => 1])['status']);
        DB::table('member')->where('uid', $uid)->update(['reputation' => 900000]);
        $this->assertSame(-8, $this->request($p['auth'], 'springshop', 'buy')['status']);

        $res = $this->request($p['auth'], 'springshop', 'buy', ['id' => 1]);
        $this->assertSame(1, $res['status']);
        $this->assertSame(71, $res['general']['generalid']);
        $this->assertSame(600, $res['get3']);
        $this->assertSame(100000, $res['reputation']);
        $this->assertTrue(General::ownsTemplate($uid, 71));
        $this->assertSame(7, PubLog::byGeneral($uid, 71)['type']);
        $this->assertSame([0, 19], [$res['list'][0]['is_buy'], $res['list'][0]['buytimes']]);
        $this->assertSame(1, DB::table('springshop_log')->where('uid', $uid)->where('giftid', 1)->count());
        $this->assertSame(1, DB::table('tavern_action_log')->where('uid', $uid)->count());
        // 已拥有该武将
        $this->assertSame(-9, $this->request($p['auth'], 'springshop', 'buy', ['id' => 1])['status']);

        // 全服限售：今天已卖完回 status 1 + msg，礼包标为售完
        Redis::set('spring_buytimes_' . date('Ymd', BossClock::now()) . '_2', 20, 86400);
        $res = $this->request($p['auth'], 'springshop', 'buy', ['id' => 2]);
        $this->assertSame([1, '礼包已售完', 2], [$res['status'], $res['msg'], $res['list'][1]['is_buy']]);
        $this->assertSame(20, (int)Redis::get('spring_buytimes_' . date('Ymd', BossClock::now()) . '_2'));
        // 购买锁
        Redis::set('spring_lock_' . $uid, 1, 60);
        $this->assertSame(-7, $this->request($p['auth'], 'springshop', 'buy', ['id' => 3])['status']);
    }

    // ---------------------------------------------------------------------------------------------
    // 转盘
    // ---------------------------------------------------------------------------------------------

    public function testSpringLotteryFreeGoldExtremeAndRankReward(): void
    {
        $p = $this->player(['level' => 50, 'vip' => 5, 'gold' => 1000, 'reputation' => 0]);
        $uid = $p['uid'];
        $closed = $this->request($p['auth'], 'springlottery', 'index');
        $this->assertSame([2, 0], [$closed['status'], $closed['vip']]);
        // 活动结束（_end 为 0）：action 回 0
        $this->assertSame(0, $this->request($p['auth'], 'springlottery', 'action')['status']);

        self::open('springlottery');
        // 排行按活动版本：清掉前面运行留下的同版本记录
        DB::execute('DELETE FROM `zc_sanguo_spring_lottery_ranklog` WHERE `version` = :v', ['v' => (int)ActivityClock::byName('springlottery')['version']]);
        BossClock::freeze((int)strtotime(date('Y-m-d') . ' 12:00:00'));
        $index = $this->request($p['auth'], 'springlottery', 'index');
        $this->assertSame(1, $index['status']);
        $this->assertSame([5, 1, 50, 0], [$index['times'], $index['lottery'], $index['costgold'], $index['extreme']]);
        $this->assertCount(13, $index['list']);
        $this->assertArrayNotHasKey('getrate', $index['list'][0]);
        $this->assertSame(['rank' => '', 'is_vip' => 1], $index['my_rank']);
        $material = array_values(array_filter($index['list'], static fn (array $v): bool => (int)$v['gettype'] === 19))[0];
        $this->assertSame([22, 3], [$material['materials_id'], $material['num']]);
        // 第一天不能领奖
        $this->assertSame(-1, $this->request($p['auth'], 'springlottery', 'get_reward')['status']);

        // 第 1 次免费
        $res = $this->request($p['auth'], 'springlottery', 'action');
        $this->assertSame(1, $res['status']);
        $this->assertSame([0, 4, 1000, 50], [$res['lottery'], $res['times'], $res['gold'], $res['costgold']]);
        $this->assertSame(1, (int)SpringLottery::ROWS[$res['info']['id']]['group']);
        $this->assertSame(['rank' => 1, 'type' => 7, 'my_reward' => 200000, 'is_vip' => 1], $res['my_rank']);
        $this->assertSame(1, $res['userlist'][0]['times']);
        // 第 2 次花 50，下次 60
        $res = $this->request($p['auth'], 'springlottery', 'action');
        $this->assertSame([1, 60], [$res['status'], $res['costgold']]);
        $this->assertSame(950, $res['gold']);
        $this->assertSame(50, (int)DB::table('springlottery_action_log')->where('uid', $uid)->orderBy('id', 'DESC')->value('gold'));
        // 第 6 次是免费的至尊转盘（buy_lavenumber 5 → 6），不改最后抽奖时间
        DB::table('spring_lottery_log')->where('uid', $uid)->update(['buy_lavenumber' => 5]);
        $last = (int)DB::table('spring_lottery_log')->where('uid', $uid)->value('lastdateline');
        $res = $this->request($p['auth'], 'springlottery', 'action');
        $this->assertSame(1, $res['status']);
        $this->assertSame(2, (int)SpringLottery::ROWS[$res['info']['id']]['group']);
        $this->assertSame(950, $res['gold']);
        $this->assertSame(5, $res['times']);
        $log = DB::table('spring_lottery_log')->where('uid', $uid)->first();
        $this->assertSame([6, $last], [(int)$log['buy_lavenumber'], (int)$log['lastdateline']]);
        $this->assertSame(3, (int)DB::table('spring_lottery_ranklog')->where('uid', $uid)->value('times'));
        $this->assertSame(3, DB::table('spring_lottery_info_log')->where('uid', $uid)->count());

        // 元宝不足
        DB::table('member')->where('uid', $uid)->update(['gold' => 0]);
        $this->assertSame(-7, $this->request($p['auth'], 'springlottery', 'action')['status']);

        // 第二天：昨天第 1 名领 20 万声望；免费次数与价格重置
        BossClock::freeze((int)strtotime(date('Y-m-d') . ' 12:00:00') + 86400);
        $index = $this->request($p['auth'], 'springlottery', 'index');
        $this->assertSame([1, 50, 0], [$index['lottery'], $index['costgold'], $index['is_cd']]);
        $this->assertSame(1, $index['my_rank']['rank']);
        $rep = self::memberValue($uid, 'reputation');
        $res = $this->request($p['auth'], 'springlottery', 'get_reward');
        $this->assertSame([1, 200000, 7, 7], [$res['status'], $res['reward'], $res['type'], $res['info']['gettype']]);
        $this->assertSame($rep + 200000, self::memberValue($uid, 'reputation'));
        $this->assertSame(-2, $this->request($p['auth'], 'springlottery', 'get_reward')['status']);

        // 主城同步排行记录的 VIP（低于 2 且玩家已到 VIP 2）
        DB::table('spring_lottery_ranklog')->where('uid', $uid)->update(['vip' => 0]);
        $this->request($p['auth'], 'member', 'index');
        $this->assertSame(5, (int)DB::table('spring_lottery_ranklog')->where('uid', $uid)->value('vip'));
    }

    // ---------------------------------------------------------------------------------------------
    // 年兽
    // ---------------------------------------------------------------------------------------------

    public function testNewyearBossBattlePowerupAchievementAndRankReward(): void
    {
        $p = $this->player(['level' => 60, 'gold' => 1000, 'silver' => 100000]);
        $uid = $p['uid'];
        $this->assertSame(-1, $this->request($p['auth'], 'newyearboss', 'index')['status']);

        self::open('newyearboss');
        $act = ActivityClock::byName('newyearboss');
        // 排行按活动版本：清掉前面运行留下的同版本记录
        DB::execute('DELETE FROM `zc_sanguo_newyear_boss_log` WHERE `version` = :v', ['v' => (int)$act['version']]);
        $today = date('Y-m-d');
        // 12:30 前
        BossClock::freeze((int)strtotime($today . ' 12:00:00'));
        $index = $this->request($p['auth'], 'newyearboss', 'index');
        $this->assertSame([-2, 1800, 100, 20], [$index['status'], $index['countdown'], $index['powerup'], $index['cdcost']]);
        $this->assertSame(['gold' => 20, 'silver' => 30000], $index['upcost']);
        $this->assertSame(-2, $this->request($p['auth'], 'newyearboss', 'battle')['status']);
        $this->assertSame(0, $this->request($p['auth'], 'member', 'index')['opennewyearboss']);

        // 12:40：进行中
        $ts = (int)strtotime($today . ' 12:40:00');
        BossClock::freeze($ts);
        $this->assertSame(1, $this->request($p['auth'], 'member', 'index')['opennewyearboss']);
        $index = $this->request($p['auth'], 'newyearboss', 'index');
        $this->assertSame([1, 1200], [$index['status'], $index['countdown']]);
        $this->assertSame(['name' => '年兽', 'blood' => '100.00', 'image' => 'newyearboss'], $index['bossinfo']);

        $res = $this->request($p['auth'], 'newyearboss', 'battle');
        $this->assertSame(1, $res['status']);
        $battle = $res['battle'];
        $this->assertSame($battle['assess'], $res['info']['win']);
        $this->assertSame(['leftteam', 'rightteam', 'process', 'assess', 'leftresult', 'rightresult', 'leftbuff', 'rightbuff'], array_keys($battle));
        $this->assertSame(['年兽', '100.00', 0], [$battle['rightteam'][5]['name'], $battle['rightteam'][5]['allblood'], $battle['rightteam'][5]['herotype']]);
        $hit = NewyearBoss::myHit($uid, $ts);
        $this->assertGreaterThan(0, $hit);
        $this->assertSame($hit, (int)DB::table('newyear_boss_log')->where('uid', $uid)->value('hit'));
        // 战斗锁 / 复活 CD
        $res = $this->request($p['auth'], 'newyearboss', 'battle');
        $this->assertSame([-3, 61], [$res['status'], $res['info']['cd']]);
        // 同一秒内再打：距上次攻击 0 秒，旧版 check_delay 为 0 时不拦（只有 1 秒战斗锁）；距上次攻击 30 秒 → 还要 31 秒
        Redis::del('newyearboss_hit_lock_' . $uid);
        Redis::expire('newyearboss_' . date('Ymd', $ts) . '_my_hit_' . $uid, NewyearBoss::REDIS_EXPIRE - 30);
        $res = $this->request($p['auth'], 'newyearboss', 'battle');
        $this->assertSame(-6, $res['status']);
        $this->assertContains($res['info']['cd'], [30, 31]);
        $this->assertContains($this->request($p['auth'], 'newyearboss', 'index')['cd'], [30, 31]);
        // 元宝立即复活 20，下次 40
        Redis::del('newyearboss_hit_lock_' . $uid);
        $res = $this->request($p['auth'], 'newyearboss', 'battle', ['now' => 1]);
        $this->assertSame([1, 980], [$res['status'], $res['info']['all_gold']]);
        $this->assertSame(40, $this->request($p['auth'], 'newyearboss', 'index')['cdcost']);
        $this->assertSame(NewyearBoss::myHit($uid, $ts), (int)DB::table('newyear_boss_log')->where('uid', $uid)->value('hit'));

        // 鼓舞：银币（等级 × 500）+10%，元宝 20 +10% 并计元宝鼓舞次数
        $res = $this->request($p['auth'], 'newyearboss', 'powerup');
        $this->assertSame([1, 110, 70000], [$res['status'], $res['powerup'], $res['all_silver']]);
        $res = $this->request($p['auth'], 'newyearboss', 'powerup', ['gold' => 1]);
        $this->assertSame([1, 120, 960, 1], [$res['status'], $res['powerup'], $res['all_gold'], $res['mypowerup']]);
        $this->assertSame(1, (int)DB::table('newyear_boss_log')->where('uid', $uid)->value('powerup'));
        Redis::set('newyearboss_' . date('Ymd', $ts) . '_power_up_' . $uid, 200, 600);
        $this->assertSame([-3, 200], array_values(array_intersect_key($this->request($p['auth'], 'newyearboss', 'powerup'), ['status' => 0, 'powerup' => 0])));

        // 每日成就：元宝鼓舞 1 次（id 11，原魂 200）
        $list = $this->request($p['auth'], 'newyearboss', 'get_achievement_list', ['type' => 2]);
        $this->assertSame(1, $list['list'][0]['status']);
        $soul = self::memberValue($uid, 'soul');
        $res = $this->request($p['auth'], 'newyearboss', 'get_achievement_reward', ['id' => 11]);
        $this->assertSame([1, 200], [$res['status'], $res['info']['getvalue']]);
        $this->assertSame($soul + 200, self::memberValue($uid, 'soul'));
        $this->assertSame(-4, $this->request($p['auth'], 'newyearboss', 'get_achievement_reward', ['id' => 11])['status']);
        $this->assertSame(2, $this->request($p['auth'], 'newyearboss', 'get_achievement_list', ['type' => 2])['list'][0]['status']);
        // 排名成就要等 BOSS 战结束
        $rankAch = array_values(array_filter(\Hanjiang\MasterData\NewyearBossAchievement::ROWS, static fn (array $r): bool => $r['type'] === 4))[0];
        $this->assertSame(-6, $this->request($p['auth'], 'newyearboss', 'get_achievement_reward', ['id' => $rankAch['id']])['status']);

        // 排行：活动未结束不可领
        $rank = $this->request($p['auth'], 'newyearboss', 'get_rank', ['type' => 1]);
        $this->assertSame([1, 1, 0], [$rank['status'], $rank['myrank'], $rank['isget']]);
        $this->assertSame(NewyearBoss::myHit($uid, $ts), $rank['list'][0]['value']);
        $this->assertSame(['type' => 5, 'value' => 24000], $rank['reward']);
        $this->assertSame(-2, $this->request($p['auth'], 'newyearboss', 'get_rank', ['type' => 9])['status']);
        $this->assertSame(-2, $this->request($p['auth'], 'newyearboss', 'get_rank_reward', ['type' => 1])['status']);
        $rewardList = $this->request($p['auth'], 'newyearboss', 'get_rank_reward_list');
        $this->assertCount(13, $rewardList['list']);

        // 最后一天：领伤害排行奖励（第 1 名原魂 24000）
        BossClock::freeze((int)$act['endtime'] - 3600);
        $this->assertSame(1, $this->request($p['auth'], 'newyearboss', 'get_rank', ['type' => 1])['isget']);
        $soul = self::memberValue($uid, 'soul');
        $res = $this->request($p['auth'], 'newyearboss', 'get_rank_reward', ['type' => 1]);
        $this->assertSame([1, ['type' => 5, 'value' => 24000]], [$res['status'], $res['reward']]);
        $this->assertSame($soul + 24000, self::memberValue($uid, 'soul'));
        $this->assertSame(-4, $this->request($p['auth'], 'newyearboss', 'get_rank_reward', ['type' => 1])['status']);

        // 活动结束后的计划任务：补发鼓舞排行（第 1 名紫宝石 300），伤害排行已领取不补发
        $jade = TraderLog::jade($uid, 3);
        $this->assertSame(-2, NewyearbossCron::run((int)$act['endtime'] - 10)[2]);
        $result = NewyearbossCron::run((int)$act['endtime'] + 100);
        $this->assertSame(1, $result[1]);
        $this->assertSame(1, $result[2]);
        $this->assertSame($jade + 300, TraderLog::jade($uid, 3));
        $this->assertSame(1, DB::table('newyear_boss_reward_log')->where('uid', $uid)->where('type', 2)->where('tab', 1)->count());
        $this->assertSame(1, DB::table('newyear_boss_reward_log')->where('uid', $uid)->where('type', 2)->where('tab', 2)->count());
        $this->assertTrue(DB::table('pm')->where('touid', $uid)->where('message', 'like', '%鼓舞排行奖励%')->exists());
        // 再执行不重复发放；超过 10 小时不再执行
        NewyearbossCron::run((int)$act['endtime'] + 200);
        $this->assertSame($jade + 300, TraderLog::jade($uid, 3));
        $this->assertSame(-3, NewyearbossCron::run((int)$act['endtime'] + 36001)[1]);
    }
}
