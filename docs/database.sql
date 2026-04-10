-- 久坐提醒小程序数据库设计
-- 数据库字符集: utf8mb4
-- 数据库排序规则: utf8mb4_unicode_ci

-- 1. 用户表
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `openid` varchar(100) NOT NULL COMMENT '微信openid',
  `unionid` varchar(100) DEFAULT NULL COMMENT '微信unionid',
  `nickname` varchar(100) DEFAULT NULL COMMENT '用户昵称',
  `avatar` mediumtext DEFAULT NULL COMMENT '用户头像URL或 base64 data URL',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_openid` (`openid`),
  KEY `idx_unionid` (`unionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- 若线上 users 表已存在且需支持 base64 头像，可执行：
-- ALTER TABLE `users` MODIFY COLUMN `avatar` mediumtext DEFAULT NULL COMMENT '用户头像URL或 base64 data URL';

-- 2. 打卡记录表
CREATE TABLE `punch_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `timestamp` bigint(20) NOT NULL COMMENT '打卡时间戳（毫秒）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_timestamp` (`user_id`, `timestamp`),
  KEY `idx_timestamp` (`timestamp`),
  CONSTRAINT `fk_punch_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='打卡记录表';

-- 3. 用户设置表
CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '设置ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用提醒：1启用，0关闭',
  `work_start_time` varchar(5) NOT NULL DEFAULT '09:00' COMMENT '上班时间，格式：HH:mm',
  `work_end_time` varchar(5) NOT NULL DEFAULT '18:00' COMMENT '下班时间，格式：HH:mm',
  `remind_interval` int(11) NOT NULL DEFAULT 2 COMMENT '提醒间隔（小时），1-6',
  `last_remind_time` bigint(20) DEFAULT NULL COMMENT '最后提醒时间戳（毫秒）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_id` (`user_id`),
  CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户设置表';

-- 4. 订阅消息表
CREATE TABLE `user_subscribes` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '订阅ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `template_id` varchar(100) NOT NULL COMMENT '订阅消息模板ID',
  `subscribe_status` varchar(20) NOT NULL COMMENT '授权状态：accept-接受，reject-拒绝',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_template` (`user_id`, `template_id`),
  KEY `idx_user_status` (`user_id`, `subscribe_status`),
  CONSTRAINT `fk_subscribe_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户订阅消息表';

-- 5. 消息发送日志表（可选，用于记录发送历史）
CREATE TABLE `message_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `template_id` varchar(100) NOT NULL COMMENT '模板ID',
  `send_status` varchar(20) NOT NULL COMMENT '发送状态：success-成功，failed-失败',
  `error_msg` text DEFAULT NULL COMMENT '错误信息',
  `send_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '发送时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_time` (`user_id`, `send_time`),
  KEY `idx_status` (`send_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='消息发送日志表';

-- 初始化数据（可选）
-- 如果需要默认数据，可以在这里插入

-- ========== 谐音梗图游戏 pun ==========
-- 6. 排行榜表（按闯关最高关排序，一用户一条；昵称/头像从 users 表读，不冗余）
CREATE TABLE `pun_game_rank` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '用户id',
  `max_level` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '闯到的最高关卡号 1~253',
  `max_level_mid` int(11) NOT NULL DEFAULT '-1' COMMENT '中级闯到的最高关卡号，未参与为-1',
  `max_level_xhs` int(11) NOT NULL DEFAULT '-1' COMMENT '小红书专辑闯到的最高关卡号，未参与为-1',
  `last_pass_at_beginner` datetime DEFAULT NULL COMMENT '初级最近一次通关时间（该榜同分排序，空则回退 updated_at）',
  `last_pass_at_mid` datetime DEFAULT NULL COMMENT '中级最近一次通关时间（该榜同分排序）',
  `last_pass_at_xhs` datetime DEFAULT NULL COMMENT '小红书专辑最近一次通关时间（该榜同分排序）',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '行级最近更新时间（任一看榜字段变更）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_id` (`user_id`),
  KEY `idx_max_level` (`max_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='谐音梗图游戏排行榜';

-- 7. 用户关卡进度表（每用户一行，已通过关卡存 JSON 数组）
CREATE TABLE `pun_game_level_progress` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '用户id',
  `passed_levels` json NOT NULL COMMENT '已通过关卡号数组 [1,2,3,...]',
  `passed_levels_mid` json DEFAULT NULL COMMENT '中级已通过关卡号数组 [0,1,2,...]',
  `passed_levels_xhs` json DEFAULT NULL COMMENT '小红书专辑已通过关卡号数组 [1,...]',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='谐音梗图游戏关卡进度';

-- 7b. 揭字提示剩余次数（每用户一行，与 users 解耦）
CREATE TABLE `pun_user_hint_quota` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户id',
  `quota` int(10) unsigned NOT NULL DEFAULT '10' COMMENT '揭字剩余次数（每成功揭一步扣1，单题仍受答案字数封顶）',
  `total_used` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '累计消耗答案次数（每成功揭一步计1，用于资产页展示）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_id` (`user_id`),
  CONSTRAINT `fk_pun_hint_quota_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='谐音梗图揭字次数配额';

-- 7c. 老库一次性给全员 +10 次揭字：见 `docs/migrations/add_hint_quota_10_all_users.sql`（勿重复执行）
-- 7d. 揭字累计消耗：见 `docs/migrations/add_pun_user_hint_quota_total_used.sql`（勿重复执行）

-- 若线上已存在表，按以下顺序执行迁移 SQL：
-- 1) 排行榜表新增 xhs 最高关字段
ALTER TABLE `pun_game_rank`
  ADD COLUMN `max_level_xhs` int(11) NOT NULL DEFAULT '-1' COMMENT '小红书专辑闯到的最高关卡号，未参与为-1' AFTER `max_level_mid`;

-- 2) 进度表新增 xhs 已通过关卡数组字段
ALTER TABLE `pun_game_level_progress`
  ADD COLUMN `passed_levels_xhs` json DEFAULT NULL COMMENT '小红书专辑已通过关卡号数组 [1,...]' AFTER `passed_levels_mid`;

-- 3) 排行榜：分模式「最近通关时间」用于同分排序（与行级 updated_at 解耦）
ALTER TABLE `pun_game_rank`
  ADD COLUMN `last_pass_at_beginner` datetime DEFAULT NULL COMMENT '初级最近一次通关时间（该榜同分排序）' AFTER `max_level_xhs`,
  ADD COLUMN `last_pass_at_mid` datetime DEFAULT NULL COMMENT '中级最近一次通关时间（该榜同分排序）' AFTER `last_pass_at_beginner`,
  ADD COLUMN `last_pass_at_xhs` datetime DEFAULT NULL COMMENT '小红书专辑最近一次通关时间（该榜同分排序）' AFTER `last_pass_at_mid`;

-- 可选：老数据按行级 updated_at 回填各轨时间（有进度才填，减轻同分排序突变）
-- UPDATE `pun_game_rank` SET `last_pass_at_beginner` = `updated_at` WHERE `last_pass_at_beginner` IS NULL AND `max_level` > 0;
-- UPDATE `pun_game_rank` SET `last_pass_at_mid` = `updated_at` WHERE `last_pass_at_mid` IS NULL AND `max_level_mid` >= 0;
-- UPDATE `pun_game_rank` SET `last_pass_at_xhs` = `updated_at` WHERE `last_pass_at_xhs` IS NULL AND `max_level_xhs` >= 0;

-- 4) 校验字段存在性
-- DESC `pun_game_rank`;
-- DESC `pun_game_level_progress`;

-- 8. 意见反馈表
CREATE TABLE `pun_game_feedback` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '用户id',
  `type` varchar(32) NOT NULL DEFAULT '' COMMENT '反馈类型：空=未选，bug/suggest/other',
  `content` text NOT NULL COMMENT '反馈内容',
  `contact` varchar(128) NOT NULL DEFAULT '' COMMENT '联系方式（选填）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '提交时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='谐音梗图游戏意见反馈';

-- 9. 论坛帖子表
CREATE TABLE `pun_forum_topic` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '发布人用户id',
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '帖子标题（可选）',
  `content` text NOT NULL COMMENT '帖子内容',
  `view_count` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '浏览量',
  `reply_count` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '回复数',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：1正常，0隐藏/删除',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '发布时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最后更新/回复时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status_updated` (`status`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='谐音梗图论坛帖子表';

-- 10. 论坛回复表
CREATE TABLE `pun_forum_reply` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` int(11) unsigned NOT NULL COMMENT '所属帖子id',
  `user_id` int(11) unsigned NOT NULL COMMENT '回复人用户id',
  `reply_to_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '回复的目标回复id(0表示直接回复帖子)',
  `content` text NOT NULL COMMENT '回复内容',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：1正常，0隐藏/删除',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '回复时间',
  PRIMARY KEY (`id`),
  KEY `idx_topic_id` (`topic_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='谐音梗图论坛回复表';

-- 11. 好友1V1对战房间与记录表
CREATE TABLE `pun_game_battle_record` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `room_id` varchar(32) NOT NULL COMMENT '房间号，如：6位随机码',
  `creator_id` int(11) unsigned NOT NULL COMMENT '创建者/房主 user_id',
  `challenger_id` int(11) unsigned DEFAULT NULL COMMENT '挑战者 user_id',
  `levels_json` text NOT NULL COMMENT '本局的5道题目ID JSON数组',
  `creator_progress` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '创建者已答题进度(0-5)',
  `challenger_progress` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '挑战者已答题进度(0-5)',
  `total_time_ms` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '本局总耗时(毫秒)，先通关方完成耗时',
  `winner_id` int(11) unsigned DEFAULT NULL COMMENT '获胜者 user_id，平局或未结束为null',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态：0-等待中，1-对战中，2-已结束',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_room_id` (`room_id`),
  KEY `idx_creator` (`creator_id`),
  KEY `idx_challenger` (`challenger_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='好友1V1对战记录表';

-- 12. 游戏版本更新说明（首页「本期更新」弹窗）
CREATE TABLE `pun_game_changelog` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `version_code` varchar(32) NOT NULL COMMENT '版本标识，唯一，用于客户端本地已读判断',
  `title` varchar(128) NOT NULL DEFAULT '' COMMENT '弹窗标题',
  `body` mediumtext NOT NULL COMMENT '正文：每行一条要点，或 JSON 字符串数组',
  `is_published` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '1 上架展示，0 下架',
  `published_at` datetime DEFAULT NULL COMMENT '发布时间（排序用）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_version_code` (`version_code`),
  KEY `idx_published_at` (`is_published`, `published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='谐音梗图游戏版本更新说明';

INSERT INTO `pun_game_changelog` (`version_code`, `title`, `body`, `is_published`, `published_at`) VALUES
('2026.04.02', '本期更新',
'好友 1V1 对战：创建房间与好友实时对战。\n经典：经典：支持答案查看。\n新增通用分享能力，各页转发/朋友圈。\n初级闯关、排行榜、我的关卡、共创与意见反馈等页面体验与文案优化。',
1, NOW());

