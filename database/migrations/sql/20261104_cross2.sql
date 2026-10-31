-- 已有数据库升级：跨服第二批——武斗会（c=war）、群服争霸（c=gvg）
-- （旧版 s1 controllers/war.php、gvg.php、cron_gvg.php、member.php 与 models/war_model.php、gvg_model.php、transfer_model.php；
-- battle controllers/gvg.php；武斗会的服务器端控制器 war.php 不在旧版代码包里（war 包只有 uc 控制器的副本），按 s1 调用方、
-- 客户端与 battle 库表结构还原）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。须在 20261104_cross1.sql 之后执行。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/sql/20261104_cross2.sql
--
-- 部署前提同 20261104_cross1.sql：单服部署，旧版 battle（战斗服务器 / war 服务器）的表加前缀 zc_sanguo_battle_ 放在同一个库里，
-- 列名同旧版，数据按 serverid（config app.server_id）分区。
--
-- 升级前：武斗会（c=war）与群服争霸（c=gvg）是新版自定的（武斗会积分存 Redis war_points:{uid}、宝箱写死；群服争霸的军团写死、
--         押注存 Redis gvg_bet:{uid}），都没有表。
-- 升级后：新建旧版 s1 的 war_reward_log、war_shop_log、gvg_bet、gvg_reward_log、gvg_round；battle 的 battle_war_entry、
--         battle_war_member、battle_war_member_info、battle_war_palace、battle_war_report、battle_war_round、battle_war_support、
--         battle_gvg_entry、battle_gvg_follow、battle_gvg_member、battle_gvg_report、battle_gvg_round、battle_gvg_server。
--         不建：battle 的 war_palace_report_log（只供客户端不调用的 palace_report）、war_support_2（旧版无代码读写）、
--         war_time / gvg_time（测试用时间表）、war_reward（0 行；武斗会每场积分取客户端积分说明，见 Cross\WarServer）。
--
-- 数据处理（前提：武斗会 / 群服争霸现有的状态都是新版自定的测试数据，对不上旧版结构）：
-- 1. Redis war_points:{uid}（自定积分）与 gvg_bet:{uid}（自定押注，押注扣掉的银两不退）不转换、不保留；升级后不再读写，可自行删除。
-- 2. 武斗会积分商城的积分（battle_war_member_info.integral）从 0 开始。
-- 各表先 DROP 再建（会清空本批新表的数据）。

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
