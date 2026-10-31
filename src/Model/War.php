<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\Cross\WarServer;
use Hanjiang\MasterData\WarShop;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 武斗会的游戏服务器端数据（移植旧版 s1 models/war_model.php）
 *
 * 表：war_reward_log（奖励领取记录）、war_shop_log（积分商城购买日志）；积分商城配置 MasterData WarShop。
 * Redis 键同旧版：war_entry_{uid}（报名标记 10 天）、war_limit_level_（报名等级 1 天）、war_end_stage_（比赛结束标志 1 天）、
 * war_sync_{uid}（同步间隔 5 秒）、war_report_info_{id}_{stage}（战报 15 天）、war_report_status_{id}_{stage}（取战报状态 30 秒）、
 * get_share_info_{id}_{stage}（5 分钟内分享过；旧版与战神殿共用这个前缀，照搬）、war_support_{id}（支持数 5 秒）、
 * point_buy_lock_{uid}（积分购买锁 30 秒）。
 */
final class War
{
    private const int TTL = 86400;
    private const string STAGE_KEY = 'war_end_stage_';
    private const string LEVEL_KEY = 'war_limit_level_';
    private const string SYNC_KEY = 'war_sync_';
    private const string REPORT_KEY = 'war_report_info_';
    private const string REPORT_STATUS_KEY = 'war_report_status_';
    private const string SHARE_KEY = 'get_share_info_';
    private const string ENTRY_KEY = 'war_entry_';
    private const string SUPPORT_KEY = 'war_support_';
    private const int SUPPORT_TTL = 5;
    public const string POINT_BUY_LOCK = 'point_buy_lock_';

    /**
     * add_entry_key：报名标记（10 天）
     */
    public static function addEntryKey(int $uid): void
    {
        Redis::set(self::ENTRY_KEY . $uid, 1, 864000);
    }

    public static function isEntry(int $uid): bool
    {
        return Redis::exists(self::ENTRY_KEY . $uid);
    }

    /**
     * get_entry_level：报名等级（缓存 1 天，没有缓存时向服务器端取 limit_level）；取不到为 0
     */
    public static function entryLevel(): int
    {
        $level = Redis::get(self::LEVEL_KEY);
        if ($level === null) {
            $uc = WarServer::index(0, 0);
            if (isset($uc['limit_level'])) {
                $level = (int)$uc['limit_level'];
                Redis::set(self::LEVEL_KEY, $level, self::TTL);
            }
        }
        return (int)$level;
    }

    /**
     * set_end_stage：比赛结束标志（全服一个键，1 天）
     */
    public static function setEndStage(): void
    {
        if (!self::endStage()) {
            Redis::set(self::STAGE_KEY, 1, self::TTL);
        }
    }

    public static function endStage(): bool
    {
        return Redis::exists(self::STAGE_KEY);
    }

    /**
     * get_reward_log：本月的领取记录（$id 为数组时按 rewardid 索引）
     *
     * @param int|list<int> $id
     * @return array<int|string, mixed>
     */
    public static function rewardLog(int $uid, int|array $id, int $type, int $now): array
    {
        $ids = is_array($id) ? $id : [$id];
        if ($ids === []) {
            return [];
        }
        $rows = DB::table('war_reward_log')->where('phase', (int)date('Ym', $now))->where('uid', $uid)->whereIn('rewardid', $ids)
            ->where('type', $type)->whereNull('deleted_at')->orderBy('id')->get();
        if (!is_array($id)) {
            return $rows[0] ?? [];
        }
        $return = [];
        foreach ($rows as $value) {
            $return[(int)$value['rewardid']] = $value;
        }
        return $return;
    }

    /**
     * is_sync：5 秒内同步过
     */
    public static function isSync(int $uid): bool
    {
        $redis = Redis::instance();
        return $redis !== null && $redis->ttl(self::SYNC_KEY . $uid) > 0;
    }

    public static function sync(int $uid, int $now): void
    {
        Redis::set(self::SYNC_KEY . $uid, $now, 5);
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
        $report = Redis::get(self::REPORT_KEY . $id . '_' . $stage);
        return is_array($report) ? $report : [];
    }

    /**
     * add_report_status：正在取战报（30 秒）
     */
    public static function addReportStatus(int $id, int $stage): void
    {
        Redis::set(self::REPORT_STATUS_KEY . $id . '_' . $stage, 1, 30);
    }

    public static function setShareCache(int $id, int $stage): void
    {
        Redis::set(self::SHARE_KEY . $id . '_' . $stage, 1, 300);
    }

    public static function shareCache(int $id, int $stage): bool
    {
        return Redis::exists(self::SHARE_KEY . $id . '_' . $stage);
    }

    /**
     * @return array<string, mixed>
     */
    public static function supportCache(int $id): array
    {
        $cache = Redis::get(self::SUPPORT_KEY . $id);
        return is_array($cache) ? $cache : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function setSupportCache(int $id, array $data): void
    {
        if ($data !== []) {
            Redis::set(self::SUPPORT_KEY . $id, $data, self::SUPPORT_TTL);
        }
    }

    /**
     * get_point_list：积分商城（$id 为 0 取全部，否则取一行；没有为空数组）
     *
     * @return array<int|string, mixed>
     */
    public static function pointList(int $id = 0): array
    {
        if ($id > 0) {
            return WarShop::ROWS[$id] ?? [];
        }
        return array_values(WarShop::ROWS);
    }

    public static function addShopLog(int $uid, int $wid, int $now): int
    {
        return (int)DB::table('war_shop_log')->insertGetId(['uid' => $uid, 'wid' => $wid, 'dateline' => $now]) > 0 ? 1 : 0;
    }

    /**
     * get_lock：锁存在为 true
     */
    public static function getLock(string $key, int $uid): bool
    {
        return (bool)Redis::get($key . $uid);
    }

    /**
     * add_lock：加锁（30 秒）。旧版先 get 再 setex，两个请求可能同时拿到；这里用 SET NX，没拿到返回 false
     */
    public static function addLock(string $key, int $uid): bool
    {
        $redis = Redis::instance();
        return $redis !== null && (bool)$redis->set($key . $uid, '1', ['nx', 'ex' => 30]);
    }

    public static function unlock(string $key, int $uid): void
    {
        Redis::del($key . $uid);
    }
}
