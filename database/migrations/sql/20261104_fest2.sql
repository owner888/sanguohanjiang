-- 已有数据库升级：节日活动第二批——七夕（c=act_tanabata，含充值记账与结束后补发）、国家庆典（c=act_celebration，含充值记账、
-- 退出国家自动领奖与结束后补发）、中秋送月饼（c=act_moon，含充值送月饼与结束后兑换银币；member_activity 补中秋吃月饼字段）、
-- 国庆红包（c=act_national_day，含元宝消耗记账与结束后补发）、万圣节（c=act_halloween，含结束后奖章兑换）、圣诞团购
-- （c=christmasbuy，含结束后补发返利）、圣诞礼包（c=christmasgift）、周年庆（c=anniversary）、清明祭祖（c=chingming，含结束后补发）
-- （旧版 s1 controllers/act_tanabata.php、act_celebration.php、act_moon.php、act_national_day.php、act_halloween.php、christmasbuy.php、
-- christmasgift.php、anniversary.php、chingming.php、cron_acttask.php、cron.php、pay.php:pay_act_init、country.php、member.php 与对应 models）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。须在 20261104_fest1.sql 之后执行（文件名按名称排在它之后）。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/sql/20261104_fest2.sql
--
-- 升级前：这些活动在新版里没有实现（万圣节为自定玩法，状态只在 Redis halloween_data:{uid}:{日期}，没有表；其余没有 Handler、
--         没有表；活动表 ActivityTime 中均已结束或从未开放）；member_activity 没有 freemooncake / mooncaketimes。
-- 升级后：新建下列旧版表（日志 / 领取记录表按并发需要加唯一键，见各表注释）；member_activity 加 freemooncake（新建档 12，
--         每日计划任务重置为 12）与 mooncaketimes。圣诞礼包的全服限售计数存 Redis（christmasgift_buytimes_{ymd}_{id}），
--         圣诞团购的购物车存 Redis（car_key_{uid}），中秋计划任务的已处理玩家存 Redis（moon_cron_list{version}）。
--         不建：halloween_sendmessage_candy_log（只有聊天室发言送糖果写，聊天室未移植）、christmassign_log（圣诞签到客户端没有界面，
--         控制器不移植）、mooncake_log（中秋吃月饼客户端没有界面，控制器不移植）。
--
-- 数据处理：没有要转换的数据（前提：这些活动在新版里没有任何表数据；万圣节旧的 Redis 测试数据 halloween_data:* 直接丢弃，
-- 可执行 redis-cli --scan --pattern 'hj:halloween_data:*' | xargs redis-cli del）。各表先 DROP 再建（会清空本批新表的数据）。

ALTER TABLE `zc_sanguo_member_activity`
    ADD COLUMN `freemooncake` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '中秋吃月饼免费次数（每日重置为 12）' AFTER `smashtime`,
    ADD COLUMN `mooncaketimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '中秋吃月饼购买次数' AFTER `freemooncake`;

-- 周年庆每天的领奖记录（anniversary_model：anniversary_log；旧版 uid + day 普通索引，新版 uid + version + day 唯一防并发重复领取）
DROP TABLE IF EXISTS `zc_sanguo_anniversary_log`;
CREATE TABLE `zc_sanguo_anniversary_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `day` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取的第几天的奖励',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动版本号',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version_day` (`uid`, `version`, `day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='周年庆活动奖励领取时间记录表';

-- 周年庆参加人次（anniversary_model->add_action_log：anniversary_action_log）
DROP TABLE IF EXISTS `zc_sanguo_anniversary_action_log`;
CREATE TABLE `zc_sanguo_anniversary_action_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的用户uid',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗金币',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='周年庆活动记录';

-- 国庆节领红包记录（national_day_model：national_day_log；新版 uid + version + ymd 唯一，每人每天一条）
DROP TABLE IF EXISTS `zc_sanguo_national_day_log`;
CREATE TABLE `zc_sanguo_national_day_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `ymd` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取的ymd',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '元宝数（手动领取为红包元宝，计划任务为当天消耗元宝）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version_ymd` (`uid`, `version`, `ymd`),
    KEY `idx_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国庆节领红包记录';

-- 国庆节活动期间的元宝消耗（member_model->_gold_log：national_day_gold_log，gold_log 的同一行加 version）
DROP TABLE IF EXISTS `zc_sanguo_national_day_gold_log`;
CREATE TABLE `zc_sanguo_national_day_gold_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '类型',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '元宝数',
    `balance` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前元宝数',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `argument` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `vip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip等级',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`),
    KEY `idx_version_dateline` (`version`, `dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国庆节消费元宝记录';

-- 圣诞礼包购买记录（christmasgift_model：christmasgift_log）
DROP TABLE IF EXISTS `zc_sanguo_christmasgift_log`;
CREATE TABLE `zc_sanguo_christmasgift_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `itemid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '购买的商品id',
    `day` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取的第几天的奖励',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动版本号',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid_version_dateline` (`uid`, `version`, `dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='圣诞活动奖励领取时间记录表';

-- 圣诞礼包参加人次（christmasgift_model->add_action_log：christmasgift_action_log）
DROP TABLE IF EXISTS `zc_sanguo_christmasgift_action_log`;
CREATE TABLE `zc_sanguo_christmasgift_action_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家角色id',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '金币数',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '礼包类型（礼包id）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参与活动时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='圣诞礼包活动记录';

-- 七夕充值记录（acttanabata_model->add_tanabata_pay_order：act_tanabata_order；gold 旧版 float，记的是整数元宝）
DROP TABLE IF EXISTS `zc_sanguo_act_tanabata_order`;
CREATE TABLE `zc_sanguo_act_tanabata_order` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '角色昵称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值元宝',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `orderid` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '订单id',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法（新版记渠道标识）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_version_uid` (`version`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='七夕充值log表';

-- 七夕充值档次奖励领取记录（act_tanabata_reward_log；新版 uid + version + re_id 唯一防重复领取）
DROP TABLE IF EXISTS `zc_sanguo_act_tanabata_reward_log`;
CREATE TABLE `zc_sanguo_act_tanabata_reward_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `re_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励ID',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version_re` (`uid`, `version`, `re_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='七夕活动领取奖励日志表';

-- 七夕排行奖励领取记录（act_tanabata_rankreward_log；新版 uid + version 唯一）
DROP TABLE IF EXISTS `zc_sanguo_act_tanabata_rankreward_log`;
CREATE TABLE `zc_sanguo_act_tanabata_rankreward_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `rank` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排行',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version` (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='七夕活动排行奖励领取日志';

-- 国家庆典充值记录（actcelebration_model->add_celebration_pay_order：act_celebration_order；gold 旧版 float）
DROP TABLE IF EXISTS `zc_sanguo_act_celebration_order`;
CREATE TABLE `zc_sanguo_act_celebration_order` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '角色昵称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值元宝',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `orderid` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '订单id',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法（新版记渠道标识）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_version_uid` (`version`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家庆典活动充值log表';

-- 国家庆典身份奖励领取记录（act_celebration_memberreward_log；新版 uid + version + member_id 唯一）
DROP TABLE IF EXISTS `zc_sanguo_act_celebration_memberreward_log`;
CREATE TABLE `zc_sanguo_act_celebration_memberreward_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `member_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '身份奖励id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version_member` (`uid`, `version`, `member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家庆典活动奖励领取身份奖log';

-- 国家庆典国家充值档次奖励发放记录（act_celebration_reward_log；旧版即 version + reward_id + country_id 唯一）
DROP TABLE IF EXISTS `zc_sanguo_act_celebration_reward_log`;
CREATE TABLE `zc_sanguo_act_celebration_reward_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `reward_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励ID',
    `country_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_version_reward_country` (`version`, `reward_id`, `country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家庆典活动领取奖励日志表';

-- 国家庆典个人领到的国家奖励（act_celebration_reward_own_log；旧版即 version + reward_id + country_id + uid 唯一）
DROP TABLE IF EXISTS `zc_sanguo_act_celebration_reward_own_log`;
CREATE TABLE `zc_sanguo_act_celebration_reward_own_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `reward_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励ID',
    `country_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_version_reward_country_uid` (`version`, `reward_id`, `country_id`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='个人领取到国家奖励日志';

-- 清明祭祖玩家记录（chingming_model：chingming_log；旧版主键 uid + version，新版加自增 id、uid + version 唯一）
DROP TABLE IF EXISTS `zc_sanguo_chingming_log`;
CREATE TABLE `zc_sanguo_chingming_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动版本号',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '用户名',
    `vip` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'VIP等级（排行要求 2 以上）',
    `times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '祭祖次数',
    `free_times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '盛大祭祖次数',
    `get_rank` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已领取的排行名次（0 未领）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后祭祖时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version` (`uid`, `version`),
    KEY `idx_version_vip` (`version`, `vip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='清明节 祭祖活动记录表';

-- 祭祖成就领取记录（chingming_achievement_log；新版 uid + version + rewardid 唯一）
DROP TABLE IF EXISTS `zc_sanguo_chingming_achievement_log`;
CREATE TABLE `zc_sanguo_chingming_achievement_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动版本号',
    `rewardid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成就id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version_reward` (`uid`, `version`, `rewardid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='祭祖活动成就领取记录表';

-- 祭祖参加人次（chingming_model->add_action_log：chingming_action_log）
DROP TABLE IF EXISTS `zc_sanguo_chingming_action_log`;
CREATE TABLE `zc_sanguo_chingming_action_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的用户uid',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗金币',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='祭祖活动记录';

-- 圣诞团购购买日志（christmasbuy_model->add_buy_log：christmasbuy_log）
DROP TABLE IF EXISTS `zc_sanguo_christmasbuy_log`;
CREATE TABLE `zc_sanguo_christmasbuy_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `goods_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '商品id',
    `goods_num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '商品数量',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid_version_dateline` (`uid`, `version`, `dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户购买日志表';

-- 圣诞团购消费记录（christmasbuy_model->add_pay_log：christmasbuy_info，每次结算一条）
DROP TABLE IF EXISTS `zc_sanguo_christmasbuy_info`;
CREATE TABLE `zc_sanguo_christmasbuy_info` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户消耗的元宝数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消费时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid_version` (`uid`, `version`),
    KEY `idx_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户团购信息表';

-- 圣诞团购返利礼包领取记录（christmasbuy_gift_log；旧版主键 id + achieve_id，新版 uid + version + achieve_id 唯一）
DROP TABLE IF EXISTS `zc_sanguo_christmasbuy_gift_log`;
CREATE TABLE `zc_sanguo_christmasbuy_gift_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'UID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `achieve_id` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '礼包成就id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '兑奖时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version_achieve` (`uid`, `version`, `achieve_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='圣诞团购礼包领取日志表';

-- 万圣节玩家南瓜状态（acthalloween_model：halloween；旧版即 uid + version 唯一）
DROP TABLE IF EXISTS `zc_sanguo_halloween`;
CREATE TABLE `zc_sanguo_halloween` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前砸南瓜轮数',
    `id_1` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第1个南瓜状态 1已开启 0未开启',
    `id_2` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第2个南瓜状态 1已开启 0未开启',
    `id_3` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第3个南瓜状态 1已开启 0未开启',
    `id_4` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第4个南瓜状态 1已开启 0未开启',
    `id_5` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第5个南瓜状态 1已开启 0未开启',
    `id_6` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第6个南瓜状态 1已开启 0未开启',
    `id_7` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第7个南瓜状态 1已开启 0未开启',
    `id_8` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第8个南瓜状态 1已开启 0未开启',
    `id_9` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第9个南瓜状态 1已开启 0未开启',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version` (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='万圣节用户表';

-- 万圣节砸南瓜记录（halloween_log）
DROP TABLE IF EXISTS `zc_sanguo_halloween_log`;
CREATE TABLE `zc_sanguo_halloween_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前砸南瓜轮数',
    `ids` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '南瓜id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='万圣节砸南瓜log表';

-- 万圣节砸南瓜获得的奖励（halloween_pumpkin_log）
DROP TABLE IF EXISTS `zc_sanguo_halloween_pumpkin_log`;
CREATE TABLE `zc_sanguo_halloween_pumpkin_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前砸南瓜轮数',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励类型',
    `value` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励数量',
    `ids` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '南瓜id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='万圣节砸南瓜获取奖励log表';

-- 万圣节吃糖果获得的奖励（halloween_candy_log）
DROP TABLE IF EXISTS `zc_sanguo_halloween_candy_log`;
CREATE TABLE `zc_sanguo_halloween_candy_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励类型',
    `value` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励数量',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='万圣节打开糖果获取奖励log表';

-- 万圣节活动结束后奖章自动兑换银币记录（acthalloween_model->add_user_medal：halloween_medal_log）
DROP TABLE IF EXISTS `zc_sanguo_halloween_medal_log`;
CREATE TABLE `zc_sanguo_halloween_medal_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `medal` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '兑换奖章个数',
    `silver` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '兑换银币数量',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='万圣节兑换奖章log表';

-- 万圣节奖章商城兑换记录（acthalloween_model->add_medal_log：halloween_medal2_log；旧版 dump 里没有此表，按写入字段建）
DROP TABLE IF EXISTS `zc_sanguo_halloween_medal2_log`;
CREATE TABLE `zc_sanguo_halloween_medal2_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `reward_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖章商城id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '兑换时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='万圣节奖章兑换记录';

-- 中秋送月饼玩家记录（moon_model：act_moon；旧版即 uid + version 唯一，新版加自增 id）
DROP TABLE IF EXISTS `zc_sanguo_act_moon`;
CREATE TABLE `zc_sanguo_act_moon` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `mine_moon` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户自己的月饼数',
    `version` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动版本号',
    `love` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '爱心',
    `friend_moon` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '朋友赠送的月饼',
    `firsttime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首次获得资源的时间，计算排行',
    `nickname` VARCHAR(250) NOT NULL DEFAULT '' COMMENT '用户昵称',
    `buynum` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户已经购买的数量',
    `sysdate` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '发送系统月饼',
    `friend_all` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所有朋友送的',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version` (`uid`, `version`),
    KEY `idx_version_love` (`version`, `love`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='中秋节活动表';

-- 中秋爱心成就领取记录（act_moon_ach_log；新版 uid + version + achid 唯一）
DROP TABLE IF EXISTS `zc_sanguo_act_moon_ach_log`;
CREATE TABLE `zc_sanguo_act_moon_ach_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `version` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `achid` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成就id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version_ach` (`uid`, `version`, `achid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='中秋爱心成就领取记录';

-- 中秋送月饼记录（act_moon_givefriend_log：type 1 系统赠送，2 好友赠送）
DROP TABLE IF EXISTS `zc_sanguo_act_moon_givefriend_log`;
CREATE TABLE `zc_sanguo_act_moon_givefriend_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `version` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `num` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '赠送数量',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1系统赠送2好友赠送',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid（系统赠送为 0）',
    `frienduid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '朋友uid',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '赠送时间',
    `nickname` VARCHAR(80) NOT NULL DEFAULT '' COMMENT '玩家昵称',
    `friendname` VARCHAR(80) NOT NULL DEFAULT '' COMMENT '朋友昵称',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_friend_version` (`frienduid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='中秋送月饼记录';

-- 中秋吃 / 送月饼获得的奖励（act_moon_giveeat_log：type 1 吃，2 送）
DROP TABLE IF EXISTS `zc_sanguo_act_moon_giveeat_log`;
CREATE TABLE `zc_sanguo_act_moon_giveeat_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `version` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `type` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '类型（1 吃获得，2 送获得）',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `rewardtype` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励类型',
    `rewardid` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励id或者数量',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领奖时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='中秋吃送月饼奖励记录';

-- 中秋月饼增减记录（moon_model->add_moon_log：act_moon_log；type 1 获得 2 消耗，moontype 1 自己的月饼 2 小月饼）
DROP TABLE IF EXISTS `zc_sanguo_act_moon_log`;
CREATE TABLE `zc_sanguo_act_moon_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `version` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1获得2消耗',
    `num` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '数量',
    `balance` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '余额',
    `class` VARCHAR(80) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(80) NOT NULL DEFAULT '' COMMENT '执行方法',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '执行时间',
    `moontype` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '月饼类型（1 充值或者购买，2 系统或好友赠送）',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='中秋月饼记录';

-- 中秋充值订单（moon_model->add_pay_moon：act_moon_order；money 为充值元宝 / 10，旧版 float、MyISAM）
DROP TABLE IF EXISTS `zc_sanguo_act_moon_order`;
CREATE TABLE `zc_sanguo_act_moon_order` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `money` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值金额',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '版本号',
    `orderid` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '订单id',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法（新版记渠道标识）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值时间',
    `nickname` VARCHAR(80) NOT NULL DEFAULT '' COMMENT '玩家昵称',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid_version` (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='中秋节活动充值订单';
