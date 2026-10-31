<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\Cron\FlopCron;
use Hanjiang\MasterData\ActivityTime;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Tests\Support\Gateway;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use PHPUnit\Framework\TestCase;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * 开发者活动时间开关（Model\ActivityClock，环境变量 ACTIVITY_OPEN / ACTIVITY_OPEN_DAYS）
 */
final class ActivityClockTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('ACTIVITY_OPEN');
        putenv('ACTIVITY_OPEN_DAYS');
        ActivityClock::reset();
    }

    private static function open(string $value, ?string $days = null): void
    {
        putenv('ACTIVITY_OPEN=' . $value);
        putenv($days === null ? 'ACTIVITY_OPEN_DAYS' : 'ACTIVITY_OPEN_DAYS=' . $days);
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
            $this->assertIsArray($json, "{$controller}.{$method}: " . $res->rawBody());
            $res = $json;
        }
        $this->assertIsArray($res);
        return $res;
    }

    /**
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

    public function testOffReturnsLegacyRows(): void
    {
        foreach ([null, ''] as $env) {
            putenv($env === null ? 'ACTIVITY_OPEN' : 'ACTIVITY_OPEN=' . $env);
            ActivityClock::reset();
            $this->assertSame(ActivityTime::ROWS, ActivityClock::rows());
            $this->assertNull(ActivityClock::open('sacredtree', time()));
            $this->assertSame(ActivityTime::ROWS[5], ActivityClock::byName('flop'));
        }
        $this->assertNull(ActivityClock::byName('no_such_activity'));
    }

    public function testNamedActivitiesOpenAndInterfacesUseOpenFlow(): void
    {
        self::open(' sacredtree, flop ,blackmarket,no_such_activity', '7');
        $now = time();
        $today = (int)strtotime(date('Y-m-d', $now));
        foreach (['sacredtree', 'flop', 'blackmarket'] as $name) {
            $row = ActivityClock::open($name, $now);
            $this->assertNotNull($row, $name);
            $legacy = array_values(array_filter(ActivityTime::ROWS, static fn(array $r): bool => $r['name'] === $name))[0];
            $this->assertSame($today, $row['starttime']);
            $this->assertEqualsWithDelta($now + 7 * 86400, $row['endtime'], 5);
            $this->assertSame([$legacy['id'], $legacy['version'], $legacy['type']], [$row['id'], $row['version'], $row['type']]);
        }
        // 未点名的活动与不认识的名字不受影响
        $this->assertNull(ActivityClock::open('exercise', $now));
        $this->assertSame(count(ActivityTime::ROWS), count(ActivityClock::rows()));
        // 窗口在本进程内固定
        $this->assertSame(ActivityClock::byName('flop'), ActivityClock::byName('flop'));

        $p = $this->player(['level' => 40, 'gold' => 1000, 'reputation' => 7]);
        $tree = $this->request($p['auth'], 'sacredtree', 'index');
        $this->assertSame(1, $tree['status']);
        $flop = $this->request($p['auth'], 'flop', 'index');
        $this->assertSame(1, $flop['status']);
        $this->assertEqualsWithDelta(7 * 86400, $flop['cd'], 5);
        $market = $this->request($p['auth'], 'blackmarket', 'index');
        $this->assertSame(1, $market['status']);
        $this->assertSame(3, count($market['list']));
        // exercise 未点名：照旧回活动已结束
        $this->assertSame(-2, $this->request($p['auth'], 'exercise', 'index')['status']);
        // 窗口未结束：结束后发放的计划任务回「活动未结束」，不会结算
        $this->assertSame(-2, FlopCron::pushReward($now));
    }

    public function testAllOpensOnlyPortedActivities(): void
    {
        self::open('ALL');
        $now = time();
        $legacyNames = array_column(ActivityTime::ROWS, 'name');
        foreach (ActivityClock::OPENABLE as $name) {
            $this->assertContains($name, $legacyNames);
            $this->assertTrue(ActivityClock::isOpen($name, $now), $name);
        }
        $this->assertEqualsWithDelta($now + 30 * 86400, ActivityClock::byName('compete')['endtime'], 5);
        // 节日 / 跨服 / 未移植的活动不开
        foreach (['god', 'lantern', 'christmaspay', 'christmas', 'mooncake', 'double_strengthen'] as $name) {
            $this->assertNotContains($name, ActivityClock::OPENABLE);
            $this->assertFalse(ActivityClock::isOpen($name, $now), $name);
        }

        // 主城活动图标随之开启
        $p = $this->player(['level' => 60]);
        $this->assertSame(1, $this->request($p['auth'], 'exercise', 'index')['status']);
        $flags = $this->request($p['auth'], 'member', 'index');
        $flags = $flags['member'] ?? $flags;
        // 群服争霸的主城图标按每月 15 ~ 22 日计时，不看活动表
        $this->assertSame([1, 1, 0], [$flags['sacredtree'], $flags['exercise'], $flags['christmaspay']]);
    }
}
