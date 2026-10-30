<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 势力地图（旧版 zc_sanguo_map）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Map
{
    public const string TABLE = 'zc_sanguo_map';

    /**
     * 按主键 id 索引，共 17 行。字段：
     *   - id (tinyint)
     *   - name (varchar) 势力名称
     *   - premise (tinyint) 前置势力
     *   - rewardtype (tinyint) 通关奖励类型 1、装备
     *   - rewardnum (int) 添加数量
     *   - reward (int) 奖励内容
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '黄巾势力', 'premise' => 0, 'rewardtype' => 1, 'rewardnum' => 1, 'reward' => 6],
        2 => ['id' => 2, 'name' => '袁绍势力', 'premise' => 1, 'rewardtype' => 1, 'rewardnum' => 1, 'reward' => 19],
        3 => ['id' => 3, 'name' => '公孙瓒势力', 'premise' => 2, 'rewardtype' => 1, 'rewardnum' => 1, 'reward' => 12],
        4 => ['id' => 4, 'name' => '董卓势力', 'premise' => 3, 'rewardtype' => 1, 'rewardnum' => 1, 'reward' => 28],
        5 => ['id' => 5, 'name' => '马腾势力', 'premise' => 4, 'rewardtype' => 1, 'rewardnum' => 1, 'reward' => 51],
        6 => ['id' => 6, 'name' => '刘表势力', 'premise' => 5, 'rewardtype' => 2, 'rewardnum' => 50, 'reward' => 1],
        7 => ['id' => 7, 'name' => '袁术势力', 'premise' => 6, 'rewardtype' => 2, 'rewardnum' => 50, 'reward' => 7],
        8 => ['id' => 8, 'name' => '吕布势力', 'premise' => 7, 'rewardtype' => 2, 'rewardnum' => 50, 'reward' => 13],
        9 => ['id' => 9, 'name' => '曹操势力', 'premise' => 8, 'rewardtype' => 2, 'rewardnum' => 50, 'reward' => 19],
        10 => ['id' => 10, 'name' => '刘备势力', 'premise' => 9, 'rewardtype' => 2, 'rewardnum' => 50, 'reward' => 25],
        11 => ['id' => 11, 'name' => '孙氏势力', 'premise' => 10, 'rewardtype' => 1, 'rewardnum' => 1, 'reward' => 57],
        12 => ['id' => 12, 'name' => '孟获势力', 'premise' => 11, 'rewardtype' => 1, 'rewardnum' => 1, 'reward' => 66],
        13 => ['id' => 13, 'name' => '匈奴势力', 'premise' => 12, 'rewardtype' => 1, 'rewardnum' => 1, 'reward' => 58],
        14 => ['id' => 14, 'name' => '英雄集结', 'premise' => 13, 'rewardtype' => 2, 'rewardnum' => 100, 'reward' => 26],
        15 => ['id' => 15, 'name' => '南宋', 'premise' => 14, 'rewardtype' => 1, 'rewardnum' => 1, 'reward' => 71],
        16 => ['id' => 16, 'name' => '隋唐', 'premise' => 15, 'rewardtype' => 1, 'rewardnum' => 1, 'reward' => 79],
        17 => ['id' => 17, 'name' => '战国', 'premise' => 16, 'rewardtype' => 4, 'rewardnum' => 1, 'reward' => 21],
    ];
}
