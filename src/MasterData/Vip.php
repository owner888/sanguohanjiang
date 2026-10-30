<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * vip（旧版 zc_sanguo_vip）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class Vip
{
    public const string TABLE = 'zc_sanguo_vip';

    /**
     * 按主键 vip_level 索引，共 20 行。字段：
     *   - vip_level (tinyint) vip等级
     *   - toplimit (mediumint) vip升级积分（rmb）
     *   - practicenum (tinyint) 最多开启训练位个数
     *   - practicelevel (tinyint) 训练位升级最高等级
     *   - culturelevel (tinyint) 属性培养功能开启等级
     *   - workshopnum (tinyint) 采集位开启个数
     *   - workshoplevel (tinyint) 采集位可升级等级
     *   - insight (tinyint) 顿悟概率
     *   - clear_cd (tinyint) 去除cd限制
     *   - strengthen (tinyint) 强化暴击开启状态
     *   - leap (tinyint) 突飞暴击开启状态
     *   - act_limit (tinyint) 军令上限
     *   - trade (tinyint) 开启强行贸易
     *   - inherit (tinyint) 继承开启类型（1普通，2加强，3白金，至尊）
     *   - insightup (tinyint) 天赋升星暴击
     */
    public const array ROWS = [
        1 => ['vip_level' => 1, 'toplimit' => 1, 'practicenum' => 4, 'practicelevel' => 2, 'culturelevel' => 0, 'workshopnum' => 4, 'workshoplevel' => 2, 'insight' => 0, 'clear_cd' => 0, 'strengthen' => 0, 'leap' => 0, 'act_limit' => 60, 'trade' => 0, 'inherit' => 2, 'insightup' => 0],
        2 => ['vip_level' => 2, 'toplimit' => 2, 'practicenum' => 5, 'practicelevel' => 2, 'culturelevel' => 0, 'workshopnum' => 5, 'workshoplevel' => 2, 'insight' => 0, 'clear_cd' => 0, 'strengthen' => 0, 'leap' => 0, 'act_limit' => 70, 'trade' => 1, 'inherit' => 2, 'insightup' => 0],
        3 => ['vip_level' => 3, 'toplimit' => 3, 'practicenum' => 5, 'practicelevel' => 3, 'culturelevel' => 3, 'workshopnum' => 5, 'workshoplevel' => 3, 'insight' => 0, 'clear_cd' => 1, 'strengthen' => 0, 'leap' => 0, 'act_limit' => 80, 'trade' => 1, 'inherit' => 2, 'insightup' => 0],
        4 => ['vip_level' => 4, 'toplimit' => 4, 'practicenum' => 6, 'practicelevel' => 4, 'culturelevel' => 3, 'workshopnum' => 6, 'workshoplevel' => 4, 'insight' => 0, 'clear_cd' => 1, 'strengthen' => 0, 'leap' => 0, 'act_limit' => 90, 'trade' => 1, 'inherit' => 3, 'insightup' => 0],
        5 => ['vip_level' => 5, 'toplimit' => 5, 'practicenum' => 6, 'practicelevel' => 4, 'culturelevel' => 4, 'workshopnum' => 6, 'workshoplevel' => 4, 'insight' => 0, 'clear_cd' => 1, 'strengthen' => 1, 'leap' => 1, 'act_limit' => 100, 'trade' => 1, 'inherit' => 3, 'insightup' => 0],
        6 => ['vip_level' => 6, 'toplimit' => 6, 'practicenum' => 7, 'practicelevel' => 5, 'culturelevel' => 4, 'workshopnum' => 7, 'workshoplevel' => 5, 'insight' => 1, 'clear_cd' => 1, 'strengthen' => 1, 'leap' => 1, 'act_limit' => 100, 'trade' => 1, 'inherit' => 3, 'insightup' => 0],
        7 => ['vip_level' => 7, 'toplimit' => 7, 'practicenum' => 8, 'practicelevel' => 5, 'culturelevel' => 4, 'workshopnum' => 8, 'workshoplevel' => 5, 'insight' => 1, 'clear_cd' => 1, 'strengthen' => 1, 'leap' => 1, 'act_limit' => 100, 'trade' => 1, 'inherit' => 3, 'insightup' => 0],
        8 => ['vip_level' => 8, 'toplimit' => 8, 'practicenum' => 8, 'practicelevel' => 6, 'culturelevel' => 4, 'workshopnum' => 8, 'workshoplevel' => 6, 'insight' => 1, 'clear_cd' => 1, 'strengthen' => 1, 'leap' => 1, 'act_limit' => 100, 'trade' => 1, 'inherit' => 4, 'insightup' => 0],
        9 => ['vip_level' => 9, 'toplimit' => 9, 'practicenum' => 8, 'practicelevel' => 6, 'culturelevel' => 4, 'workshopnum' => 8, 'workshoplevel' => 6, 'insight' => 1, 'clear_cd' => 1, 'strengthen' => 1, 'leap' => 1, 'act_limit' => 100, 'trade' => 1, 'inherit' => 4, 'insightup' => 1],
        10 => ['vip_level' => 10, 'toplimit' => 5000, 'practicenum' => 8, 'practicelevel' => 6, 'culturelevel' => 4, 'workshopnum' => 8, 'workshoplevel' => 6, 'insight' => 1, 'clear_cd' => 1, 'strengthen' => 1, 'leap' => 1, 'act_limit' => 100, 'trade' => 1, 'inherit' => 4, 'insightup' => 1],
        11 => ['vip_level' => 11, 'toplimit' => 50000, 'practicenum' => 8, 'practicelevel' => 6, 'culturelevel' => 4, 'workshopnum' => 8, 'workshoplevel' => 6, 'insight' => 1, 'clear_cd' => 1, 'strengthen' => 1, 'leap' => 1, 'act_limit' => 100, 'trade' => 1, 'inherit' => 4, 'insightup' => 1],
        12 => ['vip_level' => 12, 'toplimit' => 100000, 'practicenum' => 8, 'practicelevel' => 6, 'culturelevel' => 4, 'workshopnum' => 8, 'workshoplevel' => 6, 'insight' => 1, 'clear_cd' => 1, 'strengthen' => 1, 'leap' => 1, 'act_limit' => 100, 'trade' => 1, 'inherit' => 4, 'insightup' => 1],
        13 => ['vip_level' => 13, 'toplimit' => 200000, 'practicenum' => 8, 'practicelevel' => 6, 'culturelevel' => 4, 'workshopnum' => 8, 'workshoplevel' => 6, 'insight' => 1, 'clear_cd' => 1, 'strengthen' => 1, 'leap' => 1, 'act_limit' => 100, 'trade' => 1, 'inherit' => 4, 'insightup' => 1],
        14 => ['vip_level' => 14, 'toplimit' => 400000, 'practicenum' => 8, 'practicelevel' => 6, 'culturelevel' => 4, 'workshopnum' => 8, 'workshoplevel' => 6, 'insight' => 1, 'clear_cd' => 1, 'strengthen' => 1, 'leap' => 1, 'act_limit' => 100, 'trade' => 1, 'inherit' => 4, 'insightup' => 1],
        15 => ['vip_level' => 15, 'toplimit' => 800000, 'practicenum' => 8, 'practicelevel' => 6, 'culturelevel' => 4, 'workshopnum' => 8, 'workshoplevel' => 6, 'insight' => 1, 'clear_cd' => 1, 'strengthen' => 1, 'leap' => 1, 'act_limit' => 100, 'trade' => 1, 'inherit' => 4, 'insightup' => 1],
        16 => ['vip_level' => 16, 'toplimit' => 2000000, 'practicenum' => 8, 'practicelevel' => 6, 'culturelevel' => 4, 'workshopnum' => 8, 'workshoplevel' => 6, 'insight' => 1, 'clear_cd' => 1, 'strengthen' => 1, 'leap' => 1, 'act_limit' => 100, 'trade' => 1, 'inherit' => 4, 'insightup' => 1],
        17 => ['vip_level' => 17, 'toplimit' => 3200000, 'practicenum' => 8, 'practicelevel' => 6, 'culturelevel' => 4, 'workshopnum' => 8, 'workshoplevel' => 6, 'insight' => 1, 'clear_cd' => 1, 'strengthen' => 1, 'leap' => 1, 'act_limit' => 100, 'trade' => 1, 'inherit' => 4, 'insightup' => 1],
        18 => ['vip_level' => 18, 'toplimit' => 4000000, 'practicenum' => 8, 'practicelevel' => 6, 'culturelevel' => 4, 'workshopnum' => 8, 'workshoplevel' => 6, 'insight' => 1, 'clear_cd' => 1, 'strengthen' => 1, 'leap' => 1, 'act_limit' => 100, 'trade' => 1, 'inherit' => 4, 'insightup' => 1],
        19 => ['vip_level' => 19, 'toplimit' => 6000000, 'practicenum' => 8, 'practicelevel' => 6, 'culturelevel' => 4, 'workshopnum' => 8, 'workshoplevel' => 6, 'insight' => 1, 'clear_cd' => 1, 'strengthen' => 1, 'leap' => 1, 'act_limit' => 100, 'trade' => 1, 'inherit' => 4, 'insightup' => 1],
        20 => ['vip_level' => 20, 'toplimit' => 7200000, 'practicenum' => 8, 'practicelevel' => 6, 'culturelevel' => 4, 'workshopnum' => 8, 'workshoplevel' => 6, 'insight' => 1, 'clear_cd' => 1, 'strengthen' => 1, 'leap' => 1, 'act_limit' => 100, 'trade' => 1, 'inherit' => 4, 'insightup' => 1],
    ];
}
