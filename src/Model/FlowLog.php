<?php

declare(strict_types=1);

namespace Hanjiang\Model;

use OmniPHP\Database\DB;
use Workerman\Coroutine\Context as FiberContext;

/**
 * 货币流水（移植旧版 models/member_model.php 的 _gold_log / _silver_log / _act_log / _reputation_log / _soul_log）
 *
 * 一条流水记：uid、当时的玩家等级与 VIP、type（1 获得 / 2 花费）、操作数、变动后的余额、class / method（旧版取当前请求的
 * 控制器 / 方法名 $GLOBALS['class'] / ['method']）、argument（旧版取未定义的 $this->action，恒为空串）、dateline。
 *
 * class / method 的来源：GameProtocolMiddleware 在分发前用 route() 记下本次请求的 c / m，handler 返回后清空；
 * 全服计划任务（server.php 的 Timer）与多人副本开战（EliteRoom）执行前自己 route(...)。
 * 存放在 Workerman\Coroutine\Context（按当前 Fiber 隔离）：Fiber 事件循环下每个连接事件 / 定时器回调都在独立 Fiber 里执行，
 * 即使以后出现中途挂起的异步 IO，并发请求也不会互相串值；不在 Fiber 里（PHPUnit、命令行工具）时退化为进程级的一份。
 *
 * 由 Member::deductCurrency / addCurrency / addReputation / subReputation 与 OwnSoul 的原魂增减调用，调用方不用自己记。
 * 依赖未移植：_gold_log 在国庆节活动（activity_status('national_day')）期间另记 national_day_gold_log（节日活动，旧版数据中已结束）。
 */
final class FlowLog
{
    /** 流水表 => 记操作数的列（usesoul_log 旧版就把原魂数记在 reputation 列）、method 列宽 */
    private const array TABLES = [
        'gold_log' => ['gold', 20],
        'silver_log' => ['silver', 20],
        'act_log' => ['act', 30],
        'reputation_log' => ['reputation', 20],
        'usesoul_log' => ['reputation', 20],
    ];

    public const int TYPE_ADD = 1;
    public const int TYPE_SUB = 2;

    /** Fiber 本地存储的键 */
    private const string CONTEXT_KEY = 'hanjiang.flow_log.route';

    /**
     * 记下当前请求的控制器 / 方法（流水的 class / method 列）；传空串清空
     */
    public static function route(string $class, string $method): void
    {
        FiberContext::set(self::CONTEXT_KEY, [$class, $method]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function current(): array
    {
        $route = FiberContext::get(self::CONTEXT_KEY);
        return is_array($route) ? $route : ['', ''];
    }

    /**
     * 写一条流水
     *
     * @param string $table gold_log / silver_log / act_log / reputation_log / usesoul_log
     * @param array{level: int, vip: int} $info 当时的玩家等级与 VIP
     * @param string|null $class 显式指定执行类（默认取当前请求）
     * @param string|null $method 显式指定执行方法（默认取当前请求）
     */
    public static function write(
        string $table,
        int $uid,
        int $type,
        int $num,
        int $balance,
        array $info,
        ?int $now = null,
        ?string $class = null,
        ?string $method = null,
    ): void {
        [$column, $methodWidth] = self::TABLES[$table] ?? throw new \InvalidArgumentException("流水表不支持：{$table}");
        [$currentClass, $currentMethod] = self::current();
        DB::table($table)->insert([
            'uid' => $uid,
            'level' => $info['level'],
            'type' => $type,
            $column => max(0, $num),
            'balance' => max(0, $balance),
            'class' => Member::logText($class ?? $currentClass, 20),
            'method' => Member::logText($method ?? $currentMethod, $methodWidth),
            'argument' => '',
            'dateline' => $now ?? time(),
            'vip' => $info['vip'],
        ]);
    }
}
