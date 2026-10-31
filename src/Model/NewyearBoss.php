<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\NewyearBossRank;
use Hanjiang\MasterData\WorldBoss as WorldBossTemplate;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 春节年兽状态（移植旧版 models/newyearboss_model.php）
 *
 * 每天 12:30 ~ 13:00（30 分钟）开打，BOSS 为 world_boss 行 3（年兽）+ 写死的战斗数值（血量 2 万亿）。每日状态同旧版放 Redis，
 * 键名按日期分期（_set_phase：newyearboss_{Ymd}_*，OmniPHP Redis 自动加 hj: 前缀），12 小时过期：个人累计伤害 my_hit_{uid}
 * （复活 CD 由它的剩余 TTL 推算）、鼓舞 power_up_{uid}、闪避 dodge_{uid}、元宝鼓舞 / 复活次数 powerup_times_{uid} /
 * relive_times_{uid}、最后攻击者 final_uid、玩家活动累计信息的当日快照 last_info{uid}（旧版键名在 last_info 与 uid 之间没有下划线）；
 * BOSS 信息 newyearboss_boss_info 不分期（打过后 30 分钟过期）、战斗锁 newyearboss_hit_lock_{uid} 1 秒。
 * 整个活动期的累计伤害 / 闪避 / 元宝鼓舞次数记 newyear_boss_log（每期每人一行），排行奖励与每日成就领取记 newyear_boss_reward_log。
 *
 * 同 Model\WorldBoss 的多进程改法：伤害 INCRBY；伤害排行由 KEYS 全量扫描 + 缓存（hit_rank / rank_uid / hit_top / my_rank）改为
 * 同名有序集合 newyearboss_{Ymd}_hit_rank（ZINCRBY，前 1000 名）；BOSS 信息改为 Hash（缺失时 Lua 原子建档）；鼓舞用 Lua
 * 「未满上限才 +10」。旧版 get_boss_info 的建档锁 newyearboss_lock + sleep 等待不移植。
 */
final class NewyearBoss
{
    public const string ACTIVITY = 'newyearboss';

    /** 活动开始时间（控制器 _start_time） */
    public const string START_TIME = '123000';
    /** 活动时长（_time_range） */
    public const int TIME_RANGE = 1800;
    /** 排行 / 个人数据 redis 过期时间 */
    public const int REDIS_EXPIRE = 43200;
    /** 一次鼓舞增幅 */
    public const int POWER_UP_STEP = 10;

    /** 排行榜长度 / TOP 人数 */
    private const int HIT_RANK_NUM = 1000;

    /** get_boss_info：world_boss 行 id 3（年兽）+ 写死的战斗数值 */
    private const int BOSS_ID = 3;
    private const int BOSS_BLOOD = 2000000000000;
    private const int BOSS_ATTACK = 1000000000;

    private const string BOSS_INFO_KEY = 'newyearboss_boss_info';
    private const string HIT_LOCK_KEY = 'newyearboss_hit_lock_';

    /** 旧版 set_phase 的键名：newyearboss_{Ymd}_{name} */
    private static function key(int $now, string $name): string
    {
        return 'newyearboss_' . date('Ymd', $now) . '_' . $name;
    }

    /**
     * 活动倒计时（activity_countdown）：-1 未开启；0 已结束；> 0 进行中的剩余秒数
     */
    public static function activityCountdown(int $now): int
    {
        // 旧版 mktime(时, 分, 0)：当天的开始时间
        $starttime = mktime((int)substr(self::START_TIME, 0, 2), (int)substr(self::START_TIME, 2, 2), 0, (int)date('n', $now), (int)date('j', $now), (int)date('Y', $now));
        $time = $now - $starttime;
        if ($time < 0) {
            return -1;
        }
        if ($time > self::TIME_RANGE) {
            return 0;
        }
        return self::TIME_RANGE - $time;
    }

    /**
     * 取 BOSS 信息（get_boss_info）：没有缓存时按 world_boss 行 3 建档，满血
     *
     * @return array<string, int|string>
     */
    public static function bossInfo(): array
    {
        $row = WorldBossTemplate::ROWS[self::BOSS_ID] ?? null;
        if ($row === null) {
            return [];
        }
        $info = $row;
        foreach ([
            'blood' => self::BOSS_BLOOD,
            'allblood' => self::BOSS_BLOOD,
            'wuli' => 0,
            'zhili' => 0,
            'type' => 1,
            'attack' => self::BOSS_ATTACK,
            'pdefense' => 0,
            'mdefense' => 0,
            'talentid' => 1,
            'skillid' => 1,
        ] as $key => $value) {
            $info[$key] = $value;
        }
        return WorldBoss::createHash(self::BOSS_INFO_KEY, $info, self::REDIS_EXPIRE);
    }

    /**
     * 当前鼓舞加成（get_power_up），没鼓舞过为 100
     */
    public static function powerUp(int $uid, int $now): int
    {
        $powerup = Redis::get(self::key($now, 'power_up_') . $uid);
        return $powerup === null || $powerup === false ? 100 : (int)$powerup;
    }

    /**
     * 鼓舞一次（power_up）：+10（没鼓舞过从 100 起），12 小时过期；已到 $max 时返回 null（控制器先判断上限，并发时由调用方退款）
     */
    public static function incrPowerUp(int $uid, int $now, int $max): ?int
    {
        return WorldBoss::luaPowerUp(self::key($now, 'power_up_') . $uid, self::POWER_UP_STEP, self::REDIS_EXPIRE, $max);
    }

    /**
     * 距上次攻击的秒数（check_delay）：没打过为 0
     */
    public static function checkDelay(int $uid, int $now): int
    {
        $ttl = (int)Redis::ttl(self::key($now, 'my_hit_') . $uid);
        if ($ttl < 0) {
            return 0;
        }
        return self::REDIS_EXPIRE - $ttl;
    }

    /**
     * 今日累计伤害（get_my_hit）
     */
    public static function myHit(int $uid, int $now): int
    {
        return self::counter(self::key($now, 'my_hit_') . $uid);
    }

    /**
     * 今日闪避次数（get_dodge_times）
     */
    public static function dodgeTimes(int $uid, int $now): int
    {
        return self::counter(self::key($now, 'dodge_') . $uid);
    }

    /**
     * 今日元宝鼓舞次数（get_powerup_times）
     */
    public static function powerupTimes(int $uid, int $now): int
    {
        return self::counter(self::key($now, 'powerup_times_') . $uid);
    }

    /**
     * 今日元宝鼓舞次数 + 1（add_powerup_times）
     */
    public static function addPowerupTimes(int $uid, int $now): void
    {
        self::incr(self::key($now, 'powerup_times_') . $uid, 1);
    }

    /**
     * 今日元宝复活次数（get_relive_times）
     */
    public static function reliveTimes(int $uid, int $now): int
    {
        return self::counter(self::key($now, 'relive_times_') . $uid);
    }

    /**
     * 今日元宝复活次数 + 1（add_relive_times）
     */
    public static function addReliveTimes(int $uid, int $now): void
    {
        self::incr(self::key($now, 'relive_times_') . $uid, 1);
    }

    /**
     * 记录伤害（update_my_hit）：最后攻击者、BOSS 血量、今日累计伤害 / 闪避，并把活动累计（当日快照 + 今日）写回 newyear_boss_log
     *
     * @param array<string, mixed> $lastInfo get_member_last_info 的结果
     */
    public static function updateMyHit(int $version, int $uid, int $hit, int $dodge, array $lastInfo, int $countdown, int $now): void
    {
        // 更新最后一击
        Redis::set(self::key($now, 'final_uid'), $uid, self::REDIS_EXPIRE);
        // 更新boss剩余血量
        self::updateBossBlood($countdown);
        $data = [];
        $lastHit = (int)($lastInfo['hit'] ?? 0);
        $lastDodge = (int)($lastInfo['dodge'] ?? 0);
        // 个人伤害记录
        $value = self::incr(self::key($now, 'my_hit_') . $uid, $hit);
        $data['hit'] = $value + $lastHit;
        $rankKey = self::key($now, 'hit_rank');
        Redis::zIncrBy($rankKey, $hit, (string)$uid);
        Redis::expire($rankKey, self::REDIS_EXPIRE);
        // 闪避次数记录
        if ($dodge > 0) {
            $value = self::incr(self::key($now, 'dodge_') . $uid, $dodge);
            $data['dodge'] = $value + $lastDodge;
        }
        // 修改数据库中的数据
        self::updateMemberInfo($version, $uid, $data, $now);
    }

    /**
     * 更新用户数据（update_member_info）：总写 dateline；hit / dodge / powerup 大于 0 才写
     *
     * @param array<string, int> $info
     */
    public static function updateMemberInfo(int $version, int $uid, array $info, int $now): void
    {
        $data = ['dateline' => $now];
        foreach (['hit', 'dodge', 'powerup'] as $field) {
            if (isset($info[$field]) && $info[$field] > 0) {
                $data[$field] = $info[$field];
            }
        }
        DB::table('newyear_boss_log')->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->update($data);
    }

    /**
     * 玩家活动累计信息的当日快照（get_member_last_info）：今天第一次取时读 newyear_boss_log（没有就建档）并缓存 12 小时
     *
     * @return array<string, mixed>
     */
    public static function memberLastInfo(int $version, int $uid, string $nickname, int $now): array
    {
        $key = self::key($now, 'last_info') . $uid;
        $cached = Redis::get($key);
        if (is_string($cached) && $cached !== '') {
            $decoded = json_decode($cached, true);
            return is_array($decoded) ? $decoded : [];
        }
        $return = self::memberInfo($version, $uid);
        if ($return === []) {
            // 唯一键 version + uid：并发建档只插入一行
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_newyear_boss_log` (`version`, `uid`, `nickname`, `hit`, `dodge`, `powerup`, `dateline`)
                 VALUES (:version, :uid, :nickname, 0, 0, 0, :now)',
                ['version' => $version, 'uid' => $uid, 'nickname' => $nickname, 'now' => $now]
            );
            $return = self::memberInfo($version, $uid);
        }
        // 设置缓存
        Redis::set($key, (string)json_encode($return), self::REDIS_EXPIRE);
        return $return;
    }

    /**
     * 玩家活动累计信息（get_member_info），没有为空数组
     *
     * @return array<string, mixed>
     */
    public static function memberInfo(int $version, int $uid): array
    {
        $row = DB::table('newyear_boss_log')->select('id', 'version', 'uid', 'nickname', 'hit', 'dodge', 'powerup', 'dateline')
            ->where('uid', $uid)->where('version', $version)->whereNull('deleted_at')->orderBy('id')->first();
        return $row === null ? [] : self::logRow($row);
    }

    /**
     * 今日伤害排行前 $limit 名（_get_hit_rank / get_top_rank），[{uid, hit, rank}]
     *
     * @return list<array{uid: string, hit: string, rank: int}>
     */
    public static function topRank(int $now, int $limit): array
    {
        return WorldBoss::zsetRank(self::key($now, 'hit_rank'), min($limit, self::HIT_RANK_NUM));
    }

    /**
     * 今日名次（get_my_rank）：有伤害记录且在前 1000 名内，否则 0
     */
    public static function myRank(int $uid, int $now): int
    {
        if (self::myHit($uid, $now) === 0) {
            return 0;
        }
        return WorldBoss::zsetMyRank(self::key($now, 'hit_rank'), $uid, self::HIT_RANK_NUM);
    }

    /**
     * 活动最终排行（get_rank_list）：$type 1 伤害、2 元宝鼓舞、3 闪避；rank 为自己的名次（数值为 0 时 0），list 为前 $limit 名
     *
     * 旧版名次 SQL 的并列条件写成「字段 = .数值」（小数 0.数值，永远不等），所以名次 = 数值比自己大的人数 + 1，照旧版。
     *
     * @return array{rank: int, list: list<array<string, mixed>>}
     */
    public static function rankList(int $version, int $uid, int $type, int $limit = 50): array
    {
        $key = self::rankField($type);
        $member = self::memberInfo($version, $uid);
        $value = (int)($member[$key] ?? 0);
        $rank = 0;
        if ($value !== 0) {
            $rank = DB::table('newyear_boss_log')->where($key, '>', 0)->where('version', $version)->where($key, '>', $value)
                ->whereNull('deleted_at')->count() + 1;
        }
        $rows = DB::table('newyear_boss_log')->select('id', 'version', 'uid', 'nickname', 'hit', 'dodge', 'powerup', 'dateline')
            ->where($key, '>', 0)->where('version', $version)->whereNull('deleted_at')
            ->orderBy($key, 'DESC')->orderBy('dateline')->orderBy('id')->limit($limit)->get();
        $list = [];
        foreach ($rows as $i => $row) {
            $list[] = self::logRow($row) + ['rank' => $i + 1];
        }
        return ['rank' => $rank, 'list' => $list];
    }

    /**
     * 排行类型对应的字段（1 伤害 hit、2 鼓舞 powerup、3 闪避 dodge）
     */
    public static function rankField(int $type): string
    {
        return match ($type) {
            2 => 'powerup',
            3 => 'dodge',
            default => 'hit',
        };
    }

    /**
     * 根据排名获取奖励信息（get_reward_by_rank）：名次不小于某行 rank 时取该行的 tab{$type} / value{$type}
     *
     * @return array{type: int, value: int}
     */
    public static function rewardByRank(int $type, int $rank): array
    {
        $return = ['type' => 0, 'value' => 0];
        if ($rank <= 0) {
            return $return;
        }
        $reward = null;
        foreach (NewyearBossRank::ROWS as $val) {
            if ($rank >= $val['rank']) {
                $reward = $val;
            }
        }
        if ($reward !== null) {
            $return['type'] = (int)$reward['tab' . $type];
            $return['value'] = (int)$reward['value' . $type];
        }
        return $return;
    }

    /**
     * 奖励领取记录（get_reward_log）：$type 1 每日成就（只查今天）、2 排行；$tab 为排行 / 成就类型
     *
     * $type 2 或给了 $id 时返回一行（没有为空数组），否则 [value => 行]。
     *
     * @return array<int|string, mixed>
     */
    public static function rewardLog(int $version, int $uid, int $tab, int $type, int $now, int $id = 0): array
    {
        $query = DB::table('newyear_boss_reward_log')->where('uid', $uid)->where('version', $version)->where('type', $type)->where('tab', $tab)
            ->whereNull('deleted_at');
        if ($id > 0) {
            $query->where('value', $id);
        }
        if ($type === 1) {
            $starttime = (int)strtotime(date('Ymd', $now));
            $query->where('dateline', '>=', $starttime)->where('dateline', '<', $starttime + 86400);
        }
        $rows = $query->orderBy('id')->get();
        if ($rows === []) {
            return [];
        }
        if ($type === 2 || $id > 0) {
            return $rows[0];
        }
        $return = [];
        foreach ($rows as $val) {
            $return[(int)$val['value']] = $val;
        }
        return $return;
    }

    /**
     * 添加奖励领取记录（add_reward_log），返回 1 成功
     */
    public static function addRewardLog(int $version, int $uid, int $tab, int $type, int $id, int $now): int
    {
        return DB::table('newyear_boss_reward_log')->insert([
            'version' => $version,
            'uid' => $uid,
            'type' => $type,
            'tab' => $tab,
            'value' => $id,
            'dateline' => $now,
        ]) ? 1 : 0;
    }

    /**
     * 战斗处理锁（battle_lock_check + battle_lock，1 秒）：拿到锁返回 true
     */
    public static function battleLock(int $uid): bool
    {
        return WorldBoss::lock(self::HIT_LOCK_KEY . $uid);
    }

    /**
     * 更新 BOSS 剩余血量（_update_boss_blood）：按剩余时间自然减少，与伤害无关；BOSS 信息改为 30 分钟过期
     */
    private static function updateBossBlood(int $countdown): void
    {
        $allblood = Redis::hGet(self::BOSS_INFO_KEY, 'allblood');
        $blood = (int)($countdown / self::TIME_RANGE * ($allblood === false ? 0 : (int)$allblood)) - mt_rand(0, 1000);
        Redis::instance()->eval(
            "if redis.call('EXISTS', KEYS[1]) == 1 then redis.call('HSET', KEYS[1], 'blood', ARGV[1]) redis.call('EXPIRE', KEYS[1], ARGV[2]) end return 1",
            [self::BOSS_INFO_KEY, $blood, self::TIME_RANGE],
            1
        );
    }

    private static function counter(string $key): int
    {
        $value = Redis::get($key);
        return $value === null || $value === false ? 0 : (int)$value;
    }

    /**
     * 原子累加并刷新 12 小时过期（旧版 GET 后 SETEX），返回累加后的值
     */
    private static function incr(string $key, int $num): int
    {
        $value = (int)Redis::incrBy($key, $num);
        Redis::expire($key, self::REDIS_EXPIRE);
        return $value;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function logRow(array $row): array
    {
        foreach ($row as $key => $value) {
            $row[$key] = $key === 'nickname' ? (string)$value : (int)$value;
        }
        return $row;
    }
}
