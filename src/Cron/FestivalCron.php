<?php

declare(strict_types=1);

namespace Hanjiang\Cron;

use Hanjiang\Handler\Activity\ActCelebrationHandler;
use Hanjiang\Handler\Activity\ActHalloweenHandler;
use Hanjiang\Handler\Activity\ActMoonHandler;
use Hanjiang\Handler\Activity\ActNationalDayHandler;
use Hanjiang\Handler\Activity\ActTanabataHandler;
use Hanjiang\Handler\Activity\ChingmingHandler;
use Hanjiang\Handler\Activity\ChristmasbuyHandler;
use Hanjiang\Handler\Activity\NewyearpayHandler;

/**
 * 节日活动结束后的补发计划任务（第二批：七夕、国家庆典、中秋、国庆、万圣节、圣诞团购、清明 / 祭拜仙神、充值返利）
 *
 * 由 server.php 在 HTTP Worker #0 上每 INTERVAL 秒逐个执行 JOBS（货币流水的 class / method 记旧版计划任务的控制器与方法）。
 * 旧版代码包里没有 crontab：这些任务都只在活动结束后的一段窗口内发放（多数为 endtime ~ endtime + 10 小时，万圣节 1 小时，
 * 国家庆典 endtime - 1 天 ~ endtime + 1 天），已发放的有领取记录 / 处理标记，按小时执行即可在窗口内补发且不重复。
 * 万圣节的窗口只有 1 小时，按每 INTERVAL（10 分钟）执行保证落进窗口。
 */
final class FestivalCron
{
    /** 执行间隔（秒） */
    public const int INTERVAL = 600;

    /** [旧版计划任务控制器, 方法, 执行函数] */
    public const array JOBS = [
        ['cron_acttask', 'acttanabata_reward', [ActTanabataHandler::class, 'cronReward']],
        ['cron_acttask', 'actcelebration_reward', [ActCelebrationHandler::class, 'cronReward']],
        ['cron_acttask', 'clear_moon', [ActMoonHandler::class, 'cronReward']],
        ['cron_acttask', 'get_nationalday', [ActNationalDayHandler::class, 'cronReward']],
        ['cron_acttask', 'get_helloween_reward', [ActHalloweenHandler::class, 'cronReward']],
        ['cron_acttask', 'get_christmasbuy', [ChristmasbuyHandler::class, 'cronReward']],
        ['cron', 'get_chingming_reward', [ChingmingHandler::class, 'cronRankReward']],
        ['cron', 'get_chingming_achievement_reward', [ChingmingHandler::class, 'cronAchievementReward']],
        ['cron', 'push_pay_reward', [NewyearpayHandler::class, 'cronPushPayReward']],
    ];

    /**
     * 全部执行一次（测试与手动补发用；server.php 按 JOBS 逐个设置流水路由后执行）
     *
     * @return array<string, int> 旧版方法名 => 返回值
     */
    public static function run(int $now): array
    {
        $return = [];
        foreach (self::JOBS as [, $method, $job]) {
            $return[$method] = $job($now);
        }
        return $return;
    }
}
