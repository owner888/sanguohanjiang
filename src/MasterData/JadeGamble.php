<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * jade_gamble（旧版 zc_sanguo_jade_gamble）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class JadeGamble
{
    public const string TABLE = 'zc_sanguo_jade_gamble';

    /**
     * 按主键 id 索引，共 3 行。字段：
     *   - id (tinyint)
     *   - price1 (int) 赌博1手价
     *   - price2 (int) 赌博2手价
     *   - price3 (int) 赌博3手价
     *   - price4 (int) 赌博4手价
     *   - price5 (int) 赌博5手价
     *   - price6 (int) 赌博6手价
     *   - price7 (int) 赌博7手价
     *   - price8 (int) 赌博8手价
     *   - price9 (int) 赌博9手价
     *   - price10 (int) 赌博10手价
     *   - probability1 (tinyint) 赌博1手概率
     *   - probability2 (tinyint) 赌博2手概率
     *   - probability3 (tinyint) 赌博3手概率
     *   - probability4 (tinyint) 赌博4手概率
     *   - probability5 (tinyint) 赌博5手概率
     *   - probability6 (tinyint) 赌博6手概率
     *   - probability7 (tinyint) 赌博7手概率
     *   - probability8 (tinyint) 赌博8手概率
     *   - probability9 (tinyint) 赌博9手概率
     *   - probability10 (tinyint) 赌博10手概率
     */
    public const array ROWS = [
        1 => ['id' => 1, 'price1' => 5, 'price2' => 9, 'price3' => 17, 'price4' => 32, 'price5' => 62, 'price6' => 120, 'price7' => 230, 'price8' => 450, 'price9' => 790, 'price10' => 1350, 'probability1' => 100, 'probability2' => 95, 'probability3' => 90, 'probability4' => 85, 'probability5' => 75, 'probability6' => 70, 'probability7' => 75, 'probability8' => 65, 'probability9' => 60, 'probability10' => 50],
        2 => ['id' => 2, 'price1' => 25, 'price2' => 45, 'price3' => 85, 'price4' => 160, 'price5' => 310, 'price6' => 595, 'price7' => 1150, 'price8' => 2250, 'price9' => 4000, 'price10' => 6700, 'probability1' => 100, 'probability2' => 95, 'probability3' => 90, 'probability4' => 85, 'probability5' => 75, 'probability6' => 70, 'probability7' => 75, 'probability8' => 65, 'probability9' => 60, 'probability10' => 50],
        3 => ['id' => 3, 'price1' => 100, 'price2' => 180, 'price3' => 350, 'price4' => 680, 'price5' => 1250, 'price6' => 2300, 'price7' => 4500, 'price8' => 8500, 'price9' => 15000, 'price10' => 25000, 'probability1' => 100, 'probability2' => 95, 'probability3' => 90, 'probability4' => 85, 'probability5' => 65, 'probability6' => 60, 'probability7' => 65, 'probability8' => 60, 'probability9' => 50, 'probability10' => 40],
    ];
}
