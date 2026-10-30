<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 精炼大师表（旧版 zc_sanguo_refine_master）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class RefineMaster
{
    public const string TABLE = 'zc_sanguo_refine_master';

    /**
     * 按主键 id 索引，共 12 行。字段：
     *   - id (int) 主键ID
     *   - name (varchar) 大师名称
     *   - grade (tinyint) 大师品级
     *   - desc (varchar) 大师描述
     *   - succ_rate (tinyint) 精炼成功率
     *   - add_type (tinyint) 精炼属性类型
     *   - add_value (int) 精炼属性值
     *   - get_rate (int) 大师刷新权重
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '祖冲之', 'grade' => 1, 'desc' => '著名的数学家和天文学家，利用毫无偏差的技术使兵器增强。', 'succ_rate' => 20, 'add_type' => 1, 'add_value' => 5, 'get_rate' => 30],
        2 => ['id' => 2, 'name' => '鲁班', 'grade' => 2, 'desc' => '木匠行业的祖师，拥有极强的发明天赋。可将装备能力发挥到极致。', 'succ_rate' => 30, 'add_type' => 1, 'add_value' => 7, 'get_rate' => 20],
        3 => ['id' => 3, 'name' => '黄帝', 'grade' => 3, 'desc' => '人文初祖，采众神采首山之铜缔造了轩辕剑，拥有天界诸神的能力。', 'succ_rate' => 40, 'add_type' => 1, 'add_value' => 9, 'get_rate' => 15],
        4 => ['id' => 4, 'name' => '张衡', 'grade' => 4, 'desc' => '一代科圣，可将天地之灵气万物之精华汇集在兵刃之中。', 'succ_rate' => 50, 'add_type' => 1, 'add_value' => 12, 'get_rate' => 10],
        5 => ['id' => 5, 'name' => '马钧', 'grade' => 5, 'desc' => '神秘的灵气师，能使用万物灵气提升兵器的能力。', 'succ_rate' => 60, 'add_type' => 1, 'add_value' => 15, 'get_rate' => 5],
        6 => ['id' => 6, 'name' => '蚩尤', 'grade' => 6, 'desc' => '是战神亦是祸首，使用他所铸造的武器都拥有诛仙的气魄。', 'succ_rate' => 70, 'add_type' => 1, 'add_value' => 20, 'get_rate' => 2],
        7 => ['id' => 7, 'name' => '蔡伦', 'grade' => 1, 'desc' => '四大发明家之一，运用高超的技术提升兵刃的能力。', 'succ_rate' => 20, 'add_type' => 2, 'add_value' => 10, 'get_rate' => 30],
        8 => ['id' => 8, 'name' => '毕昇', 'grade' => 2, 'desc' => '四大发明家之一，可以在兵器上刻印魔咒提升属性。', 'succ_rate' => 30, 'add_type' => 2, 'add_value' => 12, 'get_rate' => 20],
        9 => ['id' => 9, 'name' => '沈括', 'grade' => 3, 'desc' => '千古难寻的奇才，可将万物化作趁手的兵刃，战于沙场。', 'succ_rate' => 40, 'add_type' => 2, 'add_value' => 15, 'get_rate' => 15],
        10 => ['id' => 10, 'name' => '干将', 'grade' => 4, 'desc' => '欧冶子之徒，拥有传世的工匠手艺，可将兵器能力挖掘到极致。', 'succ_rate' => 50, 'add_type' => 2, 'add_value' => 20, 'get_rate' => 10],
        11 => ['id' => 11, 'name' => '张鸦九', 'grade' => 5, 'desc' => '可借天时与地利，可打造出独一无二的绝世利器。', 'succ_rate' => 60, 'add_type' => 2, 'add_value' => 25, 'get_rate' => 5],
        12 => ['id' => 12, 'name' => '欧冶子', 'grade' => 6, 'desc' => '铸剑师的鼻祖，龙泉宝剑铸造者。拥有天下间最高的铸造手法。', 'succ_rate' => 70, 'add_type' => 2, 'add_value' => 30, 'get_rate' => 2],
    ];
}
