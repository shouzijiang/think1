<?php

namespace app\model;

use think\Model;

/**
 * 用户设置模型
 */
class UserSetting extends Model
{
    protected $name = 'user_settings';
    
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    
    // 设置字段信息
    protected $schema = [
        'id'               => 'int',
        'user_id'          => 'int',
        'enabled'          => 'int',
        'work_start_time'  => 'string',
        'work_end_time'    => 'string',
        'remind_interval'  => 'int',
        'last_remind_time' => 'int',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];
    
    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

