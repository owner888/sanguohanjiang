<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Handler\Activity\MessageHandler;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\General\TavernHandler;
use Hanjiang\Model\ActivityClock;
use Hanjiang\MasterData\BeatengodBoxReward;
use Hanjiang\MasterData\BeatengodHitChip;
use Hanjiang\MasterData\BeatengodHitReward;
use Hanjiang\MasterData\BeatengodRankReward;
use Hanjiang\MasterData\Equip as EquipTemplate;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Chat;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FlowLog;
use Hanjiang\Model\FreeLeap;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberJade;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\Pay;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 暴打名将（移植旧版 controllers/beatengod.php + models/beatengod_model.php，计划任务 cron::get_beatengod_reward /
 * get_beatengod_box_reward）
 *
 * 客户端（beatengodpop.js）调用：index / get_box_reward / beaten / get_pay_chip_info / get_pay_chip / get_rank_list /
 * get_rank_reward。运营活动 ActivityTime 的 beatengod（旧版数据 2016-03-20 已结束，接口回 -1）；活动结束后图标多挂一天，
 * 所以活动提前一天结束（_end = endtime - 1 天）。当前时间取 BossClock（生产恒为 time()）。
 * 花元宝用木锤 / 金锤（MasterData BeatengodHitChip：价格与碎片数 2~4 / 8~16 的权重）暴打得碎片（beatengod_log.own_chip）与
 * 常规奖励（BeatengodHitReward）；碎片排行前 30 名领宝石（BeatengodRankReward）；碎片累计（含充值碎片）达到 BeatengodBoxReward
 * 的数量开宝箱。活动期间充值记账 beatengod_order（Model\Pay::complete 调 addPayOrder），每 1 元 1 个充值碎片；活动结束后
 * 领的充值碎片记 end_chip（不进排行）。活动结束 10 小时内计划任务（BeatengodCron）给未领取的前 30 名发排行奖励、给碎片达标
 * 的玩家补开宝箱，均发信件。
 *
 * 照旧版保留的写法：暴打的碎片与奖励随机数是 mt_rand(50, 总权重)（权重 50 以下的部分抽不到）；beaten 在活动结束（_cd = 0）后回 -1；
 * 宝箱奖励的银币不乘主公等级、暴打奖励的银币乘主公等级；get_pay_chip_info 在活动结束后仍可查看。
 * 与旧版的差别：写操作在 Member::locked（玩家行锁）里执行；beatengod_log 以 version + uid 为唯一键、beatengod_reward_log 以
 * uid + version + rewardid 为唯一键（旧版无索引，并发会插出重复记录 / 重复领宝箱）。
 */
final class BeatengodHandler
{
    /** 运营活动名（ActivityTime.name） */
    private const string ACTIVITY = 'beatengod';
    /** 碎片排行个数 */
    private const int RANK_LIMIT = 30;
    /** 旧版语言包 chat_beatengod_get_quality */
    private const string LANG_GET_QUALITY = '恭喜【%s】在暴打名将活动中获得装备-%s';
    /** 旧版语言包 cron_beaten_box_reward */
    private const string LANG_CRON_BOX = '主公！暴打名将活动已结束，您未领取的宝箱奖励已自动帮您领取，共获得：%s请查收';
    /** 系统信件标题（旧版 message_model->send_message type 1） */
    private const string LANG_SYSTEM_TITLE = '系统通知';
    /** 计划任务按奖励类型拼信件用的旧版语言包（base_silver / base_act / base_leap1 / base_jade2 / base_jade3 / base_jade / base_soul / base_reputation） */
    private const array LANG_TYPE = [1 => '银币', 2 => '军令', 3 => '初级突飞卡', 4 => '黄宝石', 5 => '紫宝石', 6 => '玉石', 7 => '原魂', 9 => '声望'];

    /**
     * 进入活动: c=beatengod&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $act = self::activity();
        if ($act === null) {
            $return['status'] = -1;
            return $return;
        }
        $uid = MemberHandler::resolveUid($ctx);
        $memberInfo = self::memberInfo($uid, $act['version'], $act['now']);
        if ($memberInfo === null) {
            $return['status'] = -2;
            return $return;
        }
        $boxReward = array_values(BeatengodBoxReward::ROWS);
        // 获取宝箱奖励领取记录
        $rewardLog = self::rewardLog($uid, $act['version']);
        $ownChip = $memberInfo['own_chip'] + $memberInfo['end_chip'];
        foreach ($boxReward as $key => $val) {
            for ($i = 1; $i <= 3; $i++) {
                if ($val['type' . $i] === 8) {
                    // 查询道具信息
                    $item = EquipTemplate::ROWS[$val['value' . $i]] ?? null;
                    $boxReward[$key]['value' . $i] = $item['name'] ?? null;
                    $boxReward[$key]['quality' . $i] = $item['quality'] ?? null;
                    $boxReward[$key]['itemid' . $i] = $item['id'] ?? null;
                }
            }
            if (isset($rewardLog[$val['id']])) {
                // 宝箱已打开
                $boxReward[$key]['open'] = 2;
            } else {
                // 宝箱可开启 / 未开启
                $boxReward[$key]['open'] = $val['number'] <= $ownChip ? 1 : 0;
            }
        }
        // 获取道具价格
        $item = [];
        foreach (array_values(BeatengodHitChip::ROWS) as $key => $val) {
            $item[$key] = ['id' => $val['id'], 'price' => $val['price']];
        }
        $return['status'] = 1;
        $return['reward'] = $boxReward;
        $return['own_chip'] = $ownChip;
        $return['item'] = $item;
        $return['cd'] = $act['cd'];
        return $return;
    }

    /**
     * 领取宝箱奖励: c=beatengod&m=get_box_reward&id=
     */
    public static function get_box_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        return Member::locked($uid, static function () use ($uid, $id): array {
            $return = ['status' => 0];
            $act = self::activity();
            if ($act === null) {
                $return['status'] = -1;
                return $return;
            }
            $now = $act['now'];
            $memberInfo = self::memberInfo($uid, $act['version'], $now);
            if ($memberInfo === null) {
                $return['status'] = -2;
                return $return;
            }
            $reward = BeatengodBoxReward::ROWS[$id] ?? null;
            if ($reward === null) {
                $return['status'] = -3;
                return $return;
            }
            // 判断自己是否可以领取该奖励
            if ($memberInfo['own_chip'] + $memberInfo['end_chip'] < $reward['number']) {
                $return['status'] = -4;
                return $return;
            }
            if (isset(self::rewardLog($uid, $act['version'])[$id])) {
                $return['status'] = -5;
                return $return;
            }
            // 添加领奖记录
            $added = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_beatengod_reward_log` (`uid`, `version`, `rewardid`, `dateline`) VALUES (:uid, :version, :id, :now)',
                ['uid' => $uid, 'version' => $act['version'], 'id' => $id, 'now' => $now]
            );
            if ($added === 0) {
                $return['status'] = -6;
                return $return;
            }
            $nickname = (string)DB::table('member')->where('uid', $uid)->value('nickname');
            // 添加奖励
            for ($i = 1; $i <= 3; $i++) {
                $type = $reward['type' . $i];
                $value = $reward['value' . $i];
                if ($type === 8) {
                    // 红装-整件
                    $item = EquipTemplate::ROWS[$value] ?? null;
                    if ($item !== null) {
                        $reward['value' . $i] = ['itemid' => $item['id'], 'quality' => $item['quality'], 'itemname' => $item['name']];
                        Equipment::addFromTemplate($uid, $value);
                        // 橙装发送公告（小喇叭）
                        if ($item['quality'] > 6) {
                            $name = Chat::quality($item['quality'], (string)$item['name']);
                            Chat::sendToHorn(sprintf(self::LANG_GET_QUALITY, $nickname, $name), Chat::sender($uid));
                        }
                    }
                    continue;
                }
                // 宝箱奖励中的银币，不是系数
                self::grant($uid, $type, $value, 'get_box_reward', $now);
            }
            $after = DB::table('member')->select('gold', 'silver', 'act')->where('uid', $uid)->first();
            $return['status'] = 1;
            $return['reward'] = $reward;
            $return['all_gold'] = (int)$after['gold'];
            $return['all_silver'] = (int)$after['silver'];
            $return['all_act'] = (int)$after['act'];
            return $return;
        });
    }

    /**
     * 暴打: c=beatengod&m=beaten&id=（1 木锤，2 金锤）
     */
    public static function beaten(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        return Member::locked($uid, static function () use ($uid, $id): array {
            $return = ['status' => 0];
            if (!$id) {
                $return['status'] = -7;
                return $return;
            }
            $act = self::activity();
            // 判断活动是否结束 / 开启
            if ($act === null || $act['cd'] <= 0) {
                $return['status'] = -1;
                return $return;
            }
            $now = $act['now'];
            $chip = BeatengodHitChip::ROWS[$id] ?? null;
            if ($chip === null) {
                $return['status'] = -2;
                return $return;
            }
            $member = DB::table('member')->select('level', 'gold')->where('uid', $uid)->whereNull('deleted_at')->first();
            if ($member === null || (int)$member['gold'] < $chip['price']) {
                $return['status'] = -3;
                return $return;
            }
            // 得到要计算的数量和概率（木锤碎片 2 ~ 4，金锤 8 ~ 16）
            $weights = [];
            foreach ($id === 1 ? range(2, 4) : range(8, 16) as $n) {
                $weights[$n] = $chip['number' . $n];
            }
            $addChip = self::roll($weights) ?? 0;
            // 得到常规奖励数据
            $itemReward = array_values(array_filter(BeatengodHitReward::ROWS, static fn (array $r): bool => $r['hit_type'] === $id));
            if ($itemReward === []) {
                $return['status'] = -4;
                return $return;
            }
            $rates = [];
            foreach ($itemReward as $key => $val) {
                $rates[$key] = (int)$val['rate'];
            }
            $pick = self::roll($rates);
            $addReward = $pick === null ? [] : $itemReward[$pick];
            // 扣除元宝
            if (!Member::deductCurrency($uid, gold: $chip['price'])) {
                $return['status'] = -5;
                return $return;
            }
            $type = (int)($addReward['type'] ?? 0);
            if ($type === 1) {
                // 银币（× 主公等级）
                $addReward['value'] = $addReward['value'] * (int)$member['level'];
            }
            if ($type < 1 || $type > 7) {
                // 奖励类型错误（旧版元宝已扣）
                $return['status'] = -6;
                return $return;
            }
            self::grant($uid, $type, (int)$addReward['value'], 'beaten', $now);
            // 添加碎片
            $number = self::addChip($uid, $act['version'], $addChip, 0, $now);
            // 记录人次
            DB::table('beatengod_action_log')->insert([
                'uid' => $uid,
                'type' => $id,
                'gold' => $chip['price'],
                'level' => (int)$member['level'],
                'dateline' => $now,
            ]);
            $after = DB::table('member')->select('gold', 'silver', 'act')->where('uid', $uid)->first();
            $return['status'] = 1;
            $return['add_chip'] = $addChip;
            $return['number'] = $number;
            $return['gettype'] = $type;
            $return['getvalue'] = $addReward['value'];
            $return['all_gold'] = (int)$after['gold'];
            $return['all_silver'] = (int)$after['silver'];
            $return['all_act'] = (int)$after['act'];
            return $return;
        });
    }

    /**
     * 查询充值碎片领取情况: c=beatengod&m=get_pay_chip_info
     */
    public static function get_pay_chip_info(Context $ctx): array
    {
        $return = ['status' => 0];
        $act = self::activity();
        if ($act === null) {
            $return['status'] = -1;
            return $return;
        }
        $uid = MemberHandler::resolveUid($ctx);
        $memberInfo = self::memberInfo($uid, $act['version'], $act['now']);
        if ($memberInfo === null) {
            $return['status'] = -2;
            return $return;
        }
        // 本次活动充值金额，每 1 元 1 个碎片
        $amount = self::amountByOrder($uid, $act['version']);
        $allChip = $amount;
        $return['status'] = 1;
        $return['gold'] = $amount * 10;
        $return['get'] = $memberInfo['pay_chip'];
        $return['isget'] = $memberInfo['pay_chip'] < $allChip ? 1 : 0;
        return $return;
    }

    /**
     * 领取充值碎片: c=beatengod&m=get_pay_chip
     */
    public static function get_pay_chip(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return Member::locked($uid, static function () use ($uid): array {
            $return = ['status' => 0];
            $act = self::activity();
            if ($act === null) {
                $return['status'] = -1;
                return $return;
            }
            $now = $act['now'];
            // 活动结束后领的记 end_chip
            $ended = $act['cd'] <= 0 ? 1 : 0;
            $memberInfo = self::memberInfo($uid, $act['version'], $now);
            if ($memberInfo === null) {
                $return['status'] = -2;
                return $return;
            }
            $allChip = self::amountByOrder($uid, $act['version']);
            if ($memberInfo['pay_chip'] >= $allChip) {
                $return['status'] = -3;
                return $return;
            }
            // 修改记录中已领取碎片数量
            DB::table('beatengod_log')->where('uid', $uid)->where('version', $act['version'])->whereNull('deleted_at')->update(['pay_chip' => $allChip]);
            // 添加碎片
            $addChip = $allChip - $memberInfo['pay_chip'];
            $number = self::addChip($uid, $act['version'], $addChip, $ended, $now);
            $return['status'] = 1;
            $return['add_chip'] = $addChip;
            $return['all_chip'] = $number;
            $return['all_pay_chip'] = $allChip;
            return $return;
        });
    }

    /**
     * 查看排行: c=beatengod&m=get_rank_list
     */
    public static function get_rank_list(Context $ctx): array
    {
        $return = ['status' => 0];
        $act = self::activity();
        if ($act === null) {
            $return['status'] = -1;
            return $return;
        }
        $uid = MemberHandler::resolveUid($ctx);
        $list = self::rankList($act['version']);
        $myRank = 0;
        $myInfo = [];
        foreach ($list as $key => $val) {
            $list[$key]['rank'] = $key + 1;
            if ($val['uid'] === $uid) {
                $myRank = $key + 1;
                $myInfo = $val;
            }
            unset($list[$key]['version'], $list[$key]['pay_chip'], $list[$key]['get_rank'], $list[$key]['dateline']);
        }
        // 取排行奖励
        foreach ($list as $key => $val) {
            $reward = BeatengodRankReward::ROWS[$val['rank']] ?? null;
            $list[$key]['type'] = $reward['type'] ?? 0;
            $list[$key]['value'] = $reward['value'] ?? 0;
        }
        // 返回排行奖励状态（0没有排行奖励，1可领取，2已领取）
        $isget = 0;
        if ($act['cd'] <= 0) {
            if ($myRank > 0 && $myInfo['get_rank'] > 0) {
                $isget = 2;
            } elseif ($myRank > 0) {
                $isget = 1;
            }
        }
        $return['status'] = 1;
        $return['list'] = $list;
        $return['isget'] = $isget;
        return $return;
    }

    /**
     * 领取排行奖励: c=beatengod&m=get_rank_reward
     */
    public static function get_rank_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return Member::locked($uid, static function () use ($uid): array {
            $return = ['status' => 0];
            $act = self::activity();
            if ($act === null) {
                $return['status'] = -1;
                return $return;
            }
            $now = $act['now'];
            // 判断是否到领奖的时候了
            if ($act['end'] - $now > 0) {
                $return['status'] = -2;
                return $return;
            }
            $memberInfo = self::memberInfo($uid, $act['version'], $now);
            if ($memberInfo === null) {
                $return['status'] = -3;
                return $return;
            }
            // 获取自己的排名
            $myrank = 0;
            foreach (self::rankList($act['version']) as $i => $val) {
                if ($val['uid'] === $uid) {
                    $myrank = $i + 1;
                }
            }
            if ($myrank === 0) {
                $return['status'] = -4;
                return $return;
            }
            if ($memberInfo['get_rank'] > 0) {
                $return['status'] = -5;
                return $return;
            }
            $reward = BeatengodRankReward::ROWS[$myrank] ?? null;
            if ($reward === null) {
                $return['status'] = -6;
                return $return;
            }
            // 修改领奖状态
            if (self::updateRankRewardLog($act['version'], $myrank, $uid) !== 1) {
                $return['status'] = -7;
                return $return;
            }
            TavernHandler::addJade($uid, $reward['type'], $reward['value'], 'beatengod', 'get_rank_reward');
            $return['status'] = 1;
            $return['reward'] = $reward;
            return $return;
        });
    }

    /**
     * 暴打名将期间充值记账（add_beatengod_pay_order：活动开启且离结束超过 1 天时，由 Model\Pay::complete 在到账事务里调用）
     *
     * @param int $amount 充值金额（人民币元）
     */
    public static function addPayOrder(int $uid, int $level, int $amount, string $orderId, int $now): void
    {
        $info = Pay::activity(self::ACTIVITY, $now);
        // 活动结束后图标要多挂一天，所以活动会提前一天结束
        if ($info === null || (int)$info['endtime'] - 86400 <= $now) {
            return;
        }
        [$class, $method] = FlowLog::current();
        DB::table('beatengod_order')->insert([
            'uid' => $uid,
            'level' => $level,
            'money' => $amount,
            'version' => (int)$info['version'],
            'orderid' => $orderId,
            'class' => Member::logText($class, 20),
            'method' => Member::logText($method, 20),
            'dateline' => $now,
        ]);
    }

    /**
     * 计划任务：活动结束 10 小时内给未领取的前 30 名发排行奖励（cron::get_beatengod_reward）
     *
     * @return int 1 已执行；-1 无活动；-2 活动未结束；-3 结束超过 10 小时
     */
    public static function settleRankReward(int $now): int
    {
        $window = self::cronWindow($now);
        if ($window < 0) {
            return $window;
        }
        foreach (self::rankList($window) as $i => $val) {
            $rank = $i + 1;
            $reward = BeatengodRankReward::ROWS[$rank] ?? null;
            // 给未领取排行奖励的用户添加奖励
            if ($val['get_rank'] !== 0 || $reward === null) {
                continue;
            }
            if (self::updateRankRewardLog($window, $rank, $val['uid']) !== 1) {
                continue;
            }
            TavernHandler::addJade($val['uid'], $reward['type'], $reward['value'], 'cron', 'get_beatengod_reward');
            // 发信
            $message = '暴打名将活动已结束，您未领取的排行奖励，系统已自动领取，获得了第' . $rank . '名的奖励：';
            $message .= match ($reward['type']) {
                1 => '蓝宝石',
                2 => '黄宝石',
                3 => '紫宝石',
                default => '',
            };
            $message .= $reward['value'] . '个，请查收!';
            MessageHandler::sendSystemMessage([$val['uid']], 1, self::LANG_SYSTEM_TITLE, $message);
        }
        return 1;
    }

    /**
     * 计划任务：活动结束 10 小时内给碎片（含未领取的充值碎片）达标的玩家补开宝箱（cron::get_beatengod_box_reward）
     *
     * 同类奖励合并后发放并发一封信；红装（type 8）按件发放，信里写装备名。
     *
     * @return int 1 已发放；-1 无活动；-2 活动未结束；-3 结束超过 10 小时；-5 没有用户可领取；-6 没有可发放的用户
     */
    public static function settleBoxReward(int $now): int
    {
        $version = self::cronWindow($now);
        if ($version < 0) {
            return $version;
        }
        // 最小的宝箱奖励需求碎片个数
        $minChip = min(array_column(BeatengodBoxReward::ROWS, 'number'));
        // 获取用户充值积分列表
        $chipList = [];
        foreach (DB::query(
            'SELECT `uid`, SUM(`money`) AS `money` FROM `zc_sanguo_beatengod_order` WHERE `version` = :version AND `deleted_at` IS NULL GROUP BY `uid`',
            ['version' => $version]
        ) as $row) {
            $chipList[(int)$row['uid']] = (int)round((float)$row['money']);
        }
        // 取充值的人或者碎片大于宝箱最低值的用户
        $query = DB::table('beatengod_log')->where('version', $version)->whereNull('deleted_at');
        $users = [];
        foreach ($query->get() as $row) {
            $row = self::cast($row);
            if ($row['own_chip'] + $row['end_chip'] >= $minChip || isset($chipList[$row['uid']])) {
                $users[] = $row;
            }
        }
        if ($users === []) {
            return -5;
        }
        $logList = [];
        foreach (DB::table('beatengod_reward_log')->select('uid', 'rewardid')->where('version', $version)
            ->whereIn('uid', array_column($users, 'uid'))->whereNull('deleted_at')->get() as $row) {
            $logList[(int)$row['uid']][(int)$row['rewardid']] = true;
        }
        $rewards = [];
        foreach ($users as $val) {
            // 可领取的充值碎片数
            $getChip = 0;
            if (isset($chipList[$val['uid']]) && $val['pay_chip'] !== $chipList[$val['uid']]) {
                $getChip = $chipList[$val['uid']] - $val['pay_chip'];
            }
            foreach (BeatengodBoxReward::ROWS as $box) {
                if ($val['own_chip'] + $val['end_chip'] + $getChip < $box['number'] || isset($logList[$val['uid']][$box['id']])) {
                    continue;
                }
                $added = DB::execute(
                    'INSERT IGNORE INTO `zc_sanguo_beatengod_reward_log` (`uid`, `version`, `rewardid`, `dateline`) VALUES (:uid, :version, :id, :now)',
                    ['uid' => $val['uid'], 'version' => $version, 'id' => $box['id'], 'now' => $now]
                );
                if ($added === 0) {
                    continue;
                }
                for ($i = 1; $i <= 3; $i++) {
                    $type = $box['type' . $i];
                    if ($type > 0) {
                        $rewards[$val['uid']][$type] = ($rewards[$val['uid']][$type] ?? 0) + $box['value' . $i];
                    }
                }
            }
        }
        if ($rewards === []) {
            return -6;
        }
        // 发送奖励
        foreach ($rewards as $uid => $list) {
            $message = '';
            foreach ($list as $type => $value) {
                if ($type === 8) {
                    // 红装-整件（同种红装合并后的 value 是 id 之和，旧版如此）
                    $item = EquipTemplate::ROWS[$value] ?? null;
                    if ($item !== null) {
                        Equipment::addFromTemplate($uid, $value);
                        $value = $item['name'];
                    }
                    $message .= $value . ',';
                    continue;
                }
                $message .= $value;
                self::grant($uid, $type, $value, 'get_beatengod_box_reward', $now, 'cron');
                $message .= (self::LANG_TYPE[$type] ?? '') . ',';
            }
            MessageHandler::sendSystemMessage([$uid], 1, self::LANG_SYSTEM_TITLE, sprintf(self::LANG_CRON_BOX, $message));
        }
        return 1;
    }

    /**
     * 当前活动信息（_init_activity）：开启时返回版本、结束时间（endtime - 1 天）与倒计时；未开启返回 null
     *
     * @return array{version: int, end: int, cd: int, now: int}|null
     */
    private static function activity(): ?array
    {
        $now = BossClock::now();
        $info = Pay::activity(self::ACTIVITY, $now);
        if ($info === null) {
            return null;
        }
        $end = (int)$info['endtime'] - 86400;
        return ['version' => (int)$info['version'], 'end' => $end, 'cd' => max(0, $end - $now), 'now' => $now];
    }

    /**
     * 计划任务的发放窗口（get_activity_by_start：活动结束后 10 小时内），返回活动版本或状态码
     */
    private static function cronWindow(int $now): int
    {
        $row = ActivityClock::byName(self::ACTIVITY);
        if ($row === null) {
            return -1;
        }
        if ($row['endtime'] > $now) {
            // 活动未结束
            return -2;
        }
        if ($row['endtime'] + 36000 < $now) {
            // 活动结束很久了
            return -3;
        }
        return (int)$row['version'];
    }

    /**
     * 发一种奖励（1 银币、2 军令、3 突飞卡、4 黄宝石、5 紫宝石、6 玉石、7 原魂、9 声望）
     */
    private static function grant(int $uid, int $type, int $value, string $method, int $now, string $class = 'beatengod'): void
    {
        match ($type) {
            1 => Member::addCurrency($uid, silver: $value),
            2 => Member::addCurrency($uid, act: $value),
            3 => FreeLeap::add($uid, $value, $now),
            4 => TavernHandler::addJade($uid, 2, $value, $class, $method),
            5 => TavernHandler::addJade($uid, 3, $value, $class, $method),
            6 => MemberJade::add($uid, $value, $class, $method, $now),
            7 => OwnSoul::addSoul($uid, $value, 0, $class, $method, $now),
            9 => Member::addReputation($uid, $value),
            default => null,
        };
    }

    /**
     * 按权重抽一个键（mt_rand(50, 总权重)，累计到第一个不小于随机数的键）；抽不到返回 null
     *
     * @param array<int, int> $weights
     */
    private static function roll(array $weights): ?int
    {
        $total = array_sum($weights);
        $rand = mt_rand(50, max(50, $total));
        $now = 0;
        foreach ($weights as $key => $val) {
            $now += $val;
            if ($rand <= $now) {
                return $key;
            }
        }
        return null;
    }

    /**
     * 用户活动信息（get_member_info：首次进入建档）
     *
     * @return array<string, mixed>|null
     */
    private static function memberInfo(int $uid, int $version, int $now): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        $row = self::row($uid, $version);
        if ($row !== null) {
            return $row;
        }
        $nickname = (string)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('nickname') ?? '');
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_beatengod_log` (`version`, `uid`, `name`, `own_chip`, `pay_chip`, `end_chip`, `dateline`)
             VALUES (:version, :uid, :name, 0, 0, 0, :now)',
            ['version' => $version, 'uid' => $uid, 'name' => Member::logText($nickname, 20), 'now' => $now]
        );
        return self::row($uid, $version);
    }

    /** beatengod_log 的旧版列 */
    private const array COLUMNS = ['id', 'version', 'uid', 'name', 'own_chip', 'pay_chip', 'end_chip', 'get_rank', 'dateline'];

    /**
     * @return array<string, mixed>|null
     */
    private static function row(int $uid, int $version): ?array
    {
        $row = DB::table('beatengod_log')->select(...self::COLUMNS)->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->first();
        return $row === null ? null : self::cast($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function cast(array $row): array
    {
        foreach ($row as $key => $value) {
            $row[$key] = $key === 'name' ? (string)$value : (int)$value;
        }
        return $row;
    }

    /**
     * 宝箱奖励领取记录（get_reward_log：rewardid => 行）
     *
     * @return array<int, bool>
     */
    private static function rewardLog(int $uid, int $version): array
    {
        $return = [];
        foreach (DB::table('beatengod_reward_log')->select('rewardid')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->get() as $row) {
            $return[(int)$row['rewardid']] = true;
        }
        return $return;
    }

    /**
     * 添加碎片（add_chip：没有记录时建档；$ended 为 1 时记 end_chip，否则 own_chip），返回添加后的碎片总数
     */
    private static function addChip(int $uid, int $version, int $number, int $ended, int $now): int
    {
        $info = self::memberInfo($uid, $version, $now);
        if ($info === null) {
            return 0;
        }
        $column = $ended === 1 ? 'end_chip' : 'own_chip';
        DB::table('beatengod_log')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')
            ->update([$column => $info[$column] + $number, 'dateline' => $now]);
        return $info['own_chip'] + $info['end_chip'] + $number;
    }

    /**
     * 本次活动充值金额（get_amount_by_order：sum(money)）
     */
    private static function amountByOrder(int $uid, int $version): int
    {
        $sum = DB::query(
            'SELECT SUM(`money`) AS `money` FROM `zc_sanguo_beatengod_order` WHERE `uid` = :uid AND `version` = :version AND `deleted_at` IS NULL',
            ['uid' => $uid, 'version' => $version]
        )[0]['money'] ?? 0;
        return (int)round((float)$sum);
    }

    /**
     * 碎片排名前 30 的用户（get_rank_list：own_chip > 0，碎片降序、时间升序）
     *
     * @return list<array<string, mixed>>
     */
    private static function rankList(int $version): array
    {
        $rows = DB::table('beatengod_log')->select(...self::COLUMNS)->where('version', $version)->where('own_chip', '>', 0)
            ->whereNull('deleted_at')->orderBy('own_chip', 'DESC')->orderBy('dateline', 'ASC')->limit(self::RANK_LIMIT)->get();
        return array_map(self::cast(...), $rows);
    }

    /**
     * 修改排行奖励领取状态（update_rank_reward_log）：1 成功，0 没有修改
     */
    private static function updateRankRewardLog(int $version, int $rank, int $uid): int
    {
        $affected = DB::table('beatengod_log')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')
            ->update(['get_rank' => $rank]);
        return $affected > 0 ? 1 : 0;
    }
}
