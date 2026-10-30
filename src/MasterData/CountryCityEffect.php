<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 城池占领效果表（旧版 zc_sanguo_country_city_effect）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class CountryCityEffect
{
    public const string TABLE = 'zc_sanguo_country_city_effect';

    /**
     * 按主键 id 索引，共 13 行。字段：
     *   - id (smallint) 城池id
     *   - name (varchar) 城池名字
     *   - time (smallint) 国战开始时间
     *   - type (tinyint) 奖励类型（1征收，2通商，玉石采集）
     *   - value (int) 奖励参数
     *   - team (varchar) 所属
     */
    public const array ROWS = [
        1 => ['id' => 1, 'name' => '濮阳', 'time' => 1830, 'type' => 1, 'value' => 3, 'team' => '张角'],
        2 => ['id' => 2, 'name' => '邺城', 'time' => 1840, 'type' => 1, 'value' => 6, 'team' => '袁绍'],
        3 => ['id' => 3, 'name' => '北平', 'time' => 1850, 'type' => 1, 'value' => 10, 'team' => '公孙瓒'],
        4 => ['id' => 4, 'name' => '长安', 'time' => 1900, 'type' => 2, 'value' => 30, 'team' => '董卓'],
        5 => ['id' => 5, 'name' => '武威', 'time' => 1910, 'type' => 2, 'value' => 45, 'team' => '马腾'],
        6 => ['id' => 6, 'name' => '襄阳', 'time' => 1920, 'type' => 2, 'value' => 60, 'team' => '刘表'],
        7 => ['id' => 7, 'name' => '寿春', 'time' => 1930, 'type' => 4, 'value' => 100, 'team' => '袁术'],
        8 => ['id' => 8, 'name' => '下邳', 'time' => 1940, 'type' => 3, 'value' => 400, 'team' => '吕布'],
        9 => ['id' => 9, 'name' => '许昌', 'time' => 1950, 'type' => 5, 'value' => 400, 'team' => '曹操'],
        10 => ['id' => 10, 'name' => '成都', 'time' => 2000, 'type' => 6, 'value' => 5, 'team' => '刘备'],
        11 => ['id' => 11, 'name' => '吴郡', 'time' => 2010, 'type' => 6, 'value' => 10, 'team' => '孙权'],
        12 => ['id' => 12, 'name' => '蛮王洞', 'time' => 2020, 'type' => 7, 'value' => 200, 'team' => '孟获'],
        13 => ['id' => 13, 'name' => '匈奴王庭', 'time' => 2030, 'type' => 7, 'value' => 400, 'team' => '大帝阿提拉'],
    ];
}
