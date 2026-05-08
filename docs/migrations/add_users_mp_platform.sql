-- users 表新增小程序来源（微信 / 抖音）
-- MySQL 5.7+；上线执行前请备份；已执行过请勿重复执行。
--
-- 校验：DESC `users`; 或 SHOW COLUMNS FROM `users` LIKE 'mp_platform';

ALTER TABLE `users`
  ADD COLUMN `mp_platform` varchar(20) NOT NULL DEFAULT 'weixin'
    COMMENT '小程序来源：weixin-微信 douyin-抖音'
    AFTER `openid`;

-- 历史数据：openid 带 douyin: 前缀的为抖音用户（与 AuthService 约定一致）
UPDATE `users` SET `mp_platform` = 'douyin' WHERE `openid` LIKE 'douyin:%';

ALTER TABLE `users`
  ADD KEY `idx_mp_platform` (`mp_platform`);
