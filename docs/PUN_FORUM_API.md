# 谐音梗图游戏 - 论坛功能接口文档

## 基础说明
所有接口路径前缀：`/pun`

---

## 1. 获取帖子列表
- **路径**：`GET /pun/forum/list`
- **鉴权**：不需要（选传 Token）
- **参数**：
  | 字段 | 类型 | 必填 | 说明 |
  | --- | --- | --- | --- |
  | page | int | 否 | 页码，默认 1 |
  | page_size | int | 否 | 每页条数，默认 20，最大 50 |

- **返回示例**：
```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "list": [
      {
        "id": 1,
        "user_id": 123,
        "nickname": "张三",
        "avatar": "https://...",
        "title": "大家卡在第几关了？",
        "content": "我卡在第30关过不去了...",
        "view_count": 10,
        "reply_count": 2,
        "created_at": "2024-05-20 12:00",
        "updated_at": "2024-05-20 14:00"
      }
    ],
    "total": 1
  }
}
```

---

## 2. 发布新帖子
- **路径**：`POST /pun/forum/topic/create`
- **鉴权**：**需要 Token**
- **请求体 (Body/JSON)**：
  | 字段 | 类型 | 必填 | 说明 |
  | --- | --- | --- | --- |
  | title | string | 否 | 标题（最多 100 字，留空也可以） |
  | content | string | 是 | 正文内容（最多 2000 字） |

- **返回示例**：
```json
{
  "code": 200,
  "msg": "发布成功",
  "data": {
    "id": 1
  }
}
```

---

## 3. 获取帖子详情及回复
- **路径**：`GET /pun/forum/detail`
- **鉴权**：不需要（选传 Token）
- **参数**：
  | 字段 | 类型 | 必填 | 说明 |
  | --- | --- | --- | --- |
  | id | int | 是 | 帖子的 ID |
  | page | int | 否 | 评论的页码，默认 1 |
  | page_size | int | 否 | 每页评论条数，默认 20 |

- **返回示例**：
```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "topic": {
      "id": 1,
      "user_id": 123,
      "nickname": "张三",
      "avatar": "https://...",
      "title": "大家卡在第几关了？",
      "content": "我卡在第30关过不去了...",
      "view_count": 11,
      "reply_count": 2,
      "created_at": "2024-05-20 12:00"
    },
    "replies": {
      "list": [
        {
          "id": 101,
          "user_id": 456,
          "nickname": "李四",
          "avatar": "https://...",
          "content": "我才第10关",
          "reply_to_id": 0,
          "reply_to_user": "",
          "created_at": "2024-05-20 13:00"
        },
        {
          "id": 102,
          "user_id": 789,
          "nickname": "王五",
          "avatar": "https://...",
          "content": "加油兄弟",
          "reply_to_id": 101,
          "reply_to_user": "李四",
          "created_at": "2024-05-20 14:00"
        }
      ],
      "total": 2
    }
  }
}
```
*说明：获取详情时，后端的阅读量 `view_count` 会自动 +1。*

---

## 4. 回复帖子/回复评论
- **路径**：`POST /pun/forum/reply/create`
- **鉴权**：**需要 Token**
- **请求体 (Body/JSON)**：
  | 字段 | 类型 | 必填 | 说明 |
  | --- | --- | --- | --- |
  | topic_id | int | 是 | 所属帖子的 ID |
  | content | string | 是 | 回复内容（最多 1000 字） |
  | reply_to_id | int | 否 | 目标评论 ID（如果直接回复帖子传 0，如果是回复某个人传那个人的 reply id） |

- **返回示例**：
```json
{
  "code": 200,
  "msg": "回复成功",
  "data": null
}
```
