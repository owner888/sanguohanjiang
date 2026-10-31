-- 已有数据库升级：主线推图（c=map）按旧版移植（旧版 controllers/map.php + models/map_model.php，
-- 以及它调用的 member_model->add_exp / update_missionsite / update_newresult、tavern_model 的 pub_log）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。须在 20261102_q_daily1.sql 之后执行。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261103_map.sql
--
-- 升级前：征战进度存新版自定的 zc_sanguo_map（每人每势力每场景一行，mission 为该场景打赢过的最大关卡号；
--         新版自定的关卡列表每个场景只有 1~5 关，打赢第 5 关就把 member.missionstage 推到下一场景、第 4 场景打完就推到
--         下一势力）；关卡宝箱配置是新版自定的 zc_sanguo_mission_newreward（假数据，missid 是场景序号），领取记录在
--         zc_sanguo_mission_newreward_log；势力通关奖励的领取标记存 Redis 键 user_shili_claimed:{uid}:{势力id}；
--         没有首杀记录、战报、强攻次数、闯关竞速记录。
-- 升级后：新建旧版 open_map（每人每势力一行：stage 最大场景、mission 该场景已通过的最大关卡、newresult 通关奖励领取状态）、
--         mission_log（首杀记录 + 今日次数 / 强攻次数）、mission_report（首杀战报）、mission_rewardlog（闯关竞速领奖）、
--         map_action_log（闯关竞速参加人次）、pub_log（酒馆将领获得日志）；member 增加 missionworld / firstworld，
--         member_field 增加 start_time；删除 zc_sanguo_map（旧版同名表是势力配置，已在 MasterData Map）与
--         zc_sanguo_mission_newreward（宝箱配置在 MasterData MissionNewreward）。
--
-- 数据转换（前提：现有玩家多为测试数据，但开发者本人在玩，尽量保留能推出来的进度）：
-- 1. open_map：每人每个有 zc_sanguo_map 记录的势力一行，stage 取该势力有记录的最大场景，mission 取该场景记录的关卡号，
--    newresult 0。只按「真正打赢过的关卡」转换：新版自定流程在打完第 5 关 / 第 4 场景时把 member 位置推到了下一场景 /
--    下一势力，但旧版要打完该场景全部关卡（多为 10 关）才开下一场景，这部分「只是被推过去、没打过」的进度不转换
--    （例如打赢 1-1-5 后被推到 1-2：转换为 1-1 已通过第 5 关，继续从 1-1-6 打）。没有任何记录的玩家补一行势力 1
--    （stage 1、mission 0，同旧版注册 add_map_log）。
-- 2. mission_log：每条 zc_sanguo_map 记录的 1 ~ mission 关各补一条首杀记录（昵称、等级取当前值，times 0，dateline 取
--    记录的更新时间），这些关卡之后再打按非首杀算；没打过的关卡仍可首杀。不生成战报（mission_report）。
-- 3. member.missionlevel / missionstage（界面停留位置）超过转换后的进度时改为最大开启势力的最大场景。
-- 4. member_field.start_time 取该行 created_at（闯关竞速从此时起 3 天，老玩家一般已结束）。
-- 5. zc_sanguo_mission_newreward_log 的已有记录对应的是自定的假宝箱（missid 是场景序号），全部软删除，旧版宝箱可重新领取。
-- 6. Redis 键 user_shili_claimed:{uid}:{id}、lock:shili_reward:{uid}:{id}、lock:newreward:{uid}:{id} 不再读写，可自行删除；
--    势力通关奖励按旧版 member_field.newresult + open_map.newresult 判断（转换后的势力 newresult 都为 0，
--    通关时才会记 member_field.newresult）。
-- 本文件只能执行一次（末尾的 ALTER TABLE / DROP TABLE）。

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

-- 1. open_map：每人每势力取有记录的最大场景那一行
INSERT INTO `zc_sanguo_open_map` (`uid`, `level`, `stage`, `mission`, `newresult`, `dateline`)
SELECT m.`uid`, m.`level`, m.`stage`, LEAST(m.`mission`, 255), 0, m.`dateline`
FROM `zc_sanguo_map` m
JOIN (
    SELECT `uid`, `level`, MAX(`stage`) AS `stage` FROM `zc_sanguo_map`
    WHERE `deleted_at` IS NULL AND `level` BETWEEN 1 AND 17 GROUP BY `uid`, `level`
) t ON t.`uid` = m.`uid` AND t.`level` = m.`level` AND t.`stage` = m.`stage`
JOIN `zc_sanguo_member` mb ON mb.`uid` = m.`uid`
WHERE m.`deleted_at` IS NULL
ORDER BY m.`uid`, m.`level`;

-- 没有势力 1 记录的玩家补开第一个势力（旧版注册 add_map_log(1, uid)）
INSERT IGNORE INTO `zc_sanguo_open_map` (`uid`, `level`, `stage`, `mission`, `newresult`, `dateline`)
SELECT `uid`, 1, 1, 0, 0, UNIX_TIMESTAMP() FROM `zc_sanguo_member`;

-- 2. mission_log：打赢过的 1 ~ mission 关补首杀记录（4-3 场景只有 7 关）
INSERT IGNORE INTO `zc_sanguo_mission_log`
    (`uid`, `nickname`, `missionlevel`, `missionstage`, `missionid`, `times`, `strom`, `dateline`, `level`, `disable`, `tree`)
SELECT m.`uid`, LEFT(mb.`nickname`, 64), m.`level`, m.`stage`, n.`n`, 0, 0, m.`dateline`, mb.`level`, 0, 0
FROM `zc_sanguo_map` m
JOIN `zc_sanguo_member` mb ON mb.`uid` = m.`uid`
JOIN (
    SELECT 1 AS `n` UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
    UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10
) n ON n.`n` <= m.`mission`
WHERE m.`deleted_at` IS NULL AND m.`level` BETWEEN 1 AND 17
  AND NOT (m.`level` = 4 AND m.`stage` = 3 AND n.`n` > 7)
ORDER BY m.`uid`, m.`level`, m.`stage`, n.`n`;

-- member：旧版 missionworld / firstworld
ALTER TABLE `zc_sanguo_member`
    ADD COLUMN `missionworld` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前是第几张地图（1初始地图，2第二张地图）' AFTER `missionid`,
    ADD COLUMN `firstworld` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首次进入过第几张地图' AFTER `missionworld`;

-- 3. 界面停留位置超过转换后的进度时，改为最大开启势力的最大场景
UPDATE `zc_sanguo_member` mb
JOIN (
    SELECT o.`uid`, o.`level`, o.`stage`
    FROM `zc_sanguo_open_map` o
    JOIN (SELECT `uid`, MAX(`level`) AS `level` FROM `zc_sanguo_open_map` GROUP BY `uid`) x
      ON x.`uid` = o.`uid` AND x.`level` = o.`level`
) t ON t.`uid` = mb.`uid`
SET mb.`missionlevel` = t.`level`, mb.`missionstage` = t.`stage`
WHERE mb.`missionlevel` > t.`level` OR (mb.`missionlevel` = t.`level` AND mb.`missionstage` > t.`stage`);

-- member_field：旧版 start_time（首次进入游戏时间）
ALTER TABLE `zc_sanguo_member_field`
    ADD COLUMN `start_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '首次进入游戏时间（闯关竞速从此时起 3 天）' AFTER `newresult`;

-- 4. 老玩家首次进入游戏时间取建档时间
UPDATE `zc_sanguo_member_field` SET `start_time` = UNIX_TIMESTAMP(`created_at`) WHERE `start_time` = 0;

-- 5. 自定宝箱的领取记录作废
UPDATE `zc_sanguo_mission_newreward_log` SET `deleted_at` = NOW() WHERE `deleted_at` IS NULL;

-- 删除自定的进度表与宝箱配置表
DROP TABLE IF EXISTS `zc_sanguo_map`;
DROP TABLE IF EXISTS `zc_sanguo_mission_newreward`;
