<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\Cron\BeatengodCron;
use Hanjiang\Cron\NewgiftCron;
use Hanjiang\Cron\TreasuremapCron;
use Hanjiang\Handler\Activity\DiceHandler;
use Hanjiang\Handler\Activity\LotteryHandler;
use Hanjiang\MasterData\BeatengodBoxReward;
use Hanjiang\MasterData\BeatengodRankReward;
use Hanjiang\MasterData\DiceRankReward;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\HitEggPrice;
use Hanjiang\MasterData\LevelGift;
use Hanjiang\MasterData\Lottery;
use Hanjiang\MasterData\Mission;
use Hanjiang\MasterData\NewgiftReward;
use Hanjiang\MasterData\Newrolereward;
use Hanjiang\MasterData\RoleGift;
use Hanjiang\MasterData\Smash;
use Hanjiang\MasterData\WorldShop;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\Member;
use Hanjiang\Model\OwnMaterials;
use Hanjiang\Model\Pay;
use Hanjiang\Model\TraderLog;
use Hanjiang\Tests\Support\Gateway;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use PHPUnit\Framework\TestCase;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * 每日玩法第五批（最后一批）：幸运大转盘（c=lottery）、角色注册礼包（c=rolegift）、国家骰子（c=dice）、砸金蛋（c=hitegg）、
 * 威震天下（c=smash）、暴打名将（c=beatengod）、藏宝图（c=treasuremap）、装备打造（c=equip_forge）、强化成就（c=strengthen）、
 * 背包使用物品（c=pack&m=use_item）、苹果推送 token / 收据（c=ios）、临时 token（c=user）、分享战报（c=report）、评分奖励
 * （c=clickreward）、试玩绑定与奖励（c=register&m=binding / c=regreward）、修改密码（c=password）、注册前 3 日奖励
 * （c=newrolereward）、新服大礼包（c=newgift）、老版主线礼包（c=member&m=open_gift）按旧版的流程测试。
 *
 * 旧版数据中 beatengod 活动结束于 2016-03-20（1458403200），测试用 BossClock 固定到活动期间。
 */
final class Daily5Test extends TestCase
{
    /** 2016-03-15，beatengod 活动期间（离结束超过 1 天） */
    private const int BEATEN_TS = 1458000000;
    /** beatengod 活动结束时间 */
    private const int BEATEN_END = 1458403200;

    protected function tearDown(): void
    {
        BossClock::freeze(null);
        mt_srand();
    }

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $params
     * @return array<string, mixed>|string
     */
    private function raw(array $auth, string $controller, string $method, array $params = []): array|string
    {
        $ctx = Context::mock(array_merge(['c' => $controller, 'm' => $method], $auth, $params));
        $res = Gateway::handle($ctx);
        if ($res instanceof WorkermanResponse) {
            $body = (string)$res->rawBody();
            $json = json_decode($body, true);
            return is_array($json) ? $json : $body;
        }
        return $res;
    }

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function request(array $auth, string $controller, string $method, array $params = []): array
    {
        $res = $this->raw($auth, $controller, $method, $params);
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
     * @return array{uid: int, auth: array<string, mixed>, token: string}
     */
    private function player(array $member = []): array
    {
        $reg = $this->request(['token' => '', 'uid' => ''], 'register', 'guest');
        $uid = (int)$reg['uid'];
        $login = $this->request(['token' => '', 'uid' => $uid], 'login', 'guest', ['guestkey' => $reg['guestkey']]);
        $member += ['nickname' => 'd5_' . $uid, 'jointime' => time() - 30 * 86400];
        DB::table('member')->where('uid', $uid)->update($member);
        Redis::del('player:' . $uid);
        return ['uid' => $uid, 'auth' => ['token' => (string)$login['token'], 'uid' => $uid], 'token' => (string)$login['token']];
    }

    private static function memberValue(int $uid, string $column): int
    {
        return (int)DB::table('member')->where('uid', $uid)->value($column);
    }

    private static function fieldValue(int $uid, string $column): int
    {
        return (int)DB::table('member_field')->where('uid', $uid)->value($column);
    }

    /**
     * 找一个种子：mt_srand 后第一次 mt_rand($min, $max) 满足 $ok
     */
    private static function seed(int $min, int $max, callable $ok): int
    {
        for ($s = 1; $s < 100000; $s++) {
            mt_srand($s);
            if ($ok(mt_rand($min, $max))) {
                return $s;
            }
        }
        throw new \RuntimeException('no seed');
    }

    // ---------------------------------------------------------------------------------------------
    // 幸运大转盘 / 角色注册礼包
    // ---------------------------------------------------------------------------------------------

    public function testLotteryIndexActionBuyRefreshAndDailyCron(): void
    {
        $p = $this->player(['level' => 15, 'gender' => 1, 'vip' => 1, 'gold' => 1000, 'silver' => 0]);
        $uid = $p['uid'];
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'lottery', 'index'), '16 级开放');
        DB::table('member')->where('uid', $uid)->update(['level' => 16]);

        // 首次进入建档：连续登录 1 天、免费 1 次；奖品是同一组的 8 个，不带 getrate
        Redis::set('lottery_group_' . $uid . '_' . date('ymd'), 3, 86400);
        $index = $this->request($p['auth'], 'lottery', 'index');
        $this->assertSame(1, $index['status']);
        $this->assertSame([1, 1, 1, 0, 1, 10], [$index['log']['info']['day'], $index['log']['info']['number'], $index['log']['info']['lavenumber'], $index['log']['info']['buy_lavenumber'], $index['log']['info']['total_num'], $index['log']['info']['price']]);
        $this->assertCount(8, $index['list']);
        $this->assertSame([3], array_values(array_unique(array_column($index['list'], 'group'))));
        $this->assertArrayNotHasKey('getrate', $index['list'][0]);
        $this->assertSame([0, 10, 0], [$index['buy_times'], $index['buy_times_limit'], $index['buy_times_total']]);

        // 抽奖：第 3 组全是元宝，用掉免费次数
        $action = $this->request($p['auth'], 'lottery', 'action');
        $this->assertSame(1, $action['status']);
        $this->assertSame(2, $action['info']['gettype']);
        $this->assertContains($action['info']['getvalue'], array_column(array_filter(Lottery::ROWS, static fn (array $r): bool => $r['group'] === 3), 'getvalue'));
        $this->assertSame(1000 + $action['info']['getvalue'], $action['info']['all_gold']);
        $this->assertSame([0, 0, 0], [$action['lavenumber'], $action['buy_lavenumber'], $action['total_num']]);
        $this->assertSame(1000 + $action['info']['getvalue'], self::memberValue($uid, 'gold'));
        $this->assertSame(-2, $this->request($p['auth'], 'lottery', 'action')['status'], '没有次数');

        // 购买次数：10 元宝 1 次
        $gold = self::memberValue($uid, 'gold');
        $buy = $this->request($p['auth'], 'lottery', 'buy_lottery_num');
        $this->assertSame([1, $gold - 10, 1, 1, 1, 0], [$buy['status'], $buy['gold'], $buy['number'], $buy['total_num'], $buy['buy_times'], $buy['buy_times_total']]);
        $this->assertSame(1, (int)DB::table('stat_pay')->where('name', 'choujiang_times')->value('times') > 0 ? 1 : 0);
        Redis::set('lottery_buy_times_' . date('ymd') . '_' . $uid, 10, 86400);
        $this->assertSame(-3, $this->request($p['auth'], 'lottery', 'buy_lottery_num')['status'], '每天 10 次');
        $action = $this->request($p['auth'], 'lottery', 'action');
        $this->assertSame([1, 0, 0], [$action['status'], $action['buy_lavenumber'], $action['total_num']]);

        // 刷新：20 元宝换另一组
        $gold = self::memberValue($uid, 'gold');
        $refresh = $this->request($p['auth'], 'lottery', 'refresh');
        $this->assertSame([1, $gold - 20], [$refresh['status'], $refresh['gold']]);
        $this->assertNotSame(3, $refresh['list'][0]['group']);
        $this->assertSame($refresh['list'][0]['group'], (int)Redis::get('lottery_group_' . $uid . '_' . date('ymd')));

        // 每日计划任务：连续登录 + 1 天，按天数发免费次数；断开从 1 天算
        $now = time();
        DB::table('lottery_log')->where('uid', $uid)->update(['day' => 2, 'lastdateline' => $now - 86400]);
        LotteryHandler::updateDay($uid, $now);
        $this->assertSame([3, 3, 3], array_map('intval', array_values(DB::table('lottery_log')->select('day', 'number', 'lavenumber')->where('uid', $uid)->first())));
        DB::table('lottery_log')->where('uid', $uid)->update(['day' => 6, 'lastdateline' => $now - 86400]);
        LotteryHandler::updateDay($uid, $now);
        $this->assertSame(4, (int)DB::table('lottery_log')->where('uid', $uid)->value('lavenumber'), '连续 7 天 4 次');
        DB::table('lottery_log')->where('uid', $uid)->update(['day' => 6, 'lastdateline' => $now - 3 * 86400]);
        LotteryHandler::updateDay($uid, $now);
        $this->assertSame([1, 1], [(int)DB::table('lottery_log')->where('uid', $uid)->value('day'), (int)DB::table('lottery_log')->where('uid', $uid)->value('lavenumber')]);

        // 每日计划任务里调用（member_field.dateline 为昨天）
        DB::table('lottery_log')->where('uid', $uid)->update(['day' => 1, 'lastdateline' => $now - 86400]);
        DB::table('member_field')->where('uid', $uid)->update(['dateline' => $now - 86400]);
        Member::dailyCron($uid, $now);
        $this->assertSame(2, (int)DB::table('lottery_log')->where('uid', $uid)->value('day'));

        // 注册 120 秒内抽奖：按刷接口封号
        DB::table('member')->where('uid', $uid)->update(['jointime' => $now - 10]);
        $this->assertSame(-1, $this->request($p['auth'], 'lottery', 'action')['status']);
        $this->assertSame(4, self::memberValue($uid, 'status'));
    }

    public function testLotteryGeneralAndItemNeedVipAndBroadcast(): void
    {
        $p = $this->player(['level' => 20, 'gender' => 1, 'vip' => 0, 'gold' => 0]);
        $uid = $p['uid'];
        $this->request($p['auth'], 'lottery', 'index');
        // 第 1 组的道具（装备 56）VIP 0 抽不到：只会抽到紫宝石
        Redis::set('lottery_group_' . $uid . '_' . date('ymd'), 1, 86400);
        $action = $this->request($p['auth'], 'lottery', 'action');
        $this->assertSame(6, $action['info']['gettype']);
        $this->assertSame($action['info']['getvalue'], TraderLog::jade($uid, 3));
        // VIP 1：概率表里有装备 56（权重 10 / 9970）；用种子抽中装备
        DB::table('member')->where('uid', $uid)->update(['vip' => 1]);
        DB::table('lottery_log')->where('uid', $uid)->update(['lavenumber' => 1]);
        $seed = self::seed(1, 9970, static fn (int $r): bool => $r > 9960);
        mt_srand($seed);
        // lottery() 会打乱列表（shuffle 也用 mt_rand），直接改用只有装备与紫宝石的判断：结果要么装备要么紫宝石
        $action = $this->request($p['auth'], 'lottery', 'action');
        $this->assertSame(1, $action['status']);
        $this->assertContains($action['info']['gettype'], [6, 7]);
        if ($action['info']['gettype'] === 7) {
            $this->assertSame([56, Equip::ROWS[56]['name']], [$action['info']['itemid'], $action['info']['itemname']]);
            $this->assertSame(1, DB::table('equipment')->where('uid', $uid)->where('equipid', 56)->count());
        }
    }

    public function testRolegiftListAndReward(): void
    {
        $p = $this->player(['jointime' => strtotime(date('Y-m-d')) - 15 * 86400 + 3600]);
        $uid = $p['uid'];
        $index = $this->request($p['auth'], 'rolegift', 'index');
        $this->assertSame([1, 16, ['jade1' => 0, 'jade2' => 0, 'jade3' => 0], count(RoleGift::ROWS)], [$index['status'], $index['joinday'], $index['history'], count($index['list'])]);
        $this->assertSame([1, 0], [$index['list'][0]['get'], $index['list'][1]['get']]);
        $this->assertSame(date('Y-m-d', strtotime(date('Y-m-d')) - 15 * 86400 + 3600), $index['regday']);

        $this->assertSame(['status' => -2], $this->request($p['auth'], 'rolegift', 'get_reward', ['id' => 2]), '注册天数不足');
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'rolegift', 'get_reward', ['id' => 999]));
        $get = $this->request($p['auth'], 'rolegift', 'get_reward', ['id' => 1]);
        $this->assertSame([1, ['type' => 1, 'num' => RoleGift::ROWS[1]['num']], 2], [$get['status'], $get['add'], $get['list'][0]['get']]);
        $this->assertSame(RoleGift::ROWS[1]['num'], $get['history']['jade2']);
        $this->assertSame(RoleGift::ROWS[1]['num'], TraderLog::jade($uid, 2), '黄宝石');
        $this->assertSame(['status' => 2], $this->request($p['auth'], 'rolegift', 'get_reward', ['id' => 1]), '已领取');
    }

    // ---------------------------------------------------------------------------------------------
    // 国家骰子
    // ---------------------------------------------------------------------------------------------

    public function testDiceShakeRankRewardsAndClear(): void
    {
        $a = $this->player(['gold' => 0]);
        $b = $this->player(['gold' => 0]);
        $this->assertSame(['status' => -1], $this->request($a['auth'], 'dice', 'index'), '没有国家');
        $country = (int)DB::table('country')->insertGetId(['name' => '骰' . substr((string)hrtime(true), -6), 'level' => 34, 'member' => 2, 'uid' => $a['uid'], 'nickname' => 'k']);
        foreach ([$a, $b] as $i => $p) {
            DB::table('member')->where('uid', $p['uid'])->update(['country' => $country]);
            DB::table('member_country')->insert(['uid' => $p['uid'], 'country' => $country, 'job' => $i === 0 ? 1 : 10, 'joindateline' => time() - 86400]);
        }
        $this->assertSame(['status' => -3], $this->request($a['auth'], 'dice', 'index'), '国家 35 级开放');
        DB::table('country')->where('id', $country)->update(['level' => 35]);

        $week = (int)ceil((time() - 1362326400) / (86400 * 7));
        $index = $this->request($a['auth'], 'dice', 'index');
        $this->assertSame([1, 7, 0, ['status' => -1], []], [$index['status'], $index['member']['times'], $index['member']['points'], $index['last_week'], $index['list']]);
        $this->assertSame([600, 0], [$index['reward'][0]['points'], $index['reward'][0]['status']]);

        $shake = $this->request($a['auth'], 'dice', 'shake_dice');
        $points = $shake['member']['dice1'] + $shake['member']['dice2'] + $shake['member']['dice3'];
        $this->assertSame([1, 6, $points], [$shake['status'], $shake['member']['times'], $shake['member']['points']]);
        $this->assertSame([['uid' => $a['uid'], 'nickname' => 'd5_' . $a['uid'], 'points' => $points, 'dice1' => $shake['member']['dice1'], 'dice2' => $shake['member']['dice2'], 'dice3' => $shake['member']['dice3'], 'rank' => 1, 'reward' => DiceRankReward::ROWS[1]['reward']]], $shake['list']);
        DB::table('dice_log')->where('uid', $a['uid'])->update(['times' => 0]);
        $this->assertSame(['status' => -5], $this->request($a['auth'], 'dice', 'shake_dice'), '次数用完');

        // 特别大奖：点数不够 -7；达到后领元宝，不能重复领
        $this->assertSame(['status' => -9], $this->request($a['auth'], 'dice', 'get_reward'));
        $this->assertSame(['status' => -7], $this->request($a['auth'], 'dice', 'get_reward', ['id' => 2]));
        DB::table('dice_log')->where('uid', $a['uid'])->update(['points' => 600]);
        $this->assertSame(['status' => 1, 'add' => 300, 'all_gold' => 300], $this->request($a['auth'], 'dice', 'get_reward', ['id' => 1]));
        $this->assertSame(['status' => -5], $this->request($a['auth'], 'dice', 'get_reward', ['id' => 1]));
        $index = $this->request($a['auth'], 'dice', 'index');
        $this->assertSame([2, 1], [$index['reward'][0]['status'], $index['reward'][1]['status']]);

        // 上周排名奖励：本国点数比自己高的人数 + 1
        DB::table('dice_log')->insert(['uid' => $a['uid'], 'week' => $week - 1, 'nickname' => 'a', 'points' => 50, 'times' => 0, 'dateline' => time() - 7 * 86400]);
        DB::table('dice_log')->insert(['uid' => $b['uid'], 'week' => $week - 1, 'nickname' => 'b', 'points' => 80, 'times' => 0, 'dateline' => time() - 7 * 86400]);
        $index = $this->request($a['auth'], 'dice', 'index');
        $this->assertSame([1, 2, DiceRankReward::ROWS[2]['reward']], [$index['last_week']['status'], $index['last_week']['now_rank'], $index['last_week']['reward']]);
        $get = $this->request($a['auth'], 'dice', 'get_last_week_reward');
        $this->assertSame([1, DiceRankReward::ROWS[2]['reward'], 300 + DiceRankReward::ROWS[2]['reward']], [$get['status'], $get['get'], $get['all_gold']]);
        $this->assertSame(['status' => -5], $this->request($a['auth'], 'dice', 'get_last_week_reward'), '已领取');

        // 离开国家清点数
        DiceHandler::clearDiceInfo($a['uid']);
        $this->assertSame(0, (int)DB::table('dice_log')->where('uid', $a['uid'])->sum('points'));
    }

    // ---------------------------------------------------------------------------------------------
    // 砸金蛋 / 威震天下
    // ---------------------------------------------------------------------------------------------

    public function testHiteggFreeCdPaidRewardAndMemberFlag(): void
    {
        $now = time();
        $p = $this->player(['level' => 19, 'gender' => 1, 'gold' => 1000, 'jointime' => $now - 600]);
        $uid = $p['uid'];
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'hitegg', 'index'), '20 级开放');
        DB::table('member')->where('uid', $uid)->update(['level' => 20]);

        // 注册 10 分钟：金蛋 1 / 2 可免费（5 分钟），金蛋 3 还要等（60 分钟）
        $index = $this->request($p['auth'], 'hitegg', 'index');
        $this->assertSame([1, 1, 0], [$index['status'], $index['list'][0]['open'], $index['list'][2]['open']]);
        $this->assertSame(HitEggPrice::ROWS[1]['price'], $index['list'][0]['price']);
        $this->assertEqualsWithDelta(3000, $index['list'][2]['cd'], 2);
        $this->assertSame(1, $this->request($p['auth'], 'member', 'index')['hitegg']);

        // 免费砸金蛋 1：不扣元宝，倒计时重置为 1 天；金蛋 1 的武将吕蒙权重极高
        $hit = $this->request($p['auth'], 'hitegg', 'hit_egg', ['id' => 1]);
        $this->assertSame([1, 86400], [$hit['status'], $hit['cd']]);
        $this->assertArrayNotHasKey('all_gold', $hit);
        $this->assertSame(1000, self::memberValue($uid, 'gold'));
        $this->assertSame(1, DB::table('hit_egg_log')->where('uid', $uid)->where('eggid', 1)->count());
        $this->assertSame([0, 20], [(int)DB::table('hitegg_action_log')->where('uid', $uid)->value('gold'), (int)DB::table('hitegg_action_log')->where('uid', $uid)->value('level')]);
        $egg = DB::table('hit_egg')->where('uid', $uid)->first();
        $this->assertGreaterThanOrEqual($now, (int)$egg['egg1_f_time'], '新用户免费砸后改为按每天一次');
        $index = $this->request($p['auth'], 'hitegg', 'index');
        $this->assertSame(0, $index['list'][0]['open']);
        $this->assertEqualsWithDelta(86400, $index['list'][0]['cd'], 3);

        // 花元宝砸：扣价格，已拥有的武将不再出现
        $hit = $this->request($p['auth'], 'hitegg', 'hit_egg', ['id' => 1]);
        $this->assertSame([1, 1000 - HitEggPrice::ROWS[1]['price']], [$hit['status'], $hit['all_gold']]);
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'hitegg', 'hit_egg'));
        $this->assertSame(['status' => -3], $this->request($p['auth'], 'hitegg', 'hit_egg', ['id' => 9]));
        DB::table('member')->where('uid', $uid)->update(['gold' => 0]);
        $this->assertSame(['status' => -4], $this->request($p['auth'], 'hitegg', 'hit_egg', ['id' => 3]));
        Redis::set('hitege_lock_' . $uid, 1, 30);
        $this->assertSame(['status' => -8], $this->request($p['auth'], 'hitegg', 'hit_egg', ['id' => 1]), '请求太快');
        DB::table('member')->where('uid', $uid)->update(['gender' => 0]);
        $this->assertSame(['status' => -7], $this->request($p['auth'], 'hitegg', 'hit_egg', ['id' => 1]));

        // 奖励说明：非武将每种只留一条，按 price 降序
        $reward = $this->request($p['auth'], 'hitegg', 'get_hitegg_reward', ['id' => 1]);
        $this->assertSame(1, $reward['status']);
        $prices = array_column($reward['list'], 'price');
        $sorted = $prices;
        rsort($sorted);
        $this->assertSame($sorted, $prices);
        $this->assertSame(['type' => 4, 'value' => $reward['list'][array_search(4, array_column($reward['list'], 'type'), true)]['value']], array_intersect_key($reward['list'][array_search(4, array_column($reward['list'], 'type'), true)], ['type' => 0, 'value' => 0]));
        $general = $reward['list'][array_search(5, array_column($reward['list'], 'type'), true)];
        $this->assertArrayHasKey('skill_desc', $general['general']);
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'hitegg', 'get_hitegg_reward'));
    }

    public function testSmashDailyOnceAndVipCrit(): void
    {
        $p = $this->player(['level' => 149, 'gold' => 2000, 'reputation' => 0, 'vip' => 0]);
        $uid = $p['uid'];
        $this->assertSame(['status' => -1, 'isuse' => 0, 'list' => []], $this->request($p['auth'], 'smash', 'index'));
        DB::table('member')->where('uid', $uid)->update(['level' => 150]);
        $index = $this->request($p['auth'], 'smash', 'index');
        $this->assertSame([1, 0, array_values(Smash::ROWS)], [$index['status'], $index['isuse'], $index['list']]);

        $this->assertSame(['status' => -2], $this->request($p['auth'], 'smash', 'action'));
        $this->assertSame(['status' => -4], $this->request($p['auth'], 'smash', 'action', ['id' => 9]));
        $action = $this->request($p['auth'], 'smash', 'action', ['id' => 1]);
        $this->assertSame(['status' => 1, 'rate' => 0, 'add' => 1000, 'reputation' => 1000, 'all_gold' => 1900], $action);
        $this->assertSame(['status' => -5], $this->request($p['auth'], 'smash', 'action', ['id' => 1]), '每天一次');
        $this->assertSame(1, $this->request($p['auth'], 'smash', 'index')['isuse']);

        // VIP 13：随机数 31 ~ 35 时声望 ×4
        DB::table('member_activity')->where('uid', $uid)->update(['smashtime' => time() - 86400]);
        DB::table('member')->where('uid', $uid)->update(['vip' => 13]);
        mt_srand(self::seed(1, 100, static fn (int $r): bool => $r > 30 && $r <= 35));
        $action = $this->request($p['auth'], 'smash', 'action', ['id' => 2]);
        $this->assertSame([1, 4, 12000, 13000], [$action['status'], $action['rate'], $action['add'], $action['reputation']]);
        DB::table('member_activity')->where('uid', $uid)->update(['smashtime' => time() - 86400]);
        DB::table('member')->where('uid', $uid)->update(['gold' => 0]);
        $this->assertSame(['status' => -6], $this->request($p['auth'], 'smash', 'action', ['id' => 3]));
    }

    // ---------------------------------------------------------------------------------------------
    // 暴打名将
    // ---------------------------------------------------------------------------------------------

    public function testBeatengodBeatenBoxPayChipRankAndCrons(): void
    {
        // 排行是全服的：清掉之前测试留下的本期记录
        foreach (['beatengod_log', 'beatengod_order', 'beatengod_reward_log'] as $table) {
            DB::table($table)->where('version', 62)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        }
        $p = $this->player(['level' => 40, 'gold' => 5000, 'silver' => 0]);
        $q = $this->player(['level' => 40, 'gold' => 5000]);
        $uid = $p['uid'];
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'beatengod', 'index'), '旧版数据活动已结束');

        BossClock::freeze(self::BEATEN_TS);
        $index = $this->request($p['auth'], 'beatengod', 'index');
        $this->assertSame([1, 0, self::BEATEN_END - 86400 - self::BEATEN_TS, [['id' => 1, 'price' => 100], ['id' => 2, 'price' => 400]]], [$index['status'], $index['own_chip'], $index['cd'], $index['item']]);
        $this->assertSame([count(BeatengodBoxReward::ROWS), 0], [count($index['reward']), $index['reward'][0]['open']]);
        $this->assertSame(1, DB::table('beatengod_log')->where('uid', $uid)->where('version', 62)->count(), '首次进入建档');

        // 木锤：100 元宝，碎片 2 ~ 4，常规奖励（银币 × 主公等级）
        $this->assertSame(['status' => -7], $this->request($p['auth'], 'beatengod', 'beaten'));
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'beatengod', 'beaten', ['id' => 3]));
        $beaten = $this->request($p['auth'], 'beatengod', 'beaten', ['id' => 1]);
        $this->assertSame([1, 4900], [$beaten['status'], $beaten['all_gold']]);
        $this->assertContains($beaten['add_chip'], [2, 3, 4]);
        $this->assertSame($beaten['add_chip'], $beaten['number']);
        $this->assertSame([1, 100, 40], [(int)DB::table('beatengod_action_log')->where('uid', $uid)->value('type'), (int)DB::table('beatengod_action_log')->where('uid', $uid)->value('gold'), (int)DB::table('beatengod_action_log')->where('uid', $uid)->value('level')]);
        $q1 = $this->request($q['auth'], 'beatengod', 'beaten', ['id' => 2]);
        $this->assertSame(1, $q1['status']);
        $this->assertGreaterThanOrEqual(8, $q1['add_chip']);

        // 活动期间充值记账：每 1 元 1 个充值碎片
        Pay::complete($uid, 1, 'd5_beaten_' . $uid, 'test', (int)\Hanjiang\MasterData\AlipayProduct::ROWS[1]['amount'], self::BEATEN_TS);
        $amount = (int)\Hanjiang\MasterData\AlipayProduct::ROWS[1]['amount'];
        $this->assertSame([$amount, 62], [(int)DB::table('beatengod_order')->where('uid', $uid)->value('money'), (int)DB::table('beatengod_order')->where('uid', $uid)->value('version')]);
        $info = $this->request($p['auth'], 'beatengod', 'get_pay_chip_info');
        $this->assertSame(['status' => 1, 'gold' => $amount * 10, 'get' => 0, 'isget' => 1], $info);
        $chip = $this->request($p['auth'], 'beatengod', 'get_pay_chip');
        $this->assertSame([1, $amount, $beaten['add_chip'] + $amount, $amount], [$chip['status'], $chip['add_chip'], $chip['all_chip'], $chip['all_pay_chip']]);
        $this->assertSame(['status' => -3], $this->request($p['auth'], 'beatengod', 'get_pay_chip'));

        // 宝箱：碎片够了开箱
        $this->assertSame(['status' => -4], $this->request($p['auth'], 'beatengod', 'get_box_reward', ['id' => 1]));
        DB::table('beatengod_log')->where('uid', $uid)->update(['own_chip' => 1400]);
        $box = $this->request($p['auth'], 'beatengod', 'get_box_reward', ['id' => 1]);
        $this->assertSame(1, $box['status']);
        $this->assertSame(BeatengodBoxReward::ROWS[1]['value3'], TraderLog::jade($uid, 3), '紫宝石');
        $this->assertSame(['status' => -5], $this->request($p['auth'], 'beatengod', 'get_box_reward', ['id' => 1]));
        $this->assertSame(2, $this->request($p['auth'], 'beatengod', 'index')['reward'][0]['open']);

        // 排行：活动结束前不可领
        $rank = $this->request($p['auth'], 'beatengod', 'get_rank_list');
        $this->assertSame([1, 0, $uid, 1, BeatengodRankReward::ROWS[1]['value']], [$rank['status'], $rank['isget'], $rank['list'][0]['uid'], $rank['list'][0]['rank'], $rank['list'][0]['value']]);
        $this->assertArrayNotHasKey('pay_chip', $rank['list'][0]);
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'beatengod', 'get_rank_reward'));

        // 结束前一天（cd 为 0）：暴打关闭，排行奖励可领，充值碎片记 end_chip
        BossClock::freeze(self::BEATEN_END - 86400 + 60);
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'beatengod', 'beaten', ['id' => 1]));
        $this->assertSame(1, $this->request($p['auth'], 'beatengod', 'get_rank_list')['isget']);
        $get = $this->request($p['auth'], 'beatengod', 'get_rank_reward');
        $this->assertSame([1, BeatengodRankReward::ROWS[1]], [$get['status'], $get['reward']]);
        $this->assertSame(['status' => -5], $this->request($p['auth'], 'beatengod', 'get_rank_reward'));
        Pay::complete($uid, 1, 'd5_beaten2_' . $uid, 'test', $amount, self::BEATEN_END - 86400 + 60);
        $this->assertSame(1, DB::table('beatengod_order')->where('uid', $uid)->count(), '最后一天的充值不再记账');

        // 计划任务：结束后 10 小时内给第 2 名补发排行奖励、补开宝箱并发信件
        $this->assertSame(-2, BeatengodCron::rank(self::BEATEN_END - 10));
        $jade = TraderLog::jade($q['uid'], BeatengodRankReward::ROWS[2]['type']);
        $this->assertSame(1, BeatengodCron::rank(self::BEATEN_END + 100));
        $this->assertSame(2, (int)DB::table('beatengod_log')->where('uid', $q['uid'])->value('get_rank'));
        $this->assertSame($jade + BeatengodRankReward::ROWS[2]['value'], TraderLog::jade($q['uid'], BeatengodRankReward::ROWS[2]['type']));
        DB::table('beatengod_log')->where('uid', $q['uid'])->update(['own_chip' => 1500]);
        $this->assertSame(1, BeatengodCron::box(self::BEATEN_END + 100));
        $this->assertSame(1, DB::table('beatengod_reward_log')->where('uid', $q['uid'])->where('rewardid', 1)->count());
        $this->assertSame(2, DB::table('pm')->where('touid', $q['uid'])->count());
        $this->assertSame(-6, BeatengodCron::box(self::BEATEN_END + 200), '已发过不再发');
        $this->assertSame(-3, BeatengodCron::rank(self::BEATEN_END + 36001));
    }

    // ---------------------------------------------------------------------------------------------
    // 藏宝图
    // ---------------------------------------------------------------------------------------------

    /**
     * @param array{uid: int} $p
     */
    private static function giveMap(array $p, int $type, int $quality, int $dateline): int
    {
        return (int)DB::table('own_treasuremap')->insert([
            'uid' => $p['uid'], 'nickname' => 'd5_' . $p['uid'], 'type' => $type, 'quality' => $quality, 'dateline' => $dateline,
        ]);
    }

    public function testTreasuremapTeamFinishExitUpgradeSaleAndCron(): void
    {
        foreach (Redis::keys('treasuremap_*') as $key) {
            Redis::del(substr((string)$key, strlen((string)Redis::instance()->getOption(\Redis::OPT_PREFIX))));
        }
        $a = $this->player(['level' => 30, 'silver' => 0, 'gold' => 100]);
        $b = $this->player(['level' => 40, 'silver' => 0]);
        $c = $this->player(['level' => 50, 'silver' => 0]);
        $now = time();
        $this->assertSame(['status' => -1], $this->request($this->player(['level' => 25])['auth'], 'treasuremap', 'index'));
        $this->assertSame(-2, $this->request($a['auth'], 'treasuremap', 'create_team')['status'], '没有藏宝图');
        $mapA = self::giveMap($a, 1, 1, $now - 30);
        $mapB = self::giveMap($b, 2, 1, $now - 20);
        $mapC = self::giveMap($c, 3, 1, $now - 10);

        // 旧版 update_activity_info 没有活动记录时不插入：B / C 先打开过界面（建档）
        $this->request($b['auth'], 'treasuremap', 'index');
        $this->request($c['auth'], 'treasuremap', 'index');
        $index = $this->request($a['auth'], 'treasuremap', 'index');
        $this->assertSame([1, 0, 2, 1, 0], [$index['status'], $index['team_status'], $index['refresh_price'], $index['num'], $index['add_silver']]);
        $this->assertSame([$mapA, '普通藏宝图', 30, 750, 2, 0], [$index['map']['id'], $index['map']['name'], $index['map']['level'], $index['map']['sale_price'], $index['map']['levelup_price'], $index['map']['ismax']]);

        $create = $this->request($a['auth'], 'treasuremap', 'create_team');
        $this->assertSame([1, $a['uid'], 1], [$create['status'], $create['team']['id'], count($create['team']['list'])]);
        $this->assertSame(2, $this->request($a['auth'], 'treasuremap', 'create_team')['status'], '已在队伍中');
        $this->assertSame(-2, $this->request($a['auth'], 'treasuremap', 'levelup')['status'], '队伍中无法升级');
        $this->assertSame(1, (int)DB::table('own_treasuremap')->where('id', $mapA)->value('isuse'));

        $join = $this->request($b['auth'], 'treasuremap', 'join_team', ['tid' => $a['uid']]);
        $this->assertSame([1, 0, 2, []], [$join['status'], $join['finish'], count($join['team']['list']), $join['map']]);
        $this->assertSame(-2, $this->request($c['auth'], 'treasuremap', 'join_team', ['tid' => 999999])['status']);
        // 同部位不能加入
        $mapC2 = self::giveMap($c, 2, 1, $now - 100);
        $this->assertSame(3, $this->request($c['auth'], 'treasuremap', 'join_team', ['tid' => $a['uid']])['status'], '部件已存在');
        DB::table('own_treasuremap')->where('id', $mapC2)->update(['quality' => 2]);
        $this->assertSame(4, $this->request($c['auth'], 'treasuremap', 'join_team', ['tid' => $a['uid']])['status'], '品级不一致');
        DB::table('own_treasuremap')->where('id', $mapC2)->update(['deleted_at' => date('Y-m-d H:i:s')]);

        // 第三块凑齐：最后加入的当场到账，其余记 mapreward、下次打开界面到账
        $join = $this->request($c['auth'], 'treasuremap', 'join_team', ['tid' => $a['uid']]);
        $this->assertSame([1, 1, 100 * 50, 100 * 50, []], [$join['status'], $join['finish'], $join['add_silver'], $join['silver'], $join['team']]);
        $this->assertSame(0, DB::table('own_treasuremap')->whereIn('id', [$mapA, $mapB, $mapC])->whereNull('deleted_at')->count());
        $this->assertSame([3000, 4000], [(int)DB::table('member_activity')->where('uid', $a['uid'])->value('mapreward'), (int)DB::table('member_activity')->where('uid', $b['uid'])->value('mapreward')]);
        $index = $this->request($a['auth'], 'treasuremap', 'index');
        $this->assertSame([0, 3000, 3000, [], 0], [$index['team_status'], $index['add_silver'], $index['silver'], $index['map'], $index['num']]);
        $this->assertSame(0, (int)DB::table('member_activity')->where('uid', $a['uid'])->value('mapreward'));

        // 队长退出：第二个人的藏宝图重新建队
        $mapA = self::giveMap($a, 1, 2, $now);
        $mapB = self::giveMap($b, 2, 2, $now);
        $this->request($a['auth'], 'treasuremap', 'create_team');
        $this->request($b['auth'], 'treasuremap', 'join_team', ['tid' => $a['uid']]);
        $exit = $this->request($a['auth'], 'treasuremap', 'exit_team');
        $this->assertSame([1, $b['uid']], [$exit['status'], $exit['list'][0]['id']]);
        $this->assertSame([0, 1], [(int)DB::table('own_treasuremap')->where('id', $mapA)->value('isuse'), (int)DB::table('own_treasuremap')->where('id', $mapB)->value('isuse')]);
        $this->assertSame(-2, $this->request($a['auth'], 'treasuremap', 'exit_team')['status']);
        $this->assertSame(1, $this->request($b['auth'], 'treasuremap', 'exit_team')['status'], '解散');

        // 升品：2 → 3 花 4 元宝；刷新部位花 2 元宝；出售按品级系数 × 等级
        $up = $this->request($a['auth'], 'treasuremap', 'levelup');
        $this->assertSame([1, 3, '无暇藏宝图', 3000, 10, 0, 96], [$up['status'], $up['map']['quality'], $up['map']['name'], $up['map']['sale_price'], $up['map']['levelup_price'], $up['map']['ismax'], $up['gold']]);
        $refresh = $this->request($a['auth'], 'treasuremap', 'refresh');
        $this->assertSame([1, 94], [$refresh['status'], $refresh['gold']]);
        $this->assertNotSame(1, $refresh['type']);
        $silver = self::memberValue($a['uid'], 'silver');
        $sale = $this->request($a['auth'], 'treasuremap', 'sale');
        $this->assertSame([1, 3000, $silver + 3000, []], [$sale['status'], $sale['add_silver'], $sale['silver'], $sale['map']]);
        $this->assertSame(-3, $this->request($a['auth'], 'treasuremap', 'sale')['status']);

        // 征收掉落（30%）
        mt_srand(self::seed(1, 100, static fn (int $r): bool => $r > 70));
        $impose = $this->request($a['auth'], 'city', 'impress');
        $this->assertSame([$a['uid'], 'd5_' . $a['uid']], [$impose['treasuremap']['uid'], $impose['treasuremap']['nickname']]);
        $this->assertSame(1, DB::table('own_treasuremap')->where('uid', $a['uid'])->where('id', $impose['treasuremap']['id'])->count());

        // 计划任务：每日 7 点卖掉昨天以前获得、未组队的藏宝图并发信件
        DB::table('own_treasuremap')->where('uid', $a['uid'])->whereNull('deleted_at')->update(['dateline' => $now - 2 * 86400]);
        $silver = self::memberValue($a['uid'], 'silver');
        $quality = (int)DB::table('own_treasuremap')->where('uid', $a['uid'])->whereNull('deleted_at')->value('quality');
        $this->assertGreaterThanOrEqual(1, TreasuremapCron::run($now));
        $price = [1 => 25, 2 => 50, 3 => 100, 4 => 200, 5 => 400][$quality] * 30;
        $this->assertSame($silver + $price, self::memberValue($a['uid'], 'silver'));
        $this->assertSame(0, DB::table('own_treasuremap')->where('uid', $a['uid'])->whereNull('deleted_at')->count());
        $this->assertSame(1, DB::table('pm')->where('touid', $a['uid'])->where('message', 'like', '昨日剩余的1件藏宝图%')->count());
    }

    // ---------------------------------------------------------------------------------------------
    // 装备打造 / 强化成就
    // ---------------------------------------------------------------------------------------------

    private static function material(int $uid, int $mid, int $num): void
    {
        $temp = OwnMaterials::template($mid);
        OwnMaterials::change($uid, $mid, (int)$temp['quality'], (string)$temp['name'], 0, $num, 1);
    }

    public function testEquipForgeFabricateSellResolveAndSuit(): void
    {
        $p = $this->player(['level' => 109, 'silver' => 0, 'vip' => 0, 'jade' => 0]);
        $uid = $p['uid'];
        $this->assertSame(['status' => -1, 'msg' => '功能未开启'], $this->request($p['auth'], 'equip_forge', 'index'));
        DB::table('member')->where('uid', $uid)->update(['level' => 110]);
        $id = Equipment::addFromTemplate($uid, 22)[0];
        DB::table('equipment')->where('id', $id)->update(['level' => 3, 'saleprice' => 1001, 'powerup' => 260]);
        self::material($uid, 40, 30);
        self::material($uid, 15, 15);

        $index = $this->request($p['auth'], 'equip_forge', 'index');
        $this->assertSame([1, 1, $id, '', 260, 1000], [$index['status'], count($index['equiplist']), $index['equiplist'][0]['id'], $index['equiplist'][0]['gname'], $index['equiplist'][0]['allpower'], $index['equiplist'][0]['saleprice']]);
        $this->assertArrayNotHasKey('oldequipid', $index['equiplist'][0]);
        $this->assertSame([[40, 30, 5000], [15, 15, 10000]], array_map(static fn (array $m): array => [$m['materials_id'], $m['num'], $m['sales']], $index['materiallist']));

        // 打造信息：紫装青龙偃月刀 → 八荒六合戟（升级打造），材料需求与拥有数
        $info = $this->request($p['auth'], 'equip_forge', 'get_fabrication_info', ['id' => $id]);
        $this->assertSame([1, 57, 1], [$info['status'], $info['newequiplist'][0]['newequipid'], $info['newequiplist'][0]['typeup']]);
        $this->assertSame([[40, 30, 30], [15, 15, 15]], array_map(static fn (array $c): array => [$c['type'], $c['params'], $c['ownnum']], $info['newequiplist'][0]['consumearr']));
        $this->assertSame('八荒六合戟', $info['newequiplist'][0]['newequipinfo']['name']);
        $this->assertSame(['status' => -2, 'msg' => '参数不能为空'], $this->request($p['auth'], 'equip_forge', 'get_fabrication_info'));

        // 升级打造：属性取新装备模板、强化等级清零并返还强化花费（VIP 11 以下为售价 - 模板售价）
        $fab = $this->request($p['auth'], 'equip_forge', 'fabrication', ['id' => $id, 'newequipid' => 57]);
        $this->assertSame([1, $id, 1000, 1000, 0, 2000, 57, 6], [$fab['status'], $fab['ownid'], $fab['retsilver'], $fab['allsilver'], $fab['newquality_info']['qualityid'], $fab['newquality_info']['attr'], $fab['newquality_info']['id'], $fab['newquality_info']['quality']]);
        $equip = DB::table('equipment')->where('id', $id)->first();
        $this->assertSame([57, '八荒六合戟', 6, 2000, 2000, 0, 0, 0, 500000], [(int)$equip['equipid'], $equip['name'], (int)$equip['quality'], (int)$equip['power'], (int)$equip['powerup'], (int)$equip['level'], (int)$equip['newquality'], (int)$equip['oldequipid'], (int)$equip['saleprice']]);
        $this->assertSame([0, 0], [(int)DB::table('own_materials')->where('uid', $uid)->where('materials_id', 40)->value('num'), (int)DB::table('own_materials')->where('uid', $uid)->where('materials_id', 15)->value('num')]);
        $this->assertSame(2, DB::table('own_materials_log')->where('uid', $uid)->where('class', 'equip_forge')->where('method', 'fabrication')->count());
        $this->assertSame([1, 22, 57, 0], array_map('intval', array_values(DB::table('newequip_log')->select('type', 'before_equipid', 'after_equipid', 'after_quality')->where('uid', $uid)->first())));
        $this->assertSame(-7, $this->request($p['auth'], 'equip_forge', 'fabrication', ['id' => $id, 'newequipid' => 81])['status'], '材料不足');

        // 卖材料
        $row = DB::table('own_materials')->where('uid', $uid)->where('materials_id', 40)->first();
        $this->assertSame(-5, $this->request($p['auth'], 'equip_forge', 'sell', ['id' => $row['id'], 'num' => 1])['status']);
        self::material($uid, 40, 65);
        $sell = $this->request($p['auth'], 'equip_forge', 'sell', ['id' => $row['id'], 'num' => 5]);
        $this->assertSame(['status' => 1, 'silver' => 1000 + 5 * 5000], $sell);
        $this->assertSame(60, (int)DB::table('own_materials')->where('id', $row['id'])->value('num'));
        Redis::set('forge_lk_sell' . $uid, 1, 10);
        $this->assertSame(['status' => -2, 'msg' => '未知错误'], $this->request($p['auth'], 'equip_forge', 'sell', ['id' => $row['id'], 'num' => 1]));
        $this->assertSame(1, $this->request($p['auth'], 'equip_forge', 'sell', ['id' => $row['id'], 'num' => 0])['status'] === -3 ? 1 : 0);

        // 锻造红装：按品阶权重随机品阶与属性
        self::material($uid, 9, 50);
        self::material($uid, 15, 30);
        self::material($uid, 21, 15);
        $fab = $this->request($p['auth'], 'equip_forge', 'fabrication', ['id' => $id, 'newequipid' => 81]);
        $this->assertSame(1, $fab['status']);
        $this->assertGreaterThanOrEqual(1, $fab['newquality_info']['qualityid']);
        $equip = DB::table('equipment')->where('id', $id)->first();
        $this->assertSame([81, 57, $fab['newquality_info']['qualityid'], $fab['newquality_info']['attr']], [(int)$equip['equipid'], (int)$equip['oldequipid'], (int)$equip['newquality'], (int)$equip['power']]);
        $this->assertGreaterThan(4000, (int)$equip['power']);

        // 套装与激活属性
        $suit = $this->request($p['auth'], 'equip_forge', 'get_suit_attr', ['npid' => 81]);
        $this->assertSame([1, '奔雷套装', '奔雷戟', 20, 25], [$suit['status'], $suit['suit_attr']['name'], $suit['suit_attr']['name1'], $suit['suit_attr']['params3_1'], $suit['suit_attr']['params4_2']]);
        $this->assertSame(-3, $this->request($p['auth'], 'equip_forge', 'get_suit_attr', ['npid' => 22])['status']);
        $attr = $this->request($p['auth'], 'equip_forge', 'get_equip_attr', ['id' => $id]);
        $this->assertSame([1, 0, '', (int)$equip['power']], [$attr['status'], $attr['equip_info']['open'], $attr['equip_info']['gname'], $attr['equip_info']['allpower']]);
        $this->assertArrayNotHasKey('aid', $attr['equip_info']);

        // 分解：返还普通材料 × 0.5、神铸材料（× castach）与 80% 售价
        $list = $this->request($p['auth'], 'equip_forge', 'resolve_index');
        $this->assertSame([1, $id, 400000], [$list['status'], $list['equiplist'][0]['id'], $list['equiplist'][0]['saleprice']]);
        $this->assertSame([9, 43], array_column($list['equiplist'][0]['getgod'], 'godcast'));
        $silver = self::memberValue($uid, 'silver');
        $res = $this->request($p['auth'], 'equip_forge', 'resolve', ['id' => $id]);
        $this->assertSame([1, 400000, $silver + 400000], [$res['status'], $res['ret_resoure']['silver'], $res['ret_resoure']['allsilver']]);
        $this->assertSame([9, 43], array_column($res['get_materials'], 'materials_id'));
        $this->assertNotNull(DB::table('equipment')->where('id', $id)->value('deleted_at'));
        $this->assertSame(['status' => -2, 'msg' => '装备不存在'], $this->request($p['auth'], 'equip_forge', 'resolve', ['id' => $id]));
        $this->assertSame(['status' => -2, 'msg' => '没有可分解的装备(红色品质以上装备)'], $this->request($p['auth'], 'equip_forge', 'resolve_index'));
    }

    public function testStrengthenAchievementUnlockAndRevert(): void
    {
        $p = $this->player(['level' => 60]);
        $uid = $p['uid'];
        $id = Equipment::addFromTemplate($uid, 22)[0];
        Equipment::addTowerItem($uid, 1, 6);

        $ach = $this->request($p['auth'], 'strengthen', 'achievement');
        $this->assertSame([1, $id, 1, 0], [$ach['status'], $ach['itemInfo']['id'], count($ach['itemList']), $ach['achieveList'][0]['lock']]);
        $this->assertSame([1, 2, 6], [$ach['achieveList'][0]['item']['id'], $ach['achieveList'][0]['item']['num'], $ach['achieveList'][0]['item']['all_num']]);
        $this->assertSame([], $this->request($p['auth'], 'strengthen', 'achievement', ['id' => $id])['itemList']);

        $this->assertSame(2, $this->request($p['auth'], 'strengthen', 'achieve_unlock', ['id' => $id])['status']);
        $this->assertSame(6, $this->request($p['auth'], 'strengthen', 'achieve_unlock', ['id' => $id, 'aid' => 2])['status'], '按顺序解锁');
        $this->assertSame(['status' => 1, 'm_num' => 4], $this->request($p['auth'], 'strengthen', 'achieve_unlock', ['id' => $id, 'aid' => 1]));
        $this->assertSame(4, $this->request($p['auth'], 'strengthen', 'achieve_unlock', ['id' => $id, 'aid' => 1])['status']);
        $this->assertSame(['status' => 1, 'm_num' => 1], $this->request($p['auth'], 'strengthen', 'achieve_unlock', ['id' => $id, 'aid' => 2]));
        $this->assertSame('1,2', DB::table('equipment')->where('id', $id)->value('aid'));
        $short = $this->request($p['auth'], 'strengthen', 'achieve_unlock', ['id' => $id, 'aid' => 3]);
        $this->assertSame([5, ['quality' => 1, 'name' => '武器精华']], [$short['status'], $short['essInfo']]);

        $revert = $this->request($p['auth'], 'strengthen', 'revert_achieve', ['id' => $id]);
        $this->assertSame([1, 1, 5], [$revert['status'], count($revert['jinghua']), $revert['jinghua'][0]['value']]);
        $this->assertSame('', DB::table('equipment')->where('id', $id)->value('aid'));
        $this->assertSame(6, (int)Equipment::towerItem($uid, 1)['number']);
        $this->assertSame(1, DB::table('revert_achieve_log')->where('uid', $uid)->where('equipid', $id)->where('aid', '1,2')->count());
        $this->assertSame(-3, $this->request($p['auth'], 'strengthen', 'revert_achieve', ['id' => $id])['status']);
    }

    // ---------------------------------------------------------------------------------------------
    // 背包使用物品 / ios / 临时 token / 分享战报
    // ---------------------------------------------------------------------------------------------

    public function testPackUseWorldItem(): void
    {
        $p = $this->player(['level' => 50, 'silver' => 0]);
        $uid = $p['uid'];
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'pack', 'use_item', ['id' => 1]));
        $id = (int)DB::table('equipment')->insert(['uid' => $uid, 'equipid' => 1, 'name' => WorldShop::ROWS[1]['name'], 'type' => 11, 'quality' => 1, 'number' => 2]);
        $reward = WorldShop::ROWS[1]['add1'] * 50;
        $this->assertSame(['status' => 1, 'info' => ['type' => 1, 'reward' => $reward]], $this->request($p['auth'], 'pack', 'use_item', ['id' => $id]), '只预览');
        $this->assertSame(['status' => 1, 'silver' => $reward, 'act' => self::memberValue($uid, 'act'), 'number' => 1, 'info' => ['type' => 1, 'reward' => $reward]],
            $this->request($p['auth'], 'pack', 'use_item', ['id' => $id, 'confirm' => 1]));
        $this->assertSame(0, $this->request($p['auth'], 'pack', 'use_item', ['id' => $id, 'confirm' => 1])['number']);
        $this->assertNotNull(DB::table('equipment')->where('id', $id)->value('deleted_at'));
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'pack', 'use_item', ['id' => $id, 'confirm' => 1]));
        DB::table('equipment')->insert(['uid' => $uid, 'equipid' => 99, 'name' => 'x', 'type' => 12, 'quality' => 1, 'number' => 1]);
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'pack', 'use_item', ['id' => DB::table('equipment')->where('uid', $uid)->where('equipid', 99)->value('id')]));
    }

    public function testIosSavetokenVerifyAndTemptoken(): void
    {
        $p = $this->player();
        $uid = $p['uid'];
        $this->assertSame(['status' => 1], $this->request($p['auth'], 'ios', 'savetoken', ['devicetoken' => 'abc123']));
        $this->assertSame('abc123', DB::table('member_field')->where('uid', $uid)->value('devicetoken'));
        $this->assertSame(['status' => 0], $this->request($p['auth'], 'ios', 'savetoken', ['devicetoken' => 'abc123']), '没有修改');
        $this->assertSame(['status' => 0], $this->request(['token' => '', 'uid' => ''], 'ios', 'savetoken', ['devicetoken' => 'zzz']), '未登录');
        $this->assertSame('403', $this->raw($p['auth'], 'ios', 'savetoken', ['devicetoken' => '1da0b3146c30b11afae33940cd58e896c8d678cbc46d9245cbd900ef29f4961d']), '封禁设备');
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'ios', 'verify'));
        $this->assertSame(['status' => 0], $this->request($p['auth'], 'ios', 'verify', ['receipt' => 'r']), '待定渠道：不到账');

        $info = $this->request(['token' => '', 'uid' => ''], 'user', 'get_info_by_temptoken', ['temptoken' => $p['token']]);
        $this->assertSame(['status' => 1, 'uid' => $uid, 'username' => '', 'token' => $p['token'], 'servername' => '1区-桃园结义'], $info);
        $this->assertSame(['status' => 0], $this->request(['token' => '', 'uid' => ''], 'user', 'get_info_by_temptoken', ['temptoken' => 'nope']));
        $this->assertSame(['status' => 0], $this->request(['token' => '', 'uid' => ''], 'user', 'get_info_by_temptoken'));
    }

    public function testReportSave(): void
    {
        $p = $this->player();
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'report', 'save', ['type' => 'arena', 'id' => 999999999]));
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'report', 'save', ['type' => 'god', 'id' => '1_2']), '没有这一局的战报');
        $id = 900000000 + $p['uid'];
        DB::table('pm_report')->insert(['id' => $id, 'report' => '{"assess":1}', 'nickname' => 'a', 'userlevel' => 1, 'name' => 'b', 'level' => 1]);
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'report', 'save', ['type' => 'arena', 'id' => $id]), '没有配置展示页地址（report_url），见 Cross1Test');
    }

    // ---------------------------------------------------------------------------------------------
    // 评分奖励 / 试玩绑定 / 修改密码
    // ---------------------------------------------------------------------------------------------

    public function testClickreward(): void
    {
        $p = $this->player(['level' => 30, 'gold' => 0]);
        $uid = $p['uid'];
        $this->assertSame(['status' => -3], $this->request($p['auth'], 'clickreward', 'index'));
        DB::table('member')->where('uid', $uid)->update(['level' => 31]);
        Redis::set('click_reward_' . $uid, 1, 600);
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'clickreward', 'index'));
        Redis::del('click_reward_' . $uid);
        $this->assertSame(['status' => 1, 'add_gold' => 50, 'all_gold' => 50], $this->request($p['auth'], 'clickreward', 'index'));
        $this->assertSame(strtotime(date('Y-m-d')), self::fieldValue($uid, 'clickreward'));
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'clickreward', 'index'), '已领取');
    }

    public function testGuestBindingRegrewardAndPassword(): void
    {
        $p = $this->player(['gold' => 0]);
        $uid = $p['uid'];
        $other = $this->player();
        $this->assertSame(1, $this->request($p['auth'], 'member', 'index')['guest']);
        $this->assertSame(['status' => -2, 'msg' => '帐号未绑定，无法领取'], $this->request($p['auth'], 'regreward', 'get_reward'));

        $name = 'd5u' . $uid;
        $bind = fn (array $params): array => $this->request($p['auth'], 'register', 'binding', $params + ['uid' => $uid]);
        $this->assertSame(-3, $bind(['u' => 'a!', 'p' => 'pass123'])['status']);
        $this->assertSame(-4, $bind(['u' => $name, 'p' => '12'])['status']);
        DB::table('user')->where('uid', $other['uid'])->update(['username' => 'd5taken' . $uid]);
        $this->assertSame(-5, $bind(['u' => 'd5taken' . $uid, 'p' => 'pass123'])['status']);
        $this->assertSame(-1, $this->request($p['auth'], 'register', 'binding', ['u' => $name, 'p' => 'pass123', 'uid' => $other['uid']])['status'], '不是本人的帐号');
        $ok = $bind(['u' => $name, 'p' => 'pass123']);
        $this->assertSame([1, '用户绑定成功', $uid, 1], [$ok['status'], $ok['message'] ?? '用户绑定成功', $ok['uid'], count($ok['serverlist'])]);
        $this->assertSame([0, $name], [(int)DB::table('user')->where('uid', $uid)->value('is_guest'), DB::table('user')->where('uid', $uid)->value('username')]);
        $this->assertSame(-2, $bind(['u' => $name . 'x', 'p' => 'pass123'])['status'], '已绑定过');

        // 绑定后领 50 元宝，只能领一次
        $this->assertSame(['status' => 1, 'msg' => '', 'gold' => 50, 'all_gold' => 50], $this->request($p['auth'], 'regreward', 'get_reward'));
        $this->assertSame(2, self::memberValue($uid, 'guest'));
        $this->assertSame(-1, $this->request($p['auth'], 'regreward', 'get_reward')['status']);

        // 修改密码
        $this->assertSame(-1, $this->request($p['auth'], 'password', 'set_pwd', ['oldpwd' => 'pass123', 'newpwd' => '123'])['status']);
        $this->assertSame(-2, $this->request($p['auth'], 'password', 'set_pwd', ['oldpwd' => 'pass123', 'newpwd' => 'bad pass'])['status']);
        $this->assertSame(['status' => -3, 'msg' => '原始密码不正确'], $this->request($p['auth'], 'password', 'set_pwd', ['oldpwd' => 'wrong1', 'newpwd' => 'newpass1']));
        $this->assertSame(['status' => 1, 'msg' => '密码修改成功，请保管好你的新密码！'], $this->request($p['auth'], 'password', 'set_pwd', ['oldpwd' => 'pass123', 'newpwd' => 'newpass1']));
        $this->assertSame(1, $this->request(['token' => '', 'uid' => ''], 'login', 'user', ['u' => $name, 'p' => 'newpass1'])['status']);
        $this->assertSame(-3, $this->request($other['auth'], 'password', 'set_pwd', ['oldpwd' => 'whatever', 'newpwd' => 'newpass1'])['status'], '试玩帐号没有密码');
    }

    // ---------------------------------------------------------------------------------------------
    // 注册前 3 日奖励 / 新服大礼包 / 老版主线礼包
    // ---------------------------------------------------------------------------------------------

    public function testNewrolereward(): void
    {
        $p = $this->player(['silver' => 0, 'gold' => 0]);
        $uid = $p['uid'];
        $today = strtotime(date('Y-m-d'));
        DB::table('member_field')->where('uid', $uid)->update(['start_time' => $today - 86400 + 3600]);
        $index = $this->request($p['auth'], 'newrolereward', 'index');
        $this->assertSame([1, [3, 1, 0], [0, 0, $today + 86400 - time()]], [$index['status'], array_column($index['list'], 'open'), array_column($index['list'], 'endtime')]);
        $get = $this->request($p['auth'], 'newrolereward', 'get_reward');
        $this->assertSame([1, Newrolereward::ROWS[2]], [$get['status'], $get['reward']]);
        $this->assertSame(Newrolereward::ROWS[2]['value1'], TraderLog::jade($uid, 1), '蓝宝石');
        $this->assertSame(Newrolereward::ROWS[2]['value2'], $get['all_gold']);
        $this->assertSame(['status' => -3], $this->request($p['auth'], 'newrolereward', 'get_reward'));
        $this->assertSame([3, 2, 0], array_column($this->request($p['auth'], 'newrolereward', 'index')['list'], 'open'));
        DB::table('member_field')->where('uid', $uid)->update(['start_time' => $today - 3 * 86400]);
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'newrolereward', 'index'));
    }

    public function testNewgiftDailyUnlockRewardMemberInfoAndCron(): void
    {
        $now = time();
        // 注册时间取当天 0 点（handler 直接用 time()，不能冻结）：原先的 now - 3600 在北京时间 0~1 点落到前一天，
        // 活动变成第 2 天，断言随时刻变化
        $jointime = (int)strtotime(date('Ymd', $now));
        $p = $this->player(['jointime' => $jointime, 'silver' => 0, 'gold' => 0]);
        $uid = $p['uid'];
        $member = $this->request($p['auth'], 'member', 'index');
        $this->assertSame([1, 1, $jointime + 36000], [$member['newgift'], $member['newgift_popo'], $member['newgift_starttime']]);
        $this->assertCount(4, $member['newgift_msg']);

        $index = $this->request($p['auth'], 'newgift', 'index');
        $this->assertSame([1, 1, count(NewgiftReward::ROWS)], [$index['status'], $index['popo'], count($index['list'])]);
        $this->assertSame([1, 2], [$index['list'][0]['type'], $index['list'][1]['type']]);
        $this->assertSame(30, DB::table('newgift_log')->where('uid', $uid)->count());
        $this->assertSame(['status' => -2, 'popo' => 1, 'msg' => '您不能重复领取奖励'], $this->request($p['auth'], 'newgift', 'get_reward', ['id' => 2]), '未达成');
        $this->assertSame(['status' => -1, 'popo' => 1, 'msg' => '传递参数失败'], $this->request($p['auth'], 'newgift', 'get_reward', ['id' => 31]));
        $get = $this->request($p['auth'], 'newgift', 'get_reward', ['id' => 1]);
        $r = NewgiftReward::ROWS[1];
        $this->assertSame([1, 0, $r['getvalue1'], $r['getvalue2'], 3], [$get['status'], $get['popo'], $get['all_silver'], $get['all_gold'], $get['list'][0]['type']]);
        $this->assertSame($r['getvalue3'], TraderLog::jade($uid, 2));
        $this->assertSame(-2, $this->request($p['auth'], 'newgift', 'get_reward', ['id' => 1])['status']);
        Redis::set('newgift_lock_' . $uid, 1, 60);
        $this->assertSame(-7, $this->request($p['auth'], 'newgift', 'get_reward', ['id' => 1])['status']);
        Redis::del('newgift_lock_' . $uid);

        // 活动结束（注册 31 天后）：界面回 2，计划任务自动领取可领取的礼包并发信件
        DB::table('newgift_log')->where('uid', $uid)->where('day', 2)->update(['status' => 1]);
        DB::table('member')->where('uid', $uid)->update(['jointime' => $now - 31 * 86400]);
        $this->assertSame(2, $this->request($p['auth'], 'newgift', 'index')['status']);
        $silver = self::memberValue($uid, 'silver');
        $this->assertGreaterThanOrEqual(1, NewgiftCron::run($now));
        $this->assertSame($silver + NewgiftReward::ROWS[2]['getvalue1'], self::memberValue($uid, 'silver'));
        $this->assertSame(3, (int)DB::table('newgift_log')->where('uid', $uid)->where('day', 2)->value('status'));
        $this->assertSame(1, DB::table('pm')->where('touid', $uid)->where('message', 'like', '新服大礼包活动已结束%')->count());
        $this->assertSame(0, $this->request($p['auth'], 'member', 'index')['newgift']);
    }

    public function testMemberOpenGiftAndIndexGift(): void
    {
        $p = $this->player(['level' => 10, 'silver' => 0]);
        $uid = $p['uid'];
        $gift = $this->request($p['auth'], 'member', 'index')['gift'];
        $this->assertSame([1, 0], [$gift['id'], $gift['open']]);
        $this->assertSame(['status' => -2, 'next' => 0], $this->request($p['auth'], 'member', 'open_gift'), '没打过周仓');
        $m = Mission::ROWS[LevelGift::ROWS[1]['cond_value']];
        DB::table('mission_log')->insert(['uid' => $uid, 'missionlevel' => $m['missionlevel'], 'missionstage' => $m['missionstage'], 'missionid' => $m['missionid']]);
        $this->assertSame(1, $this->request($p['auth'], 'member', 'index')['gift']['open']);
        $open = $this->request($p['auth'], 'member', 'open_gift');
        $this->assertSame([1, LevelGift::ROWS[1]['num'], 2, 0], [$open['status'], $open['all_silver'], $open['next']['id'], $open['next']['open']]);
        $this->assertSame([1, 2], [self::memberValue($uid, 'gift'), $this->request($p['auth'], 'member', 'index')['gift']['id']]);
        DB::table('member')->where('uid', $uid)->update(['level' => 25]);
        Redis::del('player:' . $uid);
        $this->assertSame(0, $this->request($p['auth'], 'member', 'index')['gift']['id'], '25 级以上');
    }
}
