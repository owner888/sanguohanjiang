<?php

declare(strict_types=1);

/**
 * 全新安装：空库时导入 database/schema.sql（完整、始终最新的表结构 + 初始数据）
 *
 * - 库里已有 zc_sanguo_member：什么也不做（已有安装，表结构由 schema.sql 或 database/migrations/sql/*.sql 建成），只留下迁移记录；
 * - 一张 zc_sanguo_ 表都没有：逐条执行 schema.sql（去掉 CREATE DATABASE / USE，见 Migrations::schemaStatements）；
 * - 有别的 zc_sanguo_ 表却没有 zc_sanguo_member：库状态不明，拒绝导入（schema.sql 带 DROP TABLE，会删掉这些表）。
 *
 * 文件名以 0000_00_00 开头，保证永远排在第一个：之后的迁移都以「schema.sql 已导入」为前提，并且必须幂等
 * （schema.sql 已含它们的改动，全新安装时它们全部是空操作）。
 *
 * down() 什么也不做：回滚 / 重置只删掉这条记录，绝不删除整套游戏表；再次 migrate 时因为表已存在仍是空操作。
 */

use Hanjiang\Support\Migrations;
use OmniPHP\Database\DB;
use OmniPHP\Database\Migration;
use OmniPHP\Database\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('zc_sanguo_member')) {
            return;
        }

        $pdo = DB::connection();
        $existing = $pdo->query("SHOW TABLES LIKE 'zc\\_sanguo\\_%'")->fetchAll(PDO::FETCH_COLUMN);
        if ($existing !== []) {
            throw new RuntimeException(
                '库里有 ' . count($existing) . ' 张 zc_sanguo_ 表却没有 zc_sanguo_member，不导入 database/schema.sql'
                . '（会 DROP 这些表）。请先确认库状态：全新安装用空库，已有安装先按 README 补齐 database/migrations/sql/*.sql。'
            );
        }

        foreach (Migrations::schemaStatements() as $sql) {
            $pdo->exec($sql);
        }
    }

    public function down(): void
    {
    }
};
