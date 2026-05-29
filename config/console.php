<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'websocket:start' => \app\command\WebSocket::class,
        'pun:generate-level-explain' => \app\command\PunGenerateLevelExplain::class,
        'pun:sync-channel-unit-price' => \app\command\PunSyncChannelUnitPrice::class,
    ],
    // 定时任务（默认单价 0.01，见 conf/crontab）：
    // php think pun:sync-channel-unit-price --yesterday

    // 录入并换算（拿到广告总收入后）：
    // php think pun:sync-channel-unit-price --date=2026-05-27 --total=0.57 --remark=当日广告收入

    // 已改表 video_total_amount 后，只重算单价：
    // php think pun:sync-channel-unit-price --date=2026-05-27

    // 重算所有已填 video_total_amount 的日期：
    // php think pun:sync-channel-unit-price --all
];
