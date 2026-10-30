-- 已有数据库升级：演武榜改回旧版（controllers/arena.php + models/arena_model.php）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261031_arena_legacy.sql
--
-- 升级前：zc_sanguo_arena 是新版自定的天梯（预设 NPC 10001 ~ 10005 占前 5 名、次数默认 15、没有 win / total_rank），
--         演武积分 / 购买次数 / 兑换次数存 Redis（arena_points:* / arena_buy_times:* / arena_exch:*）。
-- 升级后：zc_sanguo_arena 为旧版字段（win 连胜、total_rank 历史最高排名）；新增 arena_log、arena_paylog、
--         arena_exchange_log、member_integral（act_arena_int 演武积分）、integral_log；
--         member_field.rank 默认 0（旧版：未入榜为 0）。
--
-- 数据处理（升级前只有测试数据）：演武榜整表重建、不保留旧排名；所有玩家 member_field.rank 归 0，
-- 玩家下次打开演武榜（26 级以上）时按旧版以「最后一名 + 1」重新入榜。上述 Redis 键不再读取，可自行删除。
-- 重复执行会重建演武榜各表（清空数据）。

ALTER TABLE `zc_sanguo_member_field`
    MODIFY COLUMN `rank` INT NOT NULL DEFAULT 0 COMMENT '演武榜排名（zc_sanguo_arena.rank，0 为未入榜）';

UPDATE `zc_sanguo_member_field` SET `rank` = 0;

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
