<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 场景（旧版 zc_sanguo_scene）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Scene
{
    public const string TABLE = 'zc_sanguo_scene';

    /**
     * 按主键 id 索引，共 94 行。字段：
     *   - id (tinyint)
     *   - name (varchar) 场景名称
     *   - openmethod (tinyint) 开启方法（1 推图、2 主公等级）
     *   - opencondition (tinyint) 开启条件（场景ID 或 主公等级）
     *   - level (tinyint) 势力ID
     *   - stage (tinyint) 第几个场景
     *   - x (smallint) x坐标
     *   - y (smallint) y坐标
     *   - type (tinyint) 场景类型（1县城，2古战场，3主城，4关隘，5粮仓，6首都）
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '曲阳', 'openmethod' => 1, 'opencondition' => 0, 'level' => 1, 'stage' => 1, 'x' => 0, 'y' => 0, 'type' => 0],
        2 => ['id' => 2, 'name' => '广宗', 'openmethod' => 1, 'opencondition' => 1, 'level' => 1, 'stage' => 2, 'x' => 0, 'y' => 0, 'type' => 0],
        3 => ['id' => 3, 'name' => '巨鹿', 'openmethod' => 1, 'opencondition' => 2, 'level' => 1, 'stage' => 3, 'x' => 0, 'y' => 0, 'type' => 0],
        4 => ['id' => 4, 'name' => '濮阳', 'openmethod' => 1, 'opencondition' => 3, 'level' => 1, 'stage' => 4, 'x' => 0, 'y' => 0, 'type' => 0],
        5 => ['id' => 5, 'name' => '乌巢', 'openmethod' => 1, 'opencondition' => 4, 'level' => 2, 'stage' => 1, 'x' => 0, 'y' => 0, 'type' => 0],
        6 => ['id' => 6, 'name' => '官渡', 'openmethod' => 1, 'opencondition' => 5, 'level' => 2, 'stage' => 2, 'x' => 0, 'y' => 0, 'type' => 0],
        7 => ['id' => 7, 'name' => '壶关', 'openmethod' => 1, 'opencondition' => 6, 'level' => 2, 'stage' => 3, 'x' => 0, 'y' => 0, 'type' => 0],
        8 => ['id' => 8, 'name' => '邺城', 'openmethod' => 1, 'opencondition' => 7, 'level' => 2, 'stage' => 4, 'x' => 0, 'y' => 0, 'type' => 0],
        9 => ['id' => 9, 'name' => '蓟县', 'openmethod' => 1, 'opencondition' => 8, 'level' => 3, 'stage' => 1, 'x' => 0, 'y' => 0, 'type' => 0],
        10 => ['id' => 10, 'name' => '界桥', 'openmethod' => 1, 'opencondition' => 9, 'level' => 3, 'stage' => 2, 'x' => 0, 'y' => 0, 'type' => 0],
        11 => ['id' => 11, 'name' => '北平', 'openmethod' => 1, 'opencondition' => 10, 'level' => 3, 'stage' => 3, 'x' => 0, 'y' => 0, 'type' => 0],
        12 => ['id' => 12, 'name' => '虎牢关', 'openmethod' => 1, 'opencondition' => 11, 'level' => 4, 'stage' => 1, 'x' => 0, 'y' => 0, 'type' => 0],
        13 => ['id' => 13, 'name' => '洛阳', 'openmethod' => 1, 'opencondition' => 12, 'level' => 4, 'stage' => 2, 'x' => 0, 'y' => 0, 'type' => 0],
        14 => ['id' => 14, 'name' => '函谷关', 'openmethod' => 1, 'opencondition' => 13, 'level' => 4, 'stage' => 3, 'x' => 0, 'y' => 0, 'type' => 0],
        15 => ['id' => 15, 'name' => '关中古道', 'openmethod' => 1, 'opencondition' => 14, 'level' => 4, 'stage' => 4, 'x' => 0, 'y' => 0, 'type' => 0],
        16 => ['id' => 16, 'name' => '潼关', 'openmethod' => 1, 'opencondition' => 15, 'level' => 4, 'stage' => 5, 'x' => 0, 'y' => 0, 'type' => 0],
        17 => ['id' => 17, 'name' => '长安', 'openmethod' => 1, 'opencondition' => 16, 'level' => 4, 'stage' => 6, 'x' => 0, 'y' => 0, 'type' => 0],
        18 => ['id' => 18, 'name' => '金城', 'openmethod' => 1, 'opencondition' => 17, 'level' => 5, 'stage' => 1, 'x' => 0, 'y' => 0, 'type' => 0],
        19 => ['id' => 19, 'name' => '天水', 'openmethod' => 1, 'opencondition' => 18, 'level' => 5, 'stage' => 2, 'x' => 0, 'y' => 0, 'type' => 0],
        20 => ['id' => 20, 'name' => '西凉古道', 'openmethod' => 1, 'opencondition' => 19, 'level' => 5, 'stage' => 3, 'x' => 0, 'y' => 0, 'type' => 0],
        21 => ['id' => 21, 'name' => '武威', 'openmethod' => 1, 'opencondition' => 20, 'level' => 5, 'stage' => 4, 'x' => 0, 'y' => 0, 'type' => 0],
        22 => ['id' => 22, 'name' => '新野', 'openmethod' => 1, 'opencondition' => 21, 'level' => 6, 'stage' => 1, 'x' => 0, 'y' => 0, 'type' => 0],
        23 => ['id' => 23, 'name' => '樊城', 'openmethod' => 1, 'opencondition' => 22, 'level' => 6, 'stage' => 2, 'x' => 0, 'y' => 0, 'type' => 0],
        24 => ['id' => 24, 'name' => '江夏', 'openmethod' => 1, 'opencondition' => 23, 'level' => 6, 'stage' => 3, 'x' => 0, 'y' => 0, 'type' => 0],
        25 => ['id' => 25, 'name' => '襄阳', 'openmethod' => 1, 'opencondition' => 24, 'level' => 6, 'stage' => 4, 'x' => 0, 'y' => 0, 'type' => 0],
        26 => ['id' => 26, 'name' => '汝南', 'openmethod' => 1, 'opencondition' => 25, 'level' => 7, 'stage' => 1, 'x' => 0, 'y' => 0, 'type' => 0],
        27 => ['id' => 27, 'name' => '九江郡', 'openmethod' => 1, 'opencondition' => 26, 'level' => 7, 'stage' => 2, 'x' => 0, 'y' => 0, 'type' => 0],
        28 => ['id' => 28, 'name' => '南阳郡', 'openmethod' => 1, 'opencondition' => 27, 'level' => 7, 'stage' => 3, 'x' => 0, 'y' => 0, 'type' => 0],
        29 => ['id' => 29, 'name' => '寿春', 'openmethod' => 1, 'opencondition' => 28, 'level' => 7, 'stage' => 4, 'x' => 0, 'y' => 0, 'type' => 0],
        30 => ['id' => 30, 'name' => '琅邪阳都', 'openmethod' => 1, 'opencondition' => 29, 'level' => 8, 'stage' => 1, 'x' => 0, 'y' => 0, 'type' => 0],
        31 => ['id' => 31, 'name' => '小沛', 'openmethod' => 1, 'opencondition' => 30, 'level' => 8, 'stage' => 2, 'x' => 0, 'y' => 0, 'type' => 0],
        32 => ['id' => 32, 'name' => '下邳', 'openmethod' => 1, 'opencondition' => 31, 'level' => 8, 'stage' => 3, 'x' => 0, 'y' => 0, 'type' => 0],
        33 => ['id' => 33, 'name' => '白门楼', 'openmethod' => 1, 'opencondition' => 32, 'level' => 8, 'stage' => 4, 'x' => 0, 'y' => 0, 'type' => 0],
        34 => ['id' => 34, 'name' => '谯郡', 'openmethod' => 1, 'opencondition' => 33, 'level' => 9, 'stage' => 1, 'x' => 0, 'y' => 0, 'type' => 0],
        35 => ['id' => 35, 'name' => '山阳', 'openmethod' => 1, 'opencondition' => 34, 'level' => 9, 'stage' => 2, 'x' => 0, 'y' => 0, 'type' => 0],
        36 => ['id' => 36, 'name' => '东平', 'openmethod' => 1, 'opencondition' => 35, 'level' => 9, 'stage' => 3, 'x' => 0, 'y' => 0, 'type' => 0],
        37 => ['id' => 37, 'name' => '泰山', 'openmethod' => 1, 'opencondition' => 36, 'level' => 9, 'stage' => 4, 'x' => 0, 'y' => 0, 'type' => 0],
        38 => ['id' => 38, 'name' => '宛城', 'openmethod' => 1, 'opencondition' => 37, 'level' => 9, 'stage' => 5, 'x' => 0, 'y' => 0, 'type' => 0],
        39 => ['id' => 39, 'name' => '陈留', 'openmethod' => 1, 'opencondition' => 38, 'level' => 9, 'stage' => 6, 'x' => 0, 'y' => 0, 'type' => 0],
        40 => ['id' => 40, 'name' => '许昌', 'openmethod' => 1, 'opencondition' => 39, 'level' => 9, 'stage' => 7, 'x' => 0, 'y' => 0, 'type' => 0],
        41 => ['id' => 41, 'name' => '汉中', 'openmethod' => 1, 'opencondition' => 40, 'level' => 10, 'stage' => 1, 'x' => 0, 'y' => 0, 'type' => 0],
        42 => ['id' => 42, 'name' => '绵竹关', 'openmethod' => 1, 'opencondition' => 41, 'level' => 10, 'stage' => 2, 'x' => 0, 'y' => 0, 'type' => 0],
        43 => ['id' => 43, 'name' => '剑门关', 'openmethod' => 1, 'opencondition' => 42, 'level' => 10, 'stage' => 3, 'x' => 0, 'y' => 0, 'type' => 0],
        44 => ['id' => 44, 'name' => '梓潼', 'openmethod' => 1, 'opencondition' => 43, 'level' => 10, 'stage' => 4, 'x' => 0, 'y' => 0, 'type' => 0],
        45 => ['id' => 45, 'name' => '永安', 'openmethod' => 1, 'opencondition' => 44, 'level' => 10, 'stage' => 5, 'x' => 0, 'y' => 0, 'type' => 0],
        46 => ['id' => 46, 'name' => '阳平关', 'openmethod' => 1, 'opencondition' => 45, 'level' => 10, 'stage' => 6, 'x' => 0, 'y' => 0, 'type' => 0],
        47 => ['id' => 47, 'name' => '成都', 'openmethod' => 1, 'opencondition' => 46, 'level' => 10, 'stage' => 7, 'x' => 0, 'y' => 0, 'type' => 0],
        48 => ['id' => 48, 'name' => '柴桑', 'openmethod' => 1, 'opencondition' => 47, 'level' => 11, 'stage' => 1, 'x' => 0, 'y' => 0, 'type' => 0],
        49 => ['id' => 49, 'name' => '夷陵古战场', 'openmethod' => 1, 'opencondition' => 48, 'level' => 11, 'stage' => 2, 'x' => 0, 'y' => 0, 'type' => 0],
        50 => ['id' => 50, 'name' => '会稽', 'openmethod' => 1, 'opencondition' => 49, 'level' => 11, 'stage' => 3, 'x' => 0, 'y' => 0, 'type' => 0],
        51 => ['id' => 51, 'name' => '赤壁', 'openmethod' => 1, 'opencondition' => 50, 'level' => 11, 'stage' => 4, 'x' => 0, 'y' => 0, 'type' => 0],
        52 => ['id' => 52, 'name' => '秣陵', 'openmethod' => 1, 'opencondition' => 51, 'level' => 11, 'stage' => 5, 'x' => 0, 'y' => 0, 'type' => 0],
        53 => ['id' => 53, 'name' => '吴郡', 'openmethod' => 1, 'opencondition' => 52, 'level' => 11, 'stage' => 6, 'x' => 0, 'y' => 0, 'type' => 0],
        54 => ['id' => 54, 'name' => '建业', 'openmethod' => 1, 'opencondition' => 53, 'level' => 11, 'stage' => 7, 'x' => 0, 'y' => 0, 'type' => 0],
        55 => ['id' => 55, 'name' => '蛮军大营', 'openmethod' => 1, 'opencondition' => 54, 'level' => 12, 'stage' => 1, 'x' => 0, 'y' => 0, 'type' => 0],
        56 => ['id' => 56, 'name' => '三江城', 'openmethod' => 1, 'opencondition' => 55, 'level' => 12, 'stage' => 2, 'x' => 0, 'y' => 0, 'type' => 0],
        57 => ['id' => 57, 'name' => '泸水', 'openmethod' => 1, 'opencondition' => 56, 'level' => 12, 'stage' => 3, 'x' => 0, 'y' => 0, 'type' => 0],
        58 => ['id' => 58, 'name' => '建宁', 'openmethod' => 1, 'opencondition' => 57, 'level' => 12, 'stage' => 4, 'x' => 0, 'y' => 0, 'type' => 0],
        59 => ['id' => 59, 'name' => '乌戈国', 'openmethod' => 1, 'opencondition' => 58, 'level' => 12, 'stage' => 5, 'x' => 0, 'y' => 0, 'type' => 0],
        60 => ['id' => 60, 'name' => '蛮王洞', 'openmethod' => 1, 'opencondition' => 59, 'level' => 12, 'stage' => 6, 'x' => 0, 'y' => 0, 'type' => 0],
        61 => ['id' => 61, 'name' => '阴山', 'openmethod' => 1, 'opencondition' => 60, 'level' => 13, 'stage' => 1, 'x' => 0, 'y' => 0, 'type' => 0],
        62 => ['id' => 62, 'name' => '漠南部', 'openmethod' => 1, 'opencondition' => 61, 'level' => 13, 'stage' => 2, 'x' => 0, 'y' => 0, 'type' => 0],
        63 => ['id' => 63, 'name' => '涿涂山', 'openmethod' => 1, 'opencondition' => 62, 'level' => 13, 'stage' => 3, 'x' => 0, 'y' => 0, 'type' => 0],
        64 => ['id' => 64, 'name' => '燕然山', 'openmethod' => 1, 'opencondition' => 63, 'level' => 13, 'stage' => 4, 'x' => 0, 'y' => 0, 'type' => 0],
        65 => ['id' => 65, 'name' => '金徽山', 'openmethod' => 1, 'opencondition' => 64, 'level' => 13, 'stage' => 5, 'x' => 0, 'y' => 0, 'type' => 0],
        66 => ['id' => 66, 'name' => '漠北部', 'openmethod' => 1, 'opencondition' => 65, 'level' => 13, 'stage' => 6, 'x' => 0, 'y' => 0, 'type' => 0],
        67 => ['id' => 67, 'name' => '单于台', 'openmethod' => 1, 'opencondition' => 66, 'level' => 13, 'stage' => 7, 'x' => 0, 'y' => 0, 'type' => 0],
        68 => ['id' => 68, 'name' => '匈奴王庭', 'openmethod' => 1, 'opencondition' => 67, 'level' => 13, 'stage' => 8, 'x' => 0, 'y' => 0, 'type' => 0],
        69 => ['id' => 69, 'name' => '赤壁', 'openmethod' => 1, 'opencondition' => 68, 'level' => 14, 'stage' => 1, 'x' => 0, 'y' => 0, 'type' => 0],
        70 => ['id' => 70, 'name' => '官渡', 'openmethod' => 1, 'opencondition' => 69, 'level' => 14, 'stage' => 2, 'x' => 0, 'y' => 0, 'type' => 0],
        71 => ['id' => 71, 'name' => '渭南', 'openmethod' => 1, 'opencondition' => 70, 'level' => 14, 'stage' => 3, 'x' => 0, 'y' => 0, 'type' => 0],
        72 => ['id' => 72, 'name' => '夷陵', 'openmethod' => 1, 'opencondition' => 71, 'level' => 14, 'stage' => 4, 'x' => 0, 'y' => 0, 'type' => 0],
        73 => ['id' => 73, 'name' => '虎牢', 'openmethod' => 1, 'opencondition' => 72, 'level' => 14, 'stage' => 5, 'x' => 0, 'y' => 0, 'type' => 0],
        74 => ['id' => 74, 'name' => '南宋', 'openmethod' => 1, 'opencondition' => 73, 'level' => 15, 'stage' => 1, 'x' => 0, 'y' => 0, 'type' => 0],
        75 => ['id' => 75, 'name' => '大理', 'openmethod' => 1, 'opencondition' => 74, 'level' => 15, 'stage' => 2, 'x' => 0, 'y' => 0, 'type' => 0],
        76 => ['id' => 76, 'name' => '西夏', 'openmethod' => 1, 'opencondition' => 75, 'level' => 15, 'stage' => 3, 'x' => 0, 'y' => 0, 'type' => 0],
        77 => ['id' => 77, 'name' => '吐蕃', 'openmethod' => 1, 'opencondition' => 76, 'level' => 15, 'stage' => 4, 'x' => 0, 'y' => 0, 'type' => 0],
        78 => ['id' => 78, 'name' => '辽', 'openmethod' => 1, 'opencondition' => 77, 'level' => 15, 'stage' => 5, 'x' => 0, 'y' => 0, 'type' => 0],
        79 => ['id' => 79, 'name' => '金', 'openmethod' => 1, 'opencondition' => 78, 'level' => 15, 'stage' => 6, 'x' => 0, 'y' => 0, 'type' => 0],
        80 => ['id' => 80, 'name' => '蒙古', 'openmethod' => 1, 'opencondition' => 79, 'level' => 15, 'stage' => 7, 'x' => 0, 'y' => 0, 'type' => 0],
        81 => ['id' => 81, 'name' => '夏', 'openmethod' => 1, 'opencondition' => 80, 'level' => 16, 'stage' => 1, 'x' => 0, 'y' => 0, 'type' => 0],
        82 => ['id' => 82, 'name' => '梁', 'openmethod' => 1, 'opencondition' => 81, 'level' => 16, 'stage' => 2, 'x' => 0, 'y' => 0, 'type' => 0],
        83 => ['id' => 83, 'name' => '郑', 'openmethod' => 1, 'opencondition' => 82, 'level' => 16, 'stage' => 3, 'x' => 0, 'y' => 0, 'type' => 0],
        84 => ['id' => 84, 'name' => '楚', 'openmethod' => 1, 'opencondition' => 83, 'level' => 16, 'stage' => 4, 'x' => 0, 'y' => 0, 'type' => 0],
        85 => ['id' => 85, 'name' => '瓦岗', 'openmethod' => 1, 'opencondition' => 84, 'level' => 16, 'stage' => 5, 'x' => 0, 'y' => 0, 'type' => 0],
        86 => ['id' => 86, 'name' => '隋', 'openmethod' => 1, 'opencondition' => 85, 'level' => 16, 'stage' => 6, 'x' => 0, 'y' => 0, 'type' => 0],
        87 => ['id' => 87, 'name' => '唐', 'openmethod' => 1, 'opencondition' => 86, 'level' => 16, 'stage' => 7, 'x' => 0, 'y' => 0, 'type' => 0],
        88 => ['id' => 88, 'name' => '韩', 'openmethod' => 1, 'opencondition' => 87, 'level' => 17, 'stage' => 1, 'x' => 0, 'y' => 0, 'type' => 1],
        89 => ['id' => 89, 'name' => '燕', 'openmethod' => 1, 'opencondition' => 88, 'level' => 17, 'stage' => 2, 'x' => 0, 'y' => 0, 'type' => 1],
        90 => ['id' => 90, 'name' => '魏', 'openmethod' => 1, 'opencondition' => 89, 'level' => 17, 'stage' => 3, 'x' => 0, 'y' => 0, 'type' => 1],
        91 => ['id' => 91, 'name' => '楚', 'openmethod' => 1, 'opencondition' => 90, 'level' => 17, 'stage' => 4, 'x' => 0, 'y' => 0, 'type' => 1],
        92 => ['id' => 92, 'name' => '赵', 'openmethod' => 1, 'opencondition' => 91, 'level' => 17, 'stage' => 5, 'x' => 0, 'y' => 0, 'type' => 1],
        93 => ['id' => 93, 'name' => '齐', 'openmethod' => 1, 'opencondition' => 92, 'level' => 17, 'stage' => 6, 'x' => 0, 'y' => 0, 'type' => 1],
        94 => ['id' => 94, 'name' => '秦', 'openmethod' => 1, 'opencondition' => 93, 'level' => 17, 'stage' => 7, 'x' => 0, 'y' => 0, 'type' => 1],
    ];
}
