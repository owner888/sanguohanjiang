<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\ChingmingAchievement;
use Hanjiang\MasterData\ChingmingRankReward;
use Hanjiang\MasterData\ChingmingReward;
use Hanjiang\MasterData\NewsacrificesAchievement;
use Hanjiang\MasterData\NewsacrificesRankReward;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FestReward;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\TraderLog;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 清明节祭祖 / 春节祭拜仙神（移植旧版 controllers/chingming.php + models/chingming_model.php，计划任务
 * cron.php:get_chingming_reward「手动发放清明节排行奖励」与 get_chingming_achievement_reward「自动领取祭祖的成就奖励」，
 * 主城 member.php index 的 VIP 同步）
 *
 * 活动时间、版本与类型取 ActivityTime 的 chingming（旧版数据 2016-12-31 已结束；type 1 为春节版「祭拜仙神」，成就 / 排行奖励
 * 取 newsacrifices_* 表）。每次祭祖花 30 元宝（百次祭祖 3000，距下次盛大祭祖正好 100 次时才能用），按 MasterData
 * ChingmingReward 的权重随机发奖（1 黄宝石 2 紫宝石 3 玉石 4 原魂 5 突飞卡 6 银币 × 主公等级 7 军令）；每满 100 次下一次
 * 盛大祭祖免费、奖励 10 倍、次数不加。累计次数达到成就（ChingmingAchievement / NewsacrificesAchievement）可领成就奖励
 * （8 为整件红装）。VIP 2 以上、次数大于 0 的玩家按次数排行前 30，「endtime - 86400」之后可领排行奖励（普通版原魂，
 * 春节版 1 紫宝石 / 2 黄宝石）。活动结束后 10 小时内计划任务补发没领的排行奖励与成就奖励（cronRankReward / cronAchievementReward）。
 * 客户端 qingmingpop.js 调用 index、get_achievement_list、get_achievement_reward（id）、action（type）、get_rank_list、
 * get_rank_reward。当前时间取 BossClock。
 *
 * 照旧版：各接口回 vip（玩家 VIP 不足 2 时为 2，否则 0；get_achievement_list 初始就是 2）；进入任一接口（含主城）都会建档；
 * get_achievement_list 的红装把 value 换成名字。
 * 与旧版的差别：祭祖在玩家行锁事务里执行（扣元宝、发奖、加次数一起提交）；成就领取记录加唯一键、排行领奖用条件更新
 * （get_rank = 0），写入成功才发奖（旧版先查后写，并发可重复领）；计划任务合并成就奖励时多件红装逐件发放（旧版把装备 id
 * 相加后按一个 id 发，会发错装备）。
 */
final class ChingmingHandler
{
    public const string ACTIVITY = 'chingming';

    /** 祭祖单价（$_price） */
    private const int PRICE = 30;
    /** 排行与界面提示的 VIP 限制（$_vip / $_rank_vip） */
    private const int VIP = 2;
    /** 排行个数（$_limit） */
    private const int RANK_LIMIT = 30;

    private const string LANG_MESSAGE_SYSTEM_TITLE = '系统通知';

    /**
     * 进入活动: c=chingming&m=index
     *
     * 1 成功；-1 活动未开启；-2 获取用户信息失败
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $member = self::member($uid);
        $return = ['status' => 0, 'vip' => self::vipTip($member)];
        $act = self::init($now);
        // 判断活动是否开启
        if ($act === null || $member === null) {
            $return['status'] = -1;
            return $return;
        }
        // 获取用户信息
        $info = self::memberInfo($member, $act['version'], $now);
        $return['status'] = 1;
        $return['times'] = $info['times'];
        $return['next_times'] = ($info['free_times'] + 1) * 100 - $info['times'];
        $return['price'] = self::PRICE;
        $return['hundred'] = self::PRICE * 100;
        $return['cd'] = $act['cd'];
        $return['type'] = $act['type'];
        return $return;
    }

    /**
     * 获取成就奖励列表: c=chingming&m=get_achievement_list
     *
     * 1 成功；-1 活动未开启；-2 获取用户信息失败；-3 成就奖励为空。open：0 不可领取，1 可领取，2 已领取
     */
    public static function get_achievement_list(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $member = self::member($uid);
        // 照旧版：初始 vip 为 2
        $return = ['status' => 0, 'vip' => self::VIP];
        $act = self::init($now);
        if ($act === null || $member === null) {
            $return['status'] = -1;
            return $return;
        }
        $info = self::memberInfo($member, $act['version'], $now);
        // 获取宝箱奖励领取记录
        $rewardLog = self::rewardLog($uid, $act['version']);
        $list = [];
        foreach (self::achievementList($act['type']) as $val) {
            for ($i = 1; $i <= 3; $i++) {
                if ($val['type' . $i] === 8) {
                    // 查询道具信息
                    $item = FestReward::equipInfo($val['value' . $i]);
                    $val['value' . $i] = $item['name'] ?? null;
                    $val['value' . $i . '_quality'] = $item['quality'] ?? null;
                }
            }
            $val['open'] = isset($rewardLog[$val['id']]) ? 2 : ($val['times'] <= $info['times'] ? 1 : 0);
            $list[] = $val;
        }
        $return['status'] = 1;
        $return['list'] = $list;
        $return['type'] = $act['type'];
        return $return;
    }

    /**
     * 领取成就奖励: c=chingming&m=get_achievement_reward（id 成就 id）
     *
     * 1 成功；-1 活动未开启；-2 获取用户信息失败；-3 参数错误 / 成就不存在；-4 次数未达到；-5 已领取；-6 添加领奖记录失败
     */
    public static function get_achievement_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $member = self::member($uid);
        $return = ['status' => 0, 'vip' => self::vipTip($member)];
        $id = (int)$ctx->input('id', 0);
        if ($id === 0) {
            $return['status'] = -3;
            return $return;
        }
        $act = self::init($now);
        if ($act === null || $member === null) {
            $return['status'] = -1;
            return $return;
        }
        $version = $act['version'];
        $info = self::memberInfo($member, $version, $now);
        // 查询成就奖励信息
        $reward = self::achievementList($act['type'])[$id] ?? null;
        if ($reward === null) {
            $return['status'] = -3;
            return $return;
        }
        // 判断自己是否可以领取该奖励
        if ($info['times'] < $reward['times']) {
            $return['status'] = -4;
            return $return;
        }
        // 获取自己的奖励领取记录
        if (isset(self::rewardLog($uid, $version)[$id])) {
            $return['status'] = -5;
            return $return;
        }
        // 添加领奖记录（唯一键，写入成功才发奖）
        if (!self::addRewardLog($uid, $version, $id, $now)) {
            $return['status'] = -6;
            return $return;
        }
        // 添加奖励（1 黄宝石，2 紫宝石，3 玉石，4 原魂，5 突飞卡，6 银币，7 军令，8 红装）
        for ($i = 1; $i <= 3; $i++) {
            $value = $reward['value' . $i];
            if ($reward['type' . $i] === 8) {
                // 红装-整件
                $item = FestReward::equipInfo($value);
                if ($item !== null) {
                    $reward['value' . $i] = ['itemid' => $item['id'], 'quality' => $item['quality'], 'itemname' => $item['name']];
                    Equipment::addFromTemplate($uid, $value);
                }
            } else {
                // 银币为宝箱奖励中的银币，不是系数
                self::grant($uid, $reward['type' . $i], $value, 'get_achievement_reward', $now);
            }
        }
        $after = self::balances($uid);
        $return['status'] = 1;
        $return['reward'] = $reward;
        $return['all_gold'] = $after['gold'];
        $return['all_silver'] = $after['silver'];
        $return['all_act'] = $after['act'];
        $return['type'] = $act['type'];
        return $return;
    }

    /**
     * 祭祖: c=chingming&m=action（type 1 百次祭祖，0 单次）
     *
     * 1 成功；-1 活动未开启；-2 活动已结束（只能领排行）；-3 获取用户信息失败；-4 距下次盛大祭祖不足 100 次不能百次祭祖；
     * -5 元宝不足；-6 奖励表为空；-7 扣元宝失败
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $member = self::member($uid);
        $return = ['status' => 0, 'vip' => self::vipTip($member)];
        // 祭祖类型（1 百次祭祖，0 单次）
        $type = (int)$ctx->input('type', 0);
        $num = $type !== 0 ? 100 : 1;
        $act = self::init($now);
        if ($act === null || $member === null) {
            $return['status'] = -1;
            return $return;
        }
        // 判断活动是否结束
        if ($act['cd'] <= 0) {
            $return['status'] = -2;
            return $return;
        }
        $result = Member::locked($uid, static fn (): array => self::doAction($member, $act, $type, $num, $now));
        return $result + $return;
    }

    /**
     * 祭祖（在玩家行锁事务里执行）
     *
     * @param array{uid: int, nickname: string, vip: int, level: int} $member
     * @param array{version: int, cd: int, type: int, end: int} $act
     * @return array<string, mixed>
     */
    private static function doAction(array $member, array $act, int $type, int $num, int $now): array
    {
        $uid = $member['uid'];
        $version = $act['version'];
        $info = self::memberInfo($member, $version, $now);
        // 百次祭祖（如果距下次盛大祭祖次数不足一百次，就无法使用该功能）
        $times = ($info['free_times'] + 1) * 100 - $info['times'];
        if ($type === 1 && $times !== 100) {
            return ['status' => -4];
        }
        // 如果之前次数已满 100 次，本次免费，奖励获得 10 倍，次数不加
        $free = $times === 0 ? 1 : 0;
        $gold = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold') ?? 0);
        if (!$free && $gold < self::PRICE * $num) {
            return ['status' => -5];
        }
        // 得到常规奖励数据
        $itemReward = array_values(ChingmingReward::ROWS);
        if ($itemReward === []) {
            return ['status' => -6];
        }
        // 得到概率总数与最小值（旧版 rate 为 decimal，mt_rand 按整数取）
        $rates = array_map(static fn (array $v): int => (int)$v['rate'], $itemReward);
        $total = array_sum($rates);
        $min = min($rates);
        // 按次数随机得到本次的奖励，同类型的奖励参数加起来
        $addReward = [];
        for ($i = 1; $i <= $num; $i++) {
            $randNum = mt_rand($min, $total);
            $nowRate = 0;
            foreach ($itemReward as $key => $val) {
                $nowRate += $rates[$key];
                if ($randNum <= $nowRate) {
                    if (isset($addReward[$val['type']])) {
                        $addReward[$val['type']]['value'] += $val['value'];
                    } else {
                        $addReward[$val['type']] = ['type' => $val['type'], 'value' => $val['value']];
                    }
                    break;
                }
            }
        }
        if ($free === 0) {
            // 扣除元宝（百次祭祖的日常任务计数类型为 100）
            if (!Member::deductCurrency($uid, gold: self::PRICE * $num, goldType: $type === 1 ? 100 : 1)) {
                return ['status' => -7];
            }
        } else {
            // 盛大祭祀（普通版 / 春节版）奖励 × 10 倍
            foreach ($addReward as $key => $val) {
                $addReward[$key]['value'] = $val['value'] * 10;
            }
        }
        // 添加奖励（1 黄宝石，2 紫宝石，3 玉石，4 原魂，5 突飞卡，6 银币 × 主公等级，7 军令）
        foreach ($addReward as $key => $val) {
            if ($val['type'] === 6) {
                $addReward[$key]['value'] = $val['value'] * $member['level'];
            }
            self::grant($uid, $val['type'], $addReward[$key]['value'], 'action', $now);
        }
        // 添加次数
        if ($free === 0) {
            $info['times'] += $num;
            DB::table('chingming_log')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')
                ->update(['dateline' => $now, 'times' => $info['times']]);
        } else {
            $info['free_times']++;
            DB::table('chingming_log')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')
                ->update(['dateline' => $now, 'free_times' => $info['free_times']]);
        }
        // 记录活动人次
        DB::table('chingming_action_log')->insert([
            'uid' => $uid,
            'gold' => $free === 0 ? self::PRICE * $num : 0,
            'level' => $member['level'],
            'dateline' => $now,
        ]);
        $after = self::balances($uid);
        return [
            'status' => 1,
            'num' => $num,
            'reward' => array_values($addReward),
            'free' => $free,
            'times' => $info['times'],
            'next_times' => ($info['free_times'] + 1) * 100 - $info['times'],
            'all_gold' => $after['gold'],
            'all_silver' => $after['silver'],
            'all_act' => $after['act'],
            'type' => $act['type'],
        ];
    }

    /**
     * 查看排行: c=chingming&m=get_rank_list
     *
     * 1 成功；-1 活动未开启。isget：0 没有排行奖励，1 可领取，2 已领取
     */
    public static function get_rank_list(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $member = self::member($uid);
        $return = ['status' => 0, 'vip' => self::vipTip($member)];
        $act = self::init($now);
        if ($act === null || $member === null) {
            $return['status'] = -1;
            return $return;
        }
        $rankReward = self::rankRewardList($act['type']);
        $myRank = 0;
        $myInfo = [];
        $list = [];
        foreach (self::rankList($act['version']) as $key => $val) {
            $rank = $key + 1;
            if ($val['uid'] === $uid) {
                $myRank = $rank;
                $myInfo = $val;
            }
            $row = ['uid' => $val['uid'], 'nickname' => $val['nickname'], 'vip' => $val['vip'], 'times' => $val['times'], 'rank' => $rank];
            // 在排行里面，取排行奖励（普通版类型恒为 5 原魂）
            $row['type'] = 5;
            if (isset($rankReward[$rank])) {
                if ($act['type']) {
                    $row['type'] = $rankReward[$rank]['type'];
                }
                $row['value'] = $rankReward[$rank]['value'];
            } else {
                $row['value'] = 0;
            }
            $list[] = $row;
        }
        // 返回排行奖励状态（0 没有排行奖励，1 可领取，2 已领取）
        $isget = 0;
        if ($act['cd'] <= 0 && $myRank > 0) {
            $isget = $myInfo['get_rank'] > 0 ? 2 : 1;
        }
        $return['status'] = 1;
        $return['list'] = $list;
        $return['isget'] = $isget;
        $return['type'] = $act['type'];
        return $return;
    }

    /**
     * 领取排行奖励: c=chingming&m=get_rank_reward
     *
     * 1 成功；-1 活动未开启；-2 未到领奖时间；-3 获取用户信息失败；-4 不在排行榜；-5 已领取；-6 没有对应的排名奖励；-7 修改领奖状态失败
     */
    public static function get_rank_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $member = self::member($uid);
        $return = ['status' => 0, 'vip' => self::vipTip($member)];
        $act = self::init($now);
        if ($act === null || $member === null) {
            $return['status'] = -1;
            return $return;
        }
        // 判断是否到领奖的时候了
        if ($act['end'] - $now > 0) {
            $return['status'] = -2;
            return $return;
        }
        $version = $act['version'];
        $info = self::memberInfo($member, $version, $now);
        // 获取自己的排名
        $myRank = 0;
        foreach (self::rankList($version) as $key => $val) {
            if ($val['uid'] === $uid) {
                $myRank = $key + 1;
            }
        }
        if ($myRank === 0) {
            $return['status'] = -4;
            return $return;
        }
        // 判断是否已领取过排名奖励
        if ($info['get_rank'] > 0) {
            $return['status'] = -5;
            return $return;
        }
        // 获取对应的排名奖励
        $reward = self::rankRewardList($act['type'])[$myRank] ?? null;
        if ($reward === null) {
            $return['status'] = -6;
            return $return;
        }
        // 修改领奖状态（条件更新，并发只成功一次）
        if (!self::updateRankRewardLog($uid, $version, $myRank)) {
            $return['status'] = -7;
            return $return;
        }
        // 添加奖励（春节版 1 紫宝石 2 黄宝石；普通版原魂）
        if ($act['type']) {
            if ($reward['type'] === 1) {
                TraderLog::addJade($uid, 3, $reward['value'], class: 'chingming', method: 'get_rank_reward', now: $now);
            } elseif ($reward['type'] === 2) {
                TraderLog::addJade($uid, 2, $reward['value'], class: 'chingming', method: 'get_rank_reward', now: $now);
            }
        } else {
            $reward['type'] = 5;
            Member::addCurrency($uid, soul: $reward['value']);
        }
        $return['status'] = 1;
        $return['reward'] = $reward;
        $return['type'] = $act['type'];
        return $return;
    }

    /**
     * 主城（member.php index）：活动开启时取（没有则建）玩家活动信息，记录的 VIP 低于 2 而玩家已到 VIP 2 时同步 VIP（排行要求）
     */
    public static function syncVip(int $uid, int $version, int $now): void
    {
        $member = self::member($uid);
        if ($member === null) {
            return;
        }
        $info = self::memberInfo($member, $version, $now);
        if ($info['vip'] < 2 && $member['vip'] >= 2) {
            DB::table('chingming_log')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->update(['vip' => $member['vip']]);
        }
    }

    /**
     * 计划任务：给未领取排行奖励的玩家发奖并发信件（cron.php:get_chingming_reward）
     *
     * 只在活动结束后 10 小时内执行（endtime <= 当前 <= endtime + 36000）。
     *
     * @return int -1 无活动；-2 活动未结束；-3 结束超过 10 小时；1 已执行
     */
    public static function cronRankReward(int $now): int
    {
        $activity = self::cronActivity($now);
        if (is_int($activity)) {
            return $activity;
        }
        $type = (int)$activity['type'] === 1 ? 1 : 0;
        $version = (int)$activity['version'];
        $rankReward = self::rankRewardList($type);
        foreach (self::rankList($version) as $key => $val) {
            $i = $key + 1;
            // 给未领取排行奖励的用户添加奖励（修改领奖状态成功才发）
            if ($val['get_rank'] !== 0 || !isset($rankReward[$i]) || !self::updateRankRewardLog($val['uid'], $version, $i)) {
                continue;
            }
            $value = $rankReward[$i]['value'];
            if ($type === 1) {
                $message = '祭拜仙神';
                $rewardStr = '';
                if ($rankReward[$i]['type'] === 1) {
                    $rewardStr = '紫宝石';
                    TraderLog::addJade($val['uid'], 3, $value, class: 'cron', method: 'get_chingming_reward', now: $now);
                } elseif ($rankReward[$i]['type'] === 2) {
                    $rewardStr = '黄宝石';
                    TraderLog::addJade($val['uid'], 2, $value, class: 'cron', method: 'get_chingming_reward', now: $now);
                }
            } else {
                $message = '祭祖';
                $rewardStr = '原魂';
                Member::addCurrency($val['uid'], soul: $value);
            }
            // 发信
            $message .= '活动已结束，您未领取的排行奖励，系统已自动领取，获得了第' . $i . '名的奖励：' . $rewardStr . $value . '个，请查收!';
            MessageHandler::sendSystemMessage([$val['uid']], 1, self::LANG_MESSAGE_SYSTEM_TITLE, $message);
        }
        return 1;
    }

    /**
     * 计划任务：自动领取祭祖的成就奖励（cron.php:get_chingming_achievement_reward）
     *
     * 只在活动结束后 10 小时内执行。次数达到成就且没领过的玩家，同类奖励合并发放，每人一封信件。
     *
     * @return int -1 无活动；-2 活动未结束；-3 结束超过 10 小时；-4 成就表为空；-5 没有达到最低成就的玩家；-6 没有可发放的；1 已发放
     */
    public static function cronAchievementReward(int $now): int
    {
        $activity = self::cronActivity($now);
        if (is_int($activity)) {
            return $activity;
        }
        $type = (int)$activity['type'] === 1 ? 1 : 0;
        $version = (int)$activity['version'];
        $achievement = self::achievementList($type);
        if ($achievement === []) {
            return -4;
        }
        // 获取成就奖励列表中最低成就需求祭祖次数
        $times = 0;
        foreach ($achievement as $val) {
            if ($times === 0) {
                $times = $val['times'];
            }
            if ($val['times'] !== 0 && $val['times'] < $times) {
                $times = $val['times'];
            }
        }
        // 获取用户祭祖次数大于成就最小次数的用户列表
        $list = DB::table('chingming_log')->select('uid', 'times')->where('version', $version)->where('times', '>=', $times)
            ->whereNull('deleted_at')->get();
        if ($list === []) {
            return -5;
        }
        $reward = [];
        foreach ($list as $val) {
            $uid = (int)$val['uid'];
            foreach ($achievement as $small) {
                // 次数达到且没领过（领取记录写入成功）
                if ((int)$val['times'] < $small['times'] || !self::addRewardLog($uid, $version, $small['id'], $now)) {
                    continue;
                }
                for ($i = 1; $i <= 3; $i++) {
                    $t = $small['type' . $i];
                    if ($t === 8) {
                        $reward[$uid][8][] = $small['value' . $i];
                    } else {
                        $reward[$uid][$t] = ($reward[$uid][$t] ?? 0) + $small['value' . $i];
                    }
                }
            }
        }
        if ($reward === []) {
            return -6;
        }
        $str = $type === 1 ? '祭拜仙神' : '祭祖';
        $names = [1 => '黄宝石%s个，', 2 => '紫宝石%s个，', 3 => '玉石%s个，', 4 => '原魂%s个，', 5 => '突飞卡%s，', 6 => '银币%s，', 7 => '军令%s个，'];
        foreach ($reward as $uid => $val) {
            $message = '主公！' . $str . '活动已结束，您未领取的' . $str . '成就奖励已自动帮您领取，共获得：';
            foreach ($val as $t => $value) {
                if ($t === 8) {
                    // 红装-整件
                    foreach ($value as $itemId) {
                        $item = FestReward::equipInfo($itemId);
                        if ($item !== null) {
                            Equipment::addFromTemplate($uid, $itemId);
                        }
                        $message .= '一件' . ($item['name'] ?? $itemId) . ',';
                    }
                } elseif (isset($names[$t])) {
                    self::grant($uid, $t, $value, 'get_chingming_achievement_reward', $now, 'cron');
                    $message .= sprintf($names[$t], $value);
                }
            }
            $message .= '请查收！';
            MessageHandler::sendSystemMessage([$uid], 1, self::LANG_MESSAGE_SYSTEM_TITLE, $message);
        }
        return 1;
    }

    /**
     * 计划任务的活动窗口（get_activity_by_start + 结束后 10 小时内）
     *
     * @return array<string, int|string>|int 活动行，或 -1 无活动 / -2 未结束 / -3 结束超过 10 小时
     */
    private static function cronActivity(int $now): array|int
    {
        $activity = ActivityClock::byName(self::ACTIVITY);
        if ($activity === null) {
            return -1;
        }
        if ($activity['endtime'] > $now) {
            return -2;
        }
        if ($activity['endtime'] + 36000 < $now) {
            return -3;
        }
        return $activity;
    }

    /**
     * 发放常规 / 成就奖励（1 黄宝石，2 紫宝石，3 玉石，4 原魂，5 突飞卡，6 银币，7 军令）
     */
    private static function grant(int $uid, int $type, int $value, string $method, int $now, string $class = 'chingming'): void
    {
        match ($type) {
            1 => TraderLog::addJade($uid, 2, $value, class: $class, method: $method, now: $now),
            2 => TraderLog::addJade($uid, 3, $value, class: $class, method: $method, now: $now),
            3 => MemberJade::add($uid, $value, $class, $method, $now),
            4 => Member::addCurrency($uid, soul: $value),
            5 => FreeLeap::add($uid, $value, $now),
            6 => Member::addCurrency($uid, silver: $value),
            7 => Member::addCurrency($uid, act: $value),
            default => null,
        };
    }

    /**
     * 活动信息（_init_activity）：activity_status 开启时返回版本、类型、领奖时间（endtime - 86400）与剩余 cd；否则 null
     *
     * @return array{version: int, type: int, end: int, cd: int}|null
     */
    private static function init(int $now): ?array
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null) {
            return null;
        }
        // 活动结束后图标要多挂一天，所以活动会提前一天结束
        $end = (int)$info['endtime'] - 86400;
        return ['version' => (int)$info['version'], 'type' => (int)$info['type'], 'end' => $end, 'cd' => max(0, $end - $now)];
    }

    /**
     * @return array{uid: int, nickname: string, vip: int, level: int}|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        $row = DB::table('member')->select('uid', 'nickname', 'vip', 'level')->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : ['uid' => (int)$row['uid'], 'nickname' => (string)$row['nickname'], 'vip' => (int)$row['vip'], 'level' => (int)$row['level']];
    }

    /**
     * 接口回的 vip 提示：玩家 VIP 不足 2 时为 2，否则 0
     *
     * @param array{vip: int}|null $member
     */
    private static function vipTip(?array $member): int
    {
        return $member !== null && $member['vip'] < self::VIP ? self::VIP : 0;
    }

    /**
     * 玩家活动信息（get_member_info：没有时建档；昵称变了时更新）
     *
     * @param array{uid: int, nickname: string, vip: int} $member
     * @return array{times: int, free_times: int, vip: int, get_rank: int}
     */
    private static function memberInfo(array $member, int $version, int $now): array
    {
        $row = DB::table('chingming_log')->where('uid', $member['uid'])->where('version', $version)->whereNull('deleted_at')->first();
        if ($row === null) {
            // 首次进入，添加活动信息（主键 uid + version，并发建档只插一行）
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_chingming_log` (`uid`, `version`, `nickname`, `times`, `free_times`, `vip`, `get_rank`, `dateline`)
                 VALUES (:uid, :version, :nickname, 0, 0, :vip, 0, :now)',
                ['uid' => $member['uid'], 'version' => $version, 'nickname' => $member['nickname'], 'vip' => $member['vip'], 'now' => $now]
            );
            $row = DB::table('chingming_log')->where('uid', $member['uid'])->where('version', $version)->whereNull('deleted_at')->first()
                ?? ['times' => 0, 'free_times' => 0, 'vip' => $member['vip'], 'get_rank' => 0];
        } elseif ($row['nickname'] !== $member['nickname']) {
            DB::table('chingming_log')->where('uid', $member['uid'])->where('version', $version)->whereNull('deleted_at')
                ->update(['nickname' => $member['nickname']]);
        }
        return ['times' => (int)$row['times'], 'free_times' => (int)$row['free_times'], 'vip' => (int)$row['vip'], 'get_rank' => (int)$row['get_rank']];
    }

    /**
     * 成就奖励表（get_achievement_reward：type 1 取祭神表）
     *
     * @return array<int, array<string, int>>
     */
    private static function achievementList(int $type): array
    {
        return $type === 1 ? NewsacrificesAchievement::ROWS : ChingmingAchievement::ROWS;
    }

    /**
     * 排名奖励表（get_reward_by_rank：type 1 取祭神表，按名次索引）
     *
     * @return array<int, array<string, int>>
     */
    private static function rankRewardList(int $type): array
    {
        return $type === 1 ? NewsacrificesRankReward::ROWS : ChingmingRankReward::ROWS;
    }

    /**
     * 排行（get_rank_list：VIP 2 以上、次数大于 0，按次数降序、时间升序，前 30）
     *
     * @return list<array{uid: int, nickname: string, vip: int, times: int, get_rank: int}>
     */
    private static function rankList(int $version): array
    {
        $rows = DB::table('chingming_log')->select('uid', 'nickname', 'vip', 'times', 'get_rank')->where('version', $version)
            ->where('vip', '>=', self::VIP)->where('times', '>', 0)->whereNull('deleted_at')
            ->orderBy('times', 'DESC')->orderBy('dateline')->orderBy('uid')->limit(self::RANK_LIMIT)->get();
        return array_map(static fn (array $r): array => [
            'uid' => (int)$r['uid'],
            'nickname' => (string)$r['nickname'],
            'vip' => (int)$r['vip'],
            'times' => (int)$r['times'],
            'get_rank' => (int)$r['get_rank'],
        ], $rows);
    }

    /**
     * 成就领取记录（get_reward_log，按 rewardid 索引）
     *
     * @return array<int, true>
     */
    private static function rewardLog(int $uid, int $version): array
    {
        $return = [];
        foreach (DB::table('chingming_achievement_log')->select('rewardid')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->get() as $row) {
            $return[(int)$row['rewardid']] = true;
        }
        return $return;
    }

    private static function addRewardLog(int $uid, int $version, int $id, int $now): bool
    {
        return DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_chingming_achievement_log` (`uid`, `version`, `rewardid`, `dateline`) VALUES (:uid, :version, :id, :now)',
            ['uid' => $uid, 'version' => $version, 'id' => $id, 'now' => $now]
        ) > 0;
    }

    /**
     * 修改排行奖励领取状态（update_rank_reward_log：get_rank 写名次；只改还没领的）
     */
    private static function updateRankRewardLog(int $uid, int $version, int $rank): bool
    {
        return DB::table('chingming_log')->where('uid', $uid)->where('version', $version)->where('get_rank', 0)->whereNull('deleted_at')
            ->update(['get_rank' => $rank]) > 0;
    }

    /**
     * @return array{gold: int, silver: int, act: int}
     */
    private static function balances(int $uid): array
    {
        $row = DB::table('member')->select('gold', 'silver', 'act')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        return ['gold' => (int)($row['gold'] ?? 0), 'silver' => (int)($row['silver'] ?? 0), 'act' => (int)($row['act'] ?? 0)];
    }
}
