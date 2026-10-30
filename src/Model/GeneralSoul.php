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
}
