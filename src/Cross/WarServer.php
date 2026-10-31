<?php

declare(strict_types=1);

namespace Hanjiang\Cross;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\Model\BossClock;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 武斗会的服务器端（s1 transfer_model 的 war_* / get_point / update_point 请求 war_url 上的 c=war）
 *
 * 旧版 war 服务器的 war 控制器与 model 都不在旧版代码包里（war 包里只有 uc 控制器的副本），整个服务器端按 s1 调用方
 * （controllers/war.php、member.php 的武斗会报名状态、models/war_model.php）、客户端（warworld.js、warheroworld.js、
 * scoreshoppop.js、miscpop.js、tmpl.html 的积分说明、language.js）与 battle 库表结构（war_entry、war_member、
 * war_member_info、war_palace、war_report、war_round、war_support）还原（按控制器与表结构还原（旧版 model 不在代码包里））。
 * 单服部署下在本进程内，s1 的 WarHandler 直接调用这里的方法，返回值即旧版 api_return 的数组；表名前缀 zc_sanguo_battle_。
 *
 * 还原的赛制（推定的部分都在这里，客户端 / s1 看得到的字段与状态码照旧版）：
 * - 期数为自然月（Ym）。每月 19 日 0 点到 26 日 18 点报名（member.php _war_entry_status 的报名时段），报名等级 LIMIT_LEVEL；
 *   报名结束到 19 点为分组等待（stage 5），报名结束后 1 分钟内同步全部报名玩家的队伍。
 * - 第 1 ~ 4 天（26 日起每天 19 点开打）依次为预赛、初赛、复赛、决赛（s1 lang war_round_day1 ~ 4）。
 *   预赛到复赛：每天 ROUNDS 轮，每轮 BATTLE_INTERVAL 秒、一局定胜负，按当天胜场排序后相邻配对（单数时最后一人轮空算胜）；
 *   当天打完按当天胜场、本期积分、报名先后排名，前 ADVANCE 名晋级下一天（客户端「前{2}名晋级初赛 / 复赛 / 决赛」），
 *   其余出局（goout 记 OUT）。决赛为 32 强淘汰赛：随机排开（不足的右方轮空），每轮三局两胜、每局 BATTLE_INTERVAL 秒，
 *   之后相邻两场的胜者配对，同战神殿；决赛打完前四名进入 war_palace（site 1 冠军、2 亚军、3 / 4 四强）。
 * - 每局开打前 1 分钟由计划任务算出战斗；支持（观战玩家每轮随机关注一场）在开打前 5 分钟截止（客户端 303 秒禁用按钮），
 *   决赛圈只能在第一局支持；支持数多的一方先手，第二局换先手（同战神殿）；显示的支持数按战神殿的公式放大。
 * - 每场的胜者得积分 integral、支持胜方的玩家得 support（REWARD，数值取客户端 tmpl.html 的积分说明：battle 库 war_reward
 *   在旧版数据里 0 行），积分同时记入本期报名行与积分商城用的 war_member_info（跨期累计）。轮空也算胜。
 * - 参赛奖励 / 支持奖励列表（get_reward_list / get_reward）：武斗会的奖励就是积分，没有物品奖励，一律回 -1。
 * - 武神榜（get_rank_list）为本期 32 强；斗士堂（get_palace）为各期四强。
 */
final class WarServer
{
    /** 报名等级限制（推定：s1 80 级以下不能报名） */
    public const int LIMIT_LEVEL = 80;

    /** 预赛 / 初赛 / 复赛每天的轮数（推定） */
    public const int ROUNDS = 5;

    /** 每局间隔（秒，推定；客户端开打前 303 秒禁用支持按钮，间隔须大于 5 分钟） */
    public const int BATTLE_INTERVAL = 600;

    /** 开打前多少秒截止支持 */
    public const int SUPPORT_CLOSE = 300;

    /** 用户同步数据的最小间隔(秒)（客户端同步按钮冷却 10 秒） */
    private const int DELAY = 10;

    /** 每天晋级人数（第 1 天晋级初赛、第 2 天晋级复赛、第 3 天晋级决赛 32 强） */
    public const array ADVANCE = [1 => 128, 2 => 64, 3 => 32];

    /** 预赛 / 初赛 / 复赛未晋级的出局标记（goout；大于 32 的排名不显示） */
    public const array OUT = [1 => 256, 2 => 128, 3 => 64];

    /** 最近报名的玩家显示人数 */
    private const int ENTRY_SHOW = 4;

    /** 武神榜：32 强（决赛圈出局轮次 16 以内） */
    private const int RANK_GOOUT = 16;

    /**
     * 每场胜利的积分（integral：参赛者；support：支持胜方的玩家），取客户端积分说明：预赛 35 / 3、初赛 60 / 6、复赛 90 / 9 每场，
     * 十六强赛 1150 / 120、八强赛 1400 / 150、四强赛 1700 / 200、半决赛 2300 / 270、决赛 3000 / 350
     */
    public const array REWARD = [
        'day' => [
            1 => ['id' => 1, 'name' => '预赛', 'integral' => 35, 'support' => 3],
            2 => ['id' => 2, 'name' => '初赛', 'integral' => 60, 'support' => 6],
            3 => ['id' => 3, 'name' => '复赛', 'integral' => 90, 'support' => 9],
        ],
        'round' => [
            16 => ['id' => 4, 'name' => '十六强赛', 'integral' => 1150, 'support' => 120],
            8 => ['id' => 5, 'name' => '八强赛', 'integral' => 1400, 'support' => 150],
            4 => ['id' => 6, 'name' => '四强赛', 'integral' => 1700, 'support' => 200],
            2 => ['id' => 7, 'name' => '半决赛', 'integral' => 2300, 'support' => 270],
            1 => ['id' => 8, 'name' => '决赛', 'integral' => 3000, 'support' => 350],
        ],
    ];

    private const string DONE_KEY = 'war_battle_done_';

    /**
     * 当前时间算出的期数、阶段、第几天、当前轮次与倒计时
     *
     * stage：1 活动未开启；2 报名阶段；5 分组等待；3 战斗阶段；6 当日战斗结束（显示晋级列表与下次开战倒计时）；4 活动结束
     *
     * @return array<string, int|array<int, int>>
     */
    public static function clock(?int $now = null): array
    {
        $now ??= BossClock::now();
        // 期数：月初 5 天内还是上个月的武斗会（第 4 天在 2 月可能落到 3 月 1 日）
        $ym = date('Y-m-', $now - 5 * 86400);
        $c = [
            'now' => $now,
            'phase' => (int)date('Ym', (int)strtotime($ym . '01')),
            'entry_start' => (int)strtotime($ym . '19'),
            'entry_end' => (int)strtotime($ym . '26 18:00:00'),
        ];
        $first = (int)strtotime($ym . '26 19:00:00');
        $c['day_start'] = $c['day_end'] = [];
        for ($d = 1; $d <= 4; $d++) {
            $c['day_start'][$d] = $first + ($d - 1) * 86400;
            $c['day_end'][$d] = $c['day_start'][$d] + self::ROUNDS * self::BATTLE_INTERVAL;
        }
        $c['max_round'] = self::maxRound($c['phase']);
        $end = $c['day_start'][4];
        for ($r = $c['max_round']; $r >= 1; $r = intdiv($r, 2)) {
            $end += self::BATTLE_INTERVAL * 3;
        }
        $c['day_end'][4] = $end;
        $c['day'] = $c['pre_day'] = $c['round'] = $c['battle_cd'] = 0;
        if ($now < $c['entry_start']) {
            $c['stage'] = 1;
            return $c;
        }
        if ($now < $c['entry_end']) {
            $c['stage'] = 2;
            return $c;
        }
        if ($now < $c['day_start'][1]) {
            $c['stage'] = 5;
            return $c;
        }
        $c['stage'] = 4;
        for ($d = 1; $d <= 4; $d++) {
            if ($now >= $c['day_start'][$d] && $now < $c['day_end'][$d]) {
                $c['stage'] = 3;
                $c['day'] = $d;
                break;
            }
            if ($d < 4 && $now >= $c['day_end'][$d] && $now < $c['day_start'][$d + 1]) {
                $c['stage'] = 6;
                $c['pre_day'] = $d;
                $c['day'] = $d + 1;
                break;
            }
        }
        if ($c['stage'] === 3 && $c['day'] <= 3) {
            // 预赛到复赛：当天第几轮
            $r = intdiv($now - $c['day_start'][$c['day']], self::BATTLE_INTERVAL) + 1;
            $c['round'] = $r;
            $c['battle_cd'] = $c['day_start'][$c['day']] + $r * self::BATTLE_INTERVAL - $now;
        } elseif ($c['stage'] === 3) {
            // 决赛：1/n 轮次（同战神殿）
            $round = $c['max_round'] * 2;
            $battleTime = $c['day_start'][4];
            while ($now >= $battleTime && $round > 1) {
                $battleTime += self::BATTLE_INTERVAL * 3;
                $round = intdiv($round, 2);
            }
            $c['round'] = $round;
            $c['battle_cd'] = $battleTime - $now;
        }
        if ($c['stage'] === 4) {
            $c['day'] = 4;
        }
        return $c;
    }

    /**
     * index：各阶段信息（limit_level、day 在顶层，info.is_entry 为报名 / 参赛状态）
     *
     * @return array<string, mixed>
     */
    public static function index(int $uid, int $serverid): array
    {
        $c = self::clock();
        $cd = 0;
        $isEntry = self::isEntry($c['phase'], $serverid, $uid) ? 1 : 0;
        $info = [];
        if ($c['stage'] === 1) {
            // 活动未开启
            $cd = $c['entry_start'] - $c['now'];
        } elseif ($c['stage'] === 2) {
            // 报名阶段
            $cd = $c['entry_end'] - $c['now'];
            $info['list'] = self::entryMember($c['phase'], self::ENTRY_SHOW);
        } elseif ($c['stage'] === 5) {
            // 分组等待
            $cd = $c['day_start'][1] - $c['now'];
        } elseif ($c['stage'] === 6) {
            // 当日战斗结束：晋级列表与下次开战倒计时
            $cd = $c['day_start'][$c['day']] - $c['now'];
            $info = self::riseInfo($c['phase'], $c['pre_day'], $serverid, $uid);
        } elseif ($c['stage'] === 3) {
            // 不带玩家的请求（s1 war_model->get_entry_level 只取 limit_level）不分配关注场次
            if ($uid > 0 && $serverid > 0) {
                [$info, $cd, $isEntry] = self::battleInfo($c, $serverid, $uid);
            }
        } else {
            // 活动结束：武神榜
            $info['list'] = self::rankList($c['phase']);
        }
        $info['is_entry'] = $isEntry;
        return [
            'status' => 1,
            'stage' => $c['stage'],
            'palace' => DB::table('battle_war_palace')->whereNull('deleted_at')->value('phase') !== null ? 1 : 0,
            'cd' => $c['stage'] === 4 ? 0 : $cd + 3,
            'limit_level' => self::LIMIT_LEVEL,
            'day' => $c['day'],
            'info' => $info,
        ];
    }

    /**
     * entry：报名
     *
     * status：-1 报名未开启；-2 报名已结束；-3 输入参数有误；-4 已报名；1 成功（list 为最近报名的玩家）
     *
     * @return array<string, mixed>
     */
    public static function entry(int $serverid, int $uid, string $nickname, int $gender): array
    {
        $c = self::clock();
        if ($c['stage'] < 2) {
            return ['status' => -1];
        }
        if ($c['stage'] > 2) {
            return ['status' => -2];
        }
        if ($serverid === 0 || $uid === 0) {
            return ['status' => -3];
        }
        // 期数 + 服务器 + uid 唯一，并发重复报名只有一个成功
        $inserted = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_battle_war_entry` (`phase`, `serverid`, `uid`, `nickname`, `gender`, `dateline`) VALUES (:phase, :serverid, :uid, :nickname, :gender, :now)',
            ['phase' => $c['phase'], 'serverid' => $serverid, 'uid' => $uid, 'nickname' => $nickname, 'gender' => $gender, 'now' => $c['now']]
        );
        if ($inserted !== 1) {
            return ['status' => -4];
        }
        return ['status' => 1, 'list' => self::entryMember($c['phase'], self::ENTRY_SHOW)];
    }

    /**
     * sync_battle_info：同步玩家战斗信息
     *
     * status：-3 报名未结束；-1 未报名；-2 同步过于频繁；-4 获取信息失败；-5 保存信息失败；1 成功
     * （等级或国家变化时 info 为新的 level / country）
     *
     * @return array{status: int, info: array<string, mixed>}
     */
    public static function syncBattleInfo(int $uid, int $serverid): array
    {
        $c = self::clock();
        $return = ['status' => 0, 'info' => []];
        if ($c['now'] < $c['entry_end']) {
            $return['status'] = -3;
            return $return;
        }
        $member = self::entryRow($c['phase'], $serverid, $uid);
        if ($member === null) {
            $return['status'] = -1;
        } elseif ($c['now'] - $member['dateline'] < self::DELAY) {
            $return['status'] = -2;
        } else {
            $result = self::doSync($c, $serverid, $uid);
            $return['status'] = match ($result['status']) {
                1 => 1,
                -1 => -4,
                -2 => -5,
                default => 0,
            };
            if ($return['status'] === 1) {
                $return['info'] = $result['info'];
            }
        }
        return $return;
    }

    /**
     * support：观战玩家支持本轮关注那场的一方
     *
     * status：-1 已截止 / 参数不正确；-2 已经支持过；1 成功
     *
     * @return array{status: int}
     */
    public static function support(int $side, int $serverid, int $uid): array
    {
        $c = self::clock();
        if ($c['stage'] !== 3 || ($side !== 1 && $side !== 2)) {
            return ['status' => -1];
        }
        // 决赛圈只能在第一局支持
        $close = $c['day'] === 4 ? self::BATTLE_INTERVAL * 2 + self::SUPPORT_CLOSE : self::SUPPORT_CLOSE;
        if ($c['battle_cd'] < $close) {
            return ['status' => -1];
        }
        $play = self::matchRound($c['phase'], $c['day'], $serverid, $uid, $c['round']);
        $toserverid = (int)($play['serverid' . $side] ?? 0);
        $touid = (int)($play['uid' . $side] ?? 0);
        if ($touid === 0 || ($play['support_side'] ?? 0) !== 0) {
            return ['status' => -2];
        }
        $updated = DB::table('battle_war_support')->where('phase', $c['phase'])->where('day', $c['day'])->where('round', $c['round'])
            ->where('serverid', $serverid)->where('uid', $uid)->where('side', 0)->whereNull('deleted_at')
            ->update(['side' => $side, 'toserverid' => $toserverid, 'touid' => $touid]);
        if ($updated !== 1) {
            return ['status' => -2];
        }
        DB::execute('UPDATE `zc_sanguo_battle_war_round` SET `support' . $side . '` = `support' . $side . '` + 1 WHERE `id` = :id', ['id' => (int)$play['id']]);
        return ['status' => 1];
    }

    /**
     * get_support：一场的支持数（按开打倒计时放大显示）
     *
     * @return array<string, mixed>
     */
    public static function getSupport(int $id): array
    {
        $c = self::clock();
        if ($id === 0) {
            return ['status' => -1];
        }
        $row = DB::table('battle_war_round')->select('support1', 'support2')->where('id', $id)->whereNull('deleted_at')->first();
        $stage = self::roundStage($c)[0];
        return ['status' => 1, 'support' => [
            'left' => self::supportUp($c, (int)($row['support1'] ?? 0), $stage),
            'right' => self::supportUp($c, (int)($row['support2'] ?? 0), $stage),
        ]];
    }

    /**
     * get_report_list：参赛玩家某期（0 为本期）的历程
     *
     * status：-1 不是该期参赛玩家；1 成功（info：uid / serverid / nickname / gender / level / country / round 名次；list 为每一局）
     *
     * @return array<string, mixed>
     */
    public static function reportList(int $serverid, int $uid, int $phase = 0): array
    {
        if ($phase <= 0) {
            $phase = self::clock()['phase'];
        }
        $info = self::entryRow($phase, $serverid, $uid);
        if ($info === null) {
            return ['status' => -1];
        }
        $infoArr = [];
        foreach (['uid', 'serverid', 'nickname', 'gender', 'level', 'country'] as $field) {
            $infoArr[$field] = $info[$field];
        }
        $infoArr['round'] = $info['goout'] < 2 ? $info['goout'] + 1 : $info['goout'] * 2;
        return ['status' => 1, 'list' => self::reportListByMember($phase, $serverid, $uid), 'info' => $infoArr];
    }

    /**
     * get_reward_list / get_reward：武斗会没有物品奖励（积分在比赛中直接记入），一律回 -1
     *
     * @return array{status: int}
     */
    public static function rewardList(int $serverid, int $uid, int $type): array
    {
        return ['status' => -1];
    }

    /**
     * @return array{status: int}
     */
    public static function reward(int $serverid, int $uid, int $type, int $id): array
    {
        return ['status' => -1];
    }

    /**
     * get_report_info：一局的战报（report 为战报行，integral 为这一场胜者的积分）
     *
     * @return array<string, mixed>
     */
    public static function reportInfo(int $id, int $stage): array
    {
        $row = DB::table('battle_war_report')->select('id', 'stage', 'report', 'nickname', 'userlevel', 'name', 'level', 'background', 'god')
            ->where('id', $id)->where('stage', $stage)->whereNull('deleted_at')->first();
        if ($row === null) {
            return ['status' => 0];
        }
        $report = json_decode((string)$row['report'], true);
        $row = array_map(static fn (mixed $v): mixed => is_numeric($v) ? (int)$v : $v, $row);
        $row['report'] = is_array($report) ? $report : [];
        $play = DB::table('battle_war_round')->select('day', 'round')->where('id', $id)->whereNull('deleted_at')->first();
        $row['integral'] = $play === null ? 0 : (self::rewardOf((int)$play['day'], (int)$play['round'])['integral'] ?? 0);
        return ['status' => 1, 'report' => $row];
    }

    /**
     * get_palace：斗士堂（各期四强，每期一组，组内按出局位置）；$all 不为 1 时只给最近一期；没有数据回 -1
     *
     * @return array<string, mixed>
     */
    public static function palaceList(int $all): array
    {
        $rows = DB::table('battle_war_palace')->select('phase', 'serverid', 'uid', 'level', 'site', 'nickname')->whereNull('deleted_at')
            ->orderBy('phase')->orderBy('site')->get();
        if ($rows === []) {
            return ['status' => -1, 'list' => []];
        }
        $list = [];
        foreach ($rows as $row) {
            $row = array_map(static fn (mixed $v): mixed => is_numeric($v) ? (int)$v : $v, $row);
            $list[$row['phase']][] = $row;
        }
        $list = array_values($list);
        if ($all !== 1) {
            $list = [end($list)];
        }
        return ['status' => 1, 'list' => $list];
    }

    /**
     * get_rank_list：本期武神榜（32 强）；没有为 -1
     *
     * @return array<string, mixed>
     */
    public static function rankListResult(): array
    {
        $list = self::rankList(self::clock()['phase']);
        return $list === [] ? ['status' => -1] : ['status' => 1, 'list' => $list];
    }

    /**
     * get_point：积分商城积分（没有记录为 0）
     */
    public static function getPoint(int $uid, int $serverid): int
    {
        return (int)(DB::table('battle_war_member_info')->where('serverid', $serverid)->where('uid', $uid)->whereNull('deleted_at')->value('integral') ?? 0);
    }

    /**
     * update_point：扣积分（积分不足时不扣，回 -1）
     *
     * @return array{status: int}
     */
    public static function updatePoint(int $uid, int $point, int $serverid): array
    {
        $updated = DB::execute(
            'UPDATE `zc_sanguo_battle_war_member_info` SET `integral` = `integral` - :point, `dateline` = :now WHERE `serverid` = :serverid AND `uid` = :uid AND `integral` >= :point2 AND `deleted_at` IS NULL',
            ['point' => $point, 'now' => BossClock::now(), 'serverid' => $serverid, 'uid' => $uid, 'point2' => $point]
        );
        return ['status' => $updated === 1 ? 1 : -1];
    }

    // ---------------------------------------------------------------------------------------------
    // 计划任务（每分钟）
    // ---------------------------------------------------------------------------------------------

    /**
     * 报名结束后同步全部报名玩家；每天开打时生成第一轮对阵；每局开打前 1 分钟算出本局全部战斗并生成下一轮；
     * 预赛到复赛每天打完排名晋级；决赛打完更新四强
     *
     * @return int 本次算了几场
     */
    public static function checkPk(int $now): int
    {
        $c = self::clock($now);
        if ($c['stage'] === 5 && self::once($c['phase'], 'sync_all')) {
            foreach (DB::table('battle_war_entry')->select('serverid', 'uid', 'level')->where('phase', $c['phase'])->whereNull('deleted_at')->get() as $value) {
                if ((int)$value['level'] === 0) {
                    self::doSync($c, (int)$value['serverid'], (int)$value['uid']);
                }
            }
            return 0;
        }
        if ($c['stage'] === 6) {
            self::finishDay($c, $c['pre_day']);
            return 0;
        }
        if ($c['stage'] === 4) {
            self::updatePalace($c['phase']);
            return 0;
        }
        if ($c['stage'] !== 3) {
            return 0;
        }
        $day = $c['day'];
        if ($day <= 3) {
            // 前一天没排过名的先排名（计划任务停过时补上）
            if ($day > 1) {
                self::finishDay($c, $day - 1);
            }
            self::pairRound($c['phase'], $day, $c['round']);
            if ($c['battle_cd'] > 60 || !self::once($c['phase'], $day . '_' . $c['round'])) {
                return 0;
            }
            $num = self::pkRound($c, $c['round']);
            if ($c['round'] < self::ROUNDS) {
                self::pairRound($c['phase'], $day, $c['round'] + 1);
            } else {
                self::finishDay($c, $day);
            }
            return $num;
        }
        // 决赛
        self::finishDay($c, 3);
        if (self::maxRoundDay4($c['phase']) === 0) {
            self::fetchRound($c['phase']);
            $c = self::clock($now);
            if ($c['stage'] !== 3) {
                return 0;
            }
        }
        if ($c['round'] > $c['max_round']) {
            return 0;
        }
        [$stage, $cd] = self::roundStage($c);
        if ($cd > 60 || !self::once($c['phase'], '4_' . $c['round'] . '_' . $stage)) {
            return 0;
        }
        $num = self::pkRound($c, $c['round']);
        if ($c['battle_cd'] <= 60) {
            self::fetchRound($c['phase']);
            if ($c['round'] === 1) {
                self::updatePalace($c['phase']);
            }
        }
        return $num;
    }

    /**
     * 单场（一局）战斗：预赛到复赛一局定胜负；决赛圈每次打一局（第一局 / 第二局 / 决胜局）
     *
     * status：-1 玩家信息不全；-2 已经战斗过；1 成功
     *
     * @param array<string, mixed> $c clock()
     * @return array{status: int}
     */
    public static function pk(int $roundid, array $c): array
    {
        $play = self::playById($roundid);
        if ($play === null) {
            return ['status' => 0];
        }
        $status = 0;
        $reportStage = $playWin = 0;
        $swapReport = false;
        $data = [];
        $pkresult = null;
        if ($play['uid2']) {
            $left = self::battleData($play['serverid1'], $play['uid1']) ?? self::memberTeamSaved($play['serverid1'], $play['uid1'], $c['now']);
            $right = self::battleData($play['serverid2'], $play['uid2']) ?? self::memberTeamSaved($play['serverid2'], $play['uid2'], $c['now']);
            // 支持数多的一方先手（相同时左方）
            $first = $play['support1'] >= $play['support2'] ? 1 : 2;
            if ($left === null || $right === null) {
                $status = -1;
            } elseif ($play['day'] <= 3) {
                if ($play['win1'] === 0) {
                    [$pkresult, $swapReport] = self::battleFirst($left, $right, $first);
                    $playWin = self::pkResult($pkresult['assess'], $first);
                    $data = ['win1' => $playWin];
                    $reportStage = 1;
                } else {
                    $status = -2;
                }
            } elseif ($play['win1'] === 0) {
                [$pkresult, $swapReport] = self::battleFirst($left, $right, $first);
                $data = ['win1' => self::pkResult($pkresult['assess'], $first)];
                $reportStage = 1;
            } elseif ($play['win2'] === 0) {
                // 第二局：换后手方先手
                [$pkresult, $swapReport] = self::battleFirst($left, $right, 3 - $first);
                $data = ['win2' => self::pkResult($pkresult['assess'], 3 - $first)];
                $reportStage = 2;
                if ($data['win2'] === $play['win1']) {
                    $playWin = $play['win1'];
                }
            } elseif ($play['win1'] !== $play['win2'] && $play['win3'] === 0) {
                [$pkresult, $swapReport] = self::battleFirst($left, $right, $first);
                $data = ['win3' => self::pkResult($pkresult['assess'], $first)];
                $reportStage = 3;
                $playWin = $data['win3'];
            } else {
                $status = -2;
            }
        } elseif ($play['win1'] === 0) {
            // 右方没人，左方轮空（算胜）
            $data = ['win1' => 1];
            $playWin = 1;
        } else {
            $status = -2;
        }
        if ($data === []) {
            return ['status' => $status];
        }
        if ($playWin) {
            $data['win'] = $playWin;
        }
        // 记录战斗结果（以本局未打为条件，并发只记一次）
        $stageColumn = 'win' . max(1, $reportStage);
        $updated = DB::table('battle_war_round')->where('id', $roundid)->where($stageColumn, 0)->update($data);
        if ($updated !== 1) {
            return ['status' => -2];
        }
        if ($reportStage && $pkresult !== null) {
            $members = self::memberPair($c['phase'], $play);
            if ($swapReport) {
                $pkresult = GodServer::swapReport($pkresult);
            }
            $pkresult['up'] = 0;
            $pkresult['day'] = $play['day'];
            if ($playWin) {
                $pkresult['up'] = $play['day'] === 4 && $play['round'] === 1 ? 2 : 1;
                $pkresult['round'] = $play['round'];
            }
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_battle_war_report` (`id`, `stage`, `report`, `nickname`, `userlevel`, `name`, `level`) VALUES (:id, :stage, :report, :nickname, :userlevel, :name, :level)',
                [
                    'id' => $roundid,
                    'stage' => $reportStage,
                    'report' => (string)json_encode($pkresult, JSON_UNESCAPED_UNICODE),
                    'nickname' => $members['left']['nickname'],
                    'userlevel' => $members['left']['level'],
                    'name' => $members['right']['nickname'],
                    'level' => $members['right']['level'],
                ]
            );
        }
        if ($playWin) {
            self::settle($c, $play, $playWin);
        }
        return ['status' => 1];
    }

    // ---------------------------------------------------------------------------------------------

    /**
     * 战斗阶段的对战信息
     *
     * @param array<string, mixed> $c
     * @return array{0: array<string, mixed>, 1: int, 2: int}
     */
    private static function battleInfo(array $c, int $serverid, int $uid): array
    {
        [$roundStage, $cd] = self::roundStage($c);
        $alive = self::isEntry($c['phase'], $serverid, $uid, true);
        $isEntry = 0;
        $play = $alive ? self::playByMember($c['phase'], $c['day'], $serverid, $uid, $c['round']) : [];
        if ($play !== []) {
            // 参赛玩家，且未出局
            $isEntry = 1;
        } else {
            // 观战玩家 查询关注的战斗场次
            $play = self::matchRound($c['phase'], $c['day'], $serverid, $uid, $c['round']);
            if (($play['support_side'] ?? 0) === 1) {
                $isEntry = -1;
            } elseif (($play['support_side'] ?? 0) === 2) {
                $isEntry = -2;
            }
        }
        $play += ['id' => 0, 'day' => $c['day'], 'serverid1' => 0, 'uid1' => 0, 'serverid2' => 0, 'uid2' => 0, 'support1' => 0, 'support2' => 0,
            'win1' => 0, 'win2' => 0, 'win3' => 0, 'round' => $c['round']];
        $play['win' . $roundStage] = 0;
        $leftReport = $rightReport = [];
        foreach (['serverid1' => 'uid1', 'serverid2' => 'uid2'] as $sKey => $uKey) {
            if (!$play[$sKey] || !$play[$uKey]) {
                continue;
            }
            foreach (self::reportListByMember($c['phase'], $play[$sKey], $play[$uKey]) as $value) {
                if ($value['id'] === $play['id'] && $value['stage'] === $roundStage) {
                    continue;
                }
                if ($sKey === 'serverid1') {
                    $leftReport[] = $value;
                } else {
                    $rightReport[] = $value;
                }
            }
        }
        $members = self::memberPair($c['phase'], $play);
        $thePlay = ['left' => $members['left'], 'right' => $members['right']];
        if ($play['support1'] !== $play['support2']) {
            $thePlay['left']['support'] = self::supportUp($c, $play['support1'], $roundStage);
            $thePlay['right']['support'] = self::supportUp($c, $play['support2'], $roundStage);
        } else {
            $thePlay['left']['support'] = self::supportUp($c, $play['support1'], 2);
            $thePlay['right']['support'] = self::supportUp($c, $play['support2'], $roundStage);
        }
        $thePlay['left']['win'] = $thePlay['right']['win'] = 0;
        for ($i = 1; $i <= 3; $i++) {
            if ($play['win' . $i] === 1) {
                $thePlay['left']['win']++;
            } elseif ($play['win' . $i] === 2) {
                $thePlay['right']['win']++;
            }
        }
        // 当前排名（预赛到复赛：当天名次与晋级人数；决赛为 0，客户端不显示）
        $rank = $maxRank = 0;
        if ($c['day'] <= 3 && $isEntry === 1) {
            foreach (self::dayStanding($c['phase'], $c['day']) as $k => $value) {
                if ($value['serverid'] === $serverid && $value['uid'] === $uid) {
                    $rank = $k + 1;
                    break;
                }
            }
            $maxRank = self::ADVANCE[$c['day']];
        }
        $info = [
            'play' => $thePlay,
            'reward' => self::rewardOf($c['day'], $play['round']),
            'report' => ['left' => $leftReport, 'right' => $rightReport],
            'round' => $play['round'],
            'roundid' => $play['id'],
            'stage' => $roundStage,
            'max_round' => $c['day'] <= 3 ? self::ROUNDS : $c['max_round'],
            'rank' => $rank,
            'max_rank' => $maxRank,
        ];
        return [$info, $cd, $isEntry];
    }

    /**
     * 当日战斗结束的晋级信息：list 为晋级名单（rank / uid / serverid / nickname），myrank 为本人当天名次（没参赛为 0），
     * isup 1 晋级、0 未晋级，pre_day 为刚打完的第几天
     *
     * @return array<string, mixed>
     */
    private static function riseInfo(int $phase, int $preDay, int $serverid, int $uid): array
    {
        $standing = self::dayStanding($phase, $preDay);
        $list = [];
        $myrank = $isup = 0;
        foreach ($standing as $k => $value) {
            $rank = $k + 1;
            if ($rank <= self::ADVANCE[$preDay]) {
                $list[] = ['rank' => $rank, 'uid' => $value['uid'], 'serverid' => $value['serverid'], 'nickname' => $value['nickname']];
            }
            if ($value['serverid'] === $serverid && $value['uid'] === $uid) {
                $myrank = $rank;
                $isup = $rank <= self::ADVANCE[$preDay] ? 1 : 0;
            }
        }
        return ['list' => $list, 'myrank' => $myrank, 'isup' => $isup, 'pre_day' => $preDay];
    }

    /**
     * 当天的排名：当天参赛过的玩家按当天胜场、本期积分降序，报名先后升序
     *
     * @return list<array{serverid: int, uid: int, nickname: string, wins: int, integral: int, id: int}>
     */
    private static function dayStanding(int $phase, int $day): array
    {
        $wins = [];
        foreach (DB::table('battle_war_round')->select('serverid1', 'uid1', 'serverid2', 'uid2', 'win')->where('phase', $phase)->where('day', $day)
            ->whereNull('deleted_at')->get() as $row) {
            foreach ([1, 2] as $i) {
                if ((int)$row['uid' . $i] === 0) {
                    continue;
                }
                $key = (int)$row['serverid' . $i] . '_' . (int)$row['uid' . $i];
                $wins[$key] = ($wins[$key] ?? 0) + ((int)$row['win'] === $i ? 1 : 0);
            }
        }
        if ($wins === []) {
            return [];
        }
        $list = [];
        foreach (DB::table('battle_war_entry')->select('id', 'serverid', 'uid', 'nickname', 'integral')->where('phase', $phase)->whereNull('deleted_at')->get() as $row) {
            $key = (int)$row['serverid'] . '_' . (int)$row['uid'];
            if (!isset($wins[$key])) {
                continue;
            }
            $list[] = ['serverid' => (int)$row['serverid'], 'uid' => (int)$row['uid'], 'nickname' => (string)$row['nickname'], 'wins' => $wins[$key],
                'integral' => (int)$row['integral'], 'id' => (int)$row['id']];
        }
        usort($list, static fn (array $a, array $b): int => [$b['wins'], $b['integral'], $a['id']] <=> [$a['wins'], $a['integral'], $b['id']]);
        return $list;
    }

    /**
     * 预赛到复赛一天打完：前 ADVANCE 名晋级，其余出局（每期每天一次）
     *
     * @param array<string, mixed> $c
     */
    private static function finishDay(array $c, int $day): void
    {
        if ($day < 1 || $day > 3 || DB::table('battle_war_round')->where('phase', $c['phase'])->where('day', $day)->whereNull('deleted_at')->value('id') === null) {
            return;
        }
        if (!self::once($c['phase'], 'finish_' . $day)) {
            return;
        }
        $standing = self::dayStanding($c['phase'], $day);
        foreach (array_slice($standing, self::ADVANCE[$day]) as $value) {
            DB::table('battle_war_entry')->where('phase', $c['phase'])->where('serverid', $value['serverid'])->where('uid', $value['uid'])
                ->where('goout', 0)->update(['goout' => self::OUT[$day]]);
        }
    }

    /**
     * 预赛到复赛的一轮对阵：未出局的玩家按当天胜场降序（同胜场随机）相邻配对，单数时最后一人轮空（已生成的不再生成）
     */
    private static function pairRound(int $phase, int $day, int $round): void
    {
        if ($round < 1 || $round > self::ROUNDS) {
            return;
        }
        if (DB::table('battle_war_round')->where('phase', $phase)->where('day', $day)->where('round', $round)->whereNull('deleted_at')->value('id') !== null) {
            return;
        }
        Server::transaction(static function () use ($phase, $day, $round): void {
            DB::query('SELECT GET_LOCK(\'battle_war_fetch\', 10)');
            try {
                if (DB::table('battle_war_round')->where('phase', $phase)->where('day', $day)->where('round', $round)->whereNull('deleted_at')->value('id') !== null) {
                    return;
                }
                $wins = [];
                foreach (self::dayStanding($phase, $day) as $value) {
                    $wins[$value['serverid'] . '_' . $value['uid']] = $value['wins'];
                }
                $players = DB::table('battle_war_entry')->select('serverid', 'uid')->where('phase', $phase)->where('goout', 0)->whereNull('deleted_at')->get();
                shuffle($players);
                usort($players, static fn (array $a, array $b): int => ($wins[$b['serverid'] . '_' . $b['uid']] ?? 0) <=> ($wins[$a['serverid'] . '_' . $a['uid']] ?? 0));
                for ($i = 0, $n = count($players); $i < $n; $i += 2) {
                    self::insertRound($phase, $day, $round, $players[$i], $players[$i + 1] ?? null);
                }
            } finally {
                DB::query('SELECT RELEASE_LOCK(\'battle_war_fetch\')');
            }
        });
    }

    /**
     * 决赛的对阵：第一轮未出局的玩家随机排开（不足的右方轮空）；之后每轮结束把相邻两场的胜者配成下一轮
     */
    private static function fetchRound(int $phase): void
    {
        Server::transaction(static function () use ($phase): void {
            DB::query('SELECT GET_LOCK(\'battle_war_fetch\', 10)');
            try {
                $min = DB::table('battle_war_round')->where('phase', $phase)->where('day', 4)->whereNull('deleted_at')->min('round');
                if ($min === null) {
                    $entries = DB::table('battle_war_entry')->select('serverid', 'uid')->where('phase', $phase)->where('goout', 0)->whereNull('deleted_at')->orderBy('id')->get();
                    $m = self::firstRound(count($entries));
                    shuffle($entries);
                    for ($i = 0; $i < $m; $i++) {
                        self::insertRound($phase, 4, $m, $entries[$i] ?? ['serverid' => 0, 'uid' => 0], $entries[$i + $m] ?? null);
                    }
                    return;
                }
                $round = (int)$min;
                if ($round <= 1) {
                    return;
                }
                $winners = [];
                foreach (DB::table('battle_war_round')->select('serverid1', 'uid1', 'serverid2', 'uid2', 'win')->where('phase', $phase)->where('day', 4)
                    ->where('round', $round)->whereNull('deleted_at')->orderBy('id')->get() as $row) {
                    if ((int)$row['win'] === 0) {
                        return;
                    }
                    $side = (int)$row['win'];
                    $winners[] = ['serverid' => (int)$row['serverid' . $side], 'uid' => (int)$row['uid' . $side]];
                }
                $next = intdiv($round, 2);
                for ($i = 0; $i < $next; $i++) {
                    self::insertRound($phase, 4, $next, $winners[$i * 2] ?? ['serverid' => 0, 'uid' => 0], $winners[$i * 2 + 1] ?? null);
                }
            } finally {
                DB::query('SELECT RELEASE_LOCK(\'battle_war_fetch\')');
            }
        });
    }

    /**
     * 本轮（本局）全部战斗
     *
     * @param array<string, mixed> $c
     */
    private static function pkRound(array $c, int $round): int
    {
        $num = 0;
        foreach (DB::table('battle_war_round')->select('id')->where('phase', $c['phase'])->where('day', $c['day'])->where('round', $round)
            ->whereNull('deleted_at')->orderBy('id')->get() as $value) {
            self::pk((int)$value['id'], $c);
            $num++;
        }
        return $num;
    }

    /**
     * 决出胜负：支持表记胜方；胜者与支持胜方的玩家得积分；决赛圈败方出局（记出局轮次）
     *
     * @param array<string, mixed> $c
     * @param array<string, int> $play
     */
    private static function settle(array $c, array $play, int $win): void
    {
        $reward = self::rewardOf($play['day'], $play['round']);
        DB::table('battle_war_support')->where('roundid', $play['id'])->whereNull('deleted_at')->update(['win' => $win]);
        $winServer = $play['serverid' . $win];
        $winUid = $play['uid' . $win];
        $winColumn = match ($play['day']) {
            3 => 'win2',
            4 => 'win3',
            default => 'win1',
        };
        DB::execute(
            'UPDATE `zc_sanguo_battle_war_entry` SET `integral` = `integral` + :integral, `' . $winColumn . '` = `' . $winColumn . '` + 1 WHERE `phase` = :phase AND `serverid` = :serverid AND `uid` = :uid',
            ['integral' => (int)($reward['integral'] ?? 0), 'phase' => $c['phase'], 'serverid' => $winServer, 'uid' => $winUid]
        );
        self::addPoint($winServer, $winUid, (int)($reward['integral'] ?? 0), $c['now']);
        if ((int)($reward['support'] ?? 0) > 0) {
            foreach (DB::table('battle_war_support')->select('serverid', 'uid')->where('roundid', $play['id'])->where('side', $win)->whereNull('deleted_at')->get() as $row) {
                self::addPoint((int)$row['serverid'], (int)$row['uid'], (int)$reward['support'], $c['now']);
            }
        }
        if ($play['day'] === 4) {
            $lose = 3 - $win;
            if ($play['uid' . $lose]) {
                DB::table('battle_war_entry')->where('phase', $c['phase'])->where('serverid', $play['serverid' . $lose])->where('uid', $play['uid' . $lose])
                    ->update(['goout' => $play['round']]);
            }
        }
    }

    /**
     * 积分商城积分累加（war_member_info）
     */
    private static function addPoint(int $serverid, int $uid, int $integral, int $now): void
    {
        if ($integral <= 0 || $uid === 0) {
            return;
        }
        DB::execute(
            'INSERT INTO `zc_sanguo_battle_war_member_info` (`serverid`, `uid`, `integral`, `dateline`) VALUES (:serverid, :uid, :integral, :now)
             ON DUPLICATE KEY UPDATE `integral` = `integral` + VALUES(`integral`), `dateline` = VALUES(`dateline`), `deleted_at` = NULL',
            ['serverid' => $serverid, 'uid' => $uid, 'integral' => $integral, 'now' => $now]
        );
    }

    /**
     * 决赛打完：前四名进入斗士堂（每期一次）
     */
    private static function updatePalace(int $phase): void
    {
        $final = DB::table('battle_war_round')->select('serverid1', 'uid1', 'serverid2', 'uid2', 'win')->where('phase', $phase)->where('day', 4)
            ->where('round', 1)->where('win', '>', 0)->whereNull('deleted_at')->first();
        if ($final === null || !self::once($phase, 'palace')) {
            return;
        }
        $win = (int)$final['win'];
        $sites = [
            1 => [(int)$final['serverid' . $win], (int)$final['uid' . $win]],
            2 => [(int)$final['serverid' . (3 - $win)], (int)$final['uid' . (3 - $win)]],
        ];
        $site = 3;
        foreach (DB::table('battle_war_round')->select('serverid1', 'uid1', 'serverid2', 'uid2', 'win')->where('phase', $phase)->where('day', 4)
            ->where('round', 2)->whereNull('deleted_at')->orderBy('id')->get() as $row) {
            $lose = 3 - (int)$row['win'];
            $sites[$site++] = [(int)$row['serverid' . $lose], (int)$row['uid' . $lose]];
        }
        foreach ($sites as $s => [$serverid, $uid]) {
            if ($uid === 0) {
                continue;
            }
            $entry = self::entryRow($phase, $serverid, $uid);
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_battle_war_palace` (`phase`, `serverid`, `uid`, `level`, `site`, `nickname`, `dateline`) VALUES (:phase, :serverid, :uid, :level, :site, :nickname, :now)',
                ['phase' => $phase, 'serverid' => $serverid, 'uid' => $uid, 'level' => (int)($entry['level'] ?? 0), 'site' => $s,
                    'nickname' => (string)($entry['nickname'] ?? ''), 'now' => BossClock::now()]
            );
        }
    }

    /**
     * 决赛圈当前是第几局与本局倒计时；预赛到复赛为第 1 局、倒计时为 battle_cd
     *
     * @param array<string, mixed> $c
     * @return array{0: int, 1: int}
     */
    private static function roundStage(array $c): array
    {
        if ($c['day'] !== 4) {
            return [1, $c['battle_cd']];
        }
        if ($c['battle_cd'] > self::BATTLE_INTERVAL * 2) {
            return [1, $c['battle_cd'] - self::BATTLE_INTERVAL * 2];
        }
        if ($c['battle_cd'] > self::BATTLE_INTERVAL) {
            return [2, $c['battle_cd'] - self::BATTLE_INTERVAL];
        }
        return [3, $c['battle_cd']];
    }

    /**
     * 显示的支持数（同战神殿 get_support_up）：(16 - 轮次位数) × 支持数 + 第一局已过时间的比例
     *
     * @param array<string, mixed> $c
     */
    private static function supportUp(array $c, int $support, int $stage): int
    {
        if (!$support) {
            return 0;
        }
        $cd = $stage === 1 ? self::roundStage($c)[1] : 0;
        if ($cd <= 60 || $stage !== 1) {
            $cd = 0;
        }
        $i = 0;
        while (2 ** $i <= $c['round']) {
            $i++;
        }
        $times = 16 - $i;
        return $times * $support + (int)($times * (self::BATTLE_INTERVAL - $cd) / self::BATTLE_INTERVAL);
    }

    /**
     * 这一场的积分奖励（预赛到复赛按天，决赛按轮次）；没有为空数组
     *
     * @return array<string, mixed>
     */
    private static function rewardOf(int $day, int $round): array
    {
        if ($day >= 1 && $day <= 3) {
            return self::REWARD['day'][$day];
        }
        return self::REWARD['round'][$round] ?? [];
    }

    /**
     * 决赛第一轮的场数（不小于人数一半的 2 的幂；没人为 0）
     */
    private static function firstRound(int $n): int
    {
        if ($n <= 1) {
            return $n;
        }
        $p = 1;
        while ($p * 2 < $n) {
            $p *= 2;
        }
        return $p;
    }

    /**
     * 决赛的最大轮次：已有对阵时取第一轮的场数，否则按未出局人数算
     */
    private static function maxRound(int $phase): int
    {
        $max = self::maxRoundDay4($phase);
        if ($max > 0) {
            return $max;
        }
        // 第 3 天还没排名时按复赛晋级人数封顶
        $n = (int)DB::table('battle_war_entry')->where('phase', $phase)->where('goout', 0)->whereNull('deleted_at')->count();
        return self::firstRound(min($n, self::ADVANCE[3]));
    }

    private static function maxRoundDay4(int $phase): int
    {
        return (int)(DB::table('battle_war_round')->where('phase', $phase)->where('day', 4)->whereNull('deleted_at')->max('round') ?? 0);
    }

    /**
     * 每期每个任务只做一次（计划任务的去重标记）
     */
    private static function once(int $phase, string $name): bool
    {
        $redis = Redis::instance();
        return $redis !== null && (bool)$redis->set(self::DONE_KEY . $phase . '_' . $name, '1', ['nx', 'ex' => 86400 * 40]);
    }

    /**
     * @param array<string, mixed>|null $left
     * @param array<string, mixed>|null $right
     */
    private static function insertRound(int $phase, int $day, int $round, ?array $left, ?array $right): void
    {
        DB::table('battle_war_round')->insert([
            'phase' => $phase,
            'day' => $day,
            'serverid1' => (int)($left['serverid'] ?? 0),
            'uid1' => (int)($left['uid'] ?? 0),
            'serverid2' => (int)($right['serverid'] ?? 0),
            'uid2' => (int)($right['uid'] ?? 0),
            'round' => $round,
        ]);
    }

    /**
     * is_entry：本期是否报名（$alive 时还要求未出局）
     */
    private static function isEntry(int $phase, int $serverid, int $uid, bool $alive = false): bool
    {
        $query = DB::table('battle_war_entry')->where('phase', $phase)->where('serverid', $serverid)->where('uid', $uid)->whereNull('deleted_at');
        if ($alive) {
            $query->where('goout', 0);
        }
        return $query->value('id') !== null;
    }

    /**
     * @return list<array{nickname: string}>
     */
    private static function entryMember(int $phase, int $limit): array
    {
        $rows = DB::table('battle_war_entry')->select('nickname')->where('phase', $phase)->whereNull('deleted_at')->orderBy('id', 'DESC')->limit($limit)->get();
        return array_map(static fn (array $r): array => ['nickname' => (string)$r['nickname']], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function entryRow(int $phase, int $serverid, int $uid): ?array
    {
        $row = DB::table('battle_war_entry')->select('id', 'phase', 'serverid', 'uid', 'nickname', 'gender', 'level', 'countryid', 'country', 'integral', 'goout', 'dateline')
            ->where('phase', $phase)->where('serverid', $serverid)->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row === null) {
            return null;
        }
        foreach (['id', 'phase', 'serverid', 'uid', 'gender', 'level', 'countryid', 'integral', 'goout', 'dateline'] as $field) {
            $row[$field] = (int)$row[$field];
        }
        $row['nickname'] = (string)$row['nickname'];
        $row['country'] = (string)$row['country'];
        return $row;
    }

    /**
     * 同步：取玩家当前的队伍快照，等级或国家变了同时更新报名行
     *
     * status：-1 获取信息失败（玩家不存在 / 其他服）；-2 保存信息失败；1 成功
     *
     * @param array<string, mixed> $c
     * @return array{status: int, info: array<string, mixed>}
     */
    private static function doSync(array $c, int $serverid, int $uid): array
    {
        $return = ['status' => 0, 'info' => []];
        if ($serverid !== Server::id()) {
            // 其他服的玩家需请求对方服（多服部署）
            $return['status'] = -1;
            return $return;
        }
        $base = DB::table('member')->select('uid', 'level', 'country')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($base === null) {
            $return['status'] = -1;
            return $return;
        }
        $countryid = (int)$base['country'];
        $countryName = $countryid > 0 ? (string)(DB::table('country')->where('id', $countryid)->whereNull('deleted_at')->value('name') ?? '') : '';
        $member = self::entryRow($c['phase'], $serverid, $uid);
        $data = ['dateline' => $c['now']];
        if ($member !== null && ($member['level'] !== (int)$base['level'] || $member['countryid'] !== $countryid)) {
            $data += ['level' => (int)$base['level'], 'countryid' => $countryid, 'country' => $countryName];
            $return['info'] = ['level' => $data['level'], 'country' => $data['country']];
        }
        DB::table('battle_war_entry')->where('phase', $c['phase'])->where('serverid', $serverid)->where('uid', $uid)->whereNull('deleted_at')->update($data);
        if (self::memberTeamSaved($serverid, $uid, $c['now']) === null) {
            $return['status'] = -2;
            return $return;
        }
        $return['status'] = 1;
        return $return;
    }

    /**
     * 取玩家队伍快照并保存（war_member）；玩家不存在为 null
     *
     * @return array<string, mixed>|null
     */
    private static function memberTeamSaved(int $serverid, int $uid, int $now): ?array
    {
        if ($serverid !== Server::id() || DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('uid') === null) {
            return null;
        }
        $team = TeamBuilder::forMember($uid, defense: true, self: false);
        $data = ['team' => $team['team'], 'matrix_type' => $team['matrix_type'], 'matrix_coe' => $team['matrix_coe']];
        DB::execute(
            'INSERT INTO `zc_sanguo_battle_war_member` (`uid`, `serverid`, `data`, `dateline`) VALUES (:uid, :serverid, :data, :now)
             ON DUPLICATE KEY UPDATE `data` = VALUES(`data`), `dateline` = VALUES(`dateline`), `deleted_at` = NULL',
            ['uid' => $uid, 'serverid' => $serverid, 'data' => (string)json_encode($data), 'now' => $now]
        );
        return self::intTeam($data);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function battleData(int $serverid, int $uid): ?array
    {
        $json = DB::table('battle_war_member')->where('serverid', $serverid)->where('uid', $uid)->whereNull('deleted_at')->value('data');
        $data = $json === null ? null : json_decode((string)$json, true);
        return is_array($data) ? self::intTeam($data) : null;
    }

    /**
     * JSON 解出来的站位键还原为整数
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function intTeam(array $data): array
    {
        $team = [];
        foreach ((array)($data['team'] ?? []) as $pos => $unit) {
            $team[(int)$pos] = $unit;
        }
        $data['team'] = $team;
        return $data;
    }

    /**
     * 先手方为攻方打一局；返回 [战报, 是否需要把战报换回场次的左右]
     *
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private static function battleFirst(array $left, array $right, int $first): array
    {
        [$atk, $def] = $first === 1 ? [$left, $right] : [$right, $left];
        $engine = new BattleEngine((int)$atk['matrix_type'], (float)$atk['matrix_coe'], (int)$def['matrix_type'], (float)$def['matrix_coe']);
        return [$engine->battle($atk['team'], $def['team']), $first !== 1];
    }

    /**
     * 按先手方的评级换算成场次的胜方（1 左方、2 右方）
     */
    private static function pkResult(int $assess, int $first): int
    {
        if ($first === 1) {
            return $assess > 0 ? 1 : 2;
        }
        return $assess > 0 ? 2 : 1;
    }

    /**
     * @return array<string, int>|null
     */
    private static function playById(int $id): ?array
    {
        $row = DB::table('battle_war_round')->select('id', 'phase', 'day', 'serverid1', 'serverid2', 'uid1', 'uid2', 'support1', 'support2', 'win1', 'win2', 'win3', 'win', 'round', 'follow')
            ->where('id', $id)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * 参赛玩家本轮的场次
     *
     * @return array<string, int>
     */
    private static function playByMember(int $phase, int $day, int $serverid, int $uid, int $round): array
    {
        $row = DB::table('battle_war_round')->select('id')->where('phase', $phase)->where('day', $day)->where('round', $round)->whereNull('deleted_at')
            ->whereRaw('((`serverid1` = ? AND `uid1` = ?) OR (`serverid2` = ? AND `uid2` = ?))', [$serverid, $uid, $serverid, $uid])->first();
        return $row === null ? [] : (self::playById((int)$row['id']) ?? []);
    }

    /**
     * 观战玩家本轮关注的场次（第一次进来时随机关注一场；support_side 为已支持的一方，0 未支持）；本轮还没有对阵为空数组
     *
     * @return array<string, int>
     */
    private static function matchRound(int $phase, int $day, int $serverid, int $uid, int $round): array
    {
        $where = static fn () => DB::table('battle_war_support')->select('roundid', 'side')->where('phase', $phase)->where('day', $day)->where('round', $round)
            ->where('serverid', $serverid)->where('uid', $uid)->whereNull('deleted_at');
        $support = $where()->first();
        if ($support === null) {
            $ids = DB::table('battle_war_round')->select('id')->where('phase', $phase)->where('day', $day)->where('round', $round)->where('uid1', '>', 0)
                ->whereNull('deleted_at')->orderBy('id')->get();
            if ($ids === []) {
                return [];
            }
            $roundid = (int)$ids[array_rand($ids)]['id'];
            $inserted = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_battle_war_support` (`phase`, `day`, `serverid`, `uid`, `roundid`, `side`, `round`) VALUES (:phase, :day, :serverid, :uid, :roundid, 0, :round)',
                ['phase' => $phase, 'day' => $day, 'serverid' => $serverid, 'uid' => $uid, 'roundid' => $roundid, 'round' => $round]
            );
            if ($inserted === 1) {
                DB::execute('UPDATE `zc_sanguo_battle_war_round` SET `follow` = `follow` + 1 WHERE `id` = :id', ['id' => $roundid]);
            }
            $support = $where()->first();
        }
        $play = self::playById((int)$support['roundid']) ?? [];
        $play['support_side'] = (int)$support['side'];
        return $play;
    }

    /**
     * 玩家本期的每一局（id 场次、stage 第几局、day 第几天、round 轮次、nickname 对手、side 对手所在的一方、win 1 本局获胜）
     *
     * @return list<array{id: int, stage: int, day: int, round: int, nickname: string, side: int, win: int}>
     */
    private static function reportListByMember(int $phase, int $serverid, int $uid): array
    {
        if ($uid === 0) {
            return [];
        }
        $rounds = DB::table('battle_war_round')->select('id', 'day', 'serverid1', 'uid1', 'serverid2', 'uid2', 'round', 'win1', 'win2', 'win3')
            ->where('phase', $phase)->whereNull('deleted_at')
            ->whereRaw('((`serverid1` = ? AND `uid1` = ?) OR (`serverid2` = ? AND `uid2` = ?))', [$serverid, $uid, $serverid, $uid])
            ->orderBy('day')->orderBy('id')->get();
        $list = [];
        foreach ($rounds as $row) {
            $isLeft = (int)$row['uid1'] === $uid && (int)$row['serverid1'] === $serverid;
            $opponentUid = $isLeft ? (int)$row['uid2'] : (int)$row['uid1'];
            $opponent = $opponentUid > 0 ? self::entryRow($phase, $isLeft ? (int)$row['serverid2'] : (int)$row['serverid1'], $opponentUid) : null;
            foreach (DB::table('battle_war_report')->select('stage')->where('id', (int)$row['id'])->whereNull('deleted_at')->orderBy('stage')->get() as $report) {
                $stage = (int)$report['stage'];
                $list[] = [
                    'id' => (int)$row['id'],
                    'stage' => $stage,
                    'day' => (int)$row['day'],
                    'round' => (int)$row['round'],
                    'nickname' => (string)($opponent['nickname'] ?? ''),
                    'side' => $isLeft ? 2 : 1,
                    'win' => (int)$row['win' . $stage] === ($isLeft ? 1 : 2) ? 1 : 0,
                ];
            }
        }
        return $list;
    }

    /**
     * 两方的报名信息：serverid / uid / nickname / level / gender / countryid / country；uid 为 0 的一方为空信息
     *
     * @param array<string, int> $play
     * @return array{left: array<string, mixed>, right: array<string, mixed>}
     */
    private static function memberPair(int $phase, array $play): array
    {
        $return = [];
        foreach (['left' => 1, 'right' => 2] as $side => $i) {
            $serverid = (int)$play['serverid' . $i];
            $uid = (int)$play['uid' . $i];
            $row = $uid > 0 ? self::entryRow($phase, $serverid, $uid) : null;
            $return[$side] = [
                'serverid' => $serverid,
                'uid' => $uid,
                'nickname' => (string)($row['nickname'] ?? ''),
                'level' => (int)($row['level'] ?? 0),
                'gender' => (int)($row['gender'] ?? 0),
                'countryid' => (int)($row['countryid'] ?? 0),
                'country' => (string)($row['country'] ?? ''),
            ];
        }
        return $return;
    }

    /**
     * 武神榜：本期决赛圈 32 强（冠军 goout 0 且决赛已有胜方），round 为名次（1 冠军、2 亚军、4 四强 … 32）
     *
     * @return list<array{uid: int, serverid: int, nickname: string, level: int, round: int}>
     */
    private static function rankList(int $phase): array
    {
        $final = DB::table('battle_war_round')->select('serverid1', 'uid1', 'serverid2', 'uid2', 'win')->where('phase', $phase)->where('day', 4)
            ->where('round', 1)->where('win', '>', 0)->whereNull('deleted_at')->first();
        $champion = $final === null ? null : [(int)$final['serverid' . (int)$final['win']], (int)$final['uid' . (int)$final['win']]];
        $rows = DB::table('battle_war_entry')->select('uid', 'serverid', 'nickname', 'level', 'goout')->where('phase', $phase)
            ->where('goout', '<=', self::RANK_GOOUT)->whereNull('deleted_at')->orderBy('goout')->orderBy('id')->get();
        $list = [];
        foreach ($rows as $row) {
            $goout = (int)$row['goout'];
            if ($goout === 0 && $champion !== [(int)$row['serverid'], (int)$row['uid']]) {
                continue;
            }
            $list[] = [
                'uid' => (int)$row['uid'],
                'serverid' => (int)$row['serverid'],
                'nickname' => (string)$row['nickname'],
                'level' => (int)$row['level'],
                'round' => $goout < 2 ? $goout + 1 : $goout * 2,
            ];
        }
        return $list;
    }
}
