-- 已有数据库升级：主城征收（c=city）、节日签到（c=dailysign）、累计登录（c=totallogin）、登录返礼（c=loginreward）、
-- 连续登录（c=logined，含主城连续登录天数 member_field.login_day / 主线任务 20）、等级礼包（c=levelgift）、激活码（c=activationcode）、
-- 邀请（c=invitation）、好友（c=friend）、整军演武（c=exercise）、神树（c=sacredtree，含战斗血量加成）、四方来朝（c=flop，
-- 含元宝 / 军令 / 突飞卡的翻牌计数）与主城建筑首次打开记录（edifice_model）按旧版移植（旧版 controllers/city.php、dailysign.php、
-- totallogin.php、loginreward.php、logined.php、levelgift.php、activationcode.php、invitation.php、friend.php、exercise.php、
-- sacredtree.php、flop.php 与对应 models，member_model 的 levy_cron / daily_cron / insert_member_log / update_continuous_login_day，
-- cron.php 的 sale_gift / clear_exercise / push_flop_reward / clear_flop_times）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。须在 20261103_q_daily2.sql 之后执行（文件名带 r_ 使其按名称排在
-- 20261103_q_daily2.sql 之后：本文件在 member_field / member_activity 末尾加列，依赖前面迁移加的列）。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261103_r_daily3.sql
--
-- 升级前：这些玩法是新版自定的，状态存 Redis：签到 dailysign:{uid}、累计登录 totallogin_days:{uid} / totallogin_claimed:{uid}、
--         连续登录 user_logined_days:{uid}、等级礼包 level_gifts:{uid}、激活码 code_used:{uid}:{code}、邀请 invitation_bound:{uid} /
--         invitation_count:{uid}、好友 user_friends:{uid}、演武 exercise_gifts:{uid}、神树 user_sacred_tree:{uid}、
--         翻牌 user_flop:{uid}；征收 / 强征的银两、暴击与元宝价格是自定的，征收次数不按每日 7 点发放。
-- 升级后：新建旧版 city_action_log、member_log、dailysign_log、totallogin、totallogin_reward_log、loginreward_reward_log、
--         logined_log、logined_list、levelgift_log、activationcode_log、invitation_code、invitation_reward_log、friend、
--         sacredtree_log、sacredtreeexp_log、sacredtree_action_log、flop_action_log、flop_cron_log、edifice_log；
--         member 增加 crontime / jointime；member_field 增加 levyall / exercisetimes / levystatus / imposetimes / qiangstatus /
--         qiangdateline / close_invitation / login_day / login_dateline / levelgift；member_activity 增加 exercise_gift /
--         exercise_level / floptimes / flopact / flopgold / flopleap / flopacttimes / flopgoldtimes / flopleaptimes。
--
-- 数据处理（前提：这些玩法现有的状态都是测试数据，对不上旧版结构）：
-- 1. 上述 Redis 键是新版自定的数据，不转换、不保留；升级后不再读写，可自行删除。
-- 2. 已有玩家 crontime 记为执行迁移的时间（下一个 7 点后的第一次请求按旧版 levy_cron 发放征收次数）；jointime 取 created_at。
-- 3. 已有玩家 login_day 0、login_dateline 0：下次打开主城时按旧版记为连续登录第 1 天；levelgift 1（显示等级礼包图标）。
-- 各表先 DROP 再建（会清空本批新表的数据）；末尾的 ALTER TABLE / UPDATE 只能执行一次。

-- 整军演武活动参加记录（city_model->add_action_log：city_action_log；演武配置在 MasterData Exercise / ExerciseAddition）
DROP TABLE IF EXISTS `zc_sanguo_city_action_log`;
CREATE TABLE `zc_sanguo_city_action_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的用户uid',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗金币',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='整军活动记录';

-- 每日计划任务日志（member_model->insert_member_log：member_log；登录返礼按它统计登录天数）
DROP TABLE IF EXISTS `zc_sanguo_member_log`;
CREATE TABLE `zc_sanguo_member_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ymd` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '记录日期',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `vip` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'VIP',
    `levytimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '前一天剩余免费征收次数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '记录时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='每日计划任务日志';

-- 签到记录（dailysign_model：dailysign_log；旧版无主键，新版加 (uid, class, version, day) 唯一键防并发重复签到）
DROP TABLE IF EXISTS `zc_sanguo_dailysign_log`;
CREATE TABLE `zc_sanguo_dailysign_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `day` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第几天签到',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '签到类型',
    `version` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动版本号',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_class_version_day` (`uid`, `class`, `version`, `day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='签到记录表';

-- 累计登录（totallogin_model：totallogin / totallogin_reward_log；奖励在 MasterData TotalloginReward）
DROP TABLE IF EXISTS `zc_sanguo_totallogin`;
CREATE TABLE `zc_sanguo_totallogin` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `day` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '累计登录的天数',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '登陆时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version` (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='累计登录用户表';

DROP TABLE IF EXISTS `zc_sanguo_totallogin_reward_log`;
CREATE TABLE `zc_sanguo_totallogin_reward_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version` (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='领取累计登陆奖励日志表';

-- 登录返礼领取日志（loginreward_model：loginreward_reward_log；唯一键照旧版不含 version）
DROP TABLE IF EXISTS `zc_sanguo_loginreward_reward_log`;
CREATE TABLE `zc_sanguo_loginreward_reward_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取奖励类型 1登陆返利奖励系数表（总奖励） 2登陆返利奖励表',
    `reward_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_type_reward` (`uid`, `type`, `reward_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='登录返礼领取奖励日志表';

-- 连续登录（logined_model：logined_log 连续登录天数 / logined_list 各天礼包状态；奖励在 MasterData LoginedReward）
DROP TABLE IF EXISTS `zc_sanguo_logined_log`;
CREATE TABLE `zc_sanguo_logined_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `day` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户连续登录的天数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '登录的时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='连续登录记录表';

DROP TABLE IF EXISTS `zc_sanguo_logined_list`;
CREATE TABLE `zc_sanguo_logined_list` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `day` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '持续登录的天数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取奖励时间',
    `status` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态(1可领取2未达成3已领取)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_day` (`uid`, `day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='持续登录记录表';

-- 等级礼包领取记录（levelgift_model：levelgift_log；奖励在 MasterData LevelgiftReward）
DROP TABLE IF EXISTS `zc_sanguo_levelgift_log`;
CREATE TABLE `zc_sanguo_levelgift_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `status` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取状态(1：领取 2：未达成 3：已领取)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_level` (`uid`, `level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='等级礼包表';

-- 激活码使用记录（activationcode_model：activationcode_log；四位激活码在 MasterData Activationcode，奖励在 ActivationcodeReward）
DROP TABLE IF EXISTS `zc_sanguo_activationcode_log`;
CREATE TABLE `zc_sanguo_activationcode_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '激活码内容',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '激活码类型（不同类型，奖励不同）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '使用时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户使用激活码记录';

-- 邀请码（invitation_model：invitation_code / invitation_reward_log；奖励在 MasterData InvitationReward）
DROP TABLE IF EXISTS `zc_sanguo_invitation_code`;
CREATE TABLE `zc_sanguo_invitation_code` (
    `uid` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT 'uid',
    `code` VARCHAR(8) NOT NULL DEFAULT '' COMMENT '邀请码',
    `number` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已邀请人数',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否被邀请',
    `to_code` VARCHAR(8) DEFAULT '' COMMENT '好友邀请码',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请码数据表';

DROP TABLE IF EXISTS `zc_sanguo_invitation_reward_log`;
CREATE TABLE `zc_sanguo_invitation_reward_log` (
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `rewardid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领奖时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`, `rewardid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请奖励领取记录表';

-- 好友（friend_model：friend；新版加 (uid, fuid) 唯一键防并发重复添加）
DROP TABLE IF EXISTS `zc_sanguo_friend`;
CREATE TABLE `zc_sanguo_friend` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属用户',
    `fuid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '好友uid',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前状态（1为好友，2为已删除）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '添加时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_fuid` (`uid`, `fuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='好友表';

-- 神树（sacredtree_model：sacredtree_log 用户神树 / sacredtreeexp_log 经验记录 / sacredtree_action_log 参加记录；
-- 神树模版在 MasterData Sacredtree / NewSacredtree（春节版），暴击权重在 SacredtreeRate）
DROP TABLE IF EXISTS `zc_sanguo_sacredtree_log`;
CREATE TABLE `zc_sanguo_sacredtree_log` (
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动版本号',
    `treeid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神树的id',
    `buff` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '血量加成百分比',
    `exp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前经验',
    `levelup_exp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '升级经验',
    `time` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '免费次数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '每日更新次数时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='神树用户记录表';

DROP TABLE IF EXISTS `zc_sanguo_sacredtreeexp_log`;
CREATE TABLE `zc_sanguo_sacredtreeexp_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神树活动版本号',
    `add_exp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '添加的经验',
    `now_exp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '添加后剩余经验',
    `now_level` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '添加后神树等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获得经验时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='神树获得经验记录表';

DROP TABLE IF EXISTS `zc_sanguo_sacredtree_action_log`;
CREATE TABLE `zc_sanguo_sacredtree_action_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的用户uid',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗金币',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='神树活动记录';

-- 四方来朝（flop_model：flop_action_log 参加记录；cron::push_flop_reward：flop_cron_log；规则在 MasterData Flop / FlopReward）
DROP TABLE IF EXISTS `zc_sanguo_flop_action_log`;
CREATE TABLE `zc_sanguo_flop_action_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的用户uid',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='朝贡活动记录';

DROP TABLE IF EXISTS `zc_sanguo_flop_cron_log`;
CREATE TABLE `zc_sanguo_flop_cron_log` (
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '日期20130626',
    `times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '四方来朝次数',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '元宝',
    `silver` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '银币',
    `act` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '军令',
    `leap1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '初级突飞卡',
    `leap2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '中级突飞卡',
    `leap3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '高级突飞卡',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '发放时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='四方来朝计划任务记录';

-- 主城建筑首次打开记录（edifice_model：edifice_log；建筑列表写死在 Model\Edifice::LIST，同旧版）
DROP TABLE IF EXISTS `zc_sanguo_edifice_log`;
CREATE TABLE `zc_sanguo_edifice_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `edifice_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '功能id',
    `edifice_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '功能名称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_edifice` (`uid`, `edifice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户功能开启操作时间';

ALTER TABLE `zc_sanguo_member`
    ADD COLUMN `crontime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '征收计划任务执行时间（每日 7 点发放征收次数）' AFTER `newmail`,
    ADD COLUMN `jointime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '加入游戏时间' AFTER `crontime`;

ALTER TABLE `zc_sanguo_member_field`
    ADD COLUMN `levyall` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '总征收次数（整军演武）' AFTER `inherit_dateline`,
    ADD COLUMN `exercisetimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '征收恢复时间' AFTER `levyall`,
    ADD COLUMN `levystatus` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '征收状态 0 可以征收 1 冷却中' AFTER `exercisetimes`,
    ADD COLUMN `imposetimes` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '今日强征次数' AFTER `levystatus`,
    ADD COLUMN `qiangstatus` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '强化状态 0 可以强化 1 冷却中' AFTER `imposetimes`,
    ADD COLUMN `qiangdateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '强化CD过期时间' AFTER `qiangstatus`,
    ADD COLUMN `close_invitation` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关闭邀请' AFTER `qiangdateline`,
    ADD COLUMN `login_day` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '连续登录天数' AFTER `close_invitation`,
    ADD COLUMN `login_dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '连续登录天数更新时间' AFTER `login_day`,
    ADD COLUMN `levelgift` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '等级礼包图标(0消失1显示2可领取)' AFTER `login_dateline`;

ALTER TABLE `zc_sanguo_member_activity`
    ADD COLUMN `exercise_gift` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '整军演武礼包剩余数' AFTER `generaltasktimes`,
    ADD COLUMN `exercise_level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获得礼包时等级' AFTER `exercise_gift`,
    ADD COLUMN `floptimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '四方来朝剩余次数' AFTER `exercise_level`,
    ADD COLUMN `flopact` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '四方来朝军令花费' AFTER `floptimes`,
    ADD COLUMN `flopgold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '四方来朝元宝花费' AFTER `flopact`,
    ADD COLUMN `flopleap` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '四方来朝突飞次数' AFTER `flopgold`,
    ADD COLUMN `flopacttimes` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '军令次数' AFTER `flopleap`,
    ADD COLUMN `flopgoldtimes` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '元宝次数' AFTER `flopacttimes`,
    ADD COLUMN `flopleaptimes` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '突飞获得次数' AFTER `flopgoldtimes`;

UPDATE `zc_sanguo_member` SET `crontime` = UNIX_TIMESTAMP(), `jointime` = UNIX_TIMESTAMP(`created_at`);
