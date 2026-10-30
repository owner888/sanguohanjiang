<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 东瀛怪物攻击加成表（旧版 zc_sanguo_japan_attack）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class JapanAttack
{
    public const string TABLE = 'zc_sanguo_japan_attack';

    /**
     * 按主键 round 索引，共 7 行。字段：
     *   - round (int) 回合数
     *   - ratio (double) 攻击系数
     */
    public const array ROWS = [
        1 => ['round' => 1, 'ratio' => 1.0],
        2 => ['round' => 2, 'ratio' => 1.0],
        3 => ['round' => 3, 'ratio' => 1.0],
        4 => ['round' => 4, 'ratio' => 1.0],
        5 => ['round' => 5, 'ratio' => 1.0],
        6 => ['round' => 6, 'ratio' => 1.0],
        7 => ['round' => 7, 'ratio' => 1.0],
    ];
}
