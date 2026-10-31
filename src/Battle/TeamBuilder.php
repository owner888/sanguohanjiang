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
use Hanjiang\MasterData\Monster;
use Hanjiang\MasterData\MountTemp;
use Hanjiang\MasterData\RefineAchieve;
use Hanjiang\MasterData\SanctumMonster;
use Hanjiang\MasterData\Skill;
use Hanjiang\MasterData\SoulMonster;
use Hanjiang\MasterData\Talent;
use Hanjiang\Model\Advanced;
use Hanjiang\Model\Beauty;
use Hanjiang\Model\GeneralBook;
use Hanjiang\Model\HeroTalent;
use Hanjiang\Model\OwnGem;
use Hanjiang\Model\OwnMatrix;
use Hanjiang\Model\OwnMount;
use Hanjiang\Model\OwnSoul;
use Hanjiang\Model\OwnStar;
use Hanjiang\Model\WarCollege;
use OmniPHP\Database\DB;
use RuntimeException;

/**
 * 战斗队伍组装（逐段移植旧版 models/battle_model.php，路径相对旧版 s1/application/）
 *
 * - forMember     ← init_member_team_info（本体 + 装备 + 阵法 + 模板天赋/技能部分）、_addition_refine
 * - forMonsters   ← init_monster_team_info，以及 battle_mission 里按天赋血量系数放大怪物血量（团战 battle_rush 不放大）
 * - teamReturnArr ← team_return_arr
 * - forBoss       ← battle_worldboss 的守方组装（世界 BOSS / 国家 BOSS）
 * - unportedUnit  ← 守军尚未按旧版移植的玩法：把新版自定的数值装成与 forMonsters 相同字段的旧版怪物单位
 *
 * 战斗总血量 allblood（_get_general_all_blood）在 BattleEngine::battle 里计算（旧版 battle() 开头）。
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

    /** 星魄加成开启的主公等级（init_member_team_info：$level >= 80） */
    private const int STAR_OPEN_LEVEL = 80;

    /** 美人加成开启的主公等级（init_member_team_info：$level >= 60） */
    private const int BEAUTY_OPEN_LEVEL = 60;

    /** 主公天赋开启的主公等级（init_member_team_info：$level >= 91） */
    private const int HERO_TALENT_OPEN_LEVEL = 91;

    /** 名将成就加成开启的主公等级（init_member_team_info：$level >= 81） */
    private const int ACHIEVEMENT_OPEN_LEVEL = 81;

    /** 名将进阶开启的主公等级（init_member_team_info：$level >= 100） */
    private const int ADVANCED_OPEN_LEVEL = 100;

    /**
     * 玩家队伍（移植 battle_model.php:init_member_team_info）
     *
     * 返回 status 同旧版：1 成功；-1 没有阵法信息；-2 阵法里没有上阵武将；-3 上阵武将一个都查不到。
     * 失败时 team 为空数组（旧版同）。旧版 battle_mission / battle_pk 等对 -2 按空队伍继续战斗（空队伍直接判 -3 大败），
     * 对 -1 / -3 返回错误，由调用方按各自旧版控制器处理。
     *
     * 阵法同旧版：读 member_field.matrixid 指向的拥有阵法（Model\OwnMatrix）。matrixid 为 0 返回 -1；
     * 启用的阵法行查不到时旧版 empty() 判断不成立，继续按空站位、等级 0 处理（最终 -2）。
     *
     * @param bool $defense 防守方（旧版 $defense，只决定阵法记录到左 / 右侧；这里两侧同样返回 matrix_type / matrix_coe）
     * @param bool $pve     旧版 $this->_pve：PVE 战斗阵法加成系数上限 0.5
     * @param bool $self    是否为当前请求的玩家本人（旧版 $uid == $this->member['uid']），只影响 6 星战斗特效 star
     * @param int $arguGid  神将之门上阵武将 id（旧版 $argu_gid：不用阵法，将领站 4 号位，没有阵法加成）
     * @return array{status: int, team: array<int, array<string, mixed>>, matrix_type: int, matrix_coe: float}
     */
    public static function forMember(int $uid, bool $defense = false, bool $pve = false, bool $self = true, int $arguGid = 0): array
    {
        $return = ['status' => 0, 'team' => [], 'matrix_type' => 0, 'matrix_coe' => 0.0];

        // 主公等级（旧版本人取 $this->member['level']，他人取 get_base_info；玩家不存在按 0）
        $level = (int)(DB::table('member')->where('uid', $uid)->whereNull('deleted_at')->value('level') ?? 0);

        // 依赖未移植（旧版此处预取，按 0 计入下方各步）：神树 sacredtree_model->get_tree_by_uid（活动开启时）

        // 名将成就加成（general_model->get_own_addition：主公 81 级开启，battle_model.php:1347；没有记录时为空，不加成）。
        // 旧版记在 _addition_arr[uuid]，这里记在本队每个单位的 addition_arr 上（BattleEngine 读取暴击率 / 闪避率 / 识破率）
        $additionArr = $level >= self::ACHIEVEMENT_OPEN_LEVEL ? GeneralBook::ownAddition($uid) : [];

        // 星魄加成（star_model->get_star_addition：主公 80 级开启；没有记录时建一行全 0，同旧版）。
        // 旧版记在 _star_arr[uuid]，这里记在本队每个单位的 star_arr 上（BattleEngine 读取暴击 / 闪避 / 识破 / 暴击伤害 / 抗晕）
        $starArr = $level >= self::STAR_OPEN_LEVEL ? OwnStar::addition($uid) : null;

        // 美人加成（beauty_model->get_beauty_addition：主公 60 级开启，battle_model.php:1351）。
        // 旧版记在 _beauty_arr[uuid]，这里记在本队每个单位的 beauty_arr 上（BattleEngine 读取闪避 / 识破 / 暴击伤害）
        $beautyArr = $level >= self::BEAUTY_OPEN_LEVEL ? Beauty::addition($uid, time()) : null;

        // 国家学院加成（warcollege_model->get_warcollege_addition：国家 30 级以上、学院 buff 未结束时）
        $warcollegeArr = self::warcollegeAddition($uid);

        if ($arguGid > 0) {
            // 神将之门 不适用阵法 将领默认站4号位
            $matrix = [4 => $arguGid];
        } else {
            // 阵法：旧版 member_field.matrixid 为 0 返回 -1
            $matrixid = OwnMatrix::activeId($uid);
            if ($matrixid === 0) {
                $return['status'] = -1;
                return $return;
            }
            $matrixInfo = OwnMatrix::own($uid, $matrixid);
            $matrix = [];
            for ($i = 1; $i <= 9; $i++) {
                $gid = $matrixInfo['position' . $i] ?? 0;
                if ($gid > 0) {
                    $matrix[$i] = $gid;
                }
            }

            // 阵法类型与加成（matrix_model->get_rule_by_id：percent = 阵法等级 × intval(Matrix.addition)，coe = percent × 0.01）
            // 旧版阵法模板不存在时 get_rule_by_id 不返回 addition，type 取到 null、coe 为 0；这里同样按 0
            $rule = OwnMatrix::rule($matrixid, $matrixInfo['level'] ?? 0);
            $matrixType = $rule !== null ? $rule['addition']['type'] : 0;
            $matrixCoe = $rule !== null ? $rule['addition']['percent'] * 0.01 : 0.0;
            // PVE 阵法加成上限控制（攻守两侧同）
            if ($pve && $matrixCoe > 0.5) {
                $matrixCoe = 0.5;
            }
            $return['matrix_type'] = $matrixType;
            $return['matrix_coe'] = $matrixCoe;
        }

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

        // 坐骑：mount_model->get_info_by_id（own_general.mid0~mid4 → own_mount.id）
        $mountIds = [];
        foreach ($generalArr as $row) {
            for ($i = 0; $i <= 4; $i++) {
                if ((int)$row['mid' . $i] > 0) {
                    $mountIds[(int)$row['mid' . $i]] = (int)$row['mid' . $i];
                }
            }
        }
        $mountArr = $mountIds === [] ? [] : OwnMount::infoById($uid, array_values($mountIds));
        // 本队品级最高的坐骑（旧版 _mount_info[uuid]：按站位顺序，品级更高才替换），用于战斗 buff 的 mount
        $mountInfo = [];

        // 宝石：gem_model->get_gem_by_eid，按装备累加 gem_power
        $gemPowerByEid = self::gemPowerByEid($uid, $equipBySite);

        // 依赖未移植：锻造套装 equip_forge_model->get_suit_attr（newquality > 0 的装备）

        // 将魂（soul_model->get_general_soul / get_soul_by_id：转生 > 0 的将领槽位上的将魂，[将领 id => [类型 => 属性]]）。
        // 旧版记在 _general_soul[serverid_gid]，这里记在单位的 soul 上（BattleEngine 读取生命 4 / 闪避 5 / 识破 6 / 暴击率 7 / 暴击伤害 8 / 抗晕 12）
        $soulGeneralArr = [];
        foreach ($generalArr as $gid => $row) {
            if ((int)$row['turn'] > 0) {
                $soulGeneralArr[] = $gid;
            }
        }
        $generalSoul = OwnSoul::battleSouls($soulGeneralArr);

        // 主公天赋（talent_model->get_talent_list：主公 91 级开启；没有记录时建 5 行，同旧版）
        $heroTalentArr = $level >= self::HERO_TALENT_OPEN_LEVEL ? HeroTalent::list($uid) : [];

        // 名将进阶（advanced_model->get_advanced_by_gid：主公 100 级开启，battle_model.php:1569；没有进阶记录的将领不加成）。
        // 旧版记在 _advanced_arr[ugid]，这里记在单位的 advanced 上（BattleEngine 读取天赋攻防系数 skill1 ~ 3、生命系数 skill4）
        $advancedArr = $level >= self::ADVANCED_OPEN_LEVEL ? Advanced::byGids($uid, array_keys($generalArr)) : [];

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
                    // 名将进阶 增加初始属性
                    if (isset($advancedArr[$gid])) {
                        $general[$attr] += $advancedArr[$gid][$attr];
                    }
                }
            }
            $general['uid'] = $uid;
            if (isset($advancedArr[$gid])) {
                $general['advanced'] = $advancedArr[$gid];
            }

            $level = (int)$generalInfo['level'];
            $turn = (int)$generalInfo['turn'];
            // 攻击 / 物防 / 策防 / 血量 = 武将成长值 + 对应装配位置装备 powerup + 该装备的宝石 gem_power
            // （_get_general_attack / _defense_1 / _defense_2 / _blood）
            $general['attack'] = (int)(LegacyStats::baseAttack($level, $turn) + self::slotPowerup($eids, 1) + self::slotGemPower($eids, 1, $gemPowerByEid));
            $general['defense1'] = (int)(LegacyStats::baseDefense($level, $turn) + self::slotPowerup($eids, 2) + self::slotGemPower($eids, 2, $gemPowerByEid));
            $general['defense2'] = (int)(LegacyStats::basePdefense($level, $turn) + self::slotPowerup($eids, 3) + self::slotGemPower($eids, 3, $gemPowerByEid));
            $general['all_blood'] = (int)(LegacyStats::baseHp($level, $turn) + self::slotPowerup($eids, 4) + self::slotGemPower($eids, 4, $gemPowerByEid));

            // 坐骑攻防血：mid0 主战 100%，mid1~mid4 备战 ceil(× 0.3)
            $ownMountTemp = [];
            for ($i = 0; $i <= 4; $i++) {
                $mount = $mountArr[(int)$generalInfo['mid' . $i]] ?? null;
                if ($mount === null) {
                    continue;
                }
                $ownMountTemp[(int)$mount['id']] = ['mid' => (int)$mount['mid'], 'level' => (int)$mount['level']];
                if ($i === 0) {
                    $general['attack'] += (int)$mount['attack'];
                    $general['defense1'] += (int)$mount['wufang'];
                    $general['defense2'] += (int)$mount['cefang'];
                    $general['all_blood'] += (int)$mount['hp'];
                } else {
                    $general['attack'] += (int)ceil((int)$mount['attack'] * 0.3);
                    $general['defense1'] += (int)ceil((int)$mount['wufang'] * 0.3);
                    $general['defense2'] += (int)ceil((int)$mount['cefang'] * 0.3);
                    $general['all_blood'] += (int)ceil((int)$mount['hp'] * 0.3);
                }
                if ($mountInfo === [] || (int)$mount['quality'] > (int)$mountInfo['quality']) {
                    $mountInfo = $mount;
                }
            }
            // 名将进阶初始血量
            if (isset($advancedArr[$gid])) {
                $general['all_blood'] += $advancedArr[$gid]['hp'];
            }
            // 主公天赋：type 1 猛将攻击 / 3 谋士攻击 / 2 物防 / 4 策防 / 5 血量
            foreach ($heroTalentArr as $value) {
                if ($value['type'] === 1 && (int)$general['type'] === 1) {
                    $general['attack'] += $value['power'];
                } elseif ($value['type'] === 3 && (int)$general['type'] === 2) {
                    $general['attack'] += $value['power'];
                } elseif ($value['type'] === 2) {
                    $general['defense1'] += $value['power'];
                } elseif ($value['type'] === 4) {
                    $general['defense2'] += $value['power'];
                } elseif ($value['type'] === 5) {
                    $general['all_blood'] += $value['power'];
                }
            }

            // 士气、参与攻击回合数、抗暴击率、命中精准
            $general['power'] = 0;
            $general['round'] = 0;
            $general['kcrit'] = 0;
            $general['mingzhong'] = 0;
            $general['suitbuff'] = ['xixue' => 0, 'clearpower' => 0, 'retpower' => 0, 'thipower' => 0, 'fanshang' => 0];

            // 依赖未移植：锻造套装属性（suit2_* ~ suit4_*，27 类加成）

            // 士气 武力 智力 抗暴击率 将魂加成
            $soul = $generalSoul[$gid] ?? null;
            if ($soul !== null) {
                $general['soul'] = $soul;
                $general['power'] += $soul[1] ?? 0;
                $general['wuli'] += $soul[2] ?? 0;
                $general['zhili'] += $soul[3] ?? 0;
                $general['kcrit'] += $soul[11] ?? 0;
            }

            // 名将成就加成（battle_model.php:1864 ~ 1882）
            if ($additionArr !== []) {
                $general['addition_arr'] = $additionArr;
                // 武力 智力 体力 血量 士气
                $general['wuli'] += $additionArr['wuli'];
                $general['zhili'] += $additionArr['zhili'];
                $general['tili'] += $additionArr['tili'];
                $general['all_blood'] += $additionArr['blood'];
                $general['power'] += $additionArr['power'];
                // 物攻 / 命中（猛将），策攻 / 精准（谋士）
                if ((int)$general['type'] === 1) {
                    $general['attack'] += $additionArr['attack'];
                    $general['mingzhong'] += $additionArr['mingzhong'];
                } elseif ((int)$general['type'] === 2) {
                    $general['attack'] += $additionArr['pattack'];
                    $general['mingzhong'] += $additionArr['jingzhun'];
                }
                // 物防、策防、抗暴击率
                $general['defense1'] += $additionArr['defense'];
                $general['defense2'] += $additionArr['pdefense'];
                $general['kcrit'] += $additionArr['kcrit'];
            }
            // 美人加成（battle_model.php:1883 ~ 1912）
            if ($beautyArr !== null) {
                $general['beauty_arr'] = $beautyArr;
                // 武力 智力 体力
                $general['wuli'] += $beautyArr['wuli'];
                $general['zhili'] += $beautyArr['zhili'];
                $general['tili'] += $beautyArr['tili'];
                // 物攻 / 命中（猛将），策攻 / 精准（谋士）
                if ((int)$general['type'] === 1) {
                    $general['attack'] += $beautyArr['attack'];
                    $general['mingzhong'] += $beautyArr['mingzhong'];
                } elseif ((int)$general['type'] === 2) {
                    $general['attack'] += $beautyArr['pattack'];
                    $general['mingzhong'] += $beautyArr['jingzhun'];
                }
                // 物防、策防
                $general['defense1'] += $beautyArr['defense'];
                $general['defense2'] += $beautyArr['pdefense'];
            }

            // 星魄加成
            if ($starArr !== null) {
                $general['star_arr'] = $starArr;
                // 武力 智力 体力 血量 士气
                $general['wuli'] += $starArr['wuli'];
                $general['zhili'] += $starArr['zhili'];
                $general['tili'] += $starArr['tili'];
                $general['all_blood'] += $starArr['blood'];
                $general['power'] += $starArr['power'];
                // 物攻 / 命中（猛将），策攻 / 精准（谋士）
                if ((int)$general['type'] === 1) {
                    $general['attack'] += $starArr['attack'];
                    $general['mingzhong'] += $starArr['mingzhong'];
                } elseif ((int)$general['type'] === 2) {
                    $general['attack'] += $starArr['pattack'];
                    $general['mingzhong'] += $starArr['jingzhun'];
                }
                // 物防、策防、抗暴击率
                $general['defense1'] += $starArr['defense'];
                $general['defense2'] += $starArr['pdefense'];
                $general['kcrit'] += $starArr['kcrit'];
            }

            // 国家学院加成（battle_model.php:1950 ~ 1979）：武力 智力 体力 士气，物攻 / 命中（猛将）、策攻 / 精准（谋士），物防 策防。
            // 旧版记在 _warcollege_arr[uuid]，这里记在单位的 warcollege_arr 上（BattleEngine 读取闪避率 / 识破率）
            if ($warcollegeArr !== null) {
                $general['warcollege_arr'] = $warcollegeArr;
                $general['wuli'] += $warcollegeArr['wuli'];
                $general['zhili'] += $warcollegeArr['zhili'];
                $general['tili'] += $warcollegeArr['tili'];
                $general['power'] += $warcollegeArr['power'];
                if ((int)$general['type'] === 1) {
                    $general['attack'] += $warcollegeArr['attack'];
                    $general['mingzhong'] += $warcollegeArr['mingzhong'];
                } elseif ((int)$general['type'] === 2) {
                    $general['attack'] += $warcollegeArr['pattack'];
                    $general['mingzhong'] += $warcollegeArr['jingzhun'];
                }
                $general['defense1'] += $warcollegeArr['defense'];
                $general['defense2'] += $warcollegeArr['pdefense'];
            }

            // 将魂命中（猛将 9）/ 精准（其他 10）：已有命中时累加，否则直接取将魂值
            $mingzhongSoul = (int)$general['type'] === 1 ? ($soul[9] ?? null) : ($soul[10] ?? null);
            if ($mingzhongSoul !== null) {
                if ($general['mingzhong'] > 0) {
                    $general['mingzhong'] += $mingzhongSoul;
                } else {
                    $general['mingzhong'] = $mingzhongSoul;
                }
            }

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

            // 坐骑模板特殊属性（type 1 攻击% / 2 双防% / 3 抗暴 / 4 血量% / 5 抗晕 / 6 减伤 / 7 吸血），
            // 加成值 = value + level × 坐骑等级；4 ~ 6 旧版记在 _suit_arr[ugid]，这里记在单位的 suit 上（BattleEngine 读取）
            foreach ($ownMountTemp as $own) {
                $temp = MountTemp::ROWS[$own['mid']] ?? null;
                if ($temp === null || $temp['type'] <= 0) {
                    continue;
                }
                $mountRatio = $temp['value'] + $temp['level'] * $own['level'];
                switch ($temp['type']) {
                    case 1:
                        $general['attack'] += (int)($general['attack'] * ($mountRatio / 100));
                        break;
                    case 2:
                        $general['defense1'] += (int)($general['defense1'] * ($mountRatio / 100));
                        $general['defense2'] += (int)($general['defense2'] * ($mountRatio / 100));
                        break;
                    case 3:
                        $general['kcrit'] += $mountRatio;
                        break;
                    case 4:
                        $general['suit']['ptblood'] = ($general['suit']['ptblood'] ?? 0) + $mountRatio;
                        break;
                    case 5:
                        $general['suit']['kangyun'] = ($general['suit']['kangyun'] ?? 0) + $mountRatio;
                        break;
                    case 6:
                        $general['suit']['lastjianhurt'] = ($general['suit']['lastjianhurt'] ?? 0) + $mountRatio;
                        break;
                    case 7:
                        $general['suitbuff']['xixue'] += $mountRatio;
                        break;
                }
            }

            $team[$pos] = $general;
        }

        // 战斗 buff 的坐骑（旧版 battle() 按单位 uuid 取 _mount_info；同一玩家各单位相同）
        if ($mountInfo !== []) {
            foreach ($team as $pos => $general) {
                $team[$pos]['mountinfo'] = ['mid' => (int)$mountInfo['mid'], 'name' => (string)$mountInfo['name'], 'quality' => (int)$mountInfo['quality']];
            }
        }

        $return['status'] = 1;
        $return['team'] = $team;
        return $return;
    }

    /**
     * 怪物队伍（移植 battle_model.php:init_monster_team_info，以及 battle_mission 里的
     * allblood = intval(blood × 天赋血量系数)；旧版单场 PVE 怪物战斗都走 battle_mission）
     *
     * 旧版团战 battle_rush（BattleRush，多人副本 / 国战 PVE）直接调 init_monster_team_info，不乘天赋血量系数：
     * 传 $missionBlood = false，allblood = blood = 怪物表血量。
     *
     * @param array<string, mixed> $positionArr 含 position1~position9（怪物 id，0 为空）的关卡行
     * @param int $type self::MONSTER_* 之一，决定查哪张怪物表
     * @param bool $missionBlood 是否按 battle_mission 乘天赋血量系数
     * @return array{status: int, team: array<int, array<string, mixed>>}
     */
    public static function forMonsters(array $positionArr, int $type = self::MONSTER_MISSION, bool $missionBlood = true): array
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

            if ($missionBlood) {
                // battle_mission：allblood = intval(allblood × 天赋血量系数)，满血
                // 旧版天赋不存在时系数取到 null（按 0 参与乘法），这里同样按 0
                $value['allblood'] = (int)((int)$value['allblood'] * (float)(Talent::ROWS[(int)$value['talentid']]['blood'] ?? 0));
                $value['blood'] = $value['allblood'];
            }

            $team[$i] = $value;
        }

        return ['status' => 1, 'team' => $team];
    }

    /**
     * 返回给客户端的队伍信息（移植 battle_model.php:team_return_arr）
     *
     * 旧版直接输出 MySQL 行，库字段是字符串：heroid / name / herotype / level / image / quality 按字符串输出；
     * allblood / power / star / blood 按整数输出。天赋 / 技能名不存在时为 null（旧版同）。
     * $result 为真时附带当前血量 blood（BattleEngine 用战后队伍调用：阵亡单位已移出队伍，士气为战后士气）。
     *
     * @param array<int, array<string, mixed>> $team
     * @return array<int, array<string, mixed>>
     */
    public static function teamReturnArr(array $team, bool $result = false): array
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
                $item['blood'] = (int)$value['blood'];
            }
            $return[$key] = $item;
        }
        return $return;
    }

    /**
     * BOSS 守方队伍（移植 battle_model.php:battle_worldboss 的守方部分）
     *
     * $bossinfo 为世界 / 国家 BOSS 信息（worldboss_model / countryboss_model->get_boss_info：world_boss 行 + 写死的战斗数值，
     * blood 为当前剩余血量）。旧版补 power / round = 0、defense1 / defense2 = pdefense / mdefense，固定站 5 号位；
     * 其余字段原样带入战斗（没有的字段旧版按 null 参与计算）。
     *
     * @param array<string, mixed> $bossinfo
     * @return array<int, array<string, mixed>>
     */
    public static function forBoss(array $bossinfo): array
    {
        $bossinfo['power'] = 0;
        $bossinfo['round'] = 0;
        $bossinfo['defense1'] = $bossinfo['pdefense'];
        $bossinfo['defense2'] = $bossinfo['mdefense'];
        return [Position::MidMiddle->value => $bossinfo];
    }

    /**
     * 守军尚未按旧版移植的玩法用的单位：字段与 forMonsters 的输出相同，数值是调用方新版自定的
     *
     * 依赖未移植：调用方的怪物 / 守军数值。旧版字段除 attack / blood / pdefense / mdefense 外按 0 记
     * （type 0 按旧版 process_hurt 的非猛将分支比智力、取策攻系数；talentid 0 时旧版 process_hurt 不给防御赋值，防御按 0 计）。
     * 移植该玩法的怪物表后改用 forMonsters。
     *
     * @return array<string, mixed>
     */
    public static function unportedUnit(int $id, string $name, int $hp, int $attack, int $defense): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'image' => '',
            'level' => 0,
            'blood' => $hp,
            'wuli' => 0,
            'zhili' => 0,
            'type' => 0,
            'attack' => $attack,
            'pdefense' => $defense,
            'mdefense' => $defense,
            'talentid' => 0,
            'skillid' => 0,
            'power' => 0,
            'mingzhong' => 0,
            'kangyun' => 0,
            'quality' => 0,
            'allblood' => $hp,
            'round' => 0,
            'defense1' => $defense,
            'defense2' => $defense,
            'dodge' => 0,
            'penetrate' => 0,
            'star' => 0,
        ];
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
     * 某装配位置装备上镶嵌的宝石属性和（equipment_arr[eid]['gem_power']；没有装备为 0）
     *
     * @param array<int, array<string, mixed>> $eids
     * @param array<int, int> $gemPowerByEid
     */
    private static function slotGemPower(array $eids, int $site, array $gemPowerByEid): int
    {
        return isset($eids[$site]) ? ($gemPowerByEid[(int)$eids[$site]['id']] ?? 0) : 0;
    }

    /**
     * 上阵将领装备上的宝石属性（gem_model->get_gem_by_eid(装备 id 数组)：按 eid 累加 power）
     *
     * @param array<int, array<int, array<string, mixed>>> $equipBySite
     * @return array<int, int> 装备 id => 宝石属性和
     */
    private static function gemPowerByEid(int $uid, array $equipBySite): array
    {
        $eids = [];
        foreach ($equipBySite as $sites) {
            foreach ($sites as $item) {
                $eids[] = (int)$item['id'];
            }
        }
        $return = [];
        foreach ($eids === [] ? [] : OwnGem::byEid($uid, $eids) as $gem) {
            $return[(int)$gem['eid']] = ($return[(int)$gem['eid']] ?? 0) + (int)$gem['power'];
        }
        return $return;
    }

    /**
     * 国家学院加成（battle_model.php:1361 ~ 1367：国家 30 级以上才取 get_warcollege_addition）；没有加成为 null
     *
     * @return array<string, int>|null
     */
    private static function warcollegeAddition(int $uid): ?array
    {
        return WarCollege::addition($uid);
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
