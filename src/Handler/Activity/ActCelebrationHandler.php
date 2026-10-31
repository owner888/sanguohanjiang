<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\ActCelebrationMember;
use Hanjiang\MasterData\ActCelebrationReward;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FestReward;
use Hanjiang\Model\Member;
use Hanjiang\Model\OwnMount;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 国家庆典（移植旧版 controllers/act_celebration.php + models/actcelebration_model.php，计划任务
 * cron_acttask.php:actcelebration_reward，充值记账 pay.php:pay_act_init 的 add_celebration_pay_order，
 * 退出 / 被踢出国家时 country.php:action_celebration_reward 自动领取身份奖励）
 *
 * 活动时间与版本取 ActivityTime 的 national_celebration（旧版数据 2016-12-31 已结束）。活动期间（充值记账到结束前一天）
 * 每笔充值由 Model\Pay 记一条 act_celebration_order（元宝数）。玩家按个人充值合计获得身份（MasterData ActCelebrationMember：
 * 佃户 ~ 王侯），可领该身份及以下的身份奖励（每档一次，act_celebration_memberreward_log）；本国（member.country）成员充值合计
 * 达到 MasterData ActCelebrationReward 的档次后，活动结束时由计划任务发给本国充值满 300 元宝的成员（国家每档一次
 * act_celebration_reward_log，个人 act_celebration_reward_own_log）。计划任务在 endtime - 86400 ~ endtime + 86400 之间
 * 补发没领的身份奖励与国家奖励并发信件（cronReward）。
 * 客户端 nationcelpop.js 调用 index、get_my_reward（reward_id）。当前时间取 BossClock。
 *
 * 奖励类型同七夕（FestReward），另有 27 玫瑰 28 钻石。
 * 照旧版：领奖时元宝（类型 1）没有 break，会同时再加同样数量的银币（身份奖励里没有元宝，国家奖励里有，但国家奖励由计划任务发，
 * 计划任务的发奖函数有 break）；get_my_reward 不看活动是否开启的判断结果（未开启时版本为 0，查不到充值回 5）；
 * 本国成员列表按充值降序（同额保持原顺序）。
 * 与旧版的差别：领取锁改为 Redis SET NX（旧版 get + setex 30 秒）；身份奖励领取记录加唯一键，写入成功才发奖；
 * 计划任务先占国家档次记录再发奖（旧版先发后记，重复执行可重复发）。
 */
final class ActCelebrationHandler
{
    public const string ACTIVITY = 'national_celebration';

    /** 领取个人充值奖励 lock_key（_member_re_key） */
    private const string MEMBER_RE_KEY = 'celebration_member_re_';
    private const int LOCK_TTL = 30;

    /** 领取国家奖励需要的个人充值（$_min_gold） */
    private const int MIN_GOLD = 300;

    /** 首页展示的神兽坐骑（type 26，坐骑 id 104） */
    private const int MOUNT_ID = 104;

    /** actcelebration_lang.php + cron_lang.php */
    private const array LANG = [
        'celebration_error_open' => '活动未开启',
        'celebration_error_reward' => '奖励表错误',
        'celebration_error_memberreward' => '身份奖励错误',
        'celebration_country_error' => '您没有国家，无法参加该活动。',
        'celebration_error_memberrewarded' => '已经领取个人充值奖励',
        'celebration_error_param' => '传递参数错误',
        'celebration_gold_error' => '充值金额不足, 不能领取该档次的奖励',
        'celebration_error_fast' => '请求不要太频繁',
        'celebration_error_memberrewardlog' => '记录领取个人充值奖励日志错误',
        'cron_celebration_end' => '亲爱的玩家！国家庆典活动已结束，您未领取的个人身份奖励系统已自动帮您领取，',
        'cron_celebration_country_end' => '亲爱的玩家！国家庆典活动已结束，您已达成的国家奖励已经由系统自动发放，',
        'country_kick_message' => '亲爱的玩家，由于您已经退出国家，您在国家庆典活动中还未领取的个人身份奖励系统已自动帮您领取，',
        'message_system_title' => '系统通知',
    ];

    /**
     * 首页: c=act_celebration&m=index
     *
     * 1 成功；-1 活动未开启；-2 奖励表错误；-3 身份奖励表错误；-4 未加入国家
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::init($now);
        $judge = self::preJudge($act);
        if ($judge !== null || $uid <= 0) {
            return $judge ?? ['status' => -1, 'msg' => self::LANG['celebration_error_open']];
        }
        $version = $act['version'];
        $return = ['status' => 0];
        $countryId = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('country') ?? 0);
        if ($countryId <= 0) {
            return ['status' => -4, 'msg' => self::LANG['celebration_country_error']];
        }
        // 当前本国充值元宝
        $return['all_gold'] = 0;
        $allMember = array_values(ActCelebrationMember::ROWS);
        $memberreList = self::memberrewardList();
        // 国家成员充值信息
        $countryList = [];
        $payData = self::payData($version);
        $countries = self::countries(array_column($payData, 'uid'));
        foreach ($payData as $val) {
            if (($countries[$val['uid']] ?? 0) === $countryId) {
                $return['all_gold'] += $val['all_gold'];
                $countryList[] = $val;
            }
        }
        $returnCountryList = [];
        foreach ($countryList as $key => $val) {
            foreach ($memberreList as $value) {
                $returnCountryList[$key]['uid'] = $val['uid'];
                $returnCountryList[$key]['nickname'] = $val['nickname'];
                $returnCountryList[$key]['gold'] = $val['all_gold'];
                if ($val['all_gold'] >= $value['gold']) {
                    $returnCountryList[$key]['name'] = $value['name'];
                    $returnCountryList[$key]['quality'] = $value['id'] - 1;
                }
                if ($val['all_gold'] < $memberreList[0]['gold']) {
                    $returnCountryList[$key]['name'] = $allMember[0]['name'];
                    $returnCountryList[$key]['quality'] = 0;
                }
            }
        }
        // 按充值降序（_array_sort arsort，同额保持原顺序）
        $gold = array_column($returnCountryList, 'gold');
        arsort($gold);
        $return['country_list'] = array_values(array_map(static fn (int $k): array => $returnCountryList[$k], array_keys($gold)));

        // 获取当前玩家充值元宝总数
        $myGold = self::payData($version, $uid)[0]['all_gold'] ?? 0;
        // 处理国家充值奖励列表（is_re：0 未达成，1 已达成，2 已经领取）
        $return['list'] = [];
        foreach (ActCelebrationReward::ROWS as $val) {
            $val['is_re'] = 0;
            if ($return['all_gold'] >= $val['gold'] && $myGold >= self::MIN_GOLD) {
                $val['is_re'] = 1;
            }
            // 根据国家 id 判断是否已经领取奖励
            if (DB::table('act_celebration_reward_log')->where('version', $version)->where('country_id', $countryId)
                ->where('reward_id', $val['id'])->whereNull('deleted_at')->exists()) {
                $val['is_re'] = 2;
            }
            for ($i = 1; $i <= 5; $i++) {
                $val = self::showItem($val, $i);
            }
            $return['list'][] = $val;
        }
        // 处理个人充值奖励列表
        foreach ($memberreList as $val) {
            $val['is_re'] = 0;
            if ($myGold >= $val['gold'] && $myGold > 0) {
                $val['is_re'] = 1;
            }
            if (self::memberrewardLog($uid, $version, $val['id'])) {
                $val['is_re'] = 2;
            }
            for ($i = 1; $i <= 3; $i++) {
                $val = self::showItem($val, $i);
            }
            $return['member_list'][] = $val;
        }
        $return['status'] = 1;
        // 活动剩余时间
        $return['cd_time'] = $act['cd'];
        // 神兽信息
        $return['mount'] = FestReward::mountInfo(self::MOUNT_ID, true);
        $return['my_gold'] = $myGold;
        $name = $allMember[0]['name'];
        $quality = 0;
        foreach ($memberreList as $value) {
            if ($myGold >= $value['gold']) {
                $name = $value['name'];
                $quality = $value['id'] - 1;
            }
        }
        $return['name'] = $name;
        $return['quality'] = $quality;
        return $return;
    }

    /**
     * 领取个人身份奖励: c=act_celebration&m=get_my_reward（reward_id 身份 id，2 ~ 8）
     *
     * 1 成功；2 请求频繁（锁）；3 参数错误；4 已经领取；5 充值金额不足；6 记录领取日志错误；-3 身份奖励错误；-4 未加入国家
     */
    public static function get_my_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        // 防刷接口处理
        if ($uid <= 0 || !(bool)Redis::instance()->set(self::MEMBER_RE_KEY . $uid, 1, ['nx', 'ex' => self::LOCK_TTL])) {
            return ['status' => 2, 'msg' => self::LANG['celebration_error_fast']];
        }
        try {
            // 照旧版：条件判断的结果不用（未开启时版本为 0）
            $version = self::init($now)['version'] ?? 0;
            // 奖励 id
            $rewardId = (int)$ctx->input('reward_id', 0);
            if ($rewardId <= 1) {
                return ['status' => 3, 'msg' => self::LANG['celebration_error_param']];
            }
            // 判断是否加入国家
            if ((int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('country') ?? 0) <= 0) {
                return ['status' => -4, 'msg' => self::LANG['celebration_country_error']];
            }
            // 获取当前玩家的领取的个人奖励的日志信息
            if (self::memberrewardLog($uid, $version, $rewardId)) {
                return ['status' => 4, 'msg' => self::LANG['celebration_error_memberrewarded']];
            }
            // 获取当前玩家充值元宝总数
            $myGold = self::payData($version, $uid)[0]['all_gold'] ?? 0;
            $info = ActCelebrationMember::ROWS[$rewardId] ?? null;
            if ($info === null) {
                return ['status' => -3, 'msg' => self::LANG['celebration_error_memberreward']];
            }
            if ($myGold <= 0 || $myGold < $info['gold']) {
                return ['status' => 5, 'msg' => self::LANG['celebration_gold_error']];
            }
            // 记录领取个人充值奖励日志（唯一键，写入成功才发奖）
            if (!self::addMemberrewardLog($uid, $version, $rewardId, $now)) {
                return ['status' => 6, 'msg' => self::LANG['celebration_error_memberrewardlog']];
            }
            $rewardInfo = [];
            for ($i = 1; $i <= 3; $i++) {
                $type = (int)$info['type' . $i];
                if ($type <= 0) {
                    continue;
                }
                $re = self::grant($uid, ['type' => $type, 'num' => (int)$info['num' . $i], 'value' => (int)$info['value' . $i]], $now);
                $rewardInfo['type' . $i] = $type;
                if (FestReward::isItemType($type)) {
                    $rewardInfo['info' . $i] = $re['info'] ?? null;
                    $rewardInfo['num' . $i] = $info['value' . $i];
                    $rewardInfo['value' . $i] = $info['num' . $i];
                } else {
                    $rewardInfo['num' . $i] = $info['num' . $i];
                    $rewardInfo['value' . $i] = $info['value' . $i];
                }
            }
            $member = DB::table('member')->select('gold', 'silver')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
            return [
                'status' => 1,
                'all_gold' => (int)($member['gold'] ?? 0),
                'all_silver' => (int)($member['silver'] ?? 0),
                'reward_info' => $rewardInfo,
            ];
        } finally {
            Redis::del(self::MEMBER_RE_KEY . $uid);
        }
    }

    /**
     * 充值记账（pay.php:pay_act_init → add_celebration_pay_order）：活动开启且距结束超过一天（图标多挂一天）时记一笔
     */
    public static function addPayOrder(int $uid, string $nickname, int $level, int $gold, string $orderId, string $class, string $method, int $now): void
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null || (int)$info['endtime'] - 86400 <= $now) {
            return;
        }
        DB::table('act_celebration_order')->insert([
            'uid' => $uid,
            'nickname' => Member::logText($nickname, 50),
            'level' => $level,
            'gold' => $gold,
            'version' => (int)$info['version'],
            'orderid' => $orderId,
            'class' => Member::logText($class, 20),
            'method' => Member::logText($method, 20),
            'dateline' => $now,
        ]);
    }

    /**
     * 退出 / 被踢出国家时自动领取未领的身份奖励（country.php:action_celebration_reward，betray 与 kick_out 调用）
     *
     * 活动开启且距结束超过一天时，按个人充值合计把达到但没领的身份奖励写领取记录、发奖并发信件（每个身份一封）。
     * 照旧版：奖励的 num 为 0 时沿用上一项的 num（$my_reward 跨循环复用；身份奖励表里没有 num 为 0 的项）。
     */
    public static function leaveCountryReward(int $uid, int $now): void
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null || (int)$info['endtime'] - 86400 <= $now) {
            return;
        }
        $version = (int)$info['version'];
        // 获取玩家充值元宝
        $myGold = self::payData($version, $uid)[0]['all_gold'] ?? 0;
        if ($myGold <= 0) {
            return;
        }
        foreach (self::memberrewardList() as $val) {
            if ($myGold < $val['gold'] || !self::addMemberrewardLog($uid, $version, $val['id'], $now)) {
                continue;
            }
            $msg = self::LANG['country_kick_message'] . FestReward::LANG_CRON['cron_get_reward'];
            $myReward = ['type' => 0, 'num' => 0, 'value' => 0];
            for ($i = 1; $i <= 3; $i++) {
                if ($val['num' . $i] > 0) {
                    $myReward['num'] = $val['num' . $i];
                }
                $myReward['type'] = $val['type' . $i];
                $myReward['value'] = $val['value' . $i];
                $msg .= FestReward::cronGrant($uid, $myReward, true, 'country', 'action_celebration_reward', $now);
            }
            $msg .= FestReward::LANG_CRON['cron_check'];
            MessageHandler::sendSystemMessage([$uid], 1, self::LANG['message_system_title'], $msg);
        }
    }

    /**
     * 计划任务：发放未领的身份奖励与国家充值档次奖励（cron_acttask.php:actcelebration_reward）
     *
     * 执行窗口：endtime - 86400 <= 当前 <= endtime + 86400。
     *
     * @return int -1 无活动；-2 未到发放时间；-3 结束超过一天；1 已执行
     */
    public static function cronReward(int $now): int
    {
        $activity = ActivityClock::byName(self::ACTIVITY);
        if ($activity === null) {
            return -1;
        }
        if ($activity['endtime'] - 86400 > $now) {
            return -2;
        }
        if ($activity['endtime'] + 86400 < $now) {
            return -3;
        }
        $version = (int)$activity['version'];
        self::cronMemberReward($version, $now);
        self::cronCountryReward($version, $now);
        return 1;
    }

    /**
     * 发放个人充值（身份）奖励（action_celebration_reward）：有国家的玩家，同类资源合并到一封信件
     * 照旧版：装备 / 武将 / 坐骑 / 精华合并时 num（id）相加、数量按 1 发（身份奖励里只有王侯的一匹坐骑）。
     */
    private static function cronMemberReward(int $version, int $now): void
    {
        $rewardList = self::memberrewardList();
        if ($rewardList === []) {
            return;
        }
        $payData = self::payData($version);
        $countries = self::countries(array_column($payData, 'uid'));
        $reward = [];
        foreach ($payData as $user) {
            if (($countries[$user['uid']] ?? 0) <= 0) {
                continue;
            }
            foreach ($rewardList as $list) {
                // 充值元宝达到可领取身份奖励并且没领过奖（写入领取记录成功）
                if ($user['all_gold'] < $list['gold'] || !self::addMemberrewardLog($user['uid'], $version, $list['id'], $now)) {
                    continue;
                }
                for ($i = 1; $i <= 3; $i++) {
                    $type = (int)$list['type' . $i];
                    if (isset($reward[$user['uid']][$type])) {
                        $reward[$user['uid']][$type] += $list['num' . $i];
                    } elseif ($type > 0) {
                        $reward[$user['uid']][$type] = $list['num' . $i];
                    }
                }
            }
        }
        self::sendCronReward($reward, self::LANG['cron_celebration_end'], $now);
    }

    /**
     * 发放国家充值档次奖励（action_celebration_country_reward）：国家合计达到的档次发给本国充值满 300 元宝的成员
     */
    private static function cronCountryReward(int $version, int $now): void
    {
        if (ActCelebrationReward::ROWS === []) {
            return;
        }
        // 组织可发奖的国家
        $payData = self::payData($version);
        $countries = self::countries(array_column($payData, 'uid'));
        $allCountry = [];
        foreach ($payData as $val) {
            $countryId = $countries[$val['uid']] ?? 0;
            if ($countryId <= 0) {
                continue;
            }
            $allCountry[$countryId] ??= ['country_id' => $countryId, 'all_gold' => 0, 'uid' => []];
            $allCountry[$countryId]['all_gold'] += $val['all_gold'];
            // 充值元宝总数大于 300 方可领取到奖励
            if ($val['all_gold'] >= self::MIN_GOLD) {
                $allCountry[$countryId]['uid'][] = $val['uid'];
            }
        }
        $reward = [];
        foreach ($allCountry as $val) {
            foreach (ActCelebrationReward::ROWS as $list) {
                if ($val['all_gold'] < $list['gold']) {
                    continue;
                }
                // 国家领取记录（唯一键，已发过的档次写不进）
                $claimed = DB::execute(
                    'INSERT IGNORE INTO `zc_sanguo_act_celebration_reward_log` (`version`, `country_id`, `reward_id`, `dateline`) VALUES (:version, :country, :reward, :now)',
                    ['version' => $version, 'country' => $val['country_id'], 'reward' => $list['id'], 'now' => $now]
                );
                if ($claimed <= 0) {
                    continue;
                }
                foreach ($val['uid'] as $user) {
                    for ($i = 1; $i <= 5; $i++) {
                        $type = (int)$list['type' . $i];
                        if (isset($reward[$user][$type])) {
                            $reward[$user][$type] += $list['num' . $i];
                        } elseif ($type > 0) {
                            $reward[$user][$type] = $list['num' . $i];
                        }
                    }
                    // 添加个人领取日志
                    DB::execute(
                        'INSERT IGNORE INTO `zc_sanguo_act_celebration_reward_own_log` (`version`, `country_id`, `reward_id`, `uid`, `dateline`)
                         VALUES (:version, :country, :reward, :uid, :now)',
                        ['version' => $version, 'country' => $val['country_id'], 'reward' => $list['id'], 'uid' => $user, 'now' => $now]
                    );
                }
            }
        }
        self::sendCronReward($reward, self::LANG['cron_celebration_country_end'], $now);
    }

    /**
     * 计划任务发奖并发信件（每个玩家一封；装备 / 武将 / 坐骑 / 精华的数量按 1）
     *
     * @param array<int, array<int, int>> $reward uid => [类型 => 数量 / id]
     */
    private static function sendCronReward(array $reward, string $title, int $now): void
    {
        foreach ($reward as $uid => $val) {
            $msg = $title . FestReward::LANG_CRON['cron_get_reward'];
            foreach ($val as $type => $num) {
                $myReward = ['type' => $type, 'num' => $num];
                if (FestReward::isItemType($type)) {
                    $myReward['value'] = 1;
                }
                $msg .= FestReward::cronGrant($uid, $myReward, true, 'cron_acttask', 'actcelebration_reward', $now);
            }
            $msg .= FestReward::LANG_CRON['cron_check'];
            MessageHandler::sendSystemMessage([$uid], 1, self::LANG['message_system_title'], $msg);
        }
    }

    /**
     * 向玩家发奖（get_reward，$is_get = 0）；照旧版元宝（1）没有 break，同时加同样数量的银币
     *
     * @param array{type: int, num: int, value: int} $info
     * @return array<string, mixed>
     */
    private static function grant(int $uid, array $info, int $now): array
    {
        $type = $info['type'];
        $num = $info['num'];
        $id = 0;
        if (FestReward::isItemType($type)) {
            $id = $info['num'];
            $num = $info['value'];
        }
        switch ($type) {
            case 1: // 元宝（旧版没有 break，继续执行银币分支）
                Member::addCurrency($uid, gold: $num, silver: $num);
                return [];
            case 8: // 装备
                $item = FestReward::equipInfo($id);
                if ($item === null) {
                    return [];
                }
                Equipment::addFromTemplate($uid, $id);
                return ['info' => $item];
            case 12: // 武将（奖励表里没有用到）
                $general = FestReward::grantGeneral($uid, $id, 'act_celebration', 'get_my_reward', $now);
                return ['info' => $general['general'] ?? []];
            case 13: // 军令（add_act(1, value)）
                Member::addCurrency($uid, act: $info['value']);
                return [];
            case 17: case 18: case 19: case 20: case 21: case 22: case 23: case 24: case 25: // 装备精华
                $ess = FestReward::essenceInfo($id);
                if ($ess !== null) {
                    Equipment::addTowerItem($uid, $id, $num);
                }
                return ['info' => ($ess ?? []) + ['num' => $num]];
            case 26: // 坐骑
                OwnMount::addMount($uid, $id, 0, $num, 'act_celebration', 'get_my_reward', $now);
                return ['info' => FestReward::mountInfo($id, true)];
        }
        FestReward::grantBasic($uid, $type, $num, 'act_celebration', 'get_my_reward', $now);
        return [];
    }

    /**
     * 列表里的装备 / 武将 / 精华 / 坐骑：加 info{$i} 并交换 num / value
     *
     * @param array<string, mixed> $val
     * @return array<string, mixed>
     */
    private static function showItem(array $val, int $i): array
    {
        $type = (int)($val['type' . $i] ?? 0);
        if ($type <= 0 || !FestReward::isItemType($type)) {
            return $val;
        }
        $id = (int)$val['num' . $i];
        $num = (int)$val['value' . $i];
        $val['info' . $i] = match (true) {
            $type === 8 => FestReward::equipInfo($id),
            $type === 12 => FestReward::generalInfo($id),
            $type === 26 => FestReward::mountInfo($id, true),
            default => (FestReward::essenceInfo($id) ?? []) + ['num' => $num],
        };
        $val['num' . $i] = $num;
        $val['value' . $i] = $id;
        return $val;
    }

    /**
     * 活动时间（_init）：activity_status 开启时返回版本、cd（「endtime - 86401」- 当前，最小 0）与是否开启（starttime <= 当前 < endtime）
     *
     * @return array{version: int, cd: int, open: bool}|null
     */
    private static function init(int $now): ?array
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null) {
            return null;
        }
        $end = (int)$info['endtime'] - 86401;
        return [
            'version' => (int)$info['version'],
            'cd' => max(0, $end - $now),
            'open' => $now >= (int)$info['starttime'] && $now < (int)$info['endtime'],
        ];
    }

    /**
     * 条件判断（pre_judge）：活动未开启 -1、奖励表错误 -2、身份奖励错误 -3；通过返回 null
     *
     * @param array{version: int, cd: int, open: bool}|null $act
     * @return array<string, mixed>|null
     */
    private static function preJudge(?array $act): ?array
    {
        if ($act === null || !$act['open']) {
            return ['status' => -1, 'msg' => self::LANG['celebration_error_open']];
        }
        if (ActCelebrationReward::ROWS === []) {
            return ['status' => -2, 'msg' => self::LANG['celebration_error_reward']];
        }
        if (self::memberrewardList() === []) {
            return ['status' => -3, 'msg' => self::LANG['celebration_error_memberreward']];
        }
        return null;
    }

    /**
     * 身份奖励（get_memberreward_list：gold > 0 的身份）
     *
     * @return list<array<string, mixed>>
     */
    private static function memberrewardList(): array
    {
        return array_values(array_filter(ActCelebrationMember::ROWS, static fn (array $r): bool => $r['gold'] > 0));
    }

    /**
     * 玩家充值合计（get_pay_data：按玩家分组；$uid 为 0 时全部玩家）
     *
     * @return list<array{uid: int, nickname: string, all_gold: int}>
     */
    private static function payData(int $version, int $uid = 0): array
    {
        $sql = 'SELECT `uid`, MAX(`nickname`) AS `nickname`, SUM(`gold`) AS `all_gold` FROM `zc_sanguo_act_celebration_order`
                WHERE `version` = :version AND `deleted_at` IS NULL';
        $bind = ['version' => $version];
        if ($uid > 0) {
            $sql .= ' AND `uid` = :uid';
            $bind['uid'] = $uid;
        }
        $rows = DB::query($sql . ' GROUP BY `uid` ORDER BY MIN(`id`)', $bind);
        return array_map(static fn (array $r): array => [
            'uid' => (int)$r['uid'],
            'nickname' => (string)$r['nickname'],
            'all_gold' => (int)$r['all_gold'],
        ], $rows);
    }

    /**
     * 玩家所属国家（member.country；get_base_info / country_model->get_member_by_uid）
     *
     * @param list<int> $uids
     * @return array<int, int>
     */
    private static function countries(array $uids): array
    {
        if ($uids === []) {
            return [];
        }
        $return = [];
        foreach (DB::table('member')->select('uid', 'country')->whereIn('uid', $uids)->whereNull('deleted_at')->get() as $row) {
            $return[(int)$row['uid']] = (int)$row['country'];
        }
        return $return;
    }

    private static function memberrewardLog(int $uid, int $version, int $rewardId): bool
    {
        return DB::table('act_celebration_memberreward_log')->where('version', $version)->where('member_id', $rewardId)->where('uid', $uid)
            ->whereNull('deleted_at')->exists();
    }

    private static function addMemberrewardLog(int $uid, int $version, int $rewardId, int $now): bool
    {
        return DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_act_celebration_memberreward_log` (`uid`, `version`, `member_id`, `dateline`) VALUES (:uid, :version, :id, :now)',
            ['uid' => $uid, 'version' => $version, 'id' => $rewardId, 'now' => $now]
        ) > 0;
    }
}
