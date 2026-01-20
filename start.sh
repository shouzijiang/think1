#!/bin/bash
# ThinkPHP 启动脚本（Linux/Mac）

PORT=${PORT:-80}
HOST=${HOST:-0.0.0.0}
ROOT=$(pwd)/public

echo "启动 ThinkPHP 服务器..."
echo "监听地址: $HOST:$PORT"
echo "文档根目录: $ROOT"
echo ""

php -S $HOST:$PORT -t $ROOT $ROOT/router.php

