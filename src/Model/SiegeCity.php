<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Database\SoftDeletes;
use OmniPHP\Model;

/**
 * 攻城掠地城池封邑模型
 */
class SiegeCity extends Model
{
    use SoftDeletes;

    protected string $table = 'siege';
    protected string $primaryKey = 'city_id';

    protected array $casts = [
        'id' => 'int',
        'city_id' => 'int',
        'occupier_uid' => 'int',
        'daily_silver' => 'int',
        'daily_gold' => 'int',
        'defense_level' => 'int',
    ];

    /**
     * 判断指定玩家是否为该城池城主
     */
    public function isOwner(int $uid): bool
    {
        return $uid > 0 && ((int)($this->attributes['occupier_uid'] ?? 0)) === $uid;
    }
}
