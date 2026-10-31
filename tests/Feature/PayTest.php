<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\MasterData\NewyearVipreward;
use Hanjiang\MasterData\VipWage;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\General;
use Hanjiang\Model\Pay;
use Hanjiang\Model\Rebate;
use Hanjiang\Model\TraderLog;
use Hanjiang\Model\Vip;
use Hanjiang\Tests\Support\Gateway;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use PHPUnit\Framework\TestCase;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * 充值到账核心（Model\Pay::complete）与 VIP 经济：充值商品（c=pay）、VIP（c=vip）、VIP 回馈（c=vipreward）、
 * VIP 工资（c=vipwage）、首充（c=first_recharge，经充值核心到账）、大熊跳楼返利（c=rebate）
 */
final class PayTest extends TestCase
{
    protected function tearDown(): void
    {
        BossClock::freeze(null);
        putenv('PAY_TEST');
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
     * @param array<string, int> $member
     * @return array{uid: int, auth: array<string, mixed>}
     */
    private function player(array $member = []): array
    {
        $reg = $this->request(['token' => '', 'uid' => ''], 'register', 'guest');
        $uid = (int)$reg['uid'];
        $login = $this->request(['token' => '', 'uid' => $uid], 'login', 'guest', ['guestkey' => $reg['guestkey']]);
        if ($member !== []) {
            DB::table('member')->where('uid', $uid)->update($member);
        }
        return ['uid' => $uid, 'auth' => ['token' => (string)$login['token'], 'uid' => $uid]];
    }

    private static function memberValue(int $uid, string $column): int
    {
        return (int)DB::table('member')->where('uid', $uid)->value($column);
    }

    private static function fieldValue(int $uid, string $column): int
    {
        return (int)DB::table('member_field')->where('uid', $uid)->value($column);
    }

    private static function orderId(string $tag): string
    {
        return 'test_' . $tag . '_' . substr((string)hrtime(true), -9);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function mails(int $uid): array
    {
        // 信件存 pm 表（Model\Message）
        return array_map(static fn (array $m): array => array_map(static fn ($v) => is_string($v) && preg_match('/^-?\d+$/', $v) ? (int)$v : $v, $m), DB::table('pm')->where('touid', $uid)->whereNull('deleted_at')->orderBy('id')->get());
    }

    public function testCompleteCreditsOnceAndRaisesVip(): void
    {
        $p = $this->player(['gold' => 0]);
        $uid = $p['uid'];
        DB::table('member_field')->where('uid', $uid)->update(['attackstatus' => 1, 'attackdateline' => time() + 600]);
        $this->assertSame(0, self::memberValue($uid, 'vip'), '新玩家 VIP 0');

        // 商品列表：返利活动未开启，取 type 1 正常商品；每个档次未充过时带「限购1次」赠送说明
        $list = $this->request($p['auth'], 'pay', 'get_product_list');
        $this->assertSame([1, 0], [$list['status'], $list['cd']]);
        $this->assertSame([1, 2, 3, 4, 5, 6], array_column($list['list'], 'id'));
        $this->assertSame('赠送60元宝(限购1次)', $list['list'][0]['info']);
        $this->assertSame(['60元宝', 6, 60, 0, 1], [$list['list'][0]['subject'], $list['list'][0]['amount'], $list['list'][0]['gold'], $list['list'][0]['add_gold'], $list['list'][0]['type']]);

        // 参数 / 玩家 / 商品 / 金额校验
        $this->assertSame(['status' => -1], Pay::complete($uid, 1, '', 'test', 6));
        $this->assertSame(['status' => -1], Pay::complete($uid, 1, str_repeat('x', 51), 'test', 6));
        $this->assertSame(['status' => -4], Pay::complete(999999999, 1, self::orderId('nouser'), 'test', 6));
        $this->assertSame(['status' => 3], Pay::complete($uid, 99, self::orderId('noproduct'), 'test', 6));
        $this->assertSame(['status' => 7], Pay::complete($uid, 1, self::orderId('amount'), 'test', 5));
        $this->assertSame(0, DB::table('pay_order')->where('uid', $uid)->count());

        // 首次充值 6 元：60 元宝，VIP 6（Vip 表 toplimit：VIP1~9 为 1~9 元），首充状态 1
        $order = self::orderId('first');
        $res = Pay::complete($uid, 1, $order, 'test', 6);
        $this->assertSame(['status' => 1, 'gold' => 60, 'add_gold' => 0, 'vip' => 6, 'viptype' => 1], $res);
        $this->assertSame([60, 6], [self::memberValue($uid, 'gold'), self::memberValue($uid, 'vip')]);
        $log = Vip::log($uid);
        $this->assertSame([6, 6, 0, 1], [$log['money'], $log['level'], $log['gift_num'], $log['type']]);
        // 首充日志 60 元宝，首充图标 2（充值 >= 6 元）；档次 1 首充记录（此时还没有首充日志，不另送）
        $this->assertSame(60, (int)DB::table('first_recharge_log')->where('uid', $uid)->value('gold'));
        $this->assertSame(2, self::fieldValue($uid, 'first_recharge'));
        $this->assertSame([1], array_keys(Vip::rechargeLog($uid)));
        // VIP 3 以上清空攻击 cd
        $this->assertSame([0, 0], [self::fieldValue($uid, 'attackstatus'), self::fieldValue($uid, 'attackdateline')]);
        // 订单与流水
        $row = DB::table('pay_order')->where('orderid', $order)->first();
        $this->assertSame([$uid, 0, 60, 1, 6, 60, 1, 'test'], [(int)$row['uid'], (int)$row['pay_befor'], (int)$row['pay_after'], (int)$row['type'], (int)$row['amount'], (int)$row['gold'], (int)$row['status'], $row['paychannel']]);
        $payLog = DB::table('pay_log')->where('orderid', $order)->first();
        $this->assertSame([6, 6, 1, 'test'], [(int)$payLog['money'], (int)$payLog['vip'], (int)$payLog['first'], $payLog['paytype']]);
        // 充值信件（type 2，发件人 vip通知）
        $mail = self::mails($uid)[0];
        $this->assertSame([2, 1000, 'vip通知', '恭喜您成功充值60元宝,VIP等级提升到了【VIP6】,祝您游戏愉快！'], [$mail['type'], $mail['fromuid'], $mail['fromname'], $mail['message']]);

        // 同一订单重复回调：不再到账
        $this->assertSame(['status' => 6], Pay::complete($uid, 1, $order, 'test', 6));
        $other = $this->player();
        $this->assertSame(['status' => 6], Pay::complete($other['uid'], 1, $order, 'test', 6), '订单号全局唯一');
        $this->assertSame(60, self::memberValue($uid, 'gold'));
        $this->assertSame(0, self::memberValue($other['uid'], 'gold') - 9999);
        $this->assertSame(1, DB::table('pay_order')->where('orderid', $order)->count());
        $this->assertSame(6, Vip::log($uid)['money']);

        // 商品列表：档次 1 已充过，不再显示赠送说明
        $list = $this->request($p['auth'], 'pay', 'get_product_list');
        $this->assertSame(['', '赠送300元宝(限购1次)'], [$list['list'][0]['info'], $list['list'][1]['info']]);

        // 第二次充值 30 元：档次 2 第一次充值且已有首充日志 → 另送 300 元宝；累计 36 元 → VIP 9
        $res = Pay::complete($uid, 2, self::orderId('second'), 'test', 30);
        $this->assertSame(['status' => 1, 'gold' => 300, 'add_gold' => 0, 'vip' => 9, 'viptype' => 0], $res);
        $this->assertSame(60 + 300 + 300, self::memberValue($uid, 'gold'));
        $this->assertSame([36, 9, 1], [Vip::log($uid)['money'], Vip::log($uid)['level'], Vip::log($uid)['type']]);
        $this->assertSame(60, (int)DB::table('first_recharge_log')->where('uid', $uid)->value('gold'), '首充日志只记第一次');
        $this->assertSame('恭喜您成功充值300元宝,另赠送您300元宝,VIP等级提升到了【VIP9】,祝您游戏愉快！', self::mails($uid)[1]['message']);

        // 同档次再充：不再另送；VIP 不变时信件不提升级
        $res = Pay::complete($uid, 2, self::orderId('third'), 'test', 30);
        $this->assertSame([1, 300, 9], [$res['status'], $res['gold'], $res['vip']]);
        $this->assertSame(660 + 300, self::memberValue($uid, 'gold'));
        $this->assertSame('恭喜您成功充值300元宝,祝您游戏愉快！', self::mails($uid)[2]['message']);
        $this->assertSame(0, (int)DB::table('pay_log')->where('uid', $uid)->orderBy('id', 'desc')->value('first'));
    }

    public function testConcurrentCallbacksCreditOnce(): void
    {
        $run = function (array $jobs): array {
            $workers = [];
            foreach ($jobs as [$uid, $orders]) {
                $cmd = [PHP_BINARY, dirname(__DIR__) . '/Support/pay_worker.php', (string)$uid, '1', '6', implode(',', $orders)];
                $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $this->assertIsResource($proc);
                $workers[] = [$proc, $pipes];
            }
            $status = [];
            foreach ($workers as [$proc, $pipes]) {
                $out = trim((string)stream_get_contents($pipes[1]));
                $err = trim((string)stream_get_contents($pipes[2]));
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($proc), $err);
                // 输出最后一行是状态码（前面可能有 Redis 连接日志）
                $lines = preg_split('/\R/', $out) ?: [''];
                array_push($status, ...array_map('intval', explode(',', (string)end($lines))));
            }
            sort($status);
            return $status;
        };

        // 同一订单的 6 个并发回调：只到账一次
        $p = $this->player(['gold' => 0]);
        $order = self::orderId('race');
        $this->assertSame([1, 6, 6, 6, 6, 6], $run(array_fill(0, 6, [$p['uid'], [$order]])));
        $this->assertSame([60, 6], [self::memberValue($p['uid'], 'gold'), Vip::log($p['uid'])['money']]);
        $this->assertSame(1, DB::table('pay_log')->where('orderid', $order)->count());

        // 同一订单号同时打到不同玩家：只有一个玩家到账
        $uids = [$this->player()['uid'], $this->player()['uid'], $this->player()['uid']];
        $order = self::orderId('race2');
        $this->assertSame([1, 6, 6], $run(array_map(static fn (int $uid): array => [$uid, [$order]], $uids)));
        $this->assertSame(1, DB::table('vip_log')->whereIn('uid', $uids)->count());

        // 同一玩家 4 个进程各 5 笔不同订单：累计金额与元宝不丢
        $q = $this->player(['gold' => 0]);
        $jobs = [];
        for ($i = 0; $i < 4; $i++) {
            $jobs[] = [$q['uid'], array_map(static fn (int $n): string => self::orderId("multi{$i}_{$n}"), range(1, 5))];
        }
        $this->assertSame(array_fill(0, 20, 1), $run($jobs));
        $this->assertSame([120, 20 * 60], [Vip::log($q['uid'])['money'], self::memberValue($q['uid'], 'gold')]);
        $this->assertSame(1, (int)DB::table('pay_log')->where('uid', $q['uid'])->sum('first'), '只有第一笔记首充');
    }

    public function testVipLevelThresholds(): void
    {
        // Vip 表 toplimit（人民币）：VIP1~9 为 1~9 元，VIP10 5000，VIP11 50000 …… VIP20 7200000
        $this->assertSame(0, Vip::rank(0));
        $this->assertSame(1, Vip::rank(1));
        $this->assertSame(5, Vip::rank(5));
        $this->assertSame(9, Vip::rank(4999));
        $this->assertSame(10, Vip::rank(5000));
        $this->assertSame(10, Vip::rank(49999));
        $this->assertSame(11, Vip::rank(50000));
        $this->assertSame(19, Vip::rank(7199999));
        $this->assertSame(20, Vip::rank(7200000));
        $this->assertSame(20, Vip::rank(99999999));

        // 累计跨过 VIP 10：648 元 × 8 = 5184 元
        $p = $this->player();
        $uid = $p['uid'];
        for ($i = 1; $i <= 8; $i++) {
            $res = Pay::complete($uid, 6, self::orderId('vip10_' . $i), 'test', 648);
            $this->assertSame(1, $res['status']);
        }
        $this->assertSame([10, 10], [$res['vip'], self::memberValue($uid, 'vip')]);
        $this->assertSame([5184, 10], [Vip::log($uid)['money'], Vip::log($uid)['level']]);
        $index = $this->request($p['auth'], 'vip', 'index');
        $this->assertSame([10, 51840, 500000, 500000 - 51840, 11], [$index['level'], $index['exp'], $index['top_exp'], $index['diff'], $index['next_level']]);
        // 军令上限按 VIP（旧版 get_act_limit：VIP 5 以上 100）
        $this->assertSame(100, $this->request($p['auth'], 'member', 'index')['act_limit']);
    }

    public function testFirstRechargeRewardAfterPay(): void
    {
        $p = $this->player(['gold' => 0]);
        $uid = $p['uid'];
        $this->assertSame(2, $this->request($p['auth'], 'first_recharge', 'get_reward', ['id' => 5])['status'], '没有充值记录');

        // 首次充值 30 元 → 首充 300 元宝，可领 300 档（id 5）
        $this->assertSame(1, Pay::complete($uid, 2, self::orderId('fr'), 'test', 30)['status']);
        $this->assertSame([0, 0, 0, 0, 1, 0], array_column($this->request($p['auth'], 'first_recharge', 'index')['list'], 'status'));
        $gold = self::memberValue($uid, 'gold');
        $silver = self::memberValue($uid, 'silver');
        $reward = $this->request($p['auth'], 'first_recharge', 'get_reward', ['id' => 5]);
        $this->assertSame(1, $reward['status']);
        // 300 档：元宝 300、银币 800000、突飞卡 100、黄宝石 100
        $this->assertSame([300, $gold + 300, 800000, $silver + 800000], [$reward['info']['gold'], $reward['info']['all_gold'], $reward['info']['silver'], $reward['info']['all_silver']]);
        $this->assertSame([2, 100], [$reward['info']['jadetype'], $reward['info']['jadenum']]);
        $this->assertSame(100, TraderLog::jade($uid, 2));
        $this->assertSame(3, $this->request($p['auth'], 'first_recharge', 'get_reward', ['id' => 5])['status'], '已领取');
        // 不是最高档：首充图标不再显示
        $this->assertSame(0, self::fieldValue($uid, 'first_recharge'));

        // 锁：领取锁存在时返回 6（操作太频繁）
        Redis::set('first_reward_lock_' . $uid, '1', 30);
        $this->assertSame(6, $this->request($p['auth'], 'first_recharge', 'get_reward', ['id' => 5])['status']);
        Redis::del('first_reward_lock_' . $uid);

        // VIP 首充奖励接口：旧版开头直接返回 0
        $this->assertSame(['status' => 0], $this->request($p['auth'], 'vip', 'get_vip_reward'));
        $this->assertSame(['status' => 0], $this->request($p['auth'], 'vip', 'get_vip_reward', ['t' => 1]));
    }

    public function testVipIndexAndGiftGates(): void
    {
        $p = $this->player();
        $uid = $p['uid'];

        // 没有 vip 记录：VIP 0，下一级 VIP 1 需 10 元宝；所有礼包不可领
        $index = $this->request($p['auth'], 'vip', 'index');
        $this->assertSame([1, 0, 0, 10, 10, 1], [$index['status'], $index['level'], $index['exp'], $index['top_exp'], $index['diff'], $index['next_level']]);
        $this->assertCount(20, $index['list']);
        $this->assertSame([0], array_values(array_unique(array_column($index['list'], 'status'))));
        $this->assertSame('1.训练位4:使用元宝购买一个额外的武将训练位<br />2.采集位4:使用元宝购买一个额外的玉石采集位<br />'
            . '3.加强训练位:永久获得150%武将训练效果<br />4.加强采集位:永久获得150%玉石采集效果<br />'
            . '5.加强继承:开启加强继承功能<br />6.军令上限增加到60个<br />7.可在商店购买绿色兵符<br />', $index['list'][1]['left']);
        // VIP 1 礼包：紫宝石 1000、蓝宝石 5000、突飞卡 1000
        $this->assertSame([
            ['gettype' => 2, 'jadetype' => 3, 'jadenum' => 1000, 'generalname' => ''],
            ['gettype' => 2, 'jadetype' => 1, 'jadenum' => 5000, 'generalname' => ''],
            ['gettype' => 7, 'getvalue' => 1000],
        ], $index['list'][1]['right']);
        $this->assertStringContainsString('至尊攻城', $index['list'][7]['left']);
        $this->assertSame(4, $index['list'][5]['right'][0]['gettype'], 'VIP 5 礼包武将');
        $this->assertSame(-1, $this->request($p['auth'], 'vip', 'get_gift', ['l' => 1])['status'], '没有 vip 记录');

        // 充值 6 元 → VIP 6
        Pay::complete($uid, 1, self::orderId('gift'), 'test', 6);
        $index = $this->request($p['auth'], 'vip', 'index');
        $this->assertSame([6, 60, 70, 10, 7], [$index['level'], $index['exp'], $index['top_exp'], $index['diff'], $index['next_level']]);
        $this->assertSame([1, 0, 0], [$index['list'][1]['status'], $index['list'][2]['status'], $index['list'][7]['status']]);

        $this->assertSame(-2, $this->request($p['auth'], 'vip', 'get_gift', ['l' => 7])['status'], 'VIP 等级不足');
        $this->assertSame(-2, $this->request($p['auth'], 'vip', 'get_gift', ['l' => 2])['status'], '须先领 VIP 1 礼包');
        $this->assertSame(-3, $this->request($p['auth'], 'vip', 'get_gift', ['l' => 0])['status']);

        $leap = self::fieldValue($uid, 'freeleap');
        $gift = $this->request($p['auth'], 'vip', 'get_gift', ['l' => 1]);
        $this->assertSame(['jadetype' => 1, 'jadenum' => 5000, 'freeleap' => 1000, 'status' => 1], $gift);
        $this->assertSame([5000, 1000], [TraderLog::jade($uid, 1), TraderLog::jade($uid, 3)]);
        $this->assertSame($leap + 1000, self::fieldValue($uid, 'freeleap'));
        $this->assertSame(1, Vip::log($uid)['gift_num']);
        $this->assertSame(-3, $this->request($p['auth'], 'vip', 'get_gift', ['l' => 1])['status'], '已领取');
        $index = $this->request($p['auth'], 'vip', 'index');
        $this->assertSame([2, 1], [$index['list'][1]['status'], $index['list'][2]['status']]);

        // VIP 2、3：紫宝石；VIP 4：两件装备
        $this->assertSame(1, $this->request($p['auth'], 'vip', 'get_gift', ['l' => 2])['status']);
        $this->assertSame(1, $this->request($p['auth'], 'vip', 'get_gift', ['l' => 3])['status']);
        $this->assertSame(1000 + 1500 + 3000, TraderLog::jade($uid, 3));
        $gift = $this->request($p['auth'], 'vip', 'get_gift', ['l' => 4]);
        $this->assertSame(1, $gift['status']);
        $this->assertSame([204, 214], array_column($gift['equip'], 'itemid'));
        $this->assertSame(2, DB::table('equipment')->where('uid', $uid)->whereIn('equipid', [204, 214])->whereNull('deleted_at')->count());

        // VIP 5：武将李广（已拥有且 VIP 10 以下时按酒馆价格 8 折折算宝石；李广不在酒馆 Pub 表，旧版取不到价格：
        // 界面不显示该格，领取时发 0 个、jadetype 为 null）
        General::createFromTemplate($uid, 20);
        $index = $this->request($p['auth'], 'vip', 'index');
        $this->assertArrayNotHasKey('right', $index['list'][5]);
        $gift = $this->request($p['auth'], 'vip', 'get_gift', ['l' => 5]);
        $this->assertSame(['jadetype' => null, 'jadenum' => 0, 'status' => 1], $gift);
        // VIP 6：两件装备
        $gift = $this->request($p['auth'], 'vip', 'get_gift', ['l' => 6]);
        $this->assertSame([1, [74, 75]], [$gift['status'], array_column($gift['equip'], 'itemid')]);
        $this->assertSame(-2, $this->request($p['auth'], 'vip', 'get_gift', ['l' => 7])['status']);
    }

    public function testVipwageClaimGates(): void
    {
        $p = $this->player(['level' => 10]);
        $uid = $p['uid'];
        $now = strtotime('2026-03-01 12:00:00');
        BossClock::freeze($now);

        // VIP 0：不可领取
        $index = $this->request($p['auth'], 'vipwage', 'index');
        $this->assertSame([1, 0, []], [$index['status'], $index['get'], $index['my_reward']]);
        $this->assertCount(20, $index['list']);
        // 银币项按主公等级倍乘
        $this->assertSame(VipWage::ROWS[1]['get1'] * 10, $index['list'][0]['get1']);
        $this->assertSame(VipWage::ROWS[6]['get1'], $index['list'][5]['get1'], 'VIP 6 第一项为原魂，不乘等级');
        $this->assertSame(-1, $this->request($p['auth'], 'vipwage', 'get_vip_wage')['status']);

        // VIP 1：银币 1000 × 10、军令 5、突飞卡 3
        DB::table('member')->where('uid', $uid)->update(['vip' => 1]);
        Redis::del('player:' . $uid);
        $index = $this->request($p['auth'], 'vipwage', 'index');
        $this->assertSame([1, 10000], [$index['get'], $index['my_reward']['get1']]);
        $silver = self::memberValue($uid, 'silver');
        $act = self::memberValue($uid, 'act');
        $leap = self::fieldValue($uid, 'freeleap');
        $wage = $this->request($p['auth'], 'vipwage', 'get_vip_wage');
        $this->assertSame([1, $act + 5, $silver + 10000], [$wage['status'], $wage['all_act'], $wage['all_silver']]);
        $this->assertSame($leap + 3, self::fieldValue($uid, 'freeleap'));
        $this->assertSame(-3, $this->request($p['auth'], 'vipwage', 'get_vip_wage')['status'], '今日已领取');
        $this->assertSame(2, $this->request($p['auth'], 'vipwage', 'index')['get']);
        $this->assertSame($now, (int)DB::table('member_activity')->where('uid', $uid)->value('vipwage_dateline'));

        // 第二天：可再领；VIP 6：原魂 1000、紫宝石 5、突飞卡 18
        BossClock::freeze($now + 86400);
        DB::table('member')->where('uid', $uid)->update(['vip' => 6]);
        $this->assertSame(1, $this->request($p['auth'], 'vipwage', 'index')['get']);
        $soul = self::memberValue($uid, 'soul');
        $this->assertSame(1, $this->request($p['auth'], 'vipwage', 'get_vip_wage')['status']);
        $this->assertSame([$soul + 1000, 5], [self::memberValue($uid, 'soul'), TraderLog::jade($uid, 3)]);
    }

    public function testViprewardClaimGates(): void
    {
        $p = $this->player();
        $uid = $p['uid'];

        // 活动 viprepay 已结束（2016-12-31）
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'vipreward', 'index'));
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'vipreward', 'get_reward', ['id' => 1]));

        // 活动期间
        $now = 1_480_000_000;
        BossClock::freeze($now);
        $index = $this->request($p['auth'], 'vipreward', 'index');
        $this->assertSame([1, 1483113600 - $now], [$index['status'], $index['cd']]);
        $this->assertCount(count(NewyearVipreward::ROWS), $index['list']);
        $this->assertSame([0], array_values(array_unique(array_column($index['list'], 'status'))));
        $this->assertSame(-2, $this->request($p['auth'], 'vipreward', 'get_reward', ['id' => 1])['status'], 'VIP 0');

        DB::table('member')->where('uid', $uid)->update(['vip' => 6]);
        $this->assertSame(-3, $this->request($p['auth'], 'vipreward', 'get_reward', ['id' => 0])['status']);
        $this->assertSame(-4, $this->request($p['auth'], 'vipreward', 'get_reward', ['id' => 99])['status']);
        $this->assertSame(-5, $this->request($p['auth'], 'vipreward', 'get_reward', ['id' => 7])['status']);

        // VIP 1 档：黄宝石 50
        $res = $this->request($p['auth'], 'vipreward', 'get_reward', ['id' => 1]);
        $this->assertSame([1, ['id' => 1, 'vip' => 1, 'type' => 2, 'value' => 50]], [$res['status'], $res['reward']]);
        $this->assertSame(50, TraderLog::jade($uid, 2));
        $this->assertSame(-6, $this->request($p['auth'], 'vipreward', 'get_reward', ['id' => 1])['status'], '已领取');
        // VIP 4 档：突飞卡 200
        $leap = self::fieldValue($uid, 'freeleap');
        $this->assertSame(1, $this->request($p['auth'], 'vipreward', 'get_reward', ['id' => 4])['status']);
        $this->assertSame($leap + 200, self::fieldValue($uid, 'freeleap'));
        $statuses = array_column($this->request($p['auth'], 'vipreward', 'index')['list'], 'status', 'id');
        $this->assertSame([2, 1, 1, 2, 1, 1, 0], [$statuses[1], $statuses[2], $statuses[3], $statuses[4], $statuses[5], $statuses[6], $statuses[7]]);

        // 领取锁存在：-7
        Redis::set('newyear_vipreward_' . $uid, '1', 60);
        $this->assertSame(-7, $this->request($p['auth'], 'vipreward', 'get_reward', ['id' => 2])['status']);
        Redis::del('newyear_vipreward_' . $uid);
        $this->assertSame(1, $this->request($p['auth'], 'vipreward', 'get_reward', ['id' => 2])['status']);
    }

    public function testRebateClaimGates(): void
    {
        $p = $this->player();
        $uid = $p['uid'];
        $now = strtotime('2026-03-10 12:00:00');
        BossClock::freeze($now);

        // 活动 jump_rebate 未开启，也没有达标记录
        $this->assertSame(['status' => -1, 'get' => 0], $this->request($p['auth'], 'rebate', 'index'));
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'rebate', 'get_reward'));

        // 两天前第 70 期累计充值 6 + 30 元（达到 300 元宝）：记下达标时间与期号
        Rebate::addOrder($uid, 10, 6, 'r1', 70, $now - 2 * 86400 - 60);
        $this->assertSame([0, 0], [self::fieldValue($uid, 'rebate_dateline'), self::fieldValue($uid, 'rebate_version')]);
        Rebate::addOrder($uid, 10, 30, 'r2', 70, $now - 2 * 86400);
        $this->assertSame([$now - 2 * 86400, 70], [self::fieldValue($uid, 'rebate_dateline'), self::fieldValue($uid, 'rebate_version')]);

        // 达标后 30 天内（活动已结束也可领）：剩余 28 天
        $index = $this->request($p['auth'], 'rebate', 'index');
        $this->assertSame(['status' => 1, 'get' => 1, 'gold' => 360, 'day' => 28, 'surplus' => 2800, 'cd' => 0, 'type' => 0], $index);
        $gold = self::memberValue($uid, 'gold');
        $res = $this->request($p['auth'], 'rebate', 'get_reward');
        $this->assertSame(['status' => 1, 'add' => 100, 'all_gold' => $gold + 100, 'day' => 27, 'type' => 0, 'surplus' => 2700], $res);
        $this->assertSame(-5, $this->request($p['auth'], 'rebate', 'get_reward')['status'], '今日已领取');
        $this->assertSame([2, 27], [$this->request($p['auth'], 'rebate', 'index')['get'], $this->request($p['auth'], 'rebate', 'index')['day']]);

        // 第二天可再领
        BossClock::freeze($now + 86400);
        $this->assertSame([1, 27], [$this->request($p['auth'], 'rebate', 'index')['get'], $this->request($p['auth'], 'rebate', 'index')['day']]);
        $this->assertSame(26, $this->request($p['auth'], 'rebate', 'get_reward')['day']);

        // 30 天后结束
        BossClock::freeze($now + 31 * 86400);
        $this->assertSame(-1, $this->request($p['auth'], 'rebate', 'get_reward')['status']);

        // 充值未满 300 元宝：-3
        $q = $this->player();
        BossClock::freeze($now);
        Rebate::addOrder($q['uid'], 10, 6, 'r3', 70, $now);
        DB::table('member_field')->where('uid', $q['uid'])->update(['rebate_dateline' => $now, 'rebate_version' => 70]);
        $this->assertSame(['status' => 1, 'get' => 0, 'gold' => 60, 'day' => 30, 'surplus' => 3000, 'cd' => 0, 'type' => 0], $this->request($q['auth'], 'rebate', 'index'));
        $this->assertSame(-3, $this->request($q['auth'], 'rebate', 'get_reward')['status']);
    }

    public function testDeveloperTestGrantIsOffUnlessPayTestEnabled(): void
    {
        $p = $this->player(['gold' => 0]);
        $uid = $p['uid'];

        // 未开启（PAY_TEST 不为 1）：test_info 不给商品，test_grant 返回 0 且不到账、不建订单
        foreach ([false, '0', 'true'] as $env) {
            putenv($env === false ? 'PAY_TEST' : 'PAY_TEST=' . $env);
            $this->assertSame(['status' => 1, 'enabled' => false, 'list' => []], $this->request($p['auth'], 'pay', 'test_info'));
            $this->assertSame(['status' => 0], $this->request($p['auth'], 'pay', 'test_grant', ['id' => 1]));
        }
        $this->assertSame([0, 0], [self::memberValue($uid, 'gold'), self::memberValue($uid, 'vip')]);
        $this->assertSame(0, DB::table('pay_order')->where('uid', $uid)->count());

        // 开启：test_info 的 list 同 get_product_list
        putenv('PAY_TEST=1');
        $info = $this->request($p['auth'], 'pay', 'test_info');
        $this->assertSame([1, true], [$info['status'], $info['enabled']]);
        $this->assertSame($this->request($p['auth'], 'pay', 'get_product_list')['list'], $info['list']);

        // 不存在的商品：3，不到账
        $this->assertSame(3, $this->request($p['auth'], 'pay', 'test_grant', ['id' => 99])['status']);
        $this->assertSame(0, DB::table('pay_order')->where('uid', $uid)->count());

        // 到账走 Pay::complete（渠道 manual）：60 元宝、VIP 6、首充
        $res = $this->request($p['auth'], 'pay', 'test_grant', ['id' => 1]);
        $orderId = (string)$res['orderid'];
        $this->assertStringStartsWith('manual_' . $uid . '_', $orderId);
        unset($res['orderid']);
        $this->assertSame(['status' => 1, 'gold' => 60, 'add_gold' => 0, 'vip' => 6, 'viptype' => 1], $res);
        $this->assertSame([60, 6], [self::memberValue($uid, 'gold'), self::memberValue($uid, 'vip')]);
        $order = DB::table('pay_order')->where('uid', $uid)->first();
        $this->assertSame([$orderId, 'manual'], [$order['orderid'], $order['paychannel']]);

        // 同一订单号再次到账由核心拦下（6），不重复发元宝
        $this->assertSame(['status' => 6], Pay::complete($uid, 1, $orderId, 'manual', 6));
        $this->assertSame(60, self::memberValue($uid, 'gold'));
        $this->assertSame(1, DB::table('pay_order')->where('uid', $uid)->count());

        // 每次测试充值是新订单：再充一次累计到账
        $this->assertSame(1, $this->request($p['auth'], 'pay', 'test_grant', ['id' => 1])['status']);
        $this->assertSame(2, DB::table('pay_order')->where('uid', $uid)->count());
        $this->assertGreaterThanOrEqual(120, self::memberValue($uid, 'gold'));

        // 关闭后立即失效
        putenv('PAY_TEST');
        $gold = self::memberValue($uid, 'gold');
        $this->assertSame(['status' => 0], $this->request($p['auth'], 'pay', 'test_grant', ['id' => 1]));
        $this->assertSame($gold, self::memberValue($uid, 'gold'));
    }
}
