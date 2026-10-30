<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 坐骑西域马场表（旧版 zc_sanguo_mount_ratio）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class MountRatio
{
    public const string TABLE = 'zc_sanguo_mount_ratio';

    /**
     * 按主键 id 索引，共 3 行。字段：
     *   - id (int) 马场id
     *   - name (varchar) 马场名称
     *   - sub_type (tinyint) 消耗类型（1黄宝石，2紫宝石）
     *   - price (int) 选马价格
     *   - rate1 (smallint) 白色权重
     *   - rate2 (smallint) 绿色权重
     *   - rate3 (smallint) 蓝色权重
     *   - rate4 (smallint) 金色权重
     *   - rate5 (smallint) 紫色权重
     *   - rate6 (smallint) 红色权重
     *   - rate7 (smallint) 暗金色权重
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '十里挑一', 'sub_type' => 1, 'price' => 500, 'rate1' => 20, 'rate2' => 30, 'rate3' => 30, 'rate4' => 20, 'rate5' => 0, 'rate6' => 0, 'rate7' => 0],
        2 => ['id' => 2, 'name' => '百里挑一', 'sub_type' => 2, 'price' => 200, 'rate1' => 0, 'rate2' => 10, 'rate3' => 10, 'rate4' => 30, 'rate5' => 40, 'rate6' => 0, 'rate7' => 0],
        3 => ['id' => 3, 'name' => '万里挑一', 'sub_type' => 2, 'price' => 500, 'rate1' => 0, 'rate2' => 0, 'rate3' => 0, 'rate4' => 20, 'rate5' => 35, 'rate6' => 40, 'rate7' => 5],
    ];
}
