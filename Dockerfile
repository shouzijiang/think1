# 二开推荐阅读[如何提高项目构建效率](https://developers.weixin.qq.com/miniprogram/dev/wxcloudrun/src/scene/build/speed.html)
# 选择构建用基础镜像（选择原则：在包含所有用到的依赖前提下尽可能体积小）。如需更换，请到[dockerhub官方仓库](https://hub.docker.com/_/php?tab=tags)自行选择后替换。
FROM alpine:3.19

# 容器默认时区为UTC，如需使用上海时间请启用以下时区设置命令
# RUN apk add tzdata && cp /usr/share/zoneinfo/Asia/Shanghai /etc/localtime && echo Asia/Shanghai > /etc/timezone

# 使用 HTTPS 协议访问容器云调用证书安装
RUN apk add ca-certificates

# 安装依赖包，如需其他依赖包，请到alpine依赖包管理(https://pkgs.alpinelinux.org/packages?name=php8*imagick*&branch=v3.13)查找。
# 选用国内镜像源以提高下载速度
RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.tencent.com/g' /etc/apk/repositories \
    && apk add --update --no-cache \
    php83 \
    php83-fpm \
    php83-common \
    php83-json \
    php83-ctype \
    php83-exif \
    php83-pdo \
    php83-pdo_mysql \
    php83-curl \
    php83-mbstring \
    php83-xml \
    php83-session \
    php83-tokenizer \
    php83-dom \
    php83-simplexml \
    php83-fileinfo \
    php83-phar \
    php83-opcache \
    nginx \
    dcron \
    curl \
    && rm -f /var/cache/apk/*

# 设定工作目录
WORKDIR /app

# 将当前目录下所有文件拷贝到/app （.dockerignore中文件除外）
COPY . /app

# 替换nginx、fpm、php配置
RUN mkdir -p /run/nginx \
    && mkdir -p /etc/nginx/http.d \
    && cp /app/conf/nginx.conf /etc/nginx/http.d/default.conf \
    # 2. 复制 PHP 配置
    && cp /app/conf/fpm.conf /etc/php83/php-fpm.d/www.conf \
    && cp /app/conf/php.ini /etc/php83/php.ini \
    # 3. 权限处理
    && chmod -R 777 /app/runtime \
    && ln -sf /usr/sbin/php-fpm83 /usr/sbin/php-fpm

# 暴露端口
EXPOSE 80

# 执行启动命令.
CMD ["sh", "run.sh"]