<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 过关斩将过人概率表（旧版 zc_sanguo_passgeneral_info）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class PassgeneralInfo
{
    public const string TABLE = 'zc_sanguo_passgeneral_info';

    /**
     * 按主键 id 索引，共 11 行。字段：
     *   - id (int) ID
     *   - g_id (int) 将领id
     *   - rate (float) 过人概率
     *   - price1 (int) 过人价格
     *   - price2 (int) 强行突破价格
     *   - num (int) 所属层数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'g_id' => 106, 'rate' => 80.0, 'price1' => 125, 'price2' => 250, 'num' => 1],
        2 => ['id' => 2, 'g_id' => 92, 'rate' => 70.0, 'price1' => 125, 'price2' => 300, 'num' => 1],
        3 => ['id' => 3, 'g_id' => 91, 'rate' => 50.0, 'price1' => 125, 'price2' => 350, 'num' => 1],
        4 => ['id' => 4, 'g_id' => 111, 'rate' => 30.0, 'price1' => 125, 'price2' => 400, 'num' => 1],
        5 => ['id' => 5, 'g_id' => 129, 'rate' => 50.0, 'price1' => 150, 'price2' => 300, 'num' => 2],
        6 => ['id' => 6, 'g_id' => 130, 'rate' => 30.0, 'price1' => 150, 'price2' => 400, 'num' => 2],
        7 => ['id' => 7, 'g_id' => 131, 'rate' => 20.0, 'price1' => 150, 'price2' => 600, 'num' => 2],
        8 => ['id' => 8, 'g_id' => 51, 'rate' => 50.0, 'price1' => 250, 'price2' => 400, 'num' => 3],
        9 => ['id' => 9, 'g_id' => 107, 'rate' => 30.0, 'price1' => 250, 'price2' => 600, 'num' => 3],
        10 => ['id' => 10, 'g_id' => 110, 'rate' => 20.0, 'price1' => 250, 'price2' => 800, 'num' => 3],
        11 => ['id' => 11, 'g_id' => 47, 'rate' => 10.0, 'price1' => 300, 'price2' => 1500, 'num' => 4],
    ];
}
