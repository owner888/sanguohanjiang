<?php

declare(strict_types=1);

namespace Hanjiang\Cron;

use Hanjiang\Handler\Activity\ActEnrichmentHandler;
use Hanjiang\Handler\Activity\ActKemariHandler;
use Hanjiang\Handler\Activity\ActPaychecksHandler;
use Hanjiang\Handler\Activity\ActSevenstarHandler;
use Hanjiang\Handler\Activity\ActThirtyHandler;
use Hanjiang\Handler\Activity\ActTreasureHandler;

/**
 * 节日活动第三批的计划任务（寻宝、蹴鞠 / 世界杯、充值送豪礼 / 累计登录、招募战友领工资、貔貅聚财、七星灯）
 *
 * 由 server.php 在 HTTP Worker #0 上执行（货币流水的 class / method 记旧版计划任务的控制器与方法）。旧版代码包里没有 crontab：
 * - DAILY：旧版注释为「每天零点」的任务，每日 RUN_AT 执行一次（cron_acttask::treasure&type=1、cron_acttask::new_pay_logined、
 *   cron_kemari::index）；
 * - STAT：招募战友统计 cron_acttask::action_paychecks，旧版注释「半小时执行一次」；
 * - JOBS：其余只在活动结束后的窗口内发放的任务（领工资 1 小时、貔貅聚财 1 小时、七星灯 10 小时），已发放的有领取记录 / 处理标记，
 *   按 INTERVAL 执行即可在窗口内补发且不重复。
 */
final class Fest3Cron
{
    /** 每天零点任务的执行时间 */
    public const string RUN_AT = '00:00';

    /** 补发任务的执行间隔（秒） */
    public const int INTERVAL = 600;

    /** 招募战友统计的执行间隔（秒，旧版注释「半小时执行一次」） */
    public const int STAT_INTERVAL = 1800;

    /** 每天零点：[旧版计划任务控制器, 方法, 执行函数] */
    public const array DAILY = [
        ['cron_acttask', 'treasure', [ActTreasureHandler::class, 'cronTreasure']],
        ['cron_acttask', 'new_pay_logined', [ActThirtyHandler::class, 'cronNewPayLogined']],
        ['cron_kemari', 'index', [ActKemariHandler::class, 'cronIndex']],
    ];

    /** 招募战友统计 */
    public const array STAT = ['cron_acttask', 'action_paychecks', [ActPaychecksHandler::class, 'cronStat']];

    /** 活动结束后的补发：[旧版计划任务控制器, 方法, 执行函数] */
    public const array JOBS = [
        ['cron_acttask', 'action_paychecks_reward', [ActPaychecksHandler::class, 'cronReward']],
        ['cron_acttask', 'get_actenrichment_reward', [ActEnrichmentHandler::class, 'cronReward']],
        ['cron_acttask', 'sevenstar_task', [ActSevenstarHandler::class, 'cronTask']],
    ];

    /**
     * 全部执行一次（测试与手动补发用；server.php 按上面的表逐个设置流水路由后执行）
     *
     * @return array<string, mixed> 旧版方法名 => 返回值
     */
    public static function run(int $now): array
    {
        $return = [];
        foreach ([...self::DAILY, self::STAT, ...self::JOBS] as [, $method, $job]) {
            $return[$method] = $job($now);
        }
        return $return;
    }
}
