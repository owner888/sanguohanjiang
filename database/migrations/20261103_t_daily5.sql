-- 已有数据库升级：每日玩法第五批（最后一批）按旧版移植——幸运大转盘（c=lottery）与角色注册礼包（c=rolegift）、国家骰子（c=dice）、
-- 砸金蛋（c=hitegg）、威震天下（c=smash）、暴打名将（c=beatengod，含充值记账与活动结束后的自动发奖）、藏宝图（c=treasuremap，
-- 含征收 / 强征掉落与每日 7 点自动出售）、装备打造（c=equip_forge）、强化成就（c=strengthen）、注册前 3 日奖励（c=newrolereward）、
-- 新服大礼包（c=newgift）、评分奖励（c=clickreward）、试玩绑定奖励（c=regreward / c=register&m=binding）、老版主线礼包
-- （c=member&m=open_gift）、苹果推送 token（c=ios&m=savetoken）（旧版 controllers/lottery.php、rolegift.php、dice.php、hitegg.php、
-- smash.php、beatengod.php、treasuremap.php、newequip/equip_forge.php、strengthen.php、newrolereward.php、newgift.php、
-- clickreward.php、regreward.php、member.php、ios.php 与对应 models，cron.php 的 sale_map / get_beatengod_reward /
-- get_beatengod_box_reward / get_newgift_reward，member_model 的 daily_cron）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。须在 20261103_s_daily4.sql 之后执行（文件名带 t_ 使其按名称排在
-- 20261103_s_daily4.sql 之后：本文件在 member_field / member_activity 末尾加列，依赖前面迁移加的列）。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261103_t_daily5.sql
--
-- 升级前：这些玩法是新版自定的，状态存 Redis：转盘 user_lottery_times:{uid} / user_lottery_buy_times:{uid}、骰子
--         user_dice_info:{uid} / dice_times:{uid} / dice_last_claimed:{uid}、砸蛋 hitegg_times:{uid}、暴打名将
--         beatengod_times:{uid} / beatengod_box:{uid}、藏宝图 user_treasure_map:{uid} / user_treasure_part:{uid} /
--         treasure_team:{uid} / treasure_public_teams、打造 forge_iron:{uid}；奖品、价格与收益是自定的。老版主线礼包已领个数记在
--         新版自定的 member_field.gift。
-- 升级后：新建旧版 lottery_log、role_gift_log、dice_log、hit_egg、hit_egg_log、hitegg_action_log、beatengod_log、beatengod_order、
--         beatengod_reward_log、beatengod_action_log、own_treasuremap、newequip_log、revert_achieve_log、newrolereward_log、
--         newgift_log；member 增加 gift / guest；member_field 去掉 gift、增加 clickreward / devicetoken；member_activity 增加
--         mapreward / smashtime。藏宝图队伍与状态按旧版存 Redis（treasuremap_team_* / treasuremap_status_* / treasuremap_count_*）。
--
-- 数据处理（前提：这些玩法现有的状态都是测试数据，对不上旧版结构）：
-- 1. 上述 Redis 键是新版自定的数据，不转换、不保留；升级后不再读写，可自行删除。
-- 2. member_field.gift 的值复制到 member.gift 后删除该列；user.is_guest = 1 的玩家 member.guest 记 1（绑定后可领 50 元宝）。
-- 3. 已有玩家的转盘 / 砸蛋 / 新服礼包记录在第一次打开界面时按旧版建档。
-- 各表先 DROP 再建（会清空本批新表的数据）；末尾的 ALTER TABLE / UPDATE 只能执行一次。

-- 幸运大转盘抽奖次数（lottery_model：lottery_log；旧版无索引，新版 uid 唯一）
DROP TABLE IF EXISTS `zc_sanguo_lottery_log`;
CREATE TABLE `zc_sanguo_lottery_log` (
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `day` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '连续登录次数',
    `number` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '免费抽奖次数',
    `lavenumber` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余免费抽奖次数',
    `buy_lavenumber` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '购买的剩余次数',
    `lastdateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上次登录时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='抽奖连续登录记录表';

-- 角色注册天数礼包领取记录（rolegift_model：role_gift_log，主键同旧版 uid + id）
DROP TABLE IF EXISTS `zc_sanguo_role_gift_log`;
CREATE TABLE `zc_sanguo_role_gift_log` (
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '礼包id',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励类型（1 黄宝石，2 紫宝石）',
    `num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励数目',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色注册礼包领取记录';

-- 国家骰子记录（dice_model：dice_log，主键同旧版 uid + week）
DROP TABLE IF EXISTS `zc_sanguo_dice_log`;
CREATE TABLE `zc_sanguo_dice_log` (
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `week` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第几周',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '玩家名称',
    `points` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前点数',
    `times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余次数',
    `get_rank` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取奖励时排名',
    `dice1` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '第一个骰子点数',
    `dice2` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '第二个骰子点数',
    `dice3` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '第三个骰子点数',
    `reward1_status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第一个特别大奖领取状态',
    `reward2_status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第二个特别大奖领取状态',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后修改时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`, `week`),
    KEY `idx_week_points` (`week`, `points`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家骰子记录表';

-- 砸蛋时间记录（hitegg_model：hit_egg；旧版 uid 普通索引，新版唯一）
DROP TABLE IF EXISTS `zc_sanguo_hit_egg`;
CREATE TABLE `zc_sanguo_hit_egg` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主键ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家UID',
    `egg1_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '金蛋1开始时间',
    `egg2_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '金蛋2开始时间',
    `egg3_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '金蛋3开始时间',
    `egg1_f_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '金蛋1第一次开始时间',
    `egg2_f_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '金蛋2第一次开始时间',
    `egg3_f_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '金蛋3第一次开始时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='砸蛋时间纪录表';

-- 砸金蛋记录（hitegg_model->add_hit_egg_log：hit_egg_log）
DROP TABLE IF EXISTS `zc_sanguo_hit_egg_log`;
CREATE TABLE `zc_sanguo_hit_egg_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `eggid` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '金蛋id',
    `rewardid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '砸蛋时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='砸金蛋记录表';

-- 砸金蛋活动人次（hitegg_model->add_action_log：hitegg_action_log）
DROP TABLE IF EXISTS `zc_sanguo_hitegg_action_log`;
CREATE TABLE `zc_sanguo_hitegg_action_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的用户uid',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗金币',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='砸金蛋活动人次表';

-- 暴打名将用户记录（beatengod_model：beatengod_log；旧版无索引，新版 version + uid 唯一）
DROP TABLE IF EXISTS `zc_sanguo_beatengod_log`;
CREATE TABLE `zc_sanguo_beatengod_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动版本号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `name` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '用户昵称',
    `own_chip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '拥有碎片数',
    `pay_chip` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动充值领取碎片个数',
    `end_chip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动结束后领取的充值碎片',
    `get_rank` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取排行奖励时的排名',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后更新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_version_uid` (`version`, `uid`),
    KEY `idx_version_chip` (`version`, `own_chip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='暴打名将用户信息记录表';

-- 暴打名将期间充值记录（beatengod_model->add_beatengod_pay_order：beatengod_order）
DROP TABLE IF EXISTS `zc_sanguo_beatengod_order`;
CREATE TABLE `zc_sanguo_beatengod_order` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值时等级',
    `money` FLOAT NOT NULL DEFAULT 0 COMMENT '充值金额',
    `version` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动版本号',
    `orderid` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '订单id',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_version_uid` (`version`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='暴打名将充值记录表';

-- 暴打名将宝箱奖励领取记录（beatengod_model：beatengod_reward_log；旧版无索引，新版 uid + version + rewardid 唯一）
DROP TABLE IF EXISTS `zc_sanguo_beatengod_reward_log`;
CREATE TABLE `zc_sanguo_beatengod_reward_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动版本号',
    `rewardid` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取的奖励ID',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version_reward` (`uid`, `version`, `rewardid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='暴打名将宝箱奖励领取记录表';

-- 暴打名将活动记录（beatengod_model->add_action_log：beatengod_action_log）
DROP TABLE IF EXISTS `zc_sanguo_beatengod_action_log`;
CREATE TABLE `zc_sanguo_beatengod_action_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的用户uid',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1木槌,2金锤',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗金币',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '暴打记录时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='暴打活动记录';

-- 拥有的藏宝图（treasuremap_model：own_treasuremap）
DROP TABLE IF EXISTS `zc_sanguo_own_treasuremap`;
CREATE TABLE `zc_sanguo_own_treasuremap` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '昵称',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '部位(1上;2中;3下)',
    `quality` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '品级(白绿蓝金紫)',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获得时间',
    `isuse` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0：未使用，1：使用中',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`, `dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='拥有藏宝图表';

-- 装备锻造记录（equip_forge_model->add_newequip_log：newequip_log）
DROP TABLE IF EXISTS `zc_sanguo_newequip_log`;
CREATE TABLE `zc_sanguo_newequip_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `before_attr` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '锻造之前的装备初始属性',
    `after_attr` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '锻造之后的装备初始属性',
    `before_quality` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '锻造之前的装备品级',
    `after_quality` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '锻造之后的装备品级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0,
    `before_equipid` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '锻造前装备模板id',
    `after_equipid` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '锻造后的装备模板id',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否是装备升级',
    `ownid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '装备唯一id',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='装备锻造记录表';

-- 装备还原强化成就日志（equipment_model->revert_achieve_log：revert_achieve_log）
DROP TABLE IF EXISTS `zc_sanguo_revert_achieve_log`;
CREATE TABLE `zc_sanguo_revert_achieve_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家ID',
    `equipid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '装备ID',
    `aid` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '还原装备强化成就',
    `type` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '精华类型',
    `num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '精华数量',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='装备还原强化成就日志表';

-- 注册前 3 日奖励领取记录（newrolereward_model：newrolereward_log；旧版无索引，新版 uid + day 唯一）
DROP TABLE IF EXISTS `zc_sanguo_newrolereward_log`;
CREATE TABLE `zc_sanguo_newrolereward_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `day` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取的第几天的奖励',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取奖励时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_day` (`uid`, `day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='注册前3日奖励领取记录';

-- 新服大礼包记录（newgift_model：newgift_log；旧版 uid 普通索引，新版 uid + day 唯一）
DROP TABLE IF EXISTS `zc_sanguo_newgift_log`;
CREATE TABLE `zc_sanguo_newgift_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `day` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户第几天登录',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取奖励时间',
    `status` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态(1:可领取2:未达成3:已领取)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_day` (`uid`, `day`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='新服大礼包表';

-- 老版主线礼包已领个数（member.gift，原新版 member_field.gift 迁回旧版位置）、是否是试玩用户（member.guest）
ALTER TABLE `zc_sanguo_member`
    ADD COLUMN `gift` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已领取礼包个数' AFTER `jointime`,
    ADD COLUMN `guest` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否是试玩用户（1 试玩、未领绑定奖励，2 已领绑定奖励）' AFTER `gift`;

UPDATE `zc_sanguo_member` m JOIN `zc_sanguo_member_field` f ON f.`uid` = m.`uid` SET m.`gift` = LEAST(GREATEST(f.`gift`, 0), 255);
UPDATE `zc_sanguo_member` m JOIN `zc_sanguo_user` u ON u.`uid` = m.`uid` SET m.`guest` = 1 WHERE u.`is_guest` = 1;

ALTER TABLE `zc_sanguo_member_field`
    DROP COLUMN `gift`,
    ADD COLUMN `clickreward` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '评分奖励领取时间' AFTER `stone3_times`,
    ADD COLUMN `devicetoken` CHAR(70) NOT NULL DEFAULT '' COMMENT 'ios设备token' AFTER `clickreward`;

ALTER TABLE `zc_sanguo_member_activity`
    ADD COLUMN `mapreward` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '藏宝图奖励' AFTER `jadeturn`,
    ADD COLUMN `smashtime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上次威震天下使用时间' AFTER `mapreward`;
