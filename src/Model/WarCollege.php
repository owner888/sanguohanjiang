<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\Handler\Pvp\CountryHandler;
use Hanjiang\MasterData\WarCollegeSkill;
use OmniPHP\Cache\Redis;
use OmniPHP\Database\DB;

/**
 * 国家学院（移植旧版 models/warcollege_model.php + models/warcollege_use_model.php）
 *
 * 技能配置取 MasterData WarCollegeSkill（11 类技能 × 0 ~ 20 级）。各国的技能等级存 war_college_skill_info（每国每类一行，
 * 国家第一次读取时按各类第一行（0 级）建档）；国家税金存 war_college_gold_info（第一次读取时按国家累计经验 × 0.1 建档，
 * 之后国家每加经验 +经验 × 0.1，研究技能扣），变动记 war_college_gold_info_log；玩家的学院 buff 结束时间存 warcollege_use
 * （将军令 member_integral.general 换 24 小时）。
 *
 * 加成（get_warcollege_addition）：国家 30 级以上、加入国家满 100 秒（旧版注释写「三天」，代码是 100 秒）、buff 未结束时，
 * 按各类技能的 skill_value 加成；用于组队（TeamBuilder：三围、士气、攻防、命中 / 精准）、推演（BattleEngine：闪避率 / 识破率）
 * 与武将界面三围（_get_war_college_info）。
 *
 * 注意：MasterData WarCollegeSkillInfo / WarCollegeGoldInfo 是旧服各国的运行数据（不是配置），这里不读。
 *
 * 与旧版的差别：国家技能与税金建档用唯一键 + INSERT IGNORE（旧版先查再插，并发会插出重复行）；税金增减用一条 UPDATE 原子增减，
 * 扣到 0 为止（旧版读改写，并发会丢）；buff 记录每人一行（唯一键 uid，旧版取最新一行）；clear_buffcd 改为软删除。
 */
final class WarCollege
{
    /** 加成字段（_type） */
    public const array TYPE = [
        1 => 'attack',      // 物攻
        2 => 'defense',     // 物防
        3 => 'pattack',     // 策攻
        4 => 'pdefense',    // 策防
        5 => 'wuli',        // 武力
        6 => 'zhili',       // 智力
        7 => 'tili',        // 体力
        8 => 'dodge',       // 闪避率
        9 => 'penetrate',   // 识破率
        10 => 'jingzhun',   // 精准
        11 => 'mingzhong',  // 命中
        12 => 'power',      // 初始士气
    ];

    /** 国家学院开启的国家等级 */
    public const int OPEN_LEVEL = 30;

    private const array SKILL_FIELDS = ['id', 'country_id', 'img_id', 'skill_name', 'skill_type', 'skill_level', 'skill_value', 'dateline'];

    /**
     * 技能列表（get_skill_list：按技能类型分组，每类取第一行）
     *
     * @return list<array<string, mixed>>
     */
    public static function skillList(): array
    {
        $return = [];
        foreach (WarCollegeSkill::ROWS as $row) {
            $return[$row['skill_type']] ??= $row;
        }
        ksort($return);
        return array_values($return);
    }

    /**
     * 技能某一级的配置（get_skill_info_by_id：$level 为 0 时取该类第一行）；没有为空数组
     *
     * @return array<string, mixed>
     */
    public static function skillInfo(int $type, int $level = 0): array
    {
        foreach (WarCollegeSkill::ROWS as $row) {
            if (($type <= 0 || $row['skill_type'] === $type) && ($level <= 0 || $row['skill_level'] === $level)) {
                return $row;
            }
        }
        return [];
    }

    /**
     * 国家的技能信息（get_country_skill_info：没有记录时建档）
     *
     * @return array<string, mixed>|list<array<string, mixed>> $skillId > 0 时为该类一行（没有为空数组），否则为全部技能
     */
    public static function countrySkillInfo(int $countryId, int $skillId = 0): array
    {
        $rows = self::countrySkillRows($countryId);
        if ($rows === []) {
            $rows = self::initCountryInfo($countryId);
            if ($skillId > 0) {
                // 旧版按位置取 $result[$skill_id - 1]
                return $rows[$skillId - 1] ?? [];
            }
            return $rows;
        }
        if ($skillId > 0) {
            foreach ($rows as $row) {
                if ($row['skill_type'] === $skillId) {
                    return $row;
                }
            }
            return [];
        }
        return $rows;
    }

    /**
     * 提升技能等级（skill_level_add）
     */
    public static function skillLevelAdd(int $countryId, int $skillId, int $now): void
    {
        $info = self::countrySkillInfo($countryId, $skillId);
        if ($info === []) {
            return;
        }
        $nextLevel = (int)$info['skill_level'] + 1;
        $next = self::skillInfo((int)$info['skill_type'], $nextLevel);
        DB::table('war_college_skill_info')->where('country_id', $countryId)->where('skill_type', (int)$info['skill_type'])
            ->where('skill_level', (int)$info['skill_level'])->whereNull('deleted_at')
            ->update(['skill_level' => $nextLevel, 'skill_value' => (int)($next['skill_value'] ?? 0), 'dateline' => $now]);
    }

    /**
     * 国家税金（get_country_gold_info：没有记录时按国家累计经验 × 0.1 建档，记 type 1 日志）
     *
     * @param array<string, mixed> $country 国家行（id / level / exp）
     * @return array{country_id: int, country_gold: float, dateline: int}
     */
    public static function countryGoldInfo(array $country, ?int $now = null): array
    {
        $id = (int)$country['id'];
        $row = DB::table('war_college_gold_info')->select('country_id', 'country_gold', 'dateline')
            ->where('country_id', $id)->whereNull('deleted_at')->first();
        if ($row !== null) {
            return ['country_id' => (int)$row['country_id'], 'country_gold' => (float)$row['country_gold'], 'dateline' => (int)$row['dateline']];
        }
        $now ??= time();
        $exp = 0;
        for ($i = 1; $i < (int)$country['level']; $i++) {
            $exp += (int)(CountryHandler::levelupRule($i)['exp'] ?? 0);
        }
        $exp += (int)$country['exp'];
        $data = ['country_id' => $id, 'country_gold' => round($exp * 0.1, 1), 'dateline' => $now];
        $affected = DB::execute(
            'INSERT IGNORE INTO `zc_sanguo_war_college_gold_info` (`country_id`, `country_gold`, `dateline`) VALUES (:id, :gold, :now)',
            ['id' => $id, 'gold' => $data['country_gold'], 'now' => $now]
        );
        if ($affected === 0) {
            // 并发建档：取已建好的那一行
            return self::countryGoldInfo($country, $now);
        }
        self::addGoldLog($id, 1, $data['country_gold'], $now);
        return $data;
    }

    /**
     * 增加（type 2）或消耗（type 3）国家税金（update_country_gold_info；记日志时旧版不写 country_id）
     *
     * @param array<string, mixed> $country
     */
    public static function updateCountryGold(array $country, float $gold, int $type, ?int $now = null): void
    {
        $now ??= time();
        self::countryGoldInfo($country, $now);
        $sql = $type === 2
            ? 'UPDATE `zc_sanguo_war_college_gold_info` SET `country_gold` = `country_gold` + :gold, `dateline` = :now WHERE `country_id` = :id AND `deleted_at` IS NULL'
            : 'UPDATE `zc_sanguo_war_college_gold_info` SET `country_gold` = IF(`country_gold` > :gold, `country_gold` - :gold2, 0), `dateline` = :now WHERE `country_id` = :id AND `deleted_at` IS NULL';
        $params = ['gold' => round($gold, 1), 'now' => $now, 'id' => (int)$country['id']];
        if ($type !== 2) {
            $params['gold2'] = round($gold, 1);
        }
        DB::execute($sql, $params);
        self::addGoldLog(0, $type, round($gold, 1), $now);
    }

    /**
     * 国家学院技能升级日志（add_college_level_up_log）
     *
     * @param array{country_id: int, skill_type: int, skill_level: int, country_gold: int, gold: int, dateline: int} $data
     */
    public static function addLevelUpLog(array $data): void
    {
        DB::table('war_college_level_up_log')->insert($data);
    }

    /**
     * 研究技能加锁（get_lock + add_lock：30 秒；已有锁返回 false）
     */
    public static function lock(int $countryId): bool
    {
        return (bool)Redis::instance()->set('war_college_level_up_lock' . $countryId, '1', ['nx', 'ex' => 30]);
    }

    public static function unlock(int $countryId): void
    {
        Redis::del('war_college_level_up_lock' . $countryId);
    }

    /**
     * 国家学院加成（get_warcollege_addition）；不满足条件时为 null（旧版 return 空值）
     *
     * @return array<string, int>|null
     */
    public static function addition(int $uid, ?int $now = null): ?array
    {
        $now ??= time();
        $country = CountryHandler::memberCountry($uid);
        if ($country === null) {
            return null;
        }
        $countryInfo = CountryHandler::countryInfo((int)$country['country']);
        // 技能生效时间（旧版注释为加入国家三天，代码是 100 秒）
        if ($countryInfo === null || (int)$countryInfo['level'] < self::OPEN_LEVEL || $now - (int)$country['joindateline'] < 100) {
            return null;
        }
        // 获得玩家的 buffcd
        if (self::buffcd($uid, $now) === 0) {
            return null;
        }
        $return = array_fill_keys(array_values(self::TYPE), 0);
        foreach (self::countrySkillInfo((int)$countryInfo['id']) as $val) {
            $field = self::TYPE[(int)$val['skill_type']] ?? null;
            if ($field !== null) {
                $return[$field] = (int)$val['skill_value'];
            }
        }
        return $return;
    }

    /**
     * 武将界面的国家学院三围（_get_war_college_info：国家 30 级以上、有加成时 wuliup / zhiliup / tiliup 加上学院武力 / 智力 / 体力）
     *
     * @param array<string, mixed> $general
     */
    public static function applyInfo(int $uid, array &$general, ?int $now = null): void
    {
        $member = DB::table('member')->select('country')->where('uid', $uid)->whereNull('deleted_at')->first();
        $country = CountryHandler::countryInfo((int)($member['country'] ?? 0));
        if ($country === null || (int)$country['level'] < self::OPEN_LEVEL) {
            return;
        }
        $addition = self::addition($uid, $now);
        if ($addition !== null) {
            foreach (['wuli' => 'wuliup', 'zhili' => 'zhiliup', 'tili' => 'tiliup'] as $key => $value) {
                $general[$value] = (int)$general[$value] + $addition[$key];
            }
        }
    }

    /**
     * 玩家的 buff 剩余秒数（warcollege_use_model->getbuffcd；没有记录时建档，结束时间为当前时间）
     */
    public static function buffcd(int $uid, ?int $now = null): int
    {
        $now ??= time();
        $end = self::buffEnd($uid, $now);
        return $end > $now ? $end - $now : 0;
    }

    /**
     * 使用 buff（use_buff：未结束时在结束时间上顺延，否则从现在起算）
     *
     * @return array{status: int, buffcd: int}
     */
    public static function useBuff(int $uid, int $bufftime, ?int $now = null): array
    {
        $now ??= time();
        self::buffEnd($uid, $now);
        DB::execute(
            'UPDATE `zc_sanguo_warcollege_use` SET `buffendtime` = IF(`buffendtime` > :now, `buffendtime`, :now2) + :add
             WHERE `uid` = :uid AND `deleted_at` IS NULL',
            ['now' => $now, 'now2' => $now, 'add' => $bufftime, 'uid' => $uid]
        );
        $end = self::buffEnd($uid, $now);
        return ['status' => 1, 'buffcd' => $end > $now ? $end - $now : 0];
    }

    /**
     * 清理 buffcd（clear_buffcd，离开国家时）
     */
    public static function clearBuffcd(int $uid): void
    {
        DB::table('warcollege_use')->where('uid', $uid)->whereNull('deleted_at')->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * buff 结束时间（warcollege_use_model->get：没有记录或已清理时建档为当前时间）
     */
    private static function buffEnd(int $uid, int $now): int
    {
        $end = DB::table('warcollege_use')->where('uid', $uid)->whereNull('deleted_at')->value('buffendtime');
        if ($end !== null) {
            return (int)$end;
        }
        DB::execute(
            'INSERT INTO `zc_sanguo_warcollege_use` (`uid`, `buffendtime`) VALUES (:uid, :now)
             ON DUPLICATE KEY UPDATE `buffendtime` = IF(`deleted_at` IS NULL, `buffendtime`, VALUES(`buffendtime`)), `deleted_at` = NULL',
            ['uid' => $uid, 'now' => $now]
        );
        return (int)DB::table('warcollege_use')->where('uid', $uid)->whereNull('deleted_at')->value('buffendtime');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function countrySkillRows(int $countryId): array
    {
        $rows = DB::table('war_college_skill_info')->select(...self::SKILL_FIELDS)
            ->where('country_id', $countryId)->whereNull('deleted_at')->orderBy('id')->get();
        return array_map(static function (array $row): array {
            foreach ($row as $key => $value) {
                $row[$key] = $key === 'skill_name' ? (string)$value : (int)$value;
            }
            return $row;
        }, $rows);
    }

    /**
     * 初始化国家技能（init_country_info：各类技能按第一行建档）
     *
     * @return list<array<string, mixed>>
     */
    private static function initCountryInfo(int $countryId): array
    {
        $now = time();
        foreach (self::skillList() as $val) {
            DB::execute(
                'INSERT IGNORE INTO `zc_sanguo_war_college_skill_info`
                    (`country_id`, `img_id`, `skill_name`, `skill_type`, `skill_level`, `skill_value`, `dateline`)
                 VALUES (:country_id, :img_id, :skill_name, :skill_type, :skill_level, :skill_value, :dateline)',
                [
                    'country_id' => $countryId,
                    'img_id' => $val['img_id'],
                    'skill_name' => $val['skill_name'],
                    'skill_type' => $val['skill_type'],
                    'skill_level' => $val['skill_level'],
                    'skill_value' => $val['skill_value'],
                    'dateline' => $now,
                ]
            );
        }
        return self::countrySkillRows($countryId);
    }

    private static function addGoldLog(int $countryId, int $type, float $gold, int $now): void
    {
        DB::table('war_college_gold_info_log')->insert([
            'country_id' => $countryId,
            'type' => $type,
            'country_gold' => $gold,
            'dateline' => $now,
        ]);
    }
}
