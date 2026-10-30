-- 已有数据库升级：平乱副本 / 新副本（c=copies）改回旧版（controllers/newequip/copies.php + models/newequip/copies_model.php），
-- 并新增旧版锻造材料存储（own_materials / own_materials_log，打造 equip_forge 移植时沿用）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261031_copies_legacy.sql
--
-- 升级前：copies 是新版自定的 3 个战役（守军 / 银两奖励 / 掉落文字都是自定数值），只有 Redis 里的今日次数（copies_times:{uid}），
--         库里没有任何副本表，也没有玩家锻造材料表。
-- 升级后：新增 copies_beat（每只怪的击败状态、今日免费次数、今日剩余可购买次数）、copies_beat_log（首杀记录 / 攻略列表）、
--         copies_report（首杀战报）、own_materials（拥有的锻造材料）、own_materials_log（材料增减记录）。
--
-- 数据处理（升级前只有测试数据）：旧的次数对不上旧版每只怪的次数，不转换、不保留；所有玩家首次打开某个副本时按旧版初始值建档。
-- 上述 Redis 键不再读取，可自行删除。本文件会重建这 5 张表（已有数据清空），可重复执行。

DROP TABLE IF EXISTS `zc_sanguo_copies_beat`;
CREATE TABLE `zc_sanguo_copies_beat` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'UID',
    `vip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前vip等级',
    `copies_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '副本ID 1天公张角 2董卓乱政 3战神吕布',
    `diff_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '副本难度1简单 2普通 3困难',
    `monster_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '怪物ID',
    `status` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻打状态 1成功 0未成功',
    `free_times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '免费攻打次数',
    `vip_times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip可购买次数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻打时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`, `copies_id`, `diff_id`, `monster_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户攻打副本数据表';

DROP TABLE IF EXISTS `zc_sanguo_copies_beat_log`;
CREATE TABLE `zc_sanguo_copies_beat_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID（即战报 copies_report.id）',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'UID',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '昵称',
    `level` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '等级',
    `copies_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '副本ID 1天公张角 2董卓乱政 3战神吕布',
    `diff_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '副本难度1简单 2普通 3困难',
    `monster_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '怪物ID',
    `status` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗结果（首杀时的战斗评级）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻打时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid_monster` (`uid`, `copies_id`, `diff_id`, `monster_id`),
    INDEX `idx_monster_dateline` (`copies_id`, `diff_id`, `monster_id`, `dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户攻打副本日志表（首杀记录 / 攻略列表）';

DROP TABLE IF EXISTS `zc_sanguo_copies_report`;
CREATE TABLE `zc_sanguo_copies_report` (
    `id` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '战报id（copies_beat_log.id）',
    `report` TEXT NOT NULL COMMENT '战斗过程（base64(gzcompress(战报 json))）',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '昵称',
    `userlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `name` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '怪物名称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '怪物等级',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='新副本战报表';

DROP TABLE IF EXISTS `zc_sanguo_own_materials`;
CREATE TABLE `zc_sanguo_own_materials` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'UID',
    `materials_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '材料ID',
    `name` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '材料名称',
    `quality` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '材料品级 1紫色 2红色 3橙色 4无等级 5卷轴',
    `chip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否卷轴碎片 1碎片 0卷轴',
    `num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '数量',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_materials` (`uid`, `materials_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='打造系统-拥有材料表';

DROP TABLE IF EXISTS `zc_sanguo_own_materials_log`;
CREATE TABLE `zc_sanguo_own_materials_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'UID',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新类型 1增加 0消耗',
    `materials_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '材料ID',
    `name` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '材料名称',
    `quality` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '材料品级 1紫色 2红色 3橙色 4无等级 5卷轴',
    `chip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否卷轴碎片 1碎片 0卷轴',
    `num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新数量',
    `now_num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前数量',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获取时间',
    `class` VARCHAR(80) NOT NULL DEFAULT '' COMMENT '执行类（旧版副本发材料时不写，为空串）',
    `method` VARCHAR(80) NOT NULL DEFAULT '' COMMENT '执行方法（旧版副本发材料时不写，为空串）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='打造系统-更新材料log';
