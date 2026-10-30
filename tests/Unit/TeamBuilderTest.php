<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Unit;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\MasterData\Mission;
use Hanjiang\MasterData\Monster;
use OmniPHP\Database\DB;
use PHPUnit\Framework\TestCase;

/**
 * TeamBuilder 对照旧版 battle_model 组队：玩家队伍用旧版库 dump 中 uid 52763 的 1 号阵法（锥形阵 3 级）重建，
 * 期望值按旧版公式逐项手算（见各断言旁的算式），不是由被测代码生成。
 *
 * dump 行：
 *   own_matrix  (52763, mid 1, position2 = 3, position4 = 5, position6 = 7, level 3)
 *   own_general 3 廖化 Lv15 turn0 type1 wuli81 zhili36 tili71 talent1 skill1 star1 eid 7/8/10/0
 *               5 周仓 Lv15 turn0 type1 wuli88 zhili35 tili79 talent2 skill2 star2 eid 12/13/15/0
 *               7 张梁 Lv15 turn0 type1 wuli80 zhili55 tili93 talent3 skill2 star3 无装备
 *   own_equip   7 赤铜刀 type1 power10 powerup10；8 赤铜甲 type3 5/5；10 苍狼披风 type4 9/9
 *               12 劈山刀 type1 Lv10 18/178；13 犀牛宝甲 type3 Lv10 9/89；15 苍狼披风 type4 9/9（均无精炼、无强化成就）
 * 主公 17 级，所有依赖未移植的加成在旧版也尚未开启（名将成就 81、美人 60、星魄 80、主公天赋 91、名将进阶 100）。
 */
final class TeamBuilderTest extends TestCase
{
    private const int UID = 52763;

    /** @var array<int, int> dump 将领 id => 新库将领 id */
    private array $gid = [];

    /** @var array<int, int> dump 装备 id => 新库装备 id */
    private array $eid = [];

    public static function setUpBeforeClass(): void
    {
        $dbConfig = require dirname(__DIR__, 2) . '/config/database.php';
        DB::init($dbConfig['mysql']);
    }

    protected function setUp(): void
    {
        $this->cleanup();

        $generals = [
            3 => ['generalid' => 1, 'name' => '廖化', 'image' => 1, 'wuli' => 81, 'zhili' => 36, 'tili' => 71, 'talentid' => 1, 'skillid' => 1, 'star' => 1],
            5 => ['generalid' => 2, 'name' => '周仓', 'image' => 4, 'wuli' => 88, 'zhili' => 35, 'tili' => 79, 'talentid' => 2, 'skillid' => 2, 'star' => 2],
            7 => ['generalid' => 3, 'name' => '张梁', 'image' => 27, 'wuli' => 80, 'zhili' => 55, 'tili' => 93, 'talentid' => 3, 'skillid' => 2, 'star' => 3],
        ];
        foreach ($generals as $legacyId => $g) {
            $this->gid[$legacyId] = (int)DB::table('general')->insert($g + [
                'uid' => self::UID,
                'level' => 15,
                'exp' => 0,
                'levelupexp' => 18000,
                'type' => 1,
                'turn' => 0,
                'quality' => 1,
                'goout' => 1,
            ]);
        }

        // [dump 装备 id => [equipid, 穿戴将领(dump id), 名称, type, quality, needlevel, level, power, powerup, saleprice]]
        $equips = [
            7 => [1, 3, '赤铜刀', 1, 1, 1, 0, 10, 10, 220],
            8 => [2, 3, '赤铜甲', 3, 1, 1, 0, 5, 5, 100],
            10 => [5, 3, '苍狼披风', 4, 2, 7, 0, 9, 9, 910],
            12 => [3, 5, '劈山刀', 1, 2, 7, 10, 18, 178, 21822],
            13 => [4, 5, '犀牛宝甲', 3, 2, 7, 10, 9, 89, 9919],
            15 => [5, 5, '苍狼披风', 4, 2, 7, 0, 9, 9, 910],
        ];
        foreach ($equips as $legacyId => [$equipid, $owner, $name, $type, $quality, $needlevel, $level, $power, $powerup, $saleprice]) {
            $this->eid[$legacyId] = (int)DB::table('equipment')->insert([
                'uid' => self::UID,
                'equipid' => $equipid,
                'name' => $name,
                'type' => $type,
                'quality' => $quality,
                'needlevel' => $needlevel,
                'level' => $level,
                'power' => $power,
                'powerup' => $powerup,
                'saleprice' => $saleprice,
                'generalid' => $this->gid[$owner],
            ]);
        }

        // 旧版 member_field.matrixid = 1：战斗读启用的 1 号阵法
        DB::table('member_field')->insert(['uid' => self::UID, 'matrixid' => 1]);
        DB::table('matrix')->insert([
            'uid' => self::UID,
            'mid' => 1,
            'level' => 3,
            'position1' => 0,
            'position2' => $this->gid[3],
            'position3' => 0,
            'position4' => $this->gid[5],
            'position5' => 0,
            'position6' => $this->gid[7],
            'position7' => 0,
            'position8' => 0,
            'position9' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    public function testMemberTeamMatchesLegacyFormulas(): void
    {
        $built = TeamBuilder::forMember(self::UID, pve: true);
        $this->assertSame(1, $built['status']);
        $this->assertSame([2, 4, 6], array_keys($built['team']));

        // 阵法：Matrix 1 锥形阵 addtype 1、addition 1；percent = 3 级 × 1 = 3，coe = 3 × 0.01
        $this->assertSame(1, $built['matrix_type']);
        $this->assertEqualsWithDelta(0.03, $built['matrix_coe'], 1e-9);

        // 15 级、0 转（转生系数 1）的成长值：
        //   攻击 40 + 14 × 10 = 180；物防 / 策防 20 + 14 × 5 = 90；血量 80 + 50 × 14 = 780
        $liao = $built['team'][2];
        $this->assertSame($this->gid[3], (int)$liao['id']);
        $this->assertSame('廖化', $liao['name']);
        $this->assertSame(190, $liao['attack']);      // 180 + 赤铜刀 10
        $this->assertSame(95, $liao['defense1']);     // 90 + 赤铜甲 5
        $this->assertSame(99, $liao['defense2']);     // 90 + 苍狼披风 9
        $this->assertSame(780, $liao['all_blood']);   // 780 + 无兵符 0
        $this->assertSame(81, $liao['wuli']);
        $this->assertSame(36, $liao['zhili']);
        $this->assertSame(71, $liao['tili']);
        $this->assertSame(0, $liao['power']);
        $this->assertSame(0, $liao['star']);          // star 1 < 6

        $zhou = $built['team'][4];
        $this->assertSame('周仓', $zhou['name']);
        $this->assertSame(358, $zhou['attack']);      // 180 + 劈山刀 178
        $this->assertSame(179, $zhou['defense1']);    // 90 + 犀牛宝甲 89
        $this->assertSame(99, $zhou['defense2']);     // 90 + 苍狼披风 9
        $this->assertSame(780, $zhou['all_blood']);
        $this->assertSame(0, $zhou['star']);

        $zhang = $built['team'][6];
        $this->assertSame('张梁', $zhang['name']);
        $this->assertSame(180, $zhang['attack']);
        $this->assertSame(90, $zhang['defense1']);
        $this->assertSame(90, $zhang['defense2']);
        $this->assertSame(780, $zhang['all_blood']);
        $this->assertArrayNotHasKey('allblood', $zhang, '总血量在 battle() 开头计算');

        // battle() 开头的 _get_general_all_blood（对空队伍推演只做初始化）+ team_return_arr
        $ret = (new BattleEngine())->battle($built['team'], [])['leftteam'];
        // intval(780 × (1 + 71 × 0.002) × 平庸 1.0) = intval(890.76) = 890；ptblood 0 → 890
        $this->assertSame(890, $ret[2]['allblood']);
        // intval(780 × (1 + 93 × 0.002) × 坚强 1.0) = intval(925.08) = 925
        $this->assertSame(925, $ret[6]['allblood']);
        // intval(780 × (1 + 79 × 0.002) × 悍勇 1.1) = intval(903.24 × 1.1) = intval(993.564) = 993
        $this->assertSame([
            'heroid' => (string)$this->gid[5],
            'name' => '周仓',
            'herotype' => '1',
            'level' => '15',
            'allblood' => 993,
            'power' => 0,
            'image' => '4',
            'star' => 0,
            'talent' => '悍勇',
            'skill' => '愤怒一击',
            'quality' => '1',
        ], $ret[4]);
        $this->assertArrayNotHasKey('blood', $ret[4]);
    }

    public function testRefineStrengthenAchievementAndRefineAchievement(): void
    {
        // 在 dump 基础上给周仓的装备加精炼 / 强化成就，并补一件青玉兵符，凑满 4 件带精炼的装备
        DB::table('equipment')->where('id', $this->eid[12])->update(['hammer' => 2, 'aid' => '1,2']);
        DB::table('equipment')->where('id', $this->eid[13])->update(['hammer' => 1]);
        DB::table('equipment')->where('id', $this->eid[15])->update(['hammer' => 1]);
        DB::table('equipment')->insert([
            'uid' => self::UID, 'equipid' => 7, 'name' => '青玉兵符', 'type' => 5, 'quality' => 3, 'needlevel' => 1,
            'level' => 0, 'power' => 100, 'powerup' => 100, 'saleprice' => 0, 'hammer' => 1, 'generalid' => $this->gid[5],
        ]);

        $built = TeamBuilder::forMember(self::UID);
        $zhou = $built['team'][4];
        // 劈山刀 精炼 2 级（refine_promote percent 25）：round((178 - 18) × 25 / 100) = 40
        //        强化成就取 aid 最大的 2（type1 = 1 与装备类型相同，add_per1 = 10）：round((178 + 40) × 10 / 100) = round(21.8) = 22
        // 精炼成就：4 件装备有精炼，去重后最低精炼等级 1 ≥ refine_achieve 1 级（level 1）：武力 +10、智力 +10、物攻 +500、策攻 +500
        // 攻击（猛将取 attack1）= 180 + 178 + 40 + 22 + 500 = 920
        $this->assertSame(920, $zhou['attack']);
        // 犀牛宝甲 精炼 1 级（percent 20）：round((89 - 9) × 20 / 100) = 16 → 90 + 89 + 16 = 195
        $this->assertSame(195, $zhou['defense1']);
        // 苍狼披风 精炼 1 级：round((9 - 9) × 0.2) = 0 → 99
        $this->assertSame(99, $zhou['defense2']);
        // 兵符：780 + 100，精炼 round((100 - 100) × 0.2) = 0 → 880
        $this->assertSame(880, $zhou['all_blood']);
        $this->assertSame(98, $zhou['wuli']);   // 88 + 10
        $this->assertSame(45, $zhou['zhili']);  // 35 + 10
        // intval(880 × (1 + 79 × 0.002) × 1.1) = intval(1019.04 × 1.1) = intval(1120.944) = 1120
        $this->assertSame(1120, (new BattleEngine())->battle($built['team'], [])['leftteam'][4]['allblood']);
    }

    public function testStarAndPveMatrixCap(): void
    {
        DB::table('general')->where('id', $this->gid[7])->update(['star' => 6]);
        DB::table('matrix')->where('uid', self::UID)->update(['level' => 80]);

        $self = TeamBuilder::forMember(self::UID, pve: true);
        $this->assertSame(1, $self['team'][6]['star'], '6 星武将对本人有战斗特效');
        // 80 级 × 1 × 0.01 = 0.8，PVE 上限 0.5
        $this->assertEqualsWithDelta(0.5, $self['matrix_coe'], 1e-9);

        $defender = TeamBuilder::forMember(self::UID, defense: true, self: false);
        $this->assertSame(0, $defender['team'][6]['star'], '作为他人的对手时没有 6 星特效');
        $this->assertEqualsWithDelta(0.8, $defender['matrix_coe'], 1e-9, 'PVP 不封顶');
    }

    public function testEmptyAndMissingMatrixStatus(): void
    {
        $positions = [];
        for ($i = 1; $i <= 9; $i++) {
            $positions['position' . $i] = 0;
        }
        DB::table('matrix')->where('uid', self::UID)->update($positions);
        $empty = TeamBuilder::forMember(self::UID);
        $this->assertSame(-2, $empty['status']);
        $this->assertSame([], $empty['team']);

        // 启用的阵法行查不到：旧版 empty(get_own_by_id) 不成立，按空站位、等级 0 继续 → -2，类型仍取模板
        DB::table('matrix')->where('uid', self::UID)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $missing = TeamBuilder::forMember(self::UID);
        $this->assertSame(-2, $missing['status']);
        $this->assertSame(1, $missing['matrix_type']);
        $this->assertEqualsWithDelta(0.0, $missing['matrix_coe'], 1e-9);

        // member_field.matrixid = 0：没有阵法信息 -1
        DB::table('member_field')->where('uid', self::UID)->update(['matrixid' => 0]);
        $this->assertSame(-1, TeamBuilder::forMember(self::UID)['status']);
    }

    public function testMonsterTeamFromCampaignMission(): void
    {
        // Mission 21（1-3-1）：position4 = 41 地公裁决者、position6 = 43 地公狂暴者、position8 = 42 地公守护者
        $mission = Mission::ROWS[21];
        $this->assertSame([1, 3, 1], [$mission['missionlevel'], $mission['missionstage'], $mission['missionid']]);

        $team = TeamBuilder::forMonsters($mission, TeamBuilder::MONSTER_MISSION)['team'];
        $this->assertSame([4, 6, 8], array_keys($team));

        $m43 = Monster::ROWS[43];
        $this->assertSame('地公狂暴者', $team[6]['name']);
        $this->assertSame($m43['attack'], $team[6]['attack']);
        $this->assertSame($m43['pdefense'], $team[6]['defense1']);
        $this->assertSame($m43['mdefense'], $team[6]['defense2']);
        // battle_mission：intval(blood 400 × 悍勇 1.1) = 440
        $this->assertSame(440, $team[6]['allblood']);
        $this->assertSame(440, $team[6]['blood']);
        // 天赋 1 / 3 血量系数 1.0：400
        $this->assertSame(400, $team[4]['allblood']);
        $this->assertSame(400, $team[8]['allblood']);
        $this->assertSame(0, $team[4]['star']);

        $ret = TeamBuilder::teamReturnArr($team, true);
        $this->assertSame('43', $ret[6]['heroid']);
        $this->assertSame((string)$m43['image'], $ret[6]['image']);
        $this->assertSame('1', $ret[6]['herotype']);
        $this->assertSame(440, $ret[6]['blood']);
    }

    public function testEveryCampaignMissionResolvesItsMonsters(): void
    {
        foreach (Mission::ROWS as $row) {
            $team = TeamBuilder::forMonsters($row)['team'];
            foreach ($team as $unit) {
                $this->assertGreaterThan(0, $unit['allblood'], "Mission {$row['id']} 怪物 {$unit['id']}");
            }
        }
    }

    private function cleanup(): void
    {
        foreach (['general', 'equipment', 'matrix', 'member_field'] as $table) {
            DB::table($table)->where('uid', self::UID)->delete();
        }
    }
}
