<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\CountryBossReward;
use Hanjiang\MasterData\WorldBoss as WorldBossTemplate;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 国家 BOSS（国家神兽蚩尤）状态（移植旧版 models/countryboss_model.php）
 *
 * 每个国家（玩家军团，member.country = zc_sanguo_country.id）一只 BOSS：控制器 set_phase(member.country)，
 * 键名 countryboss_{国家id}_*；countryboss_played_{uid}、countryboss_hit_lock_{uid} 不分国家（旧版 set_phase 不改）。
 * 过期时间同旧版：6 天（518400 秒），个人伤害 517400 秒（复活 CD 由它的剩余 TTL 推算）。
 * 击杀记录存 MySQL country_boss_log（旧版同名表），决定下次 BOSS 的血量 / 等级 / 奖励加成。
 *
 * 多进程安全的改动同 WorldBoss：BOSS 信息为 Hash，剩余血量 HINCRBY 原子扣减；个人伤害 INCRBY；
 * 伤害排行为有序集合 hit_rank（ZINCRBY，前 1000 名），代替旧版 KEYS 扫描 + 排行缓存（hit_rank / rank_uid / hit_top）。
 * BOSS 死后旧版把排行缓存 6 天不再变化；这里排行不因领奖改变，BOSS 死后也不会再有伤害写入，效果相同。
 * 旧版的全体伤害 all_hit（只写不读，写入的还是个人累计值）不移植。
 */
final class CountryBoss
{
    /** 活动周几开启（控制器 _start_day） */
    public const int START_DAY = 5;
    /** 活动开始时间（控制器 _start_time） */
    public const string START_TIME = '203000';
    /** 开启国家 BOSS 需要的国家等级 / 玩家等级 */
    public const int COUNTRY_LEVEL = 40;
    public const int MEMBER_LEVEL = 50;

    public const int REDIS_EXPIRE = 518400;
    public const int MY_HIT_EXPIRE = 517400;
    public const int TIME_RANGE = 900;
    /** 排行榜 TOP 人数 */
    public const int HIT_TOP_NUM = 50;
    /** 鼓舞上限（控制器 powerup：>= 200 返回 4） */
    public const int POWER_UP_MAX = 200;

    private const int HIT_RANK_NUM = 1000;
    private const int POWER_UP_STEP = 10;

    /** get_boss_info：world_boss 行 id 2（蚩尤）+ 写死的战斗数值；血量 / 等级来自击杀记录 */
    private const int BOSS_ID = 2;
    private const int BOSS_ATTACK = 100000000;
    /** get_killed_log：初始血量，每次击杀 × 1.03 */
    private const int BASE_BLOOD = 2000000000;

    private const string HIT_LOCK_KEY = 'countryboss_hit_lock_';
    private const string PLAYED_KEY = 'countryboss_played_';

    /** 已存在才扣血：KEYS[1] Hash，ARGV[1] 伤害，ARGV[2] 过期秒数；返回剩余血量（BOSS 信息不存在返回 nil） */
    private const string LUA_SUB_BLOOD = <<<'LUA'
        if redis.call('EXISTS', KEYS[1]) == 0 then return false end
        local blood = redis.call('HINCRBY', KEYS[1], 'blood', -tonumber(ARGV[1]))
        redis.call('EXPIRE', KEYS[1], ARGV[2])
        return blood
        LUA;

    /**
     * set_phase($this->member['country'])
     */
    private static function key(int $country, string $name): string
    {
        return 'countryboss_' . $country . '_' . $name;
    }

    /**
     * 控制器构造函数：周五才计算倒计时，其余日子 -1
     */
    public static function countdown(int $now): int
    {
        if ((int)date('w', $now) !== self::START_DAY) {
            return -1;
        }
        return WorldBoss::activityCountdown(self::START_TIME, $now);
    }

    /**
     * 取 BOSS 信息（get_boss_info）：没有缓存时按击杀记录定血量 / 等级建档，满血
     *
     * @return array<string, int|string>
     */
    public static function bossInfo(int $country, int $now): array
    {
        $row = WorldBossTemplate::ROWS[self::BOSS_ID] ?? null;
        if ($row === null) {
            return [];
        }
        $info = self::killedLog($country, $now);
        $fieldArr = [
            'blood' => $info['allblood'],
            'allblood' => $info['allblood'],
            'wuli' => 0,
            'zhili' => 0,
            'type' => 1,
            'attack' => self::BOSS_ATTACK,
            'pdefense' => 0,
            'mdefense' => 0,
            'talentid' => 1,
            'skillid' => 1,
            'level' => $info['level'],
        ];
        foreach ($fieldArr as $field => $value) {
            $row[$field] = $value;
        }
        // 已有缓存时原样返回（Lua 里判断，不覆盖当前血量）
        return WorldBoss::createHash(self::key($country, 'boss_info'), $row, self::REDIS_EXPIRE);
    }

    /**
     * 当前鼓舞加成（get_power_up），没鼓舞过为 100
     */
    public static function powerUp(int $country, int $uid): int
    {
        $powerup = Redis::get(self::key($country, 'power_up_') . $uid);
        return $powerup === null ? 100 : (int)$powerup;
    }

    /**
     * 鼓舞一次（power_up）：已到上限（并发）返回 null
     */
    public static function incrPowerUp(int $country, int $uid): ?int
    {
        return WorldBoss::luaPowerUp(self::key($country, 'power_up_') . $uid, self::POWER_UP_STEP, self::REDIS_EXPIRE, self::POWER_UP_MAX);
    }

    /**
     * 距上次攻击的秒数（check_delay）：没打过为 0
     */
    public static function checkDelay(int $country, int $uid): int
    {
        $ttl = (int)Redis::ttl(self::key($country, 'my_hit_') . $uid);
        if ($ttl < 0) {
            return 0;
        }
        return self::MY_HIT_EXPIRE - $ttl;
    }

    /**
     * 个人累计伤害（get_my_hit）
     */
    public static function myHit(int $country, int $uid): int
    {
        return (int)(Redis::get(self::key($country, 'my_hit_') . $uid) ?? 0);
    }

    /**
     * 记录伤害（update_my_hit）：最后攻击者、BOSS 剩余血量 -= 伤害、个人累计伤害
     */
    public static function updateMyHit(int $country, int $uid, int $hit): void
    {
        // 更新最后一击
        Redis::set(self::key($country, 'final_uid'), $uid, self::REDIS_EXPIRE);
        // 更新 boss 剩余血量（boss 的血等于剩余血量减去我攻击的血量）
        Redis::instance()->eval(self::LUA_SUB_BLOOD, [self::key($country, 'boss_info'), $hit, self::REDIS_EXPIRE], 1);
        // 个人伤害记录
        $key = self::key($country, 'my_hit_') . $uid;
        Redis::incrBy($key, $hit);
        Redis::expire($key, self::MY_HIT_EXPIRE);
        $rankKey = self::key($country, 'hit_rank');
        Redis::zIncrBy($rankKey, $hit, (string)$uid);
        Redis::expire($rankKey, self::REDIS_EXPIRE);
    }

    /**
     * 领奖后删除攻击记录（del_my_hit）：留一份 log_{mdHis}_{键名} 记录，记录已参加
     *
     * 并发领奖时只有真正删到记录的请求返回 true。
     */
    public static function delMyHit(int $country, int $uid, int $now): bool
    {
        $key = self::key($country, 'my_hit_') . $uid;
        $value = Redis::get($key);
        if (Redis::del($key) !== 1) {
            return false;
        }
        Redis::set('log_' . date('mdHis', $now) . '_' . $key, $value, self::REDIS_EXPIRE);
        // 已经参与过活动的记录
        Redis::set(self::PLAYED_KEY . $uid, 1, self::REDIS_EXPIRE);
        return true;
    }

    /**
     * 伤害排行（get_top_rank）：前 50 名
     *
     * @return list<array{uid: string, hit: string, rank: int}>
     */
    public static function topRank(int $country): array
    {
        return WorldBoss::zsetRank(self::key($country, 'hit_rank'), self::HIT_TOP_NUM);
    }

    /**
     * 我的排名（get_my_rank）：有伤害记录且在前 1000 名内，否则 0
     */
    public static function myRank(int $country, int $uid): int
    {
        if (self::myHit($country, $uid) === 0) {
            return 0;
        }
        return WorldBoss::zsetMyRank(self::key($country, 'hit_rank'), $uid, self::HIT_RANK_NUM);
    }

    /**
     * 奖励（get_reward）：按排名取胜利 / 失败奖励；每 10 次击杀宝石 × 1.1（逐次向上取整）；最后一击宝石 × 1.5
     *
     * @return array{status: int, itemid: int, itemnum: int, quality: int, itemname: string, jadetype: int, jadenum: int, lasthit: int}
     */
    public static function reward(int $country, int $uid, int $win, int $now): array
    {
        $return = [
            'status' => 0,
            'itemid' => 0,
            'itemnum' => 0,
            'quality' => 0,
            'itemname' => '',
            'jadetype' => 0,
            'jadenum' => 0,
            'lasthit' => 0,
        ];
        // 获取自己的排名
        $rank = self::myRank($country, $uid);
        // 获取 boss 击杀记录，判断奖励加成
        $info = self::killedLog($country, $now);
        if ($rank > 0) {
            $result = CountryBossReward::ROWS[$rank] ?? null;
            if ($result !== null) {
                // 胜利取胜利的奖励，失败取失败的奖励
                $key = $win === 1 ? 'win' : 'lose';
                $return['status'] = 1;
                $return['itemid'] = (int)$result[$key . '_item_id'];
                $return['itemnum'] = (int)$result[$key . '_item_num'];
                $return['jadetype'] = (int)$result[$key . '_jade_type'];
                $return['jadenum'] = (int)$result[$key . '_jade_num'];

                // 计算 boss 击杀增幅
                for ($i = 1; $i <= $info['add']; $i++) {
                    $return['jadenum'] = (int)ceil($return['jadenum'] * 1.1);
                }

                // 最后一击奖励多 50%
                if ($uid === (int)(Redis::get(self::key($country, 'final_uid')) ?? 0)) {
                    $return['lasthit'] = 1;
                    $return['jadenum'] = (int)ceil($return['jadenum'] * 1.5);
                }
            }
        }
        return $return;
    }

    /**
     * 最后一击公告（get_final_uid）：还没发过时取最后攻击者并标记已发；已发过返回 0
     */
    public static function finalUid(int $country): int
    {
        $uid = 0;
        if (!Redis::get(self::key($country, 'final_horn'))) {
            $uid = (int)(Redis::get(self::key($country, 'final_uid')) ?? 0);
            Redis::set(self::key($country, 'final_horn'), 1, self::REDIS_EXPIRE);
        }
        return $uid;
    }

    /**
     * 是否已发送最后一击公告（is_final_horn）
     */
    public static function isFinalHorn(int $country): bool
    {
        return Redis::exists(self::key($country, 'final_horn'));
    }

    /**
     * 战斗处理锁（battle_lock_check + battle_lock，1 秒）：拿到锁返回 true
     */
    public static function battleLock(int $uid): bool
    {
        return WorldBoss::lock(self::HIT_LOCK_KEY . $uid);
    }

    /**
     * 成功击杀，记录击杀 log（add_killed_log：同一国家同一天只记一条，旧版 REPLACE）
     */
    public static function addKilledLog(int $country, int $now): void
    {
        DB::execute(
            'INSERT INTO `zc_sanguo_country_boss_log` (`country`, `ymd`, `dateline`) VALUES (:country, :ymd, :dateline)
             ON DUPLICATE KEY UPDATE `dateline` = VALUES(`dateline`), `deleted_at` = NULL',
            ['country' => $country, 'ymd' => (int)date('Ymd', $now), 'dateline' => $now]
        );
    }

    /**
     * 本次 BOSS 的血量和奖励加成（get_killed_log）：只数 6 天前 20:00 以前的击杀
     *
     * @return array{allblood: int, add: int, level: int}
     */
    public static function killedLog(int $country, int $now): array
    {
        $return = [
            'allblood' => self::BASE_BLOOD,
            'add' => 0,
            'level' => 0,
        ];
        $dateline = ((int)strtotime(date('y-m-d', $now)) + (20 * 3600)) - 86400 * 6;
        $num = (int)DB::table('country_boss_log')
            ->where('country', $country)
            ->where('dateline', '<', $dateline)
            ->whereNull('deleted_at')
            ->count();
        if ($num > 0) {
            for ($i = 1; $i <= $num; $i++) {
                $return['allblood'] = (int)ceil($return['allblood'] * 1.03);
            }
            $return['add'] = intdiv($num, 10);
            $return['level'] = $num;
        }
        return $return;
    }
}
