<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 暴打名将常规碎片奖励表（旧版 zc_sanguo_beatengod_hit_chip）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class BeatengodHitChip
{
    public const string TABLE = 'zc_sanguo_beatengod_hit_chip';

    /**
     * 按主键 id 索引，共 2 行。字段：
     *   - id (tinyint) id（1木锤，2金锤）
     *   - price (int) 价格（元宝）
     *   - number2 (tinyint) 碎片2权重 
     *   - number3 (tinyint) 碎片3权重
     *   - number4 (tinyint) 碎片4权重
     *   - number8 (tinyint) 碎片8权重
     *   - number9 (tinyint) 碎片9权重
     *   - number10 (tinyint) 碎片10权重
     *   - number11 (tinyint) 碎片11权重
     *   - number12 (tinyint) 碎片12权重
     *   - number13 (tinyint) 碎片13权重
     *   - number14 (tinyint) 碎片14权重
     *   - number15 (tinyint) 碎片15权重
     *   - number16 (tinyint) 碎片16权重
     */
    public const array ROWS = [
        1 => ['id' => 1, 'price' => 100, 'number2' => 50, 'number3' => 50, 'number4' => 50, 'number8' => 0, 'number9' => 0, 'number10' => 0, 'number11' => 0, 'number12' => 0, 'number13' => 0, 'number14' => 0, 'number15' => 0, 'number16' => 0],
        2 => ['id' => 2, 'price' => 400, 'number2' => 0, 'number3' => 0, 'number4' => 0, 'number8' => 50, 'number9' => 50, 'number10' => 50, 'number11' => 50, 'number12' => 50, 'number13' => 50, 'number14' => 50, 'number15' => 50, 'number16' => 50],
    ];
}
