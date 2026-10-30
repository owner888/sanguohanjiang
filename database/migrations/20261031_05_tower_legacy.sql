-- 已有数据库升级：将魂星路（c=tower）改回旧版（controllers/tower.php + models/tower_model.php）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261031_05_tower_legacy.sql
--
-- 升级前：tower 是新版自定的爬塔（楼层 / 守军 / 奖励都是自定数值），进度只存 Redis（tower_progress:{uid}），库里没有任何星路表。
-- 升级后：新增 member_activity（星路位置 towerlevel / towerstage / towermission、免费次数 freetower、购买次数 towertimes、
--         每日奖励领取时间 souldate）、soul_open_map（已开启地图）、soul_log（首杀记录 / 攻略列表）、soul_report（首杀战报）；
--         member_field 新增 soultimes（今日购买次数）。
--
-- 数据处理（升级前只有测试数据）：旧进度与新版自定的楼层对不上旧版关卡，不转换、不保留；所有玩家从第 1 关重新开始，
-- 首次打开将魂星路时按旧版初始值建档（免费次数 12）。上述 Redis 键不再读取，可自行删除。
-- 只需执行一次；重复执行会在 ADD COLUMN soultimes 处报错退出（此前各星路表已重建、数据清空）。

DROP TABLE IF EXISTS `zc_sanguo_member_activity`;
CREATE TABLE `zc_sanguo_member_activity` (
    `uid` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '用户ID',
    `towerlevel` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '势力id',
    `towerstage` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '场景id',
    `towermission` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关卡id',
    `freetower` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '免费爬塔次数',
    `towertimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '购买的爬塔次数',
    `souldate` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '星路奖励领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='玩家活动信息表（旧版其余字段随对应玩法移植补充）';

DROP TABLE IF EXISTS `zc_sanguo_soul_open_map`;
CREATE TABLE `zc_sanguo_soul_open_map` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `level` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '势力id，每次开启势力，增加记录',
    `stage` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最大场景id',
    `mission` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最大任务id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='势力，场景，任务开启记录';

DROP TABLE IF EXISTS `zc_sanguo_soul_log`;
CREATE TABLE `zc_sanguo_soul_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id（即战报 soul_report.id）',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '玩家昵称',
    `missionlevel` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '势力id',
    `missionstage` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '场景id',
    `missionid` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后更新时间',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首杀时用户等级',
    `disable` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 显示;1 不显示',
    `tree` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神树活动时的神树等级',
    `first_reward` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首杀标识',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid_mission` (`uid`, `missionid`),
    INDEX `idx_mission` (`missionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='将魂星路任务记录表';

DROP TABLE IF EXISTS `zc_sanguo_soul_report`;
CREATE TABLE `zc_sanguo_soul_report` (
    `id` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '战报id（soul_log.id）',
    `report` TEXT NOT NULL COMMENT '战斗过程（json_encode(base64(gzcompress(战报 json)))）',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '玩家昵称',
    `userlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `name` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '关卡名称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关卡等级',
    `background` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗背景',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='将魂星路战报表';

ALTER TABLE `zc_sanguo_member_field`
    ADD COLUMN `soultimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '星路购买次数（将魂星路今日购买次数，每日计划任务归零）' AFTER `first_recharge`;
