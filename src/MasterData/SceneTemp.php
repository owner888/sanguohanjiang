<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 场景（旧版 zc_sanguo_scene_temp）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class SceneTemp
{
    public const string TABLE = 'zc_sanguo_scene_temp';

    /**
     * 按主键 id 索引，共 94 行。字段：
     *   - id (tinyint)
     *   - name (varchar) 场景名称
     *   - openmethod (tinyint) 开启方法（1 推图、2 主公等级）
     *   - opencondition (tinyint) 开启条件（场景ID 或 主公等级）
     *   - level (tinyint) 势力ID
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '曲阳', 'openmethod' => 1, 'opencondition' => 0, 'level' => 1],
        2 => ['id' => 2, 'name' => '广宗', 'openmethod' => 1, 'opencondition' => 1, 'level' => 1],
        3 => ['id' => 3, 'name' => '巨鹿', 'openmethod' => 1, 'opencondition' => 2, 'level' => 1],
        4 => ['id' => 4, 'name' => '濮阳', 'openmethod' => 1, 'opencondition' => 3, 'level' => 1],
        5 => ['id' => 5, 'name' => '乌巢', 'openmethod' => 1, 'opencondition' => 4, 'level' => 2],
        6 => ['id' => 6, 'name' => '官渡', 'openmethod' => 1, 'opencondition' => 5, 'level' => 2],
        7 => ['id' => 7, 'name' => '壶关', 'openmethod' => 1, 'opencondition' => 6, 'level' => 2],
        8 => ['id' => 8, 'name' => '邺城', 'openmethod' => 1, 'opencondition' => 7, 'level' => 2],
        9 => ['id' => 9, 'name' => '蓟县', 'openmethod' => 1, 'opencondition' => 8, 'level' => 3],
        10 => ['id' => 10, 'name' => '界桥', 'openmethod' => 1, 'opencondition' => 9, 'level' => 3],
        11 => ['id' => 11, 'name' => '北平', 'openmethod' => 1, 'opencondition' => 10, 'level' => 3],
        12 => ['id' => 12, 'name' => '虎牢关', 'openmethod' => 1, 'opencondition' => 11, 'level' => 4],
        13 => ['id' => 13, 'name' => '洛阳', 'openmethod' => 1, 'opencondition' => 12, 'level' => 4],
        14 => ['id' => 14, 'name' => '函谷关', 'openmethod' => 1, 'opencondition' => 13, 'level' => 4],
        15 => ['id' => 15, 'name' => '关中古道', 'openmethod' => 1, 'opencondition' => 14, 'level' => 4],
        16 => ['id' => 16, 'name' => '潼关', 'openmethod' => 1, 'opencondition' => 15, 'level' => 4],
        17 => ['id' => 17, 'name' => '长安', 'openmethod' => 1, 'opencondition' => 16, 'level' => 4],
        18 => ['id' => 18, 'name' => '金城', 'openmethod' => 1, 'opencondition' => 17, 'level' => 5],
        19 => ['id' => 19, 'name' => '天水', 'openmethod' => 1, 'opencondition' => 18, 'level' => 5],
        20 => ['id' => 20, 'name' => '西凉古道', 'openmethod' => 1, 'opencondition' => 19, 'level' => 5],
        21 => ['id' => 21, 'name' => '武威', 'openmethod' => 1, 'opencondition' => 20, 'level' => 5],
        22 => ['id' => 22, 'name' => '新野', 'openmethod' => 1, 'opencondition' => 21, 'level' => 6],
        23 => ['id' => 23, 'name' => '樊城', 'openmethod' => 1, 'opencondition' => 22, 'level' => 6],
        24 => ['id' => 24, 'name' => '江夏', 'openmethod' => 1, 'opencondition' => 23, 'level' => 6],
        25 => ['id' => 25, 'name' => '襄阳', 'openmethod' => 1, 'opencondition' => 24, 'level' => 6],
        26 => ['id' => 26, 'name' => '汝南', 'openmethod' => 1, 'opencondition' => 25, 'level' => 7],
        27 => ['id' => 27, 'name' => '九江郡', 'openmethod' => 1, 'opencondition' => 26, 'level' => 7],
        28 => ['id' => 28, 'name' => '南阳郡', 'openmethod' => 1, 'opencondition' => 27, 'level' => 7],
        29 => ['id' => 29, 'name' => '寿春', 'openmethod' => 1, 'opencondition' => 28, 'level' => 7],
        30 => ['id' => 30, 'name' => '琅邪阳都', 'openmethod' => 1, 'opencondition' => 29, 'level' => 8],
        31 => ['id' => 31, 'name' => '小沛', 'openmethod' => 1, 'opencondition' => 30, 'level' => 8],
        32 => ['id' => 32, 'name' => '下邳', 'openmethod' => 1, 'opencondition' => 31, 'level' => 8],
        33 => ['id' => 33, 'name' => '白门楼', 'openmethod' => 1, 'opencondition' => 32, 'level' => 8],
        34 => ['id' => 34, 'name' => '谯郡', 'openmethod' => 1, 'opencondition' => 33, 'level' => 9],
        35 => ['id' => 35, 'name' => '山阳', 'openmethod' => 1, 'opencondition' => 34, 'level' => 9],
        36 => ['id' => 36, 'name' => '东平', 'openmethod' => 1, 'opencondition' => 35, 'level' => 9],
        37 => ['id' => 37, 'name' => '泰山', 'openmethod' => 1, 'opencondition' => 36, 'level' => 9],
        38 => ['id' => 38, 'name' => '宛城', 'openmethod' => 1, 'opencondition' => 37, 'level' => 9],
        39 => ['id' => 39, 'name' => '陈留', 'openmethod' => 1, 'opencondition' => 38, 'level' => 9],
        40 => ['id' => 40, 'name' => '许昌', 'openmethod' => 1, 'opencondition' => 39, 'level' => 9],
        41 => ['id' => 41, 'name' => '汉中', 'openmethod' => 1, 'opencondition' => 40, 'level' => 10],
        42 => ['id' => 42, 'name' => '绵竹关', 'openmethod' => 1, 'opencondition' => 41, 'level' => 10],
        43 => ['id' => 43, 'name' => '剑门关', 'openmethod' => 1, 'opencondition' => 42, 'level' => 10],
        44 => ['id' => 44, 'name' => '梓潼', 'openmethod' => 1, 'opencondition' => 43, 'level' => 10],
        45 => ['id' => 45, 'name' => '永安', 'openmethod' => 1, 'opencondition' => 44, 'level' => 10],
        46 => ['id' => 46, 'name' => '阳平关', 'openmethod' => 1, 'opencondition' => 45, 'level' => 10],
        47 => ['id' => 47, 'name' => '成都', 'openmethod' => 1, 'opencondition' => 46, 'level' => 10],
        48 => ['id' => 48, 'name' => '柴桑', 'openmethod' => 1, 'opencondition' => 47, 'level' => 11],
        49 => ['id' => 49, 'name' => '夷陵古战场', 'openmethod' => 1, 'opencondition' => 48, 'level' => 11],
        50 => ['id' => 50, 'name' => '会稽', 'openmethod' => 1, 'opencondition' => 49, 'level' => 11],
        51 => ['id' => 51, 'name' => '赤壁', 'openmethod' => 1, 'opencondition' => 50, 'level' => 11],
        52 => ['id' => 52, 'name' => '秣陵', 'openmethod' => 1, 'opencondition' => 51, 'level' => 11],
        53 => ['id' => 53, 'name' => '吴郡', 'openmethod' => 1, 'opencondition' => 52, 'level' => 11],
        54 => ['id' => 54, 'name' => '建业', 'openmethod' => 1, 'opencondition' => 53, 'level' => 11],
        55 => ['id' => 55, 'name' => '蛮军大营', 'openmethod' => 1, 'opencondition' => 54, 'level' => 12],
        56 => ['id' => 56, 'name' => '三江城', 'openmethod' => 1, 'opencondition' => 55, 'level' => 12],
        57 => ['id' => 57, 'name' => '泸水', 'openmethod' => 1, 'opencondition' => 56, 'level' => 12],
        58 => ['id' => 58, 'name' => '建宁', 'openmethod' => 1, 'opencondition' => 57, 'level' => 12],
        59 => ['id' => 59, 'name' => '乌戈国', 'openmethod' => 1, 'opencondition' => 58, 'level' => 12],
        60 => ['id' => 60, 'name' => '蛮王洞', 'openmethod' => 1, 'opencondition' => 59, 'level' => 12],
        61 => ['id' => 61, 'name' => '阴山', 'openmethod' => 1, 'opencondition' => 60, 'level' => 13],
        62 => ['id' => 62, 'name' => '漠南部', 'openmethod' => 1, 'opencondition' => 61, 'level' => 13],
        63 => ['id' => 63, 'name' => '涿涂山', 'openmethod' => 1, 'opencondition' => 62, 'level' => 13],
        64 => ['id' => 64, 'name' => '燕然山', 'openmethod' => 1, 'opencondition' => 63, 'level' => 13],
        65 => ['id' => 65, 'name' => '金徽山', 'openmethod' => 1, 'opencondition' => 64, 'level' => 13],
        66 => ['id' => 66, 'name' => '漠北部', 'openmethod' => 1, 'opencondition' => 65, 'level' => 13],
        67 => ['id' => 67, 'name' => '单于台', 'openmethod' => 1, 'opencondition' => 66, 'level' => 13],
        68 => ['id' => 68, 'name' => '匈奴王庭', 'openmethod' => 1, 'opencondition' => 67, 'level' => 13],
        69 => ['id' => 69, 'name' => '赤壁', 'openmethod' => 1, 'opencondition' => 68, 'level' => 14],
        70 => ['id' => 70, 'name' => '官渡', 'openmethod' => 1, 'opencondition' => 69, 'level' => 14],
        71 => ['id' => 71, 'name' => '渭南', 'openmethod' => 1, 'opencondition' => 70, 'level' => 14],
        72 => ['id' => 72, 'name' => '夷陵', 'openmethod' => 1, 'opencondition' => 71, 'level' => 14],
        73 => ['id' => 73, 'name' => '虎牢', 'openmethod' => 1, 'opencondition' => 72, 'level' => 14],
        74 => ['id' => 74, 'name' => '南宋', 'openmethod' => 1, 'opencondition' => 73, 'level' => 15],
        75 => ['id' => 75, 'name' => '大理', 'openmethod' => 1, 'opencondition' => 74, 'level' => 15],
        76 => ['id' => 76, 'name' => '西夏', 'openmethod' => 1, 'opencondition' => 75, 'level' => 15],
        77 => ['id' => 77, 'name' => '吐蕃', 'openmethod' => 1, 'opencondition' => 76, 'level' => 15],
        78 => ['id' => 78, 'name' => '辽', 'openmethod' => 1, 'opencondition' => 77, 'level' => 15],
        79 => ['id' => 79, 'name' => '金', 'openmethod' => 1, 'opencondition' => 78, 'level' => 15],
        80 => ['id' => 80, 'name' => '蒙古', 'openmethod' => 1, 'opencondition' => 79, 'level' => 15],
        81 => ['id' => 81, 'name' => '夏', 'openmethod' => 1, 'opencondition' => 80, 'level' => 16],
        82 => ['id' => 82, 'name' => '梁', 'openmethod' => 1, 'opencondition' => 81, 'level' => 16],
        83 => ['id' => 83, 'name' => '郑', 'openmethod' => 1, 'opencondition' => 82, 'level' => 16],
        84 => ['id' => 84, 'name' => '楚', 'openmethod' => 1, 'opencondition' => 83, 'level' => 16],
        85 => ['id' => 85, 'name' => '瓦岗', 'openmethod' => 1, 'opencondition' => 84, 'level' => 16],
        86 => ['id' => 86, 'name' => '隋', 'openmethod' => 1, 'opencondition' => 85, 'level' => 16],
        87 => ['id' => 87, 'name' => '唐', 'openmethod' => 1, 'opencondition' => 86, 'level' => 16],
        88 => ['id' => 88, 'name' => '韩', 'openmethod' => 1, 'opencondition' => 87, 'level' => 17],
        89 => ['id' => 89, 'name' => '燕', 'openmethod' => 1, 'opencondition' => 88, 'level' => 17],
        90 => ['id' => 90, 'name' => '魏', 'openmethod' => 1, 'opencondition' => 89, 'level' => 17],
        91 => ['id' => 91, 'name' => '楚', 'openmethod' => 1, 'opencondition' => 90, 'level' => 17],
        92 => ['id' => 92, 'name' => '赵', 'openmethod' => 1, 'opencondition' => 91, 'level' => 17],
        93 => ['id' => 93, 'name' => '齐', 'openmethod' => 1, 'opencondition' => 92, 'level' => 17],
        94 => ['id' => 94, 'name' => '秦', 'openmethod' => 1, 'opencondition' => 93, 'level' => 17],
    ];
}
