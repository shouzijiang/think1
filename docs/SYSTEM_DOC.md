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
- **pun_game_rank**: 游戏排行榜（user_id, max_level, max_mid_level, updated_at）
- **pun_game_level_progress**: 初级关卡进度表（user_id, level, passed）
- **pun_game_cocreate**: 共创关卡表（user_id, answer, hint_image_prompt, word_array, status 等）
- **pun_game_feedback**: 意见反馈表（user_id, type, content, contact）
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
| `/pun/level/progress` | GET | 获取当前进度（传 `gameTier=mid` 获取中级进度）|
| `/pun/answer/submit` | POST | 提交答题结果（包含初级/中级逻辑分支） |
| `/pun/rank/list` | GET | 获取排行榜（支持分页及 `gameTier` 区分） |
| `/pun/cocreate/words/generate`| POST | 根据答案生成 20 个共创候选字/词 |
| `/pun/cocreate/image/generate`| POST | AI 生成提示图/答案图 (`type: hint/answer`) |
| `/pun/cocreate/submit` | POST | 提交玩家自创关卡 |
| `/pun/cocreate/list` | GET | 获取已审核通过的共创列表 |
| `/pun/cocreate/detail` | GET | 获取某条共创题目的详情 |
| `/pun/cocreate/answer/submit` | POST | 提交共创题目的答题结果 |
| `/pun/feedback/submit` | POST | 提交意见反馈 (`bug/suggest/other`) |
| `/pun/forum/list` | GET | 获取游戏论坛帖子列表 |
| `/pun/forum/detail` | GET | 获取帖子详情及评论列表 |
| `/pun/forum/topic/create` | POST | 发布新帖子 |
| `/pun/forum/reply/create` | POST | 回复帖子或二级评论 |

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
- **中级模式关卡逻辑**：中级关卡的 `level` 往往不连续，不能用 ID 大小判断进度，必须依赖接口 `/pun/level/progress` 返回的 `midCurrentIndex`、`midTotalLevels` 等索引字段渲染关卡锁定/解锁状态。
- **共创分享直达**：共创关卡页的分享路径需携带 `?cocreateId=xxx`，在 `onLoad` 中拦截并直接拉取该共创详情。

### 2. 后端 (ThinkPHP 8) 注意事项
- **分层架构**：Controller 仅负责接收参数与返回统一格式的 JSON，复杂逻辑（如 AI 绘图请求、闯关跳级判定）需下沉到 Service 层。
- **中级防跳关**：在提交答案 `/pun/answer/submit` 且 `gameTier=mid` 时，后端必须通过 `issue2.json` 的索引顺序校验，仅在“提交的是下一关”时才推进 `max_mid_level`，防止恶意跳关。
- **定时任务**：依赖宿主机或 Docker 的 crontab 执行 `curl http://localhost/cron/send-remind` 以派发久坐提醒。
- **统一响应封装**：所有接口均需返回 `{ "code": 200, "message": "...", "data": {...} }` 格式。