-- 买量渠道追踪迁移
-- 执行前请先备份

ALTER TABLE users
  ADD COLUMN `channel` varchar(64) DEFAULT NULL COMMENT '买量渠道来源' AFTER `openid`,
  ADD COLUMN `channel_at` datetime DEFAULT NULL COMMENT '首次来源时间' AFTER `channel`,
  ADD INDEX `idx_channel` (`channel`);

CREATE TABLE `pun_game_channel_events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `channel` varchar(64) NOT NULL,
  `event_type` varchar(64) NOT NULL COMMENT 'login / claim_reward_video / claim_daily_watch_ad_hint_1 等',
  `extra` json DEFAULT NULL COMMENT '附加数据（added、delta 等）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_channel_event` (`channel`, `event_type`, `created_at`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='买量渠道行为追踪';
