<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Cron\BusinessCron;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\Pvp\SiegeHandler;
use Hanjiang\Model\Chat;
use Hanjiang\Model\DailyTask;
use Hanjiang\Model\Expostulation;
use Hanjiang\Model\MainQuestTask;
use Hanjiang\Model\Member;
use Hanjiang\Model\MemberActivity;
use Hanjiang\Model\StatPay;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 每日通商（移植旧版 controllers/business.php + models/business_model.php）
 *
 * 客户端（dailytradepop.js）调用：index / go_business(id) / buy / refresh。主公 61 级开启（旧版判断 level > 60，status 2）。
 * 每天三条通商任务（初级 / 中级 / 高级，星级 2~7，group 为星级个商人 a~l 的组合；取数见 Cron\BusinessCron::tasks）。
 * 每人每天的通商日志 business_log.log 记最近 7 个通商的商人；最近通商的商人连起来与某任务的组合一致即完成该任务，
 * 得 250 × 主公等级银币外另加星级奖励（get_reward_by_star：2~7 星 × 500 / 1000 / 2000 / 3000 / 4000 / 5000 × 主公等级）。
 * 每次通商从 3 个随机商人（Redis business_{uid}，刷新 5 元宝）里选一个；次数 = 免费 member_activity.freebusiness（每日 20，
 * 都城效果 type 2 加次数）+ 购买的 businesstimes（8 元宝一次）。完成 6~7 星任务发小喇叭。
 * 通商计日常任务 4、谏言任务 6（有国家时）、主线任务 17 与 18（通商星级）。
 *
 * 与旧版的差别：go_business / buy / refresh 在 Member::locked（玩家行锁）里执行，次数用条件 UPDATE 扣减；buy 先扣元宝再加次数
 * （旧版先加次数后扣元宝）。旧版 business_{ymd} 任务缓存不再使用（任务取 MasterData / business 表）；运维口令接口 clear 不移植。
 */
final class BusinessHandler
{
    /** 开启等级（旧版 level > 60） */
    private const int OPEN_LEVEL = 60;
    /** 商人缓存键（旧版 $_key，后接 uid） */
    private const string KEY = 'business_';
    /** 购买通商次数价格（$_business_price） */
    private const int BUSINESS_PRICE = 8;
    /** 刷新商人价格（$_refresh） */
    private const int REFRESH = 5;
    /** 日志最多记几个商人 */
    private const int LOG_LENGTH = 7;
    /** 商人列表（business_lang） */
    private const array TRADER = [
        1 => '楼兰商人',
        2 => '西域商人',
        3 => '巴蜀商人',
        4 => '大理商人',
        5 => '闽南商人',
        6 => '辽东商人',
        7 => '关东商人',
        8 => '淮南商人',
        9 => '荆楚商人',
        10 => '南越商人',
        11 => '浔阳商人',
        12 => '岭南商人',
    ];
    /** 小喇叭（business_accomplish + base_silver） */
    private const string LANG_ACCOMPLISH = '玩家%s达成%s星幸运通商，获得';
    private const string LANG_SILVER = '银币';

    /**
     * 初始化接口: c=business&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         * -4 获取商人列表失败
         * -3 获取通商记录失败
         * -2 获取通商列表失败
         * -1 获取活动信息失败
         *  0 失败
         *  1 成功
         *  2 等级不足
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $member = self::member($uid);
        if ($member === null) {
            // 等级不足
            $return['status'] = 2;
            return $return;
        }
        $now = time();
        // 取国家占领次数加成（都城效果 type 2 加免费通商次数）
        SiegeHandler::getCityEffect($member, $now);
        $activity = self::activityInfo($uid);
        if ($activity === null) {
            // 获取活动信息失败
            $return['status'] = -1;
            return $return;
        }
        // 取得活动剩余次数
        $times = $activity['freebusiness'] + $activity['businesstimes'];
        // 获取每日通商任务
        $list = BusinessCron::tasks((int)date('Ymd', $now));
        if ($list === []) {
            // 获取通商列表失败
            $return['status'] = -2;
            return $return;
        }
        // 获取每日通商log（比较最后通商时间是否是当日,不是则清空log）
        $log = self::todayLog($uid, $now);
        // 比较任务和log
        $result = self::contrastList($log['log'], $list);
        // 获取随机商人
        $trader = self::traderList($uid, $now);

        $return['status'] = 1;
        $return['times'] = $times;
        $return['list'] = $result;
        $return['trader'] = $trader;
        $return['log'] = $log['list'];
        $return['refresh'] = self::REFRESH;
        return $return;
    }

    /**
     * 通商: c=business&m=go_business&id=商人id
     */
    public static function go_business(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        return Member::locked($uid, static function () use ($uid, $id): array {
            /**
             * -6 获取商人列表失败
             * -5 加银币失败
             * -4 更新日志失败
             * -3 获取通商列表失败
             * -2 获取活动信息失败
             * -1 商人不存在
             *  0 失败
             *  1 成功
             *  2 等级不足
             *  3 次数不足
             */
            $return = ['status' => 0];
            $now = time();
            $member = self::member($uid);
            if ($member === null) {
                // 等级不足
                $return['status'] = 2;
                return $return;
            }
            $idarr = array_column(self::traderList($uid, $now), 'id');
            if (!in_array($id, $idarr, true)) {
                // 商人不存在
                $return['status'] = -1;
                return $return;
            }
            $activity = self::activityInfo($uid);
            if ($activity === null) {
                // 获取活动信息失败
                $return['status'] = -2;
                return $return;
            }
            // 取得活动剩余次数
            $times = $activity['freebusiness'] + $activity['businesstimes'];
            if ($times < 1) {
                // 次数不足
                $return['status'] = 3;
                return $return;
            }
            $info = BusinessCron::tasks((int)date('Ymd', $now));
            if ($info === []) {
                // 获取每日通商列表失败
                $return['status'] = -3;
                return $return;
            }
            // 更新通商日志
            $log = self::getLog($uid);
            // 比较最后通商时间是否是当日,不是则清空log（旧版只清 log，不清 list）
            if ((int)date('Ymd', $now) > (int)date('Ymd', $log['dateline'])) {
                $log['log'] = '';
            }
            // 将数字转换为字母
            $char = chr(96 + $id);
            if ($log['list'] === []) {
                $newlog = $char;
            } else {
                $newlog = $log['log'] . $char;
                if (strlen($newlog) > self::LOG_LENGTH) {
                    $newlog = substr($newlog, 1);
                }
            }
            $logarr = self::traderById(self::logIds($newlog));
            $temp = $newlog;
            $lastsite = $log['lastsite'];
            if ($lastsite > 0) {
                $lastsite = $lastsite > 6 ? $lastsite - 1 : 0;
                $temp = substr($newlog, $lastsite);
            }
            $result = [];
            foreach ($info as $value) {
                // 上次完成的星级只和上次奖励之后的商人比较（同一组商人不重复奖励）
                $result[] = self::contrast($value['star'] === $log['laststar'] ? $temp : $newlog, $value['group']);
            }
            // 获取通商奖励
            $level = (int)$member['level'];
            $reward = 250 * $level;
            $addition = $star = 0;
            foreach ($result as $key => $value) {
                if ($value['finish'] === 1) {
                    // 只取最大收益
                    $addition = self::rewardByStar($value['star'], $level);
                    $star = $value['star'];
                    foreach (array_keys($value['info']) as $v) {
                        $value['info'][$v]['finish'] = 0;
                    }
                }
                $result[$key] = $value['info'];
            }
            $data = ['log' => $newlog, 'lastsite' => $lastsite, 'dateline' => $now];
            if ($addition > 0) {
                $data['laststar'] = $star;
                $data['lastsite'] = strlen($newlog);
            }
            if (DB::table('business_log')->where('uid', $uid)->whereNull('deleted_at')->update($data) !== 1) {
                // 更新失败
                $return['status'] = -4;
                return $return;
            }
            // 剩余次数-1（先扣免费次数）
            $column = $activity['freebusiness'] > 0 ? 'freebusiness' : 'businesstimes';
            $sub = DB::execute(
                "UPDATE `zc_sanguo_member_activity` SET `{$column}` = `{$column}` - 1 WHERE `uid` = :uid AND `{$column}` > 0 AND `deleted_at` IS NULL",
                ['uid' => $uid]
            );
            if ($sub === 0) {
                $return['status'] = 3;
                return $return;
            }
            $reward += $addition;
            Member::addCurrency($uid, silver: $reward);
            // 完成6~7星任务时广播
            if ($star > 5) {
                Chat::sendToHorn(sprintf(self::LANG_ACCOMPLISH, $member['nickname'], $star) . self::LANG_SILVER . $addition, Chat::sender($uid));
            }
            // 刷新商人列表
            Redis::del(self::KEY . $uid);
            $trader = self::traderList($uid, $now);
            $times -= 1;
            $complete = 0;
            if ($star > 0) {
                $complete = 1;
            }
            if ($star > 5) {
                $complete = 2;
            }
            $return['status'] = 1;
            $return['times'] = $times;
            $return['complete'] = $complete;
            $return['reward_silver'] = $reward - $addition;
            $return['addition'] = $addition;
            $return['silver'] = (int)DB::table('member')->where('uid', $uid)->value('silver');
            $return['list'] = $result;
            $return['trader'] = $trader;
            $return['log'] = $logarr;
            $return['treasuremap'] = [];
            // 日常任务(4是通商对应的类型)
            DailyTask::action($uid, DailyTask::TYPE_BUSINESS);
            // 帮派任务(谏言：6是通商对应的类型)
            if ((int)$member['country']) {
                Expostulation::action((int)$member['country'], 6);
            }
            // 主线任务 17完成一次每日通商；18完成通商x星级
            MainQuestTask::action($uid, 17, 1);
            MainQuestTask::action($uid, 18, $star);
            return $return;
        });
    }

    /**
     * 购买次数: c=business&m=buy
     */
    public static function buy(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return Member::locked($uid, static function () use ($uid): array {
            /**
             * -3 扣金币失败
             * -2 购买失败
             * -1 获取活动信息失败
             *  0 失败
             *  1 成功
             *  2 等级不足
             *  3 金币不足
             */
            $return = ['status' => 0];
            $member = self::member($uid);
            if ($member === null) {
                // 等级不足
                $return['status'] = 2;
                return $return;
            }
            if ((int)$member['gold'] < self::BUSINESS_PRICE) {
                // 金币不足
                $return['status'] = 3;
                return $return;
            }
            $activity = self::activityInfo($uid);
            if ($activity === null) {
                // 获取活动信息失败
                $return['status'] = -1;
                return $return;
            }
            // 扣除8金币
            if (!Member::deductCurrency($uid, gold: self::BUSINESS_PRICE)) {
                $return['status'] = 3;
                return $return;
            }
            if (DB::table('member_activity')->where('uid', $uid)->whereNull('deleted_at')->increment('businesstimes', 1) <= 0) {
                // 购买失败
                $return['status'] = -2;
                return $return;
            }
            $return['status'] = 1;
            // 取得活动剩余次数
            $return['times'] = $activity['freebusiness'] + $activity['businesstimes'] + 1;
            $return['gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
            // 消费统计记录
            StatPay::record($uid, 'business_buy', self::BUSINESS_PRICE);
            return $return;
        });
    }

    /**
     * 刷新商人: c=business&m=refresh
     */
    public static function refresh(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return Member::locked($uid, static function () use ($uid): array {
            /**
             * -2 获取商人列表失败
             * -1 扣金币失败
             *  0 失败
             *  1 成功
             *  2 等级不足
             *  3 金币不足
             */
            $return = ['status' => 0];
            if (self::member($uid) === null) {
                // 等级不足
                $return['status'] = 2;
                return $return;
            }
            // 扣除5金币
            if (!Member::deductCurrency($uid, gold: self::REFRESH)) {
                // 金币不足
                $return['status'] = 3;
                return $return;
            }
            // 刷新商人列表
            Redis::del(self::KEY . $uid);
            $return['status'] = 1;
            $return['trader'] = self::traderList($uid, time());
            $return['gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
            // 消费统计记录
            StatPay::record($uid, 'business_refresh', self::REFRESH);
            return $return;
        });
    }

    /**
     * 获取随机商人（_get_trader_list：3 个，缓存 1 天；生成时标出各任务下一个要找的商人 start / go / class）
     *
     * @return list<array<string, mixed>>
     */
    private static function traderList(int $uid, int $now): array
    {
        $cache = Redis::get(self::KEY . $uid);
        if (is_array($cache)) {
            return $cache;
        }
        $return = self::traderById([mt_rand(1, 12), mt_rand(1, 12), mt_rand(1, 12)]);
        // 商人组合提示
        $list = BusinessCron::tasks((int)date('Ymd', $now));
        $log = self::todayLog($uid, $now);
        $result = self::contrastList($log['log'], $list);
        foreach ($return as $key => $value) {
            $return[$key]['start'] = $return[$key]['go'] = $return[$key]['class'] = 0;
            foreach ($result as $k => $v) {
                foreach ($v as $p => $trader) {
                    if ($trader['finish'] === 1) {
                        continue;
                    }
                    if ($value['id'] === $trader['id']) {
                        $return[$key]['class'] = $k + 1;
                        if ($p === 0) {
                            $return[$key]['start'] = 1;
                        } else {
                            $return[$key]['go'] = 1;
                        }
                    }
                    break;
                }
            }
        }
        Redis::set(self::KEY . $uid, $return, 86400);
        return $return;
    }

    /**
     * 每日通商日志（get_log：没有时建档；list 为日志里商人的名称）
     *
     * @return array{log: string, laststar: int, lastsite: int, dateline: int, list: list<array{id: int, name: string}>}
     */
    private static function getLog(int $uid): array
    {
        $row = DB::table('business_log')->select('log', 'laststar', 'lastsite', 'dateline')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row === null) {
            DB::execute('INSERT IGNORE INTO `zc_sanguo_business_log` (`uid`, `log`) VALUES (:uid, \'\')', ['uid' => $uid]);
            return ['log' => '', 'laststar' => 0, 'lastsite' => 0, 'dateline' => 0, 'list' => []];
        }
        $log = (string)$row['log'];
        return [
            'log' => $log,
            'laststar' => (int)$row['laststar'],
            'lastsite' => (int)$row['lastsite'],
            'dateline' => (int)$row['dateline'],
            'list' => self::traderById(self::logIds($log)),
        ];
    }

    /**
     * 今天的通商日志（比较最后通商时间是否是当日，不是则清空 log 与 list 并记当前时间）
     *
     * @return array{log: string, laststar: int, lastsite: int, dateline: int, list: list<array{id: int, name: string}>}
     */
    private static function todayLog(int $uid, int $now): array
    {
        $log = self::getLog($uid);
        if ((int)date('Ymd', $now) > (int)date('Ymd', $log['dateline'])) {
            $log['log'] = '';
            $log['list'] = [];
            DB::table('business_log')->where('uid', $uid)->whereNull('deleted_at')->update(['log' => '', 'dateline' => $now]);
        }
        return $log;
    }

    /**
     * 比较日志与各任务（_contrast：已完成的任务显示为全部未完成）
     *
     * @param list<array<string, mixed>> $list
     * @return list<list<array{id: int, name: string, finish: int}>>
     */
    private static function contrastList(string $log, array $list): array
    {
        $return = [];
        foreach ($list as $value) {
            $info = self::contrast($log, (string)$value['group']);
            if ($info['finish'] === 1) {
                foreach (array_keys($info['info']) as $v) {
                    $info['info'][$v]['finish'] = 0;
                }
            }
            $return[] = $info['info'];
        }
        return $return;
    }

    /**
     * 对比玩家通商日志与每日通商任务（contrast：日志末尾与任务开头最长的相同部分为已完成的商人，全部相同即完成）
     *
     * @return array{finish: int, star: int, info: list<array{id: int, name: string, finish: int}>}
     */
    private static function contrast(string $log, string $group): array
    {
        $length = strlen($log);
        $star = strlen($group);
        $list = self::traderById(self::logIds($group));
        $pos = 0;
        if ($length >= 1) {
            $pos = min($length, $star);
            while ($pos > 0) {
                if (strncmp($group, substr($log, -$pos), $pos) === 0) {
                    break;
                }
                --$pos;
            }
        }
        $finish = $length >= 1 && $pos === $star ? 1 : 0;
        $info = [];
        foreach ($list as $v) {
            $info[] = ['id' => $v['id'], 'name' => $v['name'], 'finish' => $pos > 0 ? 1 : 0];
            if ($pos > 0) {
                --$pos;
            }
        }
        return ['finish' => $finish, 'star' => $star, 'info' => $info];
    }

    /**
     * 根据 id 获取商人名称（get_trader_by_id）
     *
     * @param list<int> $idarr
     * @return list<array{id: int, name: string|null}>
     */
    private static function traderById(array $idarr): array
    {
        return array_map(static fn(int $v): array => ['id' => $v, 'name' => self::TRADER[$v] ?? null], $idarr);
    }

    /**
     * 字母 a~l 转商人 id 1~12
     *
     * @return list<int>
     */
    private static function logIds(string $log): array
    {
        $idarr = [];
        for ($i = 0, $size = strlen($log); $i < $size; $i++) {
            $idarr[] = ord($log[$i]) - 96;
        }
        return $idarr;
    }

    /**
     * 获取通商奖励银币（get_reward_by_star）
     */
    private static function rewardByStar(int $star, int $level): int
    {
        return match ($star) {
            2 => $level * 500,
            3 => $level * 1000,
            4 => $level * 2000,
            5 => $level * 3000,
            6 => $level * 4000,
            7 => $level * 5000,
            default => 0,
        };
    }

    /**
     * 玩家活动信息（get_activity_info：没有时建档）
     *
     * @return array{freebusiness: int, businesstimes: int}|null
     */
    private static function activityInfo(int $uid): ?array
    {
        MemberActivity::ensure($uid);
        return MemberActivity::many([$uid], ['freebusiness', 'businesstimes'])[$uid] ?? null;
    }

    /**
     * 当前玩家；主公不足 61 级（功能未开启）为 null
     *
     * @return array<string, mixed>|null
     */
    private static function member(int $uid): ?array
    {
        $row = DB::table('member')->select('uid', 'nickname', 'level', 'gold', 'silver', 'country')->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null || (int)$row['level'] <= self::OPEN_LEVEL ? null : $row;
    }
}
