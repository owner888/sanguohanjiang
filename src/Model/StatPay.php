<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Database\DB;

/**
 * 付费点统计（移植旧版 models/stat_model.php 的 __call → _update_stat_pay：stat_pay 表按付费点累计次数与元宝）
 *
 * 旧版调用 $this->stat_model->xxx($gold)：当前主公等级 >= 16 且 xxx 在 _pay_point 列表里才累计（times + 1、gold + $gold），
 * 其余调用（如 countryboss_relive、elite_times）是空操作。
 *
 * 与旧版的差别：旧版先 SELECT 再 INSERT / UPDATE，多进程并发首次记录会插出两行同名付费点；新版 name 加唯一键，
 * 用 INSERT … ON DUPLICATE KEY UPDATE 原子累计。ymd（统计截止日期）旧版此处不写，恒 0。
 */
final class StatPay
{
    /** 旧版 _pay_point 的键（付费点名称） */
    public const array PAY_POINTS = [
        'qianghua_cd', 'zhengshou_cd', 'zhengzhan_cd', 'yanwubang_cd', 'tufei', 'maoyi', 'xunlian', 'xishuxing',
        'qianggong', 'qiangzheng', 'up_xunlianwei', 'buy_xunlianwei', 'choujiang_times', 'choujiang_shuaxin',
        'yanwubang_times', 'buy_bingfu', 'buy_junling', 'qianghua_rate', 'worldboss_relive', 'worldboss_powerup',
        'chat_horn', 'task_refresh', 'business_buy', 'business_refresh', 'map_refresh', 'map_levelup', 'unlock_pack',
        'tower_buy', 'tower_jiabei', 'sacrifice', 'drink', 'matrixlevelup', 'mooncake_buy', 'mooncake_refresh',
        'general_inherit', 'create_country', 'country_donate', 'generaltask_battle', 'generaltask_times',
        'country_sacrifice', 'put_jianyan', 'refresh_jianyan', 'buystone', 'worldarena_buytimes', 'worldarena_report',
    ];

    /** 旧版 __call：主公等级 >= 16 才统计 */
    private const int MIN_LEVEL = 16;

    /**
     * 记一次付费点消费（$uid 为当前玩家，取其等级判断）
     */
    public static function record(int $uid, string $name, int $gold): void
    {
        if (!in_array($name, self::PAY_POINTS, true)) {
            return;
        }
        $info = Member::logInfo($uid);
        if ($info === null || $info['level'] < self::MIN_LEVEL) {
            return;
        }
        DB::execute(
            'INSERT INTO `zc_sanguo_stat_pay` (`name`, `times`, `gold`) VALUES (:name, 1, :gold)
             ON DUPLICATE KEY UPDATE `times` = `times` + 1, `gold` = `gold` + :gold2',
            ['name' => $name, 'gold' => max(0, $gold), 'gold2' => max(0, $gold)]
        );
    }
}
