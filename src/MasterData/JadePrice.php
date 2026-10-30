<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 神矿挖掘表（旧版 zc_sanguo_jade_price）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class JadePrice
{
    public const string TABLE = 'zc_sanguo_jade_price';

    /**
     * 按主键 id 索引，共 3 行。字段：
     *   - id (tinyint)
     *   - price (int) 挖掘价格
     *   - X1 (tinyint) 1倍概率
     *   - X2 (tinyint) 2倍概率
     *   - X3 (tinyint) 3倍概率
     *   - X4 (tinyint) 4倍概率
     *   - X5 (tinyint) 5倍概率
     *   - X6 (tinyint) 6倍概率
     *   - X7 (tinyint) 7倍概率
     *   - X8 (tinyint) 8倍概率
     *   - X9 (tinyint) 9倍概率
     *   - X10 (tinyint) 10倍概率
     */
    public const array ROWS = [
        1 => ['id' => 1, 'price' => 30, 'X1' => 3, 'X2' => 10, 'X3' => 15, 'X4' => 17, 'X5' => 16, 'X6' => 12, 'X7' => 9, 'X8' => 7, 'X9' => 6, 'X10' => 5],
        2 => ['id' => 2, 'price' => 100, 'X1' => 0, 'X2' => 0, 'X3' => 25, 'X4' => 20, 'X5' => 16, 'X6' => 12, 'X7' => 9, 'X8' => 7, 'X9' => 6, 'X10' => 5],
        3 => ['id' => 3, 'price' => 300, 'X1' => 0, 'X2' => 0, 'X3' => 0, 'X4' => 0, 'X5' => 32, 'X6' => 25, 'X7' => 18, 'X8' => 12, 'X9' => 8, 'X10' => 5],
    ];
}
