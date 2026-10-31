<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\Cross\GodServer;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 战神殿的本服部分（移植旧版 s1 models/god_model.php + member_model->get_god）
 *
 * 报名标记（Redis god_entry_{uid}）、报名等级缓存、比赛结束标记、同步间隔、战报 / 分享 / 支持数缓存、奖励领取记录
 * （god_reward_log）、本服战帝信息（god_palace / Redis god_palace_{期数}）、当前战帝（Redis god_info）。
 */
final class God
{
    private const string STAGE_KEY = 'god_end_stage_';
    private const string LEVEL_KEY = 'god_limit_level_';
    private const string SYNC_KEY = 'god_sync_';
    private const string REPORT_KEY = 'god_report_info_';
    private const string SHARE_KEY = 'get_share_info_';
    private const string ENTRY_KEY = 'god_entry_';
    private const string SUPPORT_KEY = 'god_support_';
    private const string PALACE_KEY = 'god_palace_';
    private const string GOD_INFO_KEY = 'god_info';

    /** 战神殿奖励预览开启等级（_limit_level） */
    public const int LIMIT_LEVEL = 60;

    /**
     * 奖励预览图标（_open）与报名倒计时（_entry_cd）：每月 1 日 0 点到 15 日 18 点，60 级以上
     *
     * @return array{open: int, entry_cd: int}
     */
    public static function open(int $now, int $level): array
    {
        $start = (int)strtotime(date('Y-m-', $now) . '01 00:00:00');
        $end = (int)strtotime(date('Y-m-', $now) . '15 18:00:00');
        if ($now >= $start && $now <= $end && $level >= self::LIMIT_LEVEL) {
            return ['open' => 1, 'entry_cd' => $end - $now];
        }
        return ['open' => 0, 'entry_cd' => 0];
    }

    /** 添加报名标记（10 天） */
    public static function addEntryKey(int $uid): void
    {
        Redis::set(self::ENTRY_KEY . $uid, 1, 864000);
    }

    /** 是否已报名（本服标记） */
    public static function isEntry(int $uid): bool
    {
        return Redis::exists(self::ENTRY_KEY . $uid);
    }

    /**
     * 报名等级（get_entry_level：缓存一天，没有时取战斗服务器 index 的 limit_level）
     */
    public static function entryLevel(): int
    {
        $level = Redis::get(self::LEVEL_KEY);
        if ($level === null) {
            $level = GodServer::LIMIT_LEVEL;
            Redis::set(self::LEVEL_KEY, $level, 86400);
        }
        return (int)$level;
    }

    /** 设置比赛结束标志（set_end_stage，一天） */
    public static function setEndStage(): void
    {
        if (!self::endStage()) {
            Redis::set(self::STAGE_KEY, 1, 86400);
        }
    }

    public static function endStage(): bool
    {
        return Redis::exists(self::STAGE_KEY);
    }

    /** 是否刚同步过（issync：5 秒内） */
    public static function isSync(int $uid): bool
    {
        return (int)Redis::ttl(self::SYNC_KEY . $uid) > 0;
    }

    public static function sync(int $uid, int $now): void
    {
        Redis::set(self::SYNC_KEY . $uid, $now, 5);
    }

    /**
     * 领取记录（get_reward_log，本月）：$id 为数组时按 rewardid 索引的多行，否则一行；没有为空数组
     *
     * @param int|list<int> $id
     * @return array<int|string, mixed>
     */
    public static function rewardLog(int $uid, int|array $id, int $type, int $now): array
    {
        $query = DB::table('god_reward_log')->where('phase', (int)date('Ym', $now))->where('uid', $uid)->where('type', $type)->whereNull('deleted_at');
        if (is_array($id)) {
            if ($id === []) {
                return [];
            }
            $return = [];
            foreach ($query->whereIn('rewardid', $id)->get() as $row) {
                $return[(int)$row['rewardid']] = $row;
            }
            return $return;
        }
        return $query->where('rewardid', $id)->first() ?? [];
    }

    /**
     * 添加领取记录（add_reward_log）
     *
     * @param array<string, mixed> $reward
     */
    public static function addRewardLog(int $uid, int $id, int $type, array $reward, int $dateline): int
    {
        return DB::table('god_reward_log')->insert([
            'phase' => (int)date('Ym', $dateline),
            'rewardid' => $id,
            'uid' => $uid,
            'type' => $type,
            'gold' => (int)($reward['gold'] ?? 0),
            'silver' => (int)($reward['silver'] ?? 0),
            'jade' => (int)($reward['jade'] ?? 0),
            'tavernjade' => (int)($reward['tavernjade'] ?? 0),
            'soul' => (int)($reward['soul'] ?? 0),
            'equip' => isset($reward['equip_list']) ? implode(',', $reward['equip_list']) : '0',
            'gem' => (int)($reward['gem'] ?? 0),
            'reputation' => (int)($reward['reputation'] ?? 0),
            'mount' => isset($reward['mount_list']) ? implode(',', $reward['mount_list']) : '0',
            'dateline' => $dateline,
        ]) ? 1 : 0;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function cacheReport(int $id, int $stage, array $report): void
    {
        Redis::set(self::REPORT_KEY . $id . '_' . $stage, $report, 86400 * 15);
    }

    /**
     * @return array<string, mixed>
     */
    public static function reportCache(int $id, int $stage): array
    {
        $cache = Redis::get(self::REPORT_KEY . $id . '_' . $stage);
        return is_array($cache) ? $cache : [];
    }

    /** 分享过的战报（5 分钟内同一局只广播一次） */
    public static function setShareCache(int $id, int $stage): void
    {
        Redis::set(self::SHARE_KEY . $id . '_' . $stage, 1, 300);
    }

    public static function shareCache(int $id, int $stage): bool
    {
        return Redis::exists(self::SHARE_KEY . $id . '_' . $stage);
    }

    /**
     * 支持数缓存（5 秒）
     *
     * @return array<string, int>
     */
    public static function supportCache(int $id): array
    {
        $cache = Redis::get(self::SUPPORT_KEY . $id);
        return is_array($cache) ? $cache : [];
    }

    /**
     * @param array<string, int> $data
     */
    public static function setSupportCache(int $id, array $data): void
    {
        if ($data !== []) {
            Redis::set(self::SUPPORT_KEY . $id, $data, 5);
        }
    }

    /**
     * 当前战帝（member_model->get_god：Redis god_info；没有时 uid 0）
     *
     * @return array<string, mixed>
     */
    public static function getGod(): array
    {
        $result = Redis::get(self::GOD_INFO_KEY);
        if (is_array($result) && $result !== []) {
            return $result;
        }
        return ['uid' => 0, 'serverid' => 0, 'title' => '', 'nickname' => ''];
    }

    /**
     * 缓存战帝信息（cache_god_info：一天；14、15 日缓存到 15 日 18 点，新一届战帝产生前失效）
     *
     * @param array<string, mixed> $god
     */
    public static function cacheGodInfo(array $god, int $now): void
    {
        $ttl = 86400;
        if (in_array((int)date('d', $now), [14, 15], true)) {
            $ttl = (int)strtotime(date('Ym', $now) . '15 18:00') - $now;
        }
        if ($ttl > 0) {
            Redis::set(self::GOD_INFO_KEY, $god, $ttl);
        }
    }

    /**
     * 本服记录的本期战帝（get_place_info：god_palace，缓存一天）：servername / nickname / chapter / chaptername；没有为空数组
     *
     * 旧版由计划任务 cron_acttask::setplace_info 从战斗服务器 c=god&m=get_king 取来写入，但旧版 god 控制器里没有 get_king，
     * 该任务永远取不到（「战帝信息不存在」），本表始终为空；照旧版只读不写。
     *
     * @return array<string, mixed>
     */
    public static function placeInfo(int $now): array
    {
        $phase = (int)date('Ym', $now);
        $cache = Redis::get(self::PALACE_KEY . $phase);
        if (is_array($cache)) {
            return $cache;
        }
        $row = DB::table('god_palace')->select('servername', 'nickname', 'chapter', 'name')->where('phase', $phase)->whereNull('deleted_at')
            ->orderBy('dateline', 'DESC')->first();
        if ($row === null) {
            return [];
        }
        $return = [
            'servername' => (string)$row['servername'],
            'nickname' => (string)$row['nickname'],
            'chapter' => (int)$row['chapter'],
            'chaptername' => (string)$row['name'],
        ];
        Redis::set(self::PALACE_KEY . $phase, $return, 86400);
        return $return;
    }
}
