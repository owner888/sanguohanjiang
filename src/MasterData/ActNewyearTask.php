<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 春节送福任务表（旧版 zc_sanguo_act_newyear_task）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class ActNewyearTask
{
    public const string TABLE = 'zc_sanguo_act_newyear_task';

    /**
     * 按主键 id 索引，共 12 行。字段：
     *   - id (int) 自增ID
     *   - desc (varchar) 任务条件描述
     *   - t_type (int) 任务条件类型
     *   - t_num (int) 任务条件参数
     *   - type (int) 任务类型
     *   - num (int) 奖励送福翻牌次数
     */
    public const array ROWS = [
        1 => ['id' => 1, 'desc' => '同任意玩家切磋2次', 't_type' => 15, 't_num' => 2, 'type' => 1, 'num' => 1],
        2 => ['id' => 2, 'desc' => '铜雀台抢夺5次', 't_type' => 16, 't_num' => 5, 'type' => 1, 'num' => 1],
        3 => ['id' => 3, 'desc' => '铜雀台亲近美人1次', 't_type' => 17, 't_num' => 1, 'type' => 2, 'num' => 2],
        4 => ['id' => 4, 'desc' => '海外贸易出海2次', 't_type' => 18, 't_num' => 2, 'type' => 1, 'num' => 1],
        5 => ['id' => 5, 'desc' => '世界频道发言1次', 't_type' => 19, 't_num' => 1, 'type' => 2, 'num' => 1],
        6 => ['id' => 6, 'desc' => '完成收集藏宝图1次', 't_type' => 20, 't_num' => 1, 'type' => 1, 'num' => 1],
        7 => ['id' => 7, 'desc' => '在每日对酒中痛饮花雕1次', 't_type' => 21, 't_num' => 1, 'type' => 1, 'num' => 1],
        8 => ['id' => 8, 'desc' => '参加国宴2次', 't_type' => 22, 't_num' => 2, 'type' => 1, 'num' => 1],
        9 => ['id' => 9, 'desc' => '国家捐献5次', 't_type' => 23, 't_num' => 5, 'type' => 1, 'num' => 1],
        10 => ['id' => 10, 'desc' => '多人副本战斗1次', 't_type' => 24, 't_num' => 1, 'type' => 1, 'num' => 1],
        11 => ['id' => 11, 'desc' => '领取VIP工资', 't_type' => 25, 't_num' => 1, 'type' => 2, 'num' => 2],
        12 => ['id' => 12, 'desc' => '切任意玉石3颗', 't_type' => 26, 't_num' => 3, 'type' => 1, 'num' => 1],
    ];
}
