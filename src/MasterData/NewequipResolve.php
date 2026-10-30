<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 装备分解数据表（旧版 zc_sanguo_newequip_resolve）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class NewequipResolve
{
    public const string TABLE = 'zc_sanguo_newequip_resolve';

    /**
     * 按主键 id 索引，共 3 行。字段：
     *   - id (mediumint) 分解自增id
     *   - qualityid (mediumint) 品级id
     *   - ordinary (mediumint) 普通材料掉落数量
     *   - godcast1 (mediumint) 神铸材料1
     *   - gcrate1 (mediumint) 神铸材料权重
     *   - gcdown1 (mediumint) 神铸材料1下限
     *   - gcup1 (mediumint) 神铸材料上限
     *   - godcast2 (mediumint) 神铸材料2
     *   - gcrate2 (mediumint) 神铸材料2权重
     *   - gcdown2 (mediumint) 神铸材料1下限
     *   - gcup2 (mediumint) 神铸材料上限
     */
    public const array ROWS = [
        0 => ['id' => 0, 'qualityid' => 2, 'ordinary' => 0, 'godcast1' => 0, 'gcrate1' => 0, 'gcdown1' => 0, 'gcup1' => 0, 'godcast2' => 0, 'gcrate2' => 0, 'gcdown2' => 0, 'gcup2' => 0],
        1 => ['id' => 1, 'qualityid' => 6, 'ordinary' => 6, 'godcast1' => 9, 'gcrate1' => 100, 'gcdown1' => 3, 'gcup1' => 3, 'godcast2' => 43, 'gcrate2' => 100, 'gcdown2' => 1, 'gcup2' => 1],
        2 => ['id' => 2, 'qualityid' => 7, 'ordinary' => 6, 'godcast1' => 43, 'gcrate1' => 100, 'gcdown1' => 30, 'gcup1' => 30, 'godcast2' => 45, 'gcrate2' => 100, 'gcdown2' => 20, 'gcup2' => 20],
    ];
}
