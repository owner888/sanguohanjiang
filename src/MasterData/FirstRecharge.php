<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * 首充返利数据表（旧版 zc_sanguo_first_recharge）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class FirstRecharge
{
    public const string TABLE = 'zc_sanguo_first_recharge';

    /**
     * 按主键 id 索引，共 6 行。字段：
     *   - id (int) 主键ID
     *   - gold (int) 充值元宝
     *   - name (varchar) 礼包内容
     *   - gettype1 (int) 奖励类型1
     *   - getvalue1 (int) 奖励参数1
     *   - gettype2 (int) 奖励类型2
     *   - getvalue2 (int) 奖励参数2
     *   - gettype3 (int) 奖励类型3
     *   - getvalue3 (int) 奖励参数3
     *   - gettype4 (int) 奖励类型4
     *   - getvalue4 (int) 奖励参数4
     */
    public const array ROWS = [
        1 => ['id' => 1, 'gold' => 6480, 'name' => '首充6480元宝，赠送6480元宝(额度100%)', 'gettype1' => 5, 'getvalue1' => 6480, 'gettype2' => 1, 'getvalue2' => 3000000, 'gettype3' => 6, 'getvalue3' => 500, 'gettype4' => 3, 'getvalue4' => 500],
        2 => ['id' => 2, 'gold' => 3280, 'name' => '首充3280元宝，赠送3280元宝(额度100%)', 'gettype1' => 5, 'getvalue1' => 3280, 'gettype2' => 1, 'getvalue2' => 2000000, 'gettype3' => 6, 'getvalue3' => 400, 'gettype4' => 3, 'getvalue4' => 400],
        3 => ['id' => 3, 'gold' => 1980, 'name' => '首充1980元宝，赠送1980元宝(额度100%)', 'gettype1' => 5, 'getvalue1' => 1980, 'gettype2' => 1, 'getvalue2' => 1500000, 'gettype3' => 6, 'getvalue3' => 300, 'gettype4' => 3, 'getvalue4' => 300],
        4 => ['id' => 4, 'gold' => 680, 'name' => '首充680元宝，赠送680元宝(额度100%)', 'gettype1' => 5, 'getvalue1' => 680, 'gettype2' => 1, 'getvalue2' => 100000, 'gettype3' => 6, 'getvalue3' => 200, 'gettype4' => 3, 'getvalue4' => 200],
        5 => ['id' => 5, 'gold' => 300, 'name' => '首充300元宝，赠送300元宝(额度100%)', 'gettype1' => 5, 'getvalue1' => 300, 'gettype2' => 1, 'getvalue2' => 800000, 'gettype3' => 6, 'getvalue3' => 100, 'gettype4' => 3, 'getvalue4' => 100],
        6 => ['id' => 6, 'gold' => 60, 'name' => '首充60元宝，赠送60元宝(额度100%)', 'gettype1' => 5, 'getvalue1' => 60, 'gettype2' => 1, 'getvalue2' => 500000, 'gettype3' => 6, 'getvalue3' => 50, 'gettype4' => 3, 'getvalue4' => 50],
    ];
}
