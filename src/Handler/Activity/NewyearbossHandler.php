<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\General as GeneralTemplate;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\MasterData\NewyearBossAchievement;
use Hanjiang\MasterData\NewyearBossRank;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\General;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\NewyearBoss;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\PubLog;
use Hanjiang\Model\SpringReward;
use Hanjiang\Model\TraderLog;
use Hanjiang\Model\WorldBoss;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 春节年兽（移植旧版 controllers/newyearboss.php + models/newyearboss_model.php；活动结束后的排行补发为 cron.php 的
 * get_newyearboss_reward，见 settleRankReward / Cron\NewyearbossCron）
 *
 * 活动时间与版本取 ActivityTime 的 newyearboss（旧版数据 2016-12-31 已结束，接口回 -1）；最后一天（endtime - 1 天以后）只能领排行奖励。
 * 活动期间每天 12:30 ~ 13:00 打年兽（状态与键名见 Model\NewyearBoss）：战斗为旧版 battle_worldboss（玩家队伍打 5 号位的 BOSS，
 * 鼓舞加成乘到伤害上），复活 CD 60 秒，元宝立即复活 20 起每次 + 20（最多 200）；鼓舞银币（主公等级 × 500，加到 200%）或
 * 元宝（20，加到 400%，计入元宝鼓舞次数）。伤害 / 元宝鼓舞 / 闪避按活动累计排行（newyear_boss_log），活动结束（endtime - 1 天）
 * 后按名次领 NewyearBossRank 的奖励；每日成就 NewyearBossAchievement（伤害 / 鼓舞 / 闪避 / 排名）每天各领一次。
 * 客户端：nianworld.js 调用 index、powerup（gold）、get_rank（type）、get_rank_reward（type）、get_rank_reward_list、
 * get_achievement_list（type）、get_achievement_reward（id）；miscpop.js 调用 battle（now）。当前时间取 BossClock（生产恒为 time()）。
 *
 * 照旧版：index 的「BOSS 已死」分支永远走不到（allblood 恒为 2 万亿，血量随时间自然减少）；排名成就（type 4）在没有名次（0）时
 * 也能领（旧版只拦截名次大于条件的）；武将成就奖励返回的 getvalue 为 null（旧版取 add_general 返回值里不存在的 info）；
 * 计划任务补发的宝石碎片 / 突飞卡 / 蓝宝石数量为 0（旧版取错变量 $reward['value']），信件照写奖励数量。
 * 与旧版的差别：鼓舞到上限的判断与 +10 原子执行，并发越过上限时退回本次花费并回 -3；领奖（排行 / 成就）的查重与写记录在
 * 玩家行锁里执行（旧版先查后写，并发可重复领取）。不移植：clean（运维口令接口）。
 */
final class NewyearbossHandler
{
    /** 立即复活花费金币 / 每次增加 / 最大花费 */
    private const int RELIVE_COST = 20;
    private const int RELIVE_COST_ADD = 20;
    private const int RELIVE_COST_BIG = 200;
    /** 复活间隔时间 */
    private const int RELIVE_TIME = 60;
    /** 金币鼓舞花费 */
    private const int POWERUP_GOLD_COST = 20;
    /** 鼓舞上限：元宝 400、银币 200 */
    private const int POWERUP_GOLD_MAX = 400;
    private const int POWERUP_SILVER_MAX = 200;
    /** TOP 排行人数 */
    private const int TOP_NUM = 5;
    /** 计划任务发放窗口：活动结束后 10 小时内 */
    private const int CRON_WINDOW = 36000;

    /** newyearboss_lang.php / spring_lang.php / base_lang.php */
    private const array LANG = [
        'newyearboss_activity_no_open' => '春节年兽活动未开启！',
        'newyearboss_no_start' => '春节年兽活动时间未到，请稍后再试。',
        'newyearboss_activity_end' => '春节年兽活动倒计时已结束。',
        'newyearboss_attack_cd' => '您还没有复活，请等待CD结束或使用元宝复活！',
        'newyearboss_is_die' => '春节年兽已死，无法进入战斗',
        'newyearboss_dont_powerup' => '非活动期间无法鼓舞，请等待活动开启再试',
        'newyearboss_powerup_big' => '鼓舞已达上限，无法继续鼓舞。',
        'newyearboss_relive_no_gold' => '您的元宝不足，无法立即复活。',
        'newyearboss_powerup_silver' => '银币鼓舞已达上限，无法继续鼓舞。',
        'newyearboss_no_gold_powerup' => '您的元宝不足，无法鼓舞！',
        'newyearboss_no_silver_powerup' => '您的银币不足，无法鼓舞！',
        'newyearboss_not_rank_type' => '您查看的排行不存在。',
        'newyearboss_achievement_fail' => '获取成就信息失败，请稍后再试！',
        'newyearboss_achievement_no_access' => '成就未达成，无法领取成就奖励！',
        'newyearboss_reward_get' => '奖励已领取，无法重复领取。',
        'newyearboss_get_fail' => '奖励领取失败，请稍后再试！',
        'newyearboss_achievement_rank_fail' => '伤害排行榜成就需等待BOSS击杀后才可领取。',
        'newyearboss_activity_not_end_get' => '春节年兽活动未结束，无法领取排行奖励。',
        'newyearboss_not_rank_reward' => '当前没有可领取的排行奖励。',
        'spring_boss_hit' => '伤害',
        'spring_boss_dodge' => '闪避',
        'spring_boss_power' => '鼓舞',
        'spring_bossreward_str' => '春节年兽活动已结束，您未领取的%s排行奖励系统已自动补发给您，获得了第%s名的奖励：',
        'spring_get_reward_end' => '祝您游戏愉快！',
        'base_gold' => '元宝',
        'base_silver' => '银币',
        'base_jade' => '玉石',
        'base_soul' => '原魂',
        'base_jade1' => '蓝宝石',
        'base_jade2' => '黄宝石',
        'base_jade3' => '紫宝石',
        'base_leap1' => '初级突飞卡',
        'base_gem' => '宝石碎片',
        'base_reputation' => '声望',
        'message_system_title' => '系统通知',
    ];

    /**
     * 初始化接口: c=newyearboss&m=index
     *
     * status：-1 活动未开启；-2 还没到 BOSS 时间（countdown 为距今天 12:30 的秒数）；-3 今天的 BOSS 战已结束；1 进行中
     */
    public static function index(Context $ctx): array
    {
        $return = [
            'status' => 0,
            'countdown' => 0,
            'powerup' => 100,
            'bossinfo' => [],
            'top' => [],
            'cd' => 0,
            'upcost' => [],
            'cdcost' => 0,
            'myrank' => 0,
            'myhit' => 0,
            'mydodge' => 0,
            'mypowerup' => 0,
            'msg' => '',
        ];
        $now = BossClock::now();
        $member = self::member(MemberHandler::resolveUid($ctx));
        $act = self::activity($now);
        if ($act === null || $member === null) {
            // 活动未开启
            $return['status'] = -1;
            $return['msg'] = self::LANG['newyearboss_activity_no_open'];
            return $return;
        }
        $uid = $member['uid'];
        // 鼓舞数据一直返回
        $return['powerup'] = NewyearBoss::powerUp($uid, $now);
        $return['cdcost'] = self::reliveCost($uid, $now);
        $return['upcost']['gold'] = self::POWERUP_GOLD_COST;
        $return['upcost']['silver'] = self::powerUpPay($member);
        $return['endtime'] = $act['cd'];

        if ($act['countdown'] === -1 || $act['cd'] <= 0) {
            // 还没有到boss攻击时间
            $return['status'] = -2;
            $return['msg'] = self::LANG['newyearboss_no_start'];
            $return['countdown'] = self::startTime($now) - $now;
            return $return;
        }
        // boss攻击倒计时已结束
        if ($act['countdown'] === 0) {
            return self::withRank(array_merge($return, ['status' => -3, 'msg' => self::LANG['newyearboss_activity_end']]), $uid, $now);
        }
        // 获取用户前一天的信息
        NewyearBoss::memberLastInfo($act['version'], $uid, $member['nickname'], $now);
        // boss信息
        $bossInfo = NewyearBoss::bossInfo();
        if ((int)($bossInfo['allblood'] ?? 0) <= 0) {
            // 活动未结束 boss已经死了（allblood 恒大于 0，旧版此分支走不到）
            if (NewyearBoss::myHit($uid, $now) === 0) {
                return self::withRank(array_merge($return, ['status' => -3, 'msg' => self::LANG['newyearboss_activity_end']]), $uid, $now);
            }
        }
        // 活动进行中 准备数据
        $return['status'] = 1;
        $return['countdown'] = $act['countdown'];
        $return['bossinfo'] = [
            'name' => $bossInfo['name'] ?? '',
            'blood' => sprintf('%.2f', (int)($bossInfo['blood'] ?? 0) * 100 / max(1, (int)($bossInfo['allblood'] ?? 0))),
            'image' => $bossInfo['image'] ?? '',
        ];
        // 复活CD
        $delay = NewyearBoss::checkDelay($uid, $now);
        $return['cd'] = 0;
        if ($delay > 0 && self::RELIVE_TIME > $delay) {
            $return['cd'] = self::RELIVE_TIME - $delay + 1;
        }
        // 排行数据
        return self::withRank($return, $uid, $now);
    }

    /**
     * 战斗: c=newyearboss&m=battle（now 1 为元宝立即复活）
     *
     * status：-1 活动未开启；-2 BOSS 战已结束（或未开始）；-3 战斗锁（info.cd 61）；-4 BOSS 已死；-5 元宝不足；
     * -6 等待复活 CD（info.cd）；1 成功（battle 为战报，info.win 为评价）
     */
    public static function battle(Context $ctx): array
    {
        $return = ['status' => 0, 'msg' => '', 'info' => [], 'battle' => []];
        $now = BossClock::now();
        $member = self::member(MemberHandler::resolveUid($ctx));
        $act = self::activity($now);
        if ($act === null || $member === null) {
            // 活动未开启
            $return['status'] = -1;
            $return['msg'] = self::LANG['newyearboss_activity_no_open'];
            return $return;
        }
        if ($act['countdown'] <= 0) {
            // boss攻击倒计时已结束
            $return['status'] = -2;
            $return['msg'] = self::LANG['newyearboss_activity_end'];
            return $return;
        }
        $uid = $member['uid'];
        // 是否复活
        $relive = (int)$ctx->input('now', 0);
        // 检查战斗锁 + 战斗处理锁（1 秒）
        if (!NewyearBoss::battleLock($uid)) {
            $return['status'] = -3;
            $return['msg'] = self::LANG['newyearboss_attack_cd'];
            $return['info']['cd'] = 61;
            return $return;
        }
        // 获取用户前一天的信息
        $lastInfo = NewyearBoss::memberLastInfo($act['version'], $uid, $member['nickname'], $now);
        // 获取boss信息
        $bossInfo = NewyearBoss::bossInfo();
        if ((int)($bossInfo['allblood'] ?? 0) <= 0) {
            // boss已经死了
            $return['status'] = -4;
            $return['msg'] = self::LANG['newyearboss_is_die'];
            return $return;
        }
        if ($relive !== 0) {
            // 立即复活
            $cost = self::reliveCost($uid, $now);
            if (!Member::deductCurrency($uid, gold: $cost)) {
                // 金币不足
                $return['status'] = -5;
                $return['msg'] = self::LANG['newyearboss_relive_no_gold'];
                return $return;
            }
            $return['info']['all_gold'] = self::gold($uid);
            // 添加今日复活次数
            NewyearBoss::addReliveTimes($uid, $now);
        } else {
            // 正常进入战斗 检查cd
            $delay = NewyearBoss::checkDelay($uid, $now);
            if ($delay > 0 && $delay < self::RELIVE_TIME) {
                // 等待CD进入战斗
                $return['status'] = -6;
                $return['msg'] = self::LANG['newyearboss_attack_cd'];
                $return['info']['cd'] = self::RELIVE_TIME - $delay + 1;
                return $return;
            }
        }

        // 战斗攻击加成系数
        $attackCoe = NewyearBoss::powerUp($uid, $now) / 100;
        // 战斗（battle_model->battle_worldboss：_pve = 1，_boss_battle = 1）
        $info = WorldBoss::battle($uid, $bossInfo, $attackCoe, 1);
        // 记录伤害
        $hit = $blood = $dodge = 0;
        foreach ($info['process'] ?? [] as $key => $value) {
            if ($value['launch'] === 'left') {
                foreach ($value['hurt'] as $v) {
                    if (isset($v['self']) && $v['self'] == 0) {
                        $hit += $v['blood'];
                        $blood += $v['blood'];
                    }
                }
                $percent = sprintf('%.2f', $blood * 100 / $bossInfo['allblood']);
                if ($percent > 0.01) {
                    $info['process'][$key]['hurt'][0]['percent'] = $percent;
                    $blood = 0;
                }
            }
        }
        if ($info !== null) {
            foreach ($info['rightteam'] as $key => $value) {
                $info['rightteam'][$key]['allblood'] = sprintf('%.2f', $bossInfo['blood'] * 100 / $bossInfo['allblood']);
                $info['rightteam'][$key]['herotype'] = 0;
            }
            foreach ($info['rightresult'] as $key => $value) {
                $info['rightresult'][$key]['allblood'] = 100;
                $info['rightresult'][$key]['blood'] = sprintf('%.2f', $value['blood'] * 100 / $bossInfo['allblood']);
                $info['rightresult'][$key]['herotype'] = 0;
            }
            foreach ($info['process'] as $val) {
                // 闪避次数
                if ($val['launch'] === 'right' && $val['attacktype'] == -1) {
                    $dodge += 1;
                }
            }
        }
        // 记录伤害，闪避次数
        NewyearBoss::updateMyHit($act['version'], $uid, (int)$hit, $dodge, $lastInfo, $act['countdown'], $now);
        $return['status'] = 1;
        $return['battle'] = $info;
        $return['info']['win'] = $info['assess'] ?? null;
        return $return;
    }

    /**
     * 鼓舞: c=newyearboss&m=powerup（gold 1 为元宝鼓舞）
     *
     * status：-1 活动未开启；-2 非活动期间；-3 鼓舞达到上限；-4 元宝不足；-5 银币不足；1 鼓舞成功
     */
    public static function powerup(Context $ctx): array
    {
        $return = ['status' => 0, 'powerup' => 0, 'msg' => ''];
        $now = BossClock::now();
        $gold = (int)$ctx->input('gold', 0);
        $member = self::member(MemberHandler::resolveUid($ctx));
        $act = self::activity($now);
        if ($act === null || $member === null) {
            $return['status'] = -1;
            $return['msg'] = self::LANG['newyearboss_activity_no_open'];
            return $return;
        }
        if ($act['countdown'] <= 0) {
            // boss攻击倒计时已结束
            $return['status'] = -2;
            $return['msg'] = self::LANG['newyearboss_dont_powerup'];
            return $return;
        }
        $uid = $member['uid'];
        // 查询玩家前一天的boss信息
        $lastInfo = NewyearBoss::memberLastInfo($act['version'], $uid, $member['nickname'], $now);
        $powerup = NewyearBoss::powerUp($uid, $now);
        $goldCost = 0;
        $silverCost = 0;
        if ($gold !== 0) {
            if ($powerup >= self::POWERUP_GOLD_MAX) {
                $return['status'] = -3;
                $return['msg'] = self::LANG['newyearboss_powerup_big'];
                $return['powerup'] = $powerup;
                return $return;
            }
            $goldCost = self::POWERUP_GOLD_COST;
            if (!Member::deductCurrency($uid, gold: $goldCost)) {
                // 金币不足
                $return['status'] = -4;
                $return['msg'] = self::LANG['newyearboss_no_gold_powerup'];
                return $return;
            }
            $max = self::POWERUP_GOLD_MAX;
        } else {
            if ($powerup >= self::POWERUP_SILVER_MAX) {
                $return['status'] = -3;
                $return['msg'] = self::LANG['newyearboss_powerup_silver'];
                $return['powerup'] = $powerup;
                return $return;
            }
            $silverCost = self::powerUpPay($member);
            if (!Member::deductCurrency($uid, silver: $silverCost)) {
                // 银币不足
                $return['status'] = -5;
                $return['msg'] = self::LANG['newyearboss_no_silver_powerup'];
                return $return;
            }
            $max = self::POWERUP_SILVER_MAX;
        }
        // 鼓舞（未满上限才 +10）；并发已到上限时退回本次花费
        $newPowerup = NewyearBoss::incrPowerUp($uid, $now, $max);
        if ($newPowerup === null) {
            Member::addCurrency($uid, silver: $silverCost, gold: $goldCost);
            $return['status'] = -3;
            $return['msg'] = self::LANG[$gold !== 0 ? 'newyearboss_powerup_big' : 'newyearboss_powerup_silver'];
            $return['powerup'] = NewyearBoss::powerUp($uid, $now);
            return $return;
        }
        if ($gold > 0) {
            // 修改玩家鼓舞次数记录
            $lastPowerup = (int)($lastInfo['powerup'] ?? 0);
            $todayPowerup = NewyearBoss::powerupTimes($uid, $now);
            NewyearBoss::updateMemberInfo($act['version'], $uid, ['powerup' => $lastPowerup + $todayPowerup + 1], $now);
            // 添加今日金币鼓舞次数
            NewyearBoss::addPowerupTimes($uid, $now);
        }
        $after = self::member($uid) ?? $member;
        // 鼓舞成功
        $return['status'] = 1;
        $return['all_silver'] = $after['silver'];
        $return['all_gold'] = $after['gold'];
        $return['powerup'] = $newPowerup;
        $return['mypowerup'] = NewyearBoss::powerupTimes($uid, $now);
        return $return;
    }

    /**
     * 查看最终排行: c=newyearboss&m=get_rank（type 1 伤害、2 鼓舞、3 闪避）
     *
     * status：-1 活动未开启；-2 排行类型不对；1 成功（isget 0 不可领、1 可领、2 已领；cd 为距活动结束的秒数）
     */
    public static function get_rank(Context $ctx): array
    {
        $return = ['status' => 0, 'msg' => ''];
        $now = BossClock::now();
        $member = self::member(MemberHandler::resolveUid($ctx));
        $act = self::activity($now);
        if ($act === null || $member === null) {
            $return['status'] = -1;
            $return['msg'] = self::LANG['newyearboss_activity_no_open'];
            return $return;
        }
        // 排行类型（1.伤害，2鼓舞，3.闪避）
        $type = (int)$ctx->input('type', 0);
        if (!in_array($type, [1, 2, 3], true)) {
            $return['status'] = -2;
            $return['msg'] = self::LANG['newyearboss_not_rank_type'];
            return $return;
        }
        $uid = $member['uid'];
        // 查询排行列表
        $result = NewyearBoss::rankList($act['version'], $uid, $type);
        $field = NewyearBoss::rankField($type);
        foreach ($result['list'] as $key => $val) {
            $result['list'][$key]['value'] = $val[$field];
        }
        $rank = $result['rank'];
        // 判断领取状态
        if ($rank <= 0 || $act['cd'] > 0) {
            // 不可领取
            $isget = 0;
        } else {
            $isget = NewyearBoss::rewardLog($act['version'], $uid, $type, 2, $now) !== [] ? 2 : 1;
        }
        $return['status'] = 1;
        $return['myrank'] = $rank;
        $return['list'] = $result['list'];
        // 根据排行查询奖励信息
        $return['reward'] = NewyearBoss::rewardByRank($type, $rank);
        $return['isget'] = $isget;
        $return['cd'] = $act['cd'];
        return $return;
    }

    /**
     * 领取排行奖励: c=newyearboss&m=get_rank_reward（type）
     *
     * status：-1 活动未开启；-2 活动未结束；-3 排行类型不对；-4 已领取；-5 没有可领取的奖励；-6 领取失败；1 成功
     */
    public static function get_rank_reward(Context $ctx): array
    {
        $return = ['status' => 0, 'msg' => ''];
        $now = BossClock::now();
        $member = self::member(MemberHandler::resolveUid($ctx));
        $act = self::activity($now);
        if ($act === null || $member === null) {
            // 活动未开启
            $return['status'] = -1;
            $return['msg'] = self::LANG['newyearboss_activity_no_open'];
            return $return;
        }
        if ($act['cd'] > 0) {
            // 活动未结束，无法领取
            $return['status'] = -2;
            $return['msg'] = self::LANG['newyearboss_activity_not_end_get'];
            return $return;
        }
        $type = (int)$ctx->input('type', 0);
        if (!in_array($type, [1, 2, 3], true)) {
            $return['status'] = -3;
            $return['msg'] = self::LANG['newyearboss_not_rank_type'];
            return $return;
        }
        $uid = $member['uid'];
        $version = $act['version'];
        return Member::locked($uid, static function () use ($uid, $type, $version, $now, $return): array {
            // 查询自己是否已经领取过排行奖励
            if (NewyearBoss::rewardLog($version, $uid, $type, 2, $now) !== []) {
                $return['status'] = -4;
                $return['msg'] = self::LANG['newyearboss_reward_get'];
                return $return;
            }
            // 查询自己的排行
            $rank = NewyearBoss::rankList($version, $uid, $type)['rank'];
            if ($rank <= 0) {
                // 没有可领取的奖励
                $return['status'] = -5;
                $return['msg'] = self::LANG['newyearboss_not_rank_reward'];
                return $return;
            }
            // 根据排行查询奖励信息
            $reward = NewyearBoss::rewardByRank($type, $rank);
            // 添加奖励领取记录
            if (NewyearBoss::addRewardLog($version, $uid, $type, 2, $rank, $now) !== 1) {
                $return['status'] = -6;
                $return['msg'] = self::LANG['newyearboss_get_fail'];
                return $return;
            }
            // 添加奖励
            $value = self::grant($uid, $reward['type'], $reward['value'], 'get_rank_reward', $now);
            if ($value !== null) {
                $reward['value'] = $value;
            }
            $after = self::member($uid);
            // 成功返回
            $return['status'] = 1;
            $return['reward'] = $reward;
            $return['all_gold'] = $after['gold'] ?? 0;
            $return['all_silver'] = $after['silver'] ?? 0;
            $return['all_act'] = $after['act'] ?? 0;
            return $return;
        });
    }

    /**
     * 获得成就奖励列表: c=newyearboss&m=get_achievement_list（type 1 伤害、2 鼓舞、3 闪避、4 排名）
     *
     * 每项 status：0 未达成、1 可领取、2 今天已领取
     */
    public static function get_achievement_list(Context $ctx): array
    {
        $return = ['status' => 0, 'msg' => ''];
        $now = BossClock::now();
        $type = (int)$ctx->input('type', 0);
        $member = self::member(MemberHandler::resolveUid($ctx));
        $act = self::activity($now);
        if ($act === null || $member === null) {
            $return['status'] = -1;
            $return['msg'] = self::LANG['newyearboss_activity_no_open'];
            return $return;
        }
        $uid = $member['uid'];
        // 查询今日成就列表
        $list = array_values(array_filter(NewyearBossAchievement::ROWS, static fn (array $row): bool => $row['type'] === $type));
        $value = self::achievementValue($uid, $type, $now);
        // 查询成就奖励领取状态
        $reward = NewyearBoss::rewardLog($act['version'], $uid, $type, 1, $now);
        foreach ($list as $key => $val) {
            if ($type <= 3 && $value >= $val['value']) {
                $list[$key]['status'] = 1;
            } elseif ($type === 4 && $value > 0 && $value <= $val['value']) {
                $list[$key]['status'] = 1;
            } else {
                $list[$key]['status'] = 0;
            }
            // 判断是否已领取
            if ($list[$key]['status'] === 1 && isset($reward[$val['id']])) {
                $list[$key]['status'] = 2;
            }
            $list[$key]['getvalue'] = self::displayValue((int)$val['gettype'], (int)$val['getvalue']);
        }
        // 成功返回
        $return['status'] = 1;
        $return['list'] = $list;
        return $return;
    }

    /**
     * 获取排行奖励列表: c=newyearboss&m=get_rank_reward_list
     */
    public static function get_rank_reward_list(Context $ctx): array
    {
        $return = ['status' => 0, 'msg' => ''];
        $now = BossClock::now();
        if (self::activity($now) === null || self::member(MemberHandler::resolveUid($ctx)) === null) {
            $return['status'] = -1;
            $return['msg'] = self::LANG['newyearboss_activity_no_open'];
            return $return;
        }
        $list = array_values(NewyearBossRank::ROWS);
        foreach ($list as $key => $val) {
            for ($i = 1; $i <= 3; $i++) {
                $list[$key]['value' . $i] = self::displayValue((int)$val['tab' . $i], (int)$val['value' . $i]);
            }
        }
        $return['status'] = 1;
        $return['list'] = $list;
        return $return;
    }

    /**
     * 领取成就奖励: c=newyearboss&m=get_achievement_reward（id 成就 id）
     *
     * status：-1 活动未开启；-2 成就不存在；-3 成就未达成；-4 今天已领取；-5 领取失败；-6 排名成就要等 BOSS 战结束；1 成功
     */
    public static function get_achievement_reward(Context $ctx): array
    {
        $return = ['status' => 0, 'msg' => ''];
        $now = BossClock::now();
        $id = (int)$ctx->input('id', 0);
        $member = self::member(MemberHandler::resolveUid($ctx));
        $act = self::activity($now);
        if ($act === null || $member === null) {
            $return['status'] = -1;
            $return['msg'] = self::LANG['newyearboss_activity_no_open'];
            return $return;
        }
        // 查询成就信息
        $info = NewyearBossAchievement::ROWS[$id] ?? null;
        if ($info === null) {
            $return['status'] = -2;
            $return['msg'] = self::LANG['newyearboss_achievement_fail'];
            return $return;
        }
        // 排行成就奖励需等到boss结束后才可领取
        if ($info['type'] === 4 && $act['countdown'] > 0) {
            $return['status'] = -6;
            $return['msg'] = self::LANG['newyearboss_achievement_rank_fail'];
            return $return;
        }
        $uid = $member['uid'];
        $value = self::achievementValue($uid, $info['type'], $now);
        if (($info['type'] <= 3 && $value < $info['value']) || ($info['type'] === 4 && $value > 0 && $value > $info['value'])) {
            // 成就未达成，无法领取
            $return['status'] = -3;
            $return['msg'] = self::LANG['newyearboss_achievement_no_access'];
            return $return;
        }
        $version = $act['version'];
        return Member::locked($uid, static function () use ($uid, $id, $info, $version, $now, $return): array {
            // 查询该成就的领取状态
            if (NewyearBoss::rewardLog($version, $uid, $info['type'], 1, $now, $id) !== []) {
                $return['status'] = -4;
                $return['msg'] = self::LANG['newyearboss_reward_get'];
                return $return;
            }
            // 添加奖励领取状态
            if (NewyearBoss::addRewardLog($version, $uid, $info['type'], 1, $id, $now) !== 1) {
                $return['status'] = -5;
                $return['msg'] = self::LANG['newyearboss_get_fail'];
                return $return;
            }
            // 添加奖励
            $value = self::grant($uid, (int)$info['gettype'], (int)$info['getvalue'], 'get_achievement_reward', $now);
            if ((int)$info['gettype'] === 13) {
                // 旧版取 add_general 返回值里不存在的 info，返回 null
                $info['getvalue'] = $value === null ? $info['getvalue'] : null;
            } elseif ($value !== null) {
                $info['getvalue'] = $value;
            }
            $after = self::member($uid);
            // 成功返回
            $return['status'] = 1;
            $return['info'] = $info;
            $return['all_gold'] = $after['gold'] ?? 0;
            $return['all_silver'] = $after['silver'] ?? 0;
            $return['all_act'] = $after['act'] ?? 0;
            return $return;
        });
    }

    /**
     * 计划任务：活动结束后 10 小时内给未领取排行奖励的玩家补发（cron.php get_newyearboss_reward，$type 1 伤害、2 鼓舞、3 闪避）
     *
     * 名次按活动累计值降序、更新时间升序（不限 VIP，全部有数值的玩家）；已领取（reward_log type 2）的跳过；补发后发系统信件。
     *
     * @return int 1 已执行；-1 无活动或类型不对；-2 活动未结束；-3 结束超过 10 小时；-4 没有可发放的用户
     */
    public static function settleRankReward(int $type, int $now): int
    {
        if ($type <= 0 || $type > 3) {
            return -1;
        }
        // 查询活动信息（get_activity_by_start：不论是否开启）
        $activity = ActivityClock::byName(NewyearBoss::ACTIVITY);
        if ($activity === null) {
            return -1;
        }
        // 判断活动是否结束
        if ($activity['endtime'] > $now) {
            return -2;
        }
        // 判断是否可发放奖励（活动已结束，并且在活动结束后10个小时内）
        if ($activity['endtime'] + self::CRON_WINDOW < $now) {
            return -3;
        }
        $version = (int)$activity['version'];
        $key = NewyearBoss::rankField($type);
        $typeStr = match ($type) {
            1 => self::LANG['spring_boss_hit'],
            2 => self::LANG['spring_boss_power'],
            default => self::LANG['spring_boss_dodge'],
        };
        $list = DB::table('newyear_boss_log')->select('uid')->where('version', $version)->where($key, '>', 0)->whereNull('deleted_at')
            ->orderBy($key, 'DESC')->orderBy('dateline')->orderBy('id')->get();
        if ($list === []) {
            return -4;
        }
        // 得到要补发的用户及他们的排名奖励
        $rank = [];
        foreach ($list as $i => $val) {
            $rank[(int)$val['uid']] ??= $i + 1;
        }
        // 查询用户奖励领取记录
        $getLog = [];
        foreach (DB::table('newyear_boss_reward_log')->select('uid')->where('type', 2)->where('version', $version)->where('tab', $type)
            ->whereNull('deleted_at')->get() as $val) {
            $getLog[(int)$val['uid']] = true;
        }
        foreach ($rank as $uid => $myRank) {
            if (isset($getLog[$uid])) {
                // 排名奖励已领取
                continue;
            }
            $reward = NewyearBoss::rewardByRank($type, $myRank);
            Member::locked($uid, static function () use ($uid, $type, $version, $myRank, $reward, $typeStr, $now): void {
                if (NewyearBoss::rewardLog($version, $uid, $type, 2, $now) !== []) {
                    return;
                }
                // 修改领取状态
                if (NewyearBoss::addRewardLog($version, $uid, $type, 2, $myRank, $now) !== 1) {
                    return;
                }
                $value = $reward['value'];
                $message = sprintf(self::LANG['spring_bossreward_str'], $typeStr, $myRank) . $value;
                // 添加奖励（宝石碎片 / 突飞卡 / 蓝宝石旧版取错变量，数量为 0）
                switch ($reward['type']) {
                    case 1:
                        $message .= self::LANG['base_gold'] . ',';
                        Member::addCurrency($uid, gold: $value);
                        break;
                    case 2:
                        $message .= self::LANG['base_silver'] . ',';
                        Member::addCurrency($uid, silver: $value);
                        break;
                    case 3:
                        $message .= self::LANG['base_jade2'] . ',';
                        TraderLog::addJade($uid, 2, $value, class: 'cron', method: 'get_newyearboss_reward', now: $now);
                        break;
                    case 4:
                        $message .= self::LANG['base_jade3'] . ',';
                        TraderLog::addJade($uid, 3, $value, class: 'cron', method: 'get_newyearboss_reward', now: $now);
                        break;
                    case 5:
                        $message .= self::LANG['base_soul'] . ',';
                        OwnSoul::addSoul($uid, $value, 0, 'cron', 'get_newyearboss_reward', $now);
                        break;
                    case 6:
                        $message .= self::LANG['base_jade'] . ',';
                        MemberJade::add($uid, $value, 'cron', 'get_newyearboss_reward', $now);
                        break;
                    case 7:
                        $message .= self::LANG['base_reputation'] . ',';
                        Member::addReputation($uid, $value);
                        break;
                    case 9:
                        $message .= self::LANG['base_gem'] . ',';
                        break;
                    case 10:
                        $message .= self::LANG['base_leap1'] . ',';
                        FreeLeap::add($uid, 0, $now);
                        break;
                    case 12:
                        $message .= self::LANG['base_jade1'] . ',';
                        break;
                }
                $message .= self::LANG['spring_get_reward_end'];
                Redis::del('player:' . $uid);
                MessageHandler::sendSystemMessage([$uid], 1, self::LANG['message_system_title'], $message);
            });
        }
        return 1;
    }

    /**
     * 活动信息（_init_activity）：活动结束时间提前一天（最后一天只能领奖）；cd 为距 _end 的秒数，countdown 为今天 BOSS 战的倒计时
     *
     * @return array{version: int, start: int, end: int, cd: int, countdown: int}|null
     */
    private static function activity(int $now): ?array
    {
        $info = ActivityClock::open(NewyearBoss::ACTIVITY, $now);
        if ($info === null) {
            return null;
        }
        $end = (int)$info['endtime'] - 86400;
        return [
            'version' => (int)$info['version'],
            'start' => (int)$info['starttime'],
            'end' => $end,
            'cd' => $end - $now > 0 ? $end - $now : 0,
            'countdown' => NewyearBoss::activityCountdown($now),
        ];
    }

    /**
     * 今天 12:30:00 的时间戳（index 未开始时的 countdown）
     */
    private static function startTime(int $now): int
    {
        $s = NewyearBoss::START_TIME;
        return (int)strtotime(date('Y-m-d', $now) . substr($s, 0, 2) . ':' . substr($s, 2, 2) . ':' . substr($s, 4, 2));
    }

    /**
     * 立即复活花费（20 + 今日复活次数 × 20，最多 200）
     */
    private static function reliveCost(int $uid, int $now): int
    {
        return min(self::RELIVE_COST + NewyearBoss::reliveTimes($uid, $now) * self::RELIVE_COST_ADD, self::RELIVE_COST_BIG);
    }

    /**
     * 银币鼓舞花费（power_up_pay：主公等级 × 500）
     *
     * @param array{level: int} $member
     */
    private static function powerUpPay(array $member): int
    {
        return $member['level'] * 500;
    }

    /**
     * 排行数据（控制器 get_top_rank）：top 前 5 名（补玩家信息）、我的名次与伤害、闪避与元宝鼓舞次数
     *
     * 自己不在前 5 时伤害显示为「前 5 名最低伤害 × (100 - 名次) / 100」（自己的伤害不低于它时），照旧版。
     *
     * @param array<string, mixed> $return
     * @return array<string, mixed>
     */
    private static function withRank(array $return, int $uid, int $now): array
    {
        $top = WorldBoss::withMemberInfo(NewyearBoss::topRank($now, self::TOP_NUM), self::TOP_NUM);
        $find = false;
        $topHit = 0;
        $myrank = 0;
        $myhit = 0;
        foreach ($top as $value) {
            if ((int)$value['uid'] === $uid) {
                $myrank = $value['rank'];
                $myhit = $value['hit'];
                $find = true;
            }
            if ($topHit === 0 || $topHit > (int)$value['hit']) {
                $topHit = (int)$value['hit'];
            }
        }
        if (!$find) {
            $myrank = NewyearBoss::myRank($uid, $now);
            $myhit = NewyearBoss::myHit($uid, $now);
            if ($myhit >= $topHit) {
                $myhit = (int)($topHit * (100 - $myrank) / 100);
            }
        }
        $return['top'] = $top;
        $return['myrank'] = $myrank;
        $return['myhit'] = $myhit;
        $return['mydodge'] = NewyearBoss::dodgeTimes($uid, $now);
        $return['mypowerup'] = NewyearBoss::powerupTimes($uid, $now);
        return $return;
    }

    /**
     * 成就的当前值：1 今日伤害、2 今日元宝鼓舞次数、3 今日闪避、4 今日名次
     */
    private static function achievementValue(int $uid, int $type, int $now): int
    {
        return match ($type) {
            1 => NewyearBoss::myHit($uid, $now),
            2 => NewyearBoss::powerupTimes($uid, $now),
            3 => NewyearBoss::dodgeTimes($uid, $now),
            4 => NewyearBoss::myRank($uid, $now),
            default => 0,
        };
    }

    /**
     * 奖励的显示值：装备为装备模板、坐骑为坐骑模板、武将为 image / name / type / star，其余为数量
     *
     * @return int|array<string, mixed>|null
     */
    private static function displayValue(int $type, int $value): int|array|null
    {
        return match ($type) {
            8 => Equip::ROWS[$value] ?? [],
            11 => MountTemp::ROWS[$value] ?? [],
            13 => [
                'image' => GeneralTemplate::ROWS[$value]['image'] ?? null,
                'name' => GeneralTemplate::ROWS[$value]['name'] ?? null,
                'type' => GeneralTemplate::ROWS[$value]['type'] ?? null,
                'star' => GeneralTemplate::ROWS[$value]['star'] ?? null,
            ],
            default => $value,
        };
    }

    /**
     * 发放排行 / 成就奖励（1 ~ 13），返回替换后的奖励值（装备模板 / 坐骑信息 / 武将信息），数量类奖励返回 null
     *
     * @return array<string, mixed>|null
     */
    private static function grant(int $uid, int $type, int $value, string $method, int $now): ?array
    {
        $return = null;
        switch ($type) {
            case 8:
                // 装备
                $item = Equip::ROWS[$value] ?? null;
                if ($item !== null) {
                    Equipment::addFromTemplate($uid, $value);
                    $return = $item;
                }
                break;
            case 11:
                // 坐骑
                $return = OwnMount::addMount($uid, $value, 0, 1, 'newyearboss', $method, $now)['info'] ?? [];
                break;
            case 13:
                // 武将
                $general = General::addGeneral($uid, $value);
                if ($general !== null) {
                    // 添加酒馆日志(武将id, 拥有状态, 获取方式)
                    PubLog::add($uid, $value, 1, 9, $now);
                    $return = $general;
                }
                break;
            default:
                SpringReward::grant($uid, $type, $value, 'newyearboss', $method, $now);
                break;
        }
        Redis::del('player:' . $uid);
        return $return;
    }

    /**
     * @return array{uid: int, nickname: string, level: int, gold: int, silver: int, act: int}|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        $row = DB::table('member')->select('uid', 'nickname', 'level', 'gold', 'silver', 'act')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row === null) {
            return null;
        }
        return [
            'uid' => (int)$row['uid'],
            'nickname' => (string)$row['nickname'],
            'level' => (int)$row['level'],
            'gold' => (int)$row['gold'],
            'silver' => (int)$row['silver'],
            'act' => (int)$row['act'],
        ];
    }

    private static function gold(int $uid): int
    {
        return (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold');
    }
}
