<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\Pvp\SiegeHandler;
use Hanjiang\Model\Edifice;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 玉石作坊（移植旧版 controllers/workshop.php + models/workshop_model.php）
 *
 * 客户端（workshoppop.js）调用：index / get_reward(s) / levelup(s, t) / open_workshop_site(t)。主公 91 级开启（status 2）。
 * 每个采集位一行 workshop（site 1 ~ 8、level 0 ~ 5、starttime 开始采集时间），第一次打开时自动开启第 1 个。
 * 每小时产量 = floor(主公等级 × 5 × 等级系数 result)，再按国家都城效果 type 3（玉石作坊）加 value%；仓储上限 = 产量 × add
 * （add 小时装满）；当前产量 = floor(已采集分钟数 × 产量 / 60)，封顶仓储上限。收获加玉石（member.jade）。
 * 采集位等级表与开启价格是旧版 workshop_model 写死的数组（get_workshop_level / get_site_price）。
 * member_field.onworkshop 记最早装满的时间（主城 onworkshop 气泡）。打开时记主城建筑首次打开（edifice 8）。
 *
 * 与旧版的差别：各接口在 Member::locked（玩家行锁）里执行；收获按读到的 starttime 条件更新（并发不会重复收获）。
 * 旧版采集位个数写成 91、开启价格只配到第 8 个：第 9 个起价格为空、按 0 元宝开出表外采集位，新版回「采集位已全部开启」(-2)。
 */
final class WorkshopHandler
{
    /** 开启等级（旧版 $_level） */
    private const int OPEN_LEVEL = 91;
    /** 采集位等级（get_workshop_level：name / result 产量系数 / price 升级元宝 / vip / add 装满小时数） */
    private const array LEVEL = [
        0 => ['name' => '普通', 'result' => 1, 'price' => 0, 'vip' => 0, 'add' => 8],
        1 => ['name' => '加强', 'result' => 1.5, 'price' => 50, 'vip' => 1, 'add' => 8],
        2 => ['name' => ' 高级', 'result' => 2, 'price' => 100, 'vip' => 3, 'add' => 12],
        3 => ['name' => '白金', 'result' => 2.5, 'price' => 200, 'vip' => 4, 'add' => 24],
        4 => ['name' => '钻石', 'result' => 3, 'price' => 300, 'vip' => 6, 'add' => 48],
        5 => ['name' => '至尊', 'result' => 4, 'price' => 600, 'vip' => 8, 'add' => 72],
    ];
    /** 采集位开启价格（get_site_price，旧版值是字符串） */
    private const array SITE_PRICE = [
        1 => ['price' => '0', 'vip' => '0'],
        2 => ['price' => '50', 'vip' => '0'],
        3 => ['price' => '100', 'vip' => '0'],
        4 => ['price' => '200', 'vip' => '1'],
        5 => ['price' => '500', 'vip' => '2'],
        6 => ['price' => '1000', 'vip' => '4'],
        7 => ['price' => '2000', 'vip' => '6'],
        8 => ['price' => '5000', 'vip' => '7'],
    ];
    /** 都城效果：玉石作坊加成 */
    private const int EFFECT_WORKSHOP = 3;

    /**
     * 初始化接口: c=workshop&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return Member::locked($uid, static function () use ($uid): array {
            $return = ['status' => 0];
            $now = time();
            [$member, $addition] = self::init($uid, $now);
            // 判断用户等级是否达到开启条件
            if ($member === null) {
                $return['status'] = 2;
                return $return;
            }
            // 取用户记录。得到开启了几个采集位
            $userLog = self::workshopLog($uid, $now);
            $list = [];
            foreach ($userLog as $key => $val) {
                // 获得该采集位的产量和仓储量
                $add = self::volume((int)$member['level'], $addition, $val['level'], $val['starttime'], $now);
                $list[$key] = [
                    'uid' => $val['uid'],
                    'site' => $val['site'],
                    'level' => $val['level'],
                    'get' => $add['volume'] === $add['yield'] ? 1 : 0,
                    'full_level' => $val['level'] === 5 ? 1 : 0,
                    'volume' => $add['volume'],
                    'yield' => $add['yield'],
                    'add' => $add['add'],
                ];
            }
            // 首次打开操作记录 (类型8)
            Edifice::addLog($uid, Edifice::WORKSHOP);

            $return['status'] = 1;
            $return['list'] = $list;
            $return['addition'] = $addition > 0 ? $addition + 100 : 0;
            $return['all_jade'] = MemberJade::get($uid);
            return $return;
        });
    }

    /**
     * 收获: c=workshop&m=get_reward&s=采集位
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $site = self::siteInput($ctx);
        return Member::locked($uid, static function () use ($uid, $site): array {
            $return = ['status' => 0];
            $now = time();
            [$member, $addition] = self::init($uid, $now);
            if ($member === null) {
                $return['status'] = 2;
                return $return;
            }
            if ($site === 0) {
                // 采集位不存在
                $return['status'] = -1;
                return $return;
            }
            // 获取该采集位信息
            $workInfo = self::infoBySite($uid, $site);
            if ($workInfo === null) {
                // 采集位未开启
                $return['status'] = -2;
                return $return;
            }
            // 获取采集位当前产量
            $volume = self::volume((int)$member['level'], $addition, $workInfo['level'], $workInfo['starttime'], $now);
            if ($volume['yield'] < 1) {
                // 还没有采集完成，不能提前收获
                $return['status'] = -3;
                return $return;
            }
            // 执行收获（以读到的开始时间条件更新）
            if (self::updateStarttime($uid, $site, $now, $workInfo['starttime']) !== 1) {
                // 收获失败
                $return['status'] = -4;
                return $return;
            }
            self::updateWorkshopTime($uid);
            $jade = MemberJade::add($uid, $volume['yield'], 'workshop', 'get_reward', $now);
            $return['status'] = 1;
            $return['add'] = $volume['yield'];
            $return['all_jade'] = $jade['jade'];
            return $return;
        });
    }

    /**
     * 采集位升级: c=workshop&m=levelup&s=采集位&t=1（不传 t 只返回升级信息）
     */
    public static function levelup(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $site = self::siteInput($ctx);
        $type = (int)$ctx->input('t', 0);
        return Member::locked($uid, static function () use ($uid, $site, $type): array {
            $return = ['status' => 0];
            $now = time();
            [$member, $addition] = self::init($uid, $now);
            if ($member === null) {
                $return['status'] = 2;
                return $return;
            }
            if ($site === 0) {
                // 采集位不存在
                $return['status'] = -1;
                return $return;
            }
            // 获取该采集位信息
            $workInfo = self::infoBySite($uid, $site);
            if ($workInfo === null) {
                // 采集位未开启
                $return['status'] = -2;
                return $return;
            }
            $levelInfo = self::LEVEL[$workInfo['level'] + 1] ?? null;
            if ($levelInfo === null) {
                // 已满级
                $return['status'] = -6;
                return $return;
            }
            $level = (int)$member['level'];
            $return['price'] = $levelInfo['price'];
            $return['viplevel'] = $levelInfo['vip'];

            // 获取升级需要vip等级和金币
            if ($type !== 1) {
                $nowLevel = self::LEVEL[$workInfo['level']];
                $volume = self::volume($level, $addition, $workInfo['level'], 0, $now);
                $return['now_add'] = $volume['add'];
                $return['now_volume'] = $volume['volume'];
                $return['now_name'] = $nowLevel['name'];
                $return['now_result'] = (int)($nowLevel['result'] * 100);
                $return['now_level'] = $workInfo['level'];
            }

            if ($type === 1) {
                if ((int)$member['vip'] < $levelInfo['vip']) {
                    // vip等级不够
                    $return['status'] = -3;
                    return $return;
                }
                if ((int)$member['gold'] < $levelInfo['price']) {
                    // 元宝不够
                    $return['status'] = -4;
                    return $return;
                }
                // 执行升级
                $updated = DB::table('workshop')->where('uid', $uid)->where('site', $site)->whereNull('deleted_at')
                    ->update(['level' => $workInfo['level'] + 1]);
                if ($updated <= 0) {
                    // 升级失败
                    $return['status'] = -5;
                    return $return;
                }
                // 取出我之前的产量，按新的每小时产量折算成已采集时间（保留产量）
                $volume = self::volume($level, $addition, $workInfo['level'], $workInfo['starttime'], $now);
                $newVolume = self::volume($level, $addition, $workInfo['level'] + 1, 0, $now);
                $times = (int)(ceil($volume['yield'] / $newVolume['add'] * 60) * 60);
                // 修改开始时间
                $starttime = $now - $times;
                self::updateStarttime($uid, $site, $starttime);
                $workInfo['starttime'] = $starttime;

                Member::deductCurrency($uid, gold: $levelInfo['price']);
                $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
                $return['full_level'] = $workInfo['level'] + 1 === 5 ? 1 : 0;
            }

            self::updateWorkshopTime($uid);
            $return['status'] = 1;
            // 升级后的属性
            $volume = self::volume($level, $addition, $workInfo['level'] + 1, $workInfo['starttime'], $now);
            $return['add'] = $volume['add'];
            $return['volume'] = $volume['volume'];
            $return['yield'] = $volume['yield'];
            $return['name'] = $levelInfo['name'];
            $return['result'] = (int)($levelInfo['result'] * 100);
            $return['level'] = $workInfo['level'] + 1;
            return $return;
        });
    }

    /**
     * 开启采集位: c=workshop&m=open_workshop_site&t=1（不传 t 只返回开启价格）
     */
    public static function open_workshop_site(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $type = (int)$ctx->input('t', 0);
        return Member::locked($uid, static function () use ($uid, $type): array {
            $return = ['status' => 0];
            $now = time();
            [$member, $addition] = self::init($uid, $now);
            if ($member === null) {
                $return['status'] = 2;
                return $return;
            }
            // 取用户记录。得到开启了几个采集位
            $userLog = self::workshopLog($uid, $now);
            // 要开启的采集位
            $site = count($userLog) + 1;
            // 旧版个数写成 91、价格只到第 8 个（第 9 个起按空价格开出表外采集位），新版按全部开启处理
            $info = self::SITE_PRICE[$site] ?? null;
            if ($info === null) {
                // 采集位已全部开启
                $return['status'] = -2;
                return $return;
            }
            if (isset($userLog[$site])) {
                // 该采集位已开启
                $return['status'] = -3;
                return $return;
            }
            // 得到开启的价格
            $return['price'] = $info['price'];
            $return['viplevel'] = $info['vip'];

            if ($type === 1) {
                if ((int)$member['vip'] < (int)$info['vip']) {
                    // vip等级不够
                    $return['status'] = -4;
                    return $return;
                }
                if ((int)$member['gold'] < (int)$info['price']) {
                    // 元宝不足
                    $return['status'] = -5;
                    return $return;
                }
                // 开启采集位
                if (self::openSite($uid, $site, $now) !== 1) {
                    // 采集位开启失败
                    $return['status'] = -6;
                    return $return;
                }
                if (!Member::deductCurrency($uid, gold: (int)$info['price'])) {
                    // 扣除金币失败
                    $return['status'] = -7;
                    return $return;
                }
                $volume = self::volume((int)$member['level'], $addition, 0, 0, $now);
                $return['yield'] = $volume['yield'];
                $return['add'] = $volume['add'];
                $return['volume'] = $volume['volume'];
                $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
            }
            self::updateWorkshopTime($uid);
            // 成功返回
            $return['status'] = 1;
            return $return;
        });
    }

    /**
     * 开启状态与都城加成（构造函数：主公 91 级以上才开启，并取都城效果 type 3 的加成百分比）
     *
     * @return array{0: array<string, mixed>|null, 1: int} [未开启为 null 的玩家行, 加成百分比]
     */
    private static function init(int $uid, int $now): array
    {
        $member = DB::table('member')->select('uid', 'level', 'vip', 'gold', 'country')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($member === null || (int)$member['level'] < self::OPEN_LEVEL) {
            return [null, 0];
        }
        // 取都城占领加成
        $effect = SiegeHandler::getCityEffect($member, $now);
        $addition = $effect['status'] === 1 && ($effect['type'] ?? 0) === self::EFFECT_WORKSHOP ? (int)$effect['value'] : 0;
        return [$member, $addition];
    }

    /**
     * 采集位参数 s（旧版 in_array($site, range(1, 8))），不合法为 0
     */
    private static function siteInput(Context $ctx): int
    {
        $site = $ctx->input('s', 0);
        return is_numeric($site) && in_array((int)$site, range(1, 8), true) && (float)$site === (float)(int)$site ? (int)$site : 0;
    }

    /**
     * 得到仓储上限、产量（get_volume）
     *
     * @return array{volume: int, yield: int, add: int}
     */
    private static function volume(int $memberLevel, int $addition, int $level, int $starttime, int $now): array
    {
        $info = self::LEVEL[$level];
        // 单位产量
        $add = floor($memberLevel * 5 * $info['result']);
        $add = floor($add + $add * ($addition / 100));
        // 仓储参量
        $volume = $add * $info['add'];
        $yield = 0;
        if ($starttime) {
            // 当前产量(每分钟的产量*总共的时间)
            $yield = floor(floor(($now - $starttime) / 60) * ($add / 60));
            if ($yield > $volume) {
                $yield = $volume;
            }
            if ($yield < 0) {
                $yield = 0;
            }
        }
        return ['volume' => (int)$volume, 'yield' => (int)$yield, 'add' => (int)$add];
    }

    /**
     * 采集位列表（get_workshop_log：没有时开启第一个采集位），按 site 索引
     *
     * @return array<int, array{uid: int, site: int, level: int, starttime: int}>
     */
    private static function workshopLog(int $uid, int $now): array
    {
        $rows = self::rows($uid);
        if ($rows === []) {
            // 开启第一个采集位
            self::openSite($uid, 1, $now);
            self::updateWorkshopTime($uid);
            $rows = self::rows($uid);
        }
        $list = [];
        foreach ($rows as $val) {
            $list[$val['site']] = $val;
        }
        return $list;
    }

    /**
     * @return list<array{uid: int, site: int, level: int, starttime: int}>
     */
    private static function rows(int $uid): array
    {
        $rows = DB::table('workshop')->select('uid', 'site', 'level', 'starttime')->where('uid', $uid)->whereNull('deleted_at')
            ->orderBy('id', 'ASC')->get();
        return array_map(static fn(array $v): array => array_map('intval', $v), $rows);
    }

    /**
     * 单个采集位（get_info_by_site）
     *
     * @return array{uid: int, site: int, level: int, starttime: int}|null
     */
    private static function infoBySite(int $uid, int $site): ?array
    {
        $row = DB::table('workshop')->select('uid', 'site', 'level', 'starttime')->where('uid', $uid)->where('site', $site)
            ->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * 开启采集位（open_workshop_site：等级 0，从现在开始采集；(uid, site) 唯一，并发重复开启时失败）
     */
    private static function openSite(int $uid, int $site, int $now): int
    {
        $affected = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_workshop` (`uid`, `site`, `level`, `starttime`) VALUES (:uid, :site, 0, :now)',
            ['uid' => $uid, 'site' => $site, 'now' => $now]
        );
        return $affected > 0 ? 1 : 0;
    }

    /**
     * 修改开始采集时间（workshop_model->get_reward；$expect 非 null 时按读到的开始时间条件更新）
     */
    private static function updateStarttime(int $uid, int $site, int $starttime, ?int $expect = null): int
    {
        $query = DB::table('workshop')->where('uid', $uid)->where('site', $site)->whereNull('deleted_at');
        if ($expect !== null) {
            $query->where('starttime', $expect);
        }
        return $query->update(['starttime' => $starttime]) > 0 ? 1 : 0;
    }

    /**
     * 更新主城气泡时间（update_workshop_time：各采集位 开始时间 + 装满小时数 的最小值记到 member_field.onworkshop）
     */
    private static function updateWorkshopTime(int $uid): void
    {
        $time = [];
        foreach (self::rows($uid) as $val) {
            $time[] = $val['starttime'] + 60 * 60 * self::LEVEL[$val['level']]['add'];
        }
        if ($time === []) {
            return;
        }
        sort($time);
        DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['onworkshop' => $time[0]]);
    }
}
