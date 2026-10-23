<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 连续登录 1~15 天阶梯豪礼与登录签到处理器 (DDD Activity Domain)
 */
final class LoginedHandler
{
    /**
     * 1~15 天连续登录固定奖励配置表
     * gettype: 1 银两, 2 突飞卡, 3 军令体力, 4 黄宝石, 5 紫宝石
     *
     * @var array<int, array<string, int>>
     */
    public const array REWARD_CONFIG = [
        1 => ['id' => 1, 'gettype1' => 1, 'getvalue1' => 10000, 'gettype2' => 3, 'getvalue2' => 10, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        2 => ['id' => 2, 'gettype1' => 1, 'getvalue1' => 20000, 'gettype2' => 2, 'getvalue2' => 10, 'gettype3' => 3, 'getvalue3' => 10, 'gettype4' => 0, 'getvalue4' => 0],
        3 => ['id' => 3, 'gettype1' => 1, 'getvalue1' => 30000, 'gettype2' => 2, 'getvalue2' => 15, 'gettype3' => 3, 'getvalue3' => 15, 'gettype4' => 0, 'getvalue4' => 0],
        4 => ['id' => 4, 'gettype1' => 1, 'getvalue1' => 40000, 'gettype2' => 2, 'getvalue2' => 20, 'gettype3' => 3, 'getvalue3' => 20, 'gettype4' => 0, 'getvalue4' => 0],
        5 => ['id' => 5, 'gettype1' => 1, 'getvalue1' => 50000, 'gettype2' => 2, 'getvalue2' => 25, 'gettype3' => 3, 'getvalue3' => 25, 'gettype4' => 0, 'getvalue4' => 0],
        6 => ['id' => 6, 'gettype1' => 1, 'getvalue1' => 60000, 'gettype2' => 2, 'getvalue2' => 30, 'gettype3' => 3, 'getvalue3' => 30, 'gettype4' => 0, 'getvalue4' => 0],
        7 => ['id' => 7, 'gettype1' => 1, 'getvalue1' => 80000, 'gettype2' => 2, 'getvalue2' => 40, 'gettype3' => 3, 'getvalue3' => 35, 'gettype4' => 0, 'getvalue4' => 0],
        8 => ['id' => 8, 'gettype1' => 1, 'getvalue1' => 100000, 'gettype2' => 2, 'getvalue2' => 50, 'gettype3' => 3, 'getvalue3' => 40, 'gettype4' => 0, 'getvalue4' => 0],
        9 => ['id' => 9, 'gettype1' => 1, 'getvalue1' => 120000, 'gettype2' => 2, 'getvalue2' => 60, 'gettype3' => 3, 'getvalue3' => 45, 'gettype4' => 0, 'getvalue4' => 0],
        10 => ['id' => 10, 'gettype1' => 1, 'getvalue1' => 150000, 'gettype2' => 2, 'getvalue2' => 70, 'gettype3' => 3, 'getvalue3' => 50, 'gettype4' => 0, 'getvalue4' => 0],
        11 => ['id' => 11, 'gettype1' => 1, 'getvalue1' => 180000, 'gettype2' => 2, 'getvalue2' => 80, 'gettype3' => 3, 'getvalue3' => 55, 'gettype4' => 0, 'getvalue4' => 0],
        12 => ['id' => 12, 'gettype1' => 1, 'getvalue1' => 200000, 'gettype2' => 2, 'getvalue2' => 90, 'gettype3' => 3, 'getvalue3' => 60, 'gettype4' => 0, 'getvalue4' => 0],
        13 => ['id' => 13, 'gettype1' => 1, 'getvalue1' => 250000, 'gettype2' => 2, 'getvalue2' => 100, 'gettype3' => 3, 'getvalue3' => 70, 'gettype4' => 0, 'getvalue4' => 0],
        14 => ['id' => 14, 'gettype1' => 1, 'getvalue1' => 300000, 'gettype2' => 2, 'getvalue2' => 120, 'gettype3' => 3, 'getvalue3' => 80, 'gettype4' => 0, 'getvalue4' => 0],
        15 => ['id' => 15, 'gettype1' => 1, 'getvalue1' => 500000, 'gettype2' => 2, 'getvalue2' => 150, 'gettype3' => 3, 'getvalue3' => 100, 'gettype4' => 0, 'getvalue4' => 0],
    ];

    /**
     * 连续登录主界面: c=logined&m=index
     */
    public static function index(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        $loginedData = self::getOrUpdateLoginedDays($uid);
        $curDays = max(1, min(15, (int)($loginedData['days'] ?? 1)));
        $claimedDays = $loginedData['claimed'] ?? [];

        $list = [];
        $hasUnclaimed = false;

        for ($i = 1; $i <= 15; $i++) {
            $isClaimed = in_array($i, $claimedDays, true);
            if ($isClaimed) {
                $statusType = 3; // 已领取
            } elseif ($i <= $curDays) {
                $statusType = 1; // 可领取
                $hasUnclaimed = true;
            } else {
                $statusType = 2; // 未达成
            }

            $item = self::REWARD_CONFIG[$i];
            $item['type'] = $statusType;
            $list[] = $item;
        }

        return [
            'status' => 1,
            'day' => $curDays,
            'list' => $list,
            'popo' => $hasUnclaimed ? 1 : 0,
            'all_silver' => (int)($member['silver'] ?? 0),
            'all_gold' => (int)($member['gold'] ?? 0),
            'all_act' => (int)($member['act'] ?? 0),
            'message' => 'ok',
        ];
    }

    /**
     * 领取连续登录奖励: c=logined&m=get_reward
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)($ctx->input('id', 0));

        if ($id < 1 || $id > 15) {
            return ['status' => -1, 'msg' => '参数错误'];
        }

        $loginedData = self::getOrUpdateLoginedDays($uid);
        $curDays = (int)($loginedData['days'] ?? 1);
        $claimedDays = $loginedData['claimed'] ?? [];

        if ($id > $curDays) {
            return ['status' => -2, 'msg' => '尚未达成该天数，暂不可领取'];
        }

        if (in_array($id, $claimedDays, true)) {
            return ['status' => -2, 'msg' => '该天数连续登录奖励已领取过'];
        }

        $rewardCfg = self::REWARD_CONFIG[$id];
        $member = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first();

        $addSilver = 0;
        $addAct = 0;
        $addLeap = 0;

        for ($i = 1; $i <= 4; $i++) {
            $type = (int)($rewardCfg['gettype' . $i] ?? 0);
            $val = (int)($rewardCfg['getvalue' . $i] ?? 0);
            if ($type === 1) {
                $addSilver += $val;
            } elseif ($type === 2) {
                $addLeap += $val;
            } elseif ($type === 3) {
                $addAct += $val;
            }
        }

        if ($addSilver > 0) {
            MemberHandler::addCurrency($uid, silver: $addSilver);
        }
        if ($addAct > 0) {
            MemberHandler::addCurrency($uid, act: $addAct);
        }
        if ($addLeap > 0) {
            $field = DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->first();
            if ($field !== null) {
                DB::table('member_field')->where('uid', $uid)->update([
                    'freeleap' => (int)($field['freeleap'] ?? 0) + $addLeap,
                ]);
            }
        }

        $claimedDays[] = $id;
        $loginedData['claimed'] = $claimedDays;
        Redis::setJson("user_logined_days:{$uid}", $loginedData, 86400 * 30);
        Redis::del('player:' . $uid);

        // 重新查询最新货币总额
        $freshMember = DB::table('member')->where('uid', $uid)->first();
        $hasUnclaimed = false;
        for ($i = 1; $i <= $curDays; $i++) {
            if (!in_array($i, $claimedDays, true)) {
                $hasUnclaimed = true;
                break;
            }
        }

        return [
            'status' => 1,
            'popo' => $hasUnclaimed ? 1 : 0,
            'all_gold' => (int)($freshMember['gold'] ?? 0),
            'all_act' => (int)($freshMember['act'] ?? 0),
            'all_silver' => (int)($freshMember['silver'] ?? 0),
            'message' => '领取成功',
        ];
    }

    /**
     * 获取或更新连续登录天数数据
     *
     * @return array{days: int, last_date: string, claimed: list<int>}
     */
    private static function getOrUpdateLoginedDays(int $uid): array
    {
        $key = "user_logined_days:{$uid}";
        $data = Redis::getJson($key);
        $todayStr = date('Ymd');
        $yesterdayStr = date('Ymd', strtotime('-1 day'));

        if (!is_array($data) || !isset($data['days'])) {
            $data = [
                'days' => 1,
                'last_date' => $todayStr,
                'claimed' => [],
            ];
            Redis::setJson($key, $data, 86400 * 30);
            return $data;
        }

        $lastDate = (string)($data['last_date'] ?? '');
        if ($lastDate === $todayStr) {
            // 今日已记录，直接返回
            return $data;
        }

        if ($lastDate === $yesterdayStr) {
            // 连续次日登录，天数累加递增 (上限15天)
            $data['days'] = min(15, (int)$data['days'] + 1);
            $data['last_date'] = $todayStr;
        } else {
            // 出现断签，重新从第一天算起
            $data['days'] = 1;
            $data['last_date'] = $todayStr;
            $data['claimed'] = [];
        }

        Redis::setJson($key, $data, 86400 * 30);
        return $data;
    }
}
