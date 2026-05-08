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
        'mp_platform' => 'string',
        'unionid'     => 'string',
        'nickname'    => 'string',
        'avatar'      => 'string',
        'last_login_at' => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];
    
}

