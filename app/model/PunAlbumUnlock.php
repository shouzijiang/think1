<?php

namespace app\model;

use think\Model;

/**
 * 专辑解锁记录（观看激励视频后解锁分类专辑）
 */
class PunAlbumUnlock extends Model
{
    protected $name = 'pun_album_unlock';

    protected $autoWriteTimestamp = false;

    protected $schema = [
        'id'         => 'int',
        'user_id'    => 'int',
        'album_type' => 'string',
        'album_name' => 'string',
        'created_at' => 'datetime',
    ];
}
