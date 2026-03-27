<?php

namespace app\model;

use think\Model;

/**
 * 谐音梗图游戏 - 论坛帖子模型
 */
class PunForumTopic extends Model
{
    protected $name = 'pun_forum_topic';

    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'          => 'int',
        'user_id'     => 'int',
        'title'       => 'string',
        'content'     => 'string',
        'view_count'  => 'int',
        'reply_count' => 'int',
        'status'      => 'int',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function replies()
    {
        return $this->hasMany(\app\model\PunForumReply::class, 'topic_id', 'id');
    }
}
