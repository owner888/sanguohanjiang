<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 东瀛计谋数据表（旧版 zc_sanguo_japan_strategy）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class JapanStrategy
{
    public const string TABLE = 'zc_sanguo_japan_strategy';

    /**
     * 按主键 strategy_id 索引，共 3 行。字段：
     *   - strategy_id (int) 计谋ID
     *   - strategy_name (varchar) 计谋名称
     *   - strategy_description (text) 计谋描述
     *   - ex_type (int) 消耗资源类型
     *   - ex_number (int) 消耗资源数量
     *   - strategy_type (int) 计谋类型
     *   - param1 (int) 参数1
     *   - param2 (int) 参数2
     */
    public const array ROWS = [
        1 => ['strategy_id' => 1, 'strategy_name' => '鼓舞军心', 'strategy_description' => '提升上阵将领5%攻击力，50点初始士气。每天可使用一次，当日有效。', 'ex_type' => 1, 'ex_number' => 100, 'strategy_type' => 1, 'param1' => 5, 'param2' => 50],
        2 => ['strategy_id' => 2, 'strategy_name' => '兵贵神速', 'strategy_description' => '本次攻城战中，所有国家成员每日攻打次数+1。', 'ex_type' => 2, 'ex_number' => 15, 'strategy_type' => 2, 'param1' => 1, 'param2' => 0],
        3 => ['strategy_id' => 3, 'strategy_name' => '兵粮寸断', 'strategy_description' => '使交战中敌人当前血量下降10%。每天可使用一次。', 'ex_type' => 2, 'ex_number' => 30, 'strategy_type' => 3, 'param1' => 10, 'param2' => 0],
    ];
}
