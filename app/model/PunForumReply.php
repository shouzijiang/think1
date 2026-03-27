<?php

namespace app\model;

use think\Model;

/**
 * 谐音梗图游戏 - 论坛回复模型
 */
class PunForumReply extends Model
{
    protected $name = 'pun_forum_reply';

    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = false; // 回复目前不记录更新时间

    protected $schema = [
        'id'          => 'int',
        'topic_id'    => 'int',
        'user_id'     => 'int',
        'reply_to_id' => 'int',
        'content'     => 'string',
        'status'      => 'int',
        'created_at'  => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function topic()
    {
        return $this->belongsTo(PunForumTopic::class, 'topic_id', 'id');
    }

    // 关联回复的目标 (比如回复某人的某条评论)
    public function targetReply()
    {
        return $this->belongsTo(self::class, 'reply_to_id', 'id');
    }
}
