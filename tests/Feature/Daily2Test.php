<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\Battle\TeamBuilder;
use Hanjiang\MasterData\Drink;
use Hanjiang\MasterData\ExpostulationTemp;
use Hanjiang\MasterData\GeneralCulture;
use Hanjiang\MasterData\GeneraltaskMission;
use Hanjiang\MasterData\Pub;
use Hanjiang\MasterData\Trader;
use Hanjiang\MasterData\WarCollegeSkill;
use Hanjiang\Model\Expostulation;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\Practice;
use Hanjiang\Model\TraderLog;
use Hanjiang\Model\WarCollege;
use Hanjiang\Tests\Support\Gateway;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use PHPUnit\Framework\TestCase;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * 每日玩法第二批：酒馆（c=tavern）、每日对酒（c=drink）、国宴（c=banquet）、国家谏言（c=expostulation）、神将之门（c=generaltask）、
 * 国家学院（c=war_college）、武将培养（c=cultivate）、武将继承（c=general_inherit）、武将折旧（c=muster&m=sale_general）、
 * 校场（c=practice）
 */
final class Daily2Test extends TestCase
{
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
     * 新游客（注册时建 1、2 号训练位与初始武将）：返回 uid、鉴权参数与初始武将 id
     *
     * @param array<string, mixed> $member
     * @return array{uid: int, auth: array<string, mixed>, gid: int}
     */
    private function player(array $member = []): array
    {
        $reg = $this->request(['token' => '', 'uid' => ''], 'register', 'guest');
        $uid = (int)$reg['uid'];
        $login = $this->request(['token' => '', 'uid' => $uid], 'login', 'guest', ['guestkey' => $reg['guestkey']]);
        $member += ['nickname' => 'd2_' . $uid];
        DB::table('member')->where('uid', $uid)->update($member);
        Redis::del('player:' . $uid);
        $gid = (int)DB::table('general')->where('uid', $uid)->value('id');
        return ['uid' => $uid, 'auth' => ['token' => (string)$login['token'], 'uid' => $uid], 'gid' => $gid];
    }

    private static function memberValue(int $uid, string $column): int
    {
        return (int)DB::table('member')->where('uid', $uid)->value($column);
    }

    private static function fieldValue(int $uid, string $column): int
    {
        return (int)DB::table('member_field')->where('uid', $uid)->value($column);
    }

    private static function vip(int $uid, int $level): void
    {
        DB::table('member')->where('uid', $uid)->update(['vip' => $level]);
        DB::execute('INSERT INTO `zc_sanguo_vip_log` (`uid`, `money`, `level`, `dateline`, `type`) VALUES (:uid, 0, :level, 0, 1)
                     ON DUPLICATE KEY UPDATE `level` = :level2', ['uid' => $uid, 'level' => $level, 'level2' => $level]);
    }

    /**
     * 建国家并让玩家加入（第一个为君主 job 1）
     *
     * @param list<array{uid: int}> $players
     */
    private static function country(array $players, int $level, int $joinAgo = 30 * 86400): int
    {
        $king = $players[0]['uid'];
        $id = (int)DB::table('country')->insertGetId([
            'name' => '国' . substr((string)hrtime(true), -7), 'level' => $level, 'member' => count($players), 'uid' => $king, 'nickname' => 'k',
        ]);
        foreach ($players as $i => $p) {
            DB::table('member')->where('uid', $p['uid'])->update(['country' => $id]);
            DB::table('member_country')->insert(['uid' => $p['uid'], 'country' => $id, 'job' => $i === 0 ? 1 : 10, 'joindateline' => time() - $joinAgo]);
            Redis::del('player:' . $p['uid']);
        }
        return $id;
    }

    // ---------------------------------------------------------------------------------------------
    // 每日对酒
    // ---------------------------------------------------------------------------------------------

    public function testDrinkOncePerDayWithLog(): void
    {
        $p = $this->player(['level' => 20, 'silver' => 100000, 'gold' => 1000, 'act' => 10]);
        $uid = $p['uid'];
        $index = $this->request($p['auth'], 'drink', 'index');
        $this->assertSame(1, $index['status']);
        // 梨花白（银币）价格为主公等级 × 100，其余照配置
        $this->assertSame(array_replace(Drink::ROWS[1], ['price' => 2000]), $index['list'][0]);
        $this->assertSame(array_slice(array_values(Drink::ROWS), 1), array_slice($index['list'], 1));
        // 酒不存在
        $this->assertSame(-3, $this->request($p['auth'], 'drink', 'go_drink', ['type' => 9])['status']);
        // 梨花白：银币 = 等级 × 100，加 5 军令，不记全服记录
        $res = $this->request($p['auth'], 'drink', 'go_drink', ['type' => 1]);
        $this->assertSame(['status' => 1, 'reward' => ['act' => 15, 'gold' => 1000, 'silver' => 98000]], array_diff_key($res, ['log' => 1]));
        $this->assertSame(0, DB::table('drink_log')->where('nickname', 'd2_' . $uid)->count());
        $this->assertSame(1, DB::table('act_log')->where('uid', $uid)->where('type', 1)->where('act', 5)->count());
        // 当天不能再对酒
        $this->assertSame(-2, $this->request($p['auth'], 'drink', 'go_drink', ['type' => 2])['status']);
        $this->assertSame(2, $this->request($p['auth'], 'drink', 'index')['status']);

        // 次日：状元红（元宝 200）记全服记录与付费点
        DB::table('member_activity')->where('uid', $uid)->update(['drinktime' => time() - 86400]);
        DB::table('member')->where('uid', $uid)->update(['gold' => 100]);
        $this->assertSame(3, $this->request($p['auth'], 'drink', 'go_drink', ['type' => 4])['status']);
        DB::table('member')->where('uid', $uid)->update(['gold' => 300]);
        $res = $this->request($p['auth'], 'drink', 'go_drink', ['type' => 4]);
        $this->assertSame(1, $res['status']);
        $this->assertSame(['act' => 55, 'gold' => 100, 'silver' => 98000], $res['reward']);
        $this->assertSame(['nickname' => 'd2_' . $uid, 'type' => 4, 'act' => 40], array_diff_key($res['log'], ['dateline' => 1]));
        $log = $this->request($p['auth'], 'drink', 'index')['log'];
        $this->assertSame(['d2_' . $uid, 4, 40], [$log[0]['nickname'], $log[0]['type'], $log[0]['act']]);
        $this->assertLessThanOrEqual(7, count($log));
    }

    // ---------------------------------------------------------------------------------------------
    // 酒馆
    // ---------------------------------------------------------------------------------------------

    public function testTavernTradeAndBatch(): void
    {
        $p = $this->player(['level' => 30, 'silver' => 20000000, 'gold' => 5000]);
        $uid = $p['uid'];
        // 主公 31 级开启
        $this->assertSame(['status' => 5], $this->request($p['auth'], 'tavern', 'index'));
        DB::table('member')->where('uid', $uid)->update(['level' => 41]);
        $index = $this->request($p['auth'], 'tavern', 'index');
        $this->assertSame([1, 0, 0, 0, 1, 1, 1, 0, 0, 0, 0], [
            $index['status'], $index['get1'], $index['get2'], $index['get3'], $index['isfree'], $index['site'],
            $index['site1'], $index['site2'], $index['site3'], $index['site4'], $index['site5'],
        ]);
        $this->assertSame([1, '普通商人', 10000, 50], [
            $index['site_info'][1]['id'], $index['site_info'][1]['name'], $index['site_info'][1]['cost'], $index['site_info'][1]['trading'],
        ]);

        // 每天第一次银币贸易免费：普通商人必得 1 个蓝宝石，下一位 50% 留在 1、50% 去 2
        $res = $this->request($p['auth'], 'tavern', 'trade', ['s' => 1]);
        $this->assertSame([1, 20000000, 1, 1, 1, 0], [$res['status'], $res['silver'], $res['addtype'], $res['addnum'], $res['number'], $res['isfree']]);
        $this->assertContains($res['site'], [1, 2]);
        $this->assertSame(Trader::ROWS[$res['site']]['cost'], $res['cost']);
        $this->assertSame($res['site'], TraderLog::get($uid)['site']);
        $this->assertSame(0, $this->request($p['auth'], 'tavern', 'index')['isfree']);
        // 日常任务 11（贸易）在任务列表里才计数；记 tavernjade_log
        $log = DB::table('tavernjade_log')->where('uid', $uid)->orderBy('id', 'DESC')->first();
        $this->assertSame([1, 1, 1, 1, 'tavern', 'trade'], [(int)$log['type'], (int)$log['jadetype'], (int)$log['jade'], (int)$log['balance'], $log['class'], $log['method']]);

        // 之后按当前商人扣银币
        DB::table('trader_log')->where('uid', $uid)->update(['site' => 1, 'site2' => 0]);
        $res = $this->request($p['auth'], 'tavern', 'trade', ['s' => 1]);
        $this->assertSame([1, 20000000 - 10000, 2], [$res['status'], $res['silver'], $res['number']]);
        // 元宝强行贸易：必进下一位商人
        DB::table('trader_log')->where('uid', $uid)->update(['site' => 1, 'site2' => 0]);
        $res = $this->request($p['auth'], 'tavern', 'trade', ['s' => 1, 'type' => 1]);
        $this->assertSame([1, 5000 - 50, 2, 1], [$res['status'], $res['gold'], $res['site'], $res['site2']]);
        $this->assertSame(1, (int)DB::table('stat_pay')->where('name', 'maoyi')->count());
        // 去已开启的 2 号商人（高级商人：2 个蓝宝石，30% 回 1、70% 去 3）
        $res = $this->request($p['auth'], 'tavern', 'trade', ['s' => 2]);
        $this->assertSame([1, 20000000 - 30000, 2], [$res['status'], $res['silver'], $res['addnum']]);
        $this->assertContains($res['site'], [1, 3]);
        // 银币不足 / 元宝不足
        DB::table('member')->where('uid', $uid)->update(['silver' => 0, 'gold' => 0]);
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'tavern', 'trade', ['s' => 1]));
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'tavern', 'trade', ['s' => 1, 'type' => 1]));

        // 批量贸易：1000 万银币，宝石约 1000 次贸易，商人位置随机重置
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'tavern', 'trade_batch'));
        DB::table('member')->where('uid', $uid)->update(['silver' => 10000000]);
        $before = TraderLog::get($uid);
        $res = $this->request($p['auth'], 'tavern', 'trade_batch');
        $this->assertSame([1, 0], [$res['status'], $res['silver']]);
        $this->assertGreaterThanOrEqual(400, $res['get']['get1']);
        $this->assertSame([1 => $before['get1'] + $res['get']['get1'], 2 => $before['get2'] + $res['get']['get2'], 3 => $before['get3'] + $res['get']['get3']], $res['info']);
        $this->assertSame(1, $res['site1']);
        $this->assertSame(1, $res['site' . $res['site']]);
        $this->assertSame(Trader::ROWS[$res['site']]['cost'], $res['cost']);
        $this->assertSame($res['site'], TraderLog::get($uid)['site']);
    }

    public function testTavernListAndBuy(): void
    {
        $p = $this->player(['level' => 40, 'reputation' => 100]);
        $uid = $p['uid'];
        // 名将榜：按位置排序，每页 100
        $list = $this->request($p['auth'], 'tavern', 'get_list', ['tab' => 2, 'page' => 1]);
        $tab2 = array_values(array_filter(Pub::ROWS, static fn (array $r): bool => $r['tab'] === 2));
        $this->assertSame([1, count($tab2), 100, 1], [$list['status'], $list['totals'], $list['perpage'], $list['page']]);
        $this->assertSame([1 => 0, 2 => 0, 3 => 0], $list['jade']);
        $this->assertSame(1, $list['list'][1]['position']);
        $first = $list['list'][1];
        $this->assertSame(0, $first['isown']);
        $this->assertSame(['image', 'name', 'type', 'wuli', 'zhili', 'tili', 'star', 'skill', 'talent'], array_keys($first['info']));
        $this->assertSame(['id', 'name', 'description'], array_keys($first['info']['skill']));
        // 英雄祭坛要主公 150 级；类型不对
        $this->assertSame(-1, $this->request($p['auth'], 'tavern', 'get_list', ['tab' => 4])['status']);
        $this->assertSame(-1, $this->request($p['auth'], 'tavern', 'get_list', ['tab' => 9])['status']);

        // 买名将榜武将：宝石不足 3；有宝石时扣宝石、入库、记 pub_log（type 1）
        $gid = $first['generalid'];
        $this->assertSame(['status' => 3, 'price' => 0, 'type' => 0], $this->request($p['auth'], 'tavern', 'buy', ['generalid' => $gid]));
        TraderLog::addJade($uid, $first['paytype'], $first['price'] + 7);
        $buy = $this->request($p['auth'], 'tavern', 'buy', ['generalid' => $gid]);
        $this->assertSame([1, $first['price'], $first['paytype'], 7, $gid], [$buy['status'], $buy['price'], $buy['type'], $buy['jade'], $buy['general']['generalid']]);
        $this->assertArrayNotHasKey('status', $buy['general']);
        $this->assertSame(1, DB::table('general')->where('uid', $uid)->where('generalid', $gid)->count());
        $pub = DB::table('pub_log')->where('uid', $uid)->where('generalid', $gid)->first();
        $this->assertSame([1, 1], [(int)$pub['own'], (int)$pub['type']]);
        // 获得武将气泡
        $this->assertSame(1, self::fieldValue($uid, 'ongeneral'));
        $this->assertSame(1, $this->request($p['auth'], 'member', 'index')['ongeneral']);
        // 已拥有
        $this->assertSame(4, $this->request($p['auth'], 'tavern', 'buy', ['generalid' => $gid])['status']);
        $this->assertSame(1, $this->request($p['auth'], 'tavern', 'get_list', ['tab' => 2])['list'][1]['isown']);

        // 征服榜：没在征战中击败过 -2；击败后（pub_log own 0）可买，open 为 1
        $tab1 = array_values(array_filter(Pub::ROWS, static fn (array $r): bool => $r['tab'] === 1));
        usort($tab1, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);
        $g1 = $tab1[0];
        $this->assertSame(-2, $this->request($p['auth'], 'tavern', 'buy', ['generalid' => $g1['generalid']])['status']);
        DB::table('pub_log')->insert(['uid' => $uid, 'generalid' => $g1['generalid'], 'own' => 0, 'type' => 2]);
        $conquer = $this->request($p['auth'], 'tavern', 'get_list', ['tab' => 1]);
        $this->assertSame([1, 0], [$conquer['list'][1]['open'], $conquer['list'][1]['isown']]);
        TraderLog::addJade($uid, $g1['paytype'], $g1['price']);
        $this->assertSame(1, $this->request($p['auth'], 'tavern', 'buy', ['generalid' => $g1['generalid']])['status']);

        // 突飞卡：支付类型 1 / 2 / 3 为 1 / 2 / 4 张，num 批量（最多 100）
        $cards = $this->request($p['auth'], 'tavern', 'get_list', ['tab' => 3]);
        $card = $cards['list'][1];
        $this->assertSame([1 => 1, 2 => 2, 3 => 4][$card['paytype']], $card['times']);
        $this->assertArrayNotHasKey('info', $card);
        TraderLog::addJade($uid, $card['paytype'], $card['price'] * 3);
        $leap = self::fieldValue($uid, 'freeleap');
        $buy = $this->request($p['auth'], 'tavern', 'buy', ['generalid' => $card['generalid'], 'num' => 3]);
        $this->assertSame([1, $card['price'] * 3, []], [$buy['status'], $buy['price'], $buy['general']]);
        $this->assertSame($leap + $card['times'] * 3, self::fieldValue($uid, 'freeleap'));
        $leapLog = DB::table('leap_log')->where('uid', $uid)->orderBy('id', 'DESC')->first();
        $this->assertSame([1, $card['times'] * 3, $leap + $card['times'] * 3, 'tavern', 'buy'], [(int)$leapLog['type'], (int)$leapLog['leaptimes'], (int)$leapLog['alltimes'], $leapLog['class'], $leapLog['method']]);

        // 英雄祭坛：声望不足 -3
        $altar = array_values(array_filter(Pub::ROWS, static fn (array $r): bool => $r['tab'] === 4 && $r['need_reputation'] > 100));
        if ($altar !== []) {
            TraderLog::addJade($uid, $altar[0]['paytype'], $altar[0]['price']);
            $this->assertSame(-3, $this->request($p['auth'], 'tavern', 'buy', ['generalid' => $altar[0]['generalid']])['status']);
        }
    }

    // ---------------------------------------------------------------------------------------------
    // 校场
    // ---------------------------------------------------------------------------------------------

    public function testPracticeStartSettleStopAndUnlock(): void
    {
        $p = $this->player(['level' => 30, 'silver' => 100000, 'gold' => 1000]);
        $uid = $p['uid'];
        $gid = $p['gid'];
        DB::table('general')->where('id', $gid)->update(['level' => 10, 'exp' => 0, 'levelupexp' => 8000, 'goout' => 1]);
        // 注册时建 1、2 号训练位
        $index = $this->request($p['auth'], 'practice', 'index');
        $this->assertSame(1, $index['status']);
        $this->assertSame([1, 2], array_keys($index['place']));
        $this->assertSame([1 => ['paytype' => 1, 'hour' => 8, 'cost' => 3000], 2 => ['paytype' => 1, 'hour' => 24, 'cost' => 7650],
            3 => ['paytype' => 2, 'hour' => 24, 'cost' => 2], 4 => ['paytype' => 2, 'hour' => 72, 'cost' => 20]], $index['rule']);
        $this->assertSame([5, 10, 50, 100], $index['mop']);
        // 没有突飞卡时返回第 1 次突飞的元宝（price.leap）
        $this->assertSame([0, 1], [$index['freetimes'], $index['leap']]);
        $pid = $index['place'][1]['id'];

        // 开始训练：8 小时，银币 = 主公等级 × 100
        $start = $this->request($p['auth'], 'practice', 'practice_start', ['pid' => $pid, 'gid' => $gid, 'type' => 1]);
        $this->assertSame(1, $start['status']);
        $this->assertSame(['gid' => $gid, 'cd' => 28800, 'type' => 1, 'cost' => 3000, 'paytype' => 1, 'id' => $pid, 'squence' => 1, 'level' => 1,
            'practicetime' => 0, 'gold' => 1000, 'silver' => 97000], $start['info']);
        $this->assertSame([1, time() + 28800], [self::fieldValue($uid, 'practicenow'), self::fieldValue($uid, 'practicetime')]);
        $this->assertSame(6, $this->request($p['auth'], 'practice', 'practice_start', ['pid' => $index['place'][2]['id'], 'gid' => $gid, 'type' => 1])['status']);
        $this->assertSame(3, $this->request($p['auth'], 'practice', 'practice_start', ['pid' => $pid, 'gid' => $gid + 999999, 'type' => 1])['status'] === -1 ? 3 : 0);

        // 1 小时后结算：主公等级 × 1 × 112.5 × 100%，向上取整；开始时间推到现在
        DB::table('practice')->where('id', $pid)->update(['starttime' => time() - 3600]);
        $this->assertSame(1, Practice::updatePractice($uid));
        $this->assertSame((int)ceil(30 * 112.5), (int)DB::table('general')->where('id', $gid)->value('exp'));
        $this->assertSame(time(), (int)DB::table('practice')->where('id', $pid)->value('starttime'));
        // 到期：结算到结束时间、清空训练位
        DB::table('practice')->where('id', $pid)->update(['starttime' => time() - 7200, 'endtime' => time() - 3600]);
        Practice::updatePractice($uid);
        $this->assertSame((int)ceil(30 * 112.5) * 2, (int)DB::table('general')->where('id', $gid)->value('exp'));
        $this->assertSame([0, 0], [(int)DB::table('practice')->where('id', $pid)->value('generalid'), self::fieldValue($uid, 'practicenow')]);

        // 终止训练：清空训练位，不结算经验（旧版同）
        $this->request($p['auth'], 'practice', 'practice_start', ['pid' => $pid, 'gid' => $gid, 'type' => 3]);
        $this->assertSame(998, self::memberValue($uid, 'gold'));
        DB::table('practice')->where('id', $pid)->update(['starttime' => time() - 3600]);
        $exp = (int)DB::table('general')->where('id', $gid)->value('exp');
        $this->assertSame(['status' => 1], $this->request($p['auth'], 'practice', 'practice_stop', ['pid' => $pid]));
        $this->assertSame($exp, (int)DB::table('general')->where('id', $gid)->value('exp'));
        $this->assertSame([0, 0, 0], [(int)DB::table('practice')->where('id', $pid)->value('generalid'), self::fieldValue($uid, 'practicenow'), self::fieldValue($uid, 'practicetime')]);
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'practice', 'practice_stop', ['pid' => $pid]));

        // 解锁训练位：VIP 0 最多 3 个；价格表 3 ~ 8，已开启的位置返回 0
        $this->assertSame(['status' => -1, 'gold' => 0], $this->request($p['auth'], 'practice', 'get_unlock_price', ['squence' => 2]));
        $this->assertSame(['status' => 1, 'gold' => 50], $this->request($p['auth'], 'practice', 'get_unlock_price', ['squence' => 3]));
        $this->assertSame(['status' => 1, 'gold' => 500, 'vip' => 2], $this->request($p['auth'], 'practice', 'get_unlock_price', ['squence' => 5]));
        $unlock = $this->request($p['auth'], 'practice', 'unlock');
        $this->assertSame([1, 3, 1, 948], [$unlock['status'], $unlock['info']['squence'], $unlock['info']['level'], $unlock['info']['gold']]);
        $this->assertSame(3, self::fieldValue($uid, 'practiceall'));
        $this->assertSame(['status' => 0, 'gold' => 0], $this->request($p['auth'], 'practice', 'get_unlock_price', ['squence' => 3]));
        $this->assertSame(['status' => 3, 'info' => []], $this->request($p['auth'], 'practice', 'unlock'));

        // 训练位升级：VIP 0 最高 1 级；VIP 2 可到 2 级（50 元宝，主线任务 5）
        $this->assertSame(['status' => 2], $this->request($p['auth'], 'practice', 'get_levelup_price', ['pid' => $pid]));
        $this->assertSame(['status' => 3, 'info' => []], $this->request($p['auth'], 'practice', 'levelup', ['pid' => $pid]));
        self::vip($uid, 2);
        $this->assertSame(['status' => 1, 'info' => ['level' => 2, 'gold' => 50, 'result' => '150']], $this->request($p['auth'], 'practice', 'get_levelup_price', ['pid' => $pid]));
        $up = $this->request($p['auth'], 'practice', 'levelup', ['pid' => $pid]);
        $this->assertSame(['status' => 1, 'info' => ['id' => $pid, 'name' => '加强', 'level' => 2, 'price' => 50, 'gold' => 898]], $up);
    }

    public function testPracticeLeapMopAndTurn(): void
    {
        $p = $this->player(['level' => 60, 'gold' => 1000]);
        $uid = $p['uid'];
        $gid = $p['gid'];
        DB::table('general')->where('id', $gid)->update(['level' => 10, 'exp' => 0, 'levelupexp' => 8000, 'goout' => 1]);
        $pid = (int)DB::table('practice')->where('uid', $uid)->where('squence', 1)->value('id');
        // 训练才能突飞
        $this->assertSame(4, $this->request($p['auth'], 'practice', 'go_leap', ['gid' => $gid])['status']);
        $this->request($p['auth'], 'practice', 'practice_start', ['pid' => $pid, 'gid' => $gid, 'type' => 3]);

        // 元宝突飞：第 n 次的价格取 price.leap，经验 = 主公等级 × 562.5（VIP 5 以下不暴击）
        $leap = $this->request($p['auth'], 'practice', 'go_leap', ['gid' => $gid]);
        $this->assertSame(1, $leap['status']);
        $exp = (int)(60 * 562.5);
        $this->assertSame([$gid, $exp, 0, 0], [$leap['info']['gid'], $leap['info']['addexp'], $leap['info']['hit'], $leap['info']['freetimes']]);
        $this->assertSame(2, $leap['info']['next']);
        $this->assertSame(998 - 1, $leap['info']['gold']);
        $this->assertSame(1, self::fieldValue($uid, 'leaptimes'));
        $log = DB::table('general_leap_log')->where('uid', $uid)->first();
        $this->assertSame([$gid, $exp, 10, 0], [(int)$log['gid'], (int)$log['addexp'], (int)$log['glevel1'], (int)$log['gexp1']]);

        // 突飞卡突飞：扣突飞卡、不加今日突飞次数
        DB::table('member_field')->where('uid', $uid)->update(['freeleap' => 2]);
        $leap = $this->request($p['auth'], 'practice', 'go_leap', ['gid' => $gid]);
        $this->assertSame([1, 1, 997], [$leap['status'], $leap['info']['freetimes'], $leap['info']['gold']]);
        $this->assertSame([1, 1], [self::fieldValue($uid, 'freeleap'), self::fieldValue($uid, 'leaptimes')]);
        $this->assertSame(2, (int)DB::table('leap_log')->where('uid', $uid)->where('type', 2)->value('type'));

        // 突飞扫荡：次数不足 2；VIP 5 以下没有额外经验
        $this->assertSame(['status' => 2], $this->request($p['auth'], 'practice', 'mop', ['gid' => $gid, 'times' => 5]));
        DB::table('member_field')->where('uid', $uid)->update(['freeleap' => 5]);
        $before = DB::table('general')->where('id', $gid)->first();
        $mop = $this->request($p['auth'], 'practice', 'mop', ['gid' => $gid, 'times' => 5]);
        $this->assertSame([1, 0, 0, (float)floor(60 * 562.5 * 5)], [$mop['status'], $mop['freeleap'], $mop['ext_exp'], (float)$mop['exp']]);
        $this->assertSame(Practice::leapRule(2, 0, 60, 0)['gold'], $mop['next']);
        $after = DB::table('general')->where('id', $gid)->first();
        $this->assertGreaterThan((int)$before['level'], (int)$after['level']);

        // 转生：等级正好到上限（50 + 转数 × 5）才能转
        $this->assertSame(['status' => 3, 'info' => []], $this->request($p['auth'], 'practice', 'turn', ['gid' => $gid]));
        DB::table('general')->where('id', $gid)->update(['level' => 50, 'exp' => 0]);
        $turn = $this->request($p['auth'], 'practice', 'turn', ['gid' => $gid]);
        $this->assertSame(['status' => 1, 'info' => ['exp' => 0, 'level' => 1, 'levelupexp' => 80, 'turn' => 1]], $turn);
        // 加经验时 1 转的等级上限为 55
        DB::table('general')->where('id', $gid)->update(['level' => 54, 'exp' => 0]);
        $add = General::addExp($uid, $gid, 99999999);
        $this->assertSame([1, 55, 0, 1], [$add['status'], $add['info']['level'], $add['info']['exp'], $add['info']['isturn']]);
        $this->assertSame(4, General::addExp($uid, $gid, 1)['status']);
        $index = $this->request($p['auth'], 'practice', 'index');
        $hero = array_values(array_filter($index['list'], static fn (array $g): bool => $g['id'] === $gid))[0];
        $this->assertSame([1, 1.3], [$hero['isturn'], $hero['ratio']]);
    }

    // ---------------------------------------------------------------------------------------------
    // 武将培养
    // ---------------------------------------------------------------------------------------------

    public function testCultivateRollSaveAndGiveup(): void
    {
        $p = $this->player(['level' => 45, 'silver' => 100000, 'gold' => 1000]);
        $uid = $p['uid'];
        $gid = $p['gid'];
        DB::table('general')->where('id', $gid)->update(['level' => 10, 'goout' => 1, 'wuliup' => 0, 'zhiliup' => 0, 'tiliup' => 0]);
        // VIP 3 以下只开普通 / 加强
        $index = $this->request($p['auth'], 'cultivate', 'index');
        $this->assertSame(1, $index['status']);
        $this->assertSame([1 => ['id' => 1, 'name' => '普通培养', 'silver' => 4500], 2 => ['id' => 2, 'name' => '加强培养', 'gold' => 20]], $index['rule']);
        $this->assertSame([], $index['list'][1]['cultivate']);
        $this->assertSame(5, $this->request($p['auth'], 'cultivate', 'roll', ['gid' => $gid, 'mode' => 3])['status']);
        $this->assertSame(-2, $this->request($p['auth'], 'cultivate', 'roll', ['gid' => 999999999, 'mode' => 1])['status']);

        // 普通培养：低于基础加成时只加不减（1 ~ 2），结果先存 Redis，未保存前将领不变
        $roll = $this->request($p['auth'], 'cultivate', 'roll', ['gid' => $gid, 'mode' => 1]);
        $this->assertSame(1, $roll['status']);
        $this->assertSame(4500, $roll['next']);
        $this->assertSame(['gid', 'wuliup', 'zhiliup', 'tiliup', 'type', 'gold', 'silver'], array_keys($roll['info']));
        foreach (['wuliup', 'zhiliup', 'tiliup'] as $k) {
            $this->assertContains($roll['info'][$k], [1, 2]);
        }
        $this->assertSame([1, 1000, 95500], [$roll['info']['type'], $roll['info']['gold'], $roll['info']['silver']]);
        $this->assertSame(0, (int)DB::table('general')->where('id', $gid)->value('wuliup'));
        $this->assertSame(array_diff_key($roll['info'], ['gold' => 1, 'silver' => 1]), $this->request($p['auth'], 'cultivate', 'index')['list'][1]['cultivate']);

        // 保存：写入将领、记 cultivate_log、清缓存、主线任务 4
        $save = $this->request($p['auth'], 'cultivate', 'save', ['gid' => $gid]);
        $this->assertSame(1, $save['status']);
        $row = DB::table('general')->where('id', $gid)->first();
        $this->assertSame([$roll['info']['wuliup'], $roll['info']['zhiliup'], $roll['info']['tiliup']], [(int)$row['wuliup'], (int)$row['zhiliup'], (int)$row['tiliup']]);
        $log = DB::table('cultivate_log')->where('uid', $uid)->first();
        $this->assertSame([1, $gid, 0, $roll['info']['wuliup']], [(int)$log['type'], (int)$log['gid'], (int)$log['wuliup1'], (int)$log['wuliup2']]);
        $this->assertSame(['status' => -4], $this->request($p['auth'], 'cultivate', 'save', ['gid' => $gid]));

        // 放弃：只清缓存
        $this->request($p['auth'], 'cultivate', 'roll', ['gid' => $gid, 'mode' => 2]);
        $this->assertSame(980, self::memberValue($uid, 'gold'));
        $this->assertSame(['status' => 1], $this->request($p['auth'], 'cultivate', 'giveup', ['gid' => $gid]));
        $this->assertSame(['status' => -4], $this->request($p['auth'], 'cultivate', 'save', ['gid' => $gid]));

        // VIP 5：白金培养按今日次数递增（基数 + 递增 × 次数）
        self::vip($uid, 5);
        $rule = $this->request($p['auth'], 'cultivate', 'index')['rule'];
        $this->assertSame(['id' => 3, 'name' => '白金培养', 'gold' => 50], $rule[3]);
        $roll = $this->request($p['auth'], 'cultivate', 'roll', ['gid' => $gid, 'mode' => 3]);
        $this->assertSame([1, 51, 930], [$roll['status'], $roll['next'], $roll['info']['gold']]);
        $this->assertSame(1, self::fieldValue($uid, 'baijintimes'));
        $this->assertSame(['id' => 3, 'name' => '白金培养', 'gold' => 51], $this->request($p['auth'], 'cultivate', 'index')['rule'][3]);
        // 元宝不足
        DB::table('member')->where('uid', $uid)->update(['gold' => 10]);
        $this->assertSame(4, $this->request($p['auth'], 'cultivate', 'roll', ['gid' => $gid, 'mode' => 3])['status']);
        // 每日计划任务归零白金 / 至尊次数
        DB::table('member_field')->where('uid', $uid)->update(['dateline' => 0]);
        Member::dailyCron($uid, time());
        $this->assertSame(0, self::fieldValue($uid, 'baijintimes'));
    }

    // ---------------------------------------------------------------------------------------------
    // 武将继承
    // ---------------------------------------------------------------------------------------------

    public function testGeneralInheritPreviewAndInherit(): void
    {
        $p = $this->player(['level' => 25, 'gold' => 1000]);
        $uid = $p['uid'];
        $this->assertSame(['status' => 3], $this->request($p['auth'], 'general_inherit', 'index'));
        DB::table('member')->where('uid', $uid)->update(['level' => 60]);
        $g1 = $p['gid'];
        $g2 = (int)General::createFromTemplate($uid, 52);
        DB::table('general')->where('id', $g1)->update(['level' => 30, 'exp' => 100, 'turn' => 0, 'wuliup' => 20, 'zhiliup' => 10, 'tiliup' => 4, 'goout' => 1]);
        DB::table('general')->where('id', $g2)->update(['level' => 5, 'exp' => 0, 'wuliup' => 3, 'zhiliup' => 30, 'tiliup' => 0, 'goout' => 1]);

        // VIP 0 只有普通继承
        $index = $this->request($p['auth'], 'general_inherit', 'index');
        $this->assertSame(1, $index['status']);
        $this->assertSame([1 => ['id' => 1, 'name' => '普通继承', 'gold' => 0, 'exp' => 50, 'attr' => 50]], $index['rule']);
        $this->assertSame(['inherit_1' => 0, 'inherit_2' => 0, 'inherit_3' => 0, 'inherit_4' => 0], $index['free_inherit']);
        $this->assertSame([0, 0], [$index['endtime'], $index['free_site']]);

        $allexp = General::totalExp(0, 30, 100);
        $this->assertSame(General::levelExp(30) + 100, $allexp);
        $gold = (int)round(max(1, $allexp / (60 * 562.5)) * 1);
        $info = $this->request($p['auth'], 'general_inherit', 'get_inherit_info', ['gid' => $g1]);
        $this->assertSame([1 => ['id' => 1, 'name' => '普通继承', 'gold' => $gold, 'exp' => 50, 'attr' => 50, 'allexp' => (int)($allexp * 50 / 100)]], $info['rule']);

        // 参数检查
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'general_inherit', 'preview', ['gid1' => $g1, 'gid2' => $g1, 'type' => 1]));
        $this->assertSame(['status' => -4], $this->request($p['auth'], 'general_inherit', 'preview', ['gid1' => $g1, 'gid2' => $g2, 'type' => 2]));
        $preview = $this->request($p['auth'], 'general_inherit', 'preview', ['gid1' => $g1, 'gid2' => $g2, 'type' => 1]);
        $this->assertSame(1, $preview['status']);
        $this->assertSame([1, 0, 0], [$preview['after'][0]['level'], $preview['after'][0]['wuliup'], $preview['after'][0]['turn']]);
        // 属性取 max(武将1 × 50%, 武将2)
        $this->assertSame([10, 30, 2], [$preview['after'][1]['wuliup'], $preview['after'][1]['zhiliup'], $preview['after'][1]['tiliup']]);

        // confirm 0 只返回是否到主公等级上限
        $this->assertSame(['status' => 1, 'max' => 0], $this->request($p['auth'], 'general_inherit', 'go_inherit', ['gid1' => $g1, 'gid2' => $g2, 'type' => 1]));
        $res = $this->request($p['auth'], 'general_inherit', 'go_inherit', ['gid1' => $g1, 'gid2' => $g2, 'type' => 1, 'confirm' => 1]);
        $this->assertSame(1, $res['status']);
        $this->assertSame(1000 - $gold, $res['gold']);
        $this->assertSame([$g1 => ['level' => 1, 'turn' => 0], $g2 => ['level' => $preview['after'][1]['level'], 'turn' => 0]], $res['info']);
        $row1 = DB::table('general')->where('id', $g1)->first();
        $this->assertSame([1, 0, 80, 0, 0], [(int)$row1['level'], (int)$row1['exp'], (int)$row1['levelupexp'], (int)$row1['wuliup'], (int)$row1['turn']]);
        $row2 = DB::table('general')->where('id', $g2)->first();
        $this->assertSame([$preview['after'][1]['level'], 10, 30, 2], [(int)$row2['level'], (int)$row2['wuliup'], (int)$row2['zhiliup'], (int)$row2['tiliup']]);
        $log = DB::table('inherit_log')->where('uid', $uid)->first();
        $this->assertSame([1, $gold, $g1, 30, 1, $g2, 5], [(int)$log['type'], (int)$log['cost'], (int)$log['gid1'], (int)$log['g1_level1'], (int)$log['g1_level2'], (int)$log['gid2'], (int)$log['g2_level1']]);
        $this->assertSame(1, (int)DB::table('stat_pay')->where('name', 'general_inherit')->count());
        // 等级 1 的武将不能被继承
        $this->assertSame(['status' => 5], $this->request($p['auth'], 'general_inherit', 'go_inherit', ['gid1' => $g1, 'gid2' => $g2, 'type' => 1, 'confirm' => 1]));

        // VIP 2：每 30 天一次免费普通继承
        self::vip($uid, 2);
        DB::table('general')->where('id', $g1)->update(['level' => 20]);
        $res = $this->request($p['auth'], 'general_inherit', 'go_inherit', ['gid1' => $g1, 'gid2' => $g2, 'type' => 1, 'confirm' => 1]);
        $this->assertSame([1, 1000 - $gold, 2592000], [$res['status'], $res['gold'], $res['endtime']]);
        $this->assertSame(time(), self::fieldValue($uid, 'free_inherit'));
        $this->assertSame(2592000, $this->request($p['auth'], 'general_inherit', 'index')['endtime']);
    }

    // ---------------------------------------------------------------------------------------------
    // 武将折旧
    // ---------------------------------------------------------------------------------------------

    public function testMusterSaleGeneral(): void
    {
        $p = $this->player(['level' => 40]);
        $uid = $p['uid'];
        $tab2 = array_values(array_filter(Pub::ROWS, static fn (array $r): bool => $r['tab'] === 2));
        $tpl = $tab2[0];
        $gid = (int)General::createFromTemplate($uid, $tpl['generalid']);
        DB::table('pub_log')->insert(['uid' => $uid, 'generalid' => $tpl['generalid'], 'own' => 1, 'type' => 1]);
        // 点将台清除获得武将气泡
        $this->assertSame(1, self::fieldValue($uid, 'ongeneral'));
        $index = $this->request($p['auth'], 'muster', 'index');
        $this->assertArrayNotHasKey('sale_cd', $index);
        $this->assertSame(0, self::fieldValue($uid, 'ongeneral'));

        $this->assertSame(['status' => -1, 'tip' => 0, 'msg' => 'VIP等级不够！'], $this->request($p['auth'], 'muster', 'sale_general', ['gid' => $gid]));
        self::vip($uid, 8);
        $this->assertSame(0, $this->request($p['auth'], 'muster', 'index')['sale_cd']);
        $this->assertSame(-3, $this->request($p['auth'], 'muster', 'sale_general', ['gid' => 999999999])['status']);
        // 不在名将榜（初始武将）
        $this->assertSame(-5, $this->request($p['auth'], 'muster', 'sale_general', ['gid' => $p['gid']])['status']);
        // 预览：售价 × VIP 系数
        $price = (int)($tpl['price'] * 0.4);
        $this->assertSame(['status' => 1, 'tip' => 0, 'rule' => ['vip' => 8, 'ratio' => '40%'], 'reward' => ['type' => $tpl['paytype'], 'num' => $price]],
            $this->request($p['auth'], 'muster', 'sale_general', ['gid' => $gid]));

        // 折旧：终止训练、卸装备、禁用（软删除）、招募记录改为未招募、记 CD、返还宝石
        DB::table('general')->where('id', $gid)->update(['goout' => 1]);
        $pid = (int)DB::table('practice')->where('uid', $uid)->where('squence', 1)->value('id');
        DB::table('practice')->where('id', $pid)->update(['generalid' => $gid, 'type' => 1, 'starttime' => time(), 'endtime' => time() + 100]);
        DB::table('member_field')->where('uid', $uid)->update(['practicenow' => 1, 'practicetime' => time() + 100]);
        $res = $this->request($p['auth'], 'muster', 'sale_general', ['gid' => $gid, 'confirm' => 1]);
        $this->assertSame([1, ['type' => $tpl['paytype'], 'num' => $price], 604800], [$res['status'], $res['reward'], $res['sale_cd']]);
        $this->assertArrayNotHasKey('quality', $res['list'][1]);
        $this->assertNull(DB::table('general')->where('id', $gid)->whereNull('deleted_at')->value('id'));
        $this->assertSame(0, (int)DB::table('practice')->where('id', $pid)->value('generalid'));
        $this->assertSame(0, (int)DB::table('pub_log')->where('uid', $uid)->where('generalid', $tpl['generalid'])->value('own'));
        $this->assertSame(time(), self::fieldValue($uid, 'general_sale'));
        $this->assertSame($price, TraderLog::jade($uid, $tpl['paytype']));
        $this->assertSame(604800, $this->request($p['auth'], 'muster', 'index')['sale_cd']);
        // CD 中
        $this->assertSame(-2, $this->request($p['auth'], 'muster', 'sale_general', ['gid' => $p['gid']])['status']);
    }

    // ---------------------------------------------------------------------------------------------
    // 国宴
    // ---------------------------------------------------------------------------------------------

    public function testBanquetTeams(): void
    {
        $a = $this->player(['level' => 50, 'silver' => 0, 'gold' => 500]);
        $b = $this->player(['level' => 60, 'silver' => 0, 'gold' => 0]);
        $c = $this->player(['level' => 50]);
        // 没有国家 / 国家不到 20 级
        $this->assertSame(2, $this->request($a['auth'], 'banquet', 'index')['status']);
        $country = self::country([$a, $b, $c], 19);
        $this->assertSame(2, $this->request($a['auth'], 'banquet', 'index')['status']);
        DB::table('country')->where('id', $country)->update(['level' => 20]);
        $exp0 = (int)DB::table('country')->where('id', $country)->value('exp');

        $index = $this->request($a['auth'], 'banquet', 'index');
        $this->assertSame([1, 10, []], [$index['status'], $index['price'], $index['list']]);
        $this->assertSame(['uid' => $a['uid'], 'times' => 3, 'team' => 0, 'status' => 0, 'type' => 0], $index['info']);
        $this->assertSame([25000, 50000, 100000], array_column($index['banquet'], 'silver'));
        // 刷新（type 1）不返回宴会价格与列表（banquet 保持初始的空数组）
        $refresh = $this->request($a['auth'], 'banquet', 'index', ['type' => 1]);
        $this->assertSame([], $refresh['banquet']);
        $this->assertArrayNotHasKey('price', $refresh);

        // 小型宴会（2 人）：队员加入即满员开宴，加入者得奖励（status 4）
        $team = $this->request($a['auth'], 'banquet', 'create_team', ['id' => 1]);
        $this->assertSame([1, 1, $a['uid'], 1], [$team['status'], $team['team']['type'], $team['team']['caption'], $team['team']['now_number']]);
        $this->assertSame([['type' => 1, 'name' => '小型宴会', 'nickname' => 'd2_' . $a['uid'], 'caption' => $a['uid'], 'number' => 2, 'now_number' => 1]], $team['list']);
        $this->assertSame(-2, $this->request($a['auth'], 'banquet', 'create_team', ['id' => 1])['status']);
        $this->request($b['auth'], 'banquet', 'index');
        $join = $this->request($b['auth'], 'banquet', 'join_team', ['id' => $a['uid']]);
        $this->assertSame([4, 2, 30000, 10, []], [$join['status'], $join['times'], $join['silver'], $join['contribute'], $join['list']]);
        $this->assertSame(30000, self::memberValue($b['uid'], 'silver'));
        $this->assertSame([10, 10], array_map('intval', array_values(DB::table('member_country')->select('contribute', 'allcontribute')->where('uid', $b['uid'])->first())));
        $this->assertSame($exp0 + 10, (int)DB::table('country')->where('id', $country)->value('exp'));
        $donate = DB::table('country_donate_log')->where('uid', $b['uid'])->first();
        $this->assertSame([4, 10, 'banquet', 'join_team'], [(int)$donate['type'], (int)$donate['donate'], $donate['class'], $donate['method']]);
        // 国家加经验时国家税金 + 经验 × 0.1
        $this->assertSame(1, (int)DB::table('war_college_gold_info_log')->where('type', 2)->where('country_gold', 1.0)->count() >= 1 ? 1 : 0);
        // 队长下次打开领奖励（status 3）
        $index = $this->request($a['auth'], 'banquet', 'index');
        // （旧版返回的 info 仍是领奖前读到的状态 3，库里已改为空闲）
        $this->assertSame([25000, 10, 2, 3], [$index['add_silver'], $index['add_contribute'], $index['info']['times'], $index['info']['status']]);
        $this->assertSame(0, (int)DB::table('country_banquet_log')->where('uid', $a['uid'])->value('status'));

        // 大型宴会（5 人、60 元宝）：踢人、解散、霸王宴
        $team = $this->request($a['auth'], 'banquet', 'create_team', ['id' => 2]);
        $this->assertSame(1, $team['status']);
        // 没打开过国宴（没有记录）不能加入
        $this->assertSame(-1, $this->request($c['auth'], 'banquet', 'join_team', ['id' => $a['uid']])['status']);
        $this->request($c['auth'], 'banquet', 'index');
        $this->assertSame(1, $this->request($c['auth'], 'banquet', 'join_team', ['id' => $a['uid']])['status']);
        $this->assertSame(-4, $this->request($c['auth'], 'banquet', 'kickout_team', ['uid' => $a['uid']])['status']);
        $this->assertSame(-5, $this->request($a['auth'], 'banquet', 'kickout_team', ['uid' => $b['uid']])['status']);
        $kick = $this->request($a['auth'], 'banquet', 'kickout_team', ['uid' => $c['uid']]);
        $this->assertSame([1, 1], [$kick['status'], $kick['team']['now_number']]);
        $this->assertSame(1, $this->request($c['auth'], 'banquet', 'join_team', ['id' => $a['uid']])['status']);
        // 人数不足时普通开宴 -7；霸王宴（60 + 10 元宝）不要求满员，大型宴会 200%
        $this->assertSame(-7, $this->request($a['auth'], 'banquet', 'action')['status']);
        $act = $this->request($a['auth'], 'banquet', 'action', ['type' => 1]);
        $this->assertSame([1, 100000, 60, 200, 60, 1, 430], [$act['status'], $act['add_silver'], $act['add_contribute'], $act['addition'], $act['sub_gold'], $act['times'], $act['all_gold']]);
        // 队员下次打开领 100%（以自己的等级算）
        $res = $this->request($c['auth'], 'banquet', 'index');
        $this->assertSame([50000, 30], [$res['add_silver'], $res['add_contribute']]);

        // 解散：其他人改为已解散，下次打开改为空闲
        $this->request($a['auth'], 'banquet', 'create_team', ['id' => 2]);
        $this->request($c['auth'], 'banquet', 'join_team', ['id' => $a['uid']]);
        $this->assertSame(1, $this->request($a['auth'], 'banquet', 'dismiss_team')['status']);
        $this->assertSame(2, (int)DB::table('country_banquet_log')->where('uid', $c['uid'])->value('status'));
        $this->assertSame(2, $this->request($c['auth'], 'banquet', 'index')['info']['status']);
        $this->assertSame(0, (int)DB::table('country_banquet_log')->where('uid', $c['uid'])->value('status'));
        // 退队
        $this->request($a['auth'], 'banquet', 'create_team', ['id' => 2]);
        $this->request($c['auth'], 'banquet', 'join_team', ['id' => $a['uid']]);
        $this->assertSame(1, $this->request($c['auth'], 'banquet', 'getout_team')['status']);
        $this->assertSame(-2, $this->request($c['auth'], 'banquet', 'getout_team')['status']);
        // 叛国时队长解散队伍
        $this->request($c['auth'], 'banquet', 'join_team', ['id' => $a['uid']]);
        \Hanjiang\Model\Banquet::repair($country, $a['uid'], $a['uid']);
        $this->assertSame([], \Hanjiang\Model\Banquet::teamInfo($country, $a['uid']));
        $this->assertSame(2, (int)DB::table('country_banquet_log')->where('uid', $c['uid'])->value('status'));
    }

    // ---------------------------------------------------------------------------------------------
    // 国家谏言
    // ---------------------------------------------------------------------------------------------

    public function testExpostulationProposeAdoptSupportReward(): void
    {
        $king = $this->player(['level' => 60, 'gold' => 1000]);
        $p = $this->player(['level' => 60, 'gold' => 1000, 'gender' => 2]);
        $s = $this->player(['level' => 60, 'gold' => 100, 'silver' => 0]);
        $country = self::country([$king, $p, $s], 30);

        $this->assertSame(['status' => -2, 'free_support' => 1, 'list' => []], $this->request($p['auth'], 'expostulation', 'index'));
        // 可提出的 4 条谏言（写入 member_country.eid），奖励 × 主公等级
        $list = $this->request($p['auth'], 'expostulation', 'expostulation_list');
        $this->assertSame([1, 1, 5], [$list['status'], $list['refresh'], $list['price']]);
        $this->assertCount(4, $list['list']);
        $eids = array_column($list['list'], 'id');
        $this->assertSame(implode(',', $eids), (string)DB::table('member_country')->where('uid', $p['uid'])->value('eid'));
        $temp = ExpostulationTemp::ROWS[$eids[0]];
        $this->assertSame([$temp['put_value'] * 60, Expostulation::description($temp['type'])], [$list['list'][0]['put_value'], $list['list'][0]['description']]);
        // 再打开列表不变；刷新第一次免费、之后 5 元宝
        $this->assertSame($eids, array_column($this->request($p['auth'], 'expostulation', 'expostulation_list')['list'], 'id'));
        $this->assertSame([1, 1000], [($r = $this->request($p['auth'], 'expostulation', 'refresh'))['status'], $r['all_gold']]);
        $this->assertSame(995, $this->request($p['auth'], 'expostulation', 'refresh')['all_gold']);
        $eids = explode(',', (string)DB::table('member_country')->where('uid', $p['uid'])->value('eid'));

        // 提出谏言：不在自己的列表里 -2；扣元宝、换一条新的
        $this->assertSame(-2, $this->request($p['auth'], 'expostulation', 'put_expostulation', ['id' => 999])['status']);
        $eid = (int)$eids[0];
        $temp = ExpostulationTemp::ROWS[$eid];
        DB::table('member')->where('uid', $p['uid'])->update(['gold' => 2000]);
        $put = $this->request($p['auth'], 'expostulation', 'put_expostulation', ['id' => $eid]);
        $this->assertSame([1, 2000 - $temp['put_price']], [$put['status'], $put['all_gold']]);
        $this->assertCount(1, $put['list']);
        $row = DB::table('expostulation')->where('uid', $p['uid'])->first();
        $this->assertSame([$country, $eid, $temp['type'], 2, 10, 0], [(int)$row['cid'], (int)$row['eid'], (int)$row['type'], (int)$row['gender'], (int)$row['job'], (int)$row['status']]);
        $this->assertNotSame((string)$eid, explode(',', (string)DB::table('member_country')->where('uid', $p['uid'])->value('eid'))[0]);
        $id = (int)$row['id'];

        // 审核列表：官职前三能采纳（status 1），其余 2
        $auth = $this->request($p['auth'], 'expostulation', 'authstr');
        $this->assertSame([2, $id, \Hanjiang\MasterData\CountryJob::ROWS[10]['job'], 'd2_' . $p['uid'], 2], [$auth['status'], $auth['list'][0]['id'], $auth['list'][0]['job'], $auth['list'][0]['nickname'], $auth['list'][0]['gender']]);
        $this->assertSame(1, $this->request($king['auth'], 'expostulation', 'authstr')['status']);
        $this->assertSame(-2, $this->request($p['auth'], 'expostulation', 'adopt', ['id' => $id])['status']);
        $this->assertSame(['status' => 1], $this->request($king['auth'], 'expostulation', 'adopt', ['id' => $id]));
        $this->assertSame(-4, $this->request($king['auth'], 'expostulation', 'adopt', ['id' => $id])['status']);

        // 支持：自己的不用支持；每天第一次免费，之后收费
        $this->assertSame(['status' => -5], $this->request($p['auth'], 'expostulation', 'support', ['id' => $id]));
        $sup = $this->request($s['auth'], 'expostulation', 'support', ['id' => $id]);
        $this->assertSame([1, 100, $temp['sup_price']], [$sup['status'], $sup['all_gold'], $sup['sub_gold']]);
        $this->assertSame(['status' => -3], $this->request($s['auth'], 'expostulation', 'support', ['id' => $id]));
        $index = $this->request($s['auth'], 'expostulation', 'index');
        $this->assertSame([1, 0, 1, 2], [$index['status'], $index['free_support'], $index['list'][0]['support'], $index['list'][0]['sort']]);

        // 未完成不能领；全国完成任务后可领
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'expostulation', 'get_reward', ['id' => $id]));
        $byUser = $this->request($p['auth'], 'expostulation', 'get_expostulation_by_user');
        $this->assertSame([1, 30, $temp['type'], 1, 0, $temp['value']], [$byUser['status'], $byUser['level'], $byUser['list']['type'], $byUser['list']['status'], $byUser['list']['plan'], $byUser['list']['value']]);
        Expostulation::action($country, $temp['type'], $temp['value'] - 1);
        $this->assertSame(1, (int)DB::table('expostulation')->where('id', $id)->value('status'));
        Expostulation::action($country, $temp['type']);
        $this->assertSame([2, $temp['value']], [(int)DB::table('expostulation')->where('id', $id)->value('status'), (int)DB::table('expostulation')->where('id', $id)->value('plan')]);
        $this->assertSame(2, $this->request($p['auth'], 'expostulation', 'get_expostulation_by_user')['status']);

        // 发起者与支持者各领各的奖励（银币或原魂 × 主公等级 + 国家贡献）
        $get = $this->request($p['auth'], 'expostulation', 'get_reward', ['id' => $id]);
        $this->assertSame([1, $temp['get_type'], $temp['put_value'] * 60, $temp['put_contribute']], [$get['status'], $get['get_type'], $get['get_value'], $get['get_contribute']]);
        $this->assertSame(['status' => -4], $this->request($p['auth'], 'expostulation', 'get_reward', ['id' => $id]));
        $this->assertSame($temp['put_contribute'], (int)DB::table('member_country')->where('uid', $p['uid'])->value('contribute'));
        $this->assertSame(2, (int)DB::table('expostulation')->where('id', $id)->value('status'));
        $get = $this->request($s['auth'], 'expostulation', 'get_reward', ['id' => $id]);
        $this->assertSame([1, $temp['sup_value'] * 60], [$get['status'], $get['get_value']]);
        // 都领完后谏言改为 3（不再出现在列表里）
        $this->assertSame(3, (int)DB::table('expostulation')->where('id', $id)->value('status'));
        $this->assertSame(['status' => -2], $this->request($king['auth'], 'expostulation', 'get_reward', ['id' => $id]));

        // 离开国家时清除未领取的支持与未采纳的谏言
        Expostulation::addSupportLog($s['uid'], 99999);
        DB::table('expostulation')->insert(['cid' => $country, 'eid' => 1, 'type' => 1, 'uid' => $s['uid'], 'status' => 0]);
        Expostulation::clearSupStatus($s['uid']);
        Expostulation::clearExpos($s['uid']);
        $this->assertNull(Expostulation::support($s['uid'], 99999));
        $this->assertSame(0, DB::table('expostulation')->where('uid', $s['uid'])->whereNull('deleted_at')->count());
    }

    public function testExpostulationCountersFromOtherSystems(): void
    {
        $p = $this->player(['level' => 60, 'silver' => 10000000, 'gold' => 1000]);
        $country = self::country([$p], 30);
        // 每种类型一条已通过的谏言
        foreach ([2 => 2, 3 => 3, 4 => 4] as $type => $eid) {
            DB::table('expostulation')->insert(['cid' => $country, 'eid' => $eid, 'type' => $type, 'uid' => 1, 'status' => 1]);
        }
        $plan = static fn (int $type): int => (int)DB::table('expostulation')->where('cid', $country)->where('type', $type)->value('plan');
        // 贸易 4
        $this->request($p['auth'], 'tavern', 'trade', ['s' => 1]);
        $this->assertSame(1, $plan(4));
        // 培养 3
        DB::table('general')->where('id', $p['gid'])->update(['goout' => 1]);
        $this->request($p['auth'], 'cultivate', 'roll', ['gid' => $p['gid'], 'mode' => 1]);
        $this->assertSame(1, $plan(3));
        // 武将升级 2（按升级数）
        DB::table('general')->where('id', $p['gid'])->update(['level' => 1, 'exp' => 0]);
        General::addExp($p['uid'], $p['gid'], 80 + 320 + 720);
        $this->assertSame(3, $plan(2));
    }

    // ---------------------------------------------------------------------------------------------
    // 神将之门
    // ---------------------------------------------------------------------------------------------

    public function testGeneraltaskBattlesRewardsAndTimes(): void
    {
        $p = $this->player(['level' => 70, 'gold' => 5000, 'soul' => 0]);
        $uid = $p['uid'];
        $gid = $p['gid'];
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'generaltask', 'index'));
        DB::table('member')->where('uid', $uid)->update(['level' => 2000]);
        // 武将 1300 级 255 转：必胜
        DB::table('general')->where('id', $gid)->update(['level' => 1300, 'turn' => 255, 'goout' => 1]);
        $index = $this->request($p['auth'], 'generaltask', 'index');
        $this->assertSame([1, 10], [$index['status'], $index['number']]);
        $this->assertSame(['id', 'generalid', 'quality', 'name', 'level', 'image'], array_keys($index['list'][0]));

        $list = $this->request($p['auth'], 'generaltask', 'get_list');
        $this->assertSame([1, 1, (int)ceil(count(GeneraltaskMission::ROWS) / 3), ['1' => 270, '2' => 3000]], [$list['status'], $list['page'], $list['perpage'], $list['price']]);
        $this->assertSame([1, 1, 0], [$list['list'][0]['firstkill'], $list['list'][0]['open'], $list['list'][0]['pass']]);
        $this->assertSame([0, 0], [$list['list'][1]['open'], $list['list'][1]['pass']]);

        // 参数 / 关卡未开启 / 首杀不能押注
        $this->assertSame(-2, $this->request($p['auth'], 'generaltask', 'action', ['id' => 1])['status']);
        $this->assertSame(-3, $this->request($p['auth'], 'generaltask', 'action', ['id' => 1, 'gid' => 999999999])['status']);
        $this->assertSame(-6, $this->request($p['auth'], 'generaltask', 'action', ['id' => 2, 'gid' => $gid])['status']);
        $this->assertSame(-100, $this->request($p['auth'], 'generaltask', 'action', ['id' => 1, 'gid' => $gid, 'type' => 1])['status']);

        // 首杀：首杀奖励（原魂），进度 + 1，记战报
        $mission = GeneraltaskMission::ROWS[1];
        $res = $this->request($p['auth'], 'generaltask', 'action', ['id' => 1, 'gid' => $gid]);
        $this->assertSame(1, $res['status']);
        $this->assertGreaterThanOrEqual(1, $res['battle']['assess']);
        $this->assertSame(['name' => 'd2_' . $uid, 'level' => 2000, 'gender' => 0], $res['battle']['left_role']);
        $this->assertSame(['name' => $mission['name'], 'level' => $mission['level']], $res['battle']['right_role']);
        $this->assertNotEmpty($res['battle']['process']);
        $this->assertSame(['win' => $res['battle']['assess'], 'soul' => 0, 'firstkill' => 1, 'first_type' => 5, 'first_value' => $mission['first_value'], 'number' => 9,
            'gold' => 5000, 'all_soul' => $mission['first_value']], $res['info']);
        $this->assertSame(1, (int)DB::table('generaltask_log')->where('uid', $uid)->value('stage'));
        $this->assertSame(9, (int)DB::table('member_activity')->where('uid', $uid)->value('generaltasktimes'));

        // 再打第 1 关：押注 × 10，花 270 元宝
        $res = $this->request($p['auth'], 'generaltask', 'action', ['id' => 1, 'gid' => $gid, 'type' => 1]);
        $this->assertSame([1, 0, 5, $mission['soul'] * 10, 8, 5000 - 270], [$res['status'], $res['info']['firstkill'], $res['info']['re_type'], $res['info']['soul'], $res['info']['number'], $res['info']['gold']]);
        $this->assertSame(1, (int)DB::table('stat_pay')->where('name', 'generaltask_battle')->count());
        // 战报：只记推进进度的那场
        $reports = $this->request($p['auth'], 'generaltask', 'get_report_list', ['id' => 1]);
        $this->assertSame(1, $reports['status']);
        $mine = array_values(array_filter($reports['list'], static fn (array $r): bool => $r['nickname'] === 'd2_' . $uid));
        $this->assertCount(1, $mine);
        $report = $this->request($p['auth'], 'generaltask', 'get_report', ['id' => $mine[0]['id']]);
        $this->assertSame(1, $report['status']);
        $this->assertSame($report['battle']['assess'], $report['info']['win']);
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'generaltask', 'get_report', ['id' => 999999999]));
        $this->assertSame(['status' => 0], $this->request($p['auth'], 'generaltask', 'get_report_list', ['id' => 999]));

        // 买次数：30 元宝 + 1 次
        $this->assertSame(['status' => 1, 'price' => 30, 'times' => 8], $this->request($p['auth'], 'generaltask', 'buy_number'));
        $buy = $this->request($p['auth'], 'generaltask', 'buy_number', ['t' => 1]);
        $this->assertSame(['status' => 1, 'price' => 30, 'times' => 9, 'all_gold' => 5000 - 270 - 30], $buy);
        $this->assertSame(1, (int)DB::table('generaltask_log')->where('uid', $uid)->value('buytimes'));
        // 次数用完 -7；每日计划任务重置为 10
        DB::table('member_activity')->where('uid', $uid)->update(['generaltasktimes' => 0]);
        $this->assertSame(-7, $this->request($p['auth'], 'generaltask', 'action', ['id' => 1, 'gid' => $gid])['status']);
        DB::table('member_field')->where('uid', $uid)->update(['dateline' => 0]);
        Member::dailyCron($uid, time());
        $this->assertSame(10, (int)DB::table('member_activity')->where('uid', $uid)->value('generaltasktimes'));
    }

    // ---------------------------------------------------------------------------------------------
    // 国家学院
    // ---------------------------------------------------------------------------------------------

    public function testWarCollegeResearchBuffAndBattleBonus(): void
    {
        $king = $this->player(['level' => 60, 'gold' => 100000]);
        $m = $this->player(['level' => 60]);
        $country = self::country([$king, $m], 29);
        $this->assertSame(['status' => -2, 'msg' => '国家30级开启'], $this->request($king['auth'], 'war_college', 'index'));
        DB::table('country')->where('id', $country)->update(['level' => 30, 'exp' => 100]);
        // 第一次打开：建各技能 0 级、税金 = 国家累计经验 × 0.1
        $expTotal = 100;
        for ($i = 1; $i < 30; $i++) {
            $expTotal += (int)\Hanjiang\Handler\Pvp\CountryHandler::levelupRule($i)['exp'];
        }
        $index = $this->request($king['auth'], 'war_college', 'index');
        $this->assertSame(1, $index['status']);
        $this->assertCount(11, $index['skill_list']);
        $first = $index['skill_list'][0];
        $gold = (int)round($expTotal * 0.1, 1);
        $this->assertSame([1, '强攻', 0, $gold, 1, WarCollegeSkill::ROWS[2]['cost']], [$first['skill_id'], $first['skill_name'], $first['skill_level'], $first['country_gold'], $first['is_active'], $first['cost']]);
        $this->assertSame(2, $this->request($m['auth'], 'war_college', 'index')['skill_list'][0]['is_active']);
        $this->assertSame(['attack' => 0, 'defense' => 0, 'pattack' => 0, 'pdefense' => 0, 'wuli' => 0, 'zhili' => 0, 'tili' => 0, 'dodge' => '0%', 'penetrate' => '0%',
            'jingzhun' => '0%', 'mingzhong' => '0%'], $index['skill_buff']['addition']);
        $this->assertSame([0, 1, 0, 1, 0], [$index['general'], $index['need_general'], $index['buffcd'], $index['is_open'], $index['cd']]);
        $this->assertSame(1, DB::table('war_college_gold_info_log')->where('country_id', $country)->where('type', 1)->count());

        // 研究：只有君主；国家等级与前置技能；用税金升级、全体成员收到系统信件
        $this->assertSame(-4, $this->request($m['auth'], 'war_college', 'level_up', ['skill_id' => 2])['status']);
        $this->assertSame(-3, $this->request($king['auth'], 'war_college', 'level_up')['status']);
        $this->assertSame(-9, $this->request($king['auth'], 'war_college', 'level_up', ['skill_id' => 5])['status']);
        DB::table('war_college_gold_info')->where('country_id', $country)->update(['country_gold' => 100000]);
        $res = $this->request($king['auth'], 'war_college', 'level_up', ['skill_id' => 2]);
        $this->assertSame([1, 100000], [$res['status'], $res['gold']]);
        $this->assertSame([1, 500], [$res['skill_list'][1]['skill_level'], $res['skill_buff']['addition']['defense']]);
        $this->assertSame(100000.0 - 5000, (float)DB::table('war_college_gold_info')->where('country_id', $country)->value('country_gold'));
        $log = DB::table('war_college_level_up_log')->where('country_id', $country)->first();
        $this->assertSame([2, 0, 5000, 0], [(int)$log['skill_type'], (int)$log['skill_level'], (int)$log['country_gold'], (int)$log['gold']]);
        $this->assertSame(1, DB::table('pm')->where('touid', $m['uid'])->where('type', 1)->where('message', 'like', '%LV1%')->count());
        // 下一级要国家 35 级
        $this->assertSame(-9, $this->request($king['auth'], 'war_college', 'level_up', ['skill_id' => 2])['status']);
        // 税金不足：每 1 税金 15 元宝补足，税金扣光
        DB::table('war_college_gold_info')->where('country_id', $country)->update(['country_gold' => 10]);
        $res = $this->request($king['auth'], 'war_college', 'level_up', ['skill_id' => 1]);
        $this->assertSame([1, 100000 - (5000 - 10) * 15], [$res['status'], $res['gold']]);
        $this->assertSame(0.0, (float)DB::table('war_college_gold_info')->where('country_id', $country)->value('country_gold'));
        $this->assertSame(1, DB::table('pm')->where('touid', $m['uid'])->where('message', 'like', '国家税金不足%')->count());
        // 前置技能未达到
        DB::table('country')->where('id', $country)->update(['level' => 50]);
        $this->assertSame(-5, $this->request($king['auth'], 'war_college', 'level_up', ['skill_id' => 5])['status']);
        DB::table('country')->where('id', $country)->update(['level' => 30]);

        // 学院 buff：加入国家满 3 天、1 个将军令换 24 小时
        DB::table('member_country')->where('uid', $m['uid'])->update(['joindateline' => time() - 3600]);
        $this->assertSame(-5, $this->request($m['auth'], 'war_college', 'use_buff')['status']);
        $this->assertSame(['status' => 3, 'general' => 0, 'msg' => '将军令不足'], $this->request($king['auth'], 'war_college', 'use_buff'));
        \Hanjiang\Model\MemberIntegral::change($king['uid'], 2, 'general', 1, 'test', 'test');
        // buff 前战斗与武将界面没有学院加成
        $before = TeamBuilder::forMember($king['uid']);
        $this->assertArrayNotHasKey('warcollege_arr', array_values($before['team'])[0]);
        $buff = $this->request($king['auth'], 'war_college', 'use_buff');
        $this->assertSame(['status' => 1, 'buffcd' => 86400, 'general' => 1], $buff);
        $this->assertSame(172800, $this->request($king['auth'], 'war_college', 'use_buff')['buffcd']);
        $after = TeamBuilder::forMember($king['uid']);
        $unit0 = array_values($before['team'])[0];
        $unit1 = array_values($after['team'])[0];
        // 物防 +500、猛将物攻 +500（策攻只加谋士）
        $this->assertSame($unit0['defense1'] + 500, $unit1['defense1']);
        $this->assertSame($unit0['attack'] + ((int)$unit0['type'] === 1 ? 500 : 0), $unit1['attack']);
        $this->assertSame([500, 500], [$unit1['warcollege_arr']['defense'], WarCollege::addition($king['uid'])['attack']]);
        // 他人的国家学院（c=information）
        $info = $this->request($m['auth'], 'information', 'get_country_info', ['uid' => $king['uid']]);
        $this->assertSame([1, 11, 500], [$info['status'], count($info['skill_list']), $info['skill_buffer']['addition']['defense']]);
        // 离开国家清理 buff
        WarCollege::clearBuffcd($king['uid']);
        $this->assertNull(WarCollege::addition($king['uid']));
        // 国家加经验时税金 + 经验 × 0.1
        $before = (float)DB::table('war_college_gold_info')->where('country_id', $country)->value('country_gold');
        $c = $this->player(['level' => 60]);
        DB::table('member')->where('uid', $c['uid'])->update(['country' => $country]);
        DB::table('member_country')->insert(['uid' => $c['uid'], 'country' => $country, 'job' => 20, 'joindateline' => time() - 30 * 86400]);
        \Hanjiang\Handler\Pvp\CountryHandler::addContribute(DB::table('member')->where('uid', $c['uid'])->first(), 50, 3, 'test', 'test');
        $this->assertSame($before + 5.0, (float)DB::table('war_college_gold_info')->where('country_id', $country)->value('country_gold'));
    }
}
