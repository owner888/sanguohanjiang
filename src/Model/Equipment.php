<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Database\SoftDeletes;
use OmniPHP\Model;

/**
 * 装备道具数据模型 (自动过滤软删除，内聚部位品质数值推算)
 */
class Equipment extends Model
{
    use SoftDeletes;

    protected string $table = 'equipment';
    protected string $primaryKey = 'id';

    protected array $casts = [
        'id' => 'int',
        'uid' => 'int',
        'equipid' => 'int',
        'type' => 'int',
        'quality' => 'int',
        'level' => 'int',
        'generalid' => 'int',
        'number' => 'int',
        'saleprice' => 'int',
    ];

    /**
     * 计算单件装备提供的属性加成
     *
     * @return array{attack: int, defense: int, hp: int}
     */
    public function calculateBonus(): array
    {
        $type = (int)($this->attributes['type'] ?? 1);
        $level = max(1, (int)($this->attributes['level'] ?? 1));
        $quality = max(1, (int)($this->attributes['quality'] ?? 1));
        $qualityMultiplier = 1.0 + ($quality - 1) * 0.2;

        return match ($type) {
            1 => [ // 武器: 攻击
                'attack' => (int)round((50 + ($level - 1) * 20) * $qualityMultiplier),
                'defense' => 0,
                'hp' => 0,
            ],
            2 => [ // 铠甲: 物防
                'attack' => 0,
                'defense' => (int)round((30 + ($level - 1) * 10) * $qualityMultiplier),
                'hp' => 0,
            ],
            3 => [ // 披风: 策防 (汇入 defense)
                'attack' => 0,
                'defense' => (int)round((20 + ($level - 1) * 8) * $qualityMultiplier),
                'hp' => 0,
            ],
            4 => [ // 兵符/战马: 生命
                'attack' => 0,
                'defense' => 0,
                'hp' => (int)round((300 + ($level - 1) * 100) * $qualityMultiplier),
            ],
            default => ['attack' => 0, 'defense' => 0, 'hp' => 0],
        };
    }

    /**
     * 获取装备对应的武将穿戴部位 (1武器, 2铠甲, 3披风, 4兵符)
     */
    public function getSite(): int
    {
        $type = (int)($this->attributes['type'] ?? 1);
        return match ($type) {
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            default => 1,
        };
    }

    /**
     * 是否已穿戴在某个武将身上
     */
    public function isEquipped(): bool
    {
        return ((int)($this->attributes['generalid'] ?? 0)) > 0;
    }
}
