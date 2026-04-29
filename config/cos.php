<?php

/**
 * 腾讯云 COS 配置
 * 敏感信息通过环境变量注入，本地开发可在 .env 中配置
 */
return [
    'secret_id'  => env('COS_SECRET_ID', ''),
    'secret_key' => env('COS_SECRET_KEY', ''),
    'region'     => env('COS_REGION', 'ap-guangzhou'),
    'bucket'     => env('COS_BUCKET', ''),
    // CDN 或自定义域名，留空则使用 COS 默认域名
    'cdn_domain' => env('COS_CDN_DOMAIN', ''),
    // 上传文件大小上限（字节），默认 5MB
    'max_size'   => (int) env('COS_MAX_SIZE', 5 * 1024 * 1024),
    // 允许上传的 MIME 类型
    'allow_mime' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    // 用户头像存放目录前缀
    'avatar_prefix' => 'avatars/',
];
