-- 已有数据库升级：经济 / 交易类玩法按旧版移植——兵符商城与军令采买（c=shop）、国家商店（c=countryshop）、国家税金商城
-- （c=country_taxes_shop，含国家贡献累计税金）、国家祭祀（c=countrysacrifice）、天神祭祀（c=sacrifice）、玉石作坊（c=workshop）、
-- 银矿（c=mine）、每日通商（c=business，含都城效果 type 2 通商次数）、黑市（c=blackmarket）、赌石（c=gambling，含 VIP 每日
-- 免费切石次数）、神矿（c=jadegamble）、海外贸易（c=overseastrade）（旧版 controllers/shop.php、countryshop.php、
-- country_taxes_shop.php、countrysacrifice.php、sacrifice.php、workshop.php、mine.php、business.php、blackmarket.php、
-- gambling.php、jadegamble.php、overseastrade.php 与对应 models，member_country_taxes_model、daytime_sys_model、
-- send_reward_model，member_model 的 daily_cron / get_activity_info，vip_model->add_stone_times）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。须在 20261103_r_daily3.sql 之后执行（文件名带 s_ 使其按名称排在
-- 20261103_r_daily3.sql 之后：本文件在 member_field / member_activity 末尾加列，依赖前面迁移加的列）。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261103_s_daily4.sql
--
-- 升级前：这些玩法是新版自定的，状态存 Redis：商城购买记录 global_shop_logs、税金 user_taxes:{uid} / taxes_buy:{uid}:{id}:{ymd}、
--         国家祭祀 country_sacrifice_date:{uid}、作坊 user_workshop:{uid}、银矿 user_mine:{uid}、通商
--         user_business:{uid}、黑市 user_blackmarket:{uid}、赌石 user_gamble:{uid}、神矿 jadegamble_stone:{uid}、海外贸易
--         overseas_trade:{uid}；商品、价格与收益是自定的。
-- 升级后：新建旧版 member_country_taxes、country_taxes_shop_log、country_worship_log、sacrifice_action_log、workshop、mine、
--         own_stone、jade_gamble_log、jadegamble_action_log、blackmarket_log、blackmarket_buy_log、blackmarket_refresh、
--         blackmarket_action_log、business、business_log、overseas_trade_info、overseas_trade_team、overseas_trade_log、
--         overseas_trade_report、overseas_trade_rob、overseas_trade_buy_log；member_field 增加 actnumber / actdateline / onmine /
--         onworkshop / stone1_times / stone2_times / stone3_times；member_activity 增加 sacrifice1~3 / freebusiness /
--         businesstimes / jadetype / jadenum / jadeturn。
--
-- 数据处理（前提：这些玩法现有的状态都是测试数据，对不上旧版结构）：
-- 1. 上述 Redis 键是新版自定的数据，不转换、不保留；升级后不再读写，可自行删除。
-- 2. 已有玩家 member_activity.freebusiness 记 20（旧版建档值，每日计划任务也重置为 20）；赌石免费次数从下一次每日计划任务起按 VIP 发放。
-- 3. 已有玩家的国家税金在第一次打开税金商城时按旧版以总贡献 × 0.1 建档。
-- 各表先 DROP 再建（会清空本批新表的数据）；末尾的 ALTER TABLE / UPDATE 只能执行一次。

-- 国家税金（member_country_taxes_model：member_country_taxes；旧版 id 自增 + uid 普通索引，并发首次打开可能建出两行，新版 uid 唯一）
DROP TABLE IF EXISTS `zc_sanguo_member_country_taxes`;
CREATE TABLE `zc_sanguo_member_country_taxes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `country_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家id',
    `taxes` INT NOT NULL DEFAULT 0 COMMENT '税金',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`),
    KEY `idx_country_id` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='玩家国家税金表';

-- 国家税金流水（member_country_taxes_model->save_log：country_taxes_shop_log）
DROP TABLE IF EXISTS `zc_sanguo_country_taxes_shop_log`;
CREATE TABLE `zc_sanguo_country_taxes_shop_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `country_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家id',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作类型 （1、初始化；2、增加；3、消费；）',
    `taxes` INT NOT NULL DEFAULT 0 COMMENT '税金',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`),
    KEY `idx_country_id` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家税金商城日志表';

-- 国家祭祀记录（countrysacrifice_model：country_worship_log；祭祀配置在 MasterData CountryWorship）
DROP TABLE IF EXISTS `zc_sanguo_country_worship_log`;
CREATE TABLE `zc_sanguo_country_worship_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录ID',
    `country` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属国家id',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '用户名',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户祭祀类型',
    `get_value` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '祭祀获得贡献',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '祭祀时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_country` (`country`, `dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家祭祀记录表';

-- 天神祭祀活动记录（sacrifice_model->add_action_log：sacrifice_action_log；祭祀倍数在 MasterData Sacrifice，价格在 Price）
DROP TABLE IF EXISTS `zc_sanguo_sacrifice_action_log`;
CREATE TABLE `zc_sanguo_sacrifice_action_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `get1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获得蓝宝石数',
    `get2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获得黄宝石数',
    `get3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获得紫宝石数',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗金币',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='祭祀活动记录';

-- 玉石作坊采集位（workshop_model：workshop；旧版无唯一约束，新版 (uid, 有效采集位) 唯一防并发重复开启）
DROP TABLE IF EXISTS `zc_sanguo_workshop`;
CREATE TABLE `zc_sanguo_workshop` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `site` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '采集位位置（第几个采集位）',
    `level` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '采集位等级',
    `starttime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '开始采集时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    `active_site` TINYINT UNSIGNED AS (CASE WHEN `deleted_at` IS NULL THEN `site` END) STORED COMMENT '未删除的采集位（仅用于唯一约束）',
    UNIQUE KEY `uk_uid_site` (`uid`, `active_site`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='玉石作坊（采集位）';

-- 银矿占领记录（mine_model：mine；旧版收获 / 放弃 / 被抢时整行删除，新版软删除。
-- 新版加唯一约束防并发：每人只占一个矿（active_uid），同一等级段同一页同一位置只有一人（page, site, bracket））
DROP TABLE IF EXISTS `zc_sanguo_mine`;
CREATE TABLE `zc_sanguo_mine` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `page` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前所在页数',
    `site` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前所在位置',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '矿的类型',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '夺矿时主公等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '夺矿时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    `active_uid` INT UNSIGNED AS (CASE WHEN `deleted_at` IS NULL THEN `uid` END) STORED COMMENT '未删除记录的占领者（仅用于唯一约束）',
    `bracket` INT UNSIGNED AS (CASE WHEN `deleted_at` IS NULL THEN `level` DIV 10 END) STORED COMMENT '未删除记录的等级段（仅用于唯一约束）',
    UNIQUE KEY `uk_active_uid` (`active_uid`),
    UNIQUE KEY `uk_page_site_bracket` (`page`, `site`, `bracket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='矿战记录表';

-- 赌石拥有的石头（gambling_model：own_stone；旧版主键 (uid, position)、切碎 / 卖出时整行删除，新版软删除 + 有效位置唯一）
DROP TABLE IF EXISTS `zc_sanguo_own_stone`;
CREATE TABLE `zc_sanguo_own_stone` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `position` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '宝石id',
    `stoneid` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '品级（0原石 1绿 2蓝 3金 4紫）',
    `cut` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已经切了几刀',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    `active_position` TINYINT UNSIGNED AS (CASE WHEN `deleted_at` IS NULL THEN `position` END) STORED COMMENT '未删除的宝石位置（仅用于唯一约束）',
    UNIQUE KEY `uk_uid_position` (`uid`, `active_position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='拥有宝石表';

-- 神矿赌博记录（jadegamble_model->add_gamble_log：jade_gamble_log；挖掘 / 翻倍规则在 MasterData JadePrice / JadeGamble）
DROP TABLE IF EXISTS `zc_sanguo_jade_gamble_log`;
CREATE TABLE `zc_sanguo_jade_gamble_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `jadetype` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '类型(1蓝;2金;3紫)',
    `jadeturn` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '翻倍次数',
    `jadenum` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '宝石基数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '赌博时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='神矿赌博记录';

-- 神矿活动记录（jadegamble_model->add_action_log / update_action_log：jadegamble_action_log）
DROP TABLE IF EXISTS `zc_sanguo_jadegamble_action_log`;
CREATE TABLE `zc_sanguo_jadegamble_action_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `get1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获得蓝宝石数',
    `get2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获得黄宝石数',
    `get3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获得紫宝石数',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗金币',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`),
    KEY `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='神矿活动记录';

-- 黑市玩家货架（blackmarket_model：blackmarket_log；商品在 MasterData Blackmarket）
DROP TABLE IF EXISTS `zc_sanguo_blackmarket_log`;
CREATE TABLE `zc_sanguo_blackmarket_log` (
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `times` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '免费刷新剩余次数',
    `site1` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置1商品id',
    `site2` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置2商品id',
    `site3` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置3商品id',
    `site1_status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置1状态（1为锁定状态）',
    `site2_status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置2状态（1为锁定状态）',
    `site3_status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置3状态（1为锁定状态）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='黑市记录数据表';

-- 黑市购买记录（blackmarket_model->add_buy_log：blackmarket_buy_log）
DROP TABLE IF EXISTS `zc_sanguo_blackmarket_buy_log`;
CREATE TABLE `zc_sanguo_blackmarket_buy_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `itemid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '商品id',
    `sub_type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗类型（1银币，2元宝，3蓝宝石，4黄宝石，5紫宝石）',
    `sub_value` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗参数',
    `surplus` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '该类型剩余数量',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '购买时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='黑市购买记录表';

-- 黑市刷新记录（blackmarket_model->add_refresh_log：blackmarket_refresh）
DROP TABLE IF EXISTS `zc_sanguo_blackmarket_refresh`;
CREATE TABLE `zc_sanguo_blackmarket_refresh` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户等级',
    `site1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '商品id1',
    `site2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '商品id2',
    `site3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '商品id3',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1:免费刷新、2:金币刷新',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '刷新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='黑市刷新纪录表';

-- 黑市活动记录（blackmarket_model->add_action_log：blackmarket_action_log）
DROP TABLE IF EXISTS `zc_sanguo_blackmarket_action_log`;
CREATE TABLE `zc_sanguo_blackmarket_action_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的用户uid',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗金币',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='黑市活动记录';

-- 每日通商任务（business_model->builder 生成的 business 表：旧版数据在 MasterData Business，只到 2017-10-11；
-- 之后的日期由 Cron\BusinessCron 按旧版 builder 生成到本表，(date, type) 唯一）
DROP TABLE IF EXISTS `zc_sanguo_business`;
CREATE TABLE `zc_sanguo_business` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `date` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '日期',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '类型(1初级;2中级;3高级)',
    `star` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '星级',
    `group` VARCHAR(7) NOT NULL DEFAULT '' COMMENT '商人id组合',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_date_type` (`date`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='每日通商表';

-- 每日通商日志（business_model：business_log）
DROP TABLE IF EXISTS `zc_sanguo_business_log`;
CREATE TABLE `zc_sanguo_business_log` (
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `log` VARCHAR(7) NOT NULL DEFAULT '' COMMENT '用户每日通商日志(a-l表示12个商人)',
    `laststar` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后一次完成任务的星级',
    `lastsite` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后一次获得奖励时商人位置',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后通商时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='每日通商日志';

-- 海外贸易玩家数据（overseastrade_model：overseas_trade_info；货物在 MasterData OverseasTradeShop）
DROP TABLE IF EXISTS `zc_sanguo_overseas_trade_info`;
CREATE TABLE `zc_sanguo_overseas_trade_info` (
    `uid` INT UNSIGNED NOT NULL COMMENT '用户uid',
    `times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余贸易次数',
    `robtimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余打劫次数',
    `re_robtimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '购买的打劫次数',
    `team` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '队伍id',
    `commodity` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '货物id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后活动时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='海外贸易用户数据表';

-- 海外贸易队伍（overseastrade_model：overseas_trade_team；旧版退出队伍时整行删除，新版软删除）
DROP TABLE IF EXISTS `zc_sanguo_overseas_trade_team`;
CREATE TABLE `zc_sanguo_overseas_trade_team` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '队伍id',
    `country` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '队伍所属国家id',
    `country_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '国家名称',
    `member1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成员1',
    `member1_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '成员1名字',
    `member1_gender` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成员1性别',
    `member1_level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成员1等级',
    `member1_commodity` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成员1货物',
    `member2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成员2',
    `member2_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '成员2名字',
    `member2_gender` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成员2性别',
    `member2_level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成员2等级',
    `member2_commodity` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成员2货物',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0未开始，1开始，2被打劫',
    `page` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '队伍所在分页',
    `site` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '队伍位置',
    `startdateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '开始时间',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '队伍创建时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_country` (`country`, `status`, `page`),
    KEY `idx_status_start` (`status`, `startdateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='海外贸易队伍数据表';

-- 海外贸易记录（overseastrade_model->add_trade_log：overseas_trade_log）
DROP TABLE IF EXISTS `zc_sanguo_overseas_trade_log`;
CREATE TABLE `zc_sanguo_overseas_trade_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '类型（1贸易记录，2打劫记录）',
    `team_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '队伍id',
    `member1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成员1uid',
    `member1_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '成员1名称',
    `member1_win` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成员1在打劫中的胜负状态',
    `member1_get` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成员1贸易获得奖励',
    `member1_report` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成员1在打劫中的战报id',
    `member1_tree` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户1的神树等级',
    `member2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成员2uid',
    `member2_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '成员2名称',
    `member2_win` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成员2在打劫中的胜负状态',
    `member2_get` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成员2贸易获得奖励',
    `member2_report` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成员2在打劫中的战报',
    `member2_tree` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户2的神树等级',
    `attack_uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '打劫人的uid',
    `attack_nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '攻击放名称',
    `win` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '打劫是否成功（1成功，0失败）',
    `get` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '打劫获得银币',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_member1` (`member1`),
    KEY `idx_member2` (`member2`),
    KEY `idx_attack_uid` (`attack_uid`),
    KEY `idx_team_id` (`team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='海外贸易-贸易记录表';

-- 海外贸易打劫战报（overseastrade_model->record_pk_report：overseas_trade_report）
DROP TABLE IF EXISTS `zc_sanguo_overseas_trade_report`;
CREATE TABLE `zc_sanguo_overseas_trade_report` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '战报id',
    `report` MEDIUMTEXT NOT NULL COMMENT '战斗过程',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '攻方昵称',
    `userlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻方等级',
    `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '守方昵称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '守方等级',
    `background` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗背景',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='海外贸易打劫战报表';

-- 海外贸易可购买打劫次数（overseastrade_model->get_rob_time：overseas_trade_rob；旧版 uid 普通索引，新版唯一防并发建档）
DROP TABLE IF EXISTS `zc_sanguo_overseas_trade_rob`;
CREATE TABLE `zc_sanguo_overseas_trade_rob` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `times` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '次数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户可购买的打劫次数表';

-- 海外贸易购买打劫次数记录（overseastrade_model->add_buy_log：overseas_trade_buy_log）
DROP TABLE IF EXISTS `zc_sanguo_overseas_trade_buy_log`;
CREATE TABLE `zc_sanguo_overseas_trade_buy_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗的元宝数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid_dateline` (`uid`, `dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户购买打劫次数log表';

ALTER TABLE `zc_sanguo_member_field`
    ADD COLUMN `actnumber` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '今日军令购买次数' AFTER `levelgift`,
    ADD COLUMN `actdateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '军令购买时间' AFTER `actnumber`,
    ADD COLUMN `onmine` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '占矿时间（判断收获）' AFTER `actdateline`,
    ADD COLUMN `onworkshop` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玉石作坊采集时间' AFTER `onmine`,
    ADD COLUMN `stone1_times` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '盘龙石免费次数' AFTER `onworkshop`,
    ADD COLUMN `stone2_times` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '仙王玉石免费次数' AFTER `stone1_times`,
    ADD COLUMN `stone3_times` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '九窍灵石免费次数' AFTER `stone2_times`;

ALTER TABLE `zc_sanguo_member_activity`
    ADD COLUMN `sacrifice1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '蓝宝石祭祀' AFTER `flopleaptimes`,
    ADD COLUMN `sacrifice2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '金宝石祭祀' AFTER `sacrifice1`,
    ADD COLUMN `sacrifice3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '紫宝石祭祀' AFTER `sacrifice2`,
    ADD COLUMN `freebusiness` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '免费通商' AFTER `sacrifice3`,
    ADD COLUMN `businesstimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '购买的通商次数' AFTER `freebusiness`,
    ADD COLUMN `jadetype` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神矿宝石类型' AFTER `businesstimes`,
    ADD COLUMN `jadenum` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神矿宝石基数' AFTER `jadetype`,
    ADD COLUMN `jadeturn` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神矿赌博次数' AFTER `jadenum`;

UPDATE `zc_sanguo_member_activity` SET `freebusiness` = 20;
