<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 整军演武数据表（旧版 zc_sanguo_exercise）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Exercise
{
    public const string TABLE = 'zc_sanguo_exercise';

    /**
     * 按主键 id 索引，共 2 行。字段：
     *   - id (int)
     *   - type (int) 礼包类型 1 银币; 2 军令 
     *   - num (int) 参数
     *   - multiply1 (int) 普通
     *   - multiply2 (int) 2倍
     *   - multiply4 (int) 4倍
     *   - multiply8 (int) 8倍
     *   - rate (tinyint) 出现概率
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => 1, 'num' => 100, 'multiply1' => 95, 'multiply2' => 5, 'multiply4' => 1, 'multiply8' => 1, 'rate' => 50],
        2 => ['id' => 2, 'type' => 2, 'num' => 1, 'multiply1' => 95, 'multiply2' => 5, 'multiply4' => 1, 'multiply8' => 1, 'rate' => 50],
    ];
}
