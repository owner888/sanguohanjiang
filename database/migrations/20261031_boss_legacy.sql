-- 已有数据库升级：世界 BOSS（c=worldboss）、国家 BOSS（c=countryboss）改回旧版
-- （controllers/worldboss.php + models/worldboss_model.php、controllers/countryboss.php + models/countryboss_model.php、
--   controllers/cron_acttask.php:worldboss）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261031_boss_legacy.sql
--
-- 升级前：两个 BOSS 都是新版自定的玩法（自定血量、鼓舞系数、守军、奖励），状态只存 Redis，库里没有 BOSS 表：
--         hj:worldboss_hp、hj:worldboss_buff:{uid}、hj:worldboss_dmg:{uid}、
--         hj:countryboss_hp:{国家}、hj:countryboss_buff:{uid}、hj:countryboss_dmg:{uid}。
-- 升级后：同旧版，BOSS 状态存 Redis 的旧版键名（hj:worldboss_*、hj:countryboss_{国家id}_* 等，见 Model\WorldBoss / CountryBoss），
--         新增 country_boss_log（国家 BOSS 击杀记录，决定下次 BOSS 血量 / 等级 / 奖励加成）；
--         member_integral 只改表注释（世界 BOSS 奖励也写 act_arena_int）。
--
-- 数据处理（升级前只有 Redis 测试数据）：新版自定的伤害 / 鼓舞记录对不上旧版排行与奖励，不转换、不保留；
-- 上述 Redis 键不再读取，可执行以下命令删除：
--   redis-cli del hj:worldboss_hp
--   redis-cli --scan --pattern 'hj:worldboss_buff:*' | xargs -r redis-cli del
--   redis-cli --scan --pattern 'hj:worldboss_dmg:*' | xargs -r redis-cli del
--   redis-cli --scan --pattern 'hj:countryboss_hp:*' | xargs -r redis-cli del
--   redis-cli --scan --pattern 'hj:countryboss_buff:*' | xargs -r redis-cli del
--   redis-cli --scan --pattern 'hj:countryboss_dmg:*' | xargs -r redis-cli del
-- 重复执行会重建 country_boss_log（清空击杀记录）。

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

ALTER TABLE `zc_sanguo_member_integral`
    COMMENT='玩家积分记录表（目前只有演武榜、世界 BOSS 奖励读写 act_arena_int）';
