<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 激活码奖励表（旧版 zc_sanguo_activationcode_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActivationcodeReward
{
    public const string TABLE = 'zc_sanguo_activationcode_reward';

    /**
     * 按主键 id 索引，共 52 行。字段：
     *   - id (int) 激活码奖励id
     *   - type (int) 奖励渠道（每个渠道的激活码只能使用一次）
     *   - use_num (int) 同种类型激活码可使用次数（0表示不限制）
     *   - description (varchar) 激活码渠道名称
     *   - gettype1 (int) 奖励类型（1元宝，2银币，3军令，4突飞卡，5蓝宝石，6黄宝石，7紫宝石， 8原魂，9武将）
     *   - getvalue1 (int) 奖励参数（0就是没有该奖励）
     *   - gettype2 (int) 奖励类型（1元宝，2银币，3军令，4突飞卡，5蓝宝石，6黄宝石，7紫宝石， 8原魂，9武将）
     *   - getvalue2 (int) 奖励参数（0就是没有该奖励）
     *   - gettype3 (int) 奖励类型（1元宝，2银币，3军令，4突飞卡，5蓝宝石，6黄宝石，7紫宝石， 8原魂，9武将）
     *   - getvalue3 (int) 奖励参数（0就是没有该奖励）
     *   - gettype4 (int) 奖励类型（1元宝，2银币，3军令，4突飞卡，5蓝宝石，6黄宝石，7紫宝石， 8原魂，9武将）
     *   - getvalue4 (int) 奖励参数（0就是没有该奖励）
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => 0, 'use_num' => 1, 'description' => '第一种激活码', 'gettype1' => 1, 'getvalue1' => 100, 'gettype2' => 2, 'getvalue2' => 1000000, 'gettype3' => 4, 'getvalue3' => 50, 'gettype4' => 0, 'getvalue4' => 0],
        2 => ['id' => 2, 'type' => 1, 'use_num' => 1, 'description' => '', 'gettype1' => 1, 'getvalue1' => 100, 'gettype2' => 2, 'getvalue2' => 1000000, 'gettype3' => 6, 'getvalue3' => 100, 'gettype4' => 0, 'getvalue4' => 0],
        3 => ['id' => 3, 'type' => 2, 'use_num' => 1, 'description' => '', 'gettype1' => 2, 'getvalue1' => 500000, 'gettype2' => 3, 'getvalue2' => 10000, 'gettype3' => 4, 'getvalue3' => 10000, 'gettype4' => 0, 'getvalue4' => 0],
        4 => ['id' => 4, 'type' => 3, 'use_num' => 1, 'description' => '', 'gettype1' => 5, 'getvalue1' => 10000, 'gettype2' => 6, 'getvalue2' => 10000, 'gettype3' => 7, 'getvalue3' => 10000, 'gettype4' => 0, 'getvalue4' => 0],
        5 => ['id' => 5, 'type' => 4, 'use_num' => 1, 'description' => '', 'gettype1' => 5, 'getvalue1' => 10000, 'gettype2' => 6, 'getvalue2' => 10000, 'gettype3' => 7, 'getvalue3' => 10000, 'gettype4' => 9, 'getvalue4' => 187],
        6 => ['id' => 6, 'type' => 5, 'use_num' => 1, 'description' => '', 'gettype1' => 5, 'getvalue1' => 10000, 'gettype2' => 6, 'getvalue2' => 10000, 'gettype3' => 7, 'getvalue3' => 10000, 'gettype4' => 9, 'getvalue4' => 187],
        7 => ['id' => 7, 'type' => 6, 'use_num' => 1, 'description' => '', 'gettype1' => 5, 'getvalue1' => 10000, 'gettype2' => 6, 'getvalue2' => 10000, 'gettype3' => 7, 'getvalue3' => 10000, 'gettype4' => 4, 'getvalue4' => 100],
        8 => ['id' => 8, 'type' => 999, 'use_num' => 100, 'description' => 'imessage 同用激活码奖励', 'gettype1' => 2, 'getvalue1' => 1000000, 'gettype2' => 7, 'getvalue2' => 100, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        9 => ['id' => 9, 'type' => 7, 'use_num' => 100, 'description' => '360礼包（2000个）', 'gettype1' => 2, 'getvalue1' => 500000, 'gettype2' => 4, 'getvalue2' => 50, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        10 => ['id' => 10, 'type' => 8, 'use_num' => 1, 'description' => '配合智明推广，需要一批兑换码做市场宣传，数量：8000个，免费获得', 'gettype1' => 1, 'getvalue1' => 50, 'gettype2' => 2, 'getvalue2' => 500000, 'gettype3' => 4, 'getvalue3' => 10, 'gettype4' => 0, 'getvalue4' => 0],
        11 => ['id' => 11, 'type' => 9, 'use_num' => 1, 'description' => '360礼包（8000个）', 'gettype1' => 2, 'getvalue1' => 500000, 'gettype2' => 4, 'getvalue2' => 50, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        12 => ['id' => 12, 'type' => 10, 'use_num' => 1, 'description' => '360新手礼包', 'gettype1' => 2, 'getvalue1' => 100000, 'gettype2' => 1, 'getvalue2' => 50, 'gettype3' => 4, 'getvalue3' => 10, 'gettype4' => 6, 'getvalue4' => 10],
        13 => ['id' => 13, 'type' => 11, 'use_num' => 1, 'description' => '377平台圣诞储值好康活动,赠送所有港澳台会员武神关圣的虚宝兑换码', 'gettype1' => 1, 'getvalue1' => 200, 'gettype2' => 2, 'getvalue2' => 2000000, 'gettype3' => 5, 'getvalue3' => 200, 'gettype4' => 6, 'getvalue4' => 100],
        14 => ['id' => 14, 'type' => 12, 'use_num' => 1, 'description' => '台湾711的299虚拟高价包', 'gettype1' => 1, 'getvalue1' => 620, 'gettype2' => 7, 'getvalue2' => 50, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        15 => ['id' => 15, 'type' => 13, 'use_num' => 0, 'description' => '饮料合作，需要印制在瓶盖内的140万兑换码（8元宝）', 'gettype1' => 1, 'getvalue1' => 8, 'gettype2' => 0, 'getvalue2' => 0, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        16 => ['id' => 16, 'type' => 14, 'use_num' => 0, 'description' => '饮料合作，需要印制在瓶盖内的140万兑换码（80元宝）', 'gettype1' => 1, 'getvalue1' => 80, 'gettype2' => 0, 'getvalue2' => 0, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        17 => ['id' => 17, 'type' => 15, 'use_num' => 0, 'description' => '饮料合作，需要印制在瓶盖内的140万兑换码（8万银币）', 'gettype1' => 2, 'getvalue1' => 80000, 'gettype2' => 0, 'getvalue2' => 0, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        19 => ['id' => 19, 'type' => 16, 'use_num' => 0, 'description' => '饮料合作，需要印制在瓶盖内的140万兑换码（80万银币）', 'gettype1' => 2, 'getvalue1' => 800000, 'gettype2' => 0, 'getvalue2' => 0, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        20 => ['id' => 20, 'type' => 17, 'use_num' => 0, 'description' => '饮料合作，需要印制在瓶盖内的140万兑换码（8个突飞卡）', 'gettype1' => 4, 'getvalue1' => 8, 'gettype2' => 0, 'getvalue2' => 0, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        22 => ['id' => 22, 'type' => 18, 'use_num' => 0, 'description' => '饮料合作，需要印制在瓶盖内的140万兑换码（8个蓝宝石）', 'gettype1' => 5, 'getvalue1' => 8, 'gettype2' => 0, 'getvalue2' => 0, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        23 => ['id' => 23, 'type' => 19, 'use_num' => 0, 'description' => '饮料合作，需要印制在瓶盖内的140万兑换码（8个黄宝石）', 'gettype1' => 6, 'getvalue1' => 8, 'gettype2' => 0, 'getvalue2' => 0, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        24 => ['id' => 24, 'type' => 20, 'use_num' => 0, 'description' => '饮料合作，需要印制在瓶盖内的140万兑换码（8个紫宝石）', 'gettype1' => 7, 'getvalue1' => 8, 'gettype2' => 0, 'getvalue2' => 0, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        25 => ['id' => 25, 'type' => 21, 'use_num' => 1, 'description' => '饮料合作，需要印制在瓶盖内的140万兑换码（iphone 5S 16G）', 'gettype1' => 0, 'getvalue1' => 0, 'gettype2' => 0, 'getvalue2' => 0, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        26 => ['id' => 26, 'type' => 22, 'use_num' => 1, 'description' => '饮料合作，需要印制在瓶盖内的140万兑换码（Samsung Note3）', 'gettype1' => 0, 'getvalue1' => 0, 'gettype2' => 0, 'getvalue2' => 0, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        27 => ['id' => 27, 'type' => 23, 'use_num' => 1, 'description' => '饮料合作，需要印制在瓶盖内的140万兑换码（Sony PS4）', 'gettype1' => 0, 'getvalue1' => 0, 'gettype2' => 0, 'getvalue2' => 0, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        36 => ['id' => 36, 'type' => 998, 'use_num' => 1, 'description' => '市场推广激活码，类是imessage (孙凯)', 'gettype1' => 1, 'getvalue1' => 50, 'gettype2' => 2, 'getvalue2' => 300000, 'gettype3' => 5, 'getvalue3' => 20, 'gettype4' => 0, 'getvalue4' => 0],
        37 => ['id' => 37, 'type' => 997, 'use_num' => 1, 'description' => '市场推广激活码，类是imessage (孙凯)', 'gettype1' => 1, 'getvalue1' => 50, 'gettype2' => 2, 'getvalue2' => 300000, 'gettype3' => 5, 'getvalue3' => 20, 'gettype4' => 0, 'getvalue4' => 0],
        38 => ['id' => 38, 'type' => 996, 'use_num' => 1, 'description' => '市场推广激活码，类是imessage (孙凯)', 'gettype1' => 1, 'getvalue1' => 50, 'gettype2' => 2, 'getvalue2' => 300000, 'gettype3' => 5, 'getvalue3' => 20, 'gettype4' => 0, 'getvalue4' => 0],
        39 => ['id' => 39, 'type' => 995, 'use_num' => 1, 'description' => '市场推广激活码，类是imessage (孙凯)', 'gettype1' => 1, 'getvalue1' => 50, 'gettype2' => 2, 'getvalue2' => 300000, 'gettype3' => 5, 'getvalue3' => 20, 'gettype4' => 0, 'getvalue4' => 0],
        40 => ['id' => 40, 'type' => 994, 'use_num' => 1, 'description' => '市场推广激活码，类是imessage (孙凯)', 'gettype1' => 1, 'getvalue1' => 50, 'gettype2' => 2, 'getvalue2' => 300000, 'gettype3' => 5, 'getvalue3' => 20, 'gettype4' => 0, 'getvalue4' => 0],
        41 => ['id' => 41, 'type' => 993, 'use_num' => 1, 'description' => '市场推广激活码，类是imessage (孙凯)', 'gettype1' => 1, 'getvalue1' => 50, 'gettype2' => 2, 'getvalue2' => 300000, 'gettype3' => 5, 'getvalue3' => 20, 'gettype4' => 0, 'getvalue4' => 0],
        42 => ['id' => 42, 'type' => 992, 'use_num' => 1, 'description' => '市场推广激活码，类是imessage (孙凯)', 'gettype1' => 1, 'getvalue1' => 50, 'gettype2' => 2, 'getvalue2' => 300000, 'gettype3' => 5, 'getvalue3' => 20, 'gettype4' => 0, 'getvalue4' => 0],
        43 => ['id' => 43, 'type' => 991, 'use_num' => 1, 'description' => '市场推广激活码，类是imessage (孙凯)', 'gettype1' => 1, 'getvalue1' => 50, 'gettype2' => 2, 'getvalue2' => 300000, 'gettype3' => 5, 'getvalue3' => 20, 'gettype4' => 0, 'getvalue4' => 0],
        44 => ['id' => 44, 'type' => 990, 'use_num' => 1, 'description' => '市场推广激活码，类是imessage (孙凯)', 'gettype1' => 1, 'getvalue1' => 50, 'gettype2' => 2, 'getvalue2' => 300000, 'gettype3' => 5, 'getvalue3' => 20, 'gettype4' => 0, 'getvalue4' => 0],
        45 => ['id' => 45, 'type' => 32, 'use_num' => 1, 'description' => '元宝10银币30W军令10', 'gettype1' => 1, 'getvalue1' => 30, 'gettype2' => 2, 'getvalue2' => 300000, 'gettype3' => 3, 'getvalue3' => 10, 'gettype4' => 0, 'getvalue4' => 0],
        50 => ['id' => 50, 'type' => 37, 'use_num' => 1, 'description' => '80元宝智明星通', 'gettype1' => 1, 'getvalue1' => 80, 'gettype2' => 0, 'getvalue2' => 0, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        55 => ['id' => 55, 'type' => 42, 'use_num' => 1, 'description' => '元宝 50银币300000 蓝宝石 20', 'gettype1' => 1, 'getvalue1' => 50, 'gettype2' => 2, 'getvalue2' => 300000, 'gettype3' => 5, 'getvalue3' => 20, 'gettype4' => 0, 'getvalue4' => 0],
        64 => ['id' => 64, 'type' => 43, 'use_num' => 1, 'description' => '微信100元宝 50W银币 30蓝宝石 10突飞卡', 'gettype1' => 1, 'getvalue1' => 100, 'gettype2' => 2, 'getvalue2' => 500000, 'gettype3' => 5, 'getvalue3' => 30, 'gettype4' => 4, 'getvalue4' => 10],
        67 => ['id' => 67, 'type' => 44, 'use_num' => 1, 'description' => '智明100元宝 10个紫宝石', 'gettype1' => 1, 'getvalue1' => 100, 'gettype2' => 7, 'getvalue2' => 10, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        69 => ['id' => 69, 'type' => 46, 'use_num' => 1, 'description' => '论坛活动', 'gettype1' => 1, 'getvalue1' => 800, 'gettype2' => 0, 'getvalue2' => 0, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        73 => ['id' => 73, 'type' => 50, 'use_num' => 1, 'description' => '智明fb活动100元宝 10黄宝石', 'gettype1' => 1, 'getvalue1' => 100, 'gettype2' => 6, 'getvalue2' => 10, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        74 => ['id' => 74, 'type' => 51, 'use_num' => 1, 'description' => '200元宝 10紫宝石', 'gettype1' => 1, 'getvalue1' => 200, 'gettype2' => 7, 'getvalue2' => 10, 'gettype3' => 0, 'getvalue3' => 0, 'gettype4' => 0, 'getvalue4' => 0],
        75 => ['id' => 75, 'type' => 75, 'use_num' => 0, 'description' => '全服充5000福利', 'gettype1' => 1, 'getvalue1' => 150000, 'gettype2' => 7, 'getvalue2' => 8888, 'gettype3' => 6, 'getvalue3' => 88888, 'gettype4' => 3, 'getvalue4' => 888],
        77 => ['id' => 77, 'type' => 77, 'use_num' => 100, 'description' => '全服充10000福利', 'gettype1' => 1, 'getvalue1' => 150001, 'gettype2' => 7, 'getvalue2' => 8889, 'gettype3' => 6, 'getvalue3' => 88889, 'gettype4' => 3, 'getvalue4' => 889],
        78 => ['id' => 78, 'type' => 78, 'use_num' => 0, 'description' => '全服充1万福利', 'gettype1' => 1, 'getvalue1' => 300000, 'gettype2' => 7, 'getvalue2' => 18888, 'gettype3' => 6, 'getvalue3' => 188888, 'gettype4' => 3, 'getvalue4' => 1999],
        80 => ['id' => 80, 'type' => 80, 'use_num' => 0, 'description' => '补仓', 'gettype1' => 1, 'getvalue1' => 20000, 'gettype2' => 7, 'getvalue2' => 10000, 'gettype3' => 6, 'getvalue3' => 20000, 'gettype4' => 3, 'getvalue4' => 1000],
        81 => ['id' => 81, 'type' => 81, 'use_num' => 0, 'description' => '111', 'gettype1' => 1, 'getvalue1' => 50000, 'gettype2' => 3, 'getvalue2' => 1000, 'gettype3' => 7, 'getvalue3' => 8888, 'gettype4' => 4, 'getvalue4' => 2000],
        82 => ['id' => 82, 'type' => 82, 'use_num' => 0, 'description' => 'gxbc', 'gettype1' => 1, 'getvalue1' => 80000, 'gettype2' => 7, 'getvalue2' => 20000, 'gettype3' => 3, 'getvalue3' => 2000, 'gettype4' => 4, 'getvalue4' => 2000],
        83 => ['id' => 83, 'type' => 83, 'use_num' => 0, 'description' => '11111', 'gettype1' => 4, 'getvalue1' => 2000, 'gettype2' => 3, 'getvalue2' => 2000, 'gettype3' => 8, 'getvalue3' => 200000, 'gettype4' => 6, 'getvalue4' => 50000],
        88 => ['id' => 88, 'type' => 88, 'use_num' => 0, 'description' => '推广3', 'gettype1' => 5, 'getvalue1' => 30000, 'gettype2' => 6, 'getvalue2' => 30000, 'gettype3' => 7, 'getvalue3' => 30000, 'gettype4' => 0, 'getvalue4' => 0],
        89 => ['id' => 89, 'type' => 89, 'use_num' => 0, 'description' => '推广2', 'gettype1' => 5, 'getvalue1' => 20000, 'gettype2' => 6, 'getvalue2' => 20000, 'gettype3' => 7, 'getvalue3' => 20000, 'gettype4' => 2, 'getvalue4' => 0],
        90 => ['id' => 90, 'type' => 90, 'use_num' => 0, 'description' => '推广1', 'gettype1' => 5, 'getvalue1' => 10000, 'gettype2' => 6, 'getvalue2' => 10000, 'gettype3' => 7, 'getvalue3' => 10000, 'gettype4' => 0, 'getvalue4' => 0],
    ];
}
