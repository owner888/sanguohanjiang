<?php

declare(strict_types=1);

namespace Hanjiang\Cron;

use Hanjiang\MasterData\Business;
use OmniPHP\Database\DB;

/**
 * 每日通商任务生成（移植旧版 models/business_model.php 的 builder / _generate_star / _generate_group）
 *
 * 旧版每日通商任务表 business 由后台调用 builder($day) 预先生成（旧版代码包里没有调用方，是运维工具），旧版数据（MasterData
 * Business）只到 2017-10-11。新版由 server.php 在 HTTP Worker #0 启动时与每 INTERVAL 秒调用 run()：今天起 DAYS 天里
 * MasterData 和 business 表都没有任务的日期，按旧版 builder 每天生成初级 / 中级 / 高级三条（星级 2~3 / 4~5 / 6~7，
 * 商人组合为星级个随机商人 a~l）；(date, type) 唯一，多次执行不会重复。
 */
final class BusinessCron
{
    /** 执行间隔（秒） */
    public const int INTERVAL = 3600;
    /** 预先生成的天数（含今天） */
    public const int DAYS = 3;

    /**
     * @return int 本次生成的任务条数
     */
    public static function run(int $now): int
    {
        $added = 0;
        for ($i = 0; $i < self::DAYS; $i++) {
            $date = (int)date('Ymd', strtotime('+' . $i . ' day', $now));
            if (self::hasTasks($date)) {
                continue;
            }
            for ($type = 1; $type < 4; $type++) {
                $star = self::generateStar($type);
                $added += DB::execute(
                    'INSERT IGNORE INTO `zc_sanguo_business` (`date`, `type`, `star`, `group`) VALUES (:date, :type, :star, :group)',
                    ['date' => $date, 'type' => $type, 'star' => $star, 'group' => self::generateGroup($star)]
                );
            }
        }
        return $added;
    }

    /**
     * 某天的每日通商任务（business_model->get_info_by_date：旧版数据取 MasterData，之后的日期取 business 表）
     *
     * @return list<array{id: int, date: int, type: int, star: int, group: string}>
     */
    public static function tasks(int $ymd): array
    {
        $list = array_values(array_filter(Business::ROWS, static fn(array $v): bool => $v['date'] === $ymd));
        if ($list !== []) {
            return $list;
        }
        $rows = DB::table('business')->select('id', 'date', 'type', 'star', 'group')->where('date', $ymd)->whereNull('deleted_at')
            ->orderBy('type', 'ASC')->get();
        return array_map(static fn(array $v): array => [
            'id' => (int)$v['id'],
            'date' => (int)$v['date'],
            'type' => (int)$v['type'],
            'star' => (int)$v['star'],
            'group' => (string)$v['group'],
        ], $rows);
    }

    private static function hasTasks(int $ymd): bool
    {
        return self::tasks($ymd) !== [];
    }

    /**
     * 生成任务星级（_generate_star：1 初级 2~3、2 中级 4~5、3 高级 6~7）
     */
    private static function generateStar(int $type): int
    {
        return match ($type) {
            1 => mt_rand(2, 3),
            2 => mt_rand(4, 5),
            3 => mt_rand(6, 7),
            default => 0,
        };
    }

    /**
     * 生成商人组合（_generate_group：星级个随机商人，用 a~l 表示 1~12）
     */
    private static function generateGroup(int $star): string
    {
        $str = '';
        while ($star > 0) {
            $star--;
            $str .= chr(mt_rand(1, 12) + 96);
        }
        return $str;
    }
}
