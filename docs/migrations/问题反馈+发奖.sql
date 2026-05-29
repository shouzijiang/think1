-- 使用前改这两个变量即可
SET @feedback_id = 87;   -- pun_game_feedback.id，执行前在表里查到对应行
SET @quota_add = 5;

SET @target_user_id = (SELECT `user_id` FROM `pun_game_feedback` WHERE `id` = @feedback_id LIMIT 1);
SET @feedback_content = (SELECT `content` FROM `pun_game_feedback` WHERE `id` = @feedback_id LIMIT 1);

-- 执行前可先预览：SELECT @feedback_id, @target_user_id, @feedback_content, @quota_add;

-- 指定用户邮件
INSERT INTO `pun_game_mail` (`scope`, `target_user_id`, `sender_user_id`, `title`, `content`, `is_published`)
VALUES (
  'user',
  @target_user_id,
  NULL,
  'bug反馈回复',
  CONCAT('感谢您的反馈。【', @feedback_content, '】问题已采纳。奖励查看答案次数', @quota_add, '，奖励已发放。'),
  1
);

-- 仅指定 user_id 增加揭字次数
INSERT INTO `pun_user_hint_quota` (`user_id`, `quota`)
VALUES (@target_user_id, @quota_add)
ON DUPLICATE KEY UPDATE
  `quota` = `quota` + @quota_add,
  `updated_at` = NOW();

-- ========== 一、全站所有用户 +10 ==========
-- UPDATE `pun_user_hint_quota`
-- SET `quota` = `quota` + 10,
--     `updated_at` = NOW();
