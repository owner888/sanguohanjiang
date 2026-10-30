<?php

declare(strict_types=1);

namespace Hanjiang\Battle;

/**
 * 战斗单位
 *
 * 旧版字段（type … matrixCoe）取自 TeamBuilder 输出的旧版队伍结构
 * （models/battle_model.php:init_member_team_info / init_monster_team_info + battle() 开头的 allblood）。
 *
 * currentHp / maxHp / defense / speed / anger / maxAnger 是当前新版 BattleEngine 的输入，
 * 由 fromUnit 的临时适配层从旧版字段换算；P5 移植 process_attack / process_hurt 后连同适配层一起删除。
 */
final class Hero
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly Position $pos,
        // —— 旧版字段 ——
        public readonly int $type,
        public readonly int $level,
        public readonly int $wuli,
        public readonly int $zhili,
        public readonly int $tili,
        public readonly int $talentid,
        public readonly int $skillid,
        public readonly int $attack,
        public readonly int $defense1,
        public readonly int $defense2,
        public readonly int $allblood,
        public readonly int $power,
        public readonly int $star,
        public readonly int $quality,
        public readonly string $image,
        public readonly int $matrixType,
        public readonly float $matrixCoe,
        // —— 当前 BattleEngine 用的字段（临时，P5 删除） ——
        public int $currentHp,
        public readonly int $maxHp,
        public readonly int $defense,
        public readonly int $speed,
        public int $anger = 0,
        public readonly int $maxAnger = 100,
    ) {
    }

    /**
     * 旧版队伍单位 → 战斗单位
     *
     * 临时适配层（P5 移植旧版 process_attack / process_hurt 后删除）：
     * - currentHp / maxHp ← allblood（玩家为 battle() 开头 _get_general_all_blood 的结果，怪物为 blood × 天赋血量系数）
     * - attack 原样使用
     * - defense ← intdiv(defense1 + defense2, 2)：旧版 process_hurt 按攻击方职业取防御
     *   （猛将打 defense1、谋士打 defense2），当前引擎的伤害公式不区分攻击方职业，取两者平均
     * - speed ← 10 - 站位：旧版没有速度属性，当前引擎按 speed 降序出手，这里让出手顺序只由站位决定（1 号位最先），保持确定性
     * 阵法 type / coe 只记录，当前引擎不使用（旧版在 process_hurt 中生效）。
     *
     * @param array<string, mixed> $unit TeamBuilder 输出的旧版单位
     */
    public static function fromUnit(int $pos, array $unit, int $matrixType, float $matrixCoe): self
    {
        $allblood = (int)$unit['allblood'];
        $defense1 = (int)$unit['defense1'];
        $defense2 = (int)$unit['defense2'];

        return new self(
            id: (int)$unit['id'],
            name: (string)$unit['name'],
            pos: Position::from($pos),
            type: (int)$unit['type'],
            level: (int)$unit['level'],
            wuli: (int)$unit['wuli'],
            zhili: (int)$unit['zhili'],
            tili: (int)($unit['tili'] ?? 0),
            talentid: (int)$unit['talentid'],
            skillid: (int)$unit['skillid'],
            attack: (int)$unit['attack'],
            defense1: $defense1,
            defense2: $defense2,
            allblood: $allblood,
            power: (int)$unit['power'],
            star: (int)$unit['star'],
            quality: (int)($unit['quality'] ?? 1),
            image: (string)$unit['image'],
            matrixType: $matrixType,
            matrixCoe: $matrixCoe,
            currentHp: $allblood,
            maxHp: $allblood,
            defense: intdiv($defense1 + $defense2, 2),
            speed: 10 - $pos,
        );
    }

    /**
     * 怪物来源尚未按旧版移植的玩法用的单位（数值是新版自定的，不是旧版数据）
     *
     * 依赖未移植：调用方的怪物 / 守军数值。旧版字段除 attack / allblood / defense1 / defense2 外按 0 记，
     * 移植该玩法的怪物表后改用 TeamBuilder::forMonsters。
     * speed 同 fromUnit 按站位换算（临时适配层），出手顺序与旧版单位一致只看站位。
     */
    public static function unported(int $id, string $name, Position $pos, int $hp, int $attack, int $defense): self
    {
        return new self(
            id: $id,
            name: $name,
            pos: $pos,
            type: 0,
            level: 0,
            wuli: 0,
            zhili: 0,
            tili: 0,
            talentid: 0,
            skillid: 0,
            attack: $attack,
            defense1: $defense,
            defense2: $defense,
            allblood: $hp,
            power: 0,
            star: 0,
            quality: 0,
            image: '',
            matrixType: 0,
            matrixCoe: 0.0,
            currentHp: $hp,
            maxHp: $hp,
            defense: $defense,
            speed: 10 - $pos->value,
        );
    }

    public function isAlive(): bool
    {
        return $this->currentHp > 0;
    }

    public function takeDamage(int $damage): int
    {
        $actual = min($this->currentHp, max(1, $damage));
        $this->currentHp -= $actual;
        // 受击增加怒气
        $this->anger = min($this->maxAnger, $this->anger + 25);
        return $actual;
    }
}
