<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\JadeGamble;
use Hanjiang\MasterData\JadegambleFbReward;
use Hanjiang\MasterData\JadePrice;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberActivity;
use Hanjiang\Model\Pay;
use Hanjiang\Model\SendReward;
use Hanjiang\Model\TraderLog;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 运营活动：神矿 / 宝石赌博（移植旧版 controllers/jadegamble.php + models/jadegamble_model.php、jadegamble_fb_reward_model.php）
 *
 * 客户端（godminepop.js）调用：index / mining(id) / gambling / harvest。活动时间取 ActivityTime 的 jadegamble（旧版数据
 * 2016-12-31 已结束，接口回 -1）；当前时间取 BossClock（生产恒为 time()）。
 * 花元宝挖矿（MasterData JadePrice：id 1 ~ 3 对应蓝 / 黄 / 紫宝石，price 价格，X1 ~ X10 为挖出 1 ~ 10 颗的权重），挖到的宝石
 * 暂存 member_activity.jadetype / jadenum / jadeturn；可以收获（加到 trader_log.get1 ~ get3），也可以赌翻倍（MasterData
 * JadeGamble：第 N 手成功率 probabilityN，成功才扣 priceN 元宝、数量翻倍，第 10 手成功或任何一手失败都直接发宝石——失败按
 * 失败前的倍数发）。每次赌博按宝石类型与倍数从 MasterData JadegambleFbReward 抽一个额外奖励（Model\SendReward；旧版
 * send_fb_reward 把 is_success 固定写成 1，失败也按成功档抽，照搬；该倍数没有配置时倍数翻倍再找，到 1024 为止）。
 * 记录：jade_gamble_log（每次挖矿 / 成功翻倍）、jadegamble_action_log（挖矿一行，收获时补 get{type}）。
 *
 * 与旧版的差别：各接口在 Member::locked（玩家行锁）里执行。
 */
final class JadegambleHandler
{
    /** 运营活动名（ActivityTime.name） */
    private const string ACTIVITY = 'jadegamble';
    /** 最多赌博次数 */
    private const int MAX_TURN = 10;

    /**
     * 挖矿列表: c=jadegamble&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         * -3 获取宝石信息失败
         * -2 获取挖掘规则失败
         * -1 活动未开启
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0];
        $now = BossClock::now();
        $activity = Pay::activity(self::ACTIVITY, $now);
        if ($activity === null) {
            // 活动未开启
            $return['status'] = -1;
            return $return;
        }
        $uid = MemberHandler::resolveUid($ctx);
        $listArr = [];
        foreach (array_values(JadePrice::ROWS) as $key => $value) {
            $listArr[$key] = ['id' => $value['id'], 'price' => $value['price']];
        }
        if ($listArr === []) {
            // 获取挖掘规则失败
            $return['status'] = -2;
            return $return;
        }
        $gamble = self::info($uid);
        $return['info'] = ['status' => 0];
        if ($gamble !== null) {
            $return['info'] = $gamble;
            $return['info']['status'] = 1;
        }
        $jade = TraderLog::get($uid);
        $return['status'] = 1;
        $return['list'] = $listArr;
        $return['jade'] = [1 => $jade['get1'] ?? 0, 2 => $jade['get2'] ?? 0, 3 => $jade['get3'] ?? 0];
        $return['cd'] = (int)$activity['endtime'] - $now;
        return $return;
    }

    /**
     * 挖矿: c=jadegamble&m=mining&id=1|2|3
     */
    public static function mining(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        return Member::locked($uid, static function () use ($uid, $id): array {
            /**
             * -8 获取规则失败
             * -7 获取赌博规则失败
             * -6 添加记录失败
             * -5 更新宝石信息失败
             * -4 扣元宝失败
             * -3 元宝不足
             * -2 获取活动信息失败
             * -1 活动未开启
             *  0 失败
             *  1 成功
             *  2 有未领取的宝石
             */
            $return = ['status' => 0];
            $now = BossClock::now();
            if (Pay::activity(self::ACTIVITY, $now) === null) {
                // 活动未开启
                $return['status'] = -1;
                return $return;
            }
            $activity = self::activityInfo($uid);
            $member = DB::table('member')->select('level', 'gold')->where('uid', $uid)->whereNull('deleted_at')->first();
            if ($activity === null || $member === null) {
                // 获取活动信息失败
                $return['status'] = -2;
                return $return;
            }
            if ($activity['jadetype'] > 0) {
                // 有宝石未领取
                $return['status'] = 2;
                return $return;
            }
            $rule = JadePrice::ROWS[$id] ?? null;
            if ($rule === null) {
                // 获取规则失败
                $return['status'] = -8;
                return $return;
            }
            $gold = $rule['price'];
            // 扣除元宝
            if (!Member::deductCurrency($uid, gold: $gold)) {
                // 元宝不足
                $return['status'] = -3;
                return $return;
            }
            // 获取挖掘结果
            $data = [];
            for ($i = 1; $i <= 10; $i++) {
                $data[$i] = $rule['X' . $i];
            }
            $num = self::miningRoll($data);
            $result = ['jadetype' => $id, 'jadenum' => $num, 'jadeturn' => 0];
            // 更新活动信息
            if (DB::table('member_activity')->where('uid', $uid)->where('jadetype', 0)->whereNull('deleted_at')->update($result) !== 1) {
                // 更新宝石信息失败
                $return['status'] = -5;
                return $return;
            }
            DB::table('jade_gamble_log')->insert($result + ['dateline' => $now, 'uid' => $uid]);
            $gamble = JadeGamble::ROWS[$id] ?? null;
            if ($gamble === null) {
                // 获取赌博规则失败
                $return['status'] = -7;
                return $return;
            }
            $return['info'] = [
                'type' => $result['jadetype'],
                'multiple' => 1,
                'num' => $result['jadenum'],
                'next' => $result['jadenum'] * 2,
                'price' => $gamble['price1'],
            ];
            // 统计活动人次和消耗
            DB::table('jadegamble_action_log')->insert([
                'uid' => $uid,
                'gold' => $gold,
                'level' => (int)$member['level'],
                'dateline' => $now,
            ]);
            $return['status'] = 1;
            $return['gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
            return $return;
        });
    }

    /**
     * 赌博: c=jadegamble&m=gambling
     */
    public static function gambling(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return Member::locked($uid, static function () use ($uid): array {
            /**
             * -7 加宝石失败
             * -6 更新用户信息失败
             * -5 扣元宝失败
             * -4 元宝不足
             * -3 获取赌博信息失败
             * -2 获取活动信息失败
             * -1 活动未开启
             *  0 失败
             *  1 成功
             *  2 已达上限
             */
            $return = ['status' => 0];
            $now = BossClock::now();
            if (Pay::activity(self::ACTIVITY, $now) === null) {
                // 活动未开启
                $return['status'] = -1;
                return $return;
            }
            $activity = self::activityInfo($uid);
            if ($activity === null || $activity['jadetype'] === 0) {
                // 获取活动信息失败
                $return['status'] = -2;
                return $return;
            }
            $times = $activity['jadeturn'] + 1;
            if ($times > self::MAX_TURN) {
                // 已达上限
                $return['status'] = 2;
                return $return;
            }
            $type = $activity['jadetype'];
            // 取赌博规则
            $rule = JadeGamble::ROWS[$type] ?? null;
            if ($rule === null) {
                // 获取赌博信息失败
                $return['status'] = -3;
                return $return;
            }
            $price = $rule['price' . $times];
            if ((int)DB::table('member')->where('uid', $uid)->value('gold') < $price) {
                $return['status'] = -4;
                return $return;
            }
            // 赌博结果（概率 probability{手数}，成功才扣元宝）
            if (mt_rand(1, 100) <= (int)$rule['probability' . $times]) {
                $success = 1;
                // 扣元宝
                if (!Member::deductCurrency($uid, gold: $price)) {
                    $return['status'] = -4;
                    return $return;
                }
                DB::table('jade_gamble_log')->insert([
                    'uid' => $uid,
                    'jadetype' => $type,
                    'jadeturn' => $times,
                    'jadenum' => $activity['jadenum'],
                    'dateline' => $now,
                ]);
                $multiple = 2 ** $times;
                $num = $activity['jadenum'] * $multiple;
                $return['info'] = ['type' => $type, 'num' => $num, 'multiple' => $multiple];
                if ($times < self::MAX_TURN) {
                    if (DB::table('member_activity')->where('uid', $uid)->whereNull('deleted_at')->update(['jadeturn' => $times]) !== 1) {
                        // 更新用户信息失败
                        $return['status'] = -6;
                        return $return;
                    }
                    $return['info']['next'] = $num * 2;
                    $return['info']['price'] = $rule['price' . ($times + 1)];
                } else {
                    $add = self::addJade($uid, $type, $num, 'gambling', $now);
                    if ($add === null) {
                        // 加宝石失败
                        $return['status'] = -7;
                        return $return;
                    }
                    $return['jade'][$type] = $add;
                    $return['add_jade'] = ['type' => $type, 'num' => $num];
                }
            } else {
                $success = 0;
                $multiple = 2 ** $activity['jadeturn'];
                $num = $activity['jadenum'] * $multiple;
                $add = self::addJade($uid, $type, $num, 'gambling', $now);
                if ($add === null) {
                    $return['status'] = -7;
                    return $return;
                }
                $return['jade'][$type] = $add;
                $return['info'] = ['type' => $type, 'num' => $num, 'multiple' => $multiple];
                $return['add_jade'] = ['type' => $type, 'num' => $num];
            }
            // 发放赌博奖励
            $return['fb_info'] = self::sendFbReward($uid, $type, $multiple, $now);
            $return['status'] = 1;
            $return['gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
            $return['success'] = $success;
            return $return;
        });
    }

    /**
     * 收获: c=jadegamble&m=harvest
     */
    public static function harvest(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return Member::locked($uid, static function () use ($uid): array {
            /**
             * -3 获取宝石失败
             * -2 获取用户信息失败
             * -1 活动未开启
             *  0 失败
             *  1 成功
             */
            $return = ['status' => 0];
            $now = BossClock::now();
            if (Pay::activity(self::ACTIVITY, $now) === null) {
                // 活动未开启
                $return['status'] = -1;
                return $return;
            }
            $activity = self::activityInfo($uid);
            if ($activity === null || $activity['jadetype'] === 0) {
                // 获取活动信息失败
                $return['status'] = -2;
                return $return;
            }
            $type = $activity['jadetype'];
            $num = $activity['jadenum'] * 2 ** $activity['jadeturn'];
            $add = self::addJade($uid, $type, $num, 'harvest', $now);
            if ($add === null) {
                // 加宝石失败
                $return['status'] = -3;
                return $return;
            }
            // 修改活动收获（update_action_log：该玩家最近一条活动记录记上获得的宝石数）
            $id = DB::table('jadegamble_action_log')->where('uid', $uid)->whereNull('deleted_at')->max('id');
            if ($id !== null) {
                DB::table('jadegamble_action_log')->where('id', (int)$id)->update(['get' . $type => $num]);
            }
            $return['status'] = 1;
            $return['add_jade'] = ['type' => $type, 'num' => $num];
            $return['jade'][$type] = $add;
            return $return;
        });
    }

    /**
     * 当前挖到的宝石（_get_info）；没有为 null
     *
     * @return array{type: int, multiple: int, num: int, next: int, price: int|null}|null
     */
    private static function info(int $uid): ?array
    {
        $activity = self::activityInfo($uid);
        if ($activity === null || $activity['jadetype'] === 0) {
            return null;
        }
        $rule = JadeGamble::ROWS[$activity['jadetype']] ?? [];
        $turn = $activity['jadeturn'];
        $multiple = 2 ** $turn;
        return [
            'type' => $activity['jadetype'],
            'multiple' => $multiple,
            'num' => $multiple * $activity['jadenum'],
            'next' => $multiple * $activity['jadenum'] * 2,
            'price' => $rule['price' . ($turn + 1)] ?? null,
        ];
    }

    /**
     * 发宝石并清空暂存（_add_jade）
     *
     * @return int|null 该类宝石增加后的数量；失败为 null
     */
    private static function addJade(int $uid, int $type, int $num, string $method, int $now): ?int
    {
        $update = DB::table('member_activity')->where('uid', $uid)->where('jadetype', $type)->whereNull('deleted_at')
            ->update(['jadetype' => 0, 'jadenum' => 0, 'jadeturn' => 0]);
        if ($update !== 1) {
            // 更新用户信息失败
            return null;
        }
        $add = TraderLog::addJade($uid, $type, $num, class: 'jadegamble', method: $method, now: $now);
        return $add['status'] === 1 ? (int)$add['num'] : null;
    }

    /**
     * 挖掘（jadegamble_model->mining：按权重抽 1 ~ 10 颗）
     *
     * @param array<int, int> $rule
     */
    private static function miningRoll(array $rule): int
    {
        $sum = array_sum($rule);
        if ($sum === 0) {
            return 0;
        }
        $roll = mt_rand(1, $sum);
        $cursor = 0;
        for ($i = 1; $i <= 10; $i++) {
            $cursor += $rule[$i];
            if ($cursor >= $roll) {
                return $i;
            }
        }
        return 0;
    }

    /**
     * 发放赌博奖励（jadegamble_fb_reward_model->send_fb_reward：is_success 固定为 1；该倍数没有配置时倍数翻倍再找，到 1024 为止）
     *
     * @return array<string, mixed>
     */
    private static function sendFbReward(int $uid, int $jadetype, int $multiple, int $now): array
    {
        $list = [];
        while (true) {
            $list = array_values(array_filter(
                JadegambleFbReward::ROWS,
                static fn(array $v): bool => $v['jadetype'] === $jadetype && $v['multiple'] === $multiple && $v['is_success'] === 1
            ));
            if ($list !== []) {
                break;
            }
            if ($multiple < 1024) {
                $multiple *= 2;
            } else {
                break;
            }
        }
        if ($list === []) {
            return ['msg' => '', 'status' => 0];
        }
        $allcursor = 0;
        foreach ($list as $val) {
            $allcursor += $val['getrate'] * 1000;
        }
        $randnumber = mt_rand(1, $allcursor);
        $cursor = 0;
        $rewardKey = 0;
        foreach ($list as $key => $val) {
            $cursor += $val['getrate'] * 1000;
            if ($cursor >= $randnumber) {
                $rewardKey = $key;
                break;
            }
        }
        return SendReward::send($uid, $list[$rewardKey]['type'], $list[$rewardKey]['value'], 'jadegamble', 'gambling', $now);
    }

    /**
     * 玩家活动信息（get_activity_info：没有时建档）
     *
     * @return array{jadetype: int, jadenum: int, jadeturn: int}|null
     */
    private static function activityInfo(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        MemberActivity::ensure($uid);
        return MemberActivity::many([$uid], ['jadetype', 'jadenum', 'jadeturn'])[$uid] ?? null;
    }
}
