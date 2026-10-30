<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 东瀛关卡数据表（旧版 zc_sanguo_japan_instance）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class JapanInstance
{
    public const string TABLE = 'zc_sanguo_japan_instance';

    /**
     * 按主键 instance_id 索引，共 16 行。字段：
     *   - instance_id (int) 关卡ID
     *   - part_of_city (int) 隶属城池
     *   - sequence (int)
     *   - instance_level (int) 关卡等级
     *   - instance_name (varchar) 关卡名称
     *   - end_attack_reward (int) 最后一击奖励：战功
     *   - position1 (int) 位置1
     *   - position2 (int) 位置2
     *   - position3 (int) 位置3
     *   - position4 (int) 位置4
     *   - position5 (int) 位置5
     *   - position6 (int) 位置6
     *   - position7 (int) 位置7
     *   - position8 (int) 位置8
     *   - position9 (int) 位置9
     */
    public const array ROWS = [
        1 => ['instance_id' => 1, 'part_of_city' => 1, 'sequence' => 1, 'instance_level' => 120, 'instance_name' => '鬼小岛', 'end_attack_reward' => 10, 'position1' => 2, 'position2' => 0, 'position3' => 2, 'position4' => 0, 'position5' => 1, 'position6' => 0, 'position7' => 2, 'position8' => 0, 'position9' => 2],
        2 => ['instance_id' => 2, 'part_of_city' => 1, 'sequence' => 2, 'instance_level' => 130, 'instance_name' => '柿崎景家', 'end_attack_reward' => 10, 'position1' => 0, 'position2' => 4, 'position3' => 0, 'position4' => 4, 'position5' => 3, 'position6' => 4, 'position7' => 0, 'position8' => 4, 'position9' => 0],
        3 => ['instance_id' => 3, 'part_of_city' => 1, 'sequence' => 3, 'instance_level' => 140, 'instance_name' => '直江兼续', 'end_attack_reward' => 10, 'position1' => 0, 'position2' => 6, 'position3' => 0, 'position4' => 0, 'position5' => 6, 'position6' => 0, 'position7' => 6, 'position8' => 6, 'position9' => 5],
        4 => ['instance_id' => 4, 'part_of_city' => 1, 'sequence' => 4, 'instance_level' => 150, 'instance_name' => '上杉谦信', 'end_attack_reward' => 10, 'position1' => 0, 'position2' => 0, 'position3' => 8, 'position4' => 8, 'position5' => 8, 'position6' => 8, 'position7' => 7, 'position8' => 0, 'position9' => 0],
        5 => ['instance_id' => 5, 'part_of_city' => 2, 'sequence' => 1, 'instance_level' => 160, 'instance_name' => '山县昌景', 'end_attack_reward' => 10, 'position1' => 10, 'position2' => 0, 'position3' => 0, 'position4' => 10, 'position5' => 0, 'position6' => 9, 'position7' => 0, 'position8' => 10, 'position9' => 10],
        6 => ['instance_id' => 6, 'part_of_city' => 2, 'sequence' => 2, 'instance_level' => 170, 'instance_name' => '高坂昌信', 'end_attack_reward' => 10, 'position1' => 12, 'position2' => 0, 'position3' => 12, 'position4' => 12, 'position5' => 0, 'position6' => 12, 'position7' => 0, 'position8' => 11, 'position9' => 0],
        7 => ['instance_id' => 7, 'part_of_city' => 2, 'sequence' => 3, 'instance_level' => 180, 'instance_name' => '真田幸村', 'end_attack_reward' => 10, 'position1' => 14, 'position2' => 0, 'position3' => 14, 'position4' => 0, 'position5' => 13, 'position6' => 0, 'position7' => 14, 'position8' => 0, 'position9' => 14],
        8 => ['instance_id' => 8, 'part_of_city' => 2, 'sequence' => 4, 'instance_level' => 190, 'instance_name' => '武田信玄', 'end_attack_reward' => 10, 'position1' => 0, 'position2' => 16, 'position3' => 0, 'position4' => 16, 'position5' => 15, 'position6' => 16, 'position7' => 0, 'position8' => 16, 'position9' => 0],
        9 => ['instance_id' => 9, 'part_of_city' => 3, 'sequence' => 1, 'instance_level' => 200, 'instance_name' => '服部半藏', 'end_attack_reward' => 10, 'position1' => 0, 'position2' => 18, 'position3' => 0, 'position4' => 18, 'position5' => 17, 'position6' => 18, 'position7' => 0, 'position8' => 18, 'position9' => 0],
        10 => ['instance_id' => 10, 'part_of_city' => 3, 'sequence' => 2, 'instance_level' => 210, 'instance_name' => '酒井忠次', 'end_attack_reward' => 10, 'position1' => 0, 'position2' => 0, 'position3' => 20, 'position4' => 20, 'position5' => 20, 'position6' => 20, 'position7' => 19, 'position8' => 0, 'position9' => 0],
        11 => ['instance_id' => 11, 'part_of_city' => 3, 'sequence' => 3, 'instance_level' => 220, 'instance_name' => '本多忠胜', 'end_attack_reward' => 10, 'position1' => 22, 'position2' => 0, 'position3' => 0, 'position4' => 22, 'position5' => 0, 'position6' => 21, 'position7' => 0, 'position8' => 22, 'position9' => 22],
        12 => ['instance_id' => 12, 'part_of_city' => 3, 'sequence' => 4, 'instance_level' => 230, 'instance_name' => '德川家康', 'end_attack_reward' => 10, 'position1' => 24, 'position2' => 0, 'position3' => 24, 'position4' => 24, 'position5' => 0, 'position6' => 24, 'position7' => 0, 'position8' => 23, 'position9' => 0],
        13 => ['instance_id' => 13, 'part_of_city' => 4, 'sequence' => 1, 'instance_level' => 240, 'instance_name' => '柴田胜家', 'end_attack_reward' => 10, 'position1' => 26, 'position2' => 0, 'position3' => 0, 'position4' => 26, 'position5' => 0, 'position6' => 25, 'position7' => 0, 'position8' => 26, 'position9' => 26],
        14 => ['instance_id' => 14, 'part_of_city' => 4, 'sequence' => 2, 'instance_level' => 250, 'instance_name' => '明智光秀', 'end_attack_reward' => 10, 'position1' => 0, 'position2' => 0, 'position3' => 28, 'position4' => 28, 'position5' => 28, 'position6' => 28, 'position7' => 27, 'position8' => 0, 'position9' => 0],
        15 => ['instance_id' => 15, 'part_of_city' => 4, 'sequence' => 3, 'instance_level' => 260, 'instance_name' => '羽柴秀吉', 'end_attack_reward' => 10, 'position1' => 30, 'position2' => 0, 'position3' => 0, 'position4' => 30, 'position5' => 0, 'position6' => 30, 'position7' => 30, 'position8' => 29, 'position9' => 0],
        16 => ['instance_id' => 16, 'part_of_city' => 4, 'sequence' => 4, 'instance_level' => 270, 'instance_name' => '织田信长', 'end_attack_reward' => 10, 'position1' => 23, 'position2' => 0, 'position3' => 25, 'position4' => 0, 'position5' => 31, 'position6' => 0, 'position7' => 27, 'position8' => 0, 'position9' => 29],
    ];
}
