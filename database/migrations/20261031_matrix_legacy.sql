-- 已有数据库升级：阵法改回旧版存储（own_matrix 每个阵法一行 + member_field.matrixid 记启用的阵法）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261031_matrix_legacy.sql
--
-- 升级前：zc_sanguo_matrix 每个玩家一行（mid 为当前阵法、level 为该行等级），general.pos / general.matrix_id 是第二份站位记录。
-- 升级后：zc_sanguo_matrix 主键 (uid, mid)、每个阵法一行；member_field.matrixid 为启用的阵法；
--         general.goout 为出征名单（旧版 own_general.goout）；general.pos / general.matrix_id 删除。
--
-- 数据转换（升级前只有测试数据，按以下规则保留）：
--   1. 每个未删除的阵法行 → 同 (uid, mid) 的新行，等级不变；负数站位、指向不存在 / 已删除 / 其他玩家武将的站位清零；
--   2. member_field.matrixid = 该玩家原阵法行的 mid（同一玩家多行时取 id 最小的一行，即原代码 first() 读到的那行）；
--      没有阵法行的玩家 matrixid 为 0（旧版「没有阵法信息」）；
--   3. general.goout = 1：原 pos > 0（原代码的「出征」）或在阵法站位上的武将；其余为 0。
-- 列放在 race_dateline 之后：无论 20261031_p3_first_recharge.sql 先执行还是后执行，列顺序都与 schema.sql 一致。
-- 只需执行一次；重复执行会在第一条 ADD COLUMN matrixid 处报错退出，不改动数据。

ALTER TABLE `zc_sanguo_member_field`
    ADD COLUMN `matrixid` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '启用的阵法（zc_sanguo_matrix.mid），0 为没有阵法' AFTER `race_dateline`;

CREATE TABLE `zc_sanguo_matrix_legacy` (
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

-- 1. 阵法行（created_at / updated_at 由 MySQL 填写）
INSERT INTO `zc_sanguo_matrix_legacy`
    (`uid`, `mid`, `position1`, `position2`, `position3`, `position4`, `position5`,
     `position6`, `position7`, `position8`, `position9`, `level`)
SELECT `uid`, `mid`,
    GREATEST(`position1`, 0), GREATEST(`position2`, 0), GREATEST(`position3`, 0),
    GREATEST(`position4`, 0), GREATEST(`position5`, 0), GREATEST(`position6`, 0),
    GREATEST(`position7`, 0), GREATEST(`position8`, 0), GREATEST(`position9`, 0),
    GREATEST(`level`, 0)
FROM `zc_sanguo_matrix`
WHERE `deleted_at` IS NULL AND `mid` > 0;

UPDATE `zc_sanguo_matrix_legacy` m LEFT JOIN `zc_sanguo_general` g ON g.`id` = m.`position1` AND g.`uid` = m.`uid` AND g.`deleted_at` IS NULL SET m.`position1` = 0 WHERE m.`position1` > 0 AND g.`id` IS NULL;
UPDATE `zc_sanguo_matrix_legacy` m LEFT JOIN `zc_sanguo_general` g ON g.`id` = m.`position2` AND g.`uid` = m.`uid` AND g.`deleted_at` IS NULL SET m.`position2` = 0 WHERE m.`position2` > 0 AND g.`id` IS NULL;
UPDATE `zc_sanguo_matrix_legacy` m LEFT JOIN `zc_sanguo_general` g ON g.`id` = m.`position3` AND g.`uid` = m.`uid` AND g.`deleted_at` IS NULL SET m.`position3` = 0 WHERE m.`position3` > 0 AND g.`id` IS NULL;
UPDATE `zc_sanguo_matrix_legacy` m LEFT JOIN `zc_sanguo_general` g ON g.`id` = m.`position4` AND g.`uid` = m.`uid` AND g.`deleted_at` IS NULL SET m.`position4` = 0 WHERE m.`position4` > 0 AND g.`id` IS NULL;
UPDATE `zc_sanguo_matrix_legacy` m LEFT JOIN `zc_sanguo_general` g ON g.`id` = m.`position5` AND g.`uid` = m.`uid` AND g.`deleted_at` IS NULL SET m.`position5` = 0 WHERE m.`position5` > 0 AND g.`id` IS NULL;
UPDATE `zc_sanguo_matrix_legacy` m LEFT JOIN `zc_sanguo_general` g ON g.`id` = m.`position6` AND g.`uid` = m.`uid` AND g.`deleted_at` IS NULL SET m.`position6` = 0 WHERE m.`position6` > 0 AND g.`id` IS NULL;
UPDATE `zc_sanguo_matrix_legacy` m LEFT JOIN `zc_sanguo_general` g ON g.`id` = m.`position7` AND g.`uid` = m.`uid` AND g.`deleted_at` IS NULL SET m.`position7` = 0 WHERE m.`position7` > 0 AND g.`id` IS NULL;
UPDATE `zc_sanguo_matrix_legacy` m LEFT JOIN `zc_sanguo_general` g ON g.`id` = m.`position8` AND g.`uid` = m.`uid` AND g.`deleted_at` IS NULL SET m.`position8` = 0 WHERE m.`position8` > 0 AND g.`id` IS NULL;
UPDATE `zc_sanguo_matrix_legacy` m LEFT JOIN `zc_sanguo_general` g ON g.`id` = m.`position9` AND g.`uid` = m.`uid` AND g.`deleted_at` IS NULL SET m.`position9` = 0 WHERE m.`position9` > 0 AND g.`id` IS NULL;

-- 2. 启用的阵法
UPDATE `zc_sanguo_member_field` f
JOIN `zc_sanguo_matrix` m ON m.`uid` = f.`uid` AND m.`deleted_at` IS NULL AND m.`mid` > 0
SET f.`matrixid` = m.`mid`
WHERE m.`id` = (
    SELECT MIN(m2.`id`) FROM (SELECT `id`, `uid`, `mid`, `deleted_at` FROM `zc_sanguo_matrix`) m2
    WHERE m2.`uid` = m.`uid` AND m2.`deleted_at` IS NULL AND m2.`mid` > 0
);

-- 3. 出征名单
UPDATE `zc_sanguo_general` SET `goout` = IF(`pos` > 0, 1, 0);

UPDATE `zc_sanguo_general` g
JOIN `zc_sanguo_matrix_legacy` m ON m.`uid` = g.`uid`
SET g.`goout` = 1
WHERE g.`id` IN (m.`position1`, m.`position2`, m.`position3`, m.`position4`, m.`position5`,
                 m.`position6`, m.`position7`, m.`position8`, m.`position9`);

-- 4. 换表、删除第二份站位记录
DROP TABLE `zc_sanguo_matrix`;
RENAME TABLE `zc_sanguo_matrix_legacy` TO `zc_sanguo_matrix`;

ALTER TABLE `zc_sanguo_general`
    DROP COLUMN `pos`,
    DROP COLUMN `matrix_id`;
