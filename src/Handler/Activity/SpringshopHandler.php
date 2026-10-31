<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Springshop;
use Hanjiang\MasterData\Talent;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\PubLog;
use Hanjiang\Model\SpringReward;
use Hanjiang\Model\TraderLog;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 春节商城（武将商城；移植旧版 controllers/springshop.php + models/springshop_model.php，全服限售数量为 uc 的
 * controllers/springshop.php）
 *
 * 活动时间与版本取 ActivityTime 的 springshop（旧版数据 2016-12-31 已结束，接口回 2）。礼包取 MasterData Springshop：
 * buytype 1 花元宝（price），否则花紫宝石 price × 折扣 + 声望 price1 × 折扣（已拥有礼包里的武将不能买）；每个礼包每天全服限售
 * number 份（uc 的 Redis 计数 spring_buytimes_{Ymd}_{id}，24 小时过期），购买记 springshop_log。礼包内容 type1 ~ type4：
 * 1 元宝 2 银币 3 黄宝石 4 紫宝石 5 原魂 6 玉石 7 声望 8 装备 9 魔石碎片 10 突飞卡 11 坐骑 12 蓝宝石 13 武将 14 军令。
 * 获得武将：7 星以上发小喇叭、聊天室广播（类型 29）、酒馆日志（获取方式 7 全服限量）、活动人次 tavern_action_log。
 * 客户端 springshoppop.js 调用 index、buy（id）。当前时间取 BossClock（生产恒为 time()）。
 *
 * 单服部署：uc 的限售计数在本进程内（旧版 s1 经 uc_model 调用 uc，uc 不通时退回本地缓存 cache__{Ymd}；本进程内 uc 总是可用，
 * 本地缓存不移植）。uc 的 springshop_model 不在旧版代码包里，按 s1 springshop_model 的同名方法还原（键名、24 小时过期）。
 * 照旧版：礼包已售完时回 status 1（带 msg 与刷新后的列表，列表里第 id 个礼包标为售完）；buy 的列表 is_buy 照旧版第三段循环
 * 不再把剩余数量为 0 的写成 0（index 与售完分支写 0）。
 * 与旧版的差别：购买锁改为 Redis SET NX（旧版 get + setex，有并发窗口）；全服限售先原子占一份（INCR），付款失败退回
 * （旧版先查后加，并发可超卖）；购买在玩家行锁里执行。
 */
final class SpringshopHandler
{
    public const string ACTIVITY = 'springshop';

    /** 商品购买次数记录（_buy_times） */
    private const string BUY_TIMES_KEY = 'spring_buytimes_';
    /** 购买锁（_lock_key，60 秒） */
    private const string LOCK_KEY = 'spring_lock_';
    private const int LOCK_TTL = 60;

    /** spring_lang.php / general_lang.php */
    private const array LANG = [
        'spring_shop_error1' => '活动未开启',
        'spring_shop_error2' => '获取礼包失败',
        'spring_shop_error4' => '元宝不足',
        'spring_shop_error5' => '紫宝石不足',
        'spring_shop_error6' => '声望不足',
        'spring_shop_error7' => '请稍等一下再购买礼包',
        'spring_shop_error8' => '未找到信息',
        'spring_shop_error9' => '已拥有该武将，不能再购买',
        'spring_shop_error10' => '礼包已售完',
        'general_get' => '恭喜玩家【%s】获得%s星武将-%s',
    ];

    /**
     * 春节商城首页面: c=springshop&m=index
     *
     * 1 成功；2 活动未开启；-1 获取春节礼包失败
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $activity = ActivityClock::open(self::ACTIVITY, $now);
        // 活动已结束
        if ($activity === null || $uid <= 0) {
            $return['status'] = 2;
            $return['msg'] = self::LANG['spring_shop_error1'];
            return $return;
        }
        $return['end_time'] = $activity['endtime'] - $now;
        // 获取春节商城里所有礼包的信息
        if (Springshop::ROWS === []) {
            $return['status'] = -1;
            $return['msg'] = self::LANG['spring_shop_error2'];
            return $return;
        }
        $return['get3'] = TraderLog::jade($uid, 3);
        $return['reputation'] = (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('reputation');
        $return['list'] = self::giftList($uid, $now, true);
        // 成功返回
        $return['status'] = 1;
        return $return;
    }

    /**
     * 购买礼包: c=springshop&m=buy（id 礼包 id）
     *
     * 1 成功（或礼包已售完，带 msg）；2 活动未开启；-1 获取礼包信息失败；-4 元宝不足；-5 紫宝石不足；-6 声望不足；
     * -7 请稍等（购买锁）；-8 没有传礼包 id；-9 已拥有该武将
     */
    public static function buy(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $activity = ActivityClock::open(self::ACTIVITY, $now);
        // 活动已结束
        if ($activity === null || $uid <= 0) {
            $return['status'] = 2;
            $return['msg'] = self::LANG['spring_shop_error1'];
            self::unlock($uid);
            return $return;
        }
        $return['end_time'] = $activity['endtime'] - $now;
        // 增加锁标志
        if (!(bool)Redis::instance()->set(self::LOCK_KEY . $uid, 1, ['nx', 'ex' => self::LOCK_TTL])) {
            $return['status'] = -7;
            $return['msg'] = self::LANG['spring_shop_error7'];
            return $return;
        }
        $return = Member::locked($uid, static fn (): array => self::doBuy($uid, (int)$ctx->input('id', 0), (int)$activity['version'], $now, $return));
        self::unlock($uid);
        return $return;
    }

    /**
     * 购买（在玩家行锁里执行）
     *
     * @param array<string, mixed> $return
     * @return array<string, mixed>
     */
    private static function doBuy(int $uid, int $id, int $version, int $now, array $return): array
    {
        if ($id === 0) {
            // 没有找到信息
            $return['status'] = -8;
            $return['msg'] = self::LANG['spring_shop_error8'];
            return $return;
        }
        // 通过礼包ID找出信息来
        $result = Springshop::ROWS[$id] ?? null;
        if ($result === null) {
            $return['status'] = -1;
            $return['msg'] = self::LANG['spring_shop_error2'];
            return $return;
        }
        $member = DB::table('member')->select('uid', 'nickname', 'level', 'gold', 'reputation')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($member === null) {
            $return['status'] = -1;
            $return['msg'] = self::LANG['spring_shop_error2'];
            return $return;
        }

        // 全服限售：先占一份，付款失败时退回
        $buyKey = self::BUY_TIMES_KEY . date('Ymd', $now) . '_' . $id;
        if ($result['number'] > 0) {
            $buyTimes = (int)Redis::incr($buyKey);
            Redis::expire($buyKey, 86400);
            if ($buyTimes > $result['number']) {
                Redis::decr($buyKey);
                // 礼包已售完
                $return['status'] = 1;
                $return['list'] = self::giftList($uid, $now, true);
                $return['list'][$id - 1]['is_buy'] = 2;
                $return['msg'] = self::LANG['spring_shop_error10'];
                return $return;
            }
        }
        $release = static function () use ($result, $buyKey): void {
            if ($result['number'] > 0) {
                Redis::decr($buyKey);
            }
        };

        // 根据buytype来扣除玩家的元宝或者紫宝石(1:元宝 2:紫宝石)
        if ($result['buytype'] === 1) {
            if (!Member::deductCurrency($uid, gold: $result['price'])) {
                $release();
                // 元宝不足
                $return['status'] = -4;
                $return['msg'] = self::LANG['spring_shop_error4'];
                return $return;
            }
        } else {
            // 判断玩家是否已拥有该武将
            if (General::ownsTemplate($uid, $result['type1_num'])) {
                $release();
                $return['status'] = -9;
                $return['msg'] = self::LANG['spring_shop_error9'];
                return $return;
            }
            $jadeCost = (int)($result['price'] * $result['discount'] / 100);
            $repCost = (int)($result['price1'] * $result['discount'] / 100);
            // 获取用户的紫宝石数量
            if (TraderLog::jade($uid, 3) < $jadeCost) {
                $release();
                $return['status'] = -5;
                $return['msg'] = self::LANG['spring_shop_error5'];
                return $return;
            }
            if ((int)$member['reputation'] < $repCost) {
                $release();
                $return['status'] = -6;
                $return['msg'] = self::LANG['spring_shop_error6'];
                return $return;
            }
            // 消费紫宝石
            if (TraderLog::subJade($uid, 3, $jadeCost, 'springshop', 'buy', $now)['status'] !== 1) {
                $release();
                $return['status'] = -5;
                $return['msg'] = self::LANG['spring_shop_error5'];
                return $return;
            }
            // 扣除声望
            Member::subReputation($uid, $repCost);
        }

        // 用户购买记录到记录表中
        DB::table('springshop_log')->insert(['uid' => $uid, 'dateline' => $now, 'giftid' => $id, 'version' => $version]);

        // 付完钱后将相应的产品添加到用户的表里
        for ($i = 1; $i <= 4; $i++) {
            $type = $result['type' . $i];
            $num = $result['type' . $i . '_num'];
            switch ($type) {
                case 8:
                    // 添加装备
                    $item = Equip::ROWS[$num] ?? null;
                    if ($item !== null) {
                        Equipment::addFromTemplate($uid, $num);
                    }
                    $return['result'][$type][] = $item ?? [];
                    break;
                case 11:
                    // 添加坐骑，返回坐骑信息
                    OwnMount::addMount($uid, $num, 0, 1, 'springshop', 'buy', $now);
                    $mountInfo = MountTemp::ROWS[$num] ?? [];
                    // 发送广播到聊天室和小喇叭
                    $sender = Chat::sender($uid);
                    Chat::sendToAll(Chat::getMessageStr(31, ['springmount' => $mountInfo['name'] ?? ''], $sender), Chat::TYPE_SYSTEM, 0, 0, $sender);
                    Chat::sendToHorn(sprintf(Chat::LANG['chat_springmount_get'], $member['nickname'], $mountInfo['name'] ?? ''), $sender);
                    $return['result'][$type] = $mountInfo;
                    break;
                case 13:
                    // 添加武将
                    $general = General::addGeneral($uid, $num);
                    if ($general !== null) {
                        $sender = Chat::sender($uid);
                        $star = Chat::starWord((int)$general['star']);
                        if ($star !== null) {
                            Chat::sendToHorn(sprintf(self::LANG['general_get'], $member['nickname'], $star, $general['name']), $sender);
                        }
                        Chat::sendToAll(Chat::getMessageStr(29, ['general' => $general['name']], $sender), Chat::TYPE_SYSTEM, 0, 0, $sender);
                        // 添加酒馆日志(武将id, 拥有状态, 获取方式 7 全服限量)
                        PubLog::add($uid, $num, 1, 7, $now);
                        // 记录活动人次（旧版 name 列为 char(10)，超长截断）
                        DB::table('tavern_action_log')->insert([
                            'uid' => $uid,
                            'name' => mb_substr((string)$general['name'], 0, 10),
                            'level' => (int)$member['level'],
                            'dateline' => $now,
                        ]);
                    }
                    // 旧版 add_general 已拥有时返回 {status: -3}
                    $return['general'] = $general ?? ['status' => -3];
                    break;
                default:
                    if ($type > 0 && SpringReward::grant($uid, $type, $num, 'springshop', 'buy', $now)) {
                        $return['result'][$type] = $num;
                    }
                    break;
            }
        }
        Redis::del('player:' . $uid);

        $after = DB::table('member')->select('gold', 'silver', 'act', 'reputation')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        $return['get3'] = TraderLog::jade($uid, 3);
        // 成功返回
        $return['status'] = 1;
        $return['all_gold'] = (int)($after['gold'] ?? 0);
        $return['all_silver'] = (int)($after['silver'] ?? 0);
        $return['all_act'] = (int)($after['act'] ?? 0);
        $return['reputation'] = (int)($after['reputation'] ?? 0);
        $return['list'] = self::giftList($uid, $now, false);
        return $return;
    }

    /**
     * 礼包列表：每个礼包附武将模板（含天赋 / 技能行）、type / image、折后紫宝石 / 声望、今日剩余份数 buytimes；
     * is_buy 1 可买、2 已售完、0 已拥有该武将
     *
     * $soldOutZero 为 true 时售完的礼包 buytimes 写 0（index 与 buy 售完分支），false 时保持初始的 0（buy 成功后的第三段循环
     * 不写，结果相同）。
     *
     * @return list<array<string, mixed>>
     */
    private static function giftList(int $uid, int $now, bool $soldOutZero): array
    {
        // 获取玩家招募信息（已拥有的武将）
        $ownIdList = array_map(
            static fn (array $row): int => (int)$row['generalid'],
            DB::table('pub_log')->select('generalid')->where('uid', $uid)->where('own', 1)->whereNull('deleted_at')->get()
        );
        $ymd = date('Ymd', $now);
        $result = [];
        foreach (array_values(Springshop::ROWS) as $key => $val) {
            $row = $val;
            $row['is_buy'] = 1;
            $row['buytimes'] = 0;
            $general = GeneralTemplate::ROWS[$val['type1_num']] ?? null;
            $row['general'] = $general;
            if ($general !== null) {
                $row['general']['talent'] = Talent::ROWS[$general['talentid']] ?? [];
                $row['general']['skill'] = Skill::ROWS[$general['skillid']] ?? [];
            }
            $row['type'] = $general['type'] ?? null;
            $row['image'] = $general['image'] ?? null;
            // 折扣后的武将紫宝石和声望
            $row['price_discount'] = $val['price'] * $val['discount'] / 100;
            $row['price1_discount'] = $val['price1'] * $val['discount'] / 100;
            if ($val['number'] > 0) {
                $buytimes = (int)(Redis::get(self::BUY_TIMES_KEY . $ymd . '_' . $val['id']) ?? 0);
                if ($val['number'] > $buytimes) {
                    $row['buytimes'] = $val['number'] - $buytimes;
                } else {
                    if ($soldOutZero) {
                        $row['buytimes'] = 0;
                    }
                    $row['is_buy'] = 2;
                }
            }
            if (in_array($val['type1_num'], $ownIdList, true)) {
                $row['is_buy'] = 0;
            }
            $result[$key] = $row;
        }
        return $result;
    }

    private static function unlock(int $uid): void
    {
        if ($uid > 0) {
            Redis::del(self::LOCK_KEY . $uid);
        }
    }
}
