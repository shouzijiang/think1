<?php

namespace app\model;

use think\Model;

/**
 * 消息发送日志模型
 */
class MessageLog extends Model
{
    protected $name = 'message_logs';
    
    protected $autoWriteTimestamp = false;
    
    // 设置字段信息
    protected $schema = [
        'id'          => 'int',
        'user_id'     => 'int',
        'template_id' => 'string',
        'send_status' => 'string',
        'error_msg'   => 'string',
        'send_time'   => 'datetime',
    ];
    
    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

