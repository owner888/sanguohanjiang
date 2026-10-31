-- 悍将三国 (Modern Edition) 核心数据库完整架构定义
-- 严格遵循 AGENTS.md 第七大铁律：全表三必备时间字段 (created_at, updated_at, deleted_at)
-- 兼容 MySQL 8.0+ / MySQL 9.x，全量支持 utf8mb4_unicode_ci 高性能字符集

CREATE DATABASE IF NOT EXISTS `zc_sanguo` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `zc_sanguo`;

SET FOREIGN_KEY_CHECKS = 0;

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
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1、禁言 2、永久禁言 3、封号 4、永久封号（聊天室发言只认 1 / 2）',
    `statustime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '禁言或封号截止时间（到期后清除 status）',
    `rebate_firstpay` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '返利首次充值记录（返利活动期间充过值后不再返利）',
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
    `freeleap` INT NOT NULL DEFAULT 10,
    `leaptimes` INT NOT NULL DEFAULT 0,
    `rank` INT NOT NULL DEFAULT 0 COMMENT '演武榜排名（zc_sanguo_arena.rank，0 为未入榜）',
    `newresult` INT NOT NULL DEFAULT 0,
    `gift` INT NOT NULL DEFAULT 0,
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
-- 10. 征战地图关卡表
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_map`;
CREATE TABLE `zc_sanguo_map` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL,
    `level` INT NOT NULL DEFAULT 1 COMMENT '战役势力ID',
    `stage` INT NOT NULL DEFAULT 1 COMMENT '场景关卡ID',
    `mission` INT NOT NULL DEFAULT 0 COMMENT '已通关据点ID',
    `dateline` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_level_stage` (`uid`, `level`, `stage`),
    INDEX `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='征战副本关卡表';

-- -------------------------------------------------------------
-- 15. 关卡据点通关奖励配置表与领取记录表
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_mission_newreward`;
CREATE TABLE `zc_sanguo_mission_newreward` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `levelid` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '战役势力ID',
    `missid` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '通关据点/关卡序号',
    `mname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '关卡名称',
    `re_type` INT NOT NULL DEFAULT 1 COMMENT '奖励类型 (1:元宝, 2:银币, 5:原魂, 8:装备, 12:武将)',
    `re_name` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '奖励展示名',
    `re_num` INT NOT NULL DEFAULT 1 COMMENT '奖励数量',
    `re_value` INT NOT NULL DEFAULT 0 COMMENT '奖励关联ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_levelid` (`levelid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='关卡据点通关奖励配置表';

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

INSERT INTO `zc_sanguo_mission_newreward` (`id`, `levelid`, `missid`, `mname`, `re_type`, `re_name`, `re_num`, `re_value`) VALUES
(1, 1, 1, '大兴山', 2, '银两', 5000, 0),
(2, 1, 2, '青州', 1, '元宝', 50, 0),
(3, 1, 3, '广宗', 5, '将魂', 100, 0),
(4, 1, 4, '巨鹿', 1, '元宝', 100, 0),
(5, 1, 5, '张梁', 2, '银两', 10000, 0),
(6, 1, 6, '张角', 1, '元宝', 200, 0),
(7, 2, 1, '汜水关', 2, '银两', 10000, 0),
(8, 2, 2, '华雄', 1, '元宝', 80, 0),
(9, 2, 3, '虎牢关', 5, '将魂', 200, 0),
(10, 2, 4, '吕布', 1, '元宝', 150, 0),
(11, 2, 5, '洛阳', 2, '银两', 20000, 0),
(12, 2, 6, '董卓', 1, '元宝', 300, 0)
ON DUPLICATE KEY UPDATE `mname` = VALUES(`mname`);

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

SET FOREIGN_KEY_CHECKS = 1;
