<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 装备精华模板表（旧版 zc_sanguo_equip_essence）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class EquipEssence
{
    public const string TABLE = 'zc_sanguo_equip_essence';

    /**
     * 按主键 id 索引，共 18 行。字段：
     *   - id (int) 精华ID
     *   - name (varchar) 精华名称
     *   - quality (tinyint) 精华品级
     *   - desc (varchar) 描述
     *   - price (int) 售价
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '武器精华', 'quality' => 1, 'desc' => '白色武器精华，可以解锁武器的强化成就', 'price' => 10000],
        2 => ['id' => 2, 'name' => '武器精华', 'quality' => 2, 'desc' => '绿色武器精华，可以解锁武器的强化成就', 'price' => 30000],
        3 => ['id' => 3, 'name' => '武器精华', 'quality' => 3, 'desc' => '蓝色武器精华，可以解锁武器的强化成就', 'price' => 50000],
        4 => ['id' => 4, 'name' => '武器精华', 'quality' => 4, 'desc' => '金色武器精华，可以解锁武器的强化成就', 'price' => 100000],
        5 => ['id' => 5, 'name' => '武器精华', 'quality' => 5, 'desc' => '紫色武器精华，可以解锁武器的强化成就', 'price' => 130000],
        6 => ['id' => 6, 'name' => '武器精华', 'quality' => 6, 'desc' => '红色武器精华，可以解锁武器的强化成就', 'price' => 150000],
        7 => ['id' => 7, 'name' => '防具精华', 'quality' => 1, 'desc' => '白色防具精华，可以解锁防具的强化成就', 'price' => 10000],
        8 => ['id' => 8, 'name' => '防具精华', 'quality' => 2, 'desc' => '绿色防具精华，可以解锁防具的强化成就', 'price' => 30000],
        9 => ['id' => 9, 'name' => '防具精华', 'quality' => 3, 'desc' => '蓝色防具精华，可以解锁防具的强化成就', 'price' => 50000],
        10 => ['id' => 10, 'name' => '防具精华', 'quality' => 4, 'desc' => '金色防具精华，可以解锁防具的强化成就', 'price' => 100000],
        11 => ['id' => 11, 'name' => '防具精华', 'quality' => 5, 'desc' => '紫色防具精华，可以解锁防具的强化成就', 'price' => 130000],
        12 => ['id' => 12, 'name' => '防具精华', 'quality' => 6, 'desc' => '红色防具精华，可以解锁防具的强化成就', 'price' => 150000],
        13 => ['id' => 13, 'name' => '饰品精华', 'quality' => 1, 'desc' => '白色饰品精华，可以解锁饰品的强化成就', 'price' => 10000],
        14 => ['id' => 14, 'name' => '饰品精华', 'quality' => 2, 'desc' => '绿色饰品精华，可以解锁饰品的强化成就', 'price' => 30000],
        15 => ['id' => 15, 'name' => '饰品精华', 'quality' => 3, 'desc' => '蓝色饰品精华，可以解锁饰品的强化成就', 'price' => 50000],
        16 => ['id' => 16, 'name' => '饰品精华', 'quality' => 4, 'desc' => '金色饰品精华，可以解锁饰品的强化成就', 'price' => 100000],
        17 => ['id' => 17, 'name' => '饰品精华', 'quality' => 5, 'desc' => '紫色饰品精华，可以解锁饰品的强化成就', 'price' => 130000],
        18 => ['id' => 18, 'name' => '饰品精华', 'quality' => 6, 'desc' => '红色饰品精华，可以解锁饰品的强化成就', 'price' => 150000],
    ];
}
