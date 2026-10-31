-- 悍将三国 (Modern Edition) 核心数据库完整架构定义
-- 严格遵循 AGENTS.md 第七大铁律：全表三必备时间字段 (created_at, updated_at, deleted_at)
-- 兼容 MySQL 8.0+ / MySQL 9.x，全量支持 utf8mb4_unicode_ci 高性能字符集
-- 本文件始终是完整的最新结构：空库启动服务时由 database/migrations/0000_00_00_000000_import_base_schema.php 自动导入；
-- 每新增一个 database/migrations/*.php 迁移，同时把改动写进本文件（迁移必须幂等，见 AGENTS.md）

CREATE DATABASE IF NOT EXISTS `zc_sanguo` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `zc_sanguo`;

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- 0. 数据库迁移记录表 (OmniPHP MigrationRunner；不加 zc_sanguo_ 前缀，原因见 src/Support/Migrations.php)
--    定义与 Hanjiang\Support\Migrations::TABLE_DDL 一致；导入本文件后启动服务，database/migrations/*.php 全部记为已执行（都是空操作）
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `migration` VARCHAR(255) NOT NULL COMMENT '迁移文件名（不含 .php）',
    `batch` INT NOT NULL COMMENT '批次号',
    `executed_at` DATETIME NOT NULL COMMENT '执行时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据库迁移记录（OmniPHP MigrationRunner，回滚时物理删除记录）';

-- -------------------------------------------------------------
-- 1. 用户账号表 (对齐原版 zc_sanguo_user)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_user`;
CREATE TABLE `zc_sanguo_user` (
    `uid` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(64) NOT NULL UNIQUE,
    `password` VARCHAR(128) NOT NULL,
    `channel` INT NOT NULL DEFAULT 106,
    `is_guest` TINYINT NOT NULL DEFAULT 0,
    `mac` VARCHAR(64) NOT NULL DEFAULT '',
    `guest_key` CHAR(64) NOT NULL DEFAULT '' COMMENT '游客登录密钥 sha256（明文只在注册时返回一次）',
    `phone` VARCHAR(13) NOT NULL DEFAULT '' COMMENT '手机号（注册 / 绑定时填写）',
    `version` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '注册 / 绑定时的客户端版本',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_username` (`username`),
    INDEX `idx_mac` (`mac`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户账号主表';

-- -------------------------------------------------------------
-- 2. 玩家基础属性表 (对齐原版 zc_sanguo_member)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_member`;
CREATE TABLE `zc_sanguo_member` (
    `uid` INT UNSIGNED PRIMARY KEY,
    `username` VARCHAR(64) NOT NULL DEFAULT '',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '天命主公',
    `sex` TINYINT NOT NULL DEFAULT 1,
    `gender` TINYINT NOT NULL DEFAULT 0,
    `roleid` TINYINT NOT NULL DEFAULT 1,
    `country` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家id(zc_sanguo_country.id，0 未加入)',
    `level` INT UNSIGNED NOT NULL DEFAULT 1,
    `exp` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `maxexp` BIGINT UNSIGNED NOT NULL DEFAULT 100,
    `silver` BIGINT NOT NULL DEFAULT 88888 COMMENT '银两',
    `gold` INT NOT NULL DEFAULT 9999 COMMENT '元宝',
    `reputation` INT NOT NULL DEFAULT 100 COMMENT '声望',
    `soul` INT UNSIGNED NOT NULL DEFAULT 100 COMMENT '将魂精魄',
    `souluse` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将魂包裹占用格数（OwnSoul）',
    `soulpack` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将魂包裹格数（旧版注册时写 4）',
    `jade` INT NOT NULL DEFAULT 0 COMMENT '玉石个数（MemberJade）',
    `gem` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '拥有宝石碎片数量（OwnGem）',
    `gemuse` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '宝石格子占用数（OwnGem）',
    `refinestone` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '精炼石（MemberStone 类型 1）',
    `colorstone` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '五彩石（MemberStone 类型 2）',
    `bloodstone` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上古血玉（MemberStone 类型 3）',
    `act` INT NOT NULL DEFAULT 100 COMMENT '军令/体力',
    `maxact` INT NOT NULL DEFAULT 100,
    `vip` TINYINT NOT NULL DEFAULT 0,
    `bagnum` INT NOT NULL DEFAULT 20,
    `guide` INT NOT NULL DEFAULT 0,
    `guidestep` INT NOT NULL DEFAULT 0,
    `guidestop` TINYINT NOT NULL DEFAULT 0,
    `missionsite` INT NOT NULL DEFAULT 1,
    `missionlevel` INT NOT NULL DEFAULT 1,
    `missionstage` INT NOT NULL DEFAULT 1,
    `missionid` INT NOT NULL DEFAULT 1,
    `missionworld` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前是第几张地图（1初始地图，2第二张地图）',
    `firstworld` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首次进入过第几张地图',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1、禁言 2、永久禁言 3、封号 4、永久封号（聊天室发言只认 1 / 2）',
    `statustime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '禁言或封号截止时间（到期后清除 status）',
    `rebate_firstpay` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '返利首次充值记录（返利活动期间充过值后不再返利）',
    `newpm` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '新信件（1 有未读的气泡信件）',
    `newmail` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '邮件提醒（1 新邮件，2 GM 已回复，3 两者都有）',
    `crontime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '征收计划任务执行时间（每日 7 点发放征收次数）',
    `jointime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '加入游戏时间',
    `gift` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已领取礼包个数',
    `guest` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否是试玩用户（1 试玩、未领绑定奖励，2 已领绑定奖励）',
    `server_reward` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '全服奖励（已领到的 world_reward id）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='主公核心档案表';

-- -------------------------------------------------------------
-- 3. 玩家扩展功能字段表 (对齐原版 zc_sanguo_member_field)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_member_field`;
CREATE TABLE `zc_sanguo_member_field` (
    `uid` INT UNSIGNED PRIMARY KEY,
    `attackstatus` TINYINT NOT NULL DEFAULT 0,
    `attackdateline` INT UNSIGNED NOT NULL DEFAULT 0,
    `levytimes` INT NOT NULL DEFAULT 50,
    `levydateline` INT UNSIGNED NOT NULL DEFAULT 0,
    `practiceall` INT NOT NULL DEFAULT 2,
    `practicenow` INT NOT NULL DEFAULT 0,
    `practicetime` INT UNSIGNED NOT NULL DEFAULT 0,
    `freeleap` INT NOT NULL DEFAULT 0 COMMENT '免费突飞次数（突飞卡）',
    `leaptimes` INT NOT NULL DEFAULT 0,
    `rank` INT NOT NULL DEFAULT 0 COMMENT '演武榜排名（zc_sanguo_arena.rank，0 为未入榜）',
    `newresult` INT NOT NULL DEFAULT 0,
    `start_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首次进入游戏时间（闯关竞速从此时起 3 天）',
    `race` TINYINT NOT NULL DEFAULT 0,
    `race_dateline` INT UNSIGNED NOT NULL DEFAULT 0,
    `matrixid` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '启用的阵法（zc_sanguo_matrix.mid），0 为没有阵法',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '每日第一次游戏时间（每日计划任务判断依据）',
    `first_recharge` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '首充返利标识（1 显示首充图标，2 已首充待领奖，0 不显示）',
    `soultimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '星路购买次数（将魂星路今日购买次数，每日计划任务归零）',
    `heaventimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通天塔次数（扫荡次数不足时累加补买的次数，旧版每日计划任务不归零）',
    `oncountrymine` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家矿占领到期时间（到期后主城提示可收获，收获 / 放弃 / 被抢时清 0）',
    `advanced_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上次使用进阶传承时间（名将进阶传承 CD）',
    `rebate_dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '跳楼返利活动达到返利标准时间',
    `rebate_version` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '跳楼返利活动版本号',
    `baijintimes` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '今日白金培养次数（每日计划任务归零）',
    `zhizuntimes` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '今日至尊培养次数（每日计划任务归零）',
    `ongeneral` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '点将台是否有新武将（获得武将气泡）',
    `free_inherit` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上次使用免费继承时间',
    `general_sale` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将折旧使用时间',
    `inherit_dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '免费赠送的至尊继承使用时间',
    `levyall` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '总征收次数（整军演武）',
    `exercisetimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '征收恢复时间',
    `levystatus` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '征收状态 0 可以征收 1 冷却中',
    `imposetimes` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '今日强征次数',
    `qiangstatus` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '强化状态 0 可以强化 1 冷却中',
    `qiangdateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '强化CD过期时间',
    `close_invitation` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关闭邀请',
    `login_day` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '连续登录天数',
    `login_dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '连续登录天数更新时间',
    `levelgift` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '等级礼包图标(0消失1显示2可领取)',
    `actnumber` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '今日军令购买次数',
    `actdateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '军令购买时间',
    `onmine` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '占矿时间（判断收获）',
    `onworkshop` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玉石作坊采集时间',
    `stone1_times` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '盘龙石免费次数',
    `stone2_times` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '仙王玉石免费次数',
    `stone3_times` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '九窍灵石免费次数',
    `clickreward` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '评分奖励领取时间',
    `devicetoken` CHAR(70) NOT NULL DEFAULT '' COMMENT 'ios设备token',
    `treasure_free` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '踏青寻宝免费次数（每日重置为 1）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='玩家征战与征税扩展表';

-- -------------------------------------------------------------
-- 4. 武将表 (已招募武将及属性，含软删除覆盖索引)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_general`;
CREATE TABLE `zc_sanguo_general` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL,
    `generalid` INT NOT NULL COMMENT '武将模板ID（MasterData General，即旧版模板表 zc_sanguo_general.id）',
    `name` VARCHAR(64) NOT NULL DEFAULT '',
    `level` INT NOT NULL DEFAULT 1,
    `exp` BIGINT NOT NULL DEFAULT 0,
    `levelupexp` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领升级经验',
    `image` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将资源ID',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '职业（1 猛将、2 谋士）',
    `wuli` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '初始武力',
    `zhili` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '初始智力',
    `tili` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '初始体力',
    `talentid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '天赋ID',
    `skillid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '技能ID',
    `star` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将星级',
    `turn` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '转生次数',
    `quality` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '武将进阶品级（1白，2绿，3蓝，4金，5紫，6红）',
    `addtype` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成就加成类型',
    `addvalue` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成就加成值',
    `mid0` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '主战坐骑',
    `mid1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '备战坐骑1',
    `mid2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '备战坐骑2',
    `mid3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '备战坐骑3',
    `mid4` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '备战坐骑4',
    `goout` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否出征(1 出征中、0 休息中)',
    `status` TINYINT NOT NULL DEFAULT 1,
    `wuliup` INT NOT NULL DEFAULT 0 COMMENT '武力培养附加值',
    `zhiliup` INT NOT NULL DEFAULT 0 COMMENT '智力培养附加值',
    `tiliup` INT NOT NULL DEFAULT 0 COMMENT '体力培养附加值',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`),
    INDEX `idx_uid_deleted` (`uid`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='武将名册表';

-- -------------------------------------------------------------
-- 5. 装备背包表 (含软删除覆盖索引；同一武将同一装配位置最多一件，见 uk_general_site)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_equipment`;
CREATE TABLE `zc_sanguo_equipment` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL,
    `equipid` INT NOT NULL COMMENT '装备模板ID（MasterData Equip，即旧版 zc_sanguo_equip.id）',
    `name` VARCHAR(64) NOT NULL DEFAULT '',
    `type` TINYINT NOT NULL DEFAULT 1 COMMENT '装备类型（1 猛将武器、2 谋士武器、3 铠甲、4 披风、5 兵符）',
    `quality` TINYINT NOT NULL DEFAULT 1 COMMENT '装备品质（1 白、2 绿、3 蓝、4 金、5 紫）',
    `needlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将装配等级',
    `level` INT NOT NULL DEFAULT 0 COMMENT '装备强化等级',
    `power` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '装备能力初始值',
    `powerup` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '强化后能力',
    `generalid` INT NOT NULL DEFAULT 0 COMMENT '穿戴该装备的武将（本库 zc_sanguo_general.id），0 为未穿戴',
    `site` TINYINT UNSIGNED AS (CASE WHEN `generalid` > 0 AND `deleted_at` IS NULL THEN CASE `type` WHEN 1 THEN 1 WHEN 2 THEN 1 WHEN 3 THEN 2 WHEN 4 THEN 3 WHEN 5 THEN 4 END END) STORED COMMENT '装配位置（旧版 get_item_site 派生，未穿戴/已删除为 NULL），仅用于唯一约束',
    `number` INT NOT NULL DEFAULT 1,
    `saleprice` INT NOT NULL DEFAULT 0,
    `chip` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '碎片数(0 道具、>1碎片)',
    `sale_site` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '道具出售位置（1红装回收，0背包出售）',
    `hammer` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '锤子等级(1-7:白绿蓝黄紫红橙)',
    `aid` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '强化等级成就ID',
    `forgeid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神铸属性关联id',
    `newquality` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '打造出来的装备新品级',
    `oldequipid` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '打造出来的装备关联旧的装备id',
    `newattribute` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '神铸属性',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_general_site` (`uid`, `generalid`, `site`),
    INDEX `idx_uid` (`uid`),
    INDEX `idx_general` (`uid`, `generalid`),
    INDEX `idx_uid_deleted` (`uid`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='装备与神兵表';

-- -------------------------------------------------------------
-- 6. 将领将魂槽 (对齐原版 zc_sanguo_own_general_soul)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_own_general_soul`;
CREATE TABLE `zc_sanguo_own_general_soul` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gid` INT UNSIGNED NOT NULL COMMENT '将领id（general.id）',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `site1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置1（own_soul.id，0 为空）',
    `site2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置2',
    `site3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置3',
    `site4` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置4',
    `site5` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置5',
    `site6` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置6',
    `site7` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置7',
    `site8` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置8',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_gid` (`gid`),
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='拥有将领将魂表';

-- -------------------------------------------------------------
-- 7. 拥有阵法表 (对齐原版 zc_sanguo_own_matrix：每个阵法一行，启用的阵法记在 member_field.matrixid)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_matrix`;
CREATE TABLE `zc_sanguo_matrix` (
    `uid` INT UNSIGNED NOT NULL COMMENT '用户id',
    `mid` TINYINT UNSIGNED NOT NULL COMMENT '阵法模板id（MasterData Matrix）',
    `position1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置1武将id（zc_sanguo_general.id）',
    `position2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置2武将id',
    `position3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置3武将id',
    `position4` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置4武将id',
    `position5` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置5武将id',
    `position6` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置6武将id',
    `position7` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置7武将id',
    `position8` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置8武将id',
    `position9` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置9武将id',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '阵法等级',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`, `mid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='拥有阵法表';

-- -------------------------------------------------------------
-- 8. 演武演练修习表
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_practice`;
CREATE TABLE `zc_sanguo_practice` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL,
    `squence` TINYINT NOT NULL DEFAULT 1 COMMENT '修习队列序号 1~2',
    `generalid` INT NOT NULL DEFAULT 0 COMMENT '正在受训的武将ID',
    `level` INT NOT NULL DEFAULT 1,
    `type` TINYINT NOT NULL DEFAULT 1,
    `endtime` INT UNSIGNED NOT NULL DEFAULT 0,
    `starttime` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_seq` (`uid`, `squence`),
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='演武场修炼表';

-- -------------------------------------------------------------
-- 9. 演武榜 (对齐原版 zc_sanguo_arena / arena_log / arena_paylog / arena_exchange_log / member_integral / integral_log)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_arena`;
CREATE TABLE `zc_sanguo_arena` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `username` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '用户名（入榜时的昵称）',
    `win` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '连胜次数',
    `rank` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排名',
    `total_rank` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '历史最高排名',
    `times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余次数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后挑战时间（跨天重置剩余次数的依据）',
    `get_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后领取奖励时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`),
    INDEX `idx_rank` (`rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='竞技场数据表';

DROP TABLE IF EXISTS `zc_sanguo_arena_log`;
CREATE TABLE `zc_sanguo_arena_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'logid',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1攻击2防御',
    `win` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1胜利2失败',
    `rank` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '变化后的排名',
    `userid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '对方用户id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '挑战时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='竞技场挑战记录表';

DROP TABLE IF EXISTS `zc_sanguo_arena_paylog`;
CREATE TABLE `zc_sanguo_arena_paylog` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `times` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '本日购买次数',
    `total_times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '总共购买次数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后购买时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='竞技场次数购买记录表';

DROP TABLE IF EXISTS `zc_sanguo_arena_exchange_log`;
CREATE TABLE `zc_sanguo_arena_exchange_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `pid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '商品ID（MasterData ArenaExchange）',
    `ptype` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '商品类型：1，每日刷新数量；2，限量；',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '购买时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='演武榜积分商城商品兑换日志表';

DROP TABLE IF EXISTS `zc_sanguo_member_integral`;
CREATE TABLE `zc_sanguo_member_integral` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `act_kemari_int` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '蹴鞠奖券积分',
    `act_arena_int` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '演武榜积分',
    `beauty_rose` BIGINT NOT NULL DEFAULT 0 COMMENT '铜雀台玫瑰花数量',
    `beauty_diamond` BIGINT NOT NULL DEFAULT 0 COMMENT '铜雀台钻石数量',
    `pass_general_int` INT NOT NULL DEFAULT 0 COMMENT '过关斩将积分',
    `general` INT NOT NULL DEFAULT 0 COMMENT '将军令',
    `candy` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '万圣节糖果',
    `hammer` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '万圣节锤子',
    `medal` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '万圣节奖章',
    `god_soul` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神将魂魄',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='玩家积分记录表（演武榜、世界 BOSS 奖励读写 act_arena_int；过关斩将读写兑奖券 act_kemari_int）';

DROP TABLE IF EXISTS `zc_sanguo_integral_log`;
CREATE TABLE `zc_sanguo_integral_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主键',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `type` INT NOT NULL DEFAULT 0 COMMENT '记录类型：1，增加；2，消耗；',
    `argument` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '积分字段（zc_sanguo_member_integral 的列名）',
    `class` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '访问控制器',
    `method` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '访问方法',
    `intnum` BIGINT NOT NULL DEFAULT 0 COMMENT '增加/减少积分值',
    `balance` BIGINT NOT NULL DEFAULT 0 COMMENT '积分变化后的值',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '积分变化时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='玩家积分日志表';

-- -------------------------------------------------------------
-- 10. 主线推图（征战）玩家数据（旧版 map_model；势力 / 场景 / 关卡等配置在 MasterData）
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_open_map`;
CREATE TABLE `zc_sanguo_open_map` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `level` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '势力id，每次开启势力，增加记录',
    `stage` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最大场景id',
    `mission` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最大任务id',
    `newresult` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通关奖励领取状态',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_level` (`uid`, `level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='势力，场景，任务开启记录';

DROP TABLE IF EXISTS `zc_sanguo_mission_log`;
CREATE TABLE `zc_sanguo_mission_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id（即战报 mission_report.id）',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '玩家昵称',
    `missionlevel` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '势力id',
    `missionstage` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '场景id',
    `missionid` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务id',
    `times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '今日次数',
    `strom` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '总共强攻次数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后更新时间',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首杀时用户等级',
    `disable` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 显示;1 不显示',
    `tree` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神树活动中的神树等级',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_mission` (`uid`, `missionlevel`, `missionstage`, `missionid`),
    INDEX `idx_mission` (`missionlevel`, `missionstage`, `missionid`),
    INDEX `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='任务记录表';

DROP TABLE IF EXISTS `zc_sanguo_mission_report`;
CREATE TABLE `zc_sanguo_mission_report` (
    `id` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '战报id（mission_log.id）',
    `report` TEXT NOT NULL COMMENT '战斗过程（base64(gzcompress(战报 json))）',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '玩家昵称',
    `userlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `name` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '关卡名称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关卡等级',
    `background` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗背景',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='主线关卡首杀战报';

DROP TABLE IF EXISTS `zc_sanguo_mission_rewardlog`;
CREATE TABLE `zc_sanguo_mission_rewardlog` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `missionid` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关卡ID',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_mission` (`uid`, `missionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='闯关竞速奖励领取记录';

DROP TABLE IF EXISTS `zc_sanguo_map_action_log`;
CREATE TABLE `zc_sanguo_map_action_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的用户uid',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加活动的时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='竞速活动记录';

DROP TABLE IF EXISTS `zc_sanguo_pub_log`;
CREATE TABLE `zc_sanguo_pub_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `generalid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领模板id',
    `own` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 可招募; 1 已招募',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获得时间',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '昵称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `money` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值金额',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获取方式(1酒馆;2征战;3神将;11通关奖励)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_general` (`uid`, `generalid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='酒馆将领获得日志';

-- -------------------------------------------------------------
-- 15. 关卡宝箱领取记录表（宝箱配置在 MasterData MissionNewreward）
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_mission_newreward_log`;
CREATE TABLE `zc_sanguo_mission_newreward_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL,
    `levelid` INT UNSIGNED NOT NULL DEFAULT 1,
    `missid` INT UNSIGNED NOT NULL DEFAULT 1,
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_lvl_miss` (`uid`, `levelid`, `missid`),
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='玩家通关奖励领取记录表';


-- -------------------------------------------------------------
-- 国家（玩家军团）系统 (对齐原版 zc_sanguo_country 等表)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_country`;
CREATE TABLE `zc_sanguo_country` (
    `id` SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '国家id',
    `name` VARCHAR(21) NOT NULL COMMENT '名称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `member` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成员个数',
    `uid` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国王uid',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '国王昵称',
    `exp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前经验',
    `levelupexp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '升级所需经验',
    `allexp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '总经验',
    `city` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家占领都城id',
    `city_status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '都城状态（1已占领，2已请战，3有挑战国家）',
    `military` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家军令',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_name` (`name`),
    KEY `idx_rank` (`deleted_at`, `level`, `exp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家表';

DROP TABLE IF EXISTS `zc_sanguo_member_country`;
CREATE TABLE `zc_sanguo_member_country` (
    `uid` BIGINT UNSIGNED NOT NULL PRIMARY KEY COMMENT '玩家uid',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '昵称',
    `country` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家id',
    `job` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '职位',
    `contribute` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '今日贡献',
    `allcontribute` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '总贡献',
    `worshipdateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '每日祭祀时间',
    `eid` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '谏言的列表',
    `support` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '支持谏言时间（判断首次支持免费）',
    `refresh` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '刷新谏言列表（判断首次刷新免费）',
    `effect` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '都城占领效果更新时间',
    `effect_type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家都城奖励类型',
    `city` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参加国战时的城池id',
    `warstatus` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国战状态（1已参加国战，0没有参加',
    `salarytime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取俸禄时间',
    `goldtimes` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '元宝贡献次数',
    `silvertimes` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '银币贡献次数',
    `learntimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '学艺次数',
    `joindateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '加入国家时间',
    `leavedateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '离开国家的时间',
    `debris` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神器碎片数量',
    `credit` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战功',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `job` (`job`),
    KEY `idx_country` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='玩家国家表';

-- 原版主键为 (uid, country)；软删除后同一玩家可再次申请同一国家，改用自增 id 主键
DROP TABLE IF EXISTS `zc_sanguo_country_apply`;
CREATE TABLE `zc_sanguo_country_apply` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` BIGINT UNSIGNED NOT NULL,
    `country` MEDIUMINT UNSIGNED NOT NULL COMMENT '国家id',
    `rank` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家排行',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `nickname` VARCHAR(21) NOT NULL DEFAULT '' COMMENT '玩家昵称',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid_country` (`uid`, `country`),
    KEY `idx_country` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家申请表';

DROP TABLE IF EXISTS `zc_sanguo_country_notice`;
CREATE TABLE `zc_sanguo_country_notice` (
    `id` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '国家id',
    `notice` VARCHAR(210) NOT NULL DEFAULT '' COMMENT '公告',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家公告表';

DROP TABLE IF EXISTS `zc_sanguo_country_report`;
CREATE TABLE `zc_sanguo_country_report` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '战报id',
    `report` TEXT NOT NULL COMMENT '战斗过程',
    `nickname` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '攻方昵称',
    `userlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻方等级',
    `name` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '守方昵称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '守方等级',
    `background` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗背景',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家成员升官战报表';

DROP TABLE IF EXISTS `zc_sanguo_country_log`;
CREATE TABLE `zc_sanguo_country_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `operator_uid` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作人uid（踢人，通过审核）',
    `country` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家id',
    `status` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作（1退出，2进入，3被踢出）',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '操作执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '操作执行方法',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='退出/进入国家操作记录表';

DROP TABLE IF EXISTS `zc_sanguo_country_donate_log`;
CREATE TABLE `zc_sanguo_country_donate_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` BIGINT UNSIGNED NOT NULL,
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '花费类型1：银币 2：元宝 3: 谏言',
    `cost` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '花费数',
    `donate` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获得贡献',
    `contribute` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当日贡献',
    `allcontribute` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '总贡献',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `argument` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `vip` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家捐献记录表';

DROP TABLE IF EXISTS `zc_sanguo_country_learn_log`;
CREATE TABLE `zc_sanguo_country_learn_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `vip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip等级',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `cost` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '花费元宝',
    `addexp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获得经验',
    `gid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将id',
    `gturn1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '几转',
    `glevel1` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将等级(加经验前)',
    `gexp1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将经验（加经验前）',
    `glevelupexp1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '升级所需经验（加经验前）',
    `glevel2` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将等级（加经验后）',
    `gexp2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将经验（加经验后）',
    `glevelupexp2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '升级经验（加经验后）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 正常，3 等级上限，4 需要转生，其他失败',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国师授艺记录表';

-- -------------------------------------------------------------
-- 首充返利日志 (对齐原版 zc_sanguo_first_recharge_log，每个玩家一行)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_first_recharge_log`;
CREATE TABLE `zc_sanguo_first_recharge_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色ID',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '充值元宝数量',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已领取的首充档位ID（0 未领取）',
    `lottery` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '转盘抽奖状态（0 未抽奖，1 已抽奖）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取奖励时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='首充返利领取奖励日志表';

-- -------------------------------------------------------------
-- 将魂星路 (对齐原版 zc_sanguo_member_activity 的星路 / 多人副本 / 金银岛 / 四象圣域 / 通天塔字段 / soul_open_map / soul_log / soul_report)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_member_activity`;
CREATE TABLE `zc_sanguo_member_activity` (
    `uid` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '用户ID',
    `towerlevel` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '势力id',
    `towerstage` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '场景id',
    `towermission` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关卡id',
    `freetower` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '免费爬塔次数',
    `towertimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '购买的爬塔次数',
    `souldate` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '星路奖励领取时间',
    `elitetimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '多人副本挑战次数',
    `elitebuytimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '多人副本今日购买次数',
    `elitebuydateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '多人副本最后购买次数时间',
    `elitesurplustimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '多人副本购买剩余次数',
    `islandid` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '金银岛关卡id',
    `islandtimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '今日金银岛挑战次数',
    `islanddate` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '金银岛进度更新时间',
    `sanctumdate` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '四象圣域每日奖励领取时间',
    `heavenstage` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通天塔（旧版未使用）',
    `heavenmission` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通天塔（index 置 0）',
    `heavenlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通天塔（旧版未使用）',
    `heaventimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通天塔购买的剩余次数',
    `freeheaven` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通天塔免费次数',
    `heavendate` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通天塔每日奖励领取时间',
    `vipwage_dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'VIP工资领取时间',
    `task_number` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '日常任务今日剩余次数',
    `drinktime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '每日对酒时间',
    `generaltasktimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神将之门挑战次数（每日计划任务重置为 10）',
    `exercise_gift` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '整军演武礼包剩余数',
    `exercise_level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获得礼包时等级',
    `floptimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '四方来朝剩余次数',
    `flopact` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '四方来朝军令花费',
    `flopgold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '四方来朝元宝花费',
    `flopleap` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '四方来朝突飞次数',
    `flopacttimes` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '军令次数',
    `flopgoldtimes` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '元宝次数',
    `flopleaptimes` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '突飞获得次数',
    `sacrifice1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '蓝宝石祭祀',
    `sacrifice2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '金宝石祭祀',
    `sacrifice3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '紫宝石祭祀',
    `freebusiness` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '免费通商',
    `businesstimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '购买的通商次数',
    `jadetype` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神矿宝石类型',
    `jadenum` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神矿宝石基数',
    `jadeturn` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神矿赌博次数',
    `mapreward` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '藏宝图奖励',
    `smashtime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上次威震天下使用时间',
    `freemooncake` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '中秋吃月饼免费次数（每日重置为 12）',
    `mooncaketimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '中秋吃月饼购买次数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='玩家活动信息表（旧版其余字段随对应玩法移植补充）';

DROP TABLE IF EXISTS `zc_sanguo_soul_open_map`;
CREATE TABLE `zc_sanguo_soul_open_map` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `level` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '势力id，每次开启势力，增加记录',
    `stage` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最大场景id',
    `mission` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最大任务id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='势力，场景，任务开启记录';

DROP TABLE IF EXISTS `zc_sanguo_soul_log`;
CREATE TABLE `zc_sanguo_soul_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id（即战报 soul_report.id）',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '玩家昵称',
    `missionlevel` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '势力id',
    `missionstage` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '场景id',
    `missionid` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后更新时间',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首杀时用户等级',
    `disable` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 显示;1 不显示',
    `tree` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神树活动时的神树等级',
    `first_reward` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首杀标识',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid_mission` (`uid`, `missionid`),
    INDEX `idx_mission` (`missionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='将魂星路任务记录表';

DROP TABLE IF EXISTS `zc_sanguo_soul_report`;
CREATE TABLE `zc_sanguo_soul_report` (
    `id` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '战报id（soul_log.id）',
    `report` TEXT NOT NULL COMMENT '战斗过程（json_encode(base64(gzcompress(战报 json)))）',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '玩家昵称',
    `userlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `name` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '关卡名称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关卡等级',
    `background` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗背景',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='将魂星路战报表';

-- -------------------------------------------------------------
-- 多人副本 (对齐原版 zc_sanguo_member_activity 的 elite* 字段 / elite_log / elite_mission_log)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_elite_log`;
CREATE TABLE `zc_sanguo_elite_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `leader` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '队长uid',
    `member` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '队伍成员',
    `fid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '副本id',
    `topteam` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否至尊组队，1是，0否',
    `reward` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '用户获得奖励',
    `times` INT NOT NULL DEFAULT 0 COMMENT '当前剩余次数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_leader_dateline` (`leader`, `dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='多人副本战斗记录表';

DROP TABLE IF EXISTS `zc_sanguo_elite_mission_log`;
CREATE TABLE `zc_sanguo_elite_mission_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `missionid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '军团id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通过时间',
    `first_reward` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首杀标识',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid_mission` (`uid`, `missionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='副本关卡通过记录表';

-- -------------------------------------------------------------
-- 新副本 / 锻造材料 (对齐原版 zc_sanguo_copies_beat / copies_beat_log / copies_report / own_materials / own_materials_log)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_copies_beat`;
CREATE TABLE `zc_sanguo_copies_beat` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'UID',
    `vip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前vip等级',
    `copies_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '副本ID 1天公张角 2董卓乱政 3战神吕布',
    `diff_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '副本难度1简单 2普通 3困难',
    `monster_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '怪物ID',
    `status` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻打状态 1成功 0未成功',
    `free_times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '免费攻打次数',
    `vip_times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip可购买次数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻打时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`, `copies_id`, `diff_id`, `monster_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户攻打副本数据表';

DROP TABLE IF EXISTS `zc_sanguo_copies_beat_log`;
CREATE TABLE `zc_sanguo_copies_beat_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID（即战报 copies_report.id）',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'UID',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '昵称',
    `level` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '等级',
    `copies_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '副本ID 1天公张角 2董卓乱政 3战神吕布',
    `diff_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '副本难度1简单 2普通 3困难',
    `monster_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '怪物ID',
    `status` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗结果（首杀时的战斗评级）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻打时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid_monster` (`uid`, `copies_id`, `diff_id`, `monster_id`),
    INDEX `idx_monster_dateline` (`copies_id`, `diff_id`, `monster_id`, `dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户攻打副本日志表（首杀记录 / 攻略列表）';

DROP TABLE IF EXISTS `zc_sanguo_copies_report`;
CREATE TABLE `zc_sanguo_copies_report` (
    `id` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '战报id（copies_beat_log.id）',
    `report` TEXT NOT NULL COMMENT '战斗过程（base64(gzcompress(战报 json))）',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '昵称',
    `userlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `name` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '怪物名称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '怪物等级',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='新副本战报表';

DROP TABLE IF EXISTS `zc_sanguo_own_materials`;
CREATE TABLE `zc_sanguo_own_materials` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'UID',
    `materials_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '材料ID',
    `name` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '材料名称',
    `quality` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '材料品级 1紫色 2红色 3橙色 4无等级 5卷轴',
    `chip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否卷轴碎片 1碎片 0卷轴',
    `num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '数量',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_materials` (`uid`, `materials_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='打造系统-拥有材料表';

DROP TABLE IF EXISTS `zc_sanguo_own_materials_log`;
CREATE TABLE `zc_sanguo_own_materials_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'UID',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新类型 1增加 0消耗',
    `materials_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '材料ID',
    `name` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '材料名称',
    `quality` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '材料品级 1紫色 2红色 3橙色 4无等级 5卷轴',
    `chip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否卷轴碎片 1碎片 0卷轴',
    `num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新数量',
    `now_num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前数量',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获取时间',
    `class` VARCHAR(80) NOT NULL DEFAULT '' COMMENT '执行类（旧版副本发材料时不写，为空串）',
    `method` VARCHAR(80) NOT NULL DEFAULT '' COMMENT '执行方法（旧版副本发材料时不写，为空串）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='打造系统-更新材料log';

-- -------------------------------------------------------------
-- 国家 BOSS 击杀记录 (对齐原版 zc_sanguo_country_boss_log；世界 / 国家 BOSS 其余状态同旧版存 Redis)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_country_boss_log`;
CREATE TABLE `zc_sanguo_country_boss_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `country` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家id',
    `ymd` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '击杀日期',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '记录时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_country_ymd` (`country`, `ymd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家boss击杀次数记录表';

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

-- -------------------------------------------------------------
-- 四象圣域 (对齐原版 zc_sanguo_sanctum_log / sanctum_open_map / sanctum_mission_log / sanctum_report；
-- 每日奖励领取时间为 member_activity.sanctumdate；关卡 / 守军 / 每日奖励倍率配置在 MasterData SanctumMission / SanctumMonster / SanctumReward)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_sanctum_log`;
CREATE TABLE `zc_sanguo_sanctum_log` (
    `uid` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '用户uid',
    `missionlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '圣域id',
    `missionstage` INT NOT NULL DEFAULT 0 COMMENT '当前所在层数',
    `missionid` INT NOT NULL DEFAULT 0 COMMENT '当前所在关卡',
    `times` INT NOT NULL DEFAULT 0 COMMENT '剩余攻击次数',
    `buy_times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '四象剩余购买的次数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后活动时间（每日重置免费次数的判断依据）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='四象圣域用户记录表';

DROP TABLE IF EXISTS `zc_sanguo_sanctum_open_map`;
CREATE TABLE `zc_sanguo_sanctum_open_map` (
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `city` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '圣域id（1~5）',
    `stage` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '层数（1~5）',
    `missionid` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已通过的关卡id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`, `city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='四象圣域关卡开启表';

DROP TABLE IF EXISTS `zc_sanguo_sanctum_mission_log`;
CREATE TABLE `zc_sanguo_sanctum_mission_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id（即战报 sanctum_report.id）',
    `missionid` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关卡ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '用户昵称',
    `level` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通过时间',
    `tree` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神树活动中的神树等级',
    `first_reward` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首杀标识',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid_mission` (`uid`, `missionid`),
    INDEX `idx_mission` (`missionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='四象圣域关卡通过记录表';

DROP TABLE IF EXISTS `zc_sanguo_sanctum_report`;
CREATE TABLE `zc_sanguo_sanctum_report` (
    `id` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '战报id（sanctum_mission_log.id）',
    `report` TEXT NOT NULL COMMENT '战斗过程（json_encode(base64(gzcompress(战报 json)))）',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '玩家昵称',
    `userlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `name` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '关卡名称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关卡等级',
    `background` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗背景',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='四象圣域战报表';

-- -------------------------------------------------------------
-- 通天塔 (对齐原版 zc_sanguo_heaven_open_map / heaven_log / heaven_report；次数与每日奖励为 member_activity 的
-- freeheaven / heaventimes / heavendate，扫荡补买次数为 member_field.heaventimes；关卡 / 守军配置在 MasterData HeavenMission / HeavenMonster)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_heaven_open_map`;
CREATE TABLE `zc_sanguo_heaven_open_map` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '势力id',
    `stage` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '场景id',
    `mission` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已通过的最大关卡id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通天塔势力，任务开启记录';

DROP TABLE IF EXISTS `zc_sanguo_heaven_log`;
CREATE TABLE `zc_sanguo_heaven_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id（即战报 heaven_report.id）',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '玩家昵称',
    `missionlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '势力id',
    `missionid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关卡id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首杀时间',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首杀时用户等级',
    `tree` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神树活动时的神树等级',
    `first_reward` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首杀奖励领取标识（1 已领取，0 未领取）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid_mission` (`uid`, `missionid`),
    INDEX `idx_mission` (`missionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通天塔任务记录表';

DROP TABLE IF EXISTS `zc_sanguo_heaven_report`;
CREATE TABLE `zc_sanguo_heaven_report` (
    `id` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '战报id（heaven_log.id）',
    `report` TEXT NOT NULL COMMENT '战斗过程（json_encode(base64(gzcompress(战报 json)))）',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '玩家昵称',
    `userlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `name` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '关卡名称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关卡等级',
    `background` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗背景（旧版写战报时不写入）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通天塔战报表';

-- -------------------------------------------------------------
-- 英雄王座 (对齐原版 zc_sanguo_herothrone_log；关卡 / 守军 / 宝箱奖励配置在 MasterData HerothroneMission / HerothroneMonster)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_herothrone_log`;
CREATE TABLE `zc_sanguo_herothrone_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '用户名称',
    `times` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余免费次数',
    `buytimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余购买次数',
    `today_buytimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '今日购买次数',
    `today_buydate` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '今日最后购买时间',
    `stage` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前进度',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态（1已开启，2失败，0未开启）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后挑战时间（每日重置免费次数的判断依据）',
    `laststage` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '历史最高过关记录',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='英雄王座用户记录';

-- -------------------------------------------------------------
-- 金银岛 (对齐原版 zc_sanguo_island_log / island_report；进度与次数为 member_activity 的 islandid / islandtimes / islanddate；
-- 关卡 / 守军配置在 MasterData Island / IslandMonster)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_island_log`;
CREATE TABLE `zc_sanguo_island_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id（即战报 island_report.id）',
    `islandid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '金银岛id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '昵称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首胜时间',
    `tree` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神树活动中的神树等级',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid_island` (`uid`, `islandid`),
    INDEX `idx_island` (`islandid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='金银岛记录表';

DROP TABLE IF EXISTS `zc_sanguo_island_report`;
CREATE TABLE `zc_sanguo_island_report` (
    `id` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '战报id（island_log.id）',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '昵称',
    `userlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `name` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '怪物名称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关卡等级（monsterlevel）',
    `report` MEDIUMTEXT NOT NULL COMMENT '战斗过程（战报 json，旧版未压缩）',
    `tree` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神树活动中的神树等级（旧版写战报时不写入）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='金银岛攻略表';

-- -------------------------------------------------------------
-- 群雄逐鹿 (对齐原版 zc_sanguo_compete / compete_action_log；活动时间在 MasterData ActivityTime（compete），
-- 名次奖励在 MasterData CompeteReward / NewcompeteReward)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_compete`;
CREATE TABLE `zc_sanguo_compete` (
    `uid` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '用户uid',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '玩家名字（入榜时的昵称）',
    `rank` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排名',
    `times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余次数',
    `get_rank` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取奖励时排名（0 为未领取）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后更新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_rank` (`rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户逐鹿数据表';

DROP TABLE IF EXISTS `zc_sanguo_compete_action_log`;
CREATE TABLE `zc_sanguo_compete_action_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '流水号',
    `uid` INT UNSIGNED NOT NULL COMMENT '参加活动的用户uid',
    `level` INT UNSIGNED NOT NULL COMMENT '等级',
    `dateline` INT UNSIGNED NOT NULL COMMENT '参加活动的时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='逐鹿活动参加记录';

-- -------------------------------------------------------------
-- 国家矿脉 (对齐原版 zc_sanguo_country_mine；矿系数 / 随机事件在 MasterData CountryMineRatio / CountryMineEvent；
-- 占矿到期时间为 member_field.oncountrymine)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_country_mine`;
CREATE TABLE `zc_sanguo_country_mine` (
    `uid` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '用户uid',
    `times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '占领剩余次数',
    `robtimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '抢劫剩余次数',
    `page` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第几页',
    `site` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第几个位置',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '矿的类型（CountryMineRatio.id）',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '占矿时等级',
    `jointime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '占领矿区时间',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '每日进入矿区时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_page_site` (`page`, `site`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='矿战记录表';

DROP TABLE IF EXISTS `zc_sanguo_country_fight_notice`;
CREATE TABLE `zc_sanguo_country_fight_notice` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主键ID',
    `type` INT NOT NULL DEFAULT 0 COMMENT '战报类型 0，国家抢矿；1，海外贸易；',
    `member1` BIGINT NOT NULL DEFAULT 0 COMMENT '角色1UID',
    `member1_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '角色1名称',
    `member_country` SMALLINT NOT NULL DEFAULT 0 COMMENT '角色1国家ID',
    `member_country_name` VARCHAR(21) NOT NULL DEFAULT '' COMMENT '角色1国家名称',
    `member2` BIGINT NOT NULL DEFAULT 0 COMMENT '角色2ID',
    `member2_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '角色2名称',
    `attack_uid` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻击者UID',
    `attack_nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '攻击者用户名',
    `attack_country` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻击者国家ID',
    `attack_country_name` VARCHAR(21) NOT NULL DEFAULT '' COMMENT '攻击值国家名称',
    `win` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否成功（1，成功；0，失败；）',
    `dateline` INT NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_attack_country` (`attack_country`),
    INDEX `idx_member_country` (`member_country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家抢矿、海外贸易日志';

-- -------------------------------------------------------------
-- 国战 / 都城 (对齐原版 zc_sanguo_country_city / country_city_report / country_siege_log；
-- 都城配置在 MasterData CountryCityEffect，NPC 守将在 MasterData CountryMission / CountryMonster)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_country_city`;
CREATE TABLE `zc_sanguo_country_city` (
    `maplevel` TINYINT UNSIGNED NOT NULL PRIMARY KEY COMMENT '都城id（CountryCityEffect.id）',
    `challengercid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '挑战者国家id',
    `challengername` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '挑战者国家名字',
    `challengerlevel` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '挑战方等级',
    `challengerdateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '挑战时间',
    `cid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '占领的国家id',
    `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '国家名称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '占领者国家等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '占领时间',
    `lock` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否被国家通过至尊占领锁定',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_cid` (`cid`),
    INDEX `idx_challengercid` (`challengercid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='都城占领表';

-- 原版 report 为 TEXT：整场团战战报 json 常超过 64KB，改为 MEDIUMTEXT
DROP TABLE IF EXISTS `zc_sanguo_country_city_report`;
CREATE TABLE `zc_sanguo_country_city_report` (
    `id` MEDIUMINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '战报id',
    `city` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '城池id',
    `report` MEDIUMTEXT NOT NULL COMMENT '战报内容（json：双方名单、团战过程 battle、杀敌榜）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_city_dateline` (`city`, `dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国战战报表';

DROP TABLE IF EXISTS `zc_sanguo_country_siege_log`;
CREATE TABLE `zc_sanguo_country_siege_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主键ID',
    `city` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '城池ID',
    `city_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '城池名称',
    `report` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战报ID',
    `country` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '被攻击方国家ID',
    `country_name` VARCHAR(21) NOT NULL DEFAULT '' COMMENT '被攻击方国家名称',
    `attack_country` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻击方国家ID',
    `attack_country_name` VARCHAR(21) NOT NULL DEFAULT '' COMMENT '攻击方国家名称',
    `zhizun` INT NOT NULL DEFAULT 0 COMMENT '是否是至尊攻城（0，不是；1，是；）',
    `win_type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战胜or失败（1，战胜；0，失败；）',
    `dateline` INT NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家国战日志表';

-- -------------------------------------------------------------
-- 玩家物品存储 (对齐原版各物品表与流水表；member 上的玉石 / 宝石碎片 / 宝石格子 / 三种石头 / 将魂包裹见 zc_sanguo_member，
-- 装备碎片 / 天赋卷轴及碎片 / 装备精华同旧版存 zc_sanguo_equipment（旧版 own_equip），神器碎片见 zc_sanguo_member_country.debris)
-- -------------------------------------------------------------

-- 酒馆宝石与商贾会客（旧版 tavern_model add_jade / sub_jade；Model\TraderLog）
DROP TABLE IF EXISTS `zc_sanguo_trader_log`;
CREATE TABLE `zc_sanguo_trader_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `get1` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '蓝宝石数量',
    `get2` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '黄宝石数量',
    `get3` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '紫宝石数量',
    `site` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前位置',
    `site1` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '位置1开启状态',
    `site2` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置2开启状态',
    `site3` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置3开启状态',
    `site4` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置4开启状态',
    `site5` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '位置5开启状态',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后交易时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='黑市交易表';

DROP TABLE IF EXISTS `zc_sanguo_tavernjade_log`;
CREATE TABLE `zc_sanguo_tavernjade_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家uid',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `jadetype` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玉石类型1：蓝宝石 2：金宝石 3：紫宝石',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '记录类型1：获得 2：花费',
    `jade` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作玉石数',
    `balance` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '该类型玉石剩余数',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `argument` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `vip` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='酒馆宝石变更记录';

-- 玉石流水（旧版 member_model add_jade / sub_jade；Model\MemberJade）
DROP TABLE IF EXISTS `zc_sanguo_jade_log`;
CREATE TABLE `zc_sanguo_jade_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家uid',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '记录类型1：获得 2：花费',
    `jade` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作玉石数',
    `balance` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玉石余额',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `argument` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `vip` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='玉石消耗记录表';

-- 精炼石 / 五彩石 / 上古血玉流水（旧版 member_model add_stone / sub_stone；Model\MemberStone）
DROP TABLE IF EXISTS `zc_sanguo_stone_log`;
CREATE TABLE `zc_sanguo_stone_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主键ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家UID',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `vip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家VIP等级',
    `stone_type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '宝石类型（1，精炼石；2，五彩宝石；3，上古血石；）',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '增加or减少（1，增加；2，减少；）',
    `stone` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作宝石数量',
    `balance` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余宝石数量',
    `class` VARCHAR(60) NOT NULL DEFAULT '' COMMENT '操作控制器',
    `method` VARCHAR(60) NOT NULL DEFAULT '' COMMENT '操作方法',
    `argument` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '传递参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='精炼石、五彩石、上古血石操作日志';

-- 宝石（旧版 gem_model add_gem / del_gem / adron / pack_off_by_eid；Model\OwnGem）
DROP TABLE IF EXISTS `zc_sanguo_own_gem`;
CREATE TABLE `zc_sanguo_own_gem` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '拥有宝石id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户UID',
    `gemid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '宝石模版id（MasterData Gem）',
    `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '宝石名称',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '加成属性（1物理攻击2物理防御3策略攻击4策略防御）',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `power` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '属性值',
    `eid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '道具id',
    `site` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '装配的位置（1~8）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获得时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid_eid` (`uid`, `eid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='拥有宝石表';

-- 宝石碎片流水（旧版 member_model add_gem / sub_gem 的 member.gem；Model\OwnGem）
DROP TABLE IF EXISTS `zc_sanguo_gem_log`;
CREATE TABLE `zc_sanguo_gem_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户等级',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1获得，2消耗',
    `gem` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作宝石碎片数',
    `balance` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余宝石碎片',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `argument` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `vip` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='宝石碎片消耗记录表';

-- 装备碎片 / 天赋卷轴碎片流水（旧版 equipment_model add_item 碎片分支 / add_talent_scroll 碎片分支；Model\Equipment）
DROP TABLE IF EXISTS `zc_sanguo_itemchip_log`;
CREATE TABLE `zc_sanguo_itemchip_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1获得，2消耗',
    `itemname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '道具名称',
    `itemid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '道具模版id',
    `itemtype` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '碎片类型:1 猛将武器、2 谋士武器、3 铠甲、4 披风、5 兵符、6物攻卷轴、7物防、8策攻、9策防、10生命',
    `quality` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '装备品质（1 白、2 绿、3 蓝、4 金、5 紫、6红)',
    `num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作个数',
    `blance` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余数量',
    `class` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '执行的类',
    `method` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '执行的方法',
    `argument` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='装备碎片记录表';

-- 天赋卷轴流水（旧版 equipment_model add_talent_scroll 卷轴分支 / sub_talent_scroll；Model\Equipment）
DROP TABLE IF EXISTS `zc_sanguo_talent_scroll_log`;
CREATE TABLE `zc_sanguo_talent_scroll_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '记录类型1：获得 2：花费',
    `scrolltype` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '卷轴类型',
    `quality` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '品级',
    `scroll` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作卷轴数',
    `balance` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余卷轴',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `argument` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `vip` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='天赋卷轴记录';

-- 坐骑（旧版 mount_model add_mount / disable_mount_by_id / adorn / take_down_all；Model\OwnMount）
DROP TABLE IF EXISTS `zc_sanguo_own_mount`;
CREATE TABLE `zc_sanguo_own_mount` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '拥有坐骑id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属用户id',
    `gid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '带领坐骑的将领id',
    `mid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '坐骑模版id（MasterData MountTemp）',
    `gender` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属职业',
    `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '坐骑名称',
    `quality` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '坐骑品级',
    `level` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '坐骑等级',
    `exp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前经验',
    `levelup_exp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '升级经验',
    `attack` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻击',
    `wufang` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '物理防御',
    `cefang` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '策略防御',
    `hp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '生命',
    `toexp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '可吃经验',
    `chip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '坐骑碎片数量（大于 0 为碎片行）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获得坐骑时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`),
    INDEX `idx_gid` (`gid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='拥有坐骑表';

DROP TABLE IF EXISTS `zc_sanguo_mountchip_log`;
CREATE TABLE `zc_sanguo_mountchip_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1获得，2消耗',
    `mname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '坐骑名称',
    `mid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '坐骑id',
    `quality` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '装备品质（1 白、2 绿、3 蓝、4 金、5 紫、6红)',
    `num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作个数',
    `blance` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余数量',
    `class` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '执行的类',
    `method` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '执行的方法',
    `argument` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='坐骑碎片记录表';

DROP TABLE IF EXISTS `zc_sanguo_mount_info_log`;
CREATE TABLE `zc_sanguo_mount_info_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `vip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户vip等级',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户等级',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '类型 1 添加坐骑 2删除坐骑',
    `mid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '坐骑id',
    `mname` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '坐骑名称',
    `mlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '坐骑等级',
    `quality` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '坐骑品级（1白 2绿 3蓝 4金 5紫 6红 7橙）',
    `exp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '增加经验',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `argument` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户坐骑log表';

-- 将魂（旧版 soul_model add_hero_soul / del_soul；包裹格数与占用在 member.soulpack / souluse；Model\OwnSoul）
-- 原版 power 为 TINYINT UNSIGNED：将魂属性表 soul_type 最高 400，超出 255，改为 SMALLINT UNSIGNED
DROP TABLE IF EXISTS `zc_sanguo_own_soul`;
CREATE TABLE `zc_sanguo_own_soul` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `gid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领id',
    `name` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '将魂名称',
    `image` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '图片id',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '类型(1-8)',
    `quality` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '品质(1-5)白绿蓝金紫',
    `level` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `power` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '属性',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_quality` (`quality`, `level`),
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='拥有将魂表';

-- 宝石合成日志（旧版 gem_model add_gem_mergelog；c=gem&m=merge_gem 写入）
DROP TABLE IF EXISTS `zc_sanguo_own_gem_mergelog`;
CREATE TABLE `zc_sanguo_own_gem_mergelog` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL,
    `gemid1` INT UNSIGNED NOT NULL,
    `gemid2` INT UNSIGNED NOT NULL,
    `new_gemid` INT UNSIGNED NOT NULL,
    `dateline` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 西域马场首次选马记录（旧版 mount_model get_first_status / update_first_status；c=studfarm&m=action）
DROP TABLE IF EXISTS `zc_sanguo_mount_log`;
CREATE TABLE `zc_sanguo_mount_log` (
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `first1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首次十里挑一时间',
    `first2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首次百里挑一时间',
    `first3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首次万里挑一时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='西域马场用户记录表';

-- 坐骑获得经验记录（旧版 mount_model add_exp_log；c=mount&m=levelup）
DROP TABLE IF EXISTS `zc_sanguo_mount_exp_log`;
CREATE TABLE `zc_sanguo_mount_exp_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `mid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '升级的坐骑id',
    `exp_mid` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '搭配升级的坐骑ID列表',
    `add_exp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '添加的经验',
    `now_exp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '添加后的剩余经验',
    `now_level` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获得经验后等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '添加经验时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='坐骑获得经验记录表';

-- 神器碎片流水（旧版 country_model add_debris / sub_debris 的 member_country.debris；Model\CountryDebris）
DROP TABLE IF EXISTS `zc_sanguo_debris_log`;
CREATE TABLE `zc_sanguo_debris_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1:增加碎片、2:减少碎片',
    `add_reduce` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '减少或增加的碎片',
    `debris` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '碎片数量',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='碎片记录表';

-- -------------------------------------------------------------
-- 养成：将魂合成与原魂流水、主公星魄 / 命格、主公天赋
-- （旧版 soul_model add_soul_mergelog、member_model add_soul / sub_soul / _soul_log、star_model、talent_model；
--  Model\OwnSoul / OwnStar / HeroTalent，c=soul / star / talent）
-- -------------------------------------------------------------

-- 将魂合成日志（soul_model add_soul_mergelog；new_sid 同旧版记新将魂的模板 id）
DROP TABLE IF EXISTS `zc_sanguo_own_soul_mergelog`;
CREATE TABLE `zc_sanguo_own_soul_mergelog` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色UID',
    `sid1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '合成将魂ID1',
    `sid2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '合成将魂ID2',
    `new_sid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '新合成将魂ID',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '合成时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='将魂合成日志表';

-- 原魂流水（member_model _soul_log；member.soul 的增减）
DROP TABLE IF EXISTS `zc_sanguo_usesoul_log`;
CREATE TABLE `zc_sanguo_usesoul_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '记录类型1：获得 2：花费',
    `reputation` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作原魂数',
    `balance` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余原魂',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `argument` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `vip` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='原魂消耗记录表';

-- 主公星魄（star_model init_star / get_own_star / update_own_star）
DROP TABLE IF EXISTS `zc_sanguo_own_star`;
CREATE TABLE `zc_sanguo_own_star` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `star1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '幽蓝星魄数量',
    `star2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '铭黄星魄数量',
    `star3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '紫阳星魄数量',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首次操作时间',
    `lastsite` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '最后选择的炼化炉',
    `lastlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后升级的命格',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='拥有星魄';

-- 主公命格树（star_model init_star_tree / update_star_tree / get_star_tree）
DROP TABLE IF EXISTS `zc_sanguo_star_tree`;
CREATE TABLE `zc_sanguo_star_tree` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `starid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '命格id',
    `level` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_starid` (`uid`, `starid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户命格树';

-- 星魄流水（star_model add_star_log）
DROP TABLE IF EXISTS `zc_sanguo_star_log`;
CREATE TABLE `zc_sanguo_star_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `startype` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '星魄类型',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗获得（1 获得 2 扣除）',
    `star` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '星魄数量',
    `balance` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '余额',
    `class` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '执行方法',
    `argument` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0,
    `treeid` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '星魄树id',
    `level` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '升级时的等级',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户星魄log';

-- 主公命格加成（star_model get_star_addition / update_star_addition；战斗与将领界面读取）
DROP TABLE IF EXISTS `zc_sanguo_own_star_addition`;
CREATE TABLE `zc_sanguo_own_star_addition` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `wuli` INT UNSIGNED NOT NULL DEFAULT 0,
    `zhili` INT UNSIGNED NOT NULL DEFAULT 0,
    `tili` INT UNSIGNED NOT NULL DEFAULT 0,
    `attack` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '物攻',
    `defense` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '物防',
    `pattack` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '策攻',
    `pdefense` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '策防',
    `blood` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '血',
    `dodge` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '闪避',
    `crit` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '暴击率',
    `penetrate` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '识破',
    `harm` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '暴击伤害',
    `power` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '士气',
    `mingzhong` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '命中',
    `jingzhun` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '精准',
    `kangyun` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '抗晕',
    `kcrit` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '抗暴击率',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='主公命格加成信息表';

-- 主公天赋（talent_model；type 1 物攻 / 2 物防 / 3 策攻 / 4 策防 / 5 生命）
DROP TABLE IF EXISTS `zc_sanguo_own_hero_talent`;
CREATE TABLE `zc_sanguo_own_hero_talent` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '类型(1物攻;2物防;3策攻;4策防;5生命)',
    `quality` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '品级',
    `star` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '星级',
    `power` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '增加值',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_type` (`uid`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='主公拥有天赋表';

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

-- -------------------------------------------------------------
-- 养成：名将进阶（旧版 models/advanced_model.php；Model\Advanced，c=advanced）、
-- 装备精炼（旧版 models/refine_model.php；Model\Refine，c=refine；精炼等级为 equipment.hammer）、
-- 名将谱 / 名将成就（旧版 models/general_model.php 的 book / own_achievement / own_addition；Model\GeneralBook，c=general_book）
-- 进阶传承 CD 为 member_field.advanced_time
-- -------------------------------------------------------------

-- 将领进阶（advanced_log：品级、四个被动技能的等级 / 名称 / 加成、进阶累计的三围与血量；旧版主键 uid + gid）
DROP TABLE IF EXISTS `zc_sanguo_advanced_log`;
CREATE TABLE `zc_sanguo_advanced_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户Uid',
    `gid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领Id（general.id）',
    `quality` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前品级',
    `skill1_level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '技能1等级',
    `skill2_level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '技能2等级',
    `skill3_level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '技能3等级',
    `skill4_level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '技能4等级',
    `skill1_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '技能1名称',
    `skill2_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '技能2名称',
    `skill3_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '技能3名称',
    `skill4_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '技能4名称',
    `skill1_value` DOUBLE UNSIGNED NOT NULL DEFAULT 0 COMMENT '技能1加成属性（物攻 / 策攻天赋系数）',
    `skill2_value` DOUBLE UNSIGNED NOT NULL DEFAULT 0 COMMENT '技能2加成属性（物防天赋系数）',
    `skill3_value` DOUBLE UNSIGNED NOT NULL DEFAULT 0 COMMENT '技能3加成属性（策防天赋系数）',
    `skill4_value` DOUBLE UNSIGNED NOT NULL DEFAULT 0 COMMENT '技能4加成属性（生命天赋系数）',
    `wuli` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武力加成值',
    `zhili` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '智力加成值',
    `tili` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '体力加成值',
    `hp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '生命加成值',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_gid` (`uid`, `gid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='武将进阶用户数据表';

-- 进阶传承记录（advanced_inherit_log：传承前两个将领的进阶数据）
DROP TABLE IF EXISTS `zc_sanguo_advanced_inherit_log`;
CREATE TABLE `zc_sanguo_advanced_inherit_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `gid1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领1id（被传承）',
    `g1_quality` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将1品级',
    `g1_level1` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领1技能1等级',
    `g1_level2` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领1技能2等级',
    `g1_level3` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领1技能3等级',
    `g1_level4` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领1技能4等级',
    `g1_value1` DOUBLE UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领1技能1属性',
    `g1_value2` DOUBLE UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领1技能2属性',
    `g1_value3` DOUBLE UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领1技能3属性',
    `g1_value4` DOUBLE UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领1技能4属性',
    `g1_wuli` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领1武力',
    `g1_zhili` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领1智力',
    `g1_tili` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领1体力',
    `g1_hp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领1生命',
    `gid2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领2id（受益）',
    `g2_quality` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将2品级',
    `g2_level1` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领2技能1等级',
    `g2_level2` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领2技能2等级',
    `g2_level3` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领2技能3等级',
    `g2_level4` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领2技能4等级',
    `g2_value1` DOUBLE UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领2技能1属性',
    `g2_value2` DOUBLE UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领2技能2属性',
    `g2_value3` DOUBLE UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领2技能3属性',
    `g2_value4` DOUBLE UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领2技能4属性',
    `g2_wuli` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领2武力',
    `g2_zhili` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领2智力',
    `g2_tili` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领2体力',
    `g2_hp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领2生命',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '进阶传承时间',
    `disable` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '衡量用户是否已恢复过进阶传承（后台恢复用，客户端不涉及）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='进阶传承记录表';

-- 玩家当前的精炼大师（refine_master_log：每人一行，dateline 为最后刷新时间）
DROP TABLE IF EXISTS `zc_sanguo_refine_master_log`;
CREATE TABLE `zc_sanguo_refine_master_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `masterid` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '精炼大师id（RefineMaster）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '刷新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='精炼大师log表';

-- 装备精炼日志（refine_log）
DROP TABLE IF EXISTS `zc_sanguo_refine_log`;
CREATE TABLE `zc_sanguo_refine_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `eid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '精炼装备ID（equipment.id）',
    `mid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '大师ID',
    `now_level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '精炼当前等级',
    `new_level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '精炼最终等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='装备精炼日志';

-- 精炼继承日志（refine_inherit_log：id1 的精炼等级移到 id2，防玩家要求回档）
DROP TABLE IF EXISTS `zc_sanguo_refine_inherit_log`;
CREATE TABLE `zc_sanguo_refine_inherit_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'UID',
    `id1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '被继承装备id',
    `id2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '继承装备id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '日期',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='精炼继承log';

-- 拥有的名将成就（own_achievement：bid 为 BookAchievement.id，quality 1 ~ 6；旧版主键 uid + bid）
DROP TABLE IF EXISTS `zc_sanguo_own_achievement`;
CREATE TABLE `zc_sanguo_own_achievement` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `bid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成就id',
    `quality` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '品级',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_bid` (`uid`, `bid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='拥有成就表';

-- 名将谱 / 名将成就属性加成（own_addition：每人一行；战斗与将领界面读取，主公 81 级起生效）
-- 旧版列宽较窄（smallint / tinyint，非严格模式下溢出截断），新版统一 INT UNSIGNED；旧版的垃圾列 asdasda 不建
DROP TABLE IF EXISTS `zc_sanguo_own_addition`;
CREATE TABLE `zc_sanguo_own_addition` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `wuli` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武力',
    `zhili` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '智力',
    `tili` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '体力',
    `attack` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '物攻',
    `defense` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '物防',
    `pattack` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '策攻',
    `pdefense` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '策防',
    `blood` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '血量',
    `dodge` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '闪避',
    `crit` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '暴击',
    `penetrate` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '识破',
    `power` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '初始士气',
    `mingzhong` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '命中',
    `jingzhun` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '精准',
    `kcrit` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '抗暴击率',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='图谱成就属性加成表';

-- 名将成就升级记录（own_achievement_log）
DROP TABLE IF EXISTS `zc_sanguo_own_achievement_log`;
CREATE TABLE `zc_sanguo_own_achievement_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `bid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成就id',
    `quality1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '升级前品级',
    `quality2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '升级后品级',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '花费类型（1蓝 2黄 3紫）',
    `jade` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '宝石数目',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='拥有成就记录表';

-- -------------------------------------------------------------
-- 充值与 VIP (对齐原版 alipay_order 等各渠道成功订单表（合并为 pay_order）/ pay_log / vip_log / recharge_list_log /
-- newyear_vipreward_log / jump_rebate / jump_rebate_order；商品 / VIP 规则 / 礼包 / 工资 / 回馈配置在 MasterData
-- AlipayProduct / Vip / VipReward / VipWage / NewyearVipreward)
-- -------------------------------------------------------------
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

-- -------------------------------------------------------------
-- 每日玩法（daily2：酒馆 / 对酒 / 国宴 / 谏言 / 神将之门 / 国家学院 / 培养 / 继承 / 校场）
-- -------------------------------------------------------------
-- 每日对酒记录（drink_model：drink_log；对酒配置在 MasterData Drink）
DROP TABLE IF EXISTS `zc_sanguo_drink_log`;
CREATE TABLE `zc_sanguo_drink_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '昵称',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '对酒时间',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '对酒类型',
    `act` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '军令',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='对酒记录';

-- 国宴记录（banquet_model：country_banquet_log；宴会配置在 MasterData CountryBanquet，队伍存 Redis banquet_team_{国家}_{队长}）
DROP TABLE IF EXISTS `zc_sanguo_country_banquet_log`;
CREATE TABLE `zc_sanguo_country_banquet_log` (
    `uid` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '用户uid',
    `times` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余次数',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态 1已加入，2已解散，3已完成',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国宴类型（1小型，2大型，3国宴）',
    `team` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已加入的队伍id（队长uid）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家国宴记录表';

-- 国家谏言（expostulation_model：expostulation / expostulation_support；谏言模版在 MasterData ExpostulationTemp）
DROP TABLE IF EXISTS `zc_sanguo_expostulation`;
CREATE TABLE `zc_sanguo_expostulation` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `cid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属国家id',
    `eid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '谏言的模版id',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '谏言的类型',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '谏言的用户uid',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '谏言的用户名',
    `gender` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色性别',
    `job` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '谏言者职位',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态（0待审核，1已通过，2已完成，3奖励已领完）',
    `plan` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '任务进度',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '谏言时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_cid_status` (`cid`, `status`),
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户谏言表';

DROP TABLE IF EXISTS `zc_sanguo_expostulation_support`;
CREATE TABLE `zc_sanguo_expostulation_support` (
    `eid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '通过的谏言记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '该任务领取状态（0未领取，1已领取）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`eid`, `uid`),
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户支持的谏言列表';

-- 神将之门（generaltask_model：generaltask_log / generaltask_mission_log / generaltask_report；关卡与怪物在 MasterData
-- GeneraltaskMission / GeneraltaskMonster）
DROP TABLE IF EXISTS `zc_sanguo_generaltask_log`;
CREATE TABLE `zc_sanguo_generaltask_log` (
    `uid` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '用户id',
    `stage` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前进度',
    `buytimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '今日购买次数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '修改记录时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='神将之门记录表';

DROP TABLE IF EXISTS `zc_sanguo_generaltask_mission_log`;
CREATE TABLE `zc_sanguo_generaltask_mission_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `missionid` INT UNSIGNED NOT NULL DEFAULT 0,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户uid',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '用户昵称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0,
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0,
    `first_reward` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首杀标识',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_mission_uid` (`missionid`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='神将之门关卡通过记录表';

DROP TABLE IF EXISTS `zc_sanguo_generaltask_report`;
CREATE TABLE `zc_sanguo_generaltask_report` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '战报id',
    `missionid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关卡id',
    `report` MEDIUMTEXT NOT NULL COMMENT '战斗过程',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '攻方昵称',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `name` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '守方昵称',
    `tree` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神树活动中的神树等级',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_missionid` (`missionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='神将之门战报表';

-- 国家学院（warcollege_model / warcollege_use_model；技能配置在 MasterData WarCollegeSkill）
DROP TABLE IF EXISTS `zc_sanguo_war_college_skill_info`;
CREATE TABLE `zc_sanguo_war_college_skill_info` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增',
    `country_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家id',
    `img_id` INT NOT NULL DEFAULT 0 COMMENT '图片id',
    `skill_name` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '技能名称',
    `skill_type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '技能类型',
    `skill_level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '技能等级',
    `skill_value` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '技能效果',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_country_type` (`country_id`, `skill_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家学院技能信息表';

DROP TABLE IF EXISTS `zc_sanguo_war_college_gold_info`;
CREATE TABLE `zc_sanguo_war_college_gold_info` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增',
    `country_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家id',
    `country_gold` DECIMAL(21,1) UNSIGNED NOT NULL DEFAULT 0.0 COMMENT '国家税金',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_country_id` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家学院税金表';

DROP TABLE IF EXISTS `zc_sanguo_war_college_gold_info_log`;
CREATE TABLE `zc_sanguo_war_college_gold_info_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增',
    `country_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家id（旧版增减记录不写，恒 0）',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '增加方式 1初始化 2增加 3消费',
    `country_gold` DECIMAL(21,1) UNSIGNED NOT NULL DEFAULT 0.0 COMMENT '国家税金',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家学院税金log表';

DROP TABLE IF EXISTS `zc_sanguo_war_college_level_up_log`;
CREATE TABLE `zc_sanguo_war_college_level_up_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增',
    `country_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家id',
    `skill_type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '技能id',
    `skill_level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '技能等级',
    `country_gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗税金',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消耗元宝',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家学院升级技能log表';

DROP TABLE IF EXISTS `zc_sanguo_warcollege_use`;
CREATE TABLE `zc_sanguo_warcollege_use` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `countryid` INT NOT NULL DEFAULT 0 COMMENT '国家id（旧版不写，恒 0）',
    `buffendtime` INT NOT NULL DEFAULT 0 COMMENT 'buff 结束时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户使用国家buff记录表';

-- 武将培养记录（general_model->add_cultivate_log）
DROP TABLE IF EXISTS `zc_sanguo_cultivate_log`;
CREATE TABLE `zc_sanguo_cultivate_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 普通 2 加强 3 白金 4 至尊',
    `gid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将id',
    `level` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将等级',
    `wuliup1` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '培养前武力',
    `zhiliup1` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '培养前智力',
    `tiliup1` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '培养前体力',
    `wuliup2` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '培养后武力',
    `zhiliup2` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '培养后智力',
    `tiliup2` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '培养后体力',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `hfstatus` BIGINT NOT NULL DEFAULT 0 COMMENT '后台恢复时间（后台功能，不移植）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='武将培养记录表';

-- 武将继承（general_model：inherit_times / inherit_log；继承规则在 MasterData GeneralInherit）
DROP TABLE IF EXISTS `zc_sanguo_inherit_times`;
CREATE TABLE `zc_sanguo_inherit_times` (
    `uid` INT UNSIGNED NOT NULL PRIMARY KEY COMMENT '用户uid',
    `inherit_1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '普通继承次数',
    `inherit_2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '加强继承次数',
    `inherit_3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '白金继承次数',
    `inherit_4` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '至尊继承次数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户拥有继承次数表';

DROP TABLE IF EXISTS `zc_sanguo_inherit_log`;
CREATE TABLE `zc_sanguo_inherit_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `vip` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip等级',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '继承类型（1~4）',
    `cost` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '耗费元宝',
    `addexp` BIGINT NOT NULL DEFAULT 0 COMMENT '经验（负数为消耗）',
    `gid1` INT UNSIGNED NOT NULL DEFAULT 0,
    `g1_turn1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '转数',
    `g1_level1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `g1_exp1` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '经验',
    `g1_levelupexp1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '升级所需经验',
    `g1_wuliup1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武力',
    `g1_zhiliup1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '智力',
    `g1_tiliup1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '体力',
    `g1_turn2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '转数',
    `g1_level2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `g1_exp2` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '经验',
    `g1_levelupexp2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '升级所需经验',
    `g1_wuliup2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武力',
    `g1_zhiliup2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '智力',
    `g1_tiliup2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '体力',
    `gid2` INT UNSIGNED NOT NULL DEFAULT 0,
    `g2_turn1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '转数',
    `g2_level1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `g2_exp1` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '经验',
    `g2_levelupexp1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '升级所需经验',
    `g2_wuliup1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武力',
    `g2_zhiliup1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '智力',
    `g2_tiliup1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '体力',
    `g2_turn2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '转数',
    `g2_level2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级',
    `g2_exp2` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '经验',
    `g2_levelupexp2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '升级所需经验',
    `g2_wuliup2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武力',
    `g2_zhiliup2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '智力',
    `g2_tiliup2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '体力',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0,
    `disable` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '衡量用户是否已恢复武将继承（后台功能，不移植）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='武将继承记录';

-- 突飞卡流水（tavern_model->_leap_log）与武将突飞记录（general_model->add_leap_log）
DROP TABLE IF EXISTS `zc_sanguo_leap_log`;
CREATE TABLE `zc_sanguo_leap_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '记录类型1：获得 2：花费',
    `leaptimes` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作突飞数',
    `alltimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余免费突飞数',
    `class` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行类',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '执行方法',
    `argument` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '参数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作时间',
    `vip` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vip',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='突飞卡记录';

DROP TABLE IF EXISTS `zc_sanguo_general_leap_log`;
CREATE TABLE `zc_sanguo_general_leap_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `vip` INT UNSIGNED NOT NULL DEFAULT 0,
    `level` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `addexp` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '获得经验',
    `hit` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否暴击',
    `gid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将id',
    `turn` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `glevel1` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '突飞前武将等级',
    `gexp1` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '突飞前经验',
    `glevelupexp1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '突飞前升级所需经验',
    `glevel2` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '突飞后武将等级',
    `gexp2` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '突飞后武将经验',
    `glevelupexp2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '突飞后升级所需经验',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '突飞时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='武将突飞记录';

-- 新手引导记录（guide_model：guide_log；周仓前 5 次突飞免费）
DROP TABLE IF EXISTS `zc_sanguo_guide_log`;
CREATE TABLE `zc_sanguo_guide_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `leaptimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '突飞次数',
    `clearcd` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '清除战斗cd(免费1次)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='新手引导记录';

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

-- =============================================================
-- 跨服第一批（20261104_cross1.sql）：跨服演武 / 战神殿的本服表，进程内 uc / battle 的表
-- =============================================================

-- 跨服远征玩家信息（member_model：member_world，功勋 / 免费与购买挑战次数 / 押注时间，主键同旧版 uid）
DROP TABLE IF EXISTS `zc_sanguo_member_world`;
CREATE TABLE `zc_sanguo_member_world` (
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `exploit` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '功勋',
    `get_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '功勋领取时间',
    `freetimes` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '跨服战免费次数',
    `buytimes` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '跨服战次数',
    `bettime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '押注时间',
    `get_bet` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取押注奖励时间',
    `freedateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '刷新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='跨服远征玩家信息';

-- 跨服远征押注记录（worldarena_model：world_bet_log；旧版无主键，新版加自增 id）
DROP TABLE IF EXISTS `zc_sanguo_world_bet_log`;
CREATE TABLE `zc_sanguo_world_bet_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `touid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '押注目标uid',
    `serverid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '服务器id',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '押注对象昵称',
    `rank` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排名',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '押注时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='跨服远征押注记录';

-- 全服奖励发放表（worldarena_model：world_reward，使用跨服商城物品时写入，61 级以上玩家各领一次 add）
DROP TABLE IF EXISTS `zc_sanguo_world_reward`;
CREATE TABLE `zc_sanguo_world_reward` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '发放者uid',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '发放者',
    `itemid` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '物品id',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '加成类型(1银币;2蓝宝石;3金宝石;4紫宝石;5军令;6蓝色突飞卡;7金色突飞卡;8紫色突飞卡)',
    `add` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '增加值',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '发放时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='跨服奖励发放表';

-- 全服奖励领取记录（worldarena_model：world_reward_log，主键同旧版 uid + rewardid）
DROP TABLE IF EXISTS `zc_sanguo_world_reward_log`;
CREATE TABLE `zc_sanguo_world_reward_log` (
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `rewardid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励id',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`, `rewardid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='跨服奖励领取记录';

-- 本服每日演武榜前三（worldarena_model：world_top_log，跨服远征资格名单，主键同旧版 uid + ymd）
DROP TABLE IF EXISTS `zc_sanguo_world_top_log`;
CREATE TABLE `zc_sanguo_world_top_log` (
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `ymd` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '日期(20121111)',
    `serverid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '服务器id',
    `rank` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排名',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '昵称',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`, `ymd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='本服每日前三记录';

-- 战神殿奖励领取记录（god_model：god_reward_log）
DROP TABLE IF EXISTS `zc_sanguo_god_reward_log`;
CREATE TABLE `zc_sanguo_god_reward_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phase` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '期数（年月）',
    `rewardid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励id',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励类型（1 参战； 2 支持）',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '元宝',
    `silver` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '银币',
    `jade` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玉石',
    `tavernjade` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '紫宝石',
    `soul` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '原魂',
    `equip` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '装备',
    `gem` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '宝石碎片',
    `reputation` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '声望',
    `mount` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '坐骑',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取奖励时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_phase_uid` (`phase`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='战神殿奖励记录';

-- 本服战帝信息（god_model->get_place_info 读取；旧版由 cron_acttask::setplace_info 写入）
DROP TABLE IF EXISTS `zc_sanguo_god_palace`;
CREATE TABLE `zc_sanguo_god_palace` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    `phase` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第几期',
    `chapter` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第几世战帝',
    `name` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '帝号',
    `serverid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所在服务器id',
    `servername` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '服务器名称',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '用户昵称',
    `round` INT UNSIGNED NOT NULL DEFAULT 0,
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_phase` (`phase`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='战帝堂数据表';

-- =============================================================
-- 用户中心 uc（旧版 zc_sanguo_uc 库，进程内移植；表名前缀 zc_sanguo_uc_，按 serverid 分区）
-- =============================================================

-- 服务器列表（旧版 uc server 表；单服部署只有本服一行，id 即 config app.server_id）
DROP TABLE IF EXISTS `zc_sanguo_uc_server`;
CREATE TABLE `zc_sanguo_uc_server` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '区名',
    `servergroup` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '服务器分组',
    `opentime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '分区可看到时间',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态 0关 1开',
    `starttime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '可进入游戏时间（开服时间）',
    `endtime` INT UNSIGNED NOT NULL DEFAULT 0,
    `addr` VARCHAR(100) NOT NULL DEFAULT '',
    `newserver` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '合服后的serverid',
    `message` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '不可进入时的说明',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='uc 服务器列表';

-- 服务器分组（旧版 uc group 表）
DROP TABLE IF EXISTS `zc_sanguo_uc_group`;
CREATE TABLE `zc_sanguo_uc_group` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `groupid` INT UNSIGNED NOT NULL DEFAULT 0,
    `name` VARCHAR(50) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_groupid` (`groupid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='uc 服务器分组';

-- 英雄王座跨服排行（uc herothrone_model，按控制器与表结构还原：每个玩家一行历史最高关卡）
DROP TABLE IF EXISTS `zc_sanguo_uc_herothrone`;
CREATE TABLE `zc_sanguo_uc_herothrone` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `serverid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '服务器id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '昵称',
    `groupname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '服务器分组名',
    `stage` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '历史最高关卡',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_server_uid` (`serverid`, `uid`),
    KEY `idx_stage` (`stage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='uc 英雄王座排行';

-- 邀请码（uc invitation_model，按控制器与表结构还原：每个服务器每个玩家一个邀请码，number 为已邀请人数）
DROP TABLE IF EXISTS `zc_sanguo_uc_invitation_code`;
CREATE TABLE `zc_sanguo_uc_invitation_code` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `serverid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '服务器id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `code` VARCHAR(8) NOT NULL DEFAULT '' COMMENT '邀请码',
    `number` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已邀请人数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '生成时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_code` (`code`),
    UNIQUE KEY `uk_server_uid` (`serverid`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='uc 邀请码';

-- 邀请记录（uc invitation_model，按控制器与表结构还原：每个玩家只能被邀请一次）
DROP TABLE IF EXISTS `zc_sanguo_uc_invitation_log`;
CREATE TABLE `zc_sanguo_uc_invitation_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `serverid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '被邀请者服务器id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '被邀请者uid',
    `from_serverid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '邀请者服务器id',
    `from_uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '邀请者uid',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '邀请时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_server_uid` (`serverid`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='uc 邀请记录';

-- 8 ~ 13 位激活码（uc activationcode_model，按控制器与表结构还原：use_uid > 0 为已使用）
DROP TABLE IF EXISTS `zc_sanguo_uc_activationcode`;
CREATE TABLE `zc_sanguo_uc_activationcode` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(13) NOT NULL DEFAULT '' COMMENT '激活码',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '激活码类型（对应各服 activationcode_reward 的 type）',
    `channel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '生成批次（渠道）',
    `use_serverid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '使用者服务器id',
    `use_uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '使用者uid',
    `use_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '使用时间',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '生成时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_use` (`use_serverid`, `use_uid`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='uc 激活码';

-- 激活码类型（uc activationcode_model：add_active_type 写入，use_num > 0 时每个玩家同类型只能用一次；奖励按 type 取各服 activationcode_reward）
DROP TABLE IF EXISTS `zc_sanguo_uc_activationcode_type`;
CREATE TABLE `zc_sanguo_uc_activationcode_type` (
    `type` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '激活码类型',
    `use_num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '同类型限用（大于 0 时每个玩家只能用一个）',
    `description` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '描述',
    `gettype1` INT UNSIGNED NOT NULL DEFAULT 0,
    `gettype2` INT UNSIGNED NOT NULL DEFAULT 0,
    `gettype3` INT UNSIGNED NOT NULL DEFAULT 0,
    `gettype4` INT UNSIGNED NOT NULL DEFAULT 0,
    `getvalue1` INT UNSIGNED NOT NULL DEFAULT 0,
    `getvalue2` INT UNSIGNED NOT NULL DEFAULT 0,
    `getvalue3` INT UNSIGNED NOT NULL DEFAULT 0,
    `getvalue4` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='uc 激活码类型';

-- 分享战报（旧版 report 服务器 c=report&m=save，按 s1 调用还原：同一服务器同一战报只存一份）
DROP TABLE IF EXISTS `zc_sanguo_uc_report`;
CREATE TABLE `zc_sanguo_uc_report` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `serverid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '服务器id',
    `type` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '战报类型（arena / map / siege / god / worldarena 等）',
    `reportid` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '战报id',
    `report` MEDIUMTEXT NOT NULL COMMENT '战报内容（json）',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '保存时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_report` (`serverid`, `type`, `reportid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='uc 分享战报';

-- =============================================================
-- 战斗服务器 battle（旧版 zc_sanguo_battle 库，进程内移植；表名前缀 zc_sanguo_battle_，列名同旧版）
-- =============================================================

-- 跨服远征排行榜（world_arena：各服昨日演武榜前三，id 为 serverid_uid）
DROP TABLE IF EXISTS `zc_sanguo_battle_world_arena`;
CREATE TABLE `zc_sanguo_battle_world_arena` (
    `autoid` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `id` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '服务器id_uid',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `serverid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '服务器id',
    `groupid` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '服务器组id',
    `groupname` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '服务器组',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '昵称',
    `rank` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户排名',
    `changed` SMALLINT NOT NULL DEFAULT 0 COMMENT '本日名次变更',
    `tree` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神树活动中的神树等级',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后挑战时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_id` (`id`),
    KEY `idx_rank` (`rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='跨服竞技场数据表';

-- 跨服远征每日排名记录（world_log：每天 0 点后记录昨日排名与名次变化）
DROP TABLE IF EXISTS `zc_sanguo_battle_world_log`;
CREATE TABLE `zc_sanguo_battle_world_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `serverid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '服务器id',
    `ymd` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '日期(20121111)',
    `rank` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排名',
    `changed` SMALLINT NOT NULL DEFAULT 0 COMMENT '名次变更',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_server_uid_ymd` (`serverid`, `uid`, `ymd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='跨服远征每日排名记录表';

-- 跨服远征战报（world_report：战斗过程，id 同 world_report_log）
DROP TABLE IF EXISTS `zc_sanguo_battle_world_report`;
CREATE TABLE `zc_sanguo_battle_world_report` (
    `id` INT UNSIGNED NOT NULL DEFAULT 0,
    `report` MEDIUMTEXT NOT NULL COMMENT '战斗过程',
    `fromserverid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻击方服务器id',
    `fromgroupid` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '服务器组id',
    `fromgroup` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '服务器组',
    `fromtree` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神树活动中的神树等级',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '攻方昵称',
    `userlevel` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻方等级',
    `torank` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排名',
    `toserverid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '防守方serverid',
    `togroupid` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '服务器组id',
    `togroup` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '服务器组',
    `touid` INT UNSIGNED NOT NULL DEFAULT 0,
    `totree` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神树活动中的神树等级',
    `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '守方昵称',
    `level` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '守方等级',
    `win` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否胜利(0 否; 1胜利)',
    `background` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗背景',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '时间戳',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='跨服远征战报表';

-- 跨服远征战报列表（world_report_log）
DROP TABLE IF EXISTS `zc_sanguo_battle_world_report_log`;
CREATE TABLE `zc_sanguo_battle_world_report_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `fromserverid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻击方服务器id',
    `fromgroupid` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '服务器组id',
    `fromgroup` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '攻方服务器组',
    `fromtree` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神树活动中的神树等级',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻方uid',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '攻方昵称',
    `userlevel` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻方等级',
    `torank` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排名',
    `toserverid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '防守方serverid',
    `togroupid` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '服务器组id',
    `togroup` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '守方服务器组',
    `touid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '守方uid',
    `totree` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神树活动中的神树等级',
    `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '守方昵称',
    `level` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '守方等级',
    `win` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否胜利(0 否; 1胜利)',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '时间戳',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='跨服远征战报列表';

-- 跨服商城购买记录（world_shop_log）
DROP TABLE IF EXISTS `zc_sanguo_battle_world_shop_log`;
CREATE TABLE `zc_sanguo_battle_world_shop_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `serverid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '服务器id',
    `groupid` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '服务器组id',
    `groupname` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '服务器组',
    `itemid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '商品id',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '类型',
    `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '商品名称',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '购买者昵称',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '购买时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='跨服商城购买记录表';

-- 战神殿报名表（god_entry：phase 为年月，goout 为出局的轮次 1/n）
DROP TABLE IF EXISTS `zc_sanguo_battle_god_entry`;
CREATE TABLE `zc_sanguo_battle_god_entry` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phase` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
    `serverid` INT UNSIGNED NOT NULL DEFAULT 0,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `nickname` VARCHAR(50) NOT NULL DEFAULT '',
    `gender` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '头像 1男 2女',
    `level` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `countryid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家id',
    `country` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '国家名称',
    `goout` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战败出局',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后同步时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_phase_server_uid` (`phase`, `serverid`, `uid`),
    KEY `idx_phase_goout` (`phase`, `goout`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='战神殿报名报';

-- 战神殿玩家战斗信息（god_member：同步时的队伍快照）
DROP TABLE IF EXISTS `zc_sanguo_battle_god_member`;
CREATE TABLE `zc_sanguo_battle_god_member` (
    `serverid` INT UNSIGNED NOT NULL DEFAULT 0,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `data` MEDIUMTEXT NOT NULL COMMENT '战斗数据',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后同步时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`serverid`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='战神殿玩家战斗信息';

-- 战帝堂（god_palace：每期冠军，chapter 为第几世）
DROP TABLE IF EXISTS `zc_sanguo_battle_god_palace`;
CREATE TABLE `zc_sanguo_battle_god_palace` (
    `chapter` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '第几世战帝',
    `phase` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第几期',
    `name` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '帝号',
    `serverid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所在服务器id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '用户昵称',
    `round` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最大第几轮',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`chapter`),
    UNIQUE KEY `uk_phase` (`phase`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='战帝堂数据表';

-- 战帝战报（god_palace_report：id 为 god_palace_report_log id）
DROP TABLE IF EXISTS `zc_sanguo_battle_god_palace_report`;
CREATE TABLE `zc_sanguo_battle_god_palace_report` (
    `id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'palace_report_log id',
    `stage` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗阶段',
    `report` MEDIUMTEXT NOT NULL COMMENT '战斗过程',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '攻方昵称',
    `userlevel` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻方等级',
    `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '守方昵称',
    `level` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '守方等级',
    `background` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗背景',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='战神殿战帝战报表';

-- 战帝战报列表（god_palace_report_log）
DROP TABLE IF EXISTS `zc_sanguo_battle_god_palace_report_log`;
CREATE TABLE `zc_sanguo_battle_god_palace_report_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录id',
    `chapter` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第几世',
    `round` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第几轮战斗',
    `stage` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗阶段',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '攻方昵称',
    `roundid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战报id',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_chapter` (`chapter`, `round`, `stage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='战帝战报表';

-- 战神殿战报（god_report：主键同旧版 roundid + stage）
DROP TABLE IF EXISTS `zc_sanguo_battle_god_report`;
CREATE TABLE `zc_sanguo_battle_god_report` (
    `id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'roundid',
    `stage` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗阶段',
    `report` MEDIUMTEXT NOT NULL COMMENT '战斗过程',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '攻方昵称',
    `userlevel` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻方等级',
    `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '守方昵称',
    `level` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '守方等级',
    `background` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗背景',
    `god` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战帝战报',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`, `stage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='战神殿战报表';

-- 战神殿战斗场次（god_round：round 为 1/n 比赛，win1 ~ win3 为三局胜负，win 为胜利方）
DROP TABLE IF EXISTS `zc_sanguo_battle_god_round`;
CREATE TABLE `zc_sanguo_battle_god_round` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phase` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
    `serverid1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '左方玩家服务器id',
    `serverid2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '右方玩家服务器id',
    `uid1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '左方玩家uid',
    `uid2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '右方玩家uid',
    `support1` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '左方玩家支持数',
    `support2` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '右方玩家支持数',
    `first` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1左方先手，2右方先手',
    `win1` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第一局胜负 1左方胜利 2右方胜利',
    `win2` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第二局胜负 1左方胜利 2右方胜利',
    `win3` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第三局胜负 1左方胜利 2右方胜利',
    `win` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '胜利方 1、左方胜利;2、右方胜利',
    `round` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1/n比赛',
    `follow` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关注人数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取奖励时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_server2` (`serverid2`, `uid2`),
    KEY `idx_server1` (`serverid1`, `uid1`),
    KEY `idx_phase` (`phase`, `round`, `win`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='战神殿战斗场次';

-- 战神殿关注 / 支持（god_support：观战玩家每轮关注一场，side 0 为只关注未支持）
DROP TABLE IF EXISTS `zc_sanguo_battle_god_support`;
CREATE TABLE `zc_sanguo_battle_god_support` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phase` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
    `serverid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家服务器id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家uid',
    `toserverid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参战玩家服务器id',
    `touid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参战玩家uid',
    `roundid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关注场次',
    `side` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ' 被支持方，1、左方；2、右方',
    `round` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1/n比赛',
    `win` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '胜利方',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取奖励时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_roundid` (`roundid`),
    UNIQUE KEY `uk_phase_round_uid` (`phase`, `round`, `serverid`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='战神殿支持表';

-- 本服在 uc 服务器列表里的一行（id 同 config app.server_id 默认 1，名称同登录服务器列表；开服时间取建表时间）
INSERT INTO `zc_sanguo_uc_server` (`id`, `name`, `servergroup`, `opentime`, `status`, `starttime`, `endtime`, `addr`, `newserver`, `message`)
VALUES (1, '1区-桃园结义', 1, UNIX_TIMESTAMP(), 1, UNIX_TIMESTAMP(), 0, '', 0, '');
-- 旧版 uc group 表的数据
INSERT INTO `zc_sanguo_uc_group` (`id`, `groupid`, `name`) VALUES (1, 1, '一组');

-- =============================================================
-- 跨服第二批（20261104_cross2.sql）：武斗会 / 群服争霸的本服表，进程内 battle 的表
-- =============================================================

-- 武斗会奖励领取记录（war_model：war_reward_log）
DROP TABLE IF EXISTS `zc_sanguo_war_reward_log`;
CREATE TABLE `zc_sanguo_war_reward_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phase` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活动期数',
    `rewardid` INT UNSIGNED NOT NULL DEFAULT 0,
    `type` INT UNSIGNED NOT NULL DEFAULT 0,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `gold` INT UNSIGNED NOT NULL DEFAULT 0,
    `silver` INT UNSIGNED NOT NULL DEFAULT 0,
    `jade` INT UNSIGNED NOT NULL DEFAULT 0,
    `tavernjade` INT UNSIGNED NOT NULL DEFAULT 0,
    `soul` INT UNSIGNED NOT NULL DEFAULT 0,
    `equip` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '装备',
    `gem` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '宝石碎片',
    `reputation` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '声望',
    `mount` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '坐骑',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_phase_uid` (`phase`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='武斗会奖励记录';

-- 武斗会积分商城购买日志（war_model：war_shop_log）
DROP TABLE IF EXISTS `zc_sanguo_war_shop_log`;
CREATE TABLE `zc_sanguo_war_shop_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主键ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色uid',
    `wid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '礼包ID',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '购买时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='武斗会积分商城购买日志';

-- 群服争霸押注（gvg_model：gvg_bet）
DROP TABLE IF EXISTS `zc_sanguo_gvg_bet`;
CREATE TABLE `zc_sanguo_gvg_bet` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phase` INT UNSIGNED NOT NULL DEFAULT 0,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '押注者',
    `toserverid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '被押注者',
    `roundid` INT UNSIGNED NOT NULL DEFAULT 0,
    `round` INT UNSIGNED NOT NULL DEFAULT 0,
    `side` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1左 2右',
    `win` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1左胜2右胜',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取奖励时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_phase_uid` (`phase`, `uid`, `round`, `roundid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群服争霸支持表';

-- 群服争霸奖励记录（gvg_model：gvg_reward_log，type 1 参赛、2 全服）
DROP TABLE IF EXISTS `zc_sanguo_gvg_reward_log`;
CREATE TABLE `zc_sanguo_gvg_reward_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phase` INT UNSIGNED NOT NULL DEFAULT 0,
    `round` INT UNSIGNED NOT NULL DEFAULT 0,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'uid',
    `type` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 参赛 2 全服',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群服争霸参赛表';

-- 本服已记录的群服争霸胜场（gvg_model：gvg_round，islock 1 为奖励记录已生成）
DROP TABLE IF EXISTS `zc_sanguo_gvg_round`;
CREATE TABLE `zc_sanguo_gvg_round` (
    `phase` INT UNSIGNED NOT NULL DEFAULT 0,
    `round` INT UNSIGNED NOT NULL DEFAULT 0,
    `islock` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1已发放；0未发放',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`phase`, `round`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群服争霸参赛表';

-- 武斗会报名（battle war_entry；integral 本期积分，win1 预赛 / 初赛胜场、win2 复赛胜场、win3 决赛圈胜场，goout 出局位置）
DROP TABLE IF EXISTS `zc_sanguo_battle_war_entry`;
CREATE TABLE `zc_sanguo_battle_war_entry` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phase` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
    `serverid` INT UNSIGNED NOT NULL DEFAULT 0,
    `uid` INT UNSIGNED NOT NULL DEFAULT 0,
    `nickname` VARCHAR(50) NOT NULL DEFAULT '',
    `gender` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '头像 1男 2女',
    `level` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `countryid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家id',
    `country` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '国家名称',
    `integral` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户积分',
    `win1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '胜利场次',
    `win2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '复赛胜利场次',
    `win3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '进入32强决赛圈后的胜利场次',
    `goout` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战败出局',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后同步时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_phase` (`phase`, `serverid`, `uid`),
    KEY `idx_integral` (`integral`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='武斗会报名表';

-- 武斗会玩家战斗信息（battle war_member：队伍快照）
DROP TABLE IF EXISTS `zc_sanguo_battle_war_member`;
CREATE TABLE `zc_sanguo_battle_war_member` (
    `uid` INT UNSIGNED NOT NULL,
    `serverid` INT UNSIGNED NOT NULL,
    `data` MEDIUMTEXT NOT NULL COMMENT '战斗数据',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后同步时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`, `serverid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='武斗会玩家战斗信息';

-- 武斗会积分（battle war_member_info：积分商城用的累计积分，跨期保留）
DROP TABLE IF EXISTS `zc_sanguo_battle_war_member_info`;
CREATE TABLE `zc_sanguo_battle_war_member_info` (
    `serverid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家服务器ID',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家uid',
    `integral` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前积分',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后更新时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`serverid`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户武斗会积分表';

-- 武斗会四强（battle war_palace：每期决赛圈前四，site 为出局位置）
DROP TABLE IF EXISTS `zc_sanguo_battle_war_palace`;
CREATE TABLE `zc_sanguo_battle_war_palace` (
    `phase` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第几期',
    `serverid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所在服务器id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
    `level` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '记录时玩家等级',
    `site` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '出局位置',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '用户昵称',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '记录时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`phase`, `serverid`, `uid`),
    KEY `idx_phase` (`phase`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='武斗会晋级4强数据表';

-- 武斗会战报（battle war_report：id 为场次 id，stage 为第几局）
DROP TABLE IF EXISTS `zc_sanguo_battle_war_report`;
CREATE TABLE `zc_sanguo_battle_war_report` (
    `id` INT UNSIGNED NOT NULL COMMENT 'roundid',
    `stage` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗阶段',
    `report` MEDIUMTEXT NOT NULL COMMENT '战斗过程',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '攻方昵称',
    `userlevel` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻方等级',
    `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '守方昵称',
    `level` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '守方等级',
    `background` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗背景',
    `god` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战帝战报',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`, `stage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='武斗会战报表';

-- 武斗会战斗场次（battle war_round：day 1 预赛、2 初赛、3 复赛、4 决赛；预赛到复赛 round 为当天第几轮，决赛为 1/n）
DROP TABLE IF EXISTS `zc_sanguo_battle_war_round`;
CREATE TABLE `zc_sanguo_battle_war_round` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phase` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
    `day` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第几天的场次',
    `serverid1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '左方玩家服务器id',
    `serverid2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '右方玩家服务器id',
    `uid1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '左方玩家uid',
    `uid2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '右方玩家uid',
    `support1` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '左方玩家支持数',
    `support2` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '右方玩家支持数',
    `first` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1左方先手，2右方先手',
    `win1` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第一局胜负 1左方胜利 2右方胜利',
    `win2` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第二局胜负 1左方胜利 2右方胜利',
    `win3` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第三局胜负 1左方胜利 2右方胜利',
    `win` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '胜利方 1、左方胜利;2、右方胜利',
    `round` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1/n比赛',
    `follow` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关注人数',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取奖励时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_server1` (`serverid1`, `uid1`),
    KEY `idx_server2` (`serverid2`, `uid2`),
    KEY `idx_phase` (`phase`, `day`, `round`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='武斗会战斗场次';

-- 武斗会关注 / 支持（battle war_support：观战玩家每轮关注一场，side 0 为只关注未支持）
DROP TABLE IF EXISTS `zc_sanguo_battle_war_support`;
CREATE TABLE `zc_sanguo_battle_war_support` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phase` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
    `day` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '第几天的场次',
    `serverid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家服务器id',
    `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家uid',
    `toserverid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参战玩家服务器id',
    `touid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参战玩家uid',
    `roundid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关注场次',
    `side` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ' 被支持方，1、左方；2、右方',
    `round` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1/n比赛',
    `win` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '胜利方',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取奖励时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_roundid` (`roundid`),
    UNIQUE KEY `uk_phase_day_round_uid` (`phase`, `day`, `round`, `serverid`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='武斗会支持表';

-- 群服争霸参赛玩家（battle gvg_entry：各参赛服演武榜前 30）
DROP TABLE IF EXISTS `zc_sanguo_battle_gvg_entry`;
CREATE TABLE `zc_sanguo_battle_gvg_entry` (
    `phase` INT UNSIGNED NOT NULL,
    `uid` INT UNSIGNED NOT NULL,
    `serverid` INT UNSIGNED NOT NULL,
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '玩家昵称',
    `level` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家等级',
    `rank` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '玩家演武排行',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后同步时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`phase`, `uid`, `serverid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群服争霸参赛玩家';

-- 群服争霸关注场次（battle gvg_follow：每个参赛服每轮关注一场）
DROP TABLE IF EXISTS `zc_sanguo_battle_gvg_follow`;
CREATE TABLE `zc_sanguo_battle_gvg_follow` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phase` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
    `serverid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关注者',
    `round` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1/n赛',
    `roundid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '场次',
    `toserverid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '被关注者',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_phase_server_round` (`phase`, `serverid`, `round`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群服争霸关注场次表';

-- 群服争霸战斗信息（battle gvg_member：队伍快照）
DROP TABLE IF EXISTS `zc_sanguo_battle_gvg_member`;
CREATE TABLE `zc_sanguo_battle_gvg_member` (
    `uid` INT UNSIGNED NOT NULL,
    `serverid` INT UNSIGNED NOT NULL,
    `data` MEDIUMTEXT NOT NULL COMMENT '战斗数据',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后同步时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`uid`, `serverid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群服争霸战斗信息';

-- 群服争霸战报（battle gvg_report）
DROP TABLE IF EXISTS `zc_sanguo_battle_gvg_report`;
CREATE TABLE `zc_sanguo_battle_gvg_report` (
    `phase` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
    `round` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `roundid` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `serverid1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '攻方',
    `serverid2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '守方',
    `report` MEDIUMTEXT NOT NULL,
    `background` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战斗背景',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`phase`, `round`, `roundid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群服争霸战报表';

-- 群服争霸战斗场次（battle gvg_round：开赛前按参赛服务器生成整张对阵表）
DROP TABLE IF EXISTS `zc_sanguo_battle_gvg_round`;
CREATE TABLE `zc_sanguo_battle_gvg_round` (
    `phase` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
    `round` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1/n赛',
    `roundid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '本轮id',
    `serverid1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '左方玩家服务器id',
    `serverid2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '右方玩家服务器id',
    `bet1` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '左方玩家支持数',
    `bet2` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '右方玩家支持数',
    `win` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '胜利方 1、左方胜利;2、右方胜利',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`phase`, `round`, `roundid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群服争霸战斗场次';

-- 群服争霸参战服务器（battle gvg_server）
DROP TABLE IF EXISTS `zc_sanguo_battle_gvg_server`;
CREATE TABLE `zc_sanguo_battle_gvg_server` (
    `phase` MEDIUMINT UNSIGNED NOT NULL,
    `serverid` INT UNSIGNED NOT NULL,
    `groupid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '服务器组',
    `goout` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '战败出局',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '参赛时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`phase`, `serverid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群服争霸参战服务器';

-- -------------------------------------------------------------
-- 节日活动：春节系列（春节送福任务计数、年兽、新春签到、春节充值、转盘、武将商城）
-- -------------------------------------------------------------

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

-- -------------------------------------------------------------
-- 节日活动第二批（七夕、国家庆典、中秋、国庆、万圣节、圣诞团购、圣诞礼包、周年庆、清明）
-- -------------------------------------------------------------
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

SET FOREIGN_KEY_CHECKS = 1;

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
