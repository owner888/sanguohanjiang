<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\Cron\GodCron;
use Hanjiang\Cron\WorldArenaCron;
use Hanjiang\Cross\GodServer;
use Hanjiang\Cross\Server;
use Hanjiang\Cross\UcModel;
use Hanjiang\Cross\WorldArenaServer;
use Hanjiang\MasterData\WorldExploit;
use Hanjiang\MasterData\WorldShop;
use Hanjiang\MasterData\WorldSilver;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\TraderLog;
use Hanjiang\Model\WorldArena;
use Hanjiang\Tests\Support\Gateway;
use OmniPHP\Cache\Redis;
use OmniPHP\Config;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use PHPUnit\Framework\TestCase;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * 跨服第一批：进程内的 uc / battle，跨服演武（c=worldarena）、战神殿（c=god）、英雄王座跨服排行、邀请码 / 长激活码、
 * 分享战报、注册信息记录，按旧版的流程测试。
 *
 * 跨服演武 / 战神殿依赖自然日 / 自然月，测试用 BossClock 固定时间；排行相关的全局表（演武榜、跨服排行、战神殿）每个测试先清空。
 */
final class Cross1Test extends TestCase
{
    /** 2026-11-10 12:00（周二），不在跨服远征 0 点后的更新时间内 */
    private const string DAY = '2026-11-10';

    /** uc_server 本服行原来的开服时间 */
    private int $starttime = 0;

    protected function setUp(): void
    {
        foreach (['arena', 'arena_log', 'member_world', 'world_bet_log', 'world_reward', 'world_reward_log', 'world_top_log',
            'battle_world_arena', 'battle_world_log', 'battle_world_report', 'battle_world_report_log', 'battle_world_shop_log',
            'battle_god_entry', 'battle_god_member', 'battle_god_palace', 'battle_god_palace_report', 'battle_god_palace_report_log',
            'battle_god_report', 'battle_god_round', 'battle_god_support', 'god_reward_log', 'uc_herothrone', 'uc_report',
            'uc_activationcode', 'uc_activationcode_type'] as $table) {
            DB::execute('TRUNCATE TABLE `zc_sanguo_' . $table . '`');
        }
        foreach (Redis::keys('*worldarena*') as $key) {
            Redis::del(preg_replace('/^hj:/', '', $key));
        }
        // 聊天：5 分钟内重复的内容不发（反复运行测试时 uid 会重复）
        foreach (array_merge(Redis::keys('*god_*'), Redis::keys('*herothrone*'), Redis::keys('*chat_recode_*'), Redis::keys('*get_share_info_*')) as $key) {
            Redis::del(preg_replace('/^hj:/', '', $key));
        }
        Redis::del('god_info', 'god_login_delay', 'chat_message_0');
        // 开服时间：一年前（不是新服第一天）；结束后恢复，免得影响其他测试
        $this->starttime = (int)DB::table('uc_server')->where('id', 1)->value('starttime');
        DB::table('uc_server')->where('id', 1)->update(['starttime' => (int)strtotime('2025-11-01')]);
    }

    protected function tearDown(): void
    {
        DB::table('uc_server')->where('id', 1)->update(['starttime' => $this->starttime]);
        BossClock::freeze(null);
        Config::set('app.report_url', '');
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
        $member += ['nickname' => 'c1_' . $uid];
        DB::table('member')->where('uid', $uid)->update($member);
        Redis::del('player:' . $uid);
        return ['uid' => $uid, 'auth' => ['token' => (string)$login['token'], 'uid' => $uid]];
    }

    private static function memberValue(int $uid, string $column): int
    {
        return (int)DB::table('member')->where('uid', $uid)->value($column);
    }

    private static function emptyFormation(int $uid): void
    {
        $positions = [];
        for ($i = 1; $i <= 9; $i++) {
            $positions['position' . $i] = 0;
        }
        DB::table('matrix')->where('uid', $uid)->update($positions);
    }

    private static function setArenaRank(int $uid, int $rank): void
    {
        DB::table('arena')->insert(['uid' => $uid, 'username' => 'a' . $uid, 'rank' => $rank]);
    }

    private static function at(string $time): int
    {
        $ts = (int)strtotime($time);
        BossClock::freeze($ts);
        return $ts;
    }

    // ---------------------------------------------------------------------------------------------
    // 跨服演武
    // ---------------------------------------------------------------------------------------------

    public function testWorldarenaQualificationPkBetRewardsShopAndCrons(): void
    {
        $p1 = $this->player(['level' => 70, 'gold' => 100]);
        $p2 = $this->player(['level' => 70]);
        $p3 = $this->player(['level' => 70, 'gold' => 30, 'silver' => 0]);
        $p4 = $this->player(['level' => 61, 'silver' => 0]);
        $low = $this->player(['level' => 60]);
        foreach ([$p1, $p2, $p3] as $i => $p) {
            self::setArenaRank($p['uid'], $i + 1);
        }
        $serverid = Server::id();
        $day = (int)date('Ymd', strtotime(self::DAY));

        // 0 点后的计划任务：本服前三记 world_top_log，跨服排行按前三初始化
        $t = self::at(self::DAY . ' 00:01:00');
        WorldArenaCron::daily($t);
        $this->assertSame([$p1['uid'], $p2['uid'], $p3['uid']], array_map('intval', array_column(DB::table('world_top_log')->where('ymd', $day)->orderBy('rank')->get(), 'uid')));
        $this->assertSame(['status' => -10], $this->request($p1['auth'], 'worldarena', 'index'), '0 点后 10 分钟内更新');

        $t = self::at(self::DAY . ' 12:00:00');
        $cd = (int)strtotime(self::DAY . ' +1 day') - $t;
        $this->assertSame(['status' => 2, 'top' => [], 'bet' => [], 'reward' => []], $this->request($low['auth'], 'worldarena', 'index'));
        $index = $this->request($p1['auth'], 'worldarena', 'index');
        $this->assertSame(1, $index['status']);
        $this->assertSame([$p1['uid'], $p2['uid'], $p3['uid']], array_column($index['top'], 'uid'));
        $this->assertSame([1, 2, 3], array_column($index['top'], 'rank'));
        $this->assertSame(['exploit' => WorldExploit::ROWS[1]['exploit'], 'rank' => 1, 'cd' => $cd], $index['reward']);
        $this->assertSame(['rank' => ['before' => 1, 'after' => 1], 'support' => 0, 'silver' => 70 * WorldSilver::ROWS[0]['reward'], 'cd' => $cd], $index['bet']);
        $this->assertSame(1, DB::table('edifice_log')->where('uid', $p1['uid'])->where('edifice_id', 6)->count());
        $this->assertSame([1, 2, 3], array_map('intval', array_column(DB::table('battle_world_arena')->orderBy('rank')->get(), 'rank')));
        $this->assertSame($serverid . '_' . $p3['uid'], DB::table('battle_world_arena')->where('rank', 3)->value('id'));

        // 榜上玩家：自己所在页、可挑战标记、剩余次数
        $arena = $this->request($p3['auth'], 'worldarena', 'arena');
        $this->assertSame(1, $arena['status']);
        $this->assertSame(['exploit' => 0, 'times' => 10, 'times_price' => 30], $arena['info']);
        $this->assertSame([1, 1, 0], array_column($arena['rank']['list'], 'challenge'));
        $this->assertSame([0, 0, 1], array_column($arena['rank']['list'], 'myself'));
        $this->assertSame([1, 1], [$arena['rank']['page'], $arena['rank']['pages']]);
        $this->assertSame(['官方', 1], [$arena['top'][0]['groupname'], $arena['top'][0]['sid']]);

        // 榜外玩家押注
        $this->assertSame(-1, $this->request($p1['auth'], 'worldarena', 'betting', ['touid' => $p2['uid'], 'serverid' => $serverid])['status'], '榜上不能押注');
        $arena = $this->request($p4['auth'], 'worldarena', 'arena');
        $this->assertSame([1, 1, 1], array_column($arena['rank']['list'], 'bet'));
        $this->assertSame(-3, $this->request($p4['auth'], 'worldarena', 'betting', ['touid' => $low['uid'], 'serverid' => $serverid])['status']);
        $bet = $this->request($p4['auth'], 'worldarena', 'betting', ['touid' => $p3['uid'], 'serverid' => $serverid]);
        $this->assertSame(['status' => 1, 'target' => ['uid' => $p3['uid'], 'serverid' => $serverid], 'support' => 1], $bet);
        $this->assertSame(3, $this->request($p4['auth'], 'worldarena', 'betting', ['touid' => $p3['uid'], 'serverid' => $serverid])['status'], '每天押一次');
        $list = $this->request($p4['auth'], 'worldarena', 'get_rank_list', ['page' => 1]);
        $this->assertSame([0, 0, 2], array_column($list['rank']['list'], 'bet'));
        $this->assertSame('c1_' . $p3['uid'], $this->request($p4['auth'], 'worldarena', 'index')['bet']['nickname']);

        // 挑战：对方阵上没有武将，必胜，交换名次，被挑战者收到跨服演武信件
        self::emptyFormation($p1['uid']);
        $this->assertSame(-2, $this->request($p4['auth'], 'worldarena', 'pk', ['touid' => $p1['uid'], 'serverid' => $serverid])['status'], '没有资格');
        $pk = $this->request($p3['auth'], 'worldarena', 'pk', ['touid' => $p1['uid'], 'serverid' => $serverid]);
        $this->assertSame(1, $pk['status']);
        $this->assertSame([3, 3, 1, 9], [$pk['info']['win'], $pk['battle']['assess'], $pk['torank'], $pk['times']]);
        $this->assertSame(['sid' => 1, 'groupname' => '官方', 'name' => 'c1_' . $p3['uid'], 'level' => 70], $pk['battle']['left_role']);
        $this->assertSame(['sid' => 1, 'groupname' => '官方', 'name' => 'c1_' . $p1['uid'], 'level' => 70], $pk['battle']['right_role']);
        $this->assertSame(['id' => $pk['info']['report'], 'type' => 'worldarena'], $pk['share']);
        $this->assertSame([$p3['uid'], $p2['uid'], $p1['uid']], array_column($pk['list'], 'uid'));
        $this->assertSame([1, 0, 0], array_column($pk['list'], 'myself'));
        $this->assertSame([$p3['uid'], $p2['uid'], $p1['uid']], array_column($pk['top'], 'uid'));
        $this->assertSame([2, -2], [(int)DB::table('battle_world_arena')->where('uid', $p3['uid'])->value('changed'), (int)DB::table('battle_world_arena')->where('uid', $p1['uid'])->value('changed')]);
        $pm = DB::table('pm')->where('touid', $p1['uid'])->where('type', 8)->first();
        $this->assertSame([1000, '跨服演武', (string)$pk['info']['report']], [(int)$pm['fromuid'], $pm['fromname'], (string)$pm['title']]);
        $this->assertSame('【官方1服】玩家<em>c1_' . $p3['uid'] . '</em>在跨服排行榜将您击败，排名跌落至第3名。', $pm['message']);

        // 挑战失败（自己阵上没有武将）：名次不变，对方收到「被您打的落花流水」
        $pk2 = $this->request($p1['auth'], 'worldarena', 'pk', ['touid' => $p2['uid'], 'serverid' => $serverid]);
        $this->assertSame([1, -3, 0, 9], [$pk2['status'], $pk2['info']['win'], $pk2['torank'], $pk2['times']]);
        $this->assertSame('【官方1服】玩家<em>c1_' . $p1['uid'] . '</em>在跨服排行榜向您挑战，被您打的落花流水。', DB::table('pm')->where('touid', $p2['uid'])->where('type', 8)->value('message'));
        $this->assertSame(-1, $this->request($p1['auth'], 'worldarena', 'pk', ['touid' => $p1['uid'], 'serverid' => $serverid])['status'] === 1 ? 0 : -1);

        // 战报列表 / 回放 / 分享到聊天室
        $reports = $this->request($p4['auth'], 'worldarena', 'get_report_list', ['page' => 1]);
        $this->assertSame([1, 1], [$reports['status'], $reports['page']]);
        $this->assertSame([$pk2['info']['report'], $pk['info']['report']], array_column($reports['list'], 'id'));
        $this->assertSame(['c1_' . $p3['uid'], 'c1_' . $p1['uid'], 1, 1, '官方', 1], [$reports['list'][1]['from'], $reports['list'][1]['to'], $reports['list'][1]['win'], $reports['list'][1]['torank'], $reports['list'][1]['fromgroup'], $reports['list'][1]['tosid']]);
        $this->assertSame(['status' => -1], $this->request($p4['auth'], 'worldarena', 'get_report_list', ['page' => 2]));
        $info = $this->request($p4['auth'], 'worldarena', 'get_report_info', ['id' => $pk['info']['report']]);
        $this->assertSame([1, 3], [$info['status'], $info['info']['win']]);
        foreach (['leftteam', 'rightteam', 'process', 'assess', 'leftresult', 'rightresult', 'left_role', 'right_role'] as $key) {
            $this->assertArrayHasKey($key, $info['battle']);
        }
        $this->assertNotEmpty($info['battle']['leftteam']);
        $this->assertSame(['status' => -1], $this->request($p4['auth'], 'worldarena', 'get_report_info', ['id' => 999999]));
        $this->assertSame(['status' => -1], $this->request($p1['auth'], 'worldarena', 'send_to_chat', ['reportid' => 0]));
        $chat = $this->request($p1['auth'], 'worldarena', 'send_to_chat', ['reportid' => $pk['info']['report']]);
        $this->assertSame(['status' => 1, 'gold' => 98], $chat);
        $message = implode("\n", array_column($this->request($p1['auth'], 'chat', 'index')['list'], 'message'));
        $this->assertStringContainsString('【官方1服】c1_' . $p1['uid'], $message);
        $this->assertStringContainsString('胜利', $message);
        $this->assertStringContainsString('{13,[查看战报],' . $pk['info']['report'] . '}', $message);
        $this->assertSame(-2, $this->request($p1['auth'], 'worldarena', 'send_to_chat', ['reportid' => 999999])['status']);

        // 买次数：30 元宝；不在榜上没有资格
        $this->assertSame(['status' => -1], $this->request($p4['auth'], 'worldarena', 'buy_times'));
        $this->assertSame(['status' => 1, 'times' => 10, 'gold' => 0], $this->request($p3['auth'], 'worldarena', 'buy_times'));
        $this->assertSame(['status' => 2], $this->request($p3['auth'], 'worldarena', 'buy_times'));
        $this->assertSame(1, (int)DB::table('stat_pay')->where('name', 'worldarena_buytimes')->value('times') > 0 ? 1 : 0);

        // 第二天 0 点后：记录昨日排名（world_log），名次变化清零；领排行银币 / 押注银币 / 功勋
        $t = self::at(self::DAY . ' 00:01:00 +1 day');
        WorldArenaCron::daily($t);
        $this->assertSame(['rank' => 1, 'changed' => 2], array_map('intval', DB::table('battle_world_log')->select('rank', 'changed')->where('uid', $p3['uid'])->where('ymd', $day)->first()));
        $this->assertSame([0, 0, 0], array_map('intval', array_column(DB::table('battle_world_arena')->get(), 'changed')));
        $this->assertSame([1, 2, 3], array_map('intval', array_column(DB::table('battle_world_arena')->orderBy('rank')->get(), 'rank')), '前三没变，排行保留');
        $this->assertSame(0, (int)Redis::hGet('worldarena_support', $serverid . '_' . $p3['uid']), '支持数清空');

        self::at(self::DAY . ' 12:00:00 +1 day');
        $index = $this->request($p3['auth'], 'worldarena', 'index');
        $this->assertSame(['exploit' => WorldExploit::ROWS[1]['exploit'], 'rank' => 1, 'cd' => 0], $index['reward'], '昨日第 1 名的功勋');
        $silver = 70 * WorldSilver::ROWS[2]['reward'];
        $this->assertSame(['rank' => ['before' => 3, 'after' => 1], 'silver' => $silver, 'support' => 0, 'cd' => 0], $index['bet']);
        $get = $this->request($p3['auth'], 'worldarena', 'get_bet_reward');
        $this->assertSame([1, $silver, $silver], [$get['status'], $get['add'], $get['silver']]);
        $this->assertSame(0, $this->request($p3['auth'], 'worldarena', 'get_bet_reward')['add'], '已领');
        $rank = $this->request($p3['auth'], 'worldarena', 'get_rank_reward');
        $this->assertSame([1, 100, 100], [$rank['status'], $rank['add'], $rank['exploit']]);
        $this->assertSame(['status' => -1], $this->request($p3['auth'], 'worldarena', 'get_rank_reward'));
        $betSilver = 61 * WorldSilver::ROWS[2]['bet'];
        $this->assertSame($betSilver, $this->request($p4['auth'], 'worldarena', 'index')['bet']['silver']);
        $get = $this->request($p4['auth'], 'worldarena', 'get_bet_reward');
        $this->assertSame([1, $betSilver, $betSilver], [$get['status'], $get['add'], self::memberValue($p4['uid'], 'silver')]);

        // 跨服商城：功勋兑换物品，背包使用后发全服福利，其他 61 级以上玩家各领一次
        $shop = $this->request($p3['auth'], 'worldarena', 'shop');
        $this->assertSame([1, count(WorldShop::ROWS), 100], [$shop['status'], count($shop['list']), $shop['exploit']]);
        $this->assertSame(['status' => 3], $this->request($p3['auth'], 'worldarena', 'exchange_item', ['id' => 3]), '功勋不足');
        $this->assertSame(['status' => -1], $this->request($p3['auth'], 'worldarena', 'exchange_item', ['id' => 999]));
        $ex = $this->request($p3['auth'], 'worldarena', 'exchange_item', ['id' => 1]);
        $this->assertSame([1, 0, 1, 'c1_' . $p3['uid'], 1, '官方'], [$ex['status'], $ex['exploit'], count($ex['log']), $ex['log'][0]['nickname'], $ex['log'][0]['itemid'], $ex['log'][0]['groupname']]);
        $item = DB::table('equipment')->where('uid', $p3['uid'])->where('type', 11)->first();
        $this->assertSame([1, 11, 1], [(int)$item['equipid'], (int)$item['quality'], (int)$item['number']]);
        $this->assertSame(1, $this->request($p3['auth'], 'pack', 'use_item', ['id' => $item['id'], 'confirm' => 1])['status']);
        $reward = DB::table('world_reward')->first();
        $this->assertSame([$p3['uid'], 1, WorldShop::ROWS[1]['add2']], [(int)$reward['uid'], (int)$reward['type'], (int)$reward['add']]);
        $this->assertSame(['nickname' => 'c1_' . $p3['uid'], 'type' => 1, 'num' => WorldShop::ROWS[1]['add2'] * 61], $this->request($p4['auth'], 'member', 'index')['server_reward']);
        $this->assertSame(0, $this->request($p3['auth'], 'member', 'index')['server_reward'], '自己发的不领');
        $this->assertSame(0, $this->request($low['auth'], 'member', 'index')['server_reward'], '61 级以下不显示');
        $silver = self::memberValue($p4['uid'], 'silver');
        $sr = $this->request($p4['auth'], 'worldarena', 'get_server_reward');
        $this->assertSame([1, [], $silver + WorldShop::ROWS[1]['add2'] * 61], [$sr['status'], $sr['server_reward'], $sr['silver']]);
        $this->assertSame(['status' => -1], $this->request($p4['auth'], 'worldarena', 'get_server_reward'));
        // 升级到 61 级以上时跳过以前的全服福利（map 升级）
        WorldArena::updateServerReward($p2['uid'], (int)WorldArena::lastReward()['id']);
        $this->assertSame(['status' => -1], $this->request($p2['auth'], 'worldarena', 'get_server_reward'));

        // 神树：活动未开启时神树同步为 0；榜上玩家神树升级直接更新
        WorldArenaServer::updateTree($serverid, $p3['uid'], 5);
        $this->assertSame(5, (int)DB::table('battle_world_arena')->where('uid', $p3['uid'])->value('tree'));
        $this->assertSame(1, WorldArenaCron::tree((int)strtotime(self::DAY . ' 12:00:00 +1 day')));
        $this->assertSame(0, (int)DB::table('battle_world_arena')->where('uid', $p3['uid'])->value('tree'));
        $this->assertSame(0, WorldArenaCron::tree((int)strtotime(self::DAY . ' 00:05:00 +1 day')), '0:10 以前不执行');

        // 前三变化：p4 进入演武榜前三、p1 退出，第三天 0 点后 p1 从跨服榜删除、p4 排到最后
        DB::table('arena')->where('uid', $p1['uid'])->update(['rank' => 4]);
        self::setArenaRank($p4['uid'], 1);
        DB::table('arena')->where('uid', $p2['uid'])->update(['rank' => 2]);
        $t = self::at(self::DAY . ' 00:01:00 +2 day');
        WorldArenaCron::daily($t);
        $this->assertSame([$p3['uid'], $p2['uid'], $p4['uid']], array_map('intval', array_column(DB::table('battle_world_arena')->whereNull('deleted_at')->orderBy('rank')->get(), 'uid')));
        $this->assertNotNull(DB::table('battle_world_arena')->where('uid', $p1['uid'])->value('deleted_at'));
    }

    public function testWorldarenaNewServerFirstDayAndClose(): void
    {
        $p1 = $this->player(['level' => 70]);
        $p2 = $this->player(['level' => 70]);
        $p3 = $this->player(['level' => 70]);
        foreach ([$p1, $p2, $p3] as $i => $p) {
            self::setArenaRank($p['uid'], $i + 1);
        }
        $t = self::at(self::DAY . ' 12:00:00');
        // 开服当天没有跨服资格
        DB::table('uc_server')->where('id', 1)->update(['starttime' => $t - 3600]);
        $index = $this->request($p1['auth'], 'worldarena', 'index');
        $this->assertSame([1, [], []], [$index['status'], $index['top'], $index['reward']]);
        $this->assertSame(['status' => -1], $this->request($p1['auth'], 'worldarena', 'buy_times'));
        // 有人低于 61 级时前三不完整：没有人有资格
        DB::table('uc_server')->where('id', 1)->update(['starttime' => $t - 86400 * 30]);
        DB::table('member')->where('uid', $p2['uid'])->update(['level' => 60]);
        $this->assertSame([$p1['uid'], $p3['uid']], array_keys(WorldArena::topRank($t, 20991231)));
        // 不满三个名次时为空
        DB::table('arena')->where('uid', $p3['uid'])->delete();
        $this->assertSame([], WorldArena::topRank($t, 20991230));
    }

    // ---------------------------------------------------------------------------------------------
    // 战神殿
    // ---------------------------------------------------------------------------------------------

    public function testGodEntrySyncRoundsSupportPalaceAndShare(): void
    {
        $serverid = Server::id();
        $players = [];
        for ($i = 0; $i < 6; $i++) {
            $players[] = $this->player(['level' => 180, 'gold' => 10]);
        }
        $low80 = $this->player(['level' => 79]);
        $low175 = $this->player(['level' => 170]);
        $low60 = $this->player(['level' => 59]);
        $viewer = $this->player(['level' => 100, 'gold' => 10]);

        // 8 日以前：活动未开启
        self::at('2026-11-05 12:00:00');
        $index = $this->request($viewer['auth'], 'god', 'index');
        $this->assertSame([1, 1, (int)strtotime('2026-11-08') - (int)strtotime('2026-11-05 12:00:00') + 5], [$index['status'], $index['stage'], $index['cd']]);
        $this->assertSame(['status' => -1], $this->request($players[0]['auth'], 'god', 'entry'));
        $pre = $this->request($viewer['auth'], 'god', 'get_reward_pre');
        $this->assertSame(['status' => 1, 'entry_cd' => (int)strtotime('2026-11-15 18:00:00') - (int)strtotime('2026-11-05 12:00:00'), 'general_list' => [], 'reward_list' => [], 'entry_level' => 100], $pre);
        $this->assertSame(1, $this->request($viewer['auth'], 'member', 'index')['god_reward']);
        $this->assertSame(['status' => 1, 'king' => []], $this->request($viewer['auth'], 'god', 'loading'));

        // 报名阶段
        self::at('2026-11-10 12:00:00');
        $this->assertSame(['status' => -3], $this->request($low60['auth'], 'god', 'index'));
        $index = $this->request($players[0]['auth'], 'god', 'index');
        $this->assertSame([2, ['member_list' => [], 'limit_level' => 175, 'is_entry' => false]], [$index['stage'], $index['info']]);
        $this->assertSame([1, 1], [$this->request($players[0]['auth'], 'member', 'index')['god'], $this->request($players[0]['auth'], 'member', 'index')['god_entry']]);
        $this->assertSame(['status' => -6], $this->request($low80['auth'], 'god', 'entry'));
        $this->assertSame(['status' => 2], $this->request($low175['auth'], 'god', 'entry'));
        $entry = $this->request($players[0]['auth'], 'god', 'entry');
        $this->assertSame([1, [['nickname' => 'c1_' . $players[0]['uid']]]], [$entry['status'], $entry['list']]);
        $this->assertSame(['status' => -5], $this->request($players[0]['auth'], 'god', 'entry'));
        $this->assertSame(0, $this->request($players[0]['auth'], 'member', 'index')['god_entry'], '已报名');
        foreach (array_slice($players, 1) as $p) {
            $this->assertSame(1, $this->request($p['auth'], 'god', 'entry')['status']);
        }
        $this->assertCount(4, $this->request($viewer['auth'], 'god', 'index')['info']['member_list']);
        $this->assertSame(-3, $this->request($players[0]['auth'], 'god', 'sync_battle_info')['status'], '报名未结束');

        // 18 点报名结束：计划任务同步全部报名玩家、生成第一轮对阵（6 人 → 4 场，2 场轮空）
        $t = self::at('2026-11-15 18:00:20');
        $this->assertSame(0, GodCron::run($t));
        $this->assertSame(6, DB::table('battle_god_member')->count());
        $this->assertSame([180], array_values(array_unique(array_map('intval', array_column(DB::table('battle_god_entry')->get(), 'level')))));
        $rounds = DB::table('battle_god_round')->get();
        $this->assertSame([4, 4, 4, 4], array_map('intval', array_column($rounds, 'round')));
        $this->assertSame(2, count(array_filter($rounds, static fn (array $r): bool => (int)$r['uid2'] === 0)));
        $this->assertSame(0, GodCron::run($t + 10), '只生成一次');
        $this->assertSame(4, DB::table('battle_god_round')->count());
        $this->assertSame(5, $this->request($viewer['auth'], 'god', 'index')['stage']);
        $this->assertSame(-2, $this->request($players[0]['auth'], 'god', 'sync_battle_info')['status'], '计划任务刚同步过（80 秒）');
        self::at('2026-11-15 18:02:00');
        $this->assertSame(['status' => 1, 'info' => []], $this->request($players[0]['auth'], 'god', 'sync_battle_info'), '等级国家没变');
        $this->assertSame(-2, $this->request($players[0]['auth'], 'god', 'sync_battle_info')['status'], '5 秒内');
        $this->assertSame(['status' => -1, 'info' => []], $this->request($viewer['auth'], 'god', 'sync_battle_info'), '未报名');

        // 19 点开战（第一轮 1/4，三局两胜，每局 5 分钟）：观战玩家随机关注一场并支持一方
        self::at('2026-11-15 19:00:30');
        $index = $this->request($viewer['auth'], 'god', 'index');
        $this->assertSame([3, 0, 4, 1, 2], [$index['stage'], $index['info']['entry'], $index['info']['round'], $index['info']['stage'], $index['info']['mode']]);
        $this->assertSame('四强赛', $index['info']['type']);
        $this->assertSame('【官方1服】', $index['info']['play']['left']['server']);
        $roundid = $index['info']['roundid'];
        $this->assertSame(1, (int)DB::table('battle_god_round')->where('id', $roundid)->value('follow'));
        $side = (int)DB::table('battle_god_round')->where('id', $roundid)->value('uid2') > 0 ? 2 : 1;
        $this->assertSame(['status' => 1], $this->request($viewer['auth'], 'god', 'support', ['side' => $side]));
        $this->assertSame(['status' => -1], $this->request($viewer['auth'], 'god', 'support', ['side' => $side]), '已经支持过');
        $this->assertSame(-$side, $this->request($viewer['auth'], 'god', 'index')['info']['entry']);
        $support = $this->request($viewer['auth'], 'god', 'get_support', ['id' => $roundid]);
        $this->assertSame(1, $support['status']);
        $this->assertGreaterThan(0, $side === 1 ? $support['left'] : $support['right']);
        $mine = $this->request($players[0]['auth'], 'god', 'index');
        $this->assertSame([1, $players[0]['uid']], [$mine['info']['entry'], in_array($players[0]['uid'], [$mine['info']['play']['left']['uid'], $mine['info']['play']['right']['uid']], true) ? $players[0]['uid'] : 0]);

        // 计划任务：每局开打前 1 分钟算出全部战斗，一轮打完生成下一轮，决赛打完写战帝堂
        foreach (['19:04:30', '19:09:30', '19:14:30', '19:19:30', '19:24:30', '19:29:30', '19:34:30', '19:39:30', '19:44:30'] as $hm) {
            $t = self::at('2026-11-15 ' . $hm);
            GodCron::run($t);
            $this->assertSame(0, GodCron::run($t + 20), $hm . ' 每局只算一次');
        }
        $this->assertSame([4, 2, 1], array_values(array_unique(array_map('intval', array_column(DB::table('battle_god_round')->orderBy('id')->get(), 'round')))));
        $this->assertSame(0, DB::table('battle_god_round')->where('win', 0)->count(), '每场都决出胜负');
        $final = DB::table('battle_god_round')->where('round', 1)->first();
        $champion = (int)$final['uid' . $final['win']];
        $this->assertSame(1, DB::table('battle_god_entry')->where('goout', 0)->count());
        $this->assertSame($champion, (int)DB::table('battle_god_entry')->where('goout', 0)->value('uid'));
        $this->assertSame([1, 2, 2, 4, 4], array_map('intval', array_column(DB::table('battle_god_entry')->where('goout', '>', 0)->orderBy('goout')->get(), 'goout')), '出局的轮次');
        $palace = DB::table('battle_god_palace')->first();
        $this->assertSame([1, 202611, '无始大帝', $champion, 4], [(int)$palace['chapter'], (int)$palace['phase'], $palace['name'], (int)$palace['uid'], (int)$palace['round']]);
        // 战报：左方始终是场次左方，晋级的那一局 up 为 1，决赛决胜局 up 为 2
        $report = GodServer::reportInfo((int)$final['id'], (int)DB::table('battle_god_report')->where('id', $final['id'])->max('stage'));
        $this->assertSame([2, 1], [$report['report']['report']['up'], $report['report']['report']['round']]);
        foreach (['leftteam', 'rightteam', 'process', 'assess', 'leftresult', 'rightresult'] as $key) {
            $this->assertArrayHasKey($key, $report['report']['report']);
        }
        $this->assertNotEmpty($report['report']['report']['process']);

        // 活动结束：战神榜、战帝堂、历程、回放、分享
        $cp = ['uid' => $champion, 'auth' => ['token' => (string)array_values(array_filter($players, static fn (array $p): bool => $p['uid'] === $champion))[0]['auth']['token'], 'uid' => $champion]];
        self::at('2026-11-15 19:50:00');
        $index = $this->request($viewer['auth'], 'god', 'index');
        $this->assertSame([4, 0], [$index['stage'], $index['cd'] - 5]);
        $this->assertSame(['第一名', '第二名', '四强', '四强', '八强', '八强'], array_column($index['info']['list'], 'round'));
        $this->assertSame($champion, $index['info']['list'][0]['uid']);
        $this->assertSame(['status' => -3], $this->request($viewer['auth'], 'god', 'get_reward_list'), '依赖未移植：奖励规则为空');
        $this->assertSame(['status' => -2], $this->request($viewer['auth'], 'god', 'get_reward', ['type' => 1, 'id' => 1]));
        $history = $this->request($viewer['auth'], 'god', 'get_report_list', ['uid' => $champion, 'serverid' => $serverid]);
        $this->assertSame([1, '第一名', '【官方1服】', 180], [$history['status'], $history['info']['rank'], $history['info']['server'], $history['info']['level']]);
        $this->assertContains('决赛', array_column($history['list'], 'round'));
        $this->assertSame(['status' => -3], $this->request($viewer['auth'], 'god', 'get_report_list', ['uid' => $viewer['uid'], 'serverid' => $serverid]));
        $this->assertSame(['status' => -4], $this->request($low80['auth'], 'god', 'get_report_list', ['uid' => $champion]));
        $palaceRes = $this->request($viewer['auth'], 'god', 'palace', ['page' => 1]);
        $this->assertSame([1, '一世战帝', '无始大帝', '【官方1服】'], [$palaceRes['status'], $palaceRes['list'][1]['title'], $palaceRes['list'][1]['name'], $palaceRes['list'][1]['server']]);
        $this->assertNotEmpty($palaceRes['list'][1]['report_list']);
        $first = $palaceRes['list'][1]['report_list'][0];
        $this->assertStringEndsWith('第一场', $first['title']);
        $pr = $this->request($viewer['auth'], 'god', 'palace_report', $first);
        $this->assertSame(1, $pr['status']);
        $this->assertArrayHasKey('left_role', $pr['battle']);
        $this->assertSame(['status' => -1], $this->request($viewer['auth'], 'god', 'palace_report', ['chapter' => 1]));
        $finalStage = (int)DB::table('battle_god_report')->where('id', $final['id'])->max('stage');
        $ri = $this->request($viewer['auth'], 'god', 'get_report_info', ['id' => $final['id'], 'stage' => $finalStage]);
        $this->assertSame([1, ['id' => $final['id'] . '_' . $finalStage, 'type' => 'god']], [$ri['status'], $ri['share']]);
        $this->assertSame(['status' => -1, 'share' => ['id' => '0_0', 'type' => 'god']], $this->request($viewer['auth'], 'god', 'get_report_info'));
        $share = $this->request($viewer['auth'], 'god', 'share_report', ['id' => $final['id'], 'stage' => $finalStage]);
        $this->assertSame(['status' => 1, 'all_gold' => 8], $share);
        $this->assertStringContainsString('荣登战帝宝座', implode("\n", array_column($this->request($viewer['auth'], 'chat', 'index')['list'], 'message')));
        $this->assertSame(['status' => 1, 'all_gold' => 6], $this->request($viewer['auth'], 'god', 'share_report', ['id' => $final['id'], 'stage' => $finalStage]), '5 分钟内不再广播，照扣元宝');
        $this->assertSame(['status' => -2], $this->request($viewer['auth'], 'god', 'share_report', ['id' => 999, 'stage' => 1]), '战报取不到，照扣元宝');
        $this->assertSame(4, self::memberValue($viewer['uid'], 'gold'));

        // 分享到外部：配置了展示页地址才有链接
        $save = $this->request($viewer['auth'], 'report', 'save', ['type' => 'god', 'id' => $final['id'] . '_' . $finalStage]);
        $this->assertSame(['status' => -2], $save);
        Config::set('app.report_url', 'https://share.example/r?id=');
        $save = $this->request($viewer['auth'], 'report', 'save', ['type' => 'god', 'id' => $final['id'] . '_' . $finalStage]);
        $id = (int)DB::table('uc_report')->where('type', 'god')->value('id');
        $this->assertSame(['status' => 1, 'link' => 'https://share.example/r?id=' . $id], $save);
        $stored = json_decode((string)DB::table('uc_report')->where('id', $id)->value('report'), true);
        $this->assertSame([1, 'god'], [$stored['status'], 'god']);

        // 战帝上线公告（每月 15 日以外，战帝本人打开聊天时；5 分钟一次）
        self::at('2026-11-16 10:00:00');
        $this->request($cp['auth'], 'chat', 'index');
        $this->assertSame('无始大帝', Redis::get('god_info')['title']);
        $list = $this->request($viewer['auth'], 'chat', 'read')['list'] ?? [];
        $public = array_column($this->request($viewer['auth'], 'chat', 'index')['list'], 'message');
        $this->assertContains('{9, 无始大帝【c1_' . $champion . '】上线了，万臣朝拜，天下慑服}', $public);
        $this->assertSame(1, (int)Redis::exists('god_login_delay'));
        $this->assertSame(['status' => -1, 'msg' => '不在预览奖励时间区间！'], $this->request($viewer['auth'], 'god', 'get_reward_pre'));
        unset($list);
    }

    // ---------------------------------------------------------------------------------------------
    // uc：英雄王座排行 / 邀请码 / 长激活码 / 分享战报 / 注册信息
    // ---------------------------------------------------------------------------------------------

    public function testHerothroneUcTopRank(): void
    {
        $a = $this->player(['level' => 101]);
        $b = $this->player(['level' => 101]);
        $this->assertSame([], $this->request($a['auth'], 'herothrone', 'index')['list']);
        $this->assertSame(['status' => -1], UcModel::updateTopRank($a['uid'], 'x', 0));
        $list = UcModel::updateTopRank($a['uid'], 'c1_' . $a['uid'], 5, 10);
        $this->assertSame([['uid' => $a['uid'], 'nickname' => 'c1_' . $a['uid'], 'serverid' => 1, 'groupname' => '一组', 'stage' => 5]], $list);
        $this->assertSame(['status' => -2], UcModel::updateTopRank($a['uid'], 'c1_' . $a['uid'], 5, 10), '关卡没变化');
        UcModel::updateTopRank($b['uid'], 'c1_' . $b['uid'], 8, 10);
        $this->assertSame([$b['uid'], $a['uid']], array_column(UcModel::getTopRank(10), 'uid'));
        $index = $this->request($a['auth'], 'herothrone', 'index');
        $this->assertSame([['uid' => $b['uid'], 'nickname' => 'c1_' . $b['uid'], 'serverid' => 1, 'stage' => 8, 'servername' => '一组1服'], ['uid' => $a['uid'], 'nickname' => 'c1_' . $a['uid'], 'serverid' => 1, 'stage' => 5, 'servername' => '一组1服']], $index['list']);
        // 本服缓存 30 分钟
        UcModel::updateTopRank($a['uid'], 'c1_' . $a['uid'], 9, 10);
        $this->assertSame(8, $this->request($a['auth'], 'herothrone', 'index')['list'][0]['stage']);
        Redis::del('herothrone');
        $this->assertSame([$a['uid'], 9], [$this->request($a['auth'], 'herothrone', 'index')['list'][0]['uid'], $this->request($a['auth'], 'herothrone', 'index')['list'][0]['stage']]);
    }

    public function testInvitationThroughUc(): void
    {
        $a = $this->player(['level' => 50]);
        $b = $this->player(['level' => 50, 'gold' => 0, 'silver' => 0]);
        $c = $this->player(['level' => 50]);
        $codeA = $this->request($a['auth'], 'invitation', 'index')['code'];
        $this->assertSame($codeA, $this->request($a['auth'], 'invitation', 'index')['code'], '同一个码');
        $this->assertSame($codeA, DB::table('uc_invitation_code')->where('uid', $a['uid'])->value('code'));
        // 自己的码：错误
        $this->assertSame(3, $this->request($a['auth'], 'invitation', 'change', ['code' => $codeA])['status']);
        // B 用 A 的码：B 得 100 元宝 + 50 万银币，A 的人数 + 1 并收到信件
        $change = $this->request($b['auth'], 'invitation', 'change', ['code' => strtoupper($codeA)]);
        $this->assertSame(['status' => 1, 'all_silver' => 500000, 'all_gold' => 100, 'reward' => ['gold' => 100, 'silver' => 500000]], $change);
        $this->assertSame([1, $codeA], [(int)DB::table('invitation_code')->where('uid', $b['uid'])->value('status'), DB::table('invitation_code')->where('uid', $b['uid'])->value('to_code')]);
        $this->assertSame(1, $this->request($a['auth'], 'invitation', 'index')['number']);
        $this->assertSame(1, (int)DB::table('uc_invitation_code')->where('uid', $a['uid'])->value('number'));
        $this->assertSame('【官方1服】<em>c1_' . $b['uid'] . '</em>接受了您的邀请并达到50级，您的邀请好友数量+1。', DB::table('pm')->where('touid', $a['uid'])->where('type', 1)->orderBy('id', 'DESC')->value('message'));
        $this->assertSame(4, $this->request($b['auth'], 'invitation', 'change', ['code' => $codeA])['status'], '已被邀请过');
        // 一个码最多邀请 20 人
        DB::table('uc_invitation_code')->where('uid', $a['uid'])->update(['number' => 20]);
        $this->assertSame(2, $this->request($c['auth'], 'invitation', 'change', ['code' => $codeA])['status']);
        // uc 里已有邀请记录（例如本地状态丢失）：邀请码失效
        DB::table('uc_invitation_code')->where('uid', $a['uid'])->update(['number' => 1]);
        DB::table('invitation_code')->where('uid', $b['uid'])->update(['status' => 0]);
        $this->assertSame(2, $this->request($b['auth'], 'invitation', 'change', ['code' => $codeA])['status']);
    }

    public function testLongActivationCodeThroughUc(): void
    {
        $a = $this->player(['silver' => 0]);
        $b = $this->player();
        DB::table('uc_activationcode_type')->insert(['type' => 999, 'use_num' => 1, 'description' => 't']);
        DB::table('uc_activationcode_type')->insert(['type' => 555, 'use_num' => 0, 'description' => 'no reward']);
        foreach (['ABCDEFGH01' => 999, 'ABCDEFGH02' => 999, 'ABCDEFGH03' => 555, 'ABCDEFGH04' => 5] as $code => $type) {
            DB::table('uc_activationcode')->insert(['code' => $code, 'type' => $type, 'channel' => 1]);
        }
        $get = $this->request($a['auth'], 'activationcode', 'get_reward', ['code' => 'ABCDEFGH01']);
        $this->assertSame([1, 999, 1000000], [$get['status'], $get['reward']['type'], $get['all_silver']]);
        $this->assertSame(100, TraderLog::jade($a['uid'], 3));
        $this->assertSame([1, $a['uid']], [(int)DB::table('uc_activationcode')->where('code', 'ABCDEFGH01')->value('use_serverid'), (int)DB::table('uc_activationcode')->where('code', 'ABCDEFGH01')->value('use_uid')]);
        $this->assertSame(1, DB::table('activationcode_log')->where('uid', $a['uid'])->where('code', 'ABCDEFGH01')->count());
        $this->assertSame(-4, $this->request($b['auth'], 'activationcode', 'get_reward', ['code' => 'ABCDEFGH01'])['status'], '激活码已使用');
        $this->assertSame(-1, $this->request($a['auth'], 'activationcode', 'get_reward', ['code' => 'ABCDEFGH02'])['status'], '同类型只能用一个');
        $this->assertSame(-3, $this->request($a['auth'], 'activationcode', 'get_reward', ['code' => 'ABCDEFGH03'])['status'], '本服没有该类型的奖励');
        $this->assertSame(-3, $this->request($a['auth'], 'activationcode', 'get_reward', ['code' => 'ABCDEFGH04'])['status'], 'uc 没有该类型');
        $this->assertSame(-3, $this->request($a['auth'], 'activationcode', 'get_reward', ['code' => 'ZZZZZZZZ99'])['status'], '不存在');
    }

    public function testReportSaveThroughUcAndFetchMemberInfo(): void
    {
        $p = $this->player();
        $id = 910000000 + $p['uid'];
        DB::table('pm_report')->insert(['id' => $id, 'report' => '{"assess":1}', 'nickname' => 'a', 'userlevel' => 1, 'name' => 'b', 'level' => 1]);
        Config::set('app.report_url', 'https://share.example/r/');
        $save = $this->request($p['auth'], 'report', 'save', ['type' => 'arena', 'id' => $id]);
        $row = DB::table('uc_report')->where('type', 'arena')->where('reportid', (string)$id)->first();
        $this->assertSame(['status' => 1, 'link' => 'https://share.example/r/' . $row['id']], $save);
        $this->assertSame(['status' => 1, 'battle' => ['assess' => 1, 'left_role' => ['name' => 'a', 'level' => 1], 'right_role' => ['name' => 'b', 'level' => 1]], 'info' => ['win' => 1]], json_decode((string)$row['report'], true));
        $this->assertSame($save, $this->request($p['auth'], 'report', 'save', ['type' => 'arena', 'id' => $id]), '同一战报同一链接');
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'report', 'save', ['type' => 'gvg', 'id' => '1_1']), '群服争霸没有该场战报');
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'report', 'save', ['type' => 'worldarena', 'id' => 999999]));

        // 注册：记录手机号、设备、渠道、版本（各语言表单占位文字按空）
        $name = 'cr' . substr((string)hrtime(true), -8);
        $reg = $this->request(['token' => '', 'uid' => ''], 'register', 'reg', ['u' => $name, 'p' => 'pass1234', 'mobile' => '13800000000', 'mac' => '02:00:00:00:00:00', 'adid' => 'ADID-1', 'v' => '1.2.3', 'channel' => 7]);
        $user = DB::table('user')->where('uid', $reg['uid'])->first();
        $this->assertSame(['13800000000', '1.2.3', 'ADID-1', 7], [$user['phone'], $user['version'], $user['mac'], (int)$user['channel']]);
        $name2 = 'cr' . substr((string)hrtime(true), -8);
        $reg2 = $this->request(['token' => '', 'uid' => ''], 'register', 'reg', ['u' => $name2, 'p' => 'pass1234', 'mobile' => '僅用於找回密碼']);
        $user2 = DB::table('user')->where('uid', $reg2['uid'])->first();
        $this->assertSame(['', 106], [$user2['phone'], (int)$user2['channel']]);
    }

    public function testNewgiftNeedsNewServerOpenTime(): void
    {
        $p = $this->player(['jointime' => time() - 86400]);
        $this->assertSame(1, $this->request($p['auth'], 'newgift', 'index')['status']);
        // uc 的开服时间早于 2014-02-21：老服不开启
        DB::table('uc_server')->where('id', 1)->update(['starttime' => (int)strtotime('2013-01-01')]);
        $this->assertSame(['status' => 3, 'popo' => 1, 'msg' => '您所在的服活动未开启'], $this->request($p['auth'], 'newgift', 'index'));
        $this->assertSame(['status' => 3, 'popo' => 1, 'msg' => '您所在的服活动未开启'], $this->request($p['auth'], 'newgift', 'get_reward', ['id' => 1]));
        $this->assertSame(0, $this->request($p['auth'], 'member', 'index')['newgift']);
    }
}
