<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\ExerciseAddition;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Exercise;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberActivity;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 运营活动：整军演武（移植旧版 controllers/exercise.php；规则见 Model\Exercise，征收侧见 City\CityHandler）
 *
 * 活动时间取 ActivityTime 的 exercise（旧版数据 2016-12-31 已结束，接口回 -2）。客户端 exercisepop.js 调用 index、open_gift。
 * 旧版的 clear_rank / clear_addition 是运维口令接口（clear=123），不移植；清排行见 Cron\ExerciseCron。
 * 当前时间取 BossClock（生产恒为 time()）。
 *
 * 与旧版的差别：开礼包以礼包数条件更新扣减（旧版先读后写，并发可多开）。
 */
final class ExerciseHandler
{
    /**
     * 初始化: c=exercise&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         * -4 获取活动信息失败
         * -3 获取扩展信息失败
         * -2 活动已结束
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 1, 'info' => []];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        if (!Exercise::isOpen($now) || $uid <= 0) {
            // 获取活动信息失败
            $return['status'] = -2;
            return $return;
        }
        // [用户模型]取扩展信息
        $field = DB::table('member_field')->select('levyall', 'imposetimes')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($field === null) {
            // 获取扩展信息失败
            $return['status'] = -3;
            return $return;
        }
        // 取当前征收次数
        $levytimes = (int)$field['levyall'] + (int)$field['imposetimes'];
        // 获取排行榜
        $list = Exercise::rankList();
        $listArr = [];
        if ($list !== []) {
            $members = DB::table('member')->select('uid', 'nickname')->whereIn('uid', $list)->whereNull('deleted_at')->get();
            $addition = array_values(ExerciseAddition::ROWS);
            $special = Exercise::specialAddition($now);
            $sort = 1;
            foreach ($list as $key => $rankUid) {
                $listArr[$key]['uid'] = $rankUid;
                foreach ($members as $v) {
                    if ($rankUid === (int)$v['uid']) {
                        $times = Exercise::levytimes((int)$v['uid']);
                        $listArr[$key]['nickname'] = $v['nickname'];
                        $listArr[$key]['times'] = $times === false ? false : $times;
                        $listArr[$key]['addition'] = $special ? 300 : ($addition[$key]['addition'] ?? null);
                        $listArr[$key]['sort'] = $sort;
                    }
                }
                ++$sort;
            }
        }

        // [用户模型]获取活动信息
        MemberActivity::ensure($uid);
        $gift = MemberActivity::many([$uid], ['exercise_gift'])[$uid]['exercise_gift'] ?? null;
        if ($gift === null) {
            // 获取活动信息失败
            $return['status'] = -4;
            return $return;
        }

        $return['status'] = 1;
        $return['info']['cd'] = Exercise::activity()['endtime'] - $now;
        // 征收次数
        $return['info']['times'] = $levytimes;
        $return['info']['alltimes'] = $levytimes;
        $return['info']['gift'] = $gift;
        $return['info']['list'] = $listArr;
        return $return;
    }

    /**
     * 开启礼包: c=exercise&m=open_gift
     */
    public static function open_gift(Context $ctx): array
    {
        /**
         * -6 扣除礼包失败
         * -5 获取奖励信息失败
         * -4 没有礼包
         * -3 获取活动信息失败
         * -2 活动已结束
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0, 'gift' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        if (!Exercise::isOpen(BossClock::now()) || $uid <= 0) {
            // 获取活动信息失败
            $return['status'] = -2;
            return $return;
        }
        // [用户模型]获取活动信息
        MemberActivity::ensure($uid);
        $own = MemberActivity::many([$uid], ['exercise_gift'])[$uid]['exercise_gift'] ?? null;
        $member = DB::table('member')->select('level')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($own === null || $member === null) {
            // 获取活动信息失败
            $return['status'] = -3;
            return $return;
        }
        if ($own === 0) {
            // 没有礼包
            $return['status'] = -4;
            return $return;
        }
        $level = (int)$member['level'];
        // 取得奖励信息
        $gift = Exercise::reward($level);
        // 扣除礼包（update_exercise_gift：礼包数 - 1，记当前主公等级；以礼包数条件更新）
        $giftNum = $own - 1;
        $sub = DB::table('member_activity')->where('uid', $uid)->where('exercise_gift', $own)->whereNull('deleted_at')
            ->update(['exercise_gift' => $giftNum, 'exercise_level' => $level]);
        if ($sub !== 1) {
            // 扣除礼包失败
            $return['status'] = -6;
            return $return;
        }
        // 获取奖励:银币
        if ($gift['type'] === 1) {
            Member::addCurrency($uid, silver: $gift['num']);
            $return['all_silver'] = (int)DB::table('member')->where('uid', $uid)->value('silver');
        }
        // 军令
        if ($gift['type'] === 2) {
            Member::addCurrency($uid, act: $gift['num']);
            $return['all_act'] = (int)DB::table('member')->where('uid', $uid)->value('act');
        }

        $return['status'] = 1;
        $return['info'] = $gift;
        $return['info']['gift'] = $giftNum;
        return $return;
    }
}
