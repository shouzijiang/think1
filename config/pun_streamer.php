<?php

/**
 * 邀请人（streamer）结算配置
 */
return [
    /** 未录入 pun_game_channel_unit_price 时的默认单条视频单价 */
    'default_video_unit_price' => (float) env('PUN_STREAMER_DEFAULT_VIDEO_PRICE', 0.01),
    /** 满多少元可提现（原样展示，不做截断/四舍五入） */
    'withdraw_min_amount'      => env('PUN_STREAMER_WITHDRAW_MIN', '1'),
    /** 前端/API 展示金额小数位（截断，不四舍五入） */
    'display_amount_scale'     => (int) env('PUN_STREAMER_DISPLAY_AMOUNT_SCALE', 3),
    /** 内部累加精度（与 video_unit_price decimal(10,4) 一致） */
    'calc_amount_scale'        => 4,
];
