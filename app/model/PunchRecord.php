<?php

namespace app\model;

use think\Model;

/**
 * 打卡记录模型
 */
class PunchRecord extends Model
{
    protected $name = 'punch_records';
    
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = false;
    
    // 设置字段信息
    protected $schema = [
        'id'         => 'int',
        'user_id'    => 'int',
        'timestamp'  => 'int',
        'created_at' => 'datetime',
    ];
    
    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

