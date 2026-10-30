<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 培养表（旧版 zc_sanguo_general_culture）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class GeneralCulture
{
    public const string TABLE = 'zc_sanguo_general_culture';

    /**
     * 按主键 id 索引，共 4 行。字段：
     *   - id (tinyint) 培养类型ID
     *   - name (varchar) 名称
     *   - silver (int) 花费银币基数（乘以主公等级为实际花费）
     *   - gold (int) 消耗金币数量
     *   - addition (tinyint) 多点一次增加花费
     *   - attrsub6 (tinyint) 属性点减6概率
     *   - attrsub5 (tinyint) 属性点减5概率
     *   - attrsub4 (tinyint) 属性点减4概率
     *   - attrsub3 (tinyint) 属性点减3概率
     *   - attrsub2 (tinyint) 属性点减2概率
     *   - attrsub1 (tinyint) 属性点减1概率
     *   - attrsub0 (tinyint) 属性点加0概率
     *   - attradd1 (tinyint) 属性点加1概率
     *   - attradd2 (tinyint) 属性点加2概率
     *   - attradd3 (tinyint) 属性点加3概率
     *   - attradd4 (tinyint) 属性点加4概率
     *   - attradd5 (tinyint) 属性点加5概率
     *   - attradd6 (tinyint) 属性点加6概率
     *   - baseaddition (tinyint) 基本加成(累计培养低于该阀值必加成)
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '普通培养', 'silver' => 100, 'gold' => 0, 'addition' => 0, 'attrsub6' => 100, 'attrsub5' => 90, 'attrsub4' => 80, 'attrsub3' => 70, 'attrsub2' => 60, 'attrsub1' => 50, 'attrsub0' => 15, 'attradd1' => 50, 'attradd2' => 50, 'attradd3' => 0, 'attradd4' => 0, 'attradd5' => 0, 'attradd6' => 0, 'baseaddition' => 10],
        2 => ['id' => 2, 'name' => '加强培养', 'silver' => 0, 'gold' => 20, 'addition' => 0, 'attrsub6' => 0, 'attrsub5' => 100, 'attrsub4' => 90, 'attrsub3' => 80, 'attrsub2' => 70, 'attrsub1' => 60, 'attrsub0' => 15, 'attradd1' => 50, 'attradd2' => 50, 'attradd3' => 50, 'attradd4' => 0, 'attradd5' => 0, 'attradd6' => 0, 'baseaddition' => 10],
        3 => ['id' => 3, 'name' => '白金培养', 'silver' => 0, 'gold' => 50, 'addition' => 1, 'attrsub6' => 0, 'attrsub5' => 0, 'attrsub4' => 100, 'attrsub3' => 90, 'attrsub2' => 80, 'attrsub1' => 70, 'attrsub0' => 50, 'attradd1' => 60, 'attradd2' => 60, 'attradd3' => 60, 'attradd4' => 60, 'attradd5' => 0, 'attradd6' => 0, 'baseaddition' => 20],
        4 => ['id' => 4, 'name' => '至尊培养', 'silver' => 0, 'gold' => 100, 'addition' => 3, 'attrsub6' => 0, 'attrsub5' => 0, 'attrsub4' => 0, 'attrsub3' => 100, 'attrsub2' => 90, 'attrsub1' => 80, 'attrsub0' => 200, 'attradd1' => 210, 'attradd2' => 220, 'attradd3' => 230, 'attradd4' => 240, 'attradd5' => 250, 'attradd6' => 0, 'baseaddition' => 30],
    ];
}
