<?php

namespace app\model;

use think\Model;

/**
 * 谐音梗图游戏 - 邮件主体（站内信）
 */
class PunGameMail extends Model
{
    protected $name = 'pun_game_mail';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'            => 'int',
        'scope'         => 'string',
        'target_user_id' => 'int',
        'sender_user_id' => 'int',
        'title'         => 'string',
        'content'       => 'string',
        'is_published'  => 'int',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];
}

