# 统一系统与接口说明文档

## 一、 系统架构
- **前端**：基于 UniApp 开发，兼容微信小程序等多端 (代码位于 `pun-game/` 目录)
- **后端**：基于 ThinkPHP 8 框架提供 API 服务 (代码位于根目录)
- **数据库**：MySQL
- **鉴权方式**：微信 `code` 登录换取 JWT Token，后续请求在 Header 中携带 `Authorization: Bearer <token>`。

本系统目前包含两套核心业务：**谐音梗图游戏** 和 **久坐提醒小程序**。

---

## 二、 数据库核心表结构

### 1. 公共与基础表
- **users (或 pun_game_user)**: 用户基础信息表（openid, nickname, avatar）

### 2. 谐音梗图游戏相关表
- **pun_game_rank**: 游戏排行榜（user_id, max_level, max_level_mid, max_level_xhs；**last_pass_at_beginner / last_pass_at_mid / last_pass_at_xhs** 为各轨道最近一次通关时间，用于该榜**同分排序**；`updated_at` 仍为行级更新时间。同分排序规则：`ORDER BY 该轨最高关 DESC, COALESCE(该轨 last_pass_at_*, updated_at) DESC`）
- **pun_game_level_progress**: 关卡进度表（`user_id`，`passed_levels` / `passed_levels_mid` / `passed_levels_xhs` 为 JSON 数组）
- **pun_user_hint_quota**: 揭字提示剩余次数（`user_id` 唯一，`quota` 非负整数；`total_used` 累计消耗次数，每成功揭一步 +1；与 `users` 表分离；新用户微信登录成功创建 `users` 行时由后端插入一行，默认 **`quota = 10`**、`total_used = 0`（与模型 `PunUserHintQuota::DEFAULT_QUOTA` 一致）；每成功调用一次 `/pun/level/reveal-hint` 揭一步扣 `quota` 1 且 `total_used` +1，单题揭字步数仍不超过该题答案字数）
- **pun_game_feedback**: 意见反馈表（user_id, type, content, contact）
- **pun_game_battle_record**: 1V1对战房间与记录表（room_id, creator_id, challenger_id, levels_json, creator_progress, challenger_progress, total_time_ms, winner_id, status）
- **pun_game_changelog**: 版本更新说明（首页弹窗，`version_code` 唯一，`body` 为每行一条或 JSON 数组）
- *(论坛相关表)*: 帖子表 (topic)、回复表 (reply)

### 3. 久坐提醒相关表
- **punch_records**: 打卡记录表
- **user_settings**: 用户设置（上下班时间、提醒间隔）
- **user_subscribes**: 订阅消息授权状态
- **message_logs**: 消息发送日志

---

## 三、 核心接口清单 (API)

基础 URL：`https://你的域名/api`

### 1. 公共用户模块
| 接口路径 | 方法 | 说明 | 是否需 Token |
| --- | --- | --- | --- |
| `/auth/wechat/login` | POST | 微信 code 登录换取 Token | ❌ |
| `/auth/user/update` | POST | 更新用户昵称和头像 | ✅ |

### 2. 谐音梗图游戏模块 (Pun Game)
| 接口路径 | 方法 | 说明 |
| --- | --- | --- |
| `/pun/level/progress` | GET | 获取当前进度（支持 `gameTier=beginner/mid/xhs`）；`data` 中含 `hintAnswerQuota`（揭字剩余次数）、`hintAnswerTotalUsed`（累计消耗答案次数，上线后统计） |
| `/pun/answer/submit` | POST | 提交答题结果（包含初级/中级/小红书专辑逻辑分支） |
| `/pun/level/reveal-hint` | POST | **分步揭字提示**（每次多揭示一字，未揭示位为 `_`，字与字之间空格分隔；步数服务端缓存）。需 Token。Body 见下表 |
| `/pun/level/share-reward` | POST | 分享奖励揭字次数（默认 `+1`）。需 Token。Body: `{ add?: number }` |
| `/pun/changelog/latest` | GET | 获取最新一条已发布的「本期更新」说明（无需 Token；无数据时 `data` 为 `null`） |
| `/pun/stats/home` | GET | 首页统计（无需 Token）：`players` 为 `pun_game_level_progress` 行数，`answers` 为全表 `JSON_LENGTH(passed_levels)+JSON_LENGTH(passed_levels_mid)` 之和 |
| `/pun/rank/list` | GET | 获取排行榜（支持分页及 `gameTier=beginner/mid/xhs` 区分；同分按**该模式** `last_pass_at_*`，无则回退行 `updated_at`） |
| `/pun/feedback/submit` | POST | 提交意见反馈 (`bug/suggest/other`) |
| `/pun/forum/list` | GET | 获取游戏论坛帖子列表 |
| `/pun/forum/detail` | GET | 获取帖子详情及评论列表 |
| `/pun/forum/topic/create` | POST | 发布新帖子 |
| `/pun/forum/reply/create` | POST | 回复帖子或二级评论 |
| `/pun/battle/create` | POST | 1V1：创建对战房间 |
| `/pun/battle/history` | GET | 1V1：获取个人历史对战记录 |
| `/pun/battle/rank` | GET | 1V1：全局对战排行榜（分页；统计已结束且有胜负的局：`win_count`/`lose_count`，不含平局），无需 Token |

#### 分步提示 `/pun/level/reveal-hint`（需登录）

| 字段 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `level` | int | ✅ | 关卡 ID（与题库配置一致） |
| `gameTier` | string | ✅ | `beginner` / `mid`（或 `intermediate`）/ `xhs` / `battle` |
| `roomId` | string | 对战必填 | 房间号 |
| `questionIndex` | int | 对战必填 | 本题在本局中的索引 `0`～`4` |

成功时 `data` 示例：`{ "hintText": "火 _ _", "step": 1, "maxSteps": 3, "isComplete": false, "hintAnswerQuota": 4 }`（`hintAnswerQuota` 为本笔扣减后的剩余次数）。同一用户同一题步数递增；**每成功揭一步扣 `pun_user_hint_quota.quota` 1**；次数不足返回业务错误「提示次数不足…」；本题步数用尽返回「本题提示已用尽」。

#### 分享奖励次数 `/pun/level/share-reward`（需登录）

请求体：`{ "add": 1 }`（可省略，默认 `1`）。  
成功返回示例：`{ "hintAnswerQuota": 12, "added": 1 }`。
前端约定：在用户触发转发时调用本接口——包括右上角菜单「转发」与带 `open-type="share"` 的按钮（均在 `onShareAppMessage` 回调里调度；按钮点击与回调约 900ms 内去重为一次请求）。风控仍由后端最小间隔限制。
风控：同一用户两次领奖最小间隔 **60 秒**；过于频繁时返回业务错误（含剩余等待秒数）。

### 2.1 谐音梗图 WebSocket 接口 (1V1 对战)
| 事件/指令 (`action`) | 发送方 | 说明 |
| --- | --- | --- |
| `auth` | Client -> Server | 连接建立后，客户端发送 Token 进行鉴权 |
| `join` | Client -> Server | 客户端请求加入指定房间 |
| `room_info` | Server -> Client | 广播房间最新状态；含 `creatorId`/`challengerId`（来自 DB，断线重连时仍可用）与在线连接上的 `creator`/`challenger` 用户信息 |
| `ready` | Client -> Server | 玩家点击“准备” |
| `start_game` | Server -> Client | 双方均准备后，广播游戏开始并下发5道题目 |
| `resume_game` | Server -> Client | 断线重连且房间已在进行中时下发；含 `levels`、`myProgress`、`opponentProgress`、`timePassed`、双方昵称等 |
| `progress` | Client -> Server | 玩家答对一题后上报进度（第几题，当前耗时） |
| `sync_progress` | Server -> Client | 广播双方的答题进度 |
| `finish` | Client -> Server | 玩家完成所有5题后上报总耗时 |
| `game_over` | Server -> Client | 一方先通关即结束，广播结算结果（胜负、`totalTimeMs` 本局总耗时；并下发 `myProgress` / `opponentProgress` 用于结算态 dots） |

### 3. 久坐提醒模块 (Standup App)
| 接口路径 | 方法 | 说明 |
| --- | --- | --- |
| `/punch/submit` | POST | 提交站立打卡记录 |
| `/punch/records` | GET | 分页获取打卡记录 |
| `/punch/statistics` | GET | 获取打卡统计（今日、连续、总数等） |
| `/settings/save` | POST | 保存用户提醒设置 |
| `/settings/get` | GET | 获取用户提醒设置 |
| `/subscribe/save` | POST | 保存微信订阅消息授权状态 |
| `/cron/send-remind` | GET | 内部定时任务：触发发送订阅提醒消息 |

---

## 四、 开发与联调指南

### 1. 前端 (UniApp) 注意事项
- **API 规范**：遵循 `uniapp-apis.mdc` 规范，禁止使用原生的 `fetch` 或 `window`，必须统一使用 `uni.request` 和 `uni.setStorageSync`。
- **中级与小红书关卡逻辑**：`mid` 与 `xhs` 关卡 `level` 均可能不连续，不能用 ID 大小判断进度，必须依赖接口 `/pun/level/progress` 返回的 `currentLevel`、`passedLevels`、`totalLevels` 渲染锁定/解锁状态。
- **首页更新弹窗**：进入首页请求 `/pun/changelog/latest`，与本地 `pun_changelog_seen_version`（`version_code`）比对，未读则弹窗；点「知道了」写入本地已读。
- **微信小程序转发/朋友圈**：官方 `app.json` / 页面 `*.json` **不包含** `enableShareAppMessage`、`enableShareTimeline`（写入会被开发者工具标为无效字段）；需在页面实现 `onShareAppMessage` / `onShareTimeline`，并在 `App.vue` 的 `onLaunch`/`onShow`（及 `useWechatPageShare` 等）中调用 `uni.showShareMenu({ menus: ['shareAppMessage','shareTimeline'] })` 打开右上角菜单能力。

### 2. 后端 (ThinkPHP 8) 注意事项
- **分层架构**：Controller 仅负责接收参数与返回统一格式的 JSON，复杂逻辑（如 AI 绘图请求、闯关跳级判定）需下沉到 Service 层。
- **中级/小红书防跳关**：在提交答案 `/pun/answer/submit` 且 `gameTier=mid|xhs` 时，后端用与 `/pun/level/progress` **相同的规则**算出当前应玩关卡 `currentLevel`（综合 `passed_levels_*` 与排行榜兜底）；仅当提交的 `level` 等于该 `currentLevel` 且答案全对时，才写入 `max_level_mid` / `max_level_xhs` 与 `passed_levels_*`，避免仅看排行榜导致与进度接口不一致、答对却不推进的问题，同时仍可防跳关。
- **分步提示**：`/pun/level/reveal-hint` 步数存于 **Cache（文件缓存等）**，非 DB；**揭字剩余次数**存于 **`pun_user_hint_quota`**（按用户一行）。对战模式会校验 `pun_game_battle_record` 与 `levels_json` 与题目一致。
- **定时任务**：依赖宿主机或 Docker 的 crontab 执行 `curl http://localhost/cron/send-remind` 以派发久坐提醒。
- **统一响应封装**：所有接口均需返回 `{ "code": 200, "message": "...", "data": {...} }` 格式。

### 3. 运维扩展（可选）
- **飞书机器人（对战开房/开局通知）**：在根目录 `.env` 配置 `FEISHU_WEBHOOK_URL`、`FEISHU_WEBHOOK_SECRET`（勿提交仓库）。逻辑见 `app/common/FeishuBotHelper.php`，由 `BattleService::createRoom` 与 `WebSocket` 对局开始时触发。

---

## 五、 文档维护约定

- **唯一总览**：新增或变更 HTTP 接口、WebSocket 事件、数据库表、前端 `api.js` 封装时，须同步更新本文档对应章节；详见项目根目录 `.cursor/rules/documentation.mdc`。
- **数据库脚本**：表结构变更同时维护 `docs/database.sql`。