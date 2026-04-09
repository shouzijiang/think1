<?php

namespace app\model;

use think\Model;

/**
 * 谐音梗图 - 用户揭字提示剩余次数（独立表，不冗余在 users）
 */
class PunUserHintQuota extends Model
{
    /** 新用户 / 首次懒创建时的默认揭字次数 */
    public const DEFAULT_QUOTA = 10;

    protected $name = 'pun_user_hint_quota';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'         => 'int',
        'user_id'    => 'int',
        'quota'      => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
