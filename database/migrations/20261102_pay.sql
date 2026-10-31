-- 已有数据库升级：充值到账核心与 VIP 经济——充值商品（c=pay&m=get_product_list）、VIP（c=vip）、VIP 回馈（c=vipreward）、
-- VIP 工资（c=vipwage）、首充（c=first_recharge，到账入口接上）、大熊跳楼返利（c=rebate）按旧版移植
-- （旧版 controllers/pay.php 各渠道回调共同的到账部分 + models/pay_model.php、controllers/vip.php + models/vip_model.php、
--  controllers/vipreward.php + models/vipreward_model.php、controllers/vipwage.php + models/vipwage_model.php、
--  controllers/rebate.php + models/rebate_model.php）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261102_pay.sql
--
-- 升级前：没有充值流程；VIP 是新版自定的（member.vip 注册即为 1，俸禄 / 礼包 / 回馈 / 工资为写死数值，
--         领取状态存 Redis 键 vip_salary:{uid}、vip_gifts:{uid}、vipreward_claimed:{uid}、vipwage_date:{uid}）。
-- 升级后：新建旧版 pay_order（合并旧版各渠道结构相同的成功订单表 alipay_order / 91pay_order / pppay_order …，
--         订单号唯一）、pay_log（充值成功流水）、vip_log（VIP 记录）、recharge_list_log（档次首充记录）、
--         newyear_vipreward_log（VIP 回馈领取记录）、jump_rebate / jump_rebate_order（跳楼返利）；
--         member 增加 rebate_firstpay，member.vip 默认值改为旧版的 0；member_field 增加 rebate_dateline / rebate_version；
--         member_activity 增加 vipwage_dateline。
--
-- 数据处理（升级前只有测试数据）：上述 Redis 键是新版自定的数据，对不上旧版结构，不转换、不保留；
-- 升级后不再读写，可自行删除。已有玩家的 member.vip 不回滚（测试数据；旧版 VIP 等级只由充值累计金额决定，
-- 没有 vip_log 的玩家在 VIP 界面显示为 VIP 0，下一次充值时按累计金额重算 member.vip）。
-- 各表先 DROP 再建（会清空本批新表的数据）；末尾的 ALTER TABLE 只能执行一次。


-- 充值成功订单（旧版 alipay_order / 91pay_order / pppay_order 等各渠道成功订单表结构相同，合并为一张；paychannel 记渠道）
DROP TABLE IF EXISTS `zc_sanguo_pay_order`;
CREATE TABLE `zc_sanguo_pay_order` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '订单ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `name` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '用户名称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值时等级',
    `pay_befor` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值前剩余元宝',
    `pay_after` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值后元宝',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否首充',
    `amount` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值金额',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值对应元宝',
    `orderid` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '支付订单id',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '支付状态（1支付成功）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值时间',
    `regchannel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家注册渠道',
    `paychannel` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '用户支付方式',
    `jointime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '注册时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_orderid` (`orderid`),
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='充值订单表';

-- 充值成功流水（pay_log：vip_model->pay 记录）
DROP TABLE IF EXISTS `zc_sanguo_pay_log`;
CREATE TABLE `zc_sanguo_pay_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家uid',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值时玩家等级',
    `money` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值金额（人民币）',
    `vip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值后vip',
    `first` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1：首冲',
    `paytype` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '支付类型',
    `channel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '登录渠道',
    `regchannel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '注册渠道',
    `regtime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '注册时间',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值时间',
    `orderid` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '平台充值订单id',
    `class` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '充值调用的类',
    `method` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '充值调用的方法',
    `argument` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '差异参数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_dateline` (`dateline`),
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='充值成功流水';

-- VIP 记录（vip_log：每人一行）
DROP TABLE IF EXISTS `zc_sanguo_vip_log`;
CREATE TABLE `zc_sanguo_vip_log` (
    `uid` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '用户id',
    `money` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值金额',
    `level` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
    `gift_num` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '礼包领取个数',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首充状态（1是首充，2是领取过首充奖励）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='vip记录表';

-- 充值档次首充记录（recharge_list_log；唯一键 uid + site，同档次只记一次）
DROP TABLE IF EXISTS `zc_sanguo_recharge_list_log`;
CREATE TABLE `zc_sanguo_recharge_list_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `site` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值RMB档次',
    `money` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值RMB',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_site` (`uid`, `site`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='充值产品列表日志表';

-- VIP 回馈领取记录（newyear_vipreward_log；唯一键 uid + version + rewardid，每期每档只领一次）
DROP TABLE IF EXISTS `zc_sanguo_newyear_vipreward_log`;
CREATE TABLE `zc_sanguo_newyear_vipreward_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动版本号',
    `rewardid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取的奖励id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取的时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version_reward` (`uid`, `version`, `rewardid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='春节回馈奖励领取记录表';

-- 大熊跳楼返利活动记录（jump_rebate：旧版无主键；唯一键 uid + version，每人每期一行）
DROP TABLE IF EXISTS `zc_sanguo_jump_rebate`;
CREATE TABLE `zc_sanguo_jump_rebate` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `version` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动版本号',
    `getdateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上次领取返利时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_version` (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='大熊跳楼返利活动记录';

-- 跳楼返利活动期间的充值（jump_rebate_order）
DROP TABLE IF EXISTS `zc_sanguo_jump_rebate_order`;
CREATE TABLE `zc_sanguo_jump_rebate_order` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `level` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值时等级',
    `money` FLOAT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值金额',
    `version` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动版本号',
    `orderid` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '订单id',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid_version` (`uid`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='跳楼反馈活动充值订单';

-- 返利活动首次充值记录（旧版 member.rebate_firstpay）；VIP 默认值改为旧版的 0（VIP 等级由充值累计金额决定）
ALTER TABLE `zc_sanguo_member` ADD COLUMN `rebate_firstpay` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '返利首次充值记录（返利活动期间充过值后不再返利）' AFTER `statustime`;
ALTER TABLE `zc_sanguo_member` ALTER COLUMN `vip` SET DEFAULT 0;

-- 跳楼返利达标时间与期号（旧版 member_field.rebate_dateline / rebate_version）
ALTER TABLE `zc_sanguo_member_field` ADD COLUMN `rebate_dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '跳楼返利活动达到返利标准时间' AFTER `advanced_time`;
ALTER TABLE `zc_sanguo_member_field` ADD COLUMN `rebate_version` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '跳楼返利活动版本号' AFTER `rebate_dateline`;

-- VIP 工资领取时间（旧版 member_activity.vipwage_dateline）
ALTER TABLE `zc_sanguo_member_activity` ADD COLUMN `vipwage_dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'VIP工资领取时间' AFTER `heavendate`;
