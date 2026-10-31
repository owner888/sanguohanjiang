-- 已有数据库升级：节日活动第一批（春节系列）——春节送福任务计数（act_newyear）、春节年兽（c=newyearboss，含活动结束后的
-- 排行补发）、新春签到（c=newyeardailysign）、春节充值（c=newyearpay，含充值记账）、春节转盘（c=springlottery）、
-- 春节武将商城（c=springshop）
-- （旧版 s1 controllers/newyearboss.php、newyeardailysign.php、newyearpay.php、springlottery.php、springshop.php、cron.php 的
-- get_newyearboss_reward、member.php 与 models/actnewyear_model.php、newyearboss_model.php、dailysign_model.php、
-- christmas_model.php、springlottery_model.php、springshop_model.php、tavern_model.php 的 add_action_log）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。须在 20261104_cross2.sql 之后执行（文件名按名称排在它之后）。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/sql/20261104_fest1.sql
--
-- 升级前：这些春节活动在新版里没有实现（没有 Handler、没有表；活动表 ActivityTime 中均已结束或从未开放）。
-- 升级后：新建旧版 act_newyear_info、act_newyear_task_log、newyear_boss_log、newyear_boss_reward_log、treasurebox_log、
--         pay_reward_order、pay_reward_log、spring_lottery_log、spring_costgold、spring_lottery_ranklog、spring_lottery_info_log、
--         springlottery_action_log、springshop_log、tavern_action_log。年兽每天的状态按旧版存 Redis（newyearboss_*），
--         商城的全服限售计数存 Redis（spring_buytimes_*）。
--         不建：act_newyear_flop_log（只有不暴露的翻牌接口写）、spring_extend（按规律计算，见 SpringlotteryHandler）、
--         member_field.newyeardinner（年夜饭客户端没有界面，未移植）。
--
-- 数据处理：没有要转换的数据（前提：这些活动在新版里没有任何状态）。各表先 DROP 再建（会清空本批新表的数据）。

-- 春节送福玩家信息（actnewyear_model：act_newyear_info；旧版 uid + version 普通索引，新版唯一，并发建档只插一行）
DROP TABLE IF EXISTS `zc_sanguo_act_newyear_info`;
CREATE TABLE `zc_sanguo_act_newyear_info` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动版本号',
    `num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前翻牌次数',
    `buy_num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前已购买翻牌次数',
    `fortune` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前福气值',
    `refresh_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务刷新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version` (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='春节送福信息表';

-- 春节送福每天的任务（actnewyear_model：act_newyear_task_log）
DROP TABLE IF EXISTS `zc_sanguo_act_newyear_task_log`;
CREATE TABLE `zc_sanguo_act_newyear_task_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色uid',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动版本号',
    `ymd` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务日期',
    `tid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务ID',
    `have_num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务完成的次数',
    `is_reward` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否领取奖励（0，未领取；1，已领取）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取奖励时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid_version` (`uid`, `version`),
    KEY `idx_ymd` (`ymd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='春节送福任务日志表';

-- 春节年兽玩家活动累计（newyearboss_model：newyear_boss_log；旧版无索引，新版 version + uid 唯一，并发建档只插一行）
DROP TABLE IF EXISTS `zc_sanguo_newyear_boss_log`;
CREATE TABLE `zc_sanguo_newyear_boss_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '用户名',
    `hit` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '累计伤害',
    `dodge` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '闪避次数',
    `powerup` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '金币鼓舞次数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后更新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_version_uid` (`version`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户春节年兽数据表';

-- 春节年兽奖励领取记录（newyearboss_model：newyear_boss_reward_log；type 1 每日成就、2 排行，tab 为排行 / 成就类型）
DROP TABLE IF EXISTS `zc_sanguo_newyear_boss_reward_log`;
CREATE TABLE `zc_sanguo_newyear_boss_reward_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1.每日成就奖励，2.排行奖励',
    `tab` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排行 / 成就类型（1伤害，2鼓舞，3闪避，4排名）',
    `value` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取的成就id/排名',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid_version` (`uid`, `version`),
    KEY `idx_version_type_tab` (`version`, `type`, `tab`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='奖励领取记录表';

-- 新春签到宝箱领取记录（dailysign_model：treasurebox_log）
DROP TABLE IF EXISTS `zc_sanguo_treasurebox_log`;
CREATE TABLE `zc_sanguo_treasurebox_log` (
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `version` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动版本号',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='宝箱奖励领取记录';

-- 圣诞 / 春节活动期间的充值记录（christmas_model->add_christmas_log：pay_reward_order；money 为人民币元，旧版 float）
DROP TABLE IF EXISTS `zc_sanguo_pay_reward_order`;
CREATE TABLE `zc_sanguo_pay_reward_order` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `level` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值时等级',
    `money` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值金额',
    `version` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动版本号（春节充值为活动版本 + 1000）',
    `orderid` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '订单id',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法（新版记渠道标识）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid_version` (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='圣诞充值活动奖励';

-- 圣诞 / 春节充值奖励领取记录（christmas_model：pay_reward_log；旧版无索引，新版 uid + version + rewardid 唯一防并发重复领取）
DROP TABLE IF EXISTS `zc_sanguo_pay_reward_log`;
CREATE TABLE `zc_sanguo_pay_reward_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `rewardid` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取的奖励id',
    `version` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励的版本号',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取奖励时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version_reward` (`uid`, `version`, `rewardid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='圣诞充值活动奖励领取表';

-- 春节转盘玩家记录（springlottery_model：spring_lottery_log）
DROP TABLE IF EXISTS `zc_sanguo_spring_lottery_log`;
CREATE TABLE `zc_sanguo_spring_lottery_log` (
    `uid` INT UNSIGNED NOT NULL COMMENT '用户id',
    `nickname` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '用户昵称',
    `lavenumber` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '免费抽奖的次数',
    `buy_lavenumber` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '抽奖的次数',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `lastdateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后购买的时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='抽奖log表';

-- 春节转盘下次所需元宝（springlottery_model：spring_costgold；旧版 uid 普通索引，新版唯一）
DROP TABLE IF EXISTS `zc_sanguo_spring_costgold`;
CREATE TABLE `zc_sanguo_spring_costgold` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '抽奖所需要的金币数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='抽奖所需金币记录数';

-- 春节转盘每天的抽奖次数排行（springlottery_model：spring_lottery_ranklog；新版 uid + version + day 唯一，并发只插一行）
DROP TABLE IF EXISTS `zc_sanguo_spring_lottery_ranklog`;
CREATE TABLE `zc_sanguo_spring_lottery_ranklog` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `day` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '抽奖的天数',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家id',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '用户昵称',
    `vip` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家VIP',
    `times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '每天抽奖的次数',
    `version` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `get` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1：领取过奖励 0：未领取过奖励',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version_day` (`uid`, `version`, `day`),
    KEY `idx_version_day_times` (`version`, `day`, `times`),
    KEY `idx_vip` (`vip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='春节抽奖的排名表';

-- 春节转盘抽奖记录（springlottery_model：spring_lottery_info_log）
DROP TABLE IF EXISTS `zc_sanguo_spring_lottery_info_log`;
CREATE TABLE `zc_sanguo_spring_lottery_info_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '抽奖id',
    `uid` INT UNSIGNED NOT NULL COMMENT '用户id',
    `nickname` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '用户昵称',
    `lottery_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖品id',
    `group` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '对应转盘类型',
    `gettype` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖品类型',
    `getvalue` INT UNSIGNED NOT NULL DEFAULT 0,
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本',
    `getvalue1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参数2',
    `lastdateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后购买的时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户抽奖信息log表';

-- 春节转盘参加人次（springlottery_model->add_action_log：springlottery_action_log）
DROP TABLE IF EXISTS `zc_sanguo_springlottery_action_log`;
CREATE TABLE `zc_sanguo_springlottery_action_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL COMMENT '参加活动的用户uid',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗金币',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='春节转盘活动记录';

-- 春节商城购买记录（springshop_model->add_user：springshop_log）
DROP TABLE IF EXISTS `zc_sanguo_springshop_log`;
CREATE TABLE `zc_sanguo_springshop_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `giftid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '春节礼包ID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后购买时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid_version` (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='春节商城--用户购买记录log';

-- 武将限售活动人次（tavern_model->add_action_log：tavern_action_log；name 旧版 char(10) utf8_bin）
DROP TABLE IF EXISTS `zc_sanguo_tavern_action_log`;
CREATE TABLE `zc_sanguo_tavern_action_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL COMMENT '参加活动的用户uid',
    `name` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '' COMMENT '武将名字',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='武将限售活动记录';
