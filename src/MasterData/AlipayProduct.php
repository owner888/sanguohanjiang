<?php

declare(strict_types=1);

namespace Hanjiang\MasterData;

/**
 * alipay_product（旧版 zc_sanguo_alipay_product）
 *
 * 由 tools/gen_master_data.php 从旧版 dump 生成，勿手改。
 */
final class AlipayProduct
{
    public const string TABLE = 'zc_sanguo_alipay_product';

    /**
     * 按主键 id 索引，共 12 行。字段：
     *   - id (mediumint) 商品id
     *   - subject (varchar) 标题
     *   - subject_ext (varchar) 标题扩展内容
     *   - description (varchar) 描述
     *   - amount (mediumint) 价格
     *   - gold (int) 元宝数
     *   - add_gold (int) 返利元宝
     *   - type (tinyint) 1正常；2返利
     *   - dateline (int) 添加时间
     *   - endline (int) 过期时间
     */
    public const array ROWS = [
        1 => ['id' => 1, 'subject' => '60元宝', 'subject_ext' => '', 'description' => '', 'amount' => 6, 'gold' => 60, 'add_gold' => 0, 'type' => 1, 'dateline' => 0, 'endline' => 0],
        2 => ['id' => 2, 'subject' => '300元宝', 'subject_ext' => '', 'description' => '', 'amount' => 30, 'gold' => 300, 'add_gold' => 0, 'type' => 1, 'dateline' => 0, 'endline' => 0],
        3 => ['id' => 3, 'subject' => '680元宝', 'subject_ext' => '', 'description' => '', 'amount' => 68, 'gold' => 680, 'add_gold' => 0, 'type' => 1, 'dateline' => 0, 'endline' => 0],
        4 => ['id' => 4, 'subject' => '1980元宝', 'subject_ext' => '', 'description' => '', 'amount' => 198, 'gold' => 1980, 'add_gold' => 0, 'type' => 1, 'dateline' => 0, 'endline' => 0],
        5 => ['id' => 5, 'subject' => '3280元宝', 'subject_ext' => '', 'description' => '', 'amount' => 328, 'gold' => 3280, 'add_gold' => 0, 'type' => 1, 'dateline' => 0, 'endline' => 0],
        6 => ['id' => 6, 'subject' => '6480元宝', 'subject_ext' => '', 'description' => '', 'amount' => 648, 'gold' => 6480, 'add_gold' => 0, 'type' => 1, 'dateline' => 0, 'endline' => 0],
        11 => ['id' => 11, 'subject' => '100元宝', 'subject_ext' => '<font color="red">超值返利</font>', 'description' => '充60元宝 返40元宝', 'amount' => 6, 'gold' => 60, 'add_gold' => 40, 'type' => 2, 'dateline' => 0, 'endline' => 0],
        12 => ['id' => 12, 'subject' => '450元宝', 'subject_ext' => '<font color="red">超值返利</font>', 'description' => '充300元宝 返150元宝', 'amount' => 30, 'gold' => 300, 'add_gold' => 150, 'type' => 2, 'dateline' => 0, 'endline' => 0],
        13 => ['id' => 13, 'subject' => '900元宝', 'subject_ext' => '<font color="red">超值返利</font>', 'description' => '充680元宝 返220元宝', 'amount' => 68, 'gold' => 680, 'add_gold' => 220, 'type' => 2, 'dateline' => 0, 'endline' => 0],
        14 => ['id' => 14, 'subject' => '2400元宝', 'subject_ext' => '<font color="red">超值返利</font>', 'description' => '充1980元宝 返420元宝', 'amount' => 198, 'gold' => 1980, 'add_gold' => 420, 'type' => 2, 'dateline' => 0, 'endline' => 0],
        15 => ['id' => 15, 'subject' => '3900元宝', 'subject_ext' => '<font color="red">超值返利</font>', 'description' => '充3280元宝 返620元宝', 'amount' => 328, 'gold' => 3280, 'add_gold' => 620, 'type' => 2, 'dateline' => 0, 'endline' => 0],
        16 => ['id' => 16, 'subject' => '7300元宝', 'subject_ext' => '<font color="red">超值返利</font>', 'description' => '充6480元宝 返820元宝', 'amount' => 648, 'gold' => 6480, 'add_gold' => 820, 'type' => 2, 'dateline' => 0, 'endline' => 0],
    ];
}
