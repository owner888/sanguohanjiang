<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 激活码表（旧版 zc_sanguo_activationcode）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Activationcode
{
    public const string TABLE = 'zc_sanguo_activationcode';

    /**
     * 按主键 id 索引，共 5 行。字段：
     *   - id (int) 流水号
     *   - code (int) 激活码
     *   - type (int) 类型
     *   - dateline (int) 开始时间
     */
    public const array ROWS = [
        1 => ['id' => 1, 'code' => 1005, 'type' => 999, 'dateline' => 1483203600],
        2 => ['id' => 2, 'code' => 1004, 'type' => 83, 'dateline' => 1483203600],
        3 => ['id' => 3, 'code' => 8080, 'type' => 88, 'dateline' => 1483027200],
        4 => ['id' => 4, 'code' => 2020, 'type' => 89, 'dateline' => 1483027200],
        5 => ['id' => 5, 'code' => 6060, 'type' => 90, 'dateline' => 1483027200],
    ];
}
