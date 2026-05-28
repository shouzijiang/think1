-- 邀请人结算：单价表改为「当日视频总收入 + 自动换算单价」（已有旧 login_unit_price 表时执行）
-- 新库请直接用 add_streamer_settlement.sql

ALTER TABLE `pun_game_channel_unit_price`
  DROP COLUMN `login_unit_price`;

ALTER TABLE `pun_game_channel_unit_price`
  ADD COLUMN `video_total_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '当日视频广告总收入(手填)' AFTER `stat_date`,
  ADD COLUMN `video_claim_count` int unsigned NOT NULL DEFAULT 0 COMMENT '除数：当日全站 reward_video 成功次数' AFTER `video_unit_price`;

ALTER TABLE `pun_game_channel_unit_price`
  MODIFY COLUMN `video_unit_price` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT '单条视频单价=总价/次数，截断4位不四舍五入';
