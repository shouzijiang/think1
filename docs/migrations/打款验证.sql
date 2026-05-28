
-- 写法 1：按日期统计数量（推荐）
SELECT
  COUNT(*) AS reward_video_total
FROM
  `sofun_online`.`pun_reward_claim_record`
WHERE
  `claim_type` = 'reward_video'
  AND `claim_date` >= '2026-05-27';

  
-- 看 streamer_1 每天有多少 reward_video（只有这个计收益）
SELECT DATE(created_at) AS stat_date, COUNT(*) AS video_count
FROM pun_game_channel_events
WHERE channel = 'streamer_1'
  AND event_type = 'reward_video'
GROUP BY DATE(created_at)
ORDER BY stat_date;

-- streamer_1132 总收益模拟（对应 API totalGross）
SELECT
  e.stat_date,
  e.video_count,
  IFNULL(p.video_unit_price, 0.0100) AS unit_price,
  TRUNCATE(e.video_count * IFNULL(p.video_unit_price, 0.0100), 4) AS day_gross
FROM (
  SELECT DATE(created_at) AS stat_date, COUNT(*) AS video_count
  FROM pun_game_channel_events
  WHERE channel = 'streamer_1'
    AND event_type = 'reward_video'
  GROUP BY DATE(created_at)
) e
LEFT JOIN pun_game_channel_unit_price p ON p.stat_date = e.stat_date
ORDER BY e.stat_date;

-- 汇总一行：内部精度 4 位 + 展示精度 3 位（与接口 formatDisplayAmount 一致）
SELECT
  SUM(TRUNCATE(e.video_count * IFNULL(p.video_unit_price, 0.0100), 4)) AS total_gross_4dp,
  TRUNCATE(
    SUM(TRUNCATE(e.video_count * IFNULL(p.video_unit_price, 0.0100), 4)),
    3
  ) AS total_gross_display_3dp
FROM (
  SELECT DATE(created_at) AS stat_date, COUNT(*) AS video_count
  FROM pun_game_channel_events
  WHERE channel = 'streamer_1'
    AND event_type = 'reward_video'
  GROUP BY DATE(created_at)
) e
LEFT JOIN pun_game_channel_unit_price p ON p.stat_date = e.stat_date;


-- . 顺带核对打款 / 余额
SELECT
  TRUNCATE(
    (
      SELECT SUM(TRUNCATE(e.video_count * IFNULL(p.video_unit_price, 0.0100), 4))
      FROM (
        SELECT DATE(created_at) AS stat_date, COUNT(*) AS video_count
        FROM pun_game_channel_events
        WHERE channel = 'streamer_1132' AND event_type = 'reward_video'
        GROUP BY DATE(created_at)
      ) e
      LEFT JOIN pun_game_channel_unit_price p ON p.stat_date = e.stat_date
    ),
    3
  ) AS total_gross,
  IFNULL((
    SELECT SUM(paid_amount)
    FROM pun_game_streamer_payout
    WHERE streamer_user_id = 1132
  ), 0) AS total_paid,
  TRUNCATE(
    (
      SELECT SUM(TRUNCATE(e.video_count * IFNULL(p.video_unit_price, 0.0100), 4))
      FROM (
        SELECT DATE(created_at) AS stat_date, COUNT(*) AS video_count
        FROM pun_game_channel_events
        WHERE channel = 'streamer_1132' AND event_type = 'reward_video'
        GROUP BY DATE(created_at)
      ) e
      LEFT JOIN pun_game_channel_unit_price p ON p.stat_date = e.stat_date
    ) - IFNULL((
      SELECT SUM(paid_amount) FROM pun_game_streamer_payout WHERE streamer_user_id = 1132
    ), 0),
    3
  ) AS balance;
  


