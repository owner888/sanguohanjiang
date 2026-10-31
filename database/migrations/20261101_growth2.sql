-- 已有数据库升级：养成（第二批）——铜雀台美人（c=beauty）按旧版移植
-- （旧版 controllers/beauty.php + models/beauty_model.php，以及 information.php get_all_beauty_info、
--  battle_model 的美人加成 _beauty_arr、general.php _get_beauty_info）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261101_growth2.sql
--
-- 升级前：美人数据是新版自定的 Redis 键 user_beauties:{uid}（五位美人的等级 / 亲密度，与旧版玩法对不上），没有数据库表。
-- 升级后：新建旧版的 beauty_own_log（拥有的美人）、beauty_own_chip（碎片）、beauty_info（免战 / 抢夺次数）、
--         beauty_loot_list（抢夺对手列表）、beauty_loot_log（抢夺日志）、beauty_active_log（互动次数）、
--         beauty_buyloot_log / beauty_buyrose_log（购买日志）、beauty_speical_reward_log（特殊技能领取日志）。
--         玫瑰花 / 钻石沿用已有的 member_integral.beauty_rose / beauty_diamond。
--
-- 数据处理（升级前只有测试数据）：user_beauties 是新版自定的数据，对不上旧版结构，不转换、不保留；
-- 升级后这个 Redis 键不再读写，可自行删除：user_beauties:{uid}。
-- 本文件可重复执行（各表先 DROP 再建，会清空本批新表的数据）。

-- -------------------------------------------------------------
-- 养成：铜雀台美人（旧版 models/beauty_model.php；Model\Beauty，c=beauty）
-- 玫瑰花 / 钻石为 member_integral.beauty_rose / beauty_diamond
-- -------------------------------------------------------------

-- 玩家拥有的美人（beauty_own_log；gift / 碎片奖励也会建休息中的记录）
DROP TABLE IF EXISTS `zc_sanguo_beauty_own_log`;
CREATE TABLE `zc_sanguo_beauty_own_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `beauty_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '拥有美人id',
    `beauty_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '美人名字',
    `beauty_level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '美人等级',
    `skill1` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '技能1',
    `skill2` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '技能2',
    `skill3` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '技能3',
    `quality` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1白2绿3蓝4紫5红6橙',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获得美人时间',
    `open_status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1：激活 0：休息',
    `up_status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0未升级1升级中',
    `up_dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '升级完成时间',
    `effect_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '技能生效时间',
    `effect_end_time` INT NOT NULL DEFAULT 0 COMMENT '技能生效结束时间',
    `ratio` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '好感度参数',
    `ratio_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '好感度开始时间',
    `is_play` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '升级动画 0未播放；1已播放；2抢夺时替对方完成升级',
    `wakeup_num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '本次激活后送花的次数（特殊技能领取日志按它分组）',
    `wakeup_dateline` INT NOT NULL DEFAULT 0 COMMENT '当前美人最后一次激活时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_beauty` (`uid`, `beauty_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='玩家拥有美人log';

-- 玩家拥有的美人碎片（beauty_own_chip；掠夺按 beauty_id + chip_id 找持有者）
DROP TABLE IF EXISTS `zc_sanguo_beauty_own_chip`;
CREATE TABLE `zc_sanguo_beauty_own_chip` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '序列号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `beauty_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '美人id',
    `chip` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '碎片数量',
    `chip_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '碎片id',
    `chip_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '碎片名称',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获取时间',
    `disable` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '>0 不能用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_beauty_chip` (`uid`, `beauty_id`, `chip_id`),
    INDEX `idx_beauty_chip` (`beauty_id`, `chip_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='玩家拥有碎片表';

-- 铜雀台玩家信息（beauty_info：免战次数 / 结束时间、抢夺免费 / 购买次数、刷新对手时间）
DROP TABLE IF EXISTS `zc_sanguo_beauty_info`;
CREATE TABLE `zc_sanguo_beauty_info` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '序列号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `vip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip',
    `times` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '免战的次数',
    `cd_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '免战的结束时间',
    `buy_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '免战购买时间',
    `free_times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '抢夺免费次数剩余',
    `vip_times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip购买抢夺次数剩余',
    `reflush_dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '刷新对手时间',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '抢夺次数重置时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='铜雀台玩家信息表';

-- 抢夺对手列表（beauty_loot_list；list 为 JSON）
DROP TABLE IF EXISTS `zc_sanguo_beauty_loot_list`;
CREATE TABLE `zc_sanguo_beauty_loot_list` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `beauty_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '美人id',
    `chip_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '碎片id',
    `list` TEXT NOT NULL COMMENT '抢夺列表',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_beauty_chip` (`uid`, `beauty_id`, `chip_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='铜雀台用户抢夺列表';

-- 抢夺日志（beauty_loot_log；report_id 为给被抢方的掠夺信件 id）
DROP TABLE IF EXISTS `zc_sanguo_beauty_loot_log`;
CREATE TABLE `zc_sanguo_beauty_loot_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '角色昵称',
    `to_uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '被抢夺用户id',
    `to_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '被抢夺用户名称',
    `chip_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '碎片id',
    `beauty_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '美人id',
    `reward_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励id',
    `chip_status` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否获得碎片',
    `battle_status` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '挑战是否成功',
    `report_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`),
    INDEX `idx_to_uid` (`to_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='铜雀台用户抢夺日志表';

-- 美人互动次数（beauty_active_log：问候 / 亲近 / 宠幸剩余次数，问候时间跨天时重置）
DROP TABLE IF EXISTS `zc_sanguo_beauty_active_log`;
CREATE TABLE `zc_sanguo_beauty_active_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `greeting` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '问候剩余的次数',
    `g_dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '问候时间',
    `close` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '亲近次数',
    `love` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '宠幸次数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='美人互动log表';

-- 购买抢夺次数日志（beauty_buyloot_log）
DROP TABLE IF EXISTS `zc_sanguo_beauty_buyloot_log`;
CREATE TABLE `zc_sanguo_beauty_buyloot_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `vip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip',
    `free_times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '购买后的免费次数',
    `price` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '花费价格',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='铜雀台购买抢夺次数Log';

-- 购买玫瑰花日志（beauty_buyrose_log）
DROP TABLE IF EXISTS `zc_sanguo_beauty_buyrose_log`;
CREATE TABLE `zc_sanguo_beauty_buyrose_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `price` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '花费价格',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='铜雀台购买玫瑰花Log';

-- 特殊技能产出领取日志（beauty_speical_reward_log，表名拼写同旧版）
DROP TABLE IF EXISTS `zc_sanguo_beauty_speical_reward_log`;
CREATE TABLE `zc_sanguo_beauty_speical_reward_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `beauty_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '美人id',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励类型（技能属性 13 ~ 19）',
    `num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取个数',
    `wakeup_num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前第几次激活',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid_beauty` (`uid`, `beauty_id`, `wakeup_num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='铜雀台领取特殊奖励Log';
