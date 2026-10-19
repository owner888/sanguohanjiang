-- 悍将三国 (Modern Edition) 核心数据库定义

CREATE DATABASE IF NOT EXISTS `zc_sanguo` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `zc_sanguo`;

-- 用户账号表
CREATE TABLE IF NOT EXISTS `zc_sanguo_user` (
    `uid` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(64) NOT NULL UNIQUE,
    `password` VARCHAR(128) NOT NULL,
    `channel` INT NOT NULL DEFAULT 106,
    `is_guest` TINYINT NOT NULL DEFAULT 0,
    `mac` VARCHAR(64) NOT NULL DEFAULT '',
    `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX `idx_username` (`username`),
    INDEX `idx_mac` (`mac`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 玩家基础数据表 (对齐原版 zc_sanguo_member)
CREATE TABLE IF NOT EXISTS `zc_sanguo_member` (
    `uid` INT UNSIGNED PRIMARY KEY,
    `username` VARCHAR(64) NOT NULL DEFAULT '',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '天命主公',
    `sex` TINYINT NOT NULL DEFAULT 1,
    `gender` TINYINT NOT NULL DEFAULT 0,
    `roleid` TINYINT NOT NULL DEFAULT 1,
    `country` TINYINT NOT NULL DEFAULT 1 COMMENT '1魏 2蜀 3吴',
    `level` INT UNSIGNED NOT NULL DEFAULT 1,
    `exp` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `maxexp` BIGINT UNSIGNED NOT NULL DEFAULT 100,
    `silver` BIGINT NOT NULL DEFAULT 88888 COMMENT '银两',
    `gold` INT NOT NULL DEFAULT 9999 COMMENT '元宝',
    `reputation` INT NOT NULL DEFAULT 100 COMMENT '声望',
    `act` INT NOT NULL DEFAULT 100 COMMENT '军令/体力',
    `maxact` INT NOT NULL DEFAULT 100,
    `vip` TINYINT NOT NULL DEFAULT 1,
    `bagnum` INT NOT NULL DEFAULT 20,
    `guide` INT NOT NULL DEFAULT 0,
    `guidestep` INT NOT NULL DEFAULT 0,
    `guidestop` TINYINT NOT NULL DEFAULT 0,
    `missionsite` INT NOT NULL DEFAULT 1,
    `missionlevel` INT NOT NULL DEFAULT 1,
    `missionstage` INT NOT NULL DEFAULT 1,
    `missionid` INT NOT NULL DEFAULT 1,
    `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 玩家扩展字段表 (对齐原版 zc_sanguo_member_field)
CREATE TABLE IF NOT EXISTS `zc_sanguo_member_field` (
    `uid` INT UNSIGNED PRIMARY KEY,
    `attackstatus` TINYINT NOT NULL DEFAULT 0,
    `attackdateline` INT UNSIGNED NOT NULL DEFAULT 0,
    `levytimes` INT NOT NULL DEFAULT 50,
    `levydateline` INT UNSIGNED NOT NULL DEFAULT 0,
    `practiceall` INT NOT NULL DEFAULT 2,
    `practicenow` INT NOT NULL DEFAULT 0,
    `practicetime` INT UNSIGNED NOT NULL DEFAULT 0,
    `rank` INT NOT NULL DEFAULT 1,
    `newresult` INT NOT NULL DEFAULT 0,
    `gift` INT NOT NULL DEFAULT 0,
    `race` TINYINT NOT NULL DEFAULT 0,
    `race_dateline` INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 武将表 (对齐原版 zc_sanguo_general)
CREATE TABLE IF NOT EXISTS `zc_sanguo_general` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL,
    `generalid` INT NOT NULL COMMENT '对应 ini.js 中的武将模版ID',
    `name` VARCHAR(64) NOT NULL DEFAULT '',
    `level` INT NOT NULL DEFAULT 1,
    `exp` BIGINT NOT NULL DEFAULT 0,
    `pos` TINYINT NOT NULL DEFAULT 0 COMMENT '九宫格阵位 1~9, 0为未出战',
    `matrix_id` TINYINT NOT NULL DEFAULT 1 COMMENT '所在阵法',
    `status` TINYINT NOT NULL DEFAULT 1,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
