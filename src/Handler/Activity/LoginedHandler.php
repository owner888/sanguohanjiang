<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\LoginedReward;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\Logined;
use Hanjiang\Model\Member;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 连续登录大礼包（移植旧版 controllers/logined.php；存储见 Model\Logined，奖励在 MasterData LoginedReward）
 *
 * 连续登录天数由每日计划任务累计（logined_log）；打开界面时按天数把第 1 ~ 15 天的礼包状态（logined_list）整理为
 * 可领取 / 未达成 / 已领取（超过 15 天按第 15 天）。奖励类型 1 银币、2 突飞卡、3 军令、4 黄宝石、5 紫宝石。
 * 客户端 loginedpop.js 调用 index、get_reward（id 第几天）。
 *
 * 与旧版的差别：领奖锁改为 Redis SET NX（旧版 get + setex）；领奖以「可领取 → 已领取」条件更新只成功一次——旧版只拒绝
 * 已领取 / 未达成，没有该天记录（没打开过界面）时照发奖励且记不上已领取，可无限领取，这里按未达成回 -2。
 */
final class LoginedHandler
{
    /** 领奖锁过期时间（旧版 60 秒） */
    private const int LOCK_TTL = 60;

    /**
     * 初始化接口: c=logined&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0, 'popo' => 1];
        $uid = MemberHandler::resolveUid($ctx);
        if ($uid <= 0) {
            return $return;
        }
        $now = time();
        $today = date('Ymd', $now);
        // 获取连续登陆奖励信息
        $reward = array_values(LoginedReward::ROWS);
        // 获取玩家登录信息
        $days = Logined::loginLog($uid);
        if ($days === null) {
            Logined::updateDay($uid, $now);
            $days = ['day' => 1];
        }
        $days = (int)$days['day'];

        if ($days === 1) {
            // 如果为登录第一天：查看用户记录
            if (Logined::rewardLog($uid) === []) {
                // 第一天为可领取状态，添加活动的记录
                Logined::addRewardLog($uid, $days, 1, $now);
                for ($i = 2; $i <= Logined::MAX_DAY; $i++) {
                    Logined::addRewardLog($uid, $i, 2, $now);
                }
            } else {
                // 不为空的原因是没有持续登录：判断是否可领取
                $result = Logined::byDay($uid, $days);
                if ($result === null || $today !== date('Ymd', $result['dateline']) || $result['status'] !== 3) {
                    Logined::updateRewardLog($uid, $days, 1, $now);
                    for ($i = 2; $i <= Logined::MAX_DAY; $i++) {
                        // 未达成
                        Logined::updateRewardLog($uid, $i, 2, $now);
                    }
                }
            }
        } elseif ($days > 1 && $days <= Logined::MAX_DAY) {
            // 判断是否可领取
            $result = Logined::byDay($uid, $days);
            if ($result !== null) {
                if ($today !== date('Ymd', $result['dateline']) && $result['status'] === 3) {
                    // 本身必须是可领取状态，其余从数据库取出（旧版从当天起都改为未达成，当天随后被下面改回可领取）
                    Logined::updateRewardLog($uid, $days, 1, $now);
                    for ($i = $days; $i <= Logined::MAX_DAY; $i++) {
                        Logined::updateRewardLog($uid, $i, 2, $now);
                    }
                }
                foreach (Logined::dayLog($uid, $days) as $key => $val) {
                    if ($val['status'] === 2) {
                        // 登录了但是没进连续登陆界面
                        Logined::updateRewardLog($uid, $key + 1, 1, $now);
                    }
                }
            } else {
                // 为空的结果是第一天登陆过但是没进入连续登陆界面
                for ($i = 1; $i <= $days; $i++) {
                    Logined::addRewardLog($uid, $i, 1, $now);
                }
                // 添加活动的记录
                for ($j = $days + 1; $j <= Logined::MAX_DAY; $j++) {
                    Logined::addRewardLog($uid, $j, 2, $now);
                }
            }
        } else {
            // 大于15天全按第15天计算
            $result = Logined::byDay($uid, Logined::MAX_DAY);
            if ($result !== null) {
                if ($today !== date('Ymd', $result['dateline']) && $result['status'] === 3) {
                    // 领取状态
                    Logined::updateRewardLog($uid, Logined::MAX_DAY, 1, $now);
                }
                foreach (Logined::dayLog($uid, Logined::MAX_DAY) as $key => $val) {
                    if ($val['status'] === 2) {
                        Logined::updateRewardLog($uid, $key + 1, 1, $now);
                    }
                }
            } else {
                for ($i = 1; $i <= Logined::MAX_DAY; $i++) {
                    Logined::addRewardLog($uid, $i, 1, $now);
                }
            }
        }

        $result = Logined::rewardLog($uid);
        $return['popo'] = self::listPopo($result);
        $return['day'] = $days;
        $return['list'] = self::withStatus($reward, $result);
        $return['status'] = 1;
        return $return + self::balances($uid);
    }

    /**
     * 领取持续登录大礼包奖励: c=logined&m=get_reward（id 第几天）
     */
    public static function get_reward(Context $ctx): array
    {
        /**
         * -7 操作太快
         * -2 已领取或未达成
         * -1 参数错误 / 奖励信息错误 / 没有登录记录
         *  1 成功
         */
        $return = ['status' => 0, 'popo' => 1];
        $uid = MemberHandler::resolveUid($ctx);
        if ($uid <= 0) {
            return $return;
        }
        // 增加锁标志
        $redis = Redis::instance();
        if ($redis === null || !(bool)$redis->set(Logined::LOCK_KEY . $uid, '1', ['nx', 'ex' => self::LOCK_TTL])) {
            $return['status'] = -7;
            return $return;
        }
        try {
            return self::getReward($uid, (int)$ctx->input('id', 0), $return);
        } finally {
            // 解锁
            $redis->del(Logined::LOCK_KEY . $uid);
        }
    }

    /**
     * @param array<string, mixed> $return
     * @return array<string, mixed>
     */
    private static function getReward(int $uid, int $id, array $return): array
    {
        $now = time();
        if ($id === 0 || $id > Logined::MAX_DAY) {
            $return['status'] = -1;
            return $return;
        }
        // 判断本用户是否已将领取过奖励（改为已领取，只有可领取的那一天能成功）
        if (!Logined::claim($uid, $id, $now)) {
            $return['status'] = -2;
            return $return;
        }
        // 通过id寻找信息，添加奖励(1银币2突飞卡3军令4黄宝石5紫宝石)
        $reward = LoginedReward::ROWS[$id] ?? null;
        for ($i = 1; $reward !== null && $i <= 4; $i++) {
            $value = (int)$reward['getvalue' . $i];
            switch ((int)$reward['gettype' . $i]) {
                case 1: // 银币
                    Member::addCurrency($uid, silver: $value);
                    break;
                case 2: // 突飞卡
                    FreeLeap::add($uid, $value, $now);
                    break;
                case 3: // 军令
                    Member::addCurrency($uid, act: $value);
                    break;
                case 4: // 黄宝石
                    TavernHandler::addJade($uid, 2, $value);
                    break;
                case 5: // 紫宝石
                    TavernHandler::addJade($uid, 3, $value);
                    break;
            }
        }

        // 获取礼包信息与用户log信息
        $result = Logined::rewardLog($uid);
        $list = self::withStatus(array_values(LoginedReward::ROWS), $result);
        $return['popo'] = self::listPopo($result);
        // 获取玩家登录信息
        $days = Logined::loginLog($uid);
        if ($days === null) {
            $return['status'] = -1;
            return $return;
        }
        $return['day'] = $days['day'];
        $return['status'] = 1;
        $return['list'] = $list;
        return $return + self::balances($uid);
    }

    /**
     * 奖励列表按位置合入礼包状态（$reward[$key]['type'] = 第 $key 条记录的 status）
     *
     * @param list<array<string, mixed>> $reward
     * @param list<array<string, int>> $result
     * @return list<array<string, mixed>>
     */
    private static function withStatus(array $reward, array $result): array
    {
        foreach ($reward as $key => $val) {
            if (isset($result[$key])) {
                $reward[$key]['type'] = $result[$key]['status'];
            }
        }
        return $reward;
    }

    /**
     * 提醒状态：从头数，遇到可领取为 1，否则为最后一条的判断结果（没有记录为 1）
     *
     * @param list<array<string, int>> $result
     */
    private static function listPopo(array $result): int
    {
        $popo = 1;
        foreach ($result as $val) {
            if ($val['status'] !== 1) {
                $popo = 0;
            } else {
                $popo = 1;
                break;
            }
        }
        return $popo;
    }

    /**
     * @return array{all_silver: int, all_gold: int, all_act: int}
     */
    private static function balances(int $uid): array
    {
        $member = DB::table('member')->select('silver', 'gold', 'act')->where('uid', $uid)->whereNull('deleted_at')->first() ?? [];
        return [
            'all_silver' => (int)($member['silver'] ?? 0),
            'all_gold' => (int)($member['gold'] ?? 0),
            'all_act' => (int)($member['act'] ?? 0),
        ];
    }
}
