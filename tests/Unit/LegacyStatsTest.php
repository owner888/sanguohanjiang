<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Unit;

use Hanjiang\Battle\LegacyStats;
use Hanjiang\MasterData\Equip;
use Hanjiang\MasterData\General;
use Hanjiang\MasterData\Talent;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * 旧版数值公式回归：金标准取自旧版库 dump 中 uid 52763 的真实行
 * （zc_sanguo_own_general / zc_sanguo_own_equip），不是手算的期望值。
 */
final class LegacyStatsTest extends TestCase
{
    /**
     * zc_sanguo_own_equip（uid 52763）：id => [equipid, type, quality, level, power, powerup, saleprice]
     */
    private const array OWN_EQUIP = [
        7 => [1, 1, 1, 0, 10, 10, 220],
        8 => [2, 3, 1, 0, 5, 5, 100],
        10 => [5, 4, 2, 0, 9, 9, 910],
        12 => [3, 1, 2, 10, 18, 178, 21822],
        13 => [4, 3, 2, 10, 9, 89, 9919],
        15 => [5, 4, 2, 0, 9, 9, 910],
    ];

    /**
     * zc_sanguo_own_general（uid 52763）：id => [generalid, level, levelupexp, tili, tiliup, turn, eid1, eid2, eid3, eid4]
     */
    private const array OWN_GENERAL = [
        3 => [1, 15, 18000, 71, 0, 0, 7, 8, 10, 0],   // 廖化
        5 => [2, 15, 18000, 79, 0, 0, 12, 13, 15, 0], // 周仓
    ];

    public function testTurnCoefficientAndLevelCap(): void
    {
        $this->assertSame(1.0, LegacyStats::turnCoefficient(0));
        $this->assertSame(1.15, LegacyStats::turnCoefficient(1));
        $this->assertSame(50.6, LegacyStats::turnCoefficient(70));
        $this->assertSame(1.0, LegacyStats::turnCoefficient(71));
        $this->assertSame(50, LegacyStats::levelCap(0));
        $this->assertSame(65, LegacyStats::levelCap(3));
        $this->assertSame(400, LegacyStats::levelCap(70));
    }

    public function testLevelUpExpMatchesDumpRows(): void
    {
        $this->assertSame(0, LegacyStats::levelUpExp(0));
        $this->assertSame(80, LegacyStats::levelUpExp(1));
        // own_general 3/5/7/8：15 级 18000；4：13 级 13520；6/10：12 级 11520；9：14 级 15680
        $this->assertSame(18000, LegacyStats::levelUpExp(15));
        $this->assertSame(13520, LegacyStats::levelUpExp(13));
        $this->assertSame(11520, LegacyStats::levelUpExp(12));
        $this->assertSame(15680, LegacyStats::levelUpExp(14));
        foreach (self::OWN_GENERAL as [, $level, $levelupexp]) {
            $this->assertSame($levelupexp, LegacyStats::levelUpExp($level));
        }
    }

    public function testBaseStatsKeepFloat(): void
    {
        $this->assertSame(780.0, LegacyStats::baseHp(15, 0));
        $this->assertSame(180.0, LegacyStats::baseAttack(15, 0));
        $this->assertSame(90.0, LegacyStats::baseDefense(15, 0));
        $this->assertSame(90.0, LegacyStats::basePdefense(15, 0));
        // 转生系数带小数时旧版不取整
        $this->assertSame(137.5, LegacyStats::baseHp(2, 1));
        $this->assertSame(80.0, LegacyStats::baseHp(1, 5));
    }

    public function testItemSite(): void
    {
        $this->assertSame(1, LegacyStats::itemSite(1));
        $this->assertSame(1, LegacyStats::itemSite(2));
        $this->assertSame(2, LegacyStats::itemSite(3));
        $this->assertSame(3, LegacyStats::itemSite(4));
        $this->assertSame(4, LegacyStats::itemSite(5));
        $this->assertNull(LegacyStats::itemSite(6));
    }

    public function testEquipPowerupMatchesDumpRows(): void
    {
        foreach (self::OWN_EQUIP as $id => [$equipid, $type, $quality, $level, $power, $powerup]) {
            $tpl = Equip::ROWS[$equipid];
            $this->assertSame($tpl['type'], $type, "own_equip {$id}");
            $this->assertSame($tpl['power'], $power, "own_equip {$id}");
            $this->assertSame($powerup, LegacyStats::powerup($power, $level, $type, $quality), "own_equip {$id}");
        }
        $this->assertSame(16, LegacyStats::equipPowerStep(1, 2));
        $this->assertSame(138, LegacyStats::equipPowerStep(5, 7));
        $this->expectException(InvalidArgumentException::class);
        LegacyStats::equipPowerStep(6, 1);
    }

    public function testSalePriceMatchesDumpRows(): void
    {
        foreach (self::OWN_EQUIP as $id => [$equipid, $type, $quality, $level, , , $saleprice]) {
            $base = Equip::ROWS[$equipid]['saleprice'];
            $this->assertSame($saleprice, LegacyStats::salePrice($type, $quality, $base, $level), "own_equip {$id}");
        }
        // 劈山刀强化 10 级累计花费 Σround(2.86*30*i²) = 33033；犀牛宝甲 39*385 = 15015
        $this->assertSame(33033, LegacyStats::strengthenTotalCost(1, 2, 10));
        $this->assertSame(15015, LegacyStats::strengthenTotalCost(3, 2, 10));
    }

    public function testStrengthenCost(): void
    {
        // 0 → 1 级：equip_level[1].silver = 30
        $this->assertSame(30, LegacyStats::strengthenCost(3, 1, 0));
        $this->assertSame(66, LegacyStats::strengthenCost(1, 1, 0));
        // 2.2*1.3*3000 在浮点下是 8580.000000000002，旧版 ceil 后为 8581
        $this->assertSame(8581, LegacyStats::strengthenCost(1, 2, 9));
        $this->assertNull(LegacyStats::strengthenCost(1, 1, 400));
    }

    public function testStrengthenRule(): void
    {
        // 0 → 1 级：silver 同 strengthenCost，rate / gold / baoji 取 equip_level[1]，power = 初始值 + 1 * 成长值
        $this->assertSame(['silver' => 66, 'gold' => 0, 'rate' => 100, 'power' => 22, 'baoji' => 15], LegacyStats::strengthenRule(1, 1, 10, 0));
        // own_equip 12（劈山刀 type 1 q2 初始 18）从 9 级强化到 10 级后 powerup = 178，与 dump 一致
        $this->assertSame(178, LegacyStats::strengthenRule(1, 2, 18, 9)['power']);
        $this->assertSame(8581, LegacyStats::strengthenRule(1, 2, 18, 9)['silver']);
        // 表尾之后全为 0、没有 baoji 键
        $this->assertSame(['silver' => 0, 'gold' => 0, 'rate' => 0, 'power' => 0], LegacyStats::strengthenRule(1, 1, 10, 400));
        // 卷轴（type 6）不在旧版系数表：按 0 参与运算
        $rule = LegacyStats::strengthenRule(6, 1, 0, 0);
        $this->assertSame(0, $rule['silver']);
        $this->assertSame(0, $rule['power']);
    }

    public function testChipNumAndDisplayPowerup(): void
    {
        $this->assertSame(2, LegacyStats::chipNum(1));
        $this->assertSame(2, LegacyStats::chipNum(3));
        $this->assertSame(5, LegacyStats::chipNum(4));
        $this->assertSame(10, LegacyStats::chipNum(5));
        $this->assertSame(20, LegacyStats::chipNum(6));
        $this->assertSame(30, LegacyStats::chipNum(7));

        $this->assertNull(LegacyStats::refinePromote(0));
        $this->assertSame(20, LegacyStats::refinePromote(1)['percent']);

        // 劈山刀 178 + 精炼 1 级 32 = 210，+ 强化成就 1 round(210 * 5%) = 11
        $this->assertSame(['powerup' => 221, 'hammer_quality' => 1], LegacyStats::displayPowerup(18, 178, 1, 1, '1'));
        $this->assertSame(['powerup' => 178, 'hammer_quality' => 0], LegacyStats::displayPowerup(18, 178, 1, 0, ''));
        $this->assertSame(['powerup' => 187, 'hammer_quality' => 0], LegacyStats::displayPowerup(18, 178, 1, 0, '1'));
    }

    public function testRefineAndStrengthAchievement(): void
    {
        // refine_promote level 1 → 20%
        $this->assertSame(0, LegacyStats::refineAdd(18, 178, 0));
        $this->assertSame(32, LegacyStats::refineAdd(18, 178, 1));

        // 界面口径：不要求精炼；成就 1（强化至40级）add_per1 = 5
        $this->assertSame(11, LegacyStats::strengthAchAddItemRefine(178 + 32, 1, '1'));
        $this->assertSame(9, LegacyStats::strengthAchAddItemRefine(178, 1, '1'));
        // 取 id 最大的成就：3（强化至80级）add_per1 = 15
        $this->assertSame(32, LegacyStats::strengthAchAddItemRefine(210, 1, '1,3'));
        $this->assertSame(0, LegacyStats::strengthAchAddItemRefine(210, 1, ''));

        // 战斗口径：只有有精炼加成时才计
        $this->assertSame(11, LegacyStats::strengthAchAddBattle(178, 32, 1, '1'));
        $this->assertSame(0, LegacyStats::strengthAchAddBattle(178, 0, 1, '1'));
        $this->assertSame(0, LegacyStats::strengthAchAddBattle(178, 32, 1, ''));
    }

    public function testDumpGeneralAttributes(): void
    {
        foreach (self::OWN_GENERAL as $gid => [$generalid, $level, , $tili, $tiliup, $turn, $eid1, $eid2, $eid3, $eid4]) {
            $tpl = General::ROWS[$generalid];
            $this->assertSame($tpl['tili'], $tili, "own_general {$gid}");
            $powerupOf = static fn (int $eid): int => $eid > 0 ? self::OWN_EQUIP[$eid][5] : 0;

            // battle_model: intval(成长值 + 对应位置装备 powerup)
            $attack = (int)(LegacyStats::baseAttack($level, $turn) + $powerupOf($eid1));
            $defense1 = (int)(LegacyStats::baseDefense($level, $turn) + $powerupOf($eid2));
            $defense2 = (int)(LegacyStats::basePdefense($level, $turn) + $powerupOf($eid3));
            $allBlood = (int)(LegacyStats::baseHp($level, $turn) + $powerupOf($eid4));
            $talentBlood = (float)Talent::ROWS[$tpl['talentid']]['blood'];

            if ($gid === 3) {
                $this->assertSame(190, $attack);
                $this->assertSame(95, $defense1);
                $this->assertSame(99, $defense2);
                $this->assertSame(780, $allBlood);
                // intval(780 * (1 + 71*0.002) * 1) = 890
                $this->assertSame(890, LegacyStats::battleAllBlood($allBlood, $tili + $tiliup, $talentBlood));
                $this->assertSame(890, LegacyStats::infoHp(LegacyStats::baseHp($level, $turn), 0, 0, 0, $tili, $tiliup, $talentBlood));
            } else {
                $this->assertSame(358, $attack);
                $this->assertSame(179, $defense1);
                $this->assertSame(99, $defense2);
                // 周仓天赋 2 血量系数 1.1：intval(780 * (1 + 79*0.002) * 1.1) = 993
                $this->assertSame(993, LegacyStats::battleAllBlood($allBlood, $tili + $tiliup, $talentBlood));
            }
        }
    }

    public function testAllBloodBonusOrder(): void
    {
        // ptblood 在第一次取整之后再按百分比乘
        $this->assertSame(979, LegacyStats::battleAllBlood(780, 71, 1.0, 0.0, 10));
        // 名将进阶血量与天赋系数相加
        $this->assertSame(1336, LegacyStats::battleAllBlood(780, 71, 1.0, 0.5));
        // 界面血量：固定加成先于百分比
        $this->assertSame(1231, LegacyStats::infoHp(780.0, 100, 0, 0, 71, 0, 1.0, flatAdd: 100.0, percentMultiplier: 1.1));
    }
}
