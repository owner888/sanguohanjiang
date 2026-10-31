<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 砸金蛋彩蛋、连环暴击与稀世奇珍大奖处理器 (DDD Activity Domain)
 */
final class HiteggHandler
{
    /**
     * 3 大金蛋基础配置
     */
    public const array EGGS = [
        1 => ['id' => 1, 'name' => '吉祥如意金蛋', 'price' => 0, 'cost_silver' => 5000, 'cost_gold' => 0, 'silver' => 12000, 'gold' => 10, 'description' => '有机会获得初级突飞卡、蓝宝石'],
        2 => ['id' => 2, 'name' => '富贵荣华银蛋', 'price' => 20, 'cost_silver' => 15000, 'cost_gold' => 0, 'silver' => 35000, 'gold' => 20, 'description' => '有机会获得高级突飞卡、金宝石'],
        3 => ['id' => 3, 'name' => '至尊天下无双彩蛋', 'price' => 50, 'cost_silver' => 0, 'cost_gold' => 50, 'silver' => 88888, 'gold' => 100, 'description' => '有机会获得紫宝石、海量元宝'],
    ];

    /**
     * 砸金蛋主界面: c=hitegg&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $times = (int)(Redis::get('hitegg_times:' . $uid) ?? 5);

        $list = [];
        foreach (self::EGGS as $id => $egg) {
            $list[] = [
                'id' => $id,
                'name' => $egg['name'],
                'price' => $egg['price'],
                'description' => $egg['description'],
                'cd' => 0,
                'open' => 1,
            ];
        }

        return [
            'status' => 1,
            'list' => $list,
            'left_hammers' => $times,
            'eggs' => array_values(self::EGGS),
            'message' => 'ok',
        ];
    }

    /**
     * 挥锤砸金蛋: c=hitegg&m=hit_egg
     */
    public static function hit_egg(Context $ctx): array
    {
        // 依赖未移植：旧版砸到 7 星以上武将 send_message_to_horn(general_get_by_hitegg) + get_message_str(22) 系统消息（随旧版 hitegg 移植）
        $uid = MemberHandler::resolveUid($ctx);
        $eggId = (int)($ctx->input('id', $ctx->input('egg_id', 1)));

        if (!isset(self::EGGS[$eggId])) {
            return ['status' => 0, 'message' => '金蛋不存在'];
        }

        $cfg = self::EGGS[$eggId];
        $costSilver = (int)$cfg['cost_silver'];
        $costGold = (int)$cfg['cost_gold'];

        if (!MemberHandler::deductCurrency($uid, silver: $costSilver, gold: $costGold)) {
            return [
                'status' => -4,
                'message' => ($costGold > 0) ? "元宝不足，砸碎彩蛋需要 {$costGold} 元宝" : "银两不足，砸碎金蛋需要 {$costSilver} 银两",
            ];
        }

        $rewardSilver = (int)$cfg['silver'];
        $rewardGold = (int)$cfg['gold'];

        // 发放奖励
        MemberHandler::addCurrency($uid, silver: $rewardSilver, gold: $rewardGold);

        // 奖励类型映射: 1 蓝宝石, 2 金宝石, 3 紫宝石, 4 突飞卡
        $rewardType = match ($eggId) {
            1 => 4, // 赠送突飞卡
            2 => 2, // 赠送金宝石
            3 => 3, // 赠送紫宝石
            default => 4,
        };
        $rewardVal = match ($eggId) {
            1 => 10,
            2 => 25,
            3 => 50,
            default => 10,
        };

        // 发放突飞卡到 member_field
        if ($rewardType === 4) {
            $field = DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->first();
            if ($field !== null) {
                DB::table('member_field')->where('uid', $uid)->update([
                    'freeleap' => (int)($field['freeleap'] ?? 0) + $rewardVal,
                ]);
            }
        }

        $freshMember = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        Redis::del('player:' . $uid);

        $rewardObj = [
            'type' => $rewardType,
            'value' => $rewardVal,
            'quality' => min(5, 2 + $eggId),
        ];

        return [
            'status' => 1,
            'reward' => $rewardObj,
            'cd' => 0,
            'all_gold' => (int)($freshMember['gold'] ?? 0),
            // 向下兼容保留原字段
            'egg_name' => $cfg['name'],
            'silver' => $rewardSilver,
            'gold' => $rewardGold,
            'message' => "金蛋碎裂，祥瑞降临！斩获银两 {$rewardSilver}" . ($rewardGold > 0 ? "、元宝 {$rewardGold}" : '') . "！",
        ];
    }
}
