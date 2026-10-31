<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Database\DB;

/**
 * 按类型发奖励（移植旧版 models/send_reward_model.php；国家税金商城、神矿翻倍奖励共用）
 *
 * 类型：1 元宝、2 银币、3 黄宝石、4 紫宝石、5 原魂、6 玉石、7 声望、9 宝石碎片、10 突飞卡、11 蓝宝石、13 军令、
 * 14 精炼石、15 五彩石、16 上古血石、27 玫瑰花、28 钻石、29 ~ 31 星魄、32 将军令；8 装备 / 12 武将 / 17 ~ 25 装备精华 /
 * 26 坐骑的分支旧版已注释掉，不发。
 */
final class SendReward
{
    /**
     * 发送奖励（移植 send_reward_model->send_reward；旧版 type 1 元宝没有 break，会再按 type 2 加同样数量的银币，照搬）
     *
     * @return array<string, mixed> type / value，加银币 / 军令时带 update
     */
    public static function send(int $uid, int $type, int $value, string $class, string $method, ?int $now = null): array
    {
        $now ??= time();
        $return = ['type' => $type, 'value' => $value];
        switch ($type) {
            case 1: // 元宝
                Member::addCurrency($uid, gold: $value);
                // no break（旧版漏写 break）
            case 2: // 银币
                Member::addCurrency($uid, silver: $value);
                $return['update']['silver'] = (int)DB::table('member')->where('uid', $uid)->value('silver');
                break;
            case 3: // 黄宝石
                TraderLog::addJade($uid, 2, $value, class: $class, method: $method, now: $now);
                break;
            case 4: // 紫宝石
                TraderLog::addJade($uid, 3, $value, class: $class, method: $method, now: $now);
                break;
            case 5: // 原魂
                Member::addCurrency($uid, soul: $value);
                break;
            case 6: // 玉石
                MemberJade::add($uid, $value, $class, $method, $now);
                break;
            case 7: // 声望
                Member::addReputation($uid, $value);
                break;
            case 9: // 宝石碎片
                OwnGem::addChip($uid, $value, $class, $method, $now);
                break;
            case 10: // 突飞卡
                FreeLeap::add($uid, $value, $now);
                break;
            case 11: // 蓝宝石
                TraderLog::addJade($uid, 1, $value, class: $class, method: $method, now: $now);
                break;
            case 13: // 军令
                Member::addCurrency($uid, act: $value);
                $return['update']['act'] = (int)DB::table('member')->where('uid', $uid)->value('act');
                break;
            case 14: // 精炼石
                MemberStone::add($uid, $value, MemberStone::REFINE, $class, $method, $now);
                break;
            case 15: // 五彩石
                MemberStone::add($uid, $value, MemberStone::COLOR, $class, $method, $now);
                break;
            case 16: // 上古血石
                MemberStone::add($uid, $value, MemberStone::BLOOD, $class, $method, $now);
                break;
            case 27: // 玫瑰花
                MemberIntegral::change($uid, $value, 'beauty_rose', 1, $class, $method, $now);
                break;
            case 28: // 钻石
                MemberIntegral::change($uid, $value, 'beauty_diamond', 1, $class, $method, $now);
                break;
            case 29: // 幽蓝星魄
            case 30: // 铭黄星魄
            case 31: // 紫阳星魄
                $star = OwnStar::get($uid);
                if ($star === []) {
                    $star = OwnStar::init($uid, $now);
                }
                $site = $type - 28;
                OwnStar::update($uid, [$site], [(int)($star['star' . $site] ?? 0) + $value]);
                break;
            case 32: // 将军令
                MemberIntegral::change($uid, $value, 'general', 1, $class, $method, $now);
                break;
            default:
                // 8 装备 / 12 武将 / 17 ~ 25 装备精华 / 26 坐骑：旧版这些分支的代码已注释掉，不发奖励
                break;
        }
        return $return;
    }
}
