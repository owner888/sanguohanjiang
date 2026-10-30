-- 已有数据库升级：批次 batch2——四象圣域（c=sanctum）、通天塔（c=heaven）、英雄王座（c=herothrone）、金银岛（c=island）改回旧版
-- （controllers/sanctum.php + models/sanctum_model.php、controllers/heaven.php + models/heaven_model.php、
-- controllers/herothrone.php + models/herothrone_model.php、controllers/island.php + models/island_model.php）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261101_batch2.sql
--
-- 升级前：四个玩法都是新版自定的战斗（自定守军 / 奖励），进度只存 Redis（sanctum_cleared:{uid}、heaven_star:{uid}、
--         hero_throne_lvl:{uid}、island_times:{uid}），库里没有对应表或字段。
-- 升级后：member_activity 新增 islandid / islandtimes / islanddate（金银岛）、sanctumdate（四象圣域每日奖励）、
--         heavenstage / heavenmission / heavenlevel / heaventimes / freeheaven / heavendate（通天塔）；
--         member_field 新增 heaventimes（通天塔扫荡补买次数）；新增 sanctum_log / sanctum_open_map / sanctum_mission_log /
--         sanctum_report（四象圣域）、heaven_open_map / heaven_log / heaven_report（通天塔）、herothrone_log（英雄王座）、
--         island_log / island_report（金银岛）。
--
-- 数据处理（升级前只有测试数据）：上述 Redis 进度与旧版玩法对不上，不转换、不保留，可自行删除。
-- 已有 member_activity 记录的玩家新增字段为 0：通天塔没有次数，次日旧版 daily_cron 恢复 freeheaven 10；
-- 如需按旧版首次建档值（heavenlevel 1、heaventimes 10）补齐，执行文末注释里的 UPDATE。
-- 新玩家首次读活动信息时按旧版初始值建档。
-- 只需执行一次；重复执行会在 ADD COLUMN islandid 处报错退出（此前新建的表已重建、数据清空）。

-- -------------------------------------------------------------
-- 四象圣域 (对齐原版 zc_sanguo_sanctum_log / sanctum_open_map / sanctum_mission_log / sanctum_report；
-- 每日奖励领取时间为 member_activity.sanctumdate；关卡 / 守军 / 每日奖励倍率配置在 MasterData SanctumMission / SanctumMonster / SanctumReward)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_sanctum_log`;
CREATE TABLE `zc_sanguo_sanctum_log` (
    `uid` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '用户uid',
    `missionlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '圣域id',
    `missionstage` INT NOT NULL DEFAULT 0 COMMENT '当前所在层数',
    `missionid` INT NOT NULL DEFAULT 0 COMMENT '当前所在关卡',
    `times` INT NOT NULL DEFAULT 0 COMMENT '剩余攻击次数',
    `buy_times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '四象剩余购买的次数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后活动时间（每日重置免费次数的判断依据）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='四象圣域用户记录表';

DROP TABLE IF EXISTS `zc_sanguo_sanctum_open_map`;
CREATE TABLE `zc_sanguo_sanctum_open_map` (
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `city` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '圣域id（1~5）',
    `stage` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '层数（1~5）',
    `missionid` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已通过的关卡id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`, `city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='四象圣域关卡开启表';

DROP TABLE IF EXISTS `zc_sanguo_sanctum_mission_log`;
CREATE TABLE `zc_sanguo_sanctum_mission_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id（即战报 sanctum_report.id）',
    `missionid` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关卡ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '用户昵称',
    `level` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通过时间',
    `tree` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神树活动中的神树等级',
    `first_reward` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首杀标识',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid_mission` (`uid`, `missionid`),
    INDEX `idx_mission` (`missionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='四象圣域关卡通过记录表';

DROP TABLE IF EXISTS `zc_sanguo_sanctum_report`;
CREATE TABLE `zc_sanguo_sanctum_report` (
    `id` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '战报id（sanctum_mission_log.id）',
    `report` TEXT NOT NULL COMMENT '战斗过程（json_encode(base64(gzcompress(战报 json)))）',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '玩家昵称',
    `userlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `name` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '关卡名称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关卡等级',
    `background` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗背景',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='四象圣域战报表';

-- -------------------------------------------------------------
-- 通天塔 (对齐原版 zc_sanguo_heaven_open_map / heaven_log / heaven_report；次数与每日奖励为 member_activity 的
-- freeheaven / heaventimes / heavendate，扫荡补买次数为 member_field.heaventimes；关卡 / 守军配置在 MasterData HeavenMission / HeavenMonster)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_heaven_open_map`;
CREATE TABLE `zc_sanguo_heaven_open_map` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '势力id',
    `stage` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '场景id',
    `mission` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已通过的最大关卡id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通天塔势力，任务开启记录';

DROP TABLE IF EXISTS `zc_sanguo_heaven_log`;
CREATE TABLE `zc_sanguo_heaven_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id（即战报 heaven_report.id）',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '玩家昵称',
    `missionlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '势力id',
    `missionid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关卡id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首杀时间',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首杀时用户等级',
    `tree` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神树活动时的神树等级',
    `first_reward` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首杀奖励领取标识（1 已领取，0 未领取）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid_mission` (`uid`, `missionid`),
    INDEX `idx_mission` (`missionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通天塔任务记录表';

DROP TABLE IF EXISTS `zc_sanguo_heaven_report`;
CREATE TABLE `zc_sanguo_heaven_report` (
    `id` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '战报id（heaven_log.id）',
    `report` TEXT NOT NULL COMMENT '战斗过程（json_encode(base64(gzcompress(战报 json)))）',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '玩家昵称',
    `userlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `name` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '关卡名称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关卡等级',
    `background` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗背景（旧版写战报时不写入）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通天塔战报表';

-- -------------------------------------------------------------
-- 英雄王座 (对齐原版 zc_sanguo_herothrone_log；关卡 / 守军 / 宝箱奖励配置在 MasterData HerothroneMission / HerothroneMonster)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_herothrone_log`;
CREATE TABLE `zc_sanguo_herothrone_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '用户名称',
    `times` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余免费次数',
    `buytimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余购买次数',
    `today_buytimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '今日购买次数',
    `today_buydate` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '今日最后购买时间',
    `stage` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前进度',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态（1已开启，2失败，0未开启）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后挑战时间（每日重置免费次数的判断依据）',
    `laststage` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '历史最高过关记录',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='英雄王座用户记录';

-- -------------------------------------------------------------
-- 金银岛 (对齐原版 zc_sanguo_island_log / island_report；进度与次数为 member_activity 的 islandid / islandtimes / islanddate；
-- 关卡 / 守军配置在 MasterData Island / IslandMonster)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_island_log`;
CREATE TABLE `zc_sanguo_island_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id（即战报 island_report.id）',
    `islandid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '金银岛id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '昵称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首胜时间',
    `tree` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神树活动中的神树等级',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid_island` (`uid`, `islandid`),
    INDEX `idx_island` (`islandid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='金银岛记录表';

DROP TABLE IF EXISTS `zc_sanguo_island_report`;
CREATE TABLE `zc_sanguo_island_report` (
    `id` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '战报id（island_log.id）',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '昵称',
    `userlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `name` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '怪物名称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关卡等级（monsterlevel）',
    `report` MEDIUMTEXT NOT NULL COMMENT '战斗过程（战报 json，旧版未压缩）',
    `tree` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神树活动中的神树等级（旧版写战报时不写入）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='金银岛攻略表';

ALTER TABLE `zc_sanguo_member_activity`
    ADD COLUMN `islandid` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '金银岛关卡id' AFTER `elitesurplustimes`,
    ADD COLUMN `islandtimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '今日金银岛挑战次数' AFTER `islandid`,
    ADD COLUMN `islanddate` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '金银岛进度更新时间' AFTER `islandtimes`,
    ADD COLUMN `sanctumdate` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '四象圣域每日奖励领取时间' AFTER `islanddate`,
    ADD COLUMN `heavenstage` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通天塔（旧版未使用）' AFTER `sanctumdate`,
    ADD COLUMN `heavenmission` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通天塔（index 置 0）' AFTER `heavenstage`,
    ADD COLUMN `heavenlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通天塔（旧版未使用）' AFTER `heavenmission`,
    ADD COLUMN `heaventimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通天塔购买的剩余次数' AFTER `heavenlevel`,
    ADD COLUMN `freeheaven` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通天塔免费次数' AFTER `heaventimes`,
    ADD COLUMN `heavendate` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通天塔每日奖励领取时间' AFTER `freeheaven`;

ALTER TABLE `zc_sanguo_member_field`
    ADD COLUMN `heaventimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通天塔次数（扫荡次数不足时累加补买的次数，旧版每日计划任务不归零）' AFTER `soultimes`;

-- 可选：已有活动记录的玩家按旧版首次建档值补齐通天塔字段
-- UPDATE `zc_sanguo_member_activity` SET `heavenlevel` = 1, `heaventimes` = 10 WHERE `deleted_at` IS NULL;
