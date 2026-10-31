-- 已有数据库升级：酒馆（c=tavern）、每日对酒（c=drink）、国宴（c=banquet）、国家谏言（c=expostulation）、神将之门（c=generaltask）、
-- 国家学院（c=war_college）、武将培养（c=cultivate）、武将继承（c=general_inherit）、武将折旧（c=muster&m=sale_general）、
-- 校场训练（c=practice）按旧版移植（旧版 controllers/tavern.php、drink.php、banquet.php、expostulation.php、generaltask.php、
-- war_college.php、cultivate.php、general_inherit.php、muster.php、practice.php 与对应 models，
-- 以及它们调用的 general_model 的 add_exp / turn / 培养 / 继承、tavern_model 的突飞卡、guide_model 的新手突飞次数）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。须在 20261103_map.sql 之后执行（文件名带 q_ 使其按名称排在
-- 20261103_map.sql 之后：本文件改的 member_field / member_activity 列在前面的迁移里建好）。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261103_q_daily2.sql
--
-- 升级前：这些玩法是新版自定的，状态多存 Redis：对酒 user_drink_date:{uid} / global_drink_logs，宴会 banquet_teams，
--         谏言 expostulation_merit:{uid} / expostulation_adopted:{uid}，远征 general_task:{uid}，国家学院（按玩家）
--         war_college_techs:{uid}，培养待保存属性 cultivate_pending:{uid}:{gid}；酒馆名将池 / 会客规则、校场训练位等级 /
--         突飞 / 训练经验公式、继承规则都是自定的；新账号不建训练位（第一次打开校场才建）；member_field.freeleap 默认 10。
-- 升级后：新建旧版 drink_log、country_banquet_log、expostulation、expostulation_support、generaltask_log、
--         generaltask_mission_log、generaltask_report、war_college_skill_info、war_college_gold_info、
--         war_college_gold_info_log、war_college_level_up_log、warcollege_use、cultivate_log、inherit_times、inherit_log、
--         leap_log（突飞卡流水）、general_leap_log（武将突飞记录）、guide_log（新手引导突飞次数）；
--         member_field 增加 baijintimes / zhizuntimes / ongeneral / free_inherit / general_sale / inherit_dateline，
--         freeleap 默认值改为旧版的 0；member_activity 增加 drinktime / generaltasktimes。
--
-- 数据处理（前提：这些玩法现有的状态都是测试数据，对不上旧版结构）：
-- 1. 上述 Redis 键是新版自定的数据，不转换、不保留；升级后不再读写，可自行删除（培养改用旧版键 cultivate_{uid}_{gid}）。
-- 2. 已有 member_activity 行 generaltasktimes 为 0，玩家当天第一次请求（每日计划任务）后重置为 10；drinktime 0（今天可对酒）。
-- 3. 训练位：旧版注册时建 1、2 号训练位（init_practice）。已有玩家缺的 1 ~ practiceall 号训练位补建（等级 1、空闲）；
--    已有训练位与训练中的将领保留（结束时间不变，下次打开校场按旧版公式结算经验）。
-- 4. 已有玩家的 freeleap（突飞卡，按新版默认 10 发过）保留。
-- 各表先 DROP 再建（会清空本批新表的数据）；末尾的 ALTER TABLE 只能执行一次。

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

ALTER TABLE `zc_sanguo_member_field`
    MODIFY `freeleap` INT NOT NULL DEFAULT 0 COMMENT '免费突飞次数（突飞卡）',
    ADD COLUMN `baijintimes` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '今日白金培养次数（每日计划任务归零）' AFTER `rebate_version`,
    ADD COLUMN `zhizuntimes` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '今日至尊培养次数（每日计划任务归零）' AFTER `baijintimes`,
    ADD COLUMN `ongeneral` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '点将台是否有新武将（获得武将气泡）' AFTER `zhizuntimes`,
    ADD COLUMN `free_inherit` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上次使用免费继承时间' AFTER `ongeneral`,
    ADD COLUMN `general_sale` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将折旧使用时间' AFTER `free_inherit`,
    ADD COLUMN `inherit_dateline` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '免费赠送的至尊继承使用时间' AFTER `general_sale`;

ALTER TABLE `zc_sanguo_member_activity`
    ADD COLUMN `drinktime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '每日对酒时间' AFTER `task_number`,
    ADD COLUMN `generaltasktimes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神将之门挑战次数（每日计划任务重置为 10）' AFTER `drinktime`;

-- 补建缺少的训练位（1 ~ practiceall 号）
INSERT IGNORE INTO `zc_sanguo_practice` (`uid`, `squence`, `generalid`, `level`, `type`, `starttime`, `endtime`)
SELECT f.`uid`, n.`n`, 0, 1, 1, 0, 0
FROM `zc_sanguo_member_field` f
JOIN (SELECT 1 AS `n` UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
      UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8) n ON n.`n` <= f.`practiceall`
WHERE f.`deleted_at` IS NULL;
