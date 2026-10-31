-- 已有数据库升级：物品存储——玉石、酒馆宝石、宝石与宝石碎片、精炼石 / 五彩石 / 上古血玉、坐骑与坐骑碎片、将魂与将魂包裹、
-- 神器碎片流水，以及装备碎片 / 天赋卷轴 / 装备精华的流水（三者同旧版存 zc_sanguo_equipment，表结构不变）
-- （models/member_model.php add_jade / sub_jade / add_stone / sub_stone / add_gem / sub_gem / add_gemuse / sub_gemuse /
-- add_souluse / sub_souluse / add_soulpack，models/tavern_model.php add_jade / sub_jade，models/gem_model.php，
-- models/mount_model.php，models/soul_model.php add_hero_soul，models/equipment_model.php add_item 碎片分支 /
-- add_talent_scroll / sub_talent_scroll / add_tower_item / update_tower_item，models/country_model.php add_debris / sub_debris）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261101_items_storage.sql
--
-- 升级前：玉石、宝石碎片、三种石头、将魂、装备碎片 / 卷轴 / 精华在新版没有存储（奖励只回报不发放）；
--         酒馆宝石与会客位置存 Redis user_trader:{uid}，宝石存 Redis user_gems:{uid} 与 equip_gems:{uid}:{eid}，
--         坐骑存 Redis user_mounts:{uid}。
-- 升级后：member 新增 souluse / soulpack / jade / gem / gemuse / refinestone / colorstone / bloodstone（紧跟 soul）；
--         新增 trader_log / tavernjade_log、jade_log、stone_log、own_gem / gem_log、itemchip_log、talent_scroll_log、
--         own_gem_mergelog（宝石合成日志）、own_mount / mountchip_log / mount_info_log、mount_log（西域马场首次选马）、
--         mount_exp_log（坐骑升级经验）、own_soul、debris_log。
--
-- 数据处理（升级前只有测试数据）：上述 Redis 键是新版自定的数据（宝石 / 坐骑的模板与旧版对不上），SQL 读不到 Redis，
-- 不转换、不保留；升级后这些键不再读写，可自行删除：
--   user_trader:{uid}、user_gems:{uid}、equip_gems:{uid}:{eid}、user_mounts:{uid}
-- 已有玩家的 soulpack 按旧版注册值补为 4（旧版 member_model->insert_member 写 4，新版此前不存该列）。
-- 只需执行一次；重复执行会在 ADD COLUMN souluse 处报错退出（此前不会有任何改动）。

ALTER TABLE `zc_sanguo_member`
    ADD COLUMN `souluse` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将魂包裹占用格数（OwnSoul）' AFTER `soul`,
    ADD COLUMN `soulpack` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将魂包裹格数（旧版注册时写 4）' AFTER `souluse`,
    ADD COLUMN `jade` INT NOT NULL DEFAULT 0 COMMENT '玉石个数（MemberJade）' AFTER `soulpack`,
    ADD COLUMN `gem` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '拥有宝石碎片数量（OwnGem）' AFTER `jade`,
    ADD COLUMN `gemuse` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '宝石格子占用数（OwnGem）' AFTER `gem`,
    ADD COLUMN `refinestone` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '精炼石（MemberStone 类型 1）' AFTER `gemuse`,
    ADD COLUMN `colorstone` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '五彩石（MemberStone 类型 2）' AFTER `refinestone`,
    ADD COLUMN `bloodstone` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上古血玉（MemberStone 类型 3）' AFTER `colorstone`;

UPDATE `zc_sanguo_member` SET `soulpack` = 4 WHERE `soulpack` = 0;

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
