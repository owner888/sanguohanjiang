<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\Cron\BusinessCron;
use Hanjiang\Handler\Activity\OverseasTradeHandler;
use Hanjiang\MasterData\CountryTaxesShop;
use Hanjiang\MasterData\CountryWorship;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\JadeGamble;
use Hanjiang\MasterData\JadePrice;
use Hanjiang\MasterData\Price;
use Hanjiang\MasterData\Shop;
use Hanjiang\MasterData\StoneRule;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\CountryDebris;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberIntegral;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\TraderLog;
use Hanjiang\Model\Vip;
use Hanjiang\Tests\Support\Gateway;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use PHPUnit\Framework\TestCase;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * 每日玩法第四批（经济 / 交易）：兵符商城（c=shop）、国家商店（c=countryshop）、国家税金商城（c=country_taxes_shop）、
 * 国家祭祀（c=countrysacrifice）、天神祭祀（c=sacrifice）、玉石作坊（c=workshop）、银矿（c=mine）、每日通商（c=business）、
 * 黑市（c=blackmarket）、赌石（c=gambling）、神矿（c=jadegamble）、海外贸易（c=overseastrade）按旧版的流程测试。
 *
 * 旧版数据中 sacrifice / blackmarket / jadegamble 活动结束于 2016-12-31，测试用 BossClock 固定到活动期间（1480000000）。
 */
final class Daily4Test extends TestCase
{
    /** 2016-11-24，sacrifice / blackmarket / jadegamble 活动期间 */
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
        $member += ['nickname' => 'd4_' . $uid];
        DB::table('member')->where('uid', $uid)->update($member);
        Redis::del('player:' . $uid);
        return ['uid' => $uid, 'auth' => ['token' => (string)$login['token'], 'uid' => $uid]];
    }

    /**
     * 建国家并让玩家加入（第一个为君主 job 1）
     *
     * @param list<array{uid: int}> $players
     */
    private static function country(array $players, int $level, int $joinAgo = 30 * 86400): int
    {
        $id = (int)DB::table('country')->insertGetId([
            'name' => '国' . substr((string)hrtime(true), -7), 'level' => $level, 'member' => count($players), 'uid' => $players[0]['uid'], 'nickname' => 'k',
        ]);
        foreach ($players as $i => $p) {
            DB::table('member')->where('uid', $p['uid'])->update(['country' => $id]);
            DB::table('member_country')->insert(['uid' => $p['uid'], 'country' => $id, 'job' => $i === 0 ? 1 : 10, 'joindateline' => time() - $joinAgo]);
            Redis::del('player:' . $p['uid']);
        }
        return $id;
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

    private static function emptyFormation(int $uid): void
    {
        $positions = [];
        for ($i = 1; $i <= 9; $i++) {
            $positions['position' . $i] = 0;
        }
        DB::table('matrix')->where('uid', $uid)->update($positions);
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
    // 兵符商城 / 军令采买
    // ---------------------------------------------------------------------------------------------

    public function testShopIndexInfoBuyLimitsAndCd(): void
    {
        foreach (Shop::ROWS as $row) {
            Redis::del('shop_item_' . $row['equipid'] . date('ymd'));
        }
        Redis::del('shop_buy_log_' . date('ymd'));
        $p = $this->player(['level' => 30, 'gold' => 10000, 'silver' => 0, 'vip' => 0]);
        $uid = $p['uid'];

        $index = $this->request($p['auth'], 'shop', 'index');
        $this->assertSame(1, $index['status']);
        $this->assertSame(12, $index['totals']);
        $this->assertSame([], $index['log_list']);
        // list 按 position 排序；字段同旧版（name / type / quality / need_viplevel / id / position）
        $first = Equip::ROWS[7];
        $this->assertSame(['name' => $first['name'], 'type' => $first['type'], 'quality' => 1, 'need_viplevel' => 0, 'id' => 7, 'position' => 1], $index['list']['1']);
        $this->assertSame(8, $index['list']['12']['need_viplevel']);
        // 默认选中第一件：商品行 + 模板字段（type 被装备类型覆盖、商品类型在 shoptype）
        $this->assertSame(1, $index['info']['status']);
        $this->assertSame([1, 7, 5, 1, 50, 50, 50, 0], [
            $index['info']['info']['position'], $index['info']['info']['equipid'], $index['info']['info']['type'],
            $index['info']['info']['shoptype'], $index['info']['info']['price'], $index['info']['info']['maxtimes'],
            $index['info']['info']['nowmaxtimes'], $index['info']['cd'],
        ]);
        $this->assertSame(-1, $this->request($p['auth'], 'shop', 'get_info', ['id' => 999])['status']);

        // VIP 不足（郎将兵符品质 3 要 VIP 2）
        $this->assertSame(['status' => -8], $this->request($p['auth'], 'shop', 'buy', ['id' => 11]));
        // 购买青玉兵符：扣 50 元宝，发装备，记付费点 / 全服购买记录，60 秒 CD
        $before = Equipment::query()->where('uid', $uid)->where('equipid', 7)->count();
        $buy = $this->request($p['auth'], 'shop', 'buy', ['id' => 7]);
        $this->assertSame(['status' => 1, 'maxtimes' => 49, 'gold' => 9950, 'cd' => 60], $buy);
        $this->assertSame($before + 1, Equipment::query()->where('uid', $uid)->where('equipid', 7)->count());
        $this->assertSame(1, DB::table('gold_log')->where('uid', $uid)->where('type', 2)->where('gold', 50)->where('class', 'shop')->count());
        $this->assertSame(1, (int)DB::table('stat_pay')->where('name', 'buy_bingfu')->count());
        $info = $this->request($p['auth'], 'shop', 'get_info', ['id' => 7]);
        $this->assertSame(49, $info['info']['nowmaxtimes']);
        $this->assertGreaterThan(55, $info['cd']);
        $log = $this->request($p['auth'], 'shop', 'index')['log_list'];
        $this->assertSame([$first['name'], 1, 'd4_' . $uid, $uid], [$log[0]['itemname'], $log[0]['quality'], $log[0]['username'], $log[0]['uid']]);
        // CD 中
        $this->assertSame(['status' => -3], $this->request($p['auth'], 'shop', 'buy', ['id' => 7]));
        // 全服今日限购（键不带 uid）：别人买完后 -6
        Redis::del('shop_cd_' . $uid);
        Redis::set('shop_item_7' . date('ymd'), 50, 86400);
        $this->assertSame(['status' => -6], $this->request($p['auth'], 'shop', 'buy', ['id' => 7]));
        $this->assertSame(50, (int)Redis::get('shop_item_7' . date('ymd')), '超限时减回');
        // 元宝不足
        Redis::del('shop_item_7' . date('ymd'));
        DB::table('member')->where('uid', $uid)->update(['gold' => 10]);
        $this->assertSame(['status' => -4], $this->request($p['auth'], 'shop', 'buy', ['id' => 7]));
        $this->assertSame(0, (int)Redis::get('shop_item_7' . date('ymd')), '元宝不足时不占次数');
    }

    public function testShopBuyActPriceAndDailyCount(): void
    {
        $p = $this->player(['level' => 30, 'gold' => 1000, 'act' => 0]);
        $uid = $p['uid'];
        // 不传 t 只返回价格（第 1 个；旧版没有 status，响应信封补 1）
        $this->assertSame(['price' => Price::ROWS[1]['act'], 'status' => 1], $this->request($p['auth'], 'shop', 'buy_act'));
        $res = $this->request($p['auth'], 'shop', 'buy_act', ['t' => 1]);
        $this->assertSame(['price' => Price::ROWS[2]['act'], 'gold' => 1000 - Price::ROWS[1]['act'], 'act' => 1, 'status' => 1], $res);
        $this->assertSame(1, self::fieldValue($uid, 'actnumber'));
        $this->assertSame(1, (int)DB::table('act_log')->where('uid', $uid)->where('type', 1)->where('act', 1)->count());
        // 元宝不足
        DB::table('member')->where('uid', $uid)->update(['gold' => 1]);
        $this->assertSame(['price' => Price::ROWS[2]['act'], 'status' => -1], $this->request($p['auth'], 'shop', 'buy_act', ['t' => 1]));
        // 隔天：次数按 0 算（价格回到第 1 个），旧版写回的是昨天的次数 + 1
        DB::table('member')->where('uid', $uid)->update(['gold' => 1000]);
        DB::table('member_field')->where('uid', $uid)->update(['actnumber' => 7, 'actdateline' => time() - 86400 * 2]);
        $res = $this->request($p['auth'], 'shop', 'buy_act', ['t' => 1]);
        $this->assertSame([1, Price::ROWS[2]['act']], [$res['status'], $res['price']]);
        $this->assertSame(8, self::fieldValue($uid, 'actnumber'));
    }

    // ---------------------------------------------------------------------------------------------
    // 国家商店
    // ---------------------------------------------------------------------------------------------

    public function testCountryShopBuyAndRedItemRecycle(): void
    {
        $p = $this->player(['level' => 150, 'gold' => 1000]);
        $uid = $p['uid'];
        $this->assertSame(['status' => 2], $this->request($p['auth'], 'countryshop', 'index'));
        $country = self::country([$p], 49);
        $this->assertSame(['status' => 3], $this->request($p['auth'], 'countryshop', 'index'));
        DB::table('country')->where('id', $country)->update(['level' => 50]);

        $tab1 = $this->request($p['auth'], 'countryshop', 'index');
        $this->assertSame(1, $tab1['status']);
        $this->assertSame(['id' => 1, 'tab' => 1, 'type' => 1, 'sale_type' => 1, 'price' => 75, 'itemname' => '物攻天赋卷轴', 'itemtype' => 1, 'itemlevel' => 4, 'quality' => 4, 'number' => 5], $tab1['list'][0]);
        $this->assertSame(0, $tab1['debris']);
        $tab2 = $this->request($p['auth'], 'countryshop', 'index', ['tab' => 9]);
        $this->assertSame(['id' => 16, 'tab' => 2, 'type' => 2, 'sale_type' => 2, 'price' => 1000, 'itemname' => '青龙石', 'itemtype' => 1, 'itemlevel' => 8, 'quality' => 2, 'number' => 1], $tab2['list'][0]);

        // 元宝买卷轴
        $buy = $this->request($p['auth'], 'countryshop', 'buy', ['id' => 1]);
        $this->assertSame(['status' => 1, 'type' => 1, 'name' => '物攻天赋卷轴', 'itemtype' => 0, 'quality' => 4, 'level' => 4, 'num' => 5, 'all_gold' => 925, 'all_debris' => 0], $buy);
        $this->assertSame(5, (int)Equipment::talentScroll($uid, 1, 4)['number']);
        // 神器碎片不足 / 购买宝石（宝石品质按 ceil(等级 / 4)，加宝石格子占用）
        $this->assertSame(['status' => -4], $this->request($p['auth'], 'countryshop', 'buy', ['id' => 16]));
        CountryDebris::add($uid, 1500);
        $gemuse = self::memberValue($uid, 'gemuse');
        $buy = $this->request($p['auth'], 'countryshop', 'buy', ['id' => 16]);
        $this->assertSame([1, 2, '青龙石', 1, 2, 8, 500], [$buy['status'], $buy['type'], $buy['name'], $buy['itemtype'], $buy['quality'], $buy['level'], $buy['all_debris']]);
        $this->assertSame(500, CountryDebris::get($uid));
        $this->assertSame(1, DB::table('own_gem')->where('uid', $uid)->where('gemid', 1)->where('level', 8)->whereNull('deleted_at')->count());
        $this->assertSame($gemuse + 1, self::memberValue($uid, 'gemuse'));
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'countryshop', 'buy', ['id' => 99]));

        // 红装回收：整件武器 100 × 20 × 2 = 4000，碎片按片数
        $red = 0;
        foreach (Equip::ROWS as $row) {
            if ($row['quality'] === 6 && $row['type'] === 1) {
                $red = $row['id'];
                break;
            }
        }
        $this->assertGreaterThan(0, $red);
        [$eid] = Equipment::addFromTemplate($uid, $red);
        $list = $this->request($p['auth'], 'countryshop', 'red_item_list');
        $item = array_values(array_filter($list['list'], static fn(array $v): bool => $v['id'] === $eid))[0];
        $this->assertSame(['', 4000, 0], [$item['full_num'], $item['saleprice'], $item['hammer_quality']]);
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'countryshop', 'sale_red_item', ['id' => 999999]));
        $sale = $this->request($p['auth'], 'countryshop', 'sale_red_item', ['id' => $eid]);
        $this->assertSame(['status' => 1, 'add_debris' => 4000, 'all_debris' => 4500], $sale);
        $this->assertSame(1, (int)DB::table('equipment')->where('id', $eid)->value('sale_site'));
        $this->assertNotNull(DB::table('equipment')->where('id', $eid)->value('deleted_at'));
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'countryshop', 'sale_red_item', ['id' => $eid]));
    }

    // ---------------------------------------------------------------------------------------------
    // 国家税金商城
    // ---------------------------------------------------------------------------------------------

    public function testCountryTaxesShopInitBuyLimitAndContributeHook(): void
    {
        $p = $this->player(['level' => 60, 'silver' => 0]);
        $q = $this->player(['level' => 60]);
        $uid = $p['uid'];
        $index = $this->request($p['auth'], 'country_taxes_shop', 'index');
        $this->assertSame([2, ['msg' => '未加入任何国家', 'status' => 2]], [$index['status'], $index['shop_list']]);
        $country = self::country([$p, $q], 29);
        DB::table('member_country')->where('uid', $uid)->update(['allcontribute' => 1235]);
        DB::table('member_country_taxes')->where('uid', $uid)->delete();
        $index = $this->request($p['auth'], 'country_taxes_shop', 'index');
        $this->assertSame([3, '国家等级不足30级'], [$index['status'], $index['msg']]);
        // 第一次打开按总贡献 × 0.1 建档（流水 type 1）
        $this->assertSame([124, 1235, 0], [$index['member_info']['info']['taxes'], $index['member_info']['info']['contribute'], $index['member_info']['info']['general']]);
        // 没加入国家时第一次打开也建了档（旧版同），删掉后再建一次
        $this->assertSame(2, DB::table('country_taxes_shop_log')->where('uid', $uid)->where('type', 1)->count());

        DB::table('country')->where('id', $country)->update(['level' => 35]);
        $index = $this->request($p['auth'], 'country_taxes_shop', 'index');
        $this->assertSame(1, $index['status']);
        $this->assertSame(count(CountryTaxesShop::ROWS), count($index['shop_list']['list']));
        $first = $index['shop_list']['list'][0];
        // 排序：openlevel 升序、type 降序
        $this->assertSame([30, 32, 1, 0, 0, 0], [$first['openlevel'], $first['type'], $first['is_open'], $first['limit'], $first['times'], $first['num']]);

        // 买将军令（type 32，不限购）：扣税金、加 member_integral.general
        $buy = $this->request($p['auth'], 'country_taxes_shop', 'buy', ['id' => 1]);
        $this->assertSame([1, 119, 1, 32], [$buy['status'], $buy['taxes'], $buy['general'], $buy['reward_info']['type']]);
        $this->assertArrayNotHasKey('item', $buy);
        $this->assertSame(1, (int)MemberIntegral::get($uid)['general']);
        // 买紫宝石（type 4，每日国家限购）：国家等级不足 40 级
        $this->assertSame(-4, $this->request($p['auth'], 'country_taxes_shop', 'buy', ['id' => 15])['status']);
        // 蓝宝石 60 个（type 11，限 15 次/国家/天）：同国家其他人买过的次数也算
        Redis::hSet('country_taxes_times_' . $country . '_' . date('Ymd'), '2', '14');
        $buy = $this->request($p['auth'], 'country_taxes_shop', 'buy', ['id' => 2]);
        $this->assertSame([1, 99, 15, 0], [$buy['status'], $buy['taxes'], $buy['item']['times'], $buy['item']['num']]);
        $this->assertSame(60, TraderLog::jade($uid, 1));
        $this->request($q['auth'], 'country_taxes_shop', 'index');
        DB::table('member_country_taxes')->where('uid', $q['uid'])->update(['taxes' => 100]);
        $this->assertSame(['status' => -3, 'msg' => '购买次数不足'], $this->request($q['auth'], 'country_taxes_shop', 'buy', ['id' => 2]));
        $this->assertSame(100, (int)DB::table('member_country_taxes')->where('uid', $q['uid'])->value('taxes'), '超限不扣税金');
        // 税金不足 / 商品不存在 / 参数
        DB::table('member_country_taxes')->where('uid', $uid)->update(['taxes' => 3]);
        $this->assertSame(['status' => -2, 'msg' => '税金不足'], $this->request($p['auth'], 'country_taxes_shop', 'buy', ['id' => 1]));
        $this->assertSame(['status' => 2, 'msg' => '不存在该物品'], $this->request($p['auth'], 'country_taxes_shop', 'buy', ['id' => 99]));
        $this->assertSame(['status' => -1, 'msg' => 'id 不合法'], $this->request($p['auth'], 'country_taxes_shop', 'buy'));
        $this->assertSame(2, DB::table('country_taxes_shop_log')->where('uid', $uid)->where('type', 3)->count());

        // 国家贡献累计税金：今日贡献新增 × 0.1（国家祭祀 / 国宴 / 谏言 / 捐献共用 addContribute / update_member_info）
        DB::table('member')->where('uid', $q['uid'])->update(['level' => 50, 'silver' => 1000000]);
        $before = (int)DB::table('member_country_taxes')->where('uid', $q['uid'])->value('taxes');
        $this->assertSame(1, $this->request($q['auth'], 'countrysacrifice', 'action', ['id' => 1])['status']);
        $this->assertSame($before + 4, (int)DB::table('member_country_taxes')->where('uid', $q['uid'])->value('taxes'));
        $this->assertSame(1, DB::table('country_taxes_shop_log')->where('uid', $q['uid'])->where('type', 2)->where('taxes', 4)->count());
    }

    // ---------------------------------------------------------------------------------------------
    // 国家祭祀
    // ---------------------------------------------------------------------------------------------

    public function testCountrySacrificeDailyOnceVipGateChatAndCountryCap(): void
    {
        $p = $this->player(['level' => 49, 'silver' => 1000000, 'gold' => 1000, 'vip' => 2]);
        $q = $this->player(['level' => 50]);
        $uid = $p['uid'];
        $this->assertSame(['status' => 3], $this->request($p['auth'], 'countrysacrifice', 'index'));
        DB::table('member')->where('uid', $uid)->update(['level' => 50]);
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'countrysacrifice', 'index'));
        $country = self::country([$p, $q], 10);
        $index = $this->request($p['auth'], 'countrysacrifice', 'index');
        $this->assertSame([1, 50, 50, []], [$index['status'], $index['now_times'], $index['all_times'], $index['log_list']]);
        // 银币价格 × 主公等级，黄金 / 至尊祭祀要 VIP 3 / 5
        $this->assertSame([500 * 50, 0, 3, 5], [$index['list'][0]['price'], $index['list'][0]['need_vip'], $index['list'][1]['need_vip'], $index['list'][2]['need_vip']]);

        $this->assertSame(['status' => -6], $this->request($p['auth'], 'countrysacrifice', 'action', ['id' => 2]));
        $this->assertSame(['status' => -3], $this->request($p['auth'], 'countrysacrifice', 'action', ['id' => 9]));
        DB::table('member')->where('uid', $uid)->update(['vip' => 3]);
        $before = DB::table('country')->select('level', 'exp')->where('id', $country)->first();
        $res = $this->request($p['auth'], 'countrysacrifice', 'action', ['id' => 2]);
        $get = CountryWorship::ROWS[2]['get_value'];
        $this->assertSame(['status' => 1, 'get' => $get, 'nickname' => 'd4_' . $uid, 'type' => 2, 'now_times' => 49, 'all_times' => 50, 'all_gold' => 970, 'all_silver' => 1000000], $res);
        $row = DB::table('member_country')->where('uid', $uid)->first();
        $this->assertSame([$get, $get], [(int)$row['contribute'], (int)$row['allcontribute']]);
        $this->assertGreaterThan(time() - 5, (int)$row['worshipdateline']);
        $this->assertNotEquals($before, DB::table('country')->select('level', 'exp')->where('id', $country)->first(), '加国家经验');
        $this->assertSame([2, 30, $get], array_map('intval', array_values(DB::table('country_donate_log')->select('type', 'cost', 'donate')->where('uid', $uid)->orderBy('id', 'DESC')->first())));
        $this->assertSame(1, (int)DB::table('stat_pay')->where('name', 'country_sacrifice')->count());
        // 元宝祭祀发国家聊天室系统消息（get_message_str 9）
        $chat = Chat::readCountry($country);
        $this->assertStringContainsString('完成了 黄金祭祀', (string)end($chat)['message']);
        // 今天已祭祀
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'countrysacrifice', 'action', ['id' => 1]));
        $index = $this->request($p['auth'], 'countrysacrifice', 'index');
        $this->assertSame([2, 49, 'd4_' . $uid, 2], [$index['status'], $index['now_times'], $index['log_list'][0]['nickname'], $index['log_list'][0]['type']]);

        // 新加入国家 7 天内（VIP 0）贡献不能超过 100
        DB::table('member_country')->where('uid', $q['uid'])->update(['joindateline' => time() - 86400, 'contribute' => 90]);
        DB::table('member')->where('uid', $q['uid'])->update(['silver' => 1000000]);
        $this->assertSame(['status' => -8], $this->request($q['auth'], 'countrysacrifice', 'action', ['id' => 1]));
        // 国家每天最多 50 次
        for ($i = 0; $i < 49; $i++) {
            DB::table('country_worship_log')->insert(['country' => $country, 'nickname' => 'x', 'type' => 1, 'get_value' => 1, 'dateline' => time()]);
        }
        DB::table('member_country')->where('uid', $q['uid'])->update(['joindateline' => time() - 86400 * 30]);
        $this->assertSame(['status' => -7], $this->request($q['auth'], 'countrysacrifice', 'action', ['id' => 1]));
    }

    // ---------------------------------------------------------------------------------------------
    // 天神祭祀
    // ---------------------------------------------------------------------------------------------

    public function testSacrificeActivityPricesRewardAndDailyReset(): void
    {
        $p = $this->player(['level' => 30, 'gold' => 1000]);
        $uid = $p['uid'];
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'sacrifice', 'index'));
        $this->assertSame(['status' => -2, 'info' => []], $this->request($p['auth'], 'sacrifice', 'go_sacrifice', ['type' => 1]));
        BossClock::freeze(self::OPEN_TS);
        $index = $this->request($p['auth'], 'sacrifice', 'index');
        $this->assertSame(['status' => 1, 'info' => [1 => 1, 2 => 1, 3 => 1], 'jade' => [1 => 0, 2 => 0, 3 => 0], 'cd' => 1483113600 - self::OPEN_TS], $index);
        $this->assertSame(-3, $this->request($p['auth'], 'sacrifice', 'go_sacrifice', ['type' => 4])['status']);
        $res = $this->request($p['auth'], 'sacrifice', 'go_sacrifice', ['type' => 3]);
        $this->assertSame(1, $res['status']);
        $this->assertContains($res['info']['num'], [1, 2, 4, 8]);
        $this->assertSame([3, 1000 - Price::ROWS[1]['jade3'], Price::ROWS[2]['jade3'], $res['info']['num']], [$res['info']['type'], $res['info']['gold'], $res['info']['next'], $res['jade'][3]]);
        $this->assertSame(1, self::activityValue($uid, 'sacrifice3'));
        $this->assertSame(1, DB::table('sacrifice_action_log')->where('uid', $uid)->where('get3', $res['info']['num'])->where('gold', Price::ROWS[1]['jade3'])->count());
        $this->assertSame([2 => 1, 3 => Price::ROWS[2]['jade3']], array_slice($this->request($p['auth'], 'sacrifice', 'index')['info'], 1, 2, true));
        // 元宝不足
        DB::table('member')->where('uid', $uid)->update(['gold' => 0]);
        $this->assertSame(['status' => 2, 'info' => []], $this->request($p['auth'], 'sacrifice', 'go_sacrifice', ['type' => 3]));
        // 每日计划任务归零
        DB::table('member_field')->where('uid', $uid)->update(['dateline' => 0]);
        Member::dailyCron($uid, time());
        $this->assertSame([0, 20], [self::activityValue($uid, 'sacrifice3'), self::activityValue($uid, 'freebusiness')]);
    }

    // ---------------------------------------------------------------------------------------------
    // 玉石作坊
    // ---------------------------------------------------------------------------------------------

    public function testWorkshopCollectLevelupAndOpenSite(): void
    {
        $p = $this->player(['level' => 90, 'gold' => 10000, 'vip' => 0]);
        $uid = $p['uid'];
        $this->assertSame(['status' => 2], $this->request($p['auth'], 'workshop', 'index'));
        DB::table('member')->where('uid', $uid)->update(['level' => 100]);
        $jade = MemberJade::get($uid);

        // 第一次打开自动开启第 1 个采集位：每小时 floor(100 × 5 × 1) = 500，8 小时装满
        $index = $this->request($p['auth'], 'workshop', 'index');
        $this->assertSame(['status' => 1, 'list' => ['1' => ['uid' => $uid, 'site' => 1, 'level' => 0, 'get' => 0, 'full_level' => 0, 'volume' => 4000, 'yield' => 0, 'add' => 500]], 'addition' => 0, 'all_jade' => $jade], $index);
        $start = (int)DB::table('workshop')->where('uid', $uid)->value('starttime');
        $this->assertSame($start + 8 * 3600, self::fieldValue($uid, 'onworkshop'));
        $this->assertSame(1, DB::table('edifice_log')->where('uid', $uid)->where('edifice_id', 8)->count());

        // 采集 1 小时后收获 500 玉石
        $this->assertSame(['status' => -3], $this->request($p['auth'], 'workshop', 'get_reward', ['s' => 1]));
        DB::table('workshop')->where('uid', $uid)->update(['starttime' => time() - 3600]);
        $this->assertSame(['status' => 1, 'add' => 500, 'all_jade' => $jade + 500], $this->request($p['auth'], 'workshop', 'get_reward', ['s' => 1]));
        $this->assertSame(1, DB::table('jade_log')->where('uid', $uid)->where('method', 'get_reward')->where('jade', 500)->count());
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'workshop', 'get_reward', ['s' => 9]));
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'workshop', 'get_reward', ['s' => 2]));

        // 升级信息 / 升级（VIP 1、50 元宝）：已采集的产量按新产量折算成采集时间
        $info = $this->request($p['auth'], 'workshop', 'levelup', ['s' => 1]);
        $this->assertSame(['status' => 1, 'price' => 50, 'viplevel' => 1, 'now_add' => 500, 'now_volume' => 4000, 'now_name' => '普通', 'now_result' => 100, 'now_level' => 0, 'add' => 750, 'volume' => 6000, 'yield' => 0, 'name' => '加强', 'result' => 150, 'level' => 1], $info);
        $this->assertSame(['status' => -3, 'price' => 50, 'viplevel' => 1], $this->request($p['auth'], 'workshop', 'levelup', ['s' => 1, 't' => 1]));
        DB::table('member')->where('uid', $uid)->update(['vip' => 1]);
        DB::table('workshop')->where('uid', $uid)->update(['starttime' => time() - 1800]);
        $up = $this->request($p['auth'], 'workshop', 'levelup', ['s' => 1, 't' => 1]);
        $this->assertSame([1, 9950, 0, 1, 750, 250], [$up['status'], $up['all_gold'], $up['full_level'], $up['level'], $up['add'], $up['yield']]);
        $this->assertSame(1, (int)DB::table('workshop')->where('uid', $uid)->value('level'));

        // 开启采集位（第 2 个 50 元宝）
        $this->assertSame(['status' => 1, 'price' => '50', 'viplevel' => '0'], $this->request($p['auth'], 'workshop', 'open_workshop_site'));
        $open = $this->request($p['auth'], 'workshop', 'open_workshop_site', ['t' => 1]);
        $this->assertSame(['status' => 1, 'price' => '50', 'viplevel' => '0', 'yield' => 0, 'add' => 500, 'volume' => 4000, 'all_gold' => 9900], $open);
        $this->assertSame(2, DB::table('workshop')->where('uid', $uid)->whereNull('deleted_at')->count());
        // 第 4 个要 VIP 1、第 5 个要 VIP 2
        $this->assertSame(1, $this->request($p['auth'], 'workshop', 'open_workshop_site', ['t' => 1])['status']);
        $this->assertSame(1, $this->request($p['auth'], 'workshop', 'open_workshop_site', ['t' => 1])['status']);
        $this->assertSame(['status' => -4, 'price' => '500', 'viplevel' => '2'], $this->request($p['auth'], 'workshop', 'open_workshop_site', ['t' => 1]));
        // 8 个全开后，旧版第 9 个起按空价格开出表外采集位，新版回 -2
        for ($site = 5; $site <= 8; $site++) {
            DB::table('workshop')->insert(['uid' => $uid, 'site' => $site, 'level' => 0, 'starttime' => time()]);
        }
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'workshop', 'open_workshop_site', ['t' => 1]));
    }

    // ---------------------------------------------------------------------------------------------
    // 银矿
    // ---------------------------------------------------------------------------------------------

    public function testMineCaikuangRobHarvestAndGiveUp(): void
    {
        DB::execute('UPDATE `zc_sanguo_mine` SET `deleted_at` = NOW() WHERE `deleted_at` IS NULL');
        $low = $this->player(['level' => 19]);
        $this->assertSame(['status' => -1], $this->request($low['auth'], 'mine', 'index'));
        $a = $this->player(['level' => 35, 'act' => 10, 'silver' => 0]);
        $b = $this->player(['level' => 38, 'act' => 10, 'silver' => 0]);

        // 第 1 页：1 个 1 型（放在第 5 个）+ 8 个 2 型；每 6 分钟 系数 × 30 × 12.5，4 小时 40 次
        $index = $this->request($a['auth'], 'mine', 'index');
        $this->assertSame([1, '30~39', 2, [], 9], [$index['status'], $index['level'], $index['page'], $index['log'], count($index['list'])]);
        $this->assertSame(['id' => 1, 'page' => 1, 'type' => 1, 'status' => 0, 'silver' => 90000, 'get_silver' => 2250], $index['list'][4]);
        $this->assertSame(['id' => 2, 'page' => 1, 'type' => 2, 'status' => 0, 'silver' => 75000, 'get_silver' => 1875], $index['list'][0]);
        $this->assertSame(1, DB::table('edifice_log')->where('uid', $a['uid'])->where('edifice_id', 2)->count());
        $this->assertSame(['status' => -2, 'level' => '30~39'], $this->request($a['auth'], 'mine', 'index', ['p' => 6]));

        // 开采：矿型与配置不符 / 位置越界
        $this->assertSame(['status' => -2], $this->request($a['auth'], 'mine', 'caikuang', ['p' => 1, 'id' => 2, 't' => 1]));
        $this->assertSame(['status' => -2], $this->request($a['auth'], 'mine', 'caikuang', ['p' => 1, 'id' => 10, 't' => 2]));
        $res = $this->request($a['auth'], 'mine', 'caikuang', ['p' => 1, 'id' => 2, 't' => 2]);
        $this->assertSame([1, 14400, 9], [$res['status'], $res['dateline'], $res['act']]);
        $this->assertGreaterThan(time() - 5, self::fieldValue($a['uid'], 'onmine'));
        $this->assertSame(['status' => -1], $this->request($a['auth'], 'mine', 'caikuang', ['p' => 1, 'id' => 3, 't' => 2]));
        // 别人看到被占的矿
        $view = $this->request($b['auth'], 'mine', 'index');
        $this->assertSame([1, 'd4_' . $a['uid'], 35, 0], [$view['list'][0]['status'], $view['list'][0]['nickname'], $view['list'][0]['level'], $view['list'][0]['friend']]);
        $this->assertSame(['status' => -2], $this->request($b['auth'], 'mine', 'caikuang', ['p' => 1, 'id' => 2, 't' => 2]));

        // B 抢夺阵法为空的 A：胜利接管该矿（类型不变），掠夺 1 小时的银币，发矿战信件战报
        DB::table('mine')->where('uid', $a['uid'])->whereNull('deleted_at')->update(['dateline' => time() - 3600]);
        self::emptyFormation($a['uid']);
        $rob = $this->request($b['auth'], 'mine', 'zhanling', ['p' => 1, 'id' => 2, 't' => 9]);
        $this->assertSame(1, $rob['status']);
        $this->assertSame([14400, 18750, 9, 18750, 'd4_' . $a['uid']], [$rob['info']['dateline'], $rob['info']['get_silver'], $rob['info']['act'], $rob['info']['silver'], $rob['info']['name']]);
        $this->assertGreaterThanOrEqual(1, $rob['info']['win']);
        $this->assertSame(['name', 'level', 'gender'], array_keys($rob['battle']['right_role']));
        $this->assertSame(['id' => $rob['info']['report'], 'type' => 'mine'], $rob['share']);
        $pm = DB::table('pm')->where('id', $rob['info']['report'])->first();
        $this->assertSame([3, $a['uid'], 0], [(int)$pm['type'], (int)$pm['touid'], (int)$pm['report']]);
        $this->assertSame('d4_' . $b['uid'], (string)DB::table('pm_report')->where('id', $rob['info']['report'])->value('nickname'));
        $this->assertStringContainsString('掠夺了你银币 18750', (string)$pm['message']);
        $this->assertSame([$b['uid'], 2, 38], array_map('intval', array_values(DB::table('mine')->select('uid', 'type', 'level')->where('page', 1)->where('site', 2)->whereNull('deleted_at')->first())));
        $this->assertSame([0, true], [self::fieldValue($a['uid'], 'onmine'), self::fieldValue($b['uid'], 'onmine') > 0]);

        // 收获：占满 4 小时
        $this->assertSame(['status' => -3], $this->request($b['auth'], 'mine', 'get_silver', ['s' => 2]));
        $this->assertSame(['status' => -2], $this->request($b['auth'], 'mine', 'get_silver', ['s' => 3]));
        DB::table('mine')->where('uid', $b['uid'])->whereNull('deleted_at')->update(['dateline' => time() - 14400]);
        $this->assertSame(['status' => 1, 'silver' => 75000, 'all_silver' => 93750], $this->request($b['auth'], 'mine', 'get_silver', ['s' => 2]));
        $this->assertSame(0, DB::table('mine')->where('uid', $b['uid'])->whereNull('deleted_at')->count());
        $this->assertSame(['status' => -1], $this->request($b['auth'], 'mine', 'get_silver', ['s' => 2]));

        // 放弃：按已占时间给银币；升过等级段后返回 2（页面要刷新）
        $this->assertSame(1, $this->request($a['auth'], 'mine', 'caikuang', ['p' => 1, 'id' => 5, 't' => 2])['status']);
        DB::table('mine')->where('uid', $a['uid'])->whereNull('deleted_at')->update(['dateline' => time() - 720]);
        DB::table('member')->where('uid', $a['uid'])->update(['level' => 40]);
        $this->assertSame(['silver' => 3750, 'all_silver' => 3750, 'status' => 2], $this->request($a['auth'], 'mine', 'give_up'));
        $this->assertSame(['status' => -1], $this->request($a['auth'], 'mine', 'give_up'));

        // 抢夺失败（攻方阵法为空）：矿还是对方的，对方收到「被您击败」的信件，照样消耗军令
        $this->assertSame(1, $this->request($a['auth'], 'mine', 'caikuang', ['p' => 2, 'id' => 1, 't' => 3])['status']);
        $c = $this->player(['level' => 45, 'act' => 5]);
        self::emptyFormation($c['uid']);
        $lose = $this->request($c['auth'], 'mine', 'zhanling', ['p' => 2, 'id' => 1]);
        $this->assertSame([1, 4], [$lose['status'], $lose['info']['act']]);
        $this->assertLessThan(1, $lose['info']['win']);
        $this->assertArrayNotHasKey('get_silver', $lose['info']);
        $this->assertSame($a['uid'], (int)DB::table('mine')->where('page', 2)->where('site', 1)->whereNull('deleted_at')->value('uid'));
        $this->assertStringContainsString('攻打了您的矿，被您击败了。', (string)DB::table('pm')->where('id', $lose['info']['report'])->value('message'));

        // 双方都有阵法的真实战斗：返回旧版战报结构
        $d = $this->player(['level' => 45, 'act' => 5]);
        $real = $this->request($d['auth'], 'mine', 'zhanling', ['p' => 2, 'id' => 1]);
        $this->assertSame(1, $real['status']);
        foreach (['assess', 'left_role', 'right_role'] as $key) {
            $this->assertArrayHasKey($key, $real['battle']);
        }
        $this->assertSame($real['battle']['assess'], $real['info']['win']);
        $this->assertSame(['d4_' . $d['uid'], 45], [$real['battle']['left_role']['name'], $real['battle']['left_role']['level']]);
    }

    // ---------------------------------------------------------------------------------------------
    // 每日通商
    // ---------------------------------------------------------------------------------------------

    public function testBusinessTasksRewardTimesAndCityEffect(): void
    {
        $today = (int)date('Ymd');
        DB::table('business')->where('date', $today)->delete();
        DB::table('business')->insert(['date' => $today, 'type' => 1, 'star' => 2, 'group' => 'ab']);
        DB::table('business')->insert(['date' => $today, 'type' => 2, 'star' => 4, 'group' => 'cdef']);
        DB::table('business')->insert(['date' => $today, 'type' => 3, 'star' => 6, 'group' => 'ghijkl']);
        $p = $this->player(['level' => 60, 'gold' => 100, 'silver' => 0]);
        $uid = $p['uid'];
        $this->assertSame(['status' => 2], $this->request($p['auth'], 'business', 'index'));
        DB::table('member')->where('uid', $uid)->update(['level' => 61]);
        $index = $this->request($p['auth'], 'business', 'index');
        $this->assertSame([1, 20, [], 5, 3], [$index['status'], $index['times'], $index['log'], $index['refresh'], count($index['trader'])]);
        $this->assertSame([['id' => 1, 'name' => '楼兰商人', 'finish' => 0], ['id' => 2, 'name' => '西域商人', 'finish' => 0]], $index['list'][0]);

        $trader = static function () use ($uid): void {
            Redis::set('business_' . $uid, [['id' => 1, 'name' => '楼兰商人'], ['id' => 2, 'name' => '西域商人'], ['id' => 3, 'name' => '巴蜀商人']], 86400);
        };
        $trader();
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'business', 'go_business', ['id' => 9]));
        $go = $this->request($p['auth'], 'business', 'go_business', ['id' => 1]);
        $this->assertSame([1, 19, 0, 250 * 61, 0, 250 * 61], [$go['status'], $go['times'], $go['complete'], $go['reward_silver'], $go['addition'], $go['silver']]);
        $this->assertSame([['id' => 1, 'name' => '楼兰商人', 'finish' => 1], ['id' => 2, 'name' => '西域商人', 'finish' => 0]], $go['list'][0]);
        $this->assertSame([['id' => 1, 'name' => '楼兰商人']], $go['log']);
        $this->assertSame([19, 'a'], [self::activityValue($uid, 'freebusiness'), (string)DB::table('business_log')->where('uid', $uid)->value('log')]);
        // 连上 b 完成 2 星任务：另得 61 × 500，任务显示为未完成，记 laststar / lastsite
        $trader();
        $go = $this->request($p['auth'], 'business', 'go_business', ['id' => 2]);
        $this->assertSame([1, 18, 1, 250 * 61, 500 * 61], [$go['status'], $go['times'], $go['complete'], $go['reward_silver'], $go['addition']]);
        $this->assertSame([0, 0], array_column($go['list'][0], 'finish'));
        $this->assertSame(['ab', 2, 2], array_values(array_map(static fn($v) => is_numeric($v) ? (int)$v : $v, DB::table('business_log')->select('log', 'laststar', 'lastsite')->where('uid', $uid)->first())));
        $this->assertSame(250 * 61 * 2 + 500 * 61, self::memberValue($uid, 'silver'));
        // 旧版只在 lastsite > 6 时截掉上次奖励前的商人：再通商 a、b 时同一任务又算完成（照搬）
        $trader();
        $this->assertSame(0, $this->request($p['auth'], 'business', 'go_business', ['id' => 1])['complete']);
        $this->assertSame(0, (int)DB::table('business_log')->where('uid', $uid)->value('lastsite'));
        $trader();
        $go = $this->request($p['auth'], 'business', 'go_business', ['id' => 2]);
        $this->assertSame([1, 500 * 61, 16], [$go['complete'], $go['addition'], $go['times']]);

        // 次数用完 / 购买次数（8 元宝）/ 刷新商人（5 元宝）
        DB::table('member_activity')->where('uid', $uid)->update(['freebusiness' => 0]);
        $trader();
        $this->assertSame(['status' => 3], $this->request($p['auth'], 'business', 'go_business', ['id' => 1]));
        $this->assertSame(['status' => 1, 'times' => 1, 'gold' => 92], $this->request($p['auth'], 'business', 'buy'));
        $this->assertSame(1, self::activityValue($uid, 'businesstimes'));
        $trader();
        $this->assertSame(1, $this->request($p['auth'], 'business', 'go_business', ['id' => 3])['status']);
        $this->assertSame(0, self::activityValue($uid, 'businesstimes'));
        $refresh = $this->request($p['auth'], 'business', 'refresh');
        $this->assertSame([1, 87, 3], [$refresh['status'], $refresh['gold'], count($refresh['trader'])]);
        $this->assertSame(1, (int)DB::table('stat_pay')->where('name', 'business_refresh')->count());

        // 都城效果 type 2：国家占领武威，成员当天第一次打开每日通商加 45 次免费通商
        $country = self::country([$p], 10);
        $old = DB::table('country_city')->where('maplevel', 5)->first();
        DB::table('country_city')->where('maplevel', 5)->delete();
        DB::table('country_city')->insert(['maplevel' => 5, 'cid' => $country, 'name' => 'x', 'level' => 10, 'dateline' => time() - 86400 * 2]);
        try {
            $this->assertSame(45, $this->request($p['auth'], 'business', 'index')['times']);
            $this->assertSame(45, $this->request($p['auth'], 'business', 'index')['times'], '一天只加一次');
            $this->assertSame(2, (int)DB::table('member_country')->where('uid', $uid)->value('effect_type'));
        } finally {
            DB::table('country_city')->where('maplevel', 5)->delete();
            if ($old !== null) {
                DB::table('country_city')->insert(array_diff_key($old, ['created_at' => 1, 'updated_at' => 1]));
            }
        }

        // 计划任务按旧版 builder 补齐没有任务的日期（每天初级 / 中级 / 高级三条，商人数 = 星级）
        $tomorrow = (int)date('Ymd', strtotime('+1 day'));
        DB::table('business')->where('date', '>', $today)->delete();
        $this->assertSame(6, BusinessCron::run(time()));
        $this->assertSame(0, BusinessCron::run(time()));
        foreach (BusinessCron::tasks($tomorrow) as $i => $task) {
            $this->assertSame($i + 1, $task['type']);
            $this->assertSame($task['star'], strlen($task['group']));
            $this->assertMatchesRegularExpression('/^[a-l]+$/', $task['group']);
        }
        $this->assertSame(3, count(BusinessCron::tasks($tomorrow)));
        // 旧版数据日期取 MasterData
        $this->assertSame('ilh', BusinessCron::tasks(20150116)[0]['group']);
    }

    // ---------------------------------------------------------------------------------------------
    // 黑市
    // ---------------------------------------------------------------------------------------------

    public function testBlackmarketShelfLockRefreshAndBuy(): void
    {
        $p = $this->player(['level' => 40, 'gold' => 1000, 'silver' => 0, 'act' => 0, 'reputation' => 7]);
        $uid = $p['uid'];
        $this->assertSame(['status' => 2], $this->request($p['auth'], 'blackmarket', 'index'));
        BossClock::freeze(self::OPEN_TS);
        $index = $this->request($p['auth'], 'blackmarket', 'index');
        $this->assertSame(1, $index['status']);
        $this->assertSame([$uid, 5, self::OPEN_TS, 1, 0, 0, 0, 10, 1483113600 - self::OPEN_TS, 7], [
            $index['info']['uid'], $index['info']['times'], $index['info']['dateline'], $index['info']['type'], $index['info']['get1'],
            $index['info']['get2'], $index['info']['get3'], $index['info']['price'], $index['info']['cd'], $index['info']['reputation'],
        ]);
        $this->assertSame([1, 2, 3], array_keys($index['list']));
        $ids = array_column($index['list'], 'id');
        $this->assertSame(3, count(array_unique($ids)));
        $this->assertSame($ids, array_map('intval', array_values(DB::table('blackmarket_log')->select('site1', 'site2', 'site3')->where('uid', $uid)->first())));

        // 锁定 / 解锁
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'blackmarket', 'lock', ['site' => 4, 'status' => 1]));
        $this->assertSame(['status' => 1], $this->request($p['auth'], 'blackmarket', 'lock', ['site' => 1, 'status' => 1]));
        $this->assertSame(['status' => -3], $this->request($p['auth'], 'blackmarket', 'lock', ['site' => 1, 'status' => 1]));
        $this->assertSame(['status' => -3], $this->request($p['auth'], 'blackmarket', 'lock', ['site' => 2, 'status' => 2]));

        // 免费刷新：锁定的格子保留，只换 2、3
        $refresh = $this->request($p['auth'], 'blackmarket', 'refresh');
        $this->assertSame([1, 4, 1000, 10, [2, 3]], [$refresh['status'], $refresh['times'], $refresh['all_gold'], $refresh['price'], array_keys($refresh['list'])]);
        $this->assertSame($ids[0], (int)DB::table('blackmarket_log')->where('uid', $uid)->value('site1'));
        $this->assertSame([1, 0], array_map('intval', array_values(DB::table('blackmarket_refresh')->select('status', 'site1')->where('uid', $uid)->orderBy('id', 'DESC')->first())));
        $this->assertSame(0, (int)DB::table('blackmarket_action_log')->where('uid', $uid)->orderBy('id', 'DESC')->value('gold'));
        // 免费次数用完：花 10 元宝
        DB::table('blackmarket_log')->where('uid', $uid)->update(['times' => 0]);
        $refresh = $this->request($p['auth'], 'blackmarket', 'refresh');
        $this->assertSame([1, 0, 990], [$refresh['status'], $refresh['times'], $refresh['all_gold']]);
        $this->assertSame(10, (int)DB::table('blackmarket_action_log')->where('uid', $uid)->orderBy('id', 'DESC')->value('gold'));
        DB::table('member')->where('uid', $uid)->update(['gold' => 5]);
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'blackmarket', 'refresh'));
        DB::table('blackmarket_log')->where('uid', $uid)->update(['site2_status' => 1, 'site3_status' => 1]);
        DB::table('member')->where('uid', $uid)->update(['gold' => 1000]);
        $this->assertSame(['status' => -3], $this->request($p['auth'], 'blackmarket', 'refresh'));

        // 买军令（银币价格 × 主公等级）：格子换成新商品
        DB::table('blackmarket_log')->where('uid', $uid)->update(['site1' => 1, 'site2' => 42, 'site3' => 2, 'site2_status' => 0]);
        DB::table('member')->where('uid', $uid)->update(['silver' => 2250 * 40]);
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'blackmarket', 'buy', ['id' => 999]));
        $buy = $this->request($p['auth'], 'blackmarket', 'buy', ['id' => 42]);
        $this->assertSame([1, 6, 10, 1, 90000, 0, 0, 10, [2]], [$buy['status'], $buy['get_type'], $buy['get_value'], $buy['sub_type'], $buy['sub_value'], $buy['surplus'], $buy['all_silver'], $buy['all_act'], array_keys($buy['list'])]);
        $this->assertNotSame(42, (int)DB::table('blackmarket_log')->where('uid', $uid)->value('site2'));
        $this->assertSame([42, 1, 90000, 0], array_map('intval', array_values(DB::table('blackmarket_buy_log')->select('itemid', 'sub_type', 'sub_value', 'surplus')->where('uid', $uid)->first())));

        // 买武将（黄宝石）：记酒馆日志（获取方式 6）、发聊天室系统消息 14；已拥有时不能再买
        DB::table('blackmarket_log')->where('uid', $uid)->update(['site3' => 17]);
        $this->assertSame(['status' => -7], $this->request($p['auth'], 'blackmarket', 'buy', ['id' => 17]));
        TraderLog::addJade($uid, 2, 400);
        $buy = $this->request($p['auth'], 'blackmarket', 'buy', ['id' => 17]);
        $this->assertSame([1, 5, 4, 315, 85, '吕蒙'], [$buy['status'], $buy['get_type'], $buy['sub_type'], $buy['sub_value'], $buy['surplus'], $buy['get_value']['name']]);
        $this->assertSame(1, DB::table('general')->where('uid', $uid)->where('generalid', 12)->whereNull('deleted_at')->count());
        $this->assertSame(6, (int)DB::table('pub_log')->where('uid', $uid)->where('generalid', 12)->value('type'));
        $public = Chat::readPublicMessage();
        $this->assertStringContainsString('{9,吕蒙}', (string)end($public)['message']);
        DB::table('blackmarket_log')->where('uid', $uid)->update(['site3' => 17]);
        TraderLog::addJade($uid, 2, 400);
        $this->assertSame(['status' => -9], $this->request($p['auth'], 'blackmarket', 'buy', ['id' => 17]));
    }

    // ---------------------------------------------------------------------------------------------
    // 赌石
    // ---------------------------------------------------------------------------------------------

    public function testGamblingBuyCutSellAndFreeTimes(): void
    {
        $p = $this->player(['level' => 90, 'silver' => 1000000, 'gold' => 10]);
        $uid = $p['uid'];
        $this->assertSame(['status' => -1, 'list' => []], $this->request($p['auth'], 'gambling', 'index'));
        DB::table('member')->where('uid', $uid)->update(['level' => 100]);
        $jade = MemberJade::get($uid);
        $index = $this->request($p['auth'], 'gambling', 'index');
        $this->assertSame(1, $index['status']);
        $rule = StoneRule::ROWS[1];
        $this->assertSame(['name' => $rule['name'], 'buytype' => 2, 'price' => 2000 * 100, 'description' => $rule['description'], 'trait' => $rule['trait'], 'cut' => 0, 'stoneid' => 0, 'num' => 0], $index['list']['1']);
        $this->assertSame([2, 3], [$index['list']['2']['buytype'] + 1, count($index['list'])]);

        // 买盘龙石（银币 × 主公等级）
        $this->assertSame(['status' => -1, 'info' => []], $this->request($p['auth'], 'gambling', 'buy', ['id' => 4]));
        $buy = $this->request($p['auth'], 'gambling', 'buy', ['id' => 1]);
        $this->assertSame(['status' => 1, 'info' => ['cut' => 0, 'value' => 8000, 'stoneid' => 0], 'gold' => 10, 'silver' => 800000, 'stone1_times' => 0, 'stone2_times' => 0, 'stone3_times' => 0], $buy);
        // 已拥有：不再扣钱
        $this->assertSame(800000, $this->request($p['auth'], 'gambling', 'buy', ['id' => 1])['silver']);
        $this->assertSame(['status' => 2, 'info' => []], $this->request($p['auth'], 'gambling', 'buy', ['id' => 3]), '元宝不足');

        // 切石：第一刀 90% 切出 2 品宝石（猫眼石）
        mt_srand(self::seed(1, 100, static fn(int $r): bool => $r <= 90));
        $cut = $this->request($p['auth'], 'gambling', 'cut', ['id' => 1]);
        $this->assertSame([1, ['cut' => 0, 'value' => 10000, 'stoneid' => 1, 'name' => '猫眼石', 'level' => 2], 200000, 0, 2], [$cut['status'], $cut['info'], $cut['price'], $cut['num'], $cut['buytype']]);
        $this->assertSame(['status' => -3, 'info' => []], $this->request($p['auth'], 'gambling', 'cut', ['id' => 1]), '珍稀宝石不能再切');
        $this->assertSame(['status' => -2, 'info' => []], $this->request($p['auth'], 'gambling', 'cut', ['id' => 2]));
        // 卖出：珍稀宝石按 Stone.value 得玉石
        $sell = $this->request($p['auth'], 'gambling', 'sell', ['id' => 1]);
        $this->assertSame(['status' => 1, 'value' => 10000, 'jade' => $jade + 10000, 'info' => ['cut' => 0, 'value' => 0, 'stoneid' => 0, 'name' => '', 'level' => 0], 'num' => 0, 'price' => 200000, 'buytype' => 2, 'silver' => 800000], $sell);
        $this->assertSame(0, DB::table('own_stone')->where('uid', $uid)->whereNull('deleted_at')->count());

        // VIP 免费原石：每日计划任务按 VIP 发（VIP 2~5 盘龙石 10 次）；买 / 卖时用免费次数直接再拿一块
        DB::table('member')->where('uid', $uid)->update(['vip' => 2]);
        DB::table('member_field')->where('uid', $uid)->update(['dateline' => 0]);
        Member::dailyCron($uid, time());
        $this->assertSame([10, 0, 0], [self::fieldValue($uid, 'stone1_times'), self::fieldValue($uid, 'stone2_times'), self::fieldValue($uid, 'stone3_times')]);
        $this->assertSame(10, $this->request($p['auth'], 'gambling', 'index')['list']['1']['num']);
        $buy = $this->request($p['auth'], 'gambling', 'buy', ['id' => 1]);
        $this->assertSame([800000, 10], [$buy['silver'], $buy['stone1_times']], '手上这块也算上');
        $this->assertSame(9, self::fieldValue($uid, 'stone1_times'));
        $sell = $this->request($p['auth'], 'gambling', 'sell', ['id' => 1]);
        $this->assertSame([8000, 9, 1, '盘龙石'], [$sell['value'], $sell['num'], $sell['info']['stoneid'], $sell['info']['name']]);
        $this->assertSame([8, 1], [self::fieldValue($uid, 'stone1_times'), DB::table('own_stone')->where('uid', $uid)->whereNull('deleted_at')->count()]);
        // 切碎：第一刀 rand > 90
        mt_srand(self::seed(1, 100, static fn(int $r): bool => $r > 90));
        $cut = $this->request($p['auth'], 'gambling', 'cut', ['id' => 1]);
        $this->assertSame([2, 8000, 1, 8], [$cut['status'], $cut['info']['value'], $cut['info']['stoneid'], $cut['num']]);
        $this->assertSame(7, self::fieldValue($uid, 'stone1_times'));

        // 升 VIP 加免费切石次数（vip_model->add_stone_times：VIP 6~9 仙王玉石 + 10）
        DB::table('vip_log')->insert(['uid' => $uid, 'money' => 2, 'level' => 2, 'dateline' => time(), 'type' => 1]);
        Member::locked($uid, static fn() => Vip::pay($uid, 4, 1, 0, 'd4stone' . $uid, 'test', time()));
        $this->assertSame([6, 10], [self::memberValue($uid, 'vip'), self::fieldValue($uid, 'stone2_times')]);
    }

    // ---------------------------------------------------------------------------------------------
    // 神矿
    // ---------------------------------------------------------------------------------------------

    public function testJadegambleMiningGamblingAndHarvest(): void
    {
        $p = $this->player(['level' => 40, 'gold' => 1000]);
        $uid = $p['uid'];
        $this->assertSame(['status' => -1], $this->request($p['auth'], 'jadegamble', 'index'));
        BossClock::freeze(self::OPEN_TS);
        $index = $this->request($p['auth'], 'jadegamble', 'index');
        $this->assertSame(['status' => 1, 'info' => ['status' => 0], 'list' => [['id' => 1, 'price' => JadePrice::ROWS[1]['price']], ['id' => 2, 'price' => JadePrice::ROWS[2]['price']], ['id' => 3, 'price' => JadePrice::ROWS[3]['price']]], 'jade' => [1 => 0, 2 => 0, 3 => 0], 'cd' => 1483113600 - self::OPEN_TS], $index);
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'jadegamble', 'gambling'));
        $this->assertSame(['status' => -8], $this->request($p['auth'], 'jadegamble', 'mining', ['id' => 9]));

        $mining = $this->request($p['auth'], 'jadegamble', 'mining', ['id' => 1]);
        $num = $mining['info']['num'];
        $this->assertSame([1, 1, 1, $num * 2, JadeGamble::ROWS[1]['price1'], 1000 - JadePrice::ROWS[1]['price']], [$mining['status'], $mining['info']['type'], $mining['info']['multiple'], $mining['info']['next'], $mining['info']['price'], $mining['gold']]);
        $this->assertGreaterThanOrEqual(1, $num);
        $this->assertSame([1, $num, 0], [self::activityValue($uid, 'jadetype'), self::activityValue($uid, 'jadenum'), self::activityValue($uid, 'jadeturn')]);
        $this->assertSame(['status' => 2], $this->request($p['auth'], 'jadegamble', 'mining', ['id' => 1]), '有未领取的宝石');
        $this->assertSame(['type' => 1, 'multiple' => 1, 'num' => $num, 'next' => $num * 2, 'price' => JadeGamble::ROWS[1]['price1'], 'status' => 1], $this->request($p['auth'], 'jadegamble', 'index')['info']);

        // 第一手成功率 100%：扣第一手价格，翻倍，另抽一个翻倍奖励
        $gold = self::memberValue($uid, 'gold');
        $gamble = $this->request($p['auth'], 'jadegamble', 'gambling');
        $this->assertSame([1, 1, ['type' => 1, 'num' => $num * 2, 'multiple' => 2, 'next' => $num * 4, 'price' => JadeGamble::ROWS[1]['price2']]], [$gamble['status'], $gamble['success'], $gamble['info']]);
        $this->assertArrayHasKey('type', $gamble['fb_info']);
        $this->assertSame(1, self::activityValue($uid, 'jadeturn'));
        $this->assertSame(2, DB::table('jade_gamble_log')->where('uid', $uid)->count());

        // 收获：按当前倍数发蓝宝石，记到最近一条活动记录
        $harvest = $this->request($p['auth'], 'jadegamble', 'harvest');
        $this->assertSame(['status' => 1, 'add_jade' => ['type' => 1, 'num' => $num * 2], 'jade' => [1 => TraderLog::jade($uid, 1)]], $harvest);
        $this->assertSame($num * 2, (int)DB::table('jadegamble_action_log')->where('uid', $uid)->value('get1'));
        $this->assertSame([0, 0, 0], [self::activityValue($uid, 'jadetype'), self::activityValue($uid, 'jadenum'), self::activityValue($uid, 'jadeturn')]);
        $this->assertSame(['status' => -2], $this->request($p['auth'], 'jadegamble', 'harvest'));
        $this->assertLessThan($gold, self::memberValue($uid, 'gold') + 1);
    }

    // ---------------------------------------------------------------------------------------------
    // 海外贸易
    // ---------------------------------------------------------------------------------------------

    public function testOverseasTradeTeamRobSettleLogAndBuy(): void
    {
        DB::execute('UPDATE `zc_sanguo_overseas_trade_team` SET `deleted_at` = NOW() WHERE `deleted_at` IS NULL');
        $a = $this->player(['level' => 60, 'silver' => 1000000, 'gold' => 1000, 'vip' => 0]);
        $b = $this->player(['level' => 60, 'silver' => 1000000, 'gold' => 1000]);
        $c = $this->player(['level' => 50, 'silver' => 0, 'gold' => 1000, 'vip' => 10]);
        $this->assertSame(2, $this->request($a['auth'], 'overseastrade', 'index')['status']);
        $countryA = self::country([$a, $b], 10);
        $countryC = self::country([$c], 10);
        $this->assertSame(3, $this->request($c['auth'], 'overseastrade', 'index')['status']);
        DB::table('member')->where('uid', $c['uid'])->update(['level' => 60]);

        $index = $this->request($a['auth'], 'overseastrade', 'index');
        $this->assertSame([1, 0, 0, ['times' => 0, 'price' => 50], ['allpage' => 1, 'list' => []], 1000000], [$index['status'], $index['btn_status'], $index['trade'], $index['rob'], $index['team'], $index['all_silver']]);
        $this->assertSame([5, 5, 0, 0], [$index['info']['times'], $index['info']['robtimes'], $index['info']['team'], $index['info']['commodity']]);
        // 货物：出售所得 × 主公等级，银币价格 × 主公等级
        $items = $this->request($a['auth'], 'overseastrade', 'item_list');
        $this->assertSame([1, 4800 * 60, 800 * 60, 1, 0], [$items['status'], $items['list'][0]['get'], $items['list'][0]['buy_value'], $items['list'][0]['open'], $items['list'][1]['open']]);
        $this->assertSame(['status' => -4], $this->request($a['auth'], 'overseastrade', 'buy_item', ['id' => 2]));
        $this->assertSame(['status' => 1, 'all_gold' => 1000, 'all_silver' => 1000000 - 48000], $this->request($a['auth'], 'overseastrade', 'buy_item', ['id' => 1]));
        $this->assertSame(['status' => -2], $this->request($a['auth'], 'overseastrade', 'buy_item', ['id' => 1]));
        $this->assertSame(['status' => -2], $this->request($a['auth'], 'overseastrade', 'item_list'));

        // A 在本国第 1 页第 2 个位置建队（站 1 号位）
        $this->assertSame(-8, $this->request($a['auth'], 'overseastrade', 'join_team', ['id' => 0, 'site' => 3, 'page' => 1, 'place' => 2])['status']);
        $join = $this->request($a['auth'], 'overseastrade', 'join_team', ['id' => 0, 'site' => 1, 'page' => 1, 'place' => 2]);
        $teamId = $join['team_id'];
        $this->assertSame(['status' => 1, 'commodity' => 1, 'team_id' => $teamId, 'times' => 0, 'endtime' => 0, 'price' => 5], $join);
        $this->assertSame(-4, $this->request($a['auth'], 'overseastrade', 'join_team', ['id' => 0, 'site' => 1, 'page' => 1, 'place' => 3])['status']);
        $list = $this->request($b['auth'], 'overseastrade', 'get_list_by_country', ['p' => 1]);
        $this->assertSame([1, 0, 0, 5, 1, [2]], [$list['status'], $list['teamid'], $list['trade'], $list['allpage'], $list['page'], array_keys($list['list'])]);
        $this->assertSame([$teamId, $a['uid'], 60, 1, 0], [$list['list'][2]['id'], $list['list'][2]['member1'], $list['list'][2]['member1_level'], $list['list'][2]['member1_commodity'], $list['list'][2]['member2']]);
        // B 买货后加入 2 号位：两人凑齐自动出发，各扣一次贸易次数
        $this->assertSame(-2, $this->request($b['auth'], 'overseastrade', 'join_team', ['id' => $teamId, 'site' => 2, 'page' => 1, 'place' => 2])['status']);
        $this->request($b['auth'], 'overseastrade', 'buy_item', ['id' => 1]);
        $this->assertSame(-9, $this->request($b['auth'], 'overseastrade', 'join_team', ['id' => 0, 'site' => 1, 'page' => 1, 'place' => 2])['status'], '该位置已有队伍');
        $this->assertSame(-6, $this->request($b['auth'], 'overseastrade', 'join_team', ['id' => $teamId, 'site' => 1, 'page' => 1, 'place' => 2])['status']);
        $join = $this->request($b['auth'], 'overseastrade', 'join_team', ['id' => $teamId, 'site' => 2, 'page' => 1, 'place' => 2]);
        $this->assertSame(['status' => 1, 'commodity' => 1, 'team_id' => $teamId, 'times' => 4, 'endtime' => 900, 'price' => 0], $join);
        $this->assertSame([4, 4], [(int)DB::table('overseas_trade_info')->where('uid', $a['uid'])->value('times'), (int)DB::table('overseas_trade_info')->where('uid', $b['uid'])->value('times')]);
        $this->assertSame(1, (int)DB::table('overseas_trade_team')->where('id', $teamId)->value('status'));
        $index = $this->request($a['auth'], 'overseastrade', 'index');
        $this->assertSame([3, 1, $teamId, 144000], [$index['btn_status'], $index['trade'], $index['team']['list'][0]['id'], $index['team']['list'][0]['get']]);

        // C（他国）打劫：A、B 阵法为空，C 连胜两场打劫成功，得收益较高一方的一半
        self::emptyFormation($a['uid']);
        self::emptyFormation($b['uid']);
        $this->assertSame(['status' => -3], $this->request($c['auth'], 'overseastrade', 'rob', ['id' => 999999]));
        $rob = $this->request($c['auth'], 'overseastrade', 'rob', ['id' => $teamId]);
        $this->assertSame(['status' => 1, 'win' => 1, 'get' => 144000, 'all_silver' => 144000, 'robtimes' => 4], $rob);
        $this->assertSame(['status' => -8], $this->request($c['auth'], 'overseastrade', 'rob', ['id' => $teamId]));
        $this->assertSame(2, (int)DB::table('overseas_trade_team')->where('id', $teamId)->value('status'));
        $log = DB::table('overseas_trade_log')->where('attack_uid', $c['uid'])->first();
        $this->assertSame([2, 1, 144000, 144000, 144000, 1, 1], [(int)$log['type'], (int)$log['win'], (int)$log['get'], (int)$log['member1_get'], (int)$log['member2_get'], (int)$log['member1_win'], (int)$log['member2_win']]);
        $pmA = DB::table('pm')->where('touid', $a['uid'])->where('type', 9)->first();
        $this->assertStringContainsString('的船只,您的收益减半,获得银币144000', (string)$pmA['message']);
        $notice = DB::table('country_fight_notice')->where('attack_uid', $c['uid'])->first();
        $this->assertSame([1, $countryC, $countryA, $a['uid'], $b['uid']], [(int)$notice['type'], (int)$notice['attack_country'], (int)$notice['member_country'], (int)$notice['member1'], (int)$notice['member2']]);
        $report = $this->request($c['auth'], 'overseastrade', 'get_report_info', ['id' => (int)$log['member1_report']]);
        $this->assertSame([1, 'd4_' . $c['uid'], 60, 'd4_' . $a['uid']], [$report['status'], $report['battle']['left_role']['name'], $report['battle']['left_role']['level'], $report['battle']['right_role']['name']]);
        $this->assertSame($report['battle']['assess'], $report['info']['win']);
        $this->assertSame(['status' => -1], $this->request($c['auth'], 'overseastrade', 'get_report_info', ['id' => 999999]));

        // A 回城：被劫收益减半，计主线任务 14
        $silver = Member::findByUid($a['uid'])->silver;
        $index = $this->request($a['auth'], 'overseastrade', 'index');
        $this->assertSame([3, 144000, 0], [$index['trade'], $index['get'], $index['btn_status']]);
        $this->assertSame($silver + 144000, Member::findByUid($a['uid'])->silver);
        $this->assertSame([0, 0], [(int)DB::table('overseas_trade_info')->where('uid', $a['uid'])->value('team'), (int)DB::table('overseas_trade_info')->where('uid', $a['uid'])->value('commodity')]);
        $refresh = $this->request($b['auth'], 'overseastrade', 'refresh', ['p' => 1]);
        $this->assertSame([1, 3, 144000, $teamId], [$refresh['status'], $refresh['trade'], $refresh['get'], $refresh['team_id']]);
        // 贸易记录：打劫方 3 / 防守失败 6 / 多人贸易 2
        $this->assertSame([3], array_column($this->request($c['auth'], 'overseastrade', 'get_trade_log', ['p' => 1])['list'], 'type'));
        $this->assertSame([6, 2], array_column($this->request($a['auth'], 'overseastrade', 'get_trade_log', ['p' => 1])['list'], 'type'));
        $this->assertSame(['status' => 1, 'list' => [], 'page' => 1], $this->request($a['auth'], 'overseastrade', 'get_trade_log', ['p' => 2]));

        // 单人贸易（5 元宝），15 分钟后回城得全额收益并补记贸易完成记录
        $this->request($a['auth'], 'overseastrade', 'buy_item', ['id' => 1]);
        $teamId = $this->request($a['auth'], 'overseastrade', 'join_team', ['id' => 0, 'site' => 1, 'page' => 2, 'place' => 1])['team_id'];
        $this->assertSame(['status' => 1, 'times' => 3, 'team_id' => $teamId, 'endtime' => 900, 'all_gold' => 995], $this->request($a['auth'], 'overseastrade', 'trade'));
        $this->assertSame(['status' => -4], $this->request($a['auth'], 'overseastrade', 'goout'));
        DB::table('overseas_trade_team')->where('id', $teamId)->update(['startdateline' => time() - 900]);
        $index = $this->request($a['auth'], 'overseastrade', 'index');
        $this->assertSame([2, 288000], [$index['trade'], $index['get']]);
        $this->assertSame(288000, (int)DB::table('overseas_trade_log')->where('team_id', $teamId)->value('member1_get'));
        $this->assertSame([7, 1], array_slice(array_column($this->request($a['auth'], 'overseastrade', 'get_trade_log', ['p' => 1])['list'], 'type'), 0, 2));

        // 退出未出发的队伍；退出国家时清理队伍（clear_team）
        $this->request($a['auth'], 'overseastrade', 'buy_item', ['id' => 1]);
        $teamId = $this->request($a['auth'], 'overseastrade', 'join_team', ['id' => 0, 'site' => 1, 'page' => 3, 'place' => 1])['team_id'];
        $this->assertSame(['status' => 1], $this->request($a['auth'], 'overseastrade', 'goout'));
        $this->assertNotNull(DB::table('overseas_trade_team')->where('id', $teamId)->value('deleted_at'));
        $this->assertSame(['status' => -2], $this->request($a['auth'], 'overseastrade', 'goout'));
        $teamId = $this->request($a['auth'], 'overseastrade', 'join_team', ['id' => 0, 'site' => 1, 'page' => 3, 'place' => 1])['team_id'];
        $this->assertSame(1, OverseasTradeHandler::clearTeam($a['uid']));
        $this->assertSame(2, OverseasTradeHandler::clearTeam($a['uid']));
        $this->assertNotNull(DB::table('overseas_trade_team')->where('id', $teamId)->value('deleted_at'));

        // 购买打劫次数（VIP 10 每天 5 次，50 元宝起每次 + 10）
        $this->assertSame(['status' => -6, 'msg' => '你需要达到VIP10才能进行购买'], $this->request($a['auth'], 'overseastrade', 'buy'));
        $this->assertSame(['times' => 5, 'price' => 50], $this->request($c['auth'], 'overseastrade', 'index')['rob']);
        $this->assertSame(['status' => 1, 'gold' => 60, 'all_gold' => 950, 'times' => 4, 'robtimes' => 5], $this->request($c['auth'], 'overseastrade', 'buy'));
        $this->assertSame(['status' => 1, 'gold' => 70, 'all_gold' => 890, 'times' => 3, 'robtimes' => 6], $this->request($c['auth'], 'overseastrade', 'buy'));
        $this->assertSame([2, 4], [(int)DB::table('overseas_trade_info')->where('uid', $c['uid'])->value('re_robtimes'), (int)DB::table('overseas_trade_info')->where('uid', $c['uid'])->value('robtimes')]);
        $this->assertSame(1, $this->request($c['auth'], 'overseastrade', 'index')['status']);
    }

    // ---------------------------------------------------------------------------------------------
    // 主城气泡
    // ---------------------------------------------------------------------------------------------

    public function testMemberIndexMineAndWorkshopBubbles(): void
    {
        $p = $this->player(['level' => 100]);
        $uid = $p['uid'];
        $res = $this->request($p['auth'], 'member', 'index');
        $this->assertSame([0, 0], [$res['onmine'], $res['onworkshop']]);
        DB::table('member_field')->where('uid', $uid)->update(['onmine' => time() - 4 * 3600, 'onworkshop' => time() - 1]);
        $res = $this->request($p['auth'], 'member', 'index');
        $this->assertSame([1, 1], [$res['onmine'], $res['onworkshop']]);
        DB::table('member_field')->where('uid', $uid)->update(['onmine' => time() - 3600, 'onworkshop' => time() + 3600]);
        $res = $this->request($p['auth'], 'member', 'index');
        $this->assertSame([0, 0], [$res['onmine'], $res['onworkshop']]);
    }
}
