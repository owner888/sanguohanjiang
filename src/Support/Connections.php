<?php

declare(strict_types=1);

namespace Hanjiang\Support;

use OmniPHP\Cache\Redis;
use OmniPHP\Config;
use OmniPHP\Database\DatabaseBootstrap;
use OmniPHP\Database\DB;

/**
 * 进程级 MySQL / Redis 连接初始化
 *
 * 只能在「已经是最终进程」的地方调用：Workerman 子进程的 onWorkerStart、命令行工具、PHPUnit 与 tests/Support 子进程。
 * 严禁在 server.php 的 master 全局作用域（Worker::runAll() 之前）调用，否则 fork 出的子进程共享同一个 socket fd。
 */
final class Connections
{
    public static function init(): void
    {
        DatabaseBootstrap::initWorker();

        // DatabaseManager 建 PDO 时不读 config/database.php 的 options，这里补上（关闭模拟预处理等），
        // 与迁移前 DB::init($config) 建出的连接一致。注意框架 DB::reconnect() 断线重连时同样不读 options，迁移前后都如此。
        // initWorker() 失败只记日志不抛异常，这里 DB::connection() 会抛「未初始化」，让进程启动失败而不是带病运行
        $pdo = DB::connection();
        foreach ((array)Config::get('database.mysql.options', []) as $attribute => $value) {
            $pdo->setAttribute($attribute, $value);
        }

        // Redis::init() 不传参时不会读 key 前缀（cache.redis.prefix），必须显式传入整份配置
        Redis::init((array)Config::get('cache.redis', []));
    }
}
