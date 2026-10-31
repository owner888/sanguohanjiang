<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;

use Hanjiang\Handler\Activity\TreasuremapHandler;
use Hanjiang\Handler\Pvp\SiegeHandler;
use Hanjiang\MasterData\Price;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\DailyTask;
use Hanjiang\Model\Exercise;
use Hanjiang\Model\Expostulation;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberActivity;
use Hanjiang\Model\StatPay;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 主城征收 / 强征（移植旧版 controllers/city.php + models/city_model.php，征收 cd 取 models/cd_model.php 的
 * get_collect / update_times / clear_collect）
 *
 * 征收次数 member_field.levytimes 由每日 7 点的征收计划任务发放（Member::levyCron，每天 12 次、最多 50），
 * 征收得银币 = 主公等级 × 500（整军演武活动期间按排行加成）；强征按 MasterData Price 的 levy 收元宝（第 50 次起 50 元宝），
 * 次数记 member_field.imposetimes（每日 7 点归零）。整军演武（Model\Exercise，ActivityTime 的 exercise，旧版数据中已结束）期间
 * 征收 / 强征累计排行与礼包，并记 city_action_log。征收与强征都计日常任务 2（41 级以上）与国家谏言 5（有国家时）。
 * 客户端 levypop.js 调用 index、impose、impress、clear_cd（type 1）。
 *
 * 旧版 vip_model->cd_status() 恒为 1（免 cd）：征收不检查、不增加 cd，index 的 cd 恒为 {status 0, cd 0}；
 * clear_cd 照旧版读 levystatus / levydateline（cd_status 不管它），而征收从不写 cd，所以只有旧数据有 cd 时才能清。
 * 当前时间取 BossClock（生产恒为 time()）。
 *
 * 征收（主公 17 级以上）/ 强征有 30% 概率获得一块藏宝图（TreasuremapHandler::getMap），返回在 treasuremap 里。
 * 与旧版的差别：征收 / 强征在玩家行锁里执行，征收次数以条件更新扣减（旧版先读后写，并发可多征）；强征扣元宝失败（并发花掉）回 -1。
 */
final class CityHandler
{
    /** 强征第 50 次起的价格（gold_spent） */
    private const int MAX_IMPOSE_PRICE = 50;

    /** 每次征收的银币系数（征收银币 = 主公等级 × 500） */
    private const int LEVY_SILVER = 500;

    /** 等级超过 26 时征收要检查演武征收锁 */
    private const int EXERCISE_LOCK_LEVEL = 26;

    /** 藏宝图活动的等级（征收时主公等级 > 16） */
    private const int TREASUREMAP_LEVEL = 16;

    /**
     * 获取信息: c=city&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0, 'times' => 0, 'gold' => 0, 'cd' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        // 查询国家占领都城加成
        SiegeHandler::getCityEffect($member, BossClock::now());
        // 取扩展信息
        $field = self::field($uid);
        $return['status'] = 1;
        // 取扩展信息中的剩余征收次数信息
        $return['times'] = (int)($field['levytimes'] ?? 0);
        // 调用主城的model取强征花费
        $return['gold'] = self::goldSpent((int)($field['imposetimes'] ?? 0));
        // 判断cd免费状态是否开启，开启则不增加cd（vip_model->cd_status() 恒为 1）
        $return['cd'] = ['status' => 0, 'cd' => 0];
        return $return;
    }

    /**
     * 征收: c=city&m=impose
     */
    public static function impose(Context $ctx): array
    {
        /**
         * -3 整军演武期间征收过快（0.5 秒内）
         * -2 征收 cd 中（cd_status 恒为 1，不会出现）
         * -1 剩余次数不足
         *  1 成功
         */
        $return = ['status' => 0, 'silver' => 0, 'levytimes' => 0, 'cd' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        if (self::member($uid) === null) {
            return $return;
        }
        return Member::locked($uid, static function () use ($uid, $return): array {
            $now = BossClock::now();
            $member = self::member($uid);
            $level = (int)$member['level'];
            $exercise = Exercise::isOpen($now);
            // 取扩展信息
            $field = self::field($uid);
            if ($field === null || (int)$field['levytimes'] === 0) {
                // 剩余次数不足
                $return['status'] = -1;
                return $return;
            }
            if ($level > self::EXERCISE_LOCK_LEVEL && $exercise && Exercise::isLock($uid)) {
                $return['status'] = -3;
                return $return;
            }
            // 时间30分钟必须减完了才能点：vip_model->cd_status() 恒为 1，cd 恒为 {status 0, cd 0}，-2 不会出现

            // 整军演武活动加成
            [$addition, $activity] = self::exerciseAddition($uid, $exercise, $now);
            // 征收成功
            $return['silver'] = (int)($level * self::LEVY_SILVER * $addition / 100);
            $return['addition'] = $addition;
            Member::addCurrency($uid, silver: $return['silver']);
            $return['all_silver'] = self::memberValue($uid, 'silver');
            // 判断cd免费状态是否开启，开启则不增加cd（cd_status 恒为 1）
            $return['cd'] = 0;
            $return['levytimes'] = self::timesSpent($uid, $field, $activity);
            $return['gift'] = 0;
            // 藏宝图活动（主公 16 级以上：treasuremap_model->get_map）
            $return['treasuremap'] = $level > self::TREASUREMAP_LEVEL ? TreasuremapHandler::getMap($member, $now) : [];
            $return['status'] = 1;
            // 整军演武活动
            if ($exercise) {
                $levytimes = (int)$field['imposetimes'] + (int)$field['levyall'] + 1;
                // 初始化活动信息
                MemberActivity::ensure($uid);
                $ownGift = self::exerciseGift($uid);
                // 已拥有礼包数
                $return['exercisetimes'] = $ownGift;
                $return['gift'] = self::updateExercise($uid, $levytimes, $ownGift, $level, $now);
                Exercise::setLock($uid);
            } else {
                $return['addition'] = 0;
            }
            self::taskAction($member);
            // 整军活动期间记录人次
            if ($exercise) {
                self::addActionLog($uid, 0, $level, $now);
            }
            return $return;
        });
    }

    /**
     * 强征: c=city&m=impress
     */
    public static function impress(Context $ctx): array
    {
        /**
         * -1 强征需要元宝不足
         *  1 成功
         */
        $return = ['status' => 0, 'silver' => 0, 'gold' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        if (self::member($uid) === null) {
            return $return;
        }
        return Member::locked($uid, static function () use ($uid, $return): array {
            $now = BossClock::now();
            $member = self::member($uid);
            $level = (int)$member['level'];
            $exercise = Exercise::isOpen($now);
            // 取扩展信息
            $field = self::field($uid);
            // 取征收需要金币
            $rule = self::goldSpent((int)($field['imposetimes'] ?? 0));
            if ($field === null || (int)$member['gold'] < $rule) {
                // 强征需要金币不足
                $return['status'] = -1;
                return $return;
            }
            // 整军演武活动加成
            [$addition, $activity] = self::exerciseAddition($uid, $exercise, $now);
            if (!Member::deductCurrency($uid, gold: $rule)) {
                // 并发花掉了元宝（旧版不检查 sub_gold 的结果）
                $return['status'] = -1;
                return $return;
            }
            // 强征成功
            $return['silver'] = (int)($level * self::LEVY_SILVER * $addition / 100);
            $return['addition'] = $addition;
            Member::addCurrency($uid, silver: $return['silver']);
            // 返回剩余元宝和银币
            $return['all_silver'] = self::memberValue($uid, 'silver');
            $return['all_gold'] = self::memberValue($uid, 'gold');
            // 强征次数增加（impose_spent）
            DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['imposetimes' => (int)$field['imposetimes'] + 1]);
            $return['gold'] = self::goldSpent((int)$field['imposetimes'] + 1);
            $return['gift'] = 0;
            // 藏宝图活动（treasuremap_model->get_map）
            $return['treasuremap'] = TreasuremapHandler::getMap($member, $now);
            $return['status'] = 1;
            // 整军演武活动（强征不加演武征收锁，同旧版）
            if ($exercise) {
                $levytimes = (int)$field['imposetimes'] + (int)$field['levyall'] + 1;
                MemberActivity::ensure($uid);
                $ownGift = self::exerciseGift($uid);
                $return['exercisetimes'] = $ownGift;
                $return['gift'] = self::updateExercise($uid, $levytimes, $ownGift, $level, $now);
            } else {
                $return['addition'] = 0;
            }
            // 消费统计记录（旧版记的是下一次强征的价格）
            StatPay::record($uid, 'qiangzheng', $return['gold']);
            self::taskAction($member);
            // 整军活动期间记录人次
            if ($exercise) {
                self::addActionLog($uid, $rule, $level, $now);
            }
            return $return;
        });
    }

    /**
     * 清除征收 cd: c=city&m=clear_cd（type 1 清除，其他只返回价格）
     */
    public static function clear_cd(Context $ctx): array
    {
        /**
         * -3 清除失败
         * -2 金币不足
         * -1 不需要清除
         *  0 只查询价格（type 不为 1）
         *  1 成功
         */
        $return = ['gold' => 0, 'status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $type = (int)$ctx->input('type', 0);
        if (self::member($uid) === null) {
            return $return;
        }
        return Member::locked($uid, static function () use ($uid, $type, $return): array {
            $now = BossClock::now();
            $cd = self::collectCd($uid, $now);
            if ($cd['cd'] === 0) {
                // 不需要清除
                $return['status'] = -1;
                return $return;
            }
            $return['gold'] = (int)ceil($cd['cd'] / 600);
            if (self::memberValue($uid, 'gold') < $return['gold']) {
                // 金币不足
                $return['status'] = -2;
                return $return;
            }
            if ($type === 1) {
                if (Member::deductCurrency($uid, gold: $return['gold'])) {
                    // 成功
                    $return['status'] = 1;
                    $return['all_gold'] = self::memberValue($uid, 'gold');
                    // clear_collect
                    DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->update(['levystatus' => 0, 'levydateline' => $now]);
                    Redis::del('player:' . $uid);
                    // 消费统计记录
                    StatPay::record($uid, 'zhengshou_cd', $return['gold']);
                } else {
                    // 清除失败
                    $return['status'] = -3;
                }
            }
            return $return;
        });
    }

    /**
     * 强征花费元宝（city_model->gold_spent：第 $impose + 1 次的 Price.levy，50 次起 50；取不到为 0）
     */
    public static function goldSpent(int $impose): int
    {
        $impose++;
        if ($impose >= 50) {
            // 大于等于50次，价格都是50金币
            return self::MAX_IMPOSE_PRICE;
        }
        return (int)(Price::ROWS[$impose]['levy'] ?? 0);
    }

    /**
     * 征收 cd（cd_model->get_collect：levystatus 与 levydateline 剩余秒数）
     *
     * @return array{status: int, cd: int}
     */
    public static function collectCd(int $uid, int $now): array
    {
        $field = self::field($uid);
        if ($field === null) {
            return ['status' => 0, 'cd' => 0];
        }
        $left = (int)$field['levydateline'] - $now;
        return ['status' => (int)$field['levystatus'], 'cd' => $left < 0 ? 0 : $left];
    }

    /**
     * 整军演武加成：活动期间特殊时段 300，否则在排行榜上按名次取 ExerciseAddition；不在活动期间 100
     *
     * @return array{0: int, 1: int} [加成百分比, 是否演武活动]
     */
    private static function exerciseAddition(int $uid, bool $exercise, int $now): array
    {
        $addition = 100;
        $activity = 0;
        if ($exercise) {
            if (Exercise::specialAddition($now)) {
                // 特殊奖励
                $addition = 300;
            } else {
                // [整军model]获取排行榜
                $list = Exercise::rankList();
                if ($list !== []) {
                    // 搜索自己是否在排行榜中
                    $rank = array_search($uid, $list, true);
                    if ($rank !== false) {
                        // 获取加成
                        $addition = Exercise::addition($rank + 1);
                    }
                }
            }
            $activity = 1;
        }
        return [$addition, $activity];
    }

    /**
     * 征收次数消耗（times_spent：剩余次数 - 1、总征收次数 + 1；以剩余次数条件更新，成功返回新的剩余次数，否则 0）
     *
     * @param array<string, mixed> $field
     */
    private static function timesSpent(int $uid, array $field, int $activity): int
    {
        $levytimes = (int)$field['levytimes'];
        if ($levytimes === 0) {
            return 0;
        }
        $data = ['levytimes' => $levytimes - 1, 'levyall' => (int)$field['levyall'] + 1];
        $affected = DB::table('member_field')->where('uid', $uid)->where('levytimes', $levytimes)->whereNull('deleted_at')->update($data);
        Redis::del('player:' . $uid);
        return $affected > 0 ? $data['levytimes'] : 0;
    }

    /**
     * 更新整军演武排行与礼包（_update_exercise）
     */
    private static function updateExercise(int $uid, int $levytimes, int $ownGift, int $level, int $now): int
    {
        $return = 0;
        // 更新排行榜
        $update = Exercise::updateRank($levytimes, $uid);
        $times = Exercise::levytimes($uid);
        if ($times !== $levytimes && $update === 1) {
            // 排行与库里的次数对不上：清除整军排行，按今日征收次数重建
            Exercise::clearRank();
            foreach (Exercise::exerciseRank($now) as $value) {
                Exercise::updateRank($value['times'], $value['uid']);
            }
        }
        // 根据征收次数获取礼包
        $gift = Exercise::gift($levytimes);
        if ($gift > 0) {
            $gift += $ownGift;
            if (Exercise::updateGift($uid, $gift, $level) === 1) {
                $return = $gift;
            }
        }
        return $return;
    }

    /**
     * 日常任务（2 是征收，41 级以上）与国家谏言任务（5 是征收，有国家时）
     *
     * @param array<string, mixed> $member
     */
    private static function taskAction(array $member): void
    {
        if ((int)$member['level'] >= DailyTask::OPEN_LEVEL) {
            DailyTask::action((int)$member['uid'], DailyTask::TYPE_LEVY);
        }
        if ((int)$member['country'] > 0) {
            Expostulation::action((int)$member['country'], 5);
        }
    }

    /**
     * 整军活动参加记录（city_model->add_action_log）
     */
    private static function addActionLog(int $uid, int $gold, int $level, int $now): void
    {
        DB::table('city_action_log')->insert(['uid' => $uid, 'gold' => $gold, 'level' => $level, 'dateline' => $now]);
    }

    private static function exerciseGift(int $uid): int
    {
        return (int)(MemberActivity::many([$uid], ['exercise_gift'])[$uid]['exercise_gift'] ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return $uid > 0 ? DB::table('member')->select('uid', 'nickname', 'level', 'country', 'gold', 'silver')->where('uid', $uid)->whereNull('deleted_at')->first() : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function field(int $uid): ?array
    {
        return DB::table('member_field')->select('levytimes', 'levyall', 'imposetimes', 'levystatus', 'levydateline')
            ->where('uid', $uid)->whereNull('deleted_at')->first();
    }

    private static function memberValue(int $uid, string $column): int
    {
        return (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value($column);
    }
}
