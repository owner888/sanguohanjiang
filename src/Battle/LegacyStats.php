<?php

declare(strict_types=1);

namespace Hanjiang\Battle;

use Hanjiang\MasterData\EquipLevel;
use Hanjiang\MasterData\EquipStrengachieve;
use Hanjiang\MasterData\RefinePromote;
use InvalidArgumentException;

/**
 * 旧版武将 / 装备数值公式（纯函数，逐条移植自旧版 CodeIgniter 源码）
 *
 * 每个函数注释里标出移植来源「文件:函数」（路径相对旧版 s1/application/）。
 * 计算顺序、取整位置与旧版保持一致：旧版返回浮点的地方这里也返回 float，
 * 由调用方在旧版取整的位置再取整，不提前 intval。
 * 尚未移植的加成（主公天赋、名将进阶、将魂、套装、神树、坐骑……）一律作为参数显式传入。
 */
final class LegacyStats
{
    /**
     * 转生系数表
     * 来源：models/general_model.php:turn_coefficient
     */
    public const array TURN_COEFFICIENT = [
        0 => 1,      1 => 1.15,
        2 => 1.3,    3 => 1.45,
        4 => 1.65,   5 => 1.85,
        6 => 2.05,   7 => 2.3,
        8 => 2.55,   9 => 2.8,
        10 => 3.1,   11 => 3.4,
        12 => 3.7,   13 => 4.05,
        14 => 4.4,   15 => 4.75,
        16 => 5.15,  17 => 5.55,
        18 => 5.95,  19 => 6.4,
        20 => 6.85,  21 => 7.3,
        22 => 7.8,   23 => 8.3,
        24 => 8.8,   25 => 9.35,
        26 => 9.9,   27 => 10.45,
        28 => 11.05, 29 => 11.65,
        30 => 12.25, 31 => 12.9,
        32 => 13.55, 33 => 14.2,
        34 => 14.9,  35 => 15.6,
        36 => 16.3,  37 => 17.05,
        38 => 17.8,  39 => 18.55,
        40 => 19.35, 41 => 20.15,
        42 => 20.95, 43 => 21.8,
        44 => 22.65, 45 => 23.5,
        46 => 24.4,  47 => 25.3,
        48 => 26.2,  49 => 27.15,
        50 => 28.1,  51 => 29.05,
        52 => 30.05, 53 => 31.05,
        54 => 32.05, 55 => 33.1,
        56 => 34.15, 57 => 35.2,
        58 => 36.3,  59 => 37.4,
        60 => 38.5,  61 => 39.65,
        62 => 40.8,  63 => 41.95,
        64 => 43.15, 65 => 44.35,
        66 => 45.55, 67 => 46.8,
        68 => 48.05, 69 => 49.3,
        70 => 50.6,
    ];

    /**
     * 装备类型 → 武将装配位置（1 武器、2 铠甲、3 披风、4 兵符）
     * 来源：models/equipment_model.php:get_item_site
     */
    private const array ITEM_SITE = [1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 4];

    /**
     * 部件强化系数
     * 来源：models/equipment_model.php:equip_type
     */
    private const array EQUIP_TYPE_COEF = [1 => 2.2, 2 => 2.2, 3 => 1, 4 => 1, 5 => 1.6];

    /**
     * 品级强化系数
     * 来源：models/equipment_model.php:equip_quality
     */
    private const array EQUIP_QUALITY_COEF = [1 => 1, 2 => 1.3, 3 => 1.6, 4 => 1.9, 5 => 2.2, 6 => 3.5, 7 => 4.5];

    /**
     * 装备每级强化成长值 [type][quality]
     * 来源：models/equipment_model.php:equip_power
     */
    private const array EQUIP_POWER = [
        1 => [1 => 12, 2 => 16, 3 => 20, 4 => 24, 5 => 28, 6 => 35, 7 => 46],
        2 => [1 => 12, 2 => 16, 3 => 20, 4 => 24, 5 => 28, 6 => 35, 7 => 46],
        3 => [1 => 6, 2 => 8, 3 => 10, 4 => 12, 5 => 14, 6 => 18, 7 => 23],
        4 => [1 => 6, 2 => 8, 3 => 10, 4 => 12, 5 => 14, 6 => 18, 7 => 23],
        5 => [1 => 36, 2 => 48, 3 => 60, 4 => 72, 5 => 84, 6 => 102, 7 => 138],
    ];

    /**
     * 转生系数（表外的转数按 1 计）
     * 来源：models/general_model.php:turn_coefficient（用法见同文件 attack/defense/pdefense/hp 的 isset 判断）
     */
    public static function turnCoefficient(int $turn): float
    {
        return (float)(self::TURN_COEFFICIENT[$turn] ?? 1);
    }

    /**
     * 当前转数的等级上限（转生所需等级）：50 + 转数 * 5
     * 来源：models/general_model.php:get_turn_rule
     */
    public static function levelCap(int $turn): int
    {
        return 50 + max(0, $turn) * 5;
    }

    /**
     * 升到下一级所需经验：80 * 等级^2，等级 < 1 返回 0
     * 来源：models/general_model.php:get_levelupexp
     */
    public static function levelUpExp(int $level): int
    {
        if ($level < 1) {
            return 0;
        }
        return 80 * $level * $level;
    }

    /**
     * 武将成长攻击：40 + (等级-1) * 10 * 转生系数
     * 来源：models/general_model.php:attack
     */
    public static function baseAttack(int $level, int $turn): float
    {
        return 40 + ($level - 1) * 10 * self::turnCoefficient($turn);
    }

    /**
     * 武将成长物防：20 + (等级-1) * 5 * 转生系数
     * 来源：models/general_model.php:defense
     */
    public static function baseDefense(int $level, int $turn): float
    {
        return 20 + ($level - 1) * 5 * self::turnCoefficient($turn);
    }

    /**
     * 武将成长策防：20 + (等级-1) * 5 * 转生系数
     * 来源：models/general_model.php:pdefense
     */
    public static function basePdefense(int $level, int $turn): float
    {
        return 20 + ($level - 1) * 5 * self::turnCoefficient($turn);
    }

    /**
     * 武将成长血量：80 + 50 * (等级-1) * 转生系数
     * 来源：models/general_model.php:hp
     */
    public static function baseHp(int $level, int $turn): float
    {
        return 80 + 50 * ($level - 1) * self::turnCoefficient($turn);
    }

    /**
     * 装备类型对应的装配位置；类型 > 5（卷轴等）不可装配，返回 null
     * 来源：models/equipment_model.php:get_item_site
     */
    public static function itemSite(int $type): ?int
    {
        return self::ITEM_SITE[$type] ?? null;
    }

    /**
     * 装备每强化一级的成长值
     * 来源：models/equipment_model.php:equip_power
     */
    public static function equipPowerStep(int $type, int $quality): int
    {
        $step = self::EQUIP_POWER[$type][$quality] ?? null;
        if ($step === null) {
            throw new InvalidArgumentException("旧版 equip_power 无此组合: type={$type}, quality={$quality}");
        }
        return $step;
    }

    /**
     * 强化到 level 级后的能力值：intval(初始值 + 强化等级 * 成长值)
     * 来源：models/equipment_model.php:get_powerup
     */
    public static function powerup(int $power, int $level, int $type, int $quality): int
    {
        return (int)($power + $level * self::equipPowerStep($type, $quality));
    }

    /**
     * 从 currentLevel 强化到下一级的银币花费：
     * ceil(部件系数 * 品级系数 * equip_level[下一级].silver)；已到表尾返回 null
     * 来源：models/equipment_model.php:get_strengthen_info（系数见 equip_type / equip_quality）
     */
    public static function strengthenCost(int $type, int $quality, int $currentLevel): ?int
    {
        $row = EquipLevel::ROWS[$currentLevel + 1] ?? null;
        if ($row === null) {
            return null;
        }
        return (int)ceil(self::typeCoef($type) * self::qualityCoef($quality) * $row['silver']);
    }

    /**
     * 下一级强化规则（当前强化等级为 $level）：
     *   silver = ceil(部件系数 * 品级系数 * equip_level[level+1].silver)
     *   rate / gold / baoji = equip_level[level+1].rate / gold / powerup
     *   power  = 初始值 + (level+1) * 成长值（强化成功后写回 powerup 的值）
     * 已到表尾时 silver/gold/rate/power 全为 0 且没有 baoji 键（旧版同）。
     * 部件 / 品级不在旧版系数表里（如卷轴）时旧版取到 null，按 0 参与运算，这里同样按 0。
     * 来源：models/equipment_model.php:get_strengthen_info
     *
     * @return array{silver: int, gold: int, rate: int, power: int, baoji?: int}
     */
    public static function strengthenRule(int $type, int $quality, int $power, int $level): array
    {
        $return = ['silver' => 0, 'gold' => 0, 'rate' => 0, 'power' => 0];
        $row = EquipLevel::ROWS[$level + 1] ?? null;
        if ($row === null) {
            return $return;
        }
        $typeCoef = (float)(self::EQUIP_TYPE_COEF[$type] ?? 0);
        $qualityCoef = (float)(self::EQUIP_QUALITY_COEF[$quality] ?? 0);
        $return['silver'] = (int)ceil($typeCoef * $qualityCoef * $row['silver']);
        $return['rate'] = (int)$row['rate'];
        $return['power'] = $power + ($level + 1) * (int)(self::EQUIP_POWER[$type][$quality] ?? 0);
        $return['gold'] = (int)$row['gold'];
        $return['baoji'] = (int)$row['powerup'];
        return $return;
    }

    /**
     * 合成一件整装所需碎片数（品级 3→2、4→5、5→10、6→20、7→30，其余 2）
     * 来源：models/equipment_model.php:get_chip_num
     */
    public static function chipNum(int $quality): int
    {
        return match ($quality) {
            4 => 5,
            5 => 10,
            6 => 20,
            7 => 30,
            default => 2,
        };
    }

    /**
     * 精炼等级对应的 refine_promote 行（同 level 取第一行）；hammer <= 0 或不存在返回 null
     * 来源：models/refine_model.php:get_promote_info
     *
     * @return array<string, mixed>|null
     */
    public static function refinePromote(int $hammer): ?array
    {
        if ($hammer <= 0) {
            return null;
        }
        foreach (RefinePromote::ROWS as $row) {
            if ((int)$row['level'] === $hammer) {
                return $row;
            }
        }
        return null;
    }

    /**
     * 强化到 level 级的累计银币花费：Σ round(部件系数 * 品级系数 * 30 * i^2)，i = 1..level
     * 注意旧版这里用 30*i^2 而不是 equip_level.silver，两者数值相同但取整不同（round vs ceil）。
     * 来源：models/equipment_model.php:calc
     */
    public static function strengthenTotalCost(int $type, int $quality, int $level): int
    {
        $cursor = 0.0;
        for ($i = 1; $i <= $level; $i++) {
            $silver = 30 * ($i * $i);
            $cursor += round(self::typeCoef($type) * self::qualityCoef($quality) * $silver);
        }
        return (int)$cursor;
    }

    /**
     * 强化后的出售价格：ceil(模板售价 + 累计强化花费 * 0.6)
     * 来源：models/equipment_model.php:get_saleprice
     */
    public static function salePrice(int $type, int $quality, int $basePrice, int $level): int
    {
        return (int)ceil($basePrice + self::strengthenTotalCost($type, $quality, $level) * 0.6);
    }

    /**
     * 精炼加成值：round((强化后能力 - 初始值) * refine_promote[hammer].percent / 100)
     * hammer <= 0 或 refine_promote 无该等级时为 0。
     * 来源：models/refine_model.php:item_refine_add（battle_model.php:_addition_refine 同公式）
     */
    public static function refineAdd(int $power, int $powerup, int $hammer): int
    {
        if ($hammer <= 0) {
            return 0;
        }
        $percent = self::refinePercent($hammer);
        if ($percent === null) {
            return 0;
        }
        return (int)round(($powerup - $power) * $percent / 100);
    }

    /**
     * 强化成就加成（界面口径）：round(精炼后能力 * 最高成就.add_per{type} / 100)
     * 不要求有精炼加成；aid 为空或成就不存在时为 0。
     * $powerupAfterRefine = powerup + refineAdd(...)
     * 来源：models/refine_model.php:item_refine_add（controllers/general.php:get_unequip_list 同口径）
     */
    public static function strengthAchAddItemRefine(int $powerupAfterRefine, int $type, string $aid): int
    {
        $ach = self::topStrengAchieve($aid);
        if ($ach === null) {
            return 0;
        }
        $addPer = (float)($ach['add_per' . $type] ?? 0);
        return (int)round($powerupAfterRefine * $addPer / 100);
    }

    /**
     * 界面显示的装备能力值与锤子类型：
     *   powerup += refineAdd（有精炼等级且 refine_promote 有该等级时，hammer_quality = 该行 type，否则 0）
     *   powerup += strengthAchAddItemRefine（aid 非空时）
     * 来源：models/refine_model.php:item_refine_add
     * （controllers/general.php:get_unequip_list、controllers/strengthen.php:strengthen_info / strengthen_start 同口径）
     *
     * @return array{powerup: int, hammer_quality: int}
     */
    public static function displayPowerup(int $power, int $powerup, int $type, int $hammer, string $aid): array
    {
        $hammerQuality = 0;
        $promote = self::refinePromote($hammer);
        if ($promote !== null) {
            $powerup += self::refineAdd($power, $powerup, $hammer);
            $hammerQuality = (int)$promote['type'];
        }
        if ($aid !== '') {
            $powerup += self::strengthAchAddItemRefine($powerup, $type, $aid);
        }
        return ['powerup' => $powerup, 'hammer_quality' => $hammerQuality];
    }

    /**
     * 强化成就加成（战斗口径）：只有该装备自身有精炼加成（refineAdd > 0），
     * 且最高成就的 type{装备类型} 指向本装备类型时才生效：
     * round((powerup + refineAdd) * add_per{type} / 100)
     * 与界面口径不同（界面不要求精炼），旧版两处如此，不合并。
     * 来源：models/battle_model.php:_addition_refine
     */
    public static function strengthAchAddBattle(int $powerup, int $refineAdd, int $type, string $aid): int
    {
        $ach = self::topStrengAchieve($aid);
        if ($ach === null) {
            return 0;
        }
        // 旧版按 add 字段取 refineAddArr：只有成就 type{k} 与装备类型同字段时 refAdd 才非 0
        $refAdd = ((int)($ach['type' . $type] ?? 0) === $type) ? $refineAdd : 0;
        if ($refAdd <= 0) {
            return 0;
        }
        $addPer = (float)($ach['add_per' . $type] ?? 0);
        return (int)round(($powerup + $refAdd) * $addPer / 100);
    }

    /**
     * 战斗总血量：
     *   r = intval(all_blood * (1 + 体力 * 0.002) * (天赋血量系数 + 名将进阶血量))
     *   r = intval(r * (100 + ptblood) / 100)
     * $allBlood 为 _get_general_blood 加完精炼/强化成就/坐骑后的值；
     * $tili 为 tili + tiliup + 名将进阶 tili（battle_model 组队时已合并）；
     * $ptblood 为将魂[4] + 神树 buff + 套装 ptblood 之和（均未移植，由调用方传入）。
     * 来源：models/battle_model.php:_get_general_all_blood
     */
    public static function battleAllBlood(
        float $allBlood,
        int $tili,
        float $talentBlood,
        float $advancedBlood = 0.0,
        float $ptblood = 0.0,
    ): int {
        $return = (int)($allBlood * (1 + $tili * 0.002) * ($talentBlood + $advancedBlood));
        return (int)($return * (100 + $ptblood) / 100);
    }

    /**
     * 武将详情界面血量：
     *   hp = 成长血量 + 兵符 powerup（已含精炼/强化成就）+ 兵符宝石 + 主公天赋
     *   hp = (hp + 坐骑/套装等固定加成) * 将魂/套装百分比乘数
     *   hp = intval(hp * (1 + (tili + tiliup) * 0.002) * (天赋血量系数 + 名将进阶血量))
     * 旧版固定加成（坐骑、套装>100 的值）都发生在百分比乘数（套装<=100、将魂 type4）之前，
     * 所以合并成 $flatAdd / $percentMultiplier 两个参数不改变结果；
     * $tiliup 传入时应已含将魂/套装加到 tiliup 上的值。
     * 来源：controllers/general.php:get_info（index、_get_info 同公式）
     */
    public static function infoHp(
        float $baseHp,
        int $eid4Powerup,
        int $gemHp,
        int $heroTalentHp,
        int $tili,
        int $tiliup,
        float $talentBlood,
        float $advancedBlood = 0.0,
        float $flatAdd = 0.0,
        float $percentMultiplier = 1.0,
    ): int {
        $hp = $baseHp + $eid4Powerup + $gemHp + $heroTalentHp;
        $hp = ($hp + $flatAdd) * $percentMultiplier;
        return (int)($hp * (1 + ($tili + $tiliup) * 0.002) * ($talentBlood + $advancedBlood));
    }

    private static function typeCoef(int $type): float
    {
        $coef = self::EQUIP_TYPE_COEF[$type] ?? null;
        if ($coef === null) {
            throw new InvalidArgumentException("旧版 equip_type 无此类型: {$type}");
        }
        return (float)$coef;
    }

    private static function qualityCoef(int $quality): float
    {
        $coef = self::EQUIP_QUALITY_COEF[$quality] ?? null;
        if ($coef === null) {
            throw new InvalidArgumentException("旧版 equip_quality 无此品级: {$quality}");
        }
        return (float)$coef;
    }

    /**
     * refine_promote 按 level 查 percent（来源：models/refine_model.php:get_promote_info）
     */
    private static function refinePercent(int $hammer): ?int
    {
        $row = self::refinePromote($hammer);
        return $row === null ? null : (int)$row['percent'];
    }

    /**
     * aid 为逗号分隔的成就 id，旧版 rsort 后取第一个（数值最大）
     *
     * @return array<string, mixed>|null
     */
    public static function topStrengAchieve(string $aid): ?array
    {
        if ($aid === '') {
            return null;
        }
        $ids = array_map('intval', explode(',', $aid));
        rsort($ids);
        return EquipStrengachieve::ROWS[$ids[0]] ?? null;
    }
}
