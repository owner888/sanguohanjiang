<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\Equip as EquipTemplate;
use Hanjiang\MasterData\WorldcupBoxReward;
use Hanjiang\MasterData\WorldcupHitChip;
use Hanjiang\MasterData\WorldcupHitReward;
use Hanjiang\MasterData\WorldcupRankReward;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FlowLog;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberIntegral;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\OwnSoul;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 我爱世界杯（移植旧版 controllers/worldcup.php + models/worldcup_model.php，计划任务 cron_kemari.php:get_worldcup_num_reward，
 * 充值记账 pay.php 各渠道回调的 worldcup_model->add_beatengod_pay_order）
 *
 * 玩法与暴打名将（BeatengodHandler）同构：花元宝用银球 / 金球（MasterData WorldcupHitChip：价格与碎片数 2~4 / 8~16 的权重）
 * 支持国家得碎片（worldcup_log.own_chip）与常规奖励（WorldcupHitReward；类型 1 银币 × 主公等级、2 军令、3 突飞卡、4 黄宝石、
 * 5 紫宝石、6 玉石、7 原魂、10 兑奖券 member_integral.act_kemari_int）；碎片排行前 30 名领宝石（WorldcupRankReward）；碎片累计
 * （含充值碎片）达到 WorldcupBoxReward 的数量开宝箱（另有 8 红装、9 声望）。活动期间充值记账 worldcup_order（每 1 元 1 个充值碎片）；
 * 活动结束后领的充值碎片记 end_chip（不进排行）。活动结束 10 小时内计划任务给未领取的前 30 名发排行奖励并发信件（旧版没有补开宝箱）。
 * 运营活动 ActivityTime 的 worldcup（旧版数据已结束）；活动结束后图标多挂一天，所以活动提前一天结束（_end = endtime - 1 天）。
 * 客户端 worldcuppop.js 调用 index、get_box_reward（id）、action（id 1 银球 2 金球）、get_pay_chip_info、get_pay_chip、
 * get_rank_list、get_rank_reward。当前时间取 BossClock。
 *
 * 照旧版：随机数 mt_rand(50, 总权重)；宝箱 id 小于 0 才回 -2（0 照常查不到奖励回 -4）；支持国家抽到未知奖励类型时元宝已扣、回 -6；
 * 宝箱里的红装只显示名字、不发小喇叭。
 * 与旧版的差别：写操作在 Member::locked（玩家行锁）里执行；worldcup_log 以 version + uid 为唯一键、worldcup_reward_log 以
 * uid + version + rewardid 为唯一键（旧版无索引）；兑奖券用原子加（旧版先读后写）。
 */
final class WorldcupHandler
{
    public const string ACTIVITY = 'worldcup';
    /** 碎片排行个数 */
    private const int RANK_LIMIT = 30;

    /** worldcup_lang.php */
    private const array LANG = [
        'worldcup_not_open' => '功能未开启！',
        'get_userinfo_error' => '获取玩家信息失败',
        'worldcup_box_reward_error' => '获取宝箱奖励信息失败！',
        'worldcup_hitchip_error' => '获取道具信息失败！',
        'worldcup_box_id_error' => '宝箱id错误！',
        'worldcup_reward_not_receive' => '您不能领取该奖励！',
        'worldcup_reward_already_receive' => '已经领取该奖励！',
        'worldcup_beaten_type_error' => '道具类型错误',
        'worldcup_gold_not_enough' => '元宝不足！',
        'worldcup_base_reward_error' => '获取奖励信息失败！',
        'worldcup_type_error' => '奖励类型错误！',
        'worldcup_gold_error' => '没有扣除元宝！',
        'has_chip' => '还有未领取的碎片！',
        'worldcup_rank_time_error' => '领取时间没到！',
        'not_in_rank' => '您不在排名里',
        // cron_lang.php
        'cron_worldcupfinish_get1' => '我爱世界杯活动已结束，您未领取的排行奖励，系统已自动领取，获得了第',
        'cron_worldcupfinish_get2' => '名的奖励：',
        'cron_blue' => '蓝宝石',
        'cron_yellow' => '黄宝石',
        'cron_purple' => '紫宝石',
        'cron_ge' => '个，',
        'cron_check' => '请查收!',
        'message_system_title' => '系统通知',
    ];

    /** worldcup_log 的旧版列 */
    private const array COLUMNS = ['id', 'version', 'uid', 'name', 'own_chip', 'pay_chip', 'end_chip', 'get_rank', 'dateline'];

    /**
     * 进入活动: c=worldcup&m=index
     *
     * -1 活动没开启；-2 获取用户信息错误；-3 获取宝箱奖励信息失败；-4 获取道具信息失败；0 失败；1 成功
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $act = self::activity();
        if ($act === null) {
            return ['status' => -1, 'msg' => self::LANG['worldcup_not_open']];
        }
        $uid = MemberHandler::resolveUid($ctx);
        $memberInfo = self::memberInfo($uid, $act['version'], $act['now']);
        if ($memberInfo === null) {
            return ['status' => -2, 'msg' => self::LANG['get_userinfo_error']];
        }
        $boxReward = array_values(WorldcupBoxReward::ROWS);
        if ($boxReward === []) {
            return ['status' => -3, 'msg' => self::LANG['worldcup_box_reward_error']];
        }
        // 获取宝箱奖励领取记录
        $rewardLog = self::rewardLog($uid, $act['version']);
        $ownChip = $memberInfo['own_chip'] + $memberInfo['end_chip'];
        foreach ($boxReward as $key => $val) {
            for ($i = 1; $i <= 3; $i++) {
                if ($val['type' . $i] === 8) {
                    // 查询道具信息（只显示名字）
                    $boxReward[$key]['value' . $i] = EquipTemplate::ROWS[$val['value' . $i]]['name'] ?? null;
                }
            }
            // 2 宝箱已打开；1 可开启；0 未开启
            $boxReward[$key]['open'] = isset($rewardLog[$val['id']]) ? 2 : ($val['number'] <= $ownChip ? 1 : 0);
        }
        // 获取道具价格
        $item = [];
        foreach (array_values(WorldcupHitChip::ROWS) as $key => $val) {
            $item[$key] = ['id' => $val['id'], 'price' => $val['price']];
        }
        if ($item === []) {
            return ['status' => -4, 'msg' => self::LANG['worldcup_hitchip_error']];
        }
        $return['status'] = 1;
        $return['reward'] = $boxReward;
        $return['own_chip'] = $ownChip;
        $return['item'] = $item;
        $return['cd'] = $act['cd'];
        return $return;
    }

    /**
     * 领取宝箱奖励: c=worldcup&m=get_box_reward（id 宝箱 id）
     *
     * -1 活动没开启；-2 宝箱id错误；-3 获取玩家信息失败；-4 宝箱奖励错误；-5 不能领取奖励；-6 已经领取该奖励；0 失败；1 成功
     */
    public static function get_box_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        if ($id < 0) {
            return ['status' => -2, 'msg' => self::LANG['worldcup_box_id_error']];
        }
        $act = self::activity();
        if ($act === null) {
            return ['status' => -1, 'msg' => self::LANG['worldcup_not_open']];
        }
        return Member::locked($uid, static function () use ($uid, $id, $act): array {
            $now = $act['now'];
            $memberInfo = self::memberInfo($uid, $act['version'], $now);
            if ($memberInfo === null) {
                return ['status' => -3, 'msg' => self::LANG['get_userinfo_error']];
            }
            $reward = WorldcupBoxReward::ROWS[$id] ?? null;
            if ($reward === null) {
                return ['status' => -4, 'msg' => self::LANG['worldcup_box_reward_error']];
            }
            // 判断自己是否可以领取该奖励
            if ($memberInfo['own_chip'] + $memberInfo['end_chip'] < $reward['number']) {
                return ['status' => -5, 'msg' => self::LANG['worldcup_reward_not_receive']];
            }
            if (isset(self::rewardLog($uid, $act['version'])[$id])) {
                return ['status' => -6, 'msg' => self::LANG['worldcup_reward_already_receive']];
            }
            // 添加领奖记录
            $added = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_worldcup_reward_log` (`uid`, `version`, `rewardid`, `dateline`) VALUES (:uid, :version, :id, :now)',
                ['uid' => $uid, 'version' => $act['version'], 'id' => $id, 'now' => $now]
            );
            if ($added === 0) {
                return ['status' => -6, 'msg' => self::LANG['worldcup_reward_already_receive']];
            }
            // 添加奖励
            for ($i = 1; $i <= 3; $i++) {
                $type = $reward['type' . $i];
                $value = $reward['value' . $i];
                if ($type === 8) {
                    // 红装-整件
                    $item = EquipTemplate::ROWS[$value] ?? null;
                    if ($item !== null) {
                        $reward['value' . $i] = ['itemid' => $item['id'], 'quality' => $item['quality'], 'itemname' => $item['name']];
                        Equipment::addFromTemplate($uid, $value);
                    }
                    continue;
                }
                // 宝箱奖励中的银币，不是系数
                self::grant($uid, $type, $value, 'get_box_reward', $now);
            }
            $after = self::balances($uid);
            return [
                'status' => 1,
                'reward' => $reward,
                'all_gold' => $after['gold'],
                'all_silver' => $after['silver'],
                'all_act' => $after['act'],
            ];
        });
    }

    /**
     * 支持国家: c=worldcup&m=action（id 1 银球，2 金球）
     *
     * -1 活动没开启；-2 获取碎片数据错误；-3 元宝不足；-4 奖励信息错误；-5 消耗元宝错误；-6 奖励类型错误；-7 道具类型错误；
     * 0 失败；1 成功
     */
    public static function action(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        if (!$id) {
            return ['status' => -7, 'msg' => self::LANG['worldcup_beaten_type_error']];
        }
        $act = self::activity();
        // 判断活动是否结束 / 开启
        if ($act === null || $act['cd'] <= 0) {
            return ['status' => -1, 'msg' => self::LANG['worldcup_not_open']];
        }
        return Member::locked($uid, static function () use ($uid, $id, $act): array {
            $now = $act['now'];
            $chip = WorldcupHitChip::ROWS[$id] ?? null;
            if ($chip === null) {
                return ['status' => -2, 'msg' => self::LANG['worldcup_hitchip_error']];
            }
            $member = DB::table('member')->select('level', 'gold')->where('uid', $uid)->whereNull('deleted_at')->first();
            if ($member === null || (int)$member['gold'] < $chip['price']) {
                return ['status' => -3, 'msg' => self::LANG['worldcup_gold_not_enough']];
            }
            // 得到要计算的数量和概率（银球 2 ~ 4，金球 8 ~ 16）
            $weights = [];
            foreach ($id === 1 ? range(2, 4) : range(8, 16) as $n) {
                $weights[$n] = $chip['number' . $n];
            }
            $addChip = self::roll($weights) ?? 0;
            // 得到常规奖励数据
            $itemReward = array_values(array_filter(WorldcupHitReward::ROWS, static fn (array $r): bool => $r['hit_type'] === $id));
            if ($itemReward === []) {
                return ['status' => -4, 'msg' => self::LANG['worldcup_base_reward_error']];
            }
            $rates = [];
            foreach ($itemReward as $key => $val) {
                $rates[$key] = (int)$val['rate'];
            }
            $pick = self::roll($rates);
            $addReward = $pick === null ? [] : $itemReward[$pick];
            // 扣除元宝
            if (!Member::deductCurrency($uid, gold: $chip['price'])) {
                return ['status' => -5, 'msg' => self::LANG['worldcup_gold_error']];
            }
            $type = (int)($addReward['type'] ?? 0);
            if ($type === 1) {
                // 银币（× 主公等级）
                $addReward['value'] = $addReward['value'] * (int)$member['level'];
            }
            if (!in_array($type, [1, 2, 3, 4, 5, 6, 7, 10], true)) {
                // 奖励类型错误（旧版元宝已扣）
                return ['status' => -6, 'msg' => self::LANG['worldcup_type_error']];
            }
            self::grant($uid, $type, (int)$addReward['value'], 'action', $now);
            // 添加碎片
            $number = self::addChip($uid, $act['version'], $addChip, 0, $now);
            // 记录人次
            DB::table('worldcup_action_log')->insert([
                'uid' => $uid,
                'type' => $id,
                'gold' => $chip['price'],
                'level' => (int)$member['level'],
                'dateline' => $now,
            ]);
            $after = self::balances($uid);
            return [
                'status' => 1,
                'add_chip' => $addChip,
                'number' => $number,
                'gettype' => $type,
                'getvalue' => $addReward['value'],
                'all_gold' => $after['gold'],
                'all_silver' => $after['silver'],
                'all_act' => $after['act'],
            ];
        });
    }

    /**
     * 查询充值碎片领取情况: c=worldcup&m=get_pay_chip_info
     *
     * -1 活动未开启；-2 获取玩家信息失败；0 失败；1 成功
     */
    public static function get_pay_chip_info(Context $ctx): array
    {
        $act = self::activity();
        if ($act === null) {
            return ['status' => -1, 'msg' => self::LANG['worldcup_not_open']];
        }
        $uid = MemberHandler::resolveUid($ctx);
        $memberInfo = self::memberInfo($uid, $act['version'], $act['now']);
        if ($memberInfo === null) {
            return ['status' => -2, 'msg' => self::LANG['get_userinfo_error']];
        }
        // 本次活动充值金额，总碎片数 = 金额 × 10 / 10
        $amount = self::amountByOrder($uid, $act['version']);
        return [
            'status' => 1,
            'gold' => $amount * 10,
            'get' => $memberInfo['pay_chip'],
            'isget' => $memberInfo['pay_chip'] < $amount ? 1 : 0,
        ];
    }

    /**
     * 领取充值碎片: c=worldcup&m=get_pay_chip
     *
     * -1 活动未开启；-2 获取玩家信息失败；-3 没有未领取的碎片；0 失败；1 成功
     */
    public static function get_pay_chip(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $act = self::activity();
        if ($act === null) {
            return ['status' => -1, 'msg' => self::LANG['worldcup_not_open']];
        }
        return Member::locked($uid, static function () use ($uid, $act): array {
            $now = $act['now'];
            // 活动结束后领的记 end_chip
            $ended = $act['cd'] <= 0 ? 1 : 0;
            $memberInfo = self::memberInfo($uid, $act['version'], $now);
            if ($memberInfo === null) {
                return ['status' => -2, 'msg' => self::LANG['get_userinfo_error']];
            }
            $allChip = self::amountByOrder($uid, $act['version']);
            if ($memberInfo['pay_chip'] >= $allChip) {
                return ['status' => -3, 'msg' => self::LANG['has_chip']];
            }
            // 修改记录中已领取碎片数量
            DB::table('worldcup_log')->where('uid', $uid)->where('version', $act['version'])->whereNull('deleted_at')->update(['pay_chip' => $allChip]);
            // 添加碎片
            $addChip = $allChip - $memberInfo['pay_chip'];
            $number = self::addChip($uid, $act['version'], $addChip, $ended, $now);
            return ['status' => 1, 'add_chip' => $addChip, 'all_chip' => $number, 'all_pay_chip' => $allChip];
        });
    }

    /**
     * 查看排行: c=worldcup&m=get_rank_list
     *
     * -1 活动未开启；0 失败；1 成功。isget：0 没有排行奖励，1 可领取，2 已领取
     */
    public static function get_rank_list(Context $ctx): array
    {
        $act = self::activity();
        if ($act === null) {
            return ['status' => -1, 'msg' => self::LANG['worldcup_not_open']];
        }
        $uid = MemberHandler::resolveUid($ctx);
        $list = self::rankList($act['version']);
        $myRank = 0;
        $myInfo = [];
        foreach ($list as $key => $val) {
            $list[$key]['rank'] = $key + 1;
            if ($val['uid'] === $uid) {
                $myRank = $key + 1;
                $myInfo = $val;
            }
            unset($list[$key]['version'], $list[$key]['pay_chip'], $list[$key]['get_rank'], $list[$key]['dateline']);
        }
        // 取排行奖励
        foreach ($list as $key => $val) {
            $reward = WorldcupRankReward::ROWS[$val['rank']] ?? null;
            $list[$key]['type'] = $reward['type'] ?? 0;
            $list[$key]['value'] = $reward['value'] ?? 0;
        }
        $isget = 0;
        if ($act['cd'] <= 0) {
            if ($myRank > 0 && $myInfo['get_rank'] > 0) {
                $isget = 2;
            } elseif ($myRank > 0) {
                $isget = 1;
            }
        }
        return ['status' => 1, 'list' => $list, 'isget' => $isget];
    }

    /**
     * 领取排行奖励: c=worldcup&m=get_rank_reward
     *
     * -1 活动未开启；-2 领奖时间未到；-3 获取玩家信息失败；-4 不在排名里；-5 奖励已经领取；-6 获取奖励信息错误；-7 修改领奖状态失败；
     * 0 失败；1 成功
     */
    public static function get_rank_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $act = self::activity();
        if ($act === null) {
            return ['status' => -1, 'msg' => self::LANG['worldcup_not_open']];
        }
        return Member::locked($uid, static function () use ($uid, $act): array {
            $now = $act['now'];
            // 判断是否到领奖的时候了
            if ($act['end'] - $now > 0) {
                return ['status' => -2, 'msg' => self::LANG['worldcup_rank_time_error']];
            }
            $memberInfo = self::memberInfo($uid, $act['version'], $now);
            if ($memberInfo === null) {
                return ['status' => -3, 'msg' => self::LANG['get_userinfo_error']];
            }
            // 获取自己的排名
            $myrank = 0;
            foreach (self::rankList($act['version']) as $i => $val) {
                if ($val['uid'] === $uid) {
                    $myrank = $i + 1;
                }
            }
            if ($myrank === 0) {
                return ['status' => -4, 'msg' => self::LANG['not_in_rank']];
            }
            if ($memberInfo['get_rank'] > 0) {
                return ['status' => -5, 'msg' => self::LANG['worldcup_reward_already_receive']];
            }
            $reward = WorldcupRankReward::ROWS[$myrank] ?? null;
            if ($reward === null) {
                return ['status' => -6, 'msg' => self::LANG['worldcup_base_reward_error']];
            }
            // 修改领奖状态
            if (self::updateRankRewardLog($act['version'], $myrank, $uid) !== 1) {
                return ['status' => -7];
            }
            TavernHandler::addJade($uid, $reward['type'], $reward['value'], 'worldcup', 'get_rank_reward');
            return ['status' => 1, 'reward' => $reward];
        });
    }

    /**
     * 我爱世界杯期间充值记账（worldcup_model->add_beatengod_pay_order：活动开启且离结束超过 1 天时，money 为人民币金额；
     * 由 Model\Pay::complete 在到账事务里调用）
     */
    public static function addPayOrder(int $uid, int $level, int $amount, string $orderId, int $now): void
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        // 活动结束后图标要多挂一天，所以活动会提前一天结束
        if ($info === null || (int)$info['endtime'] - 86400 <= $now) {
            return;
        }
        [$class, $method] = FlowLog::current();
        DB::table('worldcup_order')->insert([
            'uid' => $uid,
            'level' => $level,
            'money' => $amount,
            'version' => (int)$info['version'],
            'orderid' => $orderId,
            'class' => Member::logText($class, 20),
            'method' => Member::logText($method, 20),
            'dateline' => $now,
        ]);
    }

    /**
     * 计划任务：活动结束 10 小时内给未领取的前 30 名发排行奖励（cron_kemari.php:get_worldcup_num_reward）
     *
     * @return int 1 已执行；-1 无活动；-2 活动未结束；-3 结束超过 10 小时
     */
    public static function cronRankReward(int $now): int
    {
        $row = ActivityClock::byName(self::ACTIVITY);
        if ($row === null) {
            return -1;
        }
        if ((int)$row['endtime'] > $now) {
            return -2;
        }
        if ((int)$row['endtime'] + 36000 < $now) {
            return -3;
        }
        $version = (int)$row['version'];
        foreach (self::rankList($version) as $i => $val) {
            $rank = $i + 1;
            $reward = WorldcupRankReward::ROWS[$rank] ?? null;
            // 给未领取排行奖励的用户添加奖励
            if ($val['get_rank'] !== 0 || $reward === null) {
                continue;
            }
            if (self::updateRankRewardLog($version, $rank, $val['uid']) !== 1) {
                continue;
            }
            TavernHandler::addJade($val['uid'], $reward['type'], $reward['value'], 'cron_kemari', 'get_worldcup_num_reward');
            $message = self::LANG['cron_worldcupfinish_get1'] . $rank . self::LANG['cron_worldcupfinish_get2'];
            $message .= match ($reward['type']) {
                1 => self::LANG['cron_blue'],
                2 => self::LANG['cron_yellow'],
                3 => self::LANG['cron_purple'],
                default => '',
            };
            $message .= $reward['value'] . self::LANG['cron_ge'] . self::LANG['cron_check'];
            MessageHandler::sendSystemMessage([$val['uid']], 1, self::LANG['message_system_title'], $message);
        }
        return 1;
    }

    /**
     * 当前活动信息（_init_activity）：开启时返回版本、结束时间（endtime - 1 天）与倒计时；未开启返回 null
     *
     * @return array{version: int, end: int, cd: int, now: int}|null
     */
    private static function activity(): ?array
    {
        $now = BossClock::now();
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null) {
            return null;
        }
        $end = (int)$info['endtime'] - 86400;
        return ['version' => (int)$info['version'], 'end' => $end, 'cd' => max(0, $end - $now), 'now' => $now];
    }

    /**
     * 发一种奖励（1 银币、2 军令、3 突飞卡、4 黄宝石、5 紫宝石、6 玉石、7 原魂、9 声望、10 兑奖券）
     */
    private static function grant(int $uid, int $type, int $value, string $method, int $now): void
    {
        match ($type) {
            1 => Member::addCurrency($uid, silver: $value),
            2 => Member::addCurrency($uid, act: $value),
            3 => FreeLeap::add($uid, $value, $now),
            4 => TavernHandler::addJade($uid, 2, $value, 'worldcup', $method),
            5 => TavernHandler::addJade($uid, 3, $value, 'worldcup', $method),
            6 => MemberJade::add($uid, $value, 'worldcup', $method, $now),
            7 => OwnSoul::addSoul($uid, $value, 0, 'worldcup', $method, $now),
            9 => Member::addReputation($uid, $value),
            // 兑奖券（积分变化日志 add_memint_log）
            10 => MemberIntegral::change($uid, $value, 'act_kemari_int', 1, 'worldcup', $method, $now),
            default => null,
        };
    }

    /**
     * 按权重抽一个键（mt_rand(50, 总权重)，累计到第一个不小于随机数的键）；抽不到返回 null
     *
     * @param array<int, int> $weights
     */
    private static function roll(array $weights): ?int
    {
        $total = array_sum($weights);
        $rand = mt_rand(50, max(50, $total));
        $now = 0;
        foreach ($weights as $key => $val) {
            $now += $val;
            if ($rand <= $now) {
                return $key;
            }
        }
        return null;
    }

    /**
     * 用户活动信息（get_member_info：首次进入建档）
     *
     * @return array<string, mixed>|null
     */
    private static function memberInfo(int $uid, int $version, int $now): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        $row = self::row($uid, $version);
        if ($row !== null) {
            return $row;
        }
        $nickname = (string)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('nickname') ?? '');
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_worldcup_log` (`version`, `uid`, `name`, `own_chip`, `pay_chip`, `end_chip`, `get_rank`, `dateline`)
             VALUES (:version, :uid, :name, 0, 0, 0, 0, :now)',
            ['version' => $version, 'uid' => $uid, 'name' => Member::logText($nickname, 20), 'now' => $now]
        );
        return self::row($uid, $version);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function row(int $uid, int $version): ?array
    {
        $row = DB::table('worldcup_log')->select(...self::COLUMNS)->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first();
        return $row === null ? null : self::cast($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function cast(array $row): array
    {
        foreach ($row as $key => $value) {
            $row[$key] = $key === 'name' ? (string)$value : (int)$value;
        }
        return $row;
    }

    /**
     * 宝箱奖励领取记录（get_reward_log：rewardid => true）
     *
     * @return array<int, bool>
     */
    private static function rewardLog(int $uid, int $version): array
    {
        $return = [];
        foreach (DB::table('worldcup_reward_log')->select('rewardid')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->get() as $row) {
            $return[(int)$row['rewardid']] = true;
        }
        return $return;
    }

    /**
     * 添加碎片（add_chip：$ended 为 1 时记 end_chip，否则 own_chip），返回添加后的碎片总数
     */
    private static function addChip(int $uid, int $version, int $number, int $ended, int $now): int
    {
        $info = self::memberInfo($uid, $version, $now);
        if ($info === null) {
            return 0;
        }
        $column = $ended === 1 ? 'end_chip' : 'own_chip';
        DB::table('worldcup_log')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')
            ->update([$column => $info[$column] + $number, 'dateline' => $now]);
        return $info['own_chip'] + $info['end_chip'] + $number;
    }

    /**
     * 本次活动充值金额（get_amount_by_order：sum(money)）
     */
    private static function amountByOrder(int $uid, int $version): int
    {
        $sum = DB::query(
            'SELECT SUM(`money`) AS `money` FROM `zc_sanguo_worldcup_order` WHERE `uid` = :uid AND `version` = :version AND `deleted_at` IS NULL',
            ['uid' => $uid, 'version' => $version]
        )[0]['money'] ?? 0;
        return (int)round((float)$sum);
    }

    /**
     * 碎片排名前 30 的用户（get_rank_list：own_chip > 0，碎片降序、时间升序）
     *
     * @return list<array<string, mixed>>
     */
    private static function rankList(int $version): array
    {
        $rows = DB::table('worldcup_log')->select(...self::COLUMNS)->where('version', $version)->where('own_chip', '>', 0)
            ->whereNull('deleted_at')->orderBy('own_chip', 'DESC')->orderBy('dateline', 'ASC')->orderBy('id', 'ASC')->limit(self::RANK_LIMIT)->get();
        return array_map(self::cast(...), $rows);
    }

    /**
     * 修改排行奖励领取状态（update_rank_reward_log：只改还没领的）：1 成功，0 没有修改
     */
    private static function updateRankRewardLog(int $version, int $rank, int $uid): int
    {
        $affected = DB::table('worldcup_log')->where('uid', $uid)->where('version', $version)->where('get_rank', 0)->whereNull('deleted_at')
            ->update(['get_rank' => $rank]);
        return $affected > 0 ? 1 : 0;
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
