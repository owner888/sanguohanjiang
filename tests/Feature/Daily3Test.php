<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\Cron\ExerciseCron;
use Hanjiang\Cron\FlopCron;
use Hanjiang\MasterData\ExerciseAddition;
use Hanjiang\MasterData\Flop as FlopRule;
use Hanjiang\MasterData\LevelgiftReward;
use Hanjiang\MasterData\Price;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Exercise;
use Hanjiang\Model\Flop;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\MainQuestTask;
use Hanjiang\Model\Member;
use Hanjiang\Model\Sacredtree;
use Hanjiang\Model\TraderLog;
use Hanjiang\Tests\Support\Gateway;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use PHPUnit\Framework\TestCase;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * 每日玩法第三批：主城征收（c=city）、节日签到（c=dailysign）、累计登录（c=totallogin）、登录返礼（c=loginreward）、
 * 连续登录（c=logined，主城连续登录天数 / 主线任务 20）、等级礼包（c=levelgift）、激活码（c=activationcode）、邀请（c=invitation）、
 * 好友（c=friend）、整军演武（c=exercise）、神树（c=sacredtree，含战斗血量加成）、四方来朝（c=flop）、主城建筑首次打开记录
 *
 * 旧版数据中这些运营活动都已结束：exercise / flop / sacredtree 结束于 2016-12-31，dailysign / total_login / login_reward 的
 * starttime = endtime = 0。测试用 BossClock 固定到活动期间（1480000000）或时间 0。
 */
final class Daily3Test extends TestCase
{
    /** 2016-11-24，exercise / flop / sacredtree 活动期间 */
    private const int OPEN_TS = 1480000000;

    protected function tearDown(): void
    {
        BossClock::freeze(null);
        mt_srand();
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
        $member += ['nickname' => 'd3_' . $uid];
        DB::table('member')->where('uid', $uid)->update($member);
        Redis::del('player:' . $uid);
        return ['uid' => $uid, 'auth' => ['token' => (string)$login['token'], 'uid' => $uid]];
    }

    /**
     * 第一次 mt_rand(1, 100) 落在 70 以内（征收 / 强征不掉藏宝图）的种子
     */
    private static function noMapSeed(): int
    {
        for ($s = 1; ; $s++) {
            mt_srand($s);
            if (mt_rand(1, 100) <= 70) {
                return $s;
            }
        }
    }

    private static function memberValue(int $uid, string $column): int
    {
        return (int)DB::table('member')->where('uid', $uid)->value($column);
    }

    private static function fieldValue(int $uid, string $column): int
    {
        return (int)DB::table('member_field')->where('uid', $uid)->value($column);
    }

    private static function activityValue(int $uid, string $column): int
    {
        return (int)DB::table('member_activity')->where('uid', $uid)->value($column);
    }

    // ---------------------------------------------------------------------------------------------
    // 主城征收
    // ---------------------------------------------------------------------------------------------

    public function testCityIndexImposeImpressAndClearCd(): void
    {
        $p = $this->player(['level' => 20, 'silver' => 0, 'gold' => 1000]);
        $uid = $p['uid'];
        $this->assertSame(['status' => 1, 'times' => 50, 'gold' => Price::ROWS[1]['levy'], 'cd' => ['status' => 0, 'cd' => 0]],
            $this->request($p['auth'], 'city', 'index'));

        // 征收：银币 = 等级 × 500，剩余次数 - 1，总征收次数 + 1；演武活动未开启，加成 0
        // 藏宝图掉落（30%）用种子固定为不掉（藏宝图见 Daily5Test）
        mt_srand(self::noMapSeed());
        $impose = $this->request($p['auth'], 'city', 'impose');
        $this->assertSame(['status' => 1, 'silver' => 10000, 'levytimes' => 49, 'cd' => 0, 'addition' => 0, 'all_silver' => 10000, 'gift' => 0, 'treasuremap' => []], $impose);
        $this->assertSame([49, 1], [self::fieldValue($uid, 'levytimes'), self::fieldValue($uid, 'levyall')]);
        // VIP 0 的银币增加旧版不记流水（只有消耗按 VIP 记），这里 add_silver 记 silver_log type 1
        $this->assertSame(1, DB::table('silver_log')->where('uid', $uid)->where('type', 1)->where('class', 'city')->count());

        // 次数用完
        DB::table('member_field')->where('uid', $uid)->update(['levytimes' => 0]);
        $this->assertSame(['status' => -1, 'silver' => 0, 'levytimes' => 0, 'cd' => 0], $this->request($p['auth'], 'city', 'impose'));

        // 强征：按 Price.levy 扣元宝，下次价格为第 2 次
        $stat = static fn (): array => array_map('intval', DB::table('stat_pay')->select('times', 'gold')->where('name', 'qiangzheng')->first() ?? ['times' => 0, 'gold' => 0]);
        $statBefore = $stat();
        mt_srand(self::noMapSeed());
        $impress = $this->request($p['auth'], 'city', 'impress');
        $this->assertSame(1, $impress['status']);
        // 消费统计：旧版记的是下一次强征的价格
        $this->assertSame(['times' => $statBefore['times'] + 1, 'gold' => $statBefore['gold'] + Price::ROWS[2]['levy']], $stat());
        $this->assertSame([10000, Price::ROWS[2]['levy'], 1000 - Price::ROWS[1]['levy'], 20000], [$impress['silver'], $impress['gold'], $impress['all_gold'], $impress['all_silver']]);
        $this->assertSame(1, self::fieldValue($uid, 'imposetimes'));
        $this->assertSame(1000 - Price::ROWS[1]['levy'], self::memberValue($uid, 'gold'));
        // 第 50 次起 50 元宝
        DB::table('member_field')->where('uid', $uid)->update(['imposetimes' => 60]);
        $this->assertSame(50, $this->request($p['auth'], 'city', 'index')['gold']);
        DB::table('member')->where('uid', $uid)->update(['gold' => 10]);
        $this->assertSame(['status' => -1, 'silver' => 0, 'gold' => 0], $this->request($p['auth'], 'city', 'impress'));

        // 清除征收 cd：没有 cd -1；有 cd 时 type 0 只回价格，type 1 按每 10 分钟 1 元宝扣
        $this->assertSame(['gold' => 0, 'status' => -1], $this->request($p['auth'], 'city', 'clear_cd', ['type' => 1]));
        DB::table('member_field')->where('uid', $uid)->update(['levydateline' => time() + 1100, 'levystatus' => 1]);
        $this->assertSame(['gold' => 2, 'status' => 0], $this->request($p['auth'], 'city', 'clear_cd', ['type' => 0]));
        $clear = $this->request($p['auth'], 'city', 'clear_cd', ['type' => 1]);
        $this->assertSame([1, 2, 8], [$clear['status'], $clear['gold'], $clear['all_gold']]);
        $this->assertSame(0, self::fieldValue($uid, 'levystatus'));
        $this->assertLessThanOrEqual(time(), self::fieldValue($uid, 'levydateline'));
        DB::table('member_field')->where('uid', $uid)->update(['levydateline' => time() + 6000]);
        $this->assertSame(['gold' => 10, 'status' => -2], $this->request($p['auth'], 'city', 'clear_cd', ['type' => 1]));
    }

    public function testCityTaskHooks(): void
    {
        // 41 级以上征收 / 强征计日常任务 2（进行中的任务是征收时进度 + 1）
        $p = $this->player(['level' => 45, 'gold' => 1000]);
        $uid = $p['uid'];
        \Hanjiang\Model\DailyTask::startPlan($uid, ['id' => 1, 'type' => 2, 'value' => 10], 1, time());
        $this->request($p['auth'], 'city', 'impose');
        $this->request($p['auth'], 'city', 'impress');
        $this->assertSame(2, \Hanjiang\Model\DailyTask::plan($uid, time())['plan']);
    }

    public function testLevyCronGivesTwelvePerDayCappedAtFifty(): void
    {
        $p = $this->player();
        $uid = $p['uid'];
        // 先执行过每日计划任务（member_field.dateline 不为 0）
        $this->request($p['auth'], 'city', 'index');
        $now = time();
        // 两个「7 点日」前执行过：每天 12 次 + 剩余 10 次
        DB::table('member')->where('uid', $uid)->update(['crontime' => $now - 86400 * 2]);
        DB::table('member_field')->where('uid', $uid)->update(['levytimes' => 10, 'levyall' => 7, 'imposetimes' => 3]);
        Redis::del('levy_cron:' . $uid);
        $this->request($p['auth'], 'city', 'index');
        $this->assertSame([34, 0, 0], [self::fieldValue($uid, 'levytimes'), self::fieldValue($uid, 'levyall'), self::fieldValue($uid, 'imposetimes')]);
        $this->assertSame($now, self::memberValue($uid, 'crontime'), '', 2);
        // 同一个 7 点日不再执行
        $this->assertFalse(Member::levyCron($uid, $now, 1));
        // 上限 50；从未执行过每日计划任务（dateline 0）时直接 12 次
        DB::table('member')->where('uid', $uid)->update(['crontime' => $now - 86400 * 9]);
        $this->assertTrue(Member::levyCron($uid, $now, 1));
        $this->assertSame(50, self::fieldValue($uid, 'levytimes'));
        DB::table('member')->where('uid', $uid)->update(['crontime' => $now - 86400 * 9]);
        $this->assertTrue(Member::levyCron($uid, $now, 0));
        $this->assertSame(12, self::fieldValue($uid, 'levytimes'));
    }

    // ---------------------------------------------------------------------------------------------
    // 整军演武
    // ---------------------------------------------------------------------------------------------

    public function testExerciseRankGiftAndOpenGift(): void
    {
        $p = $this->player(['level' => 30, 'silver' => 0, 'act' => 0]);
        $uid = $p['uid'];
        // 活动结束：-2
        $this->assertSame(['status' => -2, 'info' => []], $this->request($p['auth'], 'exercise', 'index'));
        $this->assertSame(['status' => -2, 'gift' => 0], $this->request($p['auth'], 'exercise', 'open_gift'));

        BossClock::freeze(self::OPEN_TS);
        Exercise::clearRank();
        // 已征收 4 次：第 5 次得 5 个礼包；排行榜第 1 名加成 ExerciseAddition 第 1 行（本次征收前榜为空，按 100%）
        DB::table('member_field')->where('uid', $uid)->update(['levyall' => 4]);
        $impose = $this->request($p['auth'], 'city', 'impose');
        $this->assertSame([1, 15000, 100, 0, 5], [$impose['status'], $impose['silver'], $impose['addition'], $impose['exercisetimes'], $impose['gift']]);
        $this->assertSame([5, 30], [self::activityValue($uid, 'exercise_gift'), self::activityValue($uid, 'exercise_level')]);
        $this->assertSame(1, DB::table('city_action_log')->where('uid', $uid)->where('gold', 0)->count());
        $this->assertSame(5, Exercise::levytimes($uid));
        // 0.5 秒内再征收：-3
        $this->assertSame(-3, $this->request($p['auth'], 'city', 'impose')['status']);
        // 在榜第 1：强征按 ExerciseAddition 第 1 行加成
        DB::table('member')->where('uid', $uid)->update(['gold' => 1000]);
        $impress = $this->request($p['auth'], 'city', 'impress');
        $addition = ExerciseAddition::ROWS[1]['addition'];
        $this->assertSame([$addition, (int)(30 * 500 * $addition / 100)], [$impress['addition'], $impress['silver']]);
        $this->assertSame(Price::ROWS[1]['levy'], (int)DB::table('city_action_log')->where('uid', $uid)->orderBy('id', 'DESC')->value('gold'));

        $index = $this->request($p['auth'], 'exercise', 'index');
        $this->assertSame(1, $index['status']);
        $this->assertSame([6, 6, 5, 1483113600 - self::OPEN_TS], [$index['info']['times'], $index['info']['alltimes'], $index['info']['gift'], $index['info']['cd']]);
        $this->assertSame(['uid' => $uid, 'nickname' => 'd3_' . $uid, 'times' => 6, 'addition' => $addition, 'sort' => 1], $index['info']['list'][0]);

        // 开礼包：银币 = 100 × 等级 × 倍数，军令 = 1 × 倍数
        $silver = self::memberValue($uid, 'silver');
        $act = self::memberValue($uid, 'act');
        $open = $this->request($p['auth'], 'exercise', 'open_gift');
        $this->assertSame([1, 4], [$open['status'], $open['info']['gift']]);
        $this->assertContains($open['info']['hit'], [1, 2, 4, 8]);
        if ($open['info']['type'] === 1) {
            $this->assertSame(100 * 30 * $open['info']['hit'], $open['info']['num']);
            $this->assertSame($silver + $open['info']['num'], $open['all_silver']);
        } else {
            $this->assertSame($open['info']['hit'], $open['info']['num']);
            $this->assertSame($act + $open['info']['num'], $open['all_act']);
        }
        DB::table('member_activity')->where('uid', $uid)->update(['exercise_gift' => 0]);
        $this->assertSame(['status' => -4, 'gift' => 0], $this->request($p['auth'], 'exercise', 'open_gift'));
        Exercise::clearRank();
    }

    public function testExerciseCronSaleGift(): void
    {
        $p = $this->player(['silver' => 0, 'act' => 0]);
        $uid = $p['uid'];
        $this->request($p['auth'], 'drink', 'index');
        DB::table('member_activity')->where('uid', $uid)->update(['exercise_gift' => 5, 'exercise_level' => 30]);
        $this->assertSame(-2, ExerciseCron::saleGift(1483113600 - 10));
        $this->assertSame(-3, ExerciseCron::saleGift(1483113600 + 86400 * 4));
        // 5 个礼包：银币礼包 intval(5 × 50%) = 2、军令礼包 2；银币 = 100 × 30 × 2，军令 = 1 × 2
        $this->assertSame(1, ExerciseCron::saleGift(1483113600 + 100));
        $this->assertSame([6000, 2, 0], [self::memberValue($uid, 'silver'), self::memberValue($uid, 'act'), self::activityValue($uid, 'exercise_gift')]);
        $this->assertStringContainsString('剩余的5个礼包', (string)DB::table('pm')->where('touid', $uid)->orderBy('id', 'DESC')->value('message'));
        // 再执行不重复发放
        ExerciseCron::saleGift(1483113600 + 200);
        $this->assertSame(6000, self::memberValue($uid, 'silver'));
    }

    // ---------------------------------------------------------------------------------------------
    // 节日签到 / 累计登录 / 登录返礼（旧版数据活动时间都是 0，固定时间 0 测试开启时的流程）
    // ---------------------------------------------------------------------------------------------

    public function testDailySign(): void
    {
        $p = $this->player(['level' => 10, 'silver' => 0, 'act' => 0]);
        $uid = $p['uid'];
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'dailysign', 'index'));
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'dailysign', 'sign'));

        BossClock::freeze(0);
        $index = $this->request($p['auth'], 'dailysign', 'index');
        $this->assertSame(['status' => 1, 'list' => [['gold' => 0, 'silver' => 10000, 'act' => 5, 'get' => 0, 'open' => 1]], 'date' => '9.30-10.7'], $index);
        $sign = $this->request($p['auth'], 'dailysign', 'sign');
        $this->assertSame(['status' => 1, 'add_gold' => 0, 'add_silver' => 10000, 'add_act' => 5, 'all_silver' => 10000, 'all_gold' => self::memberValue($uid, 'gold'), 'all_act' => 5], $sign);
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'dailysign', 'sign'));
        $this->assertSame(1, $this->request($p['auth'], 'dailysign', 'index')['list'][0]['get']);
        $this->assertSame(1, DB::table('act_log')->where('uid', $uid)->where('type', 1)->where('act', 5)->count());
    }

    public function testTotalLogin(): void
    {
        $p = $this->player();
        $uid = $p['uid'];
        $this->assertSame(['status' => -1, 'msg' => '活动未开启！'], $this->request($p['auth'], 'totallogin', 'index'));

        BossClock::freeze(0);
        // 主城累计登录：活动开启时建第 1 天
        $this->request($p['auth'], 'member', 'index');
        $this->assertSame(1, (int)DB::table('totallogin')->where('uid', $uid)->value('day'));
        $index = $this->request($p['auth'], 'totallogin', 'index');
        // 奖励武将 1（廖化）是新账号的初始武将：已拥有，按已领取
        $this->assertSame([1, 1, 2, 1], [$index['status'], $index['day'], $index['is_get'], $index['total_login_bg']]);
        $this->assertSame('廖化', $index['list']['info']['name']);
        $this->assertSame(['status' => -3, 'msg' => '累计登陆天数不足！'], $this->request($p['auth'], 'totallogin', 'action'));
        DB::table('totallogin')->where('uid', $uid)->update(['day' => 15]);
        $this->assertSame(-6, $this->request($p['auth'], 'totallogin', 'action')['status']);
        // 没有该武将时领取
        DB::table('general')->where('uid', $uid)->where('generalid', 1)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $action = $this->request($p['auth'], 'totallogin', 'action');
        $this->assertSame([1, 2, 1], [$action['status'], $action['is_get'], $action['general']['generalid']]);
        $this->assertSame(1, DB::table('pub_log')->where('uid', $uid)->where('generalid', 1)->where('type', 102)->count());
        $this->assertSame(1, DB::table('totallogin_reward_log')->where('uid', $uid)->count());
        $this->assertSame(-6, $this->request($p['auth'], 'totallogin', 'action')['status']);
    }

    public function testLoginReward(): void
    {
        $p = $this->player(['silver' => 0]);
        $uid = $p['uid'];
        $this->assertSame(['status' => -1, 'msg' => '活动未开启！'], $this->request($p['auth'], 'loginreward', 'index'));

        BossClock::freeze(0);
        // 注册晚于活动开始、没有更早的登录日志：0 天
        $this->assertSame(0, $this->request($p['auth'], 'loginreward', 'index')['day']);
        $this->assertSame(['status' => -4, 'msg' => '登陆天数不足'], $this->request($p['auth'], 'loginreward', 'action_reward', ['type' => 1, 'reward_id' => 1]));
        DB::table('member')->where('uid', $uid)->update(['jointime' => 0]);
        $index = $this->request($p['auth'], 'loginreward', 'index');
        $this->assertSame([1, 1, '1970-01-01'], [$index['status'], $index['day'], $index['join_time']]);
        $this->assertSame([100000, 3, 1], [$index['reward_index']['num1'], $index['reward_index']['num2'], $index['reward_index']['is_re']]);
        $this->assertSame([1, 0], [$index['reward'][0]['is_re'], $index['reward'][1]['is_re']]);

        // 总奖励（type 1）：银币 100000 × 天数、突飞卡 3、蓝 / 黄 / 紫宝石
        $leap = FreeLeap::get($uid);
        $get = $this->request($p['auth'], 'loginreward', 'action_reward', ['type' => 1, 'reward_id' => 1]);
        $this->assertSame([1, 100000], [$get['status'], $get['silver']]);
        $this->assertSame($leap + 3, FreeLeap::get($uid));
        $this->assertSame([10, 5, 2], [TraderLog::jade($uid, 1), TraderLog::jade($uid, 2), TraderLog::jade($uid, 3)]);
        $this->assertSame(-6, $this->request($p['auth'], 'loginreward', 'action_reward', ['type' => 1, 'reward_id' => 1])['status']);
        $this->assertSame(-2, $this->request($p['auth'], 'loginreward', 'action_reward', ['type' => 3, 'reward_id' => 1])['status']);
        $this->assertSame(-3, $this->request($p['auth'], 'loginreward', 'action_reward', ['type' => 2, 'reward_id' => 99])['status']);

        // 达成天数奖励：活动开始以前的 14 个不同日期 + 1 = 15 天
        for ($i = 1; $i <= 14; $i++) {
            DB::table('member_log')->insert(['ymd' => 700100 + $i, 'uid' => $uid, 'dateline' => 0]);
        }
        $get = $this->request($p['auth'], 'loginreward', 'action_reward', ['type' => 2, 'reward_id' => 2]);
        $this->assertSame(1, $get['status']);
        $this->assertSame(5 + 200, TraderLog::jade($uid, 2));
        $this->assertSame(2, $this->request($p['auth'], 'loginreward', 'index')['reward'][1]['is_re']);
        $this->assertSame(-4, $this->request($p['auth'], 'loginreward', 'action_reward', ['type' => 2, 'reward_id' => 3])['status']);
    }

    // ---------------------------------------------------------------------------------------------
    // 连续登录
    // ---------------------------------------------------------------------------------------------

    public function testLoginedDaysAndRewards(): void
    {
        $p = $this->player(['silver' => 0, 'act' => 0]);
        $uid = $p['uid'];
        $index = $this->request($p['auth'], 'logined', 'index');
        // 每日计划任务（第一次带 token 的请求）建了第 1 天
        $this->assertSame(1, (int)DB::table('logined_log')->where('uid', $uid)->value('day'));
        $this->assertSame([1, 1, 1, 15], [$index['status'], $index['day'], $index['popo'], count($index['list'])]);
        $this->assertSame([1, 2, 2], [$index['list'][0]['type'], $index['list'][1]['type'], $index['list'][14]['type']]);
        $this->assertSame(1, $this->request($p['auth'], 'member', 'index')['logined_popo']);

        $this->assertSame(['status' => -1, 'popo' => 1], $this->request($p['auth'], 'logined', 'get_reward', ['id' => 16]));
        $this->assertSame(['status' => -2, 'popo' => 1], $this->request($p['auth'], 'logined', 'get_reward', ['id' => 2]));
        $get = $this->request($p['auth'], 'logined', 'get_reward', ['id' => 1]);
        $this->assertSame([1, 0, 1, 100000, 50], [$get['status'], $get['popo'], $get['day'], $get['all_silver'], $get['all_act']]);
        $this->assertSame(3, $get['list'][0]['type']);
        $this->assertSame(['status' => -2, 'popo' => 1], $this->request($p['auth'], 'logined', 'get_reward', ['id' => 1]));

        // 第二天连续登录：第 2 天变为可领取
        DB::table('logined_log')->where('uid', $uid)->update(['dateline' => time() - 86400]);
        Member::dailyCron($uid, time());
        \Hanjiang\Model\Logined::updateDay($uid, time());
        $this->assertSame(2, (int)DB::table('logined_log')->where('uid', $uid)->value('day'));
        $index = $this->request($p['auth'], 'logined', 'index');
        $this->assertSame([2, 3, 1, 2], [$index['day'], $index['list'][0]['type'], $index['list'][1]['type'], $index['list'][2]['type']]);
        $this->assertSame(1, $this->request($p['auth'], 'logined', 'get_reward', ['id' => 2])['status']);
        $this->assertSame(220000, self::memberValue($uid, 'silver'));
        // 每日活动的连续登录气泡：今天已领奖、没有可领取的
        $this->assertSame(0, $this->request($p['auth'], 'activity', 'index')['list']['logined']['popo']);
    }

    public function testContinuousLoginDayAndMainquest(): void
    {
        $p = $this->player();
        $uid = $p['uid'];
        // 注册当天不计（旧版注册时写 login_dateline）
        $this->request($p['auth'], 'member', 'index');
        $this->assertSame(0, self::fieldValue($uid, 'login_day'));
        // 昨天登录过：连续 + 1
        DB::table('member_field')->where('uid', $uid)->update(['login_day' => 3, 'login_dateline' => time() - 86400]);
        $this->request($p['auth'], 'member', 'index');
        $this->assertSame(4, self::fieldValue($uid, 'login_day'));
        $this->assertSame(4, MainQuestTask::taskInfo($uid, 20, 5));
        // 断了一天：重置为 1
        DB::table('member_field')->where('uid', $uid)->update(['login_dateline' => time() - 86400 * 3]);
        $this->request($p['auth'], 'member', 'index');
        $this->assertSame(1, self::fieldValue($uid, 'login_day'));
        // 每日计划任务日志
        $this->assertSame(1, DB::table('member_log')->where('uid', $uid)->where('ymd', (int)date('ymd'))->count());
    }

    // ---------------------------------------------------------------------------------------------
    // 等级礼包
    // ---------------------------------------------------------------------------------------------

    public function testLevelgift(): void
    {
        $p = $this->player(['level' => 15, 'silver' => 0, 'gold' => 0]);
        $uid = $p['uid'];
        $index = $this->request($p['auth'], 'levelgift', 'index');
        $this->assertSame([1, 0, 1, count(LevelgiftReward::ROWS)], [$index['status'], $index['popo'], $index['lost'], count($index['list'])]);
        $this->assertSame(2, $index['list'][0]['type']);
        // 30 级礼包：武将 13 与 3 件装备的展示信息
        $this->assertSame([38, 39, 40], [$index['list'][1]['information'][2]['id'], $index['list'][1]['information'][3]['id'], $index['list'][1]['information'][4]['id']]);
        $this->assertSame(\Hanjiang\MasterData\General::ROWS[13]['image'], $index['list'][1]['information'][1]['id']);
        $this->assertSame(0, $index['list'][1]['renew']);
        $this->assertSame(['status' => -1, 'popo' => 1, 'lost' => 1], $this->request($p['auth'], 'levelgift', 'get_reward', ['level' => 16]));

        // 升到 16 级：主城礼包标识 2（可领取）
        Member::addExp($uid, Member::getLevelUpExp(15));
        $this->assertSame(2, self::fieldValue($uid, 'levelgift'));
        $member = $this->request($p['auth'], 'member', 'index');
        $this->assertSame([1, 1], [$member['levelgift'], $member['levelgift_popo']]);
        $this->assertSame(1, $this->request($p['auth'], 'levelgift', 'index')['popo']);

        // 16 级：元宝 50000、银币 300000、突飞卡 50
        $leap = FreeLeap::get($uid);
        $get = $this->request($p['auth'], 'levelgift', 'get_reward', ['level' => 16]);
        $this->assertSame([1, 0, 1, 300000, 50000], [$get['status'], $get['popo'], $get['lost'], $get['all_silver'], $get['all_gold']]);
        $this->assertSame(3, $get['list'][0]['type']);
        $this->assertSame($leap + 50, FreeLeap::get($uid));
        $this->assertSame(1, self::fieldValue($uid, 'levelgift'));
        $this->assertSame(3, (int)DB::table('levelgift_log')->where('uid', $uid)->where('level', 16)->value('status'));
        $this->assertSame(['status' => -2, 'popo' => 1, 'lost' => 1], $this->request($p['auth'], 'levelgift', 'get_reward', ['level' => 16]));

        // 30 级：武将 13 + 3 件装备
        DB::table('member')->where('uid', $uid)->update(['level' => 30]);
        $this->assertSame(1, $this->request($p['auth'], 'levelgift', 'get_reward', ['level' => 30])['status']);
        $this->assertNotNull(DB::table('general')->where('uid', $uid)->where('generalid', 13)->whereNull('deleted_at')->value('id'));
        $this->assertSame(3, DB::table('equipment')->where('uid', $uid)->whereIn('equipid', [38, 39, 40])->count());
        $this->assertSame(1, DB::table('pub_log')->where('uid', $uid)->where('generalid', 13)->where('type', 14)->count());
        $this->assertSame(1, $this->request($p['auth'], 'levelgift', 'index')['list'][1]['renew']);
    }

    // ---------------------------------------------------------------------------------------------
    // 激活码
    // ---------------------------------------------------------------------------------------------

    public function testActivationCode(): void
    {
        $p = $this->player(['silver' => 0]);
        $uid = $p['uid'];
        $this->assertSame(['status' => 1, 'open' => 0], $this->request($p['auth'], 'activationcode', 'index'));
        // 四位码 1005（type 999）在 2017-01-01 起 30 天内有效
        $this->assertSame(-9, $this->request($p['auth'], 'activationcode', 'get_reward', ['code' => '1005'])['status']);
        $this->assertSame(-8, $this->request($p['auth'], 'activationcode', 'get_reward', ['code' => '9999'])['status']);
        $this->assertSame(-2, $this->request($p['auth'], 'activationcode', 'get_reward', ['code' => 'ab'])['status']);
        // 长码由 uc 校验：uc 里没有的码为激活码不存在（uc 流程见 Cross1Test）
        $this->assertSame(-3, $this->request($p['auth'], 'activationcode', 'get_reward', ['code' => 'ABCDEFGH12'])['status']);

        BossClock::freeze(1483203600 + 100);
        $get = $this->request($p['auth'], 'activationcode', 'get_reward', ['code' => '1005']);
        $this->assertSame([1, 999, 1000000], [$get['status'], $get['reward']['type'], $get['all_silver']]);
        $this->assertSame('兑换码使用成功，恭喜您获得：1000000银币，100紫宝石， 祝您游戏愉快。', $get['msg']);
        $this->assertArrayNotHasKey('description', $get['reward']);
        $this->assertSame(100, TraderLog::jade($uid, 3));
        $this->assertSame(-1, $this->request($p['auth'], 'activationcode', 'get_reward', ['code' => '1005'])['status']);
        // 24 小时内最多尝试 50 次
        Redis::set('activation_max_' . $uid, 50, 86400);
        $this->assertSame(-7, $this->request($p['auth'], 'activationcode', 'get_reward', ['code' => '1005'])['status']);
    }

    // ---------------------------------------------------------------------------------------------
    // 邀请 / 好友
    // ---------------------------------------------------------------------------------------------

    public function testInvitation(): void
    {
        $p = $this->player(['level' => 49, 'gold' => 0]);
        $uid = $p['uid'];
        $this->assertSame(['status' => -1, 'code' => '', 'number' => 0, 'list' => []], $this->request($p['auth'], 'invitation', 'index'));
        DB::table('member')->where('uid', $uid)->update(['level' => 50]);
        // 第一次打开向 uc 取邀请码（8 位小写字母数字）存到本服（uc 流程见 Cross1Test）
        $index = $this->request($p['auth'], 'invitation', 'index');
        $this->assertSame([1, 0], [$index['status'], $index['number']]);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $index['code']);
        DB::table('invitation_code')->where('uid', $uid)->update(['code' => 'abc123', 'number' => 5]);
        $index = $this->request($p['auth'], 'invitation', 'index');
        $this->assertSame([1, 'abc123', 5], [$index['status'], $index['code'], $index['number']]);
        $this->assertSame([2, 2, 0, 'gold', 'get2'], [$index['list'][0]['completed'], $index['list'][1]['completed'], $index['list'][2]['completed'], $index['list'][0]['reward_type'], $index['list'][2]['reward_type']]);

        $this->assertSame(['status' => 1, 'all_gold' => 100], $this->request($p['auth'], 'invitation', 'get_reward', ['id' => 1]));
        $this->assertSame(3, $this->request($p['auth'], 'invitation', 'get_reward', ['id' => 1])['status']);
        $this->assertSame(2, $this->request($p['auth'], 'invitation', 'get_reward', ['id' => 3])['status']);
        $this->assertSame(4, $this->request($p['auth'], 'invitation', 'get_reward', ['id' => 99])['status']);

        // 兑换别人的邀请码：空码 3；uc 里没有的码 3（记错误次数）；已被邀请 4；错误 50 次 5
        $this->assertSame(['status' => 3, 'all_silver' => 0, 'all_gold' => 0, 'reward' => ['gold' => 100, 'silver' => 500000]], $this->request($p['auth'], 'invitation', 'change', ['code' => '']));
        $this->assertSame(3, $this->request($p['auth'], 'invitation', 'change', ['code' => 'xyz'])['status']);
        $this->assertSame(1, Redis::get('invitation_error_' . $uid));
        DB::table('invitation_code')->where('uid', $uid)->update(['status' => 1, 'number' => 50]);
        $this->assertSame(4, $this->request($p['auth'], 'invitation', 'change', ['code' => 'xyz'])['status']);
        Redis::set('invitation_error_' . $uid, 50, 86400);
        $this->assertSame(5, $this->request($p['auth'], 'invitation', 'change', ['code' => 'xyz'])['status']);

        // 被邀请过且奖励全部领完：关闭邀请入口
        foreach ([2, 3, 4] as $id) {
            $this->assertSame(1, $this->request($p['auth'], 'invitation', 'get_reward', ['id' => $id])['status']);
        }
        $this->assertArrayHasKey('invitation', $this->request($p['auth'], 'activity', 'index')['list']);
        $this->assertSame(2, $this->request($p['auth'], 'invitation', 'index')['status']);
        $this->assertSame(1, self::fieldValue($uid, 'close_invitation'));
        $this->assertArrayNotHasKey('invitation', $this->request($p['auth'], 'activity', 'index')['list']);
    }

    public function testFriendAddListDelete(): void
    {
        $a = $this->player(['level' => 30]);
        $b = $this->player(['level' => 40]);
        $c = $this->player(['level' => 20]);
        $this->assertSame(['status' => -5], $this->request($a['auth'], 'friend', 'add_friend', ['f' => $a['uid']]));
        $this->assertSame(['status' => -4], $this->request($a['auth'], 'friend', 'add_friend', ['f' => 0]));
        $this->assertSame(['status' => -4], $this->request($a['auth'], 'friend', 'add_friend', ['f' => 99999999]));
        $this->assertSame(['status' => 1], $this->request($a['auth'], 'friend', 'add_friend', ['f' => $b['uid']]));
        $this->assertSame(['status' => -2], $this->request($a['auth'], 'friend', 'add_friend', ['f' => $b['uid']]));
        $this->assertSame(['status' => 1], $this->request($a['auth'], 'friend', 'add_friend', ['f' => $c['uid']]));
        $this->assertSame([$b['uid'], $c['uid']], \Hanjiang\Handler\Activity\FriendHandler::friendList($a['uid']));

        // 都不在线：按等级降序
        $index = $this->request($a['auth'], 'friend', 'index');
        $this->assertSame([1, 1, 1, 100, 2, 0], [$index['status'], $index['page'], $index['all_page'], $index['all_number'], $index['now_number'], $index['online']]);
        $this->assertSame(['fuid' => $b['uid'], 'online' => 0, 'nickname' => 'd3_' . $b['uid'], 'level' => 40, 'countryid' => 0, 'country' => '', 'job' => '', 'tree' => 0], $index['list'][0]);
        $this->assertSame($c['uid'], $index['list'][1]['fuid']);
        // 单向好友：b 的列表是空的
        $this->assertSame([], $this->request($b['auth'], 'friend', 'index')['list']);

        $this->assertSame(['status' => -1], $this->request($a['auth'], 'friend', 'del_friend', ['f' => $a['uid']]));
        $del = $this->request($a['auth'], 'friend', 'del_friend', ['f' => $b['uid']]);
        $this->assertSame([1, 1], [$del['status'], $del['now_number']]);
        $this->assertSame([$c['uid']], \Hanjiang\Handler\Activity\FriendHandler::friendList($a['uid']));
        // 删除后再加：改回好友状态
        $this->assertSame(['status' => 1], $this->request($a['auth'], 'friend', 'add_friend', ['f' => $b['uid']]));
        $this->assertSame(1, DB::table('friend')->where('uid', $a['uid'])->where('fuid', $b['uid'])->count());
    }

    // ---------------------------------------------------------------------------------------------
    // 神树
    // ---------------------------------------------------------------------------------------------

    public function testSacredtreeWateringLevelupAndBattleBuff(): void
    {
        $p = $this->player(['level' => 30, 'gold' => 5000, 'silver' => 0]);
        $uid = $p['uid'];
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'sacredtree', 'index'));
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'sacredtree', 'watering'));
        $battle = static function (int $uid): array {
            $team = TeamBuilder::forMember($uid)['team'];
            return (new BattleEngine())->battle($team, [5 => TeamBuilder::unportedUnit(1, '木桩', 1, 0, 0)]);
        };
        $before = $battle($uid);

        BossClock::freeze(self::OPEN_TS);
        $index = $this->request($p['auth'], 'sacredtree', 'index');
        $this->assertSame([1, 0, 0, 800, 1, 1483113600 - self::OPEN_TS, ['1' => 100, '2' => 1000], 0], [$index['status'], $index['exp'], $index['btn_status'], $index['levelup_exp'], $index['time'], $index['cd'], $index['price'], $index['type']]);
        $this->assertSame(['now_tree' => '', 'name' => '', 'buff' => 0, 'tree_id' => 1], $index['tree']);
        $this->assertSame([6, 2, 0, 1, 500], [count($index['list']), $index['list'][0]['id'], $index['list'][0]['status'], $index['list'][0]['reward'][1]['type'], $index['list'][0]['reward'][1]['value']]);

        // 第一次 100 元宝浇水免费。暴击倍数走 mt_rand（8 倍时 800 经验直接升级），固定种子使第一次为 1 倍；tearDown 重置
        mt_srand(1);
        $water = $this->request($p['auth'], 'sacredtree', 'watering', ['type' => 1]);
        $this->assertSame([1, 1, 0, 5000, 100, 0], [$water['status'], $water['multiple'], $water['time'], $water['all_gold'], $water['add_exp'], $water['levelup']]);
        $this->assertSame(0, (int)DB::table('sacredtree_action_log')->where('uid', $uid)->value('gold'));
        // 升级：经验够 800 升 2 级，发奖励（元宝 500、突飞卡 50、银币 500 万）
        DB::table('sacredtree_log')->where('uid', $uid)->update(['exp' => 799]);
        $leap = FreeLeap::get($uid);
        $water = $this->request($p['auth'], 'sacredtree', 'watering', ['type' => 2]);
        $this->assertSame(1, $water['status']);
        $this->assertGreaterThanOrEqual(1, $water['levelup']);
        $this->assertSame(1 + $water['levelup'], $water['tree_id']);
        $this->assertSame(1100 * $water['multiple'], $water['add_exp']);
        $this->assertSame(['type' => 1, 'value' => 500, 'num' => 0, 'name' => '', 'gem_type' => 0, 'gem_quality' => 0, 'itemname' => '', 'itemquality' => 0], $water['reward'][0][1]);
        $this->assertSame(2, $water['reward'][0]['id']);
        $this->assertSame(5000 - 1000 + 500 * ($water['levelup'] >= 1 ? 1 : 0), self::memberValue($uid, 'gold'));
        $this->assertSame($leap + 50 + ($water['levelup'] >= 2 ? 100 : 0), FreeLeap::get($uid));
        $tree = Sacredtree::openTree($uid, self::OPEN_TS);
        $this->assertSame($water['tree_id'], $tree['treeid']);
        // 旧版只在递归层写 buff，库里留下的是本次升的第一级（2 级）的 buff
        $this->assertSame(5, $tree['buff']);

        // 战斗：活动期间单位带神树（buff 加到血量），战斗 buff 的 tree 为神树等级
        DB::table('sacredtree_log')->where('uid', $uid)->update(['treeid' => 3, 'buff' => 10]);
        $team = TeamBuilder::forMember($uid)['team'];
        $this->assertSame(['treeid' => 3, 'buff' => 10], array_values($team)[0]['tree']);
        $after = $battle($uid);
        $this->assertSame([0, 3], [$before['leftbuff']['tree'], $after['leftbuff']['tree']]);
        $hp0 = array_values($before['leftteam'])[0]['allblood'];
        $hp1 = array_values($after['leftteam'])[0]['allblood'];
        $this->assertLessThan(1.5, abs($hp1 - $hp0 * 1.1));
        // 好友列表 / 演武榜显示神树等级
        $f = $this->player();
        $this->request($f['auth'], 'friend', 'add_friend', ['f' => $uid]);
        $this->assertSame(3, $this->request($f['auth'], 'friend', 'index')['list'][0]['tree']);

        // 满级不能再浇水；元宝不足
        DB::table('sacredtree_log')->where('uid', $uid)->update(['treeid' => 7]);
        $this->assertSame(['status' => -5], $this->request($p['auth'], 'sacredtree', 'watering'));
        DB::table('sacredtree_log')->where('uid', $uid)->update(['treeid' => 2]);
        DB::table('member')->where('uid', $uid)->update(['gold' => 50]);
        $this->assertSame(['status' => -4], $this->request($p['auth'], 'sacredtree', 'watering', ['type' => 2]));

        BossClock::freeze(null);
        $this->assertArrayNotHasKey('tree', array_values(TeamBuilder::forMember($uid)['team'])[0]);
    }

    // ---------------------------------------------------------------------------------------------
    // 四方来朝
    // ---------------------------------------------------------------------------------------------

    public function testFlopSpendTimesTurnoverAndCron(): void
    {
        $p = $this->player(['level' => 20, 'gold' => 1000, 'act' => 100, 'silver' => 0]);
        $uid = $p['uid'];
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'flop', 'index'));
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'flop', 'turnover', ['id' => 7]));
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'flop', 'turnover', ['id' => 1]));
        // 活动结束后消耗不计翻牌
        Member::deductCurrency($uid, gold: 100);
        $this->assertSame(0, self::activityValue($uid, 'flopgold'));

        BossClock::freeze(self::OPEN_TS);
        $index = $this->request($p['auth'], 'flop', 'index');
        $this->assertSame(['status' => 1, 'times' => 0, 'info' => ['sivler' => 0, 'gold' => FlopRule::ROWS[1]['gold'], 'leap' => FlopRule::ROWS[1]['leap'], 'act' => FlopRule::ROWS[1]['act']], 'cd' => 1483113600 - self::OPEN_TS], $index);
        $this->assertSame(2, $this->request($p['auth'], 'flop', 'turnover', ['id' => 1])['status']);

        // 元宝：第 1 次 10、第 2 次 30；花 25 换 1 次，余 15
        Member::deductCurrency($uid, gold: 25);
        $this->assertSame([1, 1, 15], [self::activityValue($uid, 'floptimes'), self::activityValue($uid, 'flopgoldtimes'), self::activityValue($uid, 'flopgold')]);
        $this->assertSame(FlopRule::ROWS[2]['gold'] - 15, $this->request($p['auth'], 'flop', 'index')['info']['gold']);
        // 军令 5、突飞卡 2 各换 1 次
        Member::deductCurrency($uid, act: 5);
        FreeLeap::add($uid, 2);
        FreeLeap::sub($uid, 2);
        $this->assertSame([3, 1, 1], [self::activityValue($uid, 'floptimes'), self::activityValue($uid, 'flopacttimes'), self::activityValue($uid, 'flopleaptimes')]);
        $this->assertSame(['times' => 2, 'num' => 5], Flop::getTimes(0, Flop::SPEND_GOLD, 45));

        // 翻牌
        mt_srand(3);
        $turn = $this->request($p['auth'], 'flop', 'turnover', ['id' => 2]);
        $this->assertSame([1, 2, 6, 1], [$turn['status'], $turn['times'], count($turn['list']), $turn['list'][1]['isown']]);
        $this->assertSame($turn['reward']['type'], $turn['list'][1]['type']);
        $this->assertContains($turn['reward']['multiply'], [1, 2]);
        $types = array_map(static fn (array $c): int => $c['type'], $turn['list']);
        sort($types);
        $this->assertSame([1, 2, 3, 4, 5, 6], $types);
        $this->assertSame(1, DB::table('flop_action_log')->where('uid', $uid)->count());

        // 每日计划任务：消耗累计归零，剩余次数保留
        DB::table('member_field')->where('uid', $uid)->update(['dateline' => 0]);
        Member::dailyCron($uid, time());
        $this->assertSame([2, 0, 0], [self::activityValue($uid, 'floptimes'), self::activityValue($uid, 'flopgold'), self::activityValue($uid, 'flopgoldtimes')]);

        // 活动结束后 10 小时内：剩余次数自动领取并发信，之后清除
        BossClock::freeze(null);
        $end = 1483113600;
        $this->assertSame(-2, FlopCron::pushReward($end - 1));
        $this->assertSame(-3, FlopCron::pushReward($end + 36001));
        // 全服任务：清掉同一天先前执行留下的记录（重复运行测试时）
        DB::table('flop_cron_log')->where('version', (int)date('Ymd', $end + 100))->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $gold = self::memberValue($uid, 'gold');
        $silver = self::memberValue($uid, 'silver');
        $this->assertSame(1, FlopCron::pushReward($end + 100));
        $log = DB::table('flop_cron_log')->where('uid', $uid)->whereNull('deleted_at')->first();
        $this->assertSame([2, (int)date('Ymd', $end + 100)], [(int)$log['times'], (int)$log['version']]);
        $this->assertGreaterThan(0, (int)$log['dateline']);
        $this->assertSame(0, self::activityValue($uid, 'floptimes'));
        $this->assertSame([$gold + (int)$log['gold'], $silver + (int)$log['silver']], [self::memberValue($uid, 'gold'), self::memberValue($uid, 'silver')]);
        $this->assertStringContainsString('剩余2次抽奖机会', (string)DB::table('pm')->where('touid', $uid)->orderBy('id', 'DESC')->value('message'));
        $this->assertSame(-3, FlopCron::pushReward($end + 200));
        DB::table('member_activity')->where('uid', $uid)->update(['floptimes' => 4, 'flopgold' => 3]);
        $this->assertSame(1, FlopCron::clearFlopTimes($end + 40000));
        $this->assertSame([0, 0], [self::activityValue($uid, 'floptimes'), self::activityValue($uid, 'flopgold')]);
    }

    // ---------------------------------------------------------------------------------------------
    // 主城：建筑首次打开、活动图标
    // ---------------------------------------------------------------------------------------------

    public function testEdificeAndActivityFlags(): void
    {
        $p = $this->player(['level' => 12]);
        $uid = $p['uid'];
        $member = $this->request($p['auth'], 'member', 'index');
        $this->assertSame([['name' => 'practice', 'open' => 1]], $member['edifice']);
        $this->assertSame([0, 0, 0, 0], [$member['exercise'], $member['flop'], $member['sacredtree'], $member['dailysign']]);
        $this->assertSame([1, 0], [$member['levelgift'], $member['levelgift_popo']]);
        $this->assertSame(['times' => 50, 'cd' => 0, 'status' => 0], $member['levy']);
        $this->request($p['auth'], 'practice', 'index');
        $this->assertSame(1, DB::table('edifice_log')->where('uid', $uid)->where('edifice_id', 1)->count());
        $this->assertSame([], $this->request($p['auth'], 'member', 'index')['edifice']);
        // 再打开不重复记
        $this->request($p['auth'], 'practice', 'index');
        $this->assertSame(1, DB::table('edifice_log')->where('uid', $uid)->count());

        BossClock::freeze(self::OPEN_TS);
        $member = $this->request($p['auth'], 'member', 'index');
        // 战神殿 god 在活动循环后写死为 1（旧版 member.php）
        $this->assertSame([1, 1, 1, 1], [$member['exercise'], $member['flop'], $member['sacredtree'], $member['god']]);
        $this->assertSame(1, $member['blackmarket_type']);
    }
}
