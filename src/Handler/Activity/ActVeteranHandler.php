<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\ActVeteranReward;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FestLock;
use Hanjiang\Model\FestReward;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\MemberStone;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\PubLog;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 老兵召回（移植旧版 controllers/act_veteran.php + models/actveteran_model.php，以及 general_inherit.php 的免费至尊继承）
 *
 * 活动时间与版本取 ActivityTime 的 recall（旧版数据 2016-12-31 已结束）。奖励取 MasterData ActVeteranReward 的第一行
 * （level 等级要求、type1 ~ 3 / value1 ~ 3：1 银币 2 黄宝石 3 紫宝石 4 原魂 5 玉石 6 声望 7 装备 8 宝石碎片 9 突飞卡 10 坐骑
 * 11 精炼石 12 五彩石 13 上古血玉 14 军令 15 元宝 16 武将 17 一次至尊继承）。领奖条件：倒数第二条每日计划任务日志（member_log，
 * 即上一次之前的那次登录）距活动开始已满 60 天、且主公达到 level 级。领过奖的玩家在活动期间可免费至尊继承一次（act_veteran_log.is_inherit，
 * GeneralInheritHandler 调 recallFree / useRecallInherit）；类型 17 本身不发放任何东西（旧版发奖分支已注释）。
 * 客户端 recallpop.js 调用 index、get_reward。当前时间取 BossClock。
 *
 * 与旧版的差别：领奖锁改为 Redis SET NX；领奖状态用条件更新（is_reward 0 → 1）成功后才发奖（旧版先发奖后改状态，并发可重复领）；
 * 领奖记录 uid + version 唯一（get_reward_log 没有记录时 INSERT IGNORE 建档）。
 */
final class ActVeteranHandler
{
    public const string ACTIVITY = 'recall';

    /** 领取奖励 lock */
    private const string LOCK_KEY = 'veteran_lock_';
    /** 时间间隔区间：2个月 */
    private const int LIMIT_TIME = 60 * 24 * 60 * 60;

    /** actveteran_lang.php + chat_lang.php */
    private const array LANG = [
        'actveteran_error_notopen' => '活动未开启!',
        'actveteran_error_basereward' => '基础奖励表错误!',
        'actveteran_error_fast' => '操作不要太频繁!',
        'actveteran_error_rewarded' => '奖励已经领取!',
        'actveteran_error_level' => '等级不足！',
        'actveteran_error_noreward' => '条件不满足！',
        'actveteran_lev_info' => '%s后未登录过游戏且等级达到%s级以上的玩家',
        'actveteran_time' => '%s至%s',
        'chat_veteran_get' => '恭喜【%s】在老兵召回活动中获得装备-%s',
        'chat_veteran_mount_get' => '恭喜【%s】在老兵召回活动中获得名马-%s',
    ];

    /** 奖励类型名称（actveteran_type_1 ~ 17） */
    private const array TYPE_NAME = [
        1 => '银币', 2 => '黄宝石', 3 => '紫宝石', 4 => '原魂', 5 => '玉石', 6 => '声望', 7 => '装备', 8 => '宝石碎片', 9 => '突飞卡',
        10 => '坐骑', 11 => '精炼石', 12 => '五彩石', 13 => '上古血玉', 14 => '军令', 15 => '元宝', 16 => '武将', 17 => '一次至尊继承',
    ];

    /**
     * 首页: c=act_veteran&m=index
     *
     * -2 奖励表错误；-1 功能未开启；0 失败；1 成功。is_reward：0 不能领取，1 可以领取，2 已经领取
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::init($now);
        if ($act === null || $uid <= 0) {
            $return['status'] = -1;
            $return['msg'] = self::LANG['actveteran_error_notopen'];
            return $return;
        }
        // 查看基础表信息
        $baseReward = self::baseReward();
        if ($baseReward === null) {
            $return['status'] = -2;
            $return['msg'] = self::LANG['actveteran_error_basereward'];
            return $return;
        }
        $reward = $tmp = [];
        // 奖励信息处理（$tmp 跨奖励复用，同旧版）
        for ($i = 1; $i <= 3; $i++) {
            if ($baseReward['type' . $i] > 0 && $baseReward['value' . $i] > 0) {
                $tmp['type'] = $baseReward['type' . $i];
                $tmp['value'] = $baseReward['value' . $i];
                $tmp['name'] = self::TYPE_NAME[$tmp['type']] ?? false;
                // 装备 or 坐骑信息
                $rewardInfo = self::rewardInfo($uid, $tmp['type'], $tmp['value'], 0, $now);
                if ($rewardInfo['info'] !== []) {
                    $tmp['info'] = $rewardInfo['info'];
                } else {
                    unset($tmp['info']);
                }
                $reward[] = $tmp;
            } else {
                $return['status'] = -2;
                $return['msg'] = self::LANG['actveteran_error_basereward'];
                return $return;
            }
        }
        // 活动时间、玩家等级提示
        $actInfo = [
            'act_time' => sprintf(self::LANG['actveteran_time'], date('Y-m-d', $act['start']), date('Y-m-d', $act['end'])),
            'act_level' => sprintf(self::LANG['actveteran_lev_info'], date('Y-m-d', (int)strtotime((string)$baseReward['nologin_stime'])), $baseReward['level']),
        ];
        // 验证是否已经领取奖励
        $isReward = self::rewardLog($uid, $act['version'], $now)['is_reward'] === 1 ? 2 : 0;
        // 可领取状态
        if ($isReward !== 2 && self::qualified($uid, $act['start'], $baseReward['level'])) {
            $isReward = 1;
        }
        $return['status'] = 1;
        $return['reward'] = $reward;
        $return['is_reward'] = $isReward;
        $return['time_cd'] = $act['cd'];
        $return['act_info'] = $actInfo;
        return $return;
    }

    /**
     * 领取奖励: c=act_veteran&m=get_reward
     *
     * -2 奖励表错误；-1 功能未开启；0 失败；1 成功；2 操作不要太频繁；3 奖励已经领取；4 等级不足；5 条件不满足
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $lockKey = self::LOCK_KEY . $uid;
        if ($uid <= 0 || !FestLock::acquire($lockKey)) {
            return ['status' => 2, 'msg' => self::LANG['actveteran_error_fast']];
        }
        try {
            $act = self::init($now);
            if ($act === null) {
                return ['status' => -1, 'msg' => self::LANG['actveteran_error_notopen']];
            }
            $baseReward = self::baseReward();
            if ($baseReward === null) {
                return ['status' => -2, 'msg' => self::LANG['actveteran_error_basereward']];
            }
            // 验证是否已经领取奖励
            if (self::rewardLog($uid, $act['version'], $now)['is_reward'] === 1) {
                return ['status' => 3, 'msg' => self::LANG['actveteran_error_rewarded']];
            }
            // 验证是否达到领取奖励的等级
            if ($baseReward['level'] <= 0) {
                return ['status' => -2, 'msg' => self::LANG['actveteran_error_basereward']];
            }
            if (self::level($uid) < $baseReward['level']) {
                return ['status' => 4, 'msg' => self::LANG['actveteran_error_level']];
            }
            // 没有达到领取的条件
            if (!self::qualified($uid, $act['start'], $baseReward['level'])) {
                return ['status' => 5, 'msg' => self::LANG['actveteran_error_noreward']];
            }
            $reward = [];
            for ($i = 1; $i <= 3; $i++) {
                if ($baseReward['type' . $i] > 0 && $baseReward['value' . $i] > 0) {
                    $reward[] = ['type' => $baseReward['type' . $i], 'value' => $baseReward['value' . $i]];
                }
            }
            if ($reward === []) {
                return ['status' => -2, 'msg' => self::LANG['actveteran_error_basereward']];
            }
            // 更新领奖状态（条件更新，并发只领一次）
            $updated = DB::table('act_veteran_log')->where('uid', $uid)->where('version', $act['version'])->where('is_reward', 0)
                ->whereNull('deleted_at')->update(['is_reward' => 1, 'dateline' => $now]);
            if ($updated <= 0) {
                return ['status' => 3, 'msg' => self::LANG['actveteran_error_rewarded']];
            }
            // 发奖
            foreach ($reward as $value) {
                self::rewardInfo($uid, $value['type'], $value['value'], 1, $now);
            }
            $member = DB::table('member')->select('silver', 'gold')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
            return [
                'status' => 1,
                'all_silver' => (int)($member['silver'] ?? 0),
                'all_gold' => (int)($member['gold'] ?? 0),
            ];
        } finally {
            FestLock::release($lockKey);
        }
    }

    /**
     * 武将继承用：老兵召回活动开启（结束时间未到）且已领奖、还没用过免费至尊继承时返回活动版本，否则 null
     *
     * 旧版 general_inherit 构造函数 activity_status('recall') + actveteran_model->get_reward_log（没有记录时建档）。
     */
    public static function recallFree(int $uid, int $now): ?int
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null || (int)$info['endtime'] - $now <= 0) {
            return null;
        }
        $version = (int)$info['version'];
        $log = self::rewardLog($uid, $version, $now);
        return $log['is_reward'] === 1 && $log['is_inherit'] <= 0 ? $version : null;
    }

    /**
     * 武将继承用：标记已使用老兵召回的免费至尊继承（update_reward_log is_inherit = 1）
     */
    public static function useRecallInherit(int $uid, int $version, int $now): void
    {
        DB::table('act_veteran_log')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')
            ->update(['is_inherit' => 1, 'dateline' => $now]);
    }

    /**
     * 活动信息（构造函数）：activity_status 开启时返回版本、起止时间与倒计时；否则 null
     *
     * @return array{version: int, start: int, end: int, cd: int}|null
     */
    private static function init(int $now): ?array
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null) {
            return null;
        }
        $end = (int)$info['endtime'];
        return ['version' => (int)$info['version'], 'start' => (int)$info['starttime'], 'end' => $end, 'cd' => max(0, $end - $now)];
    }

    /**
     * 基础奖励表（get_base_reward：第一行）
     *
     * @return array<string, int>|null
     */
    private static function baseReward(): ?array
    {
        $rows = array_values(ActVeteranReward::ROWS);
        return $rows[0] ?? null;
    }

    /**
     * 领奖记录（get_reward_log：没有记录时建档）
     *
     * @return array{is_reward: int, is_inherit: int}
     */
    private static function rewardLog(int $uid, int $version, int $now): array
    {
        $row = DB::table('act_veteran_log')->select('is_reward', 'is_inherit')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first();
        if ($row === null) {
            // 添加日志
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_act_veteran_log` (`uid`, `version`, `is_reward`, `is_inherit`, `dateline`) VALUES (:uid, :version, 0, 0, :now)',
                ['uid' => $uid, 'version' => $version, 'now' => $now]
            );
            $row = DB::table('act_veteran_log')->select('is_reward', 'is_inherit')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first()
                ?? ['is_reward' => 0, 'is_inherit' => 0];
        }
        return ['is_reward' => (int)$row['is_reward'], 'is_inherit' => (int)$row['is_inherit']];
    }

    /**
     * 是否满足领奖条件：倒数第二条每日日志（get_memlog 取最近 2 条的第 2 条）距活动开始满 60 天，且主公达到等级
     */
    private static function qualified(int $uid, int $start, int $level): bool
    {
        $logs = DB::table('member_log')->select('dateline')->where('uid', $uid)->whereNull('deleted_at')->orderBy('dateline', 'DESC')->limit(2)->get();
        if (!isset($logs[1])) {
            return false;
        }
        return $start - (int)$logs[1]['dateline'] >= self::LIMIT_TIME && self::level($uid) >= $level;
    }

    private static function level(int $uid): int
    {
        return (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level') ?? 0);
    }

    /**
     * 获取奖励信息（装备、坐骑、武将）或发奖（get_reward_info；$view 0 只取信息，1 发奖）
     *
     * @return array{info: array<string, mixed>}
     */
    private static function rewardInfo(int $uid, int $type, int $num, int $view, int $now): array
    {
        $return = ['info' => []];
        if ($type <= 0 || $num <= 0) {
            return $return;
        }
        switch ($type) {
            case 1: // 银币
                if ($view) {
                    Member::addCurrency($uid, silver: $num);
                }
                break;
            case 2: // 黄宝石
                if ($view) {
                    TavernHandler::addJade($uid, 2, $num, 'act_veteran', 'get_reward');
                }
                break;
            case 3: // 紫宝石
                if ($view) {
                    TavernHandler::addJade($uid, 3, $num, 'act_veteran', 'get_reward');
                }
                break;
            case 4: // 原魂
                if ($view) {
                    Member::addCurrency($uid, soul: $num);
                }
                break;
            case 5: // 玉石
                if ($view) {
                    MemberJade::add($uid, $num, 'act_veteran', 'get_reward', $now);
                }
                break;
            case 6: // 声望
                if ($view) {
                    Member::addReputation($uid, $num);
                }
                break;
            case 7: // 装备
                $item = FestReward::equipInfo($num);
                if ($item !== null) {
                    if ($view) {
                        Equipment::addFromTemplate($uid, $num);
                        // 发布送广播到小喇叭（只发紫装以上品质的装备）
                        if ((int)$item['quality'] >= 5) {
                            $sender = Chat::sender($uid);
                            $name = Chat::quality($item['quality'], (string)$item['name']);
                            Chat::sendToHorn(sprintf(self::LANG['chat_veteran_get'], (string)($sender['nickname'] ?? ''), $name), $sender);
                        }
                    }
                    $return['info'] = $item;
                }
                break;
            case 8: // 魔石碎片
                if ($view) {
                    OwnGem::addChip($uid, $num, 'act_veteran', 'get_reward', $now);
                }
                break;
            case 9: // 突飞
                if ($view) {
                    FreeLeap::add($uid, $num, $now);
                }
                break;
            case 10: // 坐骑
                $mount = MountTemp::ROWS[$num] ?? null;
                if ($mount !== null) {
                    if ($view) {
                        $result = OwnMount::addMount($uid, $num, 0, 1, 'act_veteran', 'get_reward', $now);
                        // 如果获得的坐骑品级大于5 发送广播到小喇叭
                        if ($result['status'] === 1 && (int)$mount['quality'] >= 5) {
                            $sender = Chat::sender($uid);
                            $qualityStr = Chat::quality($mount['quality'], (string)$mount['name']);
                            Chat::sendToHorn(sprintf(self::LANG['chat_veteran_mount_get'], (string)($sender['nickname'] ?? ''), $qualityStr), $sender);
                        }
                    }
                    $return['info'] = $mount;
                }
                break;
            case 11: // 精炼石
            case 12: // 五彩石
            case 13: // 上古血玉
                if ($view) {
                    MemberStone::add($uid, $num, $type - 10, 'act_veteran', 'get_reward', $now);
                }
                break;
            case 14: // 军令（add_act(1, n)）
                if ($view) {
                    Member::addCurrency($uid, act: $num);
                }
                break;
            case 15: // 元宝
                if ($view) {
                    Member::addCurrency($uid, gold: $num);
                }
                break;
            case 16: // 武将（已拥有时添加失败，不补偿）
                $general = FestReward::generalInfo($num);
                if ($view) {
                    $added = General::ownsTemplate($uid, $num) ? null : General::addGeneral($uid, $num);
                    if ($added !== null) {
                        // 添加酒馆日志(武将id, 拥有状态, 获取方式)
                        PubLog::add($uid, $num, 1, 8, $now);
                        $star = Chat::starWord((int)($general['star'] ?? 0));
                        if ($star !== null) {
                            // 聊天框发送消息
                            $sender = Chat::sender($uid);
                            $chat = Chat::getMessageStr(52, ['star_general' => $star, 'general' => $general['name']], $sender);
                            Chat::sendToAll($chat, Chat::TYPE_SYSTEM, 0, 0, $sender);
                        }
                    }
                }
                $return['info'] = $general;
                break;
            case 17: // 至尊继承（旧版发奖分支已注释：领奖后由武将继承按 act_veteran_log 判断）
                break;
        }
        return $return;
    }
}
