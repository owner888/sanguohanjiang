<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\Cross\Server;
use Hanjiang\MasterData\WorldExploit;
use Hanjiang\MasterData\WorldShop;
use Hanjiang\MasterData\WorldSilver;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 跨服演武的本服部分（移植旧版 s1 models/worldarena_model.php + member_model 的 get_world_info / add_world_info /
 * add_world_free / update_world_info / update_server_reward）
 *
 * 本服演武榜昨日前三（跨服资格，Redis worldarena_rank_{ymd} 缓存一天，每日记 world_top_log）、押注（Redis worldarena_bet_{uid}
 * 与 world_bet_log）、银币 / 功勋奖励规则（MasterData WorldSilver / WorldExploit）、跨服商城（MasterData WorldShop）、
 * 全服奖励（world_reward / world_reward_log）、跨服战报缓存（Redis worldarena_report_{id}）、玩家跨服信息（member_world）。
 */
final class WorldArena
{
    private const string RANK_KEY = 'worldarena_rank_';
    private const string BET_KEY = 'worldarena_bet_';
    private const string REPORT_KEY = 'worldarena_report_';

    /** 前三需要的主公等级（_get_top_rank：低于 61 级的不进跨服榜） */
    public const int LEVEL = 61;

    /** 上次领取功勋时间为 0 时按上线时间 2012-11-22 算（旧版写死） */
    public const int ONLINE_TIME = 1353513600;

    /**
     * worldarena_model->get_date：日期；serverid 4000 ~ 7000（日本 / 韩国服）凌晨 1 点算前一天
     */
    public static function date(string $format, int $timestamp): string
    {
        $serverid = Server::id();
        if ($serverid > 4000 && $serverid < 7000 && (int)date('H', $timestamp) === 1) {
            return date($format, $timestamp - 3600);
        }
        return date($format, $timestamp);
    }

    /**
     * 按日期获取本服前三（get_top_rank）：uid => [uid, rank, nickname, level, serverid]；不满 3 人为空
     *
     * @return array<int, array{uid: int, rank: int, nickname: string, level: int, serverid: int}>
     */
    public static function topRank(int $now, int $ymd = 0): array
    {
        if ($ymd === 0) {
            $ymd = (int)self::date('Ymd', $now);
        }
        $cache = Redis::get(self::RANK_KEY . $ymd);
        if (is_array($cache)) {
            return self::keyed($cache);
        }
        $return = self::computeTopRank($ymd);
        if ($return !== []) {
            Redis::set(self::RANK_KEY . $ymd, $return, 86400);
        }
        return $return;
    }

    /**
     * 更新演武排行（cache_top_rank，计划任务 0 点后）：重新计算、记 world_top_log、缓存一天
     *
     * @return array<int, array<string, mixed>>
     */
    public static function cacheTopRank(int $ymd): array
    {
        $rank = self::computeTopRank($ymd);
        self::addWorldLog($rank, $ymd);
        Redis::set(self::RANK_KEY . $ymd, $rank, 86400);
        return $rank;
    }

    /**
     * 本服演武榜前三（_get_top_rank）：前一天结束时第 1 ~ 3 名（arena_log 里前一天最后一次到达该名次的玩家，没有就取当前排名），
     * 三个名次都有人才算，低于 61 级的去掉
     *
     * @return array<int, array{uid: int, rank: int, nickname: string, level: int, serverid: int}>
     */
    private static function computeTopRank(int $ymd): array
    {
        // 取当日 0 点时间戳
        $time = (int)strtotime((string)$ymd);
        $serverid = Server::id();
        $top = [];
        for ($i = 1; $i <= 3; $i++) {
            $row = self::userByRank($i, $time);
            if ($row !== null) {
                $top[] = $row;
            }
        }
        $return = [];
        if (count($top) === 3) {
            foreach ($top as $value) {
                $return[$value['uid']] = $value;
            }
            $info = DB::table('member')->select('uid', 'nickname', 'level')->whereIn('uid', array_keys($return))->whereNull('deleted_at')->get();
            foreach ($info as $value) {
                $uid = (int)$value['uid'];
                if ((int)$value['level'] < self::LEVEL) {
                    unset($return[$uid]);
                } else {
                    $return[$uid]['nickname'] = (string)$value['nickname'];
                    $return[$uid]['level'] = (int)$value['level'];
                    $return[$uid]['serverid'] = $serverid;
                }
            }
            // 旧版 get_base_info 查不到的玩家（已删除）留下只有 uid / rank 的行；补齐字段，免得下游取不到
            foreach ($return as $uid => $value) {
                $return[$uid] += ['nickname' => '', 'level' => 0, 'serverid' => $serverid];
            }
        }
        return $return;
    }

    /**
     * get_user_by_rank：前一天内最后一次到达该名次的 arena_log，没有则取演武榜当前名次
     *
     * @return array{uid: int, rank: int}|null
     */
    private static function userByRank(int $rank, int $time): ?array
    {
        $row = DB::table('arena_log')->select('uid', 'rank')->where('rank', $rank)->where('dateline', '>', $time - 86400)
            ->where('dateline', '<', $time)->whereNull('deleted_at')->orderBy('id', 'DESC')->first();
        if ($row === null) {
            $row = DB::table('arena')->select('uid', 'rank')->where('rank', $rank)->whereNull('deleted_at')->first();
        }
        return $row === null ? null : ['uid' => (int)$row['uid'], 'rank' => (int)$row['rank']];
    }

    /**
     * 每日记录跨服资格名单（add_world_log）；旧版 insert_batch 重复执行会主键冲突，这里 INSERT IGNORE
     *
     * @param array<int, array<string, mixed>> $log
     */
    private static function addWorldLog(array $log, int $ymd): void
    {
        foreach ($log as $value) {
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_world_top_log` (`uid`, `ymd`, `serverid`, `rank`, `level`, `nickname`) VALUES (:uid, :ymd, :serverid, :rank, :level, :nickname)',
                ['uid' => $value['uid'], 'ymd' => $ymd, 'serverid' => $value['serverid'], 'rank' => $value['rank'], 'level' => $value['level'], 'nickname' => $value['nickname']]
            );
        }
    }

    /**
     * Redis 里的 JSON 键是字符串，转回 uid => 行
     *
     * @param array<int|string, mixed> $cache
     * @return array<int, array{uid: int, rank: int, nickname: string, level: int, serverid: int}>
     */
    private static function keyed(array $cache): array
    {
        $return = [];
        foreach ($cache as $value) {
            $return[(int)$value['uid']] = [
                'uid' => (int)$value['uid'],
                'rank' => (int)$value['rank'],
                'nickname' => (string)($value['nickname'] ?? ''),
                'level' => (int)($value['level'] ?? 0),
                'serverid' => (int)($value['serverid'] ?? 0),
            ];
        }
        return $return;
    }

    /**
     * 本服排行记录（get_top_log）：$ymd 当天或以后第一次进入前三的日期；没有为今天
     */
    public static function topLog(int $ymd, int $uid, int $now): int
    {
        $row = DB::table('world_top_log')->where('ymd', '>=', $ymd)->where('uid', $uid)->whereNull('deleted_at')->orderBy('ymd')->value('ymd');
        return $row !== null ? (int)$row : (int)self::date('Ymd', $now);
    }

    /**
     * 押注信息（get_bet_info：Redis，押注时写入一天）
     *
     * @return array<string, mixed>
     */
    public static function betInfo(int $uid): array
    {
        $cache = Redis::get(self::BET_KEY . $uid);
        return is_array($cache) ? $cache : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function setBetInfo(int $uid, array $data): void
    {
        Redis::set(self::BET_KEY . $uid, $data, 86400);
    }

    public static function delBetInfo(int $uid): void
    {
        Redis::del(self::BET_KEY . $uid);
    }

    /**
     * 记录跨服远征押注 log（record_bet_log）
     *
     * @param array<string, mixed> $data
     */
    public static function recordBetLog(array $data): void
    {
        DB::table('world_bet_log')->insert($data);
    }

    /**
     * 押注 log（get_bet_log）：$ymd（0 为今天）0 点以后的第一条
     *
     * @return array<string, mixed>
     */
    public static function betLog(int $uid, int $now, int $ymd = 0): array
    {
        $min = $ymd === 0 ? (int)strtotime(self::date('Ymd', $now)) : (int)strtotime((string)$ymd);
        $row = DB::table('world_bet_log')->select('uid', 'touid', 'serverid', 'nickname', 'rank', 'dateline')
            ->where('uid', $uid)->where('dateline', '>=', $min)->whereNull('deleted_at')->orderBy('dateline')->orderBy('id')->first();
        return $row ?? [];
    }

    /**
     * 银币奖励规则（get_silver_reward：名次变化超过 50 按 50）
     *
     * @return array{changed: int, reward: int, bet: int}|null
     */
    public static function silverReward(int $changed): ?array
    {
        if ($changed > 50) {
            $changed = 50;
        }
        return WorldSilver::ROWS[$changed] ?? null;
    }

    /**
     * 功勋奖励规则（get_exploit_reward：排名超过 300 按 300；没有规则为 0）
     */
    public static function exploitReward(int $rank): int
    {
        $rank = $rank > 300 ? 300 : $rank;
        return (int)(WorldExploit::ROWS[$rank]['exploit'] ?? 0);
    }

    /**
     * 跨服商城商品列表（get_item_list：旧版缓存在 Redis worldarena_shop，MasterData 直接读）
     *
     * @return list<array<string, mixed>>
     */
    public static function itemList(): array
    {
        return array_values(WorldShop::ROWS);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function itemById(int $itemid): ?array
    {
        return WorldShop::ROWS[$itemid] ?? null;
    }

    /**
     * 增加全服奖励信息（add_reward_info，使用跨服商城物品时）
     *
     * @param array<string, mixed> $data
     */
    public static function addRewardInfo(array $data): int
    {
        return (int)DB::table('world_reward')->insertGetId($data);
    }

    /**
     * 增加全服奖励领取 log（add_reward_log）：主键 uid + rewardid，重复领取插入失败回 0
     */
    public static function addRewardLog(int $uid, int $id, int $now): int
    {
        return DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_world_reward_log` (`uid`, `rewardid`, `dateline`) VALUES (:uid, :id, :now)',
            ['uid' => $uid, 'id' => $id, 'now' => $now]
        ) > 0 ? 1 : 0;
    }

    /**
     * 下一个全服奖励（get_next_reward：id 之后第一个不是自己发放的）
     *
     * @return array<string, mixed>
     */
    public static function nextReward(int $id, int $uid): array
    {
        return DB::table('world_reward')->select('id', 'uid', 'nickname', 'itemid', 'type', 'add', 'dateline')
            ->where('uid', '!=', $uid)->where('id', '>', $id)->whereNull('deleted_at')->orderBy('id')->first() ?? [];
    }

    /**
     * 最后一个全服奖励（get_last_reward）
     *
     * @return array<string, mixed>
     */
    public static function lastReward(): array
    {
        return DB::table('world_reward')->select('id', 'uid', 'nickname', 'itemid', 'type', 'add', 'dateline')
            ->whereNull('deleted_at')->orderBy('id', 'DESC')->first() ?? [];
    }

    /**
     * 更新全服奖励领取进度（member_model->update_server_reward）
     */
    public static function updateServerReward(int $uid, int $id): int
    {
        $updated = DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->update(['server_reward' => $id]);
        Redis::del('player:' . $uid);
        return $updated > 0 ? 1 : 0;
    }

    /**
     * 缓存跨服战报（cache_report_info，一天）
     *
     * @param array<string, mixed> $report
     */
    public static function cacheReportInfo(int $id, array $report): void
    {
        Redis::set(self::REPORT_KEY . $id, $report, 86400);
    }

    /**
     * 取跨服战报缓存（get_report_cache）
     *
     * @return array<string, mixed>
     */
    public static function reportCache(int $id): array
    {
        $cache = Redis::get(self::REPORT_KEY . $id);
        return is_array($cache) ? $cache : [];
    }

    /**
     * 玩家跨服信息（member_model->get_world_info；没有就建档 add_world_info：免费 10 次，领取时间为现在）
     *
     * @return array{uid: int, exploit: int, get_time: int, freetimes: int, buytimes: int, bettime: int, get_bet: int, freedateline: int}
     */
    public static function worldInfo(int $uid, int $now): array
    {
        $row = DB::table('member_world')->select('uid', 'exploit', 'get_time', 'freetimes', 'buytimes', 'bettime', 'get_bet', 'freedateline')
            ->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($row === null) {
            $row = ['uid' => $uid, 'exploit' => 0, 'get_time' => $now, 'freetimes' => 10, 'buytimes' => 0, 'bettime' => 0, 'get_bet' => $now];
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_member_world` (`uid`, `exploit`, `get_time`, `freetimes`, `buytimes`, `bettime`, `get_bet`)
                 VALUES (:uid, 0, :get_time, 10, 0, 0, :get_bet)',
                ['uid' => $uid, 'get_time' => $now, 'get_bet' => $now]
            );
            // 旧版 add_world_info 返回的数组没有 freedateline
            $row['freedateline'] = 0;
        }
        return array_map('intval', $row);
    }

    /**
     * 更新跨服信息（update_world_info）：有改动回 1
     *
     * @param array<string, int> $data
     */
    public static function updateWorldInfo(int $uid, array $data): int
    {
        return DB::table('member_world')->where('uid', $uid)->whereNull('deleted_at')->update($data) > 0 ? 1 : 0;
    }

    /**
     * 增加免费跨服远征次数（add_world_free：免费次数置为 10）
     */
    public static function addWorldFree(int $uid): int
    {
        return self::updateWorldInfo($uid, ['freetimes' => 10]);
    }
}
