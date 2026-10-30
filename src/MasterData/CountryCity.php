<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 都城占领表（旧版 zc_sanguo_country_city）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class CountryCity
{
    public const string TABLE = 'zc_sanguo_country_city';

    /**
     * 按主键 maplevel 索引，共 13 行。字段：
     *   - maplevel (tinyint) 都城id
     *   - challengercid (int) 挑战者国家id
     *   - challengername (varchar) 挑战者国家名字
     *   - challengerlevel (tinyint) 挑战方等级
     *   - challengerdateline (int) 挑战时间
     *   - cid (int) 占领的国家id
     *   - name (varchar) 国家名称
     *   - level (int) 占领者国家等级
     *   - dateline (int) 占领时间
     *   - lock (tinyint) 是否被国家通过至尊占领锁定
     */
    public const array ROWS = [
        1 => ['maplevel' => 1, 'challengercid' => 20, 'challengername' => '国家', 'challengerlevel' => 6, 'challengerdateline' => 1463066105, 'cid' => 0, 'name' => '', 'level' => 0, 'dateline' => 0, 'lock' => 0],
        2 => ['maplevel' => 2, 'challengercid' => 5, 'challengername' => '霸气♛十足', 'challengerlevel' => 4, 'challengerdateline' => 1458146074, 'cid' => 157, 'name' => 'safas', 'level' => 1, 'dateline' => 0, 'lock' => 1],
        3 => ['maplevel' => 3, 'challengercid' => 37, 'challengername' => '遇见', 'challengerlevel' => 5, 'challengerdateline' => 1455558512, 'cid' => 0, 'name' => '', 'level' => 0, 'dateline' => 0, 'lock' => 1],
        4 => ['maplevel' => 4, 'challengercid' => 1, 'challengername' => 'AC帝国', 'challengerlevel' => 3, 'challengerdateline' => 1458110941, 'cid' => 0, 'name' => '', 'level' => 0, 'dateline' => 0, 'lock' => 1],
        5 => ['maplevel' => 5, 'challengercid' => 83, 'challengername' => '你是我兄弟', 'challengerlevel' => 5, 'challengerdateline' => 1463382073, 'cid' => 0, 'name' => '', 'level' => 0, 'dateline' => 0, 'lock' => 0],
        6 => ['maplevel' => 6, 'challengercid' => 108, 'challengername' => '待我强大', 'challengerlevel' => 4, 'challengerdateline' => 1464177132, 'cid' => 0, 'name' => '', 'level' => 0, 'dateline' => 0, 'lock' => 0],
        7 => ['maplevel' => 7, 'challengercid' => 127, 'challengername' => '云', 'challengerlevel' => 1, 'challengerdateline' => 1467128742, 'cid' => 0, 'name' => '', 'level' => 0, 'dateline' => 0, 'lock' => 1],
        8 => ['maplevel' => 8, 'challengercid' => 16, 'challengername' => '艾钗媚', 'challengerlevel' => 10, 'challengerdateline' => 1460218088, 'cid' => 27, 'name' => '光芒神殿', 'level' => 104, 'dateline' => 1456573393, 'lock' => 0],
        9 => ['maplevel' => 9, 'challengercid' => 82, 'challengername' => '王', 'challengerlevel' => 1, 'challengerdateline' => 1463370745, 'cid' => 0, 'name' => '', 'level' => 0, 'dateline' => 0, 'lock' => 1],
        10 => ['maplevel' => 10, 'challengercid' => 92, 'challengername' => '学园都市', 'challengerlevel' => 5, 'challengerdateline' => 1463555349, 'cid' => 0, 'name' => '', 'level' => 0, 'dateline' => 0, 'lock' => 1],
        11 => ['maplevel' => 11, 'challengercid' => 10, 'challengername' => 'The ', 'challengerlevel' => 5, 'challengerdateline' => 1459609385, 'cid' => 0, 'name' => '', 'level' => 0, 'dateline' => 0, 'lock' => 0],
        12 => ['maplevel' => 12, 'challengercid' => 114, 'challengername' => '活人来啊', 'challengerlevel' => 7, 'challengerdateline' => 1466400359, 'cid' => 0, 'name' => '', 'level' => 0, 'dateline' => 0, 'lock' => 1],
        13 => ['maplevel' => 13, 'challengercid' => 15, 'challengername' => '崽崽', 'challengerlevel' => 1, 'challengerdateline' => 1463018599, 'cid' => 0, 'name' => '', 'level' => 0, 'dateline' => 0, 'lock' => 1],
    ];
}
