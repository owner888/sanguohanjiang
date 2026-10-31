<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;

use Hanjiang\Model\Member;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 评分奖励（移植旧版 controllers/clickreward.php + models/clickreward_model.php）
 *
 * 客户端（miscpop.js 评分弹窗）调用 index：主公 31 级以上，去应用商店评分领一次 50 元宝，领取时间（当天 0 点）记
 * member_field.clickreward（> 0 即已领）。同旧版用 Redis 锁 click_reward_{uid}（600 秒）防重复点击，锁已存在时回 -2。
 * 与旧版的差别：领取状态以条件更新（clickreward = 0）抢占，多 Worker 并发也只发一次。
 */
final class ClickrewardHandler
{
    /** 锁 key 前缀 */
    private const string LOCK_KEY = 'click_reward_';
    /** 奖励元宝个数 */
    private const int REWARD = 50;
    /** 开启等级 */
    private const int OPEN_LEVEL = 31;

    /**
     * 添加奖励: c=clickreward&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $level = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level') ?? 0);
        if ($level < self::OPEN_LEVEL) {
            // 功能未开启
            $return['status'] = -3;
            return $return;
        }
        // 判断功能是否锁定
        $lock = self::LOCK_KEY . $uid;
        if (!Redis::instance()->set($lock, '1', ['nx', 'ex' => 600])) {
            $return['status'] = -2;
            return $return;
        }
        try {
            // 修改领取时间（未领取过才改：clickreward > 0 为已领取）
            $now = time();
            $affected = DB::table('member_field')->where('uid', $uid)->where('clickreward', 0)->whereNull('deleted_at')
                ->update(['clickreward' => strtotime(date('Y-m-d', $now))]);
            if ($affected === 0) {
                // 已领取 / 修改领取状态失败
                $return['status'] = -1;
                return $return;
            }
            // 添加元宝
            Member::addCurrency($uid, gold: self::REWARD);
            $return['status'] = 1;
            $return['add_gold'] = self::REWARD;
            $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
            return $return;
        } finally {
            // 解锁
            Redis::del($lock);
        }
    }
}
