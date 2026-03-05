<?php

namespace app\model;

use think\Model;

/**
 * 谐音梗图游戏 - 排行榜模型
 */
class PunGameRank extends Model
{
    protected $name = 'pun_game_rank';

    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = false;
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'         => 'int',
        'user_id'    => 'int',
        'nickname'   => 'string',
        'avatar'     => 'string',
        'max_level'  => 'int',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
