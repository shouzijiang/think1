-- 每日答题次数统计表（用于“答满20题可领取每日登录奖励+5次”）
CREATE TABLE `pun_daily_answer_stat` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `stat_date` date NOT NULL COMMENT '统计日期（Asia/Shanghai）',
  `answer_count` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '当日答对次数（仅答对计）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_date` (`user_id`, `stat_date`),
  KEY `idx_stat_date` (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='每日答题次数统计';

-- 校验
-- DESC `pun_daily_answer_stat`;
