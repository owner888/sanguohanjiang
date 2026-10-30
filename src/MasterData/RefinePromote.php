<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 精炼晋升表（旧版 zc_sanguo_refine_promote）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class RefinePromote
{
    public const string TABLE = 'zc_sanguo_refine_promote';

    /**
     * 无主键，按原顺序 list，共 99 行。字段：
     *   - level (int) 当前阶段精炼等级
     *   - percent (int) 对应强化加成（%）
     *   - jade (int) 消耗玉石
     *   - refinestone (int) 精炼石（100%掉落）
     *   - colorstone (int) 五彩石（30%掉落）
     *   - bloodstone (int) 上古血玉（20%掉落）
     *   - type (tinyint) 精炼类型
     */
    public const array ROWS = [
        ['level' => 1, 'percent' => 20, 'jade' => 5050, 'refinestone' => 80, 'colorstone' => 0, 'bloodstone' => 0, 'type' => 1],
        ['level' => 2, 'percent' => 25, 'jade' => 10100, 'refinestone' => 111, 'colorstone' => 0, 'bloodstone' => 0, 'type' => 1],
        ['level' => 3, 'percent' => 30, 'jade' => 15450, 'refinestone' => 144, 'colorstone' => 0, 'bloodstone' => 0, 'type' => 1],
        ['level' => 4, 'percent' => 35, 'jade' => 21100, 'refinestone' => 180, 'colorstone' => 0, 'bloodstone' => 0, 'type' => 1],
        ['level' => 5, 'percent' => 40, 'jade' => 27050, 'refinestone' => 223, 'colorstone' => 0, 'bloodstone' => 0, 'type' => 1],
        ['level' => 6, 'percent' => 45, 'jade' => 33300, 'refinestone' => 275, 'colorstone' => 0, 'bloodstone' => 0, 'type' => 1],
        ['level' => 7, 'percent' => 50, 'jade' => 39850, 'refinestone' => 339, 'colorstone' => 0, 'bloodstone' => 0, 'type' => 1],
        ['level' => 8, 'percent' => 55, 'jade' => 46700, 'refinestone' => 420, 'colorstone' => 0, 'bloodstone' => 0, 'type' => 1],
        ['level' => 9, 'percent' => 60, 'jade' => 53850, 'refinestone' => 523, 'colorstone' => 0, 'bloodstone' => 0, 'type' => 1],
        ['level' => 10, 'percent' => 100, 'jade' => 269250, 'refinestone' => 2615, 'colorstone' => 120, 'bloodstone' => 19, 'type' => 2],
        ['level' => 11, 'percent' => 105, 'jade' => 88750, 'refinestone' => 816, 'colorstone' => 129, 'bloodstone' => 0, 'type' => 2],
        ['level' => 12, 'percent' => 110, 'jade' => 116700, 'refinestone' => 1019, 'colorstone' => 140, 'bloodstone' => 0, 'type' => 2],
        ['level' => 13, 'percent' => 115, 'jade' => 145150, 'refinestone' => 1269, 'colorstone' => 153, 'bloodstone' => 0, 'type' => 2],
        ['level' => 14, 'percent' => 120, 'jade' => 174100, 'refinestone' => 1573, 'colorstone' => 168, 'bloodstone' => 0, 'type' => 2],
        ['level' => 15, 'percent' => 125, 'jade' => 203550, 'refinestone' => 1941, 'colorstone' => 186, 'bloodstone' => 0, 'type' => 2],
        ['level' => 16, 'percent' => 130, 'jade' => 233500, 'refinestone' => 2381, 'colorstone' => 207, 'bloodstone' => 0, 'type' => 2],
        ['level' => 17, 'percent' => 135, 'jade' => 263950, 'refinestone' => 2902, 'colorstone' => 231, 'bloodstone' => 0, 'type' => 2],
        ['level' => 18, 'percent' => 140, 'jade' => 294900, 'refinestone' => 3515, 'colorstone' => 259, 'bloodstone' => 0, 'type' => 2],
        ['level' => 19, 'percent' => 145, 'jade' => 326350, 'refinestone' => 4231, 'colorstone' => 292, 'bloodstone' => 0, 'type' => 2],
        ['level' => 20, 'percent' => 200, 'jade' => 1631750, 'refinestone' => 21155, 'colorstone' => 1460, 'bloodstone' => 65, 'type' => 3],
        ['level' => 21, 'percent' => 205, 'jade' => 430250, 'refinestone' => 6017, 'colorstone' => 373, 'bloodstone' => 122, 'type' => 3],
        ['level' => 22, 'percent' => 210, 'jade' => 502900, 'refinestone' => 7112, 'colorstone' => 422, 'bloodstone' => 143, 'type' => 3],
        ['level' => 23, 'percent' => 215, 'jade' => 576250, 'refinestone' => 8359, 'colorstone' => 478, 'bloodstone' => 160, 'type' => 3],
        ['level' => 24, 'percent' => 220, 'jade' => 650300, 'refinestone' => 9771, 'colorstone' => 541, 'bloodstone' => 176, 'type' => 3],
        ['level' => 25, 'percent' => 225, 'jade' => 725050, 'refinestone' => 11364, 'colorstone' => 612, 'bloodstone' => 193, 'type' => 3],
        ['level' => 26, 'percent' => 230, 'jade' => 800500, 'refinestone' => 13152, 'colorstone' => 691, 'bloodstone' => 211, 'type' => 3],
        ['level' => 27, 'percent' => 235, 'jade' => 876650, 'refinestone' => 15150, 'colorstone' => 780, 'bloodstone' => 230, 'type' => 3],
        ['level' => 28, 'percent' => 240, 'jade' => 953500, 'refinestone' => 17375, 'colorstone' => 878, 'bloodstone' => 250, 'type' => 3],
        ['level' => 29, 'percent' => 245, 'jade' => 1031050, 'refinestone' => 19844, 'colorstone' => 987, 'bloodstone' => 271, 'type' => 3],
        ['level' => 30, 'percent' => 300, 'jade' => 5155250, 'refinestone' => 99220, 'colorstone' => 4935, 'bloodstone' => 1084, 'type' => 4],
        ['level' => 31, 'percent' => 305, 'jade' => 1247550, 'refinestone' => 25583, 'colorstone' => 1240, 'bloodstone' => 316, 'type' => 4],
        ['level' => 32, 'percent' => 310, 'jade' => 1386700, 'refinestone' => 28890, 'colorstone' => 1385, 'bloodstone' => 340, 'type' => 4],
        ['level' => 33, 'percent' => 315, 'jade' => 1526750, 'refinestone' => 32514, 'colorstone' => 1544, 'bloodstone' => 366, 'type' => 4],
        ['level' => 34, 'percent' => 320, 'jade' => 1667700, 'refinestone' => 36474, 'colorstone' => 1718, 'bloodstone' => 393, 'type' => 4],
        ['level' => 35, 'percent' => 325, 'jade' => 1809550, 'refinestone' => 40792, 'colorstone' => 1907, 'bloodstone' => 421, 'type' => 4],
        ['level' => 36, 'percent' => 330, 'jade' => 1952300, 'refinestone' => 45488, 'colorstone' => 2113, 'bloodstone' => 450, 'type' => 4],
        ['level' => 37, 'percent' => 335, 'jade' => 2095950, 'refinestone' => 50583, 'colorstone' => 2336, 'bloodstone' => 481, 'type' => 4],
        ['level' => 38, 'percent' => 340, 'jade' => 2240500, 'refinestone' => 56100, 'colorstone' => 2578, 'bloodstone' => 513, 'type' => 4],
        ['level' => 39, 'percent' => 345, 'jade' => 2385950, 'refinestone' => 62062, 'colorstone' => 2839, 'bloodstone' => 547, 'type' => 4],
        ['level' => 40, 'percent' => 400, 'jade' => 11929750, 'refinestone' => 310310, 'colorstone' => 14195, 'bloodstone' => 2188, 'type' => 5],
        ['level' => 41, 'percent' => 405, 'jade' => 2758650, 'refinestone' => 75414, 'colorstone' => 3423, 'bloodstone' => 619, 'type' => 5],
        ['level' => 42, 'percent' => 410, 'jade' => 2986100, 'refinestone' => 82853, 'colorstone' => 3748, 'bloodstone' => 658, 'type' => 5],
        ['level' => 43, 'percent' => 415, 'jade' => 3214750, 'refinestone' => 90834, 'colorstone' => 4097, 'bloodstone' => 698, 'type' => 5],
        ['level' => 44, 'percent' => 420, 'jade' => 3444600, 'refinestone' => 99382, 'colorstone' => 4470, 'bloodstone' => 740, 'type' => 5],
        ['level' => 45, 'percent' => 425, 'jade' => 3675650, 'refinestone' => 108525, 'colorstone' => 4869, 'bloodstone' => 784, 'type' => 5],
        ['level' => 46, 'percent' => 430, 'jade' => 3907900, 'refinestone' => 118289, 'colorstone' => 5295, 'bloodstone' => 830, 'type' => 5],
        ['level' => 47, 'percent' => 435, 'jade' => 4141350, 'refinestone' => 128701, 'colorstone' => 5749, 'bloodstone' => 877, 'type' => 5],
        ['level' => 48, 'percent' => 440, 'jade' => 4376000, 'refinestone' => 139790, 'colorstone' => 6233, 'bloodstone' => 927, 'type' => 5],
        ['level' => 49, 'percent' => 445, 'jade' => 4611850, 'refinestone' => 151585, 'colorstone' => 6748, 'bloodstone' => 979, 'type' => 5],
        ['level' => 50, 'percent' => 500, 'jade' => 23059250, 'refinestone' => 757925, 'colorstone' => 33740, 'bloodstone' => 3916, 'type' => 6],
        ['level' => 51, 'percent' => 505, 'jade' => 5095950, 'refinestone' => 177410, 'colorstone' => 7874, 'bloodstone' => 1089, 'type' => 6],
        ['level' => 52, 'percent' => 510, 'jade' => 5344300, 'refinestone' => 191501, 'colorstone' => 8488, 'bloodstone' => 1148, 'type' => 6],
        ['level' => 53, 'percent' => 515, 'jade' => 5593950, 'refinestone' => 206419, 'colorstone' => 9138, 'bloodstone' => 1209, 'type' => 6],
        ['level' => 54, 'percent' => 520, 'jade' => 5844900, 'refinestone' => 222195, 'colorstone' => 9826, 'bloodstone' => 1273, 'type' => 6],
        ['level' => 55, 'percent' => 525, 'jade' => 6097150, 'refinestone' => 238863, 'colorstone' => 10552, 'bloodstone' => 1340, 'type' => 6],
        ['level' => 56, 'percent' => 530, 'jade' => 6350700, 'refinestone' => 256455, 'colorstone' => 11319, 'bloodstone' => 1409, 'type' => 6],
        ['level' => 57, 'percent' => 535, 'jade' => 6605550, 'refinestone' => 275004, 'colorstone' => 12127, 'bloodstone' => 1482, 'type' => 6],
        ['level' => 58, 'percent' => 540, 'jade' => 6861700, 'refinestone' => 294545, 'colorstone' => 12978, 'bloodstone' => 1557, 'type' => 6],
        ['level' => 59, 'percent' => 545, 'jade' => 7119150, 'refinestone' => 315113, 'colorstone' => 13874, 'bloodstone' => 1636, 'type' => 6],
        ['level' => 60, 'percent' => 600, 'jade' => 35595750, 'refinestone' => 1575565, 'colorstone' => 69370, 'bloodstone' => 6544, 'type' => 7],
        ['level' => 61, 'percent' => 605, 'jade' => 7648650, 'refinestone' => 359471, 'colorstone' => 15806, 'bloodstone' => 1804, 'type' => 7],
        ['level' => 62, 'percent' => 610, 'jade' => 7920900, 'refinestone' => 383334, 'colorstone' => 16845, 'bloodstone' => 1893, 'type' => 7],
        ['level' => 63, 'percent' => 615, 'jade' => 8194650, 'refinestone' => 408369, 'colorstone' => 17935, 'bloodstone' => 1986, 'type' => 7],
        ['level' => 64, 'percent' => 620, 'jade' => 8469900, 'refinestone' => 434613, 'colorstone' => 19078, 'bloodstone' => 2083, 'type' => 7],
        ['level' => 65, 'percent' => 625, 'jade' => 8746650, 'refinestone' => 462106, 'colorstone' => 20275, 'bloodstone' => 2185, 'type' => 7],
        ['level' => 66, 'percent' => 630, 'jade' => 9024900, 'refinestone' => 490886, 'colorstone' => 21528, 'bloodstone' => 2291, 'type' => 7],
        ['level' => 67, 'percent' => 635, 'jade' => 9304650, 'refinestone' => 520992, 'colorstone' => 22839, 'bloodstone' => 2401, 'type' => 7],
        ['level' => 68, 'percent' => 640, 'jade' => 9585900, 'refinestone' => 552465, 'colorstone' => 24209, 'bloodstone' => 2517, 'type' => 7],
        ['level' => 69, 'percent' => 645, 'jade' => 9868650, 'refinestone' => 585346, 'colorstone' => 25640, 'bloodstone' => 2638, 'type' => 7],
        ['level' => 70, 'percent' => 650, 'jade' => 10152900, 'refinestone' => 619676, 'colorstone' => 27134, 'bloodstone' => 2764, 'type' => 7],
        ['level' => 71, 'percent' => 655, 'jade' => 10438650, 'refinestone' => 655497, 'colorstone' => 28693, 'bloodstone' => 2896, 'type' => 7],
        ['level' => 72, 'percent' => 660, 'jade' => 10725900, 'refinestone' => 692852, 'colorstone' => 30319, 'bloodstone' => 3034, 'type' => 7],
        ['level' => 73, 'percent' => 665, 'jade' => 11014650, 'refinestone' => 731784, 'colorstone' => 32013, 'bloodstone' => 3178, 'type' => 7],
        ['level' => 74, 'percent' => 670, 'jade' => 11304900, 'refinestone' => 772336, 'colorstone' => 33778, 'bloodstone' => 3329, 'type' => 7],
        ['level' => 75, 'percent' => 675, 'jade' => 11596650, 'refinestone' => 814554, 'colorstone' => 35615, 'bloodstone' => 3487, 'type' => 7],
        ['level' => 76, 'percent' => 680, 'jade' => 11889900, 'refinestone' => 858482, 'colorstone' => 37527, 'bloodstone' => 3652, 'type' => 7],
        ['level' => 77, 'percent' => 685, 'jade' => 12184650, 'refinestone' => 904165, 'colorstone' => 39515, 'bloodstone' => 3826, 'type' => 7],
        ['level' => 78, 'percent' => 690, 'jade' => 12480900, 'refinestone' => 951650, 'colorstone' => 41581, 'bloodstone' => 4008, 'type' => 7],
        ['level' => 79, 'percent' => 695, 'jade' => 12778650, 'refinestone' => 1000984, 'colorstone' => 43728, 'bloodstone' => 4199, 'type' => 7],
        ['level' => 80, 'percent' => 700, 'jade' => 13077900, 'refinestone' => 1052214, 'colorstone' => 45957, 'bloodstone' => 4399, 'type' => 7],
        ['level' => 81, 'percent' => 705, 'jade' => 13378650, 'refinestone' => 1105388, 'colorstone' => 48271, 'bloodstone' => 4610, 'type' => 7],
        ['level' => 82, 'percent' => 710, 'jade' => 13680900, 'refinestone' => 1160555, 'colorstone' => 50671, 'bloodstone' => 4831, 'type' => 7],
        ['level' => 83, 'percent' => 715, 'jade' => 13984650, 'refinestone' => 1217764, 'colorstone' => 53160, 'bloodstone' => 5064, 'type' => 7],
        ['level' => 84, 'percent' => 720, 'jade' => 14289900, 'refinestone' => 1277064, 'colorstone' => 55740, 'bloodstone' => 5309, 'type' => 7],
        ['level' => 85, 'percent' => 725, 'jade' => 14596650, 'refinestone' => 1338507, 'colorstone' => 58413, 'bloodstone' => 5567, 'type' => 7],
        ['level' => 86, 'percent' => 730, 'jade' => 14904900, 'refinestone' => 1402143, 'colorstone' => 61181, 'bloodstone' => 5839, 'type' => 7],
        ['level' => 87, 'percent' => 735, 'jade' => 15214650, 'refinestone' => 1468023, 'colorstone' => 64047, 'bloodstone' => 6126, 'type' => 7],
        ['level' => 88, 'percent' => 740, 'jade' => 15525900, 'refinestone' => 1536200, 'colorstone' => 67013, 'bloodstone' => 6428, 'type' => 7],
        ['level' => 89, 'percent' => 745, 'jade' => 15838650, 'refinestone' => 1606727, 'colorstone' => 70081, 'bloodstone' => 6748, 'type' => 7],
        ['level' => 90, 'percent' => 750, 'jade' => 16152900, 'refinestone' => 1679657, 'colorstone' => 73254, 'bloodstone' => 7086, 'type' => 7],
        ['level' => 91, 'percent' => 755, 'jade' => 16468650, 'refinestone' => 1755044, 'colorstone' => 76533, 'bloodstone' => 7444, 'type' => 7],
        ['level' => 92, 'percent' => 760, 'jade' => 16785900, 'refinestone' => 1832943, 'colorstone' => 79922, 'bloodstone' => 7823, 'type' => 7],
        ['level' => 93, 'percent' => 765, 'jade' => 17104650, 'refinestone' => 1913409, 'colorstone' => 83422, 'bloodstone' => 8225, 'type' => 7],
        ['level' => 94, 'percent' => 770, 'jade' => 17424900, 'refinestone' => 1996497, 'colorstone' => 87036, 'bloodstone' => 8652, 'type' => 7],
        ['level' => 95, 'percent' => 775, 'jade' => 17746650, 'refinestone' => 2082265, 'colorstone' => 90767, 'bloodstone' => 9106, 'type' => 7],
        ['level' => 96, 'percent' => 780, 'jade' => 18069900, 'refinestone' => 2170769, 'colorstone' => 94617, 'bloodstone' => 9589, 'type' => 7],
        ['level' => 97, 'percent' => 785, 'jade' => 18394650, 'refinestone' => 2262066, 'colorstone' => 98588, 'bloodstone' => 10103, 'type' => 7],
        ['level' => 98, 'percent' => 790, 'jade' => 18720900, 'refinestone' => 2356215, 'colorstone' => 102683, 'bloodstone' => 10652, 'type' => 7],
        ['level' => 99, 'percent' => 795, 'jade' => 19048650, 'refinestone' => 2453275, 'colorstone' => 106905, 'bloodstone' => 11238, 'type' => 7],
    ];
}
