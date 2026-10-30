<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 过关斩将成就奖励表（旧版 zc_sanguo_passgeneral_achievement）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class PassgeneralAchievement
{
    public const string TABLE = 'zc_sanguo_passgeneral_achievement';

    /**
     * 按主键 id 索引，共 10 行。字段：
     *   - id (int) ID
     *   - desc (varchar) 描述
     *   - num (int) 所需次数
     *   - type1 (int) 奖品类型1
     *   - value1 (int) 参数(数量或者武将ID)
     *   - type2 (int) 奖品类型2
     *   - value2 (int) 参数(数量或者武将ID)
     *   - type3 (int) 奖品类型3
     *   - value3 (int) 参数(数量或者武将ID)
     */
    public const array ROWS = [
        1 => ['id' => 1, 'desc' => '突破所有人1次', 'num' => 1, 'type1' => 27, 'value1' => 20, 'type2' => 4, 'value2' => 100, 'type3' => 2, 'value3' => 5000000],
        2 => ['id' => 2, 'desc' => '突破所有人3次', 'num' => 3, 'type1' => 27, 'value1' => 30, 'type2' => 4, 'value2' => 200, 'type3' => 2, 'value3' => 8000000],
        3 => ['id' => 3, 'desc' => '突破所有人5次', 'num' => 5, 'type1' => 27, 'value1' => 50, 'type2' => 4, 'value2' => 300, 'type3' => 2, 'value3' => 10000000],
        4 => ['id' => 4, 'desc' => '突破所有人7次', 'num' => 7, 'type1' => 27, 'value1' => 70, 'type2' => 4, 'value2' => 500, 'type3' => 2, 'value3' => 15000000],
        5 => ['id' => 5, 'desc' => '突破所有人10次', 'num' => 10, 'type1' => 27, 'value1' => 100, 'type2' => 4, 'value2' => 800, 'type3' => 7, 'value3' => 50000],
        6 => ['id' => 6, 'desc' => '突破所有人20次', 'num' => 20, 'type1' => 27, 'value1' => 150, 'type2' => 4, 'value2' => 1000, 'type3' => 7, 'value3' => 80000],
        7 => ['id' => 7, 'desc' => '突破所有人35次', 'num' => 35, 'type1' => 27, 'value1' => 200, 'type2' => 4, 'value2' => 1500, 'type3' => 7, 'value3' => 100000],
        8 => ['id' => 8, 'desc' => '突破所有人50次', 'num' => 50, 'type1' => 27, 'value1' => 350, 'type2' => 4, 'value2' => 2000, 'type3' => 7, 'value3' => 150000],
        9 => ['id' => 9, 'desc' => '突破所有人75次', 'num' => 75, 'type1' => 27, 'value1' => 400, 'type2' => 4, 'value2' => 3000, 'type3' => 7, 'value3' => 200000],
        10 => ['id' => 10, 'desc' => '突破所有人100次', 'num' => 100, 'type1' => 27, 'value1' => 600, 'type2' => 4, 'value2' => 5000, 'type3' => 7, 'value3' => 300000],
    ];
}
