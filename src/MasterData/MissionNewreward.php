<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 通过奖励表（旧版 zc_sanguo_mission_newreward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class MissionNewreward
{
    public const string TABLE = 'zc_sanguo_mission_newreward';

    /**
     * 按主键 id 索引，共 94 行。字段：
     *   - id (int) 自增ID
     *   - levelid (int) 势力ID
     *   - missid (int) 关卡ID
     *   - mname (varchar) 关卡名称
     *   - re_type (int) 奖励类型
     *   - re_name (varchar) 奖励名称
     *   - re_num (int) 奖励数量
     *   - re_value (int) 奖励参数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'levelid' => 1, 'missid' => 10, 'mname' => '曲阳', 're_type' => 2, 're_name' => '银币', 're_num' => 200000, 're_value' => 0],
        2 => ['id' => 2, 'levelid' => 1, 'missid' => 20, 'mname' => '广宗', 're_type' => 10, 're_name' => '突飞卡', 're_num' => 20, 're_value' => 0],
        3 => ['id' => 3, 'levelid' => 1, 'missid' => 30, 'mname' => '巨鹿', 're_type' => 8, 're_name' => '玄黄藤甲', 're_num' => 1, 're_value' => 39],
        4 => ['id' => 4, 'levelid' => 1, 'missid' => 40, 'mname' => '濮阳', 're_type' => 8, 're_name' => '鱼鳞披风', 're_num' => 1, 're_value' => 40],
        5 => ['id' => 5, 'levelid' => 2, 'missid' => 50, 'mname' => '乌巢', 're_type' => 2, 're_name' => '银币', 're_num' => 300000, 're_value' => 0],
        6 => ['id' => 6, 'levelid' => 2, 'missid' => 60, 'mname' => '官渡', 're_type' => 10, 're_name' => '突飞卡', 're_num' => 30, 're_value' => 0],
        7 => ['id' => 7, 'levelid' => 2, 'missid' => 70, 'mname' => '壶关', 're_type' => 2, 're_name' => '银币', 're_num' => 400000, 're_value' => 0],
        8 => ['id' => 8, 'levelid' => 2, 'missid' => 80, 'mname' => '邺城', 're_type' => 10, 're_name' => '突飞卡', 're_num' => 40, 're_value' => 0],
        9 => ['id' => 9, 'levelid' => 3, 'missid' => 90, 'mname' => '蓟县', 're_type' => 2, 're_name' => '银币', 're_num' => 500000, 're_value' => 0],
        10 => ['id' => 10, 'levelid' => 3, 'missid' => 100, 'mname' => '界桥', 're_type' => 10, 're_name' => '突飞卡', 're_num' => 50, 're_value' => 0],
        11 => ['id' => 11, 'levelid' => 3, 'missid' => 110, 'mname' => '北平', 're_type' => 2, 're_name' => '银币', 're_num' => 500000, 're_value' => 0],
        12 => ['id' => 12, 'levelid' => 4, 'missid' => 120, 'mname' => '虎牢关', 're_type' => 10, 're_name' => '突飞卡', 're_num' => 60, 're_value' => 0],
        13 => ['id' => 13, 'levelid' => 4, 'missid' => 130, 'mname' => '洛阳', 're_type' => 2, 're_name' => '银币', 're_num' => 600000, 're_value' => 0],
        14 => ['id' => 14, 'levelid' => 4, 'missid' => 137, 'mname' => '函谷关', 're_type' => 5, 're_name' => '原魂', 're_num' => 500, 're_value' => 0],
        15 => ['id' => 15, 'levelid' => 4, 'missid' => 147, 'mname' => '关中古道', 're_type' => 10, 're_name' => '突飞卡', 're_num' => 70, 're_value' => 0],
        16 => ['id' => 16, 'levelid' => 4, 'missid' => 157, 'mname' => '潼关', 're_type' => 5, 're_name' => '原魂', 're_num' => 500, 're_value' => 0],
        17 => ['id' => 17, 'levelid' => 4, 'missid' => 167, 'mname' => '长安', 're_type' => 2, 're_name' => '银币', 're_num' => 650000, 're_value' => 0],
        18 => ['id' => 18, 'levelid' => 5, 'missid' => 177, 'mname' => '金城', 're_type' => 10, 're_name' => '突飞卡', 're_num' => 80, 're_value' => 0],
        19 => ['id' => 19, 'levelid' => 5, 'missid' => 187, 'mname' => '天水', 're_type' => 5, 're_name' => '原魂', 're_num' => 800, 're_value' => 0],
        20 => ['id' => 20, 'levelid' => 5, 'missid' => 197, 'mname' => '西凉古道', 're_type' => 10, 're_name' => '突飞卡', 're_num' => 100, 're_value' => 0],
        21 => ['id' => 21, 'levelid' => 5, 'missid' => 207, 'mname' => '武威', 're_type' => 2, 're_name' => '银币', 're_num' => 800000, 're_value' => 0],
        22 => ['id' => 22, 'levelid' => 6, 'missid' => 217, 'mname' => '新野', 're_type' => 5, 're_name' => '原魂', 're_num' => 1000, 're_value' => 0],
        23 => ['id' => 23, 'levelid' => 6, 'missid' => 227, 'mname' => '樊城', 're_type' => 6, 're_name' => '玉石', 're_num' => 50000, 're_value' => 0],
        24 => ['id' => 24, 'levelid' => 6, 'missid' => 237, 'mname' => '江夏', 're_type' => 29, 're_name' => '生命天赋卷轴', 're_num' => 25, 're_value' => 5],
        25 => ['id' => 25, 'levelid' => 6, 'missid' => 247, 'mname' => '襄阳', 're_type' => 2, 're_name' => '银币', 're_num' => 1000000, 're_value' => 0],
        26 => ['id' => 26, 'levelid' => 7, 'missid' => 257, 'mname' => '汝南', 're_type' => 5, 're_name' => '原魂', 're_num' => 1500, 're_value' => 0],
        27 => ['id' => 27, 'levelid' => 7, 'missid' => 267, 'mname' => '九江郡', 're_type' => 6, 're_name' => '玉石', 're_num' => 80000, 're_value' => 0],
        28 => ['id' => 28, 'levelid' => 7, 'missid' => 277, 'mname' => '南阳郡', 're_type' => 29, 're_name' => '生命天赋卷轴', 're_num' => 25, 're_value' => 10],
        29 => ['id' => 29, 'levelid' => 7, 'missid' => 287, 'mname' => '寿春', 're_type' => 2, 're_name' => '银币', 're_num' => 1200000, 're_value' => 0],
        30 => ['id' => 30, 'levelid' => 8, 'missid' => 297, 'mname' => '琅邪阳都', 're_type' => 6, 're_name' => '玉石', 're_num' => 100000, 're_value' => 0],
        31 => ['id' => 31, 'levelid' => 8, 'missid' => 307, 'mname' => '小沛', 're_type' => 5, 're_name' => '原魂', 're_num' => 2000, 're_value' => 0],
        32 => ['id' => 32, 'levelid' => 8, 'missid' => 317, 'mname' => '下邳', 're_type' => 29, 're_name' => '生命天赋卷轴', 're_num' => 25, 're_value' => 15],
        33 => ['id' => 33, 'levelid' => 8, 'missid' => 327, 'mname' => '白门楼', 're_type' => 2, 're_name' => '银币', 're_num' => 1500000, 're_value' => 0],
        34 => ['id' => 34, 'levelid' => 9, 'missid' => 337, 'mname' => '谯郡', 're_type' => 2, 're_name' => '银币', 're_num' => 2000000, 're_value' => 0],
        35 => ['id' => 35, 'levelid' => 9, 'missid' => 347, 'mname' => '山阳', 're_type' => 6, 're_name' => '玉石', 're_num' => 110000, 're_value' => 0],
        36 => ['id' => 36, 'levelid' => 9, 'missid' => 357, 'mname' => '东平', 're_type' => 5, 're_name' => '原魂', 're_num' => 2500, 're_value' => 0],
        37 => ['id' => 37, 'levelid' => 9, 'missid' => 367, 'mname' => '泰山', 're_type' => 10, 're_name' => '突飞卡', 're_num' => 120, 're_value' => 0],
        38 => ['id' => 38, 'levelid' => 9, 'missid' => 377, 'mname' => '宛城', 're_type' => 29, 're_name' => '生命天赋卷轴', 're_num' => 25, 're_value' => 20],
        39 => ['id' => 39, 'levelid' => 9, 'missid' => 387, 'mname' => '陈留', 're_type' => 2, 're_name' => '银币', 're_num' => 2500000, 're_value' => 0],
        40 => ['id' => 40, 'levelid' => 9, 'missid' => 397, 'mname' => '许昌', 're_type' => 1, 're_name' => '元宝', 're_num' => 100, 're_value' => 0],
        41 => ['id' => 41, 'levelid' => 10, 'missid' => 407, 'mname' => '汉中', 're_type' => 2, 're_name' => '银币', 're_num' => 2600000, 're_value' => 0],
        42 => ['id' => 42, 'levelid' => 10, 'missid' => 417, 'mname' => '绵竹关', 're_type' => 6, 're_name' => '玉石', 're_num' => 120000, 're_value' => 0],
        43 => ['id' => 43, 'levelid' => 10, 'missid' => 427, 'mname' => '剑门关', 're_type' => 5, 're_name' => '原魂', 're_num' => 2500, 're_value' => 0],
        44 => ['id' => 44, 'levelid' => 10, 'missid' => 437, 'mname' => '梓潼', 're_type' => 10, 're_name' => '突飞卡', 're_num' => 130, 're_value' => 0],
        45 => ['id' => 45, 'levelid' => 10, 'missid' => 447, 'mname' => '永安', 're_type' => 29, 're_name' => '生命天赋卷轴', 're_num' => 25, 're_value' => 20],
        46 => ['id' => 46, 'levelid' => 10, 'missid' => 457, 'mname' => '阳平关', 're_type' => 2, 're_name' => '银币', 're_num' => 2700000, 're_value' => 0],
        47 => ['id' => 47, 'levelid' => 10, 'missid' => 467, 'mname' => '成都', 're_type' => 1, 're_name' => '元宝', 're_num' => 200, 're_value' => 0],
        48 => ['id' => 48, 'levelid' => 11, 'missid' => 477, 'mname' => '柴桑', 're_type' => 2, 're_name' => '银币', 're_num' => 2800000, 're_value' => 0],
        49 => ['id' => 49, 'levelid' => 11, 'missid' => 487, 'mname' => '夷陵古战场', 're_type' => 6, 're_name' => '玉石', 're_num' => 150000, 're_value' => 0],
        50 => ['id' => 50, 'levelid' => 11, 'missid' => 497, 'mname' => '会稽', 're_type' => 5, 're_name' => '原魂', 're_num' => 3000, 're_value' => 0],
        51 => ['id' => 51, 'levelid' => 11, 'missid' => 507, 'mname' => '赤壁', 're_type' => 14, 're_name' => '精炼石', 're_num' => 5000, 're_value' => 0],
        52 => ['id' => 52, 'levelid' => 11, 'missid' => 517, 'mname' => '秣陵', 're_type' => 15, 're_name' => '五彩石', 're_num' => 200, 're_value' => 0],
        53 => ['id' => 53, 'levelid' => 11, 'missid' => 527, 'mname' => '吴郡', 're_type' => 9, 're_name' => '宝石碎片', 're_num' => 5000, 're_value' => 0],
        54 => ['id' => 54, 'levelid' => 11, 'missid' => 537, 'mname' => '建业', 're_type' => 1, 're_name' => '元宝', 're_num' => 300, 're_value' => 0],
        55 => ['id' => 55, 'levelid' => 12, 'missid' => 547, 'mname' => '蛮军大营', 're_type' => 2, 're_name' => '银币', 're_num' => 3000000, 're_value' => 0],
        56 => ['id' => 56, 'levelid' => 12, 'missid' => 557, 'mname' => '三江城', 're_type' => 9, 're_name' => '宝石碎片', 're_num' => 8000, 're_value' => 0],
        57 => ['id' => 57, 'levelid' => 12, 'missid' => 567, 'mname' => '泸水', 're_type' => 5, 're_name' => '原魂', 're_num' => 3500, 're_value' => 0],
        58 => ['id' => 58, 'levelid' => 12, 'missid' => 577, 'mname' => '建宁', 're_type' => 14, 're_name' => '精炼石', 're_num' => 5000, 're_value' => 0],
        59 => ['id' => 59, 'levelid' => 12, 'missid' => 587, 'mname' => '乌戈国', 're_type' => 15, 're_name' => '五彩石', 're_num' => 200, 're_value' => 0],
        60 => ['id' => 60, 'levelid' => 12, 'missid' => 597, 'mname' => '蛮王洞', 're_type' => 1, 're_name' => '元宝', 're_num' => 400, 're_value' => 0],
        61 => ['id' => 61, 'levelid' => 13, 'missid' => 607, 'mname' => '阴山', 're_type' => 6, 're_name' => '玉石', 're_num' => 200000, 're_value' => 0],
        62 => ['id' => 62, 'levelid' => 13, 'missid' => 617, 'mname' => '漠南部', 're_type' => 2, 're_name' => '银币', 're_num' => 3100000, 're_value' => 0],
        63 => ['id' => 63, 'levelid' => 13, 'missid' => 627, 'mname' => '涿涂山', 're_type' => 9, 're_name' => '宝石碎片', 're_num' => 9000, 're_value' => 0],
        64 => ['id' => 64, 'levelid' => 13, 'missid' => 637, 'mname' => '燕然山', 're_type' => 5, 're_name' => '原魂', 're_num' => 4000, 're_value' => 0],
        65 => ['id' => 65, 'levelid' => 13, 'missid' => 647, 'mname' => '金徽山', 're_type' => 14, 're_name' => '精炼石', 're_num' => 8000, 're_value' => 0],
        66 => ['id' => 66, 'levelid' => 13, 'missid' => 657, 'mname' => '漠北部', 're_type' => 15, 're_name' => '五彩石', 're_num' => 300, 're_value' => 0],
        67 => ['id' => 67, 'levelid' => 13, 'missid' => 667, 'mname' => '单于台', 're_type' => 2, 're_name' => '银币', 're_num' => 3200000, 're_value' => 0],
        68 => ['id' => 68, 'levelid' => 13, 'missid' => 677, 'mname' => '匈奴王庭', 're_type' => 1, 're_name' => '元宝', 're_num' => 400, 're_value' => 0],
        69 => ['id' => 69, 'levelid' => 14, 'missid' => 687, 'mname' => '赤壁', 're_type' => 14, 're_name' => '精炼石', 're_num' => 10000, 're_value' => 0],
        70 => ['id' => 70, 'levelid' => 14, 'missid' => 697, 'mname' => '官渡', 're_type' => 15, 're_name' => '五彩石', 're_num' => 500, 're_value' => 0],
        71 => ['id' => 71, 'levelid' => 14, 'missid' => 707, 'mname' => '渭南', 're_type' => 5, 're_name' => '原魂', 're_num' => 5000, 're_value' => 0],
        72 => ['id' => 72, 'levelid' => 14, 'missid' => 717, 'mname' => '夷陵', 're_type' => 9, 're_name' => '宝石碎片', 're_num' => 10000, 're_value' => 0],
        73 => ['id' => 73, 'levelid' => 14, 'missid' => 727, 'mname' => '虎牢', 're_type' => 29, 're_name' => '策攻天赋卷轴', 're_num' => 100, 're_value' => 28],
        74 => ['id' => 74, 'levelid' => 15, 'missid' => 737, 'mname' => '南宋', 're_type' => 2, 're_name' => '银币', 're_num' => 3300000, 're_value' => 0],
        75 => ['id' => 75, 'levelid' => 15, 'missid' => 747, 'mname' => '大理', 're_type' => 14, 're_name' => '精炼石', 're_num' => 15000, 're_value' => 0],
        76 => ['id' => 76, 'levelid' => 15, 'missid' => 757, 'mname' => '西夏', 're_type' => 15, 're_name' => '五彩石', 're_num' => 500, 're_value' => 0],
        77 => ['id' => 77, 'levelid' => 15, 'missid' => 767, 'mname' => '吐蕃', 're_type' => 16, 're_name' => '上古血玉', 're_num' => 200, 're_value' => 0],
        78 => ['id' => 78, 'levelid' => 15, 'missid' => 777, 'mname' => '辽', 're_type' => 5, 're_name' => '原魂', 're_num' => 5000, 're_value' => 0],
        79 => ['id' => 79, 'levelid' => 15, 'missid' => 787, 'mname' => '金', 're_type' => 9, 're_name' => '宝石碎片', 're_num' => 20000, 're_value' => 0],
        80 => ['id' => 80, 'levelid' => 15, 'missid' => 797, 'mname' => '蒙古', 're_type' => 1, 're_name' => '元宝', 're_num' => 1000, 're_value' => 0],
        81 => ['id' => 81, 'levelid' => 16, 'missid' => 807, 'mname' => '夏', 're_type' => 2, 're_name' => '银币', 're_num' => 3500000, 're_value' => 0],
        82 => ['id' => 82, 'levelid' => 16, 'missid' => 817, 'mname' => '梁', 're_type' => 14, 're_name' => '精炼石', 're_num' => 20000, 're_value' => 0],
        83 => ['id' => 83, 'levelid' => 16, 'missid' => 827, 'mname' => '郑', 're_type' => 15, 're_name' => '五彩石', 're_num' => 1000, 're_value' => 0],
        84 => ['id' => 84, 'levelid' => 16, 'missid' => 837, 'mname' => '楚', 're_type' => 16, 're_name' => '上古血玉', 're_num' => 500, 're_value' => 0],
        85 => ['id' => 85, 'levelid' => 16, 'missid' => 847, 'mname' => '瓦岗', 're_type' => 5, 're_name' => '原魂', 're_num' => 6000, 're_value' => 0],
        86 => ['id' => 86, 'levelid' => 16, 'missid' => 857, 'mname' => '隋', 're_type' => 9, 're_name' => '宝石碎片', 're_num' => 30000, 're_value' => 0],
        87 => ['id' => 87, 'levelid' => 16, 'missid' => 867, 'mname' => '唐', 're_type' => 1, 're_name' => '元宝', 're_num' => 2000, 're_value' => 0],
        88 => ['id' => 88, 'levelid' => 17, 'missid' => 877, 'mname' => '韩', 're_type' => 2, 're_name' => '银币', 're_num' => 10000000, 're_value' => 0],
        89 => ['id' => 89, 'levelid' => 17, 'missid' => 887, 'mname' => '燕', 're_type' => 9, 're_name' => '宝石碎片', 're_num' => 100000, 're_value' => 0],
        90 => ['id' => 90, 'levelid' => 17, 'missid' => 897, 'mname' => '魏', 're_type' => 5, 're_name' => '原魂', 're_num' => 20000, 're_value' => 0],
        91 => ['id' => 91, 'levelid' => 17, 'missid' => 907, 'mname' => '楚', 're_type' => 14, 're_name' => '精炼石', 're_num' => 40000, 're_value' => 0],
        92 => ['id' => 92, 'levelid' => 17, 'missid' => 917, 'mname' => '赵', 're_type' => 16, 're_name' => '上古血玉', 're_num' => 1000, 're_value' => 0],
        93 => ['id' => 93, 'levelid' => 17, 'missid' => 927, 'mname' => '齐', 're_type' => 29, 're_name' => '生命天赋卷轴', 're_num' => 150, 're_value' => 35],
        94 => ['id' => 94, 'levelid' => 17, 'missid' => 937, 'mname' => '秦', 're_type' => 1, 're_name' => '元宝', 're_num' => 3000, 're_value' => 0],
    ];
}
