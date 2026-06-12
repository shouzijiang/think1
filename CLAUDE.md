# 谐音梗猜一猜 项目规约

## 项目结构

```
后端: ThinkPHP 8.1  (e:\php\think1)
  app/controller/  控制器 → 调用 service
  app/service/      业务逻辑（核心在 PunService，独立模块可拆新 Service）
  route/            路由定义
  docs/database.sql 所有 DDL + 种子数据
  conf/crontab      定时任务
  scripts/          CLI 脚本（读 DB 走 vendor/autoload）

前端: uni-app Vue3 (e:\php\think1\pun-game/src)
  pages/index/         首页
  pages-sub/           子页面（所有游戏页）
  components/          全局组件（Pun 前缀）
  composables/         Vue composables
  constants/           常量 + API 驱动的配置加载
  utils/api.js         所有后端请求
  styles/page-theme.scss 全局 mixin + 公共样式
  pages.json           页面注册
```

## 核心约定

### 后端

- 所有控制器返回统一用 `ResponseHelper::success()` / `ResponseHelper::error()` / `ResponseHelper::badRequest()` / `ResponseHelper::unauthorized()`
- 新增 `gameTier` 时，**三处**都要加：`normalizeMode()`、controller `$levelConfigMap`、`submitAnswer()` 答案查找分支
- 新功能优先考虑拆独立 Service（如 `DailyChallengeService`），不要都堆在 `PunService`
- 数据库变更写 `docs/database.sql`，生产库手工执行
- 宝塔部署 = 替换 PHP 文件即可，**不需重启**

### 前端

- **所有 overlay 组件**（`PunPassSuccessOverlay`、`PunHintOverlay`）用 `:show` prop 控制显隐，**不是 `v-if`**
- 导入用静态 `import`，**不要 `await import()`**（小程序不支持动态导入）
- 页面模板/样式优先参考 `playXhs.vue`，它是标准实现
- scoped CSS 不能穿透子组件 → 用 `:deep(.selector)` 或直接在子组件内改
- CDN 图片用 `https://static2.sofun.online/` 或 `https://sofun.online/static/`

### 部署流程

- **后端**：宝塔替换 PHP 文件 → 即时生效
- **前端**：改 `.vue/.js` → `npm run build:mp-weixin` → 微信开发者工具上传
- **CDN JSON**：`php scripts/gen_category_json.php [--slug=xxx]` 生成 → 上传 `public/static/punGame/`

### 组件复用

| 组件 | 用途 | 关键 prop |
|------|------|-----------|
| PunPassSuccessOverlay | 答对庆祝动画 | `:show` `variant="plain"` `:sub-text` `:tap-anywhere` `@action` |
| PunHintOverlay | 答案提示弹窗 | `:show` `:hint-text` `:step` `:max-steps` `:is-complete` `@close` |
| PunPlayHintShareBar | 查看答案+分享按钮 | `:hint-loading` `:hint-answer-quota` `:hint-locked` `@hint` |
| PunPageNavBar | 页面导航栏 | `:status-bar-height` `:nav-bar-height` `:menu-button-height` `@left-click` |
| PunGlobalWatermark | 水印"《谐音梗猜一猜》" | 无 props |
| PunWrongAnswerFloat | 答错飘字 | `:items` |
| PunAlbumPicker | 专辑选择弹窗 | `:show` `:unlocked-albums` `@select` `@unlock` `@close` |

### 容易踩的坑

1. **新增 mode 必须改 3 处**：`normalizeMode()`、`$levelConfigMap`、`submitAnswer()` 答案分支
2. **overlay 组件必须 `:show` 不是 `v-if`**
3. **小程序不支持动态 `import()`**
4. **scoped CSS 不能跨组件**，`.nav-title` 在父组件写无效
5. **前端改了必须重新编译上传**，宝塔只管后端
6. **`pun_album_category` 是 DB 驱动的**，没有本地 fallback 常量
7. **DB 表新增字段**要手工 ALTER TABLE 执行
