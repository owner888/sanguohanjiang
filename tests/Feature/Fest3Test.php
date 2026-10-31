<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\Cron\Fest3Cron;
use Hanjiang\Handler\Activity\ActEnrichmentHandler;
use Hanjiang\Handler\Activity\ActKemariHandler;
use Hanjiang\Handler\Activity\ActPaychecksHandler;
use Hanjiang\Handler\Activity\ActSevenstarHandler;
use Hanjiang\Handler\Activity\ActThirtyHandler;
use Hanjiang\Handler\Activity\ActTreasureHandler;
use Hanjiang\Handler\Activity\WorldcupHandler;
use Hanjiang\Cross\Server;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberIntegral;
use Hanjiang\Model\MemberStone;
use Hanjiang\Model\Pay;
use Hanjiang\Model\TraderLog;
use Hanjiang\Tests\Support\Gateway;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use PHPUnit\Framework\TestCase;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * 节日活动第三批：踏青寻宝（c=act_treasure）、我爱世界杯（c=worldcup）、蹴鞠大赛（c=act_kemari）、七星灯（c=act_sevenstar）、
 * 貔貅聚财（c=act_enrichment）、招募战友领工资（c=act_paychecks）、老兵召回（c=act_veteran 与武将继承的免费至尊继承）、
 * 充值送豪礼（c=act_thirty）、绝版神将（c=general_only）、限时打折神将（c=general_onsale）、限量神将（c=limitedshop），
 * 充值记账（Model\Pay::complete）、主城图标与计划任务（Fest3Cron）按旧版流程测试。
 *
 * 旧版数据中这些活动均已结束或从未开放：用开发者开关 ACTIVITY_OPEN 开启（窗口从今天 0 点到 30 天后），时间用 BossClock 固定；
 * 随机奖励固定 mt_rand 种子（tearDown 重置），只断言与随机无关的部分。
 */
final class Fest3Test extends TestCase
{
    /** 本批活动的玩家记录表（每个测试从空表开始：活动版本号固定，重复运行时排行会带上上次的数据） */
    private const array TABLES = [
        'act_treasure', 'act_treasure_exchange', 'act_treasure_hanzi', 'act_treasure_log', 'act_treasure_hanziach_log',
        'worldcup_log', 'worldcup_order', 'worldcup_reward_log', 'worldcup_action_log', 'actkemari', 'actkemari_log',
        'actkemari_exchange_log', 'actsevenstar', 'actenrichment_order', 'actenrichment_getreward_log', 'act_paychecks_order',
        'act_paychecks_reward_log', 'act_paychecks_paycheck_log', 'act_paychecks', 'act_veteran_log', 'thirty_order',
        'thirty_chip_log', 'thirty_log', 'thirty', 'uc_limitedshop', 'uc_limitedshop_sale_log', 'uc_invitation_log',
    ];

    protected function setUp(): void
    {
        foreach (self::TABLES as $table) {
            DB::execute('DELETE FROM `zc_sanguo_' . $table . '`');
        }
    }

    protected function tearDown(): void
    {
        putenv('ACTIVITY_OPEN');
        putenv('ACTIVITY_OPEN_DAYS');
        ActivityClock::reset();
        BossClock::freeze(null);
        mt_srand();
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
        // 输出层给没有 msg 的返回补的默认值
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
        $member += ['nickname' => 'f3_' . $uid];
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

    /** 活动行（ACTIVITY_OPEN 之后） */
    private static function act(string $name): array
    {
        $row = ActivityClock::byName($name);
        self::assertNotNull($row);
        return $row;
    }

    // ---------------------------------------------------------------------------------------------
    // 踏青寻宝
    // ---------------------------------------------------------------------------------------------

    public function testTreasureIndexFreeAndPaidStepsHundredAndToday(): void
    {
        $p = $this->player(['level' => 60, 'vip' => 5, 'gold' => 20000]);
        $uid = $p['uid'];
        BossClock::freeze(self::today());
        $this->assertSame(-1, $this->request($p['auth'], 'act_treasure', 'index')['status']);
        self::open('acttreasure');
        $low = $this->player(['level' => 49]);
        $this->assertSame(['status' => -3, 'vip' => 2, 'msg' => '50级开启寻宝！'], $this->request($low['auth'], 'act_treasure', 'index'));

        mt_srand(3);
        $index = $this->request($p['auth'], 'act_treasure', 'index');
        $this->assertSame([1, 2, 50, 4800, 1, 0], [$index['status'], $index['vip'], $index['sub_gold'], $index['sub_h_gold'], $index['free'], $index['site']]);
        $this->assertSame(range(1, 19), array_keys($index['list']));
        // 第 10 格是双倍格，汉字 8 个
        $this->assertSame([20, '双倍格'], [$index['list'][10]['type'], $index['list'][10]['name']]);
        $this->assertCount(8, array_filter($index['list'], static fn (array $v): bool => $v['type'] === 19));
        $row = DB::table('act_treasure')->where('uid', $uid)->first();
        $this->assertNotNull($row);
        $this->assertCount(19, json_decode((string)$row['reward_str'], true));

        // 第一次免费：不扣元宝、不计寻宝次数
        $first = $this->request($p['auth'], 'act_treasure', 'treasure');
        $this->assertSame(1, $first['status']);
        $this->assertSame(20000, self::memberValue($uid, 'gold'));
        $this->assertSame(0, (int)DB::table('act_treasure')->where('uid', $uid)->value('num'));
        $this->assertGreaterThan(0, DB::table('act_treasure_log')->where('uid', $uid)->count());
        $site = (int)$first['now_site'];
        $this->assertSame($site, (int)DB::table('act_treasure')->where('uid', $uid)->value('site'));
        $this->assertSame($site === 0 ? 1 : 0, (int)DB::table('member_field')->where('uid', $uid)->value('treasure_free'));
        // 之后每次 50 元宝
        DB::table('member_field')->where('uid', $uid)->update(['treasure_free' => 0]);
        $second = $this->request($p['auth'], 'act_treasure', 'treasure');
        $this->assertSame(1, $second['status']);
        $this->assertSame(19950, $second['all_gold'] - self::rewardGold($second));
        $this->assertSame(1, (int)DB::table('act_treasure')->where('uid', $uid)->value('num'));
        // 操作锁
        Redis::set('treasure_lock_' . $uid, 1, 60);
        $this->assertSame(2, $this->request($p['auth'], 'act_treasure', 'treasure')['status']);
        $this->assertSame(2, $this->request($p['auth'], 'act_treasure', 'treasure_two', ['type' => 1])['status']);
        Redis::del('treasure_lock_' . $uid);

        // 百次寻宝：参数错误不解锁（照旧版）
        $this->assertSame(-3, $this->request($p['auth'], 'act_treasure', 'treasure_two')['status']);
        $this->assertSame(2, $this->request($p['auth'], 'act_treasure', 'treasure_two', ['type' => 1])['status']);
        Redis::del('treasure_lock_' . $uid);
        $gold = self::memberValue($uid, 'gold');
        $two = $this->request($p['auth'], 'act_treasure', 'treasure_two', ['type' => 1]);
        $this->assertSame(1, $two['status']);
        $this->assertNotEmpty($two['reward']);
        $this->assertSame(101, (int)DB::table('act_treasure')->where('uid', $uid)->value('num'));
        $this->assertSame($gold - 4800, self::memberValue($uid, 'gold') - array_sum(array_column(array_filter($two['reward'], static fn ($r) => $r['type'] === 1), 'num')));
        // 元宝不足
        DB::table('member')->where('uid', $uid)->update(['gold' => 100]);
        $this->assertSame(3, $this->request($p['auth'], 'act_treasure', 'treasure_two', ['type' => 1])['status']);

        // 今天的奖励：汉字在前（按字升序），其他奖励按类型降序
        $today = $this->request($p['auth'], 'act_treasure', 'today_reward');
        $this->assertSame([1, 2], [$today['status'], $today['vip']]);
        $this->assertNotEmpty($today['list']);
        $types = array_column($today['list'], 'type');
        $hanzi = array_values(array_filter($today['list'], static fn (array $v): bool => $v['type'] === 19));
        $ids = array_column($hanzi, 'id');
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids);
        $this->assertNotContains(18, $types);
        $this->assertNotContains(20, $types);
    }

    /** 抽到元宝奖励时的元宝数（寻宝基础表里没有元宝，恒为 0） */
    private static function rewardGold(array $res): int
    {
        return ($res['info']['type'] ?? 0) === 1 ? (int)$res['info']['num'] : 0;
    }

    public function testTreasureHanziExchangeAchievementAndPopo(): void
    {
        $p = $this->player(['level' => 60, 'vip' => 2, 'gold' => 1000, 'soul' => 0]);
        $uid = $p['uid'];
        BossClock::freeze(self::today());
        self::open('acttreasure');
        $version = (int)self::act('acttreasure')['version'];
        $this->assertSame(1, $this->request($p['auth'], 'act_treasure', 'index')['status']);
        // 收集了悍将三国（2 个悍）
        DB::table('act_treasure_hanzi')->insert(['uid' => $uid, 'version' => $version, 'type_str' => '1,2;2,1;3,1;4,1', 'dateline' => self::today()]);
        $hz = $this->request($p['auth'], 'act_treasure', 'hanzi_index');
        $this->assertSame([1, 1, 0], [$hz['status'], $hz['hzexch_open'], $hz['hzach_open']]);
        $this->assertArrayNotHasKey('base_hanzi', $hz);
        $this->assertSame([2, 1, 1, 1, 0, 0, 0, 0], array_column($hz['hanzi_list'], 'num'));
        $open = array_column($hz['list'], 'open', 'id');
        $this->assertSame([1 => 0, 2 => 1, 3 => 0, 4 => 1, 5 => 1, 6 => 0, 7 => 0, 8 => 0], $open);

        $this->assertSame(-2, $this->request($p['auth'], 'act_treasure', 'hanzi_exchange', ['hid' => 8])['status']);
        $this->assertSame(3, $this->request($p['auth'], 'act_treasure', 'hanzi_exchange', ['hid' => 3])['status']);
        $purple = TraderLog::jade($uid, 3);
        $ex = $this->request($p['auth'], 'act_treasure', 'hanzi_exchange', ['hid' => 2]);
        // 二等奖：150 元宝、50 紫宝石、2000 原魂
        $this->assertSame([1, 1150, 0], [$ex['status'], $ex['all_gold'], $ex['hzexch_popo']]);
        $this->assertSame([$purple + 50, 2000], [TraderLog::jade($uid, 3), self::memberValue($uid, 'soul')]);
        $this->assertSame('1,1;5,0;6,0;7,0;8,0', DB::table('act_treasure_hanzi')->where('uid', $uid)->value('type_str'));
        $this->assertSame(1, DB::table('act_treasure_exchange')->where('uid', $uid)->where('hid', 2)->count());
        $this->assertSame([1, 0, 0, 0, 0, 0, 0, 0], array_column($ex['hanzi_list'], 'num'));
        // 只剩一个悍：不能再兑换，兑换提示清零
        $this->assertSame(3, $this->request($p['auth'], 'act_treasure', 'hanzi_exchange', ['hid' => 4])['status']);

        // 集字成就：8 个字各获得过 1 次 → 集齐 1 次
        $this->assertSame(0, $this->request($p['auth'], 'act_treasure', 'hanzi_achieve')['num']);
        for ($i = 1; $i <= 8; $i++) {
            DB::table('act_treasure_log')->insert(['uid' => $uid, 'version' => $version, 'site' => 1, 'type' => 19, 'num' => 1, 'max_num' => $i, 'dateline' => self::today()]);
        }
        $ach = $this->request($p['auth'], 'act_treasure', 'hanzi_achieve');
        $this->assertSame([1, 1], [$ach['status'], $ach['num']]);
        $this->assertSame([1, 0], [$ach['list'][0]['open'], $ach['list'][1]['open']]);
        $this->assertCount(14, $ach['num_list']);
        $this->assertSame(-3, $this->request($p['auth'], 'act_treasure', 'hanzi_achieve_reward')['status']);
        $this->assertSame(2, $this->request($p['auth'], 'act_treasure', 'hanzi_achieve_reward', ['aid' => 2])['status']);
        $silver = self::memberValue($uid, 'silver');
        $purple = TraderLog::jade($uid, 3);
        $refine = MemberStone::get($uid, MemberStone::REFINE);
        $got = $this->request($p['auth'], 'act_treasure', 'hanzi_achieve_reward', ['aid' => 1]);
        $this->assertSame([1, $silver + 3000000, 0], [$got['status'], $got['all_silver'], $got['hzach_popo']]);
        $this->assertSame([$purple + 200, $refine + 30000], [TraderLog::jade($uid, 3), MemberStone::get($uid, MemberStone::REFINE)]);
        $this->assertSame(3, $this->request($p['auth'], 'act_treasure', 'hanzi_achieve_reward', ['aid' => 1])['status']);
        $this->assertSame(2, $this->request($p['auth'], 'act_treasure', 'hanzi_achieve')['list'][0]['open']);

        // 主城：VIP 同步与 acttreasure_popo
        DB::table('act_treasure')->where('uid', $uid)->update(['vip' => 0, 'hzexch_reward' => 1]);
        $flags = $this->request($p['auth'], 'member', 'index');
        $flags = $flags['member'] ?? $flags;
        $this->assertSame([1, 1], [$flags['acttreasure'], $flags['acttreasure_popo']]);
        $this->assertSame(2, (int)DB::table('act_treasure')->where('uid', $uid)->value('vip'));
    }

    public function testTreasureRankRewardDailyCronAndEndCrons(): void
    {
        self::open('acttreasure');
        $act = self::act('acttreasure');
        $version = (int)$act['version'];
        $end = (int)$act['endtime'] - 86401;
        $a = $this->player(['level' => 60, 'vip' => 3]);
        $b = $this->player(['level' => 60, 'vip' => 3]);
        $c = $this->player(['level' => 60, 'vip' => 1]);
        foreach ([[$a, 50, 100], [$b, 40, 101], [$c, 99, 102]] as [$p, $num, $at]) {
            DB::table('act_treasure')->insert(['uid' => $p['uid'], 'version' => $version, 'nickname' => 'r' . $num, 'vip' => $p === $c ? 1 : 3, 'num' => $num, 'reward_str' => '{}', 'dateline' => $at]);
        }
        BossClock::freeze($end - 100);
        $rank = $this->request($a['auth'], 'act_treasure', 'treasure_rank');
        $this->assertSame([1, 1, 0, 100], [$rank['status'], $rank['rank'], $rank['is_get'], $rank['reward_cd']]);
        $this->assertSame([[1, 50, 4, 80000], [2, 40, 4, 76000]], array_map(static fn ($r) => [$r['tank'], $r['num'], $r['type'], $r['value']], $rank['list']));
        $this->assertSame(['rank' => 1, 'type' => 4, 'value' => 80000], $rank['rank_reward']);
        // VIP 1 不进榜
        $this->assertSame(0, $this->request($c['auth'], 'act_treasure', 'treasure_rank')['rank']);
        $this->assertSame(2, $this->request($a['auth'], 'act_treasure', 'treasure_rank_reward')['status']);
        // 寻宝截止后可领
        BossClock::freeze($end + 10);
        $this->assertSame(-3, $this->request($a['auth'], 'act_treasure', 'treasure')['status']);
        $this->assertSame(1, $this->request($a['auth'], 'act_treasure', 'treasure_rank')['is_get']);
        $purple = TraderLog::jade($a['uid'], 3);
        $got = $this->request($a['auth'], 'act_treasure', 'treasure_rank_reward');
        $this->assertSame([1, ['rank' => 1, 'type' => 4, 'value' => 80000]], [$got['status'], $got['reward']]);
        $this->assertSame($purple + 80000, TraderLog::jade($a['uid'], 3));
        $this->assertSame(4, $this->request($a['auth'], 'act_treasure', 'treasure_rank_reward')['status']);
        $this->assertSame(2, $this->request($a['auth'], 'act_treasure', 'treasure_rank')['is_get']);
        $this->assertSame(3, $this->request($c['auth'], 'act_treasure', 'treasure_rank_reward')['status']);

        // 每日零点：剩余汉字每个 30 元宝，集齐的次数记兑换日志
        DB::table('act_treasure_hanzi')->insert(['uid' => $c['uid'], 'version' => $version, 'type_str' => '1,2;2,1;3,1;4,1;5,1;6,1;7,1;8,3', 'dateline' => 1]);
        $gold = self::memberValue($c['uid'], 'gold');
        $this->assertSame(1, ActTreasureHandler::cronHanziReward(self::today()));
        $this->assertSame($gold + 11 * 30, self::memberValue($c['uid'], 'gold'));
        $this->assertSame('', DB::table('act_treasure_hanzi')->where('uid', $c['uid'])->value('type_str'));
        $this->assertSame(1, DB::table('act_treasure_exchange')->where('uid', $c['uid'])->where('hid', 1)->count());
        $this->assertSame(1, self::messages($c['uid'], '总共收集了11个汉字，共计330元宝'));
        // 活动结束 10 小时内：补发未领的排行奖励与集字成就
        $endtime = (int)$act['endtime'];
        $this->assertSame(-2, ActTreasureHandler::cronRankReward($endtime - 1));
        for ($i = 1; $i <= 8; $i++) {
            DB::table('act_treasure_log')->insert(['uid' => $b['uid'], 'version' => $version, 'site' => 1, 'type' => 19, 'num' => 3, 'max_num' => $i, 'dateline' => 1]);
        }
        $purpleB = TraderLog::jade($b['uid'], 3);
        $silverB = self::memberValue($b['uid'], 'silver');
        $this->assertSame(1, ActTreasureHandler::cronRankReward($endtime + 60));
        $this->assertSame(1, ActTreasureHandler::cronAchieveReward($endtime + 60));
        // 第 2 名 76000 紫宝石；集齐 3 次：成就 1、2 → 银币 700 万、紫宝石 500、精炼石 8 万
        $this->assertSame($purpleB + 76000 + 500, TraderLog::jade($b['uid'], 3));
        $this->assertSame($silverB + 7000000, self::memberValue($b['uid'], 'silver'));
        $this->assertSame(1, self::messages($b['uid'], '获得了第2名的奖励：紫宝石76000个，'));
        $this->assertSame(1, self::messages($b['uid'], '集字成就奖励已自动帮您领取'));
        $this->assertSame(2, DB::table('act_treasure_hanziach_log')->where('uid', $b['uid'])->count());
        // 重复执行不重复发
        ActTreasureHandler::cronRankReward($endtime + 120);
        ActTreasureHandler::cronAchieveReward($endtime + 120);
        $this->assertSame($purpleB + 76000 + 500, TraderLog::jade($b['uid'], 3));
        $this->assertSame(-3, ActTreasureHandler::cronRankReward($endtime + 36001));
    }

    public function testTreasureFreeResetsDaily(): void
    {
        $p = $this->player(['level' => 60]);
        $uid = $p['uid'];
        DB::table('member_field')->where('uid', $uid)->update(['treasure_free' => 0, 'dateline' => self::today() - 86400]);
        Member::dailyCron($uid, self::today());
        $this->assertSame(1, (int)DB::table('member_field')->where('uid', $uid)->value('treasure_free'));
    }

    // ---------------------------------------------------------------------------------------------
    // 我爱世界杯
    // ---------------------------------------------------------------------------------------------

    public function testWorldcupActionBoxPayChipRankAndCron(): void
    {
        $p = $this->player(['level' => 50, 'gold' => 1000]);
        $uid = $p['uid'];
        BossClock::freeze(self::today());
        $this->assertSame(-1, $this->request($p['auth'], 'worldcup', 'index')['status']);
        self::open('worldcup');
        $act = self::act('worldcup');
        $version = (int)$act['version'];
        $index = $this->request($p['auth'], 'worldcup', 'index');
        $this->assertSame([1, 0, (int)$act['endtime'] - 86400 - self::today()], [$index['status'], $index['own_chip'], $index['cd']]);
        $this->assertSame([['id' => 1, 'price' => 100], ['id' => 2, 'price' => 400]], $index['item']);
        $this->assertCount(16, $index['reward']);
        $this->assertSame([1, 1380, 500000, 0], [$index['reward'][0]['id'], $index['reward'][0]['number'], $index['reward'][0]['value1'], $index['reward'][0]['open']]);

        $this->assertSame(-7, $this->request($p['auth'], 'worldcup', 'action')['status']);
        mt_srand(11);
        $act1 = $this->request($p['auth'], 'worldcup', 'action', ['id' => 1]);
        $this->assertSame(1, $act1['status']);
        $this->assertContains($act1['add_chip'], [2, 3, 4]);
        $this->assertSame($act1['add_chip'], $act1['number']);
        $this->assertSame(900, $act1['all_gold']);
        $this->assertSame(1, DB::table('worldcup_action_log')->where('uid', $uid)->where('gold', 100)->count());
        DB::table('member')->where('uid', $uid)->update(['gold' => 50]);
        $this->assertSame(-3, $this->request($p['auth'], 'worldcup', 'action', ['id' => 2])['status']);

        // 宝箱：碎片够 1380 开第一个
        $this->assertSame(-2, $this->request($p['auth'], 'worldcup', 'get_box_reward', ['id' => -1])['status']);
        $this->assertSame(-4, $this->request($p['auth'], 'worldcup', 'get_box_reward', ['id' => 99])['status']);
        $this->assertSame(-5, $this->request($p['auth'], 'worldcup', 'get_box_reward', ['id' => 1])['status']);
        DB::table('worldcup_log')->where('uid', $uid)->update(['own_chip' => 1380]);
        $silver = self::memberValue($uid, 'silver');
        $soul = self::memberValue($uid, 'soul');
        $lottery = (int)MemberIntegral::get($uid)['act_kemari_int'];
        $box = $this->request($p['auth'], 'worldcup', 'get_box_reward', ['id' => 1]);
        $this->assertSame([1, $silver + 500000], [$box['status'], $box['all_silver']]);
        $this->assertSame([$soul + 1000, $lottery + 10], [self::memberValue($uid, 'soul'), (int)MemberIntegral::get($uid)['act_kemari_int']]);
        $this->assertSame(-6, $this->request($p['auth'], 'worldcup', 'get_box_reward', ['id' => 1])['status']);
        $this->assertSame(2, $this->request($p['auth'], 'worldcup', 'index')['reward'][0]['open']);

        // 充值记账：每 1 元 1 个充值碎片
        $this->assertSame(1, Pay::grantManual($uid, 2, self::today())['status']);
        $this->assertSame(30.0, (float)DB::table('worldcup_order')->where('uid', $uid)->value('money'));
        $info = $this->request($p['auth'], 'worldcup', 'get_pay_chip_info');
        $this->assertSame(['status' => 1, 'gold' => 300, 'get' => 0, 'isget' => 1], $info);
        $chip = $this->request($p['auth'], 'worldcup', 'get_pay_chip');
        $this->assertSame([1, 30, 1380 + 30, 30], [$chip['status'], $chip['add_chip'], $chip['all_chip'], $chip['all_pay_chip']]);
        $this->assertSame(-3, $this->request($p['auth'], 'worldcup', 'get_pay_chip')['status']);

        // 排行：结束前不能领；提前一天结束后领宝石
        $q = $this->player();
        DB::table('worldcup_log')->insert(['version' => $version, 'uid' => $q['uid'], 'name' => 'q', 'own_chip' => 10, 'pay_chip' => 0, 'end_chip' => 0, 'get_rank' => 0, 'dateline' => 1]);
        $list = $this->request($p['auth'], 'worldcup', 'get_rank_list');
        $this->assertSame([1, 0], [$list['status'], $list['isget']]);
        $this->assertSame([[$uid, 1, 3, 300], [$q['uid'], 2, 3, 200]], array_map(static fn ($r) => [$r['uid'], $r['rank'], $r['type'], $r['value']], $list['list']));
        $this->assertArrayNotHasKey('pay_chip', $list['list'][0]);
        $this->assertSame(-2, $this->request($p['auth'], 'worldcup', 'get_rank_reward')['status']);
        BossClock::freeze((int)$act['endtime'] - 86400);
        $this->assertSame(-1, $this->request($p['auth'], 'worldcup', 'action', ['id' => 1])['status']);
        $this->assertSame(1, $this->request($p['auth'], 'worldcup', 'get_rank_list')['isget']);
        $purple = TraderLog::jade($uid, 3);
        $this->assertSame(['status' => 1, 'reward' => ['rank' => 1, 'type' => 3, 'value' => 300]], $this->request($p['auth'], 'worldcup', 'get_rank_reward'));
        $this->assertSame($purple + 300, TraderLog::jade($uid, 3));
        $this->assertSame(-5, $this->request($p['auth'], 'worldcup', 'get_rank_reward')['status']);
        // 计划任务（cron_kemari::index 里）给未领的第 2 名补发
        $purpleQ = TraderLog::jade($q['uid'], 3);
        $this->assertSame(1, WorldcupHandler::cronRankReward((int)$act['endtime'] + 60));
        $this->assertSame($purpleQ + 200, TraderLog::jade($q['uid'], 3));
        $this->assertSame(1, self::messages($q['uid'], '获得了第2名的奖励：紫宝石200个，请查收!'));
        WorldcupHandler::cronRankReward((int)$act['endtime'] + 120);
        $this->assertSame($purpleQ + 200, TraderLog::jade($q['uid'], 3));
    }

    // ---------------------------------------------------------------------------------------------
    // 蹴鞠大赛
    // ---------------------------------------------------------------------------------------------

    public function testKemariShootLotteryRankAndCron(): void
    {
        $p = $this->player(['level' => 50, 'vip' => 2, 'gold' => 1000]);
        $uid = $p['uid'];
        BossClock::freeze(self::today());
        $this->assertSame(-1, $this->request($p['auth'], 'act_kemari', 'index')['status']);
        self::open('actkemari');
        $act = self::act('actkemari');
        $version = (int)$act['version'];
        $index = $this->request($p['auth'], 'act_kemari', 'index');
        $this->assertSame(1, $index['status']);
        $this->assertSame([[1, 0, 0, 3], [2, 0, 0, 1]], array_map(static fn ($r) => [$r['id'], $r['cd'], $r['price'], $r['times']], $index['list']));
        $this->assertSame(0, $index['lottery_num']);

        $this->assertSame(-4, $this->request($p['auth'], 'act_kemari', 'action', ['type' => 3])['status']);
        mt_srand(5);
        // 免费小球：剩 2 次，30 分钟冷却
        $shot = $this->request($p['auth'], 'act_kemari', 'action', ['type' => 1]);
        $this->assertSame([1, 2, 100, 1800, 1000], [$shot['status'], $shot['times'], $shot['price'], $shot['cd'], $shot['all_gold']]);
        $this->assertContains($shot['is_succ'], [0, 1]);
        $this->assertArrayHasKey('rate_in', $shot['reward_info']);
        $row = DB::table('actkemari')->where('uid', $uid)->first();
        $this->assertSame([2, 1, $shot['is_succ']], [(int)$row['free_times1'], (int)$row['total_num'], (int)$row['goal_in']]);
        $this->assertSame(1, DB::table('actkemari_log')->where('uid', $uid)->count());
        // 旧版每日重置看 refresh_time：建档时为 0、首页时次数没变化不写，所以第一次射门后的下一次请求会再重置一次（照旧版，又是免费）
        $again = $this->request($p['auth'], 'act_kemari', 'action', ['type' => 1]);
        $this->assertSame([1, 2, 1000], [$again['status'], $again['times'], self::memberValue($uid, 'gold')]);
        $this->assertSame(self::today(), (int)DB::table('actkemari')->where('uid', $uid)->value('refresh_time'));
        // 冷却中射门扣 100 元宝
        $paid = $this->request($p['auth'], 'act_kemari', 'action', ['type' => 1]);
        $this->assertSame([1, 2], [$paid['status'], $paid['times']]);
        $this->assertSame(900, self::memberValue($uid, 'gold') - ($paid['reward_info']['type'] === 1 ? $paid['reward_info']['value'] : 0));
        // 大球免费 1 次后到明天
        $big = $this->request($p['auth'], 'act_kemari', 'action', ['type' => 2]);
        $this->assertSame([1, 0, (int)strtotime(date('Y-m-d', self::today() + 86400)) - self::today()], [$big['status'], $big['times'], $big['cd']]);
        DB::table('member')->where('uid', $uid)->update(['gold' => 10]);
        $this->assertSame(4, $this->request($p['auth'], 'act_kemari', 'action', ['type' => 2])['status']);

        // 奖励预览：武将 / 神将排最后
        $preview = $this->request($p['auth'], 'act_kemari', 'kemari_reward', ['type' => 1]);
        $this->assertSame(1, $preview['status']);
        $this->assertSame(-2, $this->request($p['auth'], 'act_kemari', 'kemari_reward', ['type' => 'x'])['status']);
        $types = array_column($preview['list'], 'type');
        $nonGeneral = array_values(array_filter($types, static fn ($t) => !in_array($t, [12, 26], true)));
        $this->assertSame(array_values(array_unique($nonGeneral)), $nonGeneral);

        // 兑奖券
        $lottery = $this->request($p['auth'], 'act_kemari', 'get_lottery');
        $this->assertSame(1, $lottery['status']);
        $this->assertCount(36, $lottery['list']);
        $this->assertSame(-2, $this->request($p['auth'], 'act_kemari', 'action_lottery')['status']);
        // 参数错误时兑奖锁不释放（照旧版）
        $this->assertSame(2, $this->request($p['auth'], 'act_kemari', 'action_lottery', ['id' => 1])['status']);
        Redis::del('lottery_lock' . $uid);
        // 射门送的兑奖券清零后再兑
        DB::table('member_integral')->where('uid', $uid)->update(['act_kemari_int' => 0]);
        $this->assertSame(3, $this->request($p['auth'], 'act_kemari', 'action_lottery', ['id' => 1])['status']);
        MemberIntegral::change($uid, 25, 'act_kemari_int', 1, 'test', 'test');
        $silver = self::memberValue($uid, 'silver');
        $ex = $this->request($p['auth'], 'act_kemari', 'action_lottery', ['id' => 1]);
        $this->assertSame([1, 15, 1], [$ex['status'], $ex['lottery_num'], $ex['reward']['is_exch']]);
        $this->assertSame($silver + 2000000, self::memberValue($uid, 'silver'));
        $this->assertSame(1, DB::table('actkemari_exchange_log')->where('uid', $uid)->where('lottery_num', 10)->count());

        // 进球榜（VIP 2 以上）与领奖
        DB::table('actkemari')->where('uid', $uid)->update(['goal_in' => 5]);
        $q = $this->player(['vip' => 3]);
        DB::table('actkemari')->insert(['uid' => $q['uid'], 'version' => $version, 'nickname' => 'q', 'vip' => 3, 'goal_in' => 3, 'dateline' => 1]);
        $rank = $this->request($p['auth'], 'act_kemari', 'kemari_rank');
        $this->assertSame([1, 1, 0, 2], [$rank['status'], $rank['rank'], $rank['is_get'], $rank['vip']]);
        $this->assertSame(['type' => 4, 'value' => 500, 'name' => '紫宝石'], array_intersect_key($rank['rank_reward'], ['type' => 0, 'value' => 0, 'name' => 0]));
        $this->assertSame([[1, 5], [2, 3]], array_map(static fn ($r) => [$r['rank'], $r['num']], $rank['list']));
        $this->assertSame(2, $this->request($p['auth'], 'act_kemari', 'kemari_rank_reward')['status']);
        BossClock::freeze((int)$act['endtime'] - 86401);
        $this->assertSame(-3, $this->request($p['auth'], 'act_kemari', 'action', ['type' => 1])['status']);
        $purple = TraderLog::jade($uid, 3);
        $got = $this->request($p['auth'], 'act_kemari', 'kemari_rank_reward');
        $this->assertSame([1, ['rank' => 1, 'type' => 4, 'value' => 500]], [$got['status'], $got['reward']]);
        $this->assertSame($purple + 500, TraderLog::jade($uid, 3));
        $this->assertSame(4, $this->request($p['auth'], 'act_kemari', 'kemari_rank_reward')['status']);

        // 计划任务：结束前一天起兑奖券换银币；结束后补发排行奖励
        $silver = self::memberValue($uid, 'silver');
        $this->assertSame(1, ActKemariHandler::cronLotteryReward((int)$act['endtime'] - 86400 + 10));
        $this->assertSame([$silver + 15 * 20000, 0], [self::memberValue($uid, 'silver'), (int)MemberIntegral::get($uid)['act_kemari_int']]);
        $this->assertSame(1, self::messages($uid, '蹴鞠活动已结束，您未兑换的兑奖券，系统已自动兑换，兑换银币300000个，'));
        $purpleQ = TraderLog::jade($q['uid'], 3);
        $this->assertSame(-2, ActKemariHandler::cronRankReward((int)$act['endtime'] - 10));
        $this->assertSame(1, ActKemariHandler::cronRankReward((int)$act['endtime'] + 10));
        $this->assertSame($purpleQ + 400, TraderLog::jade($q['uid'], 3));
        $this->assertSame(1, self::messages($q['uid'], '获得了第2名的奖励：紫宝石400个，请查收!'));
    }

    public function testKemariDailyResetCron(): void
    {
        self::open('actkemari');
        $act = self::act('actkemari');
        $p = $this->player();
        DB::table('actkemari')->insert(['uid' => $p['uid'], 'version' => (int)$act['version'], 'nickname' => 'x', 'free_times1' => 0, 'free_times2' => 0, 'normal_time1' => 5, 'normal_time2' => 6]);
        // 活动开始 5 小时内整个任务不执行
        $this->assertSame([], ActKemariHandler::cronIndex((int)$act['starttime'] + 3600));
        $res = ActKemariHandler::cronIndex((int)$act['starttime'] + 86400);
        $this->assertSame(1, $res['update_kemari_info']);
        $row = DB::table('actkemari')->where('uid', $p['uid'])->first();
        $this->assertSame([3, 1, 0, 0], [(int)$row['free_times1'], (int)$row['free_times2'], (int)$row['normal_time1'], (int)$row['normal_time2']]);
    }

    // ---------------------------------------------------------------------------------------------
    // 七星灯
    // ---------------------------------------------------------------------------------------------

    public function testSevenstarLightCallRankAndCron(): void
    {
        $p = $this->player(['level' => 50]);
        $uid = $p['uid'];
        BossClock::freeze(self::today());
        $this->assertSame(-1, $this->request($p['auth'], 'act_sevenstar', 'index', ['d' => 'lc'])['status']);
        self::open('sevenstar');
        $act = self::act('sevenstar');
        $version = (int)$act['version'];
        TraderLog::addJade($uid, 3, 5000);
        $index = $this->request($p['auth'], 'act_sevenstar', 'index', ['d' => 'lc']);
        $this->assertSame([1, 0, 0, 0, 1498, 1], [$index['status'], $index['god_soul'], $index['site'], $index['one'], $index['all'], $index['seven']]);
        $this->assertSame([167, 168], [$index['list'][0]['info']['info']['id'], $index['list'][1]['info']['info']['id']]);
        $this->assertSame([1, 0, 135], [$index['list'][0]['info']['status'], $index['list'][0]['info']['own'], $index['list'][0]['price']]);
        $this->assertArrayNotHasKey('talentid', $index['list'][0]['info']['info']);
        $this->assertArrayNotHasKey('gid', $index['list'][0]);

        $this->assertSame(-4, $this->request($p['auth'], 'act_sevenstar', 'action_light', ['d' => 'lc', 'id' => 2])['status']);
        mt_srand(7);
        // 今天第一次免费
        $one = $this->request($p['auth'], 'act_sevenstar', 'action_light', ['d' => 'lc', 'id' => 1]);
        $this->assertSame([1, 1, 0, 214, 5000], [$one['status'], $one['site'], $one['reset'], $one['one'], $one['get3'] - self::rewardPurple($one)]);
        $this->assertNotEmpty($one['reward']);
        $this->assertArrayNotHasKey('rate', $one['reward'][0]);
        // 已点亮后不能一次点七盏
        $this->assertSame(-5, $this->request($p['auth'], 'act_sevenstar', 'action_light', ['d' => 'lc', 'id' => 7])['status']);
        $jade = TraderLog::jade($uid, 3);
        $two = $this->request($p['auth'], 'act_sevenstar', 'action_light', ['d' => 'lc', 'id' => 1]);
        $this->assertSame([1, 2], [$two['status'], $two['site']]);
        $this->assertSame($jade - 214, $two['get3'] - self::rewardPurple($two));
        // 一次点亮七盏：重置并必得 1 个神将魂魄
        DB::table('actsevenstar')->where('uid', $uid)->update(['light_site' => 0]);
        $soul = (int)MemberIntegral::get($uid)['god_soul'];
        $all = $this->request($p['auth'], 'act_sevenstar', 'action_light', ['d' => 'lc', 'id' => 7]);
        $this->assertSame([1, 0, 1, 1], [$all['status'], $all['site'], $all['reset'], $all['seven']]);
        $gotSoul = array_sum(array_column(array_filter($all['reward'], static fn ($r) => $r['type'] === 30), 'num'));
        $this->assertGreaterThanOrEqual(1, (int)MemberIntegral::get($uid)['god_soul'] - $soul);
        $this->assertGreaterThanOrEqual(1, $gotSoul);
        $this->assertSame(9, (int)DB::table('actsevenstar')->where('uid', $uid)->value('times'));
        DB::table('member')->where('uid', $uid)->update(['vip' => 0]);
        DB::table('trader_log')->where('uid', $uid)->update(['get3' => 0]);
        $this->assertSame(-7, $this->request($p['auth'], 'act_sevenstar', 'action_light', ['d' => 'lc', 'id' => 1])['status']);

        // 召唤神将
        $this->assertSame(-3, $this->request($p['auth'], 'act_sevenstar', 'call_godgeneral', ['d' => 'lc'])['status']);
        $this->assertSame(-4, $this->request($p['auth'], 'act_sevenstar', 'call_godgeneral', ['d' => 'lc', 'id' => 1])['status']);
        DB::table('member_integral')->where('uid', $uid)->update(['god_soul' => 134]);
        $this->assertSame(-5, $this->request($p['auth'], 'act_sevenstar', 'call_godgeneral', ['d' => 'lc', 'id' => 167])['status']);
        DB::table('member_integral')->where('uid', $uid)->update(['god_soul' => 140]);
        $call = $this->request($p['auth'], 'act_sevenstar', 'call_godgeneral', ['d' => 'lc', 'id' => 167]);
        $this->assertSame([1, null, 167], [$call['status'], $call['god_soul'], $call['info']['generalid']]);
        $this->assertSame(5, (int)MemberIntegral::get($uid)['god_soul']);
        $this->assertTrue(General::ownsTemplate($uid, 167));
        // 魂魄不够先回 -5，够了才查是否已拥有
        $this->assertSame(-5, $this->request($p['auth'], 'act_sevenstar', 'call_godgeneral', ['d' => 'lc', 'id' => 167])['status']);
        DB::table('member_integral')->where('uid', $uid)->update(['god_soul' => 140]);
        $this->assertSame(-6, $this->request($p['auth'], 'act_sevenstar', 'call_godgeneral', ['d' => 'lc', 'id' => 167])['status']);
        DB::table('member_integral')->where('uid', $uid)->update(['god_soul' => 5]);
        // 锁：请求太快
        Redis::set('sevenstar_lk_call_godgeneral' . $uid, $uid, 10);
        $this->assertSame(-2, $this->request($p['auth'], 'act_sevenstar', 'call_godgeneral', ['d' => 'lc', 'id' => 168])['status']);
        Redis::del('sevenstar_lk_call_godgeneral' . $uid);

        // 排行（点灯次数 ≥ 7，显示 ÷ 7）
        $q = $this->player();
        DB::table('actsevenstar')->insert(['uid' => $q['uid'], 'nickname' => 'q', 'level' => 1, 'light_site' => 0, 'times' => 30, 'version' => $version]);
        $rank = $this->request($p['auth'], 'act_sevenstar', 'get_rank_list', ['d' => 'lc']);
        $this->assertSame([1, 0], [$rank['status'], $rank['isget']]);
        $this->assertSame([[$q['uid'], 1, 4], [$uid, 2, 1]], array_map(static fn ($r) => [$r['uid'], $r['rank'], $r['times']], $rank['list']));
        $this->assertSame([32, 'gem_level' => 21], [$rank['list'][0]['type2'], 'gem_level' => $rank['list'][0]['value2']['gem_level']]);
        $this->assertSame(2, $rank['own_reward']['rank']);
        $this->assertSame(-2, $this->request($p['auth'], 'act_sevenstar', 'get_rank_reward', ['d' => 'lc'])['status']);
        BossClock::freeze((int)$act['endtime'] - 86400);
        $diamond = (int)MemberIntegral::get($uid)['beauty_diamond'];
        $mounts = DB::table('own_mount')->where('uid', $uid)->count();
        $got = $this->request($p['auth'], 'act_sevenstar', 'get_rank_reward', ['d' => 'lc']);
        $this->assertSame(1, $got['status']);
        $this->assertSame([$diamond + 100, $mounts + 30], [(int)MemberIntegral::get($uid)['beauty_diamond'], DB::table('own_mount')->where('uid', $uid)->count()]);
        $this->assertSame(-6, $this->request($p['auth'], 'act_sevenstar', 'get_rank_reward', ['d' => 'lc'])['status']);

        // 计划任务：魂魄 100 紫宝石一个；第 1 名补发钻石与 21 级宝石
        $jadeP = TraderLog::jade($uid, 3);
        $gems = DB::table('own_gem')->where('uid', $q['uid'])->where('level', 21)->count();
        $res = ActSevenstarHandler::cronTask((int)$act['endtime'] + 60);
        $this->assertSame(['_send_sevenstar_reward' => 1, '_send_sevenstar_rank_reward' => 1], $res);
        $this->assertSame([$jadeP + 500, 0], [TraderLog::jade($uid, 3), (int)MemberIntegral::get($uid)['god_soul']]);
        $this->assertSame(1, self::messages($uid, '您剩余的5个神将魂魄,已经自动兑换成紫宝石500;个'));
        $this->assertSame($gems + 3, DB::table('own_gem')->where('uid', $q['uid'])->where('level', 21)->count());
        $this->assertSame(1, self::messages($q['uid'], '奖励为钻石120;宝石-21级'));
        ActSevenstarHandler::cronTask((int)$act['endtime'] + 120);
        $this->assertSame($gems + 3, DB::table('own_gem')->where('uid', $q['uid'])->where('level', 21)->count());
    }

    /** 点灯奖励里的紫宝石 */
    private static function rewardPurple(array $res): int
    {
        return array_sum(array_column(array_filter($res['reward'] ?? [], static fn ($r) => $r['type'] === 4), 'num'));
    }

    // ---------------------------------------------------------------------------------------------
    // 貔貅聚财
    // ---------------------------------------------------------------------------------------------

    public function testEnrichmentPayPeriodClaimDaysIconAndCron(): void
    {
        $p = $this->player(['gold' => 0]);
        $uid = $p['uid'];
        BossClock::freeze(self::today());
        self::open('actenrichment');
        $act = self::act('actenrichment');
        $version = (int)$act['version'];
        $end = (int)$act['endtime'];
        // 充值期：记账（商品元宝）
        $this->assertSame(1, Pay::grantManual($uid, 2, self::today())['status']);
        $this->assertSame(300.0, (float)DB::table('actenrichment_order')->where('uid', $uid)->value('gold'));
        DB::table('actenrichment_order')->insert(['uid' => $uid, 'nickname' => 'x', 'level' => 1, 'gold' => 32500, 'version' => $version, 'dateline' => 1]);
        $index = $this->request($p['auth'], 'act_enrichment', 'index');
        $this->assertSame([1, 0, 32800], [$index['status'], $index['button_status'], $index['gold_num']]);
        $this->assertSame([0, 1, 0], array_column($index['reward_info'], 'status'));
        $this->assertSame([10000, 32800, 64800], array_column($index['order_info'], 'gold'));
        $this->assertSame([2, 5, 13], [$index['reward_info'][1]['type'], $index['reward_info'][1]['value'], $index['reward_info'][1]['value1']]);
        $this->assertArrayHasKey('info', $index['reward_info'][1]);
        $this->assertSame(['status' => -1, 'msg' => '活动未结束。'], $this->request($p['auth'], 'act_enrichment', 'get_day_reward', ['day' => 1]));

        // 领奖期第 5 天（结束前 2 ~ 3 天）
        BossClock::freeze($end - 2 * 86400 - 100);
        $this->assertSame(1, $this->request($p['auth'], 'act_enrichment', 'index')['button_status']);
        $this->assertSame(-2, $this->request($p['auth'], 'act_enrichment', 'get_day_reward')['status']);
        $this->assertSame(-3, $this->request($p['auth'], 'act_enrichment', 'get_day_reward', ['day' => 6])['status']);
        $got = $this->request($p['auth'], 'act_enrichment', 'get_day_reward', ['day' => 1]);
        $this->assertSame([1, ['type' => 1, 'value' => 240], 300 + 240], [$got['status'], $got['reward'], $got['gold']]);
        $this->assertSame(-6, $this->request($p['auth'], 'act_enrichment', 'get_day_reward', ['day' => 1])['status']);
        $ri = $this->request($p['auth'], 'act_enrichment', 'reward_index');
        $this->assertSame(1, $ri['status']);
        $this->assertSame([2, 1, 1, 1, 1, 0, 0], array_column($ri['reward_info'][1], 'is_get'));
        // 主城图标：还有没领的
        $flags = $this->request($p['auth'], 'member', 'index');
        $flags = $flags['member'] ?? $flags;
        $this->assertSame(1, $flags['actenrichment']);
        // 没有充值的玩家领奖期活动按未开启
        $q = $this->player();
        $this->assertSame(-1, $this->request($q['auth'], 'act_enrichment', 'index')['status']);
        $flagsQ = $this->request($q['auth'], 'member', 'index');
        $this->assertSame(0, ($flagsQ['member'] ?? $flagsQ)['actenrichment']);

        // 计划任务：结束后补发没领的 6 天（元宝合并、将魂 5 级 13）
        $gold = self::memberValue($uid, 'gold');
        $souls = DB::table('own_soul')->where('uid', $uid)->count();
        $this->assertSame(-2, ActEnrichmentHandler::cronReward($end - 1));
        $this->assertSame(1, ActEnrichmentHandler::cronReward($end + 60));
        $this->assertSame($gold + 340 + 450 + 500 + 750 + 1000, self::memberValue($uid, 'gold'));
        $this->assertSame($souls + 1, DB::table('own_soul')->where('uid', $uid)->count());
        $this->assertSame(1, self::messages($uid, '貔貅聚财活动已结束'));
        $this->assertSame(7, DB::table('actenrichment_getreward_log')->where('uid', $uid)->count());
        ActEnrichmentHandler::cronReward($end + 120);
        $this->assertSame(1, self::messages($uid, '貔貅聚财活动已结束'));
    }

    // ---------------------------------------------------------------------------------------------
    // 招募战友领工资
    // ---------------------------------------------------------------------------------------------

    public function testPaychecksStatRewardAndCron(): void
    {
        $p = $this->player(['level' => 60, 'gold' => 0]);
        $uid = $p['uid'];
        BossClock::freeze(self::today());
        self::open('paychecks');
        $act = self::act('paychecks');
        $low = $this->player(['level' => 49]);
        $this->assertSame(-3, $this->request($low['auth'], 'act_paychecks', 'index')['status']);
        $index = $this->request($p['auth'], 'act_paychecks', 'index');
        $this->assertSame([1, 0, 0, 0, 1], [$index['status'], $index['number'], $index['pay_gold'], $index['pay_check'], $index['is_act']]);
        $this->assertSame(8, strlen($index['code']));
        $this->assertSame([0, 0, 0, 0, 0], array_column($index['list'], 'completed'));
        $this->assertSame(-2, $this->request($p['auth'], 'act_paychecks', 'get_reward')['status']);
        $this->assertSame(-3, $this->request($p['auth'], 'act_paychecks', 'get_reward', ['id' => 9])['status']);
        $this->assertSame(-5, $this->request($p['auth'], 'act_paychecks', 'get_reward', ['id' => 1])['status']);

        // 活动开始后注册、达到 80 级的被邀请者，本期充值 300 元宝
        $friend = $this->player(['level' => 80]);
        $weak = $this->player(['level' => 70]);
        foreach ([$friend, $weak] as $f) {
            // 注册时间按 PHP 时区写（测试库的 CURRENT_TIMESTAMP 是 UTC）
            DB::table('member')->where('uid', $f['uid'])->update(['created_at' => date('Y-m-d H:i:s', self::today())]);
            DB::table('uc_invitation_log')->insert(['serverid' => Server::id(), 'uid' => $f['uid'], 'from_serverid' => Server::id(), 'from_uid' => $uid, 'dateline' => self::today()]);
        }
        $this->assertSame(1, Pay::grantManual($friend['uid'], 2, self::today())['status']);
        $this->assertSame(1, ActPaychecksHandler::cronStat(self::today()));
        $index = $this->request($p['auth'], 'act_paychecks', 'index');
        $this->assertSame([1, 300, 30], [$index['number'], $index['pay_gold'], $index['pay_check']]);
        $this->assertSame([1, 0, 0, 0, 0], array_column($index['list'], 'completed'));
        $got = $this->request($p['auth'], 'act_paychecks', 'get_reward', ['id' => 1]);
        $this->assertSame([1, 200], [$got['status'], $got['all_gold']]);
        $this->assertArrayNotHasKey('desc', $got['reward']);
        $this->assertSame(-4, $this->request($p['auth'], 'act_paychecks', 'get_reward', ['id' => 1])['status']);
        $this->assertSame(-5, $this->request($p['auth'], 'act_paychecks', 'get_reward', ['id' => 2])['status']);
        // 重新统计：人数不变（软删除后覆盖）
        $this->assertSame(1, ActPaychecksHandler::cronStat(self::today() + 60));
        $this->assertSame(1, DB::table('act_paychecks')->where('uid', $uid)->whereNull('deleted_at')->count());

        // 活动结束 1 小时内发工资（充值 × 0.1）
        $end = (int)$act['endtime'];
        $this->assertSame(-2, ActPaychecksHandler::cronReward($end - 1));
        $this->assertSame(1, ActPaychecksHandler::cronReward($end + 60));
        $this->assertSame(200 + 30, self::memberValue($uid, 'gold'));
        $this->assertSame(1, self::messages($uid, '您的战友充值额度为300元宝，可领工资为30元宝'));
        ActPaychecksHandler::cronReward($end + 120);
        $this->assertSame(230, self::memberValue($uid, 'gold'));
        $this->assertSame(-3, ActPaychecksHandler::cronReward($end + 3601));
    }

    // ---------------------------------------------------------------------------------------------
    // 老兵召回
    // ---------------------------------------------------------------------------------------------

    public function testVeteranRewardAndFreeInherit(): void
    {
        $p = $this->player(['level' => 80, 'vip' => 0]);
        $uid = $p['uid'];
        BossClock::freeze(self::today());
        $this->assertSame(-1, $this->request($p['auth'], 'act_veteran', 'index')['status']);
        self::open('recall');
        $act = self::act('recall');
        $index = $this->request($p['auth'], 'act_veteran', 'index');
        $this->assertSame([1, 0], [$index['status'], $index['is_reward']]);
        $this->assertSame([16, 16, 17], array_column($index['reward'], 'type'));
        $this->assertSame(['武将', '武将', '一次至尊继承'], array_column($index['reward'], 'name'));
        $this->assertArrayHasKey('info', $index['reward'][0]);
        $this->assertArrayNotHasKey('info', $index['reward'][2]);
        $this->assertSame('2014-07-14后未登录过游戏且等级达到80级以上的玩家', $index['act_info']['act_level']);
        $this->assertSame(5, $this->request($p['auth'], 'act_veteran', 'get_reward')['status']);
        // 上一次之前的登录距活动开始满 60 天
        DB::table('member_log')->where('uid', $uid)->delete();
        DB::table('member_log')->insert(['ymd' => 1, 'uid' => $uid, 'dateline' => (int)$act['starttime'] - 61 * 86400]);
        DB::table('member_log')->insert(['ymd' => 2, 'uid' => $uid, 'dateline' => self::today()]);
        $this->assertSame(1, $this->request($p['auth'], 'act_veteran', 'index')['is_reward']);
        DB::table('member')->where('uid', $uid)->update(['level' => 79]);
        $this->assertSame(4, $this->request($p['auth'], 'act_veteran', 'get_reward')['status']);
        DB::table('member')->where('uid', $uid)->update(['level' => 80]);
        $got = $this->request($p['auth'], 'act_veteran', 'get_reward');
        $this->assertSame(1, $got['status']);
        $this->assertTrue(General::ownsTemplate($uid, 144));
        $this->assertTrue(General::ownsTemplate($uid, 152));
        $this->assertSame(3, $this->request($p['auth'], 'act_veteran', 'get_reward')['status']);
        $this->assertSame(2, $this->request($p['auth'], 'act_veteran', 'index')['is_reward']);

        // 武将继承：VIP 0 也有免费的至尊继承位
        DB::table('member')->where('uid', $uid)->update(['level' => 80]);
        $inherit = $this->request($p['auth'], 'general_inherit', 'index');
        $this->assertSame([1, 4], [$inherit['status'], $inherit['free_site']]);
        $this->assertArrayHasKey(4, $inherit['rule']);
        $this->assertSame(1, $inherit['free_inherit']['inherit_4']);
        $gid = (int)DB::table('general')->where('uid', $uid)->where('generalid', 144)->value('id');
        $info = $this->request($p['auth'], 'general_inherit', 'get_inherit_info', ['gid' => $gid]);
        if ($info['status'] === 1) {
            $this->assertSame(0, $info['rule'][4]['gold']);
        }
        // 预览不受 VIP 限制
        $gid2 = (int)DB::table('general')->where('uid', $uid)->where('generalid', 152)->value('id');
        $this->assertNotSame(-4, $this->request($p['auth'], 'general_inherit', 'preview', ['gid1' => $gid, 'gid2' => $gid2, 'type' => 4])['status']);
        // 用过之后恢复
        DB::table('act_veteran_log')->where('uid', $uid)->update(['is_inherit' => 1]);
        $this->assertSame(0, $this->request($p['auth'], 'general_inherit', 'index')['free_site']);
        $this->assertSame(-4, $this->request($p['auth'], 'general_inherit', 'preview', ['gid1' => $gid, 'gid2' => $gid2, 'type' => 4])['status']);
    }

    // ---------------------------------------------------------------------------------------------
    // 充值送豪礼
    // ---------------------------------------------------------------------------------------------

    public function testThirtyDailyRewardChipGeneralAndCrons(): void
    {
        $p = $this->player(['silver' => 0]);
        $uid = $p['uid'];
        BossClock::freeze(self::today());
        self::open('actthirty');
        $act = self::act('actthirty');
        $version = (int)$act['version'];
        // 没有打开过首页：领奖回 -3
        $this->assertSame(-3, $this->request($p['auth'], 'act_thirty', 'action_reward')['status']);
        $index = $this->request($p['auth'], 'act_thirty', 'index');
        $this->assertSame([1, 0, 0, 0, 300, 1, 70, 0], [$index['status'], $index['is_open'], $index['chip_status'], $index['gold'], $index['max_gold'], $index['day'], $index['total_chip'], $index['own_chip']]);
        $this->assertSame([2, 10, 15], array_column($index['reward_info'], 'type'));
        $this->assertSame([3000000, 100, 10], array_column($index['reward_info'], 'num'));
        $this->assertSame('武将魂魄', $index['reward_info'][2]['name'] === null ? '' : '武将魂魄');
        $this->assertSame(-3, $this->request($p['auth'], 'act_thirty', 'action_reward')['status']);
        // 充值 30 元 = 300 元宝
        $this->assertSame(1, Pay::grantManual($uid, 2, self::today())['status']);
        $this->assertSame(300.0, (float)DB::table('thirty_order')->where('uid', $uid)->value('gold'));
        $this->assertSame([1, 300], [$this->request($p['auth'], 'act_thirty', 'index')['is_open'], $this->request($p['auth'], 'act_thirty', 'index')['gold']]);
        $leap = FreeLeap::get($uid);
        $got = $this->request($p['auth'], 'act_thirty', 'action_reward');
        $this->assertSame([1, 10, 0, 3000000], [$got['status'], $got['own_chip'], $got['chip_status'], $got['silver']]);
        $this->assertSame($leap + 100, FreeLeap::get($uid));
        $this->assertCount(3, $got['reward_info']);
        $this->assertSame(-4, $this->request($p['auth'], 'act_thirty', 'action_reward')['status']);
        $this->assertSame(2, $this->request($p['auth'], 'act_thirty', 'index')['is_open']);
        // 魂魄够 70 领武将（不扣魂魄），只能领一次
        $this->assertSame(-3, $this->request($p['auth'], 'act_thirty', 'action_chip_reward')['status']);
        DB::table('thirty')->where('uid', $uid)->update(['own_chip' => 70]);
        $this->assertSame(1, $this->request($p['auth'], 'act_thirty', 'index')['chip_status']);
        $chip = $this->request($p['auth'], 'act_thirty', 'action_chip_reward');
        $this->assertSame([1, 15, 151], [$chip['status'], $chip['reward_info']['type'], $chip['reward_info']['value']]);
        $this->assertTrue(General::ownsTemplate($uid, 151));
        $this->assertSame(70, (int)DB::table('thirty')->where('uid', $uid)->value('own_chip'));
        $this->assertSame(-4, $this->request($p['auth'], 'act_thirty', 'action_chip_reward')['status']);
        $this->assertSame(2, $this->request($p['auth'], 'act_thirty', 'index')['chip_status']);

        // 每天 0 点：昨天充值达标没领的补发（记在前一天）
        $q = $this->player(['silver' => 0]);
        DB::table('thirty')->insert(['uid' => $q['uid'], 'version' => $version, 'own_chip' => 0, 'dateline' => 1]);
        DB::table('thirty_order')->insert(['uid' => $q['uid'], 'level' => 1, 'gold' => 300, 'version' => $version, 'dateline' => self::today()]);
        $tomorrow = (int)strtotime(date('Y-m-d', self::today() + 86400));
        $this->assertSame(1, ActThirtyHandler::cronDailyReward($tomorrow + 10));
        $this->assertSame([3000000, 10], [self::memberValue($q['uid'], 'silver'), (int)DB::table('thirty')->where('uid', $q['uid'])->value('own_chip')]);
        $this->assertSame(1, DB::table('thirty_log')->where('uid', $q['uid'])->where('day', 1)->count());
        $this->assertSame(1, self::messages($q['uid'], '您今天未领取的充值返豪礼奖励已自动帮您领取'));
        ActThirtyHandler::cronDailyReward($tomorrow + 20);
        $this->assertSame(3000000, self::memberValue($q['uid'], 'silver'));
        // 结束后：魂魄不够的每个 5 紫宝石，清零
        $end = (int)$act['endtime'];
        $purple = TraderLog::jade($q['uid'], 3);
        $this->assertSame(-3, ActThirtyHandler::cronChipReward($end - 1));
        $this->assertSame(1, ActThirtyHandler::cronChipReward($end + 1));
        $this->assertSame([$purple + 50, 0], [TraderLog::jade($q['uid'], 3), (int)DB::table('thirty')->where('uid', $q['uid'])->value('own_chip')]);
        $this->assertSame(1, self::messages($q['uid'], '兑换紫宝石50个，请查收!'));
        // 已领过武将的玩家：魂魄清零、不再补发
        $this->assertSame(0, (int)DB::table('thirty')->where('uid', $uid)->value('own_chip'));
        $this->assertSame(0, self::messages($uid, '充值送豪礼活动已结束'));
    }

    // ---------------------------------------------------------------------------------------------
    // 绝版神将 / 限时打折神将 / 限量神将
    // ---------------------------------------------------------------------------------------------

    public function testGeneralOnlyOnsaleAndLimitedshop(): void
    {
        $p = $this->player(['reputation' => 9000000]);
        $uid = $p['uid'];
        BossClock::freeze(self::today());
        $this->assertSame(-1, $this->request($p['auth'], 'general_only', 'index')['status']);
        self::open('general_only,general_onsale,limitedshop');
        $only = $this->request($p['auth'], 'general_only', 'index');
        $this->assertSame([1, 0, 9000000, 1], [$only['status'], $only['jade'], $only['reputation'], $only['type']]);
        $this->assertSame([180, 181], array_column($only['list'], 'generalid'));
        $this->assertSame([0, 0], array_column($only['list'], 'isget'));
        $this->assertSame(['image', 'name', 'type', 'wuli', 'zhili', 'tili', 'star', 'skill', 'talent'], array_keys($only['list'][0]['info']));
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'general_only', 'buy'));
        $this->assertSame(-2, $this->request($p['auth'], 'general_only', 'buy', ['generalid' => 1])['status']);
        $this->assertSame(3, $this->request($p['auth'], 'general_only', 'buy', ['generalid' => 180])['status']);
        TraderLog::addJade($uid, 3, 80000);
        $buy = $this->request($p['auth'], 'general_only', 'buy', ['generalid' => 180]);
        $this->assertSame([1, 3000, 0, 180], [$buy['status'], $buy['jade'], $buy['reputation'], $buy['general']['generalid']]);
        $this->assertArrayNotHasKey('status', $buy['general']);
        $this->assertSame(2, $this->request($p['auth'], 'general_only', 'buy', ['generalid' => 180])['status']);
        $this->assertSame(3, $this->request($p['auth'], 'general_only', 'buy', ['generalid' => 181])['status']);
        TraderLog::addJade($uid, 3, 80000);
        $this->assertSame(4, $this->request($p['auth'], 'general_only', 'buy', ['generalid' => 181])['status']);
        TraderLog::subJade($uid, 3, 80000);
        $this->assertSame(1, (int)DB::table('pub_log')->where('uid', $uid)->where('generalid', 180)->value('own'));

        // 限时打折（活动版本 9）
        $sale = $this->request($p['auth'], 'general_onsale', 'index');
        $this->assertSame([1, 9], [$sale['status'], $sale['version']]);
        $this->assertSame([68, 84, 85], array_column($sale['list'], 'generalid'));
        $this->assertSame([0, 0, 0], array_column($sale['list'], 'isown'));
        $this->assertSame(-2, $this->request($p['auth'], 'general_onsale', 'buy', ['generalid' => 33])['status']);
        $this->assertSame(3, $this->request($p['auth'], 'general_onsale', 'buy', ['generalid' => 68])['status']);
        TraderLog::addJade($uid, 2, 2000);
        $b = $this->request($p['auth'], 'general_onsale', 'buy', ['generalid' => 68]);
        $this->assertSame([1, 1520, 2, 480], [$b['status'], $b['price'], $b['type'], $b['jade']]);
        $this->assertSame(2, $this->request($p['auth'], 'general_onsale', 'buy', ['generalid' => 68])['status']);
        $this->assertSame(480, TraderLog::jade($uid, 2));
        $this->assertSame(1, $this->request($p['auth'], 'general_onsale', 'index')['list'][0]['isown']);

        // 限量神将：uc 没有商品时回 -2
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'limitedshop', 'index'));
        DB::table('uc_limitedshop')->insert(['id' => 1, 'generalid' => 84, 'type' => 3, 'cost' => 3000, 'reputation' => 100, 'now' => 1000, 'now_reputation' => 50, 'num' => 2, 'sale' => 0]);
        $ls = $this->request($p['auth'], 'limitedshop', 'index');
        $this->assertSame([1, 2, 0], [$ls['status'], $ls['list'][0]['num'], $ls['list'][0]['isown']]);
        $this->assertSame(['type', 'name', 'image', 'star', 'wuli', 'zhili', 'tili', 'skill', 'talent'], array_keys($ls['list'][0]['info']));
        $this->assertSame(-2, $this->request($p['auth'], 'limitedshop', 'buy_general', ['id' => 2])['status']);
        DB::table('member')->where('uid', $uid)->update(['reputation' => 10]);
        $this->assertSame(5, $this->request($p['auth'], 'limitedshop', 'buy_general', ['id' => 1])['status']);
        DB::table('member')->where('uid', $uid)->update(['reputation' => 100]);
        $lb = $this->request($p['auth'], 'limitedshop', 'buy_general', ['id' => 1]);
        $this->assertSame([1, [3 => 2000], 50, 1, 84], [$lb['status'], $lb['jade'], $lb['reputation'], $lb['num'], $lb['general']['generalid']]);
        $this->assertSame(1, DB::table('tavern_action_log')->where('uid', $uid)->count());
        $this->assertSame(2, $this->request($p['auth'], 'limitedshop', 'buy_general', ['id' => 1])['status']);
        // 另一个玩家买走最后一份后售完
        $q = $this->player(['reputation' => 100]);
        TraderLog::addJade($q['uid'], 3, 5000);
        $this->assertSame(1, $this->request($q['auth'], 'limitedshop', 'buy_general', ['id' => 1])['status']);
        $r = $this->player(['reputation' => 100]);
        TraderLog::addJade($r['uid'], 3, 5000);
        $this->assertSame(4, $this->request($r['auth'], 'limitedshop', 'buy_general', ['id' => 1])['status']);
        $this->assertSame(5000, TraderLog::jade($r['uid'], 3));
    }

    // ---------------------------------------------------------------------------------------------
    // 充值记账与计划任务汇总
    // ---------------------------------------------------------------------------------------------

    public function testPayHooksAreGatedByActivityWindowsAndCronSummary(): void
    {
        $p = $this->player();
        $uid = $p['uid'];
        // 活动未开启：不记账
        $this->assertSame(1, Pay::grantManual($uid, 2, self::today())['status']);
        foreach (['worldcup_order', 'thirty_order', 'act_paychecks_order', 'actenrichment_order'] as $table) {
            $this->assertSame(0, DB::table($table)->where('uid', $uid)->count(), $table);
        }
        self::open('worldcup,actthirty,paychecks,actenrichment');
        $this->assertSame(1, Pay::grantManual($uid, 2, self::today())['status']);
        foreach (['worldcup_order', 'thirty_order', 'act_paychecks_order', 'actenrichment_order'] as $table) {
            $this->assertSame(1, DB::table($table)->where('uid', $uid)->count(), $table);
        }
        // 世界杯结束前一天、貔貅聚财结束前 7 天停止记账
        $wc = (int)self::act('worldcup')['endtime'];
        $this->assertSame(1, Pay::grantManual($uid, 2, $wc - 86400)['status']);
        $this->assertSame(1, DB::table('worldcup_order')->where('uid', $uid)->count());
        $this->assertSame(1, DB::table('actenrichment_order')->where('uid', $uid)->count());
        $this->assertSame(2, DB::table('thirty_order')->where('uid', $uid)->count());

        $res = Fest3Cron::run(self::today());
        $this->assertSame(['treasure', 'new_pay_logined', 'index', 'action_paychecks', 'action_paychecks_reward', 'get_actenrichment_reward', 'sevenstar_task'], array_keys($res));
        $this->assertSame(-2, $res['action_paychecks_reward']);
        $this->assertSame(-2, $res['get_actenrichment_reward']);
    }
}
