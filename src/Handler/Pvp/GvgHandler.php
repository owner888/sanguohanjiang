<?php

declare(strict_types=1);

namespace Hanjiang\Handler\Pvp;

use Hanjiang\Cross\GvgServer;
use Hanjiang\Cross\Server;
use Hanjiang\Handler\City\MemberHandler;
use Hanjiang\Model\BossClock;
use Hanjiang\Model\Gvg;
use OmniPHP\Database\DB;
use OmniPHP\Http\Context;

/**
 * 群服争霸（移植旧版 s1 controllers/gvg.php + models/gvg_model.php；战斗服务器部分见 Cross\GvgServer，计划任务见 Cron\GvgCron）
 *
 * 各服务器演武榜前 30 代表本服参赛，服务器之间一局团战定胜负。每月 15 日 23:00 到 22 日 00:30 开放（当前时间取 BossClock，
 * 生产恒为 time()），18 日 19:00 开战、每 15 分钟一轮，开打前 5 分钟锁定。参赛玩家可同步阵容，其他玩家可押注一方。
 * 客户端 gvgworld.js / gvgbattleworld.js 调用 index、sync_info、betting、get_bet、get_report；只有 index 判断等级（60 级）。
 * 不移植：clear / clear_report / test_swap_report（运维口令与调试接口，客户端不调用）。
 */
final class GvgHandler
{
    /** 服务器演武榜资格 */
    private const int TOP = 30;

    /** 等级限制 */
    private const int LEVEL_LIMIT = 60;

    /** s1 lang gvg_rank_1 ~ 5 */
    private const array LANG_RANK = [1 => '第一名', 2 => '第二名', 3 => '第三名', 4 => '第四名', 5 => '第五名'];

    /**
     * 群服争霸入口: c=gvg&m=index
     */
    public static function index(Context $ctx): array
    {
        /**
         * -3 活动未开启
         * -2 获取信息失败
         * -1 等级不足
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $level = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level');
        if ($level === null) {
            return $return;
        }
        if ((int)$level < self::LEVEL_LIMIT) {
            // 等级不足
            $return['status'] = -1;
            return $return;
        }
        $now = BossClock::now();
        if (!Gvg::isOpen($now)) {
            // 活动未开启
            $return['status'] = -3;
            return $return;
        }
        $serverid = Server::id();
        $uc = GvgServer::index($serverid, $uid);
        if ($uc['status'] !== 1) {
            // 获取群服争霸信息失败
            $return['status'] = -2;
            return $return;
        }
        $cd = $uc['cd'];
        $ucStage = $uc['stage'];
        if ($ucStage === 1) {
            $return['status'] = -3;
            return $return;
        }
        $schedule = $uc['info']['schedule'] ?? [];
        $team = ['left' => [], 'right' => []];
        if (isset($uc['info']['play'])) {
            $play = $ucPlay = $uc['info']['play'];
            if ($serverid === $ucPlay['serverid2']) {
                // 左右交换
                $play['serverid1'] = $ucPlay['serverid2'];
                $play['serverid2'] = $ucPlay['serverid1'];
                $play['bet1'] = $ucPlay['bet2'];
                $play['bet2'] = $ucPlay['bet1'];
            }
            $play['left_name'] = Gvg::serverName($play['serverid1']);
            $play['right_name'] = Gvg::serverName($play['serverid2']);
            $play['reward'] = self::rewardInfo($play['round']);
            $play['round_name'] = Gvg::LANG_ROUND[$play['round']] ?? '';
            $play['level_limit'] = 60;
        } else {
            $play = [];
        }
        $stage = 0;
        if ($ucStage === 2) {
            $stage = 1;
            $team['left'] = self::arenaTop();
            $team['right'] = [];
        } elseif ($ucStage === 3) {
            $stage = 2;
            if (isset($uc['info']['player'])) {
                if ($serverid === $uc['info']['play']['serverid1']) {
                    $team['left'] = $uc['info']['player']['left'];
                    $team['right'] = $uc['info']['player']['right'];
                } else {
                    $team['left'] = $uc['info']['player']['right'];
                    $team['right'] = $uc['info']['player']['left'];
                }
            }
            if ($team['left'] === []) {
                $team['left'] = self::arenaTop();
                $team['right'] = [];
            }
        } elseif ($ucStage === 4) {
            $stage = 3;
            $ucPlay = $uc['info']['play'];
            $ucTeam = $uc['info']['player'];
            // 判断是否已支持
            $isBet = Gvg::bet($uid, $play['round'], $play['roundid'], $now);
            if ($isBet !== []) {
                $play['bet'] = 2;
                $play['bet_server'] = $isBet['toserverid'];
            }
            if ($ucPlay['serverid2'] === $serverid) {
                $team['left'] = $ucTeam['right'];
                $team['right'] = $ucTeam['left'];
            } else {
                $team['left'] = $ucTeam['left'];
                $team['right'] = $ucTeam['right'];
            }
        } elseif ($ucStage === 5) {
            $stage = 4;
        }
        if ($stage === 4) {
            $matchResult = $uc['info']['match_result'];
            for ($i = 0, $k = 1; $i < 5; $i++, $k++) {
                $return['match_result'][$k] = [
                    'rank' => self::LANG_RANK[$k],
                    'server' => Gvg::serverName((int)($matchResult[$i] ?? 0)),
                ];
            }
            $return['schedule'] = [];
            foreach ($uc['info']['schedule'] as $value) {
                $value['serverid1'] = Gvg::serverName($value['serverid1']);
                $value['serverid2'] = Gvg::serverName($value['serverid2']);
                $return['schedule'][] = $value;
            }
        } else {
            $return['cd'] = $cd;
            foreach ($schedule as $key => $value) {
                $value['server1'] = Gvg::serverName($value['serverid1']);
                $value['server2'] = Gvg::serverName($value['serverid2']);
                $schedule[$key] = $value;
            }
            $return['info'] = [
                'schedule' => $schedule,
                'team' => $team,
                'play' => $play,
            ];
        }
        $return['status'] = 1;
        $return['stage'] = $stage;
        return $return;
    }

    /**
     * 同步战斗信息: c=gvg&m=sync_info
     */
    public static function sync_info(Context $ctx): array
    {
        /**
         * -3 活动未开启
         * -2 同步过于频繁
         * -1 同步失败
         *  0 失败
         *  1 成功
         */
        $uid = MemberHandler::resolveUid($ctx);
        if (!Gvg::isOpen(BossClock::now())) {
            return ['status' => -3];
        }
        if (Gvg::syncLock($uid)) {
            return ['status' => -2];
        }
        $uc = GvgServer::syncInfo(Server::id(), $uid);
        if ($uc['status'] !== 1) {
            return ['status' => -1];
        }
        Gvg::addSyncLock($uid);
        return ['status' => 1];
    }

    /**
     * 获取战报: c=gvg&m=get_report&round=&roundid=
     */
    public static function get_report(Context $ctx): array
    {
        /**
         * -3 活动未开启
         * -2 获取战报失败
         * -1 场次不存在
         *  0 失败
         *  1 成功
         */
        $round = (int)$ctx->input('round', 0);
        $roundid = (int)$ctx->input('roundid', 0);
        if (!Gvg::isOpen(BossClock::now())) {
            return ['status' => -3];
        }
        if ($round === 0 || $roundid === 0) {
            return ['status' => -1];
        }
        $report = Gvg::reportCache($round, $roundid);
        if ($report === []) {
            $get = GvgServer::getReport($round, $roundid);
            if ($get['status'] !== 1) {
                return ['status' => -2];
            }
            // 本服是守方时左右交换
            $report = $get['info']['serverid2'] === Server::id() ? self::swapReport($get['info']['report']) : $get['info']['report'];
            Gvg::addReportCache($round, $roundid, $report);
        }
        $report['left_name'] = Gvg::serverName((int)$report['left_name']);
        $report['right_name'] = Gvg::serverName((int)$report['right_name']);
        return [
            'status' => 1,
            'report' => $report,
            'share' => ['id' => $round . '_' . $roundid, 'type' => 'gvg'],
        ];
    }

    /**
     * 押注: c=gvg&m=betting&round=&roundid=&toserverid=
     */
    public static function betting(Context $ctx): array
    {
        /**
         * -3 活动未开启
         * -2 押注失败
         * -1 已押注
         *  0 失败
         *  1 成功
         */
        $return = ['status' => 0];
        $uid = MemberHandler::resolveUid($ctx);
        $round = (int)$ctx->input('round', 0);
        $roundid = (int)$ctx->input('roundid', 0);
        $toserverid = (int)$ctx->input('toserverid', 0);
        $now = BossClock::now();
        if (!Gvg::isOpen($now)) {
            $return['status'] = -3;
            return $return;
        }
        if (Gvg::bet($uid, $round, $roundid, $now) !== []) {
            // 已押注
            $return['status'] = -1;
            return $return;
        }
        $serverid = Server::id();
        $result = GvgServer::betting($uid, $serverid, $toserverid, $round, $roundid);
        if ($result['status'] !== 1) {
            // 押注失败
            $return['status'] = -2;
            return $return;
        }
        if (Gvg::betting($uid, $result['phase'], $round, $roundid, $toserverid, $result['side'])) {
            if ($result['side'] === 2 && $serverid === $toserverid) {
                $return['bet1'] = $result['bet2'];
                $return['bet2'] = $result['bet1'];
            } else {
                $return['bet1'] = $result['bet1'];
                $return['bet2'] = $result['bet2'];
            }
            $return['status'] = 1;
        }
        return $return;
    }

    /**
     * 获取押注信息: c=gvg&m=get_bet&round=&roundid=（旧版不判断开放时间）
     */
    public static function get_bet(Context $ctx): array
    {
        /**
         * -1 获取押注信息失败
         *  1 成功
         */
        $round = (int)$ctx->input('round', 0);
        $roundid = (int)$ctx->input('roundid', 0);
        $bet = Gvg::betCache($round, $roundid);
        if ($bet === []) {
            $result = GvgServer::getBet($round, $roundid);
            if ($result['status'] !== 1) {
                return ['status' => -1];
            }
            $bet = $result['info'];
            if (Server::id() === $bet['serverid2']) {
                // 左右交换
                $bet['serverid1'] = $result['info']['serverid2'];
                $bet['serverid2'] = $result['info']['serverid1'];
                $bet['bet1'] = $result['info']['bet2'];
                $bet['bet2'] = $result['info']['bet1'];
            }
            Gvg::addBetCache($round, $roundid, $bet);
        }
        return ['status' => 1, 'info' => $bet];
    }

    /**
     * 分享用的群服争霸战报（report 控制器 _gvg_report：不按本服交换左右，服务器名用 get_server_name）；取不到为 null
     *
     * @return array<string, mixed>|null
     */
    public static function shareReport(int $round, int $roundid): ?array
    {
        $report = Gvg::reportCache($round, $roundid);
        if ($report === []) {
            $get = GvgServer::getReport($round, $roundid);
            if ($get['status'] !== 1) {
                return null;
            }
            $report = $get['info']['report'];
            Gvg::addReportCache($round, $roundid, $report);
        }
        $report['left_name'] = Server::serverName((int)$report['left_name']);
        $report['right_name'] = Server::serverName((int)$report['right_name']);
        return ['status' => 1, 'report' => $report];
    }

    // ---------------------------------------------------------------------------------------------

    /**
     * _get_arena_top：本服演武榜前 30（uid / rank / nickname / level）
     *
     * @return list<array<string, mixed>>
     */
    private static function arenaTop(): array
    {
        $top = ArenaHandler::arenaTop(self::TOP);
        $uids = array_column($top, 'uid');
        $levels = [];
        if ($uids !== []) {
            foreach (DB::table('member')->select('uid', 'level')->whereIn('uid', $uids)->whereNull('deleted_at')->get() as $row) {
                $levels[(int)$row['uid']] = (int)$row['level'];
            }
        }
        $return = [];
        foreach ($top as $key => $value) {
            $value['nickname'] = $value['username'];
            $value['level'] = $levels[$value['uid']] ?? 1;
            unset($value['username']);
            $return[$key] = $value;
        }
        return $return;
    }

    /**
     * _get_reward_info：本轮的参赛奖励与全服奖励（装备换成名称与品质）；没有该轮奖励为空数组
     *
     * @return array<string, mixed>
     */
    private static function rewardInfo(int $round): array
    {
        $info = Gvg::rewardInfo($round);
        if ($info === []) {
            return [];
        }
        return ['player' => Gvg::rewardDisplay($info['reward']), 'server' => $info['server']];
    }

    /**
     * _swap_report：调换团战战报的左右（名单、出场、每段战斗、击杀榜、胜负）
     *
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private static function swapReport(array $report): array
    {
        $return = [
            'left_name' => $report['right_name'],
            'right_name' => $report['left_name'],
            'left_team' => $report['right_team'],
            'right_team' => $report['left_team'],
        ];
        $battle = $report['battle'];
        $battleReady = [];
        foreach ($battle['battle_ready'] as $key => $value) {
            $battleReady[$key]['left'] = $value['right'] ?? null;
            $battleReady[$key]['right'] = $value['left'] ?? null;
        }
        $battleLog = [];
        foreach ($battle['battle_log'] as $key => $value) {
            $battleLog[$key] = [];
            foreach ($value as $k1 => $v1) {
                if (isset($v1['status'])) {
                    $battleLog[$key][$k1]['status'] = $v1['status'];
                } else {
                    $battleLog[$key][$k1]['left'] = $v1['right'] ?? null;
                    $battleLog[$key][$k1]['right'] = $v1['left'] ?? null;
                    $battleLog[$key][$k1]['assess'] = -($v1['assess'] ?? 0);
                    $process = [];
                    foreach ($v1['process'] ?? [] as $k2 => $v2) {
                        $process[$k2]['dead_left'] = $v2['dead_right'] ?? null;
                        $process[$k2]['dead_right'] = $v2['dead_left'] ?? null;
                        $process[$k2]['hit_left'] = $v2['hit_right'] ?? null;
                        $process[$k2]['hit_right'] = $v2['hit_left'] ?? null;
                    }
                    $battleLog[$key][$k1]['process'] = $process;
                    $battleLog[$key][$k1]['left_win'] = $v1['right_win'] ?? null;
                    $battleLog[$key][$k1]['right_win'] = $v1['left_win'] ?? null;
                }
            }
        }
        $killRank = ['left' => $battle['kill_rank']['right'] ?? [], 'right' => $battle['kill_rank']['left'] ?? []];
        // 战斗结果交换
        $return['battle']['win'] = (int)$battle['win'] === 1 ? 2 : 1;
        $return['battle']['battle_ready'] = $battleReady;
        $return['battle']['battle_log'] = $battleLog;
        $return['battle']['general_arr'] = $battle['general_arr'] ?? [];
        $return['battle']['kill_rank'] = $killRank;
        return $return;
    }
}
