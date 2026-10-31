<?php

declare(strict_types=1);

namespace Hanjiang\Model;

/**
 * 春节活动（年兽 newyearboss、转盘 springlottery、商城 springshop）共用的奖励类型里的简单资源
 *
 * 旧版三个控制器各自用同一套 switch 发奖（类型：1 元宝、2 银币、3 黄宝石、4 紫宝石、5 原魂、6 玉石、7 声望、9 宝石碎片、
 * 10 突飞卡、12 蓝宝石、14 军令、15 精炼石、16 五彩石、17 上古血玉）；装备 8、坐骑 11、武将 13、精华 18、锻造材料 19 的返回值
 * 与广播各控制器不同，由调用方处理。流水的 class / method 为旧版控制器与方法名。
 */
final class SpringReward
{
    /**
     * 按类型发放（member_model / tavern_model 的 add_*），返回 true 表示该类型由这里处理
     */
    public static function grant(int $uid, int $type, int $value, string $class, string $method, int $now): bool
    {
        switch ($type) {
            case 1: // 元宝
                Member::addCurrency($uid, gold: $value);
                return true;
            case 2: // 银币
                Member::addCurrency($uid, silver: $value);
                return true;
            case 3: // 黄宝石
                TraderLog::addJade($uid, 2, $value, class: $class, method: $method, now: $now);
                return true;
            case 4: // 紫宝石
                TraderLog::addJade($uid, 3, $value, class: $class, method: $method, now: $now);
                return true;
            case 5: // 原魂
                OwnSoul::addSoul($uid, $value, 0, $class, $method, $now);
                return true;
            case 6: // 玉石
                MemberJade::add($uid, $value, $class, $method, $now);
                return true;
            case 7: // 声望
                Member::addReputation($uid, $value);
                return true;
            case 9: // 宝石碎片
                OwnGem::addChip($uid, $value, $class, $method, $now);
                return true;
            case 10: // 突飞卡
                FreeLeap::add($uid, $value, $now);
                return true;
            case 12: // 蓝宝石
                TraderLog::addJade($uid, 1, $value, class: $class, method: $method, now: $now);
                return true;
            case 14: // 军令（add_act(1, n)：不受军令上限限制）
                Member::addCurrency($uid, act: $value);
                return true;
            case 15: // 精炼石
                MemberStone::add($uid, $value, MemberStone::REFINE, $class, $method, $now);
                return true;
            case 16: // 五彩石
                MemberStone::add($uid, $value, MemberStone::COLOR, $class, $method, $now);
                return true;
            case 17: // 上古血玉
                MemberStone::add($uid, $value, MemberStone::BLOOD, $class, $method, $now);
                return true;
        }
        return false;
    }
}
