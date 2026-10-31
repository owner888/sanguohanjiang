<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use Hanjiang\MasterData\ActivityTime;
use OmniPHP\Logger;

/**
 * 运营活动时间表的唯一读取入口（MasterData ActivityTime，旧版 activity_model 的 get_info_by_name / activity_status）
 *
 * 代码里读活动时间一律经这里，不直接读 ActivityTime::ROWS。
 *
 * 开发者测试开关（新版新增，旧版没有）：环境变量 ACTIVITY_OPEN（config/app.php activity_open）为逗号分隔的活动名或 all 时，
 * 这些活动行的 starttime 改为今天 0 点、endtime 改为「当前时间 + ACTIVITY_OPEN_DAYS 天」（默认 30），version / type 不变，
 * 用于在开发环境玩旧版数据里早已结束（或从未排期）的运营活动。未设置（默认）时原样返回旧版数据。生产环境绝不能开启。
 * all 只含 OPENABLE（已移植模块），节日 / 跨服 / 未移植的活动不开，免得主城出现点进去没反应的图标；不认识的名字忽略并记一次警告。
 *
 * 窗口在每个进程第一次读取时算好并固定（进程内不再移动）：活动「结束后 N 小时内」结算的计划任务（暴打名将、四方来朝、
 * 整军演武、过关斩将、群雄逐鹿）在窗口结束前一直是「活动未结束」，不会每次重启都结算一遍；进程跑过 endtime 时照旧版在结束后的
 * 窗口内结算一次（已领 / 已清零的不会重复）。重启后窗口重新从今天开始，version 不变，已结算的记录保留。
 */
final class ActivityClock
{
    /**
     * ACTIVITY_OPEN=all 开启的活动：被已移植代码按活动时间读取的活动名（grep src 得出）。
     *
     * 不含：节日充值返利 christmaspay / newyearpay、春节送福 act_newyear、寻宝 acttreasure、战神殿 god（只在主城图标里读，
     * 模块未移植）、双倍强化 double_strengthen（未移植）、国庆 national_day（未移植），以及其余没有代码读取的节日 / 跨服活动。
     */
    public const array OPENABLE = [
        'sacrifice', 'exercise', 'flop', 'dailysign', 'rebate', 'blackmarket', 'jadegamble', 'beatengod', 'jump_rebate',
        'sacredtree', 'compete', 'viprepay', 'pass_general', 'total_login', 'year_happy', 'login_reward',
        'double_tower', 'double_heaven', 'double_mine', 'double_refine', 'double_talent', 'double_vip', 'double_beauty',
        'double_miss', 'double_sanctum', 'double_hero', 'double_copies',
    ];

    /** 默认开放天数 */
    public const int DEFAULT_DAYS = 30;

    /** @var array<int, array{id: int, name: string, starttime: int, endtime: int, version: int, type: int}>|null 本进程的活动表 */
    private static ?array $rows = null;

    /**
     * 全部活动行（按 id 索引，同 ActivityTime::ROWS）
     *
     * @return array<int, array{id: int, name: string, starttime: int, endtime: int, version: int, type: int}>
     */
    public static function rows(): array
    {
        return self::$rows ??= self::build();
    }

    /**
     * 按名字取活动行（get_info_by_name），没有该活动为 null
     *
     * @return array{id: int, name: string, starttime: int, endtime: int, version: int, type: int}|null
     */
    public static function byName(string $name): ?array
    {
        foreach (self::rows() as $row) {
            if ($row['name'] === $name) {
                return $row;
            }
        }
        return null;
    }

    /**
     * 活动开启时返回活动行（activity_status：starttime <= now <= endtime），否则 null
     *
     * @return array{id: int, name: string, starttime: int, endtime: int, version: int, type: int}|null
     */
    public static function open(string $name, int $now): ?array
    {
        $row = self::byName($name);
        return $row !== null && $row['starttime'] <= $now && $row['endtime'] >= $now ? $row : null;
    }

    public static function isOpen(string $name, int $now): bool
    {
        return self::open($name, $now) !== null;
    }

    /**
     * 测试用：丢弃本进程算好的活动表（改了 ACTIVITY_OPEN 环境变量后重新读取）
     */
    public static function reset(): void
    {
        self::$rows = null;
    }

    /**
     * @return array<int, array{id: int, name: string, starttime: int, endtime: int, version: int, type: int}>
     */
    private static function build(): array
    {
        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        $names = self::openNames((string)($appConfig['activity_open'] ?? ''));
        if ($names === []) {
            return ActivityTime::ROWS;
        }
        $now = time();
        $start = (int)strtotime(date('Y-m-d', $now));
        $end = $now + max(0, (int)($appConfig['activity_open_days'] ?? self::DEFAULT_DAYS)) * 86400;
        $rows = ActivityTime::ROWS;
        foreach ($rows as $id => $row) {
            if (in_array($row['name'], $names, true)) {
                $rows[$id]['starttime'] = $start;
                $rows[$id]['endtime'] = $end;
            }
        }
        return $rows;
    }

    /**
     * ACTIVITY_OPEN 解析成要开启的活动名（all → OPENABLE；不认识的名字记警告后忽略）
     *
     * @return list<string>
     */
    private static function openNames(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        if (strtolower($value) === 'all') {
            return self::OPENABLE;
        }
        $known = array_column(ActivityTime::ROWS, 'name');
        $names = [];
        $unknown = [];
        foreach (explode(',', $value) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            if (in_array($name, $known, true)) {
                $names[] = $name;
            } else {
                $unknown[] = $name;
            }
        }
        if ($unknown !== []) {
            Logger::warning('[ActivityClock] ACTIVITY_OPEN 忽略不认识的活动名: ' . implode(',', $unknown));
        }
        return array_values(array_unique($names));
    }
}
