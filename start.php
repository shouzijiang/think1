<?php
// ThinkPHP 内置服务器启动脚本
// 用于云平台部署，支持端口配置

$port = getenv('PORT') ?: 80;
$host = getenv('HOST') ?: '0.0.0.0';
$root = __DIR__ . '/public';

echo "启动 ThinkPHP 服务器...\n";
echo "监听地址: {$host}:{$port}\n";
echo "文档根目录: {$root}\n\n";

$command = sprintf(
    'php -S %s:%d -t %s %s',
    $host,
    $port,
    $root,
    escapeshellarg($root . '/router.php')
);

passthru($command);

