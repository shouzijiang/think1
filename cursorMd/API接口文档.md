# 久坐提醒小程序 - 后端接口文档

## 基础信息

- **接口基础地址**: `https://your-domain.com/api`
- **请求格式**: JSON
- **响应格式**: JSON
- **字符编码**: UTF-8
- **接口版本**: v1

## 通用响应格式

```json
{
  "code": 200,           // 状态码：200成功，其他失败
  "message": "success",  // 提示信息
  "data": {}             // 数据内容
}
```

## 状态码说明

- `200`: 成功
- `400`: 参数错误
- `401`: 未授权/登录失效
- `403`: 无权限
- `404`: 资源不存在
- `500`: 服务器错误

---

## 1. 微信授权登录

### 1.1 微信登录 - 获取 openid

**接口地址**: `POST /auth/wechat/login`

**接口说明**: 通过微信 code 获取 openid 和 session_key，并完成用户登录/注册

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| code | string | 是 | 微信登录凭证，通过 `uni.login()` 获取 |
| encryptedData | string | 否 | 加密的用户信息（如果获取了用户信息） |
| iv | string | 否 | 加密算法的初始向量（如果获取了用户信息） |

**请求示例**:
```json
{
  "code": "081xxxxxx",
  "encryptedData": "",
  "iv": ""
}
```

**响应数据**:
```json
{
  "code": 200,
  "message": "登录成功",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",  // JWT token，用于后续接口鉴权
    "openid": "oUpF8uMuAJO_M2pxb1Q9zNjWeS6o",          // 用户openid
    "unionid": "o6_bmasdasdsad6_2sgVt7hMZOPfL",        // 用户unionid（如果有）
    "user_id": 123,                                     // 用户ID
    "nickname": "用户昵称",                              // 用户昵称
    "avatar": "https://...",                            // 用户头像
    "expires_in": 7200                                  // token过期时间（秒）
  }
}
```

**PHP实现要点**:
1. 调用微信接口 `https://api.weixin.qq.com/sns/jscode2session` 获取 openid
2. 使用 `appid`、`secret`、`code` 换取 `openid` 和 `session_key`
3. 根据 openid 查询用户，不存在则创建新用户
4. 生成 JWT token 返回给前端

---

### 1.2 更新用户信息

**接口地址**: `POST /auth/user/update`

**接口说明**: 更新用户昵称、头像等信息

**请求头**:
```
Authorization: Bearer {token}
```

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| nickname | string | 否 | 用户昵称 |
| avatar | string | 否 | 用户头像URL |

**响应数据**:
```json
{
  "code": 200,
  "message": "更新成功",
  "data": {
    "user_id": 123,
    "nickname": "新昵称",
    "avatar": "https://..."
  }
}
```

---

## 2. 打卡相关接口

### 2.1 提交打卡记录

**接口地址**: `POST /punch/submit`

**接口说明**: 用户提交一次站立打卡记录

**请求头**:
```
Authorization: Bearer {token}
```

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| timestamp | int | 否 | 打卡时间戳（毫秒），不传则使用服务器时间 |

**请求示例**:
```json
{
  "timestamp": 1704067200000
}
```

**响应数据**:
```json
{
  "code": 200,
  "message": "打卡成功",
  "data": {
    "record_id": 456,              // 打卡记录ID
    "timestamp": 1704067200000,    // 打卡时间戳
    "today_count": 5,              // 今日打卡次数
    "total_count": 128,            // 总打卡次数
    "streak_days": 7               // 连续打卡天数
  }
}
```

**数据库表设计建议**:
```sql
CREATE TABLE `punch_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `timestamp` bigint(20) NOT NULL COMMENT '打卡时间戳（毫秒）',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_timestamp` (`user_id`, `timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 2.2 获取打卡记录列表

**接口地址**: `GET /punch/records`

**接口说明**: 获取用户的打卡记录列表，支持分页

**请求头**:
```
Authorization: Bearer {token}
```

**请求参数** (Query参数):

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认1 |
| page_size | int | 否 | 每页数量，默认20，最大100 |
| start_date | string | 否 | 开始日期，格式：YYYY-MM-DD |
| end_date | string | 否 | 结束日期，格式：YYYY-MM-DD |

**请求示例**:
```
GET /punch/records?page=1&page_size=20
```

**响应数据**:
```json
{
  "code": 200,
  "message": "success",
  "data": {
    "list": [
      {
        "record_id": 456,
        "timestamp": 1704067200000,
        "date": "2024-01-01",
        "time": "14:30"
      }
    ],
    "pagination": {
      "page": 1,
      "page_size": 20,
      "total": 128,
      "total_pages": 7
    }
  }
}
```

---

### 2.3 获取打卡统计数据

**接口地址**: `GET /punch/statistics`

**接口说明**: 获取用户的打卡统计数据

**请求头**:
```
Authorization: Bearer {token}
```

**请求参数** (Query参数):

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| date | string | 否 | 查询日期，格式：YYYY-MM-DD，不传则查询今天 |

**响应数据**:
```json
{
  "code": 200,
  "message": "success",
  "data": {
    "today_count": 5,        // 今日打卡次数
    "total_count": 128,      // 总打卡次数
    "streak_days": 7,        // 连续打卡天数
    "week_count": 35,        // 本周打卡次数
    "month_count": 120       // 本月打卡次数
  }
}
```

---

## 3. 用户设置接口

### 3.1 保存用户设置

**接口地址**: `POST /settings/save`

**接口说明**: 保存用户的提醒设置（上下班时间、提醒间隔等）

**请求头**:
```
Authorization: Bearer {token}
```

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| enabled | int | 是 | 是否启用提醒：1启用，0关闭 |
| work_start_time | string | 是 | 上班时间，格式：HH:mm，如 "09:00" |
| work_end_time | string | 是 | 下班时间，格式：HH:mm，如 "18:00" |
| remind_interval | int | 是 | 提醒间隔（小时），1-6 |

**请求示例**:
```json
{
  "enabled": 1,
  "work_start_time": "09:00",
  "work_end_time": "18:00",
  "remind_interval": 2
}
```

**响应数据**:
```json
{
  "code": 200,
  "message": "保存成功",
  "data": {
    "settings_id": 789,
    "enabled": 1,
    "work_start_time": "09:00",
    "work_end_time": "18:00",
    "remind_interval": 2,
    "updated_at": "2024-01-01 12:00:00"
  }
}
```

**数据库表设计建议**:
```sql
CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用提醒',
  `work_start_time` varchar(5) NOT NULL DEFAULT '09:00' COMMENT '上班时间',
  `work_end_time` varchar(5) NOT NULL DEFAULT '18:00' COMMENT '下班时间',
  `remind_interval` int(11) NOT NULL DEFAULT 2 COMMENT '提醒间隔（小时）',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 3.2 获取用户设置

**接口地址**: `GET /settings/get`

**接口说明**: 获取用户的提醒设置

**请求头**:
```
Authorization: Bearer {token}
```

**响应数据**:
```json
{
  "code": 200,
  "message": "success",
  "data": {
    "enabled": 1,
    "work_start_time": "09:00",
    "work_end_time": "18:00",
    "remind_interval": 2
  }
}
```

---

## 4. 订阅消息相关接口

### 4.1 保存订阅消息授权

**接口地址**: `POST /subscribe/save`

**接口说明**: 保存用户的订阅消息授权状态和模板ID

**请求头**:
```
Authorization: Bearer {token}
```

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| template_id | string | 是 | 订阅消息模板ID |
| subscribe_status | string | 是 | 授权状态：accept-接受，reject-拒绝 |

**请求示例**:
```json
{
  "template_id": "xxxxx",
  "subscribe_status": "accept"
}
```

**响应数据**:
```json
{
  "code": 200,
  "message": "保存成功",
  "data": {
    "subscribe_id": 123
  }
}
```

**数据库表设计建议**:
```sql
CREATE TABLE `user_subscribes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `template_id` varchar(100) NOT NULL COMMENT '模板ID',
  `subscribe_status` varchar(20) NOT NULL COMMENT '授权状态',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_template` (`user_id`, `template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 4.2 发送订阅消息（定时任务接口）

**接口说明**: 此接口由后端定时任务调用，用于发送提醒消息

**接口地址**: `内部调用 /cron/send-remind`

**实现逻辑**:
1. 查询所有启用提醒的用户
2. 检查用户是否在工作时间内
3. 检查是否到了提醒时间（根据用户的设置和上次提醒时间）
4. 查询用户是否授权了订阅消息
5. 调用微信接口发送订阅消息

**微信发送订阅消息接口**:
```
POST https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token=ACCESS_TOKEN
```

**请求参数**:
```json
{
  "touser": "oUpF8uMuAJO_M2pxb1Q9zNjWeS6o",  // 用户openid
  "template_id": "xxxxx",                     // 模板ID
  "page": "pages/index/index",                // 点击消息跳转的页面
  "data": {
    "thing1": {
      "value": "久坐提醒"
    },
    "time2": {
      "value": "2024年1月1日 14:30"
    },
    "thing3": {
      "value": "您已经坐了很久了，站起来活动一下吧！"
    }
  }
}
```

**PHP定时任务实现建议**:
- 使用 Linux crontab 或 PHP 定时任务框架
- 建议每5-10分钟执行一次
- 需要获取微信 access_token（建议缓存，2小时有效期）

---

## 5. 用户表设计

```sql
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `openid` varchar(100) NOT NULL COMMENT '微信openid',
  `unionid` varchar(100) DEFAULT NULL COMMENT '微信unionid',
  `nickname` varchar(100) DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_openid` (`openid`),
  KEY `idx_unionid` (`unionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 6. 接口鉴权说明

### JWT Token 使用方式

1. **获取Token**: 通过 `/auth/wechat/login` 接口获取
2. **使用Token**: 在请求头中携带 `Authorization: Bearer {token}`
3. **Token刷新**: Token过期后需要重新登录

### PHP JWT 实现示例

```php
// 生成token
use Firebase\JWT\JWT;

$payload = [
    'user_id' => $user_id,
    'openid' => $openid,
    'exp' => time() + 7200  // 2小时过期
];

$token = JWT::encode($payload, $secret_key, 'HS256');

// 验证token
try {
    $decoded = JWT::decode($token, $secret_key, ['HS256']);
    $user_id = $decoded->user_id;
} catch (Exception $e) {
    // token无效
}
```

---

## 7. 微信接口配置

### 需要配置的参数

1. **小程序 AppID**: 在 `manifest.json` 中配置
2. **小程序 AppSecret**: 后端配置，用于获取 access_token
3. **订阅消息模板ID**: 在小程序后台申请，配置到后端

### 获取 access_token

```
GET https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=APPID&secret=APPSECRET
```

**响应**:
```json
{
  "access_token": "ACCESS_TOKEN",
  "expires_in": 7200
}
```

**注意**: access_token 需要缓存，2小时内有效，建议使用 Redis 或文件缓存。

---

## 8. 错误码说明

| 错误码 | 说明 |
|--------|------|
| 200 | 成功 |
| 400 | 参数错误 |
| 401 | 未授权/Token失效 |
| 402 | 微信登录失败 |
| 403 | 无权限 |
| 404 | 资源不存在 |
| 500 | 服务器错误 |
| 501 | 订阅消息发送失败 |

---

## 9. 接口调用示例（PHP）

### 示例1: 微信登录接口

```php
<?php
// /api/auth/wechat/login.php

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$code = $input['code'] ?? '';

// 1. 调用微信接口获取openid
$appid = 'your_appid';
$secret = 'your_secret';
$url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$secret}&js_code={$code}&grant_type=authorization_code";

$response = file_get_contents($url);
$wechat_data = json_decode($response, true);

if (isset($wechat_data['errcode'])) {
    echo json_encode([
        'code' => 402,
        'message' => '微信登录失败: ' . $wechat_data['errmsg']
    ]);
    exit;
}

$openid = $wechat_data['openid'];
$session_key = $wechat_data['session_key'];

// 2. 查询或创建用户
$user = getUserByOpenid($openid);
if (!$user) {
    $user = createUser($openid);
}

// 3. 生成JWT token
$token = generateToken($user['id'], $openid);

// 4. 返回结果
echo json_encode([
    'code' => 200,
    'message' => '登录成功',
    'data' => [
        'token' => $token,
        'openid' => $openid,
        'user_id' => $user['id'],
        'expires_in' => 7200
    ]
]);
```

### 示例2: 提交打卡

```php
<?php
// /api/punch/submit.php

header('Content-Type: application/json');

// 1. 验证token
$token = getBearerToken();
$user = verifyToken($token);
if (!$user) {
    echo json_encode(['code' => 401, 'message' => '未授权']);
    exit;
}

// 2. 获取参数
$input = json_decode(file_get_contents('php://input'), true);
$timestamp = $input['timestamp'] ?? time() * 1000;

// 3. 保存打卡记录
$record_id = savePunchRecord($user['id'], $timestamp);

// 4. 计算统计数据
$stats = calculateStatistics($user['id']);

// 5. 返回结果
echo json_encode([
    'code' => 200,
    'message' => '打卡成功',
    'data' => [
        'record_id' => $record_id,
        'timestamp' => $timestamp,
        'today_count' => $stats['today_count'],
        'total_count' => $stats['total_count'],
        'streak_days' => $stats['streak_days']
    ]
]);
```

---

## 10. 注意事项

1. **安全性**:
   - 所有接口都需要验证 token（除了登录接口）
   - 验证用户权限，防止越权访问
   - 对用户输入进行验证和过滤

2. **性能优化**:
   - 使用 Redis 缓存 access_token
   - 打卡记录查询使用索引
   - 统计数据可以定时计算并缓存

3. **错误处理**:
   - 统一错误响应格式
   - 记录错误日志
   - 微信接口调用失败要有重试机制

4. **定时任务**:
   - 建议使用队列系统处理订阅消息发送
   - 避免重复发送提醒
   - 记录发送日志

---

## 11. 前端调用示例

### 登录接口调用

```javascript
// utils/api.js
const BASE_URL = 'https://your-domain.com/api'

export function login(code) {
  return uni.request({
    url: `${BASE_URL}/auth/wechat/login`,
    method: 'POST',
    data: { code }
  })
}
```

### 提交打卡

```javascript
export function submitPunch(timestamp) {
  const token = uni.getStorageSync('token')
  return uni.request({
    url: `${BASE_URL}/punch/submit`,
    method: 'POST',
    header: {
      'Authorization': `Bearer ${token}`
    },
    data: { timestamp }
  })
}
```

---

## 12. 测试建议

1. **单元测试**: 测试各个接口的业务逻辑
2. **集成测试**: 测试完整的业务流程
3. **压力测试**: 测试接口的并发性能
4. **微信接口测试**: 在微信开发者工具中测试

---

**文档版本**: v1.0  
**最后更新**: 2024-01-01

