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
    // # 录入并换算（推荐）
    // php think pun:sync-channel-unit-price --date=2026-05-27 --total=50.00 --remark=当日广告收入

    # 已 INSERT 总价后，只重算单价
    // php think pun:sync-channel-unit-price --date=2026-05-27

    # 重算所有已填 video_total_amount 的日期
    // php think pun:sync-channel-unit-price --all
];
