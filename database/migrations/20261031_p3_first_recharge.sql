-- 已有数据库升级：首充返利（旧版 first_recharge_log 与 member_field.first_recharge）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261031_p3_first_recharge.sql
--
-- 旧版 first_recharge 列默认 1（显示首充图标），已有玩家升级后同样显示图标。
-- 首充日志由充值流程写入；升级前没有任何首充记录，旧的 Redis first_recharge_status:{uid} 不再使用，可自行清理。
-- 只需执行一次；重复执行会在 ADD COLUMN 处报错退出。

ALTER TABLE `zc_sanguo_member_field`
    ADD COLUMN `first_recharge` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '首充返利标识（1 显示首充图标，2 已首充待领奖，0 不显示）' AFTER `dateline`;

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
