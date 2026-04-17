<?php

namespace app\model;

use think\Model;

/**
 * 谐音梗图游戏 - 邮件已读记录
 */
class PunGameMailRead extends Model
{
    protected $name = 'pun_game_mail_reads';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'read_at';
    protected $updateTime = false;

    protected $schema = [
        'id'      => 'int',
        'mail_id' => 'int',
        'user_id' => 'int',
        'read_at' => 'datetime',
    ];
}

