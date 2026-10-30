<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 谏言模版表（旧版 zc_sanguo_expostulation_temp）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ExpostulationTemp
{
    public const string TABLE = 'zc_sanguo_expostulation_temp';

    /**
     * 按主键 id 索引，共 35 行。字段：
     *   - id (int) 模版id
     *   - name (varchar) 名称
     *   - description (varchar) 描述
     *   - quality (tinyint) 谏言品级（白~紫，1~5）
     *   - type (tinyint) 谏言类型
     *   - value (int) 完成任务参数
     *   - put_price (int) 发起谏言价格
     *   - sup_price (int) 支持谏言价格
     *   - get_type (tinyint) 奖励类型（1银币，2将魂）
     *   - put_value (mediumint) 发起者奖励
     *   - sup_value (mediumint) 支持者奖励
     *   - put_contribute (int) 发起者获得国家贡献
     *   - sup_contribute (int) 支持者获得国家贡献
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '神兵利器', 'description' => '全国强化装备累计30次', 'quality' => 1, 'type' => 1, 'value' => 30, 'put_price' => 60, 'sup_price' => 1, 'get_type' => 1, 'put_value' => 1000, 'sup_value' => 100, 'put_contribute' => 120, 'sup_contribute' => 2],
        2 => ['id' => 2, 'name' => '整军演武', 'description' => '全国武将升级30次', 'quality' => 1, 'type' => 2, 'value' => 30, 'put_price' => 60, 'sup_price' => 1, 'get_type' => 1, 'put_value' => 1000, 'sup_value' => 100, 'put_contribute' => 120, 'sup_contribute' => 2],
        3 => ['id' => 3, 'name' => '闭门苦修', 'description' => '全国培养属性30次', 'quality' => 1, 'type' => 3, 'value' => 30, 'put_price' => 60, 'sup_price' => 1, 'get_type' => 1, 'put_value' => 1000, 'sup_value' => 100, 'put_contribute' => 120, 'sup_contribute' => 2],
        4 => ['id' => 4, 'name' => '贸易互通', 'description' => '全国贸易30次', 'quality' => 1, 'type' => 4, 'value' => 30, 'put_price' => 60, 'sup_price' => 1, 'get_type' => 1, 'put_value' => 1000, 'sup_value' => 100, 'put_contribute' => 120, 'sup_contribute' => 2],
        5 => ['id' => 5, 'name' => '加强税收', 'description' => '全国征收30次', 'quality' => 1, 'type' => 5, 'value' => 30, 'put_price' => 60, 'sup_price' => 1, 'get_type' => 1, 'put_value' => 1000, 'sup_value' => 100, 'put_contribute' => 120, 'sup_contribute' => 2],
        6 => ['id' => 6, 'name' => '四海通商', 'description' => '全国通商30次', 'quality' => 1, 'type' => 6, 'value' => 30, 'put_price' => 60, 'sup_price' => 1, 'get_type' => 1, 'put_value' => 1000, 'sup_value' => 100, 'put_contribute' => 120, 'sup_contribute' => 2],
        7 => ['id' => 7, 'name' => '群雄逐鹿', 'description' => '全国征战主线30次', 'quality' => 1, 'type' => 7, 'value' => 30, 'put_price' => 60, 'sup_price' => 1, 'get_type' => 1, 'put_value' => 1000, 'sup_value' => 100, 'put_contribute' => 120, 'sup_contribute' => 2],
        8 => ['id' => 8, 'name' => '神兵利器', 'description' => '全国强化装备累计60次', 'quality' => 2, 'type' => 1, 'value' => 60, 'put_price' => 150, 'sup_price' => 3, 'get_type' => 1, 'put_value' => 3000, 'sup_value' => 300, 'put_contribute' => 300, 'sup_contribute' => 6],
        9 => ['id' => 9, 'name' => '整军演武', 'description' => '全国武将升级60次', 'quality' => 2, 'type' => 2, 'value' => 60, 'put_price' => 150, 'sup_price' => 3, 'get_type' => 1, 'put_value' => 3000, 'sup_value' => 300, 'put_contribute' => 300, 'sup_contribute' => 6],
        10 => ['id' => 10, 'name' => '闭门苦修', 'description' => '全国培养属性60次', 'quality' => 2, 'type' => 3, 'value' => 60, 'put_price' => 150, 'sup_price' => 3, 'get_type' => 1, 'put_value' => 3000, 'sup_value' => 300, 'put_contribute' => 300, 'sup_contribute' => 6],
        11 => ['id' => 11, 'name' => '贸易互通', 'description' => '全国贸易60次', 'quality' => 2, 'type' => 4, 'value' => 60, 'put_price' => 150, 'sup_price' => 3, 'get_type' => 1, 'put_value' => 3000, 'sup_value' => 300, 'put_contribute' => 300, 'sup_contribute' => 6],
        12 => ['id' => 12, 'name' => '加强税收', 'description' => '全国征收60次', 'quality' => 2, 'type' => 5, 'value' => 60, 'put_price' => 150, 'sup_price' => 3, 'get_type' => 1, 'put_value' => 3000, 'sup_value' => 300, 'put_contribute' => 300, 'sup_contribute' => 6],
        13 => ['id' => 13, 'name' => '四海通商', 'description' => '全国通商60次', 'quality' => 2, 'type' => 6, 'value' => 60, 'put_price' => 150, 'sup_price' => 3, 'get_type' => 1, 'put_value' => 3000, 'sup_value' => 300, 'put_contribute' => 300, 'sup_contribute' => 6],
        14 => ['id' => 14, 'name' => '群雄逐鹿', 'description' => '全国征战主线60次', 'quality' => 2, 'type' => 7, 'value' => 60, 'put_price' => 150, 'sup_price' => 3, 'get_type' => 1, 'put_value' => 3000, 'sup_value' => 300, 'put_contribute' => 300, 'sup_contribute' => 6],
        15 => ['id' => 15, 'name' => '神兵利器', 'description' => '全国强化装备累计90次', 'quality' => 3, 'type' => 1, 'value' => 120, 'put_price' => 200, 'sup_price' => 5, 'get_type' => 1, 'put_value' => 5000, 'sup_value' => 500, 'put_contribute' => 400, 'sup_contribute' => 10],
        16 => ['id' => 16, 'name' => '整军演武', 'description' => '全国武将升级90次', 'quality' => 3, 'type' => 2, 'value' => 120, 'put_price' => 200, 'sup_price' => 5, 'get_type' => 1, 'put_value' => 5000, 'sup_value' => 500, 'put_contribute' => 400, 'sup_contribute' => 10],
        17 => ['id' => 17, 'name' => '闭门苦修', 'description' => '全国培养属性90次', 'quality' => 3, 'type' => 3, 'value' => 120, 'put_price' => 200, 'sup_price' => 5, 'get_type' => 1, 'put_value' => 5000, 'sup_value' => 500, 'put_contribute' => 400, 'sup_contribute' => 10],
        18 => ['id' => 18, 'name' => '贸易互通', 'description' => '全国贸易90次', 'quality' => 3, 'type' => 4, 'value' => 120, 'put_price' => 200, 'sup_price' => 5, 'get_type' => 1, 'put_value' => 5000, 'sup_value' => 500, 'put_contribute' => 400, 'sup_contribute' => 10],
        19 => ['id' => 19, 'name' => '加强税收', 'description' => '全国征收90次', 'quality' => 3, 'type' => 5, 'value' => 120, 'put_price' => 200, 'sup_price' => 5, 'get_type' => 1, 'put_value' => 5000, 'sup_value' => 500, 'put_contribute' => 400, 'sup_contribute' => 10],
        20 => ['id' => 20, 'name' => '四海通商', 'description' => '全国通商90次', 'quality' => 3, 'type' => 6, 'value' => 120, 'put_price' => 200, 'sup_price' => 5, 'get_type' => 1, 'put_value' => 5000, 'sup_value' => 500, 'put_contribute' => 400, 'sup_contribute' => 10],
        21 => ['id' => 21, 'name' => '群雄逐鹿', 'description' => '全国征战主线90次', 'quality' => 3, 'type' => 7, 'value' => 120, 'put_price' => 200, 'sup_price' => 5, 'get_type' => 1, 'put_value' => 5000, 'sup_value' => 500, 'put_contribute' => 400, 'sup_contribute' => 10],
        22 => ['id' => 22, 'name' => '神兵利器', 'description' => '全国强化装备累计150次', 'quality' => 4, 'type' => 1, 'value' => 240, 'put_price' => 300, 'sup_price' => 10, 'get_type' => 1, 'put_value' => 10000, 'sup_value' => 1000, 'put_contribute' => 600, 'sup_contribute' => 20],
        23 => ['id' => 23, 'name' => '整军演武', 'description' => '全国武将升级150次', 'quality' => 4, 'type' => 2, 'value' => 240, 'put_price' => 300, 'sup_price' => 10, 'get_type' => 1, 'put_value' => 10000, 'sup_value' => 1000, 'put_contribute' => 600, 'sup_contribute' => 20],
        24 => ['id' => 24, 'name' => '闭门苦修', 'description' => '全国培养属性150次', 'quality' => 4, 'type' => 3, 'value' => 240, 'put_price' => 300, 'sup_price' => 10, 'get_type' => 1, 'put_value' => 10000, 'sup_value' => 1000, 'put_contribute' => 600, 'sup_contribute' => 20],
        25 => ['id' => 25, 'name' => '贸易互通', 'description' => '全国贸易150次', 'quality' => 4, 'type' => 4, 'value' => 240, 'put_price' => 300, 'sup_price' => 10, 'get_type' => 1, 'put_value' => 10000, 'sup_value' => 1000, 'put_contribute' => 600, 'sup_contribute' => 20],
        26 => ['id' => 26, 'name' => '加强税收', 'description' => '全国征收150次', 'quality' => 4, 'type' => 5, 'value' => 240, 'put_price' => 300, 'sup_price' => 10, 'get_type' => 1, 'put_value' => 10000, 'sup_value' => 1000, 'put_contribute' => 600, 'sup_contribute' => 20],
        27 => ['id' => 27, 'name' => '四海通商', 'description' => '全国通商150次', 'quality' => 4, 'type' => 6, 'value' => 240, 'put_price' => 300, 'sup_price' => 10, 'get_type' => 1, 'put_value' => 10000, 'sup_value' => 1000, 'put_contribute' => 600, 'sup_contribute' => 20],
        28 => ['id' => 28, 'name' => '群雄逐鹿', 'description' => '全国征战主线150次', 'quality' => 4, 'type' => 7, 'value' => 240, 'put_price' => 300, 'sup_price' => 10, 'get_type' => 1, 'put_value' => 10000, 'sup_value' => 1000, 'put_contribute' => 600, 'sup_contribute' => 20],
        29 => ['id' => 29, 'name' => '神兵利器', 'description' => '全国强化装备累计300次', 'quality' => 5, 'type' => 1, 'value' => 480, 'put_price' => 400, 'sup_price' => 20, 'get_type' => 1, 'put_value' => 20000, 'sup_value' => 2000, 'put_contribute' => 800, 'sup_contribute' => 40],
        30 => ['id' => 30, 'name' => '整军演武', 'description' => '全国武将升级300次', 'quality' => 5, 'type' => 2, 'value' => 480, 'put_price' => 400, 'sup_price' => 20, 'get_type' => 1, 'put_value' => 20000, 'sup_value' => 2000, 'put_contribute' => 800, 'sup_contribute' => 40],
        31 => ['id' => 31, 'name' => '闭门苦修', 'description' => '全国培养属性300次', 'quality' => 5, 'type' => 3, 'value' => 480, 'put_price' => 400, 'sup_price' => 20, 'get_type' => 1, 'put_value' => 20000, 'sup_value' => 2000, 'put_contribute' => 800, 'sup_contribute' => 40],
        32 => ['id' => 32, 'name' => '贸易互通', 'description' => '全国贸易300次', 'quality' => 5, 'type' => 4, 'value' => 480, 'put_price' => 400, 'sup_price' => 20, 'get_type' => 1, 'put_value' => 20000, 'sup_value' => 2000, 'put_contribute' => 800, 'sup_contribute' => 40],
        33 => ['id' => 33, 'name' => '加强税收', 'description' => '全国征收300次', 'quality' => 5, 'type' => 5, 'value' => 480, 'put_price' => 400, 'sup_price' => 20, 'get_type' => 1, 'put_value' => 20000, 'sup_value' => 2000, 'put_contribute' => 800, 'sup_contribute' => 40],
        34 => ['id' => 34, 'name' => '四海通商', 'description' => '全国通商300次', 'quality' => 5, 'type' => 6, 'value' => 480, 'put_price' => 400, 'sup_price' => 20, 'get_type' => 1, 'put_value' => 20000, 'sup_value' => 2000, 'put_contribute' => 800, 'sup_contribute' => 40],
        35 => ['id' => 35, 'name' => '群雄逐鹿', 'description' => '全国征战主线300次', 'quality' => 5, 'type' => 7, 'value' => 480, 'put_price' => 400, 'sup_price' => 20, 'get_type' => 1, 'put_value' => 20000, 'sup_value' => 2000, 'put_contribute' => 800, 'sup_contribute' => 40],
    ];
}
