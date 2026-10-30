<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 海外贸易货物数据表（旧版 zc_sanguo_overseas_trade_shop）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class OverseasTradeShop
{
    public const string TABLE = 'zc_sanguo_overseas_trade_shop';

    /**
     * 按主键 id 索引，共 5 行。字段：
     *   - id (tinyint) 货物id
     *   - name (varchar) 货物名称
     *   - buy_type (tinyint) 货币类型（1银币，2元宝）
     *   - buy_value (mediumint) 货币参数（银币是系数）
     *   - get (mediumint) 出售获得银币（系数）
     *   - need_vip (tinyint) 购买该货物需要vip等级
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '粮食', 'buy_type' => 1, 'buy_value' => 800, 'get' => 4800, 'need_vip' => 0],
        2 => ['id' => 2, 'name' => '生铁', 'buy_type' => 2, 'buy_value' => 200, 'get' => 8000, 'need_vip' => 2],
        3 => ['id' => 3, 'name' => '丝绸', 'buy_type' => 2, 'buy_value' => 400, 'get' => 16000, 'need_vip' => 4],
        4 => ['id' => 4, 'name' => '黄金', 'buy_type' => 2, 'buy_value' => 800, 'get' => 32000, 'need_vip' => 6],
        5 => ['id' => 5, 'name' => '玛瑙', 'buy_type' => 2, 'buy_value' => 1600, 'get' => 64000, 'need_vip' => 8],
    ];
}
