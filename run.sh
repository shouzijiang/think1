#!/bin/sh

# 安装定时任务
if [ -f /app/conf/crontab ]; then
    crontab -u root /app/conf/crontab
fi

# 启动 cron 服务
crond -f -l 2 &

# 后台启动
php-fpm -D
# 关闭后台启动，hold住进程
nginx -g 'daemon off;'