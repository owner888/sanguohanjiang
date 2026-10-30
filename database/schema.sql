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
    `act` INT NOT NULL DEFAULT 100 COMMENT '军令/体力',
    `maxact` INT NOT NULL DEFAULT 100,
    `vip` TINYINT NOT NULL DEFAULT 1,
    `bagnum` INT NOT NULL DEFAULT 20,
    `guide` INT NOT NULL DEFAULT 0,
    `guidestep` INT NOT NULL DEFAULT 0,
    `guidestop` TINYINT NOT NULL DEFAULT 0,
    `missionsite` INT NOT NULL DEFAULT 1,
    `missionlevel` INT NOT NULL DEFAULT 1,
    `missionstage` INT NOT NULL DEFAULT 1,
    `missionid` INT NOT NULL DEFAULT 1,
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
-- 6. 将魂命格表 (八大星宿孔位)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_general_soul`;
CREATE TABLE `zc_sanguo_general_soul` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` INT UNSIGNED NOT NULL,
    `gid` INT UNSIGNED NOT NULL COMMENT '武将ID',
    `slot1` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '贪狼魂 (攻击)',
    `slot2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '破军魂 (生命)',
    `slot3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '七杀魂 (防御)',
    `slot4` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '文曲魂 (暴击)',
    `slot5` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武曲魂 (闪避)',
    `slot6` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '廉贞魂 (命中)',
    `slot7` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '巨门魂 (格挡)',
    `slot8` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '天相魂 (统御)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_uid_gid` (`uid`, `gid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='武将将魂命格表';

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='玩家积分记录表（目前只有演武榜读写 act_arena_int）';

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
-- 11. 封邑与攻城略地表
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `zc_sanguo_siege`;
CREATE TABLE `zc_sanguo_siege` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `city_id` INT UNSIGNED NOT NULL UNIQUE COMMENT '城池ID: 1洛阳 2许昌 3建邺 4成都 5长安 6襄阳',
    `name` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '城池名称',
    `occupier_name` VARCHAR(64) NOT NULL DEFAULT '黄巾叛军' COMMENT '当前占领太守名称',
    `occupier_uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '占领玩家UID，0为NPC',
    `daily_silver` INT NOT NULL DEFAULT 5000 COMMENT '每日岁贡银两',
    `daily_gold` INT NOT NULL DEFAULT 20 COMMENT '每日岁贡元宝',
    `defense_level` INT NOT NULL DEFAULT 1 COMMENT '城防等级',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='城池争夺与封邑表';

-- 预置三国核心名城初始数据
INSERT INTO `zc_sanguo_siege` (`city_id`, `name`, `occupier_name`, `occupier_uid`, `daily_silver`, `daily_gold`, `defense_level`)
VALUES
    (1, '洛阳', '黄巾叛军', 0, 10000, 50, 5),
    (2, '许昌', '黄巾叛军', 0, 8000, 40, 4),
    (3, '建邺', '黄巾叛军', 0, 8000, 40, 4),
    (4, '成都', '黄巾叛军', 0, 8000, 40, 4),
    (5, '长安', '黄巾叛军', 0, 9000, 45, 5),
    (6, '中原·濮阳', '黄巾叛军', 0, 6000, 30, 3)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

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
-- 将魂星路 (对齐原版 zc_sanguo_member_activity 的星路字段 / soul_open_map / soul_log / soul_report)
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

SET FOREIGN_KEY_CHECKS = 1;
