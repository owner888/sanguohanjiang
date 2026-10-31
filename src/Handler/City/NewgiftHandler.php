<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;

use Hanjiang\Handler\Activity\MessageHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\Equip as EquipTemplate;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\NewgiftReward;
use Hanjiang\MasterData\Pub;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\Talent;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 新服大礼包（移植旧版 controllers/newgift.php + models/newgift_model.php，主城 member.php:_newgift，计划任务 cron::get_newgift_reward）
 *
 * 客户端（newgiftpop.js）调用：index / get_reward；主城 member.index 的 newgift / newgift_popo / newgift_starttime / newgift_msg
 * 取 memberInfo()。玩家注册当天起 30 天内每天登录（打开礼包界面）解锁当天的礼包（newgift_log：1 可领取、2 未达成、3 已领取），
 * 奖励取 MasterData NewgiftReward（1 银币、2 突飞卡、3 装备、4 军令、5 黄宝石、6 紫宝石、7 元宝、8 武将；第 7 / 15 / 30 天的武将
 * 已拥有时按酒馆价格 8 折给宝石，前 3 天的武将已拥有时不给）。活动结束后计划任务（NewgiftCron）把可领取未领的礼包自动发放并
 * 发信件。2014-02-21 之前注册的老玩家按 2014-02-21 起算（新服不会有）。
 *
 * 旧版先用 uc 服务器的开服时间判断「新服才开启」（开服早于 2014-02-21 时回 3）；新版服务器必然晚于该日期开服，按开启处理。
 * 照旧版保留的写法：列表与领取状态按下标合并（第 31 行奖励没有 type）；领取锁 newgift_lock_{uid}（60 秒）已存在时回 -7。
 * 与旧版的差别：newgift_log 以 uid + day 为唯一键（旧版无索引，并发首次进入会建出重复记录）；get_reward 在 Member::locked
 * （玩家行锁）里执行。领取状态的 Redis 缓存 {Ymd}newgift_{uid}（get_newgift_cache 的调用方都不在客户端流程里）不移植。
 */
final class NewgiftHandler
{
    /** 活动开始时间（2014/2/21），之前注册的玩家从这天起算 */
    public const int START = 1392912000;
    /** 活动时长（30 天） */
    private const int END = 2592000;
    /** 领取锁 key 前缀 */
    private const string LOCK_KEY = 'newgift_lock_';
    /** 旧版语言包 gift_lang.php */
    private const array LANG = [
        'gift_error1' => '获取连续登陆奖励信息失败',
        'gift_error2' => '传递参数失败',
        'gift_error4' => '您不能重复领取奖励',
        'gift_error5' => '活动已结束',
        'gift_error8' => '领取奖励失败，请稍后再试！',
        'gift_reward_equip' => '主公，今日登录奖励武器',
        'gift_reward_general' => '主公，今日登录奖励武将',
        'gift_reward' => '！请速速领取！',
    ];
    /** 系统信件标题（message_model->send_message type 1） */
    private const string LANG_SYSTEM_TITLE = '系统通知';

    /**
     * 初始化接口: c=newgift&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = time();
        $return = ['status' => 0, 'popo' => 1];
        $member = self::member($uid);
        if ($member === null) {
            return $return;
        }
        $act = self::activity($member['jointime'], $now);
        if (!$act['activity']) {
            // 活动已结束
            $return['status'] = 2;
            $return['msg'] = self::LANG['gift_error5'];
            return $return;
        }
        $result = self::rewardLog($uid);
        if ($result === []) {
            // 初始化 30 天的记录：新用户（或老用户的第一天）第 1 天可领取，其余未达成；老用户当天可领取
            for ($i = 1; $i <= 30; $i++) {
                $available = $member['jointime'] > self::START || $act['day'] === 1 ? $i === 1 : $i === $act['day'];
                self::addRewardLog($uid, $i, $available ? 1 : 2, $now);
            }
        }
        // 判断今天是否登录过：今天的礼包还是未达成时改为可领取
        $today = DB::table('newgift_log')->select('status')->where('uid', $uid)->where('day', $act['day'])->whereNull('deleted_at')->first();
        if ($today !== null && (int)$today['status'] === 2) {
            self::updateRewardLog($uid, $act['day'], 1, $now);
        }
        return self::listInfo($uid, $member, $act, $return);
    }

    /**
     * 领取奖励: c=newgift&m=get_reward&id=（第几天）
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        $return = ['status' => 0, 'popo' => 1];
        // 增加锁标志
        $lock = self::LOCK_KEY . $uid;
        if (!Redis::instance()->set($lock, '1', ['nx', 'ex' => 60])) {
            $return['status'] = -7;
            $return['msg'] = self::LANG['gift_error2'];
            return $return;
        }
        try {
            return Member::locked($uid, static function () use ($uid, $id, $return): array {
                $now = time();
                if (!$id || $id > 30) {
                    $return['status'] = -1;
                    $return['msg'] = self::LANG['gift_error2'];
                    return $return;
                }
                $member = self::member($uid);
                $act = $member === null ? ['activity' => false] : self::activity($member['jointime'], $now);
                if (!$act['activity']) {
                    $return['status'] = 2;
                    $return['msg'] = self::LANG['gift_error5'];
                    return $return;
                }
                $log = DB::table('newgift_log')->select('status')->where('uid', $uid)->where('day', $id)->whereNull('deleted_at')->first();
                if ($log === null) {
                    $return['status'] = -8;
                    $return['msg'] = self::LANG['gift_error8'];
                    return $return;
                }
                if (in_array((int)$log['status'], [2, 3], true)) {
                    // 不能重复领取（或未达成）
                    $return['status'] = -2;
                    $return['msg'] = self::LANG['gift_error4'];
                    return $return;
                }
                $reward = NewgiftReward::ROWS[$id] ?? null;
                if ($reward === null) {
                    $return['status'] = -1;
                    $return['msg'] = self::LANG['gift_error1'];
                    return $return;
                }
                // 更新成已领取状态
                if (self::updateRewardLog($uid, $id, 3, $now, 1) !== 1) {
                    $return['status'] = -1;
                    $return['msg'] = self::LANG['gift_error8'];
                    return $return;
                }
                self::grant($uid, $reward, $now, 'newgift', 'get_reward');
                return self::listInfo($uid, $member, $act, $return);
            });
        } finally {
            // 解锁
            Redis::del($lock);
        }
    }

    /**
     * 主城的新服礼包提示（member.php:_newgift）
     *
     * @return array{newgift: int, newgift_popo: int, newgift_starttime: int, newgift_msg: list<string>|string}
     */
    public static function memberInfo(int $uid, int $jointime, int $now): array
    {
        $return = ['newgift' => 0, 'newgift_popo' => 1, 'newgift_starttime' => 0, 'newgift_msg' => ''];
        // 30 天以内才开启
        if (!self::activity($jointime, $now)['activity']) {
            return $return;
        }
        $return['newgift'] = 1;
        foreach (self::rewardLog($uid) as $val) {
            if ($val['status'] !== 1) {
                $return['newgift_popo'] = 0;
            } else {
                $return['newgift_popo'] = 1;
                break;
            }
        }
        // 最后一次领奖不是今天时显示提醒
        $last = DB::table('newgift_log')->where('uid', $uid)->where('status', 3)->whereNull('deleted_at')->orderBy('dateline', 'DESC')->value('dateline');
        if ($last !== null && date('Ymd', $now) !== date('Ymd', (int)$last)) {
            $return['newgift_popo'] = 1;
        }
        // 新服礼包的推送时间（十点推送）
        $return['newgift_starttime'] = $jointime <= self::START ? self::START + 36000 : strtotime(date('Ymd', $jointime)) + 36000;
        $return['newgift_msg'] = self::msg();
        return $return;
    }

    /**
     * 计划任务：活动结束的玩家自动领取可领取的礼包并发信件（cron::get_newgift_reward）
     *
     * @return int 发放的玩家数
     */
    public static function autoReward(int $now): int
    {
        $uids = array_map('intval', array_column(
            DB::query('SELECT DISTINCT `uid` FROM `zc_sanguo_newgift_log` WHERE `status` = 1 AND `deleted_at` IS NULL'),
            'uid'
        ));
        $count = 0;
        foreach ($uids as $uid) {
            $member = self::member($uid);
            if ($member === null || self::activity($member['jointime'], $now)['activity']) {
                continue;
            }
            $sent = Member::locked($uid, static function () use ($uid, $now): ?string {
                $sum = ['silver' => 0, 'freeleap' => 0, 'act' => 0, 'jade2' => 0, 'jade3' => 0, 'gold' => 0];
                $equip = $generals = '';
                $granted = false;
                foreach (self::rewardLog($uid) as $val) {
                    if ($val['status'] !== 1) {
                        continue;
                    }
                    $reward = NewgiftReward::ROWS[$val['day']] ?? null;
                    if ($reward === null || self::updateRewardLog($uid, $val['day'], 3, $now, 1) !== 1) {
                        continue;
                    }
                    $granted = true;
                    $got = self::grant($uid, $reward, $now, 'cron', 'get_newgift_reward');
                    foreach ($sum as $key => $value) {
                        $sum[$key] = $value + $got[$key];
                    }
                    $equip .= $got['equip'];
                    $generals .= $got['generals'];
                }
                if (!$granted) {
                    return null;
                }
                $message = '新服大礼包活动已结束，您未领取的开服奖励，系统已自动帮您领取，';
                $names = ['silver' => '银币', 'freeleap' => '突飞卡', 'act' => '军令', 'jade2' => '黄宝石', 'jade3' => '紫宝石', 'gold' => '元宝'];
                foreach (['silver', 'freeleap'] as $key) {
                    if ($sum[$key]) {
                        $message .= $names[$key] . $sum[$key] . '、';
                    }
                }
                if ($equip !== '') {
                    $message .= '装备' . $equip . '、';
                }
                foreach (['act', 'jade2', 'jade3', 'gold'] as $key) {
                    if ($sum[$key]) {
                        $message .= $names[$key] . $sum[$key] . '、';
                    }
                }
                if ($generals !== '') {
                    $message .= '武将' . $generals;
                }
                return $message . '!请您查收!';
            });
            if ($sent !== null) {
                MessageHandler::sendSystemMessage([$uid], 1, self::LANG_SYSTEM_TITLE, $sent);
                $count++;
            }
        }
        return $count;
    }

    /**
     * 礼包列表、7 / 15 / 30 天的武将、推送时间与提醒状态（index / get_reward 共用的返回部分）
     *
     * @param array{uid: int, jointime: int} $member
     * @param array{activity: bool, day: int, cd: int} $act
     * @param array<string, mixed> $return
     * @return array<string, mixed>
     */
    private static function listInfo(int $uid, array $member, array $act, array $return): array
    {
        $result = self::rewardLog($uid);
        $reward = array_values(NewgiftReward::ROWS);
        // 合并用户状态信息和奖励信息（按下标）
        foreach ($reward as $key => $val) {
            if (isset($result[$key])) {
                $reward[$key]['type'] = $result[$key]['status'];
            }
        }
        // 找出装备和武将信息
        foreach ($reward as $key => $val) {
            for ($i = 1; $i <= 4; $i++) {
                if ($val['gettype' . $i] === 3) {
                    $reward[$key]['information'] = EquipTemplate::ROWS[$val['getvalue' . $i]] ?? [];
                }
                if ($val['gettype' . $i] === 8) {
                    $general = GeneralTemplate::ROWS[$val['getvalue' . $i]] ?? [];
                    $reward[$key]['information'] = $general;
                    $reward[$key]['information']['talent'] = Talent::ROWS[$general['talentid'] ?? 0] ?? [];
                    $reward[$key]['information']['skill'] = Skill::ROWS[$general['skillid'] ?? 0] ?? [];
                    // 前三天的武将是否折旧（已拥有）
                    if (in_array($key, [0, 1, 2], true)) {
                        $reward[$key]['renew'] = General::ownsTemplate($uid, $val['getvalue' . $i]) ? 1 : 0;
                    }
                }
            }
            if (in_array($key, [6, 14, 29], true)) {
                // 已拥有该武将时以紫 / 黄宝石 8 折折旧
                $reward[$key]['renew'] = 0;
                if (General::ownsTemplate($uid, $val['getvalue4'])) {
                    $reward[$key]['renew'] = 1;
                    $pub = Pub::ROWS[$val['getvalue4']] ?? null;
                    $reward[$key]['jade_type'] = $pub['paytype'] ?? null;
                    $reward[$key]['jade_price'] = $pub === null ? 0 : $pub['price'] * 0.8;
                }
            }
        }
        // 奖励表中第 7 / 15 / 30 天的武将
        $return['info'] = [];
        foreach ([7, 15, 30] as $i => $day) {
            $info = NewgiftReward::ROWS[$day] ?? [];
            $return['info'][$i] = GeneralTemplate::ROWS[$info['getvalue4'] ?? 0] ?? $info;
        }
        // 推送消息
        $return['starttime'] = $member['jointime'] <= self::START ? self::START : $member['jointime'];
        // 提醒状态
        foreach ($result as $val) {
            if ($val['status'] !== 1) {
                $return['popo'] = 0;
            } else {
                $return['popo'] = 1;
                break;
            }
        }
        $after = DB::table('member')->select('silver', 'gold', 'act')->where('uid', $uid)->first();
        $return['list'] = $reward;
        $return['status'] = 1;
        $return['cd'] = $act['cd'];
        $return['all_silver'] = (int)$after['silver'];
        $return['all_gold'] = (int)$after['gold'];
        $return['all_act'] = (int)$after['act'];
        return $return;
    }

    /**
     * 发一个礼包（1 银币、2 突飞卡、3 装备、4 军令、5 黄宝石、6 紫宝石、7 元宝、8 武将），返回各类数量（计划任务拼信件用）
     *
     * @param array<string, mixed> $reward
     * @return array{silver: int, freeleap: int, act: int, jade2: int, jade3: int, gold: int, equip: string, generals: string}
     */
    private static function grant(int $uid, array $reward, int $now, string $class, string $method): array
    {
        $got = ['silver' => 0, 'freeleap' => 0, 'act' => 0, 'jade2' => 0, 'jade3' => 0, 'gold' => 0, 'equip' => '', 'generals' => ''];
        for ($i = 1; $i <= 4; $i++) {
            $value = $reward['getvalue' . $i];
            switch ($reward['gettype' . $i]) {
                case 1:
                    Member::addCurrency($uid, silver: $value);
                    $got['silver'] += $value;
                    break;
                case 2:
                    FreeLeap::add($uid, $value, $now);
                    $got['freeleap'] += $value;
                    break;
                case 3:
                    $item = EquipTemplate::ROWS[$value] ?? null;
                    if ($item !== null) {
                        Equipment::addFromTemplate($uid, $value);
                        $got['equip'] .= $item['name'];
                    }
                    break;
                case 4:
                    Member::addCurrency($uid, act: $value);
                    $got['act'] += $value;
                    break;
                case 5:
                    TavernHandler::addJade($uid, 2, $value, $class, $method);
                    $got['jade2'] += $value;
                    break;
                case 6:
                    TavernHandler::addJade($uid, 3, $value, $class, $method);
                    $got['jade3'] += $value;
                    break;
                case 7:
                    Member::addCurrency($uid, gold: $value);
                    $got['gold'] += $value;
                    break;
                case 8:
                    if (!General::ownsTemplate($uid, $value)) {
                        $general = General::addGeneral($uid, $value);
                        $got['generals'] .= $general['name'] ?? '';
                    } elseif (!in_array($reward['id'], [1, 2, 3], true)) {
                        // 已拥有：以紫 / 黄宝石 8 折折旧（前 3 天的不给）
                        $pub = Pub::ROWS[$value] ?? null;
                        if ($pub !== null) {
                            $jade = (int)($pub['price'] * 0.8);
                            TavernHandler::addJade($uid, $pub['paytype'], $jade, $class, $method);
                            // 旧版计划任务的信件把折旧宝石都记在黄宝石里
                            $got['jade2'] += $jade;
                        }
                    }
                    break;
            }
        }
        return $got;
    }

    /**
     * @return array{uid: int, jointime: int}|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        $row = DB::table('member')->select('uid', 'jointime')->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : ['uid' => (int)$row['uid'], 'jointime' => (int)$row['jointime']];
    }

    /**
     * 活动状态（_init_activity：开始时间为 2014-02-21 或注册当天 0 点；30 天内开启；day 为第几天，cd 为剩余秒数）
     *
     * @return array{activity: bool, day: int, cd: int}
     */
    private static function activity(int $jointime, int $now): array
    {
        $start = $jointime < self::START ? self::START : strtotime(date('Ymd', $jointime));
        return [
            'activity' => $now <= $start + self::END,
            'day' => (int)ceil(($now - $start) / 86400),
            'cd' => self::END + $start - $now,
        ];
    }

    /**
     * 用户的全部记录（get_reward_log：按 id 顺序）
     *
     * @return list<array{day: int, status: int, dateline: int}>
     */
    private static function rewardLog(int $uid): array
    {
        $list = [];
        foreach (DB::table('newgift_log')->select('day', 'status', 'dateline')->where('uid', $uid)->whereNull('deleted_at')->orderBy('id', 'ASC')->get() as $row) {
            $list[] = ['day' => (int)$row['day'], 'status' => (int)$row['status'], 'dateline' => (int)$row['dateline']];
        }
        return $list;
    }

    private static function addRewardLog(int $uid, int $day, int $status, int $now): void
    {
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_newgift_log` (`uid`, `day`, `dateline`, `status`) VALUES (:uid, :day, :now, :status)',
            ['uid' => $uid, 'day' => $day, 'now' => $now, 'status' => $status]
        );
    }

    /**
     * 更新记录（update_reward_log：1 可领取 / 3 已领取）；$from 不为 0 时只更新当前为该状态的记录（并发抢占）
     */
    private static function updateRewardLog(int $uid, int $day, int $status, int $now, int $from = 0): int
    {
        $query = DB::table('newgift_log')->where('uid', $uid)->where('day', $day)->whereNull('deleted_at');
        if ($from !== 0) {
            $query->where('status', $from);
        }
        return $query->update(['dateline' => $now, 'status' => $status]) > 0 ? 1 : 0;
    }

    /**
     * 推送消息（get_msg_cache：第 2 天的装备与第 7 / 15 / 30 天的武将）
     *
     * @return list<string>
     */
    private static function msg(): array
    {
        $rows = array_values(NewgiftReward::ROWS);
        $name = static fn (array $tpl, int $index): string => (string)($tpl[$rows[$index]['getvalue4'] ?? 0]['name'] ?? '');
        return [
            self::LANG['gift_reward_equip'] . $name(EquipTemplate::ROWS, 1) . self::LANG['gift_reward'],
            self::LANG['gift_reward_general'] . $name(GeneralTemplate::ROWS, 6) . self::LANG['gift_reward'],
            self::LANG['gift_reward_general'] . $name(GeneralTemplate::ROWS, 14) . self::LANG['gift_reward'],
            self::LANG['gift_reward_general'] . $name(GeneralTemplate::ROWS, 29) . self::LANG['gift_reward'],
        ];
    }
}
