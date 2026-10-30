-- 已有数据库升级：多人副本 / 团队副本（c=elite）改回旧版（controllers/elite.php + models/elite_model.php）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261031_elite_legacy.sql
--
-- 升级前：elite 是新版自定的 3 个精英关卡（守军 / 银两奖励 / 掉落文字都是自定数值），今日次数只存 Redis（elite_times:{uid}），
--         库里没有任何多人副本表或字段。
-- 升级后：member_activity 新增 elitetimes（今日免费次数）、elitebuytimes（今日购买次数）、elitebuydateline（最后购买时间）、
--         elitesurplustimes（购买的剩余次数）；新增 elite_log（战斗记录）、elite_mission_log（军团首杀记录）。
--
-- 数据处理（升级前只有测试数据）：旧的 Redis 次数对不上旧版字段，不转换、不保留。已有 member_activity 记录的玩家
-- 新增字段为 0，按旧版次日 daily_cron 才恢复 10 次；如需当天可玩，执行文末注释里的 UPDATE。
-- 新玩家首次读活动信息时按旧版初始值建档（elitetimes 10）。上述 Redis 键不再读取，可自行删除。
-- 只需执行一次；重复执行会在 ADD COLUMN elitetimes 处报错退出（此前两张多人副本表已重建、数据清空）。

DROP TABLE IF EXISTS `zc_sanguo_elite_log`;
CREATE TABLE `zc_sanguo_elite_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `leader` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '队长uid',
    `member` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '队伍成员',
    `fid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '副本id',
    `topteam` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否至尊组队，1是，0否',
    `reward` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '用户获得奖励',
    `times` INT NOT NULL DEFAULT 0 COMMENT '当前剩余次数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_leader_dateline` (`leader`, `dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='多人副本战斗记录表';

DROP TABLE IF EXISTS `zc_sanguo_elite_mission_log`;
CREATE TABLE `zc_sanguo_elite_mission_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `missionid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '军团id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通过时间',
    `first_reward` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首杀标识',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid_mission` (`uid`, `missionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='副本关卡通过记录表';

ALTER TABLE `zc_sanguo_member_activity`
    ADD COLUMN `elitetimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '多人副本挑战次数' AFTER `souldate`,
    ADD COLUMN `elitebuytimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '多人副本今日购买次数' AFTER `elitetimes`,
    ADD COLUMN `elitebuydateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '多人副本最后购买次数时间' AFTER `elitebuytimes`,
    ADD COLUMN `elitesurplustimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '多人副本购买剩余次数' AFTER `elitebuydateline`;

-- 可选：已有活动记录的玩家当天即可挑战（相当于提前执行一次旧版 daily_cron 的 elitetimes = 10）
-- UPDATE `zc_sanguo_member_activity` SET `elitetimes` = 10 WHERE `deleted_at` IS NULL;
