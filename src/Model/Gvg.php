<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\Cross\GvgServer;
use Hanjiang\Cross\Server;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\GvgReward;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 群服争霸的游戏服务器端数据（移植旧版 s1 models/gvg_model.php 与 server_model->gvg_server_name）
 *
 * 表：gvg_bet（押注）、gvg_reward_log（晋级奖励记录）、gvg_round（已记录的本服胜场）；奖励配置 MasterData GvgReward。
 * Redis 键同旧版：gvg_sync_lock_{uid}（同步锁 5 秒）、gvg_reward_lock（奖励记录锁 180 秒）、gvg_report_{round}_{roundid}（战报 1 天）、
 * gvg_bet_{round}_{roundid}（押注数 10 秒）、gvg_member_list（本服参赛玩家 1 天）。
 *
 * 照旧版读 GvgReward：旧版数据整行错了一列（type1 为 0、type4 为 100000 之类），按旧版代码读出来参赛奖励与全服奖励都为空，
 * 押注奖励为 2 元宝。
 */
final class Gvg
{
    private const string SYNC_LOCK_KEY = 'gvg_sync_lock_';
    private const int SYNC_LOCK_TTL = 5;
    private const string REWARD_LOCK_KEY = 'gvg_reward_lock';
    private const int REWARD_LOCK_TTL = 180;
    private const string REPORT_KEY = 'gvg_report_';
    private const int REPORT_TTL = 86400;
    private const string BET_NUM_KEY = 'gvg_bet_';
    private const int BET_NUM_TTL = 10;
    private const string MEMBER_KEY = 'gvg_member_list';
    private const int MEMBER_TTL = 86400;

    /** s1 lang server_lang.php server_gvg_1 ~ 4 */
    private const array SERVER_GVG = [1 => '官方%s服', 2 => '港台%s服', 3 => '日本%s服', 4 => '韩国%s服'];

    /** s1 lang gvg_lang.php gvg_round_* */
    public const array LANG_ROUND = [
        1 => '决赛', 2 => '半决赛', 4 => '四强赛', 8 => '八强赛', 16 => '十六强赛', 32 => '海选决赛', 64 => '海选半决赛', 128 => '海选第一轮',
        256 => '海选第二轮', 512 => '海选第三轮', 1024 => '海选第四轮',
    ];

    /**
     * 活动图标 / 接口开放时间（s1 gvg _init、member.php：每月 15 日 23:00 到 22 日 00:30）
     */
    public static function isOpen(int $now): bool
    {
        $ym = date('Y-m-', $now);
        return $now >= (int)strtotime($ym . '15 23:00:00') && $now <= (int)strtotime($ym . '22 00:30:00');
    }

    /**
     * server_model->gvg_server_name：如「官方1服」；0 为空串
     */
    public static function serverName(int $serverid): string
    {
        if ($serverid === 0) {
            return '';
        }
        $sid = Server::sid($serverid);
        return match (true) {
            $serverid < 3000 => sprintf(self::SERVER_GVG[1], $sid),
            $serverid < 4000 => sprintf(self::SERVER_GVG[2], $sid),
            $serverid > 5000 && $serverid < 6000 => sprintf(self::SERVER_GVG[3], $sid),
            $serverid > 6000 && $serverid < 7000 => sprintf(self::SERVER_GVG[4], $sid),
            default => 's' . $serverid,
        };
    }

    /**
     * sync_lock：同步锁存在为 true
     */
    public static function syncLock(int $uid): bool
    {
        return Redis::exists(self::SYNC_LOCK_KEY . $uid);
    }

    public static function addSyncLock(int $uid): void
    {
        Redis::set(self::SYNC_LOCK_KEY . $uid, 1, self::SYNC_LOCK_TTL);
    }

    /**
     * get_reward_lock / add_reward_lock：奖励记录锁（拿到锁返回 true；已被占返回 false）
     */
    public static function rewardLock(): bool
    {
        $redis = Redis::instance();
        return $redis !== null && (bool)$redis->set(self::REWARD_LOCK_KEY, '1', ['nx', 'ex' => self::REWARD_LOCK_TTL]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function reportCache(int $round, int $roundid): array
    {
        $cache = Redis::get(self::REPORT_KEY . $round . '_' . $roundid);
        return is_array($cache) ? $cache : [];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function addReportCache(int $round, int $roundid, array $report): void
    {
        if ($report !== []) {
            Redis::set(self::REPORT_KEY . $round . '_' . $roundid, $report, self::REPORT_TTL);
        }
    }

    /**
     * betting：记录押注（同一玩家同一场只记一行）
     */
    public static function betting(int $uid, int $phase, int $round, int $roundid, int $toserverid, int $side): int
    {
        $id = DB::table('gvg_bet')->insertGetId([
            'phase' => $phase,
            'uid' => $uid,
            'round' => $round,
            'roundid' => $roundid,
            'toserverid' => $toserverid,
            'side' => $side,
        ]);
        return (int)$id > 0 ? 1 : 0;
    }

    /**
     * get_bet：本期玩家在这一场的押注，没有为空数组
     *
     * @return array<string, int>
     */
    public static function bet(int $uid, int $round, int $roundid, int $now): array
    {
        $row = DB::table('gvg_bet')->select('id', 'phase', 'uid', 'toserverid', 'roundid', 'round', 'side', 'win', 'dateline')
            ->where('phase', (int)date('Ym', $now))->where('uid', $uid)->where('round', $round)->where('roundid', $roundid)->whereNull('deleted_at')
            ->orderBy('id')->first();
        return $row === null ? [] : array_map('intval', $row);
    }

    /**
     * @return array<string, mixed>
     */
    public static function betCache(int $round, int $roundid): array
    {
        $cache = Redis::get(self::BET_NUM_KEY . $round . '_' . $roundid);
        return is_array($cache) ? $cache : [];
    }

    /**
     * @param array<string, mixed> $info
     */
    public static function addBetCache(int $round, int $roundid, array $info): void
    {
        if ($info !== []) {
            Redis::set(self::BET_NUM_KEY . $round . '_' . $roundid, $info, self::BET_NUM_TTL);
        }
    }

    /**
     * get_reward_info：第 $round 轮（1/n）的奖励（GvgReward 第 log2(n) + 1 行）
     *
     * @return array{reward: array<string, int>, server: array<string, int>, bet: int}|array{}
     */
    public static function rewardInfo(int $round): array
    {
        $id = $round > 0 ? (int)log($round, 2) + 1 : 0;
        $row = GvgReward::ROWS[$id] ?? null;
        return $row === null ? [] : self::rewardRow($row);
    }

    /**
     * get_reward_list：各轮奖励（键为 1/n）
     *
     * @return array<int, array{reward: array<string, int>, server: array<string, int>, bet: int}>
     */
    public static function rewardList(): array
    {
        $list = [];
        foreach (GvgReward::ROWS as $value) {
            $list[2 ** ($value['id'] - 1)] = self::rewardRow($value);
        }
        return $list;
    }

    /**
     * 装备奖励换成模板的名称与品质（s1 _get_reward_info）
     *
     * @param array<string, int> $reward
     * @return array<string, mixed>
     */
    public static function rewardDisplay(array $reward): array
    {
        if (isset($reward['equip'])) {
            $equip = Equip::ROWS[$reward['equip']] ?? null;
            if ($equip !== null) {
                $reward['equip'] = ['name' => $equip['name'], 'quality' => $equip['quality']];
            }
        }
        return $reward;
    }

    /**
     * get_bet_round：本期还没有结果的押注场次
     *
     * @return list<array{round: int, roundid: int}>
     */
    public static function betRound(int $phase): array
    {
        $rows = DB::table('gvg_bet')->select('round', 'roundid')->where('phase', $phase)->where('win', 0)->whereNull('deleted_at')
            ->groupBy('round', 'roundid')->get();
        return array_map(static fn (array $r): array => ['round' => (int)$r['round'], 'roundid' => (int)$r['roundid']], $rows);
    }

    public static function updateBetResult(int $phase, int $round, int $roundid, int $win): int
    {
        return DB::table('gvg_bet')->where('phase', $phase)->where('round', $round)->where('roundid', $roundid)->whereNull('deleted_at')->update(['win' => $win]);
    }

    /**
     * get_bet_result：本期押对了且还没领奖的押注
     *
     * @return list<array<string, int>>
     */
    public static function betResult(int $phase): array
    {
        $rows = DB::table('gvg_bet')->select('id', 'uid', 'round', 'roundid')->where('phase', $phase)->whereRaw('`side` = `win`')->where('dateline', 0)
            ->whereNull('deleted_at')->orderBy('id')->get();
        return array_map(static fn (array $r): array => array_map('intval', $r), $rows);
    }

    /**
     * update_bet_status：记领奖时间（以未领为条件，并发只成功一次）
     */
    public static function updateBetStatus(int $id, int $now): int
    {
        return DB::table('gvg_bet')->where('id', $id)->where('dateline', 0)->update(['dateline' => $now]);
    }

    /**
     * get_entry_member：本服参赛玩家（缓存 1 天，没有缓存时向战斗服务器取）
     *
     * @return list<array{uid: int}>
     */
    public static function entryMember(int $serverid): array
    {
        $cache = Redis::get(self::MEMBER_KEY);
        if (is_array($cache)) {
            return $cache;
        }
        $list = GvgServer::memberList($serverid);
        if ($list['status'] === 1 && $list['list'] !== []) {
            Redis::set(self::MEMBER_KEY, $list['list'], self::MEMBER_TTL);
            return $list['list'];
        }
        return [];
    }

    /**
     * @param array<string, int> $row GvgReward 行
     * @return array{reward: array<string, int>, server: array<string, int>, bet: int}
     */
    private static function rewardRow(array $row): array
    {
        $reward = $server = [];
        for ($i = 1; $i <= 3; $i++) {
            if ($row['type' . $i] && $row['number' . $i]) {
                $reward += self::rewardItem($row, $i);
            } else {
                // 没有奖励数据
                break;
            }
        }
        if ($row['type4'] && $row['number4']) {
            $server = self::rewardItem($row, 4);
        }
        return ['reward' => $reward, 'server' => $server, 'bet' => (int)$row['bet']];
    }

    /**
     * _reward_item：1 元宝、2 银币、3 紫宝石、4 玉石、5 原魂、6 装备（其余类型没有奖励）
     *
     * @param array<string, int> $value
     * @return array<string, int>
     */
    private static function rewardItem(array $value, int $i): array
    {
        $key = match ((int)$value['type' . $i]) {
            1 => 'gold',
            2 => 'silver',
            3 => 'tavernjade',
            4 => 'jade',
            5 => 'soul',
            6 => 'equip',
            default => null,
        };
        return $key === null ? [] : [$key => (int)$value['number' . $i]];
    }
}
