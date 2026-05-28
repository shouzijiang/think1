-- 打款表去掉快照字段，仅保留「给谁、结到哪一天、打了多少」
-- 执行前：DESC pun_game_streamer_payout; 确认 login_count / video_count / gross_amount 存在

ALTER TABLE `pun_game_streamer_payout`
  DROP COLUMN `login_count`,
  DROP COLUMN `video_count`,
  DROP COLUMN `gross_amount`;
