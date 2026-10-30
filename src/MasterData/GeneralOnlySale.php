<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 绝版神将表（旧版 zc_sanguo_general_only_sale）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class GeneralOnlySale
{
    public const string TABLE = 'zc_sanguo_general_only_sale';

    /**
     * 按主键 id 索引，共 2 行。字段：
     *   - id (int) 自增id
     *   - generalid (int)
     *   - price1 (int)
     *   - price2 (int)
     *   - desc (varchar) 武将描述
     */
    public const array ROWS = [
        1 => ['id' => 1, 'generalid' => 180, 'price1' => 77000, 'price2' => 9000000, 'desc' => '步练师,东吴丞相步骘同族。以美丽得幸于吴帝孙权，宠冠后庭。生有二女：孙鲁班、孙鲁育。赤乌元年卒，追封为皇后，葬于蒋陵。'],
        2 => ['id' => 2, 'generalid' => 181, 'price1' => 78000, 'price2' => 8500000, 'desc' => '张辽，字文远。曹操部下“五子良将”之首。赤壁败退时，成功击退孙权十万大军，一战威震江东，名扬天下，拜为征东将军。以至东吴儿啼不肯止者，其父母以辽恐之。'],
    ];
}
