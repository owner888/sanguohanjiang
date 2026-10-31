<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\City\TotalLoginHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\MasterData\ThirtyReward;
use Hanjiang\MasterData\TotalloginReward;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FestLock;
use Hanjiang\Model\FestReward;
use Hanjiang\Model\FlowLog;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\PubLog;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 充值送豪礼（移植旧版 controllers/act_thirty.php + models/actthirty_model.php，计划任务 cron_acttask.php:new_pay_logined
 * （action_thirty_reward / update_thirty_info，以及同一任务里的累计登录 update_totallogin_reward），充值记账 pay.php）
 *
 * 活动时间与版本取 ActivityTime 的 actthirty（旧版数据已结束）。活动第 N 天（超过奖励表天数按最后一天）当天充值（thirty_order，
 * 人民币 × 10 元宝，Model\Pay::complete 调 addPayOrder）达到 MasterData ThirtyReward 第 N 行的 gold 可领当天的奖励（type1 ~ 5 /
 * value / num：1 元宝 2 银币 3 黄宝石 4 紫宝石 5 原魂 6 玉石 7 声望 8 装备 9 宝石碎片 10 突飞卡 11 蓝宝石 12 武将 13 军令 14 坐骑
 * 15 武将魂魄 16 装备碎片；num 大于 0 时为数量，否则 value 为数量）；武将魂魄累计（thirty.own_chip）达到全表魂魄之和可领该武将
 * （已拥有时按酒馆价格 × 0.8 发紫宝石）。每日 0 点计划任务给昨天充值达标未领的玩家补发并发信件；活动结束后把魂魄够的玩家补发武将、
 * 不够的按每个魂魄 5 紫宝石兑换。
 * 客户端 chargegiftpop.js 调用 index、action_reward、action_chip_reward。当前时间取 BossClock。
 *
 * 照旧版：action_reward 没有打开过首页（没有 thirty 记录）的玩家回 -3；领武将不扣魂魄；武将 7 星以上的小喇叭用的是语言包
 * thirty_get_reward（「恭喜您获得」，不带名字）；每日补发的奖励 type / value / num 互换传给发奖函数（与界面一致的数量）。
 * 与旧版的差别：各操作锁改为 Redis SET NX；领奖记录先 INSERT IGNORE（uid + day + version 唯一，旧版即如此）成功才发奖；
 * 领武将先写 thirty_chip_log（旧版 uid + version 唯一，旧版先发武将 / 紫宝石再插入，第二次领取在插入时报错但奖励已发：
 * 这里插入失败回 -4「已经领取该奖励」不发）；结束后的计划任务同样先写记录再发武将；锁在每个分支都释放（旧版用户信息为空时不释放）。
 */
final class ActThirtyHandler
{
    public const string ACTIVITY = 'actthirty';

    /** 领奖锁 */
    private const string ACTION_LOCK = 'thirty_action_reward';
    /** 领武将锁 */
    private const string CHIP_LOCK = 'chip_lock';

    /** thirty_lang.php + chat_lang.php + base_lang.php */
    private const array LANG = [
        'thirty_month' => '月',
        'thirty_day' => '日',
        'active_not_open' => '功能未开启！',
        'user_data_error' => '获取用户信息失败！',
        'reward_not_found' => '奖励表错误！',
        'gold_not_enough' => '充值元宝数不足！',
        'reward_is_open' => '已经领取该奖励',
        'thirty_fast_error' => '操作太快了！',
        'chip_is_not' => '武将碎片不足',
        'add_reward_log_end' => '添加奖励失败',
        'thirty_general_get' => '恭喜玩家【%s】在充值送豪礼活动中获得%s星武将-%s',
        'thirty_get_reward' => '恭喜您获得',
        'chat_thirty_mount_get' => '恭喜【%s】在充值送豪礼活动中获得名马-%s',
        'chat_thirty_get_quality' => '恭喜【%s】在充值送豪礼活动中获得装备-%s',
        'base_chip' => '碎片',
    ];

    /** 奖励类型名（thirty_1 ~ 16） */
    private const array TYPE_NAME = [
        1 => '元宝', 2 => '银币', 3 => '黄宝石', 4 => '紫宝石', 5 => '原魂', 6 => '玉石', 7 => '声望', 8 => '装备', 9 => '魔石碎片',
        10 => '突飞卡', 11 => '蓝宝石', 12 => '武将', 13 => '军令', 14 => '坐骑', 15 => '武将魂魄', 16 => '装备碎片',
    ];

    /** cron_lang.php */
    private const array LANG_CRON = [
        'cron_thirty' => '亲爱的玩家！您今天未领取的充值返豪礼奖励已自动帮您领取，共获得：',
        'cron_thirtyfinish_get1' => '充值送豪礼活动已结束，您未兑换的武将魂魄，系统已自动兑换，',
        'cron_thirtyfinish_get2' => '兑换',
        'cron_thirtyfinish_get3' => '充值送豪礼活动已结束，您未领取的奖励，系统已自动领取，获得',
        'cron_totalloginfinish_get1' => '亲爱的玩家！累计登陆活动已结束，您未领取的奖励，系统已自动领取，',
        'cron_check' => '请查收!',
        'cron_ge' => '个，',
        1 => '元宝', 2 => '银币', 3 => '黄宝石', 4 => '紫宝石', 5 => '将魂', 6 => '玉石', 7 => '声望', 8 => '装备-', 9 => '宝石碎片',
        10 => '突飞卡', 11 => '蓝宝石', 13 => '军令', 14 => '坐骑-', 15 => '武将魂魄', 16 => '装备碎片',
        'cron_change_purple' => '武将兑换紫宝石',
        'cron_general' => '武将-',
        'message_system_title' => '系统通知',
    ];

    /**
     * 首页: c=act_thirty&m=index
     *
     * -1 功能未开启；-2 获取奖励信息失败；-3 获取用户信息失败；0 失败；1 成功
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::init($now);
        if ($act === null || $uid <= 0) {
            $return['status'] = -1;
            $return['msg'] = self::LANG['active_not_open'];
            return $return;
        }
        // 获取用户信息（首页没有记录时建档）
        $userInfo = self::memberInfo($uid, $act['version'], true, $now);
        if ($userInfo === null) {
            $return['status'] = -3;
            $return['msg'] = self::LANG['user_data_error'];
            return $return;
        }
        // 获取奖励信息
        $reward = self::rewardRow($act['day']);
        if ($reward === null) {
            $return['status'] = -2;
            $return['msg'] = self::LANG['reward_not_found'];
            return $return;
        }
        $newArray = [];
        for ($i = 1; $i <= 5; $i++) {
            // 排除type=0的奖励
            if ($reward['type' . $i] !== 0) {
                $newArray[] = ['type' => $reward['type' . $i], 'value' => $reward['value' . $i], 'num' => $reward['num' . $i]];
            }
        }
        $rewardInfo = self::rewardItem($newArray);
        $new = [];
        foreach ($rewardInfo as $key => $val) {
            if ($val['type'] === 12 || $val['type'] === 15) {
                $new[] = $val;
                unset($rewardInfo[$key]);
            }
        }
        $lastArr1 = array_values($rewardInfo);
        if ($new !== []) {
            // 武将排序
            foreach (self::arraySort($new, 'star', 'desc') as $val) {
                $lastArr1[] = $val;
            }
        }
        // 获取用户今天的充值、领取奖励状态：2已领取 1 可领取 0不可领取
        $pay = self::todayPay($uid, $act['version'], $now);
        $isOpen = 0;
        if ($pay !== null && $pay >= $reward['gold']) {
            $isOpen = self::hasRewardLog($uid, $act['day'], $act['version']) ? 2 : 1;
        }
        [$totalChip, $gid] = self::totalChip();
        $ownChip = $userInfo['own_chip'];
        $chipStatus = self::chipStatus($uid, $act['version'], $act['day'], $ownChip, $totalChip, $gid);
        $return['status'] = 1;
        $return['reward_info'] = $lastArr1;
        $return['is_open'] = $isOpen;
        $return['chip_status'] = $chipStatus;
        $return['gold'] = (int)($pay ?? 0);
        $return['max_gold'] = $reward['gold'];
        $return['day'] = $act['day_info'];
        $return['total_chip'] = $totalChip;
        $return['own_chip'] = $ownChip;
        $return['time'] = $act['act_start'] . '-' . $act['act_end'];
        return $return;
    }

    /**
     * 领取奖励: c=act_thirty&m=action_reward
     *
     * -1 功能未开启；-2 获取奖励信息失败；-3 充值元宝数不足 / 获取用户信息失败；-4 已经领取该奖励 / 操作太快了；0 失败；1 成功
     */
    public static function action_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $lockKey = self::ACTION_LOCK . $uid;
        if ($uid <= 0 || !FestLock::acquire($lockKey)) {
            return ['status' => -4, 'msg' => self::LANG['thirty_fast_error']];
        }
        try {
            $act = self::init($now);
            if ($act === null) {
                return ['status' => -1, 'msg' => self::LANG['active_not_open']];
            }
            // 获取用户信息（不建档：没有打开过首页的玩家回 -3）
            $userInfo = self::memberInfo($uid, $act['version'], false, $now);
            if ($userInfo === null) {
                return ['status' => -3, 'msg' => self::LANG['user_data_error']];
            }
            $rewardInfo = self::rewardRow($act['day']);
            if ($rewardInfo === null) {
                return ['status' => -2, 'msg' => self::LANG['reward_not_found']];
            }
            // 获取用户充值金额
            $pay = self::todayPay($uid, $act['version'], $now);
            if ($pay === null || $pay < $rewardInfo['gold']) {
                return ['status' => -3, 'msg' => self::LANG['gold_not_enough']];
            }
            // 获取领取奖励log日志
            if (self::hasRewardLog($uid, $act['day'], $act['version'])) {
                return ['status' => -4, 'msg' => self::LANG['reward_is_open']];
            }
            // 添加log日志（uid + day + version 唯一）
            if (!self::addRewardLog($uid, $act['day'], $act['version'], $rewardInfo, $now)) {
                return ['status' => -4, 'msg' => self::LANG['add_reward_log_end']];
            }
            // 发放奖励
            $newReturn = [];
            for ($i = 1; $i <= 5; $i++) {
                $reward = ['type' => $rewardInfo['type' . $i], 'value' => $rewardInfo['value' . $i], 'num' => $rewardInfo['num' . $i]];
                if ($reward['type'] === 0) {
                    continue;
                }
                if (in_array($reward['type'], [8, 12, 16, 15], true)) {
                    $reward['id'] = $reward['value'];
                }
                // 向玩家发送奖励
                $added = self::addReward($uid, $act['version'], $reward, $now);
                if (!empty($added['info'])) {
                    $newReturn[] = $added['info'];
                }
            }
            // 显示用户获取碎片信息
            $newChip = self::memberInfo($uid, $act['version'], false, $now)['own_chip'] ?? 0;
            [$totalChip, $gid] = self::totalChip();
            $chipStatus = self::chipStatus($uid, $act['version'], $act['day'], $newChip, $totalChip, $gid);
            $member = self::balances($uid);
            return [
                'status' => 1,
                'chip_status' => $chipStatus,
                'reward_info' => $newReturn,
                'own_chip' => $newChip,
                'silver' => $member['silver'],
                'gold' => $member['gold'],
            ];
        } finally {
            FestLock::release($lockKey);
        }
    }

    /**
     * 领取武将: c=act_thirty&m=action_chip_reward
     *
     * -1 功能未开启；-2 获取奖励信息失败；-3 碎片不足 / 获取用户信息失败；-4 已经领取该奖励 / 操作太快了；0 失败；1 成功
     */
    public static function action_chip_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $lockKey = self::CHIP_LOCK . $uid;
        if ($uid <= 0 || !FestLock::acquire($lockKey)) {
            return ['status' => -4, 'msg' => self::LANG['thirty_fast_error']];
        }
        try {
            $act = self::init($now);
            if ($act === null) {
                return ['status' => -1, 'msg' => self::LANG['active_not_open']];
            }
            $userInfo = self::memberInfo($uid, $act['version'], true, $now);
            if ($userInfo === null) {
                return ['status' => -3, 'msg' => self::LANG['user_data_error']];
            }
            [$totalChip, $gid] = self::totalChip();
            // 碎片数量不足
            if ($userInfo['own_chip'] < $totalChip) {
                return ['status' => -3, 'msg' => self::LANG['chip_is_not']];
            }
            // 添加Log记录（uid + version 唯一：已领过时不再发放，见类注释）
            $added = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_thirty_chip_log` (`uid`, `day`, `version`, `g_id`, `dateline`) VALUES (:uid, :day, :version, :gid, :now)',
                ['uid' => $uid, 'day' => $act['day'], 'version' => $act['version'], 'gid' => $gid, 'now' => $now]
            );
            if ($added <= 0) {
                return ['status' => -4, 'msg' => self::LANG['reward_is_open']];
            }
            // 发送奖励：查询自己是否已经获得武将
            $info = [];
            if (General::ownsTemplate($uid, $gid)) {
                // 查询武将的紫宝石价格信息
                $jade = FestReward::generalJade($gid);
                if ($jade !== null) {
                    // intval(price) × 0.8（FestReward::generalJade 同 $_rebate）
                    $jadenum = $jade;
                    TavernHandler::addJade($uid, 3, (int)$jadenum, 'act_thirty', 'action_chip_reward');
                    $info = [
                        'type' => 3,
                        'value' => $jadenum,
                        'name' => self::TYPE_NAME[4],
                        'msg' => self::LANG['thirty_get_reward'] . self::TYPE_NAME[4] . '*' . $jadenum,
                    ];
                }
            } else {
                // 添加武将
                $general = General::addGeneral($uid, $gid);
                if ($general !== null) {
                    $info = $general;
                    $star = Chat::starWord((int)$general['star']);
                    if ($star !== null) {
                        $sender = Chat::sender($uid);
                        Chat::sendToHorn(sprintf(self::LANG['thirty_general_get'], (string)($sender['nickname'] ?? ''), $star, $general['name']), $sender);
                        // 聊天框发送消息
                        $chat = Chat::getMessageStr(48, ['star_general' => $star, 'general' => $general['name']], $sender);
                        Chat::sendToAll($chat, Chat::TYPE_SYSTEM, 0, 0, $sender);
                    }
                    // 添加酒馆日志(武将id, 拥有状态, 获取方式)
                    PubLog::add($uid, $gid, 1, 103, $now);
                }
            }
            $member = self::balances($uid);
            return [
                'status' => 1,
                'reward_info' => ['type' => 15, 'value' => $gid, 'info' => $info],
                'silver' => $member['silver'],
                'gold' => $member['gold'],
            ];
        } finally {
            FestLock::release($lockKey);
        }
    }

    /**
     * 充值送豪奖活动期间充值记账（actthirty_model->add_thirty_pay_order：活动开启且未结束时，gold = 人民币金额 × 10；
     * 由 Model\Pay::complete 在到账事务里调用）
     */
    public static function addPayOrder(int $uid, int $level, int $amount, string $orderId, int $now): void
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null || (int)$info['endtime'] <= $now) {
            return;
        }
        [$class, $method] = FlowLog::current();
        DB::table('thirty_order')->insert([
            'uid' => $uid,
            'level' => $level,
            'gold' => $amount * 10,
            'version' => (int)$info['version'],
            'orderid' => $orderId,
            'class' => Member::logText($class, 20),
            'method' => Member::logText($method, 20),
            'dateline' => $now,
        ]);
    }

    /**
     * 计划任务：新活动，累计登陆 + 充值送豪礼（cron_acttask.php:new_pay_logined，旧版注释「每天零点」）
     *
     * @return array<string, int>
     */
    public static function cronNewPayLogined(int $now): array
    {
        return [
            // 充值送豪奖玩家武将碎片兑换
            'action_thirty_reward' => self::cronChipReward($now),
            // 更新充值送豪奖信息 发送玩家当天未领取的奖励
            'update_thirty_info' => self::cronDailyReward($now),
            // 累计登陆活动结束以后 如果未领取神将 发奖
            'update_totallogin_reward' => self::cronTotalLogin($now),
        ];
    }

    /**
     * 充值送豪奖玩家武将碎片兑换（action_thirty_reward：活动结束后执行，旧版没有结束后的窗口）
     *
     * 魂魄够的玩家：没有最后一天的领武将记录时补发武将（已拥有发紫宝石）并发信件；不够的：每个魂魄 5 紫宝石并发信件；最后清零魂魄。
     *
     * @return int -1 无活动；-2 活动未开始；-3 活动未结束；1 已执行
     */
    public static function cronChipReward(int $now): int
    {
        $activity = ActivityClock::byName(self::ACTIVITY);
        if ($activity === null) {
            return -1;
        }
        if ((int)$activity['starttime'] > $now) {
            return -2;
        }
        if ((int)$activity['endtime'] > $now) {
            return -3;
        }
        $version = (int)$activity['version'];
        $day = self::maxDay();
        [$totalChip, $gid] = self::totalChip();
        $userData = DB::table('thirty')->select('uid', 'own_chip')->where('version', $version)->where('own_chip', '>', 0)->whereNull('deleted_at')->get();
        foreach ($userData as $val) {
            $uid = (int)$val['uid'];
            $ownChip = (int)$val['own_chip'];
            // 更新碎片（条件更新：重复执行不会重复处理）
            $cleared = DB::table('thirty')->where('uid', $uid)->where('version', $version)->where('own_chip', $ownChip)->whereNull('deleted_at')
                ->update(['own_chip' => 0]);
            if ($cleared <= 0) {
                continue;
            }
            if ($ownChip >= $totalChip) {
                // 查看用户是否已经领取奖励（旧版按最后一天查；领武将记录 uid + version 唯一，插入成功才补发）
                if (self::hasChipLog($uid, $version, $day)) {
                    continue;
                }
                $added = DB::execute(
                    'INSERT IGNORE INTO `zc_sanguo_thirty_chip_log` (`uid`, `day`, `version`, `g_id`, `dateline`) VALUES (:uid, :day, :version, :gid, :now)',
                    ['uid' => $uid, 'day' => $day, 'version' => $version, 'gid' => $gid, 'now' => $now]
                );
                if ($added <= 0) {
                    continue;
                }
                // 发送武将
                $message = self::LANG_CRON['cron_thirtyfinish_get3'] . self::cronReward(12, $gid, $gid, $uid, $version, 'action_thirty_reward', $now)
                    . self::LANG_CRON['cron_check'];
                MessageHandler::sendSystemMessage([$uid], 1, self::LANG_CRON['message_system_title'], $message);
            } else {
                // 碎片兑换：一个碎片兑换5个紫宝石
                $jadenum = $ownChip * 5;
                TavernHandler::addJade($uid, 3, $jadenum, 'cron_acttask', 'action_thirty_reward');
                $message = self::LANG_CRON['cron_thirtyfinish_get1'] . self::LANG_CRON['cron_thirtyfinish_get2']
                    . self::LANG_CRON[4] . $jadenum . self::LANG_CRON['cron_ge'] . self::LANG_CRON['cron_check'];
                MessageHandler::sendSystemMessage([$uid], 1, self::LANG_CRON['message_system_title'], $message);
            }
        }
        return 1;
    }

    /**
     * 更新充值送豪奖信息，发送玩家昨天未领取的奖励（update_thirty_info：活动期间每天 0 点执行）
     *
     * 照旧版：奖励取「今天是第几天」那一行，记录写在前一天（day - 1）；昨天充值达到该行 gold 且前一天没有领取记录的玩家补发并发信件。
     *
     * @return int -1 无活动；-2 活动已结束；-3 活动未开始；1 已执行
     */
    public static function cronDailyReward(int $now): int
    {
        $activity = ActivityClock::byName(self::ACTIVITY);
        if ($activity === null) {
            return -1;
        }
        if ((int)$activity['endtime'] < $now) {
            return -2;
        }
        if ((int)$activity['starttime'] > $now) {
            return -3;
        }
        $version = (int)$activity['version'];
        $day = min(self::maxDay(), intdiv($now - (int)$activity['starttime'], 86400) + 1);
        $rewardInfo = self::rewardRow($day);
        if ($rewardInfo === null) {
            return 1;
        }
        // 昨天 0 点 ~ 今天 0 点
        $endTime = (int)strtotime(date('Y-m-d', $now));
        $startTime = $endTime - 86400;
        $userData = DB::query(
            'SELECT `uid`, SUM(`gold`) AS `gold` FROM `zc_sanguo_thirty_order` WHERE `version` = :version AND `dateline` BETWEEN :start AND :end
             AND `deleted_at` IS NULL GROUP BY `uid`',
            ['version' => $version, 'start' => $startTime, 'end' => $endTime]
        );
        foreach ($userData as $val) {
            $uid = (int)$val['uid'];
            if ((float)$val['gold'] < $rewardInfo['gold']) {
                continue;
            }
            // 如果没有领取奖励就发奖（记录先写，成功才发）
            if (!self::addRewardLog($uid, $day - 1, $version, $rewardInfo, $now)) {
                continue;
            }
            $message = self::LANG_CRON['cron_thirty'];
            for ($i = 1; $i <= 5; $i++) {
                $message .= self::cronReward($rewardInfo['type' . $i], $rewardInfo['num' . $i], $rewardInfo['value' . $i], $uid, $version, 'update_thirty_info', $now);
            }
            $message .= self::LANG_CRON['cron_check'];
            MessageHandler::sendSystemMessage([$uid], 1, self::LANG_CRON['message_system_title'], $message);
        }
        return 1;
    }

    /**
     * 累计登陆活动结束以后，如果未领取神将发奖（update_totallogin_reward：累计 15 天、没有领取记录的玩家补发并发信件）
     *
     * @return int -1 无活动；-2 活动未结束；-3 活动未开始；-4 奖励表为空；1 已执行
     */
    public static function cronTotalLogin(int $now): int
    {
        $activity = ActivityClock::byName(TotalLoginHandler::ACTIVITY);
        if ($activity === null) {
            return -1;
        }
        if ((int)$activity['endtime'] > $now) {
            return -2;
        }
        if ((int)$activity['starttime'] > $now) {
            return -3;
        }
        $rewardInfo = array_values(TotalloginReward::ROWS)[0] ?? null;
        if ($rewardInfo === null) {
            return -4;
        }
        $version = (int)$activity['version'];
        $users = DB::table('totallogin')->select('uid', 'day')->where('version', $version)->where('day', '>=', 15)->whereNull('deleted_at')->get();
        foreach ($users as $val) {
            $uid = (int)$val['uid'];
            // 添加Log记录（uid + version 唯一，插入成功才发）
            $added = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_totallogin_reward_log` (`uid`, `version`, `dateline`) VALUES (:uid, :version, :now)',
                ['uid' => $uid, 'version' => $version, 'now' => $now]
            );
            if ($added <= 0) {
                continue;
            }
            // 发送武将
            $message = self::LANG_CRON['cron_totalloginfinish_get1']
                . self::cronReward((int)$rewardInfo['type'], (int)$rewardInfo['value'], (int)$rewardInfo['value'], $uid, $version, 'update_totallogin_reward', $now)
                . self::LANG_CRON['cron_check'];
            MessageHandler::sendSystemMessage([$uid], 1, self::LANG_CRON['message_system_title'], $message);
        }
        return 1;
    }

    /**
     * 活动信息（_init）：活动开启时返回版本、第几天（超过奖励表天数取最后一天）与日期文字；否则 null
     *
     * @return array{version: int, day: int, day_info: int, act_start: string, act_end: string}|null
     */
    private static function init(int $now): ?array
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null) {
            return null;
        }
        $start = (int)$info['starttime'];
        $end = (int)$info['endtime'] - 1;
        // 计算当前是活动的第几天
        $day = min(self::maxDay(), intdiv($now - $start, 86400) + 1);
        $format = 'm' . self::LANG['thirty_month'] . 'd' . self::LANG['thirty_day'];
        return [
            'version' => (int)$info['version'],
            'day' => $day,
            'day_info' => $day,
            'act_start' => date($format, $start),
            'act_end' => date($format, $end),
        ];
    }

    /** 奖励表最大的天数（get_max_day） */
    private static function maxDay(): int
    {
        return ThirtyReward::ROWS === [] ? 0 : max(array_keys(ThirtyReward::ROWS));
    }

    /**
     * 某天的奖励（get_reward_info($day)：超过最大天数取最后一天）
     *
     * @return array<string, int>|null
     */
    private static function rewardRow(int $day): ?array
    {
        return ThirtyReward::ROWS[min($day, self::maxDay())] ?? null;
    }

    /**
     * 全表的武将魂魄总数与武将 id（type 15 的 num 之和，武将取最后一个）
     *
     * @return array{0: int, 1: int}
     */
    private static function totalChip(): array
    {
        $total = $gid = 0;
        foreach (ThirtyReward::ROWS as $val) {
            for ($i = 1; $i <= 5; $i++) {
                if ($val['type' . $i] === 15) {
                    $total += $val['num' . $i];
                    $gid = $val['value' . $i];
                }
            }
        }
        return [$total, $gid];
    }

    /**
     * 领武将状态：0 魂魄不够；1 可领；2 当天已领或已拥有该武将
     */
    private static function chipStatus(int $uid, int $version, int $day, int $ownChip, int $totalChip, int $gid): int
    {
        if ($ownChip < $totalChip) {
            return 0;
        }
        if (self::hasChipLog($uid, $version, $day) || General::ownsTemplate($uid, $gid)) {
            return 2;
        }
        return 1;
    }

    private static function hasChipLog(int $uid, int $version, int $day): bool
    {
        return DB::table('thirty_chip_log')->where('uid', $uid)->where('version', $version)->where('day', $day)->whereNull('deleted_at')->value('id') !== null;
    }

    private static function hasRewardLog(int $uid, int $day, int $version): bool
    {
        return DB::table('thirty_log')->where('uid', $uid)->where('version', $version)->where('day', $day)->whereNull('deleted_at')->value('id') !== null;
    }

    /**
     * 写每日领奖记录（add_reward_log；uid + day + version 唯一，已有时返回 false）
     *
     * @param array<string, int> $rewardInfo
     */
    private static function addRewardLog(int $uid, int $day, int $version, array $rewardInfo, int $now): bool
    {
        $data = ['uid' => $uid, 'day' => $day, 'version' => $version, 'dateline' => $now];
        for ($i = 1; $i <= 5; $i++) {
            $data['type' . $i] = $rewardInfo['type' . $i];
            $data['value' . $i] = $rewardInfo['value' . $i];
            $data['num' . $i] = $rewardInfo['num' . $i];
        }
        $columns = '`' . implode('`, `', array_keys($data)) . '`';
        $values = ':' . implode(', :', array_keys($data));
        return DB::execute("INSERT IGNORE INTO `zc_sanguo_thirty_log` ({$columns}) VALUES ({$values})", $data) > 0;
    }

    /**
     * 今天 0 点到明天 0 点的充值元宝合计（get_user_pay，BETWEEN 含两端）；没有充值记录为 null
     */
    private static function todayPay(int $uid, int $version, int $now): int|float|null
    {
        $start = (int)strtotime(date('Y-m-d', $now));
        $row = DB::query(
            'SELECT COUNT(*) AS `n`, SUM(`gold`) AS `gold` FROM `zc_sanguo_thirty_order` WHERE `uid` = :uid AND `version` = :version
             AND `dateline` BETWEEN :start AND :end AND `deleted_at` IS NULL',
            ['uid' => $uid, 'version' => $version, 'start' => $start, 'end' => $start + 86400]
        )[0] ?? null;
        if ($row === null || (int)$row['n'] === 0) {
            return null;
        }
        $gold = (float)$row['gold'];
        return floor($gold) === $gold ? (int)$gold : $gold;
    }

    /**
     * 用户信息（get_member_info：$init 为 true 时没有记录就建档）；没有为 null
     *
     * @return array{own_chip: int}|null
     */
    private static function memberInfo(int $uid, int $version, bool $init, int $now): ?array
    {
        $row = DB::table('thirty')->select('own_chip')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first();
        if ($row === null && $init) {
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_thirty` (`uid`, `own_chip`, `version`, `dateline`) VALUES (:uid, 0, :version, :now)',
                ['uid' => $uid, 'version' => $version, 'now' => $now]
            );
            $row = DB::table('thirty')->select('own_chip')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first();
        }
        return $row === null ? null : ['own_chip' => (int)$row['own_chip']];
    }

    /**
     * 添加奖励信息（add_reward）
     *
     * @param array<string, int> $rewardInfo type、value、num，装备 / 武将 / 魂魄 / 装备碎片另含 id
     * @return array{info: array<string, mixed>}
     */
    private static function addReward(int $uid, int $version, array $rewardInfo, int $now): array
    {
        $type = $rewardInfo['type'];
        $value = $rewardInfo['value'];
        $id = $rewardInfo['id'] ?? 0;
        $num = $rewardInfo['num'] > 0 ? $rewardInfo['num'] : $value;
        $return = ['info' => ['type' => $type, 'value' => $value, 'num' => $num, 'name' => self::TYPE_NAME[$type] ?? false]];
        $method = 'action_reward';
        switch ($type) {
            case 1: // 元宝
                Member::addCurrency($uid, gold: $num);
                break;
            case 2: // 银币
                Member::addCurrency($uid, silver: $num);
                break;
            case 3: // 黄宝石
                TavernHandler::addJade($uid, 2, $num, 'act_thirty', $method);
                break;
            case 4: // 紫宝石
                TavernHandler::addJade($uid, 3, $num, 'act_thirty', $method);
                break;
            case 5: // 原魂
                Member::addCurrency($uid, soul: $num);
                break;
            case 6: // 玉石
                MemberJade::add($uid, $num, 'act_thirty', $method, $now);
                break;
            case 7: // 声望
                Member::addReputation($uid, $num);
                break;
            case 8: // 装备
                $item = FestReward::equipInfo($id);
                if ($item !== null) {
                    Equipment::addFromTemplate($uid, $id, $num);
                    // 发送广播到小喇叭（只发紫装以上品质的装备）
                    if ((int)$item['quality'] >= 5) {
                        $sender = Chat::sender($uid);
                        $name = Chat::quality($item['quality'], (string)$item['name']);
                        Chat::sendToHorn(sprintf(self::LANG['chat_thirty_get_quality'], (string)($sender['nickname'] ?? ''), $name), $sender);
                    }
                    $return['info'] = [
                        'id' => $item['id'],
                        'image' => $item['id'],
                        'name' => $item['name'],
                        'quality' => $item['quality'],
                        'type' => 8,
                        'value' => $item['id'],
                        'num' => 1,
                    ];
                }
                break;
            case 9: // 宝石碎片
                OwnGem::addChip($uid, $num, 'act_thirty', $method, $now);
                break;
            case 10: // 突飞卡
                FreeLeap::add($uid, $num, $now);
                break;
            case 11: // 蓝宝石
                TavernHandler::addJade($uid, 1, $num, 'act_thirty', $method);
                break;
            case 12: // 武将
                $gid = $id;
                if (General::ownsTemplate($uid, $gid)) {
                    // 查询武将的紫宝石价格信息
                    $jade = FestReward::generalJade($gid);
                    if ($jade !== null) {
                        TavernHandler::addJade($uid, 3, (int)$jade, 'act_thirty', $method);
                        $return['info']['type'] = 3;
                        $return['info']['value'] = $jade;
                        $return['info']['name'] = self::TYPE_NAME[4];
                        $return['info']['msg'] = self::LANG['thirty_get_reward'] . self::TYPE_NAME[4] . '*' . $jade;
                    }
                } else {
                    // 添加武将
                    $general = General::addGeneral($uid, $gid);
                    $return['info'] = $general ?? ['status' => 0];
                    if ($general !== null) {
                        $star = Chat::starWord((int)$general['star']);
                        if ($star !== null) {
                            // 照旧版：小喇叭用的是 thirty_get_reward（没有占位符）
                            $sender = Chat::sender($uid);
                            Chat::sendToHorn(self::LANG['thirty_get_reward'], $sender);
                            // 聊天框发送消息
                            $chat = Chat::getMessageStr(48, ['star_general' => $star, 'general' => $general['name']], $sender);
                            Chat::sendToAll($chat, Chat::TYPE_SYSTEM, 0, 0, $sender);
                        }
                        // 添加酒馆日志(武将id, 拥有状态, 获取方式)
                        PubLog::add($uid, $gid, 1, 103, $now);
                    }
                }
                break;
            case 13: // 军令（add_act(1, value)）
                Member::addCurrency($uid, act: $value);
                break;
            case 14: // 坐骑
                $mount = MountTemp::ROWS[$id] ?? null;
                $result = OwnMount::addMount($uid, $id, 0, 1, 'act_thirty', $method, $now);
                if ($result['status'] === 1 && $mount !== null && (int)$mount['quality'] >= 5) {
                    // 如果获得的坐骑品级大于5 发送广播到小喇叭
                    $sender = Chat::sender($uid);
                    $qualityStr = Chat::quality($mount['quality'], (string)$mount['name']);
                    Chat::sendToHorn(sprintf(self::LANG['chat_thirty_mount_get'], (string)($sender['nickname'] ?? ''), $qualityStr), $sender);
                }
                $return['info'] = $mount === null ? [] : ['id' => $mount['id'], 'name' => $mount['name'], 'quality' => $mount['quality']];
                break;
            case 15: // 获得武将碎片（魂魄）
                DB::execute(
                    'INSERT INTO `zc_sanguo_thirty` (`uid`, `own_chip`, `version`, `dateline`) VALUES (:uid, :num, :version, :now)
                     ON DUPLICATE KEY UPDATE `own_chip` = `own_chip` + VALUES(`own_chip`)',
                    ['uid' => $uid, 'num' => $num, 'version' => $version, 'now' => $now]
                );
                $return['info']['value'] = GeneralTemplate::ROWS[$id]['image'] ?? null;
                break;
            case 16: // 装备碎片
                $item = FestReward::equipInfo($id);
                // 添加碎片
                Equipment::addChip($uid, $id, $num, 'act_thirty', $method, $now);
                if ($item !== null) {
                    $return['info'] = [
                        'id' => $item['id'],
                        'image' => $item['id'],
                        'name' => $item['name'] . self::LANG['base_chip'],
                        'quality' => $item['quality'],
                        'type' => 16,
                        'needlevel' => $item['needlevel'],
                        'power' => $item['power'],
                        'value' => $id,
                        'num' => $num,
                    ];
                }
                break;
        }
        return $return;
    }

    /**
     * 统一返回（_reward_item：首页奖励列表的展示信息）
     *
     * @param list<array<string, int>> $newReward
     * @return list<array<string, mixed>>
     */
    private static function rewardItem(array $newReward): array
    {
        $list = [];
        foreach ($newReward as $value) {
            $tmp = ['type' => $value['type'], 'value' => $value['value'], 'num' => $value['num'] > 0 ? $value['num'] : $value['value']];
            switch ($value['type']) {
                case 12: // 武将
                case 15: // 武将碎片
                    $general = FestReward::generalInfo($value['value']);
                    $tmp['general'] = $general;
                    $tmp['name'] = $general['name'] ?? null;
                    $tmp['star'] = $general['star'] ?? null;
                    break;
                case 8: // 装备
                    $item = FestReward::equipInfo($value['value']) ?? [];
                    $tmp['name'] = $item['name'] ?? null;
                    $tmp['info'] = [
                        'id' => $item['id'] ?? null,
                        'image' => $item['id'] ?? null,
                        'name' => $item['name'] ?? null,
                        'quality' => $item['quality'] ?? null,
                        'needlevel' => $item['needlevel'] ?? null,
                        'power' => $item['power'] ?? null,
                        'type' => $item['type'] ?? null,
                    ];
                    break;
                case 16: // 装备碎片
                    $item = FestReward::equipInfo($value['value']) ?? [];
                    $tmp['name'] = $item['name'] ?? null;
                    $tmp['info'] = [
                        'id' => $item['id'] ?? null,
                        'image' => $item['id'] ?? null,
                        'name' => ($item['name'] ?? '') . self::LANG['base_chip'],
                        'quality' => $item['quality'] ?? null,
                        'type' => 16,
                        'needlevel' => $item['needlevel'] ?? null,
                        'power' => $item['power'] ?? null,
                        'num' => $tmp['num'],
                    ];
                    break;
                default:
                    if (isset(self::TYPE_NAME[$value['type']])) {
                        $tmp['name'] = self::TYPE_NAME[$value['type']];
                    }
                    break;
            }
            $list[] = $tmp;
        }
        return $list;
    }

    /**
     * 武将排序（_array_sort）
     *
     * @param list<array<string, mixed>> $arr
     * @return list<array<string, mixed>>
     */
    private static function arraySort(array $arr, string $keys, string $type = 'asc'): array
    {
        $keysvalue = [];
        foreach ($arr as $k => $v) {
            $keysvalue[$k] = $v[$keys];
        }
        if ($type === 'asc') {
            asort($keysvalue);
        } else {
            arsort($keysvalue);
        }
        $newArray = [];
        foreach (array_keys($keysvalue) as $k) {
            $newArray[] = $arr[$k];
        }
        return $newArray;
    }

    /**
     * 计划任务发奖并返回信件文字（get_thirty_reward($type, $value, $num, $uid, $version)：$value 为武将 / 装备 id 或魂魄数，$num 为数量）
     *
     * 照旧版：装备只发 1 件且信件写装备名、坐骑按 $num 为坐骑 id、装备碎片按 $num 为 id 与数量。
     */
    private static function cronReward(int $type, int $value, int $num, int $uid, int $version, string $method, int $now): string
    {
        $l = self::LANG_CRON;
        switch ($type) {
            case 1: // 元宝
                Member::addCurrency($uid, gold: $num);
                return $l[1] . $num . $l['cron_ge'];
            case 2: // 银币
                Member::addCurrency($uid, silver: $num);
                return $l[2] . $num . $l['cron_ge'];
            case 3: // 黄宝石
                TavernHandler::addJade($uid, 2, $num, 'cron_acttask', $method);
                return $l[3] . $num . $l['cron_ge'];
            case 4: // 紫宝石
                TavernHandler::addJade($uid, 3, $num, 'cron_acttask', $method);
                return $l[4] . $num . $l['cron_ge'];
            case 5: // 原魂
                Member::addCurrency($uid, soul: $num);
                return $l[5] . $num . $l['cron_ge'];
            case 6: // 玉石
                MemberJade::add($uid, $num, 'cron_acttask', $method, $now);
                return $l[6] . $num . $l['cron_ge'];
            case 7: // 声望
                Member::addReputation($uid, $num);
                return $l[7] . $num . $l['cron_ge'];
            case 8: // 装备
                $item = FestReward::equipInfo($num);
                if ($item !== null) {
                    Equipment::addFromTemplate($uid, $num);
                }
                return $l[8] . ($item['name'] ?? '') . '，';
            case 9: // 宝石碎片
                OwnGem::addChip($uid, $num, 'cron_acttask', $method, $now);
                return $l[9] . $num . $l['cron_ge'];
            case 10: // 突飞卡
                FreeLeap::add($uid, $num, $now);
                return $l[10] . $num . $l['cron_ge'];
            case 11: // 蓝宝石
                TavernHandler::addJade($uid, 1, $num, 'cron_acttask', $method);
                return $l[11] . $num . $l['cron_ge'];
            case 12: // 武将
                $gid = $num;
                if (General::ownsTemplate($uid, $gid)) {
                    $jade = FestReward::generalJade($gid);
                    if ($jade === null) {
                        return '';
                    }
                    TavernHandler::addJade($uid, 3, (int)$jade, 'cron_acttask', $method);
                    return $l['cron_change_purple'] . $jade . $l['cron_ge'];
                }
                $general = General::addGeneral($uid, $gid);
                if ($general === null) {
                    return '';
                }
                PubLog::add($uid, $gid, 1, 103, $now);
                return $l['cron_general'] . $general['name'];
            case 13: // 军令
                Member::addCurrency($uid, act: $num);
                return $l[13] . $num . $l['cron_ge'];
            case 14: // 坐骑
                $result = OwnMount::addMount($uid, $num, 0, 1, 'cron_acttask', $method, $now);
                return $result['status'] === 1 ? $l[14] . (MountTemp::ROWS[$num]['name'] ?? '') : '';
            case 15: // 武将碎片
                DB::execute(
                    'UPDATE `zc_sanguo_thirty` SET `own_chip` = `own_chip` + :num WHERE `uid` = :uid AND `version` = :version AND `deleted_at` IS NULL',
                    ['num' => $value, 'uid' => $uid, 'version' => $version]
                );
                return $l[15] . $value . $l['cron_ge'];
            case 16: // 装备碎片
                Equipment::addChip($uid, $num, $num, 'cron_acttask', $method, $now);
                return $l[16] . $value . $l['cron_ge'];
        }
        return '';
    }

    /**
     * @return array{gold: int, silver: int}
     */
    private static function balances(int $uid): array
    {
        $row = DB::table('member')->select('gold', 'silver')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        return ['gold' => (int)($row['gold'] ?? 0), 'silver' => (int)($row['silver'] ?? 0)];
    }
}
