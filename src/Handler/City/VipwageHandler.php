<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;

use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\VipWage;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberActivity;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\Pay;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * VIP 工资（移植旧版 controllers/vipwage.php + models/vipwage_model.php，路径相对旧版 s1/application/）
 *
 * VIP 1 起每天可领一次本级工资（VipWage：每级三项，银币按主公等级倍乘）；双倍 VIP 工资活动 ActivityTime double_vip
 * 期间数量翻倍。领取时间存 member_activity.vipwage_dateline（按自然日判断，无需每日重置）。
 *
 * 客户端调用（viprewardpop.js）：index、get_vip_wage，全部移植。
 * 并发：领取以 vipwage_dateline 条件更新（今天还没领过才写入）占领取资格，同一天并发领取只发一次。
 *
 * 依赖未移植：春节送福活动计数 actnewyear_model->add_task_num(version, 25)（act_newyear 活动未移植）；
 * 军令 / 银币 / 声望 / 宝石流水（add_act / add_silver / add_reputation 的 act_log / silver_log / reputation_log）。
 */
final class VipwageHandler
{
    /** 开放状态（$_open，旧版恒为 1） */
    private const int OPEN = 1;

    /**
     * vip 工资初始化: c=vipwage&m=index
     *
     * list 为各级工资（银币项已乘主公等级），my_reward 为本级工资，get：0 不可领取（VIP 0）、1 可领取、2 今日已领取。
     */
    public static function index(Context $ctx): array
    {
        /**
         * -2 未开放
         * -1 没有工资信息
         *  1 成功
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = BossClock::now();
        $double = Pay::activity('double_vip', $now) !== null;

        // 开放状态
        if (!self::OPEN) {
            $return['status'] = -2;
            return $return;
        }

        // 获取 vip 奖励列表
        $list = array_values(VipWage::ROWS);
        if ($list === []) {
            $return['status'] = -1;
            return $return;
        }

        // 判断今天是否已经领取过 vip 工资了
        $dateline = self::wageDateline($uid);

        // 获取我的工资信息
        $myReward = [];
        $level = (int)$member['level'];
        $vip = (int)$member['vip'];
        foreach ($list as $key => $val) {
            for ($i = 1; $i <= 3; $i++) {
                // 双倍奖励
                if ($double) {
                    $list[$key]['get' . $i] *= 2;
                    $val['get' . $i] *= 2;
                }
                if ($val['type' . $i] === 1) {
                    $list[$key]['get' . $i] = $val['get' . $i] * $level;
                }
            }
            if ($val['vip'] === $vip) {
                for ($i = 1; $i <= 3; $i++) {
                    if ($val['type' . $i] === 1) {
                        $val['get' . $i] = $val['get' . $i] * $level;
                    }
                }
                $myReward = $val;
            }
        }

        // 判断领取状态
        $get = 0;
        if ($vip > 0) {
            $get = date('ymd', $now) === date('ymd', $dateline) ? 2 : 1;
        }

        $return['status'] = 1;
        $return['list'] = $list;
        $return['my_reward'] = $myReward;
        $return['get'] = $get;
        return $return;
    }

    /**
     * 领取 vip 工资: c=vipwage&m=get_vip_wage
     *
     * 工资类型：1 银币（× 主公等级）、2 突飞卡、3 军令、4 蓝宝石、5 黄宝石、6 紫宝石、7 原魂、8 玉石、9 声望。
     */
    public static function get_vip_wage(Context $ctx): array
    {
        /**
         * -5 未开放
         * -4 修改领奖状态失败（同一天并发领取的后到者）
         * -3 今日已领取
         * -2 没有本级工资信息
         * -1 VIP 0 不能领取
         *  1 成功
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        $now = BossClock::now();
        $double = Pay::activity('double_vip', $now) !== null;

        // 开放状态
        if (!self::OPEN) {
            $return['status'] = -5;
            return $return;
        }

        // 判断 vip 等级是否足够
        $vip = (int)$member['vip'];
        if ($vip === 0) {
            $return['status'] = -1;
            return $return;
        }

        // 获取工资信息
        $reward = VipWage::ROWS[$vip] ?? null;
        if ($reward === null) {
            $return['status'] = -2;
            return $return;
        }

        // 判断今日是否已经领取过了 vip 工资
        if (date('ymd', self::wageDateline($uid)) === date('ymd', $now)) {
            $return['status'] = -3;
            return $return;
        }

        // 修改领奖状态（今天还没领过才写入）
        $updated = DB::execute(
            'UPDATE `zc_sanguo_member_activity` SET `vipwage_dateline` = :now
             WHERE `uid` = :uid AND `vipwage_dateline` < :today AND `deleted_at` IS NULL',
            ['now' => $now, 'uid' => $uid, 'today' => strtotime(date('Y-m-d', $now))]
        );
        if ($updated !== 1) {
            $return['status'] = -4;
            return $return;
        }

        // 添加奖励
        $level = (int)$member['level'];
        for ($i = 1; $i <= 3; $i++) {
            // 双倍奖励
            if ($double) {
                $reward['get' . $i] *= 2;
            }
            $num = $reward['get' . $i];
            switch ($reward['type' . $i]) {
                case 1: // 银币
                    $reward['get' . $i] = $num * $level;
                    Member::addCurrency($uid, silver: $reward['get' . $i]);
                    break;
                case 2: // 突飞卡
                    DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->increment('freeleap', $num);
                    break;
                case 3: // 军令（add_act type 1：不受上限限制）
                    Member::addCurrency($uid, act: $num);
                    break;
                case 4: // 蓝宝石
                    TavernHandler::addJade($uid, 1, $num, 'vipwage', 'get_vip_wage');
                    break;
                case 5: // 金宝石
                    TavernHandler::addJade($uid, 2, $num, 'vipwage', 'get_vip_wage');
                    break;
                case 6: // 紫宝石
                    TavernHandler::addJade($uid, 3, $num, 'vipwage', 'get_vip_wage');
                    break;
                case 7: // 原魂
                    OwnSoul::addSoul($uid, $num, 0, 'vipwage', 'get_vip_wage', $now);
                    break;
                case 8: // 玉石
                    MemberJade::add($uid, $num, 'vipwage', 'get_vip_wage', $now);
                    break;
                case 9: // 声望
                    Member::addCurrency($uid, reputation: $num);
                    break;
            }
        }
        Redis::del('player:' . $uid);

        // 依赖未移植：春节送福活动计数 actnewyear_model->add_task_num(version, 25)

        // 成功返回
        $after = self::member($uid) ?? $member;
        $return['status'] = 1;
        $return['all_act'] = (int)$after['act'];
        $return['all_gold'] = (int)$after['gold'];
        $return['all_silver'] = (int)$after['silver'];
        return $return;
    }

    /**
     * 上次领取 vip 工资的时间（member_model->get_activity_info：没有活动记录时按旧版建档）
     */
    private static function wageDateline(int $uid): int
    {
        MemberActivity::ensure($uid);
        return (int)(DB::table('member_activity')->where('uid', $uid)->whereNull('deleted_at')->value('vipwage_dateline') ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        return DB::table('member')->select('uid', 'level', 'vip', 'act', 'gold', 'silver')->where('uid', $uid)->whereNull('deleted_at')->first();
    }
}
