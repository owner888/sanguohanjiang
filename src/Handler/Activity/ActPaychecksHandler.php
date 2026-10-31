<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Cross\Server;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\ActPaychecksReward;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\FestLock;
use Hanjiang\Model\Member;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 招募战友领工资（移植旧版 controllers/act_paychecks.php + models/actpaychecks_model.php，计划任务
 * cron_acttask.php:action_paychecks / action_paychecks_reward，充值记账 pay.php:pay_act_init）
 *
 * 活动时间与版本取 ActivityTime 的 paychecks（旧版数据已结束）。50 级开放。邀请码沿用邀请好友（InvitationHandler 的
 * invitation_code，没有时向 uc 取码）。计划任务 action_paychecks（旧版注释「半小时执行一次」）在活动开始后到结束 10 小时内
 * 重建 act_paychecks：每个邀请者在活动期间邀请到的、活动开始后注册且达到 80 级的好友（邀请者本人 50 级以上）计入 invite_num，
 * 这些好友本期的充值元宝（act_paychecks_order）合计 gold、工资 paychecks = gold × 0.1。达到 MasterData ActPaychecksReward
 * 的人数可领奖（type 1 黄宝石、2 紫宝石、3 元宝）；活动结束后 1 小时内计划任务 action_paychecks_reward 补发未领的奖励并发工资。
 * 客户端 paycheckspop.js 调用 index、get_reward（id）。当前时间取 BossClock。
 *
 * 统计来源：旧版 action_paychecks 调 uc_model->get_user_data_by_cron / get_codedata（s1 uc_model 里没有这两个方法，旧版这里会
 * 致命错误，act_paychecks 始终为空）与跨服 userinvite_model；新版按调用方与表结构还原为读进程内 uc 的邀请记录
 * uc_invitation_log（本服被邀请者）。依赖未移植：其他服的被邀请者（跨服查询等级与充值 adminapi get_base_info / get_pay_data，
 * 多服部署才需要）。
 * 与旧版的差别：领奖锁改为 Redis SET NX；领取记录 INSERT IGNORE，插入成功才发奖（并发只发一次）；工资记录 uid + version 唯一。
 */
final class ActPaychecksHandler
{
    public const string ACTIVITY = 'paychecks';

    /** 返回工资百分比 */
    private const float CHECK = 0.1;
    /** 活动开启等级 */
    private const int OPEN_LEVEL = 50;
    /** 被邀请好友需达到的等级（计划任务） */
    private const int MIN_LEVEL = 80;
    /** 领奖锁（旧版 key 后接 uid） */
    private const string LOCK_KEY = 'paychecks_get_reward';

    /** actpaychecks_lang.php + cron_lang.php */
    private const array LANG = [
        'activity_not_open' => '活动未开启',
        'param_error' => '参数错误',
        'reward_error' => '获取奖励错误',
        'not_active' => '条件未达成',
        'already_action' => '已经领取过奖励',
        'faster_error' => '操作太快了',
        'level_error' => '50级开启此活动',
        'cron_paychecks_end' => '亲爱的玩家！招募战友领工资活动已结束，您未领取奖励系统已自动帮您领取，',
        'cron_paychecks_end2' => '亲爱的玩家！招募战友领工资活动已结束，',
        'cron_paychecks_get' => '您的战友充值额度为%s元宝，可领工资为%s元宝。系统已为您发放工资奖励，',
        'cron_get_reward' => '获得奖励：',
        'cron_yellow' => '黄宝石',
        'cron_purple' => '紫宝石',
        'cron_sycee' => '元宝',
        'cron_check' => '请查收!',
        'message_system_title' => '系统通知',
    ];

    /**
     * 首页: c=act_paychecks&m=index
     *
     * -3 等级不足；-2 奖励列表错误；-1 活动未开启；0 失败；1 成功
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $act = self::init($now);
        if ($act === null || $uid <= 0) {
            $return['status'] = -1;
            $return['msg'] = self::LANG['activity_not_open'];
            return $return;
        }
        if (self::level($uid) < self::OPEN_LEVEL) {
            $return['status'] = -3;
            $return['msg'] = self::LANG['level_error'];
            return $return;
        }
        // 获取邀请码
        $codeInfo = InvitationHandler::codeInfo($uid) ?? InvitationHandler::createCodeInfo($uid);
        $number = $payGold = $payCheck = 0;
        // 获取邀请人数(查询填写我的邀请码的人数)
        $inviteData = self::inviteInfo($act['version'], $uid);
        if ($inviteData !== null) {
            $number = $inviteData['invite_num'];
            $payGold = $inviteData['gold'];
            if ($payGold > 0) {
                $payCheck = self::num($payGold * self::CHECK);
            }
        }
        // 处理奖励
        $rewardList = array_values(ActPaychecksReward::ROWS);
        if ($rewardList === []) {
            $return['status'] = -2;
            $return['msg'] = self::LANG['reward_error'];
            return $return;
        }
        $myReward = self::rewardLog($uid, $act['version']);
        foreach ($rewardList as $key => $value) {
            // 0 不可领取；1 已完成未领取；2 已领取
            $rewardList[$key]['completed'] = $number >= $value['count'] ? 1 : 0;
            if (isset($myReward[$value['id']])) {
                $rewardList[$key]['completed'] = 2;
            }
        }
        // 邀请按钮状态：可以被邀请
        $return['is_act'] = $codeInfo['status'] === 0 ? 1 : 0;
        $return['status'] = 1;
        $return['code'] = $codeInfo['code'];
        $return['number'] = $number;
        $return['cd'] = $act['cd'];
        $return['pay_gold'] = $payGold;
        $return['pay_check'] = $payCheck;
        $return['list'] = $rewardList;
        return $return;
    }

    /**
     * 招募战友领工资奖励: c=act_paychecks&m=get_reward（id 奖励 id）
     *
     * -7 等级不足；-6 操作太快了；-5 条件未达成；-4 已领取过奖励；-3 奖励信息不存在；-2 参数错误；-1 活动未开启；1 成功；0 失败
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $lockKey = self::LOCK_KEY . $uid;
        // 防刷接口处理
        if ($uid <= 0 || !FestLock::acquire($lockKey)) {
            return ['status' => -6, 'msg' => self::LANG['faster_error']];
        }
        try {
            $act = self::init($now);
            if ($act === null) {
                return ['status' => -1, 'msg' => self::LANG['activity_not_open']];
            }
            if (self::level($uid) < self::OPEN_LEVEL) {
                return ['status' => -7, 'msg' => self::LANG['level_error']];
            }
            $id = (int)$ctx->input('id', 0);
            if ($id <= 0) {
                return ['status' => -2, 'msg' => self::LANG['param_error']];
            }
            // 获取奖励信息
            $rewardInfo = ActPaychecksReward::ROWS[$id] ?? null;
            if ($rewardInfo === null) {
                return ['status' => -3, 'msg' => self::LANG['reward_error']];
            }
            // 获取邀请码（没有就生成，旧版如此）
            if (InvitationHandler::codeInfo($uid) === null) {
                InvitationHandler::createCodeInfo($uid);
            }
            // 查询玩家信息
            $inviteData = self::inviteInfo($act['version'], $uid);
            if ($inviteData === null) {
                return ['status' => -5, 'msg' => self::LANG['not_active']];
            }
            if ($inviteData['invite_num'] < $rewardInfo['count']) {
                // 条件未达成
                return ['status' => -5, 'msg' => self::LANG['not_active']];
            }
            // 达成条件：记录领取（已领取过时不插入）
            $added = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_act_paychecks_reward_log` (`uid`, `version`, `rewardid`, `dateline`) VALUES (:uid, :version, :id, :now)',
                ['uid' => $uid, 'version' => $act['version'], 'id' => $id, 'now' => $now]
            );
            if ($added <= 0) {
                return ['status' => -4, 'msg' => self::LANG['already_action']];
            }
            $return = [];
            if ($rewardInfo['type'] === 3) {
                // 奖励元宝
                Member::addCurrency($uid, gold: $rewardInfo['number']);
                $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold');
            } elseif ($rewardInfo['type'] === 1) {
                // 奖励黄宝石
                TavernHandler::addJade($uid, 2, $rewardInfo['number'], 'act_paychecks', 'get_reward');
            } elseif ($rewardInfo['type'] === 2) {
                // 奖励紫宝石
                TavernHandler::addJade($uid, 3, $rewardInfo['number'], 'act_paychecks', 'get_reward');
            }
            // 剔除奖励说明
            unset($rewardInfo['desc']);
            $return['reward'] = $rewardInfo;
            $return['status'] = 1;
            return $return;
        } finally {
            FestLock::release($lockKey);
        }
    }

    /**
     * 发工资（战友招募）活动期间充值记账（actpaychecks_model->add_paychecks_pay_order，pay_act_init：活动开启且未结束时，
     * 元宝数为商品元宝；由 Model\Pay::complete 在到账事务里调用）
     */
    public static function addPayOrder(int $uid, string $nickname, int $level, int $gold, string $orderId, string $class, string $method, int $now): void
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null || (int)$info['endtime'] <= $now) {
            return;
        }
        DB::table('act_paychecks_order')->insert([
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
     * 计划任务：重建招募统计（cron_acttask.php:action_paychecks，旧版注释「半小时执行一次」）
     *
     * 活动开始后到结束 10 小时内执行：清空本期 act_paychecks 后重新统计。邀请者为本服、本期内（邀请时间不早于活动开始）邀请过
     * 好友的玩家；每个被邀请者在邀请者 50 级以上、自己 80 级以上且在活动开始后注册时计 1 人，充值元宝累计其本期 act_paychecks_order。
     *
     * @return int -1 无活动；-2 活动未开始；-3 已结束超过 10 小时；1 已执行
     */
    public static function cronStat(int $now): int
    {
        $activity = ActivityClock::byName(self::ACTIVITY);
        if ($activity === null) {
            return -1;
        }
        $version = (int)$activity['version'];
        $start = (int)$activity['starttime'];
        if ($now >= (int)$activity['endtime'] + 36000) {
            return -3;
        }
        // 旧版没有判断活动开始（取 uc 数据时按开始时间过滤）；活动开始前没有邀请记录，结果为空
        if ($start > $now) {
            return -2;
        }
        $serverid = Server::id();
        // 获取该服本期的邀请记录（uc_model->get_user_data_by_cron / get_codedata 按调用方还原）
        $invites = DB::table('uc_invitation_log')->select('serverid', 'uid', 'from_uid')
            ->where('from_serverid', $serverid)->where('dateline', '>=', $start)->whereNull('deleted_at')->get();
        $newData = [];
        foreach ($invites as $row) {
            $fromUid = (int)$row['from_uid'];
            // 如果被邀请人是新注册的玩家 自身等级要达到50 好友也要达到80级
            $fromLevel = self::level($fromUid);
            if ((int)$row['serverid'] !== $serverid) {
                // 依赖未移植：跨服查询其他服被邀请者的等级与充值（userinvite_model / adminapi），多服部署才需要
                continue;
            }
            $user2 = DB::table('member')->select('level', 'created_at')->where('uid', (int)$row['uid'])->whereNull('deleted_at')->first();
            if ($user2 === null || $fromLevel < self::OPEN_LEVEL) {
                continue;
            }
            if ((int)$user2['level'] >= self::MIN_LEVEL && (int)strtotime((string)$user2['created_at']) >= $start) {
                $newData[$fromUid][] = (int)$row['uid'];
            }
        }
        return DB::transaction(static function () use ($newData, $version, $now): int {
            // 清空当前版本号下的数据（旧版 delete 后 insert_batch；新版软删除不在本次统计里的玩家，其余按 uid + version 覆盖）
            $query = DB::table('act_paychecks')->where('version', $version)->whereNull('deleted_at');
            if ($newData !== []) {
                $query->whereNotIn('uid', array_keys($newData));
            }
            $query->update(['deleted_at' => date('Y-m-d H:i:s', $now)]);
            foreach ($newData as $uid => $friends) {
                $payGold = 0.0;
                foreach ($friends as $friend) {
                    $payGold += (float)(DB::query(
                        'SELECT SUM(`gold`) AS `gold` FROM `zc_sanguo_act_paychecks_order` WHERE `version` = :version AND `uid` = :uid AND `deleted_at` IS NULL',
                        ['version' => $version, 'uid' => $friend]
                    )[0]['gold'] ?? 0);
                }
                $payChecks = $payGold > 0 ? $payGold * self::CHECK : 0;
                DB::execute(
                    'INSERT INTO `zc_sanguo_act_paychecks` (`uid`, `version`, `invite_num`, `gold`, `paychecks`, `dateline`)
                     VALUES (:uid, :version, :num, :gold, :paychecks, :now)
                     ON DUPLICATE KEY UPDATE `invite_num` = VALUES(`invite_num`), `gold` = VALUES(`gold`), `paychecks` = VALUES(`paychecks`),
                         `dateline` = VALUES(`dateline`), `deleted_at` = NULL',
                    ['uid' => $uid, 'version' => $version, 'num' => count($friends), 'gold' => $payGold, 'paychecks' => $payChecks, 'now' => $now]
                );
            }
            return 1;
        });
    }

    /**
     * 计划任务：活动结束后自动发放未领取的奖励与工资（cron_acttask.php:action_paychecks_reward）
     *
     * 只在活动结束后 1 小时内执行：先重建统计，再给达到人数、没有领取记录的奖励补发（同类合并、发信件），最后给战友充值大于 0
     * 且没有工资记录的玩家发工资（元宝 = 充值元宝 × 0.1）并发信件。
     * 与旧版的差别：领取记录 / 工资记录插入成功才发（旧版先查后插，计划任务重复执行时可能重复发）。
     *
     * @return int -1 无活动；-2 活动未结束；-3 结束超过 1 小时；1 已执行
     */
    public static function cronReward(int $now): int
    {
        $activity = ActivityClock::byName(self::ACTIVITY);
        if ($activity === null) {
            return -1;
        }
        if ((int)$activity['endtime'] > $now) {
            return -2;
        }
        if ((int)$activity['endtime'] + 3600 < $now) {
            return -3;
        }
        // 初始化信息
        self::cronStat($now);
        $version = (int)$activity['version'];
        $userData = array_map([self::class, 'castInvite'], DB::table('act_paychecks')->where('version', $version)->whereNull('deleted_at')->get());
        $newReward = [];
        foreach ($userData as $li) {
            foreach (ActPaychecksReward::ROWS as $val) {
                if ($li['invite_num'] < $val['count']) {
                    continue;
                }
                $added = DB::execute(
                    'INSERT IGNORE INTO `zc_sanguo_act_paychecks_reward_log` (`uid`, `version`, `rewardid`, `dateline`) VALUES (:uid, :version, :id, :now)',
                    ['uid' => $li['uid'], 'version' => $version, 'id' => $val['id'], 'now' => $now]
                );
                if ($added <= 0 || $val['type'] <= 0) {
                    continue;
                }
                $newReward[$li['uid']][$val['type']] = ($newReward[$li['uid']][$val['type']] ?? 0) + $val['number'];
            }
        }
        foreach ($newReward as $uid => $val) {
            $message = self::LANG['cron_paychecks_end'] . self::LANG['cron_get_reward'];
            foreach ($val as $type => $num) {
                // 1黄宝石 2紫宝石 3元宝
                switch ($type) {
                    case 1:
                        TavernHandler::addJade($uid, 2, $num, 'cron_acttask', 'action_paychecks_reward');
                        $message .= self::LANG['cron_yellow'] . $num . ';';
                        break;
                    case 2:
                        TavernHandler::addJade($uid, 3, $num, 'cron_acttask', 'action_paychecks_reward');
                        $message .= self::LANG['cron_purple'] . $num . ';';
                        break;
                    case 3:
                        Member::addCurrency($uid, gold: $num);
                        $message .= self::LANG['cron_sycee'] . $num . ';';
                        break;
                }
            }
            $message .= self::LANG['cron_check'];
            MessageHandler::sendSystemMessage([$uid], 1, self::LANG['message_system_title'], $message);
        }
        // 发工资
        foreach ($userData as $val) {
            if ($val['gold'] <= 0) {
                continue;
            }
            $payChecks = self::num($val['gold'] * self::CHECK);
            $added = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_act_paychecks_paycheck_log` (`uid`, `version`, `paychecks`, `dateline`) VALUES (:uid, :version, :paychecks, :now)',
                ['uid' => $val['uid'], 'version' => $version, 'paychecks' => $payChecks, 'now' => $now]
            );
            if ($added <= 0) {
                continue;
            }
            // add_gold 的元宝列为整数
            Member::addCurrency($val['uid'], gold: (int)$payChecks);
            $message = self::LANG['cron_paychecks_end2'] . sprintf(self::LANG['cron_paychecks_get'], $val['gold'], $payChecks) . self::LANG['cron_check'];
            MessageHandler::sendSystemMessage([$val['uid']], 1, self::LANG['message_system_title'], $message);
        }
        return 1;
    }

    /**
     * 活动时间（_init）：activity_status 开启且 starttime <= 当前 < endtime 时返回版本与倒计时；否则 null
     *
     * @return array{version: int, cd: int}|null
     */
    private static function init(int $now): ?array
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null || $now >= (int)$info['endtime']) {
            return null;
        }
        return ['version' => (int)$info['version'], 'cd' => max(0, (int)$info['endtime'] - $now)];
    }

    /**
     * 玩家招募统计（get_invite_info）；没有为 null
     *
     * @return array{uid: int, invite_num: int, gold: int|float, paychecks: int|float}|null
     */
    private static function inviteInfo(int $version, int $uid): ?array
    {
        $row = DB::table('act_paychecks')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first();
        return $row === null ? null : self::castInvite($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{uid: int, invite_num: int, gold: int|float, paychecks: int|float}
     */
    private static function castInvite(array $row): array
    {
        return [
            'uid' => (int)$row['uid'],
            'invite_num' => (int)$row['invite_num'],
            'gold' => self::num((float)$row['gold']),
            'paychecks' => self::num((float)$row['paychecks']),
        ];
    }

    /**
     * 已领取的奖励（get_my_reward_by_id 逐个查询，这里一次取出：rewardid => true）
     *
     * @return array<int, bool>
     */
    private static function rewardLog(int $uid, int $version): array
    {
        $return = [];
        foreach (DB::table('act_paychecks_reward_log')->select('rewardid')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->get() as $row) {
            $return[(int)$row['rewardid']] = true;
        }
        return $return;
    }

    private static function level(int $uid): int
    {
        return (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level') ?? 0);
    }

    /**
     * 浮点数为整数时按整数输出（旧版 MySQL float 列读出来是字符串，JSON 里不带小数点）
     */
    private static function num(float $value): int|float
    {
        return floor($value) === $value ? (int)$value : $value;
    }
}
