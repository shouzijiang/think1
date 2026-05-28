<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'websocket:start' => \app\command\WebSocket::class,
        'pun:generate-level-explain' => \app\command\PunGenerateLevelExplain::class,
    ],
];
