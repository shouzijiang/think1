-- 一次性执行：为全站用户增加 10 次揭字配额（与 app/model/PunUserHintQuota::DEFAULT_QUOTA 对齐）
-- 前置：`pun_user_hint_quota` 表已存在；`user_id` 与 `users.id` 同型有符号 int(11)
-- 逻辑：先给「已有配额行」的用户 +10；再为「还没有配额行」的用户插入 quota=10
--       （顺序不可颠倒，否则新插入行会被 UPDATE 再加 10，变成 20）
-- 可选：与新建表默认一致，调整列默认值（不影响已有行数值）
-- ALTER TABLE `pun_user_hint_quota`
--   MODIFY `quota` int(10) unsigned NOT NULL DEFAULT 10 COMMENT '揭字剩余次数';

UPDATE `pun_user_hint_quota` SET `quota` = `quota` + 10;

INSERT INTO `pun_user_hint_quota` (`user_id`, `quota`)
SELECT u.`id`, 10
FROM `users` u
LEFT JOIN `pun_user_hint_quota` h ON h.`user_id` = u.`id`
WHERE h.`id` IS NULL;
