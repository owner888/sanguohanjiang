-- 已有数据库升级：信件（c=message）、新 GM 信箱（c=gmmail）、日常任务（c=task）、主线任务（c=mainquest）按旧版移植，
-- 货币流水与付费点统计按旧版补齐（旧版 controllers/message.php、gmmail.php + models/message_model.php；
-- controllers/task.php + models/task_model.php；controllers/mainquest.php + models/mainquest_model.php；
-- models/member_model.php 的 _gold_log / _silver_log / _act_log / _reputation_log；models/stat_model.php 的 stat_pay）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。须在 20261102_pay.sql 之后执行（文件名带 q_ 使其按名称排在 20261102_pay.sql 之后：本文件的 ALTER 用到该文件新增的列）。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261102_q_daily1.sql
--
-- 升级前：信件存 Redis 键 user_messages:{uid}（新版自定的结构，第一次打开信箱时写入三封自定的欢迎信件），没有战报回放；
--         日常任务存 Redis 键 user_daily_tasks:{uid}:{Ymd}（自定的 6 个任务，接任务即完成）；主线任务存 Redis 键
--         mainquest:task_id:{uid}（自定的 6 个任务）；没有 GM 信箱；货币增减不记流水。
-- 升级后：新建旧版 pm（信件）、pm_report（信件战报）、gm（新 GM 信箱）、task_log（日常任务记录）、main_quest_log（主线任务）、
--         main_quest_reward（主线领奖记录）、gold_log / silver_log / act_log / reputation_log（货币流水）、stat_pay（付费点统计）；
--         member 增加 newpm / newmail（新信件提示）；member_activity 增加 task_number（日常任务今日剩余次数，每日重置为 6）。
--
-- 数据处理（升级前只有测试数据）：上述 Redis 键是新版自定的数据，对不上旧版结构，不转换、不保留；升级后不再读写，
-- 可自行删除。已有的 member_activity 行 task_number 为 0，玩家当天第一次请求（每日计划任务）后重置为 6。
-- 各表先 DROP 再建（会清空本批新表的数据）；末尾的 ALTER TABLE 只能执行一次。

-- 信件（message_model：pm）
DROP TABLE IF EXISTS `zc_sanguo_pm`;
CREATE TABLE `zc_sanguo_pm` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '信件id',
    `fromuid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '发送者ID',
    `fromname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '发送者名称',
    `title` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '标题',
    `touid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '接收者id',
    `toname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '接收者姓名',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '信件类型（1系统，2vip，3矿战，4演武榜，5玩家互动，6GM，7国家通知，8跨服远征，9海外贸易，10国家信件，11国家矿区）',
    `message` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '信件内容',
    `isread` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否阅读',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态（0提交，1回复，2发信方删除，3收信方删除）',
    `reply` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'GM回复信息',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '附件中金币',
    `silver` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '附件中的银币',
    `act` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '附件中的军令',
    `barcard` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '附件中的突飞卡',
    `giftstatus` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '附件领取状态（1已领取或没有附件）',
    `report` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0是普通信件，1是战报信件',
    `channel` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户登录渠道 id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '发信时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_isread` (`isread`, `dateline`),
    INDEX `idx_touid` (`touid`),
    INDEX `idx_fromuid` (`fromuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='信件表';

-- 信件战报（message_model：pm_report，id 即信件 id；report 旧版 TEXT，战报 JSON 可能超过 64KB，改 MEDIUMTEXT）
DROP TABLE IF EXISTS `zc_sanguo_pm_report`;
CREATE TABLE `zc_sanguo_pm_report` (
    `id` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '信件id',
    `report` MEDIUMTEXT NOT NULL COMMENT '战斗过程',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '攻方昵称',
    `userlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻方等级',
    `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '守方昵称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '守方等级',
    `background` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗背景',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='信件战报表';

-- 新 GM 信箱（message_model：gm，玩家反馈）
DROP TABLE IF EXISTS `zc_sanguo_gm`;
CREATE TABLE `zc_sanguo_gm` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '信件id',
    `fromuid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '发送者uid',
    `fromname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '发送者名称',
    `title` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '用户vip等级',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '信件类型（6 GM信件）',
    `touid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '收件人',
    `toname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '收件人名称',
    `message` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '信件内容',
    `isread` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '信件读取状态（0未读，1已读）',
    `status` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态（0提交，1回复，2发信方删除，3收信方删除,4忽略不回复）',
    `reply` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'GM回复内容',
    `reply_uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'GM回复者uid',
    `reply_dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'GM回复时间',
    `channel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '发件人的渠道',
    `grade` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '对GM的评分',
    `grade_dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家评分时间',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '发信时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_fromuid` (`fromuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GM反馈信件表';

-- 日常任务记录（task_model：task_log；uid 唯一，旧版的 number 列读出后即被 member_activity.task_number 覆盖，不建）
DROP TABLE IF EXISTS `zc_sanguo_task_log`;
CREATE TABLE `zc_sanguo_task_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `taskid1` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务1id',
    `taskid2` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务2id',
    `taskid3` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务3id',
    `taskstatus1` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务1状态（0未领取任务，1进行中，2已完成）',
    `taskstatus2` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务2状态（同上）',
    `taskstatus3` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务3状态（同上）',
    `free_times` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '免费刷新次数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后完成任务时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='日常任务记录表';

-- 主线任务用户信息（mainquest_model：main_quest_log）
DROP TABLE IF EXISTS `zc_sanguo_main_quest_log`;
CREATE TABLE `zc_sanguo_main_quest_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `task_id` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务id',
    `task_type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务类型',
    `task_value` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务目标参数',
    `task_plan` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务当前目标',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务当前状态（0进行中，1可领取，2已终结）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务进行时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='主线任务用户信息表';

-- 主线任务奖励领取记录（mainquest_model：main_quest_reward；(uid, task_id) 唯一，防并发重复领奖）
DROP TABLE IF EXISTS `zc_sanguo_main_quest_reward`;
CREATE TABLE `zc_sanguo_main_quest_reward` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `task_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务id',
    `reward_type` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务的奖励类型',
    `reward_value` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务的奖励参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取奖励时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_task` (`uid`, `task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='主线任务奖励领取记录表';

-- 元宝流水（member_model _gold_log；Model\FlowLog）
DROP TABLE IF EXISTS `zc_sanguo_gold_log`;
CREATE TABLE `zc_sanguo_gold_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家uid',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '记录类型1：获得 2：花费',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作元宝数',
    `balance` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '元宝余额',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `argument` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `vip` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='元宝消费记录表';

-- 银币流水（member_model _silver_log；Model\FlowLog）
DROP TABLE IF EXISTS `zc_sanguo_silver_log`;
CREATE TABLE `zc_sanguo_silver_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家uid',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '记录类型1：获得 2：花费',
    `silver` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作银币数',
    `balance` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '银币余额',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `argument` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `vip` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='银币消费记录表';

-- 军令流水（member_model _act_log；Model\FlowLog）
DROP TABLE IF EXISTS `zc_sanguo_act_log`;
CREATE TABLE `zc_sanguo_act_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家uid',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '记录类型1：获得 2：消耗',
    `act` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作军令数',
    `balance` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余军令个数',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '执行方法',
    `argument` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `vip` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='军令消费记录表';

-- 声望流水（member_model _reputation_log；Model\FlowLog）
DROP TABLE IF EXISTS `zc_sanguo_reputation_log`;
CREATE TABLE `zc_sanguo_reputation_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家uid',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '记录类型1：获得 2：花费',
    `reputation` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作声望数',
    `balance` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余声望',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `argument` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `vip` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='声望消耗记录表';

-- 付费点统计（stat_model _update_stat_pay；Model\StatPay；name 唯一，原子累计）
DROP TABLE IF EXISTS `zc_sanguo_stat_pay`;
CREATE TABLE `zc_sanguo_stat_pay` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '付费点',
    `times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '次数',
    `gold` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '金币',
    `ymd` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '统计截止日期',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='付费点统计表';

-- 新信件提示（旧版 member.newpm / newmail）
ALTER TABLE `zc_sanguo_member`
    ADD COLUMN `newpm` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '新信件（1 有未读的气泡信件）' AFTER `rebate_firstpay`,
    ADD COLUMN `newmail` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '邮件提醒（1 新邮件，2 GM 已回复，3 两者都有）' AFTER `newpm`;

-- 日常任务今日剩余次数（旧版 member_activity.task_number）
ALTER TABLE `zc_sanguo_member_activity`
    ADD COLUMN `task_number` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '日常任务今日剩余次数' AFTER `vipwage_dateline`;
