-- 已有数据库升级：聊天室（c=chat）改回旧版（controllers/chat.php + models/chat_model.php）
--
-- 新建库直接导入 schema.sql 即可，无需执行本文件。
-- 执行：mysql --default-character-set=utf8mb4 -u<user> -p <db> < database/migrations/20261101_chat.sql
--
-- 升级前：聊天消息存 Redis chat_messages（新版自定的 50 条全服列表）与 chat_last_read:{uid} 游标，
--         跑马灯历史存 broadcast_history，member 表没有禁言字段。
-- 升级后：member 新增旧版禁言 / 封号字段 status、statustime（聊天室发言按旧版：1 禁言、2 永久禁言时世界频道只发给自己）。
--         聊天消息按旧版存 Redis chat_message_0 / chat_country_{国家} / chat_message_{uid} 等（见 src/Model/Chat.php）。
--
-- 数据处理（升级前只有测试数据）：Redis chat_messages、chat_last_read:*、chat_msg_seq、broadcast_history
-- 是新版自定的，与旧版消息格式对不上，不转换，可自行删除。
-- 只需执行一次；重复执行会在 ADD COLUMN status 处报错退出。

ALTER TABLE `zc_sanguo_member`
    ADD COLUMN `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1、禁言 2、永久禁言 3、封号 4、永久封号（聊天室发言只认 1 / 2）' AFTER `missionid`,
    ADD COLUMN `statustime` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '禁言或封号截止时间（到期后清除 status）' AFTER `status`;
