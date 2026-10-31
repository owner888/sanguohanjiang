<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Activity;

use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Handler\Pvp\CountryHandler;
use Hanjiang\MasterData\DiceRankReward;
use Hanjiang\MasterData\DiceReward;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Member;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 国家骰子（移植旧版 controllers/dice.php + models/dice_model.php）
 *
 * 客户端（countrydicepop.js）调用：index / shake_dice / get_last_week_reward / get_reward。国家 35 级以上开放。
 * 每周一期（第几周 = ceil((当前 - 1362326400) / 7 天)），记录 dice_log（uid + week 主键，同旧版）：每天第一次进入把剩余次数
 * 重置为 7，每次摇 3 个骰子累计点数；本国成员按点数排名（点数相同先到先排），排名奖励取 MasterData DiceRankReward（元宝），
 * 下周可领上周排名奖励；本周点数达到 MasterData DiceReward 的点数可领特别大奖（元宝）。离开 / 被踢出国家时点数清零
 * （clearDiceInfo，由 CountryHandler 调用）。当前时间取 BossClock（生产恒为 time()）。
 *
 * 照旧版保留的写法：index 判断大奖可领用 >=，shake_dice 用 >（刚好达到点数时摇完骰子显示不可领）；排名超过 50 名的
 * reward 为 null；get_reward 的 id 不是 1 / 2 时按大奖不存在（-6）。
 * 与旧版的差别：shake_dice / get_last_week_reward / get_reward 在 Member::locked（玩家行锁）里执行；get_last_week_reward
 * 改领奖状态失败时回 -7 后结束（旧版回 -7 后不 return，接着发奖励并再输出一次 JSON）。
 * 计划任务 cron::send_dice_reward（修复领奖状态，旧版开头即 exit）不移植。
 */
final class DiceHandler
{
    /** 每日摇骰子次数 */
    private const int TIMES = 7;
    /** 国家骰子开启等级（国家等级） */
    private const int OPEN_LEVEL = 35;
    /** 计算第几周的起点 */
    private const int START = 1362326400;

    /**
     * 初始化: c=dice&m=index
     */
    public static function index(Context $ctx): array
    {
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $now = BossClock::now();
        $gate = self::gate($uid);
        if (is_int($gate)) {
            $return['status'] = $gate;
            return $return;
        }
        $week = self::week($now);
        // 获取用户信息
        $member = self::memberInfo($gate, $week, $now);
        // 获取大奖信息，判断领取状态
        $reward = self::bigReward($member, false);
        // 获取国家成员列表与参加活动的人
        $uids = self::countryUids((int)$gate['country']);
        $list = self::rankList($week, $uids);
        // 获取上周活动信息
        $lastWeek = self::lastWeekInfo($uid, $week - 1, $uids);
        if ($lastWeek['status'] === 1) {
            $lastWeek['reward'] = DiceRankReward::ROWS[$lastWeek['now_rank']]['reward'] ?? null;
        }
        $return['status'] = 1;
        $return['last_week'] = $lastWeek;
        $return['list'] = $list;
        $return['reward'] = $reward;
        $return['member'] = $member;
        return $return;
    }

    /**
     * 摇骰子: c=dice&m=shake_dice
     */
    public static function shake_dice(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return Member::locked($uid, static function () use ($uid): array {
            $return = ['status' => 0];
            $now = BossClock::now();
            $gate = self::gate($uid);
            if (is_int($gate)) {
                $return['status'] = $gate;
                return $return;
            }
            $week = self::week($now);
            $member = self::memberInfo($gate, $week, $now);
            // 判断还有没有次数
            if ($member['times'] === 0) {
                $return['status'] = -5;
                return $return;
            }
            $data = ['times' => $member['times'] - 1];
            // 开始摇
            $points = 0;
            for ($i = 1; $i <= 3; $i++) {
                $rands = mt_rand(1, 6);
                $points += $rands;
                $data['dice' . $i] = $rands;
            }
            // 修改用户数据
            $data['points'] = $member['points'] + $points;
            self::updateMemberInfo($uid, $week, $data, $now);
            foreach ($data as $key => $val) {
                $member[$key] = $val;
            }
            // 获取大奖信息（摇完后判断用 >）
            $reward = self::bigReward($member, true);
            $list = self::rankList($week, self::countryUids((int)$gate['country']));
            $return['status'] = 1;
            $return['member'] = $member;
            $return['reward'] = $reward;
            $return['list'] = $list;
            return $return;
        });
    }

    /**
     * 领取上周的排名奖励: c=dice&m=get_last_week_reward
     */
    public static function get_last_week_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        return Member::locked($uid, static function () use ($uid): array {
            $return = ['status' => 0];
            $now = BossClock::now();
            $gate = self::gate($uid);
            if (is_int($gate)) {
                $return['status'] = $gate;
                return $return;
            }
            $week = self::week($now);
            // 获取上周活动信息
            $member = self::lastWeekInfo($uid, $week - 1, self::countryUids((int)$gate['country']));
            if ($member['status'] !== 1) {
                $return['status'] = -4;
                return $return;
            }
            // 判断是否已经领取过
            if ($member['get_rank'] > 0) {
                $return['status'] = -5;
                return $return;
            }
            // 获取该排名的奖励
            $reward = DiceRankReward::ROWS[$member['now_rank']] ?? null;
            if ($reward === null) {
                $return['status'] = -6;
                return $return;
            }
            // 修改用户领取奖励状态
            if (self::updateMemberInfo($uid, $week - 1, ['get_rank' => $member['now_rank']], $now) !== 1) {
                $return['status'] = -7;
                return $return;
            }
            // 添加奖励
            Member::addCurrency($uid, gold: $reward['reward']);
            $return['status'] = 1;
            $return['get'] = $reward['reward'];
            $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
            return $return;
        });
    }

    /**
     * 领取额外奖励（特别大奖）: c=dice&m=get_reward&id=
     */
    public static function get_reward(Context $ctx): array
    {
        $uid = MemberHandler::resolveUid($ctx);
        $id = (int)$ctx->input('id', 0);
        return Member::locked($uid, static function () use ($uid, $id): array {
            $return = ['status' => 0];
            // 要领取的奖励id
            if (!$id) {
                $return['status'] = -9;
                return $return;
            }
            $now = BossClock::now();
            $gate = self::gate($uid);
            if (is_int($gate)) {
                $return['status'] = $gate;
                return $return;
            }
            $week = self::week($now);
            $member = self::memberInfo($gate, $week, $now);
            // 判断奖励是否已经领取（旧版 reward{id}_status 只有 1、2 两列）
            if (($member['reward' . $id . '_status'] ?? 0) > 0) {
                $return['status'] = -5;
                return $return;
            }
            // 获取该奖励信息
            $reward = DiceReward::ROWS[$id] ?? null;
            if ($reward === null || !in_array($id, [1, 2], true)) {
                $return['status'] = -6;
                return $return;
            }
            // 判断是否达到领奖资格
            if ($member['points'] < $reward['points']) {
                $return['status'] = -7;
                return $return;
            }
            // 修改领奖记录
            if (self::updateMemberInfo($uid, $week, ['reward' . $id . '_status' => 1], $now) !== 1) {
                $return['status'] = -8;
                return $return;
            }
            // 添加奖励
            Member::addCurrency($uid, gold: $reward['reward']);
            $return['status'] = 1;
            $return['add'] = $reward['reward'];
            $return['all_gold'] = (int)DB::table('member')->where('uid', $uid)->value('gold');
            return $return;
        });
    }

    /**
     * 离开国家，清除用户点数（dice_model->clear_dice_info：该玩家所有周的点数清零）
     */
    public static function clearDiceInfo(int $uid): int
    {
        if (!$uid) {
            return 0;
        }
        DB::table('dice_log')->where('uid', $uid)->whereNull('deleted_at')->update(['points' => 0]);
        return 1;
    }

    /**
     * 国家与国家等级检查：-1 未加入国家，-2 国家不存在，-3 国家等级不足；通过时返回玩家（uid / nickname / country）
     *
     * @return array<string, mixed>|int
     */
    private static function gate(int $uid): array|int
    {
        $member = DB::table('member')->select('uid', 'nickname', 'country')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($member === null || (int)$member['country'] === 0) {
            return -1;
        }
        $country = CountryHandler::countryInfo((int)$member['country']);
        if ($country === null) {
            return -2;
        }
        if ((int)$country['level'] < self::OPEN_LEVEL) {
            return -3;
        }
        return $member;
    }

    private static function week(int $now): int
    {
        return (int)ceil(($now - self::START) / (86400 * 7));
    }

    /**
     * 本周用户信息（get_member_info：没有记录时建档；不是今天更新的把次数重置为 7）
     *
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    private static function memberInfo(array $member, int $week, int $now): array
    {
        $uid = (int)$member['uid'];
        $info = self::row($uid, $week);
        if ($info !== null) {
            // 判断是否需要更新次数
            if (date('ymd', $info['dateline']) !== date('ymd', $now)) {
                $affected = DB::table('dice_log')->where('uid', $uid)->where('week', $week)->whereNull('deleted_at')
                    ->update(['times' => self::TIMES, 'dateline' => $now]);
                if ($affected > 0) {
                    $info['times'] = self::TIMES;
                    $info['dateline'] = $now;
                }
            }
            return $info;
        }
        // 添加数据
        $info = [
            'week' => $week,
            'uid' => $uid,
            'nickname' => (string)$member['nickname'],
            'points' => 0,
            'dice1' => 1,
            'dice2' => 1,
            'dice3' => 1,
            'times' => self::TIMES,
            'dateline' => $now,
        ];
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_dice_log` (`week`, `uid`, `nickname`, `points`, `dice1`, `dice2`, `dice3`, `times`, `dateline`)
             VALUES (:week, :uid, :nickname, 0, 1, 1, 1, :times, :now)',
            ['week' => $week, 'uid' => $uid, 'nickname' => $info['nickname'], 'times' => self::TIMES, 'now' => $now]
        );
        return $info;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function row(int $uid, int $week): ?array
    {
        $row = DB::table('dice_log')->select(...self::COLUMNS)->where('uid', $uid)->where('week', $week)->whereNull('deleted_at')->first();
        return $row === null ? null : self::cast($row);
    }

    /** dice_log 的旧版列 */
    private const array COLUMNS = [
        'uid', 'week', 'nickname', 'points', 'times', 'get_rank', 'dice1', 'dice2', 'dice3', 'reward1_status', 'reward2_status', 'dateline',
    ];

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function cast(array $row): array
    {
        foreach ($row as $key => $value) {
            $row[$key] = $key === 'nickname' ? (string)$value : (int)$value;
        }
        return $row;
    }

    /**
     * 修改用户数据（update_member_info：同时写 dateline）
     *
     * @param array<string, int> $data
     * @return int 1 有修改，0 没有
     */
    private static function updateMemberInfo(int $uid, int $week, array $data, int $now): int
    {
        if ($data === []) {
            return 0;
        }
        $data['dateline'] = $now;
        $affected = DB::table('dice_log')->where('uid', $uid)->where('week', $week)->whereNull('deleted_at')->update($data);
        return $affected > 0 ? 1 : 0;
    }

    /**
     * 大奖列表与领取状态（status 0 不可领、1 可领取、2 已领取；第 i 行对应 reward{i}_status）
     *
     * @param array<string, mixed> $member
     * @return list<array<string, mixed>>
     */
    private static function bigReward(array $member, bool $strict): array
    {
        $reward = array_values(DiceReward::ROWS);
        $i = 1;
        foreach ($reward as $key => $val) {
            $reached = $strict ? $member['points'] > $val['points'] : $member['points'] >= $val['points'];
            if ($reached) {
                $reward[$key]['status'] = ($member['reward' . $i . '_status'] ?? 0) == 1 ? 2 : 1;
            } else {
                $reward[$key]['status'] = 0;
            }
            $i++;
        }
        return $reward;
    }

    /**
     * 本国成员 uid（country_model->get_member_list($country, 1, 0)）
     *
     * @return list<int>
     */
    private static function countryUids(int $country): array
    {
        return array_map(static fn (array $v): int => (int)$v['uid'], CountryHandler::memberList($country, 1, 0));
    }

    /**
     * 参加活动的本国成员排名（get_member_list：点数 > 0，点数降序、时间升序），带名次与排名奖励
     *
     * @param list<int> $uids
     * @return list<array<string, mixed>>
     */
    private static function rankList(int $week, array $uids): array
    {
        if ($uids === []) {
            return [];
        }
        $rows = DB::table('dice_log')->select(...self::COLUMNS)->where('week', $week)->where('points', '>', 0)->whereIn('uid', $uids)
            ->whereNull('deleted_at')->orderBy('points', 'DESC')->orderBy('dateline', 'ASC')->get();
        $list = [];
        $rank = 1;
        foreach ($rows as $row) {
            $row = self::cast($row);
            $row['rank'] = $rank;
            $row['reward'] = DiceRankReward::ROWS[$rank]['reward'] ?? null;
            unset($row['times'], $row['get_rank'], $row['reward1_status'], $row['reward2_status'], $row['dateline'], $row['week']);
            $list[] = $row;
            $rank++;
        }
        return $list;
    }

    /**
     * 上周的活动信息（get_last_week_info：没参加或 0 点为 -1；now_rank 为本国点数比自己高的人数 + 1）
     *
     * @param list<int> $uids
     * @return array<string, mixed>
     */
    private static function lastWeekInfo(int $uid, int $week, array $uids): array
    {
        $member = self::row($uid, $week);
        if ($member === null || $member['points'] === 0) {
            return ['status' => -1];
        }
        // 查询排名
        $num = $uids === [] ? 0 : DB::table('dice_log')->where('week', $week)->whereIn('uid', $uids)
            ->where('points', '>', $member['points'])->whereNull('deleted_at')->count();
        $member['now_rank'] = $num + 1;
        $member['status'] = 1;
        return $member;
    }
}
