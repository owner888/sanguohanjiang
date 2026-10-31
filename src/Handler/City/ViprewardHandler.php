<?php

declare(strict_types=1);

namespace Hanjiang\Handler\City;

use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\MasterData\NewyearVipreward;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Member;
use Hanjiang\Model\Pay;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * VIP 回馈（春节回馈）活动（移植旧版 controllers/vipreward.php + models/vipreward_model.php，路径相对旧版 s1/application/）
 *
 * 活动时间取 ActivityTime viprepay（旧版数据 2016-12-31 00:00 已结束，接口一律 -1）。奖励取 NewyearVipreward，
 * 达到 VIP 等级的档位每期各领一次，领取记录存 newyear_vipreward_log（唯一键 uid + version + rewardid）。
 *
 * 客户端调用（viprepaypop.js）：index、get_reward，全部移植。
 * 并发：领取锁（旧版 newyear_vipreward_{uid}，60 秒）用 SET NX 原子加锁；领取记录唯一键兜底。
 */
final class ViprewardHandler
{
    /** 活动名（activity_status('viprepay')） */
    private const string ACTIVITY = 'viprepay';

    /** 领取奖励锁（vipreward_model：$_lock_key，60 秒） */
    private const string LOCK_KEY = 'newyear_vipreward_';
    private const int LOCK_TIME = 60;

    /** spring_lang.php：spring_vipreward_str */
    private const string LANG_VIPREWARD_STR = '游戏运营以来，广大玩家不遗余力的支持让我们难以为报，只有在此佳节到来之际，献上我们最真诚的最含敬意的感恩，真诚的感谢大家，愿为大家长久运营5-10年，更新不断服务永存！';

    /**
     * 回馈初始化: c=vipreward&m=index
     *
     * list 为全部档位，status：0 VIP 不足、1 可领取、2 已领取；cd 为活动剩余秒数。
     */
    public static function index(Context $ctx): array
    {
        /**
         * -2 没有奖励信息
         * -1 活动未开放
         *  1 成功
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        $now = BossClock::now();
        $activity = Pay::activity(self::ACTIVITY, $now);
        // 开放状态
        if ($activity === null || $member === null) {
            $return['status'] = -1;
            return $return;
        }
        $version = (int)$activity['version'];

        // 获取 vip 奖励列表
        $list = array_values(NewyearVipreward::ROWS);
        if ($list === []) {
            $return['status'] = -2;
            return $return;
        }

        // 获取回馈领取状态
        $rewardlog = self::rewardLog((int)$member['uid'], $version);
        foreach ($list as $key => $val) {
            $list[$key]['status'] = $val['vip'] <= (int)$member['vip'] ? 1 : 0;
            if (isset($rewardlog[$val['id']])) {
                $list[$key]['status'] = 2;
            }
        }

        $return['status'] = 1;
        $return['list'] = $list;
        $return['msg'] = self::LANG_VIPREWARD_STR;
        $return['cd'] = max(0, (int)$activity['endtime'] - $now);
        return $return;
    }

    /**
     * 领取回馈: c=vipreward&m=get_reward（id）
     *
     * 旧版 vipreward_model->get_reward($id) 按 vip 列查（WHERE vip = id），即 id 实际按 VIP 等级取档位；照旧版保留
     * （旧版数据 id 与 vip 一一相同）。奖励类型：1 突飞卡、2 黄宝石、3 紫宝石、4 声望。
     */
    public static function get_reward(Context $ctx): array
    {
        /**
         * -7 操作太频繁 / 写入领取记录失败
         * -6 已领取
         * -5 VIP 等级不足
         * -4 奖励不存在
         * -3 参数错误
         * -2 VIP 0 不能领取
         * -1 活动未开放
         *  1 成功
         */
        $return = ['status' => 0];
        $member = self::member(MemberHandler::resolveUid($ctx));
        $now = BossClock::now();
        $activity = Pay::activity(self::ACTIVITY, $now);
        // 开放状态
        if ($activity === null || $member === null) {
            $return['status'] = -1;
            return $return;
        }
        $uid = (int)$member['uid'];
        $version = (int)$activity['version'];

        // 判断 vip 等级是否足够
        if ((int)$member['vip'] === 0) {
            $return['status'] = -2;
            return $return;
        }

        $id = (int)$ctx->input('id', 0);
        if (!$id) {
            $return['status'] = -3;
            return $return;
        }

        $redis = Redis::instance();
        if ($redis === null || !$redis->set(self::LOCK_KEY . $uid, '1', ['nx', 'ex' => self::LOCK_TIME])) {
            $return['status'] = -7;
            return $return;
        }

        try {
            // 获取回馈奖励信息（旧版按 vip = id 查）
            $reward = null;
            foreach (NewyearVipreward::ROWS as $row) {
                if ($row['vip'] === $id) {
                    $reward = $row;
                    break;
                }
            }
            if ($reward === null) {
                $return['status'] = -4;
                return $return;
            }

            // 判断是否可领取
            if ($reward['vip'] > (int)$member['vip']) {
                $return['status'] = -5;
                return $return;
            }

            // 获取回馈领取状态
            if (isset(self::rewardLog($uid, $version)[$reward['id']])) {
                $return['status'] = -6;
                return $return;
            }

            // 添加领奖状态
            $inserted = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_newyear_vipreward_log` (`uid`, `rewardid`, `version`, `dateline`) VALUES (:uid, :rid, :version, :now)',
                ['uid' => $uid, 'rid' => $reward['id'], 'version' => $version, 'now' => $now]
            );
            if ($inserted !== 1) {
                $return['status'] = -7;
                return $return;
            }

            // 添加奖励
            if ($reward['type'] === 1) {
                // 突飞卡
                DB::table('member_field')->where('uid', $uid)->whereNull('deleted_at')->increment('freeleap', $reward['value']);
            } elseif ($reward['type'] === 2) {
                // 金宝石
                TavernHandler::addJade($uid, 2, $reward['value'], 'vipreward', 'get_reward');
            } elseif ($reward['type'] === 3) {
                // 紫宝石
                TavernHandler::addJade($uid, 3, $reward['value'], 'vipreward', 'get_reward');
            } elseif ($reward['type'] === 4) {
                // 声望
                Member::addCurrency($uid, reputation: $reward['value']);
            }
            Redis::del('player:' . $uid);

            // 成功返回
            $after = self::member($uid) ?? $member;
            $return['status'] = 1;
            $return['reward'] = $reward;
            $return['all_act'] = (int)$after['act'];
            $return['all_gold'] = (int)$after['gold'];
            $return['all_silver'] = (int)$after['silver'];
            return $return;
        } finally {
            $redis->del(self::LOCK_KEY . $uid);
        }
    }

    /**
     * 本期领取记录（get_reward_log）：rewardid => 记录
     *
     * @return array<int, array<string, mixed>>
     */
    private static function rewardLog(int $uid, int $version): array
    {
        $return = [];
        $rows = DB::table('newyear_vipreward_log')->select('rewardid', 'dateline')
            ->where('version', $version)->where('uid', $uid)->whereNull('deleted_at')->get();
        foreach ($rows as $val) {
            $return[(int)$val['rewardid']] = $val;
        }
        return $return;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        return DB::table('member')->select('uid', 'vip', 'act', 'gold', 'silver')->where('uid', $uid)->whereNull('deleted_at')->first();
    }
}
