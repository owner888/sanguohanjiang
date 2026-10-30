<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 角色注册礼包（旧版 zc_sanguo_role_gift）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class RoleGift
{
    public const string TABLE = 'zc_sanguo_role_gift';

    /**
     * 按主键 id 索引，共 17 行。字段：
     *   - id (smallint)
     *   - day (smallint) 注册天数
     *   - type (tinyint) 奖励类型
     *   - num (int) 奖励数目
     */
    public const array ROWS = [
        1 => ['id' => 1, 'day' => 15, 'type' => 1, 'num' => 50],
        2 => ['id' => 2, 'day' => 30, 'type' => 1, 'num' => 60],
        3 => ['id' => 3, 'day' => 45, 'type' => 1, 'num' => 80],
        4 => ['id' => 4, 'day' => 60, 'type' => 1, 'num' => 100],
        5 => ['id' => 5, 'day' => 90, 'type' => 2, 'num' => 50],
        6 => ['id' => 6, 'day' => 120, 'type' => 2, 'num' => 100],
        7 => ['id' => 7, 'day' => 150, 'type' => 2, 'num' => 150],
        8 => ['id' => 8, 'day' => 180, 'type' => 2, 'num' => 200],
        9 => ['id' => 9, 'day' => 240, 'type' => 2, 'num' => 300],
        10 => ['id' => 10, 'day' => 300, 'type' => 2, 'num' => 400],
        11 => ['id' => 11, 'day' => 360, 'type' => 2, 'num' => 500],
        12 => ['id' => 12, 'day' => 420, 'type' => 2, 'num' => 800],
        13 => ['id' => 13, 'day' => 480, 'type' => 2, 'num' => 1000],
        14 => ['id' => 14, 'day' => 600, 'type' => 2, 'num' => 1500],
        15 => ['id' => 15, 'day' => 720, 'type' => 2, 'num' => 2000],
        16 => ['id' => 16, 'day' => 860, 'type' => 2, 'num' => 3000],
        17 => ['id' => 17, 'day' => 1000, 'type' => 2, 'num' => 4000],
    ];
}
