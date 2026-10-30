<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 武将继承数据表（旧版 zc_sanguo_general_inherit）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class GeneralInherit
{
    public const string TABLE = 'zc_sanguo_general_inherit';

    /**
     * 按主键 id 索引，共 4 行。字段：
     *   - id (tinyint) 继承id
     *   - name (char) 继承名称
     *   - exp (int) 继承经验效果(%)
     *   - attr (int) 继承属性效果(%)
     *   - price (int) 突飞价格系数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '普通继承', 'exp' => 50, 'attr' => 50, 'price' => 1],
        2 => ['id' => 2, 'name' => '加强继承', 'exp' => 60, 'attr' => 80, 'price' => 2],
        3 => ['id' => 3, 'name' => '白金继承', 'exp' => 70, 'attr' => 100, 'price' => 3],
        4 => ['id' => 4, 'name' => '至尊继承', 'exp' => 100, 'attr' => 100, 'price' => 3],
    ];
}
