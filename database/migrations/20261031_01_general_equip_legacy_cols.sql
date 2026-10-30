-- 已有数据库升级：武将 / 装备补齐旧版字段（own_general / own_equip 语义）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261031_01_general_equip_legacy_cols.sql
--
-- 前提（开发者已确认）：升级前 general / equipment 表里只有测试数据，可以丢弃。
--   旧行没有模板属性（武力/智力/体力/天赋/技能/初始值……），无法可靠回填，所以本文件会：
--   1. TRUNCATE zc_sanguo_equipment、zc_sanguo_general（全部装备、武将清空，自增 id 重置）；
--   2. 给每个未删除的玩家重新发放旧版初始武将（模板 1 廖化，init_general：1 级、levelupexp 0、出征），
--      放在 1 号阵法 2 号位；所有阵法站位先清零（旧武将 id 已失效）；
--   3. 不发初始装备（旧版注册不送装备）。
--   Redis 里按武将 id 存的状态（如 general_star:{uid}:{gid}）随之失效，需要的话自行清理。
-- 只需执行一次；重复执行会在 ADD COLUMN levelupexp 处报错退出（此时第 1 步已再次清空数据）。

TRUNCATE TABLE `zc_sanguo_equipment`;
TRUNCATE TABLE `zc_sanguo_general`;

ALTER TABLE `zc_sanguo_general`
    MODIFY `generalid` INT NOT NULL COMMENT '武将模板ID（MasterData General，即旧版模板表 zc_sanguo_general.id）',
    ADD COLUMN `levelupexp` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '将领升级经验' AFTER `exp`,
    ADD COLUMN `image` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将资源ID' AFTER `levelupexp`,
    ADD COLUMN `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '职业（1 猛将、2 谋士）' AFTER `image`,
    ADD COLUMN `wuli` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '初始武力' AFTER `type`,
    ADD COLUMN `zhili` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '初始智力' AFTER `wuli`,
    ADD COLUMN `tili` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '初始体力' AFTER `zhili`,
    ADD COLUMN `talentid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '天赋ID' AFTER `tili`,
    ADD COLUMN `skillid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '技能ID' AFTER `talentid`,
    ADD COLUMN `star` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将星级' AFTER `skillid`,
    ADD COLUMN `turn` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '转生次数' AFTER `star`,
    ADD COLUMN `quality` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '武将进阶品级（1白，2绿，3蓝，4金，5紫，6红）' AFTER `turn`,
    ADD COLUMN `addtype` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成就加成类型' AFTER `quality`,
    ADD COLUMN `addvalue` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成就加成值' AFTER `addtype`,
    ADD COLUMN `mid0` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '主战坐骑' AFTER `addvalue`,
    ADD COLUMN `mid1` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '备战坐骑1' AFTER `mid0`,
    ADD COLUMN `mid2` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '备战坐骑2' AFTER `mid1`,
    ADD COLUMN `mid3` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '备战坐骑3' AFTER `mid2`,
    ADD COLUMN `mid4` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '备战坐骑4' AFTER `mid3`,
    ADD COLUMN `goout` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否出征(1 出征中、0 休息中)' AFTER `mid4`;

ALTER TABLE `zc_sanguo_equipment`
    MODIFY `equipid` INT NOT NULL COMMENT '装备模板ID（MasterData Equip，即旧版 zc_sanguo_equip.id）',
    MODIFY `type` TINYINT NOT NULL DEFAULT 1 COMMENT '装备类型（1 猛将武器、2 谋士武器、3 铠甲、4 披风、5 兵符）',
    MODIFY `quality` TINYINT NOT NULL DEFAULT 1 COMMENT '装备品质（1 白、2 绿、3 蓝、4 金、5 紫）',
    ADD COLUMN `needlevel` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '武将装配等级' AFTER `quality`,
    MODIFY `level` INT NOT NULL DEFAULT 0 COMMENT '装备强化等级' AFTER `needlevel`,
    ADD COLUMN `power` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '装备能力初始值' AFTER `level`,
    ADD COLUMN `powerup` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '强化后能力' AFTER `power`,
    MODIFY `generalid` INT NOT NULL DEFAULT 0 COMMENT '穿戴该装备的武将（本库 zc_sanguo_general.id），0 为未穿戴' AFTER `powerup`,
    ADD COLUMN `site` TINYINT UNSIGNED AS (CASE WHEN `generalid` > 0 AND `deleted_at` IS NULL THEN CASE `type` WHEN 1 THEN 1 WHEN 2 THEN 1 WHEN 3 THEN 2 WHEN 4 THEN 3 WHEN 5 THEN 4 END END) STORED COMMENT '装配位置（旧版 get_item_site 派生，未穿戴/已删除为 NULL），仅用于唯一约束' AFTER `generalid`,
    ADD COLUMN `chip` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '碎片数(0 道具、>1碎片)' AFTER `saleprice`,
    ADD COLUMN `sale_site` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '道具出售位置（1红装回收，0背包出售）' AFTER `chip`,
    ADD COLUMN `hammer` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '锤子等级(1-7:白绿蓝黄紫红橙)' AFTER `sale_site`,
    ADD COLUMN `aid` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '强化等级成就ID' AFTER `hammer`,
    ADD COLUMN `forgeid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '神铸属性关联id' AFTER `aid`,
    ADD COLUMN `newquality` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '打造出来的装备新品级' AFTER `forgeid`,
    ADD COLUMN `oldequipid` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '打造出来的装备关联旧的装备id' AFTER `newquality`,
    ADD COLUMN `newattribute` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '神铸属性' AFTER `oldequipid`,
    ADD UNIQUE KEY `uk_general_site` (`uid`, `generalid`, `site`);

-- 旧版注册初始武将：general_model::init_general(1, uid) + matrix_model::init_matrix(1, uid, 2, gid)
-- 数值取自 MasterData General 模板 1（廖化）
INSERT INTO `zc_sanguo_general`
    (`uid`, `generalid`, `name`, `level`, `exp`, `levelupexp`, `image`, `type`, `wuli`, `zhili`, `tili`,
     `talentid`, `skillid`, `star`, `goout`, `pos`, `matrix_id`, `status`)
SELECT `uid`, 1, '廖化', 1, 0, 0, 1, 1, 81, 36, 71, 1, 1, 1, 1, 2, 1, 1
FROM `zc_sanguo_member`
WHERE `deleted_at` IS NULL;

UPDATE `zc_sanguo_matrix` SET
    `position1` = 0, `position2` = 0, `position3` = 0,
    `position4` = 0, `position5` = 0, `position6` = 0,
    `position7` = 0, `position8` = 0, `position9` = 0;

UPDATE `zc_sanguo_matrix` m
JOIN `zc_sanguo_general` g ON g.`uid` = m.`uid`
SET m.`position2` = g.`id`
WHERE m.`mid` = 1;

INSERT INTO `zc_sanguo_matrix` (`uid`, `mid`, `level`, `position2`)
SELECT g.`uid`, 1, 1, g.`id`
FROM `zc_sanguo_general` g
LEFT JOIN `zc_sanguo_matrix` m ON m.`uid` = g.`uid` AND m.`mid` = 1
WHERE m.`id` IS NULL;
