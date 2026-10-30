<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 酒馆商人数据表（旧版 zc_sanguo_trader）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Trader
{
    public const string TABLE = 'zc_sanguo_trader';

    /**
     * 按主键 id 索引，共 5 行。字段：
     *   - id (tinyint)
     *   - name (varchar) 商人名称
     *   - description (varchar) 商人描述
     *   - image (tinyint)
     *   - cost (smallint) 普通交易开销
     *   - trading (int) 强行贸易花费
     *   - goto1 (tinyint) 转到商人1的概率
     *   - goto2 (tinyint) 转到商人2的概率
     *   - goto3 (tinyint) 转到商人3的概率
     *   - goto4 (tinyint) 转到商人4的概率
     *   - goto5 (tinyint) 转到商人5的概率
     *   - get1 (tinyint) 获得初级宝石概率
     *   - get2 (tinyint) 获得中级宝石概率
     *   - get3 (tinyint) 获得高级宝石概率
     *   - getvalue (tinyint) 获得宝石数量
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '普通商人', 'description' => '小概率获得高等宝石、宝石可用于酒馆购买名将。', 'image' => 0, 'cost' => 10000, 'trading' => 50, 'goto1' => 50, 'goto2' => 50, 'goto3' => 0, 'goto4' => 0, 'goto5' => 0, 'get1' => 100, 'get2' => 0, 'get3' => 0, 'getvalue' => 1],
        2 => ['id' => 2, 'name' => '高级商人', 'description' => '有概率获得高等宝石、宝石可用于酒馆购买名将。', 'image' => 0, 'cost' => 20000, 'trading' => 100, 'goto1' => 30, 'goto2' => 0, 'goto3' => 70, 'goto4' => 0, 'goto5' => 0, 'get1' => 100, 'get2' => 0, 'get3' => 0, 'getvalue' => 2],
        3 => ['id' => 3, 'name' => '西域商人', 'description' => '中等概率获得高等宝石、宝石可用于酒馆购买名将。', 'image' => 0, 'cost' => 30000, 'trading' => 200, 'goto1' => 70, 'goto2' => 0, 'goto3' => 0, 'goto4' => 30, 'goto5' => 0, 'get1' => 0, 'get2' => 100, 'get3' => 0, 'getvalue' => 1],
        4 => ['id' => 4, 'name' => '海外商人', 'description' => '大概率获得高等宝石、宝石可用于酒馆购买名将。', 'image' => 0, 'cost' => 40000, 'trading' => 400, 'goto1' => 80, 'goto2' => 0, 'goto3' => 0, 'goto4' => 0, 'goto5' => 20, 'get1' => 0, 'get2' => 80, 'get3' => 20, 'getvalue' => 1],
        5 => ['id' => 5, 'name' => '神秘商人', 'description' => '必获得最顶级宝石、宝石可用于酒馆购买名将。', 'image' => 0, 'cost' => 50000, 'trading' => 600, 'goto1' => 100, 'goto2' => 0, 'goto3' => 0, 'goto4' => 0, 'goto5' => 0, 'get1' => 0, 'get2' => 0, 'get3' => 100, 'getvalue' => 2],
    ];
}
