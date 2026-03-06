# 线上 pun_game_level_progress 表结构迁移说明

旧结构：每通过一关一条记录 `(user_id, level, passed)`，数据量大。  
新结构：每用户一行，`passed_levels` 存 JSON 数组 `[1,2,3,...]`。

---

## 一、迁移前准备

1. **备份表**（必做）  
   ```sql
   CREATE TABLE pun_game_level_progress_backup AS SELECT * FROM pun_game_level_progress;
   ```

2. 确认当前表结构为旧版（存在 `level`、`passed` 列）。

---

## 二、执行迁移（推荐在低峰期、短停写或只读时执行）

### 步骤 1：建新表

```sql
CREATE TABLE `pun_game_level_progress_new` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '用户id',
  `passed_levels` json NOT NULL COMMENT '已通过关卡号数组 [1,2,3,...]',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='谐音梗图游戏关卡进度';
```

### 步骤 2：把旧表数据按用户聚合写入新表

```sql
INSERT INTO pun_game_level_progress_new (user_id, passed_levels, created_at, updated_at)
SELECT
  user_id,
  JSON_ARRAYAGG(level),
  MIN(created_at),
  MAX(updated_at)
FROM pun_game_level_progress
WHERE passed = 1
GROUP BY user_id;
```

- **说明**：`JSON_ARRAYAGG(level)` 不带 `ORDER BY`，兼容 MySQL 5.7。数组顺序不影响逻辑，接口返回前会整理。
- 若某用户没有任何 `passed=1` 的记录，不会出现在新表，等价于该用户 `passed_levels = []`（代码里查不到会当空数组处理）。
- MySQL 5.7.22+ 支持 `JSON_ARRAYAGG`。若版本较低，可用下面「步骤 2 备选」用程序迁移。

### 步骤 2 备选（无 JSON_ARRAYAGG 时）

用脚本从旧表按 `user_id` 分组查出所有 `level`，组成 JSON 数组再插入新表（或先导出再导入）。

### 步骤 3：换表

```sql
RENAME TABLE
  pun_game_level_progress TO pun_game_level_progress_old,
  pun_game_level_progress_new TO pun_game_level_progress;
```

### 步骤 4：部署新代码

部署已改好的应用（Model 使用 `passed_levels` JSON，Service 读/写新结构，接口返回 `totalLevels`）。

### 步骤 5：验证与清理

- 抽查几个 `user_id`，对比 `pun_game_level_progress_old` 中 `passed=1` 的 `level` 列表与当前表 `passed_levels` 是否一致。
- 确认无误后删除旧表（可选）：  
  `DROP TABLE pun_game_level_progress_old;`  
  备份表可保留一段时间再删：  
  `DROP TABLE pun_game_level_progress_backup;`

---

## 三、若不想换表名（直接改原表）

不建新表、直接改原表时，需先迁移数据到临时列再改结构，步骤更多且锁表时间更长，**更推荐上面“建新表再 RENAME”的方式**。若必须改原表，大致顺序为：

1. 增加新列 `passed_levels_json`（如 TEXT 或 JSON）。
2. 用脚本或 SQL 按用户聚合旧数据，写入 `passed_levels_json`。
3. 删除旧列 `level`、`passed`，将 `passed_levels_json` 改名为 `passed_levels` 并改为 NOT NULL。
4. 删除唯一键 `uk_user_level`、`idx_user_passed`，添加 `uk_user_id`。

建议优先用「建新表 + RENAME」方案，更简单、可回滚（把表名改回去即可）。

---

## 四、回滚（若新结构有问题）

1. 停用新代码，回滚到旧版本。
2. 把表名改回旧结构在用的表名：  
   `RENAME TABLE pun_game_level_progress TO pun_game_level_progress_new, pun_game_level_progress_old TO pun_game_level_progress;`
3. 用备份表恢复数据（若曾误删）：从 `pun_game_level_progress_backup` 再导回。

---

## 五、接口变化（给前端）

- **GET /pun/level/progress** 响应中增加 `totalLevels`（当前为 270）。  
- 前端用 `totalLevels` 做总关卡数，按 `1..totalLevels` 遍历；`passedLevels` 仍为已通过关卡号数组，结构不变。
