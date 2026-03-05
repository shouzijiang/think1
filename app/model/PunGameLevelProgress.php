<?php

namespace app\model;

use think\Model;

/**
 * 谐音梗图游戏 - 用户关卡进度模型
 */
class PunGameLevelProgress extends Model
{
    protected $name = 'pun_game_level_progress';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'         => 'int',
        'user_id'    => 'int',
        'level'      => 'int',
        'passed'     => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
