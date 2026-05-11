<?php

namespace app\model;

use think\Model;

/**
 * 谐音梗猜一猜游戏 - 版本更新说明（首页弹窗）
 */
class PunGameChangelog extends Model
{
    protected $name = 'pun_game_changelog';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'           => 'int',
        'version_code' => 'string',
        'title'        => 'string',
        'body'         => 'string',
        'is_published' => 'int',
        'published_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];
}
