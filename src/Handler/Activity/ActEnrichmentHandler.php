<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\MasterData\ActenrichmentReward;
use Hanjiang\MasterData\Soul;
use Hanjiang\Model\ActivityClock;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Equipment;
use Hanjiang\Model\FestReward;
use Hanjiang\Model\FestLock;
use Hanjiang\Model\Member;
use Hanjiang\Model\OwnSoul;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 貔貅聚财（移植旧版 controllers/act_enrichment.php + models/actenrichment_model.php，计划任务
 * cron_acttask.php:get_actenrichment_reward，充值记账 pay.php:pay_act_init，主城图标 member.php index）
 *
 * 活动时间与版本取 ActivityTime 的 actenrichment（旧版数据已结束）。活动的最后 7 天为领奖期（_day = 7），之前为充值期：
 * 充值期内每笔充值按商品元宝记 actenrichment_order（Model\Pay::complete 调 addPayOrder，结束前 7 天停止记账）；领奖期按累计充值
 * 达到的最高档（MasterData ActenrichmentReward 的 gold）每天领一份（day1 ~ day7 的 type / value，第 7 天 type 2 将魂（value 将魂 id、
 * value1 等级）/ 3 装备）。领奖期内没有充值或累计未达到第一档时活动按未开启处理。活动结束后 1 小时内计划任务补发没领的天数（同类合并、
 * 发信件）。主城图标：结束前 7 天起，没有可领的奖励时为 0（MemberHandler 调 iconStatus）。
 * 客户端 enrichmentpop.js 调用 index、reward_index、get_day_reward（day）。当前时间取 BossClock。
 *
 * 照旧版：reward_index 每天的奖励数组跨档复用（前一档第 7 天的 value1 / info 会带到下一档的前 6 天）；当前档的领取状态按「某天有任意
 * 档的领取记录」判断；元宝奖励类型 1 无论是否查看都会发放（只在领奖时调用，不影响）。
 * 与旧版的差别：领奖锁改为 Redis SET NX；领取记录 uid + version + day 唯一（旧版即如此），插入成功才发奖；计划任务逐条插入记录、
 * 成功才计入补发（旧版批量插入，重复执行时整批失败但照发）。
 */
final class ActEnrichmentHandler
{
    public const string ACTIVITY = 'actenrichment';

    /** 领取奖励天数(挂图标时间) */
    private const int DAY = 7;
    /** 领奖锁 */
    private const string LOCK_KEY = 'enrichment_get_reward_lock';

    /** actenrichment_lang.php + cron_lang.php */
    private const array LANG = [
        'activities_unopened' => '活动未开启。',
        'activities_unclosed' => '活动未结束。',
        'reward_table_error' => '奖励表错误。',
        'already_get_raward' => '已经领取奖励。',
        'passing_parameters_error' => '传递参数错误。',
        'request_fast_erro' => '请求不要太频繁。',
        'add_reward_log_error' => '记录领取奖励日志错误。',
        'day_error' => '天数错误。',
        'gold_error' => '充值元宝不足。',
        'cron_enrichment_end' => '亲爱的玩家！貔貅聚财活动已结束，您未领取的奖励系统已自动帮您领取，',
        'cron_get_reward' => '获得奖励：',
        'cron_sycee' => '元宝',
        'cron_soul' => '将魂',
        'cron_equip' => '装备-',
        'cron_check' => '请查收!',
        'message_system_title' => '系统通知',
    ];

    /**
     * 首页: c=act_enrichment&m=index
     *
     * -2 获取奖励信息失败；-1 活动未开启；1 成功；0 失败
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $act = self::init($uid, BossClock::now());
        if ($act === null || $act['open'] !== 1) {
            $return['status'] = -1;
            $return['msg'] = self::LANG['activities_unopened'];
            return $return;
        }
        $rewardInfo = $act['reward'];
        if ($rewardInfo === []) {
            $return['status'] = -2;
            $return['msg'] = self::LANG['reward_table_error'];
            return $return;
        }
        $reward = $orderInfo = [];
        // 获取奖励信息（取最后一天奖励）
        foreach ($rewardInfo as $key => $val) {
            $reward[$key] = [
                'status' => 0,
                'type' => $val['day7_type'],
                'value' => $val['day7_value'],
                'value1' => $val['day7_value1'],
            ];
            if (in_array($val['day7_type'], [2, 3], true)) {
                $info = self::getReward($uid, ['type' => $val['day7_type'], 'value' => $val['day7_value'], 'value1' => $val['day7_value1']], 1);
                $reward[$key]['info'] = $info['info'] ?? null;
            }
            // 充值额度处理
            $orderInfo[$key]['gold'] = $val['gold'];
        }
        // 判断当前点亮的是第几类奖励
        $now = self::tier($rewardInfo, $act['gold_num']) - 1;
        foreach ($rewardInfo as $val) {
            if ($act['gold_num'] >= $val['gold']) {
                $reward[max(0, $now)]['status'] = 1;
            }
        }
        // 默认是领取奖励；活动未结束时查看奖励
        $buttonStatus = $act['cd'] > 0 ? 0 : 1;
        $return['status'] = 1;
        $return['cd'] = $act['cd'];
        $return['reward_info'] = $reward;
        $return['order_info'] = $orderInfo;
        $return['gold_num'] = $act['gold_num'];
        $return['button_status'] = $buttonStatus;
        return $return;
    }

    /**
     * 查看奖励: c=act_enrichment&m=reward_index
     *
     * -2 获取奖励信息失败；-1 活动未开启；1 成功；0 失败。is_get：0 不可领，1 可领，2 已领
     */
    public static function reward_index(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $act = self::init($uid, BossClock::now());
        if ($act === null || $act['open'] !== 1) {
            $return['status'] = -1;
            $return['msg'] = self::LANG['activities_unopened'];
            return $return;
        }
        $rewardInfo = $act['reward'];
        if ($rewardInfo === []) {
            $return['status'] = -2;
            $return['msg'] = self::LANG['reward_table_error'];
            return $return;
        }
        $now = 0;
        $count = count($rewardInfo);
        $newReward = $re = $reward = [];
        // 获取奖励信息（旧版 $reward 跨档复用，照旧版）
        foreach ($rewardInfo as $key => $val) {
            for ($i = 0; $i < self::DAY; $i++) {
                $reward['day'] = $i + 1;
                $reward['is_get'] = 0;
                for ($j = 0; $j < $count; $j++) {
                    if ($act['gold_num'] >= $rewardInfo[$j]['gold'] && $i + 1 === $act['today']) {
                        $now = $j;
                    }
                }
                $reward['type'] = $val['day' . ($i + 1) . '_type'];
                $reward['value'] = $val['day' . ($i + 1) . '_value'];
                if ($i === 6) {
                    $reward['value1'] = $val['day' . ($i + 1) . '_value1'];
                }
                if (in_array($reward['type'], [2, 3], true)) {
                    $info = self::getReward($uid, $reward, 1);
                    $reward['info'] = $info['info'] ?? null;
                }
                $re[$i] = $reward;
            }
            $newReward[$key] = $re;
        }
        // 当前的奖励类型：当前领奖第几天之前（含）有领取记录为 2，否则 1
        $logDays = self::logDays($uid, $act['version']);
        foreach ($newReward[$now] ?? [] as $k => $v) {
            $newReward[$now][$k]['is_get'] = 0;
            if ($k + 1 <= $act['today']) {
                $newReward[$now][$k]['is_get'] = isset($logDays[$k + 1]) ? 2 : 1;
            }
        }
        $return['status'] = 1;
        $return['reward_info'] = $newReward;
        return $return;
    }

    /**
     * 领取奖励: c=act_enrichment&m=get_day_reward（day 领取第几天）
     *
     * -9 操作太快了；-8 活动未结束（旧版回 -1）；-7 插入日志失败；-6 该奖励已经领取；-5 充值元宝不足；-4 获取奖励信息失败；
     * -3 天数错误；-2 参数错误（天数）；-1 活动未开启 / 未结束；1 成功；0 失败
     */
    public static function get_day_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $time = BossClock::now();
        $lockKey = self::LOCK_KEY . $uid;
        if ($uid <= 0 || !FestLock::acquire($lockKey)) {
            return ['status' => -9, 'msg' => self::LANG['request_fast_erro']];
        }
        try {
            $act = self::init($uid, $time);
            if ($act === null || $act['open'] !== 1) {
                return ['status' => -1, 'msg' => self::LANG['activities_unopened']];
            }
            if ($act['cd'] > 0) {
                return ['status' => -1, 'msg' => self::LANG['activities_unclosed']];
            }
            $day = (int)$ctx->input('day', 0);
            if ($day <= 0 || $day > self::DAY) {
                return ['status' => -2, 'msg' => self::LANG['passing_parameters_error']];
            }
            if ($day > $act['today']) {
                return ['status' => -3, 'msg' => self::LANG['day_error']];
            }
            $rewardInfo = $act['reward'];
            if ($rewardInfo === []) {
                return ['status' => -4, 'msg' => self::LANG['reward_table_error']];
            }
            if ($act['gold_num'] < $rewardInfo[0]['gold']) {
                return ['status' => -5, 'msg' => self::LANG['gold_error']];
            }
            // 判断获取第几档的奖励
            $type = self::tier($rewardInfo, $act['gold_num']);
            if ($type <= 0) {
                return ['status' => -5, 'msg' => self::LANG['gold_error']];
            }
            $logged = DB::table('actenrichment_getreward_log')->where('uid', $uid)->where('version', $act['version'])
                ->where('day', $day)->where('type', $type)->whereNull('deleted_at')->value('id');
            if ($logged !== null) {
                return ['status' => -6, 'msg' => self::LANG['already_get_raward']];
            }
            // 加log（同一天已领过其他档时唯一键冲突，回 -7）
            $added = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_actenrichment_getreward_log` (`uid`, `version`, `day`, `type`, `dateline`) VALUES (:uid, :version, :day, :type, :now)',
                ['uid' => $uid, 'version' => $act['version'], 'day' => $day, 'type' => $type, 'now' => $time]
            );
            if ($added <= 0) {
                return ['status' => -7, 'msg' => self::LANG['add_reward_log_error']];
            }
            // 发奖
            $row = $rewardInfo[$type - 1];
            $reward = ['type' => $row['day' . $day . '_type'], 'value' => $row['day' . $day . '_value']];
            if ($day === 7) {
                $reward['value1'] = $row['day7_value1'];
            }
            $result = self::getReward($uid, $reward, 0);
            if ($day === 7) {
                $reward['info'] = $result['info'] ?? null;
            }
            return [
                'status' => 1,
                'reward' => $reward,
                'gold' => (int)DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('gold'),
            ];
        } finally {
            FestLock::release($lockKey);
        }
    }

    /**
     * 貔貅聚财活动期间充值记账（actenrichment_model->add_enrichment_pay_order，pay_act_init：活动开启且离结束超过 7 天时，
     * 元宝数为商品元宝；由 Model\Pay::complete 在到账事务里调用）
     */
    public static function addPayOrder(int $uid, string $nickname, int $level, int $gold, string $orderId, string $class, string $method, int $now): void
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        // 挂7天图标
        if ($info === null || $now >= (int)$info['endtime'] - 7 * 86400) {
            return;
        }
        DB::table('actenrichment_order')->insert([
            'uid' => $uid,
            'nickname' => Member::logText($nickname, 50),
            'level' => $level,
            'gold' => $gold,
            'version' => (int)$info['version'],
            'orderid' => $orderId,
            'class' => Member::logText($class, 20),
            'method' => Member::logText($method, 20),
            'dateline' => $now,
        ]);
    }

    /**
     * 主城图标（member.php index）：结束日 0 点前 7 天起，累计充值达到第一档且 7 天中还有没领取的天数时为 1，否则 0；
     * 更早的时间返回 $current（活动开始结束判断的结果）
     */
    public static function iconStatus(int $uid, int $version, int $endtime, int $now, int $current): int
    {
        if ($now <= (int)strtotime(date('y-m-d', $endtime)) - 86400 * self::DAY) {
            return $current;
        }
        $allGold = self::orderGold($uid, $version);
        $rewardInfo = array_values(ActenrichmentReward::ROWS);
        if ($allGold === null || $allGold < ($rewardInfo[0]['gold'] ?? 0)) {
            return 0;
        }
        // 判断是否有未领取的奖励
        $logDays = self::logDays($uid, $version);
        $num = 0;
        for ($i = 1; $i <= self::DAY; $i++) {
            if (!isset($logDays[$i])) {
                $num++;
            }
        }
        return $num <= 0 ? 0 : $current;
    }

    /**
     * 计划任务：活动结束后发放未领取的奖励（cron_acttask.php:get_actenrichment_reward）
     *
     * 只在活动结束后 1 小时内执行：每个充值玩家按累计充值的最高档，补发 7 天中没有该档领取记录的天数（同类合并：元宝累加，
     * 将魂 / 装备取第 7 天的 value / value1），发信件。
     *
     * @return int -1 无活动；-2 活动未结束；-3 结束超过 1 小时；-4 奖励表为空；1 已执行
     */
    public static function cronReward(int $now): int
    {
        $activity = ActivityClock::byName(self::ACTIVITY);
        if ($activity === null) {
            return -1;
        }
        $version = (int)$activity['version'];
        $endTime = (int)$activity['endtime'];
        if ($endTime > $now) {
            return -2;
        }
        if ($endTime + 3600 < $now) {
            return -3;
        }
        $rewardList = array_values(ActenrichmentReward::ROWS);
        if ($rewardList === []) {
            return -4;
        }
        $payData = DB::query(
            'SELECT `uid`, SUM(`gold`) AS `all_gold` FROM `zc_sanguo_actenrichment_order` WHERE `version` = :version AND `deleted_at` IS NULL GROUP BY `uid`',
            ['version' => $version]
        );
        $addReward = [];
        foreach ($payData as $val) {
            $uid = (int)$val['uid'];
            // 判断玩家该领取哪儿个档次的奖励
            $type = self::tier($rewardList, (float)$val['all_gold']);
            if ($type <= 0) {
                continue;
            }
            $row = $rewardList[$type - 1];
            for ($day = 1; $day <= self::DAY; $day++) {
                // 添加奖励日志（该天已领过时唯一键冲突，不补发）
                $added = DB::execute(
                    'INSERT IGNORE INTO `zc_sanguo_actenrichment_getreward_log` (`uid`, `version`, `day`, `type`, `dateline`) VALUES (:uid, :version, :day, :type, :now)',
                    ['uid' => $uid, 'version' => $version, 'day' => $day, 'type' => $type, 'now' => $now]
                );
                if ($added <= 0) {
                    continue;
                }
                $rewardType = $row['day' . $day . '_type'];
                if (isset($addReward[$uid][$rewardType])) {
                    if (is_int($addReward[$uid][$rewardType])) {
                        $addReward[$uid][$rewardType] += $row['day' . $day . '_value'];
                    }
                } elseif ($day === 7) {
                    $addReward[$uid][$rewardType] = ['value' => $row['day7_value'], 'value1' => $row['day7_value1']];
                } else {
                    $addReward[$uid][$rewardType] = $row['day' . $day . '_value'];
                }
            }
        }
        foreach ($addReward as $uid => $val) {
            $message = self::LANG['cron_enrichment_end'] . self::LANG['cron_get_reward'];
            foreach ($val as $type => $value) {
                if ($type === 1) {
                    // 加元宝
                    $gold = is_array($value) ? (int)$value['value'] : $value;
                    Member::addCurrency($uid, gold: $gold);
                    $message .= self::LANG['cron_sycee'] . $gold . ';';
                } elseif ($type === 2 && is_array($value)) {
                    // 加将魂（add_hero_soul(id, value1, 1)：value1 为等级）
                    $soul = OwnSoul::addHeroSoul($uid, (int)$value['value'], (int)$value['value1'], 1);
                    $message .= self::LANG['cron_soul'] . ($soul['name'] ?? '') . ';';
                } elseif ($type === 3 && is_array($value)) {
                    // 添加装备
                    $item = FestReward::equipInfo((int)$value['value']);
                    if ($item !== null) {
                        Equipment::addFromTemplate($uid, (int)$value['value']);
                    }
                    $message .= self::LANG['cron_equip'] . ($item['name'] ?? '') . ';';
                }
            }
            $message .= self::LANG['cron_check'];
            MessageHandler::sendSystemMessage([$uid], 1, self::LANG['message_system_title'], $message);
        }
        return 1;
    }

    /**
     * 活动信息（_init）：活动开启时返回版本、倒计时（结束前 7 天为止）、累计充值、奖励表、领奖第几天与开启标识；活动未开启为 null
     *
     * 领奖期（cd <= 0）内没有充值记录或累计充值小于第一档时 open 为 0。
     *
     * @return array{version: int, cd: int, gold_num: int|float, reward: list<array<string, int>>, today: int, open: int}|null
     */
    private static function init(int $uid, int $now): ?array
    {
        $info = ActivityClock::open(self::ACTIVITY, $now);
        if ($info === null || $uid <= 0) {
            return null;
        }
        $version = (int)$info['version'];
        $end = (int)$info['endtime'];
        $cd = max(0, $end - $now - 86400 * self::DAY);
        $reward = array_values(ActenrichmentReward::ROWS);
        $orderGold = self::orderGold($uid, $version);
        $return = ['version' => $version, 'cd' => $cd, 'gold_num' => $orderGold ?? 0, 'reward' => $reward, 'today' => 0, 'open' => 1];
        if ($reward === []) {
            return $return;
        }
        // 活动结束 如果未充值 则提示活动未开启
        if ($cd <= 0) {
            // 判断当前是领奖的第几天
            $return['today'] = self::DAY - (int)floor(($end - $now) / 86400);
            if ($orderGold === null || $orderGold < $reward[0]['gold']) {
                $return['open'] = 0;
            }
        }
        return $return;
    }

    /**
     * 达到的最高档（1 起，0 为未达到）
     *
     * @param list<array<string, int>> $rewardInfo
     */
    private static function tier(array $rewardInfo, int|float $goldNum): int
    {
        $type = 0;
        foreach ($rewardInfo as $i => $row) {
            if ($goldNum >= $row['gold']) {
                $type = $i + 1;
            }
        }
        return $type;
    }

    /**
     * 本期累计充值元宝（get_order_info）；没有充值记录为 null
     */
    private static function orderGold(int $uid, int $version): int|float|null
    {
        $row = DB::query(
            'SELECT SUM(`gold`) AS `all_gold` FROM `zc_sanguo_actenrichment_order` WHERE `version` = :version AND `uid` = :uid AND `deleted_at` IS NULL',
            ['version' => $version, 'uid' => $uid]
        )[0] ?? null;
        if ($row === null || $row['all_gold'] === null) {
            return null;
        }
        $gold = (float)$row['all_gold'];
        return floor($gold) === $gold ? (int)$gold : $gold;
    }

    /**
     * 有领取记录的天数（get_reward_log($version, $day) 不限档次）：day => true
     *
     * @return array<int, bool>
     */
    private static function logDays(int $uid, int $version): array
    {
        $return = [];
        foreach (DB::table('actenrichment_getreward_log')->select('day')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->get() as $row) {
            $return[(int)$row['day']] = true;
        }
        return $return;
    }

    /**
     * 获取奖励信息或添加玩家奖励（get_reward；$isGet 0 添加奖励，1 只取信息；类型 1 元宝、2 将魂、3 装备）
     *
     * @param array<string, mixed> $rewardInfo type、value、value1
     * @return array<string, mixed>
     */
    private static function getReward(int $uid, array $rewardInfo, int $isGet): array
    {
        $type = (int)$rewardInfo['type'];
        $num = (int)$rewardInfo['value'];
        $return = ['type' => $type, 'value' => $num];
        $id = 0;
        // 装备、坐骑 单独处理
        if (in_array($type, [2, 3], true)) {
            $id = $num;
            $num = (int)($rewardInfo['value1'] ?? 0) > 0 ? (int)$rewardInfo['value1'] : 1;
            $return['value'] = $num;
        }
        switch ($type) {
            case 1: // 元宝
                Member::addCurrency($uid, gold: $num);
                break;
            case 2: // 将魂（add_hero_soul($id, $num)：$num 为等级）
                if ($isGet === 0) {
                    OwnSoul::addHeroSoul($uid, $id, $num);
                }
                $soul = Soul::ROWS[$id] ?? null;
                if ($soul !== null) {
                    // 查看等级
                    $attribute = OwnSoul::levelupRule($num, (int)$soul['type'], (int)$soul['quality']);
                    if ($attribute['status'] !== 0) {
                        $return['info'] = ($attribute['info'] ?? []) + [
                            'name' => $soul['name'],
                            'quality' => $soul['quality'],
                            'type' => $soul['type'],
                            'image' => $soul['type'] . $soul['quality'],
                            'power' => $attribute['info']['addition'] ?? null,
                        ];
                    }
                }
                break;
            case 3: // 装备
                $item = FestReward::equipInfo($id);
                if ($item !== null) {
                    if ($isGet === 0) {
                        Equipment::addFromTemplate($uid, $id, $num);
                    }
                    // 返回装备信息
                    $return['info'] = $item;
                }
                break;
        }
        return $return;
    }
}
