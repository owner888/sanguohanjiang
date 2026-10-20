-- 悍将三国 (Modern Edition) 核心数据库完整架构定义

CREATE DATABASE IF NOT EXISTS `zc_sanguo` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `zc_sanguo`;

-- 1. 用户账号表
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

-- 2. 玩家基础数据表 (对齐原版 zc_sanguo_member)
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

-- 3. 玩家扩展属性表 (对齐原版 zc_sanguo_member_field)
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

-- 4. 武将表 (已招募武将及属性)
CREATE TABLE IF NOT EXISTS `zc_sanguo_general` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL,
    `generalid` INT NOT NULL COMMENT '武将模版ID',
    `name` VARCHAR(64) NOT NULL DEFAULT '',
    `level` INT NOT NULL DEFAULT 1,
    `exp` BIGINT NOT NULL DEFAULT 0,
    `pos` TINYINT NOT NULL DEFAULT 0 COMMENT '九宫格阵位 1~9, 0未出战',
    `matrix_id` TINYINT NOT NULL DEFAULT 1,
    `status` TINYINT NOT NULL DEFAULT 1,
    `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. 阵法阵型表 (九宫格站位 1~9 号位)
CREATE TABLE IF NOT EXISTS `zc_sanguo_matrix` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL,
    `mid` TINYINT NOT NULL DEFAULT 1 COMMENT '阵法ID: 1鱼鳞阵 2锋矢阵等',
    `level` INT NOT NULL DEFAULT 1,
    `position1` INT NOT NULL DEFAULT 0 COMMENT '1号位武将ID',
    `position2` INT NOT NULL DEFAULT 0 COMMENT '2号位武将ID',
    `position3` INT NOT NULL DEFAULT 0 COMMENT '3号位武将ID',
    `position4` INT NOT NULL DEFAULT 0 COMMENT '4号位武将ID',
    `position5` INT NOT NULL DEFAULT 0 COMMENT '5号位武将ID',
    `position6` INT NOT NULL DEFAULT 0 COMMENT '6号位武将ID',
    `position7` INT NOT NULL DEFAULT 0 COMMENT '7号位武将ID',
    `position8` INT NOT NULL DEFAULT 0 COMMENT '8号位武将ID',
    `position9` INT NOT NULL DEFAULT 0 COMMENT '9号位武将ID',
    UNIQUE KEY `uk_uid_mid` (`uid`, `mid`),
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. 副本征战进度表
CREATE TABLE IF NOT EXISTS `zc_sanguo_map` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL,
    `level` INT NOT NULL DEFAULT 1 COMMENT '大地图势力ID',
    `stage` INT NOT NULL DEFAULT 1 COMMENT '关卡ID',
    `mission` INT NOT NULL DEFAULT 0 COMMENT '已通关据点ID',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY `uk_uid_level_stage` (`uid`, `level`, `stage`),
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. 校场训练位表
CREATE TABLE IF NOT EXISTS `zc_sanguo_practice` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL,
    `squence` TINYINT NOT NULL DEFAULT 1 COMMENT '训练位序号',
    `generalid` INT NOT NULL DEFAULT 0 COMMENT '正在训练的武将',
    `level` INT NOT NULL DEFAULT 1,
    `type` TINYINT NOT NULL DEFAULT 1,
    `endtime` INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY `uk_uid_seq` (`uid`, `squence`),
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. 玩家装备与道具背包表
CREATE TABLE IF NOT EXISTS `zc_sanguo_equipment` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL,
    `equipid` INT NOT NULL,
    `name` VARCHAR(64) NOT NULL DEFAULT '',
    `type` TINYINT NOT NULL DEFAULT 1 COMMENT '1武器 2衣服 3坐骑 4书籍 5兵符',
    `quality` TINYINT NOT NULL DEFAULT 1,
    `level` INT NOT NULL DEFAULT 1,
    `generalid` INT NOT NULL DEFAULT 0 COMMENT '穿戴该装备的武将ID',
    `number` INT NOT NULL DEFAULT 1,
    `saleprice` INT NOT NULL DEFAULT 0,
    `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX `idx_uid` (`uid`),
    INDEX `idx_general` (`uid`, `generalid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
