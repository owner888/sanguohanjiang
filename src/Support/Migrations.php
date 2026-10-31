<?php

declare(strict_types=1);

namespace Hanjiang\Support;

use OmniPHP\Config;
use OmniPHP\Database\DatabaseManager;
use OmniPHP\Database\DB;
use OmniPHP\Database\MigrationRunner;
use OmniPHP\Database\Schema;
use PDO;
use RuntimeException;

/**
 * 数据库迁移：OmniPHP MigrationRunner 跑 database/migrations/*.php（按文件名顺序，记录在 migrations 表）
 *
 * server.php 在 master 里 start / restart 时调用 migrateOnStart()：连库 → 跑完待执行的迁移 → 关闭连接，然后才 fork，
 * 子进程仍在 onWorkerStart 里各自 Connections::init()。tools/migrate.php 与测试用 connect() + runner()。
 *
 * 为什么不直接用 DatabaseBootstrap::init()：
 * - 它不补 config/database.php 的 PDO options（见 Connections::applyPdoOptions），失败只记日志不抛异常（会带着没迁移完的库照常启动）；
 * - 表前缀不一致：MigrationRunner 用 Schema::hasTable('migrations') / Schema::create('migrations') 判断与建表（不加前缀），
 *   却用 DB::table('migrations') 读写记录（加前缀 zc_sanguo_）。带前缀连接下它每次都会建一张不加前缀的空表，
 *   再去读写不存在的 zc_sanguo_migrations。所以迁移连接的前缀置空：记录表就叫 migrations（不加前缀），
 *   迁移文件里 Schema / DB 一律写完整表名（zc_sanguo_xxx）。
 * - 框架自建的 migrations 表没有三个时间字段（AGENTS.md 铁律 7），这里先按 TABLE_DDL 建好，ensureMigrationsTable() 看到表已存在就不再建。
 */
final class Migrations
{
    /** 迁移记录表（不加前缀，原因见类注释） */
    public const string TABLE = 'migrations';

    /** 与 database/schema.sql 里的定义一字不差（tests/Feature/MigrationTest 比对 SHOW CREATE TABLE） */
    public const string TABLE_DDL = <<<'SQL'
        CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL COMMENT '迁移文件名（不含 .php）',
            `batch` INT NOT NULL COMMENT '批次号',
            `executed_at` DATETIME NOT NULL COMMENT '执行时间',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` DATETIME DEFAULT NULL,
            UNIQUE KEY `uk_migration` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据库迁移记录（OmniPHP MigrationRunner，回滚时物理删除记录）'
        SQL;

    /**
     * 建立迁移用连接（PDO options 同 Connections，但开模拟预处理；表前缀置空）并确保 migrations 表存在
     *
     * 连接失败直接抛异常（不像 DatabaseBootstrap::initWorker() 只记日志）。
     */
    public static function connect(): void
    {
        DatabaseManager::init();
        $pdo = DB::connection();
        Connections::applyPdoOptions($pdo);
        // 唯独模拟预处理要开着：Schema::hasTable() 用 prepare('SHOW TABLES LIKE ?')，服务端预处理不接受 SHOW 里的占位符
        // （MariaDB 报 1064），MigrationRunner 每次都要先 hasTable('migrations')
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        $config = (array)Config::get('database.mysql', []);
        $config['driver'] = 'mysql';
        $config['prefix'] = '';
        DB::init($pdo, $config);

        if (!Schema::hasTable(self::TABLE)) {
            $pdo->exec(self::TABLE_DDL);
        }
    }

    public static function runner(): MigrationRunner
    {
        return new MigrationRunner(BASE_PATH . '/database/migrations');
    }

    /**
     * 回滚最后 $steps 个批次（按执行的倒序逐个 down() 并删除记录）
     *
     * 不用 MigrationRunner::rollback()：它把 down() 包在 DB::beginTransaction() 里，down() 里的 DDL 在 MySQL 隐式提交，
     * 随后 DB::commit() 抛「There is no active transaction」，第一个迁移回滚完就报错中止。DDL 本来就无法事务回滚，这里不包事务。
     *
     * @return list<string> 已回滚的迁移
     */
    public static function rollback(int $steps = 1): array
    {
        $batches = array_column(
            DB::query('SELECT DISTINCT `batch` FROM `' . self::TABLE . '` ORDER BY `batch` DESC LIMIT ' . max(1, $steps)),
            'batch',
        );
        if ($batches === []) {
            return [];
        }

        $records = DB::query(
            'SELECT `id`, `migration` FROM `' . self::TABLE . '` WHERE `batch` IN (' . implode(',', array_map('intval', $batches)) . ') ORDER BY `id` DESC',
        );
        $rolledBack = [];
        foreach ($records as $record) {
            $file = BASE_PATH . '/database/migrations/' . $record['migration'] . '.php';
            if (!is_file($file)) {
                throw new RuntimeException("迁移文件不存在，无法回滚: {$record['migration']}");
            }
            $migration = require $file;
            $migration->down();
            DB::execute('DELETE FROM `' . self::TABLE . '` WHERE `id` = ?', [$record['id']]);
            $rolledBack[] = $record['migration'];
        }

        return $rolledBack;
    }

    /**
     * master 进程启动时执行待执行的迁移，结束后关闭连接（Worker::runAll() fork 前不能留着 socket，AGENTS.md 铁律 9）
     *
     * @return list<string> 本次执行的迁移
     */
    public static function migrateOnStart(): array
    {
        try {
            self::connect();
            return self::runner()->migrate();
        } finally {
            DB::close();
        }
    }

    /**
     * database/schema.sql 拆成逐条语句：去掉 CREATE DATABASE / USE（连哪个库由配置决定）、整行注释与 migrations 表
     * （migrations 表由 connect() 先建好，导入时 DROP 掉会丢记录）
     *
     * schema.sql 约定：每条语句以行尾的分号结束，语句中间不出现行尾分号。
     *
     * @return list<string>
     */
    public static function schemaStatements(): array
    {
        $path = BASE_PATH . '/database/schema.sql';
        $lines = file($path);
        if ($lines === false) {
            throw new RuntimeException("读取失败: {$path}");
        }

        $statements = [];
        $buffer = '';
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($buffer === '' && ($trimmed === '' || str_starts_with($trimmed, '--'))) {
                continue;
            }
            $buffer .= $line;
            if (str_ends_with($trimmed, ';')) {
                $statements[] = rtrim(trim($buffer), ';');
                $buffer = '';
            }
        }
        if (trim($buffer) !== '') {
            throw new RuntimeException('database/schema.sql 末尾有未以分号结束的语句');
        }

        return array_values(array_filter(
            $statements,
            static fn (string $sql): bool => preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $sql) !== 1
                && preg_match('/^(DROP\s+TABLE\s+IF\s+EXISTS|CREATE\s+TABLE)\s+`' . self::TABLE . '`/i', $sql) !== 1,
        ));
    }
}
