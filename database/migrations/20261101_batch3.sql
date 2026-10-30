-- 已有数据库升级：批次 batch3——群雄逐鹿（c=compete）、国家矿脉（c=countrymine）、国战 / 都城（c=siege）改回旧版
-- （controllers/compete.php + models/compete_model.php、controllers/countrymine.php + models/countrymine_model.php、
-- controllers/siege.php + models/siege_model.php + models/countryfight_model.php，计划任务 cron.php:siege_battle /
-- clear_warstatus / get_compete_reward）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261101_batch3.sql
--
-- 升级前：三个玩法都是新版自定的（自定对手 / 守军 / 矿山 / 奖励）。群雄逐鹿积分与次数只存 Redis（compete_score:{uid}、
--         compete_times:{uid}），国家矿山只存 Redis（user_country_mine:{uid}，魏 / 蜀 / 吴三国矿是新版自定），
--         攻城为新版自定的 zc_sanguo_siege 表（6 座城、预置数据）与 Redis（siege_reward:{uid}）。
-- 升级后：删除 zc_sanguo_siege；新增 compete / compete_action_log（群雄逐鹿）、country_mine / country_fight_notice
--         （国家矿脉与国家战报）、country_city / country_city_report / country_siege_log（国战）；
--         member_field 新增 oncountrymine（国家矿占领到期时间）。
--
-- 数据处理（升级前只有测试数据）：zc_sanguo_siege 与上述 Redis 键是新版自定的，与旧版玩法对不上，不转换、不保留，
-- zc_sanguo_siege 随本文件删除，Redis 键可自行删除。
-- 只需执行一次；重复执行会在 ADD COLUMN oncountrymine 处报错退出（此前新建的表已重建、数据清空）。

DROP TABLE IF EXISTS `zc_sanguo_siege`;

-- -------------------------------------------------------------
-- 群雄逐鹿 (对齐原版 zc_sanguo_compete / compete_action_log；活动时间在 MasterData ActivityTime（compete），
-- 名次奖励在 MasterData CompeteReward / NewcompeteReward)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_compete`;
CREATE TABLE `zc_sanguo_compete` (
    `uid` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '用户uid',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '玩家名字（入榜时的昵称）',
    `rank` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排名',
    `times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余次数',
    `get_rank` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取奖励时排名（0 为未领取）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后更新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_rank` (`rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户逐鹿数据表';

DROP TABLE IF EXISTS `zc_sanguo_compete_action_log`;
CREATE TABLE `zc_sanguo_compete_action_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL COMMENT '参加活动的用户uid',
    `level` INT UNSIGNED NOT NULL COMMENT '等级',
    `dateline` INT UNSIGNED NOT NULL COMMENT '参加活动的时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='逐鹿活动参加记录';

-- -------------------------------------------------------------
-- 国家矿脉 (对齐原版 zc_sanguo_country_mine；矿系数 / 随机事件在 MasterData CountryMineRatio / CountryMineEvent；
-- 占矿到期时间为 member_field.oncountrymine)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_country_mine`;
CREATE TABLE `zc_sanguo_country_mine` (
    `uid` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '用户uid',
    `times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '占领剩余次数',
    `robtimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '抢劫剩余次数',
    `page` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第几页',
    `site` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第几个位置',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '矿的类型（CountryMineRatio.id）',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '占矿时等级',
    `jointime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '占领矿区时间',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '每日进入矿区时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_page_site` (`page`, `site`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='矿战记录表';

DROP TABLE IF EXISTS `zc_sanguo_country_fight_notice`;
CREATE TABLE `zc_sanguo_country_fight_notice` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主键ID',
    `type` INT NOT NULL DEFAULT 0 COMMENT '战报类型 0，国家抢矿；1，海外贸易；',
    `member1` BIGINT NOT NULL DEFAULT 0 COMMENT '角色1UID',
    `member1_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '角色1名称',
    `member_country` SMALLINT NOT NULL DEFAULT 0 COMMENT '角色1国家ID',
    `member_country_name` VARCHAR(21) NOT NULL DEFAULT '' COMMENT '角色1国家名称',
    `member2` BIGINT NOT NULL DEFAULT 0 COMMENT '角色2ID',
    `member2_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '角色2名称',
    `attack_uid` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻击者UID',
    `attack_nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '攻击者用户名',
    `attack_country` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻击者国家ID',
    `attack_country_name` VARCHAR(21) NOT NULL DEFAULT '' COMMENT '攻击值国家名称',
    `win` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否成功（1，成功；0，失败；）',
    `dateline` INT NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_attack_country` (`attack_country`),
    INDEX `idx_member_country` (`member_country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家抢矿、海外贸易日志';

-- -------------------------------------------------------------
-- 国战 / 都城 (对齐原版 zc_sanguo_country_city / country_city_report / country_siege_log；
-- 都城配置在 MasterData CountryCityEffect，NPC 守将在 MasterData CountryMission / CountryMonster)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_country_city`;
CREATE TABLE `zc_sanguo_country_city` (
    `maplevel` TINYINT UNSIGNED NOT NULL PRIMARY KEY COMMENT '都城id（CountryCityEffect.id）',
    `challengercid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '挑战者国家id',
    `challengername` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '挑战者国家名字',
    `challengerlevel` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '挑战方等级',
    `challengerdateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '挑战时间',
    `cid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '占领的国家id',
    `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '国家名称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '占领者国家等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '占领时间',
    `lock` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否被国家通过至尊占领锁定',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_cid` (`cid`),
    INDEX `idx_challengercid` (`challengercid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='都城占领表';

-- 原版 report 为 TEXT：整场团战战报 json 常超过 64KB，改为 MEDIUMTEXT
DROP TABLE IF EXISTS `zc_sanguo_country_city_report`;
CREATE TABLE `zc_sanguo_country_city_report` (
    `id` MEDIUMINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '战报id',
    `city` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '城池id',
    `report` MEDIUMTEXT NOT NULL COMMENT '战报内容（json：双方名单、团战过程 battle、杀敌榜）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_city_dateline` (`city`, `dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国战战报表';

DROP TABLE IF EXISTS `zc_sanguo_country_siege_log`;
CREATE TABLE `zc_sanguo_country_siege_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主键ID',
    `city` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '城池ID',
    `city_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '城池名称',
    `report` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战报ID',
    `country` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '被攻击方国家ID',
    `country_name` VARCHAR(21) NOT NULL DEFAULT '' COMMENT '被攻击方国家名称',
    `attack_country` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻击方国家ID',
    `attack_country_name` VARCHAR(21) NOT NULL DEFAULT '' COMMENT '攻击方国家名称',
    `zhizun` INT NOT NULL DEFAULT 0 COMMENT '是否是至尊攻城（0，不是；1，是；）',
    `win_type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战胜or失败（1，战胜；0，失败；）',
    `dateline` INT NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家国战日志表';

ALTER TABLE `zc_sanguo_member_field`
    ADD COLUMN `oncountrymine` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家矿占领到期时间（到期后主城提示可收获，收获 / 放弃 / 被抢时清 0）' AFTER `heaventimes`;
