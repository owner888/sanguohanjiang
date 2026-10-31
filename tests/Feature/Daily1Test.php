<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\Handler\Activity\MessageHandler;
use Hanjiang\MasterData\MainQuest;
use Hanjiang\MasterData\Task;
use Hanjiang\Model\DailyTask;
use Hanjiang\Model\FlowLog;
use Hanjiang\Model\MainQuestTask;
use Hanjiang\Model\Member;
use Hanjiang\Model\Message;
use Hanjiang\Model\StatPay;
use Hanjiang\Tests\Support\Gateway;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use PHPUnit\Framework\TestCase;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * 信件（c=message）、新 GM 信箱（c=gmmail）、他人资料（c=information 补齐的 6 个接口）、日常任务（c=task）、
 * 主线任务（c=mainquest）与货币流水 / 付费点统计
 */
final class Daily1Test extends TestCase
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
        $member += ['nickname' => 'd1_' . $uid];
        DB::table('member')->where('uid', $uid)->update($member);
        Redis::del('player:' . $uid);
        return ['uid' => $uid, 'auth' => ['token' => (string)$login['token'], 'uid' => $uid]];
    }

    private static function memberValue(int $uid, string $column): int
    {
        return (int)DB::table('member')->where('uid', $uid)->value($column);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rows(string $table, int $uid, string $column = 'uid'): array
    {
        $rows = DB::table($table)->where($column, $uid)->orderBy('id')->get();
        return array_map(static fn (array $m): array => array_map(
            static fn ($v) => is_string($v) && preg_match('/^-?\d+$/', $v) ? (int)$v : $v,
            array_diff_key($m, ['created_at' => 1, 'updated_at' => 1])
        ), $rows);
    }

    // ---------------------------------------------------------------------------------------------
    // 信件
    // ---------------------------------------------------------------------------------------------

    public function testPlayerLetterInboxOutboxReadAndDelete(): void
    {
        $a = $this->player(['vip' => 3]);
        $b = $this->player();
        $bName = (string)DB::table('member')->where('uid', $b['uid'])->value('nickname');

        // 收信人不存在 / 是自己
        $this->assertSame(['status' => -1], $this->request($a['auth'], 'message', 'send_message', ['n' => 'nobody_x', 'i' => 'hi']));
        $aName = (string)DB::table('member')->where('uid', $a['uid'])->value('nickname');
        $this->assertSame(['status' => -1], $this->request($a['auth'], 'message', 'send_message', ['n' => $aName, 'i' => 'hi']));

        $send = $this->request($a['auth'], 'message', 'send_message', ['n' => $bName, 'i' => '<b>你好</b>，主公']);
        $this->assertSame(1, $send['status']);
        $this->assertSame($b['uid'], $send['uid']);
        $this->assertSame(date('m月d日 H:i'), $send['dateline']);
        $pm = self::rows('pm', $send['id']['id'], 'id')[0];
        $this->assertSame([5, $a['uid'], $aName, $b['uid'], $bName, 'VIP3', '&lt;b&gt;你好&lt;/b&gt;，主公', 0, 0], [
            $pm['type'], $pm['fromuid'], $pm['fromname'], $pm['touid'], $pm['toname'], $pm['title'], $pm['message'], $pm['isread'], $pm['status'],
        ]);
        // 收件人有新邮件提示
        $this->assertSame(1, self::memberValue($b['uid'], 'newmail'));
        $this->assertSame(1, $this->request($b['auth'], 'member', 'index')['newmail']);

        // 收件箱列表：内容截取，第一页带第一封全文并标为已读、newmail 清 0
        $inbox = $this->request($b['auth'], 'message', 'get_message', ['t' => 1, 'f' => 0, 'p' => 1, 'per' => 6]);
        $this->assertSame(1, $inbox['status']);
        $this->assertSame([['id' => $pm['id'], 'isread' => 1, 'status' => 0, 'type' => 5, 'name' => $aName, 'message' => '&lt;b&gt;你好&lt;/...']], $inbox['list']);
        $this->assertSame($pm['id'], $inbox['info']['id']);
        $this->assertSame($a['uid'], $inbox['info']['fromuid']);
        $this->assertSame('&lt;b&gt;你好&lt;/b&gt;，主公', $inbox['info']['message']);
        $this->assertArrayNotHasKey('isread', $inbox['info']);
        $this->assertSame(1, (int)DB::table('pm')->where('id', $pm['id'])->value('isread'));
        $this->assertSame(0, self::memberValue($b['uid'], 'newmail'));

        // 发件箱：name 为收件人，info.fromuid 为 0
        $outbox = $this->request($a['auth'], 'message', 'get_message', ['t' => 1, 'f' => 1]);
        $this->assertSame($bName, $outbox['list'][0]['name']);
        $this->assertSame(0, $outbox['info']['fromuid']);
        // 没有信件
        $this->assertSame(['status' => 0, 'list' => []], $this->request($a['auth'], 'message', 'get_message', ['t' => 1, 'f' => 0]));

        // 读单封（index 带 id）
        $one = $this->request($b['auth'], 'message', 'index', ['id' => $pm['id'], 't' => 2]);
        $this->assertSame(1, $one['status']);
        $this->assertSame($aName, $one['list']['name']);
        $this->assertSame(date('Y-m-d H:i', $pm['dateline']), $one['list']['dateline']);
        foreach (['toname', 'reply', 'fromname', 'touid'] as $gone) {
            $this->assertArrayNotHasKey($gone, $one['list']);
        }
        // 别人的信读不到
        $this->assertSame(['status' => 0, 'list' => []], $this->request($a['auth'], 'message', 'index', ['id' => $pm['id']]));

        // 收件方删除：标为收件方删除（3），收件箱不再显示；发件方再删：双方都删且已读 → 删除
        $this->assertSame(['status' => 1], $this->request($b['auth'], 'message', 'del_message', ['id' => $pm['id'], 't' => 1, 'f' => 0]));
        $this->assertSame(3, (int)DB::table('pm')->where('id', $pm['id'])->value('status'));
        $this->assertSame(0, $this->request($b['auth'], 'message', 'get_message', ['t' => 1, 'f' => 0])['status']);
        $this->assertSame(1, $this->request($a['auth'], 'message', 'get_message', ['t' => 1, 'f' => 1])['status']);
        $this->assertSame(['status' => 1], $this->request($a['auth'], 'message', 'del_message', ['id' => $pm['id'], 't' => 1, 'f' => 1]));
        $this->assertNotNull(DB::table('pm')->where('id', $pm['id'])->value('deleted_at'));
        $this->assertSame(['status' => 0], $this->request($a['auth'], 'message', 'del_message', ['id' => $pm['id'], 't' => 1, 'f' => 1]));

        // 禁言玩家的信：收件方删除状态，不提示
        DB::table('member')->where('uid', $a['uid'])->update(['status' => 2]);
        $muted = $this->request($a['auth'], 'message', 'send_message', ['n' => $bName, 'i' => 'x']);
        $this->assertSame(3, (int)DB::table('pm')->where('id', $muted['id']['id'])->value('status'));
        $this->assertSame(0, self::memberValue($b['uid'], 'newmail'));
    }

    public function testSystemLetterBubbleGiftAndReadStatus(): void
    {
        $p = $this->player(['gold' => 100, 'silver' => 1000, 'act' => 10]);
        $uid = $p['uid'];
        // 系统信件带附件（旧版后台 / 计划任务发放），另一封没有附件
        $giftId = Message::send(['touid' => $uid, 'type' => 1, 'message' => '补偿', 'gold' => 50, 'silver' => 200, 'act' => 3, 'barcard' => 2,
            'fromuid' => 1000, 'fromname' => '系统通知'], ['uid' => 1000, 'nickname' => '系统通知']);
        $plainId = MessageHandler::sendSystemMessage([$uid], 1, '系统通知', '公告');
        $this->assertSame([0, 1], [(int)DB::table('pm')->where('id', $giftId)->value('giftstatus'), (int)DB::table('pm')->where('id', $plainId)->value('giftstatus')]);
        $this->assertSame(1, self::memberValue($uid, 'newpm'));
        $this->assertSame(1, $this->request($p['auth'], 'member', 'index')['newpm']);

        // 气泡信件：未读的按类型列出
        $bubble = $this->request($p['auth'], 'message', 'index');
        $this->assertSame(['status' => 1, 'list' => [1 => [$giftId, $plainId]]], $bubble);

        // 读附件信件：带附件字段；还有未读的气泡信件时 newpm 不清
        $read = $this->request($p['auth'], 'message', 'index', ['id' => $giftId]);
        $this->assertSame([50, 200, 3, 2, 0], [$read['list']['gold'], $read['list']['silver'], $read['list']['act'], $read['list']['barcard'], $read['list']['giftstatus']]);
        $this->assertSame('系统通知', $read['list']['name']);
        $this->assertSame(1, self::memberValue($uid, 'newpm'));

        // 领取附件
        $freeleap = (int)DB::table('member_field')->where('uid', $uid)->value('freeleap');
        $open = $this->request($p['auth'], 'message', 'open_gift', ['id' => $giftId]);
        $this->assertSame(['status' => 1, 'info' => [
            'add_gold' => 50, 'add_silver' => 200, 'add_act' => 3, 'add_barcard' => 2,
            'all_silver' => 1200, 'all_gold' => 150, 'all_act' => 13,
        ]], $open);
        $this->assertSame($freeleap + 2, (int)DB::table('member_field')->where('uid', $uid)->value('freeleap'));
        // 领取的流水：class / method 为当前请求
        $gold = self::rows('gold_log', $uid);
        $this->assertSame([1, 50, 150, 'message', 'open_gift'], [end($gold)['type'], end($gold)['gold'], end($gold)['balance'], end($gold)['class'], end($gold)['method']]);
        // 已领取 / 没有礼包 / 没有信件 / 不是自己的
        $this->assertSame(['status' => -4], $this->request($p['auth'], 'message', 'open_gift', ['id' => $giftId]));
        $this->assertSame(['status' => -3], $this->request($p['auth'], 'message', 'open_gift', ['id' => $plainId]));
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'message', 'open_gift', ['id' => 99999999]));
        $other = $this->player();
        $this->assertSame(['status' => -1], $this->request($other['auth'], 'message', 'open_gift', ['id' => $giftId]));

        // 全部标为已读：没有未读的气泡信件时 newpm 清 0（旧版不输出内容，信封补 status 1）
        $this->assertSame(['status' => 1], $this->request($p['auth'], 'message', 'update_read_status', ['type' => 1]));
        $this->assertSame(1, (int)DB::table('pm')->where('id', $plainId)->value('isread'));
        $this->assertSame(0, self::memberValue($uid, 'newpm'));
        $this->assertSame(['status' => 0, 'list' => []], $this->request($p['auth'], 'message', 'index'));

        // 公告 / 每日动态由 uc 服务器提供（未移植），按 uc 没有返回数据处理
        $this->assertSame(['status' => 0], $this->request($p['auth'], 'message', 'get_notice'));
        $this->assertSame(['status' => 1, 'list' => null], $this->request($p['auth'], 'message', 'get_new_trends'));
        $this->assertSame(['status' => 0], $this->request($p['auth'], 'message', 'update_trends_status'));
    }

    public function testCountryLetter(): void
    {
        $king = $this->player(['gold' => 150]);
        $member = $this->player();
        $other = $this->player();
        $now = time();
        $c1 = (int)DB::table('country')->insertGetId(['name' => '蜀' . $king['uid'], 'level' => 1, 'member' => 2, 'uid' => $king['uid'], 'nickname' => 'k']);
        $c2 = (int)DB::table('country')->insertGetId(['name' => '吴' . $king['uid'], 'level' => 1, 'member' => 1, 'uid' => $other['uid'], 'nickname' => 'o']);
        foreach ([[$king, $c1, 1], [$member, $c1, 0], [$other, $c2, 1]] as [$p, $cid, $job]) {
            DB::table('member')->where('uid', $p['uid'])->update(['country' => $cid]);
            DB::table('member_country')->insert(['uid' => $p['uid'], 'country' => $cid, 'job' => $job, 'joindateline' => $now]);
        }
        // 不是君主
        $this->assertSame(['status' => -2], $this->request($member['auth'], 'message', 'send_message', ['t' => 2, 'n' => '蜀' . $king['uid'], 'i' => 'x']));
        // 国家不存在
        $this->assertSame(['status' => -3], $this->request($king['auth'], 'message', 'send_message', ['t' => 2, 'n' => '魏', 'i' => 'x']));
        // 本国：免费群发给全体成员（含自己）
        $own = $this->request($king['auth'], 'message', 'send_message', ['t' => 2, 'n' => '蜀' . $king['uid'], 'i' => '集合']);
        $this->assertSame(1, $own['status']);
        $kingName = (string)DB::table('member')->where('uid', $king['uid'])->value('nickname');
        $this->assertSame($kingName, $own['king']);
        $this->assertEqualsCanonicalizing([$king['uid'], $member['uid']], $own['uid']);
        $this->assertSame(2, DB::table('pm')->where('type', 10)->where('fromuid', $king['uid'])->count());
        // 收件箱：name 为国家名、king 为君主
        $inbox = $this->request($member['auth'], 'message', 'get_message', ['t' => 1, 'f' => 0]);
        $this->assertSame(['蜀' . $king['uid'], $kingName], [$inbox['list'][0]['name'], $inbox['list'][0]['king']]);
        // 国家信箱的发件箱一次群发只列一条
        $this->assertCount(1, $this->request($king['auth'], 'message', 'get_message', ['t' => 4, 'f' => 1])['list']);
        // 别国：先确认价格，再扣 100 元宝
        $this->assertSame(['status' => -4, 'price' => 100], $this->request($king['auth'], 'message', 'send_message', ['t' => 2, 'n' => '吴' . $king['uid'], 'i' => 'x']));
        $this->assertSame(1, $this->request($king['auth'], 'message', 'send_message', ['t' => 2, 'n' => '吴' . $king['uid'], 'i' => 'x', 'sure' => 1])['status']);
        $this->assertSame(50, self::memberValue($king['uid'], 'gold'));
        $this->assertSame(['status' => -5], $this->request($king['auth'], 'message', 'send_message', ['t' => 2, 'n' => '吴' . $king['uid'], 'i' => 'x', 'sure' => 1]));
    }

    public function testChallengeLetterReportReplay(): void
    {
        $a = $this->player(['level' => 30]);
        $b = $this->player(['level' => 28]);
        $res = $this->request($a['auth'], 'challenge', 'action', ['uid' => $b['uid']]);
        $this->assertSame(1, $res['status']);
        $pmid = $res['info']['report'];
        // 信件：type 4，发件人为挑战者
        $pm = self::rows('pm', $pmid, 'id')[0];
        $this->assertSame([4, $a['uid'], $b['uid']], [$pm['type'], $pm['fromuid'], $pm['touid']]);
        // 战报回放
        $detail = $this->request($b['auth'], 'message', 'report_detail', ['id' => $pmid]);
        $this->assertSame(1, $detail['status']);
        $this->assertSame($res['battle']['process'], $detail['battle']['process']);
        $this->assertSame(['name' => 'd1_' . $a['uid'], 'level' => 30], $detail['battle']['left_role']);
        $this->assertSame(['name' => 'd1_' . $b['uid'], 'level' => 28], $detail['battle']['right_role']);
        $this->assertSame(['win' => $res['battle']['assess']], $detail['info']);
        $this->assertSame(['status' => -1, 'battle' => [], 'info' => []], $this->request($b['auth'], 'message', 'report_detail', ['id' => 0]));
    }

    // ---------------------------------------------------------------------------------------------
    // 新 GM 信箱
    // ---------------------------------------------------------------------------------------------

    public function testGmMailbox(): void
    {
        $p = $this->player(['vip' => 2]);
        $send = $this->request($p['auth'], 'gmmail', 'send_message', ['i' => '充值没到账']);
        $this->assertSame(1, $send['status']);
        $id = $send['id']['id'];
        $gm = self::rows('gm', $id, 'id')[0];
        $this->assertSame([$p['uid'], 1000, 'GM', 6, 'VIP2', '充值没到账', 1, 0], [
            $gm['fromuid'], $gm['touid'], $gm['toname'], $gm['type'], $gm['title'], $gm['message'], $gm['isread'], $gm['status'],
        ]);
        $this->assertSame(0, DB::table('pm')->where('fromuid', $p['uid'])->count());

        $list = $this->request($p['auth'], 'gmmail', 'get_message', ['p' => 1, 'per' => 6]);
        $this->assertSame([['id' => $id, 'isread' => 1, 'status' => 0, 'type' => 6, 'name' => 'GM', 'message' => '充值没到账']], $list['list']);
        $this->assertSame(['id' => $id, 'type' => 6, 'status' => 0, 'message' => '充值没到账', 'dateline' => date('m月d日 h:i', $gm['dateline']), 'name' => 'GM'], $list['info']);
        $one = $this->request($p['auth'], 'gmmail', 'index', ['id' => $id]);
        $this->assertSame([1, 'GM', 0, 0], [$one['status'], $one['list']['name'], $one['list']['reply_uid'], $one['list']['grade']]);

        // 评分：分数不对 / 信件不存在 / GM 未回复 / 成功 / 已评过
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'gmmail', 'grade', ['id' => $id, 'grade' => 6]));
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'gmmail', 'grade', ['id' => 99999999, 'grade' => 5]));
        $this->assertSame(['status' => -3], $this->request($p['auth'], 'gmmail', 'grade', ['id' => $id, 'grade' => 5]));
        DB::table('gm')->where('id', $id)->update(['reply' => '已补发', 'reply_uid' => 1, 'status' => 1]);
        $this->assertSame(['status' => 1], $this->request($p['auth'], 'gmmail', 'grade', ['id' => $id, 'grade' => 5]));
        $this->assertSame(5, (int)DB::table('gm')->where('id', $id)->value('grade'));
        $this->assertSame(['status' => -4], $this->request($p['auth'], 'gmmail', 'grade', ['id' => $id, 'grade' => 4]));
        $this->assertStringEndsWith('----以下是GM回复内容----<br/>已补发', $this->request($p['auth'], 'gmmail', 'index', ['id' => $id])['list']['message']);

        // 删除：标为发件方删除
        $this->assertSame(['status' => 1], $this->request($p['auth'], 'gmmail', 'del_message', ['id' => $id]));
        $this->assertSame(2, (int)DB::table('gm')->where('id', $id)->value('status'));
        $this->assertSame(0, $this->request($p['auth'], 'gmmail', 'get_message')['status']);
    }

    // ---------------------------------------------------------------------------------------------
    // 他人资料
    // ---------------------------------------------------------------------------------------------

    public function testInformationOfOtherPlayer(): void
    {
        $viewer = $this->player();
        $p = $this->player(['level' => 60]);
        $uid = $p['uid'];
        $gid = (int)DB::table('general')->where('uid', $uid)->value('id');
        $auth = $viewer['auth'];

        // 将魂：对方 50 级以上建将魂槽
        $this->assertSame(['status' => -1, 'general' => []], $this->request($auth, 'information', 'get_own_soul', ['uid' => 0, 'gid' => $gid]));
        $this->assertSame(['status' => -2, 'general' => []], $this->request($auth, 'information', 'get_own_soul', ['uid' => $uid, 'gid' => 0]));
        $soul = $this->request($auth, 'information', 'get_own_soul', ['uid' => $uid, 'gid' => $gid]);
        $this->assertSame(1, $soul['status']);
        $this->assertSame($gid, $soul['general']['id']);
        $turn = (int)DB::table('general')->where('id', $gid)->value('turn');
        $unlock = \Hanjiang\Model\OwnSoul::unlockSite($turn);
        for ($i = 1; $i <= 8; $i++) {
            $expect = $i > $unlock ? ['islock' => 1, 'unlock_level' => \Hanjiang\Model\OwnSoul::unlockLevel($i)] : ['islock' => 0, 'info' => []];
            $this->assertSame($expect, $soul['general']['site' . $i]);
        }
        $this->assertSame(1, DB::table('own_general_soul')->where('gid', $gid)->count());

        // 主公天赋：没有记录时建档
        $this->assertSame(['status' => -1], $this->request($auth, 'information', 'get_talent_list', ['uid' => 0]));
        $this->assertSame(['status' => -2], $this->request($auth, 'information', 'get_talent_list', ['uid' => 99999999]));
        $talent = $this->request($auth, 'information', 'get_talent_list', ['uid' => $uid]);
        $this->assertSame(1, $talent['status']);
        $this->assertCount(5, $talent['talent']);

        // 宝石：将领没有装备
        $this->assertSame(['status' => -1], $this->request($auth, 'information', 'get_general_gem', ['uid' => $uid]));
        $this->assertSame(['status' => -2], $this->request($auth, 'information', 'get_general_gem', ['uid' => $uid, 'gid' => 99999999]));
        $this->assertSame(['status' => 2], $this->request($auth, 'information', 'get_general_gem', ['uid' => $uid, 'gid' => $gid]));

        // 坐骑：没有坐骑时加成为 0
        $this->assertSame(['status' => -1], $this->request($auth, 'information', 'get_general_mount', ['uid' => 0, 'gid' => $gid]));
        $mount = $this->request($auth, 'information', 'get_general_mount', ['uid' => $uid, 'gid' => $gid]);
        $this->assertSame(1, $mount['status']);
        $this->assertSame([0, 0, 0, 0, 0], [$mount['general']['mid0'], $mount['general']['attack'], $mount['general']['wufang'], $mount['general']['cefang'], $mount['general']['hp']]);

        // 国家学院：没有国家 / 加入国家未满 3 天
        $this->assertSame(['status' => -1, 'msg' => '参数错误'], $this->request($auth, 'information', 'get_country_info', ['uid' => 0]));
        $this->assertSame(['status' => 1, 'skill_list' => [], 'skill_buffer' => [], 'is_open' => 0, 'cd' => 0], $this->request($auth, 'information', 'get_country_info', ['uid' => $uid]));
        DB::table('member_country')->insert(['uid' => $uid, 'country' => 1, 'joindateline' => time() - 3600]);
        $country = $this->request($auth, 'information', 'get_country_info', ['uid' => $uid]);
        $this->assertSame([0, 259200 - 3600], [$country['is_open'], $country['cd']]);

        // 星魄：未点亮的只列 1 级命格
        $this->assertSame(['status' => -1, 'list' => [], 'msg' => '基础数据错误！'], $this->request($auth, 'information', 'get_star_info', ['uid' => 0]));
        $star = $this->request($auth, 'information', 'get_star_info', ['uid' => $uid]);
        $this->assertSame(1, $star['status']);
        $first = $star['list'][1][1];
        $this->assertSame([0, 0], [$first['level'], $first['open_status']]);
    }

    // ---------------------------------------------------------------------------------------------
    // 日常任务
    // ---------------------------------------------------------------------------------------------

    public function testDailyTaskFlow(): void
    {
        $low = $this->player(['level' => 40]);
        $this->assertSame(['status' => -1], $this->request($low['auth'], 'task', 'index'));

        $p = $this->player(['level' => 45, 'gold' => 100, 'silver' => 0, 'vip' => 1]);
        $uid = $p['uid'];
        // 没有记录时领取 / 放弃 / 刷新 / 领奖
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'task', 'accept_task', ['s' => 1]));
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'task', 'refresh'));

        // 第一次进入：随机 3 个任务、剩余 6 次、免费刷新 1 次
        $index = $this->request($p['auth'], 'task', 'index');
        $this->assertSame(1, $index['status']);
        $this->assertSame([6, 1, 0, 0, 0], [$index['number'], $index['free_times'], $index['taskstatus1'], $index['taskstatus2'], $index['taskstatus3']]);
        for ($i = 1; $i <= 3; $i++) {
            $task = $index['taskid' . $i];
            $tpl = Task::ROWS[$task['id']];
            $this->assertSame($tpl['silver'] * 45, $task['silver']);
            $this->assertSame([$tpl['equip_type'], $tpl['equip_id'], $tpl['equip_num']], [$task['get_type'], $task['get_value'], $task['get_num']]);
            $this->assertArrayNotHasKey('equip_id', $task);
            $this->assertNotSame(4, $tpl['type'], '61 级以下不出每日通商');
        }

        // 固定任务：位置 1 为元宝消费 1 次（id 5），位置 2 为元宝消费 3 次（id 16）
        DB::table('task_log')->where('uid', $uid)->update(['taskid1' => 5, 'taskid2' => 16, 'taskid3' => 10]);
        $this->assertSame(['status' => 1], $this->request($p['auth'], 'task', 'accept_task', ['s' => 2]));
        $this->assertSame(['status' => -3], $this->request($p['auth'], 'task', 'accept_task', ['s' => 1]));
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'task', 'get_reward', ['s' => 2]));
        // 不是进行中任务的类型不计
        $this->assertSame(-4, DailyTask::action($uid, DailyTask::TYPE_ARENA));
        // 元宝消费（Member::deductCurrency → 日常任务 5）
        $this->assertTrue(Member::deductCurrency($uid, gold: 1));
        $this->assertSame(1, $this->request($p['auth'], 'task', 'index')['taskid2']['plan']);
        $this->assertSame(['status' => 2, 'number' => 6, 'name' => '元宝消费', 'value' => 3, 'type' => 5, 'plan' => 1], $this->request($p['auth'], 'member', 'index')['daily_task']);
        // 放弃后不再计数；重新领取进度从 0 开始
        $this->assertSame(['status' => 1], $this->request($p['auth'], 'task', 'give_up'));
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'task', 'give_up'));
        $this->assertSame(-3, DailyTask::action($uid, DailyTask::TYPE_GOLD));
        $this->assertSame(['status' => 1], $this->request($p['auth'], 'task', 'accept_task', ['s' => 1]));
        $this->assertTrue(Member::deductCurrency($uid, gold: 1));
        $this->assertSame(2, (int)DB::table('task_log')->where('uid', $uid)->value('taskstatus1'));

        // 领奖：银币 500 × 45，剩余次数 5，该位置换新任务；主线 12 / 13
        $reward = $this->request($p['auth'], 'task', 'get_reward', ['s' => 1]);
        $this->assertSame(1, $reward['status']);
        $this->assertSame([5, 0, 0, 0, 22500, 22500], [$reward['number'], $reward['get_type'], $reward['get_value'], $reward['get_num'], $reward['silver'], $reward['all_silver']]);
        $this->assertSame([1], array_keys($reward['list']));
        $newTask = (int)DB::table('task_log')->where('uid', $uid)->value('taskid1');
        $this->assertSame($newTask, $reward['list'][1]['id']);
        $this->assertSame(0, (int)DB::table('task_log')->where('uid', $uid)->value('taskstatus1'));
        $this->assertSame(5, (int)DB::table('member_activity')->where('uid', $uid)->value('task_number'));
        $silverLog = self::rows('silver_log', $uid);
        $this->assertSame([1, 22500, 22500, 'task', 'get_reward'], [end($silverLog)['type'], end($silverLog)['silver'], end($silverLog)['balance'], end($silverLog)['class'], end($silverLog)['method']]);

        // 刷新：第一次免费，之后 5 元宝；未领取的位置都换
        $gold = self::memberValue($uid, 'gold');
        $refresh = $this->request($p['auth'], 'task', 'refresh');
        $this->assertSame([1, 5, $gold], [$refresh['status'], $refresh['price'], $refresh['all_gold']]);
        $this->assertSame([1, 2, 3], array_keys($refresh['list']));
        $this->assertSame(0, (int)DB::table('task_log')->where('uid', $uid)->value('free_times'));
        $paid = $this->request($p['auth'], 'task', 'refresh');
        $this->assertSame($gold - 5, $paid['all_gold']);
        // 付费点统计 task_refresh（主公 16 级以上）
        $this->assertGreaterThanOrEqual(1, (int)DB::table('stat_pay')->where('name', 'task_refresh')->value('times'));
        DB::table('member')->where('uid', $uid)->update(['gold' => 4]);
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'task', 'refresh'));

        // 次数用完不能领取
        DB::table('member_activity')->where('uid', $uid)->update(['task_number' => 0]);
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'task', 'accept_task', ['s' => 1]));
        $this->assertSame(['status' => 0], $this->request($p['auth'], 'member', 'index')['daily_task']);
        // 每日计划任务：剩余次数重置为 6；跨天第一次进入免费刷新次数重置为 1
        DB::table('member_field')->where('uid', $uid)->update(['dateline' => 0]);
        Redis::del('daily_cron:' . $uid);
        DB::table('task_log')->where('uid', $uid)->update(['dateline' => time() - 86400 * 2]);
        $again = $this->request($p['auth'], 'task', 'index');
        $this->assertSame([6, 1], [$again['number'], $again['free_times']]);
    }

    public function testDailyTaskItemReward(): void
    {
        $p = $this->player(['level' => 45]);
        $uid = $p['uid'];
        $this->request($p['auth'], 'task', 'index');
        // 3 星元宝消费 5 次：奖励装备碎片（太上法扇碎片 1 个）
        DB::table('task_log')->where('uid', $uid)->update(['taskid1' => 27, 'taskstatus1' => 2]);
        $index = $this->request($p['auth'], 'task', 'index');
        $this->assertSame(['太上法扇碎片', 4], [$index['taskid1']['itemname'], $index['taskid1']['quality']]);
        $this->assertArrayNotHasKey('equip_type', $index['taskid1']);
        $reward = $this->request($p['auth'], 'task', 'get_reward', ['s' => 1]);
        $this->assertSame([1, 17, 4, '太上法扇碎片', 1, 2, 17, 1], [
            $reward['status'], $reward['itemid'], $reward['quality'], $reward['itemname'], $reward['itemnum'], $reward['get_type'], $reward['get_value'], $reward['get_num'],
        ]);
        $this->assertSame(1, (int)DB::table('equipment')->where('uid', $uid)->where('equipid', 17)->value('chip'));
    }

    // ---------------------------------------------------------------------------------------------
    // 主线任务
    // ---------------------------------------------------------------------------------------------

    public function testMainquestListProgressAndReward(): void
    {
        $p = $this->player(['silver' => 0]);
        $uid = $p['uid'];
        $index = $this->request($p['auth'], 'mainquest', 'index');
        $this->assertSame(1, $index['status']);
        // 前置为 0 的 8 条任务线，按主线 / 支线、任务 id 排序
        $this->assertSame([78, 751, 201, 301, 401, 501, 601, 701], array_column($index['list'], 'task_id'));
        $first = $index['list'][0];
        $this->assertSame([1, 1, 0, 1, '击败周仓'], [$first['type'], $first['task_type'], $first['task_plan'], $first['task_value'], $first['name']]);
        $this->assertSame([['type' => 2, 'value' => 50000]], $first['reward']);
        $this->assertArrayHasKey('missionlevel', $first);
        $this->assertSame(8, DB::table('main_quest_log')->where('uid', $uid)->count());
        // 主城的任务条同一份列表
        $this->assertSame(array_column($index['list'], 'task_id'), array_column($this->request($p['auth'], 'member', 'index')['mainquest'], 'task_id'));

        // 任务不存在 / 未完成
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'mainquest', 'get_task_reward', ['id' => 202]));
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'mainquest', 'get_task_reward', ['id' => 201]));

        // 装备强化到 10 级（任务 201）：进度记为等级，达到目标可领取
        $this->assertSame(1, MainQuestTask::action($uid, 2, 6));
        $this->assertSame([6, 0], [(int)DB::table('main_quest_log')->where('uid', $uid)->where('task_id', 201)->value('task_plan'), (int)DB::table('main_quest_log')->where('uid', $uid)->where('task_id', 201)->value('status')]);
        $this->assertSame(1, MainQuestTask::action($uid, 2, 12));
        $this->assertSame([10, 1], [(int)DB::table('main_quest_log')->where('uid', $uid)->where('task_id', 201)->value('task_plan'), (int)DB::table('main_quest_log')->where('uid', $uid)->where('task_id', 201)->value('status')]);
        $this->assertSame(-1, MainQuestTask::action($uid, 2, 12));

        $reward = $this->request($p['auth'], 'mainquest', 'get_task_reward', ['id' => 201]);
        $this->assertSame(1, $reward['status']);
        $this->assertSame([['type' => 2, 'value' => 50000]], $reward['reward']);
        $this->assertSame(50000, $reward['all_silver']);
        $this->assertSame(50000, self::memberValue($uid, 'silver'));
        // 下一个任务（202 强化到 15 级）写到同一条任务线上
        $row = DB::table('main_quest_log')->where('uid', $uid)->where('task_type', 2)->first();
        $this->assertSame([202, 15, 0, 0], [(int)$row['task_id'], (int)$row['task_value'], (int)$row['task_plan'], (int)$row['status']]);
        $this->assertContains(202, array_column($reward['list'], 'task_id'));
        $this->assertSame(1, DB::table('main_quest_reward')->where('uid', $uid)->where('task_id', 201)->count());
        $silverLog = self::rows('silver_log', $uid);
        $this->assertSame(['mainquest', 'get_task_reward'], [end($silverLog)['class'], end($silverLog)['method']]);

        // 事件型任务（12 完成任意日常任务）：直接完成
        DB::table('main_quest_log')->where('uid', $uid)->where('task_id', 601)->update(['task_id' => (int)array_values(array_filter(MainQuest::ROWS, static fn (array $q): bool => $q['type'] === 12))[0]['id'], 'task_type' => 12, 'task_value' => 0]);
        $this->assertSame(1, MainQuestTask::action($uid, 12, 1));
        $this->assertSame([1, 1], [(int)DB::table('main_quest_log')->where('uid', $uid)->where('task_type', 12)->value('task_plan'), (int)DB::table('main_quest_log')->where('uid', $uid)->where('task_type', 12)->value('status')]);

        // 领奖记录已存在：跳到下一个任务，回 -4
        DB::table('main_quest_log')->where('uid', $uid)->where('task_id', 202)->update(['task_id' => 201, 'status' => 1]);
        $skip = $this->request($p['auth'], 'mainquest', 'get_task_reward', ['id' => 201]);
        $this->assertSame(-4, $skip['status']);
        $this->assertSame(202, (int)DB::table('main_quest_log')->where('uid', $uid)->where('task_type', 2)->value('task_id'));
        $this->assertSame(50000, self::memberValue($uid, 'silver'));
    }

    // ---------------------------------------------------------------------------------------------
    // 货币流水 / 付费点统计
    // ---------------------------------------------------------------------------------------------

    public function testCurrencyFlowLogs(): void
    {
        $p = $this->player(['level' => 20, 'vip' => 0, 'gold' => 100, 'silver' => 1000, 'act' => 10, 'reputation' => 50, 'soul' => 0]);
        $uid = $p['uid'];
        FlowLog::route('unit', 'case');
        try {
            $this->assertTrue(Member::deductCurrency($uid, silver: 100, gold: 10, act: 2));
            // 不足时全部不扣、不记
            $this->assertFalse(Member::deductCurrency($uid, gold: 1000));
            Member::addCurrency($uid, silver: 5, gold: 6, act: 7, reputation: 8, soul: 9);
            $this->assertSame(48, Member::subReputation($uid, 10));
            $this->assertNull(Member::subReputation($uid, 1000));
        } finally {
            FlowLog::route('', '');
        }
        $pick = static fn (array $rows, string $col): array => array_map(static fn (array $r): array => [$r['type'], $r[$col], $r['balance'], $r['class'], $r['method'], $r['level'], $r['vip']], $rows);
        $this->assertSame([[2, 10, 90, 'unit', 'case', 20, 0], [1, 6, 96, 'unit', 'case', 20, 0]], $pick(self::rows('gold_log', $uid), 'gold'));
        // VIP 0 花银币旧版不记流水，获得照记
        $this->assertSame([[1, 5, 905, 'unit', 'case', 20, 0]], $pick(self::rows('silver_log', $uid), 'silver'));
        $this->assertSame([[2, 2, 8, 'unit', 'case', 20, 0], [1, 7, 15, 'unit', 'case', 20, 0]], $pick(self::rows('act_log', $uid), 'act'));
        $this->assertSame([[1, 8, 58, 'unit', 'case', 20, 0], [2, 10, 48, 'unit', 'case', 20, 0]], $pick(self::rows('reputation_log', $uid), 'reputation'));
        $this->assertSame([[1, 9, 9, 'unit', 'case', 20, 0]], $pick(self::rows('usesoul_log', $uid), 'reputation'));
        // VIP 玩家花银币记流水
        DB::table('member')->where('uid', $uid)->update(['vip' => 2]);
        Member::deductCurrency($uid, silver: 5);
        $silverLog = self::rows('silver_log', $uid);
        $last = end($silverLog);
        $this->assertSame([2, 5, 900, 2], [$last['type'], $last['silver'], $last['balance'], $last['vip']]);

        // 付费点统计：16 级以下、不在付费点列表里的不记
        $before = (int)(DB::table('stat_pay')->where('name', 'drink')->value('times') ?? 0);
        StatPay::record($uid, 'drink', 20);
        StatPay::record($uid, 'countryboss_relive', 20);
        $this->assertSame($before + 1, (int)DB::table('stat_pay')->where('name', 'drink')->value('times'));
        $this->assertNull(DB::table('stat_pay')->where('name', 'countryboss_relive')->value('id'));
        DB::table('member')->where('uid', $uid)->update(['level' => 15]);
        StatPay::record($uid, 'drink', 20);
        $this->assertSame($before + 1, (int)DB::table('stat_pay')->where('name', 'drink')->value('times'));
    }
}
