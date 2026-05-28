<?php

namespace app\model;

use think\Model;

/**
 * 谐音梗关卡 AI 趣味解读（按关卡类型 + 编号预生成）
 */
class PunLevelAiExplain extends Model
{
    protected $name = 'pun_level_ai_explain';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'           => 'int',
        'game_tier'    => 'string',
        'level_no'     => 'int',
        'explain_text' => 'string',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];
}
