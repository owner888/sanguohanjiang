-- 已有数据库升级：养成（第三批）——名将进阶（c=advanced）、装备精炼（c=refine）、名将谱 / 名将成就（c=general_book）按旧版移植
-- （旧版 controllers/advanced.php + models/advanced_model.php、controllers/refine.php + models/refine_model.php、
--  controllers/general_book.php + models/general_model.php 的图鉴 / 成就部分，以及 battle_model 的 _advanced_arr / _addition_arr、
--  general.php 的 _get_advanced_info / _get_achievement_info）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261101_growth3.sql
--
-- 升级前：名将进阶是新版自定的逐级飞升（扣银两 / 元宝、直接改 general.wuliup 等），没有进阶数据；
--         精炼词条存新版自定的 Redis 键 equip_refine:{uid}:{eid}（随机词条，与旧版精炼等级 equipment.hammer 无关）；
--         名将谱是新版自定的「羁绊点亮」，存 Redis 键 general_book:{uid}。
-- 升级后：新建旧版 advanced_log（将领进阶）、advanced_inherit_log（进阶传承记录）、refine_master_log（精炼大师）、
--         refine_log / refine_inherit_log（精炼 / 继承日志）、own_achievement（名将成就）、own_addition（名将谱加成）、
--         own_achievement_log（成就升级记录）；member_field 增加 advanced_time（进阶传承 CD）。
--
-- 数据处理（升级前只有测试数据）：上述 Redis 键是新版自定的数据，对不上旧版结构，不转换、不保留；
-- 升级后不再读写，可自行删除：equip_refine:{uid}:{eid}、general_book:{uid}。
-- 新版自定的飞升改过的 general.wuliup / zhiliup / tiliup 与 quality 不回滚（测试数据）。
-- 名将谱加成在玩家打开名将谱或获得武将时按旧版重算；名将进阶数据在打开进阶界面 / 将领界面时按旧版建档。
-- 各表先 DROP 再建（会清空本批新表的数据）；末尾的 ALTER TABLE 只能执行一次。


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

-- 进阶传承 CD（旧版 member_field.advanced_time）
ALTER TABLE `zc_sanguo_member_field` ADD COLUMN `advanced_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上次使用进阶传承时间（名将进阶传承 CD）' AFTER `oncountrymine`;
