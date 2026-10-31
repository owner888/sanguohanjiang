<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\DailyTask;
use Hanjiang\Model\MainQuestTask;
use Hanjiang\Model\Member;
use Hanjiang\Model\StatPay;
use Hanjiang\Model\WorldBoss;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 世界 BOSS 刑天（移植旧版 controllers/worldboss.php，状态见 Model\WorldBoss）
 *
 * 每天 12:00~12:15、20:00~20:15 两场（activity_model->_boss_time），每人每天只能参加其中一场：
 * 12:00 场有伤害记录或领过奖的玩家晚上仍按 12:00 场处理（已结束）。BOSS 血量 9 万亿、血条按剩余时间自然减少，
 * 打不死；按个人累计伤害排名发演武榜积分（world_boss_intreward），最后一击 × 1.5。
 * 未领取的奖励由 Cron\WorldBossCron 次日凌晨自动发放并清空全部世界 BOSS 键。
 *
 * 客户端调用：index、battle、powerup、reward（bossworld.js / bossbandpop.js / miscpop.js）。
 * 旧版的 clean（diy=1 清空全部世界 BOSS 键）与 power_up_pay（银币鼓舞价格，控制器内部用）客户端不调用，不对外暴露。
 * 旧版控制器的 _online 在线检查已注释（恒为 0），对应的 status 2 分支不移植。
 */
final class WorldBossHandler
{
    /** 立即复活花费元宝 */
    private const int RELIVE_COST = 10;
    /** 复活间隔时间 */
    private const int RELIVE_TIME = 60;
    /** 元宝鼓舞花费 */
    private const int POWERUP_GOLD_COST = 10;

    /**
     * 初始化接口: c=worldboss&m=index
     *
     * status	6 主公等级太低
     *			5 今日已参加过活动
     *			4 活动已结束
     *			3 活动未开启
     *			2 战斗准备
     *			1 领取奖励
     */
    public static function index(Context $ctx): array
    {
        $now = BossClock::now();
        $return = [
            'status' => 0,
            'countdown' => 0,		// 活动倒计时（单位：秒) status=2时有效
            'powerup' => 100,		// 攻击力加成百分比（单位 %）status=2时有效
            'bossinfo' => [],		// boss信息数组 status=2时有效
            'reward' => [],			// 奖励信息 status=1时有效
            'top' => [],			// top排行 status=1时有效
            'cd' => 0,				// 复活CD
            'upcost' => [],			// 鼓舞花费
            'cdcost' => 0,			// 复活花费
            'my' => [],				// 玩家伤害排名信息
        ];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null || (int)$member['level'] < WorldBoss::OPEN_LEVEL) {
            $return['status'] = 6;
            return $return;
        }
        $uid = (int)$member['uid'];
        $state = WorldBoss::context($uid, $now);
        $phase = $state['phase'];

        if ($state['countdown'] === -1) {
            // 活动未开启
            $return['status'] = 3;
        } elseif ($state['countdown'] === 0) {
            if (WorldBoss::myHit($phase, $uid) === 0) {
                if ($state['played'] === 1) {
                    // 已参加过活动
                    $return['status'] = 5;
                } elseif (date('Hi', $now) > '2015') {
                    // 活动已结束
                    $return['status'] = 4;
                } else {
                    // 活动未开启
                    $return['status'] = 3;
                }
            } else {
                // 参加活动未领取奖励
                $return['status'] = 1;
                [$return['top'], $return['reward']] = self::rewardView($phase, $uid);
            }
            // 最后一击公告只发一次（final_horn）
            $finalUid = WorldBoss::finalUid($phase);
            if ($finalUid) {
                // 发送最后一击聊天公告
                self::sendFinalNotice($finalUid, $uid);
            }
        } else {
            // boss信息
            $bossInfo = WorldBoss::bossInfo();
            if ((int)($bossInfo['allblood'] ?? 0) <= 0) {
                // 活动未结束 boss已经死了（旧版总血量写死 9 万亿，不会出现）
                if (WorldBoss::myHit($phase, $uid) === 0) {
                    // 已参加过活动 / 活动已结束
                    $return['status'] = $state['played'] === 1 ? 5 : 4;
                } else {
                    // 参加活动未领取奖励
                    $return['status'] = 1;
                    [$return['top'], $return['reward']] = self::rewardView($phase, $uid);
                }
                return $return;
            }
            // 活动进行中 准备数据
            $return['status'] = 2;
            $return['countdown'] = $state['countdown'];
            // 鼓舞
            $return['powerup'] = WorldBoss::powerUp($phase, $uid);
            $bossInfo['blood'] = sprintf('%.2f', $bossInfo['blood'] * 100 / $bossInfo['allblood']);
            foreach (['name', 'blood', 'image'] as $value) {
                $return['bossinfo'][$value] = $bossInfo[$value];
            }
            $delay = WorldBoss::checkDelay($phase, $uid);
            $return['cd'] = 0;
            if ($delay > 0 && self::RELIVE_TIME > $delay) {
                $return['cd'] = self::RELIVE_TIME - $delay + 1;
            }
            $return['cdcost'] = self::RELIVE_COST;
            $return['upcost']['gold'] = self::POWERUP_GOLD_COST;
            $return['upcost']['silver'] = self::powerUpPay($member);
            $return['top'] = self::topRank($phase, 3);
            $return['my']['rank'] = WorldBoss::myRank($phase, $uid);
            $return['my']['hit'] = WorldBoss::myHit($phase, $uid);
        }
        return $return;
    }

    /**
     * 战斗: c=worldboss&m=battle
     *
     * status	5 活动结束
     *			4 boss已经死了
     *			3 金币不足
     *			2 等待复活CD
     *			1 成功进入战斗
     */
    public static function battle(Context $ctx): array
    {
        $now = BossClock::now();
        $return = ['status' => 0, 'info' => [], 'battle' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            $return['status'] = 5;
            return $return;
        }
        $uid = (int)$member['uid'];
        $state = WorldBoss::context($uid, $now);
        $phase = $state['phase'];
        if ($state['countdown'] <= 0) {
            // 活动结束
            $return['status'] = 5;
            return $return;
        }

        $relive = (int)$ctx->input('now', 0);

        // 检查战斗锁 + 战斗处理锁（1 秒）
        if (!WorldBoss::battleLock($uid)) {
            $return['status'] = 2;
            $return['info']['cd'] = 61;
            return $return;
        }
        // 获取boss信息
        $bossInfo = WorldBoss::bossInfo();
        if ((int)($bossInfo['allblood'] ?? 0) <= 0) {
            // boss已经死了
            $return['status'] = 4;
            return $return;
        }
        if (WorldBoss::isFinalHorn($phase)) {
            $return['status'] = 4;
            return $return;
        }

        if ($relive !== 0) {
            // 立即复活
            if (!Member::deductCurrency($uid, gold: self::RELIVE_COST)) {
                // 金币不足
                $return['status'] = 3;
                return $return;
            }
            // 消费统计记录
            StatPay::record($uid, 'worldboss_relive', self::RELIVE_COST);
            $return['info']['all_gold'] = self::gold($uid);
        } else {
            // 正常进入战斗：检查cd
            $delay = WorldBoss::checkDelay($phase, $uid);
            if ($delay > 0 && $delay < self::RELIVE_TIME) {
                // 等待CD进入战斗
                $return['status'] = 2;
                $return['info']['cd'] = self::RELIVE_TIME - $delay + 1;
                return $return;
            }
        }

        /* 正常战斗处理 */
        $return['status'] = 1;
        // 战斗攻击加成系数
        $attackCoe = WorldBoss::powerUp($phase, $uid) / 100;
        // 战斗（battle_model->battle_worldboss：_pve = 1，_boss_battle = 1）
        $info = WorldBoss::battle($uid, $bossInfo, $attackCoe, 1);

        // 记录伤害
        $hit = $blood = 0;
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
        }
        WorldBoss::updateMyHit($phase, $uid, $hit, $state['countdown']);

        // 主线任务 11参加一次世界BOSS（只要有伤害）
        MainQuestTask::action($uid, 11, 1);
        // 日常任务(12是世界BOSS对应的类型)
        if ((int)$member['level'] >= DailyTask::OPEN_LEVEL) {
            DailyTask::action($uid, DailyTask::TYPE_WORLDBOSS);
        }

        $return['battle'] = $info;
        $return['info']['win'] = $info['assess'] ?? null;
        return $return;
    }

    /**
     * 鼓舞: c=worldboss&m=powerup
     *
     * status	1 鼓舞成功
     *			2 金币不足
     *			3 银币不足
     *			4 鼓舞达到上限
     */
    public static function powerup(Context $ctx): array
    {
        $now = BossClock::now();
        $return = ['status' => 0, 'powerup' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $phase = WorldBoss::context($uid, $now)['phase'];
        $powerup = WorldBoss::powerUp($phase, $uid);
        if ($powerup >= WorldBoss::POWER_UP_MAX) {
            $return['status'] = 4;
            $return['powerup'] = $powerup;
            return $return;
        }
        $gold = (bool)$ctx->input('gold', 0);
        $silver = 0;
        $goldCost = 0;
        if ($gold) {
            $goldCost = self::POWERUP_GOLD_COST;
            if (!Member::deductCurrency($uid, gold: $goldCost)) {
                // 金币不足
                $return['status'] = 2;
            } else {
                $return['all_gold'] = self::gold($uid);
                // 消费统计记录
                StatPay::record($uid, 'worldboss_powerup', $goldCost);
            }
        } else {
            $silver = self::powerUpPay($member);
            if (!Member::deductCurrency($uid, silver: $silver)) {
                // 银币不足
                $return['status'] = 3;
            } else {
                $return['all_silver'] = (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('silver');
            }
        }
        if ($return['status'] === 0) {
            $newPowerup = WorldBoss::incrPowerUp($phase, $uid);
            if ($newPowerup === null) {
                // 并发鼓舞已到上限：退回本次花费
                Member::addCurrency($uid, silver: $gold ? 0 : $silver, gold: $goldCost);
                unset($return['all_gold'], $return['all_silver']);
                $return['status'] = 4;
                $return['powerup'] = WorldBoss::powerUp($phase, $uid);
                return $return;
            }
            // 鼓舞成功
            $return['status'] = 1;
            $return['powerup'] = $newPowerup;
        }
        return $return;
    }

    /**
     * 领取奖励: c=worldboss&m=reward
     *
     * status	1 领取成功（演武榜积分）
     *			2 没有参与活动 或 已经领取奖励
     */
    public static function reward(Context $ctx): array
    {
        $now = BossClock::now();
        $return = ['status' => 0, 'reward' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            $return['status'] = 2;
            return $return;
        }
        $uid = (int)$member['uid'];
        $state = WorldBoss::context($uid, $now);
        $phase = $state['phase'];
        // 活动结束且有伤害记录的情况才能领取奖励
        if ($state['countdown'] <= 0 && WorldBoss::myHit($phase, $uid) > 0) {
            $reward = WorldBoss::reward($phase, $uid);
            // 删除伤害记录，防止重复领取
            if ($reward['intnum'] > 0 && WorldBoss::delMyHit($phase, $uid)) {
                // 获得演武榜积分
                $return['status'] = 1;
                $return['reward']['intnum'] = $reward['intnum'];
                WorldBoss::addArenaInt($uid, $reward['intnum'], 'worldboss', 'reward', $now);
            } else {
                $return['status'] = 2;
            }
        } else {
            $return['status'] = 2;
        }
        return $return;
    }

    /**
     * 伤害榜与奖励（index status 1：活动结束后，或 boss 已死）
     *
     * 我不在 TOP 5 时：伤害不小于上榜最小伤害的，显示为「上榜最小伤害 × (100 - 名次) / 100」（旧版同）。
     *
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>}
     */
    private static function rewardView(int $phase, int $uid): array
    {
        $top = self::topRank($phase);
        $find = false;
        $topHit = 0;
        $myrank = 0;
        $myhit = 0;
        foreach ($top as $value) {
            if ((int)$value['uid'] === $uid) {
                $myrank = $value['rank'];
                $myhit = (int)$value['hit'];
                $find = true;
            }
            if ($topHit === 0 || $topHit > (int)$value['hit']) {
                $topHit = (int)$value['hit'];
            }
        }
        if (!$find) {
            $myrank = WorldBoss::myRank($phase, $uid);
            $myhit = WorldBoss::myHit($phase, $uid);
            if ($myhit >= $topHit) {
                $myhit = (int)($topHit * (100 - $myrank) / 100);
            }
        }
        $reward = WorldBoss::reward($phase, $uid);
        $reward['rank'] = $myrank;
        $reward['hit'] = $myhit;
        return [$top, $reward];
    }

    /**
     * TOP 5 排行，前 $num 名补玩家信息（控制器 get_top_rank）
     *
     * @return list<array<string, mixed>>
     */
    private static function topRank(int $phase, int $num = 5): array
    {
        return WorldBoss::withMemberInfo(WorldBoss::rankList($phase, WorldBoss::HIT_TOP_NUM), $num);
    }

    /**
     * 银币鼓舞花费（power_up_pay）
     *
     * @param array<string, mixed> $member
     */
    private static function powerUpPay(array $member): int
    {
        return (int)$member['level'] * 500;
    }

    /**
     * 最后一击聊天公告（get_message_str(12, from 最后一击玩家) + send_message_to_all(…, 7)）
     */
    private static function sendFinalNotice(int $finalUid, int $uid): void
    {
        $sender = Chat::sender($uid);
        $final = self::member($finalUid);
        $message = Chat::getMessageStr(12, [
            'from' => ['uid' => $finalUid, 'nickname' => $final['nickname'] ?? null],
        ], $sender);
        Chat::sendToAll($message, Chat::TYPE_SYSTEM, 0, 0, $sender);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        return DB::table('member')->select('uid', 'nickname', 'level', 'gender', 'gold', 'silver', 'country')
            ->where('uid', $uid)->whereNull('deleted_at')->first();
    }

    private static function gold(int $uid): int
    {
        return (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold');
    }
}
