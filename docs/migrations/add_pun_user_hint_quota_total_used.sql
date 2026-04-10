-- 揭字配额表：累计消耗次数（上线后自增统计；历史消耗无法回溯，旧用户从 0 起计）
ALTER TABLE `pun_user_hint_quota`
  ADD COLUMN `total_used` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '累计消耗答案次数（每成功揭一步计1）' AFTER `quota`;
