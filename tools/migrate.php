<?php

declare(strict_types=1);

/**
 * 数据库迁移（命令行工具；服务 start / restart 时 master 已自动执行 migrate，一般用不到本工具）
 *
 * 用法:
 *   php tools/migrate.php [migrate]          执行待执行的迁移（空库时第一个迁移导入 database/schema.sql）
 *   php tools/migrate.php status             已执行 / 待执行的迁移
 *   php tools/migrate.php rollback [批次数]   回滚最后 N 个批次（默认 1）：执行 down() 并删除记录
 *   php tools/migrate.php reset --force      回滚全部迁移（逐个 down()）并清空记录
 *   php tools/migrate.php refresh --force    reset 后重新 migrate
 *
 * reset / refresh 会对每个迁移执行 down()（可能删列删表），必须加 --force。
 * 0000_00_00_000000_import_base_schema 的 down() 是空操作：回滚只删记录，不会删整套游戏表，再次 migrate 时也是空操作。
 * 连哪个库按 config/database.php（环境变量 / .env 的 DB_HOST、DB_DATABASE 等），例如 DB_DATABASE=zc_sanguo_new php tools/migrate.php status
 */

use Hanjiang\Support\Migrations;
use OmniPHP\Config;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/bootstrap.php';

$args = array_slice($argv, 1);
$force = in_array('--force', $args, true);
$args = array_values(array_filter($args, static fn (string $arg): bool => $arg !== '--force'));
$command = $args[0] ?? 'migrate';

$usage = "用法: php tools/migrate.php [migrate|status|rollback [批次数]|reset --force|refresh --force]\n";
if (!in_array($command, ['migrate', 'status', 'rollback', 'reset', 'refresh'], true)) {
    fwrite(STDERR, "未知命令: {$command}\n{$usage}");
    exit(1);
}
if (in_array($command, ['reset', 'refresh'], true) && !$force) {
    fwrite(STDERR, "{$command} 会对所有迁移执行 down()，确认要执行请加 --force\n");
    exit(1);
}

$printList = static function (string $title, array $names, string $mark): void {
    fwrite(STDOUT, $title . '：' . count($names) . " 个\n");
    foreach ($names as $name) {
        fwrite(STDOUT, "  {$mark} {$name}\n");
    }
};

try {
    Migrations::connect();
    fwrite(STDOUT, '数据库: ' . (string)Config::get('database.mysql.database') . "\n");
    $runner = Migrations::runner();

    switch ($command) {
        case 'migrate':
            $printList('执行', $runner->migrate(), '✓');
            break;
        case 'status':
            $status = $runner->status();
            $printList('已执行', $status['executed_list'], '✓');
            $printList('待执行', $status['pending_list'], '•');
            break;
        case 'rollback':
            $steps = max(1, (int)($args[1] ?? 1));
            $printList("回滚最后 {$steps} 个批次", Migrations::rollback($steps), "↩");
            break;
        case 'reset':
            $printList('重置', $runner->reset(), '↩');
            break;
        case 'refresh':
            $printList('重新执行', $runner->refresh(), '✓');
            break;
    }
} catch (Throwable $e) {
    fwrite(STDERR, "失败: {$e->getMessage()}\n  {$e->getFile()}:{$e->getLine()}\n");
    exit(1);
}
