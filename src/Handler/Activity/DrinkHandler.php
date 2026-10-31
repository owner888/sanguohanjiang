<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\Drink;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberActivity;
use Hanjiang\Model\StatPay;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 每日对酒（移植旧版 controllers/drink.php + models/drink_model.php）
 *
 * 酒的配置取 MasterData Drink（type 1 银币，价格 = 主公等级 × 100；type 2 元宝）。每天对酒一次（member_activity.drinktime），
 * 加军令（act_log）；梨花白（id 1）以外的对酒记一条 drink_log，界面显示全服最近 7 条。
 * 客户端 drinkpop.js 调用 index、go_drink（type 酒 id）。
 *
 * 依赖未移植：春节送福活动计数（actnewyear_model->add_task_num(version, 21)，type 2 时；节日活动，旧版数据中已结束）。
 * 与旧版的差别：对酒在玩家行锁里执行，并以 drinktime 条件更新抢占当天的次数（旧版先扣钱再改时间，并发可对多次）。
 */
final class DrinkHandler
{
    /**
     * 初始化接口: c=drink&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         * -2 获取活动信息失败
         * -1 获取列表失败
         *  0 失败
         *  1 成功
         *  2 当日对酒次数不足
         */
        $return = ['status' => 0, 'list' => []];
        $member = self::member(MemberHandler::resolveUid($ctx));
        if ($member === null) {
            return $return;
        }
        $uid = (int)$member['uid'];
        // [drink_model]获取每日对酒活动列表
        $list = self::drinkList((int)$member['level']);
        // [drink_model]获取每日对酒log
        $log = self::log();
        // [member_model]获取活动信息
        MemberActivity::ensure($uid);
        $drinktime = (int)(MemberActivity::many([$uid], ['drinktime'])[$uid]['drinktime'] ?? 0);
        $last = (int)date('Ymd', $drinktime);
        $now = (int)date('Ymd', time());
        // 当日对酒次数不足 2
        $return['status'] = $now <= $last ? 2 : 1;
        $return['list'] = $list;
        $return['log'] = $log;
        return $return;
    }

    /**
     * 对酒: c=drink&m=go_drink（type 酒 id）
     */
    public static function go_drink(Context $ctx): array
    {
        /**
         * -6 类型错误
         * -5 扣除金币失败
         * -4 扣除银币失败
         * -3 获取对酒信息失败
         * -2 当日对酒次数不足
         * -1 获取活动信息失败
         *  0 失败
         *  1 成功
         *  2 银币不足
         *  3 金币不足
         */
        $uid = MemberHandler::resolveUid($ctx);
        $type = (int)$ctx->input('type', 0);
        if (self::member($uid) === null) {
            return ['status' => 0, 'reward' => []];
        }
        MemberActivity::ensure($uid);
        return Member::locked($uid, static function () use ($uid, $type): array {
            $return = ['status' => 0, 'reward' => []];
            $member = self::member($uid);
            $now = time();
            $drinktime = (int)(MemberActivity::many([$uid], ['drinktime'])[$uid]['drinktime'] ?? 0);
            if ((int)date('Ymd', $now) <= (int)date('Ymd', $drinktime)) {
                // 当日对酒次数不足
                $return['status'] = -2;
                return $return;
            }
            // [drink_model]根据id获取每日对酒信息(id)
            $drink = Drink::ROWS[$type] ?? null;
            if ($drink === null) {
                // 获取对酒信息失败
                $return['status'] = -3;
                return $return;
            }
            if ($drink['type'] === 1) {
                // 扣除银币（get_info_by_id：价格 × 主公等级）
                if (!Member::deductCurrency($uid, silver: $drink['price'] * (int)$member['level'])) {
                    // 银币不足 2
                    $return['status'] = 2;
                    return $return;
                }
            } elseif ($drink['type'] === 2) {
                // 扣除元宝
                if (!Member::deductCurrency($uid, gold: $drink['price'])) {
                    // 金币不足
                    $return['status'] = 3;
                    return $return;
                }
                // 消费统计记录
                StatPay::record($uid, 'drink', $drink['price']);
            } else {
                $return['status'] = -6;
                return $return;
            }

            // 更新对酒时间
            MemberActivity::update($uid, ['drinktime' => $now]);
            // 加军令
            Member::addCurrency($uid, act: $drink['act']);

            // 添加对酒log
            $data = [
                'nickname' => (string)$member['nickname'],
                'type' => $drink['id'],
                'act' => $drink['act'],
                'dateline' => $now,
            ];
            if ($drink['id'] > 1) {
                DB::table('drink_log')->insert($data);
            }
            // 依赖未移植：春节送福活动计数 actnewyear_model->add_task_num(version, 21)（type 2 时）

            $after = self::member($uid);
            $return['status'] = 1;
            $return['reward']['act'] = (int)$after['act'];
            $return['reward']['gold'] = (int)$after['gold'];
            $return['reward']['silver'] = (int)$after['silver'];
            $return['log'] = $data;
            return $return;
        });
    }

    /**
     * 对酒列表（get_list：type 1 的价格为主公等级 × 100）
     *
     * @return list<array<string, mixed>>
     */
    private static function drinkList(int $level): array
    {
        $return = [];
        foreach (Drink::ROWS as $value) {
            if ($value['type'] === 1) {
                $value['price'] = $level * 100;
            }
            $return[] = $value;
        }
        return $return;
    }

    /**
     * 全服最近的对酒记录（get_log：最近 7 条，旧版不带 uid）
     *
     * @return list<array<string, mixed>>
     */
    private static function log(int $limit = 7): array
    {
        $rows = DB::table('drink_log')->select('id', 'nickname', 'dateline', 'type', 'act')
            ->whereNull('deleted_at')->orderBy('id', 'DESC')->limit($limit)->get();
        return array_map(static fn (array $row): array => [
            'id' => (int)$row['id'],
            'nickname' => (string)$row['nickname'],
            'dateline' => (int)$row['dateline'],
            'type' => (int)$row['type'],
            'act' => (int)$row['act'],
        ], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        return $uid > 0 ? DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->first() : null;
    }
}
