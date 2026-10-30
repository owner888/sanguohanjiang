-- 已有数据库升级：批次 batch1——切磋（c=challenge）、天下史诗（c=epic）、过关斩将（c=pass_general）改回旧版
-- （controllers/challenge.php、controllers/epic.php + models/epic_model.php、controllers/pass_general.php +
-- models/passgeneral_model.php + controllers/cron_acttask.php:pass_general）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261101_batch1.sql
--
-- 升级前：三个玩法都是新版自定的战斗（自定守军 / 奖励），进度只存 Redis（challenge_master:{uid}、epic_cleared:{uid}、
--         pass_general_stage:{uid}），库里没有对应表。
-- 升级后：切磋不需要表（旧版控制器不写 challenge_log）；新增 epic_log（天下史诗）、passgeneral / passgeneral_log /
--         passgeneral_box_log / passgeneral_achievement_log / passgeneral_exchange_log（过关斩将）；
--         member_integral 只改表注释（兑奖券 act_kemari_int 字段已存在）。
--
-- 数据处理（升级前只有测试数据）：上述 Redis 进度与旧版玩法对不上，不转换、不保留，可自行删除。
-- 可重复执行（各表 DROP 后重建，数据清空）。

-- -------------------------------------------------------------
-- 天下史诗 (对齐原版 zc_sanguo_epic_log；史诗 / 残卷配置在 MasterData Epic / EpicChip)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_epic_log`;
CREATE TABLE `zc_sanguo_epic_log` (
    `uid` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '用户uid',
    `refreshtimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '刷新剩余次数',
    `buy_refreshtimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '购买的刷新次数',
    `locktimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '锁定的剩余次数',
    `buy_locktimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '购买的锁定次数',
    `epicid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '史诗id',
    `site1` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置1残卷ID',
    `site2` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置2残卷ID',
    `site3` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置3残卷ID',
    `site4` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置4残卷ID',
    `site5` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置5残卷ID',
    `lock_site` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '锁定位置',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='天下史诗用户数据表';

-- -------------------------------------------------------------
-- 过关斩将 (对齐原版 zc_sanguo_passgeneral / passgeneral_log / passgeneral_box_log / passgeneral_achievement_log /
-- passgeneral_exchange_log；兑奖券为 member_integral.act_kemari_int；守将 / 奖励 / 宝箱 / 成就配置在 MasterData Passgeneral*)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_passgeneral`;
CREATE TABLE `zc_sanguo_passgeneral` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `version` INT NOT NULL DEFAULT 0 COMMENT '版本号',
    `num` INT NOT NULL DEFAULT 0 COMMENT '突破层数',
    `free_times1` INT NOT NULL DEFAULT 0 COMMENT '过人免费次数',
    `free_times2` INT NOT NULL DEFAULT 0 COMMENT '强制过人免费次数',
    `normal_time1` INT NOT NULL DEFAULT 0 COMMENT '过人下一次可操作时间',
    `normal_time2` INT NOT NULL DEFAULT 0 COMMENT '强制过人可操作时间',
    `pass_id` INT NOT NULL DEFAULT 0 COMMENT '将要挑战的过人id',
    `is_pass` INT NOT NULL DEFAULT 0 COMMENT '已经挑战的过人id',
    `succ_num` INT NOT NULL DEFAULT 0 COMMENT '通关次数',
    `is_get` INT NOT NULL DEFAULT 0 COMMENT '是否领取层数奖励（1 未达成 2 可领取 3 已领取）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `refresh_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '免费次数刷新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version` (`uid`, `version`),
    INDEX `idx_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='过关斩将信息表';

DROP TABLE IF EXISTS `zc_sanguo_passgeneral_log`;
CREATE TABLE `zc_sanguo_passgeneral_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `version` INT NOT NULL DEFAULT 0 COMMENT '版本号',
    `num` INT NOT NULL DEFAULT 0 COMMENT '突破层数',
    `num_id` INT NOT NULL DEFAULT 0 COMMENT '过人id',
    `g_id` INT NOT NULL DEFAULT 0 COMMENT '将领id',
    `succ_num` INT NOT NULL DEFAULT 0 COMMENT '通关次数',
    `is_get` INT NOT NULL DEFAULT 0 COMMENT '是否领取层数奖励',
    `is_succ` INT NOT NULL DEFAULT 0 COMMENT '是否过人奖励',
    `type` INT NOT NULL DEFAULT 0 COMMENT '奖励类型',
    `value` INT NOT NULL DEFAULT 0 COMMENT '奖励值',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid_version` (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='过关斩将过人日志表';

DROP TABLE IF EXISTS `zc_sanguo_passgeneral_box_log`;
CREATE TABLE `zc_sanguo_passgeneral_box_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `version` INT NOT NULL DEFAULT 0 COMMENT '版本号',
    `num` INT NOT NULL DEFAULT 0 COMMENT '突破层数',
    `name1` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '奖励名称（旧版写日志时不写名称，为空串）',
    `type1` INT NOT NULL DEFAULT 0 COMMENT '奖励物品1类型',
    `value1` INT NOT NULL DEFAULT 0 COMMENT '参数（旧版手动领取时记的是 type1）',
    `name2` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '奖励名称',
    `type2` INT NOT NULL DEFAULT 0 COMMENT '奖励物品2类型',
    `value2` INT NOT NULL DEFAULT 0 COMMENT '参数（自动领取时不写，为 0）',
    `name3` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '奖励名称',
    `type3` INT NOT NULL DEFAULT 0 COMMENT '奖励物品3类型',
    `value3` INT NOT NULL DEFAULT 0 COMMENT '参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid_version` (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='过关斩将领取宝箱日志表';

DROP TABLE IF EXISTS `zc_sanguo_passgeneral_achievement_log`;
CREATE TABLE `zc_sanguo_passgeneral_achievement_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `achieve_id` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '成就id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '兑奖时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version_achieve` (`uid`, `version`, `achieve_id`),
    INDEX `idx_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='过关斩将成就领取日志表（唯一键防止手动领取与活动结束自动领取重复发放）';

DROP TABLE IF EXISTS `zc_sanguo_passgeneral_exchange_log`;
CREATE TABLE `zc_sanguo_passgeneral_exchange_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '角色名称',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖品类型',
    `value` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖品数量',
    `value1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '装备精华数量',
    `lottery_num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗奖券',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '兑奖时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='过关斩将奖券兑换日志表';

ALTER TABLE `zc_sanguo_member_integral` COMMENT='玩家积分记录表（演武榜、世界 BOSS 奖励读写 act_arena_int；过关斩将读写兑奖券 act_kemari_int）';
