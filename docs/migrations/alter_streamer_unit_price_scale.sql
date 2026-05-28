-- 单价字段改为 4 位小数（截断存储，不四舍五入）
-- 改完后请重跑：php think pun:sync-channel-unit-price --all

ALTER TABLE `pun_game_channel_unit_price`
  MODIFY COLUMN `video_unit_price` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT '单条视频单价=总价/次数，截断4位不四舍五入';
