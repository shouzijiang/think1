@echo off
REM ThinkPHP 启动脚本（Windows）

if "%PORT%"=="" set PORT=80
if "%HOST%"=="" set HOST=0.0.0.0
set ROOT=%~dp0public

echo 启动 ThinkPHP 服务器...
echo 监听地址: %HOST%:%PORT%
echo 文档根目录: %ROOT%
echo.

php -S %HOST%:%PORT% -t %ROOT% %ROOT%\router.php

