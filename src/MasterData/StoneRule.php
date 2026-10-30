<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 赌石坊宝石表（旧版 zc_sanguo_stone_rule）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class StoneRule
{
    public const string TABLE = 'zc_sanguo_stone_rule';

    /**
     * 按主键 id 索引，共 3 行。字段：
     *   - id (tinyint) 宝石id
     *   - name (varchar) 宝石名称
     *   - description (varchar) 描述
     *   - trait (varchar) 特点描述
     *   - buytype (tinyint) 购买类型1元宝 2银币
     *   - price (int) 购买价格
     *   - cut_rate_1 (tinyint) 1切出绿宝石概率
     *   - cut_rate_2 (tinyint) 2切出蓝宝石概率
     *   - cut_rate_3 (tinyint) 3切出金宝石概率
     *   - cut_rate_4 (tinyint) 4切出紫宝石概率
     *   - value_0 (mediumint) 初始估值
     *   - value_1 (mediumint) 1切估值
     *   - value_2 (mediumint) 2切估值
     *   - value_3 (mediumint) 3切估值
     *   - broken_rate_1 (smallint) 1切破碎概率
     *   - broken_rate_2 (smallint) 2切破碎概率
     *   - broken_rate_3 (smallint) 3切破碎概率
     *   - broken_rate_4 (smallint) 4切破碎概率
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '盘龙石', 'description' => '龙气精华孕育而成，大概率切出普通宝物。', 'trait' => '切石风险低，回报中。', 'buytype' => 2, 'price' => 2000, 'cut_rate_1' => 90, 'cut_rate_2' => 0, 'cut_rate_3' => 0, 'cut_rate_4' => 0, 'value_0' => 8000, 'value_1' => 0, 'value_2' => 0, 'value_3' => 0, 'broken_rate_1' => 10, 'broken_rate_2' => 0, 'broken_rate_3' => 0, 'broken_rate_4' => 0],
        2 => ['id' => 2, 'name' => '仙王石', 'description' => '天界坠落的仙石，有概率切出顶级宝物。', 'trait' => '切石风险中，回报高。', 'buytype' => 1, 'price' => 100, 'cut_rate_1' => 0, 'cut_rate_2' => 80, 'cut_rate_3' => 0, 'cut_rate_4' => 0, 'value_0' => 40000, 'value_1' => 70000, 'value_2' => 0, 'value_3' => 0, 'broken_rate_1' => 10, 'broken_rate_2' => 20, 'broken_rate_3' => 0, 'broken_rate_4' => 0],
        3 => ['id' => 3, 'name' => '九窍灵石', 'description' => '上古天地之神石，小概率切出神级重宝。', 'trait' => '切石风险高，回报恐怖。', 'buytype' => 1, 'price' => 400, 'cut_rate_1' => 0, 'cut_rate_2' => 0, 'cut_rate_3' => 30, 'cut_rate_4' => 50, 'value_0' => 100000, 'value_1' => 160000, 'value_2' => 250000, 'value_3' => 700000, 'broken_rate_1' => 20, 'broken_rate_2' => 30, 'broken_rate_3' => 40, 'broken_rate_4' => 50],
    ];
}
