<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 团队副本军团表（含奖励）（旧版 zc_sanguo_elite_city）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class EliteCity
{
    public const string TABLE = 'zc_sanguo_elite_city';

    /**
     * 按主键 id 索引，共 83 行。字段：
     *   - id (tinyint) 军团id
     *   - name (varchar) 军团名称
     *   - first_exp (int) 首杀经验
     *   - exp (mediumint) 奖励武将经验
     *   - first_type (int) 首杀类型
     *   - first_value (int) 首杀奖励
     *   - type (tinyint) 奖励类型（1装备，2装备碎片，3天赋卷轴，4天赋卷轴碎片）
     *   - get_value (int) 奖励id
     *   - get_rate (tinyint) 掉率
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '颜良军团', 'first_exp' => 14400, 'exp' => 2880, 'first_type' => 1, 'first_value' => 16, 'type' => 2, 'get_value' => 16, 'get_rate' => 80],
        2 => ['id' => 2, 'name' => '文丑军团', 'first_exp' => 16900, 'exp' => 3380, 'first_type' => 1, 'first_value' => 37, 'type' => 2, 'get_value' => 37, 'get_rate' => 80],
        3 => ['id' => 3, 'name' => '田丰军团', 'first_exp' => 19400, 'exp' => 3880, 'first_type' => 1, 'first_value' => 41, 'type' => 2, 'get_value' => 41, 'get_rate' => 80],
        4 => ['id' => 4, 'name' => '袁绍军团', 'first_exp' => 21900, 'exp' => 4380, 'first_type' => 1, 'first_value' => 25, 'type' => 2, 'get_value' => 25, 'get_rate' => 40],
        5 => ['id' => 5, 'name' => '公孙度军团', 'first_exp' => 24400, 'exp' => 4880, 'first_type' => 1, 'first_value' => 19, 'type' => 2, 'get_value' => 19, 'get_rate' => 40],
        6 => ['id' => 6, 'name' => '田予军团', 'first_exp' => 26900, 'exp' => 5380, 'first_type' => 1, 'first_value' => 19, 'type' => 2, 'get_value' => 19, 'get_rate' => 40],
        7 => ['id' => 7, 'name' => '公孙瓒军团', 'first_exp' => 30650, 'exp' => 6130, 'first_type' => 1, 'first_value' => 26, 'type' => 2, 'get_value' => 26, 'get_rate' => 40],
        8 => ['id' => 8, 'name' => '华雄军团', 'first_exp' => 34400, 'exp' => 6880, 'first_type' => 1, 'first_value' => 45, 'type' => 2, 'get_value' => 45, 'get_rate' => 40],
        9 => ['id' => 9, 'name' => '貂蝉军团', 'first_exp' => 38150, 'exp' => 7630, 'first_type' => 1, 'first_value' => 46, 'type' => 2, 'get_value' => 46, 'get_rate' => 40],
        10 => ['id' => 10, 'name' => '张绣军团', 'first_exp' => 41900, 'exp' => 8380, 'first_type' => 1, 'first_value' => 46, 'type' => 2, 'get_value' => 46, 'get_rate' => 40],
        11 => ['id' => 11, 'name' => '李儒军团', 'first_exp' => 45650, 'exp' => 9130, 'first_type' => 1, 'first_value' => 44, 'type' => 2, 'get_value' => 44, 'get_rate' => 40],
        12 => ['id' => 12, 'name' => '徐荣军团', 'first_exp' => 50650, 'exp' => 10130, 'first_type' => 1, 'first_value' => 44, 'type' => 2, 'get_value' => 44, 'get_rate' => 40],
        13 => ['id' => 13, 'name' => '董卓军团', 'first_exp' => 55650, 'exp' => 11130, 'first_type' => 1, 'first_value' => 47, 'type' => 2, 'get_value' => 47, 'get_rate' => 20],
        14 => ['id' => 14, 'name' => '马岱军团', 'first_exp' => 60650, 'exp' => 12130, 'first_type' => 1, 'first_value' => 48, 'type' => 2, 'get_value' => 48, 'get_rate' => 20],
        15 => ['id' => 15, 'name' => '庞德军团', 'first_exp' => 65650, 'exp' => 13130, 'first_type' => 1, 'first_value' => 28, 'type' => 2, 'get_value' => 28, 'get_rate' => 20],
        16 => ['id' => 16, 'name' => '韩遂军团', 'first_exp' => 70650, 'exp' => 14130, 'first_type' => 1, 'first_value' => 47, 'type' => 2, 'get_value' => 47, 'get_rate' => 20],
        17 => ['id' => 17, 'name' => '马腾军团', 'first_exp' => 76900, 'exp' => 15380, 'first_type' => 1, 'first_value' => 28, 'type' => 2, 'get_value' => 28, 'get_rate' => 20],
        18 => ['id' => 18, 'name' => '文聘军团', 'first_exp' => 83150, 'exp' => 16630, 'first_type' => 3, 'first_value' => 1, 'type' => 3, 'get_value' => 1, 'get_rate' => 100],
        19 => ['id' => 19, 'name' => '蒯越军团', 'first_exp' => 89400, 'exp' => 17880, 'first_type' => 3, 'first_value' => 2, 'type' => 3, 'get_value' => 2, 'get_rate' => 100],
        20 => ['id' => 20, 'name' => '黄忠军团', 'first_exp' => 95650, 'exp' => 19130, 'first_type' => 3, 'first_value' => 3, 'type' => 3, 'get_value' => 3, 'get_rate' => 100],
        21 => ['id' => 21, 'name' => '刘表军团', 'first_exp' => 101900, 'exp' => 20380, 'first_type' => 3, 'first_value' => 4, 'type' => 3, 'get_value' => 4, 'get_rate' => 100],
        22 => ['id' => 22, 'name' => '纪灵军团', 'first_exp' => 109400, 'exp' => 21880, 'first_type' => 3, 'first_value' => 6, 'type' => 3, 'get_value' => 6, 'get_rate' => 100],
        23 => ['id' => 23, 'name' => '孔融军团', 'first_exp' => 116900, 'exp' => 23380, 'first_type' => 3, 'first_value' => 7, 'type' => 3, 'get_value' => 7, 'get_rate' => 100],
        24 => ['id' => 24, 'name' => '孙坚军团', 'first_exp' => 124400, 'exp' => 24880, 'first_type' => 3, 'first_value' => 8, 'type' => 3, 'get_value' => 8, 'get_rate' => 100],
        25 => ['id' => 25, 'name' => '袁术军团', 'first_exp' => 131900, 'exp' => 26380, 'first_type' => 3, 'first_value' => 9, 'type' => 3, 'get_value' => 9, 'get_rate' => 100],
        26 => ['id' => 26, 'name' => '张辽军团', 'first_exp' => 139400, 'exp' => 27880, 'first_type' => 3, 'first_value' => 11, 'type' => 4, 'get_value' => 11, 'get_rate' => 100],
        27 => ['id' => 27, 'name' => '陈宫军团', 'first_exp' => 148150, 'exp' => 29630, 'first_type' => 3, 'first_value' => 12, 'type' => 4, 'get_value' => 12, 'get_rate' => 100],
        28 => ['id' => 28, 'name' => '高顺军团', 'first_exp' => 156900, 'exp' => 31380, 'first_type' => 3, 'first_value' => 13, 'type' => 4, 'get_value' => 13, 'get_rate' => 100],
        29 => ['id' => 29, 'name' => '吕布军团', 'first_exp' => 165650, 'exp' => 33130, 'first_type' => 3, 'first_value' => 14, 'type' => 4, 'get_value' => 14, 'get_rate' => 100],
        30 => ['id' => 30, 'name' => '贾诩军团', 'first_exp' => 174400, 'exp' => 34880, 'first_type' => 3, 'first_value' => 16, 'type' => 4, 'get_value' => 16, 'get_rate' => 100],
        31 => ['id' => 31, 'name' => '荀彧军团', 'first_exp' => 183150, 'exp' => 36630, 'first_type' => 3, 'first_value' => 17, 'type' => 4, 'get_value' => 17, 'get_rate' => 100],
        32 => ['id' => 32, 'name' => '典韦军团', 'first_exp' => 193150, 'exp' => 38630, 'first_type' => 3, 'first_value' => 18, 'type' => 4, 'get_value' => 18, 'get_rate' => 100],
        33 => ['id' => 33, 'name' => '夏侯惇军团', 'first_exp' => 203150, 'exp' => 40630, 'first_type' => 3, 'first_value' => 19, 'type' => 4, 'get_value' => 19, 'get_rate' => 100],
        34 => ['id' => 34, 'name' => '曹仁军团', 'first_exp' => 213150, 'exp' => 42630, 'first_type' => 3, 'first_value' => 20, 'type' => 4, 'get_value' => 20, 'get_rate' => 100],
        35 => ['id' => 35, 'name' => '司马炎军团', 'first_exp' => 223150, 'exp' => 44630, 'first_type' => 3, 'first_value' => 16, 'type' => 4, 'get_value' => 16, 'get_rate' => 100],
        36 => ['id' => 36, 'name' => '曹操军团', 'first_exp' => 233150, 'exp' => 46630, 'first_type' => 3, 'first_value' => 18, 'type' => 4, 'get_value' => 18, 'get_rate' => 100],
        37 => ['id' => 37, 'name' => '张鲁军团', 'first_exp' => 245650, 'exp' => 49130, 'first_type' => 3, 'first_value' => 21, 'type' => 4, 'get_value' => 21, 'get_rate' => 100],
        38 => ['id' => 38, 'name' => '刘璋军团', 'first_exp' => 258150, 'exp' => 51630, 'first_type' => 3, 'first_value' => 22, 'type' => 4, 'get_value' => 22, 'get_rate' => 100],
        39 => ['id' => 39, 'name' => '姜维军团', 'first_exp' => 270650, 'exp' => 54130, 'first_type' => 3, 'first_value' => 23, 'type' => 4, 'get_value' => 23, 'get_rate' => 100],
        40 => ['id' => 40, 'name' => '关羽军团', 'first_exp' => 283150, 'exp' => 56630, 'first_type' => 3, 'first_value' => 24, 'type' => 4, 'get_value' => 24, 'get_rate' => 100],
        41 => ['id' => 41, 'name' => '张飞军团', 'first_exp' => 295650, 'exp' => 59130, 'first_type' => 3, 'first_value' => 25, 'type' => 4, 'get_value' => 25, 'get_rate' => 100],
        42 => ['id' => 42, 'name' => '诸葛亮军团', 'first_exp' => 310650, 'exp' => 62130, 'first_type' => 3, 'first_value' => 21, 'type' => 4, 'get_value' => 21, 'get_rate' => 100],
        43 => ['id' => 43, 'name' => '刘备军团', 'first_exp' => 325650, 'exp' => 65130, 'first_type' => 3, 'first_value' => 23, 'type' => 4, 'get_value' => 23, 'get_rate' => 100],
        44 => ['id' => 44, 'name' => '甘宁军团', 'first_exp' => 340650, 'exp' => 68130, 'first_type' => 2, 'first_value' => 63, 'type' => 2, 'get_value' => 63, 'get_rate' => 1],
        45 => ['id' => 45, 'name' => '张昭军团', 'first_exp' => 355650, 'exp' => 71130, 'first_type' => 2, 'first_value' => 63, 'type' => 2, 'get_value' => 63, 'get_rate' => 1],
        46 => ['id' => 46, 'name' => '陆逊军团', 'first_exp' => 370650, 'exp' => 74130, 'first_type' => 2, 'first_value' => 66, 'type' => 2, 'get_value' => 66, 'get_rate' => 1],
        47 => ['id' => 47, 'name' => '周瑜军团', 'first_exp' => 388150, 'exp' => 77630, 'first_type' => 2, 'first_value' => 66, 'type' => 2, 'get_value' => 66, 'get_rate' => 1],
        48 => ['id' => 48, 'name' => '孙坚军团', 'first_exp' => 405650, 'exp' => 81130, 'first_type' => 2, 'first_value' => 57, 'type' => 2, 'get_value' => 57, 'get_rate' => 1],
        49 => ['id' => 49, 'name' => '孙策军团', 'first_exp' => 423150, 'exp' => 84630, 'first_type' => 2, 'first_value' => 57, 'type' => 2, 'get_value' => 57, 'get_rate' => 1],
        50 => ['id' => 50, 'name' => '孙权军团', 'first_exp' => 440650, 'exp' => 88130, 'first_type' => 2, 'first_value' => 60, 'type' => 2, 'get_value' => 60, 'get_rate' => 1],
        51 => ['id' => 51, 'name' => '带来军团', 'first_exp' => 458150, 'exp' => 91630, 'first_type' => 2, 'first_value' => 63, 'type' => 2, 'get_value' => 63, 'get_rate' => 1],
        52 => ['id' => 52, 'name' => '朵思军团', 'first_exp' => 478150, 'exp' => 95630, 'first_type' => 2, 'first_value' => 63, 'type' => 2, 'get_value' => 63, 'get_rate' => 1],
        53 => ['id' => 53, 'name' => '木鹿军团', 'first_exp' => 498150, 'exp' => 99630, 'first_type' => 2, 'first_value' => 60, 'type' => 2, 'get_value' => 60, 'get_rate' => 1],
        54 => ['id' => 54, 'name' => '祝融军团', 'first_exp' => 518150, 'exp' => 103630, 'first_type' => 2, 'first_value' => 60, 'type' => 2, 'get_value' => 60, 'get_rate' => 1],
        55 => ['id' => 55, 'name' => '兀突骨军团', 'first_exp' => 538150, 'exp' => 107630, 'first_type' => 2, 'first_value' => 57, 'type' => 2, 'get_value' => 57, 'get_rate' => 1],
        56 => ['id' => 56, 'name' => '孟获军团', 'first_exp' => 558150, 'exp' => 111630, 'first_type' => 2, 'first_value' => 66, 'type' => 2, 'get_value' => 66, 'get_rate' => 1],
        57 => ['id' => 57, 'name' => '匈奴军团', 'first_exp' => 580650, 'exp' => 116130, 'first_type' => 2, 'first_value' => 63, 'type' => 2, 'get_value' => 63, 'get_rate' => 1],
        58 => ['id' => 58, 'name' => '左贤王军团', 'first_exp' => 603150, 'exp' => 120630, 'first_type' => 2, 'first_value' => 63, 'type' => 2, 'get_value' => 63, 'get_rate' => 1],
        59 => ['id' => 59, 'name' => '右贤王军团', 'first_exp' => 625650, 'exp' => 125130, 'first_type' => 2, 'first_value' => 60, 'type' => 2, 'get_value' => 60, 'get_rate' => 1],
        60 => ['id' => 60, 'name' => '大单于军团', 'first_exp' => 648150, 'exp' => 129630, 'first_type' => 2, 'first_value' => 60, 'type' => 2, 'get_value' => 60, 'get_rate' => 1],
        61 => ['id' => 61, 'name' => '大单于军团', 'first_exp' => 670650, 'exp' => 134130, 'first_type' => 2, 'first_value' => 57, 'type' => 2, 'get_value' => 57, 'get_rate' => 1],
        62 => ['id' => 62, 'name' => '大汗军团', 'first_exp' => 695650, 'exp' => 139130, 'first_type' => 2, 'first_value' => 57, 'type' => 2, 'get_value' => 57, 'get_rate' => 1],
        63 => ['id' => 63, 'name' => '大汗军团', 'first_exp' => 720650, 'exp' => 144130, 'first_type' => 2, 'first_value' => 66, 'type' => 2, 'get_value' => 66, 'get_rate' => 1],
        64 => ['id' => 64, 'name' => '阿提拉军团', 'first_exp' => 745650, 'exp' => 149130, 'first_type' => 2, 'first_value' => 66, 'type' => 2, 'get_value' => 66, 'get_rate' => 1],
        65 => ['id' => 65, 'name' => '曹操军团', 'first_exp' => 770650, 'exp' => 154130, 'first_type' => 3, 'first_value' => 26, 'type' => 4, 'get_value' => 26, 'get_rate' => 100],
        66 => ['id' => 66, 'name' => '袁绍军团', 'first_exp' => 795650, 'exp' => 159130, 'first_type' => 3, 'first_value' => 27, 'type' => 4, 'get_value' => 27, 'get_rate' => 100],
        67 => ['id' => 67, 'name' => '马超军团', 'first_exp' => 820650, 'exp' => 164130, 'first_type' => 3, 'first_value' => 28, 'type' => 4, 'get_value' => 28, 'get_rate' => 100],
        68 => ['id' => 68, 'name' => '陆逊军团', 'first_exp' => 845650, 'exp' => 169130, 'first_type' => 3, 'first_value' => 29, 'type' => 4, 'get_value' => 29, 'get_rate' => 100],
        69 => ['id' => 69, 'name' => '吕布军团', 'first_exp' => 870650, 'exp' => 174130, 'first_type' => 3, 'first_value' => 30, 'type' => 4, 'get_value' => 30, 'get_rate' => 100],
        70 => ['id' => 70, 'name' => '南宋军团', 'first_exp' => 898150, 'exp' => 179630, 'first_type' => 3, 'first_value' => 26, 'type' => 4, 'get_value' => 26, 'get_rate' => 100],
        71 => ['id' => 71, 'name' => '大理军团', 'first_exp' => 925650, 'exp' => 185130, 'first_type' => 3, 'first_value' => 27, 'type' => 4, 'get_value' => 27, 'get_rate' => 100],
        72 => ['id' => 72, 'name' => '西夏军团', 'first_exp' => 953150, 'exp' => 190630, 'first_type' => 3, 'first_value' => 28, 'type' => 4, 'get_value' => 28, 'get_rate' => 100],
        73 => ['id' => 73, 'name' => '吐蕃军团', 'first_exp' => 980650, 'exp' => 196130, 'first_type' => 3, 'first_value' => 29, 'type' => 4, 'get_value' => 29, 'get_rate' => 100],
        74 => ['id' => 74, 'name' => '辽军军团', 'first_exp' => 1008150, 'exp' => 201630, 'first_type' => 3, 'first_value' => 30, 'type' => 4, 'get_value' => 30, 'get_rate' => 100],
        75 => ['id' => 75, 'name' => '金军军团', 'first_exp' => 1038150, 'exp' => 207630, 'first_type' => 3, 'first_value' => 26, 'type' => 4, 'get_value' => 26, 'get_rate' => 100],
        76 => ['id' => 76, 'name' => '蒙古军团', 'first_exp' => 1068150, 'exp' => 213630, 'first_type' => 3, 'first_value' => 27, 'type' => 4, 'get_value' => 27, 'get_rate' => 100],
        77 => ['id' => 77, 'name' => '窦建德军团', 'first_exp' => 1098150, 'exp' => 219630, 'first_type' => 3, 'first_value' => 28, 'type' => 4, 'get_value' => 28, 'get_rate' => 100],
        78 => ['id' => 78, 'name' => '梁师都军团', 'first_exp' => 1128150, 'exp' => 225630, 'first_type' => 3, 'first_value' => 29, 'type' => 4, 'get_value' => 29, 'get_rate' => 100],
        79 => ['id' => 79, 'name' => '王世充军团', 'first_exp' => 1158150, 'exp' => 231630, 'first_type' => 3, 'first_value' => 30, 'type' => 4, 'get_value' => 30, 'get_rate' => 100],
        80 => ['id' => 80, 'name' => '萧铣军团', 'first_exp' => 1190650, 'exp' => 238130, 'first_type' => 3, 'first_value' => 31, 'type' => 4, 'get_value' => 31, 'get_rate' => 100],
        81 => ['id' => 81, 'name' => '李密军团', 'first_exp' => 1223150, 'exp' => 244630, 'first_type' => 3, 'first_value' => 32, 'type' => 4, 'get_value' => 32, 'get_rate' => 100],
        82 => ['id' => 82, 'name' => '杨广军团', 'first_exp' => 1255650, 'exp' => 251130, 'first_type' => 3, 'first_value' => 33, 'type' => 4, 'get_value' => 33, 'get_rate' => 100],
        83 => ['id' => 83, 'name' => '李世民军团', 'first_exp' => 1288150, 'exp' => 257630, 'first_type' => 3, 'first_value' => 34, 'type' => 4, 'get_value' => 34, 'get_rate' => 100],
    ];
}
