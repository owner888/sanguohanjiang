<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 闯关竞速表（旧版 zc_sanguo_mission_reward）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class MissionReward
{
    public const string TABLE = 'zc_sanguo_mission_reward';

    /**
     * 按主键 id 索引，共 27 行。字段：
     *   - id (tinyint) 奖励id
     *   - missionid (mediumint) 关卡id
     *   - name (varchar) 名称
     *   - gold (int) 奖励金币
     */
    public const array ROWS = [
        1 => ['id' => 1, 'missionid' => 10, 'name' => '周仓', 'gold' => 20],
        2 => ['id' => 2, 'missionid' => 20, 'name' => '张梁', 'gold' => 30],
        3 => ['id' => 3, 'missionid' => 30, 'name' => '张宝', 'gold' => 40],
        4 => ['id' => 4, 'missionid' => 40, 'name' => '张角', 'gold' => 50],
        5 => ['id' => 5, 'missionid' => 50, 'name' => '颜良', 'gold' => 60],
        6 => ['id' => 6, 'missionid' => 60, 'name' => '文丑', 'gold' => 70],
        7 => ['id' => 7, 'missionid' => 70, 'name' => '田丰', 'gold' => 80],
        8 => ['id' => 8, 'missionid' => 80, 'name' => '袁绍', 'gold' => 90],
        9 => ['id' => 9, 'missionid' => 90, 'name' => '公孙度', 'gold' => 100],
        10 => ['id' => 10, 'missionid' => 100, 'name' => '田予', 'gold' => 150],
        11 => ['id' => 11, 'missionid' => 110, 'name' => '公孙瓒', 'gold' => 200],
        12 => ['id' => 12, 'missionid' => 120, 'name' => '华雄', 'gold' => 300],
        13 => ['id' => 13, 'missionid' => 130, 'name' => '貂蝉', 'gold' => 400],
        14 => ['id' => 14, 'missionid' => 137, 'name' => '张绣', 'gold' => 500],
        15 => ['id' => 15, 'missionid' => 147, 'name' => '李儒', 'gold' => 600],
        16 => ['id' => 16, 'missionid' => 157, 'name' => '徐荣', 'gold' => 700],
        17 => ['id' => 17, 'missionid' => 167, 'name' => '董卓', 'gold' => 800],
        18 => ['id' => 18, 'missionid' => 177, 'name' => '马岱', 'gold' => 1000],
        19 => ['id' => 19, 'missionid' => 187, 'name' => '庞德', 'gold' => 1200],
        20 => ['id' => 20, 'missionid' => 197, 'name' => '韩遂', 'gold' => 1400],
        21 => ['id' => 21, 'missionid' => 207, 'name' => '马腾', 'gold' => 1600],
        22 => ['id' => 22, 'missionid' => 247, 'name' => '刘表', 'gold' => 2000],
        23 => ['id' => 23, 'missionid' => 287, 'name' => '袁术', 'gold' => 3000],
        24 => ['id' => 24, 'missionid' => 327, 'name' => '吕布', 'gold' => 4000],
        25 => ['id' => 25, 'missionid' => 397, 'name' => '曹操', 'gold' => 5000],
        26 => ['id' => 26, 'missionid' => 467, 'name' => '刘备', 'gold' => 7000],
        27 => ['id' => 27, 'missionid' => 537, 'name' => '孙权', 'gold' => 10000],
    ];
}
