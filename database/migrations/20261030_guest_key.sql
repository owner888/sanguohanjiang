-- 已有数据库升级：游客登录密钥
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261030_guest_key.sql
--
-- 升级前注册的游客号没有密钥，无法再以游客身份登录（login.guest 返回 -1）。

ALTER TABLE `zc_sanguo_user` ADD COLUMN `guest_key` CHAR(64) NOT NULL DEFAULT '' COMMENT '游客登录密钥 sha256（明文只在注册时返回一次）' AFTER `mac`;
