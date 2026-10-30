<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 主公命格提炼星魄（旧版 zc_sanguo_star_refine）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class StarRefine
{
    public const string TABLE = 'zc_sanguo_star_refine';

    /**
     * 按主键 rid 索引，共 9 行。字段：
     *   - rid (int) 主键
     *   - id (int) 炼化炉ID(1.青铜炉 2.盘龙炉 3.八卦炉)
     *   - refine_type (tinyint) 炼化类型(1普通提炼 2高级提炼 3至尊提炼)
     *   - type1 (tinyint) 消耗类型1(1.玉石 2.蓝宝石 3.黄宝石 4.紫宝石 5.元宝)
     *   - value1 (int) 消耗数量
     *   - type2 (tinyint) 消耗类型2(1.玉石 2.蓝宝石 3.黄宝石 4.紫宝石 5.元宝)
     *   - value2 (int) 消耗数量
     *   - type3 (tinyint) 消耗类型3(1.玉石 2.蓝宝石 3.黄宝石 4.紫宝石 5.元宝)
     *   - value3 (int) 消耗数量
     *   - gettype (tinyint) 获得类型
     *   - getvalue (int) 获得数量
     */
    public const array ROWS = [
        1 => ['rid' => 1, 'id' => 1, 'refine_type' => 1, 'type1' => 1, 'value1' => 50000, 'type2' => 2, 'value2' => 50, 'type3' => 0, 'value3' => 0, 'gettype' => 1, 'getvalue' => 100],
        2 => ['rid' => 2, 'id' => 1, 'refine_type' => 2, 'type1' => 1, 'value1' => 200000, 'type2' => 2, 'value2' => 200, 'type3' => 5, 'value3' => 100, 'gettype' => 1, 'getvalue' => 600],
        3 => ['rid' => 3, 'id' => 1, 'refine_type' => 3, 'type1' => 1, 'value1' => 450000, 'type2' => 2, 'value2' => 450, 'type3' => 5, 'value3' => 300, 'gettype' => 1, 'getvalue' => 1500],
        4 => ['rid' => 4, 'id' => 2, 'refine_type' => 1, 'type1' => 1, 'value1' => 100000, 'type2' => 3, 'value2' => 50, 'type3' => 0, 'value3' => 0, 'gettype' => 2, 'getvalue' => 100],
        5 => ['rid' => 5, 'id' => 2, 'refine_type' => 2, 'type1' => 1, 'value1' => 400000, 'type2' => 3, 'value2' => 200, 'type3' => 5, 'value3' => 200, 'gettype' => 2, 'getvalue' => 600],
        6 => ['rid' => 6, 'id' => 2, 'refine_type' => 3, 'type1' => 1, 'value1' => 900000, 'type2' => 3, 'value2' => 450, 'type3' => 5, 'value3' => 600, 'gettype' => 2, 'getvalue' => 1500],
        7 => ['rid' => 7, 'id' => 3, 'refine_type' => 1, 'type1' => 1, 'value1' => 300000, 'type2' => 4, 'value2' => 50, 'type3' => 0, 'value3' => 0, 'gettype' => 3, 'getvalue' => 100],
        8 => ['rid' => 8, 'id' => 3, 'refine_type' => 2, 'type1' => 1, 'value1' => 1200000, 'type2' => 4, 'value2' => 200, 'type3' => 5, 'value3' => 400, 'gettype' => 3, 'getvalue' => 600],
        9 => ['rid' => 9, 'id' => 3, 'refine_type' => 3, 'type1' => 1, 'value1' => 2700000, 'type2' => 4, 'value2' => 450, 'type3' => 5, 'value3' => 900, 'gettype' => 3, 'getvalue' => 1500],
    ];
}
