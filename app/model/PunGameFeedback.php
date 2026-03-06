<?php

namespace app\model;

use think\Model;

/**
 * 谐音梗图游戏 - 意见反馈模型
 */
class PunGameFeedback extends Model
{
    protected $name = 'pun_game_feedback';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'         => 'int',
        'user_id'    => 'int',
        'type'       => 'string',
        'content'    => 'string',
        'contact'    => 'string',
        'created_at' => 'datetime',
    ];

    /** 反馈类型允许值 */
    public const TYPE_BUG = 'bug';
    public const TYPE_SUGGEST = 'suggest';
    public const TYPE_OTHER = 'other';

    public static function allowedTypes(): array
    {
        return [self::TYPE_BUG, self::TYPE_SUGGEST, self::TYPE_OTHER];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
