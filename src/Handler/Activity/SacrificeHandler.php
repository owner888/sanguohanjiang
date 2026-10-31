<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Price;
use Hanjiang\MasterData\Sacrifice;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberActivity;
use Hanjiang\Model\Pay;
use Hanjiang\Model\StatPay;
use Hanjiang\Model\TraderLog;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 运营活动：天神祭祀（移植旧版 controllers/sacrifice.php + models/sacrifice_model.php）
 *
 * 客户端（sacrificepop.js）调用：index / go_sacrifice(type 1 ~ 3)。与国家祭祀（c=countrysacrifice）是两个玩法。
 * 活动时间取 ActivityTime 的 sacrifice（旧版数据 2016-12-31 已结束，接口回 -1 / -2）；当前时间取 BossClock（生产恒为 time()）。
 * 花元宝祭祀得蓝 / 黄 / 紫宝石（trader_log.get1 ~ get3）：价格取 MasterData Price.jade{type}（第 N 次，表外 50 元宝），
 * 宝石数按 MasterData Sacrifice 的 multiply1 / 2 / 4 / 8 权重抽 1 / 2 / 4 / 8 个；当日次数记 member_activity.sacrifice1~3
 * （每日计划任务归零）。index 里没祭祀过的类型价格显示为 1（旧版如此，实际第一次花 Price 第 1 行的价格）。
 *
 * 与旧版的差别：go_sacrifice 在 Member::locked（玩家行锁）里执行。
 */
final class SacrificeHandler
{
    /** 运营活动名（ActivityTime.name） */
    private const string ACTIVITY = 'sacrifice';
    /** 表外次数的价格（sacrifice_model->get_price） */
    private const int MAX_PRICE = 50;

    /**
     * 初始化（获取天神祭祀花费）: c=sacrifice&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         * -3 获取宝石失败
         * -2 获取活动信息失败
         * -1 活动未开启
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0];
        $now = BossClock::now();
        $activity = Pay::activity(self::ACTIVITY, $now);
        if ($activity === null) {
            // 活动未开启
            $return['status'] = -1;
            return $return;
        }
        $uid = MemberHandler::resolveUid($ctx);
        // [用户模型]获取玩家活动信息
        $info = self::activityInfo($uid);
        if ($info === null) {
            // 获取活动信息失败
            $return['status'] = -2;
            return $return;
        }
        // 取得三种祭祀价格
        for ($i = 1; $i < 4; $i++) {
            if ($info['sacrifice' . $i] === 0) {
                $return['info'][$i] = 1;
            } else {
                $return['info'][$i] = self::price($info['sacrifice' . $i] + 1, $i);
            }
        }
        $jade = TraderLog::get($uid);
        $return['status'] = 1;
        $return['jade'][1] = $jade['get1'] ?? 0;
        $return['jade'][2] = $jade['get2'] ?? 0;
        $return['jade'][3] = $jade['get3'] ?? 0;
        $return['cd'] = (int)$activity['endtime'] - $now;
        return $return;
    }

    /**
     * 祭祀: c=sacrifice&m=go_sacrifice&type=1|2|3
     */
    public static function go_sacrifice(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $type = (int)$ctx->input('type', 0);
        return Member::locked($uid, static function () use ($uid, $type): array {
            /**
             * -9 获取宝石数目失败
             * -8 更新活动信息失败
             * -7 获取奖励失败
             * -6 扣金币失败
             * -5 获取祭祀奖励失败
             * -4 活动信息获取事变
             * -3 祭祀类型不正确
             * -2 活动已结束
             *  0 失败
             *  1 成功
             *  2 金币不足
             */
            $return = ['status' => 0, 'info' => []];
            $now = BossClock::now();
            if (Pay::activity(self::ACTIVITY, $now) === null) {
                // 获取活动信息失败
                $return['status'] = -2;
                return $return;
            }
            if (!in_array($type, [1, 2, 3], true)) {
                // 祭祀类型不正确
                $return['status'] = -3;
                return $return;
            }
            // [用户模型]获取活动信息
            $info = self::activityInfo($uid);
            $member = DB::table('member')->select('level', 'gold')->where('uid', $uid)->whereNull('deleted_at')->first();
            if ($info === null || $member === null) {
                // 活动信息获取不正确
                $return['status'] = -4;
                return $return;
            }
            // 取得祭祀次数
            $times = $info['sacrifice' . $type] + 1;
            // [祭祀模型]获取价格
            $price = self::price($times, $type);
            if ((int)$member['gold'] < $price) {
                // 金币不足
                $return['status'] = 2;
                return $return;
            }
            // [祭祀模型]获取奖励
            $reward = self::reward($type);
            if ($reward === []) {
                // 获取祭祀奖励失败
                $return['status'] = -5;
                return $return;
            }
            // 扣除金币
            if (!Member::deductCurrency($uid, gold: $price)) {
                // 扣金币失败
                $return['status'] = -6;
                return $return;
            }
            // 获得奖励
            $add = TraderLog::addJade($uid, $reward['type'], $reward['num'], class: 'sacrifice', method: 'go_sacrifice', now: $now);
            if ($add['status'] !== 1) {
                // 获取奖励失败
                $return['status'] = -7;
                return $return;
            }
            MemberActivity::update($uid, ['sacrifice' . $type => $times]);
            // 消费统计记录
            StatPay::record($uid, 'sacrifice', $price);
            // 记录活动人次
            DB::table('sacrifice_action_log')->insert([
                'uid' => $uid,
                'get' . $reward['type'] => $reward['num'],
                'gold' => $price,
                'level' => (int)$member['level'],
                'dateline' => $now,
            ]);
            // 获取下次花费
            $jade = TraderLog::get($uid);
            $return['status'] = 1;
            $return['jade'][1] = $jade['get1'] ?? 0;
            $return['jade'][2] = $jade['get2'] ?? 0;
            $return['jade'][3] = $jade['get3'] ?? 0;
            $return['info'] = $reward;
            $return['info']['gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
            $return['info']['next'] = self::price($times + 1, $type);
            return $return;
        });
    }

    /**
     * 玩家活动信息（get_activity_info：没有时建档）
     *
     * @return array<string, int>|null
     */
    private static function activityInfo(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        MemberActivity::ensure($uid);
        return MemberActivity::many([$uid], ['sacrifice1', 'sacrifice2', 'sacrifice3'])[$uid] ?? null;
    }

    /**
     * 获取祭祀花费（get_price：Price 第 $times 行的 jade{type}，表外 50）
     */
    private static function price(int $times, int $type): int
    {
        $row = Price::ROWS[$times] ?? null;
        return $row === null ? self::MAX_PRICE : (int)$row['jade' . $type];
    }

    /**
     * 祭祀奖励（reward：按 multiply1 / 2 / 4 / 8 的权重抽倍数，num 为宝石个数）
     *
     * @return array{num: int, type: int}|array{}
     */
    private static function reward(int $type): array
    {
        $rule = Sacrifice::ROWS[$type] ?? null;
        if ($rule === null) {
            return [];
        }
        $rulearr = [];
        for ($i = 1; $i <= 8; $i++) {
            if (isset($rule['multiply' . $i])) {
                $rulearr[$i] = (int)$rule['multiply' . $i];
            }
        }
        $roll = mt_rand(1, array_sum($rulearr));
        $cursor = 0;
        $num = 0;
        for ($i = 1; $i <= 8; $i++) {
            if (isset($rulearr[$i])) {
                $cursor += $rulearr[$i];
                $num = $i;
            }
            if ($cursor >= $roll) {
                break;
            }
        }
        return ['num' => $num, 'type' => $type];
    }
}
