<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Database\SoftDeletes;
use OmniPHP\Model;

/**
 * 武将英雄数据模型 (自动过滤软删除，内聚升级经验算法与全维属性推算)
 */
class General extends Model
{
    use SoftDeletes;

    protected string $table = 'general';
    protected string $primaryKey = 'id';

    protected array $casts = [
        'id' => 'int',
        'uid' => 'int',
        'generalid' => 'int',
        'level' => 'int',
        'exp' => 'int',
        'pos' => 'int',
        'matrix_id' => 'int',
        'status' => 'int',
        'wuliup' => 'int',
        'zhiliup' => 'int',
        'tiliup' => 'int',
        'quality' => 'int',
    ];

    /**
     * 原版经典武将升级经验公式 (80 * 等级^2)
     */
    public static function getLevelUpExp(int $level): int
    {
        return max(80, 80 * $level * $level);
    }

    /**
     * 计算武将的综合战斗属性 (基础成长 + 穿戴装备 + 将魂命格 + 洗髓培养)
     *
     * @param list<array<string, mixed>> $equippedItems
     * @return array<string, mixed>
     */
    public function calculateStats(array $equippedItems = []): array
    {
        $level = max(1, (int)($this->attributes['level'] ?? 1));
        $baseHp = 1500 + ($level - 1) * 120;
        $baseAtk = 320 + ($level - 1) * 25;
        $baseDef = 120 + ($level - 1) * 12;
        $baseSpeed = 150 + ($level - 1) * 5;

        $addAtk = 0;
        $addDef = 0;
        $addHp = 0;

        // 1. 穿戴装备加成
        foreach ($equippedItems as $item) {
            $eqModel = Equipment::fromArray($item);
            $bonus = $eqModel->calculateBonus();
            $addAtk += $bonus['attack'];
            $addDef += $bonus['defense'];
            $addHp += $bonus['hp'];
        }

        // 2. 将魂命格加成
        $uid = (int)($this->attributes['uid'] ?? 0);
        $gid = (int)($this->attributes['id'] ?? 0);
        if ($uid > 0 && $gid > 0) {
            $soul = GeneralSoul::query()->where('uid', $uid)->where('gid', $gid)->first();
            if ($soul !== null) {
                $soulModel = GeneralSoul::fromArray($soul);
                $soulBonus = $soulModel->calculateBonus();
                $addHp += $soulBonus['hp'];
                $addAtk += $soulBonus['attack'];
                $addDef += $soulBonus['defense'];
            }
        }

        // 3. 武将洗髓培养永久加成 (体力加生命，武力加攻击，智力加防御)
        $wuliUp = (int)($this->attributes['wuliup'] ?? 0);
        $zhiliUp = (int)($this->attributes['zhiliup'] ?? 0);
        $tiliUp = (int)($this->attributes['tiliup'] ?? 0);
        $addAtk += $wuliUp * 2;
        $addDef += $zhiliUp * 2;
        $addHp += $tiliUp * 10;

        $totalHp = $baseHp + $addHp;
        $totalAtk = $baseAtk + $addAtk;
        $totalDef = $baseDef + $addDef;

        return [
            'level' => $level,
            'hp' => $totalHp,
            'allblood' => $totalHp,
            'attack' => $totalAtk,
            'defense' => $totalDef,
            'speed' => $baseSpeed,
            'base_hp' => $baseHp,
            'base_attack' => $baseAtk,
            'base_defense' => $baseDef,
            'add_hp' => $addHp,
            'add_attack' => $addAtk,
            'add_defense' => $addDef,
        ];
    }
}
