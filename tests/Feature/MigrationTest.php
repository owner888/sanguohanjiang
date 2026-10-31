<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Feature;

use Hanjiang\Support\Connections;
use Hanjiang\Support\Migrations;
use OmniPHP\Config;
use OmniPHP\Database\DB;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * 数据库自动迁移（Hanjiang\Support\Migrations + OmniPHP MigrationRunner，server.php 启动时在 master 里执行）
 *
 * - 测试库（由 database/schema.sql 建成）：导入基础结构的迁移是空操作，只留下记录；再跑一次没有待执行的迁移；
 * - 全新空库：迁移导入整份 schema.sql，结果与 schema.sql 建成的测试库逐表 SHOW CREATE TABLE 相同。
 */
final class MigrationTest extends TestCase
{
    private const string SCRATCH_DB = 'zc_sanguo_migrun_test';

    private string $database;

    protected function setUp(): void
    {
        $this->database = (string)Config::get('database.mysql.database');
    }

    protected function tearDown(): void
    {
        Config::set('database.mysql.database', $this->database);
        $this->rootPdo()->exec('DROP DATABASE IF EXISTS `' . self::SCRATCH_DB . '`');
        // 恢复其余测试用的带前缀连接
        Connections::init();
    }

    public function testBaseImportIsNoOpOnExistingDatabase(): void
    {
        $before = $this->createTables($this->database);
        $members = $this->memberCount();

        Migrations::connect();
        DB::execute('DELETE FROM `' . Migrations::TABLE . '`');

        $files = array_map(
            static fn (string $file): string => basename($file, '.php'),
            glob(BASE_PATH . '/database/migrations/*.php') ?: [],
        );
        sort($files);
        $this->assertSame('0000_00_00_000000_import_base_schema', $files[0]);

        $this->assertSame($files, Migrations::runner()->migrate());
        $this->assertSame($files, array_column(DB::query('SELECT `migration` FROM `' . Migrations::TABLE . '` ORDER BY `id`'), 'migration'));
        $this->assertSame([], Migrations::runner()->migrate());
        $this->assertSame(0, Migrations::runner()->status()['pending']);

        $this->assertSame($before, $this->createTables($this->database));
        $this->assertSame($members, $this->memberCount());
    }

    public function testFreshDatabaseImportsFullSchema(): void
    {
        $root = $this->rootPdo();
        $root->exec('DROP DATABASE IF EXISTS `' . self::SCRATCH_DB . '`');
        $root->exec('CREATE DATABASE `' . self::SCRATCH_DB . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        Config::set('database.mysql.database', self::SCRATCH_DB);
        Migrations::connect();
        $executed = Migrations::runner()->migrate();
        $this->assertSame('0000_00_00_000000_import_base_schema', $executed[0]);
        $this->assertSame([], Migrations::runner()->migrate());

        $fresh = $this->createTables(self::SCRATCH_DB);
        $this->assertCount(count($this->createTables($this->database)), $fresh);
        $this->assertSame($this->createTables($this->database), $fresh);
        // schema.sql 的初始数据
        $this->assertSame('1', (string)$root->query('SELECT COUNT(*) FROM `' . self::SCRATCH_DB . '`.`zc_sanguo_uc_group`')->fetchColumn());
    }

    /**
     * @return array<string, string> 表名 => SHOW CREATE TABLE（去掉随数据变化的 AUTO_INCREMENT=N）
     */
    private function createTables(string $database): array
    {
        $pdo = $this->rootPdo();
        $tables = $pdo->query('SHOW TABLES FROM `' . $database . '`')->fetchAll(PDO::FETCH_COLUMN);
        $result = [];
        foreach ($tables as $table) {
            $row = $pdo->query("SHOW CREATE TABLE `{$database}`.`{$table}`")->fetch(PDO::FETCH_NUM);
            $result[$table] = (string)preg_replace('/ AUTO_INCREMENT=\d+/', '', (string)$row[1]);
        }
        ksort($result);

        return $result;
    }

    private function memberCount(): int
    {
        return (int)$this->rootPdo()->query("SELECT COUNT(*) FROM `{$this->database}`.`zc_sanguo_member`")->fetchColumn();
    }

    private function rootPdo(): PDO
    {
        $config = (array)Config::get('database.mysql');

        return new PDO(
            "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4",
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
