<?php

declare(strict_types=1);

namespace Hanjiang\Battle;

use Hanjiang\MasterData\CopiesInfo;
use Hanjiang\MasterData\CountryMonster;
use Hanjiang\MasterData\EliteMonster;
use Hanjiang\MasterData\GeneraltaskMonster;
use Hanjiang\MasterData\HeavenMonster;
use Hanjiang\MasterData\HerothroneMonster;
use Hanjiang\MasterData\IslandMonster;
use Hanjiang\MasterData\Matrix;
use Hanjiang\MasterData\Monster;
use Hanjiang\MasterData\RefineAchieve;
use Hanjiang\MasterData\SanctumMonster;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\SoulMonster;
use Hanjiang\MasterData\Talent;
use OmniPHP\Database\DB;
use RuntimeException;

/**
 * 战斗队伍组装（逐段移植旧版 models/battle_model.php，路径相对旧版 s1/application/）
 *
 * - forMember     ← init_member_team_info（本体 + 装备 + 阵法 + 模板天赋/技能部分）、_addition_refine，
 *                   以及 battle() 开头的 _get_general_all_blood
 * - forMonsters   ← init_monster_team_info，以及 battle_mission 里按天赋血量系数放大怪物血量
 * - teamReturnArr ← team_return_arr
 * - heroes        ← 把旧版队伍交给当前 BattleEngine（经 Hero::fromUnit 的临时适配层）
 *
 * 队伍结构同旧版：[站位 1~9 => 单位数组]。
 */
final class TeamBuilder
{
    /**
     * init_monster_team_info 的 $type → 旧版怪物表（battle_model 的 table_1 ~ table_10）
     */
    public const int MONSTER_MISSION = 0;      // monster（主线关卡）
    public const int MONSTER_ISLAND = 1;       // island_monster（金银洞）
    public const int MONSTER_SOUL = 2;         // soul_monster（将魂星路）
    public const int MONSTER_COUNTRY = 3;      // country_monster（国战 PVE）
    public const int MONSTER_ELITE = 4;        // elite_monster（多人副本 PVE）
    public const int MONSTER_GENERALTASK = 5;  // generaltask_monster（神将之门）
    public const int MONSTER_HEROTHRONE = 6;   // herothrone_monster（英雄王座）
    public const int MONSTER_SANCTUM = 7;      // sanctum_monster（四象圣域）
    public const int MONSTER_HEAVEN = 8;       // heaven_monster（通天塔）
    public const int MONSTER_COPIES = 9;       // copies_info（新副本）

    /**
     * 旧版 init_member_team_info 写入每个将领的字段（general_attr）
     */
    private const array GENERAL_ATTR = ['id', 'name', 'quality', 'level', 'image', 'type', 'wuli', 'zhili', 'tili', 'talentid', 'skillid'];

    /**
     * 玩家队伍（移植 battle_model.php:init_member_team_info）
     *
     * 返回 status 同旧版：1 成功；-1 没有阵法信息；-2 阵法里没有上阵武将；-3 上阵武将一个都查不到。
     * 失败时 team 为空数组（旧版同）。旧版 battle_mission / battle_pk 等对 -2 按空队伍继续战斗（空队伍直接判 -3 大败），
     * 对 -1 / -3 返回错误，由调用方按各自旧版控制器处理。
     *
     * 阵法读新版 matrix 表（新版每个玩家只有一行，mid 为当前阵法、level 为该行等级）；
     * 旧版是 own_matrix 每个阵法一行、member_field.matrixid 指当前阵法、各阵法各自有等级。
     * 依赖未移植：多阵法存储（阵法真相源后续阶段迁移）。
     *
     * @param bool $defense 防守方（旧版 $defense，只决定阵法记录到左 / 右侧；这里两侧同样返回 matrix_type / matrix_coe）
     * @param bool $pve     旧版 $this->_pve：PVE 战斗阵法加成系数上限 0.5
     * @param bool $self    是否为当前请求的玩家本人（旧版 $uid == $this->member['uid']），只影响 6 星战斗特效 star
     * @return array{status: int, team: array<int, array<string, mixed>>, matrix_type: int, matrix_coe: float}
     */
    public static function forMember(int $uid, bool $defense = false, bool $pve = false, bool $self = true): array
    {
        $return = ['status' => 0, 'team' => [], 'matrix_type' => 0, 'matrix_coe' => 0.0];

        // 依赖未移植（以下加成在旧版此处预取，均未移植，按 0 计入下方各步）：
        // 名将成就 general_model->get_own_addition（主公 81 级开启）、美人 beauty_model->get_beauty_addition（60 级）、
        // 星魄 star_model->get_star_addition（80 级）、国家学院 warcollege_model->get_warcollege_addition（国家 30 级）、
        // 神树 sacredtree_model->get_tree_by_uid（活动开启时）

        // 阵法：旧版 member_field.matrixid 为 0 或 own_matrix 查不到都返回 -1
        $matrixRow = DB::table('matrix')->where('uid', $uid)->whereNull('deleted_at')->first();
        if ($matrixRow === null) {
            $return['status'] = -1;
            return $return;
        }
        $matrix = [];
        for ($i = 1; $i <= 9; $i++) {
            $gid = (int)$matrixRow['position' . $i];
            if ($gid > 0) {
                $matrix[$i] = $gid;
            }
        }

        // 阵法类型与加成（matrix_model->get_rule_by_id：percent = 阵法等级 × intval(Matrix.addition)，coe = percent × 0.01）
        // 旧版阵法模板不存在时 get_rule_by_id 不返回 addition，type 取到 null、coe 为 0；这里同样按 0
        $matrixTpl = Matrix::ROWS[(int)$matrixRow['mid']] ?? null;
        $matrixType = $matrixTpl !== null ? (int)$matrixTpl['addtype'] : 0;
        $matrixCoe = $matrixTpl !== null ? ((int)$matrixRow['level'] * (int)$matrixTpl['addition']) * 0.01 : 0.0;
        // PVE 阵法加成上限控制（攻守两侧同）
        if ($pve && $matrixCoe > 0.5) {
            $matrixCoe = 0.5;
        }
        $return['matrix_type'] = $matrixType;
        $return['matrix_coe'] = $matrixCoe;

        if ($matrix === []) {
            // 阵法里没有武将
            $return['status'] = -2;
            return $return;
        }

        // 上阵武将（旧版 general_model->get_own_by_id：一个都查不到返回 -3）
        $rows = DB::table('general')
            ->where('uid', $uid)
            ->whereIn('id', array_values($matrix))
            ->whereNull('deleted_at')
            ->get();
        if ($rows === []) {
            $return['status'] = -3;
            return $return;
        }
        $generalArr = [];
        foreach ($rows as $row) {
            $generalArr[(int)$row['id']] = $row;
        }

        // 装备：旧版 own_general.eid1~eid4，新版由 equipment.generalid + 装配位置派生
        $equipBySite = self::equipmentBySite($uid, array_keys($generalArr));

        // 依赖未移植：坐骑 mount_model->get_info_by_id（own_general.mid0~mid4）
        // 依赖未移植：锻造套装 equip_forge_model->get_suit_attr（newquality > 0 的装备）
        // 依赖未移植：宝石 gem_model->get_gem_by_eid，各装备 gem_power 按 0
        // 依赖未移植：将魂 soul_model->get_general_soul / get_soul_by_id（转生 > 0 的将领）
        // 依赖未移植：主公天赋 talent_model->get_talent_list（主公 91 级开启）
        // 依赖未移植：名将进阶 advanced_model->get_advanced_by_gid（主公 100 级开启）

        $team = [];
        foreach ($matrix as $pos => $gid) {
            $generalInfo = $generalArr[$gid] ?? null;
            if ($generalInfo === null) {
                // 旧版这里取到 null 仍生成一个空将领（PHP notice）；新版跳过查不到的站位
                continue;
            }
            $eids = $equipBySite[$gid] ?? [];

            $general = [];
            foreach (self::GENERAL_ATTR as $attr) {
                $general[$attr] = $generalInfo[$attr];
                if (in_array($attr, ['wuli', 'zhili', 'tili'], true)) {
                    $general[$attr] = (int)$general[$attr] + (int)$generalInfo[$attr . 'up'];
                    // 依赖未移植：名将进阶初始属性 _advanced_arr[ugid][wuli/zhili/tili]（主公 100 级）
                    $general[$attr] += 0;
                }
            }
            $general['uid'] = $uid;

            $level = (int)$generalInfo['level'];
            $turn = (int)$generalInfo['turn'];
            // 依赖未移植：宝石加成 gem_power（_get_general_attack 等的第三项）
            $gemPower = 0;
            // 攻击 / 物防 / 策防 / 血量 = 武将成长值 + 对应装配位置装备 powerup + gem_power（_get_general_attack / _defense_1 / _defense_2 / _blood）
            $general['attack'] = (int)(LegacyStats::baseAttack($level, $turn) + self::slotPowerup($eids, 1) + $gemPower);
            $general['defense1'] = (int)(LegacyStats::baseDefense($level, $turn) + self::slotPowerup($eids, 2) + $gemPower);
            $general['defense2'] = (int)(LegacyStats::basePdefense($level, $turn) + self::slotPowerup($eids, 3) + $gemPower);
            $general['all_blood'] = (int)(LegacyStats::baseHp($level, $turn) + self::slotPowerup($eids, 4) + $gemPower);

            // 依赖未移植：坐骑攻防血（mid0 主战 100%、mid1~mid4 备战 ceil(× 0.3)；坐骑系统 120 级开启）
            $general['attack'] += 0;
            $general['defense1'] += 0;
            $general['defense2'] += 0;
            $general['all_blood'] += 0;
            // 依赖未移植：名将进阶初始血量 _advanced_arr[ugid][hp]（主公 100 级）
            $general['all_blood'] += 0;
            // 依赖未移植：主公天赋 hero_talent（主公 91 级；type 1 猛将攻击 / 3 谋士攻击 / 2 物防 / 4 策防 / 5 血量）
            $general['attack'] += 0;

            // 士气、参与攻击回合数、抗暴击率、命中精准
            $general['power'] = 0;
            $general['round'] = 0;
            $general['kcrit'] = 0;
            $general['mingzhong'] = 0;
            $general['suitbuff'] = ['xixue' => 0, 'clearpower' => 0, 'retpower' => 0, 'thipower' => 0, 'fanshang' => 0];

            // 依赖未移植：锻造套装属性（suit2_* ~ suit4_*，27 类加成）
            // 依赖未移植：将魂 士气(1) / 武力(2) / 智力(3) / 抗暴击率(11)
            // 依赖未移植：名将成就加成 _addition_arr（主公 81 级；三围、血量、士气、攻防、命中、抗暴）
            // 依赖未移植：美人加成 _beauty_arr（主公 60 级；三围、攻防、命中）
            // 依赖未移植：星魄加成 _star_arr（主公 80 级；三围、血量、士气、攻防、命中、抗暴）
            // 依赖未移植：国家学院加成 _warcollege_arr（国家 30 级；三围、士气、攻防、命中）
            // 依赖未移植：将魂 命中(9) / 精准(10)
            $general['power'] += 0;

            // 6 星以上武将战斗特效：只对当前请求的玩家本人生效
            $general['star'] = ((int)$generalInfo['star'] >= 6 && $self) ? 1 : 0;

            // 装备精炼 / 强化成就 / 精炼成就
            $refineInfo = self::additionRefine($eids, [
                'attack' => $general['attack'],
                'defense1' => $general['defense1'],
                'defense2' => $general['defense2'],
                'all_blood' => $general['all_blood'],
            ]);
            $general['wuli'] += $refineInfo['wuli'];
            $general['zhili'] += $refineInfo['zhili'];
            if ((int)$general['type'] === 1) {
                $general['attack'] = $refineInfo['attack1'];
            } elseif ((int)$general['type'] === 2) {
                $general['attack'] = $refineInfo['attack2'];
            }
            $general['defense1'] = $refineInfo['defense1'];
            $general['defense2'] = $refineInfo['defense2'];
            $general['all_blood'] = $refineInfo['all_blood'];

            // 依赖未移植：坐骑模板特殊属性（攻击% / 双防% / 抗暴 / 血量% / 抗晕 / 减伤 / 吸血）
            $general['attack'] += 0;

            // 旧版在 battle() 开头计算总血量（_get_general_all_blood）；这里提前算好，P5 移植 battle() 时挪回去
            $general['allblood'] = self::generalAllBlood($general);
            $general['blood'] = $general['allblood'];

            $team[$pos] = $general;
        }

        $return['status'] = 1;
        $return['team'] = $team;
        return $return;
    }

    /**
     * 怪物队伍（移植 battle_model.php:init_monster_team_info，以及 battle_mission 里的
     * allblood = intval(blood × 天赋血量系数)；旧版所有 PVE 怪物战斗都走 battle_mission）
     *
     * @param array<string, mixed> $positionArr 含 position1~position9（怪物 id，0 为空）的关卡行
     * @param int $type self::MONSTER_* 之一，决定查哪张怪物表
     * @return array{status: int, team: array<int, array<string, mixed>>}
     */
    public static function forMonsters(array $positionArr, int $type = self::MONSTER_MISSION): array
    {
        $rows = match ($type) {
            self::MONSTER_MISSION => Monster::ROWS,
            self::MONSTER_ISLAND => IslandMonster::ROWS,
            self::MONSTER_SOUL => SoulMonster::ROWS,
            self::MONSTER_COUNTRY => CountryMonster::ROWS,
            self::MONSTER_ELITE => EliteMonster::ROWS,
            self::MONSTER_GENERALTASK => GeneraltaskMonster::ROWS,
            self::MONSTER_HEROTHRONE => HerothroneMonster::ROWS,
            self::MONSTER_SANCTUM => SanctumMonster::ROWS,
            self::MONSTER_HEAVEN => HeavenMonster::ROWS,
            self::MONSTER_COPIES => CopiesInfo::ROWS,
            default => throw new RuntimeException("旧版 init_monster_team_info 无此怪物类型: {$type}"),
        };

        $team = [];
        for ($i = 1; $i <= 9; $i++) {
            $id = (int)($positionArr['position' . $i] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $value = self::rowById($rows, $id);
            if ($value === null) {
                throw new RuntimeException("怪物表缺少 id={$id}（type={$type}）");
            }
            $value['allblood'] = $value['blood'];
            $value['power'] = $value['power'] ?? 0;
            $value['round'] = 0;
            $value['defense1'] = $value['pdefense'] ?? 0;
            $value['defense2'] = $value['mdefense'] ?? 0;
            $value['dodge'] = $value['dodge'] ?? 0;
            $value['penetrate'] = $value['penetrate'] ?? 0;
            $value['kangyun'] = $value['kangyun'] ?? 0;
            // battle() 里怪物没有 star 字段时按 0
            $value['star'] = 0;

            // battle_mission：allblood = intval(allblood × 天赋血量系数)，满血
            // 旧版天赋不存在时系数取到 null（按 0 参与乘法），这里同样按 0
            $value['allblood'] = (int)((int)$value['allblood'] * (float)(Talent::ROWS[(int)$value['talentid']]['blood'] ?? 0));
            $value['blood'] = $value['allblood'];

            $team[$i] = $value;
        }

        return ['status' => 1, 'team' => $team];
    }

    /**
     * 返回给客户端的队伍信息（移植 battle_model.php:team_return_arr）
     *
     * 旧版直接输出 MySQL 行，库字段是字符串：heroid / name / herotype / level / image / quality 按字符串输出；
     * allblood / power / star / blood 按整数输出。天赋 / 技能名不存在时为 null（旧版同）。
     * $result 为真时附带战后剩余血量 blood，取自 $heroes（站位 => Hero）的 currentHp。
     *
     * @param array<int, array<string, mixed>> $team
     * @param array<int, Hero> $heroes
     * @return array<int, array<string, mixed>>
     */
    public static function teamReturnArr(array $team, bool $result = false, array $heroes = []): array
    {
        $return = [];
        foreach ($team as $key => $value) {
            $item = [
                'heroid' => (string)$value['id'],
                'name' => (string)$value['name'],
                'herotype' => (string)$value['type'],
                'level' => (string)$value['level'],
                'allblood' => (int)$value['allblood'],
                'power' => (int)$value['power'],
                'image' => (string)$value['image'],
                'star' => (int)$value['star'],
                'talent' => Talent::ROWS[(int)$value['talentid']]['name'] ?? null,
                'skill' => Skill::ROWS[(int)$value['skillid']]['name'] ?? null,
                'quality' => isset($value['quality']) ? (string)$value['quality'] : 1,
            ];
            if ($result) {
                $item['blood'] = isset($heroes[$key]) ? max(0, $heroes[$key]->currentHp) : (int)$value['blood'];
            }
            $return[$key] = $item;
        }
        return $return;
    }

    /**
     * 旧版队伍 → 当前 BattleEngine 的战斗单位（站位 => Hero），换算见 Hero::fromUnit 的临时适配层
     *
     * @param array<int, array<string, mixed>> $team
     * @return array<int, Hero>
     */
    public static function heroes(array $team, int $matrixType = 0, float $matrixCoe = 0.0): array
    {
        $heroes = [];
        foreach ($team as $pos => $unit) {
            $heroes[$pos] = Hero::fromUnit($pos, $unit, $matrixType, $matrixCoe);
        }
        return $heroes;
    }

    /**
     * 战斗总血量（移植 battle_model.php:_get_general_all_blood）
     *
     * @param array<string, mixed> $general
     */
    private static function generalAllBlood(array $general): int
    {
        // 旧版天赋不存在时系数取到 null（按 0 参与乘法），这里同样按 0
        $talentBlood = (float)(Talent::ROWS[(int)$general['talentid']]['blood'] ?? 0);
        // 依赖未移植：名将进阶血量加成 skill4_value（主公 100 级）
        $advancedBlood = 0.0;
        // 依赖未移植：ptblood = 将魂生命(4) + 神树 buff + 套装 / 坐骑血量%
        $ptblood = 0.0;
        return LegacyStats::battleAllBlood((float)$general['all_blood'], (int)$general['tili'], $talentBlood, $advancedBlood, $ptblood);
    }

    /**
     * 装备精炼等级加成、强化成就加成、精炼成就加成（移植 battle_model.php:_addition_refine）
     *
     * 只看 hammer > 0 或 aid 非空的装备；只有至少一件装备有精炼等级时才计算任何加成（旧版同）。
     * 强化成就（战斗口径）只对本身有精炼加成的装备生效，见 LegacyStats::strengthAchAddBattle。
     * 精炼成就：上述装备件数 >= 4 时，按「去重后的最低精炼等级」>= refine_achieve.level 逐条累加
     * （与将领界面 general_refine_add 按 refine_promote.type 的口径不同，旧版两处如此，不合并）。
     * 旧版 get_streng_achieve 在多个 aid 只查到一行时把单行当多行遍历（取到错误的成就），新版按 id 查表，不复刻该缺陷。
     *
     * @param array<int, array<string, mixed>> $eids 装配位置 1~4 => equipment 行
     * @param array{attack: int, defense1: int, defense2: int, all_blood: int} $base
     * @return array{attack1: int, attack2: int, defense1: int, defense2: int, all_blood: int, wuli: int, zhili: int}
     */
    private static function additionRefine(array $eids, array $base): array
    {
        $refineArr = [
            'attack1' => $base['attack'],
            'attack2' => $base['attack'],
            'defense1' => $base['defense1'],
            'defense2' => $base['defense2'],
            'all_blood' => $base['all_blood'],
            'wuli' => 0,
            'zhili' => 0,
        ];

        $hammerArr = [];
        for ($j = 1; $j <= 4; $j++) {
            $equipInfo = $eids[$j] ?? null;
            if ($equipInfo === null) {
                continue;
            }
            if ((int)$equipInfo['hammer'] > 0 || (string)$equipInfo['aid'] !== '') {
                $hammerArr[] = $equipInfo;
            }
        }
        if ($hammerArr === []) {
            return $refineArr;
        }

        $hammers = [];
        foreach ($hammerArr as $value) {
            $hammer = (int)$value['hammer'];
            if ($hammer > 0 && !in_array($hammer, $hammers, true)) {
                $hammers[] = $hammer;
            }
        }
        if ($hammers === []) {
            return $refineArr;
        }

        foreach ($hammerArr as $val) {
            $type = (int)$val['type'];
            $power = (int)$val['power'];
            $powerup = (int)$val['powerup'];
            // 精炼等级加成：round((powerup - power) × refine_promote.percent / 100)
            $upVal = LegacyStats::refineAdd($power, $powerup, (int)$val['hammer']);
            self::addRefineInfo($refineArr, $type, $upVal);
            // 强化成就加成
            $achVal = LegacyStats::strengthAchAddBattle($powerup, $upVal, $type, (string)$val['aid']);
            self::addRefineInfo($refineArr, $type, $achVal);
        }

        // 精炼成就
        if (count($hammerArr) >= 4) {
            sort($hammers);
            foreach (RefineAchieve::ROWS as $reAch) {
                if ($hammers[0] >= (int)$reAch['level']) {
                    for ($j = 1; $j <= 4; $j++) {
                        $add = (int)$reAch['add_type_val' . $j];
                        match ((int)$reAch['add_type' . $j]) {
                            1 => $refineArr['wuli'] += $add,
                            2 => $refineArr['zhili'] += $add,
                            3 => $refineArr['attack1'] += $add,
                            4 => $refineArr['attack2'] += $add,
                            default => null,
                        };
                    }
                }
            }
        }

        return $refineArr;
    }

    /**
     * 按装备类型把加成值记到对应属性（移植 battle_model.php:_get_refine_add_info）
     *
     * @param array<string, int> $refineArr
     */
    private static function addRefineInfo(array &$refineArr, int $type, int $upVal): void
    {
        $field = match ($type) {
            1 => 'attack1',
            2 => 'attack2',
            3 => 'defense1',
            4 => 'defense2',
            5 => 'all_blood',
            default => null,
        };
        if ($field !== null) {
            $refineArr[$field] += $upVal;
        }
    }

    /**
     * 装配位置上装备的 powerup；没有装备为 0（旧版 equipment_arr[0] = ['powerup' => 0]）
     *
     * @param array<int, array<string, mixed>> $eids
     */
    private static function slotPowerup(array $eids, int $site): int
    {
        return isset($eids[$site]) ? (int)$eids[$site]['powerup'] : 0;
    }

    /**
     * 将领身上的装备（将领 id => [装配位置 1~4 => equipment 行]）
     *
     * @param list<int> $gids
     * @return array<int, array<int, array<string, mixed>>>
     */
    private static function equipmentBySite(int $uid, array $gids): array
    {
        if ($gids === []) {
            return [];
        }
        $rows = DB::table('equipment')
            ->where('uid', $uid)
            ->whereIn('generalid', $gids)
            ->whereNull('deleted_at')
            ->get();
        $result = [];
        foreach ($rows as $item) {
            $site = LegacyStats::itemSite((int)$item['type']);
            if ($site === null) {
                continue;
            }
            $result[(int)$item['generalid']][$site] = $item;
        }
        return $result;
    }

    /**
     * 按 id 字段取怪物行（部分旧版表的 MasterData 不以 id 为键，如 CountryMonster / EliteMonster）
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>|null
     */
    private static function rowById(array $rows, int $id): ?array
    {
        $row = $rows[$id] ?? null;
        if ($row !== null && (int)$row['id'] === $id) {
            return $row;
        }
        foreach ($rows as $candidate) {
            if ((int)$candidate['id'] === $id) {
                return $candidate;
            }
        }
        return null;
    }
}
