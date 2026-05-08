-- 对战房间支持题库选择（xhs=小红书, mid=经典）
ALTER TABLE pun_game_battle_record
  ADD COLUMN question_bank varchar(20) NOT NULL DEFAULT 'xhs' COMMENT '题库标识：xhs=小红书, mid=经典'
  AFTER levels_json;
