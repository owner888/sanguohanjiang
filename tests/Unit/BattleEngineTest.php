<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Unit;

use Hanjiang\Battle\BattleEngine;
use Hanjiang\Battle\TeamBuilder;
use Hanjiang\MasterData\Mission;
use Hanjiang\Tests\Support\ScriptedRandomEngine;
use OmniPHP\Database\DB;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * BattleEngine 对照旧版 battle_model（battle / process / process_attack / process_effect / process_hurt / _battle_assess）
 *
 * 公式用例：单挑的第一次出手（左 2 号位打右 2 号位），期望值按旧版 process_hurt 逐项手算（见各用例的算式）。
 * 随机数用脚本引擎控制：每次出手依次消耗「暴击 mt_rand(1, 100)」「闪避 / 识破 mt_rand(1, 100)」（攻击方为猛将 / 谋士时），
 * 伤害 <= 1 时再消耗「rand(1, 10)」；偏移 99 即 100，不触发任何概率。
 *
 * 金标准：旧版库 dump 的 zc_sanguo_mission_report 54（uid 52763 打 1-3-1 地公力士一，tests/Fixtures/legacy_mission_report_54.json），
 * 队伍取 dump 的 own_general / own_equip / own_matrix（张梁当时 14 级，其余同 dump），随机数按战报里的结果回填。
 */
final class BattleEngineTest extends TestCase
{
    private const int UID = 52763;

    /** @var array<int, int> dump 将领 id => 新库将领 id */
    private array $gid = [];

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    /**
     * 只覆盖用例关心的字段，其余同 TeamBuilder::unportedUnit（技能 0：普通攻击、技能系数 1）
     *
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private static function unit(array $override): array
    {
        return array_replace(TeamBuilder::unportedUnit(1, '单位', 100000, 0, 0), $override);
    }

    /**
     * @param array<string, mixed> $attack
     * @param array<string, mixed> $defense
     * @param list<int> $offsets
     * @return array<string, mixed> 第一条出手记录
     */
    private static function firstAction(array $attack, array $defense, array $offsets, int $lt = 0, float $lc = 0.0, int $rt = 0, float $rc = 0.0): array
    {
        $engine = new BattleEngine($lt, $lc, $rt, $rc, rng: new Randomizer(new ScriptedRandomEngine($offsets)));
        return $engine->battle([2 => self::unit($attack)], [2 => self::unit($defense)])['process'][0];
    }

    public function testWarriorVsStrategistWithTalentsAndMatrix(): void
    {
        // 猛将（type 1）打谋士：比武力；物攻天赋 悍勇 pattack 1.04，物防天赋 坚强 pdefense 1.2，打 defense1
        // type_coe = 1 + (180 - 100) / 400 = 1.2
        // hurt = 1.2 × (400 × 1.04 - 100 × 1.2) × 1 = 1.2 × 296 = 355.2
        // 左阵 type 1（物攻）+355.2 × 0.03 = 10.656，右阵 type 2（物防）-355.2 × 0.05 = 17.76 → 348.096 → 348
        $atk = ['type' => 1, 'wuli' => 180, 'zhili' => 0, 'attack' => 400, 'talentid' => 2];
        $def = ['type' => 2, 'wuli' => 100, 'zhili' => 300, 'talentid' => 3, 'defense1' => 100, 'defense2' => 999];
        $log = self::firstAction($atk, $def, [99, 99], 1, 0.03, 2, 0.05);
        $this->assertSame([
            'round' => 1, 'launch' => 'left', 'attackor' => 2, 'attackpower' => 25, 'skill' => 0, 'hit' => 0, 'attacktype' => 0,
            'beattackors' => [2],
            'hurt' => [['pos' => 2, 'blood' => 348, 'power' => 25, 'self' => 0, 'faint' => 0]],
        ], $log);

        // 不带阵法：355.2 → 355
        $this->assertSame(355, self::firstAction($atk, $def, [99, 99])['hurt'][0]['blood']);
    }

    public function testStrategistUsesZhiliSattackSdefenseAndStrategyMatrix(): void
    {
        // 谋士（type 2）：比智力；策攻天赋 教祖 sattack 1.15，策防天赋 洞彻 sdefense 1.2，打 defense2
        // type_coe = 1 + (100 - 50) / 400 = 1.125；hurt = 1.125 × (500 × 1.15 - 200 × 1.2) = 1.125 × 335 = 376.875
        $atk = ['type' => 2, 'wuli' => 999, 'zhili' => 100, 'attack' => 500, 'talentid' => 5];
        $def = ['type' => 1, 'wuli' => 0, 'zhili' => 50, 'talentid' => 4, 'defense1' => 999, 'defense2' => 200];
        // 物攻 / 物防阵法（type 1 / 2）对策略攻击不生效 → 376
        $this->assertSame(376, self::firstAction($atk, $def, [99, 99], 1, 0.1, 2, 0.1)['hurt'][0]['blood']);
        // 策攻阵法 type 3 +37.6875、策防阵法 type 4 -18.84375 → 395.71875 → 395
        $this->assertSame(395, self::firstAction($atk, $def, [99, 99], 3, 0.1, 4, 0.05)['hurt'][0]['blood']);
        // type 7 两项都算：攻方 +10%、守方 -10% → 376.875 → 376
        $this->assertSame(376, self::firstAction($atk, $def, [99, 99], 7, 0.1, 7, 0.1)['hurt'][0]['blood']);
    }

    public function testTypeCoeClamp(): void
    {
        $def = ['type' => 1, 'talentid' => 1, 'defense1' => 100];
        // 武力差 -400：1 - 1 = 0 → 下限 0.5；0.5 × (300 - 100) = 100
        $this->assertSame(100, self::firstAction(['type' => 1, 'wuli' => 0, 'attack' => 300, 'talentid' => 1], $def + ['wuli' => 400], [99, 99])['hurt'][0]['blood']);
        // 武力差 +900：1 + 2.25 → 上限 1.5；1.5 × 200 = 300
        $this->assertSame(300, self::firstAction(['type' => 1, 'wuli' => 900, 'attack' => 300, 'talentid' => 1], $def + ['wuli' => 0], [99, 99])['hurt'][0]['blood']);
    }

    public function testMinimumDamageAndDefenderWithoutTalent(): void
    {
        // 攻击 50 < 防御 100：1 × (50 - 100) <= 1 → rand(1, 10)，脚本第 3 个数偏移 6 → 7
        $atk = ['type' => 1, 'wuli' => 0, 'attack' => 50, 'talentid' => 1];
        $this->assertSame(7, self::firstAction($atk, ['type' => 1, 'talentid' => 1, 'defense1' => 100], [99, 99, 6])['hurt'][0]['blood']);

        // 防御方没有天赋：旧版 process_hurt 不给防御赋值，防御按 0；1 × (300 - 0) = 300
        $atk['attack'] = 300;
        $this->assertSame(300, self::firstAction($atk, ['type' => 1, 'talentid' => 0, 'defense1' => 250], [99, 99])['hurt'][0]['blood']);
        // 同样的防御、天赋 平庸：300 - 250 × 1.0 = 50
        $this->assertSame(50, self::firstAction($atk, ['type' => 1, 'talentid' => 1, 'defense1' => 250], [99, 99])['hurt'][0]['blood']);
    }

    public function testCritAndKcrit(): void
    {
        // 能臣：pattack 1.0、暴击 35；hurt = 1 × (400 × 1.0 - 60 × 1.0) = 340
        $atk = ['type' => 1, 'wuli' => 100, 'attack' => 400, 'talentid' => 48];
        $def = ['type' => 1, 'wuli' => 100, 'talentid' => 1, 'defense1' => 60];

        // 暴击骰 1（偏移 0）<= 35：暴击 × 1.5 = 510，hit = 1
        $crit = self::firstAction($atk, $def, [0, 99]);
        $this->assertSame(1, $crit['hit']);
        $this->assertSame(510, $crit['hurt'][0]['blood']);

        // 暴击骰 36 > 35：不暴击
        $plain = self::firstAction($atk, $def, [35, 99]);
        $this->assertSame(0, $plain['hit']);
        $this->assertSame(340, $plain['hurt'][0]['blood']);

        // 防御方抗暴 35：暴击率 35 - 35 = 0 < 1，不暴击
        $this->assertSame(340, self::firstAction($atk, $def + ['kcrit' => 35], [0, 99])['hurt'][0]['blood']);

        // 旧版浮点按 intval 截断：军神 pattack 1.15，400 × 1.15 = 459.99999999999994，- 60 → 399.99…（399），暴击 × 1.5 → 599.99…（599）
        $atk['talentid'] = 6;
        $this->assertSame(399, self::firstAction($atk, $def, [35, 99])['hurt'][0]['blood']);
        $this->assertSame(599, self::firstAction($atk, $def, [0, 99])['hurt'][0]['blood']);
    }

    public function testDodgeHitAndPenetrate(): void
    {
        // 坚强：闪避 15；闪避骰 15（偏移 14）<= 15 → 闪避，伤害记 0，双方不涨士气
        $atk = ['type' => 1, 'wuli' => 0, 'attack' => 300, 'talentid' => 1];
        $def = ['type' => 1, 'wuli' => 0, 'talentid' => 3, 'defense1' => 0];
        $dodge = self::firstAction($atk, $def, [99, 14]);
        $this->assertSame(-1, $dodge['attacktype']);
        $this->assertSame(0, $dodge['attackpower']);
        $this->assertSame([['pos' => 2, 'blood' => 0, 'power' => 0, 'self' => 0, 'faint' => 0]], $dodge['hurt']);

        // 攻击方命中 10 抵消闪避：15 - 10 = 5 < 15 → 命中
        $this->assertSame(0, self::firstAction($atk + ['mingzhong' => 10], $def, [99, 14])['attacktype']);

        // 识破：谋士打洞彻（识破 15），识破骰 1 → 防御方按自身属性反击攻击方，伤害 × 0.25
        // 反击：洞彻为猛将，比武力 1 + (100 - 100) / 400 = 1；200 × 洞彻 pattack 1.0 - 攻击方 defense1 40 × 平庸 1.0 = 160 → × 0.25 = 40
        $mou = ['type' => 2, 'wuli' => 100, 'zhili' => 100, 'attack' => 400, 'talentid' => 1, 'defense1' => 40];
        $dong = ['type' => 1, 'wuli' => 100, 'zhili' => 100, 'attack' => 200, 'talentid' => 4];
        $pen = self::firstAction($mou, $dong, [99, 0]);
        $this->assertSame(-2, $pen['attacktype']);
        $this->assertSame([2], $pen['beattackors']);
        $this->assertSame([['pos' => 2, 'blood' => 40, 'power' => 0, 'self' => 0, 'faint' => 0]], $pen['hurt']);
    }

    public function testBossInspireCoefficientMultipliesDamage(): void
    {
        // battle_worldboss：刑天站 5 号位（world_boss 行 + 写死数值：武力 0、防御 0、天赋 / 技能 1）
        $boss = TeamBuilder::forBoss(\Hanjiang\MasterData\WorldBoss::ROWS[1] + [
            'blood' => 9000000000000, 'allblood' => 9000000000000, 'wuli' => 0, 'zhili' => 0, 'type' => 1,
            'attack' => 1000000000, 'pdefense' => 0, 'mdefense' => 0, 'talentid' => 1, 'skillid' => 1,
        ]);
        $this->assertSame([5], array_keys($boss));
        $this->assertSame([0, 0, 0, 0], [$boss[5]['power'], $boss[5]['round'], $boss[5]['defense1'], $boss[5]['defense2']]);

        // 平庸猛将攻击 300 打防御 0：1 × (300 × 1.0 × 鼓舞系数 - 0)；boss 战抗闪避 150，不触发闪避
        $atk = self::unit(['type' => 1, 'wuli' => 0, 'attack' => 300, 'talentid' => 1]);
        foreach ([[1, 1, 300], [1, 1.5, 450], [2, 2, 600]] as [$bossBattle, $coe, $expected]) {
            $engine = new BattleEngine(attackCoe: $coe, bossBattle: $bossBattle, rng: new Randomizer(new ScriptedRandomEngine([99, 99])));
            $out = $engine->battle([2 => $atk], $boss);
            $this->assertSame($expected, $out['process'][0]['hurt'][0]['blood']);
            $this->assertSame('刑天', $out['rightteam'][5]['name']);
        }
    }

    public function testEmptyTeamsShortCircuit(): void
    {
        $engine = new BattleEngine();
        $lose = $engine->battle([], [5 => self::unit([])]);
        $this->assertSame(-3, $lose['assess']);
        $this->assertSame([], $lose['process']);
        $this->assertSame(['mingzhong' => 0, 'jingzhun' => 0, 'kangyun' => 0, 'tree' => 0, 'kcrit' => 0, 'mount' => []], $lose['leftbuff']);

        $win = (new BattleEngine())->battle([5 => self::unit([])], []);
        $this->assertSame(3, $win['assess']);
    }

    public function testGoldenLegacyMissionReport54(): void
    {
        $this->insertDumpTeam([3 => 15, 5 => 15, 7 => 14]);
        $left = TeamBuilder::forMember(self::UID, pve: true);
        $right = TeamBuilder::forMonsters(Mission::ROWS[21]);

        // 随机数按旧版战报回填（其余一律偏移 99，不触发暴击 / 闪避）：
        //   第 2 次出手（右 4 号打 4 号周仓）最小伤害 3 → 第 5 个数偏移 2
        //   第 6 次出手（右 8 号打 2 号廖化）最小伤害 7 → 第 14 个数偏移 6
        //   第 12 次出手（右 8 号打 2 号廖化）最小伤害 6 → 第 28 个数偏移 5
        //   第 14 次出手（右 6 号技能打 6 号张梁）被闪避（坚强闪避 15）→ 第 33 个数偏移 0
        $offsets = array_fill(0, 33, 99);
        $offsets[4] = 2;
        $offsets[13] = 6;
        $offsets[27] = 5;
        $offsets[32] = 0;
        $engine = new BattleEngine($left['matrix_type'], $left['matrix_coe'], rng: new Randomizer(new ScriptedRandomEngine($offsets)));
        $out = $engine->battle($left['team'], $right['team']);

        $legacy = json_decode((string)file_get_contents(dirname(__DIR__) . '/Fixtures/legacy_mission_report_54.json'), true, 512, JSON_THROW_ON_ERROR);
        // 新库将领 id 与旧版不同：heroid 换回 dump 的将领 id 再比较
        $legacyId = array_flip($this->gid);
        foreach (['leftteam', 'leftresult'] as $key) {
            foreach ($out[$key] as $pos => $unit) {
                $out[$key][$pos]['heroid'] = (string)$legacyId[(int)$unit['heroid']];
            }
        }
        // 旧版库字段是字符串（如怪物 power "0"、技能 effect "1"），按 JSON 解码后宽松比较数值
        $this->assertEquals($legacy, json_decode((string)json_encode($out), true));
        $this->assertSame(array_keys($legacy), array_keys($out));
        $this->assertSame(3, $out['assess']);
        $this->assertCount(16, $out['process']);
    }

    public function testSeededFullBattleIsDeterministic(): void
    {
        $this->insertDumpTeam([3 => 15, 5 => 15, 7 => 15]);
        $left = TeamBuilder::forMember(self::UID, pve: true);
        $right = TeamBuilder::forMonsters(Mission::ROWS[21]);

        $run = static function () use ($left, $right): array {
            $engine = new BattleEngine($left['matrix_type'], $left['matrix_coe'], rng: new Randomizer(new Mt19937(self::UID)));
            return $engine->battle($left['team'], $right['team']);
        };
        $first = $run();
        $this->assertSame($first, $run(), '同一种子、同一队伍推演结果完全相同');

        // 结构与旧版战报一致（顶层键、出手记录键、受击记录键）
        $legacy = json_decode((string)file_get_contents(dirname(__DIR__) . '/Fixtures/legacy_mission_report_54.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(array_keys($legacy), array_keys($first));
        $this->assertSame(array_keys($legacy['process'][0]), array_keys($first['process'][0]));
        $this->assertSame(array_keys($legacy['process'][0]['hurt'][0]), array_keys($first['process'][0]['hurt'][0]));
        $this->assertSame(array_keys($legacy['leftresult'][2]), array_keys($first['leftresult'][2]));
        $this->assertSame(array_keys($legacy['leftbuff']), array_keys($first['leftbuff']));

        // 种子 52763 的结果（本引擎在该种子下的回归锁定值）
        $this->assertSame(3, $first['assess']);
        $this->assertCount(16, $first['process']);
        $this->assertSame([], $first['rightresult']);

        foreach ($first['process'] as $i => $p) {
            $this->assertContains($p['launch'], ['left', 'right']);
            $this->assertGreaterThanOrEqual(1, $p['round']);
            if ($i > 0) {
                $this->assertGreaterThanOrEqual($first['process'][$i - 1]['round'], $p['round'], '回合数不回退');
            }
        }
    }

    /**
     * 按旧版库 dump 重建 uid 52763 的 1 号阵法（锥形阵 3 级）：2 号位廖化、4 号位周仓、6 号位张梁
     *
     * own_general 3 廖化 type1 wuli81 zhili36 tili71 talent1 skill1；5 周仓 wuli88 zhili35 tili79 talent2 skill2；
     * 7 张梁 wuli80 zhili55 tili93 talent3 skill2（无装备）。own_equip 7 / 8 / 10 在廖化身上，12 / 13 / 15 在周仓身上。
     *
     * @param array<int, int> $levels dump 将领 id => 等级
     */
    private function insertDumpTeam(array $levels): void
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
                'level' => $levels[$legacyId],
                'exp' => 0,
                'levelupexp' => 18000,
                'type' => 1,
                'turn' => 0,
                'quality' => 1,
                'goout' => 1,
            ]);
        }
        // [equipid, 穿戴将领(dump id), 名称, type, quality, needlevel, level, power, powerup, saleprice]
        $equips = [
            [1, 3, '赤铜刀', 1, 1, 1, 0, 10, 10, 220],
            [2, 3, '赤铜甲', 3, 1, 1, 0, 5, 5, 100],
            [5, 3, '苍狼披风', 4, 2, 7, 0, 9, 9, 910],
            [3, 5, '劈山刀', 1, 2, 7, 10, 18, 178, 21822],
            [4, 5, '犀牛宝甲', 3, 2, 7, 10, 9, 89, 9919],
            [5, 5, '苍狼披风', 4, 2, 7, 0, 9, 9, 910],
        ];
        foreach ($equips as [$equipid, $owner, $name, $type, $quality, $needlevel, $level, $power, $powerup, $saleprice]) {
            DB::table('equipment')->insert([
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

    private function cleanup(): void
    {
        foreach (['general', 'equipment', 'matrix', 'member_field'] as $table) {
            DB::table($table)->where('uid', self::UID)->delete();
        }
    }
}
