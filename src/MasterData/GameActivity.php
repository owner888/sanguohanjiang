<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 活动信息表（旧版 zc_sanguo_game_activity）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class GameActivity
{
    public const string TABLE = 'zc_sanguo_game_activity';

    /**
     * 按主键 id 索引，共 68 行。字段：
     *   - id (tinyint) 活动id
     *   - activity (varchar) 活动
     *   - description (varchar) 描述
     */
    public const array ROWS = [
        1 => ['id' => 1, 'activity' => 'sacrifice', 'description' => '祭祀'],
        2 => ['id' => 2, 'activity' => 'exercise', 'description' => '整军'],
        3 => ['id' => 3, 'activity' => 'treasuremap', 'description' => '已关闭'],
        4 => ['id' => 4, 'activity' => 'general_onsale', 'description' => '神将台'],
        5 => ['id' => 5, 'activity' => 'flop', 'description' => '朝贡'],
        6 => ['id' => 6, 'activity' => 'dailysign', 'description' => '每日签到[点开卡死]'],
        7 => ['id' => 7, 'activity' => 'mooncake', 'description' => '已关闭'],
        8 => ['id' => 8, 'activity' => 'rebate', 'description' => '返利[福袋]'],
        9 => ['id' => 9, 'activity' => 'activities', 'description' => '已关闭'],
        10 => ['id' => 10, 'activity' => 'exchange', 'description' => '已关闭'],
        11 => ['id' => 11, 'activity' => 'blackmarket', 'description' => '黑市'],
        12 => ['id' => 12, 'activity' => 'christmas', 'description' => '已关闭'],
        13 => ['id' => 13, 'activity' => 'christmas', 'description' => '已关闭'],
        14 => ['id' => 14, 'activity' => 'limitedshop', 'description' => '限售'],
        15 => ['id' => 15, 'activity' => 'newyeardailysign', 'description' => '感恩'],
        16 => ['id' => 16, 'activity' => 'jadegamble', 'description' => '神矿'],
        17 => ['id' => 17, 'activity' => 'newyearpay', 'description' => '返利'],
        18 => ['id' => 18, 'activity' => 'hit_egg', 'description' => '砸蛋'],
        19 => ['id' => 19, 'activity' => 'newyeardinner', 'description' => '已关闭'],
        20 => ['id' => 20, 'activity' => 'beatengod', 'description' => '暴打'],
        21 => ['id' => 21, 'activity' => 'jump_rebate', 'description' => '大熊返利'],
        22 => ['id' => 22, 'activity' => 'god', 'description' => '战神殿'],
        23 => ['id' => 23, 'activity' => 'chingming', 'description' => '祭祖'],
        24 => ['id' => 24, 'activity' => 'gvg', 'description' => '跨服争霸'],
        25 => ['id' => 25, 'activity' => 'sacredtree', 'description' => '神树'],
        26 => ['id' => 26, 'activity' => 'anniversary', 'description' => '小团购300'],
        27 => ['id' => 27, 'activity' => 'compete', 'description' => '竞速'],
        28 => ['id' => 28, 'activity' => 'christmasgift', 'description' => '大熊团购'],
        29 => ['id' => 29, 'activity' => 'springlottery', 'description' => '转盘'],
        30 => ['id' => 30, 'activity' => 'springshop', 'description' => '商城'],
        31 => ['id' => 31, 'activity' => 'newyearboss', 'description' => '年兽'],
        32 => ['id' => 32, 'activity' => 'viprepay', 'description' => '回馈'],
        33 => ['id' => 33, 'activity' => 'acttreasure', 'description' => '寻宝'],
        34 => ['id' => 34, 'activity' => 'recall', 'description' => '召回'],
        35 => ['id' => 35, 'activity' => 'actkemari', 'description' => '蹴鞠'],
        36 => ['id' => 36, 'activity' => 'worldcup', 'description' => '世界杯'],
        37 => ['id' => 37, 'activity' => 'pass_general', 'description' => '过关斩将'],
        38 => ['id' => 38, 'activity' => 'actthirty', 'description' => '赠礼'],
        39 => ['id' => 39, 'activity' => 'total_login', 'description' => '签到'],
        40 => ['id' => 40, 'activity' => 'general_only', 'description' => '名将'],
        41 => ['id' => 41, 'activity' => 'acttanabata', 'description' => '土豪'],
        42 => ['id' => 42, 'activity' => 'national_celebration', 'description' => '国家庆典'],
        43 => ['id' => 43, 'activity' => 'moon', 'description' => '中秋节'],
        44 => ['id' => 44, 'activity' => 'double_hero', 'description' => '双倍英雄王座'],
        45 => ['id' => 45, 'activity' => 'double_tower', 'description' => '双倍将魂星路'],
        46 => ['id' => 46, 'activity' => 'double_heaven', 'description' => '双倍通天塔'],
        47 => ['id' => 47, 'activity' => 'double_mine', 'description' => '双倍矿'],
        48 => ['id' => 48, 'activity' => 'double_refine', 'description' => '精炼100%'],
        49 => ['id' => 49, 'activity' => 'double_strengthen', 'description' => '强化100%'],
        50 => ['id' => 50, 'activity' => 'double_talent', 'description' => '双倍天赋'],
        51 => ['id' => 51, 'activity' => 'double_vip', 'description' => '双倍vip工资'],
        52 => ['id' => 52, 'activity' => 'double_miss', 'description' => '双倍关卡奖励'],
        53 => ['id' => 53, 'activity' => 'double_sanctum', 'description' => '双倍四象圣域'],
        54 => ['id' => 54, 'activity' => 'double_beauty', 'description' => '双倍铜雀台次数'],
        55 => ['id' => 55, 'activity' => 'christmaspay', 'description' => '已关闭'],
        56 => ['id' => 56, 'activity' => 'national_day', 'description' => '领红包'],
        57 => ['id' => 57, 'activity' => 'paychecks', 'description' => '领工资已关闭'],
        58 => ['id' => 58, 'activity' => 'login_reward', 'description' => '登陆返礼'],
        59 => ['id' => 59, 'activity' => 'year_happy', 'description' => '周年庆名将成就限时8折'],
        60 => ['id' => 60, 'activity' => 'halloween', 'description' => '万圣节'],
        61 => ['id' => 61, 'activity' => 'actenrichment', 'description' => '貔貅活动'],
        62 => ['id' => 62, 'activity' => 'sevenstar', 'description' => '七星灯'],
        63 => ['id' => 63, 'activity' => 'christmasbuy', 'description' => '购物'],
        64 => ['id' => 64, 'activity' => 'act_newyear', 'description' => '春节送福活动'],
        65 => ['id' => 65, 'activity' => 'double_copies', 'description' => '平乱免费次数翻倍'],
        66 => ['id' => 66, 'activity' => 'lantern', 'description' => '元宵节'],
        67 => ['id' => 67, 'activity' => 'double_overseastrade', 'description' => '不知道'],
        68 => ['id' => 68, 'activity' => 'sword', 'description' => '不知道'],
    ];
}
