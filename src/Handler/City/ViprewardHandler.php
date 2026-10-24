<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * VIP 回馈豪礼大狂欢与专属阶段福利处理器 (DDD City Domain)
 */
final class ViprewardHandler
{
    /**
     * VIP 1~10 阶段回馈奖励配置表
     * type: 1 突飞卡, 2 黄宝石, 3 紫宝石, 4 声望
     */
    public const array REWARD_CONFIG = [
        1 => ['id' => 1, 'vip' => 1, 'type' => 1, 'value' => 20],
        2 => ['id' => 2, 'vip' => 2, 'type' => 1, 'value' => 50],
        3 => ['id' => 3, 'vip' => 3, 'type' => 2, 'value' => 10],
        4 => ['id' => 4, 'vip' => 4, 'type' => 2, 'value' => 20],
        5 => ['id' => 5, 'vip' => 5, 'type' => 3, 'value' => 15],
        6 => ['id' => 6, 'vip' => 6, 'type' => 3, 'value' => 30],
        7 => ['id' => 7, 'vip' => 7, 'type' => 4, 'value' => 500],
        8 => ['id' => 8, 'vip' => 8, 'type' => 4, 'value' => 1000],
        9 => ['id' => 9, 'vip' => 9, 'type' => 4, 'value' => 2000],
        10 => ['id' => 10, 'vip' => 10, 'type' => 4, 'value' => 5000],
    ];

    /**
     * VIP 回馈主界面: c=vipreward&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $vip = max(0, (int)($member['vip'] ?? 1));

        $claimed = Redis::getJson("vipreward_claimed:{$uid}") ?: [];

        $list = [];
        foreach (self::REWARD_CONFIG as $id => $cfg) {
            $isClaimed = in_array($id, $claimed, true);
            if ($isClaimed) {
                $status = 2; // 已领取
            } elseif ($vip >= (int)$cfg['vip']) {
                $status = 1; // 可领取
            } else {
                $status = 0; // 不可领取
            }

            $item = $cfg;
            $item['status'] = $status;
            $list[] = $item;
        }

        return [
            'status' => 1,
            'list' => $list,
            'msg' => '尊享 VIP 荣耀，感恩一路相伴！升级 VIP 即可免费尊领海量豪礼！',
            'cd' => 86400 * 30, // 活动剩余时间
            'message' => 'ok',
        ];
    }

    /**
     * 领取 VIP 回馈礼包: c=vipreward&m=get_reward
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)($ctx->input('id', $ctx->input('itemid', 0)));

        if (!isset(self::REWARD_CONFIG[$id])) {
            return ['status' => -1, 'message' => '奖励配置不存在'];
        }

        $cfg = self::REWARD_CONFIG[$id];
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        $vip = (int)($member['vip'] ?? 0);

        if ($vip < (int)$cfg['vip']) {
            return ['status' => -2, 'message' => "需达成 VIP{$cfg['vip']} 才能领取该奖励"];
        }

        $claimed = Redis::getJson("vipreward_claimed:{$uid}") ?: [];
        if (in_array($id, $claimed, true)) {
            return ['status' => -3, 'message' => '该回馈礼包已领取过'];
        }

        $type = (int)$cfg['type'];
        $val = (int)$cfg['value'];

        // 发放奖励 (1 突飞卡, 2 黄宝石, 3 紫宝石, 4 声望)
        if ($type === 1) {
            $field = DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->first();
            if ($field !== null) {
                DB::table('member_field')->where('uid', $uid)->update([
                    'freeleap' => (int)($field['freeleap'] ?? 0) + $val,
                ]);
            }
        } elseif ($type === 4) {
            MemberHandler::addCurrency($uid, reputation: $val);
        }

        $claimed[] = $id;
        Redis::setJson("vipreward_claimed:{$uid}", $claimed, 86400 * 365);
        Redis::del('player:' . $uid);

        return [
            'status' => 1,
            'id' => $id,
            'message' => 'VIP 回馈礼包领取成功！',
        ];
    }
}
