<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\MasterData\WorldBoss as WorldBossTemplate;
use Hanjiang\MasterData\WorldBossIntreward;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 世界 BOSS 状态（移植旧版 models/worldboss_model.php；开放时间取 activity_model->_boss_time）
 *
 * 状态同旧版全部放 Redis，键名、过期时间同旧版（OmniPHP Redis 自动加 hj: 前缀）：
 * - 12:00 场（phase 0）键名 worldboss_*，20:00 场（phase 1，set_phase(1)）键名 worldboss_1_*；
 *   worldboss_boss_info、worldboss_played_{uid}、worldboss_hit_lock_{uid} 两场共用（旧版 set_phase 不改这三个）。
 * - 个人累计伤害 my_hit_{uid}：每次攻击刷新为 12 小时过期，复活 CD 由它的剩余 TTL 推算（check_delay）。
 *
 * 为多进程安全，旧版「GET 后 SETEX」的写法改为原子操作：
 * - 伤害累计用 INCRBY；伤害排行由 KEYS worldboss_my_hit_* 全量扫描 + 5 秒 / 12 小时缓存（hit_rank / rank_uid / hit_top / my_rank）
 *   改为同名有序集合 hit_rank（ZINCRBY，实时排序，只取前 1000 名同旧版 _hit_rank_num）。领奖只删 my_hit，不动排行，
 *   与旧版活动结束后排行缓存 12 小时不变的效果相同。
 * - BOSS 信息由 JSON 字符串改为同名 Hash（缺失时用 Lua 原子建档；血量单独 HSET），鼓舞用 Lua 原子「未满上限才 +10」。
 * 旧版 get_boss_info 的建档锁 worldboss_lock + sleep 等待、排行快照前的 sleep(1) 不移植（Workerman 进程内不能 sleep，原子建档后也不需要）。
 */
final class WorldBoss
{
    /** 活动时间（activity_model->_boss_time） */
    public const string START_TIME = '120000';
    public const string END_TIME = '121500';
    public const string START_TIME1 = '200000';
    public const string END_TIME1 = '201500';

    /** 开放等级（控制器 index、activity / member 的 worldboss_init） */
    public const int OPEN_LEVEL = 46;

    /** 排行 / 个人数据 redis 过期时间 */
    public const int REDIS_EXPIRE = 43200;
    /** 活动时长 */
    public const int TIME_RANGE = 900;
    /** 排行榜 TOP 人数 */
    public const int HIT_TOP_NUM = 5;
    /** 鼓舞上限（控制器 powerup：>= 200 返回 4） */
    public const int POWER_UP_MAX = 200;

    /** 排行榜长度 */
    private const int HIT_RANK_NUM = 1000;
    /** 一次鼓舞增幅 */
    private const int POWER_UP_STEP = 10;

    /** get_boss_info：world_boss 行 id 1（刑天）+ 写死的战斗数值 */
    private const int BOSS_ID = 1;
    private const int BOSS_BLOOD = 9000000000000;
    private const int BOSS_ATTACK = 1000000000;

    private const string BOSS_INFO_KEY = 'worldboss_boss_info';
    private const string HIT_LOCK_KEY = 'worldboss_hit_lock_';
    private const string PLAYED_KEY = 'worldboss_played_';

    /** Hash 里按整数取回的字段 */
    private const array INT_FIELDS = ['id', 'level', 'blood', 'allblood', 'wuli', 'zhili', 'type', 'attack', 'pdefense', 'mdefense', 'talentid', 'skillid'];

    /** 缺失才建档：KEYS[1] Hash，ARGV[1] 过期秒数，其余为字段 / 值 */
    private const string LUA_CREATE = <<<'LUA'
        if redis.call('EXISTS', KEYS[1]) == 0 then
            redis.call('HSET', KEYS[1], unpack(ARGV, 2))
            redis.call('EXPIRE', KEYS[1], ARGV[1])
        end
        return redis.call('HGETALL', KEYS[1])
        LUA;

    /** 已存在才改血量：KEYS[1] Hash，ARGV[1] 血量，ARGV[2] 过期秒数 */
    private const string LUA_SET_BLOOD = <<<'LUA'
        if redis.call('EXISTS', KEYS[1]) == 1 then
            redis.call('HSET', KEYS[1], 'blood', ARGV[1])
            redis.call('EXPIRE', KEYS[1], ARGV[2])
        end
        return 1
        LUA;

    /** 鼓舞：KEYS[1]，ARGV[1] 增幅，ARGV[2] 过期秒数，ARGV[3] 上限；已到上限返回 -当前值 */
    public const string LUA_POWER_UP = <<<'LUA'
        local v = tonumber(redis.call('GET', KEYS[1]) or '0') or 0
        if v == 0 then v = 100 end
        if v >= tonumber(ARGV[3]) then return -v end
        v = v + tonumber(ARGV[1])
        redis.call('SETEX', KEYS[1], ARGV[2], v)
        return v
        LUA;

    /**
     * set_phase：phase 1 为 20:00 场
     */
    private static function key(int $phase, string $name): string
    {
        return ($phase === 1 ? 'worldboss_1_' : 'worldboss_') . $name;
    }

    /**
     * 控制器构造函数：今日未参加过（12:00 场无伤害记录、未领过奖）且已到 20:00 时切到 20:00 场
     *
     * @return array{phase: int, start: string, played: int, countdown: int}
     */
    public static function context(int $uid, int $now): array
    {
        $start = self::START_TIME;
        $phase = 0;
        $played = self::checkPlayed($uid);
        if ($played === 0 && date('His', $now) >= self::START_TIME1) {
            $start = self::START_TIME1;
            $phase = 1;
        }
        return [
            'phase' => $phase,
            'start' => $start,
            'played' => $played,
            'countdown' => self::activityCountdown($start, $now),
        ];
    }

    /**
     * 活动倒计时（activity_countdown）：-1 未开启；0 已结束；> 0 进行中的剩余秒数
     */
    public static function activityCountdown(string $startTime, int $now): int
    {
        // 旧版 mktime(时, 分, 0)：当天的开始时间
        $starttime = mktime((int)substr($startTime, 0, 2), (int)substr($startTime, 2, 2), 0, (int)date('n', $now), (int)date('j', $now), (int)date('Y', $now));
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
     * 取 BOSS 信息（get_boss_info）：没有缓存时按 world_boss 行建档，满血
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
        $fieldArr = [
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
        ];
        foreach ($fieldArr as $key => $value) {
            $info[$key] = $value;
        }
        return self::createHash(self::BOSS_INFO_KEY, $info, self::REDIS_EXPIRE);
    }

    /**
     * 原子建档并取回 Hash（两类 BOSS 共用）
     *
     * @param array<string, int|string> $info
     * @return array<string, int|string>
     */
    public static function createHash(string $key, array $info, int $ttl): array
    {
        $args = [$key, $ttl];
        foreach ($info as $field => $value) {
            $args[] = $field;
            $args[] = (string)$value;
        }
        $flat = Redis::instance()->eval(self::LUA_CREATE, $args, 1);
        $return = [];
        for ($i = 0, $n = is_array($flat) ? count($flat) : 0; $i + 1 < $n; $i += 2) {
            $field = (string)$flat[$i];
            $return[$field] = in_array($field, self::INT_FIELDS, true) ? (int)$flat[$i + 1] : (string)$flat[$i + 1];
        }
        return $return;
    }

    /**
     * 当前鼓舞加成（get_power_up），没鼓舞过为 100
     */
    public static function powerUp(int $phase, int $uid): int
    {
        $powerup = Redis::get(self::key($phase, 'power_up_') . $uid);
        return $powerup === null ? 100 : (int)$powerup;
    }

    /**
     * 鼓舞一次（power_up）：+10（没鼓舞过从 100 起），12 小时过期
     *
     * 控制器先判断 >= 200 再扣费、再鼓舞；并发请求扣费后发现已到上限时返回 null（由调用方退款并按 4 返回）。
     */
    public static function incrPowerUp(int $phase, int $uid): ?int
    {
        return self::luaPowerUp(self::key($phase, 'power_up_') . $uid, self::POWER_UP_STEP, self::REDIS_EXPIRE, self::POWER_UP_MAX);
    }

    public static function luaPowerUp(string $key, int $step, int $ttl, int $max): ?int
    {
        $value = (int)Redis::instance()->eval(self::LUA_POWER_UP, [$key, $step, $ttl, $max], 1);
        return $value < 0 ? null : $value;
    }

    /**
     * 距上次攻击的秒数（check_delay）：没打过为 0
     */
    public static function checkDelay(int $phase, int $uid): int
    {
        $ttl = (int)Redis::ttl(self::key($phase, 'my_hit_') . $uid);
        if ($ttl < 0) {
            // -2 没有记录（没有打过 boss）
            return 0;
        }
        return self::REDIS_EXPIRE - $ttl;
    }

    /**
     * 个人累计伤害（get_my_hit）
     */
    public static function myHit(int $phase, int $uid): int
    {
        return (int)(Redis::get(self::key($phase, 'my_hit_') . $uid) ?? 0);
    }

    /**
     * 记录伤害（update_my_hit）：已发过最后一击公告则不记；记录最后攻击者、累计伤害、刷新 BOSS 血量
     */
    public static function updateMyHit(int $phase, int $uid, int $hit, int $countdown): void
    {
        if ((int)(Redis::get(self::key($phase, 'final_horn')) ?? 0) > 0) {
            return;
        }
        // 更新最后一击
        Redis::set(self::key($phase, 'final_uid'), $uid, self::REDIS_EXPIRE);
        // 个人伤害记录
        $key = self::key($phase, 'my_hit_') . $uid;
        Redis::incrBy($key, $hit);
        Redis::expire($key, self::REDIS_EXPIRE);
        $rankKey = self::key($phase, 'hit_rank');
        Redis::zIncrBy($rankKey, $hit, (string)$uid);
        Redis::expire($rankKey, self::REDIS_EXPIRE);
        // 更新 boss 剩余血量
        self::updateBossBlood($countdown);
    }

    /**
     * 更新 BOSS 剩余血量（_update_boss_blood）：按剩余时间自然减少，与伤害无关；BOSS 信息改为 15 分钟过期
     *
     * 旧版对已过期的 BOSS 信息也会写回只有 blood 的残缺缓存，这里只改已存在的 BOSS 信息。
     */
    private static function updateBossBlood(int $countdown): void
    {
        $boss = Redis::hGet(self::BOSS_INFO_KEY, 'allblood');
        $allblood = $boss === false ? 0 : (int)$boss;
        $blood = (int)($countdown / self::TIME_RANGE * $allblood) - mt_rand(0, 1000);
        Redis::instance()->eval(self::LUA_SET_BLOOD, [self::BOSS_INFO_KEY, $blood, self::TIME_RANGE], 1);
    }

    /**
     * 伤害排行（_get_hit_rank / get_top_rank）：前 $limit 名，[{uid, hit, rank}]
     *
     * @return list<array{uid: string, hit: string, rank: int}>
     */
    public static function rankList(int $phase, int $limit = self::HIT_RANK_NUM): array
    {
        return self::zsetRank(self::key($phase, 'hit_rank'), min($limit, self::HIT_RANK_NUM));
    }

    /**
     * @return list<array{uid: string, hit: string, rank: int}>
     */
    public static function zsetRank(string $key, int $limit): array
    {
        $rows = Redis::zRevRange($key, 0, $limit - 1, true);
        $return = [];
        $i = 1;
        foreach (is_array($rows) ? $rows : [] as $uid => $hit) {
            $return[] = ['uid' => (string)$uid, 'hit' => (string)(int)$hit, 'rank' => $i++];
        }
        return $return;
    }

    /**
     * 排行前 $num 名补玩家信息 uid / level / gender / nickname（控制器 get_top_rank → member_model->get_base_info）
     *
     * @param list<array{uid: string, hit: string, rank: int}> $topArr
     * @return list<array<string, mixed>>
     */
    public static function withMemberInfo(array $topArr, int $num): array
    {
        $uidArr = array_map(static fn (array $v): int => (int)$v['uid'], $topArr);
        if ($uidArr === []) {
            return $topArr;
        }
        $result = [];
        foreach (DB::table('member')->select('uid', 'level', 'gender', 'nickname')->whereIn('uid', $uidArr)->whereNull('deleted_at')->get() as $row) {
            $result[(int)$row['uid']] = $row;
        }
        foreach ($topArr as $key => $value) {
            if ($value['rank'] > $num) {
                break;
            }
            foreach (['uid', 'level', 'gender', 'nickname'] as $v) {
                $field = $result[(int)$value['uid']][$v] ?? null;
                $topArr[$key][$v] = $field === null ? null : (string)$field;
            }
        }
        return $topArr;
    }

    /**
     * BOSS 战（battle_model->battle_worldboss）：玩家队伍（init_member_team_info，_pve = 1）打 5 号位的 BOSS
     *
     * $bossBattle 即旧版 _boss_battle：1 世界 BOSS、2 国家 BOSS；鼓舞系数 _attack_coe 由 BattleEngine 乘到双方伤害上。
     * init_member_team_info 返回 -1（没有阵法）/ -3（武将信息错误）时旧版不战斗、没有战斗结果（返回 null）；
     * -2（没有上阵武将）按空队伍战斗（直接判负）。
     *
     * @param array<string, mixed> $bossInfo
     * @return array<string, mixed>|null
     */
    public static function battle(int $uid, array $bossInfo, int|float $attackCoe, int $bossBattle): ?array
    {
        $left = TeamBuilder::forMember($uid, pve: true);
        if ($left['status'] === -1 || $left['status'] === -3) {
            return null;
        }
        $engine = new BattleEngine($left['matrix_type'], $left['matrix_coe'], attackCoe: $attackCoe, bossBattle: $bossBattle);
        return $engine->battle($left['team'], TeamBuilder::forBoss($bossInfo));
    }

    /**
     * 我的排名（get_my_rank）：有伤害记录且在前 1000 名内，否则 0
     */
    public static function myRank(int $phase, int $uid): int
    {
        if (self::myHit($phase, $uid) === 0) {
            return 0;
        }
        return self::zsetMyRank(self::key($phase, 'hit_rank'), $uid, self::HIT_RANK_NUM);
    }

    public static function zsetMyRank(string $key, int $uid, int $max): int
    {
        $rank = Redis::zRevRank($key, (string)$uid);
        if (!is_int($rank) || $rank + 1 > $max) {
            return 0;
        }
        return $rank + 1;
    }

    /**
     * 奖励（get_reward）：按排名区间取演武榜积分，最后一击 × 1.5，向上取整
     *
     * @return array{intnum: int, lasthit?: int}
     */
    public static function reward(int $phase, int $uid, int $rank = 0): array
    {
        $return = ['intnum' => 0];
        if ($rank === 0) {
            $rank = self::myRank($phase, $uid);
        }
        if ($rank > 0) {
            $reward = null;
            foreach (WorldBossIntreward::ROWS as $val) {
                if ($rank >= $val['rank']) {
                    $reward = $val;
                }
            }
            // 演武榜积分
            $intnum = (int)($reward['getvalue'] ?? 0);
            $lasthit = 0;
            if ($uid === self::finalUidValue($phase)) {
                // 最后一击玩家
                $intnum = $intnum * 1.5;
                $lasthit = 1;
            }
            $return['intnum'] = (int)ceil($intnum);
            $return['lasthit'] = $lasthit;
        }
        return $return;
    }

    /**
     * 最后攻击玩家 uid（final_uid 键）
     */
    public static function finalUidValue(int $phase): int
    {
        return (int)(Redis::get(self::key($phase, 'final_uid')) ?? 0);
    }

    /**
     * 领奖后删除攻击记录（del_my_hit），并记录今日已参加
     *
     * 并发领奖时只有真正删到记录的请求返回 true（旧版先读后删，两次请求可能都发奖）。
     * 已参加记录：玩家领奖 12 小时；计划任务代领 100 秒（随后清空全部键）。
     */
    public static function delMyHit(int $phase, int $uid, int $playedTtl = self::REDIS_EXPIRE): bool
    {
        if (Redis::del(self::key($phase, 'my_hit_') . $uid) !== 1) {
            return false;
        }
        Redis::set(self::PLAYED_KEY . $uid, 1, $playedTtl);
        return true;
    }

    /**
     * 今日是否参加过（check_played）：领过奖，或 12:00 场还有伤害记录
     */
    public static function checkPlayed(int $uid): int
    {
        if (Redis::get(self::PLAYED_KEY . $uid) || self::myHit(0, $uid) > 0) {
            return 1;
        }
        return 0;
    }

    /**
     * 最后一击公告（get_final_uid）：还没发过时取最后攻击者并标记已发；已发过返回 0
     */
    public static function finalUid(int $phase): int
    {
        $uid = 0;
        if (!Redis::get(self::key($phase, 'final_horn'))) {
            $uid = self::finalUidValue($phase);
            Redis::set(self::key($phase, 'final_horn'), $uid, self::REDIS_EXPIRE);
        }
        return $uid;
    }

    /**
     * 是否已发送最后一击公告（is_final_horn）
     */
    public static function isFinalHorn(int $phase): bool
    {
        return Redis::exists(self::key($phase, 'final_horn'));
    }

    /**
     * 战斗处理锁（battle_lock_check + battle_lock，1 秒）：拿到锁返回 true
     */
    public static function battleLock(int $uid): bool
    {
        return self::lock(self::HIT_LOCK_KEY . $uid);
    }

    public static function lock(string $key): bool
    {
        return (bool)Redis::instance()->set($key, 1, ['nx', 'ex' => 1]);
    }

    /**
     * 清除所有世界 BOSS 键（clern_worldboss_redis）
     */
    public static function clear(): int
    {
        return self::deleteByPattern('worldboss_*');
    }

    public static function deleteByPattern(string $pattern): int
    {
        $prefix = (string)Redis::instance()->getOption(\Redis::OPT_PREFIX);
        $keys = [];
        foreach (Redis::keys($pattern) as $key) {
            $keys[] = substr((string)$key, strlen($prefix));
        }
        return $keys === [] ? 0 : Redis::del(...$keys);
    }

    /**
     * 活动列表的世界 BOSS 项（controllers/activity.php:worldboss_init）
     *
     * @return array{status: int, starttime: string, endtime: string, openlevel: int, starttime2: string, endtime2: string, popo: int}
     */
    public static function activityInit(int $uid, int $now): array
    {
        $return = [
            'status' => 1,
            'starttime' => self::getTime(self::START_TIME),
            'endtime' => self::getTime(self::END_TIME),
            'openlevel' => self::OPEN_LEVEL,
            'starttime2' => self::getTime(self::START_TIME1),
            'endtime2' => self::getTime(self::END_TIME1),
            'popo' => 0,
        ];
        // 活动开始时间处理
        $strartTime = date('His', $now) >= self::START_TIME ? self::START_TIME1 : self::START_TIME;
        // 验证活动是否已经结束
        $countdown = self::activityCountdown($strartTime, $now);
        // 重置 hit_key（get_hit_key：已过本次开始时间用 20:00 场的键）
        $phase = date('His', $now) >= $strartTime ? 1 : 0;
        // 标识世界 BOSS 领奖状态
        if ($countdown <= 0 && self::myHit($phase, $uid) > 0) {
            $return['popo'] = 1;
        }
        return $return;
    }

    /**
     * 主城信息的世界 BOSS 开启 / 领奖标识（controllers/member.php:worldboss_init）
     *
     * @return array{openboss: int, openbossreward: int}
     */
    public static function memberInit(int $uid, int $level, int $now): array
    {
        $return = ['openboss' => 0, 'openbossreward' => 0];
        $nowTime = date('His', $now);
        if ($level >= self::OPEN_LEVEL
            && (($nowTime >= self::START_TIME && $nowTime <= self::END_TIME) || ($nowTime >= self::START_TIME1 && $nowTime <= self::END_TIME1))) {
            $return['openboss'] = self::checkPlayed($uid) === 1 ? 0 : 1;
        }
        // 活动开始时间处理
        $strartTime = $nowTime >= self::START_TIME ? self::START_TIME1 : self::START_TIME;
        $countdown = self::activityCountdown($strartTime, $now);
        // 旧版此处 get_hit_key 已注释：伤害记录固定取 12:00 场的键
        if ($countdown <= 0 && self::myHit(0, $uid) > 0) {
            $return['openbossreward'] = 1;
        }
        return $return;
    }

    /**
     * 时间处理（activity.php:get_time）：'120000' → '12:00'
     */
    private static function getTime(string $tstr): string
    {
        return substr_replace(substr($tstr, 0, -2), ':', 2, 0);
    }

    /**
     * 演武榜积分增加（worldboss::reward / cron_acttask::worldboss：get_memint_info + update_memint + add_memint_log）
     *
     * 旧版先读后写绝对值；这里在事务里原子累加，再取变化后的余额写日志。
     *
     * @return int 变化后的演武榜积分
     */
    public static function addArenaInt(int $uid, int $intnum, string $class, string $method, int $now): int
    {
        return (int)DB::transaction(static function () use ($uid, $intnum, $class, $method, $now): int {
            // get_memint_info：没有时插入默认行（各积分字段默认 0）
            DB::execute('INSERT IGNORE INTO `zc_sanguo_member_integral` (`uid`) VALUES (:uid)', ['uid' => $uid]);
            DB::execute(
                'UPDATE `zc_sanguo_member_integral` SET `act_arena_int` = `act_arena_int` + :intnum WHERE `uid` = :uid AND `deleted_at` IS NULL',
                ['intnum' => $intnum, 'uid' => $uid]
            );
            $balance = (int)DB::table('member_integral')->where('uid', $uid)->whereNull('deleted_at')->value('act_arena_int');
            // 添加积分变化日志
            DB::table('integral_log')->insert([
                'uid' => $uid,
                'type' => 1,
                'intnum' => $intnum,
                'balance' => $balance,
                'class' => $class,
                'method' => $method,
                'argument' => 'act_arena_int',
                'dateline' => $now,
            ]);
            return $balance;
        });
    }
}
