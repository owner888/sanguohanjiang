-- 已有数据库升级：节日活动第三批——踏青寻宝（c=act_treasure，含每日汉字兑换元宝与结束后补发）、我爱世界杯（c=worldcup，含充值记账
-- 与结束后补发排行奖励）、蹴鞠大赛（c=act_kemari，含每日刷新射门次数、结束后兑奖券兑换银币与补发排行奖励）、七星灯
-- （c=act_sevenstar，含结束后魂魄兑换紫宝石与补发排行奖励）、貔貅聚财（c=act_enrichment，含充值记账与结束后补发）、招募战友领工资
-- （c=act_paychecks，含充值记账、招募统计与结束后发工资）、老兵召回（c=act_veteran 与武将继承的免费至尊继承）、充值送豪礼
-- （c=act_thirty，含充值记账、每日补发与结束后魂魄兑换）、绝版神将（c=general_only）、限时打折神将（c=general_onsale）、限量神将
-- （c=limitedshop 与 uc limitedshop）
-- （旧版 s1 controllers/act_treasure.php、worldcup.php、act_kemari.php、lc/act_sevenstar.php、act_enrichment.php、act_paychecks.php、
-- act_veteran.php、act_thirty.php、general_only.php、general_onsale.php、limitedshop.php、general_inherit.php、member.php、pay.php、
-- cron_acttask.php、cron_kemari.php 与对应 models；uc controllers/limitedshop.php）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。须在 20261104_fest2.sql 之后执行（文件名按名称排在它之后）。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/sql/20261104_fest3.sql
--
-- 升级前：这些活动在新版里没有实现（没有 Handler、没有表；活动表 ActivityTime 中均已结束或从未开放）；member_field 没有 treasure_free。
-- 升级后：新建下列旧版表（日志 / 领取记录表按并发需要加唯一键，见各表注释）；member_field 加 treasure_free（寻宝免费次数，
--         每日首次登录重置为 1，同旧版默认 1）；新建进程内 uc 的限量神将商品表与售出记录表（旧版 uc limitedshop_model 与表不在
--         旧版代码包和数据里，按控制器与客户端字段还原，没有商品数据）。
--
-- 数据处理：没有要转换的数据（前提：这些活动在新版里没有任何表数据）。各表先 DROP 再建（会清空本批新表的数据）。

ALTER TABLE `zc_sanguo_member_field`
    ADD COLUMN `treasure_free` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '踏青寻宝免费次数（每日重置为 1）' AFTER `devicetoken`;

-- 踏青寻宝玩家记录（acttreasure_model：act_treasure；新版 uid + version 唯一）
DROP TABLE IF EXISTS `zc_sanguo_act_treasure`;
CREATE TABLE `zc_sanguo_act_treasure` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主键',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '角色名称',
    `vip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'VIP等级',
    `site` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前位置数',
    `num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '寻宝次数',
    `double` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '双倍奖励标识（0，没有；1，有）',
    `rank_reward` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '寻宝次数排行奖励领取状态（0，未领取；1，已领取）',
    `hzexch_reward` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '集字兑换状态（0，未达成；1，已达成）',
    `hzach_reward` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '集字成就状态（0，未达成；1，已达成）',
    `reward_str` TEXT NOT NULL COMMENT '刷新奖励信息json字符串',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '刷新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version` (`uid`, `version`),
    KEY `idx_version_num` (`version`, `num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='踏青寻宝表';

-- 踏青寻宝汉字兑换记录（act_treasure_exchange）
DROP TABLE IF EXISTS `zc_sanguo_act_treasure_exchange`;
CREATE TABLE `zc_sanguo_act_treasure_exchange` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主键ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `hid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '汉字奖励ID',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '兑换时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='踏青寻宝-汉字兑换表';

-- 踏青寻宝汉字收集（act_treasure_hanzi；新版 uid + version 唯一）
DROP TABLE IF EXISTS `zc_sanguo_act_treasure_hanzi`;
CREATE TABLE `zc_sanguo_act_treasure_hanzi` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主键ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `type_str` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '汉字字符串:id,num',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获得时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version` (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='踏青寻宝-汉字收集表';

-- 踏青寻宝奖励日志（act_treasure_log）
DROP TABLE IF EXISTS `zc_sanguo_act_treasure_log`;
CREATE TABLE `zc_sanguo_act_treasure_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主键',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `site` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前位置',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励类型',
    `num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励数量',
    `max_num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '特殊数量（汉字数量）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '记录时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid_version` (`uid`, `version`),
    KEY `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='踏青寻宝奖励日志表';

-- 集字成就领取记录（act_treasure_hanziach_log；新版 uid + version + aid 唯一）
DROP TABLE IF EXISTS `zc_sanguo_act_treasure_hanziach_log`;
CREATE TABLE `zc_sanguo_act_treasure_hanziach_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `aid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '集字成就ID',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取成就奖励时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version_aid` (`uid`, `version`, `aid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='集字成就领取奖励日志表';

-- 我爱世界杯玩家记录（worldcup_model：worldcup_log；新版 version + uid 唯一）
DROP TABLE IF EXISTS `zc_sanguo_worldcup_log`;
CREATE TABLE `zc_sanguo_worldcup_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `name` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '用户昵称',
    `own_chip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '碎片数量',
    `pay_chip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值碎片',
    `end_chip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动结束后领取的充值碎片',
    `get_rank` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排行奖励状态',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_version_uid` (`version`, `uid`),
    KEY `idx_version_chip` (`version`, `own_chip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='我爱世界杯用户信息记录表';

-- 我爱世界杯充值记录（worldcup_model->add_beatengod_pay_order：worldcup_order，money 为人民币金额）
DROP TABLE IF EXISTS `zc_sanguo_worldcup_order`;
CREATE TABLE `zc_sanguo_worldcup_order` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `money` FLOAT NOT NULL DEFAULT 0 COMMENT '充值金额',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `orderid` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '订单id',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_version_uid` (`version`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='我爱世界杯充值记录表';

-- 我爱世界杯宝箱领取记录（worldcup_reward_log；新版 uid + version + rewardid 唯一）
DROP TABLE IF EXISTS `zc_sanguo_worldcup_reward_log`;
CREATE TABLE `zc_sanguo_worldcup_reward_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `rewardid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励ID',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version_reward` (`uid`, `version`, `rewardid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='我爱世界杯宝箱奖励领取记录表';

-- 我爱世界杯参加人次（worldcup_action_log）
DROP TABLE IF EXISTS `zc_sanguo_worldcup_action_log`;
CREATE TABLE `zc_sanguo_worldcup_action_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的用户uid',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1银球,2金球',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗金币',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '记录时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='我爱世界杯参加人次';

-- 蹴鞠大赛玩家记录（actkemari_model：actkemari；新版 uid + version 唯一）
DROP TABLE IF EXISTS `zc_sanguo_actkemari`;
CREATE TABLE `zc_sanguo_actkemari` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主键',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '角色名称',
    `vip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'VIP等级',
    `free_times1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '小球免费剩余次数',
    `free_times2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '大球免费剩余次数',
    `normal_time1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '小球下一次可操作时间',
    `normal_time2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '大球下一次可操作时间',
    `goal_in` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '进球次数',
    `total_num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '蹴鞠总次数',
    `rank_reward_status` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '蹴鞠排行奖励领取状态(0未领取 1已领取)',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `refresh_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '免费次数刷新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version` (`uid`, `version`),
    KEY `idx_version_goal` (`version`, `goal_in`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='蹴鞠大赛表';

-- 蹴鞠大赛射门日志（actkemari_log）
DROP TABLE IF EXISTS `zc_sanguo_actkemari_log`;
CREATE TABLE `zc_sanguo_actkemari_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主键',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '角色名称',
    `vip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'VIP等级',
    `s_type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属球 1小球 2大球',
    `is_goalin` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否进球奖励 0非进球奖励 1进球奖励',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励类型',
    `num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励数量或者武将ID',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '记录时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`),
    KEY `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='蹴鞠大赛奖励日志表';

-- 蹴鞠兑奖券兑换日志（actkemari_exchange_log）
DROP TABLE IF EXISTS `zc_sanguo_actkemari_exchange_log`;
CREATE TABLE `zc_sanguo_actkemari_exchange_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '角色名称',
    `vip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'VIP等级',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖品类型',
    `value` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖品数量',
    `value1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '装备精华数量',
    `lottery_num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗奖券',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '兑奖时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='兑换奖券日志表';

-- 七星灯玩家记录（actsevenstar_model：actsevenstar；新版 uid + version 唯一）
DROP TABLE IF EXISTS `zc_sanguo_actsevenstar`;
CREATE TABLE `zc_sanguo_actsevenstar` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '用户昵称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户等级',
    `light_site` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前灯的位置',
    `times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '次数',
    `freetime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '免费时间',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后操作时间',
    `is_get` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取排名奖励状态',
    `get_dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取奖励的时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version` (`uid`, `version`),
    KEY `idx_version_times` (`version`, `times`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='七星灯活动表';

-- 貔貅聚财充值记录（actenrichment_model->add_enrichment_pay_order：actenrichment_order，gold 为商品元宝）
DROP TABLE IF EXISTS `zc_sanguo_actenrichment_order`;
CREATE TABLE `zc_sanguo_actenrichment_order` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '角色昵称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `gold` FLOAT NOT NULL DEFAULT 0 COMMENT '充值元宝',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `orderid` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '订单id',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_version_uid` (`version`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='貔貅聚财活动充值log表';

-- 貔貅聚财领奖记录（actenrichment_getreward_log；旧版即 uid + version + day 唯一）
DROP TABLE IF EXISTS `zc_sanguo_actenrichment_getreward_log`;
CREATE TABLE `zc_sanguo_actenrichment_getreward_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `day` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取天数',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取的第几档的奖励',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version_day` (`uid`, `version`, `day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='貔貅聚财领取奖励log表';

-- 招募战友充值记录（actpaychecks_model->add_paychecks_pay_order：act_paychecks_order，gold 为商品元宝）
DROP TABLE IF EXISTS `zc_sanguo_act_paychecks_order`;
CREATE TABLE `zc_sanguo_act_paychecks_order` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '角色昵称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `gold` FLOAT NOT NULL DEFAULT 0 COMMENT '充值元宝',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `orderid` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '订单id',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_version_uid` (`version`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='战友招募充值log表';

-- 招募战友奖励领取记录（act_paychecks_reward_log；旧版即 uid + rewardid + version 主键，新版加自增 id 后改唯一键）
DROP TABLE IF EXISTS `zc_sanguo_act_paychecks_reward_log`;
CREATE TABLE `zc_sanguo_act_paychecks_reward_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `rewardid` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领奖时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_reward_version` (`uid`, `rewardid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='战友招募领取记录表';

-- 招募战友领工资记录（actpaychecks_model：act_paychecks_paycheck_log；旧版 dump 里没有此表，按写入字段建，新版 uid + version 唯一）
DROP TABLE IF EXISTS `zc_sanguo_act_paychecks_paycheck_log`;
CREATE TABLE `zc_sanguo_act_paychecks_paycheck_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `paychecks` FLOAT NOT NULL DEFAULT 0 COMMENT '工资（元宝）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '发放时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version` (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='战友招募领工资记录';

-- 招募战友统计（actpaychecks_model：act_paychecks，由计划任务 action_paychecks 每次重建；旧版 dump 里没有此表，按写入字段建）
DROP TABLE IF EXISTS `zc_sanguo_act_paychecks`;
CREATE TABLE `zc_sanguo_act_paychecks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '邀请者uid',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `invite_num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '达到条件的招募人数',
    `gold` FLOAT NOT NULL DEFAULT 0 COMMENT '战友充值元宝',
    `paychecks` FLOAT NOT NULL DEFAULT 0 COMMENT '工资（元宝）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '统计时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version` (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='战友招募信息表';

-- 老兵召回领奖记录（actveteran_model：act_veteran_log；新版 uid + version 唯一）
DROP TABLE IF EXISTS `zc_sanguo_act_veteran_log`;
CREATE TABLE `zc_sanguo_act_veteran_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本',
    `is_reward` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否领奖（0，未领取；1，已领取）',
    `is_inherit` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否使用至尊继承（0，未使用；1，已使用）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取奖励时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version` (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='老兵召回领取奖励日志表';

-- 充值送豪礼充值记录（actthirty_model->add_thirty_pay_order：thirty_order，gold 为人民币金额 × 10）
DROP TABLE IF EXISTS `zc_sanguo_thirty_order`;
CREATE TABLE `zc_sanguo_thirty_order` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `gold` FLOAT NOT NULL DEFAULT 0 COMMENT '充值元宝',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `orderid` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '订单id',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_version_uid` (`version`, `uid`, `dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='充值送豪礼充值log表';

-- 充值送豪礼领武将记录（thirty_chip_log；旧版即 uid + version 唯一）
DROP TABLE IF EXISTS `zc_sanguo_thirty_chip_log`;
CREATE TABLE `zc_sanguo_thirty_chip_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `day` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '天数',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `g_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version` (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户领取碎片奖励表';

-- 充值送豪礼每日领奖记录（thirty_log；旧版即 uid + day + version 唯一）
DROP TABLE IF EXISTS `zc_sanguo_thirty_log`;
CREATE TABLE `zc_sanguo_thirty_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `day` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '天数',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `type1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励类型1',
    `value1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励数量1',
    `num1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励数量1',
    `type2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励类型2',
    `value2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励数量2',
    `num2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励数量2',
    `type3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励类型3',
    `value3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励数量3',
    `num3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励数量3',
    `type4` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励类型4',
    `value4` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励数量4',
    `num4` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励数量4',
    `type5` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励类型5',
    `value5` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励数量5',
    `num5` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励数量5',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_day_version` (`uid`, `day`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='充值送豪礼领取奖励日志表';

-- 充值送豪礼玩家武将魂魄（thirty；旧版即 uid + version 唯一）
DROP TABLE IF EXISTS `zc_sanguo_thirty`;
CREATE TABLE `zc_sanguo_thirty` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `own_chip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获取的武将碎片',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version` (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='充值送豪礼用户表';

-- 进程内 uc：限量神将商品（uc limitedshop_model；旧版 model 与表不在旧版代码包和数据里，按 s1 limitedshop 控制器与客户端
-- limitedgodpop.js 读的字段还原：cost / reputation 原价，now / now_reputation 现价，num 总量，sale 已售；全服共用，不按 serverid 分区）
DROP TABLE IF EXISTS `zc_sanguo_uc_limitedshop`;
CREATE TABLE `zc_sanguo_uc_limitedshop` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '商品id',
    `generalid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将模板id',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT '支付宝石类型（1蓝 2黄 3紫）',
    `cost` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '原价（宝石）',
    `reputation` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '原价（声望）',
    `now` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '现价（宝石）',
    `now_reputation` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '现价（声望）',
    `num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '限量总数',
    `sale` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已售数量',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='uc 限量神将商品';

-- 进程内 uc：限量神将售出记录（uc limitedshop_model->add_sale_log；每个玩家每件商品一条，uid + serverid + pid 唯一）
DROP TABLE IF EXISTS `zc_sanguo_uc_limitedshop_sale_log`;
CREATE TABLE `zc_sanguo_uc_limitedshop_sale_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '购买者uid',
    `serverid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '购买者服务器id',
    `pid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '商品id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '购买时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_server_pid` (`uid`, `serverid`, `pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='uc 限量神将售出记录';
