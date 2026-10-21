<?php

declare(strict_types=1);

namespace Hanjiang\Handler;

use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use OmniPHP\WebSocket\Broadcaster;

final class FirstRechargeHandler
{
    /**
     * 首充活动主界面: c=first_recharge&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $status = (int)(Redis::get('first_recharge_status:' . $uid) ?: 1); // 默认测试/新服环境激活可领

        return [
            'status' => 1,
            'recharge_status' => $status,
            'reward_preview' => [
                'general' => '神将·赵子龙',
                'weapon' => '极品·龙胆亮银枪',
                'silver' => 88888,
                'gold' => 200,
                'act' => 100,
            ],
            'message' => 'ok',
        ];
    }

    /**
     * 领取首充至尊神礼: c=first_recharge&m=get_reward
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $status = (int)(Redis::get('first_recharge_status:' . $uid) ?: 1);

        if ($status === 2) {
            return ['status' => 0, 'message' => '首充豪礼已领取，不可重复申领'];
        }

        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($member === null) {
            return ['status' => -1, 'message' => '玩家数据不存在'];
        }

        // 1. 发放 88888 银两、200 元宝、100 军令
        $newSilver = (int)$member['silver'] + 88888;
        $newGold = (int)$member['gold'] + 200;
        $newAct = min(300, (int)$member['act'] + 100);

        DB::table('member')->where('uid', $uid)->update([
            'silver' => $newSilver,
            'gold' => $newGold,
            'act' => $newAct,
            'vip' => max(2, (int)($member['vip'] ?? 1)), // 免费直升 VIP2
        ]);

        // 2. 赠送神品武器【龙胆亮银枪】
        DB::table('equipment')->insert([
            'uid' => $uid,
            'equipid' => 105,
            'name' => '龙胆亮银枪',
            'type' => 1,
            'quality' => 5,
            'level' => 1,
            'generalid' => 0,
            'number' => 1,
            'saleprice' => 500,
        ]);

        // 3. 发放五星绝世神将【赵云】(如果尚未招募)
        $hasZhaoyun = DB::table('general')->where('uid', $uid)->where('generalid', 13)->first();
        if ($hasZhaoyun === null) {
            DB::table('general')->insert([
                'uid' => $uid,
                'generalid' => 13,
                'name' => '赵云',
                'level' => 5,
                'exp' => 0,
                'pos' => 0,
                'matrix_id' => 1,
                'status' => 1,
            ]);
        }

        // 标记已领取
        Redis::set('first_recharge_status:' . $uid, '2', 86400 * 365);
        Redis::del('player:' . $uid);

        // 全服通报首充大捷
        $nickname = $member['nickname'] ?? ('主公' . $uid);
        Broadcaster::broadcast('marquee', [
            'content' => "龙吟九霄！主公【{$nickname}】开启首充狂欢至尊宝箱，喜迎神将【赵云】出山，特赐龙胆亮银枪！",
            'type' => 'system_broadcast',
        ]);

        return [
            'status' => 1,
            'silver' => 88888,
            'gold' => 200,
            'vip' => 2,
            'general' => '赵云',
            'weapon' => '龙胆亮银枪',
            'message' => '首充至尊神礼领取成功！神将赵云与龙胆亮银枪已入驻麾下！',
        ];
    }
}
