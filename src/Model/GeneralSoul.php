<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Database\SoftDeletes;
use OmniPHP\Model;

/**
 * 将魂星盘命格模型
 */
class GeneralSoul extends Model
{
    use SoftDeletes;

    protected string $table = 'general_soul';
    protected string $primaryKey = 'id';

    protected array $casts = [
        'id' => 'int',
        'uid' => 'int',
        'gid' => 'int',
        'slot1' => 'int',
        'slot2' => 'int',
        'slot3' => 'int',
        'slot4' => 'int',
        'slot5' => 'int',
        'slot6' => 'int',
        'slot7' => 'int',
        'slot8' => 'int',
    ];

    /**
     * 计算将魂额外提供的攻防血量属性加成
     *
     * @return array{hp: int, attack: int, defense: int}
     */
    public function calculateBonus(): array
    {
        $addHp = ((int)($this->attributes['slot1'] ?? 0)) * 200;
        $addAtk = (((int)($this->attributes['slot2'] ?? 0)) * 40) + (((int)($this->attributes['slot5'] ?? 0)) * 35);
        $addDef = (((int)($this->attributes['slot3'] ?? 0)) * 25) + (((int)($this->attributes['slot6'] ?? 0)) * 20);

        return [
            'hp' => $addHp,
            'attack' => $addAtk,
            'defense' => $addDef,
        ];
    }
}
