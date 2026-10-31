-- 已有数据库升级：养成（第一批）——将魂（c=soul）、主公星魄 / 命格（c=star）、主公天赋（c=talent）按旧版移植
-- （旧版 controllers/soul.php + models/soul_model.php、controllers/star.php + models/star_model.php、
--  controllers/talent.php + models/talent_model.php，以及 member_model add_soul / sub_soul / _soul_log）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261101_growth1.sql
--
-- 升级前：将魂槽是新版自定的 zc_sanguo_general_soul（slot1~slot8 存孔位等级，与旧版「槽位装备的将魂 id」不是一回事）；
--         武将星级存 Redis general_star:{uid}:{gid}，武将天赋存 Redis general_talents:{uid}:{gid}（都是新版自定的逐将玩法，
--         旧版 star / talent 是主公的命格与天赋）。
-- 升级后：删除 zc_sanguo_general_soul，新建旧版 own_general_soul（将领将魂槽，gid 唯一）、own_soul_mergelog（将魂合成日志）、
--         usesoul_log（原魂流水）、own_star / star_tree / star_log / own_star_addition（主公星魄、命格树、星魄流水、命格加成）、
--         own_hero_talent（主公天赋）。
--
-- 数据处理（升级前只有测试数据）：general_soul 的孔位等级与 Redis 键都是新版自定的数据，对不上旧版结构，不转换、不保留；
-- 升级后这些 Redis 键不再读写，可自行删除：general_star:{uid}:{gid}、general_talents:{uid}:{gid}。
-- 已有将魂（own_soul）此前不能装备（gid 都是 0），全部留在包裹里；将领的将魂槽在玩家打开将魂界面时按旧版建空槽。
-- 本文件可重复执行（各表先 DROP 再建，会清空本批新表的数据）。

DROP TABLE IF EXISTS `zc_sanguo_general_soul`;

-- 将领将魂槽（旧版 own_general_soul，主键 gid；soul_model get_general_soul / add_general_soul / update_general；Model\OwnSoul）
DROP TABLE IF EXISTS `zc_sanguo_own_general_soul`;
CREATE TABLE `zc_sanguo_own_general_soul` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gid` INT UNSIGNED NOT NULL COMMENT '将领id（general.id）',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `site1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置1（own_soul.id，0 为空）',
    `site2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置2',
    `site3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置3',
    `site4` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置4',
    `site5` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置5',
    `site6` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置6',
    `site7` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置7',
    `site8` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置8',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_gid` (`gid`),
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='拥有将领将魂表';

-- -------------------------------------------------------------
-- 养成：将魂合成与原魂流水、主公星魄 / 命格、主公天赋
-- （旧版 soul_model add_soul_mergelog、member_model add_soul / sub_soul / _soul_log、star_model、talent_model；
--  Model\OwnSoul / OwnStar / HeroTalent，c=soul / star / talent）
-- -------------------------------------------------------------

-- 将魂合成日志（soul_model add_soul_mergelog；new_sid 同旧版记新将魂的模板 id）
DROP TABLE IF EXISTS `zc_sanguo_own_soul_mergelog`;
CREATE TABLE `zc_sanguo_own_soul_mergelog` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `sid1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '合成将魂ID1',
    `sid2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '合成将魂ID2',
    `new_sid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '新合成将魂ID',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '合成时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='将魂合成日志表';

-- 原魂流水（member_model _soul_log；member.soul 的增减）
DROP TABLE IF EXISTS `zc_sanguo_usesoul_log`;
CREATE TABLE `zc_sanguo_usesoul_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '记录类型1：获得 2：花费',
    `reputation` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作原魂数',
    `balance` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余原魂',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `argument` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `vip` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='原魂消耗记录表';

-- 主公星魄（star_model init_star / get_own_star / update_own_star）
DROP TABLE IF EXISTS `zc_sanguo_own_star`;
CREATE TABLE `zc_sanguo_own_star` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `star1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '幽蓝星魄数量',
    `star2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '铭黄星魄数量',
    `star3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '紫阳星魄数量',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首次操作时间',
    `lastsite` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '最后选择的炼化炉',
    `lastlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后升级的命格',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='拥有星魄';

-- 主公命格树（star_model init_star_tree / update_star_tree / get_star_tree）
DROP TABLE IF EXISTS `zc_sanguo_star_tree`;
CREATE TABLE `zc_sanguo_star_tree` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `starid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '命格id',
    `level` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_starid` (`uid`, `starid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户命格树';

-- 星魄流水（star_model add_star_log）
DROP TABLE IF EXISTS `zc_sanguo_star_log`;
CREATE TABLE `zc_sanguo_star_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `startype` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '星魄类型',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗获得（1 获得 2 扣除）',
    `star` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '星魄数量',
    `balance` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '余额',
    `class` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '执行方法',
    `argument` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0,
    `treeid` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '星魄树id',
    `level` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '升级时的等级',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户星魄log';

-- 主公命格加成（star_model get_star_addition / update_star_addition；战斗与将领界面读取）
DROP TABLE IF EXISTS `zc_sanguo_own_star_addition`;
CREATE TABLE `zc_sanguo_own_star_addition` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `wuli` INT UNSIGNED NOT NULL DEFAULT 0,
    `zhili` INT UNSIGNED NOT NULL DEFAULT 0,
    `tili` INT UNSIGNED NOT NULL DEFAULT 0,
    `attack` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '物攻',
    `defense` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '物防',
    `pattack` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '策攻',
    `pdefense` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '策防',
    `blood` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '血',
    `dodge` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '闪避',
    `crit` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '暴击率',
    `penetrate` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '识破',
    `harm` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '暴击伤害',
    `power` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '士气',
    `mingzhong` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '命中',
    `jingzhun` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '精准',
    `kangyun` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '抗晕',
    `kcrit` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '抗暴击率',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='主公命格加成信息表';

-- 主公天赋（talent_model；type 1 物攻 / 2 物防 / 3 策攻 / 4 策防 / 5 生命）
DROP TABLE IF EXISTS `zc_sanguo_own_hero_talent`;
CREATE TABLE `zc_sanguo_own_hero_talent` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '类型(1物攻;2物防;3策攻;4策防;5生命)',
    `quality` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '品级',
    `star` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '星级',
    `power` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '增加值',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_type` (`uid`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='主公拥有天赋表';
