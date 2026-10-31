<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\RoleGift;
use Hanjiang\Model\Member;
use Hanjiang\Model\TraderLog;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;
use PDOException;

/**
 * 角色注册天数礼包（移植旧版 controllers/rolegift.php + models/rolegift_model.php）
 *
 * 客户端（lotterypop.js 的注册礼包页）调用：index / get_reward。礼包取 MasterData RoleGift（注册满 N 天领黄宝石 type 1 /
 * 紫宝石 type 2），领取记录 role_gift_log（uid + 礼包 id 主键，同旧版）。注册天数 = 今天与注册当天相差的天数 + 1。
 * 与旧版的差别：get_reward 在 Member::locked（玩家行锁）里执行；并发重复领取被主键挡下时回 -4（旧版同样是插入失败）。
 */
final class RolegiftHandler
{
    /**
     * 礼包列表: c=rolegift&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         * -1 获取礼包列表失败
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $jointime = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('jointime') ?? 0);
        $result = self::getList($uid, $jointime, time());
        if ($result['status'] === -1) {
            // 获取礼包列表失败
            $return['status'] = -1;
            return $return;
        }
        $return['history'] = $result['history'];
        $return['list'] = $result['list'];
        $return['joinday'] = $result['joinday'];
        $return['regday'] = date('Y-m-d', $jointime);
        $return['status'] = 1;
        return $return;
    }

    /**
     * 领取礼包: c=rolegift&m=get_reward&id=
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        return Member::locked($uid, static function () use ($uid, $id): array {
            /**
             * -6 获取信息失败
             * -5 发放奖励失败
             * -4 添加领取记录失败
             * -3 未知奖励类型
             * -2 条件未达成，无法领取
             * -1 获取礼包信息失败
             *  0 失败
             *  1 成功
             *  2 礼包已领取
             */
            $return = ['status' => 0];
            $now = time();
            if (DB::table('role_gift_log')->where('uid', $uid)->where('id', $id)->whereNull('deleted_at')->value('uid') !== null) {
                // 礼包已领取
                $return['status'] = 2;
                return $return;
            }
            $info = RoleGift::ROWS[$id] ?? null;
            if ($info === null || $info['num'] === 0 || $info['type'] > 2) {
                // 获取礼包信息失败
                $return['status'] = -1;
                return $return;
            }
            $jointime = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('jointime') ?? 0);
            $joinday = self::joinDays($jointime, $now);
            if ($info['day'] > $joinday) {
                // 条件未达成，无法领取
                $return['status'] = -2;
                return $return;
            }
            if ($info['type'] === 1) {
                // 黄宝石
                $addType = 2;
            } elseif ($info['type'] === 2) {
                // 紫宝石
                $addType = 3;
            } else {
                // 未知奖励类型
                $return['status'] = -3;
                return $return;
            }
            // 修改领取状态
            try {
                DB::table('role_gift_log')->insert(['uid' => $uid, 'id' => $info['id'], 'type' => $info['type'], 'num' => $info['num']]);
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                // 添加领取记录失败
                $return['status'] = -4;
                return $return;
            }
            // 发放奖励
            $add = TraderLog::addJade($uid, $addType, $info['num'], class: 'rolegift', method: 'get_reward', now: $now);
            if ($add['status'] !== 1) {
                // 发放奖励失败
                $return['status'] = -5;
                return $return;
            }
            $result = self::getList($uid, $jointime, $now);
            if ($result['status'] !== 1) {
                $return['status'] = -6;
                return $return;
            }
            $return['status'] = 1;
            $return['list'] = $result['list'];
            $return['history'] = $result['history'];
            $return['joinday'] = $result['joinday'];
            $return['add'] = ['type' => $info['type'], 'num' => $info['num']];
            return $return;
        });
    }

    /**
     * 礼包列表与已领取宝石合计（_get_list：get 0 不可领、1 可领取、2 已领取）
     *
     * @return array<string, mixed>
     */
    private static function getList(int $uid, int $jointime, int $now): array
    {
        $log = [];
        foreach (DB::table('role_gift_log')->select('id', 'type', 'num')->where('uid', $uid)->whereNull('deleted_at')->get() as $row) {
            $log[(int)$row['id']] = $row;
        }
        $history = ['jade1' => 0, 'jade2' => 0, 'jade3' => 0];
        foreach ($log as $value) {
            if ((int)$value['type'] === 1) {
                // 黄宝石
                $history['jade2'] += (int)$value['num'];
            } elseif ((int)$value['type'] === 2) {
                // 紫宝石
                $history['jade3'] += (int)$value['num'];
            }
        }
        $joinday = self::joinDays($jointime, $now);
        $list = [];
        foreach (RoleGift::ROWS as $value) {
            if ($joinday >= $value['day']) {
                $value['get'] = isset($log[$value['id']]) ? 2 : 1;
            } else {
                $value['get'] = 0;
            }
            $list[] = $value;
        }
        if ($list === []) {
            return ['status' => -1];
        }
        return ['status' => 1, 'history' => $history, 'list' => $list, 'joinday' => $joinday];
    }

    /**
     * 注册天数（get_join_days：今天与注册当天相差的天数 + 1）
     */
    private static function joinDays(int $jointime, int $now): int
    {
        return (int)ceil((strtotime(date('Ymd', $now)) - strtotime(date('Ymd', $jointime))) / 86400) + 1;
    }
}
