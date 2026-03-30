<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class PunGameBattleRecord extends Model
{
    protected $name = 'pun_game_battle_record';

    protected $autoWriteTimestamp = 'datetime';

    // JSON字段自动转换
    protected $type = [
        'levels_json' => 'json',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id', 'id')->bind(['creator_nickname' => 'nickname', 'creator_avatar' => 'avatar']);
    }

    public function challenger()
    {
        return $this->belongsTo(User::class, 'challenger_id', 'id')->bind(['challenger_nickname' => 'nickname', 'challenger_avatar' => 'avatar']);
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id', 'id');
    }
}
