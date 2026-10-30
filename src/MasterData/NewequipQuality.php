<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 装备数据表（旧版 zc_sanguo_newequip_quality）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class NewequipQuality
{
    public const string TABLE = 'zc_sanguo_newequip_quality';

    /**
     * 按主键 qualityid 索引，共 5 行。字段：
     *   - qualityid (mediumint) 品阶id
     *   - name (varchar) 品阶名称
     *   - description (varchar) 品阶描述
     *   - attributedown (mediumint) 属性下限
     *   - attributeup (mediumint) 属性上限
     *   - rate (mediumint) 权重
     *   - upattr (mediumint) 保底概率
     */
    public const array ROWS = [
        0 => ['qualityid' => 0, 'name' => '无', 'description' => '很耐用', 'attributedown' => 0, 'attributeup' => 0, 'rate' => 0, 'upattr' => 0],
        1 => ['qualityid' => 1, 'name' => '普通', 'description' => '潜力十足', 'attributedown' => 100, 'attributeup' => 120, 'rate' => 90, 'upattr' => 1],
        2 => ['qualityid' => 2, 'name' => '良好', 'description' => '寒光闪闪', 'attributedown' => 120, 'attributeup' => 140, 'rate' => 6, 'upattr' => 96],
        3 => ['qualityid' => 3, 'name' => '优秀', 'description' => '光芒四射', 'attributedown' => 140, 'attributeup' => 170, 'rate' => 3, 'upattr' => 97],
        4 => ['qualityid' => 4, 'name' => '完美', 'description' => '价值连城', 'attributedown' => 170, 'attributeup' => 200, 'rate' => 1, 'upattr' => 999],
    ];
}
