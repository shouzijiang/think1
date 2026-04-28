<?php

namespace app\model;

use think\Model;

/**
 * 统一领奖记录
 */
class PunRewardClaimRecord extends Model
{
    protected $name = 'pun_reward_claim_record';

    protected $autoWriteTimestamp = false;

    protected $schema = [
        'id' => 'int',
        'user_id' => 'int',
        'claim_type' => 'string',
        'claim_date' => 'string',
        'add_quota' => 'int',
        'status' => 'string',
        'reason' => 'string',
        'meta_json' => 'string',
        'created_at' => 'datetime',
    ];
}
