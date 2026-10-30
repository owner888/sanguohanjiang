<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 蹴鞠活动元宝消耗表（旧版 zc_sanguo_kemari_price）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class KemariPrice
{
    public const string TABLE = 'zc_sanguo_kemari_price';

    /**
     * 按主键 id 索引，共 2 行。字段：
     *   - id (int) 自增ID
     *   - price (int)
     */
    public const array ROWS = [
        1 => ['id' => 1, 'price' => 100],
        2 => ['id' => 2, 'price' => 500],
    ];
}
