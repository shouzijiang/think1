-- 新增 pun 游戏站内信邮件表与已读记录表

-- 1) 邮件主体
CREATE TABLE `pun_game_mail` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `scope` varchar(10) NOT NULL COMMENT 'all-全服，user-单玩家',
  `target_user_id` int(11) unsigned DEFAULT NULL COMMENT 'scope=user 时目标用户ID',
  `sender_user_id` int(11) unsigned DEFAULT NULL COMMENT '发送方用户ID',
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '标题',
  `content` text NOT NULL COMMENT '正文（纯文本）',
  `is_published` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '是否上架展示',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '发送/创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最后更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_scope_target` (`scope`, `target_user_id`),
  KEY `idx_published_created` (`is_published`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='谐音梗图游戏站内信邮件主体';

-- 2) 邮件已读记录
CREATE TABLE `pun_game_mail_reads` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `mail_id` int(11) unsigned NOT NULL COMMENT '邮件ID',
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `read_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '读取时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mail_user` (`mail_id`, `user_id`),
  KEY `idx_user_id` (`user_id`, `read_at`),
  KEY `idx_mail_id` (`mail_id`, `read_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='谐音梗图游戏站内信已读记录';

-- 3) 运维入信示例（按需执行，勿照抄 ID）
-- 全服：
-- INSERT INTO `pun_game_mail` (`scope`, `target_user_id`, `sender_user_id`, `title`, `content`, `is_published`)
-- VALUES ('all', NULL, NULL, '维护公告', '今晚02:00-04:00 例行维护。', 1);
-- 指定用户（target_user_id = users.id）：
-- INSERT INTO `pun_game_mail` (`scope`, `target_user_id`, `sender_user_id`, `title`, `content`, `is_published`)
-- VALUES ('user', 12345, NULL, '补偿说明', '感谢您的反馈，已为您处理。', 1);

