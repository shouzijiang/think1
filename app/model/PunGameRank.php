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
        'id'                     => 'int',
        'user_id'                => 'int',
        'max_level'              => 'int',
        'max_level_mid'          => 'int',
        'max_level_xhs'          => 'int',
        'last_pass_at_beginner'  => 'datetime',
        'last_pass_at_mid'       => 'datetime',
        'last_pass_at_xhs'       => 'datetime',
        'updated_at'             => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
