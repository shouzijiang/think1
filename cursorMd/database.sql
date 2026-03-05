-- 久坐提醒小程序数据库设计
-- 数据库字符集: utf8mb4
-- 数据库排序规则: utf8mb4_unicode_ci

-- 1. 用户表
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `openid` varchar(100) NOT NULL COMMENT '微信openid',
  `unionid` varchar(100) DEFAULT NULL COMMENT '微信unionid',
  `nickname` varchar(100) DEFAULT NULL COMMENT '用户昵称',
  `avatar` varchar(255) DEFAULT NULL COMMENT '用户头像URL',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_openid` (`openid`),
  KEY `idx_unionid` (`unionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

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
-- 6. 排行榜表（按闯关最高关排序，一用户一条）
CREATE TABLE `pun_game_rank` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '用户id',
  `nickname` varchar(64) DEFAULT NULL COMMENT '昵称冗余',
  `avatar` varchar(512) DEFAULT NULL COMMENT '头像冗余',
  `max_level` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '闯到的最高关卡号 1~253',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最近一次通过关卡的时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_id` (`user_id`),
  KEY `idx_max_level` (`max_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='谐音梗图游戏排行榜';

-- 7. 用户关卡进度表
CREATE TABLE `pun_game_level_progress` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '用户id',
  `level` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '关卡号 1~253',
  `passed` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已通过：0否 1是',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_level` (`user_id`,`level`),
  KEY `idx_user_passed` (`user_id`,`passed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='谐音梗图游戏关卡进度';


