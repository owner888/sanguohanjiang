<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Cross\UcModel;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\ChristmasgiftReward;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FestReward;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberIntegral;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\MemberStone;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\OwnStar;
use Hanjiang\Model\PubLog;
use Hanjiang\Model\TraderLog;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 圣诞团购礼包（移植旧版 controllers/christmasgift.php + models/christmasgift_model.php；全服限售数量为 uc 的
 * controllers/christmasgift.php，见 Cross\UcModel::christmasgift*）
 *
 * 活动时间、版本与类型取 ActivityTime 的 christmasgift（旧版数据 2016-12-31 已结束，接口回 2；type 1 为春节样式）。
 * 礼包取 MasterData ChristmasgiftReward：每个礼包每天每人可花 price 元宝买一次（christmasgift_log 按当天领取记录），
 * VIP 不足 vip 不能买，num > 0 的礼包每天全服限售 num 份。礼包内容 gettype1 ~ 4：1 银币 2 突飞卡 3 原魂 4 紫宝石 5 黄宝石
 * 6 宝石碎片 7 坐骑（getvalue 坐骑 id、getnum 数量）8 声望 9 玉石 10 精炼石 11 五彩石 12 上古血石 13 装备精华 14 玫瑰
 * 15 钻石 17 武将 18 星魄（getvalue 星魄类型、getnum 数量）。每次购买记活动人次 christmasgift_action_log。
 * 客户端 christmasgiftpop.js 调用 index、get_reward（id）。当前时间取 BossClock。
 *
 * 照旧版：列表里 is_buy 恒为 1（售完只体现在 buytimes 为 0）；get_reward 返回的列表用 uc add_buy_num 返回的购买数量。
 * 与旧版的差别：购买锁改为 Redis SET NX（旧版 get + setex 600 秒）；购买在玩家行锁事务里执行；全服限售先原子占一份，
 * 付款失败退回（旧版先查后加，并发可超卖）；christmasgift_log 写入与当天是否已买的判断在行锁内（旧版先查后写）。
 */
final class ChristmasgiftHandler
{
    public const string ACTIVITY = 'christmasgift';

    /** 用户领取奖励锁（_lock_key，600 秒） */
    private const string LOCK_KEY = 'christmasgift_lock_';
    private const int LOCK_TTL = 600;

    /**
     * 初始化接口: c=christmasgift&m=index
     *
     * 1 成功；2 活动已结束；-1 获取奖励信息失败
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $activity = ActivityClock::open(self::ACTIVITY, $now);
        // 判断活动是否结束
        if ($activity === null || $uid <= 0) {
            $return['status'] = 2;
            return $return;
        }
        // 获取奖励信息
        if (ChristmasgiftReward::ROWS === []) {
            $return['status'] = -1;
            return $return;
        }
        // 获取所有商品购买数量
        $result = UcModel::christmasgiftAllBuyNum($now);
        $return['status'] = 1;
        $return['list'] = self::rewardList($uid, (int)$activity['version'], $result, $now);
        $return['cd'] = self::nextDay($now) - $now;
        $return['time'] = date('n.d', $activity['starttime']) . '~' . date('n.d', $activity['endtime'] - 100);
        $return['last_day'] = self::lastDay($activity, $now);
        $return['type'] = (int)$activity['type'];
        return $return;
    }

    /**
     * 领取奖励: c=christmasgift&m=get_reward（id 礼包 id）
     *
     * 1 成功；2 活动已结束；-1 参数错误 / 礼包不存在 / 价格为 0；-3 今日已购买；-4 已售完；-5 元宝不足；-6 添加领取记录失败；
     * -7 上次请求没终止（锁）；-8 VIP 等级不足
     */
    public static function get_reward(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $id = (int)$ctx->input('id', 0);
        if ($id === 0) {
            $return['status'] = -1;
            return $return;
        }
        // 判断是否领取操作结束
        if ($uid <= 0 || !(bool)Redis::instance()->set(self::LOCK_KEY . $uid, 1, ['nx', 'ex' => self::LOCK_TTL])) {
            $return['status'] = -7;
            return $return;
        }
        try {
            $activity = ActivityClock::open(self::ACTIVITY, $now);
            // 判断活动是否结束
            if ($activity === null) {
                $return['status'] = 2;
                return $return;
            }
            $version = (int)$activity['version'];
            $day = (int)ceil(($now - (int)$activity['starttime']) / 86400);
            $result = Member::locked($uid, static fn (): array => self::buy($uid, $id, $version, $day, $now));
            if ($result['status'] !== 1) {
                $return['status'] = $result['status'];
                return $return;
            }
            if (isset($result['info'])) {
                $return['info'] = $result['info'];
            }
            $member = DB::table('member')->select('gold', 'silver', 'act')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
            // 成功返回
            $return['status'] = 1;
            $return['all_gold'] = (int)($member['gold'] ?? 0);
            $return['all_silver'] = (int)($member['silver'] ?? 0);
            $return['all_act'] = (int)($member['act'] ?? 0);
            $return['cd'] = self::nextDay($now) - $now;
            $return['list'] = self::rewardList($uid, $version, $result['buy_num'], $now);
            $return['last_day'] = self::lastDay($activity, $now);
            return $return;
        } finally {
            // 解锁
            Redis::del(self::LOCK_KEY . $uid);
        }
    }

    /**
     * 购买（在玩家行锁事务里执行）
     *
     * @return array<string, mixed> status；成功时 buy_num 为 uc add_buy_num 返回的全部购买数量，武将礼包另有 info
     */
    private static function buy(int $uid, int $id, int $version, int $day, int $now): array
    {
        // 获取奖励信息
        $reward = ChristmasgiftReward::ROWS[$id] ?? null;
        if ($reward === null) {
            return ['status' => -1];
        }
        $member = DB::table('member')->select('vip', 'gold', 'level')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($member === null || $reward['vip'] > (int)$member['vip']) {
            return ['status' => -8];
        }
        // 获取用户今日是否已经领取过奖励
        if (isset(self::rewardLog($uid, $version, $now)[$id])) {
            return ['status' => -3];
        }
        // 获取该礼包的购买次数，判断是否可购买
        if ($reward['num'] > 0 && $reward['num'] <= UcModel::christmasgiftBuyNum($id, $now)) {
            return ['status' => -4];
        }
        // 购买价格不能为 0
        if ($reward['price'] === 0) {
            return ['status' => -1];
        }
        // 判断元宝是否足够
        if ((int)$member['gold'] < $reward['price']) {
            return ['status' => -5];
        }
        // 添加限售次数（先原子占一份，超卖时退回）
        $buy = UcModel::christmasgiftAddBuyNum($id, $now);
        if ($reward['num'] > 0 && $buy['num'] > $reward['num']) {
            UcModel::christmasgiftReleaseBuyNum($id, $now);
            return ['status' => -4];
        }
        // 扣除元宝
        if (!Member::deductCurrency($uid, gold: $reward['price'])) {
            UcModel::christmasgiftReleaseBuyNum($id, $now);
            return ['status' => -5];
        }
        // 添加奖励领取记录
        DB::table('christmasgift_log')->insert(['version' => $version, 'itemid' => $id, 'day' => $day, 'uid' => $uid, 'dateline' => $now]);

        $return = ['status' => 1, 'buy_num' => $buy['all']];
        // 添加奖励
        for ($i = 1; $i <= 4; $i++) {
            $value = $reward['getvalue' . $i];
            $num = $reward['getnum' . $i];
            switch ($reward['gettype' . $i]) {
                case 1: // 银币
                    Member::addCurrency($uid, silver: $value);
                    break;
                case 2: // 突飞卡
                    FreeLeap::add($uid, $value, $now);
                    break;
                case 3: // 原魂
                    Member::addCurrency($uid, soul: $value);
                    break;
                case 4: // 紫宝石
                    TraderLog::addJade($uid, 3, $value, class: 'christmasgift', method: 'get_reward', now: $now);
                    break;
                case 5: // 黄宝石
                    TraderLog::addJade($uid, 2, $value, class: 'christmasgift', method: 'get_reward', now: $now);
                    break;
                case 6: // 宝石碎片
                    OwnGem::addChip($uid, $value, 'christmasgift', 'get_reward', $now);
                    break;
                case 7: // 坐骑
                    OwnMount::addMount($uid, $value, 0, $num, 'christmasgift', 'get_reward', $now);
                    break;
                case 8: // 声望
                    Member::addReputation($uid, $value);
                    break;
                case 9: // 玉石
                    MemberJade::add($uid, $value, 'christmasgift', 'get_reward', $now);
                    break;
                case 10: // 精炼石
                    MemberStone::add($uid, $value, MemberStone::REFINE, 'christmasgift', 'get_reward', $now);
                    break;
                case 11: // 五彩石
                    MemberStone::add($uid, $value, MemberStone::COLOR, 'christmasgift', 'get_reward', $now);
                    break;
                case 12: // 上古血石
                    MemberStone::add($uid, $value, MemberStone::BLOOD, 'christmasgift', 'get_reward', $now);
                    break;
                case 13: // 装备精华
                    Equipment::addTowerItem($uid, $value, $num);
                    break;
                case 14: // 玫瑰花
                    MemberIntegral::change($uid, $value, 'beauty_rose', 1, 'christmasgift', 'get_reward', $now);
                    break;
                case 15: // 钻石
                    MemberIntegral::change($uid, $value, 'beauty_diamond', 1, 'christmasgift', 'get_reward', $now);
                    break;
                case 17: // 武将（礼包表里没有用到；已拥有时按酒馆价格 × 0.8 折算紫宝石）
                    $general = FestReward::grantGeneral($uid, $value, 'christmasgift', 'get_reward', $now);
                    if ($general['owned']) {
                        // 旧版回 get_own_by_id 的结果
                        $return['info'] = ['status' => 1];
                    } elseif ($general['general'] !== null) {
                        $return['info'] = $general['general'];
                    }
                    break;
                case 18: // 星魄
                    $star = OwnStar::get($uid);
                    if ($star === []) {
                        OwnStar::init($uid, $now);
                        $star = OwnStar::get($uid);
                    }
                    OwnStar::update($uid, [$value], [(int)($star['star' . $value] ?? 0) + $num]);
                    break;
            }
        }
        // 记录活动人次
        DB::table('christmasgift_action_log')->insert([
            'uid' => $uid,
            'gold' => $reward['price'],
            'level' => (int)$member['level'],
            'type' => $id,
            'dateline' => $now,
        ]);
        return $return;
    }

    /**
     * 礼包列表（index / get_reward 共用）：每个礼包加今日剩余份数 buytimes、is_buy（恒为 1）、今日是否已买 is_get、
     * VIP 是否够 is_open；坐骑 / 装备精华 / 武将 / 星魄的 getvalue 换成展示信息
     *
     * @param array<string, int> $buyNum uc get_all_buy_num 的结果
     * @return list<array<string, mixed>>
     */
    private static function rewardList(int $uid, int $version, array $buyNum, int $now): array
    {
        $vip = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('vip') ?? 0);
        // 获取用户今日领取过的奖励
        $log = self::rewardLog($uid, $version, $now);
        $list = [];
        foreach (array_values(ChristmasgiftReward::ROWS) as $key => $val) {
            $row = $val;
            $row['buytimes'] = 0;
            $row['is_buy'] = 1;
            if ($val['num'] > 0) {
                $buytimes = $buyNum[UcModel::CHRISTMASGIFT_BUY_TIMES . date('ymd', $now) . '_' . $val['id']] ?? 0;
                if ($val['num'] > $buytimes) {
                    $row['buytimes'] = $val['num'] - $buytimes;
                }
            }
            $row['is_get'] = isset($log[$val['id']]) ? 1 : 0;
            // 根据玩家 vip 来判定是否能购买此礼包（0 显示为灰色，1 红色）
            $row['is_open'] = $val['vip'] > $vip ? 0 : 1;
            for ($i = 1; $i <= 4; $i++) {
                $type = $val['gettype' . $i];
                $value = $val['getvalue' . $i];
                if ($type === 7 && isset(MountTemp::ROWS[$value])) {
                    // 坐骑：完整模板（0 级属性加成）
                    $row['getvalue' . $i] = FestReward::mountInfo($value, false);
                } elseif ($type === 13 && ($ess = FestReward::essenceInfo($value)) !== null) {
                    // 装备精华
                    $row['getvalue' . $i] = ['id' => $ess['id'], 'name' => $ess['name'], 'quality' => $ess['quality'], 'num' => $val['getnum' . $i]];
                } elseif ($type === 17 && ($general = self::generalInfo($uid, $value)) !== null) {
                    // 武将（14 玫瑰花 15 钻石 17 武将 18 星魄为 2014/08/20 新增奖励类型）
                    $row['getvalue' . $i] = $general;
                } elseif ($type === 18) {
                    // 星魄
                    $row['getvalue' . $i] = ['id' => $value, 'num' => $val['getnum' . $i]];
                }
            }
            $list[$key] = $row;
        }
        return $list;
    }

    /**
     * 武将礼包的展示信息：模板（有天赋的武将另加天赋 / 技能的 name、description；已拥有时 own_info 标明折算的紫宝石）
     *
     * @return array<string, mixed>|null
     */
    private static function generalInfo(int $uid, int $gid): ?array
    {
        $info = FestReward::generalInfo($gid);
        if (!isset($info['id'])) {
            return null;
        }
        if (empty($info['talentid'])) {
            unset($info['talent'], $info['skill']);
            return $info;
        }
        // 是否拥有该武将标识
        if (General::ownsTemplate($uid, $gid)) {
            $info['own_info'] = ['is_own' => 1, 'type' => 4];
            $jade = FestReward::generalJade($gid);
            if ($jade !== null) {
                $info['own_info']['value'] = $jade;
            }
        }
        return $info;
    }

    /**
     * 是否是最后一天（1 距结束不足 1 天，2 不足 2 天）
     *
     * @param array<string, int|string> $activity
     */
    private static function lastDay(array $activity, int $now): int
    {
        $left = (int)$activity['endtime'] - $now;
        return $left < 86400 ? 1 : ($left < 86400 * 2 ? 2 : 0);
    }

    private static function nextDay(int $now): int
    {
        return (int)strtotime(date('y-m-d', $now)) + 86400;
    }

    /**
     * 今天领取过的礼包（get_reward_log：dateline 在今天内，按 itemid 索引）
     *
     * @return array<int, true>
     */
    private static function rewardLog(int $uid, int $version, int $now): array
    {
        $start = (int)strtotime(date('y-m-d', $now));
        $return = [];
        foreach (DB::table('christmasgift_log')->select('itemid')->where('version', $version)->where('uid', $uid)
            ->where('dateline', '>=', $start)->where('dateline', '<', $start + 86400)->whereNull('deleted_at')->get() as $val) {
            $return[(int)$val['itemid']] = true;
        }
        return $return;
    }
}
