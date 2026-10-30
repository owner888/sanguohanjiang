<?php

declare(strict_types=1);

namespace Hanjiang\Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * database/migrations/20261031_03_matrix_legacy.sql 的数据转换
 *
 * 在临时库里建升级前的三张表（只含迁移读写的列），写入测试数据后执行迁移，检查转换结果。
 * 表结构与 schema.sql 是否一致另行比对（SHOW CREATE TABLE），这里只看数据。
 */
final class MatrixMigrationTest extends TestCase
{
    private const string DB = 'zc_sanguo_migtest';

    private PDO $pdo;

    protected function setUp(): void
    {
        $config = (require dirname(__DIR__, 2) . '/config/database.php')['mysql'];
        $this->pdo = new PDO(
            "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4",
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );
        $this->pdo->exec('DROP DATABASE IF EXISTS `' . self::DB . '`');
        $this->pdo->exec('CREATE DATABASE `' . self::DB . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->pdo->exec('USE `' . self::DB . '`');

        // 升级前的表（列定义取自升级前的 schema.sql）
        $this->pdo->exec("CREATE TABLE `zc_sanguo_member_field` (
            `uid` INT UNSIGNED PRIMARY KEY,
            `race_dateline` INT UNSIGNED NOT NULL DEFAULT 0,
            `dateline` INT UNSIGNED NOT NULL DEFAULT 0,
            `first_recharge` TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->pdo->exec("CREATE TABLE `zc_sanguo_general` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `uid` INT UNSIGNED NOT NULL,
            `goout` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `pos` TINYINT NOT NULL DEFAULT 0,
            `matrix_id` TINYINT NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->pdo->exec("CREATE TABLE `zc_sanguo_matrix` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `uid` INT UNSIGNED NOT NULL,
            `mid` TINYINT NOT NULL DEFAULT 1,
            `level` INT NOT NULL DEFAULT 1,
            `position1` INT NOT NULL DEFAULT 0,
            `position2` INT NOT NULL DEFAULT 0,
            `position3` INT NOT NULL DEFAULT 0,
            `position4` INT NOT NULL DEFAULT 0,
            `position5` INT NOT NULL DEFAULT 0,
            `position6` INT NOT NULL DEFAULT 0,
            `position7` INT NOT NULL DEFAULT 0,
            `position8` INT NOT NULL DEFAULT 0,
            `position9` INT NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` DATETIME DEFAULT NULL,
            UNIQUE KEY `uk_uid_mid` (`uid`, `mid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('DROP DATABASE IF EXISTS `' . self::DB . '`');
    }

    public function testConvertsSingleRowMatrixToLegacyStorage(): void
    {
        $this->pdo->exec("INSERT INTO `zc_sanguo_member_field` (`uid`) VALUES (1), (2), (3), (4)");
        $this->pdo->exec("INSERT INTO `zc_sanguo_general` (`id`, `uid`, `goout`, `pos`, `deleted_at`) VALUES
            (10, 1, 1, 2, NULL),      -- 原「出征」（pos > 0）
            (11, 1, 1, 0, NULL),      -- go_rest 只清了 pos，goout 残留 1
            (12, 1, 0, 0, NULL),      -- pos 为 0 但在阵法站位上
            (13, 1, 1, 3, '2026-10-01 00:00:00'),  -- 已删除
            (20, 2, 1, 0, NULL),
            (30, 3, 0, 1, NULL)");
        $this->pdo->exec("INSERT INTO `zc_sanguo_matrix` (`id`, `uid`, `mid`, `level`, `position2`, `position5`, `position7`, `position8`, `position9`, `deleted_at`) VALUES
            (1, 1, 3, 4, 10, 12, 13, 20, -1, NULL),
            (2, 2, 1, 1, 20, 0, 0, 0, 0, '2026-10-01 00:00:00'),
            (6, 3, 4, 2, 0, 0, 0, 0, 0, NULL),
            (5, 3, 2, 7, 30, 0, 0, 0, 0, NULL)");

        $this->runMigration();

        $matrix = $this->pdo->query('SELECT * FROM `zc_sanguo_matrix` ORDER BY `uid`, `mid`')->fetchAll();
        $this->assertSame([[1, 3], [3, 2], [3, 4]], array_map(fn (array $r): array => [(int)$r['uid'], (int)$r['mid']], $matrix), '已删除的阵法行不迁移');
        $uid1 = $matrix[0];
        $this->assertSame(4, (int)$uid1['level']);
        $this->assertSame(
            [0, 10, 0, 0, 12, 0, 0, 0, 0],
            array_map(fn (int $i): int => (int)$uid1['position' . $i], range(1, 9)),
            '已删除 / 其他玩家的武将与负数站位清零',
        );
        $this->assertSame(7, (int)$matrix[1]['level']);
        $this->assertSame(30, (int)$matrix[1]['position2']);
        $this->assertArrayNotHasKey('id', $uid1);

        $matrixid = $this->pdo->query('SELECT `uid`, `matrixid` FROM `zc_sanguo_member_field` ORDER BY `uid`')->fetchAll(PDO::FETCH_KEY_PAIR);
        // uid 3 有两行时取 id 最小的一行（mid 2）；uid 2 只有已删除的行、uid 4 没有阵法行：0
        $this->assertSame([1 => 3, 2 => 0, 3 => 2, 4 => 0], array_map('intval', $matrixid));

        $goout = $this->pdo->query('SELECT `id`, `goout` FROM `zc_sanguo_general` ORDER BY `id`')->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->assertSame([10 => 1, 11 => 0, 12 => 1, 13 => 1, 20 => 0, 30 => 1], array_map('intval', $goout));

        $columns = $this->pdo->query('SHOW COLUMNS FROM `zc_sanguo_general`')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertNotContains('pos', $columns);
        $this->assertNotContains('matrix_id', $columns);
    }

    private function runMigration(): void
    {
        $sql = (string)file_get_contents(dirname(__DIR__, 2) . '/database/migrations/20261031_03_matrix_legacy.sql');
        $lines = array_filter(explode("\n", $sql), fn (string $line): bool => !str_starts_with(ltrim($line), '--'));
        foreach (explode(";\n", implode("\n", $lines)) as $statement) {
            if (trim($statement) !== '') {
                $this->pdo->exec($statement);
            }
        }
    }
}
