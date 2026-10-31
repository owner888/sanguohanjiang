<?php

declare(strict_types=1);

namespace Hanjiang\Cross;

use Hanjiang\Battle\BattleRush;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\Handler\Pvp\ArenaHandler;
use Hanjiang\Model\BossClock;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;
use OmniPHP\Logger;

/**
 * 群服争霸的战斗服务器部分（旧版 battle controllers/gvg.php；s1 transfer_model 的 gvg_* 请求 war_url 上的 c=gvg）
 *
 * 每月 15 日 23:00 到 22 日 00:30 为活动期：开打前由计划任务按参加跨服的服务器（当月新开、已合服的不参加）生成对阵、
 * 每个服务器每轮关注一场；18 日 19:00 开战前 3 分钟取各服演武榜前 30 为参赛玩家并同步队伍，之后每 15 分钟一轮，
 * 开打前 5 分钟锁定（不能同步、押注）并算出本轮团战，胜者进入下一轮的对应位置。
 * 单服部署下 battle 是本进程内的模块，s1 的 GvgHandler / GvgCron 直接调用这里的方法，返回值与 battle 控制器 api_return 的数组相同；
 * 表名前缀 zc_sanguo_battle_；参加跨服的服务器取 uc_server（开放中，开服时间 starttime 即 battle server 表的 opentime）。
 *
 * battle 的 gvg_model / user_model / server_model 不在旧版代码包里，按控制器、s1 调用方、客户端与表结构还原
 * （按控制器与表结构还原（旧版 model 不在代码包里））：
 * - match_team：参赛服务器随机排开，第一轮场数为不小于服务器数一半的 2 的幂，第 i 个服务器在第 i 场左方、第 i + 场数个在右方
 *   （右方轮空，客户端「只有右边会轮空」）；各轮的场次一次建好，晋级时按 _get_qualify_team 填入下一轮；
 * - match_follow：服务器本轮有比赛就关注自己那场，否则随机关注一场；已关注的不再改；
 * - get_player / pk_ready：参赛玩家按演武排名升序；任一方没有参赛玩家时信息不全；
 * - get_schedule：客户端赛程图的键 1 ~ 7（4 ~ 7 为本场所在的 4 场、2 / 3 为其上一轮的 2 场、1 为决赛，只在本组的上上轮是决赛时给出）；
 *   缺的场次按空场（双方 0）补齐，免得客户端取不到；
 * - get_round_list(8)：8 进 4 及以后的场次，按轮次、场次升序；
 * - get_top_player：各服务器参赛玩家中的最高等级；
 * - get_round_result：该服务器赢下的场次（轮空也算）；
 * - 团战按同步时保存的队伍快照（gvg_member.data，TeamBuilder::forMember 的结果）打，快照缺失时现取。
 */
final class GvgServer
{
    /** 等级限制 */
    public const int LIMIT_LEVEL = 60;

    /** 服务器演武榜资格 */
    public const int TOP = 30;

    /** 开赛倒计时 15 分钟 */
    public const int BATTLE_SPACE = 900;

    /** 锁定时间 5 分钟 */
    public const int BATTLE_LOCK = 300;

    /** 同步数据最小间隔 */
    private const int DELAY = 10;

    /** 对手显示倒计时 */
    public const int PREPARE_CD = 180;

    private const string SYNC_KEY = 'gvg_battle_is_sync_';
    private const string MATCH_TEAM_LOCK = 'gvg_match_team_lock_';
    private const string BET_LOCK = 'gvg_battle_bet_lock_';

    /**
     * 构造函数：期数、各时间点、当前轮次、倒计时与阶段
     *
     * stage：1 活动未开启；2 选手确认倒计时；3 对手显示倒计时；4 战斗中；5 战斗结束
     *
     * @return array{now: int, phase: int, open_time: int, close_time: int, battle_time: int, prepare_time: int, end_time: int, round: int, battle_cd: int, stage: int}
     */
    public static function clock(?int $now = null): array
    {
        $now ??= BossClock::now();
        $ym = date('Y-m-', $now);
        $c = [
            'now' => $now,
            'phase' => (int)date('Ym', $now),
            // 每月 16 日出现活动图标（15 日 23 点）
            'open_time' => (int)strtotime($ym . '15 23:00:00'),
            // 每月 22 日 0:30 活动图标消失
            'close_time' => (int)strtotime($ym . '22 00:30:00'),
            // 每月 18 日 19 点开战
            'battle_time' => (int)strtotime($ym . '18 19:00:00'),
        ];
        // 对手显示时间
        $c['prepare_time'] = $c['battle_time'] + self::PREPARE_CD;
        // _round_now：设置场次及开战时间
        $battleTime = $c['battle_time'];
        $max = self::maxRound($c['phase']);
        $round = $now > $battleTime ? $max * 2 : $max;
        while ($now > $battleTime && $round > 1) {
            $battleTime += self::BATTLE_SPACE;
            $round = intdiv($round, 2);
        }
        $c['round'] = $round;
        $c['battle_cd'] = $battleTime - $now;
        // _get_end_time：计算结束时间
        $endTime = $c['battle_time'];
        $r = $max * 2;
        while (intdiv($r, 2) >= 1) {
            $endTime += self::BATTLE_SPACE;
            $r = intdiv($r, 2);
        }
        $c['end_time'] = $endTime;
        $c['stage'] = match (true) {
            // 活动未开启（执行 fetch_server、match_team、match_follow）
            $now < $c['open_time'] || $now > $c['close_time'] => 1,
            // 选手确认倒计时
            $now < $c['battle_time'] => 2,
            // 对手显示倒计时（执行 fetch_member、sync_all_member）
            $now < $c['prepare_time'] => 3,
            // 战斗中（倒计时剩余 5 分钟执行 run_pk）
            $now < $c['end_time'] => 4,
            // 战斗结束
            default => 5,
        };
        return $c;
    }

    /**
     * index：群服争霸入口
     *
     * @return array{status: int, info: array<string, mixed>, stage: int, cd: int}
     */
    public static function index(int $serverid, int $uid): array
    {
        $c = self::clock();
        $status = 1;
        $cd = 0;
        $info = [];
        if ($c['stage'] === 1) {
            // 活动未开启
            $status = -1;
        } elseif ($c['stage'] === 2) {
            // 选手确认倒计时
            $cd = $c['battle_time'] - $c['now'];
            // 获取关注场次信息
            $info = self::info($c, $serverid, false, true);
            if ($info === []) {
                $status = -1;
            }
        } elseif ($c['stage'] === 3) {
            // 对手显示倒计时
            $cd = $c['prepare_time'] - $c['now'];
            // 已同步玩家信息时返回参赛者
            $info = self::info($c, $serverid, self::isSync($c['phase']), true);
            if ($info === []) {
                $status = -1;
            }
        } elseif ($c['stage'] === 4) {
            $sync = $bet = 0;
            // 战斗准备倒计时
            $cd = $c['battle_cd'];
            $info = self::info($c, $serverid, true, true);
            if ($info === []) {
                $status = -1;
            } else {
                $play = $info['play'];
                $entry = self::isEntry($c['phase'], $play['round'], $play['roundid'], $serverid, $uid);
                if ($cd > self::BATTLE_LOCK) {
                    if ($entry) {
                        // 参赛者可同步
                        $sync = 1;
                    } else {
                        // 非参赛者可押注
                        $bet = 1;
                    }
                }
                $info['play']['bet'] = $bet;
                $info['play']['sync'] = $sync;
                $info['play']['is_player'] = $entry ? 1 : 0;
            }
        } else {
            // 战斗结束
            $result = self::matchResult($c['phase']);
            if ($result === []) {
                // 获取赛程失败
                $status = -1;
            } else {
                $info['match_result'] = $result;
            }
            $info['schedule'] = self::roundList($c['phase'], 8);
        }
        return ['status' => $status, 'info' => $info, 'stage' => $c['stage'], 'cd' => $cd + 5];
    }

    /**
     * sync_info：同步战斗信息
     *
     * status：-1 已锁定；-2 未参赛；2 同步过于频繁（10 秒）；-3 获取信息失败；1 成功
     *
     * @return array{status: int}
     */
    public static function syncInfo(int $serverid, int $uid): array
    {
        $c = self::clock();
        // 已锁定不能同步数据
        if ($c['battle_cd'] <= self::BATTLE_LOCK) {
            return ['status' => -1];
        }
        $member = self::entryInfo($c['phase'], $serverid, $uid);
        if ($member === null) {
            // 未参赛
            return ['status' => -2];
        }
        if ($c['now'] - $member['dateline'] < self::DELAY) {
            // 同步过于频繁
            return ['status' => 2];
        }
        if (self::doSync($c, $serverid, $uid, $member['level']) !== 1) {
            return ['status' => -3];
        }
        return ['status' => 1];
    }

    /**
     * get_report：一场的战报（info 为战报行，report 已解码）
     *
     * @return array<string, mixed>
     */
    public static function getReport(int $round, int $roundid): array
    {
        $c = self::clock();
        $row = DB::table('battle_gvg_report')->select('phase', 'round', 'roundid', 'serverid1', 'serverid2', 'report', 'background')
            ->where('phase', $c['phase'])->where('round', $round)->where('roundid', $roundid)->whereNull('deleted_at')->first();
        $report = $row === null ? null : json_decode((string)$row['report'], true);
        if (!is_array($report)) {
            return ['status' => -1];
        }
        $info = array_map(static fn (mixed $v): mixed => is_numeric($v) ? (int)$v : $v, $row);
        $info['report'] = $report;
        return ['info' => $info, 'status' => 1];
    }

    /**
     * get_bet：一场的押注数（按开打倒计时放大显示）
     *
     * @return array<string, mixed>
     */
    public static function getBet(int $round, int $roundid): array
    {
        $c = self::clock();
        $return = ['status' => 0];
        $play = self::playById($c['phase'], $round, $roundid);
        if ($play !== null) {
            $return['info'] = [
                'serverid1' => $play['serverid1'],
                'serverid2' => $play['serverid2'],
                'bet1' => self::betUp($c, $play['bet1']),
                'bet2' => self::betUp($c, $play['bet2']),
            ];
            $return['status'] = 1;
        }
        return $return;
    }

    /**
     * betting：押注
     *
     * status：-4 押注对象非参赛者；-3 场次错误；-2 无押注资格；-1 押注时间已过；0 失败；1 成功；2 已押注
     *
     * @return array<string, mixed>
     */
    public static function betting(int $uid, int $serverid, int $toserverid, int $round, int $roundid): array
    {
        $c = self::clock();
        $return = ['status' => 0];
        if ($c['stage'] !== 4 || $c['battle_cd'] <= self::BATTLE_LOCK) {
            // 押注时间已过
            $return['status'] = -1;
            return $return;
        }
        if (self::betLock($c['phase'], $uid, $round, $roundid)) {
            // 已押注（旧版先加锁再校验，校验失败的同一场也不能再押）
            $return['status'] = 2;
            return $return;
        }
        if ($toserverid > 0 && $round === $c['round']) {
            if (self::isEntry($c['phase'], $round, $roundid, $serverid, $uid)) {
                // 参赛者无法押注
                $return['status'] = -2;
                return $return;
            }
            $play = self::playById($c['phase'], $round, $roundid);
            if ($play === null || $play['win'] > 0) {
                // 场次不存在，或已有结果
                $return['status'] = -3;
                return $return;
            }
            if ($toserverid === $play['serverid1']) {
                $side = 1;
            } elseif ($toserverid === $play['serverid2']) {
                $side = 2;
            } else {
                $return['status'] = -4;
                return $return;
            }
            // 押注数 + 1（原子累加）
            $betting = DB::execute(
                'UPDATE `zc_sanguo_battle_gvg_round` SET `bet' . $side . '` = `bet' . $side . '` + 1 WHERE `phase` = :phase AND `round` = :round AND `roundid` = :roundid',
                ['phase' => $c['phase'], 'round' => $round, 'roundid' => $roundid]
            );
            if ($betting === 1) {
                $bet = ['bet1' => $play['bet1'], 'bet2' => $play['bet2']];
                $bet['bet' . $side]++;
                $return['bet1'] = self::betUp($c, $bet['bet1']);
                $return['bet2'] = self::betUp($c, $bet['bet2']);
                $return['phase'] = $c['phase'];
                $return['side'] = $side;
                $return['status'] = 1;
            }
        }
        return $return;
    }

    /**
     * get_member_list：某服务器的参赛玩家 uid
     *
     * @return array<string, mixed>
     */
    public static function memberList(int $serverid): array
    {
        $c = self::clock();
        $member = DB::table('battle_gvg_entry')->select('uid')->where('phase', $c['phase'])->where('serverid', $serverid)->whereNull('deleted_at')
            ->orderBy('rank')->get();
        if ($member === []) {
            Logger::error('GVG get_member_list faild! [ERROR: serverid=' . $serverid);
            return ['status' => -1];
        }
        return ['list' => array_map(static fn (array $r): array => ['uid' => (int)$r['uid']], $member), 'status' => 1];
    }

    /**
     * get_bet_result：一场的比赛结果（押注奖励用）
     *
     * status：-1 未开战；-2 战斗结果不能提早获取；-3 获取失败；1 成功（serverid 胜方服务器，win 1 左 2 右）
     *
     * @return array<string, mixed>
     */
    public static function betResult(int $round, int $roundid): array
    {
        $c = self::clock();
        if ($c['stage'] < 4) {
            return ['status' => -1];
        }
        if ($c['stage'] === 4 && $c['battle_cd'] <= self::BATTLE_LOCK) {
            return ['status' => -2];
        }
        $play = self::playById($c['phase'], $round, $roundid);
        if ($play === null || $play['win'] === 0) {
            return ['status' => -3];
        }
        $serverid = match ($play['win']) {
            1 => $play['serverid1'],
            2 => $play['serverid2'],
            default => 0,
        };
        return ['serverid' => $serverid, 'win' => $play['win'], 'status' => 1];
    }

    /**
     * get_round_result：某服务器赢下的场次（晋级奖励用）
     *
     * @return array<string, mixed>
     */
    public static function roundResult(int $serverid): array
    {
        $c = self::clock();
        if ($c['stage'] < 4) {
            return ['status' => -1];
        }
        if ($c['stage'] === 4 && $c['battle_cd'] <= self::BATTLE_LOCK) {
            return ['status' => -2];
        }
        $rows = DB::table('battle_gvg_round')->select('round', 'roundid')->where('phase', $c['phase'])->whereNull('deleted_at')
            ->whereRaw('((`serverid1` = ? AND `win` = 1) OR (`serverid2` = ? AND `win` = 2))', [$serverid, $serverid])
            ->orderBy('round', 'DESC')->get();
        if ($rows === []) {
            return ['status' => -3];
        }
        return ['status' => 1, 'info' => array_map(static fn (array $r): array => ['round' => (int)$r['round'], 'roundid' => (int)$r['roundid']], $rows)];
    }

    // ---------------------------------------------------------------------------------------------
    // 计划任务（battle gvg->run_pk，每分钟；旧版计划任务时间 15 日 5 点至 18 日 22 点）
    // ---------------------------------------------------------------------------------------------

    /**
     * run_pk：活动未开启时生成参赛服务器、对阵与关注；对手显示倒计时内取参赛玩家并同步；战斗中开打前 5 分钟算出本轮团战并生成下一轮的关注
     *
     * @return int 本次算了几场
     */
    public static function runPk(int $now): int
    {
        $c = self::clock($now);
        if ($c['stage'] === 1) {
            // 初始化群服争霸
            self::fetchServer($c);
            self::matchTeam($c);
            self::matchFollowAll(self::clock($now));
            return 0;
        }
        if ($c['stage'] === 3) {
            self::fetchMember($c);
            self::syncAllMember($c);
            return 0;
        }
        if ($c['stage'] !== 4 || $c['battle_cd'] > self::BATTLE_LOCK) {
            return 0;
        }
        $num = 0;
        foreach (DB::table('battle_gvg_round')->select('round', 'roundid')->where('phase', $c['phase'])->where('round', $c['round'])
            ->whereNull('deleted_at')->orderBy('roundid')->get() as $value) {
            self::pk($c, (int)$value['round'], (int)$value['roundid']);
            $num++;
        }
        if ($c['round'] > 1) {
            $round = intdiv($c['round'], 2);
            foreach (self::entryServer($c['phase']) as $value) {
                self::matchFollow($c['phase'], (int)$value['serverid'], $round);
            }
        }
        return $num;
    }

    /**
     * _pk：一场团战（支持数不影响先后手：服务器 id 小的一方先手）
     *
     * status：-2 已经战斗过；-1 信息不全；0 失败；1 成功
     *
     * @param array<string, mixed> $c clock()
     * @return array{status: int}
     */
    public static function pk(array $c, int $round, int $roundid): array
    {
        $play = self::playById($c['phase'], $round, $roundid);
        $status = 0;
        if ($play === null) {
            return ['status' => $status];
        }
        $playData = [];
        if ($play['win'] === 0) {
            if ($play['serverid2']) {
                // 确定先后手
                $first = $play['serverid2'] < $play['serverid1'] ? 2 : 1;
                // 双方有人
                $ready = self::pkReady($c['phase'], $play);
                if ($ready['status'] === 1) {
                    if ($first === 1) {
                        // 左方先
                        [$serverid1, $serverid2] = [$play['serverid1'], $play['serverid2']];
                        [$leftUid, $rightUid, $left, $right] = [$ready['left_uid'], $ready['right_uid'], $ready['left'], $ready['right']];
                    } else {
                        // 右方先
                        [$serverid1, $serverid2] = [$play['serverid2'], $play['serverid1']];
                        [$leftUid, $rightUid, $left, $right] = [$ready['right_uid'], $ready['left_uid'], $ready['right'], $ready['left']];
                    }
                    $pkResult = self::battleRush($serverid1, $leftUid, $serverid2, $rightUid);
                    $playData = ['win' => self::pkResult($first, $pkResult['win'])];
                    // 记录战报
                    $report = [
                        'left_name' => $serverid1,
                        'right_name' => $serverid2,
                        'left_team' => $left,
                        'right_team' => $right,
                        'battle' => $pkResult,
                    ];
                    DB::execute(
                        'INSERT IGNORE INTO `zc_sanguo_battle_gvg_report` (`phase`, `round`, `roundid`, `serverid1`, `serverid2`, `report`) VALUES (:phase, :round, :roundid, :s1, :s2, :report)',
                        ['phase' => $c['phase'], 'round' => $round, 'roundid' => $roundid, 's1' => $serverid1, 's2' => $serverid2, 'report' => (string)json_encode($report)]
                    );
                } else {
                    // 信息不全
                    $status = -1;
                }
            } else {
                // 左轮空
                $playData = ['win' => 1];
            }
        } else {
            // 已经战斗过
            $status = -2;
        }
        if ($playData !== []) {
            // 记录战斗结果（以 win = 0 为条件，并发只记一次）
            $updated = DB::table('battle_gvg_round')->where('phase', $c['phase'])->where('round', $round)->where('roundid', $roundid)->where('win', 0)
                ->update($playData);
            if ($updated !== 1) {
                return ['status' => -2];
            }
            // 修改参赛服务器信息：败方出局（记出局的轮次）
            $loser = $playData['win'] === 1 ? $play['serverid2'] : $play['serverid1'];
            DB::table('battle_gvg_server')->where('phase', $c['phase'])->where('serverid', $loser)->whereNull('deleted_at')->update(['goout' => $play['round']]);
            if (intdiv($round, 2) >= 1) {
                // 胜者晋级
                $next = self::qualifyTeam($play, $playData['win']);
                DB::table('battle_gvg_round')->where('phase', $c['phase'])->where('round', $next['round'])->where('roundid', $next['roundid'])
                    ->update($next['data']);
            }
            $status = 1;
        }
        return ['status' => $status];
    }

    // ---------------------------------------------------------------------------------------------

    /**
     * _get_info：本服务器本轮关注的场次（$member 返回双方参赛玩家，$schedule 返回赛程）；没有关注场次为空数组
     *
     * @param array<string, mixed> $c
     * @return array<string, mixed>
     */
    private static function info(array $c, int $serverid, bool $member, bool $schedule): array
    {
        $return = [];
        $follow = DB::table('battle_gvg_follow')->select('round', 'roundid')->where('phase', $c['phase'])->where('serverid', $serverid)
            ->where('round', $c['round'])->whereNull('deleted_at')->first();
        if ($follow === null) {
            return $return;
        }
        $play = self::playById($c['phase'], (int)$follow['round'], (int)$follow['roundid']);
        if ($play === null) {
            return $return;
        }
        if ($member) {
            $return['player'] = [
                'left' => self::player($c['phase'], $play['serverid1']),
                'right' => self::player($c['phase'], $play['serverid2']),
            ];
        }
        if ($schedule) {
            $return['schedule'] = self::schedule($c['phase'], $play['round'], $play['roundid']);
        }
        foreach (['round', 'roundid', 'serverid1', 'serverid2', 'bet1', 'bet2'] as $value) {
            $return['play'][$value] = $play[$value];
        }
        $return['play']['bet1'] = self::betUp($c, $return['play']['bet1']);
        $return['play']['bet2'] = self::betUp($c, $return['play']['bet2']);
        return $return;
    }

    /**
     * _match_result：比赛结果前 5 名的服务器（冠军、亚军，其余按出局轮次升序、最高等级降序、服务器 id 升序）
     *
     * @return list<int>
     */
    private static function matchResult(int $phase): array
    {
        $result = DB::table('battle_gvg_server')->select('serverid', 'goout')->where('phase', $phase)->whereNull('deleted_at')->orderBy('serverid')->get();
        $list = $data = [];
        foreach ($result as $value) {
            $serverid = (int)$value['serverid'];
            $goout = (int)$value['goout'];
            if ($goout === 0) {
                $list[0] = $serverid;
            } elseif ($goout === 1) {
                $list[1] = $serverid;
            } else {
                $data[] = ['serverid' => $serverid, 'goout' => $goout, 'level' => 0];
            }
        }
        if ($data !== []) {
            $top = self::topPlayer($phase, array_column($data, 'serverid'));
            foreach ($data as $key => $value) {
                $data[$key]['level'] = $top[$value['serverid']] ?? 0;
            }
            usort($data, static fn (array $a, array $b): int => [$a['goout'], $b['level'], $a['serverid']] <=> [$b['goout'], $a['level'], $b['serverid']]);
            $key = 2;
            foreach ($data as $value) {
                if ($key >= 5) {
                    break;
                }
                $list[] = $value['serverid'];
                $key++;
            }
        }
        ksort($list);
        return $list;
    }

    /**
     * get_top_player：各服务器参赛玩家的最高等级
     *
     * @param list<int> $serverids
     * @return array<int, int> serverid => level
     */
    private static function topPlayer(int $phase, array $serverids): array
    {
        $return = [];
        foreach (DB::table('battle_gvg_entry')->select('serverid', 'level')->where('phase', $phase)->whereIn('serverid', $serverids)->whereNull('deleted_at')->get() as $row) {
            $sid = (int)$row['serverid'];
            $return[$sid] = max($return[$sid] ?? 0, (int)$row['level']);
        }
        return $return;
    }

    /**
     * get_round_list：$round 轮及以后（1/n 不大于 $round）的场次，按轮次、场次升序
     *
     * @return list<array<string, int>>
     */
    private static function roundList(int $phase, int $round): array
    {
        $rows = DB::table('battle_gvg_round')->select('round', 'roundid', 'serverid1', 'serverid2', 'win')->where('phase', $phase)->where('round', '<=', $round)
            ->whereNull('deleted_at')->orderBy('round')->orderBy('roundid')->get();
        return array_map(static fn (array $r): array => array_map('intval', $r), $rows);
    }

    /**
     * get_schedule：客户端赛程图（键 1 ~ 7）
     *
     * @return array<int, array<string, int>>
     */
    private static function schedule(int $phase, int $round, int $roundid): array
    {
        $level = max(4, $round);
        $group = $round >= 4 ? intdiv($roundid + 3, 4) : 1;
        $keys = [];
        for ($i = 0; $i < 4; $i++) {
            $keys[4 + $i] = [$level, ($group - 1) * 4 + $i + 1];
        }
        for ($i = 0; $i < 2; $i++) {
            $keys[2 + $i] = [intdiv($level, 2), ($group - 1) * 2 + $i + 1];
        }
        if (intdiv($level, 4) === 1) {
            $keys[1] = [1, 1];
        }
        ksort($keys);
        $return = [];
        foreach ($keys as $key => [$r, $id]) {
            $row = self::playById($phase, $r, $id);
            $return[$key] = [
                'round' => $r,
                'roundid' => $id,
                'serverid1' => $row['serverid1'] ?? 0,
                'serverid2' => $row['serverid2'] ?? 0,
                'win' => $row['win'] ?? 0,
            ];
        }
        return $return;
    }

    /**
     * get_player：某服务器的参赛玩家（演武排名升序）
     *
     * @return list<array{uid: int, nickname: string, level: int}>
     */
    private static function player(int $phase, int $serverid): array
    {
        if ($serverid === 0) {
            return [];
        }
        $rows = DB::table('battle_gvg_entry')->select('uid', 'nickname', 'level')->where('phase', $phase)->where('serverid', $serverid)->whereNull('deleted_at')
            ->orderBy('rank')->get();
        return array_map(static fn (array $r): array => ['uid' => (int)$r['uid'], 'nickname' => (string)$r['nickname'], 'level' => (int)$r['level']], $rows);
    }

    /**
     * is_entry：玩家是否为这一场某一方的参赛玩家
     */
    private static function isEntry(int $phase, int $round, int $roundid, int $serverid, int $uid): bool
    {
        $play = self::playById($phase, $round, $roundid);
        if ($play === null || ($serverid !== $play['serverid1'] && $serverid !== $play['serverid2'])) {
            return false;
        }
        return self::entryInfo($phase, $serverid, $uid) !== null;
    }

    /**
     * get_entry_info：参赛玩家行
     *
     * @return array{uid: int, serverid: int, level: int, dateline: int}|null
     */
    private static function entryInfo(int $phase, int $serverid, int $uid): ?array
    {
        $row = DB::table('battle_gvg_entry')->select('uid', 'serverid', 'level', 'dateline')->where('phase', $phase)->where('serverid', $serverid)
            ->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * _sync_info：保存队伍快照，等级升高时更新参赛玩家行；-1 获取信息失败（其他服 / 玩家不存在）
     *
     * @param array<string, mixed> $c
     */
    private static function doSync(array $c, int $serverid, int $uid, int $level): int
    {
        if ($serverid !== Server::id()) {
            // 其他服的玩家需请求对方服（多服部署）
            return -1;
        }
        $baseLevel = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level');
        if ($baseLevel === null) {
            return -1;
        }
        $team = TeamBuilder::forMember($uid, defense: true, self: false);
        DB::execute(
            'INSERT INTO `zc_sanguo_battle_gvg_member` (`uid`, `serverid`, `data`, `dateline`) VALUES (:uid, :serverid, :data, :now)
             ON DUPLICATE KEY UPDATE `data` = VALUES(`data`), `dateline` = VALUES(`dateline`), `deleted_at` = NULL',
            ['uid' => $uid, 'serverid' => $serverid, 'data' => (string)json_encode($team), 'now' => $c['now']]
        );
        $info = ['dateline' => $c['now']];
        if ((int)$baseLevel > $level) {
            $info['level'] = (int)$baseLevel;
        }
        DB::table('battle_gvg_entry')->where('phase', $c['phase'])->where('serverid', $serverid)->where('uid', $uid)->update($info);
        return 1;
    }

    /**
     * sync_all_member：同步所有参战玩家信息
     *
     * @param array<string, mixed> $c
     */
    private static function syncAllMember(array $c): void
    {
        foreach (DB::table('battle_gvg_entry')->select('serverid', 'uid', 'level')->where('phase', $c['phase'])->whereNull('deleted_at')->get() as $value) {
            self::doSync($c, (int)$value['serverid'], (int)$value['uid'], (int)$value['level']);
        }
        Redis::set(self::SYNC_KEY . $c['phase'], 1, 86400 * 10);
    }

    /**
     * is_sync：本期是否已同步全部参赛玩家
     */
    private static function isSync(int $phase): bool
    {
        return (bool)Redis::get(self::SYNC_KEY . $phase);
    }

    /**
     * fetch_server：获取资格服务器（当月开的服、已合服的不参加）
     *
     * @param array<string, mixed> $c
     */
    private static function fetchServer(array $c): void
    {
        if (self::entryServer($c['phase']) !== []) {
            return;
        }
        // 获取参加跨服战的服务器
        $serverlist = Server::worldList();
        if ($serverlist === []) {
            Logger::error('GVG get server list failed!');
            return;
        }
        $added = 0;
        foreach ($serverlist as $value) {
            // 当月开的服不参加
            if ((int)date('Ym', (int)$value['starttime']) < $c['phase'] && (int)$value['newserver'] === 0) {
                $added += DB::execute(
                    'INSERT IGNORE INTO `zc_sanguo_battle_gvg_server` (`phase`, `serverid`, `groupid`, `goout`, `dateline`) VALUES (:phase, :serverid, :groupid, 0, :now)',
                    ['phase' => $c['phase'], 'serverid' => (int)$value['id'], 'groupid' => (int)$value['servergroup'], 'now' => $c['now']]
                );
            }
        }
        if ($added === 0) {
            Logger::error('GVG get entry info failed!');
        }
    }

    /**
     * get_entry_server：本期参赛服务器
     *
     * @return list<array<string, mixed>>
     */
    private static function entryServer(int $phase): array
    {
        return DB::table('battle_gvg_server')->select('serverid', 'groupid', 'goout')->where('phase', $phase)->whereNull('deleted_at')->orderBy('serverid')->get();
    }

    /**
     * fetch_member：取每个参赛服务器的演武榜前 30（本服；其他服需请求对方服）
     *
     * @param array<string, mixed> $c
     */
    private static function fetchMember(array $c): void
    {
        $added = 0;
        foreach (self::entryServer($c['phase']) as $value) {
            if ((int)$value['serverid'] !== Server::id()) {
                // 其他服的演武榜需请求对方服（多服部署）
                continue;
            }
            foreach (ArenaHandler::arenaTop(self::TOP) as $val) {
                $level = (int)(DB::table('member')->where('uid', (int)$val['uid'])->whereNull('deleted_at')->value('level') ?? 1);
                $added += DB::execute(
                    'INSERT IGNORE INTO `zc_sanguo_battle_gvg_entry` (`phase`, `serverid`, `uid`, `nickname`, `rank`, `level`, `dateline`) VALUES (:phase, :serverid, :uid, :nickname, :rank, :level, :now)',
                    ['phase' => $c['phase'], 'serverid' => (int)$value['serverid'], 'uid' => (int)$val['uid'], 'nickname' => (string)$val['username'],
                        'rank' => (int)$val['rank'], 'level' => $level, 'now' => $c['now']]
                );
            }
        }
        if ($added === 0 && DB::table('battle_gvg_entry')->where('phase', $c['phase'])->whereNull('deleted_at')->count() === 0) {
            Logger::error('GVG get entry member failed!');
        }
    }

    /**
     * match_team：生成整张对阵表（每期一次）；返回第一轮场数
     *
     * @param array<string, mixed> $c
     */
    private static function matchTeam(array $c): int
    {
        $redis = Redis::instance();
        if ($redis === null || !(bool)$redis->set(self::MATCH_TEAM_LOCK . $c['phase'], '1', ['nx', 'ex' => 86400 * 40])) {
            return 0;
        }
        if (self::maxRound($c['phase']) > 0) {
            return 0;
        }
        $server = self::entryServer($c['phase']);
        if ($server === []) {
            Logger::error('GVG match team failed![ERROR:get_server_list return empty]');
            // 参赛服务器还没生成：放开锁，下一次再试
            $redis->del(self::MATCH_TEAM_LOCK . $c['phase']);
            return 0;
        }
        $ids = array_map(static fn (array $r): int => (int)$r['serverid'], $server);
        shuffle($ids);
        $m = 1;
        while ($m * 2 < count($ids)) {
            $m *= 2;
        }
        return Server::transaction(static function () use ($c, $ids, $m): int {
            for ($round = $m; $round >= 1; $round = intdiv($round, 2)) {
                for ($roundid = 1; $roundid <= $round; $roundid++) {
                    $data = ['phase' => $c['phase'], 'round' => $round, 'roundid' => $roundid];
                    if ($round === $m) {
                        $data['serverid1'] = $ids[$roundid - 1] ?? 0;
                        $data['serverid2'] = $ids[$roundid - 1 + $m] ?? 0;
                    }
                    DB::table('battle_gvg_round')->insert($data);
                }
            }
            return $m;
        });
    }

    /**
     * match_follow：所有参赛服务器关注本轮的一场
     *
     * @param array<string, mixed> $c
     */
    private static function matchFollowAll(array $c): void
    {
        $round = $c['round'] > 0 ? $c['round'] : self::maxRound($c['phase']);
        if ($round === 0) {
            return;
        }
        foreach (self::entryServer($c['phase']) as $value) {
            self::matchFollow($c['phase'], (int)$value['serverid'], $round);
        }
    }

    /**
     * match_follow：服务器本轮有比赛关注自己那场，否则随机关注一场（已关注的不再改）
     */
    private static function matchFollow(int $phase, int $serverid, int $round): void
    {
        $rows = DB::table('battle_gvg_round')->select('roundid', 'serverid1', 'serverid2')->where('phase', $phase)->where('round', $round)
            ->whereNull('deleted_at')->orderBy('roundid')->get();
        if ($rows === []) {
            return;
        }
        $follow = null;
        foreach ($rows as $row) {
            if ((int)$row['serverid1'] === $serverid || (int)$row['serverid2'] === $serverid) {
                $follow = $row;
                break;
            }
        }
        $toserverid = $serverid;
        if ($follow === null) {
            $follow = $rows[array_rand($rows)];
            $toserverid = (int)$follow['serverid1'];
        }
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_battle_gvg_follow` (`phase`, `serverid`, `round`, `roundid`, `toserverid`) VALUES (:phase, :serverid, :round, :roundid, :to)',
            ['phase' => $phase, 'serverid' => $serverid, 'round' => $round, 'roundid' => (int)$follow['roundid'], 'to' => $toserverid]
        );
    }

    /**
     * max_round：本期第一轮的场数（对阵表的最大轮次；没有为 0）
     */
    private static function maxRound(int $phase): int
    {
        return (int)(DB::table('battle_gvg_round')->where('phase', $phase)->whereNull('deleted_at')->max('round') ?? 0);
    }

    /**
     * get_play_by_id
     *
     * @return array<string, int>|null
     */
    private static function playById(int $phase, int $round, int $roundid): ?array
    {
        $row = DB::table('battle_gvg_round')->select('round', 'roundid', 'serverid1', 'serverid2', 'bet1', 'bet2', 'win')->where('phase', $phase)
            ->where('round', $round)->where('roundid', $roundid)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * pk_ready：双方参赛玩家（演武排名升序）；任一方没有时 status -1
     *
     * @param array<string, int> $play
     * @return array{status: int, left: list<array<string, mixed>>, right: list<array<string, mixed>>, left_uid: list<int>, right_uid: list<int>}
     */
    private static function pkReady(int $phase, array $play): array
    {
        $left = self::player($phase, $play['serverid1']);
        $right = self::player($phase, $play['serverid2']);
        return [
            'status' => $left === [] || $right === [] ? -1 : 1,
            'left' => $left,
            'right' => $right,
            'left_uid' => array_column($left, 'uid'),
            'right_uid' => array_column($right, 'uid'),
        ];
    }

    /**
     * battle_model->battle_rush(左方服务器, 左方玩家, 右方服务器, 右方玩家)：按同步的队伍快照打团战
     *
     * @param list<int> $leftUid
     * @param list<int> $rightUid
     * @return array<string, mixed>
     */
    private static function battleRush(int $serverid1, array $leftUid, int $serverid2, array $rightUid): array
    {
        $snapshot = [];
        foreach ([$serverid1 => $leftUid, $serverid2 => $rightUid] as $serverid => $uids) {
            foreach (DB::table('battle_gvg_member')->select('uid', 'data')->where('serverid', $serverid)->whereIn('uid', $uids !== [] ? $uids : [0])
                ->whereNull('deleted_at')->get() as $row) {
                $data = json_decode((string)$row['data'], true);
                if (is_array($data)) {
                    $team = [];
                    foreach ((array)($data['team'] ?? []) as $pos => $unit) {
                        $team[(int)$pos] = $unit;
                    }
                    $data['team'] = $team;
                    $snapshot[(int)$row['uid']] = $data;
                }
            }
        }
        $rush = new BattleRush(0, null, static fn (int $uid): ?array => $snapshot[$uid] ?? null);
        return $rush->run($leftUid, $rightUid, 0);
    }

    /**
     * _get_qualify_team：胜者进入下一轮（第 2k - 1 场的胜者在第 k 场左方，第 2k 场的在右方）
     *
     * @param array<string, int> $play
     * @return array{round: int, roundid: int, data: array<string, int>}
     */
    private static function qualifyTeam(array $play, int $win): array
    {
        $serverid = $win === 1 ? $play['serverid1'] : $play['serverid2'];
        $side = ($play['roundid'] + 1) % 2 + 1;
        return [
            'round' => intdiv($play['round'], 2),
            'roundid' => intdiv($play['roundid'] + 1, 2),
            'data' => ['serverid' . $side => $serverid],
        ];
    }

    /**
     * _get_pk_result：按先手方的结果换算成场次的胜方（1 左方、2 右方）
     */
    private static function pkResult(int $first, int $win): int
    {
        if ($first === 1) {
            return $win === 1 ? 1 : 2;
        }
        return $win === 1 ? 2 : 1;
    }

    /**
     * _get_bet_up：显示的押注数（战斗中按距离锁定的时间放大；其他阶段为原数）
     *
     * @param array<string, mixed> $c
     */
    private static function betUp(array $c, int $bet): int
    {
        $return = 0;
        if ($c['stage'] !== 4) {
            return $bet;
        }
        $spaceTime = self::BATTLE_SPACE - self::BATTLE_LOCK;
        $cd = max(0, $c['battle_cd'] - self::BATTLE_LOCK);
        $i = 0;
        while (2 ** $i <= $c['round']) {
            $times = 15 - $i;
            $return = (int)($times * ($bet + ($spaceTime - $cd) / $spaceTime));
            $i++;
        }
        return $return;
    }

    /**
     * bet_lock：同一玩家同一场只押一次（已押注返回 true）
     */
    private static function betLock(int $phase, int $uid, int $round, int $roundid): bool
    {
        $redis = Redis::instance();
        if ($redis === null) {
            return true;
        }
        return !(bool)$redis->set(self::BET_LOCK . $phase . '_' . $uid . '_' . $round . '_' . $roundid, '1', ['nx', 'ex' => 86400 * 10]);
    }
}
