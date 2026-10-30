<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 武斗会积分商城表（旧版 zc_sanguo_war_shop）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class WarShop
{
    public const string TABLE = 'zc_sanguo_war_shop';

    /**
     * 按主键 id 索引，共 12 行。字段：
     *   - id (int) 主键ID
     *   - type (int) 宝箱类型
     *   - name (varchar) 宝箱名称
     *   - desc (varchar) 宝箱描述
     *   - gettype1 (int) 道具1类型
     *   - getvalue1 (int) 道具1参数
     *   - gettype2 (int) 道具2类型
     *   - getvalue2 (int) 道具2参数
     *   - gettype3 (int) 道具3类型
     *   - getvalue3 (int) 道具3参数
     *   - gettype4 (int) 道具4类型
     *   - getvalue4 (int) 道具4参数
     *   - gettype5 (int) 道具5类型
     *   - getvalue5 (int) 道具5参数1
     *   - getvalue52 (int) 类型5参数2
     *   - integral (int) 所需积分
     */
    public const array ROWS = [
        1 => ['id' => 1, 'type' => 6, 'name' => '神话宝箱', 'desc' => '主公们参加武斗会的战利品，内藏海量珍贵道具。', 'gettype1' => 11, 'getvalue1' => 1530000, 'gettype2' => 12, 'getvalue2' => 28620, 'gettype3' => 13, 'getvalue3' => 3820, 'gettype4' => 7, 'getvalue4' => 72, 'gettype5' => 7, 'getvalue5' => 73, 'getvalue52' => 0, 'integral' => 36000],
        2 => ['id' => 2, 'type' => 6, 'name' => '神话宝箱', 'desc' => '主公们参加武斗会的战利品，内藏海量珍贵道具。', 'gettype1' => 11, 'getvalue1' => 1170000, 'gettype2' => 12, 'getvalue2' => 21825, 'gettype3' => 13, 'getvalue3' => 2910, 'gettype4' => 7, 'getvalue4' => 74, 'gettype5' => 7, 'getvalue5' => 75, 'getvalue52' => 0, 'integral' => 30000],
        3 => ['id' => 3, 'type' => 5, 'name' => '至尊宝箱', 'desc' => '主公们参加武斗会的战利品，内藏海量珍贵道具。', 'gettype1' => 11, 'getvalue1' => 850000, 'gettype2' => 12, 'getvalue2' => 15948, 'gettype3' => 13, 'getvalue3' => 2125, 'gettype4' => 0, 'getvalue4' => 0, 'gettype5' => 15, 'getvalue5' => 1, 'getvalue52' => 5, 'integral' => 24000],
        4 => ['id' => 4, 'type' => 5, 'name' => '至尊宝箱', 'desc' => '主公们参加武斗会的战利品，内藏大量珍贵道具。', 'gettype1' => 5, 'getvalue1' => 54000000, 'gettype2' => 11, 'getvalue2' => 921120, 'gettype3' => 13, 'getvalue3' => 1535, 'gettype4' => 10, 'getvalue4' => 100, 'gettype5' => 0, 'getvalue5' => 0, 'getvalue52' => 0, 'integral' => 19000],
        5 => ['id' => 5, 'type' => 4, 'name' => '白金宝箱', 'desc' => '主公们参加武斗会的战利品，内藏大量珍贵道具。', 'gettype1' => 5, 'getvalue1' => 36000000, 'gettype2' => 11, 'getvalue2' => 618240, 'gettype3' => 12, 'getvalue3' => 12880, 'gettype4' => 0, 'getvalue4' => 0, 'gettype5' => 15, 'getvalue5' => 13, 'getvalue52' => 5, 'integral' => 14000],
        6 => ['id' => 6, 'type' => 4, 'name' => '白金宝箱', 'desc' => '主公们参加武斗会的战利品，内藏大量珍贵道具。', 'gettype1' => 5, 'getvalue1' => 23500000, 'gettype2' => 12, 'getvalue2' => 5025, 'gettype3' => 13, 'getvalue3' => 670, 'gettype4' => 0, 'getvalue4' => 0, 'gettype5' => 15, 'getvalue5' => 7, 'getvalue52' => 5, 'integral' => 10000],
        7 => ['id' => 7, 'type' => 3, 'name' => '高级宝箱', 'desc' => '主公们参加武斗会的战利品，内藏大量珍贵道具。', 'gettype1' => 5, 'getvalue1' => 17000000, 'gettype2' => 11, 'getvalue2' => 292800, 'gettype3' => 13, 'getvalue3' => 490, 'gettype4' => 10, 'getvalue4' => 21, 'gettype5' => 0, 'getvalue5' => 0, 'getvalue52' => 0, 'integral' => 8000],
        8 => ['id' => 8, 'type' => 3, 'name' => '高级宝箱', 'desc' => '主公们参加武斗会的战利品，内藏大量珍贵道具。', 'gettype1' => 5, 'getvalue1' => 11700000, 'gettype2' => 11, 'getvalue2' => 200160, 'gettype3' => 12, 'getvalue3' => 4170, 'gettype4' => 10, 'getvalue4' => 22, 'gettype5' => 0, 'getvalue5' => 0, 'getvalue52' => 0, 'integral' => 6000],
        9 => ['id' => 9, 'type' => 2, 'name' => '加强宝箱', 'desc' => '主公们参加武斗会的战利品，内藏珍贵道具。', 'gettype1' => 1, 'getvalue1' => 20000000, 'gettype2' => 5, 'getvalue2' => 10160000, 'gettype3' => 9, 'getvalue3' => 1020, 'gettype4' => 7, 'getvalue4' => 59, 'gettype5' => 7, 'getvalue5' => 68, 'getvalue52' => 0, 'integral' => 4000],
        10 => ['id' => 10, 'type' => 2, 'name' => '加强宝箱', 'desc' => '主公们参加武斗会的战利品，内藏珍贵道具。', 'gettype1' => 1, 'getvalue1' => 15000000, 'gettype2' => 5, 'getvalue2' => 6990000, 'gettype3' => 9, 'getvalue3' => 700, 'gettype4' => 7, 'getvalue4' => 62, 'gettype5' => 7, 'getvalue5' => 65, 'getvalue52' => 0, 'integral' => 3000],
        11 => ['id' => 11, 'type' => 1, 'name' => '普通宝箱', 'desc' => '主公们参加武斗会的战利品，内藏珍贵道具。', 'gettype1' => 1, 'getvalue1' => 10000000, 'gettype2' => 5, 'getvalue2' => 4300000, 'gettype3' => 9, 'getvalue3' => 430, 'gettype4' => 0, 'getvalue4' => 0, 'gettype5' => 0, 'getvalue5' => 0, 'getvalue52' => 0, 'integral' => 2000],
        12 => ['id' => 12, 'type' => 1, 'name' => '普通宝箱', 'desc' => '主公们参加武斗会的战利品，内藏珍贵道具。', 'gettype1' => 1, 'getvalue1' => 5000000, 'gettype2' => 5, 'getvalue2' => 2000000, 'gettype3' => 9, 'getvalue3' => 200, 'gettype4' => 0, 'getvalue4' => 0, 'gettype5' => 0, 'getvalue5' => 0, 'getvalue52' => 0, 'integral' => 1000],
    ];
}
