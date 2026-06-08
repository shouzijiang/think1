-- pun_game_channel_unit_price 新增 video_event_count 字段
-- 存储来自 pun_game_channel_events 的当日 reward_video 事件总条数（与 video_claim_count 区分数据源）

ALTER TABLE `pun_game_channel_unit_price`
  ADD COLUMN `video_event_count` int unsigned NOT NULL DEFAULT 0 COMMENT '除数：当日全站 reward_video 事件条数(来自pun_game_channel_events)' AFTER `video_claim_count`;
