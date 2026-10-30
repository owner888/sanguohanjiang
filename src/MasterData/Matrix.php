<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 阵法信息表（旧版 zc_sanguo_matrix）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Matrix
{
    public const string TABLE = 'zc_sanguo_matrix';

    /**
     * 按主键 id 索引，共 11 行。字段：
     *   - id (tinyint)
     *   - name (varchar) 阵法名称
     *   - position1 (tinyint) 开放位置1
     *   - position2 (tinyint) 开放位置2(1-9)
     *   - position3 (tinyint) 开放位置3(1-9)
     *   - position4 (tinyint) 开放位置4(1-9)
     *   - position5 (tinyint) 开放位置5(1-9)
     *   - position6 (int)
     *   - position7 (int)
     *   - position8 (int)
     *   - position9 (int)
     *   - addtype (tinyint) 加成属性
     *   - addition (tinyint) 升级加成百分比
     *   - opentype (tinyint) 1 主公等级 2 vip等级
     *   - openlevel (int) 阵法开启等级-主公等级
     *   - openlevel3 (int) 阵法位置3-阵法等级
     *   - openlevel4 (int) 阵法位置4-阵法等级
     *   - openlevel5 (int) 阵法位置5-阵法等级
     *   - ratio (int) 费用系数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '锥形阵', 'position1' => 2, 'position2' => 4, 'position3' => 6, 'position4' => 7, 'position5' => 9, 'position6' => 0, 'position7' => 0, 'position8' => 0, 'position9' => 0, 'addtype' => 1, 'addition' => 1, 'opentype' => 1, 'openlevel' => 0, 'openlevel3' => 3, 'openlevel4' => 4, 'openlevel5' => 5, 'ratio' => 1],
        2 => ['id' => 2, 'name' => '锋矢阵', 'position1' => 1, 'position2' => 2, 'position3' => 3, 'position4' => 5, 'position5' => 8, 'position6' => 0, 'position7' => 0, 'position8' => 0, 'position9' => 0, 'addtype' => 1, 'addition' => 1, 'opentype' => 1, 'openlevel' => 10, 'openlevel3' => 3, 'openlevel4' => 4, 'openlevel5' => 5, 'ratio' => 1],
        3 => ['id' => 3, 'name' => '八卦阵', 'position1' => 1, 'position2' => 4, 'position3' => 5, 'position4' => 6, 'position5' => 9, 'position6' => 0, 'position7' => 0, 'position8' => 0, 'position9' => 0, 'addtype' => 3, 'addition' => 1, 'opentype' => 1, 'openlevel' => 20, 'openlevel3' => 3, 'openlevel4' => 4, 'openlevel5' => 5, 'ratio' => 1],
        4 => ['id' => 4, 'name' => '七星阵', 'position1' => 1, 'position2' => 3, 'position3' => 5, 'position4' => 7, 'position5' => 9, 'position6' => 0, 'position7' => 0, 'position8' => 0, 'position9' => 0, 'addtype' => 3, 'addition' => 1, 'opentype' => 1, 'openlevel' => 20, 'openlevel3' => 3, 'openlevel4' => 4, 'openlevel5' => 5, 'ratio' => 1],
        5 => ['id' => 5, 'name' => '鱼鳞阵', 'position1' => 2, 'position2' => 4, 'position3' => 5, 'position4' => 6, 'position5' => 8, 'position6' => 0, 'position7' => 0, 'position8' => 0, 'position9' => 0, 'addtype' => 2, 'addition' => 1, 'opentype' => 1, 'openlevel' => 30, 'openlevel3' => 3, 'openlevel4' => 4, 'openlevel5' => 5, 'ratio' => 1],
        6 => ['id' => 6, 'name' => '雁行阵', 'position1' => 1, 'position2' => 3, 'position3' => 4, 'position4' => 6, 'position5' => 8, 'position6' => 0, 'position7' => 0, 'position8' => 0, 'position9' => 0, 'addtype' => 2, 'addition' => 1, 'opentype' => 1, 'openlevel' => 30, 'openlevel3' => 3, 'openlevel4' => 4, 'openlevel5' => 5, 'ratio' => 1],
        7 => ['id' => 7, 'name' => '偃月阵', 'position1' => 2, 'position2' => 3, 'position3' => 8, 'position4' => 4, 'position5' => 9, 'position6' => 0, 'position7' => 0, 'position8' => 0, 'position9' => 0, 'addtype' => 4, 'addition' => 1, 'opentype' => 1, 'openlevel' => 40, 'openlevel3' => 3, 'openlevel4' => 4, 'openlevel5' => 5, 'ratio' => 1],
        8 => ['id' => 8, 'name' => '长蛇阵', 'position1' => 1, 'position2' => 4, 'position3' => 7, 'position4' => 8, 'position5' => 9, 'position6' => 0, 'position7' => 0, 'position8' => 0, 'position9' => 0, 'addtype' => 4, 'addition' => 1, 'opentype' => 1, 'openlevel' => 40, 'openlevel3' => 3, 'openlevel4' => 4, 'openlevel5' => 5, 'ratio' => 1],
        9 => ['id' => 9, 'name' => '壁垒阵', 'position1' => 2, 'position2' => 3, 'position3' => 4, 'position4' => 6, 'position5' => 7, 'position6' => 0, 'position7' => 0, 'position8' => 0, 'position9' => 0, 'addtype' => 5, 'addition' => 1, 'opentype' => 2, 'openlevel' => 7, 'openlevel3' => 0, 'openlevel4' => 0, 'openlevel5' => 0, 'ratio' => 2],
        10 => ['id' => 10, 'name' => '冲锋阵', 'position1' => 2, 'position2' => 5, 'position3' => 7, 'position4' => 8, 'position5' => 9, 'position6' => 0, 'position7' => 0, 'position8' => 0, 'position9' => 0, 'addtype' => 6, 'addition' => 1, 'opentype' => 2, 'openlevel' => 10, 'openlevel3' => 0, 'openlevel4' => 0, 'openlevel5' => 0, 'ratio' => 5],
        11 => ['id' => 11, 'name' => '至尊阵', 'position1' => 1, 'position2' => 2, 'position3' => 3, 'position4' => 4, 'position5' => 5, 'position6' => 6, 'position7' => 7, 'position8' => 8, 'position9' => 9, 'addtype' => 7, 'addition' => 1, 'opentype' => 2, 'openlevel' => 13, 'openlevel3' => 0, 'openlevel4' => 0, 'openlevel5' => 0, 'ratio' => 10],
    ];
}
