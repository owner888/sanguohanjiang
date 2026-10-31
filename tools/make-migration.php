<?php

declare(strict_types=1);

/**
 * 生成迁移文件 database/migrations/YYYY_MM_DD_HHMMSS_<名称>.php（幂等模板）
 *
 * 用法:
 *   php tools/make-migration.php create_foo_table        建表模板（表 zc_sanguo_foo，Schema::hasTable 保护，三个时间字段）
 *   php tools/make-migration.php add_bar_to_member       加列 / 加索引模板（表 zc_sanguo_member，Schema::hasColumn / hasIndex 保护）
 *
 * 模板写原生 DDL 而不是 Blueprint：与 database/schema.sql 的写法一致（字符集、注释、列类型），
 * 升级上来的库与 schema.sql 全新导入的库结构才完全相同。改完迁移，把同样的改动写进 database/schema.sql。
 * 迁移连接不带表前缀，表名一律写完整的 zc_sanguo_xxx（见 Hanjiang\Support\Migrations）。
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/bootstrap.php';

$name = $argv[1] ?? '';
if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
    fwrite(STDERR, "用法: php tools/make-migration.php <名称，小写字母 / 数字 / 下划线，如 create_foo_table、add_bar_to_member>\n");
    exit(1);
}

$path = BASE_PATH . '/database/migrations/' . date('Y_m_d_His') . "_{$name}.php";
if (file_exists($path)) {
    fwrite(STDERR, "已存在: {$path}\n");
    exit(1);
}

if (preg_match('/^create_(.+?)(?:_table)?$/', $name, $m) === 1) {
    $table = 'zc_sanguo_' . $m[1];
    $template = <<<PHP
        <?php

        declare(strict_types=1);

        /**
         * 新建 {$table}（TODO 说明用途）
         *
         * 幂等：表已存在（schema.sql 全新导入的库、或已执行过）时什么也不做。同样的 CREATE TABLE 写进 database/schema.sql。
         */

        use OmniPHP\Database\DB;
        use OmniPHP\Database\Migration;
        use OmniPHP\Database\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                if (Schema::hasTable('{$table}')) {
                    return;
                }

                DB::connection()->exec(<<<'SQL'
                    CREATE TABLE `{$table}` (
                        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TODO',
                        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        `deleted_at` DATETIME DEFAULT NULL,
                        INDEX `idx_uid` (`uid`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TODO'
                    SQL);
            }

            public function down(): void
            {
                Schema::dropIfExists('{$table}');
            }
        };

        PHP;
} else {
    $table = preg_match('/_to_(.+?)(?:_table)?$/', $name, $m) === 1 ? 'zc_sanguo_' . $m[1] : 'zc_sanguo_TODO';
    $template = <<<PHP
        <?php

        declare(strict_types=1);

        /**
         * {$name}（TODO 说明改动）
         *
         * 幂等：每一步先查列 / 索引是否已存在（schema.sql 全新导入的库已经包含这些改动）。同样的改动写进 database/schema.sql。
         */

        use OmniPHP\Database\DB;
        use OmniPHP\Database\Migration;
        use OmniPHP\Database\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                if (!Schema::hasColumn('{$table}', 'new_column')) {
                    DB::connection()->exec("ALTER TABLE `{$table}` ADD COLUMN `new_column` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TODO' AFTER `TODO`");
                }
                if (!Schema::hasIndex('{$table}', 'idx_new_column')) {
                    DB::connection()->exec("ALTER TABLE `{$table}` ADD INDEX `idx_new_column` (`new_column`)");
                }
            }

            public function down(): void
            {
                Schema::dropIndex('{$table}', 'idx_new_column');
                if (Schema::hasColumn('{$table}', 'new_column')) {
                    DB::connection()->exec("ALTER TABLE `{$table}` DROP COLUMN `new_column`");
                }
            }
        };

        PHP;
}

file_put_contents($path, $template);
fwrite(STDOUT, "已生成: {$path}\n下一步: 编辑迁移（保持幂等）→ 同样的改动写进 database/schema.sql → php tools/migrate.php（或重启服务自动执行）\n");
