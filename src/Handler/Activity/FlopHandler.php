<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Flop as FlopRule;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Flop;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberActivity;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 运营活动 - 四方来朝（移植旧版 controllers/flop.php + models/flop_model.php）
 *
 * 活动时间取 ActivityTime 的 flop（旧版数据 2016-12-31 已结束，接口回活动已结束）。翻牌次数由元宝 / 军令 / 突飞卡消耗换算
 * （Model\Flop::addSpend，接在 Member::deductCurrency 与 FreeLeap::sub 上）。客户端 worshippop.js 调用 index、turnover（id 1 ~ 6）。
 * 活动结束后的计划任务见 Cron\FlopCron（push_flop_reward 自动领取剩余次数、clear_flop_times）。
 * 当前时间取 BossClock（生产恒为 time()）。
 *
 * 与旧版的差别：翻牌在玩家行锁里以 floptimes 条件更新扣次数（旧版先读后写，并发可多翻）。
 */
final class FlopHandler
{
    /**
     * 初始化: c=flop&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         * -3 获取规则失败
         * -2 获取活动信息失败
         * -1 活动已结束
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $activityInfo = Flop::activity();
        if (!Flop::isOpen($now) || $uid <= 0) {
            // 获取活动信息失败
            $return['status'] = -1;
            return $return;
        }

        // [用户模型]获取玩家活动信息
        MemberActivity::ensure($uid);
        $activity = self::activityRow($uid);
        if ($activity === null) {
            // 获取活动信息失败
            $return['status'] = -2;
            return $return;
        }

        $info = [
            // 银币消耗废弃、兼容性代码
            'sivler' => 0,
            'gold' => self::left($activity['flopgoldtimes'], Flop::SPEND_GOLD, $activity['flopgold']),
            'leap' => self::left($activity['flopleaptimes'], Flop::SPEND_LEAP, $activity['flopleap']),
            'act' => self::left($activity['flopacttimes'], Flop::SPEND_ACT, $activity['flopact']),
        ];

        $return['status'] = 1;
        // 剩余翻牌次数
        $return['times'] = $activity['floptimes'];
        $return['info'] = $info;
        $return['cd'] = $activityInfo['endtime'] - $now;
        return $return;
    }

    /**
     * 翻牌: c=flop&m=turnover（id 翻开的牌位 1 ~ 6）
     */
    public static function turnover(Context $ctx): array
    {
        /**
         * -5 更新活动信息失败
         * -4 获取奖励失败
         * -3 获取活动信息失败
         * -2 活动已结束
         * -1 参数不合法
         *  0 失败
         *  1 成功
         *  2 剩余次数不足
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);

        if ($id < 1 || $id > 6) {
            // 参数不合法
            $return['status'] = -1;
            return $return;
        }
        if (!Flop::isOpen(BossClock::now()) || $uid <= 0) {
            // 活动已结束
            $return['status'] = -2;
            return $return;
        }
        // [用户模型]获取活动信息
        MemberActivity::ensure($uid);
        return Member::locked($uid, static function () use ($uid, $id, $return): array {
            $activity = self::activityRow($uid);
            $member = DB::table('member')->select('uid', 'level')->where('uid', $uid)->whereNull('deleted_at')->first();
            if ($activity === null || $member === null) {
                // 获取活动信息失败
                $return['status'] = -3;
                return $return;
            }
            // 取得剩余翻牌次数
            $times = $activity['floptimes'];
            if ($times < 1) {
                // 剩余次数不足
                $return['status'] = 2;
                return $return;
            }
            $level = (int)$member['level'];
            // 获取奖励：翻开的牌是抽到的奖励，其余 5 张按剩下的类型随机排列（各自再抽倍数，只展示）
            $idArr = range(1, 6);
            $reward = Flop::rewardInfo($level);
            unset($idArr[$reward['type'] - 1]);
            shuffle($idArr);
            $list = [];
            for ($i = $j = 0; $i < 6; $i++) {
                if ($id === $i + 1) {
                    $list[$i] = $reward;
                    $list[$i]['isown'] = 1;
                } else {
                    $list[$i] = Flop::rewardInfo($level, $idArr[$j]);
                    $list[$i]['isown'] = 0;
                    $j++;
                }
            }

            $data = ['floptimes' => $times - 1];
            $update = DB::table('member_activity')->where('uid', $uid)->where('floptimes', $times)->whereNull('deleted_at')->update($data);
            if ($update !== 1) {
                // 更新活动信息失败
                $return['status'] = -5;
                return $return;
            }

            $info = [];
            $reward['num'] *= $reward['multiply'];
            switch ($reward['type']) {
                case 1: // 元宝
                    Member::addCurrency($uid, gold: $reward['num']);
                    $info['gold'] = self::memberValue($uid, 'gold');
                    break;
                case 2: // 银币
                    Member::addCurrency($uid, silver: $reward['num']);
                    $info['silver'] = self::memberValue($uid, 'silver');
                    break;
                case 3: // 军令
                    Member::addCurrency($uid, act: $reward['num']);
                    $info['act'] = self::memberValue($uid, 'act');
                    break;
                case 4: // 初级突飞卡
                    FreeLeap::add($uid, $reward['num']);
                    break;
                case 5: // 中级突飞卡
                    FreeLeap::add($uid, $reward['num'] * 2);
                    break;
                case 6: // 高级突飞卡
                    FreeLeap::add($uid, $reward['num'] * 4);
                    break;
            }

            // 记录活动人次
            DB::table('flop_action_log')->insert(['uid' => $uid, 'level' => $level, 'dateline' => BossClock::now()]);

            $return['status'] = 1;
            $return['list'] = $list;
            $return['reward'] = $reward;
            $return['info'] = $info;
            $return['times'] = $data['floptimes'];
            return $return;
        });
    }

    /**
     * 距下一次翻牌还差的消耗（_get_info：规则价格 - 已累计）
     */
    private static function left(int $times, string $type, int $num): int
    {
        return (int)FlopRule::ROWS[Flop::ruleIndex($times)][$type] - $num;
    }

    /**
     * @return array<string, int>|null
     */
    private static function activityRow(int $uid): ?array
    {
        $columns = ['floptimes', 'flopgold', 'flopgoldtimes', 'flopact', 'flopacttimes', 'flopleap', 'flopleaptimes'];
        return MemberActivity::many([$uid], $columns)[$uid] ?? null;
    }

    private static function memberValue(int $uid, string $column): int
    {
        return (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value($column);
    }
}
