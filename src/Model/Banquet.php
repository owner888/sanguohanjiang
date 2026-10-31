<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\CountryBanquet;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 国宴（移植旧版 models/banquet_model.php）
 *
 * 玩家记录存 country_banquet_log（每人一行：times 今日剩余次数（每天第一次打开时重置为 3）、status 1 已加入 / 2 队伍已解散 /
 * 3 宴会已完成待领奖、type 宴会类型、team 加入的队伍 id）。队伍同旧版存 Redis：banquet_team_{国家}_{队长uid}（JSON，24 小时），
 * 内容 type / name / nickname（队长）/ caption（队长 uid）/ number（满员人数）/ list（成员 uid、level、nickname）。
 * 宴会配置取 MasterData CountryBanquet（silver 为银币系数 × 主公等级）。
 *
 * 并发：队伍的读改写都在调用方持有的国家行锁里执行（Banquet::locked，同一国家的组队操作串行）。
 */
final class Banquet
{
    /** 默认每日赠送次数 */
    public const int TIMES = 3;

    /** 队伍过期时间 */
    private const int TTL = 86400;

    /**
     * 以国家行锁串行化同一国家的组队操作（多 Worker 安全）；调用方已在事务中时沿用
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function locked(int $country, callable $callback): mixed
    {
        $run = static function () use ($country, $callback): mixed {
            DB::query('SELECT `id` FROM `zc_sanguo_country` WHERE `id` = :id FOR UPDATE', ['id' => $country]);
            return $callback();
        };
        return DB::inTransaction() ? $run() : DB::transaction($run);
    }

    /**
     * 取用户基本数据（get_info：跨天重置次数；没有记录时建档）
     *
     * @return array{uid: int, times: int, team: int, status: int, type: int, dateline: int}
     */
    public static function info(int $uid, int $now): array
    {
        $info = self::memberInfo($uid);
        if ($info !== null) {
            // 判断是否要更新每日次数
            if (date('ymd', $now) !== date('ymd', $info['dateline'])) {
                DB::table('country_banquet_log')->where('uid', $uid)->whereNull('deleted_at')->update(['times' => self::TIMES, 'dateline' => $now]);
                $info['times'] = self::TIMES;
            }
            return $info;
        }
        // 添加用户数据
        $data = ['uid' => $uid, 'times' => self::TIMES, 'team' => 0, 'status' => 0, 'type' => 0, 'dateline' => $now];
        DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_country_banquet_log` (`uid`, `times`, `team`, `status`, `type`, `dateline`)
             VALUES (:uid, :times, :team, :status, :type, :dateline)',
            $data
        );
        return $data;
    }

    /**
     * 获取用户数据（get_member_info）；没有为 null
     *
     * @return array{uid: int, times: int, team: int, status: int, type: int, dateline: int}|null
     */
    public static function memberInfo(int $uid): ?array
    {
        $row = DB::table('country_banquet_log')->select('uid', 'times', 'team', 'status', 'type', 'dateline')
            ->where('uid', $uid)->whereNull('deleted_at')->first();
        return $row === null ? null : array_map('intval', $row);
    }

    /**
     * 宴会配置（get_banquet：silver 乘主公等级）；$id 为 0 时为全部（列表，下标从 0），否则为一行（没有为空数组）
     *
     * @return array<mixed>
     */
    public static function banquet(int $level, int $id = 0): array
    {
        $rows = [];
        foreach (CountryBanquet::ROWS as $row) {
            $row['silver'] = $row['silver'] * $level;
            $rows[$row['id']] = $row;
        }
        if ($id) {
            return $rows[$id] ?? [];
        }
        return array_values($rows);
    }

    /**
     * 国家的所有队伍（get_team_list）
     *
     * @return list<array<string, mixed>>
     */
    public static function teamList(int $country): array
    {
        $prefix = (string)Redis::instance()->getOption(\Redis::OPT_PREFIX);
        $return = [];
        $keys = Redis::keys(self::prefix($country) . '*');
        sort($keys);
        foreach ($keys as $key) {
            $team = Redis::get(substr((string)$key, strlen($prefix)));
            if (is_array($team)) {
                $return[] = $team;
            }
        }
        return $return;
    }

    /**
     * 按队伍 id 取队伍信息（get_team_info）；没有为空数组
     *
     * @return array<string, mixed>
     */
    public static function teamInfo(int $country, int $id): array
    {
        $info = Redis::get(self::prefix($country) . $id);
        return is_array($info) ? $info : [];
    }

    /**
     * 创建队伍（create_team）
     *
     * @param array<string, mixed> $member
     * @param array<string, mixed> $banquet
     * @return array<string, mixed>
     */
    public static function createTeam(array $member, array $banquet): array
    {
        $data = [
            'type' => $banquet['id'],
            'name' => $banquet['name'],
            'nickname' => (string)$member['nickname'],
            'caption' => (int)$member['uid'],
            'number' => $banquet['number'],
            'list' => [
                0 => ['uid' => (int)$member['uid'], 'level' => (int)$member['level'], 'nickname' => (string)$member['nickname']],
            ],
        ];
        Redis::set(self::prefix((int)$member['country']) . $member['uid'], $data, self::TTL);
        // 创建成功，修改用户的状态
        self::updateTeam([(int)$member['uid']], (int)$member['uid'], 1, $banquet['id']);
        return $data;
    }

    /**
     * 修改用户的队伍状态（update_team：状态 1 已加入，2 已解散，3 已完成；0 空闲）
     *
     * @param list<int> $uids
     */
    public static function updateTeam(array $uids, int $team = 0, int $status = 0, int $type = 0): void
    {
        if ($uids === []) {
            return;
        }
        DB::table('country_banquet_log')->whereIn('uid', $uids)->whereNull('deleted_at')
            ->update(['team' => $team, 'status' => $status, 'type' => $type]);
    }

    /**
     * 解散队伍（dismiss_team：其他成员改为已解散，$actorUid 改为空闲）
     */
    public static function dismissTeam(int $country, int $id, int $actorUid): void
    {
        $team = self::teamInfo($country, $id);
        // 删除队伍
        Redis::del(self::prefix($country) . $id);
        // 把队伍中所有人的状态改成已解散
        $uids = [];
        foreach ($team['list'] ?? [] as $val) {
            if ((int)$val['uid'] !== $actorUid) {
                $uids[] = (int)$val['uid'];
            }
        }
        self::updateTeam($uids, 0, 2);
        // 把自己的状态改成空闲
        self::updateTeam([$actorUid]);
    }

    /**
     * 退出 / 踢出队伍（gotout_team）
     *
     * @return array{status: int, team: array<string, mixed>} status 1 成功；-1 队伍已解散；-2 不在队伍中
     */
    public static function gotoutTeam(int $country, int $id, int $uid): array
    {
        $return = ['status' => 0, 'team' => []];
        $team = self::teamInfo($country, $id);
        if ($team === []) {
            $return['status'] = -1;
            return $return;
        }
        // 取出在队伍中的key
        $delKey = -1;
        foreach ($team['list'] as $key => $val) {
            if ((int)$val['uid'] === $uid) {
                $delKey = $key;
            }
        }
        if ($delKey === -1) {
            $return['status'] = -2;
            return $return;
        }
        unset($team['list'][$delKey]);
        // 得到删除过后的新列表（旧版 sort()：成员数组按第一个字段 uid 升序重排）
        $team['list'] = array_values($team['list']);
        usort($team['list'], static fn (array $a, array $b): int => (int)$a['uid'] <=> (int)$b['uid']);
        Redis::set(self::prefix($country) . $id, $team, self::TTL);
        // 修改队伍状态
        self::updateTeam([$uid]);
        $return['status'] = 1;
        $return['team'] = $team;
        return $return;
    }

    /**
     * 加入队伍（join_team）；队伍不存在为空数组
     *
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    public static function joinTeam(array $member, int $id): array
    {
        $country = (int)$member['country'];
        $team = self::teamInfo($country, $id);
        if ($team === []) {
            return [];
        }
        $team['list'][count($team['list'])] = ['uid' => (int)$member['uid'], 'nickname' => (string)$member['nickname'], 'level' => (int)$member['level']];
        Redis::set(self::prefix($country) . $id, $team, self::TTL);
        // 修改我的队伍状态
        self::updateTeam([(int)$member['uid']], $id, 1, (int)$team['type']);
        return $team;
    }

    /**
     * 开始宴会，删除队伍（del_team：除 $actorUid 外的成员改为已完成待领奖）
     */
    public static function delTeam(int $country, int $id, int $actorUid): void
    {
        $team = self::teamInfo($country, $id);
        Redis::del(self::prefix($country) . $id);
        if ($team !== []) {
            $uids = [];
            foreach ($team['list'] as $val) {
                if ((int)$val['uid'] !== $actorUid) {
                    $uids[] = (int)$val['uid'];
                }
            }
            self::updateTeam($uids, 0, 3, (int)$team['type']);
        }
    }

    /**
     * 减少次数（sub_times：直接写剩余次数）
     */
    public static function subTimes(int $uid, int $times): void
    {
        DB::table('country_banquet_log')->where('uid', $uid)->whereNull('deleted_at')->update(['times' => max(0, $times)]);
    }

    /**
     * 退出国家时修复宴会（repaire_banquet：队长解散队伍，成员退出队伍；$country 为原国家，$actorUid 为当前操作的玩家）
     */
    public static function repair(int $country, int $uid, int $actorUid): void
    {
        if ($uid <= 0) {
            return;
        }
        $info = self::memberInfo($uid);
        if ($info !== null && $info['team'] > 0 && $info['status'] === 1) {
            $team = self::teamInfo($country, $info['team']);
            if ($team !== []) {
                if ((int)$team['caption'] === $uid) {
                    // 解散队伍
                    self::dismissTeam($country, $info['team'], $actorUid);
                } else {
                    // 退出队伍
                    self::gotoutTeam($country, $info['team'], $uid);
                }
            }
        }
    }

    private static function prefix(int $country): string
    {
        return 'banquet_team_' . $country . '_';
    }
}
