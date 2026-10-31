-- 已有数据库升级：跨服第一批——进程内的 uc（用户中心）/ battle（战斗服务器）基础，跨服演武（c=worldarena）、战神殿（c=god）、
-- 英雄王座跨服排行、邀请码 / 长激活码、分享战报、注册信息记录（fetch_member_info）、61 级以上升级的全服福利
-- （旧版 s1 controllers/worldarena.php、uc_worldarena.php、god.php、herothrone.php、invitation.php、activationcode.php、
-- report.php、pack.php、map.php、member.php、chat.php、sacredtree.php 与对应 models；battle controllers/worldarena.php、
-- god.php、cron.php；uc controllers/herothrone.php、invitation.php、activationcode.php、register.php）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。须在 20261103_t_daily5.sql 之后执行（本文件在 member / user 末尾加列）。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/sql/20261104_cross1.sql
--
-- 部署前提：游戏目前按单服部署，旧版独立的 uc 服务器与 battle 服务器作为本进程内的模块，表放在同一个库里，
-- uc 的表加前缀 zc_sanguo_uc_、battle 的表加前缀 zc_sanguo_battle_（列名同旧版），数据按 serverid（config app.server_id）分区。
--
-- 升级前：跨服演武是新版自定的（排名、功勋存 Redis worldarena_exploit:{uid}，前三写死）；战神殿、英雄王座跨服排行、
--         邀请码生成 / 校验、长激活码、分享战报都没有。
-- 升级后：新建旧版 s1 的 member_world、world_bet_log、world_reward、world_reward_log、world_top_log、god_reward_log、
--         god_palace；uc 的 uc_server（本服一行）、uc_group、uc_herothrone、uc_invitation_code、uc_invitation_log、
--         uc_activationcode、uc_activationcode_type、uc_report；battle 的 battle_world_arena、battle_world_log、
--         battle_world_report、battle_world_report_log、battle_world_shop_log、battle_god_entry、battle_god_member、
--         battle_god_palace、battle_god_palace_report、battle_god_palace_report_log、battle_god_report、battle_god_round、
--         battle_god_support；member 增加 server_reward；user 增加 phone / version。
--
-- 数据处理（前提：跨服演武现有的状态都是新版自定的测试数据，对不上旧版结构）：
-- 1. Redis worldarena_exploit:{uid} 是新版自定的功勋，不转换、不保留；升级后不再读写，可自行删除。
-- 2. 已有玩家的跨服信息在第一次打开跨服演武时按旧版建档（免费 10 次）；member.server_reward 记 0（61 级以上的玩家
--    会依次领到升级后新发的全服福利）。
-- 3. 已有的本服邀请码（invitation_code）是旧版从 uc 拿到后存在本服的，升级后 uc 里没有，按 uc 的规则补登到
--    uc_invitation_code（被邀请人数取本服记录），这样别人仍能用这些码邀请。
-- 4. uc_server 的本服行与补登邀请码的 serverid 都取 1（config app.server_id 的默认值）；改过 SERVER_ID 的部署把这两处的 1
--    换成对应的值。
-- 各表先 DROP 再建（会清空本批新表的数据）；末尾的 ALTER TABLE / INSERT 只能执行一次。

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

-- 全服福利领取进度（旧版 member.server_reward：已领到的 world_reward id）
ALTER TABLE `zc_sanguo_member`
    ADD COLUMN `server_reward` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '全服奖励（已领到的 world_reward id）' AFTER `guest`;

-- 注册 / 绑定时记录的手机号与客户端版本（uc register_model->fetch_member_info；设备 mac、渠道已有列）
ALTER TABLE `zc_sanguo_user`
    ADD COLUMN `phone` VARCHAR(13) NOT NULL DEFAULT '' COMMENT '手机号（注册 / 绑定时填写）' AFTER `guest_key`,
    ADD COLUMN `version` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '注册 / 绑定时的客户端版本' AFTER `phone`;

-- 已有的本服邀请码补登到 uc（见上文数据处理 3）
INSERT IGNORE INTO `zc_sanguo_uc_invitation_code` (`serverid`, `uid`, `code`, `number`, `dateline`)
SELECT 1, `uid`, `code`, `number`, UNIX_TIMESTAMP() FROM `zc_sanguo_invitation_code` WHERE `code` <> '' AND `deleted_at` IS NULL;
