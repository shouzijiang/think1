# 统一系统与接口说明文档

## 一、 系统架构
- **前端**：基于 UniApp 开发，兼容微信小程序等多端 (代码位于 `pun-game/` 目录)
- **后端**：基于 ThinkPHP 8 框架提供 API 服务 (代码位于根目录)
- **数据库**：MySQL
- **鉴权方式**：小程序 `code` 登录（微信/抖音）换取 JWT Token，后续请求在 Header 中携带 `Authorization: Bearer <token>`。

本系统目前包含两套核心业务：**谐音梗猜一猜游戏** 和 **久坐提醒小程序**。

---

## 二、 数据库核心表结构

### 1. 公共与基础表
- **users (或 pun_game_user)**: 用户基础信息表（openid, **mp_platform**（`weixin`/`douyin`，与登录端一致）, nickname, avatar，**last_login_at** 最近登录时间；小程序登录成功时写入，且已登录态会通过 `/auth/touch-login` 心跳按天刷新，`/cron/send-remind` 等逻辑依赖；老库迁移见 `docs/migrations/add_users_last_login_at.sql`；**小程序来源字段**见 `docs/migrations/add_users_mp_platform.sql`）

### 2. 谐音梗猜一猜游戏相关表
- **pun_game_rank**: 游戏排行榜（user_id, max_level, max_level_mid, max_level_xhs；**last_pass_at_beginner / last_pass_at_mid / last_pass_at_xhs** 为各轨道最近一次通关时间，用于该榜**同分排序**；`updated_at` 仍为行级更新时间。同分排序规则：`ORDER BY 该轨最高关 DESC, COALESCE(该轨 last_pass_at_*, updated_at) DESC`）
- **pun_game_level_progress**: 关卡进度表（`user_id`，`passed_levels` / `passed_levels_mid` / `passed_levels_xhs` 为 JSON 数组）
- **pun_user_hint_quota**: 揭字提示剩余次数（`user_id` 唯一，`quota` 非负整数；`total_used` 累计消耗次数，每成功揭一步 +1；与 `users` 表分离；新用户微信登录成功创建 `users` 行时由后端插入一行，默认 **`quota = 10`**、`total_used = 0`（与模型 `PunUserHintQuota::DEFAULT_QUOTA` 一致）；每成功调用一次 `/pun/level/reveal-hint` 揭一步扣 `quota` 1 且 `total_used` +1，单题揭字步数仍不超过该题答案字数）
- **pun_game_feedback**: 意见反馈表（user_id, type, content, contact）
- **pun_game_battle_record**: 1V1对战房间与记录表（room_id, creator_id, challenger_id, levels_json, creator_progress, challenger_progress, total_time_ms, winner_id, status）
- **pun_game_changelog**: 版本更新说明（首页弹窗，`version_code` 唯一，`body` 为每行一条或 JSON 数组）
- **pun_game_mail**: 游戏站内信邮件主体表（支持全服 all 与单玩家 user）
- **pun_game_mail_reads**: 游戏站内信已读记录表（按 user_id/mail_id 记录 read_at，用于列表角标）
- **pun_reward_claim_record**: 统一领奖记录（`share` / `reward_video` / `daily_noon_hint_5` / `daily_watch_ad_hint_1` / `daily_battle_3_hint_3` / `permanent_set_avatar` / `permanent_set_nickname` / `permanent_my_mini_program_hint_3` 等）；**仅成功领取**写入一行（`status=success`）；业务拒绝与异常失败不落库（`status` 字段历史或其它脚本仍可为 rejected/failed）。若线上曾为 `permanent_my_mini_program_hint_5`，可执行 `docs/migrations/rename_permanent_my_mini_program_claim_type_hint_5_to_hint_3.sql` 统一类型名。
- **pun_daily_answer_stat**: 每日答对次数统计（`user_id + stat_date` 唯一；用于“每日答对满20题后可领登录奖励+5次”）
- *(论坛相关表)*: 帖子表 (topic)、回复表 (reply)

### 3. 久坐提醒相关表
- **user_subscribes**: 订阅消息授权状态
- **message_logs**: 消息发送日志

---

## 三、 核心接口清单 (API)

基础 URL：`https://你的域名/api`

### 1. 公共用户模块
| 接口路径 | 方法 | 说明 | 是否需 Token |
| --- | --- | --- | --- |
| `/auth/wechat/login` | POST | 微信 code 登录换取 Token | ❌ |
| `/auth/douyin/login` | POST | 抖音 code 登录换取 Token | ❌ |
| `/auth/user/update` | POST | 更新用户昵称和头像 | ✅ |
| `/auth/touch-login` | POST | 已登录心跳刷新 `last_login_at`（用于本地 token 命中时补写最近登录） | ✅ |

### 2. 谐音梗猜一猜游戏模块 (Pun Game)
| 接口路径 | 方法 | 说明 |
| --- | --- | --- |
| `/pun/level/progress` | GET | 获取当前进度（支持 `gameTier=beginner/mid/xhs`）；`data` 中含 `hintAnswerQuota`（揭字剩余次数）、`hintAnswerTotalUsed`（累计消耗答案次数）、`hintAnswerShareDailyMax`（分享日上限）、`hintAnswerShareDailyClaimed`（当日分享已领取次数，跨设备一致）、`dailyAnswerCount`（今日答对题数）、`dailyAnswerRequired`（登录奖励所需答对题数）、`dailyNoonTaskClaimed`（答题奖励是否已领）、`dailyAdTaskCount`（今日看广告任务已领取次数）、`dailyBattleCount`（当日已完成1V1局数）、`dailyBattleRequired`（1V1任务达标局数）、`dailyBattleTaskClaimed`（1V1任务是否已领）、`avatarTaskClaimed` / `nicknameTaskClaimed` / `myMiniProgramTaskClaimed`（永久任务是否已领） |
| `/pun/answer/submit` | POST | 提交答题结果（包含初级/中级/小红书专辑逻辑分支）；答对时 `data` 直接返回 `nextLevel`、`totalLevels`，前端可直接跳关，无需再请求 `/pun/level/progress` |
| `/pun/level/reveal-hint` | POST | **分步揭字提示**（每次多揭示一字，未揭示位为 `_`，字与字之间空格分隔；步数服务端缓存）。需 Token。Body 见下表 |
| `/pun/reward/claim` | POST | 统一领奖接口：`type=share/reward_video/daily_noon_hint_5/daily_watch_ad_hint_1/daily_battle_3_hint_3/permanent_set_avatar/permanent_set_nickname/permanent_my_mini_program_hint_3` 等；**成功领取**时写 `pun_reward_claim_record` |
| `/pun/changelog/latest` | GET | 获取最新一条已发布的「本期更新」说明（无需 Token；无数据时 `data` 为 `null`） |
| `/pun/stats/home` | GET | 首页统计（无需 Token）：`players` 为 `pun_game_level_progress` 行数，`answers` 为全表 `JSON_LENGTH(passed_levels)+JSON_LENGTH(passed_levels_mid)` 之和 |
| `/pun/rank/list` | GET | 获取排行榜（支持分页及 `gameTier=beginner/mid/xhs` 区分；同分按**该模式** `last_pass_at_*`，无则回退行 `updated_at`）；响应含 `nickname`/`avatar`；**小程序端**无头像时用与首页一致的默认图 `index-badge.jpg`，昵称脱敏：仅首字可见，其余每位一个 `*`；昵称为空时显示 `*` |
| `/pun/feedback/submit` | POST | 提交意见反馈 (`bug/suggest/other`) |
| `/pun/mail/list` | GET | 信箱列表（当前用户可见：全服+单发），返回每封邮件 `isRead/readAt` |
| `/pun/mail/detail` | GET | 邮件详情，并自动写入已读记录 |
| `/pun/forum/list` | GET | 获取游戏论坛帖子列表 |
| `/pun/forum/detail` | GET | 获取帖子详情及评论列表 |
| `/pun/forum/topic/create` | POST | 发布新帖子 |
| `/pun/forum/reply/create` | POST | 回复帖子或二级评论 |
| `/pun/battle/create` | POST | 1V1：创建对战房间 |
| `/pun/battle/history` | GET | 1V1：获取个人历史对战记录 |
| `/pun/battle/rank` | GET | 1V1：全局对战排行榜（分页；统计已结束且有胜负的局：`win_count`/`lose_count`，不含平局），无需 Token；**小程序端**头像默认图与昵称脱敏同 `/pun/rank/list` |

#### 分步提示 `/pun/level/reveal-hint`（需登录）

| 字段 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `level` | int | ✅ | 关卡 ID（与题库配置一致） |
| `gameTier` | string | ✅ | `beginner` / `mid`（或 `intermediate`）/ `xhs` / `battle` |
| `roomId` | string | 对战必填 | 房间号 |
| `questionIndex` | int | 对战必填 | 本题在本局中的索引 `0`～`4` |

成功时 `data` 示例：`{ "hintText": "火 _ _", "step": 1, "maxSteps": 3, "isComplete": false, "hintAnswerQuota": 4 }`（`hintAnswerQuota` 为本笔扣减后的剩余次数）。同一用户同一题步数递增；**每成功揭一步扣 `pun_user_hint_quota.quota` 1**；次数不足返回业务错误「提示次数不足…」；本题步数用尽返回「本题提示已用尽」。

#### 统一领奖 `/pun/reward/claim`（需登录）

请求体：`{ "type": "share|reward_video|daily_noon_hint_5|daily_watch_ad_hint_1|daily_battle_3_hint_3|permanent_set_avatar|permanent_set_nickname|permanent_my_mini_program_hint_3", "add"?: 1, "subscribeStatus"?: "accept|reject", "templateId"?: "...", "launchScene"?: number|string }`（其中每日任务类 `daily_noon_hint_5` / `daily_watch_ad_hint_1` / `daily_battle_3_hint_3` 可不传订阅相关字段）。  
成功返回示例：`{ "hintAnswerQuota": 12, "added": 1, "type": "share" }`。  
说明：
- `type=share`：用于转发领奖，仍有 60 秒最小间隔与单日上限（5 次）风控。
- `type=reward_video`：用于激励视频完整观看后领奖（与分享风控独立）。
- `type=daily_noon_hint_5`：每个自然日（上海时区）限领一次 +5 次揭字配额，**不限制具体领取时段**；个人小程序场景下不要求订阅模板授权，但需当日答对达到阈值（当前 20 题）后才可领取。
- `type=daily_watch_ad_hint_1`：看广告任务，完整观看后每次 +1 次揭字配额；当前不限制自然日领取次数，与 `reward_video` 领奖类型独立统计。
- `type=daily_battle_3_hint_3`：每日 1V1 任务，当日完成 1V1 对局满 3 局后可领 +3 次揭字配额，自然日限领一次。
- `type=permanent_my_mini_program_hint_3`：永久任务，全生命周期限领一次 +3 次揭字配额；须传 `launchScene`（与客户端 `uni.getLaunchOptionsSync().scene` 一致），且服务端校验为微信「我的小程序」入口（场景值 `1103`/`1104`）或抖音「我的-收藏」入口（`021003` / `21003`）。
- 仅领取成功时写入 `pun_reward_claim_record`；业务拒绝（如超限）与服务器异常不落库。

#### 站内信 `/pun/mail/*`（需登录）

| 接口 | 说明 |
| --- | --- |
| `GET /pun/mail/list` | 查询参数：`page`、`page_size`（默认 20，最大 50）。`data`：`{ list, total }`，列表项含 `id/title/contentPreview/isRead/readAt/createdAt/scope` 等。 |
| `GET /pun/mail/detail` | 查询参数：`id`。打开详情后服务端写入 `pun_game_mail_reads`（幂等）。 |

**新增邮件（无 HTTP 接口）**：由运维在数据库向 `pun_game_mail` 插入记录。`scope=all` 表示全服可见，`target_user_id` 置 `NULL`；`scope=user` 表示仅指定用户可见，需填写 `target_user_id`（`users.id`）。通常设 `is_published=1`；`sender_user_id` 可填 `NULL` 或运营账号 `user_id`。示例见 `docs/migrations/add_pun_game_mail.sql` 文件末尾注释。

### 2.1 谐音梗猜一猜 WebSocket 接口 (1V1 对战)
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
| `/subscribe/save` | POST | 保存微信订阅消息授权状态 |
| `/cron/send-remind` | GET | 内部定时任务：触发发送订阅提醒消息；可选查询参数 **`user_id`**（正整数）时仅对该用户尝试发送，规则与全量一致（已订阅 accept、当日未成功领取 `daily_noon_hint_5`、今日未登录等）。指定 `user_id` 时响应 `data` 会带 **`target_user_id`**、**`matched_user_ids`**（实际 SQL 命中的用户 id，应与之一致）。若微信返回用户拒绝（如 errmsg 含 `user refuse` / errcode `43101`），服务端会将 **`user_subscribes` 对应行改为 `reject`**，避免下次任务重复发送。模板各 `thing*` 字段文案须 **≤约 20 字**（见 `CronService`），否则微信返回 `data.thingN.value invalid` |

---

## 四、 开发与联调指南

### 1. 前端 (UniApp) 注意事项
- **API 规范**：遵循 `uniapp-apis.mdc` 规范，禁止使用原生的 `fetch` 或 `window`，必须统一使用 `uni.request` 和 `uni.setStorageSync`。
- **微信小程序分包**：主包仅 **`pages/index/index`**；其余业务页在分包 **`pages-sub`**（见 `pun-game/src/pages.json` → `subPackages`）。`uni.navigateTo` / `redirectTo` / `reLaunch` 及分享 `path` 中，非首页路由须使用 **`/pages-sub/...`** 前缀（如 `/pages-sub/play/play?level=1`）；首页仍为 **`/pages/index/index`**。仅分包页面使用的逻辑放在 **`pun-game/src/pages-sub/composables/`**、**`pun-game/src/pages-sub/utils/`**（如对战 WebSocket、通关解读、揭字/跳关共享 **`punPlayShared`**、激励视频常量等）与 **`pun-game/src/pages-sub/constants/`**，避免微信主包「未使用 JS」审计将文件算入主包根目录。
- **统一领奖调用（仅微信小程序）**：分享、激励视频、每日任务（`daily_noon_hint_5` / `daily_watch_ad_hint_1` / `daily_battle_3_hint_3`）统一调用 `POST /pun/reward/claim`，按 `type` 区分逻辑；激励视频仍要求完整观看后再领奖。广告位 `adUnitId` 配置在 **`pun-game/src/pages-sub/constants/rewardedVideoAd.js`**（与分包闯关/任务页一致，避免主包未使用 JS 审计）。
- **中级与小红书关卡逻辑**：`mid` 与 `xhs` 关卡 `level` 均可能不连续，不能用 ID 大小判断进度，必须依赖接口 `/pun/level/progress` 返回的 `currentLevel`、`passedLevels`、`totalLevels` 渲染锁定/解锁状态。
- **初级（beginner）题号约定**：关卡号最小为 **1**；`/pun/level/progress?gameTier=beginner` 仅在 `pun_levels` 配置中键 ≥1 且为有效题目时推算 `currentLevel`，避免出现 `0` 导致前端请求不存在的 `issue/0.json`。本地 `pun_game_current_level` 读写也规范为 ≥1。**总关数与是否全通**：以前端拿到的接口字段 **`totalLevels`**（由 `config/pun_levels.php` 决定）为准，前端不写死关数；**全部通关**后提示「恭喜通关全部梗图填词！」并 `reLaunch` 回首页。中级/小红书同理依赖接口与 `pun_levels_issue2` / `pun_levels_issue3`。
- **1V1 对战题库**：`battle` 模式题目来源已与小红书专辑一致（`issue3` / `pun_levels_issue3`），前后端需保持同一题库来源，避免房间下发关卡与校验答案不一致。
- **首页更新弹窗**：进入首页请求 `/pun/changelog/latest`，与本地 `pun_changelog_seen_version`（`version_code`）比对，未读则弹窗；点「知道了」写入本地已读。
- **抖音与微信登录解耦**：`uni.login` 的 `provider` 在抖音侧须为 `toutiao`（见 `pun-game/src/utils/auth.js` 中 `getUniLoginProvider`），后端接口仍走 `/auth/douyin/login`（内部 provider 为 `douyin`）；两者不可混用为同一个字符串。
- **微信小程序转发/朋友圈**：官方 `app.json` / 页面 `*.json` **不包含** `enableShareAppMessage`、`enableShareTimeline`（写入会被开发者工具标为无效字段）；需在页面实现 `onShareAppMessage` / `onShareTimeline`，并在 `App.vue` 的 `onLaunch`/`onShow`（及 `useWechatPageShare` 等）中调用 `uni.showShareMenu({ menus: ['shareAppMessage','shareTimeline'] })` 打开右上角菜单能力。

### 2. 后端 (ThinkPHP 8) 注意事项
- **小程序登录配置**：微信登录读取 `.env` 的 `WECHAT_APPID`、`WECHAT_SECRET`；抖音登录读取 `DOUYIN_APPID`、`DOUYIN_SECRET`。两端都通过 `code2Session` 交换 `openid/session_key`，并统一走 JWT 鉴权。
- **全局访问审计与 IP 黑名单**：`app/middleware.php` 已注册 `AccessLogAndIpBlacklist`。配置见 `config/ip_guard.php`：`blacklist` 为拒绝访问的 IP 列表（支持 IPv4 前缀规则如 `192.168.1.*`），命中返回 HTTP 403（JSON `code=403`）；`access_log_enabled` 控制是否对**所有**请求记一条访问日志（JSON 单行，含解析后的客户端 IP、`X-Forwarded-For`/`X-Real-IP` 原文、方法、路径、UA、耗时、HTTP 状态等，**不记录** `Authorization` 内容，仅 `has_auth`）。日志通道为 `request_audit`，默认写入 `runtime/log/request_audit.log`（见 `config/log.php`）。**黑名单命中**无论是否开启访问日志都会记一条。若部署在反向代理后，请正确配置 ThinkPHP 对代理 IP 的信任策略，否则 `$request->ip()` 可能始终为代理机 IP。
- **分层架构**：Controller 仅负责接收参数与返回统一格式的 JSON，复杂逻辑（如 AI 绘图请求、闯关跳级判定）需下沉到 Service 层。
- **中级/小红书进度策略**：提交答案 `/pun/answer/submit` 时，`mid` 轨仍按题库顺序推进（仅当前可玩关通过才写进度）；`xhs` 轨支持回跳/跨关练习，只要题目存在且答对即可写入 `passed_levels_xhs` 并更新 `max_level_xhs`。
- **答题后跳关返回**：`/pun/answer/submit` 在答对时会直接返回 `nextLevel`（无下一关时为 `null`）与 `totalLevels`，用于前端直接跳转下一关，避免再额外请求 `/pun/level/progress`。
- **passedLevels 顺序约定**：`/pun/level/progress` 返回的 `passedLevels` 按题库关卡定义顺序返回（而非答题时间顺序），避免出现如 `222` 后才出现 `7` 的展示问题。
- **xhs 当前关选择规则**：`/pun/level/progress?gameTier=xhs` 的 `currentLevel` 按关卡号升序计算，返回“最小的未通过且存在的关卡号”；例如已通过 7/8/9 时优先返回 10，若 10 不存在则返回 11（依此类推）。
- **分步提示**：`/pun/level/reveal-hint` 步数存于 **Cache（文件缓存等）**，非 DB；**揭字剩余次数**存于 **`pun_user_hint_quota`**（按用户一行）。对战模式会校验 `pun_game_battle_record` 与 `levels_json` 与题目一致。
- **定时任务**：依赖宿主机或 Docker 的 crontab 执行 `curl http://localhost/cron/send-remind` 以派发「每日领奖提醒」（过滤条件：已订阅 accept、今日未登录且未领取）。下发文案受微信 **`thing` 类型约 20 字上限** 约束，勿在 `CronService` 中写超长 `thing*`。
- **统一响应封装**：所有接口均需返回 `{ "code": 200, "message": "...", "data": {...} }` 格式。

### 3. 运维扩展（可选）
- **飞书机器人（对战开房/开局通知）**：在根目录 `.env` 配置 `FEISHU_WEBHOOK_URL`、`FEISHU_WEBHOOK_SECRET`（勿提交仓库）。逻辑见 `app/common/FeishuBotHelper.php`，由 `BattleService::createRoom` 与 `WebSocket` 对局开始时触发。
- **站内信入信**：不向客户端开放发送接口；在 MySQL 中 `INSERT` `pun_game_mail`（见上节「站内信」与迁移脚本注释示例）。

---

## 五、 文档维护约定

- **唯一总览**：新增或变更 HTTP 接口、WebSocket 事件、数据库表、前端 `api.js` 封装时，须同步更新本文档对应章节；详见项目根目录 `.cursor/rules/documentation.mdc`。
- **数据库脚本**：表结构变更同时维护 `docs/database.sql`。