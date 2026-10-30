<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 主公天赋顿悟表（旧版 zc_sanguo_hero_talent_insight）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class HeroTalentInsight
{
    public const string TABLE = 'zc_sanguo_hero_talent_insight';

    /**
     * 按主键 star 索引，共 6 行。字段：
     *   - star (int) 星级
     *   - rate (tinyint) 成功率
     *   - jade (int) 花费玉石
     *   - part (int) 花费卷轴
     *   - sub1 (tinyint) 扣1星概率
     *   - sub2 (tinyint) 扣2星概率
     *   - sub3 (tinyint) 扣3星概率
     *   - sub4 (tinyint) 扣4星概率
     *   - sub5 (tinyint) 扣5星概率
     *   - protect (int) 保护价格
     *   - success (int) 必升价格
     */
    public const array ROWS = [
        21 => ['star' => 21, 'rate' => 30, 'jade' => 635250, 'part' => 50, 'sub1' => 20, 'sub2' => 20, 'sub3' => 20, 'sub4' => 20, 'sub5' => 20, 'protect' => 40, 'success' => 400],
        41 => ['star' => 41, 'rate' => 25, 'jade' => 2890500, 'part' => 50, 'sub1' => 20, 'sub2' => 20, 'sub3' => 20, 'sub4' => 20, 'sub5' => 20, 'protect' => 80, 'success' => 800],
        61 => ['star' => 61, 'rate' => 20, 'jade' => 7365750, 'part' => 50, 'sub1' => 20, 'sub2' => 20, 'sub3' => 20, 'sub4' => 20, 'sub5' => 20, 'protect' => 160, 'success' => 1600],
        81 => ['star' => 81, 'rate' => 15, 'jade' => 22097250, 'part' => 50, 'sub1' => 20, 'sub2' => 20, 'sub3' => 20, 'sub4' => 20, 'sub5' => 20, 'protect' => 320, 'success' => 3200],
        101 => ['star' => 101, 'rate' => 10, 'jade' => 66291750, 'part' => 50, 'sub1' => 20, 'sub2' => 20, 'sub3' => 20, 'sub4' => 20, 'sub5' => 20, 'protect' => 640, 'success' => 6400],
        201 => ['star' => 201, 'rate' => 5, 'jade' => 165729375, 'part' => 50, 'sub1' => 20, 'sub2' => 20, 'sub3' => 20, 'sub4' => 20, 'sub5' => 20, 'protect' => 1280, 'success' => 12800],
    ];
}
