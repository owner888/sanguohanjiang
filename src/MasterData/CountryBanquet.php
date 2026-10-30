<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 国家宴会数据表（旧版 zc_sanguo_country_banquet）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class CountryBanquet
{
    public const string TABLE = 'zc_sanguo_country_banquet';

    /**
     * 按主键 id 索引，共 3 行。字段：
     *   - id (tinyint) 宴会id
     *   - name (varchar) 宴会名称
     *   - number (tinyint) 参加人数
     *   - sub_gold (int) 消耗元宝数量（0免费）
     *   - silver (int) 奖励银币（系数）
     *   - contribute (mediumint) 奖励国家贡献
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '小型宴会', 'number' => 2, 'sub_gold' => 0, 'silver' => 500, 'contribute' => 10],
        2 => ['id' => 2, 'name' => '大型宴会', 'number' => 5, 'sub_gold' => 60, 'silver' => 1000, 'contribute' => 30],
        3 => ['id' => 3, 'name' => '国宴', 'number' => 10, 'sub_gold' => 200, 'silver' => 2000, 'contribute' => 50],
    ];
}
