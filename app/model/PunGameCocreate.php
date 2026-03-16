<?php

namespace app\model;

use think\Model;

/**
 * 谐音梗图游戏 - 共创关卡
 */
class PunGameCocreate extends Model
{
    protected $name = 'pun_game_cocreate';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    /** 选词存 JSON 数组 */
    protected $json = ['word_array'];

    protected $schema = [
        'id'                => 'int',
        'user_id'           => 'int',
        'answer'            => 'string',
        'answer_length'     => 'int',
        'hint_image_prompt' => 'string',
        'answer_explanation' => 'string',
        'word_array'        => 'json',
        'hint_image_url'    => 'string',
        'answer_image_url'  => 'string',
        'status'            => 'int',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    /** 状态：待审核 */
    public const STATUS_PENDING = 0;
    /** 状态：已通过 */
    public const STATUS_APPROVED = 1;
    /** 状态：拒绝 */
    public const STATUS_REJECTED = 2;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
