-- 邀请人结算：全平台每日视频单价 + 打款记录
-- 执行前请确认表不存在；已有旧表见 alter_streamer_unit_price.sql
--
-- 单价换算（无需手改 pun_game_channel_unit_price）：
--   php think pun:sync-channel-unit-price --date=YYYY-MM-DD --total=当日视频总收入
--   php think pun:sync-channel-unit-price --all
-- 打款记录仍手填 pun_game_streamer_payout，详见 docs/SYSTEM_DOC.md

CREATE TABLE IF NOT EXISTS `pun_game_channel_unit_price` (
  `stat_date`           date          NOT NULL COMMENT '统计日',
  `video_total_amount`  decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '当日视频广告总收入(命令写入)',
  `video_unit_price`    decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT '单条视频单价=总价/次数，截断4位不四舍五入',
  `video_claim_count`   int unsigned  NOT NULL DEFAULT 0 COMMENT '除数快照：当日全站 reward_video 成功次数',
  `remark`              varchar(255)  DEFAULT NULL,
  `updated_at`          datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='买量渠道全平台每日视频单价';

CREATE TABLE IF NOT EXISTS `pun_game_streamer_payout` (
  `id`               int unsigned  NOT NULL AUTO_INCREMENT,
  `streamer_user_id` int unsigned  NOT NULL COMMENT '邀请人 users.id',
  `channel`          varchar(64)   NOT NULL COMMENT 'streamer_{userId}',
  `period_end`       date          NOT NULL COMMENT '本次已结算到的最后一天(含)',
  `paid_amount`      decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '本次实际打款金额',
  `paid_at`          datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `remark`           varchar(255)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_streamer_user` (`streamer_user_id`),
  KEY `idx_channel` (`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请人打款/结算记录';
