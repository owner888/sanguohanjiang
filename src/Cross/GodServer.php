<?php

declare(strict_types=1);

namespace Hanjiang\Cross;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\Model\BossClock;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 战神殿的战斗服务器部分（旧版 battle controllers/god.php；s1 transfer_model 的 god_* 请求 war_url 上的 c=god）
 *
 * 每月 8 日 0 点到 15 日 18 点报名（主公 175 级），15 日 18 点后同步战斗数据，19 点开战：先海选（每轮 1/n 赛一局定胜负，
 * 间隔 5 分钟），32 强起每轮三局两胜（每局 5 分钟），按支持数多的一方先手；冠军进入战帝堂。观战玩家每轮关注一场并可支持一方。
 * 单服部署下 battle 是本进程内的模块，s1 的 GodHandler 直接调用这里的方法，返回值与 battle 控制器 api_return 的数组相同；
 * 表名前缀 zc_sanguo_battle_。旧版 war 服务器的地址（war_url）不在 s1 配置里，取 battle 包里的 god.php（报名等级 175、
 * 15 日 19 点开战）；uc 包里的 god.php 是测试用的另一份（报名等级 10、16 日开战），不采用。
 *
 * battle 的 god_model / user_model 不在旧版代码包里，按控制器、s1 调用方、客户端与表结构还原
 * （按控制器与表结构还原（旧版 model 不在代码包里））：
 * - max_round：已有对阵时取本期最大轮次，否则按报名人数取第一轮的场数（不小于人数一半的 2 的幂）；
 * - fetch_round：本期没有对阵时把报名玩家随机排开，第 i 名对第 i + 场数 名（不足的右方轮空）；之后每轮结束把相邻两场的胜者配成下一轮；
 * - match_round：观战玩家本轮第一次进来时随机关注一场（god_support 记一行 side 0，follow + 1），之后一直看这一场；
 * - 队伍快照（god_member.data）为 TeamBuilder::forMember 的结果（同步时取，防守方、非本人视角），战斗按快照算；
 * - 战报列表 get_report_list_by_member：该玩家本期参加过的、轮次不小于 $round 的场次的每一局（side 为对手所在的一方）；
 * - 战神榜 get_rank_list：本期 32 强（出局轮次 16 以内，冠军 goout 为 0）；
 * - 战帝堂 update_palace：冠军决出后写一行（帝号取 s1 lang god_name_{第几世}），并把冠军每场的战报复制进战帝战报；
 *   get_palace 返回全部（客户端只请求第 1 页并倒序显示）；
 * - 奖励 get_reward_by_round / get_my_reward_list / get_my_reward 读 battle 库 god_reward，旧版数据里该表 0 行：奖励一律为空。
 */
final class GodServer
{
    /** 用户同步数据的最小间隔(秒) */
    private const int DELAY = 80;

    /** 战斗间隔时间(s) */
    public const int BATTLE_INTERVAL = 300;

    /** 报名等级限制 */
    public const int LIMIT_LEVEL = 175;

    /** 最近报名的玩家显示人数 */
    private const int ENTRY_SHOW = 4;

    /** 战神榜人数（32 强：出局轮次 16 以内） */
    private const int RANK_GOOUT = 16;

    private const string END_TIME_KEY = 'god_end_time_';
    private const string PK_DONE_KEY = 'god_pk_done_';

    /** s1 lang god_name_1 ~ 36（帝号） */
    private const array GOD_NAME = [
        1 => '无始大帝', 2 => '穹宇大帝', 3 => '极天大帝', 4 => '裂海大帝', 5 => '虚空大帝', 6 => '乱古大帝', 7 => '紫薇大帝',
        8 => '轮回大帝', 9 => '太衍大帝', 10 => '天羽大帝', 11 => '神武大帝', 12 => '四极大帝', 13 => '圣威大帝', 14 => '神魔大帝',
        15 => '太皇大帝', 16 => '圣祖大帝', 17 => '紫皇大帝', 18 => '四方大帝', 19 => '傲世大帝', 20 => '皇极大帝', 21 => '七曜大帝',
        22 => '天元大帝', 23 => '破元大帝', 24 => '圣天大帝', 25 => '武威大帝', 26 => '武烈大帝', 27 => '太初大帝', 28 => '玄通大帝',
        29 => '文谦大帝', 30 => '圣通大帝', 31 => '德象大帝', 32 => '亘古大帝', 33 => '睿圣大帝', 34 => '玄冲大帝', 35 => '乾坤大帝',
        36 => '文成大帝',
    ];

    /**
     * 构造函数：当前时间算出的期数、阶段、当前轮次与倒计时
     *
     * play_stage：1 活动未开启；2 报名阶段；5 准备阶段；3 战斗阶段；4 活动结束
     *
     * @return array{now: int, phase: int, entry_start: int, entry_end: int, battle_start: int, end_time: int, max_round: int, round: int, battle_cd: int, stage: int}
     */
    public static function clock(?int $now = null): array
    {
        $now ??= BossClock::now();
        $ym = date('Y-m-', $now);
        $c = [
            'now' => $now,
            'phase' => (int)date('Ym', $now),
            // 当月 8 日开始报名，15 日 18 点报名结束，晚 7 点开战
            'entry_start' => (int)strtotime($ym . '8'),
            'entry_end' => (int)strtotime($ym . '15 18:00:00'),
            'battle_start' => (int)strtotime($ym . '15 19:00:00'),
        ];
        // 计算当前第几轮 并更新 battle_cd（round_now）
        $c['max_round'] = self::maxRound($c['phase']);
        $round = $c['max_round'] * 2;
        $battleTime = $c['battle_start'];
        while ($now >= $battleTime && $round > 1) {
            $battleTime += $round > 32 ? self::BATTLE_INTERVAL : self::BATTLE_INTERVAL * 3;
            $round = intdiv($round, 2);
        }
        $c['round'] = $round;
        $c['battle_cd'] = $battleTime - $now;
        $c['end_time'] = self::endTime($c['phase'], $c['max_round'], $c['battle_start']);
        $c['stage'] = match (true) {
            $now < $c['entry_start'] => 1,
            $now < $c['entry_end'] => 2,
            $now < $c['battle_start'] => 5,
            $now < $c['end_time'] => 3,
            default => 4,
        };
        return $c;
    }

    /**
     * index：各阶段信息
     *
     * @return array<string, mixed>
     */
    public static function index(int $uid, int $serverid): array
    {
        $c = self::clock();
        $status = 1;
        $cd = 0;
        $palace = 1;
        $info = [];
        if ($c['stage'] === 1) {
            // 活动未开启
            $cd = $c['entry_start'] - $c['now'];
        } elseif ($c['stage'] === 2) {
            // 报名阶段
            $cd = $c['entry_end'] - $c['now'];
            $info = [
                'member_list' => self::entryMember($c['phase'], self::ENTRY_SHOW),
                'limit_level' => self::LIMIT_LEVEL,
                // true 已报名; false 未报名
                'is_entry' => self::isEntry($c['phase'], $serverid, $uid),
            ];
        } elseif ($c['stage'] === 5) {
            // 战斗准备
            $cd = $c['battle_start'] - $c['now'];
        } elseif ($c['stage'] === 3) {
            // 战斗阶段
            [$roundStage, $cd] = self::roundStage($c);
            // 判断玩家是否报名，且未战败
            $isEntry = self::isEntry($c['phase'], $serverid, $uid, true) ? 1 : 0;
            if ($isEntry) {
                // 参赛玩家，且未出局，查询战斗场次
                $play = self::playByMember($c['phase'], $serverid, $uid, $c['round']);
            } else {
                // 观战玩家 查询关注的战斗场次
                $play = self::matchRound($c['phase'], $serverid, $uid, $c['round']);
                if (($play['support_side'] ?? 0) === 1) {
                    // 支持了左方
                    $isEntry = -1;
                } elseif (($play['support_side'] ?? 0) === 2) {
                    // 支持了右方
                    $isEntry = -2;
                }
            }
            // 旧版这里查不到场次（本轮对阵还没生成）时 $play 为空、后面读出 null 继续；这里按全 0 的场次处理
            $play += ['id' => 0, 'serverid1' => 0, 'uid1' => 0, 'serverid2' => 0, 'uid2' => 0, 'support1' => 0, 'support2' => 0, 'win1' => 0, 'win2' => 0, 'win3' => 0, 'round' => 0];
            $play['win' . $roundStage] = 0;
            $reward = self::rewardByRound($play['round']);
            $leftReport = $rightReport = [];
            foreach (self::reportListByMember($c['phase'], $play['serverid1'], $play['uid1'], $c['round']) as $value) {
                if ($value['round'] === $c['round'] && $value['stage'] === $roundStage) {
                    continue;
                }
                $leftReport[] = $value;
            }
            if ($play['serverid2'] && $play['uid2']) {
                // 右方不论空
                foreach (self::reportListByMember($c['phase'], $play['serverid2'], $play['uid2'], $c['round']) as $value) {
                    if ($value['round'] === $c['round'] && $value['stage'] === $roundStage) {
                        continue;
                    }
                    $rightReport[] = $value;
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
                if ($play['win' . $i] === 0) {
                    continue;
                }
                if ($play['win' . $i] === 1) {
                    $thePlay['left']['win']++;
                } else {
                    $thePlay['right']['win']++;
                }
            }
            $info = [
                // 1 参赛；0 观战；-1 / -2 支持了左方 / 右方
                'entry' => $isEntry,
                'play' => $thePlay,
                'reward' => $reward,
                'report' => ['left' => $leftReport, 'right' => $rightReport],
                'round' => $play['round'],
                'roundid' => $play['id'],
                'stage' => $roundStage,
                'max_round' => $c['max_round'],
            ];
        } else {
            // 活动结束：取消 cd，更新战神殿
            $cd = 0;
            self::updatePalace($c['phase'], $c['max_round']);
            $palace = 1;
            $info = [
                'entry' => self::isEntry($c['phase'], $serverid, $uid),
                'list' => self::rankList($c['phase']),
            ];
        }
        $info['limit_level'] = self::LIMIT_LEVEL;
        return ['status' => $status, 'stage' => $c['stage'], 'palace' => $palace, 'cd' => $cd + 5, 'info' => $info];
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
        // 报名（期数 + 服务器 + uid 唯一，并发重复报名只有一个成功）
        $inserted = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_battle_god_entry` (`phase`, `serverid`, `uid`, `nickname`, `gender`, `dateline`) VALUES (:phase, :serverid, :uid, :nickname, :gender, :now)',
            ['phase' => $c['phase'], 'serverid' => $serverid, 'uid' => $uid, 'nickname' => $nickname, 'gender' => $gender, 'now' => $c['now']]
        );
        if ($inserted !== 1) {
            // 已报名
            return ['status' => -4];
        }
        return ['status' => 1, 'list' => self::entryMember($c['phase'], self::ENTRY_SHOW)];
    }

    /**
     * sync_battle_info：同步玩家战斗信息
     *
     * status：-3 报名未结束；-1 未报名；-2 同步过于频繁（80 秒）；-4 获取信息失败；-5 保存信息失败；1 成功
     * （等级或国家变化时 info 为新的 level / country）
     *
     * @return array{status: int, info: array<string, mixed>}
     */
    public static function syncBattleInfo(int $uid, int $serverid): array
    {
        $c = self::clock();
        $return = ['status' => 0, 'info' => []];
        if ($c['now'] < $c['entry_end']) {
            // 报名未结束，不能同步数据
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
     * status：-1 关闭支持（海选开打前 1 分钟内、决赛圈开打前 11 分钟内）或参数不正确；-2 已经支持过；1 成功
     *
     * @return array{status: int}
     */
    public static function support(int $side, int $serverid, int $uid): array
    {
        $c = self::clock();
        if ($c['round'] >= 32 && $c['battle_cd'] < 60) {
            return ['status' => -1];
        }
        if ($c['round'] < 32 && $c['battle_cd'] < 660) {
            return ['status' => -1];
        }
        if ($side !== 1 && $side !== 2) {
            return ['status' => -1];
        }
        $play = self::matchRound($c['phase'], $serverid, $uid, $c['round']);
        $toserverid = $touid = 0;
        if ($side === 1) {
            $toserverid = (int)($play['serverid1'] ?? 0);
            $touid = (int)($play['uid1'] ?? 0);
        } else {
            $toserverid = (int)($play['serverid2'] ?? 0);
            $touid = (int)($play['uid2'] ?? 0);
        }
        if ($touid === 0 || ($play['support_side'] ?? 0) !== 0) {
            // 已经支持过
            return ['status' => -2];
        }
        // 支持（以 side = 0 为条件更新关注记录，并发只成功一次）
        $updated = DB::table('battle_god_support')->where('phase', $c['phase'])->where('serverid', $serverid)->where('uid', $uid)
            ->where('roundid', (int)$play['id'])->where('side', 0)->whereNull('deleted_at')
            ->update(['side' => $side, 'toserverid' => $toserverid, 'touid' => $touid]);
        if ($updated !== 1) {
            return ['status' => -2];
        }
        DB::execute(
            'UPDATE `zc_sanguo_battle_god_round` SET `support' . $side . '` = `support' . $side . '` + 1 WHERE `id` = :id',
            ['id' => (int)$play['id']]
        );
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
        $roundStage = $c['battle_cd'] > self::BATTLE_INTERVAL * 2 ? 1 : ($c['battle_cd'] > self::BATTLE_INTERVAL ? 2 : 3);
        if ($id === 0) {
            return ['status' => -1];
        }
        $row = DB::table('battle_god_round')->select('support1', 'support2')->where('id', $id)->whereNull('deleted_at')->first();
        $support = ['left' => (int)($row['support1'] ?? 0), 'right' => (int)($row['support2'] ?? 0)];
        foreach ($support as $key => $value) {
            $support[$key] = self::supportUp($c, $value, $roundStage);
        }
        return ['status' => 1, 'support' => $support];
    }

    /**
     * get_report_list：某个参赛玩家本期的历程
     *
     * status：-1 不是本期参赛玩家；1 成功（info：uid / serverid / nickname / gender / level / country / round 名次；list 为每一局）
     *
     * @return array<string, mixed>
     */
    public static function reportList(int $serverid, int $uid): array
    {
        $c = self::clock();
        $info = self::entryRow($c['phase'], $serverid, $uid);
        if ($info === null) {
            return ['status' => -1];
        }
        $infoArr = [];
        foreach (['uid', 'serverid', 'nickname', 'gender', 'level', 'country'] as $field) {
            $infoArr[$field] = $info[$field];
        }
        $infoArr['round'] = $info['goout'] < 2 ? $info['goout'] + 1 : $info['goout'] * 2;
        // 旧版 $this->_round = 0（debug）：取全部轮次
        return ['status' => 1, 'list' => self::reportListByMember($c['phase'], $serverid, $uid, 0), 'info' => $infoArr];
    }

    /**
     * get_reward_list（god_reward 旧版数据 0 行，没有奖励时回 -1）
     *
     * @return array<string, mixed>
     */
    public static function rewardList(int $serverid, int $uid, int $type): array
    {
        // 依赖未移植：奖励规则（battle 库 god_reward 在旧版数据里 0 行，get_my_reward_list 无从还原，按没有奖励处理）
        return ['status' => -1];
    }

    /**
     * get_reward（同上，一律领取失败 -1）
     *
     * @return array<string, mixed>
     */
    public static function reward(int $serverid, int $uid, int $type, int $id): array
    {
        // 依赖未移植：奖励规则（同 rewardList）
        return ['status' => -1];
    }

    /**
     * get_report_info：一局的战报（report 为战报行，其中 report 为战斗过程）
     *
     * @return array<string, mixed>
     */
    public static function reportInfo(int $id, int $stage): array
    {
        $row = DB::table('battle_god_report')->select('id', 'stage', 'report', 'nickname', 'userlevel', 'name', 'level', 'background', 'god')
            ->where('id', $id)->where('stage', $stage)->whereNull('deleted_at')->first();
        if ($row === null) {
            return ['status' => 0];
        }
        return ['status' => 1, 'report' => self::reportRow($row)];
    }

    /**
     * get_palace：战帝堂（chapter => 行，带 report_list）；没有战帝回 -1
     *
     * @return array<string, mixed>
     */
    public static function palaceList(int $page): array
    {
        $rows = DB::table('battle_god_palace')->select('chapter', 'phase', 'name', 'serverid', 'uid', 'nickname', 'round')
            ->whereNull('deleted_at')->orderBy('chapter')->get();
        if ($rows === []) {
            return ['status' => -1, 'list' => []];
        }
        $list = [];
        foreach ($rows as $row) {
            $row = array_map(static fn (mixed $v): mixed => is_numeric($v) ? (int)$v : $v, $row);
            $row['report_list'] = [];
            $list[$row['chapter']] = $row;
        }
        $logs = DB::table('battle_god_palace_report_log')->select('chapter', 'round', 'stage')->whereIn('chapter', array_keys($list))
            ->whereNull('deleted_at')->orderBy('id')->get();
        foreach ($logs as $value) {
            $list[(int)$value['chapter']]['report_list'][] = ['chapter' => (int)$value['chapter'], 'round' => (int)$value['round'], 'stage' => (int)$value['stage']];
        }
        return ['status' => 1, 'list' => $list];
    }

    /**
     * get_palace_report：战帝战报
     *
     * @return array<string, mixed>
     */
    public static function palaceReport(int $chapter, int $round, int $stage): array
    {
        $log = DB::table('battle_god_palace_report_log')->select('id')->where('chapter', $chapter)->where('round', $round)->where('stage', $stage)
            ->whereNull('deleted_at')->orderBy('id')->first();
        $row = $log === null ? null : DB::table('battle_god_palace_report')
            ->select('id', 'stage', 'report', 'nickname', 'userlevel', 'name', 'level', 'background')->where('id', (int)$log['id'])->whereNull('deleted_at')->first();
        if ($row === null) {
            return ['status' => -1];
        }
        return ['status' => 1, 'info' => self::reportRow($row)];
    }

    /**
     * get_god_info：当前战帝（最近一世；title 为帝号）
     *
     * @return array<string, mixed>
     */
    public static function godInfo(): array
    {
        $row = DB::table('battle_god_palace')->select('chapter', 'phase', 'name', 'serverid', 'uid', 'nickname', 'round')
            ->whereNull('deleted_at')->orderBy('chapter', 'DESC')->first();
        if ($row === null) {
            return ['status' => 0, 'god' => []];
        }
        $god = array_map(static fn (mixed $v): mixed => is_numeric($v) ? (int)$v : $v, $row);
        $god['title'] = $god['name'];
        return ['status' => 1, 'god' => $god];
    }

    // ---------------------------------------------------------------------------------------------
    // 计划任务（battle god->check_pk，每分钟）
    // ---------------------------------------------------------------------------------------------

    /**
     * check_pk：报名结束后 1 分钟内同步全部报名玩家并生成第一轮对阵；战斗阶段每轮开打前 1 分钟算出本轮（本局）全部战斗，
     * 一轮打完生成下一轮对阵，决赛打完更新战帝堂
     *
     * 旧版按 p / w 分多个进程计算海选并轮询等待；进程内按顺序算完，不需要等待。每轮（局）只算一次（Redis 标记，
     * 定时器在 1 分钟窗口里多跑一次也不会重复打）。
     *
     * @return int 本次算了几场
     */
    public static function checkPk(int $now): int
    {
        $c = self::clock($now);
        if ($c['stage'] === 5 && $now - $c['entry_end'] < 60 && self::once($c['phase'], 'fetch_first')) {
            // 同步全部报名玩家（level 为 0 即还没同步过的），生成第一轮对阵
            foreach (DB::table('battle_god_entry')->select('serverid', 'uid', 'level')->where('phase', $c['phase'])->whereNull('deleted_at')->get() as $value) {
                if ((int)$value['level'] === 0) {
                    self::doSync($c, (int)$value['serverid'], (int)$value['uid']);
                }
            }
            self::fetchRound($c['phase']);
            $c = self::clock($now);
        }
        if ($c['stage'] !== 3 || $c['round'] > $c['max_round']) {
            return 0;
        }
        $battle = $fetch = false;
        $stage = 1;
        if ($c['round'] >= 32) {
            // 计算海选战斗
            if ($c['battle_cd'] <= 60) {
                $battle = $fetch = true;
            }
        } else {
            [$stage, $cd] = self::roundStage($c);
            if ($cd <= 60) {
                // 计算决赛圈战斗
                $battle = true;
                $fetch = $c['battle_cd'] <= 60;
            }
        }
        if (!$battle || !self::once($c['phase'], $c['round'] . '_' . $stage)) {
            return 0;
        }
        $num = 0;
        foreach (DB::table('battle_god_round')->select('id')->where('phase', $c['phase'])->where('round', $c['round'])->whereNull('deleted_at')->orderBy('id')->get() as $value) {
            self::pk((int)$value['id'], $c);
            $num++;
        }
        if ($fetch) {
            self::fetchRound($c['phase']);
            if ($c['round'] === 1) {
                // 更新战神殿
                self::updatePalace($c['phase'], $c['max_round']);
            }
        }
        return $num;
    }

    /**
     * 单场战斗（_pk）：海选一局定胜负；决赛圈每次打一局（第一局 / 第二局 / 决胜局），决出胜负后记胜方、更新支持表、败方出局
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
        $data = $supportData = [];
        $pkresult = null;
        if ($play['uid2']) {
            // 双方有人
            $ready = self::pkReady($play);
            // 判断先手：支持数多的一方先手（相同时左方）
            $first = $play['support1'] >= $play['support2'] ? 1 : 2;
            if ($play['round'] >= 32) {
                // 海选赛
                if ($play['win1'] === 0) {
                    if ($ready['status'] === -1) {
                        // 玩家信息不全 尝试获取
                        if ($ready['left'] === null) {
                            $team = self::memberTeam($play['uid1']);
                            if ($team !== null) {
                                self::saveBattleInfo($play['uid1'], $play['serverid1'], $team, $c['now']);
                                $ready['left'] = $team;
                            }
                        }
                        if ($ready['right'] === null) {
                            $team = self::memberTeam($play['uid2']);
                            if ($team !== null) {
                                self::saveBattleInfo($play['uid2'], $play['serverid2'], $team, $c['now']);
                                $ready['right'] = $team;
                            }
                        }
                    }
                    if ($ready['left'] !== null && $ready['right'] !== null) {
                        if ($first === 1) {
                            $pkresult = self::battle($ready['left'], $ready['right']);
                        } else {
                            $pkresult = self::battle($ready['right'], $ready['left']);
                            $swapReport = true;
                        }
                        $theWin = self::pkResult($pkresult['assess'], $first);
                        $data = ['win1' => $theWin, 'win' => $theWin];
                        $supportData = ['win' => $theWin];
                        $reportStage = 1;
                    } else {
                        // 信息不全
                        $status = -1;
                    }
                } else {
                    // 已经战斗过
                    $status = -2;
                }
            } elseif ($ready['left'] === null || $ready['right'] === null) {
                // 决赛圈（旧版不检查快照，缺了就用空队伍打；这里按信息不全返回，免得写出空战报）
                $status = -1;
            } else {
                // 决赛圈：第几场战斗
                if ($play['win1'] === 0) {
                    // 第一场战斗
                    if ($first === 1) {
                        $pkresult = self::battle($ready['left'], $ready['right']);
                    } else {
                        $pkresult = self::battle($ready['right'], $ready['left']);
                        $swapReport = true;
                    }
                    $data = ['win1' => self::pkResult($pkresult['assess'], $first)];
                    $reportStage = 1;
                } elseif ($play['win2'] === 0) {
                    // 第二场战斗：换后手方先手
                    if ($first === 1) {
                        $pkresult = self::battle($ready['right'], $ready['left']);
                        $swapReport = true;
                    } else {
                        $pkresult = self::battle($ready['left'], $ready['right']);
                    }
                    $data = ['win2' => self::pkResult($pkresult['assess'], 3 - $first)];
                    $reportStage = 2;
                    if ($data['win2'] === $play['win1']) {
                        $playWin = $play['win1'];
                    }
                } elseif ($play['win1'] !== $play['win2'] && $play['win3'] === 0) {
                    // 第三场战斗
                    if ($first === 1) {
                        $pkresult = self::battle($ready['left'], $ready['right']);
                    } else {
                        $pkresult = self::battle($ready['right'], $ready['left']);
                        $swapReport = true;
                    }
                    $data = ['win3' => self::pkResult($pkresult['assess'], $first)];
                    $reportStage = 3;
                    // 获胜方
                    $playWin = $data['win3'];
                }
                if ($playWin) {
                    // 决出胜负：修改 round 表与 support 表
                    $data['win'] = $playWin;
                    $supportData = ['win' => $playWin];
                }
            }
            if ($reportStage && $pkresult !== null) {
                // 记录战报
                $members = self::memberPair($c['phase'], $play);
                // 调换战报攻守方位置（战报左方始终是场次的左方）
                if ($swapReport) {
                    $pkresult = self::swapReport($pkresult);
                }
                $pkresult['up'] = 0;
                if ($supportData !== []) {
                    if ($play['round'] === 1) {
                        $pkresult['up'] = 2;
                        $pkresult['round'] = 1;
                        // 决赛打完：活动结束时间为现在
                        self::setEndTime($c['phase'], $c['now']);
                    } else {
                        $pkresult['up'] = 1;
                        $pkresult['round'] = $play['round'];
                    }
                }
                self::insertReport($roundid, $reportStage, $pkresult, $members['left'], $members['right']);
            }
        } else {
            // 右方没人，左方轮空（只可能在海选赛中出现）
            if ($play['win1'] === 0) {
                $data = ['win1' => 1, 'win' => 1];
                $supportData = ['win' => 1];
            } else {
                $status = -2;
            }
        }
        if ($data !== []) {
            // 记录战斗结果
            DB::table('battle_god_round')->where('id', $roundid)->update($data);
            $status = 1;
        }
        if ($supportData !== []) {
            // 修改支持表
            DB::table('battle_god_support')->where('roundid', $roundid)->whereNull('deleted_at')->update($supportData);
            // 修改参赛者信息：败方出局（记出局的轮次）
            [$loseServer, $loseUid] = $supportData['win'] === 1 ? [$play['serverid2'], $play['uid2']] : [$play['serverid1'], $play['uid1']];
            DB::table('battle_god_entry')->where('phase', $c['phase'])->where('serverid', $loseServer)->where('uid', $loseUid)->whereNull('deleted_at')
                ->update(['goout' => $play['round']]);
        }
        return ['status' => $status];
    }

    // ---------------------------------------------------------------------------------------------

    /**
     * 决赛圈当前是第几局与本局倒计时；海选（32 及以上）为第 1 局、倒计时为 battle_cd
     *
     * @param array<string, mixed> $c
     * @return array{0: int, 1: int}
     */
    private static function roundStage(array $c): array
    {
        if ($c['round'] >= 32) {
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
     * get_support_up：显示的支持数 = (16 - 轮次位数) × 支持数 + 本局已过时间的比例
     *
     * @param array<string, mixed> $c
     */
    private static function supportUp(array $c, int $support, int $stage): int
    {
        if (!$support) {
            return 0;
        }
        if ($stage === 1 && $c['round'] < 32) {
            $cd = $c['battle_cd'] - self::BATTLE_INTERVAL * 2;
        } elseif ($stage === 1) {
            $cd = $c['battle_cd'];
        } else {
            $cd = 0;
        }
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
     * 第一轮的场数 / 本期最大轮次（max_round）
     */
    private static function maxRound(int $phase): int
    {
        $max = DB::table('battle_god_round')->where('phase', $phase)->whereNull('deleted_at')->max('round');
        if ($max !== null && (int)$max > 0) {
            return (int)$max;
        }
        $n = (int)DB::table('battle_god_entry')->where('phase', $phase)->whereNull('deleted_at')->count();
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
     * get_end_time：活动结束时间（Redis 缓存）。最大轮次大于 2 且没有算过时按轮次算出并缓存，否则取缓存（没有为 0：
     * 4 人以下的比赛 19 点即结束，旧版如此）
     */
    private static function endTime(int $phase, int $max, int $battleStart): int
    {
        $time = (int)(Redis::get(self::END_TIME_KEY . $phase) ?? 0);
        if ($max > 2 && $time <= $battleStart) {
            $endTime = $battleStart;
            $max *= 2;
            while (intdiv($max, 2) >= 1) {
                $endTime += $max > 32 ? self::BATTLE_INTERVAL : self::BATTLE_INTERVAL * 3;
                $max = intdiv($max, 2);
            }
            self::setEndTime($phase, $endTime);
            return $endTime;
        }
        return $time;
    }

    private static function setEndTime(int $phase, int $time): void
    {
        Redis::set(self::END_TIME_KEY . $phase, $time, 86400 * 40);
    }

    /**
     * 每期每个任务只做一次（计划任务的去重标记）
     */
    private static function once(int $phase, string $name): bool
    {
        $redis = Redis::instance();
        return $redis !== null && (bool)$redis->set(self::PK_DONE_KEY . $phase . '_' . $name, '1', ['nx', 'ex' => 86400 * 40]);
    }

    /**
     * fetch_round：生成下一轮对阵，返回当前（新）轮次
     */
    private static function fetchRound(int $phase): int
    {
        return Server::transaction(static function () use ($phase): int {
            DB::query('SELECT GET_LOCK(\'battle_god_fetch\', 10)');
            try {
                $min = DB::table('battle_god_round')->where('phase', $phase)->whereNull('deleted_at')->min('round');
                if ($min === null) {
                    // 第一轮：报名玩家随机排开
                    $entries = DB::table('battle_god_entry')->select('serverid', 'uid')->where('phase', $phase)->whereNull('deleted_at')->orderBy('id')->get();
                    $m = self::maxRound($phase);
                    if ($m === 0) {
                        return 0;
                    }
                    shuffle($entries);
                    for ($i = 0; $i < $m; $i++) {
                        self::insertRound($phase, $m, $entries[$i] ?? null, $entries[$i + $m] ?? null);
                    }
                    return $m;
                }
                $round = (int)$min;
                if ($round <= 1) {
                    return $round;
                }
                $rows = DB::table('battle_god_round')->select('id', 'serverid1', 'uid1', 'serverid2', 'uid2', 'win')->where('phase', $phase)
                    ->where('round', $round)->whereNull('deleted_at')->orderBy('id')->get();
                $winners = [];
                foreach ($rows as $row) {
                    if ((int)$row['win'] === 0) {
                        // 本轮还没打完
                        return $round;
                    }
                    $side = (int)$row['win'];
                    $winners[] = ['serverid' => (int)$row['serverid' . $side], 'uid' => (int)$row['uid' . $side]];
                }
                $next = intdiv($round, 2);
                for ($i = 0; $i < $next; $i++) {
                    self::insertRound($phase, $next, $winners[$i * 2] ?? null, $winners[$i * 2 + 1] ?? null);
                }
                return $next;
            } finally {
                DB::query('SELECT RELEASE_LOCK(\'battle_god_fetch\')');
            }
        });
    }

    /**
     * @param array<string, mixed>|null $left
     * @param array<string, mixed>|null $right
     */
    private static function insertRound(int $phase, int $round, ?array $left, ?array $right): void
    {
        DB::table('battle_god_round')->insert([
            'phase' => $phase,
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
        $query = DB::table('battle_god_entry')->where('phase', $phase)->where('serverid', $serverid)->where('uid', $uid)->whereNull('deleted_at');
        if ($alive) {
            $query->where('goout', 0);
        }
        return $query->value('id') !== null;
    }

    /**
     * get_entry_member：最近报名的玩家昵称
     *
     * @return list<array{nickname: string}>
     */
    private static function entryMember(int $phase, int $limit): array
    {
        $rows = DB::table('battle_god_entry')->select('nickname')->where('phase', $phase)->whereNull('deleted_at')->orderBy('id', 'DESC')->limit($limit)->get();
        return array_map(static fn (array $r): array => ['nickname' => (string)$r['nickname']], $rows);
    }

    /**
     * get_member：本期报名行
     *
     * @return array<string, mixed>|null
     */
    private static function entryRow(int $phase, int $serverid, int $uid): ?array
    {
        $row = DB::table('battle_god_entry')->select('id', 'phase', 'serverid', 'uid', 'nickname', 'gender', 'level', 'countryid', 'country', 'goout', 'dateline')
            ->where('phase', $phase)->where('serverid', $serverid)->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row === null) {
            return null;
        }
        foreach (['id', 'phase', 'serverid', 'uid', 'gender', 'level', 'countryid', 'goout', 'dateline'] as $field) {
            $row[$field] = (int)$row[$field];
        }
        $row['nickname'] = (string)$row['nickname'];
        $row['country'] = (string)$row['country'];
        return $row;
    }

    /**
     * _sync_battle_info：取玩家当前的队伍快照，等级或国家变了同时更新报名表
     *
     * status：-1 获取信息失败（玩家不存在）；-2 保存信息失败；1 成功
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
        // 从游戏服务器获取数据（s1 uc_worldarena->init_member_team_info，god = 1）
        $base = DB::table('member')->select('uid', 'nickname', 'level', 'country')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($base === null) {
            $return['status'] = -1;
            return $return;
        }
        $team = self::memberTeam($uid) ?? [];
        $countryid = (int)$base['country'];
        $countryName = $countryid > 0 ? (string)(DB::table('country')->where('id', $countryid)->whereNull('deleted_at')->value('name') ?? '') : '';
        $member = self::entryRow($c['phase'], $serverid, $uid);
        if ($member !== null && ($member['level'] !== (int)$base['level'] || $member['countryid'] !== $countryid)) {
            // 用户等级 或 国家 id 发生改变的时候才更新 god_entry 表
            $data = ['level' => (int)$base['level'], 'countryid' => $countryid, 'country' => $countryName, 'dateline' => $c['now']];
            DB::table('battle_god_entry')->where('phase', $c['phase'])->where('serverid', $serverid)->where('uid', $uid)->whereNull('deleted_at')->update($data);
            $return['info'] = ['level' => $data['level'], 'country' => $data['country']];
        }
        // 保存战斗数据
        if (self::saveBattleInfo($uid, $serverid, $team, $c['now']) !== 1) {
            $return['status'] = -2;
            return $return;
        }
        $return['status'] = 1;
        return $return;
    }

    /**
     * 玩家的队伍快照（init_member_team_info：防守方、非本人视角）；玩家不存在为 null
     *
     * @return array<string, mixed>|null
     */
    private static function memberTeam(int $uid): ?array
    {
        if (DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('uid') === null) {
            return null;
        }
        $team = TeamBuilder::forMember($uid, defense: true, self: false);
        return ['team' => $team['team'], 'matrix_type' => $team['matrix_type'], 'matrix_coe' => $team['matrix_coe']];
    }

    /**
     * save_battle_info：保存战斗数据（god_member）
     *
     * @param array<string, mixed> $data
     */
    private static function saveBattleInfo(int $uid, int $serverid, array $data, int $now): int
    {
        DB::execute(
            'INSERT INTO `zc_sanguo_battle_god_member` (`serverid`, `uid`, `data`, `dateline`) VALUES (:serverid, :uid, :data, :now)
             ON DUPLICATE KEY UPDATE `data` = VALUES(`data`), `dateline` = VALUES(`dateline`), `deleted_at` = NULL',
            ['serverid' => $serverid, 'uid' => $uid, 'data' => (string)json_encode($data), 'now' => $now]
        );
        return 1;
    }

    /**
     * pk_ready：双方的战斗数据；任一方没有时 status -1
     *
     * @param array<string, mixed> $play
     * @return array{status: int, left: array<string, mixed>|null, right: array<string, mixed>|null}
     */
    private static function pkReady(array $play): array
    {
        $left = self::battleData($play['serverid1'], $play['uid1']);
        $right = self::battleData($play['serverid2'], $play['uid2']);
        return ['status' => $left === null || $right === null ? -1 : 1, 'left' => $left, 'right' => $right];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function battleData(int $serverid, int $uid): ?array
    {
        $json = DB::table('battle_god_member')->where('serverid', $serverid)->where('uid', $uid)->whereNull('deleted_at')->value('data');
        $data = $json === null ? null : json_decode((string)$json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * battle_model->battle_pk(左方数据, 右方数据)：左方为攻方
     *
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     * @return array<string, mixed>
     */
    private static function battle(array $left, array $right): array
    {
        $engine = new BattleEngine((int)$left['matrix_type'], (float)$left['matrix_coe'], (int)$right['matrix_type'], (float)$right['matrix_coe']);
        return $engine->battle(self::intKeys($left['team']), self::intKeys($right['team']));
    }

    /**
     * JSON 解出来的站位键还原为整数
     *
     * @param array<int|string, mixed> $team
     * @return array<int, mixed>
     */
    private static function intKeys(array $team): array
    {
        $return = [];
        foreach ($team as $pos => $unit) {
            $return[(int)$pos] = $unit;
        }
        return $return;
    }

    /**
     * swap_report：调换战报攻守方位置（左右队伍、结果互换，出手方 launch 互换，评级取反）
     *
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    public static function swapReport(array $report): array
    {
        $new = [];
        foreach ($report as $key => $value) {
            if ($key === 'leftteam') {
                $new['rightteam'] = $value;
            } elseif ($key === 'rightteam') {
                $new['leftteam'] = $value;
            } elseif ($key === 'leftresult') {
                $new['rightresult'] = $value;
            } elseif ($key === 'rightresult') {
                $new['leftresult'] = $value;
            } elseif ($key === 'process') {
                foreach ($value as $k => $v) {
                    $value[$k]['launch'] = $v['launch'] === 'left' ? 'right' : 'left';
                }
                $new['process'] = $value;
            } elseif ($key === 'assess') {
                $new['assess'] = 0 - $value;
            }
            // 其他键（leftbuff / rightbuff）旧版丢掉
        }
        return $new;
    }

    /**
     * _get_pk_result：按先手方的评级换算成场次的胜方（1 左方、2 右方）
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
        $row = DB::table('battle_god_round')->select('id', 'phase', 'serverid1', 'serverid2', 'uid1', 'uid2', 'support1', 'support2', 'win1', 'win2', 'win3', 'win', 'round', 'follow')
            ->where('id', $id)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * get_play_by_member：参赛玩家本轮的场次
     *
     * @return array<string, int>
     */
    private static function playByMember(int $phase, int $serverid, int $uid, int $round): array
    {
        $row = DB::table('battle_god_round')->select('id')->where('phase', $phase)->where('round', $round)->whereNull('deleted_at')
            ->whereRaw('((`serverid1` = ? AND `uid1` = ?) OR (`serverid2` = ? AND `uid2` = ?))', [$serverid, $uid, $serverid, $uid])->first();
        return $row === null ? [] : (self::playById((int)$row['id']) ?? []);
    }

    /**
     * match_round：观战玩家本轮关注的场次（support_side 为已支持的一方，0 未支持）；本轮还没有对阵为空数组
     *
     * @return array<string, int>
     */
    private static function matchRound(int $phase, int $serverid, int $uid, int $round): array
    {
        $support = DB::table('battle_god_support')->select('roundid', 'side')->where('phase', $phase)->where('round', $round)
            ->where('serverid', $serverid)->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($support === null) {
            $ids = DB::table('battle_god_round')->select('id')->where('phase', $phase)->where('round', $round)->whereNull('deleted_at')->orderBy('id')->get();
            if ($ids === []) {
                return [];
            }
            $roundid = (int)$ids[array_rand($ids)]['id'];
            $inserted = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_battle_god_support` (`phase`, `serverid`, `uid`, `roundid`, `side`, `round`) VALUES (:phase, :serverid, :uid, :roundid, 0, :round)',
                ['phase' => $phase, 'serverid' => $serverid, 'uid' => $uid, 'roundid' => $roundid, 'round' => $round]
            );
            if ($inserted === 1) {
                DB::execute('UPDATE `zc_sanguo_battle_god_round` SET `follow` = `follow` + 1 WHERE `id` = :id', ['id' => $roundid]);
            }
            $support = DB::table('battle_god_support')->select('roundid', 'side')->where('phase', $phase)->where('round', $round)
                ->where('serverid', $serverid)->where('uid', $uid)->whereNull('deleted_at')->first();
        }
        $play = self::playById((int)$support['roundid']) ?? [];
        $play['support_side'] = (int)$support['side'];
        return $play;
    }

    /**
     * get_report_list_by_member：玩家本期轮次不小于 $round 的每一局（id 场次、stage 第几局、round 轮次、nickname 对手、side 对手所在的一方）
     *
     * @return list<array{id: int, stage: int, round: int, nickname: string, side: int}>
     */
    private static function reportListByMember(int $phase, int $serverid, int $uid, int $round): array
    {
        if ($uid === 0) {
            return [];
        }
        $query = DB::table('battle_god_round')->select('id', 'serverid1', 'uid1', 'serverid2', 'uid2', 'round')->where('phase', $phase)->whereNull('deleted_at')
            ->whereRaw('((`serverid1` = ? AND `uid1` = ?) OR (`serverid2` = ? AND `uid2` = ?))', [$serverid, $uid, $serverid, $uid]);
        if ($round > 0) {
            $query->where('round', '>=', $round);
        }
        $rounds = $query->orderBy('round', 'DESC')->get();
        $list = [];
        foreach ($rounds as $row) {
            $isLeft = (int)$row['uid1'] === $uid && (int)$row['serverid1'] === $serverid;
            $opponentServer = $isLeft ? (int)$row['serverid2'] : (int)$row['serverid1'];
            $opponentUid = $isLeft ? (int)$row['uid2'] : (int)$row['uid1'];
            $opponent = $opponentUid > 0 ? self::entryRow($phase, $opponentServer, $opponentUid) : null;
            $reports = DB::table('battle_god_report')->select('stage')->where('id', (int)$row['id'])->whereNull('deleted_at')->orderBy('stage')->get();
            foreach ($reports as $report) {
                $list[] = [
                    'id' => (int)$row['id'],
                    'stage' => (int)$report['stage'],
                    'round' => (int)$row['round'],
                    'nickname' => (string)($opponent['nickname'] ?? ''),
                    'side' => $isLeft ? 2 : 1,
                ];
            }
        }
        return $list;
    }

    /**
     * get_member_info（两方）：serverid / uid / nickname / level / gender / countryid / country；uid 为 0 的一方为空信息
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
     * insert_report：一局的战报
     *
     * @param array<string, mixed> $report
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private static function insertReport(int $roundid, int $stage, array $report, array $left, array $right): void
    {
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_battle_god_report` (`id`, `stage`, `report`, `nickname`, `userlevel`, `name`, `level`) VALUES (:id, :stage, :report, :nickname, :userlevel, :name, :level)',
            [
                'id' => $roundid,
                'stage' => $stage,
                'report' => (string)json_encode($report, JSON_UNESCAPED_UNICODE),
                'nickname' => (string)$left['nickname'],
                'userlevel' => (int)$left['level'],
                'name' => (string)$right['nickname'],
                'level' => (int)$right['level'],
            ]
        );
    }

    /**
     * 战报行：report 解成数组，数字列转整数
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function reportRow(array $row): array
    {
        $report = json_decode((string)$row['report'], true);
        $row = array_map(static fn (mixed $v): mixed => is_numeric($v) ? (int)$v : $v, $row);
        $row['report'] = is_array($report) ? $report : [];
        return $row;
    }

    /**
     * get_reward_by_round：本轮胜利奖励（god_reward 旧版 0 行，恒为空）
     *
     * @return array<string, mixed>
     */
    private static function rewardByRound(int $round): array
    {
        return [];
    }

    /**
     * get_rank_list：本期 32 强（出局轮次 16 以内，冠军 goout 为 0），按名次排列；round 为名次（1 冠军、2 亚军、4 四强 …）
     *
     * @return list<array{uid: int, serverid: int, nickname: string, level: int, round: int}>
     */
    private static function rankList(int $phase): array
    {
        // 还没决出冠军时 goout 为 0 的不止一人（未出局），只算已决出名次的
        $champion = self::champion($phase);
        $rows = DB::table('battle_god_entry')->select('uid', 'serverid', 'nickname', 'level', 'goout')->where('phase', $phase)
            ->where('goout', '<=', self::RANK_GOOUT)->whereNull('deleted_at')->orderBy('goout')->orderBy('id')->get();
        $list = [];
        foreach ($rows as $row) {
            $goout = (int)$row['goout'];
            if ($goout === 0 && ($champion === null || (int)$row['uid'] !== $champion['uid'] || (int)$row['serverid'] !== $champion['serverid'])) {
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

    /**
     * 本期冠军（决赛的胜方）；还没决出为 null
     *
     * @return array{serverid: int, uid: int, round_id: int}|null
     */
    private static function champion(int $phase): ?array
    {
        $final = DB::table('battle_god_round')->select('id', 'serverid1', 'uid1', 'serverid2', 'uid2', 'win')->where('phase', $phase)->where('round', 1)
            ->where('win', '>', 0)->whereNull('deleted_at')->first();
        if ($final === null) {
            return null;
        }
        $side = (int)$final['win'];
        return ['serverid' => (int)$final['serverid' . $side], 'uid' => (int)$final['uid' . $side], 'round_id' => (int)$final['id']];
    }

    /**
     * update_palace：冠军进入战帝堂（每期一次），冠军每一局的战报复制进战帝战报
     */
    private static function updatePalace(int $phase, int $maxRound): void
    {
        $champion = self::champion($phase);
        if ($champion === null) {
            return;
        }
        Server::transaction(static function () use ($phase, $maxRound, $champion): void {
            $nickname = (string)(DB::table('battle_god_entry')->where('phase', $phase)->where('serverid', $champion['serverid'])
                ->where('uid', $champion['uid'])->whereNull('deleted_at')->value('nickname') ?? '');
            $inserted = DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_battle_god_palace` (`phase`, `serverid`, `uid`, `nickname`, `round`) VALUES (:phase, :serverid, :uid, :nickname, :round)',
                ['phase' => $phase, 'serverid' => $champion['serverid'], 'uid' => $champion['uid'], 'nickname' => $nickname, 'round' => $maxRound]
            );
            if ($inserted !== 1) {
                return;
            }
            $chapter = (int)DB::table('battle_god_palace')->where('phase', $phase)->value('chapter');
            DB::table('battle_god_palace')->where('chapter', $chapter)->update(['name' => self::GOD_NAME[$chapter] ?? '']);
            foreach (self::reportListByMember($phase, $champion['serverid'], $champion['uid'], 0) as $value) {
                $report = DB::table('battle_god_report')->select('stage', 'report', 'nickname', 'userlevel', 'name', 'level', 'background')
                    ->where('id', $value['id'])->where('stage', $value['stage'])->whereNull('deleted_at')->first();
                if ($report === null) {
                    continue;
                }
                $id = (int)DB::table('battle_god_palace_report_log')->insertGetId([
                    'chapter' => $chapter,
                    'round' => $value['round'],
                    'stage' => $value['stage'],
                    'nickname' => $nickname,
                    'roundid' => $value['id'],
                ]);
                DB::table('battle_god_palace_report')->insert(['id' => $id] + $report);
                DB::table('battle_god_report')->where('id', $value['id'])->where('stage', $value['stage'])->update(['god' => 1]);
            }
        });
    }
}
