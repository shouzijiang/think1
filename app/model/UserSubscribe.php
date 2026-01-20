<?php

namespace app\model;

use think\Model;

/**
 * 用户订阅消息模型
 */
class UserSubscribe extends Model
{
    protected $name = 'user_subscribes';
    
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    
    // 设置字段信息
    protected $schema = [
        'id'              => 'int',
        'user_id'         => 'int',
        'template_id'     => 'string',
        'subscribe_status' => 'string',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];
    
    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

