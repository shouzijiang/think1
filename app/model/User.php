<?php

namespace app\model;

use think\Model;

/**
 * 用户模型
 */
class User extends Model
{
    protected $name = 'users';
    
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    
    // 设置字段信息
    protected $schema = [
        'id'          => 'int',
        'openid'      => 'string',
        'unionid'     => 'string',
        'nickname'    => 'string',
        'avatar'      => 'string',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];
    
    /**
     * 关联用户设置
     */
    public function settings()
    {
        return $this->hasOne(UserSetting::class, 'user_id');
    }
    
    /**
     * 关联打卡记录
     */
    public function punchRecords()
    {
        return $this->hasMany(PunchRecord::class, 'user_id');
    }
}

