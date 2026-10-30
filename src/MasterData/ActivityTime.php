<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 活动表（旧版 zc_sanguo_activity_time）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActivityTime
{
    public const string TABLE = 'zc_sanguo_activity_time';

    /**
     * 按主键 id 索引，共 67 行。字段：
     *   - id (tinyint)
     *   - name (varchar) sacrifice-天神祭祀; exercise-整军演武
     *   - starttime (int)
     *   - endtime (int)
     *   - version (int) 版本
     *   - type (tinyint) 活动类型（1春节活动）
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => 'sacrifice', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 21, 'type' => 0],
        2 => ['id' => 2, 'name' => 'exercise', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 14, 'type' => 0],
        3 => ['id' => 3, 'name' => 'treasuremap', 'starttime' => 0, 'endtime' => 0, 'version' => 1, 'type' => 0],
        4 => ['id' => 4, 'name' => 'general_onsale', 'starttime' => 0, 'endtime' => 0, 'version' => 9, 'type' => 1],
        5 => ['id' => 5, 'name' => 'flop', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 11, 'type' => 0],
        6 => ['id' => 6, 'name' => 'dailysign', 'starttime' => 0, 'endtime' => 0, 'version' => 2, 'type' => 0],
        7 => ['id' => 7, 'name' => 'mooncake', 'starttime' => 0, 'endtime' => 0, 'version' => 1, 'type' => 0],
        8 => ['id' => 8, 'name' => 'rebate', 'starttime' => 0, 'endtime' => 0, 'version' => 1, 'type' => 0],
        9 => ['id' => 9, 'name' => 'activities', 'starttime' => 0, 'endtime' => 0, 'version' => 1, 'type' => 0],
        10 => ['id' => 10, 'name' => 'exchange', 'starttime' => 0, 'endtime' => 0, 'version' => 1, 'type' => 0],
        11 => ['id' => 11, 'name' => 'blackmarket', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 28, 'type' => 1],
        12 => ['id' => 12, 'name' => 'christmas', 'starttime' => 0, 'endtime' => 0, 'version' => 1, 'type' => 0],
        13 => ['id' => 13, 'name' => 'christmaspay', 'starttime' => 0, 'endtime' => 0, 'version' => 1, 'type' => 0],
        14 => ['id' => 14, 'name' => 'limitedshop', 'starttime' => 0, 'endtime' => 0, 'version' => 10, 'type' => 0],
        15 => ['id' => 15, 'name' => 'newyeardailysign', 'starttime' => 0, 'endtime' => 0, 'version' => 1, 'type' => 0],
        16 => ['id' => 16, 'name' => 'jadegamble', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 19, 'type' => 0],
        17 => ['id' => 17, 'name' => 'newyearpay', 'starttime' => 0, 'endtime' => 0, 'version' => 65, 'type' => 1],
        18 => ['id' => 18, 'name' => 'hit_egg', 'starttime' => 0, 'endtime' => 0, 'version' => 12, 'type' => 0],
        19 => ['id' => 19, 'name' => 'newyeardinner', 'starttime' => 0, 'endtime' => 0, 'version' => 1, 'type' => 0],
        20 => ['id' => 20, 'name' => 'beatengod', 'starttime' => 0, 'endtime' => 1458403200, 'version' => 62, 'type' => 0],
        21 => ['id' => 21, 'name' => 'jump_rebate', 'starttime' => 0, 'endtime' => 0, 'version' => 70, 'type' => 0],
        22 => ['id' => 22, 'name' => 'god', 'starttime' => 0, 'endtime' => 1458403200, 'version' => 7, 'type' => 0],
        23 => ['id' => 23, 'name' => 'chingming', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 61, 'type' => 0],
        24 => ['id' => 24, 'name' => 'gvg', 'starttime' => 0, 'endtime' => 0, 'version' => 10, 'type' => 0],
        25 => ['id' => 25, 'name' => 'sacredtree', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 47, 'type' => 0],
        26 => ['id' => 26, 'name' => 'anniversary', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 13, 'type' => 0],
        27 => ['id' => 27, 'name' => 'compete', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 10, 'type' => 0],
        28 => ['id' => 28, 'name' => 'christmasgift', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 48, 'type' => 1],
        29 => ['id' => 29, 'name' => 'springlottery', 'starttime' => 0, 'endtime' => 0, 'version' => 28, 'type' => 1],
        30 => ['id' => 30, 'name' => 'springshop', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 26, 'type' => 0],
        31 => ['id' => 31, 'name' => 'newyearboss', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 12, 'type' => 0],
        32 => ['id' => 32, 'name' => 'viprepay', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 12, 'type' => 0],
        37 => ['id' => 37, 'name' => 'acttreasure', 'starttime' => 0, 'endtime' => 1458403200, 'version' => 38, 'type' => 0],
        40 => ['id' => 40, 'name' => 'recall', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 11, 'type' => 0],
        41 => ['id' => 41, 'name' => 'actkemari', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 77, 'type' => 0],
        42 => ['id' => 42, 'name' => 'worldcup', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 10, 'type' => 0],
        43 => ['id' => 43, 'name' => 'pass_general', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 34, 'type' => 0],
        44 => ['id' => 44, 'name' => 'actthirty', 'starttime' => 0, 'endtime' => 0, 'version' => 11, 'type' => 0],
        45 => ['id' => 45, 'name' => 'general_only', 'starttime' => 0, 'endtime' => 0, 'version' => 24, 'type' => 1],
        46 => ['id' => 46, 'name' => 'total_login', 'starttime' => 0, 'endtime' => 0, 'version' => 10, 'type' => 0],
        47 => ['id' => 47, 'name' => 'acttanabata', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 50, 'type' => 0],
        48 => ['id' => 48, 'name' => 'year_happy', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 12, 'type' => 0],
        49 => ['id' => 49, 'name' => 'national_celebration', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 20, 'type' => 0],
        50 => ['id' => 50, 'name' => 'moon', 'starttime' => 0, 'endtime' => 0, 'version' => 15, 'type' => 0],
        51 => ['id' => 51, 'name' => 'login_reward', 'starttime' => 0, 'endtime' => 0, 'version' => 12, 'type' => 0],
        52 => ['id' => 52, 'name' => 'paychecks', 'starttime' => 0, 'endtime' => 0, 'version' => 5, 'type' => 0],
        54 => ['id' => 54, 'name' => 'national_day', 'starttime' => 0, 'endtime' => 0, 'version' => 88, 'type' => 0],
        58 => ['id' => 58, 'name' => 'double_tower', 'starttime' => 0, 'endtime' => 0, 'version' => 4, 'type' => 0],
        59 => ['id' => 59, 'name' => 'double_heaven', 'starttime' => 0, 'endtime' => 0, 'version' => 4, 'type' => 0],
        60 => ['id' => 60, 'name' => 'double_mine', 'starttime' => 0, 'endtime' => 0, 'version' => 7, 'type' => 0],
        61 => ['id' => 61, 'name' => 'double_refine', 'starttime' => 0, 'endtime' => 0, 'version' => 6, 'type' => 0],
        62 => ['id' => 62, 'name' => 'double_strengthen', 'starttime' => 0, 'endtime' => 0, 'version' => 6, 'type' => 0],
        63 => ['id' => 63, 'name' => 'double_talent', 'starttime' => 0, 'endtime' => 0, 'version' => 8, 'type' => 0],
        64 => ['id' => 64, 'name' => 'double_vip', 'starttime' => 0, 'endtime' => 0, 'version' => 4, 'type' => 0],
        65 => ['id' => 65, 'name' => 'double_beauty', 'starttime' => 0, 'endtime' => 0, 'version' => 5, 'type' => 0],
        66 => ['id' => 66, 'name' => 'double_miss', 'starttime' => 0, 'endtime' => 0, 'version' => 4, 'type' => 0],
        67 => ['id' => 67, 'name' => 'double_sanctum', 'starttime' => 0, 'endtime' => 0, 'version' => 5, 'type' => 0],
        68 => ['id' => 68, 'name' => 'double_hero', 'starttime' => 0, 'endtime' => 0, 'version' => 5, 'type' => 0],
        69 => ['id' => 69, 'name' => 'halloween', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 15, 'type' => 0],
        70 => ['id' => 70, 'name' => 'actenrichment', 'starttime' => 1457107200, 'endtime' => 1458403200, 'version' => 23, 'type' => 0],
        71 => ['id' => 71, 'name' => 'christmasbuy', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 18, 'type' => 0],
        72 => ['id' => 72, 'name' => 'sevenstar', 'starttime' => 0, 'endtime' => 1483113600, 'version' => 82, 'type' => 0],
        73 => ['id' => 73, 'name' => 'act_newyear', 'starttime' => 0, 'endtime' => 0, 'version' => 16, 'type' => 0],
        74 => ['id' => 74, 'name' => 'double_copies', 'starttime' => 0, 'endtime' => 0, 'version' => 2, 'type' => 0],
        75 => ['id' => 75, 'name' => 'lantern', 'starttime' => 0, 'endtime' => 0, 'version' => 2, 'type' => 0],
        76 => ['id' => 76, 'name' => 'double_overseastrade', 'starttime' => 0, 'endtime' => 0, 'version' => 2, 'type' => 0],
        77 => ['id' => 77, 'name' => 'sword', 'starttime' => 0, 'endtime' => 0, 'version' => 2, 'type' => 0],
    ];
}
